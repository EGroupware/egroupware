<?php
/**
 * EGroupware - Notifications
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage backends
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>, Christian Binder <christian@jaytraxx.de>
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Instant user notification with egroupware popup.
 *
 * @abstract egwpopup is a two stage notification. In the first stage
 * notification is written into self::_notification_table.
 * In the second stage a request from the client reads
 * out the table to look if there is a notificaton for this
 * client. The second stage is done in class.notifications_ajax.inc.php
 */
class notifications_popup implements notifications_iface {

	/**
	 * Appname
	 */
	const _appname = 'notifications';

	/**
	 * Notification table in SQL database
	 */
	const _notification_table = 'egw_notificationpopup';

	/**
	 * Notification type
	 */
	const _type = 'base';

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
	 * holds db object of SQL database
	 *
	 * @var Api\Db
	 */
	private $db;

	/**
	 * constructor of notifications_egwpopup
	 *
	 * @param object $_sender
	 * @param object $_recipient
	 * @param object $_config
	 * @param object $_preferences
	 */
	public function __construct($_sender, $_recipient, $_config = null, $_preferences = null) {
		//error_log(__METHOD__."(".array2string($_sender).', '.array2string($_recipient).', '.array2string($config).',...)');
		if(!is_object($_sender)) { throw new Exception("no sender given."); }
		if(!is_object($_recipient)) { throw new Exception("no recipient given."); }
		$this->sender = $_sender;
		$this->recipient = $_recipient;
		$this->config = $_config;
		$this->preferences = $_preferences;
		$this->db = $GLOBALS['egw']->db;
	}

	/**
	 * sends notification if user is online
	 *
	 * @param array $_messages
	 * @param string $_subject
	 * @param array $_links
	 * @param array $_attachments
	 * @param array $_data
	 */
	public function send(array $_messages, $_subject = false, $_links = false, $_attachments = false, $_data = false)
	{
		unset($_attachments);	// not used

		$message = 	$this->render_infos($_subject)
					.Api\Html::hr()
					.(isset($_messages['popup'])&&!empty($_messages['popup'])?$_messages['popup']:$_messages['html'])
					.$this->render_links($_links);

		$this->save($message, $_data);
	}

	/**
	 * saves notification into database so that the client can fetch it from there
	 *
	 * @param string $_message
	 * @param array $_user_sessions
	 * @param array $_data
	 */
	private function save($_message, $_data) {
		$result = $this->db->insert( self::_notification_table, array(
			'account_id'     => $this->recipient->account_id,
			'notify_message' => $_message,
			'notify_type'    => self::_type,
			'notify_data'    => is_array($_data) ? json_encode($_data) : NULL,
			'notify_created' => Api\DateTime::user2server('now'),
		), false,__LINE__,__FILE__,self::_appname);
		if ($result === false) throw new Exception("Can't save notification into SQL table");
		$push = new Api\Json\Push($this->recipient->account_id);
		$entries = self::read($this->recipient->account_id);
		$push->call('app.notifications.append', $entries['rows'], null, $entries['total']);
	}


	/**
	 * read all notification messages for given recipient
	 * @param $_account_id
	 * @return array
	 */
	public static function read($_account_id)
	{
		if (!$_account_id) return [];

		$rs = $GLOBALS['egw']->db->select(self::_notification_table, '*', array(
				'account_id' => $_account_id,
				'notify_type' => self::_type
			),
			__LINE__,__FILE__,0 ,'ORDER BY notify_id DESC',self::_appname, 100);
		// Fetch the total
		$total =  $GLOBALS['egw']->db->select(self::_notification_table, 'COUNT(*)', array(
			'account_id' => $_account_id,
			'notify_type' => self::_type
		),
			__LINE__,__FILE__,0 ,'',self::_appname)->fetchColumn();
		$result = array();
		if ($rs->NumRows() > 0)	{
			foreach ($rs as $notification) {
				$actions = null;
				$data = json_decode($notification['notify_data'], true);
				if (!empty($data['appname']) && !empty($data['data']))
				{
					$_actions = Api\Hooks::process (array(
						'location' => 'notifications_actions',
						'data' => $data['data']
						), $data['appname'], true);
					$actions = $_actions[$data['appname']];
				}
				$result[] = array(
					'id'      => $notification['notify_id'],
					'message' => $notification['notify_message'],
					'status'  => $notification['notify_status'],
					'created' => Api\DateTime::server2user($notification['notify_created']),
					'current' => new Api\DateTime('now'),
					'actions' => is_array($actions)?$actions:NULL,
					'extra_data' => $data['data'] ?? [],
				);

			}
			return ['rows' => $result, 'total'=> $total];
		}
	}

