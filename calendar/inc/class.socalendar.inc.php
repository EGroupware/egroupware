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
//		var $debug = True;
		var $debug = False;
		var $cal;
		var $db;
		var $owner;
		var $g_owner;
		var $is_group = False;
		var $datetime;
		var $filter;
		var $cat_id;

		function socalendar($param)
		{
			$this->db = $GLOBALS['phpgw']->db;
			if(!is_object($GLOBALS['phpgw']->datetime))
			{
				$GLOBALS['phpgw']->datetime = createobject('phpgwapi.datetime');
			}

			$this->owner = (!isset($param['owner']) || $param['owner'] == 0?$GLOBALS['phpgw_info']['user']['account_id']:$param['owner']);
			$this->filter = (isset($param['filter']) && $param['filter'] != ''?$param['filter']:$this->filter);
			$this->cat_id = (isset($param['category']) && $param['category'] != ''?$param['category']:$this->cat_id);
			if(isset($param['g_owner']) && is_array($param['g_owner']))
			{
				$this->is_group = True;
				$this->g_owner = $param['g_owner'];
			}
			if($this->debug)
			{
				echo '<!-- SO Filter : '.$this->filter.' -->'."\n";
				echo '<!-- SO cat_id : '.$this->cat_id.' -->'."\n";
			}
			$this->cal = CreateObject('calendar.socalendar_');
			$this->open_box($this->owner);
		}

		function open_box($owner)
		{
			$this->cal->open('INBOX',intval($owner));
		}

		function maketime($time)
		{
			return mktime($time['hour'],$time['min'],$time['sec'],$time['month'],$time['mday'],$time['year']);
		}

		function read_entry($id)
		{
			return $this->cal->fetch_event($id);
		}

		function list_events($startYear,$startMonth,$startDay,$endYear=0,$endMonth=0,$endDay=0,$owner_id=0)
		{
			$extra = '';
			$extra .= (strpos($this->filter,'private')?'AND phpgw_cal.is_public=0 ':'');
			$extra .= ($this->cat_id?"AND phpgw_cal.category like '%".$this->cat_id."%' ":'');
			if($owner_id)
			{
				return $this->cal->list_events($startYear,$startMonth,$startDay,$endYear,$endMonth,$endDay,$extra,$GLOBALS['phpgw']->datetime->tz_offset,$owner_id);
			}
			else
			{
				return $this->cal->list_events($startYear,$startMonth,$startDay,$endYear,$endMonth,$endDay,$extra,$GLOBALS['phpgw']->datetime->tz_offset);
			}
		}

		function list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday,$owner_id=0)
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] != 'sql')
			{
				return Array();
			}

			$starttime = mktime(0,0,0,$smonth,$sday,$syear) - $GLOBALS['phpgw']->datetime->tz_offset;
			$endtime = mktime(23,59,59,$emonth,$eday,$eyear) - $GLOBALS['phpgw']->datetime->tz_offset;
//			$starttime = mktime(0,0,0,$smonth,$sday,$syear);
//			$endtime = mktime(23,59,59,$emonth,$eday,$eyear);
			$sql = "AND (phpgw_cal.cal_type='M') "
				. 'AND (phpgw_cal_user.cal_login in (';
			if($owner_id)
			{
				if(is_array($owner_id))
				{
					$sql .= implode(',',$owner_id);
				}
				else
				{
					$sql .= $owner_id;
				}
			}
			else
			{
				$sql .= (!$this->is_group?$this->owner:implode(',',$this->g_owner));
			}

