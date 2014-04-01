<?php
/**
 *  EGroupware Admin: Hooks
 *
 * @link http://www.egroupware.org
 * @author Stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Static hooks for admin application
 */
class admin_hooks
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var unknown_type
	 */
	var $public_functions = array(
		'register_all_hooks' => True,
	);

	/**
	 * hooks to build projectmanager's sidebox-menu
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);

		if (!isset($_GET['menuaction']) && substr($_SERVER['PHP_SELF'],-16) == '/admin/index.php')
		{
			admin_statistics::check();
		}
		$appname = 'admin';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			// Destination div for folder tree
			$file[] = array(
				'no_lang' => true,
				'text' => '<span id="admin_tree_target" class="admin_tree" />',
				'link' => false,
				'icon' => false
			);
			display_sidebox($appname,lang('Admin'),$file);
			return;
		}
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{

			if (! $GLOBALS['egw']->acl->check('site_config_access',1,'admin'))
			{
				$file['Site Configuration']         = egw::link('/index.php','menuaction=admin.uiconfig.index&appname=admin');
			}

			if (! $GLOBALS['egw']->acl->check('account_access',1,'admin'))
			{
				$file['User Accounts']              = array(
					'id' => '/accounts',
					'icon' => common::image('addressbook', 'accounts'),
				);
			}

			if (! $GLOBALS['egw']->acl->check('account_access',16,'admin'))
			{
				$file['Bulk password reset']        = egw::link('/index.php','menuaction=admin.admin_passwordreset.index');
			}

			if (! $GLOBALS['egw']->acl->check('group_access',1,'admin'))
			{
				$file['User Groups']                = array(
					'id' => '/groups',
					'icon' => common::image('addressbook', 'group'),
					'child' => 1,
				);
			}

			if (! $GLOBALS['egw']->acl->check('applications_access',1,'admin'))
			{
				$file['Applications']               = egw::link('/index.php','menuaction=admin.admin_applications.index');
			}
			if (! $GLOBALS['egw']->acl->check('global_categories_access',1,'admin'))
			{
				$file['Global Categories']          = egw::link('/index.php','menuaction=admin.admin_categories.index&appname=phpgw');
			}

			if (!$GLOBALS['egw']->acl->check('mainscreen_message_access',1,'admin') || !$GLOBALS['egw']->acl->check('mainscreen_message_access',2,'admin'))
			{
				$file['Change Main Screen Message'] = egw::link('/index.php','menuaction=admin.uimainscreen.index');
			}

			if (! $GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))
			{
				$file['View Sessions'] = egw::link('/index.php','menuaction=admin.admin_accesslog.sessions');
			}

			if (! $GLOBALS['egw']->acl->check('access_log_access',1,'admin'))
			{
				$file['View Access Log'] = egw::link('/index.php','menuaction=admin.admin_accesslog.index');
			}

			if (! $GLOBALS['egw']->acl->check('error_log_access',1,'admin'))
			{
				$file['View Error Log']  = egw::link('/index.php','menuaction=admin.uilog.list_log');
			}

			if (! $GLOBALS['egw']->acl->check('applications_access',16,'admin'))
			{
				$file['Clear cache and register hooks'] = egw::link('/index.php','menuaction=admin.admin_hooks.register_all_hooks');
			}

			if (! $GLOBALS['egw']->acl->check('asyncservice_access',1,'admin'))
			{
				$file['Asynchronous timed services'] = egw::link('/index.php','menuaction=admin.uiasyncservice.index');
			}

			if (! $GLOBALS['egw']->acl->check('db_backup_access',1,'admin'))
			{
				$file['DB backup and restore'] = egw::link('/index.php','menuaction=admin.admin_db_backup.index');
			}

			if (! $GLOBALS['egw']->acl->check('info_access',1,'admin'))
			{
				$file['phpInfo']         = "javascript:egw_openWindowCentered2('" . egw::link('/admin/phpinfo.php','',false) . "','phpinfoWindow',700,600,'yes')";
			}
			$file['Admin queue and history'] = egw::link('/index.php','menuaction=admin.admin_cmds.index');
			$file['Remote administration instances'] = egw::link('/index.php','menuaction=admin.admin_cmds.remotes');
			$file['Custom translation'] = egw::link('/index.php','menuaction=admin.admin_customtranslation.index');

			$file['Submit statistic information'] = egw::link('/index.php','menuaction=admin.admin_statistics.submit');

			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				foreach($file as &$url)
				{
					if (is_array($url) && $url['link']) $url = $url['link'];
				}
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * Register all hooks
	 */
	function register_all_hooks()
	{
		if ($GLOBALS['egw']->acl->check('applications_access',16,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		egw_cache::flush(egw_cache::INSTANCE);

		$GLOBALS['egw']->hooks->register_all_hooks();

		common::delete_image_map();

		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();	// in case with cache the egw_info array in the session
		}
		// allow apps to hook into "Admin >> Clear cache and register hooks"
		$GLOBALS['egw']->hooks->process('clear_cache', array(), true);

		$GLOBALS['egw']->redirect_link('/admin/index.php');
	}

	/**
	 * Actions for context menu of users
	 *
	 * @return array of actions
	 */
	public static function edit_user()
	{
		$actions = array();

		if (!$GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))	// no rights to view
		{
			$actions[] = array(
				'description' => 'Login History',
				'url'         => '/index.php',
				'extradata'   => 'menuaction=admin.admin_accesslog.index'
			);
		}

		if (!$GLOBALS['egw']->acl->check('account_access',64,'admin'))	// no rights to set ACL-rights
		{
			$actions[] = array(
				'description' => 'Deny access',
				'url'         => '/index.php',
				'extradata'   => 'menuaction=admin.uiaclmanager.list_apps'
			);
		}
		return $actions;
	}
}
