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

class calendar_
{
	var $stream;
	var $user;
	var $event;

	var $printer_friendly = False;
	var $owner;
	var $rights;
    
	var $cal_event;
	var $today = Array('raw','day','month','year','full','dow','dm','bd');
	var $repeated_events;
	var $checked_events;
	var $re = 0;
	var $checkd_re = 0;
	var $sorted_re = 0;
	var $days = Array();
	var $weekstarttime;
	var $daysinweek = 7;
	var $filter;
	var $tempyear;
	var $tempmonth;
	var $tempday;

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
		if(!isset($this->stream))
		{
			return False;
		}
	  
		$this->stream->lock(array('calendar_entry','calendar_entry_user','calendar_entry_repeats'));

		$this->stream->query('SELECT * FROM calendar_entry WHERE cal_id='.$event_id,__LINE__,__FILE__);
		
		if($this->stream->num_rows() > 0)
		{
			$this->event = CreateObject('calendar.calendar_item');
			$this->stream->next_record();
			// Load the calendar event data from the db into $this->event structure
			// Use http://www.php.net/manual/en/function.mcal-fetch-event.php as the reference
			
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

	/***************** Local functions for SQL based Calendar *****************/

	function get_event_ids($search_repeats=False,$extra='')
	{
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
		
		$sql = 'SELECT DISTINCT calendar_entry.cal_id '
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
// End of ICal style support.......

	function set_filter()
	{
		global $phpgw_info, $phpgw, $filter;
		if (!isset($this->filter) || !$this->filter)
		{
			if (isset($filter) && $filter)
			{
				$this->filter = ' '.$filter.' ';
			}
         else
			{
				if (!isset($phpgw_info['user']['preferences']['calendar']['defaultfilter']))
				{
					$phpgw->preferences->add('calendar','defaultfilter','all');
					$phpgw->preferences->save_repository(True);
				}
				$this->filter = ' '.$phpgw_info['user']['preferences']['calendar']['defaultfilter'].' ';
			}
		}
	}

	function check_perms($needed)
	{
		if($this->rights & $needed)
		{
			return True;
		}
		else
		{
			return False;
		}
	}

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

	function get_weekday_start($year,$month,$day) {
		global $phpgw_info;

		$weekday = date('w',mktime(0,0,0,$month,$day,$year));

		if ($phpgw_info['user']['preferences']['calendar']['weekdaystarts'] == 'Monday')
		{
			$days = Array(
				0	=>	'Mon',
				1	=>	'Tue',
				2	=>	'Wed',
				3	=>	'Thu',
				4	=>	'Fri',
				5	=>	'Sat',
				6	=>	'Sun'
			);
			$sday = mktime(0,0,0,$month,$day - ($weekday - 1),$year);
		}
		else
		{
			$days = Array(
				0	=>	'Sun',
				1	=>	'Mon',
				2	=>	'Tue',
				3	=>	'Wed',
				4	=>	'Thu',
				5	=>	'Fri',
				6	=>	'Sat'
			);
			$sday = mktime(0,0,0,$month,$day - $weekday,$year);
		}

		$this->days = $days;
      return $sday;
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

	function splittime($time)
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

	function is_private($cal_info,$owner,$field)
	{
		global $phpgw, $phpgw_info;

		$is_private  = False;
		if ($owner == $phpgw_info['user']['account_id'] || $owner == 0 || $this->check_perms(16) == True)
		{
		}
		elseif ($cal_info->access == 'private')
		{
			$is_private = True;
		}
		elseif($cal_info->access == 'group')
		{
			$is_private = True;
			$groups = $phpgw->accounts->memberships($owner);
			while ($group = each($groups))
			{
				if (strpos(' '.$cal_info->groups.' ',','.$group[1]['account_id']).',')
				{
					$is_private = False;
				}
			}
		}

		if ($is_private)
		{
			$str = 'private';
		}
		elseif (strlen($cal_info->$field) > 19)
		{
			$str = substr($cal_info->$field, 0 , 19) . '...';
		}
		else
		{
			$str = $cal_info->$field;
		}
		return $str;
	}

	function read_repeated_events($owner=0)
	{
		global $phpgw, $phpgw_info;

		$this->re = 0;
		$this->set_filter();
		$owner = $owner == 0?$phpgw_info['user']['account_id']:$owner;
		$sql = "AND calendar_entry.cal_type='M' AND ";
		$sqlfilter='';
		
// Private
		if($this->filter==' all ' || strpos($this->filter,'private'))
		{
			$sqlfilter .= '(calendar_entry_user.cal_login='.$owner." AND calendar_entry.cal_access='private') ";
		}

// Group Public
		if($this->filter==' all ' || strpos($this->filter,'group'))
		{
			if($sqlfilter)
			{
				$sqlfilter .= 'OR ';
			}
			
			$sqlfilter .= '(calendar_entry_user.cal_login='.$owner.' OR '.$this->group_search($owner).') ';
		}

// Global Public
		if($this->filter==' all ' || strpos($this->filter,'public'))
		{
			if($sqlfilter)
			{
				$sqlfilter .= 'OR ';
			}
			$sqlfilter .= "calendar_entry.cal_access='public' ";
		}
		
		$orderby = ' ORDER BY calendar_entry.cal_datetime ASC, calendar_entry.cal_edatetime ASC, calendar_entry.cal_priority ASC';

		$db2 = $phpgw->db;

		if($sqlfilter)
		{
			$sql .= '('.$sqlfilter.') ';
		}
		
		$sql .= $orderby;

		$events = $this->get_event_ids(True,$sql);

		if($events == False)
		{
			$this->repeated_events = Null;
		}
		else
		{
			$this->re = count($events);
			$this->repeated_events = $this->getevent($events);
		}
	}

	function link_to_entry($id, $pic, $description)
	{
		global $phpgw, $phpgw_info;

		$str = '';
		if (!$this->printer_friendly)
		{
			$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
			$p->set_unknowns('remove');
			$p->set_file(array('link_pict' => 'link_pict.tpl'));
//			$p->set_block('link_pict','link_pict');
			$p->set_var('link_link',$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/view.php','id='.$id.'&owner='.$this->owner));
			$p->set_var('lang_view',lang('View this entry'));
			$p->set_var('pic_image',$phpgw->common->get_image_path('calendar').'/'.$pic);
			$p->set_var('description',$description);
			$str = $p->finish($p->parse('out','link_pict'));
			unset($p);
		}
		return $str;
	}

	function build_time_for_display($fixed_time)
	{
		global $phpgw_info;
		
//		echo "<br>before: $fixed_time";
		$time = $this->splittime($fixed_time);
//		echo '<br>test -> build_time_for_display () in if ' . $time['hour'] . ' ' . $time['ampm'];
//		echo "<br>&nbsp;&nbsp;$fixed_time";
		$str = '';
		$str .= $time['hour'].':'.((int)$time['minute']<=9?'0':'').$time['minute'];
		
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			$str .= ' ' . $time['ampm'];
		}
		
		return $str;
	}

	function check_repeating_entries($datetime)
	{
		global $phpgw, $phpgw_info;

		$this->checked_re = 0;
		
		if(!$this->re)
		{
			return False;
		}
		
		$link = Array();
//		$date = $this->gmtdate($datetime);
		$date = $this->localdates($datetime);
		for ($i=0;$i<$this->re;$i++)
		{
			$rep_events = $this->repeated_events[$i];
			$frequency = intval($rep_events->rpt_freq);
			$start = $this->localdates($rep_events->datetime);
			if($rep_events->rpt_use_end)
			{
				$enddate = $this->gmtdate($rep_events->rpt_end);
			}
			else
			{
				$enddate   = $this->makegmttime(0,0,0,1,1,2007);
			}
			
			// only repeat after the beginning, and if there is an rpt_end before the end date
			if (($rep_events->rpt_use_end && ($date['full'] > $enddate['full'])) ||
				($date['full'] < $start['full']))
			{
				continue;
			}

			if ($date['full'] == $start['full'])
			{
				$link[$this->checked_re++] = $i;
			}
			elseif ($rep_events->rpt_type == 'daily')
			{
				if ((floor(($date['bd'] - $start['bd'])/86400) % $frequency))
				{
					continue;
				}
				else
				{
					$link[$this->checked_re++] = $i;
				}
			}
			elseif ($rep_events->rpt_type == 'weekly')
			{
				$isDay = strtoupper(substr($rep_events->rpt_days, $date['dow'], 1));
				
				if (floor(($date['bd'] - $start['bd'])/604800) % $frequency)
				{
					continue;
				}
				
				if (strcmp($isDay,'Y') == 0)
				{
					$link[$this->checked_re++] = $i;
				}
			}
			elseif ($rep_events->rpt_type == 'monthlybyday')
			{
				if ((($date['year'] - $start['year']) * 12 + $date['month'] - $start['month']) % $frequency)
				{
					continue;
				}
	  
				if (($start['dow'] == $date['dow']) &&
					(ceil($start['day']/7) == ceil($date['day']/7)))
				{
					$link[$this->checked_re++] = $i;
				}
			}
			elseif ($rep_events->rpt_type == 'monthlybydate')
			{
				if ((($date['year'] - $start['year']) * 12 + $date['month'] - $start['month']) % $frequency)
				{
					continue;
				}
				
				if ($date['day'] == $start['day'])
				{
					$link[$this->checked_re++] = $i;
				}
			}
			elseif ($rep_events->rpt_type == 'yearly')
			{
				if (($date['year'] - $start['year']) % $frequency)
				{
					continue;
				}
				
				if ($date['dm'] == $start['dm'])
				{
					$link[$this->checked_re++] = $i;
				}
			}
			else
			{
				// unknown rpt type - because of all our else ifs
			}
		}	// end for loop

		if($this->checked_re)
		{
			return $link;
		}
		else
		{
			return False;
		}
	}	// end function

// Start from here to meet coding stds.......
	function get_sorted_by_date($datetime,$owner=0)
	{
		global $phpgw, $phpgw_info;

		$this->sorted_re = 0;
		$this->set_filter();
		$owner = !$owner?$phpgw_info['user']['account_id']:$owner;
		$rep_event = $this->check_repeating_entries($datetime);
		$sql = 'SELECT DISTINCT calendar_entry.cal_id, calendar_entry.cal_datetime, '
				. 'calendar_entry.cal_edatetime, calendar_entry.cal_priority '
				. 'FROM calendar_entry, calendar_entry_user '
				. 'WHERE ((calendar_entry.cal_datetime >= '.$datetime.' AND calendar_entry.cal_datetime <= '.($datetime + 86399).') OR '
				. '(calendar_entry.cal_datetime <= '.$datetime.' AND calendar_entry.cal_edatetime >= '.($datetime + 86399).') OR '
				. '(calendar_entry.cal_edatetime >= '.$datetime.' AND calendar_entry.cal_edatetime <= '.($datetime + 86399).')) AND '
				. "calendar_entry_user.cal_id=calendar_entry.cal_id AND calendar_entry.cal_type != 'M' AND ";
				
		$sqlfilter = '';
		
// Private
		if($this->filter==' all ' || strpos($this->filter,'private'))
		{
			$sqlfilter .= '(calendar_entry_user.cal_login = '.$owner." AND calendar_entry.cal_access='private') ";
		}

// Group Public
		if($this->filter==' all ' || strpos($this->filter,'group'))
		{
			if($sqlfilter)
			{
				$sqlfilter .= 'OR ';
			}
			
			$sqlfilter .= $this->group_search($owner).' ';
		}

// Global Public
		if($this->filter==' all ' || strpos($this->filter,'public'))
		{
			if($sqlfilter)
			{
				$sqlfilter .= 'OR ';
			}
			
			$sqlfilter .= "calendar_entry.cal_access='public' ";
		}
		
		$orderby = ' ORDER BY calendar_entry.cal_datetime ASC, calendar_entry.cal_edatetime ASC, calendar_entry.cal_priority ASC';

		$db2 = $phpgw->db;

		if($sqlfilter)
		{
			$sql .= '('.$sqlfilter.') ';
		}
		
		$sql .= $orderby;

		$db2->query($sql,__LINE__,__FILE__);

		$events = Null;
		$rep_events = Array();
		if($db2->num_rows())
		{
			while($db2->next_record())
			{
				$rep_events[$this->sorted_re++] = (int)$db2->f(0);
			}
			
			$events = $this->getevent($rep_events);
		}
		else
		{
			$events = Array(CreateObject('calendar.calendar_item'));
		}

		if(!$this->checked_re && !$this->sorted_re)
		{
			return False;
		}

		$e = CreateObject('calendar.calendar_item');
		for ($j=0;$j<$this->checked_re;$j++)
		{
			$e = $this->repeated_events[$rep_event[$j]];
			$events[$this->sorted_re++] = $e;
		}
		
		if($this->sorted_re == 0)
		{
			return False;
		}
		
		if($this->sorted_re == 1)
		{
			return $events;
		}
		
		for($outer_loop=0;$outer_loop<($this->sorted_re - 1);$outer_loop++)
		{
			$outer = $events[$outer_loop];
			$outer_time = $phpgw->common->show_date($outer->datetime,'Hi');
			$outer_etime = $phpgw->common->show_date($outer->edatetime,'Hi');
			
			if($outer->datetime < $datetime)
			{
				$outer_time = 0;
			}
			
			if($outer->edatetime > ($datetime + 86399))
			{
				$outer_etime = 2359;
			}
			
			for($inner_loop=$outer_loop;$inner_loop<$this->sorted_re;$inner_loop++)
			{
				$inner = $events[$inner_loop];
				$inner_time = $phpgw->common->show_date($inner->datetime,'Hi');
				$inner_etime = $phpgw->common->show_date($inner->edatetime,'Hi');
				
				if($inner->datetime < $datetime)
				{
					$inner_time = 0;
				}
				
				if($inner->edatetime > ($datetime + 86399))
				{
					$inner_etime = 2359;
				}
				
				if(($outer_time > $inner_time) ||
					(($outer_time == $inner_time) && ($outer_etime > $inner_etime)))
				{
					$temp = $events[$inner_loop];
					$events[$inner_loop] = $events[$outer_loop];
					$events[$outer_loop] = $temp;
				}
			}
		}
		
		if(isset($events))
		{
			return $events;
		}
		else
		{
			return False;
		}
	}

	function large_month_header($month,$year,$display_name = False)
	{
		global $phpgw, $phpgw_info;

		$this->weekstarttime = $this->get_weekday_start($year,$month,1);

		$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
		$p->set_unknowns('remove');
		$templates = Array (
			'month_header' => 'month_header.tpl',
			'column_title' => 'column_title.tpl'
		);
		$p->set_file($templates);

		$var = Array(
			'bgcolor'		=> $phpgw_info['theme']['th_bg'],
			'font_color'	=> $phpgw_info['theme']['th_text']
		);
		$p->set_var($var);
		
		if($display_name == True)
		{
			$p->set_var('col_title',lang('name'));
			$p->parse('column_header','column_title',True);
		}

		for($i=0;$i<$this->daysinweek;$i++)
		{
			$p->set_var('col_title',lang($this->days[$i]));
			$p->parse('column_header','column_title',True);
		}
		
		return $p->finish($p->parse('out','month_header'));
	}

	function display_week($startdate,$weekly,$cellcolor,$display_name = False,$owner=0,$monthstart=0,$monthend=0)
	{
		global $phpgw, $phpgw_info;

		$str = '';
		$gr_events = CreateObject('calendar.calendar_item');
		$lr_events = CreateObject('calendar.calendar_item');

		$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
		$p->set_unknowns('remove');
		
		$templates = Array (
			'month_header'		=> 'month_header.tpl',
			'month_column'		=> 'month_column.tpl',
			'month_day'			=> 'month_day.tpl',
			'week_day_event'	=> 'week_day_event.tpl',
			'week_day_events'	=> 'week_day_events.tpl',
			'link_pict'			=>	'link_pict.tpl'
		);
		$p->set_file($templates);

		$p->set_var('extra','');
		
		if($display_name)
		{
			$p->set_var('column_data',$phpgw->common->grab_owner_name($owner));
			$p->parse('column_header','month_column',True);
		}
		
		for ($j=0;$j<$this->daysinweek;$j++)
		{
			$date = $this->gmtdate($startdate + ($j * 24 * 3600));
			$p->set_var('column_data','');
			$p->set_var('extra','');
			
			if ($weekly || ($date['full'] >= $monthstart && $date['full'] <= $monthend))
			{
				if($weekly)
				{
					$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
				}
				
				if ($date['full'] == $this->today['full'])
				{
					$p->set_var('extra',' bgcolor="'.$phpgw_info['theme']['cal_today'].'"');
				}
				else
				{
					$p->set_var('extra',' bgcolor="'.$cellcolor.'"');
				}

				if (!$this->printer_friendly)
				{
					$str = '';
					
					if($this->check_perms(PHPGW_ACL_ADD) == True)
					{
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/edit_entry.php','year='.$date_year.'&month='.$date['month'].'&day='.$date['day'].'&owner='.$this->owner).'">';
						$str .= '<img src="'.$phpgw->common->get_image_path('calendar').'/new.gif" width="10" height="10" ';
						$str .= 'alt="'.lang('New Entry').'" ';
						$str .= 'border="0" align="right">';
						$str .= '</a>';
					}
            
					$p->set_var('new_event_link',$str);
					$str = '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/day.php','month='.$date['month'].'&day='.$date['day'].'&year='.$date['year'].'&owner='.$this->owner).'">'.$date['day'].'</a>';
					$p->set_var('day_number',$str);
				}
				else
				{
					$p->set_var('new_event_link','');
					$p->set_var('day_number',$date['day']);
				}
				
				$p->parse('column_data','month_day',True);

				$rep_events = $this->get_sorted_by_date($date['raw'],$owner);

				if ($this->sorted_re)
				{
					$lr_events = CreateObject('calendar.calendar_item');
					$p->set_var('week_day_font_size','2');
					$p->set_var('events','');
					for ($k=0;$k<$this->sorted_re;$k++)
					{
						$lr_events = $rep_events[$k];
						$pict = 'circle.gif';
						for ($outer_loop=0;$outer_loop<$this->re;$outer_loop++)
						{
							$gr_events = $this->repeated_events[$outer_loop];
							if ($gr_events->id == $lr_events->id)
							{
								$pict = "rpt.gif";
							}
						}
						
						$p->set_var('link_entry','');
						$description = $this->is_private($lr_events,$owner,'description');

						if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(16)) || ($description != 'private'))  && $this->check_perms(PHPGW_ACL_EDIT))
						{
							$p->set_var('link_link',$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/view.php','id='.$lr_events->id.'&owner='.$owner));
							$p->set_var('lang_view',lang('View this entry'));
							$p->set_var('pic_image',$phpgw->common->get_image_path('calendar').'/'.$pict);
							$p->set_var('description',$description);
							$p->parse('link_entry','link_pict');
						}

//						$p->set_var('link_entry',$this->link_to_entry($lr_events->id, $pict, $this->is_private($lr_events,$owner,'description')));
						if (intval($phpgw->common->show_date($lr_events->datetime,"Hi")))
						{
							if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12")
							{
								$format = "h:i a";
							}
							else
							{
								$format = "H:i";
							}
							
							if($lr_events->datetime < $date["raw"] && $lr_events->rpt_type=="none")
							{
								$temp_time = $this->makegmttime(0,0,0,$date["month"],$date["day"],$date["year"]);
								$start_time = $phpgw->common->show_date($temp_time["raw"],$format);
							}
							else
							{
								$start_time = $phpgw->common->show_date($lr_events->datetime,$format);
							}
                
							if($lr_events->edatetime > ($date["raw"] + 86400))
							{
								$temp_time = $this->makegmttime(23,59,59,$date["month"],$date["day"],$date["year"]);
								$end_time = $phpgw->common->show_date($temp_time["raw"],$format);
							}
							else
							{
								$end_time = $phpgw->common->show_date($lr_events->edatetime,$format);
							}
							
							$p->set_var('start_time',$start_time);
							$p->set_var('end_time',$end_time);
						}
						else
						{
							$p->set_var('start_time','');
							$p->set_var('end_time','');
						}
						
						if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(16)) || ($description != 'private'))  && $this->check_perms(PHPGW_ACL_EDIT))
						{
							$p->set_var('close_view_link','</a>');
						}
						else
						{
							$p->set_var('close_view_link','');
						}
						$p->set_var('name',$this->is_private($lr_events,$owner,'name'));
						$p->parse('events','week_day_event',True);
					}
				}
				$p->parse('column_data','week_day_events',True);
				$p->set_var('events','');
				if (!$j || ($j && $date["full"] == $monthstart))
				{
					$p->set_var('week_day_font_size','-2');
					
					if(!$this->printer_friendly)
					{
						$str = '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/week.php','date='.$date['full'].'&owner='.$this->owner).'">week ' .(int)((date('z',($startdate+(24*3600*4)))+7)/7).'</a>';
					}
					else
					{
						$str = 'week ' .(int)((date('z',($startdate+(24*3600*4)))+7)/7);
					}
					
					$p->set_var('events',$str);
					$p->parse('column_data','week_day_events',True);
					$p->set_var('events','');
				}
			}
			$p->parse('column_header','month_column',True);
			$p->set_var('column_data','');
		}
		return $p->finish($p->parse('out','month_header'));
	}

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
		
		for($i=0;$i<$daysinweek;$i++)
		{
			$str .= '<td>'.lang($days[$i]).'</td>';
		}
		
		$str .= '</tr>';

		for($i=$weekstarttime;date('Ymd',$i)<=$monthend['full'];$i+=604800)
		{
			$str .= '<tr>';
			for($j=0;$j<$daysinweek;$j++)
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
					$rpt_end = $phpgw->common->show_date($db2->f('cal_end'),'Ymd');
//					$date = $this->date_to_epoch($rpt_end);
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
