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

  if (floor(phpversion()) == 4) {
    global $date, $year, $month, $day, $thisyear, $thismonth, $thisday, $filter, $keywords;
    global $matrixtype, $participants, $owner, $phpgw, $grants, $rights, $SCRIPT_FILENAME, $remainder;
  }

	$cols = 8;
	if($phpgw->calendar->check_perms(PHPGW_ACL_PRIVATE) == True)
	{
		$cols++;
	}
	
	include(PHPGW_APP_TPL.'/header.inc.php');
	flush();
?>
