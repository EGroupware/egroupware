<?php
/**
 * EGroupware - Calendar's buisness-object - access + update
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2005-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\Link;

// types of messsages send by calendar_boupdate::send_update
define('MSG_DELETED',0);
define('MSG_MODIFIED',1);
define('MSG_ADDED',2);
define('MSG_REJECTED',3);
define('MSG_TENTATIVE',4);
define('MSG_ACCEPTED',5);
define('MSG_ALARM',6);
define('MSG_DISINVITE',7);
define('MSG_DELEGATED',8);
define('MSG_REQUEST',9);

/**
 * Class to access AND manipulate all calendar data (business object)
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * As this BO class deals with dates/times of several types and timezone, each variable should have a postfix
 * appended, telling with type it is: _s = seconds, _su = secs in user-time, _ss = secs in server-time, _h = hours
 *
 * All new BO code (should be true for eGW in general) NEVER use any $_REQUEST ($_POST or $_GET) vars itself.
 * Nor does it store the state of any UI-elements (eg. cat-id selectbox). All this is the task of the UI class(es) !!!
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the bocal class !!!
 */

class calendar_boupdate extends calendar_bo
{
	/**
	 * Category ACL allowing to add a given category
	 */
	const CAT_ACL_ADD = 512;
	/**
	 * Category ACL allowing to change status of a participant
	 */
	const CAT_ACL_STATUS = 1024;

	/**
	 * name of method to debug or level of debug-messages:
	 *	False=Off as higher as more messages you get ;-)
	 *	1 = function-calls incl. parameters to general functions like search, read, write, delete
	 *	2 = function-calls to exported helper-functions like check_perms
	 *	4 = function-calls to exported conversation-functions like date2ts, date2array, ...
	 *	5 = function-calls to private functions
	 * @var mixed
	 */
	var $debug;

	/**
	 * Set Logging
	 *
	 * @var boolean
	 */
	var $log = false;
	var $logfile = '/tmp/log-calendar-boupdate';

	/**
	 * Cached timezone data
	 *
	 * @var array id => data
	 */
	protected static $tz_cache = array();

	/**
	 * Constructor
	 */
	function __construct()
	{
		if ($this->debug > 0) $this->debug_message('calendar_boupdate::__construct() started',True);

		parent::__construct();	// calling the parent constructor

		if ($this->debug > 0) $this->debug_message('calendar_boupdate::__construct() finished',True);
	}

	/**
	 * updates or creates an event, it (optionally) checks for conflicts and sends the necessary notifications
	 *
	 * @param array &$event event-array, on return some values might be changed due to set defaults
	 * @param boolean $ignore_conflicts =false just ignore conflicts or do a conflict check and return the conflicting events
	 * @param boolean $touch_modified =true NOT USED ANYMORE (was only used in old csv-import), modified&modifier is always updated!
	 * @param boolean $ignore_acl =false should we ignore the acl
	 * @param boolean $updateTS =true update the content history of the event (ignored, as updating timestamps is required for sync!)
	 * @param array &$messages=null messages about because of missing ACL removed participants or categories
	 * @param boolean|"NOPUSH" $skip_notification =false true: send NO notifications, default false = send them,
	 *  "NOPUSH": also do NOT send push notifications / call Link::notifiy(), which still happens for true
	 * @return mixed on success: int $cal_id > 0, on error false or array with conflicting events (only if $check_conflicts)
	 * 		Please note: the events are not guarantied to be readable by the user (no read grant or private)!
	 *
	 * @ToDo current conflict checking code does NOT cope quantity-wise correct with multiple non-overlapping
	 * 	events overlapping the event to store: the quantity sum is used, even as the events dont overlap!
	 *
	 * ++++++++ ++++++++
	 * +      + +  B   +	If A get checked for conflicts, the check used for resource quantity is
	 * +      + ++++++++
	 * +  A   +				quantity(A,resource)+quantity(B,resource)+quantity(C,resource) > maxBookable(resource)
	 * +      + ++++++++
	 * +      + +  C   +	which is clearly wrong for everything with a maximum quantity > 1
	 * ++++++++ ++++++++
	 */
	function update(&$event,$ignore_conflicts=false,$touch_modified=true,$ignore_acl=false,$updateTS=true,&$messages=null, $skip_notification=false)
	{
		$update_type = 'edit';
		unset($updateTS);	// ignored, as updating timestamps is required for sync!
		//error_log(__METHOD__."(".array2string($event).",$ignore_conflicts,$touch_modified,$ignore_acl)");
		if (!is_array($messages)) $messages = $messages ? (array)$messages : array();

		if ($this->debug > 1 || $this->debug == 'update')
		{
			$this->debug_message('calendar_boupdate::update(%1,ignore_conflict=%2,touch_modified=%3,ignore_acl=%4)',
				false,$event,$ignore_conflicts,$touch_modified,$ignore_acl);
		}
		// check some minimum requirements:
		// - new events need start, end and title
		// - updated events cant set start, end or title to empty
		if (empty($event['id']) && (!$event['start'] || !$event['end'] || !$event['title']) ||
			!empty($event['id']) && (isset($event['start']) && !$event['start'] || isset($event['end']) && !$event['end'] ||
			isset($event['title']) && !$event['title']))
		{
			$messages[] = lang('Required information (start, end, title, ...) missing!');
			return false;
		}

		$status_reset_to_unknown = false;

		if (($new_event = empty($event['id'])))	// some defaults for new entries
		{
			// if no owner given, set user to owner
			if (!$event['owner']) $event['owner'] = $this->user;
			// set owner as participant if none is given
			if (!is_array($event['participants']) || !count($event['participants']))
			{
				$status = calendar_so::combine_status($event['owner'] == $this->user ? 'A' : 'U', 1, 'CHAIR');
				$event['participants'] = array($event['owner'] => $status);
			}
			$update_type = 'add';
		}

		// check if user has the permission to update / create the event
		if (!$ignore_acl && (!$new_event && !$this->check_perms(Acl::EDIT,$event['id']) ||
			$new_event && !$this->check_perms(Acl::EDIT,0,$event['owner'])) &&
			!$this->check_perms(Acl::ADD,0,$event['owner']))
		{
			$messages[] = lang('Access to calendar of %1 denied!',Api\Accounts::username($event['owner']));
			return false;
		}
		if ($new_event)
		{
			$old_event = array();
		}
		else
		{
			$old_event = $this->read((int)$event['id'],null,$ignore_acl);
		}

		// do we need to check, if user is allowed to invite the invited participants
		if ($this->require_acl_invite && ($removed = $this->remove_no_acl_invite($event,$old_event)))
		{
			// report removed participants back to user
			foreach($removed as $key => $account_id)
			{
				$removed[$key] = $this->participant_name($account_id);
			}
			$messages[] = lang('%1 participants removed because of missing invite grants',count($removed)).
				': '.implode(', ',$removed);
		}
		// check category based ACL
		if (!empty($event['category']))
		{
			if (!is_array($event['category'])) $event['category'] = explode(',',$event['category']);
			if (!$old_event || !isset($old_event['category']))
			{
				$old_event['category'] = array();
			}
			elseif (!is_array($old_event['category']))
			{
				$old_event['category'] = explode(',', $old_event['category']);
			}
			foreach($event['category'] as $key => $cat_id)
			{
				// check if user is allowed to update event categories
				if ((!$old_event || !in_array($cat_id,$old_event['category'])) &&
					self::has_cat_right(self::CAT_ACL_ADD,$cat_id,$this->user) === false)
				{
					unset($event['category'][$key]);
					// report removed category to user
					$removed_cats[$cat_id] = $this->categories->id2name($cat_id);
					continue;	// no further check, as cat was removed
				}
				// for new or moved events check status of participants, if no category status right --> set all status to 'U' = unknown
				if (!$status_reset_to_unknown &&
					self::has_cat_right(self::CAT_ACL_STATUS,$cat_id,$this->user) === false &&
					(!$old_event || $old_event['start'] != $event['start'] || $old_event['end'] != $event['end']))
				{
					foreach((array)$event['participants'] as $uid => $status)
					{
						$q = $r = null;
						calendar_so::split_status($status,$q,$r);
						if ($status != 'U')
						{
							$event['participants'][$uid] = calendar_so::combine_status('U',$q,$r);
							// todo: report reset status to user
						}
					}
					$status_reset_to_unknown = true;	// once is enough
				}
			}
			if ($removed_cats)
			{
				$messages[] = lang('Category %1 removed because of missing rights',implode(', ',$removed_cats));
			}
			if ($status_reset_to_unknown)
			{
				$messages[] = lang('Status of participants set to unknown because of missing category rights');
			}
		}

		// generate a video-room-url, if we need one and not already have one
		if (!empty($event['videoconference']) && empty($event['##videoconference']) && !calendar_hooks::isVideoconferenceDisabled())
		{
			$event['##videoconference'] = EGroupware\Status\Videoconference\Call::genUniqueRoomID();
		}
		elseif (isset($event['videoconference']) && !$event['videoconference'])
		{
			$event['##videoconference'] = '';
		}
		// update videoconference resource amounts based on number of participants
		if (!empty($event['videoconference']) && !empty($event['##videoconference']) && !calendar_hooks::isVideoconferenceDisabled()
			&& ($videoconferenceResId = \EGroupware\Status\Hooks::getVideoconferenceResourceId()))
		{
			$participant_total = 0;
			foreach(['u','e','c'] as $p_type)
			{
				if(is_array($event['participant_types'][$p_type]))
				{
					$participant_total += count($event['participant_types'][$p_type]);
				}
			}
			$event['participant_types']['r'][$videoconferenceResId] =
			$event['participants']['r'.$videoconferenceResId] = 'A'.$participant_total;
		}

		// check for conflicts only happens !$ignore_conflicts AND if start + end date are given
		$checked_excluding = null;
		if (!$ignore_conflicts && !$event['non_blocking'] && isset($event['start']) && isset($event['end']) &&
			(($conflicts = $this->conflicts($event, $checked_excluding)) || $checked_excluding))
		{
			if ($checked_excluding)	// warn user if not all recurrences have been checked
			{
				$conflicts['warning'] = array(
					'start' => $checked_excluding,
					'title' => lang('Only recurrences until %1 (excluding) have been checked!', $checked_excluding->format(true)),
				);
			}
			return $conflicts;
		}



		//echo "saving $event[id]="; _debug_array($event);
		$event2save = $event;

		if (!($cal_id = $this->save($event, $ignore_acl)))
		{
			return $cal_id;
		}

		// we re-read the event, in case only partial information was update and we need the full info for the notifies
		// The check for new private events is to at least show the private version,
		// otherwise we get FALSE
		$event = $this->read($cal_id, null, $ignore_acl, 'ts', $new_event && !$event['public'] ? $this->user : null);
		//error_log("new $cal_id=". array2string($event));

		if(!empty($old_event['deleted']) && !isset($event['deleted']))
		{
			// Restored, bring back links
			Link::restore('calendar', $cal_id);
			$update_type = 'add';
		}
		if (!empty($this->log_file))
		{
			$this->log2file($event2save,$event,$old_event);
		}
		// send notifications if the event is in the future
		if(!$skip_notification && $event['end'] > $this->now_su)
		{
			if ($new_event)
			{
				$this->send_update(MSG_ADDED,$event['participants'],null, $event);
			}
			else // update existing event
			{
				$this->check4update($event,$old_event);
			}
		}

		// notify the link-class about the update, as other apps may be subscribed to it
		if ($skip_notification !== "NOPUSH")
		{
			Link::notify_update('calendar', $cal_id, $event, $update_type);
		}

		return $cal_id;
	}

