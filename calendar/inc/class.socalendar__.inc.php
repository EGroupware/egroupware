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

if (@$phpgw_info['flags']['included_classes']['socalendar__'])
{
	return;
}

$phpgw_info['flags']['included_classes']['socalendar__'] = True;

include(PHPGW_SERVER_ROOT.'/calendar/setup/setup.inc.php');

if(extension_loaded('mcal') == False)
{
	define('MCAL_RECUR_NONE',0);
	define('MCAL_RECUR_DAILY',1);
	define('MCAL_RECUR_WEEKLY',2);
	define('MCAL_RECUR_MONTHLY_MDAY',3);
	define('MCAL_RECUR_MONTHLY_WDAY',4);
	define('MCAL_RECUR_YEARLY',5);
	
	define('MCAL_M_SUNDAY',1);
	define('MCAL_M_MONDAY',2);
	define('MCAL_M_TUESDAY',4);
	define('MCAL_M_WEDNESDAY',8);
	define('MCAL_M_THURSDAY',16);
	define('MCAL_M_FRIDAY',32);
	define('MCAL_M_SATURDAY',64);
	
	define('MCAL_M_WEEKDAYS',63);
	define('MCAL_M_WEEKEND',65);
	define('MCAL_M_ALLDAYS',127);
}

define('MSG_DELETED',0);
define('MSG_MODIFIED',1);
define('MSG_ADDED',2);
define('MSG_REJECTED',3);
define('MSG_TENTATIVE',4);
define('MSG_ACCEPTED',5);

define('REJECTED',0);
define('NO_RESPONSE',1);
define('TENTATIVE',2);
define('ACCEPTED',3);

class socalendar__
{
	var $event;
	var $stream;
	var $user;
	var $users_status;
	var $datetime;

	function socalendar__()
	{
		$this->datetime = CreateObject('phpgwapi.datetime');
	}

	function event_init()
	{
		CreateObject('calendar.calendar_item');
		$this->event = new calendar_item;
		$this->event->start = new calendar_time;
		$this->event->end = new calendar_time;
		$this->event->mod = new calendar_time;
		$this->event->recur_enddate = new calendar_time;
		$this->add_attribute('owner',intval($this->user));
	}

	function set_category($category='')
	{
		$this->event->category = $category;
	}

	function set_title($title='')
	{
		$this->event->title = $title;
	}

	function set_description($description='')
	{
		$this->event->description = $description;
	}

	function set_start($year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->event->start->year = intval($year);
		$this->event->start->month = intval($month);
		$this->event->start->mday = intval($day);
		$this->event->start->hour = intval($hour);
		$this->event->start->min = intval($min);
		$this->event->start->sec = intval($sec);
		$this->event->start->alarm = 0;
	}

	function set_end($year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->event->end->year = intval($year);
		$this->event->end->month = intval($month);
		$this->event->end->mday = intval($day);
		$this->event->end->hour = intval($hour);
		$this->event->end->min = intval($min);
		$this->event->end->sec = intval($sec);
		$this->event->end->alarm = 0;
	}

	function set_alarm($alarm)
	{
		$this->event->alarm = intval($alarm);
	}

	function set_class($class)
	{
		$this->event->public = $class;
	}

	function set_common_recur($year,$month,$day,$interval)
	{
		$this->event->recur_interval = intval($interval);
		if(intval($day) == 0 && intval($month) == 0 && intval($year) == 0)
		{
			$this->event->recur_enddate->year = 0;
			$this->event->recur_enddate->month = 0;
			$this->event->recur_enddate->mday = 0;
		}
		else
		{
			$this->event->recur_enddate->year = intval($year);
			$this->event->recur_enddate->month = intval($month);
			$this->event->recur_enddate->mday = intval($day);
		}
		$this->event->recur_enddate->hour = 0;
		$this->event->recur_enddate->min = 0;
		$this->event->recur_enddate->sec = 0;
		$this->event->recur_enddate->alarm = 0;
		$this->event->recur_data = 0;
	}

	function set_recur_none()
	{
		$this->set_common_recur(0,0,0,0);
		$this->event->recur_type = MCAL_RECUR_NONE;
	}

	function set_recur_daily($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = MCAL_RECUR_DAILY;
	}

	function set_recur_weekly($year,$month,$day,$interval,$weekdays)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = MCAL_RECUR_WEEKLY;
		$this->event->recur_data = intval($weekdays);
	}

	function set_recur_monthly_mday($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = MCAL_RECUR_MONTHLY_MDAY;
	}
	
	function set_recur_monthly_wday($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = MCAL_RECUR_MONTHLY_WDAY;
	}
	
	function set_recur_yearly($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = MCAL_RECUR_YEARLY;
	}

	function fetch_current_stream_event()
	{
		return $this->fetch_event($this->event->id);
	}
	
	function add_attribute($attribute,$value,$element='')
	{
		if(is_array($value))
		{
			reset($value);
		}
		eval("\$this->event->".$attribute." = ".$value.";"); 
	}
}
