<?php
/**
 * InfoLog - Business object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-6 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
	
include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.soinfolog.inc.php');

/**
 * This class is the BO-layer of InfoLog, it also handles xmlrpc requests
 */
class boinfolog
{
	var $enums;
	/**
	 * Instance of our so class
	 *
	 * @var soinfolog
	 */
	var $so;
	/**
	 * Instance of the link class
	 *
	 * @var bolink
	 */
	var $link;
	var $vfs;
	var $vfs_basedir='/infolog';
	var $valid_pathes = array();
	var $send_file_ips = array();

	var $xmlrpc_methods = array();
	var $soap_functions = array(
		'read' => array(
			'in'  => array('int'),
			'out' => array('array')
		),
		'search' => array(
			'in'  => array('array'),
			'out' => array('array')
		),
		'write' => array(
			'in'  => array('array'),
			'out' => array()
		),
		'delete' => array(
			'in'  => array('int'),
			'out' => array()
		),
		'categories' => array(
			'in'  => array('bool'),
			'out' => array('array')
		),
	);
	var $xmlrpc = False;	// called via xmlrpc

	var $tz_offset = 0;
	/**
	 * offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 * 
	 * @var int
	 */
	var $tz_offset_s = 0;
	var $user_time_now;
	/**
	 * name of timestamps in an InfoLog entry
	 * 
	 * @var array
	 */
	var $timestamps = array('info_startdate','info_enddate','info_datemodified','info_datecompleted');
	/**
	 * instance of the config class for infolog
	 * 
	 * @var config
	 */
	var $config;
	/**
	 * fields the responsible user can change
	 * 
	 * @var array
	 */
	var $responsible_edit=array('info_status','info_percent','info_datecompleted');
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
	 * Constructor Infolog BO
	 *
	 * @param int $info_id
	 * @param boolean $instanciate_link=true should the link class be instanciated, used by the link-registry to prevent infinit recursion
	 */
	function boinfolog($info_id = 0,$instanciate_link=true)
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
				'billed' => 'billed' ),			// -->  DONE
			'phone' => array(
				'not-started' => 'call',		// iCal NEEDS-ACTION
				'ongoing' => 'will-call',		// iCal IN-PROCESS
				'done' => 'done', 				// iCal COMPLETED
				'billed' => 'billed' ),			// -->  DONE
			'note' => array(
				'ongoing' => 'ongoing',			// iCal has no status on notes
				'done' => 'done'),
			'email' => array(
				'ongoing' => 'ongoing',			// iCal has no status on notes
				'done' => 'done'
		));

		if (!is_object($GLOBALS['egw']->link) && $instanciate_link)
		{
			$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
		}
		$this->link =& $GLOBALS['egw']->link;

		$this->config =& CreateObject('phpgwapi.config','infolog');
		$this->config->read_repository();

		if ($this->config->config_data)
		{
			$this->link_pathes   = $this->config->config_data['link_pathes'];
			$this->send_file_ips = $this->config->config_data['send_file_ips'];

			if (isset($this->config->config_data['status']) && is_array($this->config->config_data['status']))
			{
				foreach($this->config->config_data['status'] as $key => $data)
				{
					if (!is_array($this->status[$key]))
					{
						$this->status[$key] = array();
					}
					$this->status[$key] = array_merge($this->status[$key],$this->config->config_data['status'][$key]);
				}
			}
			if (isset($this->config->config_data['types']) && is_array($this->config->config_data['types']))
			{
				//echo "stock-types:<pre>"; print_r($this->enums['type']); echo "</pre>\n";
				//echo "config-types:<pre>"; print_r($this->config->config_data['types']); echo "</pre>\n";
				$this->enums['type'] += $this->config->config_data['types'];
				//echo "types:<pre>"; print_r($this->enums['type']); echo "</pre>\n";
			}
			if ($this->config->config_data['group_owners']) $this->group_owners = $this->config->config_data['group_owners'];

			if (isset($this->config->config_data['customfields']) && is_array($this->config->config_data['customfields']))
			{
				if (!($this->customfields = $this->config->config_data['customfields'])) $this->customfields = array();
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
						$this->customfields[$name] = $this->config->config_data['customfields'][$name] = $field;
						$save_config = true;
					}
				}
				if ($save_config) $this->config->save_repository();
			}
			if (count(explode(',',$this->config->config_data['responsible_edit'])))
			{
				$this->responsible_edit = array_merge($this->responsible_edit,explode(',',$this->config->config_data['responsible_edit']));
			}
			if ($this->config->config_data['implicit_rights'] == 'edit')
			{
				$this->implicit_rights = 'edit';
			}
		}
		// sort types by there translation
		foreach($this->enums['type'] as $key => $val)
		{
			if (($val = lang($key)) != $key.'*') $this->enums['type'][$key] = lang($key);
		}
		natcasesort($this->enums['type']);

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->tz_offset = $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
		$this->tz_offset_s = 60*60*$this->tz_offset;
		$this->user_time_now = time() + $this->tz_offset_s;

		$this->grants = $GLOBALS['egw']->acl->get_grants('infolog',$this->group_owners ? $this->group_owners : true);
		$this->so =& new soinfolog($this->grants);

		// are we called via xmlrpc?
		$this->xmlrpc = is_object($GLOBALS['server']) && $GLOBALS['server']->last_method;

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
		if ($links) $link_types = $this->get_customfield_link_types();

		foreach($this->customfields as $name => $field)
		{
			if ((!$type || empty($field['type2']) || $field['type2'] == $type) &&
				(!$links || in_array($field['type'],$link_types)))
			{
				return True;
			}
		}
		return False;
	}

	/**
	 * Get the customfield types containing links
	 *
	 * @return array with customefield types as values
	 */
	function get_customfield_link_types()
	{
		static $link_types;
		
		if (is_null($link_types))
		{
			$link_types = array_keys($this->link->app_list());
			$link_types[] = 'link-entry';
		}
		return $link_types;
	}
	
	/**
	 * check's if user has the requiered rights on entry $info_id
	 *
	 * @param int/array $info data or info_id of infolog entry to check
	 * @param int $required_rights EGW_ACL_{READ|EDIT|ADD|DELETE}
	 * @return boolean
	 */
	function check_access( $info,$required_rights )
	{
		static $cache = array();
		
		$info_id = is_array($info) ? $info['info_id'] : $info;
		
		if (isset($cache[$info_id][$required_rights]))
		{
			return $cache[$info_id][$required_rights];
		}
		return $cache[$info_id][$required_rights] = $this->so->check_access( $info,$required_rights,$this->implicit_rights == 'edit' );
	}
	
	/**
	 * Check if use is responsible for an entry: he or one of his memberships is in responsible
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
			 ($link = $this->link->get_link($info['info_link_id'])) !== False)
		{
			$nr = $link['link_app1'] == 'infolog' && $link['link_id1'] == $info['info_id'] ? '2' : '1';
			$title = $this->link->title($link['link_app'.$nr],$link['link_id'.$nr]);

			if ((string)$info['info_custom_from'] === '')	// old entry
			{
				$info['info_custom_from'] = (int) ($title != $info['info_from'] && @htmlentities($title) != $info['info_from']);
			}
			if (!$info['info_custom_from'])
			{
				$info['info_from'] = '';
				$info['info_custom_from'] = 0;
			}
			if ($link['link_app'.$nr] == $not_app && $link['link_id'.$nr] == $not_id)
			{
				return False;
			}
			$info['info_link'] = array(
				'app'   => $link['link_app'.$nr],
				'id'    => $link['link_id'.$nr],
				'title' => (!empty($info['info_from']) ? $info['info_from'] : $title),
			);
			$info['info_contact'] = $link['link_app'.$nr].':'.$link['link_id'.$nr];

			//echo " title='$title'</p>\n";
			return $info['blur_title'] = $title;
		}
		else
		{
			$info['info_link'] = array('title' => $info['info_from']);
			$info['info_link_id'] = 0;	// link might have been deleted
			$info['info_custom_from'] = (int)!!$info['info_from'];
		}
		return False;
	}

	/**
	 * Create a subject from a description: truncate it and add ' ...'
	 */
	function subject_from_des($des)
	{
		return substr($des,0,60).' ...';
	}

	/**
	 * Read an infolog entry specified by $info_id
	 *
	 * @param int/array $info_id integer id or array with key 'info_id' of the entry to read
	 * @param boolean $run_link_id2from=true should link_id2from run, default yes, 
	 *	need to be set to false if called from link-title to prevent an infinit recursion
	 * @return array/boolean infolog entry, null if not found or false if no permission to read it
	 */
	function &read($info_id,$run_link_id2from=true)
	{
		if (is_array($info_id))
		{
			$info_id = (int) (isset($info_id['info_id']) ? $info_id['info_id'] : $info_id[0]);
		}

		if ($this->so->read($info_id) === False)
		{
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
			}
			return null;
		}
		if (!$this->check_access($info_id,EGW_ACL_READ))	// check behind read, to prevent a double read
		{
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return False;
		}
		$data = $this->so->data;

		if ($data['info_subject'] == $this->subject_from_des($data['info_des']))
		{
			$data['info_subject'] = '';
		}
		if ($run_link_id2from) $this->link_id2from($data);

		// convert system- to user-time
		foreach($this->timestamps as $time)
		{
			if ($data[$time]) $data[$time] += $this->tz_offset_s;
		}
		if ($this->xmlrpc)
		{
			$data = $this->data2xmlrpc($data);
		}
		return $data;
	}

	/**
	 * Delete an infolog entry, evtl. incl. it's children / subs
	 *
	 * @param int/array $info_id int id or array with keys 'info_id', 'delete_children' and 'new_parent' setting all 3 params
	 * @param boolean $delete_children should the children be deleted
	 * @param int/boolean $new_parent parent to use for not deleted children if > 0
	 * @return boolean True if delete was successful, False otherwise ($info_id does not exist or no rights)
	 */
	function delete($info_id,$delete_children=False,$new_parent=False)
	{
		if (is_array($info_id))
		{
			$delete_children = $info_id['delete_children'];
			$new_parent = $info_id['new_parent'];
			$info_id = (int)(isset($info_id[0]) ? $info_id[0] : (isset($info_id['info_id']) ? $info_id['info_id'] : $info_id['info_id']));
		}
		if ($this->so->read($info_id) === False)
		{
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
			}
			return False;
		}
		if (!$this->check_access($info_id,EGW_ACL_DELETE))
		{
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return False;
		}

		$this->link->unlink(0,'infolog',$info_id);

		$info = $this->read($info_id);

		$this->so->delete($info_id,$delete_children,$new_parent);
		
		$GLOBALS['egw']->contenthistory->updateTimeStamp('infolog_'.$info['info_type'], $info_id, 'delete', time());
		
		return True;
	}

	/**
	* writes the given $values to InfoLog, a new entry gets created if info_id is not set or 0
	*
	* checks and asures ACL
	*
	* @param array &$values values to write, if contains values for check_defaults and touch_modified, 
	*	they have precedens over the parameters. The 
	* @param boolean $check_defaults=true check and set certain defaults
	* @param boolean $touch_modified=true touch the modification data and sets the modiefier's user-id
	* @return int/boolean info_id on a successfull write or false
	*/
	function write(&$values,$check_defaults=True,$touch_modified=True)
	{
		//echo "boinfolog::write()values="; _debug_array($values);
		// allow to (un)set check_defaults and touch_modified via values, eg. via xmlrpc
		foreach(array('check_defaults','touch_modified') as $var)
		{
			if(isset($values[$var]))
			{
				$$var = $values[$var];
				unset($values[$var]);
			}
		}
		if ($status_only = $values['info_id'] && !$this->check_access($values['info_id'],EGW_ACL_EDIT))
		{
			if (!isset($values['info_responsible']))
			{
				if (!($values_read = $this->read($values['info_id']))) return false;
				$responsible =& $values_read['info_responsible'];
			}
			else
			{
				$responsible =& $values['info_responsible'];
			}
			if (!($status_only = in_array($this->user, $responsible)))	// responsible has implicit right to change status
			{
				$status_only = !!array_intersect($responsible,array_keys($GLOBALS['egw']->accounts->memberships($this->user)));
			}
		}
		if ($values['info_id'] && !$this->check_access($values['info_id'],EGW_ACL_EDIT) && !$status_only ||
		    !$values['info_id'] && $values['info_id_parent'] && !$this->check_access($values['info_id_parent'],EGW_ACL_ADD))
		{
			if ($this->xmlrpc)
			{
				$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
			}
			return False;
		}
		if ($this->xmlrpc)
		{
			$values = $this->xmlrpc2data($values);
		}
		if ($status_only)	// make sure only status gets writen
		{
			$set_completed = !$values['info_datecompleted'] &&	// set date completed of finished job, only if its not already set 
				(in_array($values['info_status'],array('done','billed','cancelled')) || (int)$values['info_percent'] == 100);

			$backup_values = $values;	// to return the full values
			$values = array(
				'info_id'     => $values['info_id'],
				'info_datemodified' => $values['info_datemodified'],
			);
			foreach($this->responsible_edit as $name)
			{
				if (isset($backup_values[$name])) $values[$name] = $backup_values[$name];
			}
			if ($set_completed)
			{
				$values['info_datecompleted'] = $this->user_time_now;
				$values['info_percent'] = '100%';
				if (!in_array($values['info_status'],array('done','billed','cancelled'))) $values['info_status'] = 'done';
			}
			$check_defaults = False;
		}
		if ($check_defaults)
		{
			if (!$values['info_datecompleted'] && 
				(in_array($values['info_status'],array('done','billed')) || (int)$values['info_percent'] == 100))
			{
				$values['info_datecompleted'] = $this->user_time_now;	// set date completed to today if status == done
			}
			if (in_array($values['info_status'],array('done','billed')))
			{
				$values['info_percent'] == '100%';
			}
			if ((int)$values['info_percent'] == 100 && !in_array($values['info_status'],array('done','billed','cancelled')))
			{
				$values['info_status'] = 'done';
			}
			if ($values['info_responsible'] && $values['info_status'] == 'offer')
			{
				$values['info_status'] = 'not-started';   // have to match if not finished
			}
			if (isset($values['info_subject']) && empty($values['info_subject']))
			{
				$values['info_subject'] = $this->subject_from_des($values['info_des']);
			}
		}
		if (isset($this->group_owners[$values['info_type']]))
		{
			$values['info_owner'] = $this->group_owners[$values['info_type']];
			if (!($this->grants[$this->group_owners[$values['info_type']]] & EGW_ACL_EDIT))
			{
				return false;	// no edit rights from the group-owner
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
		if ($touch_modified || !$values['info_datemodified'])
		{
			// Should only an entry be updated which includes the original modification date?
			// Used in the web-GUI to check against a modification by an other user while editing the entry.
			// It's now disabled for xmlrpc, as otherwise the xmlrpc code need to be changed!
			$check_modified = $values['info_datemodified'] && !$this->xmlrpc ? $values['info_datemodified']-$this->tz_offset_s : false;
			$values['info_datemodified'] = $this->user_time_now;
		}
		if ($touch_modified || !$values['info_modifier'])
		{
			$values['info_modifier'] = $this->so->user;
		}
		$to_write = $values;
		if ($status_only) $values = array_merge($backup_values,$values);
		// convert user- to system-time
		foreach($this->timestamps as $time)
		{
			if ($to_write[$time]) $to_write[$time] -= $this->tz_offset_s;
		}
		// if we have links in customfields, we need to get the old values, to be able to remove changed links
		if ($this->has_customfields($values['info_type'],true) && $values['info_id'])
		{
			$old = $this->read($values['info_id']);
		}
		if(($info_id = $this->so->write($to_write,$check_modified)))
		{
			if (!isset($values['info_type']) || $status_only)
			{
				$values = $this->read($info_id);
			}
			if($values['info_id'])
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
			
			// create (and remove) links in custom fields
			$this->update_customfield_links($values,$old);

			// notify the link-class about the update, as other apps may be subscribt to it
			$this->link->notify_update('infolog',$info_id,$values);
		}
		if ($info_from_set) $values['info_from'] = '';

		return $info_id;
	}

	/**
	 * Check if there are links in the custom fields and update them
	 *
	 * @param array $values new values including the custom fields
	 * @param array $old=null old values before the update, if existing
	 */
	function update_customfield_links($values,$old=null)
	{
		$link_types = $this->get_customfield_link_types();
		
		foreach($this->customfields as $name => $data)
		{
			if (!in_array($data['type'],$link_types)) continue;
			
			// do we have a different old value --> delete that link
			if ($old && $old['#'.$name] && $old['#'.$name] != $values['#'.$name])
			{
				if ($data['type'] == 'link-entry')
				{
					list($app,$id) = explode(':',$old['#'.$name]);
				}
				else
				{
					$app = $data['type'];
					$id = $old['#'.$name];
				}
				$this->link->unlink(false,'infolog',$values['info_id'],'',$app,$id);
			}
			if ($data['type'] == 'link-entry')
			{
				list($app,$id) = explode(':',$values['#'.$name]);
			}
			else
			{
				$app = $data['type'];
				$id = $values['#'.$name];
			}
			if ($id)	// create new link, does nothing for already existing links 
			{
				$this->link->link('infolog',$values['info_id'],$app,$id);
			}
		}
	}

	/**
	 * Query the number of children / subs
	 *
	 * @param int $info_id id 
	 * @return int number of subs
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
		//echo "<p>boinfolog::search(".print_r($query,True).")</p>\n";
		$ret = $this->so->search($query);
		
		// convert system- to user-time
		if (is_array($ret) && $this->tz_offset_s)
		{
			foreach($ret as $id => $data)
			{
				foreach($this->timestamps as $time)
				{
					if ($data[$time]) $ret[$id][$time] += $this->tz_offset_s;
				}
			}
		}
		if ($this->xmlrpc && is_array($ret))
		{
			$infos =& $ret;
			unset($ret);
			$ret = array();
			foreach($infos as $id => $data)
			{
				$ret[] = $this->data2xmlrpc($data);
			}
		}
		//echo "<p>boinfolog::search(".print_r($query,True).")=<pre>".print_r($ret,True)."</pre>\n";
		return $ret;
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
		$info = array(
			'info_id' => 0,
			'info_type' => isset($this->enums['type']['email']) ? 'email' : 'note',
			'info_from' => implode(',',$name),
			'info_addr' => implode(',',$email),
			'info_subject' => $_subject,
			'info_des' => $_message,
			'info_startdate' => $_date,
			'info_status' => 'done',
			'info_priority' => 1,
			'info_percent' => 100,
			'referer' => false,
			'link_to' => array(
				'to_app' => 'infolog',
				'to_id' => 0,
			),
		);
		// find the addressbookentry to link with
		$addressbook =& CreateObject('addressbook.bocontacts');
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
				$this->link->link('infolog',$info['link_to']['to_id'],'addressbook',$contact['id']);
			}
		}
		if (is_array($_attachments))
		{
			foreach ($_attachments as $attachment)
			{
				if(is_readable($attachment['tmp_name']))
				{
					$this->link->link('infolog',$info['link_to']['to_id'],'file',$attachment);
				}
			}
		}
		return $info;			
	}

	/**
	 * Hook called by link-class to include infolog in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	function search_link($location)
	{
		return array(
			'query'      => 'infolog.boinfolog.link_query',
			'title'      => 'infolog.boinfolog.link_title',
			'view'       => array(
				'menuaction' => 'infolog.uiinfolog.index',
				'action' => 'sp'
			),
			'view_id'    => 'action_id',
			'add' => array(
				'menuaction' => 'infolog.uiinfolog.edit',
				'type'   => 'task'
			),
			'add_app'    => 'action',
			'add_id'     => 'action_id',
			'add_popup'  => '750x550',			
		);
	}

	/**
	 * get title for an infolog entry identified by $info
	 * 
	 * Is called as hook to participate in the linking
	 *
	 * @param int/array $info int info_id or array with infolog entry
	 * @return string/boolean string with the title, null if $info not found, false if no perms to view
	 */
	function link_title( $info )
	{
		if (!is_array($info))
		{
			$info = $this->read( $info,false );
		}
		if (!$info)
		{
			return $info;
		}
		return !empty($info['info_subject']) ? $info['info_subject'] :
			$this->subject_from_des($info['info_descr']);
	}

	/**
	 * query infolog for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @return array with info_id - title pairs of the matching entries
	 */
	function link_query( $pattern )
	{
		$query = array(
			'search' => $pattern,
			'start'  => 0,
			'subs'   => true,
		);
		$ids = $this->search($query);
		$content = array();
		if (is_array($ids))
		{
			foreach($ids as $id => $info )
			{
				$content[$id] = $this->link_title($id);
			}
		}
		return $content;
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
		if (!is_object($GLOBALS['egw']->html))
		{
			$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
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
		if ($this->customfields && !$GLOBALS['egw_info']['user']['preferences']['infolog']['cal_show_custom'])
		{
			$query['col_filter']['info_type'] = array('task','phone','note','email');
		}
		while ($infos = $this->search($query))
		{
			foreach($infos as $info)
			{
				$time = (int) adodb_date('Hi',$info['info_startdate']);
				$date = adodb_date('Y/m/d',$info['info_startdate']);
				/* As event-like infologs are not showen in current calendar, 
				we need to present all open infologs to the user! (2006-06-27 nelius)
				if ($do_events && !$time ||
				    !$do_events && $time && $date == $date_wanted)
				{
					continue;
				}*/
				$title = ($do_events?$GLOBALS['egw']->common->formattime(adodb_date('H',$info['info_startdate']),adodb_date('i',$info['info_startdate'])).' ':'').
					$info['info_subject'];
				$view = $this->link->view('infolog',$info['info_id']);
				$content=array();
				foreach($icons = array(
					$info['info_type']   => 'infolog',
					$this->status[$info['info_type']][$info['info_status']] => 'infolog',
				) as $name => $app)
				{
					$content[] = $GLOBALS['egw']->html->image($app,$name,lang($name),'border="0" width="15" height="15"').' ';
				}
				$content[] = $GLOBALS['egw']->html->a_href($title,$view);
				$content = $GLOBALS['egw']->html->table(array(1 => $content));

				$to_include[] = array(
					'starttime' => $info['info_startdate'],
					'endtime'   => ($info['info_enddate'] ? $info['info_enddate'] : $info['info_startdate']),
					'title'     => $title,
					'view'      => $view,
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
	 * handles introspection or discovery by the logged in client,
	 *  in which case the input might be an array.  The server always calls
	 *  this function to fill the server dispatch map using a string.
	 *
	 * @param string $_type='xmlrpc' xmlrpc or soap
	 * @return array
	 */
	function list_methods($_type='xmlrpc')
	{
		if (is_array($_type))
		{
			$_type = $_type['type'] ? $_type['type'] : $_type[0];
		}

		switch($_type)
		{
			case 'xmlrpc':
				$xml_functions = array(
					'read' => array(
						'function'  => 'read',
						'signature' => array(array(xmlrpcInt,xmlrpcInt)),
						'docstring' => lang('Read one record by passing its id.')
					),
					'search' => array(
						'function'  => 'search',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Returns a list / search for records.')
					),
					'write' => array(
						'function'  => 'write',
						'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
						'docstring' => lang('Write (add or update) a record by passing its fields.')
					),
					'delete' => array(
						'function'  => 'delete',
						'signature' => array(array(xmlrpcInt,xmlrpcInt)),
						'docstring' => lang('Delete one record by passing its id.')
					),
					'categories' => array(
						'function'  => 'categories',
						'signature' => array(array(xmlrpcBoolean,xmlrpcBoolean)),
						'docstring' => lang('List all categories')
					),
					'list_methods' => array(
						'function'  => 'list_methods',
						'signature' => array(array(xmlrpcStruct,xmlrpcString)),
						'docstring' => lang('Read this list of methods.')
					)
				);
				return $xml_functions;
				break;
			case 'soap':
				return $this->soap_functions;
				break;
			default:
				return array();
				break;
		}
	}

	/**
	 * Convert an InfoLog entry into its xmlrpc representation, eg. convert timestamps to datetime.iso8601
	 *
	 * @param array $data infolog entry
	 * @param array xmlrpc infolog entry
	 */
	function data2xmlrpc($data)
	{
		$data['rights'] = $this->so->grants[$data['info_owner']];
		
		// translate timestamps
		if($data['info_enddate'] == 0) unset($data['info_enddate']);
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = $GLOBALS['server']->date2iso8601($data[$name]);
			}
		}
		$ret[$id]['info_percent'] = (int)$data['info_percent'].'%';

		// translate cat_id
		if (isset($data['info_cat']))
		{
			$data['info_cat'] = $GLOBALS['server']->cats2xmlrpc(array($data['info_cat']));
		}
		foreach($data as $name => $val)
		{
			if (substr($name,0,5) == 'info_')
			{
				unset($data[$name]);
				$data[substr($name,5)] = $val;
			}
		}
		// unsetting everything which could result in an typeless <value />
		foreach($data as $key => $value)
		{
			if (is_null($value) || is_array($value) && !$value)
			{
				unset($data[$key]);
			}
		}
		return $data;
	}

	/**
	 * Convert an InfoLog xmlrpc representation into the internal one, eg. convert datetime.iso8601 to timestamps
	 *
	 * @param array $data infolog entry
	 * @param array xmlrpc infolog entry
	 */
	function xmlrpc2data($data)
	{
		foreach($data as $name => $val)
		{
			if (substr($name,0,5) != 'info_')
			{
				unset($data[$name]);
				$data['info_'.$name] = $val;
			}
		}
		// translate timestamps
		foreach($this->timestamps as $name)
		{
			if (isset($data[$name]))
			{
				$data[$name] = $GLOBALS['server']->iso86012date($data[$name],True);
			}
		}
		// translate cat_id
		if (isset($data['info_cat']))
		{
			$cats = $GLOBALS['server']->xmlrpc2cats($data['info_cat']);
			$data['info_cat'] = (int)$cats[0];
		}
		return $data;
	}

	/**
	 * return array with all infolog categories (for xmlrpc)
	 *
	 * @param boolean $complete true returns array with all data for each cat, else only the title is returned
	 * @return array with cat_id / title or data pairs (see above)
	 */
	function categories($complete = False)
	{
		return $this->xmlrpc ? $GLOBALS['server']->categories($complete) : False;
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
			foreach((array) $icons as $id => $status)
			{
				if ($status && substr($status,-1) != '%')
				{
					$icons[$id] = 'infolog/'.$status;
				}
			}
		}
		return $icons;
	}
}
