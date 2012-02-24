<?php
/**
 * EGroupware - InfoLog - Business object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package infolog
 * @copyright (c) 2003-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

define('EGW_ACL_UNDELETE',EGW_ACL_CUSTOM_1);	// undelete right

/**
 * This class is the BO-layer of InfoLog
 */
class infolog_bo
{
	var $enums;
	var $status;
	/**
	 * Instance of our so class
	 *
	 * @var infolog_so
	 */
	var $so;
	var $vfs;
	var $vfs_basedir='/infolog';
	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	/**
	 * Cached timezone data
	 *
	 * @var array id => data
	 */
	protected static $tz_cache = array();
	/**
	 * current time as timestamp in user-time and server-time
	 *
	 * @var int
	 */
	var $user_time_now;
	var $now;
	/**
	 * name of timestamps in an InfoLog entry
	 *
	 * @var array
	 */
	var $timestamps = array('info_startdate','info_enddate','info_datemodified','info_datecompleted','info_created');
	/**
	 * fields the responsible user can change
	 *
	 * @var array
	 */
	var $responsible_edit=array('info_status','info_percent','info_datecompleted');
	/**
	 * Fields to exclude from copy, if an entry is copied, the ones below are excluded by default.
	 *
	 * @var array
	 */
	var $copy_excludefields = array('info_id', 'info_uid', 'info_etag', 'caldav_name', 'info_created', 'info_creator', 'info_datemodified', 'info_modifier');
	/**
	 * Fields to exclude from copy, if a sub-entry is created, the ones below are excluded by default.
	 *
	 * @var array
	 */
	var $sub_excludefields = array('info_id', 'info_uid', 'info_etag', 'caldav_name', 'info_created', 'info_creator', 'info_datemodified', 'info_modifier');
	/**
	 * Additional fields to $sub_excludefields to exclude, if no config stored
	 *
	 * @var array
	 */
	var $default_sub_excludefields = array('info_des');
	/**
	 * implicit ACL rights of the responsible user: read or edit
	 *
	 * @var string
	 */
	var $implicit_rights='read';
	/**
	 * Custom fields read from the infolog config
	 *
	 * @var array
	 */
	var $customfields=array();
	/**
	 * Group owners for certain types read from the infolog config
	 *
	 * @var array
	 */
	var $group_owners=array();
	/**
	 * Current user
	 *
	 * @var int
	 */
	var $user;
	/**
	 * History loggin: ''=no, 'history'=history & delete allowed, 'history_admin_delete', 'history_no_delete'
	 *
	 * @var string
	 */
	var $history;
	/**
	 * Instance of infolog_tracking, only instaciated if needed!
	 *
	 * @var infolog_tracking
	 */
	var $tracking;
	/**
	 * Maximum number of line characters (-_+=~) allowed in a mail, to not stall the layout.
	 * Longer lines / biger number of these chars are truncated to that max. number or chars.
	 *
	 * @var int
	 */
	var $max_line_chars = 40;

	/**
	 * Available filters
	 *
	 * @var array filter => label pairs
	 */
	var $filters = array(
		'none'                     => 'no Filter',
		'done'                     => 'done',
		'responsible'              => 'responsible',
		'responsible-open-today'   => 'responsible open',
		'responsible-open-overdue' => 'responsible overdue',
		'responsible-upcoming'     => 'responsible upcoming',
		'responsible-open-upcoming'=> 'responsible open and upcoming',
		'delegated'                => 'delegated',
		'delegated-open-today'     => 'delegated open',
		'delegated-open-overdue'   => 'delegated overdue',
		'delegated-upcoming'       => 'delegated upcomming',
		'delegated-open-upcoming'  => 'delegated open and upcoming',
		'own'                      => 'own',
		'own-open-today'           => 'own open',
		'own-open-overdue'         => 'own overdue',
		'own-upcoming'             => 'own upcoming',
		'own-open-upcoming'		   => 'own open and upcoming',
		'open-today'               => 'open(status)',
		'open-overdue'             => 'overdue',
		'upcoming'                 => 'upcoming',
		'open-upcoming'			   => 'open and upcoming',
		'bydate'                   => 'startdate',
	);

	/**
	 * Constructor Infolog BO
	 *
	 * @param int $info_id
	 * @param boolean $instanciate_link=true should the link class be instanciated, used by the link-registry to prevent infinit recursion
	 */
	function __construct($info_id = 0,$instanciate_link=true)
	{
		$this->enums = $this->stock_enums = array(
			'priority' => array (
				3 => 'urgent',
				2 => 'high',
				1 => 'normal',
				0 => 'low'
			),
			'confirm'   => array(
				'not' => 'not','accept' => 'accept','finish' => 'finish',
				'both' => 'both' ),
			'type'      => array(
				'task' => 'task','phone' => 'phone','note' => 'note','email' => 'email'
			/*	,'confirm' => 'confirm','reject' => 'reject','fax' => 'fax' not implemented so far */ )
		);
		$this->status = $this->stock_status = array(
			'defaults' => array(
				'task' => 'not-started', 'phone' => 'not-started', 'note' => 'done','email' => 'done'),
			'task' => array(
				'offer' => 'offer',				// -->  NEEDS-ACTION
				'not-started' => 'not-started',	// iCal NEEDS-ACTION
				'ongoing' => 'ongoing',			// iCal IN-PROCESS
				'done' => 'done',				// iCal COMPLETED
				'cancelled' => 'cancelled',		// iCal CANCELLED
				'billed' => 'billed',			// -->  DONE
				'template' => 'template',		// -->  cancelled
				'nonactive' => 'nonactive',		// -->  cancelled
				'archive' => 'archive' ), 		// -->  cancelled
			'phone' => array(
				'not-started' => 'call',		// iCal NEEDS-ACTION
				'ongoing' => 'will-call',		// iCal IN-PROCESS
				'done' => 'done', 				// iCal COMPLETED
				'billed' => 'billed' ),			// -->  DONE
			'note' => array(
				'ongoing' => 'ongoing',			// iCal has no status on notes
				'done' => 'done' ),
			'email' => array(
				'ongoing' => 'ongoing',			// iCal has no status on notes
				'done' => 'done' ),
		);
		if (($config_data = config::read('infolog')))
		{
			if (isset($config_data['status']) && is_array($config_data['status']))
			{
				foreach($config_data['status'] as $key => $data)
				{
					if (!is_array($this->status[$key]))
					{
						$this->status[$key] = array();
					}
					$this->status[$key] = array_merge($this->status[$key],(array)$config_data['status'][$key]);
				}
			}
			if (isset($config_data['types']) && is_array($config_data['types']))
			{
				//echo "stock-types:<pre>"; print_r($this->enums['type']); echo "</pre>\n";
				//echo "config-types:<pre>"; print_r($config_data['types']); echo "</pre>\n";
				$this->enums['type'] += $config_data['types'];
				//echo "types:<pre>"; print_r($this->enums['type']); echo "</pre>\n";
			}
			if ($config_data['group_owners']) $this->group_owners = $config_data['group_owners'];

			$this->customfields = config::get_customfields('infolog');
			if ($this->customfields)
			{
				foreach($this->customfields as $name => $field)
				{
					// old infolog customefield record
					if(empty($field['type']))
					{
						if (count($field['values'])) $field['type'] = 'select'; // selectbox
						elseif ($field['rows'] > 1) $field['type'] = 'textarea'; // textarea
						elseif (intval($field['len']) > 0) $field['type'] = 'text'; // regular input field
						else $field['type'] = 'label'; // header-row
						$field['type2'] = $field['typ'];
						unset($field['typ']);
						$this->customfields[$name] = $field;
						$save_config = true;
					}
				}
				if ($save_config) config::save_value('customfields',$this->customfields,'infolog');
			}
			if (is_array($config_data['responsible_edit']))
			{
				$this->responsible_edit = array_merge($this->responsible_edit,$config_data['responsible_edit']);
			}
			if (is_array($config_data['copy_excludefields']))
			{
				$this->copy_excludefields = array_merge($this->copy_excludefields,$config_data['copy_excludefields']);
			}
			if (is_array($config_data['sub_excludefields']) && $config_data['sub_excludefields'])
			{
				$this->sub_excludefields = array_merge($this->sub_excludefields,$config_data['sub_excludefields']);
			}
			else
			{
				$this->sub_excludefields = array_merge($this->sub_excludefields,$this->default_sub_excludefields);
			}
			if ($config_data['implicit_rights'] == 'edit')
			{
				$this->implicit_rights = 'edit';
			}
			$this->history = $config_data['history'];
		}
		// sort types by there translation
		foreach($this->enums['type'] as $key => $val)
		{
			if (($val = lang($key)) != $key.'*') $this->enums['type'][$key] = lang($key);
		}
		natcasesort($this->enums['type']);

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->now = time();
		$this->user_time_now = egw_time::server2user($this->now,'ts');

		$this->grants = $GLOBALS['egw']->acl->get_grants('infolog',$this->group_owners ? $this->group_owners : true);
		$this->so = new infolog_so($this->grants);

		if ($info_id)
		{
			$this->read( $info_id );
		}
		else
		{
			$this->init();
		}
	}

