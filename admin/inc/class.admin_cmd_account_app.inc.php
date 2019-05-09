<?php
/**
 * EGroupware admin - admin command: give or remove run rights from a given account and application
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * admin command: give or remove run rights from a given account and application
 *
 * @property boolean $allow True for permission being added, false for a permission
 *	being removed
 * @property string[] $apps List of application names that we're modifying
 *	permissions for.
 */
class admin_cmd_account_app extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param boolean|array $allow true=give rights, false=remove rights, or array with all 3 params
	 * @param string|int $account =null account name or id
	 * @param array|string $apps =null app-names
	 */
	function __construct($allow,$account=null,$apps=null, $other=array())
	{
		if (!is_array($allow))
		{
			$allow = array(
				'allow' => $allow,
				'account' => $account,
				'apps' => $apps,
			)+(array)$other;
		}
		if (isset($allow['apps']) && !is_array($allow['apps']))
		{
			$allow['apps'] = explode(',',$allow['apps']);
		}
		admin_cmd::__construct($allow);
	}

	/**
	 * give or remove run rights from a given account and application
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Api\Exception\NoPermission\Admin
	 * @throws Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws Api\Exception\WrongUserinput(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
	 */
	protected function exec($check_only=false)
	{
		$account_id = admin_cmd::parse_account($this->account);
		// check creator is still admin and not explicitly forbidden to edit accounts/groups
		if ($this->creator) $this->_check_admin($account_id > 0 ? 'account_access' : 'group_access',16);

		$apps = admin_cmd::parse_apps($this->apps);

		$old_rights = (array)$GLOBALS['egw']->acl->get_app_list_for_id('run', Egroupware\Api\Acl::READ, $account_id);
		$new_rights = $this->allow ?
			array_merge($old_rights, $apps) :
			array_diff($old_rights, $apps);

		// Sometimes keys get stringified, so remove them
		$this->set = Array('app' => array_values($new_rights));
		$this->old = Array('app' => array_values($old_rights));
		if ($check_only) return true;

		//echo "account=$this->account, account_id=$account_id, apps: ".implode(', ',$apps)."\n";
		admin_cmd::_instanciate_acl($account_id);
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
		$apps = $this->apps;
		foreach($apps as &$app)
		{
			$app = lang($app);
		}
		return lang('%1 rights for %2 and applications %3',$this->allow ? lang('Grant') : lang('Remove'),
			admin_cmd::display_account($this->account),implode(', ',$apps));
	}

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * Reading them from admin.account template
	 *
	 * @return array
	 */
	function get_change_labels()
	{
		$labels = parent::get_change_labels();

		$labels['app'] = 'Applications';

		return $labels;
	}
	/**
	 * Return widget types (indexed by field key) for changes
	 *
	 * Used by historylog widget to show the changes the command recorded.
	 */
	function get_change_widgets()
	{
		$widgets = parent::get_change_widgets();

		$widgets['app'] = 'select-app';

		return $widgets;
	}
}
