<?php
  /**************************************************************************\
  * phpGroupWare - ICal Calendar                                             *
  * http://www.phpgroupware.org                                              *
  * Created by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
class calendar_
{
	var $stream;
	var $user;
	var $event;

	function open($calendar='',$user='',$passwd='',$options='')
	{
		global $phpgw, $phpgw_info;

		if($user=='')
		{
			$this->user = $phpgw_info['user']['account_lid'];
		}
		elseif(is_int($user)) 
		{
			$this->user = $phpgw->accounts->id2name($user);
		}
		elseif(is_string($user))
		{
			$this->user = $user;
		}
		if($options != '')
		{
			$this->stream = mcal_open('{'.$phpgw_info['server']['icap_server'].'/'.$phpgw_info['server']['icap_type'].'}'.$calendar,$this->user,$passwd,$options);
		}
		else
		{
			$this->stream = mcal_open('{'.$phpgw_info['server']['icap_server'].'/'.$phpgw_info['server']['icap_type'].'}'.$calendar,$this->user,$passwd);
		}
		
		return $this->stream;
	}

	function popen($calendar='',$user='',$passwd='',$options='')
	{
		global $phpgw, $phpgw_info;

		if($user=='')
		{
			$this->user = $phpgw_info['user']['account_lid'];
		}
		elseif(is_int($user)) 
		{
			$this->user = $phpgw->accounts->id2name($user);
		}
		elseif(is_string($user))
		{
			$this->user = $user;
		}
		if($options != '')
		{
			$this->stream = mcal_popen('{'.$phpgw_info['server']['icap_server'].'/'.$phpgw_info['server']['icap_type'].'}'.$calendar,$this->user,$passwd,$options);
		}
		else
		{
			$this->stream = mcal_popen('{'.$phpgw_info['server']['icap_server'].'/'.$phpgw_info['server']['icap_type'].'}'.$calendar,$this->user,$passwd);
		}
		
		return $this->stream;
	}

	function reopen($calendar,$options='')
	{
		if($options != '')
		{
			$this->stream = mcal_reopen($calendar,$options);
		}
		else
		{
			$this->stream = mcal_reopen($calendar);
		}
		
		return $this->stream;
	}

	function close($mcal_stream,$options='')
	{
		if($options != '')
		{
			return mcal_close($mcal_stream,$options);
		}
		else
		{
			return mcal_close($mcal_stream);
		}
	}

	function create_calendar($stream,$calendar)
	{
		return mcal_create_calendar($stream,$calendar);
	}

	function rename_calendar($stream,$old_name,$new_name)
	{
		return mcal_rename_calendar($stream,$old_name,$new_name);
	}

	function delete_calendar($stream,$calendar)
	{
		return mcal_delete_calendar($stream,$calendar);
	}

	function fetch_event($mcal_stream,$event_id,$options='')
	{
		if(!isset($this->stream))
		{
			return False;
		}
	
		$this->event = CreateObject('calendar.calendar_item');
	  
		if($options != '')
		{
			$this->event = mcal_fetch_event($mcal_stream,$event_id,$options);
		}
		else
		{
			$this->event = mcal_fetch_event($mcal_stream,$event_id);
		}

		// Need to load the $this->event variable with the $event structure from
		// the mcal_fetch_event() call
		// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
		// This only needs legacy support
		
		return $this->event;
	}

	function list_events($mcal_stream,$startYear,$startMonth,$startDay,$endYear='',$endMonth='',$endYear='')
	{
		if($endYear != '' && $endMonth != '' && $endDay != '')
		{
			$events = mcal_list_events($mcal_stream,$startYear,$startMonth,$startDay,$endYear,$endMonth,$endYear);
		}
		else
		{
			$events = mcal_list_events($mcal_stream,$startYear,$startMonth,$startDay);
		}

		return $events;		
	}

	function append_event($mcal_stream)
	{
		return mcal_append_event($mcal_stream);
	}

	function store_event($mcal_stream)
	{
		return mcal_store_event($mcal_stream);
	}

	function delete_event($mcal_stream,$event_id)
	{
		return mcal_delete_event($mcal_stream,$event_id);
	}

	function snooze($mcal_stream,$event_id)
	{
		return mcal_snooze($mcal_stream,$event_id);
	}

	function list_alarms($mcal_stream,$begin_year='',$begin_month='',$begin_day='',$end_year='',$end_month='',$end_day='')
	{
		if($end_day == '')
		{
			if($end_month == '')
			{
				if($end_year == '')
				{
					if($begin_day == '')
					{
						if($begin_month == '')
						{
							if($begin_year == '')
							{
								return mcal_list_alarms($mcal_stream);
							}
							else
							{
								return mcal_list_alarms($mcal_stream,$begin_year);
							}
						}
						else
						{
							return mcal_list_alarms($mcal_stream,$begin_year,$begin_month);
						}
					}
					else
					{
						return mcal_list_alarms($mcal_stream,$begin_year,$begin_month,$begin_day);
					}
				}
				else
				{
					return mcal_list_alarms($mcal_stream,$begin_year,$begin_month,$begin_day,$end_year);
				}
			}
			else
			{
				return mcal_list_alarms($mcal_stream,$begin_year,$begin_month,$begin_day,$end_year,$end_month);
			}
		}
		else
		{
			return mcal_list_alarms($mcal_stream,$begin_year,$begin_month,$begin_day,$end_year,$end_month,$end_day);
		}
	}
	
	function event_init($stream)
	{
		$this->event = CreateObject('calendar.calendar_item');
		return mcal_event_init($stream);
	}

	function event_set_category($stream,$category='')
	{
		$this->event->category = $category;
		return mcal_event_set_category($stream,$this->event->category);
	}
	
	function event_set_title($stream,$title='')
	{
		$this->event->title = $title;
		return mcal_event_set_title($stream,$this->event->title);
	}

	function event_set_description($stream,$description='')
	{
		$this->event->description = $description;
		return mcal_event_set_description($stream,$this->event->description);
	}

	function event_set_start($stream,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->event->start->year = $year;
		$this->event->start->month = $month;
		$this->event->start->day = $day;
		$this->event->start->hour = $hour;
		$this->event->start->min = $min;
		$this->event->start->sec = $sec;
		
		if($sec == 0)
		{
			if($min == 0)
			{
				if($hour == 0)
				{
					if($day == 0)
					{
						return mcal_event_set_start($stream,$year,$month);
					}
					else
					{
						return mcal_event_set_start($stream,$year,$month,$day);
					}
				}
				else
				{
					return mcal_event_set_start($stream,$year,$month,$day,$hour);
				}
			}
			else
			{
				return mcal_event_set_start($stream,$year,$month,$day,$hour,$min);
			}
		}
		else
		{
			return mcal_event_set_start($stream,$year,$month,$day,$hour,$min,$sec);
		}
	}

	function event_set_end($stream,$year,$month,$day=0,$hour=0,$min=0,$sec=0)
	{
		$this->event->end->year = $year;
		$this->event->end->month = $month;
		$this->event->end->day = $day;
		$this->event->end->hour = $hour;
		$this->event->end->min = $min;
		$this->event->end->sec = $sec;
		
		if($sec == 0)
		{
			if($min == 0)
			{
				if($hour == 0)
				{
					if($day == 0)
					{
						return mcal_event_set_end($stream,$year,$month);
					}
					else
					{
						return mcal_event_set_end($stream,$year,$month,$day);
					}
				}
				else
				{
					return mcal_event_set_end($stream,$year,$month,$day,$hour);
				}
			}
			else
			{
				return mcal_event_set_end($stream,$year,$month,$day,$hour,$min);
			}
		}
		else
		{
			return mcal_event_set_end($stream,$year,$month,$day,$hour,$min,$sec);
		}
	}
}
