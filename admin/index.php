<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* Written by coreteam <phpgroupware-developers@gnu.org>                    *
	*           & Stephen Brown <steve@dataclarity.net>                        *
	* to distribute admin across the application directories                   *
	* ------------------------------------------------------                   *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU General Public License as published by the    *
	* Free Software Foundation; either version 2 of the License, or (at your   *
	* option) any later version.                                               *
	\**************************************************************************/
	/* $Id$ */

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	$GLOBALS['phpgw']->template->set_file(Array('admin' => 'index.tpl'));

	$GLOBALS['phpgw']->template->set_block('admin','list');
	$GLOBALS['phpgw']->template->set_block('admin','app_row');
	$GLOBALS['phpgw']->template->set_block('admin','app_row_noicon');
	$GLOBALS['phpgw']->template->set_block('admin','link_row');
	$GLOBALS['phpgw']->template->set_block('admin','spacer_row');

	$GLOBALS['phpgw']->template->set_var('title',lang('Administration'));

	// This func called by the includes to dump a row header
	function section_start($appname='',$icon='')
	{
		$GLOBALS['phpgw']->template->set_var('app_title',$GLOBALS['phpgw_info']['apps'][$appname]['title']);
		$GLOBALS['phpgw']->template->set_var('app_name',$appname);
		$GLOBALS['phpgw']->template->set_var('app_icon',$icon);
		if ($icon)
		{
			$GLOBALS['phpgw']->template->parse('rows','app_row',True);
		}
		else
		{
			$GLOBALS['phpgw']->template->parse('rows','app_row_noicon',True);
		}
	}

	function section_item($pref_link='',$pref_text='')
	{
		$GLOBALS['phpgw']->template->set_var('pref_link',$pref_link);
		$GLOBALS['phpgw']->template->set_var('pref_text',$pref_text);
		$GLOBALS['phpgw']->template->parse('rows','link_row',True);
	}

	function section_end()
	{
		$GLOBALS['phpgw']->template->parse('rows','spacer_row',True);
	}

	function display_section($appname,$file,$file2=False)
	{
		if ($file2)
		{
			$file = $file2;
		}
		if(is_array($file))
		{
			section_start($appname,
				$GLOBALS['phpgw']->common->image($appname,'navbar','',True)
			);

			while(is_array($file) && list($text,$url) = each($file))
			{
				section_item($url,lang($text));
			}
			section_end();
		}
	}

	$GLOBALS['phpgw']->hooks->process('admin');
	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array
	(
		'body_data' => $GLOBALS['phpgw']->template->parse('out','list')
	));
?>
