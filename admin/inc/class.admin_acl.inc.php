<?php
/**
 * EGroupware: Admin app ACL
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @package admin
 * @copyright (c) 2013 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

require_once EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php';

/**
 * UI for admin
 *
 * @todo acl needs to use etemplate_old, as auto-repeat does not work for acl & label
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
	 * Edit or add an ACL entry
	 *
	 * @param array $content
	 * @param string $msg
	 */
	public function acl(array $content=null, $msg='')
	{
		$state = (array)egw_cache::getSession(__CLASS__, 'state');
		$tpl = new etemplate_old('admin.acl.edit');	// auto-repeat of acl & label not working with etemplate_new!

		if (!is_array($content))
		{
			if (isset($_GET['id']))
			{
				list($app, $account, $location) = explode(':', $_GET['id'], 3);
				$acl = (int)$account == (int)$GLOBALS['egw_info']['user']['account_id'] ?
					$GLOBALS['egw']->acl : new acl($account);

				if (!($rights = $acl->get_rights($location, $app)))
				{
					egw_framework::window_close(lang('ACL entry not found!'));
				}
			}
			else
			{
				$app = !empty($_GET['app']) && isset($GLOBALS['egw_info']['apps'][$_GET['app']]) ?
					$_GET['app'] : $state['acl_appname'];
				$location = $state['filter'] == 'run' ? 'run' : $state['account_id'];
				$account = $state['filter'] == 'run' ? $state['account_id'] : $state['acl_account'];
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
				if (!isset($acl))
				{
					$acl = (int)$account == (int)$GLOBALS['egw_info']['user']['account_id'] ?
						$GLOBALS['egw']->acl : new acl($account);
				}
				$content['apps'] = array_keys($acl->get_user_applications($account, false, false));	// false: only direct rights, no memberships
			}
		}
		$acl_rights = $GLOBALS['egw']->hooks->process(array(
			'location' => 'acl_rights',
			'owner' => $content['account_id'],
		));
		if ($content['save'])
		{
			self::check_access($content['acl_location']);

			if ($content['acl_location'] == 'run')
			{
				self::save_run_rights($content);
			}
			else
			{
				self::save_rights($content);
			}
			egw_framework::window_close();
		}
		if ($content['location'] == 'run')
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
			foreach($state['filter'] == 'run' ? $GLOBALS['egw_info']['apps'] : $acl_rights as $app => $data)
			{
				$sel_options['acl_appname'][$app] = lang($app);
			}
			natcasesort($sel_options['acl_appname']);

			if (!empty($content['id']))
			{
				$readonlys['acl_appname'] = $readonlys['acl_account'] = $readonlys['acl_location'] = true;
			}
			// only user himself is allowed to grant private rights!
			if ($content['acl_location'] != $GLOBALS['egw_info']['user']['account_id'])
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
		if (!self::check_access($content['acl_location'], false))
		{
			$readonlys[__ALL__] = true;
			$readonlys['cancel'] = false;
		}

		//error_log(__METHOD__."() _GET[id]=".array2string($_GET['id'])." --> content=".array2string($content));
		$tpl->exec('admin.admin_acl.acl', $content, $sel_options, $readonlys, $content);
	}

	/**
	 * Save run rights and refresh opener
	 *
	 * @param array $content
	 */
	private static function save_run_rights(array $content)
	{
		$acl = new acl($content['acl_account']);
		$old_apps = array_keys($acl->get_user_applications($content['acl_account'], false, false));
		$ids = array();
		// add new apps
		$added_apps = array_diff($content['apps'], $old_apps);
		foreach($added_apps as $app)
		{
			$acl->add_repository($app, 'run', $content['acl_account'], 1);
		}
		// remove no longer checked apps
		$removed_apps = array_diff($old_apps, $content['apps']);
		$deleted_ids = array();
		foreach($removed_apps as $app)
		{
			$acl->delete_repository($app, 'run', $content['acl_account']);
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
	private static function save_rights(array $content)
	{
		$acl = new acl($content['acl_account']);
		// assamble rights again
		$rights = (int)$content['preserve_rights'];
		foreach($content['acl'] as $right)
		{
			$rights |= $right;
		}
		$id = !empty($content['id']) ? $content['id'] :
		$content['acl_appname'].':'.$content['acl_account'].':'.$content['acl_location'];
		//error_log(__METHOD__."() id=$id, acl=".array2string($content['acl'])." --> rights=$rights");

		if ($acl->get_specific_rights($content['acl_location'], $content['acl_appname']) == $rights)
		{
			// nothing changed --> nothing to do
		}
		elseif (!$rights)	// all rights removed --> delete it
		{
			$acl->delete_repository($content['acl_appname'], $content['acl_location'], $content['acl_account']);
			egw_framework::refresh_opener(lang('ACL deleted.'), 'admin', $id, 'delete');
		}
		else
		{
			$acl->add_repository($content['acl_appname'], $content['acl_location'], $content['acl_account'], $rights);
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
				'acl_appname' => $query['col_filter']['acl_appname'],
				'acl_location' => $query['col_filter']['acl_location'],
				'acl_account' => $query['col_filter']['acl_account'],
			));

			if ($GLOBALS['egw_info']['user']['preferences']['admin']['acl_filter'] != $query['filter'])
			{
				$GLOBALS['egw']->preferences->add('admin', 'acl_filter', $query['filter']);
				$GLOBALS['egw']->preferences->save_repository(false,'user',false);
			}
			switch($query['filter'])
			{
				default:
				case 'run':
					$query['col_filter']['acl_location'] = 'run';
					if (empty($query['col_filter']['acl_account']))
					{
						$query['col_filter']['acl_account'] = $memberships;
					}
					break;
				case 'own':
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
					if (empty($query['col_filter']['acl_account']))
					{
						$query['col_filter']['acl_account'] = $memberships;
					}
					break;

				case 'other':
					if (empty($query['col_filter']['acl_location']))
					{
						$query['col_filter']['acl_location'] = $memberships;//$query['account_id'];
					}
					break;
			}
			// do NOT list group-memberships and other non-ACL stuff here
			if (empty($query['col_filter']['acl_appname']) && $query['filter'] != 'run')
			{
				//$query['col_filter'][] = "NOT acl_appname IN ('phpgw_group','sqlfs')";
				$query['col_filter']['acl_appname'] = array_keys($query['acl_rights']);
			}
		}
		$total = $so_sql->get_rows($query, $rows, $readonlys);

		foreach($rows as $n => &$row)
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
			if (!self::check_access($row['acl_location'], false))	// false: do NOT throw an exception!
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
	 * @param int|string $location numeric account-id or "run"
	 * @param boolean $throw=true if true, throw an exception if no access, instead of just returning false
	 * @return boolean true if access is granted, false if notification_bo
	 * @throws egw_exception_no_permission
	 */
	public static function check_access($location, $throw=true)
	{
		static $admin_access;
		static $own_access;
		if (is_null($admin_access))
		{
			$admin_access = isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
				!$GLOBALS['egw']->acl->check('account_access', 64, 'admin');	// ! because this denies access!
			$own_access = $admin_access || isset($GLOBALS['egw_info']['user']['apps']['preferences']);
		}
		if (!($location === 'run' || (int)$location) ||
			!((int)$location == (int)$GLOBALS['egw_info']['user']['account_id'] ? $own_access : $admin_access))
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

			self::check_access($location);	// throws exception, if no rights

			if ((int)$account_id == (int)$GLOBALS['egw_info']['user']['account_id'])
			{
				$acl = $GLOBALS['egw']->acl;
			}
			else
			{
				$acl = new acl($account_id);
			}

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
	 * @param string $msg
	 */
	public function index(array $content=null, $msg='')
	{
		$tpl = new etemplate_new('admin.acl');

		$content = array();
		$content['nm'] = array(
			'get_rows' => 'admin_acl::get_rows',
			'no_cat' => true,
			'filter' => $GLOBALS['egw_info']['user']['preferences']['admin']['acl_filter'],
			'no_filter2' => true,
			'lettersearch' => false,
			//'order' => 'account_lid',
			'sort' => 'ASC',
			'row_id' => 'id',
			//'default_cols' => '!account_id,account_created',
			'actions' => self::get_actions(),
			'acl_rights' => $GLOBALS['egw']->hooks->process(array(
				'location' => 'acl_rights',
				'owner' => $query['account_id'],
			), array(), true),
		);
		if (isset($_GET['account_id']) && (int)$_GET['account_id'])
		{
			$content['nm']['account_id'] = (int)$_GET['account_id'];
			$content['nm']['order'] = 'acl_appname';
		}
		$sel_options = array(
			'filter' => array(
				'other' => 'Rights granted to others',
				'own'   => 'Own rights granted from others',
				'run'   => 'Run rights for applications',
			),
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
				'caption' => 'Edit ACL',
				'default' => true,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.admin.acl',
			),
			'add' => array(
				'caption' => 'Add ACL',
				'onExecute' => 'javaScript:app.admin.acl',
			),
			'delete' => array(
				'confirm' => 'Delete this ACL',
				'caption' => 'Delete ACL',
				'disableClass' => 'rowNoEdit',
				'onExecute' => 'javaScript:app.admin.acl',
			),
		);
	}
}
