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

			if($this->debug)
			{
				echo "BO Owner : ".$this->bo->owner."<br>\n";
			}
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir('calendar');

			$this->html = CreateObject('calendar.html');
		}

		function prep_page()
		{
			if ($this->bo->cal_id <= 0 ||
			    !$this->event = $this->bo->read_entry($this->bo->cal_id))
			{
				$GLOBALS['phpgw']->redirect_link('/index.php',Array(
					'menuaction'	=> 'calendar.uicalendar.index'
				));
			}

			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['app_header'] = $GLOBALS['phpgw_info']['apps']['calendar']['title'].' - '.lang('Alarm Management');
			$GLOBALS['phpgw']->common->phpgw_header();

			$this->template = CreateObject('phpgwapi.Template',$this->template_dir);

			$this->template->set_unknowns('remove');
			$this->template->set_file(
				Array(
					'alarm'	=> 'alarm.tpl'
				)
			);
			$this->template->set_block('alarm','alarm_management','alarm_management');
			$this->template->set_block('alarm','alarm_headers','alarm_headers');
			$this->template->set_block('alarm','list','list');
			$this->template->set_block('alarm','hr','hr');
			$this->template->set_block('alarm','buttons','buttons');
		}

		function output_template_array($row,$list,$var)
		{
			if (!isset($var['tr_color']))
			{
				$var['tr_color'] = $GLOBALS['phpgw']->nextmatchs->alternate_row_color();
			}
			$this->template->set_var($var);
			$this->template->parse($row,$list,True);
		}

		/* Public functions */

		function manager()
		{
			if ($_POST['delete'] && count($_POST['alarm']))
			{
				if ($this->bo->delete($_POST['alarm']) < 0)
				{
					echo '<center>'.lang('You do not have permission to delete this alarm !!!').'</center>';
					$GLOBALS['phpgw']->common->phpgw_exit(True);
				}
			}
			if (($_POST['enable'] || $_POST['disable']) && count($_POST['alarm']))
			{
				if ($this->bo->enable($_POST['alarm'],$_POST['enable']) < 0)
				{
					echo '<center>'.lang('You do not have permission to enable/disable this alarm !!!').'</center>';
					$GLOBALS['phpgw']->common->phpgw_exit(True);
				}
			}
			$this->prep_page();

			if ($_POST['add'])
			{
				$time = intval($_POST['time']['days'])*24*3600 +
					intval($_POST['time']['hours'])*3600 +
					intval($_POST['time']['mins'])*60;

				if ($time > 0 && !$this->bo->add($this->event,$time,$_POST['owner']))
				{
					echo '<center>'.lang('You do not have permission to add alarms to this event !!!').'</center>';
					$GLOBALS['phpgw']->common->phpgw_exit(True);
				}
			}
			if (!ExecMethod('calendar.uicalendar.view_event',$this->event))
			{
				echo '<center>'.lang('You do not have permission to read this record!').'</center>';
				$GLOBALS['phpgw']->common->phpgw_exit(True);
			}
			echo "<br>\n";
			$GLOBALS['phpgw']->template->set_var('th_bg',$this->theme['th_bg']);
			$GLOBALS['phpgw']->template->set_var('hr_text',lang('Alarms').':');
			$GLOBALS['phpgw']->template->fp('row','hr',True);
			$GLOBALS['phpgw']->template->pfp('phpgw_body','view_event');

			$var = Array(
				'tr_color'		=> $this->theme['th_bg'],
				'lang_select'	=> lang('Select'),
				'lang_time'		=> lang('Time'),
				'lang_text'		=> lang('Text'),
				'lang_owner'	=> lang('Owner'),
				'lang_enabled'	=> lang('enabled'),
				'lang_disabled'	=> lang('disabled'),
			);
			if($this->event['alarm'])
			{
				$this->output_template_array('rows','alarm_headers',$var);

				foreach($this->event['alarm'] as $key => $alarm)
				{
					if (!$this->bo->check_perms(PHPGW_ACL_READALARM,$alarm['owner']))
					{
						continue;
					}
					$var = Array(
						'field'    => $GLOBALS['phpgw']->common->show_date($alarm['time']-$this->bo->tz_offset),
						//'data'   => $alarm['text'],
						'data'     => lang('Email Notification'),
						'owner'    => $GLOBALS['phpgw']->common->grab_owner_name($alarm['owner']),
						'enabled'  => ($alarm['enabled']?'<img src="'.$GLOBALS['phpgw']->common->image('calendar','enabled.gif').'" width="13" height="13" title="'.lang('enabled').'">':
							'<img src="'.$GLOBALS['phpgw']->common->image('calendar','disabled.gif').'" width="13" height="13" title="'.lang('disabled').'">'),
						'select'   => '<input type="checkbox" name="alarm['.$alarm['id'].']">'
					);
					if ($this->bo->check_perms(PHPGW_ACL_DELETEALARM,$alarm['owner']))
					{
						++$to_delete;
					}
					$this->output_template_array('rows','list',$var);
				}
				$this->template->set_var('enable_button',$this->html->submit_button('enable','Enable'));
				$this->template->set_var('disable_button',$this->html->submit_button('disable','Disable'));
				if ($to_delete)
				{
					$this->template->set_var('delete_button',$this->html->submit_button('delete','Delete',"return confirm('".lang("Are you sure\\nyou want to\\ndelete these alarms?")."')"));
				}
				$this->template->parse('rows','buttons',True);
			}
			if (isset($this->event['participants'][intval($GLOBALS['phpgw_info']['user']['account_id'])]))
			{
				$this->template->set_var(Array(
					'input_days'    => $this->html->select('time[days]',$_POST['time']['days'],range(0,31),True).' '.lang('days'),
					'input_hours'   => $this->html->select('time[hours]',$_POST['time']['hours'],range(0,24),True).' '.lang('hours'),
					'input_minutes' => $this->html->select('time[mins]',$_POST['time']['mins'],range(0,60),True).' '.lang('minutes').' '.lang('before the event'),
					'input_owner'   => $this->html->select('owner',$GLOBALS['phpgw_info']['user']['account_id'],$this->bo->participants($this->event,True),True),
					'input_add'     => $this->html->submit_button('add','Add Alarm'),
				));
			}
			$this->template->set_var(Array(
				'action_url'	=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.uialarm.manager')),
				'hidden_vars'	=> $this->html->input_hidden('cal_id',$this->bo->cal_id),
				'lang_enable'	=> lang('Enable'),
				'lang_disable'	=> lang('Disable')
			));
//echo "<p>alarm_management='".htmlspecialchars($this->template->get_var('alarm_management'))."'</p>\n";
			$this->template->pfp('out','alarm_management');
		}
	}
?>
