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

include(PHPGW_SERVER_ROOT.'/calendar/version.inc.php');

if(extension_loaded('mcal') == False)
{
	define('RECUR_NONE',0);
	define('RECUR_DAILY',1);
	define('RECUR_WEEKLY',2);
	define('RECUR_MONTHLY_MDAY',3);
	define('RECUR_MONTHLY_WDAY',4);
	define('RECUR_YEARLY',5);
	
	define('M_SUNDAY',1);
	define('M_MONDAY',2);
	define('M_TUESDAY',4);
	define('M_WEDNESDAY',8);
	define('M_THURSDAY',16);
	define('M_FRIDAY',32);
	define('M_SATURDAY',64);
	
	define('M_WEEKDAYS',63);
	define('M_WEEKEND',65);
	define('M_ALLDAYS',127);
}

define('MSG_DELETED',0);
define('MSG_MODIFIED',1);
define('MSG_ADDED',2);
define('MSG_REJECTED',3);
define('MSG_TENTATIVE',4);
define('MSG_ACCEPTED',5);

define('REJECTED',0);
define('NO_RESPONSE',1);
define('TENTATIVE',2);
define('ACCEPTED',3);

class calendar__
{
	var $event;
	var $stream;
	var $user;
	var $users_status;
	var $modified;
	var $deleted;
	var $added;

