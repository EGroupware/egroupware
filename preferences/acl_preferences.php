<?php
	/**************************************************************************\
	* phpGroupWare - Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_flags = Array(
		'currentapp'               => $acl_app,
		'enable_nextmatchs_class'  => True,
		'noappheader'              => True,
		'noappfooter'              => True
  	);

	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	function check_acl($label,$id,$acl,$rights,$right)
	{
		global $phpgw_info, $p;

		$p->set_var($acl,$label.$phpgw_info['flags']['currentapp'].'['.$id.']['.$right.']');
		if ($rights & $right)
		{
			$p->set_var($acl.'_selected',' checked');
		}
		else
		{
			$p->set_var($acl.'_selected','');
		}
	}
  

	function display_row($bg_color,$label,$id,$name)
	{
		global $phpgw_info, $acl, $p;
    
		$p->set_var('row_color',$bg_color);
		$p->set_var('user',$name);
		$rights = $acl->get_rights($id,$phpgw_info['flags']['currentapp']);

		check_acl($label,$id,'read',$rights,PHPGW_ACL_READ);
		check_acl($label,$id,'add',$rights,PHPGW_ACL_ADD);
		check_acl($label,$id,'edit',$rights,PHPGW_ACL_EDIT);
		check_acl($label,$id,'delete',$rights,PHPGW_ACL_DELETE);
		check_acl($label,$id,'private',$rights,PHPGW_ACL_PRIVATE);
    
		$p->parse('row','acl_row',True);
	}

	if(!isset($owner) || !$phpgw_info['user']['apps']['admin'])
	{
		$owner = $phpgw_info['user']['account_id'];
	}

	$acct = CreateObject('phpgwapi.accounts',$owner);
	$groups = $acct->get_list('groups');
	$users = $acct->get_list('accounts');
	unset($acct);
	$acl = CreateObject('phpgwapi.acl',intval($owner));
	$acl->read_repository();
  
	if ($submit)
	{
		$to_remove = unserialize(urldecode($processed));
		
		for($i=0;$i<count($to_remove);$i++)
		{
			$acl->delete($phpgw_info['flags']['currentapp'],$to_remove[$i]);
		}
		
		// Group records
		$group_variable = 'g_'.$phpgw_info['flags']['currentapp'];

		if (!$$group_variable)
		{
			$$group_variable = array();
		}
		@reset($$group_variable);
		while(list($group_id,$acllist) = each($$group_variable))
		{
			$totalacl = 0;
			while(list($right,$permission) = each($acllist))
			{
				$totalacl += $right;
			}
			$acl->add($phpgw_info['flags']['currentapp'],$group_id,$totalacl);
		}

		// User records
		$user_variable = 'u_'.$phpgw_info['flags']['currentapp'];

		if (!$$user_variable)
		{
			$$user_variable = array();
		}
		@reset($$user_variable);
		while(list($user_id,$acllist) = each($$user_variable))
		{
			$totalacl = 0;
			while(list($right,$permission) = each($acllist))
			{
				$totalacl += $right;
			}
			$acl->add($phpgw_info['flags']['currentapp'],$user_id,$totalacl);
		}
		$acl->save_repository();
	}

	$processed = Array();

	$total = 0;

	if(!isset($start))
	{
		$start = 0;
	}

	if(!$start)
	{
		$s_groups = 0;
		$s_users = 0;
	}
  
	if(!isset($s_groups))
	{
		$s_groups = 0;
	}

	if(!isset($s_users))
	{
		$s_users = 0;
	}

	if(!isset($query))
	{
		$query = "";
	}

	if(!isset($maxm))
	{
		$maxm = $phpgw_info['user']['preferences']['common']['maxmatchs'];
	}

	if(!isset($totalentries))
	{
		$totalentries = count($groups) + count($users);
		if($totalentries < $maxm)
		{
			$maxm = $totalentries;
		}
	}

	$p = CreateObject('phpgwapi.Template',$phpgw_info['server']['app_tpl']);
	$templates = Array (
		'preferences'	=> 'preference_acl.tpl',
		'row_colspan'	=> 'preference_colspan.tpl',
		'acl_row'	=> 'preference_acl_row.tpl'
	);

	$p->set_file($templates);

	$common_hidden_vars = '     <input type="hidden" name="s_groups" value="'.$s_groups.'">'."\n"
		. '     <input type="hidden" name="s_users" value="'.$s_users.'">'."\n"
		. '     <input type="hidden" name="maxm" value="'.$maxm.'">'."\n"
		. '     <input type="hidden" name="totalentries" value="'.$totalentries.'">'."\n"
		. '     <input type="hidden" name="start" value="'.$start.'">'."\n"
		. '     <input type="hidden" name="query" value="'.$query.'">'."\n"
		. '     <input type="hidden" name="owner" value="'.$owner.'">'."\n";

	$var = Array(
		'errors'			=> '',
		'title'				=> '<p><b>'.lang($phpgw_info['flags']['currentapp'].' preferences').' - '.lang('acl').':</b><hr><p>',
		'action_url'			=> $phpgw->link('/preferences/acl_preferences.php','acl_app=' . $acl_app),
		'bg_color'			=> $phpgw_info['theme']['th_bg'],
		'submit_lang'			=> lang('submit'),
		'common_hidden_vars_form'	=> $common_hidden_vars
	);
	
	$p->set_var($var);

	if(isset($query_result) && $query_result)
	{
		$common_hidden_vars .= '<input type="hidden" name="query_result" value="'.$query_result.'">'."\n";
	}

	$p->set_var('common_hidden_vars',$common_hidden_vars);

	$var = Array(
		'read_lang'	=> lang('Read'),
		'add_lang'	=> lang('Add'),
		'edit_lang'	=> lang('Edit'),
		'delete_lang'	=> lang('Delete')
	);
	
	$p->set_var($var);
	$p->set_var('private_lang',lang('Private'));

	if(intval($s_groups) <> count($groups))
	{
		$p->set_var('string',lang('Groups'));
		$p->parse('row','row_colspan',True);

		reset($groups);
		for($k=0;$k<count($groups);$k++)
		{
			$group = $groups[$k];
			$go = True;
			
			if($query)
			{
				if(!strpos(' '.$group['account_lid'].' ',$query))
				{
					$go = False;
				}
			}
			
			if($go)
			{
				$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
				display_row($tr_color,'g_',$group['account_id'],$group['account_lid']);
				$s_groups++;
				$processed[] = $group['account_id'];
				$total++;
				if($total == $maxm)
				{
					break;
				}
			}
		}
	}

	if($total <> $maxm)
	{
		if($users)
		{
			$p->set_var('string',ucfirst(lang('Users')));
			$p->parse('row','row_colspan',True);
			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			for($k=$s_users;$k<$total || $k==count($users);$k++)
			{
				$user = $users[$k];
				$go = True;
				if($query)
				{
					$name = ' '.$user['account_firstname'].' '.$user['account_lastname'].' '.$user['account_lid'].' ';
					if(!strpos($name,$query))
					{
						$go = False;
					}
				}

				if($go)
				{
					$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
					display_row($tr_color,'u_',$user['account_id'],$phpgw->common->display_fullname($user['account_lid'],$user['account_firstname'],$user['account_lastname']));
					$s_users++;
					$processed[] = $user['account_id'];
					$total++;
					if($total == $maxm)
					{
						break;
					}
				}
			}
		}
	}

	$extra_parms = '&acl_app=' . $acl_app
		. '&s_users='.$s_users.'&s_groups='.$s_groups
		. '&maxm=' . $maxm . '&totalentries=' . $totalentries
		. '&total=' . ($start + $total) . '&owner='.$owner;

	$var = Array(
		'nml'		=> $phpgw->nextmatchs->left('/preferences/acl_preferences.php',$start,$totalentries,$extra_parms),
		'nmr'		=> $phpgw->nextmatchs->right('/preferences/acl_preferences.php',$start,$totalentries,$extra_parms),
		'search_value'	=> (isset($query) && $query?$query:''),
		'search'	=> lang('search'),
		'processed'	=> urlencode(serialize($processed))
	);
	
	$p->set_var($var);

	$p->pfp('out','preferences');
	$phpgw->common->phpgw_footer();
?>
