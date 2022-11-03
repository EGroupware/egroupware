<?php
/**
 * EGroupware API - abstract base class for tracking (history log, notifications, ...)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage storage
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;

// explicitly reference classes still in phpgwapi or otherwise outside api
use notifications;


/**
 * Abstract base class for trackering:
 *  - logging all modifications of an entry
 *  - notifying users about changes in an entry
 *
 * You need to extend these class in your application:
 *	1. set the required class-vars: app, id_field
 *	2. optional set class-vars: creator_field, assigned_field, check2prefs
 *	3. implement the required methods: get_config, get_details
 *	4. optionally re-implement: get_title, get_subject, get_body, get_attachments, get_link, get_notification_link, get_message
 * They are all documented in this file via phpDocumentor comments.
 *
 * Translate field-name to history status field:
 * As history status was only char(2) prior to EGroupware 1.6, a mapping was necessary.
 * Now it's varchar(64) and a mapping makes no sense for new applications, just list
 * all fields to log as key AND value!
 *
 * History login supports now 1:N relations on a base record. To use that you need:
 * - to have the 1:N relation as array of arrays with the values of that releation, eg:
 * $data = array(
 * 	'id' => 123,
 *  'title' => 'Something',
 *  'date'  => '2009-08-21 14:42:00',
 * 	'participants' => array(
 * 		array('account_id' => 15, 'name' => 'User Hugo', 'status' => 'A', 'quantity' => 1),
 * 		array('account_id' => 17, 'name' => 'User Bert', 'status' => 'U', 'quantity' => 3),
 *  ),
 * );
 * - set field2history as follows
 * $field2history = array(
 * 	'id' => 'id',
 *  'title' => 'title',
 *  'participants' => array('uid','status','quantity'),
 * );
 * - set content for history log widget:
 * $content['history'] = array(
 * 	'id' => 123,
 *  'app' => 'calendar',
 *  'num_rows' => 50, // optional, defaults to 50
 *  'status-widgets' => array(
 * 		'title' => 'label',	// no need to set, as default is label
 * 		'date'  => 'datetime',
 * 		'participants' = array(
 * 			'select-account',
 * 			array('U' => 'Unknown', 'A' => 'Accepted', 'R' => 'Rejected'),
 * 			'integer',
 * 		),
 *  ),
 * );
 * - set lables for history:
 * $sel_options['status'] = array(
 * 	'title' => 'Title',
 *  'date'  => 'Starttime',
 *  'participants' => 'Participants: User, Status, Quantity',	// a single label!
 * );
 *
 * The above is also an example for using regular history login in EGroupware (by skipping the 'participants' key).
 */
abstract class Tracking
{
	/**
	 * Application we are tracking
	 *
	 * @var string
	 */
	var $app;
	/**
	 * Name of the id-field, used as id in the history log (required!)
	 *
	 * @var string
	 */
	var $id_field;
	/**
	 * Name of the field with the creator id, if the creator of an entry should be notified
	 *
	 * @var string
	 */
	var $creator_field;
	/**
	 * Name of the field with the id(s) of assinged users, if they should be notified
	 *
	 * @var string
	 */
	var $assigned_field;
	/**
	 * Can be used to map the following prefs to different names:
	 *  - notify_creator  - user wants to be notified for items he created
	 *  - notify_assigned - user wants to be notified for items assigned to him
	 * @var array
	 */
	var $check2pref;
	/**
	 * Translate field-name to history status field (see comment in class header)
	 *
	 * @var array
	 */
	var $field2history = array();
	/**
	 * Should the user (passed to the track method or current user if not passed) be used as sender or get_config('sender')
	 *
	 * @var boolean
	 */
	var $prefer_user_as_sender = true;
	/**
	 * Should the current user be email-notified (about change he made himself)
	 *
	 * Popup notifications are never send to the current user!
	 *
	 * @var boolean
	 */
	var $notify_current_user = false;

	/**
	 * Class to use for generating the notifications.
	 * Normally, just the notification class but for testing we pass in a mocked
	 * class
	 *
	 * @var string class-name
	 */
	protected $notification_class = notifications::class;

	/**
	 * Array with error-messages if track($data,$old) returns false
	 *
	 * @var array
	 */
	var $errors = array();

	/**
	 * Instance of the History object for the app we are tracking
	 *
	 * @var History
	 */
	protected $historylog;

	/**
	 * Current user, can be set via bo_tracking::track(,,$user)
	 *
	 * @access private
	 * @var int;
	 */
	var $user;

	/**
	 * Datetime format of the currently notified user (send_notificaton)
	 *
	 * @var string
	 */
	var $datetime_format;
	/**
	 * Should the class allow html content (for notifications)
	 *
	 * @var boolean
	 */
	var $html_content_allow = false;

	/**
	 * Custom fields of type link entry or application
	 *
	 * Used to automatic create or update a link
	 *
	 * @var array field => application name pairs (or empty for link entry)
	 */
	var $cf_link_fields = array();

	/**
	 * Separator for 1:N relations
	 *
	 */
	const ONE2N_SEPERATOR = '~|~';

	/**
	 * Marker for change stored as unified diff, not old/new value
	 * Diff is in the new value, marker in old value
	 */
	const DIFF_MARKER = '***diff***';

	/**
	 * Config name for custom notification message
	 */
	const CUSTOM_NOTIFICATION = 'custom_notification';

	/**
	 * Constructor
	 *
	 * @param string $cf_app = null if set, custom field names get added to $field2history
	 */
	function __construct($cf_app = null, $notification_class=false)
	{
		if ($cf_app)
		{
			$linkable_cf_types = array('link-entry')+array_keys(Api\Link::app_list());
			foreach(Customfields::get($cf_app, true) as $cf_name => $cf_data)
			{
				$this->field2history['#'.$cf_name] = '#'.$cf_name;

				if (in_array($cf_data['type'],$linkable_cf_types))
				{
					$this->cf_link_fields['#'.$cf_name] = $cf_data['type'] == 'link-entry' ? '' : $cf_data['type'];
				}
			}
		}
		if($notification_class)
		{
			$this->notification_class = $notification_class;
		}
	}

