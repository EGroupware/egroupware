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

	$bodyheader = ' bgcolor="' . $phpgw_info['theme']['bg_color'] . '" alink="'
			. $phpgw_info['theme']['alink'] . '" link="' . $phpgw_info['theme']['link'] . '" vlink="'
			. $phpgw_info['theme']['vlink'] . '"';

	if (! $phpgw_info['server']['htmlcompliant'])
	{
		$bodyheader .= ' topmargin="0" bottommargin="0" marginheight="0" marginwidth="0" leftmargin="0" rightmargin="0"';
	}

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('head' => 'head.tpl'));
	$tpl->set_var('website_title', $phpgw_info['server']['site_title']);
	$tpl->set_var('body_tags',$bodyheader);
	$tpl->pfp('out','head');
	unset($tpl);
