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

CreateObject('calendar.calendar_item');
if($phpgw_info['server']['calendar_type'] == 'mcal' && extension_loaded('mcal') == False)
{
	$phpgw_info['server']['calendar_type'] = 'sql';
}
// The following line can be removed when vCalendar is implemented....
$phpgw_info['server']['calendar_type'] = 'sql';
//CreateObject('calendar.vCalendar');
CreateObject('calendar.calendar__');
include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_'.$phpgw_info['server']['calendar_type'].'.inc.php');

class calendar extends calendar_
{
	var $owner;
	var $rights;
	var $printer_friendly = False;

	var $template_dir;
	var $phpgwapi_template_dir;
	var $image_dir;

	var $filter;
	var $repeating_events;
	var $repeated_events = Array();
	var $repeating_event_matches = 0;
	var $sorted_events_matching = 0;
	var $weekstarttime;
	var $days = Array();

	var $tempyear;
	var $tempmonth;
	var $tempday;

	var $rowspan_arr = Array();
	var $rowspan;

	function calendar($params=False)
	{
		global $phpgw, $phpgw_info;
	  
		if(gettype($params)=='array')
		{
			while(list($key,$value) = each($params))
			{
				$this->$key = $value;
			}
		}
		else
		{
			$this->printer_friendly = $params;
		}

		if(!$this->owner)
		{
			$this->owner = $phpgw_info['user']['account_id'];
		}
      
		if(!isset($this->rights))
		{
			$this->rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + PHPGW_ACL_PRIVATE;
		}

		$this->template_dir = $phpgw->common->get_tpl_dir('calendar');
		$this->phpgwapi_template_dir = $phpgw->common->get_image_path('phpgwapi');
		$this->image_dir = $phpgw->common->get_image_path('calendar');
		$this->today = $this->localdates(time());

		$this->open('',intval($this->owner));
		$this->read_repeated_events($this->owner);
		$this->set_filter();
	}

// Generic functions that are derived from mcal functions.
// NOT PART OF THE ORIGINAL MCAL SPECS.
	function time_compare($a_hour,$a_minute,$a_second,$b_hour,$b_minute,$b_second)
	{
		$a_time = mktime(intval($a_hour),intval($a_minute),intval($a_second),0,0,0);
		$b_time = mktime(intval($b_hour),intval($b_minute),intval($b_second),0,0,0);
		if($a_time == $b_time)
		{
			return 0;
		}
		elseif($a_time > $b_time)
		{
			return 1;
		}
		elseif($a_time < $b_time)
		{
			return -1;
		}
	}

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
		return (!!($this->rights & $needed) == True);
	}

	function get_long_status($status_short)
	{
		switch ($status_short)
		{
			case 'A':
				$status = 'Accepted';
				break;
			case 'R':
				$status = 'Rejected';
				break;
			case 'T':
				$status = 'Tentative';
				break;
			case 'U':
				$status = 'No Response';
				break;
		}
		return $status;
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
			$sday = mktime(2,0,0,$month,$day - ($weekday - 1),$year);
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
			$sday = mktime(2,0,0,$month,$day - $weekday,$year);
		}

		$this->days = $days;
      return $sday;
	}

	function link_to_entry($id, $pic, $description)
	{
		global $phpgw, $phpgw_info;

		$str = '';
		if (!$this->printer_friendly)
		{
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');
			$p->set_file(array('link_pict' => 'link_pict.tpl'));
			$p->set_var('link_link',$phpgw->link('/calendar/view.php','id='.$id.'&owner='.$this->owner));
			$p->set_var('lang_view',lang('View this entry'));
			$p->set_var('pic_image',$this->image_dir.'/'.$pic);
			$p->set_var('description',$description);
			$str = $p->finish($p->parse('out','link_pict'));
			unset($p);
		}
		return $str;
	}

	function is_private($event,$owner,$field)
	{
		global $phpgw, $phpgw_info, $grants;

		if($owner == 0) { $owner = $phpgw_info['user']['account_id']; }
		$is_private  = False;
		if ($owner == $phpgw_info['user']['account_id'] || (!!($grants[$owner] & PHPGW_ACL_PRIVATE) == True))
		{
		}
		elseif ($event->public == False)
		{
			$is_private = True;
		}
		elseif($event->access == 'group')
		{
			$is_private = True;
			$groups = $phpgw->accounts->memberships($owner);
			while ($group = each($groups))
			{
				if (strpos(' '.implode($event->groups,',').' ',$group[1]['account_id']))
				{
					$is_private = False;
				}
			}
		}

		if ($is_private)
		{
			$str = 'private';
		}
		elseif (strlen($event->$field) > 19)
		{
			$str = substr($event->$field, 0 , 19) . '...';
		}
		else
		{
			$str = $event->$field;
		}

		return $str;
	}

	function read_repeated_events($owner=0)
	{
		global $phpgw, $phpgw_info;

		$this->set_filter();
		$owner = $owner == 0?$phpgw_info['user']['account_id']:$owner;
		$sql = "AND (calendar_entry.cal_type='M') "
			. 'AND (calendar_entry_user.cal_login='.$owner;

// Private
		if(strpos($this->filter,'private'))
		{
			$sql .= " AND calendar_entry.cal_access='private'";
		}
		
		$sql .= ') ORDER BY calendar_entry.cal_datetime ASC, calendar_entry.cal_edatetime ASC, calendar_entry.cal_priority ASC';

		$events = $this->get_event_ids(True,$sql);

		if($events == False)
		{
			$this->repeated_events = Null;
			$this->repeating_events = False;
		}
		else
		{
			$this->repeated_events = $events;
			for($i=0;$i<count($events);$i++)
			{
				$this->repeating_events[] = $this->fetch_event($this->stream,$events[$i]);
			}
		}
	}

	function check_repeating_entries($datetime)
	{
		global $phpgw, $phpgw_info;

		$this->repeating_event_matches = 0;

		if(count($this->repeated_events) <= 0)
		{
			return False;
		}
		
		$link = Array();
		$search_date_full = date('Ymd',$datetime);
		$search_date_year = date('Y',$datetime);
		$search_date_month = date('m',$datetime);
		$search_date_day = date('d',$datetime);
		$search_date_dow = date('w',$datetime);
		$search_beg_day = mktime(0,0,0,$search_date_month,$search_date_day,$search_date_year);
		for ($i=0;$i<count($this->repeated_events);$i++)
		{
			$rep_events = $this->repeating_events[$i];
			$id = $rep_events->id;
			$event_beg_day = mktime(0,0,0,$rep_events->start->month,$rep_events->start->mday,$rep_events->start->year);
			$event_recur_time = mktime($rep_events->recur_enddate->hour,$rep_events->recur_enddate->min,$rep_events->recur_enddate->sec,$rep_events->recur_enddate->month,$rep_events->recur_enddate->mday,$rep_events->recur_enddate->year);
			if($event_recur_time != 0)
			{
				$end_recur_date = date('Ymd',$event_recur_time);
			}
			else
			{
				$end_recur_date = date('Ymd',mktime(0,0,0,1,1,2007));
			}
			$full_event_date = date('Ymd',$event_beg_day);
			
			// only repeat after the beginning, and if there is an rpt_end before the end date
			if (($rep_events->rpt_use_end && ($search_date_full > $end_recur_date)) ||
				($search_date_full < $full_event_date))
			{
				continue;
			}

			if ($search_date_full == $full_event_date)
			{
				$link[$this->repeating_event_matches++] = $id;
			}
			else
			{
				switch($rep_events->recur_type)
				{
					case RECUR_DAILY:
						if (floor(($search_beg_day - $event_beg_day)/86400) % $rep_events->recur_interval)
						{
							continue;
						}
						else
						{
							$link[$this->repeating_event_matches++] = $id;
						}
						break;
					case RECUR_WEEKLY:
						$check = 0;
						switch($search_date_dow)
						{
							case 0:
								$check = M_SUNDAY;
								break;
							case 1:
								$check = M_MONDAY;
								break;
							case 2:
								$check = M_TUESDAY;
								break;
							case 3:
								$check = M_WEDNESDAY;
								break;
							case 4:
								$check = M_THURSDAY;
								break;
							case 5:
								$check = M_FRIDAY;
								break;
							case 6:
								$check = M_SATURDAY;
								break;
						}
						if (floor(($search_beg_day - $event_beg_day)/604800) % $rep_events->recur_interval)
						{
							continue;
						}
				
						if ($rep_events->recur_data & $check)
						{
							$link[$this->repeating_event_matches++] = $id;
						}
						break;
					case RECUR_MONTHLY_WDAY:
						if ((($search_date_year - $rep_events->start->year) * 12 + $search_date_month - $rep_events->start->month) % $rep_events->recur_interval)
						{
							continue;
						}
	  
						if (($this->day_of_week($rep_events->start->year,$rep_events->start->month,$rep_events->start->mday) == $this->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
							(ceil($rep_events->start->mday/7) == ceil($search_date_day/7)))
						{
							$link[$this->repeating_event_matches++] = $id;
						}
						break;
					case RECUR_MONTHLY_MDAY:
						if ((($search_date_year - $rep_events->start->year) * 12 + $search_date_month - $rep_events->start->month) % $rep_events->recur_interval)
						{
							continue;
						}
				
						if ($search_date_day == $rep_events->start->mday)
						{
							$link[$this->repeating_event_matches++] = $id;
						}
						break;
					case RECUR_YEARLY:
						if (($search_date_year - $rep_events->start->year) % $rep_events->recur_interval)
						{
							continue;
						}
				
						if (date('dm',$datetime) == date('dm',$event_beg_day))
						{
							$link[$this->repeating_event_matches++] = $id;
						}
						break;
				}
			}
		}	// end for loop

		if($this->repeating_event_matches > 0)
		{
			return $link;
		}
		else
		{
			return False;
		}
	}	// end function

	function get_sorted_by_date($datetime,$owner=0)
	{
		global $phpgw, $phpgw_info;

		$this->sorted_events_matching = 0;
		$this->set_filter();
		$owner = !$owner?$phpgw_info['user']['account_id']:$owner;
		$repeating_events_matched = $this->check_repeating_entries($datetime);
		$sql = "AND (calendar_entry.cal_type != 'M') "
				. 'AND ((calendar_entry.cal_datetime >= '.$datetime.' AND calendar_entry.cal_datetime <= '.($datetime + 86399).') '
				.   'OR (calendar_entry.cal_datetime <= '.$datetime.' AND calendar_entry.cal_edatetime >= '.($datetime + 86399).') '
				.   'OR (calendar_entry.cal_edatetime >= '.$datetime.' AND calendar_entry.cal_edatetime <= '.($datetime + 86399).')) '
				. 'AND (calendar_entry_user.cal_login='.$owner;

// Private
		if(strpos($this->filter,'private'))
		{
			$sql .= " AND calendar_entry.cal_access='private'";
		}
		
		$sql .= ') ORDER BY calendar_entry.cal_datetime ASC, calendar_entry.cal_edatetime ASC, calendar_entry.cal_priority ASC';

		$event = $this->get_event_ids(False,$sql);

		if($this->repeating_event_matches == False && $event == False)
		{
			return False;
		}

		if($this->repeating_event_matches != False)
		{
			reset($repeating_events_matched);
			while(list($key,$value) = each($repeating_events_matched))
			{
				$event[] = $value;
			}
		}

		$this->sorted_events_matching = count($event);

		if($this->sorted_events_matching == 0)
		{
			return False;
		}
		else
		{
			for($i=0;$i<$this->sorted_events_matching;$i++)
			{
				$events[] = $this->fetch_event($this->stream,$event[$i]);
			}

			if($this->sorted_events_matching == 1)
			{
				return $events;
			}
		}

		for($outer_loop=0;$outer_loop<($this->sorted_events_matching - 1);$outer_loop++)
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
			
			for($inner_loop=$outer_loop;$inner_loop<$this->sorted_events_matching;$inner_loop++)
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

	function mini_calendar($day,$month,$year,$link='')
	{
		global $phpgw, $phpgw_info, $view;

		$date = $this->makegmttime(0,0,0,$month,$day,$year);
		$month_ago = intval(date('Ymd',mktime(0,0,0,$month - 1,$day,$year)));
		$month_ahead = intval(date('Ymd',mktime(0,0,0,$month + 1,$day,$year)));
		$monthstart = intval(date('Ymd',mktime(0,0,0,$month,1,$year)));
		$monthend = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

		$weekstarttime = $this->get_weekday_start($year,$month,1);

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');

		$templates = Array(
			'mini_cal'	=> 'mini_cal.tpl',
			'mini_day'	=>	'mini_day.tpl',
			'mini_week'	=> 'mini_week.tpl'
		);
		$p->set_file($templates);

		if($this->printer_firendly == False)
		{
			$month = '<a href="' . $phpgw->link('/calendar/month.php','month='.date('m',$date['raw']).'&year='.date('Y',$date['raw']).'&owner='.$this->owner) . '" class="minicalendar">' . lang($phpgw->common->show_date($date['raw'],'F')).' '.$year . '</a>';
		}
		else
		{
			$month = lang($phpgw->common->show_date($date['raw'],'F')).' '.$year;
		}

		$var = Array(
			'img_root'			=>	$this->phpgwapi_template_dir,
			'cal_img_root'		=>	$this->image_dir,
			'bgcolor'			=>	$phpgw_info['theme']['bg_color'],
			'bgcolor1'			=>	$phpgw_info['theme']['bg_color'],
			'month'				=>	$month,
			'prevmonth'			=>	$phpgw->link('/calendar/month.php','date='.$month_ago.'&owner='.$this->owner),
			'nextmonth'			=>	$phpgw->link('/calendar/month.php','date='.$month_ahead.'&owner='.$this->owner),
			'bgcolor2'			=>	$phpgw_info['theme']['cal_dayview']
		);

		$p->set_var($var);
		
		for($i=0;$i<7;$i++)
		{
			$p->set_var('dayname','<b>' . substr(lang($this->days[$i]),0,2) . '</b>');
			$p->parse('daynames','mini_day',True);
		}
		
		for($i=$weekstarttime;date('Ymd',$i)<=$monthend;$i += (24 * 3600 * 7))
		{
			for($j=0;$j<7;$j++)
			{
				$str = '';
				$cal = $this->gmtdate($i + ($j * 24 * 3600));
				if($cal['full'] >= $monthstart && $cal['full'] <= $monthend)
				{
					if ($cal['full'] == $this->today['full'])
					{
						$p->set_var('day_image',' background="'.$this->image_dir.'/mini_day_block.gif"');
					}
					else
					{
						$p->set_var('day_image','');
						$p->set_var('bgcolor2','#FFFFFF');
					}
					
					if(!$this->printer_friendly)
					{
						$str .= '<a href="'.$phpgw->link('/calendar/'.$link,'year='.$cal['year'].'&month='.$cal['month'].'&day='.$cal['day'].'&owner='.$this->owner).'" class="minicalendar">';
					}
					
					$str .= $cal['day'];
					
					if (!$this->printer_friendly)
					{
						$str .= '</a>';
					}
					
					if ($cal['full'] == $this->today['full'])
					{
						$p->set_var('dayname',"<b>$str</b>");
					}
					else
					{
						$p->set_var('dayname',$str);
					}
				}
				else
				{
					$p->set_var('day_image','');
					$p->set_var('bgcolor2','#FEFEFE');
					$p->set_var('dayname','');
				}
				
				$p->parse('monthweek_day','mini_day',True);
			}
			$p->parse('display_monthweek','mini_week',True);
			$p->set_var('dayname','');
			$p->set_var('monthweek_day','');
		}
		
		$return_value = $p->finish($p->parse('out','mini_cal'));
		unset($p);
		return $return_value;
	}

	function overlap($starttime,$endtime,$participants,$owner=0,$id=0)
	{
		global $phpgw, $phpgw_info;

		$retval = Array();
		$ok = False;

		$starttime -= ((60 * 60) * $phpgw_info['user']['preferences']['common']['tz_offset']);
		$endtime -= ((60 * 60) * $phpgw_info['user']['preferences']['common']['tz_offset']);

		if($starttime == $endtime)
		{
//			$endtime = mktime($phpgw->common->show_date($starttime,'H'),$phpgw->common->show_date($starttime,'i'),0,$phpgw->common->show_date($starttime,'m'),$phpgw->common->show_date($starttime,'d') + 1,$phpgw->common->show_date($starttime,'Y')) - ((60*60) * $phpgw_info['user']['preferences']['common']['tz_offset']) - 1;
			$endtime = mktime(0,0,0,$phpgw->common->show_date($starttime,'m'),$phpgw->common->show_date($starttime,'d') + 1,$phpgw->common->show_date($starttime,'Y')) - ((60*60) * $phpgw_info['user']['preferences']['common']['tz_offset']) - 1;
		}

		$sql = 'AND ((('.$starttime.' <= calendar_entry.cal_datetime) AND ('.$endtime.' >= calendar_entry.cal_datetime) AND ('.$endtime.' <= calendar_entry.cal_edatetime)) '
				.  'OR (('.$starttime.' >= calendar_entry.cal_datetime) AND ('.$starttime.' < calendar_entry.cal_edatetime) AND ('.$endtime.' >= calendar_entry.cal_edatetime)) '
				.  'OR (('.$starttime.' <= calendar_entry.cal_datetime) AND ('.$endtime.' >= calendar_entry.cal_edatetime)) '
				.  'OR (('.$starttime.' >= calendar_entry.cal_datetime) AND ('.$endtime.' <= calendar_entry.cal_edatetime))) ';

		if(count($participants) > 0)
		{
			$p_g = '';
			if(count($participants))
			{
				for($i=0;$i<count($participants);$i++)
				{
					if($i > 0)
					{
						$p_g .= ' OR ';
					}
					$p_g .= 'calendar_entry_user.cal_login='.$participants[$i];
				}
			}
			if($p_g)
			{
				$sql .= ' AND (' . $p_g . ')';
			}
		}
      
		if($id)
		{
			$sql .= ' AND calendar_entry.cal_id <> '.$id;
		}

		$db2 = $phpgw->db;

		$events = $this->get_event_ids(False,$sql);
		if($events == False)
		{
			return false;
		}
		for($i=0;$i<count($events);$i++)
		{
			$db2->query('SELECT cal_type FROM calendar_entry_repeats WHERE cal_id='.$events[$i],__LINE__,__FILE__);
			if($db2->num_rows() == 0)
			{
				$retval[] = $events[$i];
				$ok = True;
			}
			else
			{
				$db2->next_record();
				if($db2->f('cal_type') <> 'monthlyByDay')
				{
					$retval[] = $events[$i];
					$ok = True;
				}
			}
		}
		if($ok == True)
		{
			return $retval;
		}
		else
		{
			return False;
		}
	}

	function large_month_header($month,$year,$display_name = False)
	{
		global $phpgw_info;

		$this->weekstarttime = $this->get_weekday_start($year,$month,1);

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
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

		for($i=0;$i<7;$i++)
		{
			$p->set_var('col_title',lang($this->days[$i]));
			$p->parse('column_header','column_title',True);
		}
		
		return $p->finish($p->parse('out','month_header'));
	}

	function display_week($startdate,$weekly,$cellcolor,$display_name = False,$owner=0,$monthstart=0,$monthend=0)
	{
		global $phpgw, $phpgw_info, $grants;

		if($owner == 0) { $owner= $phpgw_info['user']['account_id']; }

		$str = '';
		$gr_events = CreateObject('calendar.calendar_item');
		$lr_events = CreateObject('calendar.calendar_item');

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
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
		
		for ($j=0;$j<7;$j++)
		{
			$date = $this->gmtdate($startdate + ($j * 24 * 3600));
			$var = Array(
				'column_data'	=>	'',
				'extra'		=>	''
			);
			$p->set_var($var);
			
			if ($weekly || ($date['full'] >= $monthstart && $date['full'] <= $monthend))
			{
				if($weekly)
				{
					$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
				}
				
				if ($date['full'] == $this->today['full'])
				{
					$extra = ' bgcolor="'.$phpgw_info['theme']['cal_today'].'"';
				}
				else
				{
					$extra = ' bgcolor="'.$cellcolor.'"';
				}

				$new_event_link = '';
				if (!$this->printer_friendly)
				{
					if((!!($grants[$owner] & PHPGW_ACL_ADD) == True))
					{
						$new_event_link .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date_year.'&month='.$date['month'].'&day='.$date['day'].'&owner='.$owner).'">';
						$new_event_link .= '<img src="'.$this->image_dir.'/new.gif" width="10" height="10" alt="'.lang('New Entry').'" border="0" align="right">';
						$new_event_link .= '</a>';
					}
					$day_number = '<a href="'.$phpgw->link('/calendar/day.php','month='.$date['month'].'&day='.$date['day'].'&year='.$date['year'].'&owner='.$this->owner).'">'.$date['day'].'</a>';
				}
				else
				{
					$day_number = $date['day'];
				}

				$var = Array(
					'extra'		=>	$extra,
					'new_event_link'	=> $new_event_link,
					'day_number'		=>	$day_number
				);

				$p->set_var($var);
				
				$p->parse('column_data','month_day',True);

				$rep_events = $this->get_sorted_by_date($date['raw'],$owner);

				if ($this->sorted_events_matching)
				{
					$lr_events = CreateObject('calendar.calendar_item');
					$var = Array(
						'week_day_font_size'	=>	'2',
						'events'		=>	''
					);
					$p->set_var($var);
					for ($k=0;$k<$this->sorted_events_matching;$k++)
					{
						$lr_events = $rep_events[$k];
						$pict = 'circle.gif';
						if($lr_events->recur_type != RECUR_NONE)
						{
							$pict = 'rpt.gif';
						}
//						if(count($lr_events->participants) > 1)
//						{
//							$pict = 'multi_1.gif';
//						}
						
						$description = $this->is_private($lr_events,$owner,'description');

						if (($this->printer_friendly == False) &&
							(($description == 'private' && (!!($grants[$owner] & PHPGW_ACL_PRIVATE) == True)) || ($description != 'private'))  &&
							(!!($grants[$owner] & PHPGW_ACL_EDIT) == True))
						{
							$var = Array(
								'link_link'			=>	$phpgw->link('/calendar/view.php','id='.$lr_events->id.'&owner='.$owner),
								'lang_view'			=>	lang('View this entry'),
								'pic_image'			=>	$this->image_dir.'/'.$pict,
								'description'		=>	$description.(isset($phpgw_info['user']['preferences']['calendar']['display_status']) && $phpgw_info['user']['preferences']['calendar']['display_status'] == True?' ('.$lr_events->users_status.')':'')
							);
							$p->set_var($var);
							$p->parse('link_entry','link_pict');
						}
						else
						{
							$p->set_var('link_entry','');
						}

						if (intval($phpgw->common->show_date($lr_events->datetime,'Hi')))
						{
							if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
							{
								$format = 'h:i a';
							}
							else
							{
								$format = 'H:i';
							}
							
							if($lr_events->datetime < $date['raw'] && $lr_events->recur_type==RECUR_NONE)
							{
								$temp_time = $this->makegmttime(0,0,0,$date['month'],$date['day'],$date['year']);
								$start_time = $phpgw->common->show_date($temp_time['raw'],$format);
							}
							else
							{
								$start_time = $phpgw->common->show_date($lr_events->datetime,$format);
							}
                
							if($lr_events->edatetime > ($date['raw'] + 86400))
							{
								$temp_time = $this->makegmttime(23,59,59,$date['month'],$date['day'],$date['year']);
								$end_time = $phpgw->common->show_date($temp_time['raw'],$format);
							}
							else
							{
								$end_time = $phpgw->common->show_date($lr_events->edatetime,$format);
							}
							
						}
						else
						{
							$start_time = '';
							$end_time = '';
						}
						
						if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(PHPGW_ACL_PRIVATE)) || ($description != 'private'))  && $this->check_perms(PHPGW_ACL_EDIT))
						{
							$close_view_link = '</a>';
						}
						else
						{
							$close_view_link = '';
						}
						$var = Array(
							'start_time'		=>	$start_time,
							'end_time'			=>	$end_time,
							'close_view_link'	=> $close_link,
							'name'				=>	$this->is_private($lr_events,$owner,'name').(isset($phpgw_info['user']['preferences']['calendar']['display_status']) && $phpgw_info['user']['preferences']['calendar']['display_status'] == True?' ('.$lr_events->users_status.')':'')
						);
						$p->set_var($var);
						$p->parse('events','week_day_event',True);
					}
				}
				$p->parse('column_data','week_day_events',True);
				$p->set_var('events','');
				if (!$j || ($j && $date['full'] == $monthstart))
				{
					if(!$this->printer_friendly)
					{
						$str = '<a href="'.$phpgw->link('/calendar/week.php','date='.$date['full'].'&owner='.$this->owner).'">week ' .(int)((date('z',($startdate+(24*3600*4)))+7)/7).'</a>';
					}
					else
					{
						$str = 'week ' .(int)((date('z',($startdate+(24*3600*4)))+7)/7);
					}
					$var = Array(
						'week_day_font_size'	=>	'-2',
						'events'					=> $str
					);
					$p->set_var($var);
					$p->parse('column_data','week_day_events',True);
					$p->set_var('events','');
				}
			}
			$p->parse('column_header','month_column',True);
			$p->set_var('column_data','');
		}
		return $p->finish($p->parse('out','month_header'));
	}

	function display_large_week($day,$month,$year,$showyear,$owners=0)
	{
		global $phpgw, $phpgw_info;

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');

		$templates = Array(
			'month'			=>	'month.tpl',
			'month_filler'	=>	'month_filler.tpl',
			'month_header'	=>	'month_header.tpl'
		);
		$p->set_file($templates);
		
		$start = $this->get_weekday_start($year, $month, $day);

		$cellcolor = $phpgw_info['theme']['row_off'];

		$true_printer_friendly = $this->printer_friendly;

		if(is_array($owners))
		{
			$display_name = True;
			$counter = count($owners);
			$owners_array = $owners;
		}
		else
		{
			$display_name = False;
			$counter = 1;
			$owners_array[0] = $owners;
		}
		$p->set_var('month_filler_text',$this->large_month_header($month,$year,$display_name));
		$p->parse('row','month_filler',True);

		for($i=0;$i<$counter;$i++)
		{
			$this->repeated_events = Null;
			$owner = $owners_array[$i];
			$this->read_repeated_events($owner);
			$p->set_var('month_filler_text',$this->display_week($start,True,$cellcolor,$display_name,$owner));
			$p->parse('row','month_filler',True);
		}
		$this->printer_friendly = $true_printer_friendly;
		return $p->finish($p->parse('out','month'));
	}

	function display_large_month($month,$year,$showyear,$owner=0)
	{
		global $phpgw, $phpgw_info;

		if($owner == $phpgw_info['user']['account_id'])
		{
			$owner = 0;
		}
		
		$this->read_repeated_events($owner);

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');

		$templates = Array(
			'month'			=>	'month.tpl',
			'month_filler'	=>	'month_filler.tpl',
			'month_header'	=>	'month_header.tpl'
		);
		$p->set_file($templates);
		
		$p->set_var('month_filler_text',$this->large_month_header($month,$year,False));
		$p->parse('row','month_filler',True);

		$monthstart = intval(date('Ymd',mktime(0,0,0,$month    ,1,$year)));
		$monthend   = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

		$cellcolor = $phpgw_info['theme']['row_on'];

		for ($i=$this->weekstarttime;intval(date('Ymd',$i))<=$monthend;$i += (24 * 3600 * 7))
		{
			$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
			$p->set_var('month_filler_text',$this->display_week($i,False,$cellcolor,False,$owner,$monthstart,$monthend));
			$p->parse('row','month_filler',True);
		}
		return $p->finish($p->parse('out','month'));
	}

	function html_for_event_day_at_a_glance ($event,$first_hour,$last_hour,&$time)
	{
		global $phpgw, $phpgw_info;

		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			$format = 'h:i a';
		}
		else
		{
			$format = 'H:i';
		}

		$ind = intval($phpgw->common->show_date($event->datetime,'H'));

		if($ind<$first_hour || $ind>$last_hour)
		{
			$ind = 99;
		}

		if(!isset($time[$ind]) || !$time[$ind])
		{
			$time[$ind] = '';
		}

		$description = $this->is_private($event,$this->owner,'description');
		
		if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(PHPGW_ACL_PRIVATE)) || ($description != 'private')) && $this->check_perms(PHPGW_ACL_EDIT))
		{
			$time[$ind] .= '<a href="'.$phpgw->link('/calendar/view.php',
								  'id='.$event->id.'&owner='.$this->owner)
								. "\" onMouseOver=\"window.status='"
								. lang('View this entry')."'; return true;\">";
		}

		$time[$ind] .= '[' . $phpgw->common->show_date($event->datetime,$format);
		if ($event->datetime <> $event->edatetime)
		{
			$time[$ind] .= ' - ' . $phpgw->common->show_date($event->edatetime,$format);
			$end_t_h = intval($phpgw->common->show_date($event->edatetime,'H'));
			$end_t_m = intval($phpgw->common->show_date($event->edatetime,'i'));
			$this->rowspan = $end_t_h - $ind;
			
			if ($end_t_m > 0)
			{
				$this->rowspan += 1;
			}
			
			if(isset($this->rowspan_arr[$ind]))
			{
				$r = $this->rowspan_arr[$ind];
			}
			else
			{
				$r = 0;
			}
			
			if ($this->rowspan > $r && $this->rowspan > 1)
			{
				$this->rowspan_arr[$ind] = $this->rowspan;
			}
		}

		$time[$ind] .= '] ';
		$time[$ind] .= '<img src="'.$this->image_dir.'/circle.gif" border="0" alt="' . $description . '">';

		if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(PHPGW_ACL_PRIVATE)) || ($description != 'private')) && $this->check_perms(PHPGW_ACL_EDIT))
		{
			$time[$ind] .= '</a>';
		}
		
		if ($event->priority == 3)
		{
			$time[$ind] .= '<font color="CC0000">';
		}
		
		$time[$ind] .= $this->is_private($event,$this->owner,'name').(isset($phpgw_info['user']['preferences']['calendar']['display_status']) && $phpgw_info['user']['preferences']['calendar']['display_status'] == True?' ('.$event->users_status.')':'');

		if ($event->priority == 3)
		{
			$time[$ind] .= '</font>';
		}
		
		$time[$ind] .= '<br>';
	}

	function print_day_at_a_glance($date,$owner=0)
	{
		global $phpgw, $phpgw_info;

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');

		$templates = Array(
			'day_cal'			=>	'day_cal.tpl',
			'mini_week'			=> 'mini_week.tpl',
			'day_row_event'	=> 'day_row_event.tpl',
			'day_row_time'		=>	'day_row_time.tpl'
		);
      $p->set_file($templates);
      
		if (! $phpgw_info['user']['preferences']['calendar']['workdaystarts'] &&
			 ! $phpgw_info['user']['preferences']['calendar']['workdayends'])
		{

			$phpgw_info['user']['preferences']['calendar']['workdaystarts'] = 8;
			$phpgw_info['user']['preferences']['calendar']['workdayends']   = 16;
		}

		$first_hour = (int)$phpgw_info['user']['preferences']['calendar']['workdaystarts'] + 1;
		$last_hour  = (int)$phpgw_info['user']['preferences']['calendar']['workdayends'] + 1;

		$events = Array(
			CreateObject('calendar.calendar_item')
		);

		$time = Array();

		$events = $this->get_sorted_by_date($date['raw']);

		if(!$events)
		{
      }
      else
      {
			$event = CreateObject('calendar.calendar_item');
			for($i=0;$i<count($events);$i++)
			{
				$event = $events[$i];
				if($event)
				{
					$this->html_for_event_day_at_a_glance($event,$first_hour,$last_hour,$time);
				}
			}
		}

		// squish events that use the same cell into the same cell.
		// For example, an event from 8:00-9:15 and another from 9:30-9:45 both
		// want to show up in the 8:00-9:59 cell.
		$this->rowspan = 0;
		$this->last_row = -1;
		for ($i=0;$i<24;$i++)
		{
			if(isset($this->rowspan_arr[$i]))
			{
				$r = $this->rowspan_arr[$i];
			}
			else
			{
				$r = 0;
			}
			
			if(isset($time[$i]))
			{
				$h = $time[$i];
			}
			else
			{
				$h = '';
			}
			
			if ($this->rowspan > 1)
			{
				if (strlen($h))
				{
					$time[$this->last_row] .= $time[$i];
					$time[$i] = '';
					$this->rowspan_arr[$i] = 0;
				}
				$this->rowspan--;
			}
			elseif ($r > 1)
			{
				$this->rowspan = $this->rowspan_arr[$i];
				$this->last_row = $i;
			}
		}
		$var = Array(
			'time_bgcolor'		=>	$phpgw_info['theme']['cal_dayview'],
			'bg_time_image'	=>	$this->phpgwapi_template_dir.'/navbar_filler.jpg',
			'font_color'		=>	$phpgw_info['theme']['bg_text'],
			'font'				=>	$phpgw_info['theme']['font']
		);

		$p->set_var($var);
		
		if (isset($time[99]) && strlen($time[99]) > 0)
		{
			$var = Array(
				'event'		=>	$time[99],
				'bgcolor'	=>	$phpgw->nextmatchs->alternate_row_color()
			);
			$p->set_var($var);
			$p->parse('monthweek_day','day_row_event',False);

			$var = Array(
				'open_link'		=>	'',
				'time'			=>	'&nbsp;',
				'close_link'	=>	''
			);
			$p->set_var($var);
			
			$p->parse('monthweek_day','day_row_time',True);
			$p->parse('row','mini_week',True);
			$p->set_var('monthweek_day','');
		}
		$this->rowspan = 0;
		$times = 0;
		for ($i=$first_hour;$i<=$last_hour;$i++)
		{
			if(isset($time[$i]))
			{
				$h = $time[$i];
			}
			else
			{
				$h = '';
			}
			
			$dtime = $this->build_time_for_display($i * 10000);
			$p->set_var('extras','');
			$p->set_var('event','&nbsp');
			if ($this->rowspan > 1)
			{
				// this might mean there's an overlap, or it could mean one event
				// ends at 11:15 and another starts at 11:30.
				if (strlen($h))
				{
					$p->set_var('event',$time[$i]);
					$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
					$p->parse('monthweek_day','day_row_event',False);
				}
				$this->rowspan--;
			}
			else
			{
				if (!strlen($h))
				{
					$p->set_var('event','&nbsp;');
					$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
					$p->parse('monthweek_day','day_row_event',False);
				}
				else
				{
					$this->rowspan = isset($this->rowspan_arr[$i])?$this->rowspan_arr[$i]:0;
					if ($this->rowspan > 1)
					{
						$p->set_var('extras',' rowspan="'.$this->rowspan.'"');
						$p->set_var('event',$time[$i]);
						$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
						$p->parse('monthweek_day','day_row_event',False);
					}
					else
					{
						$p->set_var('event',$time[$i]);
						$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
						$p->parse('monthweek_day','day_row_event',False);
					}
				}
			}
			
			$open_link = ' - ';
			$close_link = '';
			
			if(($this->printer_friendly == False) && ($this->check_perms(PHPGW_ACL_EDIT) == True))
			{
				$open_link .= '<a href="'.$phpgw->link('/calendar/edit_entry.php',
								  'year='.$date['year'].'&month='.$date['month']
								. '&day='.$date['day']
								. '&hour='.substr($dtime,0,strpos($dtime,':'))
								. '&minute='.substr($dtime,strpos($dtime,':')+1,2).'&owner='.$this->owner).'">';
								
				$close_link = '</a>';
			}

			$var = Array(
				'open_link'		=>	$open_link,
				'time'			=>	(intval(substr($dtime,0,strpos($dtime,':'))) < 10 ? '0'.$dtime : $dtime),
				'close_link'	=>	$close_link
			);
			
			$p->set_var($var);
			
			$p->parse('monthweek_day','day_row_time',True);
			$p->parse('row','mini_week',True);
			$p->set_var('monthweek_day','');
		}	// end for
		return $p->finish($p->parse('out','day_cal'));
	}	// end function

	function view_add_day(&$repeat_days,$day)
	{
		if($repeat_days)
		{
			$repeat_days .= ', ';
		}
		$repeat_days .= $day;
	}

	function view_event($event)
	{
		global $phpgw, $phpgw_info;

		$tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));

		$pri = Array(
  			1	=> lang('Low'),
  			2	=> lang('Normal'),
	  		3	=> lang('High')
		);

		reset($event->participants);
		$participating = False;
		for($j=0;$j<count($event->participants);$j++)
		{
			if($event->participants[$j] == $owner)
			{
				$participating = True;
			}
		}
  
		$p = CreateObject('phpgwapi.Template',$phpgw->calendar->template_dir);

		$templates = Array(
  			'view_begin'	=> 'view.tpl',
  			'list'			=> 'list.tpl',
	  		'view_end'		=> 'view.tpl',
  			'form_button'	=> 'form_button_script.tpl'
		);
		$p->set_file($templates);

		$var = Array(
			'bg_text'	=>	$phpgw_info['theme']['bg_text'],
			'name'	=>	$event->name
		);
		$p->set_var($var);
		$p->parse('out','view_begin');

		// Some browser add a \n when its entered in the database. Not a big deal
		// this will be printed even though its not needed.
		if (nl2br($event->description))
		{
			$var = Array(
				'field'	=>	lang('Description'),
				'data'	=>	nl2br($event->description)
			);
			$p->set_var($var);
			$p->parse('output','list',True);
		}

		$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $tz_offset;
		$var = Array(
			'field'	=>	lang('Start Date/Time'),
			'data'	=>	$phpgw->common->show_date($start)
		);
		$p->set_var($var);
		$p->parse('output','list',True);
	
		$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $tz_offset;
		$var = Array(
			'field'	=>	lang('End Date/Time'),
			'data'	=>	$phpgw->common->show_date($end)
		);
		$p->set_var($var);
		$p->parse('output','list',True);

		$var = Array(
			'field'	=>	lang('Priority'),
			'data'	=>	$pri[$event->priority]
		);
		$p->set_var($var);
		$p->parse('output','list',True);

		$participate = False;
		for($i=0;$i<count($event->participants);$i++)
		{
			if($event->participants[$i] == $phpgw_info['user']['account_id'])
			{
				$participate = True;
			}
		}
		$var = Array(
			'field'	=>	lang('Created By'),
			'data'	=>	$phpgw->common->grab_owner_name($event->owner)
		);
		$p->set_var($var);
		$p->parse('output','list',True);
	
		$var = Array(
			'field'	=>	lang('Updated'),
			'data'	=>	$phpgw->common->show_date($event->mdatetime)
		);
		$p->set_var($var);
		$p->parse('output','list',True);

		if($event->groups[0])
		{
			$cal_grps = '';
			for($i=0;$i<count($event->groups);$i++)
			{
				if($i>0)
				{
					$cal_grps .= '<br>';
				}
				$cal_grps .= $phpgw->accounts->id2name($event->groups[$i]);
			}
	
			$var = Array(
				'field'	=>	lang('Groups'),
				'data'	=>	$cal_grps
			);
			$p->set_var($var);
			$p->parse('output','list',True);
		}

		$str = '';
		for($i=0;$i<count($event->participants);$i++)
		{
			if($i)
			{
				$str .= '<br>';
			}

			$status = $this->get_long_status($event->status[$i]);
			
			$str .= $phpgw->common->grab_owner_name($event->participants[$i]).' (';
			
			if($event->participants[$i] == $this->owner && $this->check_perms(PHPGW_ACL_EDIT) == True)
			{
				$str .= '<a href="'.$phpgw->link('/calendar/edit_status.php','owner='.$this->owner.'&id='.$event->id).'">'.$status.'</a>';
			}
			else
			{
				$str .= $status;
			}
			$str .= ')';
		}
		$var = Array(
			'field'	=>	lang('Participants'),
			'data'	=>	$str
		);
		$p->set_var($var);
		$p->parse('output','list',True);

