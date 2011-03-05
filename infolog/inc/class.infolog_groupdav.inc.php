<?php
/**
 * eGroupWare: GroupDAV access: infolog handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

/**
 * eGroupWare: GroupDAV access: infolog handler
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
	);
	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 * @param string $principalURL=null pricipal url of handler
	 */
	function __construct($app,$debug=null,$base_uri=null,$principalURL=null)
	{
		parent::__construct($app,$debug,$base_uri,$principalURL);

		$this->bo = new infolog_bo();
		$this->vCalendar = new Horde_iCalendar;
	}

	const PATH_ATTRIBUTE = 'info_id';

	/**
	 * Create the path for an event
	 *
	 * @param array|int $info
	 * @return string
	 */
	static function get_path($info)
	{
		if (is_numeric($info) && self::PATH_ATTRIBUTE == 'info_id')
		{
			$name = $info;
		}
		else
		{
			if (!is_array($info)) $info = $this->bo->read($info);
			$name = $info[self::PATH_ATTRIBUTE];
		}
		return $name.'.ics';
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

		// todo add a filter to limit how far back entries from the past get synced
		$filter = array(
			'info_type'	=> 'task',
			'filter'	=> $task_filter,
		);

		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id))
		{
			return false;
		}
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
			$query['cols'] = array('info_id', 'info_datemodified');
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
					HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($task)),
					HTTP_WebDAV_Server::mkprop('getcontenttype',$this->agent != 'kde' ?
							'text/calendar; charset=utf-8; component=VTODO' : 'text/calendar'),	// Konqueror (3.5) dont understand it otherwise
							// getlastmodified and getcontentlength are required by WebDAV and Cadaver eg. reports 404 Not found if not set
							HTTP_WebDAV_Server::mkprop('getlastmodified', $task['info_datemodified']),
							HTTP_WebDAV_Server::mkprop('resourcetype',''),	// DAVKit requires that attribute!
							HTTP_WebDAV_Server::mkprop('getcontentlength',''),
				);
				if ($calendar_data)
				{
					$content = $handler->exportVTODO($task,'2.0','PUBLISH');
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength',bytes($content));
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data',$content);
				}
				else
				{
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength', ''); // expensive to calculate and no CalDAV client uses it
				}
				$files[] = array(
	            	'path'  => $path.self::get_path($task),
	            	'props' => $props,
				);
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
				 		if ($this->debug > 1) error_log(__FILE__ . __METHOD__."($options[path],...) time-range={$filter['attrs']['start']}-{$filter['attrs']['end']}");
				 		if (!empty($filter['attrs']['start']))
				 		{
					 		$cal_filters[] = 'info_startdate >= ' . (int)$this->vCalendar->_parseDateTime($filter['attrs']['start']);
				 		}
				 		if (!empty($filter['attrs']['end']))
				 		{
					 		$cal_filters[]   = 'info_startdate <= ' . (int)$this->vCalendar->_parseDateTime($filter['attrs']['end']);
				 		}
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
				if (is_numeric($id))
				{
					$cal_filters['info_id'] = $id;
				}
				else
				{
					$cal_filters['info_uid'] = basename($id,'.ics');
				}
			}
			else	// fetch all given url's
			{
				foreach($options['other'] as $option)
				{
					if ($option['name'] == 'href')
					{
						$parts = explode('/',$option['data']);
						if (is_numeric($id = basename(array_pop($parts),'.ics'))) $ids[] = $id;
					}
				}
				if ($ids)
				{
					$cal_filters[] = 'info_id IN ('.implode(',',array_map(create_function('$n','return (int)$n;'),$ids)).')';
				}
			}
			if ($this->debug > 1) error_log(__METHOD__ ."($options[path],...,$id) calendar-multiget: ids=".implode(',',$ids));
		}
		return true;
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
		$options['data'] = $handler->exportVTODO($id,'2.0','PUBLISH');
		$options['mimetype'] = 'text/calendar; charset=utf-8';
		header('Content-Encoding: identity');
		header('ETag: '.$this->get_etag($task));
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
		else
		{
			// new entry?
			if (($foundTasks = $handler->searchVTODO($vTodo)))
			{
				if (($taskId = array_shift($foundTasks)) &&
					($oldTask = $this->bo->read($taskId)))
				{
					$retval = '301 Moved Permanently';
				}
				else
				{
					// to be safe
					$taskId = 0;
					$retval = '201 Created';
				}
			}
			else
			{
				// new entry
				$taskId = 0;
				$retval = '201 Created';
			}
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
		if (!($infoId = $handler->importVTODO($vTodo, $taskId, false, $user)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vtodo($options[content]) returned false");
			return '403 Forbidden';
		}

		/*
		if (strstr($option['path'], '/infolog/') === 0)
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
				$task_filter = 'user' . $user. '-open';
			}
		}

		$query = array(
			'order'			=> 'info_datemodified',
			'sort'			=> 'DESC',
			'filter'    	=> $task_filter,
			'date_format'	=> 'server',
			'col_filter'	=> array('info_id' => $infoId),
		);

		if (!$this->bo->search($query))
		{
			$retval = '410 Gone';
		}
		else
		*/
		if ($infoId != $taskId)
		{
			$retval = '201 Created';

		}

		header('ETag: '.$this->get_etag($infoId));
		if ($retval !== true)
		{
			$path = preg_replace('|(.*)/[^/]*|', '\1/', $options['path']);
			header('Location: '.$this->base_uri.$path.self::get_path($infoId));
			return $retval;
		}
		return true;
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
		return $this->bo->delete($id);
	}

	/**
	 * Read an entry
	 *
	 * @param string/id $id
	 * @return array/boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		if (is_numeric($id)) return $this->bo->read($id,false,'server');
		return null;
	}

	/**
	 * Check if user has the neccessary rights on a task / infolog entry
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array/int $task task-array or id
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

		$query = array(
			'order'			=> 'info_datemodified',
			'sort'			=> 'DESC',
			'filter'    	=> $task_filter,
			'date_format'	=> 'server',
			'col_filter'	=> array('info_type' => 'task'),
			'start'			=> 0,
			'num_rows'		=> 1,
		);

		$result =& $this->bo->search($query);

		if (empty($result)) return 'EGw-0-wGE';

		$entry = array_shift($result);

		return $this->get_etag($entry);
	}

	/**
	 * Get the etag for an infolog entry
	 *
	 * @param array/int $info array with infolog entry or info_id
	 * @return string/boolean string with etag or false
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