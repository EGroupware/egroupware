<?php
	/**************************************************************************\
	* eGroupWare - freebusy times as iCals                                     *
	* http://www.egroupware.org                                                *
	* Written by RalfBecker@outdoor-training.de                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'calendar',
			'noheader'   => True,
			'nofooter'   => True,
		),
	);
	// check if we are loged in, by checking sessionid and kp3, as the sessionid get set automaticaly by php for php4-sessions
	if (!($loged_in = @$_REQUEST['sessionid'] && @$_REQUEST['kp3']))
	{
		$GLOBALS['egw_info']['flags']['currentapp'] = 'login';
		$GLOBALS['egw_info']['flags']['noapi'] = True;
	}
	include ('../header.inc.php');

	function fail_exit($msg)
	{
		echo "<html>\n<head>\n<title>$msg</title>\n<meta http-equiv=\"content-type\" content=\"text/html; charset=".
			$GLOBALS['egw']->translation->charset()."\" />\n</head>\n<body><h1>$msg</h1>\n</body>\n</html>\n";

		$GLOBALS['egw']->common->egw_exit();
	}

	if (!$loged_in)
	{
		include ('../phpgwapi/inc/functions.inc.php');
		$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';
	}
	$user  = is_numeric($_GET['user']) ? (int) $_GET['user'] : $GLOBALS['egw']->accounts->name2id($_GET['user'],'account_lid','u');

	if (!($username = $GLOBALS['egw']->accounts->id2name($user)))
	{
		fail_exit(lang("freebusy: Unknow user '%1', wrong password or not availible to not loged in users !!!"." $username($user)",$_GET['user']));
	}
	if (!$loged_in)
	{
		$GLOBALS['egw']->preferences->account_id = $user;
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw_info']['user']['account_id'] = $user;
		$GLOBALS['egw_info']['user']['account_lid'] = $username;

		$cal_prefs = &$GLOBALS['egw_info']['user']['preferences']['calendar'];
		if (!$cal_prefs['freebusy'] || !empty($cal_prefs['freebusy_pw']) && $cal_prefs['freebusy_pw'] != $_GET['password'])
		{
			fail_exit(lang("freebusy: Unknow user '%1', wrong password or not availible to not loged in users !!!",$_GET['user']));
		}
	}
	if ($_GET['debug'])
	{
		echo "<pre>";
	}
	else
	{
		ExecMethod2('phpgwapi.browser.content_header','freebusy.ifb','text/calendar');
	}
	echo ExecMethod2('calendar.boical.freebusy',$user,$_GET['end']);
