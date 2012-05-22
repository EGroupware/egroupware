<?php
/**
 * InfoLog - Admin-, Preferences- and SideboxMenu-Hooks
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 */
class infolog_hooks
{
	/**
	 * For which groups should no group acl be used: infolog group owners
	 *
	 * @param string|array $data
	 * @return boolean|array true, false or array with group-account_id's
	 */
	static function not_enum_group_acls($data)
	{
		$config = config::read('infolog');

		return $config['group_owners'];
	}

	/**
	 * Hook called by link-class to include infolog in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		// register our not_enum_group_acls hook, if not already registered
		// can be removed after next infolog version update after 1.6
		if ($GLOBALS['egw']->hooks->single('not_enum_group_acls',$acl_app) === false)
		{
			include(EGW_INCLUDE_ROOT.'/infolog/setup/setup.inc.php');
			$GLOBALS['egw']->hooks->register_hooks('infolog',$setup_info['infolog']['hooks']);
			unset($setup_info);
		}

		return array(
			'query'      => 'infolog.infolog_bo.link_query',
			'title'      => 'infolog.infolog_bo.link_title',
			'titles'     => 'infolog.infolog_bo.link_titles',
			'view'       => array(
				'menuaction' => 'infolog.infolog_ui.edit',
			),
			'view_id'    => 'info_id',
			'view_popup'  => '750x580',
			'view_list'	=>	'infolog.infolog_ui.index',
			'add' => array(
				'menuaction' => 'infolog.infolog_ui.edit',
				'type'   => 'task'
			),
			'add_app'    => 'action',
			'add_id'     => 'action_id',
			'add_popup'  => '750x550',
			'file_access'=> 'infolog.infolog_bo.file_access',
			'file_access_user' => true,	// file_access supports 4th parameter $user
			'edit'       => array(
				'menuaction' => 'infolog.infolog_ui.edit',
			),
			'edit_id'    => 'info_id',
			'edit_popup'  => '750x580',
		);
	}

	/**
	 * hooks to build sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		$appname = 'infolog';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>admin_prefs_sidebox_hooks::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'infolog list' => egw::link('/index.php',array(
					'menuaction' => 'infolog.infolog_ui.index' )),
				'Add' => "javascript:egw_openWindowCentered2('".egw::link('/index.php',array(
					'menuaction' => 'infolog.infolog_ui.edit',
				),false)."','_blank',750,410,'yes');",
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps']['infolog']['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => egw::link('/index.php','menuaction=preferences.preferences_categories_ui.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site configuration' => egw::link('/index.php',array(
					'menuaction' => 'infolog.infolog_ui.admin' )),
				'Global Categories'  => egw::link('/index.php',array(
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True)),
				'Custom fields, typ and status' => egw::link('/index.php',array(
					'menuaction' => 'infolog.infolog_customfields.edit'))
			);
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
	 * populates $settings for the preferences
	 *
	 * @return array
	 */
	static function settings()
	{
		/* Setup some values to fill the array of this app's settings below */
		$info = new infolog_bo();	// need some labels from
		$filters = $show_home = array();
		$show_home[] = lang("DON'T show InfoLog");
		foreach($info->filters as $key => $label)
		{
			$show_home[$key] = $filters[$key] = lang($label);
		}

		// migrage old filter-pref 1,2 to the filter one 'own-open-today'
		if (isset($GLOBALS['type']) && in_array($GLOBALS['egw']->preferences->{$GLOBALS['type']}['homeShowEvents'],array('1','2')))
		{
			$GLOBALS['egw']->preferences->add('infolog','homeShowEvents','own-open-today',$GLOBALS['type']);
			$GLOBALS['egw']->preferences->save_repository();
		}
		$show_links = array(
			'all'    => lang('all links and attachments'),
			'links'  => lang('only the links'),
			'attach' => lang('only the attachments'),
			'none'   => lang('no links or attachments'),
				'no_describtion' => lang('no describtion, links or attachments'),
		);
		$show_details = array(
			0 => lang('No'),
			1 => lang('Yes'),
			2 => lang('Only for details'),
		);

		/* Settings array for this app */
		$settings = array(
			'defaultFilter' => array(
				'type'   => 'select',
				'label'  => 'Default Filter for InfoLog',
				'name'   => 'defaultFilter',
				'values' => $filters,
				'help'   => 'This is the filter InfoLog uses when you enter the application. Filters limit the entries to show in the actual view. There are filters to show only finished, still open or futures entries of yourself or all users.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'none',
			),
			'homeShowEvents' => array(
				'type'   => 'select',
				'label'  => 'InfoLog filter for the main screen',
				'name'   => 'homeShowEvents',
				'values' => $show_home,
				'help'   => 'Should InfoLog show up on the main screen and with which filter. Works only if you dont selected an application for the main screen (in your preferences).',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'responsible-open-today',
			),
			'listNoSubs' => array(
				'type'   => 'check',
				'label'  => 'List no Subs/Childs',
				'name'   => 'listNoSubs',
				'help'   => 'Should InfoLog show Subtasks, -calls or -notes in the normal view or not. You can always view the Subs via there parent.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '0',	// No = List subs
			),
			'show_links' => array(
				'type'   => 'select',
				'label'  => 'Show in the InfoLog list',
				'name'   => 'show_links',
				'values' => $show_links,
				'help'   => 'Should InfoLog show the links to other applications and/or the file-attachments in the InfoLog list (normal view when you enter InfoLog).',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'all',
			),
			'never_hide' => array(
				'type'   => 'check',
				'label'  => 'Never hide search and filters',
				'name'   => 'never_hide',
				'help'   => 'If not set, the line with search and filters is hidden for less entries then "max matches per page" (as defined in your common preferences).',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '1',
			),
			'show_percent' => array(
				'type'   => 'select',
				'label'  => 'Show status and percent done separate',
				'name'   => 'show_percent',
				'values' => $show_details,
				'help'   => 'Should the Infolog list show the percent done only for status ongoing or two separate icons.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 1,	// Yes
			),
			'show_id' => array(
				'type'   => 'select',
				'label'  => 'Show ticket Id',
				'name'   => 'show_id',
				'values' => $show_details,
				'help'   => 'Should the Infolog list show a unique numerical Id, which can be used eg. as ticket Id.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '1',	// Yes
			),
			'limit_des_lines' => array(
				'type'   => 'input',
				'size'   => 5,
				'label'  => 'Limit number of description lines (default 5, 0 for no limit)',
				'name'   => 'limit_des_lines',
				'help'   => 'How many describtion lines should be directly visible. Further lines are available via a scrollbar.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 5,
			),
			'limit_des_width' => array(
				'type'   => 'input',
				'size'   => 5,
				'label'  => 'Limit width of description column ((effective only if lines limit is set), 0 for no limit)',
				'name'   => 'limit_des_width',
				'help'   => 'How wide should the description area be. This value is numeric and interpreted as em; 60 works reasonably well.',
				'xmlrpc' => True,
				'admin'  => False,
			),
			'set_start' => array(
				'type'   => 'select',
				'label'  => 'Startdate for new entries',
				'name'   => 'set_start',
				'values' => array(
					'date'     => lang('todays date'),
					'datetime' => lang('actual date and time'),
					'empty'    => lang('leave it empty'),
				),
				'help'   => 'To what should the startdate of new entries be set.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'date',
			),
			'cal_show' => array(
				'type'   => 'multiselect',
				'label'  => 'Which types should the calendar show',
				'name'   => 'cal_show',
				'values' => $info->enums['type'],
				'help'   => 'Can be used to show further InfoLog types in the calendar or limit it to show eg. only tasks.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'tasks,phone',
			),
			'cat_add_default' => array(
				'type'   => 'select',
				'label'  => 'Default category for new Infolog entries',
				'name'   => 'cat_add_default',
				'values' => self::all_cats(),
				'help'   => 'You can choose a categorie to be preselected, when you create a new Infolog entry',
				'xmlrpc' => True,
				'admin'  => False,
			),

		);

		// notification preferences
		$settings['notify_creator'] = array(
			'type'   => 'check',
			'label'  => 'Receive notifications about own items',
			'name'   => 'notify_creator',
			'help'   => 'Do you want a notification, if items you created get updated?',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '1',	// Yes
		);
		$settings['notify_assigned'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about items assigned to you',
			'name'   => 'notify_assigned',
			'help'   => 'Do you want a notification, if items get assigned to you or assigned items get updated?',
			'values' => array(
				'0' => lang('No'),
				'1' => lang('Yes'),
				'assignment' => lang('Only if I get assigned or removed'),
			),
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '1',	// Yes
		);

		// to add options for more then 3 days back or in advance, you need to update soinfolog::users_with_open_entries()!
		$options = array(
			'0'   => lang('No'),
			'-1d' => lang('one day after'),
			'0d'  => lang('same day'),
			'1d'  => lang('one day in advance'),
			'2d'  => lang('%1 days in advance',2),
			'3d'  => lang('%1 days in advance',3),
		);
		$settings['notify_due_delegated'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about due entries you delegated',
			'name'   => 'notify_due_delegated',
			'help'   => 'Do you want a notification, if items you delegated are due?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0',	// No
		);
		$settings['notify_due_responsible'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about due entries you are responsible for',
			'name'   => 'notify_due_responsible',
			'help'   => 'Do you want a notification, if items you are responsible for are due?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0d',	// Same day
		);
		$settings['notify_start_delegated'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about starting entries you delegated',
			'name'   => 'notify_start_delegated',
			'help'   => 'Do you want a notification, if items you delegated are about to start?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0',	// No
		);
		$settings['notify_start_responsible'] = array(
			'type'   => 'select',
			'label'  => 'Receive notifications about starting entries you are responsible for',
			'name'   => 'notify_start_responsible',
			'help'   => 'Do you want a notification, if items you are responsible for are about to start?',
			'values' => $options,
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0d',	// Same day
		);

		// receive notification for items owned by groups you are part of
		$settings['notify_owner_group_member'] = array(
			'type'   => 'check',
			'label'  => 'Receive notifications about items of type owned by groups you are part of',
			'name'   => 'notify_owner_group_member',
			'help'   => 'Do you want a notification if items owned by groups you are part of get updated ?',
			'xmlrpc' => True,
			'admin'  => False,
			'default'=> '0',	// No
		);

		// Merge print
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$link = egw::link('/index.php','menuaction=infolog.infolog_merge.show_replacements');

			$settings['default_document'] = array(
				'type'   => 'vfs_file',
				'size'   => 60,
				'label'  => 'Default document to insert entries',
				'name'   => 'default_document',
				'help'   => lang('If you specify a document (full vfs path) here, %1 displays an extra document icon for each entry. That icon allows to download the specified document with the data inserted.',lang('infolog')).' '.
					lang('The document can contain placeholder like {{%3}}, to be replaced with the data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>','info_subject').' '.
					lang('The following document-types are supported:').'*.rtf, *.txt',
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
			$settings['document_dir'] = array(
				'type'   => 'vfs_dirs',
				'size'   => 60,
				'label'  => 'Directory with documents to insert entries',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, %1 displays an action for each document. That action allows to download the specified document with the data inserted.',lang('infolog')).' '.
					lang('The document can contain placeholder like {{%3}}, to be replaced with the data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>','info_subject').' '.
					lang('The following document-types are supported:').'*.rtf, *.txt',
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '/templates/infolog',
			);
		}

		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'infolog'
			));
			$options = array(
				'~nextmatch~'	=>	lang('Old fixed definition')
			);
			foreach ((array)$definitions->get_definitions() as $identifier)
			{
				try
				{
					$definition = new importexport_definition($identifier);
				}
				catch (Exception $e)
				{
					// permission error
					continue;
				}
				if ($title = $definition->get_title())
				{
					$options[$title] = $title;
				}
				unset($definition);
			}
			$default_def = 'export-infolog';
			$settings['nextmatch-export-definition'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definition to use for nextmatch export',
				'name'   => 'nextmatch-export-definition',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> isset($options[$default_def]) ? $default_def : false,
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['calendar'])
		{
			$settings['calendar_set'] = array(
				'type'   => 'multiselect',
				'label'  => 'Participants for scheduling an appointment',
				'name'   => 'calendar_set',
				'values' => array(
					'responsible' => lang('Responsible'),
					'contact' => lang('Contact'),
					'owner' => lang('Owner'),
					'user' => lang('Current user'),
					'selected' => lang('Selected calendars'),
				),
				'help'   => 'Which participants should be preselected when scheduling an appointment.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'responsible,contact,user',
			);
		}
		return $settings;
	}

	/**
	 * Return InoLog Categories (used for setting )
	 *
	 * @return array
	 */
	private static function all_cats()
	{
		$categories = new categories('','infolog');
		$accountId = $GLOBALS['egw_info']['user']['account_id'];

		foreach((array)$categories->return_sorted_array(0,False,'','','',true) as $cat)
		{
			$s = str_repeat('&nbsp;',$cat['level']) . stripslashes($cat['name']);

			if ($cat['app_name'] == 'phpgw' || $cat['owner'] == '-1')
			{
				$s .= ' &#9830;';
			}
			elseif ($cat['owner'] != $accountId)
			{
				$s .= '&lt;' . $GLOBALS['egw']->accounts->id2name($cat['owner'], 'account_fullname') . '&gt;';
			}
			elseif ($cat['access'] == 'private')
			{
				$s .= ' &#9829;';
			}
			$sel_options[$cat['id']] = $s;	// 0.9.14 only
		}
		return $sel_options;
	}

	/**
	 * Verification hook called if settings / preferences get stored
	 *
	 * Installs a task to send async infolog notifications at 2h everyday
	 *
	 * @param array $data
	 */
	static function verify_settings($data)
	{
		if ($data['prefs']['notify_due_delegated'] || $data['prefs']['notify_due_responsible'] ||
			$data['prefs']['notify_start_delegated'] || $data['prefs']['notify_start_responsible'])
		{
			$async = new asyncservice();

			if (!$async->read('infolog-async-notification'))
			{
				$async->set_timer(array('hour' => 2),'infolog-async-notification','infolog.infolog_bo.async_notification',null);
			}
		}
	}
}
