<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access: Principals handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

// explicit import classes without namespace
use resources_bo;
use calendar_bo;

/**
 * EGroupware: GroupDAV access: groupdav/caldav/carddav principals handlers
 *
 * First-level properties used in this class should have the property name as their key,
 * to allow to check if required properties are set!
 * Principals::add_principal() converts simple associative props (name => value pairs)
 * to name => Api\CalDAV(name, value) pairs.
 *
 * Permanent error_log() calls should use $this->caldav->log($str) instead, to be send to PHP error_log()
 * and our request-log (prefixed with "### " after request and response, like exceptions).
 */
class Principals extends Handler
{
	/**
	 * Instance of resources_bo
	 *
	 * @var resources_bo
	 */
	private static $resources;

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		parent::__construct($app, $caldav);

		if (!isset(self::$resources)) self::$resources = new resources_bo();
	}

	/**
	 * Supported reports and methods implementing them or what to return eg. "501 Not Implemented"
	 *
	 * @var array
	 */
	public $supported_reports = array(
		'acl-principal-prop-set' => array(
			'method' => 'acl_principal_prop_set_report',
		),
		/*'principal-match' => array(
			// an other report calendarserver announces
		),*/
		'principal-property-search' => array(
			'method' => 'principal_property_search_report',
		),
		'principal-search-property-set' => array(
			'method' => 'principal_search_property_set_report',
		),
		'expand-property' => array(
			'method' => 'expand_property_report',
		),
		/* seems only be used 'til OS X 10.6, no longer in 10.7
		'addressbook-findshared' => array(
			'ns' => Api\CalDAV::ADDRESSBOOKSERVER,
			'method' => 'addressbook_findshared_report',
		),*/
	);

	/**
	 * Generate supported-report-set property
	 *
	 * Currently we return all reports independed of path
	 *
	 * @param string $path eg. '/principals/'
	 * @param array $reports =null
	 * @return array Api\CalDAV::mkprop('supported-report-set', ...)
	 */
	protected function supported_report_set($path, array $reports=null)
	{
		unset($path);	// not used, but required by function signature

		if (is_null($reports)) $reports = $this->supported_reports;

		$supported = array();
		foreach($reports as $name => $data)
		{
			$supported[$name] = Api\CalDAV::mkprop('supported-report',array(
				Api\CalDAV::mkprop('report',array(
					!$data['ns'] ? Api\CalDAV::mkprop($name, '') :
						Api\CalDAV::mkprop($data['ns'], $name, '')))));
		}
		return $supported;
	}

	/**
	 * Handle propfind request for an application folder
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user)
	{
		if (($report = isset($_GET['report']) ? $_GET['report'] : $options['root']['name']) && $report != 'propfind')
		{
			$report_data = $this->supported_reports[$report];
			if (isset($report_data) && ($method = $report_data['method']) && method_exists($this, $method))
			{
				return $this->$method($path, $options, $files, $user);
			}
			$this->caldav->log(__METHOD__."('$path', ".array2string($options).",, $user) not implemented report, returning 501 Not Implemented");
			return '501 Not Implemented';
		}
		list(,,$type,$name,$rest) = explode('/',$path,5);
		// /principals/users/$name/
		//            /users/$name/calendar-proxy-read/
		//            /users/$name/calendar-proxy-write/
		//            /groups/$name/
		//            /resources/$resource/
		//            /locations/$resource/
		//            /__uids__/$uid/.../

		switch($type)
		{
			case 'users':
				$files['files'] = $this->propfind_users($name,$rest,$options);
				break;
			case 'groups':
				$files['files'] = $this->propfind_groups($name,$rest,$options);
				break;
			case 'resources':
				$files['files'] = $this->propfind_resources($name,$rest,$options,false);
				break;
			case 'locations':
				$files['files'] = $this->propfind_resources($name,$rest,$options,true);
				break;
			/*case '__uids__':
				$files['files'] = $this->propfind_uids($name,$rest,$options);
				break;*/
			case '':
				$files['files'] = $this->propfind_principals($options);
				break;
			default:
				return '404 Not Found';
		}
		if (!is_array($files['files']))
		{
			return $files['files'];
		}
		return true;
	}

	/**
	 * Handle addressbook-findshared Addressbookserver report
	 *
	 * Required for Apple Addressbook on Mac (addressbook-findshared REPORT)
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	/* Seens only to work 'til OS X 10.6, no longer in 10.7 AND response seems NOT correct for 10.6
	function addressbook_findshared_report($path,$options,&$files,$user)
	{
		error_log(__METHOD__."('$path', ".array2string($options).",, $user)");
		$files['files'] = array();
		$files['files'][] = $this->add_collection($path);	// will be removed for reports
		foreach($this->get_shared_addressbooks() as $path)
		{
			$files['files'][] = $f = $this->add_collection($path.'addressbook/', array(
				'resourcetype' => array(Api\CalDAV::mkprop(Api\CalDAV::CARDDAV,'addressbook','')),
			));
			error_log(__METHOD__."() ".array2string($f));
		}
		return true;
	}*/

	/**
	 * Handle expand-property reports (all from http://calendarserver.org/ns/ namespace) seen from newer iCal on OS X
	 * - expanded-group-member-set
	 * - expanded-group-membership
	 * - calendar-proxy-read-for
	 * - calendar-proxy-write-for
	 *
	 * Example requests:
	 *
	 * REPORT /egw/groupdav.php/principals/groups/groupname/ HTTP/1.1
	 * User-Agent: CalendarStore/5.0.3 (1204.1); iCal/5.0.3 (1605.3); Mac OS X/10.7.4 (11E53)
	 *
	 * <?xml version="1.0" encoding="UTF-8"?>
	 * <A:expand-property xmlns:A="DAV:">
	 *   <A:property name="expanded-group-member-set" namespace="http://calendarserver.org/ns/">
	 *     <A:property name="calendar-user-address-set" namespace="urn:ietf:params:xml:ns:caldav"/>
	 *     <A:property name="last-name" namespace="http://calendarserver.org/ns/"/>
	 *     <A:property name="calendar-user-type" namespace="urn:ietf:params:xml:ns:caldav"/>
	 *     <A:property name="principal-URL" namespace="DAV:"/>
	 *     <A:property name="displayname" namespace="DAV:"/>
	 *     <A:property name="record-type" namespace="http://calendarserver.org/ns/"/>
	 *     <A:property name="first-name" namespace="http://calendarserver.org/ns/"/>
	 *   </A:property>
	 * </A:expand-property>
	 *
	 * REPORT /egw/groupdav.php/principals/users/username/ HTTP/1.1
	 * User-Agent: CalendarStore/5.0.3 (1204.1); iCal/5.0.3 (1605.3); Mac OS X/10.7.4 (11E53)
	 *
	 * <?xml version="1.0" encoding="UTF-8"?>
	 * <A:expand-property xmlns:A="DAV:">
	 *   <A:property name="calendar-proxy-read-for" namespace="http://calendarserver.org/ns/">
	 *     <A:property name="displayname" namespace="DAV:"/>
	 *     <A:property name="calendar-user-address-set" namespace="urn:ietf:params:xml:ns:caldav"/>
	 *     <A:property name="email-address-set" namespace="http://calendarserver.org/ns/"/>
	 *   </A:property>
	 *   <A:property name="calendar-proxy-write-for" namespace="http://calendarserver.org/ns/">
	 *     <A:property name="displayname" namespace="DAV:"/>
	 *     <A:property name="calendar-user-address-set" namespace="urn:ietf:params:xml:ns:caldav"/>
	 *     <A:property name="email-address-set" namespace="http://calendarserver.org/ns/"/>
	 *   </A:property>
	 * </A:expand-property>
	 *
	 * REPORT /egroupware/groupdav.php/principals/users/username/ HTTP/1.1
	 * User-Agent: DAVKit/4.0.3 (732.2); CalendarStore/4.0.4 (997.7); iCal/4.0.4 (1395.7); Mac OS X/10.6.8 (10K549)
	 *
	 * <?xml version="1.0" encoding="utf-8" ?>
	 * <x0:expand-property xmlns:x0="DAV:">
	 *  <x0:property name="calendar-proxy-write-for" namespace="http://calendarserver.org/ns/">
	 *   <x0:property name="displayname"/>
	 *   <x0:property name="principal-URL"/>
	 *   <x0:property name="calendar-user-address-set" namespace="urn:ietf:params:xml:ns:caldav"/>
	 *  </x0:property>
	 *  <x0:property name="calendar-proxy-read-for" namespace="http://calendarserver.org/ns/">
	 *   <x0:property name="displayname"/>
	 *   <x0:property name="principal-URL"/>
	 *   <x0:property name="calendar-user-address-set" namespace="urn:ietf:params:xml:ns:caldav"/>
	 *  </x0:property>
	 * </x0:expand-property>
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function expand_property_report($path,&$options,&$files,$user)
	{
		//error_log(__METHOD__."('$path', ".array2string($options).",, $user)");
		$requested_props = $options['other'];
		while(($requested_prop = array_shift($requested_props)))
		{
			if ($requested_prop['name'] != 'property' || $requested_prop['depth'] != 1) continue;

			$prop_ns = $requested_prop['attrs']['namespace'];
			$prop_name = $requested_prop['attrs']['name'];
			$prop_path = $path;
			// calendarserver has some special property-names for expansion
			switch($prop_name)
			{
				case 'calendar-proxy-read-for':
				case 'calendar-proxy-write-for':
					$prop_path = $path . substr($prop_name, 0, -4).'/';
					$prop_name = 'group-member-set';
					$prop_ns = Api\CalDAV::DAV;
					break;

				case 'expanded-group-member-set':
				case 'expanded-group-membership':
					// remove 'expanded-' prefix
					$prop_name = substr($prop_name, 9);
					$prop_ns = Api\CalDAV::DAV;
					break;
			}
			// run regular propfind for requested property
			$options['depth'] = '0';
			$options['root']['name'] = 'propfind';
			$options['props'] = array(array(
				'name' => $prop_name,
				'xmlns' => $prop_ns,
			));
			$prop_files = array();
			$this->caldav->options = $options;	// also modify global variable
			if (empty($prop_name) || $this->propfind($prop_path, $options, $prop_files, $user) !== true)
			{
				$this->caldav->log('### NO expand-property report for '.$requested_prop['attrs']['name']);
				continue;
			}
			// find prop to expand
			foreach($prop_files['files'][0]['props'] as $expand_prop)
			{
				if ($expand_prop['name'] === $prop_name) break;
			}
			if ($expand_prop['name'] !== $prop_name || !is_array($expand_prop['val']) ||
				$expand_prop['val'] && $expand_prop['val'][0]['name'] !== 'href')
			{
				$this->caldav->log('### NO expand-property report for '.$requested_prop['attrs']['name'].' ('.$prop_name.')');
				continue;
			}

			// requested properties of each href are in depth=2 properties
			// set them as regular propfind properties to $options['props']
			$options2 = array('props' => 'all');
			while(($prop = array_shift($requested_props)) && $prop['depth'] >= 2)
			{
				if ($prop['name'] == 'property' && $prop['depth'] == 2)
				{
					if (!is_array($options2['props']))	// is "all" initially
					{
						$options2['props'] = array();
					}
					$options2['props'][] = array(
						'name' => $prop['attrs']['name'],
						'xmlns' => isset($prop['attrs']['namespace']) ? $prop['attrs']['namespace'] : $prop['xmlns'],
					);
				}
			}
			// put back evtl. read top-level property
			if ($prop && $prop['depth'] == 1) array_unshift($requested_props, $prop);
			$this->caldav->options = $options2;	// also modify global variable

			// run regular profind to get requested 2.-level properties for each href
			foreach($expand_prop['val'] as $key => &$prop_val)
			{
				list(,$expand_path) = explode($this->caldav->base_uri, $prop_val['val']);
				//error_log(__METHOD__."('$path', ..., $user) calling propfind('$expand_path', ".array2string($options2).', '.array2string($prop_val).", $user)");
				if ($this->propfind($expand_path, $options2, $prop_val, $user) !== true || !isset($prop_val['files'][0]))
				{
					// do NOT return that path, eg. perms give rights but account_selection="groupmembers" forbids access
					unset($expand_prop['val'][$key]);
					continue;
				}
				$prop_val = $prop_val['files'][0];
			}
			// setting top-level name and namespace
			$expand_prop['ns'] = $requested_prop['attrs']['namespace'];
			$expand_prop['name'] = $requested_prop['attrs']['name'];
			// setting 2.-level props, so Api\CalDAV can filter unwanted ones out or mark missing ones
			$expand_prop['props'] = $options2['props'];
			// add top-level path and property
			$files['files'][0]['path'] = $path;
			$files['files'][0]['props'][] = $expand_prop;
		}
		// we can use 'all' here, as we return only requested properties
		$options['props'] = 'all';

		return true;
	}

	/**
	 * Handle principal-property-search report
	 *
	 * Current implementation runs a full infinity propfind and filters out not matching resources.
	 *
	 * Eg. from Lightning on the principal collection /principals/:
	 * <D:principal-property-search xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
	 *   <D:property-search>
	 *     <D:prop>
	 *       <C:calendar-home-set/>
	 *     </D:prop>
	 *     <D:match>/egroupware/groupdav.php</D:match>
	 *   </D:property-search>
	 *   <D:prop>
	 *     <C:calendar-home-set/>
	 *     <C:calendar-user-address-set/>
	 *     <C:schedule-inbox-URL/>
	 *     <C:schedule-outbox-URL/>
	 *   </D:prop>
	 * </D:principal-property-search>
	 *
	 * Hack for Lightning: it requests calendar-home-set matching our root (/egroupware/groupdav.php),
	 * but interprets returning all principals (all have a matching calendar-home-set) as NOT supporting CalDAV scheduling
	 * --> search only current user's principal, when Lightning searches for calendar-home-set
	 *
	 * Example from iOS iCal autocompleting invitees using calendarserver-principal-property-search WebDAV extension
	 * <x0:principal-property-search xmlns:x2="urn:ietf:params:xml:ns:caldav" xmlns:x1="http://calendarserver.org/ns/" xmlns:x0="DAV:" test="anyof">
	 *   <x0:property-search>
	 *     <x0:prop>
	 *       <x0:displayname/>
	 *     </x0:prop>
	 *     <x0:match match-type="contains">beck</x0:match>
	 *   </x0:property-search>
	 *   <x0:property-search>
	 *     <x0:prop>
	 *       <x1:email-address-set/>
	 *     </x0:prop>
	 *     <x0:match match-type="starts-with">beck</x0:match>
	 *   </x0:property-search>
	 *   <x0:property-search>
	 *     <x0:prop>
	 *       <x1:first-name/>
	 *     </x0:prop>
	 *     <x0:match match-type="starts-with">beck</x0:match>
	 *   </x0:property-search>
	 *   <x0:property-search>
	 *     <x0:prop>
	 *       <x1:last-name/>
	 *     </x0:prop>
	 *     <x0:match match-type="starts-with">beck</x0:match>
	 *   </x0:property-search>
	 *   <x0:prop>
	 *     <x1:first-name/>
	 *     <x1:last-name/>
	 *     <x0:displayname/>
	 *     <x1:email-address-set/>
	 *     <x2:calendar-user-address-set/>
	 *     <x1:record-type/>
	 *     <x0:principal-URL/>
	 *   </x0:prop>
	 * </x0:principal-property-search>
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function principal_property_search_report($path,&$options,&$files,$user)
	{
		//error_log(__METHOD__."('$path', ".array2string($options).",, $user)");

		// cant find the test attribute to root principal-property-search element in WebDAV rfc, but iPhones use it ...
		$anyof = !empty($options['root']['attrs']['test']) && $options['root']['attrs']['test'] == 'anyof';	// "allof" (default) or "anyof"

		// parse property-search prop(s) contained in $options['other']
		foreach($options['other'] as $n => $prop)
		{
			switch($prop['name'])
			{
				case 'apply-to-principal-collection-set':	// optinal prop to apply search on principal-collection-set == '/principals/'
					$path = '/principals/';
					break;
				case 'property-search':
					$property_search = $n;	// should be 1
					break;
				case 'prop':
					if (isset($property_search))
					{
						$search_props[$property_search] = array();
					}
					break;
				case 'match':
					if (isset($property_search) && is_array($search_props[$property_search]))
					{
						$search_props[$property_search]['match'] = $prop['data'];
						// optional match-type: "contains" (default), "starts-with", "ends-with", "equals"
						$search_props[$property_search]['match-type'] = $prop['attrs']['match-type'];
					}
					break;
				default:
					if (isset($property_search) && $search_props[$property_search] === array())
					{
						$search_props[$property_search] = $prop;
					}
					break;
			}
		}
		if (!isset($property_search) || !$search_props || !isset($search_props[$property_search]['match']))
		{
			$this->caldav->log(__METHOD__."('$path',...) Could not parse options[other]=".array2string($options['other']));
			return '400 Bad Request';
		}
		// make sure search property is included in toplevel props (can be missing and defaults to property-search/prop's)
		foreach($search_props as $prop)
		{
			if (!$this->caldav->prop_requested($prop['name'], $prop['xmlns']))
			{
				$options['props'][] = array(
					'name' => $prop['name'],
					'xmlns' => $prop['xmlns'],
				);
			}
			// Hack for Lightning prior 1.1.1 (TB 9): it requests calendar-home-set matching our root (/egroupware/groupdav.php),
			// but interprets returning all principals (all have a matching calendar-home-set) as NOT supporting CalDAV scheduling
			// --> search only current user's principal
			if ($prop['name'] == 'calendar-home-set' && stripos($_SERVER['HTTP_USER_AGENT'], 'Lightning') !== false &&
				substr($search_props[0]['match'],-13) == '/groupdav.php')
			{
				$path = '/principals/users/'.$GLOBALS['egw_info']['user']['account_lid'].'/';
				$this->caldav->log('Enabling hack for Lightning prior 1.1.1 for searching calendar-home-set matching "/groupdav.php": limiting search to '.$path);
			}
		}
		// check type attribute to limit search on a certain tree
		if (isset($options['root']['attrs']['type']))
		{
			switch($options['root']['attrs']['type'])
			{
				case 'INDIVIDUAL':
					$path = '/principals/users/';
					break;
				case 'GROUP':
					$path = '/principals/groups/';
					break;
				case 'ROOM':
					$path = '/principals/locations/';
					break;
				case 'RESOURCE':
					$path = '/principals/resources/';
					break;
			}
		}
		// run "regular" propfind
		$options['other'] = array();
		$options['root']['name'] = 'propfind';
		// search all principals, but not the proxys, rfc requires depth=0, but to search all principals
		$options['depth'] = 5 - count(explode('/', $path)); // /principals/ --> 3

		if (($ret = $this->propfind($path, $options, $files, $user)) !== true)
		{
			return $ret;
		}
		// now filter out not matching "files"
		foreach($files['files'] as $n => $resource)
		{
			if (count(explode('/', $resource['path'])) < 5)	// hack to only return principals, not the collections itself
			{
				unset($files['files'][$n]);
				continue;
			}
			// match with $search_props
			$matches = 0;
			foreach($search_props as $search_prop)
			{
				// search resource for $search_prop
				foreach($resource['props'] as $prop)
				{
					if ($prop['name'] === $search_prop['name']) break;
				}
				if ($prop['name'] === $search_prop['name'])	// search_prop NOT found
				{
					foreach((array)$prop['val'] as $value)
					{
						if (is_array($value)) $value = $value['val'];	// eg. href prop
						if (self::match($value, $search_prop['match'], $search_prop['match-type']) !== false)	// prop does match
						{
							++$matches;
							//error_log("$matches: $resource[path]: $search_prop[name]=".array2string($prop['name'] !== $search_prop['name'] ? null : $prop['val'])." does match '$search_prop[match]'");
							break;
						}
					}
				}
				if ($anyof && $matches || $matches == count($search_props))
				{
					//error_log("$resource[path]: anyof=$anyof, $matches matches --> keep");
					continue 2;	// enough matches --> keep
				}
			}
			//error_log("$resource[path]: anyof=$anyof, $matches matches --> skip");
			unset($files['files'][$n]);
		}
		return $ret;
	}

	/**
	 * Match using $match_type
	 *
	 * It's not defined in WebDAV ACL, but CardDAV:text-match seems similar
	 *
	 * @param string $value value to test
	 * @param string $match criteria/sub-string
	 * @param string $match_type ='contains' 'starts-with', 'ends-with' or 'equals'
	 */
	private static function match($value, $match, $match_type='contains')
	{
		switch($match_type)
		{
			case 'equals':
				return $value === $match;

			case 'starts-with':
				return stripos($value, $match) === 0;

			case 'ends-with':
				return stripos($value, $match) === strlen($value) - strlen($match);

			case 'contains':
			default:
				return stripos($value, $match) !== false;
		}
	}

	/**
	 * Handle principal-search-property-set report
	 *
	 * REPORT /principals/ HTTP/1.1
	 * <?xml version="1.0" encoding="utf-8" ?>
	 * <x0:principal-search-property-set xmlns:x0="DAV:"/>
	 *
	 * <?xml version='1.0' encoding='UTF-8'?>
	 * <principal-search-property-set xmlns='DAV:'>
	 *   <principal-search-property>
	 *     <prop>
	 *       <displayname/>
	 *     </prop>
	 *     <description xml:lang='en'>Display Name</description>
	 *   </principal-search-property>
	 *   <principal-search-property>
	 *     <prop>
	 *       <email-address-set xmlns='http://calendarserver.org/ns/'/>
	 *     </prop>
	 *     <description xml:lang='en'>Email Addresses</description>
	 *   </principal-search-property>
	 *   <principal-search-property>
	 *     <prop>
	 *       <last-name xmlns='http://calendarserver.org/ns/'/>
	 *     </prop>
	 *     <description xml:lang='en'>Last Name</description>
	 *   </principal-search-property>
	 *   <principal-search-property>
	 *     <prop>
	 *       <calendar-user-type xmlns='urn:ietf:params:xml:ns:caldav'/>
	 *     </prop>
	 *     <description xml:lang='en'>Calendar User Type</description>
	 *   </principal-search-property>
	 *   <principal-search-property>
	 *     <prop>
	 *       <first-name xmlns='http://calendarserver.org/ns/'/>
	 *     </prop>
	 *     <description xml:lang='en'>First Name</description>
	 *   </principal-search-property>
	 *   <principal-search-property>
	 *     <prop>
	 *       <calendar-user-address-set xmlns='urn:ietf:params:xml:ns:caldav'/>
	 *     </prop>
	 *     <description xml:lang='en'>Calendar User Address Set</description>
	 *   </principal-search-property>
	 * </principal-search-property-set>
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function principal_search_property_set_report($path,&$options,&$files,$user)
	{
		unset($path, $options, $files, $user);	// not used, but required by function signature

		static $search_props = array(
			// from iOS iCal
			'displayname' => 'Display Name',
			'email-address-set' => array('description' => 'Email Addresses', 'ns' => Api\CalDAV::CALENDARSERVER),
			'last-name' => array('description' => 'Last Name', 'ns' => Api\CalDAV::CALENDARSERVER),
			'calendar-user-type' => array('description' => 'Calendar User Type', 'ns' => Api\CalDAV::CALDAV),
			'first-name' => array('description' => 'First Name', 'ns' => Api\CalDAV::CALENDARSERVER),
			'calendar-user-address-set' => array('description' => 'Calendar User Address Set', 'ns' => Api\CalDAV::CALDAV),
			// Lightning
			'calendar-home-set' => array('description' => 'Calendar Home Set', 'ns' => Api\CalDAV::CALENDARSERVER),
			// others, we generally support all properties of the principal
		);
		header('Content-type: text/xml; charset=UTF-8');

		$xml = new \XMLWriter;
		$xml->openMemory();
		$xml->setIndent(true);
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElementNs(null, 'principal-search-property-set', 'DAV:');

		foreach($search_props as $name => $data)
		{
			$xml->startElement('principal-search-property');
			$xml->startElement('prop');
			if (is_array($data) && !empty($data['ns']))
			{
				$xml->writeElementNs(null, $name, $data['ns']);
			}
			else
			{
				$xml->writeElement($name);
			}
			$xml->endElement();	// prop

			$xml->startElement('description');
			$xml->writeAttribute('xml:lang', 'en');
			$xml->text(is_array($data) ? $data['description'] : $data);
			$xml->endElement();	// description

			$xml->endElement();	// principal-search-property
		}
		$xml->endElement();	// principal-search-property-set
		$xml->endDocument();
		echo $xml->outputMemory();

		exit;
	}

	/**
	 * Handle principal-property-search report
	 *
	 * Current implementation runs a full (infinity) propfind, as we have principals only once in the principal collection.
	 *
	 * Example from WebDAV ACL rfc 3744:
	 * REPORT /index.html HTTP/1.1
	 * Host: www.example.com
	 * Content-Type: text/xml; charset="utf-8"
	 * Content-Length: xxxx
	 * Depth: 0
	 *
	 * <?xml version="1.0" encoding="utf-8" ?>
	 * <D:acl-principal-prop-set xmlns:D="DAV:">
	 *   <D:prop>
	 *     <D:displayname/>
	 *   </D:prop>
	 * </D:acl-principal-prop-set>
	 *
	 * Response is a multistatus as for a propfind. Seems the only diverence is, that prinipals are only returned once
	 * (even if they exists multiple times), only principals get returned (eg. not the collections they are in) AND the depth 0.
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function acl_principal_prop_set_report($path,&$options,&$files,$user)
	{
		//error_log(__METHOD__."('$path', ".array2string($options).",, $user)");

		// run "regular" propfind
		$options['root']['name'] = 'propfind';
		// search all principals, but not the proxys, rfc requires depth=0, but to search all principals
		$options['depth'] = 5 - count(explode('/', $path)); // /principals/ --> 3

		// we need the resourcetype to only return principals
		$options['props']['resourcetype'] = array(
			'name' => 'resourcetype',
			'xmlns' => 'DAV:',
		);
		if (($ret = $this->propfind($path, $options, $files, $user)) !== true)
		{
			return $ret;
		}
		// now filter out not matching "files"
		foreach($files['files'] as $n => $resource)
		{
			foreach($resource['props']['resourcetype']['val'] as $prop)
			{
				if ($prop['name'] == 'principal') continue 2;
			}
			unset($files['files'][$n]);	// not a principal --> do not return
		}
		// we should not return it
		unset($options['props']['resourcetype']);

		return $ret;
	}

	/**
	 * Do propfind in /pricipals/users
	 *
	 * @param string $name name of account or empty
	 * @param string $rest rest of path behind account-name
	 * @param array $options
	 * @return array|string array with files or HTTP error code
	 */
	protected function propfind_users($name,$rest,array $options)
	{
		//error_log(__METHOD__."($name,$rest,".array2string($options).')');
		if (empty($name))
		{
			$files = array();
			// add /pricipals/users/ entry
			$files[] = $this->add_collection('/principals/users/', array('displayname' => lang('Users')));

			if ($options['depth'])
			{
				if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
					!isset($GLOBALS['egw_info']['user']['apps']['admin']))
				{
					if (($account = $this->accounts->read($GLOBALS['egw_info']['user']['account_id'])))
					{
						$files[] = $this->add_account($account);
					}
				}
				else
				{
					// add all users (account_selection == groupmembers is handled by accounts->search())
					foreach($this->accounts->search(array('type' => 'accounts','order' => 'account_lid')) as $account)
					{
						$files[] = $this->add_account($account);
					}
				}
			}
		}
		else
		{
			if (!($id = $this->accounts->name2id($name,'account_lid','u')) ||
				!($account = $this->accounts->read($id)) ||
				!$this->accounts->visible($name))
			{
				$this->caldav->log(__METHOD__."('$name', ...) account '$name' NOT found OR not visible to you (check account-selection preference)!");
				return '404 Not Found';
			}
			while (substr($rest,-1) == '/')
			{
				$rest = substr($rest,0,-1);
			}
			switch((string)$rest)
			{
				case '':
					$files[] = $this->add_account($account);
					if ($options['depth'])
					{
						$files[] = $this->add_proxys('users/'.$account['account_lid'], 'calendar-proxy-read');
						$files[] = $this->add_proxys('users/'.$account['account_lid'], 'calendar-proxy-write');
					}
					break;
				case 'calendar-proxy-read':
				case 'calendar-proxy-write':
					$files = array();
					$files[] = $this->add_proxys('users/'.$account['account_lid'], $rest);
					break;
				default:
					return '404 Not Found';
			}
		}
		return $files;
	}

	/**
	 * Do propfind in /pricipals/groups
	 *
	 * @param string $name name of group or empty
	 * @param string $rest rest of path behind account-name
	 * @param array $options
	 * @return array|string array with files or HTTP error code
	 */
	protected function propfind_groups($name,$rest,array $options)
	{
		//echo "<p>".__METHOD__."($name,$rest,".array2string($options).")</p>\n";
		if (empty($name))
		{
			$files = array();
			// add /pricipals/users/ entry
			$files[] = $this->add_collection('/principals/groups/', array('displayname' => lang('Groups')));

			if ($options['depth'])
			{
				// only show own groups, if account-selection is groupmembers or none
				$type = in_array($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'], array('groupmembers','none')) ?
					'owngroups' : 'groups';

				// add all groups or only membergroups
				foreach($this->accounts->search(array('type' => $type,'order' => 'account_lid')) as $account)
				{
					$files[] = $this->add_group($account);
				}
			}
		}
		else
		{
			if (!($id = $this->accounts->name2id($name,'account_lid','g')) ||
				!($account = $this->accounts->read($id)) ||
				// do NOT allow other groups, if account-selection is groupmembers or none
				in_array($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'], array('groupmembers','none')) &&
				!in_array($account['account_id'], $this->accounts->memberships($GLOBALS['egw_info']['user']['account_id'],true)))
			{
				return '404 Not Found';
			}
			while (substr($rest,-1) == '/')
			{
				$rest = substr($rest,0,-1);
			}
			switch((string)$rest)
			{
				case '':
					$files[] = $this->add_group($account);
					if ($options['depth'])
					{
						$files[] = $this->add_proxys('groups/'.$account['account_lid'], 'calendar-proxy-read');
						$files[] = $this->add_proxys('groups/'.$account['account_lid'], 'calendar-proxy-write');
					}
					break;
				case 'calendar-proxy-read':
				case 'calendar-proxy-write':
					$files = array();
					$files[] = $this->add_proxys('groups/'.$account['account_lid'], $rest);
					break;
				default:
					return '404 Not Found';
			}
		}
		return $files;
	}

	/**
	 * Get shared addressbooks of current user
	 *
	 * @return array with path relative to base URI (without addressbook postfix!)
	 */
	protected function get_shared_addressbooks()
	{
		$addressbooks = array();
		$ab_home_set = $GLOBALS['egw_info']['user']['preferences']['groupdav']['addressbook-home-set'];
		if (empty($ab_home_set)) $ab_home_set = 'P';	// personal addressbook
		$addressbook_home_set = explode(',', $ab_home_set);
		// replace symbolic id's with real nummeric id's
		foreach(array(
			'P' => $GLOBALS['egw_info']['user']['account_id'],
			'G' => $GLOBALS['egw_info']['user']['account_primary_group'],
			'U' => '0',
		) as $sym => $id)
		{
			if (($key = array_search($sym, $addressbook_home_set)) !== false)
			{
				$addressbook_home_set[$key] = $id;
			}
		}
		if (in_array('O',$addressbook_home_set))	// "all in one" from groupdav.php/addressbook/
		{
			$addressbooks[] = '/';
		}
		foreach(array_keys($GLOBALS['egw']->contacts->get_addressbooks(Api\Acl::READ)) as $id)
		{
			if ((in_array('A',$addressbook_home_set) || in_array((string)$id,$addressbook_home_set)) &&
				is_numeric($id) && ($owner = $this->accounts->id2name($id)))
			{
				$addressbooks[] = '/'.$owner.'/';
			}
		}
		return $addressbooks;
	}

	/**
	 * Add collection of a single account to a collection
	 *
	 * @param array $account
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_account(array $account)
	{
		$addressbooks = $calendars = array();
		// since we "show" shared addressbooks and calendars in the user home, no need for individualiced homes
		$addressbooks[] = Api\CalDAV::mkprop('href',
			$this->base_uri.'/'.$account['account_lid'].'/');
		$calendars[] = Api\CalDAV::mkprop('href',
			$this->base_uri.'/'.$account['account_lid'].'/');

		$displayname = Api\Translation::convert($account['account_fullname'], Api\Translation::charset(),'utf-8');

		return $this->add_principal('users/'.$account['account_lid'], array(
			'getetag' => $this->get_etag($account),
			'displayname' => $displayname,
			// CalDAV
			'calendar-home-set' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-home-set',$calendars),
			// CalDAV scheduling
			'schedule-outbox-URL' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'schedule-outbox-URL',array(
				Api\CalDAV::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/outbox/'))),
			'schedule-inbox-URL' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'schedule-inbox-URL',array(
				Api\CalDAV::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/inbox/'))),
			'calendar-user-address-set' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-user-address-set',array(
				Api\CalDAV::mkprop('href','mailto:'.$account['account_email']),
				Api\CalDAV::mkprop('href',$this->base_uri(true).'/principals/users/'.$account['account_lid'].'/'),
				Api\CalDAV::mkprop('href',$this->base_uri(false).'/principals/users/'.$account['account_lid'].'/'),
				Api\CalDAV::mkprop('href','urn:uuid:'.Api\CalDAV::generate_uid('accounts', $account['account_id'])),
			)),
			'calendar-user-type' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-user-type','INDIVIDUAL'),
			// Calendarserver
			'email-address-set' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'email-address-set',array(
				Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'email-address',$account['account_email']))),
			'last-name' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'last-name',$account['account_lastname']),
			'first-name' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'first-name',$account['account_firstname']),
			'record-type' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'record-type','users'),
			// WebDAV ACL and CalDAV proxy
			'group-membership' => $this->principal_set('group-membership', $this->accounts->memberships($account['account_id']),
				array('calendar', 'resources'), $account['account_id']),	// add proxy-rights
			'alternate-URI-set' => array(
				Api\CalDAV::mkprop('href','mailto:'.$account['account_email'])),
			// CardDAV
			'addressbook-home-set' => Api\CalDAV::mkprop(Api\CalDAV::CARDDAV,'addressbook-home-set',$addressbooks),
			'principal-address' => Api\CalDAV::mkprop(Api\CalDAV::CARDDAV,'principal-address',
				$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'] ? '' : array(
				Api\CalDAV::mkprop('href',$this->base_uri.'/addressbook-accounts/'.$account['person_id'].'.vcf'))),
			// CardDAV directory
			'directory-gateway' => Api\CalDAV::mkprop(Api\CalDAV::CARDDAV, 'directory-gateway',array(
				Api\CalDAV::mkprop('href', $this->base_uri.'/addressbook/'))),
			'resource-id' => array(Api\CalDAV::mkprop('href','urn:uuid:'.Api\CalDAV::generate_uid('accounts', $account['account_id']))),
		));
	}

	/**
	 * Convert CalDAV principal URL to a calendar uid
	 *
	 * @param string $url
	 * @param string|array $only_type =null allowed types, return false for other (valid) types, eg. "users", "groups" or "resources", default all
	 * @param string $cn =null common name to be stored in case of an "e" uid
	 * @return int|string|boolean integer account_id, string calendar uid or false if not a supported uid
	 */
	static public function url2uid($url, $only_type=null, $cn=null)
	{
		if (!$only_type) $only_type = array('users', 'groups', 'resources', 'locations', 'mailto');

		if ($url[0] == '/')
		{
			$schema = 'http';
		}
		else
		{
			list($schema, $rest) = explode(':', $url, 2);
		}
		if (empty($rest)) return false;

		switch(strtolower($schema))
		{
			case 'http':
			case 'https':
				list(,$rest) = explode('/groupdav.php/principals/', $url);
				list($type, $name) = explode('/', $rest);
				switch($type)
				{
					case 'users':
					case 'groups':
						$uid = $GLOBALS['egw']->accounts->name2id($name, 'account_lid', $type[0]);	// u=users, g=groups
						break;
					case 'resources':
					case 'locations':
						$uid = 'r'.(int)$name;
						break;
				}
				break;

			case 'mailto':
				if (($uid = $GLOBALS['egw']->accounts->name2id($rest, 'account_email')))
				{
					$type = $uid > 0 ? 'users' : 'groups';
					break;
				}
				// search contacts for email
				if ((list($data) = $GLOBALS['egw']->contacts->search(array('email' => $rest, 'email_home' => $rest),
					array('id','egw_addressbook.account_id as account_id','n_fn'),
					'egw_addressbook.account_id IS NOT NULL DESC, n_fn IS NOT NULL DESC',
					'','',false,'OR')))
				{
					// found an addressbook entry
					$uid = $data['account_id'] ? (int)$data['account_id'] : 'c'.$data['id'];
					$type = 'users';
				}
				else	// just store email-address
				{
					$uid = $cn && $rest[0] != '<' && $cn != $rest ? 'e'.$cn.' <'.$rest.'>' : 'e'.$rest;
					$type = 'users';
				}
				break;

			case 'urn':
				list($urn_type, $uid) = explode(':', $rest, 2);
				list($type, $id, $install_id) = explode('-', $uid);
				if ($type == 'accounts' && empty($id))	// groups have a negative id, eg. "urn:uuid:accounts--1-..."
				{
					list($type, , $id_abs, $install_id) = explode('-', $uid);
					$id = -$id_abs;
				}
				// own urn
				if ($urn_type === 'uuid' && $install_id === $GLOBALS['egw_info']['server']['install_id'])
				{
					if ($type == 'accounts')
					{
						$type = $id > 0 ? 'users' : 'groups';
						$uid = $id;
					}
					else
					{
						static $calendar_bo=null;
						if (is_null($calendar_bo)) $calendar_bo = new calendar_bo();
						foreach($calendar_bo->resources as $letter => $info)
						{
							if ($info['app'] == $type || $info['app'] == 'resources' && $type == 'location')
							{
								$uid = $letter.$id;
							}
						}
					}
				}
				// todo: store urn's from other EGroupware / calendarservers like email addresses ("CN <urn>" or "urn", maybe with 'u' prefix)
				break;

			default:
				if (isset($GLOBALS['groupdav']) && is_a($GLOBALS['groupdav'],'groupdav'))
				{
					$GLOBALS['groupdav']->log(__METHOD__."('$url') unsupported principal URL '$url'!");
				}
				return false;
		}
		//error_log(__METHOD__."('$url', ".array2string($only_type).") uid='$uid', type='$type' --> returning ".array2string($uid && in_array($type, $only_type) ? $uid : false));
		return $uid && in_array($type, $only_type) ? $uid : false;
	}

	/**
	 * Add collection of a single group to a collection
	 *
	 * @param array $account
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_group(array $account)
	{
		$displayname = Api\Translation::convert(lang('Group').' '.$account['account_lid'],	Api\Translation::charset(), 'utf-8');

		// only return current user, if account-selection == 'none'
		if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none')
		{
			$groupmembers = array($GLOBALS['egw_info']['user']['account_id'] => $GLOBALS['egw_info']['user']['account_lid']);
		}
		else
		{
			$groupmembers = $this->accounts->members($account['account_id']);
		}

		return $this->add_principal('groups/'.$account['account_lid'], array(
			'getetag' => $this->get_etag($account),
			'displayname' => $displayname,
			'calendar-home-set' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-home-set',array(
				Api\CalDAV::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			'addressbook-home-set' => Api\CalDAV::mkprop(Api\CalDAV::CARDDAV,'addressbook-home-set',array(
				Api\CalDAV::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			'calendar-user-address-set' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-user-address-set',array(
				Api\CalDAV::mkprop('href',$this->base_uri(true).'/principals/groups/'.$account['account_lid'].'/'),
				Api\CalDAV::mkprop('href',$this->base_uri(false).'/principals/groups/'.$account['account_lid'].'/'),
				Api\CalDAV::mkprop('href','urn:uuid:'.Api\CalDAV::generate_uid('accounts', $account['account_id'])),
			)),
			'record-type' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'record-type','groups'),
			'calendar-user-type' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-user-type','GROUP'),
			'group-member-set' => $this->principal_set('group-member-set', $groupmembers),
			'resource-id' => array(Api\CalDAV::mkprop('href','urn:uuid:'.Api\CalDAV::generate_uid('accounts', $account['account_id']))),
		));
	}

	/**
	 * Add collection of a single resource to a collection
	 *
	 * @param array $resource
	 * @param boolean $is_location =null
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_principal_resource(array $resource, $is_location=null)
	{
		$displayname = null;
		$name = $this->resource2name($resource, $is_location, $displayname);

		return $this->add_principal($name, array(
			'getetag' => $this->get_resource_etag($resource),
			'displayname' => $displayname,
			'calendar-user-address-set' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-user-address-set',array(
				Api\CalDAV::mkprop('href',$this->base_uri(true).'/principals/'.$name.'/'),
				Api\CalDAV::mkprop('href',$this->base_uri(false).'/principals/'.$name.'/'),
				Api\CalDAV::mkprop('href','urn:uuid:'.Api\CalDAV::generate_uid('resources', $resource['res_id'])),
			)),
			'record-type' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'record-type',$is_location ? 'locations' : 'resources'),
			'calendar-user-type' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-user-type',$is_location ? 'ROOM' : 'RESOURCE'),
			'resource-id' => array(Api\CalDAV::mkprop('href','urn:uuid:'.Api\CalDAV::generate_uid('resources', $resource['res_id']))),
			// Calendarserver also reports empty email-address-set, thought iCal still does not show resources (only locations)
			'email-address-set' => Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER,'email-address-set',''),
			'calendar-home-set' => Api\CalDAV::mkprop(Api\CalDAV::CALDAV,'calendar-home-set',array(
				Api\CalDAV::mkprop('href',$this->base_uri.'/'.$name.'/'))),
		));
	}

	/**
	 * Get path of a resource-principal (relative to principal collection)
	 *
	 * @param array|int $resource
	 * @param boolean &$is_location=null
	 * @param string &$displayname=null on return displayname of resource
	 * @return string eg. "locations/123-some-room" or "resouces/345-some-device"
	 */
	public static function resource2name($resource, &$is_location=null, &$displayname=null)
	{
		if (!is_array($resource) && !($resource = self::read_resource($resource)))
		{
			return null;
		}
		if (is_null($is_location)) $is_location = self::resource_is_location($resource);

		$displayname = Api\Translation::convert($resource['name'],	Api\Translation::charset(), 'utf-8');

		return ($is_location ? 'locations/' : 'resources/').$resource['res_id'].'-'.
			preg_replace('/[^a-z0-9]+/i','-', Api\Translation::to_ascii($resource['name']));
	}

	/**
	 * Check if resource is a location
	 *
	 * @param array|int $resource
	 * @return boolean
	 */
	public static function resource_is_location($resource)
	{
		static $location_cats=null;
		if (is_null($location_cats))
		{
			$config = Api\Config::read('resources');
			$location_cats = $config['location_cats'] ? explode(',', $config['location_cats']) : array();
		}
		if (!is_array($resource) && !($resource = self::read_resource($resource)))
		{
			return null;
		}
		return $resource['cat_id'] && in_array($resource['cat_id'], $location_cats);
	}

	/**
	 * Read a resource
	 *
	 * @param int $res_id resource-id
	 * @return array with values for res_id, cat_id and name
	 */
	public static function read_resource($res_id)
	{
		static $cache=null;	// some per-request caching

		if (isset(self::$all_resources) && isset(self::$all_resources[$res_id]))
		{
			return self::$all_resources[$res_id];
		}

		if (!isset($cache[$res_id]))
		{
			if (!isset(self::$resources)) self::$resources = new resources_bo();

			if (!($cache[$res_id] = self::$resources->read($res_id)))
			{
				return null;
			}
		}
		return $cache[$res_id];
	}

	/**
	 * Get an etag for a resource
	 *
	 * @param array $resource
	 * @return string
	 */
	protected function get_resource_etag(array $resource)
	{
		return md5(serialize($resource)).'-'.(self::resource_is_location($resource) ? 'l' : 'r');
	}

	/**
	 * Cache for get_resources
	 *
	 * @var array
	 */
	private static $all_resources;
	/**
	 * Get all resources (we cache the resources here, to only query them once per request)
	 *
	 * @param int $user =null account_if of user, or null for current user
	 * @return array of array with values for res_id, cat_id and name (no other values1)
	 */
	public static function get_resources($user=null)
	{
		if (!isset(self::$all_resources))
		{
			if (!isset(self::$resources)) self::$resources = new resources_bo($user);

			self::$all_resources = array();
			$query = array(
				'show_bookable' => true,	// ignore non-bookable resources
				'filter2' => -3,
				'start' => 0,
				'num_rows' => 10000,	// return all aka first 10000 entries
			);
			$rows = $readonlys = null;
			if (self::$resources->get_rows($query, $rows, $readonlys))
			{
				//_debug_array($rows);
				foreach($rows as $resource)
				{
					self::$all_resources[$resource['res_id']] = array_intersect_key($resource, array('res_id'=>true,'cat_id'=>true,'name'=>true));
				}
			}
		}
		return self::$all_resources;
	}

	/**
	 * Get category based ACL rights for resouces
	 *
	 * Cached to not query it multiple times per request
	 *
	 * @return array of 'L'.$cat_id => array($account_id => $rights) pairs
	 */
	protected function get_resource_rights()
	{
		static $grants=null;

		if (is_null($grants))
		{
			$grants = $this->acl->get_location_grants('L%', 'resources');
		}
		return $grants;
	}

	/**
	 * Add a collection
	 *
	 * @param string $path
	 * @param array $props =array() extra properties 'resourcetype' is added anyway, name => value pairs or name => Api\CalDAV([namespace,]name,value)
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_collection($path, array $props = array())
	{
		if ($this->caldav->prop_requested('supported-report-set'))
		{
			$props['supported-report-set'] = $this->supported_report_set($path);
		}
		return $this->caldav->add_collection($path, $props);
	}

	/**
	 * Add a principal collection
	 *
	 * @param string $principal relative to principal-collection-set, eg. "users/username"
	 * @param array $props =array() extra properties 'resourcetype' is added anyway
	 * @param string $principal_url =null include given principal url, relative to principal-collection-set, default $principal
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_principal($principal, array $props = array(), $principal_url=null)
	{
		$props['resourcetype'][] = Api\CalDAV::mkprop('principal', '');

		// required props per WebDAV ACL
		foreach(array('alternate-URI-set', 'group-membership') as $name)
		{
			if (!isset($props[$name])) $props[$name] = Api\CalDAV::mkprop($name,'');
		}
		if (!$principal_url) $principal_url = $principal;

		$props['principal-URL'] = array(
			Api\CalDAV::mkprop('href',$this->base_uri.'/principals/'.$principal.'/'));

		return $this->add_collection('/principals/'.$principal.'/', $props);
	}

	/**
	 * Add a proxy collection for given principal and type
	 *
	 * A proxy is a user or group who has the right to act on behalf of the user
	 *
	 * @param string $principal relative to principal-collection-set, eg. "users/username"
	 * @param string $type eg. 'calendar-proxy-read' or 'calendar-proxy-write'
	 * @param array $proxys =array()
	 * @param array $resource =null resource to use (to not query it multiple times from the database)
	 * @return array with values for 'path' and 'props'
	 */
	protected function add_proxys($principal, $type, array $proxys=array(), array $resource=null)
	{
		list($app,,$what) = explode('-', $type);

		if (true) $proxys = array();	// ignore parameter!
		list($account_type,$account) = explode('/', $principal);

		switch($account_type)
		{
			case 'users':
			case 'groups':
				$account = $location = $this->accounts->name2id($account, 'account_lid', $account_type[0]);
				$right = $what == 'write' ? Api\Acl::EDIT : Api\Acl::READ;
				$mask = $what == 'write' ? Api\Acl::EDIT : Api\Acl::EDIT|Api\Acl::READ;	// do NOT report write+read in read
				break;

			case 'locations':
			case 'resources':
				$app = 'resources';
				if (!is_array($resource) || $resource['res_id'] == (int)$account)
				{
					$resource = self::read_resource((int)$account);
				}
				$location = 'L'.$resource['cat_id'];
				$right = $what == 'write' ? EGW_ACL_DIRECT_BOOKING : EGW_ACL_CALREAD;
				$mask = $what == 'write' ? EGW_ACL_DIRECT_BOOKING : EGW_ACL_DIRECT_BOOKING|EGW_ACL_CALREAD;	// do NOT report write+read in read
				break;
		}
		static $principal2grants = array();
		$grants =& $principal2grants[$principal];
		if (!isset($grants))
		{
			switch($app)
			{
				case 'resources':
					$res_grants = $this->get_resource_rights();
					$grants = (array)$res_grants[$location];	// returns array($location => $grants)
					break;

				case 'calendar':
				default:
					$grants = $this->acl->get_all_location_rights($account, $app, $app != 'addressbook');
					break;
			}
			//echo "<p>type=$type --> app=$app, what=$what --> right=$right, mask=$mask, account=$account, location=$location --> grants=".array2string($grants)."</p>\n";
		}
		foreach($grants as $account_id => $rights)
		{
			if ($account_id !== 'run' && $account_id != $account && ($rights & $mask) == $right &&
				($account_lid = $this->accounts->id2name($account_id)))
			{
				// ignore "broken" grants (eg. negative account_id for a user), as they lead to further errors (no members)
				if (($t = $this->accounts->exists($account_id) == 1 ? 'u' : 'g') !== $this->accounts->get_type($account_id))
				{
					error_log(__METHOD__."('$principal', '$type') broken grants from #$account_id ($account_lid) type=$t ignored!");
					continue;
				}
				$proxys[$account_id] = $account_lid;
				// no need to add group-members, ACL grants to groups are understood by WebDAV ACL (tested with iCal)
			}
			//echo "<p>$account_id ($account_lid) type=$t: (rights=$rights & mask=$mask) == right=$right --> ".array2string(($rights & $mask) == $right)."</p>\n";
		}
		return $this->add_principal($principal.'/'.$type, array(
				'displayname' => lang('%1 proxy of %2', lang($app).' '.lang($what), basename($principal)),
				'group-member-set' => $this->principal_set('group-member-set', $proxys),
				'getetag' => md5(serialize($proxys)),
				'resourcetype' => array(Api\CalDAV::mkprop(Api\CalDAV::CALENDARSERVER, $type, '')),
			));
	}

	/**
	 * Create a named property with set or principal-urls
	 *
	 * @param string $prop egw. 'group-member-set' or 'membership'
	 * @param array $accounts =array() account_id => account_lid pairs
	 * @param string|array $app_proxys =null applications for which proxys should be added
	 * @param int $account who is the proxy
	 * @return array with href props
	 */
	protected function principal_set($prop, array $accounts=array(), $app_proxys=null, $account=null)
	{
		unset($prop);	// not used, but required by function signature

		$set = array();
		foreach($accounts as $account_id => $account_lid)
		{
			if ($this->accounts->visible($account_lid))	// only add visible accounts, gives error in iCal otherwise
			{
				$set[] = Api\CalDAV::mkprop('href', $this->base_uri.'/principals/'.($account_id < 0 ? 'groups/' : 'users/').$account_lid.'/');
			}
		}
		if ($app_proxys)
		{
			foreach((array)$app_proxys as $app)
			{
				if (!isset($GLOBALS['egw_info']['user']['apps'][$app])) continue;

				switch($app)
				{
					case 'resources':
						$proxy_groups = $this->get_resource_proxy_groups($account);
						break;
					default:
						$proxy_groups = $this->get_calendar_proxy_groups($account, $app);
						break;
				}
				$set = array_merge($set, $proxy_groups);
			}
		}
		return $set;
	}

	/**
	 * Get proxy-groups for given user $account: users or groups who GRANT proxy rights to $account
	 *
	 * @param int $account who is the proxy
	 * @param string|array $app_proxys =null applications for which proxys should be added
	 * @return array with href props
	 */
	protected function get_resource_proxy_groups($account)
	{
		$set = array();
		if (($resources = $this->get_resources()))
		{
			// location_grants = array(location => array(account_id => rights))
			$all_location_grants = $this->get_resource_rights();
			// get location grants for $account (incl. his memberships)
			$memberships = $GLOBALS['egw']->accounts->memberships($account, true);
			$location_grants = array();
			foreach($all_location_grants as $location => $grants)
			{
				foreach($grants as $account_id => $rights)
				{
					if (($rights & (EGW_ACL_CALREAD|EGW_ACL_DIRECT_BOOKING)) &&	// we only care for these rights
						($account_id == $account || in_array($account_id, $memberships)))
					{
						if (!isset($location_grants[$location])) $location_grants[$location] = 0;
						$location_grants[$location] |= $rights;
					}
				}
			}
			// now add proxy-groups for all resources user has rights to
			foreach($resources as $resource)
			{
				$rights = $location_grants['L'.$resource['cat_id']];
				if (isset($rights))
				{
					$set[] = Api\CalDAV::mkprop('href', $this->base_uri.'/principals/'.$this->resource2name($resource).
						'/calendar-proxy-'.($rights & EGW_ACL_DIRECT_BOOKING ? 'write' : 'read').'/');
				}
			}
		}
		//echo "get_resource_proxy_groups($account)"; _debug_array($set);
		return $set;
	}

	/**
	 * Get proxy-groups for given user $account: users or groups who GRANT proxy rights to $account
	 *
	 * @param int $account who is the proxy
	 * @param string $app ='calendar' applications for which proxys should be added
	 * @return array with href props
	 */
	protected function get_calendar_proxy_groups($account, $app='calendar')
	{
		$set = array();
		foreach($this->acl->get_grants($app, $app != 'addressbook', $account) as $account_id => $rights)
		{
			if ($account_id != $account && ($rights & Api\Acl::READ) &&
				($account_lid = $this->accounts->id2name($account_id)) &&
				$this->accounts->visible($account_lid))	// only add visible accounts, gives error in iCal otherwise
			{
				$set[] = Api\CalDAV::mkprop('href', $this->base_uri.'/principals/'.
					($account_id < 0 ? 'groups/' : 'users/').
					$account_lid.'/'.$app.'-proxy-'.($rights & Api\Acl::EDIT ? 'write' : 'read').'/');
			}
		}
		return $set;
	}

	/**
	 * Do propfind in /pricipals/(resources|locations)
	 *
	 * @param string $name name of group or empty
	 * @param string $rest rest of path behind account-name
	 * @param array $options
	 * @param boolean $do_locations =false false: /principal/resources, true: /principals/locations
	 * @return array|string array with files or HTTP error code
	 */
	protected function propfind_resources($name,$rest,array $options,$do_locations=false)
	{
		if (!isset($GLOBALS['egw_info']['user']['apps']['resources']))
		{
			return '404 Not Found';
		}
		//echo "<p>".__METHOD__."('$name', '$rest', ".array2string($options).', '.array2string($do_locations).")</p>\n";
		if (empty($name))
		{
			$files = array();
			// add /pricipals/users/ entry
			$files[] = $this->add_collection('/principals/'.($do_locations ? 'locations/' : 'resources/'),
				array('displayname' => $do_locations ? lang('Locations') : lang('Resources')));

			if ($options['depth'])
			{
				if (($resources = $this->get_resources()))
				{
					//_debug_array($resources);
					foreach($resources as $resource)
					{
						if (($is_location = self::resource_is_location($resource)) == $do_locations)
						{
							$files[] = $this->add_principal_resource($resource, $is_location);
						}
					}
				}
			}
		}
		else
		{
			if (!($resource = self::read_resource((int)$name)) || ($is_location = self::resource_is_location($resource)) != $do_locations)
			{
				return '404 Not Found';
			}
			$path = ($is_location ? 'locations/' : 'resources/').$name;
			while (substr($rest,-1) == '/')
			{
				$rest = substr($rest,0,-1);
			}
			switch((string)$rest)
			{
				case '':
					$files[] = $this->add_principal_resource($resource);
					if ($options['depth'])
					{
						$files[] = $this->add_proxys($path, 'calendar-proxy-read', array(), $resource);
						$files[] = $this->add_proxys($path, 'calendar-proxy-write', array(), $resource);
					}
					break;
				case 'calendar-proxy-read':
				case 'calendar-proxy-write':
					$files = array();
					$files[] = $this->add_proxys($path, $rest, array(), $resource);
					break;
				default:
					return '404 Not Found';
			}
		}
		return $files;
	}

	/**
	 * Do propfind of /principals/
	 *
	 * @param string $name name of group or empty
	 * @param string $rest name of rest of path behind group-name
	 * @param array $options
	 * @return array|string array with files or HTTP error code
	 */
	protected function propfind_principals(array $options)
	{
		//echo "<p>".__METHOD__."(".array($options).")</p>\n";
		$files = array();
		$files[] = $this->add_collection('/principals/');

		if ($options['depth'])
		{
			if (is_numeric($options['depth'])) --$options['depth'];
			$files = array_merge($files, $this->propfind_users('','',$options),
				$this->propfind_groups('','',$options));
			if ($GLOBALS['egw_info']['user']['apps']['resources'])
			{
				$files = array_merge($files, $this->propfind_resources('','',$options,false),	// resources
					$this->propfind_resources('','',$options,true));	// locations
			}
			//$files = array_merge($files,$this->propfind_uids('','',$options));
		}
		return $files;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		unset($options, $id, $user);	// not used, but required by function signature

		return false;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null)
	{
		unset($options, $id, $user);	// not used, but required by function signature

		return false;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id)
	{
		unset($options, $id);	// not used, but required by function signature

		return false;
	}

	/**
	 * Read an entry
	 *
	 * @param string|int $id
	 * @return array/boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		unset($id);	// not used, but required by function signature

		return false;
	}

	/**
	 * Check if user has the neccessary rights on an entry
	 *
	 * @param int $acl Api\Acl::READ, Api\Acl::EDIT or Api\Acl::DELETE
	 * @param array|int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$entry)
	{
		if ($acl != Api\Acl::READ)
		{
			return false;
		}
		if (!is_array($entry) && !$this->accounts->name2id($entry,'account_lid','u'))
		{
			return null;
		}
		return true;
	}

	/**
	 * Get the etag for an entry, can be reimplemented for other algorithm or field names
	 *
	 * @param array|int $account array with event or cal_id
	 * @return string/boolean string with etag or false
	 */
	function get_etag($account)
	{
		if (!is_array($account))
		{
			$account = $this->read($account);
		}
		return $account['account_id'].':'.md5(serialize($account)).
			// add md5 from calendar & resource grants, as they are listed as memberships
			':'.md5(serialize($this->acl->get_grants('calendar', true, $account['account_id'])).
				serialize($this->get_resource_rights())).
			// as the principal of current user is influenced by GroupDAV prefs, we have to include them in the etag
			($account['account_id'] == $GLOBALS['egw_info']['user']['account_id'] ?
				':'.md5(serialize($GLOBALS['egw_info']['user']['preferences']['groupdav'])) : '');
	}

	/**
	 * Return privileges for current user, default is read and read-current-user-privilege-set
	 *
	 * Privileges are for the collection, not the resources / entries!
	 *
	 * @param string $path path of collection
	 * @param int $user =null owner of the collection, default current user
	 * @return array with privileges
	 */
	public function current_user_privileges($path, $user=null)
	{
		unset($path, $user);	// not used, but required by function signature

		return array('read', 'read-current-user-privilege-set');
	}
}
