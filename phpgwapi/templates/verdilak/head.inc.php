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

	if($GLOBALS['menuaction'] && is_array($GLOBALS['obj']->public_functions) && $GLOBALS['obj']->public_functions['css'])
	{
		eval("\$app_css = \$GLOBALS['obj']->css();");
	}
	else
	{
		$app_css = '';
	}

	$bodyheader = ' bgcolor="' . $GLOBALS['phpgw_info']['theme']['bg_color'] . '" alink="'
			. $GLOBALS['phpgw_info']['theme']['alink'] . '" link="' . $GLOBALS['phpgw_info']['theme']['link'] . '" vlink="'
			. $GLOBALS['phpgw_info']['theme']['vlink'] . '"';

	if (! $GLOBALS['phpgw_info']['server']['htmlcompliant'])
	{
		$bodyheader .= ' topmargin="0" bottommargin="0" marginheight="0" marginwidth="0" leftmargin="0" rightmargin="0"';
	}

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('head' => 'head.tpl'));
	$var = Array (
		'charset'		=> lang('charset'),
		'font_family'	=> $GLOBALS['phpgw_info']['theme']['font'],
		'website_title'	=> $GLOBALS['phpgw_info']['server']['site_title'],
		'body_tags'		=> $bodyheader,
		'app_css'		=> $app_css
	);
	$tpl->set_var($var);
	$tpl->pfp('out','head');
	unset($tpl);
?>
