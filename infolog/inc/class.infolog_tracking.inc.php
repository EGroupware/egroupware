<?php
/**
 * InfoLog - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package tracker
 * @copyright (c) 2007-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
		'info_cc'            => 'cc',
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
		'info_startdate' => 'Start date',
		'info_enddate'   => 'Due date',
		'info_responsible' => 'Responsible',
		'info_cc'        => 'Cc',
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
		parent::__construct('infolog');	// add custom fields from infolog

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
	 * @param array|object $data
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data,$receiver=null)
	{
		//error_log(__METHOD__.__LINE__.' Data:'.array2string($data));
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
			'info_enddate'   => $data['info_enddate'] ? $this->datetime($data['info_enddate'],null) : '',
			'info_responsible' => implode(', ',$responsible),
			'info_subject'   => $data['info_subject'],
		) as $name => $value)
		{
			//error_log(__METHOD__.__LINE__.' Key:'.$name.' val:'.array2string($value));
			if ($name=='info_from' && empty($value) && !empty($data['info_contact']) && is_array($data['link_to']['to_id']))
			{
				$lkeys = array_keys($data['link_to']['to_id']);
				if (in_array($data['info_contact'],$lkeys))
				{
					list($app,$id) = explode(':',$data['info_contact']);
					if (!empty($app)&&!empty($id)) $value = egw_link::title($app,$id);
				}
			}
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
		// add custom fields for given type
		$details += $this->get_customfields($data, $data['info_type']);

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
		//error_log(__METHOD__.__LINE__.' notify?'.($skip_notification?'no':'yes').function_backtrace());
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
		//error_log(__METHOD__.__LINE__.array2string($changed_fields));
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
	 * Get a notification-config value
	 *
	 * @param string $what
	 *  - 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'sender' string send email address
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	function get_config($name,$data,$old=null)
	{
		$config = array();
		switch($name)
		{
			case 'copy':	// include the info_cc addresses
				if ($data['info_access'] == 'private') return array();	// no copies for private entries
				if ($data['info_cc'])
				{
					$config = array_merge($config,preg_split('/, ?/',$data['info_cc']));
				}
				break;
		}
		return $config;
	}
}
