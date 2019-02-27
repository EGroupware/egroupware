<?php
/**
 * EGroupWare admin - admin command: check ACL for entries of deleted accounts
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * admin command: check ACL for entries of deleted accounts
 */
class admin_cmd_check_acl extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param array $data =array() default parm from parent class, no real parameters
	 */
	function __construct($data=array())
	{
		admin_cmd::__construct($data);
	}

	/**
	 * give or remove run rights from a given account and application
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception(lang("Permission denied !!!"),2)
	 * @throws Exception(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws Exception(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
	 */
	protected function exec($check_only=false)
	{
		if ($check_only) return true;

		admin_cmd::_instanciate_accounts();
		$deleted = 0;
		// get all accounts: users+groups and also non-active ones (not yet deleted!)
		if (($all_accounts = admin_cmd::$accounts->search(array('type'=>'both','active'=>false))))
		{
			$ids = array();
			foreach($all_accounts as $account)
			{
				$ids[] = $account['account_id'];
			}
			$GLOBALS['egw']->db->query("DELETE FROM egw_acl WHERE acl_account NOT IN (".implode(',',$ids).") OR acl_appname='phpgw_group' AND acl_location NOT IN ('".implode("','",$ids)."')",__LINE__,__FILE__);
			$deleted = $GLOBALS['egw']->db->affected_rows();
		}
		return lang("%1 ACL records of not (longer) existing accounts deleted.",$deleted);
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('Check ACL for entries of not (longer) existing accounts');
	}
}
