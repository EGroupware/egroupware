<?php
/**************************************************************************\
* eGroupWare - Calendar's "new" BO-layer (buisness-object) access + update *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/calendar/inc/class.bocal.inc.php');

/**
 * Class to access AND manipulate all calendar data
 *
 * Note: All new code should only access this class or bocal and NOT bocalendar!!!
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only in server-time (please note, this is not the case with the socalendar*-classes used atm.)
 *
 * As this BO class deals with dates/times of several types and timezone, each variable should have a postfix
 * appended, telling with type it is: _s = seconds, _su = secs in user-time, _ss = secs in server-time, _h = hours
 *
 * All new BO code (should be true for eGW in general) NEVER use any $_REQUEST ($_POST or $_GET) vars itself.
 * Nor does it store the state of any UI-elements (eg. cat-id selectbox). All this is the task of the UI class !!!
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the class !!!
 *
 * @package calendar
 * @author RalfBecker@outdoor-training.de
 * @license GPL
 */

class bocalupdate extends bocal
{
	/**
	 * Constructor
	 */
	function bocalupdate()
	{
		if ($this->debug > 0) $this->debug_message('bocalupdate::bocalupdate() started',True);

		$this->bocal();	// calling the parent constructor

		if ($this->debug > 0) $this->debug_message('bocalupdate::bocalupdate() finished',True);
	}

	/**
	 * updates or creates an event, it (optionaly) checks for conflicts and sends the necessary notifications 
	 *
	 * @param array &$event event-array, on return some values might be changed due to set defaults
	 * @param boolean $ignore_conflicts=false just ignore conflicts or do a conflict check and return the conflicting events
	 * @param boolean $touch_modified=true touch modificatin time and set modifing user, default true=yes
	 * @return mixed on success: int $cal_id > 0, on error false or array with conflicting events (only if $check_conflicts)
	 */
	function update(&$event,$ignore_conflicts=false,$touch_modified=true)
	{
		// check some minimum requirements:
		// - new events need start, end and title
		// - updated events cant set start, end or title to empty
		if (!$event['id'] && (!$event['start'] || !$event['end'] || !$event['title']) ||
			$event['id'] && (isset($event['start']) && !$event['start'] || isset($event['end']) && !$event['end'] ||  isset($event['title']) && !$event['title']))
		{
			return false;
		}
		if (!$event['id'])	// some defaults for new entries
		{
			// if no owner given, set user to owner
			if (!$event['owner']) $event['owner'] = $this->user;
			// set owner as participant if none is given
			if (!$event['id'] && (!is_array($event['participants']) || !count($event['participants'])))
			{
				$event['participants'][$event['owner']] = 'U';
			} 
			// set the status of the current user to 'A' = accepted
			if (isset($event['participants'][$this->user]) &&  $event['participants'][$this->user] != 'A')
			{
				$event['participants'][$this->user] = 'A';
			}
		}
		// check for conflicts only happens !$ignore_conflicts AND if start + end date are given
		if (!$ignore_conflicts && $event['non_blocking'] && isset($event['start']) && isset($event['end']))
		{
			$types_with_quantity = array();
			foreach($this->resources as $type => $data)
			{
				if ($data['use_quantity']) $types_with_quantity[] = $type;
			}
			// get all NOT rejected participants and evtl. their quantity
			$quantity = $users = array();
			foreach($event['participants'] as $uid => $status)
			{
				if ($status[0] == 'R') continue;	// ignore rejected participants

				$users[] = $uid;
				if (in_array($uid[0],$types_with_quantity))
				{
					$quantity[$uid] = min(1,(int) substr($overlap['participants']['uid'],2));
				}
			}
			$overlapping_events =& $this->search(array(
				'start' => $event['start'],
				'end'   => $event['end'],
				'users' => $users,
				'query' => array('cal_non_blocking != 1'),	// ignore non-blocking events
			));
			$max_quantity = $possible_quantity_conflicts = $conflicts = array();
			foreach((array) $overlapping_events as $k => $overlap)
			{
				echo "checking overlaping event"; _debug_array($overlap);
				// check if the overlap is with a rejected participant or within the allowed quantity
				$common_parts = array_intersect($users,array_keys($overlap['participants']));
				foreach($common_parts as $n => $uid)
				{
					if ($overlap['participants'][$uid][0] == 'R') 
					{
						unset($common_parts[$uid]);
						continue;
					}
					if (is_numeric($uid) || !in_array($uid[0],$types_with_quantity))
					{
						continue;	// no quantity check: quantity allways 1 ==> conflict
					}
					if (!isset($max_quantity[$uid]))
					{
						$res_info = ExecMethod($this->resources[$uid[0]]['info']);
						$max_quantity[$uid] = $res_info[$this->resources[$uid[0]]['use_quantity']];
					}
					$quantity[$uid] += min(1,(int) substr($overlap['participants']['uid'],2));
					
					if ($quantity[$uid] <= $max_quantity)
					{
						$possible_quantity_conflicts[$uid][] =& $overlapping_events[$k];	// an other event can give the conflict
						unset($common_parts[$uid]);
						continue;
					}
					// now we have a quantity conflict for $uid
				}
				if (!count($common_parts))
				{
					$conflicts[$overlap['id']-$this->bo->date2ts($overlap['start'])] =& $overlapping_events[$k];
				}
			}
			// check if we are withing the allowed quantity and if not add all events using that resource
			foreach($max_quantity as $uid => $max)
			{
				if ($quantity[$uid] > $max)
				{
					foreach($possible_quantity_conflicts[$uid] as $conflict)
					{
						$conflicts[$conflict['id']-$this->bo->date2ts($conflict['start'])] =& $possible_quantity_conflicts[$k];
					}
				}
			}
			unset($possible_quantity_conflicts);
			
			if (count($conflicts))
			{
				return $conflicts;
			}						
		}
		// save the event to the database
		if ($touch_modified)
		{
			$event['modified'] = time() + $this->tz_offset_s;	// we are still in user-time
			$event['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		if (!($cal_id = $this->save($event)))
		{
			return $cal_id;
		}
		$event['id'] = $cal_id;

		// TODO send update messages
		
		return $cal_id;
	}

	/**
	 * saves an event to the database, does NOT do any notifications, see bocalupdate::update for that
	 *
	 * This methode converts from user to server time and handles the insertion of users and dates of repeating events
	 *
	 * @param array $event
	 * @return int/boolean $cal_id > 0 or false on error
	 */
	function save($event)
	{
		$save_event = $event;
		// we run all dates through date2array, to adjust to server-time and get the new keys (day,minute,second,full,raw)
		foreach(array('start','end','modified','recur_enddate') as $ts)
		{
			// we convert here from user-time to timestamps in server-time!
			if (isset($event[$ts])) $event[$ts] = $event[$ts] ? $this->date2ts($event[$ts],true) : 0;
		}
		if (($cal_id = $this->so->save($event,$set_recurrences)) && $set_recurrences && $event['recur_type'] != MCAL_RECUR_NONE)
		{
			$save_event['id'] = $cal_id;
			$this->set_recurrences($save_event);
		}
		$GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id,$event['cal_id'] ? 'modify' : 'add',time());

		return $cal_id;
	}
	
	/**
	 * deletes an event
	 *
	 * @param int $cal_id
	 * @return boolean true on success, flase on error (usually permission denied)
	 */
	function delete($cal_id)
	{
		if (!$this->check_perms(EGW_ACL_DELETE,$cal_id)) return false;
		
		$this->so->delete($cal_id);
		$GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id,'delete',time());
		
		return true;
	}
}
