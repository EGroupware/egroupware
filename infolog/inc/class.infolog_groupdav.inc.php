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

require_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');

/**
 * eGroupWare: GroupDAV access: infolog handler
 */
class infolog_groupdav extends groupdav_handler
{
	/**
	 * bo class of the application
	 *
	 * @var boinfolog
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

		$this->bo =& new boinfolog();
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
	function propfind($path,$options,&$files,$user)
	{
		$icalvc =& $this->_instanciate_icalvc($user);
		// ToDo: add parameter to only return id & etag
		if (($tasks = $this->bo->search($icalvc->_caldef['rscs']['infolog.boinfolog'])))
		{
			foreach($tasks as $task)
			{
				$files['files'][] = array(
	            	'path'  => '/infolog/'.$task['info_id'],
	            	'props' => array(
	            		HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($task)),
	            		HTTP_WebDAV_Server::mkprop('getcontenttype', 'text/calendar'),
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
		include_once(EGW_INCLUDE_ROOT.'/icalsrv/inc/class.boinfolog_vtodos.inc.php');
		$handler =& new boinfolog_vtodos($this->bo);
		$vtodo = $handler->export_vtodo($task,UMM_UID2UID);
		$options['data'] = $handler->render_velt2vcal($vtodo);
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
		include_once(EGW_INCLUDE_ROOT.'/icalsrv/inc/class.boinfolog_vtodos.inc.php');
		$handler =& new boinfolog_vtodos($this->bo);
		$vcalelm =& $handler->parse_vcal2velt($options['content']);
		if (!($info_id = $handler->import_vtodo($vcalelm, $uid_mapping_import=UMM_UID2UID, $reimport_missing_events=false, $id)) > 0)
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vtodo($options[content]) returned false");
			return false;	// something went wrong ...
		}
		header('ETag: '.$this->get_etag($info_id));
		if (is_null($ok) || $id != $info_id)
		{
			header('Location: '.$this->base_uri.'/infolog/'.$info_id);
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
}