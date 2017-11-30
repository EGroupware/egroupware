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

/**
 * jdesk Json methods for notifications
 */
class notifications_jdesk_ajax {

	public $public_functions = array(
		'get_notification'	=> true
		);

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
	 * holds preferences array of user to notify
	 *
	 * @var array
	 */
	private $preferences;

	/**
	 * reference to global db object
	 *
	 * @var Api\Db
	 */
	private $db;

	/**
	 * the xml response object
	 *
	 * @var Api\Json\Response
	 */
	private $response;

	/**
	 * constructor
	 *
	 */
	public function __construct()
	{
		$this->response = Api\Json\Response::get();

		$this->recipient = (object)$GLOBALS['egw']->accounts->read($GLOBALS['egw_info']['user']['account_id']);

		$this->config = (object)Api\Config::read(self::_appname);

		$prefs = new Api\Preferences($this->recipient->account_id);
		$this->preferences = $prefs->read();

		$this->db = $GLOBALS['egw']->db;
	}

	/**
	 * destructor
	 *
	 */
	public function __destruct() {}

	/**
	 * public AJAX trigger function to be called by the JavaScript client
	 *
	 * this function calls all other recurring AJAX notifications methods
	 * to have ONE single recurring AJAX call per user
	 *
	 * @return xajax response
	 */
	public function get_notifications($browserNotify = false)
	{
		//if ($GLOBALS['egw_info']['user']['apps']['felamimail'])  $this->check_mailbox();

		// update currentusers
		/*if( $GLOBALS['egw_info']['user']['apps']['admin'] &&
			$GLOBALS['egw_info']['user']['preferences']['common']['show_currentusers'] )
		{
			$this->response->jquery('#currentusers', 'text',
				array((string)$GLOBALS['egw']->session->session_count()));
		}*/

		$this->get_egwpopup($browserNotify);

		/**
		 * $this->addGeneric('alert', array(
				"message" => $message,
				"details" => $details));
		 */
	}

	/**
	 * Let the user confirm that they have seen the message.
	 * After they've seen it, remove it from the database
	 *
	 * @param int|array $notify_id one or more notify_id's
	 */
	public function confirm_message($notify_id)
	{
		if ($notify_id)
		{
			$this->db->delete(self::_notification_table,array(
				'notify_id' => $notify_id,
				'account_id' => $this->recipient->account_id,
				'notify_type' => self::_type
			),__LINE__,__FILE__,self::_appname);
		}
	}

	/**
	 * gets all egwpopup notifications for calling user
	 *
	 * @return boolean true or false
	 */
	private function get_egwpopup($browserNotify = false)
	{
		unset($browserNotify);	// not used

		$message = '';

		$rs = $this->db->select(self::_notification_table, '*', array(
				'account_id' => $this->recipient->account_id,
				'notify_type' => self::_type
			),
			__LINE__,__FILE__,false,'',self::_appname);

		if( $rs->NumRows() > 0 )
		{
			foreach ($rs as $notification)
			{
				$message = null;

				$jmessage = unserialize($notification['notify_message']);
				$jmessage['notify_id'] = $notification['notify_id'];

				$this->response->data($jmessage);
			}

			switch( $this->preferences[self::_appname]['egwpopup_verbosity'] )
			{
				case 'low':

					break;

				case 'high':

					break;

				case 'medium':
				default:

					break;
			}
		}

		return true;
	}
}