	/**
	 * renders plaintext/html links from given link array
	 * should be moved to the ajax class later - like mentioned in the Todo
	 *
	 * @param array $_links
	 * @return string html rendered link(s) as complete string with jspopup or a new window
	 */
	private function render_links($_links = false) {
		if(!is_array($_links) || count($_links) == 0) { return false; }
		$newline = "<br />";

		$rendered_links = array();
		foreach($_links as $link) {
			if(!$link->popup) { $link->view['no_popup'] = 1; }

			// do not expose sensitive data
			$url = preg_replace('/(sessionid|kp3|domain)=[^&]+&?/','',
				Api\Html::link('/index.php', $link->view));
			// extract application-icon from menuaction
			if($link->view['menuaction']) {
				$menuaction_arr = explode('.',$link->view['menuaction']);
				$application = $menuaction_arr[0];
				$image = $application ? Api\Html::image($application,'navbar',$link->text,'align="middle" style="width: 24px; margin-right: 0.5em;"') : '';
			} else {
				$image = '';
			}
			if($link->popup && !$GLOBALS['egw_info']['user']['preferences']['notifications']['external_mailclient'])
			{
				$data = array(
					"data-app = '{$link->app}'",
					"data-id = '{$link->id}'",
					"data-url = '$url'",
					"data-popup = '{$link->popup}'"
				);

				$rendered_links[] = Api\Html::div($image.$link->text,implode(' ',$data),'link');
			} else {
				$rendered_links[] = Api\Html::div('<a href="'.$url.'" target="_blank">'.$image.$link->text.'</a>','','link');
			}

		}
		if(count($rendered_links) > 0) {
			return Api\Html::hr().Api\Html::bold(Api\Translation::translate_as($this->recipient->account_id,'Linked entries:')).$newline.implode($newline,$rendered_links);
		}
	}

	/**
	 * returns javascript to open a popup window: window.open(...)
	 *
	 * @param string $link link or this.href
	 * @param string $target ='_blank' name of target or this.target
	 * @param int $width =750 width of the window
	 * @param int $height =400 height of the window
	 * @return string javascript (using single quotes)
	 */
	private function jspopup($link,$target='_blank',$width=750,$height=410)
	{
		if($GLOBALS['egw_info']['user']['preferences']['notifications']['external_mailclient'])
		{
			return 'window.open('.($link == 'this.href' ? $link : "'".$link."'").','.
				($target == 'this.target' ? $target : "'".$target."'").",$width,$height,'yes')";
		}
		else
		{
			return 'egw_openWindowCentered2('.($link == 'this.href' ? $link : "'".$link."'").','.
				($target == 'this.target' ? $target : "'".$target."'").",$width,$height,'yes')";
		}
	}

	/**
	 * renders additional infos from sender and subject
	 *
	 * @param string $_subject
	 * @return string html rendered info as complete string
	 */
	private function render_infos($_subject = false) {
		$infos = array();
		$newline = "<br />";

		$sender = $this->sender->account_fullname ? $this->sender->account_fullname : $this->sender_account_email;
		$infos[] = Api\Translation::translate_as($this->recipient->account_id, 'Message from').': '.$sender;
		if(!empty($_subject)) { $infos[] = Api\Html::bold($_subject); }
		return implode($newline,$infos);
	}

	/**
	 * Actions to take when deleting an account
	 *
	 * @param settings array with keys account_id and new_owner (new_owner is optional)
	 */
	public static function deleteaccount($settings) {
		$GLOBALS['egw']->db->delete( self::_notification_table, array(
			'account_id'	=> $settings['account_id']
		),__LINE__,__FILE__,self::_appname);
	}
}
