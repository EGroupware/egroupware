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
		var $theme;
		var $link_tpl;

		// planner related variables
		var $planner_html;

		var $planner_header;
		var $planner_rows;

		var $planner_group_members;

		var $planner_firstday;
		var $planner_lastday;
		var $planner_days;

		var $planner_end_month;
		var $planner_end_year;
		var $planner_days_in_end_month; 

		var $planner_intervals = array(	// conversation hour and interval depending on intervals_per_day
					//                                  1 1 1 1 1 1 1 1 1 1 2 2 2 2
					//              0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3
						'1' => array(0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0), // 0=0-23h
						'2' => array(0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,1,1,1,0,0,0,0,0), // 0=0-12h, 1=12-23h
						'3' => array(0,0,0,0,0,0,0,0,0,0,0,0,1,1,1,1,1,1,2,2,2,2,2,2), // 0=0-12h, 2=12-18h, 3=18-23h
						'4' => array(0,0,0,0,0,0,0,1,1,1,1,1,2,2,2,2,2,2,3,3,3,3,3,3)  // 0=0-7, 7-12h, 3=12-18h, 4=18-23h
					);

		var $public_functions = array(
			'mini_calendar' => True,
			'index' => True,
			'month' => True,
			'get_month' => True,
			'week'  => True,
			'get_week' => True,
			'year' => True,
			'view' => True,
			'edit' => True,
			'export'	=> True,
			'reinstate_list'	=> True,
			'reinstate'	=> True,
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

			print_debug('BO Owner',$this->bo->owner);

			$this->template = $GLOBALS['phpgw']->template;
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir('calendar');
			$this->cat      = CreateObject('phpgwapi.categories');

			$this->holiday_color = (substr($this->theme['bg06'],0,1)=='#'?'':'#').$this->theme['bg06'];
			
			$this->cat_id   = $this->bo->cat_id;

			$this->link_tpl = CreateObject('phpgwapi.Template',$this->template_dir);
			$this->link_tpl->set_unknowns('remove');
			$this->link_tpl->set_file(
			   Array(
				   'link_picture'	=> 'link_pict.tpl'
			   )
			);
			$this->link_tpl->set_block('link_picture','link_pict','link_pict');
			$this->link_tpl->set_block('link_picture','pict','pict');
			$this->link_tpl->set_block('link_picture','link_open','link_open');
			$this->link_tpl->set_block('link_picture','link_close','link_close');
			$this->link_tpl->set_block('link_picture','link_text','link_text');

			if($this->bo->use_session)
			{
				// save return-fkt for add, view, ...
				list(,,$fkt) = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
				if ($fkt == 'day' || $fkt == 'week' || $fkt == 'month' || $fkt == 'year' || $fkt == 'planner')
				{
					$this->bo->return_to = $GLOBALS['HTTP_GET_VARS']['menuaction'].
						sprintf('&date=%04d%02d%02d',$this->bo->year,$this->bo->month,$this->bo->day);
				}
				$this->bo->save_sessiondata();
			}
			print_debug('UI',$this->_debug_sqsof());
		}

		/* Public functions */

		function mini_calendar($params)
		{
			static $mini_cal_tpl;
			if(!is_array($params))
			{
				return;
			}

			if($params['month'] == 0)
			{
				$params['month'] = 12;
				$params['year'] = $params['year'] - 1;
			}
			elseif($params['month'] == 13)
			{
				$params['month'] = 1;
				$params['year'] = $params['year'] + 1;
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

			$this->bo->read_holidays($params['year']);

			$date = $GLOBALS['phpgw']->datetime->makegmttime(0,0,0,$params['month'],$params['day'],$params['year']);
			$month_ago = intval(date('Ymd',mktime(0,0,0,$params['month'] - 1,$params['day'],$params['year'])));
			$month_ahead = intval(date('Ymd',mktime(0,0,0,$params['month'] + 1,$params['day'],$params['year'])));
			$monthstart = intval(date('Ymd',mktime(0,0,0,$params['month'],1,$params['year'])));
			$monthend = intval(date('Ymd',mktime(0,0,0,$params['month'] + 1,0,$params['year'])));

			$weekstarttime = $GLOBALS['phpgw']->datetime->get_weekday_start($params['year'],$params['month'],1);

			print_debug('mini_calendar:monthstart',$monthstart);
			print_debug('mini_calendar:weekstarttime',date('Ymd H:i:s',$weekstarttime));

			if(!is_object($mini_cal_tpl))
			{
				$mini_cal_tpl = CreateObject('phpgwapi.Template',$this->template_dir);
				$mini_cal_tpl->set_unknowns('remove');

				$mini_cal_tpl->set_file(
					Array(
						'mini_calendar'	=> 'mini_cal.tpl'
					)
				);
				$mini_cal_tpl->set_block('mini_calendar','mini_cal','mini_cal');
				$mini_cal_tpl->set_block('mini_calendar','mini_week','mini_week');
				$mini_cal_tpl->set_block('mini_calendar','mini_day','mini_day');
			}

			if($this->bo->printer_friendly == False)
			{
				$month = '<a href="' . $this->page('month','&month='.$GLOBALS['phpgw']->common->show_date($date['raw'],'m').'&year='.$GLOBALS['phpgw']->common->show_date($date['raw'],'Y')). '" class="minicalendar">' . lang($GLOBALS['phpgw']->common->show_date($date['raw'],'F')).' '.$GLOBALS['phpgw']->common->show_date($date['raw'],'Y').'</a>';
			}
			else
			{
				$month = lang($GLOBALS['phpgw']->common->show_date($date['raw'],'F')).' '.$GLOBALS['phpgw']->common->show_date($date['raw'],'Y');
			}

			$var = Array(
				'cal_img_root'		=>	$GLOBALS['phpgw']->common->image('calendar','mini-calendar-bar'),
				'bgcolor'			=>	$this->theme['bg_color'],
				'bgcolor1'			=>	$this->theme['bg_color'],
				'month'				=>	$month,
				'bgcolor2'			=>	$this->theme['cal_dayview'],
				'holiday_color'	=> $this->holiday_color
			);

			$mini_cal_tpl->set_var($var);

			switch(strtolower($params['buttons']))
			{
				case 'right':
					$var = Array(
						'nextmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ahead).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','right').'" border="0"></a>'
					);
					break;
				case 'left':
					$var = Array(
						'prevmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ago).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','left').'" border="0"></a>'
					);					
					break;
				case 'both':
					$var = Array(
						'prevmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ago).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','left').'" border="0"></a>',
						'nextmonth'			=>	'<a href="'.$this->page('month','&date='.$month_ahead).'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi','right').'" border="0"></a>'
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
			$mini_cal_tpl->set_var($var);

			if(!$mini_cal_tpl->get_var('daynames'))
			{
				for($i=0;$i<7;$i++)
				{
					$var = Array(
						'dayname'	=> '<b>' . substr(lang($GLOBALS['phpgw']->datetime->days[$i]['name']),0,2) . '</b>',
						'day_image'	=> ''
					);
					$this->output_template_array($mini_cal_tpl,'daynames','mini_day',$var);
				}
			}
			$today = date('Ymd',$GLOBALS['phpgw']->datetime->users_localtime);
			unset($date);
			for($i=$weekstarttime + $GLOBALS['phpgw']->datetime->tz_offset;date('Ymd',$i)<=$monthend;$i += (24 * 3600 * 7))
			{
				unset($var);
				$daily = $this->set_week_array($i - $GLOBALS['phpgw']->datetime->tz_offset,$cellcolor,$weekly);
				@reset($daily);
				while(list($date,$day_params) = each($daily))
				{
					print_debug('Mini-Cal Date',$date);
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
					else
					{
						$day_params['day_image'] = '';
					}
					$var[] = Array(
						'day_image'	=> $day_params['day_image'],
						'dayname'	=> $str
					);
				}
				for($l=0;$l<count($var);$l++)
				{
					$this->output_template_array($mini_cal_tpl,'monthweek_day','mini_day',$var[$l]);
				}
				$mini_cal_tpl->parse('display_monthweek','mini_week',True);
				$mini_cal_tpl->set_var('dayname','');
				$mini_cal_tpl->set_var('monthweek_day','');
			}
		
			$return_value = $mini_cal_tpl->fp('out','mini_cal');
			$mini_cal_tpl->set_var('display_monthweek','');
			return $return_value;
		}

		function index($params='')
		{
			Header('Location: '. $this->page('',$params));
			$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
			exit;
		}

		/*!
		@function cal_header
		@abstract call common::phpgw_header and shows the application-header
		*/
		function cal_header()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			unset($GLOBALS['phpgw_info']['flags']['noappheader']);
			unset($GLOBALS['phpgw_info']['flags']['noappfooter']);
			$GLOBALS['phpgw']->common->phpgw_header();

			function add_col(&$tpl,$str)
			{
				$tpl->set_var('str',$str);
				$tpl->parse('header_column','head_col',True);
			}

			function add_image_ahref($link,$image,$alt)
			{
				return '<a href="'.$link.'"><img src="'.$GLOBALS['phpgw']->common->image('calendar',$image).'" alt="'.$alt.'" title="'.$alt.'" border="0"></a>';
			}

			list(,,$referrer) = explode('.',MENUACTION);

			$templates = Array(
				'head_tpl'	=> 'head.tpl',
				'form_button_dropdown'	=> 'form_button_dropdown.tpl',
				'form_button_script'	=> 'form_button_script.tpl'
			);
			$tpl = &$GLOBALS['phpgw']->template;
			$tpl->set_file($templates);
			$tpl->set_block('head_tpl','head','head');
			$tpl->set_block('head_tpl','head_table','head_table');
			$tpl->set_block('head_tpl','head_col','head_col');
			$tpl->set_block('form_button_script','form_button');

			if(floor(phpversion()) >= 4)
			{
				$tpl->set_var('cols',8);
			}
			else
			{
				$tpl->set_var('cols',7);
			}

			$today = date('Ymd',$GLOBALS['phpgw']->datetime->users_localtime);

			$col_width = 12;

			add_col($tpl,'  <td width="2%">&nbsp;</td>');

			add_col($tpl,'  <td width="2%">'.add_image_ahref($this->page('day','&date='.$today),'today',lang('Today')).'</td>');

			add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('week','&date='.$today),'week',lang('This week')).'</td>');

			add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('month','&date='.$today),'month',lang('This month')).'</td>');

			add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('year','&date='.$today),'year',lang('This Year')).'</td>');

			if(floor(phpversion()) >= 4)
			{
				add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('planner','&date='.$today),'planner',lang('Planner')).'</td>');
				$col_width += 2;
			}

			add_col($tpl,'  <td width="2%" align="left">'.add_image_ahref($this->page('matrixselect'),'view',lang('Daily Matrix View')).'</td>');

			add_col($tpl,'  <td width="'.(100 - $col_width).'%" align="left"'.(floor(phpversion()) < 4?' colspan="2"':'').'>&nbsp;</td>');

			$tpl->parse('phpgw_body','head_table',True);

			$tpl->set_var('header_column','');
			$tpl->set_var('cols',$cols);

			if($referrer!='view')
			{
				$remainder = 72;
				$cal_id       = get_var('cal_id',Array('GET','DEFAULT'),0);
				$keywords     = get_var('keywords',Array('POST','DEFAULT'),'');
				$matrixtype   = get_var('matrixtype',Array('POST','DEFAULT'),'');
				$participants = get_var('participants',Array('POST'));
				$date         = get_var('date',Array('GET','POST'));
				$year         = $this->bo->year;
				$month        = $this->bo->month;
				$day          = $this->bo->day;
				$var_list = Array(
					'cal_id',
					'keywords',
					'matrixtype',
					'date',
					'year',
					'month',
					'day'
				);

				$base_hidden_vars = '<input type="hidden" name="from" value="'.MENUACTION.'">'."\n";
				for($i=0;$i<count($var_list);$i++)
				{
					if($$var_list[$i])
					{
						$base_hidden_vars .= '    <input type="hidden" name="'.$var_list[$i].'" value="'.$$var_list[$i].'">'."\n";
					}
				}
				$hidden_vars = '';
				if($participants)
				{
					for ($i=0;$i<count($participants);$i++)
					{
						$hidden_vars .= '    <input type="hidden" name="participants[]" value="'.$participants[$i].'">'."\n";
					}
				}

				$var = Array(
					'form_width' => '28',
					'form_link'	=> $this->page($referrer),
					'form_name'	=> 'cat_id',
					'title'	=> lang('Category'),
					'hidden_vars'	=> $base_hidden_vars.$hidden_vars,
					'form_options'	=> '<option value="0">All</option>'.$this->cat->formatted_list('select','all',$this->bo->cat_id,'True'),
					'button_value'	=> lang('Go!')
				);
				$tpl->set_var($var);
				$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
				$tpl->parse('header_column','head_col',True);

				if(MENUACTION == 'calendar.uicalendar.planner')
				{
					$remainder -= 28;
					print_debug('Sort By',$this->bo->sortby);

					$form_options = '<option value="user"'.($this->bo->sortby=='user'?' selected':'').'>'.lang('User').'</option>'."\n";
					$form_options .= '     <option value="category"'.((!isset($this->bo->sortby) || !$this->bo->sortby) || $this->bo->sortby=='category'?' selected':'').'>'.lang('Category').'</option>'."\n";

					$var = Array(
						'form_width' => '28',
						'form_link'	=> $this->page($referrer),
						'form_name'	=> 'sortby',
						'title'	=> lang('Sort By'),
						'hidden_vars'	=> $base_hidden_vars,
						'form_options'	=> $form_options,
						'button_value'	=> lang('Go!')
					);
					$tpl->set_var($var);
					$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
					$tpl->parse('header_column','head_col',True);
				}

				if($this->bo->check_perms(PHPGW_ACL_PRIVATE))
				{
					$remainder -= 28;
					$hidden_vars = '';
					if($participants)
					{
						for ($i=0;$i<count($participants);$i++)
						{
							$hidden_vars .= '    <input type="hidden" name="participants[]" value="'.$participants[$i].'">'."\n";
						}
					}
					$form_options = '<option value=" all "'.($this->bo->filter==' all '?' selected':'').'>'.lang('All').'</option>'."\n";
					$form_options .= '     <option value=" private "'.((!isset($this->bo->filter) || !$this->bo->filter) || $this->bo->filter==' private '?' selected':'').'>'.lang('Private Only').'</option>'."\n";

					$var = Array(
						'form_width' => '28',
						'form_link'	=> $this->page($referrer),
						'form_name'	=> 'filter',
						'title'	=> lang('Filter'),
						'hidden_vars'	=> $base_hidden_vars.$hidden_vars,
						'form_options'	=> $form_options,
						'button_value'	=> lang('Go!')
					);
					$tpl->set_var($var);
					$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
					$tpl->parse('header_column','head_col',True);
				}

				if((!isset($GLOBALS['phpgw_info']['server']['deny_user_grants_access']) || !$GLOBALS['phpgw_info']['server']['deny_user_grants_access']) && count($this->bo->grants) > 0)
				{
					$form_options = '';
					reset($this->bo->grants);
					while(list($grantor,$temp_rights) = each($this->bo->grants))
					{
						$GLOBALS['phpgw']->accounts->get_account_name($grantor,$lid,$fname,$lname);
						$drop_down[$lname.' '.$fname] = Array(
							'grantor'	=> $grantor,
							'value'		=> ($GLOBALS['phpgw']->accounts->get_type($grantor)=='g'?'g_':'').$grantor,
							'name'		=> $GLOBALS['phpgw']->common->display_fullname($lid,$fname,$lname)
						);
					}
					$memberships = $GLOBALS['phpgw']->accounts->membership($GLOBALS['phpgw_info']['user']['account_id']);
					while($memberships != False && list($key,$group_info) = each($memberships))
					{
						$GLOBALS['phpgw']->accounts->get_account_name($group_info['account_id'],$lid,$fname,$lname);
						$drop_down[$lname.' '.$fname] = Array(
							'grantor'	=> $group_info['account_id'],
							'value'		=> ($GLOBALS['phpgw']->accounts->get_type($group_info['account_id'])=='g'?'g_':'').$group_info['account_id'],
							'name'		=> $GLOBALS['phpgw']->common->display_fullname($lid,$fname,$lname)
						);

						$account_perms = $GLOBALS['phpgw']->acl->get_ids_for_location($group_info['account_id'],PHPGW_ACL_READ,'calendar');
						while($account_perms && list($key,$group_id) = each($account_perms))
						{
							$GLOBALS['phpgw']->accounts->get_account_name($group_id,$lid,$fname,$lname);
							$drop_down[$lname.' '.$fname] = Array(
								'grantor'	=> $group_id,
								'value'		=> ($GLOBALS['phpgw']->accounts->get_type($group_id)=='g'?'g_':'').$group_id,
								'name'		=> $GLOBALS['phpgw']->common->display_fullname($lid,$fname,$lname)
							);
						}
					}

					@reset($drop_down);
					@ksort($drop_down);
					while(list($key,$grant) = each($drop_down))
					{
						$form_options .= '    <option value="'.$grant['value'].'"'.($grant['grantor']==$this->bo->owner?' selected':'').'>'.$grant['name'].'</option>'."\n";
					}
					reset($this->bo->grants);

					$var = Array(
						'form_width' => $remainder,
						'form_link'	=> $this->page($referrer),
						'form_name'	=> 'owner',
						'title'	=> lang('User'),
						'hidden_vars'	=> $base_hidden_vars,
						'form_options'	=> $form_options,
						'button_value'	=> lang('Go!')
					);
					$tpl->set_var($var);
					$tpl->set_var('str',$tpl->fp('out','form_button_dropdown'));
					$tpl->parse('header_column','head_col',True);
				}
			}

			$hidden_vars = '    <input type="hidden" name="from" value="'.MENUACTION.'">'."\n";
			$date = get_var('date',Array('GET'));
			if($date)
			{
				$hidden_vars .= '    <input type="hidden" name="date" value="'.$date.'">'."\n";
			}
			$hidden_vars .= '    <input type="hidden" name="month" value="'.$this->bo->month.'">'."\n";
			$hidden_vars .= '    <input type="hidden" name="day" value="'.$this->bo->day.'">'."\n";
			$hidden_vars .= '    <input type="hidden" name="year" value="'.$this->bo->year.'">'."\n";
			if(isset($this->bo->filter) && $this->bo->filter)
			{
				$hidden_vars .= '    <input type="hidden" name="filter" value="'.$this->bo->filter.'">'."\n";
			}
			if(isset($this->bo->sortby) && $this->bo->sortby)
			{
				$hidden_vars .= '    <input type="hidden" name="sortby" value="'.$this->bo->sortby.'">'."\n";
			}
			if(isset($this->bo->num_months) && $this->bo->num_months)
			{
				$hidden_vars .= '    <input type="hidden" name="num_months" value="'.$this->bo->num_months.'">'."\n";
			}
			$hidden_vars .= '    <input name="keywords"'.($keywords?' value="'.$keywords.'"':'').'>';

			$var = Array(
				'action_url_button'	=> $this->page('search'),
				'action_text_button'	=> lang('Search'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> $hidden_vars
			);
			$tpl->set_var($var);
			$button = $tpl->fp('out','form_button');
			$tpl->set_var('str','<td align="right" valign="bottom">'.$button.'</td>');
			$tpl->parse('header_column','head_col',True);
			$tpl->parse('phpgw_body','head_table',True);
		}

		function printer_friendly($body)
		{
			if($this->bo->printer_friendly)
			{
				$new_body = '<html>'."\n"
					.'<head>'."\n"
					.'<STYLE type="text/css">'."\n"
					.'<!--'."\n"
					.'  body { margin-top: 0px; margin-right: 0px; margin-left: 0px; font-family: "'.$GLOBALS['phpgw_info']['theme']['font'].'" }'."\n"
					.'  .tablink { color: #000000; }'."\n"
					.' '.$this->css()."\n"
					.'-->'."\n"
					.'</STYLE>'."\n"
					.'</head>'."\n"
					.$this->bo->debug_string.$body
					.'</body>'."\n"
					.'</html>'."\n";
			}
			else
			{
				$this->cal_header();

				$new_body = $this->bo->debug_string.$body;
			}
			return $new_body;
		}

		function month()
		{
			echo $this->printer_friendly($this->get_month());
		}

		function get_month()
		{
			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			if (!$this->bo->printer_friendly || ($this->bo->printer_friendly && @$this->bo->prefs['calendar']['display_minicals']))
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

			$this->bo->read_holidays();

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

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');
			$p->set_file(
				Array(
					'index_t'	=>	'index.tpl'
				)
			);
			$p->set_var($var);
			return $p->fp('out','index_t');
		}

		function week()
		{
			echo $this->printer_friendly($this->get_week());
		}

		function get_week()
		{
			$this->bo->read_holidays();

			$next = $GLOBALS['phpgw']->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day + 7,$this->bo->year);
			$prev = $GLOBALS['phpgw']->datetime->makegmttime(0,0,0,$this->bo->month,$this->bo->day - 7,$this->bo->year);

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
				$print = '<a href="'.$this->page('week','&friendly=1&date='.sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day))."\" TARGET=\"cal_printer_friendly\" onMouseOver=\"window.status = '".lang('Generate printer-friendly version')."'\">[".lang('Printer Friendly').']</a>';
			}
			else
			{
				$printer = '<body bgcolor="'.$this->theme['bg_color'].'">';
				$prev_week_link = '';
				$next_week_link = '';
				$print =	'';
				$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
			}

			$var = Array(
				'printer_friendly'	=> $printer,
				'bg_text'		=> $this->theme['bg_text'],
				'small_calendar_prev'	=> $minical_prev,
				'prev_week_link'	=> $prev_week_link,
				'small_calendar_this'	=> $minical_this,
				'week_identifier'	=> $this->bo->get_week_label(),
				'next_week_link'	=> $next_week_link,
				'username'		=> $GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner),
				'small_calendar_next'	=> $minical_next,
				'week_display'		=> $this->display_weekly(
					Array(
						'date'		=> sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day),
						'showyear'	=> true,
						'owners'	=> $this->bo->owner
					)
				),
				'print'			=> $print
			);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'week_t' => 'week.tpl'
				)
			);
			$p->set_var($var);
			return $p->fp('out','week_t');

