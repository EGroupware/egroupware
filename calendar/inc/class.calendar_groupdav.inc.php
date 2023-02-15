<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access: Calendar handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage caldav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * CalDAV/CardDAV/GroupDAV access: Calendar handler
 *
 * Permanent error_log() calls should use $this->caldav->log($str) instead, to be send to PHP error_log()
 * and our request-log (prefixed with "### " after request and response, like exceptions).
 *
 * @ToDo: new properties on calendars and it's ressources specially from sharing:
 * - for the invite property: 5.2.2 in https://trac.calendarserver.org/browser/CalendarServer/trunk/doc/Extensions/caldav-sharing.txt
 * - https://trac.calendarserver.org/browser/CalendarServer/trunk/doc/Extensions/caldav-schedulingchanges.txt
 */
class calendar_groupdav extends Api\CalDAV\Handler
{
	/**
	 * bo class of the application
	 *
	 * @var calendar_boupdate
	 */
	var $bo;

	/**
	 * vCalendar Instance for parsing
	 *
	 * @var Horde_Icalendar
	 */
	var $vCalendar;

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
	 * Does client understand exceptions to be included in VCALENDAR component of series master sharing its UID
	 *
	 * That also means no EXDATE for these exceptions!
	 *
	 * Setting it to false, should give the old behavior used in 1.6 (hopefully) no client needs that.
	 *
	 * @var boolean
	 */
	var $client_shared_uid_exceptions = true;

	/**
	 * Enable or disable Schedule-Tag handling:
	 * - return Schedule-Tag header in PUT response
	 * - update only status and alarms of calendar owner, if If-Schedule-Tag-Match header in PUT
	 *
	 * Disabling Schedule-Tag for iCal, as current implementation seems to create too much trouble :-(
	 * - iCal on OS X always uses If-Schedule-Tag-Match, even if other stuff in event is changed (eg. title)
	 * - iCal on iOS allways uses both If-Schedule-Tag-Match and If-Match (ETag)
	 * - Lighting 1.0 is NOT using it
	 *
	 * @var boolean
	 */
	var $use_schedule_tag = true;

	/**
	 * Are we using id, uid or caldav_name for the path/url
	 *
	 * Get's set in constructor to 'caldav_name' and self::$path_extension = ''!
	 */
	static $path_attr = 'id';

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

		$this->bo = new calendar_boupdate();
		$this->vCalendar = new Horde_Icalendar;

		// since 1.9.003 we allow clients to specify the URL when creating a new event, as specified by CalDAV
		if (version_compare($GLOBALS['egw_info']['apps']['calendar']['version'], '1.9.003', '>='))
		{
			self::$path_attr = 'caldav_name';
			self::$path_extension = '';
		}
	}

	/**
	 * Get grants of current user and app
	 *
	 * Overwritten to return rights modified for certain user-agents (eg. Outlook CalDAV Synchroniser) in the consturctor.
	 *
	 * @return array user-id => Api\Acl::ADD|Api\Acl::READ|Api\Acl::EDIT|Api\Acl::DELETE pairs
	 */
	public function get_grants()
	{
		return $this->bo->grants;
	}

