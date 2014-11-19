<?php
/**
 * EGroupware - abstract base class for tracking (history log, notifications, ...)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package etemplate
 * @subpackage api
 * @copyright (c) 2007-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
abstract class bo_tracking
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
	 * Array with error-messages if track($data,$old) returns false
	 *
	 * @var array
	 */
	var $errors = array();

	/**
	 * instance of the historylog object for the app we are tracking
	 *
	 * @access private
	 * @var historylog
	 */
	var $historylog;

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
	 * Config name for custom notification message
	 */
	const CUSTOM_NOTIFICATION = 'custom_notification';

	/**
	 * Constructor
	 *
	 * @param string $cf_app=null if set, custom field names get added to $field2history
	 * @return bo_tracking
	 */
	function __construct($cf_app = null)
	{
		if ($cf_app)
		{
			$linkable_cf_types = array('link-entry')+array_keys(egw_link::app_list());
			foreach(egw_customfields::get($cf_app, true) as $cf_name => $cf_data)
			{
				$this->field2history['#'.$cf_name] = '#'.$cf_name;

				if (in_array($cf_data['type'],$linkable_cf_types))
				{
					$this->cf_link_fields['#'.$cf_name] = $cf_data['type'] == 'link-entry' ? '' : $cf_data['type'];
				}
			}
		}
	}

	function bo_tracking()
	{
		self::__construct();
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
		return array();
	}

	/**
	 * Get custom fields of an entry of an entry
	 *
	 * @param array|object $data
	 * @param string $only_type2=null if given only return fields of type2 == $only_type2
	 * @return array of details as array with values for keys 'label','value','type'
	 */
	function get_customfields($data, $only_type2=null)
	{
		$details = array();

		if (($cfs = egw_customfields::get($this->app, $all_private_too=false, $only_type2)))
		{
			$header_done = false;
			foreach($cfs as $name => $field)
			{
				if (in_array($field['type'], egw_customfields::$non_printable_fields)) continue;

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
					'value' => egw_customfields::format($field, $data['#'.$name]),
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
	 * @param string $what possible values are:
	 *  - 'assigned' array of users to use instead of a field in the data
	 * 	- 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'subject' string subject line for the notification of $data,$old, defaults to link-title
	 *  - 'link' string of link to view $data
	 *  - 'sender' sender of email
	 *  - 'skip_notify' array of email addresses that should _not_ be notified
	 *  - CUSTOM_NOTIFICATION string notification body message.  Merge print placeholders are allowed.
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @return mixed
	 */
	protected function get_config($name,$data,$old=null)
	{
		return null;
	}

	/**
	 * Tracks the changes in one entry $data, by comparing it with the last version in $old
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
		foreach((array)$this->cf_link_fields as $name => $val)
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
			if ($source_id) egw_link::link($this->app,$source_id,$app,$id);
			//error_log(__METHOD__.__LINE__."egw_link::link('$this->app',".array2string($source_id).",'$app',$id);");
			//echo "<p>egw_link::link('$this->app',{$data[$this->id_field]},'$app',$id);</p>\n";
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
			if ($source_id) egw_link::unlink(null,$this->app,$source_id,0,$app,$id);
			//echo "<p>egw_link::unlink(NULL,'$this->app',{$data[$this->id_field]},0,'$app',$id);</p>\n";
		}
	}

	/**
	 * Save changes to the history log
	 *
	 * @internal use only track($data,$old)
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undelted
	 * @param array $changed_fields=null changed fields from ealier call to $this->changed_fields($data,$old), to not compute it again
	 * @return int number of log-entries made
	 */
	protected function save_history(array $data,array $old=null,$deleted=null,array $changed_fields=null)
	{
		//error_log(__METHOD__.__LINE__.' Changedfields:'.array2string($changed_fields));
		if (is_null($changed_fields))
		{
			$changed_fields = self::changed_fields($data,$old);
			//error_log(__METHOD__.__LINE__.' Changedfields:'.array2string($changed_fields));
		}
		if (!$changed_fields && ($old || !$GLOBALS['egw_info']['server']['log_user_agent_action'])) return 0;

		if (!is_object($this->historylog) || $this->historylog->user != $this->user)
		{
			$this->historylog = new historylog($this->app,$this->user);
		}
		// log user-agent and session-action
		if ($GLOBALS['egw_info']['server']['log_user_agent_action'] && ($changed_fields || !$old))
		{
			$this->historylog->add('user_agent_action', $data[$this->id_field],
				$_SERVER['HTTP_USER_AGENT'], $_SESSION[egw_session::EGW_SESSION_VAR]['session_action']);
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
	 * @param array $old=null
	 * @return array of keys with different values in $data and $old
	 */
	public function changed_fields(array $data,array $old=null)
	{
		if (is_null($old)) return array_keys($data);
		$changed_fields = array();
		foreach($this->field2history as $name => $status)
		{
			if (!$old[$name] && !$data[$name]) continue;	// treat all sorts of empty equally

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
				$changed_fields[] = $name;
				//echo "<p>$name: ".array2string($data[$name]).' != '.array2string($old[$name])."</p>\n";
			}
		}
		foreach($data as $name => $value)
		{
			if ($name[0] == '#' && $name[1] == '#' && $value !== $old[$name])
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
			foreach($rows as $key => &$row)
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
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undelted
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
			//error_log("bo_tracking::do_notifications() data[$this->assigned_field]=".print_r($data[$this->assigned_field],true).", old[$this->assigned_field]=".print_r($old[$this->assigned_field],true));
			$assignees = $old_assignees = array();
			$assignees = $assigned ? $assigned : $assignees;
			if ($data[$this->assigned_field])	// current assignments
			{
				$assignees = is_array($data[$this->assigned_field]) ?
					$data[$this->assigned_field] : explode(',',$data[$this->assigned_field]);
			}
			if ($old && $old[$this->assigned_field])
			{
				$old_assignees = is_array($old[$this->assigned_field]) ?
					$old[$this->assigned_field] : explode(',',$old[$this->assigned_field]);
			}
			foreach(array_unique(array_merge($assignees,$old_assignees)) as $assignee)
			{
				//error_log("bo_tracking::do_notifications() assignee=$assignee, type=".$GLOBALS['egw']->accounts->get_type($assignee).", email=".$GLOBALS['egw']->accounts->id2name($assignee,'account_email'));
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
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param string $email address to send the notification to
	 * @param string $user_or_lang='en' user-id or 2 char lang-code for a non-system user
	 * @param string $check=null pref. to check if a notification is wanted
	 * @param boolean $assignment_changed=true the assignment of the user $user_or_lang changed
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undelted
	 * @return boolean true on success or false if notification not requested or error (error-message is in $this->errors)
	 */
	public function send_notification($data,$old,$email,$user_or_lang,$check=null,$assignment_changed=true,$deleted=null)
	{
		//error_log(__METHOD__."(,,'$email',$user_or_lang,$check,$assignment_changed,$deleted)");
		if (!$email) return false;

		$save_user = $GLOBALS['egw_info']['user'];
		$do_notify = true;

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
		}
		else
		{
			// for the notification copy, we use default (and forced) prefs plus the language from the the tracker config
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->default_prefs();
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $user_or_lang;
		}
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] != translation::$userlang)	// load the right language if needed
		{
			translation::init();
		}

		if ($do_notify)
		{
			// Load date/time preferences into egw_time
			egw_time::init();

			// Cache message body to not have to re-generate it every time
			$lang = translation::$userlang;
			$date_format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] .
				$GLOBALS['egw_info']['user']['preferences']['common']['timeformat'];

			// Cache text body
			$body_cache =& $this->body_cache[$data[$this->id_field]][$lang][$date_format];
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
		egw_time::init();

		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] != translation::$userlang)
		{
			translation::init();
		}

		if (!$do_notify)
		{
			return false;
		}

		// send over notification_app
		if ($GLOBALS['egw_info']['apps']['notifications']['enabled'])
		{
			// send via notification_app
			$receiver = is_numeric($user_or_lang) ? $user_or_lang : $email;
			try {
				$notification = new notifications();
				$notification->set_receivers(array($receiver));
				$notification->set_message($body_cache['text']);
				$notification->set_message($body_cache['html']);
				$notification->set_sender($sender);
				$notification->set_subject($subject);
				$notification->set_links(array($link));
				if ($attachments && is_array($attachments))
				{
					$notification->set_attachments($attachments);
				}
				$notification->send();
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
	 * @param boolean $do_time=true true=allways (default), false=never print the time, null=print time if != 00:00
	 *
	 * @return string
	 */
	public function datetime($timestamp,$do_time=true)
	{
		if (!is_a($timestamp,'DateTime'))
		{
			$timestamp = new egw_time($timestamp,egw_time::$server_timezone);
		}
		$timestamp->setTimezone(egw_time::$user_timezone);
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
		$sender = $this->get_config('sender',$data,$old);
		//echo "<p>bo_tracking::get_sender() get_config('sender',...)='".htmlspecialchars($sender)."'</p>\n";

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
		elseif(!$sender)
		{
			$sender = 'eGroupWare '.lang($this->app).' <noreply@'.$GLOBALS['egw_info']['server']['mail_suffix'].'>';
		}
		//echo "<p>bo_tracking::get_sender()='".htmlspecialchars($sender)."'</p>\n";
		return $sender;
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
		return egw_link::title($this->app,$data[$this->id_field]);
	}

	/**
	 * Get the subject for a given entry, can be reimplemented
	 *
	 * Default implementation uses the link-title
	 *
	 * @param array $data
	 * @param array $old
	 * @param boolean $deleted=null can be set to true to let the tracking know the item got deleted or undelted
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string
	 */
	protected function get_subject($data,$old,$deleted=null,$receiver=null)
	{
		return egw_link::title($this->app,$data[$this->id_field]);
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
		return '';
	}

	/**
	 * Get a link to view the entry, can be reimplemented
	 *
	 * Default implementation checks get_config('link') (appending the id) or link::view($this->app,$id)
	 *
	 * @param array $data
	 * @param array $old
	 * @param string $allow_popup=false if true return array(link,popup-size) incl. session info an evtl. partial url (no host-part)
	 * @param int|string $receiver nummeric account_id or email address
	 * @return string|array string with link (!$allow_popup) or array(link,popup-size), popup size is something like '640x480'
	 */
	protected function get_link($data,$old,$allow_popup=false,$receiver=null)
	{
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
			if (($view = egw_link::view($this->app,$data[$this->id_field])))
			{
				$link = $GLOBALS['egw']->link('/index.php',$view);
				$popup = egw_link::is_popup($this->app,'view');
			}
		}
		if ($link[0] == '/')
		{
			$link = ($_SERVER['HTTPS'] || $GLOBALS['egw_info']['server']['enforce_ssl'] ? 'https://' : 'http://').
				($GLOBALS['egw_info']['server']['hostname'] ? $GLOBALS['egw_info']['server']['hostname'] : $_SERVER['HTTP_HOST']).$link;
		}
		if (!$allow_popup)
		{
			// remove the session-id in the notification mail!
			$link = preg_replace('/(sessionid|kp3|domain)=[^&]+&?/','',$link);

			if ($popup) $link .= '&nopopup=1';
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
		if (($view = egw_link::view($this->app,$data[$this->id_field])))
		{
			return array(
				'text' 	=> $this->get_title($data,$old),
				'view' 	=> $view,
				'popup'	=> egw_link::is_popup($this->app,'view'),
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
			$body = $this->get_custom_message($data,$old);
			if($sig = $this->get_signature($data,$old,$receiver))
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
			foreach ((array)$message as $k => $_message)
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
			$modified = $old && $data[$name] != $old[$name] && !(!$data[$name] && !$old[$name]);
			//if ($modified) error_log("data[$name]=".print_r($data[$name],true).", old[$name]=".print_r($old[$name],true)." --> modified=".(int)$modified);
			if (empty($detail['value']) && !$modified) continue;	// skip unchanged, empty values

			$body .= $this->format_line($html_email,$detail['type'],$modified,
				$detail['label'] ? $detail['label'] : '', $detail['value']);
		}
		if ($html_email)
		{
			$body .= "</table>\n";
		}
		if($sig = $this->get_signature($data,$old,$receiver))
		{
			$body .= ($html_email ? '<br />':'') . "\n$sig";
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
	 * @param string $data=null data or null to display just $line over 2 columns
	 * @return string
	 */
	protected function format_line($html_mail,$type,$modified,$line,$data=null)
	{
		//error_log(__METHOD__.'('.array2string($html_mail).",'$type',".array2string($modified).",'$line',".array2string($data).')');
		$content = '';

		if ($html_mail)
		{
			if (!$this->html_content_allow) $line = html::htmlspecialchars($line);	// XSS

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
						$data = nl2br($this->html_content_allow ? $data : html::htmlspecialchars($data));
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
				$content .= html::a_href(chunk_split(rawurldecode($data),40,'&#8203;'),$data,'','target="_blank"');
			}
			elseif ($this->html_content_allow)
			{
				$content .= html::activate_links($data);
			}
			else
			{
				$content .= html::htmlspecialchars($data);
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
	 	return array();
	}

	/**
	 * Get a (global) signature to append to the change notificaiton
	 */
	protected function get_signature($data, $old, $receiver)
	{
		$config = config::read('notifications');
		if(!isset($data[$this->id_field]))
		{
			error_log($this->app . ' did not properly implement bo_tracking->id_field.  Merge skipped.');
		}
		elseif(class_exists($this->app. '_merge'))
		{
			$merge_class = $this->app.'_merge';
			$merge = new $merge_class();
			$sig = $merge->merge_string($config['signature'], array($data[$this->id_field]), $error, 'text/html');
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
	protected function get_custom_message($data, $old, $merge_class = null)
	{
		$message = $this->get_config(self::CUSTOM_NOTIFICATION, $data, $old);
		if(!$message)
		{
			return '';
		}

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
			throw new egw_exception_wrong_parameter("Invalid merge class '$merge_class' for {$this->app} custom notification");
		}
	}
}
