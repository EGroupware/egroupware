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

		var $bo;
		var $cat;

		var $holidays;
		var $holiday_color;
		
		var $debug = False;

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
			'update' => True,
			'delete' => True,
			'preferences' => True,
			'day' => True,
			'edit_status' => True,
			'set_action' => True,
			'header' => True,
			'footer' => True
		);

		function uicalendar()
		{
			global $phpgw, $phpgw_info;

			$phpgw->browser    = CreateObject('phpgwapi.browser');

			$this->bo = CreateObject('calendar.bocalendar',1);

			if($this->debug)
			{
				echo "BO Owner : ".$this->bo->owner."<br>\n";
			}


			$this->template = $phpgw->template;
			$this->template_dir = $phpgw->common->get_tpl_dir('calendar');
			$this->cat      = CreateObject('phpgwapi.categories');

			$this->holiday_color = (substr($phpgw_info['theme']['bg07'],0,1)=='#'?'':'#').$phpgw_info['theme']['bg07'];
			
			$this->cat_id   = $this->bo->cat_id;

			if($this->bo->use_session)
			{
				$this->save_sessiondata();
			}

			if($this->debug)
			{
				$this->_debug_sqsof();
			}
		}

		/* Public functions */

		function mini_calendar($day,$month,$year,$link='',$buttons="none",$outside_month=True)
		{
			global $phpgw, $phpgw_info;

			$this->bo->read_holidays();

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

			if($this->bo->printer_friendly == False)
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
						if(!$this->bo->printer_friendly)
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
			$phpgw_info['flags']['nofooter'] = True;
			$phpgw->common->phpgw_exit();
		}

		function month()
		{
			global $phpgw, $phpgw_info;
			
			$this->bo->read_holidays();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');

			$templates = Array(
				'index_t'	=>	'index.tpl'
			);
	
			$p->set_file($templates);

			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			if (!$this->bo->printer_friendly)
			{
				unset($phpgw_info['flags']['noheader']);
				unset($phpgw_info['flags']['nonavbar']);
				$phpgw->common->phpgw_header();
				$printer = '';
				$param = '&year='.$this->bo->year.'&month='.$this->bo->month.'&friendly=1';
				$print = '<a href="'.$this->page('month'.$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
				$minical_prev = $this->mini_calendar(1,$this->bo->month - 1,$this->bo->year,'day');
				$minical_next = $this->mini_calendar(1,$this->bo->month + 1,$this->bo->year,'day');
			}
			else
			{
				$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
				$print =	'';
				if($this->bo->prefs['calendar']['display_minicals'] == 'Y' || $this->bo->prefs['calendar']['display_minicals'])
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
				'username'					=>	$phpgw->common->grab_owner_name($this->bo->owner),
				'small_calendar_next'	=>	$minical_next,
				'large_month'				=>	$this->display_month($this->bo->month,$this->bo->year,True,$this->bo->owner),
				'print'						=>	$print
			);

			$p->set_var($var);
			$p->pparse('out','index_t');
			if($this->bo->printer_friendly)
			{
				$phpgw_info['flags']['nofooter'] = True;
			}
		}

		function week()
		{

			global $phpgw, $phpgw_info;

			$this->bo->read_holidays();

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

			if (!$this->bo->printer_friendly)
			{
				unset($phpgw_info['flags']['noheader']);
				unset($phpgw_info['flags']['nonavbar']);
				$phpgw->common->phpgw_header();
				$printer = '';
				$prev_week_link = '<a href="'.$this->page('week','&year='.$prev['year'].'&month='.$prev['month'].'&day='.$prev['day']).'">&lt;&lt;</a>';
				$next_week_link = '<a href="'.$this->page('week','&year='.$next['year'].'&month='.$next['month'].'&day='.$next['day']).'">&gt;&gt;</a>';
				$param = '&year='.$this->bo->year.'&month='.$this->bo->month.'&day='.$this->bo->day.'&friendly=1';
				$print = '<a href="'.$this->page('week',$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
				$minical_this = $this->mini_calendar($this->bo->day,$this->bo->month,$this->bo->year,'day','none',False);
				$minical_prev = $this->mini_calendar(1,$this->bo->month - 1,$this->bo->year,'day','left',False);
				$minical_next = $this->mini_calendar(1,$this->bo->month + 1,$this->bo->year,'day','right',False);
			}
			else
			{
				$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
				$prev_week_link = '&lt;&lt;';
				$next_week_link = '&gt;&gt;';
				$print =	'';
				if($this->bo->prefs['calendar']['display_minicals'] == 'Y' || $this->bo->prefs['calendar']['display_minicals'])
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
				'printer_friendly'		=>	$printer,
				'bg_text'					=> $phpgw_info['themem']['bg_text'],
				'small_calendar_prev'	=>	$minical_prev,
				'prev_week_link'			=>	$prev_week_link,
				'small_calendar_this'	=>	$minical_this,
				'week_identifier'			=>	$week_id,
				'next_week_link'			=>	$next_week_link,
				'username'					=>	$phpgw->common->grab_owner_name($this->bo->owner),
				'small_calendar_next'	=>	$minical_next,
				'week_display'				=>	$this->display_weekly($this->bo->day,$this->bo->month,$this->bo->year,true,$this->bo->owner),
				'print'						=>	$print
			);

			$p->set_var($var);
			$p->pparse('out','week_t');
			flush();
			if($this->bo->printer_friendly)
			{
				$phpgw_info['flags']['nofooter'] = True;
			}
		}

		function year()
		{
			global $phpgw, $phpgw_info;
			
			if ($this->bo->printer_friendly)
			{
				echo '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
			}
			else
			{
				unset($phpgw_info['flags']['noheader']);
				unset($phpgw_info['flags']['nonavbar']);
				$phpgw->common->phpgw_header();
			}
?>

<center>
<table border="0" cellspacing="3" cellpadding="4" cols=4>
 <tr>
<?php
			if(!$this->bo->printer_friendly)
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
			if(!$this->bo->printer_friendly)
			{
				echo '<td align="right"><a href="'.$this->page('year','&year='.($this->bo->year + 1)).'">&gt;&gt;</a>';
			}
?>
  </td>
 </tr>
 <tr valign="top">
<?php
			if(!$this->bo->printer_friendly)
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
			if($this->bo->printer_friendly)
			{
				$phpgw_info['flags']['nofooter'] = True;
			}
			else
			{
				echo '&nbsp;<a href="'.$this->page('year','&friendly=1')
					.'" target="cal_printer_friendly" onMouseOver="window.status = '."'"
					.lang('Generate printer-friendly version')."'".'">['.lang('Printer Friendly').']</a>';
			}
		}
		
		function view()
		{
			global $phpgw,$phpgw_info,$cal_id,$submit,$referer;

			unset($phpgw_info['flags']['noheader']);
			unset($phpgw_info['flags']['nonavbar']);
			$phpgw->common->phpgw_header();

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

				if ($this->bo->owner == $event->owner && $this->bo->check_perms(PHPGW_ACL_EDIT) == True)
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

				if ($this->bo->owner == $event->owner && $this->bo->check_perms(PHPGW_ACL_DELETE) == True)
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
		}

		function edit()
		{
			global $phpgw, $phpgw_info, $cal_id, $readsess, $hour, $minute, $cd;
			
			$sb = CreateObject('phpgwapi.sbox');
			if ($this->bo->prefs['common']['timeformat'] == '12')
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
				
				$can_edit = $this->bo->can_user_edit($event);
				
				if($can_edit == False)
				{
					header('Location: '.$this->page('view','&cal_id='.$cal_id));
				}
			}
			elseif(isset($readsess))
			{
				$event = $this->bo->restore_from_appsession;
		
				if($event->owner == 0)
				{
					$this->bo->add_attribute('owner',$this->bo->owner);
				}
		
				$can_edit = True;
			}
			else
			{
				if(!$this->bo->check_perms(PHPGW_ACL_ADD))
				{
					header('Location: '.$this->page('view','&cal_id='.$cal_id));
				}

				$this->bo->event_init();
				$this->bo->add_attribute('id',0);

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

				$this->bo->set_start($this->bo->year,$this->bo->month,$this->bo->day,$thishour,$thisminute,0);
				$this->bo->set_end($this->bo->year,$this->bo->month,$this->bo->day,$thishour,$thisminute,0);
				$this->bo->set_title('');
				$this->bo->set_description('');
				$this->bo->add_attribute('priority',2);
				if($this->bo->prefs['calendar']['default_private'] == 'Y' || $this->bo->prefs['calendar']['default_private'] == True)
				{
					$this->bo->set_class(False);
				}
				else
				{
					$this->bo->set_class(True);
				}

				$this->bo->set_recur_none();
				$event = $this->bo->get_cached_event();
			}

			$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->bo->datetime->tz_offset;
			$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->bo->datetime->tz_offset;

			unset($phpgw_info['flags']['noheader']);
			unset($phpgw_info['flags']['nonavbar']);
			$phpgw->common->phpgw_header();

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

			$common_hidden = '<input type="hidden" name="cal[id]" value="'.$event->id.'">'."\n"
								. '<input type="hidden" name="cal[owner]" value="'.$this->bo->owner.'">'."\n";
						
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
				'data'	=> '<input name="cal[title]" size="25" maxlength="80" value="'.$event->title.'">'
			);

// Full Description
			$var[] = Array(
				'field'	=> lang('Full Description'),
				'data'	=> '<textarea name="cal[description]" rows="5" cols="40" wrap="virtual" maxlength="2048">'.$event->description.'</textarea>'
			);

// Display Categories
			$var[] = Array(
				'field'	=> lang('Category'),
				'data'	=> '<select name="cal[category]"><option value="">'.lang('Choose the category').'</option>'.$this->cat->formated_list('select','all',$event->category,True).'</select>'
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
			if ($this->bo->prefs['common']['timeformat'] == '12')
			{
				if ($event->start->hour >= 12)
				{
					$amsel = ''; $pmsel = ' checked';
				}
			}
			$str = '<input name="start[hour]" size="2" VALUE="'.$phpgw->common->show_date($start,$hourformat).'" maxlength="2">:<input name="start[min]" size="2" value="'.$phpgw->common->show_date($start,'i').'" maxlength="2">';
			if ($this->bo->prefs['common']['timeformat'] == '12')
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
			if ($this->bo->prefs['common']['timeformat'] == '12')
			{
				if ($event->end->hour >= 12)
				{
					$amsel = ''; $pmsel = ' checked';
				}
			}

			$str = '<input name="end[hour]" size="2" VALUE="'.$phpgw->common->show_date($end,$hourformat).'" maxlength="2">:<input name="end[min]" size="2" value="'.$phpgw->common->show_date($end,'i').'" maxlength="2">';
			if ($this->bo->prefs['common']['timeformat'] == '12')
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
				'data'	=> $sb->getPriority('cal[priority]',$event->priority)
			);

// Access
			$str = '<input type="checkbox" name="cal[private]" value="private"';
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
			$str = '<input type="checkbox" name="participants[]" value="'.$this->bo->owner.'"';
			if((($cal_id > 0) && isset($event->participants[$this->bo->owner])) || !isset($cal_id))
			{
				$str .= ' checked';
			}
			$str .= '>';
			$var[] = Array(
				'field'	=> $phpgw->common->grab_owner_name($this->bo->owner).' '.lang('Participates'),
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
			$str = '<select name="cal[recur_type]">';
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

			$str = '<input type="checkbox" name="cal[rpt_use_end]" value="y"';

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
				'data'	=> '<input name="cal[recur_interval]" size="4" maxlength="4" value="'.$event->recur_interval.'">'
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
		}

		function update()
		{
			global $phpgw, $phpgw_info, $readsess, $cal, $participants, $start, $end, $recur_enddate;

			if(!isset($readsess))
			{
				$this->bo->fix_update_time($start);
				$this->bo->fix_update_time($end);

				if(!isset($cal[private]))
				{
					$cal[private] = 'public';
				}

				$is_public = ($private == 'public'?1:0);
				$this->bo->event_init();
				$this->bo->set_category($cal[category]);
				$this->bo->set_title($cal[title]);
				$this->bo->set_description($cal[description]);
				$this->bo->set_start($start[year],$start[month],$start[mday],$start[hour],$start[min],0);
				$this->bo->set_end($end[year],$end[month],$end[mday],$end[hour],$end[min],0);
				$this->bo->set_class($is_public);
				if($cal[id] != 0)
				{
					$this->bo->add_attribute('id',$cal[id]);
				}

				if($cal[rpt_use_end] != 'y')
				{
					$recur_enddate[year] = 0;
					$recur_enddate[month] = 0;
					$recur_enddate[mday] = 0;
				}
				$cal[recur_data] = $cal[rpt_sun] + $cal[rpt_mon] + $cal[rpt_tue] + $cal[rpt_wed] + $cal[rpt_thu] + $cal[rpt_fri] + $cal[rpt_sat];
		
				switch($cal[recur_type])
				{
					case MCAL_RECUR_NONE:
						$this->bo->set_recur_none();
						break;
					case MCAL_RECUR_DAILY:
						$this->bo->set_recur_daily($recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$cal[recur_interval]);
						break;
					case MCAL_RECUR_WEEKLY:
						$this->bo->set_recur_weekly($recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$cal[recur_interval],$cal[recur_data]);
						break;
					case MCAL_RECUR_MONTHLY_MDAY:
						$this->bo->set_recur_monthly_mday($recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$cal[recur_interval]);
						break;
					case MCAL_RECUR_MONTHLY_WDAY:
						$this->bo->set_recur_monthly_wday($recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$cal[recur_interval]);
						break;
					case MCAL_RECUR_YEARLY:
						$this->bo->set_recur_yearly($recur_enddate[year],$recur_enddate[month],$recur_enddate[mday],$cal[recur_interval]);
						break;
				}

				$parts = $participants;
				$minparts = min($participants);
				$part = Array();
				for($i=0;$i<count($parts);$i++)
				{
					$acct_type = $phpgw->accounts->get_type(intval($parts[$i]));
					if($acct_type == 'u')
					{
						$part[$parts[$i]] = 1;
					}
					elseif($acct_type == 'g')
					{
						/* This pulls ALL users of a group and makes them as participants to the event */
						/* I would like to turn this back into a group thing. */
						$acct = CreateObject('phpgwapi.accounts',intval($parts[$i]));
						$members = $acct->members(intval($parts[$i]));
						unset($acct);
						if($members == False)
						{
							continue;
						}
						while($member = each($members))
						{
							$part[$member[1]['account_id']] = 1;
						}
					}
				}

				@reset($part);
				while(list($key,$value) = each($part))
				{
					$this->bo->add_attribute('participants['.$key.']','U');
				}

				reset($participants);
				$event = $this->bo->get_cached_event();
				if(!@$event->participants[$cal[owner]])
				{
					$this->bo->add_attribute('owner',$minparts);
				}
				$this->bo->add_attribute('priority',$cal[priority]);
				$event = $this->bo->get_cached_event();

				$this->bo->store_to_appsession($event);
				$datetime_check = $this->bo->validate_update($event);
				if($datetime_check)
				{
					Header('Location: '.$this->page('edit','&readsess='.$event->id.'&cd='.$datetime_check));
				}

				$start = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year) - $this->bo->datetime->tz_offset;
				$end = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year) - $this->bo->datetime->tz_offset;

				$overlapping_events = $this->bo->overlap($start,$end,$event->participants,$event->owner,$event->id);
			}
			else
			{
				$event = $this->bo->restore_from_appsession();
				$datetime_check = $this->bo->validate_update($event);
				$overlapping_events = False;
				if($datetime_check)
				{
					Header('Location: '.$this->page('edit','&readsess='.$event->id.'&cd='.$datetime_check));
				}
			}

			if(count($overlapping_events) > 0 && $overlapping_events != False)
			{	
				unset($phpgw_info['flags']['noheader']);
				unset($phpgw_info['flags']['nonavbar']);
				$phpgw->common->phpgw_header();

				$p = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('calendar'));
				$templates = Array(
					'overlap'		=>	'overlap.tpl',
					'form_button'	=>	'form_button_script.tpl'
				);
				$p->set_file($templates);

				$p->set_var('color',$phpgw_info['theme']['bg_text']);
				$p->set_var('overlap_title',lang('Scheduling Conflict'));

				$overlap = '';
				for($i=0;$i<count($overlapping_events);$i++)
				{
					$over = $this->bo->read_entry($overlapping_events[$i]);
					$overlap .= '<li>'.$this->link_to_entry($over,$event->start->month,$event->start->mday,$event->start->year);
				}
				if(strlen($overlap) > 0)
				{
					$var = Array(
						'overlap_text'	=>	lang('Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:',$phpgw->common->show_date($start),$phpgw->common->show_date($end)),
						'overlap_list'	=>	$overlap
					);
				}
				else
				{
					$var = Array(
						'overlap_text'	=>	'',
						'overlap_list'	=>	''
					);
				}

				$p->set_var($var);
