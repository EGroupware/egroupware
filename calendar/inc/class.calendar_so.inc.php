<?php
/**
 * EGroupware - Calendar's storage-object
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2005-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Acl;

/**
 * some necessary defines used by the calendar
 */
if(!extension_loaded('mcal'))
{
	define('MCAL_RECUR_NONE',0);
	define('MCAL_RECUR_DAILY',1);
	define('MCAL_RECUR_WEEKLY',2);
	define('MCAL_RECUR_MONTHLY_MDAY',3);
	define('MCAL_RECUR_MONTHLY_WDAY',4);
	define('MCAL_RECUR_YEARLY',5);
	define('MCAL_RECUR_SECONDLY',6);
	define('MCAL_RECUR_MINUTELY',7);
	define('MCAL_RECUR_HOURLY',8);

	define('MCAL_M_SUNDAY',1);
	define('MCAL_M_MONDAY',2);
	define('MCAL_M_TUESDAY',4);
	define('MCAL_M_WEDNESDAY',8);
	define('MCAL_M_THURSDAY',16);
	define('MCAL_M_FRIDAY',32);
	define('MCAL_M_SATURDAY',64);

	define('MCAL_M_WEEKDAYS',62);
	define('MCAL_M_WEEKEND',65);
	define('MCAL_M_ALLDAYS',127);
}

define('REJECTED',0);
define('NO_RESPONSE',1);
define('TENTATIVE',2);
define('ACCEPTED',3);
define('DELEGATED',4);

define('HOUR_s',60*60);
define('DAY_s',24*HOUR_s);
define('WEEK_s',7*DAY_s);

/**
 * Class to store all calendar data (storage object)
 *
 * Tables used by calendar_so:
 *	- egw_cal: general calendar data: cal_id, title, describtion, locations, range-start and -end dates
 *	- egw_cal_dates: start- and enddates (multiple entry per cal_id for recuring events!), recur_exception flag
 *	- egw_cal_user: participant info including status (multiple entries per cal_id AND startdate for recuring events)
 * 	- egw_cal_repeats: recur-data: type, interval, days etc.
 *  - egw_cal_extra: custom fields (multiple entries per cal_id possible)
 *
 * The new UI, BO and SO classes have a strict definition, in which timezone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * DB-model uses egw_cal_user.cal_status='X' for participants who got deleted. They never get returned by
 * read or search methods, but influence the ctag of the deleted users calendar!
 *
 * DB-model uses egw_cal_user.cal_status='E' for participants only participating in exceptions of recurring
 * events, so whole recurring event get found for these participants too!
 *
 * All update methods now take care to update modification time of (evtl. existing) series master too,
 * to force an etag, ctag and sync-token change! Methods not doing that are private to this class.
 *
 * range_start/_end in main-table contains start and end of whole event series (range_end is NULL for unlimited recuring events),
 * saving the need to always join dates table, to query non-enumerating recuring events (like CalDAV or ActiveSync does).
 * This effectivly stores MIN(cal_start) and MAX(cal_end) permanently as column in main-table and improves speed tremendiously
 * (few milisecs instead of more then 2 minutes on huge installations)!
 * It's set in calendar_so::save from start and end or recur_enddate, so nothing changes for higher level classes.
 *
 * egw_cal_user.cal_user_id contains since 14.3.001 only an md5-hash of a lowercased raw email address (not rfc822 address!).
 * Real email address and other possible attendee information for iCal or CalDAV are stored in cal_user_attendee.
 * This allows a short 32byte ascii cal_user_id and also storing attendee information for accounts and contacts.
 * Outside of this class uid for email address is still "e$cn <$email>" or "e$email".
 * We use calendar_so::split_user($uid, &$user_type, &$user_id, $md5_email=false) with last param true to generate
 * egw_cal_user.cal_user_id for DB and calendar_so::combine_user($user_type, $user_id, $user_attendee) to generate
 * uid used outside of this class. Both methods are unchanged when using with their default parameters.
 *
 * @ToDo drop egw_cal_repeats table in favor of a rrule colum in main table (saves always used left join and allows to store all sorts of rrules)
 */
class calendar_so
{
	/**
	 * name of the main calendar table and prefix for all other calendar tables
	 */
	var $cal_table = 'egw_cal';
	var $extra_table,$repeats_table,$user_table,$dates_table,$all_tables;

	/**
	 * reference to global db-object
	 *
	 * @var Api\Db
	 */
	var $db;
	/**
	 * instance of the async object
	 *
	 * @var Api\Asyncservice
	 */
	var $async;
	/**
	 * SQL to sort by status U, T, A, R
	 *
	 */
	const STATUS_SORT = "CASE cal_status WHEN 'U' THEN 1 WHEN 'T' THEN 2 WHEN 'A' THEN 3 WHEN 'R' THEN 4 ELSE 0 END ASC";

	/**
	 * Time to keep alarms in async table to allow eg. alarm snozzing
	 */
	const ALARM_KEEP_TIME = 86400;

	/**
	 * Cached timezone data
	 *
	 * @var array id => data
	 */
	protected static $tz_cache = array();

	/**
	 * Constructor of the socal class
	 */
	function __construct()
	{
		$this->async = $GLOBALS['egw']->asyncservice;
		$this->db = $GLOBALS['egw']->db;

		$this->all_tables = array($this->cal_table);
		foreach(array('extra','repeats','user','dates') as $name)
		{
			$vname = $name.'_table';
			$this->all_tables[] = $this->$vname = $this->cal_table.'_'.$name;
		}
	}

	/**
	 * Return sql to fetch all events in a given timerange, to be used instead of full table in further sql queries
	 *
	 * @param int $start
	 * @param int $end
	 * @param array $_where =null
	 * @param boolean $deleted =false
	 * @return string
	 */
	protected function cal_range_view($start, $end, array $_where=null, $deleted=false)
	{
		if ($GLOBALS['egw_info']['server']['no_timerange_views'] || !$start)	// using view without start-date is slower!
		{
			return $this->cal_table;	// no need / use for a view
		}

		$where = array();
		if (isset($deleted)) $where[] = "cal_deleted IS ".($deleted ? '' : 'NOT').' NULL';
		if ($end) $where[] = "range_start<".(int)$end;
		if ($start) $where[] = "(range_end IS NULL OR range_end>".(int)$start.")";
		if ($_where) $where = array_merge($where, $_where);

		$sql = "(SELECT * FROM $this->cal_table WHERE ".$this->db->expression($this->cal_table, $where).") $this->cal_table";

		return $sql;
	}

	/**
	 * Return sql to fetch all dates in a given timerange, to be used instead of full dates table in further sql queries
	 *
	 * Currently NOT used, as using two views joined together appears slower in my tests (probably because no index) then
	 * joining cal_range_view with real dates table (with index).
	 *
	 * @param int $start
	 * @param int $end
	 * @param array $_where =null
	 * @param boolean $deleted =false
	 * @return string
	 */
	protected function dates_range_view($start, $end, array $_where=null, $deleted=false)
	{
		if ($GLOBALS['egw_info']['server']['no_timerange_views'] || !$start || !$end)	// using view without start- AND end-date is slower!
		{
			return $this->dates_table;	// no need / use for a view
		}

		$where = array();
		if (isset($deleted)) $where['recur_exception'] = $deleted;
		if ($end) $where[] = "cal_start<".(int)$end;
		if ($start) $where[] = "cal_end>".(int)$start;
		if ($_where) $where = array_merge($where, $_where);

		// Api\Db::union uses Api\Db::select which check if join contains "WHERE"
		// to support old join syntax like ", other_table WHERE ...",
		// therefore we have to use eg. "WHERe" instead!
		$sql = "(SELECT * FROM $this->dates_table WHERe ".$this->db->expression($this->dates_table, $where).") $this->dates_table";

		return $sql;
	}

	/**
	 * Return events in a given timespan containing given participants (similar to search but quicker)
	 *
	 * Not all search parameters are currently supported!!!
	 *
	 * @param int $start startdate of the search/list (servertime)
	 * @param int $end enddate of the search/list (servertime)
	 * @param int|array $users user-id or array of user-id's, !$users means all entries regardless of users
	 * @param int|array $cat_id =0 mixed category-id or array of cat-id's (incl. all sub-categories), default 0 = all
	 * @param string $filter ='default' string filter-name: all (not rejected), accepted, unknown, tentative, rejected or everything (incl. rejected, deleted)
	 * @param int|boolean $offset =False offset for a limited query or False (default)
	 * @param int $num_rows =0 number of rows to return if offset set, default 0 = use default in user prefs
	 * @param array $params =array()
	 * @param string|array $params['query'] string: pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 *      array: everything is directly used as $where
	 * @param string $params['order'] ='cal_start' column-names plus optional DESC|ASC separted by comma
	 * @param string $params['sql_filter'] sql to be and'ed into query (fully quoted)
	 * @param string|array $params['cols'] what to select, default "$this->repeats_table.*,$this->cal_table.*,cal_start,cal_end,cal_recur_date",
	 * 						if specified and not false an iterator for the rows is returned
	 * @param string $params['append'] SQL to append to the query before $order, eg. for a GROUP BY clause
	 * @param array $params['cfs'] custom fields to query, null = none, array() = all, or array with cfs names
	 * @param array $params['users'] raw parameter as passed to calendar_bo::search() no memberships resolved!
	 * @param boolean $params['master_only'] =false, true only take into account participants/status from master (for AS)
	 * @param boolean $params['enum_recuring'] =true enumerate recuring events
	 * @param int $remove_rejected_by_user =null add join to remove entry, if given user has rejected it
	 * @return array of events
	 */
	function &events($start,$end,$users,$cat_id=0,$filter='all',$offset=False,$num_rows=0,array $params=array(),$remove_rejected_by_user=null)
	{
		error_log(__METHOD__.'('.($start ? date('Y-m-d H:i',$start) : '').','.($end ? date('Y-m-d H:i',$end) : '').','.array2string($users).','.array2string($cat_id).",'$filter',".array2string($offset).",$num_rows,".array2string($params).') '.function_backtrace());
		$start_time = microtime(true);
		// not everything is supported by now
		if (!$start || !$end || is_string($params['query']) ||
			//in_array($filter,array('owner','deleted')) ||
			$params['enum_recuring']===false)
		{
			throw new Api\Exception\AssertionFailed("Unsupported value for parameters!");
		}
		$where = is_array($params['query']) ? $params['query'] : array();
		if ($cat_id) $where[] = $this->cat_filter($cat_id);
		$egw_cal = $this->cal_range_view($start, $end, $where, $filter == 'everything' ? null : $filter != 'deleted');

		$status_filter = $this->status_filter($filter, $params['enum_recuring']);

		$sql = "SELECT DISTINCT {$this->cal_table}_repeats.*,$this->cal_table.*,\n".
			"	CASE WHEN recur_type IS NULL THEN egw_cal.range_start ELSE cal_start END AS cal_start,\n".
			"	CASE WHEN recur_type IS NULL THEN egw_cal.range_end ELSE cal_end END AS cal_end\n".
			// using time-limited range view, instead of complete table, give a big performance plus
			"FROM $egw_cal\n".
			"JOIN egw_cal_user ON egw_cal_user.cal_id=egw_cal.cal_id\n".
			// need to left join dates, as egw_cal_user.recur_date is null for non-recuring event
			"LEFT JOIN egw_cal_dates ON egw_cal_user.cal_id=egw_cal_dates.cal_id AND egw_cal_dates.cal_start=egw_cal_user.cal_recur_date\n".
			"LEFT JOIN egw_cal_repeats ON egw_cal_user.cal_id=egw_cal_repeats.cal_id\n".
			"WHERE ".($status_filter ? $this->db->expression($this->table, $status_filter, " AND \n") : '').
			"	CASE WHEN recur_type IS NULL THEN egw_cal.range_start ELSE cal_start END<".(int)$end." AND\n".
			"	CASE WHEN recur_type IS NULL THEN egw_cal.range_end ELSE cal_end END>".(int)$start;

		if ($users)
		{
			// fix $users to also prefix system users and groups (with 'u')
			if (!is_array($users)) $users = $users ? (array)$users : array();
			foreach($users as &$uid)
			{
				$user_type = $user_id = null;
				self::split_user($uid, $user_type, $user_id, true);
				$uid = $user_type.$user_id;
			}
			$sql .= " AND\n	CONCAT(cal_user_type,cal_user_id) IN (".implode(',', array_map(array($this->db, 'quote'), $users)).")";
		}

		if ($remove_rejected_by_user && !in_array($filter, array('everything', 'deleted')))
		{
			$sql .= " AND\n	(cal_user_type!='u' OR cal_user_id!=".(int)$remove_rejected_by_user." OR cal_status!='R')";
		}

		if (!empty($params['sql_filter']) && is_string($params['sql_filter']))
		{
			$sql .= " AND\n	".$params['sql_filter'];
		}

		if ($params['order'])	// only order if requested
		{
			if (!preg_match('/^[a-z_ ,c]+$/i',$params['order'])) $params['order'] = 'cal_start';		// gard against SQL injection
			$sql .= "\nORDER BY ".$params['order'];
		}

		if ($offset === false)	// return all rows --> Api\Db::query wants offset=0, num_rows=-1
		{
			$offset = 0;
			$num_rows = -1;
		}
		$events =& $this->get_events($this->db->query($sql, __LINE__, __FILE__, $offset, $num_rows));
		error_log(__METHOD__."(...) $sql --> ".number_format(microtime(true)-$start_time, 3));
		return $events;
	}

