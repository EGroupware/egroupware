<?php
/**
 * EGroupware: GroupDAV access: groupdav/caldav/carddav principals handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * EGroupware: GroupDAV access: groupdav/caldav/carddav principals handlers
 *
 * @todo All principal urls should either contain no account_lid (eg. base64 of it) or use urlencode($account_lid)
 */
class groupdav_principals extends groupdav_handler
{
	/**
	 * Reference to the accounts class
	 *
	 * @var accounts
	 */
	var $accounts;

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 * @param string $principalURL=null principal url of handler
	 */
	function __construct($app,$debug=null,$base_uri=null,$principalURL=null)
	{
		parent::__construct($app,$debug,$base_uri,$principalURL);

		$this->accounts = $GLOBALS['egw']->accounts;
	}

	/**
	 * Handle propfind request for an application folder
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,$options,&$files,$user)
	{
		// we do NOT support REPORTS on pricipals yet
		// required for Apple Addressbook on Mac (addressbook-findshared REPORT)
		if ($options['root']['name'] && $options['root']['name'] != 'propfind')
		{
			return '501 Not Implemented';
		}
		list(,$principals,$type,$name,$rest) = explode('/',$path,5);
		// /principals/users/$name/
		//            /users/$name/calendar-proxy-read/
		//            /users/$name/calendar-proxy-write/
		//            /groups/$name/
		//            /resources/$resource/
		//            /__uids__/$uid/.../

		switch($type)
		{
			case 'users':
				$files['files'] = $this->propfind_users($name,$rest,$options);
				break;
			case 'groups':
				$files['files'] = $this->propfind_groups($name,$rest,$options);
				break;
			/*case 'resources':
				$files['files'] = $this->propfind_resources($name,$rest,$options);
				break;
			case '__uids__':
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
	 * Do propfind in /pricipals/users
	 *
	 * @param string $name name of account or empty
	 * @param string $rest rest of path behind account-name
	 * @param array $options
	 * @return array|string array with files or HTTP error code
	 */
	protected function propfind_users($name,$rest,array $options)
	{
		error_log(__METHOD__."($name,$rest,".array2string($options).')');
		if (empty($name))
		{
			$files = array();
			// add /pricipals/users/ entry
			$files[] = $this->add_collection('/principals/users/');

			if ($options['depth'])
			{
				// add all users
				foreach($this->accounts->search(array('type' => 'accounts')) as $account)
				{
					$files[] = $this->add_account($account);
				}
			}
		}
		else
		{
			if (!($id = $this->accounts->name2id($name,'account_lid','u')) ||
				!($account = $this->accounts->read($id)))
			{
				return '404 Not Found';
			}
			while (substr($rest,-1) == '/') $rest = substr($rest,0,-1);
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
			$files[] = $this->add_collection('/principals/groups/');

			if ($options['depth'])
			{
				// add all users
				foreach($this->accounts->search(array('type' => 'groups')) as $account)
				{
					$files[] = $this->add_group($account);
				}
			}
		}
		else
		{
			if (!($id = $this->accounts->name2id($name,'account_lid','g')) ||
				!($account = $this->accounts->read($id)))
			{
				return '404 Not Found';
			}
			while (substr($rest,-1) == '/') $rest = substr($rest,0,-1);
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
	 * Add collection of a single account to a collection
	 *
	 * @param array $account
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_account(array $account)
	{
		$addressbooks = $calendars = array();
		if ($account['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
		{
			$prefs = $GLOBALS['egw_info']['user']['preferences']['groupdav'];
			$addressbook_home_set = $prefs['addressbook-home-set'];
			if (empty($addressbook_home_set)) $addressbook_home_set = 'P';	// personal addressbook
			$addressbook_home_set = explode(',',$addressbook_home_set);
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
				$addressbooks[] = HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/');
			}
			foreach(ExecMethod('addressbook.addressbook_bo.get_addressbooks',EGW_ACL_READ) as $id => $label)
			{
				if ((in_array('A',$addressbook_home_set) || in_array((string)$id,$addressbook_home_set)) &&
					is_numeric($id) && ($owner = $GLOBALS['egw']->accounts->id2name($id)))
				{
					$addressbooks[] = HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.urlencode($owner).'/');
				}
			}
			$calendars[] = HTTP_WebDAV_Server::mkprop('href',
				$this->base_uri.'/'.$account['account_lid'].'/');

/* iCal send propfind to wrong url (concatinated href's), if we return multiple href in calendar-home-set
			$cal_bo = new calendar_bo();
			foreach ($cal_bo->list_cals() as $label => $entry)
			{
				$id = $entry['grantor'];
				$owner = $GLOBALS['egw']->accounts->id2name($id);
				$calendars[] = HTTP_WebDAV_Server::mkprop('href',
					$this->base_uri.'/'.$owner.'/');
			}
*/
		}
		else
		{
			$addressbooks[] = HTTP_WebDAV_Server::mkprop('href',
				$this->base_uri.'/'.$account['account_lid'].'/');
			$calendars[] = HTTP_WebDAV_Server::mkprop('href',
				$this->base_uri.'/'.$account['account_lid'].'/');
		}
		$displayname = translation::convert($account['account_fullname'], translation::charset(),'utf-8');

		return $this->add_principal('users/'.$account['account_lid'], array(
			HTTP_WebDAV_Server::mkprop('displayname',$displayname),
			HTTP_WebDAV_Server::mkprop('alternate-URI-set',array(
				HTTP_WebDAV_Server::mkprop('href','MAILTO:'.$account['account_email']))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',$calendars),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-address-set',array(
				HTTP_WebDAV_Server::mkprop('href','MAILTO:'.$account['account_email']),
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/users/'.$account['account_lid'].'/'),
				HTTP_WebDAV_Server::mkprop('href','urn:uuid:'.$account['account_lid']))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'schedule-outbox-URL',array(
				HTTP_WebDAV_Server::mkprop(groupdav::DAV,'href',$this->base_uri.'/calendar/'))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'email-address-set',array(
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'email-address',$account['account_email']))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'last-name',$account['account_lastname']),
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'first-name',$account['account_firstname']),
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'record-type','user'),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-type','INDIVIDUAL'),
			HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-home-set',$addressbooks),
			$this->principal_set('group-membership', $this->accounts->memberships($account['account_id']),
				'calendar', $account['account_id']),	// add proxy-rights
			HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop('acl-principal-prop-set'))))))),
		), array(), $this->get_etag($account));
	}

	/**
	 * Add collection of a single group to a collection
	 *
	 * @param array $account
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_group(array $account)
	{
		$displayname = translation::convert(lang('Group').' '.$account['account_lid'],	translation::charset(), 'utf-8');

		return $this->add_principal('groups/'.$account['account_lid'], array(
			HTTP_WebDAV_Server::mkprop('displayname',$displayname),
			HTTP_WebDAV_Server::mkprop('alternate-URI-set',''),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-home-set',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'record-type','group'),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-type','GROUP'),
			$this->principal_set('group-member-set', $this->accounts->members($account['account_id'])),
		), array(), $this->get_etag($account));
	}

	/**
	 * Add a collection
	 *
	 * @param string $path
	 * @param array $props=array() extra properties 'resourcetype' is added anyway
	 * @param array $additional_resource_types=array() additional resource-types, collection and principal are always added
	 * @param string $etag=''
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_collection($path, array $props = array(), array $additional_resource_types=array(), $etag='')
	{
		// resourcetype: collection + $additional_resource_types
		$props[] = HTTP_WebDAV_Server::mkprop('resourcetype',array_merge(array(
			HTTP_WebDAV_Server::mkprop('collection',''),
		),$additional_resource_types));

		// props for all collections: current-user-principal and principal-collection-set
		$props[] = HTTP_WebDAV_Server::mkprop('current-user-principal',array(
			HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/users/'.$GLOBALS['egw_info']['user']['account_lid'].'/')));
		$props[] = HTTP_WebDAV_Server::mkprop('principal-collection-set',array(
			HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/')));

		// required per WebDAV standard
		$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength', '');
		$props[] = HTTP_WebDAV_Server::mkprop('getlastmodified', '');
		$props[] = HTTP_WebDAV_Server::mkprop('getcontenttype', '');
		$props[] = HTTP_WebDAV_Server::mkprop('getetag', $etag);

		if ($this->debug > 1) error_log(__METHOD__."(path='$path', props=".array2string($props).')');

		return array(
			'path' => $path,
			'props' => $props,
		);
	}

	/**
	 * Add a principal collection
	 *
	 * @param string $principal relative to principal-collection-set, eg. "users/username"
	 * @param array $props=array() extra properties 'resourcetype' is added anyway
	 * @param array $additional_resource_types=array() additional resource-types, collection and principal are always added
	 * @param string $principal_url=null include given principal url, relative to principal-collection-set
	 * @param string $etag=''
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_principal($principal, array $props = array(), array $additional_resource_types=array(), $etag='')
	{
		$additional_resource_types[] = HTTP_WebDAV_Server::mkprop('principal', '');

		if (!$principal_url) $principal_url = $principal;

		$props[] = HTTP_WebDAV_Server::mkprop('principal-URL',array(
			HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/'.$principal.'/')));

		return $this->add_collection('/principals/'.$principal.'/', $props, $additional_resource_types, $etag);
	}

	/**
	 * Add a proxy collection for given principal and type
	 *
	 * A proxy is a user or group who has the right to act on behalf of the user
	 *
	 * @param string $principal relative to principal-collection-set, eg. "users/username"
	 * @param string $type eg. 'calendar-proxy-read' or 'calendar-proxy-write'
	 * @param array $proxys=array()
	 * @return array with values for 'path' and 'props'
	 */
	protected function add_proxys($principal, $type, array $proxys=array())
	{
		list($app,,$what) = explode('-', $type);
		$right = $what == 'write' ? EGW_ACL_EDIT : EGW_ACL_READ;
		$mask = $what == 'write' ? EGW_ACL_EDIT : EGW_ACL_EDIT|EGW_ACL_READ;	// do NOT report write+read in read
		//echo "<p>type=$type --> app=$app, what=$what --> right=$right, mask=$mask</p>\n";

		list($account_type,$account) = explode('/', $principal);
		$account = $GLOBALS['egw']->accounts->name2id($account, 'account_lid', $account_type[0]);

		$proxys = array();
		foreach($GLOBALS['egw']->acl->get_all_location_rights($account, $app, $app != 'addressbook') as $account_id => $rights)
		{
			if ($account_id !== 'run' && $account_id != $account && ($rights & $mask) == $right &&
				($account_lid = $GLOBALS['egw']->accounts->id2name($account_id)))
			{
				$proxys[$account_id] = $account_lid;
				// for groups add members too, if app is not addressbook
				if ($account_id < 0 && $app != 'addressbook')
				{
					foreach($GLOBALS['egw']->accounts->members($account_id) as $account_id => $account_lid)
					{
						$proxys[$account_id] = $account_lid;
					}
				}
			}
			//echo "<p>$account_id ($account_lid): (rights=$rights & mask=$mask) == right=$right --> ".array2string(($rights & $mask) == $right)."</p>\n";
		}
		return $this->add_principal($principal.'/'.$type, array(
				$this->principal_set('group-member-set', $proxys),
			), array(
				HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER, $type, ''),
			),'EGw-'.md5(serialize($proxys)).'-wGE');
	}

	/**
	 * Create a named property with set or principal-urls
	 *
	 * @param string $prop egw. 'group-member-set' or 'membership'
	 * @param array $accounts=array() account_id => account_lid pairs
	 * @param string|array $app_proxys=null applications for which proxys should be added
	 * @param int $account who is the proxy
	 */
	protected function principal_set($prop, array $accounts=array(), $add_proxys=null, $account=null)
	{
		$set = array();
		foreach($accounts as $account_id => $account_lid)
		{
			$set[] = HTTP_WebDAV_Server::mkprop('href', $this->base_uri.'/principals/'.($account_id < 0 ? 'groups/' : 'users/').$account_lid);
		}
		if ($add_proxys)
		{
			foreach((array)$add_proxys as $app)
			{
				foreach($GLOBALS['egw']->acl->get_grants($app, $app != 'addressbook', $account) as $account_id => $rights)
				{
					if ($account_id != $account && ($rights & EGW_ACL_READ) &&
						($account_lid = $GLOBALS['egw']->accounts->id2name($account_id)))
					{
						$set[] = HTTP_WebDAV_Server::mkprop('href', $this->base_uri.'/principals/'.
							($account_id < 0 ? 'groups/' : 'users/').
							$account_lid.'/'.$app.'-proxy-'.($rights & EGW_ACL_EDIT ? 'write' : 'read'));
					}
				}
			}
		}
		return HTTP_WebDAV_Server::mkprop($prop, $set);
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
			$options['depth'] = 0;
			$files = array_merge($files,$this->propfind_users('','',$options));
			$files = array_merge($files,$this->propfind_groups('','',$options));
			//$files = array_merge($this->propfind_resources('','',$options));
			//$files = array_merge($this->propfind_uids('','',$options));
		}
		return $files;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		if (!is_array($account = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $account;
		}
		$name = $GLOBALS['egw']->translation->convert(
			trim($account['account_firstname'].' '.$account['account_lastname']),
			$GLOBALS['egw']->translation->charset(),'utf-8');
		$options['data'] = 'Principal: '.$account['account_lid'].
			"\nURL: ".$this->base_uri.$options['path'].
			"\nName: ".$name.
			"\nEmail: ".$account['account_email'].
			"\nMemberships: ".implode(', ',$this->accounts->memberships($id))."\n";
		$options['mimetype'] = 'text/plain; charset=utf-8';
		header('Content-Encoding: identity');
		header('ETag: '.$this->get_etag($account));
		return true;
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null)
	{
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
		return false;
	}

	/**
	 * Read an entry
	 *
	 * @param string/int $id
	 * @return array/boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		return false;
		//return $this->accounts->read($id);
	}

	/**
	 * Check if user has the neccessary rights on an entry
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array/int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$entry)
	{
		if ($acl != EGW_ACL_READ)
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
	 * @param array/int $event array with event or cal_id
	 * @return string/boolean string with etag or false
	 */
	function get_etag($account)
	{
		if (!is_array($account))
		{
			$account = $this->read($account);
		}
		return 'EGw-'.$account['account_id'].':'.md5(serialize($account)).
			// add md5 from calendar grants, as they are listed as memberships
			':'.md5(serialize($GLOBALS['egw']->acl->get_grants('calendar', true, $account['account_id']))).
			// as the principal of current user is influenced by GroupDAV prefs, we have to include them in the etag
			($account['account_id'] == $GLOBALS['egw_info']['user']['account_id'] ?
				':'.md5(serialize($GLOBALS['egw_info']['user']['preferences']['groupdav'])) : '').'-wGE';
	}
}