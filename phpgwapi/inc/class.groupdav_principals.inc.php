<?php
/**
 * EGroupware: GroupDAV access: groupdav/caldav/carddav principals handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * EGroupware: GroupDAV access: groupdav/caldav/carddav principals handlers
 */
class groupdav_principals extends groupdav_handler
{

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
			case 'resources':
				$files['files'] = $this->propfind_resources($name,$rest,$options);
				break;
			case '__uids__':
				$files['files'] = $this->propfind_uids($name,$rest,$options);
				break;
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

		list(,,$id) = explode('/',$path);
		if ($id && !($id = $this->accounts->id2name($id)))
		{
			return false;
		}
		foreach($id ? array($this->accounts->read($id)) : $this->accounts->search(array('type' => 'accounts')) as $account)
		{
			$displayname = translation::convert($account['account_fullname'],
				translation::charset(),'utf-8');
			
			$props = array(
				HTTP_WebDAV_Server::mkprop('displayname',$displayname),
				HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($account)),
				HTTP_WebDAV_Server::mkprop('resourcetype',array(
					HTTP_WebDAV_Server::mkprop('principal', ''))),
				HTTP_WebDAV_Server::mkprop('alternate-URI-set',''),
				HTTP_WebDAV_Server::mkprop('principal-URL',$this->base_uri.'/principals/'.$account['account_lid']),
				HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',array(
					HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
				HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-home-set',array(
					HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			);
			
