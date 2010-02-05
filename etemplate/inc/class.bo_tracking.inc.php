<?php
/**
 * eGroupWare - abstract base class for tracking (history log, notifications, ...)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package etemplate
 * @subpackage api
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * Saved user preferences, if send_notifications need to set an other language
	 *
	 * @access private
	 * @var array
	 */
	var $save_prefs;
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
	 * Separator for 1:N relations
	 *
	 */
	const ONE2N_SEPERATOR = '~|~';

	/**
	 * Constructor
	 *
	 * @return bo_tracking
	 */
	function __construct()
	{

	}

	function bo_tracking()
	{
		self::__construct();
	}

	/**
	 * Get a config value, which can depend on $data and $old
	 *
	 * Need to be implemented in your extended tracking class!
	 *
	 * @param string $what possible values are:
	 * 	- 'copy' array of email addresses notifications should be copied too, can depend on $data
	 *  - 'lang' string lang code for copy mail
	 *  - 'subject' string subject line for the notification of $data,$old, defaults to link-title
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
	 * @return int|boolean false on error, integer number of changes logged or true for new entries ($old == null)
	 */
	public function track(array $data,array $old=null,$user=null,$deleted=null,array $changed_fields=null)
	{
		$this->user = !is_null($user) ? $user : $GLOBALS['egw_info']['user']['account_id'];

		$changes = true;

		if ($old && $this->field2history)
		{
			$changes = $this->save_history($data,$old,$deleted,$changed_fields);
		}
		// do not run do_notifications if we have no changes
		if ($changes && !$this->do_notifications($data,$old,$deleted))
		{
			$changes = false;
		}
		return $changes;
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
		if (is_null($changed_fields))
		{
			$changed_fields = self::changed_fields($data,$old);
		}
		if (!$changed_fields) return 0;

		if (!is_object($this->historylog) || $this->historylog->user != $this->user)
		{
			$this->historylog = new historylog($this->app,$this->user);
		}
		foreach($changed_fields as $name)
		{
			$status = $this->field2history[$name];
			if (is_array($status))	// 1:N relation --> remove common rows
			{
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
				$this->historylog->add($status,$data[$this->id_field],
					is_array($data[$name]) ? implode(',',$data[$name]) : $data[$name],
					is_array($old[$name]) ? implode(',',$old[$name]) : $old[$name]);
			}
		}
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
	 * @return boolean true on success, false on error (error messages are in $this->errors)
	 */
	public function do_notifications($data,$old,$deleted=null)
	{
		$this->errors = $email_sent = array();

		if (!$this->notify_current_user && $this->user)		// do we have a current user and should we notify the current user about his own changes
		{
			//error_log("do_notificaton() adding user=$this->user to email_sent, to not notify him");
			$email_sent[] = $GLOBALS['egw']->accounts->id2name($this->user,'account_email');
		}

		// entry creator
		if ($this->creator_field && ($email = $GLOBALS['egw']->accounts->id2name($data[$this->creator_field],'account_email')) &&
			!in_array($email, $email_sent))
		{
			$this->send_notification($data,$old,$email,$data[$this->creator_field],'notify_creator');
			$email_sent[] = $email;
		}

		// assigned / responsible users
		if ($this->assigned_field)
		{
			//error_log("bo_tracking::do_notifications() data[$this->assigned_field]=".print_r($data[$this->assigned_field],true).", old[$this->assigned_field]=".print_r($old[$this->assigned_field],true));
			$assignees = $old_assignees = array();
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
						$this->send_notification($data,$old,$email,$assignee,'notify_assigned',
							in_array($assignee,$assignees) !== in_array($assignee,$old_assignees) || $deleted);	// assignment changed
						$email_sent[] = $email;
					}
				}
				else	// item assignee is a group
				{
					foreach($GLOBALS['egw']->accounts->members($assignee,true) as $u)
					{
						if (($email = $GLOBALS['egw']->accounts->id2name($u,'account_email')) && !in_array($email, $email_sent))
						{
							$this->send_notification($data,$old,$email,$u,'notify_assigned',
								in_array($u,$assignees) !== in_array($u,$old_assignees) || $deleted);	// assignment changed
							$email_sent[] = $email;
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
					$this->send_notification($data,$old,$email,$lang,'notify_copy');
					$email_sent[] = $email;
				}
			}
		}

		// restore the user enviroment
		if ($this->save_prefs) $GLOBALS['egw_info']['user'] = $this->save_prefs; unset($this->save_prefs);
		if ($GLOBALS['egw_info']['user']['preferences']['common']['lang'] != translation::$userlang)
		{
			translation::init();
		}
		return !count($this->errors);
	}

	/**
	 * Sending a notification to the given email-address
	 *
	 * Called by track() or externally for sending async notifications
	 *
	 * @param array $data current entry
	 * @param array $old=null old/last state of the entry or null for a new entry
	 * @param string $email address to send the notification to
	 * @param string $user_or_lang='en' user-id or 2 char lang-code for a non-system user
	 * @param string $check=null pref. to check if a notification is wanted
	 * @param boolean $assignment_changed=true the assignment of the user $user_or_lang changed
	 * @return boolean true on success or false on error (error-message is in $this->errors)
	 */
	public function send_notification($data,$old,$email,$user_or_lang,$check=null,$assignment_changed=true)
	{
		//error_log("bo_trackering::send_notification(,,'$email',$user_or_lang,$check)");
		if (!$email) return false;

		if (!$this->save_prefs) $this->save_prefs = $GLOBALS['egw_info']['user'];

		if (is_numeric($user_or_lang))	// user --> read everything from his prefs
		{
			$GLOBALS['egw']->preferences->__construct($user_or_lang);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();

			if ($check && $this->check2pref) $check = $this->check2pref[$check];
			if ($check && !$GLOBALS['egw_info']['user']['preferences'][$this->app][$check])
			{
				return false;	// no notification requested
			}
			if ($check && $GLOBALS['egw_info']['user']['preferences'][$this->app][$check] === 'assignment' && !$assignment_changed)
			{
				return false;	// only notification about changed assignment requested
			}
			if($this->user == $user_or_lang && !$this->notify_current_user)
			{
				return false;  // no popup for own actions
			}
		}
		else
		{
			// for the notification copy, we use the default-prefs plus the language from the the tracker config
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->default;
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $user_or_lang;
		}
		if ($lang != translation::$userlang)	// load the right language if needed
		{
			translation::init();
		}

		// send over notification_app
		if ($GLOBALS['egw_info']['apps']['notifications']['enabled']) {
			// send via notification_app
			$receiver = is_numeric($user_or_lang) ? $user_or_lang : $email;
			try {
				$notification = new notifications();
				$notification->set_receivers(array($receiver));
				$notification->set_message($this->get_body(false,$data,$old,false)); // set message as plaintext
				$notification->set_message($this->get_body(true,$data,$old,false)); // and html
				$notification->set_sender($this->get_sender($data,$old,true));
				$notification->set_subject($this->get_subject($data,$old));
				$notification->set_links(array($this->get_notification_link($data,$old)));
				$attachments = $this->get_attachments($data,$old);
				if(is_array($attachments)) { $notification->set_attachments($attachments); }
				$notification->send();
			}
			catch (Exception $exception) {
				$this->errors[] = $exception->getMessage();
				return false;
			}
		} else {
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
	 * @return string or userid
	 */
	protected function get_sender($data,$old,$prefer_id=false)
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
	 * @return string
	 */
	protected function get_subject($data,$old)
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
	 * @return string
	 */
	protected function get_message($data,$old)
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
	 * @return string/array string with link (!$allow_popup) or array(link,popup-size), popup size is something like '640x480'
	 */
	protected function get_link($data,$old,$allow_popup=false)
	{
		if (($link = $this->get_config('link',$data,$old)))
		{
			if (strpos($link,$this->id_field.'=') === false)
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
		return $allow_popup ? array($link,$popup) : $link;
	}

	/**
	 * Get a link for notifications to view the entry, can be reimplemented
	 *
	 * @param array $data
	 * @param array $old
	 * @return array with link
	 */
	protected function get_notification_link($data,$old)
	{
		if($view = egw_link::view($this->app,$data[$this->id_field])) {
			return array(	'text' 	=> $this->get_title($data,$old),
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
     * @return string
     */
    public function get_body($html_email,$data,$old,$integrate_link = true)
    {
        $body = '';
        if ($html_email)
        {
            $body = '<table cellspacing="2" cellpadding="0" border="0" width="100%">'."\n";
        }
        // new or modified message
        if (($message = $this->get_message($data,$old)))
        {
            $body .= $this->format_line($html_email,'message',false,$message);
        }
        if ($integrate_link && ($link = $this->get_link($data,$old)))
        {
            $body .= $this->format_line($html_email,'link',false,lang('You can respond by visiting:'),$link);
        }
        foreach($this->get_details($data) as $name => $detail)
        {
            // if there's no old entry, the entry is not modified by definition
            // if both values are '', 0 or null, we count them as equal too
            $modified = $old && $data[$name] != $old[$name] && !(!$data[$name] && !$old[$name]);
            //if ($modified) error_log("data[$name]=".print_r($data[$name],true).", old[$name]=".print_r($old[$name],true)." --> modified=".(int)$modified);
            if (empty($detail['value']) && !$modified) continue;    // skip unchanged, empty values

            $body .= $this->format_line($html_email,$detail['type'],$modified,
                ($detail['label'] ? $detail['label'].': ':'').$detail['value']);
        }
        if ($html_email)
        {
            $body .= "</table>\n";
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
	 * @param string $line
	 * @param string $link=null
	 * @return string
	 */
	protected function format_line($html_mail,$type,$modified,$line,$link=null)
	{
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
					$pos = strpos($line, '<br');
					if ($pos===false)
					{
						$line = nl2br($line);
					}
					break;
				case 'reply':
					$background = '#F1F1F1';
					break;
				default:
					$size = '100%';
			}
			$style = ($bold ? 'font-weight:bold;' : '').($size ? 'font-size:'.$size.';' : '').($color?'color:'.$color:'');

			$content = '<tr style="background-color: '.$background.';"><td style="'.$style.'">';
		}
		else	// text-mail
		{
			if ($type == 'reply') $content = str_repeat('-',64)."\n";

			if ($modified) $content .= '> ';
		}
		$content .= $line;

		if ($link)
		{
			$content .= ' ';

			if ($html_mail)
			{
				// the link is often too long for html boxes
				// chunk-split allows to break lines if needed
				$content .= html::a_href(chunk_split($link,40,'&#8203'),$link,'','target="_blank"');
			}
			else
			{
				$content .= $link;
			}
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
	 * @return array with values for either 'content' or 'path' and optionally 'mimetype', 'filename' and 'encoding'
	 */
	protected function get_attachments($data,$old)
	{
	 	return array();
	}
}
