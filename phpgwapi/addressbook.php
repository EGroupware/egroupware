<?php
	/**************************************************************************\
	* eGroupWare - email/addressbook                                           *
	* http://www.eGroupWare.org                                                *
	* Originaly written by Bettina Gille [ceb@phpgroupware.org]                *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',	// resolves to phpgwapi, which is not allowed itself
		'enable_nextmatchs_class' => True
	);

	include('../header.inc.php');

	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'addressbook';
	include('templates/'.$GLOBALS['phpgw_info']['user']['preferences']['common']['template_set'].'/head.inc.php');

	$GLOBALS['phpgw']->template->set_file(array(
		'addressbook_list_t' => 'addressbook.tpl',
	));
	$GLOBALS['phpgw']->template->set_block('addressbook_list_t','addressbook_list','list');

	$contacts = CreateObject('phpgwapi.contacts');
	$cats = CreateObject('phpgwapi.categories');
	$cats->app_name = 'addressbook';
	
	$include_personal = True;

	$GLOBALS['phpgw']->template->set_var(array(
		'lang_search' => lang('Search'),
		'lang_select_cats' => lang('Show all categorys'),
		'lang_done' => lang('Done'),
		'to' => lang('To'),
		'cc' => lang('Cc'),
		'bcc' => lang('Bcc'),
		'lang_email' => lang('Select work email address'),
		'lang_hemail' => lang('Select home email address'),
	));


	$start  = intval(get_var('start',array('POST','GET'),0));
	$filter = get_var('filter',array('POST','GET'),'none');
	$cat_id = intval(get_var('cat_id',array('POST','GET'),0));
	$query  = get_var('query',array('POST','GET'));
	$sort   = get_var('sort',array('POST','GET'));
	$order  = get_var('order',array('POST','GET'));

	$common_vars = array(
		'filter' => $filter,
		'cat_id' => $cat_id,
		'query'  => $query,
		'sort'   => $sort,
		'order'  => $order,
	);

	$link = '/phpgwapi/addressbook.php';
	$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link($link,$common_vars+array(
		'start' => $start,
	)));

	$qfilter = 'tid=n';
	switch($filter)
	{
		case 'none':
			break;
		case 'private':
			$qfilter .=',access=private';
			// fall-through
		case 'yours':
			$qfilter .= ',owner='.$GLOBALS['phpgw_info']['user']['account_id'];
			break;
		default:
			if(is_numeric($filter))
			{
				$qfilter = ',owner='.$filter;
			}
			break;
	}

	if ($cat_id)
	{
		$qfilter  .= ',cat_id='.$cat_id;
	}

	if ($GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'] > 0)
	{
		$offset = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
	}
	else
	{
		$offset = 15;
	}

	$account_id = $GLOBALS['phpgw_info']['user']['account_id'];

	$cols = array (
		'n_given'    => 'n_given',
		'n_family'   => 'n_family',
		'org_name'   => 'org_name',
		'email'      => 'email',
		'email_home' => 'email_home'
	);

	$entries = $contacts->read($start,$offset,$cols,$query,$qfilter,$sort,$order,$account_id);

	//------------------------------------------- nextmatch --------------------------------------------
	$GLOBALS['phpgw']->template->set_var('left',$GLOBALS['phpgw']->nextmatchs->left(
		$link,$start,$contacts->total_records,'&'.explode('&',$common_vars)));
	$GLOBALS['phpgw']->template->set_var('right',$GLOBALS['phpgw']->nextmatchs->right(
		$link,$start,$contacts->total_records,'&'.explode('&',$common_vars)));
	foreach(array(
		'n_given'  => lang('Firstname'),
		'n_family' => lang('Lastname'),
		'org_name' => lang('Company'),
	) as $col => $translation)
	{
		$GLOBALS['phpgw']->template->set_var('sort_'.$col,$GLOBALS['phpgw']->nextmatchs->show_sort_order(
			$sort,$col,$order,$link,$translation,'&cat_id='.$cat_id));
	}

	if ($contacts->total_records > $offset)
	{
		$GLOBALS['phpgw']->template->set_var('lang_showing',lang('showing %1 - %2 of %3',
			1+$start,$start+$offset>$contacts->total_records ? $contacts->total_records : $start+$offset,
			$contacts->total_records));
	}

	else
	{
		$GLOBALS['phpgw']->template->set_var('lang_showing',lang('showing %1',$contacts->total_records));
	}
	// --------------------------------------- end nextmatch ------------------------------------------

	// ------------------- list header variable template-declaration -----------------------
	$GLOBALS['phpgw']->template->set_var('cats_list',$cats->formated_list('select','all',$cat_id,'True'));

	$filter_list = '';
	foreach(array(
		'none'    => lang('Show all'),
		'yours'   => lang('Only yours'),
		'private' => lang('Only private'),
	) as $id => $translation)
	{
		$filter_list .= "<option value=\"$id\"".($filter == $id ? ' selected':'').">$translation</option>\n";
	}
	$GLOBALS['phpgw']->template->set_var(array(
		'query' => $query,
		'filter_list' => $filter_list,
	));
	// --------------------------- end header declaration ----------------------------------

	for ($i=0;$i<count($entries);$i++)
	{
		$GLOBALS['phpgw']->template->set_var('tr_class',
			$GLOBALS['phpgw']->nextmatchs->alternate_row_color('',True));

		$firstname = $entries[$i]['n_given'];
		if (!$firstname)
		{
			$firstname = '&nbsp;';
		}
		$lastname = $entries[$i]['n_family'];
		if (!$lastname)
		{
			$lastname = '&nbsp;';
		}
		// thanks to  dave.hall@mbox.com.au for adding company
		$company = $entries[$i]['org_name'];
		if (!$company)
		{
			$company = '&nbsp;';
		}
		
		$personal_firstname = '';
		$personal_lastname = '';
		$personal_part = '';
		if ((isset($firstname)) &&
			($firstname != '') &&
			($firstname != '&nbsp;'))
		{
			$personal_firstname = $firstname.' ';
		}
		if ((isset($lastname)) &&
			($lastname != '') &&
			($lastname != '&nbsp;'))
		{
			$personal_lastname = $lastname;
		}
		$personal_part = $personal_firstname.$personal_lastname;
		
		if (($personal_part == '') ||
			($include_personal == False))
		{
			$id     = $entries[$i]['id'];
			$email  = $entries[$i]['email'];
			$hemail = $entries[$i]['email_home'];
		}
		else
		{
			$id = $entries[$i]['id'];
			if ((isset($entries[$i]['email'])) &&
				(trim($entries[$i]['email']) != ''))
			{
				$email  = '&quot;'.$personal_part.'&quot; &lt;'.$entries[$i]['email'].'&gt;';
			}
			else
			{
				$email  = $entries[$i]['email'];
			}
			if ((isset($entries[$i]['email_home'])) &&
			(trim($entries[$i]['email_home']) != ''))
			{
				$hemail = '&quot;'.$personal_part.'&quot; &lt;'.$entries[$i]['email_home'].'&gt;';
			}
			else
			{
				$hemail = $entries[$i]['email_home'];
			}
		}
		
		// --------------------- template declaration for list records --------------------------
		$GLOBALS['phpgw']->template->set_var(array(
			'firstname' => $firstname,
			'lastname'  => $lastname,
			'company'	=> $company
		));

		$GLOBALS['phpgw']->template->set_var('id',$id);
		$GLOBALS['phpgw']->template->set_var('email',$email);
		$GLOBALS['phpgw']->template->set_var('hemail',$hemail);

		$GLOBALS['phpgw']->template->parse('list','addressbook_list',True);
	}
	// --------------------------- end record declaration ---------------------------

	$GLOBALS['phpgw']->template->parse('out','addressbook_list_t',True);
	$GLOBALS['phpgw']->template->p('out');

	$GLOBALS['phpgw']->common->phpgw_exit();
?>
