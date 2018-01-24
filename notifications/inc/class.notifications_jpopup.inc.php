<?php
/**
 * EGroupware - Notifications Java Desktop App
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage jdesk
 * @link http://www.egroupware.org
 * @author Stefan Werfling <stefan.werfling@hw-softwareentwicklung.de>, Maik H�ttner <maik.huettner@hw-softwareentwicklung.de>
 */

use EGroupware\Api;

class notifications_jpopup implements notifications_iface
{

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
	const _type = 'jpopup';

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
	public function __construct($_sender, $_recipient, $_config = null, $_preferences = null)
	{
		if( !is_object($_sender) ) { throw new Exception("no sender given."); }
		if( !is_object($_recipient) ) { throw new Exception("no recipient given."); }

		$this->sender		= $_sender;
		$this->recipient	= $_recipient;
		$this->config		= $_config;
		$this->preferences	= $_preferences;
		$this->db			= $GLOBALS['egw']->db;
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
	public function send(array $_messages, $_subject=false, $_links=false, $_attachments=false, $_data = false)
	{
		unset($_attachments, $_data);	// not used

		$jmessage = array();

		// app-message
		if( ($_links != null) && (count($_links) > 0) )
		{
			$tlink		= $_links[0];
			$appname	= "";

			if( key_exists('menuaction', $tlink->view) )
			{
				$tmp = explode(".", $tlink->view['menuaction']);
				$appname = $tmp[0];
			}

			$link = array();

			foreach( $tlink->view as $pkey => $pvalue )
			{
				$link[] = $pkey . '=' . $pvalue;
			}

			// TODO more links?
			$jmessage['link'] = implode("&", $link);
		}

		$message = $this->render_infos($_subject)
			.Api\Html::hr()
			.$_messages['html'];

		$jmessage['msghtml']	= $message;
		$jmessage['app']		= $appname;


		$this->save( serialize($jmessage) );
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
		$infos[] = lang('Message from').': '.$sender;
		if(!empty($_subject)) { $infos[] = Api\Html::bold($_subject); }
		return implode($newline,$infos);
	}

	/**
	* saves notification into database so that the client can fetch it from there
	*
	* @param string $_message
	* @param array $_user_sessions
	*/
	private function save( $_message ) {
		$result = $this->db->insert( self::_notification_table, array(
			'account_id'     => $this->recipient->account_id,
			'notify_message' => $_message,
			'notify_type'	 => self::_type
			), false,__LINE__,__FILE__,self::_appname);
		if ($result === false) throw new Exception("Can't save notification into SQL table");
	}
}
