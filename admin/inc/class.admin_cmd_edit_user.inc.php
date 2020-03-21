<?php
/**
 * EGgroupware admin - admin command: edit/add a user
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-18 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * admin command: edit/add a user
 */
class admin_cmd_edit_user extends admin_cmd_change_pw
{
	/**
	 * Constructor
	 *
	 * @param string|int|array $account account name or id (!$account to add a new account), or array with all parameters
	 * @param array $set =null array with all data to change
	 * @param string $password =null password
	 * @param boolean $run_addaccount_hook =null default run addaccount for new Api\Accounts and editaccount for existing ones
	 * @param array $old =null array to log old values of $set
	 */
	function __construct($account, $set=null, $password=null, $run_addaccount_hook=null, array $old=null)
	{
		if (!is_array($account))
		{
			//error_log(__METHOD__."(".array2string($account).', '.array2string($set).", ...)");
			$account = array(
				'account' => $account,
				'set' => $set,
				'password' => is_null($password) ? $set['account_passwd'] : $password,
				'run_addaccount_hook' => $run_addaccount_hook,
				'old' => $old,
			);
		}
		admin_cmd::__construct($account);
	}

	/**
	 * change the account of a given user
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Api\Exception\NoPermission\Admin
	 * @throws Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws Api\Exception\WrongUserinput(lang('Error changing the password for %1 !!!',$this->account),99);
	 */
	protected function exec($check_only=false)
	{
		// check creator is still admin and not explicitly forbidden to edit accounts/groups
		if ($this->creator) $this->_check_admin('account_access',$this->account ? 16 : 4);

		admin_cmd::_instanciate_accounts();

		$data = $this->set;
		$data['account_type'] = 'u';

		if ($this->account)	// existing account
		{
			$data['account_id'] = admin_cmd::parse_account($this->account);
			//error_log(__METHOD__."($check_only) this->account=".array2string($this->account).', data[account_id]='.array2string($data['account_id']).", ...)");

			$data['old_loginid'] = admin_cmd::$accounts->id2name($data['account_id']);
		}
		if (!$data['account_lid'] && (!$this->account || !is_null($data['account_lid'])))
		{
			throw new Api\Exception\WrongUserinput(lang('You must enter a loginid'),9);
		}
		// Check if an account already exists as system user, and if it does deny creation
		if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
			!$GLOBALS['egw_info']['server']['ldap_allow_systemusernames'] && !$data['account_id'] &&
			function_exists('posix_getpwnam') && posix_getpwnam($data['account_lid']))
		{
			throw new Api\Exception\WrongUserinput(lang('There already is a system-user with this name. User\'s should not have the same name as a systemuser'),99);
		}
		if (!$data['account_lastname'] && (!$this->account || !is_null($data['account_lastname'])))
		{
			throw new Api\Exception\WrongUserinput(lang('You must enter a lastname'), 13);
		}
		if (!is_null($data['account_lid']) && ($id = admin_cmd::$accounts->name2id($data['account_lid'],'account_lid','u')) &&
			(string)$id !== (string)$data['account_id'])
		{
			throw new Api\Exception\WrongUserinput(lang('That loginid has already been taken'), 11);
		}
		if (isset($data['account_passwd_2']) && $data['account_passwd'] != $data['account_passwd_2'])
		{
			throw new Api\Exception\WrongUserinput(lang('The two passwords are not the same'), 12);
		}
		$expires = self::_parse_expired($data['account_expires'],(boolean)$this->account);
		if ($expires === 0)	// deactivated
		{
			$data['account_expires'] = -1;
			$data['account_status'] = '';
		}
		else
		{
			$data['account_expires'] = $expires;
			$data['account_status'] = is_null($expires) ? null : ($expires == -1 || $expires > time() ? 'A' : '');
		}

		$data['changepassword'] = admin_cmd::parse_boolean($data['changepassword'],$this->account ? null : true);
		// automatic set anonymous flag for username "anonymous", to not allow to create anonymous user without it
		$data['anonymous'] = ($data['account_lid'] ?: admin_cmd::$accounts->id2name($this->account)) === 'anonymous' ?
			true : admin_cmd::parse_boolean($data['anonymous'],$this->account ? null : false);

		if ($data['mustchangepassword'] && $data['changepassword'])
		{
			$data['account_lastpwd_change']=0;
		}

