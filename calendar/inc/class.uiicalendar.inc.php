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

	class uiicalendar
	{
		var $bo;
		var $template;

		var $public_functions = array(
			'test'		=> True,
			'import'		=> True
		);



		function uiicalendar()
		{
			$this->bo = CreateObject('calendar.boicalendar');
			$this->template = $GLOBALS['phpgw']->template;
		}


		function print_test($val,$title,$x_pre='')
		{
//			echo 'VAL = '._debug_array($val,False)."<br>\n";
			if(is_array($val))
			{
				@reset($val);
				while(list($key,$value) = each($val))
				{
					if(is_array($key))
					{
						$this->print_test($key,$title,$x_pre);
					}
					elseif(is_array($value))
					{
						$this->print_test($value,$title,$x_pre);
					}
					else
					{
						if($x_pre && $key == 'name')
						{
							$x_key = $x_pre.$value;
							list($key,$value) = each($val);
							$key=$x_key;
						}
						if($this->bo->parameter[$key]['type'] == 'function')
						{
							$function = $this->bo->parameter[$key]['function'];
							$v_value = $this->bo->$function($value);
						}
						else
						{
							$v_value = $value;
						}
						echo $title.' ('.$key.') = '.$v_value."<br>\n";
					}
				}
			}
			elseif($val != '')
			{
				echo $title.' = '.$val."<br>\n";
			}
		}

		function test()
		{
			$print_events = True;
			
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw']->common->phpgw_header();

			echo "Start Time : ".$GLOBALS['phpgw']->common->show_date()."<br>\n";
			@set_time_limit(0);

			$icsfile=PHPGW_APP_INC.'/events.vcs';
			$fp=fopen($icsfile,'rt');
			$contents = explode("\n",fread($fp, filesize($icsfile)));
			fclose($fp);

			$vcalendar = $this->bo->parse($contents);

			if($print_events)
			{
				$this->print_test($vcalendar['prodid'],'Product ID');
				$this->print_test($vcalendar['method'],'Method');
				$this->print_test($vcalendar['version'],'Version');

				for($i=0;$i<count($vcalendar['event']);$i++)
				{
					$event = $vcalendar['event'][$i];

					echo "<br>\nEVENT<br>\n";
//					echo 'TEST Debug : '._debug_array($event,False)."<br>\n";
					$this->print_test($event['uid'],'UID','X-');
					$this->print_test($event['valscale'],'Calscale','X-');
					$this->print_test($event['description'],'Description','X-');
					$this->print_test($event['summary'],'Summary','X-');
					$this->print_test($event['comment'],'Comment','X-');
					$this->print_test($event['location'],'Location','X-');
					$this->print_test($event['sequence'],'Sequence','X-');
					$this->print_test($event['priority'],'Priority','X-');
					$this->print_test($event['categories'],'Categories','X-');
					$this->print_test($event['dtstart'],'Date Start','X-');
					$this->print_test($event['dtstamp'],'Date Stamp','X-');
					$this->print_test($event['rrule'],'Recurrence','X-');

					echo "Class = ".$this->bo->switch_class($event['class'])."<br>\n";

					$this->print_test($event['organizer'],'Organizer','X-');
					$this->print_test($event['attendee'],'Attendee','X-');
					$this->print_test($event['x_type'],'X-Type','X-');
					$this->print_test($event['alarm'],'Alarm','X-');
				}
			}

/*
			for($i=0;$i<count($vcalendar->todo);$i++)
			{
				echo "<br>\nTODO<br>\n";
				if($vcalendar['todo'][$i]['summary']['value'])
				{
					echo "Summary = ".$vcalendar['todo'][$i]['summary']['value']."<br>\n";
				}
				if($vcalendar['todo'][$i]['description']['value'])
				{
					echo "Description (Value) = ".$vcalendar['todo'][$i]['description']['value']."<br>\n";
				}
				if($vcalendar['todo'][$i]['description']['altrep'])
				{
					echo "Description (Alt Rep) = ".$vcalendar['todo'][$i]['description']['altrep']."<br>\n";
				}
				if($vcalendar['todo'][$i]['location']['value'])
				{
					echo "Location = ".$vcalendar['todo'][$i]['location']['value']."<br>\n";
				}
				echo "Sequence = ".$vcalendar['todo'][$i]['sequence']."<br>\n";	
				echo "Date Start : ".$GLOBALS['phpgw']->common->show_date(mktime($vcalendar['todo'][$i]['dtstart']['hour'],$vcalendar['todo'][$i]['dtstart']['min'],$vcalendar['todo'][$i]['dtstart']['sec'],$vcalendar['todo'][$i]['dtstart']['month'],$vcalendar['todo'][$i]['dtstart']['mday'],$vcalendar['todo'][$i]['dtstart']['year']) - $this->datatime->tz_offset)."<br>\n";
				echo "Class = ".$vcalendar['todo'][$i]['class']['value']."<br>\n";
			}

*/
			include(PHPGW_APP_INC.'/../setup/setup.inc.php');

			$this->bo->set_var($vcalendar['prodid'],'value','-//phpGroupWare//phpGroupWare '.$setup_info['calendar']['version'].' MIMEDIR//'.strtoupper($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']));
			$this->bo->set_var($vcalendar['version'],'value','2.0');
			$this->bo->set_var($vcalendar['method'],'value',strtoupper('publish'));
			echo "<br><br><br>\n";
			echo nl2br($this->bo->build_ical($vcalendar));
			echo "End Time : ".$GLOBALS['phpgw']->common->show_date()."<br>\n";
		}

		function import()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			$GLOBALS['phpgw_info']['flags']['nonappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['nonappfooter'] = True;
			$GLOBALS['phpgw']->common->phpgw_header();

			if(!@is_dir($GLOBALS['phpgw_info']['server']['temp_dir']))
			{
				mkdir($GLOBALS['phpgw_info']['server']['temp_dir'],0700);
			}

			echo '<body bgcolor="' . $GLOBALS['phpgw_info']['theme']['bg_color'] . '">';

			if ($GLOBALS['HTTP_GET_VARS']['action'] == 'GetFile')
			{
				echo '<b><center>' . lang('You must select a [iv]Cal. (*.[iv]cs)') . '</b></center><br><br>';
			}

 			$this->template->set_file(
 				Array(
 					'vcalimport' => 'vcal_import.tpl'
 				)
 			);

			$var = Array(
				'vcal_header'	=> '<p>&nbsp;<b>' . lang('Calendar - [iv]Cal Importer') . '</b><hr><p>',
				'ical_lang'		=> lang('(i/v)Cal'),
				'action_url'	=> $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.boicalendar.import'),
				'lang_access'	=> lang('Access'),
				'lang_groups'	=> lang('Which groups'),
				'access_option'=> $access_option,
				'group_option'	=> $group_option,
				'load_vcal'	=> lang('Load [iv]Cal')
			);
			$this->template->set_var($var);
			$this->template->pparse('out','vcalimport');
		}
	}
?>
