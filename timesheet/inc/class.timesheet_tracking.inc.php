<?php
/**
 * Timesheet - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package tracker
 * @copyright (c) 2006-16 by Ralf Becker <RalfBecker-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.timesheet_tracking.inc.php 26515 2009-03-24 11:50:16Z leithoff $
 */

use EGroupware\Api;

/**
 * Timesheet - tracking object for the tracker
 */
class timesheet_tracking extends Api\Storage\Tracking
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
	 * @param timesheet_bo $bo
	 * @return timesheet_tracking
	 */
	function __construct(timesheet_bo $bo)
	{
		$this->bo = $bo;

		//set fields for tracking
		$this->field2history = array_keys($this->bo->db_cols);
		$this->field2history = array_diff(array_combine($this->field2history,$this->field2history),array('ts_modified'));
		$this->field2history += array('customfields'   => '#c');	// to display old customfield data in history

		// custom fields are now handled by parent::__construct('tracker')
		parent::__construct('timesheet');
	}

	/**
	 * Get the subject for a given entry, reimplementation for get_subject in Api\Storage\Tracking
	 *
	 * Default implementation uses the link-title
	 *
	 * @param array $data
	 * @param array $old
	 * @param boolean $deleted =null can be set to true to let the tracking know the item got deleted or undeleted
	 * @param int|string $receiver numeric account_id or email address
	 * @return string
	 */
	protected function get_subject($data,$old,$deleted=null,$receiver=null)
	{
		return '#'.$data['ts_id'].' - '.$data['ts_title'];
	}

	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	protected function get_message($data,$old,$receiver=null)
	{
		if (!$data['ts_modified'] || !$old)
		{
			return lang('New timesheet submitted by %1 at %2',
				Api\Accounts::username($data['ts_creator']),
				$this->datetime($data['ts_created']));
		}
		return lang('Timesheet modified by %1 at %2',
			$data['ts_modifier'] ? Api\Accounts::username($data['ts_modifier']) : lang('Timesheet'),
			$this->datetime($data['ts_modified']));
	}
}
