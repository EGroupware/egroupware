<?php
/**
 * Timesheet - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package tracker
 * @copyright (c) 2006-8 by Ralf Becker <RalfBecker-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.timesheet_tracking.inc.php 26515 2009-03-24 11:50:16Z leithoff $
 */

/**
 * Timesheet - tracking object for the tracker
 */
class timesheet_tracking extends bo_tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'timesheet';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'ts_id';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field = 'ts_owner';
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field = 'ts_assigned';
	/**
	 * Translate field-name to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array();
	/**
	 * Should the user (passed to the track method or current user if not passed) be used as sender or get_config('sender')
	 *
	 * @var boolean
	 */
	var $prefer_user_as_sender = false;
	/**
	 * Instance of the timesheet_bo class calling us
	 *
	 * @access private
	 * @var timesheet_bo
	 */
	var $timesheet;

	/**
	 * Constructor
	 *
	 * @param timesheet_bo $botimesheet
	 * @return timesheet_tracking
	 */
	function __construct($bo)
	{
		parent::__construct();	// calling the constructor of the extended class

		$this->bo = $bo;

		$this->field2history = $this->bo->field2history;

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
	function get_config($name,$data,$old=null)
	{
		$timesheet = $data['ts_id'];

		//$config = $this->timesheet->notification[$timesheet][$name] ? $this->timesheet->notification[$timesheet][$name] : $this->$timesheet->notification[0][$name];
		//no nitify configert (ToDo)
		return $config;
	}

	/**
	 * Get the subject for a given entry, reimplementation for get_subject in bo_tracking
	 *
	 * Default implementation uses the link-title
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	function get_subject($data,$old)
	{
		return '#'.$data['ts_id'].' - '.$data['ts_title'];
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
		if (!$data['ts_modified'] || !$old)
		{
			return lang('New timesheet submitted by %1 at %2',
				common::grab_owner_name($data['ts_creator']),
				$this->datetime($data['ts_created']));
		}
		return lang('Timesheet modified by %1 at %2',
			$data['ts_modifier'] ? common::grab_owner_name($data['ts_modifier']) : lang('Timesheet'),
			$this->datetime($data['ts_modified']));
	}
}
