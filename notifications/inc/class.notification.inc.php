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
 * @abstract NOTE:Notifications are small messages. No subject and no attechments. 
 * If you need this kind of elements you probably want to send a mail, don't you :-)
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
	 * holds notification message
	 * @var string
	 */
	private $message = '';
	
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
	 * as long as the accounts class isn't a nice object, it's an int with the account id :-(
	 */
	public function set_sender($_sender) {
		$this->sender = is_object($_sender) ? $_sender : (object) $GLOBALS['egw']->accounts->get_account_data($_sender);
		return true;
	}
	
	/**
	 * Set receivers for the current notification
	 *
	 * @param array $_receivers array with objects of accounts
	 * as long as the accounts class isn't a nice object, it's an array of account id's :-(
	 */
	public function set_receivers(array $_receivers) {
		foreach ($_receivers as $receiver_id) {
			$receiver = $GLOBALS['egw']->accounts->get_account_data($receiver_id);
			$receiver[$receiver_id]['id'] = $receiver_id;
			$this->receivers[$receiver_id] = (object)$receiver[$receiver_id];
		}
		return true;
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
	 * @abstract $message accepts html tags: <p><a><b><br>.
	 * NOTE: There is no XSS prevention in notifications framework! 
	 * You have to filter userinputs yourseve (e.g. htmlspechialchars() )
	 * 
	 * @param string $_message
	 */
	public function set_message($_message) {
		$this->message = $_message;
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
		if (empty($this->receivers) || !$this->message) {
			throw new Exception('Error: Could not send notification. No receiver or no message where supplied');
		}
		// create all message styles
		$messages = $this->create_messages($this->message, $this->links);
		foreach ($this->receivers as $receiver) {
			try {
				// check if the receiver has rights to run the notifcation app
				$ids = $GLOBALS['egw']->accounts->memberships($receiver->id,true);
				$ids[] = $receiver->id;
				if (!$GLOBALS['egw']->acl->get_specific_rights_for_account($ids,'run','notifications')) {
					throw new Exception('Error: Could not send notification to user '.$receiver->lid.' because of missing execute rights on notification-app.');
				}
				
				$prefs = new preferences($receiver->id);
				$preferences = $prefs->read();
				$preferences = (object)$preferences[self::_appname];
				$notification_chain = $this->notification_chains[$preferences->notification_chain];
				if(!is_array($notification_chain)) {
					throw new Exception('Error: Could not send notification to user '.$receiver->lid.' because of missing notification settings.');
				}
			}
			catch (Exception $exception) {
				error_log('notification of receiver'.$receiver->lid.' failed: '.$exception->getMessage());
			}

			$user_notified = false;
			$backend_errors = array();
			foreach($notification_chain as $notification_backend => $action) {
				try {
					$notification_backend = 'notification_'.$notification_backend;
					require_once(EGW_INCLUDE_ROOT. SEP. self::_appname. SEP. 'inc'. SEP. 'class.'. $notification_backend. '.inc.php');
					$obj = @new $notification_backend( $this->sender, $receiver, $this->config, $preferences );
					if ( !is_a( $obj, iface_notification )) {
						unset ( $obj );
					 	throw new Exception('Error: '.$notification_backend. ' is no implementation of iface_notification');
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
						error_log('Error: notification of receiver '.$receiver->lid.' failed');
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
	 * @return array of account objects
	 */
	public function get_receivers() {
		return $this->receivers;
	}
	
	/**
	 * this function creates an array with the message as plaintext and html
	 *
	 * @param string $message
	 * @param array $links
	 * @return array $messages
	 */
	private function create_messages($_message, $_links = false) {
		$messages = array();
		$messages['plain'] = array();
		$messages['html'] = array();
		
		if(strlen($_message) == strlen(strip_tags($_message))) {
			// $_message is plaintext
			$messages['plain']['text'] = $_message;
			$messages['html']['text'] = nl2br($_message);
		} else {
			// $_message already contains html
			$messages['plain']['text'] = strip_tags($_message);
			$messages['html']['text'] = $_message;
		}
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
	
}