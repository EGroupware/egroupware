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
	$pref_tpl->set_file(array(
		'T_icon_cell' => 'index_icon_cell.tpl',
		'T_link_cell' => 'index_link_cell.tpl',	
		'index_out' => 'index.tpl',
	));

	// This func called by the includes to dump a row header
	function section_start($name='',$icon='')
	{
		global $phpgw,$phpgw_info, $loopnum, $pref_tpl;
		
		$pref_tpl->set_var('icon_backcolor',$phpgw_info["theme"]["row_off"]);
		$pref_tpl->set_var('link_backcolor',$phpgw_info["theme"]["row_off"]);
		$pref_tpl->set_var('app_name',lang($name));
		$pref_tpl->set_var('app_icon',$icon);
		if ($icon)
		{
			$pref_tpl->parse('V_icon_cell','T_icon_cell');
		}
		else
		{
			$pref_tpl->set_var('V_icon_cell','&nbsp;');
		} 

		// prepare an iteration variable for section_item to know when to add a <br>
		$loopnum = 1;
	}

	function section_item($pref_link='',$pref_text='')
	{
		global $phpgw,$phpgw_info, $loopnum, $pref_tpl;
		if ($loopnum > 1)
		{
			$pref_tpl->set_var('insert_br','<br>');
		}
		else
		{
			$pref_tpl->set_var('insert_br','');
		}

		$pref_tpl->set_var('pref_link',$pref_link);
		$pref_tpl->set_var('pref_text',$pref_text);		
		$pref_tpl->parse('V_link_cell','T_link_cell',True);
		$loopnum = $loopnum + 1;
	} 

	function section_end()
	{
		global $phpgw,$phpgw_info, $pref_tpl;
		$pref_tpl->pparse('out','index_out');
		$pref_tpl->set_var('V_icon_cell','');
		$pref_tpl->set_var('V_link_cell','');
	}

	$phpgw->common->hook();

	$phpgw->common->phpgw_footer();
?>