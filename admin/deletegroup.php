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
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'admin'
	);

	if (! $group_id)
	{
		Header('Location: ' . $phpgw->link('/admin/groups.php'));
	}
	include('../header.inc.php');

	$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
	$p->set_file(array(
		'body' => 'delete_common.tpl',
		'message_row' => 'message_row.tpl'
	));

	if ((($group_id) && ($confirm)) || $removeusers)
	{
		if ($removeusers)
		{
			$old_group_list = $phpgw->acl->get_ids_for_location(intval($group_id),1,'phpgw_group');
			@reset($old_group_list);
			while($old_group_list && $id = each($old_group_list))
			{
				$phpgw->acl->delete_repository('phpgw_group',$group_id,intval($id[1]));
			}
		}

		$group_name = $phpgw->accounts->id2name($group_id);

		$old_group_list = $phpgw->acl->get_ids_for_location(intval($group_id),1,'phpgw_group');
		if ($old_group_list)
		{
			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$p->set_var('message_display','<tr><td>'
				. lang('Sorry, the follow users are still a member of the group x',$group_name)
				. '<br>' . lang('They must be removed before you can continue') . '</td></td>');
			$p->parse('messages','message_row',True);

			$p->set_var('message_display','<tr><td><table border="0">');
			$p->parse('messages','message_row',True);

			while (list(,$id) = each($old_group_list))
			{
				$p->set_var('message_display','<tr><td><a href="' . $phpgw->link('/admin/editaccount.php','account_=' . $id) . '">' . $phpgw->common->grab_owner_name($id) . '</a></tr></td>');
				$p->parse('messages','message_row',True);
			}
			$p->set_var('message_display','</table></center></td></tr><tr><td>'
				. '<a href="' . $phpgw->link('/admin/deletegroup.php','group_id=' . $group_id . '&removeusers=True')
				. '">' . lang('Remove all users from this group') . '</a></td></tr>');
			$p->parse('messages','message_row',True);
			$p->set_var('yes','');
			$p->set_var('no','');
			$p->pparse('out','body');
			$phpgw->common->phpgw_footer();
			$phpgw->common->phpgw_exit();
		}
		elseif ($removeusers && !$confirm)
		{
		 	Header('Location: ' . $phpgw->link('/admin/deletegroup.php','group_id='.$group_id.'&confirm=True'));
			$phpgw->common->phpgw_exit();
		}

		if ($confirm)
		{
			$phpgw->db->lock(array('phpgw_accounts','phpgw_acl'));
			$phpgw->db->query('DELETE FROM phpgw_accounts WHERE account_id='.$group_id,__LINE__,__FILE__);
			$phpgw->acl->delete_repository('%%','run',intval($group_id));

			$basedir = $phpgw_info['server']['files_dir'] . SEP . 'groups' . SEP;

			if (! @rmdir($basedir . $group_name))
			{
				$cd = 38;
			}
			else
			{
				$cd = 32;
			}

			$phpgw->db->unlock();

			Header('Location: ' . $phpgw->link('/admin/groups.php','cd='.$cd));
			$phpgw->common->phpgw_exit();
		}
	}
	else
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		$p->set_var('message_display',lang('Are you sure you want to delete this group ?'));
		$p->parse('messages','message_row');
		$p->set_var('yes','<a href="' . $phpgw->link('/admin/deletegroup.php',"group_id=$group_id&confirm=true") . '">' . lang('Yes') . '</a>');
		$p->set_var('no','<a href="' . $phpgw->link('/admin/groups.php') . '">' . lang('No') . '</a>');

		$p->pparse('out','body');

		$phpgw->common->phpgw_footer();
	}
?>
