<?php
/**
 * EGroupware: GroupDAV access: infolog handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

/**
 * EGroupware: GroupDAV access: infolog handler
 */
class infolog_groupdav extends groupdav_handler
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
	 * Get's set in constructor to 'caldav_name' and groupdav_handler::$path_extension = ''!
	 */
	static $path_attr = 'info_id';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param groupdav $groupdav calling class
	 */
	function __construct($app, groupdav $groupdav)
	{
		parent::__construct($app, $groupdav);

		$this->bo = new infolog_bo();
		$this->vCalendar = new Horde_iCalendar;

		// since 1.9.002 we allow clients to specify the URL when creating a new event, as specified by CalDAV
		if (version_compare($GLOBALS['egw_info']['apps']['calendar']['version'], '1.9.002', '>='))
		{
			self::$path_attr = 'caldav_name';
			groupdav_handler::$path_extension = '';
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
		return $name.groupdav_handler::$path_extension;
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
		if (!($infolog_types = $GLOBALS['egw_info']['user']['preferences']['activesync']['infolog-types']))
		{
			$infolog_types = 'task';
		}

		$myself = ($user == $GLOBALS['egw_info']['user']['account_id']);

		if ($path == '/infolog/')
		{
			$task_filter= 'own';
		}
		else
		{
			if ($myself)
			{
				$task_filter = 'open';
			}
			else
			{
				$task_filter = 'open-user' . $user;
			}
		}

		return array(
			'filter'	=> $task_filter,
			'info_type' => explode(',', $infolog_types),
		);
	}

	/**
	 * Handle propfind in the infolog folder
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,$options,&$files,$user,$id='')
	{
		// todo add a filter to limit how far back entries from the past get synced
		$filter = $this->get_infolog_filter($path, $user);

		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id))
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
			$options['root']['ns'] == groupdav::CALDAV) && is_array($options['props']))
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

		// return iterator, calling ourself to return result in chunks
		$files['files'] = new groupdav_propfind_iterator($this,$path,$filter,$files['files']);

		return true;
	}

	/**
	 * Callback for profind interator
	 *
	 * @param string $path
	 * @param array $filter
	 * @param array|boolean $start=false false=return all or array(start,num)
	 * @return array with "files" array with values for keys path and props
	 */
	function &propfind_callback($path,array $filter,$start=false)
	{
		if ($this->debug) $starttime = microtime(true);

		if (($calendar_data = $filter['calendar_data']))
		{
			$handler = self::_get_handler();
		}
		unset($filter['calendar_data']);
		$task_filter = $filter['filter'];
		unset($filter['filter']);

		$query = array(
			'order'			=> 'info_datemodified',
			'sort'			=> 'DESC',
			'filter'    	=> $task_filter,
			'date_format'	=> 'server',
			'col_filter'	=> $filter,
		);

		if (!$calendar_data)
		{
			$query['cols'] = array('info_id', 'info_datemodified', 'info_uid', 'caldav_name');
		}

		if (is_array($start))
		{
			$query['start'] = $offset = $start[0];
			$query['num_rows'] = $start[1];
		}
		else
		{
			$offset = 0;
		}

		$files = array();
		// ToDo: add parameter to only return id & etag
		$tasks =& $this->bo->search($query);
		if ($tasks && $offset == $query['start'])
		{
			foreach($tasks as $task)
			{
				$props = array(
					'getcontenttype' => $this->agent != 'kde' ? 'text/calendar; charset=utf-8; component=VTODO' : 'text/calendar',	// Konqueror (3.5) dont understand it otherwise
					'getlastmodified' => $task['info_datemodified'],
				);
				if ($calendar_data)
				{
					$content = $handler->exportVTODO($task, '2.0', null);	// no METHOD:PUBLISH for CalDAV
					$props['getcontentlength'] = bytes($content);
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data',$content);
				}
				$files[] = $this->add_resource($path, $task, $props);
			}
		}
		if ($this->debug) error_log(__METHOD__."($path) took ".(microtime(true) - $starttime).' to return '.count($files).' items');
		return $files;
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$cal_filters
	 * @param string $id
	 * @return boolean true if filter could be processed, false for requesting not here supported VTODO items
	 */
	function _report_filters($options,&$cal_filters,$id)
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
						$cal_filters[] = $sql = $this->_time_range_filter($filter['attrs']);
						break;
					default:
						if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown filter --> ignored");
						break;
				}
			}
		}
		// multiget or propfind on a given id
		//error_log(__FILE__ . __METHOD__ . "multiget of propfind:");
		if ($options['root']['name'] == 'calendar-multiget' || $id)
		{
			$ids = array();
			if ($id)
			{
				$cal_filters[self::$path_attr] = groupdav_handler::$path_extension ?
					basename($id,groupdav_handler::$path_extension) : $id;
			}
			else	// fetch all given url's
			{
				foreach($options['other'] as $option)
				{
					if ($option['name'] == 'href')
					{
						$parts = explode('/',$option['data']);
						if (($id = basename(array_pop($parts))))
						{
							$cal_filters[self::$path_attr][] = groupdav_handler::$path_extension ?
								basename($id,groupdav_handler::$path_extension) : $id;
						}
					}
				}
			}
			if ($this->debug > 1) error_log(__METHOD__ ."($options[path],...,$id) calendar-multiget: ids=".implode(',',$ids));
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
			error_log(__METHOD__.'('.array2string($attrs).') minimum one of start or end is required!');
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
		if ($this->debug > 1) error_log(__FILE__ . __METHOD__.'('.array2string($attrs).") time-range={$filter['attrs']['start']}-{$filter['attrs']['end']} --> $sql");
		return $sql;
	}

	/**
	 * Handle get request for a task / infolog entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
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
	 * @param int $user=null account_id of owner, default null
	 * @param string $prefix=null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null,$prefix=null)
	{
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
		if ($user)
		{
			if (!$prefix)		// for everything in /infolog/
			{
				$user = null;	// do NOT set current user (infolog_bo->write() set it for new entries anyway)
			}
			elseif($oldTask)	// existing entries
			{
				if ($oldTask['info_owner'] != $user)
				{
					if ($this->debug) error_log(__METHOD__."(,$id,$user,$prefix) changing owner of existing entries is forbidden!");
					return '403 Forbidden';		// changing owner of existing entries is generally forbidden
				}
				$user = null;
			}
			else	// new entries in /$user/infolog
			{
				// ACL is checked in infolog_bo->write() called by infolog_ical->importVTODO().
				// Not sure if it's a good idea to set a different owner, as GUI does NOT allow that,
				// thought there's an ACL for it and backend (infolog_bo) checks it.
				// More like the GUI would be to add it for current user and delegate it to $user.
			}
		}
		if (!($infoId = $handler->importVTODO($vTodo, $taskId, false, $user, null, $id)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vtodo($options[content]) returned false");
			return '403 Forbidden';
		}

		if ($infoId != $taskId)
		{
			$retval = '201 Created';
		}

		// we should not return an etag here, as we never store the PUT ical byte-by-byte
		//header('ETag: "'.$this->get_etag($infoId).'"');

		// send GroupDAV Location header only if we dont use caldav_name as path-attribute
		if ($retval !== true && self::$path_attr != 'caldav_name')
		{
			$path = preg_replace('|(.*)/[^/]*|', '\1/', $options['path']);
			header('Location: '.$this->base_uri.$path.self::get_path($infoId));
		}
		return $retval;
	}

	/**
	 * Handle delete request for a task / infolog entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id)
	{
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
	 * Check if user has the neccessary rights on a task / infolog entry
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array|int $task task-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$task)
	{
		if (is_null($task)) return true;

		return $this->bo->check_access($task,$acl);
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
	 * etag currently uses the modifcation time (info_modified), 1.9.002 adds etag column, but it's not yet used!
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
		return 'EGw-'.$info['info_id'].':'.$info['info_datemodified'].'-wGE';
	}

	/**
	 * Add extra properties for calendar collections
	 *
	 * @param array $props=array() regular props by the groupdav handler
	 * @param string $displayname
	 * @param string $base_uri=null base url of handler
	 * @return array
	 */
	static function extra_properties(array $props=array(), $displayname, $base_uri=null)
	{
		// calendar description
		$displayname = translation::convert(lang('Tasks of') . ' ' .
			$displayname,translation::charset(),'utf-8');
		$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-description',$displayname);
		// email of the current user, see caldav-sheduling draft
		$props[] =	HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-address-set',array(
			HTTP_WebDAV_Server::mkprop('href','MAILTO:'.$GLOBALS['egw_info']['user']['email'])));
		// supported components, currently only VEVENT
		$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'supported-calendar-component-set',array(
			// HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VEVENT')),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VCALENDAR')),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VTIMEZONE')),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VTODO')),
		));

		$props[] = HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-multiget','')))))));

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
		$handler->setSupportedFields('GroupDAV',$this->agent);

		return $handler;
	}
}