	/**
	 * Check given event for conflicts and return them
	 *
	 * For recurring events we check a configurable fixed number of recurrences
	 * and for a fixed maximum time (default 3s).
	 *
	 * Conflict check skips past events/recurrences and is always limited by recurrence horizont,
	 * as it would only report non-recurring events after.
	 *
	 * @param array $event
	 * @param Api\DateTime& $checked_excluding =null time until which (excluding) recurrences have been checked
	 * @return array or events
	 */
	function conflicts(array $event, &$checked_excluding=null)
	{
		$types_with_quantity = array();
		foreach($this->resources as $type => $data)
		{
			if ($data['max_quantity']) $types_with_quantity[] = $type;
		}
		// get all NOT rejected participants and evtl. their quantity
		$quantity = $users = array();
		foreach($event['participants'] as $uid => $status)
		{
			$q = $role = null;
			calendar_so::split_status($status, $q, $role);
			if ($status == 'R' || $role == 'NON-PARTICIPANT') continue;	// ignore rejected or non-participants

			if ($uid < 0)	// group, check it's members too
			{
				$users = array_unique(array_merge($users, (array)$GLOBALS['egw']->accounts->members($uid,true)));
			}
			$users[] = $uid;
			if (in_array($uid[0],$types_with_quantity))
			{
				$quantity[$uid] = $q;
			}
		}
		$max_quantity = $possible_quantity_conflicts = $conflicts = array();

		if ($event['recur_type'])
		{
			$recurences = calendar_rrule::event2rrule($event);
		}
		else
		{
			$recurences = array(new Api\DateTime((int)$event['start']));
		}
		$checked_excluding = null;
		$max_checked = $GLOBALS['egw_info']['server']['conflict_max_checked'];
		if (($max_check_time = (float)$GLOBALS['egw_info']['server']['conflict_max_check_time']) < 1.0)
		{
			$max_check_time = 3.0;
		}
		$checked = 0;
		$start = microtime(true);
		$duration = Api\DateTime::to($event['end'], 'ts') - Api\DateTime::to($event['start'], 'ts');
		foreach($recurences as $date)
		{
			$startts = $date->format('ts');

			// skip past events or recurrences
			if ($startts+$duration < $this->now_su) continue;

			// abort check if configured limits are exceeded
			if ($event['recur_type'] &&
				(++$checked > $max_checked && $max_checked > 0 || // maximum number of checked recurrences exceeded
				microtime(true) > $start+$max_check_time ||	// max check time exceeded
				$startts > $this->config['horizont']))	// we are behind horizon for which recurrences are rendered
			{
				if ($this->debug > 2 || $this->debug == 'conflicts')
				{
					$this->debug_message(__METHOD__.'() conflict check limited to %1 recurrences, %2 seconds, until (excluding) %3',
						$checked, microtime(true)-$start, $date);
				}
				$checked_excluding = $date;
				break;
			}
			$overlapping_events =& $this->search(array(
				'start' => $startts,
				'end'   => $startts+$duration,
				'users' => $users,
				'ignore_acl' => true,	// otherwise we get only events readable by the user
				'enum_groups' => true,	// otherwise group-events would not block time
				'query' => array(
					'cal_non_blocking' => 0,
				),
				'no_integration' => true,	// do NOT use integration of other apps
			));
			if ($this->debug > 2 || $this->debug == 'conflicts')
			{
				$this->debug_message(__METHOD__.'() checking for potential overlapping events for users %1 from %2 to %3',false,$users,$startts,$startts+$duration);
			}
			foreach((array) $overlapping_events as $k => $overlap)
			{
				if ($overlap['id'] == $event['id'] ||	// that's the event itself
					$overlap['id'] == $event['reference'] ||	// event is an exception of overlap
					$overlap['non_blocking'])			// that's a non_blocking event
				{
					continue;
				}
				if ($this->debug > 3 || $this->debug == 'conflicts')
				{
					$this->debug_message(__METHOD__.'() checking overlapping event %1',false,$overlap);
				}
				// check if the overlap is with a rejected participant or within the allowed quantity
				$common_parts = array_intersect($users,array_keys($overlap['participants']));
				foreach($common_parts as $n => $uid)
				{
					$status = $overlap['participants'][$uid];
					calendar_so::split_status($status, $q, $role);
					if ($status == 'R' || $role == 'NON-PARTICIPANT')
					{
						unset($common_parts[$n]);
						continue;
					}
					if (is_numeric($uid) || !in_array($uid[0],$types_with_quantity))
					{
						continue;	// no quantity check: quantity allways 1 ==> conflict
					}
					if (!isset($max_quantity[$uid]))
					{
						$res_info = $this->resource_info($uid);
						$max_quantity[$uid] = $res_info[$this->resources[$uid[0]]['max_quantity']];
					}
					$quantity[$uid] += $q;
					if ($quantity[$uid] <= $max_quantity[$uid])
					{
						$possible_quantity_conflicts[$uid][] =& $overlapping_events[$k];	// an other event can give the conflict
						unset($common_parts[$n]);
						continue;
					}
					// now we have a quantity conflict for $uid
				}
				if (count($common_parts))
				{
					if ($this->debug > 3 || $this->debug == 'conflicts')
					{
						$this->debug_message(__METHOD__.'() conflicts with the following participants found %1',false,$common_parts);
					}
					$conflicts[$overlap['id'].'-'.$this->date2ts($overlap['start'])] =& $overlapping_events[$k];
				}
			}
		}
		//error_log(__METHOD__."() conflict check took ".number_format(microtime(true)-$start, 3).'s');
		// check if we are withing the allowed quantity and if not add all events using that resource
		// seems this function is doing very strange things, it gives empty conflicts
		foreach($max_quantity as $uid => $max)
		{
			if ($quantity[$uid] > $max)
			{
				foreach((array)$possible_quantity_conflicts[$uid] as $conflict)
				{
					$conflicts[$conflict['id'].'-'.$this->date2ts($conflict['start'])] =& $possible_quantity_conflicts[$k];
				}
			}
		}
		unset($possible_quantity_conflicts);

		if (count($conflicts))
		{
			foreach($conflicts as $key => $conflict)
			{
					$conflict['participants'] = array_intersect_key((array)$conflict['participants'],$event['participants']);
				if (!$this->check_perms(Acl::READ,$conflict))
				{
					$conflicts[$key] = array(
						'id'    => $conflict['id'],
						'title' => lang('busy'),
						'participants' => $conflict['participants'],
						'start' => $conflict['start'],
						'end'   => $conflict['end'],
					);
				}
			}
			if ($this->debug > 2 || $this->debug == 'conflicts')
			{
				$this->debug_message(__METHOD__.'() %1 conflicts found %2',false,count($conflicts),$conflicts);
			}
		}
		return $conflicts;
	}
	/**
	 * Remove participants current user has no right to invite
	 *
	 * @param array &$event new event
	 * @param array $old_event =null old event with already invited participants
	 * @return array removed participants because of missing invite grants
	 */
	public function remove_no_acl_invite(array &$event,array $old_event=null)
	{
		if (!$this->require_acl_invite)
		{
			return array();	// nothing to check, everyone can invite everyone else
		}
		if ($event['id'] && is_null($old_event))
		{
			$old_event = $this->read($event['id']);
		}
		$removed = array();
		foreach(array_keys((array)$event['participants']) as $uid)
		{
			if ((is_null($old_event) || !isset($old_event['participants'][$uid])) && !$this->check_acl_invite($uid))
			{
				unset($event['participants'][$uid]);	// remove participant
				$removed[] = $uid;
			}
		}
		//echo "<p>".__METHOD__."($event[title],".($old_event?'$old_event':'NULL').") returning ".array2string($removed)."</p>";
		return $removed;
	}

	/**
	 * Check if current user is allowed to invite a given participant
	 *
	 * @param int|string $uid
	 * @return boolean
	 */
	public function check_acl_invite($uid)
	{
		if (!is_numeric($uid) && $this->resources[$uid[0]]['check_invite'])
		{
			// Resource specific ACL check
			return call_user_func($this->resources[$uid[0]]['check_invite'], $uid);
		}
		elseif (!$this->require_acl_invite)
		{
			$ret = true;	// no grant required
		}
		elseif ($this->require_acl_invite == 'groups' && $GLOBALS['egw']->accounts->get_type($uid) != 'g')
		{
			$ret = true;	// grant only required for groups
		}
		else
		{
			$ret = $this->check_perms(self::ACL_INVITE,0,$uid);
		}
		//error_log(__METHOD__."($uid) = ".array2string($ret));
		//echo "<p>".__METHOD__."($uid) require_acl_invite=$this->require_acl_invite returning ".array2string($ret)."</p>\n";
		return $ret;
	}

	/**
	 * Check for added, modified or deleted participants AND notify them
	 *
	 * @param array $new_event the updated event
	 * @param array $old_event the event before the update
	 */
	function check4update($new_event,$old_event)
	{
		//error_log(__METHOD__."($new_event[title])");
		$modified = $added = $deleted = array();

		//echo "<p>calendar_boupdate::check4update() new participants = ".print_r($new_event['participants'],true).", old participants =".print_r($old_event['participants'],true)."</p>\n";
		foreach(['start','end','tz_id','owner','category','priority','public','title','description','location'] as $field)
		{
			if($new_event[$field] !== $old_event[$field])
			{
				$modified = $new_event['participants'];
				break;
			}
		}

		// Find modified and deleted participants ...
		foreach ((array)$old_event['participants'] as $old_userid => $old_status)
		{
			if (isset($new_event['participants'][$old_userid]) && $old_status !== $new_event['participants'][$old_userid])
			{
				$modified[$old_userid] = $new_event['participants'][$old_userid];
			}
			else if (!isset($new_event['participants'][$old_userid]))
			{
				$deleted[$old_userid] = $old_status;
			}
		}
		// Find new participants ...
		foreach(array_keys((array)$new_event['participants']) as $new_userid)
		{
			if(!isset($old_event['participants'][$new_userid]))
			{
				$added[$new_userid] = 'U';
			}
		}
		//echo "<p>calendar_boupdate::check4update() added=".print_r($added,true).", modified=".print_r($modified,true).", deleted=".print_r($deleted,true)."</p>\n";
		if(count($added) || count($modified) || count($deleted))
		{
			if(count($added))
			{
				$this->send_update(MSG_ADDED,$added,$old_event,$new_event);
			}
			if(count($modified))
			{
				$this->send_update(MSG_MODIFIED,$modified,$old_event,$new_event);
			}
			if(count($deleted))
			{
				$this->send_update(MSG_DISINVITE,$deleted,$new_event);
			}
		}
	}

	/**
	 * checks if $userid has requested (in $part_prefs) updates for $msg_type
	 *
	 * @param int $userid numerical user-id
	 * @param array $part_prefs preferces of the user $userid
	 * @param int &$msg_type type of the notification: MSG_ADDED, MSG_MODIFIED, MSG_ACCEPTED, ...
	 * @param array $old_event Event before the change
	 * @param array $new_event Event after the change
	 * @param string $role we treat CHAIR like event owners
	 * @param string $status of current user
	 * @return boolean true = update requested, false otherwise
	 */
	public function update_requested($userid, $part_prefs, &$msg_type, $old_event ,$new_event, $role, $status=null)
	{
		if ($msg_type == MSG_ALARM)
		{
			return True;	// always True for now
		}
		$want_update = 0;

		// the following switch falls through all cases, as each included the following too
		//
		$msg_is_response = $msg_type == MSG_REJECTED || $msg_type == MSG_ACCEPTED || $msg_type == MSG_TENTATIVE || $msg_type == MSG_DELEGATED;

		// Check if user is not participating, and does not want notifications
		if ($msg_is_response && is_array($old_event) && empty($part_prefs['calendar']['receive_not_participating']) &&
			// userid is the email address for non-user and NOT necessary the uid used as key in participants ("rb@egroupware.org" vs "eRalf Becker <rb@egroupware.org>")
			!in_array(is_numeric($userid) ? $userid : strtolower($userid), array_map(function($uid)
			{
				return is_numeric($uid) ? $uid : strtolower($this->resource_info($uid)['email'] ?? '');
			}, array_keys($old_event['participants'])), false))
		{
			error_log(__METHOD__."(userid=$userid, receive_not_participating='{$part_prefs['calendar']['receive_not_participating']}', msg_type=$msg_type, {participants: ".json_encode($old_event['participants']).", ...}, role='$role') msg_is_response=$msg_is_response --> user $userid is NOT participating");
			return false;
		}

		// always notify externals chairs
		// EGroupware owner only get notified about responses, if pref is NOT "no"
		if (!is_numeric($userid) && $role == 'CHAIR' &&
			($msg_is_response || in_array($msg_type, array(MSG_ADDED, MSG_DELETED))))
		{
			switch($msg_type)
			{
				case MSG_DELETED:	// treat deleting event as rejection to organizer
					$msg_type = MSG_REJECTED;
					break;
				case MSG_ADDED:		// new events use added, but organizer needs status
					switch($status[0])
					{
						case 'A': $msg_type = MSG_ACCEPTED; break;
						case 'R': $msg_type = MSG_REJECTED; break;
						case 'T': $msg_type = MSG_TENTATIVE; break;
						case 'D': $msg_type = MSG_DELEGATED; break;
					}
					break;
			}
			++$want_update;
		}
		else
		{
			switch($ru = $part_prefs['calendar']['receive_updates'])
			{
				case 'responses':
					++$want_update;
				case 'modifications':
					if (!$msg_is_response)
					{
						++$want_update;
					}
				case 'time_change_4h':
				case 'time_change':
				default:
					if (is_array($new_event) && is_array($old_event))
					{
						$diff = max(abs(self::date2ts($old_event['start']??null)-self::date2ts($new_event['start']??null)),
							abs(self::date2ts($old_event['end']??null)-self::date2ts($new_event['end']??null)));
						$check = $ru == 'time_change_4h' ? 4 * 60 * 60 - 1 : 0;
						if ($msg_type == MSG_MODIFIED && $diff > $check)
						{
							++$want_update;
						}
					}
				case 'add_cancel':
					if ($msg_is_response && is_array($old_event) && (isset($old_event['owner']) && $old_event['owner'] == $userid || $role == 'CHAIR') ||
						$msg_type == MSG_DELETED || $msg_type == MSG_ADDED || $msg_type == MSG_DISINVITE)
					{
						++$want_update;
					}
					break;
				case 'no':
					break;
			}
		}
		//error_log(__METHOD__."(userid=$userid, receive_updates='$ru', msg_type=$msg_type, ".json_encode($old_event).", ..., role='$role') msg_is_response=$msg_is_response --> want_update=$want_update");
		return $want_update > 0;
	}

	/**
	 * Check calendar prefs, if a given user (integer account_id) or email (user or externals) should get notified
	 *
	 * @param int|string $user_or_email
	 * @param string $ical_method ='REQUEST'
	 * @param string $role ='REQ-PARTICIPANT'
	 * @param string $notify_externals =null event-specific overwrite, default preferences
	 * @return boolean true if user requested to be notified, false if not
	 */
	static public function email_update_requested($user_or_email, $ical_method='REQUEST', $role='REQ-PARTICIPANT',$notify_externals=null)
	{
		// check if email is from a user
		if (is_numeric($user_or_email))
		{
			$account_id = $user_or_email;
		}
		else
		{
			$account_id = $GLOBALS['egw']->accounts->name2id($user_or_email, 'account_email');
		}
		if ($account_id)
		{
			$pref_obj = new Api\Preferences($account_id);
			$prefs = $pref_obj->read_repository();
		}
		else
		{
			$prefs = array(
				'calendar' => array(
					'receive_updates' => $notify_externals ?: $GLOBALS['egw_info']['user']['preferences']['calendar']['notify_externals'],
				)
			);
		}
		switch($ical_method)
		{
			default:
			case 'REQUEST':
				$msg_type = MSG_ADDED;
				break;
			case 'REPLY':
				$msg_type = MSG_ACCEPTED;
				break;
			case 'CANCEL':
				$msg_type = MSG_DELETED;
				break;
		}
		static $calendar_boupdate=null;
		if (!isset($calendar_boupdate)) $calendar_boupdate = new calendar_boupdate();
		$ret = $calendar_boupdate->update_requested($account_id, $prefs, $msg_type, array(), array(), $role);
		//error_log(__METHOD__."('$user_or_email', '$ical_method', '$role') account_id=$account_id --> updated_requested returned ".array2string($ret));
		return $ret;
	}

