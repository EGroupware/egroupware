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

	$bodyheader = 'BGCOLOR="'.$phpgw_info['theme']['bg_color'].'"';
	if ($phpgw_info['server']['htmlcompliant']) {
		$bodyheader .= ' ALINK="'.$phpgw_info['theme']['alink'].'" LINK="'.$phpgw_info['theme']['link'].'" VLINK="'.$phpgw_info['theme']['vlink'].'"';
	}

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('head' => 'head.tpl'));
	$tpl->set_var('font_family',$phpgw_info['theme']['font']);
	$tpl->set_var('charset',lang('charset'));
	$tpl->set_var('website_title',$phpgw_info['server']['site_title']);
	$tpl->set_var('body_tags',$bodyheader);
	echo $tpl->finish($tpl->parse('out','head'));
	unset($tpl);
?>
