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

use EGroupware\Api;

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
class notifications {

	/**
	 * Appname
	 */
	const _appname = 'notifications';

	/**
	 * backend to use for fallback reasons
	 */
	const _fallback = 'email_only';

	/**
	 * backend to use if EGroupware user has no preferenc set and no default preference set
	 */
	const user_fallback = 'popup_or_email';

	/**
	 * registered backends
	 * @var array
	 */
	protected $backends = array('popup', 'winpopup', 'email', 'jpopup');

	/**
	 * backends to skip even if the user has chosen it
	 * this could be set by the calling application
	 * @var array
	 */
	protected $skip_backends = array();

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
	protected $notification_chains = array(
		'disable' 				=> false, // will be created by $this->get_available_chains
		'email_only' 			=> false, // will be created by $this->get_available_chains
		'all' 					=> false, // will be created by $this->get_available_chains
 		'popup_only' 			=> array('popup' => 'stop'),
 		'popup_or_email' 		=> array('popup' => 'fail', 'email' => 'stop'),
 		'popup_and_email' 		=> array('popup' => 'continue', 'email' => 'stop'),
 		'popup_and_winpopup'	=> array('popup' => 'continue', 'winpopup' => 'stop'),
		'winpopup_only' 		=> array('winpopup' => 'stop'),
		'winpopup_or_email'		=> array('winpopup' => 'fail', 'email' => 'stop'),
		'winpopup_and_email' 	=> array('winpopup' => 'continue', 'email' => 'stop'),
		'jpopup_only'			=> array('jpopup' => 'continue', 'popup' => 'stop'),
		'jpopup_and_email'		=> array('jpopup' => 'continue', 'popup' => 'continue', 'email' => 'stop'),
	);

	/**
	 * human readable descriptions for the notification chains
	 * @var array
	 */
	protected $chains_descriptions = array(
		'disable' 				=> 'do not notify me at all',
		'email_only' 			=> 'E-Mail only',
		'all' 					=> 'all possible notification backends',
		'popup_only' 			=> 'eGroupWare-Popup only',
		'popup_or_email' 		=> 'Email notifications only, if user is not logged in',
		'popup_and_email' 		=> 'eGroupWare-Popup and E-Mail',
		'popup_and_winpopup'	=> 'eGroupWare-Popup and Windows-Popup',
		'winpopup_only' 		=> 'Windows-Popup only',
		'winpopup_or_email' 	=> 'Windows-Popup first, if that fails notify me by E-Mail',
		'winpopup_and_email' 	=> 'Windows-Popup and E-Mail',
		'jpopup_only'			=> 'Java-Windows-Popup and eGroupWare-Popup only',
		'jpopup_and_email'		=> 'Java-Windows-Popup, eGroupWare-Popup and Email'
	);

	/**
	 * array with objects of receivers
	 * @var array
	 */
	protected $receivers = array();

	/**
	 * object of sender
	 * @var object
	 */
	protected $sender;

	/**
	 * email address for reply to
	 * @var string
	 */
	protected $reply_to;

	/**
	 * holds notification subject
	 * @var string
	 */
	protected $subject = '';

	/**
	 * holds notification subject for popup
	 * @var string
	 */
	protected $popupsubject = '';

	/**
	 * holds notification message in plaintext
	 * @var string
	 */
	protected $message_plain = '';

	/**
	 * holds notification message in html
	 * @var string
	 */
	protected $message_html = '';

	/**
	 * holds notification message for popup
	 * @var string
	 */
	protected $message_popup = '';

	/**
	 * array with objects of links
	 * @var array
	 */
	protected $links = array();

	/**
	 * array with objects of links
	 * @var array
	 */
	protected $popup_links = array();

	/**
	 * array with objects of data
	 * @var array
	 */
	protected $popup_data = array();

	/**
	 * array with objects of attachments
	 * @var array
	 */
	protected $attachments = array();

	/**
	 * holds config object (sitewide configuration of app)
	 *
	 * @var object
	 */
	protected $config;

	/**
	 * Error-message cat be read and reset via notifications::errors($reset=false)
	 *
	 * @var array
	 */
	protected static $errors = array();

	/**
	 * constructor of notifications
	 *
	 */
	public function __construct() {
		$this->config = (object) Api\Config::read(self::_appname);
	}

	/**
	 * Return notification errors
	 *
	 * @param boolean $reset =false true: reset all errors
	 * @return array
	 */
	public static function errors($reset=false)
	{
		$ret = self::$errors;
		if ($reset) self::$errors = array();
		return $ret;
	}

