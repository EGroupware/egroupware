<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* Modified by Stephen Brown <steve@dataclarity.net>                        *
	*  to distribute admin across the application directories                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	$admin_tpl = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$admin_tpl->set_file(array(
		'admin' => 'index.tpl'
	));

	$admin_tpl->set_block('admin','list');
	$admin_tpl->set_block('admin','app_row');
	$admin_tpl->set_block('admin','app_row_noicon');
	$admin_tpl->set_block('admin','link_row');
	$admin_tpl->set_block('admin','spacer_row');

	$admin_tpl->set_var('title',lang('Administration'));

	// This func called by the includes to dump a row header
	function section_start($name='',$icon='',$appname='')
	{
		global $phpgw, $phpgw_info, $admin_tpl;

		$admin_tpl->set_var('icon_backcolor',$phpgw_info['theme']['row_off']);
		$admin_tpl->set_var('link_backcolor',$phpgw_info['theme']['row_off']);
		$admin_tpl->set_var('app_name',lang($name));
		$admin_tpl->set_var('a_name',$appname);
		$admin_tpl->set_var('app_icon',$icon);
		if ($icon)
		{
			$admin_tpl->parse('rows','app_row',True);
		}
		else
		{
			$admin_tpl->parse('rows','app_row_noicon',True);
		} 
	}

	function section_item($pref_link='',$pref_text='')
	{
		global $phpgw, $phpgw_info, $admin_tpl;

		$admin_tpl->set_var('pref_link',$pref_link);
		$admin_tpl->set_var('pref_text',$pref_text);		
		$admin_tpl->parse('rows','link_row',True);
	} 

	function section_end()
	{
		global $phpgw, $phpgw_info, $admin_tpl;

		$admin_tpl->parse('rows','spacer_row',True);
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

	$phpgw->common->hook('admin');
	$admin_tpl->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>
