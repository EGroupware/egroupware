<?php
/**
 * eGroupWare - Calendar's buisness-object - access only
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2004-9 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if (!defined('ACL_TYPE_IDENTIFER'))	// used to mark ACL-values for the debug_message methode
{
	define('ACL_TYPE_IDENTIFER','***ACL***');
}

define('HOUR_s',60*60);
define('DAY_s',24*HOUR_s);
define('WEEK_s',7*DAY_s);

/**
 * Gives read access to the calendar, but all events the user is not participating are private!
 * Used by addressbook.
 */
define('EGW_ACL_READ_FOR_PARTICIPANTS',EGW_ACL_CUSTOM_1);
define('EGW_ACL_FREEBUSY',EGW_ACL_CUSTOM_2);

/**
 * Required (!) include, as we use the MCAL_* constants, BEFORE instanciating (and therefore autoloading) the class
 */
require_once(EGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_so.inc.php');

/**
 * Class to access all calendar data
 *
 * For updating calendar data look at the bocalupdate class, which extends this class.
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only in server-time
 *
 * As this BO class deals with dates/times of several types and timezone, each variable should have a postfix
 * appended, telling with type it is: _s = seconds, _su = secs in user-time, _ss = secs in server-time, _h = hours
 *
 * All new BO code (should be true for eGW in general) NEVER use any $_REQUEST ($_POST or $_GET) vars itself.
 * Nor does it store the state of any UI-elements (eg. cat-id selectbox). All this is the task of the UI class(es) !!!
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of this class !!!
 */
class calendar_bo
{
	/**
	 * @var int $debug name of method to debug or level of debug-messages:
	 *	False=Off as higher as more messages you get ;-)
	 *	1 = function-calls incl. parameters to general functions like search, read, write, delete
	 *	2 = function-calls to exported helper-functions like check_perms
	 *	4 = function-calls to exported conversation-functions like date2ts, date2array, ...
	 *	5 = function-calls to private functions
	 */
	var $debug=false;

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
		'G' => 'Group invitation',
	);
	/**
	 * @var array recur_types translates MCAL recur-types to verbose labels
	 */
	var $recur_types = Array(
		MCAL_RECUR_NONE         => 'None',
		MCAL_RECUR_DAILY        => 'Daily',
		MCAL_RECUR_WEEKLY       => 'Weekly',
		MCAL_RECUR_MONTHLY_WDAY => 'Monthly (by day)',
		MCAL_RECUR_MONTHLY_MDAY => 'Monthly (by date)',
		MCAL_RECUR_YEARLY       => 'Yearly'
	);
	/**
	 * @var array recur_days translates MCAL recur-days to verbose labels
	 */
	var $recur_days = array(
		MCAL_M_MONDAY    => 'Monday',
		MCAL_M_TUESDAY   => 'Tuesday',
		MCAL_M_WEDNESDAY => 'Wednesday',
		MCAL_M_THURSDAY  => 'Thursday',
		MCAL_M_FRIDAY    => 'Friday',
		MCAL_M_SATURDAY  => 'Saturday',
		MCAL_M_SUNDAY    => 'Sunday',
	);
	/**
	 * @var array $resources registered scheduling resources of the calendar (gets chached in the session for performance reasons)
	 */
	var $resources;
	/**
	 * @var array $cached_event here we do some caching to read single events only once
	 */
	protected static $cached_event = array();
	protected static $cached_event_date_format = false;
	protected static $cached_event_date = 0;
	/**
	 * @var array $cached_holidays holidays plus birthdays (gets cached in the session for performance reasons)
	 */
	var $cached_holidays;
	/**
	 * Instance of the socal class
	 *
	 * @var calendar_so
	 */
	var $so;
	/**
	 * Instance of the datetime class
	 *
	 * @var egw_datetime
	 */
	var $datetime;

	/**
	 * Constructor
	 */
	function __construct()
	{
		if ($this->debug > 0) $this->debug_message('bocal::bocal() started',True,$param);

		$this->so = new calendar_so();
		$this->datetime = $GLOBALS['egw']->datetime;

		$this->common_prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		$this->cal_prefs =& $GLOBALS['egw_info']['user']['preferences']['calendar'];

		$this->tz_offset_s = $this->datetime->tz_offset;

		$this->now_su = time() + $this->tz_offset_s;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->grants = $GLOBALS['egw']->acl->get_grants('calendar');

		foreach($this->verbose_status as $status => $text)
		{
			$this->verbose_status[$status] = lang($text);
		}
		if (!is_array($this->resources = $GLOBALS['egw']->session->appsession('resources','calendar')))
		{
			$this->resources = array();
			foreach($GLOBALS['egw']->hooks->process('calendar_resources') as $app => $data)
			{
				if ($data && $data['type'])
				{
					$this->resources[$data['type']] = $data + array('app' => $app);
				}
			}
			$this->resources['e'] = array(
				'type' => 'e',
				'info' => __CLASS__.'::email_info',
				'app'  => 'email',
			);
			$GLOBALS['egw']->session->appsession('resources','calendar',$this->resources);
		}
		//echo "registered resources="; _debug_array($this->resources);

		$this->config = config::read('calendar');
	}

	/**
	 * returns info about email addresses as participants
	 *
	 * @param int|array $ids single contact-id or array of id's
	 * @return array
	 */
	static function email_info($ids)
	{
		if (!$ids) return null;

		$data = array();
		foreach(!is_array($ids) ? array($ids) : $ids as $id)
		{
			$email = $id;
			$name = '';
			if (preg_match('/^(.*) *<([a-z0-9_.@-]{8,})>$/i',$email,$matches))
			{
				$name = $matches[1];
				$email = $matches[2];
			}
			$data[] = array(
				'res_id' => $id,
				'email' => $email,
				'rights' => EGW_ACL_READ_FOR_PARTICIPANTS,
				'name' => $name,
			);
		}
		//echo "<p>email_info(".print_r($ids,true).")="; _debug_array($data);
		return $data;
	}

	/**
	 * Add group-members as participants with status 'G'
	 *
	 * @param array $event event-array
	 * @return int number of added participants
	 */
	function enum_groups(&$event)
	{
		$added = 0;
		foreach($event['participants'] as $uid => $status)
		{
			if (is_numeric($uid) && $GLOBALS['egw']->accounts->get_type($uid) == 'g' &&
				($members = $GLOBALS['egw']->accounts->member($uid)))
			{
				foreach($members as $member)
				{
					$member = $member['account_id'];
					if (!isset($event['participants'][$member]))
					{
						$event['participants'][$member] = 'G';
						++$added;
					}
				}
			}
		}
		return $added;
	}

	/**
	 * Searches / lists calendar entries, including repeating ones
	 *
	 * @param params array with the following keys
	 *	start date startdate of the search/list, defaults to today
	 *	end   date enddate of the search/list, defaults to start + one day
	 *	users  mixed integer user-id or array of user-id's to use, defaults to the current user
	 *	cat_id mixed category-id or array of cat-id's, defaults to all if unset, 0 or False
	 *		Please note: only a single cat-id, will include all sub-cats (if the common-pref 'cats_no_subs' is False)
	 *	filter string filter-name, atm. 'all' or 'hideprivate'
	 *	query string pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 *	dayswise boolean on True it returns an array with YYYYMMDD strings as keys and an array with events
	 *		(events spanning multiple days are returned each day again (!)) otherwise it returns one array with
	 *		the events (default), not honored in a search ==> always returns an array of events !
	 *	date_format string date-formats: 'ts'=timestamp (default), 'array'=array, or string with format for date
	 *  offset boolean/int false (default) to return all entries or integer offset to return only a limited result
	 *  enum_recuring boolean if true or not set (default) or daywise is set, each recurence of a recuring events is returned,
	 *		otherwise the original recuring event (with the first start- + enddate) is returned
	 *  num_rows int number of entries to return, default or if 0, max_entries from the prefs
	 *  order column-names plus optional DESC|ASC separted by comma
	 *  show_rejected if set rejected invitation are shown only when true, otherwise it depends on the cal-pref or a running query
	 *  ignore_acl if set and true no check_perms for a general EGW_ACL_READ grants is performed
	 *  enum_groups boolean if set and true, group-members will be added as participants with status 'G'
	 *  cols string|array columns to select, if set the recordset/iterator will be returned
	 *  append string to append to the query, eg. GROUP BY
	 * @return iterator|array|boolean array of events or array with YYYYMMDD strings / array of events pairs (depending on $daywise param)
	 *	or false if there are no read-grants from _any_ of the requested users or iterator/recordset if cols are given
	 */
	function &search($params)
	{
		$params_in = $params;

		if (!isset($params['users']) || !$params['users'] ||
			count($params['users']) == 1 && isset($params['users'][0]) && !$params['users'][0])	// null or '' casted to an array
		{
			// for a search use all account you have read grants from
			$params['users'] = $params['query'] ? array_keys($this->grants) : $this->user;
		}
		if (!is_array($params['users']))
		{
			$params['users'] = $params['users'] ? array($params['users']) : array();
		}
		// only query calendars of users, we have READ-grants from
		$users = array();
		foreach($params['users'] as $user)
		{
			if ($params['ignore_acl'] || $this->check_perms(EGW_ACL_READ|EGW_ACL_READ_FOR_PARTICIPANTS|EGW_ACL_FREEBUSY,0,$user))
			{
				if ($user && !in_array($user,$users))	// already added?
				{
					$users[] = $user;
				}
			}
			elseif ($GLOBALS['egw']->accounts->get_type($user) != 'g')
			{
				continue;	// for non-groups (eg. users), we stop here if we have no read-rights
			}
			// the further code is only for real users
			if (!is_numeric($user)) continue;

			// for groups we have to include the members
			if ($GLOBALS['egw']->accounts->get_type($user) == 'g')
			{
				$members = $GLOBALS['egw']->accounts->member($user);
				if (is_array($members))
				{
					foreach($members as $member)
					{
						// use only members which gave the user a read-grant
						if (!in_array($member['account_id'],$users) &&
							($params['ignore_acl'] || $this->check_perms(EGW_ACL_READ|EGW_ACL_FREEBUSY,0,$member['account_id'])))
						{
							$users[] = $member['account_id'];
						}
					}
				}
			}
			else	// for users we have to include all the memberships, to get the group-events
			{
				$memberships = $GLOBALS['egw']->accounts->membership($user);
				if (is_array($memberships))
				{
					foreach($memberships as $group)
					{
						if (!in_array($group['account_id'],$users))
						{
							$users[] = $group['account_id'];
						}
					}
				}
			}
		}
		// if we have no grants from the given user(s), we directly return no events / an empty array,
		// as calling the so-layer without users would give the events of all users (!)
		if (!count($users))
		{
			return false;
		}
		if (isset($params['start'])) $start = $this->date2ts($params['start']);

		if (isset($params['end']))
		{
			$end = $this->date2ts($params['end']);
			$this->check_move_horizont($end);
		}
		$daywise = !isset($params['daywise']) ? False : !!$params['daywise'];
		$enum_recuring = $daywise || !isset($params['enum_recuring']) || !!$params['enum_recuring'];
		$cat_id = isset($params['cat_id']) ? $params['cat_id'] : 0;
		$filter = isset($params['filter']) ? $params['filter'] : 'all';
		$offset = isset($params['offset']) && $params['offset'] !== false ? (int) $params['offset'] : false;
		$show_rejected = isset($params['show_rejected']) ? $params['show_rejected'] : $this->cal_prefs['show_rejected'] || $params['query'];
		if ($this->debug && ($this->debug > 1 || $this->debug == 'search'))
		{
			$this->debug_message('bocal::search(%1) start=%2, end=%3, daywise=%4, cat_id=%5, filter=%6, query=%7, offset=%8, num_rows=%9, order=%10, show_rejected=%11)',
				True,$params,$start,$end,$daywise,$cat_id,$filter,$params['query'],$offset,(int)$params['num_rows'],$params['order'],$show_rejected);
		}
		// date2ts(,true) converts to server time, db2data converts again to user-time
		$events =& $this->so->search(isset($start) ? $this->date2ts($start,true) : null,isset($end) ? $this->date2ts($end,true) : null,
			$users,$cat_id,$filter,$params['query'],$offset,(int)$params['num_rows'],$params['order'],$show_rejected,$params['cols'],$params['append']);

		if (isset($params['cols']))
		{
			return $events;
		}
		$this->total = $this->so->total;
		$this->db2data($events,isset($params['date_format']) ? $params['date_format'] : 'ts');

		// socal::search() returns rejected group-invitations, as only the user not also the group is rejected
		// as we cant remove them efficiantly in SQL, we kick them out here, but only if just one user is displayed
		$remove_rejected_by_user = !$show_rejected && count($params['users']) == 1 ? $params['users'][0] : false;
		//echo "<p align=right>remove_rejected_by_user=$remove_rejected_by_user, show_rejected=$show_rejected, params[users]=".print_r($param['users'])."</p>\n";
		foreach($events as $id => $event)
		{
			if ($remove_rejected_by_user && $event['participants'][$remove_rejected_by_user] == 'R')
			{
				unset($events[$id]);	// remove the rejected event
				$this->total--;
				continue;
			}
			if ($params['enum_groups'] && $this->enum_groups($event))
			{
				$events[$id] = $event;
			}
			if (!$this->check_perms(EGW_ACL_READ,$event) || (!$event['public'] && $filter == 'hideprivate'))
			{
				if($params['query'])
				{
					unset($events[$id]);
					$this->total--;
					continue;
				}
				else
				{
					$this->clear_private_infos($events[$id],$users);
				}
			}
		}

		if ($daywise)
		{
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search daywise sorting from %1 to %2 of %3',False,$start,$end,$events);
			}
			// create empty entries for each day in the reported time
			for($ts = $start; $ts <= $end; $ts += DAY_s)
			{
				$daysEvents[$this->date2string($ts)] = array();
			}
			foreach($events as $k => $event)
			{
				$e_start = max($this->date2ts($event['start']),$start);
				// $event['end']['raw']-1 to allow events to end on a full hour/day without the need to enter it as minute=59
				$e_end   = min($this->date2ts($event['end'])-1,$end);

				// add event to each day in the reported time
				for($ts = $e_start; $ts <= $e_end; $ts += DAY_s)
				{
					$daysEvents[$ymd = $this->date2string($ts)][] =& $events[$k];
				}
				if ($ymd != ($last = $this->date2string($e_end)))
				{
					$daysEvents[$last][] =& $events[$k];
				}
			}
			$events =& $daysEvents;
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search daywise events=%1',False,$events);
			}
		}
		elseif(!$enum_recuring)
		{
			$recur_ids = array();
			foreach($events as $k => $event)
			{
				if ($event['recur_type'] != MCAL_RECUR_NONE)
				{
					if (!in_array($event['id'],$recur_ids))
					{
						$recur_ids[] = $event['id'];
					}
					unset($events[$k]);
				}
			}
			if (count($recur_ids))
			{
				$events = array_merge($this->read($recur_ids,null,false,$params['date_format']),$events);
			}
		}
		if ($this->debug && ($this->debug > 0 || $this->debug == 'search'))
		{
			$this->debug_message('bocal::search(%1)=%2',True,$params,$events);
		}
		return $events;
	}

	/**
	 * Clears all non-private info from a privat event
	 *
	 * That function only returns the infos allowed to be viewed by people without EGW_ACL_PRIVATE grants
	 *
	 * @param array &$event
	 * @param array $allowed_participants ids of the allowed participants, eg. the ones the search is over or eg. the owner of the calendar
	 */
	function clear_private_infos(&$event,$allowed_participants = array())
	{
		$event = array(
			'id'    => $event['id'],
			'start' => $event['start'],
			'end'   => $event['end'],
			'title' => lang('private'),
			'participants' => array_intersect_key($event['participants'],array_flip($allowed_participants)),
			'public'=> 0,
			'category' => $event['category'],	// category is visible anyway, eg. by using planner by cat
			'non_blocking' => $event['non_blocking'],
		);
	}

	/**
	 * check and evtl. move the horizont (maximum date for unlimited recuring events) to a new date
	 *
	 * @internal automaticaly called by search
	 * @param mixed $new_horizont time to set the horizont to (user-time)
	 */
	function check_move_horizont($new_horizont)
	{
		if ((int) $this->debug >= 2 || $this->debug == 'check_move_horizont')
		{
			$this->debug_message('bocal::check_move_horizont(%1) horizont=%2',true,$new_horizont,$this->config['horizont']);
		}
		$new_horizont = $this->date2ts($new_horizont,true);	// now we are in server-time, where this function operates

		if ($new_horizont > time()+1000*DAY_s)		// some user tries to "look" more then 1000 days in the future
		{
			if ($this->debug == 'check_move_horizont') $this->debug_message('bocal::check_move_horizont(%1) horizont=%2 new horizont more then 1000 days from now --> ignoring it',true,$new_horizont,$this->config['horizont']);
			return;
		}
		if ($new_horizont <= $this->config['horizont'])	// no move necessary
		{
			if ($this->debug == 'check_move_horizont') $this->debug_message('bocal::check_move_horizont(%1) horizont=%2 is bigger ==> nothing to do',true,$new_horizont,$this->config['horizont']);
			return;
		}
		if ($new_horizont < time()+31*DAY_s)
		{
			$new_horizont = time()+31*DAY_s;
		}
		$old_horizont = $this->config['horizont'];
		$this->config['horizont'] = $new_horizont;

		// create further recurances for all recuring and not yet (at the old horizont) ended events
		if (($recuring = $this->so->unfinished_recuring($old_horizont)))
		{
			foreach($this->read(array_keys($recuring)) as $cal_id => $event)
			{
				if ($this->debug == 'check_move_horizont')
				{
					$this->debug_message('bocal::check_move_horizont(%1): calling set_recurrences(%2,%3)',true,$new_horizont,$event,$old_horizont);
				}
				// insert everything behind max(cal_start), which can be less then $old_horizont because of bugs in the past
				$this->set_recurrences($event,$recuring[$cal_id]+1+$this->tz_offset_s);	// set_recurences operates in user-time!
			}
		}
		// update the horizont
		$config = CreateObject('phpgwapi.config','calendar');
		$config->save_value('horizont',$this->config['horizont'],'calendar');

		if ($this->debug == 'check_move_horizont') $this->debug_message('bocal::check_move_horizont(%1) new horizont=%2, exiting',true,$new_horizont,$this->config['horizont']);
	}

	/**
	 * set all recurances for an event til the defined horizont $this->config['horizont']
	 *
	 * @param array $event
	 * @param mixed $start=0 minimum start-time for new recurances or !$start = since the start of the event
	 */
	function set_recurrences($event,$start=0)
	{
		if ($this->debug && ((int) $this->debug >= 2 || $this->debug == 'set_recurrences' || $this->debug == 'check_move_horizont'))
		{
			$this->debug_message('bocal::set_recurrences(%1,%2)',true,$event,$start);
		}
		// check if the caller gave the participants and if not read them from the DB
		if (!isset($event['participants']))
		{
			list(,$event_read) = each($this->so->read($event['id']));
			$event['participants'] = $event_read['participants'];
		}
		if (!$start) $start = $event['start'];

		$events = array();
		$this->insert_all_repetitions($event,$start,$this->date2ts($this->config['horizont'],true),$events,null);
		$days = $this->so->get_recurrence_exceptions($event);
		$days = is_array($days) ? $days : array();
		//error_log('set_recurrences: days' . print_r($days, true) );
		foreach($events as $event)
		{
			//error_log('set_recurrences: start = ' . $event['start'] );
			if (in_array($event['start'], $days))
			{
				// we don't change the stati of recurrence exceptions
				$event['participants'] = array();
			}
			$this->so->recurrence($event['id'],$this->date2ts($event['start'],true),$this->date2ts($event['end'],true),$event['participants']);
		}
	}

	/**
	 * convert data read from the db, eg. convert server to user-time
	 *
	 * @param array &$events array of event-arrays (reference)
	 * @param $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 */
	function db2data(&$events,$date_format='ts')
	{
		if (!is_array($events)) echo "<p>bocal::db2data(\$events,$date_format) \$events is no array<br />\n".function_backtrace()."</p>\n";
		foreach($events as $id => $event)
		{
			// we convert here from the server-time timestamps to user-time and (optional) to a different date-format!
			foreach(array('start','end','modified','created','recur_enddate','recurrence') as $ts)
			{
				if (empty($event[$ts])) continue;

				$events[$id][$ts] = $this->date2usertime($event[$ts],$date_format);
			}
			// same with the recur exceptions
			if (isset($event['recur_exception']) && is_array($event['recur_exception']))
			{
				foreach($event['recur_exception'] as $n => $date)
				{
					$events[$id]['recur_exception'][$n] = $this->date2usertime($date,$date_format);
				}
			}
			// same with the alarms
			if (isset($event['alarm']) && is_array($event['alarm']))
			{
				foreach($event['alarm'] as $n => $alarm)
				{
					$events[$id]['alarm'][$n]['time'] = $this->date2usertime($alarm['time'],$date_format);
				}
			}
		}
	}

	/**
	 * convert a date from server to user-time
	 *
	 * @param int $date timestamp in server-time
	 * @param string $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 */
	function date2usertime($ts,$date_format='ts')
	{
		if (empty($ts)) return $ts;

		switch ($date_format)
		{
			case 'ts':
				return $ts + $this->tz_offset_s;

			case 'server':
				return $ts;

			case 'array':
				return $this->date2array((int) $ts,true);

			case 'string':
				return $this->date2string($ts,true);
		}
		return $this->date2string($ts,true,$date_format);
	}

	/**
	 * Reads a calendar-entry
	 *
	 * @param int|array|string $ids id or array of id's of the entries to read, or string with a single uid
	 * @param mixed $date=null date to specify a single event of a series
	 * @param boolean $ignore_acl should we ignore the acl, default False for a single id, true for multiple id's
	 * @param string $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in servertime, 'array'=array, or string with date-format
	 * @return boolean/array event or array of id => event pairs, false if the acl-check went wrong, null if $ids not found
	 */
	function read($ids,$date=null,$ignore_acl=False,$date_format='ts')
	{
		if ($date) $date = $this->date2ts($date);

		if ($ignore_acl || is_array($ids) || ($return = $this->check_perms(EGW_ACL_READ,$ids,0,$date_format,$date)))
		{
			if (is_array($ids) || !isset(self::$cached_event['id']) || self::$cached_event['id'] != $ids ||
				self::$cached_event_date_format != $date_format ||
				self::$cached_event['recur_type'] != MCAL_RECUR_NONE && !is_null($date) && self::$cached_event_date != $date || (!$date || self::$cached_event['start'] < $date))
			{
				$events = $this->so->read($ids,$date ? $this->date2ts($date,true) : 0);

				if ($events)
				{
					$this->db2data($events,$date_format);

					if (is_array($ids))
					{
						$return =& $events;
					}
					else
					{
						self::$cached_event = array_shift($events);
						self::$cached_event_date_format = $date_format;
						self::$cached_event_date = $date;
						$return =& self::$cached_event;
					}
				}
			}
			else
			{
				$return =& self::$cached_event;
			}
		}
		if ($this->debug && ($this->debug > 1 || $this->debug == 'read'))
		{
			$this->debug_message('bocal::read(%1,%2,%3,%4)=%5',True,$ids,$date,$ignore_acl,$date_format,$return);
		}
		return $return;
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
	 * @param array $event repeating event whos repetions should be inserted
	 * @param mixed $start start-date
	 * @param mixed $end end-date
	 * @param array $events where the repetions get inserted
	 * @param array $recur_exceptions with date (in Ymd) as key (and True as values)
	 */
	function insert_all_repetitions($event,$start,$end,&$events,$recur_exceptions)
	{
		if ((int) $this->debug >= 3 || $this->debug == 'set_recurrences' || $this->debug == 'check_move_horizont' || $this->debug == 'insert_all_repitions')
		{
			$this->debug_message('bocal::insert_all_repitions(%1,%2,%3,&$event,%4)',true,$event,$start,$end,$recur_exceptions);
		}
		$start_in = $start; $end_in = $end;

		$start = $this->date2ts($start);
		$end   = $this->date2ts($end);
		$event_start_ts = $this->date2ts($event['start']);
		$event_end_ts   = $this->date2ts($event['end']);

		if ($this->debug && ((int) $this->debug > 3 || $this->debug == 'insert_all_repetions' || $this->debug == 'check_move_horizont' || $this->debug == 'insert_all_repitions'))
		{
			$this->debug_message('bocal::insert_all_repetions(%1,start=%2,end=%3,,%4) starting...',True,$event,$start_in,$end_in,$recur_exceptions);
		}
		$id = $event['id'];
		$event_start_arr = $this->date2array($event['start']);
		// to be able to calculate the repetitions as difference to the start-date,
		// both need to be calculated without daylight saving: mktime(,,,,,,0)
		$event_start_daybegin_ts = adodb_mktime(0,0,0,$event_start_arr['month'],$event_start_arr['day'],$event_start_arr['year'],0);

		if($event['recur_enddate'])
		{
			$recur_end_ymd = $this->date2string($event['recur_enddate']);
		}
		else
		{
			$recur_end_ymd = $this->date2string(adodb_mktime(0,0,0,1,1,5+adodb_date('Y')));	// go max. 5 years from now
		}

		// We only need to compute the intersection between our reported time-span and the live-time of the event
		// To catch all multiday repeated events (eg. second days), we need to start the length of the even earlier
		// then our original report-starttime
		$event_length = $event_end_ts - $event_start_ts;
		$start_ts = max($event_start_ts,$start-$event_length);
		// we need to add 26*60*60-1 to the recur_enddate as its hour+minute are 0
		$end_ts   = $event['recur_enddate'] ? min($this->date2ts($event['recur_enddate'])+DAY_s-1,$end) : $end;

		for($ts = $start_ts; $ts < $end_ts; $ts += DAY_s)
		{
			$search_date_ymd = (int)$this->date2string($ts);

			//error_log('insert_all_repetitions search_date = ' . $search_date_ymd . ' => ' . print_r($recur_exceptions, true));

			$have_exception = !is_null($recur_exceptions) && isset($recur_exceptions[$search_date_ymd]);

			if (!$have_exception)	// no execption by an edited event => check the deleted ones
			{
				foreach((array)$event['recur_exception'] as $exception_ts)
				{
					if (($have_exception = $search_date_ymd == (int)$this->date2string($exception_ts))) break;
				}
			}
			if ($this->debug && ((int) $this->debug > 3 || $this->debug == 'insert_all_repetions' || $this->debug == 'check_move_horizont' || $this->debug == 'insert_all_repitions'))
			{
				$this->debug_message('bocal::insert_all_repetions(...,%1) checking recur_exceptions[%2] and event[recur_exceptions]=%3 ==> %4',False,
					$recur_exceptions,$search_date_ymd,$event['recur_exception'],$have_exception);
			}
			if ($have_exception)
			{
				continue;	// we already have an exception for that date
			}
			$search_date_year = adodb_date('Y',$ts);
			$search_date_month = adodb_date('m',$ts);
			$search_date_day = adodb_date('d',$ts);
			$search_date_dow = adodb_date('w',$ts);
			// to be able to calculate the repetitions as difference to the start-date,
			// both need to be calculated without daylight saving: mktime(,,,,,,0)
			$search_beg_day = adodb_mktime(0,0,0,$search_date_month,$search_date_day,$search_date_year,0);

			if ($search_date_ymd == $event_start_arr['full'])	// first occurence
			{
				$this->add_adjusted_event($events,$event,$search_date_ymd);
				continue;
			}
			$freq = $event['recur_interval'] ? $event['recur_interval'] : 1;
			$type = $event['recur_type'];
			switch($type)
			{
				case MCAL_RECUR_DAILY:
					if($this->debug > 4)
					{
						echo '<!-- check_repeating_events - MCAL_RECUR_DAILY - '.$id.' -->'."\n";
					}
					if ($freq == 1 && $event['recur_enddate'] && $search_date_ymd <= $recur_end_ymd)
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
					// we use round(,1) to deal with changing daylight saving
					if (floor(round(($search_beg_day - $event_start_daybegin_ts)/WEEK_s,1)) % $freq)
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
					if ((($search_date_year - $event_start_arr['year']) * 12 + $search_date_month - $event_start_arr['month']) % $freq)
					{
						continue;
					}

					if (($GLOBALS['egw']->datetime->day_of_week($event_start_arr['year'],$event_start_arr['month'],$event_start_arr['day']) == $GLOBALS['egw']->datetime->day_of_week($search_date_year,$search_date_month,$search_date_day)) &&
						(ceil($event_start_arr['day']/7) == ceil($search_date_day/7)))
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
				case MCAL_RECUR_MONTHLY_MDAY:
					if ((($search_date_year - $event_start_arr['year']) * 12 + $search_date_month - $event_start_arr['month']) % $freq)
					{
						continue;
					}
					if ($search_date_day == $event_start_arr['day'])
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
				case MCAL_RECUR_YEARLY:
					if (($search_date_year - $event_start_arr['year']) % $freq)
					{
						continue;
					}
					if (adodb_date('dm',$ts) == adodb_date('dm',$event_start_daybegin_ts))
					{
						$this->add_adjusted_event($events,$event,$search_date_ymd);
					}
					break;
			} // switch(recur-type)
		} // for($date = ...)
		if ($this->debug && ((int) $this->debug > 2 || $this->debug == 'insert_all_repetions' || $this->debug == 'check_move_horizont' || $this->debug == 'insert_all_repitions'))
		{
			$this->debug_message('bocal::insert_all_repetions(%1,start=%2,end=%3,events,exections=%4) events=%5',True,$event,$start_in,$end_in,$recur_exceptions,$events);
		}
	}

	/**
	 * Adds one repetion of $event for $date_ymd to the $events array, after adjusting its start- and end-time
	 *
	 * @param array $events array in which the event gets inserted
	 * @param array $event event to insert, it has start- and end-date of the first recurrence, not of $date_ymd
	 * @param int|string $date_ymd of the date of the event
	 */
	function add_adjusted_event(&$events,$event,$date_ymd)
	{
		$event_in = $event;
		// calculate the new start- and end-time
		$length_s = $this->date2ts($event['end']) - $this->date2ts($event['start']);
		$event_start_arr = $this->date2array($event['start']);

		$date_arr = $this->date2array((string) $date_ymd);
		$date_arr['hour'] = $event_start_arr['hour'];
		$date_arr['minute'] = $event_start_arr['minute'];
		$date_arr['second'] = $event_start_arr['second'];
		unset($date_arr['raw']);	// else date2ts would use it
		$event['start'] = $this->date2ts($date_arr);
		$event['end'] = $event['start'] + $length_s;

		$events[] = $event;

		if ($this->debug && ($this->debug > 2 || $this->debug == 'add_adjust_event'))
		{
			$this->debug_message('bocal::add_adjust_event(,%1,%2) as %3',True,$event_in,$date_ymd,$event);
		}
	}

	/**
	 * Fetch information about a resource
	 *
	 * We do some caching here, as the resource itself might not do it.
	 *
	 * @param string $uid string with one-letter resource-type and numerical resource-id, eg. "r19"
	 * @return array|boolean array with keys res_id,cat_id,name,useable (name definied by max_quantity in $this->resources),rights,responsible or false if $uid is not found
	 */
	function resource_info($uid)
	{
		static $res_info_cache = array();

		if (!isset($res_info_cache[$uid]))
		{
			if (is_numeric($uid))
			{
				$info = array(
					'res_id'    => $uid,
					'email' => $GLOBALS['egw']->accounts->id2name($uid,'account_email'),
					'name'  => trim($GLOBALS['egw']->accounts->id2name($uid,'account_firstname'). ' ' .
					$GLOBALS['egw']->accounts->id2name($uid,'account_lastname')),
					'type'  => $GLOBALS['egw']->accounts->get_type($uid),
				);
			}
			else
			{
				list($info) = $this->resources[$uid[0]]['info'] ? ExecMethod($this->resources[$uid[0]]['info'],substr($uid,1)) : false;
				if ($info)
				{
					$info['type'] = $uid[0];
					if (!$info['email'] && $info['responsible'])
					{
						$info['email'] = $GLOBALS['egw']->accounts->id2name($info['responsible'],'account_email');
					}
				}
			}
			$res_info_cache[$uid] = $info;
		}
		if ($this->debug && ($this->debug > 2 || $this->debug == 'resource_info'))
		{
			$this->debug_message('bocal::resource_info(%1) = %2',True,$uid,$res_info_cache[$uid]);
		}
		return $res_info_cache[$uid];
	}

	/**
	 * Checks if the current user has the necessary ACL rights
	 *
	 * The check is performed on an event or generally on the cal of an other user
	 *
	 * Note: Participating in an event is considered as haveing read-access on that event,
	 *	even if you have no general read-grant from that user.
	 *
	 * @param int $needed necessary ACL right: EGW_ACL_{READ|EDIT|DELETE}
	 * @param mixed $event event as array or the event-id or 0 for a general check
	 * @param int $other uid to check (if event==0) or 0 to check against $this->user
	 * @param string $date_format='ts' date-format used for reading: 'ts'=timestamp, 'array'=array, 'string'=iso8601 string for xmlrpc
	 * @param mixed $date_to_read=null date used for reading, internal param for the caching
	 * @return boolean true permission granted, false for permission denied or null if event not found
	 */
	function check_perms($needed,$event=0,$other=0,$date_format='ts',$date_to_read=null)
	{
		$event_in = $event;
		if ($other && !is_numeric($other))
		{
			$resource = $this->resource_info($other);
			return $needed & $resource['rights'];
		}
		if (is_int($event) && $event == 0)
		{
			$owner = $other ? $other : $this->user;
		}
		else
		{
			if (!is_array($event))
			{
				$event = $this->read($event,$date_to_read,True,$date_format);	// = no ACL check !!!
			}
			if (!is_array($event))
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
				}
				return null;	// event not found
			}
			$owner = $event['owner'];
			$private = !$event['public'];
		}
		$user = $GLOBALS['egw_info']['user']['account_id'];
		$grants = $this->grants[$owner];
		if (is_array($event) && $needed == EGW_ACL_READ)
		{
			// Check if the $user is one of the participants or has a read-grant from one of them
			// in that case he has an implicite READ grant for that event
			//
			if ($event['participants'] && is_array($event['participants']))
			{
				foreach($event['participants'] as $uid => $accept)
				{
					if ($uid == $user || $uid < 0 && in_array($user,$GLOBALS['egw']->accounts->members($uid,true)))
					{
						// if we are a participant, we have an implicite READ and PRIVAT grant
						$grants |= EGW_ACL_READ;
						break;
					}
					elseif ($this->grants[$uid] & EGW_ACL_READ)
					{
						// if we have a READ grant from a participant, we dont give an implicit privat grant too
						$grants |= EGW_ACL_READ;
						// we cant break here, as we might be a participant too, and would miss the privat grant
					}
					elseif (!is_numeric($uid))
					{
						// if we have a resource as participant
						$resource = $this->resource_info($uid);
						$grants |= $resource['rights'];
					}
				}
			}
			else
			{
				error_log(__METHOD__." no participants for event:".print_r($event,true));
			}
		}
		if ($GLOBALS['egw']->accounts->get_type($owner) == 'g' && $needed == EGW_ACL_ADD)
		{
			$access = False;	// a group can't be the owner of an event
		}
		else
		{
			$access = $user == $owner || $grants & $needed && (!$private || $grants & EGW_ACL_PRIVATE);
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
	 * @param mixed $date date to convert, should be one of the following types
	 *	string (!) in form YYYYMMDD or iso8601 YYYY-MM-DDThh:mm:ss or YYYYMMDDThhmmss
	 *	int already a timestamp
	 *	array with keys 'second', 'minute', 'hour', 'day' or 'mday' (depricated !), 'month' and 'year'
	 * @param boolean $user2server_time conversation between user- and server-time default False == Off
	 */
	function date2ts($date,$user2server=False)
	{
		$date_in = $date;

		switch(gettype($date))
		{
			case 'string':	// YYYYMMDD or iso8601 YYYY-MM-DDThh:mm:ss[Z|[+-]hh:mm] string
			if (is_numeric($date) && $date > 21000000)
			{
				$date = (int) $date;	// this is already as timestamp
				break;
			}
			// evaluate evtl. added timezone
			if (strlen($date) > 12)
			{
				if (substr($date,-1) == 'Z')
				{
					$time_offset = date('Z');
				}
				elseif(preg_match('/([+-]{1})([0-9]{2}):?([0-9]{2})$/',$date,$matches))
				{
					$time_offset = date('Z')-($matches[1] == '+' ? 1 : -1)*(3600*$matches[2]+60*$matches[3]);
				}
			}
			// removing all non-nummerical chars, gives YYYYMMDDhhmmss, independent of the iso8601 format
			$date = str_replace(array('-',':','T','Z',' ','+'),'',$date);
			$date = array(
				'year'   => (int) substr($date,0,4),
				'month'  => (int) substr($date,4,2),
				'day'    => (int) substr($date,6,2),
				'hour'   => (int) substr($date,8,2),
				'minute' => (int) substr($date,10,2),
				'second' => (int) substr($date,12,2),
			);
			// fall-through
			case 'array':	// day, month and year keys
			if (isset($date['raw']) && $date['raw'])	// we already have a timestamp
			{
				$date = $date['raw'];
				break;
			}
			if (!isset($date['year']) && isset($date['full']))
			{
				$date['year']  = (int) substr($date['full'],0,4);
				$date['month'] = (int) substr($date['full'],4,2);
				$date['day']   = (int) substr($date['full'],6,2);
			}
			$date = adodb_mktime((int)$date['hour'],(int)$date['minute'],(int)$date['second'],(int)$date['month'],
			(int) (isset($date['day']) ? $date['day'] : $date['mday']),(int)$date['year']);
			break;
			case 'integer':		// already a timestamp
			break;
			default:		// eg. boolean, means now in user-time (!)
			$date = $this->now_su;
			break;
		}
		if ($time_offset)
		{
			$date += $time_offset;
			if (!$user2server) $date += $this->tz_offset_s;	// we have to return user time!
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
	 * @param mixed $date date to convert
	 * @param boolean $server2user_time conversation between user- and server-time default False == Off
	 * @return array with keys 'second', 'minute', 'hour', 'day', 'month', 'year', 'raw' (timestamp) and 'full' (Ymd-string)
	 */
	function date2array($date,$server2user=False)
	{
		$date_called = $date;

		if (!is_array($date) || count($date) < 8 || $server2user)	// do we need a conversation
		{
			if (!is_int($date))
			{
				$date = $this->date2ts($date);
			}
			if ($server2user)
			{
				$date += $this->tz_offset_s;
			}
			$arr = array();
			foreach(array('second'=>'s','minute'=>'i','hour'=>'H','day'=>'d','month'=>'m','year'=>'Y','full'=>'Ymd') as $key => $frmt)
			{
				$arr[$key] = (int) adodb_date($frmt,$date);
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
	 * @param mixed $date integer timestamp or array with ('year','month',..,'second') to convert
	 * @param boolean $server2user_time conversation between user- and server-time default False == Off, not used if $format ends with \Z
	 * @param string $format='Ymd' format of the date to return, eg. 'Y-m-d\TH:i:sO' (2005-11-01T15:30:00+0100)
	 * @return string date formatted according to $format
	 */
	function date2string($date,$server2user=False,$format='Ymd')
	{
		$date_in = $date;

		if (!$format) $format = 'Ymd';

		if (is_array($date) && isset($date['full']) && !$server2user && $format == 'Ymd')
		{
			$date = $date['full'];
		}
		else
		{
			$date = $this->date2ts($date,False);

			// if timezone is requested, we dont need to convert to user-time
			if (($tz_used = substr($format,-1)) == 'O' || $tz_used == 'Z') $server2user = false;

			if ($server2user && substr($format,-1) )
			{
				$date += $this->tz_offset_s;
			}
			if (substr($format,-2) == '\\Z')	// GMT aka. Zulu time
			{
				$date = adodb_gmdate($format,$date);
			}
			else
			{
				$date = adodb_date($format,$date);
			}
		}
		if ($this->debug && ($this->debug > 3 || $this->debug == 'date2string'))
		{
			$this->debug_message('bocal::date2string(%1,server2user=%2,format=%3)=%4)',False,$date_in,$server2user,$format,$date);
		}
		return $date;
	}

	/**
	 * Formats a date given as timestamp or array
	 *
	 * @param mixed $date integer timestamp or array with ('year','month',..,'second') to convert
	 * @param string|boolean $format='' default common_prefs[dateformat], common_prefs[timeformat], false=time only, true=date only
	 * @return string the formated date (incl. time)
	 */
	function format_date($date,$format='')
	{
		$timeformat = $this->common_prefs['timeformat'] != '12' ? 'H:i' : 'h:i a';
		if ($format === '')		// date+time wanted
		{
			$format = $this->common_prefs['dateformat'].', '.$timeformat;
		}
		elseif ($format === false)	// time wanted
		{
			$format = $timeformat;
		}
		elseif ($format === true)
		{
			$format = $this->common_prefs['dateformat'];
		}
		return adodb_date($format,$this->date2ts($date,False));
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
	 * @param string $msg message with parameters/variables like lang(), eg. '%1'
	 * @param boolean $backtrace=True include a function-backtrace, default True=On
	 *	should only be set to False=Off, if your code ensures a call with backtrace=On was made before !!!
	 * @param mixed $param a variable number of parameters, to be inserted in $msg
	 *	arrays get serialized with print_r() !
	 */
	function debug_message($msg,$backtrace=True)
	{
		static $acl2string = array(
			0               => 'ACL-UNKNOWN',
			EGW_ACL_READ    => 'ACL_READ',
			EGW_ACL_ADD     => 'ACL_ADD',
			EGW_ACL_EDIT    => 'ACL_EDIT',
			EGW_ACL_DELETE  => 'ACL_DELETE',
			EGW_ACL_PRIVATE => 'ACL_PRIVATE',
			EGW_ACL_FREEBUSY => 'ACL_FREEBUSY',
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
							$param = (isset($acl2string[$param]) ? $acl2string[$param] : $acl2string[0])." ($param)";
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
					case 'integer':
						if ($param >= mktime(0,0,0,1,1,2000)) $param = adodb_date('Y-m-d H:i:s',$param)." ($param)";
						break;
				}
			}
			$msg = str_replace('%'.($i-1),$param,$msg);
		}
		echo '<p>'.$msg."<br>\n".($backtrace ? 'Backtrace: '.function_backtrace(1)."</p>\n" : '').str_repeat(' ',4096);
	}

	/**
	 * Formats one or two dates (range) as long date (full monthname), optionaly with a time
	 *
	 * @param mixed $first first date
	 * @param mixed $last=0 last date if != 0 (default)
	 * @param boolean $display_time=false should a time be displayed too
	 * @param boolean $display_day=false should a day-name prefix the date, eg. monday June 20, 2006
	 * @return string with formated date
	 */
	function long_date($first,$last=0,$display_time=false,$display_day=false)
	{
		$first = $this->date2array($first);
		if ($last)
		{
			$last = $this->date2array($last);
		}
		$datefmt = $this->common_prefs['dateformat'];
		$timefmt = $this->common_prefs['timeformat'] == 12 ? 'h:i a' : 'H:i';

		$month_before_day = strtolower($datefmt[0]) == 'm' ||
			strtolower($datefmt[2]) == 'm' && $datefmt[4] == 'd';

		if ($display_day)
		{
			$range = lang(adodb_date('l',$first['raw'])).($this->common_prefs['dateformat'][0] != 'd' ? ' ' : ', ');
		}
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
						if ($display_time)
						{
							$range .= ' '.adodb_date($timefmt,$first['raw']);
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
						if ($display_time)
						{
							$range .= ' '.adodb_date($timefmt,$first['raw']);
						}
						$range .= ' - ';
					}
					$range .= ' ' . $last['day'] . ($datefmt[1] == '.' ? '.' : '');
					break;
				case 'm':
				case 'M':
					$range .= ' '.lang(strftime('%B',$month_before_day ? $first['raw'] : $last['raw'])) . ' ';
					break;
				case 'Y':
					if ($datefmt[0] != 'm')
					{
						$range .= ' ' . ($datefmt[0] == 'Y' ? $first['year'].($datefmt[2] == 'd' ? ', ' : ' ') : $last['year'].' ');
					}
					break;
			}
		}
		if ($display_time && $last)
		{
			$range .= ' '.adodb_date($timefmt,$last['raw']);
		}
		if ($datefmt[4] == 'Y' && $datefmt[0] == 'm')
		{
			$range .= ', ' . $last['year'];
		}
		return $range;
	}

	/**
	 * Displays a timespan, eg. $both ? "10:00 - 13:00: 3h" (10:00 am - 1 pm: 3h) : "10:00 3h" (10:00 am 3h)
	 *
	 * @param int $start_m start time in minutes since 0h
	 * @param int $end_m end time in minutes since 0h
	 * @param boolean $both=false display the end-time too, duration is always displayed
	 */
	function timespan($start_m,$end_m,$both=false)
	{
		$duration = $end_m - $start_m;
		if ($end_m == 24*60-1) ++$duration;
		$duration = floor($duration/60).lang('h').($duration%60 ? $duration%60 : '');

		$timespan = $t = $GLOBALS['egw']->common->formattime(sprintf('%02d',$start_m/60),sprintf('%02d',$start_m%60));

		if ($both)	// end-time too
		{
			$timespan .= ' - '.$GLOBALS['egw']->common->formattime(sprintf('%02d',$end_m/60),sprintf('%02d',$end_m%60));
			// dont double am/pm if they are the same in both times
			if ($this->common_prefs['timeformat'] == 12 && substr($timespan,-2) == substr($t,-2))
			{
				$timespan = str_replace($t,substr($t,0,-3),$timespan);
			}
			$timespan .= ':';
		}
		return $timespan . ' ' . $duration;
	}

	/**
	* Converts a participant into a (readable) user- or resource-name
	*
	* @param string|int $id id of user or resource
	* @return string with name
	*/
	function participant_name($id,$use_type=false)
	{
		static $id2lid = array();

		if ($use_type && $use_type != 'u') $id = $use_type.$id;

		if (!isset($id2lid[$id]))
		{
			if (!is_numeric($id))
			{
				$id2lid[$id] = '#'.$id;
				if (($info = $this->resource_info($id)))
				{
					$id2lid[$id] = $info['name'] ? $info['name'] : $info['email'];
				}
			}
			else
			{
				$id2lid[$id] = $GLOBALS['egw']->common->grab_owner_name($id);
			}
		}
		return $id2lid[$id];
	}

	/**
	* Converts participants array of an event into array of (readable) participant-names with status
	*
	* @param array $event event-data
	* @param boolean $long_status=false should the long/verbose status or an icon be use
	* @param boolean $show_group_invitation=false show group-invitations (status == 'G') or not (default)
	* @return array with id / names with status pairs
	*/
	function participants($event,$long_status=false,$show_group_invitation=false)
	{
		//_debug_array($event);
		$names = array();
		foreach($event['participants'] as $id => $status)
		{
			$quantity = $role = '';
			if (strlen($status) > 1 && preg_match('/^.([0-9]*)(.*)$/',$status,$matches))
			{
				if ((int)$matches[1] > 1) $quantity = (int)$matches[1];
				$role = $matches[2];
			}
			$status = $status[0];

			if ($status == 'G' && !$show_group_invitation) continue;	// dont show group-invitation

			if (!$long_status)
			{
				switch($status[0])
				{
					case 'A':	// accepted
						$status = html::image('calendar','agt_action_success',$this->verbose_status[$status]);
						break;
					case 'R':	// rejected
						$status = html::image('calendar','agt_action_fail',$this->verbose_status[$status]);
						break;
					case 'T':	// tentative
						$status = html::image('calendar','tentative',$this->verbose_status[$status]);
						break;
					case 'U':	// no response = unknown
						$status = html::image('calendar','cnr-pending',$this->verbose_status[$status]);
						break;
					case 'G':	// group invitation
						// Todo: Image, seems not to be used
						$status = '('.$this->verbose_status[$status].')';
						break;
				}
			}
			else
			{
				$status = '('.$this->verbose_status[$status].')';
			}
			$names[$id] = $this->participant_name($id).($quantity ? ' ('.$quantity.')' : '').
				' '.$status.($role ? ' '.lang(str_replace('X-','',$role)) : '');
		}
		return $names;
	}

	/**
	* Converts category string of an event into array of (readable) category-names
	*
	* @param string $category cat-id (multiple id's commaseparated)
	* @param int $color color of the category, if multiple cats, the color of the last one with color is returned
	* @return array with id / names
	*/
	function categories($category,&$color)
	{
		static $id2cat = array();
		$cats = array();
		$color = 0;
		if (!is_object($this->cats))
		{
			$this->cats = CreateObject('phpgwapi.categories','','calendar');
		}
		foreach(explode(',',$category) as $cat_id)
		{
			if (!$cat_id) continue;

			if (!isset($id2cat[$cat_id]))
			{
				list($id2cat[$cat_id]) = $this->cats->return_single($cat_id);
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

	/**
	 *  This is called only by list_cals().  It was moved here to remove fatal error in php5 beta4
	 */
	function _list_cals_add($id,&$users,&$groups)
	{
		$name = $GLOBALS['egw']->common->grab_owner_name($id);
		if (($type = $GLOBALS['egw']->accounts->get_type($id)) == 'g')
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
	 *
	 * @return array alphabeticaly sorted array with groups first and then users: $name => array('grantor'=>$id,'value'=>['g_'.]$id,'name'=>$name)
	 */
	function list_cals()
	{
		$users = $groups = array();
		foreach($this->grants as $id => $rights)
		{
			$this->_list_cals_add($id,$users,$groups);
		}
		if ($memberships = $GLOBALS['egw']->accounts->membership($GLOBALS['egw_info']['user']['account_id']))
		{
			foreach($memberships as $group_info)
			{
				$this->_list_cals_add($group_info['account_id'],$users,$groups);

				if ($account_perms = $GLOBALS['egw']->acl->get_ids_for_location($group_info['account_id'],EGW_ACL_READ,'calendar'))
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
	 * Convert the recure-information of an event, into a human readable string
	 *
	 * @param array $event
	 * @return string
	 */
	function recure2string($event)
	{
		$str = '';
		// Repeated Events
		if($event['recur_type'] != MCAL_RECUR_NONE)
		{
			$str = lang($this->recur_types[$event['recur_type']]);

			$str_extra = array();
			if ($event['recur_enddate'])
			{
				$str_extra[] = lang('ends').': '.lang($this->format_date($event['recur_enddate'],'l')).', '.$this->long_date($event['recur_enddate']).' ';
			}
			// only weekly uses the recur-data (days) !!!
			if($event['recur_type'] == MCAL_RECUR_WEEKLY)
			{
				$repeat_days = array();
				foreach ($this->recur_days as $mcal_mask => $dayname)
				{
					if ($event['recur_data'] & $mcal_mask)
					{
						$repeat_days[] = lang($dayname);
					}
				}
				if(count($repeat_days))
				{
					$str_extra[] = lang('days repeated').': '.implode(', ',$repeat_days);
				}
			}
			if($event['recur_interval'] > 1)
			{
				$str_extra[] = lang('Interval').': '.$event['recur_interval'];
			}

			if(count($str_extra))
			{
				$str .= ' ('.implode(', ',$str_extra).')';
			}
		}
		return $str;
	}

	/**
	 * Read the holidays for a given $year
	 *
	 * The holidays get cached in the session (performance), so changes in holidays or birthdays do NOT affect a current session!!!
	 *
	 * @param int $year=0 year, defaults to 0 = current year
	 * @return array indexed with Ymd of array of holidays. A holiday is an array with the following fields:
	 *	index: numerical unique id
	 *	locale: string, 2-char short for the nation
	 *	name: string
	 *	day: numerical day in month
	 *	month: numerical month
	 *	occurence: numerical year or 0 for every year
	 *	dow: day of week, 0=sunday, .., 6= saturday
	 *	observande_rule: boolean
	 */
	function read_holidays($year=0)
	{
		if (!$year) $year = (int) date('Y',$this->now_su);

		if (!$this->cached_holidays)	// try reading the holidays from the session
		{
			$this->cached_holidays = $GLOBALS['egw']->session->appsession('holidays','calendar');
		}
		if (!isset($this->cached_holidays[$year]))
		{
			if (!is_object($this->holidays))
			{
				$this->holidays = CreateObject('calendar.boholiday');
			}
			$this->holidays->prepare_read_holidays($year);
			$this->cached_holidays[$year] = $this->holidays->read_holiday();

			// search for birthdays
			if ($GLOBALS['egw_info']['server']['hide_birthdays'] != 'yes')
			{
				$contacts = CreateObject('phpgwapi.contacts');
				$bdays =& $contacts->read(0,0,array('id','n_family','n_given','n_prefix','n_middle','bday'),'',"bday=!'',n_family=!''",'ASC','bday');
				if ($bdays)
				{
					// sort by month and day only
					usort($bdays,create_function('$a,$b','return (int) $a[\'bday\'] == (int) $b[\'bday\'] ? strcmp($a[\'bday\'],$b[\'bday\']) : (int) $a[\'bday\'] - (int) $b[\'bday\'];'));
					foreach($bdays as $pers)
					{
						list($m,$d,$y) = explode('/',$pers['bday']);
						if ($y > $year) continue; 	// not yet born
						$this->cached_holidays[$year][sprintf('%04d%02d%02d',$year,$m,$d)][] = array(
							'day'       => $d,
							'month'     => $m,
							'occurence' => 0,
							'name'      => lang('Birthday').' '.($pers['n_given'] ? $pers['n_given'] : $pers['n_prefix']).' '.$pers['n_middle'].' '.
								$pers['n_family'].($y && !$GLOBALS['egw_info']['server']['hide_birthdays'] ? ' ('.$y.')' : ''),
							'birthyear' => $y,	// this can be used to identify birthdays from holidays
						);
					}
				}
			}
			// store holidays and birthdays in the session
			$this->cached_holidays = $GLOBALS['egw']->session->appsession('holidays','calendar',$this->cached_holidays);
		}
		if ((int) $this->debug >= 2 || $this->debug == 'read_holidays')
		{
			$this->debug_message('bocal::read_holidays(%1)=%2',true,$year,$this->cached_holidays[$year]);
		}
		return $this->cached_holidays[$year];
	}

	/**
	 * get title for an event identified by $event
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param int|array $entry int cal_id or array with event
	 * @param string|boolean string with title, null if not found or false if not read perms
	 */
	function link_title($event)
	{
		if (!is_array($event) && (int) $event > 0)
		{
			$event = $this->read($event);
		}
		if (!is_array($event))
		{
			return $event;
		}
		return $this->format_date($event['start']) . ': ' . $event['title'];
	}

	/**
	 * query calendar for events matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @return array with cal_id - title pairs of the matching entries
	 */
	function link_query($pattern)
	{
		$result = array();
		foreach((array) $this->search(array('query' => $pattern)) as $event)
		{
			$result[$event['id']] = $this->link_title($event);
		}
		return $result;
	}

	/**
	 * Check access to the projects file store
	 *
	 * @param int $id id of entry
	 * @param int $check EGW_ACL_READ for read and EGW_ACL_EDIT for write or delete access
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path)
	{
		return $this->check_perms($check,$id);
	}

	/**
	 * sets the default prefs, if they are not already set (on a per pref. basis)
	 *
	 * It sets a flag in the app-session-data to be called only once per session
	 */
	function check_set_default_prefs()
	{
		if ($this->cal_prefs['interval'] && ($set = $GLOBALS['egw']->session->appsession('default_prefs_set','calendar')))
		{
			return;
		}
		$GLOBALS['egw']->session->appsession('default_prefs_set','calendar','set');

		$default_prefs =& $GLOBALS['egw']->preferences->default['calendar'];

		if (!($planner_start_with_group = $GLOBALS['egw']->accounts->name2id('Default')))
		{
			$planner_start_with_group = '0';
		}
		$subject = lang('Calendar Event') . ' - $$action$$: $$startdate$$ $$title$$'."\n";
		$defaults = array(
			'defaultcalendar' => 'week',
			'mainscreen_showevents' => '0',
			'summary'         => 'no',
			'receive_updates' => 'no',
			'update_format'   => 'extended',
			'notifyAdded'     => $subject . lang ('You have a meeting scheduled for %1','$$startdate$$'),
			'notifyCanceled'  => $subject . lang ('Your meeting scheduled for %1 has been canceled','$$startdate$$'),
			'notifyModified'  => $subject . lang ('Your meeting that had been scheduled for %1 has been rescheduled to %2','$$olddate$$','$$startdate$$'),
			'notifyDisinvited'=> $subject . lang ('You have been disinvited from the meeting at %1','$$startdate$$'),
			'notifyResponse'  => $subject . lang ('On %1 %2 %3 your meeting request for %4','$$date$$','$$fullname$$','$$action$$','$$startdate$$'),
			'notifyAlarm'     => lang('Alarm for %1 at %2 in %3','$$title$$','$$startdate$$','$$location$$')."\n".lang ('Here is your requested alarm.'),
			'show_rejected'   => '0',
			'display_status'  => '1',
			'weekdaystarts'   => 'Monday',
			'workdaystarts'   => '9',
			'workdayends'     => '17',
			'interval'        => '30',
			'defaultlength'   => '60',
			'planner_start_with_group' => $planner_start_with_group,
			'defaultfilter'   => 'all',
			'default_private' => '0',
			'defaultresource_sel' => 'resources',
		);
		foreach($defaults as $var => $default)
		{
			if (!isset($default_prefs[$var]) || (string)$default_prefs[$var] == '')
			{
				$GLOBALS['egw']->preferences->add('calendar',$var,$default,'default');
				$this->cal_prefs[$var] = $default;
				$need_save = True;
			}
		}
		if ($need_save)
		{
			$GLOBALS['egw']->preferences->save_repository(False,'default');
		}
	}

	/**
	 * Get the freebusy URL of a user
	 *
	 * @param int|string $user account_id or account_lid
	 * @param string $pw=null password
	 */
	static function freebusy_url($user,$pw=null)
	{
		if (is_numeric($user)) $user = $GLOBALS['egw']->accounts->id2name($user);

		return (!$GLOBALS['egw_info']['server']['webserver_url'] || $GLOBALS['egw_info']['server']['webserver_url'][0] == '/' ?
			($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'] : '').
			$GLOBALS['egw_info']['server']['webserver_url'].'/calendar/freebusy.php?user='.urlencode($user).
			($pw ? '&password='.urlencode($pw) : '');
	}

	/**
	 * Check if the event is the whole day
	 *
	 * @param array $event event
	 * @return boolean true if whole day event, false othwerwise
	 */
	function isWholeDay($event)
	{
		// check if the event is the whole day
		$start = $this->date2array($event['start']);
		$end = $this->date2array($event['end']);

		return !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;
	}
}
