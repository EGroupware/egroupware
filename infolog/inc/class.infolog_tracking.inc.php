<?php
/**
 * InfoLog - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package tracker
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Tracker - tracking object for the tracker
 */
class infolog_tracking extends bo_tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'infolog';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'info_id';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field = 'info_owner';
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field = 'info_responsible';
	/**
	 * Translate field-names to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array(
		'info_type'          => 'Ty',
		'info_from'          => 'Fr',
		'info_addr'          => 'Ad',
		'info_link_id'       => 'Li',
		'info_cat'           => 'Ca',
		'info_priority'      => 'Pr',
		'info_owner'         => 'Ow',
		'info_access'        => 'Ac',
		'info_status'        => 'St',
		'info_percent'       => 'Pe',
		'info_datecompleted' => 'Co',
		'info_location'      => 'Lo',
		'info_startdate'     => 'st',
		'info_enddate'       => 'En',
		'info_responsible'   => 'Re',
		'info_subject'       => 'Su',
		'info_des'           => 'De',
		'info_location'      => 'Lo',
		// PM fields
		'info_planned_time'  => 'pT',
		'info_used_time'     => 'uT',
		'pl_id'              => 'pL',
		'info_price'         => 'pr',
		// all custom fields together
		'custom'             => '#c',
	);
	/**
	 * Translate field-names to labels
	 *
	 * @note The order of these fields is used to determine the order for CSV export
	 * @var array
	 */
	var $field2label = array(
		'info_type'      => 'Type',
		'info_from'      => 'Contact',
		'info_subject'   => 'Subject',
		'info_des'       => 'Description',
		'info_addr'      => 'Phone/Email',
		'info_link_id'   => 'primary link',
		'info_cat'       => 'Category',
		'info_priority'  => 'Priority',
		'info_owner'     => 'Owner',
		'info_access'    => 'Access',
		'info_status'    => 'Status',
		'info_percent'   => 'Completed',
		'info_datecompleted' => 'Date completed',
		'info_datemodified' => 'Last changed',
		'info_location'  => 'Location',
		'info_startdate' => 'Startdate',
		'info_enddate'   => 'Enddate',
		'info_responsible' => 'Responsible',
		// PM fields
		'info_planned_time'  => 'planned time',
		'info_used_time'     => 'used time',
		'pl_id'              => 'pricelist',
		'info_price'         => 'price',
		// custom fields
		'custom'             => 'custom fields'
	);

	/**
	 * Instance of the infolog_bo class calling us
	 *
	 * @var infolog_bo
	 */
	private $infolog;

	/**
	 * Constructor
	 *
	 * @param botracker $botracker
	 * @return tracker_tracking
	 */
	function __construct(&$infolog_bo)
	{
		parent::__construct();	// calling the constructor of the extended class

		$this->infolog =& $infolog_bo;
	}

	/**
	 * Get the subject for a given entry
	 *
	 * Reimpleneted to use a New|deleted|modified prefix.
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_subject($data,$old)
	{
		if ($data['prefix'])
		{
			$prefix = $data['prefix'];	// async notification
		}
		elseif (!$old || $old['info_status'] == 'deleted')
		{
			$prefix = lang('New %1',lang($this->infolog->enums['type'][$data['info_type']]));
		}
		elseif($data['info_status'] == 'deleted')
		{
			$prefix = lang('%1 deleted',lang($this->infolog->enums['type'][$data['info_type']]));
		}
		else
		{
			$prefix = lang('%1 modified',lang($this->infolog->enums['type'][$data['info_type']]));
		}
		return $prefix.': '.$data['info_subject'];
	}

	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_message($data,$old)
	{
		if ($data['message']) return $data['message'];	// async notification

		if (!$old || $old['info_status'] == 'deleted')
		{
			return lang('New %1 created by %2 at %3',lang($this->infolog->enums['type'][$data['info_type']]),
				common::grab_owner_name($this->infolog->user),$this->datetime('now'));
		}
		elseif($data['info_status'] == 'deleted')
		{
			return lang('%1 deleted by %2 at %3',lang($this->infolog->enums['type'][$data['info_type']]),
				common::grab_owner_name($data['info_modifier']),
				$this->datetime($data['info_datemodified']));
		}
		return lang('%1 modified by %2 at %3',lang($this->infolog->enums['type'][$data['info_type']]),
			common::grab_owner_name($data['info_modifier']),
			$this->datetime($data['info_datemodified']));
	}

	/**
	 * Get the details of an entry
	 *
	 * @param array $data
	 * @param string $datetime_format of user to notify, eg. 'Y-m-d H:i'
	 * @param int $tz_offset_s offset in sec to be add to server-time to get the user-time of the user to notify
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data)
	{
		$header_done = false;
		$responsible = array();
		if ($data['info_responsible'])
		{
			foreach($data['info_responsible'] as $uid)
			{
				$responsible[] = common::grab_owner_name($uid);
			}
		}
		if ($GLOBALS['egw_info']['user']['preferences']['infolog']['show_id'])
		{
			$id = ' #'.$data['info_id'];
		}
		foreach(array(
			'info_type'      => lang($this->infolog->enums['type'][$data['info_type']]).$id,
			'info_from'      => $data['info_from'],
			'info_addr'      => $data['info_addr'],
			'info_cat'       => $data['info_cat'] ? $GLOBALS['egw']->categories->id2name($data['info_cat']) : '',
			'info_priority'  => lang($this->infolog->enums['priority'][$data['info_priority']]),
			'info_owner'     => common::grab_owner_name($data['info_owner']),
			'info_status'    => lang($data['info_status']=='deleted'?'deleted':$this->infolog->status[$data['info_type']][$data['info_status']]),
			'info_percent'   => (int)$data['info_percent'].'%',
			'info_datecompleted' => $data['info_datecompleted'] ? $this->datetime($data['info_datecompleted']) : '',
			'info_location'  => $data['info_location'],
			'info_startdate' => $data['info_startdate'] ? $this->datetime($data['info_startdate'],null) : '',
			'info_enddate'   => $data['info_enddate'] ? $this->datetime($data['info_enddate'],false) : '',
			'info_responsible' => implode(', ',$responsible),
			'info_subject'   => $data['info_subject'],
		) as $name => $value)
		{
			$details[$name] = array(
				'label' => lang($this->field2label[$name]),
				'value' => $value,
			);
			if ($name == 'info_subject') $details[$name]['type'] = 'summary';
		}
		$details['info_des'] = array(
			'value' => $data['info_des'],
			'type'  => 'multiline',
		);
		// should be moved to bo_tracking because auf the different custom field types
		if ($this->infolog->customfields)
		{
			foreach($this->infolog->customfields as $name => $field)
			{
				if ($field['type2'] && !in_array($data['info_type'],explode(',',$field['type2']))) continue;	// different type

				if (!$header_done)
				{
					$details['custom'] = array(
						'value' => lang('Custom fields').':',
						'type'  => 'reply',
					);
					$header_done = true;
				}
				$details['#'.$name] = array(
					'label' => $field['label'],
					'value' => (is_array($field['values']) && !empty($field['values']) && isset($data['#'.$name]) &&
						array_key_exists($data['#'.$name],$field['values']))?$field['values'][$data['#'.$name]] : $data['#'.$name],
				);
			}
		}
		return $details;
	}

	/**
	 * Track changes
	 *
	 * Overrides parent to log the modified date in the history, but not to send a notification
	 *
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param int $user=null user who made the changes, default to current user
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undeleted
	 * @param array $changed_fields=null changed fields from ealier call to $this->changed_fields($data,$old), to not compute it again
	 * @param boolean $skip_notification=false do NOT send any notification
	 * @return int|boolean false on error, integer number of changes logged or true for new entries ($old == null)
	 */
	public function track(array $data,array $old=null,$user=null,$deleted=null,array $changed_fields=null,$skip_notification=false)
	{
		$this->user = !is_null($user) ? $user : $GLOBALS['egw_info']['user']['account_id'];

		$changes = true;

		if ($old && $this->field2history)
		{
			$changes = $this->save_history($data,$old,$deleted,$changed_fields);
		}

		// Don't notify if the only change was to the modified date
		if(is_null($changed_fields))
		{
			$changed_fields = $this->changed_fields($data, $old);
			$changes = count($changed_fields); // we need that since TRUE evaluates to 1
		}
		if(is_array($changed_fields) && $changes == 1 && in_array('info_datemodified', $changed_fields))
		{
			return count($changes);
		}

                // do not run do_notifications if we have no changes
                if ($changes && !$skip_notification && !$this->do_notifications($data,$old,$deleted))
                {
                        $changes = false;
                }
                return $changes;
        }

	/**
	 * Save changes to the history log
	 *
	 * Reimplemented to store all customfields in a single field, as the history-log has only 2-char field-ids
	 *
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param int number of log-entries made
	 */
	function save_history($data,$old)
	{
		$data_custom = $old_custom = array();
		foreach($this->infolog->customfields as $name => $custom)
		{
			if (isset($data['#'.$name]) && (string)$data['#'.$name]!=='') $data_custom[] = $custom['label'].': '.$data['#'.$name];
			if (isset($old['#'.$name]) && (string)$old['#'.$name]!=='') $old_custom[] = $custom['label'].': '.$old['#'.$name];
		}
		$data['custom'] = implode("\n",$data_custom);
		$old['custom'] = implode("\n",$old_custom);

		return parent::save_history($data,$old);
	}
}
