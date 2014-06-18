<?php
/**
 * eGgroupWare admin - admin command: change the password of a given user
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * admin command: change the password of a given user
 */
class admin_cmd_change_pw extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param string/int/array $account account name or id, or array with all parameters
	 * @param string $password=null password
	 */
	function __construct($account,$password=null)
	{
		if (!is_array($account))
		{
			$account = array(
				'account' => $account,
				'password' => $password,
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
	 * @throws egw_exception_wrong_userinput(lang('Error changing the password for %1 !!!',$this->account),99);
	 */
	protected function exec($check_only=false)
	{
		$account_id = admin_cmd::parse_account($this->account,true);	// true = user, no group
		// check creator is still admin and not explicitly forbidden to edit accounts
		if ($this->creator) $this->_check_admin('account_access',16);

		if ($check_only) return true;

		$auth = new auth;

		if (!$auth->change_password(null, $this->password, $account_id))
		{
			// as long as the auth class is not throwing itself ...
			throw new Exception(lang('Error changing the password for %1 !!!',$this->account),99);
		}
		return lang('Password updated');
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('change password for %1',admin_cmd::display_account($this->account));
	}
}
