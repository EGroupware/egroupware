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
			$event = mcal_fetch_event($mcal_stream,$event_id,$options);
		}
		else
		{
			$event = mcal_fetch_event($mcal_stream,$event_id);
		}

		// Need to load the $this->event variable with the data from the mcal_fetch_event() call
		// Use http://www.php.net/manual/en/function.mcal-fetch-event.phpas the reference

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

	function event_init($stream)
	{
		return mcal_event_init($stream);
	}

}
