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

	define('PHPGW_ACL_DELETEALARM',PHPGW_ACL_DELETE);	// for now
	define('PHPGW_ACL_SETALARM',PHPGW_ACL_WRITE);
	define('PHPGW_ACL_READALARM',PHPGW_ACL_READ);

	class boalarm
	{
		var $so;
		var $cal;
		var $cal_id;

		var $tz_offset;

		var $debug = False;
//		var $debug = True;

		var $public_functions = array(
			'add'       => True,
			'delete'    => True
		);

		function boalarm()
		{
			$cal_id = (isset($_POST['cal_id'])?intval($_POST['cal_id']):'');
			if($cal_id)
			{
				$this->cal_id = $cal_id;
			}
			$this->bo = CreateObject('calendar.bocalendar',1);
			$this->so = CreateObject('calendar.socalendar',1);
			$this->tz_offset = $this->bo->datetime->tz_offset;

			if($this->debug)
			{
				echo "BO Owner : ".$this->bo->owner."<br>\n";
			}

			if($this->bo->use_session)
			{
				$this->save_sessiondata();
			}
		}

		function save_sessiondata()
		{
			$data = array(
				'filter' => $this->bo->filter,
				'cat_id' => $this->bo->cat_id,
				'owner'  => $this->bo->owner,
				'year'   => $this->bo->year,
				'month'  => $this->bo->month,
				'day'    => $this->bo->day
			);
			$this->bo->save_sessiondata($data);
		}

		function read_entry($cal_id)
		{
			return $this->bo->read_entry(intval($cal_id));
		}

		/*!
		@function add
		@abstract adds a new alarm to an event
		@syntax add(&$event,$time,$login_id)
		@param &$event event to add the alarm too
		@param $time for the alarm in sec before the starttime of the event
		@param $login_id user to alarm
		@returns the alarm or False
		*/
		function add(&$event,$time,$owner)
		{
			if (!$this->check_perms(PHPGW_ACL_SETALARM,$owner) || !($cal_id = $event['id']))
			{
				return False;
			}
			$alarm = Array(
				'time'    => ($etime=$this->bo->maketime($event['start'])) - $time,
				'offset'  => $time,
				'owner'   => $owner,
				'enabled' => 1
			);
			$alarm['id'] = $this->so->save_alarm($cal_id,$alarm);

			$event['alarm'][$alarm['id']] = $alarm;

			return $alarm;
		}

		/*!
		@function check_perms
		@abstract checks if user has a certain grant from the owner of an alarm or event
		@syntax check_perms($grant,$owner)
		@param $grant PHPGW_ACL_{SET|READ|DELETE}ALARM (defined at the top of this file)
		*/
		function check_perms($grant,$owner)
		{
			return $this->bo->check_perms($grant,0,$owner);
		}

		/*!
		@function participants
		@abstract get the participants of an event, the user has grants to alarm
		@syntax participants($event,$fullnames=True)
		@param $event or cal_id of an event
		@param $fullnames if true return array with fullnames as values
		@returns array with account_ids as keys
		*/
		function participants($event,$fullnames=True)
		{
			if (!is_array($event))
			{
				$event = $this->read_entry($event);
			}
			if (!$event)
			{
				return False;
			}
			$participants = array();
			foreach ($event['participants'] as $uid => $status)
			{
				if ($this->check_perms(PHPGW_ACL_SETALARM,$uid))
				{
					$participants[$uid] = $fullnames ? $GLOBALS['phpgw']->common->grab_owner_name($uid) : True;
				}
			}
			return $participants;
		}

		/*!
		@function enable
		@abstract enable or disable one or more alarms identified by its ids
		@syntax enable($ids,$enable=True)
		@param $ids array with alarm ids as keys (!)
		@returns the number of alarms enabled or -1 for insuficent permission to do so
		@note Not found alarms or insuficent perms stop the enableing of multiple alarms
		*/
		function enable($alarms,$enable=True)
		{
			$enabled = 0;
			foreach ($alarms as $id => $field)
			{
				if (!($alarm = $this->so->read_alarm($id)))
				{
					return 0;	// alarm not found
				}
				if (!$alarm['enabled'] == !$enable)
				{
					continue;	// nothing to do
				}
				if ($enable && !$this->check_perms(PHPGW_ACL_SETALARM,$alarm['owner']) ||
					!$enable && !$this->check_perms(PHPGW_ACL_DELETEALARM,$alarm['owner']))
				{
					return -1;
				}
				$alarm['enabled'] = intval(!$alarm['enabled']);
				if ($this->so->save_alarm($alarm['cal_id'],$alarm))
				{
					++$enabled;
				}
			}
			return $enabled;
		}

		/*!
		@function delete
		@abstract delete one or more alarms identified by its ids
		@syntax delete($ids)
		@param $ids array with alarm ids as keys (!)
		@returns the number of alarms deleted or -1 for insuficent permission to do so
		@note Not found alarms or insuficent perms stop the deleting of multiple alarms
		*/
		function delete($alarms)
		{
			$deleted = 0;
			foreach ($alarms as $id => $field)
			{
				if (!($alarm = $this->so->read_alarm($id)))
				{
					return 0;	// alarm not found
				}
				if (!$this->check_perms(PHPGW_ACL_DELETEALARM,$alarm['owner']))
				{
					return -1;
				}
				if ($this->so->delete_alarm($id))
				{
					++$deleted;
				}
			}
			return $deleted;
		}
	}
?>
