<?php
/**
 * eGgroupWare admin - admin command: give or remove run rights from a given account and application
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/admin/inc/class.admin_cmd.inc.php');

/**
 * admin command: give or remove run rights from a given account and application
 */
class admin_cmd_account_app extends admin_cmd 
{
	/**
	 * Constructor
	 *
	 * @param boolean/array $allow true=give rights, false=remove rights, or array with all 3 params
	 * @param string/int $account=null account name or id
	 * @param array/string $apps=null app-names
	 */
	function __construct($allow,$account=null,$apps=null)
	{
		if (!is_array($allow))
		{
			$allow = array(
				'allow' => $allow,
				'account' => $account,
				'apps' => $apps,
			);
		}
		if (isset($allow['apps']) && !is_array($allow['apps']))
		{
			$allow['apps'] = explode(',',$allow['apps']);
		}
		parent::__construct($allow);
	}

	/**
	 * give or remove run rights from a given account and application
	 * 
	 * @return string success message
	 * @throws Exception(lang("Permission denied !!!"),2)
	 * @throws Exception(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws Exception(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
	 */
	function exec()
	{
		admin_cmd::_instanciate_acl($this->creator);
		admin_cmd::_instanciate_accounts();

		$account_id = admin_cmd::_parse_account($this->account);
		// check creator is still admin and not explicitly forbidden to edit accounts/groups
		if ($this->creator) $this->_check_admin($account_id > 0 ? 'account_access' : 'group_access',16);
		
		$apps = admin_cmd::_parse_apps($this->apps);
		//echo "account=$this->account, account_id=$account_id, apps: ".implode(', ',$apps)."\n";
		foreach($apps as $app)
		{
			if ($this->allow)
			{
				admin_cmd::$acl->add_repository($app,'run',$account_id,1);
			}
			else
			{
				admin_cmd::$acl->delete_repository($app,'run',$account_id);
			}
		}
		return lang('Applications run rights updated.');
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return lang('%1 rights for %2 and applications %3',$this->allow ? lang('Grant') : lang('Remove'),
			$this->account,implode(', ',$this->apps));
	}
}