		if (!$data['account_primary_group'] && $this->account)
		{
			$data['account_primary_group'] = null;	// dont change
		}
		else
		{
			if (!$data['account_primary_group'] && admin_cmd::$accounts->exists('Default') == 2)
			{
				$data['account_primary_group'] = 'Default';
			}
			$data['account_primary_group'] = admin_cmd::parse_account($data['account_primary_group'],false);
		}
		if (!$data['account_groups'] && $this->account)
		{
			$data['account_groups'] = null;	// dont change
		}
		else
		{
			if (!$data['account_groups'] && admin_cmd::$accounts->exists('Default') == 2)
			{
				$data['account_groups'] = array('Default');
			}
			$data['account_groups'] = admin_cmd::parse_accounts($data['account_groups'],false);
		}
		if ($check_only) return true;

		if ($this->account)
		{
			// invalidate account, before reading it, to code with changed to DB or LDAP outside EGw
			Api\Accounts::cache_invalidate($data['account_id']);
			if (!($old = admin_cmd::$accounts->read($data['account_id'])))
			{
				throw new Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$this->account),15);
			}
			// as the current account class always sets all values, we have to add the not specified ones
			foreach($data as $name => &$value)
			{
				if (is_null($value)) $value = $old[$name];
			}
		}
		else
		{
			unset($data['account_id']);	// otherwise add will fail under postgres
		}
		// hook allowing apps to intercept adding/editing Api\Accounts before saving them
		Api\Hooks::process($data+array(
			'location' => $this->account ? 'pre_editaccount' : 'pre_addaccount',
		),False,True);	// called for every app now, not only enabled ones)

		if (!($data['account_id'] = admin_cmd::$accounts->save($data)))
		{
			//_debug_array($data);
			throw new Api\Db\Exception(lang("Error saving account!"),11);
		}
		// make new account_id available to caller
		$update = (boolean)$this->account;
		if (!$this->account) $this->account = $data['account_id'];

		if ($data['account_groups'])
		{
			admin_cmd::$accounts->set_memberships($data['account_groups'],$data['account_id']);
		}
		if (!is_null($data['anonymous']))
		{
			admin_cmd::_instanciate_acl();
			if ($data['anonymous'])
			{
				admin_cmd::$acl->add_repository('phpgwapi','anonymous',$data['account_id'],1);
			}
			else
			{
				admin_cmd::$acl->delete_repository('phpgwapi','anonymous',$data['account_id']);
			}
		}
		if (!is_null($data['changepassword']))
		{
			if (!$data['changepassword'])
			{
				admin_cmd::$acl->add_repository('preferences','nopasswordchange',$data['account_id'],1);
			}
			else
			{
				admin_cmd::$acl->delete_repository('preferences','nopasswordchange',$data['account_id']);
			}
		}
		// if we have a password and it's not a hash, and auth_type != account_repository
		if (!is_null($this->password) &&
			!preg_match('/^\\{[a-z5]{3,5}\\}.+/i',$this->password) &&
			!preg_match('/^[0-9a-f]{32}$/',$this->password) &&	// md5 hash
			admin_cmd::$accounts->config['auth_type'] != admin_cmd::$accounts->config['account_repository'])
		{
			admin_cmd_change_pw::exec();		// calling the exec method of the admin_cmd_change_pw
		}
		$data['account_passwd'] = $this->password;
		$GLOBALS['hook_values'] =& $data;
		Api\Hooks::process($GLOBALS['hook_values']+array(
			'location' => $update && $this->run_addaccount_hook !== true ? 'editaccount' : 'addaccount'
		),False,True);	// called for every app now, not only enabled ones)

		return lang("Account %1 %2", $data['account_lid'] ? $data['account_lid'] : Api\Accounts::id2name($this->account),
			$update ? lang('updated') : lang("created with id #%1", $this->account));
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('%1 user %2',$this->account ? lang('Edit') : lang('Add'),
			admin_cmd::display_account($this->account ? $this->account : $this->set['account_lid']));
	}

	/**
	 * Get name of eTemplate used to make the change to derive UI for history
	 *
	 * @return string|null etemplate name
	 */
	function get_etemplate_name()
	{
		return 'admin.account';
	}

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * @return array
	 */
	function get_change_labels()
	{
		$labels = parent::get_change_labels();
		$labels += array(
			'account_lastname' => 'lastname',
			'account_firstname' => 'firstname'
		);
		return $labels;
	}

	/**
	 * parse the expired string and return the expired date as timestamp
	 *
	 * @param string $str date, 'never', 'already' or '' (=dont change, or default of never of new Api\Accounts)
	 * @param boolean $existing
	 * @return int timestamp, 0 for already, -1 for never or null for dont change
	 * @throws Api\Exception\WrongUserinput(lang('Invalid formated date "%1"!',$datein),6);
	 */
	private function _parse_expired($str,$existing)
	{
		switch($str)
		{
			case '':
				if ($existing) return null;
				// fall through --> default for new Api\Accounts is never
			case 'never':
				return -1;
			case 'already':
				return 0;
		}
		return admin_cmd::parse_date($str);
	}
}