/**
	 * Create the path for an event
	 *
	 * @param array|int $event
	 * @return string
	 */
	function get_path($event)
	{
		if (is_numeric($event) && self::$path_attr == 'id')
		{
			$name = $event;
		}
		else
		{
			if (!is_array($event)) $event = $this->bo->read($event);
			$name = $event[self::$path_attr];
		}
		$name .= self::$path_extension;
		//error_log(__METHOD__.'('.array2string($event).") path_attr='".self::$path_attr."', path_extension='".self::$path_extension."' returning ".array2string($name));
		return $name;
	}

	const PAST_LIMIT = 100;
	const FUTURE_LIMIT = 365;

	/**
	 * Handle propfind in the calendar folder
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id =''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user,$id='')
	{
		if ($this->debug)
		{
			error_log(__METHOD__."($path,".array2string($options).",,$user,$id)");
		}

		if ($options['root']['name'] == 'free-busy-query')
		{
			return $this->free_busy_report($path, $options, $user);
		}

		if (isset($_GET['download']))
		{
			$this->caldav->propfind_options['props'] = array(array(
				'xmlns' => Api\CalDAV::CALDAV,
				'name'  => 'calendar-data',
			));
		}

		// ToDo: add parameter to only return id & etag
		$filter = array(
			'users' => $user,
			'enum_recuring' => false,
			'daywise' => false,
			'date_format' => 'server',
			'no_total' => true,	// we need no total number of rows (saves extra query)
			'cfs' => array(),	// return custom-fields, as we use them to store X- attributes
		);
		foreach(array(
			'start' => $GLOBALS['egw_info']['user']['preferences']['groupdav']['calendar-past-limit'],
			'end' => $GLOBALS['egw_info']['user']['preferences']['groupdav']['calendar-future-limit'],
		) as $name => $value)
		{
			if (!is_numeric($value))
			{
				$value = $name == 'start' ? self::PAST_LIMIT : self::FUTURE_LIMIT;
			}
			$filter[$name] = $this->bo->now + 24*3600*($name == 'start' ? -1 : 1)*abs($value);
		}
		if ($this->client_shared_uid_exceptions)	// do NOT return (non-virtual) exceptions
		{
			$filter['query'] = array('cal_reference' => 0);
		}

		if ($path == '/calendar/')
		{
			$filter['filter'] = 'owner';
		}
		// scheduling inbox, shows only not yet accepted or rejected events
		elseif (substr($path,-7) == '/inbox/')
		{
			$filter['filter'] = 'unknown';
			$filter['start'] = $this->bo->now;	// only return future invitations
		}
		// ToDo: not sure what scheduling outbox is supposed to show, leave it empty for now
		elseif (substr($path,-8) == '/outbox/')
		{
			return true;
		}
		else
		{
			$filter['filter'] = 'default'; // not rejected
		}

		// process REPORT filters or multiget href's
		$nresults = null;
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options, $filter, $id, $nresults))
		{
			// return empty collection, as iCal under iOS 5 had problems with returning "404 Not found" status
			// when trying to request not supported components, eg. VTODO on a calendar collection
			return true;
		}
		if ($id) $path = dirname($path).'/';	// caldav_name get's added anyway in the callback

		if ($this->debug > 1)
		{
			error_log(__METHOD__."($path,,,$user,$id) filter=".array2string($filter));
		}

		// rfc 6578 sync-collection report: filter for sync-token is already set in _report_filters
		if ($options['root']['name'] == 'sync-collection')
		{
			// callback to query sync-token, after propfind_callbacks / iterator is run and
			// stored max. modification-time in $this->sync_collection_token
			$files['sync-token'] = array($this, 'get_sync_collection_token');
			$files['sync-token-params'] = array($path, $user);

			$this->sync_collection_token = null;

			$filter['order'] = 'cal_modified ASC';	// return oldest modifications first
			$filter['sync-collection'] = true;
			// no end-date / limit into the future, as unchanged entries would never be transferted later on
			unset($filter['end']);
		}

		if (isset($nresults))
		{
			unset($filter['no_total']);	// we need the total!
			$files['files'] = $this->propfind_generator($path, $filter, [], (int)$nresults);

			// hack to support limit with sync-collection report: events are returned in modified ASC order (oldest first)
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
			$files['files'] = $this->propfind_generator($path, $filter, $files['files']);
		}
		if (isset($_GET['download']))
		{
			$this->output_vcalendar($files['files']);
		}
		return true;
	}

	/**
	 * Download whole calendar as big ics file
	 *
	 * @param iterator|array $files
	 */
	function output_vcalendar($files)
	{
		// todo ETag logic with CTag to not download unchanged calendar again
		Api\Header\Content::type('calendar.ics', 'text/calendar');

		$n = 0;
		foreach($files as $file)
		{
			if (!$n++) continue;	// first entry is collection itself

			$icalendar = $file['props']['calendar-data']['val'];
			if (($start = strpos($icalendar, 'BEGIN:VEVENT')) !== false &&
				($end = strrpos($icalendar, 'END:VCALENDAR')) !== false)
			{
				if ($n === 2)
				{
					// skip X-CALENDARSERVER-ACCESS:CONFIDENTIAL, as it is on VCALENDAR not VEVENT level
					if (($x_calendarserver_access = strpos($icalendar, 'X-CALENDARSERVER-ACCESS:')) !== false)
					{
						echo substr($icalendar, 0, $x_calendarserver_access);
					}
					// skip timezones, as we would need to collect them from all events
					// ans most clients understand timezone by reference anyway
					elseif (($tz = strpos($icalendar, 'BEGIN:VTIMEZONE')) !== false)
					{
						echo substr($icalendar, 0, $tz);
					}
					else
					{
						echo substr($icalendar, 0, $start);
					}
				}
				echo substr($icalendar, $start, $end-$start);
			}
		}
		if ($icalendar && $end)
		{
			echo "END:VCALENDAR\n";
		}
		exit();
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

		$calendar_data = $this->caldav->prop_requested('calendar-data', Api\CalDAV::CALDAV, true);
		if (!is_array($calendar_data)) $calendar_data = false;	// not in allprop or autoindex

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
		$sync_collection = $filter['sync-collection'];

		for($chunk=0; $events =& $this->bo->search($filter+[
			'offset' => $chunk*self::CHUNK_SIZE,
			'num_rows' => self::CHUNK_SIZE,
		]); ++$chunk)
		{
			foreach($events as $event)
			{
				// remove event from requested multiget ids, to be able to report not found urls
				if (!empty($this->requested_multiget_ids) && ($k = array_search($event[self::$path_attr], $this->requested_multiget_ids)) !== false)
				{
					unset($this->requested_multiget_ids[$k]);
				}
				// sync-collection report: deleted entries need to be reported without properties, same for rejected or deleted invitations
				if ($sync_collection && ($event['deleted'] && !$event['cal_reference'] || in_array($event['participants'][$filter['users']][0], array('R','X'))))
				{
					if (++$yielded && isset($nresults) && $yielded > $nresults)
					{
						return;
					}
					yield ['path' => $path.urldecode($this->get_path($event))];
					continue;
				}
				$schedule_tag = null;
				$etag = $this->get_etag($event, $schedule_tag);

				//header('X-EGROUPWARE-EVENT-'.$event['id'].': '.$event['title'].': '.date('Y-m-d H:i:s',$event['start']).' - '.date('Y-m-d H:i:s',$event['end']));
				$props = array(
					'getcontenttype' => $this->agent != 'kde' ? 'text/calendar; charset=utf-8; component=VEVENT' : 'text/calendar',
					'getetag' => '"'.$etag.'"',
					'getlastmodified' => $event['modified'],
					// user and timestamp of creation or last modification of event, used in calendarserver only for shared calendars
					'created-by' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER, 'created-by',
						$this->_created_updated_by_prop($event['creator'], $event['created'])),
					'updated-by' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER, 'updated-by',
						$this->_created_updated_by_prop($event['modifier'], $event['modified'])),
				);
				if ($this->use_schedule_tag)
				{
					$props['schedule-tag'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV, 'schedule-tag', '"'.$schedule_tag.'"');
				}
				//error_log(__FILE__ . __METHOD__ . "Calendar Data : $calendar_data");
				if ($calendar_data)
				{
					$content = $this->iCal($event, $filter['users'],
						strpos($path, '/inbox/') !== false ? 'REQUEST' : null,
						!isset($calendar_data['children']['expand']) ? false :
							($calendar_data['children']['expand']['attrs'] ? $calendar_data['children']['expand']['attrs'] : true));
					$props['getcontentlength'] = bytes($content);
					$props['calendar-data'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-data',$content);
				}
				/* Calendarserver reports new events with schedule-changes: action: create, which iCal request
				 * adding it, unfortunately does not lead to showing the new event in the users inbox
				if (strpos($path, '/inbox/') !== false && $this->caldav->prop_requested('schedule-changes'))
				{
					$props['schedule-changes'] = Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'schedule-changes',array(
						Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'dtstamp',gmdate('Ymd\THis',$event['created']).'Z'),
						Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'action',array(
							Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'create',''),
						)),
					));
				}*/
				if (++$yielded && isset($nresults) && $yielded > $nresults)
				{
					return;
				}
				yield  $this->add_resource($path, $event, $props);
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
		if ($sync_collection)
		{
			$this->sync_collection_token = $event['modified'];
		}

		if ($this->debug)
		{
			error_log(__METHOD__."($path) took ".(microtime(true) - $starttime).
				" to return $yielded resources");
		}
	}

	/**
	 * Return Calendarserver:(created|updated)-by sub-properties for a given user and time
	 *
	 * <created-by xmlns='http://calendarserver.org/ns/'>
	 *  <first-name>Ralf</first-name>
	 *  <last-name>Becker</last-name>
	 *  <dtstamp>20121002T092006Z</dtstamp>
	 *  <href xmlns='DAV:'>mailto:farktronix@me.com</href>
	 * </created-by>
	 *
	 * @param int $user
	 * @param int $time
	 * @return array with subprops
	 */
	private function _created_updated_by_prop($user, $time)
	{
		$props = array();
		foreach(array(
			'first-name' => 'account_firstname',
			'last-name' => 'account_lastname',
			'href' => 'account_email',
		) as $prop => $name)
		{
			if ($user && ($val = $this->accounts->id2name($user, $name)))
			{
				$ns = Api\CalDAV::CALENDARSERVER;
				if ($prop == 'href')
				{
					$ns = '';
					$val = 'mailto:'.$val;
				}
				$props[$prop] = $ns ? Api\CalDAV::mkprop($ns, $prop, $val) : Api\CalDAV::mkprop($prop, $val);
			}
		}
		if ($time)
		{
			$props['dtstamp'] = Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER, 'dtstamp', gmdate('Ymd\\This\\Z', $time));
		}
		//error_log(__METHOD__."($user, $time) returning ".array2string($props));
		return $props ? $props : '';
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
	function _report_filters($options, &$cal_filters, $id, &$nresults)
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
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) comp-filter='{$filter['attrs']['name']}'");

						switch($filter['attrs']['name'])
						{
							case 'VTODO':
								return false;	// return nothing for now, todo: check if we can pass it on to the infolog handler
								// todos are handled by the infolog handler
								//$infolog_handler = new groupdav_infolog();
								//return $infolog_handler->propfind($options['path'],$options,$options['files'],$user,$method);
							case 'VCALENDAR':
							case 'VEVENT':
								break;			// that's our default anyway
						}
						break;
					case 'prop-filter':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) prop-filter='{$filter['attrs']['name']}'");
						$prop_filter = $filter['attrs']['name'];
						break;
					case 'text-match':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) text-match: $prop_filter='{$filter['data']}'");
						if (!isset($this->filter_prop2cal[strtoupper($prop_filter)]))
						{
							if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown property '$prop_filter' --> ignored");
						}
						else
						{
							$cal_filters['query'][$this->filter_prop2cal[strtoupper($prop_filter)]] = $filter['data'];
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
					 		$cal_filters['start'] = $this->vCalendar->_parseDateTime($filter['attrs']['start']);
				 		}
				 		if (!empty($filter['attrs']['end']))
				 		{
					 		$cal_filters['end']   = $this->vCalendar->_parseDateTime($filter['attrs']['end']);
				 		}
						break;
					default:
						if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown filter --> ignored");
						break;
				}
			}
			if (count($cal_filters) == $num_filters)	// no filters set --> restore default start and end time
			{
				$cal_filters['start'] = $cal_start;
				$cal_filters['end']   = $cal_end;
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
						$cal_filters['query'][] = 'cal_modified>'.(int)$sync_token;
						$cal_filters['filter'] = 'everything';	// to return deleted entries too
						// no standard time-range!
						unset($cal_filters['start']);
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
		//error_log(__FILE__ . __METHOD__ . "multiget of propfind:");
		$this->requested_multiget_ids = null;
		if ($options['root']['name'] == 'calendar-multiget' || $id)
		{
			// no standard time-range!
			unset($cal_filters['start']);
			unset($cal_filters['end']);

			if ($id)
			{
				$cal_filters['query'][self::$path_attr] = self::$path_extension ?
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
						if (($id = urldecode(array_pop($parts))))
						{
							$this->requested_multiget_ids[] = self::$path_extension ?
								basename($id,self::$path_extension) : $id;
						}
					}
				}
				$cal_filters['query'][self::$path_attr] = $this->requested_multiget_ids;
			}

			if ($this->debug > 1) error_log(__FILE__ . __METHOD__ ."($options[path],...,$id) calendar-multiget: ids=".implode(',',$ids).', cal_filters='.array2string($cal_filters));
		}
		return true;
	}

	/**
	 * Handle get request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		if (!is_array($event = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $event;
		}

		$options['data'] = $this->iCal($event, $user, strpos($options['path'], '/inbox/') !== false ? 'REQUEST' : null);
		$options['mimetype'] = 'text/calendar; charset=utf-8';
		header('Content-Encoding: identity');
		$schedule_tag = null;
		header('ETag: "'.$this->get_etag($event, $schedule_tag).'"');
		if ($this->use_schedule_tag)
		{
			header('Schedule-Tag: "'.$schedule_tag.'"');
		}
		return true;
	}

	/**
	 * Generate an iCal for the given event
	 *
	 * Taking into account virtual an real exceptions for recuring events
	 *
	 * @param array $event
	 * @param int $user =null account_id of calendar to display
	 * @param string $method =null eg. 'PUBLISH' for inbox, nothing anywhere else
	 * @param boolean|array $expand =false true or array with values for 'start', 'end' to expand recurrences
	 * @return string
	 */
	private function iCal(array $event,$user=null, $method=null, $expand=false)
	{
		static $handler = null;
		if (is_null($handler)) $handler = $this->_get_handler();

		if (!$user) $user = $GLOBALS['egw_info']['user']['account_id'];

		// only return alarms in own calendar, not other users calendars
		if ($user != $GLOBALS['egw_info']['user']['account_id'])
		{
			//error_log(__METHOD__.'('.array2string($event).", $user) clearing alarms");
			$event['alarm'] = array();
		}
		else if ($event['##videoconference'])
		{
			// Add video conference link to description for user's own calendar
			$avatar = new Api\Contacts\Photo("account:$user",
					// disable sharing links currently, as sharing links from a different EGroupware user destroy the session
					true);

			// wrap in try and catch in case videoconference throws exceptions. e.g. BBB server exceptions
			try {
				$link = EGroupware\Status\Videoconference\Call::genMeetingUrl($event['##videoconference'], [
					'name' => Api\Accounts::username($user),
					'email' => Api\Accounts::id2name($user, 'account_email'),
					'avatar' => (string)$avatar,
					'account_id' => $user,
					'cal_id' => $event['id'],
					'notify_only' => true
				], ['participants' =>array_filter($event['participants'], function($key){return is_numeric($key);}, ARRAY_FILTER_USE_KEY)], $event['start_date'], $event['end_date']);
			}catch (Exception $e)
			{
				//error_log(__METHOD__.'()'.$e->getMessage());
				// do nothing
			}

			$event['description'] = lang('Videoconference').":\n$link\n\n".$event['description'];
		}

		$events = array($event);

		// for recuring events we have to add the exceptions
		if ($this->client_shared_uid_exceptions && $event['recur_type'] && !empty($event['uid']))
		{
			if (is_array($expand))
			{
				if (isset($expand['start'])) $expand['start'] = $this->vCalendar->_parseDateTime($expand['start']);
				if (isset($expand['end'])) $expand['end'] = $this->vCalendar->_parseDateTime($expand['end']);
			}
			// pass in original event as master, as it has correct start-date even if first recurrence is an exception
			$events =& self::get_series($event['uid'], $this->bo, $expand, $user, $event);

			// as alarm is now only on next recurrence, set alarm from original event on master
			if ($event['alarm']) $events[0]['alarm'] = $event['alarm'];
		}
		elseif(!$this->client_shared_uid_exceptions && $event['reference'])
		{
			$events[0]['uid'] .= '-'.$event['id'];	// force a different uid
		}
		return $handler->exportVCal($events, '2.0', $method);
	}

	/**
	 * Get array with events of a series identified by its UID (master and all exceptions)
	 *
	 * Maybe that should be part of calendar_bo
	 *
	 * @param string $uid UID
	 * @param calendar_bo $bo =null calendar_bo object to reuse for search call
	 * @param boolean|array $expand =false true or array with values for 'start', 'end' to expand recurrences
	 * @param int $user =null account_id of calendar to display, to remove master, if current user does not participate in
	 * @param array $master =null use provided event as master to fix wrong start-date if first recurrence is an exception
	 * @return array
	 */
	private static function &get_series($uid,calendar_bo $bo=null, $expand=false, $user=null, $master=null)
	{
		if (is_null($bo)) $bo = new calendar_boupdate();

		$params = array(
			'query' => array('cal_uid' => $uid),
			'filter' => 'owner',  // return all possible entries
			'daywise' => false,
			'date_format' => 'server',
			'cfs' => array(),	// read cfs as we use them to store X- attributes
		);
		if (is_array($expand)) $params += $expand;

		if (!($events =& $bo->search($params)))
		{
			return array();
		}

		// find master, which is not always first event, eg. when first event is an exception
		$exceptions = array();
		foreach($events as $k => &$recurrence)
		{
			if ($recurrence['recur_type'])
			{
				if (!isset($master)) $master = $recurrence;
				$exceptions =& $master['recur_exception'];
				unset($events[$k]);
				break;
			}
		}
		// if recurring event starts in future behind horizont, nothing will be returned by bo::search()
		if (!isset($master)) $master = $bo->read($uid);

		foreach($events as $k => &$recurrence)
		{
			//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($uid)[$k]:" . array2string($recurrence));
			if ($master && $recurrence['reference'] && $recurrence['reference'] != $master['id'])
			{
				unset($events[$k]);
				continue;	// same uid, but references a different event or is own master
			}
			if (!$master || $recurrence['id'] != $master['id'])	// real exception
			{
				// user is NOT participating in this exception
				if ($user && !self::isParticipant($recurrence, $user))
				{
					// if he is NOT in master, delete this exception
					if (!$master || !self::isParticipant($master, $user))
					{
						unset($events[$k]);
						continue;
					}
					// otherwise mark him in this exception as rejected
					$recurrence['participants'][$user] = 'R';
				}
				//error_log('real exception: '.array2string($recurrence));
				// remove from masters recur_exception, as exception is include
				// at least Lightning "understands" EXDATE as exception from what's included
				// in the whole resource / VCALENDAR component
				// not removing it causes Lightning to remove the exception itself
				if (($e = array_search($recurrence['recurrence'],$exceptions)) !== false)
				{
					unset($exceptions[$e]);
				}
				continue;	// nothing to change
			}
			// add alarms from master to recurrences, as clients otherwise have no alarms on virtual exceptions
			if ($master && $master['alarm'])
			{
				$recurrence['alarm'] = $master['alarm'];
			}
			// now we need to check if this recurrence is an exception
			if (!$expand && $master && $master['participants'] == $recurrence['participants'])
			{
				//error_log('NO exception: '.array2string($recurrence));
				unset($events[$k]);	// no exception --> remove it
				continue;
			}
			// this is a virtual exception now (no extra event/cal_id in DB)
			//error_log('virtual exception: '.array2string($recurrence));
			$recurrence['recurrence'] = $recurrence['start'];
			if ($master) $recurrence['reference'] = $master['id'];
			$recurrence['recur_type'] = MCAL_RECUR_NONE;	// is set, as this is a copy of the master
			// not for included exceptions (Lightning): $master['recur_exception'][] = $recurrence['start'];
		}
		// only add master if we are not expanding and current user participates in master (and not just some exceptions)
		// also need to add master, if we have no (other) $events eg. birthday noone every accepted/rejected
		if (!$expand && $master && (!$user || self::isParticipant($master, $user) || !$events))
		{
			$events = array_merge(array($master), $events);
		}
		return $events;
	}

	/**
	 * Check if $user is a participant of given $event incl. group-invitations
	 *
	 * @param array $event
	 * @param int|string $user
	 * @return boolean
	 */
	public static function isParticipant(array $event, $user)
	{
		return isset($event['participants'][$user]) ||
			// for users and group-invitations we need to check memberships of $user too
			$user > 0 && array_intersect(array_keys($event['participants']),
				(array)$GLOBALS['egw']->accounts->memberships($user, true));
	}

	/**
	 * Handle put request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @param string $prefix =null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null,$prefix=null)
	{
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));

		if (!$prefix) $user = null;	// /infolog/ does not imply setting the current user (for new entries it's done anyway)

		// work around missing handling / racecondition in Lightning, if event already exists on server,
		// but Lightning has not yet synced with the server: Lightning just retries the PUT, not GETing the event
		// --> for now we ignore the If-None-Match: "*" as the lesser of two evils ;)
		if (self::get_agent() === 'lightning' && isset($_SERVER['HTTP_IF_NONE_MATCH']) &&
			in_array($_SERVER['HTTP_IF_NONE_MATCH'], array('*', '"*"')))
		{
			unset($_SERVER['HTTP_IF_NONE_MATCH']);
			$workaround_lightning_if_none_match = true;
		}

		// fix for iCal4OL using WinHTTP only supporting a certain header length
		if (isset($_SERVER['HTTP_IF_SCHEDULE']) && !isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']))
		{
			$_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH'] = $_SERVER['HTTP_IF_SCHEDULE'];
		}
		$return_no_access = true;	// as handled by importVCal anyway and allows it to set the status for participants
		$oldEvent = $this->_common_get_put_delete('PUT',$options,$id,$return_no_access,
			isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']));	// dont fail with 412 Precondition Failed in that case
		if (!is_null($oldEvent) && !is_array($oldEvent))
		{
			if ($this->debug) error_log(__METHOD__.': '.print_r($oldEvent,true).function_backtrace());
			return $oldEvent;
		}

		if (is_null($oldEvent) && ($user >= 0 && !$this->bo->check_perms(Acl::ADD, 0, $user) ||
			// if we require an extra invite grant, we fail if that does not exist (bind privilege is not given in that case)
			$this->bo->require_acl_invite && $user && $user != $GLOBALS['egw_info']['user']['account_id'] &&
				!$this->bo->check_acl_invite($user)))
		{
			// we have no add permission on this user's calendar
			// ToDo: create event in current users calendar and invite only $user
			if ($this->debug) error_log(__METHOD__."(,,$user) we have not enough rights on this calendar");
			return '403 Forbidden';
		}

		$handler = $this->_get_handler();
		$vCalendar = htmlspecialchars_decode($options['content']);
		$charset = null;
		if (!empty($options['content_type']))
		{
			$content_type = explode(';', $options['content_type']);
			if (count($content_type) > 1)
			{
				array_shift($content_type);
				foreach ($content_type as $attribute)
				{
					list($key, $value) = explode('=', $attribute);
					switch (strtolower(trim($key)))
					{
						case 'charset':
							$charset = strtoupper(trim($value));
					}
				}
			}
		}

		if (is_array($oldEvent))
		{
			$eventId = $oldEvent['id'];

			//client specified a CalDAV Scheduling schedule-tag AND an etag If-Match precondition
			if ($this->use_schedule_tag && isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']) &&
				isset($_SERVER['HTTP_IF_MATCH']))
			{
				if ($oldEvent['owner'] == $GLOBALS['egw_info']['user']['account_id'])
				{
					$this->caldav->log("Both If-Match and If-Schedule-Tag-Match header given: If-Schedule-Tag-Match ignored for event owner!");
					unset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']);
				}
				else
				{
					$this->caldav->log("Both If-Match and If-Schedule-Tag-Match header given: If-Schedule-Tag-Match takes precedence for participants!");
				}
			}
			// check CalDAV Scheduling schedule-tag precondition
			if ($this->use_schedule_tag && isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']))
			{
				$schedule_tag_match = $_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH'];
				if ($schedule_tag_match[0] == '"') $schedule_tag_match = substr($schedule_tag_match, 1, -1);
				$schedule_tag = null;
				$this->get_etag($oldEvent, $schedule_tag);

				if ($schedule_tag_match !== $schedule_tag)
				{
					if ($this->debug) error_log(__METHOD__."(,,$user) schedule_tag missmatch: given '$schedule_tag_match' != '$schedule_tag'");
					// honor Prefer: return=representation for 412 too (no need for client to explicitly reload)
					$this->check_return_representation($options, $id, $user);
					return '412 Precondition Failed';
				}
			}
			// if no edit-rights (aka no organizer), update only attendee stuff: status and alarms
			if (!$this->check_access(Acl::EDIT, $oldEvent) ||
				// we ignored Lightings If-None-Match: "*" --> do not overwrite event, just change status
				!empty($workaround_lightning_if_none_match))
			{
				$user_and_memberships = $GLOBALS['egw']->accounts->memberships($user, true);
				$user_and_memberships[] = $user;
				if (!array_intersect(array_keys($oldEvent['participants']), $user_and_memberships) &&
					// above can be true, if current user is not in master but just a recurrence
					(!$oldEvent['recur_type'] || !($series = self::get_series($oldEvent['uid'], $this->bo))))
				{
					if ($this->debug) error_log(__METHOD__."(,,$user) user $user is NOT an attendee!");
					return '403 Forbidden';
				}
				// update only participant status and alarms of current user
				if (($events = $handler->icaltoegw($vCalendar)))
				{
					$modified = 0;
					$master = null;
					foreach($events as $n => $event)
					{
						// for recurrances of event series, we need to read correct recurrence (or if series master is no first event)
						if ($event['recurrence'] || $n && !$event['recurrence'] || isset($series))
						{
							// first try reading (virtual and real) exceptions
							if (!isset($series))
							{
								$series = self::get_series($event['uid'], $this->bo);
								//foreach($series as $s => $sEvent) error_log("series[$s]: ".array2string($sEvent));
							}
							foreach($series as $oldEvent)
							{
								if ($oldEvent['recurrence'] == $event['recurrence']) break;
							}
							// if no exception found, check if it might be just a recurrence (no exception)
							if ($event['recurrence'] && $oldEvent['recurrence'] != $event['recurrence'])
							{
								if (!($oldEvent = $this->bo->read($eventId, $event['recurrence'], true)) ||
									// virtual exceptions have recurrence=0 and recur_date=recurrence (series master or real exceptions have recurence=0)
									!($oldEvent['recur_date'] == $event['recurrence'] || !$event['recurrence'] && !$oldEvent['recurrence']))
								{
									// if recurrence not found --> log it and continue with other recurrence
									$this->caldav->log(__METHOD__."(,,$user) could NOT find recurrence=$event[recurrence]=".Api\DateTime::to($event['recurrence']).' of event series! event='.array2string($event));
									continue;
								}
							}
						}
						if ($this->debug) error_log(__METHOD__."(, $id, $user, '$prefix') eventId=$eventId ($oldEvent[id]), user=$user, old-status='{$oldEvent['participants'][$user]}', new-status='{$event['participants'][$user]}', recurrence=$event[recurrence]=".Api\DateTime::to($event['recurrence']).", event=".array2string($event));
						if (isset($event['participants']) && isset($event['participants'][$user]) &&
							$event['participants'][$user] !== $oldEvent['participants'][$user])
						{
							if (!$this->bo->set_status($oldEvent['id'], $user, $event['participants'][$user],
								// real (not virtual) exceptions use recurrence 0 in egw_cal_user.cal_recurrence!
								$recurrence = $eventId == $oldEvent['id'] ? $event['recurrence'] : 0))
							{
								if ($this->debug) error_log(__METHOD__."(,,$user) failed to set_status($oldEvent[id], $user, '{$event['participants'][$user]}', $recurrence=".Api\DateTime::to($recurrence).')');
								return '403 Forbidden';
							}
							else
							{
								++$modified;
								if ($this->debug) error_log(__METHOD__."() set_status($oldEvent[id], $user, {$event['participants'][$user]} , $recurrence=".Api\DateTime::to($recurrence).')');
							}
						}
						// import alarms, if given and changed
						if ((array)$event['alarm'] !== (array)$oldEvent['alarm'])
						{
							$event['id'] = $oldEvent['id'];
							if (isset($master) && $event['id'] == $master['id'])	// only for pseudo exceptions
							{
								$modified += $handler->sync_alarms($event, (array)$oldEvent['alarm'], $user, $master);
							}
							else
							{
								$modified += $handler->sync_alarms($event, (array)$oldEvent['alarm'], $user);
							}
						}
						if (!isset($master) && !$event['recurrence']) $master = $event;
					}
					if (!$modified)	// NO modififictions, or none we understood --> log it and return Ok: "204 No Content"
					{
						$this->caldav->log(__METHOD__."(,,$user) NO changes for current user events=".array2string($events).', old-event='.array2string($oldEvent));
					}
					$this->put_response_headers($eventId, $options['path'], '204 No Content', self::$path_attr == 'caldav_name');

					return '204 No Content';
				}
				if ($this->debug && !isset($events)) error_log(__METHOD__."(,,$user) only schedule-tag given for event without participants (only calendar owner) --> handle as regular PUT");
			}
			if ($return_no_access)
			{
				$retval = true;
			}
			else
			{
				$retval = '204 No Content';

				// lightning will pop up the alarm, as long as the Sequence (etag) does NOT change
				// --> update the etag alone, if user has no edit rights
				if ($this->agent == 'lightning' && !$this->check_access(Acl::EDIT, $oldEvent) &&
					isset($oldEvent['participants'][$GLOBALS['egw_info']['user']['account_id']]))
				{
					// just update etag in database
					$GLOBALS['egw']->db->update($this->bo->so->cal_table,'cal_etag=cal_etag+1',array(
						'cal_id' => $eventId,
					),__LINE__,__FILE__,'calendar');
				}
			}
		}
		// schedule tag with deleted event should not create a new entry, therefore returning 404 Not Found
		elseif (!isset($oldEvent) && isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']))
		{
			return '404 Not Found';
		}
		else
		{
			// new entry
			$eventId = -1;
			$retval = '201 Created';
		}

		if (!($cal_id = $handler->importVCal($vCalendar, $eventId,
			self::etag2value($this->http_if_match), false, 0, $this->caldav->current_user_principal, $user, $charset, $id)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) eventId=$eventId: importVCal('$options[content]') returned ".array2string($cal_id));
			if ($eventId && $cal_id === false)
			{
				// ignore import failures
				$cal_id = $eventId;
				$retval = true;
			}
			elseif ($cal_id === 0)	// etag failure
			{
				// honor Prefer: return=representation for 412 too (no need for client to explicitly reload)
				$this->check_return_representation($options, $id, $user);
				return '412 Precondition Failed';
			}
			else
			{
				return '403 Forbidden';
			}
		}

		// send evtl. necessary respose headers: Location, etag, ...
		$this->put_response_headers($cal_id, $options['path'], $retval, self::$path_attr == 'caldav_name');

		return $retval;
	}

	/**
	 * Handle post request for a schedule entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function post(&$options,$id,$user=null)
	{
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));

		$vCalendar = htmlspecialchars_decode($options['content']);
		$charset = null;
		if (!empty($options['content_type']))
		{
			$content_type = explode(';', $options['content_type']);
			if (count($content_type) > 1)
			{
				array_shift($content_type);
				foreach ($content_type as $attribute)
				{
					list($key, $value) = explode('=', $attribute);
					switch (strtolower(trim($key)))
					{
						case 'charset':
							$charset = strtoupper(trim($value));
					}
				}
			}
		}

		if (substr($options['path'],-8) == '/outbox/')
		{
			if (preg_match('/^METHOD:REQUEST(\r\n|\r|\n)(.*)^BEGIN:VFREEBUSY/ism', $vCalendar))
			{
				if ($user != $GLOBALS['egw_info']['user']['account_id'])
				{
					error_log(__METHOD__."() freebusy request only allowed to own outbox!");
					return '403 Forbidden';
				}
				// do freebusy request
				return $this->outbox_freebusy_request($vCalendar, $charset, $user, $options);
			}
			else
			{
				// POST to deliver an invitation, containing http headers:
				// Originator: mailto:<organizer-email>
				// Recipient: mailto:<attendee-email>
				// --> currently we simply ignore these posts, as EGroupware does it's own notifications based on user preferences
				return '204 No Content';
			}
		}
		if (preg_match('/^METHOD:(PUBLISH|REQUEST)(\r\n|\r|\n)(.*)^BEGIN:VEVENT/ism', $options['content']))
		{
			$handler = $this->_get_handler();
			if (($foundEvents = $handler->iCalSearch($vCalendar, null, false, $charset)))
			{
				$id = array_shift($foundEvents);
				list($eventId) = explode(':', $id);

				if (!($cal_id = $handler->importVCal($vCalendar, $eventId, null,
					false, 0, $this->caldav->current_user_principal, $user, $charset)))
				{
					if ($this->debug) error_log(__METHOD__."() importVCal($eventId) returned false");
				}
				header('ETag: "'.$this->get_etag($eventId).'"');
			}
		}
		return true;
	}

	/**
	 * Handle outbox freebusy request
	 *
	 * @param string $ical
	 * @param string $charset of ical
	 * @param int $user account_id of owner
	 * @param array &$options
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	protected function outbox_freebusy_request($ical, $charset, $user, array &$options)
	{
		unset($options);	// not used, but required by function signature

		$vcal = new Horde_Icalendar();
		if (!$vcal->parsevCalendar($ical, 'VCALENDAR', $charset))
		{
			return '400 Bad request';
		}
		$version = $vcal->getAttribute('VERSION');

		//echo $ical."\n";

		$handler = $this->_get_handler();
		$handler->setSupportedFields('groupdav');
		$handler->calendarOwner = $handler->user = 0;	// to NOT default owner/organizer to something
		if (!($component = $vcal->getComponent(0)) ||
			!($event = $handler->vevent2egw($component, $version, $handler->supportedFields, $this->caldav->current_user_principal, 'Horde_Icalendar_Vfreebusy')))
		{
			return '400 Bad request';
		}
		if ($event['owner'] != $user)
		{
			$this->caldav->log(__METHOD__."('$ical',,$user) ORGANIZER is NOT principal!");
			return '403 Forbidden';
		}
		//print_r($event);
		$organizer = $component->getAttribute('ORGANIZER');
		$attendees = (array)$component->getAttribute('ATTENDEE');
		// X-CALENDARSERVER-MASK-UID specifies to exclude given event from busy-time
		$mask_uid = $component->getAttributeDefault('X-CALENDARSERVER-MASK-UID', null);

		header('Content-type: text/xml; charset=UTF-8');

		$xml = new XMLWriter;
		$xml->openMemory();
		$xml->setIndent(true);
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElementNs('C', 'schedule-response', Api\CalDAV::CALDAV);

		foreach(array_keys($event['participants'] ?? []) as $uid)
		{
			$xml->startElementNs('C', 'response', null);

			$xml->startElementNs('C', 'recipient', null);
			$xml->writeElementNs('D', 'href', 'DAV:', $attendee=array_shift($attendees));
			$xml->endElement();	// recipient

			$xml->writeElementNs('C', 'request-status', null, '2.0;Success');
			$xml->writeElementNs('C', 'calendar-data', null,
				$handler->freebusy($uid, $event['end'], true, 'utf-8', $event['start'], 'REPLY', array(
					'UID' => $event['uid'],
					'ORGANIZER' => $organizer,
					'ATTENDEE' => $attendee,
				)+(empty($mask_uid) || !is_string($mask_uid) ? array() : array(
					'X-CALENDARSERVER-MASK-UID' => $mask_uid,
				))));

			$xml->endElement();	// response
		}
		$xml->endElement();	// schedule-response
		$xml->endDocument();
		echo $xml->outputMemory();

		return true;
	}

	/**
	 * Handle free-busy-query report
	 *
	 * @param string $path
	 * @param array $options
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function free_busy_report($path,$options,$user)
	{
		unset($path);	// unused, but required by function signature
		if (!$this->bo->check_perms(calendar_bo::ACL_FREEBUSY, 0, $user))
		{
			return '403 Forbidden';
		}
		foreach($options['other'] as $filter)
		{
			if ($filter['name'] == 'time-range')
			{
				$start = $this->vCalendar->_parseDateTime($filter['attrs']['start']);
				$end = $this->vCalendar->_parseDateTime($filter['attrs']['end']);
			}
		}
		$handler = $this->_get_handler();
		header('Content-Type: text/calendar');
		echo $handler->freebusy($user, $end, true, 'utf-8', $start, 'REPLY', array());

		exit();	// otherwise we get a 207 multistatus, not 200 Ok
	}

	/**
	 * Return priviledges for current user, default is read and read-current-user-privilege-set
	 *
	 * Reimplemented to add read-free-busy and schedule-deliver privilege
	 *
	 * @param string $path path of collection
	 * @param int $user =null owner of the collection, default current user
	 * @return array with privileges
	 */
	public function current_user_privileges($path, $user=null)
	{
		$privileges = parent::current_user_privileges($path, $user);
		//error_log(__METHOD__."('$path', $user) parent gave ".array2string($privileges));

		if ($this->bo->check_perms(calendar_bo::ACL_FREEBUSY, 0, $user))
		{
			$privileges['read-free-busy'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV, 'read-free-busy', '');

			if (substr($path, -8) == '/outbox/' && $this->bo->check_acl_invite($user))
			{
				$privileges['schedule-send'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV, 'schedule-send', '');
			}
		}
		if (substr($path, -7) == '/inbox/' && $this->bo->check_acl_invite($user))
		{
			$privileges['schedule-deliver'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV, 'schedule-deliver', '');
		}
		// remove bind privilege on other users or groups calendars, if calendar Api\Config require_acl_invite is set
		// and current user has no invite grant
		if ($user && $user != $GLOBALS['egw_info']['user']['account_id'] && isset($privileges['bind']) &&
			!$this->bo->check_acl_invite($user))
		{
			unset($privileges['bind']);
		}
		//error_log(__METHOD__."('$path', $user) returning ".array2string($privileges));
		return $privileges;
	}

	/**
	 * Fix event series with exceptions, called by calendar_ical::importVCal():
	 *	a) only series master = first event got cal_id from URL
	 *	b) exceptions need to be checked if they are already in DB or new
	 *	c) recurrence-id of (real not virtual) exceptions need to be re-added to master
	 *
	 * @param array &$events
	 */
	static function fix_series(array &$events)
	{
		$bo = new calendar_boupdate();

		// get array with orginal recurrences indexed by recurrence-id
		$org_recurrences = $exceptions = array();
		foreach(self::get_series($events[0]['uid'],$bo) as $k => $event)
		{
			if (!$k) $master = $event;
			if ($event['recurrence'])
			{
				$org_recurrences[$event['recurrence']] = $event;
			}
		}

		// assign cal_id's to already existing recurrences and evtl. re-add recur_exception to master
		foreach($events as $k => &$recurrence)
		{
			if (!$recurrence['recurrence'])
			{
				// master
				$recurrence['id'] = $master['id'];
				$master =& $events[$k];
				continue;
			}

			// from now on we deal with exceptions
			$org_recurrence = isset($recurrence['recurrence']) ? $org_recurrences[$recurrence['recurrence']] : null;
			if (isset($org_recurrence))	// already existing recurrence
			{
				//error_log(__METHOD__.'() setting id #'.$org_recurrence['id']).' for '.$recurrence['recurrence'].' = '.date('Y-m-d H:i:s',$recurrence['recurrence']);
				$recurrence['id'] = $org_recurrence['id'];

				// re-add (non-virtual) exceptions to master's recur_exception
				if ($recurrence['id'] != $master['id'])
				{
					//error_log(__METHOD__.'() re-adding recur_exception '.$recurrence['recurrence'].' = '.date('Y-m-d H:i:s',$recurrence['recurrence']));
					$exceptions[] = $recurrence['recurrence'];
				}
				// remove recurrence to be able to detect deleted exceptions
				unset($org_recurrences[$recurrence['recurrence']]);
			}
		}
		$master['recur_exception'] = array_merge($exceptions, $master['recur_exception']);

		// delete not longer existing recurrences
		foreach($org_recurrences as $org_recurrence)
		{
			if ($org_recurrence['id'] != $master['id'])	// non-virtual recurrence
			{
				//error_log(__METHOD__.'() deleting #'.$org_recurrence['id']);
				$bo->delete($org_recurrence['id']);	// might fail because of permissions
			}
			else	// virtual recurrence
			{
				//error_log(__METHOD__.'() delete virtual exception '.$org_recurrence['recurrence'].' = '.date('Y-m-d H:i:s',$org_recurrence['recurrence']));
				$bo->update_status($master, $org_recurrence, $org_recurrence['recurrence']);
			}
		}
		//foreach($events as $n => $event) error_log(__METHOD__." $n after: ".array2string($event));
	}

	/**
	 * Handle delete request for an event
	 *
	 * If current user has no right to delete the event, but is an attendee, we reject the event for him.
	 *
	 * @todo remove (non-virtual) exceptions, if series master gets deleted
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id,$user)
	{
		if (strpos($options['path'], '/inbox/') !== false)
		{
			return true;	// simply ignore DELETE in inbox for now
		}
		$return_no_access = true;	// to allow to check if current use is a participant and reject the event for him
		$event = $this->_common_get_put_delete('DELETE',$options,$id,$return_no_access);

		// no event found --> 404 Not Found
		if (!is_array($event))
		{
			$ret = $event;
			error_log("_common_get_put_delete('DELETE', ..., $id) user=$user, return_no_access=".array2string($return_no_access)." returned ".array2string($event));
		}
		// Work around problems with Outlook CalDAV Synchronizer (https://caldavsynchronizer.org/)
		// - sends a DELETE to reject a meeting request --> deletes event for all participants, if user has delete rights from the organizer
		// --> only set status for everyone else but the organizer
		// OR no delete rights and deleting an event in someone else calendar --> check if calendar owner is a participant --> reject him
		elseif ((!$return_no_access || (self::get_agent() === 'caldavsynchronizer' && $event['owner'] != $user)) &&
			// check if current user has edit rights for calendar of $user, can change status / reject invitation for him
			$this->bo->check_perms(Acl::EDIT, 0, $user))
		{
			// check if user is a participant or one of the groups he is a member of --> reject the meeting request
			$ret = '403 Forbidden';
			$memberships = $GLOBALS['egw']->accounts->memberships($user, true);
			foreach(array_keys($event['participants']) as $uid)
			{
				if ($user == $uid || in_array($uid, $memberships))
				{
					$this->bo->set_status($event, $user, 'R');
					$ret = true;
					break;
				}
			}
		}
		// current user has no delete rights for event --> reject invitation, if he is a participant
		elseif (!$return_no_access)
		{
			// check if current user is a participant or one of the groups he is a member of --> reject the meeting request
			$ret = '403 Forbidden';
			$memberships = $GLOBALS['egw']->accounts->memberships($this->bo->user, true);
			foreach(array_keys($event['participants']) as $uid)
			{
				if ($this->bo->user == $uid || in_array($uid, $memberships))
				{
					$this->bo->set_status($event, $this->bo->user, 'R');
					$ret = true;
					break;
				}
			}
		}
		// we have delete rights on the event and (try to) delete it
		else
		{
			$ret = $this->bo->delete($event['id']);
			if (!$ret) { error_log("delete($event[id]) returned FALSE"); $ret = '400 Failed to delete event';}
		}
		if ($this->debug) error_log(__METHOD__."(,$id) return_no_access=$return_no_access, event[participants]=".array2string(is_array($event)?$event['participants']:null).", user={$this->bo->user} --> return ".array2string($ret));
		return $ret;
	}

	/**
	 * Read an entry
	 *
	 * We have to make sure to not return or even consider in read deleted events, as the might have
	 * the same UID and/or caldav_name as not deleted events and would block access to valid entries
	 *
	 * @param string|id $id
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		if (strpos($column=self::$path_attr,'_') === false) $column = 'cal_'.$column;

		$event = $this->bo->read(array($column => $id, 'cal_deleted IS NULL', 'cal_reference=0'), null, true, 'server');
		if ($event) $event = array_shift($event);	// read with array as 1. param, returns an array of events!

		if (!($retval = $this->bo->check_perms(calendar_bo::ACL_FREEBUSY,$event, 0, 'server')) &&
			// above can be true, if current user is not in master but just a recurrence
			(!$event['recur_type'] || !($events = self::get_series($event['uid'], $this->bo))))
		{
			if ($this->debug > 0) error_log(__METHOD__."($id) no READ or FREEBUSY rights returning ".array2string($retval));
			return $retval;
		}
		if (!$this->bo->check_perms(Acl::READ, $event, 0, 'server'))
		{
			$this->bo->clear_private_infos($event, array($this->bo->user, $event['owner']));
		}
		// handle deleted events, as not existing
		if ($event['deleted']) $event = null;

		if ($this->debug > 1) error_log(__METHOD__."($id) returning ".array2string($event));

		return $event;
	}

	/**
	 * Update etag, ctag and sync-token to reflect changed attachments
	 *
	 * @param array|string|int $entry array with entry data from read, or id
	 */
	public function update_tags($entry)
	{
		if (!is_array($entry)) $entry = $this->read($entry);

		$this->bo->update($entry, true);
	}

	/**
	 * Query ctag for calendar
	 *
	 * @return string
	 */
	public function getctag($path,$user)
	{
		$ctag = $this->bo->get_ctag($user,$path == '/calendar/' ? 'owner' : 'default'); // default = not rejected

		if ($this->debug > 1) error_log(__FILE__.'['.__LINE__.'] '.__METHOD__. "($path)[$user] = $ctag");

		return $ctag;
	}

	/**
	 * Get the etag for an entry
	 *
	 * @param array|int $entry array with event or cal_id
	 * @param string $schedule_tag =null on return schedule-tag
	 * @return string|boolean string with etag or false
	 */
	function get_etag($entry, &$schedule_tag=null)
	{
		$etag = $this->bo->get_etag($entry, $schedule_tag, $this->client_shared_uid_exceptions);

		//error_log(__METHOD__ . "($entry[id] ($entry[etag]): $entry[title] --> etag=$etag");
		return $etag;
	}

	/**
	 * Send response-headers for a PUT (or POST with add-member query parameter)
	 *
	 * Reimplemented to send
	 *
	 * @param int|array $entry id or array of new created entry
	 * @param string $path
	 * @param int|string $retval
	 * @param boolean $path_attr_is_name =true true: path_attr is ca(l|rd)dav_name, false: id (GroupDAV needs Location header)
	 * @param string $etag =null etag, to not calculate it again (if != null)
	 * @param string $prefix =''
	 */
	function put_response_headers($entry, $path, $retval, $path_attr_is_name=true, $etag=null, $prefix='')
	{
		$schedule_tag = null;
		if (!isset($etag)) $etag = $this->get_etag($entry, $schedule_tag);

		if ($this->use_schedule_tag)
		{
			header('Schedule-Tag: "'.$schedule_tag.'"');
		}
		parent::put_response_headers($entry, $path, $retval, $path_attr_is_name, $etag, $prefix);
	}

	/**
	 * Check if user has the neccessary rights on an event
	 *
	 * @param int $acl Acl::READ, Acl::EDIT or Acl::DELETE
	 * @param array|int $event event-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$event)
	{
		if ($acl == Acl::READ)
		{
			// we need at least calendar_bo::ACL_FREEBUSY to get some information
			$acl = calendar_bo::ACL_FREEBUSY;
		}
		return $this->bo->check_perms($acl,$event,0,'server');
	}

	/**
	 * Add extra properties for calendar collections
	 *
	 * @param array $props regular props by the Api\CalDAV handler
	 * @param string $displayname
	 * @param string $base_uri =null base url of handler
	 * @param int $user =null account_id of owner of current collection
	 * @param string $path =null path of the collection
	 * @return array
	 */
	public function extra_properties(array $props, $displayname, $base_uri=null, $user=null, $path=null)
	{
		unset($base_uri);	// unused, but required by function signature
		if (!isset($props['calendar-description']))
		{
			// default calendar description: can be overwritten via PROPPATCH, in which case it's already set
			$props['calendar-description'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-description',$displayname);
		}
		$supported_components = array(
			Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'comp',array('name' => 'VEVENT')),
		);
		// outbox supports VFREEBUSY too, it is required from OS X iCal to autocomplete locations
		if (substr($path,-8) == '/outbox/')
		{
			$supported_components[] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'comp',array('name' => 'VFREEBUSY'));
		}
		$props['supported-calendar-component-set'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,
			'supported-calendar-component-set',$supported_components);
		// supported reports
		$props['supported-report-set'] = array(
			'calendar-query' => Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-query',''))))),
			'calendar-multiget' => Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-multiget',''))))),
			'free-busy-query' => Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'free-busy-query',''))))),
		);
		// rfc 6578 sync-collection report for everything but outbox
		// only if "delete-prevention" is switched on (deleted entries get marked deleted but not actualy deleted
		if (strpos($path, '/outbox/') === false)
		{
			$props['supported-report-set']['sync-collection'] = Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					Api\CalDAV::mkprop('sync-collection','')))));
		}
		$props['supported-calendar-data'] = Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'supported-calendar-data',array(
			Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-data', array('content-type' => 'text/calendar', 'version'=> '2.0')),
			Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-data', array('content-type' => 'text/x-calendar', 'version'=> '1.0'))));

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
	 * @return calendar_ical
	 */
	private function _get_handler()
	{
		$handler = new calendar_ical();
		$handler->setSupportedFields('GroupDAV',$this->agent);
		$handler->supportedFields['attachments'] = true;	// enabling attachments
		if ($this->debug > 1) error_log("ical Handler called: " . $this->agent);
		return $handler;
	}

	/**
	 * Return calendars/addressbooks shared from other users with the current one
	 *
	 * return array account_id => account_lid pairs
	 */
	function get_shared()
	{
		$shared = array();
		$pref = $GLOBALS['egw_info']['user']['preferences']['groupdav']['calendar-home-set'];
		$calendar_home_set = $pref ? explode(',', $pref) : array();
		// replace symbolic id's with real nummeric id's
		foreach(array(
			'G' => $GLOBALS['egw_info']['user']['account_primary_group'],
		) as $sym => $id)
		{
			if (($key = array_search($sym, $calendar_home_set)) !== false)
			{
				$calendar_home_set[$key] = $id;
			}
		}
		foreach(ExecMethod('calendar.calendar_bo.list_cals') as $entry)
		{
			$id = $entry['grantor'];
			if ($id && $GLOBALS['egw_info']['user']['account_id'] != $id &&	// no current user
				(in_array('A',$calendar_home_set) || in_array((string)$id,$calendar_home_set)) &&
				is_numeric($id) && ($owner = $this->accounts->id2name($id)))
			{
				$shared[$id] = 'calendar-'.$owner;
			}
		}
		// shared locations and resources
		if ($GLOBALS['egw_info']['user']['apps']['resources'])
		{
			foreach(array('locations','resources') as $res)
			{
				if (($pref = $GLOBALS['egw_info']['user']['preferences']['groupdav']['calendar-home-set-'.$res]))
				{
					foreach(explode(',', $pref) as $res_id)
					{
						$is_location = $res == 'locations';
						$shared['r'.$res_id] = str_replace('s/', '-', Api\CalDAV\Principals::resource2name($res_id, $is_location));
					}
				}
			}
		}
		return $shared;
	}

	/**
	 * Return appliction specific settings
	 *
	 * @param array $hook_data
	 * @return array of array with settings
	 */
	static function get_settings($hook_data)
	{
		$calendars = array(
			'A'	=> lang('All'),
			'G'	=> lang('Primary Group'),
		);
		if (!isset($hook_data['setup']) && in_array($hook_data['type'], array('user', 'group')))
		{
			$user = $hook_data['account_id'];
			foreach (calendar_bo::list_calendars($user) as $entry)
			{
				$calendars[$entry['grantor']] = $entry['name'];
			}
			if ($user > 0) unset($calendars[$user]);	// skip current user
		}

		$settings = array();
		$settings['calendar-home-set'] = array(
			'type'   => 'multiselect',
			'label'  => 'Calendars to sync in addition to personal calendar',
			'name'   => 'calendar-home-set',
			'help'   => lang('Only supported by a few fully conformant clients (eg. from Apple). If you have to enter a URL, it will most likly not be suppored!').'<br/>'.lang('They will be sub-folders in users home (%1 attribute).','CalDAV "calendar-home-set"'),
			'values' => $calendars,
			'xmlrpc' => True,
			'admin'  => False,
		);
		$settings['calendar-past-limit'] = array(
			'type'   => 'integer',
			'label'  => lang('How many days to sync in the past (default %1)', self::PAST_LIMIT),
			'name'   => 'calendar-past-limit',
			'help'   => 'Clients not explicitly stating a limit get limited to these many days. A too high limit may cause problems with some clients.',
			'xmlrpc' => True,
			'admin'  => False,
		);
		$settings['calendar-future-limit'] = array(
			'type'   => 'integer',
			'label'  => lang('How many days to sync in the future (default %1)', self::FUTURE_LIMIT),
			'name'   => 'calendar-future-limit',
			'help'   => 'Clients not explicitly stating a limit get limited to these many days. A too high limit may cause problems with some clients.',
			'xmlrpc' => True,
			'admin'  => False,
		);

		// allow to subscribe to resources
		if ($GLOBALS['egw_info']['user']['apps']['resources'] && ($all_resources = Api\CalDAV\Principals::get_resources()))
		{
			$resources = $locations = array();
			foreach($all_resources as $resource)
			{
				if (Api\CalDAV\Principals::resource_is_location($resource))
				{
					$locations[$resource['res_id']] = $resource['name'];
				}
				else
				{
					$resources[$resource['res_id']] = $resource['name'];
				}
			}
			foreach(array(
				'locations' => $locations,
				'resources' => $resources,
			) as $name => $options)
			{
				if ($options)
				{
					natcasesort($options);
					$settings['calendar-home-set-'.$name] = array(
						'type'   => 'multiselect',
						'label'  => lang('%1 to sync', lang($name == 'locations' ? 'Location calendars' : 'Resource calendars')),
						'no_lang'=> true,
						'name'   => 'calendar-home-set-'.$name,
						'help'   => lang('Only supported by a few fully conformant clients (eg. from Apple). If you have to enter a URL, it will most likly not be suppored!').'<br/>'.lang('They will be sub-folders in users home (%1 attribute).','CalDAV "calendar-home-set"'),
						'values' => $options,
						'xmlrpc' => True,
						'admin'  => False,
					);
				}
			}
		}
		return $settings;
	}
}