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

$phpgw_info['server']['calendar_type'] = 'sql';
include(PHPGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_'.$phpgw_info['server']['calendar_type'].'.inc.php');

class calendar extends calendar_
{
	var $template_dir;
	var $image_dir;
	
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
		$this->image_dir = $phpgw->common->get_image_path('calendar');
      $this->today = $this->localdates(time());
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
			'img_root'			=>	$phpgw->common->get_image_path('phpgwapi'),
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
						$p->set_var('day_image',' background="' . $phpgw_info['server']['webserver_url']
									. '/calendar/templates/' . $phpgw_info['server']['template_set']
									. '/images/mini_day_block.gif' . '"');
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
