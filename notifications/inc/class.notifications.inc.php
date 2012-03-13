<?php
/**
 * EGroupware - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

if (!defined('SEP')) define('SEP','/');
 
/**
 * Notifies users according to their preferences.
 * 
 * @abstract NOTE: This is for instant notifications. If you need time dependend notifications use the 
 * asyncservices wrapper!
 *
 * This class takes care about the notification-routing. It chooses one or more backends for each
 * given recipient depending on its prefs or falls back to self::_fallback 
 * 
 * The classes doing the notifications are called notifications_<backend> and should only be 
 * called from this class. The backend's job is to deliver ONE message to ONE recipient.
 *
 */
final class notifications {

	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * backend to use for fallback reasons
	 */
	const _fallback = 'email_only';
	
	/**
	 * registered backends
	 * @var array
	 */
	private $backends = array('popup', 'winpopup', 'email', 'sms');
	
	/**
	 * backends to skip even if the user has chosen it
	 * this could be set by the calling application
	 * @var array
	 */
	private $skip_backends = array();
	
	/** 
	 * pre-defined notificaton chains
	 * @abstract
	 * arrays with name => chain pairs
	 * the chain itself consists of an array with framework => action pairs 
	 * where action defines what to do after the framework has been executed:
	 * stop: stop executing notifications
	 * fail: do not stop if framework fails, otherwise stop
	 * continue: execute next framework
	 *
	 * @var array
	 */
	private $notification_chains = array(
		'disable' 				=> false, // will be created by $this->get_available_chains
		'email_only' 			=> false, // will be created by $this->get_available_chains
		'all' 					=> false, // will be created by $this->get_available_chains
 		'popup_only' 			=> array('popup' => 'stop'),
 		'popup_or_email' 		=> array('popup' => 'fail', 'email' => 'stop'),
 		'popup_or_sms' 			=> array('popup' => 'fail', 'sms' => 'stop'),
 		'popup_and_email' 		=> array('popup' => 'continue', 'email' => 'stop'),
 		'popup_and_winpopup'	=> array('popup' => 'continue', 'winpopup' => 'stop'),
		'winpopup_only' 		=> array('winpopup' => 'stop'),
		'winpopup_or_email'		=> array('winpopup' => 'fail', 'email' => 'stop'),
		//'winpopup_or_sms'		=> array('winpopup' => 'fail', 'sms' => 'stop'),
		'winpopup_and_email' 	=> array('winpopup' => 'continue', 'email' => 'stop'),
		'sms_only' 				=> array('sms' => 'stop'),
	);

	/** 
	 * human readable descriptions for the notification chains
	 * @var array
	 */
	private $chains_descriptions = array(
		'disable' 				=> 'do not notify me at all',
		'email_only' 			=> 'E-Mail only',
		'all' 					=> 'all possible notification backends',
		'popup_only' 			=> 'eGroupWare-Popup only',
		'popup_or_email' 		=> 'eGroupWare-Popup first, if that fails notify me by E-Mail',
		'popup_or_sms' 			=> 'eGroupware-Popup first, if that fails notify me by SMS',
		'popup_and_email' 		=> 'eGroupWare-Popup and E-Mail',
		'popup_and_winpopup'	=> 'eGroupWare-Popup and Windows-Popup',
		'winpopup_only' 		=> 'Windows-Popup only',
		'winpopup_or_email' 	=> 'Windows-Popup first, if that fails notify me by E-Mail',
		//'winpopup_or_sms' 		=> 'Windows-Popup first, if that fails notify me by SMS',
		'winpopup_and_email' 	=> 'Windows-Popup and E-Mail',
		'sms_only' 				=> 'SMS only',
	);
	
	/**
	 * array with objects of receivers
	 * @var array
	 */
	private $receivers = array();
	
	/**
	 * object of sender
	 * @var object
	 */
	private $sender;
	
	/**
	 * holds notification subject
	 * @var string
	 */
	private $subject = '';
	
	/**
	 * holds notification message in plaintext
	 * @var string
	 */
	private $message_plain = '';
	
	/**
	 * holds notification message in html
	 * @var string
	 */
	private $message_html = '';
	
	/**
	 * array with objects of links
	 * @var array
	 */
	private $links = array();
	
	/**
	 * array with objects of attachments
	 * @var array
	 */
	private $attachments = array();
		
	/**
	 * holds config object (sitewide configuration of app)
	 *
	 * @var object
	 */
	private $config;
	
	/**
	 * constructor of notifications
	 *
	 */
	public function __construct() {
		$this->config = (object) config::read(self::_appname);
	}
	
