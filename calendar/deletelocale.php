<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/
	/* $Id$ */

	if(!$locale)
	{
		Header('Location: ' . $phpgw->link('/calendar/holiday_admin.php'));
	}
	
	$phpgw_flags = Array(
		'currentapp'		=> 'calendar',
		'enable_nextmatchs_class'	=> True,
		'admin_header'		=>	True,
		'noheader'		=> True,
		'nonavbar'		=> True,
		'noappheader'		=> True,
		'noappfooter'		=> True,
		'parent_page'		=> 'holiday_admin.php'
	);
	$phpgw_info['flags'] = $phpgw_flags;
	include('../header.inc.php');

	if(isset($yes) && $yes==True)
	{
		$phpgw->calendar->holidays->delete_locale($locale);
		Header('Location: ' . $phpgw->link('/calendar/holiday_admin.php'));
	}

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$p = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$templates = Array(
		'form'	=> 'delete_common.tpl',
		'form_button'	=> 'form_button_script.tpl'
	);
	$p->set_file($templates);

	$p->set_var('messages',lang('Are you sure you want to delete this  ?'));

	$var = Array(
		'action_url_button'	=> $phpgw->link('/calendar/holiday_admin.php'),
		'action_text_button'	=> lang('No'),
		'action_confirm_button'	=> '',
		'action_extra_field'	=> ''
	);
	$p->set_var($var);
	$p->parse('no','form_button');

	$var = Array(
		'action_url_button'	=> $phpgw->link('/calendar/deletelocale.php','locale='.$locale.'&yes=true'),
		'action_text_button'	=> lang('Yes'),
		'action_confirm_button'	=> '',
		'action_extra_field'	=> ''
	);
	$p->set_var($var);
	$p->parse('yes','form_button');

	$p->pparse('out','form');
	$phpgw->common->phpgw_footer();
?>
