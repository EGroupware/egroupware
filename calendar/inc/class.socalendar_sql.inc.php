<?php
	/**************************************************************************\
	* eGroupWare - Calendar                                                    *
	* http://www.eGroupWare.org                                                *
	* Maintained and further developed by RalfBecker@outdoor-training.de       *
	* Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
	*          http://www.radix.net/~cknudsen                                  *
	* Originaly modified by Mark Peters <skeeter@phpgroupware.org>             *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

	if (@$GLOBALS['phpgw_info']['flags']['included_classes']['socalendar_'])
	{
		return;
	}

	$GLOBALS['phpgw_info']['flags']['included_classes']['socalendar_'] = True;

	class socalendar_ extends socalendar__
	{
		var $deleted_events = Array();

		var $cal_event;
		var $today = Array('raw','day','month','year','full','dow','dm','bd');

		function socalendar_()
		{
			$this->socalendar__();

			if (!is_object($GLOBALS['phpgw']->asyncservice))
			{
				$GLOBALS['phpgw']->asyncservice = CreateObject('phpgwapi.asyncservice');
			}
			$this->async = &$GLOBALS['phpgw']->asyncservice;
			
			$this->table = 'phpgw_cal';
			$this->all_tables = array(
				'table'			=> $this->table,
				'user_table'	=> ($this->user_table  = $this->table.'_user'),
				'recur_table'	=> ($this->recur_table = $this->table.'_repeats'),
				'extra_table'	=> ($this->extra_table = $this->table.'_extra'),
			);
			$this->db = $GLOBALS['phpgw']->db;
			$this->db->set_app('calendar');
			$this->stream = &$this->db;	// legacy support
		}

		function open($calendar='',$user='',$passwd='',$options='')
		{
			if($user=='')
			{
				$this->user = $GLOBALS['phpgw_info']['user']['account_id'];
			}
			elseif(is_int($user))
			{
				$this->user = $user;
			}
			elseif(is_string($user))
			{
				$this->user = $GLOBALS['phpgw']->accounts->name2id($user);
			}
			return $this->db;
		}

		function popen($calendar='',$user='',$passwd='',$options='')
		{
			return $this->open($calendar,$user,$passwd,$options);
		}

		function reopen($calendar,$options='')
		{
			return $this->db;
		}

		function close($options='')
		{
			return True;
		}

		function create_calendar($calendar='')
		{
			return $calendar;
		}

		function rename_calendar($old_name='',$new_name='')
		{
			return $new_name;
		}

		function delete_calendar($calendar='')
		{
			$this->db->select($this->table,'cal_id',array('cal_owner' => $calendar),__LINE__,__FILE__);
			if($this->db->num_rows())
			{
				while($this->db->next_record())
				{
					$this->delete_event((int)$this->db->f('cal_id'));
				}
				$this->expunge();
			}
			$this->db->lock(array($this->user_table));
			$this->db->delete($this->user_table,array('cal_user_id' => $calendar),__LINE__,__FILE__);
			$this->db->unlock();

			return $calendar;
		}

		/*!
		@function read_alarms
		@abstract read the alarms of a calendar-event specified by $cal_id
		@returns array of alarms with alarm-id as key
		@note the alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
		*/
		function read_alarms($cal_id)
		{
			$alarms = array();

			if ($jobs = $this->async->read('cal:'.(int)$cal_id.':%'))
			{
				foreach($jobs as $id => $job)
				{
					$alarm         = $job['data'];	// text, enabled
					$alarm['id']   = $id;
					$alarm['time'] = $job['next'];

					$alarms[$id] = $alarm;
				}
			}
			return $alarms;
		}

		/*!
		@function read_alarm
		@abstract read a single alarm specified by it's $id
		@returns array with data of the alarm
		@note the alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
		*/
		function read_alarm($id)
		{
			if (!($jobs = $this->async->read($id)))
			{
				return False;
			}
			list($id,$job) = each($jobs);
			$alarm         = $job['data'];	// text, enabled
			$alarm['id']   = $id;
			$alarm['time'] = $job['next'];

			//echo "<p>read_alarm('$id')="; print_r($alarm); echo "</p>\n";
			return $alarm;
		}

		/*!
		@function save_alarm
		@abstract saves a new or updated alarm
		@syntax save_alarm($cal_id,$alarm,$id=False)
		@param $cal_id Id of the calendar-entry
		@param $alarm array with fields: text, owner, enabled, ..
		@returns the id of the alarm
		*/
		function save_alarm($cal_id,$alarm)
		{
			//echo "<p>save_alarm(cal_id=$cal_id, alarm="; print_r($alarm); echo ")</p>\n";
			if (!($id = $alarm['id']))
			{
				$alarms = $this->read_alarms($cal_id);	// find a free alarm#
				$n = count($alarms);
				do
				{
					$id = 'cal:'.(int)$cal_id.':'.$n;
					++$n;
				}
				while (@isset($alarms[$id]));
			}
			else
			{
				$this->async->cancel_timer($id);
			}
			$alarm['cal_id'] = $cal_id;		// we need the back-reference

			$alarm['time'] -= $GLOBALS['phpgw']->datetime->tz_offset;	// time should be stored in server timezone
			if (!$this->async->set_timer($alarm['time'],$id,'calendar.bocalendar.send_alarm',$alarm))
			{
				return False;
			}
			return $id;
		}

		/*!
		@function delete_alarms($cal_id)
		@abstract delete all alarms of a calendar-entry
		@returns the number of alarms deleted
		*/
		function delete_alarms($cal_id)
		{
			$alarms = $this->read_alarms($cal_id);

			foreach($alarms as $id => $alarm)
			{
				$this->async->cancel_timer($id);
			}
			return count($alarms);
		}

		/*!
		@function delete_alarm($id)
		@abstract delete one alarms identified by its id
		@returns the number of alarms deleted
		*/
		function delete_alarm($id)
		{
			return $this->async->cancel_timer($id);
		}

		function fetch_event($event_id,$options='')
		{
			if(!isset($this->db))
			{
				return False;
			}
			$this->db->lock($this->all_tables);

			$this->db->select($this->table,'*',array('cal_id'=>$event_id),__LINE__,__FILE__);

			if($this->db->num_rows() > 0)
			{
				$this->event_init();

				$this->db->next_record();
				// Load the calendar event data from the db into $event structure
				// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
				$this->add_attribute('owner',(int)$this->db->f('cal_owner'));
				$this->add_attribute('id',(int)$this->db->f('cal_id'));
				$this->set_class((int)$this->db->f('cal_public'));
				$this->set_category($this->db->f('cal_category'));
				$this->set_title(stripslashes($GLOBALS['phpgw']->strip_html($this->db->f('cal_title'))));
				$this->set_description(stripslashes($GLOBALS['phpgw']->strip_html($this->db->f('cal_description'))));
				$this->add_attribute('uid',$GLOBALS['phpgw']->strip_html($this->db->f('cal_uid')));
				$this->add_attribute('location',stripslashes($GLOBALS['phpgw']->strip_html($this->db->f('cal_location'))));
				$this->add_attribute('reference',(int)$this->db->f('cal_reference'));

				// This is the preferred method once everything is normalized...
				//$this->event->alarm = (int)$this->db->f('alarm');
				// But until then, do it this way...
				//Legacy Support (New)

				$datetime = $GLOBALS['phpgw']->datetime->localdates($this->db->f('cal_starttime'));
				$this->set_start($datetime['year'],$datetime['month'],$datetime['day'],$datetime['hour'],$datetime['minute'],$datetime['second']);

				$datetime = $GLOBALS['phpgw']->datetime->localdates($this->db->f('cal_modified'));
				$this->set_date('modtime',$datetime['year'],$datetime['month'],$datetime['day'],$datetime['hour'],$datetime['minute'],$datetime['second']);

				$datetime = $GLOBALS['phpgw']->datetime->localdates($this->db->f('cal_endtime'));
				$this->set_end($datetime['year'],$datetime['month'],$datetime['day'],$datetime['hour'],$datetime['minute'],$datetime['second']);

			//Legacy Support
				$this->add_attribute('priority',(int)$this->db->f('cal_priority'));
				if($this->db->f('cal_group') || $this->db->f('cal_groups') != 'NULL')
				{
					for($j=1;$j<count($groups) - 1;$j++)
					{
						$this->add_attribute('groups',$groups[$j],$j-1);
					}
				}

				$this->db->select($this->recur_table,'*',array('cal_id'=>$event_id),__LINE__,__FILE__);
				if($this->db->num_rows())
				{
					$this->db->next_record();

					$this->add_attribute('recur_type',(int)$this->db->f('recur_type'));
					$this->add_attribute('recur_interval',(int)$this->db->f('recur_interval'));
					$enddate = $this->db->f('recur_enddate');
					if($enddate != 0 && $enddate != Null)
					{
						$datetime = $GLOBALS['phpgw']->datetime->localdates($enddate);
						$this->add_attribute('recur_enddate',$datetime['year'],'year');
						$this->add_attribute('recur_enddate',$datetime['month'],'month');
						$this->add_attribute('recur_enddate',$datetime['day'],'mday');
						$this->add_attribute('recur_enddate',$datetime['hour'],'hour');
						$this->add_attribute('recur_enddate',$datetime['minute'],'min');
						$this->add_attribute('recur_enddate',$datetime['second'],'sec');
					}
					else
					{
						$this->add_attribute('recur_enddate',0,'year');
						$this->add_attribute('recur_enddate',0,'month');
						$this->add_attribute('recur_enddate',0,'mday');
						$this->add_attribute('recur_enddate',0,'hour');
						$this->add_attribute('recur_enddate',0,'min');
						$this->add_attribute('recur_enddate',0,'sec');
					}
					$this->add_attribute('recur_enddate',0,'alarm');
					if($this->debug)
					{
						echo 'Event ID#'.$this->event['id'].' : Enddate = '.$enddate."<br>\n";
					}
					$this->add_attribute('recur_data',$this->db->f('recur_data'));

					$exception_list = $this->db->f('recur_exception');
					$exceptions = Array();
					if(strpos(' '.$exception_list,','))
					{
						$exceptions = explode(',',$exception_list);
					}
					elseif($exception_list != '')
					{
						$exceptions[]= $exception_list;
					}
					$this->add_attribute('recur_exception',$exceptions);
				}

			//Legacy Support
				$this->db->select($this->user_table,'*',array('cal_id'=>$event_id),__LINE__,__FILE__);
				if($this->db->num_rows())
				{
					while($this->db->next_record())
					{
						if((int)$this->db->f('cal_user_id') == (int)$this->user)
						{
							$this->add_attribute('users_status',$this->db->f('cal_status'));
						}
						$this->add_attribute('participants',$this->db->f('cal_status'),(int)$this->db->f('cal_user_id'));
					}
				}

			// Custom fields
				$this->db->select($this->extra_table,'*',array('cal_id'=>$event_id),__LINE__,__FILE__);
				if($this->db->num_rows())
				{
					while($this->db->next_record())
					{
						$this->add_attribute('#'.$this->db->f('cal_extra_name'),$this->db->f('cal_extra_value'));
					}
				}
			}
			else
			{
				$this->event = False;
			}

			$this->db->unlock();

			if ($this->event)
			{
				$this->event['alarm'] = $this->read_alarms($event_id);

				if($this->event['reference'])
				{
					$this->event['alarm'] += $this->read_alarms($event_id);
				}
			}
			return $this->event;
		}

		function list_events($startYear,$startMonth,$startDay,$endYear=0,$endMonth=0,$endDay=0,$extra='',$tz_offset=0,$owner_id=0)
		{
			if(!isset($this->db))
			{
				return False;
			}

			$user_where = " AND ($this->user_table.cal_user_id IN (";
			if(is_array($owner_id) && count($owner_id))
			{
				array_walk($owner_id,create_function('$key,&$val','$val = (int) $val;'));
				$user_where .= implode(',',$owner_id);
			}
			else
			{
				$user_where .= (int)$this->user;
			}
/* why ???
			$member_groups = $GLOBALS['phpgw']->accounts->membership($this->user);
			@reset($member_groups);
			while($member_groups != False && list($key,$group_info) = each($member_groups))
			{
				$member[] = $group_info['account_id'];
			}
			@reset($member);
	//		$user_where .= ','.implode(',',$member);
*/
			$user_where .= ')) ';

			if($this->debug)
			{
				echo '<!-- '.$user_where.' -->'."\n";
			}

			$datetime = mktime(0,0,0,$startMonth,$startDay,$startYear) - $tz_offset;
			$startDate = "AND ( ( ($this->table.cal_starttime >= $datetime) ";

			$enddate = '';
			if($endYear != 0 && $endMonth != 0 && $endDay != 0)
			{
				$edatetime = mktime(23,59,59,(int)$endMonth,(int)$endDay,(int)$endYear) - $tz_offset;
				$endDate .= "AND ($this->table.cal_endtime <= $edatetime) ) "
					. "OR ( ($this->table.cal_starttime <= $datetime) "
					. "AND ($this->table.cal_endtime >= $edatetime) ) "
					. "OR ( ($this->table.cal_starttime >= $datetime) "
					. "AND ($this->table.cal_starttime <= $edatetime) "
					. "AND ($this->table.cal_endtime >= $edatetime) ) "
					. "OR ( ($this->table.cal_starttime <= $datetime) "
					. "AND ($this->table.cal_endtime >= $datetime) "
					. "AND ($this->table.cal_endtime <= $edatetime) ";
			}
			$endDate .= ') ) ';

			$order_by = "ORDER BY $this->table.cal_starttime ASC, $this->table.cal_endtime ASC, $this->table.cal_priority ASC";
			if($this->debug)
			{
				echo "SQL : ".$user_where.$startDate.$endDate.$extra."<br>\n";
			}
			return $this->get_event_ids(False,$user_where.$startDate.$endDate.$extra.$order_by);
		}

		function append_event()
		{
			$this->save_event($this->event);
			$this->send_update(MSG_ADDED,$this->event->participants,'',$this->event);
			return $this->event['id'];
		}

		function store_event()
		{
			return $this->save_event($this->event);
		}

		function delete_event($event_id)
		{
			$this->deleted_events[] = $event_id;
		}

		function snooze($event_id)
		{
		//Turn off an alarm for an event
		//Returns true.
		}

		function list_alarms($begin_year='',$begin_month='',$begin_day='',$end_year='',$end_month='',$end_day='')
		{
		//Return a list of events that has an alarm triggered at the given datetime
		//Returns an array of event ID's
		}

		// The function definition doesn't look correct...
		// Need more information for this function
		function next_recurrence($weekstart,$next)
		{
	//		return next_recurrence (int stream, int weekstart, array next);
		}

		function expunge()
		{
			if(count($this->deleted_events) <= 0)
			{
				return 1;
			}
			$this_event = $this->event;

			$this->db->lock($this->all_tables);
			foreach($this->deleted_events as $cal_id)
			{
				foreach ($this->all_tables as $table)
				{
					$this->db->delete($table,array('cal_id'=>$cal_id),__LINE__,__FILE__);
				}
			}
			$this->db->unlock();

			foreach($this->deleted_events as $cal_id)
			{
				$this->delete_alarms($cal_id);
			}
			$this->deleted_events = array();

			$this->event = $this_event;
			return 1;
		}

		/***************** Local functions for SQL based Calendar *****************/

		function get_event_ids($search_repeats=False,$extra='',$search_extra=False)
		{
			$from = $where = ' ';
			if($search_repeats)
			{
				$from  = ",$this->recur_table ";
				$where = "AND ($this->recur_table.cal_id = $this->table.cal_id) ";
			}
			if($search_extra)
			{
				$from  .= "LEFT JOIN $this->extra_table ON $this->extra_table.cal_id = $this->table.cal_id ";
			}

			$sql = "SELECT DISTINCT $this->table.cal_id,$this->table.cal_starttime,$this->table.cal_endtime,$this->table.cal_priority".
				" FROM $this->user_table,$this->table$from".
				" WHERE ($this->user_table.cal_id=$this->table.cal_id) $where $extra";

			if($this->debug)
			{
				echo "FULL SQL : ".$sql."<br>\n";
			}

			$this->db->query($sql,__LINE__,__FILE__);

			$retval = Array();
			if($this->db->num_rows() == 0)
			{
				if($this->debug)
				{
					echo "No records found!<br>\n";
				}
				return $retval;
			}

			while($this->db->next_record())
			{
				$retval[] = (int)$this->db->f('cal_id');
			}
			if($this->debug)
			{
				echo "Records found!<br>\n";
			}
			return $retval;
		}

		function generate_uid($event)
		{
			if (!$event['id']) return False;	// we need the id !!!

			$suffix = $GLOBALS['phpgw_info']['server']['hostname'] ? $GLOBALS['phpgw_info']['server']['hostname'] : 'local';
			$prefix = 'cal-'.$event['id'].'-'.$GLOBALS['phpgw_info']['server']['install_id'];
			return $prefix . '@' . $suffix;
		}

		function save_event(&$event)
		{
			$this->db->lock($this->all_tables);
			if($event['id'] == 0)
			{
				$this->db->insert($this->table,array(
					'cal_uid'		=> '*new*',
					'cal_title'		=> $event['title'],
					'cal_owner'		=> $event['owner'],
					'cal_priority'	=> $event['priority'],
					'cal_public'	=> $event['public'],
					'cal_category'	=> $event['category']
				),False,__LINE__,__FILE__);

				$event['id'] = $this->db->get_last_insert_id($this->table,'cal_id');
			}
			// new event or new created referencing event
			if (!$event['uid'] || $event['reference'] && strstr($event['uid'],'cal-'.$event['reference'].'-'))
			{
				$event['uid'] = $this->generate_uid($event);
			}
			$this->db->update($this->table,array(
				'cal_uid'		=> $event['uid'],
				'cal_owner' 	=> $event['owner'],
				'cal_starttime'	=> $this->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset,
				'cal_modified'	=> time() - $GLOBALS['phpgw']->datetime->tz_offset,
				'cal_endtime'	=> $this->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset,
				'cal_priority'	=> $event['priority'],
				'cal_category'	=> $event['category'],
				'cal_type'		=> $event['recur_type'] != MCAL_RECUR_NONE ? 'M' : 'E',
				'cal_public'	=> $event['public'],
				'cal_title'		=> $event['title'],
				'cal_description'=> $event['description'],
				'cal_location'	=> $event['location'],
				'cal_groups'	=> count($event['groups']) ? ','.implode(',',$event['groups']).',' : '',
				'cal_reference'	=> $event['reference'],
			),array('cal_id' => $event['id']),__LINE__,__FILE__);

			$this->db->delete($this->user_table,array('cal_id' => $event['id']),__LINE__,__FILE__);

			foreach($event['participants'] as $uid => $status)
			{
				$this->db->insert($this->user_table,array(
					'cal_id'		=> $event['id'],
					'cal_user_id' 	=> $uid,
					'cal_status'	=> (int)$uid == $event['owner'] ? 'A' : $status,
				),False,__LINE__,__FILE__);
			}

			if($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$this->db->insert($this->recur_table,array(
					'recur_type'	 => $event['recur_type'],
					'recur_enddate' => $event['recur_enddate']['month'] != 0 && $event['recur_enddate']['mday'] != 0 && $event['recur_enddate']['year'] != 0 ?
						$this->maketime($event['recur_enddate']) - $GLOBALS['phpgw']->datetime->tz_offset : 0,
					'recur_data'	 => $event['recur_data'],
					'recur_interval' => $event['recur_interval'],
					'recur_exception'=> is_array($event['recur_exception']) ? implode(',',$event['recur_exception']) : '',
				),array('cal_id' => $event['id']),__LINE__,__FILE__);
			}
			else
			{
				$this->db->delete($this->recur_table,array('cal_id' => $event['id']),__LINE__,__FILE__);
			}
			// Custom fields
			$this->db->delete($this->extra_table,array('cal_id' => $event['id']),__LINE__,__FILE__);

			foreach($event as $name => $value)
			{
				if ($name[0] == '#' && strlen($value))
				{
					$this->db->insert($this->extra_table,array(
						'cal_id'			=> $event['id'],
						'cal_extra_name'	=> substr($name,1),
						'cal_extra_value'	=> $value,
					),False,__LINE__,__FILE__);
				}
			}
			print_debug('Event Saved: ID #',$event['id']);

			$this->db->unlock();

			if (is_array($event['alarm']))
			{
				foreach ($event['alarm'] as $alarm)	// this are all new alarms
				{
					$this->save_alarm($event['id'],$alarm);
				}
			}
			$GLOBALS['phpgw_info']['cal_new_event_id'] = $event['id'];
			$this->event = $event;

			return True;
		}

		function get_alarm($cal_id)
		{
			$alarms = $this->read_alarms($cal_id);
			$ret = False;

			foreach($alarms as $alarm)
			{
				if ($alarm['owner'] == $this->user || !$alarm['owner'])
				{
					$ret[$alarm['time']] = $alarm['text'];
				}
			}
			return $ret;
		}

		function set_status($id,$owner,$status)
		{
			$status_code_short = Array(
				REJECTED 	=> 'R',
				NO_RESPONSE	=> 'U',
				TENTATIVE	=> 'T',
				ACCEPTED	=> 'A'
			);

			$this->db->update($this->user_table,array(
				'cal_status'	=> $status_code_short[$status],
			),array(
				'cal_id'		=> $id,
				'cal_user_id'	=> $owner,
			),__LINE__,__FILE__);

			return True;
		}

	// End of ICal style support.......

		function group_search($owner=0)
		{
			$owner = ($owner==$GLOBALS['phpgw_info']['user']['account_id']?0:$owner);
			$groups = substr($GLOBALS['phpgw']->common->sql_search("$this->table.groups",(int)$owner),4);
			if (!$groups)
			{
				return '';
			}
			else
			{
				return "($this->table.is_public=2 AND (". $groups .')) ';
			}
		}

		function splittime_($time)
		{
			$temp = array('hour','minute','second','ampm');
			$time = strrev($time);
			$second = (int)strrev(substr($time,0,2));
			$minute = (int)strrev(substr($time,2,2));
			$hour   = (int)strrev(substr($time,4));
			$temp['second'] = (int)$second;
			$temp['minute'] = (int)$minute;
			$temp['hour']   = (int)$hour;
			$temp['ampm']   = '  ';

			return $temp;
		}

		function date_to_epoch($d)
		{
			return $this->localdates(mktime(0,0,0,(int)(substr($d,4,2)),(int)(substr($d,6,2)),(int)(substr($d,0,4))));
		}

		function list_dirty_events($lastmod=-1,$repeats=false)
		{
			if(!isset($this->db))
			{
				return False;
			}
			$lastmod = (int)  $lastmod;
			$repeats = (bool) $repeats;

			$user_where = " AND $this->user_table.cal_user_id=".(int)$this->user;
/* why not used ???
			if ($member_groups = $GLOBALS['phpgw']->accounts->membership($this->user))
			{
				foreach($member_groups as $key => $group_info)
				{
					$member[] = $group_info['account_id'];
				}
			}
			$user_where .= ','.implode(',',$member) . ')) ';
*/
			if($this->debug)
			{
				echo '<!-- '.$user_where.' -->'."\n";
			}

			if($lastmod > 0)
			{
				$wheremod = "AND $this->table.cal_modified=".(int)$lastmod;
			}

			$order_by = " ORDER BY $this->table.cal_id ASC";
			if($this->debug)
			{
				echo "SQL : ".$user_where.$wheremod.$extra."<br>\n";
			}
			return $this->get_event_ids($repeats,$user_where.$wheremod.$extra.$order_by);
		}
	}
