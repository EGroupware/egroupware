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
		'lang_to_title' => lang('Select all %1 %2 for %3')
	));

	$start  = intval(get_var('start',array('POST','GET'),0));
	$filter = get_var('filter',array('POST','GET'),'none');
	$cat_id = intval(get_var('cat_id',array('POST','GET'),0));
	$query  = get_var('query',array('POST','GET'));
	$sort   = get_var('sort',array('POST','GET'));
	$order  = get_var('order',array('POST','GET'));
	list($all) = @each($_POST['all']);
	$inserted = $_GET['inserted'];

	$common_vars = array(
		'filter' => $filter,
		'cat_id' => $cat_id,
		'query'  => $query,
		'sort'   => $sort,
		'order'  => $order,
	);

	$link = '/phpgwapi/addressbook.php';
	$full_link = $GLOBALS['phpgw']->link($link,$common_vars+array(
		'start' => $start,
	));
	$GLOBALS['phpgw']->template->set_var('form_action',$full_link);

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

	if ($all)
	{
		$qfilter .= ',email'.($all[0] == 'h' ? '_home' : '')."=!''";
		$entries = $contacts->read(0,0,$cols,$query,$qfilter,$sort,$order,$account_id);
		//echo "<pre>".print_r($entries,True)."</pre>\n";
		if (!$entries)
		{
			$all = False;
			$inserted = 0;
		}
	}
	if (!$all)
	{
		$entries = $contacts->read($start,$offset,$cols,$query,$qfilter,$sort,$order,$account_id);
	}
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

	foreach(array(
		'work' => lang('work email'),
		'home' => lang('home email')
	) as $type => $lang_type)
	{
		foreach(array(
			'to' => lang('To'),
			'cc' => lang('Cc'),
			'bcc'=> lang('Bcc')) as $target => $lang_target)
		{
			$GLOBALS['phpgw']->template->set_var('title_'.$type.'_'.$target,
				lang('Insert all %1 addresses of the %2 contacts in %3',$lang_type,
					$contacts->total_records,$lang_target));
		}
	}

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

	$all_emails = array();
	if ($entries)
	foreach ($entries as $entry)
	{
		$GLOBALS['phpgw']->template->set_var('tr_class',
			$GLOBALS['phpgw']->nextmatchs->alternate_row_color('',True));

		$firstname = $entry['n_given'];
		if (!$firstname)
		{
			$firstname = '&nbsp;';
		}
		$lastname = $entry['n_family'];
		if (!$lastname)
		{
			$lastname = '&nbsp;';
		}
		// thanks to  dave.hall@mbox.com.au for adding company
		$company = $entry['org_name'];
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
			$id     = $entry['id'];
			$email  = $entry['email'];
			$hemail = $entry['email_home'];
		}
		else
		{
			$id = $entry['id'];
			if ((isset($entry['email'])) &&
				(trim($entry['email']) != ''))
			{
				$email  = '"'.$personal_part.'" <'.$entry['email'].'>';
			}
			else
			{
				$email  = $entry['email'];
			}
			if ((isset($entry['email_home'])) &&
			(trim($entry['email_home']) != ''))
			{
				$hemail = '"'.$personal_part.'" <'.$entry['email_home'].'>';
			}
			else
			{
				$hemail = $entry['email_home'];
			}
		}
		if ($all)
		{
			$all_emails[] = $all[0] == 'h' ? $hemail : $email;
		}
		else
		{
			$email = htmlspecialchars($email);
			$hemail = htmlspecialchars($hemail);

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
	}
	// --------------------------- end record declaration ---------------------------

	if ($all && count($all_emails))
	{
		$full_link .= '&inserted='.count($all_emails);
		$target = substr($all,1);
		echo "<script type=\"text/javascript\">
			if (opener.document.doit.$target.value != '')
			{
				opener.document.doit.$target.value += ',';
			}
			opener.document.doit.$target.value += '".str_replace("'","\\'",implode(',',$all_emails))."';
			window.location.href = '$full_link';
		</script>
		</body>
		</html>\n";
	}
	else
	{
		if ($inserted || $inserted === 0)
		{
			$GLOBALS['phpgw']->template->set_var('message','<b>'.
				lang('%1 email addresses inserted',intval($_GET['inserted'])).'</b>');
		}
		$GLOBALS['phpgw']->template->parse('out','addressbook_list_t',True);
		$GLOBALS['phpgw']->template->p('out');
	}
	$GLOBALS['phpgw']->common->phpgw_exit();
?>