	/**
	 * checks if there are customfields for typ $typ
	 *
	 * @param string $type
	 * @param boolean $links=false if true check only customfields containing links, default false = all custom fields
	 * @return boolean True if there are customfields for $typ, else False
	 */
	function has_customfields($type,$links=false)
	{
		if ($links) $link_types = customfields_widget::get_customfield_link_types();

		foreach($this->customfields as $name => $field)
		{
			if ((!$type || empty($field['type2']) || in_array($type,explode(',',$field['type2']))) &&
				(!$links || in_array($field['type'],$link_types)))
			{
				return True;
			}
		}
		return False;
	}

	/**
	 * check's if user has the requiered rights on entry $info_id
	 *
	 * @param int|array $info data or info_id of infolog entry to check
	 * @param int $required_rights EGW_ACL_{READ|EDIT|ADD|DELETE}
	 * @param int $other uid to check (if info==0) or 0 to check against $this->user
	 * @param int $user=null user whos rights to check, default current user
	 * @return boolean
	 */
	function check_access($info,$required_rights,$other=0,$user=null)
	{
		static $cache = array();

		$info_id = is_array($info) ? $info['info_id'] : $info;

		if (!$user) $user = $this->user;
		if ($user == $this->user)
		{
			$grants = $this->grants;
			if ($info_id) $access =& $cache[$info_id][$required_rights];	// we only cache the current user!
		}
		else
		{
			$grants = $GLOBALS['egw']->acl->get_grants('infolog',$this->group_owners ? $this->group_owners : true,$user);
		}
		if (!$info)
		{
			$owner = $other ? $other : $user;
			$grant = $grants[$owner];
			return $grant & $required_rights;
		}


		if (!isset($access))
		{
			// handle delete for the various history modes
			if ($this->history)
			{
				if (!is_array($info) && !($info = $this->so->read(array('info_id' => $info_id)))) return false;

				if ($info['info_status'] == 'deleted' &&
					($required_rights == EGW_ACL_EDIT ||		// no edit rights for deleted entries
					 $required_rights == EGW_ACL_ADD  ||		// no add rights for deleted entries
					 $required_rights == EGW_ACL_DELETE && ($this->history == 'history_no_delete' || // no delete at all!
					 $this->history == 'history_admin_delete' && (!isset($GLOBALS['egw_info']['user']['apps']['admin']) || $user!=$this->user))))	// delete only for admins
				{
					$access = false;
				}
				elseif ($required_rights == EGW_ACL_UNDELETE)
				{
					if ($info['info_status'] != 'deleted')
					{
						$access = false;	// can only undelete deleted items
					}
					else
					{
						// undelete requires edit rights
						$access = $this->so->check_access( $info,EGW_ACL_EDIT,$this->implicit_rights == 'edit',$grants,$user );
					}
				}
			}
			elseif ($required_rights == EGW_ACL_UNDELETE)
			{
				$access = false;
			}
			if (!isset($access))
			{
				$access = $this->so->check_access( $info,$required_rights,$this->implicit_rights == 'edit',$grants,$user );
			}
		}
		// else $cached = ' (from cache)';
		// error_log(__METHOD__."($info_id,$required_rights,$other,$user) returning$cached ".array2string($access));
		return $access;
	}

	/**
	 * Check if user is responsible for an entry: he or one of his memberships is in responsible
	 *
	 * @param array $info infolog entry as array
	 * @return boolean
	 */
	function is_responsible($info)
	{
		return $this->so->is_responsible($info);
	}

	/**
	 * init internal data to be empty
	 */
	function init()
	{
		$this->so->init();
	}

	/**
	 * convert a link_id value into an info_from text
	 *
	 * @param array &$info infolog entry, key info_from gets set by this function
	 * @param string $not_app='' app to exclude
	 * @param string $not_id='' id to exclude
	 * @return boolean True if we have a linked item, False otherwise
	 */
	function link_id2from(&$info,$not_app='',$not_id='')
	{
		//echo "<p>boinfolog::link_id2from(subject='$info[info_subject]', link_id='$info[info_link_id]', from='$info[info_from]', not_app='$not_app', not_id='$not_id')";

		if ($info['info_link_id'] > 0 &&
			(isset($info['links']) && ($link = $info['links'][$info['info_link_id']]) ||	// use supplied links info
			 ($link = egw_link::get_link($info['info_link_id'])) !== False))	// if link not found in supplied links, we always search!
		{
			if (isset($info['links']) && isset($link['app']))
			{
				$app = $link['app'];
				$id  = $link['id'];
			}
			else
			{
				$nr = $link['link_app1'] == 'infolog' && $link['link_id1'] == $info['info_id'] ? '2' : '1';
				$app = $link['link_app'.$nr];
				$id  = $link['link_id'.$nr];
			}
			$title = egw_link::title($app,$id);

			if ((string)$info['info_custom_from'] === '')	// old entry
			{
				$info['info_custom_from'] = (int) ($title != $info['info_from'] && @htmlentities($title) != $info['info_from']);
			}
			if (!$info['info_custom_from'])
			{
				$info['info_from'] = '';
				$info['info_custom_from'] = 0;
			}
			if ($app == $not_app && $id == $not_id)
			{
				return False;
			}
			$info['info_link'] = array(
				'app'   => $app,
				'id'    => $id,
				'title' => (!empty($info['info_from']) ? $info['info_from'] : $title),
			);
			$info['info_contact'] = $app.':'.$id;

			//echo " title='$title'</p>\n";
			return $info['blur_title'] = $title;
		}
		$info['info_link'] = array('title' => $info['info_from']);
		$info['info_link_id'] = 0;	// link might have been deleted
		$info['info_custom_from'] = (int)!!$info['info_from'];

		return False;
	}

	/**
	 * Create a subject from a description: truncate it and add ' ...'
	 */
	static function subject_from_des($des)
	{
		return substr($des,0,60).' ...';
	}

