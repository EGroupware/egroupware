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

	class bocalendar
	{
		var $public_functions = Array(
			'read_entry'	=> True,
			'delete_entry' => True,
			'delete_calendar'	=> True,
			'update'       => True,
			'preferences'  => True,
			'store_to_cache'	=> True
		);

		var $soap_functions = Array(
			'read_entry' => Array(
				'in' => Array(
					'int'
				),
				'out' => Array(
					'SOAPStruct'
				)
			),
			'delete_entry' => Array(
				'in' => Array(
					'int'
				),
				'out' => Array(
					'int'
				)
			),
			'delete_calendar' => Array(
				'in' => Array(
					'int'
				),
				'out' => Array(
					'int'
				)
			),
			'update' => Array(
				'in' => Array(
					'array',
					'array',
					'array',
					'array',
					'array'
				),
				'out' => Array(
					'array'
				)
			),
			'store_to_cache'	=> Array(
				'in' => Array(
					'struct'
				),
				'out' => Array(
					'SOAPStruct'
				)
			)
		);

		var $debug = False;
//		var $debug = True;

		var $so;
		var $cached_events;
		var $repeating_events;
		var $datetime;
		var $day;
		var $month;
		var $year;
		var $prefs;

		var $owner;
		var $holiday_color;
		var $printer_friendly = False;

		var $cached_holidays;
		
		var $filter;
		var $cat_id;
		var $users_timeformat;
		
		var $modified;
		var $deleted;
		var $added;

		var $soap = False;
		
		var $use_session = False;

		var $today;

		function bocalendar($session=0)
		{
			$GLOBALS['phpgw']->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$this->grants = $GLOBALS['phpgw']->acl->get_grants('calendar');

			if($this->debug) { echo "Read Use_Session : (".$session.")<br>\n"; }

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}

			if($this->debug)
			{
				echo "BO Filter : (".$this->filter.")<br>\n";
				echo "Owner : ".$this->owner."<br>\n";
			}
			
			if(isset($GLOBALS['owner']))
			{
				$this->owner = intval($GLOBALS['owner']);
			}
			elseif(!isset($this->owner) || !$this->owner)
			{
				$this->owner = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$this->prefs['common']    = $GLOBALS['phpgw_info']['user']['preferences']['common'];
			$this->prefs['calendar']    = $GLOBALS['phpgw_info']['user']['preferences']['calendar'];

			if ($this->prefs['common']['timeformat'] == '12')
			{
				$this->users_timeformat = 'h:i a';
			}
			else
			{
				$this->users_timeformat = 'H:i';
			}

			$this->holiday_color = (substr($GLOBALS['phpgw_info']['theme']['bg07'],0,1)=='#'?'':'#').$GLOBALS['phpgw_info']['theme']['bg07'];

			$this->printer_friendly = ($GLOBALS['friendly'] == 1?True:False);

			if(isset($GLOBALS['filter']))   { $this->filter = $GLOBALS['filter']; }
			if(isset($GLOBALS['cat_id']))  { $this->cat_id = $GLOBALS['cat_id']; }

			if(!isset($this->filter))
			{
				$this->filter = ' '.$this->prefs['calendar']['defaultfilter'].' ';
			}

			if(isset($GLOBALS['date']))
			{
				$this->year = intval(substr($GLOBALS['date'],0,4));
				$this->month = intval(substr($GLOBALS['date'],4,2));
				$this->day = intval(substr($GLOBALS['date'],6,2));
			}
			else
			{
				if(isset($GLOBALS['year']))
				{
					$this->year = $GLOBALS['year'];
				}
				elseif($this->year == 0)
				{
					$this->year = date('Y',time());
				}
				if(isset($GLOBALS['month']))
				{
					$this->month = $GLOBALS['month'];
				}
				elseif($this->month == 0)
				{
					$this->month = date('m',time());
				}
				if(isset($GLOBALS['day']))
				{
					$this->day = $GLOBALS['day'];
				}
				elseif($this->day == 0)
				{
					$this->day = date('d',time());
				}
			}
			
			$this->so = CreateObject('calendar.socalendar',
				Array(
					'owner'		=> $this->owner,
					'filter'		=> $this->filter,
					'category'	=> $this->cat_id
				)
			);
			$this->datetime = $this->so->datetime;
			
			$this->today = date('Ymd',time());

			if($this->debug)
			{
				echo "BO Filter : (".$this->filter.")<br>\n";
				echo "Owner : ".$this->owner."<br>\n";
			}
		}

		function save_sessiondata($data)
		{
			if ($this->use_session)
			{
				if($this->debug) { echo '<br>Save:'; _debug_array($data); }
				$GLOBALS['phpgw']->session->appsession('session_data','calendar',$data);
			}
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','calendar');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
			$this->owner  = intval($data['owner']);
			$this->year   = intval($data['year']);
			$this->month  = intval($data['month']);
			$this->day    = intval($data['day']);
		}

		function read_entry($id)
		{
			if($this->check_perms(PHPGW_ACL_READ))
			{
				$event = $this->so->read_entry($id);
				return $event;
			}
		}

		function delete_entry($id)
		{
			if($this->check_perms(PHPGW_ACL_DELETE))
			{
			   $temp_event = $this->read_entry($id);
			   if($this->owner == $temp_event['owner'])
			   {
   				$this->so->delete_entry($id);
   				$cd = 16;
   			}
   			else
   			{
   			   $cd = 60;
   			}
			}
			return $cd;
		}

		function delete_calendar($owner)
		{
			if($GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				$this->so->delete_calendar($owner);
			}
		}

		function change_owner($account_id,$new_owner)
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] == 'sql')
			{
				$this->so->change_owner($account_id,$new_owner);
			}
		}

		function expunge()
		{
			if($this->check_perms(PHPGW_ACL_DELETE))
			{
				reset($this->so->cal->deleted_events);
				for($i=0;$i<count($this->so->cal->deleted_events);$i++)
				{
					$event_id = $this->so->cal->deleted_events[$i];
					$event = $this->so->read_entry($event_id);
					$this->send_update(MSG_DELETED,$event['participants'],$event);
				}
				$this->so->expunge();
			}
		}

		function search_keywords($keywords)
		{
			return $this->so->list_events_keyword($keywords);
		}

		function update($p_cal=0,$p_participants=0,$p_start=0,$p_end=0,$p_recur_enddata=0)
		{
			global $HTTP_POST_VARS, $HTTP_GET_VARS;
			
			$l_cal = ($p_cal?$p_cal:$HTTP_POST_VARS['cal']);
			$l_participants = ($p_participants?$p_participants:$HTTP_POST_VARS['participants']);
			$l_start = ($p_start?$p_start:$HTTP_POST_VARS['start']);
			$l_end = ($p_end?$p_end:$HTTP_POST_VARS['end']);
			$l_recur_enddate = ($p_recur_enddate?$p_recur_enddate:$HTTP_POST_VARS['recur_enddate']);

			$send_to_ui = True;
			if($p_cal || $p_participants || $p_start || $p_end || $p_recur_enddata)
			{
				$send_to_ui = False;
			}

			if($this->debug)
			{
				echo "ID : ".$l_cal['id']."<br>\n";
			}

  			$ui = CreateObject('calendar.uicalendar');

         if(isset($HTTP_GET_VARS['readsess']))
         {
				$event = $this->restore_from_appsession();
				$datetime_check = $this->validate_update($event);
				if($datetime_check)
				{
					$ui->edit(
						Array(
							'cd'		=> $datetime_check,
							'readsess'	=> 1
						)
					);
				}
				$overlapping_events = False;
         }
         else
			{
   			if(!$l_cal['id'] && !$this->check_perms(PHPGW_ACL_ADD))
	   		{
	   		   $ui->index();
	   		}
	   		elseif($l_cal['id'] && !$this->check_perms(PHPGW_ACL_EDIT))
	   		{
	   		   $ui->index();
	   		}

				$this->fix_update_time($l_start);
				$this->fix_update_time($l_end);

				if(!isset($l_cal['private']))
				{
					$l_cal['private'] = 'public';
				}

				$is_public = ($l_cal['private'] == 'public'?1:0);
				$this->so->event_init();
				$this->so->set_category($l_cal['category']);
				$this->so->set_title($l_cal['title']);
				$this->so->set_description($l_cal['description']);
				$this->so->set_start($l_start['year'],$l_start['month'],$l_start['mday'],$l_start['hour'],$l_start['min'],0);
				$this->so->set_end($l_end['year'],$l_end['month'],$l_end['mday'],$l_end['hour'],$l_end['min'],0);
				$this->so->set_class($is_public);
				if($l_cal['id'])
				{
					$this->so->add_attribute('id',$l_cal['id']);
				}

				if($l_cal['rpt_use_end'] != 'y')
				{
					$l_recur_enddate['year'] = 0;
					$l_recur_enddate['month'] = 0;
					$l_recur_enddate['mday'] = 0;
				}
		
				switch(intval($l_cal['recur_type']))
				{
					case MCAL_RECUR_NONE:
						$this->so->set_recur_none();
						break;
					case MCAL_RECUR_DAILY:
						$this->so->set_recur_daily(intval($l_recur_enddate['year']),intval($l_recur_enddate['month']),intval($l_recur_enddate['mday']),intval($l_cal['recur_interval']));
						break;
					case MCAL_RECUR_WEEKLY:
						$cal['recur_data'] = intval($l_cal['rpt_sun']) + intval($l_cal['rpt_mon']) + intval($l_cal['rpt_tue']) + intval($l_cal['rpt_wed']) + intval($l_cal['rpt_thu']) + intval($l_cal['rpt_fri']) + intval($l_cal['rpt_sat']);
						$this->so->set_recur_weekly(intval($l_recur_enddate['year']),intval($l_recur_enddate['month']),intval($l_recur_enddate['mday']),intval($l_cal['recur_interval']),$l_cal['recur_data']);
						break;
					case MCAL_RECUR_MONTHLY_MDAY:
						$this->so->set_recur_monthly_mday(intval($l_recur_enddate['year']),intval($l_recur_enddate['month']),intval($l_recur_enddate['mday']),intval($l_cal['recur_interval']));
						break;
					case MCAL_RECUR_MONTHLY_WDAY:
						$this->so->set_recur_monthly_wday(intval($l_recur_enddate['year']),intval($l_recur_enddate['month']),intval($l_recur_enddate['mday']),intval($l_cal['recur_interval']));
						break;
					case MCAL_RECUR_YEARLY:
						$this->so->set_recur_yearly(intval($l_recur_enddate['year']),intval($l_recur_enddate['month']),intval($l_recur_enddate['mday']),intval($l_cal['recur_interval']));
						break;
				}

				if($l_participants)
				{
					$parts = $l_participants;
					$minparts = min($l_participants);
					$part = Array();
					for($i=0;$i<count($parts);$i++)
					{
						$acct_type = $GLOBALS['phpgw']->accounts->get_type(intval($parts[$i]));
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
				}
				else
				{
					$part = False;
				}

				if($part)
				{
					@reset($part);
					while(list($key,$value) = each($part))
					{
						$this->so->add_attribute('participants','U',intval($key));
					}
				}

				$event = $this->get_cached_event();
				if(!@$event['participants'][$l_cal['owner']])
				{
					$this->so->add_attribute('owner',$minparts);
				}
				else
				{
					$this->so->add_attribute('owner',$l_cal['owner']);
				}
				$this->so->add_attribute('priority',$l_cal['priority']);
				$event = $this->get_cached_event();

				$this->store_to_appsession($event);
				$datetime_check = $this->validate_update($event);
				if($datetime_check)
				{
				   $ui->edit(
				   	Array(
				   		'cd'		=> $datetime_check,
				   		'readsess'	=> 1
				   	)
				   );
				}

				$overlapping_events = $this->overlap(
												$this->maketime($event['start']) - $this->datetime->tz_offset,
												$this->maketime($event['end']) - $this->datetime->tz_offset,
												$event['participants'],
												$event['owner'],
												$event['id']
				);
			}

			if($overlapping_events)
			{
            if($send_to_ui)
            {
					unset($GLOBALS['phpgw_info']['flags']['noheader']);
					unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				   $ui->overlap($overlapping_events,$event);
					$GLOBALS['phpgw_info']['flags']['nofooter'] = True;
				   return;
				}
				else
				{
					return $overlapping_events;
				}
			}
			else
			{
				if(!$event['id'])
				{
   				$this->so->add_entry($event);
	   			$this->send_update(MSG_ADDED,$event['participants'],'',$this->get_cached_event());
				}
				else
				{
					$new_event = $event;
					$old_event = $this->read_entry($event['id']);
					$this->prepare_recipients($new_event,$old_event);
					$this->so->cal->event = $event;
   				$this->so->add_entry($event);
				}
            $date = sprintf("%04d%02d%02d",$event['start']['year'],$event['start']['month'],$event['start']['mday']);
            if($send_to_ui)
            {
				   $ui->index();
				}
			}
		}

		/* Private functions */
		function read_holidays()
		{
			$holiday = CreateObject('calendar.boholiday');
			$holiday->prepare_read_holidays($this->year,$this->owner);
			$this->cached_holidays = $holiday->read_holiday();
			unset($holiday);
		}

		function maketime($time)
		{
			return mktime($time['hour'],$time['min'],$time['sec'],$time['month'],$time['mday'],$time['year']);
		}

		function can_user_edit($event)
		{
			$can_edit = False;
		
			if(($event['owner'] == $this->owner) && ($this->check_perms(PHPGW_ACL_EDIT) == True))
			{
				if($event['public'] != True)
				{
					if($this->check_perms(PHPGW_ACL_PRIVATE) == True)
					{
						$can_edit = True;
					}
				}
				else
				{
					$can_edit = True;
				}
			}
			return $can_edit;
		}

		function fix_update_time(&$time_param)
		{
			if ($this->prefs['common']['timeformat'] == '12')
			{
				if ($time_param['ampm'] == 'pm')
				{
					if ($time_param['hour'] <> 12)
					{
						$time_param['hour'] += 12;
					}
				}
				elseif ($time_param['ampm'] == 'am')
				{
					if ($time_param['hour'] == 12)
					{
						$time_param['hour'] -= 12;
					}
				}
		
				if($time_param['hour'] > 24)
				{
					$time_param['hour'] -= 12;
				}
			}
		}

		function validate_update($event)
		{
			$error = 0;
			// do a little form verifying
			if (!$event['participants'])
			{
				$error = 43;
			}
			elseif ($event['title'] == '')
			{
				$error = 40;
			}
			elseif (($this->datetime->time_valid($event['start']['hour'],$event['start']['min'],0) == False) || ($this->datetime->time_valid($event['end']['hour'],$event['end']['min'],0) == False))
			{
				$error = 41;
			}
			elseif (($this->datetime->date_valid($event['start']['year'],$event['start']['month'],$event['start']['mday']) == False) || ($this->datetime->date_valid($event['end']['year'],$event['end']['month'],$event['end']['mday']) == False) || ($this->datetime->date_compare($event['start']['year'],$event['start']['month'],$event['start']['mday'],$event['end']['year'],$event['end']['month'],$event['end']['mday']) == 1))
			{
				$error = 42;
			}
			elseif ($this->datetime->date_compare($event['start']['year'],$event['start']['month'],$event['start']['mday'],$event['end']['year'],$event['end']['month'],$event['end']['mday']) == 0)
			{
				if ($this->datetime->time_compare($event['start']['hour'],$event['start']['min'],0,$event['end']['hour'],$event['end']['min'],0) == 1)
				{
					$error = 42;
				}
			}
			return $error;
		}

		function overlap($starttime,$endtime,$participants,$owner=0,$id=0)
		{
			$retval = Array();
			$ok = False;

			if($starttime == $endtime && $GLOBALS['phpgw']->common->show_date($starttime,'Hi') == 0)
			{
				$endtime = mktime(23,59,59,$GLOBALS['phpgw']->common->show_date($starttime,'m'),$GLOBALS['phpgw']->common->show_date($starttime,'d') + 1,$GLOBALS['phpgw']->common->show_date($starttime,'Y')) - $this->datetime->tz_offset;
			}

			$sql = 'AND ((('.$starttime.' <= phpgw_cal.datetime) AND ('.$endtime.' >= phpgw_cal.datetime) AND ('.$endtime.' <= phpgw_cal.edatetime)) '
					.  'OR (('.$starttime.' >= phpgw_cal.datetime) AND ('.$starttime.' < phpgw_cal.edatetime) AND ('.$endtime.' >= phpgw_cal.edatetime)) '
					.  'OR (('.$starttime.' <= phpgw_cal.datetime) AND ('.$endtime.' >= phpgw_cal.edatetime)) '
					.  'OR (('.$starttime.' >= phpgw_cal.datetime) AND ('.$endtime.' <= phpgw_cal.edatetime))) ';

			if(count($participants) > 0)
			{
				$p_g = '';
				if(count($participants))
				{
					$users = Array();
					while(list($user,$status) = each($participants))
					{
						$users[] = $user;
					}
					if($users)
					{
						$p_g .= 'phpgw_cal_user.cal_login in ('.implode(',',$users).')';
					}
				}
				if($p_g)
				{
					$sql .= ' AND (' . $p_g . ')';
				}
			}
      
			if($id)
			{
				$sql .= ' AND phpgw_cal.cal_id <> '.$id;
			}

			$sql .= ' ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';

			$events = $this->so->get_event_ids(False,$sql);
			if($events == False)
			{
				return false;
			}
		
			$db2 = $GLOBALS['phpgw']->db;

			for($i=0;$i<count($events);$i++)
			{
				$db2->query('SELECT recur_type FROM phpgw_cal_repeats WHERE cal_id='.$events[$i],__LINE__,__FILE__);
				if($db2->num_rows() == 0)
				{
					$retval[] = $events[$i];
					$ok = True;
				}
				else
				{
					$db2->next_record();
					if($db2->f('recur_type') <> MCAL_RECUR_MONTHLY_MDAY)
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

		function check_perms($needed,$user=0)
		{
			if($user == 0)
			{
				return ($this->grants[$this->owner] & $needed);
			}
			else
			{
				return ($this->grants[$user] & $needed);
			}
		}

		function get_fullname($accountid)
		{
			$account_id = get_account_id($accountid);
			if($GLOBALS['phpgw']->accounts->exists($account_id) == False)
			{
				return False;
			}
			$db = $GLOBALS['phpgw']->db;
			$db->query('SELECT account_lid,account_lastname,account_firstname FROM phpgw_accounts WHERE account_id='.$account_id,__LINE__,__FILE__);
			if($db->num_rows())
			{
				$db->next_record();
				$fullname = $db->f('account_lid');
				$lname = $db->f('account_lastname');
				$fname = $db->f('account_firstname');
				if($lname && $fname)
				{
					$fullname = $lname.', '.$fname;
				}
				return $fullname;
			}
			else
			{
				return False;
			}
		}

		function display_status($user_status)
		{
			if(@$this->prefs['calendar']['display_status'])
			{
				return ' ('.$user_status.')';
			}
			else
			{
				return '';
			}
		}

		function get_long_status($status_short)
		{
			switch ($status_short)
			{
				case 'A':
					$status = lang('Accepted');
					break;
				case 'R':
					$status = lang('Rejected');
					break;
				case 'T':
					$status = lang('Tentative');
					break;
				case 'U':
					$status = lang('No Response');
					break;
			}
			return $status;
		}

		function is_private($event,$owner)
		{
			if($owner == 0)
			{
				$owner = $this->owner;
			}
			if ($owner == $GLOBALS['phpgw_info']['user']['account_id'] || ($event['public']==1) || ($this->check_perms(PHPGW_ACL_PRIVATE,$owner) && $event['public']==0))
			{
				return False;
			}
			elseif($event['public'] == 0)
			{
				return True;
			}
			elseif($event['public'] == 2)
			{
				$is_private = True;
				$groups = $GLOBALS['phpgw']->accounts->membership($owner);
				while (list($key,$group) = each($groups))
				{
					if (strpos(' '.implode($event['groups'],',').' ',$group['account_id']))
					{
						return False;
					}
				}
			}
			else
			{
				return False;
			}

			return $is_private;
		}

		function get_short_field($event,$is_private=True,$field='')
		{
			if ($is_private)
			{
				return 'private';
			}
			elseif (strlen($event[$field]) > 19)
			{
				return substr($event[$field], 0 , 19) . '...';
			}
			else
			{
				return $event[$field];
			}
		}

		function get_week_label()
		{
			$first = $this->datetime->gmtdate($this->datetime->get_weekday_start($this->year, $this->month, $this->day));
			$last = $this->datetime->gmtdate($first['raw'] + 518400);

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

			return $week_id;
		}

		function normalizeminutes(&$minutes)
		{
			$hour = 0;
			$min = intval($minutes);
			if($min >= 60)
			{
				$hour += $min / 60;
				$min %= 60;
			}
			settype($minutes,'integer');
			$minutes = $min;
			return $hour;
		}

		function splittime($time,$follow_24_rule=True)
		{
			$temp = array('hour','minute','second','ampm');
			$time = strrev($time);
			$second = intval(strrev(substr($time,0,2)));
			$minute = intval(strrev(substr($time,2,2)));
			$hour   = intval(strrev(substr($time,4)));
			$hour += $this->normalizeminutes(&$minute);
			$temp['second'] = $second;
			$temp['minute'] = $minute;
			$temp['hour']   = $hour;
			$temp['ampm']   = '  ';
			if($follow_24_rule == True)
			{
				if ($this->prefs['common']['timeformat'] == '24')
				{
					return $temp;
				}
		
				$temp['ampm'] = 'am';
		
				if ((int)$temp['hour'] > 12)
				{
					$temp['hour'] = (int)((int)$temp['hour'] - 12);
					$temp['ampm'] = 'pm';
   		   }
      		elseif ((int)$temp['hour'] == 12)
	      	{
					$temp['ampm'] = 'pm';
				}
			}
			return $temp;
		}

		function build_time_for_display($fixed_time)
		{
			$time = $this->splittime($fixed_time);
			$str = $time['hour'].':'.((int)$time['minute']<=9?'0':'').$time['minute'];
		
			if ($this->prefs['common']['timeformat'] == '12')
			{
				$str .= ' ' . $time['ampm'];
			}
		
			return $str;
		}
	
		function sort_event($event,$date)
		{
			$inserted = False;
			if($this->cached_events[$date])
			{
				$year = substr($date,0,4);
				$month = substr($date,4,2);
				$day = substr($date,6,2);

				if($this->debug)
				{
					echo "Date : ".$date." Count : ".count($this->cached_events[$date])."<br>\n";
				}
				
				for($i=0;$i<count($this->cached_events[$date]);$i++)
				{
					$events = $this->cached_events[$date][$i];
					if($this->cached_events[$date][$i]['id'] == $event['id'])
					{
						if($this->debug)
						{
							echo "Item already inserted!<br>\n";
						}
						$inserted = True;
						break;
					}
					/* This puts all spanning events across multiple days up at the top. */
					if($this->cached_events[$date][$i]['recur_type'] == MCAL_RECUR_NONE)
					{
						if($this->cached_events[$date][$i]['start']['mday'] != $day && $this->cached_events[$date][$i]['end']['mday'] >= $day)
						{
							continue;
						}
					}
					if(date('Hi',mktime($event['start']['hour'],$event['start']['min'],$event['start']['sec'],$month,$day,$year)) < date('Hi',mktime($this->cached_events[$date][$i]['start']['hour'],$this->cached_events[$date][$i]['start']['min'],$this->cached_events[$date][$i]['start']['sec'],$month,$day,$year)))
					{
						for($j=count($this->cached_events[$date]);$j>=$i;$j--)
						{
							$this->cached_events[$date][$j] = $this->cached_events[$date][$j-1];
						}
						$inserted = True;
						$this->cached_events[$date][$i] = $event;
						break;
					}
				}
			}
			if(!$inserted)
			{
				$this->cached_events[$date][] = $event;
			}					
		}

		function check_repeating_events($datetime)
		{
			@reset($this->repeating_events);
			$search_date_full = date('Ymd',$datetime);
			$search_date_year = date('Y',$datetime);
			$search_date_month = date('m',$datetime);
			$search_date_day = date('d',$datetime);
			$search_date_dow = date('w',$datetime);
			$search_beg_day = mktime(0,0,0,$search_date_month,$search_date_day,$search_date_year);
			$repeated = $this->repeating_events;
			$r_events = count($repeated);
			for ($i=0;$i<$r_events;$i++)
			{
				$rep_events = $this->repeating_events[$i];
				$id = $rep_events->id;
				$event_beg_day = mktime(0,0,0,$rep_events['start']['month'],$rep_events['start']['mday'],$rep_events['start']['year']);
				if($rep_events['recur_enddate']['month'] != 0 && $rep_events['recur_enddate']['mday'] != 0 && $rep_events['recur_enddate']['year'] != 0)
				{
					$event_recur_time = $this->maketime($rep_events['recur_enddate']);
				}
				else
				{
					$event_recur_time = mktime(0,0,0,1,1,2030);
				}
				$end_recur_date = date('Ymd',$event_recur_time);
				$full_event_date = date('Ymd',$event_beg_day);
			
				// only repeat after the beginning, and if there is an rpt_end before the end date
				if (($search_date_full > $end_recur_date) || ($search_date_full < $full_event_date))
				{
					continue;
				}

				if ($search_date_full == $full_event_date)
				{
					$this->sort_event($rep_events,$search_date_full);
					continue;
				}
				else
				{				
					$freq = $rep_events['recur_interval'];
					$type = $rep_events['recur_type'];
					switch($type)
					{
						case MCAL_RECUR_DAILY:
							if (floor(($search_beg_day - $event_beg_day)/86400) % $freq)
							{
								continue;
							}
							else
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_WEEKLY:
							if (floor(($search_beg_day - $event_beg_day)/604800) % $freq)
							{
								continue;
							}
							$check = 0;
							switch($search_date_dow)
							{
								case 0:
									$check = MCAL_M_SUNDAY;
									break;
								case 1:
									$check = MCAL_M_MONDAY;
									break;
								case 2:
									$check = MCAL_M_TUESDAY;
									break;
								case 3:
									$check = MCAL_M_WEDNESDAY;
									break;
								case 4:
									$check = MCAL_M_THURSDAY;
									break;
								case 5:
									$check = MCAL_M_FRIDAY;
									break;
								case 6:
									$check = MCAL_M_SATURDAY;
									break;
							}
							if ($rep_events['recur_data'] & $check)
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_MONTHLY_WDAY:
							if ((($search_date_year - $rep_events['start']['year']) * 12 + $search_date_month - $rep_events['start']['month']) % $freq)
							{
								continue;
							}
	  
							if (($this->datetime->day_of_week($rep_events['start']['year'],$rep_events['start']['month'],$rep_events['start']['mday']) == $this->datetime->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
								(ceil($rep_events['start']['mday']/7) == ceil($search_date_day/7)))
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_MONTHLY_MDAY:
							if ((($search_date_year - $rep_events['start']['year']) * 12 + $search_date_month - $rep_events['start']['month']) % $freq)
							{
								continue;
							}
							if ($search_date_day == $rep_events['start']['mday'])
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
						case MCAL_RECUR_YEARLY:
							if (($search_date_year - $rep_events['start']['year']) % $freq)
							{
								continue;
							}
							if (date('dm',$datetime) == date('dm',$event_beg_day))
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							break;
					}
				}
			}	// end for loop
		}	// end function

		function store_to_cache($params)
		{
			if(!is_array($params))
			{
				return False;
			}

			$syear = $params['syear'];
			$smonth = $params['smonth'];
			$sday = $params['sday'];
			$eyear = (isset($params['eyear'])?$params['eyear']:0);
			$emonth = (isset($params['emonth'])?$params['emonth']:0);
			$eday = (isset($params['eday'])?$params['eday']:0);
			
			if(!$eyear && !$emonth && !$eday)
			{
				$edate = mktime(23,59,59,$smonth + 1,$sday + 1,$syear);
				$eyear = date('Y',$edate);
				$emonth = date('m',$edate);
				$eday = date('d',$edate);
			}
			else
			{
				if(!$eyear)
				{
					$eyear = $syear;
				}
				if(!$emonth)
				{
					$emonth = $smonth + 1;
					if($emonth > 12)
					{
						$emonth = 1;
						$eyear++;
					}
				}
				if(!$eday)
				{
					$eday = $sday + 1;
				}
				$edate = mktime(23,59,59,$emonth,$eday,$eyear);
			}
			
			if($this->debug)
			{
				echo "Start Date : ".sprintf("%04d%02d%02d",$syear,$smonth,$sday)."<br>\n";
				echo "End   Date : ".sprintf("%04d%02d%02d",$eyear,$emonth,$eday)."<br>\n";
			}

			$cached_event_ids = $this->so->list_events($syear,$smonth,$sday,$eyear,$emonth,$eday);
			$cached_event_ids_repeating = $this->so->list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday);

			$c_cached_ids = count($cached_event_ids);
			$c_cached_ids_repeating = count($cached_event_ids_repeating);

			if($this->debug)
			{
				echo "events cached : $c_cached_ids : for : ".sprintf("%04d%02d%02d",$syear,$smonth,$sday)."<br>\n";
				echo "repeating events cached : $c_cached_ids_repeating : for : ".sprintf("%04d%02d%02d",$syear,$smonth,$sday)."<br>\n";
			}

			$this->cached_events = Array();
			
			if($c_cached_ids == 0 && $c_cached_ids_repeating == 0)
			{
				return;
			}

			$this->cached_events = Array();
			if($c_cached_ids)
			{
				for($i=0;$i<$c_cached_ids;$i++)
				{
					$event = $this->so->read_entry($cached_event_ids[$i]);
					$startdate = intval(date('Ymd',$this->maketime($event['start'])));
					$enddate = intval(date('Ymd',$this->maketime($event['end'])));
					$this->cached_events[$startdate][] = $event;
					if($startdate != $enddate)
					{
						$start['year'] = intval(substr($startdate,0,4));
						$start['month'] = intval(substr($startdate,4,2));
						$start['mday'] = intval(substr($startdate,6,2));
						for($j=$startdate,$k=0;$j<=$enddate;$k++,$j=intval(date('Ymd',mktime(0,0,0,$start['month'],$start['mday'] + $k,$start['year']))))
						{
							$c_evt_day = count($this->cached_events[$j]) - 1;
							if($c_evt_day < 0)
							{
								$c_evt_day = 0;
							}
//							if($this->debug)
//							{
//								echo "Date: ".$j." Count : ".$c_evt_day."<br>\n";
//							}
							if($this->cached_events[$j][$c_evt_day]['id'] != $event['id'])
							{
//								if($this->debug)
//								{
//									echo "Adding Event for Date: ".$j."<br>\n";
//								}
								$this->cached_events[$j][] = $event;
							}
						}
					}
				}
			}

			$this->repeating_events = Array();
			if($c_cached_ids_repeating)
			{
				for($i=0;$i<$c_cached_ids_repeating;$i++)
				{
					$this->repeating_events[$i] = $this->so->read_entry($cached_event_ids_repeating[$i]);
				}
				$edate -= $this->datetime->tz_offset;
				for($date=mktime(0,0,0,$smonth,$sday,$syear) - $this->datetime->tz_offset;$date<$edate;$date += 86400)
				{
					$this->check_repeating_events($date);
				}
			}
			$retval = Array();
			for($j=date('Ymd',mktime(0,0,0,$smonth,$sday,$syear)),$k=0;$j<=date('Ymd',mktime(0,0,0,$emonth,$eday,$eyear));$k++,$j=date('Ymd',mktime(0,0,0,$smonth,$sday + $k,$syear)))
			{
				$retval[$j] = $this->cached_events[$j];
			}
			return $retval;
//			return $this->cached_events;
		}

		/* Begin Appsession Data */
		function store_to_appsession($event)
		{
			$GLOBALS['phpgw']->session->appsession('entry','calendar',$event);
		}

		function restore_from_appsession()
		{
			$this->event_init();
			$event = $GLOBALS['phpgw']->session->appsession('entry','calendar');
			$this->so->cal->event = $event;
			return $event;
		}
		/* End Appsession Data */

		/* Begin of SO functions */
		function get_cached_event()
		{
			return $this->so->get_cached_event();
		}
		
		function add_attribute($var,$value)
		{
			$this->so->add_attribute($var,$value);
		}

		function event_init()
		{
			$this->so->event_init();
		}

		function set_start($year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			$this->so->set_start($year,$month,$day,$hour,$min,$sec);
		}

		function set_end($year,$month,$day=0,$hour=0,$min=0,$sec=0)
		{
			$this->so->set_end($year,$month,$day,$hour,$min,$sec);
		}

		function set_title($title='')
		{
			$this->so->set_title($title);
		}

		function set_description($description='')
		{
			$this->so->set_description($description);
		}

		function set_class($class)
		{
			$this->so->set_class($class);
		}

		function set_category($category='')
		{
			$this->so->set_category($category);
		}

		function set_alarm($alarm)
		{
			$this->so->set_alarm($alarm);
		}

		function set_recur_none()
		{
			$this->so->set_recur_none();
		}

		function set_recur_daily($year,$month,$day,$interval)
		{
			$this->so->set_recur_daily($year,$month,$day,$interval);
		}

		function set_recur_weekly($year,$month,$day,$interval,$weekdays)
		{
			$this->so->set_recur_weekly($year,$month,$day,$interval,$weekdays);
		}

		function set_recur_monthly_mday($year,$month,$day,$interval)
		{
			$this->so->set_recur_monthly_mday($year,$month,$day,$interval);
		}

		function set_recur_monthly_wday($year,$month,$day,$interval)
		{
			$this->so->set_recur_monthly_wday($year,$month,$day,$interval);
		}

		function set_recur_yearly($year,$month,$day,$interval)
		{
			$this->so->set_recur_yearly($year,$month,$day,$interval);
		}
		/* End of SO functions */

		function set_week_array($startdate,$cellcolor,$weekly)
		{
			for ($j=0,$datetime=$startdate - $this->datetime->tz_offset;$j<7;$j++,$datetime += 86400)
			{
				$date = date('Ymd',$datetime);

				if($this->debug)
				{
					echo "set_week_array : Date : ".$date."<br>\n";
				}

				$holidays = $this->cached_holidays[$date];
				if($weekly)
				{
					$cellcolor = $GLOBALS['phpgw']->nextmatchs->alternate_row_color($cellcolor);
				}
				
				$day_image = '';
				if($holidays)
				{
					$extra = ' bgcolor="'.$this->holiday_color.'"';
					$class = 'minicalhol';
					if ($date == $this->today)
					{
						$day_image = ' background="'.$GLOBALS['phpgw']->common->image('calendar','mini_day_block.gif').'"';
					}
				}
				elseif ($date != $this->today)
				{
					$extra = ' bgcolor="'.$cellcolor.'"';
					$class = 'minicalendar';
				}
				else
				{
					$extra = ' bgcolor="'.$GLOBALS['phpgw_info']['theme']['cal_today'].'"';
					$class = 'minicalendar';
					$day_image = ' background="'.$GLOBALS['phpgw']->common->image('calendar','mini_day_block.gif').'"';
				}

				if($this->printer_friendly && @$this->prefs['calendar']['print_black_white'])
				{
					$extra = '';
				}

				if(!$this->printer_friendly && $this->check_perms(PHPGW_ACL_ADD))
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
				if($this->cached_events[$date])
				{
					if($this->debug)
					{
						echo "Date : ".$date." Appointments found : ".count($this->cached_events[$date])."<br>\n";
					}
					$appts = True;
				}
				else
				{
					$appts = False;
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

		function prepare_matrix($interval,$increment,$part,$status,$fulldate)
		{
			for($h=0;$h<24;$h++)
			{
				for($m=0;$m<$interval;$m++)
				{
					$index = (($h * 10000) + (($m * $increment) * 100));
					$time_slice[$index]['marker'] = '&nbsp';
					$time_slice[$index]['description'] = '';
				}
			}
			for($k=0;$k<count($this->cached_events[$fulldate]);$k++)
			{
				$event = $this->cached_events[$fulldate][$k];
				$eventstart = $this->datetime->localdates($event->datetime);
				$eventend = $this->datetime->localdates($event->edatetime);
				$start = ($eventstart['hour'] * 10000) + ($eventstart['minute'] * 100);
				$starttemp = $this->splittime("$start",False);
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
				$endtemp = $this->splittime("$end",False);
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
				$starttemp = $this->splittime("$start",False);
				$endtemp = $this->splittime("$end",False);
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
						$private = $this->is_private($event,$part);
						$time_display = $GLOBALS['phpgw']->common->show_date($eventstart['raw'],$this->users_timeformat).'-'.$GLOBALS['phpgw']->common->show_date($eventend['raw'],$this->users_timeformat);
						$time_description = '('.$time_display.') '.$this->get_short_field($event,$private,'title').$this->display_status($event['participants'][$part]);
						for($m=$startminute;$m<=$endminute;$m++)
						{
							$index = ($hour + (($m * $increment) * 100));
							$time_slice[$index]['marker'] = '-';
							$time_slice[$index]['description'] = $time_description;
						}
					}
				}
			}
			return $time_slice;
		}

		function set_status($cal_id,$status)
		{
			$old_event = $this->so->read_entry($cal_id);
			switch($status)
			{
				case REJECTED:
					$this->send_update(MSG_REJECTED,$old_event['participants'],$old_event);
					$this->so->set_status($cal_id,$status);
					break;
				case TENTATIVE:
					$this->send_update(MSG_TENTATIVE,$old_event['participants'],$old_event);
					$this->so->set_status($cal_id,$status);
					break;
				case ACCEPTED:
					$this->send_update(MSG_ACCEPTED,$old_event['participants'],$old_event);
					$this->so->set_status($cal_id,$status);
					break;
			}
			return True;
		}

		function send_update($msg_type,$participants,$old_event=False,$new_event=False)
		{
			$db = $GLOBALS['phpgw']->db;
			$db->query("SELECT app_version FROM phpgw_applications WHERE app_name='calendar'",__LINE__,__FILE__);
			$db->next_record();
			$version = $db->f('app_version');
			unset($db);

			$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->common->create_emailpreferences($GLOBALS['phpgw_info']['user']['preferences']);
			$sender = $GLOBALS['phpgw_info']['user']['preferences']['email']['address'];

			$temp_tz_offset = $this->prefs['common']['tz_offset'];
			$temp_timeformat = $this->prefs['common']['timeformat'];
			$temp_dateformat = $this->prefs['common']['dateformat'];

			$tz_offset = ((60 * 60) * intval($temp_tz_offset));

			if($old_event != False)
			{
				$t_old_start_time = $this->maketime($old_event['start']);
				if($t_old_start_time < (time() - 86400))
				{
					return False;
				}
			}

			$temp_user = $GLOBALS['phpgw_info']['user'];

			if($this->owner != $temp_user['account_id'])
			{
				$user = $this->owner;
		
				$accounts = CreateObject('phpgwapi.accounts',$user);
				$phpgw_info['user'] = $accounts->read_repository();

				$pref = CreateObject('phpgwapi.preferences',$user);
				$GLOBALS['phpgw_info']['user']['preferences'] = $pref->read_repository();
			}
			else
			{
				$user = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->common->create_emailpreferences($GLOBALS['phpgw_info']['user']['preferences'],$user);

			switch($msg_type)
			{
				case MSG_DELETED:
					$action = 'Deleted';
					$event_id = $old_event['id'];
					$msgtype = '"calendar";';
					break;
				case MSG_MODIFIED:
					$action = 'Modified';
					$event_id = $old_event['id'];
					$msgtype = '"calendar"; Version="'.$version.'"; Id="'.$new_event->id.'"';
					break;
				case MSG_ADDED:
					$action = 'Added';
					$event_id = $new_event['id'];
					$msgtype = '"calendar"; Version="'.$version.'"; Id="'.$new_event->id.'"';
					break;
				case MSG_REJECTED:
					$action = 'Rejected';
					$event_id = $old_event['id'];
					$msgtype = '"calendar";';
					break;
				case MSG_TENTATIVE:
					$action = 'Tentative';
					$event_id = $old_event['id'];
					$msgtype = '"calendar";';
					break;
				case MSG_ACCEPTED:
					$action = 'Accepted';
					$event_id = $old_event['id'];
					$msgtype = '"calendar";';
					break;
			}

			if($old_event != False)
			{
				$old_event_datetime = $t_old_start_time - $this->datetime->tz_offset;
			}
		
			if($new_event != False)
			{
				$new_event_datetime = $this->maketime($new_event['start']) - $this->datetime->tz_offset;
			}

			while(list($userid,$statusid) = each($participants))
			{
				if((intval($userid) != $GLOBALS['phpgw_info']['user']['account_id']) &&
				   (
				    (
				     ($msg_type == MSG_REJECTED || $msg_type == MSG_TENTATIVE || $msg_type == MSG_ACCEPTED) &&
				     ($old_event['owner'] == $userid)
				    ) ||
				    ($msg_type == MSG_DELETED || $msg_type == MSG_MODIFIED || $msg_type == MSG_ADDED)
				   )
				  )
				{
					if($this->debug)
					{
						echo "Msg Type = ".$msg_type."<br>\n";
						echo "userid = ".$userid."<br>\n";
					}
					if(!is_object($send))
					{
						$send = CreateObject('phpgwapi.send');
					}

					$preferences = CreateObject('phpgwapi.preferences',intval($userid));
					$part_prefs = $preferences->read_repository();
					if(!isset($part_prefs['calendar']['send_updates']) || !$part_prefs['calendar']['send_updates'])
					{
						continue;
					}
					$part_prefs = $GLOBALS['phpgw']->common->create_emailpreferences($part_prefs,intval($userid));
					$to = $part_prefs['email']['address'];
					
					if($this->debug)
					{
						echo "Email being sent to: ".$to."<br>\n";
					}

					$GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset'] = $part_prefs['common']['tz_offset'];
					$GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] = $part_prefs['common']['timeformat'];
					$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'] = $part_prefs['common']['dateformat'];
				
					$new_tz_offset = ((60 * 60) * intval($GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']));

					if($old_event != False)
					{
						$old_event_date = $GLOBALS['phpgw']->common->show_date($old_event_datetime);
					}
				
					if($new_event != False)
					{
						$new_event_date = $GLOBALS['phpgw']->common->show_date($new_event_datetime);
					}
				
					switch($msg_type)
					{
						case MSG_DELETED:
							$action_date = $old_event_date;
							$body = 'Your meeting scehduled for '.$old_event_date.' has been canceled';
							break;
						case MSG_MODIFIED:
							$action_date = $new_event_date;
							$body = 'Your meeting that had been scheduled for '.$old_event_date.' has been rescheduled to '.$new_event_date;
							break;
						case MSG_ADDED:
							$action_date = $new_event_date;
							$body = 'You have a meeting scheduled for '.$new_event_date;
							break;
						case MSG_REJECTED:
						case MSG_TENTATIVE:
						case MSG_ACCEPTED:
							$action_date = $old_event_date;
							$body = 'On '.$phpgw->common->show_date(time() - $new_tz_offset).' '.$phpgw->common->grab_owner_name($phpgw_info['user']['account_id']).' '.$action.' your meeting request for '.$old_event_date;
							break;
					}
					$subject = 'Calendar Event ('.$action.') #'.$event_id.': '.$action_date.' (L)';
					$returncode = $send->msg('email',$to,$subject,$body,$msgtype,'','','',$sender);
				}
			}
			unset($send);
		
			if((is_int($this->user) && $this->user != $temp_user['account_id']) ||
				(is_string($this->user) && $this->user != $temp_user['account_lid']))
			{
				$GLOBALS['phpgw_info']['user'] = $temp_user;
			}

			$GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset'] = $temp_tz_offset;
			$GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] = $temp_timeformat;
			$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'] = $temp_dateformat;
		}

		function prepare_recipients(&$new_event,$old_event)
		{
			// Find modified and deleted users.....
			while(list($old_userid,$old_status) = each($old_event['participants']))
			{
				if(isset($new_event['participants'][$old_userid]))
				{
					if($this->debug)
					{
						echo "Modifying event for user ".$old_userid."<br>\n";
					}
					$this->modified[intval($old_userid)] = $new_status;
				}
				else
				{
					if($this->debug)
					{
						echo "Deleting user ".$old_userid." from the event<br>\n";
					}
					$this->deleted[intval($old_userid)] = $old_status;
				}
			}
			// Find new users.....
			while(list($new_userid,$new_status) = each($new_event['participants']))
			{
				if(!isset($old_event['participants'][$new_userid]))
				{
					if($this->debug)
					{
						echo "Adding event for user ".$new_userid."<br>\n";
					}
					$this->added[$new_userid] = 'U';
					$new_event['participants'][$new_userid] = 'U';
				}
			}
		
	      if(count($this->added) > 0 || count($this->modified) > 0 || count($this->deleted) > 0)
   	   {
				if(count($this->added) > 0)
				{
					$this->send_update(MSG_ADDED,$this->added,'',$new_event);
				}
				if(count($this->modified) > 0)
				{
					$this->send_update(MSG_MODIFIED,$this->modified,$old_event,$new_event);
				}
				if(count($this->deleted) > 0)
				{
					$this->send_update(MSG_DELETED,$this->deleted,$old_event);
				}
			}
		}

		function remove_doubles_in_cache($firstday,$lastday)
		{
			$already_moved = Array();
			for($v=$firstday;$v<=$lastday;$v++)
			{
				if (!$this->cached_events[$v])
				{
					continue;
				}
				while (list($g,$event) = each($this->cached_events[$v]))
				{
					$start = sprintf('%04d%02d%02d',$event['start']['year'],$event['start']['month'],$event['start']['mday']);
					if($this->debug)
					{
						echo "<p>Event:<br>"; print_r($event); echo "</p>";
						echo "<p>start='$start', v='$v' ";
					}

//					if ($start != $v && $event['recur_type'] == MCAL_RECUR_NONE)							// this is an enddate-entry --> remove it
					if ($start != $v)							// this is an enddate-entry --> remove it
					{
						unset($this->cached_events[$v][$g]);
						if($g != count($this->cached_events[$v]))
						{
							for($h=$g + 1;$h<$c_daily;$h++)
							{
								$this->cached_events[$v][$h - 1] = $this->cached_events[$v][$h];
							}
							unset($this->cached_events[$v][$h]);
						}

//						if ($start < $firstday && $event['recur_type'] == MCAL_RECUR_NONE)				// start before period --> move it to the beginning
						if ($start < $firstday)				// start before period --> move it to the beginning
						{
							if($already_moved[$event['id']] > 0)
							{
								continue;
							}
							$add_event = True;
							$c_events = count($this->cached_events[$firstday]);
							for($i=0;$i<$c_events;$i++)
							{
								$add_event = ($this->cached_events[$firstday][$i]['id'] == $event['id']?False:$add_event);
							}
							if($add_event)
							{
								$this->cached_events[$firstday][] = $event;
								$already_moved[$event['id']] = 1;
								if($this->debug)
								{
									echo "moved</p>\n";
								}
							}
							else
							{
								$already_moved[$event['id']] = 2;
								if($this->debug)
								{
									echo "removed (not moved)</p>\n";
								}
							}
       				}
						elseif($this->debug)
						{
							echo "removed</p>\n";
						}
					}
					elseif($this->debug)
					{
						echo "ok</p>\n";
					}
				}
				flush();
			}
		}

		function _debug_array($data)
		{
			echo '<br>UI:';
			_debug_array($data);
		}
	}
?>
