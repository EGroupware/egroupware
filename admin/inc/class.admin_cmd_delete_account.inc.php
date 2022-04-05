<?php
/**
 * EGroupware admin - admin command: delete an account (user or group)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;

/**
 * admin command: delete an account (user or group)
 */
class admin_cmd_delete_account extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param string|int|array $account account name or id, or array with all parameters
	 *	or string "--not-existing" to delete all, in account repository no longer existing, accounts
	 * @param string $new_user =null if specified, account to transfer the data to (users only)
	 * @param string $is_user =true type of the account: true=user, false=group
	 * @param array $extra =array() values for requested(_email), comment, ...
	 */
	function __construct($account, $new_user=null, $is_user=true, array $extra=array())
	{
		if (!is_array($account))
		{
			$account = array(
				'account' => $account,
				'new_user' => $new_user,
				'is_user' => $is_user,
			)+$extra;
		}
		if (empty($account['change_apps']))
		{
			$account['change_apps'] = [];
		}
		admin_cmd::__construct($account);
	}

	/**
	 * delete an account (user or group)
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Api\Exception\NoPermission\Admin
	 * @throws Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws Api\Exception\WrongUserinput(lang('Error changing the password for %1 !!!',$this->account),99);
	 */
	protected function exec($check_only=false)
	{
		// check creator is still admin and not explicitly forbidden to edit accounts
		if ($this->creator) $this->_check_admin($this->is_user ? 'account_access' : 'group_access',32);

		if ($this->account === '--not-existing')
		{
			return $this->delete_not_existing($check_only);
		}
		$account_id = admin_cmd::parse_account($this->account,$this->is_user);
		admin_cmd::_instanciate_accounts();
		$account_lid = admin_cmd::$accounts->id2name($account_id);

		if ($this->is_user && $this->new_user)
		{
			$new_user = admin_cmd::parse_account($this->new_user,true);	// true = user, no group
		}
		if ($check_only) return true;

		$this->delete_account($this->is_user, $account_id, $account_lid, $new_user);

		if ($account_id < 0)
		{
			return lang("Group '%1' deleted.",$account_lid)."\n\n";
		}
		return lang("Account '%1' deleted.",$account_lid)."\n\n";
	}

	/**
	 * Delete all in account repository no longer existing accounts
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string with success message
	 */
	protected function delete_not_existing($check_only=false)
	{
		admin_cmd::_instanciate_accounts();
		$repo_ids = array();
		if (($all_accounts = admin_cmd::$accounts->search(array('type'=>'both','active'=>false))))
		{
			foreach($all_accounts as $account)
			{
				$repo_ids[] = $account['account_id'];
			}
		}
		//print_r($repo_ids);

		static $ignore = array(
			'egw_admin_queue' => array('cmd_account'),	// contains also deleted accounts / admin history
		);
		$account_ids = array();
		$account_cols = admin_cmd_change_account_id::get_account_colums();
		//print_r($account_cols);
		foreach($account_cols as $app => $data)
		{
			if (!isset($GLOBALS['egw_info']['apps'][$app])) continue;	// $app is not installed

			$db = clone($GLOBALS['egw']->db);
			$db->set_app($app);
			if ($check_only) $db->log_updates = $db->readonly = true;

			foreach($data as $table => $columns)
			{
				$db->column_definitions = $db->get_table_definitions($app,$table);
				$db->column_definitions = $db->column_definitions['fd'];
				if (!$columns || substr($table, 0, 4) != 'egw_')
				{
					//echo "$app: $table no columns with account-id's\n";
					continue;	// noting to do for this table
				}
				// never check / use accounts-table (not used for LDAP/AD, all in for SQL)
				if ($table == 'egw_accounts') continue;

				if (!is_array($columns)) $columns = array($columns);

				foreach($columns as $column)
				{
					$type = $where = null;
					if (is_array($column))
					{
						$type = $column['.type'];
						unset($column['.type']);
						$where = $column;
						$column = array_shift($where);
					}
					if (in_array($type, array('abs','prefs')))	// would need special handling
					{
						continue;
					}
					if (isset($ignore[$table]) && in_array($column, $ignore[$table]))
					{
						continue;
					}
					if ($table == 'egw_acl' && $column == 'acl_location')
					{
						$where[] = "acl_appname='phpgw_group'";
					}
					$ids = array();
					foreach($rs=$db->select($table, 'DISTINCT '.$column, $where, __LINE__, __FILE__) as $row)
					{
						foreach(explode(',', $row[$column]) as $account_id)
						{
							if ($account_id && is_numeric($account_id) && !in_array($account_id, $repo_ids))
							{
								$account_ids[$account_id] = $ids[] = $account_id;
							}
						}
					}
					if ($ids) echo $rs->sql.": ".implode(', ', $ids)."\n";
				}
			}
		}
		//print_r($account_ids);

		asort($account_ids, SORT_NUMERIC);
		echo count($account_ids)." not existing account_id's found in EGroupware, ".count($repo_ids)." exist in account repository\n".
			"--> following should be deleted: ".implode(', ', $account_ids)."\n";

		if ($check_only) return true;

		if ($this->new_user)
		{
			$new_user = admin_cmd::parse_account($this->new_user,true);	// true = user, no group
		}
		foreach($account_ids as $account_id)
		{
			$this->delete_account($account_id > 0, $account_id, 'account'.$account_id, $account_id > 0 ? $new_user : null);
		}
		Api\Cache::flush(Api\Cache::INSTANCE);

		return lang("Total of %1 accounts deleted.", count($account_ids))."\n";
	}

	/**
	 * Delete account incl. calling all necessary hooks
	 *
	 * @param boolean $is_user true: user, false: group
	 * @param int $account_id numerical account_id of use to delete
	 * @param string $account_lid =null account_lid of user to delete
	 * @param int $new_user =null if given account_id to transfer data to
	 */
	protected function delete_account($is_user, $account_id, $account_lid=null, $new_user=null)
	{
		set_time_limit(0);

		// delete the account
		$GLOBALS['hook_values'] = array(
			'account_id'   => $account_id,
			'account_lid'  => $account_lid,
			'account_name' => $account_lid,        // depericated name for deletegroup hook
			'new_owner'    => (int)$new_user,    // deleteaccount only
			'location'     => $is_user ? 'deleteaccount' : 'deletegroup',
		);
		// First do apps that were not selected
		$skip_apps = array();
		$do_last = array('preferences','admin','api');
		foreach(array_diff(array_keys($GLOBALS['egw_info']['apps'] ?? []), array_merge($this->change_apps,$do_last)) as $app)
		{
			$skip_apps[] = $app;
			Api\Hooks::single(array_merge($GLOBALS['hook_values'], array('new_owner' => 0)), $app, true);
		}
		
		// Filemanager is a special case, since the hook is in API not filemanager
		$vfs_new_owner = in_array('filemanager', $this->change_apps) ? $new_user : 0;
		Api\Vfs\Hooks::deleteAccount(array_merge($GLOBALS['hook_values'], array('new_owner' => $vfs_new_owner)));

		// first all other apps, then preferences, admin & api
		foreach(array_merge($this->change_apps,$do_last) as $app)
		{
			Api\Hooks::single($GLOBALS['hook_values'], $app, true);
		}
		// store old content at time of deletion
		$this->old = $GLOBALS['egw']->accounts->read($account_id);

		$GLOBALS['egw']->accounts->delete($account_id);
	}

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * Reading them from admin.account template
	 *
	 * @return array
	 */
	function get_etemplate_name()
	{
		return $this->is_user ? 'admin.account':
			($GLOBALS['egw_info']['apps']['stylite'] ? 'stylite' : 'groups').'.group.edit';
	}

	/**
	 * Return widget types (indexed by field key) for changes
	 *
	 * Used by historylog widget to show the changes the command recorded.
	 */
	function get_change_labels()
	{
		$widgets = parent::get_change_labels();

		$widgets['account_id'] = 'numerical ID';	// normaly not displayed

		return $widgets;
	}

	/**
	 * Return widget types (indexed by field key) for changes
	 *
	 * Used by historylog widget to show the changes the command recorded.
	 */
	function get_change_widgets()
	{
		$widgets = parent::get_change_widgets();

		$widgets['account_id'] = 'integer';	// normaly not displayed

		return $widgets;
	}

	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * Reimplement to supress data not relevant for groups, but historically stored
	 *
	 * @return array
	 */
	function as_array()
	{
		$data = parent::as_array();

		if (!$this->is_user)
		{
			$data['old'] = array_diff_key($data['old'], array_flip([
				'account_pwd', 'account_status',
				'account_expires', 'account_primary_group',
				'account_lastlogin', 'account_lastloginfrom',
				'account_lastpwd_change', 'members-active',
				'account_firstname', 'account_lastname', 'account_fullname',
			]));
		}
		unset($data['old']['account_type']);

		return $data;
	}

	/**
	}
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __toString()
	{
		return lang('Delete account %1',
			// use own data to display deleted name of user/group
			$this->old['account_lid'] ? ($this->is_user ? lang('User') : lang('Group')).' '.$this->old['account_lid'] :
			admin_cmd::display_account($this->account));
	}
}
