<?php
/**
 * EGroupware - Calendar's buisness-object - access only
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2004-11 by RalfBecker-At-outdoor-training.de
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
 * Allows to invite an other user (if configured to be used!)
 */
define('EGW_ACL_INVITE',EGW_ACL_CUSTOM_3);

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
	 * @var int $now timestamp in server-time
	 */
	var $now;

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
		'D' => 'Delegated',
		'G' => 'Group invitation',
	);
	/**
	 * @var array recur_types translates MCAL recur-types to verbose labels
	 */
	var $recur_types = Array(
		MCAL_RECUR_NONE         => 'No recurrence',
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
	 * Standard iCal attendee roles
	 *
	 * @var array
	 */
	var $roles = array(
		'REQ-PARTICIPANT' => 'Requested',
		'CHAIR'           => 'Chair',
		'OPT-PARTICIPANT' => 'Optional',
		'NON-PARTICIPANT' => 'None',
	);
	/**
	 * @var array $resources registered scheduling resources of the calendar (gets cached in the session for performance reasons)
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
	 * @var boholiday
	 */
	var $holidays;
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
	 * Instance of the categories class
	 *
	 * @var categories
	 */
	var $categories;

	/**
	 * Does a user require an extra invite grant, to be able to invite an other user, default no
	 *
	 * @var boolean
	 */
	public $require_acl_invite = false;

	/**
	 * if the number of selected users for a view exeeds this number a view is consolidated (5 is set as default)
	 * @var int
	 */
	public $calview_no_consolidate = 5;

	/**
	 * Warnings to show in regular UI
	 *
	 * @var array
	 */
	var $warnings = array();

	/**
	 * Constructor
	 */
	function __construct()
	{
		if ($this->debug > 0) $this->debug_message('calendar_bo::bocal() started',True,$param);

		$this->so = new calendar_so();
		$this->datetime = $GLOBALS['egw']->datetime;

		$this->common_prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		$this->cal_prefs =& $GLOBALS['egw_info']['user']['preferences']['calendar'];

		$this->now = time();
		$this->now_su = egw_time::server2user($this->now,'ts');

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

		$this->config = config::read('calendar');	// only used for horizont, regular calendar config is under phpgwapi
		$this->calview_no_consolidate = ($GLOBALS['egw_info']['server']['calview_no_consolidate']?$GLOBALS['egw_info']['server']['calview_no_consolidate']:5);
		$this->require_acl_invite = $GLOBALS['egw_info']['server']['require_acl_invite'];

		$this->categories = new categories($this->user,'calendar');
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
		foreach((array)$ids as $id)
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
		//error_log(__METHOD__.'('.array2string($ids).')='.array2string($data).' '.function_backtrace());
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
	 * Resolve users to add memberships for users and members for groups
	 *
	 * @param int|array $_users
	 * @param boolean $no_enum_groups=true
	 * @param boolean $ignore_acl=false
	 * @return array of user-ids
	 */
	private function resolve_users($_users, $no_enum_groups=true, $ignore_acl=false)
	{
		if (!is_array($_users))
		{
			$_users = $_users ? array($_users) : array();
		}
		// only query calendars of users, we have READ-grants from
		$users = array();
		foreach($_users as $user)
		{
			$user = trim($user);
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
				if ($params['filter'] == 'no-enum-groups') continue;

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
		return $users;
	}

	/**
	 * Searches / lists calendar entries, including repeating ones
	 *
	 * @param array $params array with the following keys
	 *	start date startdate of the search/list, defaults to today
	 *	end   date enddate of the search/list, defaults to start + one day
	 *	users  int|array integer user-id or array of user-id's to use, defaults to the current user
	 *  cat_id int|array category-id or array of cat-id's (incl. all sub-categories), default 0 = all
	 *	filter string all (not rejected), accepted, unknown, tentative, rejected or hideprivate
	 *	query string pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 *	daywise boolean on True it returns an array with YYYYMMDD strings as keys and an array with events
	 *		(events spanning multiple days are returned each day again (!)) otherwise it returns one array with
	 *		the events (default), not honored in a search ==> always returns an array of events!
	 *	date_format string date-formats: 'ts'=timestamp (default), 'array'=array, or string with format for date
	 *  offset boolean|int false (default) to return all entries or integer offset to return only a limited result
	 *  enum_recuring boolean if true or not set (default) or daywise is set, each recurence of a recuring events is returned,
	 *		otherwise the original recuring event (with the first start- + enddate) is returned
	 *  num_rows int number of entries to return, default or if 0, max_entries from the prefs
	 *  order column-names plus optional DESC|ASC separted by comma
	 *  ignore_acl if set and true no check_perms for a general EGW_ACL_READ grants is performed
	 *  enum_groups boolean if set and true, group-members will be added as participants with status 'G'
	 *  cols string|array columns to select, if set an iterator will be returned
	 *  append string to append to the query, eg. GROUP BY
	 *  cfs array if set, query given custom fields or all for empty array, none are returned, if not set (default)
	 * @param string $sql_filter=null sql to be and'ed into query (fully quoted), default none
	 * @return iterator|array|boolean array of events or array with YYYYMMDD strings / array of events pairs (depending on $daywise param)
	 *	or false if there are no read-grants from _any_ of the requested users or iterator/recordset if cols are given
	 */
	function &search($params,$sql_filter=null)
	{
		$params_in = $params;

		$params['sql_filter'] = $sql_filter;	// dont allow to set it via UI or xmlrpc

		// check if any resource wants to hook into
		foreach($this->resources as $app => $data)
		{
			if (isset($data['search_filter']))
			{
				$params = ExecMethod($data['search_filter'],$params);
			}
		}

		if (!isset($params['users']) || !$params['users'] ||
			count($params['users']) == 1 && isset($params['users'][0]) && !$params['users'][0])	// null or '' casted to an array
		{
			// for a search use all account you have read grants from
			$params['users'] = $params['query'] ? array_keys($this->grants) : $this->user;
		}
		// resolve users to add memberships for users and members for groups
		$users = $this->resolve_users($params['users'], $params['filter'] == 'no-enum-groups', $params['ignore_acl']);

		// replace (by so not understood filter 'no-enum-groups' with 'default' filter
		if ($params['filter'] == 'no-enum-groups')
		{
			$params['filter'] = 'default';
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
		$params['enum_recuring'] = $enum_recuring = $daywise || !isset($params['enum_recuring']) || !!$params['enum_recuring'];
		$cat_id = isset($params['cat_id']) ? $params['cat_id'] : 0;
		$filter = isset($params['filter']) ? $params['filter'] : 'all';
		$offset = isset($params['offset']) && $params['offset'] !== false ? (int) $params['offset'] : false;
		// socal::search() returns rejected group-invitations, as only the user not also the group is rejected
		// as we cant remove them efficiantly in SQL, we kick them out here, but only if just one user is displayed
		$users_in = (array)$params_in['users'];
		$remove_rejected_by_user = !in_array($filter,array('all','rejected')) &&
			count($users_in) == 1 && $users_in[0] > 0 ? $users_in[0] : null;
		//error_log(__METHOD__.'('.array2string($params_in).", $sql_filter) params[users]=".array2string($params['users']).' --> remove_rejected_by_user='.array2string($remove_rejected_by_user));

		if ($this->debug && ($this->debug > 1 || $this->debug == 'search'))
		{
			$this->debug_message('calendar_bo::search(%1) start=%2, end=%3, daywise=%4, cat_id=%5, filter=%6, query=%7, offset=%8, num_rows=%9, order=%10, sql_filter=%11)',
				True,$params,$start,$end,$daywise,$cat_id,$filter,$params['query'],$offset,(int)$params['num_rows'],$params['order'],$params['sql_filter']);
		}
		// date2ts(,true) converts to server time, db2data converts again to user-time
		$events =& $this->so->search(isset($start) ? $this->date2ts($start,true) : null,isset($end) ? $this->date2ts($end,true) : null,
			$users,$cat_id,$filter,$offset,(int)$params['num_rows'],$params,$remove_rejected_by_user);

		if (isset($params['cols']))
		{
			return $events;
		}
		$this->total = $this->so->total;
		$this->db2data($events,isset($params['date_format']) ? $params['date_format'] : 'ts');

		//echo "<p align=right>remove_rejected_by_user=$remove_rejected_by_user, filter=$filter, params[users]=".print_r($param['users'])."</p>\n";
		foreach($events as $id => $event)
		{
			if ($params['enum_groups'] && $this->enum_groups($event))
			{
				$events[$id] = $event;
			}
			if (!(int)$event['id'] && preg_match('/^([a-z_]+)([0-9]+)$/',$event['id'],$matches))
			{
				$is_private = self::integration_get_private($matches[1],$matches[2],$event);
			}
			else
			{
				$is_private = !$this->check_perms(EGW_ACL_READ,$event);
			}
			if ($is_private || (!$event['public'] && $filter == 'hideprivate'))
			{
				$this->clear_private_infos($events[$id],$users);
			}
		}

		if ($daywise)
		{
			if ($this->debug && ($this->debug > 2 || $this->debug == 'search'))
			{
				$this->debug_message('socalendar::search daywise sorting from %1 to %2 of %3',False,$start,$end,$events);
			}
			// create empty entries for each day in the reported time
			for($ts = $start; $ts <= $end; $ts += DAY_s) // good enough for array creation, but see while loop below.
			{
				$daysEvents[$this->date2string($ts)] = array();
			}
			foreach($events as $k => $event)
			{
				$e_start = max($this->date2ts($event['start']),$start);
				// $event['end']['raw']-1 to allow events to end on a full hour/day without the need to enter it as minute=59
				$e_end   = min($this->date2ts($event['end'])-1,$end);

				// add event to each day in the reported time
				$ts = $e_start;
				//  $ts += DAY_s in a 'for' loop does not work for daylight savings in week view
				// because the day is longer than DAY_s: Fullday events will be added twice.
				while ($ts <= $e_end)
				{
					$daysEvents[$ymd = $this->date2string($ts)][] =& $events[$k];
					$ts = strtotime("+1 day",$ts);
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
		if ($this->debug && ($this->debug > 0 || $this->debug == 'search'))
		{
			$this->debug_message('calendar_bo::search(%1)=%2',True,$params,$events);
		}
		//error_log(__METHOD__."() returning ".count($events)." entries, total=$this->total ".function_backtrace());
		return $events;
	}

	/**
	 * Get integration data for a given app of a part (value for a certain key) of it
	 *
	 * @param string $app
	 * @param string $part
	 * @return array
	 */
	static function integration_get_data($app,$part=null)
	{
		static $integration_data;

		if (!isset($integration_data))
		{
			$integration_data = calendar_so::get_integration_data();
		}

		if (!isset($integration_data[$app])) return null;

		return $part ? $integration_data[$app][$part] : $integration_data[$app];
	}

	/**
	 * Get private attribute for an integration event
	 *
	 * Attribute 'is_private' is either a boolean value, eg. false to make all events of $app public
	 * or an ExecMethod callback with parameters $id,$event
	 *
	 * @param string $app
	 * @param int|string $id
	 * @return string
	 */
	static function integration_get_private($app,$id,$event)
	{
		$app_data = self::integration_get_data($app,'is_private');

		// no method, fall back to link title
		if (is_null($app_data))
		{
			$is_private = !egw_link::title($app,$id);
		}
		// boolean value to make all events of $app public (false) or private (true)
		elseif (is_bool($app_data))
		{
			$is_private = $app_data;
		}
		else
		{
			$is_private = (bool)ExecMethod2($app_data,$id,$event);
		}
		//echo '<p>'.__METHOD__."($app,$id,) app_data=".array2string($app_data).' returning '.array2string($is_private)."</p>\n";
		return $is_private;
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
			'tzid'  => $event['tzid'],
			'title' => lang('private'),
			'modified'	=> $event['modified'],
			'owner'		=> $event['owner'],
			'recur_type' => MCAL_RECUR_NONE,
			'etag'	=> $event['etag'],
			'max_user_modified' => $event['max_user_modified'],
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
			$this->debug_message('calendar_bo::check_move_horizont(%1) horizont=%2',true,$new_horizont,(int)$this->config['horizont']);
		}
		$new_horizont = $this->date2ts($new_horizont,true);	// now we are in server-time, where this function operates

		if ($new_horizont <= $this->config['horizont'])	// no move necessary
		{
			if ($this->debug == 'check_move_horizont') $this->debug_message('calendar_bo::check_move_horizont(%1) horizont=%2 is bigger ==> nothing to do',true,$new_horizont,(int)$this->config['horizont']);
			return;
		}
		if (!empty($GLOBALS['egw_info']['server']['calendar_horizont']))
		{
			$maxdays = abs($GLOBALS['egw_info']['server']['calendar_horizont']);
		}
		if (empty($maxdays)) $maxdays = 1000; // old default
		if ($new_horizont > time()+$maxdays*DAY_s)		// some user tries to "look" more then the maximum number of days in the future
		{
			if ($this->debug == 'check_move_horizont') $this->debug_message('calendar_bo::check_move_horizont(%1) horizont=%2 new horizont more then %3 days from now --> ignoring it',true,$new_horizont,(int)$this->config['horizont'],$maxdays);
			$this->warnings['horizont'] = lang('Requested date %1 outside allowed range of %2 days: recurring events obmitted!', egw_time::to($new_horizont,true), $maxdays);
			return;
		}
		if ($new_horizont < time()+31*DAY_s)
		{
			$new_horizont = time()+31*DAY_s;
		}
		$old_horizont = $this->config['horizont'];
		$this->config['horizont'] = $new_horizont;

		// create further recurrences for all recurring and not yet (at the old horizont) ended events
		if (($recuring = $this->so->unfinished_recuring($old_horizont)))
		{
			@set_time_limit(0);	// disable time-limit, in case it takes longer to calculate the recurrences
			foreach($this->read(array_keys($recuring)) as $cal_id => $event)
			{
				if ($this->debug == 'check_move_horizont')
				{
					$this->debug_message('calendar_bo::check_move_horizont(%1): calling set_recurrences(%2,%3)',true,$new_horizont,$event,$old_horizont);
				}
				// insert everything behind max(cal_start), which can be less then $old_horizont because of bugs in the past
				$this->set_recurrences($event,egw_time::server2user($recuring[$cal_id]+1));	// set_recurences operates in user-time!
			}
		}
		// update the horizont
		config::save_value('horizont',$this->config['horizont'],'calendar');

		if ($this->debug == 'check_move_horizont') $this->debug_message('calendar_bo::check_move_horizont(%1) new horizont=%2, exiting',true,$new_horizont,(int)$this->config['horizont']);
	}

	/**
	 * set all recurrences for an event until the defined horizont $this->config['horizont']
	 *
	 * This methods operates in usertime, while $this->config['horizont'] is in servertime!
	 *
	 * @param array $event
	 * @param mixed $start=0 minimum start-time for new recurrences or !$start = since the start of the event
	 */
	function set_recurrences($event,$start=0)
	{
		if ($this->debug && ((int) $this->debug >= 2 || $this->debug == 'set_recurrences' || $this->debug == 'check_move_horizont'))
		{
			$this->debug_message('calendar_bo::set_recurrences(%1,%2)',true,$event,$start);
		}
		// check if the caller gave us enough information and if not read it from the DB
		if (!isset($event['participants']) || !isset($event['start']) || !isset($event['end']))
		{
			list(,$event_read) = each($this->so->read($event['id']));
			if (!isset($event['participants']))
			{
				$event['participants'] = $event_read['participants'];
			}
			if (!isset($event['start']) || !isset($event['end']))
			{
				$event['start'] = $event_read['start'];
				$event['end'] = $event_read['end'];
			}
		}
		if (!$start) $start = $event['start'];

		$events = array();
		$this->insert_all_recurrences($event,$start,$this->date2usertime($this->config['horizont']),$events);

		$limit = min($this->config['horizont'], $event['end']);
		$days = $this->so->get_recurrence_exceptions($event, null, $start, $limit);	// content of array is in server-time!
		//error_log('set_recurrences: days=' . array2string($days) );
		foreach($events as $event)
		{
			$start = $this->date2ts($event['start'],true);
			if (in_array($start, (array)$days))
			{
				// we don't change the stati of recurrence exceptions
				$event['participants'] = array();
			}
			if ($event['whole_day'])
			{
				$time = new egw_time($event['end'], egw_time::$user_timezone);
				$time =& $this->so->startOfDay($time);
				$time->setTime(23, 59, 59);
				$end = $this->date2ts($time,true);
			}
			else
			{
				$end = $this->date2ts($event['end'],true);
			}
			$this->so->recurrence($event['id'],$start,$end,$event['participants']);
		}
	}

	/**
	 * Convert data read from the db, eg. convert server to user-time
	 *
	 * Also make sure all timestamps comming from DB as string are converted to integer,
	 * to avoid misinterpretation by egw_time as Ymd string.
	 *
	 * @param array &$events array of event-arrays (reference)
	 * @param $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 */
	function db2data(&$events,$date_format='ts')
	{
		if (!is_array($events)) echo "<p>calendar_bo::db2data(\$events,$date_format) \$events is no array<br />\n".function_backtrace()."</p>\n";
		foreach ($events as &$event)
		{
			// convert timezone id of event to tzid (iCal id like 'Europe/Berlin')
			if (!$event['tz_id'] || !($event['tzid'] = calendar_timezones::id2tz($event['tz_id'])))
			{
				$event['tzid'] = egw_time::$server_timezone->getName();
			}
			// database returns timestamps as string, convert them to integer
			// to avoid misinterpretation by egw_time as Ymd string
			// (this will fail on 32bit systems for times > 2038!)
			$event['start'] = (int)$event['start'];	// this is for isWholeDay(), which also calls egw_time
			$event['end'] = (int)$event['end'];
			$event['whole_day'] = self::isWholeDay($event);
			if ($event['whole_day'] && $date_format != 'server')
			{
				// Adjust dates to user TZ
				$time = new egw_time((int)$event['start'], egw_time::$server_timezone);
				$time =& $this->so->startOfDay($time, $event['tzid']);
				$event['start'] = egw_time::to($time, $date_format);
				$time = new egw_time((int)$event['end'], egw_time::$server_timezone);
				$time =& $this->so->startOfDay($time, $event['tzid']);
				$time->setTime(23, 59, 59);
				$event['end'] = egw_time::to($time, $date_format);
				if (!empty($event['recurrence']))
				{
					$time = new egw_time((int)$event['recurrence'], egw_time::$server_timezone);
					$time =& $this->so->startOfDay($time, $event['tzid']);
					$event['recurrence'] = egw_time::to($time, $date_format);
				}
				if (!empty($event['recur_enddate']))
				{
					$time = new egw_time((int)$event['recur_enddate'], egw_time::$server_timezone);
					$time =& $this->so->startOfDay($time, $event['tzid']);
					$time->setTime(23, 59, 59);
					$event['recur_enddate'] = egw_time::to($time, $date_format);
				}
				$timestamps = array('modified','created');
			}
			else
			{
				$timestamps = array('start','end','modified','created','recur_enddate','recurrence');
			}
			// we convert here from the server-time timestamps to user-time and (optional) to a different date-format!
			foreach ($timestamps as $ts)
			{
				if (!empty($event[$ts]))
				{
					$event[$ts] = $this->date2usertime((int)$event[$ts],$date_format);
				}
			}
			// same with the recur exceptions
			if (isset($event['recur_exception']) && is_array($event['recur_exception']))
			{
				foreach($event['recur_exception'] as &$date)
				{
					if ($event['whole_day'] && $date_format != 'server')
					{
						// Adjust dates to user TZ
						$time = new egw_time((int)$date, egw_time::$server_timezone);
						$time =& $this->so->startOfDay($time, $event['tzid']);
						$date = egw_time::to($time, $date_format);
					}
					else
					{
						$date = $this->date2usertime((int)$date,$date_format);
					}
				}
			}
			// same with the alarms
			if (isset($event['alarm']) && is_array($event['alarm']))
			{
				foreach($event['alarm'] as &$alarm)
				{
					$alarm['time'] = $this->date2usertime((int)$alarm['time'],$date_format);
				}
			}
		}
	}

	/**
	 * convert a date from server to user-time
	 *
	 * @param int $ts timestamp in server-time
	 * @param string $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 * @return mixed depending of $date_format
	 */
	function date2usertime($ts,$date_format='ts')
	{
		if (empty($ts) || $date_format == 'server') return $ts;

		return egw_time::server2user($ts,$date_format);
	}

	/**
	 * Reads a calendar-entry
	 *
	 * @param int|array|string $ids id or array of id's of the entries to read, or string with a single uid
	 * @param mixed $date=null date to specify a single event of a series
	 * @param boolean $ignore_acl should we ignore the acl, default False for a single id, true for multiple id's
	 * @param string $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in servertime, 'array'=array, or string with date-format
	 * @param array|int $clear_privat_infos_users=null if not null, return events with EGW_ACL_FREEBUSY too,
	 * 	but call clear_private_infos() with the given users
	 * @return boolean|array event or array of id => event pairs, false if the acl-check went wrong, null if $ids not found
	 */
	function read($ids,$date=null,$ignore_acl=False,$date_format='ts',$clear_private_infos_users=null)
	{
		if (!$ids) return false;

		if ($date) $date = $this->date2ts($date);

		$return = null;

		$check = $clear_private_infos_users ? EGW_ACL_FREEBUSY : EGW_ACL_READ;
		if ($ignore_acl || is_array($ids) || ($return = $this->check_perms($check,$ids,0,$date_format,$date)))
		{
			if (is_array($ids) || !isset(self::$cached_event['id']) || self::$cached_event['id'] != $ids ||
				self::$cached_event_date_format != $date_format ||
				self::$cached_event['recur_type'] != MCAL_RECUR_NONE && self::$cached_event_date != $date)
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
						$return = self::$cached_event;
					}
				}
			}
			else
			{
				$return = self::$cached_event;
			}
		}
		if ($clear_private_infos_users && !is_array($ids) && !$this->check_perms(EGW_ACL_READ,$return))
		{
			$this->clear_private_infos($return, (array)$clear_private_infos_users);
		}
		if ($this->debug && ($this->debug > 1 || $this->debug == 'read'))
		{
			$this->debug_message('calendar_bo::read(%1,%2,%3,%4,%5)=%6',True,$ids,$date,$ignore_acl,$date_format,$clear_private_infos_users,$return);
		}
		return $return;
	}

	/**
	 * Inserts all repetions of $event in the timespan between $start and $end into $events
	 *
	 * The new entries are just appended to $events, so $events is no longer sorted by startdate !!!
	 *
	 * Recurrences get calculated by rrule iterator implemented in calendar_rrule class.
	 *
	 * @param array $event repeating event whos repetions should be inserted
	 * @param mixed $start start-date
	 * @param mixed $end end-date
	 * @param array $events where the repetions get inserted
	 * @param array $recur_exceptions with date (in Ymd) as key (and True as values), seems not to be used anymore
	 */
	function insert_all_recurrences($event,$start,$end,&$events)
	{
		if ((int) $this->debug >= 3 || $this->debug == 'set_recurrences' || $this->debug == 'check_move_horizont' || $this->debug == 'insert_all_recurrences')
		{
			$this->debug_message(__METHOD__.'(%1,%2,%3,&$event)',true,$event,$start,$end);
		}
		$start_in = $start; $end_in = $end;

		$start = $this->date2ts($start);
		$event_start_ts = $this->date2ts($event['start']);
		$event_length = $this->date2ts($event['end']) - $event_start_ts;	// we use a constant event-length, NOT a constant end-time!

		// if $end is before recur_enddate, use it instead
		if (!$event['recur_enddate'] || $this->date2ts($event['recur_enddate']) > $this->date2ts($end))
		{
			//echo "<p>recur_enddate={$event['recur_enddate']}=".egw_time::to($event['recur_enddate'])." > end=$end=".egw_time::to($end)." --> using end instead of recur_enddate</p>\n";
			// insert at least the event itself, if it's behind the horizont
			$event['recur_enddate'] = $this->date2ts($end) < $this->date2ts($event['end']) ? $event['end'] : $end;
		}
		// loop over all recurrences and insert them, if they are after $start
		$rrule = calendar_rrule::event2rrule($event, true);	// true = we operate in usertime, like the rest of calendar_bo
		foreach($rrule as $time)
		{
			$time->setUser();	// $time is in timezone of event, convert it to usertime used here
			if (($ts = $this->date2ts($time)) < $start-$event_length)
			{
				//echo "<p>".$time." --> ignored as $ts < $start-$event_length</p>\n";
				continue;	// to early or original event (returned by interator too)
			}

			$ts_end = $ts + $event_length;

			// adjust ts_end for whole day events in case it does not fit due to
			// spans over summer/wintertime adjusted days
			if($event['whole_day'] && ($arr_end = $this->date2array($ts_end)) &&
				!($arr_end['hour'] == 23 && $arr_end['minute'] == 59 && $arr_end['second'] == 59))
			{
				$arr_end['hour'] = 23;
				$arr_end['minute'] = 59;
				$arr_end['second'] = 59;
				$ts_end_guess = $this->date2ts($arr_end);
				if($ts_end_guess - $ts_end > DAY_s/2)
				{
					$ts_end = $ts_end_guess - DAY_s; // $ts_end_guess was one day too far in the future
				}
				else
				{
					$ts_end = $ts_end_guess; // $ts_end_guess was ok
				}
			}

			$event['start'] = $ts;
			$event['end'] = $ts_end;
			$events[] = $event;
		}
		if ($this->debug && ((int) $this->debug > 2 || $this->debug == 'set_recurrences' || $this->debug == 'check_move_horizont' || $this->debug == 'insert_all_recurrences'))
		{
			$event['start'] = $event_start_ts;
			$event['end'] = $event_start_ts + $event_length;
			$this->debug_message(__METHOD__.'(%1,start=%2,end=%3,events) events=%5',True,$event,$start_in,$end_in,$events);
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
			$this->debug_message('calendar_bo::add_adjust_event(,%1,%2) as %3',True,$event_in,$date_ymd,$event);
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

		if (!is_scalar($uid)) throw new egw_exception_wrong_parameter(__METHOD__.'('.array2string($uid).') parameter must be scalar');

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
			$this->debug_message('calendar_bo::resource_info(%1) = %2',True,$uid,$res_info_cache[$uid]);
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
	 * @param int $user=null for which user to check, default current user
	 * @return boolean true permission granted, false for permission denied or null if event not found
	 */
	function check_perms($needed,$event=0,$other=0,$date_format='ts',$date_to_read=null,$user=null)
	{
		if (!$user) $user = $this->user;
		if ($user == $this->user)
		{
			$grants = $this->grants;
		}
		else
		{
			$grants = $GLOBALS['egw']->acl->get_grants('calendar',true,$user);
		}

		$event_in = $event;
		if ($other && !is_numeric($other))
		{
			$resource = $this->resource_info($other);
			return $needed & $resource['rights'];
		}
		if (is_int($event) && $event == 0)
		{
			$owner = $other ? $other : $user;
		}
		else
		{
			if (!is_array($event))
			{
				$event = $this->read($event,$date_to_read,true,$date_format);	// = no ACL check !!!
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
		$grant = $grants[$owner];

		// now any ACL rights (but invite rights!) implicate FREEBUSY rights (at least READ has to include FREEBUSY)
		if ($grant & ~EGW_ACL_INVITE) $grant |= EGW_ACL_FREEBUSY;

		if (is_array($event) && ($needed == EGW_ACL_READ || $needed == EGW_ACL_FREEBUSY))
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
						// if we are a participant, we have an implicite FREEBUSY, READ and PRIVAT grant
						$grant |= EGW_ACL_FREEBUSY | EGW_ACL_READ | EGW_ACL_PRIVATE;
						break;
					}
					elseif ($grants[$uid] & EGW_ACL_READ)
					{
						// if we have a READ grant from a participant, we dont give an implicit privat grant too
						$grant |= EGW_ACL_READ;
						// we cant break here, as we might be a participant too, and would miss the privat grant
					}
					elseif (!is_numeric($uid))
					{
						// if the owner only grants EGW_ACL_FREEBUSY we are not interested in the recources explicit rights
						if ($grant == EGW_ACL_FREEBUSY) break;
						// if we have a resource as participant
						$resource = $this->resource_info($uid);
						$grant |= $resource['rights'];
					}
				}
			}
		}
		if ($GLOBALS['egw']->accounts->get_type($owner) == 'g' && $needed == EGW_ACL_ADD)
		{
			$access = False;	// a group can't be the owner of an event
		}
		else
		{
			$access = $user == $owner || $grant & $needed
				&& ($needed == EGW_ACL_FREEBUSY || !$private || $grant & EGW_ACL_PRIVATE);
		}
		// do NOT allow users to purge deleted events, if we dont have 'user_purge' enabled
		if ($access && $needed == EGW_ACL_DELETE && $event['deleted'] &&
			!$GLOBALS['egw_info']['user']['apps']['admin'] && $user != $this->user &&
			$GLOBALS['egw_info']['server']['calendar_delete_history'] != 'user_purge')
		{
			$access = false;
		}
		if ($this->debug && ($this->debug > 2 || $this->debug == 'check_perms'))
		{
			$this->debug_message('calendar_bo::check_perms(%1,%2,other=%3,%4,%5,user=%6)=%7',True,ACL_TYPE_IDENTIFER.$needed,$event,$other,$date_format,$date_to_read,$user,$access);
		}
		//error_log(__METHOD__."($needed,".array2string($event).",$other,...,$user) returning ".array2string($access));
		return $access;
	}

	/**
	 * Converts several date-types to a timestamp and optionally converts user- to server-time
	 *
	 * @param mixed $date date to convert, should be one of the following types
	 *	string (!) in form YYYYMMDD or iso8601 YYYY-MM-DDThh:mm:ss or YYYYMMDDThhmmss
	 *	int already a timestamp
	 *	array with keys 'second', 'minute', 'hour', 'day' or 'mday' (depricated !), 'month' and 'year'
	 * @param boolean $user2server=False conversion between user- and server-time; default False == Off
	 */
	static function date2ts($date,$user2server=False)
	{
		return $user2server ? egw_time::user2server($date,'ts') : egw_time::to($date,'ts');
	}

	/**
	 * Converts a date to an array and optionally converts server- to user-time
	 *
	 * @param mixed $date date to convert
	 * @param boolean $server2user_time conversation between user- and server-time default False == Off
	 * @return array with keys 'second', 'minute', 'hour', 'day', 'month', 'year', 'raw' (timestamp) and 'full' (Ymd-string)
	 */
	static function date2array($date,$server2user=False)
	{
		return $server2user ? egw_time::server2user($date,'array') : egw_time::to($date,'array');
	}

	/**
	 * Converts a date as timestamp or array to a date-string and optionaly converts server- to user-time
	 *
	 * @param mixed $date integer timestamp or array with ('year','month',..,'second') to convert
	 * @param boolean $server2user_time conversation between user- and server-time default False == Off, not used if $format ends with \Z
	 * @param string $format='Ymd' format of the date to return, eg. 'Y-m-d\TH:i:sO' (2005-11-01T15:30:00+0100)
	 * @return string date formatted according to $format
	 */
	static function date2string($date,$server2user=False,$format='Ymd')
	{
		return $server2user ? egw_time::server2user($date,$format) : egw_time::to($date,$format);
	}

	/**
	 * Formats a date given as timestamp or array
	 *
	 * @param mixed $date integer timestamp or array with ('year','month',..,'second') to convert
	 * @param string|boolean $format='' default common_prefs[dateformat], common_prefs[timeformat], false=time only, true=date only
	 * @return string the formated date (incl. time)
	 */
	static function format_date($date,$format='')
	{
		return egw_time::to($date,$format);
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
	static function debug_message($msg,$backtrace=True)
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
					case 'egw_time':
					case 'datetime':
						$p = $param;
						unset($param);
						$param = $p->format('l, Y-m-d H:i:s').' ('.$p->getTimeZone()->getName().')';
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
		//echo '<p>'.$msg."<br>\n".($backtrace ? 'Backtrace: '.function_backtrace(1)."</p>\n" : '').str_repeat(' ',4096);
		error_log($msg);
		if ($backtrace) error_log(function_backtrace(1));
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

		$timespan = $t = common::formattime(sprintf('%02d',$start_m/60),sprintf('%02d',$start_m%60));

		if ($both)	// end-time too
		{
			$timespan .= ' - '.common::formattime(sprintf('%02d',$end_m/60),sprintf('%02d',$end_m%60));
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
	* @param string|boolean $use_type=false type-letter or false
	* @param boolean $append_email=false append email (Name <email>)
	* @return string with name
	*/
	function participant_name($id,$use_type=false, $append_email=false)
	{
		static $id2lid = array();
		static $id2email = array();

		if ($use_type && $use_type != 'u') $id = $use_type.$id;

		if (!isset($id2lid[$id]))
		{
			if (!is_numeric($id))
			{
				$id2lid[$id] = '#'.$id;
				if (($info = $this->resource_info($id)))
				{
					$id2lid[$id] = $info['name'] ? $info['name'] : $info['email'];
					if ($info['name']) $id2email[$id] = $info['email'];
				}
			}
			else
			{
				$id2lid[$id] = common::grab_owner_name($id);
				$id2email[$id] = $GLOBALS['egw']->accounts->id2name($id,'account_email');
			}
		}
		return $id2lid[$id].($append_email && $id2email[$id] ? ' <'.$id2email[$id].'>' : '');
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
			calendar_so::split_status($status,$quantity,$role);

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
					case 'D':	// delegated
						$status = html::image('calendar','forward',$this->verbose_status[$status]);
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
			$names[$id] = $this->participant_name($id).($quantity > 1 ? ' ('.$quantity.')' : '').' '.$status;

			// add role, if not a regular participant
			if ($role != 'REQ-PARTICIPANT')
			{
				if (isset($this->roles[$role]))
				{
					$role = lang($this->roles[$role]);
				}
				// allow to use cats as roles (beside regular iCal ones)
				elseif (substr($role,0,6) == 'X-CAT-' && ($cat_id = (int)substr($role,6)) > 0)
				{
					$role = $GLOBALS['egw']->categories->id2name($cat_id);
				}
				else
				{
					$role = lang(str_replace('X-','',$role));
				}
				$names[$id] .= ' '.$role;
			}
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

		foreach(explode(',',$category) as $cat_id)
		{
			if (!$cat_id) continue;

			if (!isset($id2cat[$cat_id]))
			{
				$id2cat[$cat_id] = categories::read($cat_id);
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
		$name = common::grab_owner_name($id);
		if (!($egw_name = $GLOBALS['egw']->accounts->id2name($id)))
		{
			return;	// do not return no longer existing accounts which eg. still mentioned in acl
		}
		if (($type = $GLOBALS['egw']->accounts->get_type($id)) == 'g')
		{
			$arr = &$groups;
		}
		else
		{
			$arr = &$users;
		}
		$arr[$id] = array(
			'grantor' => $id,
			'value'   => ($type == 'g' ? 'g_' : '') . $id,
			'name'    => $name,
			'sname'	  => $egw_name
		);
	}

	/**
	 * generate list of user- / group-calendars for the selectbox in the header
	 *
	 * @return array alphabeticaly sorted array with users first and then groups: array('grantor'=>$id,'value'=>['g_'.]$id,'name'=>$name)
	 */
	function list_cals()
	{
		$users = $groups = array();
		foreach($this->grants as $id => $rights)
		{
			$this->_list_cals_add($id,$users,$groups);
		}
		if (($memberships = $GLOBALS['egw']->accounts->membership($GLOBALS['egw_info']['user']['account_id'])))
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
		usort($users,array($this,'name_cmp'));
		usort($groups,array($this,'name_cmp'));

		return array_merge($users, $groups);	// users first and then groups, both alphabeticaly
	}

	/**
	 * Compare function for sort by value of key 'name'
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	function name_cmp(array $a, array $b)
	{
		return strnatcasecmp($a['name'], $b['name']);
	}

	/**
	 * Convert the recurrence-information of an event, into a human readable string
	 *
	 * @param array $event
	 * @return string
	 */
	function recure2string($event)
	{
		if (!is_array($event)) return false;
		return (string)calendar_rrule::event2rrule($event);
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
				$contacts = new addressbook_bo();
				$filter = array(
					'n_family' => "!''",
					'bday' => "!''",
				);
				$bdays =& $contacts->search('',array('id','n_family','n_given','n_prefix','n_middle','bday'),
					'bday ASC',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter);
				// search accounts too, if not stored in contacts repository
				$extra_accounts_search = $contacts->account_repository == 'ldap' && !is_null($contacts->so_accounts) &&
					!$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'];
				if ($extra_accounts_search && ($bdays2 =& $contacts->search('',array('id','n_family','n_given','n_prefix','n_middle','bday'),
					'bday ASC',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter+array('owner' => 0))))
				{
					$bdays = !$bdays ? $bdays2 : array_merge($bdays,$bdays2);
				}
				if ($bdays)
				{
					// sort by month and day only
					usort($bdays,create_function('$a,$b','return (int) $a[\'bday\'] == (int) $b[\'bday\'] ? strcmp($a[\'bday\'],$b[\'bday\']) : (int) $a[\'bday\'] - (int) $b[\'bday\'];'));
					foreach($bdays as $pers)
					{
						if (empty($pers['bday']) || $pers['bday']=='0000-00-00 0' || $pers['bday']=='0000-00-00' || $pers['bday']=='0.0.00')
						{
							//error_log(__METHOD__.__LINE__.' Skipping entry for invalid birthday:'.array2string($pers));
							continue;
						}
						list($y,$m,$d) = explode('-',$pers['bday']);
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
			$this->debug_message('calendar_bo::read_holidays(%1)=%2',true,$year,$this->cached_holidays[$year]);
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
		if (!is_array($event) && strpos($event, '-') !== false)
		{
			list($id, $recur) = explode('-', $event, 2);
			$event = $this->read($id, $recur);
		}
		else if (!is_array($event) && (int) $event > 0)
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
	function link_query($pattern, Array &$options = array())
	{
		$result = array();
		$query = array(
			'query'	=>	$pattern,
			'offset'	=>	$options['start'],
		);
		if($options['num_rows']) {
			$query['num_rows'] = $options['num_rows'];
		}
		foreach((array) $this->search($query) as $event)
		{
			$result[$event['id']] = $this->link_title($event);
		}
		$options['total'] = $this->total;
		return $result;
	}

	/**
	 * Check access to the file store
	 *
	 * @param int $id id of entry
	 * @param int $check EGW_ACL_READ for read and EGW_ACL_EDIT for write or delete access
	 * @param string $rel_path=null currently not used in calendar
	 * @param int $user=null for which user to check, default current user
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path,$user=null)
	{
		return $this->check_perms($check,$id,0,'ts',null,$user);
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
		$forced_prefs  =& $GLOBALS['egw']->preferences->forced['calendar'];

		if (!($planner_start_with_group = $GLOBALS['egw']->accounts->name2id('Default')))
		{
			$planner_start_with_group = '0';
		}
		$subject = lang('Calendar Event') . ' - $$action$$: $$startdate$$ $$title$$'."\n";
		$values = array(
			'notifyAdded'     => $subject . lang ('You have a meeting scheduled for %1','$$startdate$$'),
			'notifyCanceled'  => $subject . lang ('Your meeting scheduled for %1 has been canceled','$$startdate$$'),
			'notifyModified'  => $subject . lang ('Your meeting that had been scheduled for %1 has been rescheduled to %2','$$olddate$$','$$startdate$$'),
			'notifyDisinvited'=> $subject . lang ('You have been disinvited from the meeting at %1','$$startdate$$'),
			'notifyResponse'  => $subject . lang ('On %1 %2 %3 your meeting request for %4','$$date$$','$$fullname$$','$$action$$','$$startdate$$'),
			'notifyAlarm'     => lang('Alarm for %1 at %2 in %3','$$title$$','$$startdate$$','$$location$$')."\n".lang ('Here is your requested alarm.'),
			'planner_start_with_group' => $planner_start_with_group,
			'interval'        => 30,
		);
		foreach($values as $var => $default)
		{
			$type = substr($var,0,6) == 'notify' ? 'forced' : 'default';

			// only set, if neither default nor forced pref exists
			if ((!isset($default_prefs[$var]) || (string)$default_prefs[$var] === '') && (!isset($forced_prefs[$var]) || (string)$forced_prefs[$var] === ''))
			{
				$GLOBALS['egw']->preferences->add('calendar',$var,$default,'default');	// always store default, even if we have a forced too
				if ($type == 'forced') $GLOBALS['egw']->preferences->add('calendar',$var,$default,'forced');
				$this->cal_prefs[$var] = $default;
				$need_save = True;
			}
		}
		if ($need_save)
		{
			$GLOBALS['egw']->preferences->save_repository(False,'default');
			$GLOBALS['egw']->preferences->save_repository(False,'forced');
		}
	}

	/**
	 * Get the freebusy URL of a user
	 *
	 * @param int|string $user account_id or account_lid
	 * @param string $pw=null password
	 */
	static function freebusy_url($user='',$pw=null)
	{
		if (is_numeric($user)) $user = $GLOBALS['egw']->accounts->id2name($user);

		$credentials = '';

		if ($pw)
		{
			$credentials = '&password='.urlencode($pw);
		}
		elseif ($GLOBALS['egw_info']['user']['preferences']['calendar']['freebusy'] == 2)
		{
			$credentials = $GLOBALS['egw_info']['user']['account_lid']
				. ':' . $GLOBALS['egw_info']['user']['passwd'];
			$credentials = '&cred=' . base64_encode($credentials);
		}
		return (!$GLOBALS['egw_info']['server']['webserver_url'] || $GLOBALS['egw_info']['server']['webserver_url'][0] == '/' ?
			($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'] : '').
			$GLOBALS['egw_info']['server']['webserver_url'].'/calendar/freebusy.php/?user='.urlencode($user).$credentials;
	}

	/**
	 * Check if the event is the whole day
	 *
	 * @param array $event event
	 * @return boolean true if whole day event, false othwerwise
	 */
	public static function isWholeDay($event)
	{
		// check if the event is the whole day
		$start = self::date2array($event['start']);
		$end = self::date2array($event['end']);

		return !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;
	}

	/**
	 * Get the etag for an entry
	 *
	 * @param array|int|string $event array with event or cal_id, or cal_id:recur_date for virtual exceptions
	 * @param boolean $client_share_uid_excpetions Does client understand exceptions to be included in VCALENDAR component of series master sharing its UID
	 * @return string|boolean string with etag or false
	 */
	function get_etag($entry,$client_share_uid_excpetions=true)
	{
		if (!is_array($entry))
		{
			list($entry,$recur_date) = explode(':',$entry);
			if (!$this->check_perms(EGW_ACL_FREEBUSY, $entry, 0, 'server')) return false;
			$entry = $this->read($entry, $recur_date, true, 'server');
		}
		$etag = $entry['id'].':'.$entry['etag'];

		// use new MAX(modification date) of egw_cal_user table (deals with virtual exceptions too)
		if (isset($entry['max_user_modified']))
		{
			$modified = max($entry['max_user_modified'], $entry['modified']);
		}
		else
		{
			$modified = max($this->so->max_user_modified($entry['id']), $entry['modified']);
		}
		$etag .= ':' . $modified;
		// include exception etags into our own etag, if exceptions are included
		if ($client_share_uid_excpetions && !empty($entry['uid']) &&
			$entry['recur_type'] != MCAL_RECUR_NONE && $entry['recur_exception'])
		{
			$events =& $this->search(array(
				//'query' => array('cal_uid' => $entry['uid']),
				'query' => array('cal_reference' => $entry['id']),
				'filter' => 'owner',  // return all possible entries
				'daywise' => false,
				'enum_recuring' => false,
				'date_format' => 'server',
				'no_total' => true,
			));
			foreach($events as $k => &$recurrence)
			{
				if ($recurrence['reference'] && $recurrence['id'] != $entry['id'])	// ignore series master
				{
					$etag .= ':'.$this->get_etag($recurrence);
				}
			}
		}
		//error_log(__METHOD__ . "($entry[id],$client_share_uid_excpetions) entry=".array2string($entry)." --> etag=$etag");
		return $etag;
	}

	/**
	 * Query ctag for calendar
	 *
	 * @param int|array $user integer user-id or array of user-id's to use, defaults to the current user
	 * @param $filter='owner'
	 * @return string $filter='owner' all (not rejected), accepted, unknown, tentative, rejected or hideprivate
	 * @todo use MAX(modified) to query everything in one sql query, currently we do one query per event (more then the search)
	 */
	public function get_ctag($user,$filter='owner')
	{
		if ($this->debug > 1) $startime = microtime(true);

		// resolve users to add memberships for users and members for groups
		$users = $this->resolve_users($user);
		$ctag = $users ? $this->so->get_ctag($users, $filter == 'owner') : 0;	// no rights, return 0 as ctag (otherwise we get SQL error!)

		if ($this->debug > 1) error_log(__METHOD__. "($user, '$filter') = $ctag = ".date('Y-m-d H:i:s',$ctag)." took ".(microtime(true)-$startime)." secs");
		return $ctag;
	}

	/**
	 * Hook for timesheet to set some extra data and links
	 *
	 * @param array $data
	 * @param int $data[id] cal_id:recurrence
	 * @return array with key => value pairs to set in new timesheet and link_app/link_id arrays
	 */
	function timesheet_set($data)
	{
		$set = array();
		list($id,$recurrence) = explode(':',$data['id']);
		if ((int)$id && ($event = $this->read($id,$recurrence)))
		{
			$set['ts_start'] = $event['start'];
			$set['ts_title'] = $this->link_title($event);
			$set['start_time'] = egw_time::to($event['start'],'H:i');
			$set['ts_description'] = $event['description'];
			if ($this->isWholeDay($event)) $event['end']++;	// whole day events are 1sec short
			$set['ts_duration']	= ($event['end'] - $event['start']) / 60;
			$set['ts_quantity'] = ($event['end'] - $event['start']) / 3600;
			$set['end_time'] = null;	// unset end-time
			$set['cat_id'] = (int)$event['category'];
		}
		return $set;
	}
}
