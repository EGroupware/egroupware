<?php
/**
 * Addressbook - admin, preferences and sidebox-menus
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

/**
 * Class containing admin, preferences and sidebox-menus (used as hooks)
 *
 * @package addressbook
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2006 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class contacts_admin_prefs
{
	var $contact_repository = 'sql';
	
	/**
	 * constructor
	 */
	function contacts_admin_prefs()
	{
		if($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap') $this->contact_repository = 'ldap';
	}

	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	function all_hooks($args)
	{
		$appname = 'addressbook';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>contacts_admin_prefs::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$file = array(
				array(
					'text' => '<a class="textSidebox" href="'.$GLOBALS['egw']->link('/index.php',array('menuaction' => 'addressbook.uicontacts.edit')).
						'" onclick="window.open(this.href,\'_blank\',\'dependent=yes,width=850,height=440,scrollbars=yes,status=yes\'); 
						return false;">'.lang('Add').'</a>',
					'no_lang' => true,
					'link' => false
				),
// Disabled til they are working again
//				'Advanced search'=>$GLOBALS['egw']->link('/index.php','menuaction=addressbook.uicontacts.search'),
//				'import contacts' => $GLOBALS['egw']->link('/index.php','menuaction=addressbook.uiXport.import'),
//				'export contacts' => $GLOBALS['egw']->link('/index.php','menuaction=addressbook.uiXport.export'),
				'CSV-Import'      => $GLOBALS['egw']->link('/addressbook/csv_import.php')
			);
			display_sidebox($appname,lang('Addressbook menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($this->contact_repository == 'ldap' || $GLOBALS['egw_info']['server']['deny_user_grants_access'])
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
				'Site configuration' => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => $appname,
				)),
				'Global Categories'  => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True,
				)),
			);
			// custom fields are not availible in LDAP
			if ($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap')
			{
				$file['Custom fields'] = $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'admin.customfields.edit',
					'appname'    => $appname,
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
	 * populates $GLOBALS['settings'] for the preferences
	 */
	function settings()
	{
		$GLOBALS['settings']['add_default'] = array(
			'type'   => 'select',
			'label'  => 'Default addressbook for adding contacts',
			'name'   => 'add_default',
			'help'   => 'Which addressbook should be selected when adding a contact AND you have no add rights to the current addressbook.',
			'values' => ExecMethod('addressbook.uicontacts.get_addressbooks',EGW_ACL_ADD),
			'xmlrpc' => True,
			'admin'  => False,
		);
		$GLOBALS['settings']['mainscreen_showbirthdays'] = array(
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
		);
		$column_display_options = array(
			''       => lang('only if there is content'),
			'always' => lang('always'),
			'never'  => lang('never'),
		);
		foreach(array(
			'photo_column' => lang('Photo'),
			'home_column'  => lang('Home address'),
			'custom_colum' => lang('custom fields'),
		) as $name => $label)
		{
			$GLOBALS['settings'][$name] = array(
				'type'   => 'select',
				'label'  => lang('Show a column for %1',$label),
				'run_lang' => -1,
				'name'   => $name,
				'values' => $column_display_options,
				'help'   => 'When should the contacts list display that colum. "Only if there is content" hides the column, unless there is some content in the view.',
				'xmlrpc' => True,
				'admin'  => false,
			);
		}
		// CSV Export
		$GLOBALS['settings']['csv_fields'] = array(
			'type'   => 'select',
			'label'  => 'Fields for the CSV export',
			'name'   => 'csv_fields',
			'values' => array(
				'' => lang('All'),
				'business' => lang('Business address'),
				'home'     => lang('Home address'),
			),	
			'help'   => 'Which fields should be exported. All means every field stored in the addressbook incl. the custom fields. The business or home address only contains name, company and the selected address.',
			'xmlrpc' => True,
			'admin'  => false,
		);
		$GLOBALS['settings']['csv_charset'] = array(
			'type'   => 'select',
			'label'  => 'Charset for the CSV export',
			'name'   => 'csv_charset',
			'values' => $GLOBALS['egw']->translation->get_installed_charsets()+array('utf-8' => 'utf-8 (Unicode)'),		
			'help'   => 'Which charset should be used for the CSV export. The system default is the charset of this eGroupWare installation.',
			'xmlrpc' => True,
			'admin'  => false,
		);

		if ($this->contact_repository == 'sql')
		{
			$GLOBALS['settings']['private_addressbook'] = array(
				'type'   => 'check',
				'label'  => 'Enable an extra private addressbook',
				'name'   => 'private_addressbook',
				'help'   => 'Do you want a private addressbook, which can not be viewed by users, you grant access to your personal addressbook?',
				'xmlrpc' => True,
				'admin'  => False,
			);
		}
		$GLOBALS['settings']['link_title'] = array(
			'type'   => 'select',
			'label'  => 'Link title for contacts show',
			'name'   => 'link_title',
			'values' => array(
				'n_fileas' => lang('own sorting').' ('.lang('default').': '.lang('Company').': '.lang('lastname').', '.lang('firstname').')',
				'org_name: n_family, n_given' => lang('Company').': '.lang('lastname').', '.lang('firstname'),
				'org_name, org_unit: n_family, n_given' => lang('Company').', '.lang('Department').': '.lang('lastname').', '.lang('firstname'),
				'org_name, adr_one_locality: n_family, n_given' => lang('Company').', '.lang('City').': '.lang('lastname').', '.lang('firstname'),
				'org_name, org_unit, adr_one_locality: n_family, n_given' => lang('Company').', '.lang('Department').', '.lang('City').': '.lang('lastname').', '.lang('firstname'),
			),		
			'help'   => 'What should links to the addressbook display in other applications. Empty values will be left out. You need to log in anew, if you change this setting!',
			'xmlrpc' => True,
			'admin'  => false,
		);
		return true;	// otherwise prefs say it cant find the file ;-)
	}

	/**
	 * add an Addressbook tab to Admin >> Edit user
	 */
	function edit_user()
	{
		global $menuData;

		$menuData[] = array(
			'description' => 'Addressbook',
			'url'         => '/index.php',
			'extradata'   => 'menuaction=addressbook.uicontacts.edit',
			'options'     => "onclick=\"window.open(this,'_blank','dependent=yes,width=850,height=440,scrollbars=yes,status=yes'); return false;\"".
				' title="'.htmlspecialchars(lang('Edit extra account-data in the addressbook')).'"',
		);
	}
}
