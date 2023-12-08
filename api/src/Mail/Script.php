<?php
/**
 * EGroupware Api: Support for Sieve scripts
 *
 * See the inclosed smartsieve-NOTICE file for conditions of use and distribution.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Stephen Grier <stephengrier@users.sourceforge.net>
 * @author Hadi Nategh	<hn@stylite.de>
 * @copyright 2002 by Stephen Grier <stephengrier@users.sourceforge.net>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Translation;

/**
 * Support for Sieve scripts
 */
class Script
{
	var $name;         /* filename of script. */
	var $script;       /* full ascii text of script from server. */
	var $size;         /* size of script in bytes. */
	var $so;           /* boolean: is it safe to overwrite script?
											* only safe if we recognise encoding. */
	var $mode;         /* basic or advanced. Smartsieve can only read/write basic. */
	var $rules;        /* array of sieve rules. */
	var $vacation;     /* vacation settings. */
	var $emailNotification; /* email notification settings. */
	var $pcount;       /* highest priority value in ruleset. */
	var $errstr;       /* error text. */
	var $extensions;	/* contains extensions status*/
	/**
	 * Body transform content types
	 *
	 * @static array
	 */
	static $btransform_ctype_array = array(
		'0' => 'Non',
		'1' => 'image',
		'2' => 'multipart',
		'3' => 'text',
		'4' => 'media',
		'5' => 'message',
		'6' => 'application',
		'7' => 'audio',
	);
	/**
	 * Switch on some error_log debug messages
	 *
	 * @var boolean
	 */
	var $debug=false;

	// class constructor
	function __construct ($scriptname)
	{
		$this->name = $scriptname;
		$this->script = '';
		$this->size = 0;
		$this->so = true;
		$this->mode = '';
		$this->rules = array();
		$this->vacation = array();
		$this->emailNotification = array(); // Added email notifications
		$this->pcount = 0;
		$this->errstr = '';
		$this->extensions = [];
	}

	private function _setExtensionsStatus(Sieve $connection)
	{
		$this->extensions = [
			'vacation' => $connection->hasExtension('vacation'),
			'vacation-seconds' => $connection->hasExtension('vacation-seconds'),
			'regex' => $connection->hasExtension('regex'),
			'enotify' => $connection->hasExtension('enotify'),
			'body' => $connection->hasExtension('body'),
			'variables' => $connection->hasExtension('variables'),
			'date' => $connection->hasExtension('date'),
			'imap4flags' => $connection->hasExtension('imap4flags'),
			'relational' => $connection->hasExtension('relational'),
		];
	}

	// get sieve script rules for this user
	/**
	 * Retrieve the rules
	 *
	 * @param Sieve $connection
	 * @return boolean true, if script written successfull
	 */
	function retrieveRules (Sieve $connection)
	{
		#global $_SESSION;
		$continuebit = 1;
		$sizebit = 2;
		$anyofbit = 4;
		$keepbit = 8;
		$regexbit = 128;
		$this->_setExtensionsStatus($connection);

		if (!isset($this->name)){
			$this->errstr = 'retrieveRules: no script name specified';
			if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.": no script name specified");
			return false;
		}

		// If the called script name is not exist then create it
		// otherwise we might get error due to non existance script
		if (!in_array($this->name, $connection->listScripts()))
		{
			$this->updateScript($connection);
		}

		$script = $connection->getScript($this->name);

		$lines = explode("\n",$script);

		$rules = array();
		$vacation = array();
		$emailNotification = array(); // Added email notifications

