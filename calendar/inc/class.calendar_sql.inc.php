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

if ($phpgw_info['flags']['included_classes']['calendar_'] == True)
{
	return;
}

$phpgw_info['flags']['included_classes']['calendar_'] = True;

class calendar_ extends calendar__
{
	var $deleted_events = Array();
	
	var $cal_event;
	var $today = Array('raw','day','month','year','full','dow','dm','bd');

	function open($calendar='',$user='',$passwd='',$options='')
	{
		global $phpgw, $phpgw_info;

		$this->stream = $phpgw->db;
		if($user=='')
		{
			settype($user,'integer');
			$user = $phpgw_info['user']['account_id'];
		}
		elseif(is_int($user)) 
		{
			$this->user = $user;
		}
		elseif(is_string($user))
		{
			$this->user = $phpgw->accounts->name2id($user);
		}

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

	function close($mcal_stream,$options='')
	{
		return True;
	}

	function create_calendar($stream='',$calendar='')
	{
		return $calendar;
	}

	function rename_calendar($stream='',$old_name='',$new_name='')
	{
		return $new_name;
	}
    
	function delete_calendar($stream='',$calendar='')
	{
		$this->stream->query('SELECT cal_id FROM phpgw_cal WHERE owner='.$calendar,__LINE__,__FILE__);
		if($this->stream->num_rows())
		{
			while($this->stream->next_record())
			{
				$this->delete_event($stream,intval($this->stream->f('cal_id')));
			}
			$this->expunge($stream);
		}
		$this->stream->lock(array('phpgw_cal_user'));
		$this->stream->query('DELETE FROM phpgw_cal_user WHERE cal_login='.$calendar,__LINE__,__FILE__);
		$this->stream->unlock();
			
		return $calendar;
	}

	function fetch_event($mcal_stream,$event_id,$options='')
	{
		global $phpgw;
		
		if(!isset($this->stream))
		{
			return False;
		}
	  
		$this->stream->lock(array('phpgw_cal','phpgw_cal_user','phpgw_cal_repeats'));

		$this->stream->query('SELECT * FROM phpgw_cal WHERE cal_id='.$event_id,__LINE__,__FILE__);
		
		if($this->stream->num_rows() > 0)
		{
			$this->event = CreateObject('calendar.calendar_item');
			$this->event->start = new calendar_time;
			$this->event->end = new calendar_time;
			$this->event->mod = new calendar_time;
			$this->event->recur_enddate = new calendar_time;

			$this->stream->next_record();
			// Load the calendar event data from the db into $event structure
			// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
			
			$this->event->owner = $this->stream->f('owner');
			$this->event->id = intval($this->stream->f('cal_id'));
			$this->event->public = intval($this->stream->f('is_public'));
			$this->event->category = intval($this->stream->f('category'));
			$this->event->title = $phpgw->strip_html($this->stream->f('title'));
			$this->event->description = $phpgw->strip_html($this->stream->f('description'));
			
			// This is the preferred method once everything is normalized...
			//$this->event->alarm = intval($this->stream->f('alarm'));
			// But until then, do it this way...
		//Legacy Support (New)
			$this->event->alarm = 0;
			
			$this->event->datetime = $this->stream->f('datetime');
			$datetime = $this->localdates($this->stream->f('datetime'));
			$this->event->start->year	= $datetime['year'];
			$this->event->start->month	= $datetime['month'];
			$this->event->start->mday	= $datetime['day'];
			$this->event->start->hour	= $datetime['hour'];
			$this->event->start->min	= $datetime['minute'];
			$this->event->start->sec	= $datetime['second'];
			$this->event->start->alarm	= 0;

			$this->event->mdatetime = $this->stream->f('mdatetime');
			$datetime = $this->localdates($this->stream->f('mdatetime'));
			$this->event->mod->year	= $datetime['year'];
			$this->event->mod->month	= $datetime['month'];
			$this->event->mod->mday	= $datetime['day'];
			$this->event->mod->hour	= $datetime['hour'];
			$this->event->mod->min	= $datetime['minute'];
			$this->event->mod->sec	= $datetime['second'];
			$this->event->mod->alarm	= 0;

			$this->event->edatetime = $this->stream->f('edatetime');
			$datetime = $this->localdates($this->stream->f('edatetime'));
			$this->event->end->year	= $datetime['year'];
			$this->event->end->month	= $datetime['month'];
			$this->event->end->mday	= $datetime['day'];
			$this->event->end->hour	= $datetime['hour'];
			$this->event->end->min	= $datetime['minute'];
			$this->event->end->sec	= $datetime['second'];
			$this->event->end->alarm	= 0;

		//Legacy Support
			$this->event->priority = intval($this->stream->f('priority'));
			if($this->stream->f('cal_group') || $this->stream->f('groups') != 'NULL')
			{
				$groups = explode(',',$this->stream->f('groups'));
				for($j=1;$j<count($groups) - 1;$j++)
				{
					$this->event->groups[] = $groups[$j];
				}
			}

			$this->stream->query('SELECT * FROM phpgw_cal_repeats WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				$this->stream->next_record();

				$this->event->recur_type = intval($this->stream->f('recur_type'));
				$this->event->recur_interval = intval($this->stream->f('recur_interval'));
				$enddate = $this->stream->f('recur_enddate');
				if($enddate != 0 && $enddate != Null)
				{
					$datetime = $this->localdates($enddate);
					$this->event->recur_enddate->year	= $datetime['year'];
					$this->event->recur_enddate->month	= $datetime['month'];
					$this->event->recur_enddate->mday	= $datetime['day'];
					$this->event->recur_enddate->hour	= $datetime['hour'];
					$this->event->recur_enddate->min	= $datetime['minute'];
					$this->event->recur_enddate->sec	= $datetime['second'];
					$this->event->recur_enddate->alarm	= 0;
				}
				else
				{
					$this->event->recur_enddate->year	= 0;
					$this->event->recur_enddate->month	= 0;
					$this->event->recur_enddate->mday	= 0;
					$this->event->recur_enddate->hour	= 0;
					$this->event->recur_enddate->min	= 0;
					$this->event->recur_enddate->sec	= 0;
					$this->event->recur_enddate->alarm	= 0;
				}
				$this->event->recur_data = $this->stream->f('recur_data');
			}
			
		//Legacy Support
			$this->stream->query('SELECT * FROM phpgw_cal_user WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				while($this->stream->next_record())
				{
					if($this->stream->f('cal_login') == $this->user)
					{
						$this->event->users_status = $this->stream->f('cal_status');
					}
					$this->event->participants[] = $this->stream->f('cal_login');
					$this->event->status[] = $this->stream->f('cal_status');
				}
			}
		}
		else
		{
			$this->event = False;
		}
      
		$this->stream->unlock();

		return $this->event;
	}

	function list_events($mcal_stream,$startYear,$startMonth,$startDay,$endYear='',$endMonth='',$endYear='')
	{
		if(!isset($this->stream))
		{
			return False;
		}

		$datetime = $this->makegmttime(0,0,0,$startMonth,$startDay,$startYear);
		$startDate = ' AND (phpgw_cal.datetime >= '.$datetime.') ';
	  
		if($endYear != '' && $endMonth != '' && $endDay != '')
		{
			$edatetime = $this->makegmttime(23,59,59,intval($endMonth),intval($endDay),intval($endYear));
			$endDate = 'AND (phpgw_cal.edatetime <= '.$edatetime.') ';
		}
		else
		{
			$endDate = '';
		}

		return $this->get_event_ids(False,$startDate.$endDate);
	}

	function append_event($mcal_stream)
	{
		$this->save_event($this->event);
		$this->send_update(MSG_ADDED,$this->event->participants,'',$this->event);
		return $this->event->id;
	}

	function store_event($mcal_stream)
	{
		if($this->event->id != 0)
		{
			$new_event = $this->event;
			$old_event = $this->fetch_event($this->stream,$new_event->id);
			$this->prepare_recipients($new_event,$old_event);
			$this->event = $new_event;
		}
		else
		{
			$part_count = count($this->event->participants);
			for($i=0;$i<$part_count;$i++)
			{
				$this->event->status[$i] = 'U';
			}
			$this->send_update(MSG_ADDED,$this->event->participants,'',$this->event);
		}
		return $this->save_event($this->event);
	}

	function delete_event($mcal_stream,$event_id)
	{
		$this->deleted_events[] = $event_id;
	}

	function snooze($mcal_stream,$event_id)
	{
	//Turn off an alarm for an event
	//Returns true. 
	}

	function list_alarms($mcal_stream,$begin_year='',$begin_month='',$begin_day='',$end_year='',$end_month='',$end_day='')
	{
	//Return a list of events that has an alarm triggered at the given datetime
	//Returns an array of event ID's
	}

	function event_init($stream)
	{
		$this->event = CreateObject('calendar.calendar_item');
		$this->event->owner = $this->user;
//		echo 'Initializing Calendar Event<br>'."\n";
//		echo 'Setting Owner = '.$this->event->owner."<br>\n";
		return True;
	}

	function event_set_category($stream,$category='')
	{
		$this->event->category = $category;
//		echo 'Setting Calendar Category = '.$this->event->category.'<br>'."\n";
		return True;
	}

	function event_set_title($stream,$title='')
	{
		$this->event->title = $title;
//		echo 'Setting Calendar Title = '.$this->event->title.'<br>'."\n";
		return True;
	}

	function event_set_description($stream,$description='')
	{
		$this->event->description = $description;
//		echo 'Setting Calendar Description = '.$this->event->description.'<br>'."\n";
		return True;
	}

	function event_set_start($stream,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		global $phpgw_info;
		
		$this->event->start->year = intval($year);
		$this->event->start->month = intval($month);
		$this->event->start->mday = intval($day);
		$this->event->start->hour = intval($hour);
		$this->event->start->min = intval($min);
		$this->event->start->sec = intval($sec);
		$this->event->start->alarm = 0;

//		echo 'Setting Calendar Start = '.$this->event->start->year.$this->event->start->month.$this->event->start->mday.':'.$this->event->start->hour.$this->event->start->min.$this->event->start->sec.'<br>'."\n";
		return True;
	}

	function event_set_end($stream,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		global $phpgw_info;
		
		$this->event->end->year = intval($year);
		$this->event->end->month = intval($month);
		$this->event->end->mday = intval($day);
		$this->event->end->hour = intval($hour);
		$this->event->end->min = intval($min);
		$this->event->end->sec = intval($sec);
		$this->event->end->alarm = 0;
		
//		echo 'Setting Calendar End = '.$this->event->end->year.$this->event->end->month.$this->event->end->mday.':'.$this->event->end->hour.$this->event->end->min.$this->event->end->sec.'<br>'."\n";
		return True;
	}

	function event_set_alarm($stream,$alarm)
	{
		$this->event->alarm = intval($alarm);
		return True;
	}

	function event_set_class($stream,$class)
	{
		$this->event->public = $class;
		return True;
	}

	function is_leap_year($year)
	{
		if ((intval($year) % 4 == 0) && (intval($year) % 100 != 0) || (intval($year) % 400 == 0))
			return 1;
		else
			return 0;
	}

	function days_in_month($month,$year)
	{
		$days = Array(
			1	=>	31,
			2	=>	28 + $this->is_leap_year(intval($year)),
			3	=>	31,
			4	=>	30,
			5	=>	31,
			6	=>	30,
			7	=>	31,
			8	=>	31,
			9	=>	30,
			10	=>	31,
			11	=>	30,
			12	=>	31
		);
		return $days[intval($month)];
	}

	function date_valid($year,$month,$day)
	{
		return checkdate(intval($month),intval($day),intval($year));
	}

	function time_valid($hour,$minutes,$seconds)
	{
		if(intval($hour) < 0 || intval($hour) > 24)
		{
			return False;
		}
		if(intval($minutes) < 0 || intval($minutes) > 59)
		{
			return False;
		}
		if(intval($seconds) < 0 || intval($seconds) > 59)
		{
			return False;
		}

		return True;
	}

	function day_of_week($year,$month,$day)
	{
		if($month > 2)
		{
			$month -= 2;
		}
		else
		{
			$month += 10;
			$year--;
		}
		$day = (floor((13 * $month - 1) / 5) + $day + ($year % 100) + floor(($year % 100) / 4) + floor(($year / 100) / 4) - 2 * floor($year / 100) + 77);
		return (($day - 7 * floor($day / 7)));
	}
	
	function day_of_year($year,$month,$day)
	{
		$days = array(0,31,59,90,120,151,181,212,243,273,304,334);
        
		$julian = ($days[$month - 1] + $day);

		if($month > 2 && $this->is_leap_year($year))
		{
			$julian++;
		}
		return($julian);
	}

	function date_compare($a_year,$a_month,$a_day,$b_year,$b_month,$b_day)
	{
		$a_date = mktime(0,0,0,intval($a_month),intval($a_day),intval($a_year));
		$b_date = mktime(0,0,0,intval($b_month),intval($b_day),intval($b_year));
		if($a_date == $b_date)
		{
			return 0;
		}
		elseif($a_date > $b_date)
		{
			return 1;
		}
		elseif($a_date < $b_date)
		{
			return -1;
		}
	}

	// The function definition doesn't look correct...
	// Need more information for this function
	function next_recurrence($stream,$weekstart,$next)
	{
//		return next_recurrence (int stream, int weekstart, array next);
	}

	function event_set_recur_none($stream)
	{
		$this->event->recur_type = RECUR_NONE;
		$this->event->recur_interval = 0;
		$this->event->recur_enddate->year = 0;
		$this->event->recur_enddate->month = 0;
		$this->event->recur_enddate->mday = 0;
		$this->event->recur_enddate->hour = 0;
		$this->event->recur_enddate->min = 0;
		$this->event->recur_enddate->sec = 0;
		$this->event->recur_enddate->alarm = 0;
		$this->event->recur_data = 0;
		
		return True;
	}

	function event_set_recur_daily($stream,$year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = RECUR_DAILY;
	}

	function event_set_recur_weekly($stream,$year,$month,$day,$interval,$weekdays)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = RECUR_WEEKLY;
		$this->event->recur_data = intval($weekdays);
	}