//				$phpgw->calendar->event = $event;

				$var = Array(
					'action_url_button'	=> $this->page('update','&readsess='.$event->id.'&year='.$event->start->year.'&month='.$event->start->month.'&day='.$event->start->mday),
					'action_text_button'	=> lang('Ignore Conflict'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				);
				$p->set_var($var);

				$p->parse('resubmit_button','form_button');

				$var = Array(
					'action_url_button'	=> $this->page('update','&readsess='.$event->id.'&year='.$event->start->year.'&month='.$event->start->month.'&day='.$event->start->mday),
					'action_text_button'	=> lang('Re-Edit Event'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				);
				$p->set_var($var);

				$p->parse('reedit_button','form_button');

				$p->pparse('out','overlap');
			}
			else
			{
				if(!$event->id)
				{
					$this->bo->add_entry($event);
				}
				elseif($event->id)
				{
					$this->bo->update_entry($event);
				}

				Header('Location: '.$this->page('','&year='.$event->start->year.'&month='.$event->start->month.'&day='.$event->start->mday.'&cd=14&owner='.$this->bo->owner));
			}
		}

		function delete()
		{
			global $cal_id;
			$event = $this->bo->read_entry(intval($cal_id));
			if(($cal_id > 0) && ($event->owner == $this->bo->owner) && ($this->bo->check_perms(PHPGW_ACL_DELETE) == True))
			{
				$date = sprintf("%04d%02d%02d",$event->start->year,$event->start->month,$event->start->mday);

				$this->bo->delete_entry(intval($cal_id));
				$this->bo->expunge();
			}
			else
			{
				$date = sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day);
			}
			Header('Location: '.$this->page('','&date='.$date));
		}

		function preferences()
		{
			global $phpgw, $phpgw_info, $submit, $prefs;
			if ($submit)
			{
				$phpgw->preferences->read_repository();
				$phpgw->preferences->add('calendar','weekdaystarts',$prefs[weekdaystarts]);
				$phpgw->preferences->add('calendar','workdaystarts',$prefs[workdaystarts]);
				$phpgw->preferences->add('calendar','workdayends',$prefs[workdayends]);
				$phpgw->preferences->add('calendar','defaultcalendar',$prefs[defaultcalendar]);
				$phpgw->preferences->add('calendar','defaultfilter',$prefs[defaultfilter]);
				$phpgw->preferences->add('calendar','interval',$prefs[interval]);
				if ($prefs[mainscreen_showevents] == True)
				{
					$phpgw->preferences->add('calendar','mainscreen_showevents',$prefs[mainscreen_showevents]);
				}
				else
				{
					$phpgw->preferences->delete('calendar','mainscreen_showevents');
				}
				if ($prefs[send_updates] == True)
				{
					$phpgw->preferences->add('calendar','send_updates',$prefs[send_updates]);
				}
				else
				{
					$phpgw->preferences->delete('calendar','send_updates');
				}
		
				if ($prefs[display_status] == True)
				{
					$phpgw->preferences->add('calendar','display_status',$prefs[display_status]);
				}
				else
				{
					$phpgw->preferences->delete('calendar','display_status');
				}

				if ($prefs[default_private] == True)
				{
					$phpgw->preferences->add('calendar','default_private',$prefs[default_private]);
				}
				else
				{
					$phpgw->preferences->delete('calendar','default_private');
				}

				if ($prefs[display_minicals] == True)
				{
					$phpgw->preferences->add('calendar','display_minicals',$prefs[display_minicals]);
				}
				else
				{
					$phpgw->preferences->delete('calendar','display_minicals');
				}

				if ($prefs[print_black_white] == True)
				{
					$phpgw->preferences->add('calendar','print_black_white',$prefs[print_black_white]);
				}
				else
				{
					$phpgw->preferences->delete('calendar','print_black_white');
				}

				$phpgw->preferences->save_repository(True);
     
				Header('Location: '.$phpgw->link('/preferences/index.php'));
				$phpgw->common->phpgw_exit();
			}

			unset($phpgw_info['flags']['noheader']);
			unset($phpgw_info['flags']['nonavbar']);
			$phpgw_info['flags']['noappheader'] = True;
			$phpgw->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$templates = Array(
				'pref'		=>	'pref.tpl',
				'pref_colspan'	=>	'pref_colspan.tpl',
				'pref_list'	=>	'pref_list.tpl'
			);
			$p->set_file($templates);

			$var = Array(
				'title'		=>	lang('Calendar preferences'),
				'action_url'	=>	$this->page('preferences'),
				'bg_color'	=>	$phpgw_info['theme']['th_bg'],
				'submit_lang'	=>	lang('submit'),
				'text'		=> '&nbsp;'
			);
	
			$this->output_template_array($p,'row','pref_colspan',$var);

//	if ($totalerrors)
//	{
//		echo '<p><center>' . $phpgw->common->error_list($errors) . '</center>';
//	}

			$str = '<input type="checkbox" name="prefs[mainscreen_showevents]" value="True"'.($this->bo->prefs['calendar']['mainscreen_showevents'] == 'Y' || $this->bo->prefs['calendar']['mainscreen_showevents'] == True?' checked':'').'>';
			$this->display_item($p,lang('show day view on main screen'),$str);

			$t_weekday[$this->bo->prefs['calendar']['weekdaystarts']] = ' selected';
			$str = '<select name="prefs[weekdaystarts]">'
				. '<option value="Monday"'.$t_weekday['Monday'].'>'.lang('Monday').'</option>'
				. '<option value="Sunday"'.$t_weekday['Sunday'].'>'.lang('Sunday').'</option>'
// The following is for Arabic support.....
				. '<option value="Saturday"'.$t_weekday['Saturday'].'>'.lang('Saturday').'</option>'
				. '</select>';
			$this->display_item($p,lang('weekday starts on'),$str);

			$t_workdaystarts[$this->bo->prefs['calendar']['workdaystarts']] = ' selected';
			$str = '<select name="prefs[workdaystarts]">';
			for ($i=0; $i<24; $i++)
			{
				$str .= '<option value="'.$i.'"'.$t_workdaystarts[$i].'>'
					. $phpgw->common->formattime($i,'00') . '</option>';
			}
			$str .= '</select>';
			$this->display_item($p,lang('work day starts on'),$str);
  
			$t_workdayends[$this->bo->prefs['calendar']['workdayends']] = ' selected';
			$str = '<select name="prefs[workdayends]">';
			for ($i=0; $i<24; $i++)
			{
				$str .= '<option value="'.$i.'"'.$t_workdayends[$i].'>'
					. $phpgw->common->formattime($i,'00') . '</option>';
			}
			$str .= '</select>';
			$this->display_item($p,lang('work day ends on'),$str);

			if(strpos('.',$this->bo->prefs['calendar']['defaultcalendar']))
			{
				$temp = explode('.',$this->bo->prefs['calendar']['defaultcalendar']);
				$this->bo->prefs['calendar']['defaultcalendar'] = $temp[0];
			}
			$selected[$this->bo->prefs['calendar']['defaultcalendar']] = ' selected';
			if (!isset($this->bo->prefs['calendar']['defaultcalendar']))
			{
				$selected['month'] = ' selected';
			}
			$str = '<select name="prefs[defaultcalendar]">'
				. '<option value="year"'.$selected['year'].'>'.lang('Yearly').'</option>'
				. '<option value="month"'.$selected['month'].'>'.lang('Monthly').'</option>'
				. '<option value="week"'.$selected['week'].'>'.lang('Weekly').'</option>'
				. '<option value="day"'.$selected['day'].'>'.lang('Daily').'</option>'
				. '</select>';
			$this->display_item($p,lang('default calendar view'),$str);

			$selected = array();
			$selected[$this->bo->prefs['calendar']['defaultfilter']] = ' selected';
			if (! isset($this->bo->prefs['calendar']['defaultfilter']) || $this->bo->prefs['calendar']['defaultfilter'] == 'private')
			{
				$selected['private'] = ' selected';
			}
			$str = '<select name="prefs[defaultfilter]">'
				. '<option value="all"'.$selected['all'].'>'.lang('all').'</option>'
				. '<option value="private"'.$selected['private'].'>'.lang('private only').'</option>'
//				. '<option value="public"'.$selected['public'].'>'.lang('global public only').'</option>'
//				. '<option value="group"'.$selected['group'].'>'.lang('group public only').'</option>'
//				. '<option value="private+public"'.$selected['private+public'].'>'.lang('private and global public').'</option>'
//				. '<option value="private+group"'.$selected['private+group'].'>'.lang('private and group public').'</option>'
//				. '<option value="public+group"'.$selected['public+group'].'>'.lang('global public and group public').'</option>'
				. '</select>';
			$this->display_item($p,lang('Default calendar filter'),$str);

			$selected = array();
			$selected[intval($this->bo->prefs['calendar']['interval'])] = ' selected';
			if (! isset($this->bo->prefs['calendar']['interval']))
			{
				$selected[60] = ' selected';
			}
			$var = Array(
				5	=> '5',
				10	=> '10',
				15	=> '15',
				20	=> '20',
				30	=> '30',
				45	=> '45',
				60	=> '60'
			);
	
			$str = '<select name="prefs[interval]">';
			while(list($key,$value) = each($var))
			{
				$str .= '<option value="'.$key.'"'.$selected[$key].'>'.$value.'</option>';
			}
			$str .= '</select>';
			$this->display_item($p,lang('Display interval in Day View'),$str);

			$str = '<input type="checkbox" name="prefs[send_updates]" value="True"'.($this->bo->prefs['calendar']['send_updates'] == 'Y' || $this->bo->prefs['calendar']['send_updates'] == True?' checked':'').'>';
			$this->display_item($p,lang('Send/receive updates via email'),$str);

			$str = '<input type="checkbox" name="prefs[display_status]" value="True"'.($this->bo->prefs['calendar']['display_status'] == 'Y' || $this->bo->prefs['calendar']['display_status'] == True?' checked':'').'>';
			$this->display_item($p,lang('Display status of events'),$str);

			$str = '<input type="checkbox" name="prefs[default_private]" value="True"'.($this->bo->prefs['calendar']['default_private'] == 'Y' || $this->bo->prefs['calendar']['default_private'] == True?' checked':'').'>';
			$this->display_item($p,lang('When creating new events default set to private'),$str);

			$str = '<input type="checkbox" name="prefs[display_minicals]" value="True"'.($this->bo->prefs['calendar']['display_minicals'] == 'Y' || $this->bo->prefs['calendar']['display_minicals'] == True?' checked':'').'>';
			$this->display_item($p,lang('Display mini calendars when printing'),$str);

			$str = '<input type="checkbox" name="prefs[print_black_white]" value="True"'.($this->bo->prefs['calendar']['print_black_white'] == 'Y' || $this->bo->prefs['calendar']['print_black_white'] == True?' checked':'').'>';
			$this->display_item($p,lang('Print calendars in black & white'),$str);

			$p->pparse('out','pref');
			$phpgw_info['flags']['noappfooter'] = True;
		}

		function day()
		{
			global $phpgw, $phpgw_info;
			
			$this->bo->read_holidays();
			
			if (!$this->bo->printer_friendly)
			{
				unset($phpgw_info['flags']['noheader']);
				unset($phpgw_info['flags']['nonavbar']);
				$phpgw->common->phpgw_header();
				$printer = '';
				$param = '&year='.$this->bo->year.'&month='.$this->bo->month.'&day='.$this->bo->day.'&friendly=1';
				$print = '<a href="'.$this->page('day'.$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
				$minical = $this->mini_calendar($this->bo->day,$this->bo->month,$this->bo->year,'day');
			}
			else
			{
				$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
				$print =	'';
				if($this->bo->prefs['calendar']['display_minicals'] == 'Y' || $this->bo->prefs['calendar']['display_minicals'])
				{
					$minical = $this->mini_calendar($this->bo->day,$this->bo->month,$this->bo->year,'day');
				}
				else
				{
					$minical = '';
				}
			}


			$now	= $this->bo->datetime->makegmttime(0, 0, 0, $this->bo->month, $this->bo->day, $this->bo->year);
			$now['raw'] += $this->bo->datetime->tz_offset;
			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$template = Array(
				'day_t' => 'day.tpl'
			);
			$p->set_file($template);

			$var = Array(
				'printer_friendly'		=>	$printer,
				'bg_text'					=> $phpgw_info['themem']['bg_text'],
				'daily_events'				=>	$this->print_day($this->bo->year,$this->bo->month,$this->bo->day),
				'small_calendar'			=>	$minical,
				'date'						=>	lang(date('F',$m)).' '.sprintf("%02d",$this->bo->day).', '.$this->bo->year,
				'username'					=>	$phpgw->common->grab_owner_name($owner),
				'print'						=>	$print
			);

			$p->set_var($var);

			$p->pparse('out','day_t');
			if($this->bo->printer_friendly)
			{
				$phpgw_info['flags']['nofooter'] = True;
			}
		}

		function edit_status()
		{
			global $phpgw, $phpgw_info, $cal_id;

			unset($phpgw_info['flags']['noheader']);
			unset($phpgw_info['flags']['nonavbar']);
			$phpgw_info['flags']['noappheader'] = True;
			$phpgw->common->phpgw_header();
			
			$event = $this->bo->read_entry($cal_id);

			reset($event->participants);

			if(!$event->participants[$this->bo->owner])
			{
				echo '<center>The user '.$phpgw->common->grab_owner_name($this->bo->owner).' is not participating in this event!</center>';
				$phpgw->common->footer();
			}

			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
				echo '<center>You do not have permission to edit this appointment!</center>';
				$phpgw->common->footer();
			}

			$freetime = $this->bo->datetime->localdates(mktime(0,0,0,$event->start->month,$event->start->mday,$event->start->year) - $this->bo->datetime->tz_offset);
			echo $this->timematrix($freetime,$this->bo->splittime('000000',False),0,$event->participants);

			echo $this->view_event($event);

			echo $this->get_response($event->id);
			$phpgw_info['flags']['noappfooter'] = True;
		}

		function set_action()
		{
			global $phpgw, $cal_id, $action;
			
			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
				unset($phpgw_info['flags']['noheader']);
				unset($phpgw_info['flags']['nonavbar']);
				$phpgw->common->phpgw_header();
				echo '<center>You do not have permission to edit this appointment!</center>';
				$phpgw->common->phpgw_exit();
			}

			$this->bo->set_status(intval($cal_id),intval($action));

			Header('Location: '.$this->page('',''));
		}

		/* Private functions */
		function _debug_sqsof()
		{
			$data = array(
				'filter' => $this->bo->filter,
				'cat_id' => $this->bo->cat_id,
				'owner'	=> $this->bo->owner,
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
				'filter' => $this->bo->filter,
				'cat_id' => $this->bo->cat_id,
				'owner'	=> $this->bo->owner,
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

		function display_item(&$p,$field,$data)
		{
			global $phpgw, $tr_color;
			$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
			$var = Array(
				'bg_color'	=>	$tr_color,
				'field'		=>	$field,
				'data'		=>	$data
			);
			$this->output_template_array($p,'row','pref_list',$var);
		}

		function page($page='',$params='')
		{
			global $phpgw, $phpgw_info;

			if($page == '')
			{
				$page_ = explode('.',$this->bo->prefs['calendar']['defaultcalendar']);
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
			global $phpgw, $cal_id, $date, $keywords, $matrixtype, $participants, $tpl, $menuaction;

			$cols = 8;
			if($this->bo->check_perms(PHPGW_ACL_PRIVATE) == True)
			{
				$cols++;
			}
	
			$tpl = CreateObject('phpgwapi.Template',$this->template_dir);
			$tpl->set_unknowns('remove');

			include($this->template_dir.'/header.inc.php');
			$header = $tpl->fp('out','head');
			unset($tpl);
			echo $header;
		}

		function footer()
		{
			global $phpgw;
		
			if (@$this->bo->printer_friendly)
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
			$is_private = $this->bo->is_private($event,$this->bo->owner);
			$editable = ((!$this->bo->printer_friendly) && (($is_private && $this->bo->check_perms(PHPGW_ACL_PRIVATE)) || !$is_private));
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
				$time = $phpgw->common->show_date($starttime,$this->bo->users_timeformat);
			}
			elseif ($starttime <= $rawdate_offset && $endtime >= $nextday - 60)
			{
				$time = '[ '.lang('All Day').' ]';
			}
			elseif (intval($phpgw->common->show_date($starttime,'Hi')) || $starttime != $endtime)
			{
				if($starttime < $rawdate_offset && $event->recur_type==MCAL_RECUR_NONE)
				{
					$start_time = $phpgw->common->show_date($rawdate_offset,$this->bo->users_timeformat);
				}
				else
				{
					$start_time = $phpgw->common->show_date($starttime,$this->bo->users_timeformat);
				}

				if($endtime >= ($rawdate_offset + 86400))
				{
					$end_time = $phpgw->common->show_date(mktime(23,59,59,$month,$day,$year) - $this->bo->datetime->tz_offset,$this->bo->users_timeformat);
				}
				else
				{
					$end_time = $phpgw->common->show_date($endtime,$this->bo->users_timeformat);
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
			if($this->bo->printer_friendly && @$this->bo->prefs['calendar']['print_black_white'])
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

			$temp_owner = $this->bo->owner;
			$this->bo->owner = $owner;

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
						if(!$this->bo->printer_friendly)
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
			$this->bo->owner = $temp_owner;
			return $p->fp('out','monthly_header');
		}
		
		function display_month($month,$year,$showyear,$owner=0)
		{
			global $phpgw, $phpgw_info;

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

			$true_printer_friendly = $this->bo->printer_friendly;

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

			$original_owner = $this->bo->owner;
			for($i=0;$i<$counter;$i++)
			{
				$this->so->owner = $owners_array[$i];
				$this->bo->store_to_cache($year,$month,1);
				$p->set_var('day_events',$this->display_week($start,True,$cellcolor,$display_name,$owners_array[$i]));
				$p->parse('row','event',True);
			}
			$this->bo->owner = $original_owner;
			$this->bo->printer_friendly = $true_printer_friendly;
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
				$this->cat->categories($this->bo->owner,'calendar');
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
					if($this->bo->prefs['calendar']['weekdaystarts'] == 'Sunday')
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
					if($this->bo->prefs['calendar']['weekdaystarts'] == 'Monday')
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

		function html_for_day($event,&$time,$month,$day,$year,&$rowspan,&$rowspan_arr)
		{
			$ind = intval($event->start->hour);

			if($ind < (int)$this->bo->prefs['calendar']['workdaystarts'] || $ind > (int)$this->bo->prefs['calendar']['workdayends'])
			{
				$ind = 99;
			}

			if(!@$time[$ind])
			{
				$time[$ind] = '';
			}

			$time[$ind] .= $this->link_to_entry($event,$month,$day,$year);

			$starttime = mktime($event->start->hour,$event->start->min,$event->start->sec,$event->start->month,$event->start->mday,$event->start->year);
			$endtime = mktime($event->end->hour,$event->end->min,$event->end->sec,$event->end->month,$event->end->mday,$event->end->year);

			if ($starttime <> $endtime)
			{
				$rowspan = (int)(($endtime - $starttime) / 3600);
				$mins = (int)((($endtime - $starttime) / 60) % 60);
			
				if ($mins <> 0)
				{
					$rowspan += 1;
				}
			
				if ($rowspan > $rowspan_arr[$ind] && $rowspan > 1)
				{
					$rowspan_arr[$ind] = $rowspan;
				}
			}
		}

		function print_day($year,$month,$day)
		{
			global $phpgw, $phpgw_info;

			$this->bo->store_to_cache($year,$month,$day,$year,$month,$day + 1);

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

			if (! $this->bo->prefs['calendar']['workdaystarts'] &&
				 ! $this->bo->prefs['calendar']['workdayends'])
			{
				
				$phpgw->preferences->add('calendar','workdaystarts',8);
				$phpgw->preferences->add('calendar','workdayends',16);
				$phpgw->preferences->save_repository();
				$this->bo->prefs['calendar']['workdaystarts'] = 8;
				$this->bo->prefs['calendar']['workdayends'] = 16;
			}

			$t_format = $this->bo->prefs['common']['time_format'];
			$phpgw->browser->browser();
			$browser_agent = $phpgw->browser->get_agent();
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

			for ($i=0;$i<24;$i++)
			{
				$this->rowspan_arr[$i] = 0;
			}

			$events = Array(
				CreateObject('calendar.calendar_item')
			);
			$date_to_eval = sprintf("%04d%02d%02d",$year,$month,$day);
	
			$time = Array();

			$cellcolor = $phpgw_info['theme']['row_on'];
			$daily = $this->bo->set_week_array($this->bo->datetime->get_weekday_start($year, $month, $day),$cellcolor,True);

//			$events = $this->bo->cached_events[$date_to_eval];

			if($daily[$date_to_eval]['appts'])
      	{
				$events = $this->bo->cached_events[$date_to_eval];
				$c_events = count($events);
				for($i=0;$i<$c_events;$i++)
				{
					$this->html_for_day($events[$i],$time,$month,$day,$year,$rowspan,$rowspan_arr);
				}
			}

			// squish events that use the same cell into the same cell.
			// For example, an event from 8:00-9:15 and another from 9:30-9:45 both
			// want to show up in the 8:00-9:59 cell.
			$rowspan = 0;
			$last_row = -1;
			for ($i=0;$i<24;$i++)
			{
				if ($rowspan > 1)
				{
					if (isset($time[$i]) && strlen($time[$i]) > 0)
					{
						$rowspan_arr[$last_row] += $rowspan_arr[$i];
						if ($rowspan_arr[$i] <> 0)
						{
							$rowspan_arr[$last_row] -= 1;
						}
						$time[$last_row] .= $time[$i];
						$time[$i] = '';
						$rowspan_arr[$i] = 0;
					}
					$rowspan--;
				}
				elseif ($rowspan_arr[$i] > 1)
				{
					$rowspan = $rowspan_arr[$i];
					$last_row = $i;
				}
			}

			$holiday_names = $daily[$date_to_eval]['holidays'];
			if(!$holiday_names)
			{
				$bgcolor = $phpgw->nextmatchs->alternate_row_color();
			}
			else
			{
				$bgcolor = $phpgw_info['theme']['bg04'];
				while(list($index,$name) = each($holiday_names))
				{
					$time[99] = '<center>'.$name.'</center>'.$time[99];
				}
			}

			if (isset($time[99]) && strlen($time[99]) > 0)
			{
				$var = Array(
					'event'		=>	$time[99],
					'bgcolor'	=>	$bgcolor
				);
				$this->output_template_array($p,'item','day_event',$var);

				$var = Array(
					'open_link'		=>	'',
					'time'			=>	'&nbsp;',
					'close_link'	=>	''
				);
				$this->output_template_array($p,'item','day_time',$var);
				$p->parse('row','day_row',True);
				$p->set_var('item','');
			}
			$rowspan = 0;
			for ($i=(int)$this->bo->prefs['calendar']['workdaystarts'];$i<=(int)$this->bo->prefs['calendar']['workdayends'];$i++)
			{
				$dtime = $this->bo->build_time_for_display($i * 10000);
				$p->set_var('extras','');
				$p->set_var('event','&nbsp');
				if ($rowspan > 1)
				{
					// this might mean there's an overlap, or it could mean one event
					// ends at 11:15 and another starts at 11:30.
					if (isset($time[$i]) && strlen($time[$i]))
					{
						$p->set_var('event',$time[$i]);
						$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
						$p->parse('item','day_event',False);
					}
					$rowspan--;
				}
				elseif (!isset($time[$i]) || !strlen($time[$i]))
				{
					$p->set_var('event','&nbsp;');
					$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
					$p->parse('item','day_event',False);
				}
				else
				{
					$rowspan = intval($rowspan_arr[$i]);
					if ($rowspan > 1)
					{
						$p->set_var('extras',' rowspan="'.$rowspan.'"');
					}
					$p->set_var('event',$time[$i]);
					$p->set_var('bgcolor',$phpgw->nextmatchs->alternate_row_color());
					$p->parse('item','day_event',False);
				}
			
				$open_link = ' - ';
				$close_link = '';
			
				if(!$this->bo->printer_friendly && $this->bo->check_perms(PHPGW_ACL_ADD))
				{
					$new_hour = intval(substr($dtime,0,strpos($dtime,':')));
					if ($this->bo->prefs['common']['timeformat'] == '12' && $i >= 12)
					{
						$new_hour += 12;
					}
				
					$new_minute = substr($dtime,strpos($dtime,':')+1,2);
	
					$open_link .= '<a href="'.$this->page('edit',
									  '&date='.$date_to_eval.'&hour='.$new_hour
									. '&minute='.$new_minute).'">';
								
					$close_link = '</a>';
				}

				$var = Array(
					'open_link'		=>	$open_link,
					'time'			=>	(intval(substr($dtime,0,strpos($dtime,':'))) < 10 ? '0'.$dtime : $dtime),
					'close_link'	=>	$close_link
				);
	
				$this->output_template_array($p,'item','day_time',$var);
				$p->parse('row','day_row',True);
				$p->set_var('event','');
				$p->set_var('item','');
			}	// end for
			return $p->fp('out','day');
		}	// end function

		function timematrix($date,$starttime,$endtime,$participants)
		{
			global $phpgw, $phpgw_info;

			if(!isset($this->bo->prefs['calendar']['interval']))
			{
				$this->bo->prefs['calendar']['interval'] = 15;
				$phpgw->preferences->add('calendar','interval',15);
				$phpgw->preferences->save_repository();
			}
//			$increment = $this->bo->prefs['calendar']['interval'];
			$increment = 15;
			$interval = (int)(60 / $increment);

			$pix = $phpgw->common->image('calendar','pix.gif');
			$str = '<center>'.lang($phpgw->common->show_date($date['raw'],'l'));
			$str .= ', '.lang($phpgw->common->show_date($date['raw'],'F'));
			$str .= ' '.$phpgw->common->show_date($date['raw'],'d, Y').'<br>';
			$str .= '<table width="85%" border="0" cellspacing="0" cellpadding="0" cols="'.((24 * $interval) + 1).'">';
			$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$pix.'"></td></tr>';
			$str .= '<tr><td width="15%"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">'.lang('Participant').'</font></td>';
			for($i=0;$i<24;$i++)
			{
				for($j=0;$j<$interval;$j++)
				{
					switch($j)
					{
						case 0:
						case 1:
							switch($j)
							{
								case 0:
									$pre = '0';
									break;
								case 1:
									$pre = substr(strval($i),0,1);
									break;
							}
						
							$k = ($i<=9?$pre:substr($i,$j,$j+1));
							if($increment == 60)
							{
								$k .= substr(strval($i),strlen(strval($i)) - 1,1);
							}
							$str .= '<td align="left" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">';
							$str .= '<a href="'.$this->page('edit_entry','&date='.$date['full'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.(($increment * $j)<=9?'0':'').($increment * $j)."'; return true;\">";
							$str .= $k.'</a></font></td>';
							break;
						default:
							$str .= '<td align="left" bgcolor="'.$phpgw_info['theme']['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">';
							$str .= '<a href="'.$this->page('edit_entry','&date='.$date['full'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j)."'; return true;\">";
							$str .= '&nbsp</a></font></td>';
							break;
					}
				}
			}
			$str .= '</tr>';
			$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$pix.'"></td></tr>';
			if(!$endtime)
			{
				$endtime = $starttime;
			}
			$owner = $this->bo->owner;
			while(list($part,$status) = each($participants))
			{
				$str .= '<tr>';
				$str .= '<td width="15%"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">'.$this->bo->get_fullname($participants[$i]).'</font></td>';

				$this->bo->cached_events = Array();
				$this->bo->owner = $part;
				$this->so->owner = $part;
				$this->bo->store_to_cache($date['year'],$date['month'],$date['day'],0,0,$date['day'] + 1);

				if(!$this->bo->cached_events[$date['full']])
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
					$time_slice = $this->bo->prepare_matrix($interval,$increment,$part,$status,$date['full']);
					for($h=0;$h<24;$h++)
					{
						$hour = $h * 10000;
						for($m=0;$m<$interval;$m++)
						{
							$index = ($hour + (($m * $increment) * 100));
							switch($time_slice[$index]['marker'])
							{
								case '&nbsp':
									$time_slice[$index]['color'] = $phpgw_info['theme']['bg_color'];
									break;
								case '-':
									$time_slice[$index]['color'] = $phpgw_info['theme']['bg01'];
									break;
							}
							$str .= '<td height="1" align="left" bgcolor="'.$time_slice[$index]['color']."\" color=\"#999999\"  onMouseOver=\"window.status='".$time_slice[$index]['description']."'; return true;\">".'<font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$phpgw_info['theme']['font'].'" size="-2">'.$time_slice[$index]['marker'].'</font></td>';
						}
					}
				}
				$str .= '</tr>';
				$str .= '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="#999999"><img src="'.$pix.'"></td></tr>';
			}
			$str .= '</table></center>';
			$this->bo->owner = $owner;
			$this->so->owner = $owner;
			return $str;
		}      

		function get_response($cal_id)
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
					'action_url_button'	=> $this->page('set_action','&cal_id='.$cal_id.'&action='.$param),
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
	}
?>
