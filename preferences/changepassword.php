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

	if (! $GLOBALS['phpgw']->acl->check('changepassword', 1) || $_POST['cancel'])
	{
		$GLOBALS['phpgw']->redirect_link('/preferences/index.php');
	}

	$GLOBALS['phpgw']->template->set_file(array(
		'form' => 'changepassword.tpl'
	));
	$GLOBALS['phpgw']->template->set_var('lang_enter_password',lang('Enter your new password'));
	$GLOBALS['phpgw']->template->set_var('lang_reenter_password',lang('Re-enter your password'));
	$GLOBALS['phpgw']->template->set_var('lang_change',lang('Change'));
	$GLOBALS['phpgw']->template->set_var('lang_cancel',lang('Cancel'));
	$GLOBALS['phpgw']->template->set_var('form_action',$GLOBALS['phpgw']->link('/preferences/changepassword.php'));

	if($GLOBALS['phpgw_info']['server']['auth_type'] != 'ldap')
	{
		$GLOBALS['phpgw']->template->set_var('sql_message',lang('note: This feature might *not* change your email password. This may '
			. 'need to be done manually.'));
	}

	if(get_var('change',Array('POST')))
	{
		if($n_passwd != $n_passwd_2)
		{
			$GLOBALS['phpgw_info']['flags']['msgbox_data']['The two passwords are not the same']=False;
		}

		if(! $n_passwd)
		{
			$GLOBALS['phpgw_info']['flags']['msgbox_data']['You must enter a password']=False;
		}
		sanitize($n_passwd,'password');
		
		if(@is_array($GLOBALS['phpgw_info']['flags']['msgbox_data']))
		{
			$GLOBALS['phpgw']->common->phpgw_header();
			$GLOBALS['phpgw']->template->pfp('out','form');
		}
		else
		{
			$o_passwd = $GLOBALS['phpgw_info']['user']['passwd'];
			$passwd_changed = $GLOBALS['phpgw']->auth->change_password($o_passwd, $n_passwd);
			if(!$passwd_changed)
			{
				// This need to be changed to show a different message based on the result
				$GLOBALS['phpgw']->redirect_link('/preferences/index.php','cd=38');
			}
			else
			{
				$GLOBALS['phpgw_info']['user']['passwd'] = $GLOBALS['phpgw']->auth->change_password($o_passwd, $n_passwd);
				$GLOBALS['hook_values']['account_id'] = $GLOBALS['phpgw_info']['user']['account_id'];
				$GLOBALS['hook_values']['old_passwd'] = $o_passwd;
				$GLOBALS['hook_values']['new_passwd'] = $n_passwd;
				$GLOBALS['phpgw']->hooks->process('changepassword');
				$GLOBALS['phpgw']->redirect_link('/preferences/index.php','cd=18');
			}
		}
	}
	else
	{
		$GLOBALS['phpgw_info']['flags']['app_header'] = lang('Change your password');
		$GLOBALS['phpgw']->common->phpgw_header();
		$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array(
			'body_data' => $GLOBALS['phpgw']->template->fp('out','form')
		));
	}
?>
