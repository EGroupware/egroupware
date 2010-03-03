<?php
/**
 * eGroupWare - Calendar's buisness-object - access + update
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) 2005-9 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// types of messsages send by calendar_boupdate::send_update
define('MSG_DELETED',0);
define('MSG_MODIFIED',1);
define('MSG_ADDED',2);
define('MSG_REJECTED',3);
define('MSG_TENTATIVE',4);
define('MSG_ACCEPTED',5);
define('MSG_ALARM',6);
define('MSG_DISINVITE',7);

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
	 * @var string|boolean $log_file filename to enable the login or false for no update-logging
	 */
	var $log_file = false;

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
	 * updates or creates an event, it (optionaly) checks for conflicts and sends the necessary notifications
	 *
	 * @param array &$event event-array, on return some values might be changed due to set defaults
	 * @param boolean $ignore_conflicts=false just ignore conflicts or do a conflict check and return the conflicting events
	 * @param boolean $touch_modified=true touch modificatin time and set modifing user, default true=yes
	 * @param boolean $ignore_acl=false should we ignore the acl
	 * @param boolean $updateTS=true update the content history of the event
	 * @param array &$messages=null messages about because of missing ACL removed participants or categories
	 * @return mixed on success: int $cal_id > 0, on error false or array with conflicting events (only if $check_conflicts)
	 * 		Please note: the events are not garantied to be readable by the user (no read grant or private)!
	 */
	function update(&$event,$ignore_conflicts=false,$touch_modified=true,$ignore_acl=false,$updateTS=true,&$messages=null)
	{
		//error_log(__METHOD__."(".array2string($event).",$ignore_conflicts,$touch_modified,$ignore_acl)");
		if ($this->debug > 1 || $this->debug == 'update')
		{
			$this->debug_message('calendar_boupdate::update(%1,ignore_conflict=%2,touch_modified=%3,ignore_acl=%4)',
				false,$event,$ignore_conflicts,$touch_modified,$ignore_acl);
		}
		// check some minimum requirements:
		// - new events need start, end and title
		// - updated events cant set start, end or title to empty
		if (!$event['id'] && (!$event['start'] || !$event['end'] || !$event['title']) ||
			$event['id'] && (isset($event['start']) && !$event['start'] || isset($event['end']) && !$event['end'] ||
			isset($event['title']) && !$event['title']))
		{
			return false;
		}

		if (($new_event = !$event['id']))	// some defaults for new entries
		{
			// if no owner given, set user to owner
			if (!$event['owner']) $event['owner'] = $this->user;
			// set owner as participant if none is given
			if (!is_array($event['participants']) || !count($event['participants']))
			{
				$status = $event['owner'] == $this->user ? 'A' : 'U';
				$status = calendar_so::combine_status($status, 1, 'CHAIR');
				$event['participants'] = array($event['owner'] => $status);
			}
		}

		// check if user has the permission to update / create the event
		if (!$ignore_acl && (!$new_event && !$this->check_perms(EGW_ACL_EDIT,$event['id']) ||
			$new_event && !$this->check_perms(EGW_ACL_EDIT,0,$event['owner'])) &&
			!$this->check_perms(EGW_ACL_ADD,0,$event['owner']))
		{
			return false;
		}
		if ($new_event)
		{
			$event['created'] = $this->now_su;
			$event['creator'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		else
		{
			$old_event = $this->read((int)$event['id'],null,$ignore_acl);
			// if no participants are set, set them from the old event, as we might need them to update recuring events
			if (!isset($event['participants'])) $event['participants'] = $old_event['participants'];
			//echo "old $event[id]="; _debug_array($old_event);
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
		if ($event['category'])
		{
			if (!is_array($event['category'])) $event['category'] = explode(',',$event['category']);
			if ($old_event && $old_event['category'] && !is_array($old_event['category']))
			{
				$old_event['category'] = explode(',',$old_event['category']);
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
					foreach($event['participants'] as $uid => $status)
					{
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
		// check for conflicts only happens !$ignore_conflicts AND if start + end date are given
		if (!$ignore_conflicts && !$event['non_blocking'] && isset($event['start']) && isset($event['end']))
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
				calendar_so::split_status($status,$q,$r);
				if ($status[0] == 'R') continue;	// ignore rejected participants

				if ($uid < 0)	// group, check it's members too
				{
					$users += $GLOBALS['egw']->accounts->members($uid,true);
					$users = array_unique($users);
				}
				$users[] = $uid;
				if (in_array($uid[0],$types_with_quantity))
				{
					$quantity[$uid] = $q;
				}
			}
			$overlapping_events =& $this->search(array(
				'start' => $event['start'],
				'end'   => $event['end'],
				'users' => $users,
				'ignore_acl' => true,	// otherwise we get only events readable by the user
				'enum_groups' => true,	// otherwise group-events would not block time
			));
			if ($this->debug > 2 || $this->debug == 'update')
			{
				$this->debug_message('calendar_boupdate::update() checking for potential overlapping events for users %1 from %2 to %3',false,$users,$event['start'],$event['end']);
			}
			$max_quantity = $possible_quantity_conflicts = $conflicts = array();
			foreach((array) $overlapping_events as $k => $overlap)
			{
				if ($overlap['id'] == $event['id'] ||	// that's the event itself
					$overlap['id'] == $event['reference'] ||	// event is an exception of overlap
					$overlap['non_blocking'])			// that's a non_blocking event
				{
					continue;
				}
				if ($this->debug > 3 || $this->debug == 'update')
				{
					$this->debug_message('calendar_boupdate::update() checking overlapping event %1',false,$overlap);
				}
				// check if the overlap is with a rejected participant or within the allowed quantity
				$common_parts = array_intersect($users,array_keys($overlap['participants']));
				foreach($common_parts as $n => $uid)
				{
					if ($overlap['participants'][$uid][0] == 'R')
					{
						unset($common_parts[$uid]);
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
					$quantity[$uid] += max(1,(int) substr($overlap['participants'][$uid],2));
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
					if ($this->debug > 3 || $this->debug == 'update')
					{
						$this->debug_message('calendar_boupdate::update() conflicts with the following participants found %1',false,$common_parts);
					}
					$conflicts[$overlap['id'].'-'.$this->date2ts($overlap['start'])] =& $overlapping_events[$k];
				}
			}
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
					$conflict['participants'] = array_intersect_key($conflict['participants'],$event['participants']);
					if (!$this->check_perms(EGW_ACL_READ,$conflict))
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
				if ($this->debug > 2 || $this->debug == 'update')
				{
					$this->debug_message('calendar_boupdate::update() %1 conflicts found %2',false,count($conflicts),$conflicts);
				}
				return $conflicts;
			}
		}

		// save the event to the database
		if ($touch_modified)
		{
			$event['modified'] = $this->now_su;	// we are still in user-time
			$event['modifier'] = $GLOBALS['egw_info']['user']['account_id'];
		}
		//echo "saving $event[id]="; _debug_array($event);
		$event2save = $event;

		if (!($cal_id = $this->save($event, $ignore_acl, $updateTS)))
		{
			return $cal_id;
		}

		$event = $this->read($cal_id);	// we re-read the event, in case only partial information was update and we need the full info for the notifies
		//echo "new $cal_id="; _debug_array($event);

		if ($this->log_file)
		{
			$this->log2file($event2save,$event,$old_event);
		}
		// send notifications
		if ($new_event)
		{
			$this->send_update(MSG_ADDED,$event['participants'],'',$event);
		}
		else // update existing event
		{
			$this->check4update($event,$old_event);

		}
		// notify the link-class about the update, as other apps may be subscribt to it
		egw_link::notify_update('calendar',$cal_id,$event);

		return $cal_id;
	}

	/**
	 * Remove participants current user has no right to invite
	 *
	 * @param array &$event new event
	 * @param array $old_event=null old event with already invited participants
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
		foreach($event['participants'] as $uid => $status)
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
		if (!is_numeric($uid)) return true;	// nothing implemented for resources so far

		if (!$this->require_acl_invite)
		{
			$ret = true;	// no grant required
		}
		elseif ($this->require_acl_invite == 'groups' && $GLOBALS['egw']->accounts->get_type($uid) != 'g')
		{
			$ret = true;	// grant only required for groups
		}
		else
		{
			$ret = $this->check_perms(EGW_ACL_INVITE,0,$uid);
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
		$modified = $added = $deleted = array();

		//echo "<p>calendar_boupdate::check4update() new participants = ".print_r($new_event['participants'],true).", old participants =".print_r($old_event['participants'],true)."</p>\n";

		// Find modified and deleted participants ...
		foreach($old_event['participants'] as $old_userid => $old_status)
		{
			if(isset($new_event['participants'][$old_userid]))
			{
				$modified[$old_userid] = $new_event['participants'][$old_userid];
			}
			else
			{
				$deleted[$old_userid] = $old_status;
			}
		}
		// Find new participants ...
		foreach($new_event['participants'] as $new_userid => $new_status)
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
	 * @param int $msg_type type of the notification: MSG_ADDED, MSG_MODIFIED, MSG_ACCEPTED, ...
	 * @param array $old_event Event before the change
	 * @param array $new_event Event after the change
	 * @return boolean true = update requested, flase otherwise
	 */
	function update_requested($userid,$part_prefs,$msg_type,$old_event,$new_event)
	{
		if ($msg_type == MSG_ALARM)
		{
			return True;	// always True for now
		}
		$want_update = 0;

		// the following switch falls through all cases, as each included the following too
		//
		$msg_is_response = $msg_type == MSG_REJECTED || $msg_type == MSG_ACCEPTED || $msg_type == MSG_TENTATIVE;

		switch($ru = $part_prefs['calendar']['receive_updates'])
		{
			case 'responses':
				if ($msg_is_response)
				{
					++$want_update;
				}
			case 'modifications':
				if ($msg_type == MSG_MODIFIED)
				{
					++$want_update;
				}
			case 'time_change_4h':
			case 'time_change':
				$diff = max(abs($this->date2ts($old_event['start'])-$this->date2ts($new_event['start'])),
					abs($this->date2ts($old_event['end'])-$this->date2ts($new_event['end'])));
				$check = $ru == 'time_change_4h' ? 4 * 60 * 60 - 1 : 0;
				if ($msg_type == MSG_MODIFIED && $diff > $check)
				{
					++$want_update;
				}
			case 'add_cancel':
				if ($old_event['owner'] == $userid && $msg_is_response ||
					$msg_type == MSG_DELETED || $msg_type == MSG_ADDED || $msg_type == MSG_DISINVITE)
				{
					++$want_update;
				}
				break;
			case 'no':
				break;
		}
		//echo "<p>calendar_boupdate::update_requested(user=$userid,pref=".$part_prefs['calendar']['receive_updates'] .",msg_type=$msg_type,".($old_event?$old_event['title']:'False').",".($old_event?$old_event['title']:'False').") = $want_update</p>\n";
		return $want_update > 0;
	}

	/**
	 * sends update-messages to certain participants of an event
	 *
	 * @param int $msg_type type of the notification: MSG_ADDED, MSG_MODIFIED, MSG_ACCEPTED, ...
	 * @param array $to_notify numerical user-ids as keys (!) (value is not used)
	 * @param array $old_event Event before the change
	 * @param array $new_event=null Event after the change
	 * @param int $user=0 User who started the notify, default current user
	 * @return bool true/false
	 */
	function send_update($msg_type,$to_notify,$old_event,$new_event=null,$user=0)
	{
		//echo "<p>".__METHOD__."($msg_type,".array2string($to_notify).",,$new_event[title],$user)</p>\n";
		if (!is_array($to_notify))
		{
			$to_notify = array();
		}
		$disinvited = $msg_type == MSG_DISINVITE ? array_keys($to_notify) : array();

		$owner = $old_event ? $old_event['owner'] : $new_event['owner'];
		if ($owner && !isset($to_notify[$owner]) && $msg_type != MSG_ALARM)
		{
			$to_notify[$owner] = 'owner';	// always include the event-owner
		}
		$version = $GLOBALS['egw_info']['apps']['calendar']['version'];

		// ignore events in the past (give a tolerance of 10 seconds for the script)
		if($old_event != False && $this->date2ts($old_event['start']) < ($this->now_su - 10))
		{
			return False;
		}
		$temp_user = $GLOBALS['egw_info']['user'];	// save user-date of the enviroment to restore it after

		if (!$user)
		{
			$user = $temp_user['account_id'];
		}
		if ($GLOBALS['egw']->preferences->account_id != $user)
		{
			$GLOBALS['egw']->preferences->__construct($user);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
		}
		$senderid = $GLOBALS['egw_info']['user']['account_id'];
		$event = $msg_type == MSG_ADDED || $msg_type == MSG_MODIFIED ? $new_event : $old_event;

		switch($msg_type)
		{
			case MSG_DELETED:
				$action = lang('Canceled');
				$msg = 'Canceled';
				$msgtype = '"calendar";';
				$method = 'CANCEL';
				break;
			case MSG_MODIFIED:
				$action = lang('Modified');
				$msg = 'Modified';
				$msgtype = '"calendar"; Version="'.$version.'"; Id="'.$new_event['id'].'"';
				$method = 'REQUEST';
				break;
			case MSG_DISINVITE:
				$action = lang('Disinvited');
				$msg = 'Disinvited';
				$msgtype = '"calendar";';
				$method = 'CANCEL';
				break;
			case MSG_ADDED:
				$action = lang('Added');
				$msg = 'Added';
				$msgtype = '"calendar"; Version="'.$version.'"; Id="'.$new_event['id'].'"';
				$method = 'REQUEST';
				break;
			case MSG_REJECTED:
				$action = lang('Rejected');
				$msg = 'Response';
				$msgtype = '"calendar";';
				$method = 'REPLY';
				break;
			case MSG_TENTATIVE:
				$action = lang('Tentative');
				$msg = 'Response';
				$msgtype = '"calendar";';
				$method = 'REPLY';
				break;
			case MSG_ACCEPTED:
				$action = lang('Accepted');
				$msg = 'Response';
				$msgtype = '"calendar";';
				$method = 'REPLY';
				break;
			case MSG_ALARM:
				$action = lang('Alarm');
				$msg = 'Alarm';
				$msgtype = '"calendar";';
				$method = 'PUBLISH';	// duno if thats right
				break;
			default:
				$method = 'PUBLISH';
		}
		$notify_msg = $this->cal_prefs['notify'.$msg];
		if (empty($notify_msg))
		{
			$notify_msg = $this->cal_prefs['notifyAdded'];	// use a default
		}
		$details = $this->_get_event_details($event,$action,$event_arr,$disinvited);

		// add all group-members to the notification, unless they are already participants
		foreach($to_notify as $userid => $statusid)
		{
			if (is_numeric($userid) && $GLOBALS['egw']->accounts->get_type($userid) == 'g' &&
				($members = $GLOBALS['egw']->accounts->member($userid)))
			{
				foreach($members as $member)
				{
					$member = $member['account_id'];
					if (!isset($to_notify[$member]))
					{
						$to_notify[$member] = 'G';	// Group-invitation
					}
				}
			}
		}
		//$currentPrefs = CreateObject('phpgwapi.preferences',$GLOBALS['egw_info']['user']['account_id']);
		//$user_prefs = $currentPrefs->read_repository();
		$user_prefs = $GLOBALS['egw_info']['user']['preferences'];
		foreach($to_notify as $userid => $statusid)
		{
			if ($this->debug > 0) error_log(__METHOD__." trying to notify $userid, with $statusid");
			if (!is_numeric($userid))
			{
				$res_info = $this->resource_info($userid);
				$userid = $res_info['responsible'];
				if (!isset($userid)) continue;
			}

			if ($statusid == 'R' || $GLOBALS['egw']->accounts->get_type($userid) == 'g')
			{
				continue;	// dont notify rejected participants or groups
			}

			if($userid != $GLOBALS['egw_info']['user']['account_id'] || $user_prefs['calendar']['receive_own_updates']==1 ||  $msg_type == MSG_ALARM)
			{
				$preferences = CreateObject('phpgwapi.preferences',$userid);
				$part_prefs = $preferences->read_repository();

				if (!$this->update_requested($userid,$part_prefs,$msg_type,$old_event,$new_event))
				{
					continue;
				}
				$GLOBALS['egw']->accounts->get_account_name($userid,$lid,$details['to-firstname'],$details['to-lastname']);
				$details['to-fullname'] = $GLOBALS['egw']->common->display_fullname('',$details['to-firstname'],$details['to-lastname']);

				$GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'] = $part_prefs['common']['tz_offset'];
				$GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] = $part_prefs['common']['timeformat'];
				$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'] = $part_prefs['common']['dateformat'];

				$GLOBALS['egw']->datetime->tz_offset = 3600 * (int) $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];

				// event is in user-time of current user, now we need to calculate the tz-difference to the notified user and take it into account
				$tz_diff = $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'] - $this->common_prefs['tz_offset'];
				if($old_event != False) $details['olddate'] = $this->format_date($old_event['start']+$tz_diff);
				$details['startdate'] = $this->format_date($event['start']+$tz_diff);
				$details['enddate']   = $this->format_date($event['end']+$tz_diff);

				list($subject,$body) = explode("\n",$GLOBALS['egw']->preferences->parse_notify($notify_msg,$details),2);

				switch($part_prefs['calendar']['update_format'])
				{
					case 'ical':
						if ($method == 'REQUEST')
						{
							$ics = ExecMethod2('calendar.calendar_ical.exportVCal',$event['id'],'2.0',$method);
							$attachment = array(	'string' => $ics,
													'filename' => 'cal.ics',
													'encoding' => '8bit',
													'type' => 'text/calendar; method='.$method,
													);
						}
						// fall through
					case 'extended':
						$body .= "\n\n".lang('Event Details follow').":\n";
						foreach($event_arr as $key => $val)
						{
							if(strlen($details[$key])) {
								switch($key){
							 		case 'access':
									case 'priority':
									case 'link':
										break;
									default:
										$body .= sprintf("%-20s %s\n",$val['field'].':',$details[$key]);
										break;
							 	}
							}
						}
						break;
				}
				// send via notification_app
				if($GLOBALS['egw_info']['apps']['notifications']['enabled']) {
					try {
						$notification = new notifications();
						$notification->set_receivers(array($userid));
						$notification->set_message($body);
						$notification->set_sender($senderid);
						$notification->set_subject($subject);
						$notification->set_links(array($details['link_arr']));
						if(is_array($attachment)) { $notification->set_attachments(array($attachment)); }
						$notification->send();
					}
					catch (Exception $exception) {
						error_log(__METHOD__.' error while notifying user '.$userid.':'.$exception->getMessage());
						continue;
					}
				} else {
					error_log(__METHOD__.' cannot send any notifications because notifications is not installed');
				}
			}
		}
		// restore the enviroment (preferences->read_repository() sets the timezone!)
		$GLOBALS['egw_info']['user'] = $temp_user;
		if ($GLOBALS['egw']->preferences->account_id != $temp_user['account_id'] || isset($preferences))
		{
			$GLOBALS['egw']->preferences->__construct($temp_user['account_id']);
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->read_repository();
			//echo "<p>".__METHOD__."() restored enviroment of #$temp_user[account_id] $temp_user[account_fullname]: tz={$GLOBALS['egw_info']['user']['preferences']['common']['tz']}</p>\n";
		}
		return true;
	}

	function get_update_message($event,$added)
	{
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

		$event_time_user = egw_time::server2user($alarm['time'] + $alarm['offset']);	// alarm[time] is in server-time, read requires user-time
		if (!$alarm['owner'] || !$alarm['cal_id'] || !($event = $this->read($alarm['cal_id'],$event_time_user)))
		{
			return False;	// event not found
		}
		if ($alarm['all'])
		{
			$to_notify = $event['participants'];
		}
		elseif ($this->check_perms(EGW_ACL_READ,$event))	// checks agains $this->owner set to $alarm[owner]
		{
			$to_notify[$alarm['owner']] = 'A';
		}
		else
		{
			return False;	// no rights
		}
		$ret = $this->send_update(MSG_ALARM,$to_notify,$event,False,$alarm['owner']);

		// create a new alarm for recuring events for the next event, if one exists
		if ($event['recur_type'] && ($event = $this->read($alarm['cal_id'],$event_time_user+1)))
		{
			$alarm['time'] = $this->date2ts($event['start']) - $alarm['offset'];

			$this->save_alarm($alarm['cal_id'],$alarm);
		}
		return $ret;
	}

	/**
	 * saves an event to the database, does NOT do any notifications, see calendar_boupdate::update for that
	 *
	 * This methode converts from user to server time and handles the insertion of users and dates of repeating events
	 *
	 * @param array $event
	 * @param boolean $ignore_acl=false should we ignore the acl
	 * @param boolean $updateTS=true update the content history of the event
	 * @return int|boolean $cal_id > 0 or false on error (eg. permission denied)
	 */
	function save($event,$ignore_acl=false,$updateTS=true)
	{
		//echo '<p>'.__METHOD__.'('.array2string($event).",$ignore_acl)</p>\n";
		//error_log(__METHOD__.'('.array2string($event).",$etag)");
		// check if user has the permission to update / create the event
		if (!$ignore_acl && ($event['id'] && !$this->check_perms(EGW_ACL_EDIT,$event['id']) ||
			!$event['id'] && !$this->check_perms(EGW_ACL_EDIT,0,$event['owner']) &&
			!$this->check_perms(EGW_ACL_ADD,0,$event['owner'])))
		{
			return false;
		}

		// invalidate the read-cache if it contains the event we store now
		if ($event['id'] && $event['id'] == self::$cached_event['id']) self::$cached_event = array();

		$save_event = $event;
		// we run all dates through date2ts, to adjust to server-time and the possible date-formats
		foreach(array('start','end','modified','created','recur_enddate','recurrence') as $ts)
		{
			// we convert here from user-time to timestamps in server-time!
			if (isset($event[$ts])) $event[$ts] = $event[$ts] ? $this->date2ts($event[$ts],true) : 0;
		}
		// convert tzid name to integer tz_id, of set user default
		if (empty($event['tzid']) || !($event['tz_id'] = calendar_timezones::tz2id($event['tzid'])))
		{
			$event['tz_id'] = calendar_timezones::tz2id($event['tzid'] = egw_time::$user_timezone->getName());
		}
		// same with the recur exceptions
		if (isset($event['recur_exception']) && is_array($event['recur_exception']))
		{
			foreach($event['recur_exception'] as $n => $date)
			{
				$event['recur_exception'][$n] = $this->date2ts($date,true);
			}
		}
		// same with the alarms
		if (isset($event['alarm']) && is_array($event['alarm']))
		{
			foreach($event['alarm'] as $id => $alarm)
			{
				$event['alarm'][$id]['time'] = $this->date2ts($alarm['time'],true);
			}
		}
		$set_recurrences = false;
		$set_recurrences_start = 0;
		if (($cal_id = $this->so->save($event,$set_recurrences,$set_recurrences_start,0,$event['etag'])) && $set_recurrences && $event['recur_type'] != MCAL_RECUR_NONE)
		{
			$save_event['id'] = $cal_id;
			// unset participants to enforce the default stati for all added recurrences
			unset($save_event['participants']);
			$this->set_recurrences($save_event, $set_recurrences_start);
		}
		if ($updateTS) $GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id,$event['id'] ? 'modify' : 'add',time());

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

			return $this->check_perms(EGW_ACL_EDIT,0,$event['owner']);
		}
		// check if we have a category acl for the event or not (null)
		$access = $this->check_cat_acl(self::CAT_ACL_STATUS,$event);
		if (!is_null($access))
		{
			return $access;
		}
		// no access or denied access because of category acl --> regular check
		if (!is_numeric($uid))	// this is eg. for resources (r123)
		{
			$resource = $this->resource_info($uid);

			return EGW_ACL_EDIT & $resource['rights'];
		}
		// regular user and groups
		return $this->check_perms(EGW_ACL_EDIT,0,$uid);
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
	 * @param int $cat_id=null null to return array with all cats
	 * @return array with account_id => right pairs
	 */
	public static function get_cat_rights($cat_id=null)
	{
		if (!isset(self::$cat_rights_cache))
		{
			self::$cat_rights_cache = egw_cache::getSession('calendar','cat_rights',
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
			egw_cache::setSession('calendar','cat_rights',self::$cat_rights_cache);
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
		static $cache;

		if (!isset($cache[$cat_id]))
		{
			$all = $own = 0;
			$cat_rights = self::get_cat_rights($cat_id);
			if (!is_null($cat_rights))
			{
				static $memberships;
				if (is_null($memberships))
				{
					$memberships = $GLOBALS['egw']->accounts->memberships($user,true);
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
	 * @param int $recur_date=0 date to change, or 0 = all since now
	 * @param boolean $ignore_acl=false do not check the permisions for the $uid, if true
	 * @param boolean $updateTS=true update the content history of the event
	 * @return int number of changed recurrences
	 */
	function set_status($event,$uid,$status,$recur_date=0,$ignore_acl=false,$updateTS=true)
	{
		$cal_id = is_array($event) ? $event['id'] : $event;
		//echo "<p>calendar_boupdate::set_status($cal_id,$uid,$status,$recur_date)</p>\n";
		if (!$cal_id || (!$ignore_acl && !$this->check_status_perms($uid,$event)))
		{
			return false;
		}
		calendar_so::split_status($status, $quantity, $role);
		if (($Ok = $this->so->set_status($cal_id,is_numeric($uid)?'u':$uid[0],is_numeric($uid)?$uid:substr($uid,1),$status,$recur_date ? $this->date2ts($recur_date,true) : 0,$role)))
		{
			if ($updateTS) $GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id,'modify',time());

			static $status2msg = array(
				'R' => MSG_REJECTED,
				'T' => MSG_TENTATIVE,
				'A' => MSG_ACCEPTED,
			);
			if (isset($status2msg[$status]))
			{
				if (!is_array($event)) $event = $this->read($cal_id);
				if (isset($recur_date)) $event = $this->read($event['id'],$recur_date); //re-read the actually edited recurring event
				$this->send_update($status2msg[$status],$event['participants'],$event);
			}
		}
		return $Ok;
	}

	/**
	 * deletes an event
	 *
	 * @param int $cal_id id of the event to delete
	 * @param int $recur_date=0 if a single event from a series should be deleted, its date
	 * @param boolean $ignore_acl=false true for no ACL check, default do ACL check
	 * @return boolean true on success, false on error (usually permission denied)
	 */
	function delete($cal_id,$recur_date=0,$ignore_acl=false)
	{
		$event = $this->read($cal_id,$recur_date);

		if (!($event = $this->read($cal_id,$recur_date)) ||
			!$ignore_acl && !$this->check_perms(EGW_ACL_DELETE,$event))
		{
			return false;
		}
		$this->send_update(MSG_DELETED,$event['participants'],$event);

		if (!$recur_date || $event['recur_type'] == MCAL_RECUR_NONE)
		{
			$this->so->delete($cal_id);
			$GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id,'delete',time());

			// delete all links to the event
			egw_link::unlink(0,'calendar',$cal_id);
		}
		else
		{
			$event['recur_exception'][] = $recur_date = $this->date2ts($event['start']);
			unset($event['start']);
			unset($event['end']);
			$this->save($event);	// updates the content-history
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
	 */
	function _get_event_details($event,$action,&$event_arr,$disinvited=array())
	{
		$details = array(			// event-details for the notify-msg
			'id'          => $event['id'],
			'action'      => $action,
		);
		$event_arr = $this->event2array($event);
		foreach($event_arr as $key => $val)
		{
			$details[$key] = $val['data'];
		}
		$details['participants'] = $details['participants'] ? implode("\n",$details['participants']) : '';

		$event_arr['link']['field'] = lang('URL');
		$eventStart_arr = $this->date2array($event['start']); // give this as 'date' to the link to pick the right recurrence for the participants state
		$link = $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=calendar.calendar_uiforms.edit&cal_id='.$event['id'].'&date='.$eventStart_arr['full'].'&no_popup=1';
		// if url is only a path, try guessing the rest ;-)
		if ($link[0] == '/')
		{
			$link = ($GLOBALS['egw_info']['server']['enforce_ssl'] || $_SERVER['HTTPS'] ? 'https://' : 'http://').
				($GLOBALS['egw_info']['server']['hostname'] ? $GLOBALS['egw_info']['server']['hostname'] : $_SERVER['HTTP_HOST']).
				$link;
		}
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

		foreach(explode(',',$event['category']) as $cat_id)
		{
			list($cat) = $GLOBALS['egw']->categories->return_single($cat_id);
			$cat_string[] = stripslashes($cat['name']);
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
			0   => '',
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
			'data'	=> $GLOBALS['egw']->common->grab_owner_name($event['owner'])
		);

		$var['updated'] = Array(
			'field'	=> lang('Updated'),
			'data'	=> $this->format_date($event['modtime']).', '.$GLOBALS['egw']->common->grab_owner_name($event['modifier'])
		);

		$var['access'] = Array(
			'field'	=> lang('Access'),
			'data'	=> $event['public'] ? lang('Public') : lang('Private')
		);

		if (isset($event['participants']) && is_array($event['participants']))
		{
			$participants = $this->participants($event,true);
		}
		$var['participants'] = Array(
			'field'	=> lang('Participants'),
			'data'	=> $participants
		);

		// Repeated Events
		if($event['recur_type'] != MCAL_RECUR_NONE)
		{
			$var['recur_type'] = Array(
				'field'	=> lang('Repetition'),
				'data'	=> $this->recure2string($event),
			);
		}
		return $var;
	}

	/**
	 * log all updates to a file
	 *
	 * @param array $event2save event-data before calling save
	 * @param array $event_saved event-data read back from the DB
	 * @param array $old_event=null event-data in the DB before calling save
	 * @param string $type='update'
	 */
	function log2file($event2save,$event_saved,$old_event=null,$type='update')
	{
		if (!($f = fopen($this->log_file,'a')))
		{
			echo "<p>error opening '$this->log_file' !!!</p>\n";
			return false;
		}
		fwrite($f,$type.': '.$GLOBALS['egw']->common->grab_owner_name($this->user).': '.date('r')."\n");
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
	 * saves a new or updated alarm
	 *
	 * @param int $cal_id Id of the calendar-entry
	 * @param array $alarm array with fields: text, owner, enabled, ..
	 * @return string id of the alarm, or false on error (eg. no perms)
	 */
	function save_alarm($cal_id,$alarm)
	{
		if (!$cal_id || !$this->check_perms(EGW_ACL_EDIT,$alarm['all'] ? $cal_id : 0,!$alarm['all'] ? $alarm['owner'] : 0))
		{
			//echo "<p>no rights to save the alarm=".print_r($alarm,true)." to event($cal_id)</p>";
			return false;	// no rights to add the alarm
		}
		$alarm['time'] = $this->date2ts($alarm['time'],true);	// user to server-time

		$GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id, 'modify', time());

		return $this->so->save_alarm($cal_id,$alarm, $this->now_su);
	}

	/**
	 * delete one alarms identified by its id
	 *
	 * @param string $id alarm-id is a string of 'cal:'.$cal_id.':'.$alarm_nr, it is used as the job-id too
	 * @return int number of alarms deleted, false on error (eg. no perms)
	 */
	function delete_alarm($id)
	{
		list(,$cal_id) = explode(':',$id);

		if (!($alarm = $this->so->read_alarm($id)) || !$cal_id || !$this->check_perms(EGW_ACL_EDIT,$alarm['all'] ? $cal_id : 0,!$alarm['all'] ? $alarm['owner'] : 0))
		{
			return false;	// no rights to delete the alarm
		}

		$GLOBALS['egw']->contenthistory->updateTimeStamp('calendar',$cal_id, 'modify', time());

		return $this->so->delete_alarm($id, $this->now_su);
	}

	/**
	 * Find existing categories in database by name or add categories that do not exist yet
	 * currently used for ical/sif import
	 *
	 * @param array $catname_list names of the categories which should be found or added
	 * @param int $cal_id=-1 match against existing event and expand the returned category ids
	 *  by the ones the user normally does not see due to category permissions - used to preserve categories
	 * @return array category ids (found, added and preserved categories)
	 */
	function find_or_add_categories($catname_list, $cal_id=-1)
	{
		if ($cal_id && $cal_id > 0)
		{
			// preserve categories without users read access
			$old_event = $this->read($cal_id);
			$old_categories = explode(',',$old_event['category']);
			$old_cats_preserve = array();
			if (is_array($old_categories) && count($old_categories) > 0)
			{
				foreach ($old_categories as $cat_id)
				{
					if (!$this->categories->check_perms(EGW_ACL_READ, $cat_id))
					{
						$old_cats_preserve[] = $cat_id;
					}
				}
			}
		}

		$cat_id_list = array();
		foreach ($catname_list as $cat_name)
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
			if ($cat_id && $this->categories->check_perms(EGW_ACL_READ, $cat_id) &&
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
	 * @param boolean $relax=false if asked to relax, we only match against some key fields
	 * @return the calendar_id of the matching entry or false (if none matches)
	 */
	function find_event($event, $relax=false)
	{
		$query = array();
		if (isset($event['start']))
		{
			$query[] = 'cal_start='.$event['start'];
		}
		if (isset($event['end']))
		{
			$query[] = 'cal_end='.$event['end'];
		}

		foreach (array('title', 'location',
				 'public', 'non_blocking', 'category') as $key)
		{
			if (!empty($event[$key])) $query['cal_'.$key] = $event[$key];
		}

		if ($event['uid'] && ($uidmatch = $this->read($event['uid'])))
		{
			if ($event['recurrence'])
			{
				// Let's try to find a real exception first
				$query['cal_uid'] = $event['uid'];
				$query['cal_recurrence'] = $event['recurrence'];

				if ($foundEvents = parent::search(array(
					'query' => $query,
				)))
				{
					if(is_array($foundEvents))
					{
						$event = array_shift($foundEvents);
						return $event['id'];
					}
				}
				// Let's try the "status only" (pseudo) exceptions now
				if (($egw_event = $this->read($uidmatch['id'], $event['recurrence'])))
				{
					// Do we work with a pseudo exception here?
					$match = true;
					foreach (array('start', 'end', 'title', 'priority',
						'location', 'public', 'non_blocking') as $key)
					{
						if (isset($event[$key])
								&& $event[$key] != $egw_event[$key])
						{
							$match = false;
							break;
						}
					}
					if ($match && is_array($event['participants']))
					{
						foreach ($event['participants'] as $attendee => $status)
						{
							if (!isset($egw_event['participants'][$attendee])
									|| $egw_event['participants'][$attendee] != $status)
							{
								$match = false;
								break;
							}
							else
							{
								unset($egw_event['participants'][$attendee]);
							}
						}
						if ($match && !empty($egw_event['participants'])) $match = false;
					}
					if ($match)	return ($uidmatch['id'] . ':' . $event['recurrence']);

					return false; // We need to create a new pseudo exception
				}
			}
			else
			{
				return $uidmatch['id'];
			}
		}

		if ($event['id'] && ($found = $this->read($event['id'])))
		{
			// We only do a simple consistency check
			if ($found['title'] == $event['title']
				&& $found['start'] == $event['start']
				&& $found['end'] == $event['end'])
				{
					return $found['id'];
				}
		}
		unset($event['id']);

		if($foundEvents = parent::search(array(
			'query' => $query,
		)))
		{
			if(is_array($foundEvents))
			{
				$event = array_shift($foundEvents);
				return $event['id'];
			}
		}
		return false;
	}
}
