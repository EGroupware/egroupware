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
	}

	function open($calendar='',$user='',$passwd='',$options='')
	{
		if($user=='')
		{
//			settype($user,'integer');
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

		$this->stream = $GLOBALS['phpgw']->db;
		return $this->stream;
	}

	function popen($calendar='',$user='',$passwd='',$options='')
	{
		return $this->open($calendar,$user,$passwd,$options);
	}

	function reopen($calendar,$options='')
	{
		return $this->stream;
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
		$this->stream->query('SELECT cal_id FROM phpgw_cal WHERE owner='.intval($calendar),__LINE__,__FILE__);
		if($this->stream->num_rows())
		{
			while($this->stream->next_record())
			{
				$this->delete_event(intval($this->stream->f('cal_id')));
			}
			$this->expunge();
		}
		$this->stream->lock(array('phpgw_cal_user'));
		$this->stream->query('DELETE FROM phpgw_cal_user WHERE cal_login='.intval($calendar),__LINE__,__FILE__);
		$this->stream->unlock();
			
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

		if ($jobs = $this->async->read('cal:'.intval($cal_id).':%'))
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
				$id = 'cal:'.intval($cal_id).':'.$n;
				++$n;
			}
			while (@isset($alarms[$id]));
		}
		else
		{
			$this->async->cancel_timer($id);
		}
		$alarm['cal_id'] = $cal_id;		// we need the back-reference

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
		if(!isset($this->stream))
		{
			return False;
		}

		$event_id = intval($event_id);

		$this->stream->lock(array('phpgw_cal','phpgw_cal_user','phpgw_cal_repeats','phpgw_cal_extra'/* OLD-ALARM,'phpgw_cal_alarm'*/));

		$this->stream->query('SELECT * FROM phpgw_cal WHERE cal_id='.$event_id,__LINE__,__FILE__);
		
		if($this->stream->num_rows() > 0)
		{
			$this->event_init();
			
			$this->stream->next_record();
			// Load the calendar event data from the db into $event structure
			// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
			$this->add_attribute('owner',intval($this->stream->f('owner')));
			$this->add_attribute('id',intval($this->stream->f('cal_id')));
			$this->set_class(intval($this->stream->f('is_public')));
			$this->set_category($this->stream->f('category'));
			$this->set_title(stripslashes($GLOBALS['phpgw']->strip_html($this->stream->f('title'))));
			$this->set_description(stripslashes($GLOBALS['phpgw']->strip_html($this->stream->f('description'))));
			$this->add_attribute('uid',$GLOBALS['phpgw']->strip_html($this->stream->f('uid')));
			$this->add_attribute('location',stripslashes($GLOBALS['phpgw']->strip_html($this->stream->f('location'))));
			$this->add_attribute('reference',intval($this->stream->f('reference')));
			
			// This is the preferred method once everything is normalized...
			//$this->event->alarm = intval($this->stream->f('alarm'));
			// But until then, do it this way...
		//Legacy Support (New)

			$datetime = $GLOBALS['phpgw']->datetime->localdates($this->stream->f('datetime'));
			$this->set_start($datetime['year'],$datetime['month'],$datetime['day'],$datetime['hour'],$datetime['minute'],$datetime['second']);

			$datetime = $GLOBALS['phpgw']->datetime->localdates($this->stream->f('mdatetime'));
			$this->set_date('modtime',$datetime['year'],$datetime['month'],$datetime['day'],$datetime['hour'],$datetime['minute'],$datetime['second']);

			$datetime = $GLOBALS['phpgw']->datetime->localdates($this->stream->f('edatetime'));
			$this->set_end($datetime['year'],$datetime['month'],$datetime['day'],$datetime['hour'],$datetime['minute'],$datetime['second']);

		//Legacy Support
			$this->add_attribute('priority',intval($this->stream->f('priority')));
			if($this->stream->f('cal_group') || $this->stream->f('groups') != 'NULL')
			{
				$groups = explode(',',$this->stream->f('groups'));
				for($j=1;$j<count($groups) - 1;$j++)
				{
					$this->add_attribute('groups',$groups[$j],$j-1);
				}
			}
			
			$this->stream->query('SELECT * FROM phpgw_cal_repeats WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				$this->stream->next_record();

				$this->add_attribute('recur_type',intval($this->stream->f('recur_type')));
				$this->add_attribute('recur_interval',intval($this->stream->f('recur_interval')));
				$enddate = $this->stream->f('recur_enddate');
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
				$this->add_attribute('recur_data',$this->stream->f('recur_data'));

				$exception_list = $this->stream->f('recur_exception');
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
			$this->stream->query('SELECT * FROM phpgw_cal_user WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				while($this->stream->next_record())
				{
					if(intval($this->stream->f('cal_login')) == intval($this->user))
					{
						$this->add_attribute('users_status',$this->stream->f('cal_status'));
					}
					$this->add_attribute('participants',$this->stream->f('cal_status'),intval($this->stream->f('cal_login')));
				}
			}

		// Custom fields
			$this->stream->query('SELECT * FROM phpgw_cal_extra WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				while($this->stream->next_record())
				{
					$this->add_attribute('#'.$this->stream->f('cal_extra_name'),$this->stream->f('cal_extra_value'));
				}
			}

/* OLD-ALARM
			if($this->event['reference'])
			{
				// What is event['reference']???
				$alarm_cal_id = $event_id.','.$this->event['reference'];
			}
			else
			{
				$alarm_cal_id = $event_id;
			}

			//echo '<!-- cal_id='.$alarm_cal_id.' -->'."\n";
			//$this->stream->query('SELECT * FROM phpgw_cal_alarm WHERE cal_id in ('.$alarm_cal_id.') AND cal_owner='.$this->user,__LINE__,__FILE__);
			$this->stream->query('SELECT * FROM phpgw_cal_alarm WHERE cal_id='.$event_id.' AND cal_owner='.$this->user,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				while($this->stream->next_record())
				{
					$this->event['alarm'][] = Array(
						'id'		=> intval($this->stream->f('alarm_id')),
						'time'	=> intval($this->stream->f('cal_time')),
						'text'	=> $this->stream->f('cal_text'),
						'enabled'	=> intval($this->stream->f('alarm_enabled'))
					);
				}
			}
*/
		}
		else
		{
			$this->event = False;
		}
      
		$this->stream->unlock();

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
		if(!isset($this->stream))
		{
			return False;
		}

		$datetime = mktime(0,0,0,$startMonth,$startDay,$startYear) - $tz_offset;
		
		$user_where = ' AND (phpgw_cal_user.cal_login in (';
		if($owner_id)
		{
			$user_where .= implode(',',$owner_id);
		}
		else
		{
			$user_where .= $this->user;
		}
		$member_groups = $GLOBALS['phpgw']->accounts->membership($this->user);
		@reset($member_groups);
		while($member_groups != False && list($key,$group_info) = each($member_groups))
		{
			$member[] = $group_info['account_id'];
		}
		@reset($member);
//		$user_where .= ','.implode(',',$member);
		$user_where .= ')) ';

		if($this->debug)
		{
			echo '<!-- '.$user_where.' -->'."\n";
		}

		$startDate = 'AND ( ( (phpgw_cal.datetime >= '.$datetime.') ';

		$enddate = '';
		if($endYear != 0 && $endMonth != 0 && $endDay != 0)
		{
			$edatetime = mktime(23,59,59,intval($endMonth),intval($endDay),intval($endYear)) - $tz_offset;
			$endDate .= 'AND (phpgw_cal.edatetime <= '.$edatetime.') ) '
				. 'OR ( (phpgw_cal.datetime <= '.$datetime.') '
				. 'AND (phpgw_cal.edatetime >= '.$edatetime.') ) '
				. 'OR ( (phpgw_cal.datetime >= '.$datetime.') '
				. 'AND (phpgw_cal.datetime <= '.$edatetime.') '
				. 'AND (phpgw_cal.edatetime >= '.$edatetime.') ) '
				. 'OR ( (phpgw_cal.datetime <= '.$datetime.') '
				. 'AND (phpgw_cal.edatetime >= '.$datetime.') '
				. 'AND (phpgw_cal.edatetime <= '.$edatetime.') ';
		}
		$endDate .= ') ) ';

		$order_by = 'ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';
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
		return $this->save_event(&$this->event);
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
		$locks = Array(
			'phpgw_cal',
			'phpgw_cal_user',
			'phpgw_cal_repeats',
			'phpgw_cal_extra'
// OLD-ALARM			'phpgw_cal_alarm'
		);
		$this->stream->lock($locks);
		foreach($this->deleted_events as $cal_id)
		{
			foreach ($locks as $table)
			{
				$this->stream->query('DELETE FROM '.$table.' WHERE cal_id='.$cal_id,__LINE__,__FILE__);
			}
		}
		$this->stream->unlock();

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
			$from  = ', phpgw_cal_repeats ';
			$where = 'AND (phpgw_cal_repeats.cal_id = phpgw_cal.cal_id) ';
		}
		if($search_extra)
		{
			$from  .= 'LEFT JOIN phpgw_cal_extra ON phpgw_cal_extra.cal_id = phpgw_cal.cal_id ';
		}

		$sql = 'SELECT DISTINCT phpgw_cal.cal_id,'
				. 'phpgw_cal.datetime,phpgw_cal.edatetime,'
				. 'phpgw_cal.priority '
				. 'FROM phpgw_cal, phpgw_cal_user'
				. $from
				. 'WHERE (phpgw_cal_user.cal_id = phpgw_cal.cal_id) '
				. $where . $extra;

		if($this->debug)
		{
			echo "FULL SQL : ".$sql."<br>\n";
		}
		
		$this->stream->query($sql,__LINE__,__FILE__);

		$retval = Array();
		if($this->stream->num_rows() == 0)
		{
			if($this->debug)
			{
				echo "No records found!<br>\n";
			}
			return $retval;
		}

		while($this->stream->next_record())
		{
			$retval[] = intval($this->stream->f('cal_id'));
		}
		if($this->debug)
		{
			echo "Records found!<br>\n";
		}
		return $retval;
	}

	function save_event(&$event)
	{
		$locks = Array(
			'phpgw_cal',
			'phpgw_cal_user',
			'phpgw_cal_repeats',
			'phpgw_cal_extra'
// OLD-ALARM			'phpgw_cal_alarm'
		);
		$this->stream->lock($locks);
		if($event['id'] == 0)
		{
			if(!$event['uid'])
			{
				if ($GLOBALS['phpgw_info']['server']['hostname'] != '')
				{
					$id_suffix = $GLOBALS['phpgw_info']['server']['hostname'];
				}
				else
				{
					$id_suffix = $GLOBALS['phpgw']->common->randomstring(3).'local';
				}
				$parts = Array(
					0 => 'title',
					1 => 'description'
				);
				@reset($parts);
				while(list($key,$field) = each($parts))
				{
					$part[$key] = substr($GLOBALS['phpgw']->crypto->encrypt($event[$field]),0,20);
					if(!$GLOBALS['phpgw']->crypto->enabled)
					{
						$part[$key] = bin2hex(unserialize($part[$key]));
					}
				}
				$event['uid'] = $part[0].'-'.$part[1].'@'.$id_suffix;
			}
			$this->stream->query('INSERT INTO phpgw_cal(uid,title,owner,priority,is_public,category) '
				. "values('".$event['uid']."','".$this->stream->db_addslashes($event['title'])
				. "',".$event['owner'].','.$event['priority'].','.$event['public'].",'"
				. $event['category']."')",__LINE__,__FILE__);
			$event['id'] = $this->stream->get_last_insert_id('phpgw_cal','cal_id');
		}

		$date = $this->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset;
		$enddate = $this->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset;
		$today = time() - $GLOBALS['phpgw']->datetime->tz_offset;

		if($event['recur_type'] != MCAL_RECUR_NONE)
		{
			$type = 'M';
		}
		else
		{
			$type = 'E';
		}

		$sql = 'UPDATE phpgw_cal SET '
				. 'owner='.$event['owner'].', '
				. 'datetime='.$date.', '
				. 'mdatetime='.$today.', '
				. 'edatetime='.$enddate.', '
				. 'priority='.$event['priority'].', '
				. "category='".$event['category']."', "
				. "cal_type='".$type."', "
				. 'is_public='.$event['public'].', '
				. "title='".$this->stream->db_addslashes($event['title'])."', "
				. "description='".$this->stream->db_addslashes($event['description'])."', "
				. "location='".$this->stream->db_addslashes($event['location'])."', "
				. ($event['groups']?"groups='".(count($event['groups'])>1?implode(',',$event['groups']):','.$event['groups'][0].',')."', ":'')
				. 'reference='.$event['reference'].' '
				. 'WHERE cal_id='.$event['id'];
				
		$this->stream->query($sql,__LINE__,__FILE__);
		
		$this->stream->query('DELETE FROM phpgw_cal_user WHERE cal_id='.$event['id'],__LINE__,__FILE__);

		@reset($event['participants']);
		while (list($key,$value) = @each($event['participants']))
		{
			if(intval($key) == $event['owner'])
			{
				$value = 'A';
			}
			$this->stream->query('INSERT INTO phpgw_cal_user(cal_id,cal_login,cal_status) '
				. 'VALUES('.$event['id'].','.intval($key).",'".$value."')",__LINE__,__FILE__);
		}

		if($event['recur_type'] != MCAL_RECUR_NONE)
		{
			if($event['recur_enddate']['month'] != 0 && $event['recur_enddate']['mday'] != 0 && $event['recur_enddate']['year'] != 0)
			{
				$end = $this->maketime($event['recur_enddate']) - $GLOBALS['phpgw']->datetime->tz_offset;
			}
			else
			{
				$end = 0;
			}

			$this->stream->query('SELECT count(cal_id) FROM phpgw_cal_repeats WHERE cal_id='.$event['id'],__LINE__,__FILE__);
			$this->stream->next_record();
			$num_rows = $this->stream->f(0);
			if($num_rows == 0)
			{
				$this->stream->query('INSERT INTO phpgw_cal_repeats(cal_id,recur_type,recur_enddate,recur_data,recur_interval) '
					.'VALUES('.$event['id'].','.$event['recur_type'].','.$end.','.$event['recur_data'].','.$event['recur_interval'].')',__LINE__,__FILE__);
			}
			else
			{
				$this->stream->query('UPDATE phpgw_cal_repeats '
					. 'SET recur_type='.$event['recur_type'].', '
					. 'recur_enddate='.$end.', '
					. 'recur_data='.$event['recur_data'].', '
					. 'recur_interval='.$event['recur_interval'].', '
					. "recur_exception='".(count($event['recur_exception'])>1?implode(',',$event['recur_exception']):(count($event['recur_exception'])==1?$event['recur_exception'][0]:''))."' "
					. 'WHERE cal_id='.$event['id'],__LINE__,__FILE__);
			}
		}
		else
		{
			$this->stream->query('DELETE FROM phpgw_cal_repeats WHERE cal_id='.$event['id'],__LINE__,__FILE__);
		}
		// Custom fields
		$this->stream->query('DELETE FROM phpgw_cal_extra WHERE cal_id='.$event['id'],__LINE__,__FILE__);

		foreach($event as $name => $value)
		{
			if ($name[0] == '#' && strlen($value))
			{
				$this->stream->query('INSERT INTO phpgw_cal_extra (cal_id,cal_extra_name,cal_extra_value) '
				. 'VALUES('.$event['id'].",'".addslashes(substr($name,1))."','".addslashes($value)."')",__LINE__,__FILE__);
			}
		}
/*
		$alarmcount = count($event['alarm']);
		if ($alarmcount > 1)
		{
			// this should never happen, $event['alarm'] should only be set
			// if creating a new event and uicalendar only sets up 1 alarm
			// the user must use "Alarm Management" to create/establish multiple
			// alarms or to edit/change an alarm
			echo '<!-- how did this happen, too many alarms -->'."\n";
			$this->stream->unlock();
			return True;
		}

		if ($alarmcount == 1)
		{

			list($key,$alarm) = @each($event['alarm']);

			$this->stream->query('INSERT INTO phpgw_cal_alarm(cal_id,cal_owner,cal_time,cal_text,alarm_enabled) VALUES('.$event['id'].','.$event['owner'].','.$alarm['time'].",'".$alarm['text']."',".$alarm['enabled'].')',__LINE__,__FILE__);
			$this->stream->query('SELECT LAST_INSERT_ID()');
			$this->stream->next_record();
			$alarm['id'] = $this->stream->f(0);
		}
*/
		print_debug('Event Saved: ID #',$event['id']);

		$this->stream->unlock();

		if (is_array($event['alarm']))
		{
			foreach ($event['alarm'] as $alarm)	// this are all new alarms
			{
				$this->save_alarm($event['id'],$alarm);
			}
		}
		$GLOBALS['phpgw_info']['cal_new_event_id'] = $event['id']; 
		return True;
	}

	function get_alarm($cal_id)
	{
/* OLD-ALARM		
		$this->stream->query('SELECT cal_time, cal_text FROM phpgw_cal_alarm WHERE cal_id='.$id.' AND cal_owner='.$this->user,__LINE__,__FILE__);
		if($this->stream->num_rows())
		{
			while($this->stream->next_record())
			{
				$alarm[$this->stream->f('cal_time')] = $this->stream->f('cal_text');
			}
			@reset($alarm);
			return $alarm;
		}
		else
		{
			return False;
		}
*/
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
			REJECTED =>	'R',
			NO_RESPONSE	=> 'U',
			TENTATIVE	=>	'T',
			ACCEPTED	=>	'A'
		);
		
		$this->stream->query("UPDATE phpgw_cal_user SET cal_status='".$status_code_short[$status]."' WHERE cal_id=".$id." AND cal_login=".$owner,__LINE__,__FILE__);
