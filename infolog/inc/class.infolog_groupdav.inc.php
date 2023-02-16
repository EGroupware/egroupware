<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access: InfoLog handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage caldav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * EGroupware: CalDAV access: infolog handler
 *
 * Permanent error_log() calls should use $this->caldav->log($str) instead, to be send to PHP error_log()
 * and our request-log (prefixed with "### " after request and response, like exceptions).
 */
class infolog_groupdav extends Api\CalDAV\Handler
{
	/**
	 * bo class of the application
	 *
	 * @var infolog_bo
	 */
	var $bo;

	/**
	 * vCalendar Instance for parsing
	 *
	 * @var array
	 */
	var $vCalendar;

	var $filter_prop2infolog = array(
		'SUMMARY'	=> 'info_subject',
		'UID'		=> 'info_uid',
		'DTSTART'	=> 'info_startdate',
		'DUE'		=> 'info_enddate',
		'DESCRIPTION'	=> 'info_des',
		'STATUS'	=> 'info_status',
		'PRIORITY'	=> 'info_priority',
		'LOCATION'	=> 'info_location',
		'COMPLETED'	=> 'info_datecompleted',
		'CREATED'   => 'info_created',
	);

	/**
	 * Are we using info_id, info_uid or caldav_name for the path/url
	 *
	 * Get's set in constructor to 'caldav_name' and self::$path_extension = ''!
	 */
	static $path_attr = 'info_id';

	/**
	 * Contains IDs for multiget REPORT to be able to report missing ones
	 *
	 * @var string[]
	 */
	var $requested_multiget_ids;

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		parent::__construct($app, $caldav);

		$this->bo = new infolog_bo();
		$this->vCalendar = new Horde_Icalendar;

