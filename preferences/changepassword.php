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

	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'preferences'
	);

	include('../header.inc.php');

	$n_passwd   = get_var('n_passwd',Array('POST'));
	$n_passwd_2 = get_var('n_passwd_2',Array('POST'));

	if (! $GLOBALS['phpgw']->acl->check('changepassword', 1))
	{
		Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php/'));
		$GLOBALS['phpgw']->common->phpgw_exit();
	}

	$GLOBALS['phpgw']->template->set_file(array(
		'form' => 'changepassword.tpl'
	));
	$GLOBALS['phpgw']->template->set_var('lang_changepassword',lang('Change your password'));
	$GLOBALS['phpgw']->template->set_var('lang_enter_password',lang('Enter your new password'));
	$GLOBALS['phpgw']->template->set_var('lang_reenter_password',lang('Re-enter your password'));
	$GLOBALS['phpgw']->template->set_var('lang_change',lang('Change'));
	$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/preferences/changepassword.php'));

	if($GLOBALS['phpgw_info']['server']['auth_type'] != 'ldap')
	{
		$GLOBALS['phpgw']->template->set_var('sql_message',lang('note: This feature might *not* change your email password. This may '
			. 'need to be done manually.'));
	}

	if(get_var('submit',Array('POST')))
	{
		if($n_passwd != $n_passwd_2)
		{
			$errors[] = lang('The two passwords are not the same');
		}

		if(! $n_passwd)
		{
			$errors[] = lang('You must enter a password');
		}

		if(is_array($errors))
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			$GLOBALS['phpgw']->template->set_var('messages',$GLOBALS['phpgw']->common->error_list($errors));
			$GLOBALS['phpgw']->template->pfp('out','form');
			$GLOBALS['phpgw']->common->phpgw_exit(True);
		}

		$o_passwd = $GLOBALS['phpgw_info']['user']['passwd'];
		$passwd_changed = $GLOBALS['phpgw']->auth->change_password($o_passwd, $n_passwd);
		if(!$passwd_changed)
		{
			// This need to be changed to show a different message based on the result
			Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php','cd=38'));
		}
		else
		{
			$GLOBALS['phpgw_info']['user']['passwd'] = $GLOBALS['phpgw']->auth->change_password($o_passwd, $n_passwd);
			$GLOBALS['hook_values']['account_id'] = $GLOBALS['phpgw_info']['user']['account_id'];
			$GLOBALS['hook_values']['old_passwd'] = $o_passwd;
			$GLOBALS['hook_values']['new_passwd'] = $n_passwd;
			$GLOBALS['phpgw']->hooks->process('changepassword');
			Header('Location: ' . $GLOBALS['phpgw']->link('/preferences/index.php','cd=18'));
		}
	}
	else
	{
		$GLOBALS['phpgw']->common->phpgw_header();

		$GLOBALS['phpgw']->template->pfp('out','form');
		$GLOBALS['phpgw']->common->phpgw_footer();
	}
?>
