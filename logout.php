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

	$phpgw_info          = array();
	$phpgw_info['flags'] = array(
		'disable_template_class' => True,
		'currentapp'             => 'logout',
		'noheader'               => True,
		'nofooter'               => True,
		'nonavbar'               => True
	);

	include('./header.inc.php');

	if ($phpgw->session->verify($sessionid))
	{
		if (file_exists($phpgw_info['server']['temp_dir'] . sep . $sessionid))
		{
			$dh = opendir($phpgw_info['server']['temp_dir'] . sep . $sessionid);
			while ($file = readdir($dh))
			{
				if ($file != '.' && $file != '..')
				{
					unlink($phpgw_info['server']['temp_dir'] . sep . $sessionid . sep . $file);
				}
			}
			rmdir($phpgw_info['server']['temp_dir'] . sep . $sessionid);
		}
		$phpgw->session->destroy();
	}
	Setcookie('sessionid');
	Setcookie('kp3');
	Setcookie('domain');

	$phpgw->redirect($phpgw_info['server']['webserver_url'].'/login.php?cd=1');
?>