	/**
	 * Convert the timestamps from given timezone to another and keep dates.
	 * The timestamps are mostly expected to be in server-time
	 * and $fromTZId is only used to qualify dates.
	 *
	 * @param array $values to modify
	 * @param string $fromTZId=null
	 * @param string $toTZId=false
	 * 		TZID timezone name e.g. 'UTC'
	 * 			or NULL for timestamps in user-time
	 * 			or false for timestamps in server-time
	 */
	 function time2time(&$values, $fromTZId=false, $toTZId=null)
	 {

		if ($fromTZId === $toTZId) return;

		$tz = egw_time::$server_timezone;

	 	if ($fromTZId)
		{
			if (!isset(self::$tz_cache[$fromTZId]))
			{
				self::$tz_cache[$fromTZId] = calendar_timezones::DateTimeZone($fromTZId);
			}
			$fromTZ = self::$tz_cache[$fromTZId];
		}
		elseif (is_null($fromTZId))
		{
			$tz = egw_time::$user_timezone;
			$fromTZ = egw_time::$user_timezone;
		}
		else
		{
			$fromTZ = egw_time::$server_timezone;
		}
		if ($toTZId)
		{
			if (!isset(self::$tz_cache[$toTZId]))
			{
				self::$tz_cache[$toTZId] = calendar_timezones::DateTimeZone($toTZId);
			}
			$toTZ = self::$tz_cache[$toTZId];
		}
		elseif (is_null($toTZId))
		{
			$toTZ = egw_time::$user_timezone;
		}
		else
		{
			$toTZ = egw_time::$server_timezone;
		}
		//error_log(__METHOD__.'(values[info_enddate]='.date('Y-m-d H:i:s',$values['info_enddate']).", from=".array2string($fromTZId).", to=".array2string($toTZId).") tz=".$tz->getName().', fromTZ='.$fromTZ->getName().', toTZ='.$toTZ->getName().', userTZ='.egw_time::$user_timezone->getName());
	 	foreach($this->timestamps as $key)
		{
		 	if ($values[$key])
		 	{
			 	$time = new egw_time($values[$key], $tz);
			 	$time->setTimezone($fromTZ);
			 	if ($time->format('Hi') == '0000')
			 	{
				 	// we keep dates the same in new timezone
				 	$arr = egw_time::to($time,'array');
				 	$time = new egw_time($arr, $toTZ);
			 	}
			 	else
			 	{
				 	$time->setTimezone($toTZ);
			 	}
			 	$values[$key] = egw_time::to($time,'ts');
		 	}
		}
		//error_log(__METHOD__.'() --> values[info_enddate]='.date('Y-m-d H:i:s',$values['info_enddate']));
	 }

	/**
	 * convert a date from server to user-time
	 *
	 * @param int $ts timestamp in server-time
	 * @param string $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time, 'array'=array or string with date-format
	 * @return mixed depending of $date_format
	 */
	function date2usertime($ts,$date_format='ts')
	{
		if (empty($ts) || $date_format == 'server') return $ts;

		return egw_time::server2user($ts,$date_format);
	}

	/**
	 * Read an infolog entry specified by $info_id
	 *
	 * @param int|array $info_id integer id or array with id's or array with column=>value pairs of the entry to read
	 * @param boolean $run_link_id2from=true should link_id2from run, default yes,
	 *	need to be set to false if called from link-title to prevent an infinit recursion
	 * @param string $date_format='ts' date-formats: 'ts'=timestamp, 'server'=timestamp in server-time,
	 * 	'array'=array or string with date-format
	 *
	 * @return array|boolean infolog entry, null if not found or false if no permission to read it
	 */
	function &read($info_id,$run_link_id2from=true,$date_format='ts')
	{
		//error_log(__METHOD__.'('.array2string($info_id).', '.array2string($run_link_id2from).", '$date_format') ".function_backtrace());
		if (is_scalar($info_id) || isset($info_id[count($info_id)-1]))
		{
			if (is_scalar($info_id) && !is_numeric($info_id))
			{
				$info_id = array('info_uid' => $info_id);
			}
			else
			{
				$info_id = array('info_id' => $info_id);
			}
		}

		if (($data = $this->so->read($info_id)) === False)
		{
			return null;
		}
		$info_id = $data['info_id'];	// in case the uid was specified

		if (!$this->check_access($data,EGW_ACL_READ))	// check behind read, to prevent a double read
		{
			return False;
		}

		if ($data['info_subject'] == $this->subject_from_des($data['info_des']))
		{
			$data['info_subject'] = '';
		}
		if ($run_link_id2from) $this->link_id2from($data);

		// convert server- to user-time
		if ($date_format == 'ts')
		{
			$this->time2time($data);

			// pre-cache title and file access
			self::set_link_cache($data);
		}

		return $data;
	}

	/**
	 * Delete an infolog entry, evtl. incl. it's children / subs
	 *
	 * @param int|array $info_id int id
	 * @param boolean $delete_children should the children be deleted
	 * @param int|boolean $new_parent parent to use for not deleted children if > 0
	 * @param boolean $skip_notification Do not send notification of delete
	 * @return boolean True if delete was successful, False otherwise ($info_id does not exist or no rights)
	 */
	function delete($info_id,$delete_children=False,$new_parent=False, $skip_notification=False)
	{
		if (is_array($info_id))
		{
			$info_id = (int)(isset($info_id[0]) ? $info_id[0] : (isset($info_id['info_id']) ? $info_id['info_id'] : $info_id['info_id']));
		}
		if (($info = $this->so->read(array('info_id' => $info_id), true, 'server')) === False)
		{
			return False;
		}
		if (!$this->check_access($info,EGW_ACL_DELETE))
		{
			return False;
		}
		// check if we have children and delete or re-parent them
		if (($children = $this->so->get_children($info_id)))
		{
			foreach($children as $id => $owner)
			{
				if ($delete_children && $this->so->grants[$owner] & EGW_ACL_DELETE)
				{
					$this->delete($id,$delete_children,$new_parent,$skip_notification);	// call ourself recursive to delete the child
				}
				else	// dont delete or no rights to delete the child --> re-parent it
				{
					$this->so->write(array(
						'info_id' => $id,
						'info_parent_id' => $new_parent,
					));
				}
			}
		}
		$deleted = $info;
		$deleted['info_status'] = 'deleted';
		$deleted['info_datemodified'] = time();
		$deleted['info_modifier'] = $this->user;

		// if we have history switched on and not an already deleted item --> set only status deleted
		if ($this->history && $info['info_status'] != 'deleted')
		{
			if ($info['info_status'] == 'deleted') return false;	// entry already deleted

			$this->so->write($deleted);

			egw_link::unlink(0,'infolog',$info_id,'','!file','',true);	// keep the file attachments, hide the rest
		}
		else
		{
			$this->so->delete($info_id,false);	// we delete the children via bo to get all notifications!

			egw_link::unlink(0,'infolog',$info_id);
		}
		if ($info['info_status'] != 'deleted')	// dont notify of final purge of already deleted items
		{
			$GLOBALS['egw']->contenthistory->updateTimeStamp('infolog_'.$info['info_type'], $info_id, 'delete', time());

			// send email notifications and do the history logging
			if(!$skip_notification)
			{
				if (!is_object($this->tracking))
				{
					$this->tracking = new infolog_tracking($this);
				}
				$this->tracking->track($deleted,$info,$this->user,true);
			}
		}
		return True;
	}

