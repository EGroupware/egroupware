<?php
/**
 * EGgroupware admin - admin command: edit/add a group
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
 * admin command: edit/add a user
 */
class admin_cmd_edit_group extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param string|int|array $account account name or id (!$account to add a new account), or array with all parameters
	 * @param array $set =null array with all data to change
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
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Api\Exception\NoPermission\Admin
	 * @throws Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$this->account),15);
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
		$data += array(
			'account_type' => 'g',
			'account_status' => 'A',	// not used, but so we do the same thing as the web-interface
			'account_expires' => -1,
		);
		if(!array_key_exists('account_email', $data))
        {
            $data['account_email'] = null;
        }
		if (!$data['account_lid'] && (!$this->account || !is_null($data['account_lid'])))
		{
			throw new Api\Exception\WrongUserinput(lang('You must enter a group name.'), 17);
		}
		if (!is_null($data['account_lid']) && ($id = admin_cmd::$accounts->name2id($data['account_lid'],'account_lid','g')) &&
			$id !== $data['account_id'])
		{
		    throw new Api\Exception\WrongUserinput(lang('That loginid has already been taken'), 11);
		}
		if (!$data['account_members'] && !$this->account)
		{
			throw new Api\Exception\WrongUserinput(lang('You must select at least one group member.'), 18);
		}
		if ($data['account_members'])
		{
			$data['account_members'] = admin_cmd::parse_accounts($data['account_members'],true);
		}
		if ($check_only) return true;

		if (($update = $this->account))
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
		// Make sure we have lid for hook even if not changed, some backend require it
		if (empty($data['account_lid']))
		{
			$data['account_lid'] = admin_cmd::$accounts->id2name($data['account_id']);
		}
		if (!($data['account_id'] = admin_cmd::$accounts->save($data)))
		{
			//_debug_array($data);
			throw new Api\Db\Exception(lang("Error saving account!"),11);
		}
		// set deprecated name
		$data['account_name'] = $data['account_lid'];

		if ($update) $data['old_name'] = $old['account_lid'];	// make old name available for hooks
		$GLOBALS['hook_values'] =& $data;
		Api\Hooks::process($GLOBALS['hook_values']+array(
			'location' => $update ? 'editgroup' : 'addgroup'
		),False,True);	// called for every app now, not only enabled ones)

		if ($data['account_members'])
		{
			admin_cmd::$accounts->set_members($data['account_members'],$data['account_id']);
		}
		// make new account_id available to caller
		$this->account = $data['account_id'];

		return lang("Group %1 %2", $data['account_lid'] ? $data['account_lid'] : Api\Accounts::id2name($this->account),
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

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * Reading them from admin.account template
	 *
	 * @return array
	 */
	function get_etemplate_name()
	{
		return ($GLOBALS['egw_info']['apps']['stylite'] ? 'stylite' : 'groups').'.group.edit';
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
		unset($labels['${row}[run]']);

		$labels['account_members'] = 'Members';
		$labels['account_email'] = 'Email';

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

		$widgets['account_id'] = 'integer';	// normaly not displayed
		$widgets['run'] = 'select-app';

		return $widgets;
	}

	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * Reimplement to supress data not relevant for groups, but historically stored
	 *
	 * @todo Fix command to store it's data in a more sane way, like we use it.
	 * @return array
	 */
	function as_array()
	{
		$data = parent::as_array();

		// for some reason old is stored under set
		if (isset($data['set']['old']))
		{
			$data['old'] = $data['set']['old'];
			unset($data['set']['old']);
		}
		if (!empty($data['set']['old_run']))
		{
			$data['old']['run'] = $data['set']['old_run'];
			usort($data['old']['run'], function($a, $b)
			{
				return strcasecmp(lang($a), lang($b));
			});
			unset($data['set']['old_run']);
		}
		if (!empty($data['set']['apps']))
		{
			$data['set']['run'] = array_diff(array_map(function($data)
			{
				return $data['run'] ? $data['appname'] : null;
			}, $data['set']['apps']), [null]);
			usort($data['set']['run'], function($a, $b)
			{
				return strcasecmp(lang($a), lang($b));
			});
			unset($data['set']['apps']);
		}

		// remove values not relevant to groups
		foreach(['old', 'set'] as $name)
		{
			$data[$name] = array_diff_key($data[$name], array_flip([
				'account_pwd', 'account_status', 'account_type',
				'account_expires', 'account_primary_group',
				'account_lastlogin', 'account_lastloginfrom',
				'account_lastpwd_change', 'members-active',
				'account_firstname', 'account_lastname', 'account_fullname',
			]));
		}

		// remove unchanged values (null == '' and arrays might not be sorted)
		foreach($data['set'] as $name => $value)
		{
			if ($data['old'][$name] == $value ||
				is_array($value) && sort($value) && sort($data['old'][$name]) && $value == $data['old'][$name])
			{
				unset($data['old'][$name], $data['set'][$name]);
			}
		}
		return $data;
	}

}
