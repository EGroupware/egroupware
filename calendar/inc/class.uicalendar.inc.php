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

	class uicalendar
	{
		var $template;
		var $template_dir;
		var $printer_friendly;

		var $owner;
		
//		var $contacts;
		var $bo;
		var $cat;
		var $prefs;

		var $holidays;
		var $holiday_color;
		
		var $debug = False;

		var $filter;
		var $cat_id;

		var $public_functions = array(
			'mini_calendar' => True,
			'index' => True,
			'month' => True,
			'week'  => True,
			'year' => True,
			'view' => True,
			'add'  => True,
			'edit' => True,
			'delete' => True
		);

		function uicalendar()
		{
			global $phpgw, $phpgw_info, $friendly;

			$phpgw->browser    = CreateObject('phpgwapi.browser');

			if($friendly == 1)
			{
				$this->printer_friendly = True;
			}
			else
			{
				$this->printer_friendly = False;
			}

			$this->bo       = CreateObject('calendar.bocalendar',True);
			if(!isset($this->bo->year))
			{
				$this->bo->year = date('Y',time());
			}
			if(!isset($this->bo->month))
			{
				$this->bo->month = date('m',time());
			}
			if(!isset($this->bo->day))
			{
				$this->bo->day = date('d',time());
			}

			$this->owner    = $this->bo->owner;
			$this->template = $phpgw->template;
			$this->template_dir = $phpgw->common->get_tpl_dir('calendar');
			$this->cat      = CreateObject('phpgwapi.categories');
			$this->prefs    = $phpgw_info['user']['preferences']['calendar'];

			$this->holiday_color = (substr($phpgw_info['theme']['bg07'],0,1)=='#'?'':'#').$phpgw_info['theme']['bg07'];
			
			$this->filter   = $this->bo->filter;
			$this->cat_id   = $this->bo->cat_id;

			$this->users_timeformat = $this->bo->users_timeformat;

			$this->save_sessiondata();

			if($this->debug)
			{
				$this->_debug_sqsof();
			}
		}

		/* Public functions */

		function mini_calendar($day,$month,$year,$link='',$buttons="none",$outside_month=True)
		{
			global $phpgw, $phpgw_info;

			$this->bo->read_holiday();

			$date = $this->bo->datetime->makegmttime(0,0,0,$month,$day,$year);
			$month_ago = intval(date('Ymd',mktime(0,0,0,$month - 1,$day,$year)));
			$month_ahead = intval(date('Ymd',mktime(0,0,0,$month + 1,$day,$year)));
			$monthstart = intval(date('Ymd',mktime(0,0,0,$month,1,$year)));
			$monthend = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

			$weekstarttime = $this->bo->datetime->get_weekday_start($year,$month,1);

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
				$month = '<a href="' . $this->page('month','&month='.$phpgw->common->show_date($date['raw'],'m').'&year='.$phpgw->common->show_date($date['raw'],'Y')). '" class="minicalendar">' . lang($phpgw->common->show_date($date['raw'],'F')).' '.$phpgw->common->show_date($date['raw'],'Y').'</a>';
			}
			else
			{
				$month = lang($phpgw->common->show_date($date['raw'],'F')).' '.$phpgw->common->show_date($date['raw'],'Y');
			}

			$var = Array(
				'cal_img_root'		=>	$phpgw->common->image('calendar','mini-calendar-bar.gif'),
				'bgcolor'			=>	$phpgw_info['theme']['bg_color'],
				'bgcolor1'			=>	$phpgw_info['theme']['bg_color'],
				'month'				=>	$month,
				'bgcolor2'			=>	$phpgw_info['theme']['cal_dayview'],
				'holiday_color'	=> $this->holiday_color
			);

			$p->set_var($var);

			switch(strtolower($buttons))
			{
				case 'right':
					$var = Array(
						'nextmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ahead).'"><img src="'.$phpgw->common->image('phpgwapi','right.gif').'" border="0"></a>'
					);
					break;
				case 'left':
					$var = Array(
						'prevmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ago).'"><img src="'.$phpgw->common->image('phpgwapi','left.gif').'" border="0"></a>'
					);					
					break;
				case 'both':
					$var = Array(
						'prevmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ago).'"><img src="'.$phpgw->common->image('phpgwapi','left.gif').'" border="0"></a>',
						'nextmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ahead).'"><img src="'.$phpgw->common->image('phpgwapi','right.gif').'" border="0"></a>'
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
				$p->set_var('dayname','<b>' . substr(lang($this->bo->datetime->days[$i]),0,2) . '</b>');
				$p->parse('daynames','mini_day',True);
			}
			$today = date('Ymd',time());
			unset($date);
			for($i=$weekstarttime;date('Ymd',$i)<=$monthend;$i += (24 * 3600 * 7))
			{
				unset($var);
				$daily = $this->bo->set_week_array($i,$cellcolor,$weekly);
				@reset($daily);
				while(list($date,$day_params) = each($daily))
				{
//	echo 'Mini-Cal Date : '.$date."<br>\n";
					$year = intval(substr($date,0,4));
					$month = intval(substr($date,4,2));
					$day = intval(substr($date,6,2));
					$str = '';
					if(($date >= $monthstart && $date <= $monthend) || $outside_month == True)
					{
						if(!$this->printer_friendly)
						{
							$str = '<a href="'.$this->page($link,'&date='.$date).'" class="'.$day_params['class'].'">'.$day.'</a>';
						}
						else
						{
							$str = $day;
						}

					}
					$var[] = Array(
						'day_image'	=> $day_params['day_image'],
						'dayname'	=> $str
					);
				}
				for($l=0;$l<count($var);$l++)
				{
					$this->output_template_array($p,'monthweek_day','mini_day',$var[$l]);
				}
				$p->parse('display_monthweek','mini_week',True);
				$p->set_var('dayname','');
				$p->set_var('monthweek_day','');
			}
		
			$return_value = $p->fp('out','mini_cal');
			unset($p);
			return $return_value;
		}

		function index()
		{
			global $phpgw;

			Header('Location: '. $this->page());
			$phpgw->common->phpgw_exit();
		}

		function month()
		{
			global $phpgw, $thismonth, $thisday, $thisyear;
			
			$this->bo->read_holiday();

			$templates = Array(
				'index_t'	=>	'index.tpl'
			);
	
			$this->template->set_file($templates);

			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			if ($this->printer_friendly == False)
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();
				$this->header();

				$printer = '';
				$param = '&year='.$this->bo->year.'&month='.$this->bo->month.'&friendly=1&filter='.$filter;
				$print = '<a href="'.$this->page('month'.$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
				$minical_prev = $this->mini_calendar(1,$this->bo->month - 1,$this->bo->year,'day');
				$minical_next = $this->mini_calendar(1,$this->bo->month + 1,$this->bo->year,'day');
			}
			else
			{
				$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
				$print =	'';
				if($this->preferences['display_minicals'] == 'Y' || $this->preferences['display_minicals'])
				{
					$minical_prev = $this->mini_calendar(1,$this->bo->month - 1,$this->bo->year,'day');
					$minical_next = $this->mini_calendar(1,$this->bo->month + 1,$this->bo->year,'day');
				}
				else
				{
					$minical_prev = '';
					$minical_next = '';
				}
			}

			$var = Array(
				'printer_friendly'		=>	$printer,
				'bg_text'					=> $phpgw_info['theme']['bg_text'],
				'small_calendar_prev'	=>	$minical_prev,
				'month_identifier'		=>	lang(strftime("%B",$m)) . ' ' . $this->bo->year,
				'username'					=>	$phpgw->common->grab_owner_name($this->owner),
				'small_calendar_next'	=>	$minical_next,
				'large_month'				=>	$this->display_month($this->bo->month,$this->bo->year,True,$this->owner),
				'print'						=>	$print
			);

			$this->template->set_var($var);
			$this->template->pparse('out','index_t');
			if($this->printer_friendly)
			{
				$phpgw->common->phpgw_exit();
			}
			else
			{
				$this->footer();
			}
		}

		function week()
		{

			global $phpgw, $phpgw_info;

			$next = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day + 7,$this->bo->year);
			$prev = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day - 7,$this->bo->year);

			$nextmonth = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month + 1,1,$this->bo->year);
			$prevmonth = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month - 1,1,$this->bo->year);

			$first = $this->bo->datetime->gmtdate($this->bo->datetime->get_weekday_start($this->bo->year, $this->bo->month, $this->bo->day));
			$last = $this->bo->datetime->gmtdate($first['raw'] + 518400);

