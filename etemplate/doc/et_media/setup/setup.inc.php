<?php
	/**************************************************************************\
	* eGroupWare - Editable Templates: Example App of the tutorial             *
	* http://www.egroupware.org                                                *
	" Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$setup_info['et_media']['name']      = 'et_media';
	$setup_info['et_media']['title']     = 'eT-Media';
	$setup_info['et_media']['version']   = '1.2';
	$setup_info['et_media']['app_order'] = 100;     // at the end
	$setup_info['et_media']['tables']    = array('egw_et_media');
	$setup_info['et_media']['enable']    = 1;
	$setup_info['et_media']['author'] = 
 	$setup_info['et_media']['maintainer'] = array(
		'name'  => 'Ralf Becker',
		'email' => 'ralfbecker@outdoor-training.de'
	);
	$setup_info['et_media']['license']   = 'GPL';
	$setup_info['et_media']['description'] =
		'<b>eTemplates</b> are a new widget-based template system for eGroupWare.<br>
		<b>eT-Media</b> is the example application of the eTemplates tutorial.';
	$setup_info['et_media']['note'] =
		'For more information check out the <a href="etemplate/doc/etemplate.html" target="_blank">Tutorial</a>
		and the <a href="etemplate/doc/referenz.html" target="_blank">Referenz Documentation</a>.';

	/* Dependencies for this app to work */
	$setup_info['et_media']['depends'][] = array(
				'appname' => 'phpgwapi',
				'versions' => Array('1.0.0','1.0.1','1.2','1.3')
	);
	$setup_info['et_media']['depends'][] = array(
				'appname' => 'etemplate',
				'versions' => Array('1.0.0','1.0.1','1.2','1.3')
	);
