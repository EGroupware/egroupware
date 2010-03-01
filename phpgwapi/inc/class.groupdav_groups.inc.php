<?php
/**
 * eGroupWare: GroupDAV access: groupdav/caldav/carddav groups handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare: GroupDAV access: groupdav/caldav/carddav groups handlers
 */
class groupdav_groups extends groupdav_handler
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
	 * @param string $principalURL=null pricipal url of handler
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
		list(,,$user) = explode('/',$path);
		foreach($user ? array($this->accounts->read($user)) : $this->accounts->search(array('type' => 'groups')) as $account)
		{
	      $props = array(
				HTTP_WebDAV_Server::mkprop('displayname',lang('Group').' '.$account['account_lid']),
				HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($account)),
				HTTP_WebDAV_Server::mkprop('resourcetype','principal'),
				HTTP_WebDAV_Server::mkprop('alternate-URI-set',''),
				HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-home-set',$this->base_uri.'/'.$account['account_lid'].'/calendar/'),
				//HTTP_WebDAV_Server::mkprop('principal-URL',array(self::mkprop('href',$this->principalURL))),
			);
			foreach($this->accounts->members($account['account_id']) as $uid => $user)
			{
				$props[] = HTTP_WebDAV_Server::mkprop('group-membership',$this->base_uri.'/principals/'.$user);
			}
			$files['files'][] = array(
	           	'path'  => '/groups/'.$account['account_lid'],
	           	'props' => $props,
			);
			if ($this->debug > 1) error_log(__METHOD__."($path) path=/principals/".$account['account_lid'].', props='.array2string($props));
		}
		return true;
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
		$options['data'] = 'Principal: '.$account['account_lid'].
			"\nURL: ".$this->base_uri.$options['path'].
			"\nName: ".lang('Group').' '.$account['account_lid'].
			($account['account_email'] ? "\nEmail: ".$account['account_email'] : '').
			"\nMembers: ".implode(', ',$this->accounts->members($id))."\n";
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
		return $this->accounts->read($id);
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
		if (!is_array($entry) && !$this->accounts->name2id($entry,'account_lid','g'))
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
		return '"'.$account['account_id'].':'.md5(serialize($account)).'"';
	}
}