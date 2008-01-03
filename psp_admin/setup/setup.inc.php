<?php
    /**************************************************************************\
    * eGroupWare - psp_admin                                                     *
    * http://www.egroupware.org                                                *
    * -----------------------------------------------                          *
    *  This program is free software; you can redistribute it and/or modify it *
    *  under the terms of the GNU General Public License as published by the   *
    *  Free Software Foundation; either version 2 of the License, or (at your  *
    *  option) any later version.                                              *
    \**************************************************************************/

	/* $Id$ */

	/* Basic information about this app */
	$setup_info['psp_admin']['name']      = 'psp_admin';
	$setup_info['psp_admin']['title']     = 'PSP Admin';
	$setup_info['psp_admin']['version']   = '0.001';
	$setup_info['psp_admin']['app_order'] = 90;
	$setup_info['psp_admin']['enable']    = 1;

	/* some info's for about.php and apps.egroupware.org */
	$setup_info['psp_admin']['author']    = 'RVD';
	$setup_info['psp_admin']['license']   = 'GPL';
	$setup_info['psp_admin']['description'] =
	'pspadmin';
	$setup_info['psp_admin']['note'] =
	'pspadmin';
	$setup_info['psp_admin']['maintainer'] = 'Richard van Diessen';
	$setup_info['psp_admin']['maintainer_email'] = 'richard@jataggo.com';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['psp_admin']['hooks'] = array(
		'preferences',
		'manual',
		'home',
		'settings'
	);
	$setup_info['psp_admin']['hooks']['sidebox_menu'] = 'psp_admin.ui_pspadmin.sidebox_menu';

	/* Dependacies for this app to work */
	$setup_info['psp_admin']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => array('1.0.0','1.0.1','1.2','1.3','1.3.005','1.3.006','1.4','1.5')
	);
	$setup_info['psp_admin']['depends'][] = array(
	   'appname' => 'jinn',
	   'versions' => array('0.9.021','0.9.026','0.9.1','0.9.031','0.9','1.0.0')
	);
	
	/* The tables this app creates */
	$setup_info['psp_admin']['tables'] = array('egw_psp_admin_links','egw_psp_admin_frontpage','egw_psp_admin_homepage','egw_psp_admin_contacts');