//			$member_groups = $GLOBALS['phpgw']->accounts->membership($this->user);
//			@reset($member_groups);
//			while(list($key,$group_info) = each($member_groups))
//			{
//				$member[] = $group_info['account_id'];
//			}
//			@reset($member);
//			$sql .= ','.implode(',',$member).') ';
//			$sql .= 'AND (phpgw_cal.datetime <= '.$starttime.') ';
//			$sql .= 'AND (((phpgw_cal_repeats.recur_enddate >= '.$starttime.') AND (phpgw_cal_repeats.recur_enddate <= '.$endtime.')) OR (phpgw_cal_repeats.recur_enddate=0))) '
			$sql .= ') AND ((phpgw_cal_repeats.recur_enddate >= '.$starttime.') OR (phpgw_cal_repeats.recur_enddate=0))) '
				. (strpos($this->filter,'private')?'AND phpgw_cal.is_public=0 ':'')
				. ($this->cat_id?"AND phpgw_cal.category like '%".$this->cat_id."%' ":'')
				. 'ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

			if($this->debug)
			{
				echo '<!-- SO list_repeated_events : SQL : '.$sql.' -->'."\n";
			}

			return $this->get_event_ids(True,$sql);
		}

		function list_events_keyword($keywords,$members='')
		{
			if (!$members)
			{
				$members[] = $this->owner;
			}
			$sql = 'AND (phpgw_cal_user.cal_login IN ('.implode(',',$members).')) AND '.
				'(phpgw_cal_user.cal_login='.intval($this->owner).' OR phpgw_cal.is_public=1) AND (';

			$words = split(' ',$keywords);
			foreach($words as $i => $word)
			{
				$sql .= $i > 0 ? ' OR ' : '';
				$sql .= "(UPPER(phpgw_cal.title) LIKE UPPER('%".addslashes($word)."%') OR "
						. "UPPER(phpgw_cal.description) LIKE UPPER('%".addslashes($word)."%') OR "
						. "UPPER(phpgw_cal.location) LIKE UPPER('%".addslashes($word)."%') OR "
						. "UPPER(phpgw_cal_extra.cal_extra_value) LIKE UPPER('%".addslashes($word)."%'))";
			}
			$sql .= ') ';

			$sql .= (strpos($this->filter,'private')?'AND phpgw_cal.is_public=0 ':'');
			$sql .= ($this->cat_id? "AND (phpgw_cal.category='$this->cat_id' OR phpgw_cal.category like '%,".$this->cat_id.",%') ":'');
			$sql .= 'ORDER BY phpgw_cal.datetime DESC, phpgw_cal.edatetime DESC, phpgw_cal.priority ASC';

			return $this->get_event_ids(False,$sql,True);
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

		function get_event_ids($search_repeats=False, $sql='',$search_extra=False)
		{
			return $this->cal->get_event_ids($search_repeats,$sql,$search_extra);
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

		function save_alarm($cal_id,$alarm,$id=0)
		{
			$this->cal->save_alarm($cal_id,$alarm,$id);
		}

		function delete_alarm($id)
		{
			$this->cal->delete_alarm($id);
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
				$db2 = $this->cal->stream;
				$this->cal->stream->query('SELECT cal_id FROM phpgw_cal_user WHERE cal_login='.$account_id,__LINE__,__FILE__);
				while($this->cal->stream->next_record())
				{
					$id = $this->cal->stream->f('cal_id');
					$db2->query('SELECT count(*) FROM phpgw_cal_user WHERE cal_id='.$id.' AND cal_login='.$new_owner,__LINE__,__FILE__);
					$db2->next_record();
					if($db2->f(0) == 0)
					{
						$db2->query('UPDATE phpgw_cal_user SET cal_login='.$new_owner.' WHERE cal_id='.$id.' AND cal_login='.$account_id,__LINE__,__FILE__);
					}
					else
					{
						$db2->query('DELETE FROM phpgw_cal_user WHERE cal_id='.$id.' AND cal_login='.$account_id,__LINE__,__FILE__);
					}
				}
				$this->cal->stream->query('UPDATE phpgw_cal SET owner='.$new_owner.' WHERE owner='.$account_id,__LINE__,__FILE__);
			}
		}

		function set_status($id,$status)
		{
			$this->cal->set_status($id,$this->owner,$status);
		}

		function get_alarm($cal_id)
		{
			if (!method_exists($this->cal,'get_alarm'))
			{
				return False;
			}
			return $this->cal->get_alarm($cal_id);
		}

		function read_alarm($id)
		{
			if (!method_exists($this->cal,'read_alarm'))
			{
				return False;
			}
			return $this->cal->read_alarm($id);
		}

		function read_alarms($cal_id)
		{
			if (!method_exists($this->cal,'read_alarms'))
			{
				return False;
			}
			return $this->cal->read_alarms($cal_id);
		}

		function find_recur_exceptions($event_id)
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] == 'sql')
			{
				$arr = Array();
				$this->cal->query('SELECT datetime FROM phpgw_cal WHERE reference='.$event_id,__LINE__,__FILE__);
				if($this->cal->num_rows())
				{
					while($this->cal->next_record())
					{
						$arr[] = intval($this->cal->f('datetime'));
					}
				}
				if(count($arr) == 0)
				{
					return False;
				}
				else
				{
					return $arr;
				}
			}
			else
			{
				return False;
			}
		}

		/* Begin mcal equiv functions */
		function get_cached_event()
		{
			return $this->cal->event;
		}
		
		function add_attribute($var,$value,$element='**(**')
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