	/**
	* writes the given $values to InfoLog, a new entry gets created if info_id is not set or 0
	*
	* checks and asures ACL
	*
	* @param array &$values values to write
	* @param boolean $check_defaults=true check and set certain defaults
	* @param boolean $touch_modified=true touch the modification data and sets the modiefier's user-id
	* @param boolean $user2server=true conversion between user- and server-time necessary
	* @param boolean $skip_notification=false true = do NOT send notification, false (default) = send notifications
	* @param boolean $throw_exception=false Throw an exception (if required fields are not set)
	* @param string $purge_cfs=null null=dont, 'ical'=only iCal X-properties (cfs name starting with "#"), 'all'=all cfs
	*
	* @return int|boolean info_id on a successfull write or false
	*/
	function write(&$values_in, $check_defaults=true, $touch_modified=true, $user2server=true,
		$skip_notification=false, $throw_exception=false, $purge_cfs=null)
	{
		$values = $values_in;
		//echo "boinfolog::write()values="; _debug_array($values);
		if (!$values['info_id'] && !$this->check_access(0,EGW_ACL_EDIT,$values['info_owner']) &&
			!$this->check_access(0,EGW_ACL_ADD,$values['info_owner']))
		{
			return false;
		}
		// we need to get the old values to update the links in customfields and for the tracking
		if ($values['info_id'])
		{
			$old = $this->read($values['info_id'], false, 'server');
		}
		if (($status_only = $values['info_id'] && !$this->check_access($values['info_id'],EGW_ACL_EDIT)))
		{
			if (!isset($values['info_responsible']))
			{
				$responsible = $old['info_responsible'];
			}
			else
			{
				$responsible = $values['info_responsible'];
			}
			if (!($status_only = in_array($this->user, (array)$responsible)))	// responsible has implicit right to change status
			{
				$status_only = !!array_intersect((array)$responsible,array_keys($GLOBALS['egw']->accounts->memberships($this->user)));
			}
			if (!$status_only && $values['info_status'] != 'deleted')
			{
				$status_only = $undelete = $this->check_access($values['info_id'],EGW_ACL_UNDELETE);
			}
		}
		if ($values['info_id'] && !$this->check_access($values['info_id'],EGW_ACL_EDIT) && !$status_only ||
		    !$values['info_id'] && $values['info_id_parent'] && !$this->check_access($values['info_id_parent'],EGW_ACL_ADD))
		{
			return false;
		}
		if ($status_only && !$undelete)	// make sure only status gets writen
		{
			$set_completed = !$values['info_datecompleted'] &&	// set date completed of finished job, only if its not already set
				(in_array($values['info_status'],array('done','billed','cancelled')) || (int)$values['info_percent'] == 100);

			$values = $old;
			// only overwrite explicitly allowed fields
			$values['info_datemodified'] = $values_in['info_datemodified'];
			foreach ($this->responsible_edit as $name)
			{
				if (isset($values_in[$name])) $values[$name] = $values_in[$name];
			}
			if ($set_completed)
			{
				$values['info_datecompleted'] = $user2server ? $this->user_time_now : $this->now;
				$values['info_percent'] = 100;
				$forcestatus = true;
				$status = 'done';
				if (isset($values['info_type']) && !in_array($values['info_status'],array('done','billed','cancelled'))) {
					$forcestatus = false;
					//echo "set_completed:"; _debug_array($this->status[$values['info_type']]);
					if (isset($this->status[$values['info_type']]['done'])) {
						$forcestatus = true;
						$status = 'done';
					} elseif (isset($this->status[$values['info_type']]['billed'])) {
						$forcestatus = true;
						$status = 'billed';
					} elseif (isset($this->status[$values['info_type']]['cancelled'])) {
						$forcestatus = true;
						$status = 'cancelled';
					}
				}
				if ($forcestatus && !in_array($values['info_status'],array('done','billed','cancelled'))) $values['info_status'] = $status;
			}
			$check_defaults = false;
		}
		if ($check_defaults)
		{
			if (!$values['info_datecompleted'] &&
				(in_array($values['info_status'],array('done','billed')) || (int)$values['info_percent'] == 100))
			{
				$values['info_datecompleted'] = $user2server ? $this->user_time_now : $this->now;	// set date completed to today if status == done
			}
			if (in_array($values['info_status'],array('done','billed')))
			{
				$values['info_percent'] = 100;
			}
			if ((int)$values['info_percent'] == 100 && !in_array($values['info_status'],array('done','billed','cancelled','archive')))
			{
				//echo "check_defaults:"; _debug_array($this->status[$values['info_type']]);
				//$values['info_status'] = 'done';
				$status = 'done';
				if (isset($values['info_type'])) {
					if (isset($this->status[$values['info_type']]['done'])) {
						$status = 'done';
					} elseif (isset($this->status[$values['info_type']]['billed'])) {
						$status = 'billed';
					} elseif (isset($this->status[$values['info_type']]['cancelled'])) {
						$status = 'cancelled';
					} else {
						// since the comlete stati above do not exist for that type, dont change it
						$status = $values['info_status'];
					}
				}
				$values['info_status'] = $status;
			}
			if ($values['info_responsible'] && $values['info_status'] == 'offer')
			{
				$values['info_status'] = 'not-started';   // have to match if not finished
			}
			if (isset($values['info_subject']) && empty($values['info_subject']))
			{
				$values['info_subject'] = $this->subject_from_des($values['info_des']);
			}

			// Check required custom fields
			if($throw_exception)
			{
				$custom = config::get_customfields('infolog');
				foreach($custom as $c_name => $c_field)
				{
					if($c_field['type2']) $type2 = explode(',',$c_field['type2']);
					if($c_field['needed'] && (!$c_field['type2'] || $c_field['type2'] && in_array($values['info_type'],$type2)))
					{
						// Required custom field
						if(!$values['#'.$c_name])
						{
							throw new egw_exception_wrong_userinput(lang('For infolog type %1, %2 is required',lang($values['info_type']),$c_field['label']));
						}
					}
				}
			}
		}
		if (isset($this->group_owners[$values['info_type']]))
		{
			$values['info_owner'] = $this->group_owners[$values['info_type']];
			if (!($this->grants[$this->group_owners[$values['info_type']]] & EGW_ACL_EDIT))
			{
				if (!$this->check_access($values['info_id'],EGW_ACL_EDIT) ||
					!$values['info_id'] && !$this->check_access($values,EGW_ACL_ADD)
				)
				{
					return false;	// no edit rights from the group-owner and no implicit rights (delegated and sufficient rights)
				}
			}
		}
		elseif (!$values['info_id'] && !$values['info_owner'] || $GLOBALS['egw']->accounts->get_type($values['info_owner']) == 'g')
		{
			$values['info_owner'] = $this->so->user;
		}

		if ($info_from_set = ($values['info_link_id'] && isset($values['info_from']) && empty($values['info_from'])))
		{
			$values['info_from'] = $this->link_id2from($values);
		}

		$to_write = $values;
		if ($user2server)
		{
			// convert user- to server-time
			$this->time2time($to_write, null, false);
		}
		else
		{
			// convert server- to user-time
			$this->time2time($values);
		}

		if ($touch_modified || !$values['info_datemodified'])
		{
			// Should only an entry be updated which includes the original modification date?
			// Used in the web-GUI to check against a modification by an other user while editing the entry.
			// It's now disabled for xmlrpc, as otherwise the xmlrpc code need to be changed!
			$xmlrpc = is_object($GLOBALS['server']) && $GLOBALS['server']->last_method;
			$check_modified = $values['info_datemodified'] && !$xmlrpc ? $to_write['info_datemodified'] : false;
			$values['info_datemodified'] = $this->user_time_now;
			$to_write['info_datemodified'] = $this->now;
		}
		if ($touch_modified || !$values['info_modifier'])
		{
			$values['info_modifier'] = $to_write['info_modifier'] = $this->so->user;
		}

		// set created and creator for new entries
		if (!$values['info_id'])
		{
			$values['info_created'] = $this->user_time_now;
			$to_write['info_created'] = $this->now;
			$values['info_creator'] = $to_write['info_creator'] = $this->so->user;
		}
		//_debug_array($values);
		// error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."()\n".array2string($values)."\n",3,'/tmp/infolog');

		if (($info_id = $this->so->write($to_write, $check_modified, $purge_cfs)))
		{
			if (!isset($values['info_type']) || $status_only || empty($values['caldav_url']))
			{
				$values = $this->read($info_id, true, 'server');
			}
			if ($values['info_id'] && $old['info_status'] != 'deleted')
			{
				// update
				$GLOBALS['egw']->contenthistory->updateTimeStamp(
					'infolog_'.$values['info_type'],
					$info_id, 'modify', time()
				);
			}
			else
			{
				// add
				$GLOBALS['egw']->contenthistory->updateTimeStamp(
					'infolog_'.$values['info_type'],
					$info_id, 'add', time()
				);
			}
			$values['info_id'] = $info_id;
			$to_write['info_id'] = $info_id;
			// if the info responbsible array is not passed, fetch it from old.
			if (!array_key_exists('info_responsible',$values)) $values['info_responsible'] = $old['info_responsible'];
			if (!is_array($values['info_responsible']))		// this should not happen, bug it does ;-)
			{
				$values['info_responsible'] = $values['info_responsible'] ? explode(',',$values['info_responsible']) : array();
				$to_write['info_responsible'] = $values['info_responsible'];
			}
			// create (and remove) links in custom fields
			customfields_widget::update_customfield_links('infolog',$values,$old,'info_id');

			// Check for restore of deleted entry, restore held links
			if($old['info_status'] == 'deleted' && $values['info_status'] != 'deleted')
			{
				egw_link::restore('infolog', $info_id);
			}

			// notify the link-class about the update, as other apps may be subscribt to it
			egw_link::notify_update('infolog',$info_id,$values);

			// pre-cache the new values
			self::set_link_cache($values);

			// send email notifications and do the history logging
			if (!is_object($this->tracking))
			{
				$this->tracking = new infolog_tracking($this);
			}

			if ($old && ($missing_fields = array_diff_key($old,$values)))
			{
				$values = array_merge($values,$missing_fields);
			}
			// Add keys missing in the $to_write array
			if (($missing_fields = array_diff_key($values,$to_write)))
			{
				$to_write = array_merge($to_write,$missing_fields);
			}
			$this->tracking->track($to_write,$old,$this->user,$values['info_status'] == 'deleted' || $old['info_status'] == 'deleted',
				null,$skip_notification);

			if ($info_from_set) $values['info_from'] = '';

			// Change new values back to user time before sending them back
			if($user2server)
			{
				$this->time2time($values);
			}
			// merge changes (keeping extra values from the UI)
			$values_in = array_merge($values_in,$values);
		}
		return $info_id;
	}

