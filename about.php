<?php
	/**************************************************************************\
	* phpGroupWare                                                             *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'about';
	include('header.inc.php');

	$app = $HTTP_GET_VARS['app'];

	if ($app)
	{
		$included = $GLOBALS['phpgw']->hooks->single('about',$app);
	}

	$tpl = CreateObject('phpgwapi.xslttemplates',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default'));
	$tpl->add_file(array('about'));

	if ($included)
	{
		$app_data = about_app();
	}

	$data = array
	(
		'phpgw_logo'	=> $GLOBALS['phpgw']->common->get_image_path('phpgwapi'),
		'lang_version'	=> lang('version'),
		'phpgw_version'	=> 'phpGroupWare API ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi'],
		'phpgw_descr'	=> lang('is a multi-user, web-based groupware suite written in PHP'), 
		'about_app'		=> $app_data
	);

	$tpl->set_var('about',$data);
	$tpl->pparse();
?>
