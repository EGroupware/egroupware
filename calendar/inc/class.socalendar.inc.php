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

	class socalendar
	{
		var $debug = False;
		var $cal;
		var $db;
		var $owner;
		var $datetime;
		var $filter;
		var $cat_id;

		function socalendar($param)
		{
			$this->db = $GLOBALS['phpgw']->db;
			$this->datetime = CreateObject('phpgwapi.datetime');

			$this->owner = (!isset($param['owner']) || $param['owner'] == 0?$GLOBALS['phpgw_info']['user']['account_id']:$param['owner']);
			$this->filter = (isset($param['filter']) && $param['filter'] != ''?$param['filter']:$this->filter);
			$this->cat_id = (isset($param['category']) && $param['category'] != ''?$param['category']:$this->cat_id);
			if($this->debug)
			{
				echo 'SO Filter : '.$this->filter."<br>\n";
				echo 'SO cat_id : '.$this->cat_id."<br>\n";
			}
			$this->cal = CreateObject('calendar.socalendar_');
			$this->cal->open('INBOX',intval($this->owner));
		}

		function maketime($time)
		{
			return mktime($time['hour'],$time['min'],$time['sec'],$time['month'],$time['mday'],$time['year']);
		}

		function read_entry($id)
		{
			return $this->cal->fetch_event($id);
		}

		function list_events($startYear,$startMonth,$startDay,$endYear=0,$endMonth=0,$endDay=0)
		{
			$extra = '';
			$extra .= (strpos($this->filter,'private')?'AND phpgw_cal.is_public=0 ':'');
			$extra .= ($this->cat_id?"AND phpgw_cal.category like '%".$this->cat_id."%' ":'');
			return $this->cal->list_events($startYear,$startMonth,$startDay,$endYear,$endMonth,$endDay,$extra,$this->datetime->tz_offset);
		}

		function list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday)
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] != 'sql')
			{
				return Array();
			}

			$starttime = mktime(0,0,0,$smonth,$sday,$syear) - $this->datetime->tz_offset;
			$endtime = mktime(23,59,59,$emonth,$eday,$eyear) - $this->datetime->tz_offset;
//			$starttime = mktime(0,0,0,$smonth,$sday,$syear);
//			$endtime = mktime(23,59,59,$emonth,$eday,$eyear);
			$sql = "AND (phpgw_cal.cal_type='M') "
				. 'AND (phpgw_cal_user.cal_login='.$this->owner.' '
