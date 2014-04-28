<?php
/**
 * EGroupware: Admin ACL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2013 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for admin ACL
 *
 * Will also be extended by preferences_acl for user ACL
 */
class admin_acl
{
	/**
	 * Methods callable via menuaction
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
		'acl' => true,
	);

	/**
	 * Reference to global acl class (instanciated for current user)
	 *
	 * @var acl
	 */
	protected $acl;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->acl = $GLOBALS['egw']->acl;
	}

	/**
	 * Edit or add an ACL entry
	 *
	 * @param array $content
	 */
	public function acl(array $content=null)
	{
		$state = (array)egw_cache::getSession(__CLASS__, 'state');
		$tpl = new etemplate_new('admin.acl.edit');	// auto-repeat of acl & label not working with etemplate_new!

		if (!is_array($content))
		{
			if (isset($_GET['id']))
			{
				list($app, $account, $location) = explode(':', $_GET['id'], 3);

				if (!($rights = $this->acl->get_specific_rights_for_account($account, $location, $app)))
				{
					egw_framework::window_close(lang('ACL entry not found!'));
				}
			}
			else
			{
				$app = !empty($_GET['app']) && isset($GLOBALS['egw_info']['apps'][$_GET['app']]) ?
					$_GET['app'] : $state['acl_appname'];
				$location = $state['filter'] == 'run' ? 'run' : null;//$state['account_id'];
				$account = $state['account_id'];//$state['filter'] == 'run' ? $state['account_id'] : $state['acl_account'];
				$rights = 1;
			}
			$content = array(
				'id' => $_GET['id'],
				'acl_appname' => $app,
				'acl_location' => $location,
				'acl_account' => $account,
			);
			if ($location == 'run')
			{
				$content['apps'] = array_keys($this->acl->get_user_applications($account, false, false));	// false: only direct rights, no memberships
			}
		}
		$acl_rights = $GLOBALS['egw']->hooks->process(array(
			'location' => 'acl_rights',
			'owner' => $content['account_id'],
		));
		if ($content['save'])
		{
			self::check_access($content['acl_account'], $content['acl_location']);

			if ($content['acl_location'] == 'run')
			{
				$this->save_run_rights($content);
			}
			else
			{
				$this->save_rights($content);
			}
			egw_framework::window_close();
		}
		if ($content['acl_location'] == 'run')
		{
			$readonlys['acl_account'] = true;
		}
		else
		{
			$content['acl'] = $content['label'] = array();
			foreach($state['filter'] == 'run' ? array(1 => 'run') : $acl_rights[$content['acl_appname']] as $right => $label)
			{
				$content['acl'][] = $rights & $right;
				$content['right'][] = $right;
				$content['label'][] = lang($label);
			}
			$sel_options['acl_appname'] = array();
			foreach(array_keys($state['filter'] == 'run' ? $GLOBALS['egw_info']['apps'] : $acl_rights) as $app)
			{
				$sel_options['acl_appname'][$app] = lang($app);
			}
			natcasesort($sel_options['acl_appname']);

			if (!empty($content['id']))
			{
				$readonlys['acl_appname'] = $readonlys['acl_account'] = $readonlys['acl_location'] = true;
			}
			else
			{
				$readonlys['acl_account'] = true;
			}
			// only user himself is allowed to grant private rights!
			if ($content['acl_account'] != $GLOBALS['egw_info']['user']['account_id'])
			{
				$readonlys['acl[5]'] = true;
				$content['preserve_rights'] = $rights & acl::PRIVAT;
			}
			else
			{
				unset($content['preserve_rights']);
			}
		}
		// view only, if no rights
		if (!self::check_access($content['acl_account'], $content['acl_location'], false))
		{
			$readonlys[__ALL__] = true;
			$readonlys['cancel'] = false;
		}

		//error_log(__METHOD__."() _GET[id]=".array2string($_GET['id'])." --> content=".array2string($content));
		$tpl->exec('admin.admin_acl.acl', $content, $sel_options, $readonlys, $content, 2);
	}

	/**
	 * Save run rights and refresh opener
	 *
	 * @param array $content
	 */
	protected function save_run_rights(array $content)
	{
		$old_apps = array_keys($this->acl->get_user_applications($content['acl_account'], false, false));
		// add new apps
		$added_apps = array_diff($content['apps'], $old_apps);
		foreach($added_apps as $app)
		{
			$this->acl->add_repository($app, 'run', $content['acl_account'], 1);
		}
		// remove no longer checked apps
		$removed_apps = array_diff($old_apps, $content['apps']);
		$deleted_ids = array();
		foreach($removed_apps as $app)
		{
			$this->acl->delete_repository($app, 'run', $content['acl_account']);
			$deleted_ids[] = $app.':'.$content['acl_account'].':run';
		}
		//error_log(__METHOD__."() apps=".array2string($content['apps']).", old_apps=".array2string($old_apps).", added_apps=".array2string($added_apps).", removed_apps=".array2string($removed_apps));

		if (!$added_apps && !$removed_apps)
		{
			// nothing changed --> nothing to do/notify
		}
		elseif (!$old_apps)
		{
			egw_framework::refresh_opener(lang('ACL added.'), 'admin', null, 'add');
		}
		elseif (!$added_apps)
		{
			egw_framework::refresh_opener(lang('ACL deleted.'), 'admin', $deleted_ids, 'delete');
		}
		else
		{
			egw_framework::refresh_opener(lang('ACL updated.'), 'admin', null, 'edit');
		}
	}

	/**
	 * Save rights and refresh opener
	 *
	 * @param array $content
	 */
	protected function save_rights(array $content)
	{
		// assamble rights again
		$rights = (int)$content['preserve_rights'];
		foreach($content['acl'] as $right)
		{
			$rights |= $right;
		}
		$id = !empty($content['id']) ? $content['id'] :
		$content['acl_appname'].':'.$content['acl_account'].':'.$content['acl_location'];
		//error_log(__METHOD__."() id=$id, acl=".array2string($content['acl'])." --> rights=$rights");

		if ($this->acl->get_specific_rights_for_account($content['acl_account'], $content['acl_location'], $content['acl_appname']) == $rights)
		{
			// nothing changed --> nothing to do
		}
		elseif (!$rights)	// all rights removed --> delete it
		{
			$this->acl->delete_repository($content['acl_appname'], $content['acl_location'], $content['acl_account']);
			egw_framework::refresh_opener(lang('ACL deleted.'), 'admin', $id, 'delete');
		}
		else
		{
			$this->acl->add_repository($content['acl_appname'], $content['acl_location'], $content['acl_account'], $rights);
			if ($content['id'])
			{
				egw_framework::refresh_opener(lang('ACL updated.'), 'admin', $id, 'edit');
			}
			else
			{
				egw_framework::refresh_opener(lang('ACL added.'), 'admin', $id, 'add');
			}
		}
	}

	/**
	 * Callback for nextmatch to fetch acl
	 *
	 * @param array $query
	 * @param array &$rows=null
	 * @return int total number of rows available
	 */
	public static function get_rows(array $query, array &$rows=null)
	{
		$so_sql = new so_sql('phpgwapi', acl::TABLE, null, '', true);

		// client queries destinct rows by their row-id
		if (isset($query['col_filter']['id']))
		{
			$query['col_filter'][] = $GLOBALS['egw']->db->concat('acl_appname',"':'",'acl_account',"':'",'acl_location').
				' IN ('.implode(',', array_map(array($GLOBALS['egw']->db, 'quote'), (array)$query['col_filter']['id'])).')';
			unset($query['col_filter']['id']);
		}
		else
		{
			$memberships = $GLOBALS['egw']->accounts->memberships($query['account_id'], true);
			$memberships[] = $query['account_id'];

			egw_cache::setSession(__CLASS__, 'state', array(
				'account_id' => $query['account_id'],
				'filter' => $query['filter'],
				'acl_appname' => $query['filter2'],
			));

			if ($GLOBALS['egw_info']['user']['preferences']['admin']['acl_filter'] != $query['filter'])
			{
				$GLOBALS['egw']->preferences->add('admin', 'acl_filter', $query['filter']);
				$GLOBALS['egw']->preferences->save_repository(false,'user',false);
			}
			switch($query['filter'])
			{
				case 'run':
					$query['col_filter']['acl_location'] = 'run';
					$query['col_filter']['acl_account'] = $memberships;
					break;
				default:
				case 'other':
					//$query['col_filter'][] = "acl_location!='run'";
					// remove everything not an account-id in location, like category based acl
					if ($GLOBALS['egw']->db->Type == 'mysql')
					{
						$query['col_filter'][] = "acl_location REGEXP '^-?[0-9]+$'";
					}
					else
					{
						$query['col_filter'][] = "acl_location SIMILAR TO '-?[0-9]+'";
					}
					// get apps not using group-acl (eg. Addressbook) or using it only partialy (eg. InfoLog)
					$not_enum_group_acls = $GLOBALS['egw']->hooks->process('not_enum_group_acls', array(), true);
					//error_log(__METHOD__."(filter=$query[filter]) not_enum_group_acl=".array2string($not_enum_group_acls));
					if ($not_enum_group_acls)
					{
						$sql = '(CASE acl_appname';
						foreach($not_enum_group_acls as $app => $groups)
						{
							if ($groups === true)
							{
								$check = $query['account_id'];
							}
							else
							{
								$check = array_diff($memberships, $groups);
								//error_log(__METHOD__."() app=$app, array_diff(memberships=".array2string($memberships).", groups=".array2string($groups).")=".array2string($check));
								if (!$check) continue;	// would give sql error otherwise!
							}
							$sql .= ' WHEN '.$GLOBALS['egw']->db->quote($app).' THEN '.$GLOBALS['egw']->db->expression(acl::TABLE, array(
								'acl_account' => $check,
							));
						}
						$sql .= ' ELSE ';
					}
					$sql .= $GLOBALS['egw']->db->expression(acl::TABLE, array(
						'acl_account' => $memberships,
					));
					if ($not_enum_group_acls) $sql .= ' END)';
					$query['col_filter'][] = $sql;
					break;

				case 'own':
					$query['col_filter']['acl_location'] = $memberships;
					break;
			}
			// do NOT list group-memberships and other non-ACL stuff here
			$query['col_filter']['acl_appname'] = $query['filter2'];
			if (empty($query['col_filter']['acl_appname']) && $query['filter'] != 'run')
			{
				//$query['col_filter'][] = "NOT acl_appname IN ('phpgw_group','sqlfs')";
				$query['col_filter']['acl_appname'] = array_keys($query['acl_rights']);
			}
		}
		$readonlys = array();
		$total = $so_sql->get_rows($query, $rows, $readonlys);

		foreach($rows as &$row)
		{
			// generate a row-id
			$row['id'] = $row['acl_appname'].':'.$row['acl_account'].':'.$row['acl_location'];

			if ($row['acl_location'] == 'run')
			{
				$row['acl1'] = lang('run');
			}
			else
			{
				if ($app !== $row['acl_appname']) translation::add_app($row['app_name']);
				foreach($query['acl_rights'][$row['acl_appname']] as $val => $label)
				{
					if ($row['acl_rights'] & $val)
					{
						$row['acl'.$val] = lang($label);
					}
				}
			}
			if (!self::check_access($row['acl_account'], $row['acl_location'], false))	// false: do NOT throw an exception!
			{
				$row['class'] = 'rowNoEdit';
			}
			//error_log(__METHOD__."() $n: ".array2string($row));
		}
		//error_log(__METHOD__."(".array2string($query).") returning ".$total);
		return $total;
	}

	/**
	 * Check if current user has access to ACL setting of a given location
	 *
	 * @param int $account_id numeric account-id
	 * @param int|string $location=null numeric account-id or "run"
	 * @param boolean $throw=true if true, throw an exception if no access, instead of just returning false
	 * @return boolean true if access is granted, false if notification_bo
	 * @throws egw_exception_no_permission
	 */
	public static function check_access($account_id, $location=null, $throw=true)
	{
		static $admin_access=null;
		static $own_access=null;
		if (is_null($admin_access))
		{
			$admin_access = isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
				!$GLOBALS['egw']->acl->check('account_access', 64, 'admin');	// ! because this denies access!
			$own_access = $admin_access || isset($GLOBALS['egw_info']['user']['apps']['preferences']);
		}
		if (!($location === 'run' || (int)$account_id) ||
			!((int)$account_id == (int)$GLOBALS['egw_info']['user']['account_id'] ? $own_access : $admin_access))
		{
			if ($throw) throw new egw_exception_no_permission(lang('Permission denied!!!'));
			return false;
		}
		return true;
	}

	/**
	 * Change (add, modify, delete) an ACL entry
	 *
	 * Checks access and throws an exception, if a change is attempted without proper access
	 *
	 * @param string|array $ids "$app:$account:$location" string used as row-id in list
	 * @param int $rights=null null to delete, or new rights
	 * @throws egw_exception_no_permission
	 */
	public static function ajax_change_acl($ids, $rights=null)
	{
		foreach((array)$ids as $id)
		{
			list($app, $account_id, $location) = explode(':', $id, 3);

			self::check_access($account_id, $location);	// throws exception, if no rights

			$acl = $GLOBALS['egw']->acl;

			if (!(int)$rights)	// this also handles taking away all rights as delete
			{
				$acl->delete_repository($app, $location, $account_id);
			}
			else
			{
				$acl->add_repository($app, $location, $account_id, $rights);
			}
		}
		if (!(int)$rights)
		{
			if (count(ids) > 1)
			{
				$msg = lang('%1 ACL entries deleted.', count($ids));
			}
			else
			{
				$msg = lang('ACL entry deleted.');
			}
		}
		else
		{
			$msg = lang('ACL updated');
		}
		egw_json_response::get()->data(array(
			'msg' => $msg,
			'ids' => $ids,
			'type' => !(int)$rights ? 'delete' : 'add',
		));
	}

	/**
	 * New index page
	 *
	 * @param array $content
	 */
	public function index(array $content=null)
	{
		$tpl = new etemplate_new('admin.acl');

		$content = array();
		$account_id = isset($_GET['account_id']) && (int)$_GET['account_id'] ?
			(int)$_GET['account_id'] : $GLOBALS['egw_info']['user']['account_id'];
		$content['nm'] = array(
			'get_rows' => 'admin_acl::get_rows',
			'no_cat' => true,
			'filter' => !empty($_GET['acl_filter']) ? $_GET['acl_filter'] :
				($GLOBALS['egw_info']['flags']['currentapp'] != 'admin' ? 'other' :
					$GLOBALS['egw_info']['user']['preferences']['admin']['acl_filter']),
			'filter2' => !empty($_GET['acl_app']) ? $_GET['acl_app'] : '',
			'lettersearch' => false,
			'header_row'   => 'admin.acl.add',
			'order' => 'acl_appname',
			'sort' => 'ASC',
			'row_id' => 'id',
			'account_id' => $account_id,
			'actions' => self::get_actions(),
			'acl_rights' => $GLOBALS['egw']->hooks->process(array(
				'location' => 'acl_rights',
				'owner' => $account_id,
			), array(), true),
		);
		$user = common::grab_owner_name($content['nm']['account_id']);
		$content['acl_apps'] = $GLOBALS['egw']->acl->get_app_list_for_id('run', acl::READ, $account_id);
		$sel_options = array(
			'filter' => array(
				'other' => lang('Access to %1 data by others', $user),
				'own'   => lang('%1 access to other data', $user),
				'run'   => lang('%1 run rights for applications', $user),
			),
			'filter2' => array(
				'' => lang('All applications'),
			)+etemplate_widget_menupopup::app_options('enabled'),
		);

		$tpl->exec('admin.admin_acl.index', $content, $sel_options);
	}

	/**
	 * Get actions for ACL
	 *
	 * @return array
	 */
	static function get_actions()
	{
		return array(
			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.admin.acl',
			),
			'add' => array(
				'caption' => 'Add',
				'onExecute' => 'javaScript:app.admin.acl',
			),
			'delete' => array(
				'confirm' => 'Delete this access control',
				'caption' => 'Delete',
				'disableClass' => 'rowNoEdit',
				'onExecute' => 'javaScript:app.admin.acl',
			),
		);
	}
}
