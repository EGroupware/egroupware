<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
  *          http://www.radix.net/~cknudsen                                  *
  * Modified by Mark Peters <skeeter@phpgroupware.org>                       *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

	/* $Id$ */

	$phpgw_flags = Array(
		'currentapp'	=>	'calendar',
		'noheader'	=>	True,
		'nonavbar'	=>	True,
		'noappheader'	=>	True,
		'noappfooter'	=>	True,
		'nofooter'	=>	True
	);

	$phpgw_info['flags'] = $phpgw_flags;
	
	include('../header.inc.php');

	$newpage = $phpgw_info['user']['preferences']['calendar']['defaultcalendar'];
	if ($newpage=='index.php' || ($newpage != 'day.php' && $newpage != 'week.php' && $newpage != 'month.php' && $newpage != 'year.php'))
	{
		$newpage = 'month.php';
		$phpgw->preferences->add('calendar','defaultcalendar','month.php');
		$phpgw->preferences->save_repository();
	}

	Header('Location: '.$newpage.'?'.$QUERY_STRING);
	$phpgw->common->phpgw_exit();
?>
