<?php
/**************************************************************************\
* eGroupWare - Calendar's "new" BO-layer (buisness-object)                 *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * Class to access and manipulate all calendar data
 *
 * At the moment this class partialy uses the "old" bocalendar class.
 * As the calendar rewrite proceeds, these references will be removed.
 *
 * Note: All new code should only access this class and not bocalendar!!!
 *
 * If you need a function not already available in bocal, please ask RalfBecker@outdoor-training.de
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only in server-time (please note, this is not the case with the socalendar*-classes used atm.)
 *
 * As this BO class deals with dates/times of several types and timezone, each variable should have a postfix
 * appended, telling with type it is: _s = seconds, _su = secs in user-time, _ss = secs in server-time, _h = hours
 * The dates returned by this class are always arrays with the following keys (subset of the array used by datetime):
 * year, month, day (not mday!), hour, minute (not min!), second (not sec!), raw (timestamp) and full (Ymd-string)
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

if (!defined('ACL_TYPE_IDENTIFER'))	// used to mark ACL-values for the debug_message methode
{
	define('ACL_TYPE_IDENTIFER','***ACL***');
}

define('HOUR_s',60*60);
define('DAY_s',24*HOUR_s);
define('WEEK_s',7*DAY_s);

class bocal
{
	/**
	 * @var int $debug name of method to debug or level of debug-messages:
	 *	False=Off as higher as more messages you get ;-)
	 *	1 = function-calls incl. parameters to general functions like search, read, write, delete
	 *	2 = function-calls to exported helper-functions like check_perms
	 *	4 = function-calls to exported conversation-functions like date2ts, date2array, ...
	 *	5 = function-calls to private functions
	 */
	var $debug=False;

	/**
	 * @var int $tz_offset_s offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 */
	var $tz_offset_s;

	/**
	 * @var int $now_su timestamp of actual user-time
	 */
	var $now_su;

	/**
	 * @var array $cal_prefs calendar-specific prefs
	 */
	var $cal_prefs;

	/**
	 * @var array $common_prefs common preferences
	 */
	var $common_prefs;

	/**
	 * @var int $user nummerical id of the current user-id
	 */
	var $user=0;

	/**
	 * @var array $grants grants of the current user, array with user-id / ored-ACL-rights pairs
	 */
	var $grants=array();

	/**
	 * @var array $verbose_status translated 1-char status values to a verbose name, run through lang() by the constructor
	 */
	var $verbose_status = array(
		'A' => 'Accepted',
		'R' => 'Rejected',
		'T' => 'Tentative',
		'U' => 'No Response',
	);

	/**
	 * Constructor
	 */
	function bocal()
	{
		if ($this->debug > 0) $this->debug_message('bocal::bocal() started',True,$param);

		foreach(array(
//			'old_bo'    => 'calendar.bocalendar',
			'old_so'    => 'calendar.socalendar',
			'datetime'  => 'phpgwapi.datetime',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['phpgw']->$class))
			{
				$GLOBALS['phpgw']->$class = CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['phpgw']->$class;
		}
		$this->common_prefs = $GLOBALS['phpgw_info']['user']['preferences']['common'];
		$this->cal_prefs = $GLOBALS['phpgw_info']['user']['preferences']['calendar'];

		$this->tz_offset_s = $this->datetime->tz_offset;

		$this->now_su = time() + $this->tz_offset_s;

		$this->user = $GLOBALS['phpgw_info']['user']['account_id'];

		$this->grants = $GLOBALS['phpgw']->acl->get_grants('calendar');

		foreach($this->verbose_status as $status => $text)
		{
			$this->verbose_status[$status] = lang($text);
		}
	}

	/**
	 * Searches / lists calendar entries, including repeating ones
	 *
	 * As said earlier the new bo-class operates to the outside only in user-time and to the so-class only in server-time.
	 * The existing old_so-class (socalendar*) still has a mixed approach, values are mostly (!) returned in user-time
	 * and arguments are sometimes (!) expected in server-time ;-)
	 * We need to kope with that, til the so-class gets re-written !!!
	 * The arguments to the list_*events* functions are in user-time (they take only year,month,day) and they return id's.
	 * The events, fetched with there id's via old_so::read_entry are also in user-time.
	 *
	 * @param params array with the following keys
	 *	start date startdate of the search/list, defaults to today
	 *	end   date enddate of the search/list, defaults to start + one day
	 *	users  mixed integer user-id or array of user-id's to use, defaults to the current user
	 *	cat_id mixed category-id or array of cat-id's, defaults to all if unset, 0 or False
	 *		Please note: only a single cat-id, will include all sub-cats (if the common-pref 'cats_no_subs' is False)
	 *	filter string space delimited filter-names, atm. 'all' or 'private'
	 *	query string pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 *	dayswise boolean on True it returns an array with YYYYMMDD strings as keys and an array with events
	 *		(events spanning multiple days are returned each day again (!)) otherwise it returns one array with
	 *		the events (default), not honored in a search ==> always returns an array of events !
	 * @return array of events or array with YYYYMMDD strings / array of events pairs (depending on $daywise param)
	 */
	function search($params)
	{
		$params_in = $params;

		if (!isset($params['users']) || !$params['users'])
		{
			$params['users'] = $this->user;
		}
		if (!is_array($params['users']))
		{
			$params['users'] = array((int) $params['users']);
		}
		// only query calendars of users, we have READ-grants from
		$users = array();
		foreach($params['users'] as $user)
		{
			if ($this->check_perms(PHPGW_ACL_READ,0,$user))
			{
				$users[] = $user;
			}
		}
		$start = $this->date2ts($params['start']);
		$end   = isset($params['end']) ? $this->date2ts($params['end']) : $start + DAY_s-1;
		$daywise = !isset($params['daywise']) ? False : !!$params['daywise'];
		$cat_id = isset($params['cat_id']) ? $params['cat_id'] : 0;
		$filter = isset($params['filter']) ? $params['filter'] : 'all';
		if ($this->debug && ($this->debug > 1 || $this->debug == 'search'))
		{
			$this->debug_message('bocal::search(%1) start=%2, end=%3, daywise=%4, cat_id=%5, filter=%6',True,
				$params,$start,$end,$daywise,$cat_id,$filter);
		}
		// some of the params need to be set as class vars in the old so-class
		$this->old_so->cat_id = $cat_id;
		$this->old_so->filter = $filter;

		if (!isset($params['query']) || empty($params['query']))
		{
			$start = $this->date2array($start);
			$end   = $this->date2array($end);

			$event_ids = $this->old_so->list_events($start['year'],$start['month'],$start['day'],
				$end['year'],$end['month'],$end['day'],$users);

			if ($this->debug && ($this->debug > 1 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::list_events(start=%1,end=%2,users=%3)=%4',False,
					$start,$end,$users,$event_ids);
			}
			$rep_event_ids = $this->old_so->list_repeated_events($start['year'],$start['month'],$start['day'],
				$end['year'],$end['month'],$end['day'],$users);
			if ($this->debug > 1) $this->debug_message('socalendar::list_repeated_events(start=%1,end=%2,users=%3)=%4',False,$start,$end,$users,$rep_event_ids);
		}
		else
		{
			$daywise = False;
			$event_ids = $this->old_so->list_events_keyword($params['query'],$users);

			if ($this->debug && ($this->debug > 1 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::list_events_keyword(query=%1,users=%2)=%3',False,
					$params['query'],$users,$event_ids);
			}
			$rep_event_ids = array();	// not used in search
		}
		// To build the list of events from returned id's, we need go through the $event_ids, read each event
		// and put it in the cached_events for each day it's running, if it's NO repeating event.
		// Then we need to go through $rep_event_ids, fetch the event and add each event with ALL it's occurences
		// within the reported timespan, if there is no recur-exception-event already in the list of events.
		// If daywise is True each event need to be added in the array of each day it's running.
		// Please note: as we use the old SO class, all returned dates are already in user-time !!!

		$events = $recur_exceptions = $recur_events = Array();
		$events_sorted = True;

		if(count($event_ids))
		{
			foreach($event_ids as $id)
			{
				$event = $this->read($id,True);	// = no ACL check, as other entries dont get reported !!!

				// recuring events are handled later, remember them for later use, no new read necessary
				if ($event['recur_type'])
				{
					$recur_events[$id] = $event;
					continue;
				}
				// recur-exceptions have a reference to the original event
				// we remember they are their for a certain date and id, to not insert their regular recurrence
				if ($event['reference'])
				{
					for($ts = $event['start']['raw']; $ts < $event['end']['raw']; $ts += DAY_s)
					{
						$recur_exceptions[$event['reference']][(int)$this->date2string($ts)] = True;
					}
					$recur_exceptions[$event['reference']][$event['end']['full']] = True;
				}
				$events[] = $event;
			}
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search processed event_ids=%1, events=%2',False,$event_ids,$events);
			}
		}

		if (count($rep_event_ids))
		{
			foreach($rep_event_ids as $id)
			{
				$event = isset($recur_events[$id]) ? $recur_events[$id] : $this->read($id,True);

				$this->insert_all_repetitions($event,$start,$end,$events,$recur_exceptions[$id]);
				$events_sorted = False;
			}
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search processed rep_event_ids=%1, events=%2',False,$rep_event_ids,$events);
			}
		}

		if (!$events_sorted)
		{
			// sort the events by start-date
			usort($events,create_function('$e1,$e2',"return \$e1['start']['raw']-\$e2['start']['raw'];"));
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search usort(events)=%1',False,$events);
			}
		}
		if ($daywise)
		{
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search daywise sorting from %1 to %2 of %3',False,$start,$end,$events);
			}
			// create empty entries for each day in the reported time
			for($ts = $start['raw']; $ts <= $end['raw']; $ts += DAY_s)
			{
				$daysEvents[$this->date2string($ts)] = array();
			}
			foreach($events as $event)
			{
				$e_start = max($event['start']['raw'],$start['raw']);
				// $event['end']['raw']-1 to allow events to end on a full hour/day without the need to enter it as minute=59
				$e_end   = min($event['end']['raw']-1,$end['raw']);

				// add event to each day in the reported time
				for($ts = $e_start; $ts <= $e_end; $ts += DAY_s)
				{
					$daysEvents[$this->date2string($ts)][] = $event;
				}
			}
			$events = $daysEvents;
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search daywise events=%1',False,$events);
			}
		}
		if ($this->debug && ($this->debug > 0 || $this->debug == 'search'))
		{
			$this->debug_message('bocal::search(%1)=%2',True,$params,$events);
		}
		return $events;
	}

	/**
	 * Reads a calendar-entry
	 *
	 * @param $id int the id of the entry
	 * @param $ignore_acl boolean should we ignore the acl, default False
	 * @return array with the event or False if the acl-check went wrong
	 */
	function read($id,$ignore_acl=False)
	{
		$event = False;

		if($ignore_acl || $this->check_perms(PHPGW_ACL_READ,$id))
		{
			// some minimal cacheing to re-use the event already read in check_perms
			static $event = array();
			if (!isset($event['id']) || $event['id'] != $id)
			{
				$event = $this->old_so->read_entry($id);

				if (!$event['recur_enddate']['year'] && !$event['recur_enddate']['month'] && !$event['recur_enddate']['day'])
				{
					$event['recur_enddate'] = False;	// for easier checking
				}
				// we run all dates through date2array, to get the new keys (day,minute,second,full,raw)
				foreach(array('start','end','modtime','recur_enddate') as $date)
				{
					// The dates are already in user-time, because of the old so-class !!!
					$event[$date] = $event[$date] ? $this->date2array($event[$date]) : $event[$date];
				}
			}
		}
		if ($this->debug && ($this->debug > 1 || $this->debug == 'read'))
		{
			$this->debug_message('bocal::read(%1,%2)=%3',True,$id,$ignore_acl,$event);
		}
		return $event;
	}

	/**
	 * Inserts all repetions of $event in the timespan between $start and $end into $events
	 *
	 * As events can have recur-exceptions, only those event-date not having one, should get inserted.
	 * The caller supplies an array with the already inserted exceptions.
	 *
	 * The new entries are just appended to $entries, so $events is no longer sorted by startdate !!!
	 * Unlike the old code the start- and end-date of the events should be adapted here !!!
	 *
	 * TODO: This code is mainly copied from bocalendar and need to be rewritten for the changed algorithm:
	 *	We insert now all repetions of one event in one go. It should be possible to calculate the time-difference
	 *	of the used recur-type and add all events in one simple for-loop. Daylightsaving changes need to be taken into Account.
	 *
	 * @param $event array repeating event whos repetions should be inserted
	 * @param $start array start-date
	 * @param $end array end-date
	 * @param $events array where the repetions get inserted
	 * @param $recur_exceptions array with date (in Ymd) as key (and True as values)
	 */
	function insert_all_repetitions($event,$start,$end,&$events,$recur_exceptions)
	{
		$start_in = $start; $end_in = $end;

		if ($this->debug && ($this->debug > 3 || $this->debug == 'insert_all_repetions'))
		{
			$this->debug_message('bocal::insert_all_repetions(%1,start=%2,end=%3,,%4) starting...',True,$event,$start_in,$end_in,$recur_exceptions);
		}
		$id = $event['id'];
		$event_start_daybegin_ts = $this->date2ts($event['start']['full']);

		if($event['recur_enddate'])
		{
			$recur_end_ymd = $this->date2string($event['recur_enddate']);
		}
		else
		{
			$recur_end_ymd = $this->date2string(mktime(0,0,0,1,1,5+date('Y')));	// go max. 5 years from now
		}

		// We only need to compute the intersection between our reported time-span and the live-time of the event
		// To catch all multiday repeated events (eg. second days), we need to start the length of the even earlier
		// then our original report-starttime
		$event_length = $event['end']['raw']-$event['start']['raw'];
		$start_ts = max($event['start']['raw'],$start['raw']-$event_length);
		// we need to add 26*60*60-1 to the recur_enddate as its hour+minute are 0
		$end_ts   = $event['recur_enddate'] ? min($event['recur_enddate']['raw']+DAY_s-1,$end['raw']) : $end['raw'];

		for($ts = $start_ts; $ts < $end_ts; $ts += DAY_s)
		{
			$search_date_ymd = (int)$this->date2string($ts);

			$have_exception = !is_null($recur_exceptions) && isset($recur_exceptions[$search_date_ymd]);
			if ($this->debug && ($this->debug > 3 || $this->debug == 'insert_all_repetions'))
			{
				$this->debug_message('bocal::insert_all_repetions(...,%1) checking recur_exceptions[%2]=%3',False,
					$recur_exceptions,$search_date_ymd,$have_exception);
			}
			if ($have_exception)
			{
				continue;	// we already have an exception for that date
			}
			$search_date_year = date('Y',$ts);
			$search_date_month = date('m',$ts);
			$search_date_day = date('d',$ts);
			$search_date_dow = date('w',$ts);
			$search_beg_day = mktime(0,0,0,$search_date_month,$search_date_day,$search_date_year);

			if ($search_date_ymd == $event['start']['full'])	// first occurence
			{
				$this->add_adjusted_event($events,$event,$search_date_ymd);
				continue;
			}
			$freq = $event['recur_interval'];
			$type = $event['recur_type'];
			switch($type)
			{
				case MCAL_RECUR_DAILY:
					if($this->debug > 4)
					{
						echo '<!-- check_repeating_events - MCAL_RECUR_DAILY - '.$id.' -->'."\n";
					}
					if ($freq == 1 && $event['recur_enddate']['month'] != 0 && $event['recur_enddate']['day'] != 0 && $event['recur_enddate']['year'] != 0 && $search_date_ymd <= $recur_end_ymd)
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					elseif (floor(($search_beg_day - $event_start_daybegin_ts)/DAY_s) % $freq)
					{
						continue;
					}
					else
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
				case MCAL_RECUR_WEEKLY:
					if (floor(($search_beg_day - $event_start_daybegin_ts)/WEEK_s) % $freq)
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
					if ($event['recur_data'] & $check)
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
				case MCAL_RECUR_MONTHLY_WDAY:
					if ((($search_date_year - $event['start']['year']) * 12 + $search_date_month - $event['start']['month']) % $freq)
					{
						continue;
					}

					if (($GLOBALS['phpgw']->datetime->day_of_week($event['start']['year'],$event['start']['month'],$event['start']['day']) == $GLOBALS['phpgw']->datetime->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
						(ceil($event['start']['day']/7) == ceil($search_date_day/7)))
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
				case MCAL_RECUR_MONTHLY_MDAY:
					if ((($search_date_year - $event['start']['year']) * 12 + $search_date_month - $event['start']['month']) % $freq)
					{
						continue;
					}
					if ($search_date_day == $event['start']['day'])
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
				case MCAL_RECUR_YEARLY:
					if (($search_date_year - $event['start']['year']) % $freq)
					{
						continue;
					}
					if (date('dm',$ts) == date('dm',$event_start_daybegin_ts))
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
			} // switch(recur-type)
		} // for($date = ...)
		if ($this->debug && ($this->debug > 2 || $this->debug == 'insert_all_repetions'))
		{
			$this->debug_message('bocal::insert_all_repetions(%1,start=%2,end=%3,events,exections=%4) events=%5',True,$event,$start_in,$end_in,$recur_exceptions,$events);
		}
	}

	/**
	 * Adds one repetion of $event for $date_ymd to the $events array, after adjusting its start- and end-time
	 *
	 * @param $events array in which the event gets inserted
	 * @param $event array event to insert, it has start- and end-date of the first recurrence, not of $date_ymd
	 * @param $date_ymd int/string of the date of the event
	 */
	function add_adjusted_event(&$events,$event,$date_ymd)
	{
		$event_in = $event;
		// calculate the new start- and end-time
		$length_s = $event['end']['raw'] - $event['start']['raw'];

		$date_arr = $this->date2array((string) $date_ymd);
		$date_arr['hour'] = $event['start']['hour'];
		$date_arr['minute'] = $event['start']['minute'];
		$date_arr['second'] = $event['start']['second'];
		unset($date_arr['raw']);	// else date2ts would use it
		$date_arr['raw'] = $this->date2ts($date_arr);
		$date_arr['full'] = (int) $date_ymd;

		$event['start'] = $date_arr;
		$event['end'] = $this->date2array($date_arr['raw']+$length_s);

		$events[] = $event;

		if ($this->debug && ($this->debug > 2 || $this->debug == 'add_adjust_event'))
		{
			$this->debug_message('bocal::add_adjust_event(,%1,%2) as %3',True,$event_in,$date_ymd,$event);
		}
	}

	/**
	 * Checks if the current user has the necessary ACL rights
	 *
	 * The check is performed on an event or generally on the cal of an other user
	 *
	 * Note: Participating in an event is considered as haveing read-access on that event,
	 *	even if you have no general read-grant from that user.
	 *
	 * @param $needed int necessary ACL right: PHPGW_ACL_{READ|EDIT|DELETE}
	 * @param $event mixed event as array or the event-id or 0 for a general check
	 * @param $other int uid to check (if event==0) or 0 to check against $this->user
	 */
	function check_perms($needed,$event=0,$other=0)
	{
		$event_in = $event;
		if (is_int($event) && $event == 0)
		{
			$owner = $other > 0 ? $other : $this->user;
		}
		else
		{
			if (!is_array($event))
			{
				$event = $this->read((int) $event,True);	// = no ACL check !!!
			}
			if (!is_array($event))
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
				}
				return False;
			}
			$owner = $event['owner'];
			$private = !$event['public'];
		}
		$user = $GLOBALS['phpgw_info']['user']['account_id'];
		$grants = $this->grants[$owner];

		if (is_array($event) && $needed == PHPGW_ACL_READ)
		{
			// Check if the $user is one of the participants or has a read-grant from one of them
			// in that case he has an implicite READ grant for that event
			//
			foreach($event['participants'] as $uid => $accept)
			{
				if ($uid == $user) 
				{
					// if we are a participant, we have an implicite READ and PRIVAT grant
					$grants |= PHPGW_ACL_READ | PHPGW_ACL_PRIVATE;
					break;
				}
				elseif ($this->grants[$uid] & PHPGW_ACL_READ)
				{
					// if we have a READ grant from a participant, we dont give an implicit privat grant too
					$grants |= PHPGW_ACL_READ;
					// we cant break here, as we might be a participant too, and would miss the privat grant 
				}	
			}
		}

		if ($GLOBALS['phpgw']->accounts->get_type($owner) == 'g' && $needed == PHPGW_ACL_ADD)
		{
			$access = False;	// a group can't be the owner of an event
		}
		else
		{
			$access = $user == $owner || $grants & $needed && (!$private || $grants & PHPGW_ACL_PRIVATE);
		}
		if ($this->debug && ($this->debug > 2 || $this->debug == 'check_perms'))
		{
			$this->debug_message('bocal::check_perms(%1,%2,%3)=%4',True,ACL_TYPE_IDENTIFER.$needed,$event,$other,$access);
		}
		return $access;
	}

	/**
	 * Converts several date-types to a timestamp and optionaly converts user- to server-time
	 *
	 * @param $date mixed date to convert, should be one of the following types
	 *	string (!) in form YYYYMMDD or iso8601 YYYY-MM-DDThh:mm:ss
	 *	int already a timestamp
	 *	array with keys 'second', 'minute', 'hour', 'day' or 'mday' (depricated !), 'month' and 'year'
	 * @param $user2server_time boolean conversation between user- and server-time default False == Off
	 */
	function date2ts($date,$user2server=False)
	{
		$date_in = $date;

		switch(gettype($date))
		{
			case 'string':	// YYYYMMDD or iso8601 YYYY-MM-DDThh:mm:ss string
				if ($date[10] == 'T')
				{
					$date = array(
						'year'   => (int) substr($date,0,4),
						'month'  => (int) substr($date,5,2),
						'day'    => (int) substr($date,8,2),
						'hour'   => (int) substr($date,11,2),
						'minute' => (int) substr($date,14,2),
						'second' => (int) substr($date,17,2),
					);
				}
				else
				{
					$date = array(
						'year'  => (int) substr($date,0,4),
						'month' => (int) substr($date,4,2),
						'day'   => (int) substr($date,6,2),
					);
				}
				// fall-through
			case 'array':	// day or mday, month and year keys
				if (isset($date['raw']) && $date['raw'])	// we already have a timestamp
				{
					$date = $date['raw'];
					break;
				}
				foreach(array('mday'=>'day','min'=>'minute','sec'=>'second') as $old => $new)
				{
					if (!isset($date[$new]) && isset($date[$old]))	// support the old format too
					{
						$date[$new] = $date[$old];
						unset($date[$old]);
					}
				}
				$date = mktime((int)$date['hour'],(int)$date['minute'],(int)$date['second'],(int)$date['month'],(int)$date['day'],(int)$date['year']);
				break;
			case 'integer':		// already a timestamp
				break;
			default:		// eg. boolean, means now in user-time (!)
				$date = $this->now_us;
				break;
		}
		if ($user2server)
		{
			$date -= $this->tz_offset_s;
		}
		if ($this->debug && ($this->debug > 3 || $this->debug == 'date2ts'))
		{
			$this->debug_message('bocal::date2ts(%1,user2server=%2)=%3)',False,$date_in,$user2server,$date);
		}
		return $date;
	}

	/**
	 * Converts a date to an array and optionaly converts server- to user-time
	 *
	 * @param $date mixed date to convert
	 * @param $server2user_time boolean conversation between user- and server-time default False == Off
	 * @return array with keys 'second', 'minute', 'hour', 'day', 'month', 'year', 'raw' (timestamp) and 'full' (Ymd-string)
	 */
	function date2array($date,$server2user=False)
	{
		$date_called = $date;

		if (!is_array($date) || count($date) < 8 || $server2user)	// do we need a conversation
		{
			$date = $this->date2ts($date);

			if ($server2user)
			{
				$date += $this->tz_offset_s;
			}
			$arr = array();
			foreach(array('second'=>'s','minute'=>'i','hour'=>'H','day'=>'d','month'=>'m','year'=>'Y','full'=>'Ymd') as $key => $frmt)
			{
				$arr[$key] = (int) date($frmt,$date);
			}
			$arr['raw'] = $date;
		}
		if ($this->debug && ($this->debug > 3 || $this->debug == 'date2array'))
		{
			$this->debug_message('bocal::date2array(%1,server2user=%2)=%3)',False,$date_called,$server2user,$arr);
		}
		return $arr;
	}

	/**
	 * Converts a date as timestamp or array to a date-string and optionaly converts server- to user-time
	 *
	 * @param $date mixed integer timestamp or array with ('year','month',..,'second') to convert
	 * @param $server2user_time boolean conversation between user- and server-time default False == Off
	 * @param $iso8601 boolean return a iso8601 date (YYYY-MM-DDThh:ii:ss), default False == Off
	 * @return YYYYMMDD or iso8601 date as string
	 */
	function date2string($date,$server2user=False,$iso8601=False)
	{
		$date_in = $date;

		if (is_array($date) && isset($date['full']) && !$server2user && !$iso8601)
		{
			$date = $date['full'];
		}
		else
		{
			$date = $this->date2ts($date,False);

			if ($server2user)
			{
				$date += $this->tz_offset_s;
			}
			$date = date(($iso8601 ? 'Y-m-d\TH:i:s' : 'Ymd'),$date);
		}
		if ($this->debug && ($this->debug > 3 || $this->debug == 'date2string'))
		{
			$this->debug_message('bocal::date2string(%1,server2user=%2,iso8601=%3)=%4)',False,$date_in,$server2user,$iso8601,$date);
		}
		return $date;
	}

	/**
	 * Gives out a debug-message with certain parameters
	 *
	 * All permanent debug-messages in the calendar should be done by this function !!!
	 *	(In future they may be logged or sent as xmlrpc-faults back.)
	 *
	 * Permanent debug-message need to make sure NOT to give secret information like passwords !!!
	 *
	 * This function do NOT honor the setting of the debug variable, you may use it like
	 * if ($this->debug > N) $this->debug_message('Error ;-)');
	 *
	 * The parameters get formated depending on their type. ACL-values need a ACL_TYPE_IDENTIFER prefix.
	 *
	 * @param $msg string message with parameters/variables like lang(), eg. '%1'
	 * @param $backtrace include a function-backtrace, default True=On
	 *	should only be set to False=Off, if your code ensures a call with backtrace=On was made before !!!
	 * @param $param mixed a variable number of parameters, to be inserted in $msg
	 *	arrays get serialized with print_r() !
	 */
	function debug_message($msg,$backtrace=True)
	{
		static $acl2string = array(
			0                 => 'ACL-UNKNOWN',
			PHPGW_ACL_READ    => 'ACL_READ',
			PHPGW_ACL_WRITE   => 'ACL_WRITE',
			PHPGW_ACL_ADD     => 'ACL_ADD',
			PHPGW_ACL_DELETE  => 'ACL_DELETE',
			PHPGW_ACL_PRIVATE => 'ACL_PRIVATE',
		);
		for($i = 2; $i < func_num_args(); ++$i)
		{
			$param = func_get_arg($i);

			if (is_null($param))
			{
				$param='NULL';
			}
			else
			{
				switch(gettype($param))
				{
					case 'string':
						if (substr($param,0,strlen(ACL_TYPE_IDENTIFER))== ACL_TYPE_IDENTIFER)
						{
							$param = (int) substr($param,strlen(ACL_TYPE_IDENTIFER));
							$param = isset($acl2string[$param]) ? $acl2string[$param] : $acl2string[0];
						}
						else
						{
							$param = "'$param'";
						}
						break;
					case 'array':
					case 'object':
						list(,$content) = @each($param);
						$do_pre = is_array($param) ? count($param) > 6 || is_array($content)&&count($content) : True;
						$param = ($do_pre ? '<pre>' : '').print_r($param,True).($do_pre ? '</pre>' : '');
						break;
					case 'boolean':
						$param = $param ? 'True' : 'False';
						break;
				}
			}
			$msg = str_replace('%'.($i-1),$param,$msg);
		}
		echo '<p>'.$msg."<br>\n".($backtrace ? 'Backtrace: '.function_backtrace(1)."</p>\n" : '');
	}

	/**
	 * Formats one or two dates (range) as long date (full monthname)
	 *
	 * @param $first mixed first date
	 * @param $last mixed last date if != 0 (default)
	 * @return string with formated date
	 */
	function long_date($first,$last=0)
	{
		$first = $this->date2array($first);
		if ($last)
		{
			$last = $this->date2array($last);
		}
		$datefmt = $this->common_prefs['dateformat'];

		$month_before_day = strtolower($datefmt[0]) == 'm' ||
			strtolower($datefmt[2]) == 'm' && $datefmt[4] == 'd';

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
				case 'M':
					$range .= ' '.lang(strftime('%B',$month_before_day ? $first['raw'] : $last['raw'])) . ' ';
					break;
				case 'Y':
					$range .= ($datefmt[0] == 'm' ? ', ' : ' ') . ($datefmt[0] == 'Y' ? $first['year'].($datefmt[2] == 'd' ? ', ' : ' ') : $last['year'].' ');
					break;
			}
		}
		return $range;
	}

	/**
	* Converts participants array of an event into array of (readable) participant-names with status
	*
	* @param $parts array participants array of an event
	* @param $long_status boolean should the long/verbose status or only the one letter shortcut be used
	* @return array with id / names with status pairs
	*/
	function participants($parts,$long_status=False)
	{
		static $id2lid = array();

		foreach($parts as $id => $status)
		{
			$status = $this->verbose_status[$status];

			if (!$long_status)
			{
				$status = $status[0];
			}
			if (!isset($id2lid[$id]))
			{
				$id2lid[$id] = $GLOBALS['phpgw']->common->grab_owner_name($id);
			}
			$names[$id] = $id2lid[$id]." ($status)";
		}
		return $names;
	}

	/**
	* Converts category string of an event into array of (readable) category-names
	*
	* @param $category string cat-id (multiple id's commaseparated)
	* @param $color int color of the category, if multiple cats, the color of the last one with color is returned
	* @return array with id / names
	*/
	function categories($category,&$color)
	{
		static $id2cat = array();
		$cats = array();
		$color = 0;
		if (!isset($this->cat))
		{
			$this->cat = $GLOBALS['phpgw']->categories;
			$this->cat->categories($this->owner,'calendar');
		}
		foreach(explode(',',$category) as $cat_id)
		{
			if (!$cat_id) continue;

			if (!isset($id2cat[$cat_id]))
			{
				list($id2cat[$cat_id]) = $this->cat->return_single($cat_id);
				$id2cat[$cat_id]['data'] = unserialize($id2cat[$cat_id]['data']);
			}
			$cat = $id2cat[$cat_id];

			if ($cat['data']['color'] || preg_match('/(#[0-9A-Fa-f]{6})/',$cat['description'],$parts))
			{
				$color = $cat['data']['color'] ? $cat['data']['color'] : $parts[1];
			}
			$cats[$cat_id] = stripslashes($cat['name']);
		}
		return $cats;
	}

	/* This is called only by list_cals().  It was moved here to remove fatal error in php5 beta4 */
	function _list_cals_add($id,&$users,&$groups)
	{
		$name = $GLOBALS['phpgw']->common->grab_owner_name($id);
		if (($type = $GLOBALS['phpgw']->accounts->get_type($id)) == 'g')
		{
			$arr = &$groups;
		}
		else
		{
			$arr = &$users;
		}
		$arr[$name] = Array(
			'grantor' => $id,
			'value'   => ($type == 'g' ? 'g_' : '') . $id,
			'name'    => $name
		);
	}

	/**
	* generate list of user- / group-calendars for the selectbox in the header
	* @return alphabeticaly sorted array with groups first and then users
	*/
	function list_cals()
	{
		$users = $groups = array();
		foreach($this->grants as $id => $rights)
		{
			$this->_list_cals_add($id,$users,$groups);
		}
		if ($memberships = $GLOBALS['phpgw']->accounts->membership($GLOBALS['phpgw_info']['user']['account_id']))
		{
			foreach($memberships as $group_info)
			{
				$this->_list_cals_add($group_info['account_id'],$users,$groups);

				if ($account_perms = $GLOBALS['phpgw']->acl->get_ids_for_location($group_info['account_id'],PHPGW_ACL_READ,'calendar'))
				{
					foreach($account_perms as $id)
					{
						$this->_list_cals_add($id,$users,$groups);
					}
				}
			}
		}
		uksort($users,'strnatcasecmp');
		uksort($groups,'strnatcasecmp');

		return $users + $groups;	// users first and then groups, both alphabeticaly
	}

	/**
	 * Read the holidays for a given $year
	 *
	 * @param $year integer defaults to 0
	 * @return array indexed with Ymd of array of holidays. A holiday is an array with the following fields:
	 *	index: numerica unique id
	 *	locale: string, 2-char short for the nation
	 *	name: string
	 *	day: numerical day in month
	 *	month: numerical month
	 *	occurence:
	 *	dow: day of week, 0=sunday, .., 6= saturday
	 *	observande_rule: boolean
	 */
	function read_holidays($year=0)
	{
		if (!isset($this->cached_holidays[$year]))
		{
			if (!is_object($this->holidays))
			{
				$this->holidays = CreateObject('calendar.boholiday');
			}
			$this->holidays->prepare_read_holidays($year);
			$this->cached_holidays[$year] = $this->holidays->read_holiday();
		}
		return $this->cached_holidays[$year];
	}
}
