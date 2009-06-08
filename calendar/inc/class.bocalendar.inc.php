<?php
/**
 * eGroupWare - Calendar's XMLRPC or SOAP access
 *
 * Please note: dont use addressbook_... naming convention, as it would break the existing xmlrpc clients
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005-8 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class to access AND manipulate calendar data via XMLRPC or SOAP
 *
 * eGW's xmlrpc interface is documented at http://egroupware.org/wiki/xmlrpc
 *
 * @link http://egroupware.org/wiki/xmlrpc
 */
class bocalendar
{
	var $xmlrpc_date_format = 'Y-m-d\\TH:i:s';
	var $debug = false;	// log function call to the apache error_log
	var $cal;
	var $public_functions = Array(
		'read'      => True,
		'delete'    => True,
		'write'     => True,
		'search'    => True,
		'categories'=> True,
		'list_methods' => True,
	);

	function __construct()
	{
		$this->cal = new calendar_boupdate();

		if (is_object($GLOBALS['server']) && $GLOBALS['server']->simpledate)
		{
			$this->xmlrpc_date_format = 'Ymd\\TH:i:s';
		}
	}

	/**
	 * This handles introspection or discovery by the logged in client,
	 * in which case the input might be an array.  The server always calls
	 * this function to fill the server dispatch map using a string.
	 *
	 * @param string/array $_type string or array with key 'type' for type of interface: xmlrpc or soap
	 * @return array
	 */
	function list_methods($_type='xmlrpc')
	{
		switch(is_array($_type) ? ($_type['type'] ? $_type['type'] : $_type[0]) : $_type)
		{
			case 'xmlrpc':
				return array(
					'list_methods' => array(
						'function'  => 'list_methods',
						'signature' => array(array(xmlrpcStruct,xmlrpcString)),
						'docstring' => 'Read this list of methods.'
					),
					'read' => array(
						'function'  => 'read',
						'signature' => array(array(xmlrpcStruct,xmlrpcInt)),
						'docstring' => 'Read a single entry by passing the id or uid.'
					),
					'write' => array(
						'function'  => 'write',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => 'Add or update a single entry by passing the fields.'
					),
					'delete' => array(
						'function'  => 'delete',
						'signature' => array(array(xmlrpcInt,xmlrpcInt)),
						'docstring' => 'Delete a single entry by passing the id.'
					),
					'search' => array(
						'function'  => 'search',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => 'Read a list of entries.'
					),
					'categories' => array(
						'function'  => 'categories',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => 'List all categories.'
					),
				);

			case 'soap':
				return Array(
					'read' => Array(
						'in' => Array('int'),
						'out' => Array('SOAPStruct')
					),
					'delete' => Array(
						'in' => Array('int'),
						'out' => Array('int')
					),
					'write' => Array(
						'in' => Array('array'),
						'out' => Array('array')
					),
					'search'	=> Array(
						'in' => Array('struct'),
						'out' => Array('SOAPStruct')
					),
					'categories' => array(
						'in'  => array('bool'),
						'out' => array('array')
					),
				);
		}
		return array();
	}

