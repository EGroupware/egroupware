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
  $phpgw_info['flags'] = array('currentapp' => 'admin', 'enable_nextmatchs_class' => True);  
  include('../header.inc.php');
  include(PHPGW_APP_INC . '/accounts_' . $phpgw_info['server']['account_repository'] . '.inc.php');

  $p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
  
	$p->set_file(array(
		'list'       => 'accounts.tpl',
		'row'        => 'accounts_row.tpl',
		'empty_row'  => 'accounts_row_empty.tpl'
	));

  $total = account_total();

  $p->set_var('bg_color',$phpgw_info['theme']['bg_color']);
  $p->set_var('th_bg',$phpgw_info['theme']['th_bg']);

  $p->set_var('left_next_matchs',$phpgw->nextmatchs->left('accounts.php',$start,$total));
  $p->set_var('lang_user_accounts',lang('user accounts'));
  $p->set_var('right_next_matchs',$phpgw->nextmatchs->right('accounts.php',$start,$total));

  $p->set_var('lang_loginid',$phpgw->nextmatchs->show_sort_order($sort,'account_lid',$order,'accounts.php',lang('LoginID')));
  $p->set_var('lang_lastname',$phpgw->nextmatchs->show_sort_order($sort,'account_lastname',$order,'accounts.php',lang('last name')));
  $p->set_var('lang_firstname',$phpgw->nextmatchs->show_sort_order($sort,'account_firstname',$order,'accounts.php',lang('first name')));

  $p->set_var('lang_edit',lang('Edit'));
  $p->set_var('lang_delete',lang('Delete'));
  $p->set_var('lang_view',lang('View'));

	$account_info = $phpgw->accounts->get_list('accounts',$start,$sort, $order, $query);

	if (! count($account_info))
	{
		$p->set_var('message',lang('No matchs found'));
		$p->parse('rows','empty_row',True);
	}
	else
	{

		while (list($null,$account) = each($account_info))
		{
	
			$lastname   = $account['account_lastname'];
			$firstname  = $account['account_firstname'];
			$account_id = $account['account_id'];
			$loginid    = $account['account_lid'];
	
			$phpgw->nextmatchs->template_alternate_row_color(&$p);
	
			if (! $lastname)
			{
				$lastname  = '&nbsp;';
			}
	
			if (! $firstname)
			{
				$firstname = '&nbsp;';
			}
	
			$p->set_var('row_loginid',$loginid);
			$p->set_var('row_firstname',$firstname);
			$p->set_var('row_lastname',$lastname);
			$p->set_var('row_edit','<a href="'.$phpgw->link('/admin/editaccount.php','account_id='
	       				     . $account_id) . '"> ' . lang('Edit') . ' </a>');
	
			if ($phpgw_info['user']['userid'] != $account['account_lid'])
			{
				$p->set_var('row_delete','<a href="' . $phpgw->link('/admin/deleteaccount.php','account_id='
	      						. $account_id) . '"> '.lang('Delete').' </a>');
			}
			else
			{
				$p->set_var('row_delete','&nbsp;');
			}
	
			$p->set_var('row_view','<a href="' . $phpgw->link("/admin/viewaccount.php", "account_id="
	   				         . $account_id) . '"> ' . lang("View") . ' </a>');
	
			$p->parse('rows','row',True);
		}
	}		// End else

	$p->set_var('actionurl',$phpgw->link("/admin/newaccount.php"));
	$p->set_var('accounts_url',$phpgw->link("/admin/accounts.php"));
	$p->set_var('lang_add',lang("add"));
	$p->set_var('lang_search',lang("search"));

	$p->pparse('out','list');

	$phpgw->common->phpgw_footer();
?>