/*
			$this->bo->read_holidays();
			
			if (!$this->bo->printer_friendly || ($this->bo->printer_friendly && @$this->bo->prefs['calendar']['display_minicals']))
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

			$now	= $GLOBALS['phpgw']->datetime->makegmttime(0, 0, 0, $this->bo->month, $this->bo->day, $this->bo->year);
			$now['raw'] += $GLOBALS['phpgw']->datetime->tz_offset;
			$m = mktime(0,0,0,$this->bo->month,1,$this->bo->year);

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'day_t' => 'day.tpl'
				)
			);
			$p->set_block('day_t','day','day');
			$p->set_block('day_t','day_event','day_event');

			$var = Array(
				'printer_friendly'		=> $printer,
				'bg_text'			=> $this->theme['bg_text'],
				'daily_events'			=> $this->print_day(
					Array(
						'year'	=> $this->bo->year,
						'month'	=> $this->bo->month,
						'day'	=> $this->bo->day
					)
				),
				'small_calendar'		=> $minical,
				'date'				=> lang(date('F',$m)).' '.sprintf("%02d",$this->bo->day).', '.$this->bo->year,
				'username'			=> $GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner),
				'print'				=> $print
			);

			$p->set_var($var);
			$p->parse('day_events','day_event');
			$p->pparse('out','day');
*/
		}

		function year()
		{
			echo $this->printer_friendly($this->get_year());
		}

		function get_year()
		{
			if(!$this->bo->printer_friendly)
			{
				$print = '';
				$left_link = '<a href="'.$this->page('year','&year='.($this->bo->year - 1)).'">&lt;&lt;</a>';
				$right_link = '<a href="'.$this->page('year','&year='.($this->bo->year + 1)).'">&gt;&gt;</a>';
				$link = 'day';
				$printer = '<a href="'.$this->page('year','&friendly=1&year='.$this->bo->year).'" target="cal_printer_friendly" onMouseOver="window.status = '."'".lang('Generate printer-friendly version')."'".'">['.lang('Printer Friendly').']</a>';
			}
			else
			{
				$print = '<body bgcolor="'.$this->theme['bg_color'].'">';
				$left_link = '';
				$right_link = '';
				$link = '';
				$printer = '';
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
			return $p->fp('out','year_t');
		}
		
		function view($vcal_id=0,$cal_date=0)
		{
			$this->cal_header();

			$cal_id = get_var('cal_id',Array('GET','POST','DEFAULT'),$vcal_id);
			$date = get_var('date',Array('GET','DEFAULT'),$cal_date);
 
			// First, make sure they have permission to this entry
			if ($cal_id < 1)
			{
				$GLOBALS['phpgw']->template->set_var('phpgw_body','<center>'.lang('Invalid entry id.').'</center>'."\n");
				exit;
			}

			if(!$this->bo->check_perms(PHPGW_ACL_READ,$cal_id))
			{
				$GLOBALS['phpgw']->template->set_var('phpgw_body','<center>'.lang('You do not have permission to read this record!').'</center>'."\n");
				exit;
			}

			$event = $this->bo->read_entry($cal_id);

			if(!isset($event['id']))
			{
				$GLOBALS['phpgw']->template->set_var('phpgw_body','<center>'.lang("Sorry, this event does not exist").'.'.'</center>'."\n");
				exit;
			}

			$this->bo->repeating_events = Array();
			$this->bo->cached_events = Array();
			$this->bo->repeating_events[0] = $event;
			$datetime = mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year) - $GLOBALS['phpgw']->datetime->tz_offset;
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

			$ret_value = $this->view_event($event,True);
			$GLOBALS['phpgw']->template->set_var('phpgw_body',$ret_value);

			if($ret_value == '<center>'.lang('You do not have permission to read this record!').'</center>')
			{
				exit;
			}

			//$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p = &$GLOBALS['phpgw']->template;
			$p->set_file(
				Array(
					'form_button'	=> 'form_button_script.tpl'
				)
			);

			if($this->bo->check_perms(PHPGW_ACL_EDIT,$event))
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
					$p->parse('phpgw_body','form_button',True);

					$var = Array(
						'action_url_button'	=> $this->page('edit','&cal_id='.$cal_id),
						'action_text_button'	=> lang('Edit Series'),
						'action_confirm_button'	=> '',
						'action_extra_field'	=> '<input type="hidden" name="edit_type" value="series">'
					);
					$p->set_var($var);
					$p->parse('phpgw_body','form_button',True);
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
					$p->parse('phpgw_body','form_button',True);
				}

				$var = Array(
					'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uialarm.manager'),
					'action_text_button'	=> lang('Alarm Management'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> '<input type="hidden" name="cal_id" value="'.$cal_id.'">'
				);
				$p->set_var($var);
				$p->parse('phpgw_body','form_button',True);
			}

			if ($this->bo->check_perms(PHPGW_ACL_DELETE,$event))
			{
				if($event['recur_type'] != MCAL_RECUR_NONE)
				{
					$var = Array(
						'action_url_button'	=> $this->page('delete','&cal_id='.$cal_id),
						'action_text_button'	=> lang('Delete Single'),
						'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this single occurence ?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
						'action_extra_field'	=> '<input type="hidden" name="delete_type" value="single">'
							. '<input type="hidden" name="date" value="'.sprintf('%04d%02d%02d',$this->bo->year,$this->bo->month,$this->bo->day).'">'
					);
					$p->set_var($var);
					$p->parse('phpgw_body','form_button',True);

					$var = Array(
						'action_url_button'	=> $this->page('delete','&cal_id='.$cal_id),
						'action_text_button'	=> lang('Delete Series'),
						'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
						'action_extra_field'	=> '<input type="hidden" name="delete_type" value="series">'
					);
					$p->set_var($var);
					$p->parse('phpgw_body','form_button',True);

					if($event['recur_exception'])
					{
						$var = Array(
							'action_url_button'	=> $this->page('reinstate_list','&cal_id='.$cal_id),
							'action_text_button'	=> lang('Reinstate'),
							'action_confirm_button'	=> '',
							'action_extra_field'	=> ''
						);
						$p->set_var($var);
						$p->parse('phpgw_body','form_button',True);
					}
				}
				else
				{
					$var = Array(
						'action_url_button'	=> $this->page('delete','&cal_id='.$cal_id),
						'action_text_button'	=> lang('Delete'),
						'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to\\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
						'action_extra_field'	=> ''
					);
					$p->set_var($var);
					$p->parse('phpgw_body','form_button',True);
				}
//				}
			}

			$var = Array(
				'action_url_button'	=> $this->page('export'),
				'action_text_button'	=> lang('Export'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> '<input type="hidden" name="cal_id" value="'.$cal_id.'">'
			);
			$p->set_var($var);
			$p->parse('phpgw_body','form_button',True);

			if ($this->bo->return_to)
			{
				$var = Array(
					'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to),
					'action_text_button'	=> lang('Done'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				);
				$p->set_var($var);
				$p->parse('phpgw_body','form_button',True);
			}

			$GLOBALS['phpgw']->hooks->process('calendar_view');
		}

		function edit($params='')
		{
// 		No event loaded, so what to check?
//			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
//			{
//			   $this->no_edit();
//			}

			if($this->debug)
			{
				echo '<!-- params[readsess] = '.$params['readsess'].' -->'."\n";
				echo '<!-- params[cd] = '.$params['cd'].' -->'."\n";
			}

			if(isset($GLOBALS['HTTP_GET_VARS']['readsess']))
			{
				$params['readsess'] = $GLOBALS['HTTP_GET_VARS']['readsess'];
				$params['cd'] = 0;
			}

			if($this->debug)
			{
				echo '<!-- params[readsess] = '.$params['readsess'].' -->'."\n";
				echo '<!-- params[cd] = '.$params['cd'].' -->'."\n";
			}

			if($params != '' && @is_array($params) && @isset($params['readsess']))
			{
				$can_edit = True;
				$this->edit_form(
					Array(
						'event' => $this->bo->restore_from_appsession(),
						'cd' => $params['cd']
					)
				);
			}
			elseif(isset($GLOBALS['HTTP_GET_VARS']['cal_id']))
			{
				$cal_id = intval($GLOBALS['HTTP_GET_VARS']['cal_id']);
				$event = $this->bo->read_entry($cal_id);

				if(!$this->bo->check_perms(PHPGW_ACL_EDIT,$event))
				{
					Header('Location: '.$this->page('view','&cal_id='.$cal_id));
					$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
					exit;
				}
				if(@isset($GLOBALS['HTTP_POST_VARS']['edit_type']) && $GLOBALS['HTTP_POST_VARS']['edit_type'] == 'single')
				{
					$event['id'] = 0;
					$this->bo->set_recur_date($event,$GLOBALS['HTTP_POST_VARS']['date']);
					$event['recur_type'] = MCAL_RECUR_NONE;
					$event['recur_interval'] = 0;
					$event['recur_data'] = 0;
					$event['recur_enddate']['month'] = 0;
					$event['recur_enddate']['mday'] = 0;
					$event['recur_enddate']['year'] = 0;
					$event['recur_execption'] = array();
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
			if(!isset($GLOBALS['HTTP_POST_VARS']['cal_id']) || !$GLOBALS['HTTP_POST_VARS']['cal_id'])
			{
				Header('Location: '.$this->index());
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}
			$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			if(!isset($GLOBALS['HTTP_POST_VARS']['output_file']) || !$GLOBALS['HTTP_POST_VARS']['output_file'])
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				$GLOBALS['phpgw']->common->phpgw_header();
				
				$p = CreateObject('phpgwapi.Template',$this->template_dir);
				$p->set_file(
					Array(
						'form_button'	=> 'form_button_script.tpl'
					)
				);
				$var = Array(
					'action_url_button'	=> $this->page('export'),
					'action_text_button'	=> lang('Submit'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> "\n".lang('Enter Output Filename: ( .vcs appended )')."\n".'   <input name="output_file" size="25" maxlength="80" value="">'."\n"
						. '   <input type="hidden" name="cal_id" value="'.$GLOBALS['HTTP_POST_VARS']['cal_id'].'">'
				);
				$p->set_var($var);
				echo $p->fp('out','form_button');
			}
			else
			{
				$output_file = $GLOBALS['HTTP_POST_VARS']['output_file'].'.vcs';
				$vfs = CreateObject('phpgwapi.vfs');
//				if(!$vfs->file_exists('.calendar',array(RELATIVE_USER)))
//				{
//					$vfs->mkdir('.calendar',array(RELATIVE_USER));
//				}

				$content = ExecMethod('calendar.boicalendar.export',$GLOBALS['HTTP_POST_VARS']['cal_id']);
				$vfs->cd(array('string' => '/', 
							'relatives' => array(RELATIVE_USER)
							));
				$vfs->write(array('string' => $output_file,
				 			'relatives' => array (RELATIVE_USER), 
				 			'content' => $content
				 			));
//				$vfs->write($output_file, array (RELATIVE_USER_APP), $content);
				if($this->debug)
				{
					echo '<!-- DEBUG: Output Filename = '.$output_file.' -->'."\n";
					echo '<!-- DEBUG: Fakebase = '.$vfs->fakebase.' -->'."\n";
					echo '<!-- DEBUG: Path = '.$vfs->pwd().' -->'."\n";
				}
				if ($this->bo->return_to)
				{
					Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to));
				}
				else
				{
					Header('Location: '.$this->index());
				}
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}
		}

		function reinstate_list($params='')
		{
			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
			   $this->no_edit();
			}
			elseif(!$this->bo->check_perms(PHPGW_ACL_ADD))
			{
				$this->index();
			}

  			unset($GLOBALS['phpgw_info']['flags']['noheader']);
 	 		unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();
	   	
			echo '<center>';

			$cal_id = (isset($params['cal_id'])?intval($params['cal_id']):'');
			$cal_id = ($cal_id==''?intval($GLOBALS['HTTP_GET_VARS']['cal_id']):$cal_id);

			if ($cal_id < 1)
			{
				echo lang('Invalid entry id.').'</center>'."\n";
				exit;
			}

			if(!$this->bo->check_perms(PHPGW_ACL_READ))
			{
				echo lang('You do not have permission to read this record!').'</center>'."\n";
				exit;
			}

			$event = $this->bo->read_entry($cal_id);

			if(!isset($event['id']))
			{
				echo lang('Sorry, this event does not exist').'.'.'</center>'."\n";
				exit;
			}
			elseif(!isset($event['recur_exception']))
			{
				echo lang('Sorry, this event does not have exceptions defined').'.'.'</center>'."\n";
				exit;
			}

			$ret_value = $this->view_event($event,True);
			echo $ret_value;

			if($ret_value == '<center>'.lang('You do not have permission to read this record!').'</center>')
			{
				echo '</center>'."\n";
				exit;
			}

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'form_button'	=> 'form_button_script.tpl'
				)
			);

			$str = '';

			for($i=0;$i<count($event['recur_exception']);$i++)
			{
				$str .= '    <option value="'.$i.'">'.$GLOBALS['phpgw']->common->show_date($event['recur_exception'][$i]).'</option>'."\n";
			}

			$var = Array(
				'action_url_button'	=> $this->page('reinstate','&cal_id='.$cal_id),
				'action_text_button'	=> lang('Reinstate'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> "\n".'   <select name="reinstate_index[]" multiple size="5">'."\n".$str.'   </select>'
			);
			$p->set_var($var);
			echo $p->fp('out','form_button');

			$var = Array(
				'action_url_button'	=> $this->bo->return_to ? $GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to) : $this->page(''),
				'action_text_button'	=> lang('Cancel'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$p->set_var($var);
			echo $p->fp('out','form_button').'</center>';
		}

		function reinstate($params='')
		{
			if(!$this->bo->check_perms(PHPGW_ACL_EDIT))
			{
			   $this->no_edit();
			}
			elseif(!$this->bo->check_perms(PHPGW_ACL_ADD))
			{
				$this->index();
			}
			$cal_id = (isset($params['cal_id'])?intval($params['cal_id']):'');
			$cal_id = ($cal_id==''?intval($GLOBALS['HTTP_GET_VARS']['cal_id']):$cal_id);

			$reinstate_index = (isset($params['reinstate_index'])?intval($params['reinstate_index']):'');
			$reinstate_index = ($reinstate_index==''?intval($GLOBALS['HTTP_POST_VARS']['reinstate_index']):$reinstate_index);
			if($this->debug)
			{
				echo '<!-- Calling bo->reinstate -->'."\n";
			}
			$cd = $this->bo->reinstate(
				Array(
					'cal_id'	=> $cal_id,
					'reinstate_index'	=> $reinstate_index
				)
			);
			if($this->debug)
			{
				echo '<!-- Return Value = '.$cd.' -->'."\n";
			}
			if ($this->bo->return_to)
			{
				Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to));
			}			
			else
			{
				Header('Location: '.$this->page('',($cd?'&cd='.$cd:'')));
			}
			$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
			exit;
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
			$cal_id = get_var('cal_id',Array('GET'));
			if(!$cal_id)
			{
				Header('Location: '.$this->page('','&date='.sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day)));
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}

			$date = sprintf("%04d%02d%02d",$this->bo->year,$this->bo->month,$this->bo->day);
			if($this->bo->check_perms(PHPGW_ACL_DELETE,$cal_id))
			{
				$delete_type = get_var('delete_type',Array('POST'));
				if($deleted_type && $delete_type == 'single')
				{
					$date = $GLOBALS['HTTP_POST_VARS']['date'];
					$cd = $this->bo->delete_single(
						Array(
							'id'	=> intval($cal_id),
							'year'	=> substr($date,0,4),
							'month'	=> substr($date,4,2),
							'day'	=> substr($date,6,2)
						)
					);
				}
				else
				{
					$cd = $this->bo->delete_entry(intval($cal_id));
					$this->bo->expunge();
				}
			}
			else
			{
				$cd = '';
			}
			if ($this->bo->return_to)
			{
				Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to));
			}
			else
			{
				Header('Location: '.$this->page('','&date='.$date.($cd?'&cd='.$cd:'')));
			}
			$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
			exit;
		}

		function day()
		{
			$this->bo->read_holidays();
			
			if (!$this->bo->printer_friendly || ($this->bo->printer_friendly && @$this->bo->prefs['calendar']['display_minicals']))
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

			$now	= $GLOBALS['phpgw']->datetime->makegmttime(0, 0, 0, $this->bo->month, $this->bo->day, $this->bo->year);
			$now['raw'] += $GLOBALS['phpgw']->datetime->tz_offset;

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'day_t' => 'day.tpl'
				)
			);
			$p->set_block('day_t','day','day');
			$p->set_block('day_t','day_event','day_event');

			$var = Array(
				'printer_friendly'		=> $printer,
				'bg_text'			=> $this->theme['bg_text'],
				'daily_events'			=> $this->print_day(
					Array(
						'year'	=> $this->bo->year,
						'month'	=> $this->bo->month,
						'day'	=> $this->bo->day
					)
				),
				'small_calendar'		=> $minical,
				'date'				=> $this->bo->long_date($now),
				'username'			=> $GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner),
				'print'				=> $print
			);

			$p->set_var($var);
			$p->parse('day_events','day_event');
			echo $this->printer_friendly($p->fp('out','day'));
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

			$freetime = $GLOBALS['phpgw']->datetime->localdates(mktime(0,0,0,$event['start']['month'],$event['start']['mday'],$event['start']['year']) - $GLOBALS['phpgw']->datetime->tz_offset);
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

			if ($this->bo->return_to)
			{
				Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to));
			}
			else
			{
				Header('Location: '.$this->page('','')); 
			}
			$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
			exit;
		}

		function planner()
		{
			if(floor(phpversion()) < 4)
			{
				return;
			}
			$home = strstr($GLOBALS['PHP_SELF'],'home') !== False;
			// generate header and set global/member variables
			//
			$this->planner_prepare($home);

			// process events within selected interval
			//
			$this->planner_process_interval();

			// generate the planner view
			//
			if (!$home)
			{
				echo '<p>'.$this->planner_print_rows();
			}
			else
			{
				return $this->planner_print_rows();
			}
		}

		function set_planner_group_members()
		{
			$type = $GLOBALS['phpgw']->accounts->get_type($this->bo->owner);

			if ($type == 'g') // display schedule of all group members
			{
				$members = array();
				$ids = $GLOBALS['phpgw']->acl->get_ids_for_location($this->bo->owner, 1, 'phpgw_group');
				while (list(,$id) = each($ids))
				{
					if ($this->bo->check_perms(PHPGW_ACL_READ,0,$id))
					{
						$members[$GLOBALS['phpgw']->common->grab_owner_name($id)] = $id;
					}
				}
				ksort($members);
				$this->planner_group_members = $members;
			}
			else
			{
				$this->planner_group_members = array( 
					$GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner) => $this->bo->owner
				);
			}
		}

		/**
		 * planner_prepare - prepare the planner view
		 *
		 * - sets global environment variables
		 * - initializes class member variables used in multiple planner related functions
		 * - generates header lines for the planner view (month, calendar week, days)
		 */
		function planner_prepare($no_header = False)
		{
			// set some globals
			//
			if (!$no_header)
			{
				//unset($GLOBALS['phpgw_info']['flags']['noheader']);
				//unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				//$GLOBALS['phpgw']->common->phpgw_header();
				$this->cal_header();
			}

			// intervals_per_day can be configured in preferences now :-)
			//
			if (! $this->bo->prefs['calendar']['planner_intervals_per_day'])
			{
				$GLOBALS['phpgw']->preferences->add('calendar','planner_intervals_per_day',3);
				$GLOBALS['phpgw']->preferences->save_repository();
				$this->bo->prefs['calendar']['planner_intervals_per_day'] = 3;
			}
			$intervals_per_day = $this->bo->prefs['calendar']['planner_intervals_per_day'];
			$this->bo->save_sessiondata();	// need to save $this->bo->save_owner

			// set title for table and rows of planner view
			//
			if ($this->bo->sortby == 'category')
			{
				$title = lang('Category');
			}
			else
			{
				$title = lang('User');

				$this->set_planner_group_members();
			}

			// create/initialize variables directly used for HTML code generation
			//
			$this->planner_html   = CreateObject('calendar.html');
			$this->planner_header = array();
			$this->planner_rows   = array();

			// generate header lines with days and associated months
			//
			$hdr = &$this->planner_header;
			$hdr[0]['0']  = $title;
			$hdr[0]['.0'] = 'rowspan="3"';

			$this->planner_days = 0; // reset

			$m = $this->bo->month;
			$y = $this->bo->year;
			$this->bo->read_holidays($y);
			for ($i=1; $i<=$this->bo->num_months; $i++,$m++)
			{
				if ($m == 13)
				{
					$m = 1; $y++; // "wrap-around" into new year
					$this->bo->read_holidays($y);
				}
				$days = $GLOBALS['phpgw']->datetime->days_in_month($m,$y);

				$d     = mktime(0,0,0,$m,1,$y);
				$month = lang(date('F', $d)).strftime(' %Y', $d);
				$class = $m % 2 || $this->bo->num_months == 1 ? 'th' : 'row_on';
				$cols  = $days * $intervals_per_day;

				$hdr[0]['.'.$i] = 'class="'.$class.'" colspan="'.$cols.'" align="center"';
				$prev_month = sprintf('%04d%02d01',$y-($m==1),$m > 1?$m-1:12);
				$next_month = sprintf('%04d%02d01',$y+($m==12),$m < 12?$m+1:1);
				$prev_link = $GLOBALS['phpgw']->link('/index.php',"menuaction=calendar.uicalendar.planner&date=$prev_month");
				$next_link = $GLOBALS['phpgw']->link('/index.php',"menuaction=calendar.uicalendar.planner&date=$next_month");
				$hdr[0][$i] = "<b><a href=\"$prev_link\">&lt;&lt;</a> &nbsp $month &nbsp <a href=\"$next_link\">&gt;&gt;</a></b>";

				$add_owner = array();	// if no add-rights on the showed cal use own cal
				if (!$this->bo->save_owner && !$this->bo->check_perms(PHPGW_ACL_ADD) ||
					!$this->bo->check_perms(PHPGW_ACL_ADD,0,$this->bo->save_owner))
				{
					$add_owner = array(
						'owner' => $GLOBALS['phpgw_info']['user']['account_id']
					);
				}
				for ($d=1; $d<=$days; $d++)
				{
					$dayname = substr(lang(date('D',mktime(0,0,0,$m,$d,$y))),0,2);
					$index = $d + $this->planner_days;

					$hdr[2]['.'.$index] = 'colspan="'.$intervals_per_day.'" align="center"';

					// highlight today, saturday, sunday and holidays
					//
					$class = 'row_off';
					$dow = $GLOBALS['phpgw']->datetime->day_of_week($y,$m,$d);
					$date = sprintf("%04d%02d%02d",$y,$m,$d);
					if ($date == date('Ymd'))
					{
						$class = 'cal_today';
					}
					elseif ($this->bo->cached_holidays[$date])
					{
						$class = 'cal_holiday';
						$hdr[2]['.'.$index] .= ' title="'.$this->bo->cached_holidays[$date][0]['name'].'"';
					}
					elseif ($dow == 0 || $dow == 6)
					{
						$class = 'th';
					}

					$hdr[2]['.'.$index] .= " class=\"$class\"";

					$hdr[2][$index] = '<a href="'.$this->planner_html->link('/index.php',
								array(
									'menuaction' => 'calendar.uicalendar.add',
									'date' => $date
								) + $add_owner
							).'">'.$dayname.'<br>'.$d.'</a>';
				}
				$this->planner_days += $days;
			}

			// create/initialize member variables describing the time interval to be displayed
			//
			$this->planner_end_month = $m - 1;
			$this->planner_end_year  = $y;
			$this->planner_days_in_end_month = $GLOBALS['phpgw']->datetime->days_in_month($this->planner_end_month,$this->planner_end_year);
			$this->planner_firstday = intval(date('Ymd',mktime(0,0,0,$this->bo->month,1,$this->bo->year)));
			$this->planner_lastday  = intval(date('Ymd',mktime(0,0,0,$this->planner_end_month,$this->planner_days_in_end_month,$this->planner_end_year)));

			// generate line with calendar weeks in observed interval
			//
			$d      = mktime(0,0,0,$this->bo->month,1,$this->bo->year);
			$w      = date('W', $d);
			if ($w == 'W')	// php < 4.1
			{
				$w = 1 + intval(date('z',$d) / 7);	// a bit simplistic
			}
			$offset = (7-date("w", $d)+1)%7;
			$offset = $offset == 0 ? 7 : $offset;
			$class = $w % 2 ? 'th' : 'row_on';

			$hdr[1]['.'.$w] = 'class="'.$class.'" colspan="'.$intervals_per_day * $offset.'" align="left"';
			$hdr[1][$w] = '';
			if ($offset >= 3)
			{
				$hdr[1][$w] .= '<font size="-2"> '.lang('week').' '.$w.' </font>';
			}
			$days_left = $this->planner_days - $offset;

			$colspan = 7 * $intervals_per_day;
			while ($days_left > 0)
			{
				$colspan = ($days_left < 7) ? $days_left*$intervals_per_day : $colspan;
				$d += 604800; // 7 days whith 24 hours (1h == 3600 seconds) each
				$w = date('W', $d);
				if ($w == 'W')	// php < 4.1
				{
					$w = 1 + intval(date('z',$d) / 7);	// a bit simplistic
				}
				$w += (isset($hdr[1][$w]))?1:0; // bug in "date('W')" ?

				$class = $w % 2 ? 'th' : 'row_on';
				$hdr[1]['.'.$w] = 'class="'.$class.'" colspan="'.$colspan.'" align="left"';
				$hdr[1][$w] = '';
				if ($days_left >= 3)
				{
					$hdr[1][$w] .= '<font size="-2"> '.lang('week').' '.$w.' </font>';
				}

				$days_left -= 7;
			}
			return $hdr;
		}

		/**
		 * planner_update_row - update a row of the planner view
		 *
		 * parameters are:
		 *   - index (e.g. user id, category id, ...) of the row
		 *   - name/title of the row (e.g. user name, category name)
		 *   - the event to be integrated
		 *   - list of categories associated with the event
		 *   - first and last cell of the row
		 */
		function planner_update_row($index,$name,$event,$cat,$start_cell,$end_cell)
		{
			$rows              = &$this->planner_rows;
			$intervals_per_day = $this->bo->prefs['calendar']['planner_intervals_per_day'];
			$is_private        = !$this->bo->check_perms(PHPGW_ACL_READ,$event);
			
			$view = $this->planner_html->link('/index.php',
				array(
					'menuaction' => 'calendar.uicalendar.view',
					'cal_id' => $event['id'],
					'date' => date('Ymd',$this->bo->maketime($event['start']))
				)
			);

			// check how many lines are needed for this "row" (currently: user or category)
			$i = 0;
			do {
				++$i;

				$k = $index.'_'.$i;
				$ka = '.nr_'.$k;

				if (!isset($rows[$k]))
				{
					if ($i > 1)				// further line - no name
					{
						$rows[$k] = array();
						$rows[$index.'_1']['._name'] = 'rowspan="'.$i.'"';
					}
					else
					{
						$rows[$k]['_name'] = $name;
					}
					$rows[$ka] = 0;
				}
				$rows[$index.'_1']['._name'] .= ' nowrap'; // title must be one row

				$row = &$rows[$k];
				$akt_cell = &$rows[$ka];
			} while ($akt_cell > $start_cell);

			$id = $event['id'].'-'.date('Ymd',$this->bo->maketime($event['start']));
			if ($akt_cell < $start_cell)
			{
				$row[$id.'_1'] = '&nbsp;';
				$row['.'.$id.'_1'] = 'colspan="'.($start_cell-$akt_cell).'"';
			}
			$opt = &$row['.'.$id.'_2'];
			$cel = &$row[$id.'_2'];

			// if possible, display information about event within cells representing it
			//
			if ($start_cell < $end_cell)
			{
				$colspan = $end_cell - $start_cell;
				$opt .= "colspan=".(1 + $colspan);

				if (!$is_private)
				{
					$max_chars = 6*$colspan/$intervals_per_day-5;
					$max_chars /= ($colspan<9 ? 2 : 1);

					$min_chars = 3; // minimum for max_chars to display -> this should be configurable
					if ($max_chars >= $min_chars)
					{
						$len_title = strlen($event['title']);

						if ($len_title < $max_chars)
						{
							$title = $event['title'];
							$max_chars -= $len_title - 3; // 3 chars for separator: " - "
							$len_descr = strlen($event['description']);

							if ($len_descr > 0 && $len_descr <= $max_chars)
							{
								$event['print_description'] = 'yes';
							}
						}
						else
						{
							$title = substr($event['title'], 0 , $max_chars).'...';
						}
						$event['print_title'] = 'yes';
					}
				}
			}

			if ($bgcolor=$cat['color'])
			{
				$opt .= ' bgcolor="'.$bgcolor.'"';
			}
			if (!$is_private)
			{
				$opt .= ' title="'.lang('Title').": ".$event['title'];
				if ($event['description'])
				{
					$opt .= "\n".lang('Description').": ".$event['description'];
				}
			}
			else
			{
				$opt .= ' title="'.lang('You do not have permission to read this record!');
			}

			$start = $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset);
			$end = $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset);
			$opt .= "\n".lang('Start Date/Time').": ".$start."\n".lang('End Date/Time').": ".$end;

			if ($event['location'] && !$is_private)
			{
				$opt .= " \n".lang('Location').": ".$event['location'];
			}

			if (!$is_private)
			{
				$opt .= "\" onClick=\"location='".$view."'\"";
				$cel = '<a href="'.$view.'">';
			}
			else
			{
				$opt .= '"';
				$cel = '';
			}

			if ($event['priority'] == 3)
			{
				$cel .= $this->planner_html->image('calendar','mini-calendar-bar.gif','','border="0"');
			}
			if ($event['recur_type'])
			{
				$cel .= $this->planner_html->image('calendar','recur.gif','','border="0"');
			}
			$cel .= $this->planner_html->image('calendar',count($event['participants'])>1?'multi_3.gif':'single.gif',$this->planner_participants($event['participants']),'border="0"');
			$cel .= '</a>';

			if (isset($event['print_title']) && $event['print_title'] == 'yes')
			{
				$cel .= '<font size="-2"> '.$title.' </font>';
			}
			if (isset($event['print_description']) && $event['print_description'] == 'yes')
			{
				$cel .= '<font size="-2"> - '.$event['description'].' </font>';
			}
			$akt_cell = $end_cell + 1;

			return $rows;
		}

		function planner_process_event($event)
		{
			$intervals_per_day = $this->bo->prefs['calendar']['planner_intervals_per_day'];
			$interval = $this->planner_intervals[$intervals_per_day];
			$last_cell = $intervals_per_day * $this->planner_days - 1;

			$rows = &$this->planner_rows;

			// caluculate start and end of event
			//
			$event_start = intval(date('Ymd',mktime(0,0,0,$event['start']['month'],
																			$event['start']['mday'],
																			$event['start']['year'])));
			$event_end   = intval(date('Ymd',mktime(0,0,0,$event['end']['month'],
																			$event['end']['mday'],
																			$event['end']['year'])));

			// calculate first cell of event within observed interval
			//
			if ($event_start >= $this->planner_firstday)
			{
				$days_between = $GLOBALS['phpgw']->datetime->days_between($this->bo->month,1,$this->bo->year,$event['start']['month'],$event['start']['mday'],$event['start']['year']);

				$start_cell = $intervals_per_day * $days_between + $interval[$event['start']['hour']];
			}
			else
			{
				$start_cell = 0;
			}

			// calculate last cell of event within observed interval
			//
			if ($event_end <= $this->planner_lastday)
			{
				$days_between = $GLOBALS['phpgw']->datetime->days_between($this->bo->month,1,$this->bo->year,$event['end']['month'],$event['end']['mday'],$event['end']['year']);
				$end_cell = $intervals_per_day * $days_between + $interval[$event['end']['hour']];
				if ($end_cell == $start_cell && $end_cell < $last_cell)
				{
					$end_cell++;	// min. width 1 interval
				}
			}
			else
			{
				$end_cell = $last_cell;
			}
			// get the categories associated with event
			//
			if ($c = $event['category'])
			{
				list($cat)   = $this->planner_category($event['category']);
				if ($cat['parent'])
				{
					list($pcat) = $this->planner_category($c = $cat['parent']);
				}
				else
				{
					$pcat = $cat;
				}
			}
			else
			{
				$cat = $pcat = array( 'name' => lang('none'));
			}

			// add the event to it`s associated row(s)
			//
			if ($this->bo->sortby == 'category')
			{
				// event needs to show up in it`s category`s row
				//
				$this->planner_update_row($c,$pcat['name'],$event,$cat,$start_cell,$end_cell);
			}
			elseif ($this->bo->sortby == 'user')
			{
				// event needs to show up in rows of all participants that are also owners
				//
				reset($this->planner_group_members);
				while(list($user_name,$id) = each($this->planner_group_members))
				{
					$status = $event['participants'][$id];

					if (isset($status) && $status != 'R')
					{
						$this->planner_update_row($user_name,$user_name,$event,$cat,$start_cell,$end_cell);
					}
				}
			}
		}

		function planner_pad_rows()
	   {
			$rows = &$this->planner_rows;

			if ($this->bo->sortby == 'user')
			{
				// add empty rows for users that do not participante in any event
				//
				reset($this->planner_group_members);
				while(list($user_name,$id) = each($this->planner_group_members))
				{
					$k  = $user_name.'_1';
					$ka = '.nr_'.$k;

					if (!isset($rows[$k]))
					{
						$rows[$k]['_name'] = $user_name;
						$rows[$k]['._name'] .= ' nowrap';
						$rows[$ka] = 0;
					}
				}
			}

			// fill the remaining cols
			//
			$last_cell = $this->bo->prefs['calendar']['planner_intervals_per_day'] * $this->planner_days - 1;

			ksort($rows);
			while (list($k,$r) = each($rows))
			{
				if (is_array($r))
				{
					$rows['.'.$k] = 'class="'.$GLOBALS['phpgw']->nextmatchs->alternate_row_color().'"';
					$row = &$rows[$k];
					$akt_cell = &$rows['.nr_'.$k];
					if ($akt_cell < $last_cell)
					{
						$row['3'] = '&nbsp';
						$row['.3'] = 'colspan="'.(1+$last_cell-$akt_cell).'"';
					}
				}
			}
		}

		function planner_print_rows()
	   {
			$class = 'class="th"';
			$intervals_per_day = $this->bo->prefs['calendar']['planner_intervals_per_day'];

			if ($this->debug)
			{
				_debug_array($this->planner_rows);
				reset($this->planner_rows);
			}
			return $this->planner_html->table(
				array(
					'_hdr0' => $this->planner_header[0],
					'._hdr0' => $class,
					'_hdr1' => $this->planner_header[1],
					'._hdr1' => $class,
					'_hdr2' => $this->planner_header[2],
					'._hdr2' => $class
				)+$this->planner_rows,
				'width="100%" cols="'.(1+$this->planner_days_in_end_month*$intervals_per_day).'"');
	   }

		function planner_process_interval()
		{
			// generate duplicate free list of events within observed interval
			//
			$this->bo->store_to_cache(
				Array(
					'syear'	=> $this->bo->year,
					'smonth'	=> $this->bo->month,
					'sday'	=> 1,
					'eyear'	=> $this->planner_end_year,
					'emonth'	=> $this->planner_end_month,
					'eday'	=> $this->planner_days_in_end_month
				)
			);
			$this->bo->remove_doubles_in_cache($this->planner_firstday,$this->planner_lastday);

			// process all events within observed interval
			//
			for($v=$this->planner_firstday; $v <= $this->planner_lastday; $v += 1)
			{
				$daily = $this->bo->cached_events[$v];
				@reset($daily);

				print_debug('For Date',$v);
				print_debug('Count of items',count($daily));

				// process all events on day $v
				//
				while (list(,$event) = @each($daily))
				{
					if ($event['recur_type'])	// calculate start- + end-datetime for recuring events
					{
						$this->bo->set_recur_date($event,$v);
					}
					$this->planner_process_event($event);
				}
			}
			$this->planner_pad_rows();
		}

		function matrixselect()
		{
			$datetime = mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year) - $GLOBALS['phpgw']->datetime->tz_offset;

			$sb = CreateObject('phpgwapi.sbox');

			//unset($GLOBALS['phpgw_info']['flags']['noheader']);
			//unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			//$GLOBALS['phpgw']->common->phpgw_header();
			$this->cal_header();

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
				if(($GLOBALS['phpgw']->accounts->exists($user[0]) && $this->bo->check_perms(PHPGW_ACL_READ,0,$user[0])) || $GLOBALS['phpgw']->accounts->get_type($user[0]) == 'g')
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
						$members = $acct->member(intval($participants[$i]));
						while($members != False && list($index,$member) = each($members))
						{
							if($this->bo->check_perms(PHPGW_ACL_READ,0,$member['account_id']) && !isset($parts[$member['account_id']]))
							{
								$parts[$member['account_id']] = 1;
							}
						}
						break;
					case 'u':
						if($this->bo->check_perms(PHPGW_ACL_READ,0,$participants[$i]) && !isset($parts[$participants[$i]]))
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

			$matrixtype = get_var('matrixtype',Array('POST'));
			switch($matrixtype)
			{
				case 'free/busy':
					$freetime = $GLOBALS['phpgw']->datetime->gmtdate(mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year));
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
			echo '  <input type="hidden" name="matrixtype" value="'.$matrixtype.'">'."\n";
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
			$keywords = get_var('keywords',Array('POST'));
			if (empty($keywords))
			{
				// If we reach this, it is because they didn't search for anything,
				// attempt to send them back to where they where.
				Header('Location: ' . $GLOBALS['phpgw']->link('/index.php',array(
					'menuaction' => $GLOBALS['HTTP_POST_VARS']['from'],
					'date' => $GLOBALS['HTTP_POST_VARS']['year'].$GLOBALS['HTTP_POST_VARS']['month'].$GLOBALS['HTTP_POST_VARS']['day']
				)));
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			$error = '';

			$matches = 0;

			// There is currently a problem searching in with repeated events.
			// It spits back out the date it was entered.  I would like to to say that
			// it is a repeated event.

			// This has been solved by the little icon indicator for recurring events.

			$event_ids = $this->bo->search_keywords($keywords);
			$ids = Array();
			while(list($key,$id) = each($event_ids))
			{
				$event = $this->bo->read_entry($id);

				if(!$event['public'] && !$this->bo->check_perms(PHPGW_ACL_READ,$event))
				{
					continue;
				}

				$datetime = $this->bo->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset;

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
				$quantity = lang('%1 matches found',$matches).'.';
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
				'filter'     => $this->bo->filter,
				'cat_id'     => $this->bo->cat_id,
				'owner'      => $this->bo->owner,
				'year'       => $this->bo->year,
				'month'      => $this->bo->month,
				'day'        => $this->bo->day,
				'sortby'     => $this->bo->sortby,
				'num_months' => $this->bo->num_months
			);
			return _debug_array($data,False);
		}

		function output_template_array(&$p,$row,$list,$var)
		{
			if (!isset($var['hidden_vars']))
			{
				$var['hidden_vars'] = '';
			}
			$p->set_var($var);
			$p->parse($row,$list,True);
		}

		function page($_page='',$params='')
		{
			if($_page == '')
			{
				$page_ = explode('.',$this->bo->prefs['calendar']['defaultcalendar']);
				$_page = $page_[0];
				if ($_page=='planner_cat' || $_page=='planner_user')
				{
					$_page = 'planner';
				}
				elseif ($_page=='index' || ($_page != 'day' && $_page != 'week' && $_page != 'month' && $_page != 'year' && $_page != 'planner'))
				{
					$_page = 'month';
					$GLOBALS['phpgw']->preferences->add('calendar','defaultcalendar','month');
					$GLOBALS['phpgw']->preferences->save_repository();
				}
			}
			if($GLOBALS['phpgw_info']['flags']['currentapp'] == 'home' ||
			   strstr($GLOBALS['phpgw_info']['flags']['currentapp'],'mail'))	// email, felamimail, ...
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
			list(,,$method) = explode('.',MENUACTION);

			if (@$this->bo->printer_friendly)
			{
			   return;
			}

			$p = &$GLOBALS['phpgw']->template;
	
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

			if(MENUACTION == 'calendar.uicalendar.week')
			{
				unset($thisdate);
				$thisdate = mktime(0,0,0,$this->bo->month,$this->bo->day,$this->bo->year) - $GLOBALS['phpgw']->datetime->tz_offset;
				$sun = $GLOBALS['phpgw']->datetime->get_weekday_start($this->bo->year,$this->bo->month,$this->bo->day) - $GLOBALS['phpgw']->datetime->tz_offset;

				$str = '';
				for ($i = -7; $i <= 7; $i++)
				{
					$begin = $sun + (604800 * $i);
					$end = $begin + 604799;
					$str .= '<option value="' . $GLOBALS['phpgw']->common->show_date($begin,'Ymd') . '"'.($begin <= $thisdate && $end >= $thisdate?' selected':'').'>'
					   . $GLOBALS['phpgw']->common->show_date($begin,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']) . ' - '
					   . $GLOBALS['phpgw']->common->show_date($end,$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat']);
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
			}

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

			if(MENUACTION == 'calendar.uicalendar.planner')
			{
				$str = '';
				$date_str = '';
				
				$date = get_var('date',Array('GET'));
				if($date)
				{
					$date_str .= '    <input type="hidden" name="date" value="'.$date.'">'."\n";
				}
				$date_str .= '    <input type="hidden" name="month" value="'.$this->bo->month.'">'."\n";
				$date_str .= '    <input type="hidden" name="day" value="'.$this->bo->day.'">'."\n";
				$date_str .= '    <input type="hidden" name="year" value="'.$this->bo->year.'">'."\n";

				for($i=1; $i<=6; $i++)
				{
					$str .= '<option value="'.$i.'"'.($i == $this->bo->num_months?' selected':'').'>'.$i.'</option>'."\n";
				}

				$var = Array(
					'action_url'	=> $this->page($method,''),
					'form_name'	=> 'SelectNumberOfMonths',
					'label'		=> lang('Number of Months'),
					'hidden_vars' => $date_str,
					'form_label'	=> 'num_months',
					'form_onchange'	=> 'document.SelectNumberOfMonths.submit()',
					'action_extra_field'	=> $date_str,
					'row'		=> $str,
					'go'		=> lang('Go!')
				);
				$this->output_template_array($p,'table_row','footer_row',$var);
			}

			$var = Array(
				'submit_button'		=> lang('Submit'),
				'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uiicalendar.import'),
				'action_text_button'	=> lang('Import'),
				'action_confirm_button'	=> '',
				'action_extra_field'	=> ''
			);
			$this->output_template_array($p,'b_row','form_button',$var);
			$p->parse('table_row','blank_row',True);

			$p->parse('phpgw_body','footer_table',True);
		}

		function css()
		{
			$GLOBALS['phpgw']->browser->browser();
			if($GLOBALS['phpgw']->browser->get_agent() == 'MOZILLA')
			{
				$time_width = (intval($this->bo->prefs['common']['time_format']) == 12?12:8);
			}
			else
			{
			   $time_width = (intval($this->bo->prefs['common']['time_format']) == 12?10:7);
			}

			return 'A.minicalendar { color: #000000; font: xx-small '.$this->theme['font'].' }'."\n"
				. '  A.bminicalendar { color: #336699; font: italic bold xx-small '.$this->theme['font'].' }'."\n"
				. '  A.minicalendargrey { color: #999999; font: xx-small '.$this->theme['font'].' }'."\n"
				. '  A.bminicalendargrey { color: #336699; font: italic bold xx-small '.$this->theme['font'].' }'."\n"
				. '  A.minicalhol { padding-left:3px; padding-right:3px; background: '.$this->holiday_color.'; color: #000000; font: xx-small '.$this->theme['font'].' }'."\n"
				. '  A.bminicalhol { padding-left:3px; padding-right:3px; background: '.$this->holiday_color.'; color: #336699; font: italic bold xx-small '.$this->theme['font'].' }'."\n"
				. '  A.minicalgreyhol { padding-left:3px; padding-right:3px; background: '.$this->holiday_color.'; color: #999999; font: xx-small '.$this->theme['font'].' }'."\n"
				. '  A.bminicalgreyhol { padding-left:3px; padding-right:3px; background: '.$this->holiday_color.'; color: #999999; font: italic bold xx-small '.$this->theme['font'].' }'."\n"
				. '  .event-on { background: '.$this->theme['row_on'].'; color: '.$this->theme['bg_text'].'; font: 100 80%/110% '.$this->theme['font'].'; vertical-align: middle }'."\n"
				. '  .event-off { background: '.$this->theme['row_off'].'; color: '.$this->theme['bg_text'].'; font: 100 80%/110% '.$this->theme['font'].'; vertical-align: middle }'."\n"
				. '  .event-holiday { background: '.$this->theme['bg04'].'; color: '.$this->theme['bg_text'].'; font: 100 80%/110% '.$this->theme['font'].'; vertical-align: middle }'."\n"
				. '  .time { background: '.$this->theme['navbar_bg'].'; color: '.$this->theme['bg_text'].'; font: 65%/100% '.$this->theme['font'].'; width: '.$time_width.'%; border: 1px '.$this->theme['navbar_text'].'; vertical-align: middle }'."\n"
				. '  .tablecell { width: 80px; height: 80px }';
		}

		function no_edit()
		{
			if(!isset($GLOBALS['phpgw_info']['flags']['noheader']))
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
			$is_private = !$event['public'] && !$this->bo->check_perms(PHPGW_ACL_READ,$event);
			$editable = !$this->bo->printer_friendly && $this->bo->check_perms(PHPGW_ACL_READ,$event);

			$starttime = $this->bo->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset;
			$endtime = $this->bo->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset;
			$rawdate = mktime(0,0,0,$month,$day,$year);
			$rawdate_offset = $rawdate - $GLOBALS['phpgw']->datetime->tz_offset;
			$nextday = mktime(0,0,0,$month,$day + 1,$year) - $GLOBALS['phpgw']->datetime->tz_offset;
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
					$end_time = $GLOBALS['phpgw']->common->show_date(mktime(23,59,59,$month,$day,$year) - $GLOBALS['phpgw']->datetime->tz_offset,$this->bo->users_timeformat);
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
			$text = '';
			if(!$is_private)
			{
				$text .= $this->bo->display_status($event['users_status']);
			}
//			$text = '<font size="-2" face="'.$this->theme['font'].'"><nobr>'.$time.'</nobr> '.$this->bo->get_short_field($event,$is_private,'title').$text.'</font>'.$GLOBALS['phpgw']->browser->br;
			$text = '<font size="-2" face="'.$this->theme['font'].'"><nobr>&nbsp;'.$time.'&nbsp;</nobr> '.$this->bo->get_short_field($event,$is_private,'title').$text.': <I>'.$this->bo->get_short_field($event,$is_private,'description').'</I></font>'.$GLOBALS['phpgw']->browser->br;

			if ($editable)
			{
				$date = sprintf('%04d%02d%02d',$year,$month,$day);
				$this->link_tpl->set_var('link_link',$this->page('view','&cal_id='.$event['id'].'&date='.$date));
				$this->link_tpl->set_var('lang_view',lang('View this entry'));
				$this->link_tpl->parse('picture','link_open',True);

				if($event['priority'] == 3)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','high'),
						'width'	=> 8,
						'height'=> 17,
						'alt' => lang('high priority'),
						'title' => lang('high priority')
					);
				}
				if($event['recur_type'] == MCAL_RECUR_NONE)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','circle'),
						'width'	=> 5,
						'height'=> 7,
						'alt' => lang('single event'),
						'title' => lang('single event')
					);
				}
				else
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','recur'),
						'width'	=> 12,
						'height'=> 12,
						'alt' => lang('recurring event'),
						'title' => lang('recurring event')
					);
				}

				$participants = $this->planner_participants($event['participants']);
				if(count($event['participants']) > 1)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','multi_3'),
						'width'	=> 14,
						'height'=> 14,
						'alt' => $participants,
						'title' => $participants
					);
				}
				else
				{
					$picture[] = Array(
						'pict'	=>  $GLOBALS['phpgw']->common->image('calendar','single'),
						'width'	=> 14,
						'height'=> 14,
						'alt' => $participants,
						'title' => $participants
					);
				}
				if($event['public'] == 0)
				{
					$picture[] = Array(
						'pict'	=> $GLOBALS['phpgw']->common->image('calendar','private'),
						'width'	=> 13,
						'height'=> 13,
						'alt' => lang('private'),
						'title' => lang('private')
					);
				}
				if(@isset($event['alarm']) && count($event['alarm']) >= 1)
				{
					if($this->bo->alarm_today($event,$rawdate_offset,$starttime))
					{
						$picture[] = Array(
							'pict'	=> $GLOBALS['phpgw']->common->image('calendar','alarm'),
							'width'	=> 13,
							'height'=> 13,
							'alt' => lang('alarm'),
							'title' => lang('alarm')
						);
					}
				}

				$description = $this->bo->get_short_field($event,$is_private,'description');
				for($i=0;$i<count($picture);$i++)
				{
					$var = Array(
						'pic_image'  => $picture[$i]['pict'],
						'width'      => $picture[$i]['width'],
						'height'     => $picture[$i]['height'],
						'alt'        => $picture[$i]['alt'],
						'title'      => $picture[$i]['title']
					);
					$this->output_template_array($this->link_tpl,'picture','pict',$var);
				}
			}
			if ($text)
			{
				$var = Array(
					'text' => $text
				);
				$this->output_template_array($this->link_tpl,'picture','link_text',$var);
			}

			if ($editable)
			{
				$this->link_tpl->parse('picture','link_close',True);
			}
			$str = $this->link_tpl->fp('out','link_pict');
			$this->link_tpl->set_var('picture','');			
			$this->link_tpl->set_var('out','');
