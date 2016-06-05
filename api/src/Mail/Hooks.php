<?php
/**
 * EGroupware - Mail hooks
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage amil
 * @author Klaus Leithoff <leithoff-AT-stylite.de>
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2008-16 by leithoff-At-stylite.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;

/**
 * diverse static Mail hooks
 */
class Hooks
{
	/**
	 * Password changed hook --> unset cached objects, as password might be used for email connection
	 *
	 * @param array $hook_data
	 */
	public static function changepassword($hook_data)
	{
		if (!empty($hook_data['old_passwd']))
		{
			Credentials::changepassword($hook_data);
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
		Account::delete(0, $data['account_id']);
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
		Account::delete(0, $data['account_id']);
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
		foreach(Account::search((int)$data['account_id'], 'params') as $params)
		{
			if (!Account::is_multiple($params)) continue;	// no need to waste time on personal accounts

			try {
				$account = new Account($params);
				if ($account->acc_smtp_type != __NAMESPACE__.'\\Smtp' && ($smtp = $account->smtpServer(true)) &&
					is_a($smtp, __NAMESPACE__.'\\Smtp') && get_class($smtp) != __NAMESPACE__.'\\Smtp')
				{
					$smtp->$method($data);
				}
				if ($account->acc_imap_type != __NAMESPACE__.'\\Imap' && $account->acc_imap_admin_username &&
					$account->acc_imap_admin_password && ($imap = $account->imapServer(true)) &&
					is_a($imap, __NAMESPACE__.'\\Imap') && get_class($imap) != __NAMESPACE__.'\\Imap')
				{
					$imap->$method($data);
				}
			}
			catch(\Exception $e) {
				_egw_log_exception($e);
				// ignore exception, without stalling other hooks
			}
		}
	}
}
