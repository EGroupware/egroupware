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

use EGroupware\Api;
use EGroupware\Api\Egw;

/**
 * Static hooks for admin application
 */
class admin_hooks
{
	/**
	 * Functions callable via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array(
		'ajax_clear_cache' => True,
	);

	/**
	 * hooks to build projectmanager's sidebox-menu
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);

		$appname = 'admin';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			// Destination div for folder tree
			$file[] = array(
				'no_lang' => true,
				// Tree has about 20 leaves (or more) in it, but the sidebox starts
				// with no content.  Set some minimum height to make sure scrolling is triggered.
				'text' => '<div id="admin_tree_target" class="admin_tree" style="min-height:20em"/>',
				'link' => false,
				'icon' => false
			);
			display_sidebox($appname,lang('Admin'),$file);
			return;
		}
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{

			if (! $GLOBALS['egw']->acl->check('site_config_acce',1,'admin'))
			{
				$file['Site Configuration']         = Egw::link('/index.php','menuaction=admin.admin_config.index&appname=admin&ajax=true');
			}

			if (! $GLOBALS['egw']->acl->check('account_access',1,'admin'))
			{
				$file['User Accounts']              = array(
					'id' => '/accounts',
					'icon' => Api\Image::find('addressbook', 'accounts'),
				);
			}

			if (! $GLOBALS['egw']->acl->check('account_access',16,'admin'))
			{
				$file['Bulk password reset']        = Egw::link('/index.php','menuaction=admin.admin_passwordreset.index&ajax=true');
			}

			if (! $GLOBALS['egw']->acl->check('group_access',1,'admin'))
			{
				$file['User Groups']                = array(
					'id' => '/groups',
					'icon' => Api\Image::find('addressbook', 'group'),
					'child' => 1,
				);
			}

			if (! $GLOBALS['egw']->acl->check('global_categorie',1,'admin'))
			{
				$file['Global Categories']          = Egw::link('/index.php','menuaction=admin.admin_categories.index&appname=phpgw&ajax=true');
			}

			if (!$GLOBALS['egw']->acl->check('mainscreen_messa',1,'admin') || !$GLOBALS['egw']->acl->check('mainscreen_messa',2,'admin'))
			{
				$file['Change Login Screen Message'] = Egw::link('/index.php','menuaction=admin.admin_messages.index');
			}

			if (! $GLOBALS['egw']->acl->check('current_sessions',1,'admin'))
			{
				$file['View Sessions'] = Egw::link('/index.php','menuaction=admin.admin_accesslog.sessions&ajax=true');
			}

			if (! $GLOBALS['egw']->acl->check('access_log_acces',1,'admin'))
			{
				$file['View Access Log'] = Egw::link('/index.php','menuaction=admin.admin_accesslog.index&ajax=true');
			}

			if (! $GLOBALS['egw']->acl->check('applications_acc',16,'admin'))
			{
				$file['Clear cache and register hooks'] = array(
					'id' => 'admin/clear_cache',
					'no_lang' => true,
					'link' => "javascript:app.admin.clear_cache();",
				 );
			}

			if (! $GLOBALS['egw']->acl->check('asyncservice_acc',1,'admin'))
			{
				$file['Asynchronous timed services'] = Egw::link('/index.php','menuaction=admin.admin_asyncservice.index');
			}

			if (! $GLOBALS['egw']->acl->check('db_backup_access',1,'admin'))
			{
				$file['DB backup and restore'] = Egw::link('/index.php','menuaction=admin.admin_db_backup.index');
			}

			if (! $GLOBALS['egw']->acl->check('info_access',1,'admin'))
			{
				$file['phpInfo']         = "javascript:egw.openPopup('" . Egw::link('/admin/phpinfo.php','',false) . "',960,600,'phpinfoWindow')";
			}
			if (file_exists(EGW_SERVER_ROOT.'/swoolepush/test.php'))
			{
				$file['Test Push']       = Egw::link('/swoolepush/test.php');
			}
			$file['Admin queue and history'] = Egw::link('/index.php','menuaction=admin.admin_cmds.index&ajax=true');
			$file['Remote administration instances'] = Egw::link('/index.php','menuaction=admin.admin_cmds.remotes&ajax=true');
			$file['Custom translation'] = Egw::link('/index.php','menuaction=admin.admin_customtranslation.index');
			$file['Changelog and versions'] = Egw::link('/index.php','menuaction=api.EGroupware\\Api\\Framework\\About.index&ajax=true');

			// disable usage statistic for now, as no more backend
			//$file['Submit statistic information'] = Egw::link('/index.php','menuaction=admin.admin_statistics.submit');

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
	 * Clears instance cache (and thereby also refreshes hooks)
	 */
	function ajax_clear_cache()
	{
		if ($GLOBALS['egw']->acl->check('applications_acc',16,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		Api\Cache::flush(Api\Cache::INSTANCE, !empty($_GET['errored']) ? "all" : null);

		Api\Image::invalidate();

		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// Egw object in setup is limited
		{
			$GLOBALS['egw']->invalidate_session_cache();	// in case with cache the egw_info array in the session
		}
		// allow apps to hook into "Admin >> Clear cache and register hooks"
		Api\Hooks::process('clear_cache', array(), true);
	}

	/**
	 * Actions for context menu of users
	 *
	 * @return array of actions
	 */
	public static function edit_user()
	{
		$actions = array();

		$actions[] = array(
			'id' => 'acl',
			'caption' => 'Access control',
			'url' => 'menuaction=admin.admin_acl.index&account_id=$id',
			'popup' => '900x450',
			'icon' => 'lock',
		);

		// Clear Credentials
		$actions[] = array(
			'id' => 'clear_credentials',
			'caption' => 'Clear credentials',
			'icon' => 'password',
			'onExecute' => 'javaScript:app.admin.clear_credentials_handler',
			'confirm' => 'Clear credentials',
			'children' => array (
				'clear_2fa' => array (
					'caption'		  => 'Clear security tokens',
					'icon'			  => 'password',
					'allowOnMultiple' => true
				),
				'clear_mail' => array (
					'caption'		  => 'Clear mail credentials',
					'icon'			  => 'mail',
					'allowOnMultiple' => true
				)
			)
		);

		if (!$GLOBALS['egw']->acl->check('current_sessions',1,'admin'))	// no rights to view
		{
			$actions[] = array(
				'description' => 'Login History',
				'url'         => '/index.php',
				'extradata'   => 'menuaction=admin.admin_accesslog.index',
				'icon'        => 'timesheet',
			);
		}

		if (!$GLOBALS['egw']->acl->check('account_access',64,'admin'))	// no rights to set ACL-rights
		{
			$actions[] = array(
				'description' => 'Deny access',
				'url'         => '/index.php',
				'extradata'   => 'menuaction=admin.admin_denyaccess.list_apps',
				'icon'        => 'cancel',
			);
		}

		// currently no way to deny Admins access to administrate mail
		// we could add a deny check as for other admin functionality
		{
			$actions[] = array(
				'id'      => 'mail_account',
				'caption' => 'mail account',
				'url'     => 'menuaction=admin.admin_mail.edit&account_id=$id',
				'popup'   => '720x700',
				'icon'    => 'mail/navbar',
			);

			$emailadmin = Api\Mail\Account::get_default();
			if ($emailadmin->acc_smtp_type && $emailadmin->acc_smtp_type !== 'EGroupware\Api\Mail\Smtp')
			{
				$actions[] = array (
					'id'       => 'mail_activeAccounts',
					'caption'  => '(de)activate mail accounts',
					'icon'    => 'mail/navbar',
					'children' => array (
						'active' => array (
							'caption'		  => 'activate',
							'onExecute'	  	  => 'javaScript:app.admin.emailadminActiveAccounts',
							'icon'			  => 'check',
							'allowOnMultiple' => true
						),
						'inactive' => array (
							'caption'		  => 'deactivate',
							'onExecute'		  => 'javaScript:app.admin.emailadminActiveAccounts',
							'icon'			  => 'bullet',
							'allowOnMultiple' => true
						)
					)
				);
			}
		}
		return $actions;
	}

	/**
	 * Called before displaying site configuration
	 *
	 * @param array $config
	 * @return array with additional Api\Config to merge
	 */
	public static function config(array $config)
	{
		$ret = array();
		if (empty($config['fw_mobile_app_list']))
		{
			$ret['fw_mobile_app_list'] = Api\Framework\Ajax::DEFAULT_MOBILE_APPS;
		}
		return $ret;
	}
}