		/* next line should be the recognised encoded head. if not, the script
		 * is of an unrecognised format, and we should not overwrite it. */
		$line1 = array_shift($lines);
		if (!preg_match("/^# ?Mail(.*)rules for/", $line1)){
				$this->errstr = 'retrieveRules: encoding not recognised';
				$this->so = false;
				if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.": encoding not recognised");
				return false;
		}
		$this->so = true;

		$line = array_shift($lines);

		while (isset($line))
		{
			$matches = null;
			if (preg_match("/^ *#(#PSEUDO|rule|vacation|mode|notify)/i",$line,$matches))
			{
				$line = rtrim($line);
				switch ($matches[1]){
					case "rule":
						$bits = explode("&&",  $line);
						$rule = array();
						$rule['priority']	= $bits[1];
						$rule['status']		= $bits[2];
						$rule['from']		= stripslashes($bits[3]);
						$rule['to']		= stripslashes($bits[4]);
						$rule['subject']	= stripslashes($bits[5]);
						$rule['action']		= $bits[6];
						$rule['action_arg']	= $bits[7];
						// <crnl>s will be encoded as \\n. undo this.
						$rule['action_arg']	= preg_replace("/\\\\n/","\r\n",$rule['action_arg']);
						$rule['action_arg']	= stripslashes($rule['action_arg']);
						$rule['flg']		= $bits[8] = (int)$bits[8];   // bitwise flag
						$rule['field']		= stripslashes($bits[9]);
						$rule['field_val']	= stripslashes($bits[10]);
						$rule['size']		= $bits[11];
						$rule['continue']	= ($bits[8] & $continuebit);
						$rule['gthan']		= ($bits[8] & $sizebit); // use 'greater than'
						$rule['anyof']		= ($bits[8] & $anyofbit);
						$rule['keep']		= ($bits[8] & $keepbit);
						$rule['regexp']		= ($bits[8] & $regexbit);
						$rule['bodytransform'] = ($bits[12]??null);
						$rule['field_bodytransform'] = ($bits[13]??null);
						$rule['ctype'] = ($bits[14]??null);
						$rule['field_ctype_val'] = ($bits[15]??null);
						$rule['unconditional']	= 0;
						if (!$rule['from'] && !$rule['to'] && !$rule['subject'] &&
							!$rule['field'] && !$rule['size'] && $rule['action']) {
							$rule['unconditional'] = 1;
						}

						array_push($rules,$rule);

						if ($rule['priority'] > $this->pcount) {
							$this->pcount = $rule['priority'];
						}
						break;
					case "vacation" :   // #vacation&&1:days&&2:addresses&&3:text&&4:status/start-end&&5:forwards&&6:modus
						if (preg_match("/^ *#vacation&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)/i",$line,$bits) ||
							preg_match("/^ *#vacation&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)/i",$line,$bits) ||
							preg_match("/^ *#vacation&&(.*)&&(.*)&&(.*)&&(.*)/i",$line,$bits)) {
							$vacation['days'] = $bits[1];
							$vaddresslist = preg_replace("/\"|\s/","",$bits[2]);
							$vaddresses = preg_split("/,/",$vaddresslist);
							if (($vacation['text'] = $bits[3]) === 'false')
							{
								$vacation['text'] = false;
							}
							else
							{
								// <crnl>s will be encoded as \\n. undo this.
								$vacation['text'] = preg_replace("/\\\\n/","\r\n",$vacation['text']);
							}
							$vacation['modus'] = $bits[6] ?? null;

							if (strpos($bits[4],'-')!== false)
							{
								$vacation['status'] = 'by_date';
								list($vacation['start_date'],$vacation['end_date']) = explode('-',$bits[4]);
							}
							else
							{
								$vacation['status'] = $bits[4];
							}
							$vacation['addresses'] = &$vaddresses;

							$vacation['forwards'] = $bits[5]??null;
						}
						break;
					case "notify":
						if (preg_match("/^ *#notify&&(.*)&&(.*)&&(.*)/i",$line,$bits)) {
							$emailNotification['status'] = $bits[1];
							$emailNotification['externalEmail'] = $bits[2];
							$emailNotification['displaySubject'] = $bits[3];
						}
						break;
					case "mode" :
						if (preg_match("/^ *#mode&&(.*)/i",$line,$bits)){
							if ($bits[1] == 'basic')
								$this->mode = 'basic';
							elseif ($bits[1] == 'advanced')
								$this->mode = 'advanced';
							else
								$this->mode = 'unknown';
						}
				}
			}
			$line = array_shift($lines);
		}

		$this->script = $script;
		$this->rules = $rules;
		$this->vacation = $vacation;
		if (!$connection->hasExtension('vacation')) $this->vacation = false;
		$this->emailNotification = $emailNotification; // Added email notifications
		if ($this->debug) error_log(__CLASS__.'::'.__METHOD__.": Script succesful retrieved: ".print_r($vacation,true));

		return true;
	}


	/**
	 * update and save sieve script
	 *
	 * @param Sieve $connection
	 * @param boolean $utf7imap_fileinto =false true: encode foldernames with utf7imap, default utf8
	 * @param string|null& $vac_rule on return vacation-rule in sieve syntax
	 * @param bool $throw_exceptions =false true: throw exception with error-message
	 * @return bool false on error with error-message in $this->error
	 */
	function updateScript (Sieve $connection, $utf7imap_fileinto=false, &$vac_rule=null, bool $throw_exceptions=false)
	{
		#global $_SESSION,$default,$sieve;
		global $default,$sieve;

		$activerules = 0;
		$regexused = 0;
		$rejectused = 0;
		$vacation_active = false;

		$username	= $GLOBALS['egw_info']['user']['account_lid'];
		$version	= $GLOBALS['egw_info']['apps']['mail']['version'];

		//include "$default->lib_dir/version.php";

		// set extensions status
		$this->_setExtensionsStatus($connection);

		if (!$this->extensions['vacation']) $this->vacation = false;

		// for vacation with redirect we need to keep track were the mail goes, to add an explicit keep
		if ($this->extensions['variables'] && $this->vacation)
		{
			$newscriptbody = 'set "action" "inbox";'."\n\n";
		}
		$continue = 1;

		foreach ($this->rules as $rule) {
			$newruletext = "";

			// don't print this rule if disabled.
			if ($rule['status'] != 'ENABLED') {
			} else {
				$activerules = 1;

				// conditions

				$anyall = "allof";
				if ($rule['anyof']) $anyall = "anyof";
				if ($rule['regexp']) {
						$regexused = 1;
				}
				$started = 0;

				if (!$rule['unconditional']) {
						if (!$continue) $newruletext .= "els";
						$newruletext .= "if " . $anyall . " (";
						if ($rule['from']) {
								if (preg_match("/^\s*!/", $rule['from'])){
										$newruletext .= 'not ';
										$rule['from'] = preg_replace("/^\s*!/","",$rule['from']);
								}
								$match = ':contains';
								if (preg_match("/\*|\?/", $rule['from'])) $match = ':matches';
								if ($rule['regexp']) $match = ':regex';
								$newruletext .= "address " . $match . " [\"From\"]";
								$newruletext .= " \"" . addslashes($rule['from']) . "\"";
								$started = 1;
						}
						if ($rule['to']) {
								if ($started) $newruletext .= ", ";
								if (preg_match("/^\s*!/", $rule['to'])){
										$newruletext .= 'not ';
										$rule['to'] = preg_replace("/^\s*!/","",$rule['to']);
								}
								$match = ':contains';
								if (preg_match("/\*|\?/", $rule['to'])) $match = ':matches';
								if ($rule['regexp']) $match = ':regex';
								$newruletext .= "address " . $match . " [\"To\",\"TO\",\"Cc\",\"CC\"]";
								$newruletext .= " \"" . addslashes($rule['to']) . "\"";
								$started = 1;
						}
						if ($rule['subject']) {
								if ($started) $newruletext .= ", ";
								if (preg_match("/^\s*!/", $rule['subject'])){
										$newruletext .= 'not ';
										$rule['subject'] = preg_replace("/^\s*!/","",$rule['subject']);
								}
								$match = ':contains';
								if (preg_match("/\*|\?/", $rule['subject'])) $match = ':matches';
								if ($rule['regexp']) $match = ':regex';
								$newruletext .= "header " . $match . " \"subject\"";
								$newruletext .= " \"" . addslashes($rule['subject']) . "\"";
								$started = 1;
						}
						if ($rule['field'] && $rule['field_val']) {
								if ($started) $newruletext .= ", ";
								if (preg_match("/^\s*!/", $rule['field_val'])){
										$newruletext .= 'not ';
										$rule['field_val'] = preg_replace("/^\s*!/","",$rule['field_val']);
								}
								$match = ':contains';
								if (preg_match("/\*|\?/", $rule['field_val'])) $match = ':matches';
								if ($rule['regexp']) $match = ':regex';
								$newruletext .= "header " . $match . " \"" . addslashes($rule['field']) . "\"";
								$newruletext .= " \"" . addslashes($rule['field_val']) . "\"";
								$started = 1;
						}
						if ($rule['size']) {
								$xthan = " :under ";
								if ($rule['gthan']) $xthan = " :over ";
								if ($started) $newruletext .= ", ";
								$newruletext .= "size " . $xthan . $rule['size'] . "K";
								$started = 1;
						}
						if ($this->extensions['body']){
							if (!empty($rule['field_bodytransform'])){
								if ($started) $newruletext .= ", ";
								$btransform	= " :raw ";
								$match = ' :contains';
								if ($rule['bodytransform'])	$btransform = " :text ";
								if (preg_match("/\*|\?/", $rule['field_bodytransform'])) $match = ':matches';
								if ($rule['regexp']) $match = ':regex';
								$newruletext .= "body " . $btransform . $match . " \"" . $rule['field_bodytransform'] . "\"";
								$started = 1;

							}
							if ($rule['ctype']!= '0' && !empty($rule['ctype'])){
								if ($started) $newruletext .= ", ";
								$btransform_ctype = self::$btransform_ctype_array[$rule['ctype']];
								$ctype_subtype = "";
								if ($rule['field_ctype_val']) $ctype_subtype = "/";
								$newruletext .= "body :content " . " \"" . $btransform_ctype . $ctype_subtype . $rule['field_ctype_val'] . "\"" . " :contains \"\"";
								$started = 1;
								//error_log(__CLASS__."::".__METHOD__.array2string(Script::$btransform_ctype_array));
							}
						}
				}

				// actions

				if (!$rule['unconditional']) $newruletext .= ") {\n\t";

				if (preg_match("/folder/i",$rule['action'])) {
						$newruletext .= "fileinto \"" . ($utf7imap_fileinto ?
							Translation::convert($rule['action_arg'],'utf-8', 'utf7-imap') : $rule['action_arg']) . "\";";
				}
				if (preg_match("/reject/i",$rule['action'])) {
						$newruletext .= "reject text: \n" . $rule['action_arg'] . "\n.\n;";
						$rejectused = 1;
				}
				if (preg_match("/address/i",$rule['action'])) {
						foreach(preg_split('/, ?/',$rule['action_arg']) as $addr)
						{
							$newruletext .= "\tredirect \"".trim($addr)."\";\n";
						}
				}
				if (preg_match("/discard/i",$rule['action'])) {
						$newruletext .= "discard;";
				}
				if (preg_match("/flags/i",$rule['action'])) {
					$newruletext .= "addflag \"".$rule['action_arg']."\";";
				}
				if ($rule['keep'])
				{
					$newruletext .= "\n\tkeep;";
				}
				// for vacation with redirect we need to keep track were the mail goes, to NOT add an explicit keep
				elseif ($this->extensions['variables'] && $this->vacation &&
					preg_match('/(folder|reject|address|discard)/', $rule['action']))
				{
					$newruletext .= "\n\tset \"action\" \"$rule[action]\";";
				}
				if (!$rule['unconditional']) $newruletext .= "\n}";

				$continue = 0;
				if ($rule['continue']) $continue = 1;
				if ($rule['unconditional']) $continue = 1;

				$newscriptbody .= $newruletext . "\n\n";

			} // end 'if ! ENABLED'
		}

		// vacation rule

		if ($this->vacation) {
			$vacation = $this->vacation;
			if ((string)$vacation['days'] === '' || $vacation['days'] < 0) $vacation['days'] = $default->vacation_days ?: 3;
			if (!$vacation['text']) $vacation['text'] = $default->vacation_text ?: '';
			if (!$vacation['status']) $vacation['status'] = 'on';

			// filter out invalid addresses.
			$ok_vaddrs = array();
			foreach($vacation['addresses'] as $addr){
				if ($addr != '' && preg_match("/\@/",$addr))
				array_push($ok_vaddrs,$addr);
			}
			$vacation['addresses'] = $ok_vaddrs;

			if (!$vacation['addresses'][0]){
				$defaultaddr = $sieve->user . '@' . $sieve->maildomain;
				array_push($vacation['addresses'],$defaultaddr);
			}
			if (($vacation['status'] == 'on' && strlen(trim($vacation['text']))>0)|| $vacation['status'] == 'by_date')	// +24*3600 to include the end_date day
			{
				$vacation_active = true;
				$vac_rule = '';
				if ($vacation['text'])
				{
					if ($this->extensions['regex'])
					{
						$vac_rule .= "if header :regex " . '"X-Spam-Status" ' . '"\\\\bYES\\\\b"' . "{\n\tstop;\n}\n"; //stop vacation reply if it is spam
						$regexused = 1;
					}
					else
					{
						// if there are no regex'es supported use a different Anti-Spam Rule: if X-Spam-Status holds
						// additional spamscore information (e.g. BAYES) this rule may prevent Vacation notification
						// TODO: refine rule without using regex
						$vac_rule .= "if header :contains " . '"X-Spam-Status" ' . '"YES"' . "{\n\tstop;\n}\n"; //stop vacation reply if it is spam
					}
				}
				if (trim($vacation['forwards']))
				{
					$if = array();
					foreach ($vacation['addresses'] as $addr)
					{
						$if[] = 'address :contains ["To","TO","Cc","CC"] "' . trim($addr) . '"';
					}
					$vac_rule .= 'if anyof (' . implode(', ', $if) . ") {\n";
					foreach (preg_split('/, ?/', $vacation['forwards']) as $addr)
					{
						$vac_rule .= "\tredirect \"" . trim($addr) . "\";\n";
					}
					// if there is no other action e.g. fileinto before, we need to add an explicit keep, as the implicit on is canceled by the vaction redirect!
					if ($this->extensions['variables']) $vac_rule .= "\tif string :is \"\${action}\" \"inbox\" {\n";
					$vac_rule .= "\t\tkeep;\n";
					if ($this->extensions['variables']) $vac_rule .= "\t}\n";
					$vac_rule .= "}\n";
				}
				if (!isset($vacation['modus']) || $vacation['modus'] !== 'store')
				{
					if ($vacation['days'])
					{
						$vac_rule .= "vacation :days " . $vacation['days'];
					}
					else
					{
						$vac_rule .= "vacation :seconds 1";
					}
					$first = 1;
					if (!empty($vacation['addresses'][0]))
					{
						$vac_rule .= " :addresses [";
						foreach ($vacation['addresses'] as $vaddress)
						{
							if (!$first) $vac_rule .= ", ";
							$vac_rule .= "\"" . trim($vaddress) . "\"";
							$first = 0;
						}
						$vac_rule .= "] ";
					}
					$message = $vacation['text'];
					if ($vacation['status'] === 'by_date' && ($vacation['start_date'] || $vacation['end_date']))
					{
						$format_date = 'd M Y'; // see to it, that there is always a format, because if it is missing - no date will be output
						if (!empty($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'])) $format_date = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
						$message = str_replace(array('$$start$$', '$$end$$'), array(
							date($format_date, $vacation['start_date']),
							date($format_date, $vacation['end_date']),
						), $message);
					}
					$vac_rule .= " text:\n" . $message . "\n.\n;\n\n";
				}
				if (isset($vacation['modus']) && $vacation['modus'] === 'notice')
				{
					$vac_rule .= "discard;\n";
				}
				if ($vacation['status'] === 'by_date' && $this->extensions['date'] && $vacation['start_date'] && $vacation['end_date'])
				{
					$vac_rule = "if allof (\n".
					"currentdate :value \"ge\" \"date\" \"". date('Y-m-d', $vacation['start_date']) ."\",\n".
					"currentdate :value \"le\" \"date\" \"". date('Y-m-d', $vacation['end_date']) ."\")\n".
					"{\n".
						$vac_rule."\n".
					"}\n";
				}
				$newscriptbody .= $vac_rule;
			}

			// update with any changes.
			$this->vacation = $vacation;
		}

		if ($this->emailNotification && $this->emailNotification['status'] == 'on') {
			// format notification email header components
			$notification_email = $this->emailNotification['externalEmail'];

			// format notification body
			$egw_site_title = $GLOBALS['egw_info']['server']['site_title'];
			if ($this->extensions['enotify']==true)
			{
				$notification_body = lang("You have received a new message on the")." {$egw_site_title}";
				if ($this->extensions['variables'])
				{
					$notification_body .= ", ";
					$notification_body .= 'From: ${from}';
					if ($this->emailNotification['displaySubject']) {
						$notification_body .= ', Subject: ${subject}';
					}
					//$notification_body .= 'Size: $size$'."\n";
					$newscriptbody .= 'if header :matches "subject" "*" {'."\n\t".'set "subject" "${1}";'."\n".'}'."\n\n";
					$newscriptbody .= 'if header :matches "from" "*" {'."\n\t".'set "from" "${1}";'."\n".'}'."\n\n";
				}
				else
				{
					$notification_body ="[SIEVE] ".$notification_body;
				}
				$newscriptbody .= 'notify :message "'.$notification_body.'"'."\n\t".'"mailto:'.$notification_email.'";'."\n";
				//$newscriptbody .= 'notify :message "'.$notification_body.'" :method "mailto" :options "'.$notification_email.'?subject='.$notification_subject.'";'."\n";
			}
			else
			{
				$notification_body = lang("You have received a new message on the")." {$egw_site_title}"."\n";
				$notification_body .= "\n";
				$notification_body .= 'From: $from$'."\n";
				if ($this->emailNotification['displaySubject']) {
					$notification_body .= 'Subject: $subject$'."\n";
				}
				//$notification_body .= 'Size: $size$'."\n";

				$newscriptbody .= 'notify :message "'.$notification_body.'" :method "mailto" :options "'.$notification_email.'";'."\n";
				//$newscriptbody .= 'notify :message "'.$notification_body.'" :method "mailto" :options "'.$notification_email.'?subject='.$notification_subject.'";'."\n";
			}
			$newscriptbody .= 'keep;'."\n\n";
		}

		// generate the script head

		$newscripthead = "";
		$newscripthead .= "#Mail filter rules for " . $username . "\n";
		$newscripthead .= '#Generated by ' . $username . ' using Mail ' . $version . ' ' . date($default->script_date_format);
		$newscripthead .= "\n";

		if ($activerules) {
			$newscripthead .= "require [\"fileinto\"";
			if ($this->extensions['regex'] && $regexused) $newscripthead .= ",\"regex\"";
			if ($rejectused) $newscripthead .= ",\"reject\"";
			if ($this->vacation && $vacation_active) {
				$newscripthead .= (string)$this->vacation['days'] === '0' ? ',"vacation-seconds"' : ',"vacation"';
			}
			if ($this->extensions['body']) $newscripthead .= ",\"body\"";
			if ($this->extensions['date']) $newscripthead .= ",\"date\"";
			if ($this->extensions['relational']) $newscripthead .= ",\"relational\"";
			if ($this->extensions['variables']) $newscripthead .= ",\"variables\"";
			if ($this->extensions['imap4flags']) $newscripthead .= ",\"imap4flags\"";

			if ($this->emailNotification && $this->emailNotification['status'] == 'on') $newscripthead .= ',"'.($this->extensions['enotify']?'e':'').'notify"'.($this->extensions['variables']?',"variables"':''); // Added email notifications
			$newscripthead .= "];\n\n";
		} else {
			// no active rules, but might still have an active vacation rule
			$closeRequired = false;
			if ($this->vacation)
			{
				$newscripthead .= 'require['.((string)$this->vacation['days'] === '0' ? '"vacation-seconds"' : '"vacation"');
				if ($this->extensions['variables']) $newscripthead .= ',"variables"';
				if ($this->extensions['regex'] && $regexused) $newscripthead .= ",\"regex\"";
				if ($this->extensions['date']) $newscripthead .= ",\"date\"";
				if ($this->extensions['relational']) $newscripthead .= ",\"relational\"";

				$closeRequired=true;
			}
			if ($this->emailNotification && $this->emailNotification['status'] == 'on')
			{
				if ($this->vacation && $vacation_active)
				{
					$newscripthead .= ",\"".($this->extensions['enotify']?'e':'')."notify\"".($this->extensions['variables']?',"variables"':'')."];\n\n"; // Added email notifications
				}
				else
				{
					$newscripthead .= "require [\"".($this->extensions['enotify']?'e':'')."notify\"".($this->extensions['variables']?',"variables"':'')."];\n\n"; // Added email notifications
				}
			}
			if ($closeRequired) $newscripthead .= "];\n\n";
		}

		// generate the encoded script foot

		$newscriptfoot = "";
		$pcount = 1;
		$newscriptfoot .= "##PSEUDO script start\n";
		foreach ($this->rules as $rule) {
			// only add rule to foot if status != deleted. this is how we delete a rule.
			if ($rule['status'] != 'DELETED') {
				$rule['action_arg'] = addslashes($rule['action_arg']);
				// we need to handle \r\n here.
				$rule['action_arg'] = preg_replace("/\r?\n/","\\n",$rule['action_arg']);
				/* reset priority value. note: we only do this
				* for compatibility with Websieve. */
				$rule['priority'] = $pcount;
				$newscriptfoot .= "#rule&&" . $rule['priority'] . "&&" . $rule['status'] . "&&" .
				addslashes($rule['from']) . "&&" . addslashes($rule['to']) . "&&" . addslashes($rule['subject']) . "&&" . $rule['action'] . "&&" .
				$rule['action_arg'] . "&&" . $rule['flg'] . "&&" . addslashes($rule['field']) . "&&" . addslashes($rule['field_val']) . "&&" . $rule['size'];
				if ($this->extensions['body'] && (!empty($rule['field_bodytransform']) || ($rule['ctype']!= '0' && !empty($rule['ctype'])))) $newscriptfoot .= "&&" . $rule['bodytransform'] . "&&" . $rule['field_bodytransform']. "&&" . $rule['ctype'] . "&&" . $rule['field_ctype_val'];
				$newscriptfoot .= "\n";
				$pcount = $pcount+2;
				//error_log(__CLASS__."::".__METHOD__.__LINE__.array2string($newscriptfoot));
			}
		}

		if ($this->vacation)
		{
			$vacation = $this->vacation;
			$newscriptfoot .= "#vacation&&" . $vacation['days'] . "&&";
			$first = 1;
			foreach ($vacation['addresses'] as $address) {
				if (!$first) $newscriptfoot .= ", ";
				$newscriptfoot .= "\"" . trim($address) . "\"";
				$first = 0;
			}

			$vacation['text'] = $vacation['text'] === false ? 'false' : preg_replace("/\r?\n/","\\n", $vacation['text']);
			$newscriptfoot .= "&&" . $vacation['text'] . "&&" .
				($vacation['status']=='by_date' ? $vacation['start_date'].'-'.$vacation['end_date'] : $vacation['status']);
			$newscriptfoot .= '&&' . ($vacation['forwards'] ?? '');
			if (!empty($vacation['modus'])) $newscriptfoot .= '&&' . $vacation['modus'];
			$newscriptfoot .= "\n";
		}
		if ($this->emailNotification) {
			$emailNotification = $this->emailNotification;
			$newscriptfoot .= "#notify&&" . $emailNotification['status'] . "&&" . $emailNotification['externalEmail'] . "&&" . $emailNotification['displaySubject'] . "\n";
		}

		$newscriptfoot .= "#mode&&basic\n";

		$newscript = $newscripthead . $newscriptbody . $newscriptfoot;
		$this->script = $newscript;
		//error_log(__METHOD__.__LINE__.array2string($newscript));
		//print "<pre>$newscript</pre>"; exit;
		//print "<hr><pre>".htmlentities($newscript)."</pre><hr>";

		try {
			if (!$connection->hasSpace($this->name, mb_strlen($newscript)))
			{
				throw new \Exception(lang("There is no space left to store sieve script, please check sieve_maxscriptsize option on your mailserver's config."));
			}
			$connection->installScript($this->name, $newscript, true);
		}
		catch (\Exception $e) {
			if ($throw_exceptions)
			{
				$e->script = $newscript;
				throw $e;
			}
			$this->errstr = 'updateScript: putscript failed: ' . $e->getMessage().($e->details?': '.$e->details:'');
			if ($regexused && !$this->extensions['regex']) $this->errstr .= " REGEX is not an supported CAPABILITY";
			error_log(__METHOD__.__LINE__.' # Error: ->'.$this->errstr);
			error_log(__METHOD__.__LINE__.' # ScriptName:'.$this->name.' Script:'.$newscript);
			error_log(__METHOD__.__LINE__.' # Instance='.$GLOBALS['egw_info']['user']['domain'].', User='.$GLOBALS['egw_info']['user']['account_lid']);
			return false;
		}

		return true;
	}
}