	/**
	 * Get iCal/iMip method from internal nummeric msg-type plus optional notification message and verbose name
	 *
	 * @param int $msg_type see MSG_* defines
	 * @param string& $action=null on return verbose name
	 * @param string& $msg=null on return notification message
	 */
	function msg_type2ical_method($msg_type, &$action=null, &$msg=null, $prefs=null)
	{
		switch($msg_type)
		{
			case MSG_DELETED:
				$action = 'Canceled';
				$pref = 'Canceled';
				$method = 'CANCEL';
				break;
			case MSG_MODIFIED:
				$action = 'Modified';
				$pref = 'Modified';
				$method = 'REQUEST';
				break;
			case MSG_DISINVITE:
				$action = 'Disinvited';
				$pref = 'Disinvited';
				$method = 'CANCEL';
				break;
			case MSG_ADDED:
				$action = 'Added';
				$pref = 'Added';
				$method = 'REQUEST';
				break;
			case MSG_REJECTED:
				$action = 'Rejected';
				$pref = 'Response';
				$method = 'REPLY';
				break;
			case MSG_TENTATIVE:
				$action = 'Tentative';
				$pref = 'Response';
				$method = 'REPLY';
				break;
			case MSG_ACCEPTED:
				$action = 'Accepted';
				$pref = 'Response';
				$method = 'REPLY';
				break;
			case MSG_DELEGATED:
				$action = 'Delegated';
				$pref = 'Response';
				$method = 'REPLY';
				break;
			case MSG_ALARM:
				$action = 'Alarm';
				$pref = 'Alarm';
				break;
			case MSG_REQUEST:
				$method = 'REQUEST';
				break;
			default:
				$method = 'PUBLISH';
		}
		if(is_null($prefs))
		{
			$prefs = $this->cal_prefs;
		}
		$msg = $prefs['notify'.$pref];
		if (empty($msg))
		{
			$msg = $prefs['notifyAdded'];	// use a default
		}
		//error_log(__METHOD__."($msg_type) action='$action', $msg='$msg' returning '$method'");
		return $method ?? null;
	}

	/**
	 * sends update-messages to certain participants of an event
	 *
	 * @param int $msg_type type of the notification: MSG_ADDED, MSG_MODIFIED, MSG_ACCEPTED, ...
	 * @param array $to_notify numerical user-ids as keys (!) (value is not used)
	 * @param array $old_event Event before the change
	 * @param array $new_event =null Event after the change
	 * @param int|string $user =0 User/participant who started the notify, default current user
	 * @param array $alarm =null values for "offset", "start", etc.
	 * @return bool true/false
	 */
	function send_update($msg_type, $to_notify, $old_event, $new_event=null, $user=0, array $alarm=null)
	{
		Api\Egw::on_shutdown([$this, '_send_update'], func_get_args());
	}

	/**
	 * sends update-messages to certain participants of an event
	 *
	 * @param int $msg_type type of the notification: MSG_ADDED, MSG_MODIFIED, MSG_ACCEPTED, ...
	 * @param array $to_notify numerical user-ids as keys (!) (value is not used)
	 * @param array $old_event Event before the change
	 * @param array $new_event =null Event after the change
	 * @param int|string $user =0 User/participant who started the notify, default current user
	 * @param array $alarm =null values for "offset", "start", etc.
	 * @parqm boolean $ignore_prefs Ignore the user's preferences about when they want to be notified and send it
	 * @return bool true/false
	 */
	function _send_update($msg_type, $to_notify, $old_event, $new_event=null, $user=0, array $alarm=null, $ignore_prefs = false)
	{
		//error_log(__METHOD__."($msg_type, ".json_encode($to_notify).", ...,  ".json_encode($new_event).", ...)");
		if (!is_array($to_notify))
		{
			$to_notify = array();
		}
		$notify_externals = $new_event ? ($new_event['##notify_externals']??null) : ($old_event['##notify_externals']??null);
		$disinvited = $msg_type == MSG_DISINVITE ? array_keys($to_notify) : array();

		$owner = $old_event ? $old_event['owner'] : $new_event['owner'];
		if($owner && !isset($to_notify[$owner]) && $msg_type != MSG_ALARM)
		{
			$to_notify[$owner] = 'OCHAIR';    // always include the event-owner
		}

		// ignore events in the past (give a tolerance of 10 seconds for the script)
		if($new_event && $this->date2ts($new_event['start']) < ($this->now_su - 10) ||
			!$new_event && $old_event && $this->date2ts($old_event['start']) < ($this->now_su - 10)
		)
		{
			error_log(__METHOD__."($msg_type, ".json_encode($to_notify).", ...,  ".json_encode($new_event).", ...) --> ignoring event in the past: start=".
				date('Y-m-d H:i:s', ($new_event ?: $old_event)['start'])." < ".date('Y-m-d H:i:s', $this->now_su-10));
			return False;
		}
		// check if default timezone is set correctly to server-timezone (ical-parser messes with it!!!)
		if($GLOBALS['egw_info']['server']['server_timezone'] && ($tz = date_default_timezone_get()) != $GLOBALS['egw_info']['server']['server_timezone'])
		{
			$restore_tz = $tz;
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
		}
		$temp_user = $GLOBALS['egw_info']['user'];    // save user-date of the enviroment to restore it after

		if (!$user)
		{
			$user = $temp_user['account_id'];
		}
		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		if ($GLOBALS['egw']->preferences->account_id != $user)
		{
			// Get correct preferences
			$GLOBALS['egw']->preferences->__construct(is_numeric($user) ? $user : $temp_user['account_id']);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();

			// If target user/participant is not an account, try to get good notification message
			if(!is_numeric($user))
			{
				$res_info = $this->resource_info($user);
				$title = $res_info['name'] ?: Link::title($res_info['app'], $res_info['res_id']) ?: $res_info['res_id'] ?: $user;
				$GLOBALS['egw']->preferences->values['fullname'] = $GLOBALS['egw']->preferences->values['lastname'] = $title;
				$GLOBALS['egw']->preferences->values['firstname'] = '';
				$msg = $GLOBALS['egw']->preferences->user['calendar']['notifyResponse'] ?: $GLOBALS['egw']->preferences->default['calendar']['notifyResponse'] ?: $GLOBALS['egw']->preferences->forced['calendar']['notifyResponse'];
				$GLOBALS['egw_info']['user']['preferences']['calendar']['notifyResponse'] = $GLOBALS['egw']->preferences->parse_notify(
					$msg
				);
			}

		}
		$senderid = $this->user;
		$event = $msg_type == MSG_ADDED || $msg_type == MSG_MODIFIED ? $new_event : $old_event;

		// add all group-members to the notification, unless they are already participants
		foreach($to_notify as $userid => $statusid)
		{
			if (is_numeric($userid) && $GLOBALS['egw']->accounts->get_type($userid) == 'g' &&
				($members = $GLOBALS['egw']->accounts->members($userid, true)))
			{
				foreach($members as $member)
				{
					if (!isset($to_notify[$member]))
					{
						$to_notify[$member] = 'G';	// Group-invitation
					}
				}
			}
		}
		// unless we notify externals about everything aka 'responses'
		// we will notify only an external chair, if only one exists
		if (($notify_externals ?: $GLOBALS['egw_info']['user']['calendar']['notify_externals'] ?? null) !== 'responses')
		{
			// check if we have *only* an external chair
			$chair = null;
			foreach($to_notify as $userid => $statusid)
			{
				$res_info = $quantity = $role = null;
				calendar_so::split_status($statusid, $quantity, $role);
				if ($role == 'CHAIR' && (empty($chair) || !is_numeric($chair)))
				{
					$chair = $userid;
				}
			}
			// *only* an external chair --> do not notify anyone, but the external chair and the current user
			if (!empty($chair) && !is_numeric($chair))
			{
				$to_notify = array($chair => $to_notify[$chair])+
					(isset($to_notify[$user]) ? array($user => $to_notify[$user]) : array());
			}
		}

		// Event is passed in user time, make sure that's taken into account for date calculations
		$user_prefs = $GLOBALS['egw_info']['user']['preferences'];
		$date = new Api\DateTime('now',new DateTimeZone($user_prefs['common']['tz']));
		$startdate = new Api\DateTime($event['start'], new DateTimeZone($user_prefs['common']['tz']));
		$enddate = new Api\DateTime($event['end'], new DateTimeZone($user_prefs['common']['tz']));
		$modified = new Api\DateTime($event['modified'], new DateTimeZone($user_prefs['common']['tz']));
		if ($old_event) $olddate = new Api\DateTime($old_event['start'], new DateTimeZone($user_prefs['common']['tz']));

		//error_log(__METHOD__."() date_default_timezone_get()=".date_default_timezone_get().", user-timezone=".Api\DateTime::$user_timezone->getName().", startdate=".$startdate->format().", enddate=".$enddate->format().", updated=".$modified->format().", olddate=".($olddate ? $olddate->format() : ''));
		$owner_prefs = $ics = null;
		foreach($to_notify as $userid => $statusid)
		{
			$res_info = $quantity = $role = null;
			calendar_so::split_status($statusid, $quantity, $role);
			if ($this->debug > 0) error_log(__METHOD__." trying to notify $userid, with $statusid ($role)");

			// hack to add videoconference in event description, by always setting $cleared_event
			// Can't re-load, if we're notifying of a cancelled recurrence we'll load the next event in the series
			$cleared_event = $event;

			if (!is_numeric($userid))
			{
				$res_info = $this->resource_info($userid);

				// check if responsible for a resource has read rights on event (might be private!)
				if ($res_info['app'] == 'resources' && $res_info['responsible'] &&
					!$this->check_perms(Acl::READ, $event, 0, 'ts', null, $res_info['responsible']))
				{
					// --> use only details from (private-)cleared event only containing resource ($userid)
					// reading server timezone, to be able to use cleared event for iCal generation further down
					//$cleared_event = $this->read($event['id'], null, true, 'server');
					$this->clear_private_infos($cleared_event, array($userid));
				}
				$userid = $res_info['responsible'] ?? null;

				if (empty($userid))	// no resource responsible: $userid===0
				{
					if (empty($res_info['email'])) continue;	// no way to notify
					// check if event-owner wants non-EGroupware users notified
					if (is_null($owner_prefs))
					{
						$preferences = new Api\Preferences($owner);
						$owner_prefs = $preferences->read_repository();
						if (!empty($notify_externals)) $owner_prefs['calendar']['notify_externals'] = $notify_externals;
					}
					if ($role != 'CHAIR' &&		// always notify externals CHAIRs
						(empty($owner_prefs['calendar']['notify_externals']) ||
						$owner_prefs['calendar']['notify_externals'] == 'no'))
					{
						continue;
					}
					$userid = $res_info['email'];
				}
			}

			if ($statusid == 'R' || $GLOBALS['egw']->accounts->get_type($userid) == 'g')
			{
				continue;	// dont notify rejected participants or groups
			}

			if ($userid != $GLOBALS['egw_info']['user']['account_id'] ||
				($userid == $GLOBALS['egw_info']['user']['account_id'] &&
					$user_prefs['calendar']['receive_own_updates']==1) ||
				$msg_type == MSG_ALARM)
			{
				$tfn = $tln = $lid = null; //cleanup of lastname and fullname (in case they are set in a previous loop)
				if (is_numeric($userid))
				{
					$preferences = new Api\Preferences($userid);
					$GLOBALS['egw_info']['user']['preferences'] = $part_prefs = $preferences->read_repository();
					$fullname = Api\Accounts::username($userid);
					$tfn = Api\Accounts::id2name($userid,'account_firstname');
					$tln = Api\Accounts::id2name($userid,'account_lastname');
				}
				else	// external email address: use Api\Preferences of event-owner, plus some hardcoded settings (eg. ical notification)
				{
					if (is_null($owner_prefs))
					{
						$preferences = new Api\Preferences($owner);
						$GLOBALS['egw_info']['user']['preferences'] = $owner_prefs = $preferences->read_repository();
						if (!empty($notify_externals)) $owner_prefs['calendar']['notify_externals'] = $notify_externals;
					}
					$part_prefs = $owner_prefs;
					$part_prefs['calendar']['receive_updates'] = $owner_prefs['calendar']['notify_externals'];
					$part_prefs['calendar']['update_format'] = 'ical';	// use ical format
					$fullname = $res_info && !empty($res_info['name']) ? $res_info['name'] : $userid;
				}
				$m_type = $msg_type;
				if (!$ignore_prefs && !$this->update_requested($userid, $part_prefs, $m_type, $old_event, $new_event, $role,
					$event['participants'][$GLOBALS['egw_info']['user']['account_id']]))
				{
					//error_log("--> Update/notification NOT requested / ignored");
					continue;
				}
				$action = $notify_msg = null;
				$method = $this->msg_type2ical_method($m_type, $action, $notify_msg, $user_prefs['calendar']);

				if ($lang !== $part_prefs['common']['lang'])
				{
					Api\Translation::init();
					$lang = $part_prefs['common']['lang'];
				}

				// Since we're running from cron, make sure notifications uses user's theme (for images)
				$GLOBALS['egw_info']['server']['template_set'] = $GLOBALS['egw_info']['user']['preferences']['common']['template_set'];

				$event_arr = null;
				$details = $this->_get_event_details(isset($cleared_event) ? $cleared_event : $event,
					$action, $event_arr, $disinvited);
				$details['fullname'] = is_numeric($user) ? Api\Accounts::username($user) : $fullname;
				$details['to-fullname'] = $fullname;
				$details['to-firstname'] = isset($tfn)? $tfn: '';
				$details['to-lastname'] = isset($tln)? $tln: '';

				// event is in user-time of current user, now we need to calculate the tz-difference to the notified user and take it into account
				if (!isset($part_prefs['common']['tz'])) $part_prefs['common']['tz'] = $GLOBALS['egw_info']['server']['server_timezone'];
				try
				{
					$timezone = new DateTimeZone($part_prefs['common']['tz']);
				}
				catch(Exception $e)
				{
					$timezone = new DateTimeZone($GLOBALS['egw_info']['server']['server_timezone']);
				}
				$timeformat = $part_prefs['common']['timeformat'];
				switch($timeformat)
				{
			  		case '24':
						$timeformat = 'H:i';
						break;
					case '12':
						$timeformat = 'h:i a';
						break;
				}
				$timeformat = $part_prefs['common']['dateformat'] . ', ' . $timeformat;

				// Set dates:
				// $details in "preference" format, $cleared_event as DateTime so calendar_ical->exportVCal() gets
				// the times right, since it assumes a timestamp is in server time
				$startdate->setTimezone($timezone);
				$details['startdate'] = $startdate->format($timeformat);
				$cleared_event['start'] = $startdate;

				$enddate->setTimezone($timezone);
				$details['enddate'] = $enddate->format($timeformat);
				$cleared_event['end'] = $enddate;

				$modified->setTimezone($timezone);
				$details['updated'] = $modified->format($timeformat) . ', ' . Api\Accounts::username($event['modifier']);
				$cleared_event['updated'] = $modified;

				// Current date doesn't need to go into the cleared event, just for details
				$date->setTimezone($timezone);
				$details['date'] = $date->format($timeformat);

				if ($old_event != False)
				{
					$olddate->setTimezone($timezone);
					$details['olddate'] = $olddate->format($timeformat);
				}
				// generate a personal videoconference url, if we need one
				if (!empty($event['##videoconference']) && !calendar_hooks::isVideoconferenceDisabled())
				{
					$avatar = new Api\Contacts\Photo(is_numeric($userid) ? "account:$userid" :
						(isset($res_info) && $res_info['type'] === 'c' ? $res_info['res_id'] : $userid),
						// disable sharing links currently, as sharing links from a different EGroupware user destroy the session
						true);

					$details['videoconference'] = EGroupware\Status\Videoconference\Call::genMeetingUrl($event['##videoconference'], [
						'title' => $event['title'],
						'name' => $fullname,
						'email' => is_numeric($userid) ? Api\Accounts::id2name($userid, 'account_email') : $userid,
						'avatar' => (string)$avatar,
						'account_id' => $userid,
						'cal_id' => $details['id'],
						'notify_only' => true
					], ['participants' =>array_filter($event['participants'], function($key){return is_numeric($key);}, ARRAY_FILTER_USE_KEY)], $startdate, $enddate);
					$event_arr['videoconference'] = [
						'field' => lang('Video Conference'),
						'data'  => $details['videoconference'],
					];
					// hack to add videoconference-url to ical, only if description was NOT cleared
					if (isset($cleared_event['description']))
					{
						$cleared_event['description'] = lang('Video conference').': '.$details['videoconference']."\n\n".$event['description'];
					}
				}
				//error_log(__METHOD__."() userid=$userid, timezone=".$timezone->getName().", startdate=$details[startdate], enddate=$details[enddate], updated=$details[updated], olddate=$details[olddate]");

				list($subject,$notify_body) = explode("\n",$GLOBALS['egw']->preferences->parse_notify($notify_msg,$details),2);
				// alarm is NOT an iCal method, therefore we have to use extened (no iCal)
				switch($msg_type == MSG_ALARM ? 'extended' : $part_prefs['calendar']['update_format'])
				{
					case 'ical':
						if (is_null($ics) || $m_type != $msg_type || $event['##videoconference'])	// need different ical for organizer notification or videoconference join urls
						{
							$calendar_ical = new calendar_ical();
							$calendar_ical->setSupportedFields('full');	// full iCal fields+event TZ
							// we need to pass $event[id] so iCal class reads event again,
							// as event is in user TZ, but iCal class expects server TZ!
							$ics = $calendar_ical->exportVCal([$cleared_event],
								'2.0', $method, $cleared_event['recur_date'] ?? null,
								'', 'utf-8', $method == 'REPLY' ? $user : 0
							);
							unset($calendar_ical);
						}
						$attachment = array(
							'string' => $ics,
							'filename' => 'cal.ics',
							'encoding' => '8bit',
							'type' => 'text/calendar; method='.$method,
						);
						if ($m_type != $msg_type) unset($ics);
						$subject = isset($cleared_event) ? $cleared_event['title'] : $event['title'];
						// fall through
					case 'extended':

						$details_body = lang('Event Details follow').":\n";
						foreach($event_arr as $key => $val)
						{
							if(!empty($details[$key]))
							{
								switch($key)
								{
							 		case 'access':
									case 'priority':
									case 'link':
									case 'description':
									case 'title':
										break;
									default:
										$details_body .= sprintf("%-20s %s\n",$val['field'].':',$details[$key]);
										break;
							 	}
							}
						}
						break;
				}
				// send via notification_app
				if($GLOBALS['egw_info']['apps']['notifications']['enabled'])
				{
					try {
						//error_log(__METHOD__."() notifying $userid from $senderid: $subject");
						$notification = new notifications();
						$notification->set_receivers(array($userid));
						$notification->set_sender($senderid);
						$notification->set_subject($subject);
						// as we want ical body to be just description, we can NOT set links, as they get appended to body
						if ($part_prefs['calendar']['update_format'] != 'ical')
						{
							$notification->set_message($notify_body."\n\n".$details['description']."\n\n".$details_body);
							$notification->set_links(array($details['link_arr']));
						}
						else
						{
							// iCal: description need to be separated from body by fancy separator
							$notification->set_message($notify_body."\n\n".$details_body."\n*~*~*~*~*~*~*~*~*~*\n\n".$details['description']);
						}
						// popup notifiactions: set subject, different message (without separator) and (always) links
						$notification->set_popupsubject($subject);

						if ($method =='REQUEST')
						{
							// Add ACCEPT|REHECT|TENTATIVE actions
							$notification->set_popupdata('calendar', array(
								'event_id' => $event['id'],
								'user_id' => $userid,
								'type' => $m_type,
								'id' => $event['id'],
								'app' => 'calendar',
								'videoconference' => $details['videoconference'],
							));
						}
						if ($m_type === MSG_ALARM)
						{
							$notification->set_popupdata('calendar',
								array('egw_pr_notify' => 1,
									'type' => $m_type,
									'videoconference' => $details['videoconference'],
									'account_id' => $senderid,
									'name' =>  Api\Accounts::username($senderid)
								)
								+ ($alarm ? ['alarm-offset' => (int)$alarm['offset']] : []));
						}
						$notification->set_popupmessage($subject."\n\n".$notify_body."\n\n".$details['description']."\n\n".$details_body."\n\n");
						$notification->set_popuplinks(array($details['link_arr']+array('app'=>'calendar')));

						if(!empty($attachment)) { $notification->set_attachments(array($attachment)); }
						$notification->send();
						$errors = notifications::errors(true);
					}
					catch (Exception $exception) {
						$errors = [$exception->getMessage()];
						continue;
					}
				}
				else
				{
					$errors = [lang('Can not send any notifications because notifications app is not installed!')];
				}
				foreach($errors as $error)
				{
					error_log(__METHOD__."() Error notifying $userid from $senderid: $subject: $error");
					// send notification errors via push to current user (not session, as alarms send via async job have none!)
					(new Api\Json\Push($GLOBALS['egw_info']['user']['account_id']))->message(
						lang('Error notifying %1', !is_numeric($userid) ? $userid :
							Api\Accounts::id2name($userid, 'account_fullname').' <'.Api\Accounts::id2name($userid, 'account_email').'>').
						"\n".$subject."\n".$error, 'error');
				}
			}
		}
		// restore the enviroment (preferences->read_repository() sets the timezone!)
		$GLOBALS['egw_info']['user'] = $temp_user;
		if ($GLOBALS['egw']->preferences->account_id != $temp_user['account_id'])
		{
			$GLOBALS['egw']->preferences->__construct($temp_user['account_id']);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
			//echo "<p>".__METHOD__."() restored enviroment of #$temp_user[account_id] $temp_user[account_fullname]: tz={$GLOBALS['egw_info']['user']['preferences']['common']['tz']}</p>\n";
		}
		else
		{
			// Loading other user's preferences can change current user's tz
			$GLOBALS['egw']->preferences->check_set_tz_offset();
		}
		if ($lang !== $GLOBALS['egw_info']['user']['preferences']['common']['lang'])
		{
			Api\Translation::init();
		}
		// restore timezone, in case we had to reset it to server-timezone
		if (!empty($restore_tz)) date_default_timezone_set($restore_tz);

		return true;
	}

