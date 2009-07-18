<?php
/**
 * eGroupWare - Calendar's storage-object
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005-8 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * some necessary defines used by the calendar
 */
if(extension_loaded('mcal') == False)
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

/**
 * Class to store all calendar data (storage object)
 *
 * Tables used by socal:
 *	- egw_cal: general calendar data: cal_id, title, describtion, locations, ...
 *	- egw_cal_dates: start- and enddates (multiple entry per cal_id for recuring events!)
 *	- egw_cal_user: participant info including status (multiple entries per cal_id AND startdate for recuring events)
 * 	- egw_cal_repeats: recur-data: type, optional enddate, etc.
 *  - egw_cal_extra: custom fields (multiple entries per cal_id possible)
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 */
class calendar_so
{
	/**
	 * name of the main calendar table and prefix for all other calendar tables
	 */
	var $cal_table = 'egw_cal';
	var $extra_table,$repeats_table,$user_table,$dates_table,$all_tables;

	/**
	 * internal copy of the global db-object
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
	 * @param int/array/string $ids id or array of id's of the entries to read, or string with a single uid
	 * @param int $recur_date=0 if set read the next recurrance at or after the timestamp, default 0 = read the initital one
	 * @return array/boolean array with id => data pairs or false if entry not found
	 */
	function read($ids,$recur_date=0)
	{
		//echo "<p>socal::read(".print_r($ids,true).",$recur_date)<br />\n".function_backtrace()."<p>\n";

		$table_def = $this->db->get_table_definitions('calendar',$this->cal_table);
		$group_by_cols = $this->cal_table.'.'.implode(','.$this->cal_table.'.',array_keys($table_def['fd']));
		$table_def = $this->db->get_table_definitions('calendar',$this->repeats_table);
		$group_by_cols .= ','.$this->repeats_table.'.'.implode(','.$this->repeats_table.'.',array_keys($table_def['fd']));

		$where = array();
		if (is_array($ids))
		{
			array_walk($ids,create_function('&$val,$key','$val = (int) $val;'));

			$where[] = $this->cal_table.'.cal_id IN ('.implode(',',$ids).')';
		}
		elseif (is_numeric($ids))
		{
			$where[] = $this->cal_table.'.cal_id = '.(int) $ids;
		}
		else
		{
			$where['cal_uid'] = $ids;
		}
		if ((int) $recur_date)
		{
			$where[] = 'cal_start >= '.(int)$recur_date;
		}
		$events = array();
		foreach($this->db->select($this->cal_table,"$this->repeats_table.*,$this->cal_table.*,MIN(cal_start) AS cal_start,MIN(cal_end) AS cal_end",
			$where,__LINE__,__FILE__,false,'GROUP BY '.$group_by_cols,'calendar',0,
			",$this->dates_table LEFT JOIN $this->repeats_table ON $this->dates_table.cal_id=$this->repeats_table.cal_id".
			" WHERE $this->cal_table.cal_id=$this->dates_table.cal_id") as $row)
		{
			$row['recur_exception'] = $row['recur_exception'] ? explode(',',$row['recur_exception']) : array();
			if (!$row['recur_type']) $row['recur_type'] = MCAL_RECUR_NONE;
			$row['alarm'] = array();
			$events[$row['cal_id']] = egw_db::strip_array_keys($row,'cal_');

			// if a uid was supplied, convert it for the further code to an id
			if (!is_array($ids) && !is_numeric($ids)) $ids = $row['cal_id'];
		}
		if (!$events) return false;

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
				$recur_date = $events[$ids]['recur_date'] = $events[$ids]['start'];
			}
		}

		// participants, if a recur_date give, we read that recurance, else the one users from the default entry with recur_date=0
		foreach($this->db->select($this->user_table,'*',array(
			'cal_id'      => $ids,
			'cal_recur_date' => $recur_date,
		),__LINE__,__FILE__,false,'ORDER BY cal_user_type DESC,'.self::STATUS_SORT,'calendar') as $row)	// DESC puts users before resources and contacts
		{
			// if the type is not an ordinary user (eg. contact or resource)...
			if ($row['cal_user_type'] && $row['cal_user_type'] != 'u')
			{
				// prefix the id with the type
				$user_id = $row['cal_user_type'].$row['cal_user_id'];
				// and append quantity
				$row['cal_status'] .= $row['cal_quantity'] == 1 ? '' : $row['cal_quantity'];
			}
			else
			{
				$user_id = (int) $row['cal_user_id'];
			}
			$events[$row['cal_id']]['participants'][$user_id] = $row['cal_status'];
			$events[$row['cal_id']]['participant_types'][$row['cal_user_type']][$row['cal_user_id']] = $row['cal_status'];
		}

		// custom fields
		foreach($this->db->select($this->extra_table,'*',array('cal_id'=>$ids),__LINE__,__FILE__,false,'','calendar') as $row)
		{
			$events[$row['cal_id']]['#'.$row['cal_extra_name']] = $row['cal_extra_value'];
		}

		// alarms, atm. we read all alarms in the system, as this can be done in a single query
		foreach((array)$this->async->read('cal'.(is_array($ids) ? '' : ':'.(int)$ids).':%') as $id => $job)
		{
			list(,$cal_id) = explode(':',$id);
			if (!isset($events[$cal_id])) continue;	// not needed

			$alarm         = $job['data'];	// text, enabled
			$alarm['id']   = $id;
			$alarm['time'] = $job['next'];

			$events[$cal_id]['alarm'][$id] = $alarm;
		}
		//echo "<p>socal::read(".print_r($ids,true).")=<pre>".print_r($events,true)."</pre>\n";
		return $events;
	}

	/**
	 * generate SQL to filter after a given category (evtl. incl. subcategories)
	 *
	 * @param array/int $cat_id cat-id or array of cat-ids, or !$cat_id for none
	 * @return string SQL to include in the query
	 */
	function cat_filter($cat_id)
	{
		$sql = '';
		if ($cat_id)
		{
			if (!is_array($cat_id) && !@$GLOBALS['egw_info']['user']['preferences']['common']['cats_no_subs'])
			{
				$cats = $GLOBALS['egw']->categories->return_all_children($cat_id);
			}
			else
			{
				$cats = is_array($cat_id) ? $cat_id : array($cat_id);
			}
			array_walk($cats,create_function('&$val,$key','$val = (int) $val;'));

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
	 * @param int/array $users user-id or array of user-id's, !$users means all entries regardless of users
	 * @param int $cat_id=0 mixed category-id or array of cat-id's, default 0 = all
	 *		Please note: only a single cat-id, will include all sub-cats (if the common-pref 'cats_no_subs' is False)
	 * @param string $filter='' string filter-name, atm. all or hideprivate
	 * @param string $query='' pattern so search for, if unset or empty all matching entries are returned (no search)
	 *		Please Note: a search never returns repeating events more then once AND does not honor start+end date !!!
	 * @param int/bool $offset=False offset for a limited query or False (default)
	 * @param int $num_rows=0 number of rows to return if offset set, default 0 = use default in user prefs
	 * @param string $order='cal_start' column-names plus optional DESC|ASC separted by comma
	 * @param boolean $show_rejected=true should the search return rejected invitations
	 * @return array of cal_ids, or false if error in the parameters
	 *
	 * ToDo: search custom-fields too
	 */
	function &search($start,$end,$users,$cat_id=0,$filter='',$query='',$offset=False,$num_rows=0,$order = 'cal_start',$show_rejected=true)
	{
		//echo '<p>socal::search('.($start ? date('Y-m-d H:i',$start) : '').','.($end ? date('Y-m-d H:i',$end) : '').','.print_r($users,true).','.print_r($cat_id,true).",'$filter',".print_r($query,true).",$offset,$num_rows)</p>\n";

		$where = array();
		if (is_array($query))
		{
			$where = $query;
		}
		elseif ($query)
		{
			foreach(array('cal_title','cal_description','cal_location') as $col)
			{
				$to_or[] = $col . ' LIKE ' . $this->db->quote('%'.$query.'%');
			}
			$where[] = '('.implode(' OR ',$to_or).')';
		}
		if ($users)
		{
			$users_by_type = array();
			foreach(is_array($users) ? $users : array($users) as $user)
			{
				if (is_numeric($user))
				{
					$users_by_type['u'][] = (int) $user;
				}
				elseif (is_numeric(substr($user,1)))
				{
					$users_by_type[$user{0}][] = (int) substr($user,1);
				}
			}
			$to_or = array();
			$table_def = $this->db->get_table_definitions('calendar',$this->user_table);
			foreach($users_by_type as $type => $ids)
			{
				$to_or[] = $this->db->expression($table_def,array(
					'cal_user_type' => $type,
					'cal_user_id'   => $ids,
				));
			}
			$where[] = '('.implode(' OR ',$to_or).')';

			if (!$show_rejected) $where[] = "cal_status != 'R'";
		}
		if ($cat_id)
		{
			$where[] = $this->cat_filter($cat_id);
		}
		if ($start) $where[] = (int)$start.' < cal_end';
		if ($end)   $where[] = 'cal_start < '.(int)$end;

		if (!preg_match('/^[a-z_ ,]+$/i',$order)) $order = 'cal_start';		// gard against SQL injunktion

		if ($this->db->capabilities['distinct_on_text'] && $this->db->capabilities['union'])
		{
			// changed the original OR in the query into a union, to speed up the query execution under MySQL 5
			$select = array(
				'table' => $this->cal_table,
				'join'  => "JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id",
				'cols'  => "$this->repeats_table.*,$this->cal_table.*,cal_start,cal_end,cal_recur_date",
				'where' => $where,
				'app'   => 'calendar',
			);
			$selects = array($select,$select);
			$selects[0]['where'][] = 'recur_type IS NULL AND cal_recur_date=0';
			$selects[1]['where'][] = 'cal_recur_date=cal_start';

			if (is_numeric($offset))	// get the total too
			{
				// we only select cal_table.cal_id (and not cal_table.*) to be able to use DISTINCT (eg. MsSQL does not allow it for text-columns)
				$selects[0]['cols'] = $selects[1]['cols'] = "DISTINCT $this->repeats_table.*,$this->cal_table.cal_id,cal_start,cal_end,cal_recur_date";

				$this->total = $this->db->union($selects,__LINE__,__FILE__)->NumRows();

				$selects[0]['cols'] = $selects[1]['cols'] = $select['cols'];	// restore the original cols
			}
			$rs = $this->db->union($selects,__LINE__,__FILE__,$order,$offset,$num_rows);
		}
		else	// MsSQL oder MySQL 3.23
		{
			$where[] = '(recur_type IS NULL AND cal_recur_date=0 OR cal_recur_date=cal_start)';

			//_debug_array($where);
			if (is_numeric($offset))	// get the total too
			{
				// we only select cal_table.cal_id (and not cal_table.*) to be able to use DISTINCT (eg. MsSQL does not allow it for text-columns)
				$this->total = $this->db->select($this->cal_table,"DISTINCT $this->repeats_table.*,$this->cal_table.cal_id,cal_start,cal_end,cal_recur_date",
					$where,__LINE__,__FILE__,false,'','calendar',0,
					"JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id")->NumRows();
			}
			$rs = $this->db->select($this->cal_table,($this->db->capabilities['distinct_on_text'] ? 'DISTINCT ' : '').
				"$this->repeats_table.*,$this->cal_table.*,cal_start,cal_end,cal_recur_date",
				$where,__LINE__,__FILE__,$offset,'ORDER BY '.$order,'calendar',$num_rows,
				"JOIN $this->dates_table ON $this->cal_table.cal_id=$this->dates_table.cal_id JOIN $this->user_table ON $this->cal_table.cal_id=$this->user_table.cal_id LEFT JOIN $this->repeats_table ON $this->cal_table.cal_id=$this->repeats_table.cal_id");
		}
		$events = $ids = $recur_dates = $recur_ids = array();
		foreach($rs as $row)
		{
			$ids[] = $id = $row['cal_id'];
			if ($row['cal_recur_date'])
			{
				$id .= '-'.$row['cal_recur_date'];
				$recur_dates[] = $row['cal_recur_date'];
			}
			$row['alarm'] = array();
			$row['recur_exception'] = $row['recur_exception'] ? explode(',',$row['recur_exception']) : array();

			$events[$id] = egw_db::strip_array_keys($row,'cal_');
		}
		if (count($events))
		{
			// now ready all users with the given cal_id AND (cal_recur_date=0 or the fitting recur-date)
			// This will always read the first entry of each recuring event too, we eliminate it later
			$recur_dates[] = 0;
			foreach($this->db->select($this->user_table,'*',array(
				'cal_id' => array_unique($ids),
				'cal_recur_date' => $recur_dates,
			),__LINE__,__FILE__,false,'ORDER BY cal_id,cal_user_type DESC,'.self::STATUS_SORT,'calendar') as $row)	// DESC puts users before resources and contacts
			{
				$id = $row['cal_id'];
				if ($row['cal_recur_date']) $id .= '-'.$row['cal_recur_date'];

				if (!isset($events[$id])) continue;		// not needed first entry of recuring event

				$events[$id]['participants'][$this->combine_user($row['cal_user_type'],$row['cal_user_id'])] = $row['cal_status'];
			}
/*			custom fields are not shown in the regular views, so we can ignore them here for the moment
			foreach($this->db->select($this->extra_table,'*',array('cal_id'=>$ids),__LINE__,__FILE__,false,'','calendar') as $row)
			{
				$set_ids = array($row['cal_id']);
				if (isset($recur_ids[$row['cal_id']])) $set_ids += $recur_ids[$row['cal_id']];

				foreach($set_ids as $id)
				{
					if (isset($events[$cal_id]))
					{
						$events[$id]['#'.$row['cal_extra_name']] = $row['cal_extra_value'];
					}
				}
			}
*/
			// alarms, atm. we read all alarms in the system, as this can be done in a single query
			foreach((array)$this->async->read('cal'.(is_array($ids) ? '' : ':'.(int)$ids).':%') as $id => $job)
			{
				list(,$cal_id) = explode(':',$id);

				$alarm         = $job['data'];	// text, enabled
				$alarm['id']   = $id;
				$alarm['time'] = $job['next'];

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
		//echo "<p>socal::search\n"; _debug_array($events);
		return $events;
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
	 * @param int $change_since=0 time from which on the repetitions should be changed, default 0=all
	 * @param int &$etag etag=null etag to check or null, on return new etag
	 * @return boolean/int false on error, 0 if etag does not match, cal_id otherwise
	 */
	function save($event,&$set_recurrences,$change_since=0,&$etag=null)
	{
		//echo "<p>socal::save(,$change_since) event="; _debug_array($event);
		//error_log(__METHOD__."(".str_replace(array("\n",'    '),'',print_r($event,true)).",$set_recurrences,$change_since,$etag)");

		$cal_id = (int) $event['id'];
		unset($event['id']);
		$set_recurrences = !$cal_id && $event['recur_type'] != MCAL_RECUR_NONE;

		// add colum prefix 'cal_' if there's not already a 'recur_' prefix
		foreach($event as $col => $val)
		{
			if ($col{0} != '#' && substr($col,0,6) != 'recur_' && $col != 'alarm')
			{
				$event['cal_'.$col] = $val;
				unset($event[$col]);
			}
		}
		if (is_array($event['cal_category'])) $event['cal_category'] = implode(',',$event['cal_category']);

		if ($cal_id)
		{
			$where = array('cal_id' => $cal_id);
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
			if (!$event['cal_owner']) $event['cal_owner'] = $GLOBALS['egw_info']['user']['account_id'];

			if (!$event['cal_id'] && !isset($event['cal_uid'])) $event['cal_uid'] = '';	// uid is NOT NULL!

			$this->db->insert($this->cal_table,$event,false,__LINE__,__FILE__,'calendar');
			if (!($cal_id = $this->db->get_last_insert_id($this->cal_table,'cal_id')))
			{
				return false;
			}
			// new event (without uid), not strong enough uid or new created referencing event => create new uid
			if (strlen($event['cal_uid']) < 20 || is_numeric($event['cal_uid']) ||
				$event['cal_reference'] && strpos($event['cal_uid'],'cal-'.$event['calreference'].'-') !== false)
			{
				$event['cal_uid'] = $GLOBALS['egw']->common->generate_uid('calendar',$cal_id);
				$this->db->update($this->cal_table,array('cal_uid' => $event['cal_uid']),array('cal_id' => $cal_id),__LINE__,__FILE__,'calendar');
			}
			$etag = 0;
			// new events need to have at least one participant, default to the owner
			if (!isset($event['cal_participants']))
			{
				$event['cal_participants'] = array($event['cal_owner'] => 'A');
			}
		}
		// write information about recuring event, if recur_type is present in the array
		if (isset($event['recur_type']))
		{
			if (isset($event['recur_exception']) && is_array($event['recur_exception']) && count($event['recur_exception']))
			{
				// delete execeptions from the user and dates table, it could be the first time
				$this->db->delete($this->user_table,array('cal_id' => $cal_id,'cal_recur_date' => $event['recur_exception']),__LINE__,__FILE__,'calendar');
				$this->db->delete($this->dates_table,array('cal_id' => $cal_id,'cal_start' => $event['recur_exception']),__LINE__,__FILE__,'calendar');

				$event['recur_exception'] = implode(',',$event['recur_exception']);
			}
			else
			{
				$event['recur_exception'] = null;
			}
			if (!$set_recurrences)
			{
				// check if the recure-information changed
				$old_recur = $this->db->select($this->repeats_table,'*',array('cal_id' => $cal_id),__LINE__,__FILE__,false,'','calendar')->fetch();
				$old_exceptions = $old_recur['recur_exception'] ? explode(',',$old_recur['recur_exception']) : array();
				$exceptions = $event['recur_exception'] ? explode(',',$event['recur_exception']) : array();
				$set_recurrences = $event['recur_type'] != $old_recur['recur_type'] || $event['recur_data'] != $old_recur['recur_data'] ||
					$event['recur_interval'] != $old_recur['recur_interval'] || $event['recur_enddate'] != $old_recur['recur_enddate'] ||
					count(array_diff($old_exceptions,$exceptions));	// exception deleted or added
			}
			unset($event[0]);	// unset the 'etag=etag+1', as it's not in the repeats table
			if($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$this->db->insert($this->repeats_table,$event,array('cal_id' => $cal_id),__LINE__,__FILE__,'calendar');
			}
			else
			{
				$this->db->delete($this->repeats_table,array('cal_id' => $cal_id),__LINE__,__FILE__,'calendar');
			}
			if ($set_recurrences)
			{
				// delete all, but the lowest dates record
				$min = (int) $this->db->select($this->dates_table,'MIN(cal_start)',array('cal_id'=>$cal_id),__LINE__,__FILE__,false,'','calendar')->fetchSingle();

				$this->db->delete($this->dates_table,array(
					'cal_id' => $cal_id,
					'cal_start > '.(int)$min,
				),__LINE__,__FILE__,'calendar');
				// delete all user-records, with recur-date != 0
				$this->db->delete($this->user_table,array(
					'cal_id' => $cal_id,
					'cal_recur_date != 0',
				),__LINE__,__FILE__,'calendar');
			}
		}
		// update start- and endtime if present in the event-array, evtl. we need to move all recurrences
		if (isset($event['cal_start']) && isset($event['cal_end']))
		{
			$this->move($cal_id,$event['cal_start'],$event['cal_end'],!$cal_id ? false : $change_since);
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
				if (strlen($value))
				{
					$this->db->insert($this->extra_table,array(
						'cal_extra_value'	=> $value,
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
		// updating or saving the alarms, new alarms have a temporary numeric id!
		// ToDo: recuring events !!!
		if (is_array($event['alarm']))
		{
			foreach ($event['alarm'] as $id => $alarm)
			{
				if (is_numeric($id)) unset($alarm['id']);	// unset the temporary id, to add the alarm

				if(!isset($alarm['offset']))
				{
					$alarm['offset'] = $event['cal_start'] - $alarm['time'];
				}
				elseif (!isset($alarm['time']))
				{
					$alarm['time'] = $event['cal_start'] - $alarm['offset'];
				}

				//pgoerzen: don't add an alarm if it is before the current date.
				if ($event['recur_type'] && ($tmp_event = $this->read($eventID, time() + $alarm['offset'])))
				{
					$alarm['time'] = $tmp_event['cal_start'] - $alarm['offset'];
				}

				$this->save_alarm($cal_id,$alarm);
			}
		}
		if (is_null($etag))
		{
			$etag = $this->db->select($this->cal_table,'cal_etag',array('cal_id' => $cal_id),__LINE__,__FILE__,false,'','calendar')->fetchSingle();
		}
		return $cal_id;
	}

	/**
	 * moves an event to an other start- and end-time taken into account the evtl. recurrences of the event(!)
	 *
	 * @param int $cal_id
	 * @param int $start new starttime
	 * @param int $end new endtime
	 * @param int/boolean $change_since=0 false=new entry, > 0 time from which on the repetitions should be changed, default 0=all
	 * @param int $old_start=0 old starttime or (default) 0, to query it from the db
	 * @param int $old_end=0 old starttime or (default) 0
	 * @return int/boolean number of moved recurrences or false on error
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
	function combine_user($user_type,$user_id)
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
	function split_user($uid,&$user_type,&$user_id)
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
	 * updates the participants of an event, taken into account the evtl. recurrences of the event(!)
	 *
	 * @param int $cal_id
	 * @param array $participants id => status pairs
	 * @param int/boolean $change_since=0 false=new entry, > 0 time from which on the repetitions should be changed, default 0=all
	 * @param int $recur_date=0 time of which repetitions should be updated, default 0=all
	 * @return int/boolean number of updated recurrences or false on error
	 */
	function participants($cal_id,$participants,$change_since=0)
	{
		//echo "<p>socal::participants($cal_id,".print_r($participants,true).",$change_since)</p>\n";

		// remove group-invitations, they are NOT stored in the db
		foreach($participants as $uid => $status)
		{
			if ($status == 'G')
			{
				unset($participants[$uid]);
			}
		}
		$where = array('cal_id' => $cal_id);

		if ((int) $change_since)
		{
			$where[] = '(cal_recur_date=0 OR cal_recur_date >= '.(int)$change_since.')';
		}
		if ($change_since !== false)	// existing entries only
		{
			// delete not longer set participants
			$deleted = array();
			foreach($this->db->select($this->user_table,'DISTINCT cal_user_type,cal_user_id,cal_quantity',$where,
				__LINE__,__FILE__,false,'','calendar') as $row)
			{
				$uid = $this->combine_user($row['cal_user_type'],$row['cal_user_id']);
				if (!isset($participants[$uid]))	// delete group-invitations
				{
					$deleted[$row['cal_user_type']][] = $row['cal_user_id'];
				}
				elseif($row['cal_quantity'] == (substr($participants[$uid],1) ? substr($participants[$uid],1) : 1))
				{
					unset($participants[$uid]);	// we dont touch them
				}
			}

			if (count($deleted))
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
				$this->db->delete($this->user_table,$where + array('('.implode(' OR ',$to_or).')'),__LINE__,__FILE__,'calendar');
			}
		}
		if (count($participants))	// these are NEW participants now
		{
			// find all recurrences, as they all need the new parts to be added
			$recurrences = array();
			if ($change_since !== false)	// existing entries only
			{
				foreach($this->db->select($this->user_table,'DISTINCT cal_recur_date',$where,__LINE__,__FILE__,false,'','calendar') as $row)
				{
					$recurrences[] = $row['cal_recur_date'];
				}
			}
			if (!count($recurrences)) $recurrences[] = 0;	// insert the default one

			foreach($participants as $uid => $status)
			{
				$this->split_user($uid,$type,$id);
				foreach($recurrences as $recur_date)
				{
					$this->db->insert($this->user_table,array(
						'cal_status'	  => $status !== true ? $status{0} : 'U',
						'cal_quantity'	  => substr($status,1) ? substr($status,1) : 1,
					),array(
						'cal_id'	      => $cal_id,
						'cal_recur_date'  => $recur_date,
						'cal_user_type'   => $type,
						'cal_user_id' 	  => $id,
					),__LINE__,__FILE__,'calendar');
				}
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
	 * @param int/char $status numeric status (defines) or 1-char code: 'R', 'U', 'T' or 'A'
	 * @param int $recur_date=0 date to change, or 0 = all since now
	 * @return int number of changed recurrences
	 */
	function set_status($cal_id,$user_type,$user_id,$status,$recur_date=0)
	{
		static $status_code_short = array(
			REJECTED 	=> 'R',
			NO_RESPONSE	=> 'U',
			TENTATIVE	=> 'T',
			ACCEPTED	=> 'A'
		);
		if (!(int)$cal_id || !(int)$user_id && $user_type != 'e')
		{
			return false;
		}

		if (is_numeric($status)) $status = $status_code_short[$status];

		$where = array(
			'cal_id'		=> $cal_id,
			'cal_user_type'	=> $user_type ? $user_type : 'u',
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
		}
		else
		{
			$this->db->insert($this->user_table,array(
				'cal_status'     => $status,
			),$where,__LINE__,__FILE__,'calendar');
		}
		$ret = $this->db->affected_rows();
		error_log(__METHOD__."($cal_id,$user_type,$user_id,$status,$recur_date) = $ret");
		return $this->db->affected_rows();
	}

	/**
	 * creates or update a recurrence in the dates and users table
	 *
	 * @param int $cal_id
	 * @param int $start
	 * @param int $end
	 * @param array $participants uid => status pairs
	 */
	function recurrence($cal_id,$start,$end,$participants)
	{
		//echo "<p>socal::recurrence($cal_id,$start,$end,".print_r($participants,true).")</p>\n";
		$this->db->insert($this->dates_table,array(
			'cal_end' => $end,
		),array(
			'cal_id' => $cal_id,
			'cal_start'  => $start,
		),__LINE__,__FILE__,'calendar');

		foreach($participants as $uid => $status)
		{
			if ($status == 'G') continue;	// dont save group-invitations

			$this->split_user($uid,$type,$id);
			$this->db->insert($this->user_table,array(
				'cal_status'	 => $status !== true ? $status{0} : 'U',
				'cal_quantity'	=> substr($status,1) ? substr($status,1) : 1,
			),array(
				'cal_id'		 => $cal_id,
				'cal_recur_date' => $start,
				'cal_user_type'  => $type,
				'cal_user_id' 	 => $id,
			),__LINE__,__FILE__,'calendar');
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
		foreach($this->db->select($this->repeats_table,"$this->repeats_table.cal_id,MAX(cal_start) AS cal_start",array(
			"$this->repeats_table.cal_id = $this->dates_table.cal_id",
			'(recur_enddate = 0 OR recur_enddate IS NULL OR recur_enddate > '.(int)$time.')',
		),__LINE__,__FILE__,false,"GROUP BY $this->repeats_table.cal_id",'calendar',0,','.$this->dates_table) as $row)
		{
			$ids[$row['cal_id']] = $row['cal_start'];
		}
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

		foreach($this->all_tables as $table)
		{
			$this->db->delete($table,array('cal_id'=>$cal_id),__LINE__,__FILE__,'calendar');
		}
	}

	/**
	 * read the alarms of a calendar-event specified by $cal_id
	 *
	 * alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
	 *
	 * @param int $cal_id
	 * @return array of alarms with alarm-id as key
	 */
	function read_alarms($cal_id)
	{
		$alarms = array();

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
	 * @return string id of the alarm
	 */
	function save_alarm($cal_id,$alarm)
	{
		//echo "<p>save_alarm(cal_id=$cal_id, alarm="; print_r($alarm); echo ")</p>\n";
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

		if (!$this->async->set_timer($alarm['time'],$id,'calendar.calendar_boupdate.send_alarm',$alarm))
		{
			return False;
		}
		return $id;
	}

	/**
	 * delete all alarms of a calendar-entry
	 *
	 * @param int $cal_id Id of the calendar-entry
	 * @return int number of alarms deleted
	 */
	function delete_alarms($cal_id)
	{
		$alarms = $this->read_alarms($cal_id);

		foreach($alarms as $id => $alarm)
		{
			$this->async->cancel_timer($id);
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
		return $this->async->cancel_timer($id);
	}

	/**
	 * Delete account hook
	 *
	 * @param array|int $old_user integer old user or array with keys 'account_id' and 'new_owner' as the deleteaccount hook uses it
	 * @param int $new_user=null
	 */
	function deleteaccount($data)
	{
		if (is_array($old_user))
		{
			$new_user = $old_user['new_owner'];
			$old_user = $old_user['account_id'];
		}
		if (!(int)$new_user)
		{
			$this->split_user($old_user,$user_type,$user_id);

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
				"cal_id IN (SELECT cal_id FROM $this->user_table other WHERE other.cal_id=cal_id AND other.cal_user_id=".(int)$new_user." AND cal_user_type='u')",
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
}
