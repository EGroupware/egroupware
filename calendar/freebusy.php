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

	$GLOBALS['phpgw_info'] = array(
		'flags' => array(
			'currentapp' => 'calendar',
			'noheader'   => True,
			'nofooter'   => True,
		),
	);
	// check if we are loged in, by checking sessionid and kp3, as the sessionid get set automaticaly by php for php4-sessions
	$sessionid = isset($_COOKIE['sessionid']) ? $_COOKIE['sessionid'] : @$_GET['sessionid'];
	$kp3 = isset($_COOKIE['kp3']) ? $_COOKIE['kp3'] : @$_GET['kp3'];

	if (!($loged_in = $sessionid && $kp3))
	{
		$GLOBALS['phpgw_info']['flags']['currentapp'] = 'login';
		$GLOBALS['phpgw_info']['flags']['noapi'] = True;
	}
	include ('../header.inc.php');

	function fail_exit($msg)
	{
		echo "<html>\n<head>\n<title>$msg</title>\n<meta http-equiv=\"content-type\" content=\"text/html; charset=".
			$GLOBALS['phpgw']->translation->charset()."\" />\n</head>\n<body><h1>$msg</h1>\n</body>\n</html>\n";

		$GLOBALS['phpgw']->common->phpgw_exit();
	}

	if (!$loged_in)
	{
		include ('../phpgwapi/inc/functions.inc.php');
		$GLOBALS['phpgw_info']['flags']['currentapp'] = 'calendar';
	}
	$user  = is_numeric($_GET['user']) ? (int) $_GET['user'] : $GLOBALS['phpgw']->accounts->name2id($_GET['user']);

	if (!($username = $GLOBALS['phpgw']->accounts->id2name($user)))
	{
		fail_exit(lang("Unknow user '%1' !!!",$_GET['user']));
	}
	if (!$loged_in)
	{
		$GLOBALS['phpgw']->preferences->account_id = $user;
		$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->read_repository();
		$GLOBALS['phpgw_info']['user']['account_id'] = $user;
		$GLOBALS['phpgw_info']['user']['account_lid'] = $username;

		$cal_prefs = &$GLOBALS['phpgw_info']['user']['preferences']['calendar'];
		if (!$cal_prefs['freebusy'])
		{
			fail_exit(lang("The freebusy information for user '%1' is not availible to not loged in users !!!",$username));
		}
		if (!empty($cal_prefs['freebusy_pw']) && $cal_prefs['freebusy_pw'] != $_GET['password'])
		{
			fail_exit(lang("Wrong password for user '%1' !!!",$username));
		}
	}
	ExecMethod('calendar.boicalendar.freebusy');
