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

	class boalarm
	{
		var $so;
		var $cal;
		var $cal_id;

		var $tz_offset;

		var $debug = False;
//		var $debug = True;

		var $public_functions = array(
		);

		function boalarm()
		{
			$cal_id = (isset($GLOBALS['HTTP_POST_VARS']['cal_id'])?intval($GLOBALS['HTTP_POST_VARS']['cal_id']):'');
			if($cal_id)
			{
				$this->cal_id = $cal_id;
			}
			$this->cal = CreateObject('calendar.bocalendar',1);
			$this->tz_offset = $this->cal->datetime->tz_offset;

			if($this->debug)
			{
				echo "BO Owner : ".$this->cal->owner."<br>\n";
			}

			if($this->cal->use_session)
			{
				$this->save_sessiondata();
			}

		}

		function save_sessiondata()
		{
			$data = array(
				'filter' => $this->cal->filter,
				'cat_id' => $this->cal->cat_id,
				'owner'	=> $this->cal->owner,
				'year'	=> $this->cal->year,
				'month'	=> $this->cal->month,
				'day'		=> $this->cal->day
			);
			$this->cal->save_sessiondata($data);
		}

		function read_entry($cal_id)
		{
			return $this->cal->read_entry($cal_id);
		}
		/* Public functions */
	}