	/**
	 * Get the details of an entry
	 *
	 * You can/should call $this->get_customfields() to add custom fields.
	 *
	 * @param array|object $data
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_details($data,$receiver=null)
	{
		unset($data, $receiver);	// not uses as just a stub

		return array();
	}

	/**
	 * Get custom fields of an entry of an entry
	 *
	 * @param array|object $data
	 * @param string $only_type2 = null if given only return fields of type2 == $only_type2
	 * @param int $user = false Use this user for custom field permissions, or false
	 *	to strip all private custom fields
	 *
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_customfields($data, $only_type2=null, $user = false)
	{
		$details = array();
		if(!is_numeric($user))
		{
			$user = false;
		}

		if (($cfs = Customfields::get($this->app, $user, $only_type2)))
		{
			$header_done = false;
			foreach($cfs as $name => $field)
			{
				if (in_array($field['type'], Customfields::$non_printable_fields)) continue;

				// Sometimes cached customfields let private fields the user can access
				// leak through.  If no specific user provided, make sure we don't expose them.
				if ($user === false && $field['private']) continue;

				if (!$header_done)
				{
					$details['custom'] = array(
						'value' => lang('Custom fields').':',
						'type'  => 'reply',
					);
					$header_done = true;
				}
				//error_log(__METHOD__."() $name: data['#$name']=".array2string($data['#'.$name]).", field[values]=".array2string($field['values']));
				$details['#'.$name] = array(
					'label' => $field['label'],
					'value' => Customfields::format($field, $data['#'.$name] ?? null),
				);
				//error_log("--> details['#$name']=".array2string($details['#'.$name]));
			}
		}
		return $details;
	}

	/**
	 * Get a config value, which can depend on $data and $old
	 *
	 * Need to be implemented in your extended tracking class!
	 *
	 * @param string $name possible values are:
	 *  - 'assigned' array of users to use instead of a field in the data
	 * 	- 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'subject' string subject line for the notification of $data,$old, defaults to link-title
	 *  - 'link' string of link to view $data
	 *  - 'sender' sender of email
	 *  - 'reply_to' reply to of email
	 *  - 'skip_notify' array of email addresses that should _not_ be notified
	 *  - CUSTOM_NOTIFICATION string notification body message.  Merge print placeholders are allowed.
	 * @param array $data current entry
	 * @param array $old = null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	protected function get_config($name,$data,$old=null)
	{
		unset($name, $data, $old);	// not used as just a stub

		return null;
	}

	/**
	 * Tracks the changes in one entry $data, by comparing it with the last version in $old
	 *
	 * @param array $data current entry
	 * @param array $old = null old/last state of the entry or null for a new entry
	 * @param int $user = null user who made the changes, default to current user
	 * @param boolean $deleted = null can be set to true to let the tracking know the item got deleted or undeleted
	 * @param array $changed_fields = null changed fields from ealier call to $this->changed_fields($data,$old), to not compute it again
	 * @param boolean $skip_notification = false do NOT send any notification
	 * @return int|boolean false on error, integer number of changes logged or true for new entries ($old == null)
	 */
	public function track(array $data,array $old=null,$user=null,$deleted=null,array $changed_fields=null,$skip_notification=false)
	{
		$this->user = !is_null($user) ? $user : $GLOBALS['egw_info']['user']['account_id'];

		$changes = true;
		//error_log(__METHOD__.__LINE__);
		if ($old && $this->field2history)
		{
			//error_log(__METHOD__.__LINE__.' Changedfields:'.print_r($changed_fields,true));
			$changes = $this->save_history($data,$old,$deleted,$changed_fields);
			//error_log(__METHOD__.__LINE__.' Changedfields:'.print_r($changed_fields,true));
			//error_log(__METHOD__.__LINE__.' Changes:'.print_r($changes,true));
		}

		//error_log(__METHOD__.__LINE__.' LinkFields:'.array2string($this->cf_link_fields));
		if ($changes && $this->cf_link_fields)
		{
			$this->update_links($data,(array)$old);
		}
		// do not run do_notifications if we have no changes
		if ($changes && !$skip_notification && !$this->do_notifications($data,$old,$deleted))
		{
			$changes = false;
		}
		return $changes;
	}