//			unset($p);
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

			$start = mktime($event['start']['hour'],$event['start']['min'],$event['start']['sec'],$month,$mday,$year) - $GLOBALS['phpgw']->datetime->tz_offset;
			$end = $this->bo->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset;

			$overlap = '';
			for($i=0;$i<count($overlapping_events);$i++)
			{
				$overlapped_event = $this->bo->read_entry($overlapping_events[$i]);
				$overlap .= '<li> ['.$GLOBALS['phpgw']->common->grab_owner_name($overlapped_event['owner']).'] '.$this->link_to_entry($overlapped_event,$month,$mday,$year);
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
				'overlap_text'	=> lang('Your suggested time of <B> %1 - %2 </B> conflicts with the following existing calendar entries:',$GLOBALS['phpgw']->common->show_date($start),$GLOBALS['phpgw']->common->show_date($end)),
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
				$status = substr($this->bo->get_long_status($status),0,1);
				
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
				echo '<!-- Inside participants() : '.$names.' -->'."\n";
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
					$ids = explode(',',$ids);
				}
				else
				{
					$ids = array( 0 => $ids);
				}
			}
			@reset($ids);
			$ret_val = Array();
			while(list(,$id) = each($ids))
			{
				if (!isset($cats[$id]))
				{
					$cats[$id] = $this->cat->return_single( $id );
					$cats[$id]['color'] = strstr($cats[$id]['descr'],'#');
				}
				$ret_val[] = $cats[$id];
			} 
			return $ret_val;
		}

		function week_header($month,$year,$display_name = False)
		{
			$this->weekstarttime = $GLOBALS['phpgw']->datetime->get_weekday_start($year,$month,1);

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
				'class'	=> 'th',
				'font_color'	=> $this->theme['th_text']
			);
			if($this->bo->printer_friendly && @$this->bo->prefs['calendar']['print_black_white'])
			{
				$var = Array(
					'class'	=> '',
					'font_color'	=> ''
				);
			}
			$p->set_var($var);

			$col_width = 14;
			$p->set_var('col_width','14');
			if($display_name == True)
			{
				$p->set_var('col_title',lang('name'));
				$p->parse('column_header','column_title',True);
				$col_width = 12;
			}

			if($GLOBALS['phpgw']->datetime->days[$i]['weekday'])
			{
				switch($col_width)
				{
					case 12:
						$col_width = 16;
						break;
					case 14:
						$col_width = 20;
						break;
				}
			}

			$p->set_var('col_width',$col_width);

			for($i=0;$i<7;$i++)
			{
				if(!$this->bo->prefs['calendar']['weekdays_only'] || $GLOBALS['phpgw']->datetime->days[$i]['weekday'])
				{
					$p->set_var('col_title',lang($GLOBALS['phpgw']->datetime->days[$i]['name']));
					$p->parse('column_header','column_title',True);
				}
			}
			return $p->fp('out','monthly_header');
		}

		function display_week($startdate,$weekly,$cellcolor,$display_name = False,$owner=0,$monthstart=0,$monthend=0)
		{
			if($owner == 0)
			{
				$owner = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$temp_owner = $this->bo->owner;

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
			$today = date('Ymd',$GLOBALS['phpgw']->datetime->users_localtime);
			$daily = $this->set_week_array($startdate - $GLOBALS['phpgw']->datetime->tz_offset,$cellcolor,$weekly);
			@reset($daily);
			while(list($date,$day_params) = each($daily))
			{
				$year = intval(substr($date,0,4));
				$month = intval(substr($date,4,2));
				$day = intval(substr($date,6,2));
				$dow = $GLOBALS['phpgw']->datetime->day_of_week($year,$month,$day);
				if($this->bo->prefs['calendar']['weekdays_only'] && ($dow == 0 || $dow == 6))
				{
					continue;
				}
				$var = Array(
					'column_data'	=> '',
					'extra'		=> ''
				);
				$p->set_var($var);
				if ($weekly || ($date >= $monthstart && $date <= $monthend))
				{
					if ($day_params['new_event'])
					{
						$new_event_link = ' <a href="'.$this->page('add','&date='.$date).'">'
							. '<img src="'.$GLOBALS['phpgw']->common->image('calendar','new').'" width="10" height="10" alt="'.lang('New Entry').'" border="0" align="center">'
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
					if($day_params['week'])
					{
						$var['new_event_link'] .= '<font size="-2"> &nbsp; '.
							(!$this->bo->printer_friendly?'<a href="'.$this->page('week','&date='.$date).'">' .$day_params['week'].'</a>':$day_params['week']);
					}

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
/*					if($day_params['week'])
					{
						$var = Array(
							'week_day_font_size'	=> '-2',
							'events'		=> (!$this->bo->printer_friendly?'<a href="'.$this->page('week','&date='.$date).'">' .$day_params['week'].'</a>':$day_params['week'])
						);
						$this->output_template_array($p,'column_data','day_event',$var);
						$p->set_var('events','');
					} */
				}
				$p->parse('column_header','month_column',True);
				$p->set_var('column_data','');
			}
			$this->bo->owner = $temp_owner;
			return $p->fp('out','monthly_header');
		}
		
		function display_month($month,$year,$showyear,$owner=0)
		{
			if($this->debug)
			{
				echo '<!-- datetime:gmtdate = '.$GLOBALS['phpgw']->datetime->cv_gmtdate.' -->'."\n";
			}

			$this->bo->store_to_cache(
				Array(
					'syear'	=> $year,
					'smonth'=> $month,
					'sday'	=> 1
				)
			);

			$monthstart = intval(date('Ymd',mktime(0,0,0,$month    ,1,$year)));
			$monthend   = intval(date('Ymd',mktime(0,0,0,$month + 1,0,$year)));

			$start = $GLOBALS['phpgw']->datetime->get_weekday_start($year, $month, 1);

			if($this->debug)
			{
				echo '<!-- display_month:monthstart = '.$monthstart.' -->'."\n";
				echo '<!-- display_month:start = '.date('Ymd H:i:s',$start).' -->'."\n";
			}

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

			for ($i=intval($start + $GLOBALS['phpgw']->datetime->tz_offset);intval(date('Ymd',$i)) <= $monthend;$i += 604800)
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
		
			$start = $GLOBALS['phpgw']->datetime->get_weekday_start($year, $month, $day);

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
			if($this->bo->prefs['calendar']['weekdays_only'])
			{
				$cols -= 2;
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

		function view_event($event,$alarms=False)
		{
			if((!$event['participants'][$this->bo->owner] && !$this->bo->check_perms(PHPGW_ACL_READ,$event)))
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
				'data'	=> $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset)
			);
	
			$var[] = Array(
				'field'	=> lang('End Date/Time'),
				'data'	=> $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset)
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
				'data'	=> $GLOBALS['phpgw']->common->show_date($this->bo->maketime($event['modtime']) - $GLOBALS['phpgw']->datetime->tz_offset)
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
					$str .= ($str?'<br>':'').$GLOBALS['phpgw']->common->grab_owner_name($user).' ('.($this->bo->check_perms(PHPGW_ACL_EDIT,0,$user)?'<a href="'.$this->page('edit_status','&cal_id='.$event['id'].'&owner='.$user).'">'.$this->bo->get_long_status($short_status).'</a>':$this->bo->get_long_status($short_status)).')'."\n";
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
						$recur_end -= $GLOBALS['phpgw']->datetime->tz_offset;
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

			if($alarms && @isset($event['alarm']))
			{
				$p->set_var('hr_text','<hr>');
				$p->parse('row','hr',True);
				$p->set_var('hr_text','<center><b>'.lang('Alarms').'</b></center><br>');
				$p->parse('row','hr',True);

				@reset($event['alarm']);
				while(list($key,$alarm) = each($event['alarm']))
				{
					$icon = '<img src="'.$GLOBALS['phpgw']->common->image('calendar',($alarm['enabled']?'enabled':'disabled')).'" width="13" height="13">';
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

			print_debug('in print_day()');

			$this->bo->store_to_cache(
				Array(
					'syear'	=> $params['year'],
					'smonth'	=> $params['month'],
					'sday'	=> $params['day'],
					'eyear'	=> $params['year'],
					'emonth'	=> $params['month'],
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
			$p->set_block('day_cal','day_event_on','day_event_on');
			$p->set_block('day_cal','day_event_off','day_event_off');
			$p->set_block('day_cal','day_event_holiday','day_event_holiday');
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

			print_debug('Interval set to',intval($this->bo->prefs['calendar']['interval']));

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

			$daily = $this->set_week_array($GLOBALS['phpgw']->datetime->get_weekday_start($params['year'],$params['month'],$params['day']),$this->theme['row_on'],True);
			print_debug('Date to Eval',$date_to_eval);
			if($daily[$date_to_eval]['appts'])
			{
				$day_start = mktime(intval($this->bo->prefs['calendar']['workdaystarts']-1),0,0,$params['month'],$params['day'],$params['year']);
				$day_end = mktime(intval($this->bo->prefs['calendar']['workdayends']),0,1,$params['month'],$params['day'],$params['year']);
				$starttime = 0;
				$endtime = 0;
				$events = $this->bo->cached_events[$date_to_eval];
				$c_events = count($events);
				print_debug('Date',$date_to_eval);
				print_debug('Count',$c_events);
				for($i=0;$i<$c_events;$i++)
				{
					$starttime = $this->bo->maketime($events[$i]['start']);
					$endtime = $this->bo->maketime($events[$i]['end']);
					
					if($events[$i]['recur_type'] == MCAL_RECUR_NONE)
					{
						$ind = 0;
						$interval_start = 0; 
						if ($starttime < $day_start)
						{
							$ind = 0;
						}
						elseif ($starttime >= $day_end)
						{
							$ind = 99;
						}
						else
						{
							$ind = intval($events[$i]['start']['hour']);
							$interval_start = intval($events[$i]['start']['min'] / intval($this->bo->prefs['calendar']['interval']));
							print_debug('Start Time Minutes',$events[$i]['start']['min']);
							print_debug('Interval',$interval_start);
						}
					}
					else
					{
						$ind = intval($events[$i]['start']['hour']);
						$interval_start = intval($events[$i]['start']['min'] / intval($this->bo->prefs['calendar']['interval']));
					}

					if(($ind < intval($this->bo->prefs['calendar']['workdaystarts'])) || ($ind > intval($this->bo->prefs['calendar']['workdayends'])))
					{
						$ind = $ind < intval($this->bo->prefs['calendar']['workdaystarts']) ? 0 : 99;
						$interval_start = 0;
					}

					if((($ind <> 99) && ($ind <> 0)) && (($starttime <> 0) && ($endtime <> 0)))
					{
						print_debug('IND before',$ind);
						if($ind <= date('H',$last_endtime-1) && $last_ind)	// -1 to allow events to end on a full hour, without blocking the next hour-slot
						{
							$ind = $last_ind;
							$interval_start = $last_interval_start;
						}
						print_debug('IND after',$ind);
					}
					$time[$ind][$interval_start] .= $this->link_to_entry($events[$i],$params['month'],$params['day'],$params['year']);

					print_debug('IND',$ind);
					print_debug('TIME',$time[$ind][$interval_start]);

					if ($starttime < $day_start)
					{
						$starttime = $day_start;
					}
					if ($endtime > $day_end)
					{
						$endtime = $day_end;
					}
					if ($starttime <> $endtime)
					{
						$rowspan = $rowspan_arr[$ind][$interval_start];
						if($rowspan == 0 || $last_endtime <= $starttime)
						{
							$rowspan = intval(round(($endtime - $starttime) / (60 * intval($this->bo->prefs['calendar']['interval']))));
						}
						elseif($last_endtime < $endtime && $last_starttime)
						{
							$rowspan = intval(round(($endtime - $last_starttime) / (60 * intval($this->bo->prefs['calendar']['interval']))));
						}
						$mins = (int)((($endtime - $starttime) / 60) % 60);

						if(($mins <> 0 && $mins <= intval(60 / intval($this->bo->prefs['calendar']['interval']))) || ($mins == 0 && date('i',$endtime) > intval($this->bo->prefs['calendar']['interval'])))
						{
							$rowspan += 1;
						}
						print_debug('Rowspan being set to',$rowspan);

						if ($rowspan > $rowspan_arr[$ind][$interval_start])
						{
							$rowspan_arr[$ind][$interval_start] = $rowspan;
						}
					}
					$last_ind = $ind;
					$last_interval_start = $interval_start;
					if ($rowspan <= 1 || !$last_starttime)
					{
						$last_starttime = $starttime;
					}
					$last_endtime = $endtime;
					print_debug('Time',$GLOBALS['phpgw']->common->show_date($this->bo->maketime($events[$i]['start']) - $GLOBALS['phpgw']->datetime->tz_offset).' - '.$GLOBALS['phpgw']->common->show_date($this->bo->maketime($events[$i]['end']) - $GLOBALS['phpgw']->datetime->tz_offset));
					print_debug('Start',$ind);
					print_debug('Interval #',$interval_start);
				}
			}

			// squish events that use the same cell into the same cell.
			// For example, an event from 8:00-9:15 and another from 9:30-9:45 both
			// want to show up in the 8:00-9:59 cell.
			$holiday_names = $daily[$date_to_eval]['holidays'];
			if(!$holiday_names)
			{
				if($GLOBALS['phpgw']->nextmatchs->alternate_row_color() == $this->theme['row_on'])
				{
					$row_to_print = '_on';
				}
				else
				{
					$row_to_print = '_off';
				}
			}
			else
			{
				$row_to_print = '_holiday';
				while(list($index,$name) = each($holiday_names))
				{
					$time[0][0] = '<center>'.$name.'</center>'.$time[0][0];
				}
			}

			$rowspan = intval($rowspan_arr[0][0]);
			// events before workdaystart
			if (isset($time[0][0]))
			{
				$var = array('event' => $time[0][0]);
				if ($rowspan > 1)
				{
					$var['extras'] = ' rowspan="'.$rowspan.'"';
				}
				$this->output_template_array($p,'item','day_event'.$row_to_print,$var);

				$var = Array(
					'open_link'	=> '',
					'time'		=> '&nbsp;',
					'close_link'	=> ''
				);
				$this->output_template_array($p,'item','day_time',$var);
				$p->parse('row','day_row',True);
				$p->set_var('item','');
			}
			// events between workdaystart and -end
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
						if (isset($time[$i][$j]))
						{
							$p->set_var('event',$time[$i][$j]);
							if($GLOBALS['phpgw']->nextmatchs->alternate_row_color() == 'row_on')
							{
								$row_to_print = '_on';
							}
							else
							{
								$row_to_print = '_off';
							}
							$p->parse('item','day_event'.$row_to_print,False);
						}
						$rowspan--;
					}
					elseif (!isset($time[$i][$j]))
					{
						$p->set_var('event','&nbsp;');
						if($GLOBALS['phpgw']->nextmatchs->alternate_row_color() == 'row_on')
						{
							$row_to_print = '_on';
						}
						else
						{
							$row_to_print = '_off';
						}
						$p->parse('item','day_event'.$row_to_print,False);
					}
					else
					{
						$rowspan = intval($rowspan_arr[$i][$j]);
						if ($rowspan > 1)
						{
							$p->set_var('extras',' rowspan="'.$rowspan.'"');
						}
						$p->set_var('event',$time[$i][$j]);
						if($GLOBALS['phpgw']->nextmatchs->alternate_row_color() == 'row_on')
						{
							$row_to_print = '_on';
						}
						else
						{
							$row_to_print = '_off';
						}
						$p->parse('item','day_event'.$row_to_print,False);
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
			// events after workdayend
			if (isset($time[99][0]))
			{
				$var = array('event' => $time[99][0]);
				if($GLOBALS['phpgw']->nextmatchs->alternate_row_color() == 'row_on')
				{
					$row_to_print = '_on';
				}
				else
				{
					$row_to_print = '_off';
				}
				$this->output_template_array($p,'item','day_event'.$row_to_print,$var);

				$var = Array(
					'open_link'	=> '',
					'time'		=> '&nbsp;',
					'close_link'	=> ''
				);
				$this->output_template_array($p,'item','day_time',$var);
				$p->parse('row','day_row',True);
				$p->set_var('item','');
			}
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
			$increment = $this->bo->prefs['calendar']['interval'];
//			$increment = 15;
			$interval = (int)(60 / $increment);

			$pix = $GLOBALS['phpgw']->common->image('calendar','pix');

			$str = '<center>'.lang($GLOBALS['phpgw']->common->show_date($date['raw'],'l'))
				. ', '.$this->bo->long_date($date).'<br>'
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
				$this->bo->so->owner = $part;
				$this->bo->so->open_box($part);
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
			$this->bo->so->owner = $owner;
			$this->bo->so->open_box($owner);
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
				$var = Array(
					'action_url_button'	=> $this->page('set_action','&cal_id='.$cal_id.'&action='.$param),
					'action_text_button'	=> '  '.$text.'  ',
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				);
				$p->set_var($var);
				$str .= '<td>'.$p->fp('out','form_button').'</td>'."\n";
			}
			if ($this->bo->return_to)
			{
				$var = Array(
					'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to),
					'action_text_button'	=> lang('cancel'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				);
				$p->set_var($var);
				$str .= '<td>'.$p->fp('out','form_button').'</td>'."\n";
			}
			$str = '<td><b>'.$GLOBALS['phpgw']->common->grab_owner_name($this->bo->owner).":</b></td>\n".$str;

			return '<table width="100%"><tr align="center">'."\n".$str.'</tr></table>'."\n";
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
				'common_hidden'	=> '<input type="hidden" name="cal[id]" value="'.$event['id'].'">'."\n"
										. '<input type="hidden" name="cal[owner]" value="'.$event['owner'].'">'."\n"
										. '<input type="hidden" name="cal[uid]" value="'.$event['uid'].'">'."\n"
										. ($GLOBALS['HTTP_GET_VARS']['cal_id'] && $event['id'] == 0?'<input type="hidden" name="cal[reference]" value="'.$GLOBALS['HTTP_GET_VARS']['cal_id'].'">'."\n":
										  (@isset($event['reference'])?'<input type="hidden" name="cal[reference]" value="'.$event['reference'].'">'."\n":''))
										. (@isset($GLOBALS['phpgw_info']['server']['deny_user_grants_access']) && $GLOBALS['phpgw_info']['server']['deny_user_grants_access']?
										  '<input type="hidden" name="participants[]" value="'.$this->bo->owner.'">'."\n":''),
				'errormsg'		=> ($param['cd']?$GLOBALS['phpgw']->common->check_code($param['cd']):'')
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
				'data'	=> '<select name="categories[]" multiple size="5"><option value="0">'.lang('Choose the category').'</option>'.$this->cat->formatted_list('select','all',$check_cats,True).'</select>'
			);

// Location
			$var[] = Array(
				'field'	=> lang('Location'),
				'data'	=> '<input name="cal[location]" size="25" maxlength="80" value="'.$event['location'].'">'
			);

// Date
			$start = $this->bo->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset;
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
			$end = $this->bo->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset;
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
			if(!isset($GLOBALS['phpgw_info']['server']['deny_user_grants_access']) || !$GLOBALS['phpgw_info']['server']['deny_user_grants_access'])
			{
				$accounts = $GLOBALS['phpgw']->acl->get_ids_for_location('run',1,'calendar');
				$users = Array();
				$this->build_part_list($users,$accounts,$event['owner']);

				$str = '';
				@asort($users);
				@reset($users);
				while (list($id,$user_array) = each($users))
				{
					if($id != intval($event['owner']))
					{
						$str .= '    <option value="' . $id.$event['participants'][$id] . '"'.($event['participants'][$id]?' selected':'').'>('.$user_array['type'].') '.$user_array['name'].'</option>'."\n";
					}
				}
				$var[] = Array(
					'field'	=> lang('Participants'),
					'data'	=> "\n".'   <select name="participants[]" multiple size="5">'."\n".$str.'   </select>'
				);

// I Participate
				if((($event['id'] > 0) && isset($event['participants'][$event['owner']])) || !$event['id'])
				{
					$checked = ' checked';
				}
				else
				{
					$checked = '';
				}
				$var[] = Array(
					'field'	=> $GLOBALS['phpgw']->common->grab_owner_name($event['owner']).' '.lang('Participates'),
					'data'	=> '<input type="checkbox" name="participants[]" value="'.$event['owner'].$event['participants'][$event['owner']].'"'.$checked.'>'
				);
			}
			
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
				$recur_end = $this->bo->maketime($event['recur_enddate']) - $GLOBALS['phpgw']->datetime->tz_offset;
			}
			else
			{
				$checked = '';
				$recur_end = $this->bo->maketime($event['start']) + 86400 - $GLOBALS['phpgw']->datetime->tz_offset;
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
					'action_confirm_button'	=> "onClick=\"return confirm('".lang("Are you sure\\nyou want to \\ndelete this entry ?\\n\\nThis will delete\\nthis entry for all users.")."')\"",
					'action_extra_field'	=> ''
				);
				$p->set_var($var);
				$extra_buttons = $p->fp('out','form_button');
			}
			if ($this->bo->return_to)
			{
				$var = Array(
					'action_url_button'	=> $GLOBALS['phpgw']->link('/index.php','menuaction='.$this->bo->return_to),
					'action_text_button'	=> lang('Cancel'),
					'action_confirm_button'	=> '',
					'action_extra_field'	=> ''
				);
				$p->set_var($var);
				$extra_buttons .= ($event['id']>0?'<br>':'') . $p->fp('out','form_button');
			}
			$p->set_var('extra_buttons',$extra_buttons);
			
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
			for ($j=0,$datetime=$startdate;$j<7;$j++,$datetime += 86400)
			{
				$date = date('Ymd',$datetime + (60 * 60 * 2));

				print_debug('set_week_array:Date',$date);

				if($this->bo->cached_events[$date])
				{
					print_debug('Date',$date);
					print_debug('Appointments Found',count($this->bo->cached_events[$date]));
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
					$extra = ' class="cal_holiday"';
					$class = ($appts?'b':'').'minicalhol';
					if ($date == $this->bo->today)
					{
						$day_image = ' background="'.$GLOBALS['phpgw']->common->image('calendar','mini_day_block').'"';
					}
				}
				elseif ($date != $this->bo->today)
				{
					$extra = ' class="'.$cellcolor.'"';
					$class = ($appts?'b':'').'minicalendar';
				}
				else
				{
					$extra = ' class="cal_today"';
					$class = ($appts?'b':'').'minicalendar';
					$day_image = ' background="'.$GLOBALS['phpgw']->common->image('calendar','mini_day_block').'"';
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
				if (!$j || (!$weekly && $j && substr($date,6,2) == '01'))
				{
					$week = lang('week').' '.(int)((date('z',($startdate+(24*3600*4)))+7)/7);
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
				_debug_array($daily);
			}
			
			return $daily;
		}
	}
?>
