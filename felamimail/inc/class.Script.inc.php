<?php
/*
 * $Id$
 *
 * Copyright 2002 Stephen Grier <stephengrier@users.sourceforge.net>
 *
 * See the inclosed smartsieve-NOTICE file for conditions of use and distribution.
 */

class Script {

	var $name;         /* filename of script. */
	var $script;       /* full ascii text of script from server. */
	var $size;         /* size of script in bytes. */
	var $so;           /* boolean: is it safe to overwrite script?
											* only safe if we recognise encoding. */
	var $mode;         /* basic or advanced. Smartsieve can only read/write basic. */
	var $rules;        /* array of sieve rules. */
	var $vacation;     /* vacation settings. */
	var $pcount;       /* highest priority value in ruleset. */
	var $errstr;       /* error text. */

	// class constructor
	function Script ($scriptname) {
		$this->name = $scriptname;
		$this->script = '';
		$this->size = 0;
		$this->so = true;
		$this->mode = '';
		$this->rules = array();
		$this->vacation = array();
		$this->pcount = 0;
		$this->errstr = '';
	}

	// get sieve script rules for this user
	function retrieveRules ($connection) {
		#global $_SESSION;
		$continuebit = 1;
		$sizebit = 2;
		$anyofbit = 4;
		$keepbit = 8;
		$regexbit = 128;
 
		if (!isset($this->name)){
			$this->errstr = 'retrieveRules: no script name specified';
			return false;
		}
		
		if (!is_object($connection)) {
			$this->errstr = "retrieveRules: no sieve session open";
			return false;
		}

		// if script doesn't yet exist, nothing to retrieve.
		// safe to write to this script file.
	#LK	if (!AppSession::scriptExists($this->name)) {
	#LK		$this->so = true;
	#LK		return true;
	#LK	}
 
		#print "<br><br><br><br>get Script ". $this->name ."<bR>";
 
		if(PEAR::isError($script = $connection->getScript($this->name))) {
			return $script;
		}
		
		#print "<br>AAA: Script is ". htmlentities($script) ."<br>";
		$lines = array();
		$lines = preg_split("/\n/",$script); //,PREG_SPLIT_NO_EMPTY);

		$rules = array();
		$vacation = array();
		$regexps = array('^ *##PSEUDO','^ *#rule','^ *#vacation','^ *#mode');

		/* first line should be the script size. eg: {123}. */
		#$line = array_shift($lines);
		#if (!preg_match("/^\{(\d+)\}$/", $line, $bits)){
		#		print 'retrieveRules: unexpected value: ' . $line .'<br>';
		#		$this->errstr = 'retrieveRules: unexpected value: ' . $line;
		#		return false;
		#}
	#LK	$this->size = $bits[1];

		/* next line should be the recognised encoded head. if not, the script 
		 * is of an unrecognised format, and we should not overwrite it. */
		$line = array_shift($lines);
		if (!preg_match("/^# ?Mail(.*)rules for/", $line)){
				$this->errstr = 'retrieveRules: encoding not recognised';
				$this->so = false;
				return false;
		}
		$this->so = true;

		$line = array_shift($lines);

		while (isset($line)){
			foreach ($regexps as $regexp){
				if (preg_match("/$regexp/i",$line)){
					$line = rtrim($line);
					if (preg_match("/^ *#rule&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)&&(.*)$/i",$line,$bits)){
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
						$rule['flg']		= $bits[8];   // bitwise flag
						$rule['field']		= stripslashes($bits[9]);
						$rule['field_val']	= stripslashes($bits[10]);
						$rule['size']		= $bits[11];
						$rule['continue']	= ($bits[8] & $continuebit);
						$rule['gthan']		= ($bits[8] & $sizebit); // use 'greater than'
						$rule['anyof']		= ($bits[8] & $anyofbit);
						$rule['keep']		= ($bits[8] & $keepbit);
						$rule['regexp']		= ($bits[8] & $regexbit);
						$rule['unconditional']	= 0;
						if (!$rule['from'] && !$rule['to'] && !$rule['subject'] &&
							!$rule['field'] && !$rule['size'] && $rule['action']) {
							$rule['unconditional'] = 1;
						}
						
						array_push($rules,$rule);
						
						if ($rule['priority'] > $this->pcount) {
							$this->pcount = $rule['priority'];
						}
					}
					
					if (preg_match("/^ *#vacation&&(.*)&&(.*)&&(.*)&&(.*)/i",$line,$bits)) {
						$vacation['days'] = $bits[1];
						$vaddresslist = $bits[2];
						$vaddresslist = preg_replace("/\"|\s/","",$vaddresslist);
						$vaddresses = array();
						$vaddresses = preg_split("/,/",$vaddresslist);
						$vacation['text'] = $bits[3];
						
						// <crnl>s will be encoded as \\n. undo this.
						$vacation['text'] = preg_replace("/\\\\n/","\r\n",$vacation['text']);
						$vacation['status'] = $bits[4];
						$vacation['addresses'] = &$vaddresses;
					}
					
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
 
		return true;
	}
 
 
	// update and save sieve script
	function updateScript ($connection) 
	{
		#global $_SESSION,$default,$sieve;
		global $default,$sieve;
		
		$activerules = 0;
		$regexused = 0;
		$rejectused = 0;
		
		$username	= $GLOBALS['egw_info']['user']['account_lid'];
		$version	= $GLOBALS['egw_info']['apps']['felamimail']['version'];
		
		//include "$default->lib_dir/version.php";
 
		if (!is_object($connection)) 
		{
			$this->errstr = "updateScript: no sieve session open";
			return false;
		}

		// don't overwrite a file if not created by SmartSieve,
		// unless configured to do so.
#LK		if (!$this->so && !$default->allow_write_unrecognised_scripts) {
#LK			$this->errstr = 'updateScript: encoding not recognised: not safe to overwrite ' . $this->name;
#LK			return false;
#LK		}
 
		// lets generate the main body of the script from our rules
 
		$newscriptbody = "";
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
 
				}
 
				// actions
 
				if (!$rule['unconditional']) $newruletext .= ") {\n\t";

				if (preg_match("/folder/i",$rule['action'])) {
						$newruletext .= "fileinto \"" . $rule['action_arg'] . "\";";
				}
				if (preg_match("/reject/i",$rule['action'])) {
						$newruletext .= "reject text: \n" . $rule['action_arg'] . "\n.\n;";
						$rejectused = 1;
				}
				if (preg_match("/address/i",$rule['action'])) {
						$newruletext .= "redirect \"" . $rule['action_arg'] . "\";";
				}
				if (preg_match("/discard/i",$rule['action'])) {
						$newruletext .= "discard;";
				}
				if ($rule['keep']) $newruletext .= "\n\tkeep;";
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
			if (!$vacation['days']) $vacation['days'] = $default->vacation_days;
			if (!$vacation['text']) $vacation['text'] = $default->vacation_text;
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
			if ($vacation['status'] == 'on') {
				$newscriptbody .= "vacation :days " . $vacation['days'] . " :addresses [";
				$first = 1;
				foreach ($vacation['addresses'] as $vaddress) {
						if (!$first) $newscriptbody .= ", ";
						$newscriptbody .= "\"" . $vaddress . "\"";
						$first = 0;
				}
				$newscriptbody .= "] text:\n" . $vacation['text'] . "\n.\n;\n\n";
			}

			// update with any changes.
			$this->vacation = $vacation;
		}
 
		// generate the script head
 
		$newscripthead = "";
		$newscripthead .= "#Mail filter rules for " . $username . "\n";
		$newscripthead .= '#Generated by ' . $username . ' using FeLaMiMail ' . $version . ' ' . date($default->script_date_format);
		$newscripthead .= "\n";
 
		if ($activerules) {
			$newscripthead .= "require [\"fileinto\"";
			if ($regexused) $newscripthead .= ",\"regex\"";
			if ($rejectused) $newscripthead .= ",\"reject\"";
			if ($this->vacation && $this->vacation['status'] == 'on') {
				$newscripthead .= ",\"vacation\"";
			}
			$newscripthead .= "];\n\n";
		} else {
			// no active rules, but might still have an active vacation rule
			if ($this->vacation && $this->vacation['status'] == 'on')
				$newscripthead .= "require [\"vacation\"];\n\n";
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
				$rule['action_arg'] = preg_replace("/\r\n/","\\n",$rule['action_arg']);
				/* reset priority value. note: we only do this
				* for compatibility with Websieve. */
				$rule['priority'] = $pcount;
				$newscriptfoot .= "#rule&&" . $rule['priority'] . "&&" . $rule['status'] . "&&" . 
					$rule['from'] . "&&" . $rule['to'] . "&&" . $rule['subject'] . "&&" . $rule['action'] . "&&" . 
					$rule['action_arg'] . "&&" . $rule['flg'] . "&&" . $rule['field'] . "&&" . $rule['field_val'] . "&&" . $rule['size'] . "\n";
				$pcount = $pcount+2;
			}
		}

		if ($this->vacation) {
				$vacation = $this->vacation;
				$newscriptfoot .= "#vacation&&" . $vacation['days'] . "&&";
				$first = 1;
				foreach ($vacation['addresses'] as $address) {
						if (!$first) $newscriptfoot .= ", ";
						$newscriptfoot .= "\"" . $address . "\"";
						$first = 0;
				}
				
				$vacation['text'] = preg_replace("/\r\n/","\\n",$vacation['text']);
				$newscriptfoot .= "&&" . $vacation['text'] . "&&" . $vacation['status'] . "\n";
		}
		$newscriptfoot .= "#mode&&basic\n";
 
		$newscript = $newscripthead . $newscriptbody . $newscriptfoot;
		$this->script = $newscript;
		#print "<pre>$newscript</pre>"; exit;
		$scriptfile = $this->name;
		#print "<hr><pre>".htmlentities($newscript)."</pre><hr>";
		if (!$connection->installScript($this->name, $newscript, true)) {
			$this->errstr = 'updateScript: putscript failed: ' . $connection->errstr;
			return false;
		}

		return true;
	}


}


?>
