<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	$phpgw_flags = Array(
		'currentapp'		=> 'calendar',
		'enable_nextmatchs_class'	=> True,
		'admin_header'		=>	True,
		'noappheader'		=> True,
		'noappfooter'		=> True,
		'parent_page'		=> '../admin/index.php'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	if(!isset($start))
	{
		$start = 0;
	}
	
	function country_total($query)
	{
		global $phpgw;

		$querymethod='';
		if($query)
		{
			$querymethod = "WHERE locale='".$query."'";
		}
		$phpgw->db->query("SELECT DISTINCT locale FROM phpgw_cal_holidays".$querymethod,__LINE__,__FILE__);
		$count = 0;
		while($phpgw->db->next_record())
		{
			$count++;
		}
		return $count;
	}

	function get_locale_list($sort, $order, $query, $total)
	{
		global $phpgw;
		
		$querymethod = '';

		if($query)
		{
			$querymethod .= " WHERE locale='".$query."'";
		}
		
		if($order)
		{
			$querymethod .= ' ORDER BY '.$order;
		}
		$phpgw->db->query("SELECT DISTINCT locale FROM phpgw_cal_holidays".$querymethod,__LINE__,__FILE__);
		while($phpgw->db->next_record())
		{
			$locale[] = $phpgw->db->f('locale');
		}
		return $locale;
	}

	if($query)
	{
		$query = str_replace('=',"='",$query)."'";
	}

	$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
	$templates = Array(
		'group'      => 'groups.tpl'
	);
	$p->set_file($templates);
	$p->set_block('group','list','list');
	$p->set_block('group','row','row');
	$p->set_block('group','row_empty','row_empty');

	$total = country_total($query);
 
	$p->set_var('th_bg',$phpgw_info['theme']['th_bg']);

	$p->set_var('left_next_matchs',$phpgw->nextmatchs->left('/calendar/'.basename($SCRIPT_FILENAME),$start,$total));
	$p->set_var('right_next_matchs',$phpgw->nextmatchs->right('/calendar/'.basename($SCRIPT_FILENAME),$start,$total));
	$p->set_var('lang_groups',lang('Countries'));

	$p->set_var('sort_name',$phpgw->nextmatchs->show_sort_order($sort,'locale',$order,'/calendar/'.basename($SCRIPT_FILENAME),lang('Country')));
	$p->set_var('header_edit',lang('Edit'));
	$p->set_var('header_delete',lang('Delete'));

	$locales = get_locale_list($sort, $order, $query, $total);

	if (! count($locales))
	{
		$p->set_var('message',lang('No matchs found'));
		$p->parse('rows','row_empty',True);
	}
	else
	{
		while (list(,$value) = each($locales))
		{
			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			$p->set_var('tr_color',$tr_color);

			if (! $value)  $value  = '&nbsp;';

			$p->set_var('group_name',$value); 
			$p->set_var("edit_link",'<a href="' . $phpgw->link('/calendar/editlocale.php','locale='.$value) . '"> ' . lang('Edit') . ' </a>');
			$p->set_var("delete_link",'<a href="' . $phpgw->link('/calendar/deletelocale.php','locale='.$value) . '"> ' . lang('Delete') . ' </a>');
			$p->parse('rows','row',True);

		}
	}

	$p->set_var('new_action',$phpgw->link('/calendar/new_locale.php'));
	$p->set_var("lang_add",lang('add'));

	$p->set_var('search_action',$phpgw->link('/calendar/holiday_admin.php'));
	$p->set_var('lang_search',lang('search'));

	$p->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>

