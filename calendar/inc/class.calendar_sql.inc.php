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
			$this->user = $phpgw_info['user']['account_id'];
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
		return $calendar;
	}

	function fetch_event($mcal_stream,$event_id,$options='')
	{
		global $phpgw;
		
		if(!isset($this->stream))
		{
			return False;
		}
	  
		$this->stream->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));

		// This is the preferred method once everything is normalized...
		//$this->stream->query('SELECT * FROM calendar_entry WHERE id='.$event_id,__LINE__,__FILE__);
		// But until then, do it this way...
		$this->stream->query('SELECT * FROM calendar_entry WHERE cal_id='.$event_id,__LINE__,__FILE__);
		
		if($this->stream->num_rows() > 0)
		{
			$this->event = CreateObject('calendar.calendar_item');
			$this->event->start = new calendar_time;
			$this->event->end = new calendar_time;
			$this->event->recur_enddate = new calendar_time;

			$this->stream->next_record();
			// Load the calendar event data from the db into $event structure
			// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
			
			// This is the preferred method once everything is normalized...
			//$this->event->owner = $this->user;
			// But until then, do it this way...
		//Legacy Support
			$this->event->owner = $this->stream->f('cal_owner');
			
			// This is the preferred method once everything is normalized...
			//$this->event->id = $event_id;
			// But until then, do it this way...
		//Legacy Support
			$this->event->id = intval($this->stream->f('cal_id'));
			
			// This is the preferred method once everything is normalized...
			//$this->event->public = $this->stream->f('public');
			// But until then, do it this way...
		//Legacy Support
			$this->event->access = $this->stream->f('cal_access');
		//Legacy Support (New)
			$this->event->public = ($this->stream->f('cal_access')=='private'?0:1);

			// This is the preferred method once everything is normalized...
			//$this->event->category = $this->stream->f('category');
			// But until then, do it this way...
		//Legacy Support (New)
			$this->event->category = 'Unfiled';

			// This is the preferred method once everything is normalized...
			//$this->event->title = $phpgw->strip_html($this->stream->f('title'));
			// But until then, do it this way...
		//Legacy Support
			$this->event->name = $phpgw->strip_html($this->stream->f('cal_name'));
		//Legacy Support (New)
			$this->event->title = $phpgw->strip_html($this->stream->f('cal_name'));
			
			// This is the preferred method once everything is normalized...
			//$this->event->title = $phpgw->strip_html($this->stream->f('description'));
			// But until then, do it this way...
		//Legacy Support
		//Legacy Support (New)
			$this->event->description = $phpgw->strip_html($this->stream->f('cal_description'));

			// This is the preferred method once everything is normalized...
			//$this->event->alarm = intval($this->stream->f('alarm'));
			// But until then, do it this way...
		//Legacy Support (New)
			$this->event->alarm = 0;
			
			// This is the preferred method once everything is normalized...
			//$this->event->start = unserialize($this->stream->f('start'));
			// But until then, do it this way...
		//Legacy Support
			$this->event->datetime = $this->stream->f('cal_datetime');
			$date = $this->localdates($this->event->datetime);
			$this->event->day = $date['day'];
			$this->event->month = $date['month'];
			$this->event->year = $date['year'];

			$time = $this->splittime($phpgw->common->show_date($this->event->datetime,'His'));
			$this->event->hour   = (int)$time['hour'];
			$this->event->minute = (int)$time['minute'];
			$this->event->ampm   = $time['ampm'];

		//Legacy Support (New)
			$datetime = $this->localdates($this->stream->f('cal_datetime'));
			$this->event->start->year	= $datetime['year'];
			$this->event->start->month	= $datetime['month'];
			$this->event->start->mday	= $datetime['day'];
			$this->event->start->hour	= $datetime['hour'];
			$this->event->start->min	= $datetime['minute'];
			$this->event->start->sec	= $datetime['second'];
			$this->event->start->alarm	= 0;
			

		//Legacy Support
			$this->event->mdatetime = $this->stream->f('cal_mdatetime');
			$date = $this->localdates($this->event->mdatetime);
			$this->event->mod_day = $date['day'];
			$this->event->mod_month = $date['month'];
			$this->event->mod_year = $date['year'];

			$time = $this->splittime($phpgw->common->show_date($this->event->mdatetime,'His'));
			$this->event->mod_hour = (int)$time['hour'];
			$this->event->mod_minute = (int)$time['minute'];
			$this->event->mod_second = (int)$time['second'];
			$this->event->mod_ampm = $time['ampm'];


			// This is the preferred method once everything is normalized...
			//$this->event->end = unserialize($this->stream->f('end'));
			// But until then, do it this way...
		//Legacy Support
			$this->event->edatetime = $this->stream->f('cal_edatetime');
			$date = $this->localdates($this->event->edatetime);
			$this->event->end_day = $date['day'];
			$this->event->end_month = $date['month'];
			$this->event->end_year = $date['year'];

			$time = $this->splittime($phpgw->common->show_date($this->event->edatetime,'His'));
			$this->event->end_hour = (int)$time['hour'];
			$this->event->end_minute = (int)$time['minute'];
			$this->event->end_second = (int)$time['second'];
			$this->event->end_ampm = $time['ampm'];

		//Legacy Support (New)
			$datetime = $this->localdates($this->stream->f('cal_edatetime'));
			$this->event->end->year	= $datetime['year'];
			$this->event->end->month	= $datetime['month'];
			$this->event->end->mday	= $datetime['day'];
			$this->event->end->hour	= $datetime['hour'];
			$this->event->end->min	= $datetime['minute'];
			$this->event->end->sec	= $datetime['second'];
			$this->event->end->alarm	= 0;

		//Legacy Support
			$this->event->priority = $this->stream->f('cal_priority');
			if($this->stream->f('cal_group'))
			{
				$groups = explode(',',$this->stream->f('cal_group'));
				for($j=1;$j<count($groups) - 1;$j++)
				{
					$this->event->groups[] = $groups[$j];
				}
			}

			// This should all be one table,
			// but for now we'll leave it separate...
			// This is the preferred method once everything is normalized...
			//$this->stream->query('SELECT * FROM calendar_entry_repeats WHERE id='.$event_id,__LINE__,__FILE__);
			// But until then, do it this way...
		//Legacy Support
			$this->stream->query('SELECT * FROM calendar_entry_repeats WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				$this->stream->next_record();

				// This is the preferred method once everything is normalized...
				//$this->event->recur_type = intval($this->stream->f('recur_type'));
				// But until then, do it this way...
		//Legacy Support
				$rpt_type = strtolower($this->stream->f('cal_type'));
				$this->event->rpt_type = !$rpt_type?'none':$rpt_type;
				
		//Legacy Support (New)
				switch($this->event->rpt_type)
				{
					case 'none':
						$this->event->recur_type = RECUR_NONE;
						break;
					case 'daily':
						$this->event->recur_type = RECUR_DAILY;
						break;
					case 'weekly':
						$this->event->recur_type = RECUR_WEEKLY;
						break;
					case 'monthlybydate':
						$this->event->recur_type = RECUR_MONTHLY_MDAY;
						break;
					case 'monthlybyday':
						$this->event->recur_type = RECUR_MONTHLY_WDAY;
						break;
					case 'yearly':
						$this->event->recur_type = RECUR_YEARLY;
						break;
				}
				
				// This is the preferred method once everything is normalized...
				//$this->event->recur_interval = intval($this->stream->f('recur_interval'));
				// But until then, do it this way...
		//Legacy Support
				$this->event->rpt_freq = (int)$this->stream->f('cal_frequency');
		//Legacy Support (New)
				$this->event->recur_interval = (int)$this->stream->f('cal_frequency');

				// This is the preferred method once everything is normalized...
				//$this->event->recur_enddate = unserialize($this->stream->f('recur_enddate'));
				// But until then, do it this way...
		//Legacy Support
				$this->event->recur_use_end = $this->stream->f('cal_use_end');
				if($this->event->recur_use_end == True)
				{
					$date = $this->localdates($this->stream->f('cal_end'));
		//Legacy Support
					$this->event->rpt_end = $this->stream->f('cal_end');
					$this->event->rpt_end_day = (int)$date['day'];
					$this->event->rpt_end_month = (int)$date['month'];
					$this->event->rpt_end_year = (int)$date['year'];
					
		//Legacy Support (New)
					$this->event->recur_enddate->year	= $date['year'];
					$this->event->recur_enddate->month	= $date['month'];
					$this->event->recur_enddate->mday	= $date['day'];
					$this->event->recur_enddate->hour	= $date['hour'];
					$this->event->recur_enddate->min	= $date['minute'];
					$this->event->recur_enddate->sec	= $date['second'];
					$this->event->recur_enddate->alarm	= 0;
				}
				else
				{
		//Legacy Support
					$this->event->rpt_end = 0;
					$this->event->rpt_end_day = 0;
					$this->event->rpt_end_month = 0;
					$this->event->rpt_end_year = 0;

		//Legacy Support (New)
					$this->event->recur_enddate->year	= 0;
					$this->event->recur_enddate->month	= 0;
					$this->event->recur_enddate->mday	= 0;
					$this->event->recur_enddate->hour	= 0;
					$this->event->recur_enddate->min	= 0;
					$this->event->recur_enddate->sec	= 0;
					$this->event->recur_enddate->alarm	= 0;
				}
				
				// This is the preferred method once everything is normalized...
				//$this->event->recur_data = $this->stream->f('recur_data');
				// But until then, do it this way...
		//Legacy Support
				$rpt_days = strtoupper($this->stream->f('cal_rpt_days'));
				$this->event->rpt_days = $rpt_days;
				$this->event->rpt_sun = (substr($rpt_days,0,1)=='Y'?1:0);
				$this->event->rpt_mon = (substr($rpt_days,1,1)=='Y'?1:0);
				$this->event->rpt_tue = (substr($rpt_days,2,1)=='Y'?1:0);
				$this->event->rpt_wed = (substr($rpt_days,3,1)=='Y'?1:0);
				$this->event->rpt_thu = (substr($rpt_days,4,1)=='Y'?1:0);
				$this->event->rpt_fri = (substr($rpt_days,5,1)=='Y'?1:0);
				$this->event->rpt_sat = (substr($rpt_days,6,1)=='Y'?1:0);

		//Legacy Support (New)
				$rpt_days = strtoupper($this->stream->f('cal_rpt_days'));
				$this->event->recur_data = 0;
				$this->event->recur_data += (substr($rpt_days,0,1)=='Y'?M_SUNDAY:0);
				$this->event->recur_data += (substr($rpt_days,1,1)=='Y'?M_MONDAY:0);
				$this->event->recur_data += (substr($rpt_days,2,1)=='Y'?M_TUESDAY:0);
				$this->event->recur_data += (substr($rpt_days,3,1)=='Y'?M_WEDNESDAY:0);
				$this->event->recur_data += (substr($rpt_days,4,1)=='Y'?M_THURSDAY:0);
				$this->event->recur_data += (substr($rpt_days,5,1)=='Y'?M_FRIDAY:0);
				$this->event->recur_data += (substr($rpt_days,6,1)=='Y'?M_SAYURDAY:0);
			}
			
		//Legacy Support
			$this->stream->query('SELECT * FROM calendar_entry_user WHERE cal_id='.$event_id,__LINE__,__FILE__);
			if($this->stream->num_rows())
			{
				while($this->stream->next_record())
				{
					if($this->stream->f('cal_login') == $this->user)
					{
						$this->users_status = $this->stream->f('cal_status');
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
		$startDate = ' AND (calendar_entry.datetime >= '.$datetime.') ';
	  
		if($endYear != '' && $endMonth != '' && $endDay != '')
		{
			$edatetime = $this->makegmttime(23,59,59,intval($endMonth),intval($endDay),intval($endYear));
			$endDate = 'AND (calendar_entry.edatetime <= '.$edatetime.') ';
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
		$this->event->name = $title;
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
		
	// Legacy Support
		$this->event->year = intval($year);
		$this->event->month = intval($month);
		$this->event->day = intval($day);
		$this->event->hour = intval($hour);
		$this->event->minute = intval($min);
		$this->event->datetime = mktime(intval($hour),intval($min),intval($sec),intval($month),intval($day),intval($year));
		$this->event->datetime -= ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));

	// Legacy Support (New)
		$this->event->start->year = intval($year);
		$this->event->start->month = intval($month);
		$this->event->start->mday = intval($day);
		$this->event->start->hour = intval($hour);
		$this->event->start->min = intval($min);
		$this->event->start->sec = intval($sec);

//		echo 'Setting Calendar Start = '.$this->event->start->year.$this->event->start->month.$this->event->start->mday.':'.$this->event->start->hour.$this->event->start->min.$this->event->start->sec.'<br>'."\n";
		return True;
	}

	function event_set_end($stream,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		global $phpgw_info;
		
	// Legacy Support
		$this->event->end_year = intval($year);
		$this->event->end_month = intval($month);
		$this->event->end_day = intval($day);
		$this->event->end_hour = intval($hour);
		$this->event->end_minute = intval($min);
		$this->event->end_second = intval($sec);
		$this->event->edatetime = mktime(intval($hour),intval($min),intval($sec),intval($month),intval($day),intval($year));
		$this->event->edatetime -= ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));

	// Legacy Support (New)
		$this->event->end->year = intval($year);
		$this->event->end->month = intval($month);
		$this->event->end->mday = intval($day);
		$this->event->end->hour = intval($hour);
		$this->event->end->min = intval($min);
		$this->event->end->sec = intval($sec);
		
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
		return date('w',mktime(0,0,0,intval($month),intval($day),intval($year)));
	}
	
	function day_of_year($year,$month,$day)
	{
		return date('w',mktime(0,0,0,intval($month),intval($day),intval($year)));
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
	// Legacy Support
		$this->event->rpt_type = 'none';
		$this->event->rpt_end_use = 0;
		$this->event->rpt_end = 0;
		$this->event->rpt_end_day = 0;
		$this->event->rpt_end_month = 0;
		$this->event->rpt_end_year = 0;
		$this->event->rpt_days = 'nnnnnnn';
		$this->event->rpt_sun = 0;
		$this->event->rpt_mon = 0;
		$this->event->rpt_tue = 0;
		$this->event->rpt_wed = 0;
		$this->event->rpt_thu = 0;
		$this->event->rpt_fri = 0;
		$this->event->rpt_sat = 0;
		$this->event->rpt_freq = 0;

	// Legacy Support (New)
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

	function event_recur_daily($stream,$year,$monh,$day,$interval)
	{
	// Legacy Support
		$this->event->rpt_type = 'daily';

	// Legacy Support (New)
		$this->event->recur_type = RECUR_DAILY;

		$this->set_common_recur(intval($year),intval($month),intval($day));
	}

	function event_set_recur_weekly($stream,$year,$month,$day,$interval,$weekdays)
	{
	// Legacy Support
		$this->event->rpt_type = 'weekly';

		$this->set_common_recur(intval($year),intval($month),intval($day));

		$this->event->rpt_sun = (intval($weekdays) & M_SUNDAY	?1:0);
		$this->event->rpt_mon = (intval($weekdays) & M_MONDAY	?1:0);
		$this->event->rpt_tue = (intval($weekdays) & M_TUESDAY	?1:0);
		$this->event->rpt_wed = (intval($weekdays) & M_WEDNESDAY?1:0);
		$this->event->rpt_thu = (intval($weekdays) & M_THURSDAY	?1:0);
		$this->event->rpt_fri = (intval($weekdays) & M_FRIDAY	?1:0);
		$this->event->rpt_sat = (intval($weekdays) & M_SATURDAY	?1:0);

		$this->event->rpt_days =
			  ($this->event->rpt_sun == 1?'y':'n')
			. ($this->event->rpt_mon == 1?'y':'n')
			. ($this->event->rpt_tue == 1?'y':'n')
			. ($this->event->rpt_wed == 1?'y':'n')
			. ($this->event->rpt_thu == 1?'y':'n')
			. ($this->event->rpt_fri == 1?'y':'n')
			. ($this->event->rpt_sat == 1?'y':'n');

	// Legacy Support (New)
		$this->event->recur_type = RECUR_WEEKLY;
		$this->event->recur_data = intval($weekdays);
	}

	function event_set_recur_monthly_mday($stream,$year,$month,$day,$interval)
	{
	// Legacy Support
		$this->event->rpt_type = 'monthlybydate';

	// Legacy Support
		$this->event->recur_type = RECUR_MONTHLY_MDAY;

		$this->set_common_recur(intval($year),intval($month),intval($day));
	}
	
	function event_set_recur_monthly_wday($stream,$year,$month,$day,$interval)
	{
	// Legacy Support
		$this->event->rpt_type = 'monthlybyday';

	// Legacy Support (New)
		$this->event->recur_type = RECUR_MONTHLY_WDAY;
		
		$this->set_common_recur(intval($year),intval($month),intval($day));
	}
	
	function event_set_recur_yearly($stream,$year,$month,$day,$interval)
	{
	// Legacy Support
		$this->event->rpt_type = 'yearly';

	// Legacy Support (New)
		$this->event->recur_type = RECUR_YEARLY;
		
		$this->set_common_recur(intval($year),intval($month),intval($day));
	}

	function fetch_current_stream_event($stream)
	{
		return $this->fetch_event($this->event->id);
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
		$this->stream->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));
		for($i=0;$i<count($this->deleted_events);$i++)
		{
			$event_id = $this->deleted_events[$i];

			$event = $this->fetch_event($event_id);
			$this->send_update(MSG_DELETED,$event->participants,$event);

			$this->stream->query('DELETE FROM calendar_entry_user WHERE cal_id='.$event_id,__LINE__,__FILE__);
			$this->stream->query('DELETE FROM calendar_entry_repeats WHERE cal_id='.$event_id,__LINE__,__FILE__);
			$this->stream->query('DELETE FROM calendar_entry WHERE cal_id='.$event_id,__LINE__,__FILE__);
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
			$repeats_from = ', calendar_entry_repeats ';
			$repeats_where = 'AND (calendar_entry_repeats.cal_id = calendar_entry.cal_id) ';
		}
		else
		{
			$repeats_from = ' ';
			$repeats_where = '';
		}
		
		$sql = 'SELECT DISTINCT calendar_entry.cal_id,'
				. 'calendar_entry.cal_datetime,calendar_entry.cal_edatetime,'
				. 'calendar_entry.cal_priority '
				. 'FROM calendar_entry, calendar_entry_user'
				. $repeats_from
				. 'WHERE (calendar_entry_user.cal_id = calendar_entry.cal_id) '
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
		
		$this->stream->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));
		if($event->id == 0)
		{
			$temp_name = tempnam($phpgw_info['server']['temp_dir'],'cal');
			$this->stream->query("INSERT INTO calendar_entry(cal_name) values('".$temp_name."')");
			$this->stream->query("SELECT cal_id FROM calendar_entry WHERE cal_name='".$temp_name."'");
			$this->stream->next_record();
			$event->id = $this->stream->f('cal_id');
		}