	/**
	 * Store a link for each custom field linking to an other application and update them
	 *
	 * @param array $data
	 * @param array $old
	 */
	protected function update_links(array $data, array $old)
	{
		//error_log(__METHOD__.__LINE__.array2string($data).function_backtrace());
		//error_log(__METHOD__.__LINE__.array2string($this->cf_link_fields));
		foreach(array_keys((array)$this->cf_link_fields) as $name)
		{
			//error_log(__METHOD__.__LINE__.' Field:'.$name. ' Value (new):'.array2string($data[$name]));
			//error_log(__METHOD__.__LINE__.' Field:'.$name. ' Value (old):'.array2string($old[$name]));
			if (is_array($data[$name]) && array_key_exists('id',$data[$name])) $data[$name] = $data[$name]['id'];
			if (is_array($old[$name]) && array_key_exists('id',$old[$name])) $old[$name] = $old[$name]['id'];
			//error_log(__METHOD__.__LINE__.'(After processing) Field:'.$name. ' Value (new):'.array2string($data[$name]));
			//error_log(__METHOD__.__LINE__.'(After processing) Field:'.$name. ' Value (old):'.array2string($old[$name]));
		}
		$current_ids = array_unique(array_diff(array_intersect_key($data,$this->cf_link_fields),array('',0,NULL)));
		$old_ids = $old ? array_unique(array_diff(array_intersect_key($old,$this->cf_link_fields),array('',0,NULL))) : array();
		//error_log(__METHOD__.__LINE__.array2string($current_ids));
		//error_log(__METHOD__.__LINE__.array2string($old_ids));
		// create links for added application entry
		foreach(array_diff($current_ids,$old_ids) as $name => $id)
		{
			if (!($app = $this->cf_link_fields[$name]))
			{
				list($app,$id) = explode(':',$id);
				if (!$id) continue;	// can be eg. 'addressbook:', if no contact selected
			}
			$source_id = $data[$this->id_field];
			//error_log(__METHOD__.__LINE__.array2string($source_id));
			if ($source_id) Api\Link::link($this->app,$source_id,$app,$id);
			//error_log(__METHOD__.__LINE__."Api\Link::link('$this->app',".array2string($source_id).",'$app',$id);");
			//echo "<p>Api\Link::link('$this->app',{$data[$this->id_field]},'$app',$id);</p>\n";
		}

		// unlink removed application entries
		foreach(array_diff($old_ids,$current_ids) as $name => $id)
		{
			if (!isset($data[$name])) continue;	// ignore not set link cf's, eg. from sync clients
			if (!($app = $this->cf_link_fields[$name]))
			{
				list($app,$id) = explode(':',$id);
				if (!$id) continue;
			}
			$source_id = $data[$this->id_field];
			if ($source_id) Api\Link::unlink(null,$this->app,$source_id,0,$app,$id);
			//echo "<p>Api\Link::unlink(NULL,'$this->app',{$data[$this->id_field]},0,'$app',$id);</p>\n";
		}
	}

	/**
	 * Save changes to the history log
	 *
	 * @internal use only track($data,$old)
	 * @param array $data current entry
	 * @param array $old = null old/last state of the entry or null for a new entry
	 * @param boolean $deleted = null can be set to true to let the tracking know the item got deleted or undelted
	 * @param array $changed_fields = null changed fields from ealier call to $this->changed_fields($data,$old), to not compute it again
	 * @return int number of log-entries made
	 */
	protected function save_history(array $data,array $old=null,$deleted=null,array $changed_fields=null)
	{
		unset($deleted);	// not used, but required by function signature

		//error_log(__METHOD__.__LINE__.' Changedfields:'.array2string($changed_fields));
		if (is_null($changed_fields))
		{
			$changed_fields = self::changed_fields($data,$old);
			//error_log(__METHOD__.__LINE__.' Changedfields:'.array2string($changed_fields));
		}
		if (!$changed_fields && ($old || !$GLOBALS['egw_info']['server']['log_user_agent_action'])) return 0;

		if (!is_object($this->historylog) || $this->historylog->user != $this->user)
		{
			$this->historylog = new History($this->app, $this->user);
		}
		// log user-agent and session-action
		if (!empty($GLOBALS['egw_info']['server']['log_user_agent_action']) && ($changed_fields || !$old))
		{
			$this->historylog->add('user_agent_action', $data[$this->id_field],
				$_SERVER['HTTP_USER_AGENT'], $_SESSION[Api\Session::EGW_SESSION_VAR]['session_action']);
		}
		foreach($changed_fields as $name)
		{
			$status = isset($this->field2history[$name]) ? $this->field2history[$name] : $name;
			//error_log(__METHOD__.__LINE__." Name $name,".' Status:'.array2string($status));
			if (is_array($status))	// 1:N relation --> remove common rows
			{
				//error_log(__METHOD__.__LINE__.' is Array');
				self::compact_1_N_relation($data[$name],$status);
				self::compact_1_N_relation($old[$name],$status);
				$added = array_values(array_diff($data[$name],$old[$name]));
				$removed = array_values(array_diff($old[$name],$data[$name]));
				$n = max(array(count($added),count($removed)));
				for($i = 0; $i < $n; ++$i)
				{
					//error_log(__METHOD__."() $i: historylog->add('$name',data['$this->id_field']={$data[$this->id_field]},".array2string($added[$i]).','.array2string($removed[$i]));
					$this->historylog->add($name,$data[$this->id_field],$added[$i],$removed[$i]);
				}
			}
			else if (is_string($data[$name]) && is_string($old[$name]) && (
					$this->historylog->needs_diff ($name, $data[$name]) || $this->historylog->needs_diff ($name, $old[$name])))
			{
				// Multiline string, just store diff
				// Strip HTML first though
				$old_text = Api\Mail\Html::convertHTMLToText($old[$name]);
				$new_text = Api\Mail\Html::convertHTMLToText($data[$name]);

				// If only change was something in HTML, show the HTML
				if(trim($old_text) === trim($new_text))
				{
					$old_text = $old[$name];
					$new_text = $data[$name];
				}

				$diff = new \Horde_Text_Diff('auto', array(explode("\n",$old_text), explode("\n",$new_text)));
				$renderer = new \Horde_Text_Diff_Renderer_Unified();
				$this->historylog->add(
					$status,
					$data[$this->id_field],
					$renderer->render($diff),
					self::DIFF_MARKER
				);
			}
			else
			{
				//error_log(__METHOD__.__LINE__.' IDField:'.array2string($this->id_field).' ->'.$data[$this->id_field].' New:'.$data[$name].' Old:'.$old[$name]);
				$this->historylog->add($status,$data[$this->id_field],
					is_array($data[$name]) ? implode(',',$data[$name]) : $data[$name],
					is_array($old[$name]) ? implode(',',$old[$name]) : $old[$name]);
			}
		}
		//error_log(__METHOD__.__LINE__.' return:'.count($changed_fields));
		return count($changed_fields);
	}

