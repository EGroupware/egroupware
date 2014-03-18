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
		if ((string)$content['owner'] === '0' && $GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$deny_edit = $content['account_id'] ? $GLOBALS['egw']->acl->check('account_access', 16, 'admin') :
				$GLOBALS['egw']->acl->check('account_access', 4, 'admin');
			//error_log(__METHOD__."() contact_id=$content[contact_id], account_id=$content[account_id], deny_edit=".array2string($deny_edit));

			if (!$content['account_id'] && $deny_edit) return;	// no right to add new accounts, should not happen by AB ACL

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
					'changepassword' => true,	//old default: (bool)$GLOBALS['egw_info']['server']['change_pwd_every_x_days'],
					'mustchangepassword' => false,
					'account_primary_group' => $GLOBALS['egw']->accounts->name2id('Default'),
				);
			}
			$readonlys = array();

			if ($deny_edit)
			{
				foreach(array_keys($account) as $key)
				{
					$readonlys[$key] = true;
				}
				$readonlys['account_passwd'] = $readonlys['account_passwd2'] = true;
			}
			return array(
				'name' => 'admin.account?'.filemtime(EGW_SERVER_ROOT.'/admin/templates/default/account.xet'),
				'prepend' => true,
				'label' => 'Account',
				'data' => $account,
				'readonlys' => $readonlys,
				'pre_save_callback' => $deny_edit ? null : 'admin_account::addressbook_pre_save',
			);
		}
	}

	/**
	 * Hook called by addressbook prior to saving addressbook data
	 *
	 * @param array &$content
	 * @throws Exception for errors
	 * @return string Success message
	 */
	public static function addressbook_pre_save(&$content)
	{
		//error_log(__METHOD__."(".array2string($content).")");
		$account = array();
		foreach(array(
			// need to copy/rename some fields named different in account and contact
			'n_given' => 'account_firstname',
			'n_family' => 'account_lastname',
			'email' => 'account_email',
			'memberships' => 'account_groups',
			// copy following fields to account
			'account_lid', 'account_id',
			'changepassword', 'anonymous', 'mustchangepassword',
			'account_passwd', 'account_passwd2',
			'account_primary_group',
			'account_expires', 'account_status',
		) as $c_name => $a_name)
		{
			if (is_int($c_name)) $c_name = $a_name;

			switch($a_name)
			{
				case 'account_expires':
					$account[$a_name] = $content[$c_name] ? $content[$c_name] : 'never';
					break;

				case 'changepassword':	// boolean values: admin_cmd_edit_user understands '' as NOT set
				case 'anonymous':
				case 'mustchangepassword':
					$account[$a_name] = (boolean)$content[$c_name];
					break;

				default:
					$account[$a_name] = $content[$c_name];
					break;
			}
		}

		$cmd = new admin_cmd_edit_user((int)$content['account_id'], $account);
		$cmd->run();

		egw_json_response::get()->call('egw.refresh', '', 'admin', $cmd->account, $content['account_id'] ? 'edit' : 'add');

		// for a new account a new contact was created, need to merge that data with $content
		if (!$content['account_id'])
		{
			$content['account_id'] = $cmd->account;
			$addressbook_bo = new addressbook_bo();
			if (!($content['id'] = accounts::id2name($cmd->account, 'person_id')) ||
				!($contact = $addressbook_bo->read($content['id'])))
			{
				throw new egw_exception_assertion_failed("Can't find contact of just created account!");
			}
			$content = array_merge($contact, $content);
		}
	}
}