	/**
	 * Query the number of children / subs for one or more info_id's
	 *
	 * @param int|array $info_id id
	 * @return int|array number of subs
	 */
	function anzSubs( $info_id )
	{
		return $this->so->anzSubs( $info_id );
	}

	/**
	 * searches InfoLog for a certain pattern in $query
	 *
	 * @param $query[order] column-name to sort after
	 * @param $query[sort] sort-order DESC or ASC
	 * @param $query[filter] string with combination of acl-, date- and status-filters, eg. 'own-open-today' or ''
	 * @param $query[cat_id] category to use or 0 or unset
	 * @param $query[search] pattern to search, search is done in info_from, info_subject and info_des
	 * @param $query[action] / $query[action_id] if only entries linked to a specified app/entry show be used
	 * @param &$query[start], &$query[total] nextmatch-parameters will be used and set if query returns less entries
	 * @param $query[col_filter] array with column-name - data pairs, data == '' means no filter (!)
	 * @return array with id's as key of the matching log-entries
	 */
	function &search(&$query)
	{
		//error_log(__METHOD__.'('.array2string($query).')');

		if($query['filter'] == 'bydate')
		{
			if (is_int($query['startdate'])) $query['col_filter'][] = 'info_startdate >= '.$GLOBALS['egw']->db->quote($query['startdate']);
			if (is_int($query['enddate'])) $query['col_filter'][] = 'info_startdate <= '.$GLOBALS['egw']->db->quote($query['enddate']+(60*60*24)-1);
		}
		if (!isset($query['date_format']) || $query['date_format'] != 'server')
		{
			if (isset($query['col_filter']))
			{
				foreach ($this->timestamps as $key)
				{
					if (!empty($query['col_filter'][$key]))
					{
						$query['col_filter'][$key] = egw_time::user2server($query['col_filter'][$key],'ts');
					}
				}
			}
		}

		$ret = $this->so->search($query);

		if (is_array($ret))
		{
			foreach ($ret as $id => &$data)
			{
				if (!$this->check_access($data,EGW_ACL_READ))
				{
					unset($ret[$id]);
					continue;
				}
				// convert system- to user-time
				foreach ($this->timestamps as $key)
				{
					if ($data[$key])
					{
						$time = new egw_time($data[$key], egw_time::$server_timezone);
						if (!isset($query['date_format']) || $query['date_format'] != 'server')
						{
							if ($time->format('Hi') == '0000')
							{
								// we keep dates the same in user-time
								$arr = egw_time::to($time,'array');
								$time = new egw_time($arr, egw_time::$user_timezone);
							}
							else
							{
								$time->setTimezone(egw_time::$user_timezone);
							}
						}
						$data[$key] = egw_time::to($time,'ts');
					}
				}
				// pre-cache title and file access
				self::set_link_cache($data);
			}
		}
		//echo "<p>boinfolog::search(".print_r($query,True).")=<pre>".print_r($ret,True)."</pre>\n";
		return $ret;
	}

	/**
	 * Query ctag for infolog
	 *
	 * @param array $filter=array('filter'=>'own','info_type'=>'task')
	 * @return string
	 */
	public function getctag(array $filter=array('filter'=>'own','info_type'=>'task'))
	{
		$filter += array(
			'order'			=> 'info_datemodified',
			'sort'			=> 'DESC',
			'date_format'	=> 'server',
			'start'			=> 0,
			'num_rows'		=> 1,
		);

		$result =& $this->search($filter);

		if (empty($result)) return 'EGw-empty-wGE';

		$entry = array_shift($result);

		return $entry['info_datemodified'];
	}

	/**
	 * imports a mail identified by uid as infolog
	 *
	 * @author Cornelius Weiss <nelius@cwtech.de>
	 * @todo search if infolog with from and subject allready exists ->appned body & inform user
	 * @param string $_email_address rfc822 conform emailaddresses
	 * @param string $_subject
	 * @param string $_message
	 * @param array $_attachments
	 * @param string $_date
	 * @return array $content array for uiinfolog
	 */
	function import_mail($_email_address,$_subject,$_message,$_attachments,$_date)
	{
		$address_array = imap_rfc822_parse_adrlist($_email_address,'');
		foreach ((array)$address_array as $address)
		{
			$email[] = $emailadr = sprintf('%s@%s',
				trim($address->mailbox),
				trim($address->host));
				$name[] = !empty($address->personal) ? $address->personal : $emailadr;
		}
		// shorten long (> $this->max_line_chars) lines of "line" chars (-_+=~) in mails
		$_message = preg_replace_callback('/[-_+=~\.]{'.$this->max_line_chars.',}/m',
			create_function('$matches',"return substr(\$matches[0],0,$this->max_line_chars);"),$_message);
		$type = isset($this->enums['type']['email']) ? 'email' : 'note';
		$status = isset($this->status['defaults'][$type]) ? $this->status['defaults'][$type] : 'done';
		$info = array(
			'info_id' => 0,
			'info_type' => $type,
			'info_from' => implode(', ',$name),
			'info_addr' => implode(', ',$email),
			'info_subject' => $_subject,
			'info_des' => $_message,
			'info_startdate' => egw_time::server2user($_date),
			'info_status' => $status,
			'info_priority' => 1,
			'info_percent' => $status == 'done' ? 100 : 0,
			'referer' => false,
			'link_to' => array(
				'to_app' => 'infolog',
				'to_id' => 0,
			),
		);
		// find the addressbookentry to link with
		$addressbook = new addressbook_bo();
		$contacts = array();
		foreach ($email as $mailadr)
		{
			$contacts = array_merge($contacts,(array)$addressbook->search(
				array(
					'email' => $mailadr,
					'email_home' => $mailadr
				),True,'','','',false,'OR',false,null,'',false));
		}
		if (!$contacts || !is_array($contacts) || !is_array($contacts[0]))
		{
			$info['msg'] = lang('Attention: No Contact with address %1 found.',$info['info_addr']);
			$info['info_custom_from'] = true;	// show the info_from line and NOT only the link
		}
		else
		{
			// create the first address as info_contact
			$contact = array_shift($contacts);
			$info['info_contact'] = 'addressbook:'.$contact['id'];
			// create the rest a "ordinary" links
			foreach ($contacts as $contact)
			{
				egw_link::link('infolog',$info['link_to']['to_id'],'addressbook',$contact['id']);
			}
		}
		if (is_array($_attachments))
		{
			foreach ($_attachments as $attachment)
			{
				$is_vfs = false;
				if (parse_url($attachment['tmp_name'],PHP_URL_SCHEME) == 'vfs' && egw_vfs::is_readable($attachment['tmp_name']))
				{
					$is_vfs = true;
				}
				if(is_readable($attachment['tmp_name']) || $is_vfs)
				{
					egw_link::link('infolog',$info['link_to']['to_id'],'file',$attachment);
				}
			}
		}
		return $info;
	}

	/**
	 * get title for an infolog entry identified by $info
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param int|array $info int info_id or array with infolog entry
	 * @return string|boolean string with the title, null if $info not found, false if no perms to view
	 */
	function link_title($info)
	{
		if (!is_array($info))
		{
			$info = $this->read( $info,false );
		}
		if (!$info)
		{
			return $info;
		}
		$title = !empty($info['info_subject']) ? $info['info_subject'] :self::subject_from_des($info['info_descr']);
		return $title.($GLOBALS['egw_info']['user']['preferences']['infolog']['show_id']?' (#'.$info['info_id'].')':'');
	}