	function get_update_message($event,$added)
	{
		$nul = null;
		$details = $this->_get_event_details($event,$added ? lang('Added') : lang('Modified'),$nul);

		$notify_msg = $this->cal_prefs[$added || empty($this->cal_prefs['notifyModified']) ? 'notifyAdded' : 'notifyModified'];

		return explode("\n",$GLOBALS['egw']->preferences->parse_notify($notify_msg,$details),2);
	}

	/**
	 * Function called via async service, when an alarm is to be send
	 *
	 * @param array $alarm array with keys owner, cal_id, all
	 * @return boolean
	 */
	function send_alarm($alarm)
	{
		//echo "<p>bocalendar::send_alarm("; print_r($alarm); echo ")</p>\n";
		$GLOBALS['egw_info']['user']['account_id'] = $this->owner = $alarm['owner'];

		$event_time_user = Api\DateTime::server2user($alarm['time'] + $alarm['offset']);	// alarm[time] is in server-time, read requires user-time
		if (!$alarm['owner'] || !$alarm['cal_id'] || !($event = $this->read($alarm['cal_id'],$event_time_user)))
		{
			return False;	// event not found
		}
		if ($alarm['all'])
		{
			$to_notify = $event['participants'];
		}
		elseif ($this->check_perms(Acl::READ,$event))	// checks agains $this->owner set to $alarm[owner]
		{
			$to_notify[$alarm['owner']] = 'A';
		}
		else
		{
			return False;	// no rights
		}
		// need to load calendar translations and set currentapp, so calendar can reload a different lang
		Api\Translation::add_app('calendar');
		$GLOBALS['egw_info']['flags']['currentapp'] = 'calendar';

		$ret = $this->_send_update(MSG_ALARM, $to_notify, $event, False, $alarm['owner'], $alarm);

		// create a new alarm for recuring events for the next event, if one exists
		if (!empty($event['recur_type']) && ($event = $this->read($alarm['cal_id'],$event_time_user+1)))
		{
			$alarm['time'] = $this->date2ts($event['start']) - $alarm['offset'];
			unset($alarm['times']);
			unset($alarm['next']);
			unset($alarm['keep_time']);	// need to remove the keep_time, as otherwise the alarm would be deleted automatically
			//error_log(__METHOD__."() moving alarm to next recurrence ".array2string($alarm));
			$this->save_alarm($alarm['cal_id'], $alarm, false);	// false = do NOT update timestamp, as nothing changed for iCal clients
		}
		return $ret;
	}