	/**
	 * reads one or more calendar entries
	 *
	 * All times (start, end and modified) are returned as timesstamps in servertime!
	 *
	 * @param int|array|string $ids id or array of id's of the entries to read, or string with a single uid
	 * @param int $recur_date =0 if set read the next recurrence at or after the timestamp, default 0 = read the initital one
	 * @param boolean $read_recurrence =false true: read the exception, not the series master (only for recur_date && $ids='<uid>'!)
	 * @return array|boolean array with cal_id => event array pairs or false if entry not found
	 */
	function read($ids, $recur_date=0, $read_recurrence=false)
	{
		//error_log(__METHOD__.'('.array2string($ids).",$recur_date) ".function_backtrace());
		$cols = self::get_columns('calendar', $this->cal_table);
		$cols[0] = $this->db->to_varchar($this->cal_table.'.cal_id');
		$cols = "$this->repeats_table.recur_type,$this->repeats_table.recur_interval,$this->repeats_table.recur_data,".implode(',',$cols);
		$join = "LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id";

		$where = array();
		if (is_scalar($ids) && !is_numeric($ids))	// a single uid
		{
			// We want only the parents to match
			$where['cal_uid'] = $ids;
			if ($read_recurrence)
			{
				$where['cal_recurrence'] = $recur_date;
			}
			else
			{
				$where['cal_reference'] = 0;
			}
		}
		elseif(is_array($ids) && isset($ids[count($ids)-1]) || is_scalar($ids))	// one or more cal_id's
		{
			$where['cal_id'] = $ids;
		}
		else	// array with column => value pairs
		{
			$where = $ids;
			unset($ids);	// otherwise users get not read!
		}
		if (isset($where['cal_id']))	// prevent non-unique column-name cal_id
		{
			$where[] = $this->db->expression($this->cal_table, $this->cal_table.'.',array(
				'cal_id' => $where['cal_id'],
			));
			unset($where['cal_id']);
		}
		if ((int) $recur_date && !$read_recurrence)
		{
			$where[] = 'cal_start >= '.(int)$recur_date;
			$group_by = 'GROUP BY '.$cols;
			$cols .= ',MIN(cal_start) AS cal_start,MIN(cal_end) AS cal_end';
			$join = "JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id $join";
		}
		else
		{
			$cols .= ',range_start AS cal_start,(SELECT MIN(cal_end) FROM egw_cal_dates WHERE egw_cal.cal_id=egw_cal_dates.cal_id) AS cal_end';
		}
		$cols .= ',range_end-1 AS recur_enddate';

		$events =& $this->get_events($this->db->select($this->cal_table, $cols, $where, __LINE__, __FILE__, false, $group_by, 'calendar', 0, $join), $recur_date);

		// if we wanted to read the real recurrence, but we have eg. only a virtual one, we need to try again without $read_recurrence
		if ((!$events || ($e = current($events)) && $e['deleted']) && $recur_date && $read_recurrence)
		{
			return $this->read($ids, $recur_date);
		}

		return $events ? $events : false;
	}

	/**
	 * Get full event information from an iterator of a select on egw_cal
	 *
	 * @param array|Iterator $rs
	 * @param int $recur_date =0
	 * @return array
	 */
	protected function &get_events($rs, $recur_date=0)
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$events = array();
		foreach($rs as $row)
		{
			if (!$row['recur_type'])
			{
				$row['recur_type'] = MCAL_RECUR_NONE;
				unset($row['recur_enddate']);
			}
			$row['recur_exception'] = $row['alarm'] = array();
			$events[$row['cal_id']] = Api\Db::strip_array_keys($row,'cal_');
		}
		if (!$events) return $events;

		$ids = array_keys($events);
		if (count($ids) == 1) $ids = $ids[0];

		foreach ($events as &$event)
		{
			if (!isset($event['uid']) || strlen($event['uid']) < $minimum_uid_length)
			{
				// event (without uid), not strong enough uid => create new uid
				$event['uid'] = Api\CalDAV::generate_uid('calendar',$event['id']);
				$this->db->update($this->cal_table, array('cal_uid' => $event['uid']),
					array('cal_id' => $event['id']),__LINE__,__FILE__,'calendar');
			}
			if (!(int)$recur_date && $event['recur_type'] != MCAL_RECUR_NONE)
			{
				foreach($this->db->select($this->dates_table, 'cal_id,cal_start', array(
					'cal_id' => $ids,
					'recur_exception' => true,
				), __LINE__, __FILE__, false, 'ORDER BY cal_id,cal_start', 'calendar') as $row)
				{
					$events[$row['cal_id']]['recur_exception'][] = $row['cal_start'];
				}
				break;	// as above select read all exceptions (and I dont think too short uid problem still exists)
			}
			// make sure we fetch only real exceptions (deleted occurrences of a series should not show up)
			if (($recur_date &&	$event['recur_type'] != MCAL_RECUR_NONE))
			{
				//_debug_array(__METHOD__.__LINE__.' recur_date:'.$recur_date.' check cal_start:'.$event['start']);
				foreach($this->db->select($this->dates_table, 'cal_id,cal_start', array(
					'cal_id' => $event['id'],
					'cal_start' => $event['start'],
					'recur_exception' => true,
				), __LINE__, __FILE__, false, '', 'calendar') as $row)
				{
					$isException[$row['cal_id']] = true;
				}
				if ($isException[$event['id']])
				{
					if (!$this->db->select($this->cal_table, 'COUNT(*)', array(
						'cal_uid' => $event['uid'],
						'cal_recurrence' => $event['start'],
						'cal_deleted' => NULL
					), __LINE__, __FILE__, false, '', 'calendar')->fetchColumn())
					{
						$e = $this->read($event['id'],$event['start']+1);
						$event = $e[$event['id']];
						break;
					}
					else
					{
						//real exception -> should we return it? probably not, so we live with the result of the next occurrence of the series
					}
				}
			}
		}

		// check if we have a real recurance, if not set $recur_date=0
		if (is_array($ids) || $events[(int)$ids]['recur_type'] == MCAL_RECUR_NONE)
		{
			$recur_date = 0;
		}
		else	// adjust the given recurance to the real time, it can be a date without time(!)
		{
			if ($recur_date)
			{
				// also remember recur_date, maybe we need it later, duno now
				$recur_date = array(0,$events[$ids]['recur_date'] = $events[$ids]['start']);
			}
		}

		// participants, if a recur_date give, we read that recurance, plus the one users from the default entry with recur_date=0
		// sorting by cal_recur_date ASC makes sure recurence status always overwrites series status
		foreach($this->db->select($this->user_table,'*',array(
			'cal_id'      => $ids,
			'cal_recur_date' => $recur_date,
			"cal_status NOT IN ('X','E')",
		),__LINE__,__FILE__,false,'ORDER BY cal_user_type DESC,cal_recur_date ASC,'.self::STATUS_SORT,'calendar') as $row)	// DESC puts users before resources and contacts
		{
			// combine all participant data in uid and status values
			$uid    = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
			$status = self::combine_status($row['cal_status'],$row['cal_quantity'],$row['cal_role']);

			$events[$row['cal_id']]['participants'][$uid] = $status;
			$events[$row['cal_id']]['participant_types'][$row['cal_user_type']][is_numeric($uid) ? $uid : substr($uid, 1)] = $status;
			// make extra attendee information available eg. for iCal export (attendee used eg. in response to organizer for an account)
			$events[$row['cal_id']]['attendee'][$uid] = $row['cal_user_attendee'];
		}

		// custom fields
		foreach($this->db->select($this->extra_table,'*',array('cal_id'=>$ids),__LINE__,__FILE__,false,'','calendar') as $row)
		{
			$events[$row['cal_id']]['#'.$row['cal_extra_name']] = $row['cal_extra_value'];
		}

		// alarms
		if (is_array($ids))
		{
			foreach($this->read_alarms((array)$ids) as $cal_id => $alarms)
			{
				$events[$cal_id]['alarm'] = $alarms;
			}
		}
		else
		{
			$events[$ids]['alarm'] = $this->read_alarms($ids);
		}