	/**
	 * Return multiple titles fetched by a single query
	 *
	 * @param array $ids
	 */
	function link_titles(array $ids)
	{
		$titles = array();
		foreach ($this->search($params=array(
			'col_filter' => array('info_id' => $ids),
		)) as $info)
		{
			$titles[$info['info_id']] = $this->link_title($info);
		}
		foreach (array_diff($ids,array_keys($titles)) as $id)
		{
			$titles[$id] = false;	// we assume every not returned entry to be not readable, as we notify the link class about all deletes
		}
		return $titles;
	}

	/**
	 * query infolog for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @param array $options Array of options for the search
	 * @return array with info_id - title pairs of the matching entries
	 */
	function link_query($pattern, Array &$options = array())
	{
		$query = array(
			'search' => $pattern,
			'start'  => $options['start'],
			'num_rows'	=>	$options['num_rows'],
			'subs'   => true,
		);
		$ids = $this->search($query);
		$options['total'] = $query['total'];
		$content = array();
		if (is_array($ids))
		{
			foreach ($ids as $id => $info )
			{
				$content[$id] = $this->link_title($id);
			}
		}
		return $content;
	}

	/**
	 * Check access to the file store
	 *
	 * @param int|array $id id of entry or entry array
	 * @param int $check EGW_ACL_READ for read and EGW_ACL_EDIT for write or delete access
	 * @param string $rel_path=null currently not used in InfoLog
	 * @param int $user=null for which user to check, default current user
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path=null,$user=null)
	{
		return $this->check_access($id,$check,0,$user);
	}

	/**
	 * Set the cache of the link class (title, file_access) for the given infolog entry
	 *
	 * @param array $info
	 */
	function set_link_cache(array $info)
	{
		egw_link::set_cache('infolog',$info['info_id'],
			$this->link_title($info),
			$this->file_access($info,EGW_ACL_EDIT) ? EGW_ACL_READ|EGW_ACL_EDIT :
			($this->file_access($info,EGW_ACL_READ) ? EGW_ACL_READ : 0));
	}

	/**
	 * hook called be calendar to include events or todos in the cal-dayview
	 *
	 * @param int $args[year], $args[month], $args[day] date of the events
	 * @param int $args[owner] owner of the events
	 * @param string $args[location] calendar_include_{events|todos}
	 * @return array of events (array with keys starttime, endtime, title, view, icon, content)
	 */
	function cal_to_include($args)
	{
		//echo "<p>cal_to_include("; print_r($args); echo ")</p>\n";
		$user = (int) $args['owner'];
		if ($user <= 0 && !checkdate($args['month'],$args['day'],$args['year']))
		{
			return False;
		}
		$GLOBALS['egw']->translation->add_app('infolog');

		$do_events = $args['location'] == 'calendar_include_events';
		$to_include = array();
		$date_wanted = sprintf('%04d/%02d/%02d',$args['year'],$args['month'],$args['day']);
		$query = array(
			'order' => 'info_startdate',
			'sort'  => $do_events ? 'ASC' : 'DESC',
			'filter'=> "user$user".($do_events ? 'date' : 'opentoday').$date_wanted,
			'start' => 0,
		);
		if ($GLOBALS['egw_info']['user']['preferences']['infolog']['cal_show'])
		{
			$query['col_filter']['info_type'] = explode(',',$GLOBALS['egw_info']['user']['preferences']['infolog']['cal_show']);
		}
		elseif ($this->customfields && !$GLOBALS['egw_info']['user']['preferences']['infolog']['cal_show_custom'])
		{
			$query['col_filter']['info_type'] = array('task','phone','note','email');
		}
		while ($infos = $this->search($query))
		{
			foreach ($infos as $info)
			{
				$start = new egw_time($info['info_startdate'],egw_time::$user_timezone);
				$time = (int) $start->format('Hi');
				$date = $start->format('Y/m/d');
				/* As event-like infologs are not showen in current calendar,
				we need to present all open infologs to the user! (2006-06-27 nelius)
				if ($do_events && !$time ||
				    !$do_events && $time && $date == $date_wanted)
				{
					continue;
				}*/
				$title = ($do_events?common::formattime($start->format('H'),$start->format('i')).' ':'').
					$info['info_subject'];
				$view = egw_link::view('infolog',$info['info_id']);
				$edit = egw_link::edit('infolog',$info['info_id'], $size);
				$edit['size'] = $size;
				$content=array();
				foreach ($icons = array(
					$info['info_type']   => 'infolog',
					$this->status[$info['info_type']][$info['info_status']] => 'infolog',
				) as $name => $app)
				{
					$content[] = html::image($app,$name,lang($name),'border="0" width="15" height="15"').' ';
				}
				$content[] = html::a_href($title,$view);
				$content = html::table(array(1 => $content));

				$to_include[] = array(
					'starttime' => $info['info_startdate'],
					'endtime'   => ($info['info_enddate'] ? $info['info_enddate'] : $info['info_startdate']),
					'title'     => $title,
					'view'      => $view,
					'edit'      => $edit,
					'icons'     => $icons,
					'content'   => $content
				);
			}
			if ($query['total'] <= ($query['start']+=count($infos)))
			{
				break;	// no more availible
			}
		}
		//echo "boinfolog::cal_to_include("; print_r($args); echo ")<pre>"; print_r($to_include); echo "</pre>\n";
		return $to_include;
	}

	/**
	 * Returm InfoLog (custom) status icons for projectmanager
	 *
	 * @param array $args array with id's in $args['infolog']
	 * @return array with id => icon pairs
	 */
	function pm_icons($args)
	{
		if (isset($args['infolog']) && count($args['infolog']))
		{
			$icons = $this->so->get_status($args['infolog']);
			foreach ((array) $icons as $id => $status)
			{
				if ($status && substr($status,-1) != '%')
				{
					$icons[$id] = 'infolog/'.$status;
				}
			}
		}
		return $icons;
	}

	var $categories;

	/**
	 * Find existing categories in database by name or add categories that do not exist yet
	 * currently used for ical/sif import
	 *
	 * @param array $catname_list names of the categories which should be found or added
	 * @param int $info_id=-1 match against existing infolog and expand the returned category ids
	 *  by the ones the user normally does not see due to category permissions - used to preserve categories
	 * @return array category ids (found, added and preserved categories)
	 */
	function find_or_add_categories($catname_list, $info_id=-1)
	{
		if (!is_object($this->categories))
		{
			$this->categories = new categories($this->user,'infolog');
		}
		$old_cats_preserve = array();
		if ($info_id && $info_id > 0)
		{
			// preserve categories without users read access
			$old_infolog = $this->read($info_id);
			$old_categories = explode(',',$old_infolog['info_cat']);
			if (is_array($old_categories) && count($old_categories) > 0)
			{
				foreach ($old_categories as $cat_id)
				{
					if ($cat_id && !$this->categories->check_perms(EGW_ACL_READ, $cat_id))
					{
						$old_cats_preserve[] = $cat_id;
					}
				}
			}
		}

		$cat_id_list = array();
		foreach ((array)$catname_list as $cat_name)
		{
			$cat_name = trim($cat_name);
			$cat_id = $this->categories->name2id($cat_name, 'X-');

			if (!$cat_id)
			{
				// some SyncML clients (mostly phones) add an X- to the category names
				if (strncmp($cat_name, 'X-', 2) == 0)
				{
					$cat_name = substr($cat_name, 2);
				}
				$cat_id = $this->categories->add(array('name' => $cat_name, 'descr' => $cat_name, 'access' => 'private'));
			}

			if ($cat_id)
			{
				$cat_id_list[] = $cat_id;
			}
		}

		if (count($old_cats_preserve) > 0)
		{
			$cat_id_list = array_merge($old_cats_preserve, $cat_id_list);
		}

		if (count($cat_id_list) > 1)
		{
			$cat_id_list = array_unique($cat_id_list);
			// disable sorting until infolog supports multiple categories
			// to make sure that the preserved category takes precedence over a new one from the client
			/* sort($cat_id_list, SORT_NUMERIC); */
		}

		return $cat_id_list;
	}

