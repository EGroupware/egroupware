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
	$GLOBALS['phpgw_info']['flags']['disable_Template_class'] = True;
	include('header.inc.php');

	$app = $HTTP_GET_VARS['app'];
	if ($app)
	{
		$included = $GLOBALS['phpgw']->hooks->single('about',$app);
	}
	else
	{
		$api_only = True;
	}

	$tpl = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi'));
	$tpl->set_file(array(
		'phpgw_about'         => 'about.tpl',
		'phpgw_about_unknown' => 'about_unknown.tpl'
	));

	$tpl->set_var('webserver_url',$GLOBALS['phpgw']->common->get_image_path('phpgwapi'));
	$tpl->set_var('phpgw_version','phpGroupWare API version ' . $GLOBALS['phpgw_info']['server']['versions']['phpgwapi']);
	if ($included)
	{
		$tpl->set_var('phpgw_app_about',about_app('',''));
		//about_app($tpl,"phpgw_app_about");
	}
	else
	{
		if ($api_only)
		{
			$tpl->set_var('phpgw_app_about','');
		}
		else
		{
			$tpl->set_var('app_header',$app);
			$tpl->parse('phpgw_app_about','phpgw_about_unknown');
		}
	}

	$tpl->pparse('out','phpgw_about');
?>
