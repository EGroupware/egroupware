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

	$GLOBALS['phpgw_info']        = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'disable_template_class' => True,
		'currentapp'             => 'logout',
		'noheader'               => True,
		'nofooter'               => True,
		'nonavbar'               => True
	);

	include('./header.inc.php');

	$GLOBALS['sessionid'] = $GLOBALS['HTTP_GET_VARS']['sessionid'] ? $GLOBALS['HTTP_GET_VARS']['sessionid'] : $GLOBALS['HTTP_COOKIE_VARS']['sessionid'];
	$GLOBALS['kp3']       = $GLOBALS['HTTP_GET_VARS']['kp3'] ? $GLOBALS['HTTP_GET_VARS']['kp3'] : $GLOBALS['HTTP_COOKIE_VARS']['kp3'];

	$verified = $GLOBALS['phpgw']->session->verify();
	if ($verified)
	{
		if (file_exists($GLOBALS['phpgw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid']))
		{
			$dh = opendir($GLOBALS['phpgw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid']);
			while ($file = readdir($dh))
			{
				if ($file != '.' && $file != '..')
				{
					unlink($GLOBALS['phpgw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid'] . SEP . $file);
				}
			}
			rmdir($GLOBALS['phpgw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid']);
		}
		$GLOBALS['phpgw']->common->hook('logout');
		$GLOBALS['phpgw']->session->destroy($GLOBALS['sessionid'],$GLOBALS['kp3']);
	}
	else
	{
		if(is_object($GLOBALS['phpgw']->log))
		{
			$GLOBALS['phpgw']->log->write(array(
				'text' => 'W-VerifySession, could not verify session during logout',
				'line' => __LINE__,
				'file' => __FILE__
			));
		}
	}
	Setcookie('sessionid');
	Setcookie('kp3');
	Setcookie('domain');

	$GLOBALS['phpgw']->redirect($GLOBALS['phpgw_info']['server']['webserver_url'].'/login.php?cd=1');
?>
