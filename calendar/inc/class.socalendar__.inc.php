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

if (@$GLOBALS['phpgw_info']['flags']['included_classes']['socalendar__'])
{
	return;
}

$GLOBALS['phpgw_info']['flags']['included_classes']['socalendar__'] = True;

/*	include(PHPGW_SERVER_ROOT.'/calendar/setup/setup.inc.php');	*/

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
	var $debug = False;

	function socalendar__()
	{
		$this->datetime = CreateObject('phpgwapi.datetime');
	}

	function event_init()
	{
		$this->event = Array();
		$this->add_attribute('owner',intval($this->user));
	}

	function set_category($category='')
	{
		$this->add_attribute('category',$category);
	}

	function set_title($title='')
	{
		$this->add_attribute('title',$title);
	}

	function set_description($description='')
	{
		$this->add_attribute('description',$description);
	}

	function set_date($element,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->add_attribute($element,intval($year),'year');
		$this->add_attribute($element,intval($month),'month');
		$this->add_attribute($element,intval($day),'mday');
		$this->add_attribute($element,intval($hour),'hour');
		$this->add_attribute($element,intval($min),'min');
		$this->add_attribute($element,intval($sec),'sec');
		$this->add_attribute($element,0,'alarm');
	}

	function set_start($year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->set_date('start',$year,$month,$day,$hour,$min,$sec);
	}

	function set_end($year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->set_date('end',$year,$month,$day,$hour,$min,$sec);
	}

	function set_alarm($alarm)
	{
		$this->add_attribute('alarm',intval($alarm));
	}

	function set_class($class)
	{
		$this->add_attribute('public',$class);
	}

	function set_common_recur($year=0,$month=0,$day=0,$interval)
	{
		$this->add_attribute('recur_interval',intval(interval));
		$this->set_date('recur_enddate',$year,$month,$day,0,0,0);
		$this->add_attribute('recur_data',0);
	}

	function set_recur_none()
	{
		$this->set_common_recur(0,0,0,0);
		$this->add_attribute('recur_type',MCAL_RECUR_NONE);
	}

	function set_recur_daily($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->add_attribute('recur_type',MCAL_RECUR_DAILY);
	}

	function set_recur_weekly($year,$month,$day,$interval,$weekdays)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->add_attribute('recur_type',MCAL_RECUR_WEEKLY);
		$this->add_attribute('recur_data',intval($weekdays));
	}

	function set_recur_monthly_mday($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->add_attribute('recur_type',MCAL_RECUR_MONTHLY_MDAY);
	}
	
	function set_recur_monthly_wday($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->add_attribute('recur_type',MCAL_RECUR_MONTHLY_WDAY);
	}
	
	function set_recur_yearly($year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->add_attribute('recur_type',MCAL_RECUR_YEARLY);
	}

	function fetch_current_stream_event()
	{
		return $this->fetch_event($this->event['id']);
	}
	
	function add_attribute($attribute,$value,$element='False')
	{
		if(is_array($value))
		{
			reset($value);
		}
		if($element!='False')
		{
			$this->event[$attribute][$element] = $value;
		}
		else
		{
			$this->event[$attribute] = $value;
		}
	}
}
