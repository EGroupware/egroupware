<?php
	/**
	 * importexport
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package importexport
	 * @author Cornelius Weiss <nelius@cwtech.de>
	 * @version $Id$
	 */
	
	$setup_info['importexport']['name']      = 'importexport';
	$setup_info['importexport']['version']   = '1.4';
	$setup_info['importexport']['app_order'] = 2;
	$setup_info['importexport']['enable']    = 2;
	$setup_info['importexport']['tables']    = array('egw_importexport_definitions');
	
	$setup_info['importexport']['author'] = 
	$setup_info['importexport']['maintainer'] = array(
		'name'  => 'Cornelius Weiss',
		'email' => 'nelius@cwtech.de'
	);
	$setup_info['importexport']['license']  = 'GPL';
	$setup_info['importexport']['description'] = 
	'';
	$setup_info['importexport']['note'] = 
	'';
	
	/* The hooks this app includes, needed for hooks registration */
	//$setup_info['importexport']['hooks']['preferences'] = 'importexport'.'.admin_prefs_sidebox_hooks.all_hooks';
	//$setup_info['importexport']['hooks']['settings'] = 'importexport'.'.admin_prefs_sidebox_hooks.settings';
	$setup_info['importexport']['hooks']['admin'] = 'importexport'.'.importexport_admin_prefs_sidebox_hooks.all_hooks';
	$setup_info['importexport']['hooks']['sidebox_menu'] = 'importexport'.'.importexport_admin_prefs_sidebox_hooks.all_hooks';
	//$setup_info['importexport']['hooks']['search_link'] = 'importexport'.'.bomyterra.search_link';
	
	/* Dependencies for this app to work */
	$setup_info['importexport']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => Array('1.3','1.4','1.5')
	);
	$setup_info['importexport']['depends'][] = array(
		 'appname' => 'etemplate',
		 'versions' => Array('1.3','1.4','1.5')
	);
	



