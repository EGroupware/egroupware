<?php
/**
 * eGroupWare - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.html.inc.php');
require_once(EGW_INCLUDE_ROOT.'/phpgwapi/inc/class.config.inc.php');
 
/**
 * Notifies users according to their preferences.
 * 
 * @abstract NOTE: This is for instant notifications. If you need time dependend notifications use the 
 * asyncservices wrapper!
 * 
 * The classes doing the notifications are called notification_<method> and should only be 
 * called from this class.
 * The <method> gets extractd out of the preferences labels.
 *
 */
final class notification {

	/**
	 * Appname
	 */
	const _appname = 'notifications';
	
	/**
	 * backend to use for fallback reasons
	 */
	const _fallback = 'email_only';
	
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
		'disable' => false,
 		'popup_only' => array('popup' => 'stop'),
		'winpopup_only' => array('winpopup' => 'stop'),
		'email_only' => array('email' => 'stop'),
		'popup_or_email' => array('popup' => 'fail', 'email' => 'stop'),
		'winpopup_or_email' => array('winpopup' => 'fail', 'email' => 'stop'),
		'popup_and_email' => array('popup' => 'continue', 'email' => 'stop'),
		'winpopup_and_email' => array('winpopup' => 'continue', 'email' => 'stop'),
		'egwpopup_and_winpopup' => array('popup' => 'continue', 'winpopup' => 'stop'),
		'all' => array('popup' => 'continue', 'winpopup' => 'continue', 'email' => 'stop'),
	);
	
	/**
	 * array with objects of receivers
	 * @var array
	 */
	private $receivers = array();
	
	/**
	 * objects of sender
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
	 * holds html object to render elements
	 *
	 * @var object
	 */
	private $html;
	
	/**
	 * holds config object (sitewide configuration of app)
	 *
	 * @var object
	 */
	private $config;
	
	/**
	 * constructor of notification
	 *
	 */
	public function __construct() {
		$this->html = & html::singleton();
		$config = new config(self::_appname);
		$this->config = (object) $config->read_repository();
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
   * @param array $links link array (like defined in $this->add_link)
   */
	public function set_links(array $_links) {
		$this->links = array(); // clear array if set
		foreach($_links as $link) {
			if(is_array($link)) {
				$this->add_link($link['menuaction'], $link['params'], $link['text']);
			}
		}
		return true;
	}
	
	/**
	 * adds a notification link
	 * 
	 * @param string $menuaction egw menuaction (appname.classname.functionname)
	 * @param array $params params to append (name => value pairs)
	 * @param string $text a descriptive text for the link
	 */
	public function add_link($_menuaction, $_params, $_text) {
		if(!$_menuaction || !$_params || !$_text) { return false; }
		$this->links[] = (object)array(	'menuaction' => $_menuaction,
										'params' => $_params,
										'text' => $_text,
										);
		return true;
	}
	
	/**
	 * sets the notification attachments
	 * 
	 * @param array $attachments attachment array (like defined in $this->add_attachment
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
	 * @param string $string Attachment data.
	 * @param string $filename Name of the attachment.
	 * @param string $encoding File encoding (see $Encoding).
	 * @param string $type File extension (MIME) type.
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
	 * sends notification 
	 */
	public function send() {
		if (empty($this->receivers) || (empty($this->message_plain) && empty($this->message_html))) {
			throw new Exception('Error: Could not send notification. No receiver or no message where supplied');
		}
		if(!$messages = $this->create_messages($this->message_plain, $this->message_html, $this->links)) {
			throw new Exception('Error: Could not send notification. Generating the messages failed');
		}
		foreach ($this->receivers as $receiver) {
			$user_notified = false;
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
							$notification_chain = $this->notification_chains[$preferences->notification_chain];
						} else {
							$notification_chain = $this->notification_chains[self::_fallback]; // fallback: no prefs
						}
					} else {
						$notification_chain = $this->notification_chains[self::_fallback]; // fallback: no rights to app
					}
				} else {
					// non-system user
					$receiver->handle = $receiver->account_email;
					$notification_chain = $this->notification_chains[self::_fallback]; // fallback: non-system user
				}
				
				if($notification_chain === false) {
					continue; //user disabled notifications
				}

				foreach($notification_chain as $notification_backend => $action) {
					try {
						$notification_backend = 'notification_'.$notification_backend;
						if(!file_exists(EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.'. $notification_backend. '.inc.php')) {
							throw new Exception('file for '.$notification_backend. ' does not exist');
						}
						require_once(EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.'. $notification_backend. '.inc.php');
						$obj = @new $notification_backend( $this->sender, $receiver, $this->config, $preferences );
						if ( !is_a( $obj, iface_notification )) {
							unset ( $obj );
					 		throw new Exception($notification_backend. ' is no implementation of iface_notification');
						}
									
						$obj->send($this->subject, $messages, $this->attachments);
					}
					catch (Exception $exception) {
						$backend_errors[] = $notification_backend.' failed: '.$exception->getMessage();
						// try next backend
						if($action == 'fail' || $action == 'continue') {
							continue;
						}
						// all backends failed - give error message
						if(!$user_notified) {
							error_log('Error: notification of receiver '.$receiver->handle.' failed for the following reasons:');
							foreach($backend_errors as $id=>$backend_error) {
								error_log($backend_error);
							}
						}
						break; // stop running through chain
					}
					// backend sucseeded
					$user_notified = true;
					if($action == 'stop' || $action == 'fail') { break; } // stop running through chain				
				}
			}
			catch (Exception $exception_user) {
				error_log('Error: notification of receiver '.$receiver->handle.' failed: '.$exception_user->getMessage());
			}
		}
		return true;
	}
	
	/**
	 * gets message
	 * 
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}
	
	/**
	 * gets receivers
	 *
	 * @return array of receiver objects
	 */
	public function get_receivers() {
		return $this->receivers;
	}
	
	/**
	 * this function creates an array with the message as plaintext and html
	 * including given links for internal usage or external mailers
	 *
	 * @param string $message_plain
	 * @param string $message_html
	 * @param array $links
	 * @return array $messages
	 */
	private function create_messages($_message_plain = '', $_message_html = '', $_links = false) {
		if(empty($_message_plain) && empty($_message_html)) { return false; } // no message set
		$messages = array();
		$messages['plain'] = array();
		$messages['html'] = array();
		
		// create the messages
		if(!empty($_message_plain)) {
			$messages['plain']['text'] = $_message_plain;
		} else {
			$messages['plain']['text'] = strip_tags($_message_html);
		}
		
		if(!empty($_message_html)) {
			$messages['html']['text'] = $_message_html;
		} else {
			$messages['html']['text'] = nl2br($_message_plain);
		}

		// create the links
		if(is_array($_links)) {
			foreach($_links as $link) {
				$params = '';
				foreach($link->params as $param => $value) {
					$params.='&'.$param.'='.$value;
				}
				$url = $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction='.$link->menuaction.$params;
				$menuaction_arr = explode('.',$link->menuaction);
				$application = $menuaction_arr[0];
				$image = $application ? $this->html->image($application,'navbar',$link->text,'align="middle"').'&nbsp;' : '';
				$messages['plain']['link_internal'] .= "\n".$url;
				$messages['plain']['link_external'] .= "\n".$url.'&no_popup=1';
				$messages['html']['link_internal'] .= '<br /><a href="'.$url.'" target="_blank">'.$image.$link->text.'</a>';
				$messages['html']['link_external'] .= '<br /><a href="'.$url.'&no_popup=1" target="_blank">'.$link->text.'</a>';
				$messages['html']['link_jspopup'] .= '<br /><div onclick="'.$this->popup($url).'">'.$image.$link->text.'</div>';
			}
		}
		
		// create additional formatted info for backends which do not use
		// subject or sender as plain info
		if(is_object($this->sender)) {
			$sender = $this->sender->account_fullname ? $this->sender->account_fullname : $this->sender_account_email;
			$messages['plain']['info_sender'] = lang('Message from').': '.$sender."\n";
			$messages['html']['info_sender'] = lang('Message from').': '.$sender.'<br />';
		}
		if(!empty($this->subject)) {
			$messages['plain']['info_subject'] = $this->subject."\n";
			$messages['html']['info_subject'] = $this->html->bold($this->subject).'<br />';
		}
		
		return $messages;
	}
	
	/**
	 * returns javascript to open a popup window: window.open(...)
	 *
	 * @param string $link link or this.href
	 * @param string $target='_blank' name of target or this.target
	 * @param int $width=750 width of the window
	 * @param int $height=400 height of the window
	 * @return string javascript (using single quotes)
	 */
	private function popup($link,$target='_blank',$width=750,$height=410)
	{
		return 'egw_openWindowCentered2('.($link == 'this.href' ? $link : "'".$link."'").','.
			($target == 'this.target' ? $target : "'".$target."'").",$width,$height,'yes')";
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
	 		ereg('^(.*)[:space:]{0,1}<(.*)>',$_address,&$parts);
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
	
}