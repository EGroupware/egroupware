<?php
	/**************************************************************************\
	* eGroupWare - Home                                                        *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/


	/* Basic information about this app */
	$setup_info['home']['name']      = 'home';
	$setup_info['home']['title']     = 'Home';
	$setup_info['home']['version']   = '1.0.0';
	$setup_info['home']['app_order'] = 1;
	$setup_info['home']['enable']    = 1;

	$setup_info['home']['author'] = 'Edo van Bruggen';
	$setup_info['home']['license']  = 'GPL';
	$setup_info['home']['description'] = 'Displays home';
	$setup_info['home']['maintainer'] = array(
		'name' => 'eGroupWare Developers',
		'email' => 'egroupware-developers@lists.sourceforge.net'
	);

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['home']['hooks'][] = 'home';
	$setup_info['home']['hooks'][] = 'sidebox_menu';
	
	$setup_info['home']['hooks']['hasUpdates'] = 'home.updates.hasUpdates';
	$setup_info['home']['hooks']['showUpdates'] = 'home.updates.showUpdates';

?>
