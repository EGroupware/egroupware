<?php
	/**************************************************************************\
	* phpGroupWare - Notes eTemplates Port                                     *
	* http://www.phpgroupware.org                                              *
	* Ported to eTemplate by Ralf Becker [ralfbecker@outdoor-training.de]      *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$setup_info['et_notes']['name']      = 'et_notes'; 
	$setup_info['et_notes']['version']   = '0.9.15.001';
	$setup_info['et_notes']['app_order'] = 8;
	$setup_info['et_notes']['tables']    = array('phpgw_et_notes');
	$setup_info['et_notes']['enable']    = 1;
	$setup_info['et_notes']['licenze']   = 'GPL';
	
	$setup_info['et_notes']['description'] =
		'Notes and short texts can go in here';
	$setup_info['et_notes']['description'] =
		'This is an eTemplate port of the notes app.';
		
	$setup_info['et_notes']['author'] = 'Bettina Gille, Andy Holman (LoCdOg)';
	$setup_info['et_notes']['maintainer'] = 'Ralf Becker';
	$setup_info['et_notes']['maintainer_email'] = 'ralfbecker@phpgroupware.org';

	/* The hooks this app includes, needed for hooks registration */
	$setup_info['et_notes']['hooks'][] = 'deleteaccount';
	$setup_info['et_notes']['hooks'][] = 'admin';

	/* Dependencies for this app to work */
	$setup_info['et_notes']['depends'][] = array(
		'appname'  => 'phpgwapi',
		'versions' => Array('0.9.13','0.9.14','0.9.15')
	);
	$setup_info['et_media']['depends'][] = array(   // this is only necessary as long the etemplate-class is not in the api
				'appname' => 'etemplate',
				'versions' => Array('0.9.13','0.9.14','0.9.15')
	);