	/**
	 * Compute changes between new and old data
	 *
	 * Can be used to check if saving the data is really necessary or user just pressed save
	 *
	 * @param array $data
	 * @param array $old = null
	 * @return array of keys with different values in $data and $old
	 */
	public function changed_fields(array $data,array $old=null)
	{
		if (is_null($old)) return array_keys($data);
		$changed_fields = array();
		foreach($this->field2history as $name => $status)
		{
			if (empty($old[$name]) && empty($data[$name])) continue;	// treat all sorts of empty equally

			if ($name[0] == '#' && !isset($data[$name])) continue;	// no set customfields are not stored, therefore not changed

			if (is_array($status))	// 1:N relation
			{
				self::compact_1_N_relation($data[$name],$status);
				self::compact_1_N_relation($old[$name],$status);
			}
			if ($old[$name] != $data[$name])
			{
				// normalize arrays, we do NOT care for the order of multiselections
				if (is_array($data[$name]) || is_array($old[$name]))
				{
					if (!is_array($data[$name])) $data[$name] = explode(',',$data[$name]);
					if (!is_array($old[$name])) $old[$name] = explode(',',$old[$name]);
					if (count($data[$name]) == count($old[$name]))
					{
						sort($data[$name]);
						sort($old[$name]);
						if ($data[$name] == $old[$name]) continue;
					}
				}
				elseif ($old[$name] == $data[$name] ||
					is_a($data[$name], \DateTime::class) ? (new Api\DateTime($old[$name], $data[$name]->getTimezone())) == $data[$name] :
						str_replace("\r", '', $old[$name]) == str_replace("\r", '', $data[$name]))
				{
					continue;	// change only in CR (eg. different OS) --> ignore
				}
				$changed_fields[] = $name;
				//echo "<p>$name: ".array2string($data[$name]).' != '.array2string($old[$name])."</p>\n";
			}
		}
		foreach($data as $name => $value)
		{
			if (isset($name[0]) && $name[0] == '#' && $name[1] == '#' && $value !== $old[$name])
			{
				$changed_fields[] = $name;
			}
		}
		//error_log(__METHOD__."() changed_fields=".array2string($changed_fields));
		return $changed_fields;
	}

	/**
	 * Compact (spezified) fields of a 1:N relation into an array of strings
	 *
	 * @param array &$rows rows of the 1:N relation
	 * @param array $cols field names as values
	 */
	private static function compact_1_N_relation(&$rows,array $cols)
	{
		if (is_array($rows))
		{
			foreach($rows as &$row)
			{
				$values = array();
				foreach($cols as $col)
				{
					$values[] = $row[$col];
				}
				$row = implode(self::ONE2N_SEPERATOR,$values);
			}
		}
		else
		{
			$rows = array();
		}
	}

	/**
	 * sending all notifications for the changed entry
	 *
	 * @internal use only track($data,$old,$user)
	 * @param array $data current entry
	 * @param array $old = null old/last state of the entry or null for a new entry
	 * @param boolean $deleted = null can be set to true to let the tracking know the item got deleted or undelted
	 * @param array $email_notified=null if present will return the emails notified, if given emails in that list will not be notified
	 * @return boolean true on success, false on error (error messages are in $this->errors)
	 */
	public function do_notifications($data,$old,$deleted=null,&$email_notified=null)
	{
		$this->errors = $email_sent = array();
		if (!empty($email_notified) && is_array($email_notified)) $email_sent = $email_notified;

		if (!$this->notify_current_user && $this->user)		// do we have a current user and should we notify the current user about his own changes
		{
			//error_log("do_notificaton() adding user=$this->user to email_sent, to not notify him");
			$email_sent[] = $GLOBALS['egw']->accounts->id2name($this->user,'account_email');
		}
		$skip_notify = $this->get_config('skip_notify',$data,$old);
		if($skip_notify && is_array($skip_notify))
		{
			$email_sent = array_merge($email_sent, $skip_notify);
		}

		// entry creator
		if ($this->creator_field && ($email = $GLOBALS['egw']->accounts->id2name($data[$this->creator_field],'account_email')) &&
			!in_array($email, $email_sent))
		{
			if ($this->send_notification($data,$old,$email,$data[$this->creator_field],'notify_creator'))
			{
				$email_sent[] = $email;
			}
		}

		// members of group when entry owned by group
		if ($this->creator_field && $GLOBALS['egw']->accounts->get_type($data[$this->creator_field]) == 'g')
		{
			foreach($GLOBALS['egw']->accounts->members($data[$this->creator_field],true) as $u)
			{
				if (($email = $GLOBALS['egw']->accounts->id2name($u,'account_email')) &&
					!in_array($email, $email_sent))
				{
					if ($this->send_notification($data,$old,$email,$u,'notify_owner_group_member'))
					{
						$email_sent[] = $email;
					}
				}
			}
		}

		// assigned / responsible users
		if ($this->assigned_field || $assigned = $this->get_config('assigned', $data))
		{
			//error_log(__METHOD__."() data[$this->assigned_field]=".print_r($data[$this->assigned_field],true).", old[$this->assigned_field]=".print_r($old[$this->assigned_field],true));
			$old_assignees = array();
			$assignees = $assigned ?? array();
			if (!empty($data[$this->assigned_field]))	// current assignments
			{
				$assignees = is_array($data[$this->assigned_field]) ?
					$data[$this->assigned_field] : explode(',',$data[$this->assigned_field]);
			}
			if ($old && !empty($old[$this->assigned_field]))
			{
				$old_assignees = is_array($old[$this->assigned_field]) ?
					$old[$this->assigned_field] : explode(',',$old[$this->assigned_field]);
			}
			foreach(array_unique(array_merge($assignees,$old_assignees)) as $assignee)
			{
				//error_log(__METHOD__."() assignee=$assignee, type=".$GLOBALS['egw']->accounts->get_type($assignee).", email=".$GLOBALS['egw']->accounts->id2name($assignee,'account_email'));
				if (!$assignee) continue;

				// item assignee is a user
				if ($GLOBALS['egw']->accounts->get_type($assignee) == 'u')
				{
					if (($email = $GLOBALS['egw']->accounts->id2name($assignee,'account_email')) && !in_array($email, $email_sent))
					{
						if ($this->send_notification($data,$old,$email,$assignee,'notify_assigned',
							in_array($assignee,$assignees) !== in_array($assignee,$old_assignees) || $deleted))	// assignment changed
						{
							$email_sent[] = $email;
						}
					}
				}
				else	// item assignee is a group
				{
					foreach($GLOBALS['egw']->accounts->members($assignee,true) as $u)
					{
						if (($email = $GLOBALS['egw']->accounts->id2name($u,'account_email')) && !in_array($email, $email_sent))
						{
							if ($this->send_notification($data,$old,$email,$u,'notify_assigned',
								in_array($u,$assignees) !== in_array($u,$old_assignees) || $deleted))	// assignment changed
							{
								$email_sent[] = $email;
							}
						}
					}
				}
			}
		}

		// notification copies
		if (($copies = $this->get_config('copy',$data,$old)))
		{
			$lang = $this->get_config('lang',$data,$old);
			foreach($copies as $email)
			{
				if (strchr($email,'@') !== false && !in_array($email, $email_sent))
				{
					if ($this->send_notification($data,$old,$email,$lang,'notify_copy'))
					{
						$email_sent[] = $email;
					}
				}
			}
		}
		$email_notified = $email_sent;
		return !count($this->errors);
	}

