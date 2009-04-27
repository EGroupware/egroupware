<?php
/**
 *  Admin-, Preferences- and SideboxMenu-Hooks
 *
 * @link http://www.egroupware.org
 * @author Stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @package courseprotocol
 * @copyright (c) 2007 by Stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.admin_prefs_sidebox_hooks.inc.php
 */


class admin_prefs_sidebox_hooks
{
	var $public_functions = array(
//		'check_set_default_prefs' => true,
	);
	var $config = array();

	function admin_prefs_sidebox_hooks()
	{
		$config =& CreateObject('phpgwapi.config','admin');
		$config->read_repository();
		$this->config =& $config->config_data;
		unset($config);
	}

	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	function all_hooks($args)
	{
		$appname = 'admin';
		$location = is_array($args) ? $args['location'] : $args;


		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'admins')
		{

			if (! $GLOBALS['egw']->acl->check('site_config_access',1,'admin'))
			{
				$file['Site Configuration']         = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiconfig.index&appname=admin');
			}

		/* disabled it, til it does something useful
			if (! $GLOBALS['egw']->acl->check('peer_server_access',1,'admin'))
			{
				$file['Peer Servers']               = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiserver.list_servers');
			}
		*/
			if (! $GLOBALS['egw']->acl->check('account_access',1,'admin'))
			{
				$file['User Accounts']              = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_users');
			}

			if (! $GLOBALS['egw']->acl->check('group_access',1,'admin'))
			{
				$file['User Groups']                = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiaccounts.list_groups');
			}

			if (! $GLOBALS['egw']->acl->check('applications_access',1,'admin'))
			{
				$file['Applications']               = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiapplications.get_list');
			}

			if (! $GLOBALS['egw']->acl->check('global_categories_access',1,'admin'))
			{
				$file['Global Categories']          = $GLOBALS['egw']->link('/index.php','menuaction=admin.uicategories.index');
			}

			if (!$GLOBALS['egw']->acl->check('mainscreen_message_access',1,'admin') || !$GLOBALS['egw']->acl->check('mainscreen_message_access',2,'admin'))
			{
				$file['Change Main Screen Message'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uimainscreen.index');
			}

			if (! $GLOBALS['egw']->acl->check('current_sessions_access',1,'admin'))
			{
				$file['View Sessions'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
			}

			if (! $GLOBALS['egw']->acl->check('access_log_access',1,'admin'))
			{
				$file['View Access Log'] = egw::link('/index.php','menuaction=admin.admin_accesslog.index');
			}

			if (! $GLOBALS['egw']->acl->check('error_log_access',1,'admin'))
			{
				$file['View Error Log']  = $GLOBALS['egw']->link('/index.php','menuaction=admin.uilog.list_log');
			}

			if (! $GLOBALS['egw']->acl->check('applications_access',16,'admin'))
			{
				$file['Find and Register all Application Hooks'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiapplications.register_all_hooks');
			}

			if (! $GLOBALS['egw']->acl->check('asyncservice_access',1,'admin'))
			{
				$file['Asynchronous timed services'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.uiasyncservice.index');
			}

			if (! $GLOBALS['egw']->acl->check('db_backup_access',1,'admin'))
			{
				$file['DB backup and restore'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.admin_db_backup.index');
			}

			if (! $GLOBALS['egw']->acl->check('info_access',1,'admin'))
			{
				$file['phpInfo']         = "javascript:openwindow('" . $GLOBALS['egw']->link('/admin/phpinfo.php') . "')"; //$GLOBALS['egw']->link('/admin/phpinfo.php');
			}
			$file['Admin queue and history'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.admin_cmds.index');
			$file['Remote administration instances'] = $GLOBALS['egw']->link('/index.php','menuaction=admin.admin_cmds.remotes');

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
