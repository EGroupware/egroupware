<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@mail.com>                                  *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class socalendar
	{
		var $cal;
		var $rights;

		var $db;
		
		var $owner;
		var $datetime;

		function socalendar()
		{
			global $phpgw, $rights, $owner;

			$this->db = $phpgw->db;
			$this->rights = $rights;
			if(isset($owner)) { $this->owner = $owner; }
			$this->datetime = CreateObject('phpgwapi.datetime');
		}

		function makeobj()
		{
			if (!is_object($this->cal))
			{
				$this->cal = CreateObject('calendar.socalendar_');
				$this->cal->open('INBOX',intval($this->owner));
			}
			return;
		}

		function read_entry($id)
		{
			$this->makeobj();
			if ($this->rights & PHPGW_ACL_READ)
			{
				return $this->cal->fetch_event($id);
			}
			else
			{
				$rtrn = array('No access' => 'No access');
				return $rtrn;
			}
		}

		function list_events($startYear,$startMonth,$startDay,$endYear='',$endMonth='',$endDay='')
		{
			$this->makeobj();
			return $this->cal->list_events($startYear,$startMonth,$startDay,$endYear,$endMonth,$endDay,$this->datetime->tz_offset);
		}

		function list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday)
		{
			global $phpgw, $phpgw_info;
			
			if($phpgw_info['server']['calendar_type'] != 'sql')
			{
				return Array();
			}

			$starttime = mktime(0,0,0,$smonth,$sday,$syear);
			$endtime = mktime(23,59,59,$emonth,$eday,$eyear);
			$sql = "AND (phpgw_cal.cal_type='M') "
				. 'AND (phpgw_cal_user.cal_login='.$this->owner.' '
				. 'AND (phpgw_cal.datetime >= '.$starttime.') '
				. 'AND (((phpgw_cal_repeats.recur_enddate >= '.$starttime.') AND (phpgw_cal_repeats.recur_enddate <= '.$endtime.')) OR (phpgw_cal_repeats.recur_enddate=0))) '
				. 'ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

			$events = $this->get_event_ids(True,$sql);

			return $events;
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
			$this->makeobj();
			return $this->cal->get_event_ids($include_repeats,$sql);
		}

		function add_entry($userid,$fields)
		{
			$this->makeobj();
			if ($this->rights & PHPGW_ACL_ADD)
			{
				$this->cal->add($userid,$fields,$fields['access'],$fields['cat_id'],$fields['tid']);
			}
			return;
		}

		function get_lastid()
		{
			$this->makeobj();
		 	$entry = $this->cal->read_last_entry();
			$ab_id = $entry[0]['id'];
			return $ab_id;
		}

		function update_entry($userid,$fields)
		{
			$this->makeobj();
			if ($this->rights & PHPGW_ACL_EDIT)
			{
				$this->cal->update($fields['ab_id'],$userid,$fields,$fields['access'],$fields['cat_id']);
			}
			return;
		}

		function delete_entry($ab_id)
		{
			$this->makeobj();
			if ($this->rights & PHPGW_ACL_DELETE)
			{
				$this->cal->delete($ab_id);
			}
			return;
		}

		/* Begin Holiday functions */
		function save_holiday($holiday)
		{
			if(isset($holiday['hol_id']) && $holiday['hol_id'])
			{
//				echo "Updating LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				$sql = "UPDATE phpgw_cal_holidays SET name='".$holiday['name']."', mday=".$holiday['mday'].', month_num='.$holiday['month_num'].', occurence='.$holiday['occurence'].', dow='.$holiday['dow'].', observance_rule='.intval($holiday['observance_rule']).' WHERE hol_id='.$holiday['hol_id'];
			}
			else
			{
//				echo "Inserting LOCALE='".$holiday['locale']."' NAME='".$holiday['name']."' extra=(".$holiday['mday'].'/'.$holiday['month_num'].'/'.$holiday['occurence'].'/'.$holiday['dow'].'/'.$holiday['observance_rule'].")<br>\n";
				$sql = 'INSERT INTO phpgw_cal_holidays(locale,name,mday,month_num,occurence,dow,observance_rule) '
						. "VALUES('".strtoupper($holiday['locale'])."','".$holiday['name']."',".$holiday['mday'].','.$holiday['month_num'].','.$holiday['occurence'].','.$holiday['dow'].','.intval($holiday['observance_rule']).")";
			}
			$this->db->query($sql,__LINE__,__FILE__);
		}

		function read_holidays($sql)
		{
			global $phpgw;
			
			$this->db->query($sql,__LINE__,__FILE__);
			$holidays = Array();
			while($this->db->next_record())
			{
				$holidays[] = Array(
					'index'				=> $this->db->f('hol_id'),
					'locale'				=> $this->db->f('locale'),
					'name'				=> $phpgw->strip_html($this->db->f('name')),
					'day'					=> intval($this->db->f('mday')),
					'month'				=> intval($this->db->f('month_num')),
					'occurence'			=> intval($this->db->f('occurence')),
					'dow'					=> intval($this->db->f('dow')),
					'observance_rule'	=> $this->db->f('observance_rule')
				);
			}

			return $holidays;
		}

		/* Private functions */
		/* Holiday */
		function count_of_holidays($locale)
		{
			$sql = "SELECT count(*) FROM phpgw_cal_holidays WHERE locale='".$locale."'";
			$this->db->query($sql,__LINE__,__FILE__);
			$this->db->next_record();
			return $this->db->f(0);
		}
		/* End Holiday functions */

		/* Begin mcal equiv functions */
		function get_cached_event()
		{
			return $this->cal->event;
		}
		
		function add_attribute($var,$value)
		{
			$this->cal->add_attribute($var,$value);
		}

		function event_init()
		{
			$this->makeobj();
			$this->cal->event_init();
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
