<?php
/**
 *  Admin-, Preferences- and SideboxMenu-Hooks
 *
 * @link http://www.egroupware.org
 * @author Stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Static hooks for admin application
 *
 */
class admin_prefs_sidebox_hooks
{
	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		if (!isset($_GET['menuaction']) && substr($_SERVER['PHP_SELF'],-16) == '/admin/index.php')
		{
			admin_statistics::check();
		}
		$appname = 'admin';
		$location = is_array($args) ? $args['location'] : $args;
				

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'admins')
		{
			
			if (! $GLOBALS['egw']->acl->check('site_config_access',1,'admin'))
			{
				$file['Site Configuration']         = egw::link('/index.php','menuaction=admin.uiconfig.index&appname=admin');
			}

			/* disabled it, til it does something useful
			if (! $GLOBALS['egw']->acl->check('peer_server_access',1,'admin'))
			{
				$file['Peer Servers']               = egw::link('/index.php','menuaction=admin.uiserver.list_servers');
			}
			*/
			if (! $GLOBALS['egw']->acl->check('account_access',1,'admin'))
			{
				$file['User Accounts']              = egw::link('/index.php','menuaction=admin.uiaccounts.list_users');
			}

			if (! $GLOBALS['egw']->acl->check('group_access',1,'admin'))
			{
				$file['User Groups']                = egw::link('/index.php','menuaction=admin.uiaccounts.list_groups');
			}

			if (! $GLOBALS['egw']->acl->check('applications_access',1,'admin'))
			{
				$file['Applications']               = egw::link('/index.php','menuaction=admin.uiapplications.get_list');
			}

			if (! $GLOBALS['egw']->acl->check('global_categories_access',1,'admin'))
			{
				$file['Global Categories']          = egw::link('/index.php','menuaction=admin.uicategories.index');
			}

			if (!$GLOBALS['egw']->acl->check('mainscreen_message_access',1,'admin') || !$GLOBALS['egw']->acl->check('mainscreen_message_access',2,'admin'))
			{
				$file['Change Main Screen Message'] = egw::link('/index.php','menuaction=admin.uimainscreen.index');
			}

			if (! $GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))
			{
				$file['View Sessions'] = egw::link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
			}
			
			if (! $GLOBALS['egw']->acl->check('access_log_access',1,'admin'))
			{
				$file['View Access Log'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccess_history.list_history');
			}

			if (! $GLOBALS['egw']->acl->check('error_log_access',1,'admin'))
			{
				$file['View Error Log']  = egw::link('/index.php','menuaction=admin.uilog.list_log');
			}

			if (! $GLOBALS['egw']->acl->check('applications_access',16,'admin'))
			{
				$file['Find and Register all Application Hooks'] = egw::link('/index.php','menuaction=admin.uiapplications.register_all_hooks');
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
				$file['phpInfo']         = "javascript:openwindow('" . egw::link('/admin/phpinfo.php') . "')"; //egw::link('/admin/phpinfo.php');
			}
			$file['Admin queue and history'] = egw::link('/index.php','menuaction=admin.admin_cmds.index');
			$file['Remote administration instances'] = egw::link('/index.php','menuaction=admin.admin_cmds.remotes');

			$file['Submit statistic information'] = egw::link('/index.php','menuaction=admin.admin_statistics.submit');

			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}
	
	/**
	 * populates $GLOBALS['settings'] for the preferences
	 */
	function settings()
	{
		$this->check_set_default_prefs();

		return true;	// otherwise prefs say it cant find the file ;-)
	}
	
	/**
	 * Check if reasonable default preferences are set and set them if not
	 *
	 * It sets a flag in the app-session-data to be called only once per session
	 */
	function check_set_default_prefs()
	{
		if ($GLOBALS['egw']->session->appsession('default_prefs_set','admin'))
		{
			return;
		}
		$GLOBALS['egw']->session->appsession('default_prefs_set','admin','set');

		$default_prefs =& $GLOBALS['egw']->preferences->default['admin'];

		$defaults = array(
		);
		foreach($defaults as $var => $default)
		{
			if (!isset($default_prefs[$var]) || $default_prefs[$var] === '')
			{
				$GLOBALS['egw']->preferences->add('admin',$var,$default,'default');
				$need_save = True;
			}
		}
		if ($need_save)
		{
			$GLOBALS['egw']->preferences->save_repository(False,'default');
		}
	}
}
