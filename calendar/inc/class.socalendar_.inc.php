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

	if(@$GLOBALS['phpgw_info']['server']['calendar_type'] == 'mcal' &&
		extension_loaded('mcal') == False)
	{
		$GLOBALS['phpgw_info']['server']['calendar_type'] = 'sql';
	}
// This will be elminated when ical is fully implemented
	else
	{
		$GLOBALS['phpgw_info']['server']['calendar_type'] = 'sql';
	}
	include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.socalendar__.inc.php');
	include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.socalendar_'.$GLOBALS['phpgw_info']['server']['calendar_type'].'.inc.php');
	return new socalendar_;
?>