//		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
//		{
//			if ($event->ampm == 'pm' && ($event->hour < 12 && $event->hour <> 12))
//			{
//				$event->hour += 12;
//			}
//			
//			if ($event->end_ampm == 'pm' && ($event->end_hour < 12 && $event->end_hour <> 12))
//			{
//				$event->end_hour += 12;
//			}
//		}
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

		if($event->public == True)
		{
			$event->access = 'public';
		}
		else
		{
			$event->access = 'private';
		}

		$sql = 'UPDATE calendar_entry SET '
				. 'cal_owner='.$event->owner.', '
				. 'cal_datetime='.$date.', '
				. 'cal_mdatetime='.$today.', '
				. 'cal_edatetime='.$enddate.', '
				. 'cal_priority='.$event->priority.', '
				. "cal_type='".$type."', "
				. "cal_access='".$event->access."', "
				. "cal_name='".$event->name."', "
				. "cal_description='".$event->description."' "
				. 'WHERE cal_id='.$event->id;
				
		$this->stream->query($sql,__LINE__,__FILE__);
		
		$this->stream->query('DELETE FROM calendar_entry_user WHERE cal_id='.$event->id,__LINE__,__FILE__);

		reset($event->participants);
		while ($participant = each($event->participants))
		{
			if(intval($participant[1]) == intval($this->user))
			{
				$status = 'A';
			}
			else
			{
				$status = $event->status[$participant[0]];
			}
			$this->stream->query('INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) '
				. 'VALUES('.$event->id.','.$participant[1].",'".$status."')",__LINE__,__FILE__);
		}

		if($event->recur_type != RECUR_NONE)
		{
			if($event->rpt_use_end)
			{
				$end = mktime($event->recur_enddate->hour,$event->recur_enddate->min,$event->recur_enddate->sec,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year) - $tz_offset;
				$use_end = 1;
			}
			else
			{
				$end = 'NULL';
				$use_end = 0;
			}

			$days = $event->rpt_days;
			
			$this->stream->query('SELECT count(cal_id) FROM calendar_entry_repeats WHERE cal_id='.$event->id,__LINE__,__FILE__);
			$this->stream->next_record();
			$num_rows = $this->stream->f(0);
			if($num_rows == 0)
			{
				$this->stream->query('INSERT INTO calendar_entry_repeats(cal_id,'
					.'cal_type,cal_use_end,cal_end,cal_days,cal_frequency) '
					.'VALUES('.$event->id.",'".$event->rpt_type."',".$use_end.','
					.$end.",'".$days."',".$event->recur_interval.')',__LINE__,__FILE__);
			}
			else
			{
				$this->stream->query('UPDATE calendar_entry_repeats '
					."SET cal_type='".$event->rpt_type."', "
					.'cal_use_end='.$use_end.", cal_end='".$end."', "
					."cal_days='".$days."', cal_frequency=".$event->recur_interval.' '
					.'WHERE cal_id='.$event->id,__LINE__,__FILE__);
			}
		}
		else
		{
			$this->stream->query('DELETE FROM calendar_entry_repeats WHERE cal_id='.$event->id,__LINE__,__FILE__);
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
		$this->stream->query("UPDATE calendar_entry_user SET cal_status='".$status_code_short[$status]."' WHERE cal_id=".$id." AND cal_login=".$owner,__LINE__,__FILE__);
		return True;
	}
	