	function send_update($msg_type,$participants,$old_event=False,$new_event=False)
	{
		global $phpgw, $phpgw_info;
		
		$phpgw_info['user']['preferences'] = $phpgw->common->create_emailpreferences($phpgw_info['user']['preferences']);
		$sender = $phpgw_info['user']['preferences']['email']['address'];

		$temp_tz_offset = $phpgw_info['user']['preferences']['common']['tz_offset'];
		$temp_timeformat = $phpgw_info['user']['preferences']['common']['timeformat'];
		$temp_dateformat = $phpgw_info['user']['preferences']['common']['dateformat'];

		$tz_offset = ((60 * 60) * intval($temp_tz_offset));

		$t_old_start_time = mktime($old_event->start->hour,$old_event->start->min,$old_event->start->sec,$old_event->start->month,$old_event->start->mday,$old_event->start->year);
		if($t_old_start_time < (time() - 86400))
		{
			return False;
		}

		$temp_user = $phpgw_info['user'];

		if((is_int($this->user) && $this->user != $temp_user['account_id']) ||
			(is_string($this->user) && $this->user != $temp_user['account_lid']))
		{
			if(is_string($this->user))
			{
				$user = $phpgw->accounts->name2id($this->user);
			}
			elseif(is_int($this->user))
			{
				$user = $this->user;
			}
		
			$accounts = CreateObject('phpgwapi.accounts',$user);
			$phpgw_info['user'] = $accounts->read_repository();

			$pref = CreateObject('phpgwapi.preferences',$user);
			$phpgw_info['user']['preferences'] = $pref->read_repository();
		}
		else
		{
			$user = $phpgw_info['user']['account_id'];
		}

		$phpgw_info['user']['preferences'] = $phpgw->common->create_emailpreferences($phpgw_info['user']['preferences'],$user);

		$send = CreateObject('phpgwapi.send');

		switch($msg_type)
		{
			case MSG_DELETED:
				$action = 'Deleted';
				$event_id = $old_event->id;
				$msgtype = '"calendar";';
				break;
			case MSG_MODIFIED:
				$action = 'Modified';
				$event_id = $old_event->id;
				$msgtype = '"calendar"; Version="'.$phpgw_info['server']['versions']['calendar'].'"; Id="'.$new_event->id.'"';
				break;
			case MSG_ADDED:
				$action = 'Added';
				$event_id = $old_event->id;
				$msgtype = '"calendar"; Version="'.$phpgw_info['server']['versions']['calendar'].'"; Id="'.$new_event->id.'"';
				break;
			case MSG_REJECTED:
				$action = 'Rejected';
				$event_id = $old_event->id;
				$msgtype = '"calendar";';
				break;
			case MSG_TENTATIVE:
				$action = 'Tentative';
				$event_id = $old_event->id;
				$msgtype = '"calendar";';
				break;
			case MSG_ACCEPTED:
				$action = 'Tentative';
				$event_id = $old_event->id;
				$msgtype = '"calendar";';
				break;
		}

		if($old_event != False)
		{
			$old_event_datetime = $t_old_start_time - $tz_offset;
		}
		
		if($new_event != False)
		{
			$new_event_datetime = mktime($new_event->start->hour,$new_event->start->min,$new_event->start->sec,$new_event->start->month,$new_event->start->mday,$new_event->start->year) - $tz_offset;
		}

		for($i=0;$i<count($participants);$i++)
		{
			if($participants[$i] != $phpgw_info['user']['account_id'])
			{
				$preferences = CreateObject('phpgwapi.preferences',$participants[$i]);
				$part_prefs = $preferences->read_repository();
				if(!isset($part_prefs['calendar']['send_updates']) || !$part_prefs['calendar']['send_updates'])
				{
					continue;
				}
				$part_prefs = $phpgw->common->create_emailpreferences($part_prefs,$participants[$i]);
				$to = $part_prefs['email']['address'];

				$phpgw_info['user']['preferences']['common']['tz_offset'] = $part_prefs['common']['tz_offset'];
				$phpgw_info['user']['preferences']['common']['timeformat'] = $part_prefs['common']['timeformat'];
				$phpgw_info['user']['preferences']['common']['dateformat'] = $part_prefs['common']['dateformat'];
				
				$new_tz_offset = ((60 * 60) * intval($phpgw_info['user']['preferences']['common']['tz_offset']));

				if($old_event != False)
				{
					$old_event_date = $phpgw->common->show_date($old_event_datetime);
				}
				
				if($new_event != False)
				{
					$new_event_date = $phpgw->common->show_date($new_event_datetime);
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
			$phpgw_info['user'] = $temp_user;
		}

		$phpgw_info['user']['preferences']['common']['tz_offset'] = $temp_tz_offset;
		$phpgw_info['user']['preferences']['common']['timeformat'] = $temp_timeformat;
		$phpgw_info['user']['preferences']['common']['dateformat'] = $temp_dateformat;
	}

	function prepare_recipients(&$new_event,$old_event)
	{
		$old_part_count = count($old_event->participants);
		for($i=0;$i<$old_part_count;$i++)
		{
			$delete = True;
			$new_part_count = count($new_event->participants);
			for($k=0;$k<$new_part_count;$k++)
			{
				if($new_event->participants[$k] == $old_event->participants[$i])
				{
					$delete = False;
					$this->modified[] = $new_event->participants[$k];
					$new_event->status[$k] = $old_event->status[$i];
				}
			}
			if($delete == True)
			{
				$this->deleted[] = $old_event->participants[$i];
			}
		}
		$new_part_count = count($new_event->participants);
		for($i=0;$i<$new_part_count;$i++)
		{
			$add = True;
			$old_part_count = count($old_event->participants);
			for($k=0;$k<$old_part_count;$k++)
			{
				if($new_event->participants[$i] == $old_event->participants[$k])
				{
					$add = False;
				}
			}
			if($add == True)
			{
				$this->added[] = $new_event->participants[$i];
				$new_event->status[$i] = 'U';
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

	function set_common_recur($year,$month,$day,$interval)
	{
		$this->event->recur_interval = intval($interval);
		if(intval($day) == 0 && intval($month) == 0 && intval($year) == 0)
		{
			$this->event->recur_enddate->year = 0;
			$this->event->recur_enddate->month = 0;
			$this->event->recur_enddate->mday = 0;
		}
		else
		{
			$this->event->recur_enddate->year = intval($year);
			$this->event->recur_enddate->month = intval($month);
			$this->event->recur_enddate->mday = intval($day);
		}
		$this->event->recur_enddate->hour = 0;
		$this->event->recur_enddate->min = 0;
		$this->event->recur_enddate->sec = 0;
		$this->event->recur_enddate->alarm = 0;
		$this->event->recur_data = 0;
	}
}
