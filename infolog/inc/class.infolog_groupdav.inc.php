<?php
/**
 * eGroupWare: GroupDAV access: infolog handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package infolog
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * @param string $principalURL=null pricipal url of handler
	 */
	function __construct($app,$debug=null,$base_uri=null,$principalURL=null)
	{
		parent::__construct($app,$debug,$base_uri,$principalURL);

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
		return $name.'.ics';
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
		$starttime = microtime(true);

		$myself = ($user == $GLOBALS['egw_info']['user']['account_id']);

		if ($path == '/infolog/')
		{
			$task_filter= 'open';
		}
		else
		{
			$task_filter= 'own' . ($myself?'':'-open');
		}

		if ($options['filters'])
		{

			foreach($options['filters'] as $filter)
			{
				switch($filter['name'])
				{
					case 'comp-filter':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) comp-filter='{$filter['attrs']['name']}'");

						switch($filter['attrs']['name'])
						{
							case 'VCALENDAR':
								continue;
							case 'VTODO':
								break 3;
							default: // We don't handle this
								return false;
						}
				}
			}
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

		// todo add a filter to limit how far back entries from the past get synced
		$filter = array(
			'info_type'	=> 'task',
		);

		//if (!$myself) $filter['info_owner'] = $user;

		if ($id) $filter['info_id'] = $id;	// propfind on a single id

		// ToDo: add parameter to only return id & etag
		if (($tasks =& $this->bo->search($params=array(
			'order'		=> 'info_datemodified',
			'sort'		=> 'DESC',
			'filter'    => $task_filter,	// filter my: entries user is responsible for,
											// filter own: entries the user own or is responsible for
			'date_format' => 'server',
			'col_filter'	=> $filter,
		))))
		{
			foreach($tasks as &$task)
			{
				$props = array(
					HTTP_WebDAV_Server::mkprop('getetag',$this->get_etag($task)),
					HTTP_WebDAV_Server::mkprop('getcontenttype',$this->agent != 'kde' ?
							'text/calendar; charset=utf-8; component=VTODO' : 'text/calendar'),	// Konqueror (3.5) dont understand it otherwise
							// getlastmodified and getcontentlength are required by WebDAV and Cadaver eg. reports 404 Not found if not set
							HTTP_WebDAV_Server::mkprop('getlastmodified', $task['info_datemodified']),
							HTTP_WebDAV_Server::mkprop('resourcetype',''),	// DAVKit requires that attribute!
							HTTP_WebDAV_Server::mkprop('getcontentlength',''),
				);
				if ($calendar_data)
				{
					$handler = $this->_get_handler();
					$content = $handler->exportVTODO($task,'2.0','PUBLISH');
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength',bytes($content));
					$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data',$content);
				}
				else
				{
					$props[] = HTTP_WebDAV_Server::mkprop('getcontentlength', ''); // expensive to calculate and no CalDAV client uses it
				}
				$files['files'][] = array(
	            	'path'  => $path.self::get_path($task),
	            	'props' => $props,
				);
			}
		}
		if ($this->debug) error_log(__METHOD__."($path) took ".(microtime(true) - $starttime).' to return '.count($files['files']).' items');
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
		$options['data'] = $handler->exportVTODO($id,'2.0','PUBLISH');
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
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));

		$oldTask = $this->_common_get_put_delete('PUT',$options,$id);
		if (!is_null($oldTask) && !is_array($oldTask))
		{
			return $oldTask;
		}

		$handler = $this->_get_handler();
		$vTodo = htmlspecialchars_decode($options['content']);

		if (is_array($oldTask))
		{
			$taskId = $oldTask['info_id'];
			$retval = true;
		}
		else
		{
			// new entry?
			if (($foundTasks = $handler->searchVTODO($vTodo)))
			{
				if (($taskId = array_shift($foundTasks)) &&
					($oldTask = $this->bo->read($taskId)))
				{
					$retval = '301 Moved Permanently';
				}
				else
				{
					// to be safe
					$taskId = -1;
					$retval = '201 Created';
				}
			}
			else
			{
				// new entry
				$taskId = -1;
				$retval = '201 Created';
			}
		}

		if (!($infoId = $handler->importVTODO($vTodo, $taskId, false, $user)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) import_vtodo($options[content]) returned false");
			return '403 Forbidden';
		}

		if ($infoId != $taskId) $retval = '201 Created';

		header('ETag: '.$this->get_etag($infoId));
		if ($retval !== true)
		{
			$path = preg_replace('|(.*)/[^/]*|', '\1/', $options['path']);
			header('Location: '.$this->base_uri.$path.self::get_path($infoId));
			return $retval;
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
		return $this->bo->read($id,false,'server');
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
			$info = $this->bo->read($info,true,'server');
		}
		if (!is_array($info) || !isset($info['info_id']) || !isset($info['info_datemodified']))
		{
			return false;
		}
		return 'EGw-'.$info['info_id'].':'.$info['info_datemodified'].'-wGE';
	}

	/**
	 * Add extra properties for calendar collections
	 *
	 * @param array $props=array() regular props by the groupdav handler
	 * @param string $displayname
	 * @param string $base_uri=null base url of handler
	 * @return array
	 */
	static function extra_properties(array $props=array(), $displayname, $base_uri=null)
	{
		// calendar description
		$displayname = $GLOBALS['egw']->translation->convert(lang('Tasks of') . ' ' .
			$displayname,
			$GLOBALS['egw']->translation->charset(),'utf-8');
		$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-description',$displayname);
		// email of the current user, see caldav-sheduling draft
		$props[] =	HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-user-address-set',array(
			HTTP_WebDAV_Server::mkprop('href','MAILTO:'.$GLOBALS['egw_info']['user']['email'])));
		// supported components, currently only VEVENT
		$props[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'supported-calendar-component-set',array(
			// HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VEVENT')),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VTODO')),
		));

		$props[] = HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',
					HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-multiget'))))));

		return $props;
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