// End of ICal style support.......

	function group_search($owner=0)
	{
		global $phpgw, $phpgw_info;
      
		$owner = $owner==$phpgw_info['user']['account_id']?0:$owner;
		$groups = substr($phpgw->common->sql_search('calendar_entry.cal_group',intval($owner)),4);
		if (!$groups)
		{
			return '';
		}
		else
		{
			return "(calendar_entry.cal_access='group' AND (". $groups .')) ';
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
		$date['dow'] = intval($phpgw->common->show_date($date['raw'],'w'));
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

// Start from here to meet coding stds.......
// This function may be removed in the near future.....
	function pretty_small_calendar($day,$month,$year,$link='')
	{
		global $phpgw, $phpgw_info, $view;

//		$tz_offset = (-1 * ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset'])));
		$date = $this->makegmttime(0,0,0,$month,$day,$year);
		$month_ago = intval(date('Ymd',mktime(0,0,0,$month - 1,$day,$year)));
		$month_ahead = intval(date('Ymd',mktime(0,0,0,$month + 1,$day,$year)));
		$monthstart = intval(date('Ymd',mktime(0,0,0,$month,1,$year)));
		$monthend = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

		$weekstarttime = $this->get_weekday_start($year,$month,1);

		$str  = '';
		$str .= '<table border="0" cellspacing="0" cellpadding="0" valign="top">';
		$str .= '<tr valign="top">';
		$str .= '<td bgcolor="'.$phpgw_info['theme']['bg_text'].'">';
		$str .= '<table border="0" width="100%" cellspacing="1" cellpadding="2" border="0" valign="top">';
		
		if ($view == 'day')
		{
			$str .= '<tr><th colspan="7" bgcolor="'.$phpgw_info['theme']['th_bg'].'"><font size="+4" color="'.$phpgw_info['theme']['th_text'].'">'.$day.'</font></th></tr>';
		}
		
		$str .= '<tr>';

		if ($view == 'year')
		{
			$str .= '<td align="center" colspan="7" bgcolor="' . $phpgw_info['theme']['th_bg'] . '">';
		}
		else
		{
			$str .= '<td align="left" bgcolor="' . $phpgw_info['theme']['th_bg'] .'">';
		}

		if ($view != 'year')
		{
			if (!$this->printer_friendly)
			{
				$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/day.php','date='.$month_ago).'" class="monthlink">';
			}
			
			$str .= '&lt;';
			if (!$this->printer_friendly)
			{
				$str .= '</a>';
			}
			$str .= '</td>';
			$str .= '<th colspan="5" bgcolor="'.$phpgw_info['theme']['th_bg'].'"><font color="'.$phpgw_info['theme']['th_text'].'">';
		}
		
		if (!$this->printer_friendly)
		{
			$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/index.php','year='.$year.'&month='.$month).'">';
		}
		
		$str .= lang($phpgw->common->show_date($date['raw'],'F')).' '.$year;
		
		if(!$this->printer_friendly)
		{
			$str .= '</a>';
		}
		
		if ($view != 'year')
		{
			$str .= '</font></th>';
		}

		if ($view != 'year')
		{
			$str .= '<td align="right" bgcolor="'.$phpgw_info['theme']['th_bg'].'">';
			
			if (!$this->printer_friendly)
			{
				$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/day.php','date='.$month_ahead).'" class="monthlink">';
			}
			
			$str .= '&gt;';
			
			if (!$this->printer_friendly)
			{
				$str .= '</a>';
			}
			
			$str .= '</td>';
		}
		$str .= '</tr>';
		$str .= '<tr>';
		
		for($i=0;$i<7;$i++)
		{
			$str .= '<td bgcolor="'.$phpgw_info['theme']['cal_dayview'].'"><font size="-2">'.substr(lang($days[$i]),0,2).'</td>';
		}
		
		$str .= '</tr>';
		
		for($i=$weekstarttime;date('Ymd',$i)<=$monthend;$i += (24 * 3600 * 7))
		{
			$str .= '<tr>';
			for($j=0;$j<7;$j++)
			{
				$cal = $this->gmtdate($i + ($j * 24 * 3600));
				
				if($cal['full'] >= $monthstart && $cal['full'] <= $monthend)
				{
					$str .= '<td align="center" bgcolor="';
					
					if($cal['full'] == $this->today['full'])
					{
						$str .= $phpgw_info['theme']['cal_today'];
					}
					else
					{
						$str .= $phpgw_info['theme']['cal_dayview'];
					}
					
					$str .= '"><font size="-2">';

					if(!$this->printer_friendly)
					{
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/'.$link,'year='.$cal['year'].'&month='.$cal['month'].'&day='.$cal['day']).'" class="monthlink">';
					}
					
					$str .= $cal['day'];
					
					if(!$this->printer_friendly)
					{
						$str .= '</a>';
					}
					
					$str .= '</font></td>';
				}
				else
				{
					$str .= '<td bgcolor="' . $phpgw_info['theme']['cal_dayview'] 
							. '"><font size="-2" color="' . $phpgw_info['theme']['cal_dayview'] . '">.</font></td>';
				}
			}
			$str .= '</tr>';
		}
		$str .= '</table>';
		$str .= '</td>';
		$str .= '</tr>';
		$str .= '</table>';
		return $str;
	}

// This function may be removed in the near future.....
	function display_small_month($month,$year,$showyear,$link='')
	{
		global $phpgw, $phpgw_info;

		$weekstarttime = $this->get_weekday_start($year,$month,1);

		$str  = '';
		$str .= '<table border="0" bgcolor="'.$phpgw_info['theme']['bg_color'].'">';

		$monthstart = $this->localdates(mktime(0,0,0,$month    ,1,$year));
		$monthend   = $this->localdates(mktime(0,0,0,$month + 1,0,$year));

		$str .= '<tr><td colspan="7" align="center"><font size="2">';

		if(!$this->printer_friendly)
		{
			$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/index.php',"year=$year&month=$month").'">';
		}
		
		$str .= lang(date('F',$monthstart['raw']));

		if($showyear)
		{
			$str .= ' '.$year;
		}
    
		if(!$this->printer_friendly)
		{
			$str .= '</a>';
		}

		$str .= '</font></td></tr><tr>';
		
		for($i=0;$i<7;$i++)
		{
			$str .= '<td>'.lang($days[$i]).'</td>';
		}
		
		$str .= '</tr>';

		for($i=$weekstarttime;date('Ymd',$i)<=$monthend['full'];$i+=604800)
		{
			$str .= '<tr>';
			for($j=0;$j<7;$j++)
			{
				$date = $this->localdates($i + ($j * 86400));
				
				if($date['full']>=$monthstart['full'] &&
					$date['full']<=$monthend['full'])
				{
					$str .= '<td align="right">';
					
					if(!$this->printer_friendly || $link)
					{
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/'.$link,'year='.$date['year'].'&month='.$date['month'].'&day='.$date['day']).'">';
					}
					
					$str .= '<font size="2">'.date('j',$date['raw']);
					if(!$this->printer_friendly || $link)
					{
						$str .= '</a>';
					}
					
					$str .= '</font></td>';
				}
				else
				{
					$str .= '<td></td>';
				}
			}
			$str .= '</tr>';
		}
		$str .= '</table>';
		return $str;
	}

	function prep($calid)
	{
		global $phpgw, $phpgw_info;

		if(!$phpgw_info['user']['apps']['calendar']) return false;

		$db2 = $phpgw->db;
      
		$cal_id = array();
		if(is_long($calid))
		{
			if(!$calid)
			{
				return false;
			}
			
			$cal_id[0] = $calid;
		}
		elseif(is_string($calid))
		{
			$calid = $phpgw->account->name2id($calid);
			$db2->query('SELECT cal_id FROM calendar_entry WHERE cal_owner='.$calid,__LINE__,__FILE__);
			while($phpgw->db->next_record())
			{
				$cal_id[] = $db2->f('cal_id');
			}
		}
		elseif(is_array($calid))
		{
		
			if(is_string($calid[0]))
			{
			
				for($i=0;$i<count($calid);$i++)
				{
					$db2->query('SELECT cal_id FROM calendar_entry WHERE cal_owner='.$calid[$i],__LINE__,__FILE__);
					while($db2->next_record())
					{
						$cal_id[] = $db2->f('cal_id');
					}
          }
        }
        elseif(is_long($calid[0]))
        {
				$cal_id = $calid;
			}
		}
		return $cal_id;
	}

	function getwithindates($from,$to)
	{
		global $phpgw, $phpgw_info;

		if(!$phpgw_info['user']['apps']['calendar'])
		{
			return false;
		}

		$phpgw->db->query('SELECT cal_id FROM calendar_entry WHERE cal_date >= '.$from.' AND cal_date <= '.$to,__LINE__,__FILE__);

		if($phpgw->db->num_rows())
		{
			while($phpgw->db->next_record())
			{
				$calid[count($calid)] = intval($phpgw->db->f('cal_id'));
			}
			return $this->getevent($calid);
		}
		else
		{
			return false;
		}
	}

	function add($calinfo,$calid=0)
	{
		global $phpgw, $phpgw_info;

		$db2 = $phpgw->db;

		if(!$phpgw_info['user']['apps']['calendar'])
		{
			return false;
		}
		
		if(!$calid)
		{
			$db2->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));
			$db2->query("INSERT INTO calendar_entry(cal_name) VALUES('".addslashes($calinfo->name)."')",__LINE__,__FILE__);
			$db2->query('SELECT MAX(cal_id) FROM calendar_entry',__LINE__,__FILE__);
			$db2->next_record();
			$calid = $db2->f(0);
			$db2->unlock();
		}
		if($calid)
		{
			return $this->modify($calinfo,$calid);
		}
	}

	function delete($calid=0)
	{
		global $phpgw;

		$cal_id = $this->prep($calid);

		if(!$cal_id)
		{
			return false;
		}

		$db2 = $phpgw->db;

		$db2->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));

      for($i=0;$i<count($cal_id);$i++)
      {
			$db2->query('DELETE FROM calendar_entry_user WHERE cal_id='.$cal_id[$i],__LINE__,__FILE__);
			$db2->query('DELETE FROM calendar_entry_repeats WHERE cal_id='.$cal_id[$i],__LINE__,__FILE__);
			$db2->query('DELETE FROM calendar_entry WHERE cal_id='.$cal_id[$i],__LINE__,__FILE__);
		}
		$db2->unlock();
	}

	function modify($calinfo,$calid=0)
	{
		global $phpgw, $phpgw_info;

		if(!$phpgw_info['user']['apps']['calendar'])
		{
			return false;
		}

		if(!$calid)
		{
			return false;
		}

		$db2 = $phpgw->db;

		$db2->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));

		$owner = ($calinfo->owner?$calinfo->owner:$phpgw_info['user']['account_id']);
		
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			if ($calinfo->ampm == 'pm' && ($calinfo->hour < 12 && $calinfo->hour <> 12))
			{
				$calinfo->hour += 12;
			}
			
			if ($calinfo->end_ampm == 'pm' && ($calinfo->end_hour < 12 && $calinfo->end_hour <> 12))
			{
				$calinfo->end_hour += 12;
			}
		}
		$date = $this->makegmttime($calinfo->hour,$calinfo->minute,0,$calinfo->month,$calinfo->day,$calinfo->year);
		$enddate = $this->makegmttime($calinfo->end_hour,$calinfo->end_minute,0,$calinfo->end_month,$calinfo->end_day,$calinfo->end_year);
		$today = $this->gmtdate(time());

		if($calinfo->rpt_type != 'none')
		{
			$rpt_type = 'M';
		}
		else
		{
			$rpt_type = 'E';
		}

		$query = 'UPDATE calendar_entry SET cal_owner='.$owner.", cal_name='".addslashes($calinfo->name)."', "
			. "cal_description='".addslashes($calinfo->description)."', cal_datetime=".$date['raw'].', '
			. 'cal_mdatetime='.$today['raw'].', cal_edatetime='.$enddate['raw'].', '
			. 'cal_priority='.$calinfo->priority.", cal_type='".$rpt_type."' ";

		if(($calinfo->access == 'public' || $calinfo->access == 'group') && count($calinfo->groups))
		{
			$query .= ", cal_access='".$calinfo->access."', cal_group = '".$phpgw->accounts->array_to_string($calinfo->access,$calinfo->groups)."' ";
		}
		elseif(($calinfo->access == 'group') && !count($calinfo->groups))
		{
			$query .= ", cal_access='private', cal_group = '' ";
		}
		else
		{
			$query .= ", cal_access='".$calinfo->access."', cal_group = '' ";
		}

		$query .= 'WHERE cal_id='.$calid;

		$db2->query($query,__LINE__,__FILE__);

		$db2->query('DELETE FROM calendar_entry_user WHERE cal_id='.$calid,__LINE__,__FILE__);

		while ($participant = each($calinfo->participants))
		{
			$phpgw->db->query('INSERT INTO calendar_entry_user(cal_id,cal_login,cal_status) '
				. 'VALUES('.$calid.','.$participant[1].",'A')",__LINE__,__FILE__);
		}

		if(strcmp($calinfo->rpt_type,'none') <> 0)
		{
			$freq = ($calinfo->rpt_freq?$calinfo->rpt_freq:0);

			if($calinfo->rpt_use_end)
			{
				$end = $this->makegmttime(0,0,0,$calinfo->rpt_month,$calinfo->rpt_day,$calinfo->rpt_year);
				$use_end = 1;
			}
			else
			{
				$end = 'NULL';
				$use_end = 0;
			}

			if($calinfo->rpt_type == 'weekly' || $calinfo->rpt_type == 'daily')
			{
				$days = ($calinfo->rpt_sun?'y':'n')
						. ($calinfo->rpt_mon?'y':'n')
						. ($calinfo->rpt_tue?'y':'n')
						. ($calinfo->rpt_wed?'y':'n')
						. ($calinfo->rpt_thu?'y':'n')
						. ($calinfo->rpt_fri?'y':'n')
						. ($calinfo->rpt_sat?'y':'n');
			}
			else
			{
				$days = 'nnnnnnn';
			}
			
			$db2->query('SELECT count(cal_id) FROM calendar_entry_repeats WHERE cal_id='.$calid,__LINE__,__FILE__);
			$db2->next_record();
			$num_rows = $db2->f(0);
			if(!$num_rows)
			{
				$db2->query('INSERT INTO calendar_entry_repeats(cal_id,cal_type,cal_use_end,cal_end,cal_days,cal_frequency) '
					."VALUES($calid,'".$calinfo->rpt_type."',$use_end,".$end['raw'].",'$days',$freq)",__LINE__,__FILE__);
			}
			else
			{
				$db2->query("UPDATE calendar_entry_repeats SET cal_type='".$calinfo->rpt_type."', cal_use_end=".$use_end.', '
					."cal_end='".$end['raw']."', cal_days='".$days."', cal_frequency=".$freq.' '
					.'WHERE cal_id='.$calid,__LINE__,__FILE__);
			}
		}
		else
		{
			$db2->query('DELETE FROM calendar_entry_repeats WHERE cal_id='.$calid,__LINE__,__FILE__);
		}
		
		$db2->unlock();      
	}

	function getevent($calid)
	{
		global $phpgw;

		$cal_id = $this->prep($calid);

		if(!$cal_id)
		{
			return false;
		}

		$db2 = $phpgw->db;

		$db2->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));

		$calendar = CreateObject('calendar.calendar_item');

		for($i=0;$i<count($cal_id);$i++)
		{
			$db2->query('SELECT * FROM calendar_entry WHERE cal_id='.$cal_id[$i],__LINE__,__FILE__);
			$db2->next_record();

			$calendar->id = (int)$db2->f('cal_id');
			$calendar->owner = $db2->f('cal_owner');

			$calendar->datetime = $db2->f('cal_datetime');
//			$date = $this->date_to_epoch($phpgw->common->show_date($calendar->datetime,'Ymd'));
			$date = $this->localdates($calendar->datetime);
			$calendar->day = $date['day'];
			$calendar->month = $date['month'];
			$calendar->year = $date['year'];

			$time = $this->splittime($phpgw->common->show_date($calendar->datetime,'His'));
			$calendar->hour   = (int)$time['hour'];
			$calendar->minute = (int)$time['minute'];
			$calendar->ampm   = $time['ampm'];

			$calendar->mdatetime = $db2->f('cal_mdatetime');
//			$date = $this->date_to_epoch($phpgw->common->show_date($calendar->mdatetime,'Ymd'));
			$date = $this->localdates($calendar->mdatetime);
			$calendar->mod_day = $date['day'];
			$calendar->mod_month = $date['month'];
			$calendar->mod_year = $date['year'];

			$time = $this->splittime($phpgw->common->show_date($calendar->mdatetime,'His'));
			$calendar->mod_hour = (int)$time['hour'];
			$calendar->mod_minute = (int)$time['minute'];
			$calendar->mod_second = (int)$time['second'];
			$calendar->mod_ampm = $time['ampm'];

			$calendar->edatetime = $db2->f('cal_edatetime');
//			$date = $this->date_to_epoch($phpgw->common->show_date($calendar->edatetime,'Ymd'));
			$date = $this->localdates($calendar->edatetime);
			$calendar->end_day = $date['day'];
			$calendar->end_month = $date['month'];
			$calendar->end_year = $date['year'];

			$time = $this->splittime($phpgw->common->show_date($calendar->edatetime,'His'));
			$calendar->end_hour = (int)$time['hour'];
			$calendar->end_minute = (int)$time['minute'];
			$calendar->end_second = (int)$time['second'];
			$calendar->end_ampm = $time['ampm'];

			$calendar->priority = $db2->f('cal_priority');
// not loading webcal_entry.cal_type
			$calendar->access = $db2->f('cal_access');
			$calendar->name = htmlspecialchars(stripslashes($db2->f('cal_name')));
			$calendar->description = htmlspecialchars(stripslashes($db2->f('cal_description')));
			if($db2->f('cal_group'))
			{
				$groups = explode(',',$db2->f('cal_group'));
				for($j=1;$j<count($groups) - 1;$j++)
				{
					$calendar->groups[] = $groups[$j];
				}
			}

			$db2->query('SELECT * FROM calendar_entry_repeats WHERE cal_id='.$cal_id[$i],__LINE__,__FILE__);
			if($db2->num_rows())
			{
				$db2->next_record();

				$rpt_type = strtolower($db2->f('cal_type'));
				$calendar->rpt_type = !$rpt_type?'none':$rpt_type;
				$calendar->rpt_use_end = $db2->f('cal_use_end');
				if($calendar->rpt_use_end)
				{
					$calendar->rpt_end = $db2->f('cal_end');
					$date = $this->localdates($db2->f('cal_end'));
					$calendar->rpt_end_day = (int)$date['day'];
					$calendar->rpt_end_month = (int)$date['month'];
					$calendar->rpt_end_year = (int)$date['year'];
				}
				else
				{
					$calendar->rpt_end = 0;
					$calendar->rpt_end_day = 0;
					$calendar->rpt_end_month = 0;
					$calendar->rpt_end_year = 0;
				}
				
				$calendar->rpt_freq = (int)$db2->f('cal_frequency');
				$rpt_days = strtoupper($db2->f('cal_days'));
				$calendar->rpt_days = $rpt_days;
				$calendar->rpt_sun = (substr($rpt_days,0,1)=='Y'?1:0);
				$calendar->rpt_mon = (substr($rpt_days,1,1)=='Y'?1:0);
				$calendar->rpt_tue = (substr($rpt_days,2,1)=='Y'?1:0);
				$calendar->rpt_wed = (substr($rpt_days,3,1)=='Y'?1:0);
				$calendar->rpt_thu = (substr($rpt_days,4,1)=='Y'?1:0);
				$calendar->rpt_fri = (substr($rpt_days,5,1)=='Y'?1:0);
				$calendar->rpt_sat = (substr($rpt_days,6,1)=='Y'?1:0);
			}

			$db2->query('SELECT * FROM calendar_entry_user WHERE cal_id='.$cal_id[$i],__LINE__,__FILE__);
			
			if($db2->num_rows())
			{
				while($db2->next_record())
				{
					$calendar->participants[] = $db2->f('cal_login');
					$calendar->status[] = $db2->f('cal_status');
				}
			}
			
			$calendar_item[$i] = $calendar;
		}
		$db2->unlock();
		
		return $calendar_item;
	}

	function findevent()
	{
		global $phpgw_info;

		if(!$phpgw_info['user']['apps']['calendar'])
		{
			return false;
		}
	}
}
?>
