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
 */
class admin_cmd_acl extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param boolean|array $allow true=give rights, false=remove rights, or array with all params
	 * @param string|int $account =null account name or id
	 * @param array|string $app =null app-name
	 * @param string $location =null ACL location.  Usually a user or group ID, but may also be any app-specific string
	 * @param int $rights =null ACL rights.  See Api\ACL.
	 */
	function __construct($allow,$account=null,$app=null,$location=null,$rights=null)
	{
		if (!is_array($allow))
		{
			$allow = array(
				'allow' => $allow,
				'account' => $account,
				'app' => $app,
				'location' => $location,
				'rights' => (int)$rights
			);
		}

		// Make sure we only deal with real add/remove changes

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


		list($app) = admin_cmd::parse_apps(array($this->app));
		$location = $this->location;
		$rights = (int)$this->rights;


		$old_rights = (int)$GLOBALS['egw']->acl->get_specific_rights_for_account($account_id, $location, $app);
		$new_rights = max(0,$old_rights + (($this->allow ? 1 : -1) * $rights));

		$this->set = $new_rights;
		$this->old = $old_rights;
		if ($check_only) return true;

		//echo "account=$this->account, account_id=$account_id, apps: ".implode(', ',$apps)."\n";
		admin_cmd::_instanciate_acl($account_id);

		if ($new_rights)
		{
			admin_cmd::$acl->add_repository($app,$location,$account_id,$new_rights);
		}
		else
		{
			admin_cmd::$acl->delete_repository($app,$location,$account_id);
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
		$rights = $this->rights;
		$location = lang($this->location);

		if($this->location == 'run')
		{
			$rights = lang('run');
		}
		$names = Api\Hooks::single(array(
			'location' => 'acl_rights'
		), $this->app);
		if($names[$rights])
		{
			$rights = lang($names[$rights]);
		}

		if(is_numeric($this->location))
		{
			$location = admin_cmd::display_account($this->location);
		}
		return lang('%1 %2 rights for %3 on %4 to %5',
			$this->allow ? lang('Grant') : lang('Remove'),
			$rights,
			admin_cmd::display_account($this->account),
			$this->app,
			$location
		);
	}

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * @return array
	 */
	function get_change_labels()
	{
		$labels = parent::get_change_labels();
		$labels[get_class($this)] = lang('ACL');
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
		// Specify app to get bitwise permissions, since it's not always admin
		$widgets[get_class($this)] = 'select-bitwise';

		// Get select options for this app, slide them in via modifications
		// since historylog doesn't do attributes on value widgets
		Api\Etemplate::setElementAttribute('history['.get_class($this).']', 'select_options',
				Api\Etemplate\Widget\Select::typeOptions('select-bitwise', ','.$this->app)
		);
		return $widgets;
	}
}
