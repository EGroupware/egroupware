<?php
	/**************************************************************************\
	* phpGroupWare - preferences                                               *
	* http://www.phpgroupware.org                                              *
	* Written by phpGroupWare coreteam <phpgroupware-developers@gnu.org>       *
	* ------------------------------------------------------------------       *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags']['currentapp'] = 'preferences';
	include('../header.inc.php');

	$GLOBALS['pref_tpl'] = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$templates = Array
	(
		'pref' => 'index.tpl'
	);

	$GLOBALS['pref_tpl']->set_file($templates);

	$GLOBALS['pref_tpl']->set_block('pref','list');
	$GLOBALS['pref_tpl']->set_block('pref','app_row');
	$GLOBALS['pref_tpl']->set_block('pref','app_row_noicon');
	$GLOBALS['pref_tpl']->set_block('pref','link_row');
	$GLOBALS['pref_tpl']->set_block('pref','spacer_row');

	if ($GLOBALS['phpgw']->acl->check('run',1,'admin'))
	{
		// This is where we will keep track of our position.
		// Developers won't have to pass around a variable then
		$session_data = $GLOBALS['phpgw']->session->appsession('session_data','preferences');

		if (! is_array($session_data))
		{
			$session_data = array('type' => 'user');
			$GLOBALS['phpgw']->session->appsession('session_data','preferences',$session_data);
		}

		$type = get_var('type',Array('GET'));
		if(!$type)
		{
			$type = $session_data['type'];
		}
		else
		{
			$session_data = array('type' => $type);
			$GLOBALS['phpgw']->session->appsession('session_data','preferences',$session_data);
		}

		$tabs[] = array
		(
			'label' => lang('Your preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/index.php','type=user')
		);
		$tabs[] = array
		(
			'label' => lang('Default preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/index.php','type=default')
		);
		$tabs[] = array
		(
			'label' => lang('Forced preferences'),
			'link'  => $GLOBALS['phpgw']->link('/preferences/index.php','type=forced')
		);

		switch($type)
		{
			case 'user':    $selected = 0; break;
			case 'default': $selected = 1; break;
			case 'forced':  $selected = 2; break;
		}
		$GLOBALS['pref_tpl']->set_var('tabs',$GLOBALS['phpgw']->common->create_tabs($tabs,$selected));
	}

	// This func called by the includes to dump a row header
	function section_start($appname='',$icon='')
	{
		$GLOBALS['pref_tpl']->set_var('icon_backcolor',$GLOBALS['phpgw_info']['theme']['row_off']);
//		$GLOBALS['pref_tpl']->set_var('link_backcolor',$GLOBALS['phpgw_info']['theme']['row_off']);
		$GLOBALS['pref_tpl']->set_var('app_name',$appname);
		$GLOBALS['pref_tpl']->set_var('app_title',lang($appname));
		$GLOBALS['pref_tpl']->set_var('app_icon',$icon);

		if ($icon)
		{
			$GLOBALS['pref_tpl']->parse('rows','app_row',True);
		}
		else
		{
			$GLOBALS['pref_tpl']->parse('rows','app_row_noicon',True);
		}
	}

	function section_item($pref_link='',$pref_text='')
	{
		$GLOBALS['pref_tpl']->set_var('pref_link',$pref_link);

		if (strtolower($pref_text) == 'grant access' && $GLOBALS['phpgw_info']['server']['deny_user_grants_access'])
		{
			return False;
		}
		else
		{
			$GLOBALS['pref_tpl']->set_var('pref_text',$pref_text);
		}

		$GLOBALS['pref_tpl']->parse('rows','link_row',True);
	}

	function section_end()
	{
		$GLOBALS['pref_tpl']->parse('rows','spacer_row',True);
	}

	function display_section($appname,$file)
	{
		section_start($appname,$GLOBALS['phpgw']->common->image($appname,'navbar','',True));

		while(list($text,$url) = each($file))
		{
			section_item($url,lang($text));
		}
		section_end();
	}

	$GLOBALS['phpgw']->hooks->process('preferences',array('preferences'));
	$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array(
		'body_data' => $GLOBALS['pref_tpl']->fp('out','list')
	));
?>
