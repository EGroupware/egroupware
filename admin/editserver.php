<?php
  /**************************************************************************\
  * phpGroupWare - Admin                                                     *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
  /* $Id$ */

	$phpgw_info["flags"]["currentapp"] = 'admin';
	include('../header.inc.php');

	if (!$server_id)
	{
		Header('Location: ' . $phpgw->link('/admin/servers.php',"sort=$sort&order=$order&query=$query&start=$start"
			. "&filter=$filter"));
	}

	$phpgw->t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$phpgw->template->set_file(array('form' => 'server_form.tpl'));
	$phpgw->template->set_block('form','add','addhandle');
	$phpgw->template->set_block('form','edit','edithandle');

	function formatted_list($name,$list,$id='',$default=False,$java=False)
	{
		$select  = "\n" .'<select name="' . $name . '"' . ">\n";
		if($default)
		{
			$select .= '<option value="">' . lang('Please Select') . '</option>'."\n";
		}
		while (list($val,$key) = each($list))
		{
			$select .= '<option value="' . $key . '"';
			if ($key == $id && $id != '')
			{
				$select .= ' selected';
			}
			$select .= '>' . lang($val) . '</option>'."\n";
		}

		$select .= '</select>'."\n";

		return $select;
	}

	$is = CreateObject('phpgwapi.interserver',$server_id);
	$server = $is->read_repository($server_id);

	$hidden_vars = "<input type=\"hidden\" name=\"sort\" value=\"$sort\">\n"
		. "<input type=\"hidden\" name=\"order\" value=\"$order\">\n"
		. "<input type=\"hidden\" name=\"query\" value=\"$query\">\n"
		. "<input type=\"hidden\" name=\"start\" value=\"$start\">\n"
		. "<input type=\"hidden\" name=\"filter\" value=\"$filter\">\n"
		. "<input type=\"hidden\" name=\"server_id\" value=\"$server_id\">\n";

	if ($submit)
	{
		$errorcount = 0;

		$tmp = $is->name2id($server_name);
		if(0)
		{
			$error[$errorcount++] = lang('That server name has been used already !');
		}

		if (!$server_name)
		{
			$error[$errorcount++] = lang('Please enter a name for that server !');
		}

		if (!$error)
		{
			$server_info = array(
				'server_name' => addslashes($server_name),
				'server_url'  => addslashes($server_url),
				'trust_level' => intval($trust_level),
				'trust_rel'   => intval($trust_rel),
				'username'    => addslashes($server_username),
				'password'    => $server_password ? $server_password : $server['password'],
				'server_mode' => addslashes($server_mode),
				'server_security' => addslashes($server_security),
				'admin_name'  => addslashes($admin_name),
				'admin_email' => addslashes($admin_email)
			);

			$is->server = $server_info;
			$is->save_repository($server_id);
			$server = $is->read_repository($server_id);
		}
	}

	if ($errorcount)
	{
		$phpgw->template->set_var('message',$phpgw->common->error_list($error));
	}
	if (($submit) && (!$error) && (!$errorcount))
	{
		$phpgw->template->set_var('message',lang("Server x has been updated !",$server_name));
	}
	if ((!$submit) && (!$error) && (!$errorcount))
	{
		$phpgw->template->set_var('message','');
	}

	$phpgw->template->set_var('title_servers',lang('Edit Peer Server'));
	$phpgw->template->set_var('actionurl',$phpgw->link('/admin/editserver.php'));
	$phpgw->template->set_var('deleteurl',$phpgw->link('/admin/deleteserver.php',"server_id=$server_id&start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));
	$phpgw->template->set_var('doneurl',$phpgw->link('/admin/servers.php',"start=$start&query=$query&sort=$sort&order=$order&filter=$filter"));

	$phpgw->template->set_var('lang_name',lang('Server name'));
	$phpgw->template->set_var('lang_url',lang('Server URL'));
	$phpgw->template->set_var('lang_trust',lang('Trust Level'));
	$phpgw->template->set_var('lang_relationship',lang('Trust Relationship'));
	$phpgw->template->set_var('lang_username',lang('Server Username'));
	$phpgw->template->set_var('lang_password',lang('Server Password'));
	$phpgw->template->set_var('lang_mode',lang('Server Type(mode)'));
	$phpgw->template->set_var('lang_security',lang('Security'));
	$phpgw->template->set_var('lang_admin_name',lang('Admin Name'));
	$phpgw->template->set_var('lang_admin_email',lang('Admin Email'));
	$phpgw->template->set_var('lang_edit',lang('Edit'));
	$phpgw->template->set_var('lang_default',lang('Default'));
	$phpgw->template->set_var('lang_reset',lang('Clear Form'));
	$phpgw->template->set_var('lang_done',lang('Done'));
	$phpgw->template->set_var('hidden_vars',$hidden_vars);
	$phpgw->template->set_var('lang_delete',lang('Delete'));

	$phpgw->template->set_var('server_name',$server['server_name']);
	$phpgw->template->set_var('server_url',$server['server_url']);
	$phpgw->template->set_var('server_username',$server['username']);
	$phpgw->template->set_var('server_mode',formatted_list('server_mode',$is->server_modes,$server['server_mode']));
	$phpgw->template->set_var('server_security',formatted_list('server_security',$is->security_types,$server['server_security']));
	$phpgw->template->set_var('ssl_note',lang('Note: SSL available only if PHP is compiled with curl support'));
	$phpgw->template->set_var('pass_note',lang('(Stored password will not be shown here)'));
	$phpgw->template->set_var('trust_level',formatted_list('trust_level',$is->trust_levels,$server['trust_level']));
	$phpgw->template->set_var('trust_relationship',formatted_list('trust_rel',$is->trust_relationships,$server['trust_rel'],True));
	$phpgw->template->set_var('admin_name',$phpgw->strip_html($server['admin_name']));
	$phpgw->template->set_var('admin_email',$phpgw->strip_html($server['admin_email']));

	$phpgw->template->set_var('edithandle','');
	$phpgw->template->set_var('addhandle','');

	$phpgw->template->pparse('out','form');
	$phpgw->template->pparse('edithandle','edit');

	$phpgw->common->phpgw_footer();
?>
