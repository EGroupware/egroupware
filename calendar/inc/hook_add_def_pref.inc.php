<?php
  /**************************************************************************\
  * phpGroupWare - Calendar                                                  *
  * http://www.phpgroupware.org                                              *
  * Written by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	global $pref;
	$pref->change('calendar','weekstarts','Monday');
	$pref->change('calendar','workdaystarts','9');
	$pref->change('calendar','workdayends','17');
	$pref->change('calendar','defaultcalendar','month.php');
	$pref->change('calendar','defaultfilter',"all');
	$pref->change('calendar','mainscreen_showevents','Y');
?>
