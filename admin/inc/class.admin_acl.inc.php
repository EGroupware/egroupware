<?php
/**
 * EGroupware: Admin ACL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2013-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

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
	);

	/**
	 * Reference to global Acl class (instanciated for current user)
	 *
	 * @var Acl
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
			Framework::refresh_opener(lang('ACL added.'), 'admin', null, 'add');
		}
		elseif (!$added_apps)
		{
			Framework::refresh_opener(lang('ACL deleted.'), 'admin', $deleted_ids, 'delete');
		}
		else
		{
			Framework::refresh_opener(lang('ACL updated.'), 'admin', null, 'edit');
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
			Framework::refresh_opener(lang('ACL deleted.'), 'admin', $id, 'delete');
		}
		else
		{
			$this->acl->add_repository($content['acl_appname'], $content['acl_location'], $content['acl_account'], $rights);
			if ($content['id'])
			{
				Framework::refresh_opener(lang('ACL updated.'), 'admin', $id, 'edit');
			}
			else
			{
				Framework::refresh_opener(lang('ACL added.'), 'admin', $id, 'add');
			}
		}
	}

	/**
	 * Callback for nextmatch to fetch Acl
	 *
	 * @param array $query
	 * @param array &$rows=null
	 * @return int total number of rows available
	 */
	public static function get_rows(array $query, array &$rows=null)
	{
		$so_sql = new Api\Storage\Base('phpgwapi', Acl::TABLE, null, '', true);

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

			Api\Cache::setSession(__CLASS__, 'state', array(
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
					if (substr($GLOBALS['egw']->db->Type, 0, 5) == 'mysql')
					{
						$query['col_filter'][] = "acl_location REGEXP '^-?[0-9]+$'";
					}
					else
					{
						$query['col_filter'][] = "acl_location SIMILAR TO '-?[0-9]+'";
					}
					// get apps not using group-acl (eg. Addressbook) or using it only partialy (eg. InfoLog)
					$not_enum_group_acls = Api\Hooks::process('not_enum_group_acls', array(), true);
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
								$check = array_diff($memberships, (array)$groups);
								//error_log(__METHOD__."() app=$app, array_diff(memberships=".array2string($memberships).", groups=".array2string($groups).")=".array2string($check));
								if (!$check) continue;	// would give sql error otherwise!
							}
							$sql .= ' WHEN '.$GLOBALS['egw']->db->quote($app).' THEN '.$GLOBALS['egw']->db->expression(Acl::TABLE, array(
								'acl_account' => $check,
							));
						}
						$sql .= ' ELSE ';
					}
					$sql .= $GLOBALS['egw']->db->expression(Acl::TABLE, array(
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
				if ($app !== $row['acl_appname']) Api\Translation::add_app($row['app_name']);
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

		// Get supporting or all apps for filter2 depending on filter
		if($query['filter'] == 'run')
		{
			$rows['sel_options']['acl_appname'] = $rows['sel_options']['filter2'] = array(
				'' => lang('All applications'),
			)+Etemplate\Widget\Select::app_options('enabled');
		}
		else
		{
			$rows['sel_options']['filter2'] = array(
				array('value' => '', 'label' => lang('All applications'))
			);
			$apps = Api\Hooks::process(array(
				'location' => 'acl_rights',
				'owner' => $query['account_id'],
			), array(), true);
			foreach(array_keys($apps) as $appname)
			{
				$rows['sel_options']['filter2'][] = array(
					'value' => $appname,
					'label' => lang(Api\Link::get_registry($appname, 'entries') ?: $appname)
				);
			}
			usort($rows['sel_options']['filter2'], function($a,$b) {
				return strcasecmp($a['label'], $b['label']);
			});
			$rows['sel_options']['acl_appname'] = $rows['sel_options']['filter2'];
		}

		return $total;
	}

	/**
	 * Check if current user has access to ACL setting of a given location
	 *
	 * @param int $account_id numeric account-id
	 * @param int|string $location =null numeric account-id or "run"
	 * @param boolean $throw =true if true, throw an exception if no access, instead of just returning false
	 * @return boolean true if access is granted, false if notification_bo
	 * @throws Api\Exception\NoPermission
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
		if (!(int)$account_id || !((int)$account_id == (int)$GLOBALS['egw_info']['user']['account_id'] && $location !== 'run' ?
				$own_access : $admin_access))
		{
			if ($throw) throw new Api\Exception\NoPermission(lang('Permission denied!!!'));
			return false;
		}
		return true;
	}

	/**
	 * Get the list of applications allowed for the given user
	 *
	 * The list of applications is added to the json response
	 *
	 * @param int $account_id
	 */
	public static function ajax_get_app_list($account_id)
	{
		$list = array();
		if(self::check_access((int)$account_id,'run',false))
		{
			$list = array_keys($GLOBALS['egw']->acl->get_user_applications((int)$account_id,false,false));
		}
		Api\Json\Response::get()->data($list);
	}

	/**
	 * Change (add, modify, delete) an ACL entry
	 *
	 * Checks access and throws an exception, if a change is attempted without proper access
	 *
	 * @param string|array $ids "$app:$account:$location" string used as row-id in list
	 * @param int $rights null to delete, or new rights
	 * @param array $values Additional values from UI
	 * @param string $etemplate_exec_id to check against CSRF
	 * @throws Api\Exception\NoPermission
	 */
	public static function ajax_change_acl($ids, $rights, $values, $etemplate_exec_id)
	{
		Api\Etemplate\Request::csrfCheck($etemplate_exec_id, __METHOD__, func_get_args());

		try {
			foreach((array)$ids as $id)
			{
				list($app, $account_id, $location) = explode(':', $id, 3);

				self::check_access($account_id, $location);	// throws exception, if no rights

				$acl = $GLOBALS['egw']->acl;

				if($location == 'run')
				{
					$right_list = array(1 => 'run');
				}
				else
				{
					$right_list = Api\Hooks::single(array(
						'location' => 'acl_rights',
						'owner' => $location,
					), $app);
				}
				$current = (int)$acl->get_specific_rights_for_account($account_id,$location,$app);
				foreach(array_keys((array)$right_list) as $right)
				{
					$have_it = !!($current & $right);
					$set_it = !!($rights & $right);
					if($have_it == $set_it) continue;
					$data = array(
						'allow' => $set_it,
						'account' => $account_id,
						'app' => $app,
						'location' => $location,
						'rights' => (int)$right
						// This is the documentation from policy app
					)+(array)$values['admin_cmd'];
					if($location == 'run')
					{
						$cmd = new admin_cmd_account_app($set_it,$account_id, $app, (array)$values['admin_cmd']);
					}
					else
					{
						$cmd = new admin_cmd_acl($data);
					}
					$cmd->run();
				}
			}
			if (!(int)$rights)
			{
				if (count((array)$ids) > 1)
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
			Api\Json\Response::get()->data(array(
				'msg' => $msg,
				'ids' => $ids,
				'type' => !(int)$rights ? 'delete' : 'add',
			));
		}
		catch (Exception $e) {
			Api\Json\Response::get()->call('egw.message', $e->getMessage(), 'error');
		}
	}

	/**
	 * New index page
	 *
	 * @param array $_content =null
	 */
	public function index(array $_content=null)
	{
		unset($_content);	// not used, required by function signature

		$tpl = new Etemplate('admin.acl');

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
			'filter2_onchange' => 'app.admin.acl_app_change',
			'lettersearch' => false,
			'order' => 'acl_appname',
			'sort' => 'ASC',
			'row_id' => 'id',
			'account_id' => $account_id,
			'actions' => self::get_actions(),
			'acl_rights' => Api\Hooks::process(array(
				'location' => 'acl_rights',
				'owner' => $account_id,
			), array(), true),
		);
		$user = Api\Accounts::username($content['nm']['account_id']);
		$sel_options = array(
			'filter' => array(
				'other' => lang('Access to %1 data by others', $user),
				'own'   => lang('%1 access to other data', $user),
				'run'   => lang('%1 run rights for applications', $user),
			)
		);

		// Set this so if loaded via preferences, js is still properly
		// loaded into global app.admin
		$GLOBALS['egw_info']['flags']['currentapp'] = 'admin';

		$tpl->exec('admin.admin_acl.index', $content, $sel_options, array(), array(), 2);
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
				'disableClass' => 'rowNoEdit',
				'onExecute' => 'javaScript:app.admin.acl',
			),
			'add' => array(
				'caption' => 'Add',
				'disableClass' => 'rowNoEdit',
				'onExecute' => 'javaScript:app.admin.acl',
			),
			'delete' => array(
				'caption' => 'Delete',
				'disableClass' => 'rowNoEdit',
				'onExecute' => 'javaScript:app.admin.acl',
			),
		);
	}
}