	function event_set_recur_monthly_mday($stream,$year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = RECUR_MONTHLY_MDAY;
	}
	
	function event_set_recur_monthly_wday($stream,$year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = RECUR_MONTHLY_WDAY;
	}
	
	function event_set_recur_yearly($stream,$year,$month,$day,$interval)
	{
		$this->set_common_recur(intval($year),intval($month),intval($day),$interval);
		$this->event->recur_type = RECUR_YEARLY;
	}

	function fetch_current_stream_event($stream)
	{
		return $this->fetch_event($stream,$this->event->id);
	}
	
	function event_add_attribute($stream,$attribute,$value)
	{
		$this->event->$attribute = $value;
	}

	function expunge($stream)
	{
		if(count($this->deleted_events) <= 0)
		{
			return 1;
		}
		$this_event = $this->event;
		$locks = Array(
			'phpgw_cal',
			'phpgw_cal_user',
			'phpgw_cal_repeats'
		);
		$this->stream->lock($locks);
		for($i=0;$i<count($this->deleted_events);$i++)
		{
			$event_id = $this->deleted_events[$i];

			$event = $this->fetch_event($stream,$event_id);
			$this->send_update(MSG_DELETED,$event->participants,$event);

			for($k=0;$k<count($locks);$k++)
			{
				$this->stream->query('DELETE FROM '.$locks[$k].' WHERE cal_id='.$event_id,__LINE__,__FILE__);
			}
		}
		$this->stream->unlock();
		$this->event = $this_event;
		return 1;
	}
	
