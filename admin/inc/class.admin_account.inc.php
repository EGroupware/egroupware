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
	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'delete' => true,
	);

	/**
	 * Hook to edit account data via "Account" tab in addressbook edit dialog
	 *
	 * @param array $content
	 * @return array
	 * @throws egw_exception_not_found
	 */
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
				// invalidate account, before reading it, to code with changed to DB or LDAP outside EGw
				accounts::cache_invalidate((int)$content['account_id']);
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
					'homedirectory' => $GLOBALS['egw_info']['server']['ldap_account_home'],
					'loginshell' => $GLOBALS['egw_info']['server']['ldap_account_shell'],
				);
			}
			$account['ldap_extra_attributes'] = $GLOBALS['egw_info']['server']['ldap_extra_attributes'];
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
				// save old values to only trigger save, if one of the following values change (contact data get saved anyway)
				'preserve' => array('old_account' => array_intersect_key($account, array_flip(array(
					'account_lid', 'account_status', 'memberships', 'anonymous', 'changepassword',
					'mustchangepassword', 'account_primary_group', 'homedirectory', 'loginshell')))),
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
		if ($content['old_account'] && $content['old_account'] == array_diff_key($content, $content['old_account']))
		{
			return '';	// no need to save account data, if nothing changed
		}
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

	/**
	 * Delete an account
	 *
	 * @param array $content=null
	 */
	public static function delete(array $content=null)
	{
		if (!is_array($content))
		{
			if (isset($_GET['contact_id']) && ($account_id = $GLOBALS['egw']->accounts->name2id((int)$_GET['contact_id'], 'person_id')))
			{
				$content = array(
					'account_id' => $account_id,
					'contact_id' => (int)$_GET['contact_id'],
				);
			}
			else
			{
				$content = array('account_id' => (int)$_GET['account_id']);
			}
			//error_log(__METHOD__."() \$_GET[account_id]=$_GET[account_id], \$_GET[contact_id]=$_GET[contact_id] content=".array2string($content));
		}
		if ($GLOBALS['egw']->acl->check('account_access',32,'admin') || !($content['account_id'] > 0) ||
			$GLOBALS['egw_info']['user']['account_id'] == $content['account_id'])
		{
			egw_framework::window_close(lang('Permission denied!!!'));
		}
		if ($content['delete'])
		{
			$cmd = new admin_cmd_delete_account(accounts::id2name($content['account_id']), $content['new_owner'], true);
			$msg = $cmd->run();
			if ($content['contact_id'])
			{
				egw_framework::refresh_opener($msg, 'addressbook', $content['contact_id'], 'delete');
			}
			else
			{
				egw_framework::refresh_opener($msg, 'admin', $content['account_id'], 'delete');
			}
			egw_framework::window_close();
		}
		$tpl = new etemplate_new('admin.account.delete');
		$tpl->exec('admin_account::delete', $content, array(), array(), $content, 2);
	}

	/**
	 * Delete a group via ajax
	 *
	 * @param int $account_id
	 */
	public static function ajax_delete_group($account_id)
	{
		$cmd = new admin_cmd_delete_account(accounts::id2name(accounts::id2name($account_id)), null, false);
		$msg = $cmd->run();

		egw_json_response::get()->call('egw.refresh', $msg, 'admin', $account_id, 'delete');
	}

	/**
	 * Check entered data and return error-msg via json data or null
	 *
	 * @param array $data values for account_id and account_lid
	 * @param string $changed name of addressbook widget triggering change eg. "email", "n_given" or "n_family"
	 */
	public static function ajax_check(array $data, $changed)
	{
		// generate default email address
		if (empty($data['account_email']) || !$data['account_id'] && in_array($changed, array('n_given', 'n_family')))
		{
			$email = common::email_address($data['account_firstname'], $data['account_lastname'], $data['account_lid']);
			if ($email && $email[0] != '@' && strpos($email, '@'))	// only add valid email addresses
			{
				egw_json_response::get()->assign('addressbook-edit_email', 'value', $email);
			}
		}

		if (!$data['account_lid'] && !$data['account_id']) return;	// makes no sense to check before

		// set dummy membership to get no error about no members yet
		$data['account_memberships'] = array($data['account_primary_user'] = $GLOBALS['egw_info']['user']['account_primary_group']);

		try {
			$cmd = new admin_cmd_edit_user($data['account_id'], $data);
			$cmd->run(null, false, false, true);
		}
		catch(Exception $e)
		{
			egw_json_response::get()->data($e->getMessage());
		}
	}
}