// Repeated Events
		$str = $event->rpt_type;
		if($event->recur_type <> RECUR_NONE || ($event->recur_enddate->mday != 0 && $event->recur_enddate->month != 0 && $event->recur_enddate->year != 0))
		{
			$str .= ' (';
			$recur_end = mktime(0,0,0,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year);
			if($recur_end != 0)
			{
				$str .= lang('ends').': '.$phpgw->common->show_date($recur_end,'l, F d, Y').' ';
			}
			if($event->recur_type == RECUR_WEEKLY || $event->recur_type == RECUR_DAILY)
			{
				$repeat_days = '';
				if ($event->recur_data & M_SUNDAY)
				{
					add_day($repeat_days,lang('Sunday '));
				}
				if ($event->recur_data & M_MONDAY)
				{
					add_day($repeat_days,lang('Monday '));
				}
				if ($event->recur_data & M_TUESDAY)
				{
					add_day($repeat_days,lang('Tuesay '));
				}
				if ($event->recur_data & M_WEDNESDAY)
				{
					add_day($repeat_days,lang('Wednesday '));
				}
				if ($event->recur_data & M_THURSDAY)
				{
					add_day($repeat_days,lang('Thursday '));
				}
				if ($event->recur_data & M_FRIDAY)
				{
					add_day($repeat_days,lang('Friday '));
				}
				if ($event->recur_data & M_SATURDAY)
				{
					add_day($repeat_days,lang('Saturday '));
				}
				$str .= lang('days repeated').': '.$repeat_days;
			}
			if($event->recur_interval)
			{
				$str .= lang('frequency').' '.$event->recur_interval;
			}
			$str .= ')';

			$var = Array(
				'field'	=>	lang('Repitition'),
				'data'	=>	$str
			);
			$p->set_var($var);
			$p->parse('output','list',True);
		}

		return $p->finish($p->parse('out','view_end'));
	}

	function get_response()
	{
		global $phpgw;
		
		$str = '<table width="100%" cols="4"><tr align="center">';

		$p = CreateObject('phpgwapi.Template',$this->template_dir);

		$templates = Array(
  			'form_button'	=> 'form_button_script.tpl'
		);
		$p->set_file($templates);
	
		$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$this->event->id.'&action='.ACCEPTED));
		$p->set_var('action_text_button','  '.lang('Accept').'  ');
		$p->set_var('action_confirm_button','');
		$str .= '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

		$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$this->event->id.'&action='.REJECTED));
		$p->set_var('action_text_button','  '.lang('Reject').'  ');
		$p->set_var('action_confirm_button','');
		$str .= '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

		$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$this->event->id.'&action='.TENTATIVE));
		$p->set_var('action_text_button','  '.lang('Tentative').'  ');
		$p->set_var('action_confirm_button','');
		$str .= '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

		$p->set_var('action_url_button',$phpgw->link('/calendar/action.php','id='.$this->event->id.'&action='.NO_RESPONSE));
		$p->set_var('action_text_button','  '.lang('No Response').'  ');
		$p->set_var('action_confirm_button','');
		$str .= '<td>'.$p->finish($p->parse('out','form_button')).'</td>'."\n";

		$str .= '</tr></table>';

		return $str;
	}

	function timematrix($date,$starttime,$endtime,$participants)
	{
		global $phpgw, $phpgw_info;

		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			$time_format .= 'h:i:s a';
		}
		else
		{
			$time_format .= 'H:i:s';
		}

		if(!isset($phpgw_info['user']['preferences']['calendar']['interval']) ||
			!$phpgw_info['user']['preferences']['calendar']['interval'])
		{
			$phpgw_info['user']['preferences']['calendar']['interval'] = 15;
		}
		$increment = $phpgw_info['user']['preferences']['calendar']['interval'];
		$interval = (int)(60 / $increment);

		$str = '<center>'.$phpgw->common->show_date($date['raw'],'l, F d, Y').'<br>';
		$str .= '<table width="85%" border="0" cellspacing="0" cellpadding="0" cols="'.((24 * $interval) + 1).'">';
		$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$this->image_dir.'/pix.gif"></td></tr>';
		$str .= '<tr><td width="15%">Participant</td>';
		for($i=0;$i<24;$i++)
		{
			for($j=0;$j<$interval;$j++)
			{
				switch($j)
				{
					case 0:
						if($interval == 4)
						{
							$k = ($i<=9?'0':substr($i,0,1));
						}
						$str .= '<td align="right" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'">';
						$str .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j<=9?'0':'').($increment * $j)."'; return true;\">";
						$str .= $k.'</a></font></td>';
						break;
					case 1:
						if($interval == 4)
						{
							$k = ($i<=9?substr($i,0,1):substr($i,1,2));
						}
						$str .= '<td align="right" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'">';
						$str .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j)."'; return true;\">";
						$str .= $k.'</a></font></td>';
						break;
					default:
						$str .= '<td align="left" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'">';
						$str .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j)."'; return true;\">";
						$str .= '&nbsp</a></font></td>';
						break;
				}
			}
		}
		$str .= '</tr>';
		$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$this->image_dir.'/pix.gif"></td></tr>';
		if(!$endtime)
		{
			$endtime = $starttime;
		}
		for($i=0;$i<count($participants);$i++)
		{
			$this->read_repeated_events($participants[$i]);
			$str .= '<tr>';
			$str .= '<td width="15%">'.$phpgw->common->grab_owner_name($participants[$i]).'</td>';
			$events = $this->get_sorted_by_date($date['raw'],$participants[$i]);
			if($this->sorted_events_matching == False)
			{
				for($j=0;$j<24;$j++)
				{
					for($k=0;$k<$interval;$k++)
					{
						$str .= '<td height="1" align="left" bgcolor="'.$phpgw_info['theme']['bg_color'].'" color="#999999">&nbsp;</td>';
					}
				}
			}
			else
			{
				for($h=0;$h<24;$h++)
				{
					for($m=0;$m<$interval;$m++)
					{
						$index = (($h * 10000) + (($m * $increment) * 100));
						$time_slice[$index]['marker'] = '&nbsp';
						$time_slice[$index]['color'] = $phpgw_info['theme']['bg_color'];
						$time_slice[$index]['description'] = '';
					}
				}
				for($k=0;$k<$this->sorted_events_matching;$k++)
				{
					$event = $events[$k];
					$eventstart = $this->localdates($event->datetime);
					$eventend = $this->localdates($event->edatetime);
					$start = ($eventstart['hour'] * 10000) + ($eventstart['minute'] * 100);
					$starttemp = $this->splittime("$start",False);
					$subminute = 0;
					for($m=0;$m<$interval;$m++)
					{
						$minutes = $increment * $m;
						if(intval($starttemp['minute']) > $minutes && intval($starttemp['minute']) < ($minutes + $increment))
						{
							$subminute = ($starttemp['minute'] - $minutes) * 100;
						}
					}
					$start -= $subminute;
					$end =  ($eventend['hour'] * 10000) + ($eventend['minute'] * 100);
					$endtemp = $this->splittime("$end",False);
					$addminute = 0;
					for($m=0;$m<$interval;$m++)
					{
						$minutes = ($increment * $m);
						if($endtemp['minute'] < ($minutes + $increment) && $endtemp['minute'] > $minutes)
						{
							$addminute = ($minutes + $increment - $endtemp['minute']) * 100;
						}
					}
					$end += $addminute;
					$starttemp = $this->splittime("$start",False);
					$endtemp = $this->splittime("$end",False);
// Do not display All-Day events in this free/busy time
					if((($starttemp['hour'] == 0) && ($starttemp['minute'] == 0)) && (($endtemp['hour'] == 23) && ($endtemp['minute'] == 59)))
					{
					}
					else
					{
						for($h=$starttemp['hour'];$h<=$endtemp['hour'];$h++)
						{
							$startminute = 0;
							$endminute = $interval;
							$hour = $h * 10000;
							if($h == intval($starttemp['hour']))
							{
								$startminute = ($starttemp['minute'] / $increment);
							}
							if($h == intval($endtemp['hour']))
							{
								$endminute = ($endtemp['minute'] / $increment);
							}
							for($m=$startminute;$m<=$endminute;$m++)
							{
								$index = ($hour + (($m * $increment) * 100));
								$time_slice[$index]['marker'] = '-';
								$time_slice[$index]['color'] = $phpgw_info['theme']['bg01'];
								$time_display = $phpgw->common->show_date($eventstart['raw'],$time_format).'-'.$phpgw->common->show_date($eventend['raw'],$time_format);
								$time_slice[$index]['description'] = '('.$time_display.') '.$this->is_private($event,$participants[$i],'title');
								if(isset($phpgw_info['user']['preferences']['calendar']['display_status']) && $phpgw_info['user']['preferences']['calendar']['display_status'] == True)
								{
									$time_slice[$index]['description'] .= ' ('.$event->users_status.')';
								}
							}
						}
					}
				}
				for($h=0;$h<24;$h++)
				{
					$hour = $h * 10000;
					for($m=0;$m<$interval;$m++)
					{
						$index = ($hour + (($m * $increment) * 100));
						$str .= '<td height="1" align="left" bgcolor="'.$time_slice[$index]['color']."\" color=\"#999999\"  onMouseOver=\"window.status='".$time_slice[$index]['description']."'; return true;\">".$time_slice[$index]['marker'].'</td>';
					}
				}
			}
			$str .= '</tr>';
			$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="#999999"><img src="'.$this->image_dir.'/pix.gif"></td></tr>';
		}
		$str .= '</table></center>';
		return $str;
	}      
}
?>
