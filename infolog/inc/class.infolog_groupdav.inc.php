<?php
/**
 * eGroupWare: GroupDAV access: infolog handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

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
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 */
	function __construct($app,$debug=null,$base_uri=null)
	{
		parent::__construct($app,$debug,$base_uri);

		$this->bo = new infolog_bo();
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
		return '/infolog/'.$name.'.ics';
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
		$filter = array(
			'info_type'	=> 'task',
		);
		if ($id) $filter['info_id'] = $id;	// propfind on a single id

		// ToDo: add parameter to only return id & etag
		if (($tasks = $this->bo->search($params=array(
			'order'		=> 'info_datemodified',
			'sort'		=> 'DESC',
			'filter'    => 'own',	// filter my: entries user is responsible for,
									// filter own: entries the user own or is responsible for
			'col_filter'	=> $filter,
		))))
		{
			foreach($tasks as $task)
			{
				$files['files'][] = array(
	            	'path'  => self::get_path($task),
	            	'props' => array(
	            		HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($task)),
	            		HTTP_WebDAV_Server::mkprop('getcontenttype',$this->agent != 'kde' ?
	            			'text/calendar; charset=utf-8; component=VTODO' : 'text/calendar'),	// Konqueror (3.5) dont understand it otherwise
						// getlastmodified and getcontentlength are required by WebDAV and Cadaver eg. reports 404 Not found if not set
						HTTP_WebDAV_Server::mkprop('getlastmodified', $task['info_datemodified']),
						HTTP_WebDAV_Server::mkprop('getcontentlength',''),
	            	),
				);
			}
		}
		return true;
	}

	/**
	 * Handle get request for a task / infolog entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id)
	{
		if (!is_array($task = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $task;
		}
		$handler = $this->_get_handler();
		$options['data'] = $handler->exportVTODO($id,'2.0',false,false);	// keep UID the client set and no extra charset attributes
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
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null)
	{
		$ok = $this->_common_get_put_delete('PUT',$options,$id);
		if (!is_null($ok) && !is_array($ok))
		{
			return $ok;
		}
		$handler = $this->_get_handler();
		if (!($info_id = $handler->importVTODO($options['content'],is_numeric($id) ? $id : -1)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vtodo($options[content]) returned false");
			return '403 Forbidden';
		}
		header('ETag: '.$this->get_etag($info_id));
		if (is_null($ok) || $id != $info_id)
		{
			header('Location: '.$this->base_uri.self::get_path($info_id));
			return '201 Created';
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
		return $this->bo->read($id,false);
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
		return $this->bo->check_access($task,$acl);
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
			$info = $this->bo->read($info);
		}
		if (!is_array($info) || !isset($info['info_id']) || !isset($info['info_datemodified']))
		{
			return false;
		}
		return '"'.$info['info_id'].':'.$info['info_datemodified'].'"';
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