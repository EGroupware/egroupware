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

		function bocalendar($session=0)
		{
			$this->grants = $GLOBALS['phpgw']->acl->get_grants('calendar');

			if($this->debug) { echo '<!-- Read Use_Session : ('.$session.') -->'."\n"; }

			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
			}

			if($this->debug)
			{
				echo '<!-- BO Filter : ('.$this->filter.') -->'."\n";
				echo '<!-- Owner : '.$this->owner.' -->'."\n";
			}
			
			$owner = (isset($GLOBALS['owner'])?$GLOBALS['owner']:'');
			$owner = (isset($GLOBALS['HTTP_GET_VARS']['owner'])?$GLOBALS['HTTP_GET_VARS']['owner']:$owner);
			$owner = ($owner=='' && isset($GLOBALS['HTTP_POST_VARS']['owner'])?$GLOBALS['HTTP_POST_VARS']['owner']:$owner);

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
				$this->set_owner_to_group($this->owner);
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

			$friendly = (isset($GLOBALS['HTTP_GET_VARS']['friendly'])?$GLOBALS['HTTP_GET_VARS']['friendly']:'');
			$friendly = ($friendly=='' && isset($GLOBALS['HTTP_POST_VARS']['friendly'])?$GLOBALS['HTTP_POST_VARS']['friendly']:$friendly);

			$this->printer_friendly = (intval($friendly) == 1?True:False);

			if(isset($GLOBALS['HTTP_POST_VARS']['filter']))   { $this->filter = $GLOBALS['HTTP_POST_VARS']['filter']; }
			if(isset($GLOBALS['HTTP_POST_VARS']['cat_id']))  { $this->cat_id = $GLOBALS['HTTP_POST_VARS']['cat_id']; }

			if(!isset($this->filter))
			{
				$this->filter = ' '.$this->prefs['calendar']['defaultfilter'].' ';
			}

			$date = (isset($GLOBALS['date'])?$GLOBALS['date']:'');
			$date = (isset($GLOBALS['HTTP_GET_VARS']['date'])?$GLOBALS['HTTP_GET_VARS']['date']:$date);
			$date = ($date=='' && isset($GLOBALS['HTTP_POST_VARS']['date'])?$GLOBALS['HTTP_POST_VARS']['date']:$date);

			$year = (isset($GLOBALS['HTTP_GET_VARS']['year'])?$GLOBALS['HTTP_GET_VARS']['year']:'');
			$year = ($year=='' && isset($GLOBALS['HTTP_POST_VARS']['year'])?$GLOBALS['HTTP_POST_VARS']['year']:$year);
			
			$month = (isset($GLOBALS['HTTP_GET_VARS']['month'])?$GLOBALS['HTTP_GET_VARS']['month']:'');
			$month = ($month=='' && isset($GLOBALS['HTTP_POST_VARS']['month'])?$GLOBALS['HTTP_POST_VARS']['month']:$month);
			
			$day = (isset($GLOBALS['HTTP_GET_VARS']['day'])?$GLOBALS['HTTP_GET_VARS']['day']:'');
			$day = ($day=='' && isset($GLOBALS['HTTP_POST_VARS']['day'])?$GLOBALS['HTTP_POST_VARS']['day']:'');
			
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
				elseif($this->year == 0)
				{
					$this->year = date('Y',time());
				}
				if(isset($month) && $month!='')
				{
					$this->month = $month;
				}
				elseif($this->month == 0)
				{
					$this->month = date('m',time());
				}
				if(isset($day) && $day!='')
				{
					$this->day = $day;
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
					'category'	=> $this->cat_id,
					'g_owner'	=> $this->g_owner
				)
			);
			$this->datetime = $this->so->datetime;
			
			$this->today = date('Ymd',time());

			if($this->debug)
			{
				echo '<!-- BO Filter : ('.$this->filter.') -->'."\n";
				echo '<!-- Owner : '.$this->owner.' -->'."\n";
			}
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
			
			if($this->check_perms(PHPGW_ACL_DELETE))
			{
				$temp_event = $this->get_cached_event();
			   $event = $this->read_entry(intval($param['id']));
			   if($this->owner == $event['owner'])
			   {
			   	$exception_time = mktime($event['start']['hour'],$event['start']['min'],0,$param['month'],$param['day'],$param['year']) - $this->datetime->tz_offset;
			   	$event['recur_exception'][] = intval($exception_time);
			   	$this->so->cal->event = $event;
			   	if($this->debug)
			   	{
				   	echo '<!-- exception time = '.$event['recur_exception'][count($event['recur_exception']) -1].' -->'."\n";
				   	echo '<!-- count event exceptions = '.count($event['recur_exception']).' -->'."\n";
				   }
   				$this->so->add_entry($event);
   				$cd = 16;
   			}
   			else
   			{
   			   $cd = 60;
   			}
			}
			$this->so->cal->event = $temp_event;
			unset($temp_event);
			return $cd;
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

		function reinstate($params='')
		{
			if($this->check_perms(PHPGW_ACL_EDIT) && isset($params['cal_id']) && isset($params['reinstate_index']))
			{
				$event = $this->so->read_entry($params['cal_id']);
				@reset($params['reinstate_index']);
				echo '<!-- Count of reinstate_index = '.count($params['reinstate_index']).' -->'."\n";
				if(count($params['reinstate_index']) > 1)
				{
					while(list($key,$value) = each($params['reinstate_index']))
					{
						if($this->debug)
						{
							echo '<!-- reinstate_index ['.$key.'] = '.intval($value).' -->'."\n";
							echo '<!-- exception time = '.$event['recur_exception'][intval($value)].' -->'."\n";
						}
						unset($event['recur_exception'][intval($value)]);
						if($this->debug)
						{
							echo '<!-- count event exceptions = '.count($event['recur_exception']).' -->'."\n";
						}
				 	}
				}
				else
				{
			   	if($this->debug)
			   	{
		   			echo '<!-- reinstate_index [0] = '.intval($params['reinstate_index'][0]).' -->'."\n";
			   		echo '<!-- exception time = '.$event['recur_exception'][intval($params['reinstate_index'][0])].' -->'."\n";
				   }
					unset($event['recur_exception'][intval($params['reinstate_index'][0])]);
			   	if($this->debug)
			   	{
				   	echo '<!-- count event exceptions = '.count($event['recur_exception']).' -->'."\n";
			   	}
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

			if($this->debug)
			{
				echo '<!-- ID : '.$l_cal['id'].' -->'."\n";
			}

         if(isset($GLOBALS['HTTP_GET_VARS']['readsess']))
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
				$GLOBALS['phpgw']->common->phpgw_exit(True);
				}
				$overlapping_events = False;
         }
         else
			{
   			if((!$l_cal['id'] && !$this->check_perms(PHPGW_ACL_ADD)) || ($l_cal['id'] && !$this->check_perms(PHPGW_ACL_EDIT)))
	   		{
	   		   ExecMethod('calendar.uicalendar.index');
					$GLOBALS['phpgw']->common->phpgw_exit();
	   		}

				if($this->debug)
				{
					echo '<!-- Prior to fix_update_time() -->'."\n";
				}
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
						$acct_type = $GLOBALS['phpgw']->accounts->get_type(intval($parts[$i]));
						if($acct_type == 'u')
						{
							$part[$parts[$i]] = 1;
						}
						elseif($acct_type == 'g')
						{
							$part[$parts[$i]] = 1;
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
				if($this->debug)
				{
					echo '<!-- bo->validate_update() returned : '.$datetime_check.' -->'."\n";
				}
				if($datetime_check)
				{
				   ExecMethod('calendar.uicalendar.edit',
				   	Array(
				   		'cd'		=> $datetime_check,
				   		'readsess'	=> 1
				   	)
				   );
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
					$GLOBALS['phpgw']->common->phpgw_exit(True);
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
					if($this->debug)
					{
						echo '<!-- Creating a new event. -->'."\n";
					}
					$this->so->cal->event = $event;
					$this->so->add_entry($event);
					$this->send_update(MSG_ADDED,$event['participants'],'',$this->get_cached_event());
					if($this->debug)
					{
						echo '<!-- New event ID = '.$event['id'].' -->'."\n";
					}
				}
				else
				{
					if($this->debug)
					{
						echo '<!-- Updating an existing event. -->'."\n";
					}
					$new_event = $event;
					$old_event = $this->read_entry($event['id']);
					$this->prepare_recipients($new_event,$old_event);
					$this->so->cal->event = $event;
					$this->so->add_entry($event);
				}
				$date = sprintf("%04d%02d%02d",$event['start']['year'],$event['start']['month'],$event['start']['mday']);
				if($send_to_ui)
				{
					Execmethod('calendar.uicalendar.index');
					$GLOBALS['phpgw']->common->phpgw_exit();
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

		function can_user_edit($event)
		{
			$can_edit = False;
		
			if(($event['owner'] == $this->owner) && ($this->check_perms(PHPGW_ACL_EDIT) == True))
			{
				if($event['public'] == False || $event['public'] == 0)
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
			if (!count($event['participants']))
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

		function overlap($starttime,$endtime,$participants,$owner=0,$id=0,$restore_cache=False)
		{
//			$retval = Array();
//			$ok = False;

/* This needs some attention.. by commenting this chunk of code it will fix bug #444265 */

			if($restore_cache)
			{
				$temp_cache_events = $this->cached_events;
			}

//			$temp_start = intval($GLOBALS['phpgw']->common->show_date($starttime,'Ymd'));
//			$temp_start_time = intval($GLOBALS['phpgw']->common->show_date($starttime,'Hi'));
//			$temp_end = intval($GLOBALS['phpgw']->common->show_date($endtime,'Ymd'));
//			$temp_end_time = intval($GLOBALS['phpgw']->common->show_date($endtime,'Hi'));
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
							if((($temp_start_time <= $temp_event_start) && ($temp_end_time >= $temp_event_start) && ($temp_end_time <= $temp_event_end)) ||
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

//			if($starttime == $endtime && $GLOBALS['phpgw']->common->show_date($starttime,'Hi') == 0)
//			{
//				$endtime = mktime(23,59,59,$GLOBALS['phpgw']->common->show_date($starttime,'m'),$GLOBALS['phpgw']->common->show_date($starttime,'d') + 1,$GLOBALS['phpgw']->common->show_date($starttime,'Y')) - $this->datetime->tz_offset;
//			}
//
//	-		$sql = 'AND ((('.$starttime.' <= phpgw_cal.datetime) AND ('.$endtime.' >= phpgw_cal.datetime) AND ('.$endtime.' <= phpgw_cal.edatetime)) '
//					.  'OR (('.$starttime.' >= phpgw_cal.datetime) AND ('.$starttime.' < phpgw_cal.edatetime) AND ('.$endtime.' >= phpgw_cal.edatetime)) '
//	-				.  'OR (('.$starttime.' <= phpgw_cal.datetime) AND ('.$endtime.' >= phpgw_cal.edatetime)) '
//	-				.  'OR (('.$starttime.' >= phpgw_cal.datetime) AND ('.$endtime.' <= phpgw_cal.edatetime))) ';
//
//			if(count($participants) > 0)
//			{
//				$p_g = '';
//				if(count($participants))
//				{
//					$users = Array();
//					while(list($user,$status) = each($participants))
//					{
//						$users[] = $user;
//					}
//					if($users)
//					{
//						$p_g .= 'phpgw_cal_user.cal_login IN ('.implode(',',$users).')';
//					}
//				}
//				if($p_g)
//				{
//					$sql .= ' AND (' . $p_g . ')';
//				}
//			}
//
//			if(count($id) >= 1)
//			{
//				@reset($id);
//				$sql .= ' AND phpgw_cal.cal_id NOT IN ('.(count($id)==1?$id[0]:implode(',',$id)).')';
//			}
//
//			$sql .= ' ORDER BY phpgw_cal.datetime ASC, phpgw_cal.edatetime ASC, phpgw_cal.priority ASC';
//
//			$events = $this->so->get_event_ids(False,$sql);
//			if($events == False)
//			{
//				return false;
//			}
//		
//			$db2 = $GLOBALS['phpgw']->db;
//
//			for($i=0;$i<count($events);$i++)
//			{
//				$db2->query('SELECT recur_type FROM phpgw_cal_repeats WHERE cal_id='.$events[$i],__LINE__,__FILE__);
//				if($db2->num_rows() == 0)
//				{
//					$retval[] = $events[$i];
//					$ok = True;
//				}
//				else
//				{
//					$db2->next_record();
//					if($db2->f('recur_type') <> MCAL_RECUR_MONTHLY_MDAY)
//					{
//						$retval[] = $events[$i];
//						$ok = True;
//					}
//				}
//			}
//			if($ok == True)
//			{
//				return $retval;
//			}
//			else
//			{
//				return False;
//			}
		}

		function check_perms($needed,$user=0)
		{
			if($user == 0)
			{
				return !!($this->grants[$this->owner] & $needed);
			}
			else
			{
				return !!($this->grants[intval($user)] & $needed);
			}
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
			if ($owner == $GLOBALS['phpgw_info']['user']['account_id'] || ($event['public']==1) || ($this->check_perms(PHPGW_ACL_PRIVATE,$owner) && $event['public']==0) || $event['owner'] == $GLOBALS['phpgw_info']['user']['account_id'])
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
				$event_time = mktime($event['start']['hour'],$event['start']['min'],0,intval(substr($date,4,2)),intval(substr($date,6,2)),intval(substr($date,0,4))) - $this->datetime->tz_offset;
				while($inserted == False && list($key,$exception_time) = each($event['recur_exception']))
				{
					echo '<!-- checking exception datetime '.$exception_time.' to event datetime '.$event_time.' -->'."\n";
					if($exception_time == $event_time)
					{
						$inserted = True;
					}
				}
			}
			if($this->cached_events[$date] && $inserted == False)
			{
				
				if($this->debug)
				{
					echo '<!-- Cached Events found for '.$date.' -->'."\n";
				}
				$year = substr($date,0,4);
				$month = substr($date,4,2);
				$day = substr($date,6,2);

				if($this->debug)
				{
					echo '<!-- Date : '.$date.' Count : '.count($this->cached_events[$date]).' -->'."\n";
				}
				
				for($i=0;$i<count($this->cached_events[$date]);$i++)
				{
					$events = $this->cached_events[$date][$i];
					if($this->cached_events[$date][$i]['id'] == $event['id'] || $this->cached_events[$date][$i]['reference'] == $event['id'])
					{
						if($this->debug)
						{
							echo '<!-- Item already inserted! -->'."\n";
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
						if($this->debug)
						{
							echo '<!-- Adding event ID: '.$event['id'].' to cached_events -->'."\n";
						}
						$inserted = True;
						$this->cached_events[$date][$i] = $event;
						break;
					}
				}
			}
			if(!$inserted)
			{
				if($this->debug)
				{
					echo '<!-- Adding event ID: '.$event['id'].' to cached_events -->'."\n";
				}
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
			if($this->debug)
			{
				echo '<!-- Search Date Full = '.$search_date_full.' -->'."\n";
			}
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

				if($this->debug)
				{
					echo '<!-- check_repeating_events - Processing ID - '.$id.' -->'."\n";
					echo '<!-- check_repeating_events - Recurring End Date - '.$end_recur_date.' -->'."\n";
				}

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
			$owner_id = (isset($params['owner'])?$params['owner']:0);
			if($owner_id==0 && $this->is_group)
			{
				unset($owner_id);
				$owner_id = $this->g_owner;
				if($this->debug)
				{
					echo '<!-- owner_id in ('.implode($owner_id,',').') -->'."\n";
				}
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
			
			if($this->debug)
			{
				echo '<!-- Start Date : '.sprintf("%04d%02d%02d",$syear,$smonth,$sday).' -->'."\n";
				echo '<!-- End   Date : '.sprintf("%04d%02d%02d",$eyear,$emonth,$eday).' -->'."\n";
			}

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

			if($this->debug)
			{
				echo '<!-- events cached : '.$c_cached_ids.' : for : '.sprintf("%04d%02d%02d",$syear,$smonth,$sday).' -->'."\n";
				echo '<!-- repeating events cached : '.$c_cached_ids_repeating.' : for : '.sprintf("%04d%02d%02d",$syear,$smonth,$sday).' -->'."\n";
			}

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
							if($this->debug)
							{
								echo '<!-- Date: '.$j.' Count : '.$c_evt_day.' -->'."\n";
							}
							if($this->cached_events[$j][$c_evt_day]['id'] != $event['id'])
							{
								if($this->debug)
								{
									echo '<!-- Adding Event for Date: '.$j.' -->'."\n";
								}
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
//				$edate -= $this->datetime->tz_offset;
//				for($date=mktime(0,0,0,$smonth,$sday,$syear) - $this->datetime->tz_offset;$date<=$edate;$date += 86400)
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
				$old_event_datetime = $t_old_start_time - $this->datetime->tz_offset;
			}
		
			if($new_event != False)
			{
				$new_event_datetime = $this->maketime($new_event['start']) - $this->datetime->tz_offset;
			}

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
					if($this->debug)
					{
						echo '<!-- Msg Type = '.$msg_type.' -->'."\n";
						echo '<!-- userid = '.$userid.' -->'."\n";
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
					$part_prefs = $preferences->create_email_preferences(intval($userid));
					$to = $part_prefs['email']['address'];
					
					if($this->debug)
					{
						echo '<!-- Email being sent to: '.$to.' -->'."\n";
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
							$body = 'On '.$GLOBALS['phpgw']->common->show_date(time() - $new_tz_offset).' '.$GLOBALS['phpgw']->common->grab_owner_name($GLOBALS['phpgw_info']['user']['account_id']).' '.$action.' your meeting request for '.$old_event_date;
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
			$t_time = $this->maketime($t_appt) - $this->datetime->tz_offset;
			$y_time = $t_time - 86400;
			$tt_time = $t_time + 86399;
//echo 'T_TIME : '.$t_time."<br>\n";
//echo 'Y_TIME : '.$y_time."<br>\n";
//echo 'TT_TIME : '.$tt_time."<br>\n";
			while(list($key,$alarm) = each($event['alarm']))
			{
//echo 'TIME : '.$alarm['time']."<br>\n";
				if($event['recur_type'] != MCAL_RECUR_NONE)   /* Recurring Event */
				{
					if($alarm['time'] > $y_time && $GLOBALS['phpgw']->common->show_date($alarm['time'],'Hi') < $starttime_hi && $alarm['time'] < $t_time)
					{
						$found = True;
					}
				}
				elseif($GLOBALS['phpgw']->common->show_date($alarm['time'],'Hi') < $starttime_hi)
				{
					$found = True;
				}
			}
			return $found;
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
						echo '<!-- Modifying event for user '.$old_userid.' -->'."\n";
					}
					$this->modified[intval($old_userid)] = $new_status;
				}
				else
				{
					if($this->debug)
					{
						echo '<!-- Deleting user '.$old_userid.' from the event -->'."\n";
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
						echo '<!-- Adding event for user '.$new_userid.' -->'."\n";
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
						echo '<!-- start='.$start.', v='.$v.' ';
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
									echo 'moved --> '."\n";
								}
							}
							else
							{
								$already_moved[$event['id']] = 2;
								if($this->debug)
								{
									echo 'removed (not moved) -->'."\n";
								}
							}
       				}
						elseif($this->debug)
						{
							echo 'removed -->'."\n";
						}
					}
					elseif($this->debug)
					{
						echo 'ok -->'."\n";
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