	/**
	 * Read a single entry
	 *
	 * @param int/array $id
	 * @return array with event(s) or XMLRPC error not_existent or no_access
	 */
	function read($id)
	{
		if ($this->debug) error_log('bocalendar::read('.print_r($id,true).')');

		$events =& $this->cal->read($id,null,true,$this->xmlrpc_date_format);	// true = ignore acl!!!

		if (!$events)	// only id not found, as ignore_acl=true
		{
			// xmlrpc_error does NOT return
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_existent'],$GLOBALS['xmlrpcstr']['not_existent']);
		}
		if (is_array($id) && count($id) > 1)
		{
			foreach($events as $key => $event)
			{
				if (!$this->cal->check_perms(EGW_ACL_READ,$event,0,$this->xmlrpc_date_format))
				{
					// xmlrpc_error does NOT return
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
				}
				$events[$key] = $this->xmlrpc_prepare($event);
			}
		}
		else
		{
			// for a single id the event is returned and not an array with the event
			if (is_array($id)) $events = array_shift($events);

			if (!$this->cal->check_perms(EGW_ACL_READ,$events,0,$this->xmlrpc_date_format))
			{
				// xmlrpc_error does NOT return
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			$events = $this->xmlrpc_prepare($events);
		}
		return $events;
	}

	/**
	 * Delete an event
	 *
	 * @param array $ids event-id(s)
	 * @return boolean true or XMLRPC error not_existent or no_access
	 */
	function delete($ids)
	{
		if ($this->debug) error_log('bocalendar::delete('.print_r($ids,true).')');

		foreach((array) $ids as $id)
		{
			if (!$event = $this->cal->read($id,null,true))	// id not found, as ignore_acl=true
			{
				// xmlrpc_error does NOT return
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_existent'],$GLOBALS['xmlrpcstr']['not_existent']);
			}
			if (!$this->cal->check_perms(EGW_ACL_DELETE,$event))
			{
				// xmlrpc_error does NOT return
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			if (!$this->cal->delete($id))
			{
				return false;	// other db-problem
			}
		}
		return true;
	}

	/**
	 * Add/update an event
	 *
	 * @param array $event event-data
	 * @return int cal-id
	 */
	function write($event)
	{
		if ($this->debug) error_log('bocalendar::write('.print_r($event,true).')');

		// convert xmlrpc specific values back
		$event['category'] = $event['category'] ? $GLOBALS['server']->xmlrpc2cats($event['category']) : null;

		// using access={public|private} in all modules via xmlrpc
		$event['public'] = $event['access'] != 'private';
		unset($event['access']);

		if (is_array($event['participants']))
		{
			foreach($event['participants'] as $user => $data)
			{
				if (!is_numeric($user))
				{
					unset($event['participants'][$user]);
					$user = $GLOBALS['egw']->accounts->name2id($data['email'],'account_email');
				}
				if (!$user) continue;

				$event['participants'][$user] = in_array($data['status'],array('U','A','R','T')) ? $data['status'] : 'U';
			}
		}
		if (!is_array($event['participants']) || !count($event['participants']))
		{
			$event['participants'] = array($GLOBALS['egw_info']['user']['account_id'] => 'A');
		}
		if (!($id = $this->cal->update($event,true)))	// true=no conflict check for now
		{
			$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
		}
		return (int) $id;
	}

	/**
	 * search calendar for events
	 *
	 * If daywise is not set or false, an XMLRPC array of events is returned, otherwise an
	 * XMLRCP struct with date as string (eg. '20060101') and an array of events is returned.
	 *
	 * @param array $params following keys are allowed: start, end, user (more see bocal::search)
	 * @return array with events
	 */
	function search($params)
	{
		if ($this->debug) error_log('bocalendar::search('.print_r($params,true).')');

		// some defaults for xmlrpc
		if (!isset($params['date_format'])) $params['date_format'] = $this->xmlrpc_date_format;
		if (!isset($params['enum_recuring'])) $params['enum_recuring'] = false;
		// security precausion
		unset($params['ignore_acl']);

		$events =& $this->cal->search($params);

		foreach($events as $key => $event)
		{
			$events[$key] = $this->xmlrpc_prepare($event);
		}
		return !$params['daywise'] ? array_values($events) : $events;
	}

	/**
	 * prepare regular event-array (already with iso8601 dates) to be send by xmlrpc
	 *	- participants are send as struct/array with keys: name, email, status
	 *	- categories are send as array with cat_id - title pairs
	 *	- public is transformed to access={public|private}
	 *  - new (1.2) values get unset (eg. participant_types)
	 *
	 * @param array &$event
	 * @return array
	 */
	function xmlrpc_prepare(&$event)
	{
		$event['rights'] = $this->cal->grants[$event['owner']];

		static $user_cache = array();

		foreach((array) $event['participants'] as $uid => $status)
		{
			if (!is_numeric($uid)) continue;	// resources

			if (!isset($user_cache[$uid]))
			{
				$user_cache[$uid] = array(
					'name'   => $GLOBALS['egw']->common->grab_owner_name($uid),
					'email'  => $GLOBALS['egw']->accounts->id2name($uid,'account_email')
				);
			}
			$event['participants'][$uid] = $user_cache[$uid] + array(
				'status' => $status,
			);
		}
		if (is_array($event['alarm']))
		{
			foreach($event['alarm'] as $id => $alarm)
			{
				if ($alarm['owner'] != $GLOBALS['egw_info']['user']['account_id'])
				{
					unset($event['alarm'][$id]);
				}
			}
		}
		if ($event['category'])
		{
			$event['category'] = $GLOBALS['server']->cats2xmlrpc(explode(',',$event['category']));
		}
		else
		{
			unset($event['category']);
		}
		// using access={public|private} in all modules via xmlrpc
		$event['access'] = $event['public'] ? 'public' : 'private';

		// unset everything not known in version 1.0
		foreach(array('public','participant_types') as $key)
		{
			unset($event[$key]);
		}
		// unsetting everything which could result in an typeless <value />
		foreach($event as $key => $value)
		{
			if (is_null($value) || is_array($value) && !$value)
			{
				unset($event[$key]);
			}
		}
		return $event;
	}

	/**
	 * return array with all categories
	 *
	 * @param boolean $complete=false false: return id => title pairs, true array with full data instead of title
	 * @return array
	 */
	function categories($complete = False)
	{
		return $GLOBALS['server']->categories($complete);
	}
}