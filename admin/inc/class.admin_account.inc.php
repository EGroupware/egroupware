<?php
/**
 * EGroupware: Admin app UI: edit/add account
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2014 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for admin: edit/add account
 */
class admin_account
{
	public function addressbook_edit(array $content)
	{
		if ((string)$content['owner'] === '0')
		{
			// load our translations
			translation::add_app('admin');

			if ($content['id'])	// existing account
			{
				if (!($account = $GLOBALS['egw']->accounts->read($content['account_id'])))
				{
					throw new egw_exception_not_found('Account data NOT found!');
				}
				if ($account['account_expires'] == -1) $account['account_expires'] = '';
				unset($account['account_pwd']);	// do NOT send to client
				$account['memberships'] = array_keys($account['memberships']);
				$acl = new acl($content['account_id']);
				$acl->read_repository();
				$account['anonymous'] = $acl->check('anonymous', 1, 'phpgwapi');
				$account['changepassword'] = !$acl->check('nopasswordchange', 1, 'preferences');
				$auth = new auth();
				if (($account['account_lastpwd_change'] = $auth->getLastPwdChange($account['account_lid'])) === false)
				{
					$account['account_lastpwd_change'] = null;
				}
				$account['mustchangepassword'] = isset($account['account_lastpwd_change']) &&
					(string)$account['account_lastpwd_change'] === '0';
			}
			else	// new account
			{
				$account = array(
					'account_status' => 'A',
					'memberships' => array(),
					'anonymous' => false,
					'changepassword' => (bool)$GLOBALS['egw_info']['server']['change_pwd_every_x_days'],
					'mustchangepassword' => false,
				);
			}
			return array(
				'name' => 'admin.account?'.filemtime(EGW_SERVER_ROOT.'/admin/templates/default/account.xet'),
				'prepend' => true,
				'label' => 'Account',
				'data' => $account,
				'callback' => 'admin_account::addressbook_save',
			);
		}
	}
}