/* OLD-ALARM
		if ($status == 'R')
		{
			$this->stream->query('UPDATE phpgw_cal_alarm set alarm_enabled=0 where cal_id='.$id.' and cal_owner='.$owner,__LINE__,__FILE__);
		}
*/
		return True;
	}
	
// End of ICal style support.......

	function group_search($owner=0)
	{
		$owner = ($owner==$GLOBALS['phpgw_info']['user']['account_id']?0:$owner);
		$groups = substr($GLOBALS['phpgw']->common->sql_search('phpgw_cal.groups',intval($owner)),4);
		if (!$groups)
		{
			return '';
		}
		else
		{
			return "(phpgw_cal.is_public=2 AND (". $groups .')) ';
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
		return $this->localdates(mktime(0,0,0,intval(substr($d,4,2)),intval(substr($d,6,2)),intval(substr($d,0,4))));
	}

	function list_dirty_events($lastmod=-1,$repeats=false)
	{
		if(!isset($this->stream))
		{
			return False;
		}
		$lastmod = intval($lastmod);
		$repeats = (bool) $repeats;

		$user_where = " AND phpgw_cal_user.cal_login = $this->user";

		$member_groups = $GLOBALS['phpgw']->accounts->membership($this->user);
		@reset($member_groups);
		while($member_groups != False && list($key,$group_info) = each($member_groups))
		{
			$member[] = $group_info['account_id'];
		}
		@reset($member);
//		$user_where .= ','.implode(',',$member);
		//$user_where .= ')) ';

		if($this->debug)
		{
			echo '<!-- '.$user_where.' -->'."\n";
		}

		if($lastmod > 0)
		{
			$wheremod = "AND mdatetime = $lastmod"; 
		}
		
		$order_by = ' ORDER BY phpgw_cal.cal_id ASC';
		if($this->debug)
		{
			echo "SQL : ".$user_where.$wheremod.$extra."<br>\n";
		}
		return $this->get_event_ids($repeats,$user_where.$wheremod.$extra.$order_by);
	}

/* OLD-ALARM
	function add_alarm($eventid,$alarm,$owner)
	{
		$this->stream->query('INSERT INTO phpgw_cal_alarm(cal_id,cal_owner,cal_time,cal_text,alarm_enabled) VALUES('.$eventid.','.$owner.','.$alarm['time'].",'".$alarm['text']."',1)",__LINE__,__FILE__);
		$this->stream->query('SELECT LAST_INSERT_ID()');
		$this->stream->next_record();
		return($this->stream->f(0));
	}
	function delete_alarm($alarmid)
	{
		$this->stream->query('DELETE FROM phpgw_cal_alarm WHERE alarm_id='.$alarmid,__LINE__,__FILE__);
	}
*/
}
