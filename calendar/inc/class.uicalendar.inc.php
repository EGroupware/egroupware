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
//		var $debug = True;

		var $cat_id;
		var $tz_offset;
		var $theme;

		var $public_functions = array(
			'mini_calendar' => True,
			'index' => True,
			'month' => True,
			'week'  => True,
			'year' => True,
			'view' => True,
			'edit' => True,
			'export'	=> True,
			'add'  => True,
			'delete' => True,
			'preferences' => True,
			'day' => True,
			'edit_status' => True,
			'set_action' => True,
			'planner' => True,
			'matrixselect'	=> True,
			'viewmatrix'	=> True,
			'search' => True,
			'header' => True,
			'footer' => True,
			'css'		=> True
		);

		function uicalendar()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$GLOBALS['phpgw']->browser    = CreateObject('phpgwapi.browser');
			
			$this->theme = $GLOBALS['phpgw_info']['theme'];

			$this->bo = CreateObject('calendar.bocalendar',1);
			$this->tz_offset = $this->bo->datetime->tz_offset;

			if($this->debug)
			{
				echo "BO Owner : ".$this->bo->owner."<br>\n";
			}

			$this->template = $GLOBALS['phpgw']->template;
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir('calendar');
			$this->cat      = CreateObject('phpgwapi.categories');

			$this->holiday_color = (substr($this->theme['bg06'],0,1)=='#'?'':'#').$this->theme['bg06'];
			
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

		function mini_calendar($params)
		{
			if(!is_array($params))
			{
				return;
			}

			$this->bo->store_to_cache(
				Array(
					'smonth'	=> $params['month'],
					'sday'	=> 1,
					'syear'	=> $params['year']
				)
			);
			
			$params['link']			= (!isset($params['link'])?'':$params['link']);
			$params['buttons']		= (!isset($params['buttons'])?'none':$params['buttons']);
			$params['outside_month']	= (!isset($params['outside_month'])?True:$params['outside_month']);

			$this->bo->read_holidays();

			$date = $this->bo->datetime->makegmttime(0,0,0,$params['month'],$params['day'],$params['year']);
			$month_ago = intval(date('Ymd',mktime(0,0,0,$params['month'] - 1,$params['day'],$params['year'])));
			$month_ahead = intval(date('Ymd',mktime(0,0,0,$params['month'] + 1,$params['day'],$params['year'])));
			$monthstart = intval(date('Ymd',mktime(0,0,0,$params['month'],1,$params['year'])));
			$monthend = intval(date('Ymd',mktime(0,0,0,$params['month'] + 1,0,$params['year'])));

			$weekstarttime = $this->bo->datetime->get_weekday_start($params['year'],$params['month'],1);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');

			$p->set_file(
				Array(
					'mini_calendar'	=> 'mini_cal.tpl'
				)
			);
			$p->set_block('mini_calendar','mini_cal','mini_cal');
			$p->set_block('mini_calendar','mini_week','mini_week');
			$p->set_block('mini_calendar','mini_day','mini_day');

			if($this->bo->printer_friendly == False)
			{
				$month = '<a href="' . $this->page('month','&month='.$GLOBALS['phpgw']->common->show_date($date['raw'],'m').'&year='.$GLOBALS['phpgw']->common->show_date($date['raw'],'Y')). '" class="minicalendar">' . lang($GLOBALS['phpgw']->common->show_date($date['raw'],'F')).' '.$GLOBALS['phpgw']->common->show_date($date['raw'],'Y').'</a>';
			}
			else
			{
				$month = lang($GLOBALS['phpgw']->common->show_date($date['raw'],'F')).' '.$GLOBALS['phpgw']->common->show_date($date['raw'],'Y');
			}

			$var = Array(
				'cal_img_root'		=>	$GLOBALS['phpgw']->common->image('calendar','mini-calendar-bar.gif'),
				'bgcolor'			=>	$this->theme['bg_color'],
				'bgcolor1'			=>	$this->theme['bg_color'],
				'month'				=>	$month,
				'bgcolor2'			=>	$this->theme['cal_dayview'],
				'holiday_color'	=> $this->holiday_color
			);

			$p->set_var($var);

			switch(strtolower($params['buttons']))
			{
				case 'right':
					$var = Array(
						'nextmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ahead).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','right.gif').'" border="0"></a>'
					);
					break;
				case 'left':
					$var = Array(
						'prevmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ago).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','left.gif').'" border="0"></a>'
					);					
					break;
				case 'both':
					$var = Array(
						'prevmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ago).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','left.gif').'" border="0"></a>',
						'nextmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ahead).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','right.gif').'" border="0"></a>'
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
				$var = Array(
					'dayname'	=> '<b>' . substr(lang($this->bo->datetime->days[$i]),0,2) . '</b>',
					'day_image'	=> ''
				);
				$this->output_template_array($p,'daynames','mini_day',$var);
			}
			$today = date('Ymd',time());
			unset($date);
			for($i=$weekstarttime;date('Ymd',$i)<=$monthend;$i += (24 * 3600 * 7))
			{
				unset($var);
				$daily = $this->set_week_array($i,$cellcolor,$weekly);
				@reset($daily);
				while(list($date,$day_params) = each($daily))
				{
				   if($this->debug)
				   {
                  echo 'Mini-Cal Date : '.$date."<br>\n";
               }               
					$year = intval(substr($date,0,4));
					$month = intval(substr($date,4,2));
					$day = intval(substr($date,6,2));
					$str = '';
					if(($date >= $monthstart && $date <= $monthend) || $params['outside_month'] == True)
					{
						if(!$this->bo->printer_friendly && $params['link'])
						{
							$str = '<a href="'.$this->page($params['link'],'&date='.$date).'" class="'.$day_params['class'].'">'.$day.'</a>';
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

		function index($params='')
		{
			Header('Location: '. $this->page('',$params));
			$GLOBALS['phpgw']->common->phpgw_exit();
		}

		function month()
		{
			$this->bo->read_holidays();

			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			if (!$this->bo->printer_firendly || ($this->bo->printer_friendly && @$this->bo->prefs['calendar']['display_minicals']))
			{
				$minical_prev = $this->mini_calendar(
					Array(
						'day'	=> 1,
						'month'	=> $this->bo->month - 1,
						'year'	=> $this->bo->year,
						'link'	=> 'day'
					)
				);
				$minical_next = $this->mini_calendar(
					Array(
						'day'	=> 1,
						'month'	=> $this->bo->month + 1,
						'year'	=> $this->bo->year,
						'link'	=> 'day'
					)
				);
			}
			else
			{
				$minical_prev = '';
				$minical_next = '';
			}

			if (!$this->bo->printer_friendly)
			{
				$printer = '';
				$param = '&year='.$this->bo->year.'&month='.$this->bo->month.'&friendly=1';
				$print = '<a href="'.$this->page('month'.$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
			}
			else
			{
				$printer = '<body bgcolor="'.$phpgw_info['theme']['bg_color'].'">';
				$print =	'';
				$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
			}

			$var = Array(
				'printer_friendly'		=>	$printer,
				'bg_text'					=> $this->theme['bg_text'],
				'small_calendar_prev'	=>	$minical_prev,
				'month_identifier'		=>	lang(strftime("%B",$m)).' '.$this->bo->year,
				'username'					=>	$GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner),
				'small_calendar_next'	=>	$minical_next,
				'large_month'				=>	$this->display_month($this->bo->month,$this->bo->year,True,$this->bo->owner),
				'print'						=>	$print
			);

			if (!$this->bo->printer_friendly)
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				unset($GLOBALS['phpgw_info']['flags']['noappheader']);
				unset($GLOBALS['phpgw_info']['flags']['noappfooter']);
				$GLOBALS['phpgw']->common->phpgw_header();
			}
			
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');
			$p->set_file(
				Array(
					'index_t'	=>	'index.tpl'
				)
			);
			$p->set_var($var);
			$p->pparse('out','index_t');
		}

		function week()
		{
			$this->bo->read_holidays();

			$next = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day + 7,$this->bo->year);
			$prev = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day - 7,$this->bo->year);

			if (!$this->bo->printer_friendly || ($this->bo->printer_friendly && @$this->bo->prefs['calendar']['display_minicals']))
			{
				$minical_this = $this->mini_calendar(
					Array(
						'day'	=> $this->bo->day,
						'month'	=> $this->bo->month,
						'year'	=> $this->bo->year,
						'link'	=> 'day',
						'butons'	=> 'none',
						'outside_month'	=> False
					)
				);
				$minical_prev = $this->mini_calendar(
					Array(
						'day'	=> $this->bo->day,
						'month'	=> $this->bo->month - 1,
						'year'	=> $this->bo->year,
						'link'	=> 'day',
						'butons'	=> 'left',
						'outside_month'	=> False
					)
				);
				$minical_next = $this->mini_calendar(
					Array(
						'day'	=> $this->bo->day,
						'month'	=> $this->bo->month + 1,
						'year'	=> $this->bo->year,
						'link'	=> 'day',
						'butons'	=> 'right',
						'outside_month'	=> False
					)
				);
			}
			else
			{
				$minical_this = '';
				$minical_prev = '';
				$minical_next = '';
			}
			
			if (!$this->bo->printer_friendly)
			{
				$printer = '';
				$prev_week_link = '<a href="'.$this->page('week','&date='.$prev['full']).'">&lt;&lt;</a>';
				$next_week_link = '<a href="'.$this->page('week','&date='.$next['full']).'">&gt;&gt;</a>';
				$print = '<a href="'.$this->page('week','&friendly=1')."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
			}
			else
			{
				$printer = '<body bgcolor="'.$this->theme['bg_color'].'">';
				$prev_week_link = '&lt;&lt;';
				$next_week_link = '&gt;&gt;';
				$print =	'';
				$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
			}

			$var = Array(
				'printer_friendly'		=>	$printer,
				'bg_text'					=> $this->theme['bg_text'],
				'small_calendar_prev'	=>	$minical_prev,
				'prev_week_link'			=>	$prev_week_link,
				'small_calendar_this'	=>	$minical_this,
				'week_identifier'			=>	$this->bo->get_week_label(),
				'next_week_link'			=>	$next_week_link,
				'username'					=>	$GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner),
				'small_calendar_next'	=>	$minical_next,
				'week_display'				=>	$this->display_weekly(
					Array(
						'date'		=> sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day),
						'showyear'	=> true,
						'owners'		=> $this->bo->owner
					)
				),
				'print'						=>	$print
			);

			if (!$this->bo->printer_friendly)
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				$GLOBALS['phpgw']->common->phpgw_header();
			}
			
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'week_t' => 'week.tpl'
				)
			);
			$p->set_var($var);
			$p->pparse('out','week_t');
		}

		function year()
		{
			if(!$this->bo->printer_friendly)
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				$GLOBALS['phpgw']->common->phpgw_header();
				$print = '';
				$left_link = '<a href="'.$this->page('year','&year='.($this->bo->year - 1)).'">&lt;&lt;</a>';
				$right_link = '<a href="'.$this->page('year','&year='.($this->bo->year + 1)).'">&gt;&gt;</a>';
				$link = 'day';
				$printer = '<a href="'.$this->page('year','&friendly=1').'" target="cal_printer_friendly" onMouseOver="window.status = '."'".lang('Generate printer-friendly version')."'".'">['.lang('Printer Friendly').']</a>';
			}
			else
			{
				$print = '<body bgcolor="'.$this->theme['bg_color'].'">';
				$left_link = '';
				$right_link = '';
				$link = '';
				$printer = '';
				$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
			}

			$var = Array(
				'print'		=> $print,
				'left_link' => $left_link,
				'font'		=> $this->theme['font'],
				'year_text' => $this->bo->year,
				'right_link'=> $right_link,
				'printer_friendly'=> $printer
			);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'year_t' => 'year.tpl'
				)
			);
			$p->set_block('year_t','year','year');
			$p->set_block('year_t','month','month');
			$p->set_block('year_t','month_sep','month_sep');
			$p->set_var($var);

			for($i=1;$i<=12;$i++)
			{
				$p->set_var('mini_month',$this->mini_calendar(
						Array(
							'day'	=> 1,
							'month'	=> $i,
							'year'	=> $this->bo->year,
							'link'	=> $link,
							'buttons'	=> 'none',
							'outside_month'	=> False
						)
					)
				);
				$p->parse('row','month',True);
				$p->set_var('mini_month','');
				if(($i % 3) == 0)
				{
					$p->parse('row','month_sep',True);
				}
			}
			$p->pparse('out','year_t');
		}
		
		function view($vcal_id=0,$cal_date=0)
		{
  			unset($GLOBALS['phpgw_info']['flags']['noheader']);
 	 		unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();
	   	
			echo '<center>';

			$cal_id = ($vcal_id?$vcal_id:'');
			$cal_id = (isset($GLOBALS['HTTP_POST_VARS']['cal_id'])?$GLOBALS['HTTP_POST_VARS']['cal_id']:$cal_id);
			$cal_id = (isset($GLOBALS['HTTP_GET_VARS']['cal_id'])?$GLOBALS['HTTP_GET_VARS']['cal_id']:$cal_id);
   		
			$date = $cal_date?$cal_date:0;
			$date = $date?$date:intval($GLOBALS['HTTP_GET_VARS']['date']);
	   	
			// First, make sure they have permission to this entry
			if ($cal_id < 1)
			{
				echo lang('Invalid entry id.').'</center>';
				return;
			}

			if(!$this->bo->check_perms(PHPGW_ACL_READ))
			{
				echo lang('You do not have permission to read this record!').'</center>';
				return;
			}

			$event = $this->bo->read_entry($cal_id);

			if(!isset($event['id']))
			{
				echo lang("Sorry, this event does not exist").'.'.'</center>';
				return;
			}

			$this->bo->repeating_events = Array();
			$this->bo->cached_events = Array();
			$this->bo->repeating_events[0] = $event;
			$datetime = mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year) - $this->tz_offset;
			$this->bo->check_repeating_events($datetime);
			$check_date = $GLOBALS['phpgw']->common->show_date($datetime,'Ymd');
			if(is_array($this->bo->cached_events[$check_date][0]) &&
				$this->bo->cached_events[$check_date][0]['id'] == $event['id'])
			{
				$starttime = $this->bo->maketime($event['start']);
				$endtime = $this->bo->maketime($event['end']);
				$event['start']['month'] = $this->bo->month;
				$event['start']['mday'] = $this->bo->day;
				$event['start']['year'] = $this->bo->year;
				$temp_end =  $this->bo->maketime($event['start']) + ($endtime - $starttime);
				$event['end']['month'] = date('m',$temp_end);
				$event['end']['mday'] = date('d',$temp_end);
				$event['end']['year'] = date('Y',$temp_end);
			}
			echo $this->view_event($event);

			if($this->bo->owner == $event['owner'])
			{
				$p = CreateObject('phpgwapi.Template',$this->template_dir);
				$p->set_file(
					Array(
						'form_button'	=> 'form_button_script.tpl'
					)
				);

				if ($this->bo->check_perms(PHPGW_ACL_EDIT))
				{
					if($event['recur_type'] != MCAL_RECUR_NONE)
					{
						$var = Array(
							'action_url_button'	=> $this->page('edit','&cal_id='.$cal_id),
							'action_text_button'	=> lang('Edit Single'),
							'action_confirm_button'	=> '',
							'action_extra_field'	=> '<input type="hidden" name="edit_type" value="single">'."\n"
								. '<input type="hidden" name="date" value="'.sprintf('%04d%02d%02d',$this->bo->year,$this->bo->month,$this->bo->day).'">'
							
						);
						$p->set_var($var);
						echo $p->fp('out','form_button');

						$var = Array(
							'action_url_button'	=> $this->page('edit','&cal_id='.$cal_id),
							'action_text_button'	=> lang('Edit Series'),
							'action_confirm_button'	=> '',
							'action_extra_field'	=> '<input type="hidden" name="edit_type" value="series">'
						);
						$p->set_var($var);
						echo $p->fp('out','form_button');
					}
					else
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
					
					$var = Array(
						'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uialarm.manager'),
						'action_text_button'	=> lang('Alarm Management'),
						'action_confirm_button'	=> '',
						'action_extra_field'	=> '<input type="hidden" name="cal_id" value="'.$cal_id.'">'
					);
					$p->set_var($var);
					echo $p->fp('out','form_button');
				}

				if ($this->bo->check_perms(PHPGW_ACL_DELETE))
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
			
			$var = Array(
				'action_url_button'	=> $this->page('export','&cal_id='.$cal_id),
				'action_text_button'	=> lang('Export'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			echo $p->fp('out','form_button');
			
   		echo '</center>';
		}

		function edit($params='')
		{
			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
			   $this->no_edit();
			}

			if(isset($GLOBALS['HTTP_GET_VARS']['readsess']))
			{
				$params['readsess'] = $GLOBALS['HTTP_GET_VARS']['readsess'];
				$params['cd'] = 0;
			}

			if($params != '' && @isset($params['readsess']))
			{
				$event = $this->bo->restore_from_appsession;
				$can_edit = True;
				$this->edit_form(
					Array(
						'event' => $event,
						'cd' => $params['cd']
					)
				);
			}
			elseif(isset($GLOBALS['HTTP_GET_VARS']['cal_id']))
			{
				$cal_id = $GLOBALS['HTTP_GET_VARS']['cal_id'];
				$event = $this->bo->read_entry(intval($GLOBALS['HTTP_GET_VARS']['cal_id']));
				
				if(!$this->bo->can_user_edit($event))
				{
					Header('Location: '.$this->page('view','&cal_id='.$cal_id));
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
				if(@isset($GLOBALS['HTTP_POST_VARS']['edit_type']) && $GLOBALS['HTTP_POST_VARS']['edit_type'] == 'single')
				{
					$event['id'] = 0;
					$event['start']['month'] = $this->bo->month;
					$event['start']['mday'] = $this->bo->day;
					$event['start']['year'] = $this->bo->year;
					$event['end']['month'] = $this->bo->month;
					$event['end']['mday'] = $this->bo->day;
					$event['end']['year'] = $this->bo->year;
					$event['recur_type'] = MCAL_RECUR_NONE;
					$event['recur_interval'] = 0;
					$event['recur_data'] = 0;
					$event['recur_enddate']['month'] = 0;
					$event['recur_enddate']['mday'] = 0;
					$event['recur_enddate']['year'] = 0;
				}
				$this->edit_form(
					Array(
						'event' => $event,
						'cd'	=> $cd
					)
				);
			}
 		}

		function export($vcal_id=0)
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();
			echo nl2br(execmethod('calendar.boicalendar.export',$GLOBALS['HTTP_GET_VARS']['cal_id']));
		}

		function add($cd=0,$readsess=0)
		{
			if(!$this->bo->check_perms(PHPGW_ACL_ADD))
			{
				$this->index();
			}
			
			if($readsess)
			{
				$event = $this->bo->restore_from_appsession;
				if(!$event['owner'])
				{
					$this->bo->add_attribute('owner',$this->bo->owner);
				}
				$can_edit = True;
			}
			else
			{
				$this->bo->event_init();
				$this->bo->add_attribute('id',0);

				$can_edit = True;

				$thishour = (isset($GLOBALS['HTTP_GET_VARS']['hour'])?intval($GLOBALS['HTTP_GET_VARS']['hour']):0);
				$thisminute = (isset($GLOBALS['HTTP_GET_VARS']['minute'])?intval($GLOBALS['HTTP_GET_VARS']['minute']):0);
				$this->bo->set_start($this->bo->year,$this->bo->month,$this->bo->day,$thishour,$thisminute,0);
				$this->bo->set_end($this->bo->year,$this->bo->month,$this->bo->day,$thishour,$thisminute,0);
				$this->bo->set_title('');
				$this->bo->set_description('');
				$this->bo->add_attribute('location','');
				$this->bo->add_attribute('uid','');
				$this->bo->add_attribute('priority',2);
				if(@$this->bo->prefs['calendar']['default_private'])
				{
					$this->bo->set_class(False);
				}
				else
				{
					$this->bo->set_class(True);
				}
				$this->bo->add_attribute('participants','A',$this->bo->owner);
				$this->bo->set_recur_none();
				$event = $this->bo->get_cached_event();
			}
			$this->edit_form(
				Array(
					'event' => $event,
					'cd' => $cd
				)
			);
		}

		function delete()
		{
			if(!isset($GLOBALS['HTTP_GET_VARS']['cal_id']))
			{
				Header('Location: '.$this->page('','&date='.sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day)));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			$event = $this->bo->read_entry(intval($GLOBALS['HTTP_GET_VARS']['cal_id']));
			if(($GLOBALS['HTTP_GET_VARS']['cal_id'] > 0) && ($event['owner'] == $this->bo->owner) && $this->bo->check_perms(PHPGW_ACL_DELETE))
			{
				$date = sprintf("%04d%02d%02d",$event['start']['year'],$event['start']['month'],$event['start']['mday']);

				$cd = $this->bo->delete_entry(intval($GLOBALS['HTTP_GET_VARS']['cal_id']));
				$this->bo->expunge();
			}
			else
			{
				$date = sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day);
				$cd = '';
			}
			Header('Location: '.$this->page('','&date='.$date.($cd?'&cd='.$cd:'')));
			$GLOBALS['phpgw']->common->phpgw_exit();
		}

		function day()
		{
			$this->bo->read_holidays();
			
			if (!$this->bo->printer_firendly || ($this->bo->printer_friendly && @$this->bo->prefs['calendar']['display_minicals']))
			{
				$minical = $this->mini_calendar(
					Array(
						'day'	=> $this->bo->day,
						'month'	=> $this->bo->month,
						'year'	=> $this->bo->year,
						'link'	=> 'day'
					)
				);
			}
			else
			{
				$minical = '';
			}
			
			if (!$this->bo->printer_friendly)
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				$GLOBALS['phpgw']->common->phpgw_header();
				$printer = '';
				$param = '&date='.sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day).'&friendly=1';
				$print = '<a href="'.$this->page('day'.$param)."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
			}
			else
			{
				$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
				$printer = '<body bgcolor="'.$this->theme['bg_color'].'">';
				$print =	'';
			}

			$now	= $this->bo->datetime->makegmttime(0, 0, 0, $this->bo->month, $this->bo->day, $this->bo->year);
			$now['raw'] += $this->tz_offset;
			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'day_t' => 'day.tpl'
				)
			);

			$var = Array(
				'printer_friendly'		=> $printer,
				'bg_text'			=> $GLOBALS['phpgw_info']['themem']['bg_text'],
				'daily_events'			=> $this->print_day(
					Array(
						'year'	=> $this->bo->year,
						'month'	=> $this->bo->month,
						'day'	=> $this->bo->day
					)
				),
				'small_calendar'		=> $minical,
				'date'				=> lang(date('F',$m)).' '.sprintf("%02d",$this->bo->day).', '.$this->bo->year,
				'username'			=> $GLOBALS['phpgw']->common->grab_owner_name($owner),
				'print'				=> $print
			);

			$p->set_var($var);
			$p->pparse('out','day_t');
		}

		function edit_status()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();
			
			$event = $this->bo->read_entry($GLOBALS['HTTP_GET_VARS']['cal_id']);

			reset($event['participants']);

			if(!$event['participants'][$this->bo->owner])
			{
				echo '<center>The user '.$GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner).' is not participating in this event!</center>';
				return;
			}

			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
			   $this->no_edit();
			   return;
			}

			$freetime = $this->bo->datetime->localdates(mktime(0,0,0,$event['start']['month'],$event['start']['mday'],$event['start']['year']) - $this->tz_offset);
			echo $this->timematrix(
				Array(
					'date'		=> $freetime,
					'starttime'	=> $this->bo->splittime('000000',False),
					'endtime'	=> 0,
					'participants'	=> $event['participants']
				)
			);

			$event = $this->bo->read_entry($GLOBALS['HTTP_GET_VARS']['cal_id']);
			echo $this->view_event($event);

			echo $this->get_response($event['id']);
		}

		function set_action()
		{
			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
				$this->no_edit();
				return;
			}

			$this->bo->set_status(intval($GLOBALS['HTTP_GET_VARS']['cal_id']),intval($GLOBALS['HTTP_GET_VARS']['action']));

			Header('Location: '.$this->page('',''));
			$GLOBALS['phpgw']->common->phpgw_exit();
		}

		function planner()
		{

		if(floor(phpversion()) == 4)
		{ 
			eval('

			unset($GLOBALS[\'phpgw_info\'][\'flags\'][\'noheader\']);
			unset($GLOBALS[\'phpgw_info\'][\'flags\'][\'nonavbar\']);
			$GLOBALS[\'phpgw\']->common->phpgw_header();
			
			$html = CreateObject(\'infolog.html\');
			$sbox = CreateObject(\'phpgwapi.sbox\');

			$intervals_per_day = 3;					// this should be configurable
			$interval = Array(
				14 => 1,
				15 => 1,
				16 => 1,
				17 => 1, 
				18 => 2,
				19 => 2,
				20 => 2,
				21 => 2,
				22 => 2,
				23 => 2
			);

			$startdate = mktime(0,0,0,$this->bo->month,1,$this->bo->year) - $this->tz_offset;
			$days = $this->bo->datetime->days_in_month($this->bo->month,$this->bo->year);
			$enddate   = mktime(23,59,59,$this->bo->month,$this->bo->days,$this->bo->year) - $this->tz_offset;

			$header[] = lang(\'Category\');
			for ($d = 1; $d <= $days; $d++)
			{
				$dayname = substr(lang(date(\'D\',mktime(0,0,0,$this->bo->month,$d,$this->bo->year))),0,2);

				$header[\'.\'.$d] = \'colspan="\'.$intervals_per_day.\'" align="center"\';
				$header[$d] = \'<a href="\'.$html->link(\'/index.php\',
										array(
											\'menuaction\' => \'calendar.uicalendar.add\',
											\'date\' => sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$d)
										)
									).\'">\'.$dayname.\'<br>\'.$d.\'</a>\';
			}
			$last_cell = $intervals_per_day * $days - 1;

			$this->bo->store_to_cache(
				Array(
					\'syear\'	=> $this->bo->year,
					\'smonth\'	=> $this->bo->month,
					\'sday\'	=> 1,
					\'eyear\'	=> $this->bo->year,
					\'emonth\'	=> $this->bo->month,
					\'eday\'	=> $days
				)
			);
			$firstday = intval(date(\'Ymd\',mktime(0,0,0,$this->bo->month,1,$this->bo->year)));
			$lastday = intval(date(\'Ymd\',mktime(0,0,0,$this->bo->month,$days,$this->bo->year)));
			
			$this->bo->remove_doubles_in_cache($firstday,$lastday);

			$rows = array();
			for($v=$firstday;$v<=$lastday;$v++)
			{
				$daily = $this->bo->cached_events[$v];
				@reset($daily);
				if($this->debug)
				{
					echo "For Date : $v : Count of items : ".count($daily)."<br>\n";
				}
				for($g=0;$g<count($daily);$g++)
				{
					$event = $daily[$g];

					$view = $html->link(\'/index.php\',
						array(
							\'menuaction\' => \'calendar.uicalendar.view\',
							\'cal_id\' => $event[\'id\']
						)
					);

					$start_cell = $intervals_per_day * ($event[\'start\'][\'mday\'] - 1);
					$start_cell += $interval[$event[\'start\'][\'hour\']];

					$end_cell = $intervals_per_day * ($event[\'end\'][\'mday\'] - 1);
					$end_cell += $interval[$event[\'end\'][\'hour\']];

					$i = 0;					// search for row of parent category
					do {
						++$i;
						if ($c = $event[\'category\'])
						{
							$cat   = $this->planner_category($event[\'category\']);
							if ($cat[\'parent\'])
							{
								$pcat = $this->planner_category($c = $cat[\'parent\']);
							}
							else
							{
								$pcat = $cat;
							}
						}
						else
						{
							$cat = $pcat = array( \'name\' => lang(\'none\'));
						}
						$k = $c.\'_\'.$i;
						$ka = \'.nr_\'.$k;
						if (!isset($rows[$k]))
						{
							if ($i > 1)				// further line - no name
							{
								$rows[$k] = array();
								$rows[$c.\'_1\'][\'._name\'] = \'rowspan="\'.$i.\'"\';
							}
							else
							{
								$rows[$k][\'_name\'] = $pcat[\'name\'];
							}
							$rows[$ka] = 0;
						}
						$row = &$rows[$k];
						$akt_cell = &$rows[$ka];
					} while ($akt_cell > $start_cell);

					if ($akt_cell < $start_cell)
					{
						$row[$event[\'id\'].\'_1\'] = \'&nbsp;\';
						$row[\'.\'.$event[\'id\'].\'_1\'] = \'colspan="\'.($start_cell-$akt_cell).\'"\';
					}

					$opt = &$row[\'.\'.$event[\'id\'].\'_2\'];
					$cel = &$row[$event[\'id\'].\'_2\'];
					if ($start_cell < $end_cell)
					{
						$opt .= "colspan=".(1 + $end_cell - $start_cell);
					}

					if ($bgcolor=$cat[\'color\'])
					{
						$opt .= \' bgcolor="\'.$bgcolor.\'"\';
					}
					$opt .= \' title="\'.$event[\'title\'];
					if ($event[\'description\'])
					{
						$opt .= " \n".$event[\'description\'];
					}
					$opt .= \'" onClick="location=\\\'\'.$view.\'\\\'"\';
					$cel = \'<a href="\'.$view.\'">\';
					if ($event[\'priority\'] == 3)
					{
						$cel .= $html->image(\'calendar\',\'mini-calendar-bar.gif\',\'\',\'border="0"\');
					}
					$cel .= $html->image(\'calendar\',count($event[\'participants\'])>1?\'multi_3.gif\':\'single.gif\',$this->planner_participants($event[\'participants\']),\'border="0"\');
					$cel .= \'</a>\';

					$akt_cell = $end_cell + 1;
				}
			}
			ksort($rows);
			while (list($k,$r) = each($rows))
			{
				if (is_array($r))
				{
					$rows[\'.\'.$k] = \'bgcolor="\'.$GLOBALS[\'phpgw\']->nextmatchs->alternate_row_color().\'"\';
					$row = &$rows[$k];
					$akt_cell = &$rows[\'.nr_\'.$k];
					if ($akt_cell <= $last_cell)
					{
						$row[\'3\'] = \'&nbsp\';
						$row[\'.3\'] = \'colspan="\'.(1+$last_cell-$akt_cell).\'"\';
					}
				}
			}
			$bgcolor = \'bgcolor="\'.$this->theme[\'th_bg\'].\'"\';

			if ($this->debug)
			{
				_debug_array($rows);
				reset($rows);
			}
			echo $html->table(
				array(
					\'_h\' => $header,
					\'._h\' => $bgcolor
				)+$rows,
				\'width="100%" cols="\'.(1+$days*$intervals_per_day).\'"\'
			);
			');
		}

		}

		function matrixselect()
		{
			$datetime = mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year) - $this->tz_offset;

			$sb = CreateObject('phpgwapi.sbox');
	
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'mq'		=> 'matrix_query.tpl',
					'form_button'	=> 'form_button_script.tpl'
				)
			);
			$p->set_block('mq','matrix_query','matrix_query');
			$p->set_block('mq','list','list');

			$vars = Array(
				'matrix_action'	=> lang('Daily Matrix View'),
				'action_url'	=> $this->page('viewmatrix')
			);

			$p->set_var($vars);

// Date
			$var[] = Array(
				'field'	=>	lang('Date'),
				'data'	=>	$GLOBALS['phpgw']->common->dateformatorder(
					$sb->getYears('year',intval($GLOBALS['phpgw']->common->show_date($datetime,'Y')),intval($GLOBALS['phpgw']->common->show_date($datetime,'Y'))),
					$sb->getMonthText('month',intval($GLOBALS['phpgw']->common->show_date($datetime,'n'))),
					$sb->getDays('day',intval($GLOBALS['phpgw']->common->show_date($datetime,'d')))
				)
			);
	
// View type
			$var[] = Array(
				'field'	=>	lang('View'),
				'data'	=>	'<select name="matrixtype">'."\n"
					. '<option value="free/busy" selected>'.lang('free/busy').'</option>'."\n"
					. '<option value="weekly">'.lang('Weekly').'</option>'."\n"
					. '</select>'."\n"
			);

// Participants
			$accounts = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,'calendar');
			$users = Array();
			for($i=0;$i<count($accounts);$i++)
			{
				$user = $accounts[$i];
				if(!isset($users[$user]))
				{
					$users[$user] = $GLOBALS['phpgw']->common->grab_owner_name($user);
					if($GLOBALS['phpgw']->accounts->get_type($user) == 'g')
					{
						$group_members = $GLOBALS['phpgw']->acl->get_ids_for_location($user,1,'phpgw_group');
						if($group_members != False)
						{
							for($j=0;$j<count($group_members);$j++)
							{
								if(!isset($users[$group_members[$j]]))
								{
									$users[$group_members[$j]] = $GLOBALS['phpgw']->common->grab_owner_name($group_members[$j]);
								}
							}
						}
					}
				}
			}

			$num_users = count($users);

			if ($num_users > 50)
			{
				$size = 15;
			}
			elseif ($num_users > 5)
			{
				$size = 5;
			}
			else
			{
				$size = $num_users;
			}
			$str = '';
			@asort($users);
			@reset($users);
			while ($user = each($users))
			{
				if(($GLOBALS['phpgw']->accounts->exists($user[0]) && $this->bo->check_perms(PHPGW_ACL_READ,$user[0])) || $GLOBALS['phpgw']->accounts->get_type($user[0]) == 'g')
				{
					$str .= '    <option value="'.$user[0].'">('.$GLOBALS['phpgw']->accounts->get_type($user[0]).') '.$user[1].'</option>'."\n";
				}
			}
			$var[] = Array(
				'field'	=>	lang('Participants'),
				'data'	=>	"\n".'   <select name="participants[]" multiple size="'.$size.'">'."\n".$str.'   </select>'."\n"
			);

			for($i=0;$i<count($var);$i++)
			{
				$this->output_template_array($p,'rows','list',$var[$i]);
			}
			
			$vars = Array(
				'submit_button'		=> lang('Submit'),
				'action_url_button'	=> '',
				'action_text_button'	=> lang('Cancel'),
				'action_confirm_button'	=> 'onClick="history.back(-1)"',
				'action_extra_field'	=> ''
			);

			$p->set_var($vars);
			$p->parse('cancel_button','form_button');
			$p->pparse('out','matrix_query');
		}

		function viewmatrix()
		{
			$participants = $GLOBALS['HTTP_POST_VARS']['participants'];
			$parts = Array();
			$acct = CreateObject('phpgwapi.accounts',$this->bo->owner);
			$c_participants = count($participants);
			for($i=0;$i<$c_participants;$i++)
			{
				switch ($GLOBALS['phpgw']->accounts->get_type($participants[$i]))
				{
					case 'g':
						$members = $acct->members(intval($participants[$i]));
						while($members != False && list($index,$member) = each($members))
						{
							if($this->bo->check_perms(PHPGW_ACL_READ,$member['account_id']) && !isset($parts[$member['account_id']]))
							{
								$parts[$member['account_id']] = 1;
							}
						}
						break;
					case 'u':
						if($this->bo->check_perms(PHPGW_ACL_READ,$participants[$i]) && !isset($parts[$participants[$i]]))
						{
							$parts[$participants[$i]] = 1;
						}
						break;
				}
			}
			unset($acct);

			$participants = Array();
			reset($parts);
			while(list($key,$value) = each($parts))
			{
				$participants[] = $key;
			}

			reset($participants);

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			switch($GLOBALS['HTTP_POST_VARS']['matrixtype'])
			{
				case 'free/busy':
					$freetime = $this->bo->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year);
					echo $this->timematrix(
						Array(
							'date'		=> $freetime,
							'starttime'	=> $this->bo->splittime('000000',False),
							'endtime'	=> 0,
							'participants'	=> $parts
						)
					);
					break;
				case 'weekly':
					echo $this->display_weekly(
						Array(
							'date'		=> sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day),
							'showyear'	=> true,
							'owners'	=> $participants
						)
					);
					break;
			}
			echo "\n".'<center>'."\n";
			echo ' <form action="'.$this->page('viewmatrix').'" method="post" name="matrixform" target="viewmatrix">'."\n";
			echo '  <input type="hidden" name="year" value="'.$this->bo->year.'">'."\n";
			echo '  <input type="hidden" name="month" value="'.$this->bo->month.'">'."\n";
			echo '  <input type="hidden" name="day" value="'.$this->bo->day.'">'."\n";
			echo '  <input type="hidden" name="matrixtype" value="'.$GLOBALS['HTTP_POST_VARS']['matrixtype'].'">'."\n";
			reset($parts);
			while(list($key,$value) = each($parts))
			{
				echo '  <input type="hidden" name="participants[]" value="'.$key.'">'."\n";
			}
			echo '  <input type="submit" value="'.lang('refresh').'">'."\n";
			echo ' </form>'."\n";
			echo '</center>'."\n";
		}

		function search()
		{
			if (!$GLOBALS['HTTP_POST_VARS']['keywords'])
			{
				// If we reach this, it is because they didn't search for anything,
				// attempt to send them back to where they where.
				Header('Location: ' . $phpgw->link($from));
				$GLOBALS['phpgw']->common->phpgw_exit();
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$error = '';

			if (strlen($GLOBALS['HTTP_POST_VARS']['keywords']) == 0)
			{
				echo '<b>'.lang('Error').':</b>';
				echo lang('You must enter one or more search keywords.');
				return;
			}
	
			$matches = 0;

			// There is currently a problem searching in with repeated events.
			// It spits back out the date it was entered.  I would like to to say that
			// it is a repeated event.

			// This has been solved by the little icon indicator for recurring events.

			$event_ids = $this->bo->search_keywords($GLOBALS['HTTP_POST_VARS']['keywords']);
			$ids = Array();
			while(list($key,$id) = each($event_ids))
			{
				$event = $this->bo->read_entry($id);
				
				$datetime = $this->bo->maketime($event['start']) - $this->tz_offset;
				
				$ids[strval($event['id'])]++;
				$info[strval($event['id'])] = $GLOBALS['phpgw']->common->show_date($datetime).$this->link_to_entry($event,$event['start']['month'],$event['start']['mday'],$event['start']['year']);

			}
			$matches = count($event_ids);

			if ($matches > 0)
			{
				$matches = count($ids);
			}

			if ($matches == 1)
			{
				$quantity = lang('1 match found').'.';
			}
			elseif ($matches > 0)
			{
				$quantity = lang('x matches found',$matches).'.';
			}
			else
			{
				echo '<b>'.lang('Error').':</b>'.lang('no matches found.');
				return;
			}

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'search_form'	=> 'search.tpl'
				)
			);
			$p->set_block('search_form','search','search');
			$p->set_block('search_form','search_list_header','search_list_header');
			$p->set_block('search_form','search_list','search_list');
			$p->set_block('search_form','search_list_footer','search_list_footer');
	
			$var = Array(
				'color'		=> $this->theme['bg_text'],
				'search_text'	=> lang('Search Results'),
				'quantity'	=> $quantity
			);
			$p->set_var($var);

			if($matches > 0)
			{
				$p->parse('rows','search_list_header',True);
			}
			// now sort by number of hits
			arsort($ids);
			for(reset($ids);$key=key($ids);next($ids))
			{
				$p->set_var('result_desc',$info[$key]);
				$p->parse('rows','search_list',True);
			}
	
			if($matches > 0)
			{
				$p->parse('rows','search_list_footer',True);
			}

			$p->pparse('out','search');
		}

		/* Private functions */
		function _debug_sqsof()
		{
			$data = array(
				'filter'=> $this->bo->filter,
				'cat_id'=> $this->bo->cat_id,
				'owner'	=> $this->bo->owner,
				'year'	=> $this->bo->year,
				'month'	=> $this->bo->month,
				'day'	=> $this->bo->day
			);
			echo '<br>UI:';
			_debug_array($data);
		}

		/* Called only by get_list(), just prior to page footer. */
		function save_sessiondata()
		{
			$data = array(
				'filter'=> $this->bo->filter,
				'cat_id'=> $this->bo->cat_id,
				'owner'	=> $this->bo->owner,
				'year'	=> $this->bo->year,
				'month'	=> $this->bo->month,
				'day'	=> $this->bo->day
			);
			$this->bo->save_sessiondata($data);
		}

		function output_template_array(&$p,$row,$list,$var)
		{
			$p->set_var($var);
			$p->parse($row,$list,True);
		}

		function page($_page='',$params='')
		{
			if($_page == '')
			{
				$page_ = explode('.',$this->bo->prefs['calendar']['defaultcalendar']);
				$_page = $page_[0];
				if ($_page=='index' || ($_page != 'day' && $_page != 'week' && $_page != 'month' && $_page != 'year'))
				{
					$_page = 'month';
					$GLOBALS['phpgw']->preferences->add('calendar','defaultcalendar','month');
					$GLOBALS['phpgw']->preferences->save_repository();
				}
			}
			if($GLOBALS['phpgw_info']['flags']['currentapp'] == 'home')
			{
				$page_app = 'calendar';
			}
			else
			{
				$page_app = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			return $GLOBALS['phpgw']->link('/index.php','menuaction='.$page_app.'.ui'.$page_app.'.'.$_page.$params);
		}

		function header()
		{
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
			list(,,$method) = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
		
			if (@$this->bo->printer_friendly)
			{
			   return;
			}

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
	
			$p->set_file(
				Array(
					'footer'	=> 'footer.tpl',
					'form_button'	=> 'form_button_script.tpl'
				)
			);
			$p->set_block('footer','footer_table','footer_table');
			$p->set_block('footer','footer_row','footer_row');
			$p->set_block('footer','blank_row','blank_row');

			$m = $this->bo->month;
			$y = $this->bo->year;

			$thisdate = date('Ymd',mktime(0,0,0,$m,1,$y));
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
				$d_ymd = date('Ymd',$d);
				$str .= '<option value="'.$d_ymd.'"'.($d_ymd == $thisdate?' selected':'').'>'.lang(date('F', $d)).strftime(' %Y', $d).'</option>'."\n";
			}

			$var = Array(
				'action_url'	=> $this->page($method,''),
				'form_name'	=> 'SelectMonth',
				'label'		=> lang('Month'),
				'form_label'	=> 'date',
				'form_onchange'	=> 'document.SelectMonth.submit()',
				'row'		=> $str,
				'go'		=> lang('Go!')
			);
			$this->output_template_array($p,'table_row','footer_row',$var);

			unset($thisdate);
			$thisdate = mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year) - $this->tz_offset;
			$sun = $this->bo->datetime->get_weekday_start($this->bo->year,$this->bo->month,$this->bo->day) - $this->tz_offset - 7200;

			$str = '';
			for ($i = -7; $i <= 7; $i++)
			{
				$begin = $sun + (604800 * $i);
				$end = $begin + 604799;
				$str .= '<option value="' . $GLOBALS['phpgw']->common->show_date($begin,'Ymd') . '"'.($begin <= $thisdate && $end >= $thisdate?' selected':'')
				   .'>' . lang($GLOBALS['phpgw']->common->show_date($begin,'F')) . ' ' . $GLOBALS['phpgw']->common->show_date($begin,'d') . '-'
					. lang($GLOBALS['phpgw']->common->show_date($end,'F')) . ' ' . $GLOBALS['phpgw']->common->show_date($end,'d') . '</option>'."\n";
			}
 
			$var = Array(
				'action_url'	=> $this->page($method,''),
				'form_name'	=> 'SelectWeek',
				'label'		=> lang('Week'),
				'form_label'	=> 'date',
				'form_onchange'	=> 'document.SelectWeek.submit()',
				'row'		=> $str,
				'go'		=> lang('Go!')
			);

			$this->output_template_array($p,'table_row','footer_row',$var);

			$str = '';
			for ($i = ($this->bo->year - 3); $i < ($this->bo->year + 3); $i++)
			{
				$str .= '<option value="'.$i.'"'.($i == $this->bo->year?' selected':'').'>'.$i.'</option>'."\n";
			}
  
			$var = Array(
				'action_url'	=> $this->page($method,''),
				'form_name'	=> 'SelectYear',
				'label'		=> lang('Year'),
				'form_label'	=> 'year',
				'form_onchange'	=> 'document.SelectYear.submit()',
				'row'		=> $str,
				'go'		=> lang('Go!')
			);
			$this->output_template_array($p,'table_row','footer_row',$var);

			$var = Array(
				'submit_button'		=> lang('Submit'),
				'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uiicalendar.import'),
				'action_text_button'	=> lang('Import'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$this->output_template_array($p,'b_row','form_button',$var);
			$p->parse('table_row','blank_row',True);

			$p->pparse('out','footer_table');
			unset($p);
		}

		function css()
		{
			return 'A.minicalendar { color: #000000 }'."\n"
				. '  A.bminicalendar { color: #336699; font-weight: bold; font-style: italic }'."\n"
				. '  A.minicalendargrey { color: #999999 }'."\n"
				. '  A.bminicalendargrey { color: #336699; font-weight: bold; font-style: italic }'."\n"
				. '  A.minicalhol { color: #000000; background-color: '.$this->holiday_color.' }'."\n"
				. '  A.bminicalhol { color: #336699; background-color: '.$this->holiday_color.'; font-weight: bold; font-style: italic }'."\n"
				. '  A.minicalgreyhol { color: #999999; background-color: '.$this->holiday_color.' }'."\n"
				. '  A.bminicalgreyhol { color: #999999; background-color: '.$this->holiday_color.'; font-weight: bold; font-style: italic }'."\n";
		}

		function no_edit()
		{
			if(!$isset($GLOBALS['phpgw_info']['flags']['noheader']))
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
				$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
				$GLOBALS['phpgw']->common->phpgw_header();
			}
			echo '<center>You do not have permission to edit this appointment!</center>';
			return;
		}

		function link_to_entry($event,$month,$day,$year)
		{
			$str = '';
			$is_private = $this->bo->is_private($event,$this->bo->owner);
			$editable = ((!$this->bo->printer_friendly) && (($is_private && $this->bo->check_perms(PHPGW_ACL_PRIVATE)) || !$is_private));
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');
			$p->set_file(
			   Array(
				   'link_picture'	=> 'link_pict.tpl'
			   )
			);
			$p->set_block('link_picture','link_pict','link_pict');
			$p->set_block('link_picture','pict','pict');
			$p->set_block('link_picture','link_open','link_open');
			$p->set_block('link_picture','link_close','link_close');
			$p->set_block('link_picture','link_text','link_text');

			$starttime = $this->bo->maketime($event['start']) - $this->tz_offset;
			$endtime = $this->bo->maketime($event['end']) - $this->tz_offset;
			$rawdate = mktime(0,0,0,$month,$day,$year);
			$rawdate_offset = $rawdate - $this->tz_offset;
			$nextday = mktime(0,0,0,$month,$day + 1,$year) - $this->tz_offset;
			if (intval($GLOBALS['phpgw']->common->show_date($starttime,'Hi')) && $starttime == $endtime)
			{
				$time = $GLOBALS['phpgw']->common->show_date($starttime,$this->bo->users_timeformat);
			}
			elseif ($starttime <= $rawdate_offset && $endtime >= $nextday - 60)
			{
				$time = '[ '.lang('All Day').' ]';
			}
			elseif (intval($GLOBALS['phpgw']->common->show_date($starttime,'Hi')) || $starttime != $endtime)
			{
				if($starttime < $rawdate_offset && $event['recur_type']==MCAL_RECUR_NONE)
				{
					$start_time = $GLOBALS['phpgw']->common->show_date($rawdate_offset,$this->bo->users_timeformat);
				}
				else
				{
					$start_time = $GLOBALS['phpgw']->common->show_date($starttime,$this->bo->users_timeformat);
				}

				if($endtime >= ($rawdate_offset + 86400))
				{
					$end_time = $GLOBALS['phpgw']->common->show_date(mktime(23,59,59,$month,$day,$year) - $this->tz_offset,$this->bo->users_timeformat);
				}
				else
				{
					$end_time = $GLOBALS['phpgw']->common->show_date($endtime,$this->bo->users_timeformat);
				}
				$time = $start_time.'-'.$end_time;
			}
			else
			{
				$time = '';
			}
			$text = '';;
			if(!$is_private)
			{
				$text .= $this->bo->display_status($event['users_status']);
			}
			$text = '<font size="-2" face="'.$this->theme['font'].'"><nobr>'.$time.'</nobr>&nbsp;'.$this->bo->get_short_field($event,$is_private,'title').$text.'</font>'.$GLOBALS['phpgw']->browser->br;
		
			if ($editable)
			{
				$date = sprintf('%04d%02d%02d',$year,$month,$day);
				$p->set_var('link_link',$this->page('view','&cal_id='.$event['id'].'&date='.$date));
				$p->set_var('lang_view',lang('View this entry'));
				$p->parse('picture','link_open',True);
			
				if($event['priority'] == 3)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','high.gif'),
						'width'	=> 8,
						'height'=> 17
					);
				}
				if($event['recur_type'] == MCAL_RECUR_NONE)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','circle.gif'),
						'width'	=> 5,
						'height'=> 7
					);
				}
				else
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','recur.gif'),
						'width'	=> 12,
						'height'=> 12
					);
				}
				if(count($event['participants']) > 1)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','multi_3.gif'),
						'width'	=> 14,
						'height'=> 14
					);
				}
				if($event['public'] == 0)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','private.gif'),
						'width'	=> 13,
						'height'=> 13
					);
				}
				if(@isset($event['alarm']) && count($event['alarm']) >= 1)
				{
					if($this->bo->alarm_today($event,$rawdate_offset,$starttime))
					{
						$picture[] = Array(
							'pict'	=> $GLOBALS['phpgw']->common->image('calendar','alarm.gif'),
							'width'	=> 13,
							'height'=> 13
						);
					}
				}

   			$description = $this->bo->get_short_field($event,$is_private,'description');
				for($i=0;$i<count($picture);$i++)
				{
					$var = Array(
						'pic_image'	=> $picture[$i]['pict'],
						'width'		=> $picture[$i]['width'],
						'height'	=> $picture[$i]['height'],
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

		function overlap($params)
		{

			if(!is_array($params))
			{
			}
			else
			{
				$overlapping_events = $params['o_events'];
				$event = $params['this_event'];
			}

			$month = $event['start']['month'];
			$mday = $event['start']['mday'];
			$year = $event['start']['year'];

			$start = mktime($event['start']['hour'],$event['start']['min'],$event['start']['sec'],$month,$mday,$year) - $this->tz_offset;
			$end = $this->bo->maketime($event['end']) - $this->tz_offset;

			$overlap = '';
			for($i=0;$i<count($overlapping_events);$i++)
			{
				$overlap .= '<li>'.$this->link_to_entry($this->bo->read_entry($overlapping_events[$i]),$month,$mday,$year);
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'overlap'	=> 'overlap.tpl',
   					'form_button'	=> 'form_button_script.tpl'
				)
			);

			$var = Array(
				'color'		=> $this->theme['bg_text'],
				'overlap_title' => lang('Scheduling Conflict'),
				'overlap_text'	=> lang('Your suggested time of <B> x - x </B> conflicts with the following existing calendar entries:',$GLOBALS['phpgw']->common->show_date($start),$GLOBALS['phpgw']->common->show_date($end)),
				'overlap_list'	=> $overlap
			);
			$p->set_var($var);

         $date = sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->mday);
			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.bocalendar.update','readsess'=>1)),
				'action_text_button'	=> lang('Ignore Conflict'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$this->output_template_array($p,'resubmit_button','form_button',$var);

			$var = Array(
				'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.uicalendar.edit','readsess'=>1,'date'=>$date)),
				'action_text_button'	=> lang('Re-Edit Event'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$this->output_template_array($p,'reedit_button','form_button',$var);
			$p->pparse('out','overlap');
		}

		function planner_participants($parts)
		{
			static $id2lid;
			
			$names = '';
			while (list($id,$status) = each($parts))
			{
				if (!isset($id2lid[$id]))
				{
					$id2lid[$id] = $GLOBALS['phpgw']->common->grab_owner_name($id);
				}
				if (strlen($names))
				{
					$names .= ",\n";
				}
				$names .= $id2lid[$id]." ($status)";
			}
			if($this->debug)
			{
				echo "Inside participants() : $names<br>\n";
			}
			return $names;
		}
			
		function planner_category($ids)
		{
			static $cats;
			if(!is_array($ids))
			{
				if (strpos($ids,','))
				{
					$id_array = explode(',',$ids);
				}
				else
				{
					$id_array[0] = $ids;
				}
			}
			@reset($id_array);
			$ret_val = Array();
			while(list($index,$id) = each($id_array))
			{
				if (!isset($cats[$id]))
				{
					$cat_arr = $this->cat->return_single( $id );
					$cats[$id] = $cat_arr[0];
					$cats[$id]['color'] = strstr($cats[$id]['description'],'#');
				}
				$ret_val[] = $cats[$id];
			}
			return $ret_val;
		}

		function week_header($month,$year,$display_name = False)
		{
			$this->weekstarttime = $this->bo->datetime->get_weekday_start($year,$month,1);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');
			$p->set_file(
				Array (
					'month_header' => 'month_header.tpl'
				)
			);
			$p->set_block('month_header','monthly_header','monthly_header');
			$p->set_block('month_header','column_title','column_title');
		
			$var = Array(
				'bgcolor'	=> $this->theme['th_bg'],
				'font_color'	=> $this->theme['th_text']
			);
			if($this->bo->printer_friendly && @$this->bo->prefs['calendar']['print_black_white'])
			{
				$var = Array(
					'bgcolor'	=> '',
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
			if($owner == 0) { $owner = $GLOBALS['phpgw_info']['user']['account_id']; }

			$temp_owner = $this->bo->owner;
			$this->bo->owner = $owner;

			$str = '';
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('keep');
		
			$p->set_file(
			   Array (
				'month_header'	=> 'month_header.tpl',
   				'month_day'	=> 'month_day.tpl'
	   		)
	   	);
			$p->set_block('month_header','monthly_header','monthly_header');
			$p->set_block('month_header','month_column','month_column');
			$p->set_block('month_day','month_daily','month_daily');
			$p->set_block('month_day','day_event','day_event');
			$p->set_block('month_day','event','event');
		
			$p->set_var('extra','');
			$p->set_var('col_width','14');
			if($display_name)
			{
				$p->set_var('column_data',$GLOBALS['phpgw']->common->grab_owner_name($owner));
				$p->parse('column_header','month_column',True);
				$p->set_var('col_width','12');
			}
			$today = date('Ymd',time());
			$daily = $this->set_week_array($startdate,$cellcolor,$weekly);
			@reset($daily);
			while(list($date,$day_params) = each($daily))
			{
				$year = intval(substr($date,0,4));
				$month = intval(substr($date,4,2));
				$day = intval(substr($date,6,2));
				$var = Array(
					'column_data'	=> '',
					'extra'		=> ''
				);
				$p->set_var($var);
				if ($weekly || ($date >= $monthstart && $date <= $monthend))
				{
					if ($day_params['new_event'])
					{
						$new_event_link = '<a href="'.$this->page('add','&date='.$date).'">'
							. '<img src="'.$GLOBALS['phpgw']->common->image('calendar','new.gif').'" width="10" height="10" alt="'.lang('New Entry').'" border="0" align="center">'
							. '</a>';
						$day_number = '<a href="'.$this->page('day','&date='.$date).'">'.$day.'</a>';
					}
					else
					{
						$new_event_link = '';
						$day_number = $day;
					}

					$var = Array(
						'extra'		=> $day_params['extra'],
						'new_event_link'=> $new_event_link,
						'day_number'	=> $day_number
					);

					$p->set_var($var);
				
					if(@$day_params['holidays'])
					{
						reset($day_params['holidays']);
						while(list($key,$value) = each($day_params['holidays']))
						{
							$var = Array(
								'day_events' => '<font face="'.$this->theme['font'].'" size="-1">'.$value.'</font>'.$GLOBALS['phpgw']->browser->br
							);
							$this->output_template_array($p,'daily_events','event',$var);
						}
					}

					if($day_params['appts'])
					{
						$var = Array(
							'week_day_font_size'	=> '2',
							'events'		=> ''
						);
						$p->set_var($var);
						$rep_events = $this->bo->cached_events[$date];
						$c_rep_events = count($rep_events);
						for ($k=0;$k<$c_rep_events;$k++)
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
						$var = Array(
							'week_day_font_size'	=> '-2',
							'events'		=> (!$this->bo->printer_friendly?'<a href="'.$this->page('week','&date='.$date).'">' .$day_params['week'].'</a>':$day_params['week'])
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
			$this->bo->store_to_cache(
				Array(
					'syear'	=> $year,
					'smonth'=> $month,
					'sday'	=> 1
				)
			);

			$monthstart = intval(date('Ymd',mktime(0,0,0,$month    ,1,$year)));
			$monthend   = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

			$start = $this->bo->datetime->get_weekday_start($year, $month, 1);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('keep');
		
			$p->set_file(
				Array(
					'week'	=>	'month_day.tpl'
   				)
			);
			$p->set_block('week','m_w_table','m_w_table');
			$p->set_block('week','event','event');


			$var = Array(
				'cols'      => 7,
				'day_events'=> $this->week_header($month,$year,False)
			);
			$this->output_template_array($p,'row','event',$var);

			$cellcolor = $this->theme['row_on'];

			for ($i=intval($start);intval(date('Ymd',$i)) <= $monthend;$i += 604800)
			{
				$cellcolor = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($cellcolor);
				$var = Array(
					'day_events' => $this->display_week($i,False,$cellcolor,False,$owner,$monthstart,$monthend)
				);
				$this->output_template_array($p,'row','event',$var);
			}
			return $p->fp('out','m_w_table');
		}

		function display_weekly($params)
		{
			if(!is_array($params))
			{
				$this->index();
			}

			$year = substr($params['date'],0,4);
			$month = substr($params['date'],4,2);
			$day = substr($params['date'],6,2);
			$showyear = $params['showyear'];
			$owners = $params['owners'];
			
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('keep');

			$p->set_file(
				Array(
					'week'	=> 'month_day.tpl'
				)
			);
			$p->set_block('week','m_w_table','m_w_table');
			$p->set_block('week','event','event');
		
			$start = $this->bo->datetime->get_weekday_start($year, $month, $day);

			$cellcolor = $this->theme['row_off'];

			$true_printer_friendly = $this->bo->printer_friendly;

			if(is_array($owners))
			{
				$display_name = True;
				$counter = count($owners);
				$owners_array = $owners;
				$cols = 8;
			}
			else
			{
				$display_name = False;
				$counter = 1;
				$owners_array[0] = $owners;
				$cols = 7;
			}
			$var = Array(
			   'cols'         => $cols,
			   'day_events'   => $this->week_header($month,$year,$display_name)
			);
			$this->output_template_array($p,'row','event',$var);

			$original_owner = $this->bo->so->owner;
			for($i=0;$i<$counter;$i++)
			{
				$this->bo->so->owner = $owners_array[$i];
				$this->bo->store_to_cache(
					Array(
						'syear'	=> $year,
						'smonth'=> $month,
						'sday'	=> 1
					)
				);
				$p->set_var('day_events',$this->display_week($start,True,$cellcolor,$display_name,$owners_array[$i]));
				$p->parse('row','event',True);
			}
			$this->bo->so->owner = $original_owner;
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
			if(!$event['participants'][$this->bo->owner])
			{
				return '<center>'.lang('You do not have permission to read this record!').'</center>';
			}

			$pri = Array(
  				1	=> lang('Low'),
  				2	=> lang('Normal'),
		  		3	=> lang('High')
			);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);

			$p->set_unknowns('keep');
			$p->set_file(
				Array(
  					'view'	=> 'view.tpl'
   				)
			);
			$p->set_block('view','view_event','view_event');
			$p->set_block('view','list','list');
			$p->set_block('view','hr','hr');

			$var = Array(
				'bg_text'	=> $this->theme['bg_text'],
				'name'		=> $event['title']
			);
			$p->set_var($var);
			unset($var);

			// Some browser add a \n when its entered in the database. Not a big deal
			// this will be printed even though its not needed.
			if ($event['description'])
			{
				$var[] = Array(
					'field'	=> lang('Description'),
					'data'	=> $event['description']
				);
			}

			if ($event['category'])
			{
				$category = Array();
				$this->cat->categories($this->bo->owner,'calendar');
				if(strpos($event['category'],','))
				{
					$category = explode(',',$event['category']);
				}
				else
				{
					$category[] = $event['category'];
				}
				@reset($category);
				while(list($key,$cat) = each($category))
				{
					$_cat = $this->cat->return_single($cat);
					$cat_string[] = $_cat[0]['name'];
				}
				@reset($cat_string);
				$var[] = Array(
					'field'	=> lang('Category'),
					'data'	=> implode(',',$cat_string)
				);
			}

			if ($event['location'])
			{
				$var[] = Array(
					'field'	=> lang('Location'),
					'data'	=> $event['location']
				);
			}
			$var[] = Array(
				'field'	=> lang('Start Date/Time'),
				'data'	=> $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['start']) - $this->tz_offset)
			);
	
			$var[] = Array(
				'field'	=> lang('End Date/Time'),
				'data'	=> $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['end']) - $this->tz_offset)
			);

			$var[] = Array(
				'field'	=> lang('Priority'),
				'data'	=> $pri[$event['priority']]
			);

			$var[] = Array(
				'field'	=> lang('Created By'),
				'data'	=> $GLOBALS['phpgw']->common->grab_owner_name($event['owner'])
			);
	
			$var[] = Array(
				'field'	=> lang('Updated'),
				'data'	=> $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['modtime']) - $this->tz_offset)
			);

			$var[] = Array(
				'field'	=> lang('Private'),
				'data'	=> $event['public']==True?'False':'True'
			);

			if(@isset($event['groups'][0]))
			{
				$cal_grps = '';
				for($i=0;$i<count($event['groups']);$i++)
				{
					if($GLOBALS['phpgw']->accounts->exists($event['groups'][$i]))
					{
						$cal_grps .= ($i>0?'<br>':'').$GLOBALS['phpgw']->accounts->id2name($event['groups'][$i]);
					}
				}
	
				$var[] = Array(
					'field'	=> lang('Groups'),
					'data'	=> $cal_grps
				);
			}

			$str = '';
			reset($event['participants']);
			while (list($user,$short_status) = each($event['participants']))
			{
				if($GLOBALS['phpgw']->accounts->exists($user))
				{
					$str .= ($str?'<br>':'').$GLOBALS['phpgw']->common->grab_owner_name($user).' ('.($this->bo->check_perms(PHPGW_ACL_EDIT,$user)?'<a href="'.$this->page('edit_status','&cal_id='.$event['id'].'&owner='.$user).'">'.$this->bo->get_long_status($short_status).'</a>':$this->bo->get_long_status($short_status)).')'."\n";
				}
			}
			$var[] = Array(
				'field'	=> lang('Participants'),
				'data'	=> $str
			);

			// Repeated Events
			$rpt_type = Array(
				MCAL_RECUR_NONE		=> 'none',
				MCAL_RECUR_DAILY	=> 'daily',
				MCAL_RECUR_WEEKLY	=> 'weekly',
				MCAL_RECUR_MONTHLY_WDAY	=> 'monthlybyday',
				MCAL_RECUR_MONTHLY_MDAY	=> 'monthlybydate',
				MCAL_RECUR_YEARLY	=> 'yearly'
			);
			$str = lang($rpt_type[$event['recur_type']]);
			if($event['recur_type'] <> MCAL_RECUR_NONE)
			{
				$str_extra = '';
				if ($event['recur_enddate']['mday'] != 0 && $event['recur_enddate']['month'] != 0 && $event['recur_enddate']['year'] != 0)
				{
					$recur_end = $this->bo->maketime($event['recur_enddate']);
					if($recur_end != 0)
					{
						$recur_end -= $this->tz_offset;
						$str_extra .= lang('ends').': '.lang($GLOBALS['phpgw']->common->show_date($recur_end,'l')).', '.lang($GLOBALS['phpgw']->common->show_date($recur_end,'F')).' '.$GLOBALS['phpgw']->common->show_date($recur_end,'d, Y').' ';
					}
				}
				if($event['recur_type'] == MCAL_RECUR_WEEKLY || $event['recur_type'] == MCAL_RECUR_DAILY)
				{
					$repeat_days = '';
					if($this->bo->prefs['calendar']['weekdaystarts'] == 'Sunday')
					{
						if (!!($event['recur_data'] & MCAL_M_SUNDAY) == True)
						{
							$this->view_add_day(lang('Sunday'),$repeat_days);
						}
					}
					if (!!($event['recur_data'] & MCAL_M_MONDAY) == True)
					{
						$this->view_add_day(lang('Monday'),$repeat_days);
					}
					if (!!($event['recur_data'] & MCAL_M_TUESDAY) == True)
					{
						$this->view_add_day(lang('Tuesday'),$repeat_days);
					}
					if (!!($event['recur_data'] & MCAL_M_WEDNESDAY) == True)
					{
						$this->view_add_day(lang('Wednesday'),$repeat_days);
					}
					if (!!($event['recur_data'] & MCAL_M_THURSDAY) == True)
					{
						$this->view_add_day(lang('Thursday'),$repeat_days);
					}
					if (!!($event['recur_data'] & MCAL_M_FRIDAY) == True)
					{
						$this->view_add_day(lang('Friday'),$repeat_days);
					}
					if (!!($event['recur_data'] & MCAL_M_SATURDAY) == True)
					{
						$this->view_add_day(lang('Saturday'),$repeat_days);
					}
					if($this->bo->prefs['calendar']['weekdaystarts'] == 'Monday')
					{
						if (!!($event['recur_data'] & MCAL_M_SUNDAY) == True)
						{
							$this->view_add_day(lang('Sunday'),$repeat_days);
						}
					}
					if($repeat_days <> '')
					{
						$str_extra .= lang('days repeated').': '.$repeat_days;
					}
				}
				if($event['recur_interval'] != 0)
				{
					$str_extra .= lang('Interval').': '.$event['recur_interval'];
				}

				if($str_extra)
				{
					$str .= ' ('.$str_extra.')';
				}

				$var[] = Array(
					'field'	=> lang('Repetition'),
					'data'	=> $str
				);
			}

			for($i=0;$i<count($var);$i++)
			{
				$this->output_template_array($p,'row','list',$var[$i]);
			}

			if(@isset($event['alarm']))
			{
				$p->set_var('hr_text','<hr>');
				$p->parse('row','hr',True);
				$p->set_var('hr_text','<center><b>'.lang('Alarms').'</b></center><br>');
				$p->parse('row','hr',True);

				@reset($event['alarm']);
				while(list($key,$alarm) = each($event['alarm']))
				{
					$icon = '<img src="'.$GLOBALS['phpgw']->common->image('calendar',($alarm['enabled']?'enabled.gif':'disabled.gif')).'" width="13" height="13">';
					$var = Array(
						'field'	=> $icon.$GLOBALS['phpgw']->common->show_date($alarm['time']),
						'data'	=> $alarm['text']
					);
					$this->output_template_array($p,'row','list',$var);
				}
			}

			$p->set_var('hr_text','<hr>');
			$p->parse('row','hr',True);

			return $p->fp('out','view_event');
		}

		function print_day($params)
		{
			if(!is_array($params))
			{
				$this->index();
			}

			$this->bo->store_to_cache(
				Array(
					'syear'	=> $params['year'],
					'smomth'=> $params['month'],
					'sday'	=> $params['day'],
					'eyear'	=> $params['year'],
					'emonth'=> $params['month'],
					'eday'	=> $params['day']
				)
			);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('keep');

			$templates = Array(
				'day_cal'	=> 'day_cal.tpl'
			);
   	   $p->set_file($templates);
			$p->set_block('day_cal','day','day');
			$p->set_block('day_cal','day_row','day_row');
			$p->set_block('day_cal','day_event','day_event');
			$p->set_block('day_cal','day_time','day_time');

			if (! $this->bo->prefs['calendar']['workdaystarts'] &&
				 ! $this->bo->prefs['calendar']['workdayends'])
			{
				
				$GLOBALS['phpgw']->preferences->add('calendar','workdaystarts',8);
				$GLOBALS['phpgw']->preferences->add('calendar','workdayends',16);
				$GLOBALS['phpgw']->preferences->save_repository();
				$this->bo->prefs['calendar']['workdaystarts'] = 8;
				$this->bo->prefs['calendar']['workdayends'] = 16;
			}

			if(!isset($this->bo->prefs['calendar']['interval']))
			{
				$GLOBALS['phpgw']->preferences->add('calendar','interval',60);
				$GLOBALS['phpgw']->preferences->save_repository();
				$this->bo->prefs['calendar']['interval'] = 60;
			}

			if($this->debug)
			{
				echo "Interval set to : ".intval($this->bo->prefs['calendar']['interval'])."<br>\n";
			}

			$GLOBALS['phpgw']->browser->browser();
			if($GLOBALS['phpgw']->browser->get_agent() == 'MOZILLA')
			{
				$time_width = (intval($this->bo->prefs['common']['time_format']) == 12?12:8);
			}
			else
			{
			   $time_width = (intval($this->bo->prefs['common']['time_format']) == 12?10:7);
			}
			$var = Array(
				'time_width'		=> $time_width,
				'time_bgcolor'		=> $this->theme['navbar_bg'],
				'font_color'		=> $this->theme['bg_text'],
				'time_border_color'	=> $this->theme['navbar_text'],
				'font'			=> $this->theme['font']
			);

			$p->set_var($var);

			for ($i=0;$i<24;$i++)
			{
				for($j=0;$j<(60 / intval($this->bo->prefs['calendar']['interval']));$j++)
				{
					$rowspan_arr[$i][$j] = 0;
     				$time[$ind][$j] = '';
				}
			}

			$date_to_eval = sprintf("%04d%02d%02d",$params['year'],$params['month'],$params['day']);
	
			$time = Array();

			$daily = $this->set_week_array($this->bo->datetime->get_weekday_start($params['year'],$params['month'],$params['day']),$this->theme['row_on'],True);
			if($this->debug)
			{
				echo "Date to Eval : ".$date_to_eval."<br>\n";
			}
			if($daily[$date_to_eval]['appts'])
      	{
				$events = $this->bo->cached_events[$date_to_eval];
				$c_events = count($events);
				if($this->debug)
				{
					echo "Date : ".$date_to_eval." Count : ".$c_events."<br>\n";
				}
				for($i=0;$i<$c_events;$i++)
				{
//					$event = $events[$i];
					if($events[$i]['recur_type'] == MCAL_RECUR_NONE)
					{
						if($events[$i]['start']['mday'] < $params['day'])
						{
							if($events[$i]['end']['mday'] > $params['day'])
							{
								$ind = 99;
								$interval_start = 0;
							}
							elseif($events[$i]['end']['mday'] == $params['day'])
							{
								$ind = 0;
								$interval_start = 0;
							}
						}
						elseif($events[$i]['start']['mday'] == $params['day'])
						{
							$ind = intval($events[$i]['start']['hour']);
							$interval_start = intval($events[$i]['start']['min'] / intval($this->bo->prefs['calendar']['interval']));
							if($this->debug)
							{
								echo 'Start Time Minutes : '.$events[$i]['start']['min']."<br>\n";
								echo 'Interval : '.$interval_start."<br>\n";
							}
						}
					}
					else
					{
						$ind = intval($events[$i]['start']['hour']);
						$interval_start = intval($events[$i]['start']['min'] / intval($this->bo->prefs['calendar']['interval']));
					}

				      	if($ind < (int)$this->bo->prefs['calendar']['workdaystarts'] || $ind > (int)$this->bo->prefs['calendar']['workdayends'])
		      			{
				      		$ind = 99;
						$interval_start = 0;
		      			}

					$time[$ind][$interval_start] .= $this->link_to_entry($events[$i],$params['month'],$params['day'],$params['year']);

					$starttime = $this->bo->maketime($events[$i]['start']);
					$endtime = $this->bo->maketime($events[$i]['end']);

					if ($starttime <> $endtime)
					{
						$rowspan = intval(($endtime - $starttime) / (60 * intval($this->bo->prefs['calendar']['interval'])));
						$mins = (int)((($endtime - $starttime) / 60) % 60);
			
						if(($mins <> 0 && $mins <= intval(60 / intval($this->bo->prefs['calendar']['interval']))) || ($mins == 0 && date('i',$endtime) > intval($this->bo->prefs['calendar']['interval'])))
						{
							$rowspan += 1;
						}
						if($this->debug)
						{
							echo "Rowspan being set to : ".$rowspan."<br>\n";
						}

						if ($rowspan > $rowspan_arr[$ind][$interval_start] && $rowspan > 1)
						{
							$rowspan_arr[$ind][$interval_start] = $rowspan;
						}
					}
					if($this->debug)
					{
						echo 'Time : '.$GLOBALS['phpgw']->common->show_date($this->bo->maketime($events[$i]['start']) - $this->tz_offset).' - '.$GLOBALS['phpgw']->common->show_date($this->bo->maketime($events[$i]['end']) - $this->tz_offset).' : Start : '.$ind.' : Interval # : '.$interval_start."<br>\n";
					}
				}
			}

			// squish events that use the same cell into the same cell.
			// For example, an event from 8:00-9:15 and another from 9:30-9:45 both
			// want to show up in the 8:00-9:59 cell.
//			$rowspan = 0;
//			$last_row = -1;
//			for ($i=0;$i<24;$i++)
//			{
//				for($j=0;$j<(60 / intval($this->bo->prefs['calendar']['interval']));$j++)
//				{
//					if ($rowspan > 1)
//					{
//						if (isset($time[$i][$j]) && strlen($time[$i]) > 0)
//						{
//							$rowspan_arr[$last_row] += $rowspan_arr[$i];
//							if ($rowspan_arr[$i] <> 0)
//							{
//								$rowspan_arr[$last_row] -= 1;
//							}
//							$time[$last_row] .= $time[$i];
//							$time[$i] = '';
//							$rowspan_arr[$i] = 0;
//						}
//						$rowspan--;
//					}
//					elseif ($rowspan_arr[$i] > 1)
//					{
//						$rowspan = $rowspan_arr[$i];
//						$last_row = $i;
//					}
//				}
//			}

			$holiday_names = $daily[$date_to_eval]['holidays'];
			if(!$holiday_names)
			{
				$bgcolor = $GLOBALS['phpgw']->nextmatchs->alternate_row_color();
			}
			else
			{
				$bgcolor = $this->theme['bg04'];
				while(list($index,$name) = each($holiday_names))
				{
					$time[99][0] = '<center>'.$name.'</center>'.$time[99][0];
				}
			}

//			if (isset($time[99]) && strlen($time[99]) > 0)
			if (isset($time[99][0]))
			{
				$var = Array(
					'event'		=> $time[99][0],
					'bgcolor'	=> $bgcolor
				);
				$this->output_template_array($p,'item','day_event',$var);

				$var = Array(
					'open_link'	=> '',
					'time'		=> '&nbsp;',
					'close_link'	=> ''
				);
				$this->output_template_array($p,'item','day_time',$var);
				$p->parse('row','day_row',True);
				$p->set_var('item','');
			}
			$rowspan = 0;
			for ($i=(int)$this->bo->prefs['calendar']['workdaystarts'];$i<=(int)$this->bo->prefs['calendar']['workdayends'];$i++)
			{
				for($j=0;$j<(60 / intval($this->bo->prefs['calendar']['interval']));$j++)
				{
					$dtime = $this->bo->build_time_for_display(($i * 10000) + (($j *intval($this->bo->prefs['calendar']['interval'])) * 100));
					$p->set_var('extras','');
					$p->set_var('event','&nbsp');
					if ($rowspan > 1)
					{
						// this might mean there's an overlap, or it could mean one event
						// ends at 11:15 and another starts at 11:30.
//						if (isset($time[$i]) && strlen($time[$i]))
						if (isset($time[$i][$j]))
						{
							$p->set_var('event',$time[$i][$j]);
							$p->set_var('bgcolor',$GLOBALS['phpgw']->nextmatchs->alternate_row_color());
							$p->parse('item','day_event',False);
						}
						$rowspan--;
					}
//					elseif (!isset($time[$i]) || !strlen($time[$i]))
					elseif (!isset($time[$i][$j]))
					{
						$p->set_var('event','&nbsp;');
						$p->set_var('bgcolor',$GLOBALS['phpgw']->nextmatchs->alternate_row_color());
						$p->parse('item','day_event',False);
					}
					else
					{
						$rowspan = intval($rowspan_arr[$i][$j]);
						if ($rowspan > 1)
						{
							$p->set_var('extras',' rowspan="'.$rowspan.'"');
						}
						$p->set_var('event',$time[$i][$j]);
						$p->set_var('bgcolor',$GLOBALS['phpgw']->nextmatchs->alternate_row_color());
						$p->parse('item','day_event',False);
					}
			
					$open_link = ' - ';
					$close_link = '';
			
					if(!$this->bo->printer_friendly && $this->bo->check_perms(PHPGW_ACL_ADD))
					{
						$new_hour = intval(substr($dtime,0,strpos($dtime,':')));
						if ($this->bo->prefs['common']['timeformat'] == '12' && $i > 12)
						{
							$new_hour += 12;
						}
				
						$open_link .= '<a href="'.$this->page('add','&date='.$date_to_eval.'&hour='.$new_hour.'&minute='.substr($dtime,strpos($dtime,':')+1,2)).'">';
								
						$close_link = '</a>';
					}

					$var = Array(
						'open_link'	=> $open_link,
						'time'		=> (intval(substr($dtime,0,strpos($dtime,':')))<10?'0'.$dtime:$dtime),
						'close_link'	=> $close_link
					);
	
					$this->output_template_array($p,'item','day_time',$var);
					$p->parse('row','day_row',True);
					$p->set_var('event','');
					$p->set_var('item','');
				}
			}	// end for
			return $p->fp('out','day');
		}	// end function

		function timematrix($param)
		{
			if(!is_array($param))
			{
				$this->index();
			}

			$date = $param['date'];
			$starttime = $param['starttime'];
			$endtime = $param['endtime'];
			$participants = $param['participants'];

			if(!isset($this->bo->prefs['calendar']['interval']))
			{
				$this->bo->prefs['calendar']['interval'] = 15;
				$GLOBALS['phpgw']->preferences->add('calendar','interval',15);
				$GLOBALS['phpgw']->preferences->save_repository();
			}
//			$increment = $this->bo->prefs['calendar']['interval'];
			$increment = 15;
			$interval = (int)(60 / $increment);

			$pix = $GLOBALS['phpgw']->common->image('calendar','pix.gif');

			$str = '<center>'.lang($GLOBALS['phpgw']->common->show_date($date['raw'],'l'))
				. ', '.lang($GLOBALS['phpgw']->common->show_date($date['raw'],'F'))
				. ' '.$GLOBALS['phpgw']->common->show_date($date['raw'],'d, Y').'<br>'
				. '<table width="85%" border="0" cellspacing="0" cellpadding="0" cols="'.((24 * $interval) + 1).'">'
				. '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$pix.'"></td></tr>'
				. '<tr><td width="15%"><font color="'.$this->theme['bg_text'].'" face="'.$this->theme['font'].'" size="-2">'.lang('Participant').'</font></td>';
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
							$str .= '<td align="left" bgcolor="'.$this->theme['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$this->theme['font'].'" size="-2">'
								. '<a href="'.$this->page('add','&date='.$date['full'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.(($increment * $j)<=9?'0':'').($increment * $j)."'; return true;\">"
								. $k.'</a></font></td>';
							break;
						default:
							$str .= '<td align="left" bgcolor="'.$this->theme['bg_color'].'"><font color="'.$phpgw_info['theme']['bg_text'].'" face="'.$this->theme['font'].'" size="-2">'
								. '<a href="'.$this->page('add','&date='.$date['full'].'&hour='.$i.'&minute='.(interval * $j))."\" onMouseOver=\"window.status='".$i.':'.($increment * $j)."'; return true;\">"
								. '&nbsp</a></font></td>';
							break;
					}
				}
			}
			$str .= '</tr>'
				. '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="black"><img src="'.$pix.'"></td></tr>';
			if(!$endtime)
			{
				$endtime = $starttime;
			}
			$owner = $this->bo->owner;
			while(list($part,$status) = each($participants))
			{
				$str .= '<tr>'
					. '<td width="15%"><font color="'.$this->theme['bg_text'].'" face="'.$this->theme['font'].'" size="-2">'.$this->bo->get_fullname($part).'</font></td>';

				$this->bo->cached_events = Array();
				$this->bo->owner = $part;
				$this->so->owner = $part;
				$this->bo->store_to_cache(
					Array(
						'syear'	=> $date['year'],
						'smonth'=> $date['month'],
						'sday'	=> $date['day'],
						'eyear'	=> 0,
						'emonth'=> 0,
						'eday'	=> $date['day'] + 1
					)
				);

				if(!$this->bo->cached_events[$date['full']])
				{
					for($j=0;$j<24;$j++)
					{
						for($k=0;$k<$interval;$k++)
						{
							$str .= '<td height="1" align="left" bgcolor="'.$this->theme['bg_color'].'" color="#999999">&nbsp;</td>';
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
									$time_slice[$index]['color'] = $this->theme['bg_color'];
									break;
								case '-':
									$time_slice[$index]['color'] = $this->theme['bg01'];
									break;
							}
							$str .= '<td height="1" align="left" bgcolor="'.$time_slice[$index]['color']."\" color=\"#999999\"  onMouseOver=\"window.status='".$time_slice[$index]['description']."'; return true;\">".'<font color="'.$this->theme['bg_text'].'" face="'.$this->theme['font'].'" size="-2">'.$time_slice[$index]['marker'].'</font></td>';
						}
					}
				}
				$str .= '</tr>'
					. '<tr><td height="1" colspan="'.((24 * $interval) + 1).'" bgcolor="#999999"><img src="'.$pix.'"></td></tr>';
			}
			$this->bo->owner = $owner;
			$this->so->owner = $owner;
			return $str.'</table></center>'."\n";
		}      

		function get_response($cal_id)
		{
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
  					'form_button'	=> 'form_button_script.tpl'
				)
			);

			$ev = $this->bo->get_cached_event();
			$response_choices = Array(
				ACCEPTED	=> lang('Accept'),
				REJECTED	=> lang('Reject'),
				TENTATIVE	=> lang('Tentative'),
				NO_RESPONSE	=> lang('No Response')
			);
			$str = '';
			while(list($param,$text) = each($response_choices))
			{
				if(!($param == REJECTED && $ev['owner'] == $this->bo->owner))
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
			}
			return '<table width="100%" cols="4"><tr align="center">'."\n".$str.'</tr></table>'."\n";
		}

		function edit_form($param)
		{
			if(!is_array($param))
			{
				$this->index();
			}

			if(isset($param['event']))
			{
				$event = $param['event'];
			}

			$hourformat = substr($this->bo->users_timeformat,0,1);
			
			$sb = CreateObject('phpgwapi.sbox');

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'edit'		=> 'edit.tpl',
					'form_button'	=> 'form_button_script.tpl'
				)
			);
			$p->set_block('edit','edit_entry','edit_entry');
			$p->set_block('edit','list','list');
			$p->set_block('edit','hr','hr');

			$vars = Array(
				'font'			=> $this->theme['font'],
				'bg_color'		=> $this->theme['bg_text'],
				'calendar_action'	=> ($event['id']?lang('Calendar - Edit'):lang('Calendar - Add')),
				'action_url'		=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.bocalendar.update')),
				'common_hidden'		=> '<input type="hidden" name="cal[id]" value="'.$event['id'].'">'."\n"
         							. '<input type="hidden" name="cal[owner]" value="'.$this->bo->owner.'">'."\n"
         							. '<input type="hidden" name="cal[uid]" value="'.$event['uid'].'">'."\n"
         							. ($GLOBALS['HTTP_GET_VARS']['cal_id'] && $event['id'] == 0?'<input type="hidden" name="cal[reference]" value="'.$GLOBALS['HTTP_GET_VARS']['cal_id'].'">'."\n":
         							  (@isset($event['reference'])?'<input type="hidden" name="cal[reference]" value="'.$event['reference'].'">'."\n":'')),
				'errormsg'		=> ($params['cd']?$GLOBALS['phpgw']->common->check_code($params['cd']):'')
			);
			$p->set_var($vars);

// Brief Description
			$var[] = Array(
				'field'	=> lang('Title'),
				'data'	=> '<input name="cal[title]" size="25" maxlength="80" value="'.$event['title'].'">'
			);

// Full Description
			$var[] = Array(
				'field'	=> lang('Full Description'),
				'data'	=> '<textarea name="cal[description]" rows="5" cols="40" wrap="virtual" maxlength="2048">'.$event['description'].'</textarea>'
			);

// Display Categories
			if(strpos($event['category'],','))
			{
				$temp_cats = explode(',',$event['category']);
				@reset($temp_cats);
				while(list($key,$value) = each($temp_cats))
				{
					$check_cats[] = intval($value);
				}
			}
			elseif($event['category'])
			{
				$check_cats[] = intval($event['category']);
			}
			else
			{
				$check_cats[] = 0;
			}
			$var[] = Array(
				'field'	=> lang('Category'),
				'data'	=> '<select name="categories[]" multiple size="5"><option value="0">'.lang('Choose the category').'</option>'.$this->cat->formated_list('select','all',$check_cats,True).'</select>'
			);

// Location
			$var[] = Array(
				'field'	=> lang('Location'),
				'data'	=> '<input name="cal[location]" size="25" maxlength="80" value="'.$event['location'].'">'
			);

// Date
			$start = $this->bo->maketime($event['start']) - $this->tz_offset;
			$var[] = Array(
				'field'	=> lang('Start Date'),
				'data'	=> $GLOBALS['phpgw']->common->dateformatorder(
				   $sb->getYears('start[year]',intval($GLOBALS['phpgw']->common->show_date($start,'Y'))),
				   $sb->getMonthText('start[month]',intval($GLOBALS['phpgw']->common->show_date($start,'n'))),
				   $sb->getDays('start[mday]',intval($GLOBALS['phpgw']->common->show_date($start,'d')))
			   )
			);

// Time
			if ($this->bo->prefs['common']['timeformat'] == '12')
			{
				$str .= '<input type="radio" name="start[ampm]" value="am"'.($event['start']['hour'] >= 12?'':' checked').'>am'."\n"
					. '<input type="radio" name="start[ampm]" value="pm"'.($event['start']['hour'] >= 12?' checked':'').'>pm'."\n";
			}
			$var[] = Array(
				'field'	=> lang('Start Time'),
				'data'	=> '<input name="start[hour]" size="2" VALUE="'.$GLOBALS['phpgw']->common->show_date($start,$hourformat).'" maxlength="2">:<input name="start[min]" size="2" value="'.$GLOBALS['phpgw']->common->show_date($start,'i').'" maxlength="2">'."\n".$str
			);

// End Date
			$end = $this->bo->maketime($event['end']) - $this->tz_offset;
			$var[] = Array(
				'field'	=> lang('End Date'),
				'data'	=> $GLOBALS['phpgw']->common->dateformatorder(
				   $sb->getYears('end[year]',intval($GLOBALS['phpgw']->common->show_date($end,'Y'))),
				   $sb->getMonthText('end[month]',intval($GLOBALS['phpgw']->common->show_date($end,'n'))),
				   $sb->getDays('end[mday]',intval($GLOBALS['phpgw']->common->show_date($end,'d')))
				)
			);

// End Time
			if ($this->bo->prefs['common']['timeformat'] == '12')
			{
				$str = '<input type="radio" name="end[ampm]" value="am"'.($event['end']['hour'] >= 12?'':' checked').'>am'."\n"
					. '<input type="radio" name="end[ampm]" value="pm"'.($event['end']['hour'] >= 12?' checked':'').'>pm'."\n";
			}
			$var[] = Array(
				'field'	=> lang('End Time'),
				'data'	=> '<input name="end[hour]" size="2" VALUE="'.$GLOBALS['phpgw']->common->show_date($end,$hourformat).'" maxlength="2">:<input name="end[min]" size="2" value="'.$GLOBALS['phpgw']->common->show_date($end,'i').'" maxlength="2">'."\n".$str
			);

// Priority
			$var[] = Array(
				'field'	=> lang('Priority'),
				'data'	=> $sb->getPriority('cal[priority]',$event['priority'])
			);

// Access
			$var[] = Array(
				'field'	=> lang('Private'),
				'data'	=> '<input type="checkbox" name="cal[private]" value="private"'.(!$event['public']?' checked':'').'>'
			);

// Participants
			$accounts = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,'calendar');
			$users = Array();
			$this->build_part_list($users,$accounts,$this->bo->owner);
    
			$str = '';
			@asort($users);
			@reset($users);
			while (list($id,$user_array) = each($users))
			{
				if($id != intval($this->bo->owner))
				{
					$str .= '    <option value="' . $id . '"'.($event['participants'][$id]?' selected':'').'>('.$user_array['type'].') '.$user_array['name'].'</option>'."\n";
				}
			}
			$var[] = Array(
				'field'	=> lang('Participants'),
				'data'	=> "\n".'   <select name="participants[]" multiple size="5">'."\n".$str.'   </select>'
			);

// I Participate
			if((($event['id'] > 0) && isset($event['participants'][$this->bo->owner])) || !$event['id'])
			{
				$checked = ' checked';
			}
			else
			{
				$checked = '';
			}
			$var[] = Array(
				'field'	=> $GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner).' '.lang('Participates'),
				'data'	=> '<input type="checkbox" name="participants[]" value="'.$this->bo->owner.'"'.$checked.'>'
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
				MCAL_RECUR_NONE		=> 'None',
				MCAL_RECUR_DAILY	=> 'Daily',
				MCAL_RECUR_WEEKLY	=> 'Weekly',
				MCAL_RECUR_MONTHLY_WDAY	=> 'Monthly (by day)',
				MCAL_RECUR_MONTHLY_MDAY	=> 'Monthly (by date)',
				MCAL_RECUR_YEARLY	=> 'Yearly'
			);
			$str = '';
			for($l=0;$l<count($rpt_type);$l++)
			{
				$str .= '<option value="'.$rpt_type[$l].'"'.($event['recur_type']==$rpt_type[$l]?' selected':'').'>'.lang($rpt_type_out[$rpt_type[$l]]).'</option>';
			}
			$var[] = Array(
				'field'	=> lang('Repeat Type'),
				'data'	=> '<select name="cal[recur_type]">'."\n".$str.'</select>'."\n"
			);

			if($event['recur_enddate']['year'] != 0 && $event['recur_enddate']['month'] != 0 && $event['recur_enddate']['mday'] != 0)
			{
				$checked = ' checked';
				$recur_end = $this->bo->maketime($event['recur_enddate']) - $this->tz_offset;
			}
			else
			{
				$checked = '';
				$recur_end = $this->bo->maketime($event['start']) + 86400 - $this->tz_offset;
			}
	
			$var[] = Array(
				'field'	=> lang('Repeat End Date'),
				'data'	=> '<input type="checkbox" name="cal[rpt_use_end]" value="y"'.$checked.'>'.lang('Use End Date').'  '
				   .$GLOBALS['phpgw']->common->dateformatorder(
			         $sb->getYears('recur_enddate[year]',intval($GLOBALS['phpgw']->common->show_date($recur_end,'Y'))),
         		   $sb->getMonthText('recur_enddate[month]',intval($GLOBALS['phpgw']->common->show_date($recur_end,'n'))),
                  $sb->getDays('recur_enddate[mday]',intval($GLOBALS['phpgw']->common->show_date($recur_end,'d')))
               )
			);

			$var[] = Array(
				'field'	=> lang('Repeat Day').'<br>'.lang('(for weekly)'),
				'data'	=> '<input type="checkbox" name="cal[rpt_sun]" value="'.MCAL_M_SUNDAY.'"'.(($event['recur_data'] & MCAL_M_SUNDAY) ?' checked':'').'> '.lang('Sunday').' '
			            . '<input type="checkbox" name="cal[rpt_mon]" value="'.MCAL_M_MONDAY.'"'.(($event['recur_data'] & MCAL_M_MONDAY) ?' checked':'').'> '.lang('Monday').' '
                     . '<input type="checkbox" name="cal[rpt_tue]" value="'.MCAL_M_TUESDAY.'"'.(($event['recur_data'] & MCAL_M_TUESDAY) ?' checked':'').'> '.lang('Tuesday').' '
                     . '<input type="checkbox" name="cal[rpt_wed]" value="'.MCAL_M_WEDNESDAY.'"'.(($event['recur_data'] & MCAL_M_WEDNESDAY) ?' checked':'').'> '.lang('Wednesday').' <br>'
                     . '<input type="checkbox" name="cal[rpt_thu]" value="'.MCAL_M_THURSDAY.'"'.(($event['recur_data'] & MCAL_M_THURSDAY) ?' checked':'').'> '.lang('Thursday').' '
                     . '<input type="checkbox" name="cal[rpt_fri]" value="'.MCAL_M_FRIDAY.'"'.(($event['recur_data'] & MCAL_M_FRIDAY) ?' checked':'').'> '.lang('Friday').' '
                     . '<input type="checkbox" name="cal[rpt_sat]" value="'.MCAL_M_SATURDAY.'"'.(($event['recur_data'] & MCAL_M_SATURDAY) ?' checked':'').'> '.lang('Saturday').' '
			);

			$var[] = Array(
				'field'	=> lang('Interval'),
				'data'	=> '<input name="cal[recur_interval]" size="4" maxlength="4" value="'.$event['recur_interval'].'">'
			);

			for($i=0;$i<count($var);$i++)
			{
				$this->output_template_array($p,'row','list',$var[$i]);
			}
			
			$p->set_var('submit_button',lang('Submit'));

			if ($event['id'] > 0)
			{
				$var = Array(
					'action_url_button'	=> $this->page('delete','&cal_id='.$event['id']),
					'action_text_button'	=> lang('Delete'),
					'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to \\ndelete this entry?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
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

		function build_part_list(&$users,$accounts,$owner)
		{
			if($accounts == False)
			{
				return;
			}
			@reset($accounts);
			while(list($index,$id) = each($accounts))
			{
				$i_id = intval($id);
				if($i_id == $owner)
				{
					continue;
				}
				elseif(!isset($users[$i_id]))
				{
					if($GLOBALS['phpgw']->accounts->exists($i_id) == True)
					{
						$users[$i_id] = Array(
							'name'	=> $GLOBALS['phpgw']->common->grab_owner_name($i_id),
							'type'	=> $GLOBALS['phpgw']->accounts->get_type($i_id)
						);
					}
					if($GLOBALS['phpgw']->accounts->get_type($i_id) == 'g')
					{
						$this->build_part_list($users,$GLOBALS['phpgw']->acl->get_ids_for_location($i_id,1,'phpgw_group'),$owner);
					}
				}
			}
		}

		function set_week_array($startdate,$cellcolor,$weekly)
		{
			for ($j=0,$datetime=$startdate - $this->tz_offset;$j<7;$j++,$datetime += 86400)
			{
				$date = date('Ymd',$datetime);

				if($this->debug)
				{
					echo "set_week_array : Date : ".$date."<br>\n";
				}

				if($this->bo->cached_events[$date])
				{
					if($this->debug)
					{
						echo "Date : ".$date." Appointments found : ".count($this->bo->cached_events[$date])."<br>\n";
					}
					$appts = True;
				}
				else
				{
					$appts = False;
				}

				$holidays = $this->bo->cached_holidays[$date];
				if($weekly)
				{
					$cellcolor = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($cellcolor);
				}
				
				$day_image = '';
				if($holidays)
				{
					$extra = ' bgcolor="'.$this->bo->holiday_color.'"';
					$class = ($appts?'b':'').'minicalhol';
					if ($date == $this->bo->today)
					{
						$day_image = ' background="'.$GLOBALS['phpgw']->common->image('calendar','mini_day_block.gif').'"';
					}
				}
				elseif ($date != $this->bo->today)
				{
					$extra = ' bgcolor="'.$cellcolor.'"';
					$class = ($appts?'b':'').'minicalendar';
				}
				else
				{
					$extra = ' bgcolor="'.$GLOBALS['phpgw_info']['theme']['cal_today'].'"';
					$class = ($appts?'b':'').'minicalendar';
					$day_image = ' background="'.$GLOBALS['phpgw']->common->image('calendar','mini_day_block.gif').'"';
				}

				if($this->bo->printer_friendly && @$this->bo->prefs['calendar']['print_black_white'])
				{
					$extra = '';
				}

				if(!$this->bo->printer_friendly && $this->bo->check_perms(PHPGW_ACL_ADD))
				{
					$new_event = True;
				}
				else
				{
					$new_event = False;
				}
				$holiday_name = Array();
				if($holidays)
				{
					for($k=0;$k<count($holidays);$k++)
					{
						$holiday_name[] = $holidays[$k]['name'];
					}
				}
				$week = '';
				if (!$j || ($j && substr($date,6,2) == '01'))
				{
					$week = 'week ' .(int)((date('z',($startdate+(24*3600*4)))+7)/7);
				}
				$daily[$date] = Array(
					'extra'		=> $extra,
					'new_event'	=> $new_event,
					'holidays'	=> $holiday_name,
					'appts'		=> $appts,
					'week'		=> $week,
					'day_image'	=> $day_image,
					'class'		=> $class
				);
			}

			if($this->debug)
			{
				$this->_debug_array($daily);
			}
			
			return $daily;
		}
	}
?>
