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
			$querymethod = " WHERE locale like '%".$query."%'";
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
			$querymethod .= " WHERE locale like '%".$query."%'";
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

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$templates = Array(
		'locales'      => 'locales.tpl'
	);
	$p->set_file($templates);
	$p->set_block('locales','list','list');
	$p->set_block('locales','row','row');
	$p->set_block('locales','row_empty','row_empty');
	$p->set_block('locales','submit_column','submit_column');

	$total = country_total($query);

	$var = Array(
		'th_bg'		=> $phpgw_info['theme']['th_bg'],
		'left_next_matchs'	=> $phpgw->nextmatchs->left('/calendar/'.basename($SCRIPT_FILENAME),$start,$total),
		'right_next_matchs'	=> $phpgw->nextmatchs->right('/calendar/'.basename($SCRIPT_FILENAME),$start,$total),
		'lang_groups'	=> lang('Countries'),
		'sort_name'		=> $phpgw->nextmatchs->show_sort_order($sort,'locale',$order,'/calendar/'.basename($SCRIPT_FILENAME),lang('Country')),
		'header_edit'	=> lang('Edit'),
		'header_delete'	=> lang('Delete'),
		'submit_extra'	=> '',
		'submit_link'	=> lang('Submit to Repository'),
		'back_button'	=> ''
	);

	$p->set_var($var);

	$p->parse('header_submit','submit_column',False);

	$locales = get_locale_list($sort, $order, $query, $total);

	if (! count($locales))
	{
		$p->set_var('message',lang('No matchs found'));
		$p->parse('rows','row_empty',True);
	}
	else
	{
		$p->set_var('submit_extra',' width="5%"');
		while (list(,$value) = each($locales))
		{
			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			$p->set_var('tr_color',$tr_color);

			if (! $value)  $value  = '&nbsp;';

			$var = Array(
				'tr_color'		=> $tr_color,
				'group_name'	=> $value,
				'edit_link'		=> '<a href="' . $phpgw->link('/calendar/editlocale.php','locale='.$value) . '"> ' . lang('Edit') . ' </a>',
				'delete_link'	=> '<a href="' . $phpgw->link('/calendar/deletelocale.php','locale='.$value) . '"> ' . lang('Delete') . ' </a>',
				'submit_link'	=> '<a href="' . $phpgw->link('/calendar/submitlocale.php','locale='.$value) . '"> ' . lang('Submit') . ' </a>'
			);
			$p->set_var($var);
			$p->parse('submit_link_column','submit_column',False);
			$p->parse('rows','row',True);
		}
	}

	$var = Array(
		'new_action'		=> $phpgw->link('/calendar/editholiday.php','id=0'),
		'lang_add'			=> lang('add'),
		'search_action'	=> $phpgw->link('/calendar/holiday_admin.php'),
		'lang_search'		=> lang('search')
	);

	$p->set_var($var);
	$p->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>