// Week Label
			$week_id = lang(strftime("%B",$first['raw'])).' '.$first['day'];
			if($first['month'] <> $last['month'] && $first['year'] <> $last['year'])
			{
				$week_id .= ', '.$first['year'];
			}
			$week_id .= ' - ';
			if($first['month'] <> $last['month'])
			{
				$week_id .= lang(strftime("%B",$last['raw'])).' ';
			}
			$week_id .= $last['day'].', '.$last['year'];

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$templates = Array(
				'week_t' => 'week.tpl'
			);
	
			$p->set_file($templates);

			if (!$this->printer_friendly)
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();
				$this->header();
				$printer_header = '';
				$prev_week_link = '<a href="'.$this->page('week','&year='.$prev['year'].'&month='.$prev['month'].'&day='.$prev['day']).'">&lt;&lt;</a>';
				$next_week_link = '<a href="'.$this->page('week','&year='.$next['year'].'&month='.$next['month'].'&day='.$next['day']).'">&gt;&gt;</a>';
				$param = '&year='.$this->bo->year.'&month='.$this->bo->month.'&day='.$this->bo->day.'&friendly=1';
				$printer_friendly = '<a href="'.$this->page('week',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
				$minical_this = $this->mini_calendar($this->bo->day,$this->bo->month,$this->bo->year,'day');
				$minical_prev = $this->mini_calendar(1,$this->bo->month - 1,$this->bo->year,'day');
				$minical_next = $this->mini_calendar(1,$this->bo->month + 1,$this->bo->year,'day');
			}
			else
			{
				$printer_header = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
				$prev_week_link = '&lt;&lt;';
				$next_week_link = '&gt;&gt;';
				$printer_friendly =	'';
				if($this->prefs['display_minicals'] == 'Y' || $this->prefs['display_minicals'])
				{
					$minical_this = $this->mini_calendar($this->bo->day,$this->bo->month,$this->bo->year,'day');
					$minical_prev = $this->mini_calendar(1,$this->bo->month - 1,$this->bo->year,'day');
					$minical_next = $this->mini_calendar(1,$this->bo->month + 1,$this->bo->year,'day');
				}
				else
				{
					$minical_this = '';
					$minical_prev = '';
					$minical_next = '';
				}
			}

			$var = Array(
				'printer_header'			=>	$printer_header,
				'bg_text'					=> $phpgw_info['themem']['bg_text'],
				'small_calendar_prev'	=>	$minical_prev,
				'prev_week_link'			=>	$prev_week_link,
				'small_calendar_this'	=>	$minical_this,
				'week_identifier'			=>	$week_id,
				'next_week_link'			=>	$next_week_link,
				'username'					=>	$phpgw->common->grab_owner_name($this->owner),
				'small_calendar_next'	=>	$minical_next,
				'week_display'				=>	$this->display_weekly($this->bo->day,$this->bo->month,$this->bo->year,true,$this->owner),
				'printer_friendly'		=>	$printer_friendly
			);

			$p->set_var($var);
			$p->pparse('out','week_t');
			flush();
			if($this->printer_friendly)
			{
				$phpgw->common->phpgw_exit();
			}
			else
			{
				$this->footer();
			}
		}

		function year()
		{
			global $phpgw, $phpgw_info;
			
			if ($this->printer_friendly)
			{
				echo '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
			}
			else
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();
				$this->header();
			}
?>

<center>
<table border="0" cellspacing="3" cellpadding="4" cols=4>
 <tr>
<?php
			if(!$this->printer_friendly)
			{
				echo '<td align="left"><a href="'.$this->page('year','&year='.($this->bo->year - 1)).'">&lt;&lt;</a>';
			}
?>
  </td>
  </td>
  <td align="center">
   <font face=\"".$phpgw_info["theme"][font]."\" size="+1"><?php echo $this->bo->year; ?></font>
  </td>