//				. 'AND (phpgw_cal.datetime <= '.$starttime.') '
				. 'AND (((phpgw_cal_repeats.recur_enddate >= '.$starttime.') AND (phpgw_cal_repeats.recur_enddate <= '.$endtime.')) OR (phpgw_cal_repeats.recur_enddate=0))) ';

			$sql .= (strpos($this->filter,'private')?'AND phpgw_cal.is_public=0 ':'');

			$sql .= ($this->cat_id?"AND phpgw_cal.category like '%".$this->cat_id."%' ":'');

			$sql .= 'ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

			if($this->debug)
			{
				echo "SO list_repeated_events : SQL : ".$sql."<br>\n";
			}

			return $this->get_event_ids(True,$sql);
		}

		function list_events_keyword($keywords)
		{
			$sql = 'AND (phpgw_cal_user.cal_login='.$this->owner.') ';

			$words = split(' ',$keywords);
			for ($i=0;$i<count($words);$i++)
			{
				$sql .= ($i==0?' AND (':'');
				$sql .= ($i>0?' OR ':'');
				$sql .= "(UPPER(phpgw_cal.title) LIKE UPPER('%".$words[$i]."%') OR "
						. "UPPER(phpgw_cal.description) LIKE UPPER('%".$words[$i]."%'))";
				$sql .= ($i==count($words) - 1?') ':'');
			}

			$sql .= (strpos($this->filter,'private')?'AND phpgw_cal.is_public=0 ':'');
			$sql .= ($this->cat_id?"AND phpgw_cal.category like '%".$this->cat_id."%' ":'');
			$sql .= 'ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';
			return $this->get_event_ids(False,$sql);
		}

		function read_from_store($startYear,$startMonth,$startDay,$endYear='',$endMonth='',$endDay='')
		{
			$events = $this->list_events($startYear,$startMonth,$startDay,$endYear,$endMonth,$endDay);
			$events_cached = Array();
			for($i=0;$i<count($events);$i++)
			{
				$events_cached[] = $this->read_entry($events[$i]);
			}
			return $events_cached;
		}

		function get_event_ids($include_repeats=False, $sql='')
		{
			return $this->cal->get_event_ids($include_repeats,$sql);
		}

		function find_uid($uid)
		{
			$sql = " AND (phpgw_cal.uid = '".$uid."') ";

			$found = $this->cal->get_event_ids(False,$sql);
			if(!$found)
			{
				$found = $this->cal->get_event_ids(True,$sql);
			}
			if(is_array($found))
			{
				return $found[0];
			}
			else
			{
				return False;
			}
		}

		function add_entry(&$event)
		{
			$this->cal->store_event($event);
		}

		function delete_entry($id)
		{
			$this->cal->delete_event($id);
		}

		function expunge()
		{
			$this->cal->expunge();
		}

		function delete_calendar($owner)
		{
			$this->cal->delete_calendar($owner);
		}

		function change_owner($account_id,$new_owner)
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] == 'sql')
			{
				$this->cal->stream->query('UPDATE phpgw_cal SET owner='.$new_owner.' WHERE owner='.$account_id,__LINE__,__FILE__);
				$this->cal->stream->query('UPDATE phpgw_cal_user SET cal_login='.$new_owner.' WHERE cal_login='.$account_id);
			}
		}

		function set_status($id,$status)
		{
			$this->cal->set_status($id,$this->owner,$status);
		}

		function get_alarm($id)
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] == 'sql')
			{
				return $this->cal->get_alarm($id);	
			}
			else
			{
			}
		}

		/* Begin mcal equiv functions */
		function get_cached_event()
		{
			return $this->cal->event;
		}
		
		function add_attribute($var,$value,$element='False')
		{
			$this->cal->add_attribute($var,$value,$element);
		}

		function event_init()
		{
			$this->cal->event_init();
		}

		function set_date($element,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			$this->cal->set_date($element,$year,$month,$day,$hour,$min,$sec);
		}

		function set_start($year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			$this->cal->set_start($year,$month,$day,$hour,$min,$sec);
		}

		function set_end($year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			$this->cal->set_end($year,$month,$day,$hour,$min,$sec);
		}

		function set_title($title='')
		{
			$this->cal->set_title($title);
		}

		function set_description($description='')
		{
			$this->cal->set_description($description);
		}

		function set_class($class)
		{
			$this->cal->set_class($class);
		}

		function set_category($category='')
		{
			$this->cal->set_category($category);
		}

		function set_alarm($alarm)
		{
			$this->cal->set_alarm($alarm);
		}

		function set_recur_none()
		{
			$this->cal->set_recur_none();
		}

		function set_recur_daily($year,$month,$day,$interval)
		{
			$this->cal->set_recur_daily($year,$month,$day,$interval);
		}

		function set_recur_weekly($year,$month,$day,$interval,$weekdays)
		{
			$this->cal->set_recur_weekly($year,$month,$day,$interval,$weekdays);
		}

		function set_recur_monthly_mday($year,$month,$day,$interval)
		{
			$this->cal->set_recur_monthly_mday($year,$month,$day,$interval);
		}

		function set_recur_monthly_wday($year,$month,$day,$interval)
		{
			$this->cal->set_recur_monthly_wday($year,$month,$day,$interval);
		}

		function set_recur_yearly($year,$month,$day,$interval)
		{
			$this->cal->set_recur_yearly($year,$month,$day,$interval);
		}
		
		/* End mcal equiv functions */
	}
?>