	/***************** Local functions for SQL based Calendar *****************/

	function get_event_ids($search_repeats=False,$extra='')
	{
		$retval = Array();
		if($search_repeats == True)
		{
			$repeats_from = ', phpgw_cal_repeats ';
			$repeats_where = 'AND (phpgw_cal_repeats.cal_id = phpgw_cal.cal_id) ';
		}
		else
		{
			$repeats_from = ' ';
			$repeats_where = '';
		}
		
		$sql = 'SELECT DISTINCT phpgw_cal.cal_id,'
				. 'phpgw_cal.datetime,phpgw_cal.edatetime,'
				. 'phpgw_cal.priority '
				. 'FROM phpgw_cal, phpgw_cal_user'
				. $repeats_from
				. 'WHERE (phpgw_cal_user.cal_id = phpgw_cal.cal_id) '
				. $repeats_where . $extra;

		$this->stream->query($sql,__LINE__,__FILE__);
		
		if($this->stream->num_rows() == 0)
		{
			return False;
		}

		$retval = Array();

		while($this->stream->next_record())
		{
			$retval[] = intval($this->stream->f('cal_id'));
		}

		return $retval;
	}

	function save_event(&$event)
	{
		global $phpgw_info;

		$locks = Array(
			'phpgw_cal',
			'phpgw_cal_user',
			'phpgw_cal_repeats'
		);
		$this->stream->lock($locks);
		if($event->id == 0)
		{
			$temp_name = tempnam($phpgw_info['server']['temp_dir'],'cal');
			$this->stream->query('INSERT INTO phpgw_cal(title,owner,priority,is_public) '
				. "values('".$temp_name."',".$event->owner.",".$event->priority.",".$event->public.")");
			$this->stream->query("SELECT cal_id FROM phpgw_cal WHERE title='".$temp_name."'");
			$this->stream->next_record();
			$event->id = $this->stream->f('cal_id');
		}

		$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));
		$date = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset;
		$enddate = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $tz_offset;
		$today = time() - $tz_offset;

		if($event->recur_type != RECUR_NONE)
		{
			$type = 'M';
		}
		else
		{
			$type = 'E';
		}

		$cat = '';
		if($event->category != 0)
		{
			$cat = 'category='.$event->category.', ';
		}

		$sql = 'UPDATE phpgw_cal SET '
				. 'owner='.$event->owner.', '
				. 'datetime='.$date.', '
				. 'mdatetime='.$today.', '
				. 'edatetime='.$enddate.', '
				. 'priority='.$event->priority.', '
				. $cat
				. "cal_type='".$type."', "
				. 'is_public='.$event->public.', '
				. "title='".addslashes($event->title)."', "
				. "description='".addslashes($event->description)."' "
				. 'WHERE cal_id='.$event->id;
				
		$this->stream->query($sql,__LINE__,__FILE__);
		
		$this->stream->query('DELETE FROM phpgw_cal_user WHERE cal_id='.$event->id,__LINE__,__FILE__);

		reset($event->participants);
		while (list($key,$value) = each($event->participants))
		{
			if(intval($value) == intval($this->user))
			{
				$status = 'A';
			}
			else
			{
				$status = $event->status[$key];
			}
			$this->stream->query('INSERT INTO phpgw_cal_user(cal_id,cal_login,cal_status) '
				. 'VALUES('.$event->id.','.$value.",'".$status."')",__LINE__,__FILE__);
		}

		if($event->recur_type != RECUR_NONE)
		{
			if($event->recur_enddate->month != 0 && $event->recur_enddate->mday != 0 && $event->recur_enddate->year != 0)
			{
				$end = mktime($event->recur_enddate->hour,$event->recur_enddate->min,$event->recur_enddate->sec,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year) - $tz_offset;
			}
			else
			{
				$end = '0';
			}

			$this->stream->query('SELECT count(cal_id) FROM phpgw_cal_repeats WHERE cal_id='.$event->id,__LINE__,__FILE__);
			$this->stream->next_record();
			$num_rows = $this->stream->f(0);
			if($num_rows == 0)
			{
				$this->stream->query('INSERT INTO phpgw_cal_repeats(cal_id,recur_type,recur_enddate,recur_data,recur_interval) '
					.'VALUES('.$event->id.','.$event->recur_type.','.$end.','.$event->recur_data.','.$event->recur_interval.')',__LINE__,__FILE__);
			}
			else
			{
				$this->stream->query('UPDATE phpgw_cal_repeats '
					.'SET recur_type='.$event->recur_type.', '
					.'recur_enddate='.$end.', '
					.'recur_data='.$event->recur_data.', recur_interval='.$event->recur_interval.' '
					.'WHERE cal_id='.$event->id,__LINE__,__FILE__);
			}
		}
		else
		{
			$this->stream->query('DELETE FROM phpgw_cal_repeats WHERE cal_id='.$event->id,__LINE__,__FILE__);
		}
		
		$this->stream->unlock();
		return True;
	}

	function event_set_participants($stream,$participants)
	{
		$this->event->participants = Array();
		reset($participants);
		$this->event->participants = $participants;
		return True;
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
		return True;
	}
	