	/**
	 * saves an event to the database, does NOT do any notifications, see calendar_boupdate::update for that
	 *
	 * This methode converts from user to server time and handles the insertion of users and dates of repeating events
	 *
	 * @param array $event
	 * @param boolean $ignore_acl =false should we ignore the acl
	 * @param boolean $updateTS =true update the content history of the event
	 * Please note: you should ALLWAYS update timestamps, as they are required for sync!
	 * @return int|boolean $cal_id > 0 or false on error (eg. permission denied)
	 */
	function save($event,$ignore_acl=false,$updateTS=true)
	{
		//error_log(__METHOD__.'('.array2string($event).", $ignore_acl, $updateTS)");

		// check if user has the permission to update / create the event
		if (!$ignore_acl && (!empty($event['id']) && !$this->check_perms(Acl::EDIT,$event['id']) ||
			empty($event['id']) && !$this->check_perms(Acl::EDIT,0,$event['owner']) &&
			!$this->check_perms(Acl::ADD,0,$event['owner'])))
		{
			return false;
		}

		if (!empty($event['id']))
		{
			// invalidate the read-cache if it contains the event we store now
			if ($event['id'] == self::$cached_event['id']) self::$cached_event = array();
			$old_event = $this->read($event['id'], $event['recurrence'], $ignore_acl, 'server');
		}
		else
		{
			$old_event = null;
		}
		if (!isset($event['whole_day'])) $event['whole_day'] = $this->isWholeDay($event);

		$this->check_reset_statuses($event, $old_event);

		// set recur-enddate/range-end to real end-date of last recurrence
		if (!empty($event['recur_type']) && $event['recur_enddate'] && $event['start'])
		{
			$event['recur_enddate'] = new Api\DateTime($event['recur_enddate'], calendar_timezones::DateTimeZone($event['tzid']));
			$event['recur_enddate']->setTime(23,59,59);
			$rrule = calendar_rrule::event2rrule($event, true, Api\DateTime::$user_timezone->getName());
			$rrule->rewind();
			$enddate = $rrule->current();
			do
			{
				$rrule->next_no_exception();
				$occurrence = $rrule->current();
			}
			while ($rrule->validDate($event['whole_day']) && ($enddate = $occurrence));
			$enddate->modify(($event['end'] - $event['start']).' second');
			$event['recur_enddate'] = $save_event['recur_enddate'] = $enddate->format('ts');
			//error_log(__METHOD__."($event[title]) start=".Api\DateTime::to($event['start'],'string').', end='.Api\DateTime::to($event['end'],'string').', range_end='.Api\DateTime::to($event['recur_enddate'],'string'));
		}

		$save_event = $event;
		if ($event['whole_day'])
		{
			if (!empty($event['start']))
			{
				$time = $this->so->startOfDay(new Api\DateTime($event['start'], Api\DateTime::$user_timezone));
				$event['start'] = Api\DateTime::to($time, 'ts');
				$save_event['start'] = $time;
			}
			if (!empty($event['end']))
			{
				$time = new Api\DateTime($event['end'], Api\DateTime::$user_timezone);

				// Check to see if switching timezones changes the date, we'll need to adjust for that
				$end_event_timezone = clone $time;
				$time->setServer();
				$delta = (int)$end_event_timezone->format('z') - (int)$time->format('z');
				$time->add("$delta days");

				$time->setTime(23, 59, 59);
				$event['end'] = Api\DateTime::to($time, 'ts');
				$save_event['end'] = $time;
			}
			if (!empty($event['recurrence']))
			{
				$time = $this->so->startOfDay(new Api\DateTime($event['recurrence'], Api\DateTime::$user_timezone));
				$event['recurrence'] = Api\DateTime::to($time, 'ts');
			}
			if (!empty($event['recur_enddate']))
			{
				// all-day events are handled in server time, but here (BO) it's in user time
				$time = new Api\DateTime($event['recur_enddate'], Api\DateTime::$user_timezone);
				$time->setTime(23, 59, 59);
				// Check to see if switching timezones changes the date, we'll need to adjust for that
				$enddate_event_timezone = clone $time;
				$time->setServer();
				$delta = (int)$enddate_event_timezone->format('z') - (int)$time->format('z');
				$time->add("$delta days");

				//$time->setServer();
				$time->setTime(23, 59, 59);

				$event['recur_enddate'] = $save_event['recur_enddate'] = $time;
			}
			$timestamps = array('modified','created');
			// all-day events are handled in server time
		//	$event['tzid'] = $save_event['tzid'] = Api\DateTime::$server_timezone->getName();
		}
		else
		{
			$timestamps = array('start','end','modified','created','recur_enddate','recurrence');
		}
		// we run all dates through date2ts, to adjust to server-time and the possible date-formats
		foreach($timestamps as $ts)
		{
			// we convert here from user-time to timestamps in server-time!
			if (isset($event[$ts])) $event[$ts] = $event[$ts] ? $this->date2ts($event[$ts],true) : 0;
		}
		// convert tzid name to integer tz_id, of set user default
		if (empty($event['tzid']) || !($event['tz_id'] = calendar_timezones::tz2id($event['tzid'])))
		{
			$event['tz_id'] = calendar_timezones::tz2id($event['tzid'] = Api\DateTime::$user_timezone->getName());
		}
		// same with the recur exceptions
		if (isset($event['recur_exception']) && is_array($event['recur_exception']))
		{
			foreach($event['recur_exception'] as &$date)
			{
				if ($event['whole_day'])
				{
					$time = $this->so->startOfDay(new Api\DateTime($date, Api\DateTime::$user_timezone));
					$date = Api\DateTime::to($time, 'ts');
				}
				else
				{
					$date = $this->date2ts($date,true);
				}
			}
			unset($date);
		}
		// same with the alarms
		if (isset($event['alarm']) && is_array($event['alarm']) && isset($event['start']))
		{
			// Expand group invitations so we don't lose individual alarms
			$expanded = $event;
			$this->enum_groups($expanded);
			foreach($event['alarm'] as $id => &$alarm)
			{
				if(!empty($alarm['time']))
				{
					$alarm['time'] = $this->date2ts($alarm['time'], true);    // user to server-time
				}

				// remove alarms belonging to not longer existing or rejected participants
				if (!empty($alarm['owner']) && isset($expanded['participants']))
				{
					// Don't auto-delete alarm if for all users
					if(!empty($alarm['all'])) continue;

					$status = $expanded['participants'][$alarm['owner']];
					if (!isset($status) || calendar_so::split_status($status) === 'R')
					{
						unset($event['alarm'][$id]);
						$this->so->delete_alarm($id);
						//error_log(__LINE__.': '.__METHOD__."(".array2string($event).") deleting alarm=".array2string($alarm).", $status=".array2string($alarm));
					}
				}
				else if (empty($alarm['owner']))
				{
					$alarm['owner'] = $event['owner'];
				}
			}
		}
		// update all existing alarm times, in case alarm got moved and alarms are not include in $event
		if ($old_event && is_array($old_event['alarm']) && isset($event['start']))
		{
			foreach($old_event['alarm'] as $id => &$alarm)
			{
				if (!isset($event['alarm'][$id]))
				{
					$alarm['time'] = $event['start'] - $alarm['offset'];
					if ($alarm['time'] < time()) calendar_so::shift_alarm($event, $alarm);
						// remove (not store) alarms belonging to not longer existing or rejected participants
					$status = isset($event['participants']) ? $event['participants'][$alarm['owner']] :
						$old_event['participants'][$alarm['owner']];
					if (!$alarm['owner'] || isset($status) && calendar_so::split_status($status) !== 'R')
					{
						$this->so->save_alarm($event['id'], $alarm);
						//error_log(__LINE__.': '.__METHOD__."() so->save_alarm($event[id], ".array2string($alarm).")");
					}
					else
					{
						$this->so->delete_alarm($id);
						//error_log(__LINE__.': '.__METHOD__."(".array2string($event).") deleting alarm=".array2string($alarm).", $status=".array2string($alarm));
					}
				}
			}
		}

		// you should always update modification time (ctag depends on it!)
		if ($updateTS)
		{
			$event['modified'] = $save_event['modified'] = $this->now;
			$event['modifier'] = $save_event['modifier'] = $this->user;
		}

		if (empty($event['id']) && (!isset($event['created']) || $event['created'] > $this->now))
		{
			$event['created'] = $save_event['created'] = $this->now;
			$event['creator'] = $save_event['creator'] = $this->user;
		}
		$set_recurrences = $old_event ? abs(Api\DateTime::to($event['recur_enddate'] ?? null, 'utc') - Api\DateTime::to($old_event['recur_enddate'] ?? null, 'utc')) > 1 ||
			count($old_event['recur_exception'] ?? []) != count($event['recur_exception'] ?? []) : false;
		$set_recurrences_start = 0;
		if (($cal_id = $this->so->save($event,$set_recurrences,$set_recurrences_start,0,$event['etag'])) && $set_recurrences && !empty($event['recur_type']))
		{
			$save_event['id'] = $cal_id;
			// unset participants to enforce the default stati for all added recurrences
			unset($save_event['participants']);
			$this->set_recurrences($save_event, $set_recurrences_start);
		}

		// create links for new participants from addressbook, if configured
		if($cal_id && !empty($GLOBALS['egw_info']['server']['link_contacts']) && !empty($save_event['participants']))
		{
			foreach($save_event['participants'] as $uid => $status)
			{
				$user_type = $user_id = null;
				calendar_so::split_user($uid, $user_type, $user_id);
				if($user_type == 'c' && (!$old_event || !isset($old_event['participants'][$uid])))
				{
					Link::link('calendar', $cal_id, 'addressbook', $user_id);
				}
			}
		}

		// Update history
		$tracking = new calendar_tracking($this);
		if(empty($event['id']) && !empty($cal_id))
		{
			$event['id'] = $cal_id;
			$save_event['id'] = $cal_id;
		}
		// we run all dates through date2ts, to adjust to server-time and the possible date-formats
		// This is done here to avoid damaging the actual event when saving, but the old event is in server-time
		foreach ($timestamps as $ts)
		{
			// we convert here from user-time to timestamps in server-time!
			if (isset($save_event[$ts])) $save_event[$ts] = $save_event[$ts] ? calendar_bo::date2ts($save_event[$ts], true) : 0;
		}
		foreach(['start', 'end', 'recur_enddate'] as $ts)
		{
			if(isset($save_event[$ts]) && is_object($save_event[$ts]))
			{
				$save_event[$ts] = $save_event[$ts]->format('ts');
			}
		}
		$tracking->track($save_event, $old_event ?: null);

		return $cal_id;
	}

	/**
	 * Check if the current user has the necessary ACL rights to change the status of $uid
	 *
	 * For contacts we use edit rights of the owner of the event (aka. edit rights of the event).
	 *
	 * @param int|string $uid account_id or 1-char type-identifer plus id (eg. c15 for addressbook entry #15)
	 * @param array|int $event event array or id of the event
	 * @return boolean
	 */
	function check_status_perms($uid,$event)
	{
		if ($uid[0] == 'c' || $uid[0] == 'e')	// for contact we use the owner of the event
		{
			if (!is_array($event) && !($event = $this->read($event))) return false;

			return $this->check_perms(Acl::EDIT,0,$event['owner']);
		}
		// check if we have a category Acl for the event or not (null)
		$access = $this->check_cat_acl(self::CAT_ACL_STATUS,$event);
		if (!is_null($access))
		{
			return $access;
		}
		// no access or denied access because of category acl --> regular check
		if (!is_numeric($uid))	// this is eg. for resources (r123)
		{
			$resource = $this->resource_info($uid);

			return Acl::EDIT & $resource['rights'];
		}
		if (!is_array($event) && !($event = $this->read($event))) return false;

		// regular user and groups (need to check memberships too)
		if (!isset($event['participants'][$uid]))
		{
			$memberships = $GLOBALS['egw']->accounts->memberships($uid,true);
		}
		$memberships[] = $uid;
		return array_intersect($memberships, array_keys($event['participants'] ?? [])) && $this->check_perms(Acl::EDIT,0,$uid);
	}

	/**
	 * Check if current user has a certain right on the categories of an event
	 *
	 * Not having the given right for a single category, means not having it!
	 *
	 * @param int $right self::CAT_ACL_{ADD|STATUS}
	 * @param int|array $event
	 * @return boolean true if use has the right, false if not
	 * @return boolean false=access denied because of cat acl, true access granted because of cat acl,
	 * 	null = cat has no acl
	 */
	function check_cat_acl($right,$event)
	{
		if (!is_array($event)) $event = $this->read($event);

		$ret = null;
		if ($event['category'])
		{
			foreach(is_array($event['category']) ? $event['category'] : explode(',',$event['category']) as $cat_id)
			{
				$access = self::has_cat_right($right,$cat_id,$this->user);
				if ($access === true)
				{
					$ret = true;
					break;
				}
				if ($access === false)
				{
					$ret = false;	// cat denies access --> check further cats
				}
			}
		}
		//echo "<p>".__METHOD__."($event[id]: $event[title], $right) = ".array2string($ret)."</p>\n";
		return $ret;
	}

	/**
	 * Array with $cat_id => $rights pairs for current user (no entry means, cat is not limited by ACL!)
	 *
	 * @var array
	 */
	private static $cat_rights_cache;

	/**
	 * Get rights for a given category id
	 *
	 * @param int $cat_id =null null to return array with all cats
	 * @return array with account_id => right pairs
	 */
	public static function get_cat_rights($cat_id=null)
	{
		if (!isset(self::$cat_rights_cache))
		{
			self::$cat_rights_cache = Api\Cache::getSession('calendar','cat_rights',
				array($GLOBALS['egw']->acl,'get_location_grants'),array('L%','calendar'));
		}
		//echo "<p>".__METHOD__."($cat_id) = ".array2string($cat_id ? self::$cat_rights_cache['L'.$cat_id] : self::$cat_rights_cache)."</p>\n";
		return $cat_id ? self::$cat_rights_cache['L'.$cat_id] : self::$cat_rights_cache;
	}

	/**
	 * Set rights for a given single category and user
	 *
	 * @param int $cat_id
	 * @param int $user
	 * @param int $rights self::CAT_ACL_{ADD|STATUS} or'ed together
	 */
	public static function set_cat_rights($cat_id,$user,$rights)
	{
		//echo "<p>".__METHOD__."($cat_id,$user,$rights)</p>\n";
		if (!isset(self::$cat_rights_cache)) self::get_cat_rights($cat_id);

		if ((int)$rights != (int)self::$cat_rights_cache['L'.$cat_id][$user])
		{
			if ($rights)
			{
				self::$cat_rights_cache['L'.$cat_id][$user] = $rights;
				$GLOBALS['egw']->acl->add_repository('calendar','L'.$cat_id,$user,$rights);
			}
			else
			{
				unset(self::$cat_rights_cache['L'.$cat_id][$user]);
				if (!self::$cat_rights_cache['L'.$cat_id]) unset(self::$cat_rights_cache['L'.$cat_id]);
				$GLOBALS['egw']->acl->delete_repository('calendar','L'.$cat_id,$user);
			}
			Api\Cache::setSession('calendar','cat_rights',self::$cat_rights_cache);
		}
	}

	/**
	 * Check if current user has a given right on a category (if it's restricted!)
	 *
	 * @param int $cat_id
	 * @return boolean false=access denied because of cat acl, true access granted because of cat acl,
	 * 	null = cat has no acl
	 */
	public static function has_cat_right($right,$cat_id,$user)
	{
		static $cache=null;

		if (!isset($cache[$cat_id]))
		{
			$all = $own = 0;
			$cat_rights = self::get_cat_rights($cat_id);
			if (!is_null($cat_rights))
			{
				static $memberships=null;
				if (is_null($memberships))
				{
					$memberships = $GLOBALS['egw']->accounts->memberships($user,true) ?: [];
					$memberships[] = $user;
				}
				foreach($cat_rights as $uid => $value)
				{
					$all |= $value;
					if (in_array($uid,$memberships)) $own |= $value;
				}
			}
			foreach(array(self::CAT_ACL_ADD,self::CAT_ACL_STATUS) as $mask)
			{
				$cache[$cat_id][$mask] = !($all & $mask) ? null : !!($own & $mask);
			}
		}
		//echo "<p>".__METHOD__."($right,$cat_id) all=$all, own=$own returning ".array2string($cache[$cat_id][$right])."</p>\n";
		return $cache[$cat_id][$right];
	}

