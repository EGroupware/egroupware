<?php
	/**************************************************************************\
	* phpGroupWare - preferences                                               *
	* http://www.phpgroupware.org                                              *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'preferences'
	);

	include('../header.inc.php');

	if (! $phpgw->acl->check('changepassword', 1))
	{
		Header('Location: ' . $phpgw->link('/preferences/index.php/'));
		$phpgw->common->phpgw_exit();
	}    

	$phpgw->template->set_file(array(
		'form' => 'changepassword.tpl'
	));
	$phpgw->template->set_var('lang_changepassword',lang('Change password'));
	$phpgw->template->set_var('lang_enter_password',lang('Enter your new password'));
	$phpgw->template->set_var('lang_reenter_password',lang('Re-enter your password'));
	$phpgw->template->set_var('lang_change',lang('Change'));
	$phpgw->template->set_var('form_action',$phpgw->link('/preferences/changepassword.php'));

	if ($phpgw_info['server']['auth_type'] != 'ldap')
	{
		$phpgw->template->set_var('sql_message',lang('note: This feature does *not* change your email password. This will '
	           	   	   . 'need to be done manually.'));
	}


	if ($submit)
	{
		if ($n_passwd != $n_passwd_2)
		{
			$errors[] = lang('The two passwords are not the same');
		}

		if (! $n_passwd)
		{
			$errors[] = lang('You must enter a password');
		}

		if (is_array($errors))
		{
			$phpgw->common->phpgw_header();
			echo parse_navbar();
			$phpgw->template->set_var('messages',$phpgw->common->error_list($errors));
			$phpgw->template->pfp('out','form');
			$phpgw->common->phpgw_exit(True);
		}

		$o_passwd = $phpgw_info['user']['passwd'];
		$passwd_changed = $phpgw->auth->change_password($o_passwd, $n_passwd);
		if (! $passwd_changed)
		{
			// This need to be changed to show a different message based on the result
			Header('Location: ' . $phpgw->link('/preferences/index.php','cd=38'));
		}
		else
		{
			$phpgw_info['user']['passwd'] = $phpgw->auth->change_password($o_passwd, $n_passwd);
			Header('Location: ' . $phpgw->link('/preferences/index.php','cd=18'));
		}

	}
	else
	{
		$phpgw->common->phpgw_header();
		echo parse_navbar();

		$phpgw->template->pfp('out','form');
		$phpgw->common->phpgw_footer();
	}
?>
