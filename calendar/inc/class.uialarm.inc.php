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

	class uialarm
	{
		var $template;
		var $template_dir;

		var $bo;
		var $event;

		var $debug = False;
//		var $debug = True;

		var $tz_offset;
		var $theme;

		var $public_functions = array(
			'manager' => True
		);

		function uialarm()
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$GLOBALS['phpgw']->browser    = CreateObject('phpgwapi.browser');
			
			$this->theme = $GLOBALS['phpgw_info']['theme'];

			$this->bo = CreateObject('calendar.boalarm');
			$this->tz_offset = $this->bo->tz_offset;

			if($this->debug)
			{
				echo "BO Owner : ".$this->bo->owner."<br>\n";
			}

			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir('calendar');
		}

		function prep_page()
		{
			$this->event = $this->bo->read_entry($this->bo->cal_id);

			$can_edit = $this->bo->cal->check_perms(PHPGW_ACL_EDIT,$this->event);

			if(!$can_edit)
			{
				Header('Location : '.$GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> 'calendar.uicalendar.view',
							'cal_id'		=> $this->bo->cal_id
						)
					)
				);
			}

  			unset($GLOBALS['phpgw_info']['flags']['noheader']);
   		unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
	   	$GLOBALS['phpgw']->common->phpgw_header();

			$this->template = CreateObject('phpgwapi.Template',$this->template_dir);

			$this->template->set_unknowns('keep');
			$this->template->set_file(
				Array(
  					'alarm'	=> 'alarm.tpl'
   				)
			);
			$this->template->set_block('alarm','alarm_management','alarm_management');
			$this->template->set_block('alarm','alarm_headers','alarm_headers');
			$this->template->set_block('alarm','list','list');
			$this->template->set_block('alarm','hr','hr');
		}

		function output_template_array($row,$list,$var)
		{
			$this->template->set_var($var);
			$this->template->parse($row,$list,True);
		}

		/* Public functions */

		function manager()
		{
			$this->prep_page();
			echo ExecMethod('calendar.uicalendar.view_event',$this->event);

			$this->template->set_var('hr_text','<center><b>'.lang('Alarms').'</b></center></br><hr>');
			$this->template->parse('row','hr',True);
			$var = Array(
				'action_url'	=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.uialarm.form_handler')),
				'time_lang'		=> lang('Time'),
				'text_lang'		=> lang('Text'),
				'enabled_pict'		=> $GLOBALS['phpgw']->common->image('calendar','enabled.gif'),
				'disabled_pict'	=> $GLOBALS['phpgw']->common->image('calendar','disabled.gif')
			);
			
			$this->output_template_array('row','alarm_headers',$var);
			$this->template->set_var('hr_text','<hr>');
			$this->template->parse('row','hr',True);

			if($this->event['alarm'])
			{
				@reset($this->event['alarm']);
				while(list($key,$alarm) = each($this->event['alarm']))
				{
					$var = Array(
						'edit_box'	=> '<input type="checkbox" name="alarm[id]" value="'.$alarm['id'].'">',
						'field'	=> $icon.$GLOBALS['phpgw']->common->show_date($alarm['time']),
						'data'	=> $alarm['text'],
						'alarm_enabled'	=> ($alarm['enabled']?'<img src="'.$GLOBALS['phpgw']->common->image('calendar','enabled.gif').'" width="13" height="13" alt="enabled">':'&nbsp;'),
						'alarm_disabled'	=> (!$alarm['enabled']?'<img src="'.$GLOBALS['phpgw']->common->image('calendar','disabled.gif').'" width="13" height="13" alt="disabled">':'&nbsp;')
					);
					$this->output_template_array('row','list',$var);
				}
			}
			$this->template->set_var('hr_text','<hr>');
			$this->template->parse('row','hr',True);

			echo $this->template->fp('out','alarm_management');
		}

		function add_alarm()
		{
			$this->prep_page();
		}
	}
