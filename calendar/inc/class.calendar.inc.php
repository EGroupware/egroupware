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
if(isset($phpgw_info['server']['calendar_type']) &&
	$phpgw_info['server']['calendar_type'] == 'mcal' &&
	extension_loaded('mcal') == False)
{
	$phpgw_info['server']['calendar_type'] = 'sql';
}
// The following line can be removed when vCalendar is implemented....
$phpgw_info['server']['calendar_type'] = 'sql';
//CreateObject('calendar.vCalendar');
$temp = CreateObject('calendar.calendar__');
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
	var $repeating_events = Array();
	var $repeated_events = Array();
	var $repeating_event_matches = 0;
	var $sorted_events_matching = 0;
	var $end_repeat_day = 0;
	var $weekstarttime;
	
	var $tz_offset;

	var $tempyear;
	var $tempmonth;
	var $tempday;

	var $users_timeformat;

	var $rowspan_arr = Array();
	var $rowspan;

	var $holidays;
	var $br;

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
		$this->phpgwapi_template_dir = PHPGW_IMAGES_DIR;
		$this->image_dir = $phpgw->common->get_image_path('calendar');

		$this->calendar__();
		
		$this->today = $this->datetime->localdates(time());

		$this->open('INBOX',intval($this->owner));
		$this->set_filter();
		
		if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
		{
			$this->users_timeformat = 'h:i a';
		}
		else
		{
			$this->users_timeformat = 'H:i';
		}
		$this->holidays = CreateObject('calendar.calendar_holiday',$this->owner);
		$browser = CreateObject('phpgwapi.browser');
		$this->br = $browser->br;
	}

