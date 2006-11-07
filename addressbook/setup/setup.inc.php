<?php
/**
 * Addressbook - setup config
 *
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Basic information about this app */
$setup_info['addressbook']['name']      = 'addressbook';
$setup_info['addressbook']['title']     = 'Addressbook';
$setup_info['addressbook']['version']   = '1.3.002';
$setup_info['addressbook']['app_order'] = 4;
$setup_info['addressbook']['enable']    = 1;

$setup_info['addressbook']['author'] = 'Ralf Becker, Cornelius Weiss, Lars Kneschke';
$setup_info['addressbook']['license']  = 'GPL';
$setup_info['addressbook']['description'] =
	'Contact manager with Vcard support.<br />
	 Always have your address book available for updates or look ups from anywhere. <br />
	 Share address book contact information with others. <br />
	 Link contacts to calendar events or InfoLog entires like phonecalls.<br /> 
	 Addressbook is the eGroupWare default contact application. <br />
	 It stores contact information via SQL or LDAP and provides contact services via the eGroupWare API.';

$setup_info['addressbook']['maintainer'] = 'eGroupWare coreteam';
$setup_info['addressbook']['maintainer_email'] = 'egroupware-developers@lists.sourceforge.net';

$setup_info['addressbook']['tables']  = array();	// addressbook tables are in the API!

/* The hooks this app includes, needed for hooks registration */
$setup_info['addressbook']['hooks']['admin'] = 'addressbook.contacts_admin_prefs.all_hooks';
$setup_info['addressbook']['hooks']['preferences'] = 'addressbook.contacts_admin_prefs.all_hooks';
$setup_info['addressbook']['hooks']['sidebox_menu'] = 'addressbook.contacts_admin_prefs.all_hooks';
$setup_info['addressbook']['hooks']['settings'] = 'addressbook.contacts_admin_prefs.settings';
$setup_info['addressbook']['hooks'][] = 'home';
$setup_info['addressbook']['hooks']['deleteaccount'] = 'addressbook.bocontacts.deleteaccount';
$setup_info['addressbook']['hooks']['search_link'] = 'addressbook.bocontacts.search_link';
$setup_info['addressbook']['hooks']['edit_user']    = 'addressbook.contacts_admin_prefs.edit_user';
$setup_info['addressbook']['hooks'][] = 'config';

/* Dependencies for this app to work */
$setup_info['addressbook']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.3','1.4')
);
$setup_info['addressbook']['depends'][] = array(
	'appname' => 'etemplate',
	'versions' => Array('1.2','1.3','1.4')
);

// installation checks for addresbook
$setup_info['projectmanager']['check_install'] = array(
	'gd' => array(
		'func' => 'extension_check',
	),
	'imagecreatefromjpeg' => array(
		'func' => 'function_check',
		'warning' => "The imagecreatefromjpeg function is supplied by the gd extension (complied with jpeg support!). It's needed to upload photos for contacts.",
	),
);

