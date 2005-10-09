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

	$egw_info = array();
	$GLOBALS['egw_info']['flags'] = array(
		'disable_Template_class' => True,
		'currentapp'             => 'logout',
		'noheader'               => True,
		'nofooter'               => True,
		'nonavbar'               => True
	);
	include('./header.inc.php');

	$GLOBALS['sessionid'] = get_var('sessionid',array('GET','COOKIE'));
	$GLOBALS['kp3']       = get_var('kp3',array('GET','COOKIE'));

	$verified = $GLOBALS['egw']->session->verify();
	if ($verified)
	{
		if (file_exists($GLOBALS['egw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid']))
		{
			$dh = opendir($GLOBALS['egw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid']);
			while ($file = readdir($dh))
			{
				if ($file != '.' && $file != '..')
				{
					unlink($GLOBALS['egw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid'] . SEP . $file);
				}
			}
			closedir($dh);
			rmdir($GLOBALS['egw_info']['server']['temp_dir'] . SEP . $GLOBALS['sessionid']);
		}
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
	$GLOBALS['egw']->session->phpgw_setcookie('eGW_remember');
	$GLOBALS['egw']->session->phpgw_setcookie('sessionid');
	$GLOBALS['egw']->session->phpgw_setcookie('kp3');
	$GLOBALS['egw']->session->phpgw_setcookie('domain');
	if($GLOBALS['egw_info']['server']['sessions_type'] == 'php4')
	{
		$GLOBALS['egw']->session->phpgw_setcookie(EGW_PHPSESSID);
	}

	$GLOBALS['egw']->redirect($GLOBALS['egw_info']['server']['webserver_url'].'/login.php?cd=1&domain='.$GLOBALS['egw_info']['user']['domain']);
?>
