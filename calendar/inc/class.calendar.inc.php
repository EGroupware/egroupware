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
$phpgw_info['server']['calendar_type'] = 'sql';
include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_'.$phpgw_info['server']['calendar_type'].'.inc.php');

class calendar extends calendar_
{
	var $template_dir;
	var $phpgwapi_template_dir;
	var $image_dir;

	var $rowspan_arr = Array();
	var $rowspan;

	function calendar($params=False)
	{
	  global $phpgw, $phpgw_info;
	  
	  if(gettype($params)=="array")
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
        $this->rights = PHPGW_ACL_READ + PHPGW_ACL_ADD + PHPGW_ACL_EDIT + PHPGW_ACL_DELETE + 16;
      }

		$this->template_dir = $phpgw->common->get_tpl_dir('calendar');
		$this->phpgwapi_template_dir = $phpgw->common->get_image_path('phpgwapi');
		$this->image_dir = $phpgw->common->get_image_path('calendar');
      $this->today = $this->localdates(time());

      $this->open($this->owner);
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
			$month = '<a href="' . $phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/month.php','month='.date('m',$date['raw']).'&year='.date('Y',$date['raw']).'&owner='.$this->owner) . '" class="minicalendar">' . lang($phpgw->common->show_date($date['raw'],'F')).' '.$year . '</a>';
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
			'prevmonth'			=>	$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/month.php','date='.$month_ago.'&owner='.$this->owner),
			'nextmonth'			=>	$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/month.php','date='.$month_ahead.'&owner='.$this->owner),
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
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/'.$link,'year='.$cal['year'].'&month='.$cal['month'].'&day='.$cal['day'].'&owner='.$this->owner).'" class="minicalendar">';
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
			
			if($owner <> $phpgw_info['user']['account_id'] && $owner <> 0)
			{
				$this->printer_friendly = True;
			}
			else
			{
				$this->printer_friendly = $true_printer_friendly;
			}
			
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
		
		if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(16)) || ($description != 'private'))  && $this->check_perms(PHPGW_ACL_EDIT))
		{
			$time[$ind] .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url']
								.'/calendar/view.php','id='.$event->id.'&owner='.$this->owner)
								. "\" onMouseOver=\"window.status='"
								. lang('View this entry')."'; return true;\">";
		}

		$time[$ind] .= '[' . $phpgw->common->show_date($event->datetime,$format);
		if ($event->datetime <> $event->edatetime)
		{
			$time[$ind] .= ' - ' . $phpgw->common->show_date($event->edatetime,$format);
			$end_t_h = intval($phpgw->common->show_date($event->edatetime,'H'));
			$end_t_m = intval($phpgw->common->show_date($event->edatetime,'i'));
			
			if (end_t_m == 0)
			{
				$this->rowspan = $end_t_h - $ind;
			}
			else
			{
				$this->rowspan = $end_t_h - $ind + 1;
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

		if (($this->printer_friendly == False) && (($description == 'private' && $this->check_perms(16)) || ($description != 'private'))  && $this->check_perms(PHPGW_ACL_EDIT))
		{
			$time[$ind] .= '</a>';
		}
		
		if ($event->priority == 3)
		{
			$time[$ind] .= '<font color="CC0000">';
		}
		
		$time[$ind] .= $this->is_private($event,$this->owner,'name');

		if ($event->priority == 3)
		{
			$time[$ind] .= '</font>';
		}
		
		$time[$ind] .= '<br>';
	}

	function print_day_at_a_glance($date,$owner=0)
	{
		global $phpgw, $phpgw_info;

		$this->read_repeated_events($owner);

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
				$open_link .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url']
								. '/calendar/edit_entry.php','year='.$date['year']
								. '&month='.$date['month'].'&day='.$date['day']
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

	function timematrix($date,$starttime,$endtime,$participants)
	{
		global $phpgw, $phpgw_info;

		if(!isset($phpgw_info['user']['preferences']['calendar']['interval']) ||
			!$phpgw_info['user']['preferences']['calendar']['interval'])
		{
			$phpgw_info['user']['preferences']['calendar']['interval'] = 15;
		}
		$datetime = $this->gmtdate($date['raw']);
		$increment = $phpgw_info['user']['preferences']['calendar']['interval'];
		$interval = (int)(60 / $increment);

		$str = '<center>'.$phpgw->common->show_date($datetime['raw'],'l, F d, Y').'<br>';
		$str .= '<table width="85%" border="0" cellspacing="0" cellpadding="0" cols="'.((24 * $interval) + 1).'">';
		$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$phpgw_info['server']['app_images'].'/pix.gif"></td></tr>';
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
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/edit_entry.php','year='.$datetime['year'].'&month='.$datetime['month'].'&day='.$datetime['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j<=9?'0':'').($increment * $j)."'; return true;\">";
						$str .= $k.'</a></font></td>';
						break;
					case 1:
						if($interval == 4)
						{
							$k = ($i<=9?substr($i,0,1):substr($i,1,2));
						}
						$str .= '<td align="right" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'">';
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/edit_entry.php','year='.$datetime['year'].'&month='.$datetime['month'].'&day='.$datetime['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j)."'; return true;\">";
						$str .= $k.'</a></font></td>';
						break;
					default:
						$str .= '<td align="left" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'">';
						$str .= '<a href="'.$phpgw->link($phpgw_info['server']['webserver_url'].'/calendar/edit_entry.php','year='.$datetime['year'].'&month='.$datetime['month'].'&day='.$datetime['day'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j)."'; return true;\">";
						$str .= '&nbsp</a></font></td>';
						break;
				}
			}
		}
		$str .= '</tr>';
		$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$phpgw_info['server']['app_images'].'/pix.gif"></td></tr>';
		if(!$endtime)
		{
			$endtime = $starttime;
		}
		for($i=0;$i<count($participants);$i++)
		{
			$this->read_repeated_events($participants[$i]);
			$str .= '<tr>';
			$str .= '<td width="15%">'.$phpgw->common->grab_owner_name($participants[$i]).'</td>';
			$events = $this->get_sorted_by_date($datetime['raw'],$participants[$i]);
			if(!$this->sorted_re)
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
				for($k=0;$k<$this->sorted_re;$k++)
				{
					$event = $events[$k];
					$eventstart = $this->localdates($event->datetime);
					$eventend = $this->localdates($event->edatetime);
					$start = ($eventstart['hour'] * 10000) + ($eventstart['minute'] * 100);
					$starttemp = $this->splittime("$start");
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
					$endtemp = $this->splittime("$end");
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
					$starttemp = $this->splittime("$start");
					$endtemp = $this->splittime("$end");
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
								$time_slice[$index]['description'] = $this->is_private($event,$participants[$i]);
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
			$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="#999999"><img src="'.$phpgw_info['server']['app_images'].'/pix.gif"></td></tr>';
		}
		$str .= '</table></center>';
		return $str;
	}      
}
?>
