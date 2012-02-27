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
class admin_prefs_sidebox_hooks
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var unknown_type
	 */
	var $public_functions = array(
		'register_all_hooks' => True,
		'fsck' => true,
	);

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
				$file['View Sessions'] = egw::link('/index.php','menuaction=admin.uicurrentsessions.list_sessions');
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
				$file['Find and Register all Application Hooks'] = egw::link('/index.php','menuaction=admin.admin_prefs_sidebox_hooks.register_all_hooks');
			}

			//if (! $GLOBALS['egw']->acl->check('applications_access',16,'admin'))
			{
				$file['Check virtual filesystem'] = egw::link('/index.php','menuaction=admin.admin_prefs_sidebox_hooks.fsck');
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
	 * Register all hooks
	 */
	function register_all_hooks()
	{
		if ($GLOBALS['egw']->acl->check('applications_access',16,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		$GLOBALS['egw']->hooks->register_all_hooks();

		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();	// in case with cache the egw_info array in the session
		}
		$GLOBALS['egw']->redirect_link('/admin/index.php');
	}

	/**
	 * Run fsck on sqlfs
	 */
	function fsck()
	{
		$check_only = !isset($_POST['fix']);

		if (!($msgs = sqlfs_utils::fsck($check_only)))
		{
			$msgs = lang('Filesystem check reported no problems.');
		}
		$content = '<p>'.implode("</p>\n<p>", (array)$msgs)."</p>\n";

		$content .= html::form('<p>'.($check_only&&is_array($msgs)?html::submit_button('fix', lang('Fix reported problems')):'').
			html::submit_button('cancel', lang('Cancel'), "window.location.href='".egw::link('/admin/index.php')."'; return false;").'</p>',
			'',egw::link('/index.php',array('menuaction'=>'admin.admin_prefs_sidebox_hooks.fsck')));

		$GLOBALS['egw']->framework->render($content, lang('Admin').' - '.lang('Check virtual filesystem'), true);
	}
}
