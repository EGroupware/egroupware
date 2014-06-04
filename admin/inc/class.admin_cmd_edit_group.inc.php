<?php
/**
 * eGgroupWare admin - admin command: edit/add a group
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * admin command: edit/add a user
 */
class admin_cmd_edit_group extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param string/int/array $account account name or id (!$account to add a new account), or array with all parameters
	 * @param array $set=null array with all data to change
	 */
	function __construct($account,$set=null)
	{
		if (!is_array($account))
		{
			$account = array(
				'account' => $account,
				'set' => $set,
			);
		}
		admin_cmd::__construct($account);
	}

	/**
	 * change the password of a given user
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws egw_exception_no_admin
	 * @throws egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$this->account),15);
	 */
	protected function exec($check_only=false)
	{
		// check creator is still admin and not explicitly forbidden to edit accounts/groups
		if ($this->creator) $this->_check_admin('group_access',$this->account ? 16 : 4);

		admin_cmd::_instanciate_accounts();

		$data = $this->set;

		if ($this->account)	// existing account
		{
			$data['account_id'] = admin_cmd::parse_account($this->account,false);
		}
		else
		{
			$data += array(
				'account_type' => 'g',
				'account_status' => 'A',	// not used, but so we do the same thing as the web-interface
				'account_expires' => -1,
			);
		}
		if (!$data['account_lid'] && (!$this->account || !is_null($data['account_lid'])))
		{
			throw new egw_exception_wrong_userinput(lang('You must enter a group name.'),9);
		}
		if (!is_null($data['account_lid']) && ($id = admin_cmd::$accounts->name2id($data['account_lid'],'account_lid','g')) &&
			$id !== $data['account_id'])
		{
			throw new egw_exception_wrong_userinput(lang('That loginid has already been taken'),999);
		}
		if (!$data['account_members'] && !$this->account)
		{
			throw new egw_exception_wrong_userinput(lang('You must select at least one group member.'),9);
		}
		if ($data['account_members'])
		{
			$data['account_members'] = admin_cmd::parse_accounts($data['account_members'],true);
		}
		if ($check_only) return true;

		if (($update = $this->account))
		{
			// invalidate account, before reading it, to code with changed to DB or LDAP outside EGw
			accounts::cache_invalidate($data['account_id']);
			if (!($old = admin_cmd::$accounts->read($data['account_id'])))
			{
				throw new egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$this->account),15);
			}
			// as the current account class always sets all values, we have to add the not specified ones
			foreach($data as $name => &$value)
			{
				if (is_null($value)) $value = $old[$name];
			}
		}
		if (!($data['account_id'] = admin_cmd::$accounts->save($data)))
		{
			//_debug_array($data);
			throw new egw_exception_db(lang("Error saving account!"),11);
		}
		$data['account_name'] = $data['account_lid'];	// also set deprecated name
		if ($update) $data['old_name'] = $old['account_lid'];	// make old name available for hooks
		$GLOBALS['hook_values'] =& $data;
		$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
			'location' => $update ? 'editgroup' : 'addgroup'
		),False,True);	// called for every app now, not only enabled ones)

		if ($data['account_members'])
		{
			admin_cmd::$accounts->set_members($data['account_members'],$data['account_id']);
		}
		// make new account_id available to caller
		$this->account = $data['account_id'];

		return lang("Group %1 %2", $data['account_lid'] ? $data['account_lid'] : accounts::id2name($this->account),
			$update ? lang('updated') : lang("created with id #%1", $this->account));
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('%1 group %2',$this->account ? lang('Edit') : lang('Add'),
			admin_cmd::display_account($this->account ? $this->account : $this->set['account_lid']));
	}
}