	/**
	 * Set sender for the current notification
	 *
	 * @param $_sender object of account
	 * as long as the accounts class isn't a nice object,
	 * it's an int with the account id or the e-mail address of a non-eGW user
	 */
	public function set_sender($_sender) {
		if(is_object($_sender)) {
			$this->sender = $_sender;
			return true;
		} else {
			// no object atm, we have to handle this and make a pseudo-object
			if(is_numeric($_sender)) {
				$this->sender = (object) $GLOBALS['egw']->accounts->read($_sender);
				return true;
			}
			if(is_string($_sender) && strpos($_sender,'@')) {
				$this->sender = (object) array (
									'account_email' => $this->get_addresspart($_sender,'email'),
									'account_fullname' => $this->get_addresspart($_sender,'fullname'),	
									);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Set receivers for the current notification
	 *
	 * @param array $_receivers array with objects of accounts
	 * as long as the accounts class isn't a nice object,
	 * it's an array with the int of the account id or the e-mail address of a non-eGW user
	 */
	public function set_receivers(array $_receivers) {
		$this->receivers = array();
		foreach ($_receivers as $receiver) {
			$this->add_receiver($receiver);
		}
	}
	
	/**
	 * Add single receiver for the current notification
	 *
	 * @param $_receiver object of account
	 * as long as the accounts class isn't a nice object,
	 * it's an int with the account id or the e-mail address of a non-eGW user
	 */
	public function add_receiver($_receiver) {
		if(is_object($_receiver)) {
			$this->receivers[] = $_receiver;
			return true;
		} else {
			// no object atm, we have to handle this and make a pseudo-object
			if(is_numeric($_receiver)) {
				$this->receivers[] = (object) $GLOBALS['egw']->accounts->read($_receiver);
				return true;
			}
			if(is_string($_receiver) && strpos($_receiver,'@')) {
				$this->receivers[] = (object) array (
									'account_email' => $this->get_addresspart($_receiver,'email'),
									'account_fullname' => $this->get_addresspart($_receiver,'fullname'),	
									);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * sets notification subject
	 * 
	 * @param string $_subject
	 */
	public function set_subject($_subject) {
		$this->subject = $_subject;
		return true;
	}
	
	/**
	 * sets notification message
	 * @abstract $_message accepts plaintext or html
	 * NOTE: There is no XSS prevention in notifications framework! 
	 * You have to filter userinputs yourselve (e.g. htmlspechialchars() )
	 * If you want to set plain AND html messages, just call this function
	 * two times, it autodetects the type of your input
	 * 
	 * @param string $_message
	 */
	public function set_message($_message) {
		if(strlen($_message) == strlen(strip_tags($_message))) {
			$this->message_plain = $_message;
		} else {
			$this->message_html = $_message;
		}
		return true;
	}
	
	/**
	 * sets the notification links
	 * 
   * @param array $_links link array (like defined in $this->add_link)
   */
	public function set_links(array $_links) {
		$this->links = array(); // clear array if set
		foreach($_links as $link) {
			if(is_array($link)) {
				$this->add_link($link['text'], $link['view'], $link['popup']);
			}
		}
		return true;
	}
	
	/**
	 * adds a notification link
	 *
	 * @param string $_text a descriptive text for the link
	 * @param array $_view all params needed to view the link (name => value pairs)
	 * @param string $_popup if link can be viewed in a popup something like '300x200' otherwise false
	 */
	public function add_link($_text, $_view, $_popup = false) {
		if(!$_view || !$_text) { return false; }
		$this->links[] = (object)array(	'text'	=> $_text,
										'view'	=> $_view,
										'popup'	=> $_popup,
										);
		return true;
	}
	
	/**
	 * sets the notification attachments
	 * 
	 * @param array $_attachments attachment array (like defined in $this->add_attachment)
	 */
	public function set_attachments(array $_attachments) {
		$this->attachments = array(); // clear array if set
		foreach($_attachments as $attachment) {
			if(is_array($attachment)) {
				$this->add_attachment(	$attachment['string'],
										$attachment['filename'],
										$attachment['encoding'],
										$attachment['type']
										);
			}
		}
		return true;
	}
	
	/**
	 * adds a notification attachment
	 * This method can be used to attach ascii or binary data,
	 * such as a BLOB record from a database.
	 * 
	 * @param string $_string Attachment data.
	 * @param string $_filename Name of the attachment.
	 * @param string $_encoding File encoding (see $Encoding).
	 * @param string $_type File extension (MIME) type.
	 */
	public function add_attachment($_string, $_filename, $_encoding = "base64", $_type = "application/octet-stream") {
		if(!$_string || !$_filename) { return false; }
		$this->attachments[] = (object)array(	'string' => $_string,
												'filename' => $_filename,
												'encoding' => $_encoding,
												'type' => $_type,
												);
		return true;
	}
	
	/**
	 * Sets backends that should be skipped even if the user
	 * defined them in its chain
	 *
	 * @param array $_skip_backends array with names of the backends to be skipped
	 * e.g. array('popup', 'winpopup')
	 */
	public function set_skip_backends(array $_skip_backends) {
		$this->skip_backends = $_skip_backends;
	}
	
	/**
	 * sends notifications
	 */
	public function send() {
		if (!is_object($this->sender)) {
			throw new Exception('Error: cannot send notifications. No sender supplied');
		}
		if (!is_array($this->receivers) || count($this->receivers) == 0) {
			throw new Exception('Error: cannot send notifications. No receivers supplied');
		}
		if(!$messages = $this->create_messages($this->message_plain, $this->message_html)) {
			throw new Exception('Error: cannot send notifications. No valid messages supplied');
		}
		
		$available_chains = $this->get_available_chains('routing');
		
		foreach ($this->receivers as $receiver) {
			$user_notified = false;
			$prepend_message = '';
			$backend_errors = array();
			try {
				// system or non-system user
				if($receiver->account_id && is_numeric($receiver->account_id)) {
					// system user
					$receiver->handle = $receiver->account_lid;
					// check if the receiver has rights to run the notifcation app
					$ids = $GLOBALS['egw']->accounts->memberships($receiver->account_id,true);
					$ids[] = $receiver->account_id;
					if ($GLOBALS['egw']->acl->get_specific_rights_for_account($ids,'run','notifications')) {
						// read the users notification chain
						$prefs = new preferences($receiver->account_id);
						$preferences = $prefs->read();
						$preferences = (object)$preferences[self::_appname];
						if($preferences->notification_chain) {
							// fallback: admin disabled user-chosen chain
							if(!$notification_chain = $available_chains[$preferences->notification_chain]) {
								$prepend_message .= lang(	'This eGroupWare notification has been sent to you by mail because your'
															.' chosen notification-chain has been disabled by the administrator.'
															.' Please choose another notification-chain in your preferences!');
								$notification_chain = $available_chains[self::_fallback];
							}
						} else {
							$notification_chain = $available_chains[self::_fallback]; // fallback: no prefs
						}
					} else {
						$notification_chain = $available_chains[self::_fallback]; // fallback: no rights to app
					}
				} else {
					// non-system user
					$receiver->handle = $receiver->account_email;
					$notification_chain = $available_chains[self::_fallback]; // fallback: non-system user
				}
				
				if($notification_chain == 'disable') {
					continue; //user disabled notifications
				}

				foreach($notification_chain as $backend => $action) {
					try {
						// check if backend should be skipped
						if( in_array($backend, $this->skip_backends) ) {
							// log as error just for the case too much skipping prevents user from being notified
							$backend_errors[] = $backend.' will be skipped (as defined by calling application)';
							continue;
						}
						
						$notification_backend = self::_appname.'_'.$backend;
						if(!file_exists(EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.'. $notification_backend. '.inc.php')) {
							throw new Exception('file for '.$notification_backend. ' does not exist');
						}
						$obj = new $notification_backend( $this->sender, $receiver, $this->config, $preferences );
						if ( !($obj instanceof notifications_iface) ) {
							unset ( $obj );
					 		throw new Exception($notification_backend. ' is no implementation of notifications_iface');
						}
									
						$obj->send($this->prepend_message($messages, $prepend_message), $this->subject, $this->links, $this->attachments);
					}
					catch (Exception $exception) {
						$backend_errors[] = $notification_backend.' failed: '.$exception->getMessage();
						// try next backend
						if($action == 'fail' || $action == 'continue') {
							continue;
						}
						break; // stop running through chain
					}
					// backend sucseeded
					$user_notified = true;
					if($action == 'stop' || $action == 'fail') { break; } // stop running through chain				
				}
				// check if the user has been notified at all
				/*if(!$user_notified) {
					error_log('Error: notification of receiver '.$receiver->handle.' failed for the following reasons:');
					foreach($backend_errors as $id=>$backend_error) {
						error_log($backend_error);
					}
				}*/
			}
			catch (Exception $exception_user) {
				//error_log('Error: notification of receiver '.$receiver->handle.' failed: '.$exception_user->getMessage());
			}
		}
		return true;
	}
	
	/**
	 * creates an array with the message as plaintext and html
	 *
	 * @param string $_message_plain
	 * @param string $_message_html
	 * @return plain and html message in one array, $messages['plain'] and $messages['html']
	 */
	private function create_messages($_message_plain = '', $_message_html = '') {
		if(empty($_message_plain) && empty($_message_html)) { return false; } // no message set
		$messages = array();
		
		// create the messages
		if(!empty($_message_plain)) {
			$messages['plain'] = $_message_plain;
		} else {
			$messages['plain'] = strip_tags($_message_html);
		}
		
		if(!empty($_message_html)) {
			$messages['html'] = $_message_html;
		} else {
			$messages['html'] = nl2br($_message_plain);
		}
		
		return $messages;
	}
	
	/**
	 * prepends another message to the messages array
	 *
	 * @param array $_messages the messages array from create_messages()
	 * @param string $_prepend just a plain message to prepend, no html!
	 * @return plain and html message in one array including the prepended message, $messages['plain'] and $messages['html']
	 */
	 private function prepend_message(array $_messages, $_prepend = null) {
		if(strlen($_prepend) > 0) {
			foreach($_messages as $key => $value) {
				switch($key) {
					case 'plain':
						$_messages[$key] = $_prepend."\n\n".$value;
						break;
					case 'html':
						// ToDo: move stylesheet to a nicer place
						$_messages[$key] = '<div style="margin:0; padding:1em; margin-bottom: 1em; background-color:orange; border:1px solid red;">'.$_prepend.'</div>'.$value;
						break;
					default:
						break;
				}
			}
		}
		
		return $_messages;
	}
	
	/**
	 * returns specified part from a given mailaddress
	 *
	 * @param string $_address
	 * @param string $_part
	 * @return string chosen part of the address
	 */
	 private function get_addresspart($_address, $_part='email') {
	 	if(strpos($_address,'<')) { // _address contains a fullname part
	 		ereg('^(.*)[:space:]{0,1}<(.*)>',$_address, $parts);
	 		$fullname = trim(trim($parts[1]),'\"');
	 		$email = $parts[2];
	 	} else {
	 		$fullname = false;
	 		$email = $_address;
	 	}
	 	switch($_part) {
	 		case 'fullname':
	 			return $fullname;
	 			break;	
	 		case 'email':
	 		default:
	 			return $email;
	 			break;
	 	}
	 	return false;
	 }
	 
	 /**
	 * returns notification chains based on admin prefs
	 * @abstract the available chains can be retrieved in two different output formats:
	 * routing: array with common and enabled chains, chain-name as key and the chain-array as value (used for message-routing)
	 * human: array with common, enabled and disabled chains, chain-name as key and a human-readable description as value (used for config)
	 *
	 * @param string $_output one of: 'routing' or 'human', defaults to 'routing'
	 * @return array containing notification chains, output like given in $_output
	 */
	public function get_available_chains($_output = 'routing') {
		// determine enabled backends from config
		$enabled_backends = array();
		foreach($this->backends as $id => $backend) {
			switch($backend) {
				case 'email':
					$enabled_backends[$backend] = true; // fallback must always be enabled
					break;
				default:
					$param = $backend.'_enable';
					$enabled_backends[$backend] = $this->config->{$param} == true ? true : false;
					break;
			}
		}
				
		$enabled_chains = array();
		$disabled_chains = array();
		foreach($this->notification_chains as $key => $chain) {
			$allow_chain = true;
			if(is_array($chain)) {
				foreach($chain as $name => $action) {
					if(!$enabled_backends[$name]) {
						$allow_chain = false; // disable whole chain if one backend is disabled
					}
				}
				if($allow_chain) {
					$enabled_chains[$key] = $chain;
				} else {
					$disabled_chains[$key] = $chain;
				}
			}
		}
		
		// common chain
		$common_chains = array();
		$common_chains['disable'] = 'disable';
		$common_chains['email_only'] = array('email' => 'stop');
		// create the 'all' chain from the enabled backends
		$chain_all = array();
		$backend_count = 1;
		foreach($enabled_backends as $backend => $enabled) {
			if($enabled) {
				$chain_all[$backend] = count($enabled_backends) == $backend_count ? 'stop' : 'continue'; 
			}
			$backend_count++;
		}
		$common_chains['all'] = $chain_all;
		
		switch($_output) {
			case 'human':
				$chain_groups = array(
					lang('Common chains')	=> 'common_chains',
					lang('Enabled chains') 	=> 'enabled_chains',
					lang('Disabled chains') => 'disabled_chains',
					);
				$suffix = '_human';
				// create descriptions for each chain key in each group
				foreach($chain_groups as $name => $arr_name) {
					${$arr_name.$suffix} = array();
					foreach(${$arr_name} as $key => $value) {
						if($arr_name == 'disabled_chains') {
							${$arr_name.$suffix}[$key] = '('.lang('Disabled').') '.lang($this->chains_descriptions[$key]);
						} else {
							${$arr_name.$suffix}[$key] = lang($this->chains_descriptions[$key]);
						}
					}
				}
				// summarize all groups with minimum one chain to the final array
				$chains_final = array();
				foreach($chain_groups as $name => $arr_name) {
					if(is_array(${$arr_name.$suffix}) && count(${$arr_name.$suffix}) > 0) {
						$chains_final[$name] = ${$arr_name.$suffix};
					}
				}
				return $chains_final;
				break;
				
			case 'routing':
			default:
				return array_merge($common_chains, $enabled_chains);
				break;
		}
		
		return false;
	}
}