// End of ICal style support.......

	function group_search($owner=0)
	{
		global $phpgw, $phpgw_info;
      
		$owner = $owner==$phpgw_info['user']['account_id']?0:$owner;
		$groups = substr($phpgw->common->sql_search('phpgw_cal.groups',intval($owner)),4);
		if (!$groups)
		{
			return '';
		}
		else
		{
			return "(phpgw_cal.is_public=2 AND (". $groups .')) ';
		}
	}

	function normalizeminutes(&$minutes)
	{
		$hour = 0;
		$min = intval($minutes);
		if($min >= 60)
		{
			$hour += $min / 60;
			$min %= 60;
		}
		settype($minutes,'integer');
		$minutes = $min;
		return $hour;
	}

	function splittime_($time)
	{
		global $phpgw_info;

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

	function splittime($time,$follow_24_rule=True)
	{
		global $phpgw_info;

		$temp = array('hour','minute','second','ampm');
		$time = strrev($time);
		$second = intval(strrev(substr($time,0,2)));
		$minute = intval(strrev(substr($time,2,2)));
		$hour   = intval(strrev(substr($time,4)));
		$hour += $this->normalizeminutes(&$minute);
		$temp['second'] = $second;
		$temp['minute'] = $minute;
		$temp['hour']   = $hour;
		$temp['ampm']   = '  ';
		if($follow_24_rule == True)
		{
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '24')
			{
				return $temp;
			}
		
			$temp['ampm'] = 'am';
		
			if ((int)$temp['hour'] > 12)
			{
				$temp['hour'] = (int)((int)$temp['hour'] - 12);
				$temp['ampm'] = 'pm';
   	   }
      	elseif ((int)$temp['hour'] == 12)
	      {
				$temp['ampm'] = 'pm';
			}
		}
		return $temp;
	}

	function makegmttime($hour,$minute,$second,$month,$day,$year)
	{
		global $phpgw, $phpgw_info;

		return $this->gmtdate(mktime($hour, $minute, $second, $month, $day, $year));
	}

	function localdates($localtime)
	{
		global $phpgw, $phpgw_info;

		$date = Array('raw','day','month','year','full','dow','dm','bd');
		$date['raw'] = $localtime;
		$date['year'] = intval($phpgw->common->show_date($date['raw'],'Y'));
		$date['month'] = intval($phpgw->common->show_date($date['raw'],'m'));
		$date['day'] = intval($phpgw->common->show_date($date['raw'],'d'));
		$date['full'] = intval($phpgw->common->show_date($date['raw'],'Ymd'));
		$date['bd'] = mktime(0,0,0,$date['month'],$date['day'],$date['year']);
		$date['dm'] = intval($phpgw->common->show_date($date['raw'],'dm'));
		$date['dow'] = $this->day_of_week($date['year'],$date['month'],$date['day']);
		$date['hour'] = intval($phpgw->common->show_date($date['raw'],'H'));
		$date['minute'] = intval($phpgw->common->show_date($date['raw'],'i'));
		$date['second'] = intval($phpgw->common->show_date($date['raw'],'s'));
		
		return $date;
	}

	function gmtdate($localtime)
	{
		global $phpgw_info;
      
		$localtime -= ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));
		
		return $this->localdates($localtime);
	}

	function date_to_epoch($d)
	{
		return $this->localdates(mktime(0,0,0,intval(substr($d,4,2)),intval(substr($d,6,2)),intval(substr($d,0,4))));
	}

	function build_time_for_display($fixed_time)
	{
		global $phpgw_info;
		
		$time = $this->splittime($fixed_time);
		$str = '';
		$str .= $time['hour'].':'.((int)$time['minute']<=9?'0':'').$time['minute'];
		
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			$str .= ' ' . $time['ampm'];
		}
		
		return $str;
	}
}
?>
