<?php
/**
 * eGroupWare: GroupDAV access: calendar handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare: GroupDAV access: calendar handler
 */
class calendar_groupdav extends groupdav_handler
{
	/**
	 * bo class of the application
	 *
	 * @var calendar_boupdate
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

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 */
	function __construct($app,$debug=null, $base_uri=null)
	{
		parent::__construct($app,$debug,$base_uri);

		$this->bo = new calendar_boupdate();
	}

	const PATH_ATTRIBUTE = 'id';

	/**
	 * Create the path for an event
	 *
	 * @param array|int $event
	 * @return string
	 */
	static function get_path($event)
	{
		if (is_numeric($event) && self::PATH_ATTRIBUTE == 'id')
		{
			$name = $event;
		}
		else
		{
			if (!is_array($event)) $event = $this->bo->read($event);
			$name = $event[self::PATH_ATTRIBUTE];
		}
		return '/calendar/'.$name.'.ics';
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
		if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user,$id)");
		$starttime = microtime(true);

		// ToDo: add parameter to only return id & etag
		$cal_filters = array(
			'users' => $user,
			'start' => time()-100*24*3600,	// default one month back -30 breaks all sync  recurrences
			'end' => time()+365*24*3600,	// default one year into the future +365
			'enum_recuring' => false,
			'daywise' => false,
			'date_format' => 'server',
		);
		if ($this->debug > 1) error_log(__METHOD__."($path,,,$user,$id) cal_filters=".array2string($cal_filters));
		//error_log(__METHOD__."($path,,,$user,$id) cal_filters=".array2string($cal_filters));
		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$cal_filters,$id))
		{
			return false;
		}
		// check if we have to return the full calendar data or just the etag's
		if (!($calendar_data = $options['props'] == 'all' && $options['root']['ns'] == groupdav::CALDAV) && is_array($options['props']))
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
		if (($events =& $this->bo->search($cal_filters)))
		{
			foreach($events as &$event)
			{
				//header('X-EGROUPWARE-EVENT-'.$event['id'].': '.$event['title'].': '.date('Y-m-d H:i:s',$event['start']).' - '.date('Y-m-d H:i:s',$event['end']));
				$props = array(
					HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($event)),
					HTTP_WebDAV_Server::mkprop('getcontenttype', $this->agent != 'kde' ?
	            			'text/calendar; charset=utf-8; component=VEVENT' : 'text/calendar'),
					// getlastmodified and getcontentlength are required by WebDAV and Cadaver eg. reports 404 Not found if not set
					HTTP_WebDAV_Server::mkprop('getlastmodified', $event['modified']),
					HTTP_WebDAV_Server::mkprop('resourcetype',''),	// iPhone requires that attribute!
				);
				//error_log(__FILE__ . __METHOD__ . "Calendar Data : $calendar_data");
				if ($calendar_data)
				{
					if (is_null($handler)) $handler = $this->_get_handler();
					$content = $handler->exportVCal(array($event),'2.0','PUBLISH');
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength',bytes($content));
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data',$content);
				}
				else
				{
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength', '');		// expensive to calculate and no CalDAV client uses it
				}
				$files['files'][] = array(
	            	'path'  => self::get_path($event),
	            	'props' => $props,
				);
			}
		}
		if ($this->debug) error_log(__METHOD__."($path) took ".(microtime(true) - $starttime).' to return '.count($files['files']).' items');
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
						if ($this->debug > 1) error_log(__METHOD__."($path,...) comp-filter='{$filter['attrs']['name']}'");

						switch($filter['attrs']['name'])
						{
							case 'VTODO':
								return false;	// return nothing for now, todo: check if we can pass it on to the infolog handler
								// todos are handled by the infolog handler
								$infolog_handler = new groupdav_infolog();
								return $infolog_handler->propfind($path,$options,$files,$user,$method);
							case 'VCALENDAR':
							case 'VEVENT':
								break;			// that's our default anyway
						}
						break;
					case 'prop-filter':
						if ($this->debug > 1) error_log(__METHOD__."($path,...) prop-filter='{$filter['attrs']['name']}'");
						$prop_filter = $filter['attrs']['name'];
						break;
					case 'text-match':
						if ($this->debug > 1) error_log(__METHOD__."($path,...) text-match: $prop_filter='{$filter['data']}'");
						if (!isset($this->filter_prop2cal[strtoupper($prop_filter)]))
						{
							if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user) unknown property '$prop_filter' --> ignored");
						}
						else
						{
							$cal_filters['query'][$this->filter_prop2cal[strtoupper($prop_filter)]] = $filter['data'];
						}
						unset($prop_filter);
						break;
					case 'param-filter':
						if ($this->debug) error_log(__METHOD__."($path,...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'time-range':
				 		if ($this->debug > 1) error_log(__FILE__ . __METHOD__."($path,...) time-range={$filter['attrs']['start']}-{$filter['attrs']['end']}");
						$cal_filters['start'] = $filter['attrs']['start'];
						$cal_filters['end']   = $filter['attrs']['end'];
						break;
					default:
						if ($this->debug) error_log(__METHOD__."($path,".array2string($options).",,$user) unknown filter --> ignored");
						break;
				}
			}
			if (count($cal_filters) == $num_filters)	// no filters set --> restore default start and end time
			{
				$cal_filters['start'] = $cal_start;
				$cal_filters['end']   = $cal_end;
			}
		}
		// multiget or propfind on a given id
		//error_log(__FILE__ . __METHOD__ . "multiget of propfind:");
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
					$cal_filters['query']['cal_uid'] = basename($id,'.ics');
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
			}
			if ($ids)
			{
				$cal_filters['query'][] = 'egw_cal.cal_id IN ('.implode(',',array_map(create_function('$n','return (int)$n;'),$ids)).')';
			}

			if ($this->debug > 1) error_log(__FILE__ . __METHOD__ ."($path,,,$user,$id) calendar-multiget: ids=".implode(',',$ids));
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
		$handler = $this->_get_handler();
		$options['data'] = $handler->exportVCal(array($event),'2.0','PUBLISH');
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
		if($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));
		$return_no_access=true;	// as handled by importVCal anyway and allows it to set the status for participants
		$event = $this->_common_get_put_delete('PUT',$options,$id,$return_no_access);

		if (!is_null($event) && !is_array($event))
		{

		if($this->debug) error_log(__METHOD__.print_r($event,true).function_backtrace());
			return $event;
		}
		$handler = $this->_get_handler();
		if (!($cal_id = $handler->importVCal($options['content'],is_numeric($id) ? $id : -1,
			self::etag2value($this->http_if_match))))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) importVCal($options[content]) returned false");
			return '403 Forbidden';
		}

		header('ETag: '.$this->get_etag($cal_id));
		if (is_null($event) || !$return_no_access)	// let lightning think the event is added
		{
			if ($this->debug) error_log(__METHOD__."(,$id,$user) cal_id=$cal_id, is_null(\$event)=".(int)is_null($event));
			header('Location: '.$this->base_uri.self::get_path($cal_id));
			return '201 Created';
		}
		return true;
	}

	/**
	 * Handle delete request for an event
	 *
	 * If current user has no right to delete the event, but is an attendee, we reject the event for him.
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id)
	{
		$return_no_access=true;	// to allow to check if current use is a participant and reject the event for him
		if (!is_array($event = $this->_common_get_put_delete('DELETE',$options,$id,$return_no_access)) || !$return_no_access)
		{
			if (!$return_no_access)
			{
				$ret = isset($event['participants'][$this->bo->user]) &&
					$this->bo->set_status($event,$this->bo->user,'R') ? true : '403 Forbidden';
				if ($this->debug) error_log(__METHOD__."(,$id) return_no_access=$return_no_access, event[participants]=".array2string($event['participants']).", user={$this->bo->user} --> return $ret");
				return $ret;
			}
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
		//$cal_read = $this->bo->read($id,null,false,'server');//njv: do we actually get anything
		if ($this->debug > 1) error_log("bo-ical read  :$id:");//njv:
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
		$e_in = $entry;
		if (!is_array($entry))
		{
			$entry = $this->read($entry);
		}
		if (!$entry['id'] || !isset($entry['etag']) || !isset($entry['participants'])) {
			if ($this->debug > 1) error_log(__FILE__ . __METHOD__."($e_in): id=$entry[id], etag=$entry[etag], isset(participants)=".(int)isset($entry['participants']).", title=$entry[title]: id, etag or participants not set!!!");
		}
		$etag = $entry['id'].':'.$entry['etag'];
		// add a hash over the participants and their stati
		ksort($entry['participants']);	// create a defined order
		$etag .= ':'.md5(serialize($entry['participants']));
		//error_log(__FILE__ .__METHOD__ . "($entry[id] ($entry[etag]): $entry[title] --> etag=$etag");
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
		$props[] =	HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',$_SERVER['SCRIPT_NAME'].'/');
		// email of the current user, see caldav-sheduling draft
		$props[] =	HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-address-set','MAILTO:'.$GLOBALS['egw_info']['user']['email']);
		// supported components, currently only VEVENT
		$props[] =	$sc = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'supported-calendar-component-set',array(
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VEVENT')),
//			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VTODO')),	// not yet supported
		));

		return $props;
	}

	/**
	 * Get the handler and set the supported fields
	 *
	 * @return calendar_ical
	 */
	private function _get_handler()
	{
		$handler = new calendar_ical();
		$handler->setSupportedFields('GroupDAV',$this->agent);
		if ($this->debug > 1) error_log("ical Handler called:" . $this->agent);
		return $handler;
	}
}