		//echo "<p>socal::read(".print_r($ids,true).")=<pre>".print_r($events,true)."</pre>\n";
		return $events;
	}

	/**
	 * Maximum time a ctag get cached, as ActiveSync ping requests can run for a long time
	 */
	const MAX_CTAG_CACHE_TIME = 29;

	/**
	 * Get maximum modification time of events for given participants and optional owned by them
	 *
	 * This includes ALL recurences of an event series
	 *
	 * @param int|string|array $users one or mulitple calendar users
	 * @param booelan $owner_too =false if true return also events owned by given users
	 * @param boolean $master_only =false only check recurance master (egw_cal_user.recur_date=0)
	 * @return int maximum modification timestamp
	 */
	function get_ctag($users, $owner_too=false,$master_only=false)
	{
		static $ctags = array();	// some per-request caching
		static $last_request = null;
		if (!isset($last_request) || time()-$last_request > self::MAX_CTAG_CACHE_TIME)
		{
			$ctags = array();
			$last_request = time();
		}
		$signature = serialize(func_get_args());
		if (isset($ctags[$signature])) return $ctags[$signature];

		$types = array();
		foreach((array)$users as $uid)
		{
			$type = $id = null;
			self::split_user($uid, $type, $id, true);
			$types[$type][] = $id;
		}
		foreach($types as $type => $ids)
		{
			$where = array(
				'cal_user_type' => $type,
				'cal_user_id' => $ids,
			);
			if (count($types) > 1)
			{
				$types[$type] = $this->db->expression($this->user_table, $where);
			}
		}
		if (count($types) > 1)
		{
			$where[] = '('.explode(' OR ', $types).')';
		}
		if ($master_only)
		{
			$where['cal_recur_date'] = 0;
		}
		if ($owner_too)
		{
			// owner can only by users, no groups or resources
			foreach($users as $key => $user)
			{
				if (!($user > 0)) unset($users[$key]);
			}
			$where = $this->db->expression($this->user_table, '(', $where, ' OR ').
				$this->db->expression($this->cal_table, array(
					'cal_owner' => $users,
				),')');
		}
		return $ctags[$signature] = $this->db->select($this->user_table,'MAX(cal_modified)',
			$where,__LINE__,__FILE__,false,'','calendar',0,'JOIN egw_cal ON egw_cal.cal_id=egw_cal_user.cal_id')->fetchColumn();
	}

	/**
	 * Query calendar main table and return iterator of query
	 *
	 * Use as: foreach(get_cal_data() as $data) { $data = Api\Db::strip_array_keys($data, 'cal_'); // do something with $data
	 *
	 * @param array $query filter, keys have to use 'cal_' prefix
	 * @param string|array $cols ='cal_id,cal_reference,cal_etag,cal_modified,cal_user_modified' cols to query
	 * @return Iterator as Api\Db::select
	 */
	function get_cal_data(array $query, $cols='cal_id,cal_reference,cal_etag,cal_modified,cal_user_modified')
	{
		if (!is_array($cols)) $cols = explode(',', $cols);

		// special handling of cal_user_modified "pseudo" column
		if (($key = array_search('cal_user_modified', $cols)) !== false)
		{
			$cols[$key] = $this->db->unix_timestamp('(SELECT MAX(cal_user_modified) FROM '.
				$this->user_table.' WHERE '.$this->cal_table.'.cal_id='.$this->user_table.'.cal_id)').
				' AS cal_user_modified';
		}
		return $this->db->select($this->cal_table, $cols, $query, __LINE__, __FILE__);
	}

	/**
	 * generate SQL to filter after a given category (incl. subcategories)
	 *
	 * @param array|int $cat_id cat-id or array of cat-ids, or !$cat_id for none
	 * @return string SQL to include in the query
	 */
	function cat_filter($cat_id)
	{
		$sql = '';
		if ($cat_id)
		{
			$cats = $GLOBALS['egw']->categories->return_all_children($cat_id);
			array_walk($cats, function(&$val, $key)
			{
				unset($key);	// not used, but required by function signature
				$val = (int) $val;
			});
			if (is_array($cat_id) && count($cat_id)==1) $cat_id = $cat_id[0];
			$sql = '(cal_category'.(count($cats) > 1 ? " IN ('".implode("','",$cats)."')" : '='.$this->db->quote((int)$cat_id));
			foreach($cats as $cat)
			{
				$sql .= ' OR '.$this->db->concat("','",'cal_category',"','").' LIKE '.$this->db->quote('%,'.$cat.',%');
			}
			$sql .= ') ';
		}
		return $sql;
	}

	/**
	 * Return filters to filter by given status
	 *
	 * @param string $filter "default", "all", ...
	 * @param boolean $enum_recuring are recuring events enumerated or not
	 * @param array $where =array() array to add filters too
	 * @return array
	 */
	protected function status_filter($filter, $enum_recuring=true, array $where=array())
	{
		if($filter != 'deleted' && $filter != 'everything')
		{
			$where[] = 'cal_deleted IS NULL';
		}
		switch($filter)
		{
			case 'everything':	// no filter at all
				break;
			case 'showonlypublic':
				$where['cal_public'] = 1;
				$where[] = "$this->user_table.cal_status NOT IN ('R','X','E')";
				break;
			case 'deleted':
				$where[] = 'cal_deleted IS NOT NULL';
				break;
			case 'unknown':
				$where[] = "$this->user_table.cal_status='U'";
				break;
			case 'not-unknown':
				$where[] = "$this->user_table.cal_status NOT IN ('U','X','E')";
				break;
			case 'accepted':
				$where[] = "$this->user_table.cal_status='A'";
				break;
			case 'tentative':
				$where[] = "$this->user_table.cal_status='T'";
				break;
			case 'rejected':
				$where[] = "$this->user_table.cal_status='R'";
				break;
			case 'delegated':
				$where[] = "$this->user_table.cal_status='D'";
				break;
			case 'all':
			case 'owner':
				$where[] = "$this->user_table.cal_status NOT IN ('X','E')";
				break;
			default:
				if ($enum_recuring)	// regular UI
				{
					$where[] = "$this->user_table.cal_status NOT IN ('R','X','E')";
				}
				else	// CalDAV / eSync / iCal need to include 'E' = exceptions
				{
					$where[] = "$this->user_table.cal_status NOT IN ('R','X')";
				}
				break;
		}
		return $where;
	}

	/**
	 * Searches / lists calendar entries, including repeating ones
	 *
	 * @param int $start startdate of the search/list (servertime)
	 * @param int $end enddate of the search/list (servertime)
	 * @param int|array $users user-id or array of user-id's, !$users means all entries regardless of users
	 * @param int|array $cat_id =0 mixed category-id or array of cat-id's (incl. all sub-categories), default 0 = all
	 * @param string $filter ='all' string filter-name: all (not rejected), accepted, unknown, tentative, rejected or everything (incl. rejected, deleted)
	 * @param int|boolean $offset =False offset for a limited query or False (default)
	 * @param int $num_rows =0 number of rows to return if offset set, default 0 = use default in user prefs
	 * @param array $params =array()
	 * @param string|array $params['query'] string: pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 *      array: everything is directly used as $where
	 * @param string $params['order'] ='cal_start' column-names plus optional DESC|ASC separted by comma
	 * @param string|array $params['sql_filter'] sql to be and'ed into query (fully quoted), or usual filter array
	 * @param string|array $params['cols'] what to select, default "$this->repeats_table.*,$this->cal_table.*,cal_start,cal_end,cal_recur_date",
	 * 						if specified and not false an iterator for the rows is returned
	 * @param string $params['append'] SQL to append to the query before $order, eg. for a GROUP BY clause
	 * @param array $params['cfs'] custom fields to query, null = none, array() = all, or array with cfs names
	 * @param array $params['users'] raw parameter as passed to calendar_bo::search() no memberships resolved!
	 * @param boolean $params['master_only'] =false, true only take into account participants/status from master (for AS)
	 * @param boolean $params['enum_recuring'] =true enumerate recuring events
	 * @param boolean $params['use_so_events'] =false, true return result of new $this->events()
	 * @param int $remove_rejected_by_user =null add join to remove entry, if given user has rejected it
	 * @return Iterator|array of events
	 */
	function &search($start,$end,$users,$cat_id=0,$filter='all',$offset=False,$num_rows=0,array $params=array(),$remove_rejected_by_user=null)
	{
		//error_log(__METHOD__.'('.($start ? date('Y-m-d H:i',$start) : '').','.($end ? date('Y-m-d H:i',$end) : '').','.array2string($users).','.array2string($cat_id).",'$filter',".array2string($offset).",$num_rows,".array2string($params).') '.function_backtrace());

		/* not using new events method currently, as it not yet fully working and
		   using time-range views in old code gives simmilar improvments
		// uncomment to use new events method for supported parameters
		//if (!isset($params['use_so_events'])) $params['use_so_events'] = $params['use_so_events'] || $start && $end && !in_array($filter, array('owner', 'deleted')) && $params['enum_recuring']!==false;

		// use new events method only if explicit requested
		if ($params['use_so_events'])
		{
			return call_user_func_array(array($this,'events'), func_get_args());
		}
		*/
		if (isset($params['cols']))
		{
			$cols = $params['cols'];
		}
		else
		{
			$all_cols = self::get_columns('calendar', $this->cal_table);
			$all_cols[0] = $this->db->to_varchar($this->cal_table.'.cal_id');
			$cols = "$this->repeats_table.recur_type,$this->repeats_table.recur_interval,$this->repeats_table.recur_data,range_end - 1 AS recur_enddate,".implode(',',$all_cols).",cal_start,cal_end,$this->user_table.cal_recur_date";
		}
		$where = array();
		$join = '';
		if (is_array($params['query']))
		{
			$where = $params['query'];
		}
		elseif ($params['query'])
		{
			$columns = array('cal_title','cal_description','cal_location');

			$wildcard = '%'; $op = null;
			$so_sql = new Api\Storage('calendar', $this->cal_table, $this->extra_table, '', 'cal_extra_name', 'cal_extra_value', 'cal_id', $this->db);
			$where = $so_sql->search2criteria($params['query'], $wildcard, $op, null, $columns);

			// Searching - restrict private to own or private grant
			if (!isset($params['private_grants']))
			{
				$params['private_grants'] = $GLOBALS['egw']->acl->get_ids_for_location($GLOBALS['egw_info']['user']['account_id'], Acl::PRIVAT, 'calendar');
				$params['private_grants'][] = $GLOBALS['egw_info']['user']['account_id'];	// db query does NOT return current user
			}
			$private_filter = '(cal_public=1 OR cal_public=0 AND '.$this->db->expression($this->cal_table, array('cal_owner' => $params['private_grants'])) . ')';
			$where[] = $private_filter;
		}
		if (!empty($params['sql_filter']))
		{
			if (is_string($params['sql_filter']))
			{
				$where[] = $params['sql_filter'];
			}
			elseif(is_array($params['sql_filter']))
			{
				$where = array_merge($where, $params['sql_filter']);
			}
		}
		$useUnionQuery = $this->db->capabilities['distinct_on_text'] && $this->db->capabilities['union'];
		if ($users)
		{
			$users_by_type = array();
			foreach((array)$users as $user)
			{
				if (is_numeric($user))
				{
					$users_by_type['u'][] = (int) $user;
				}
				else
				{
					$user_type = $user_id = null;
					self::split_user($user, $user_type, $user_id, true);
					$users_by_type[$user_type][] = $user_id;
				}
			}
			$to_or = $user_or = array();
			$owner_or = null;
			$table_def = $this->db->get_table_definitions('calendar',$this->user_table);
			foreach($users_by_type as $type => $ids)
			{
				// when we are able to use Union Querys, we do not OR our query, we save the needed parts for later construction of the union
				if ($useUnionQuery)
				{
					$user_or[] = $this->db->expression($table_def,$this->user_table.'.',array(
						'cal_user_type' => $type,
					),' AND '.$this->user_table.'.',array(
						'cal_user_id'   => $ids,
					));
					if ($type == 'u' && $filter == 'owner')
					{
						$cal_table_def = $this->db->get_table_definitions('calendar',$this->cal_table);
						// only users can be owners, no need to add groups
						$user_ids = array();
						foreach($ids as $user_id)
						{
							if ($GLOBALS['egw']->accounts->get_type($user_id) === 'u') $user_ids[] = $user_id;
						}
						$owner_or = $this->db->expression($cal_table_def,array('cal_owner' => $user_ids));
					}
				}
				else
				{
					$to_or[] = $this->db->expression($table_def,$this->user_table.'.',array(
						'cal_user_type' => $type,
					),' AND '.$this->user_table.'.',array(
						'cal_user_id'   => $ids,
					));
					if ($type == 'u' && $filter == 'owner')
					{
						$cal_table_def = $this->db->get_table_definitions('calendar',$this->cal_table);
						$to_or[] = $this->db->expression($cal_table_def,array('cal_owner' => $ids));
					}
				}
			}
			// this is only used, when we cannot use UNIONS
			if (!$useUnionQuery) $where[] = '('.implode(' OR ',$to_or).')';

			$where = $this->status_filter($filter, $params['enum_recuring'], $where);
		}
		if ($cat_id)
		{
			$where[] = $this->cat_filter($cat_id);
		}
		if ($start)
		{
			if ($params['enum_recuring'])
			{
				$where[] = (int)$start.' < cal_end';
			}
			else
			{
				$where[] = '('.((int)$start).' < range_end OR range_end IS NULL)';
			}
		}
		if (!preg_match('/^[a-z_ ,c]+$/i',$params['order'])) $params['order'] = 'cal_start';		// gard against SQL injection

		// if not enum recuring events, we have to use minimum start- AND end-dates, otherwise we get more then one event per cal_id!
		if (!$params['enum_recuring'])
		{
			$where[] = "$this->user_table.cal_recur_date=0";
			$cols = str_replace(array('cal_start','cal_end'),array('range_start AS cal_start','(SELECT MIN(cal_end) FROM egw_cal_dates WHERE egw_cal.cal_id=egw_cal_dates.cal_id) AS cal_end'),$cols);
			// in case cal_start is used in a query, eg. calendar_ical::find_event
			$where = str_replace(array('cal_start','cal_end'), array('range_start','(SELECT MIN(cal_end) FROM egw_cal_dates WHERE egw_cal.cal_id=egw_cal_dates.cal_id)'), $where);
			$params['order'] = str_replace('cal_start', 'range_start', $params['order']);
			if ($end) $where[] = (int)$end.' > range_start';
  		}
		elseif ($end) $where[] = (int)$end.' > cal_start';

		if ($remove_rejected_by_user && $filter != 'everything')
		{
			$rejected_by_user_join = "LEFT JOIN $this->user_table rejected_by_user".
				" ON $this->cal_table.cal_id=rejected_by_user.cal_id".
				" AND rejected_by_user.cal_user_type='u'".
				" AND rejected_by_user.cal_user_id=".$this->db->quote($remove_rejected_by_user).
				" AND ".(!$params['enum_recuring'] ? 'rejected_by_user.cal_recur_date=0' :
					'(recur_type IS NULL AND rejected_by_user.cal_recur_date=0 OR cal_start=rejected_by_user.cal_recur_date)');
			$or_required = array(
				'rejected_by_user.cal_status IS NULL',
				"rejected_by_user.cal_status NOT IN ('R','X')",
			);
			if ($filter == 'owner') $or_required[] = 'cal_owner='.(int)$remove_rejected_by_user;
			$where[] = '('.implode(' OR ',$or_required).')';
		}
		// using a time-range and deleted attribute limited view instead of full table
		$cal_table = $this->cal_range_view($start, $end, null, $filter == 'everything' ? null : $filter != 'deleted');
		$cal_table_def = $this->db->get_table_definitions('calendar', $this->cal_table);

		$u_join = "JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id ".
			"LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id ".
			$rejected_by_user_join;
		// dates table join only needed to enum recuring events, we use a time-range limited view here too
		if ($params['enum_recuring'])
		{
			$join .= "JOIN ".$this->dates_table.	// using dates_table direct seems quicker then an other view
				//$this->dates_range_view($start, $end, null, $filter == 'everything' ? null : $filter == 'deleted').
				" ON $this->cal_table.cal_id=$this->dates_table.cal_id ".$u_join;
		}
		else
		{
			$join .= $u_join;
		}

		// Check for some special sorting, used by planner views
		if($params['order'] == 'participants , cal_non_blocking DESC')
		{
			$order = ($GLOBALS['egw_info']['user']['preferences']['common']['account_display'] == 'lastname' ? 'n_family' : 'n_fileas');
			$cols .= ",egw_addressbook.{$order}";
			$join .= "LEFT JOIN egw_addressbook ON ".
					($this->db->Type == 'pgsql'? "egw_addressbook.account_id::varchar = ":"egw_addressbook.account_id = ").
					"{$this->user_table}.cal_user_id";
			$params['order'] = "$order, cal_non_blocking DESC";
		}
		else if ($params['order'] == 'categories , cal_non_blocking DESC')
		{
			$params['order'] = 'cat_name, cal_non_blocking DESC';
			$cols .= ',egw_categories.cat_name';
			$join .= "LEFT JOIN egw_categories ON egw_categories.cat_id = {$this->cal_table}.cal_category";
		}

		//$starttime = microtime(true);
		if ($useUnionQuery)
		{
			// allow apps to supply participants and/or icons
			if (!isset($params['cols'])) $cols .= ',NULL AS participants,NULL AS icons';

			// changed the original OR in the query into a union, to speed up the query execution under MySQL 5
			// with time-range views benefit is now at best slim for huge tables or none at all!
			$select = array(
				'table' => $cal_table,
				'join'  => $join,
				'cols'  => $cols,
				'where' => $where,
				'app'   => 'calendar',
				'append'=> $params['append'],
				'table_def' => $cal_table_def,
			);
			$selects = array();
			// we check if there are parts to use for the construction of our UNION query,
			// as replace the OR by construction of a suitable UNION for performance reasons
			if ($owner_or || $user_or)
			{
				foreach($user_or as $user_sql)
				{
					$selects[] = $select;
					$selects[count($selects)-1]['where'][] = $user_sql;
					if ($params['enum_recuring'])
					{
						$selects[count($selects)-1]['where'][] = "recur_type IS NULL AND $this->user_table.cal_recur_date=0";
						$selects[] = $select;
						$selects[count($selects)-1]['where'][] = $user_sql;
						$selects[count($selects)-1]['where'][] = "$this->user_table.cal_recur_date=cal_start";
					}
				}
				// if the query is to be filtered by owner we need to add more selects for the union
				if ($owner_or)
				{
					$selects[] = $select;
					$selects[count($selects)-1]['where'][] = $owner_or;
					if ($params['enum_recuring'])
					{
						$selects[count($selects)-1]['where'][] = "recur_type IS NULL AND $this->user_table.cal_recur_date=0";
						$selects[] = $select;
						$selects[count($selects)-1]['where'][] = $owner_or;
						$selects[count($selects)-1]['where'][] = "$this->user_table.cal_recur_date=cal_start";
					}
				}
			}
			else
			{
				// if the query is to be filtered by neither by user nor owner (should not happen?) we need 2 selects for the union
				$selects[] = $select;
				if ($params['enum_recuring'])
				{
					$selects[count($selects)-1]['where'][] = "recur_type IS NULL AND $this->user_table.cal_recur_date=0";
					$selects[] = $select;
					$selects[count($selects)-1]['where'][] = "$this->user_table.cal_recur_date=cal_start";
				}
			}
			if (is_numeric($offset) && !$params['no_total'])	// get the total too
			{
				$save_selects = $selects;
				// we only select cal_table.cal_id (and not cal_table.*) to be able to use DISTINCT (eg. MsSQL does not allow it for text-columns)
				foreach(array_keys($selects) as $key)
				{
					$selects[$key]['cols'] = "$this->repeats_table.recur_type,range_end AS recur_enddate,$this->repeats_table.recur_interval,$this->repeats_table.recur_data,".$this->db->to_varchar($this->cal_table.'.cal_id').",cal_start,cal_end,$this->user_table.cal_recur_date";
					if (!$params['enum_recuring'])
					{
						$selects[$key]['cols'] = str_replace(array('cal_start','cal_end'),
							array('range_start AS cal_start','range_end AS cal_end'), $selects[$key]['cols']);
					}
				}
				if (!isset($params['cols']) && !$params['no_integration']) self::get_union_selects($selects,$start,$end,$users,$cat_id,$filter,$params['query'],$params['users']);

				$this->total = $this->db->union($selects,__LINE__,__FILE__)->NumRows();

				// restore original cols / selects
				$selects = $save_selects; unset($save_selects);
			}
			if (!isset($params['cols']) && !$params['no_integration']) self::get_union_selects($selects,$start,$end,$users,$cat_id,$filter,$params['query'],$params['users']);

			$rs = $this->db->union($selects,__LINE__,__FILE__,$params['order'],$offset,$num_rows);
		}
		else	// MsSQL oder MySQL 3.23
		{
			$where[] = "(recur_type IS NULL AND $this->user_table.cal_recur_date=0 OR $this->user_table.cal_recur_date=cal_start)";

			$selects = array(array(
				'table' => $cal_table,
				'join'  => $join,
				'cols'  => $cols,
				'where' => $where,
				'app'   => 'calendar',
				'append'=> $params['append'],
				'table_def' => $cal_table_def,
			));

			if (is_numeric($offset) && !$params['no_total'])	// get the total too
			{
				$save_selects = $selects;
				// we only select cal_table.cal_id (and not cal_table.*) to be able to use DISTINCT (eg. MsSQL does not allow it for text-columns)
				$selects[0]['cols'] = "$this->cal_table.cal_id,cal_start";
				if (!isset($params['cols']) && !$params['no_integration'] && $this->db->capabilities['union'])
				{
					self::get_union_selects($selects,$start,$end,$users,$cat_id,$filter,$params['query'],$params['users']);
				}
				$this->total = $this->db->union($selects, __LINE__, __FILE__)->NumRows();
				$selects = $save_selects;
			}
			if (!isset($params['cols']) && !$params['no_integration'] && $this->db->capabilities['union'])
			{
				self::get_union_selects($selects,$start,$end,$users,$cat_id,$filter,$params['query'],$params['users']);
			}
			$rs = $this->db->union($selects,__LINE__,__FILE__,$params['order'],$offset,$num_rows);
		}
		//error_log(__METHOD__."() useUnionQuery=$useUnionQuery --> query took ".(microtime(true)-$starttime).'s '.$rs->sql);

		if (isset($params['cols']))
		{
			return $rs;	// if colums are specified we return the recordset / iterator
		}
		// Todo: return $this->get_events($rs);

		$events = $ids = $recur_dates = $recur_ids = array();
		foreach($rs as $row)
		{
			$id = $row['cal_id'];
			if (is_numeric($id)) $ids[] = $id;

			if ($row['cal_recur_date'])
			{
				$id .= '-'.$row['cal_recur_date'];
				$recur_dates[] = $row['cal_recur_date'];
			}
			if ($row['participants'])
			{
				$row['participants'] = explode(',',$row['participants']);
				$row['participants'] = array_combine($row['participants'],
					array_fill(0,count($row['participants']),''));
			}
			else
			{
				$row['participants'] = array();
			}
			$row['recur_exception'] = $row['alarm'] = array();

			// compile a list of recurrences per cal_id
			if (!in_array($id,(array)$recur_ids[$row['cal_id']])) $recur_ids[$row['cal_id']][] = $id;

			$events[$id] = Api\Db::strip_array_keys($row,'cal_');
		}
		//_debug_array($events);
		if (count($ids))
		{
			$ids = array_unique($ids);

			// now ready all users with the given cal_id AND (cal_recur_date=0 or the fitting recur-date)
			// This will always read the first entry of each recuring event too, we eliminate it later
			$recur_dates[] = 0;
			$utcal_id_view = " (SELECT * FROM ".$this->user_table." WHERE cal_id IN (".implode(',',$ids).")".
				($filter != 'everything' ? " AND cal_status NOT IN ('X','E')" : '').") utcalid ";
			//$utrecurdate_view = " (select * from ".$this->user_table." where cal_recur_date in (".implode(',',array_unique($recur_dates)).")) utrecurdates ";
			foreach($this->db->select($utcal_id_view,'*',array(
					//'cal_id' => array_unique($ids),
					'cal_recur_date' => $recur_dates,
				),__LINE__,__FILE__,false,'ORDER BY cal_id,cal_user_type DESC,'.self::STATUS_SORT,'calendar',-1,$join='',
				$this->db->get_table_definitions('calendar',$this->user_table)) as $row)	// DESC puts users before resources and contacts
			{
				$id = $row['cal_id'];
				if ($row['cal_recur_date']) $id .= '-'.$row['cal_recur_date'];

				// combine all participant data in uid and status values
				$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
				$status = self::combine_status($row['cal_status'],$row['cal_quantity'],$row['cal_role']);

				// set accept/reject/tentative of series for all recurrences
				if (!$row['cal_recur_date'])
				{
					foreach((array)$recur_ids[$row['cal_id']] as $i)
					{
						if (isset($events[$i]) && !isset($events[$i]['participants'][$uid]))
						{
							$events[$i]['participants'][$uid] = $status;
						}
					}
				}

				// set data, if recurrence is requested
				if (isset($events[$id])) $events[$id]['participants'][$uid] = $status;
			}
			// query recurrance exceptions, if needed: enum_recuring && !daywise is used in calendar_groupdav::get_series($uid,...)
			if (!$params['enum_recuring'] || !$params['daywise'])
			{
				foreach($this->db->select($this->dates_table, 'cal_id,cal_start', array(
					'cal_id' => $ids,
					'recur_exception' => true,
				), __LINE__, __FILE__, false, 'ORDER BY cal_id,cal_start', 'calendar') as $row)
				{
					// for enum_recurring events are not indexed by cal_id, but $cal_id.'-'.$cal_start
					// find master, which is first recurrence
					if (!isset($events[$id=$row['cal_id']]))
					{
						foreach($events as $id => $event)
						{
							if ($event['id'] == $row['cal_id']) break;
						}
					}
					$events[$id]['recur_exception'][] = $row['cal_start'];
				}
			}
			//custom fields are not shown in the regular views, so we only query them, if explicitly required
			if (!is_null($params['cfs']))
			{
				$where = array('cal_id' => $ids);
				if ($params['cfs']) $where['cal_extra_name'] = $params['cfs'];
				foreach($this->db->select($this->extra_table,'*',$where,
					__LINE__,__FILE__,false,'','calendar') as $row)
				{
					foreach((array)$recur_ids[$row['cal_id']] as $id)
					{
						if (isset($events[$id]))
						{
							$events[$id]['#'.$row['cal_extra_name']] = $row['cal_extra_value'];
						}
					}
				}
			}
			// alarms
			foreach($this->read_alarms($ids) as $cal_id => $alarms)
			{
				foreach($alarms as $id => $alarm)
				{
					$event_start = $alarm['time'] + $alarm['offset'];

					if (isset($events[$cal_id]))	// none recuring event
					{
						$events[$cal_id]['alarm'][$id] = $alarm;
					}
					elseif (isset($events[$cal_id.'-'.$event_start]))	// recuring event
					{
						$events[$cal_id.'-'.$event_start]['alarm'][$id] = $alarm;
					}
				}
			}
		}
		//echo "<p>socal::search\n"; _debug_array($events);
		//error_log(__METHOD__."(,filter=".array2string($params['query']).",offset=$offset, num_rows=$num_rows) returning ".count($events)." entries".($offset!==false?" total=$this->total":'').' '.function_backtrace());
		return $events;
	}

	/**
	 * Data returned by calendar_search_union hook
	 */
	private static $integration_data;

	/**
	 * Ask other apps if they want to participate in calendar search / display
	 *
	 * @param &$selects parts of union query
	 * @param $start see search()
	 * @param $end
	 * @param $users as used in calendar_so ($users_raw plus all members and memberships added by calendar_bo)
	 * @param $cat_id
	 * @param $filter
	 * @param $query
	 * @param $users_raw as passed to calendar_bo::search (no members and memberships added)
	 */
	private static function get_union_selects(array &$selects,$start,$end,$users,$cat_id,$filter,$query,$users_raw)
	{
		if (in_array(basename($_SERVER['SCRIPT_FILENAME']),array('groupdav.php','rpc.php','xmlrpc.php','/activesync/index.php')) ||
			!in_array($GLOBALS['egw_info']['flags']['currentapp'],array('calendar','home')))
		{
			return;    // disable integration for GroupDAV, SyncML, ...
		}
		self::$integration_data = Api\Hooks::process(array(
			'location' => 'calendar_search_union',
			'cols'  => $selects[0]['cols'],    // cols to return
			'start' => $start,
			'end'   => $end,
			'users' => $users,
			'users_raw' => $users_raw,
			'cat_id'=> $cat_id,
			'filter'=> $filter,
			'query' => $query,
		));
		foreach(self::$integration_data as $data)
		{
			if (is_array($data['selects']))
			{
				//echo $app; _debug_array($data);
				$selects = array_merge($selects,$data['selects']);
			}
		}
	}

	/**
	 * Get data from last 'calendar_search_union' hook call
	 *
	 * @return array
	 */
	public static function get_integration_data()
	{
		return self::$integration_data;
	}

	/**
	 * Return union cols constructed from application cols and required cols
	 *
	 * Every col not supplied in $app_cols get returned as NULL.
	 *
	 * @param array $app_cols required name => own name pairs
	 * @param string|array $required array or comma separated column names or table.*
	 * @param string $required_app ='calendar'
	 * @return string cols for union query to match ones supplied in $required
	 */
	public static function union_cols(array $app_cols,$required,$required_app='calendar')
	{
		// remove evtl. used DISTINCT, we currently dont need it
		if (($distinct = substr($required,0,9) == 'DISTINCT '))
		{
			$required = substr($required,9);
		}
		$return_cols = array();
		foreach(is_array($required) ? $required : explode(',',$required) as $cols)
		{
			$matches = null;
			if (substr($cols,-2) == '.*')
			{
				$cols = self::get_columns($required_app,substr($cols,0,-2));
			}
			// remove CAST added for PostgreSQL from eg. "CAST(egw_cal.cal_id AS varchar)"
			elseif (preg_match('/CAST\(([a-z0-9_.]+) AS [a-z0-9_]+\)/i', $cols, $matches))
			{
				$cols = $matches[1];
			}
			elseif (strpos($cols,' AS ') !== false)
			{
				list(,$cols) = explode(' AS ',$cols);
			}
			foreach((array)$cols as $col)
			{
				if (substr($col,0,7) == 'egw_cal')	// remove table name
				{
					$col = preg_replace('/^egw_cal[a-z_]*\./','',$col);
				}
				if (isset($app_cols[$col]))
				{
					$return_cols[] = $app_cols[$col];
				}
				else
				{
					$return_cols[] = 'NULL';
				}
			}
		}
		//error_log(__METHOD__."(".array2string($app_cols).", ".array2string($required).", '$required_app') returning ".array2string(implode(',',$return_cols)));
		return implode(',',$return_cols);
	}

	/**
	 * Get columns of given table, taking into account historically different column order of egw_cal table
	 *
	 * @param string $app
	 * @param string $table
	 * @return array of column names
	 */
	static private function get_columns($app,$table)
	{
		if ($table != 'egw_cal')
		{
			$table_def = $GLOBALS['egw']->db->get_table_definitions($app,$table);
			$cols = array_keys($table_def['fd']);
		}
		else
		{
			// special handling for egw_cal, as old databases have a different column order!!!
			$cols =& Api\Cache::getSession(__CLASS__,$table);

			if (is_null($cols))
			{
				$meta = $GLOBALS['egw']->db->metadata($table,true);
				$cols = array_keys($meta['meta']);
			}
		}
		return $cols;
	}

	/**
	 * Checks for conflicts
	 */

