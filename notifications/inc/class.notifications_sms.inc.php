<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Aleksander Adamowski <aleksander.adamowski@olo.org.pl>
 * @version $Id$
 */

/**
 * User notification via winpopup.
 */
class notifications_sms implements notifications_iface {

	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * Login table in SQL database
	 */
	const _login_table = 'egw_access_log';
	
	/**
	 * holds account object for user who sends the message
	 *
	 * @var object
	 */
	private $sender;
	
	/**
	 * holds account object for user to notify
	 *
	 * @var object
	 */
	private $recipient;
	
	/**
	 * holds config object (sitewide application config)
	 *
	 * @var object
	 */
	private $config;
	
	/**
	 * holds preferences object of user to notify
	 *
	 * @var object
	 */
	private $preferences;
	
	/**
	 * holds the sms sending command. the script should accept two arguments: mobile number and
	 * SMS body in ASCII.
	 *
	 * @abstract 
	 * Example: /usr/bin/sendsms
	 * (sendsms is from smstools)
	 *
	 * @var string
	 */
	private $sendsms_command = '/usr/sbin/sendsms';
	
	/**
	 * constructor of notification_sms
	 *
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_config
	 * @param object $_preferences
	 */
	public function __construct($_sender, $_recipient, $_config = null, $_preferences = null) {
		if(!is_object($_sender)) { throw new Exception("no sender given."); }
		if(!is_object($_recipient)) { throw new Exception("no recipient given."); }
		$this->sender = $_sender;
		$this->recipient = $_recipient;
		$this->config = $_config;
		$this->preferences = $_preferences;
	}
	
	/**
	 * sends notification
	 *
	 * @param string $_subject
	 * @param array $_messages
	 * @param array $_attachments
	 */
	public function send(array $_messages, $_subject = false, $_links = false, $_attachments = false) {
		if(!$this->sendsms_command) {
			throw new Exception(	'SMS plugin not configured yet. Skipped sending notification message. '.
									'Please check var "sms_command" in SMS backend '.
									'('.EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.notification_sms.inc.php).');
		}
		//error_log('_messages: '.var_export($_messages, TRUE)."\n");
		$this->send_sms( $_subject.$_messages['plain']);
		return true;
	}

	/**
	 * Try to guess a locale supported by the server, with fallback to 'en_EN' and 'C'
	 * borrowed from projectmanager/inc/class.ganttchart.inc.php
	 *
	 * @return string
	 */
	function guess_locale() {
		$fullprefs = new preferences($this->recipient->account_id);
		$fullpreferences = $fullprefs->read();
		$lang = $fullpreferences['common']['lang'];
		$country = $fullpreferences['common']['country'];

		if (strlen($lang) == 2) { 
			$country_from_lang = strtoupper($lang);
		} else { 
			list($lang,$country_from_lang) = explode('-',$lang);
			$country_from_lang = strtoupper($country_from_lang);
		}
		$old_locale = setlocale(LC_CTYPE, '0');
		if (setlocale(LC_CTYPE,$locale=$lang.'_'.$country)) return $old_locale;
		if (setlocale(LC_CTYPE,$locale=$lang.'_'.$country_from_lang)) return $old_locale;
		if (setlocale(LC_CTYPE,$locale=$lang)) return $old_locale;
		if (setlocale(LC_CTYPE,$locale='en_EN')) return $old_locale;
		return $old_locale;
	}
	
	/**
	 * sends the sms message via command line script specified above
	 *
	 * @param string $_message
	 * @param array $_user_sessions
	 */
	private function send_sms( $_message) {
		$account_id = $this->recipient->account_id;
		// Theoretically one could send unicode text SMS by using UCS-2 encoding for the message.
		// My modem, however, rejected UCS-2 encoded text so I convert to plain ASCII:
		if (function_exists('iconv')) { 
			$funcToUse = 'iconv';
		} else if (function_exists('mb_convert_encoding')) { 
			$funcToUse = 'mb_convert_encoding';
		}
		$old_locale = $this->guess_locale();
		//error_log("New LC_CTYPE locale: ".setlocale(LC_CTYPE, '0'));
		$source_charset = $GLOBALS['egw']->translation->charset();
		if ($funcToUse == 'iconv') {
			$converted_message = iconv($source_charset, 'ASCII//TRANSLIT', $_message);
		} else if ($funcToUse == 'mb_convert_encoding') {
			$converted_message = mb_convert_encoding($_message, 'ASCII', $source_charset);
		}
		setlocale(LC_CTYPE, $old_locale);
		//error_log("Restored old LC_CTYPE locale: ".setlocale(LC_CTYPE, '0'));

		$addressbook = new addressbook_bo();
		$contact = $addressbook->read('account:'.$account_id);
		$cell = $contact['tel_cell'];
		// remove all characters that are non-digit and not a plus sign:
		$cell = preg_replace('/[^0-9]/', '', $cell);
		if (!preg_match('/48[^0][0-9]{8}/', $cell)) {
			error_log('Invalid cell number: '.$cell);
			throw new Exception("Failed sending notification message via sms. Invalid mobile number: ".$cell);
		}		

		$command = $this->sendsms_command;
		// just in case to be sure of safeness:
		$escaped_cell = escapeshellarg($cell);
		$command .= ' '.$cell;

		// The first four rows of alarm message contain redundant text
		// that's unneeded in SMS notifications, and space is precious,
		// so we remove them:
		// Or maybe not:
		//$converted_message = preg_replace("/^([^\n]*\r?\n){4}/", "", $converted_message);
		// SMS length limit of max. 3 SMS concatenated:
		$converted_message = substr($converted_message, 0, 160*3);
		// escape any dangerous content from the message:
		$escaped_message = escapeshellarg($converted_message);
		$command .= ' '.$escaped_message;
		//error_log('SMS command: '.$command."\n");
		exec($command,$output,$returncode);
		if($returncode != 0) {
			throw new Exception("Failed sending notification message via sms. Error while executing the specified command.");
		}
	}

}
