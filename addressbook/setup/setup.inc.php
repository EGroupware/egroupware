<?php
/**
 * EGroupware - Addressbook
 *
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/* Basic information about this app */
$setup_info['addressbook']['name']      = 'addressbook';
$setup_info['addressbook']['title']     = 'Addressbook';
$setup_info['addressbook']['version']   = '21.1';
$setup_info['addressbook']['app_order'] = 4;
$setup_info['addressbook']['enable']    = 1;
$setup_info['addressbook']['index']    = 'addressbook.addressbook_ui.index&ajax=true';

$setup_info['addressbook']['author'] = 'Ralf Becker, Cornelius Weiss, Lars Kneschke';
$setup_info['addressbook']['license']  = 'GPL';
$setup_info['addressbook']['description'] =
	'Contact manager with Vcard support.<br />
	 Always have your address book available for updates or look ups from anywhere. <br />
	 Share address book contact information with others. <br />
	 Link contacts to calendar events or InfoLog entires like phonecalls.<br />
	 Addressbook is the eGroupWare default contact application. <br />
	 It stores contact information via SQL or LDAP and provides contact services via the eGroupWare API.';

$setup_info['addressbook']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'ralfbecker@outdoor-training.de'
);

$setup_info['addressbook']['tables']  = array();	// addressbook tables are in the API!

/* The hooks this app includes, needed for hooks registration */
$setup_info['addressbook']['hooks']['admin'] = 'addressbook_hooks::all_hooks';
$setup_info['addressbook']['hooks']['sidebox_menu'] = 'addressbook_hooks::all_hooks';
$setup_info['addressbook']['hooks']['settings'] = 'addressbook_hooks::settings';
$setup_info['addressbook']['hooks']['deleteaccount'] = 'addressbook.addressbook_bo.deleteaccount';
$setup_info['addressbook']['hooks']['deletegroup'] = 'addressbook.addressbook_bo.deleteaccount';
$setup_info['addressbook']['hooks']['delete_category'] = 'addressbook.addressbook_bo.delete_category';
$setup_info['addressbook']['hooks']['search_link'] = 'addressbook_hooks::search_link';
$setup_info['addressbook']['hooks']['calendar_resources'] = 'addressbook_hooks::calendar_resources';
$setup_info['addressbook']['hooks']['config'] = 'addressbook_hooks::config';
$setup_info['addressbook']['hooks']['group_acl'] = 'addressbook_hooks::group_acl';
$setup_info['addressbook']['hooks']['not_enum_group_acls'] = 'addressbook_hooks::not_enum_group_acls';
$setup_info['addressbook']['hooks']['export_limit'] = 'addressbook_hooks::getAppExportLimit';
$setup_info['addressbook']['hooks']['acl_rights'] = 'addressbook_hooks::acl_rights';
$setup_info['addressbook']['hooks']['categories'] = 'addressbook_hooks::categories';
$setup_info['addressbook']['hooks']['groupdav_root_props'] = 'addressbook_groupdav::groupdav_root_props';
$setup_info['addressbook']['hooks']['status-get_actions'] = 'addressbook_hooks::status_get_actions';

/* Dependencies for this app to work */
$setup_info['addressbook']['depends'][] = array(
	'appname' => 'api',
	'versions' => Array('21.1')
);

// installation checks for addresbook
$setup_info['addressbook']['check_install'] = array(
	'gd' => array(
		'func' => 'extension_check',
	),
	'imagecreatefromjpeg' => array(
		'func' => 'function_check',
		'warning' => "The imagecreatefromjpeg function is supplied by the gd extension (complied with jpeg support!). It's needed to upload photos for contacts.",
	),
);
