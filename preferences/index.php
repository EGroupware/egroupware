<?php
	/**************************************************************************\
	* phpGroupWare - preferences                                               *
	* http://www.phpgroupware.org                                              *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags']['currentapp'] = 'preferences';
	include('../header.inc.php');

	$pref_tpl = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$templates = Array(
		'pref' => 'index.tpl'
	);
	
	$pref_tpl->set_file($templates);

	$pref_tpl->set_block('pref','list');
	$pref_tpl->set_block('pref','app_row');
	$pref_tpl->set_block('pref','app_row_noicon');
	$pref_tpl->set_block('pref','link_row');
	$pref_tpl->set_block('pref','spacer_row');

	// This func called by the includes to dump a row header
	function section_start($name='',$icon='',$appname='')
	{
		global $phpgw_info, $pref_tpl;
		
		$pref_tpl->set_var('icon_backcolor',$phpgw_info['theme']['row_off']);
//		$pref_tpl->set_var('link_backcolor',$phpgw_info['theme']['row_off']);
		$pref_tpl->set_var('a_name',$appname);
		$pref_tpl->set_var('app_name',lang($name));
		$pref_tpl->set_var('app_icon',$icon);
		if ($icon)
		{
			$pref_tpl->parse('rows','app_row',True);
		}
		else
		{
			$pref_tpl->parse('rows','app_row_noicon',True);
		} 
	}

	function section_item($pref_link='',$pref_text='')
	{
		global $pref_tpl;

		$pref_tpl->set_var('pref_link',$pref_link);
		$pref_tpl->set_var('pref_text',$pref_text);		
		$pref_tpl->parse('rows','link_row',True);
	} 

	function section_end()
	{
		global $pref_tpl;
		
		$pref_tpl->parse('rows','spacer_row',True);
	}

	function display_section($appname,$title,$file)
	{
		global $phpgw;
		section_start($title,$phpgw->common->image($appname,Array('navbar.gif',$appname.'.gif')),$appname);

		while(list($text,$url) = each($file))
		{
			section_item($url,lang($text));
		}
		section_end(); 
	}

	$phpgw->common->hook();
	$pref_tpl->pparse('out','list');
	$phpgw->common->phpgw_footer();
?>