	/**
	 * Get names for categories specified by their id's
	 *
	 * @param array|string $cat_id_list array or comma-sparated list of id's
	 * @return array with names
	 */
	function get_categories($cat_id_list)
	{
		if (!is_object($this->categories))
		{
			$this->categories = new categories($this->user,'infolog');
		}

		if (!is_array($cat_id_list))
		{
			$cat_id_list = explode(',',$cat_id_list);
		}
		$cat_list = array();
		foreach($cat_id_list as $cat_id)
		{
			if ($cat_id && $this->categories->check_perms(EGW_ACL_READ, $cat_id) &&
					($cat_name = $this->categories->id2name($cat_id)) && $cat_name != '--')
			{
				$cat_list[] = $cat_name;
			}
		}

		return $cat_list;
	}

	/**
	 * Send all async infolog notification
	 *
	 * Called via the async service job 'infolog-async-notification'
	 */
	function async_notification()
	{
		if (!($users = $this->so->users_with_open_entries()))
		{
			return;
		}
		//error_log(__METHOD__."() users with open entries: ".implode(', ',$users));

		$save_account_id = $GLOBALS['egw_info']['user']['account_id'];
		$save_prefs      = $GLOBALS['egw_info']['user']['preferences'];
		foreach($users as $user)
		{
			if (!($email = $GLOBALS['egw']->accounts->id2name($user,'account_email'))) continue;
			// create the enviroment for $user
			$this->user = $GLOBALS['egw_info']['user']['account_id'] = $user;
			$GLOBALS['egw']->preferences->preferences($user);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
			$GLOBALS['egw']->acl->acl($user);
			$GLOBALS['egw']->acl->read_repository();
			$this->grants = $GLOBALS['egw']->acl->get_grants('infolog',$this->group_owners ? $this->group_owners : true);
			$this->so = new infolog_so($this->grants);	// so caches it's filters

			$notified_info_ids = array();
			foreach(array(
				'notify_due_responsible'   => 'open-responsible-enddate',
				'notify_due_delegated'     => 'open-delegated-enddate',
				'notify_start_responsible' => 'open-responsible-date',
				'notify_start_delegated'   => 'open-delegated-date',
			) as $pref => $filter)
			{
				if (!($pref_value = $GLOBALS['egw_info']['user']['preferences']['infolog'][$pref])) continue;

				$filter .= date('Y-m-d',time()+24*60*60*(int)$pref_value);
				//error_log(__METHOD__."() checking with filter '$filter' ($pref_value) for user $user ($email)");

				$params = array('filter' => $filter);
				foreach($this->so->search($params) as $info)
				{
					// check if we already send a notification for that infolog entry, eg. starting and due on same day
					if (in_array($info['info_id'],$notified_info_ids)) continue;

					if (is_null($this->tracking) || $this->tracking->user != $user)
					{
						$this->tracking = new infolog_tracking($this);
					}
					switch($pref)
					{
						case 'notify_due_responsible':
							$info['prefix'] = lang('Due %1',$this->enums['type'][$info['info_type']]);
							$info['message'] = lang('%1 you are responsible for is due at %2',$this->enums['type'][$info['info_type']],
								$this->tracking->datetime($info['info_enddate'],false));
							break;
						case 'notify_due_delegated':
							$info['prefix'] = lang('Due %1',$this->enums['type'][$info['info_type']]);
							$info['message'] = lang('%1 you delegated is due at %2',$this->enums['type'][$info['info_type']],
								$this->tracking->datetime($info['info_enddate'],false));
							break;
						case 'notify_start_responsible':
							$info['prefix'] = lang('Starting %1',$this->enums['type'][$info['info_type']]);
							$info['message'] = lang('%1 you are responsible for is starting at %2',$this->enums['type'][$info['info_type']],
								$this->tracking->datetime($info['info_startdate'],null));
							break;
						case 'notify_start_delegated':
							$info['prefix'] = lang('Starting %1',$this->enums['type'][$info['info_type']]);
							$info['message'] = lang('%1 you delegated is starting at %2',$this->enums['type'][$info['info_type']],
								$this->tracking->datetime($info['info_startdate'],null));
							break;
					}
					//error_log("notifiying $user($email) about $info[info_subject]: $info[message]");
					$this->tracking->send_notification($info,null,$email,$user,$pref);

					$notified_info_ids[] = $info['info_id'];
				}
			}
		}
		$GLOBALS['egw_info']['user']['account_id']  = $save_account_id;
		$GLOBALS['egw_info']['user']['preferences'] = $save_prefs;
	}

	/** conversion of infolog status to vtodo status
	 * @private
	 * @var array
	 */
	var $_status2vtodo = array(
		'offer'       => 'NEEDS-ACTION',
		'not-started' => 'NEEDS-ACTION',
		'ongoing'     => 'IN-PROCESS',
		'done'        => 'COMPLETED',
		'cancelled'   => 'CANCELLED',
		'billed'      => 'COMPLETED',
		'template'    => 'CANCELLED',
		'nonactive'   => 'CANCELLED',
		'archive'     => 'CANCELLED',
		'deferred'    => 'NEEDS-ACTION',
		'waiting'     => 'IN-PROCESS',
	);

	/** conversion of vtodo status to infolog status
	 * @private
	 * @var array
	 */
	var $_vtodo2status = array(
		'NEEDS-ACTION' => 'not-started',
		'NEEDS ACTION' => 'not-started',
		'IN-PROCESS'   => 'ongoing',
		'IN PROCESS'   => 'ongoing',
		'COMPLETED'    => 'done',
		'CANCELLED'    => 'cancelled',
	);

	/**
	 * Converts an infolog status into a vtodo status
	 *
	 * @param string $status see $this->status
	 * @return string {CANCELLED|NEEDS-ACTION|COMPLETED|IN-PROCESS}
	 */
	function status2vtodo($status)
	{
		return isset($this->_status2vtodo[$status]) ? $this->_status2vtodo[$status] : 'NEEDS-ACTION';
	}

	/**
	 * Converts a vtodo status into an infolog status using the optional X-INFOLOG-STATUS
	 *
	 * X-INFOLOG-STATUS is only used, if translated to the vtodo-status gives the identical vtodo status
	 * --> the user did not changed it
	 *
	 * @param string $vtodo_status {CANCELLED|NEEDS-ACTION|COMPLETED|IN-PROCESS}
	 * @param string $x_infolog_status preserved original infolog status
	 * @return string
	 */
	function vtodo2status($vtodo_status,$x_infolog_status=null)
	{
		$vtodo_status = strtoupper($vtodo_status);

		if ($x_infolog_status && $this->status2vtodo($x_infolog_status) == $vtodo_status)
		{
			$status = $x_infolog_status;
		}
		else
		{
			$status = isset($this->_vtodo2status[$vtodo_status]) ? $this->_vtodo2status[$vtodo_status] : 'not-started';
		}
		return $status;
	}

	/**
	 * Activates an InfoLog entry (setting it's status from template or inactive depending on the completed percentage)
	 *
	 * @param array $info
	 * @return string new status
	 */
	function activate($info)
	{
		switch((int)$info['info_percent'])
		{
			case 0:		return 'not-started';
			case 100:	return 'done';
		}
		return 'ongoing';
	}

	/**
	 * Get the Parent ID of an InfoLog entry
	 *
	 * @param string $_guid
	 * @return string parentID
	 */
	function getParentID($_guid)
	{
		#Horde::logMessage("getParentID($_guid)",  __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$parentID = False;
		$myfilter = array('col_filter' => array('info_uid'=>$_guid)) ;
		if ($_guid && ($found=$this->search($myfilter)) && ($uidmatch = array_shift($found))) {
			$parentID = $uidmatch['info_id'];
		};
		return $parentID;
	}

