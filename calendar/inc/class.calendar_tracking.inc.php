<?php
/**
 * Calendar - history and notifications
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Calendar - tracking object
 */
class calendar_tracking extends Api\Storage\Tracking
{
	/**
	 * Application we are tracking (required!)
	 *
	 * @var string
	 */
	var $app = 'calendar';
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field = 'id';
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field = 'creator';
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field;
	/**
	 * Translate field-name to 2-char history status
	 *
	 * @var array
	 */
	var $field2history = array(
		'owner'		=>	'owner',
		'category'	=>	'category',
		'priority'	=>	'priority',
		'public'	=>	'public',
		'title'		=>	'title',
		'description'	=>	'description',
		'location'	=>	'location',
		'reference'	=>	'reference',
		'non_blocking'	=>	'non_blocking',
		'special'	=>	'special',
		'recurrence'	=>	'recurrence',
		'recur_enddate'	=>	'recur_enddate',
		'tz_id'		=>	'tz_id',

		'start'		=>	'start',
		'end'		=>	'end',
		'deleted'   =>  'deleted',

		'participants'	=>	array('user_id', 'status', 'role', 'recur'),
		'participants-c'	=>	array('user_id', 'status', 'quantity', 'role', 'recur'),
		'participants-r'	=>	array('user_id', 'status', 'quantity', 'role', 'recur'),

		// Custom fields added in constructor
	);

	/**
	 * Translate field name to label
	 */
	public $field2label = array(
		'owner'		=>	'owner',
		'category'	=>	'category',
		'priority'	=>	'priority',
		'public'	=>	'public',
		'title'		=>	'title',
		'description'	=>	'description',
		'location'	=>	'location',
		'reference'	=>	'reference',
		'non_blocking'	=>	'non blocking',
		'special'	=>	'special',
		'recurrence'=>	'recurrence',
		'recur_enddate'	=>	'recurrence enddate',
		'tz_id'		=>	'timezone',

		'start'		=>	'start',
		'end'		=>	'end',
		'deleted'   =>  'deleted',

		'participants'	=>	'Participants: User, Status, Role',
		'participants-c'=>	'Participants: User, Status, Quantity, Role',
		'participants-r'=>	'Participants: Resource, Status, Quantity',

		// Custom fields added in constructor
	);

	/**
	 * Should the user (passed to the track method or current user if not passed) be used as sender or get_config('sender')
	 *
	 * @var boolean
	 */
	var $prefer_user_as_sender = true;

	/**
	 * Constructor
	 *
	 * @param calendar_bo &$calendar_bo
	 */
	public function __construct()
	{
		parent::__construct('calendar');	// adds custom fields
	}

	/**
	 * Tracks the changes in one entry $data, by comparing it with the last version in $old
	 * Overrides parent to reformat participants into a format parent can handle
	 */
	public function track(array $data,array $old=null,$user=null,$deleted=null,array $changed_fields=null, $skip_notification = false)
	{
		// Don't try to track dates on recurring events.
		// It won't change for the base event, and any change to the time creates an exception
		if($data['recur_type'])
		{
			unset($data['start']); unset($data['end']);
			unset($old['start']); unset($old['end']);
		}

		/**
		* Do some magic with the participants and recurrance.
		* If this is one of a recurring event, append the recur_date to the participant field so we can
		* filter by it later.
		*/
		if(is_array($data['participants']))
		{
			$participants = $data['participants'];
			$data['participants'] = array();
			$data = array_merge($data, $this->alter_participants($participants));
		}
		// if clients eg. CalDAV do NOT set participants, they are left untouched
		// therefore we should not track them, as all updates then show up as all participants removed
		elseif(!isset($data['participants']))
		{
			unset($old['participants']);
		}
		if(is_array($old['participants']))
		{
			$participants = $old['participants'];
			$old['participants'] = array();
			$old = array_merge($old, $this->alter_participants($participants));
		}
		// Make sure dates are timestamps
		foreach(array('start','end') as $date)
		{
			if(is_object($data[$date]) && is_a($data[$date], 'DateTime'))
			{
				$data[$date] = $data[$date]->format('ts');
			}
		}
		parent::track($data,$old,$user,$deleted, $changed_fields, $skip_notification);
	}

	/**
	 * Overrides parent because calendar_boupdates handles the notifications
	 */
	public function do_notifications($data,$old,$deleted=null, &$email_notified = null)
	{
		unset($data, $old, $deleted);	// unused, but required by function signature
		return true;
	}


	/**
	 * Compute changes between new and old data
	 *
	 * Can be used to check if saving the data is really necessary or user just pressed save
	 * Overridden to handle various participants options
	 *
	 * @param array $data
	 * @param array $old = null
	 * @return array of keys with different values in $data and $old
	 */
	public function changed_fields(array $data,array $old=null)
	{
		if(is_array($data['participants']))
		{
			$participants = $data['participants'];
			$data['participants'] = array();
			$data = array_merge($data, $this->alter_participants($participants));
		}
		if(is_array($old['participants']))
		{
			$participants = $old['participants'];
			$old['participants'] = array();
			$old = array_merge($old, $this->alter_participants($participants));
		}
		return parent::changed_fields($data,$old);
	}

	/**
	* Do some magic with the participants and recurrance.
	* If this is one of a recurring event, append the recur_date to the participant field so we can
	* filter by it later.
	*/
	protected function alter_participants($participants)
	{
		$data = array();
		foreach($participants as $uid => $status)
		{
			$quantity = $role = $user_type = $user_id = null;
			calendar_so::split_status($status, $quantity, $role);
			calendar_so::split_user($uid, $user_type, $user_id);
			$field = is_numeric($uid) ? 'participants' : 'participants-'.$user_type;
			$data[$field][] = array(
				'user_id'	=>	$user_id,
				'status'	=>	$status,
				'quantity'	=>	$quantity,
				'role'		=>	$role,
				'recur'		=>	$data['recur_date'] ? $data['recur_date'] : 0,
			);
		}
		return $data;
	}
}
