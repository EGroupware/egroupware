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

	if ($submit || !$account_id)
	{
		$phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
	}

	$phpgw_info["flags"]["currentapp"] = "admin";
	include("../header.inc.php");
	// Make sure they are not attempting to delete their own account, or they have cancelled.
	// If they are, they should not reach this point anyway.
	if($submit=='Cancel' || $phpgw_info['user']['account_id'] == $account_id)
	{
		Header('Location: '.$phpgw->link('/admin/accounts.php'));
		$phpgw->common->phpgw_exit();
	}
	
	if (($account_id) && ($submit<>'Delete'))
	{
		$phpgw->template->set_file(array("form" => "delete_account.tpl"));
		
		$phpgw->template->set_var('form_action',$phpgw->link('/admin/deleteaccount.php'));
		$phpgw->template->set_var('account_id',$account_id);
		// the account can have special chars/white spaces, if it is a ldap dn
		$account_id = rawurlencode($account_id);
		
		// Find out who the new owner is of the deleted users records...
		$str = '<select name="new_owner" size="5">'."\n";;
		$users = $phpgw->accounts->get_list('accounts');
		$c_users = count($users);
		$str .= '<option value="0">Delete All Records</option>'."\n";
		for($i=0;$i<$c_users;$i++)
		{
			$str .= '<option value="'.$users[$i]['account_id'].'">'.$phpgw->common->display_fullname($users[$i]['account_lid'],$users[$i]['account_firstname',$users[$i]['account_lastname']).'</option>'."\n";
		}
		$str .= '</select>'."\n";
		$phpgw->template->set_var('lang_new_owner',lang('Who would you like to transfer ALL records owned by the deleted user to?'));
		$phpgw->template->set_var('new_owner_select',$str);
		$phpgw->template->set_var('cancel',lang('cancel'));
		$phpgw->template->set_var('delete',lang('delete'));
		$phpgw->template->pparse("out","form");

		$phpgw->common->phpgw_footer();
	}
	if($submit=='Delete')
	{
		$accountid = $account_id
		settype($account_id,'integer');
		$account_id = get_account_id($accountid);
		$lid = $phpgw->accounts->id2name($account_id);
		$phpgw->db->query('SELECT app_name FROM phpgw_applications WHERE app_enabled=1',__LINE__,__FILE__);
		if($phpgw->db->num_rows())
		{
			while($phpgw->db->next_record())
			{
				$appname = $phpgw->db->f('app_name');
				if($appname <> 'admin')
				{
					$phpgw->common->hook_single('deleteaccount', $appname);
				}
			}
		}
		
		$phpgw->common->hook_single('deleteaccount','preferences');
		$phpgw->common->hook_single('deleteaccount','admin');
		
		$sep = $phpgw->common->filesystem_separator();

		$basedir = $phpgw_info['server']['files_dir'] . $sep . 'users' . $sep;

		if (! @rmdir($basedir . $lid))
		{
			$cd = 34;
		}
		else
		{
			$cd = 29;
		}

		Header("Location: " . $phpgw->link("/admin/accounts.php","cd=$cd"));
	}
?>
