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

class calendar_time
{
	var $year;
	var $month;
	var $mday;
	var $hour;
	var $min;
	var $sec;
	var $alarm = 0;
}

class calendar_item
{
//New ICal Support
	var $id = 0;
	var $category = 0;
	var $title = "Unnamed Event";
	var $description = "Unnamed Event";
	var $public = 0;
	var $alarm = 0;
	var $start;
	var $end;
	var $mod;
	var $recur_type = 0;
	var $recur_interval = 0;
	var $recur_enddate;
	var $recur_data = 0;

	var $users_status = 'U';
	var $owner;
	var $datetime = 0;
	var $mdatetime = 0;
	var $edatetime = 0;
	var $priority = 0;
	var $groups = array();
	var $participants = array();
	var $status = array();

	function set($var,$val="")
	{
		$this->$var = $val;
	}
}

?>
