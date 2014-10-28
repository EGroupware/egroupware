<?php
/**
 * EGroupware - Calendar's storage-object
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Christian Binder <christian-AT-jaytraxx.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2005-14 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
 * All update methods not take care to update modification time of (evtl. existing) series master too,
 * to force an etag, ctag and sync-token change! Methods not doing that are private to this class.
 *
 * range_start/_end in main-table contains start and end of whole event series (range_end is NULL for unlimited recuring events),
 * saving the need to always join dates table, to query non-enumerating recuring events (like CalDAV or ActiveSync does).
 * This effectivly stores MIN(cal_start) and MAX(cal_end) permanently as column in main-table and improves speed tremendiously
 * (few milisecs instead of more then 2 minutes on huge installations)!
 * It's set in calendar_so::save from start and end or recur_enddate, so nothing changes for higher level classes.
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
	 * @var egw_db
	 */
	var $db;
	/**
	 * instance of the async object
	 *
	 * @var asyncservice
	 */
	var $async;
	/**
	 * SQL to sort by status U, T, A, R
	 *
	 */
	const STATUS_SORT = "CASE cal_status WHEN 'U' THEN 1 WHEN 'T' THEN 2 WHEN 'A' THEN 3 WHEN 'R' THEN 4 ELSE 0 END ASC";

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
	 * reads one or more calendar entries
	 *
	 * All times (start, end and modified) are returned as timesstamps in servertime!
	 *
	 * @param int|array|string $ids id or array of id's of the entries to read, or string with a single uid
	 * @param int $recur_date=0 if set read the next recurrence at or after the timestamp, default 0 = read the initital one
	 * @return array|boolean array with cal_id => event array pairs or false if entry not found
	 */
	function read($ids,$recur_date=0)
	{
		//_debug_array(__METHOD__.__LINE__.'#'.$recur_date.'#'.function_backtrace());
		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length']))
		{
			$minimum_uid_length = $GLOBALS['egw_info']['user']['preferences']['syncml']['minimum_uid_length'];
		}
		else
		{
			$minimum_uid_length = 8;
		}

		//echo "<p>socal::read(".print_r($ids,true).",$recur_date)<br />\n".function_backtrace()."<p>\n";

		$cols = self::get_columns('calendar', $this->cal_table);
		$cols[0] = $this->db->to_varchar($this->cal_table.'.cal_id');
		$cols = "$this->repeats_table.recur_type,$this->repeats_table.recur_interval,$this->repeats_table.recur_data,".implode(',',$cols);
		$join = "LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id";

		$where = array();
		if (is_scalar($ids) && !is_numeric($ids))	// a single uid
		{
			// We want only the parents to match
			$where['cal_uid'] = $ids;
			$where['cal_reference'] = 0;
			$where['cal_recurrence'] = 0;
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
		if ((int) $recur_date)
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
		$cols .= ',range_end AS recur_enddate';

		$events = array();
		foreach($this->db->select($this->cal_table, $cols, $where, __LINE__, __FILE__, false, $group_by, 'calendar', 0, $join) as $row)
		{
			if (!$row['recur_type'])
			{
				$row['recur_type'] = MCAL_RECUR_NONE;
				unset($row['recur_enddate']);
			}
			$row['recur_exception'] = $row['alarm'] = array();
			$events[$row['cal_id']] = egw_db::strip_array_keys($row,'cal_');

			// if a uid was supplied, convert it for the further code to an id
			if (!is_array($ids) && !is_numeric($ids)) $ids = $row['cal_id'];
		}
		if (!$events) return false;

		foreach ($events as &$event)
		{
			if (!isset($event['uid']) || strlen($event['uid']) < $minimum_uid_length)
			{
				// event (without uid), not strong enough uid => create new uid
				$event['uid'] = common::generate_uid('calendar',$event['id']);
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
				foreach(($i=$this->db->select($this->dates_table, 'cal_id,cal_start', array(
					'cal_id' => $event['id'],
					'cal_start' => $event['start'],
					'recur_exception' => true,
				), __LINE__, __FILE__, false, '', 'calendar')) as $row)
				{
					$isException[$row['cal_id']] = true;
				}
				//_debug_array($i->sql.'-> found Rows:'.$i->_numOfRows);
				if ($isException[$event['id']])
				{
					$x = $this->db->select($this->cal_table, 'cal_id', array(
						'cal_uid' => $event['uid'],
						'cal_recurrence' => $event['start'],
						'cal_deleted' => NULL
					), __LINE__, __FILE__, false, '', 'calendar');
					//_debug_array(__METHOD__.__LINE__.$x->sql.'-> found Rows:'.$x->_numOfRows);
					if (empty($x->_numOfRows))
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
			$uid    = self::combine_user($row['cal_user_type'],$row['cal_user_id']);
			$status = self::combine_status($row['cal_status'],$row['cal_quantity'],$row['cal_role']);

			$events[$row['cal_id']]['participants'][$uid] = $status;
			$events[$row['cal_id']]['participant_types'][$row['cal_user_type']][$row['cal_user_id']] = $status;
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
	const MAX_CTAG_CACHE_TIME = 300;

	/**
	 * Get maximum modification time of events for given participants and optional owned by them
	 *
	 * This includes ALL recurences of an event series
	 *
	 * @param int|string|array $users one or mulitple calendar users
	 * @param booelan $owner_too=false if true return also events owned by given users
	 * @param boolean $master_only=false only check recurance master (egw_cal_user.recur_date=0)
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
			self::split_user($uid, $type, $id);
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
	 * Use as: foreach(get_cal_data() as $data) { $data = egw_db::strip_array_keys($data, 'cal_'); // do something with $data
	 *
	 * @param array $query filter, keys have to use 'cal_' prefix
	 * @param string|array $cols='cal_id,cal_reference,cal_etag,cal_modified,cal_user_modified' cols to query
	 * @return Iterator as egw_db::select
	 */
	function get_cal_data(array $query, $cols='cal_id,cal_reference,cal_etag,cal_modified,cal_user_modified', $include_user_modified=false)
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
			array_walk($cats,create_function('&$val,$key','$val = (int) $val;'));
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
	 * Searches / lists calendar entries, including repeating ones
	 *
	 * @param int $start startdate of the search/list (servertime)
	 * @param int $end enddate of the search/list (servertime)
	 * @param int|array $users user-id or array of user-id's, !$users means all entries regardless of users
	 * @param int|array $cat_id=0 mixed category-id or array of cat-id's (incl. all sub-categories), default 0 = all
	 * @param string $filter='all' string filter-name: all (not rejected), accepted, unknown, tentative, rejected or everything (incl. rejected, deleted)
	 * @param int|boolean $offset=False offset for a limited query or False (default)
	 * @param int $num_rows=0 number of rows to return if offset set, default 0 = use default in user prefs
	 * @param array $params=array()
	 * @param string|array $params['query'] string: pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 *      array: everything is directly used as $where
	 * @param string $params['order']='cal_start' column-names plus optional DESC|ASC separted by comma
	 * @param string $params['sql_filter'] sql to be and'ed into query (fully quoted)
	 * @param string|array $params['cols'] what to select, default "$this->repeats_table.*,$this->cal_table.*,cal_start,cal_end,cal_recur_date",
	 * 						if specified and not false an iterator for the rows is returned
	 * @param string $params['append'] SQL to append to the query before $order, eg. for a GROUP BY clause
	 * @param array $params['cfs'] custom fields to query, null = none, array() = all, or array with cfs names
	 * @param array $params['users'] raw parameter as passed to calendar_bo::search() no memberships resolved!
	 * @param boolean $params['master_only']=false, true only take into account participants/status from master (for AS)
	 * @param int $remove_rejected_by_user=null add join to remove entry, if given user has rejected it
	 * @return array of cal_ids, or false if error in the parameters
	 *
	 * ToDo: search custom-fields too
	 */
	function &search($start,$end,$users,$cat_id=0,$filter='all',$offset=False,$num_rows=0,array $params=array(),$remove_rejected_by_user=null)
	{
		error_log(__METHOD__.'('.($start ? date('Y-m-d H:i',$start) : '').','.($end ? date('Y-m-d H:i',$end) : '').','.array2string($users).','.array2string($cat_id).",'$filter',".array2string($offset).",$num_rows,".array2string($params).') '.function_backtrace());

		if (isset($params['cols']))
		{
			$cols = $params['cols'];
		}
		else
		{
			$all_cols = self::get_columns('calendar', $this->cal_table);
			$all_cols[0] = $this->db->to_varchar($this->cal_table.'.cal_id');
			$cols = "$this->repeats_table.recur_type,$this->repeats_table.recur_interval,$this->repeats_table.recur_data,range_end AS recur_enddate,".implode(',',$all_cols).",cal_start,cal_end,$this->user_table.cal_recur_date";
		}
		$where = array();
		if (is_array($params['query']))
		{
			$where = $params['query'];
		}
		elseif ($params['query'])
		{
			foreach(array('cal_title','cal_description','cal_location') as $col)
			{
				$to_or[] = $col.' '.$this->db->capabilities[egw_db::CAPABILITY_CASE_INSENSITIV_LIKE].' '.$this->db->quote('%'.$params['query'].'%');
			}
			$where[] = '('.implode(' OR ',$to_or).')';

			// Searching - restrict private to own or private grant
			if (!isset($params['private_grants']))
			{
				$params['private_grants'] = $GLOBALS['egw']->acl->get_ids_for_location($GLOBALS['egw_info']['user']['account_id'], EGW_ACL_PRIVATE, 'calendar');
				$params['private_grants'][] = $GLOBALS['egw_info']['user']['account_id'];	// db query does NOT return current user
			}
			$private_filter = '(cal_public=1 OR cal_public=0 AND '.$this->db->expression($this->cal_table, array('cal_owner' => $params['private_grants'])) . ')';
			$where[] = $private_filter;
		}
		if (!empty($params['sql_filter']) && is_string($params['sql_filter']))
		{
			$where[] = $params['sql_filter'];
		}
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
					$users_by_type[$user[0]][] = substr($user,1);
				}
			}
			$to_or = $user_or = array();
			$owner_or = null;
			$useUnionQuery = $this->db->capabilities['distinct_on_text'] && $this->db->capabilities['union'];
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
						foreach($ids as $user_id) if ($GLOBALS['egw']->accounts->get_type($user_id) === 'u') $user_ids[] = $user_id;
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
					if ($type == 'u' && ($filter == 'owner'))
					{
						$cal_table_def = $this->db->get_table_definitions('calendar',$this->cal_table);
						$to_or[] = $this->db->expression($cal_table_def,array('cal_owner' => $ids));
					}
				}
			}
			// this is only used, when we cannot use UNIONS
			if (!$useUnionQuery) $where[] = '('.implode(' OR ',$to_or).')';

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
					if ($params['enum_recuring'])	// regular UI
					{
						$where[] = "$this->user_table.cal_status NOT IN ('R','X','E')";
					}
					else	// CalDAV / eSync / iCal need to include 'E' = exceptions
					{
						$where[] = "$this->user_table.cal_status NOT IN ('R','X')";
					}
					break;
			}
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
		//$starttime = microtime(true);
		if ($useUnionQuery)
		{
			// allow apps to supply participants and/or icons
			if (!isset($params['cols'])) $cols .= ',NULL AS participants,NULL AS icons';

			// changed the original OR in the query into a union, to speed up the query execution under MySQL 5
			$select = array(
				'table' => $this->cal_table,
				'join'  => "JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id $rejected_by_user_join",
				'cols'  => $cols,
				'where' => $where,
				'app'   => 'calendar',
				'append'=> $params['append'].' '.$group_by,
			);
			if ($params['enum_recuring'])	// dates table join only needed to enum recuring events
			{
				$select['join'] = "JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id ".$select['join'];
			}
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
					$selects[$key]['cols'] = "DISTINCT $this->repeats_table.recur_type,range_end AS recur_enddate,$this->repeats_table.recur_interval,$this->repeats_table.recur_data,".$this->db->to_varchar($this->cal_table.'.cal_id').",cal_start,cal_end,$this->user_table.cal_recur_date";
					if (!$params['enum_recuring'])
					{
						$selects[$key]['cols'] = str_replace(array('cal_start','cal_end'),
							array('range_start AS cal_start','range_end AS cal_end'), $selects[$key]['cols']);
					}
				}
				if (!isset($params['cols'])) self::get_union_selects($selects,$start,$end,$users,$cat_id,$filter,$params['query'],$params['users']);

				$this->total = $this->db->union($selects,__LINE__,__FILE__)->NumRows();

				// restore original cols / selects
				$selects = $save_selects; unset($save_selects);
			}
			if (!isset($params['cols'])) self::get_union_selects($selects,$start,$end,$users,$cat_id,$filter,$params['query'],$params['users']);

			$rs = $this->db->union($selects,__LINE__,__FILE__,$params['order'],$offset,$num_rows);
		}
		else	// MsSQL oder MySQL 3.23
		{
			$where[] = "(recur_type IS NULL AND $this->user_table.cal_recur_date=0)";// OR $this->user_table.cal_recur_date=cal_start)";

			//_debug_array($where);
			if (is_numeric($offset))	// get the total too
			{
				// we only select cal_table.cal_id (and not cal_table.*) to be able to use DISTINCT (eg. MsSQL does not allow it for text-columns)
				$this->total = $this->db->select($this->cal_table,"DISTINCT ".$cols,//$this->repeats_table.*,$this->cal_table.cal_id,cal_start,cal_end,cal_recur_date",
					$where,__LINE__,__FILE__,false,'','calendar',0,
					// dates table join only needed to enum recuring events
					($params['enum_recuring'] ? "JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id " : '').
					"JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id $rejected_by_user_join LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id")->NumRows();
			}
			$rs = $this->db->select($this->cal_table,($this->db->capabilities['distinct_on_text'] ? 'DISTINCT ' : '').$cols,
				$where,__LINE__,__FILE__,$offset,$params['append'].' ORDER BY '.$params['order'],'calendar',$num_rows,
				"JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id $rejected_by_user_join LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id");
		}
		//error_log(__METHOD__."() useUnionQuery=$useUnionQuery --> query took ".(microtime(true)-$starttime));
		if (isset($params['cols']))
		{
			return $rs;	// if colums are specified we return the recordset / iterator
		}
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

			$events[$id] = egw_db::strip_array_keys($row,'cal_');
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
				),__LINE__,__FILE__,false,'ORDER BY cal_id,cal_user_type DESC,'.self::STATUS_SORT,'calendar',$num_rows,$join='',
				$this->db->get_table_definitions('calendar',$this->user_table)) as $row)	// DESC puts users before resources and contacts
			{
				$id = $row['cal_id'];
				if ($row['cal_recur_date']) $id .= '-'.$row['cal_recur_date'];

				// combine all participant data in uid and status values
				$uid = self::combine_user($row['cal_user_type'],$row['cal_user_id']);
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
			// query recurrance exceptions, if needed
			if (!$params['enum_recuring'])
			{
				foreach($this->db->select($this->dates_table, 'cal_id,cal_start', array(
					'cal_id' => $ids,
					'recur_exception' => true,
				), __LINE__, __FILE__, false, 'ORDER BY cal_id,cal_start', 'calendar') as $row)
				{
					$events[$row['cal_id']]['recur_exception'][] = $row['cal_start'];
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
		self::$integration_data = $GLOBALS['egw']->hooks->process(array(
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
		foreach(self::$integration_data as $app => $data)
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
	 * @param string $required_app='calendar'
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
			$cols =& egw_cache::getSession(__CLASS__,$table);

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
	 * @param int $change_since=0 time from which on the repetitions should be changed, default 0=all
	 * @param int &$etag etag=null etag to check or null, on return new etag
	 * @return boolean|int false on error, 0 if etag does not match, cal_id otherwise
	 */
	function save($event,&$set_recurrences,&$set_recurrences_start=0,$change_since=0,&$etag=null)
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
		$set_recurrences = !$cal_id && $event['recur_type'] != MCAL_RECUR_NONE;

		if ($event['recur_type'] != MCAL_RECUR_NONE &&
			!(int)$event['recur_interval'])
		{
			$event['recur_interval'] = 1;
		}

		// add colum prefix 'cal_' if there's not already a 'recur_' prefix
		foreach($event as $col => $val)
		{
			if ($col[0] != '#' && substr($col,0,6) != 'recur_' && substr($col,0,6) != 'range_' && $col != 'alarm' && $col != 'tz_id' && $col != 'caldav_name')
			{
				$event['cal_'.$col] = $val;
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
			$event[] = 'cal_etag=cal_etag+1';	// always update the etag, even if none given to check

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

			$this->db->insert($this->cal_table,$event,false,__LINE__,__FILE__,'calendar');
			if (!($cal_id = $this->db->get_last_insert_id($this->cal_table,'cal_id')))
			{
				return false;
			}
			$etag = 0;
		}
		$update = array();
		// event without uid or not strong enough uid
		if (!isset($event['cal_uid']) || strlen($event['cal_uid']) < $minimum_uid_length)
		{
			$update['cal_uid'] = $event['cal_uid'] = common::generate_uid('calendar',$cal_id);
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
				foreach($this->db->select($this->user_table, 'cal_user_type,cal_user_id', array(
					'cal_id' => $event['cal_reference'],
					'cal_recur_date' => 0,
					"cal_status != 'X'",	// deleted need to be replaced with exception marker too
				), __LINE__, __FILE__, 'calendar') as $row)
				{
					$master_participants[] = self::combine_user($row['cal_user_type'], $row['cal_user_id']);
				}
				foreach(array_diff(array_keys((array)$event['cal_participants']), $master_participants) as $uid)
				{
					$user_type = $user_id = null;
					self::split_user($uid, $user_type, $user_id);
					$this->db->insert($this->user_table, array(
						'cal_status' => 'E',
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
			foreach ($this->db->select($this->user_table,'cal_user_type,cal_user_id,cal_status,cal_quantity,cal_role', $where,
				__LINE__,__FILE__,false,'','calendar') as $row)
			{
				$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id']);
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
				if (is_numeric($id)) unset($alarm['id']);	// unset the temporary id to add the alarm

				if(!isset($alarm['offset']))
				{
					$alarm['offset'] = $event['cal_start'] - $alarm['time'];
				}
				elseif (!isset($alarm['time']))
				{
					$alarm['time'] = $event['cal_start'] - $alarm['offset'];
				}

				if ($alarm['time'] < time())
				{
					//pgoerzen: don't add an alarm in the past
					if ($event['recur_type'] == MCAL_RECUR_NONE) continue;
					$start = (int)time() + $alarm['offset'];
					$event['start'] = $event['cal_start'];
					$event['end'] = $event['cal_end'];
					$event['tzid'] = $event['cal_tzid'];
					$rrule = calendar_rrule::event2rrule($event, false);
					foreach ($rrule as $time)
					{
						if ($start < ($ts = egw_time::to($time,'server'))) break;
						$ts = 0;
					}
					if (!$ts) continue;
					$alarm['time'] = $ts - $alarm['offset'];
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
	 * moves an event to an other start- and end-time taken into account the evtl. recurrences of the event(!)
	 *
	 * @param int $cal_id
	 * @param int $start new starttime
	 * @param int $end new endtime
	 * @param int|boolean $change_since=0 false=new entry, > 0 time from which on the repetitions should be changed, default 0=all
	 * @param int $old_start=0 old starttime or (default) 0, to query it from the db
	 * @param int $old_end=0 old starttime or (default) 0
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
	 * combines user_type and user_id into a single string or integer (for users)
	 *
	 * @param string $user_type 1-char type: 'u' = user, ...
	 * @param string|int $user_id id
	 * @return string|int combined id
	 */
	static function combine_user($user_type,$user_id)
	{
		if (!$user_type || $user_type == 'u')
		{
			return (int) $user_id;
		}
		return $user_type.$user_id;
	}

	/**
	 * splits the combined user_type and user_id into a single values
	 *
	 * @param string|int $uid
	 * @param string &$user_type 1-char type: 'u' = user, ...
	 * @param string|int &$user_id id
	 */
	static function split_user($uid,&$user_type,&$user_id)
	{
		if (is_numeric($uid))
		{
			$user_type = 'u';
			$user_id = (int) $uid;
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
	 * @param string $status
	 * @param int $quantity=1
	 * @param string $role='REQ-PARTICIPANT'
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

		if (strlen($status) > 1 && preg_match('/^.([0-9]*)(.*)$/',$status,$matches))
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
	 * @param int|boolean $change_since=0, false=new event,
	 * 		0=all, > 0 time from which on the repetitions should be changed
	 * @param boolean $add_only=false
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
				$uid = self::combine_user($row['cal_user_type'],$row['cal_user_id']);
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
					$uid = self::combine_user($row['cal_user_type'],$row['cal_user_id']);
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
				self::split_user($uid,$type,$id);
				self::split_status($status,$quantity,$role);
				$set = array(
					'cal_status'	  => $status,
					'cal_quantity'	  => $quantity,
					'cal_role'        => $role,
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
	 * @param int $user_id
	 * @param int|char $status numeric status (defines) or 1-char code: 'R', 'U', 'T' or 'A'
	 * @param int $recur_date=0 date to change, or 0 = all since now
	 * @param string $role=null role to set if !is_null($role)
	 * @return int number of changed recurrences
	 */
	function set_status($cal_id,$user_type,$user_id,$status,$recur_date=0,$role=null)
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

		if (!$user_type) $user_type == 'u';

		$where = array(
			'cal_id'		=> $cal_id,
			'cal_user_type'	=> $user_type,
			'cal_user_id'   => $user_id,
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
	 * @param boolean $exception=null true or false to set recure_exception flag, null leave it unchanged (new are by default no exception)
	 * @param array $participants uid => status pairs
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
				self::split_user($uid,$type,$id);
				self::split_status($status,$quantity,$role);
				$this->db->insert($this->user_table,array(
					'cal_status'	=> $status,
					'cal_quantity'	=> $quantity,
					'cal_role'		=> $role
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
			// handle sync
			$this->db->update('egw_api_content_history',array(
				'sync_deleted' => time(),
			),array(
				'sync_appname' => 'calendar',
				'sync_contentid' => $row['cal_id'],	// sync_contentid is varchar(60)!
			), __LINE__, __FILE__);
			// handle links
			egw_link::unlink('', 'calendar', $row['cal_id']);
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
	 * @param boolean $update_cache=null true: re-read given $cal_id, false: delete given $cal_id
	 * @return array of (cal_id => array of) alarms with alarm-id as key
	 */
	function read_alarms($cal_id, $update_cache=null)
	{
		if (!isset(self::$alarm_cache) && is_array($cal_id))
		{
			self::$alarm_cache = array();
			if ($jobs = $this->async->read('cal:%'))
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
		if ($jobs = $this->async->read('cal:'.(int)$cal_id.':%'))
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
		return $alarms;
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
		list($id,$job) = each($jobs);
		$alarm         = $job['data'];	// text, enabled
		$alarm['id']   = $id;
		$alarm['time'] = $job['next'];

		//echo "<p>read_alarm('$id')="; print_r($alarm); echo "</p>\n";
		return $alarm;
	}

	/**
	 * saves a new or updated alarm
	 *
	 * @param int $cal_id Id of the calendar-entry
	 * @param array $alarm array with fields: text, owner, enabled, ..
	 * @param boolean $update_modified=true call update modified, default true
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
		//error_log(__METHOD__.__LINE__.' Save Alarm for CalID:'.$cal_id.'->'.array2string($alarm).'-->'.$id.'#'.function_backtrace());
		// allways store job with the alarm owner as job-owner to get eg. the correct from address
		if (!$this->async->set_timer($alarm['time'],$id,'calendar.calendar_boupdate.send_alarm',$alarm,$alarm['owner']))
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
			foreach($alarms as $id => $alarm)
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
	 * @param int $new_user=null
	 */
	function deleteaccount($old_user, $newuser=null)
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
	 * @param int $uid=null  participant uid; if == null return only the recur dates
	 * @param int $start=0  if != 0: startdate of the search/list (servertime)
	 * @param int $end=0  if != 0: enddate of the search/list (servertime)
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
		self::split_user($uid, $user_type, $user_id);
		$where = array(
			'cal_id'		=> $cal_id,
			'cal_user_type'	=> $user_type ? $user_type : 'u',
			'cal_user_id'   => $user_id,
		);
		if ($start != 0 && $end == 0) $where[] = '(cal_recur_date = 0 OR cal_recur_date >= ' . (int)$start . ')';
		if ($start == 0 && $end != 0) $where[] = '(cal_recur_date = 0 OR cal_recur_date <= ' . (int)$end . ')';
		if ($start != 0 && $end != 0)
		{
			$where[] = '(cal_recur_date = 0 OR (cal_recur_date >= ' . (int)$start .
						' AND cal_recur_date <= ' . (int)$end . '))';
		}
		foreach ($this->db->select($this->user_table,'cal_recur_date,cal_status,cal_quantity,cal_role',$where,
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
	 * @param int $recur_date=0 gives participants of this recurrence, default 0=all
	 *
	 * @return array participants
	 */
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
	}

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
	 * @param int $start=0  if != 0: startdate of the search/list (servertime)
	 * @param int $end=0  if != 0:	enddate of the search/list (servertime)
	 * @param string $filter='all'	string filter-name: all (not rejected),
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
					$locts = (int)egw_time::to($egw_rrule->current(),'server');
					if ($expand_all)
					{
						$remts = (int)egw_time::to($remote_rrule->current(),'server');
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
			$locts = (int)egw_time::to($day,'server');
			$tz_exception = ($filter == 'tz_rrule');
			//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
			//	'()[EVENT Server]: ' . $day->format('Ymd\THis') . " ($locts)");
			if ($expand_all)
			{
				$remote_day = $remote_rrule->current();
				$remts = (int)egw_time::to($remote_day,'server');
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
		static $recurrence_zero;
		static $cached_id;
		static $user;

		if (!isset($cached_id) || $cached_id != $cal_id)
		{
			// get default stati
			$recurrence_zero = array();
			$user = $GLOBALS['egw_info']['user']['account_id'];
			$where = array('cal_id' => $cal_id,
							'cal_recur_date' => 0);
			foreach ($this->db->select($this->user_table,'cal_user_id,cal_user_type,cal_status',$where,
				__LINE__,__FILE__,false,'','calendar') as $row)
			{
				switch ($row['cal_user_type'])
				{
					case 'u':	// account
					case 'c':	// contact
					case 'e':	// email address
						$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id']);
						$recurrence_zero[$uid] = $row['cal_status'];
				}
			}
			$cached_id = $cal_id;
		}

		//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
		//	"($cal_id, $recur_date, $filter)[DEFAULTS]: " .
		//	array2string($recurrence_zero));

		$participants = array();
		$where = array('cal_id' => $cal_id,
			'cal_recur_date' => $recur_date);
		foreach ($this->db->select($this->user_table,'cal_user_id,cal_user_type,cal_status',$where,
			__LINE__,__FILE__,false,'','calendar') as $row)
		{
			switch ($row['cal_user_type'])
			{
				case 'u':	// account
				case 'c':	// contact
				case 'e':	// email address
					$uid = self::combine_user($row['cal_user_type'], $row['cal_user_id']);
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
							continue;
						}
						break;
					case 'accepted':
						if ($status != 'A')
						{
							unset($participants[$uid]);
							continue;
						}
						break;
					case 'tentative':
						if ($status != 'T')
						{
							unset($participants[$uid]);
							continue;
						}
						break;
					case 'rejected':
						if ($status != 'R')
						{
							unset($participants[$uid]);
							continue;
						}
						break;
					case 'delegated':
						if ($status != 'D')
						{
							unset($participants[$uid]);
							continue;
						}
						break;
					case 'default':
						if ($status == 'R')
						{
							unset($participants[$uid]);
							continue;
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
			$timezone = egw_time::$server_timezone;
		}
		else
		{
			if (!isset(self::$tz_cache[$event['tzid']]))
			{
				self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
			}
			$timezone = self::$tz_cache[$event['tzid']];
		}
		$start = new egw_time($event['start'],egw_time::$server_timezone);
		$start->setTimezone($timezone);
		$end = new egw_time($event['end'],egw_time::$server_timezone);
		$end->setTimezone($timezone);
		//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
		//	'(): ' . $start . '-' . $end);
		$start = egw_time::to($start,'array');
		$end = egw_time::to($end,'array');


		return !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;
	}

	/**
	 * Moves a datetime to the beginning of the day within timezone
	 *
	 * @param egw_time	&time	the datetime entry
	 * @param string tz_id		timezone
	 *
	 * @return DateTime
	 */
	function &startOfDay(egw_time $time, $tz_id=null)
	{
		if (empty($tz_id))
		{
			$timezone = egw_time::$server_timezone;
		}
		else
		{
			if (!isset(self::$tz_cache[$tz_id]))
			{
				self::$tz_cache[$tz_id] = calendar_timezones::DateTimeZone($tz_id);
			}
			$timezone = self::$tz_cache[$tz_id];
		}
		return new egw_time($time->format('Y-m-d 00:00:00'), $timezone);
	}

	/**
	 * Updates the modification timestamp to force an etag, ctag and sync-token change
	 *
	 * @param int $id event id
	 * @param int|boolean $update_master=false id of series master or true, to update series master too
	 * @param int $time=null new timestamp, default current (server-)time
	 * @param int $modifier=null uid of the modifier, default current user
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
