<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	$phpgw_info['flags'] = array(
		'currentapp' => 'admin',
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$p->set_file(array('applications' => 'applications.tpl'));
	$p->set_block('applications','list','list');
	$p->set_block('applications','row','row');

	$offset = $phpgw_info['user']['preferences']['common']['maxmatchs'];

	$phpgw->db->query("SELECT * FROM phpgw_applications WHERE app_enabled<3",__LINE__,__FILE__);
	if($phpgw->db->num_rows())
	{
		while ($phpgw->db->next_record())
		{
			$name   = $phpgw->db->f('app_name');
			$title  = $phpgw->db->f('app_title');
			$status = $phpgw->db->f('app_enabled');
			$apps[$name] = array(
				'title'  => $title,
				'name'   => $name,
				'status' => $status
			);
		}
	}
	@reset($apps);
	$total = count($apps);

	if(!$sort)
	{
		$sort = 'ASC';
	}

	if($sort == 'ASC')
	{
		ksort($apps);
	}
	else
	{
		krsort($apps);
	}

	if ($start && $offset)
	{
		$limit = $start + $offset;
	}
	elseif ($start && !$offset)
	{
		$limit = $start;
	}
	elseif(!$start && !$offset)
	{
		$limit = $total;
	}
	else
	{
		$start = 0;
		$limit = $offset;
	}

	if ($limit > $total)
	{
		$limit = $total;
	}

	$i = 0;
	$applications = array();
	while(list($app,$data) = @each($apps))
	{
		if($i >= $start && $i<= $limit)
		{
			$applications[$app] = $data;
		}
		$i++;
	}

	$p->set_var('lang_installed',lang('Installed applications'));
	$p->set_var('bg_color',$phpgw_info['theme']['bg_color']);
	$p->set_var('th_bg',$phpgw_info['theme']['th_bg']);

	$p->set_var('sort_title',$phpgw->nextmatchs->show_sort_order($sort,'title','title','/admin/applications.php',lang('Title')));
	$p->set_var('lang_showing',$phpgw->nextmatchs->show_hits($total,$start));
	$p->set_var('left',$phpgw->nextmatchs->left('/admin/applications.php',$start,$total));
	$p->set_var('right',$phpgw->nextmatchs->right('/admin/applications.php',$start,$total));

	$p->set_var('lang_edit',lang('Edit'));
	$p->set_var('lang_delete',lang('Delete'));
	$p->set_var('lang_enabled',lang('Enabled'));

	$p->set_var('new_action',$phpgw->link('/admin/newapplication.php'));
	$p->set_var('lang_add',lang('add'));

	@reset($applications);
	while (list($key,$app) = @each($applications))
	{
		$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

		if($app['title'])
		{
			$name = $app['title'];
		}
		elseif($app['name'])
		{
			$name = $app['name'];
		}
		else
		{
			$name = '&nbsp;';
		}

		$p->set_var('tr_color',$tr_color);
		$p->set_var('name',$name);

		$p->set_var('edit','<a href="' . $phpgw->link('/admin/editapplication.php','app_name=' . urlencode($app['name'])) . '"> ' . lang('Edit') . ' </a>');
		$p->set_var('delete','<a href="' . $phpgw->link('/admin/deleteapplication.php','app_name=' . urlencode($app['name'])) . '"> ' . lang('Delete') . ' </a>');

		if ($app['status'])
		{
			$status = lang('Yes');
		}
		else
		{
			$status = '<b>' . lang('No') . '</b>';
		}
		$p->set_var('status',$status);

		$p->parse('rows','row',True);
	}

	$p->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>
