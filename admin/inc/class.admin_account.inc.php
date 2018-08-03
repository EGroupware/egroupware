<?php
/**
 * EGroupware: Admin app UI: edit/add account
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2014-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

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
	 * @throws Api\Exception\NotFound
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
			Api\Translation::add_app('admin');

			if ($content['id'])	// existing account
			{
				// invalidate account, before reading it, to code with changed to DB or LDAP outside EGw
				Api\Accounts::cache_invalidate((int)$content['account_id']);
				if (!($account = $GLOBALS['egw']->accounts->read($content['account_id'])))
				{
					throw new Api\Exception\NotFound('Account data NOT found!');
				}
				if ($account['account_expires'] == -1) $account['account_expires'] = '';
				unset($account['account_pwd']);	// do NOT send to client
				$account['account_groups'] = array_keys($account['memberships']);
				$acl = new Acl($content['account_id']);
				$acl->read_repository();
				$account['anonymous'] = $acl->check('anonymous', 1, 'phpgwapi');
				$account['changepassword'] = !$acl->check('nopasswordchange', 1, 'preferences');
				$auth = new Api\Auth();
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
					'account_groups' => array(),
					'anonymous' => false,
					'changepassword' => true,	//old default: (bool)$GLOBALS['egw_info']['server']['change_pwd_every_x_days'],
					'mustchangepassword' => false,
					'account_primary_group' => $GLOBALS['egw']->accounts->name2id('Default'),
					'homedirectory' => $GLOBALS['egw_info']['server']['ldap_account_home'],
					'loginshell' => $GLOBALS['egw_info']['server']['ldap_account_shell'],
				);
			}
			// should we show extra ldap attributes home-directory and login-shell
			$account['ldap_extra_attributes'] = $GLOBALS['egw_info']['server']['ldap_extra_attributes'] &&
				get_class($GLOBALS['egw']->accounts->backend) === 'EGroupware\\Api\\Accounts\\Ldap';

			$readonlys = array();

			// at least ADS does not allow to unset it and SQL backend does not implement it either
			if ($account['mustchangepassword'])
			{
				$readonlys['mustchangepassword'] = true;
			}

			if ($deny_edit)
			{
				foreach(array_keys($account) as $key)
				{
					$readonlys[$key] = true;
				}
				$readonlys['account_passwd'] = $readonlys['account_passwd2'] = true;
			}
			return array(
				'name' => 'admin.account',
				'prepend' => true,
				'label' => 'Account',
				'data' => $account,
				// save old values to only trigger save, if one of the following values change (contact data get saved anyway)
				'preserve' => empty($content['id']) ? array() :
					array('old_account' => array_intersect_key($account, array_flip(array(
						'account_lid', 'account_status', 'account_groups', 'anonymous', 'changepassword',
						'mustchangepassword', 'account_primary_group', 'homedirectory', 'loginshell',
						'account_expires', 'account_firstname', 'account_lastname', 'account_email'))),
						'deny_edit' => $deny_edit),
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
		if (!isset($content['mustchangepassword']))
		{
			$content['mustchangepassword'] = true;	// was readonly because already set
		}
		$content['account_firstname'] = $content['n_given'];
		$content['account_lastname'] = $content['n_family'];
		$content['account_email'] = $content['email'];
		if (!empty($content['old_account']))
		{
			$old = array_diff_assoc($content['old_account'], $content);
			// array_diff_assoc compares everything as string (cast to string)
			if ($content['old_account']['account_groups'] != $content['account_groups'])
			{
				$old['account_groups'] = $content['old_account']['account_groups'];
			}
		}
		if ($content['deny_edit'] || empty($old))
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
			'account_groups',
			// copy following fields to account
			'account_lid',
			'changepassword', 'anonymous', 'mustchangepassword',
			'account_passwd', 'account_passwd_2',
			'account_primary_group',
			'account_expires',
			'homedirectory', 'loginshell',
			'requested', 'requested_email', 'comment',	// admin_cmd documentation (EPL)
		) as $c_name => $a_name)
		{
			if (is_int($c_name)) $c_name = $a_name;

			// only record real changes
			if (isset($content['old_account']) &&
				(!isset($content[$c_name]) && $c_name !== 'account_expires' || // account_expires is not set when empty!
				$content['old_account'][$a_name] == $content[$c_name]))
			{
				continue;	// no change --> no need to log setting it to identical value
			}

			switch($a_name)
			{
				case 'account_expires':
					$account[$a_name] = $content[$c_name] ? $content[$c_name] :
						($content['account_status'] ? 'never' : 'already');
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
		// Make sure primary group is in account groups
		if ($account['account_primary_group'] && !in_array($account['account_primary_group'], (array)$account['account_groups']))
		{
			$account['account_groups'][] = $account['account_primary_group'];
		}

		$cmd = new admin_cmd_edit_user((int)$content['account_id'], $account, null, null, $old);
		$cmd->run();

		Api\Json\Response::get()->call('egw.refresh', '', 'admin', $cmd->account, $content['account_id'] ? 'edit' : 'add');

		$addressbook_bo = new Api\Contacts();
		if (!($content['id'] = Api\Accounts::id2name($cmd->account, 'person_id')) ||
			!($contact = $addressbook_bo->read($content['id'])))
		{
			throw new Api\Exception\AssertionFailed("Can't find contact of just created account!");
		}
		// for a new account a new contact was created, need to merge that data with $content
		if (!$content['account_id'])
		{
			$content['account_id'] = $cmd->account;
			$content = array_merge($contact, $content);
		}
		else	// for updated account, we need to refresh etag
		{
			$content['etag'] = $contact['etag'];
		}
	}

	/**
	 * Delete an account
	 *
	 * @param array $content =null
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
			Framework::window_close(lang('Permission denied!!!'));
		}
		if ($content['delete'])
		{
			$cmd = new admin_cmd_delete_account($content['account_id'], $content['new_owner'], true);
			$msg = $cmd->run();
			if ($content['contact_id'])
			{
				Framework::refresh_opener($msg, 'addressbook', $content['contact_id'], 'delete');
			}
			else
			{
				Framework::refresh_opener($msg, 'admin', $content['account_id'], 'delete');
			}
			Framework::window_close();
		}
		$tpl = new Etemplate('admin.account.delete');
		$tpl->exec('admin_account::delete', $content, array(), array(), $content, 2);
	}

	/**
	 * Delete a group via ajax
	 *
	 * @param int $account_id
	 */
	public static function ajax_delete_group($account_id)
	{
		$cmd = new admin_cmd_delete_account(Api\Accounts::id2name(Api\Accounts::id2name($account_id)), null, false);
		$msg = $cmd->run();

		Api\Json\Response::get()->call('egw.refresh', $msg, 'admin', $account_id, 'delete');
	}

	/**
	 * Check entered data and return error-msg via json data or null
	 *
	 * @param array $data values for account_id and account_lid
	 * @param string $changed name of addressbook widget triggering change eg. "email", "n_given" or "n_family"
	 */
	public static function ajax_check(array $data, $changed)
	{
		// warn if anonymous user is renamed, as it breaks eg. sharing and Collabora
		if ($changed == 'account_lid' && Api\Accounts::id2name($data['account_id']) === 'anonymous' && $data['account_lid'] !== 'anonymous')
		{
			Api\Json\Response::get()->data(lang("Renaming user 'anonymous' will break file sharing and Collabora Online Office!"));
			return;
		}

		// for 1. password field just check password complexity
		if ($changed == 'account_passwd')
		{
			$data['account_fullname'] = $data['account_firstname'].' '.$data['account_lastname'];
			if (($error = Api\Auth::crackcheck($data['account_passwd'], null, null, null, $data)))
			{
				$error .= "\n\n".lang('If you ignore that error as admin, you should check "%1"!', lang('Must change password upon next login'));
			}
			Api\Json\Response::get()->data($error);
			return;
		}
		// generate default email address, but only for new Api\Accounts
		if (!$data['account_id'] && in_array($changed, array('n_given', 'n_family', 'account_lid')))
		{
			$email = Api\Accounts::email($data['account_firstname'], $data['account_lastname'], $data['account_lid']);
			if ($email && $email[0] != '@' && strpos($email, '@'))	// only add valid email addresses
			{
				Api\Json\Response::get()->assign('addressbook-edit_email', 'value', $email);
			}
		}

		if (!$data['account_lid'] && !$data['account_id']) return;	// makes no sense to check before

		// set home-directory when account_lid is entered, but only for new Api\Accounts
		if ($changed == 'account_lid' && !$data['account_id'] &&
			$GLOBALS['egw_info']['server']['ldap_extra_attributes'] &&
			$GLOBALS['egw_info']['server']['ldap_account_home'])
		{
			Api\Json\Response::get()->assign('addressbook-edit_homedirectory', 'value',
				$GLOBALS['egw_info']['server']['ldap_account_home'].'/'.preg_replace('/[^a-z0-9_.-]/i', '',
					Api\Translation::to_ascii($data['account_lid'])));
		}

		// set dummy membership to get no error about no members yet
		$data['account_memberships'] = array($data['account_primary_user'] = $GLOBALS['egw_info']['user']['account_primary_group']);

		try {
			$cmd = new admin_cmd_edit_user($data['account_id'], $data);
			$cmd->run(null, false, false, true);
		}
		catch(Exception $e)
		{
			Api\Json\Response::get()->data($e->getMessage());
		}
	}
}
