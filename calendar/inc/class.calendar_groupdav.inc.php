<?php
/**
 * eGroupWare: GroupDAV access: calendar handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/calendar/inc/class.bocalupdate.inc.php');

/**
 * eGroupWare: GroupDAV access: calendar handler
 */
class calendar_groupdav extends groupdav_handler
{
	/**
	 * bo class of the application
	 *
	 * @var bocalupdate
	 */
	var $bo;

	var $filter_prop2cal = array(
		'SUMMARY' => 'cal_title',
		'UID' => 'cal_uid',
		'DTSTART' => 'cal_start',
		'DTEND' => 'cal_end',
		// 'DURATION'
		//'RRULE' => 'recur_type',
		//'RDATE' => 'cal_start',
		//'EXRULE'
		//'EXDATE'
		//'RECURRENCE-ID'
	);

	function __construct($debug=null)
	{
		parent::__construct('calendar',$debug);

		$this->bo =& new bocalupdate();
	}

	/**
	 * Handle propfind in the calendar folder
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id=''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,$options,&$files,$user,$id='')
	{
		if ($this->debug > 2) error_log(__METHOD__."($path,".str_replace(array("\n",'    '),'',print_r($options,true)).",,$user,$id)");

		// ToDo: add parameter to only return id & etag
		$cal_filters = array(
			'users' => $user,
			'start' => time()-30*24*3600,	// default one month back
			'end' => time()+365*24*3600,	// default one year into the future
			'enum_recuring' => false,
			'daywise' => false,
			'date_format' => 'server',
		);
		error_log(__METHOD__."($path,,,$user,$id) cal_filters=".str_replace(array("\n",'    '),'',print_r($cal_filters,true)));

		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$cal_filters,$id))
		{
			return false;
		}
		// check if we have to return the full calendar data or just the etag's
		if (!($calendar_data = $options['props'] == 'all' && $options['root']['ns'] == groupdav::CALDAV))
		{
			foreach($options['props'] as $prop)
			{
				if ($prop['name'] == 'calendar-data')
				{
					$calendar_data = true;
					break;
				}
			}
		}
		if (($events = $this->bo->search($cal_filters)))
		{
			foreach($events as $event)
			{
				$props = array(
					HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($event)),
					HTTP_WebDAV_Server::mkprop('getcontenttype', 'text/calendar'),
				);
				if ($calendar_data)
				{
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data',
						ExecMethod2('calendar.boical.exportVCal',array($event),'2.0','PUBLISH',false));
				}
				$files['files'][] = array(
	            	'path'  => '/calendar/'.$event['id'],
	            	'props' => $props,
				);
			}
		}
		return true;
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
			// unset default start & end
			$cal_start = $cal_filters['start']; unset($cal_filters['start']);
			$cal_end = $cal_filters['end']; unset($cal_filters['end']);
			$num_filters = count($cal_filters);

			foreach($options['filters'] as $filter)
			{
				switch($filter['name'])
				{
					case 'comp-filter':
						error_log(__METHOD__."($path,...) comp-filter='{$filter['attrs']['name']}'");
						switch($filter['attrs']['name'])
						{
							case 'VTODO':
								return false;	// return nothing for now, todo: check if we can pass it on to the infolog handler
								// todos are handled by the infolog handler
								$infolog_handler = new infolog_groupdav();
								return $infolog_handler->propfind($path,$options,$files,$user,$method);
							case 'VCALENDAR':
							CASE 'VEVENT':
								break;			// that's our default anyway
						}
						break;
					case 'prop-filter':
						error_log(__METHOD__."($path,...) prop-filter='{$filter['attrs']['name']}'");
						$prop_filter = $filter['attrs']['name'];
						break;
					case 'text-match':
						error_log(__METHOD__."($path,...) text-match: $prop_filter='{$filter['data']}'");
						if (!isset($this->filter_prop2cal[strtoupper($prop_filter)]))
						{
							error_log(__METHOD__."($path,".str_replace(array("\n",'    '),'',print_r($options,true)).",,$user) unknown property '$prop_filter' --> ignored");
						}
						else
						{
							$cal_filters['query'][$this->filter_prop2cal[strtoupper($prop_filter)]] = $filter['data'];
						}
						unset($prop_filter);
						break;
					case 'param-filter':
						error_log(__METHOD__."($path,...) param-filter='{$filter['attrs']['name']}'");
						break;
					case 'time-range':
						error_log(__METHOD__."($path,...) time-range={$filter['attrs']['start']}-{$filter['attrs']['end']}");

						break;
					default:
						error_log(__METHOD__."($path,".str_replace(array("\n",'    '),'',print_r($options,true)).",,$user) unknown filter --> ignored");
						break;
				}
			}
			if (count($cal_filters) != $num_filters)	// no filters set --> restore default start and end time
			{
				$cal_filters['start'] = $cal_start;
				$cal_filters['end']   = $cal_end;
			}
		}
		// multiget or propfind on a given id
		if ($options['root']['name'] == 'calendar-multiget' || $id)
		{
			// no standard time-range!
			unset($cal_filters['start']);
			unset($cal_filters['end']);

			$ids = array();

			if ($id)
			{
				if (is_numeric($id))
				{
					$ids[] = (int)$id;
				}
				else
				{
					$cal_filters['query']['uid'] = basename($id,'.ics');
				}

			}
			else	// fetch all given url's
			{
				foreach($options['other'] as $option)
				{
					if ($option['name'] == 'href')
					{
						$parts = explode('/',$option['data']);
						if (is_numeric($id = array_pop($parts))) $ids[] = $id;
					}
				}
			}
			if ($ids)
			{
				$cal_filters['query'][] = 'egw_cal.cal_id IN ('.implode(',',array_map(create_function('$n','return (int)$n;'),$ids)).')';
			}
			//error_log(__METHOD__."($path,,,$user,$id) calendar-multiget: ids=".implode(',',$ids));
		}
		return true;
	}

	/**
	 * Handle get request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id)
	{
		if (!is_array($event = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $event;
		}
		$options['data'] = ExecMethod2('calendar.boical.exportVCal',array($event),'2.0','PUBLISH',false);
		$options['mimetype'] = 'text/calendar; charset=utf-8';
		header('Content-Encoding: identity');
		header('ETag: '.$this->get_etag($event));
		return true;
	}

	/**
	 * Handle put request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null)
	{
		$event = $this->_common_get_put_delete('PUT',$options,$id);
		if (!is_null($event) && !is_array($event))
		{
			return $event;
		}
		if (!($cal_id = ExecMethod2('calendar.boical.importVCal',$options['content'],is_numeric($id) ? $id : -1,
			self::etag2value($this->http_if_match))))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vevent($options[content]) returned false");
			return false;	// something went wrong ...
		}

		header('ETag: '.$this->get_etag($cal_id));
		if (is_null($event) || $id != $cal_id)
		{
			header('Location: '.$this->base_uri.'/calendar/'.$cal_id);
			return '201 Created';
		}
		return true;
	}

	/**
	 * Handle delete request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id)
	{
		if (!is_array($event = $this->_common_get_put_delete('DELETE',$options,$id)))
		{
			return $event;
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
		return $this->bo->read($id,null,false,'server');
	}

	/**
	 * Get the etag for an entry, reimplemented to include the participants and stati in the etag
	 *
	 * @param array/int $event array with event or cal_id
	 * @return string/boolean string with etag or false
	 */
	function get_etag($entry)
	{
		if (!is_array($entry))
		{
			$entry = $this->read($entry);
		}
		$etag = $entry['id'].':'.$entry['etag'];
		// add a hash over the participants and their stati
		ksort($entry['participants']);	// create a defined order
		$etag .= ':'.md5(serialize($entry['participants']));
		//error_log(__METHOD__."($entry[id]: $entry[title])=$etag");
		return $etag;
	}

	/**
	 * Check if user has the neccessary rights on an event
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array/int $event event-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$event)
	{
		return $this->bo->check_perms($acl,$event,0,'server');
	}

	/**
	 * Add extra properties for calendar collections
	 *
	 * @param array $props=array() regular props by the groupdav handler
	 * @return array
	 */
	static function extra_properties(array $props=array())
	{
		// calendaring URL of the current user
		$props[] =	HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',$_SERVER['SCRIPT_NAME'].'/calendar/');
		// email of the current user, see caldav-sheduling draft
		$props[] =	HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-address-set','mailto:'.$GLOBALS['egw_info']['user']['email']);

		return $props;
	}
}