	/**
	 * Cache for notificaton body
	 *
	 * Cache is by id, language, date-format and type text/html
	 */
	protected $body_cache = array();

	/**
	 * method to clear the Cache for notificaton body
	 *
	 * Cache is by id, language, date-format and type text/html
	 */
	public function ClearBodyCache()
	{
		$this->body_cache = array();
	}

	/**
	 * Sending a notification to the given email-address
	 *
	 * Called by track() or externally for sending async notifications
	 *
	 * Method changes $GLOBALS['egw_info']['user'], so everything called by it, eg. get_(subject|body|links|attachements),
	 * must NOT store something from user enviroment! By the end of the method, everything get changed back.
	 *
	 * @param array $data current entry
	 * @param array $old = null old/last state of the entry or null for a new entry
	 * @param string $email address to send the notification to
	 * @param string $user_or_lang = 'en' user-id or 2 char lang-code for a non-system user
	 * @param string $check = null pref. to check if a notification is wanted
	 * @param boolean $assignment_changed = true the assignment of the user $user_or_lang changed
	 * @param boolean $deleted = null can be set to true to let the tracking know the item got deleted or undelted
	 * @return boolean true on success or false if notification not requested or error (error-message is in $this->errors)
	 */
	public function send_notification($data,$old,$email,$user_or_lang,$check=null,$assignment_changed=true,$deleted=null)
	{
		//error_log(__METHOD__."(,,'$email',$user_or_lang,$check,$assignment_changed,$deleted)");
		if (!$email) return false;

		$save_user = $GLOBALS['egw_info']['user'];
		$do_notify = true;
		$can_cache = false;

		if (is_numeric($user_or_lang))	// user --> read everything from his prefs
		{
			$GLOBALS['egw_info']['user']['account_id'] = $user_or_lang;
			$GLOBALS['egw']->preferences->__construct($user_or_lang);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);	// no session prefs!

			if ($check && $this->check2pref) $check = $this->check2pref[$check];

			if ($check && !$GLOBALS['egw_info']['user']['preferences'][$this->app][$check] ||	// no notification requested
				// only notification about changed assignment requested
				$check && $GLOBALS['egw_info']['user']['preferences'][$this->app][$check] === 'assignment' && !$assignment_changed ||
				$this->user == $user_or_lang && !$this->notify_current_user)  // no popup for own actions
			{
				$do_notify = false;	// no notification requested / necessary
			}
			$can_cache = (Customfields::get($this->app, true) == Customfields::get($this->app, $user_or_lang));
		}
		else
		{
			// for the notification copy, we use default (and forced) prefs plus the language from the the tracker config
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->default_prefs();
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $user_or_lang;
		}
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] != Api\Translation::$userlang)	// load the right language if needed
		{
			Api\Translation::init();
		}

		$receiver = is_numeric($user_or_lang) ? $user_or_lang : $email;

		if ($do_notify)
		{
			// Load date/time preferences into egw_time
			Api\DateTime::init();

			// Cache message body to not have to re-generate it every time
			$lang = Api\Translation::$userlang;
			$date_format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] .
				$GLOBALS['egw_info']['user']['preferences']['common']['timeformat'].
				$GLOBALS['egw_info']['user']['preferences']['common']['tz'];

			// Cache text body, if there's no private custom fields we might reveal
			if($can_cache)
			{
				$body_cache =& $this->body_cache[$data[$this->id_field]][$lang][$date_format];
			}
			if(empty($data[$this->id_field]) || !isset($body_cache['text']))
			{
				$body_cache['text'] = $this->get_body(false,$data,$old,false,$receiver);
			}
			// Cache HTML body
			if(empty($data[$this->id_field]) || !isset($body_cache['html']))
			{
				$body_cache['html'] = $this->get_body(true,$data,$old,false,$receiver);
			}

			// get rest of notification message
			$sender = $this->get_sender($data,$old,true,$receiver);
			$reply_to = $this->get_reply_to($data,$old);
			$subject = $this->get_subject($data,$old,$deleted,$receiver);
			$link = $this->get_notification_link($data,$old,$receiver);
			$attachments = $this->get_attachments($data,$old,$receiver);
		}

		// restore user enviroment BEFORE calling notification class or returning
		$GLOBALS['egw_info']['user'] = $save_user;
		// need to call preferences constructor and read_repository, to set user timezone again
		$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_id']);
		$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository(false);	// no session prefs!

		// Re-load date/time preferences
		Api\DateTime::init();

		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] != Api\Translation::$userlang)
		{
			Api\Translation::init();
		}

		if (!$do_notify)
		{
			return false;
		}

		// send over notification_app
		if ($GLOBALS['egw_info']['apps']['notifications']['enabled'])
		{
			// send via notification_app
			try {
				$class = $this->notification_class;
				$notification = new $class();
				$notification->set_receivers(array($receiver));
				$notification->set_message($body_cache['text'], 'plain');
				// add our own signature to distinguish between original message and reply
				// part. (e.g.: in OL there's no reply quote)
				$body_cache['html'] = "<span style='display:none;'>-----".lang('original message')."-----</span>"."\r\n".$body_cache['html'];
				$notification->set_message($body_cache['html'], 'html');
				$notification->set_sender($sender);
				$notification->set_reply_to($reply_to);
				$notification->set_subject($subject);
				$notification->set_links(array($link));
				$notification->set_popupdata($link?$link['app']:null, $link);
				if ($attachments && is_array($attachments))
				{
					$notification->set_attachments($attachments);
				}
				// run immediatly during async service, as sending mail with Horde fails, if PHP is already in shutdown
				// (json requests take care of that by calling Egw::__desctruct() explicit before it's regular triggered)
				$run = isset($GLOBALS['egw_info']['flags']['async-service']) ? 'call_user_func_array' : Api\Egw::class.'::on_shutdown';
				$run(static function($notification, $sender, $receiver, $subject)
				{
					$notification->send();

					// Notification can (partially) succeed and still generate errors
					foreach($notification->errors(true) as $error)
					{
						error_log(__METHOD__."() Error notifying $receiver from $sender: $subject: $error");
						// send notification errors via push to current user (not session, as alarms send via async job have none!)
						(new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']))->message(
							lang('Error notifying %1', !is_numeric($receiver) ? $receiver :
								Api\Accounts::id2name($receiver, 'account_fullname').' <'.Api\Accounts::id2name($receiver, 'account_email').'>').
							"\n".$subject."\n".$error, 'error');

					}
				}, [$notification, $sender, $receiver, $subject]);
			}
			catch (Exception $exception)
			{
				$this->errors[] = $exception->getMessage();
				return false;
			}
		}
		else
		{
			error_log('tracking: cannot send any notifications because notifications is not installed');
		}

		return true;
	}

	/**
	 * Return date+time formatted for the currently notified user (prefs in $GLOBALS['egw_info']['user']['preferences'])
	 *
	 * @param int|string|DateTime $timestamp in server-time
	 * @param boolean $do_time =true true=allways (default), false=never print the time, null=print time if != 00:00
	 *
	 * @return string
	 */
	public function datetime($timestamp,$do_time=true)
	{
		if (!is_a($timestamp,'DateTime'))
		{
			$timestamp = new Api\DateTime($timestamp,Api\DateTime::$server_timezone);
		}
		$timestamp->setTimezone(Api\DateTime::$user_timezone);
		if (is_null($do_time))
		{
			$do_time = ($timestamp->format('Hi') != '0000');
		}
		$format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
		if ($do_time) $format .= ' '.($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] != 12 ? 'H:i' : 'h:i a');

		return $timestamp->format($format);
	}

	/**
	 * Get sender address
	 *
	 * The default implementation prefers depending on the prefer_user_as_sender class-var the user over
	 * what is returned by get_config('sender').
	 *
	 * @param int $user account_lid of user
	 * @param array $data
	 * @param array $old
	 * @param bool $prefer_id returns the userid rather than email
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string or userid
	 */
	protected function get_sender($data,$old,$prefer_id=false,$receiver=null)
	{
		unset($receiver);	// not used, but required by function signature

		$sender = $this->get_config('sender',$data,$old);
		//echo "<p>".__METHOD__."() get_config('sender',...)='".htmlspecialchars($sender)."'</p>\n";

		if (($this->prefer_user_as_sender || !$sender) && $this->user &&
			($email = $GLOBALS['egw']->accounts->id2name($this->user,'account_email')))
		{
			$name = $GLOBALS['egw']->accounts->id2name($this->user,'account_fullname');

			if($prefer_id) {
				$sender = $this->user;
			} else {
				$sender = $name ? $name.' <'.$email.'>' : $email;
			}
		}
		elseif (!$sender)
		{
			$sender = 'EGroupware '.lang($this->app).' <noreply@'.($GLOBALS['egw_info']['server']['mail_suffix']??'nodomain.org').'>';
		}
		//echo "<p>".__METHOD__."()='".htmlspecialchars($sender)."'</p>\n";
		return $sender;
	}

	/**
	 * Get reply to address
	 *
	 * The default implementation prefers depending on what is returned by get_config('reply_to').
	 *
	 * @param int $user account_lid of user
	 * @param array $data
	 * @param array $old
	 * @return string or null
	 */
	protected function get_reply_to($data,$old)
	{
		$reply_to = $this->get_config('reply_to',$data,$old);

		return $reply_to;
	}

	/**
	 * Get the title for a given entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @return string
	 */
	protected function get_title($data,$old)
	{
		unset($old);	// not used, but required by function signature

		return Api\Link::title($this->app,$data[$this->id_field]);
	}

	/**
	 * Get the subject for a given entry, can be reimplemented
	 *
	 * Default implementation uses the link-title
	 *
	 * @param array $data
	 * @param array $old
	 * @param boolean $deleted =null can be set to true to let the tracking know the item got deleted or undelted
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	protected function get_subject($data,$old,$deleted=null,$receiver=null)
	{
		unset($old, $deleted, $receiver);	// not used, but required by function signature

		return Api\Link::title($this->app,$data[$this->id_field]);
	}

	/**
	 * Get the modified / new message (1. line of mail body) for a given entry, can be reimplemented
	 *
	 * Default implementation does nothing
	 *
	 * @param array $data
	 * @param array $old
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	protected function get_message($data,$old,$receiver=null)
	{
		unset($data, $old, $receiver);	// not used, but required by function signature

		return '';
	}

	/**
	 * Get a link to view the entry, can be reimplemented
	 *
	 * Default implementation checks get_config('link') (appending the id) or link::view($this->app,$id)
	 *
	 * @param array $data
	 * @param array $old
	 * @param string $allow_popup = false if true return array(link,popup-size) incl. session info an evtl. partial url (no host-part)
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string|array string with link (!$allow_popup) or array(link,popup-size), popup size is something like '640x480'
	 */
	protected function get_link($data,$old,$allow_popup=false,$receiver=null)
	{
		unset($receiver);	// not used, but required by function signature

		if (($link = $this->get_config('link',$data,$old)))
		{
			if (!$this->get_config('link_no_id', $data) && strpos($link,$this->id_field.'=') === false && isset($data[$this->id_field]))
			{
				$link .= strpos($link,'?') === false ? '?' : '&';
				$link .= $this->id_field.'='.$data[$this->id_field];
			}
		}
		else
		{
			if (($view = Api\Link::view($this->app,$data[$this->id_field])))
			{
				$link = Api\Framework::link('/index.php',$view);
				$popup = Api\Link::is_popup($this->app,'view');
			}
		}
		if ($link[0] == '/') Api\Framework::getUrl($link);

		if (!$allow_popup)
		{
			// remove the session-id in the notification mail!
			$link = preg_replace('/(sessionid|kp3|domain)=[^&]+&?/','',$link);

			if (!empty($popup)) $link .= '&nopopup=1';
		}
		//error_log(__METHOD__."(..., $allow_popup, $receiver) returning ".array2string($allow_popup ? array($link,$popup) : $link));
		return $allow_popup ? array($link,$popup) : $link;
	}

	/**
	 * Get a link for notifications to view the entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array with link
	 */
	protected function get_notification_link($data,$old,$receiver=null)
	{
		unset($receiver);	// not used, but required by function signature

		if (($view = Api\Link::view($this->app,$data[$this->id_field])))
		{
			return array(
				'text' 	=> $this->get_title($data,$old),
				'app'	=> $this->app,
				'id'	=> $data[$this->id_field],
				'view' 	=> $view,
				'popup'	=> Api\Link::is_popup($this->app,'view'),
			);
		}
		return false;
	}

	/**
	 * Get the body of the notification message, can be reimplemented
	 *
	 * @param boolean $html_email
	 * @param array $data
	 * @param array $old
	 * @param boolean $integrate_link to have links embedded inside the body
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	public function get_body($html_email,$data,$old,$integrate_link = true,$receiver=null)
	{
		$body = '';
		if($this->get_config(self::CUSTOM_NOTIFICATION, $data, $old))
		{
			$body = $this->get_custom_message($data,$old,null,$receiver);
			if(($sig = $this->get_signature($data,$old,$receiver)))
			{
				$body .= ($html_email ? '<br />':'') . "\n$sig";
			}
			return $body;
		}
		if ($html_email)
		{
			$body = '<table cellspacing="2" cellpadding="0" border="0" width="100%">'."\n";
		}
		// new or modified message
		if (($message = $this->get_message($data,$old,$receiver)))
		{
			foreach ((array)$message as $_message)
			{
				$body .= $this->format_line($html_email,'message',false,($_message=='---'?($html_email?'<hr/>':$_message):$_message));
			}
		}
		if ($integrate_link && ($link = $this->get_link($data,$old,false,$receiver)))
		{
			$body .= $this->format_line($html_email,'link',false,$integrate_link === true ? lang('You can respond by visiting:') : $integrate_link,$link);
		}
		foreach($this->get_details($data,$receiver) as $name => $detail)
		{
			// if there's no old entry, the entry is not modified by definition
			// if both values are '', 0 or null, we count them as equal too
			$modified = $old && ($data[$name] ?? null) != ($old[$name] ?? null) && !(empty($data[$name]) && empty($old[$name]));
			//if ($modified) error_log("data[$name]=".print_r($data[$name],true).", old[$name]=".print_r($old[$name],true)." --> modified=".(int)$modified);
			if (empty($detail['value']) && !$modified) continue;	// skip unchanged, empty values

			$body .= $this->format_line($html_email, $detail['type'] ?? null, $modified,
				$detail['label'] ?? '', $detail['value']);
		}
		if ($html_email)
		{
			$body .= "</table>\n";
		}
		if (($sig = $this->get_signature($data,$old,$receiver)))
		{
			$body .= ($html_email ? '<br />':'') . "\n$sig";
		}
		if (!$html_email && isset($data['tr_edit_mode']) && $data['tr_edit_mode'] === 'html')
		{
			$body = Api\Mail\Html::convertHTMLToText($body);
		}
		return $body;
	}

	/**
	 * Format one line to the mail body
	 *
	 * @internal
	 * @param boolean $html_mail
	 * @param string $type 'link', 'message', 'summary', 'multiline', 'reply' and ''=regular content
	 * @param boolean $modified mark field as modified
	 * @param string $line whole line or just label
	 * @param string $data = null data or null to display just $line over 2 columns
	 * @return string
	 */
	protected function format_line($html_mail,$type,$modified,$line,$data=null)
	{
		//error_log(__METHOD__.'('.array2string($html_mail).",'$type',".array2string($modified).",'$line',".array2string($data).')');
		$content = '';

		if ($html_mail)
		{
			if (!$this->html_content_allow) $line = Api\Html::htmlspecialchars($line);	// XSS

			$color = $modified ? 'red' : false;
			$size  = '110%';
			$bold = false;
			$background = '#FFFFF1';
			switch($type)
			{
				case 'message':
					$background = '#D3DCE3;';
					$bold = true;
					break;
				case 'link':
					$background = '#F1F1F1';
					break;
				case 'summary':
					$background = '#F1F1F1';
					$bold = true;
					break;
				case 'multiline':
					// Only Convert nl2br on non-html content
					if (strpos($data, '<br') === false)
					{
						$data = nl2br($this->html_content_allow ? $data : Api\Html::htmlspecialchars($data));
						$this->html_content_allow = true;	// to NOT do htmlspecialchars again
					}
					break;
				case 'reply':
					$background = '#F1F1F1';
					break;
				default:
					$size = false;
			}
			$style = ($bold ? 'font-weight:bold;' : '').($size ? 'font-size:'.$size.';' : '').($color?'color:'.$color:'');

			$content = '<tr style="background-color: '.$background.';"><td style="'.$style.($line && $data?'" width="20%':'" colspan="2').'">';
		}
		else	// text-mail
		{
			if ($type == 'reply') $content = str_repeat('-',64)."\n";

			if ($modified) $content .= '> ';
		}
		$content .= $line;

		if ($html_mail)
		{
			if ($line && $data) $content .= '</td><td style="'.$style.'">';
			if ($type == 'link')
			{
				// the link is often too long for html boxes chunk-split allows to break lines if needed
				$content .= Api\Html::a_href(chunk_split(rawurldecode($data),40,'&#8203;'),$data,'','target="_blank"');
			}
			elseif ($this->html_content_allow)
			{
				$content .= Api\Html::activate_links($data);
			}
			else
			{
				$content .= Api\Html::htmlspecialchars($data);
			}
		}
		else
		{
			$content .= ($content&&$data?': ':'').$data;
		}
		if ($html_mail) $content .= '</td></tr>';

		$content .= "\n";

		return $content;
	}

	/**
	 * Get the attachments for a notification
	 *
	 * @param array $data
	 * @param array $old
	 * @param int|string $receiver nummeric account_id or email address
	 * @return array or array with values for either 'string' or 'path' and optionally (mime-)'type', 'filename' and 'encoding'
	 */
	protected function get_attachments($data,$old,$receiver=null)
	{
		unset($data, $old, $receiver);	// not used, but required by function signature

	 	return array();
	}

	/**
	 * Get a (global) signature to append to the change notificaiton
	 * @param array $data
	 * @param type $old
	 * @param type $receiver
	 */
	protected function get_signature($data, $old, $receiver)
	{
		unset($old, $receiver);	// not used, but required by function signature

		$config = Api\Config::read('notifications');
		if(!isset($data[$this->id_field]))
		{
			error_log($this->app . ' did not properly implement bo_tracking->id_field.  Merge skipped.');
		}
		elseif(class_exists($this->app . '_merge') && $config['signature'])
		{
			$merge_class = $this->app . '_merge';
			$merge = new $merge_class();
			$error = null;
			$sig = $merge->merge_string($config['signature']??null, array($data[$this->id_field]), $error, 'text/html');
			if($error)
			{
				error_log($error);
				return $config['signature'];
			}
			return $sig;
		}
		return $config['signature'];
	}

	/**
	 * Get a custom notification message to be used instead of the standard one.
	 * It can use merge print placeholders to include data.
	 */
	protected function get_custom_message($data, $old, $merge_class = null, $receiver = false)
	{
		$message = $this->get_config(self::CUSTOM_NOTIFICATION, $data, $old);
		if(!$message)
		{
			return '';
		}

		// Check if there's any custom field privacy issues, and try to remove them
		$message = $this->sanitize_custom_message($message, $receiver);

		// Automatically set merge class from naming conventions
		if($merge_class == null)
		{
			$merge_class = $this->app.'_merge';
		}
		if(!isset($data[$this->id_field]))
		{
			error_log($this->app . ' did not properly implement bo_tracking->id_field.  Merge skipped.');
			return $message;
		}
		elseif(class_exists($merge_class))
		{
			$merge = new $merge_class();
			$error = null;
			$merged_message = $merge->merge_string($message, array($data[$this->id_field]), $error, 'text/html');
			if($error)
			{
				error_log($error);
				return $message;
			}
			return $merged_message;
		}
		else
		{
			throw new Api\Exception\WrongParameter("Invalid merge class '$merge_class' for {$this->app} custom notification");
		}
	}

	/**
	 * Check to see if the message would expose any custom fields that are
	 * not visible to the receiver, and try to remove them from the message.
	 *
	 * @param string $message
	 * @param string|int $receiver Account ID or email address
	 */
	protected function sanitize_custom_message($message, $receiver)
	{
		if(!is_numeric($receiver))
		{
			$receiver = false;
		}

		$cfs = Customfields::get($this->app, $receiver);
		$all_cfs = Customfields::get($this->app, true);

		// If we have a specific user and they're the same then there are
		// no private fields so nothing needs to be done
		if($receiver && $all_cfs == $cfs)
		{
			return $message;
		}

		// Replace any placeholders that use the private field, or any sub-keys
		// of the field
		foreach($all_cfs as $name => $field)
		{
			if ($receiver === false && $field['private'] || !$cfs[$name])
			{
				// {{field}} or {{field/subfield}} or $$field$$ or $$field/subfield$$
				$message = preg_replace('/(\{\{|\$\$)#'.$name.'(\/[^\}\$]+)?(\}\}|\$\$)/', '', $message);
			}
		}
		return $message;
	}
}
