<?php
	/**************************************************************************\
	* eGroupWare - preferences                                                 *
	* http://www.egroupware.org                                                *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'preferences'
	);
	include('../header.inc.php');

	$n_passwd   = $_POST['n_passwd'];
	$n_passwd_2 = $_POST['n_passwd_2'];
	$o_passwd_2 = $_POST['o_passwd_2'];

	if(!$GLOBALS['egw']->acl->check('changepassword', 1) || $_POST['cancel'])
	{
		$GLOBALS['egw']->redirect_link('/preferences/index.php');
		$GLOBALS['egw']->common->phpgw_exit();
	}

	$GLOBALS['egw']->template->set_file(array(
		'form' => 'changepassword.tpl'
	));
	$GLOBALS['egw']->template->set_var('lang_enter_password',lang('Enter your new password'));
	$GLOBALS['egw']->template->set_var('lang_reenter_password',lang('Re-enter your password'));
	$GLOBALS['egw']->template->set_var('lang_enter_old_password',lang('Enter your old password'));
	$GLOBALS['egw']->template->set_var('lang_change',lang('Change'));
	$GLOBALS['egw']->template->set_var('lang_cancel',lang('Cancel'));
	$GLOBALS['egw']->template->set_var('form_action',$GLOBALS['egw']->link('/preferences/changepassword.php'));

	if ($GLOBALS['egw_info']['server']['auth_type'] != 'ldap')
	{
		$GLOBALS['egw']->template->set_var('sql_message',lang('note: This feature does *not* change your email password. This will '
			. 'need to be done manually.'));
	}

	if ($_POST['change'])
	{
		$o_passwd = $GLOBALS['egw_info']['user']['passwd'];
		
		if ($o_passwd != $o_passwd_2)
		{
		       $errors[] = lang('The old password is not correct');
		}
		
		if ($n_passwd != $n_passwd_2)
		{
			$errors[] = lang('The two passwords are not the same');
		}

		if (! $n_passwd)
		{
			$errors[] = lang('You must enter a password');
		}

		if(is_array($errors))
		{
			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();
			$GLOBALS['egw']->template->set_var('messages',$GLOBALS['egw']->common->error_list($errors));
			$GLOBALS['egw']->template->pfp('out','form');
			$GLOBALS['egw']->common->phpgw_exit(True);
		}

		$passwd_changed = $GLOBALS['egw']->auth->change_password($o_passwd, $n_passwd);
		if(!$passwd_changed)
		{
			$errors[] = lang('Failed to change password.  Please contact your administrator.');
			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();
			$GLOBALS['egw']->template->set_var('messages',$GLOBALS['egw']->common->error_list($errors));
			$GLOBALS['egw']->template->pfp('out','form');
			$GLOBALS['egw']->common->phpgw_exit(True);
		}
		else
		{
			$GLOBALS['egw']->session->appsession('password','phpgwapi',base64_encode($n_passwd));
			$GLOBALS['egw_info']['user']['passwd'] = $n_passwd;
			$GLOBALS['hook_values']['account_id'] = $GLOBALS['egw_info']['user']['account_id'];
			$GLOBALS['hook_values']['old_passwd'] = $o_passwd;
			$GLOBALS['hook_values']['new_passwd'] = $n_passwd;
			
			// called for every app now, not only for the ones enabled for the user
			$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
				'location' => 'changepassword',
			),False,True);
			$GLOBALS['egw']->redirect_link('/preferences/index.php','cd=18');
		}
	}
	else
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Change your password');
		$GLOBALS['egw']->common->phpgw_header();
		echo parse_navbar();

		$GLOBALS['egw']->template->pfp('out','form');
		$GLOBALS['egw']->common->phpgw_footer();
	}
?>
