<?php
  /**************************************************************************\
  * phpGroupWare - Chora                                                     *
  * http://www.phpgroupware.org                                              *
  * Written by Bettina Gille [ceb@phpgroupware.org]                          *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
/* $Id$ */

	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'admin';
	include('../header.inc.php');

	$GLOBALS['phpgw']->template->set_file(array('form' => 'server_form.tpl'));
	$GLOBALS['phpgw']->template->set_block('form','add','addhandle');
	$GLOBALS['phpgw']->template->set_block('form','edit','edithandle');

	$is = CreateObject('phpgwapi.interserver');
	$server_id = $GLOBALS['HTTP_POST_VARS']['server_id'];
	$server = $is->read_repository($server_id);

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

	$submit = $GLOBALS['HTTP_POST_VARS']['submit'];
	if ($submit)
	{
		$errorcount = 0;

		if($is->name2id($GLOBALS['HTTP_POST_VARS']['server_name']))
		{
			$error[$errorcount++] = lang('That server name has been used already !');
		}

		if (!$GLOBALS['HTTP_POST_VARS']['server_name'])
		{
			$error[$errorcount++] = lang('Please enter a name for that server !');
		}

		if (!$error)
		{
			$server_info = array(
				'server_name' => addslashes($GLOBALS['HTTP_POST_VARS']['server_name']),
				'server_url'  => addslashes($GLOBALS['HTTP_POST_VARS']['server_url']),
				'trust_level' => intval($GLOBALS['HTTP_POST_VARS']['trust_level']),
				'trust_rel'   => intval($GLOBALS['HTTP_POST_VARS']['trust_rel']),
				'username'    => addslashes($GLOBALS['HTTP_POST_VARS']['server_username']),
				'password'    => $GLOBALS['HTTP_POST_VARS']['server_password'] ? $GLOBALS['HTTP_POST_VARS']['server_password'] : $server['password'],
				'server_mode' => addslashes($GLOBALS['HTTP_POST_VARS']['server_mode']),
				'server_security' => addslashes($GLOBALS['HTTP_POST_VARS']['server_security']),
				'admin_name'  => addslashes($GLOBALS['HTTP_POST_VARS']['admin_name']),
				'admin_email' => addslashes($GLOBALS['HTTP_POST_VARS']['admin_email'])
			);

			$is->create($server_info);
		}
	}

	if ($errorcount)
	{
		$GLOBALS['phpgw']->template->set_var('message',$GLOBALS['phpgw']->common->error_list($error));
	}
	if (($submit) && (! $error) && (! $errorcount))
	{
		$GLOBALS['phpgw']->template->set_var('message',lang('Server x has been added !', $server_name));
	}
	if ((!$submit) && (!$error) && (!$errorcount))
	{
		$GLOBALS['phpgw']->template->set_var('message','');
	}

	$GLOBALS['phpgw']->template->set_var('title_servers',lang('Add Peer Server'));
	$GLOBALS['phpgw']->template->set_var('actionurl',$GLOBALS['phpgw']->link('/admin/addserver.php'));
	$GLOBALS['phpgw']->template->set_var('doneurl',$GLOBALS['phpgw']->link('/admin/servers.php'));
	$GLOBALS['phpgw']->template->set_var('hidden_vars','<input type="hidden" name="server_id" value="' . $server_id . '">');

	$GLOBALS['phpgw']->template->set_var('lang_name',lang('Server name'));
	$GLOBALS['phpgw']->template->set_var('lang_url',lang('Server URL'));
	$GLOBALS['phpgw']->template->set_var('lang_mode',lang('Server Type(mode)'));
	$GLOBALS['phpgw']->template->set_var('lang_security',lang('Security'));
	$GLOBALS['phpgw']->template->set_var('lang_trust',lang('Trust Level'));
	$GLOBALS['phpgw']->template->set_var('lang_relationship',lang('Trust Relationship'));
	$GLOBALS['phpgw']->template->set_var('lang_username',lang('Server Username'));
	$GLOBALS['phpgw']->template->set_var('lang_password',lang('Server Password'));
	$GLOBALS['phpgw']->template->set_var('lang_admin_name',lang('Admin Name'));
	$GLOBALS['phpgw']->template->set_var('lang_admin_email',lang('Admin Email'));
	$GLOBALS['phpgw']->template->set_var('lang_add',lang('Add'));
	$GLOBALS['phpgw']->template->set_var('lang_default',lang('Default'));
	$GLOBALS['phpgw']->template->set_var('lang_reset',lang('Clear Form'));
	$GLOBALS['phpgw']->template->set_var('lang_done',lang('Done'));

	$GLOBALS['phpgw']->template->set_var('server_name',$server['server_name']);
	$GLOBALS['phpgw']->template->set_var('server_url',$server['server_url']);
	$GLOBALS['phpgw']->template->set_var('server_username',$server['username']);
	$GLOBALS['phpgw']->template->set_var('server_mode',formatted_list('server_mode',$is->server_modes,$server['server_mode']));
	$GLOBALS['phpgw']->template->set_var('server_security',formatted_list('server_security',$is->security_types,$server['server_security']));
	$GLOBALS['phpgw']->template->set_var('ssl_note',lang('Note: SSL available only if PHP is compiled with curl support'));
	$GLOBALS['phpgw']->template->set_var('pass_note',lang('(Stored password will not be shown here)'));
	$GLOBALS['phpgw']->template->set_var('trust_level',formatted_list('trust_level',$is->trust_levels,$server['trust_level']));
	$GLOBALS['phpgw']->template->set_var('trust_relationship',formatted_list('trust_rel',$is->trust_relationships,$server['trust_rel'],True));
	$GLOBALS['phpgw']->template->set_var('admin_name',$GLOBALS['phpgw']->strip_html($server['admin_name']));
	$GLOBALS['phpgw']->template->set_var('admin_email',$GLOBALS['phpgw']->strip_html($server['admin_email']));

	$GLOBALS['phpgw']->template->set_var('edithandle','');
	$GLOBALS['phpgw']->template->set_var('addhandle','');
	$GLOBALS['phpgw']->template->pparse('out','form');
	$GLOBALS['phpgw']->template->pparse('addhandle','add');

	$GLOBALS['phpgw']->common->phpgw_footer();
?>