		// since 1.9.002 we allow clients to specify the URL when creating a new event, as specified by CalDAV
		if (version_compare($GLOBALS['egw_info']['apps']['calendar']['version'], '1.9.002', '>='))
		{
			self::$path_attr = 'caldav_name';
			self::$path_extension = '';
		}
	}

	/**
	 * Create the path for an event
	 *
	 * @param array|int $info
	 * @return string
	 */
	function get_path($info)
	{
		if (is_numeric($info) && self::$path_attr == 'info_id')
		{
			$name = $info;
		}
		else
		{
			if (!is_array($info)) $info = $this->bo->read($info);
			$name = $info[self::$path_attr];
		}
		return $name.self::$path_extension;
	}

	/**
	 * Get filter-array for infolog_bo::search used by getctag and propfind
	 *
	 * @param string $path
	 * @param int $user account_id
	 * @return array
	 */
	private function get_infolog_filter($path, $user)
	{
		$infolog_types = $GLOBALS['egw_info']['user']['preferences']['groupdav']['infolog-types'];

		// 'None' selected for types, make sure filter gives no results
		if($infolog_types == '0')
		{
			return array('info_type' => '**NONE**');
		}
		if (!$infolog_types)
		{
			$infolog_types = 'task';
		}
		if ($path == '/infolog/')
		{
			$task_filter= 'own';
		}
		else
		{
			if ($user == $GLOBALS['egw_info']['user']['account_id'])
			{
				$task_filter = 'own';
			}
			else
			{
				$task_filter = 'user' . $user;
			}
		}

		$ret = array(
			'filter'	=> $task_filter,
			'info_type' => explode(',', $infolog_types),
		);
		//error_log(__METHOD__."('$path', $user) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Handle propfind in the infolog folder
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user,$id='')
	{
		// todo add a filter to limit how far back entries from the past get synced
		$filter = $this->get_infolog_filter($path, $user);

		// process REPORT filters or multiget href's
		$nresults = null;
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options, $filter, $id, $nresults))
		{
			// return empty collection, as iCal under iOS 5 had problems with returning "404 Not found" status
			// when trying to request not supported components, eg. VTODO on a calendar collection
			return true;
		}
		// enable time-range filter for tests via propfind / autoindex
		//$filter[] = $sql = $this->_time_range_filter(array('end' => '20001231T000000Z'));

		if ($id) $path = dirname($path).'/';	// caldav_name get's added anyway in the callback

		if ($this->debug > 1)
		{
			error_log(__METHOD__."($path,,,$user,$id) filter=".
				array2string($filter));
		}

		// check if we have to return the full calendar data or just the etag's
		if (!($filter['calendar_data'] = $options['props'] == 'all' &&
			$options['root']['ns'] == Api\CalDAV::CALDAV) && is_array($options['props']))
		{
			foreach($options['props'] as $prop)
			{
				if ($prop['name'] == 'calendar-data')
				{
					$filter['calendar_data'] = true;
					break;
				}
			}
		}

		// rfc 6578 sync-collection report: filter for sync-token is already set in _report_filters
		if ($options['root']['name'] == 'sync-collection')
		{
			// callback to query sync-token, after propfind_callbacks / iterator is run and
			// stored max. modification-time in $this->sync_collection_token
			$files['sync-token'] = array($this, 'get_sync_collection_token');
			$files['sync-token-params'] = array($path, $user);

			$this->sync_collection_token = null;

			$filter['order'] = 'info_datemodified ASC';	// return oldest modifications first
			$filter['sync-collection'] = true;
		}

		if (isset($nresults))
		{
			$files['files'] = $this->propfind_generator($path, $filter, [], $nresults);

			// hack to support limit with sync-collection report: contacts are returned in modified ASC order (oldest first)
			// if limit is smaller than full result, return modified-1 as sync-token, so client requests next chunk incl. modified
			// (which might contain further entries with identical modification time)
			if ($options['root']['name'] == 'sync-collection' && $this->bo->total > $nresults)
			{
				--$this->sync_collection_token;
				$files['sync-token-params'][] = true;	// tel get_sync_collection_token that we have more entries
			}
		}
		else
		{
			$files['files'] = $this->propfind_generator($path,$filter, $files['files']);
		}
		return true;
	}

	/**
	 * Chunk-size for DB queries of profind_generator
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Generator for propfind with ability to skip reporting not found ids
	 *
	 * @param string $path
	 * @param array& $filter
	 * @param array $extra extra resources like the collection itself
	 * @param int|null $nresults option limit of number of results to report
	 * @return Generator<array with values for keys path and props>
	 */
	function propfind_generator($path, array &$filter, array $extra=[], $nresults=null)
	{
		if ($this->debug) $starttime = microtime(true);

		if (($calendar_data = $filter['calendar_data']))
		{
			$handler = self::_get_handler();
		}
		unset($filter['calendar_data']);
		$task_filter = $filter['filter'];
		unset($filter['filter']);

		// yield extra resources like the root itself
		$yielded = 0;
		foreach($extra as $resource)
		{
			if (++$yielded && isset($nresults) && $yielded > $nresults)
			{
				return;
			}
			yield $resource;
		}

		$order = 'info_datemodified';
		$sort = 'DESC';
		$matches = null;
		if (preg_match('/^([a-z0-9_]+)( DESC| ASC)?$/i', $filter['order'], $matches))
		{
			$order = $matches[1];
			if ($matches[2]) $sort = $matches[2];
			unset($filter['order']);
		}
		$query = array(
			'order'			=> $order,
			'sort'			=> $sort,
			'filter'    	=> $task_filter,
			'date_format'	=> 'server',
			'col_filter'	=> $filter,
			'custom_fields' => true,	// otherwise custom fields get NOT loaded!
			'start'         => 0,
			'num_rows'      => self::CHUNK_SIZE,
		);
		$check_responsible = false;
		if (substr($task_filter, -8) == '+deleted')
		{
			$check_responsible = substr($task_filter, 0, 4) == 'user' ?
				(int)substr($task_filter, 4) : $GLOBALS['egw_info']['user']['account_id'];
		}

		if (!$calendar_data)
		{
			$query['cols'] = array('main.info_id AS info_id', 'info_datemodified', 'info_uid', 'caldav_name', 'info_subject', 'info_status', 'info_owner');
		}

		$files = array();
		// ToDo: add parameter to only return id & etag
		// Please note: $query['start'] get incremented automatically by bo->search() with number of returned rows!
		while(($tasks =& $this->bo->search($query)))
		{
			foreach($tasks as $task)
			{
				// remove task from requested multiget ids, to be able to report not found urls
				if (!empty($this->requested_multiget_ids) && ($k = array_search($task[self::$path_attr], $this->requested_multiget_ids)) !== false)
				{
					unset($this->requested_multiget_ids[$k]);
				}
				// sync-collection report: deleted entry need to be reported without properties
				if ($task['info_status'] == 'deleted' ||
					// or event is reported as removed from collection, because collection owner is no longer an attendee
					$check_responsible && $task['info_owner'] != $check_responsible &&
						!infolog_so::is_responsible_user($task, $check_responsible))
				{
					if (++$yielded && isset($nresults) && $yielded > $nresults)
					{
						return;
					}
					yield ['path' => $path.urldecode($this->get_path($task))];
					continue;
				}
				$props = array(
					'getcontenttype' => $this->agent != 'kde' ? 'text/calendar; charset=utf-8; component=VTODO' : 'text/calendar',	// Konqueror (3.5) dont understand it otherwise
					'getlastmodified' => $task['info_datemodified'],
					'displayname' => $task['info_subject'],
				);
				if ($calendar_data)
				{
					$content = $handler->exportVTODO($task, '2.0', null);	// no METHOD:PUBLISH for CalDAV
					$props['getcontentlength'] = bytes($content);
					$props[] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-data',$content);
				}
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield $this->add_resource($path, $task, $props);
			}
			// Please note: $query['start'] get incremented automatically by bo->search() with number of returned rows!
			// --> we need to break here, if start is further then total
			if ($query['start'] < $query['total'])
			{
				break;
			}
		}
		// report not found multiget urls
		if (!empty($this->requested_multiget_ids))
		{
			foreach($this->requested_multiget_ids as $id)
			{
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield ['path' => $path.$id.self::$path_extension];
			}
		}
		// sync-collection report --> return modified of last contact as sync-token
		$sync_collection_report =  $filter['sync-collection'];
		if ($sync_collection_report)
		{
			$this->sync_collection_token = $task['date_modified'];
		}
		if ($this->debug)
		{
			error_log(__METHOD__."($path) took ".(microtime(true) - $starttime)." to return $yielded resources");
		}
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$cal_filters
	 * @param string $id
	 * @param int &$nresult on return limit for number or results or unchanged/null
	 * @return boolean true if filter could be processed
	 */
	function _report_filters($options,&$cal_filters,$id, &$nresults)
	{
		if ($options['filters'])
		{
			foreach($options['filters'] as $filter)
			{
				switch($filter['name'])
				{
					case 'comp-filter':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) comp-filter='{$filter['attrs']['name']}'");

						switch($filter['attrs']['name'])
						{
							case 'VTODO':
							case 'VCALENDAR':
								break;
							default:
								return false;
						}
						break;
					case 'prop-filter':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) prop-filter='{$filter['attrs']['name']}'");
						$prop_filter = $filter['attrs']['name'];
						break;
					case 'text-match':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) text-match: $prop_filter='{$filter['data']}'");
						if (!isset($this->filter_prop2infolog[strtoupper($prop_filter)]))
						{
							if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown property '$prop_filter' --> ignored");
						}
						else
						{
							$cal_filters[$this->filter_prop2infolog[strtoupper($prop_filter)]] = $filter['data'];
						}
						unset($prop_filter);
						break;
					case 'param-filter':
						if ($this->debug) error_log(__METHOD__."($options[path],...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'time-range':
						$cal_filters[] = $this->_time_range_filter($filter['attrs']);
						break;
					default:
						if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown filter --> ignored");
						break;
				}
			}
		}
		// parse limit from $options['other']
		/* Example limit
		  <B:limit>
		    <B:nresults>10</B:nresults>
		  </B:limit>
		*/
		foreach((array)$options['other'] as $option)
		{
			switch($option['name'])
			{
				case 'nresults':
					$nresults = (int)$option['data'];
					//error_log(__METHOD__."(...) options[other]=".array2string($options['other'])." --> nresults=$nresults");
					break;
				case 'limit':
					break;
				case 'href':
					break;	// from addressbook-multiget, handled below
				// rfc 6578 sync-report
				case 'sync-token':
					if (!empty($option['data']))
					{
						$parts = explode('/', $option['data']);
						$sync_token = array_pop($parts);
						$cal_filters[] = 'info_datemodified>'.(int)$sync_token;
						$cal_filters['filter'] .= '+deleted';	// to return deleted entries too
					}
					break;
				case 'sync-level':
					if ($option['data'] != '1')
					{
						$this->caldav->log(__METHOD__."(...) only sync-level {$option['data']} requested, but only 1 supported! options[other]=".array2string($options['other']));
					}
					break;
				default:
					$this->caldav->log(__METHOD__."(...) unknown xml tag '{$option['name']}': options[other]=".array2string($options['other']));
					break;
			}
		}
		// multiget or propfind on a given id
		$this->requested_multiget_ids = null;
		if ($options['root']['name'] == 'calendar-multiget' || $id)
		{
			if ($id)
			{
				$cal_filters[self::$path_attr] = self::$path_extension ?
					basename($id,self::$path_extension) : $id;
			}
			else	// fetch all given url's
			{
				$this->requested_multiget_ids = [];
				foreach($options['other'] as $option)
				{
					if ($option['name'] == 'href')
					{
						$parts = explode('/',$option['data']);
						if (($id = basename(urldecode(array_pop($parts)))))
						{
							$this->requested_multiget_ids[] = self::$path_extension ?
								basename($id,self::$path_extension) : $id;
						}
					}
				}
				$cal_filters[self::$path_attr] = $this->requested_multiget_ids;
			}
			if ($this->debug > 1) error_log(__METHOD__ ."($options[path],...,$id) calendar-multiget: ids=".implode(',', $this->requested_multiget_ids));
		}
		return true;
	}

	/**
	 * Create SQL filter from time-range filter attributes
	 *
	 * CalDAV time-range for VTODO checks DTSTART, DTEND, DUE, CREATED and allways includes tasks if none given
	 * @see http://tools.ietf.org/html/rfc4791#section-9.9
	 *
	 * @param array $attrs values for keys 'start' and/or 'end', at least one is required by CalDAV rfc!
	 * @return string with sql
	 */
	private function _time_range_filter(array $attrs)
	{
		$to_or = $to_and = array();
 		if (!empty($attrs['start']))
 		{
 			$start = (int)$this->vCalendar->_parseDateTime($attrs['start']);
		}
 		if (!empty($attrs['end']))
 		{
 			$end = (int)$this->vCalendar->_parseDateTime($attrs['end']);
		}
		elseif (empty($attrs['start']))
		{
			$this->caldav->log(__METHOD__.'('.array2string($attrs).') minimum one of start or end is required!');
			return '1';	// to not give sql error, but simply not filter out anything
		}
		// we dont need to care for DURATION line in rfc4791#section-9.9, as we always put that in DUE/info_enddate

		// we have start- and/or enddate
		if (isset($start))
		{
			$to_and[] = "($start < info_enddate OR $start <= info_startdate)";
		}
		if (isset($end))
		{
			$to_and[] = "(info_startdate < $end OR info_enddate <= $end)";
		}
		$to_or[] = '('.implode(' AND ', $to_and).')';

		/* either start or enddate is already included in the above, because of OR!
		// only a startdate, no enddate
		$to_or[] = "NOT info_enddate > 0".($start ? " AND $start <= info_startdate" : '').
			($end ? " AND info_startdate < $end" : '');

		// only an enddate, no startdate
		$to_or[] = "NOT info_startdate > 0".($start ? " AND $start < info_enddate" : '').
			($end ? " AND info_enddate <= $end" : '');*/

		// no startdate AND no enddate (2. half of rfc4791#section-9.9) --> use created and due dates instead
		$to_or[] = 'NOT info_startdate > 0 AND NOT info_enddate > 0 AND ('.
			// we have a completed date
			"info_datecompleted > 0".(isset($start) ? " AND ($start <= info_datecompleted OR $start <= info_created)" : '').
				(isset($end) ? " AND (info_datecompleted <= $end OR info_created <= $end)" : '').' OR '.
			// we have no completed date, but always a created date
 			"NOT info_datecompleted > 0". (isset($end) ? " AND info_created < $end" : '').
		')';
		$sql = '('.implode(' OR ', $to_or).')';
		if ($this->debug > 1) error_log(__FILE__ . __METHOD__.'('.array2string($attrs).") time-range=$attrs[start]-$attrs[end] --> $sql");
		return $sql;
	}

	/**
	 * Handle get request for a task / infolog entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		unset($user);	// not used, but required by function signature

		if (!is_array($task = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $task;
		}
		$handler = $this->_get_handler();
		$options['data'] = $handler->exportVTODO($task, '2.0', null);	// no METHOD:PUBLISH for CalDAV
		$options['mimetype'] = 'text/calendar; charset=utf-8';
		header('Content-Encoding: identity');
		header('ETag: "'.$this->get_etag($task).'"');
		return true;
	}

	/**
	 * Handle put request for a task / infolog entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @param string $prefix =null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null,$prefix=null)
	{
		unset($prefix);	// not used, but required by function signature

		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));

		$oldTask = $this->_common_get_put_delete('PUT',$options,$id);
		if (!is_null($oldTask) && !is_array($oldTask))
		{
			return $oldTask;
		}

		$handler = $this->_get_handler();
		$vTodo = htmlspecialchars_decode($options['content']);

		if (is_array($oldTask))
		{
			$taskId = $oldTask['info_id'];
			$retval = true;
		}
		else	// new entry
		{
			$taskId = 0;
			$retval = '201 Created';
		}
		if ($GLOBALS['egw_info']['user']['preferences']['groupdav']['infolog-cat-action'] &&
			$GLOBALS['egw_info']['user']['preferences']['groupdav']['infolog-cat-action'] !== 'none')
		{
			$callback_data = array(array($this, 'cat_action'), $oldTask);
		}
		if (!($infoId = $handler->importVTODO($vTodo, $taskId, false, $user, null, $id, $callback_data)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vtodo($options[content]) returned false");
			return '403 Forbidden';
		}

		if ($infoId != $taskId)
		{
			$retval = '201 Created';
		}

		// send evtl. necessary respose headers: Location, etag, ...
		// but only for new entries, as X-INFOLOG-STATUS get's not updated on client, if we confirm with an etag
		if ($retval !== true)
			// POST with add-member query parameter
			//$_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['add-member'])))
		{
			$this->put_response_headers($infoId, $options['path'], $retval, self::$path_attr == 'caldav_name');
		}
		return $retval;
	}

	/**
	 * Update etag, ctag and sync-token to reflect changed attachments
	 *
	 * @param array|string|int $entry array with entry data from read, or id
	 */
	public function update_tags($entry)
	{
		if (!is_array($entry)) $entry = $this->read($entry);

		$this->bo->write($entry, true);
	}

	/**
	 * Callback for infolog_ical::importVTODO to implement infolog-cat-action
	 *
	 * @param array $task
	 * @param array $oldTask =null
	 * @return array modified task data
	 */
	public function cat_action(array $task, $oldTask=null)
	{
		$action = $GLOBALS['egw_info']['user']['preferences']['groupdav']['infolog-cat-action'];

		//error_log(__METHOD__.'('.array2string($task).', '.array2string($oldTask).") action=$action");
		if ($task['info_cat'] && ($new_cat = Api\Categories::id2name($task['info_cat'])) &&
			strpos($new_cat, '@') !== false)
		{
			$new_user = $GLOBALS['egw']->accounts->name2id($new_cat, 'account_email');
		}
		$old_responsible = $task['info_responsible'];
		// no action taken, if cat is not email of user
		if ($new_user)
		{
			// make sure category is global, as otherwise it will not be transmitted to other users
			if (!Api\Categories::is_global($task['info_cat']))
			{
				$cat_obj = new Api\Categories(Api\Categories::GLOBAL_ACCOUNT, 'infolog');
				if (($cat = Api\Categories::read($task['info_cat'])))
				{
					$cat['owner'] = Api\Categories::GLOBAL_ACCOUNT;
					$cat['access'] = 'public';
					$cat_obj->edit($cat);
				}
			}
			// if replace, remove user of old category from responsible
			if ($action == 'replace' && $oldTask && $oldTask['info_cat'] &&
				($old_cat = Api\Categories::id2name($oldTask['info_cat'])) && strpos($old_cat, '@') !== false &&
				($old_user = $GLOBALS['egw']->accounts->name2id($old_cat, 'account_email')) &&
				($key = array_search($old_user, (array)$task['info_responsible'])) !== false)
			{
				unset($task['info_responsible'][$key]);
			}
			switch($action)
			{
				case 'set':
					$task['info_responsible'] = array();
					// fall through
				case 'set-user':
					foreach($task['info_responsible'] as $key => $account_id)
					{
						if ($GLOBALS['egw']->accounts->get_type($account_id) == 'u')
						{
							unset($task['info_responsible'][$key]);
						}
					}
					// fall-through
				case 'add':
				case 'replace':
					if (!in_array($new_user, (array)$task['info_responsible']))
					{
						$task['info_responsible'][] = $new_user;
					}
					break;
			}
		}
		error_log(__METHOD__."() action=$action, new_cat=$new_cat --> new_user=$new_user, old_cat=$old_cat --> old_user=$old_user: responsible: ".array2string($old_responsible).' --> '.array2string($task['info_responsible']));
		return $task;
	}

	/**
	 * Handle delete request for a task / infolog entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id,$user)
	{
		unset($user);	// not used, but required by function signature

		if (!is_array($task = $this->_common_get_put_delete('DELETE',$options,$id)))
		{
			return $task;
		}
		return $this->bo->delete($task['info_id']);
	}

	/**
	 * Read an entry
	 *
	 * We have to make sure to not return or even consider in read deleted infologs, as the might have
	 * the same UID and/or caldav_name as not deleted ones and would block access to valid entries
	 *
	 * @param string|id $id
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		return $this->bo->read(array(self::$path_attr => $id, "info_status!='deleted'"),false,'server');
	}

	/**
	 * Get id from entry-array returned by read()
	 *
	 * Reimplemented because id uses key 'info_id'
	 *
	 * @param int|string|array $entry
	 * @return int|string
	 */
	function get_id($entry)
	{
		return is_array($entry) ? $entry['info_id'] : $entry;
	}

	/**
	 * Check if user has the neccessary rights on a task / infolog entry
	 *
	 * @param int $acl Acl::READ, Acl::EDIT or Acl::DELETE
	 * @param array|int $task task-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$task)
	{
		if (is_null($task)) return true;

		$access = $this->bo->check_access($task,$acl);

		if (!$access && $acl == Acl::EDIT && $this->bo->is_responsible($task))
		{
			$access = true;	// access limited to $this->bo->responsible_edit fields (handled in infolog_bo::write())
		}
		if ($this->debug > 1) error_log(__METHOD__."($acl, ".array2string($task).') returning '.array2string($access));
		return $access;
	}

	/**
	 * Query ctag for infolog
	 *
	 * @return string
	 */
	public function getctag($path,$user)
	{
		return $this->bo->getctag($this->get_infolog_filter($path, $user));
	}

	/**
	 * Get the etag for an infolog entry
	 *
	 * etag currently uses the modifcation time (info_datemodified), 1.9.002 adds etag column, but it's not yet used!
	 *
	 * @param array|int $info array with infolog entry or info_id
	 * @return string|boolean string with etag or false
	 */
	function get_etag($info)
	{
		if (!is_array($info))
		{
			$info = $this->bo->read($info,true,'server');
		}
		if (!is_array($info) || !isset($info['info_id']) || !isset($info['info_datemodified']))
		{
			return false;
		}
		return $info['info_id'].':'.$info['info_datemodified'];
	}

	/**
	 * Add extra properties for calendar collections
	 *
	 * @param array $props =array() regular props by the Api\CalDAV handler
	 * @param string $displayname
	 * @param string $base_uri =null base url of handler
	 * @param int $user =null account_id of owner of collection
	 * @return array
	 */
	public function extra_properties(array $props, $displayname, $base_uri=null,$user=null)
	{
		unset($base_uri);	// not used, but required by function signature

		// calendar description
		$displayname = Api\Translation::convert(lang('Tasks of'),Api\Translation::charset(),'utf-8').' '.$displayname;
		$props['calendar-description'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-description',$displayname);
		// supported components, currently only VTODO
		$props['supported-calendar-component-set'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'supported-calendar-component-set',array(
			Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'comp',array('name' => 'VTODO')),
		));
		// supported reports
		$props['supported-report-set'] = array(
			'calendar-query' => Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-query',''))))),
			'calendar-multiget' => Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-multiget',''))))),
		);
		// only advertice rfc 6578 sync-collection report, if "delete-prevention" is switched on (deleted entries get marked deleted but not actualy deleted
		$config = Api\Config::read('infolog');
		if ($config['history'])
		{
			$props['supported-report-set']['sync-collection'] = Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop('sync-collection','')))));
		}
		// get timezone of calendar
		if ($this->caldav->prop_requested('calendar-timezone'))
		{
			$props['calendar-timezone'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-timezone',
				calendar_timezones::user_timezone($user));
		}
		return $props;
	}

	/**
	 * Get the handler and set the supported fields
	 *
	 * @return infolog_ical
	 */
	private function _get_handler()
	{
		$handler = new infolog_ical();
		$handler->tzid = false;	//	as we read server-time timestamps (!= null=user-time), exports UTC times
		$handler->setSupportedFields('GroupDAV',$this->agent);

		return $handler;
	}

	/**
	 * Return appliction specific settings
	 *
	 * @param array $hook_data
	 * @return array of array with settings
	 */
	static function get_settings($hook_data)
	{
		if (!isset($hook_data['setup']))
		{
			Api\Translation::add_app('infolog');
			$infolog = new infolog_bo();
			$types = $infolog->enums['type'];
		}
		if (!isset($types))
		{
			$types = array(
				'task' => 'Tasks',
			);
		}
		$settings = array();
		$settings['infolog-types'] = array(
			'type'   => 'multiselect',
			'label'  => 'InfoLog types to sync',
			'name'   => 'infolog-types',
			'help'   => 'Which InfoLog types should be synced with the device, default only tasks.',
			'values' => array('0' => lang('none')) + $types,
			'default' => 'task',
			'xmlrpc' => True,
			'admin'  => False,
		);
		$settings['infolog-cat-action'] = array(
			'type'   => 'select',
			'label'  => 'Action when category is an EMail address',
			'name'   => 'infolog-cat-action',
			'help'   => 'Allows to modify responsible users from devices not supporting them, by setting EMail address of a user as category.',
			'values' => array(
				'none' => lang('none'),
				'add' => lang('add user to responsibles'),
				'replace' => lang('add user to responsibles, removing evtl. previous category user'),
				'set' => lang('set user as only responsible, removing all existing responsibles'),
				'set-user' => lang('set user as only responsible user, but keeping groups'),
			),
			'default' => 'none',
			'xmlrpc' => True,
			'admin'  => False,
		);
		return $settings;
	}
}