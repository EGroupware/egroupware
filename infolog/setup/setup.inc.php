<?php
/**
 * EGroupware - InfoLog - Setup
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @subpackage setup
 * @copyright (c) 2003-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$setup_info['infolog']['name']      = 'infolog';
$setup_info['infolog']['version']   = '1.9.003';
$setup_info['infolog']['app_order'] = 5;
$setup_info['infolog']['tables']    = array('egw_infolog','egw_infolog_extra');
$setup_info['infolog']['enable']    = 1;

$setup_info['infolog']['author'] =
$setup_info['infolog']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'ralfbecker@outdoor-training.de'
);
$setup_info['infolog']['license']  = 'GPL';
$setup_info['infolog']['description'] =
	'<p><b>CRM</b> (customer-relation-management) type app using Addressbook providing
	Todo List, Notes and Phonelog. <b>InfoLog</b> is orininaly based on eGroupWare\'s
	old ToDo-List and has the features of all 3 mentioned applications plus fully working ACL
	(including Add+Private attributes, add for to addreplys/subtasks).</p>
	<p>Responsibility for a task (ToDo) or a phonecall can be <b>delegated</b> to an other
	user. All entries can be linked to addressbook entries, projects and/or calendar events.
	This allows you to <b>log all activity of a contact</b>/address or project.
	The entries may be viewed or added from InfoLog direct or from within
	the contact/address, project or calendar view.</p>
	<p>Other documents / files can be linked to InfoLog entries and are store in the VFS
	(eGroupWare\'s virtual file system).</p>';
$setup_info['infolog']['note'] =
	'<p>There is a <b>CSV import</b> (in the admin-section) to import existing data.
	It allows to interactivly assign fields, customize the values with regular
	expressions and direct calls to php-functions (e.g. to link the phone calls
	(again) to the addressbook entrys).</p>
	<p><b>More information</b> about InfoLog and the current development-status can be found on the
	<a href="http://www.egroupware.org/wiki/infolog" target="_blank">InfoLog page on our Website</a>.</p>';

/* The hooks this app includes, needed for hooks registration */
$setup_info['infolog']['hooks']['preferences'] = 'infolog_hooks::all_hooks';
$setup_info['infolog']['hooks']['settings'] = 'infolog_hooks::settings';
$setup_info['infolog']['hooks']['verify_settings'] = 'infolog_hooks::verify_settings';
$setup_info['infolog']['hooks']['admin'] = 'infolog_hooks::all_hooks';
$setup_info['infolog']['hooks']['not_enum_group_acls'] = 'infolog_hooks::not_enum_group_acls';
$setup_info['infolog']['hooks']['deleteaccount'] = 'infolog.infolog_so.change_delete_owner';
$setup_info['infolog']['hooks'][] = 'home';
$setup_info['infolog']['hooks']['addressbook_view'] = 'infolog.infolog_ui.hook_view';
$setup_info['infolog']['hooks']['projects_view']    = 'infolog.infolog_ui.hook_view';
$setup_info['infolog']['hooks']['calendar_view']    = 'infolog.infolog_ui.hook_view';
$setup_info['infolog']['hooks']['infolog']          = 'infolog.infolog_ui.hook_view';
$setup_info['infolog']['hooks']['calendar_include_events'] = 'infolog.infolog_bo.cal_to_include';
$setup_info['infolog']['hooks']['calendar_include_todos']  = 'infolog.infolog_bo.cal_to_include';
$setup_info['infolog']['hooks']['sidebox_menu'] = 'infolog_hooks::all_hooks';
$setup_info['infolog']['hooks']['search_link'] = 'infolog_hooks::search_link';
$setup_info['infolog']['hooks']['pm_custom_app_icons'] = 'infolog.infolog_bo.pm_icons';
$setup_info['infolog']['hooks']['timesheet_set'] = 'infolog.infolog_ui.timesheet_set';

/* Dependencies for this app to work */
$setup_info['infolog']['depends'][] = array(
	'appname' => 'phpgwapi',
	'versions' => Array('1.7','1.8','1.9')
);
$setup_info['infolog']['depends'][] = array(
	'appname' => 'etemplate',
	'versions' => Array('1.7','1.8','1.9')
);
