<?php
/**
 * EGroupware - eMailAdmin hooks
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Klaus Leithoff <leithoff-AT-stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2008-14 by leithoff-At-stylite.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * diverse static emailadmin hooks
 */
class emailadmin_hooks
{
    /**
     * Hook called to add action to user
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     */
	static function edit_user($data)
	{
		unset($data);	// not used

		$actions = array();

		if ($GLOBALS['egw_info']['user']['apps']['emailadmin'])
		{
			$actions[] = array(
				'id'      => 'mail_account',
				'caption' => 'mail account',
				'url'     => 'menuaction=emailadmin.emailadmin_wizard.edit&account_id=$id',
				'popup'   => '720x530',
				'icon'    => 'emailadmin/navbar',
			);
		}
		return $actions;
	}

	/**
	 * Password changed hook --> unset cached objects, as password might be used for email connection
	 *
	 * @param array $hook_data
	 */
	public static function changepassword($hook_data)
	{
		if (!empty($hook_data['old_passwd']))
		{
			emailadmin_credentials::changepassword($hook_data);
		}
	}

    /**
     * Hook called before an account get deleted
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     * @param string $data['account_lid'] account-name
     * @param int $data['new_owner'] account-id of new owner, or false if data should get deleted
     */
	static function deleteaccount(array $data)
	{
		self::run_plugin_hooks('deleteAccount', $data);

		// as mail accounts contain credentials, we do NOT assign them to user users
		emailadmin_account::delete(0, $data['account_id']);
	}

    /**
     * Hook called before a group get deleted
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     * @param string $data['account_name'] account-name
     */
	static function deletegroup(array $data)
	{
		emailadmin_account::delete(0, $data['account_id']);
	}

	/**
     * Hook called when an account get added or edited
     *
     * @param array $data
     * @param int $data['account_id'] numerical id
     * @param string $data['account_lid'] account-name
     * @param string $data['account_email'] email
	 */
	static function addaccount(array $data)
	{
		$method = $data['location'] == 'addaccount' ? 'addAccount' : 'updateAccount';
		self::run_plugin_hooks($method, $data);
	}

	/**
	 * Run hook on plugins of all mail-accounts of given account_id
	 *
	 * @param string $method plugin method to run
	 * @param array $data hook-data incl. value for key account_id
	 */
	protected static function run_plugin_hooks($method, array $data)
	{
		foreach(emailadmin_account::search((int)$data['account_id'], 'params') as $params)
		{
			if (!emailadmin_account::is_multiple($params)) continue;	// no need to waste time on personal accounts

			try {
				$account = new emailadmin_account($params);
				if ($account->acc_imap_type != 'emailadmin_imap' && ($imap = $account->imapServer(true)) &&
					is_a($imap, 'emailadmin_imap') && get_class($imap) != 'emailadmin_imap')
				{
					$imap->$method($data);
				}
				if ($account->acc_smtp_type != 'emailadmin_smtp' && ($smtp = $account->smtpServer(true)) &&
					is_a($smtp, 'emailadmin_smtp') && get_class($smtp) != 'emailadmin_smtp')
				{
					$smtp->$method($data);
				}
			}
			catch(Exception $e) {
				_egw_log_exception($e);
				// ignore exception, without stalling other hooks
			}
		}
	}

	/**
	 * Detect imap and smtp server plugins from EMailAdmin's inc directory
	 *
	 * @param string|array $data location string or array with key 'location' and other params
	 * @return array
	 */
	public static function server_types($data)
	{
		$location = is_array($data) ? $data['location'] : $data;
		$extended = is_array($data) ? $data['extended'] : false;

		$types = array();
		foreach(scandir($dir=EGW_INCLUDE_ROOT.'/emailadmin/inc') as $file)
		{
			$matches = null;
			if (!preg_match('/^class\.([^.]*(smtp|imap|postfix|dovecot|dbmail)[^.*]*)\.inc\.php$/', $file, $matches)) continue;
			$class_name = $matches[1];
			include_once($dir.'/'.$file);
			if (!class_exists($class_name)) continue;

			$is_imap = $class_name == 'emailadmin_imap' || is_subclass_of($class_name, 'emailadmin_imap');
			$is_smtp = $class_name == 'emailadmin_smtp' || is_subclass_of($class_name, 'emailadmin_smtp') && $class_name != 'defaultsmtp';

			if ($is_smtp && $location == 'smtp_server_types' || $is_imap && $location == 'imap_server_types')
			{
				// only register new imap-class-names
				if ($is_imap && $class_name == emailadmin_account::getIcClass ($class_name, true)) continue;

				$type = array(
					'classname' => $class_name,
					'description' => is_callable($function=$class_name.'::description') ? call_user_func($function) : $class_name,
				);

				if ($is_imap) $type['protocol'] = 'imap';

				$types[$class_name] = $type;
			}
		}
		if (!$extended)
		{
			foreach($types as $class_name => &$type)
			{
				$type = $type['description'];
			}
		}
		//error_log(__METHOD__."(".array2string($data).") returning ".array2string($types));
		return $types;
	}
}