<?php
			if(!$this->printer_friendly)
			{
				echo '<td align="right"><a href="'.$this->page('year','&year='.($this->bo->year + 1)).'">&gt;&gt;</a>';
			}
?>
  </td>
 </tr>
 <tr valign="top">
<?php
			if(!$this->printer_friendly)
			{
				$link = 'day.php';
			}
			else
			{
				$link = '';
			}
		  for($i=1;$i<13;$i++)
		  {
				echo '<td valign="top">';
				echo $this->mini_calendar($i,$i,$this->bo->year,$link,'none',False);
				if($i % 3 == 0)
				{
					echo '</tr><tr valign="top">';
				}
			}
?>
 </tr>
</table>
</center>
<?php
			if($this->printer_friendly)
			{
				$phpgw->common->phpgw_exit();
			}
			else
			{
				echo '&nbsp;<a href="'.$this->page('year','&friendly=1')
					.'" target="cal_printer_friendly" onMouseOver="window.status = '."'"
					.lang('Generate printer-friendly version')."'".'">['.lang('Printer Friendly').']</a>';
				$this->footer();
			}
		}
		
		function view()
		{
			global $phpgw,$phpgw_info,$cal_id,$submit,$referer;

			$phpgw->common->phpgw_header();
			echo parse_navbar();
			$this->header();

			// First, make sure they have permission to this entry
			if ($cal_id < 1)
			{
				echo lang('Invalid entry id.');
				$phpgw->common->phpgw_footer();
				$phpgw->common->phpgw_exit();
			}

			if($this->bo->check_perms(PHPGW_ACL_READ) == False)
			{
				echo lang('You do not have permission to read this record!');
				$phpgw->common->phpgw_footer();
				$phpgw->common->phpgw_exit();    
			}

			$event = $this->bo->read_entry($cal_id);
			
			echo '<center>';

			if(isset($event->id))
			{
				echo $this->view_event($event);

				$thisyear	= $event->start->year;
				$thismonth	= $event->start->month;
				$thisday 	= $event->start->mday;
	
				$p = CreateObject('phpgwapi.Template',$this->template_dir);

				$templates = Array(
					'form_button'	=> 'form_button_script.tpl'
				);
				$p->set_file($templates);

				if ($this->owner == $event->owner && $this->bo->check_perms(PHPGW_ACL_EDIT) == True)
				{
					$var = Array(
						'action_url_button'	=> $this->page('edit','&cal_id='.$cal_id),
						'action_text_button'	=> lang('Edit'),
						'action_confirm_button'	=> '',
						'action_extra_field'	=> ''
					);
					$p->set_var($var);
					echo $p->fp('out','form_button');
				}

				if ($this->owner == $event->owner && $this->bo->check_perms(PHPGW_ACL_DELETE) == True)
				{
					$var = Array(
						'action_url_button'	=> $this->page('delete','&cal_id='.$cal_id),
						'action_text_button'	=> lang('Delete'),
						'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
						'action_extra_field'	=> ''
					);
					$p->set_var($var);
					echo $p->fp('out','form_button');
				}
			}
			else
			{
				echo lang("Sorry, the owner has just deleted this event").'.';
			}
			echo '</center>';
			$this->footer();
		}

		function edit()
		{
			global $phpgw, $phpgw_info, $cal_id, $readsess, $hour, $minute, $cd;
			
			$sb = CreateObject('phpgwapi.sbox');
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
			{
				$hourformat = 'h';
			}
			else
			{
				$hourformat = 'H';
			}

			if ($cal_id > 0)
			{
				$event = $this->bo->read_entry(intval($cal_id));

				$can_edit = False;
		
				if(($event->owner == $this->owner) && ($this->bo->check_perms(PHPGW_ACL_EDIT) == True))
				{
					if($event->public != True)
					{
						if($this->bo->check_perms(PHPGW_ACL_PRIVATE) == True)
						{
							$can_edit = True;
						}
					}
					else
					{
						$can_edit = True;
					}
				}

				if($can_edit == False)
				{
					header('Location: '.$this->page('view','&cal_id='.$cal_id));
				}
			}
			elseif(isset($readsess))
			{
				$event = unserialize(str_replace('O:8:"stdClass"','O:13:"calendar_time"',serialize($phpgw->session->appsession('entry','calendar'))));
		
				if($event->owner == 0)
				{
					$this->so->add_attribute('owner',$this->owner);
				}
		
				$can_edit = True;
			}
			else
			{
				if($this->bo->check_perms(PHPGW_ACL_ADD) == False)
				{
					header('Location: '.$this->page('view','&cal_id='.$cal_id));
				}

				$this->so->event_init();
				$this->so->add_attribute('id',0);

				$can_edit = True;

				if (!isset($hour))
				{
					$thishour = 0;
				}
				else
				{
					$thishour = (int)$hour;
				}
		
				if (!isset($minute))
				{
					$thisminute = 00;
				}
				else
				{
					$thisminute = (int)$minute;
				}

				$this->so->set_start($this->bo->year,$this->bo->month,$this->bo->day,$thishour,$thisminute,0);
				$this->so->set_end($this->bo->year,$this->bo->month,$this->bo->day,$thishour,$thisminute,0);
				$this->so->set_title('');
				$this->so->set_description('');
				$this->so->add_attribute('priority',2);
				if($this->preferences['default_private'] == 'Y' || $this->prefs['default_private'] == True)
				{
					$this->so->set_class(False);
				}
				else
				{
					$this->so->set_class(True);
				}

				$this->so->set_recur_none();
				$event = $this->so->get_cached_event();
			}

			$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->bo->datetime->tz_offset;
			$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->bo->datetime->tz_offset;

			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$templates = Array(
				'edit'	=>	'edit.tpl',
				'form_button'		=>	'form_button_script.tpl'
			);
			$p->set_file($templates);
			$p->set_block('edit','edit_entry','edit_entry');
			$p->set_block('edit','list','list');
			$p->set_block('edit','hr','hr');

			if($cal_id > 0)
			{
				$action = lang('Calendar - Edit');
			}
			else
			{
				$action = lang('Calendar - Add');
			}

			if($cd)
			{
				$errormsg = $phpgw->common->check_code($cd);
			}
			else
			{
				$errormsg = '';
			}

			$common_hidden = '<input type="hidden" name="cal_id" value="'.$event->id.'">'."\n"
								. '<input type="hidden" name="owner" value="'.$owner.'">'."\n";
						
			$vars = Array(
				'font'				=>	$phpgw_info['theme']['font'],
				'bg_color'			=>	$phpgw_info['theme']['bg_text'],
				'calendar_action'	=>	$action,
				'action_url'		=>	$this->page('update'),
				'common_hidden'	=>	$common_hidden,
				'errormsg'			=>	$errormsg
			);
	
			$p->set_var($vars);

// Brief Description
			$var[] = Array(
				'field'	=> lang('Title'),
				'data'	=> '<input name="title" size="25" maxlength="80" value="'.$event->title.'">'
			);

// Full Description
			$var[] = Array(
				'field'	=> lang('Full Description'),
				'data'	=> '<textarea name="description" rows="5" cols="40" wrap="virtual" maxlength="2048">'.$event->description.'</textarea>'
			);

// Display Categories
			$var[] = Array(
				'field'	=> lang('Category'),
				'data'	=> '<select name="category"><option value="">'.lang('Choose the category').'</option>'.$this->cat->formated_list('select','all',$event->category,True).'</select>'
			);

// Date
			$day_html = $sb->getDays('start[mday]',intval($phpgw->common->show_date($start,'d')));
			$month_html = $sb->getMonthText('start[month]',intval($phpgw->common->show_date($start,'n')));
			$year_html = $sb->getYears('start[year]',intval($phpgw->common->show_date($start,'Y')),intval($phpgw->common->show_date($start,'Y')));
			$var[] = Array(
				'field'	=> lang('Start Date'),
				'data'	=> $phpgw->common->dateformatorder($year_html,$month_html,$day_html)
			);

// Time
			$amsel = ' checked'; $pmsel = '';
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
			{
				if ($event->start->hour >= 12)
				{
					$amsel = ''; $pmsel = ' checked';
				}
			}
			$str = '<input name="start[hour]" size="2" VALUE="'.$phpgw->common->show_date($start,$hourformat).'" maxlength="2">:<input name="start[min]" size="2" value="'.$phpgw->common->show_date($start,'i').'" maxlength="2">';
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
			{
				$str .= '<input type="radio" name="start[ampm]" value="am"'.$amsel.'>am';
				$str .= '<input type="radio" name="start[ampm]" value="pm"'.$pmsel.'>pm';
			}
			$var[] = Array(
				'field'	=> lang('Start Time'),
				'data'	=> $str
			);

// End Date
			$day_html = $sb->getDays('end[mday]',intval($phpgw->common->show_date($end,'d')));
			$month_html = $sb->getMonthText('end[month]',intval($phpgw->common->show_date($end,'n')));
			$year_html = $sb->getYears('end[year]',intval($phpgw->common->show_date($end,'Y')),intval($phpgw->common->show_date($end,'Y')));
			$var[] = Array(
				'field'	=> lang('End Date'),
				'data'	=> $phpgw->common->dateformatorder($year_html,$month_html,$day_html)
			);

// End Time
			$amsel = ' checked'; $pmsel = '';
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
			{
				if ($event->end->hour >= 12)
				{
					$amsel = ''; $pmsel = ' checked';
				}
			}

			$str = '<input name="end[hour]" size="2" VALUE="'.$phpgw->common->show_date($end,$hourformat).'" maxlength="2">:<input name="end[min]" size="2" value="'.$phpgw->common->show_date($end,'i').'" maxlength="2">';
			if ($phpgw_info['user']['preferences']['common']['timeformat'] == '12')
			{
				$str .= '<input type="radio" name="end[ampm]" value="am"'.$amsel.'>am';
				$str .= '<input type="radio" name="end[ampm]" value="pm"'.$pmsel.'>pm';
			}
			$var[] = Array(
				'field'	=> lang("End Time"),
				'data'	=> $str
			);

// Priority
			$var[] = Array(
				'field'	=> lang('Priority'),
				'data'	=> $sb->getPriority('priority',$event->priority)
			);

// Access
			$str = '<input type="checkbox" name="private" value="private"';
			if($event->public != True)
			{
				$str .= ' checked';
			}
			$str .= '>';
			$var[] = Array(
				'field'	=> lang('Private'),
				'data'	=> $str
			);

			function build_part_list(&$users,$accounts,$owner)
			{
				global $phpgw;
				if($accounts == False)
				{
					return;
				}
				while(list($index,$id) = each($accounts))
				{
					if(intval($id) == $owner)
					{
						continue;
					}
					if(!isset($users[intval($id)]))
					{
						if($phpgw->accounts->exists(intval($id)) == True)
						{
							$users[intval($id)] = $phpgw->common->grab_owner_name(intval($id));
						}
						if($phpgw->accounts->get_type(intval($id)) == 'g')
						{
							build_part_list($users,$phpgw->acl->get_ids_for_location(intval($id),1,'phpgw_group'),$owner);
						}
					}
				}
			}

// Participants
			$accounts = $phpgw->acl->get_ids_for_location('run',1,'calendar');
			$users = Array();
			build_part_list($users,$accounts,$owner);
			while(list($key,$status) = each($event->participants))
			{
				$parts[$key] = ' selected';
			}
    
			$str = "\n".'   <select name="participants[]" multiple size="5">'."\n";
			@asort($users);
			@reset($users);
			$user = Array();
			while (list($id,$name) = each($users))
			{
				if(intval($id) == intval($owner))
				{
					continue;
				}
				else
				{
					$str .= '    <option value="' . $id . '"'.$parts[$id].'>('.$phpgw->accounts->get_type($id).') '.$name.'</option>'."\n";
				}
			}
			$str .= '   </select>';
			$var[] = Array(
				'field'	=> lang('Participants'),
				'data'	=> $str
			);

// I Participate
			$str = '<input type="checkbox" name="participants[]" value="'.$owner.'"';
			if((($id > 0) && isset($event->participants[$owner])) || !isset($id))
			{
				$str .= ' checked';
			}
			$str .= '>';
			$var[] = Array(
				'field'	=> $phpgw->common->grab_owner_name($owner).' '.lang('Participates'),
				'data'	=> $str
			);

			for($i=0;$i<count($var);$i++)
			{
				$this->output_template_array($p,'row','list',$var[$i]);
			}

			unset($var);

// Repeat Type
			$p->set_var('hr_text','<hr>');
			$p->parse('row','hr',True);
			$p->set_var('hr_text','<center><b>'.lang('Repeating Event Information').'</b></center><br>');
			$p->parse('row','hr',True);
			$str = '<select name="recur_type">';
			$rpt_type = Array(
				MCAL_RECUR_NONE,
				MCAL_RECUR_DAILY,
				MCAL_RECUR_WEEKLY,
				MCAL_RECUR_MONTHLY_WDAY,
				MCAL_RECUR_MONTHLY_MDAY,
				MCAL_RECUR_YEARLY
			);
			$rpt_type_out = Array(
				MCAL_RECUR_NONE => 'None',
				MCAL_RECUR_DAILY => 'Daily',
				MCAL_RECUR_WEEKLY => 'Weekly',
				MCAL_RECUR_MONTHLY_WDAY => 'Monthly (by day)',
				MCAL_RECUR_MONTHLY_MDAY => 'Monthly (by date)',
				MCAL_RECUR_YEARLY => 'Yearly'
			);
			for($l=0;$l<count($rpt_type);$l++)
			{
				$str .= '<option value="'.$rpt_type[$l].'"';
				if($event->recur_type == $rpt_type[$l])
				{
					$str .= ' selected';
				}
				$str .= '>'.lang($rpt_type_out[$rpt_type[$l]]).'</option>';
			}
			$str .= '</select>';
			$var[] = Array(
				'field'	=> lang('Repeat Type'),
				'data'	=> $str
			);

			$str = '<input type="checkbox" name="rpt_use_end" value="y"';

			if($event->recur_enddate->year != 0 && $event->recur_enddate->month != 0 && $event->recur_enddate->mday != 0)
			{
				$str .= ' checked';
				$recur_end = mktime($event->recur_enddate->hour,$event->recur_enddate->min,$event->recur_enddate->sec,$event->recur_enddate->month,$event->recur_enddate->mday,$event->recur_enddate->year) - $this->bo->datetime->tz_offset;
			}
			else
			{
				$recur_end = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) + 86400 - $this->bo->datetime->tz_offset;
			}
	
			$str .= '>'.lang('Use End Date').'  ';

			$day_html = $sb->getDays('recur_enddate[mday]',intval($phpgw->common->show_date($recur_end,'d')));
			$month_html = $sb->getMonthText('recur_enddate[month]',intval($phpgw->common->show_date($recur_end,'n')));
			$year_html = $sb->getYears('recur_enddate[year]',intval($phpgw->common->show_date($recur_end,'Y')),intval($phpgw->common->show_date($recur_end,'Y')));
			$str .= $phpgw->common->dateformatorder($year_html,$month_html,$day_html);

			$var[] = Array(
				'field'	=> lang('Repeat End Date'),
				'data'	=> $str
			);

			$str  = '<input type="checkbox" name="cal[rpt_sun]" value="'.MCAL_M_SUNDAY.'"'.(($event->recur_data & MCAL_M_SUNDAY) ?' checked':'').'> '.lang('Sunday').' ';
			$str .= '<input type="checkbox" name="cal[rpt_mon]" value="'.MCAL_M_MONDAY.'"'.(($event->recur_data & MCAL_M_MONDAY) ?' checked':'').'> '.lang('Monday').' ';
			$str .= '<input type="checkbox" name="cal[rpt_tue]" value="'.MCAL_M_TUESDAY.'"'.(($event->recur_data & MCAL_M_TUESDAY) ?' checked':'').'> '.lang('Tuesday').' ';
			$str .= '<input type="checkbox" name="cal[rpt_wed]" value="'.MCAL_M_WEDNESDAY.'"'.(($event->recur_data & MCAL_M_WEDNESDAY) ?' checked':'').'> '.lang('Wednesday').' <br>';
			$str .= '<input type="checkbox" name="cal[rpt_thu]" value="'.MCAL_M_THURSDAY.'"'.(($event->recur_data & MCAL_M_THURSDAY) ?' checked':'').'> '.lang('Thursday').' ';
			$str .= '<input type="checkbox" name="cal[rpt_fri]" value="'.MCAL_M_FRIDAY.'"'.(($event->recur_data & MCAL_M_FRIDAY) ?' checked':'').'> '.lang('Friday').' ';
			$str .= '<input type="checkbox" name="cal[rpt_sat]" value="'.MCAL_M_SATURDAY.'"'.(($event->recur_data & MCAL_M_SATURDAY) ?' checked':'').'> '.lang('Saturday').' ';

			$var[] = Array(
				'field'	=> lang('Repeat Day').'<br>'.lang('(for weekly)'),
				'data'	=> $str
			);

			$var[] = Array(
				'field'	=> lang('Frequency'),
				'data'	=> '<input name="recur_interval" size="4" maxlength="4" value="'.$event->recur_interval.'">'
			);

			for($i=0;$i<count($var);$i++)
			{
				$this->output_template_array($p,'row','list',$var[$i]);
			}
			
			$p->set_var('submit_button',lang('Submit'));

			if ($cal_id > 0)
			{
				$action_url_button = $this->page('delete','&cal_id='.$cal_id);
				$action_text_button = lang('Delete');
				$action_confirm_button = "onClick=\"return confirm('".lang("Are you sure\\nyou want to \\ndelete this entry?\\n\\nThis will delete\\nthis entry for all users.")."')\"";
				$var = Array(
					'action_url_button'	=> $action_url_button,
					'action_text_button'	=> $action_text_button,
					'action_confirm_button'	=> $action_confirm_button,
					'action_extra_field'	=> ''
				);
				$p->set_var($var);
				$p->parse('delete_button','form_button');
			}
			else
			{
				$p->set_var('delete_button','');
			}
			$p->pparse('out','edit_entry');
			$this->footer();
		}

		/* Private functions */

		function _debug_sqsof()
		{
			$data = array(
				'filter' => $this->filter,
				'cat_id' => $this->cat_id,
				'owner'	=> $this->owner,
				'year'	=> $this->bo->year,
				'month'	=> $this->bo->month,
				'day'		=> $this->bo->day
			);
			echo '<br>UI:';
			_debug_array($data);
		}

		/* Called only by get_list(), just prior to page footer. */
		function save_sessiondata()
		{
			$data = array(
				'filter' => $this->filter,
				'cat_id' => $this->cat_id,
				'owner'	=> $this->owner,
				'year'	=> $this->bo->year,
				'month'	=> $this->bo->month,
				'day'		=> $this->bo->day
			);
			$this->bo->save_sessiondata($data);
		}

		function output_template_array(&$p,$row,$list,$var)
		{
			$p->set_var($var);
			$p->parse($row,$list,True);
		}

		function page($page='',$params='')
		{
			global $phpgw, $phpgw_info;

			if($page == '')
			{
				$page_ = explode('.',$this->prefs['defaultcalendar']);
				$page = $page_[0];
				if ($page=='index' || ($page != 'day' && $page != 'week' && $page != 'month' && $page != 'year'))
				{
					$page = 'month';
					$phpgw->preferences->add('calendar','defaultcalendar','month');
					$phpgw->preferences->save_repository();
				}
			}
			return $phpgw->link('/index.php','menuaction='.$phpgw_info['flags']['currentapp'].'.ui'.$phpgw_info['flags']['currentapp'].'.'.$page.$params);
		}

		function header()
		{
			if (floor(phpversion()) == 4)
			{
				global $date, $year, $month, $day, $thisyear, $thismonth, $thisday, $filter, $keywords;
				global $matrixtype, $participants, $owner, $phpgw, $grants, $rights, $SCRIPT_FILENAME, $remainder, $tpl;
			}

			$cols = 8;
			if($this->bo->check_perms(PHPGW_ACL_PRIVATE) == True)
			{
				$cols++;
			}
	
			$tpl = CreateObject('phpgwapi.Template',$this->template_dir);
			$tpl->set_unknowns('remove');

			include($this->template_dir.'/header.inc.php');
			flush();
		}

		function footer()
		{
			global $phpgw;
		
			if (@$this->printer_friendly)
			{
				$phpgw->common->phpgw_footer();
				$phpgw->common->phpgw_exit();
			}

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
	
			$templates = Array(
				'footer'	=>	'footer.tpl'
			);

			$p->set_file($templates);
			$p->set_block('footer','footer_table','footer_table');
			$p->set_block('footer','footer_row','footer_row');

			$m = $this->bo->month;
			$y = $this->bo->year;

			$d_time = mktime(0,0,0,$m,1,$y);
			$thisdate = date('Ymd', $d_time);
			$y--;

			$str = '';

			for ($i = 0; $i < 25; $i++)
			{
				$m++;
				if ($m > 12)
				{
					$m = 1;
					$y++;
				}
				$d = mktime(0,0,0,$m,1,$y);
				$str .= '<option value="' . date('Ymd', $d) . '"';
				if (date('Ymd', $d) == $thisdate)
				{
					$str .= ' selected';
				}
				$str .= '>'.lang(date('F', $d)).strftime(' %Y', $d).'</option>'."\n";
			}

			$var = Array(
				'action_url'		=>	$this->page('month',''),
				'form_name'			=>	'SelectMonth',
				'label'				=>	lang('Month'),
				'form_label'		=>	'date',
				'form_onchange'	=>	'document.SelectMonth.submit()',
				'row'					=>	$str,
				'go'					=>	lang('Go!')
			);

			$this->output_template_array($p,'table_row','footer_row',$var);

			$str = '';

			$y = $this->bo->year;
			$m = $this->bo->month;
			$d = $this->bo->day;

			unset($thisdate);
	
			$thisdate = $this->bo->datetime->makegmttime(0,0,0,$m,$d,$y);
			$sun = $this->bo->datetime->get_weekday_start($y,$m,$d) - $this->bo->datetime->tz_offset - 7200;

			$str = '';

			for ($i = -7; $i <= 7; $i++)
			{
				$begin = $sun + (3600 * 24 * 7 * $i);
				$end = $begin + (3600 * 24 * 6);
				$str .= '<option value="' . $phpgw->common->show_date($begin,'Ymd') . '"';
				if ($begin <= $thisdate['raw'] && $end >= $thisdate['raw'])
				{
					$str .= ' selected';
				}
				$str .= '>' . lang($phpgw->common->show_date($begin,'F')) . ' ' . $phpgw->common->show_date($begin,'d') . '-'
					. lang($phpgw->common->show_date($end,'F')) . ' ' . $phpgw->common->show_date($end,'d') . '</option>'."\n";
			}
 
			$var = Array(
				'action_url'		=>	$this->page('week',''),
				'form_name'			=>	'SelectWeek',
				'label'				=>	lang('Week'),
				'form_label'		=>	'date',
				'form_onchange'	=>	'document.SelectWeek.submit()',
				'row'					=>	$str,
				'go'					=>	lang('Go!')
			);

			$this->output_template_array($p,'table_row','footer_row',$var);

			$str = '';
			for ($i = ($y - 3); $i < ($y + 3); $i++)
			{
				$str .= '<option value="'.$i.'"';
				if ($i == $y)
				{
					$str .= ' selected';
				}
				$str .= '>'.$i.'</option>'."\n";
			}
  
			$var = Array(
				'action_url'		=>	$this->page('year',''),
				'form_name'			=>	'SelectYear',
				'label'				=>	lang('Year'),
				'form_label'		=>	'year',
				'form_onchange'	=>	'document.SelectYear.submit()',
				'row'					=>	$str,
				'go'					=>	lang('Go!')
			);

			$this->output_template_array($p,'table_row','footer_row',$var);

			$p->pparse('out','footer_table');
			unset($p);
		}

		function link_to_entry($event,$month,$day,$year)
		{
			global $phpgw, $phpgw_info, $grants;

			$str = '';
			$is_private = $this->bo->is_private($event,$this->owner);
			$editable = ((!$this->printer_friendly) && (($is_private && $this->bo->check_perms(PHPGW_ACL_PRIVATE)) || !$is_private));
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
			$description = $this->bo->get_short_field($event,$is_private,'description');

			$starttime = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->bo->datetime->tz_offset;
			$endtime = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->bo->datetime->tz_offset;
			$rawdate = mktime(0,0,0,$month,$day,$year);
			$rawdate_offset = $rawdate - $this->bo->datetime->tz_offset;
			$nextday = mktime(0,0,0,$month,$day + 1,$year) - $this->bo->datetime->tz_offset;
			if (intval($phpgw->common->show_date($starttime,'Hi')) && $starttime == $endtime)
			{
				$time = $phpgw->common->show_date($starttime,$this->users_timeformat);
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
					$end_time = $phpgw->common->show_date(mktime(23,59,59,$month,$day,$year) - $this->bo->datetime->tz_offset,$this->users_timeformat);
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
			$text = '<font size="-2" face="'.$phpgw_info['theme']['font'].'"><nobr>'.$time.'</nobr>&nbsp;'.$this->bo->get_short_field($event,$is_private,'title');
			if(!$is_private)
			{
				$text .= $this->bo->display_status($event->users_status);
			}
			$text .= '</font>'.$phpgw->browser->br;

		
			if ($editable)
			{
				$p->set_var('link_link',$this->page('view','&cal_id='.$event->id));
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
					$this->output_template_array($p,'picture','pict',$var);
				}
			}
			if ($text)
			{
				$var = Array(
					'text' => $text
				);
				$this->output_template_array($p,'picture','link_text',$var);
			}

			if ($editable)
			{
				$p->parse('picture','link_close',True);
			}
			$str = $p->fp('out','link_pict');
			unset($p);
			return $str;
		}

		function week_header($month,$year,$display_name = False)
		{
			global $phpgw_info;

			$this->weekstarttime = $this->bo->datetime->get_weekday_start($year,$month,1);

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
			if($this->printer_friendly && @$this->preferences['print_black_white'])
			{
				$var = Array(
					'bgcolor'		=> '',
					'font_color'	=> ''
				);
			}
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
				$p->set_var('col_title',lang($this->bo->datetime->days[$i]));
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
			$gr_events = CreateObject('calendar.calendar_item');
			$lr_events = CreateObject('calendar.calendar_item');
			$today = date('Ymd',time());
			$daily = $this->bo->set_week_array($startdate,$cellcolor,$weekly);
			@reset($daily);
			while(list($date,$day_params) = each($daily))
			{
				$year = intval(substr($date,0,4));
				$month = intval(substr($date,4,2));
				$day = intval(substr($date,6,2));
				$var = Array(
					'column_data'	=>	'',
					'extra'		=>	''
				);
				$p->set_var($var);
				if ($weekly || ($date >= $monthstart && $date <= $monthend))
				{
					if ($day_params['new_event'])
					{
						$new_event_link = '<a href="'.$this->page('edit','&year='.$year.'&month='.$month.'&day='.$day).'">'
							. '<img src="'.$phpgw->common->image('calendar','new.gif').'" width="10" height="10" alt="'.lang('New Entry').'" border="0" align="center">'
							. '</a>';
						$day_number = '<a href="'.$this->page('day','&month='.$month.'&day='.$day.'&year='.$year).'">'.$day.'</a>';
					}
					else
					{
						$new_event_link = '';
						$day_number = $day;
					}

					$var = Array(
						'extra'		=>	$day_params['extra'],
						'new_event_link'	=> $new_event_link,
						'day_number'		=>	$day_number
					);

					$p->set_var($var);
				
					if($day_params['holidays'])
					{
						reset($day_params['holidays']);
						while(list($key,$value) = each($day_params['holidays']))
						{
							$var = Array(
								'day_events' => '<font face="'.$phpgw_info['theme']['font'].'" size="-1">'.$value.'</font>'.$phpgw->browser->br
							);
							$this->output_template_array($p,'daily_events','event',$var);
						}
					}

					if($day_params['appts'])
					{
						$lr_events = CreateObject('calendar.calendar_item');
						$var = Array(
							'week_day_font_size'	=>	'2',
							'events'		=>	''
						);
						$p->set_var($var);
						$rep_events = $this->bo->cached_events[$date];
						for ($k=0;$k<count($rep_events);$k++)
						{
							$lr_events = $rep_events[$k];
							$p->set_var('day_events',$this->link_to_entry($lr_events,$month,$day,$year));
							$p->parse('events','event',True);
							$p->set_var('day_events','');
						}
					}
					$p->parse('daily_events','day_event',True);
					$p->parse('column_data','month_daily',True);
					$p->set_var('daily_events','');
					$p->set_var('events','');
					if($day_params['week'])
					{
						if(!$this->printer_friendly)
						{
							$str = '<a href="'.$this->page('week','&date='.$date).'">' .$day_params['week'].'</a>';
						}
						else
						{
							$str = $day_params['week'];
						}
						$var = Array(
							'week_day_font_size'	=>	'-2',
							'events'					=> $str
						);
						$this->output_template_array($p,'column_data','day_event',$var);
						$p->set_var('events','');
					}
				}
				$p->parse('column_header','month_column',True);
				$p->set_var('column_data','');
			}
			$this->owner = $temp_owner;
			return $p->fp('out','monthly_header');
		}
		
		function display_month($month,$year,$showyear,$owner=0)
		{
			global $phpgw, $phpgw_info;

//			if($owner == $phpgw_info['user']['account_id'])
//			{
//				$owner = 0;
//			}

			$this->bo->store_to_cache($year,$month,1);

			$monthstart = intval(date('Ymd',mktime(0,0,0,$month    ,1,$year)));
			$monthend   = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

			$start = $this->bo->datetime->get_weekday_start($year, $month, 1);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('keep');
		
			$templates = Array(
				'week'			=>	'month_day.tpl'
			);
			$p->set_file($templates);
			$p->set_block('week','m_w_table','m_w_table');
			$p->set_block('week','event','event');

			$p->set_var('cols','7');
			$p->set_var('day_events',$this->week_header($month,$year,False));
			$p->parse('row','event',True);

			$cellcolor = $phpgw_info['theme']['row_on'];

			for ($i=intval($start);intval(date('Ymd',$i)) <= $monthend;$i += 604800)
			{
				$cellcolor = $phpgw->nextmatchs->alternate_row_color($cellcolor);
				$var = Array(
					'day_events' => $this->display_week($i,False,$cellcolor,False,$owner,$monthstart,$monthend)
				);
				$this->output_template_array($p,'row','event',$var);
			}
			return $p->fp('out','m_w_table');
		}

		function display_weekly($day,$month,$year,$showyear,$owners=0)
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
		
			$start = $this->bo->datetime->get_weekday_start($year, $month, $day);

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
			$p->set_var('day_events',$this->week_header($month,$year,$display_name));
			$p->parse('row','event',True);

			$original_owner = $this->so->owner;
			for($i=0;$i<$counter;$i++)
			{
				$this->so->owner = $owners_array[$i];
				$this->bo->store_to_cache($year,$month,1);
				$p->set_var('day_events',$this->display_week($start,True,$cellcolor,$display_name,$owners_array[$i]));
				$p->parse('row','event',True);
			}
			$this->so->owner = $original_owner;
			$this->printer_friendly = $true_printer_friendly;
			return $p->fp('out','m_w_table');
		}

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
			unset($var);

			// Some browser add a \n when its entered in the database. Not a big deal
			// this will be printed even though its not needed.
			if (nl2br($event->description))
			{
				$var[] = Array(
					'field'	=>	lang('Description'),
					'data'	=>	nl2br($event->description)
				);
			}

			if ($event->category)
			{
				$this->cat->categories($this->owner,'calendar');
				$cat = $this->cat->return_single($event->category);
				$var[] = Array(
					'field'	=>	lang('Category'),
					'data'	=>	$cat[0]['name']
				);
			}

			$var[] = Array(
				'field'	=>	lang('Start Date/Time'),
				'data'	=>	$phpgw->common->show_date(mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->bo->datetime->tz_offset)
			);
	
			$var[] = Array(
				'field'	=>	lang('End Date/Time'),
				'data'	=>	$phpgw->common->show_date(mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->bo->datetime->tz_offset)
			);

			$var[] = Array(
				'field'	=>	lang('Priority'),
				'data'	=>	$pri[$event->priority]
			);

			$var[] = Array(
				'field'	=>	lang('Created By'),
				'data'	=>	$phpgw->common->grab_owner_name($event->owner)
			);
	
			$var[] = Array(
				'field'	=>	lang('Updated'),
				'data'	=>	$phpgw->common->show_date(mktime($event->mod->hour,$event->mod->min,$event->mod->sec,$event->mod->month,$event->mod->mday,$event->mod->year) - $this->bo->datetime->tz_offset)
			);

			$var[] = Array(
				'field'	=>	lang('Private'),
				'data'	=>	$event->public==True?'False':'True'
			);

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
	
				$var[] = Array(
					'field'	=>	lang('Groups'),
					'data'	=>	$cal_grps
				);
			}

			$str = '';
			reset($event->participants);
			while (list($user,$short_status) = each($event->participants))
			{
				if($phpgw->accounts->exists($user))
				{
					if($str)
					{
						$str .= '<br>';
					}

					$long_status = $this->bo->get_long_status($short_status);
		
					$str .= $phpgw->common->grab_owner_name($user).' (';
			
					if($this->bo->check_perms(PHPGW_ACL_EDIT,$user) == True)
					{
						$str .= '<a href="'.$this->page('edit_status','&cal_id='.$event->id.'&owner='.$user).'">'.$long_status.'</a>';
					}
					else
					{
						$str .= $long_status;
					}
					$str .= ')'."\n";
				}
			}
			$var[] = Array(
				'field'	=>	lang('Participants'),
				'data'	=>	$str
			);

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
						$recur_end -= $this->bo->datetime->tz_offset;
						$str_extra .= lang('ends').': '.lang($phpgw->common->show_date($recur_end,'l'));
						$str_extra .= ', '.lang($phpgw->common->show_date($recur_end,'F'));
						$str_extra .= ' '.$phpgw->common->show_date($recur_end,'d, Y').' ';
					}
				}
				if($event->recur_type == MCAL_RECUR_WEEKLY || $event->recur_type == MCAL_RECUR_DAILY)
				{
					$repeat_days = '';
					if($this->prefs['weekdaystarts'] == 'Sunday')
					{
						if (!!($event->recur_data & MCAL_M_SUNDAY) == True)
						{
							$this->view_add_day(lang('Sunday'),$repeat_days);
						}
					}
					if (!!($event->recur_data & MCAL_M_MONDAY) == True)
					{
						$this->view_add_day(lang('Monday'),$repeat_days);
					}
					if (!!($event->recur_data & MCAL_M_TUESDAY) == True)
					{
						$this->view_add_day(lang('Tuesday'),$repeat_days);
					}
					if (!!($event->recur_data & MCAL_M_WEDNESDAY) == True)
					{
						$this->view_add_day(lang('Wednesday'),$repeat_days);
					}
					if (!!($event->recur_data & MCAL_M_THURSDAY) == True)
					{
						$this->view_add_day(lang('Thursday'),$repeat_days);
					}
					if (!!($event->recur_data & MCAL_M_FRIDAY) == True)
					{
						$this->view_add_day(lang('Friday'),$repeat_days);
					}
					if (!!($event->recur_data & MCAL_M_SATURDAY) == True)
					{
						$this->view_add_day(lang('Saturday'),$repeat_days);
					}
					if($phpgw_info['user']['preferences']['calendar']['weekdaystarts'] == 'Monday')
					{
						if (!!($event->recur_data & MCAL_M_SUNDAY) == True)
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

				$var[] = Array(
					'field'	=>	lang('Repetition'),
					'data'	=>	$str
				);
			}

			for($i=0;$i<count($var);$i++)
			{
				$this->output_template_array($p,'row','list',$var[$i]);
			}

			return $p->fp('out','view_event');
		}
	}
?>
