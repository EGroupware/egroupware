<?php
	/**************************************************************************\
	* phpGroupWare - PHP Webhosting                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['phpwebhosting']['name']    = 'phpwebhosting';
	$setup_info['phpwebhosting']['title']   = 'PHPWebHosting';
	$setup_info['phpwebhosting']['version'] = '0.9.13.005';
	$setup_info['phpwebhosting']['app_order'] = 10;
	$setup_info['phpwebhosting']['enable']  = 1;

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['phpwebhosting']['hooks'] = array ('add_def_pref', 'admin', 'deleteaccount', 'preferences');

	/* Dependencies for this app to work */
	$setup_info['phpwebhosting']['depends'][] = array(
		 'appname' => 'phpgwapi',
		 'versions' => array('0.9.10', '0.9.11' , '0.9.12', '0.9.13')
	);
?>
