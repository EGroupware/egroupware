<?php
  /**************************************************************************\
  * eGroupWare - ICal Calendar                                               *
  * http://www.egroupware.org                                                *
  * Created by Mark Peters <skeeter@phpgroupware.org>                        *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	if (isset($GLOBALS['phpgw_info']['flags']['included_classes']['calendar_']) &&
		$GLOBALS['phpgw_info']['flags']['included_classes']['calendar_'] == True)
	{
		return;
	}

	$GLOBALS['phpgw_info']['flags']['included_classes']['calendar_'] = True;

	class calendar_ extends calendar__
	{
		function open($calendar='',$user='',$passwd='',$options='')
		{
			if($user=='')
			{
				$user = $GLOBALS['phpgw_info']['user']['account_lid'];
			}
			elseif(is_int($user))
			{
				$this->user = $GLOBALS['phpgw']->accounts->id2name($user);
			}
			elseif(is_string($user))
			{
				$this->user = $user;
			}
			if($options != '')
			{
				$this->stream = mcal_open('{'.$GLOBALS['phpgw_info']['server']['icap_server'].'/'.$GLOBALS['phpgw_info']['server']['icap_type'].'}'.$calendar,$this->user,$passwd,$options);
			}
			else
			{
				$this->stream = mcal_open('{'.$GLOBALS['phpgw_info']['server']['icap_server'].'/'.$GLOBALS['phpgw_info']['server']['icap_type'].'}'.$calendar,$this->user,$passwd);
			}
		}

		function popen($calendar='',$user='',$passwd='',$options='')
		{
			if($user=='')
			{
				$this->user = $GLOBALS['phpgw_info']['user']['account_lid'];
			}
			elseif(is_int($user))
			{
				$this->user = $GLOBALS['phpgw']->accounts->id2name($user);
			}
			elseif(is_string($user))
			{
				$this->user = $user;
			}
			if($options != '')
			{
				$this->stream = mcal_popen('{'.$GLOBALS['phpgw_info']['server']['icap_server'].'/'.$GLOBALS['phpgw_info']['server']['icap_type'].'}'.$calendar,$this->user,$passwd,$options);
			}
			else
			{
				$this->stream = mcal_popen('{'.$GLOBALS['phpgw_info']['server']['icap_server'].'/'.$GLOBALS['phpgw_info']['server']['icap_type'].'}'.$calendar,$this->user,$passwd);
			}
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
		}

		function close($options='')
		{
			if($options != '')
			{
				return mcal_close($this->stream,$options);
			}
			else
			{
				return mcal_close($this->stream);
			}
		}

		function create_calendar($calendar)
		{
			return mcal_create_calendar($this->stream,$calendar);
		}

		function rename_calendar($old_name,$new_name)
		{
			return mcal_rename_calendar($this->stream,$old_name,$new_name);
		}

		function delete_calendar($calendar)
		{
			return mcal_delete_calendar($this->stream,$calendar);
		}

		function fetch_event($event_id,$options='')
		{
			if(!isset($this->stream))
			{
				return False;
			}

			$this->event = CreateObject('calendar.calendar_item');

			if($options != '')
			{
				$this->event = mcal_fetch_event($this->stream,$event_id,$options);
			}
			else
			{
				$this->event = mcal_fetch_event($this->stream,$event_id);
			}

			// Need to load the $this->event variable with the $event structure from
			// the mcal_fetch_event() call
			// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
			// This only needs legacy support

			return $this->event;
		}

		function list_events($startYear,$startMonth,$startDay,$endYear='',$endMonth='',$endYear='')
		{
			if($endYear != '' && $endMonth != '' && $endDay != '')
			{
				$events = mcal_list_events($this->stream,$startYear,$startMonth,$startDay,$endYear,$endMonth,$endYear);
			}
			else
			{
				$events = mcal_list_events($this->stream,$startYear,$startMonth,$startDay);
			}

			return $events;
		}

		function append_event()
		{
			return mcal_append_event($this->stream);
		}

		function store_event()
		{
			return mcal_store_event($this->stream);
		}

		function delete_event($event_id)
		{
			return mcal_delete_event($this->stream,$event_id);
		}

		function snooze($event_id)
		{
			return mcal_snooze($this->stream,$event_id);
		}

		function list_alarms($begin_year='',$begin_month='',$begin_day='',$end_year='',$end_month='',$end_day='')
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
									return mcal_list_alarms($this->stream);
								}
								else
								{
									return mcal_list_alarms($this->stream,$begin_year);
								}
							}
							else
							{
								return mcal_list_alarms($this->stream,$begin_year,$begin_month);
							}
						}
						else
						{
							return mcal_list_alarms($this->stream,$begin_year,$begin_month,$begin_day);
						}
					}
					else
					{
						return mcal_list_alarms($this->stream,$begin_year,$begin_month,$begin_day,$end_year);
					}
				}
				else
				{
					return mcal_list_alarms($this->stream,$begin_year,$begin_month,$begin_day,$end_year,$end_month);
				}
			}
			else
			{
				return mcal_list_alarms($this->stream,$begin_year,$begin_month,$begin_day,$end_year,$end_month,$end_day);
			}
		}

		function event_init()
		{
			$this->event = CreateObject('calendar.calendar_item');
			return mcal_event_init($this->stream);
		}

		function set_category($category='')
		{
			calendar__::set_category($category);
			return mcal_event_set_category($this->stream,$category);
		}

		function set_title($title='')
		{
			calendar__::set_title($title);
			return mcal_event_set_title($this->stream,$title);
		}

		function set_description($description='')
		{
			calendar__::set_description($description);
			return mcal_event_set_description($this->stream,$description);
		}

		function set_start($year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			calendar__::set_start($year,$month,$day,$hour,$min,$sec);
			return mcal_event_set_start($this->stream,$year,$month,$day,$hour,$min,$sec);
		}

		function set_end($year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			calendar__::set_end($year,$month,$day,$hour,$min,$sec);
			return mcal_event_set_end($this->stream,$year,$month,$day,$hour,$min,$sec);
		}

		function set_alarm($alarm)
		{
			calendar__::set_alarm($alarm);
			return mcal_event_set_alarm ($this->stream,$alarm);
		}

		function set_class($class)
		{
			calendar__::set_class($class);
			return mcal_event_set_class($this->stream,$class);
		}

		// The function definition doesn't look correct...
		// Need more information for this function
		function next_recurrence($weekstart,$next)
		{
			return mcal_next_recurrence($this->stream,$weekstart,$next);
		}

		function set_recur_none()
		{
			calendar__::set_recur_none();
			return mcal_event_set_recur_none($this->stream);
		}

		function set_recur_secondly($year,$month,$day,$interval)
		{
			calendar__::set_recur_secondly($year,$month,$day,$interval);
			//return mcal_event_set_recur_secondly($this->stream,$year,$month,$day,$interval);
			return 0; // stub - mcal_event_set_recur_secondly() does not exist
		}

		function set_recur_minutely($year,$month,$day,$interval)
		{
			calendar__::set_recur_minutely($year,$month,$day,$interval);
			//return mcal_event_set_recur_minutely($this->stream,$year,$month,$day,$interval);
			return 0; // stub - mcal_event_set_recur_minutely() does not exist
		}

		function set_recur_hourly($year,$month,$day,$interval)
		{
			calendar__::set_recur_hourly($year,$month,$day,$interval);
			//return mcal_event_set_recur_hourly($this->stream,$year,$month,$day,$interval);
			return 0; // stub - mcal_event_set_recur_hourly() does not exist
		}

		function set_recur_daily($year,$month,$day,$interval)
		{
			calendar__::set_recur_daily($year,$month,$day,$interval);
			return mcal_event_set_recur_daily($this->stream,$year,$month,$day,$interval);
		}

		function set_recur_weekly($year,$month,$day,$interval,$weekdays)
		{
			calendar__::set_recur_weekly($year,$month,$day,$interval,$weekdays);
			return mcal_event_set_recur_weekly($this->stream,$year,$month,$day,$interval,$weekdays);
		}

		function set_recur_monthly_mday($year,$month,$day,$interval)
		{
			calendar__::set_recur_monthly_mday($year,$month,$day,$interval);
			return mcal_event_set_recur_monthly_mday($this->stream,$year,$month,$day,$interval);
		}

		function set_recur_monthly_wday($year,$month,$day,$interval)
		{
			calendar__::set_recur_monthly_wday($year,$month,$day,$interval);
			return mcal_event_set_recur_monthly_wday($this->stream,$year,$month,$day,$interval);
		}

		function set_recur_yearly($year,$month,$day,$interval)
		{
			calendar__::set_recur_yearly($year,$month,$day,$interval);
			return mcal_event_set_recur_yearly($this->stream,$year,$month,$day,$interval);
		}

		function fetch_current_stream_event()
		{
			$this->event = mcal_fetch_current_stream_event($this->stream);
			return $this->event;
		}

		function add_attribute($attribute,$value)
		{
			calendar__::add_attribute($attribute,$value);
			return mcal_event_add_attribute($this->stream,$attribute,$value);
		}

		function expunge()
		{
			return mcal_expunge($this->stream);
		}

		/**************** Local functions for ICAL based Calendar *****************/

		function set_status($id,$owner,$status)
		{
			$status_code_short = Array(
				REJECTED =>	'R',
				NO_RESPONSE	=> 'U',
				TENTATIVE	=>	'T',
				ACCEPTED	=>	'A'
			);
			$this->add_attribute('status['.$owner.']',$status_code_short[$status]);
	//		$this->stream->query("UPDATE calendar_entry_user SET cal_status='".$status_code_short[$status]."' WHERE cal_id=".$id." AND cal_login=".$owner,__LINE__,__FILE__);
			return True;
		}
	}