	/**
	 * Try to find a matching db entry
	 * This expects timestamps to be in server-time.
	 *
	 * @param array $infoData   the infolog data we try to find
	 * @param boolean $relax=false if asked to relax, we only match against some key fields
	 * @param string $tzid=null timezone, null => user time
	 *
	 * @return array of infolog_ids of matching entries
	 */
	function findInfo($infoData, $relax=false, $tzid=null)
	{
		$foundInfoLogs = array();
		$filter = array();

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
				. '('. ($relax ? 'RELAX, ': 'EXACT, ') . $tzid . ')[InfoData]:'
				. array2string($infoData));
		}

		if ($infoData['info_id']
			&& ($egwData = $this->read($infoData['info_id'], true, 'server')))
		{
			// we only do a simple consistency check
			if (!$relax || strpos($egwData['info_subject'], $infoData['info_subject']) === 0)
			{
				return array($egwData['info_id']);
			}
			if (!$relax) return array();
		}
		unset($infoData['info_id']);

		if (!$relax && !empty($infoData['info_uid']))
		{
			$filter = array('col_filter' => array('info_uid' => $infoData['info_uid']));
			foreach($this->so->search($filter) as $egwData)
			{
				if (!$this->check_access($egwData,EGW_ACL_READ)) continue;
				$foundInfoLogs[$egwData['info_id']] = $egwData['info_id'];
			}
			return $foundInfoLogs;
		}
		unset($infoData['info_uid']);

		if (empty($infoData['info_des']))
		{
			$description = false;
		}
		else
		{
			// ignore meta information appendices
			$description = trim(preg_replace('/\s*\[[A-Z_]+:.*\].*/im', '', $infoData['info_des']));
			$text = trim(preg_replace('/\s*\[[A-Z_]+:.*\]/im', '', $infoData['info_des']));
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. "()[description]: $description");
			}
			// Avoid quotation problems
			if (preg_match_all('/[\x20-\x7F]*/m', $text, $matches, PREG_SET_ORDER))
			{
				$text = '';
				foreach ($matches as $chunk)
				{
					if (strlen($text) <  strlen($chunk[0]))
					{
						$text = $chunk[0];
					}
				}
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
						. "()[search]: $text");
				}
				$filter['search'] = $text;
			}
		}
		$this->time2time($infoData, $tzid, false);

		$filter['col_filter'] = $infoData;
		// priority does not need to match
		unset($filter['col_filter']['info_priority']);
		// we ignore description and location first
		unset($filter['col_filter']['info_des']);
		unset($filter['col_filter']['info_location']);

		foreach ($this->so->search($filter) as $itemID => $egwData)
		{
			if (!$this->check_access($egwData,EGW_ACL_READ)) continue;

			switch ($infoData['info_type'])
			{
				case 'task':
					if (!empty($egwData['info_location']))
					{
						$egwData['info_location'] = str_replace("\r\n", "\n", $egwData['info_location']);
					}
					if (!$relax &&
					!empty($infoData['info_location']) && (empty($egwData['info_location'])
						|| strpos($egwData['info_location'], $infoData['info_location']) !== 0))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. '()[location mismatch]: '
								. $infoData['info_location'] . ' <> ' . $egwData['info_location']);
						}
						continue;
					}
				default:
					if (!empty($egwData['info_des']))
					{
						$egwData['info_des'] = str_replace("\r\n", "\n", $egwData['info_des']);
					}
					if (!$relax && ($description && empty($egwData['info_des'])
						|| !empty($egwData['info_des']) && empty($infoData['info_des'])
						|| strpos($egwData['info_des'], $description) === false))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. '()[description mismatch]: '
								. $infoData['info_des'] . ' <> ' . $egwData['info_des']);
						}
						continue;
					}
					// no further criteria to match
					$foundInfoLogs[$egwData['info_id']] = $egwData['info_id'];
			}
		}

		if (!$relax && !empty($foundInfoLogs))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
					. '()[FOUND]:' . array2string($foundInfoLogs));
			}
			return $foundInfoLogs;
		}

		if ($relax)
		{
			unset($filter['search']);
		}

		// search for matches by date only
		unset($filter['col_filter']['info_startdate']);
		unset($filter['col_filter']['info_enddate']);
		unset($filter['col_filter']['info_datecompleted']);
		// Some devices support lesser stati
		unset($filter['col_filter']['info_status']);

		// try tasks without category
		unset($filter['col_filter']['info_cat']);

		// Horde::logMessage("findVTODO Filter\n"
		//	. print_r($filter, true),
		//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
		foreach ($this->so->search($filter) as $itemID => $egwData)
		{
			if (!$this->check_access($egwData,EGW_ACL_READ)) continue;
			// Horde::logMessage("findVTODO Trying\n"
			//	. print_r($egwData, true),
			//	__FILE__, __LINE__, PEAR_LOG_DEBUG);
			if (isset($infoData['info_cat'])
					&& isset($egwData['info_cat']) && $egwData['info_cat']
															   && $infoData['info_cat'] != $egwData['info_cat'])
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
						. '()[category mismatch]: '
						. $infoData['info_cat'] . ' <> ' . $egwData['info_cat']);
				}
				continue;
			}
			if (isset($infoData['info_startdate']) && $infoData['info_startdate'])
			{
				// We got a startdate from client
				if (isset($egwData['info_startdate']) && $egwData['info_startdate'])
				{
					// We compare the date only
					$taskTime = new egw_time($infoData['info_startdate'],egw_time::$server_timezone);
					$egwTime = new egw_time($egwData['info_startdate'],egw_time::$server_timezone);
					if ($taskTime->format('Ymd') != $egwTime->format('Ymd'))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. '()[start mismatch]: '
								. $taskTime->format('Ymd') . ' <> ' . $egwTime->format('Ymd'));
						}
						continue;
					}
				}
				elseif (!$relax)
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
							. '()[start mismatch]');
					}
					continue;
				}
			}
			if ($infoData['info_type'] == 'task')
			{
				if (isset($infoData['info_status']) && isset($egwData['info_status'])
						&& $egwData['info_status'] == 'done'
							&& $infoData['info_status'] != 'done' ||
								$egwData['info_status'] != 'done'
									&& $infoData['info_status'] == 'done')
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
							. '()[status mismatch]: '
							. $infoData['info_status'] . ' <> ' . $egwData['info_status']);
					}
					continue;
				}
				if (isset($infoData['info_enddate']) && $infoData['info_enddate'])
				{
					// We got a enddate from client
					if (isset($egwData['info_enddate']) && $egwData['info_enddate'])
					{
						// We compare the date only
						$taskTime = new egw_time($infoData['info_enddate'],egw_time::$server_timezone);
						$egwTime = new egw_time($egwData['info_enddate'],egw_time::$server_timezone);
						if ($taskTime->format('Ymd') != $egwTime->format('Ymd'))
						{
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. '()[DUE mismatch]: '
									. $taskTime->format('Ymd') . ' <> ' . $egwTime->format('Ymd'));
							}
							continue;
						}
					}
					elseif (!$relax)
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. '()[DUE mismatch]');
						}
						continue;
					}
				}
				if (isset($infoData['info_datecompleted']) && $infoData['info_datecompleted'])
				{
					// We got a completed date from client
					if (isset($egwData['info_datecompleted']) && $egwData['info_datecompleted'])
					{
						// We compare the date only
						$taskTime = new egw_time($infoData['info_datecompleted'],egw_time::$server_timezone);
						$egwTime = new egw_time($egwData['info_datecompleted'],egw_time::$server_timezone);
						if ($taskTime->format('Ymd') != $egwTime->format('Ymd'))
						{
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
									. '()[completed mismatch]: '
									. $taskTime->format('Ymd') . ' <> ' . $egwTime->format('Ymd'));
							}
							continue;
						}
					}
					elseif (!$relax)
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
								. '()[completed mismatch]');
						}
						continue;
					}
				}
				elseif (!$relax && isset($egwData['info_datecompleted']) && $egwData['info_datecompleted'])
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
							. '()[completed mismatch]');
					}
					continue;
				}
			}
			$foundInfoLogs[$itemID] = $itemID;
		}
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__
				. '()[FOUND]:' . array2string($foundInfoLogs));
		}
		return $foundInfoLogs;
	}
}