// Generic functions that are derived from mcal functions.
// NOT PART OF THE ORIGINAL MCAL SPECS.

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

	function get_fullname($accountid)
	{
		global $phpgw;

		$account_id = get_account_id($accountid);
		if($phpgw->accounts->exists($account_id) == False)
		{
			return False;
		}
		$db = $phpgw->db;
		$db->query('SELECT account_lid,account_lastname,account_firstname FROM phpgw_accounts WHERE account_id='.$account_id,__LINE__,__FILE__);
		if($db->num_rows())
		{
			$db->next_record();
			$fullname = $db->f('account_lid');
			$lname = $db->f('account_lastname');
			$fname = $db->f('account_firstname');
			if($lname && $fname)
			{
				$fullname = $lname.', '.$fname;
			}
			return $fullname;
		}
		else
		{
			return False;
		}
	}

	function get_long_status($status_short)
	{
		switch ($status_short)
		{
			case 'A':
				$status = lang('Accepted');
				break;
			case 'R':
				$status = lang('Rejected');
				break;
			case 'T':
				$status = lang('Tentative');
				break;
			case 'U':
				$status = lang('No Response');
				break;
		}
		return $status;
	}

	function display_status($user_status)
	{
		global $phpgw_info;
		
		if(isset($phpgw_info['user']['preferences']['calendar']['display_status']) && $phpgw_info['user']['preferences']['calendar']['display_status'] == True)
		{
			return ' ('.$user_status.')';
		}
		else
		{
			return '';
		}
	}

	function link_to_entry($event,$month,$day,$year)
	{
		global $phpgw, $phpgw_info, $grants;

		$str = '';
		$is_private = $this->is_private($event,$this->owner);
		$editable = ((!$this->printer_friendly) && (($is_private && ($grants[$this->owner] & PHPGW_ACL_PRIVATE)) || !$is_private));
		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');
		$templates = Array(
			'link_picture'		=>	'link_pict.tpl'
		);
		$p->set_file($templates);
		$p->set_block('link_picture','link_pict','link_pict');
		$p->set_block('link_picture','pict','pict');
		$p->set_block('link_picture','link_open','link_open');
		$p->set_block('link_picture','link_close','link_close');
		$p->set_block('link_picture','link_text','link_text');
		$description = $this->get_short_field($event,$is_private,'description');

		$starttime = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->datetime->tz_offset;
		$endtime = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->datetime->tz_offset;
		$rawdate = mktime(0,0,0,$month,$day,$year);
		$rawdate_offset = $rawdate - $this->datetime->tz_offset;
		$nextday = mktime(0,0,0,$month,$day + 1,$year) - $this->datetime->tz_offset;
		if (intval($phpgw->common->show_date($starttime,'Hi')) && $starttime == $endtime)
		{
			$time = $phpgw->common->show_date($starttime,'H:i');
		}
		elseif ($starttime <= $rawdate_offset && $endtime >= $nextday - 60)
		{
			$time = '[ '.lang('All Day').' ]';
		}
		elseif (intval($phpgw->common->show_date($starttime,'Hi')) || $starttime != $endtime)
		{
			if($starttime < $rawdate_offset && $event->recur_type==MCAL_RECUR_NONE)
			{
				$start_time = $phpgw->common->show_date($rawdate_offset,$this->users_timeformat);
			}
			else
			{
				$start_time = $phpgw->common->show_date($starttime,$this->users_timeformat);
			}

			if($endtime >= ($rawdate_offset + 86400))
			{
				$end_time = $phpgw->common->show_date(mktime(23,59,59,$month,$day,$year) - $this->datetime->tz_offset,$this->users_timeformat);
			}
			else
			{
				$end_time = $phpgw->common->show_date($endtime,$this->users_timeformat);
			}
			$time = $start_time.'-'.$end_time;
		}
		else
		{
			$time = '';
		}
		$text = '<font size="-2" face="'.$phpgw_info['theme']['font'].'"><nobr>'.$time.'</nobr>&nbsp;'.$this->get_short_field($event,$is_private,'title');
		if(!$is_private)
		{
			$text .= $this->display_status($event->users_status);
		}
		$text .= '</font>'.$this->br;

		
		if ($editable)
		{
			$p->set_var('link_link',$phpgw->link('/calendar/view.php','id='.$event->id.'&owner='.$this->owner));
			$p->set_var('lang_view',lang('View this entry'));
			$p->parse('picture','link_open',True);
			
			if($event->priority == 3)
			{
				$picture[] = Array(
					'pict'	=> $phpgw->common->image('calendar','high.gif'),
					'width'	=> 8,
					'height'	=> 17
				);
			}
			if($event->recur_type == MCAL_RECUR_NONE)
			{
				$picture[] = Array(
					'pict'	=> $phpgw->common->image('calendar','circle.gif'),
					'width'	=> 5,
					'height'	=> 7
				);
			}
			else
			{
				$picture[] = Array(
					'pict'	=> $phpgw->common->image('calendar','recur.gif'),
					'width'	=> 12,
					'height'	=> 12
				);
			}
			if(count($event->participants) > 1)
			{
				$picture[] = Array(
					'pict'	=> $phpgw->common->image('calendar','multi_3.gif'),
					'width'	=> 14,
					'height'	=> 14
				);
			}
			if($event->public == 0)
			{
				$picture[] = Array(
					'pict'	=> $phpgw->common->image('calendar','private.gif'),
					'width'	=> 13,
					'height'	=> 13
				);
			}
			
			for($i=0;$i<count($picture);$i++)
			{
				$var = Array(
					'pic_image'	=> $picture[$i]['pict'],
					'width'		=> $picture[$i]['width'],
					'height'		=> $picture[$i]['height'],
					'description'	=> $description
				);
				$p->set_var($var);
				$p->parse('picture','pict',True);
			}
		}
		if ($text)
		{
			$p->set_var('text',$text);
			$p->parse('picture','link_text',True);
		}
		
		if ($editable)
		{
			$p->parse('picture','link_close',True);
		}
		$str = $p->fp('out','link_pict');
		unset($p);
		return $str;
	}

	function is_private($event,$owner,$field='')
	{
		global $phpgw, $phpgw_info, $grants;

		if($owner == 0) { $owner = $phpgw_info['user']['account_id']; }
		if ($owner == $phpgw_info['user']['account_id'] || ($grants[$owner] & PHPGW_ACL_PRIVATE) || ($event->public == 1))
		{
			$is_private  = False;
		}
		elseif($event->public == 0)
		{
			$is_private = True;
		}
		elseif($event->public == 2)
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
		else
		{
			$is_private  = False;
		}

		return $is_private;
	}
	
	function get_short_field($event,$is_private=True,$field='')
	{
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

	function change_owner($account_id,$new_owner)
	{
		if($phpgw_info['server']['calendar_type'] == 'sql')
		{
			$this->stream->query('UPDATE phpgw_cal SET owner='.$new_owner.' WHERE owner='.$account_id,__LINE__,__FILE__);
			$this->stream->query('UPDATE phpgw_cal_user SET cal_login='.$new_owner.' WHERE cal_login='.$account_id);
		}
	}

	function read_repeated_events($owner=0)
	{
		global $phpgw, $phpgw_info;

		$this->set_filter();
		$owner = $owner == 0?$phpgw_info['user']['account_id']:$owner;
		$sql = "AND (phpgw_cal.cal_type='M') "
			. 'AND (phpgw_cal_user.cal_login='.$owner.' '
			. 'AND ((phpgw_cal_repeats.recur_enddate >= '.$this->end_repeat_day.') OR (phpgw_cal_repeats.recur_enddate=0))';

// Private
		if(strpos($this->filter,'private'))
		{
			$sql .= " AND phpgw_cal.is_public=0";
		}
		
		$sql .= ') ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

		$events = $this->get_event_ids(True,$sql);

		if($events == False)
		{
			$this->repeated_events = Null;
			$this->repeating_events = False;
		}
		else
		{
			$repetitive_events = Array();
			$this->repeated_events = $events;
			$c_events = count($events);
			if($c_events > 0)
			{
				for($i=0;$i<$c_events;$i++)
				{
					$this->repeating_events[] = $this->fetch_event($events[$i]);
				}
//				$this->repeating_events = $repititive_events;
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
		@reset($this->repeated_events);
		$repeated = $this->repeated_events;
		$r_events = count($repeated);
		for ($i=0;$i<$r_events;$i++)
		{
			$rep_events = $this->repeating_events[$i];
			$id = $rep_events->id;
			$event_beg_day = mktime(0,0,0,$rep_events->start->month,$rep_events->start->mday,$rep_events->start->year);
			if($rep_events->recur_enddate->month != 0 && $rep_events->recur_enddate->mday != 0 && $rep_events->recur_enddate->year != 0)
			{
				$event_recur_time = mktime($rep_events->recur_enddate->hour,$rep_events->recur_enddate->min,$rep_events->recur_enddate->sec,$rep_events->recur_enddate->month,$rep_events->recur_enddate->mday,$rep_events->recur_enddate->year);
			}
			else
			{
				$event_recur_time = mktime(0,0,0,1,1,2030);
			}
			$end_recur_date = date('Ymd',$event_recur_time);
			$full_event_date = date('Ymd',$event_beg_day);
			
			// only repeat after the beginning, and if there is an rpt_end before the end date
//			if ((($rep_events->recur_enddate->month != 0 && $rep_events->recur_enddate->mday != 0 && $rep_events->recur_enddate->year != 0) &&
//				($search_date_full > $end_recur_date)) || ($search_date_full < $full_event_date))
			if (($search_date_full > $end_recur_date) || ($search_date_full < $full_event_date))
			{
				continue;
			}

			if ($search_date_full == $full_event_date)
			{
				$link[$this->repeating_event_matches] = $id;
				$this->repeating_event_matches++;
			}
			else
			{				
				$freq = $rep_events->recur_interval;
				$type = $rep_events->recur_type;
				switch($type)
				{
					case MCAL_RECUR_DAILY:
						if (floor(($search_beg_day - $event_beg_day)/86400) % $freq)
						{
							continue;
						}
						else
						{
							
							$link[$this->repeating_event_matches] = $id;
							$this->repeating_event_matches++;
						}
						break;
					case MCAL_RECUR_WEEKLY:
						if (floor(($search_beg_day - $event_beg_day)/604800) % $freq)
						{
							continue;
						}
				
						$check = 0;
						switch($search_date_dow)
						{
							case 0:
								$check = MCAL_SUNDAY;
								break;
							case 1:
								$check = MCAL_MONDAY;
								break;
							case 2:
								$check = MCAL_TUESDAY;
								break;
							case 3:
								$check = MCAL_WEDNESDAY;
								break;
							case 4:
								$check = MCAL_THURSDAY;
								break;
							case 5:
								$check = MCAL_FRIDAY;
								break;
							case 6:
								$check = MCAL_SATURDAY;
								break;
						}
						if ($rep_events->recur_data & $check)
						{
							$link[$this->repeating_event_matches] = $id;
							$this->repeating_event_matches++;
						}
						break;
					case MCAL_RECUR_MONTHLY_WDAY:
						if ((($search_date_year - $rep_events->start->year) * 12 + $search_date_month - $rep_events->start->month) % $freq)
						{
							continue;
						}
	  
						if (($this->datetime->day_of_week($rep_events->start->year,$rep_events->start->month,$rep_events->start->mday) == $this->datetime->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
							(ceil($rep_events->start->mday/7) == ceil($search_date_day/7)))
						{
							$link[$this->repeating_event_matches] = $id;
							$this->repeating_event_matches++;
						}
						break;
					case MCAL_RECUR_MONTHLY_MDAY:
						if ((($search_date_year - $rep_events->start->year) * 12 + $search_date_month - $rep_events->start->month) % $freq)
						{
							continue;
						}
				
						if ($search_date_day == $rep_events->start->mday)
						{
							$link[$this->repeating_event_matches] = $id;
							$this->repeating_event_matches++;
						}
						break;
					case MCAL_RECUR_YEARLY:
						if (($search_date_year - $rep_events->start->year) % $freq)
						{
							continue;
						}
				
						if (date('dm',$datetime) == date('dm',$event_beg_day))
						{
							$link[$this->repeating_event_matches] = $id;
							$this->repeating_event_matches++;
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
		$repeating_events_matched = $this->check_repeating_entries($datetime - $this->datetime->tz_offset);
		$eod = $datetime + 86399;
		$sql = "AND (phpgw_cal.cal_type != 'M') "
				. 'AND ((phpgw_cal.datetime >= '.$datetime.' AND phpgw_cal.datetime <= '.$eod.') '
				.   'OR (phpgw_cal.datetime <= '.$datetime.' AND phpgw_cal.edatetime >= '.$eod.') '
				.   'OR (phpgw_cal.edatetime >= '.$datetime.' AND phpgw_cal.edatetime <= '.$eod.')) '
				. 'AND (phpgw_cal_user.cal_login='.$owner;

// Private
		if(strpos($this->filter,'private'))
		{
			$sql .= " AND phpgw_cal.is_public=0";
		}
		
		$sql .= ') ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

//		$event = Array();

		$event = $this->get_event_ids(False,$sql);

		if($this->repeating_event_matches == 0 && $event == False)
		{
			return False;
		}
		elseif($event == False)
		{
			unset($event);
		}

		if($this->repeating_event_matches != 0)
		{
			reset($repeating_events_matched);
			if(count($repeating_events_matched))
			{
				while(list($key,$value) = each($repeating_events_matched))
				{
					$event[] = intval($value);
				}
			}
		}

		$this->sorted_events_matching = count($event);

		if($this->sorted_events_matching == 0)
		{
			return False;
		}
		else
		{
//			$events = Array();
			for($i=0;$i<$this->sorted_events_matching;$i++)
			{
				$events[] = $this->fetch_event(intval($event[$i]));
			}

			if($this->sorted_events_matching == 1)
			{
				return $events;
			}
		}

//		$temp = CreateObject('calendar.calendar_item');
//		$inner = CreateObject('calendar.calendar_item');
//		$outer = CreateObject('calendar.calendar_item');
		
		for($outer_loop=0;$outer_loop<($this->sorted_events_matching - 1);$outer_loop++)
		{
			$outer = $events[$outer_loop];
			$outer_stime = mktime($outer->start->hour,$outer->start->min,$outer->start->sec,$outer->start->month,$outer->start->mday,$outer->start->year) - $this->datetime->tz_offset;
			$outer_etime = mktime($outer->end->hour,$outer->end->min,$outer->end->sec,$outer->end->month,$outer->end->mday,$outer->end->year) - $this->datetime->tz_offset;
			$ostime = $phpgw->common->show_date($outer_stime,'Hi');
			$oetime = $phpgw->common->show_date($outer_etime,'Hi');

			if($outer->recur_type == MCAL_RECUR_NONE)
			{
				if($outer_stime < $datetime)
				{
					$ostime = 0;
				}
			
				if($outer_etime > $eod)
				{
					$oetime = 2359;
				}
			}

			for($inner_loop=$outer_loop;$inner_loop<$this->sorted_events_matching;$inner_loop++)
			{
				$inner = $events[$inner_loop];
				$inner_stime = mktime($inner->start->hour,$inner->start->min,$inner->start->sec,$inner->start->month,$inner->start->mday,$inner->start->year) - $this->datetime->tz_offset;
				$inner_etime = mktime($inner->end->hour,$inner->end->min,$inner->end->sec,$inner->end->month,$inner->end->mday,$inner->end->year) - $this->datetime->tz_offset;
				$istime = $phpgw->common->show_date($inner_stime,'Hi');
				$ietime = $phpgw->common->show_date($inner_etime,'Hi');
				
				if($inner->recur_type == MCAL_RECUR_NONE)
				{
					if($inner_stime < $datetime)
					{
						$istime = 0;
					}
				
					if($inner_etime > ($datetime + 86399))
					{
						$ietime = 2359;
					}
				}
				
				if(($ostime > $istime) || (($ostime == $istime) && ($oetime > $ietime)))
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

	function mini_calendar($day,$month,$year,$link='',$buttons="none",$outside_month=True)
	{
		global $phpgw, $phpgw_info, $view;

		$date = $this->datetime->makegmttime(0,0,0,$month,$day,$year);
		$month_ago = intval(date('Ymd',mktime(0,0,0,$month - 1,$day,$year)));
		$month_ahead = intval(date('Ymd',mktime(0,0,0,$month + 1,$day,$year)));
		$monthstart = intval(date('Ymd',mktime(0,0,0,$month,1,$year)));
		$monthend = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

		$weekstarttime = $this->datetime->get_weekday_start($year,$month,1);

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');

		$templates = Array(
			'mini_calendar'	=> 'mini_cal.tpl'
		);
		$p->set_file($templates);
		$p->set_block('mini_calendar','mini_cal','mini_cal');
		$p->set_block('mini_calendar','mini_week','mini_week');
		$p->set_block('mini_calendar','mini_day','mini_day');

		if($this->printer_friendly == False)
		{
			$month = '<a href="' . $phpgw->link('/calendar/month.php','month='.$phpgw->common->show_date($date['raw'],'m').'&year='.$phpgw->common->show_date($date['raw'],'Y').'&owner='.$this->owner) . '" class="minicalendar">' . lang($phpgw->common->show_date($date['raw'],'F')).' '.$phpgw->common->show_date($date['raw'],'Y').'</a>';
		}
		else
		{
			$month = lang($phpgw->common->show_date($date['raw'],'F')).' '.$phpgw->common->show_date($date['raw'],'Y');
		}

		$var = Array(
			'cal_img_root'		=>	$this->image_dir,
			'bgcolor'			=>	$phpgw_info['theme']['bg_color'],
			'bgcolor1'			=>	$phpgw_info['theme']['bg_color'],
			'month'				=>	$month,
			'bgcolor2'			=>	$phpgw_info['theme']['cal_dayview'],
			'holiday_color'	=> (substr($phpgw_info['theme']['bg07'],0,1)=='#'?'':'#').$phpgw_info['theme']['bg07']
		);

		$p->set_var($var);

		switch(strtolower($buttons))
		{
			case 'right':
				$var = Array(
					'nextmonth'			=>	'<a href="'.$phpgw->link('/calendar/month.php','date='.$month_ahead.'&owner='.$this->owner).'"><img src="'.$this->phpgwapi_template_dir.'/right.gif" border="0"></a>'
				);
				break;
			case 'left':
				$var = Array(
					'prevmonth'			=>	'<a href="'.$phpgw->link('/calendar/month.php','date='.$month_ago.'&owner='.$this->owner).'"><img src="'.$this->phpgwapi_template_dir.'/left.gif" border="0"></a>'
				);					
				break;
			case 'both':
				$var = Array(
					'prevmonth'			=>	'<a href="'.$phpgw->link('/calendar/month.php','date='.$month_ago.'&owner='.$this->owner).'"><img src="'.$this->phpgwapi_template_dir.'/left.gif" border="0"></a>',
					'nextmonth'			=>	'<a href="'.$phpgw->link('/calendar/month.php','date='.$month_ahead.'&owner='.$this->owner).'"><img src="'.$this->phpgwapi_template_dir.'/right.gif" border="0"></a>'
				);
				break;
			case 'none':
			default:
				$var = Array(
					'prevmonth'			=>	'',
					'nextmonth'			=>	''
				);
				break;
		}
		$p->set_var($var);

		for($i=0;$i<7;$i++)
		{
			$p->set_var('dayname','<b>' . substr(lang($this->datetime->days[$i]),0,2) . '</b>');
			$p->parse('daynames','mini_day',True);
		}
		for($i=$weekstarttime;date('Ymd',$i)<=$monthend;$i += (24 * 3600 * 7))
		{
			for($j=0;$j<7;$j++)
			{
				$str = '';
				$cal = $this->datetime->gmtdate($i + ($j * 24 * 3600));
				$cal = $this->datetime->makegmttime(0,0,0,$cal['month'],$cal['day'],$cal['year']);
				if($cal['full'] >= $monthstart && $cal['full'] <= $monthend)
				{
					$day_image = '';
					if ($cal['full'] == $this->today['full'])
					{
						$day_image .= ' background="'.$this->image_dir.'/mini_day_block.gif"';
					}
//					else
//					{
//						$p->set_var('bgcolor2','#FFFFFF');
//					}
					
					$p->set_var('day_image',$day_image);


					$holiday_found = $this->holidays->find_date($cal['raw']);
					if($holiday_found != False)
					{
						$class = 'minicalhol';
					}
					else
					{
						$class = 'minicalendar';
					}

					if(!$this->printer_friendly)
					{
						$str .= '<a href="'.$phpgw->link('/calendar/'.$link,'year='.$cal['year'].'&month='.$cal['month'].'&day='.$cal['day'].'&owner='.$this->owner).'" class="'.$class.'">';
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
					$dayname = '';
					$holiday_found = $this->holidays->find_date($cal['raw']);
					if($outside_month == True)
					{
						if($holiday_found == False)
						{
							$class = 'minicalendargrey';
						}
						else
						{
							$class = 'minicalgreyhol';
						}
						if(!$this->printer_friendly)
						{
							$dayname .= '<a href="'.$phpgw->link('/calendar/'.$link,'year='.$cal['year'].'&month='.$cal['month'].'&day='.$cal['day']).'" class="'.$class.'">';
						}
						$dayname .= $cal['day'];

						if(!$this->printer_friendly)
						{
							$dayname .= '</a>';
						}
					}

					$var = Array(
						'day_image'	=> '',
						'dayname'	=> $dayname
					);
					$p->set_var($var);
				}
				$p->parse('monthweek_day','mini_day',True);
			}
			$p->parse('display_monthweek','mini_week',True);
			$p->set_var('dayname','');
			$p->set_var('monthweek_day','');
		}
		
		$return_value = $p->fp('out','mini_cal');
		unset($p);
		return $return_value;
	}

	function overlap($starttime,$endtime,$participants,$owner=0,$id=0)
	{
		global $phpgw, $phpgw_info;

		$retval = Array();
		$ok = False;

		if($starttime == $endtime)
		{
			$endtime = mktime(23,59,59,$phpgw->common->show_date($starttime,'m'),$phpgw->common->show_date($starttime,'d') + 1,$phpgw->common->show_date($starttime,'Y')) - $this->datetime->tz_offset;
		}

		$sql = 'AND ((('.$starttime.' <= phpgw_cal.datetime) AND ('.$endtime.' >= phpgw_cal.datetime) AND ('.$endtime.' <= phpgw_cal.edatetime)) '
				.  'OR (('.$starttime.' >= phpgw_cal.datetime) AND ('.$starttime.' < phpgw_cal.edatetime) AND ('.$endtime.' >= phpgw_cal.edatetime)) '
				.  'OR (('.$starttime.' <= phpgw_cal.datetime) AND ('.$endtime.' >= phpgw_cal.edatetime)) '
				.  'OR (('.$starttime.' >= phpgw_cal.datetime) AND ('.$endtime.' <= phpgw_cal.edatetime))) ';

		if(count($participants) > 0)
		{
			$p_g = '';
			if(count($participants))
			{
				$users = '';
				while(list($user,$status) = each($participants))
				{
					if($users)
					{
						$users .= ',';
					}
					$users .= $user;
				}
				if($users)
				{
					$p_g .= 'phpgw_cal_user.cal_login in ('.$users.')';
				}
			}
			if($p_g)
			{
				$sql .= ' AND (' . $p_g . ')';
			}
		}
      
		if($id)
		{
			$sql .= ' AND phpgw_cal.cal_id <> '.$id;
		}

		$sql .= ' ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

		$events = $this->get_event_ids(False,$sql);
		if($events == False)
		{
			return false;
		}
		
		$db2 = $phpgw->db;

		for($i=0;$i<count($events);$i++)
		{
			$db2->query('SELECT recur_type FROM phpgw_cal_repeats WHERE cal_id='.$events[$i],__LINE__,__FILE__);
			if($db2->num_rows() == 0)
			{
				$retval[] = $events[$i];
				$ok = True;
			}
			else
			{
				$db2->next_record();
				if($db2->f('recur_type') <> MCAL_RECUR_MONTHLY_MDAY)
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

		$this->weekstarttime = $this->datetime->get_weekday_start($year,$month,1);

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('remove');
		$templates = Array (
			'month_header' => 'month_header.tpl'
		);
		$p->set_file($templates);
		$p->set_block('month_header','monthly_header','monthly_header');
		$p->set_block('month_header','column_title','column_title');

		$var = Array(
			'bgcolor'		=> $phpgw_info['theme']['th_bg'],
			'font_color'	=> $phpgw_info['theme']['th_text']
		);
		$p->set_var($var);
		
		$p->set_var('col_width','14');
		if($display_name == True)
		{
			$p->set_var('col_title',lang('name'));
			$p->parse('column_header','column_title',True);
			$p->set_var('col_width','12');
		}

		for($i=0;$i<7;$i++)
		{
			$p->set_var('col_title',lang($this->datetime->days[$i]));
			$p->parse('column_header','column_title',True);
		}
		
		return $p->fp('out','monthly_header');
	}

	function display_week($startdate,$weekly,$cellcolor,$display_name = False,$owner=0,$monthstart=0,$monthend=0)
	{
		global $phpgw, $phpgw_info, $grants;

		if($owner == 0) { $owner= $phpgw_info['user']['account_id']; }

		$temp_owner = $this->owner;
		$this->owner = $owner;

		$str = '';
		$browser = CreateObject('phpgwapi.browser');
		$gr_events = CreateObject('calendar.calendar_item');
		$lr_events = CreateObject('calendar.calendar_item');
		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('keep');
		
		$templates = Array (
			'month_header'		=> 'month_header.tpl',
			'month_day'			=> 'month_day.tpl'
		);
		$p->set_file($templates);
		$p->set_block('month_header','monthly_header','monthly_header');
		$p->set_block('month_header','month_column','month_column');
		$p->set_block('month_day','month_daily','month_daily');
		$p->set_block('month_day','day_event','day_event');
		$p->set_block('month_day','event','event');
		
		$p->set_var('extra','');

		$p->set_var('col_width','14');
		if($display_name)
		{
			$p->set_var('column_data',$phpgw->common->grab_owner_name($owner));
			$p->parse('column_header','month_column',True);
			$p->set_var('col_width','12');
		}
		for ($j=0;$j<7;$j++)
		{
			$date = $this->datetime->gmtdate($startdate + ($j * 86400));
			$var = Array(
				'column_data'	=>	'',
				'extra'		=>	''
			);
			$p->set_var($var);
			
			$day = $phpgw->common->show_date($date['raw'],'d');
			$month = $phpgw->common->show_date($date['raw'],'m');
			$year = $phpgw->common->show_date($date['raw'],'Y');
			$date = $this->datetime->gmtdate(mktime(0,0,0,$date['month'],$date['day'],$date['year']));

			if ($weekly || ($date['full'] >= $monthstart && $date['full'] <= $monthend))
			{
				if($weekly)
				{
					$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
				}
				
//				echo 'Date = '.$date['raw'].'  '.date('Y.m.d H:i:s',$date['raw'])."<br>\n";
				if ($date['full'] != $this->today['full'])
				{
					$extra = ' bgcolor="'.$cellcolor.'"';
				}
				else
				{
					$extra = ' bgcolor="'.$phpgw_info['theme']['cal_today'].'"';
				}

				$holiday_found = $this->holidays->find_date($date['raw']);
				if($holiday_found != False)
				{
					$extra = ' bgcolor="'.$phpgw_info['theme']['bg04'].'"';
				}

//				$day = $phpgw->common->show_date($date['raw'],'d');
//				$month = $phpgw->common->show_date($date['raw'],'m');
//				$year = $phpgw->common->show_date($date['raw'],'Y');
//				$date = $this->gmtdate(mktime(0,0,0,$month,$day,$year));
				$new_event_link = '';
				if (!$this->printer_friendly)
				{
					if((!!($grants[$owner] & PHPGW_ACL_ADD) == True))
					{
						$new_event_link .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&owner='.$owner).'">'
							. '<img src="'.$this->image_dir.'/new.gif" width="10" height="10" alt="'.lang('New Entry').'" border="0" align="center">'
							. '</a>';
					}
					$day_number = '<a href="'.$phpgw->link('/calendar/day.php','month='.$month.'&day='.$day.'&year='.$year.'&owner='.$owner).'">'.$day.'</a>';
				}
				else
				{
					$day_number = $day;
				}

				$var = Array(
					'extra'		=>	$extra,
					'new_event_link'	=> $new_event_link,
					'day_number'		=>	$day_number
				);

				$p->set_var($var);
				
				if($holiday_found != False)
				{
					while(list(,$value) = each($holiday_found))
					{
						$p->set_var('day_events','<font face="'.$phpgw_info['theme']['font'].'" size="-1">'.$this->holidays->get_name($value).'</font>'.$browser->br);
						$p->parse('daily_events','event',True);
					}
				}

				$rep_events = $this->get_sorted_by_date($date['raw'],$owner);

//				echo "Searching for events on : ".$phpgw->common->show_date($date['raw']).' : Found : '.count($rep_events).' : Reported : '.$this->sorted_events_matching."<br>\n";

				if ($this->sorted_events_matching)
				{
					$lr_events = CreateObject('calendar.calendar_item');
					$var = Array(
						'week_day_font_size'	=>	'2',
						'events'		=>	''
					);
					$p->set_var($var);
					$c_rep_events = count($rep_events);
					for ($k=0;$k<$c_rep_events;$k++)
					{
						$lr_events = $rep_events[$k];
						
						$is_private = $this->is_private($lr_events,$owner);

						$p->set_var('day_events',$this->link_to_entry($lr_events,$month,$day,$year));

						$p->parse('events','event',True);
						$p->set_var('day_events','');
					}
				}
				$p->parse('daily_events','day_event',True);
				$p->parse('column_data','month_daily',True);
//				$p->parse('column_data','week_day_events',True);
				$p->set_var('daily_events','');
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
					$p->parse('column_data','day_event',True);
					$p->set_var('events','');
				}
			}
			$p->parse('column_header','month_column',True);
			$p->set_var('column_data','');
		}
		$this->owner = $temp_owner;
		return $p->fp('out','monthly_header');
	}

	function display_large_week($day,$month,$year,$showyear,$owners=0)
	{
		global $phpgw, $phpgw_info;

		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('keep');

		$templates = Array(
			'week'			=>	'month_day.tpl'
		);
		$p->set_file($templates);
		$p->set_block('week','m_w_table','m_w_table');
		$p->set_block('week','event','event');
		
		$start = $this->datetime->get_weekday_start($year, $month, $day);

		$this->end_repeat_day = intval(date('Ymd',$start + 604800));

		$cellcolor = $phpgw_info['theme']['row_off'];

		$true_printer_friendly = $this->printer_friendly;

		if(is_array($owners))
		{
			$display_name = True;
			$counter = count($owners);
			$owners_array = $owners;
			$p->set_var('cols','8');
		}
		else
		{
			$display_name = False;
			$counter = 1;
			$owners_array[0] = $owners;
			$p->set_var('cols','7');
		}
		$p->set_var('day_events',$this->large_month_header($month,$year,$display_name));
		$p->parse('row','event',True);

		for($i=0;$i<$counter;$i++)
		{
			$this->repeated_events = Null;
			$this->repeating_events = Null;
			$owner = $owners_array[$i];
			$this->read_repeated_events($owner);
			$p->set_var('day_events',$this->display_week($start,True,$cellcolor,$display_name,$owner));
			$p->parse('row','event',True);
		}
		$this->printer_friendly = $true_printer_friendly;
		return $p->fp('out','m_w_table');
	}

	function display_large_month($month,$year,$showyear,$owner=0)
	{
		global $phpgw, $phpgw_info;

		if($owner == $phpgw_info['user']['account_id'])
		{
			$owner = 0;
		}

		$monthstart = intval(date('Ymd',mktime(0,0,0,$month    ,1,$year)));
		$monthend   = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

		$this->end_repeat_day = $monthend;
		
		$start = $this->datetime->get_weekday_start($year, $month, 1);

		$this->repeated_events = Null;
		$this->repeating_events = Null;
		$this->read_repeated_events($owner);
		
		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('keep');
		
		$templates = Array(
			'week'			=>	'month_day.tpl'
		);
		$p->set_file($templates);
		$p->set_block('week','m_w_table','m_w_table');
		$p->set_block('week','event','event');

		$p->set_var('cols','7');
		$p->set_var('day_events',$this->large_month_header($month,$year,False));
		$p->parse('row','event',True);

		$cellcolor = $phpgw_info['theme']['row_on'];

		for ($i=intval($start);intval(date('Ymd',$i)) <= $monthend;$i += 604800)
		{
			$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
			$p->set_var('day_events',$this->display_week($i,False,$cellcolor,False,$owner,$monthstart,$monthend));
			$p->parse('row','event',True);
		}
		return $p->fp('out','m_w_table');
	}

	function html_for_event_day_at_a_glance ($event,$first_hour,$last_hour,&$time,$month,$day,$year)
	{
		$ind = intval($event->start->hour);

		if($ind<$first_hour || $ind>$last_hour)
		{
			$ind = 99;
		}

		if(!isset($time[$ind]) || !$time[$ind])
		{
			$time[$ind] = '';
		}

		$time[$ind] .= $this->link_to_entry($event,$month,$day,$year);


		$starttime = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year);
		$endtime = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year);

		if ($starttime <> $endtime)
		{
			$this->rowspan = (int)(($endtime - $starttime) / 3600);
			$mins = (int)((($endtime - $starttime) / 60) % 60);
			
			if ($mins <> 0)
			{
				$this->rowspan += 1;
			}
			
			if ($this->rowspan > $this->rowspan_arr[$ind] && $this->rowspan > 1)
			{
				$this->rowspan_arr[$ind] = $this->rowspan;
			}
		}
	}

	function print_day_at_a_glance($date)
	{
		global $phpgw, $phpgw_info;


		$this->end_repeat_day = intval(date('Ymd',$date['raw']));
		$this->read_repeated_events($this->owner);
		$p = CreateObject('phpgwapi.Template',$this->template_dir);
		$p->set_unknowns('keep');

		$templates = Array(
			'day_cal'			=>	'day_cal.tpl'
		);
      $p->set_file($templates);
		$p->set_block('day_cal','day','day');
		$p->set_block('day_cal','day_row','day_row');
		$p->set_block('day_cal','day_event','day_event');
		$p->set_block('day_cal','day_time','day_time');
      
		if (! $phpgw_info['user']['preferences']['calendar']['workdaystarts'] &&
			 ! $phpgw_info['user']['preferences']['calendar']['workdayends'])
		{
			$phpgw_info['user']['preferences']['calendar']['workdaystarts'] = 8;
			$phpgw_info['user']['preferences']['calendar']['workdayends']   = 16;
			$phpgw->preferences->save_repository();
		}

		$t_format = $phpgw_info['user']['preferences']['common']['time_format'];
		$browser = CreateObject('phpgwapi.browser');
		$browser->browser();
		$browser_agent = $browser->get_agent();
		if($browser_agent == 'MOZILLA')
		{
			if($t_format == '12')
			{
				$time_width=12;
			}
			else
			{
				$time_width=8;
			}
		}
		else
		{
			if($t_format == '12')
			{
				$time_width=10;
			}
			else
			{
				$time_width=7;
			}
		}
		$var = Array(
			'time_width'		=> $time_width,
			'time_bgcolor'		=>	$phpgw_info['theme']['navbar_bg'],
			'font_color'		=>	$phpgw_info['theme']['bg_text'],
			'time_border_color'	=> $phpgw_info['theme']['navbar_text'],
			'font'				=>	$phpgw_info['theme']['font']
		);

		$p->set_var($var);

		$first_hour = (int)$phpgw_info['user']['preferences']['calendar']['workdaystarts'];
		$last_hour  = (int)$phpgw_info['user']['preferences']['calendar']['workdayends'];

		for ($i=0;$i<24;$i++)
		{
			$this->rowspan_arr[$i] = 0;
		}

		$events = Array(
			CreateObject('calendar.calendar_item')
		);

		$time = Array();

		$date = $this->datetime->localdates($date['raw'] - $this->datetime->tz_offset);

//		echo 'Searching for events on : '.$phpgw->common->show_date($date['raw'])."<br>\n";

		$events = $this->get_sorted_by_date($date['raw'],$this->owner);

		if($events)
      {
			$c_events = count($events);
			for($i=0;$i<$c_events;$i++)
			{
				$this->html_for_event_day_at_a_glance($events[$i],$first_hour,$last_hour,$time,$date['month'],$date['day'],$date['year']);
			}
		}

		// squish events that use the same cell into the same cell.
		// For example, an event from 8:00-9:15 and another from 9:30-9:45 both
		// want to show up in the 8:00-9:59 cell.
		$this->rowspan = 0;
		$last_row = -1;
		for ($i=0;$i<24;$i++)
		{
			if ($this->rowspan > 1)
			{
				if (isset($time[$i]) && strlen($time[$i]) > 0)
				{
					$this->rowspan_arr[$last_row] += $this->rowspan_arr[$i];
					if ($this->rowspan_arr[$i] <> 0)
					{
						$this->rowspan_arr[$last_row] -= 1;
					}
					$time[$last_row] .= $time[$i];
					$time[$i] = '';
					$this->rowspan_arr[$i] = 0;
				}
				$this->rowspan--;
			}
			elseif ($this->rowspan_arr[$i] > 1)
			{
				$this->rowspan = $this->rowspan_arr[$i];
				$last_row = $i;
			}
		}
		
		$holiday_found = $this->holidays->find_date($date['raw']);
		if($holiday_found == False)
		{
			$bgcolor = $phpgw->nextmatchs->alternate_row_color();
		}
		else
		{
			$bgcolor = $phpgw_info['theme']['bg04'];
			while(list(,$value) = each($holiday_found))
			{
				$time[99] = '<center>'.$this->holidays->get_name($value).'</center>'.$time[99];
			}
		}

		if (isset($time[99]) && strlen($time[99]) > 0)
		{
			$var = Array(
				'event'		=>	$time[99],
				'bgcolor'	=>	$bgcolor
			);
			$p->set_var($var);
			$p->parse('item','day_event',False);

			$var = Array(
				'open_link'		=>	'',
				'time'			=>	'&nbsp;',
				'close_link'	=>	''
			);
			$p->set_var($var);
			
			$p->parse('item','day_time',True);
			$p->parse('row','day_row',True);
			$p->set_var('item','');
		}
		$this->rowspan = 0;
		for ($i=$first_hour;$i<=$last_hour;$i++)
		{
			$dtime = $this->build_time_for_display($i * 10000);
			$p->set_var('extras','');
			$p->set_var('event','&nbsp');
			if ($this->rowspan > 1)
			{
				// this might mean there's an overlap, or it could mean one event
				// ends at 11:15 and another starts at 11:30.
				if (isset($time[$i]) && strlen($time[$i]))
				{
					$p->set_var('event',$time[$i]);
					$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
					$p->parse('item','day_event',False);
				}
				$this->rowspan--;
			}
			elseif (!isset($time[$i]) || !strlen($time[$i]))
			{
				$p->set_var('event','&nbsp;');
				$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
				$p->parse('item','day_event',False);
			}
			else
			{
				$this->rowspan = intval($this->rowspan_arr[$i]);
				if ($this->rowspan > 1)
				{
					$p->set_var('extras',' rowspan="'.$this->rowspan.'"');
				}
				$p->set_var('event',$time[$i]);
				$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
				$p->parse('item','day_event',False);
			}
			
			$open_link = ' - ';
			$close_link = '';
			
			if(($this->printer_friendly == False) && ($this->check_perms(PHPGW_ACL_ADD) == True))
			{
				$new_hour = intval(substr($dtime,0,strpos($dtime,':')));
				if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12' && $i >= 12)
				{
					$new_hour += 12;
				}
				
				$new_minute = substr($dtime,strpos($dtime,':')+1,2);
				
				$open_link .= '<a href="'.$phpgw->link('/calendar/edit_entry.php',
								  'year='.$date['year'].'&month='.$date['month']
								. '&day='.$date['day'].'&hour='.$new_hour
								. '&minute='.$new_minute.'&owner='.$this->owner).'">';
								
				$close_link = '</a>';
			}

			$var = Array(
				'open_link'		=>	$open_link,
				'time'			=>	(intval(substr($dtime,0,strpos($dtime,':'))) < 10 ? '0'.$dtime : $dtime),
				'close_link'	=>	$close_link
			);
			
			$p->set_var($var);
			
			$p->parse('item','day_time',True);
			$p->parse('row','day_row',True);
			$p->set_var('event','');
			$p->set_var('item','');
		}	// end for
		return $p->fp('out','day');
	}	// end function

	function view_add_day($day,&$repeat_days)
	{
		if($repeat_days)
		{
			$repeat_days .= ', ';
		}
		$repeat_days .= $day.' ';
	}

	function view_event($event)
	{
		global $phpgw, $phpgw_info;

		$pri = Array(
  			1	=> lang('Low'),
  			2	=> lang('Normal'),
	  		3	=> lang('High')
		);

		reset($event->participants);
		if($event->participants[$this->owner])
		{
			$participating = True;
		}
		else
		{
			$participating = False;
		}
		
		if($event->participants[$phpgw_info['user']['account_id']])
		{
			$participate = True;
		}
		else
		{
			$participate = False;
		}

		$p = CreateObject('phpgwapi.Template',$this->template_dir);

		$p->set_unknowns('keep');
		$templates = Array(
  			'view'	=> 'view.tpl'
		);
		$p->set_file($templates);
		$p->set_block('view','view_event','view_event');
		$p->set_block('view','list','list');

		$var = Array(
			'bg_text'	=>	$phpgw_info['theme']['bg_text'],
			'name'	=>	$event->title
		);
		$p->set_var($var);

		// Some browser add a \n when its entered in the database. Not a big deal
		// this will be printed even though its not needed.
		if (nl2br($event->description))
		{
			$var = Array(
				'field'	=>	lang('Description'),
				'data'	=>	nl2br($event->description)
			);
			$p->set_var($var);
			$p->parse('row','list',True);
		}

		if ($event->category)
		{
			$cats = CreateObject('phpgwapi.categories');
			$cats->categories($this->owner,'calendar');
			$cat = $cats->return_single($event->category);
			$var = Array(
				'field'	=>	lang('Category'),
				'data'	=>	$cat[0]['name']
			);
			$p->set_var($var);
			$p->parse('row','list',True);
		}

		$var = Array(
			'field'	=>	lang('Start Date/Time'),
			'data'	=>	$phpgw->common->show_date(mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->datetime->tz_offset)
		);
		$p->set_var($var);
		$p->parse('row','list',True);
	
		$var = Array(
			'field'	=>	lang('End Date/Time'),
			'data'	=>	$phpgw->common->show_date(mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->datetime->tz_offset)
		);
		$p->set_var($var);
		$p->parse('row','list',True);

		$var = Array(
			'field'	=>	lang('Priority'),
			'data'	=>	$pri[$event->priority]
		);
		$p->set_var($var);
		$p->parse('row','list',True);

		$var = Array(
			'field'	=>	lang('Created By'),
			'data'	=>	$phpgw->common->grab_owner_name($event->owner)
		);
		$p->set_var($var);
		$p->parse('row','list',True);
	
		$var = Array(
			'field'	=>	lang('Updated'),
			'data'	=>	$phpgw->common->show_date(mktime($event->mod->hour,$event->mod->min,$event->mod->sec,$event->mod->month,$event->mod->mday,$event->mod->year) - $this->datetime->tz_offset)
		);
		$p->set_var($var);
		$p->parse('row','list',True);

		$var = Array(
			'field'	=>	lang('Private'),
			'data'	=>	$event->public==True?'False':'True'
		);
		$p->set_var($var);
		$p->parse('row','list',True);

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
			$p->parse('row','list',True);
		}

		$str = '';
		reset($event->participants);
		while (list($user,$short_status) = each($event->participants))
		{
			if($str)
			{
				$str .= '<br>';
			}

			$long_status = $this->get_long_status($short_status);
			
			$str .= $phpgw->common->grab_owner_name($user).' (';
			
			if($user == $this->owner && $this->check_perms(PHPGW_ACL_EDIT) == True)
			{
				$str .= '<a href="'.$phpgw->link('/calendar/edit_status.php','owner='.$this->owner.'&id='.$event->id).'">'.$long_status.'</a>';
			}
			else
			{
				$str .= $long_status;
			}
			$str .= ')'."\n";
		}
		$var = Array(
			'field'	=>	lang('Participants'),
			'data'	=>	$str
		);
		$p->set_var($var);
		$p->parse('row','list',True);

// Repeated Events
		$rpt_type = Array(
			MCAL_RECUR_NONE => 'none',
			MCAL_RECUR_DAILY => 'daily',
			MCAL_RECUR_WEEKLY => 'weekly',
			MCAL_RECUR_MONTHLY_WDAY => 'monthlybyday',
			MCAL_RECUR_MONTHLY_MDAY => 'monthlybydate',
			MCAL_RECUR_YEARLY => 'yearly'
		);
		$str = lang($rpt_type[$event->recur_type]);
		if($event->recur_type <> MCAL_RECUR_NONE)
		{
			$str_extra = '';
			if ($event->recur_enddate->mday != 0 && $event->recur_enddate->month != 0 && $event->recur_enddate->year != 0)
			{
				$recur_end = mktime($event->recur_enddate->hour,$event->recur_enddate->min,$event->recur_enddate->sec,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year);
				if($recur_end != 0)
				{
					$recur_end -= $this->datetime->tz_offset;
					$str_extra .= lang('ends').': '.lang($phpgw->common->show_date($recur_end,'l'));
					$str_extra .= ', '.lang($phpgw->common->show_date($recur_end,'F'));
					$str_extra .= ' '.$phpgw->common->show_date($recur_end,'d, Y').' ';
				}
			}
			if($event->recur_type == MCAL_RECUR_WEEKLY || $event->recur_type == MCAL_RECUR_DAILY)
			{
				$repeat_days = '';
				if($phpgw_info['user']['preferences']['calendar']['weekdaystarts'] == 'Sunday')
				{
					if (!!($event->recur_data & MCAL_SUNDAY) == True)
					{
						$this->view_add_day(lang('Sunday'),$repeat_days);
					}
				}
				if (!!($event->recur_data & MCAL_MONDAY) == True)
				{
					$this->view_add_day(lang('Monday'),$repeat_days);
				}
				if (!!($event->recur_data & MCAL_TUESDAY) == True)
				{
					$this->view_add_day(lang('Tuesday'),$repeat_days);
				}
				if (!!($event->recur_data & MCAL_WEDNESDAY) == True)
				{
					$this->view_add_day(lang('Wednesday'),$repeat_days);
				}
				if (!!($event->recur_data & MCAL_THURSDAY) == True)
				{
					$this->view_add_day(lang('Thursday'),$repeat_days);
				}
				if (!!($event->recur_data & MCAL_FRIDAY) == True)
				{
					$this->view_add_day(lang('Friday'),$repeat_days);
				}
				if (!!($event->recur_data & MCAL_SATURDAY) == True)
				{
					$this->view_add_day(lang('Saturday'),$repeat_days);
				}
				if($phpgw_info['user']['preferences']['calendar']['weekdaystarts'] == 'Monday')
				{
					if (!!($event->recur_data & MCAL_SUNDAY) == True)
					{
						$this->view_add_day(lang('Sunday'),$repeat_days);
					}
				}
				if($repeat_days <> '')
				{
					$str_extra .= lang('days repeated').': '.$repeat_days;
				}
			}
			if($event->recur_interval)
			{
				$str_extra .= lang('Interval').': '.$event->recur_interval;
			}

			if($str_extra)
			{
				$str .= ' ('.$str_extra.')';
			}

			$var = Array(
				'field'	=>	lang('Repetition'),
				'data'	=>	$str
			);
			$p->set_var($var);
			$p->parse('row','list',True);
		}

		return $p->fp('out','view_event');
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

		$response_choices = Array(
			ACCEPTED	=> lang('Accept'),
			REJECTED	=> lang('Reject'),
			TENTATIVE	=> lang('Tentative'),
			NO_RESPONSE	=> lang('No Response')
		);
		while(list($param,$text) = each($response_choices))
		{
			$var = Array(	
				'action_url_button'	=> $phpgw->link('/calendar/action.php','id='.$this->event->id.'&action='.$param),
				'action_text_button'	=> '  '.$text.'  ',
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			$str .= '<td>'.$p->fp('out','form_button').'</td>'."\n";
		}
		$str .= '</tr></table>';
		return $str;
	}

	function timematrix($date,$starttime,$endtime,$participants)
	{
		global $phpgw, $phpgw_info;

		if(!isset($phpgw_info['user']['preferences']['calendar']['interval']) ||
			!$phpgw_info['user']['preferences']['calendar']['interval'])
		{
			$phpgw_info['user']['preferences']['calendar']['interval'] = 15;
		}
		$increment = $phpgw_info['user']['preferences']['calendar']['interval'];
		$interval = (int)(60 / $increment);

		$str = '<center>'.lang($phpgw->common->show_date($date['raw'],'l'));
		$str .= ', '.lang($phpgw->common->show_date($date['raw'],'F'));
		$str .= ' '.$phpgw->common->show_date($date['raw'],'d, Y').'<br>';
		$str .= '<table width="85%" border="0" cellspacing="0" cellpadding="0" cols="'.((24 * $interval) + 1).'">';
		$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$this->image_dir.'/pix.gif"></td></tr>';
		$str .= '<tr><td width="15%"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">'.lang('Participant').'</font></td>';
		for($i=0;$i<24;$i++)
		{
			for($j=0;$j<$interval;$j++)
			{
				switch($j)
				{
					case 0:
					case 1:
//						if($interval == 4)
//						{
//							$k = ($i<=9?'0':substr($i,$j,$j+1));
//						}
//						else
//						{
						switch($j)
						{
							case 0:
								$pre = '0';
								break;
							case 1:
								$pre = substr($i,0,1);
								break;
						}
						
							$k = ($i<=9?$pre:substr($i,$j,$j+1));
//						}
						$str .= '<td align="right" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">';
						$str .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.(($increment * $j)<=9?'0':'').($increment * $j)."'; return true;\">";
						$str .= $k.'</a></font></td>';
						break;
//					case 1:
//						if($interval == 4)
//						{
//							$k = ($i<=9?substr($i,0,1):substr($i,1,2));
//						}
//						else
//						{
//							$k = ($i<=9?substr($i,0,1):substr($i,1,2));
//						}
//						$str .= '<td align="right" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">';
//						$str .= '<a href="'.$phpgw->link('/calendar/edit_entry.php','year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$i.'&minute='.(($increment * $j)<=9?'0':'').($increment * $j))."\" onMouseOver=\"window.status='".$i.':'.(($increment * $j)<=9?'0':'').($increment * $j)."'; return true;\">";
//						$str .= $k.'</a></font></td>';
//						break;
					default:
						$str .= '<td align="left" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">';
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
		$this->end_repeat_day = $date['raw'];
		for($i=0;$i<count($participants);$i++)
		{
			$this->read_repeated_events($participants[$i]);
			$str .= '<tr>';
			$str .= '<td width="15%"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">'.$this->get_fullname($participants[$i]).'</font></td>';
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
					$eventstart = $this->datetime->localdates($event->datetime);
					$eventend = $this->datetime->localdates($event->edatetime);
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
								$time_display = $phpgw->common->show_date($eventstart['raw'],$this->users_timeformat).'-'.$phpgw->common->show_date($eventend['raw'],$this->user_timeformat);
								$time_slice[$index]['description'] = '('.$time_display.') '.$this->is_private($event,$participants[$i],'title').$this->display_status($event->users_status);
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
						$str .= '<td height="1" align="left" bgcolor="'.$time_slice[$index]['color']."\" color=\"#999999\"  onMouseOver=\"window.status='".$time_slice[$index]['description']."'; return true;\">".'<font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">'.$time_slice[$index]['marker'].'</font></td>';
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
