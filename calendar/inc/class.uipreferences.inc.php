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

	class uipreferences
	{
//		var $template_dir;
		var $template;

		var $bo;
		
		var $debug = False;
//		var $debug = True;

		var $theme;

		var $public_functions = array(
			'preferences' => True
		);

		function uipreferences()
		{
			global $GLOBALS;
			
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$this->template = $GLOBALS['phpgw']->template;
			$this->theme = $GLOBALS['phpgw_info']['theme'];
			$this->bo = CreateObject('calendar.bopreferences');
		}

		function preferences()
		{
			global $GLOBALS;

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			$this->template->set_file(
			   Array(
   			   'pref'      =>'pref.tpl',
	   		   'pref_colspan' =>'pref_colspan.tpl',
		   	   'pref_list' =>'pref_list.tpl'
			   )
			);

			$var = Array(
				'title'	   	=>	lang('Calendar preferences'),
				'action_url'	=>	$GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.bopreferences.preferences')),
				'bg_color   '	=>	$this->theme['th_bg'],
				'submit_lang'	=>	lang('submit'),
				'text'   		=> '&nbsp;'
			);
	
			$this->output_template_array('row','pref_colspan',$var);

			$this->display_item(lang('show day view on main screen'),'<input type="checkbox" name="prefs[mainscreen_showevents]" value="True"'.(@$this->bo->prefs['calendar']['mainscreen_showevents']?' checked':'').'>');

			$days = Array(
			   'Monday',
			   'Sunday',
			   'Saturday'
			);
			$str = '';
			for($i=0;$i<count($days);$i++)
			{
			   $str .= '<option value="'.$days[$i].'"'.($this->bo->prefs['calendar']['weekdaystarts']==$days[$i]?' selected':'').'>'.lang($days[$i]).'</option>'."\n";
			}
			$this->display_item(lang('weekday starts on'),'<select name="prefs[weekdaystarts]">'."\n".$str.'</select>'."\n");

			$str = '';
			for ($i=0; $i<24; $i++)
			{
				$str .= '<option value="'.$i.'"'.($this->bo->prefs['calendar']['workdaystarts']==$i?' selected':'').'>'.$GLOBALS['phpgw']->common->formattime($i,'00').'</option>'."\n";
			}
			$this->display_item(lang('work day starts on'),'<select name="prefs[workdaystarts]">'."\n".$str.'</select>'."\n");
  
			$str = '';
			for ($i=0; $i<24; $i++)
			{
				$str .= '<option value="'.$i.'"'.($this->bo->prefs['calendar']['workdayends']==$i?' selected':'').'>'.$GLOBALS['phpgw']->common->formattime($i,'00').'</option>';
			}
			$this->display_item(lang('work day ends on'),'<select name="prefs[workdayends]">'."\n".$str.'</select>'."\n");

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
			$this->display_item(lang('default calendar view'),$str);

			$selected = array();
			$selected[$this->bo->prefs['calendar']['defaultfilter']] = ' selected';
			if (!isset($this->bo->prefs['calendar']['defaultfilter']) || $this->bo->prefs['calendar']['defaultfilter'] == 'private')
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
			$this->display_item(lang('Default calendar filter'),$str);

			$var = Array(
				5	=> '5',
				10	=> '10',
				15	=> '15',
				20	=> '20',
				30	=> '30',
				45	=> '45',
				60	=> '60'
			);
	
			$str = '';
			while(list($key,$value) = each($var))
			{
				$str .= '<option value="'.$key.'"'.(intval($this->bo->prefs['calendar']['interval'])==$key?' selected':'').'>'.$value.'</option>'."\n";
			}
			$this->display_item(lang('Display interval in Day View'),'<select name="prefs[interval]">'."\n".$str.'</select>'."\n");

			$this->display_item(lang('Send/receive updates via email'),'<input type="checkbox" name="prefs[send_updates]" value="True"'.(@$this->bo->prefs['calendar']['send_updates']?' checked':'').'>'."\n");

			$this->display_item(lang('Display status of events'),'<input type="checkbox" name="prefs[display_status]" value="True"'.(@$this->bo->prefs['calendar']['display_status']?' checked':'').'>'."\n");

			$this->display_item(lang('When creating new events default set to private'),'<input type="checkbox" name="prefs[default_private]" value="True"'.(@$this->bo->prefs['calendar']['default_private']?' checked':'').'>'."\n");

			$this->display_item(lang('Display mini calendars when printing'),'<input type="checkbox" name="prefs[display_minicals]" value="True"'.(@$this->bo->prefs['calendar']['display_minicals']?' checked':'').'>'."\n");

			$this->display_item(lang('Print calendars in black & white'),'<input type="checkbox" name="prefs[print_black_white]" value="True"'.(@$this->bo->prefs['calendar']['print_black_white']?' checked':'').'>'."\n");

			$this->template->pparse('out','pref');
		}

		function output_template_array($row,$list,$var)
		{
			$this->template->set_var($var);
			$this->template->parse($row,$list,True);
		}

		function display_item($field,$data)
		{
			global $GLOBALS;
			static $tr_color;
			$tr_color = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($tr_color);
			$var = Array(
				'bg_color'	=>	$tr_color,
				'field'		=>	$field,
				'data'		=>	$data
			);
			$this->output_template_array('row','pref_list',$var);
		}
	}
