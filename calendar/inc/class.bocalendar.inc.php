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
			'change_owner'	=> True,
			'update'       => True,
			'preferences'  => True,
			'store_to_cache'	=> True,
			'export_event'	=> True,
			'reinstate'		=> True
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
			'change_owner' => Array(
				'in' => Array(
					'array'
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
			),
			'store_to_cache'	=> Array(
				'in' => Array(
					'array'
				),
				'out' => Array(
					'string'
				)
			)
		);

		var $debug = False;
		var $debug = True;

		var $so;
		var $cached_events;
		var $repeating_events;
		var $day;
		var $month;
		var $year;
		var $prefs;

		var $owner;
		var $holiday_color;
		var $printer_friendly = False;

		var $cached_holidays;

		var $g_owner = 0;
		
		var $filter;
		var $cat_id;
		var $users_timeformat;
		
		var $modified;
		var $deleted;
		var $added;
		var $is_group = False;
		var $soap = False;
		var $use_session = False;
		var $today;

		var $sortby;
		var $num_months;

		function bocalendar($session=0)
		{
			$this->grants = $GLOBALS['phpgw']->acl->get_grants('calendar');
			@reset($this->grants);
			while(list($grantor,$rights) = each($this->grants))
			{
				print_debug('Grantor',$grantor);
				print_debug('Rights',$rights);
			}
			@reset($this->grants);

			print_debug('Read use_session',$session);

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}
			print_debug('BO Filter',$this->filter);
			print_debug('Owner',$this->owner);

			$this->prefs['calendar']    = $GLOBALS['phpgw_info']['user']['preferences']['calendar'];

			$owner = (isset($GLOBALS['owner'])?$GLOBALS['owner']:'');
			$owner = (isset($GLOBALS['HTTP_GET_VARS']['owner'])?$GLOBALS['HTTP_GET_VARS']['owner']:$owner);
			$owner = ($owner=='' && isset($GLOBALS['HTTP_POST_VARS']['owner'])?$GLOBALS['HTTP_POST_VARS']['owner']:$owner);
			
			ereg('menuaction=([a-zA-Z.]+)',$GLOBALS['HTTP_REFERER'],$regs);
			$from = $regs[1];
			if ((substr($GLOBALS['PHP_SELF'],-8) == 'home.php' && substr($this->prefs['calendar']['defaultcalendar'],0,7) == 'planner'
				 || $GLOBALS['HTTP_GET_VARS']['menuaction'] == 'calendar.uicalendar.planner' &&
				    $from  != 'calendar.uicalendar.planner' && !$this->save_owner)
				 && intval($this->prefs['calendar']['planner_start_with_group']) > 0)
			{
				// entering planner for the first time ==> saving owner in save_owner, setting owner to default
				//
				$this->save_owner = $this->owner;
				$owner = 'g_'.$this->prefs['calendar']['planner_start_with_group'];
			}
			elseif ($GLOBALS['HTTP_GET_VARS']['menuaction'] != 'calendar.uicalendar.planner' &&
			        $this->save_owner)
			{
				// leaving planner with an unchanged user/owner ==> setting owner back to save_owner
				//
				$owner = intval(isset($GLOBALS['HTTP_GET_VARS']['owner']) ? $GLOBALS['HTTP_GET_VARS']['owner'] : $this->save_owner);
				unset($this->save_owner);
			}
			elseif (!empty($owner) && $owner != $this->owner && $from == 'calendar.uicalendar.planner')
			{
				// user/owner changed within planner ==> forgetting save_owner
				//
				unset($this->save_owner);
			}
			
			if(isset($owner) && $owner!='' && substr($owner,0,2) == 'g_')
			{
				$this->set_owner_to_group(substr($owner,2));
			}
			elseif(isset($owner) && $owner!='')
			{
				$this->owner = intval($owner);
			}
			elseif(!@isset($this->owner) || !@$this->owner)
			{
				$this->owner = intval($GLOBALS['phpgw_info']['user']['account_id']);
			}
			elseif(isset($this->owner) && $GLOBALS['phpgw']->accounts->get_type($this->owner) == 'g')
			{
				$this->set_owner_to_group(intval($this->owner));
			}

			$this->prefs['common']    = $GLOBALS['phpgw_info']['user']['preferences']['common'];

			if ($this->prefs['common']['timeformat'] == '12')
			{
				$this->users_timeformat = 'h:ia';
			}
			else
			{
				$this->users_timeformat = 'H:i';
			}
			$this->holiday_color = (substr($GLOBALS['phpgw_info']['theme']['bg07'],0,1)=='#'?'':'#').$GLOBALS['phpgw_info']['theme']['bg07'];

			$this->printer_friendly = (intval(get_var('friendly',Array('GET','POST','DEFAULT'),0)) == 1?True:False);

			$this->filter = get_var('filter',Array('POST','DEFAULT'),' '.$this->prefs['calendar']['defaultfilter'].' ');

			$this->sortby = get_var('sortby',Array('POST'),$this->sortby);
			if(empty($this->sortby))
			{
			   $this->sortby = $this->prefs['calendar']['defaultcalendar'] == 'planner_user' ? 'user' : 'category';
			}

			if($GLOBALS['phpgw']->accounts->get_type($this->owner)=='g')
			{
				$this->filter = ' all ';
			}

			$this->cat_id = get_var('cat_id',Array('POST'));

			$this->so = CreateObject('calendar.socalendar',
				Array(
					'owner'		=> $this->owner,
					'filter'		=> $this->filter,
					'category'	=> $this->cat_id,
					'g_owner'	=> $this->g_owner
				)
			);
			$localtime = $GLOBALS['phpgw']->datetime->users_localtime;

			$date = get_var('date',Array('GET','POST','GLOBAL'));
			$year = get_var('year',Array('GET','POST'));
			$month = get_var('month',Array('GET','POST'));
			$day = get_var('day',Array('GET','POST'));
			$num_months = get_var('num_months',Array('GET','POST'));
			
			if(isset($date) && $date!='')
			{
				$this->year = intval(substr($date,0,4));
				$this->month = intval(substr($date,4,2));
				$this->day = intval(substr($date,6,2));
			}
			else
			{
				if(isset($year) && $year!='')
				{
					$this->year = $year;
				}
				else
				{
					$this->year = date('Y',$GLOBALS['phpgw']->datetime->users_localtime);
				}
				if(isset($month) && $month!='')
				{
					$this->month = $month;
				}
				else
				{
					$this->month = date('m',$GLOBALS['phpgw']->datetime->users_localtime);
				}
				if(isset($day) && $day!='')
				{
					$this->day = $day;
				}
				else
				{
					$this->day = date('d',$GLOBALS['phpgw']->datetime->users_localtime);
				}
			}

			if(isset($num_months) && $num_months!='')
			{
				$this->num_months = $num_months;
			}
			elseif($this->num_months == 0)
			{
				$this->num_months = 1;
			}


			$this->today = date('Ymd',$GLOBALS['phpgw']->datetime->users_localtime);

			print_debug('BO Filter','('.$this->filter.')');
			print_debug('Owner',$this->owner);
		}

		function list_methods($_type='xmlrpc')
		{
			/*
			  This handles introspection or discovery by the logged in client,
			  in which case the input might be an array.  The server always calls
			  this function to fill the server dispatch map using a string.
			*/
			if (is_array($_type))
			{
				$_type = $_type['type'];
			}
			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
 						),
						'read_entry' => array(
							'function'  => 'read_entry',
							'signature' => array(array(xmlrpcStruct,xmlrpcInt)),
							'docstring' => lang('Read a single entry by passing the id and fieldlist.')
						),
						'add_entry' => array(
							'function'  => 'update',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Add a single entry by passing the fields.')
						),
						'update_entry' => array(
							'function'  => 'update',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Update a single entry by passing the fields.')
						),
						'delete_entry' => array(
							'function'  => 'delete_entry',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Delete a single entry by passing the id.')
						),
						'delete_calendar' => array(
							'function'  => 'delete_calendar',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Delete an entire users calendar.')
						),
						'change_owner' => array(
							'function'  => 'change_owner',
							'signature' => array(array(xmlrpcInt,xmlrpcStruct)),
							'docstring' => lang('Change all events for $params[\'old_owner\'] to $params[\'new_owner\'].')
						),
						'store_to_cache' => array(
							'function'  => 'store_to_cache',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Read a list of entries.')
						),
						'export_event' => array(
							'function'  => 'export_event',
							'signature' => array(array(xmlrpcString,xmlrpcStruct)),
							'docstring' => lang('Export a list of entries in iCal format.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

		function set_owner_to_group($owner)
		{
			$this->owner = intval($owner);
			$this->is_group = True;
			settype($this->g_owner,'array');
			$this->g_owner = Array();
			$group_owners = $GLOBALS['phpgw']->accounts->member($owner);
			while($group_owners && list($index,$group_info) = each($group_owners))
			{
				$this->g_owner[] = $group_info['account_id'];
			}
		}

		function member_of_group($owner=0)
		{
			$owner = ($owner==0?$GLOBALS['phpgw_info']['user']['account_id']:$owner);
			$group_owners = $GLOBALS['phpgw']->accounts->membership();
			while($group_owners && list($index,$group_info) = each($group_owners))
			{
				if($this->owner == $group_info['account_id'])
				{
					return True;
				}
			}
			return False;
		}

		function save_sessiondata($data='')
		{
			if ($this->use_session)
			{
				if (!is_array($data))
				{
					$data = array(
						'filter'     => $this->filter,
						'cat_id'     => $this->cat_id,
						'owner'      => $this->owner,
						'save_owner' => $this->save_owner,
						'year'       => $this->year,
						'month'      => $this->month,
						'day'        => $this->day,
						'date'       => $this->date,
						'sortby'     => $this->sortby,
						'num_months' => $this->num_months,
						'return_to'  => $this->return_to
					);
				}
				print_debug('Save',_debug_array($data,False));
				$GLOBALS['phpgw']->session->appsession('session_data','calendar',$data);
			}
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','calendar');
			print_debug('Read',_debug_array($data,False));

			$this->filter = $data['filter'];
			$this->cat_id = $data['cat_id'];
			$this->sortby = $data['sortby'];
			$this->owner  = intval($data['owner']);
			$this->save_owner = intval($data['save_owner']);
			$this->year   = intval($data['year']);
			$this->month  = intval($data['month']);
			$this->day    = intval($data['day']);
			$this->num_months = intval($data['num_months']);
			$this->return_to = $data['return_to'];
		}

		function read_entry($id)
		{
			if($this->check_perms(PHPGW_ACL_READ,$id))
			{
				$event = $this->so->read_entry($id);
				if(!isset($event['participants'][$this->owner]) && $this->user_is_a_member($event,$this->owner))
				{
					$this->so->add_attribute('participants','U',intval($this->owner));
					$this->so->add_entry($event);
					$event = $this->get_cached_event();
				}
				return $event;
			}
		}

		function delete_single($param)
		{
			if($this->check_perms(PHPGW_ACL_DELETE,intval($param['id'])))
			{
				$temp_event = $this->get_cached_event();
				$event = $this->read_entry(intval($param['id']));

				$exception_time = mktime($event['start']['hour'],$event['start']['min'],0,$param['month'],$param['day'],$param['year']) - $GLOBALS['phpgw']->datetime->tz_offset;
				$event['recur_exception'][] = intval($exception_time);
				$this->so->cal->event = $event;
				print_debug('exception time',$event['recur_exception'][count($event['recur_exception']) -1]);
				print_debug('count event exceptions',count($event['recur_exception']));
				$this->so->add_entry($event);
				$cd = 16;
			}
			else
			{
				$cd = 60;
			}
			$this->so->cal->event = $temp_event;
			unset($temp_event);
			return $cd;
		}

		function delete_entry($id)
		{
			if($this->check_perms(PHPGW_ACL_DELETE,$id))
			{
				$this->so->delete_entry($id);
				$cd = 16;
			}
			else
			{
				$cd = 60;
			}
			return $cd;
		}

		function reinstate($params='')
		{
			if($this->check_perms(PHPGW_ACL_EDIT,$params['cal_id']) && isset($params['reinstate_index']))
			{
				$event = $this->so->read_entry($params['cal_id']);
				@reset($params['reinstate_index']);
				print_debug('Count of reinstate_index',count($params['reinstate_index']));
				if(count($params['reinstate_index']) > 1)
				{
					while(list($key,$value) = each($params['reinstate_index']))
					{
						print_debug('reinstate_index ['.$key.']',intval($value));
						print_debug('exception time',$event['recur_exception'][intval($value)]);
						unset($event['recur_exception'][intval($value)]);
						print_debug('count event exceptions',count($event['recur_exception']));
					}
				}
				else
				{
					print_debug('reinstate_index[0]',intval($params['reinstate_index'][0]));
					print_debug('exception time',$event['recur_exception'][intval($params['reinstate_index'][0])]);
					unset($event['recur_exception'][intval($params['reinstate_index'][0])]);
					print_debug('count event exceptions',count($event['recur_exception']));
				}
				$this->so->cal->event = $event;
				$this->so->add_entry($event);
				return 42;
			}
			else
			{
				return 43;
			}
		}

		function delete_calendar($owner)
		{
			if($GLOBALS['phpgw_info']['user']['apps']['admin'])
			{
				$this->so->delete_calendar($owner);
			}
		}

		function change_owner($params='')
		{
			if($GLOBALS['phpgw_info']['server']['calendar_type'] == 'sql')
			{
				if(is_array($params))
				{
					$this->so->change_owner($params['old_owner'],$params['new_owner']);
				}
			}
		}

		function expunge()
		{
			reset($this->so->cal->deleted_events);
			while(list($i,$event_id) = each($this->so->cal->deleted_events))
			{
				$event = $this->so->read_entry($event_id);
				if($this->check_perms(PHPGW_ACL_DELETE,$event))
				{
					$this->send_update(MSG_DELETED,$event['participants'],$event);
				}
				else
				{
					unset($this->so->cal->deleted_events[$i]);
				}
			}
			$this->so->expunge();
		}

		function search_keywords($keywords)
		{
			return $this->so->list_events_keyword($keywords);
		}

		function update($params='')
		{
			$l_cal = (@isset($params['cal']) && $params['cal']?$params['cal']:$GLOBALS['HTTP_POST_VARS']['cal']);
			$l_participants = (@$params['participants']?$params['participants']:$GLOBALS['HTTP_POST_VARS']['participants']);
			$l_categories = (@$params['categories']?$params['categories']:$GLOBALS['HTTP_POST_VARS']['categories']);
			$l_start = (@isset($params['start']) && $params['start']?$params['start']:$GLOBALS['HTTP_POST_VARS']['start']);
			$l_end = (@isset($params['end']) && $params['end']?$params['end']:$GLOBALS['HTTP_POST_VARS']['end']);
			$l_recur_enddate = (@isset($params['recur_enddate']) && $params['recur_enddate']?$params['recur_enddate']:$GLOBALS['HTTP_POST_VARS']['recur_enddate']);

			$send_to_ui = True;
			if($this->debug)
			{
				$send_to_ui = True;
			}
			if($p_cal || $p_participants || $p_start || $p_end || $p_recur_enddata)
			{
				$send_to_ui = False;
			}

			print_debug('ID',$l_cal['id']);

			if(get_var('readsess',Array('GET')))
			{
				$event = $this->restore_from_appsession();
				$event['title'] = stripslashes($event['title']);
				$event['description'] = stripslashes($event['description']);
				$datetime_check = $this->validate_update($event);
				if($datetime_check)
				{
					ExecMethod('calendar.uicalendar.edit',
						Array(
							'cd'		=> $datetime_check,
							'readsess'	=> 1
						)
					);
					exit;
				}
				$overlapping_events = False;
			}
			else
			{
				if((!$l_cal['id'] && !$this->check_perms(PHPGW_ACL_ADD)) || ($l_cal['id'] && !$this->check_perms(PHPGW_ACL_EDIT,$l_cal['id'])))
				{
					ExecMethod('calendar.uicalendar.index');
					$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
					exit;
				}

				print_debug('prior to fix_update_time()');
				$this->fix_update_time($l_start);
				$this->fix_update_time($l_end);

				if(!isset($l_cal['private']))
				{
					$l_cal['private'] = 'public';
				}

				if(!isset($l_categories))
				{
					$l_categories = 0;
				}

				$is_public = ($l_cal['private'] == 'public'?1:0);
				$this->so->event_init();
				$this->add_attribute('uid',$l_cal['uid']);
				if(count($l_categories) >= 2)
				{
					$this->so->set_category(implode(',',$l_categories));
				}
				else
				{
					$this->so->set_category(strval($l_categories[0]));
				}
				$this->so->set_title($l_cal['title']);
				$this->so->set_description($l_cal['description']);
				$this->so->set_start($l_start['year'],$l_start['month'],$l_start['mday'],$l_start['hour'],$l_start['min'],0);
				$this->so->set_end($l_end['year'],$l_end['month'],$l_end['mday'],$l_end['hour'],$l_end['min'],0);
				$this->so->set_class($is_public);
				$this->so->add_attribute('reference',(@isset($l_cal['reference']) && $l_cal['reference']?$l_cal['reference']:0));
				$this->so->add_attribute('location',(@isset($l_cal['location']) && $l_cal['location']?$l_cal['location']:''));
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
						$l_cal['recur_data'] = intval($l_cal['rpt_sun']) + intval($l_cal['rpt_mon']) + intval($l_cal['rpt_tue']) + intval($l_cal['rpt_wed']) + intval($l_cal['rpt_thu']) + intval($l_cal['rpt_fri']) + intval($l_cal['rpt_sat']);
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
						if (($accept_type = substr($parts[$i],-1,1)) == '0' || intval($accept_type) > 0)
						{
							$accept_type = 'U';
						}
						$acct_type = $GLOBALS['phpgw']->accounts->get_type(intval($parts[$i]));
						if($acct_type == 'u')
						{
							$part[intval($parts[$i])] = $accept_type;
						}
						elseif($acct_type == 'g')
						{
							$part[intval($parts[$i])] = $accept_type;
							$groups[] = $parts[$i];
							/* This pulls ALL users of a group and makes them as participants to the event */
							/* I would like to turn this back into a group thing. */
							$acct = CreateObject('phpgwapi.accounts',intval($parts[$i]));
							$members = $acct->member(intval($parts[$i]));
							unset($acct);
							if($members == False)
							{
								continue;
							}
							while($member = each($members))
							{
								$part[$member[1]['account_id']] = $accept_type;
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
					while(list($key,$accept_type) = each($part))
					{
						$this->so->add_attribute('participants',$accept_type,intval($key));
					}
				}

				if($groups)
				{
					@reset($groups);
					$this->so->add_attribute('groups',intval($group_id));
				}

				$event = $this->get_cached_event();
				if(!is_int($minparts))
				{
					$minparts = $this->owner;
				}
				if(!@isset($event['participants'][$l_cal['owner']]))
				{
					$this->so->add_attribute('owner',$minparts);
				}
				else
				{
					$this->so->add_attribute('owner',$l_cal['owner']);
				}
				$this->so->add_attribute('priority',$l_cal['priority']);
				$event = $this->get_cached_event();

				$event['title'] = $GLOBALS['phpgw']->db->db_addslashes($event['title']);
				$event['description'] = $GLOBALS['phpgw']->db->db_addslashes($event['description']);
				$this->store_to_appsession($event);
				$datetime_check = $this->validate_update($event);

				print_debug('bo->validate_update() returnval',$datetime_check);

				if($datetime_check)
				{
				   ExecMethod('calendar.uicalendar.edit',
				   	Array(
				   		'cd'		=> $datetime_check,
				   		'readsess'	=> 1
				   	)
				   );
					exit;
				}

				if($event['id'])
				{
					$event_ids[] = $event['id'];
				}
				if($event['reference'])
				{
					$event_ids[] = $event['reference'];
				}

				$overlapping_events = $this->overlap(
					$this->maketime($event['start']),
					$this->maketime($event['end']),
					$event['participants'],
					$event['owner'],
					$event_ids
				);
			}

			if($overlapping_events)
			{
				if($send_to_ui)
				{
					unset($GLOBALS['phpgw_info']['flags']['noheader']);
					unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
					ExecMethod('calendar.uicalendar.overlap',
				   		Array(
				   			'o_events'	=> $overlapping_events,
				   			'this_event'	=> $event
				   		)
					);
					exit;
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
					print_debug('Creating a new event.');
					$this->so->cal->event = $event;
					$this->so->add_entry($event);
					$this->send_update(MSG_ADDED,$event['participants'],'',$this->get_cached_event());
					print_debug('New Event ID',$event['id']);
				}
				else
				{
					print_debug('Updating an existing event.');
					$new_event = $event;
					$old_event = $this->read_entry($event['id']);
					$this->prepare_recipients($new_event,$old_event);
					$this->so->cal->event = $event;
					$this->so->add_entry($event);
				}
				$date = sprintf("%04d%02d%02d",$event['start']['year'],$event['start']['month'],$event['start']['mday']);
				if($send_to_ui)
				{
					$this->read_sessiondata();
					if ($this->return_to)
					{
						header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction='.$this->return_to));
						$GLOBALS['phpgw']->common->phpgw_exit();
					}
					Execmethod('calendar.uicalendar.index');
//					$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
//					exit;
				}
			}
		}

		/* Private functions */
		function read_holidays($year=0)
		{
			if(!$year)
			{
				$year = $this->year;
			}
			$holiday = CreateObject('calendar.boholiday');
			$holiday->prepare_read_holidays($year,$this->owner);
			$this->cached_holidays = $holiday->read_holiday();
			unset($holiday);
		}

		function user_is_a_member($event,$user)
		{
			@reset($event['participants']);
			$uim = False;
			$security_equals = $GLOBALS['phpgw']->accounts->membership($user);
			while(!$uim && $event['participants'] && $security_equals && list($participant,$status) = each($event['participants']))
			{
				if($GLOBALS['phpgw']->accounts->get_type($participant) == 'g')
				{
					@reset($security_equals);
					while(list($key,$group_info) = each($security_equals))
					{
						if($group_info['account_id'] == $participant)
						{
							return True;
							$uim = True;
						}
					}
				}
			}
			return $uim;
		}

		function maketime($time)
		{
			return mktime($time['hour'],$time['min'],$time['sec'],$time['month'],$time['mday'],$time['year']);
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
			if (!count($event['participants']))
			{
				$error = 43;
			}
			elseif ($event['title'] == '')
			{
				$error = 40;
			}
			elseif (($GLOBALS['phpgw']->datetime->time_valid($event['start']['hour'],$event['start']['min'],0) == False) || ($GLOBALS['phpgw']->datetime->time_valid($event['end']['hour'],$event['end']['min'],0) == False))
			{
				$error = 41;
			}
			elseif (($GLOBALS['phpgw']->datetime->date_valid($event['start']['year'],$event['start']['month'],$event['start']['mday']) == False) || ($GLOBALS['phpgw']->datetime->date_valid($event['end']['year'],$event['end']['month'],$event['end']['mday']) == False) || ($GLOBALS['phpgw']->datetime->date_compare($event['start']['year'],$event['start']['month'],$event['start']['mday'],$event['end']['year'],$event['end']['month'],$event['end']['mday']) == 1))
			{
				$error = 42;
			}
			elseif ($GLOBALS['phpgw']->datetime->date_compare($event['start']['year'],$event['start']['month'],$event['start']['mday'],$event['end']['year'],$event['end']['month'],$event['end']['mday']) == 0)
			{
				if ($GLOBALS['phpgw']->datetime->time_compare($event['start']['hour'],$event['start']['min'],0,$event['end']['hour'],$event['end']['min'],0) == 1)
				{
					$error = 42;
				}
			}
			return $error;
		}

		function overlap($starttime,$endtime,$participants,$owner=0,$id=0,$restore_cache=False)
		{
//			$retval = Array();
//			$ok = False;

/* This needs some attention.. by commenting this chunk of code it will fix bug #444265 */

			if($restore_cache)
			{
				$temp_cache_events = $this->cached_events;
			}

			$temp_start = intval(date('Ymd',$starttime));
			$temp_start_time = intval(date('Hi',$starttime));
			$temp_end = intval(date('Ymd',$endtime));
			$temp_end_time = intval(date('Hi',$endtime));
			if($this->debug)
			{
				echo '<!-- Temp_Start: '.$temp_start.' -->'."\n";
				echo '<!-- Temp_End: '.$temp_end.' -->'."\n";
			}

			$users = Array();
			if(count($participants))
			{
				while(list($user,$status) = each($participants))
				{
					$users[] = $user;
				}
			}
			else
			{
				$users[] = $this->owner;
			}

			$possible_conflicts = $this->store_to_cache(
				Array(
					'smonth'	=> substr(strval($temp_start),4,2),
					'sday'	=> substr(strval($temp_start),6,2),
					'syear'	=> substr(strval($temp_start),0,4),
					'emonth'	=> substr(strval($temp_end),4,2),
					'eday'	=> substr(strval($temp_end),6,2),
					'eyear'	=> substr(strval($temp_end),0,4),
					'owner'	=> $users
				)
			);

			if($this->debug)
			{
				echo '<!-- Possible Conflicts ('.($temp_start - 1).'): '.count($possible_conflicts[$temp_start - 1]).' -->'."\n";
				echo '<!-- Possible Conflicts ('.$temp_start.'): '.count($possible_conflicts[$temp_start]).' '.count($id).' -->'."\n";
			}

			if($possible_conflicts[$temp_start] || $possible_conflicts[$temp_end])
			{
				if($temp_start == $temp_end)
				{
					if($this->debug)
					{
						echo '<!-- Temp_Start == Temp_End -->'."\n";
					}
					@reset($possible_conflicts[$temp_start]);
					while(list($key,$event) = each($possible_conflicts[$temp_start]))
					{
						$found = False;
						if($id)
						{
							@reset($id);
							while(list($key,$event_id) = each($id))
							{
								if($this->debug)
								{
									echo '<!-- $id['.$key.'] = '.$id[$key].' = '.$event_id.' -->'."\n";
									echo '<!-- '.$event['id'].' == '.$event_id.' -->'."\n";
								}
								if($event['id'] == $event_id)
								{
									$found = True;
								}
							}
						}
						if($this->debug)
						{
							echo '<!-- Item found: '.$found.' -->'."<br>\n";
						}
						if(!$found)
						{
							if($this->debug)
							{
								echo '<!-- Checking event id #'.$event['id'];
							}
							$temp_event_start = sprintf("%d%02d",$event['start']['hour'],$event['start']['min']);
							$temp_event_end = sprintf("%d%02d",$event['end']['hour'],$event['end']['min']);					
//							if((($temp_start_time <= $temp_event_start) && ($temp_end_time >= $temp_event_start) && ($temp_end_time <= $temp_event_end)) ||
							if((($temp_start_time <= $temp_event_start) && ($temp_end_time > $temp_event_start) && ($temp_end_time <= $temp_event_end)) ||
								(($temp_start_time >= $temp_event_start) && ($temp_start_time < $temp_event_end) && ($temp_end_time >= $temp_event_end)) ||
								(($temp_start_time <= $temp_event_start) && ($temp_end_time >= $temp_event_end)) ||
								(($temp_start_time >= $temp_event_start) && ($temp_end_time <= $temp_event_end)))
							{
								if($this->debug)
								{
									echo ' Conflicts';
								}
								$retval[] = $event['id'];
							}
							if($this->debug)
							{
								echo ' -->'."\n";
							}
						}
					}
				}
			}
			else
			{
				$retval = False;
			}

			if($restore_cache)
			{
				$this->cached_events = $temp_cache_events;
			}

			return $retval;
		}

		/*!
		@function check_perms( )
		@syntax check_perms($needed,$event=0,$other=0)
		@abstract Checks if the current user has the necessary ACL rights 
		@author ralfbecker
		@discussion The check is performed on an event or general on the cal of an other user
		@param $needed necessary ACL right: PHPGW_ACL_{READ|EDIT|DELETE}
		@param $event event as array or the event-id or 0 for general check
		@param $other uid to check (if event==0) or 0 to check against $this->owner
		*/
		function check_perms($needed,$event=0,$other=0)
		{
			if (is_int($event) && $event == 0)
			{
				$owner = $other > 0 ? $other : $this->owner;
			}
			else
			{
				if (!is_array($event))
				{
					$event = $this->so->read_entry((int) $event);
				}
				if (!is_array($event))
				{
					return False;
				}
				$owner = $event['owner'];
				$private = $event['public'] == False || $event['public'] == 0;
			}
			$user = $GLOBALS['phpgw_info']['user']['account_id'];
			$grants = $this->grants[$owner];

			if ($GLOBALS['phpgw']->accounts->get_type($owner) == 'g' && $needed == PHPGW_ACL_ADD)
			{
				$access = False;	// a group can't be the owner of an event
			}
			else
			{
				$access = $user == $owner || $grants & $needed && (!$private || $grants & PHPGW_ACL_PRIVATE);
			}

			return $access;
		}

		function get_fullname($accountid)
		{
			$account_id = get_account_id($accountid);
			if($GLOBALS['phpgw']->accounts->exists($account_id) == False)
			{
				return False;
			}
			$GLOBALS['phpgw']->accounts->get_account_name($account_id,$lid,$fname,$lname);
			$fullname = $lid;
			if($lname && $fname)
			{
				$fullname = $lname.', '.$fname;
			}
			return $fullname;
		}

		function display_status($user_status)
		{
			if(@$this->prefs['calendar']['display_status'] && $user_status)
			{
				$user_status = substr($this->get_long_status($user_status),0,1);

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
			if ($owner == $GLOBALS['phpgw_info']['user']['account_id'] || ($event['public']==1) || ($this->check_perms(PHPGW_ACL_PRIVATE,$event) && $event['public']==0) || $event['owner'] == $GLOBALS['phpgw_info']['user']['account_id'])
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
					if (strpos(' '.implode(',',$event['groups']).' ',$group['account_id']))
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
			if($is_private)
			{
				return 'private';
			}
//			elseif(strlen($event[$field]) > 19 && !$this->printer_friendly)
			elseif(strlen($event[$field]) > 19 && $this->printer_friendly)
			{
				return substr($event[$field], 0 , 19) . '...';
			}
			else
			{
				return $event[$field];
			}
		}

		function long_date($first,$last=0)
		{
			$datefmt = $this->prefs['common']['dateformat'];
			
			$month_before_day = $datefmt[0] == 'm' || $datefmt[2] == 'm' && $datefmt[4] == 'd';

			for ($i = 0; $i < 5; $i += 2)
			{
				switch($datefmt[$i])
				{
					case 'd':
						$range .= $first['day'] . ($datefmt[1] == '.' ? '.' : '');
						if ($first['month'] != $last['month'] || $first['year'] != $last['year'])
						{
							if (!$month_before_day)
							{
								$range .= ' '.lang(strftime('%B',$first['raw']));
							}
							if ($first['year'] != $last['year'] && $datefmt[0] != 'Y')
							{
								$range .= ($datefmt[0] != 'd' ? ', ' : ' ') . $first['year'];
							}
							if (!$last)
							{
								return $range;
							}
							$range .= ' - ';
							
							if ($first['year'] != $last['year'] && $datefmt[0] == 'Y')
							{
								$range .= $last['year'] . ', ';
							}

							if ($month_before_day)
							{
								$range .= lang(strftime('%B',$last['raw']));
							}
						}
						else
						{
							$range .= ' - ';
						}
						$range .= ' ' . $last['day'] . ($datefmt[1] == '.' ? '.' : '');
						break;
					case 'm':
						$range .= ' '.lang(strftime('%B',$month_before_day ? $first['raw'] : $last['raw'])) . ' ';
						break;
					case 'Y':
						$range .= ($datefmt[0] == 'm' ? ', ' : ' ') . ($datefmt[0] == 'Y' ? $first['year'].', ' : $last['year'].' ');
						break;
				}
			}
			return $range;
		}

		function get_week_label()
		{
			$first = $GLOBALS['phpgw']->datetime->gmtdate($GLOBALS['phpgw']->datetime->get_weekday_start($this->year, $this->month, $this->day));
			$last = $GLOBALS['phpgw']->datetime->gmtdate($first['raw'] + 518400);
         
			return ($this->long_date($first,$last));
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

		function get_exception_array($exception_str='')
		{
			$exception = Array();
			if(strpos(' '.$exception_str,','))
			{
				$exceptions = explode(',',$exception_str);
				for($exception_count=0;$exception_count<count($exceptions);$exception_count++)
				{
					$exception[] = intval($exceptions[$exception_count]);
				}
			}
			elseif($exception_str != '')
			{
				$exception[] = intval($exception_str);
			}
			return $exception;
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
			if(isset($event['recur_exception']))
			{
				$event_time = mktime($event['start']['hour'],$event['start']['min'],0,intval(substr($date,4,2)),intval(substr($date,6,2)),intval(substr($date,0,4))) - $GLOBALS['phpgw']->datetime->tz_offset;
				while($inserted == False && list($key,$exception_time) = each($event['recur_exception']))
				{
					print_debug('Checking Exception DateTime',$exception_time);
					print_debug('Checking Event     DateTime',$event_time);

					if($exception_time == $event_time)
					{
						$inserted = True;
					}
				}
			}
			if($this->cached_events[$date] && $inserted == False)
			{

				print_debug('Cached Events Found',$date);

				$year = substr($date,0,4);
				$month = substr($date,4,2);
				$day = substr($date,6,2);

				print_debug('Date',$date);
				print_debug('Count',count($this->cached_events[$date]));
				
				for($i=0;$i<count($this->cached_events[$date]);$i++)
				{
					$events = $this->cached_events[$date][$i];
					if($this->cached_events[$date][$i]['id'] == $event['id'] || $this->cached_events[$date][$i]['reference'] == $event['id'])
					{
						print_debug('Item Already Inserted!');
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
						print_debug('Adding to cached events:ID',$event['id']);
						$inserted = True;
						$this->cached_events[$date][$i] = $event;
						break;
					}
				}
			}
			if(!$inserted)
			{
				print_debug('Adding to cached events:ID',$event['id']);
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

			print_debug('Search Date Full',$search_date_full);

			$repeated = $this->repeating_events;
			$r_events = count($repeated);
			for ($i=0;$i<$r_events;$i++)
			{
				$rep_events = $this->repeating_events[$i];
				$id = $rep_events['id'];
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

				print_debug('check_repeating_events:Processing ID',$id);
				print_debug('check_repeating_events:Recurring End Date',$end_recur_date);
				
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
							if($this->debug)
							{
								echo '<!-- check_repeating_events - MCAL_RECUR_DAILY - '.$id.' -->'."\n";
							}
							if ($freq == 1 && $rep_events['recur_enddate']['month'] != 0 && $rep_events['recur_enddate']['mday'] != 0 && $rep_events['recur_enddate']['year'] != 0 && $search_date_full <= $end_recur_date)
							{
								$this->sort_event($rep_events,$search_date_full);
							}
							elseif (floor(($search_beg_day - $event_beg_day)/86400) % $freq)
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
	  
							if (($GLOBALS['phpgw']->datetime->day_of_week($rep_events['start']['year'],$rep_events['start']['month'],$rep_events['start']['mday']) == $GLOBALS['phpgw']->datetime->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
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
			$owner_id = (isset($params['owner'])?$params['owner']:0);
			if($owner_id==0 && $this->is_group)
			{
				unset($owner_id);
				$owner_id = $this->g_owner;
				print_debug('owner_id in','('.implode(',',$owner_id).')');
			}
			
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

			print_debug('Start Date',sprintf("%04d%02d%02d",$syear,$smonth,$sday));
			print_debug('End Date',sprintf("%04d%02d%02d",$eyear,$emonth,$eday));

			if($owner_id)
			{
				$cached_event_ids = $this->so->list_events($syear,$smonth,$sday,$eyear,$emonth,$eday,$owner_id);
				$cached_event_ids_repeating = $this->so->list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday,$owner_id);
			}
			else
			{
				$cached_event_ids = $this->so->list_events($syear,$smonth,$sday,$eyear,$emonth,$eday);
				$cached_event_ids_repeating = $this->so->list_repeated_events($syear,$smonth,$sday,$eyear,$emonth,$eday);
			}
			$c_cached_ids = count($cached_event_ids);
			$c_cached_ids_repeating = count($cached_event_ids_repeating);
			print_debug('Date',sprintf("%04d%02d%02d",$syear,$smonth,$sday));
			print_debug('Events Cached',$c_cached_ids);
			print_debug('Repeating Events Cached',$c_cached_ids_repeating);

			$this->cached_events = Array();

			if($c_cached_ids == 0 && $c_cached_ids_repeating == 0)
			{
				return;
			}

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
							print_debug('Date',$j);
							print_debug('Count',$c_evt_day);
							if($this->cached_events[$j][$c_evt_day]['id'] != $event['id'])
							{
								print_debug('Adding Event For Date',$j);
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
					if($this->debug)
					{
						echo '<!-- Cached Events ID: '.$cached_event_ids_repeating[$i].' ('.sprintf("%04d%02d%02d",$this->repeating_events[$i]['start']['year'],$this->repeating_events[$i]['start']['month'],$this->repeating_events[$i]['start']['mday']).') -->'."\n";
					}
				}
//				$edate -= $GLOBALS['phpgw']->datetime->tz_offset;
//				for($date=mktime(0,0,0,$smonth,$sday,$syear) - $GLOBALS['phpgw']->datetime->tz_offset;$date<=$edate;$date += 86400)
				for($date=mktime(0,0,0,$smonth,$sday,$syear);$date<=$edate;$date += 86400)
				{
					if($this->debug)
					{
//						$search_date = $GLOBALS['phpgw']->common->show_date($date,'Ymd');
						$search_date = date('Ymd',$date);
						echo '<!-- Calling check_repeating_events('.$search_date.') -->'."\n";
					}
					$this->check_repeating_events($date);
					if($this->debug)
					{
						echo '<!-- Total events found matching '.$search_date.' = '.count($this->cached_events[$search_date]).' -->'."\n";
						for($i=0;$i<count($this->cached_events[$search_date]);$i++)
						{
							echo '<!-- Date: '.$search_date.' ['.$i.'] = '.$this->cached_events[$search_date][$i]['id'].' -->'."\n";
						}
					}
				}
			}
			$retval = Array();
			for($j=date('Ymd',mktime(0,0,0,$smonth,$sday,$syear)),$k=0;$j<=date('Ymd',mktime(0,0,0,$emonth,$eday,$eyear));$k++,$j=date('Ymd',mktime(0,0,0,$smonth,$sday + $k,$syear)))
			{
				if(is_array($this->cached_events[$j]))
				{
					$retval[$j] = $this->cached_events[$j];
				}
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
		
		function add_attribute($var,$value,$index='**(**')
		{
			$this->so->add_attribute($var,$value,$index);
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
				$eventstart = $GLOBALS['phpgw']->datetime->localdates($this->maketime($event['start']) - $GLOBALS['phpgw']->datetime->tz_offset);
				$eventend = $GLOBALS['phpgw']->datetime->localdates($this->maketime($event['end']) - $GLOBALS['phpgw']->datetime->tz_offset);
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

			$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->create_email_preferences();
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
//		
//				$accounts = CreateObject('phpgwapi.accounts',$user);
//				$phpgw_info['user'] = $accounts->read_repository();
//
//				$pref = CreateObject('phpgwapi.preferences',$user);
//				$GLOBALS['phpgw_info']['user']['preferences'] = $pref->read_repository();
			}
			else
			{
				$user = $GLOBALS['phpgw_info']['user']['account_id'];
			}

			$GLOBALS['phpgw_info']['user']['preferences'] = $GLOBALS['phpgw']->preferences->create_email_preferences($user);

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
					$msgtype = '"calendar"; Version="'.$version.'"; Id="'.$new_event['id'].'"';
					break;
				case MSG_ADDED:
					$action = 'Added';
					$event_id = $new_event['id'];
					$msgtype = '"calendar"; Version="'.$version.'"; Id="'.$new_event['id'].'"';
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
				$old_event_datetime = $t_old_start_time - $GLOBALS['phpgw']->datetime->tz_offset;
			}
		
			if($new_event != False)
			{
				$new_event_datetime = $this->maketime($new_event['start']) - $GLOBALS['phpgw']->datetime->tz_offset;
				$new_event_datetime_end = $this->maketime($new_event['end']) - $GLOBALS['phpgw']->datetime->tz_offset;
			}

 			//Added to construct the participant's list to an event
 			$event_participants = '';
 			reset($participants);
 			$ac=CreateObject('phpgwapi.accounts');
 
 			while(list($userid,$statid)=each($participants))
 			{
 				$event_participants .= ($event_participants?"\n":'');
 				$ac->account_id=$userid;
 				$ac->read_repository();
 				$event_participants .= '<'.$ac->data['account_lid'].'> '.$ac->data['fullname'];
 			}
 			//End

 			reset($participants);
			while($participants && list($userid,$statusid) = each($participants))
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
					print_debug('Msg Type',$msg_type);
					print_debug('UserID',$userid);
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
					$part_prefs = $preferences->create_email_preferences(intval($userid));
					$to = $part_prefs['email']['address'];

					print_debug('Email being sent to',$to);

					$GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset'] = $part_prefs['common']['tz_offset'];
					$GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] = $part_prefs['common']['timeformat'];
					$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'] = $part_prefs['common']['dateformat'];
				
					$GLOBALS['phpgw']->datetime->tz_offset = ((60 * 60) * intval($GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset']));

					if($old_event != False)
					{
						$old_event_date = $GLOBALS['phpgw']->common->show_date($old_event_datetime);
					}
				
					if($new_event != False)
					{
						$new_event_date = $GLOBALS['phpgw']->common->show_date($new_event_datetime);
						$new_event_end = $GLOBALS['phpgw']->common->show_date($new_event_datetime_end);
					}
				
					switch($msg_type)
					{
						case MSG_DELETED:
							$action_date = $old_event_date;
							$body = lang ('Your meeting scheduled for') .' '. $old_event_date .' '. lang('has been canceled');
 							$event_head=$old_event['title'];
 							$event_description=$old_event['description'];
							break;
						case MSG_MODIFIED:
							$action_date = $new_event_date;
							$body = lang ('Your meeting that had been scheduled for').' '.$old_event_date.' '. lang('has been rescheduled to') .' '.$new_event_date;
 							$event_head=$old_event['title'];
 							$event_description=$old_event['description'];
							break;
						case MSG_ADDED:
							$action_date = $new_event_date;
							$body = lang ('You have a meeting scheduled for').' '. $new_event_date;
 							$event_head=$new_event['title'];
 							$event_description=$new_event['description'];
							break;
						case MSG_REJECTED:
						case MSG_TENTATIVE:
						case MSG_ACCEPTED:
							$action_date = $old_event_date;
							$body = 'On '.$GLOBALS['phpgw']->common->show_date(time() - $GLOBALS['phpgw']->datetime->tz_offset).' '.$GLOBALS['phpgw']->common->grab_owner_name($GLOBALS['phpgw_info']['user']['account_id']).' '.$action.' your meeting request for '.$old_event_date;
							$body = lang('On %1 %2 %3 your meeting request for %4',$GLOBALS['phpgw']->common->show_date(time() - $GLOBALS['phpgw']->datetime->tz_offset),$GLOBALS['phpgw']->common->grab_owner_name($GLOBALS['phpgw_info']['user']['account_id']),lang($action),$old_event_date);
 							$event_head=$old_event['title'];
 							$event_description=$old_event['description'];
							break;
					}

					$subject = lang('Calendar Event') . ' ('. lang($action) .') #'.$event_id.': '.$action_date.' (L)';
					if(isset($part_prefs['calendar']['send_extra']) && $part_prefs['calendar']['send_extra'])
 					{
						$body .= "\n\n".'***'.lang('Please confirm,accept,reject or examine changes in the corresponding entry in your calendar').'***'."\n\n"
							. '----'.lang('Event Details Follow').'----';
						$body .= ($new_event_date ? "\n\n".lang('Start- and Enddates').":\n".$new_event_date.' -- '. $new_event_end : '');
						$body .= ($event_head?"\n\n".lang('TITLE').':'."\n".'        '.$event_head:'');
						$body .= ($event_description?"\n\n".lang('DESCRIPTION').':'."\n".'        '.$event_description:'');
						$body .= ($event_participants?"\n\n".lang('Participants').':'."\n".'        '.$event_participants:'');
 					}
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
			$GLOBALS['phpgw']->datetime->tz_offset = ((60 * 60) * $temp_tz_offset);
			$GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat'] = $temp_timeformat;
			$GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'] = $temp_dateformat;
		}

		function get_alarms($event_id)
		{
			return $this->so->get_alarm($event_id);
		}

		function alarm_today($event,$today,$starttime)
		{
			$found = False;
			@reset($event['alarm']);
			$starttime_hi = $GLOBALS['phpgw']->common->show_date($starttime,'Hi');
			$t_appt['month'] =$GLOBALS['phpgw']->common->show_date($today,'m');
			$t_appt['mday'] = $GLOBALS['phpgw']->common->show_date($today,'d');
			$t_appt['year'] = $GLOBALS['phpgw']->common->show_date($today,'Y');
			$t_appt['hour'] = $GLOBALS['phpgw']->common->show_date($starttime,'H');
			$t_appt['min']  = $GLOBALS['phpgw']->common->show_date($starttime,'i');
			$t_appt['sec']  = 0;
			$t_time = $this->maketime($t_appt) - $GLOBALS['phpgw']->datetime->tz_offset;
			$y_time = $t_time - 86400;
			$tt_time = $t_time + 86399;
			print_debug('T_TIME',$t_time.' : '.$GLOBALS['phpgw']->common->show_date($t_time));
			print_debug('Y_TIME',$y_time.' : '.$GLOBALS['phpgw']->common->show_date($y_time));
			print_debug('TT_TIME',$tt_time.' : '.$GLOBALS['phpgw']->common->show_date($tt_time));
			while(list($key,$alarm) = each($event['alarm']))
			{
				if($alarm['enabled'])
				{
					print_debug('TIME',$alarm['time'].' : '.$GLOBALS['phpgw']->common->show_date($alarm['time']).' ('.$event['id'].')');
					if($event['recur_type'] != MCAL_RECUR_NONE)   /* Recurring Event */
					{
						print_debug('Recurring Event');
						if($alarm['time'] > $y_time && $GLOBALS['phpgw']->common->show_date($alarm['time'],'Hi') < $starttime_hi && $alarm['time'] < $t_time)
						{
							$found = True;
						}
					}
					elseif($alarm['time'] > $y_time && $alarm['time'] < $t_time)
					{
						$found = True;
					}
				}
			}
			print_debug('Found',$found);
			return $found;
		}
		
		function prepare_recipients(&$new_event,$old_event)
		{
			// Find modified and deleted users.....
			while(list($old_userid,$old_status) = each($old_event['participants']))
			{
				if(isset($new_event['participants'][$old_userid]))
				{
					print_debug('Modifying event for user',$old_userid);
					$this->modified[intval($old_userid)] = $new_status;
				}
				else
				{
					print_debug('Deleting user from the event',$old_userid);
					$this->deleted[intval($old_userid)] = $old_status;
				}
			}
			// Find new users.....
			while(list($new_userid,$new_status) = each($new_event['participants']))
			{
				if(!isset($old_event['participants'][$new_userid]))
				{
					print_debug('Adding event for user',$new_userid);
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
			$has_category  = Array(); // remove only multiple occurences of a category per event/day
			for($v=$firstday;$v<=$lastday;$v++)
			{
				if (!$this->cached_events[$v])
				{
					continue;
				}
				while (list($g,$event) = each($this->cached_events[$v]))
				{
					$start = sprintf('%04d%02d%02d',$event['start']['year'],$event['start']['month'],$event['start']['mday']);
					print_debug('EVENT',_debug_array($event,False));
					print_debug('start',$start);
					print_debug('v',$v);

					if($start < $firstday)
					{
						$start = $firstday; // event continues into current month/year
					}

//					if ($start != $v && $event['recur_type'] == MCAL_RECUR_NONE)							// this is an enddate-entry --> remove it
					if ($start != $v)							// this is an enddate-entry --> remove it
					{
						unset($this->cached_events[$v][$g]);
						if($g != count($this->cached_events[$v]))
						{
							if ($has_category[$event['id']]['category'] != True)
							{
								continue; // we need at least one evidence for this category
							}
							for($h=$g + 1;$h<$c_daily;$h++)
							{
								$this->cached_events[$v][$h - 1] = $this->cached_events[$v][$h];
							}
							unset($this->cached_events[$v][$h]);
							$has_category[$event['id']]['category'] = True;
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
								print_debug('Event moved');
							}
							else
							{
								$already_moved[$event['id']] = 2;
								print_debug('Event removed (not moved)');
							}
						}
						else
						{
							print_debug('Event removed');
						}
					}
					else
					{
						print_debug('Event OK');
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
