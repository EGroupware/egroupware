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

  $phpgw_info['server']['calendar_type'] = 'sql';
  include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_'.$phpgw_info['server']['calendar_type'].'.inc.php');

  class calendar extends calendar_
  {
	function calendar($p_friendly=False)
	{
		$this->calendar_($p_friendly);
	}
  }
?>