/* folowing SQL checks for conflicts completly on DB level

SELECT cal_user_type, cal_user_id, SUM( cal_quantity )
FROM egw_cal, egw_cal_dates, egw_cal_user
LEFT JOIN egw_cal_repeats ON egw_cal.cal_id = egw_cal_repeats.cal_id
WHERE egw_cal.cal_id = egw_cal_dates.cal_id
AND egw_cal.cal_id = egw_cal_user.cal_id
AND (
recur_type IS NULL
AND cal_recur_date =0
OR cal_recur_date = cal_start
)
AND (
(
cal_user_type = 'u'			# user of the checked event
AND cal_user_id
IN ( 7, 5 )
)
AND 1118822400 < cal_end	# start- and end-time of the checked event
AND cal_start <1118833200
)
AND egw_cal.cal_id !=26		# id of the checked event
AND cal_non_blocking !=1
AND cal_status != 'R'
GROUP BY cal_user_type, cal_user_id
ORDER BY cal_user_type, cal_usre_id

*/

	/**
	 * Saves or creates an event
	 *
	 * We always set cal_modified and cal_modifier and for new events cal_uid.
	 * All other column are only written if they are set in the $event parameter!
	 *
	 * @param array $event
	 * @param boolean &$set_recurrences on return: true if the recurrences need to be written, false otherwise
	 * @param int &$set_recurrences_start=0 on return: time from which on the recurrences should be rebuilt, default 0=all
	 * @param int $change_since =0 time from which on the repetitions should be changed, default 0=all
	 * @param int &$etag etag=null etag to check or null, on return new etag
	 * @return boolean|int false on error, 0 if etag does not match, cal_id otherwise
	 */
	function save(&$event,&$set_recurrences,&$set_recurrences_start=0,$change_since=0,&$etag=null)
	{
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
			if (empty($minimum_uid_length) || $minimum_uid_length<=1) $minimum_uid_length = 8; // we just do not accept no uid, or uid way to short!
		}
		else
		{
			$minimum_uid_length = 8;
		}

		$old_min = $old_duration = 0;

		//error_log(__METHOD__.'('.array2string($event).",$set_recurrences,$change_since,$etag) ".function_backtrace());

		$cal_id = (int) $event['id'];
		unset($event['id']);
		$set_recurrences = $set_recurrences || !$cal_id && $event['recur_type'] != MCAL_RECUR_NONE;

		if ($event['recur_type'] != MCAL_RECUR_NONE &&
			!(int)$event['recur_interval'])
		{
			$event['recur_interval'] = 1;
		}

		// add colum prefix 'cal_' if there's not already a 'recur_' prefix
		foreach(array_keys($event) as $col)
		{
			if ($col[0] != '#' && substr($col,0,6) != 'recur_' && substr($col,0,6) != 'range_' && $col != 'alarm' && $col != 'tz_id' && $col != 'caldav_name')
			{
				$event['cal_'.$col] = $event[$col];
				unset($event[$col]);
			}
		}
		// set range_start/_end, but only if we have cal_start/_end, as otherwise we destroy present values!
		if (isset($event['cal_start'])) $event['range_start'] = $event['cal_start'];
		if (isset($event['cal_end']))
		{
			$event['range_end'] = $event['recur_type'] == MCAL_RECUR_NONE ? $event['cal_end'] :
				($event['recur_enddate'] ? $event['recur_enddate'] : null);
		}
		// ensure that we find mathing entries later on
		if (!is_array($event['cal_category']))
		{
			$categories = array_unique(explode(',',$event['cal_category']));
			sort($categories);
		}
		else
		{
			$categories = array_unique($event['cal_category']);
		}
		sort($categories, SORT_NUMERIC);

		$event['cal_category'] = implode(',',$categories);

		// make sure recurring events never reference to an other recurrent event
		if ($event['recur_type'] != MCAL_RECUR_NONE) $event['cal_reference'] = 0;

		if ($cal_id)
		{
			// query old recurrance information, before updating main table, where recur_endate is now stored
			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$old_repeats = $this->db->select($this->repeats_table, "$this->repeats_table.*,range_end AS recur_enddate",
					"$this->repeats_table.cal_id=".(int)$cal_id, __LINE__, __FILE__,
					false, '', 'calendar', 0, "JOIN $this->cal_table ON $this->repeats_table.cal_id=$this->cal_table.cal_id")->fetch();
			}
			$where = array('cal_id' => $cal_id);
			// read only timezone id, to check if it is changed
			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$old_tz_id = $this->db->select($this->cal_table,'tz_id',$where,__LINE__,__FILE__,'calendar')->fetchColumn();
			}
			if (!is_null($etag)) $where['cal_etag'] = $etag;

			unset($event['cal_etag']);
			$event[] = 'cal_etag=COALESCE(cal_etag,0)+1';	// always update the etag, even if none given to check

			$this->db->update($this->cal_table,$event,$where,__LINE__,__FILE__,'calendar');

			if (!is_null($etag) && $this->db->affected_rows() < 1)
			{
				return 0;	// wrong etag, someone else updated the entry
			}
			if (!is_null($etag)) ++$etag;
		}
		else
		{
			// new event
			if (!$event['cal_owner']) $event['cal_owner'] = $GLOBALS['egw_info']['user']['account_id'];

			if (!$event['cal_id'] && !isset($event['cal_uid'])) $event['cal_uid'] = '';	// uid is NOT NULL!

			$event['cal_etag'] = $etag = 0;
			$this->db->insert($this->cal_table,$event,false,__LINE__,__FILE__,'calendar');
			if (!($cal_id = $this->db->get_last_insert_id($this->cal_table,'cal_id')))
			{
				return false;
			}
		}
		$update = array();
		// event without uid or not strong enough uid
		if (!isset($event['cal_uid']) || strlen($event['cal_uid']) < $minimum_uid_length)
		{
			$update['cal_uid'] = $event['cal_uid'] = Api\CalDAV::generate_uid('calendar',$cal_id);
		}
		// set caldav_name, if not given by caller
		if (empty($event['caldav_name']) && version_compare($GLOBALS['egw_info']['apps']['calendar']['version'], '1.9.003', '>='))
		{
			$update['caldav_name'] = $event['caldav_name'] = $cal_id.'.ics';
		}
		if ($update)
		{
			$this->db->update($this->cal_table, $update, array('cal_id' => $cal_id),__LINE__,__FILE__,'calendar');
		}

		if ($event['recur_type'] == MCAL_RECUR_NONE)
		{
			$this->db->delete($this->dates_table,array(
				'cal_id' => $cal_id),
				__LINE__,__FILE__,'calendar');

			// delete all user-records, with recur-date != 0
			$this->db->delete($this->user_table,array(
				'cal_id' => $cal_id, 'cal_recur_date != 0'),
				__LINE__,__FILE__,'calendar');

			$this->db->delete($this->repeats_table,array(
				'cal_id' => $cal_id),
				__LINE__,__FILE__,'calendar');

			// add exception marker to master, so participants added to exceptions *only* get found
			if ($event['cal_reference'])
			{
				$master_participants = array();
				foreach($this->db->select($this->user_table, 'cal_user_type,cal_user_id,cal_user_attendee', array(
					'cal_id' => $event['cal_reference'],
					'cal_recur_date' => 0,
					"cal_status != 'X'",	// deleted need to be replaced with exception marker too
				), __LINE__, __FILE__, 'calendar') as $row)
				{
					$master_participants[] = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
				}
				foreach(array_diff(array_keys((array)$event['cal_participants']), $master_participants) as $uid)
				{
					$user_type = $user_id = null;
					self::split_user($uid, $user_type, $user_id, true);
					$this->db->insert($this->user_table, array(
						'cal_status' => 'E',
						'cal_user_attendee' => $user_type == 'e' ? substr($uid, 1) : null,
					), array(
						'cal_id' => $event['cal_reference'],
						'cal_recur_date' => 0,
						'cal_user_type' => $user_type,
						'cal_user_id' => $user_id,
					), __LINE__, __FILE__, 'calendar');
				}
			}
		}
		else // write information about recuring event, if recur_type is present in the array
		{
			// fetch information about the currently saved (old) event
			$old_min = (int) $this->db->select($this->dates_table,'MIN(cal_start)',array('cal_id'=>$cal_id),__LINE__,__FILE__,false,'','calendar')->fetchColumn();
			$old_duration = (int) $this->db->select($this->dates_table,'MIN(cal_end)',array('cal_id'=>$cal_id),__LINE__,__FILE__,false,'','calendar')->fetchColumn() - $old_min;
			$old_exceptions = array();
			foreach($this->db->select($this->dates_table, 'cal_start', array(
				'cal_id' => $cal_id,
				'recur_exception' => true
			), __LINE__, __FILE__, false, 'ORDER BY cal_start', 'calendar') as $row)
			{
				$old_exceptions[] = $row['cal_start'];
			}

			$event['recur_exception'] = is_array($event['recur_exception']) ? $event['recur_exception'] : array();
			if (!empty($event['recur_exception']))
			{
				sort($event['recur_exception']);
			}

			$where = array(
				'cal_id' => $cal_id,
				'cal_recur_date' => 0,
			);
			$old_participants = array();
			foreach ($this->db->select($this->user_table,'cal_user_type,cal_user_id,cal_user_attendee,cal_status,cal_quantity,cal_role', $where,
				__LINE__,__FILE__,false,'','calendar') as $row)
			{
				$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
				$status = self::combine_status($row['cal_status'], $row['cal_quantity'], $row['cal_role']);
				$old_participants[$uid] = $status;
			}

			// re-check: did so much recurrence data change that we have to rebuild it from scratch?
			if (!$set_recurrences)
			{
				$set_recurrences = (isset($event['cal_start']) && (int)$old_min != (int) $event['cal_start']) ||
				    $event['recur_type'] != $old_repeats['recur_type'] || $event['recur_data'] != $old_repeats['recur_data'] ||
					(int)$event['recur_interval'] != (int)$old_repeats['recur_interval'] || $event['tz_id'] != $old_tz_id;
			}

			if ($set_recurrences)
			{
				// too much recurrence data has changed, we have to do a rebuild from scratch
				// delete all, but the lowest dates record
				$this->db->delete($this->dates_table,array(
					'cal_id' => $cal_id,
					'cal_start > '.(int)$old_min,
				),__LINE__,__FILE__,'calendar');

				// delete all user-records, with recur-date != 0
				$this->db->delete($this->user_table,array(
					'cal_id' => $cal_id,
					'cal_recur_date != 0',
				),__LINE__,__FILE__,'calendar');
			}
			else
			{
				// we adjust some possibly changed recurrences manually
				// deleted exceptions: re-insert recurrences into the user and dates table
				if (count($deleted_exceptions = array_diff($old_exceptions,$event['recur_exception'])))
				{
					if (isset($event['cal_participants']))
					{
						$participants = $event['cal_participants'];
					}
					else
					{
						// use old default
						$participants = $old_participants;
					}
					foreach($deleted_exceptions as $id => $deleted_exception)
					{
						// rebuild participants for the re-inserted recurrence
						$this->recurrence($cal_id, $deleted_exception, $deleted_exception + $old_duration, $participants);
					}
				}

				// check if recurrence enddate was adjusted
				if(isset($event['recur_enddate']))
				{
					// recurrences need to be truncated
					if((int)$event['recur_enddate'] > 0 &&
						((int)$old_repeats['recur_enddate'] == 0 || (int)$old_repeats['recur_enddate'] > (int)$event['recur_enddate'])
					)
					{
						$this->db->delete($this->user_table,array('cal_id' => $cal_id,'cal_recur_date >= '.($event['recur_enddate'] + 1*DAY_s)),__LINE__,__FILE__,'calendar');
						$this->db->delete($this->dates_table,array('cal_id' => $cal_id,'cal_start >= '.($event['recur_enddate'] + 1*DAY_s)),__LINE__,__FILE__,'calendar');
					}

					// recurrences need to be expanded
					if(((int)$event['recur_enddate'] == 0 && (int)$old_repeats['recur_enddate'] > 0)
						|| ((int)$event['recur_enddate'] > 0 && (int)$old_repeats['recur_enddate'] > 0 && (int)$old_repeats['recur_enddate'] < (int)$event['recur_enddate'])
					)
					{
						$set_recurrences = true;
						$set_recurrences_start = ($old_repeats['recur_enddate'] + 1*DAY_s);
					}
					//error_log(__METHOD__."() event[recur_enddate]=$event[recur_enddate], old_repeats[recur_enddate]=$old_repeats[recur_enddate] --> set_recurrences=".array2string($set_recurrences).", set_recurrences_start=$set_recurrences_start");
				}

				// truncate recurrences by given exceptions
				if (count($event['recur_exception']))
				{
					// added and existing exceptions: delete the execeptions from the user table, it could be the first time
					$this->db->delete($this->user_table,array('cal_id' => $cal_id,'cal_recur_date' => $event['recur_exception']),__LINE__,__FILE__,'calendar');
					// update recur_exception flag based on current exceptions
					$this->db->update($this->dates_table, 'recur_exception='.$this->db->expression($this->dates_table,array(
						'cal_start' => $event['recur_exception'],
					)), array(
						'cal_id' => $cal_id,
					), __LINE__, __FILE__, 'calendar');
				}
			}

			// write the repeats table
			unset($event[0]);	// unset the 'etag=etag+1', as it's not in the repeats table
			$this->db->insert($this->repeats_table,$event,array('cal_id' => $cal_id),__LINE__,__FILE__,'calendar');
		}
		// update start- and endtime if present in the event-array, evtl. we need to move all recurrences
		if (isset($event['cal_start']) && isset($event['cal_end']))
		{
			$this->move($cal_id,$event['cal_start'],$event['cal_end'],!$cal_id ? false : $change_since, $old_min, $old_min +  $old_duration);
		}
		// update participants if present in the event-array
		if (isset($event['cal_participants']))
		{
			$this->participants($cal_id,$event['cal_participants'],!$cal_id ? false : $change_since);
		}
		// Custom fields
		Api\Storage\Customfields::handle_files('calendar', $cal_id, $event);

		foreach($event as $name => $value)
		{
			if ($name[0] == '#')
			{
				if (is_array($value) && array_key_exists('id',$value))
				{
					//error_log(__METHOD__.__LINE__."$name => ".array2string($value).function_backtrace());
					$value = $value['id'];
					//error_log(__METHOD__.__LINE__."$name => ".array2string($value));
				}
				if ($value)
				{
					$this->db->insert($this->extra_table,array(
						'cal_extra_value'	=> is_array($value) ? implode(',',$value) : $value,
					),array(
						'cal_id'			=> $cal_id,
						'cal_extra_name'	=> substr($name,1),
					),__LINE__,__FILE__,'calendar');
				}
				else
				{
					$this->db->delete($this->extra_table,array(
						'cal_id'			=> $cal_id,
						'cal_extra_name'	=> substr($name,1),
					),__LINE__,__FILE__,'calendar');
				}
			}
		}
		// updating or saving the alarms; new alarms have a temporary numeric id!
		if (is_array($event['alarm']))
		{
			foreach ($event['alarm'] as $id => $alarm)
			{
				if ($alarm['id'] && strpos($alarm['id'], 'cal:'.$cal_id.':') !== 0)
				{
					unset($alarm['id']);	// unset the temporary id to add the alarm
				}
				if(!isset($alarm['offset']))
				{
					$alarm['offset'] = $event['cal_start'] - $alarm['time'];
				}
				elseif (!isset($alarm['time']))
				{
					$alarm['time'] = $event['cal_start'] - $alarm['offset'];
				}

				if ($alarm['time'] < time() && !self::shift_alarm($event, $alarm))
				{
					continue;	// pgoerzen: don't add alarm in the past
				}
				$this->save_alarm($cal_id, $alarm, false);	// false: not update modified, we do it anyway
			}
		}
		if (is_null($etag))
		{
			$etag = $this->db->select($this->cal_table,'cal_etag',array('cal_id' => $cal_id),__LINE__,__FILE__,false,'','calendar')->fetchColumn();
		}

		// if event is an exception: update modified of master, to force etag, ctag and sync-token change
		if ($event['cal_reference'])
		{
			$this->updateModified($event['cal_reference']);
		}
		return $cal_id;
	}

	/**
	 * Shift alarm on recurring events to next future recurrence
	 *
	 * @param array $_event event with optional 'cal_' prefix in keys
	 * @param array &$alarm
	 * @param int $timestamp For recurring events, this is the date we
	 *	are dealing with, default is now.
	 * @return boolean true if alarm could be shifted, false if not
	 */
	public static function shift_alarm(array $_event, array &$alarm, $timestamp=null)
	{
		if ($_event['recur_type'] == MCAL_RECUR_NONE)
		{
			return false;
		}
		$start = $timestamp ? $timestamp : (int)time() + $alarm['offset'];
		$event = Api\Db::strip_array_keys($_event, 'cal_');
		$rrule = calendar_rrule::event2rrule($event, false);
		foreach ($rrule as $time)
		{
			if ($start < ($ts = Api\DateTime::to($time,'server')))
			{
				$alarm['time'] = $ts - $alarm['offset'];
				return true;
			}
		}
		return false;
	}

	/**
	 * moves an event to an other start- and end-time taken into account the evtl. recurrences of the event(!)
	 *
	 * @param int $cal_id
	 * @param int $start new starttime
	 * @param int $end new endtime
	 * @param int|boolean $change_since =0 false=new entry, > 0 time from which on the repetitions should be changed, default 0=all
	 * @param int $old_start =0 old starttime or (default) 0, to query it from the db
	 * @param int $old_end =0 old starttime or (default) 0
	 * @todo Recalculate recurrences, if timezone changes
	 * @return int|boolean number of moved recurrences or false on error
	 */
	function move($cal_id,$start,$end,$change_since=0,$old_start=0,$old_end=0)
	{
		//echo "<p>socal::move($cal_id,$start,$end,$change_since,$old_start,$old_end)</p>\n";

		if (!(int) $cal_id) return false;

		if (!$old_start)
		{
			if ($change_since !== false) $row = $this->db->select($this->dates_table,'MIN(cal_start) AS cal_start,MIN(cal_end) AS cal_end',
				array('cal_id'=>$cal_id),__LINE__,__FILE__,false,'','calendar')->fetch();
			// if no recurrence found, create one with the new dates
			if ($change_since === false || !$row || !$row['cal_start'] || !$row['cal_end'])
			{
				$this->db->insert($this->dates_table,array(
					'cal_id'    => $cal_id,
					'cal_start' => $start,
					'cal_end'   => $end,
				),false,__LINE__,__FILE__,'calendar');

				return 1;
			}
			$move_start = (int) ($start-$row['cal_start']);
			$move_end   = (int) ($end-$row['cal_end']);
		}
		else
		{
			$move_start = (int) ($start-$old_start);
			$move_end   = (int) ($end-$old_end);
		}
		$where = 'cal_id='.(int)$cal_id;

		if ($move_start)
		{
			// move the recur-date of the participants
			$this->db->query("UPDATE $this->user_table SET cal_recur_date=cal_recur_date+$move_start WHERE $where AND cal_recur_date ".
				((int)$change_since ? '>= '.(int)$change_since : '!= 0'),__LINE__,__FILE__);
		}
		if ($move_start || $move_end)
		{
			// move the event and it's recurrences
			$this->db->query("UPDATE $this->dates_table SET cal_start=cal_start+$move_start,cal_end=cal_end+$move_end WHERE $where".
				((int) $change_since ? ' AND cal_start >= '.(int) $change_since : ''),__LINE__,__FILE__);
		}
		return $this->db->affected_rows();
	}

	/**
	 * Format attendee as email
	 *
	 * @param string|array $attendee attendee information: email, json or array with attr cn and url
	 * @return type
	 */
	static function attendee2email($attendee)
	{
		if (is_string($attendee) && $attendee[0] == '{' && substr($attendee, -1) == '}')
		{
			$user_attendee = json_decode($user_attendee, true);
		}
		if (is_array($attendee))
		{
			$email = !empty($attendee['email']) ? $user_attendee['email'] :
				(strtolower(substr($attendee['url'], 0, 7)) == 'mailto:' ? substr($user_attendee['url'], 7) : $attendee['url']);
			$attendee = !empty($attendee['cn']) ? $attendee['cn'].' <'.$email.'>' : $email;
		}
		return $attendee;
	}
	/**
	 * combines user_type and user_id into a single string or integer (for users)
	 *
	 * @param string $user_type 1-char type: 'u' = user, ...
	 * @param string|int $user_id id
	 * @param string|array $attendee attendee information: email, json or array with attr cn and url
	 * @return string|int combined id
	 */
	static function combine_user($user_type, $user_id, $attendee=null)
	{
		if (!$user_type || $user_type == 'u')
		{
			return (int) $user_id;
		}
		if ($user_type == 'e' && $attendee)
		{
			$user_id = self::attendee2email($attendee);
		}
		return $user_type.$user_id;
	}

	/**
	 * splits the combined user_type and user_id into a single values
	 *
	 * This is the only method building (normalized) md5 hashes for user_type="e",
	 * if called with $md5_email=true parameter!
	 *
	 * @param string|int $uid
	 * @param string &$user_type 1-char type: 'u' = user, ...
	 * @param string|int &$user_id id
	 * @param boolean $md5_email =false md5 hash user_id for email / user_type=="e"
	 */
	static function split_user($uid, &$user_type, &$user_id, $md5_email=false)
	{
		if (is_numeric($uid))
		{
			$user_type = 'u';
			$user_id = (int) $uid;
		}
		// create md5 hash from lowercased and trimed raw email ("rb@stylite.de", not "Ralf Becker <rb@stylite.de>")
		elseif ($md5_email && $uid[0] == 'e')
		{
			$user_type = $uid[0];
			$email = substr($uid, 1);
			$matches = null;
			if (preg_match('/<([^<>]+)>$/', $email, $matches)) $email = $matches[1];
			$user_id = md5(trim(strtolower($email)));
		}
		else
		{
			$user_type = $uid[0];
			$user_id = substr($uid,1);
		}
	}

	/**
	 * Combine status, quantity and role into one value
	 *
	 * @param string $status status letter: U, T, A, R
	 * @param int $quantity =1
	 * @param string $role ='REQ-PARTICIPANT'
	 * @return string
	 */
	static function combine_status($status,$quantity=1,$role='REQ-PARTICIPANT')
	{
		if ((int)$quantity > 1) $status .= (int)$quantity;
		if ($role != 'REQ-PARTICIPANT') $status .= $role;

		return $status;
	}

	/**
	 * splits the combined status, quantity and role
	 *
	 * @param string &$status I: combined value, O: status letter: U, T, A, R
	 * @param int &$quantity=null only O: quantity
	 * @param string &$role=null only O: role
	 * @return string status U, T, A or R, same as $status parameter on return
	 */
	static function split_status(&$status,&$quantity=null,&$role=null)
	{
		$quantity = 1;
		$role = 'REQ-PARTICIPANT';
		//error_log(__METHOD__.__LINE__.array2string($status));
		$matches = null;
		if (is_string($status) && strlen($status) > 1 && preg_match('/^.([0-9]*)(.*)$/',$status,$matches))
		{
			if ((int)$matches[1] > 0) $quantity = (int)$matches[1];
			if ($matches[2]) $role = $matches[2];
			$status = $status[0];
		}
		elseif ($status === true)
		{
			$status = 'U';
		}
		return $status;
	}

	/**
	 * updates the participants of an event, taken into account the evtl. recurrences of the event(!)
	 * this method just adds new participants or removes not longer set participants
	 * this method does never overwrite existing entries (except the 0-recurrence and for delete)
	 *
	 * @param int $cal_id
	 * @param array $participants uid => status pairs
	 * @param int|boolean $change_since =0, false=new event,
	 * 		0=all, > 0 time from which on the repetitions should be changed
	 * @param boolean $add_only =false
	 *		false = add AND delete participants if needed (full list of participants required in $participants)
	 *		true = only add participants if needed, no participant will be deleted (participants to check/add required in $participants)
	 * @return int|boolean number of updated recurrences or false on error
	 */
	function participants($cal_id,$participants,$change_since=0,$add_only=false)
	{
		//error_log(__METHOD__."($cal_id,".array2string($participants).",$change_since,$add_only");

		$recurrences = array();

		// remove group-invitations, they are NOT stored in the db
		foreach($participants as $uid => $status)
		{
			if ($status[0] == 'G')
			{
				unset($participants[$uid]);
			}
		}
		$where = array('cal_id' => $cal_id);

		if ((int) $change_since)
		{
			$where[] = '(cal_recur_date=0 OR cal_recur_date >= '.(int)$change_since.')';
		}

		if ($change_since !== false)
		{
			// find all existing recurrences
			foreach($this->db->select($this->user_table,'DISTINCT cal_recur_date',$where,__LINE__,__FILE__,false,'','calendar') as $row)
			{
				$recurrences[] = $row['cal_recur_date'];
			}

			// update existing entries
			$existing_entries = $this->db->select($this->user_table,'*',$where,__LINE__,__FILE__,false,'ORDER BY cal_recur_date DESC','calendar');

			// create a full list of participants which already exist in the db
			// with status, quantity and role of the earliest recurence
			$old_participants = array();
			foreach($existing_entries as $row)
			{
				$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
				if ($row['cal_recur_date'] || !isset($old_participants[$uid]))
				{
					$old_participants[$uid] = self::combine_status($row['cal_status'],$row['cal_quantity'],$row['cal_role']);
				}
			}

			// tag participants which should be deleted
			if($add_only === false)
			{
				$deleted = array();
				foreach($existing_entries as $row)
				{
					$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
					// delete not longer set participants
					if (!isset($participants[$uid]))
					{
						$deleted[$row['cal_user_type']][] = $row['cal_user_id'];
					}
				}
			}

			// only keep added OR status (incl. quantity!) changed participants for further steps
			// we do not touch unchanged (!) existing ones
			foreach($participants as $uid => $status)
			{
				if ($old_participants[$uid] === $status)
				{
					unset($participants[$uid]);
				}
			}

			// delete participants tagged for delete
			if ($add_only === false && count($deleted))
			{
				$to_or = array();
				$table_def = $this->db->get_table_definitions('calendar',$this->user_table);
				foreach($deleted as $type => $ids)
				{
					$to_or[] = $this->db->expression($table_def,array(
						'cal_user_type' => $type,
						'cal_user_id'   => $ids,
					));
				}
				$where[] = '('.implode(' OR ',$to_or).')';
				$where[] = "cal_status!='E'";	// do NOT delete exception marker
				$this->db->update($this->user_table,array('cal_status'=>'X'),$where,__LINE__,__FILE__,'calendar');
			}
		}

		if (count($participants))	// participants which need to be added
		{
			if (!count($recurrences)) $recurrences[] = 0;   // insert the default recurrence

			$delete_deleted = array();

			// update participants
			foreach($participants as $uid => $status)
			{
				$type = $id = $quantity = $role = null;
				self::split_user($uid, $type, $id, true);
				self::split_status($status,$quantity,$role);
				$set = array(
					'cal_status'	  => $status,
					'cal_quantity'	  => $quantity,
					'cal_role'        => $role,
					'cal_user_attendee' => $type == 'e' ? substr($uid, 1) : null,
				);
				foreach($recurrences as $recur_date)
				{
					$this->db->insert($this->user_table,$set,array(
						'cal_id'	      => $cal_id,
						'cal_recur_date'  => $recur_date,
						'cal_user_type'   => $type,
						'cal_user_id' 	  => $id,
					),__LINE__,__FILE__,'calendar');
				}
				// for new or changed group-invitations, remove previously deleted members, so they show up again
				if ($uid < 0)
				{
					$delete_deleted = array_merge($delete_deleted, $GLOBALS['egw']->accounts->members($uid, true));
				}
			}
			if ($delete_deleted)
			{
				$this->db->delete($this->user_table, $where=array(
					'cal_id' => $cal_id,
					'cal_recur_date' => $recurrences,
					'cal_user_type' => 'u',
					'cal_user_id' => array_unique($delete_deleted),
					'cal_status' => 'X',
				),__LINE__,__FILE__,'calendar');
				//error_log(__METHOD__."($cal_id, ".array2string($participants).", since=$change_since, add_only=$add_only) db->delete('$this->user_table', ".array2string($where).") affected ".$this->db->affected_rows().' rows');
			}
		}
		return true;
	}

	/**
	 * set the status of one participant for a given recurrence or for all recurrences since now (includes recur_date=0)
	 *
	 * @param int $cal_id
	 * @param char $user_type 'u' regular user, 'r' resource, 'c' contact
	 * @param int|string $user_id
	 * @param int|char $status numeric status (defines) or 1-char code: 'R', 'U', 'T' or 'A'
	 * @param int $recur_date =0 date to change, or 0 = all since now
	 * @param string $role =null role to set if !is_null($role)
	 * @param string $attendee =null extra attendee information to set for all types (incl. accounts!)
	 * @return int number of changed recurrences
	 */
	function set_status($cal_id,$user_type,$user_id,$status,$recur_date=0,$role=null,$attendee=null)
	{
		static $status_code_short = array(
			REJECTED 	=> 'R',
			NO_RESPONSE	=> 'U',
			TENTATIVE	=> 'T',
			ACCEPTED	=> 'A',
			DELEGATED	=> 'D'
		);
		if (!(int)$cal_id || !(int)$user_id && $user_type != 'e')
		{
			return false;
		}

		if (is_numeric($status)) $status = $status_code_short[$status];

		$uid = self::combine_user($user_type, $user_id);
		$user_id_md5 = null;
		self::split_user($uid, $user_type, $user_id_md5, true);

		$where = array(
			'cal_id'		=> $cal_id,
			'cal_user_type'	=> $user_type,
			'cal_user_id'   => $user_id_md5,
		);
		if ((int) $recur_date)
		{
			$where['cal_recur_date'] = $recur_date;
		}
		else
		{
			$where[] = '(cal_recur_date=0 OR cal_recur_date >= '.time().')';
		}

		if ($status == 'G')		// remove group invitations, as we dont store them in the db
		{
			$this->db->delete($this->user_table,$where,__LINE__,__FILE__,'calendar');
			$ret = $this->db->affected_rows();
		}
		else
		{
			$set = array('cal_status' => $status);
			if ($user_type == 'e' || $attendee) $set['cal_user_attendee'] = $attendee ? $attendee : $user_id;
			if (!is_null($role) && $role != 'REQ-PARTICIPANT') $set['cal_role'] = $role;
			$this->db->insert($this->user_table,$set,$where,__LINE__,__FILE__,'calendar');
			// for new or changed group-invitations, remove previously deleted members, so they show up again
			if (($ret = $this->db->affected_rows()) && $user_type == 'u' && $user_id < 0)
			{
				$where['cal_user_id'] = $GLOBALS['egw']->accounts->members($user_id, true);
				$where['cal_status'] = 'X';
				$this->db->delete($this->user_table, $where, __LINE__, __FILE__, 'calendar');
				//error_log(__METHOD__."($cal_id,$user_type,$user_id,$status,$recur_date) = $ret, db->delete('$this->user_table', ".array2string($where).") affected ".$this->db->affected_rows().' rows');
			}
		}
		// update modified and modifier in main table
		if ($ret)
		{
			$this->updateModified($cal_id, true);	// true = update series master too
		}
		//error_log(__METHOD__."($cal_id,$user_type,$user_id,$status,$recur_date) = $ret");
		return $ret;
	}

	/**
	 * creates or update a recurrence in the dates and users table
	 *
	 * @param int $cal_id
	 * @param int $start
	 * @param int $end
	 * @param array $participants uid => status pairs
	 * @param boolean $exception =null true or false to set recure_exception flag, null leave it unchanged (new are by default no exception)
	 */
	function recurrence($cal_id,$start,$end,$participants,$exception=null)
	{
		//error_log(__METHOD__."($cal_id, $start, $end, ".array2string($participants).", ".array2string($exception));
		$update = array('cal_end' => $end);
		if (isset($exception)) $update['recur_exception'] = $exception;

		$this->db->insert($this->dates_table, $update, array(
			'cal_id' => $cal_id,
			'cal_start'  => $start,
		),__LINE__,__FILE__,'calendar');

		if (!is_array($participants))
		{
			error_log(__METHOD__."($cal_id, $start, $end, ".array2string($participants).") participants is NO array! ".function_backtrace());
		}
		if ($exception !== true)
		{
			foreach($participants as $uid => $status)
			{
				if ($status == 'G') continue;	// dont save group-invitations

				$type = '';
				$id = null;
				self::split_user($uid, $type, $id, true);
				$quantity = $role = null;
				self::split_status($status,$quantity,$role);
				$this->db->insert($this->user_table,array(
					'cal_status'	=> $status,
					'cal_quantity'	=> $quantity,
					'cal_role'		=> $role,
					'cal_user_attendee' => $type == 'e' ? substr($uid, 1) : null,
				),array(
					'cal_id'		 => $cal_id,
					'cal_recur_date' => $start,
					'cal_user_type'  => $type,
					'cal_user_id' 	 => $id,
				),__LINE__,__FILE__,'calendar');
			}
		}
	}

	/**
	 * Get all unfinished recuring events (or all users) after a given time
	 *
	 * @param int $time
	 * @return array with cal_id => max(cal_start) pairs
	 */
	function unfinished_recuring($time)
	{
		$ids = array();
		foreach($rs=$this->db->select($this->repeats_table, "$this->repeats_table.cal_id,MAX(cal_start) AS cal_start",
			'(range_end IS NULL OR range_end > '.(int)$time.')',
			__LINE__, __FILE__, false, "GROUP BY $this->repeats_table.cal_id,range_end", 'calendar', 0,
			" JOIN $this->cal_table ON $this->repeats_table.cal_id=$this->cal_table.cal_id".
			" JOIN $this->dates_table ON $this->repeats_table.cal_id=$this->dates_table.cal_id") as $row)
		{
			$ids[$row['cal_id']] = $row['cal_start'];
		}
		//error_log(__METHOD__."($time) query='$rs->sql' --> ids=".array2string($ids));
		return $ids;
	}

	/**
	 * deletes an event incl. all recurrences, participants and alarms
	 *
	 * @param int $cal_id
	 */
	function delete($cal_id)
	{
		//echo "<p>socal::delete($cal_id)</p>\n";

		$this->delete_alarms($cal_id);

		// update timestamp of series master, updates own timestamp too, which does not hurt ;-)
		$this->updateModified($cal_id, true);

		foreach($this->all_tables as $table)
		{
			$this->db->delete($table,array('cal_id'=>$cal_id),__LINE__,__FILE__,'calendar');
		}
	}

	/**
	 * Delete all events that were before the given date.
	 *
	 * Recurring events that finished before the date will be deleted.
	 * Recurring events that span the date will be ignored.  Non-recurring
	 * events before the date will be deleted.
	 *
	 * @param int $date
	 */
	function purge($date)
	{
		// with new range_end we simple delete all with range_end < $date (range_end NULL is never returned)
		foreach($this->db->select($this->cal_table, 'cal_id', 'range_end < '.(int)$date, __LINE__, __FILE__, false, '', 'calendar') as $row)
		{
			//echo __METHOD__." About to delete".$row['cal_id']."\r\n";
			foreach($this->all_tables as $table)
			{
				$this->db->delete($table, array('cal_id'=>$row['cal_id']), __LINE__, __FILE__, 'calendar');
			}
			// handle links
			Link::unlink('', 'calendar', $row['cal_id']);
		}
	}

	/**
	 * Caches all alarms read from async table to not re-read them in same request
	 *
	 * @var array cal_id => array(async_id => data)
	 */
	static $alarm_cache;

	/**
	 * read the alarms of one or more calendar-event(s) specified by $cal_id
	 *
	 * alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
	 *
	 * @param int|array $cal_id
	 * @param boolean $update_cache =null true: re-read given $cal_id, false: delete given $cal_id
	 * @return array of (cal_id => array of) alarms with alarm-id as key
	 */
	function read_alarms($cal_id, $update_cache=null)
	{
		if (!isset(self::$alarm_cache) && is_array($cal_id))
		{
			self::$alarm_cache = array();
			if (($jobs = $this->async->read('cal:%')))
			{
				foreach($jobs as $id => $job)
				{
					$alarm         = $job['data'];	// text, enabled
					$alarm['id']   = $id;
					$alarm['time'] = $job['next'];

					self::$alarm_cache[$alarm['cal_id']][$id] = $alarm;
				}
			}
			unset($update_cache);	// just done
		}
		$alarms = array();

		if (isset(self::$alarm_cache))
		{
			if (isset($update_cache))
			{
				foreach((array)$cal_id as $id)
				{
					if ($update_cache === false)
					{
						unset(self::$alarm_cache[$cal_id]);
					}
					elseif($update_cache === true)
					{
						self::$alarm_cache[$cal_id] = $this->read_alarms_nocache($cal_id);
					}
				}
			}
			if (!is_array($cal_id))
			{
				$alarms = (array)self::$alarm_cache[$cal_id];
			}
			else
			{
				foreach($cal_id as $id)
				{
					$alarms[$id] = (array)self::$alarm_cache[$id];
				}
			}
			//error_log(__METHOD__."(".array2string($cal_id).", ".array2string($update_cache).") returning from cache ".array2string($alarms));
			return $alarms;
		}
		return $this->read_alarms_nocache($cal_id);
	}

	private function read_alarms_nocache($cal_id)
	{
		if (($jobs = $this->async->read('cal:'.(int)$cal_id.':%')))
		{
			foreach($jobs as $id => $job)
			{
				$alarm         = $job['data'];	// text, enabled
				$alarm['id']   = $id;
				$alarm['time'] = $job['next'];

				$alarms[$id] = $alarm;
			}
		}
		//error_log(__METHOD__."(".array2string($cal_id).") returning ".array2string($alarms));
		return $alarms ? $alarms : array();
	}

	/**
	 * read a single alarm specified by it's $id
	 *
	 * @param string $id alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
	 * @return array with data of the alarm
	 */
	function read_alarm($id)
	{
		if (!($jobs = $this->async->read($id)))
		{
			return False;
		}
		list($alarm_id,$job) = each($jobs);
		$alarm         = $job['data'];	// text, enabled
		$alarm['id']   = $alarm_id;
		$alarm['time'] = $job['next'];

		//echo "<p>read_alarm('$id')="; print_r($alarm); echo "</p>\n";
		return $alarm;
	}

	/**
	 * saves a new or updated alarm
	 *
	 * @param int $cal_id Id of the calendar-entry
	 * @param array $alarm array with fields: text, owner, enabled, ..
	 * @param boolean $update_modified =true call update modified, default true
	 * @return string id of the alarm
	 */
	function save_alarm($cal_id, $alarm, $update_modified=true)
	{
		//error_log(__METHOD__."($cal_id, ".array2string($alarm).', '.array2string($update_modified).') '.function_backtrace());
		if (!($id = $alarm['id']))
		{
			$alarms = $this->read_alarms($cal_id);	// find a free alarm#
			$n = count($alarms);
			do
			{
				$id = 'cal:'.(int)$cal_id.':'.$n;
				++$n;
			}
			while (@isset($alarms[$id]));
		}
		else
		{
			$this->async->cancel_timer($id);
		}
		$alarm['cal_id'] = $cal_id;		// we need the back-reference
		// do not deleted async-job, as we need it for alarm snozzing
		$alarm['keep'] = self::ALARM_KEEP_TIME;
		// past alarms need NOT to be triggered, but kept around for a while to allow alarm snozzing
		if ($alarm['time'] < time())
		{
			$alarm['time'] = $alarm['keep_time'] = time()+self::ALARM_KEEP_TIME;
		}
		// add an alarm uid, if none is given
		if (empty($alarm['uid']) && class_exists('Horde_Support_Uuid')) $alarm['uid'] = (string)new Horde_Support_Uuid;
		//error_log(__METHOD__.__LINE__.' Save Alarm for CalID:'.$cal_id.'->'.array2string($alarm).'-->'.$id.'#'.function_backtrace());
		// allways store job with the alarm owner as job-owner to get eg. the correct from address
		if (!$this->async->set_timer($alarm['time'], $id, 'calendar.calendar_boupdate.send_alarm', $alarm, $alarm['owner'], false, true))
		{
			return False;
		}

		// update the modification information of the related event
		if ($update_modified) $this->updateModified($cal_id, true);

		// update cache, if used
		if (isset(self::$alarm_cache)) $this->read_alarms($cal_id, true);

		return $id;
	}

	/**
	 * Delete all alarms of a calendar-entry
	 *
	 * Does not update timestamps of series master, therefore private!
	 *
	 * @param int $cal_id Id of the calendar-entry
	 * @return int number of alarms deleted
	 */
	private function delete_alarms($cal_id)
	{
		//error_log(__METHOD__."($cal_id) ".function_backtrace());
		if (($alarms = $this->read_alarms($cal_id)))
		{
			foreach(array_keys($alarms) as $id)
			{
				$this->async->cancel_timer($id);
			}
			// update cache, if used
			if (isset(self::$alarm_cache)) $this->read_alarms($cal_id, false);
		}
		return count($alarms);
	}

	/**
	 * delete one alarms identified by its id
	 *
	 * @param string $id alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
	 * @return int number of alarms deleted
	 */
	function delete_alarm($id)
	{
		//error_log(__METHOD__."('$id') ".function_backtrace());
		// update the modification information of the related event
		list(,$cal_id) = explode(':',$id);
		if ($cal_id)
		{
			$this->updateModified($cal_id, true);
		}
		$ret = $this->async->cancel_timer($id);

		// update cache, if used
		if (isset(self::$alarm_cache)) $this->read_alarms($cal_id, true);

		return $ret;
	}

	/**
	 * Delete account hook
	 *
	 * @param array|int $old_user integer old user or array with keys 'account_id' and 'new_owner' as the deleteaccount hook uses it
	 * @param int $new_user =null
	 */
	function deleteaccount($old_user, $new_user=null)
	{
		if (is_array($old_user))
		{
			$new_user = $old_user['new_owner'];
			$old_user = $old_user['account_id'];
		}
		if (!(int)$new_user)
		{
			$user_type = '';
			$user_id = null;
			self::split_user($old_user,$user_type,$user_id);

			if ($user_type == 'u')	// only accounts can be owners of events
			{
				foreach($this->db->select($this->cal_table,'cal_id',array('cal_owner' => $old_user),__LINE__,__FILE__,false,'','calendar') as $row)
				{
					$this->delete($row['cal_id']);
				}
			}
			$this->db->delete($this->user_table,array(
				'cal_user_type' => $user_type,
				'cal_user_id'   => $user_id,
			),__LINE__,__FILE__,'calendar');

			// delete calendar entries without participants (can happen if the deleted user is the only participants, but not the owner)
			foreach($this->db->select($this->cal_table,"DISTINCT $this->cal_table.cal_id",'cal_user_id IS NULL',__LINE__,__FILE__,
				False,'','calendar',0,"LEFT JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id") as $row)
			{
				$this->delete($row['cal_id']);
			}
		}
		else
		{
			$this->db->update($this->cal_table,array('cal_owner' => $new_user),array('cal_owner' => $old_user),__LINE__,__FILE__,'calendar');
			// delete participation of old user, if new user is already a participant
			$ids = array();
			foreach($this->db->select($this->user_table,'cal_id',array(		// MySQL does NOT allow to run this as delete!
				'cal_user_type' => 'u',
				'cal_user_id' => $old_user,
				"cal_id IN (SELECT cal_id FROM $this->user_table other WHERE other.cal_id=cal_id AND other.cal_user_id=".$this->db->quote($new_user)." AND cal_user_type='u')",
			),__LINE__,__FILE__,false,'','calendar') as $row)
			{
				$ids[] = $row['cal_id'];
			}
			if ($ids) $this->db->delete($this->user_table,array(
				'cal_user_type' => 'u',
				'cal_user_id' => $old_user,
				'cal_id' => $ids,
			),__LINE__,__FILE__,'calendar');
			// now change participant in the rest to contain new user instead of old user
			$this->db->update($this->user_table,array(
				'cal_user_id' => $new_user,
			),array(
				'cal_user_type' => 'u',
				'cal_user_id' => $old_user,
			),__LINE__,__FILE__,'calendar');
		}
	}

	/**
	 * get stati of all recurrences of an event for a specific participant
	 *
	 * @param int $cal_id
	 * @param int $uid =null  participant uid; if == null return only the recur dates
	 * @param int $start =0  if != 0: startdate of the search/list (servertime)
	 * @param int $end =0  if != 0: enddate of the search/list (servertime)
	 *
	 * @return array recur_date => status pairs (index 0 => main status)
	 */
	function get_recurrences($cal_id, $uid=null, $start=0, $end=0)
	{
		$participant_status = array();
		$where = array('cal_id' => $cal_id);
		if ($start != 0 && $end == 0) $where[] = '(cal_recur_date = 0 OR cal_recur_date >= ' . (int)$start . ')';
		if ($start == 0 && $end != 0) $where[] = '(cal_recur_date = 0 OR cal_recur_date <= ' . (int)$end . ')';
		if ($start != 0 && $end != 0)
		{
			$where[] = '(cal_recur_date = 0 OR (cal_recur_date >= ' . (int)$start .
						' AND cal_recur_date <= ' . (int)$end . '))';
		}
		foreach($this->db->select($this->user_table,'DISTINCT cal_recur_date',$where,__LINE__,__FILE__,false,'','calendar') as $row)
		{
			// inititalize the array
			$participant_status[$row['cal_recur_date']] = null;
		}
		if (is_null($uid)) return $participant_status;
		$user_type = $user_id = null;
		self::split_user($uid, $user_type, $user_id, true);

		$where2 = array(
			'cal_id'		=> $cal_id,
			'cal_user_type'	=> $user_type ? $user_type : 'u',
			'cal_user_id'   => $user_id,
		);
		if ($start != 0 && $end == 0) $where2[] = '(cal_recur_date = 0 OR cal_recur_date >= ' . (int)$start . ')';
		if ($start == 0 && $end != 0) $where2[] = '(cal_recur_date = 0 OR cal_recur_date <= ' . (int)$end . ')';
		if ($start != 0 && $end != 0)
		{
			$where2[] = '(cal_recur_date = 0 OR (cal_recur_date >= ' . (int)$start .
						' AND cal_recur_date <= ' . (int)$end . '))';
		}
		foreach ($this->db->select($this->user_table,'cal_recur_date,cal_status,cal_quantity,cal_role',$where2,
				__LINE__,__FILE__,false,'','calendar') as $row)
		{
			$status = self::combine_status($row['cal_status'],$row['cal_quantity'],$row['cal_role']);
			$participant_status[$row['cal_recur_date']] = $status;
		}
		return $participant_status;
	}

	/**
	 * get all participants of an event
	 *
	 * @param int $cal_id
	 * @param int $recur_date =0 gives participants of this recurrence, default 0=all
	 *
	 * @return array participants
	 */
	/* seems NOT to be used anywhere, NOT ported to new md5-email schema!
	function get_participants($cal_id, $recur_date=0)
	{
		$participants = array();
		$where = array('cal_id' => $cal_id);
		if ($recur_date)
		{
			$where['cal_recur_date'] = $recur_date;
		}

		foreach ($this->db->select($this->user_table,'DISTINCT cal_user_type,cal_user_id', $where,
				__LINE__,__FILE__,false,'','calendar') as $row)
		{
			$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id']);
			$id = $row['cal_user_type'] . $row['cal_user_id'];
			$participants[$id]['type'] = $row['cal_user_type'];
			$participants[$id]['id'] = $row['cal_user_id'];
			$participants[$id]['uid'] = $uid;
		}
		return $participants;
	}*/

	/**
	 * get all releated events
	 *
	 * @param int $uid					UID of the series
	 *
	 * @return array of event exception ids for all events which share $uid
	 */
	function get_related($uid)
	{
		$where = array(
			'cal_uid'		=> $uid,
		);
		$related = array();
		foreach ($this->db->select($this->cal_table,'cal_id,cal_reference',$where,
				__LINE__,__FILE__,false,'','calendar') as $row)
		{
			if ($row['cal_reference'] != 0)
			{
				// not the series master
				$related[] = $row['cal_id'];
			}
		}
		return $related;
	}

	/**
	 * Gets the exception days of a given recurring event caused by
	 * irregular participant stati or timezone transitions
	 *
	 * @param array $event			Recurring Event.
	 * @param string tz_id=null		timezone for exports (null for event's timezone)
	 * @param int $start =0  if != 0: startdate of the search/list (servertime)
	 * @param int $end =0  if != 0:	enddate of the search/list (servertime)
	 * @param string $filter ='all'	string filter-name: all (not rejected),
	 * 		accepted, unknown, tentative, rejected, delegated
	 *      rrule					return array of remote exceptions in servertime
	 * 		tz_rrule/tz_only,		return (only by) timezone transition affected entries
	 * 		map						return array of dates with no pseudo exception
	 * 									key remote occurrence date
	 * 		tz_map					return array of all dates with no tz pseudo exception
	 *
	 * @return array		Array of exception days (false for non-recurring events).
	 */
	function get_recurrence_exceptions($event, $tz_id=null, $start=0, $end=0, $filter='all')
	{
		if (!is_array($event)) return false;
		$cal_id = (int) $event['id'];
		//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
		//		"($cal_id, $tz_id, $filter): " . $event['tzid']);
		if (!$cal_id || $event['recur_type'] == MCAL_RECUR_NONE) return false;

		$days = array();

		$expand_all = (!$this->isWholeDay($event) && $tz_id && $tz_id != $event['tzid']);

		if ($filter == 'tz_only' && !$expand_all) return $days;

		$remote = in_array($filter, array('tz_rrule', 'rrule'));

		$egw_rrule = calendar_rrule::event2rrule($event, false);
		$egw_rrule->current = clone $egw_rrule->time;
		if ($expand_all)
		{
			unset($event['recur_exception']);
			$remote_rrule = calendar_rrule::event2rrule($event, false, $tz_id);
			$remote_rrule->current = clone $remote_rrule->time;
		}
		while ($egw_rrule->valid())
		{
			while ($egw_rrule->exceptions &&
				in_array($egw_rrule->current->format('Ymd'),$egw_rrule->exceptions))
			{
				if (in_array($filter, array('map','tz_map','rrule','tz_rrule')))
				{
					 // real exception
					$locts = (int)Api\DateTime::to($egw_rrule->current(),'server');
					if ($expand_all)
					{
						$remts = (int)Api\DateTime::to($remote_rrule->current(),'server');
						if ($remote)
						{
							$days[$locts]= $remts;
						}
						else
						{
							$days[$remts]= $locts;
						}
					}
					else
					{
						$days[$locts]= $locts;
					}
				}
				if ($expand_all)
				{
					$remote_rrule->next_no_exception();
				}
				$egw_rrule->next_no_exception();
				if (!$egw_rrule->valid()) return $days;
			}
			$day = $egw_rrule->current();
			$locts = (int)Api\DateTime::to($day,'server');
			$tz_exception = ($filter == 'tz_rrule');
			//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
			//	'()[EVENT Server]: ' . $day->format('Ymd\THis') . " ($locts)");
			if ($expand_all)
			{
				$remote_day = $remote_rrule->current();
				$remts = (int)Api\DateTime::to($remote_day,'server');
			//	error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
			//	'()[EVENT Device]: ' . $remote_day->format('Ymd\THis') . " ($remts)");
			}


			if (!($end && $end < $locts) && $start <= $locts)
			{
				// we are within the relevant time period
				if ($expand_all && $day->format('U') != $remote_day->format('U'))
				{
					$tz_exception = true;
					if ($filter != 'map' && $filter != 'tz_map')
					{
						// timezone pseudo exception
						//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						//	'() tz exception: ' . $day->format('Ymd\THis'));
						if ($remote)
						{
							$days[$locts]= $remts;
						}
						else
						{
							$days[$remts]= $locts;
						}
					}
				}
				if ($filter != 'tz_map' && (!$tz_exception || $filter == 'tz_only') &&
					$this->status_pseudo_exception($event['id'], $locts, $filter))
				{
					// status pseudo exception
					//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					//	'() status exception: ' . $day->format('Ymd\THis'));
					if ($expand_all)
					{
						if ($filter == 'tz_only')
						{
								unset($days[$remts]);
						}
						else
						{
							if ($filter != 'map')
							{
								if ($remote)
								{
									$days[$locts]= $remts;
								}
								else
								{
									$days[$remts]= $locts;
								}
							}
						}
					}
					elseif ($filter != 'map')
					{
						$days[$locts]= $locts;
					}
				}
				elseif (($filter == 'map' || filter == 'tz_map') &&
						!$tz_exception)
				{
					// no pseudo exception date
					if ($expand_all)
					{

						$days[$remts]= $locts;
					}
					else
					{
						$days[$locts]= $locts;
					}
				}
			}
			if ($expand_all)
			{
				$remote_rrule->next_no_exception();
			}
			$egw_rrule->next_no_exception();
		}
		return $days;
	}

	/**
	 * Checks for status only pseudo exceptions
	 *
	 * @param int $cal_id		event id
	 * @param int $recur_date	occurrence to check
	 * @param string $filter	status filter criteria for user
	 *
	 * @return boolean			true, if stati don't match with defaults
	 */
	function status_pseudo_exception($cal_id, $recur_date, $filter)
	{
		static $recurrence_zero=null;
		static $cached_id=null;
		static $user=null;

		if (!isset($cached_id) || $cached_id != $cal_id)
		{
			// get default stati
			$recurrence_zero = array();
			$user = $GLOBALS['egw_info']['user']['account_id'];
			$where = array(
				'cal_id' => $cal_id,
				'cal_recur_date' => 0,
			);
			foreach ($this->db->select($this->user_table,'cal_user_type,cal_user_id,cal_user_attendee,cal_status',$where,
				__LINE__,__FILE__,false,'','calendar') as $row)
			{
				switch ($row['cal_user_type'])
				{
					case 'u':	// account
					case 'c':	// contact
					case 'e':	// email address
						$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
						$recurrence_zero[$uid] = $row['cal_status'];
				}
			}
			$cached_id = $cal_id;
		}

		//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
		//	"($cal_id, $recur_date, $filter)[DEFAULTS]: " .
		//	array2string($recurrence_zero));

		$participants = array();
		$where = array(
			'cal_id' => $cal_id,
			'cal_recur_date' => $recur_date,
		);
		foreach ($this->db->select($this->user_table,'cal_user_type,cal_user_id,cal_user_attendee,cal_status',$where,
			__LINE__,__FILE__,false,'','calendar') as $row)
		{
			switch ($row['cal_user_type'])
			{
				case 'u':	// account
				case 'c':	// contact
				case 'e':	// email address
					$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id'], $row['cal_user_attendee']);
					$participants[$uid] = $row['cal_status'];
			}
		}

		if (empty($participants)) return false; // occurrence does not exist at all yet

		foreach ($recurrence_zero as $uid => $status)
		{
			if ($uid == $user)
			{
				// handle filter for current user
				switch ($filter)
				{
					case 'unknown':
						if ($status != 'U')
						{
							unset($participants[$uid]);
							continue 2;	// +1 for switch
						}
						break;
					case 'accepted':
						if ($status != 'A')
						{
							unset($participants[$uid]);
							continue 2;	// +1 for switch
						}
						break;
					case 'tentative':
						if ($status != 'T')
						{
							unset($participants[$uid]);
							continue 2;	// +1 for switch
						}
						break;
					case 'rejected':
						if ($status != 'R')
						{
							unset($participants[$uid]);
							continue 2;	// +1 for switch
						}
						break;
					case 'delegated':
						if ($status != 'D')
						{
							unset($participants[$uid]);
							continue 2;	// +1 for switch
						}
						break;
					case 'default':
						if ($status == 'R')
						{
							unset($participants[$uid]);
							continue 2;	// +1 for switch
						}
						break;
					default:
						// All entries
				}
			}
			if (!isset($participants[$uid])
				|| $participants[$uid] != $status)
				return true;
			unset($participants[$uid]);
		}
		return (!empty($participants));
	}

	/**
	 * Check if the event is the whole day
	 *
	 * @param array $event event (all timestamps in servertime)
	 * @return boolean true if whole day event within its timezone, false othwerwise
	 */
	function isWholeDay($event)
	{
		if (!isset($event['start']) || !isset($event['end'])) return false;

		if (empty($event['tzid']))
		{
			$timezone = Api\DateTime::$server_timezone;
		}
		else
		{
			if (!isset(self::$tz_cache[$event['tzid']]))
			{
				self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
			}
			$timezone = self::$tz_cache[$event['tzid']];
		}
		$start_time = new Api\DateTime($event['start'],Api\DateTime::$server_timezone);
		$start_time->setTimezone($timezone);
		$end_time = new Api\DateTime($event['end'],Api\DateTime::$server_timezone);
		$end_time->setTimezone($timezone);
		//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
		//	'(): ' . $start . '-' . $end);
		$start = Api\DateTime::to($start_time,'array');
		$end = Api\DateTime::to($end_time,'array');


		return !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;
	}

	/**
	 * Moves a datetime to the beginning of the day within timezone
	 *
	 * @param Api\DateTime	$time	the datetime entry
	 * @param string tz_id		timezone
	 *
	 * @return DateTime
	 */
	function &startOfDay(Api\DateTime $time, $tz_id=null)
	{
		if (empty($tz_id))
		{
			$timezone = Api\DateTime::$server_timezone;
		}
		else
		{
			if (!isset(self::$tz_cache[$tz_id]))
			{
				self::$tz_cache[$tz_id] = calendar_timezones::DateTimeZone($tz_id);
			}
			$timezone = self::$tz_cache[$tz_id];
		}
		return new Api\DateTime($time->format('Y-m-d 00:00:00'), $timezone);
	}

	/**
	 * Updates the modification timestamp to force an etag, ctag and sync-token change
	 *
	 * @param int $id event id
	 * @param int|boolean $update_master =false id of series master or true, to update series master too
	 * @param int $time =null new timestamp, default current (server-)time
	 * @param int $modifier =null uid of the modifier, default current user
	 */
	function updateModified($id, $update_master=false, $time=null, $modifier=null)
	{
		if (is_null($time) || !$time) $time = time();
		if (is_null($modifier)) $modifier = $GLOBALS['egw_info']['user']['account_id'];

		$this->db->update($this->cal_table,
			array('cal_modified' => $time, 'cal_modifier' => $modifier),
			array('cal_id' => $id), __LINE__,__FILE__, 'calendar');

		// if event is an exception: update modified of master, to force etag, ctag and sync-token change
		if ($update_master)
		{
			if ($update_master !== true || ($update_master = $this->db->select($this->cal_table, 'cal_reference', array('cal_id' => $id), __LINE__, __FILE__)->fetchColumn()))
			{
				$this->updateModified($update_master, false, $time, $modifier);
			}
		}
	}
}