	/**
	 * set the status of one participant for a given recurrence or for all recurrences since now (includes recur_date=0)
	 *
	 * @param int|array $event event-array or id of the event
	 * @param string|int $uid account_id or 1-char type-identifer plus id (eg. c15 for addressbook entry #15)
	 * @param int|char $status numeric status (defines) or 1-char code: 'R', 'U', 'T' or 'A'
	 * @param int $recur_date =0 date to change, or 0 = all since now
	 * @param boolean $ignore_acl =false do not check the permisions for the $uid, if true
	 * @param boolean $updateTS =true update the content history of the event
	 * DEPRECATED: we always (have to) update timestamp, as they are required for sync!
	 * @param boolean|"NOPUSH" $skip_notification =false true: send NO notifications, default false = send them,
	 *  "NOPUSH": also do NOT send push notifications / call Link::notifiy(), which still happens for true
	 * @return int number of changed recurrences
	 */
	function set_status($event,$uid,$status,$recur_date=0,$ignore_acl=false,$updateTS=true,$skip_notification=false)
	{
		unset($updateTS);

		$cal_id = is_array($event) ? $event['id'] : $event;
		//echo "<p>calendar_boupdate::set_status($cal_id,$uid,$status,$recur_date)</p>\n";
		if (!$cal_id || (!$ignore_acl && !$this->check_status_perms($uid,$event)))
		{
			return false;
		}
		$quantity = $role = null;
		calendar_so::split_status($status, $quantity, $role);
		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				"($cal_id, $uid, $status, $recur_date)\n",3,$this->logfile);
		}
		$old_event = $this->read($cal_id, $recur_date, $ignore_acl, 'server');
		if (($Ok = $this->so->set_status($cal_id,is_numeric($uid)?'u':$uid[0],
				is_numeric($uid)?$uid:substr($uid,1),$status,
				$recur_date?$this->date2ts($recur_date,true):0,$role)))
		{
			if ($status == 'R')	// remove alarms belonging to rejected participants
			{
				foreach(is_array($event) && isset($event['alarm']) ? $event['alarm'] : $old_event['alarm'] as $id => $alarm)
				{
					if ((string)$alarm['owner'] === (string)$uid)
					{
						$this->so->delete_alarm($id);
						//error_log(__LINE__.': '.__METHOD__."(".array2string($event).", '$uid', '$status', ...) deleting alarm=".array2string($alarm).", $status=".array2string($alarm));
					}
				}
			}

			static $status2msg = array(
				'R' => MSG_REJECTED,
				'T' => MSG_TENTATIVE,
				'A' => MSG_ACCEPTED,
				'D' => MSG_DELEGATED,
			);
			// Reset cached event
			static::$cached_event = array();

			if (isset($status2msg[$status]) && !$skip_notification)
			{
				if (!is_array($event)) $event = $this->read($cal_id);
				if (isset($recur_date)) $event = $this->read($event['id'],$recur_date); //re-read the actually edited recurring event
				$user_id = is_numeric($uid) ? (int)$uid : $uid;
				$this->send_update($status2msg[$status],$event['participants'],$event, null, $user_id);
			}

			// Update history
			if (($event = $this->read($cal_id, $recur_date, $ignore_acl, 'server')))
			{
				$tracking = new calendar_tracking($this);
				$tracking->track($event, $old_event ?: null);
			}
			// notify the link-class about the update, as other apps may be subscribed to it
			if ($skip_notification !== "NOPUSH")
			{
				Link::notify_update('calendar', $cal_id, $event, "update");
			}
		}
		return $Ok;
	}

	/**
	 * update the status of all participant for a given recurrence or for all recurrences since now (includes recur_date=0)
	 *
	 * @param array $new_event event-array with the new stati
	 * @param array $old_event event-array with the old stati
	 * @param int $recur_date =0 date to change, or 0 = all since now
	 * @param boolean|"NOPUSH" $skip_notification =false true: send NO notifications, default false = send them,
	 *  "NOPUSH": also do NOT send push notifications / call Link::notifiy(), which still happens for true
	 */
	function update_status($new_event, $old_event , $recur_date=0, $skip_notification=false)
	{
		if (!isset($new_event['participants'])) return;

		// check the old list against the new list
		foreach ($old_event['participants'] as $userid => $status)
  		{
            if (!isset($new_event['participants'][$userid])){
            	// Attendee will be deleted this way
            	$new_event['participants'][$userid] = 'G';
            }
            elseif ($new_event['participants'][$userid] == $status)
            {
            	// Same status -- nothing to do.
            	unset($new_event['participants'][$userid]);
            }
		}
		// write the changes
		foreach ($new_event['participants'] as $userid => $status)
		{
			$this->set_status($old_event, $userid, $status, $recur_date, true, false,$skip_notification);
		}

		// notify the link-class about the update, as other apps may be subscribed to it
		if ($skip_notification !== "NOPUSH")
		{
			Link::notify_update('calendar',$new_event['id'],$new_event,"update");
		}
  }

	/**
	 * deletes an event
	 *
	 * @param int $cal_id id of the event to delete
	 * @param int $recur_date =0 if a single event from a series should be deleted, its date
	 * @param boolean $ignore_acl =false true for no ACL check, default do ACL check
	 * @param boolean|array|"NOPUSH" $skip_notification =false or array with uid to skip eg. [5,'eemail@example.org']
	 *  "NOPUSH": also do NOT send push notifications / call Link::notifiy(), which still happens for true
	 * @param boolean $delete_exceptions =true true: delete, false: keep exceptions (with new UID)
	 * @param int &$exceptions_kept=null on return number of kept exceptions
	 * @return boolean true on success, false on error (usually permission denied)
	 */
	function delete($cal_id, $recur_date=0, $ignore_acl=false, $skip_notification=false,
		$delete_exceptions=true, &$exceptions_kept=null)
	{
		//error_log(__METHOD__."(cal_id=$cal_id, recur_date=$recur_date, ignore_acl=$ignore_acl, skip_notifications=$skip_notification)");
		if (!($event = $this->read($cal_id,$recur_date)) ||
			!$ignore_acl && !$this->check_perms(Acl::DELETE,$event))
		{
			return false;
		}

		// Don't send notification if the event has already been deleted
		if (!$event['deleted'] && (!$skip_notification || is_array($skip_notification)))
		{
			$to_notify = !is_array($skip_notification) ? $event['participants'] :
				array_diff_key($event['participants'], array_flip($skip_notification));
			$this->send_update(MSG_DELETED, $to_notify, $event);
		}

		if (!$recur_date || empty($event['recur_type']))
		{
			$config = Api\Config::read('phpgwapi');
			if ($event['deleted'])
			{
				$this->so->delete($cal_id);

				// delete all links to the event
				Link::unlink(0,'calendar',$cal_id);
			}
			else
			{
				// mark all links to the event as deleted, but keep them
				Link::unlink(0,'calendar',$cal_id,'','','',true);

				$event['deleted'] = $this->now;
				$this->save($event, $ignore_acl);
				// Actually delete alarms
				if (isset($event['alarm']) && is_array($event['alarm']))
				{
					foreach($event['alarm'] as $id => $alarm)
					{
						$this->delete_alarm($id, true);
					}
				}
			}

			// delete or keep (with new uid) exceptions of a recurring event
			if (!empty($event['recur_type']))
			{
				$exceptions_kept = 0;
				foreach ($this->so->get_related($event['uid']) as $id)
				{
					if ($delete_exceptions)
					{
						$this->delete($id, 0, $ignore_acl, true);
					}
					else
					{
						if (!($exception = $this->read($id))) continue;
						$exception['uid'] = Api\CalDAV::generate_uid('calendar', $id);
						$exception['reference'] = $exception['recurrence'] = 0;
						$msg = null;
						$this->update($exception, true, true, false, true, $msg, true);
						++$exceptions_kept;
					}
				}
			}
		}
		else	// delete an exception
		{
			// check if deleted recurrance has alarms (because it's the next recurrance) --> move it to next recurrance
			if ($event['alarm'])
			{
				$next_recurrance = null;
				foreach($event['alarm'] as &$alarm)
				{
					if (($alarm['time'] == $recur_date) || ($alarm['time']+$alarm['offset'] == $recur_date))
					{
						//error_log(__METHOD__.__LINE__.'->'.array2string($recur_date));
						//error_log(__METHOD__.__LINE__.array2string($event));
						if (is_null($next_recurrance))
						{
							$checkdate = $recur_date;
							//if ($alarm['time']+$alarm['offset'] == $recur_date) $checkdate = $recur_date + $alarm['offset'];
							if (($e = $this->read($cal_id,$checkdate+1)))
							{
								$next_recurrance = $this->date2ts($e['start']);
							}
						}
						$alarm['time'] = $this->date2ts($next_recurrance, true);	// user to server-time
						$alarm['cal_id'] = $cal_id;
						unset($alarm['times']);
						unset($alarm['next']);
						$this->so->save_alarm($event['id'], $alarm);
					}
				}
			}
			// need to read series master, as otherwise existing exceptions will be lost!
			$recur_date = $this->date2ts($event['start']);
			//if ($event['alarm']) $alarmbuffer = $event['alarm'];
			$event = $this->read($cal_id);
			//if (isset($alarmbuffer)) $event['alarm'] = $alarmbuffer;
			$event['recur_exception'][] = $recur_date;
			$this->save($event);// updates the content-history

			// for "real" push, need to push delete of recurrence
			if (!Api\Json\Push::onlyFallback() && $skip_notification !== "NOPUSH")
			{
				Api\Link::notify_update('calendar', $cal_id.':'.$recur_date, $event, 'delete');
			}
		}
		if ($event['reference'])
		{
			// evtl. delete recur_exception $event['recurrence'] from event with cal_id=$event['reference']
		}
		return true;
	}

	/**
	 * helper for send_update and get_update_message
	 * @internal
	 * @param array $event
	 * @param string $action
	 * @param array $event_arr
	 * @param array $disinvited
	 * @return array
	 */
	function _get_event_details($event,$action,&$event_arr,$disinvited=array())
	{
		$details = array(			// event-details for the notify-msg
			'id'          => $event['id'],
			'action'      => lang($action),
		);
		$event_arr = $this->event2array($event);
		foreach($event_arr as $key => $val)
		{
			if ($key == 'recur_type') $key = 'repetition';
			$details[$key] = $val['data'];
		}
		$details['participants'] = $details['participants'] ? implode("\n",$details['participants']) : '';

		$event_arr['link']['field'] = lang('URL');
		$eventStart_arr = $this->date2array($event['start']); // give this as 'date' to the link to pick the right recurrence for the participants state
		$link = $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=calendar.calendar_uiforms.edit&cal_id='.$event['id'].'&date='.$eventStart_arr['full'].'&no_popup=1&ajax=true';
		// if url is only a path, try guessing the rest ;-)
		if ($link[0] == '/') $link = Api\Framework::getUrl($link);
		$event_arr['link']['data'] = $details['link'] = $link;

		/* this is needed for notification-app
		 * notification-app creates the link individual for
		 * every user, so we must provide a neutral link-style
		 * if calendar implements tracking in near future, this part can be deleted
		 */
		$link_arr = array();
		$link_arr['text'] = $event['title'];
		$link_arr['view'] = array(	'menuaction' => 'calendar.calendar_uiforms.edit',
									'cal_id' => $event['id'],
									'date' => $eventStart_arr['full'],
									'ajax' => true
									);
		$link_arr['popup'] = '750x400';
		$details['link_arr'] = $link_arr;

		$dis = array();
		foreach($disinvited as $uid)
		{
			$dis[] = $this->participant_name($uid);
		}
		$details['disinvited'] = implode(', ',$dis);
		return $details;
	}

	/**
	 * create array with name, translated name and readable content of each attributes of an event
	 *
	 * old function, so far only used by send_update (therefor it's in bocalupdate and not bocal)
	 *
	 * @param array $event event to use
	 * @returns array of attributes with fieldname as key and array with the 'field'=translated name 'data' = readable content (for participants this is an array !)
	 */
	function event2array($event)
	{
		$var['title'] = Array(
			'field'		=> lang('Title'),
			'data'		=> $event['title']
		);

		$var['description'] = Array(
			'field'	=> lang('Description'),
			'data'	=> $event['description']
		);

		foreach(is_array($event['category']) ? $event['category'] : explode(',', $event['category']) as $cat_id)
		{
			$cat_string[] = stripslashes(Api\Categories::id2name($cat_id));
		}
		$var['category'] = Array(
			'field'	=> lang('Category'),
			'data'	=> implode(', ',$cat_string)
		);

		$var['location'] = Array(
			'field'	=> lang('Location'),
			'data'	=> $event['location']
		);

		$var['startdate'] = Array(
			'field'	=> lang('Start Date/Time'),
			'data'	=> $this->format_date($event['start']),
		);

		$var['enddate'] = Array(
			'field'	=> lang('End Date/Time'),
			'data'	=> $this->format_date($event['end']),
		);

		$pri = Array(
			0   => lang('None'),
			1	=> lang('Low'),
			2	=> lang('Normal'),
			3	=> lang('High')
		);
		$var['priority'] = Array(
			'field'	=> lang('Priority'),
			'data'	=> $pri[$event['priority']]
		);

		$var['owner'] = Array(
			'field'	=> lang('Owner'),
			'data'	=> Api\Accounts::username($event['owner'])
		);

		$var['updated'] = Array(
			'field'	=> lang('Updated'),
			'data'	=> $this->format_date($event['modtime'] ?? null).', '.Api\Accounts::username($event['modifier'])
		);

		$var['access'] = Array(
			'field'	=> lang('Access'),
			'data'	=> $event['public'] ? lang('Public') : lang('Private')
		);

		if (isset($event['participants']) && is_array($event['participants']) && !empty($event['participants']))
		{
			$participants = $this->participants($event,true);

			// fix external organiser to not be included as participant and shown as organiser
			foreach($event['participants'] as $uid => $status)
			{
				$role = $quantity = null;
				calendar_so::split_status($status, $quantity, $role);
				if ($role == 'CHAIR' && $status == 'D' && !is_numeric($uid))
				{
					$var['owner']['data'] = $this->participant_name($uid);
					unset($participants[$uid]);
					break;
				}
			}
		}

		$var['participants'] = Array(
			'field'	=> lang('Participants'),
			'data'	=> $participants
		);

		// Repeated Events
		$var['recur_type'] = Array(
			'field'	=> lang('Repetition'),
			'data'	=> (!empty($event['recur_type'])) ? $this->recure2string($event) : '',
		);
		return $var;
	}

	/**
	 * log all updates to a file
	 *
	 * @param array $event2save event-data before calling save
	 * @param array $event_saved event-data read back from the DB
	 * @param array $old_event =null event-data in the DB before calling save
	 * @param string $type ='update'
	 */
	function log2file($event2save,$event_saved,$old_event=null,$type='update')
	{
		if (!($f = fopen($this->log_file,'a')))
		{
			echo "<p>error opening '$this->log_file' !!!</p>\n";
			return false;
		}
		fwrite($f,$type.': '.Api\Accounts::username($this->user).': '.date('r')."\n");
		fwrite($f,"Time: time to save / saved time read back / old time before save\n");
		foreach(array('start','end') as $name)
		{
			fwrite($f,$name.': '.(isset($event2save[$name]) ? $this->format_date($event2save[$name]) : 'not set').' / '.
				$this->format_date($event_saved[$name]) .' / '.
				(is_null($old_event) ? 'no old event' : $this->format_date($old_event[$name]))."\n");
		}
		foreach(array('event2save','event_saved','old_event') as $name)
		{
			fwrite($f,$name.' = '.print_r($$name,true));
		}
		fwrite($f,"\n");
		fclose($f);

		return true;
	}

	/**
	 * Check alarms and move them if needed
	 *
	 * Used when the start time has changed, and alarms need to be updated
	 *
	 * @param array $event
	 * @param array $old_event
	 * @param Api\DateTime|int|null $instance_date For recurring events, this is the date we are dealing with
	 * @param boolean $ignore_acl=false true: no acl check
	 */
	function check_move_alarms(Array &$event, Array $old_event = null, $instance_date = null, $ignore_acl=false)
	{
		if ($old_event !== null && $event['start'] == $old_event['start']) return;

		$time = new Api\DateTime($event['start']);
		if(!is_array($event['alarm']))
		{
			$event['alarm'] = $this->so->read_alarms($event['id']);
		}

		if (is_object($instance_date))
		{
			if (!is_a($instance_date, 'EGroupware\\Api\\DateTime'))
			{
				throw new Api\Exception\WrongParameter('$instance_date must be integer or Api\DateTime!');
			}
			$instance_date = $instance_date->format('ts');
		}

		foreach($event['alarm'] as &$alarm)
		{
			if(!empty($event['recur_type']) && $instance_date)
			{
				calendar_so::shift_alarm($event, $alarm, $instance_date);
			}
			else if ($alarm['time'] !== $time->format('ts') - $alarm['offset'])
			{
				$alarm['time'] = $time->format('ts') - $alarm['offset'];
				$this->save_alarm($event['id'], $alarm, true, $ignore_acl);
			}
		}
	}

	/**
	 * saves a new or updated alarm
	 *
	 * @param int $cal_id Id of the calendar-entry
	 * @param array $alarm array with fields: text, owner, enabled, ..
	 * @param boolean $update_modified =true call update modified, default true
	 * @param boolean $ignore_acl=false true: no acl check
	 * @return string id of the alarm, or false on error (eg. no perms)
	 */
	function save_alarm($cal_id, $alarm, $update_modified=true, $ignore_acl=false)
	{
		if (!$cal_id || !$ignore_acl && !$this->check_perms(Acl::EDIT,$alarm['all'] ? $cal_id : 0,!$alarm['all'] ? $alarm['owner'] : 0))
		{
			//echo "<p>no rights to save the alarm=".print_r($alarm,true)." to event($cal_id)</p>";
			return false;	// no rights to add the alarm
		}
		$alarm['time'] = $this->date2ts($alarm['time'],true);	// user to server-time

		return $this->so->save_alarm($cal_id, $alarm, $update_modified);
	}

	/**
	 * delete one alarms identified by its id
	 *
	 * @param string $id alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
	 * @param boolean $ignore_acl=false true: no acl check
	 * @return int number of alarms deleted, false on error (eg. no perms)
	 */
	function delete_alarm($id, $ignore_acl=false)
	{
		list(,$cal_id) = explode(':',$id);

		if (!($alarm = $this->so->read_alarm($id)) || !$cal_id || !$ignore_acl && !$this->check_perms(Acl::EDIT,$alarm['all'] ? $cal_id : 0,!$alarm['all'] ? $alarm['owner'] : 0))
		{
			return false;	// no rights to delete the alarm
		}

		return $this->so->delete_alarm($id);
	}

	/**
	 * Find existing categories in database by name or add categories that do not exist yet
	 * currently used for ical/sif import
	 *
	 * @param array $catname_list names of the categories which should be found or added
	 * @param int|array $old_event =null match against existing event and expand the returned category ids
	 *  by the ones the user normally does not see due to category permissions - used to preserve categories
	 * @return array category ids (found, added and preserved categories)
	 */
	function find_or_add_categories($catname_list, $old_event=null)
	{
		if (is_array($old_event) || $old_event > 0)
		{
			// preserve categories without users read access
			if (!is_array($old_event)) $old_event = $this->read($old_event);
			$old_categories = explode(',',$old_event['category']);
			$old_cats_preserve = array();
			if (is_array($old_categories) && count($old_categories) > 0)
			{
				foreach ($old_categories as $cat_id)
				{
					if (!$this->categories->check_perms(Acl::READ, $cat_id))
					{
						$old_cats_preserve[] = $cat_id;
					}
				}
			}
		}

		$cat_id_list = array();
		foreach ((array)$catname_list as $cat_name)
		{
			$cat_name = trim($cat_name);
			$cat_id = $this->categories->name2id($cat_name, 'X-');

			if (!$cat_id)
			{
				// some SyncML clients (mostly phones) add an X- to the category names
				if (strncmp($cat_name, 'X-', 2) == 0)
				{
					$cat_name = substr($cat_name, 2);
				}
				$cat_id = $this->categories->add(array('name' => $cat_name, 'descr' => $cat_name, 'access' => 'private'));
			}

			if ($cat_id)
			{
				$cat_id_list[] = $cat_id;
			}
		}

		if (is_array($old_cats_preserve) && count($old_cats_preserve) > 0)
		{
			$cat_id_list = array_merge($cat_id_list, $old_cats_preserve);
		}

		if (count($cat_id_list) > 1)
		{
			$cat_id_list = array_unique($cat_id_list);
			sort($cat_id_list, SORT_NUMERIC);
		}

		return $cat_id_list;
	}

	function get_categories($cat_id_list)
	{
		if (!is_array($cat_id_list))
		{
			$cat_id_list = explode(',',$cat_id_list);
		}
		$cat_list = array();
		foreach ($cat_id_list as $cat_id)
		{
			if ($cat_id && $this->categories->check_perms(Acl::READ, $cat_id) &&
					($cat_name = $this->categories->id2name($cat_id)) && $cat_name != '--')
			{
				$cat_list[] = $cat_name;
			}
		}

		return $cat_list;
	}

	/**
	 * Try to find a matching db entry
	 *
	 * @param array $event	the vCalendar data we try to find
	 * @param string filter='exact' exact	-> find the matching entry
	 * 								check	-> check (consitency) for identical matches
	 * 							    relax	-> be more tolerant
	 *                              master	-> try to find a releated series master
	 * @return array calendar_ids of matching entries
	 */
	function find_event($event, $filter='exact')
	{
		$matchingEvents = array();
		$query = array();

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				"($filter)[EVENT]:" . array2string($event)."\n",3,$this->logfile);
		}

		if (!isset($event['recurrence'])) $event['recurrence'] = 0;

		if ($filter == 'master')
		{
			$query[] = 'recur_type!='. MCAL_RECUR_NONE;
			$query['cal_recurrence'] = 0;
		}
		elseif ($filter == 'exact')
		{
			if (!empty($event['recur_type']))
			{
				$query[] = 'recur_type='.$event['recur_type'];
			}
			else
			{
				$query[] = 'recur_type IS NULL';
			}
			$query['cal_recurrence'] = $event['recurrence'];
		}

		if ($event['id'])
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'(' . $event['id'] . ")[EventID]\n",3,$this->logfile);
			}
			if (($egwEvent = $this->read($event['id'], 0, false, 'server')))
			{
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
						'()[FOUND]:' . array2string($egwEvent)."\n",3,$this->logfile);
				}
				if (!empty($egwEvent['recur_type']) &&
					(empty($event['uid']) || $event['uid'] == $egwEvent['uid']))
				{
					if ($filter == 'master')
					{
						$matchingEvents[] = $egwEvent['id']; // we found the master
					}
					if ($event['recur_type'] == $egwEvent['recur_type'])
					{
						$matchingEvents[] = $egwEvent['id']; // we found the event
					}
					elseif (empty($event['recur_type']) &&
								$event['recurrence'] != 0)
					{
						$exceptions = $this->so->get_recurrence_exceptions($egwEvent, $event['tzid']);
						if (in_array($event['recurrence'], $exceptions))
						{
							$matchingEvents[] = $egwEvent['id'] . ':' . (int)$event['recurrence'];
						}
					}
				} elseif ($filter != 'master' && ($filter == 'exact' ||
							$event['recur_type'] == $egwEvent['recur_type'] &&
							strpos($egwEvent['title'], $event['title']) === 0))
				{
					$matchingEvents[] = $egwEvent['id']; // we found the event
				}
			}
			if (!empty($matchingEvents) || $filter == 'exact') return $matchingEvents;
		}
		unset($event['id']);

		// No chance to find a master without [U]ID
		if ($filter == 'master' && empty($event['uid'])) return $matchingEvents;

		// only query calendars of users, we have READ-grants from
		$users = array();
		foreach(array_keys($this->grants) as $user)
		{
			$user = trim($user);
			if ($this->check_perms(Acl::READ|self::ACL_READ_FOR_PARTICIPANTS|self::ACL_FREEBUSY,0,$user))
			{
				if ($user && !in_array($user,$users))	// already added?
				{
					$users[] = $user;
				}
			}
			elseif ($GLOBALS['egw']->accounts->get_type($user) != 'g')
			{
				continue;	// for non-groups (eg. users), we stop here if we have no read-rights
			}
			// the further code is only for real users
			if (!is_numeric($user)) continue;

			// for groups we have to include the members
			if ($GLOBALS['egw']->accounts->get_type($user) == 'g')
			{
				$members = $GLOBALS['egw']->accounts->members($user, true);
				if (is_array($members))
				{
					foreach($members as $member)
					{
						// use only members which gave the user a read-grant
						if (!in_array($member, $users) &&
								$this->check_perms(Acl::READ|self::ACL_FREEBUSY, 0, $member))
						{
							$users[] = $member;
						}
					}
				}
			}
			else	// for users we have to include all the memberships, to get the group-events
			{
				$memberships = $GLOBALS['egw']->accounts->memberships($user, true);
				if (is_array($memberships))
				{
					foreach($memberships as $group)
					{
						if (!in_array($group, $users))
						{
							$users[] = $group;
						}
					}
				}
			}
		}

		if ($filter != 'master' && ($filter != 'exact' || empty($event['uid'])))
		{
			if (!empty($event['whole_day']))
			{
				if ($filter == 'relax')
				{
					$delta = 1800;
				}
				else
				{
					$delta = 60;
				}

				// check length with some tolerance
				$length = $event['end'] - $event['start'] - $delta;
				$query[] = ('(cal_end-cal_start)>' . $length);
				$length += 2 * $delta;
				$query[] = ('(cal_end-cal_start)<' . $length);
				$query[] = ('cal_start>' . ($event['start'] - 86400));
				$query[] = ('cal_start<' . ($event['start'] + 86400));
			}
			elseif (isset($event['start']))
			{
				if ($filter == 'relax')
				{
					$query[] = ('cal_start>' . ($event['start'] - 3600));
					$query[] = ('cal_start<' . ($event['start'] + 3600));
				}
				else
				{
					// we accept a tiny tolerance
					$query[] = ('cal_start>' . ($event['start'] - 2));
					$query[] = ('cal_start<' . ($event['start'] + 2));
				}
			}
			if ($filter == 'relax')
			{
				$matchFields = array();
			}
			else
			{
				$matchFields = array('priority', 'public');
			}
			foreach ($matchFields as $key)
			{
				if (isset($event[$key])) $query['cal_'.$key] = $event[$key];
			}
		}

		if (!empty($event['uid']))
		{
			$query['cal_uid'] = $event['uid'];
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'(' . $event['uid'] . ")[EventUID]\n",3,$this->logfile);
			}
		}

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				'[QUERY]: ' . array2string($query)."\n",3,$this->logfile);
		}
		if (!count($users) || !($foundEvents =
			$this->so->search(null, null, $users, 0, 'owner', false, 0, array('query' => $query))))
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				"[NO MATCH]\n",3,$this->logfile);
			}
			return $matchingEvents;
		}

		$pseudos = array();

		foreach($foundEvents as $egwEvent)
		{
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					'[FOUND]: ' . array2string($egwEvent)."\n",3,$this->logfile);
			}

			if (in_array($egwEvent['id'], $matchingEvents)) continue;

			// convert timezone id of event to tzid (iCal id like 'Europe/Berlin')
			if (!$egwEvent['tz_id'] || !($egwEvent['tzid'] = calendar_timezones::id2tz($egwEvent['tz_id'])))
			{
				$egwEvent['tzid'] = Api\DateTime::$server_timezone->getName();
			}
			if (!isset(self::$tz_cache[$egwEvent['tzid']]))
			{
				self::$tz_cache[$egwEvent['tzid']] = calendar_timezones::DateTimeZone($egwEvent['tzid']);
			}
			if (!$event['tzid'])
			{
				$event['tzid'] = Api\DateTime::$server_timezone->getName();
			}
			if (!isset(self::$tz_cache[$event['tzid']]))
			{
				self::$tz_cache[$event['tzid']] = calendar_timezones::DateTimeZone($event['tzid']);
			}

			if (!empty($event['uid']))
			{
				if ($filter == 'master')
				{
					// We found the master
					$matchingEvents = array($egwEvent['id']);
					break;
				}
				if ($filter == 'exact')
				{
					// UID found
					if (empty($event['recurrence']))
					{
						$egwstart = new Api\DateTime($egwEvent['start'], Api\DateTime::$server_timezone);
						$egwstart->setTimezone(self::$tz_cache[$egwEvent['tzid']]);
						$dtstart = new Api\DateTime($event['start'], Api\DateTime::$server_timezone);
						$dtstart->setTimezone(self::$tz_cache[$event['tzid']]);
						if (empty($egwEvent['recur_type']) &&
							empty($event['recur_type']) ||
								!empty($egwEvent['recur_type']) &&
									!empty($event['recur_type']))
						{
							if (empty($egwEvent['recur_type']) &&
								$egwstart->format('Ymd') == $dtstart->format('Ymd') ||
									!empty($egwEvent['recur_type']))
							{
								// We found an exact match
								$matchingEvents = array($egwEvent['id']);
								break;
							}
							else
							{
								$matchingEvents[] = $egwEvent['id'];
							}
						}
						continue;
					}
					elseif ($egwEvent['recurrence'] == $event['recurrence'])
					{
						// We found an exact match
						$matchingEvents = array($egwEvent['id']);
						break;
					}
					if (!empty($egwEvent['recur_type']) &&
						empty($event['recur_type']) &&
							!$egwEvent['recurrence'] && $event['recurrence'])
					{
						$exceptions = $this->so->get_recurrence_exceptions($egwEvent, $event['tzid']);
						if (in_array($event['recurrence'], $exceptions))
						{
							// We found a pseudo exception
							$matchingEvents = array($egwEvent['id'] . ':' . (int)$event['recurrence']);
							break;
						}
					}
					continue;
				}
			}

			// check times
			if ($filter != 'relax')
			{
				if (empty($event['whole_day']))
				{
					if (abs($event['end'] - $egwEvent['end']) >= 120)
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"() egwEvent length does not match!\n",3,$this->logfile);
						}
						continue;
					}
				}
				else
				{
					if (!$this->so->isWholeDay($egwEvent))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"() egwEvent is not a whole-day event!\n",3,$this->logfile);
						}
						continue;
					}
				}
			}

			// check for real match
			$matchFields = array('title', 'description');
			if ($filter != 'relax')
			{
				$matchFields[] = 'location';
			}
			foreach ($matchFields as $key)
			{
				if (!empty($event[$key]) && (empty($egwEvent[$key])
						|| strpos(str_replace("\r\n", "\n", $egwEvent[$key]), $event[$key]) !== 0))
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"() event[$key] differ: '" . $event[$key] .
							"' <> '" . $egwEvent[$key] . "'\n",3,$this->logfile);
					}
					continue 2; // next foundEvent
				}
			}

			if (is_array($event['category']))
			{
				// check categories
				$egwCategories = explode(',', $egwEvent['category']);
				foreach ($egwCategories as $cat_id)
				{
					if ($this->categories->check_perms(Acl::READ, $cat_id) &&
							!in_array($cat_id, $event['category']))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							"() egwEvent category $cat_id is missing!\n",3,$this->logfile);
						}
						continue 2;
					}
				}
				$newCategories = array_diff($event['category'], $egwCategories);
				if (!empty($newCategories))
				{
					if ($this->log)
					{
						error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
							'() event has additional categories:'
							. array2string($newCategories)."\n",3,$this->logfile);
					}
					continue;
				}
			}

			if ($filter != 'relax')
			{
				// check participants
				if (is_array($event['participants']))
				{
					foreach ($event['participants'] as $attendee => $status)
					{
						if (!isset($egwEvent['participants'][$attendee]) &&
								$attendee != $egwEvent['owner']) // ||
							//(!$relax && $egw_event['participants'][$attendee] != $status))
						{
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								"() additional event['participants']: $attendee\n",3,$this->logfile);
							}
							continue 2;
						}
						else
						{
							unset($egwEvent['participants'][$attendee]);
						}
					}
					// ORGANIZER and Groups may be missing
					unset($egwEvent['participants'][$egwEvent['owner']]);
					foreach ($egwEvent['participants'] as $attendee => $status)
					{
						if (is_numeric($attendee) && $attendee < 0)
						{
							unset($egwEvent['participants'][$attendee]);
						}
					}
					if (!empty($egwEvent['participants']))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								'() missing event[participants]: ' .
								array2string($egwEvent['participants'])."\n",3,$this->logfile);
						}
						continue;
					}
				}
			}

			if (empty($event['recur_type']))
			{
				if (!empty($egwEvent['recur_type']))
				{
					// We found a pseudo Exception
					$pseudos[] = $egwEvent['id'] . ':' . $event['start'];
					continue;
				}
			}
			elseif ($filter != 'relax')
			{
				// check exceptions
				// $exceptions[$remote_ts] = $egw_ts
				$exceptions = $this->so->get_recurrence_exceptions($egwEvent, $event['$tzid'], 0, 0, 'map');
				if (is_array($event['recur_exception']))
				{
					foreach ($event['recur_exception'] as $key => $day)
					{
						if (isset($exceptions[$day]))
						{
							unset($exceptions[$day]);
						}
						else
						{
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								"() additional event['recur_exception']: $day\n",3,$this->logfile);
							}
							continue 2;
						}
					}
					if (!empty($exceptions))
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								'() missing event[recur_exception]: ' .
								array2string($event['recur_exception'])."\n",3,$this->logfile);
						}
						continue;
					}
				}

				// check recurrence information
				foreach (array('recur_type', 'recur_interval', 'recur_enddate') as $key)
				{
					if (isset($event[$key])
							&& $event[$key] != $egwEvent[$key])
					{
						if ($this->log)
						{
							error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
								"() events[$key] differ: " . $event[$key] .
								' <> ' . $egwEvent[$key]."\n",3,$this->logfile);
						}
						continue 2;
					}
				}
			}
			$matchingEvents[] = $egwEvent['id']; // exact match
		}

		if ($filter == 'exact' && !empty($event['uid']) && count($matchingEvents) > 1
			|| $filter != 'master' && !empty($egwEvent['recur_type']) && empty($event['recur_type']))
		{
			// Unknown exception for existing series
			if ($this->log)
			{
				error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"() new exception for series found.\n",3,$this->logfile);
			}
			$matchingEvents = array();
		}

		// append pseudos as last entries
		$matches = array_merge($matchingEvents, $pseudos);

		if ($this->log)
		{
			error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
				'[MATCHES]:' . array2string($matches)."\n",3,$this->logfile);
		}
		return $matches;
	}

	/**
	 * classifies an incoming event from the eGW point-of-view
	 *
     * exceptions: unlike other calendar apps eGW does not create an event exception
     * if just the participant state changes - therefore we have to distinguish between
     * real exceptions and status only exceptions
     *
     * @param array $event the event to check
     *
     * @return array
     * 	type =>
     * 		SINGLE a single event
     * 		SERIES-MASTER the series master
     * 		SERIES-EXCEPTION event is a real exception
	  * 		SERIES-PSEUDO-EXCEPTION event is a status only exception
	  * 		SERIES-EXCEPTION-PROPAGATE event was a status only exception in the past and is now a real exception
	  * 	stored_event => if event already exists in the database array with event data or false
	  * 	master_event => for event type SERIES-EXCEPTION, SERIES-PSEUDO-EXCEPTION or SERIES-EXCEPTION-PROPAGATE
	  * 		the corresponding series master event array
	  * 		NOTE: this param is false if event is of type SERIES-MASTER
     */
	function get_event_info(&$event)
	{
		$type = 'SINGLE'; // default
		$master_event = false; //default
		$stored_event = false;
		$recurrence_event = false;
		$wasPseudo = false;

		if (($foundEvents = $this->find_event($event, 'exact')))
		{
			// We found the exact match
			$eventID = array_shift($foundEvents);
			if (strstr($eventID, ':'))
			{
				$type = 'SERIES-PSEUDO-EXCEPTION';
				$wasPseudo = true;
				list($eventID, $date) = explode(':', $eventID);
				$recur_date = $this->date2usertime($date);
				$stored_event = $this->read($eventID, $recur_date, false, 'server');
				$master_event = $this->read($eventID, 0, false, 'server');
				$recurrence_event = $stored_event;
			}
			else
			{
				$stored_event = $this->read($eventID, 0, false, 'server');
			}
			if (!empty($stored_event['uid']) && empty($event['uid']))
			{
				$event['uid'] = $stored_event['uid']; // restore the UID if it was not delivered
			}
		}

		if (!empty($event['recur_type']))
		{
			$type = 'SERIES-MASTER';
		}

		if ($type == 'SINGLE' &&
			($foundEvents = $this->find_event($event, 'master')))
		{
			// SINGLE, SERIES-EXCEPTION OR SERIES-EXCEPTON-STATUS
			foreach ($foundEvents  as $eventID)
			{
				// Let's try to find a related series
				if ($this->log)
				{
					error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
					"()[MASTER]: $eventID\n",3,$this->logfile);
				}
				$type = 'SERIES-EXCEPTION';
				if (($master_event = $this->read($eventID, 0, false, 'server')))
				{
					if (isset($stored_event['id']) &&
						$master_event['id'] != $stored_event['id'])
					{
						break; // this is an existing exception
					}
					elseif (isset($event['recurrence']) &&
						in_array($event['recurrence'], $master_event['recur_exception']))
					{
						$type = 'SERIES-PSEUDO-EXCEPTION'; // could also be a real one
						$recurrence_event = $master_event;
						$recurrence_event['start'] = $event['recurrence'];
						$recurrence_event['end'] -= $master_event['start'] - $event['recurrence'];
						break;
					}
					elseif (in_array($event['start'], $master_event['recur_exception']))
					{
						$type='SERIES-PSEUDO-EXCEPTION'; // new pseudo exception?
						$recurrence_event = $master_event;
						$recurrence_event['start'] = $event['start'];
						$recurrence_event['end'] -= $master_event['start'] - $event['start'];
						break;
					}
					else
					{
						// try to find a suitable pseudo exception date
						// Checks are all in server time
						$egw_rrule = calendar_rrule::event2rrule($master_event, false, $GLOBALS['egw_info']['server']['server_timezone']);
						$egw_rrule->current = clone $egw_rrule->time;
						while ($egw_rrule->valid())
						{
							$occurrence = Api\DateTime::to($egw_rrule->current(), 'server');
							if ($this->log)
							{
								error_log(__FILE__.'['.__LINE__.'] '.__METHOD__.
									'() try occurrence ' . $egw_rrule->current()
									. " ($occurrence)\n",3,$this->logfile);
							}
							if ($event['start'] == $occurrence)
							{
								$type = 'SERIES-PSEUDO-EXCEPTION'; // let's try a pseudo exception
								$recurrence_event = $master_event;
								// Update the event's recurrence too
								$recurrence_event['start'] = $event['recurrence'] = $occurrence;
								$recurrence_event['end'] -= $master_event['start'] - $occurrence;
								break 2;
							}
							if (isset($event['recurrence']) && $event['recurrence'] == $occurrence)
							{
								$type = 'SERIES-EXCEPTION-PROPAGATE';
								if ($stored_event)
								{
									unset($stored_event['id']); // signal the true exception
									$stored_event['recur_type'] = MCAL_RECUR_NONE;
								}
								break 2;
							}
							$egw_rrule->next_no_exception();
						}
					}
				}
			}
		}

		// check pseudo exception propagation
		if ($recurrence_event)
		{
			// default if we cannot find a proof for a fundamental change
			// the recurrence_event is the master event with start and end adjusted to the recurrence
			// check for changed data
			foreach (array('start','end','uid','title','location','description',
				'priority','public','special','non_blocking') as $key)
			{
				if (!empty($event[$key]) && $recurrence_event[$key] != $event[$key])
				{
					if ($wasPseudo)
					{
						// We started with a pseudo exception
						$type = 'SERIES-EXCEPTION-PROPAGATE';
					}
					else
					{
						$type = 'SERIES-EXCEPTION';
					}

					if ($stored_event)
					{
						unset($stored_event['id']); // signal the true exception
						$stored_event['recur_type'] = MCAL_RECUR_NONE;
					}
					break;
				}
			}
			// the event id here is always the id of the master event
			// unset it to prevent confusion of stored event and master event
			unset($event['id']);
		}

		// check ACL
		if (is_array($master_event))
		{
			$acl_edit = $this->check_perms(Acl::EDIT, $master_event['id']);
		}
		else
		{
			if (is_array($stored_event))
			{
				$acl_edit = $this->check_perms(Acl::EDIT, $stored_event['id']);
			}
			else
			{
				$acl_edit = true; // new event
			}
		}

		return array(
			'type' => $type,
			'acl_edit' => $acl_edit,
			'stored_event' => $stored_event,
			'master_event' => $master_event,
		);
    }

    /**
     * Translates all timestamps for a given event from server-time to user-time.
     * The update() and save() methods expect timestamps in user-time.
     * @param &$event	the event we are working on
     *
     */
    function server2usertime (&$event)
    {
		// we run all dates through date2usertime, to adjust to user-time
		foreach(array('start','end','recur_enddate','recurrence') as $ts)
		{
			// we convert here from server-time to timestamps in user-time!
			if (isset($event[$ts])) $event[$ts] = $event[$ts] ? $this->date2usertime($event[$ts]) : 0;
		}
		// same with the recur exceptions
		if (isset($event['recur_exception']) && is_array($event['recur_exception']))
		{
			foreach($event['recur_exception'] as $n => $date)
			{
				$event['recur_exception'][$n] = $this->date2usertime($date);
			}
		}
		// same with the alarms
		if (isset($event['alarm']) && is_array($event['alarm']))
		{
			foreach($event['alarm'] as $id => $alarm)
			{
				$event['alarm'][$id]['time'] = $this->date2usertime($alarm['time'] ?? null);
			}
		}
    }
	/**
	 * Delete events that are more than $age years old
	 *
	 * Purges old events from the database
	 *
	 * @param int|float $age How many years old the event must be before it is deleted
	 */
	function purge($age)
	{
		if (is_numeric($age) && $age > 0)	// just make sure bogus values dont delete everything
		{
			$this->so->purge(time() - 365*24*3600*(float)$age);
		}
	}

	/**
	 * Check to see if we need to reset the status of any of the participants
	 * - Current user is never reset
	 * - Other users we respect their preference
	 * - Non-users status is always reset
	 *
	 * @param Array $event New event
	 * @param Array $old_event Event before modification
	 *
	 * @return boolean true if any statuses were reset
	 */
	protected function check_reset_statuses(&$event, $old_event)
	{
		// Event passed is still in user time at this point, convert to servertime for
		// comparison, but don't modify it in event
		if(!$old_event || !is_array($old_event) || $this->date2ts($event['start'],true) == $old_event['start'])
		{
			return false;
		}

		$status_reset = false;
		$sameday = (date('Ymd', $old_event['start']) == date('Ymd', $event['start']));
		foreach((array)$event['participants'] as $uid => $status)
		{
			$q = $r = null;
			calendar_so::split_status($status, $q, $r);
			if($uid == $this->user || $status == 'U')
			{
				continue;
			}

			if(is_int($uid))
			{
				// Just user accounts
				$preferences = new Api\Preferences($uid);
			}
			else
			{
				// For non-users, use the owner's preference
				$preferences = new Api\Preferences($event['owner']);
			}
			$part_prefs = $preferences->read_repository();
			switch($part_prefs['calendar']['reset_stati'])
			{
				case 'no':
					break;
				case 'startday':
					if($sameday)
					{
						break;
					}
				default:
					$status_reset = true;
					$event['participants'][$uid] = calendar_so::combine_status('U', $q, $r);
			}

		}
		return $status_reset;

	}
}