<?php
/**
 * Addressbook - admin, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2006-10 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class addressbook_hooks
{
	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		$appname = 'addressbook';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>contacts_admin_prefs::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'Add'             => "javascript:egw_openWindowCentered2('".
					egw::link('/index.php',array('menuaction' => 'addressbook.addressbook_ui.edit'),false).
					"','_blank',870,480,'yes')",
				'Advanced search' => "javascript:egw_openWindowCentered2('".
					egw::link('/index.php',array('menuaction' => 'addressbook.addressbook_ui.search'),false).
					"','_blank',870,480,'yes')",
			);
			display_sidebox($appname,lang('Addressbook menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => egw::link('/index.php','menuaction=preferences.preferences_categories_ui.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap' || $GLOBALS['egw_info']['server']['deny_user_grants_access'])
			{
				unset($file['Grant Access']);
			}
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
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => $appname,
				)),
				'Global Categories'  => egw::link('/index.php',array(
					'menuaction' => 'admin.admin_categories.index',
					'appname'    => $appname,
					'global_cats'=> True,
				)),
			);
			// custom fields are not availible in LDAP
			if ($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap')
			{
				$file['Custom fields'] = egw::link('/index.php',array(
					'menuaction' => 'admin.customfields.edit',
					'appname'    => $appname,
					'use_private'=> 1,
				));
			}
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
	 * @param array|string $hook_data
	 * @return array
	 */
	static function settings($hook_data)
	{
		$settings = array();
		$settings['add_default'] = array(
			'type'   => 'select',
			'label'  => 'Default addressbook for adding contacts',
			'name'   => 'add_default',
			'help'   => 'Which addressbook should be selected when adding a contact AND you have no add rights to the current addressbook.',
			'values' => !$hook_data['setup'] ? ExecMethod('addressbook.addressbook_ui.get_addressbooks',EGW_ACL_ADD) : array(),
			'xmlrpc' => True,
			'admin'  => False,
		);
		if ($GLOBALS['egw_info']['server']['hide_birthdays'] != 'yes')	// calendar config
		{
			$settings['mainscreen_showbirthdays'] = array(
				'type'   => 'select',
				'label'  => 'Show birthday reminders on main screen',
				'name'   => 'mainscreen_showbirthdays',
				'help'   => 'Displays a remider for birthdays on the startpage (page you get when you enter eGroupWare or click on the homepage icon).',
				'values' => array(
					0 => lang('No'),
					1 => lang('Yes, for today and tomorrow'),
					3 => lang('Yes, for the next three days'),
					7 => lang('Yes, for the next week'),
					14=> lang('Yes, for the next two weeks'),
				),
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 3,
			);
		}
		$settings['no_auto_hide'] = array(
			'type'   => 'check',
			'label'  => 'Don\'t hide empty columns',
			'name'   => 'no_auto_hide',
			'help'   => 'Should the columns photo and home address always be displayed, even if they are empty.',
			'xmlrpc' => True,
			'admin'  => false,
			'forced' => false,
		);
		// CSV Export
		$settings['csv_fields'] = array(
			'type'   => 'select',
			'label'  => 'Fields for the CSV export',
			'name'   => 'csv_fields',
			'values' => array(
				'all'      => lang('All'),
				'business' => lang('Business address'),
				'home'     => lang('Home address'),
			),
			'help'   => 'Which fields should be exported. All means every field stored in the addressbook incl. the custom fields. The business or home address only contains name, company and the selected address.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'business',
		);
		$settings['csv_charset'] = array(
			'type'   => 'select',
			'label'  => 'Charset for the CSV export',
			'name'   => 'csv_charset',
			'values' => translation::get_installed_charsets(),
			'help'   => 'Which charset should be used for the CSV export. The system default is the charset of this eGroupWare installation.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'iso-8859-1',
		);

		$settings['vcard_charset'] = array(
			'type'   => 'select',
			'label'  => 'Charset for the vCard export',
			'name'   => 'vcard_charset',
			'values' => translation::get_installed_charsets(),
			'help'   => 'Which charset should be used for the vCard export.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'iso-8859-1',
		);

		if ($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap')
		{
			$settings['private_addressbook'] = array(
				'type'   => 'check',
				'label'  => 'Enable an extra private addressbook',
				'name'   => 'private_addressbook',
				'help'   => 'Do you want a private addressbook, which can not be viewed by users, you grant access to your personal addressbook?',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => false,
			);
		}
		$fileas_options = ExecMethod('addressbook.addressbook_bo.fileas_options');
		$settings['link_title'] = array(
			'type'   => 'select',
			'label'  => 'Link title for contacts show',
			'name'   => 'link_title',
			'values' => array(
				'n_fileas' => lang('own sorting').' ('.lang('default').': '.lang('Company').': '.lang('lastname').', '.lang('firstname').')',
			)+$fileas_options,	// plus all fileas types
			'help'   => 'What should links to the addressbook display in other applications. Empty values will be left out. You need to log in anew, if you change this setting!',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'org_name: n_family, n_given',
		);
		$settings['addr_format'] = array(
			'type'   => 'select',
			'label'  => 'Default address format',
			'name'   => 'addr_format',
			'values' => array(
				'postcode_city' => lang('zip code').' '.lang('City'),
				'city_state_postcode' => lang('City').' '.lang('State').' '.lang('zip code'),
			),
			'help'   => 'Which address format should the addressbook use for countries it does not know the address format. If the address format of a country is known, it uses it independent of this setting.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'postcode_city',
		);
		$settings['fileas_default'] = array(
			'type'   => 'select',
			'label'  => 'Default file as format',
			'name'   => 'fileas_default',
			'values' => $fileas_options,
			'help'   => 'Default format for fileas, eg. for new entries.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'org_name: n_family, n_given',
		);
		$settings['hide_accounts'] = array(
			'type'   => 'check',
			'label'  => 'Hide accounts from addressbook',
			'name'   => 'hide_accounts',
			'help'   => 'Hides accounts completly from the adressbook.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> '1',
		);
		$settings['distributionListPreferredMail'] = array(
			'type'   => 'select',
			'label'  => 'Preferred email address to use in distribution lists',
			'name'   => 'distributionListPreferredMail',
			'values' => array(
				'email'	=> lang("Work email if given, else home email"),
				'email_home'	=> lang("Home email if given, else work email"),
			),
			'help'   => 'Defines which email address (business or home) to use as the preferred one for distribution lists in mail.',
			'xmlrpc' => True,
			'admin'  => False,
			'forced'=> 'email',
		);
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$link = egw::link('/index.php','menuaction=addressbook.addressbook_merge.show_replacements');

			$settings['default_document'] = array(
				'type'   => 'vfs_file',
				'size'   => 60,
				'label'  => 'Default document to insert contacts',
				'name'   => 'default_document',
				'help'   => lang('If you specify a document (full vfs path) here, %1 displays an extra document icon for each entry. That icon allows to download the specified document with the data inserted.', lang('addressbook')).' '.
					lang('The document can contain placeholder like {{%3}}, to be replaced with the data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>', 'n_fn').' '.
					lang('The following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
			$settings['document_dir'] = array(
				'type'   => 'vfs_dirs',
				'size'   => 60,
				'label'  => 'Directory with documents to insert contacts',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, %1 displays an action for each document. That action allows to download the specified document with the data inserted.',lang('addressbook')).' '.
					lang('The document can contain placeholder like {{%3}}, to be replaced with the data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>','n_fn').' '.
					lang('The following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '/templates/addressbook',
			);
		}

		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'addressbook'
			));
			$options = array(
				'~nextmatch~'	=>	lang('Old fixed definition')
			);
			$default_def = 'export-addressbook';
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
		if ($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			$settings['force_mailto'] = array(
				'type'   => 'check',
				'label'  => 'Open EMail addresses in external mail program',
				'name'   => 'force_mailto',
				'help'   => 'Default is to open EMail addresses in EGroupware EMail application, if user has access to it.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> false,
			);
		}
		return $settings;
	}

	/**
	 * add an Addressbook tab to Admin >> Edit user
	 */
	static function edit_user()
	{
		global $menuData;

		$menuData[] = array(
			'description' => 'Addressbook',
			'url'         => '/index.php',
			'extradata'   => 'menuaction=addressbook.addressbook_ui.edit',
			'options'     => "onclick=\"egw_openWindowCentered2(this,'_blank',870,440,'yes'); return false;\"".
				' title="'.htmlspecialchars(lang('Edit extra account-data in the addressbook')).'"',
		);
	}

	/**
	 * Hook called by link-class to include calendar in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		return array(
			'query' => 'addressbook.addressbook_bo.link_query',
			'title' => 'addressbook.addressbook_bo.link_title',
			'titles' => 'addressbook.addressbook_bo.link_titles',
			'view' => array(
				'menuaction' => 'addressbook.addressbook_ui.view'
			),
			'view_id' => 'contact_id',
			'view_list'	=>	'addressbook.addressbook_ui.index',
			'edit' => array(
				'menuaction' => 'addressbook.addressbook_ui.edit'
			),
			'edit_id' => 'contact_id',
			'edit_popup'  => '870x440',
			'add' => array(
				'menuaction' => 'addressbook.addressbook_ui.edit'
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '870x440',
			'file_access_user' => true,	// file_access supports 4th parameter $user
			'file_access'=> 'addressbook.addressbook_bo.file_access',
			'default_types' => array('n' => array('name' => 'contact', 'options' => array('icon' => 'navbar.png','template' => 'addressbook.edit'))),
			// registers an addtional type 'addressbook-email', returning only contacts with email, title has email appended
			'additional' => array(
				'addressbook-email' => array(
					'query' => 'addressbook.addressbook_bo.link_query_email',
				),
			)
		);
	}

	/**
	 * Hook called to retrieve a app specific exportLimit
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return the export_limit to be applied for the app, may be empty, int or string
	 */
	static function getAppExportLimit($location)
	{
		return $GLOBALS['egw_info']['server']['contact_export_limit'];
	}

	/**
	 * Register contacts as calendar resources (items which can be sheduled by the calendar)
	 *
	 * @param array $args hook-params (not used)
	 * @return array
	 */
	static function calendar_resources($args)
	{
		return array(
			'type' => 'c',// one char type-identifiy for this resources
			'info' => 'addressbook.addressbook_bo.calendar_info',// info method, returns array with id, type & name for a given id
		);
	}

	/**
	 * Register addressbook for group-acl
	 *
	 * @param array $args hook-params (not used)
	 * @return boolean|string true=standard group acl link, of string with link
	 */
	static function group_acl($args)
	{
		// addressbook uses group-acl, only if contacts-backend is NOT LDAP, as the ACL can not be modified there
		return $GLOBALS['egw_info']['server']['contact_repository'] != 'ldap';
	}

	/**
	 * For which groups should no group acl be used: addressbook always
	 *
	 * @param string|array $data
	 * @return boolean|array true, false or array with group-account_id's
	 */
	static function not_enum_group_acls($data)
	{
		return true;
	}
}
