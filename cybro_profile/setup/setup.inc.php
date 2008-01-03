<?php
    /**************************************************************************\
    * eGroupWare - cybro_profile                                                     *
    * http://www.egroupware.org                                                *
    * -----------------------------------------------                          *
    *  This program is free software; you can redistribute it and/or modify it *
    *  under the terms of the GNU General Public License as published by the   *
    *  Free Software Foundation; either version 2 of the License, or (at your  *
    *  option) any later version.                                              *
    \**************************************************************************/

	/* $Id$ */

	/* Basic information about this app */
	$setup_info['cybro_profile']['name']      = 'cybro_profile';
	$setup_info['cybro_profile']['title']     = 'Cybro Profile';
	$setup_info['cybro_profile']['version']   = '0.001';
	$setup_info['cybro_profile']['app_order'] = 90;
	$setup_info['cybro_profile']['enable']    = 1;

	/* some info's for about.php and apps.egroupware.org */
	$setup_info['cybro_profile']['author']    = 'RVD';
	$setup_info['cybro_profile']['license']   = 'GPL';
	$setup_info['cybro_profile']['description'] =
	'reprofile myprofile';
	$setup_info['cybro_profile']['note'] =
	'must be simple';
	$setup_info['cybro_profile']['maintainer'] = 'Richard van Diessen';
	$setup_info['cybro_profile']['maintainer_email'] = 'richard@jataggo.nl';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['cybro_profile']['hooks'] = array(
		'preferences',
		'manual',
		'home',
		'settings'
	);
	$setup_info['cybro_profile']['hooks']['sidebox_menu'] = 'cybro_profile.ui_cprofile.sidebox_menu';

	/* Dependacies for this app to work */
	$setup_info['cybro_profile']['depends'][] = array(
		'appname' => 'phpgwapi',
		'versions' => array('1.0.0','1.0.1','1.2','1.3','1.3.005','1.3.006','1.4','1.5')
	);
	$setup_info['cybro_profile']['depends'][] = array(
	   'appname' => 'jinn',
	   'versions' => array('0.9.021','0.9.026','0.9.1','0.9.031','0.9','1.0.0')
	);
	
	/* The tables this app creates */
	$setup_info['cybro_profile']['tables'] = array('egw_cybro_profile_links','egw_cybro_profile_frontpage','egw_cybro_profile_homepage','egw_cybro_profile_contacts');



