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

require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.bo_tracking.inc.php');

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
	var $field2history = array();
	/**
	 * Translate field-names to labels
	 *
	 * @var array
	 */
	var $field2label = array(
		'info_type'      => 'Type',
		'info_from'      => 'Contact',
		'info_addr'      => 'Phone/Email',
		'info_cat'       => 'Category',
		'info_priority'  => 'Priority',
		'info_owner'     => 'Owner',
		'info_status'    => 'Status',
		'info_percent'   => 'Completed',
		'info_datecompleted' => 'Date completed',
		'info_location'  => 'Location',
		'info_startdate' => 'Startdate',
		'info_enddate'   => 'Enddate',
		'info_responsible' => 'Responsible',
		'info_subject'   => 'Subject',
	);

	/**
	 * Instance of the boinfolog class calling us
	 * 
	 * @access private
	 * @var boinfolog
	 */
	var $infolog;

	/**
	 * Constructor
	 *
	 * @param botracker $botracker
	 * @return tracker_tracking
	 */
	function infolog_tracking(&$boinfolog)
	{
		$this->infolog =& $boinfolog;
	}
	
	/**
	 * Tracks the changes in one entry $data, by comparing it with the last version in $old
	 * 
	 * Reimplemented to fix some fields, who otherwise allways show up as modified
	 *
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param int $user=null user who made the changes, default to current user
	 * @return int/boolean false on error, integer number of changes logged or true for new entries ($old == null)
	 */
	function track($data,$old=null,$user=null)
	{
		if ($old)
		{
			foreach($this->infolog->timestamps as $name)
			{
				if (!$old[$name]) $old[$name] = '';
			}
		}
		return parent::track($data,$old,$user);
	}
	
	
	/**
	 * Get a notification-config value
	 *
	 * @param string $what
	 * 	- 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'sender' string send email address
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	function get_config($name,$data,$old)
	{
		return null;
	}
	
	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 * 
	 * @param array $data
	 * @param array $old
	 * @return array/string array(message,user-id,timestamp-in-servertime) or string
	 */
	function get_message($data,$old)
	{
		if (!$data['info_datemodified'] || !$old)
		{
			return lang('New %1 created by %2 at %3',lang($this->infolog->enums['type'][$data['info_type']]),
				$GLOBALS['egw']->common->grab_owner_name($this->infolog->user),$this->datetime(time()));
		}
		return lang('%1 modified by %2 at %3',lang($this->infolog->enums['type'][$data['info_type']]),
			$GLOBALS['egw']->common->grab_owner_name($data['info_modifier']),
			$this->datetime($data['info_datemodified']-$this->infolog->tz_offset_s));
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
		$responsible = array();
		if ($data['info_responsible'])
		{
			foreach($data['info_responsible'] as $uid)
			{
				$responsible[] = $GLOBALS['egw']->common->grab_owner_name($uid);
			}
		}
		if ($data['info_cat'] && !is_object($GLOBALS['egw']->categories))
		{
			require_once(EGW_API_INC.'/class.categories.inc.php');
			$GLOBALS['egw']->categories =& new categories($this->infolog->user,'infolog');
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
			'info_owner'     => $GLOBALS['egw']->common->grab_owner_name($data['info_owner']),
			'info_status'    => lang($this->infolog->status[$data['info_type']][$data['info_status']]),
			'info_percent'   => (int)$data['info_percent'].'%',
			'info_datecompleted' => $data['info_datecomplete'] ? $this->datetime($data['info_datecompleted']-$this->infolog->tz_offset_s) : '',
			'info_location'  => $data['info_location'],
			'info_startdate' => $data['info_startdate'] ? $this->datetime($data['info_startdate']-$this->infolog->tz_offset_s) : '',
			'info_enddate'   => $data['info_enddate'] ? $this->datetime($data['info_enddate']-$this->infolog->tz_offset_s) : '',
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
				if ($field['type2'] && $field['type2'] != $data['info_type']) continue;	// different type
				
				if (!$header_done)
				{
					$details['custom'] = array(
						'value' => lang('Custom fields').':',
						'type'  => 'reply',
					);
					$header_done = true;
				}
				$details[$name] = array(
					'label' => $field['label'],
					'value' => $data['#'.$name],
				);
			}
		}
		return $details;
	}
}