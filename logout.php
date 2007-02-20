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

	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'disable_Template_class' => True,
			'currentapp'             => 'logout',
			'noheader'               => True,
			'nofooter'               => True,
			'nonavbar'               => True
		)
	);
	include('./header.inc.php');

	$GLOBALS['sessionid'] = get_var('sessionid',array('GET','COOKIE'));
	$GLOBALS['kp3']       = get_var('kp3',array('GET','COOKIE'));

	$verified = $GLOBALS['egw']->session->verify();

	if(!$redirectTarget = $GLOBALS['egw']->session->appsession('referer', 'login')) {
		$redirectTarget = $GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=1&domain='.$GLOBALS['egw_info']['user']['domain'];
	}

	if ($verified)
	{
		$GLOBALS['egw']->hooks->process('logout');
		$GLOBALS['egw']->session->destroy($GLOBALS['sessionid'],$GLOBALS['kp3']);
	}
	else
	{
		if(is_object($GLOBALS['egw']->log))
		{
			$GLOBALS['egw']->log->write(array(
				'text' => 'W-VerifySession, could not verify session during logout',
				'line' => __LINE__,
				'file' => __FILE__
			));
		}
	}
	$GLOBALS['egw']->session->egw_setcookie('eGW_remember','',0,'/');
	$GLOBALS['egw']->session->egw_setcookie('sessionid');
	$GLOBALS['egw']->session->egw_setcookie('kp3');
	$GLOBALS['egw']->session->egw_setcookie('domain');

	$GLOBALS['egw']->redirect($redirectTarget);
