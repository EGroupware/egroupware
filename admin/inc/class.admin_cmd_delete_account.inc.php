<?php
/**
 * eGgroupWare admin - admin command: delete an account (user or group)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * admin command: delete an account (user or group)
 */
class admin_cmd_delete_account extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param string/int/array $account account name or id, or array with all parameters
	 * @param string $new_user=null if specified, account to transfer the data to (users only)
	 * @param string $is_user=true type of the account: true=user, false=group
	 */
	function __construct($account,$new_user=null,$is_user=true)
	{
		if (!is_array($account))
		{
			$account = array(
				'account' => $account,
				'new_user' => $new_user,
				'is_user' => $is_user,
			);
		}
		admin_cmd::__construct($account);
	}

	/**
	 * delete an account (user or group)
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws egw_exception_no_admin
	 * @throws egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws egw_exception_wrong_userinput(lang('Error changing the password for %1 !!!',$this->account),99);
	 */
	protected function exec($check_only=false)
	{
		$account_id = admin_cmd::parse_account($this->account,$this->is_user);
		admin_cmd::_instanciate_accounts();
		$account_lid = admin_cmd::$accounts->id2name($account_id);
		
		if ($this->is_user && $this->new_user)
		{
			$new_user = admin_cmd::parse_account($this->new_user,true);	// true = user, no group
		}
		// check creator is still admin and not explicitly forbidden to edit accounts
		if ($this->creator) $this->_check_admin($this->is_user ? 'account_access' : 'group_access',32);
		
		if ($check_only) return true;
		
		// delete the account
		$GLOBALS['hook_values'] = array(
			'account_id'  => $account_id,
			'account_lid' => $account_lid,
			'account_name'=> $account_lid,		// depericated name for deletegroup hook
			'new_owner'   => (int)$new_user,	// deleteaccount only
			'location'    => $this->is_user ? 'deleteaccount' : 'deletegroup',
		);
		// first all other apps, then preferences and admin
		foreach(array_merge(array_diff(array_keys($GLOBALS['egw_info']['apps']),array('preferences','admin')),array('preferences','admin')) as $app)
		{
			$GLOBALS['egw']->hooks->single($GLOBALS['hook_values'],$app);
		}			
		if (!$this->is_user) $GLOBALS['egw']->accounts->delete($account_id);	// groups get not deleted via the admin hook, as users
	
		if ($account_id < 0)
		{
			return lang("Group '%1' deleted.",$this->account)."\n\n";
		}
		return lang("Account '%1' deleted.",$this->account)."\n\n";
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('Delete account %1',admin_cmd::display_account($this->account));
	}
}