	/**
	 * Set sender for the current notification
	 *
	 * @param $_sender object of account
	 * as long as the accounts class isn't a nice object,
	 * it's an int with the account id or the e-mail address of a non-eGW user
	 */
	public function set_sender($_sender) {
		if(is_object($_sender))
		{
			$this->sender = $_sender;
			return true;
		}
		else
		{
			// no object atm, we have to handle this and make a pseudo-object
			if(is_numeric($_sender))
			{
				$this->sender = (object)$GLOBALS['egw']->accounts->read($_sender);
				return true;
			}
			if(is_string($_sender) && strpos($_sender, '@'))
			{
				$this->sender = (object)array(
					'account_email'    => $this->get_addresspart($_sender, 'email'),
					'account_fullname' => $this->get_addresspart($_sender, 'fullname'),
				);
				return true;
			}
		}
		return false;
	}

	/**
	 * Set reply_to for the current notification
	 *
	 * @param $_reply_to string for email address to reply to
	 */
	public function set_reply_to($_reply_to) {
		$this->reply_to = $_reply_to;
		return true;
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
	 * sets notification subject for popup
	 *
	 * @param string $_subject
	 */
	public function set_popupsubject($_subject) {
		$this->popupsubject = $_subject;
		return true;
	}

	/**
	 * sets notification message
	 * @abstract $_message accepts plaintext or html
	 * NOTE: There is no XSS prevention in notifications framework!
	 * You have to filter userinputs yourselve (e.g. htmlspechialchars() )
	 * If you want to set plain AND html messages, just call this function
	 * two times, it autodetects the type of your input, or pass $type to be
	 * explicit.
	 *
	 * @param string $_message
	 * @param string $_type Type of message, 'plain' or 'html'
	 */
	public function set_message($_message, $_type = false) {
		if(!$_type)
		{
			$_type = strlen($_message) == strlen(strip_tags($_message)) ? 'plain' : 'html';
		}
		if($_type == 'plain') {
			$this->message_plain = $_message;
		} else {
			$this->message_html = $_message;
		}
		return true;
	}

	/**
	 * sets specific popup notification message
	 * @abstract $_message accepts plaintext or html
	 * NOTE: There is no XSS prevention in notifications framework!
	 * You have to filter userinputs yourselve (e.g. htmlspechialchars() )
	 *
	 * @param string $_message
	 */
	public function set_popupmessage($_message) {
		//popup requires html
		if(strlen($_message) == strlen(strip_tags($_message))) $_message = self::plain2html($_message);
		$this->message_popup = $_message;
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
				$this->add_link($link['text'], $link['view'], $link['popup'], $link['app'],$link['id']);
			}
		}
		return true;
	}

	/**
	 * sets the notification links for popups
	 *
	 * @param array $_links link array (like defined in $this->add_link)
	 */
	public function set_popuplinks(array $_links) {
		$this->popup_links = array(); // clear array if set
		foreach($_links as $link) {
			if(is_array($link)) {
				$this->add_popuplink($link['text'], $link['view'], $link['popup'], $link['app']);
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
	 * @param string $_app Application name, to use link registry (popup & view ignored)
	 * @param string $_id Application ID, to use link registry (popup & view ignored)
	 */
	public function add_link($_text, $_view, $_popup = false, $_app = false, $_id = false) {
		if(!$_view || !$_text) { return false; }
		$this->links[] = (object)array(
			'text'	=> $_text,
			'view'	=> $_view,
			'popup'	=> $_popup,
			'app'	=> $_app,
			'id'	=> $_id
		);
		return true;
	}

	/**
	 * adds a notification link for popups
	 *
	 * @param string $_text a descriptive text for the link
	 * @param array $_view all params needed to view the link (name => value pairs)
	 * @param string $_popup if link can be viewed in a popup something like '300x200' otherwise false
	 * @param string $_app application name
	 */
	public function add_popuplink($_text, $_view, $_popup = false, $_app = '') {
		if(!$_view || !$_text) { return false; }
		$this->popup_links[] = (object)array(
										'text'	=> $_text,
										'view'	=> $_view,
										'popup'	=> $_popup,
										'app'	=> $_app
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
				$this->add_attachment(
					$attachment['string'],
					$attachment['filename'],
					$attachment['encoding'],
					$attachment['type'],
					$attachment['path']
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
	 * @param string $_string Attachment data or null to use $_path
	 * @param string $_filename Name of the attachment.
	 * @param string $_encoding File encoding (see $Encoding).
	 * @param string $_type File extension (MIME) type.
	 * @param string $_path optional path to attachment, if !$_string
	 */
	public function add_attachment($_string, $_filename, $_encoding = "base64", $_type = "application/octet-stream", $_path=null) {
		if(!$_string && (!$_path || !file_exists($_path)) || !$_filename) return false;
		$this->attachments[] = (object)array(
			'string' => $_string,
			'filename' => $_filename,
			'encoding' => $_encoding ? $_encoding : "base64",
			'type' => $_type ? $_type : "application/octet-stream",
			'path' => $_path,
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
		if(!$messages = $this->create_messages($this->message_plain, $this->message_html, $this->message_popup)) {
			throw new Exception('Error: cannot send notifications. No valid messages supplied');
		}

		Api\Translation::add_app('notifications');

		$available_chains = $this->get_available_chains('routing');
		$push = new Api\Json\Push();

		foreach ($this->receivers as $receiver) {
			$user_notified = false;
			$prepend_message = '';
			$backend_errors = array();
			try {
				// system or non-system user
				if(!empty($receiver->account_id) && is_numeric($receiver->account_id)) {
					// system user, collect data and check for Status and expire state, skip notification if expired or not active
					$userData = $GLOBALS['egw']->accounts->read($receiver->account_id);
					//error_log(__METHOD__.__LINE__." fetched data for User:".array2string($userData['account_lid']).'#'.$userData['account_type'].'#'.$userData['account_status'].'#'.$GLOBALS['egw']->accounts->is_expired($userData).'#');
					if ($userData && $userData['account_type'] === 'u' &&
						($userData['account_status'] != 'A' || $GLOBALS['egw']->accounts->is_expired($userData)))
					{
						//error_log(__METHOD__.__LINE__." skipped notification for User with Data:".array2string($userData));
						$notification_chain = 'disable';
						continue;
					}
					$receiver->handle = $receiver->account_lid;
					// check if the receiver has rights to run the notifcation app
					$ids = $GLOBALS['egw']->accounts->memberships($receiver->account_id,true);
					$ids[] = $receiver->account_id;
					if ($GLOBALS['egw']->acl->get_specific_rights_for_account($ids,'run','notifications')) {
						// read the users notification chain
						$prefs = new Api\Preferences($receiver->account_id);
						$preferences_all = $prefs->read();
						$preferences = (object)$preferences_all[self::_appname];
						if($preferences->notification_chain) {
							// fallback: admin disabled user-chosen chain
							if(!$notification_chain = $available_chains[$preferences->notification_chain]) {
								$prepend_message .= lang(	'This eGroupWare notification has been sent to you by mail because your'
															.' chosen notification-chain has been disabled by the administrator.'
															.' Please choose another notification-chain in your preferences!');
								$notification_chain = $available_chains[self::_fallback];
							}
						} else {
							$notification_chain = $available_chains[self::user_fallback]; // fallback: no prefs
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
					$notification_backend = null;
					try {
						// check if backend should be skipped
						if( in_array($backend, $this->skip_backends) ) {
							// log as error just for the case too much skipping prevents user from being notified
							$backend_errors[] = $backend.' will be skipped (as defined by calling application)';
							continue;
						}

						$notification_backend = self::_appname.'_'.$backend;
						if(!file_exists(EGW_INCLUDE_ROOT.'/'. self::_appname.'/inc/class.'. $notification_backend. '.inc.php')) {
							throw new Exception('file for '.$notification_backend. ' does not exist');
						}
						$obj = new $notification_backend( $this->sender, $receiver, $this->config, $preferences??null );
						if ( !($obj instanceof notifications_iface) ) {
							unset ( $obj );
					 		throw new Exception($notification_backend. ' is no implementation of notifications_iface');
						}
						$lsubject = $this->subject;
						$llinks = $this->links;
						if ($backend == 'popup')
						{
							if (!empty($this->popupsubject)) $lsubject = $this->popupsubject;
							if ($this->popup_links) $llinks = $this->popup_links;
							if (is_array($this->popup_data)) $popup_data = $this->popup_data;
						} elseif ($backend == 'email') {
							if (!empty($this->reply_to)) $popup_data = array( 'reply_to' => $this->reply_to );
						}
						$obj->send($this->prepend_message($messages, $prepend_message), $lsubject, $llinks, $this->attachments, $popup_data??null);

						// This is to make popup_or_email option sensfull since
						// we save popup notifications in database anyway, email
						// notifications should be based on user availability.
						if ($backend === 'popup' && $action === 'fail' && !$push->isOnline($receiver->account_id))
						{
							throw new Exception();
						}
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
				if(!$user_notified) {
					/*error_log('Error: notification of receiver '.$receiver->handle.' failed for the following reasons:');
					foreach($backend_errors as $id=>$backend_error) {
						error_log($backend_error);
					}*/
					$error = implode(', ', $backend_errors);
					if (stripos($error, (string)$receiver->handle) !== false) $error = $receiver->handle.': '.$error;
					self::$errors[] = $error;
				}
			}
			catch (Exception $exception_user) {
				error_log('Error: notification of receiver '.$receiver->handle.' failed: '.$exception_user->getMessage());
				self::$errors[] = $receiver->handle.': '.$exception_user->getMessage();
			}
		}
		return true;
	}

	/**
	 * creates an array with the message as plaintext and html, and optional a html popup message
	 *
	 * @param string $_message_plain
	 * @param string $_message_html
	 * @param string $_message_popup
	 * @return plain and html message in one array, $messages['plain'] and $messages['html'] and, if exists $messages['popup']
	 */
	private function create_messages($_message_plain = '', $_message_html = '', $_message_popup = '') {
		if(empty($_message_plain) && empty($_message_html) && empty($_message_popup)) { return false; } // no message set
		$messages = array();

		// create the messages
		if(!empty($_message_plain)) {
			$messages['plain'] = $_message_plain;
		} else {
			$messages['plain'] = Api\Mail\Html::convertHTMLToText($_message_html, false, true);
		}

		if(!empty($_message_html)) {
			$messages['html'] = $_message_html;
		} else {
			$messages['html'] = self::plain2html($_message_plain);
		}
		if (!empty($_message_popup)) $messages['popup']=$_message_popup;
		return $messages;
	}

	/**
	 * Create html from plaintext message
	 *
	 * @param string $_plain
	 * @return string html message
	 */
	public static function plain2html($_plain)
	{
		return Api\Html::activate_links(nl2br(Api\Html::htmlspecialchars($_plain, true)));
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
	private function get_addresspart($_address, $_part='email')
	{
		$parts = null;
	 	if(strpos($_address,'<') && preg_match('/^(.*)\S?\<(.*)\>/',$_address, $parts)) { // _address contains a fullname part
	 		$fullname = trim(trim($parts[1]),'\"');
	 		$email = $parts[2];
	 	} else {
	 		$fullname = false;
	 		$email = $_address;
	 	}
	 	switch($_part) {
	 		case 'fullname':
	 			return $fullname;
	 		case 'email':
	 		default:
	 			return $email;
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
		// determine enabled backends from Api\Config
		$enabled_backends = array();
		foreach($this->backends as $backend) {
			switch($backend) {
				case 'email':
				case 'popup':
				case 'jpopup':
					$enabled_backends[$backend] = true; // fallback must always be enabled
					break;
				default:
					$param = $backend.'_enable';
					$enabled_backends[$backend] = !empty($this->config->{$param});
					break;
			}
		}

		$enabled_chains = array();
		$disabled_chains = array();
		foreach($this->notification_chains as $key => $chain) {
			$allow_chain = true;
			if(is_array($chain)) {
				foreach(array_keys($chain) as $name) {
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
					foreach(array_keys(${$arr_name}) as $key) {
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

			case 'routing':
			default:
				return array_merge($common_chains, $enabled_chains);
		}

		return false;
	}

	/**
	 * Actions to take when an account is deleted
	 *
	 * @param settings array with keys account_id and new_owner (new_owner is optional)
	 */
	public function deleteaccount($settings) {
		foreach($this->backends as $backend) {
			$backend_hook = array(self::_appname.'_'.$backend,'deleteaccount');
			if (is_callable($backend_hook)) {
				call_user_func($backend_hook,$settings);
			}
		}
	}


	/**
	 * Set popup data
	 *
	 * @param string $_appname
	 * @param array $_data
	 * @return boolean
	 */
	public function set_popupdata($_appname, $_data)
	{
		$this->popup_data = array(
			'appname' => $_appname,
			'data'    => $_data
		);

		return true;
	}

	/**
	 * Hook for site configuration
	 * Gets the appropriate mail accounts to offer to use for notifications
	 *
	 * @param $data
	 * @return void
	 */
	public function config($data)
	{
		$result = ['sel_options' => ['async_identity' => []]];
		$identities = iterator_to_array(EGroupware\Api\Mail\Account::search(false, 'params', 'ABS(egw_ea_valid.account_id) ASC, '));

		// We only want identities for all users, and prefer SMTP only
		$smtp_only = [];
		$others = [];
		foreach($identities as $id => $identity)
		{
			// Identities should be sorted so all users are first.  Stop when we get to the others.
			if(!in_array('0', $identity['account_id']))
			{
				break;
			}
			$destination = $identity['acc_imap_host'] ? 'others' : 'smtp_only';
			$$destination[$id] = $identity['acc_name'];
		}

		// Put SMTP only identities first
		$result['sel_options']['async_identity'] = $smtp_only + $others;

		return $result;
	}
}