			foreach($this->accounts->memberships($account['account_id']) as $gid => $group)
			{
				$props[] = HTTP_WebDAV_Server::mkprop('group-membership',$this->base_uri.'/groups/'.$group);
			}
			$files['files'][] = array(
	           	'path'  => '/principals/'.$account['account_lid'],
	           	'props' => $props,
			);
			if ($this->debug > 1) error_log(__METHOD__."($path) path=/principals/".$account['account_lid'].', props='.array2string($props));
		}
		return files;
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
		//echo "<p>".__METHOD__."($name,$rest,".array2string($options).")</p>\n";
		if (empty($name))
		{
			$files = array();
			// add /pricipals/users/ entry
			$files[] = $this->add_collection('/principals/users/',array(
				HTTP_WebDAV_Server::mkprop('current-user-principal',array(
					HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/users/'.$GLOBALS['egw_info']['user']['account_lid'].'/'))),
			));
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
			switch((string)$rest)
			{
				case '':
					$files[] = $this->add_account($account);
					if ($options['depth'])
					{
						$files[] = $this->add_collection('/principals/users/'.$account['account_lid'].'/calendar-proxy-read');
						$files[] = $this->add_collection('/principals/users/'.$account['account_lid'].'/calendar-proxy-write');
					}
					break;
				case 'calendar-proxy-read':
				case 'calendar-proxy-write':
					$files = array();
					$files[] = $this->add_collection('/principals/users/'.$account['account_lid'].'/'.$rest);
					// add proxys
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
			$files[] = $this->add_collection('/principals/groups/',array(
				HTTP_WebDAV_Server::mkprop('current-user-principal',array(
					HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/users/'.$GLOBALS['egw_info']['user']['account_lid'].'/'))),
			));
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
			switch((string)$rest)
			{
				case '':
					$files[] = $this->add_group($account);
					$files[] = $this->add_collection('/principals/groups/'.$account['account_lid'].'/calendar-proxy-read');
					$files[] = $this->add_collection('/principals/groups/'.$account['account_lid'].'/calendar-proxy-write');
					break;
				case 'calendar-proxy-read':
				case 'calendar-proxy-write':
					$files = array();
					$files[] = $this->add_collection('/principals/groups/'.$account['account_lid'].'/'.$rest);
					// add proxys
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
		//echo "<p>".__METHOD__."(".array2string($account).")</p>\n";

		$displayname = translation::convert($account['account_fullname'],
				translation::charset(),'utf-8');
		$memberships = array();
		foreach($this->accounts->memberships($account['account_id']) as $gid => $group)
		{
			if ($group)
			{
				$memberships[] = HTTP_WebDAV_Server::mkprop('href',
					$this->base_uri.'/principals/groups/'.$group);
			}
		}
		$props = array(
			HTTP_WebDAV_Server::mkprop('displayname',$displayname),
			HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($account)),
			HTTP_WebDAV_Server::mkprop('resourcetype',array(
					HTTP_WebDAV_Server::mkprop('principal', ''))),
			HTTP_WebDAV_Server::mkprop('alternate-URI-set',array(
				HTTP_WebDAV_Server::mkprop('href','MAILTO:'.$account['account_email']))),
			HTTP_WebDAV_Server::mkprop('principal-URL',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/users/'.$account['account_lid'].'/'))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
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
			HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-home-set',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			HTTP_WebDAV_Server::mkprop('group-member-ship', $memberships),
			HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop('acl-principal-prop-set'))))))),
		);
		if ($this->debug > 1) error_log(__METHOD__."($path) path=/principals/users/".$account['account_lid'].', props='.array2string($props));
		return array(
			'path' => '/principals/users/'.$account['account_lid'].'/',
			'props' => $props,
		);
	}

	/**
	 * Add collection of a single group to a collection
	 *
	 * @param array $account
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_group(array $account)
	{
		$displayname = translation::convert(lang('Group').' '.$account['account_lid'],
			translation::charset(),'utf-8');
		$members = array();
		foreach($this->accounts->members($account['account_id']) as $gid => $user)
		{
			if ($user)
			{
				$members[] = HTTP_WebDAV_Server::mkprop('href',
					$this->base_uri.'/principals/users/'.$user);
			}
		}
		$props = array(
			HTTP_WebDAV_Server::mkprop('displayname',$displayname),
			HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($account)),
			HTTP_WebDAV_Server::mkprop('resourcetype',array(
					HTTP_WebDAV_Server::mkprop('principal', ''))),
			HTTP_WebDAV_Server::mkprop('alternate-URI-set',''),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			HTTP_WebDAV_Server::mkprop(groupdav::CARDDAV,'addressbook-home-set',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/'.$account['account_lid'].'/'))),
			HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'record-type','group'),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-type','GROUP'),
			HTTP_WebDAV_Server::mkprop('group-member-set', $members),
			//HTTP_WebDAV_Server::mkprop('principal-URL',array(self::mkprop('href',$this->principalURL))),
		);
		$files['files'][] = array(
			'path'  => '/principals/groups/'.$account['account_lid'].'/',
			'props' => $props,
		);
		if ($this->debug > 1) error_log(__METHOD__."($path) path=/principals/groups/".$account['account_lid'].', props='.array2string($props));
		return array(
			'path' => '/principals/groups/'.$account['account_lid'].'/',
			'props' => $props,
		);
	}

	/**
	 * Add a collection
	 *
	 * @param string $path
	 * @param array $props=array() extra properties 'resourcetype' is added anyway
	 * @return array
	 */
	protected function add_collection($path,$props=array())
	{
		//echo "<p>".__METHOD__."($path,".array($props).")</p>\n";
		$props[] = HTTP_WebDAV_Server::mkprop('resourcetype',array(
			HTTP_WebDAV_Server::mkprop('collection',''),
			HTTP_WebDAV_Server::mkprop('resourcetype',array(
					HTTP_WebDAV_Server::mkprop('principal', ''))),
		));
		return array(
			'path' => $path,
			'props' => $props,
		);
	}

	/**
	 * Do propfind of /pricipals
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
		$files[] = $this->add_collection('/principals/',array(
			HTTP_WebDAV_Server::mkprop('current-user-principal',array(
				HTTP_WebDAV_Server::mkprop('href',$this->base_uri.'/principals/users/'.$GLOBALS['egw_info']['user']['account_lid'].'/'))),
		));

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
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id)
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
		return 'EGw-'.$account['account_id'].':'.md5(serialize($account)).'-wGE';
	}
}