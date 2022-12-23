<?php
/**
 * EGroupware - Calendar's forms of the UserInterface
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-18 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;

/**
 * calendar UserInterface forms: view and edit events, freetime search
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * The state of the UI elements is managed in the uical class, which all UI classes extend.
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the bocal class !!!
 */
class calendar_uiforms extends calendar_ui
{
	var $public_functions = array(
		'freetimesearch'  => True,
		'ajax_add' => true,
		'ajax_conflicts' => true,
		'edit' => true,
		'process_edit' => true,
		'export' => true,
		'import' => true,
		'cat_acl' => true,
		'meeting' => true,
		'mail_import' => true,
		'notify' => true
	);

	/**
	 * Standard durations used in edit and freetime search
	 *
	 * @var array
	 */
	var $durations = array();

	/**
	 * default locking time for entries, that are opened by another user
	 *
	 * @var int lock time in seconds
	 */
	var $locktime_default=1;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct(true);	// call the parent's constructor

		for ($n=15; $n <= 16*60; $n+=($n < 60 ? 15 : ($n < 240 ? 30 : ($n < 600 ? 60 : 120))))
		{
			$this->durations[$n*60] = sprintf('%d:%02d',$n/60,$n%60);
		}
	}

	/**
	 * Create a default event (adding a new event) by evaluating certain _GET vars
	 *
	 * @return array event-array
	 */
	function &default_add_event()
	{
		$extra_participants = $_GET['participants'] ?
			(!is_array($_GET['participants']) ? explode(',',$_GET['participants']) : $_GET['participants']) :
			array();

		// if participant is a contact, add its link title as title
		foreach($extra_participants as $uid)
		{
			if ($uid[0] == 'c')
			{
				$title = Link::title('addressbook', substr($uid, 1));
				break;
			}
		}

		if($_GET['title'])
		{
			$title = $_GET['title'];
		}
		if (isset($_GET['owner']))
		{
			$owner = $_GET['owner'];
			if(!is_array($owner) && strpos($owner, ','))
			{
				$owner = explode(',',$owner);
			}
			if(is_array($owner))
			{
				// old behavior "selected" should also be used for not set preference, therefore we need to test for !== '0'
				if($this->cal_prefs['default_participant'] !== '0' || count($extra_participants) === 0 && count($owner) === 1)
				{
					$extra_participants += $owner;
				}
				$owner = count($owner) > 1 ? $this->user : $owner[0];
			}
			else if ($owner)
			{
				$extra_participants[] = $owner;
			}
		}
		else
		{
			// old behavior "selected" should also be used for not set preference, therefore we need to test for === '0'
			$owner = $this->cal_prefs['default_participant'] === '0' ? $this->user : $this->owner;
		}

		if (!$owner || !is_numeric($owner) || $GLOBALS['egw']->accounts->get_type($owner) != 'u' ||
			!$this->bo->check_perms(Acl::ADD,0,$owner))
		{
			if ($owner)	// make an owner who is no user or we have no add-rights a participant
			{
				if(!is_array($owner))
				{
					$owner = explode(',',$owner);
				}
				// if we come from ressources we don't need any users selected in calendar
				if (!isset($_GET['participants']) || $_GET['participants'][0] != 'r')
				{
					foreach($owner as $uid)
					{
						$extra_participants[] = $uid;
					}
				}
			}
			$owner = $this->user;
		}
		//error_log("this->owner=$this->owner, _GET[owner]=$_GET[owner], user=$this->user => owner=$owner, extra_participants=".implode(',',$extra_participants).")");

		if(isset($_GET['start']))
		{
			$start = Api\DateTime::to($_GET['start'], 'ts');
		}
		else
		{
			$ts = new Api\DateTime();
			$ts->setUser();
			$start = $this->bo->date2ts(array(
				'full' => isset($_GET['date']) && (int) $_GET['date'] ? (int) $_GET['date'] : $this->date,
				'hour' => (int) (isset($_GET['hour']) ? $_GET['hour'] : ($ts->format('H')+1)),
				'minute' => (int) $_GET['minute'],
			));
		}
		//echo "<p>_GET[date]=$_GET[date], _GET[hour]=$_GET[hour], _GET[minute]=$_GET[minute], this->date=$this->date ==> start=$start=".date('Y-m-d H:i',$start)."</p>\n";

		$participant_types['u'] = $participant_types = $participants = array();
		foreach($extra_participants as $uid)
		{
			if (isset($participants[$uid])) continue;	// already included

			if (!$this->bo->check_acl_invite($uid)) continue;	// no right to invite --> ignored

			if (is_numeric($uid))
			{
				$participants[$uid] = $participant_types['u'][$uid] =
					calendar_so::combine_status($uid == $this->user ? 'A' : 'U',1,
					($uid == $this->user || ($uid == $owner && $this->bo->check_perms(Acl::ADD,0,$owner))) ? 'CHAIR' : 'REQ-PARTICIPANT');
			}
			elseif (is_array($this->bo->resources[$uid[0]]))
			{
				// Expand mailing lists
				if($uid[0] == 'l')
				{
					foreach($this->bo->enum_mailing_list($uid) as $contact)
					{
						$participants[$contact] = $participant_types['c'][substr($contact,1)] =
							calendar_so::combine_status('U',1,'REQ-PARTICIPANT');
					}
					continue;
				}
				// if contact is a user, use the user instead (as the GUI)
				if ($uid[0] == 'c' && ($account_id = $GLOBALS['egw']->accounts->name2id(substr($uid,1),'person_id')))
				{
					$uid = $account_id;
					$participants[$uid] = $participant_types['u'][$uid] =
						calendar_so::combine_status($uid == $this->user ? 'A' : 'U',1,
						($uid == $this->user || ($uid == $owner && $this->bo->check_perms(Acl::ADD,0,$owner))) ? 'CHAIR' : 'REQ-PARTICIPANT');
					continue;
				}
				$res_data = $this->bo->resources[$uid[0]];
				list($id,$quantity) = explode(':',substr($uid,1));
				if (($status = $res_data['new_status'] ? ExecMethod($res_data['new_status'],$id) : 'U'))
				{
					$participants[$uid] = $participant_types[$uid[0]][$id] =
						calendar_so::combine_status($status,$quantity,'REQ-PARTICIPANT');
				}
			}
		}
		if (!$participants)	// if all participants got removed, include current user
		{
			$participants[$this->user] = $participant_types['u'][$this->user] = calendar_so::combine_status('A',1,'CHAIR');
		}
		if(isset($_GET['cat_id']))
		{
			$cat_id = explode(',',$_GET['cat_id']);
			foreach($cat_id as &$cat)
			{
				$cat = (int)$cat;
			}
		}
		else
		{
			$cat_id = $this->cal_prefs['default_category'];
		}
		$duration = isset($_GET['duration']) ? (int)$_GET['duration'] : (int) $this->bo->cal_prefs['defaultlength']*60;
		if(isset($_GET['end']))
		{
			$end = Api\DateTime::to($_GET['end'], 'ts');
			$duration = $end - $start;
		}
		else
		{
			$end = $start + $duration;
		}
		$whole_day = ($duration + 60 == DAY_s);

		$alarms = array();
		$alarm_pref = $whole_day ? 'default-alarm-wholeday' : 'default-alarm';
		// if default alarm set in prefs --> add it
		// we assume here that user does NOT have a whole-day but no regular default-alarm, no whole-day!
		if((string)$this->cal_prefs[$alarm_pref] !== '')
		{
			$offset = 60 * $this->cal_prefs[$alarm_pref];
			$alarms[1] = array(
				'default' => 1,
				'offset'  => $offset,
				'time'    => $start - $offset,
				'all'     => $this->cal_prefs['default-alarm-for'] === 'all',
				'owner'   => $owner,
				'id'      => 1,
			);
		}
		// add automatic alarm 5min before videoconference for all participants
		if (!empty($_GET['videoconference']))
		{
			$offset = 5 * 60;
			$alarms[1+count($alarms)] =  array(
				'offset' => $offset,
				'time'   => $start - $offset,
				'all'    => true,
				'owner'  => $owner,
				'id'	=> 2,
			);
		}

		$ret = array(
			'participant_types' => $participant_types,
			'participants' => $participants,
			'owner' => $owner,
			'start' => $start,
			'end'   => $end,
			'tzid'  => $this->bo->common_prefs['tz'],
			'priority' => 2,	// normal
			'public'=> $this->cal_prefs['default_private'] ? 0 : 1,
			'alarm' => $alarms,
			'recur_exception' => array(),
			'title' => $title ? $title : '',
			'category' => $cat_id,
			'videoconference' => !empty($_GET['videoconference']),
			'##notify_externals' => !empty($_GET['videoconference']) ? 'yes' : $this->cal_prefs['notify_externals'],
		);
		return $ret;
	}

	/**
	 * Process the edited event and evtl. call edit to redisplay it
	 *
	 * @param array $content posted eTemplate content
	 * @ToDo add conflict check / available quantity of resources when adding participants
	 */
	function process_edit($content)
	{
		if (!is_array($content))	// redirect from etemplate, if POST empty
		{
			return $this->edit(null,null,strip_tags($_GET['msg']));
		}
		// clear notification errors
		notifications::errors(true);
		$messages = null;
		$msg_permission_denied_added = false;

		// We'd like to just refresh the data as that's the fastest, but some changes
		// affect more than just one event widget, so require a full refresh.
		// $update_type is one of the update types
		// (add, edit, update, delete)
		$update_type = $content['id'] ? ($content['recur_type'] == MCAL_RECUR_NONE ? 'update' : 'edit') : 'add';

		$button = @key((array)$content['button']);
		if (!$button && $content['action']) $button = $content['action'];	// action selectbox
		unset($content['button']); unset($content['action']);

		$view = $content['view'];
		if ($button == 'ical')
		{
			$msg = $this->export($content['id'],true);
		}
		// delete a recur-exception
		if (!empty($content['recur_exception']['delete_exception']))
		{
			$date = key($content['recur_exception']['delete_exception']);
			// eT2 converts time to
			if (!is_numeric($date)) $date = Api\DateTime::to (str_replace('Z','', $date), 'ts');
			unset($content['recur_exception']['delete_exception']);
			if (($key = array_search($date,$content['recur_exception'])) !== false)
			{
				// propagate the exception to a single event
				$recur_exceptions = $this->bo->so->get_related($content['uid']);
				foreach ($recur_exceptions as $id)
				{
					if (!($exception = $this->bo->read($id)) ||
							$exception['recurrence'] != $content['recur_exception'][$key]) continue;
					$exception['uid'] = Api\CalDAV::generate_uid('calendar', $id);
					$exception['reference'] = $exception['recurrence'] = 0;
					$this->bo->update($exception, true, true,false,true,$messages,$content['no_notifications']);
					break;
				}
				unset($content['recur_exception'][$key]);
				$content['recur_exception'] = array_values($content['recur_exception']);
			}
			$update_type = 'edit';
		}
		// delete an alarm
		if (!empty($content['alarm']['delete_alarm']))
		{
			$id = key($content['alarm']['delete_alarm']);
			//echo "delete alarm $id"; _debug_array($content['alarm']['delete_alarm']);

			if ($content['id'])
			{
				if ($this->bo->delete_alarm($id))
				{
					$msg = lang('Alarm deleted');
					unset($content['alarm'][$id]);
				}
				else
				{
					$msg = lang('Permission denied');
				}
			}
			else
			{
				unset($content['alarm'][$id]);
			}
		}
		if($content['end'] && $content['start'] >= $content['end'])
		{
			unset($content['end']);
			$content['duration'] = $this->bo->cal_prefs['defaultlength']*60;
		}
		if ($content['duration'])
		{
			$content['end'] = $content['start'] + $content['duration'];
		}
		// fix default alarm for a new (whole day) event, to be according to default-alarm(-wholeday) pref
		if ($content['alarm'][1]['default'])
		{
			$def_alarm = $this->cal_prefs['default-alarm'.($content['whole_day'] ? '-wholeday' : '')];
			if ((string)$def_alarm === '')
			{
				unset($content['alarm'][1]);	// '' = no alarm on whole day --> delete it
			}
			else
			{
				$content['alarm'][1]['offset'] = $offset = 60 * $def_alarm;
				$content['start'] = $this->bo->date2array($content['start']);
				$content['start'][1]['time'] = $this->bo->date2ts($content['start']) - $offset;
				$content['start'] = $this->bo->date2ts($content['start']);
			}
		}
		else if ($content['cal_id'] && count($content['alarm']) > 0 && current($content['alarm'])['default'] &&
			// Existing event, check for change from/to whole day
			($old = $this->bo->read($content['cal_id'])) && $old['whole_day'] !== $content['whole_day'] &&
			($def_alarm = $this->cal_prefs['default-alarm'.($content['whole_day'] ? '-wholeday' : '')])
		)
		{
			// Reset default alarm
			$old_default = array_shift($content['alarm']);
			$this->bo->delete_alarm($old_default['id']);
			$offset = 60 * $def_alarm;
			array_unshift($content['alarm'], array(
				'default' => 1,
				'offset' => $offset ,
				'time'   => $content['start'] - $offset,
				'all'    => false,
				'owner'  => 0,
				'id'	=> 1
			));
		}

		$event = $content;
		unset($event['new_alarm']);
		unset($event['alarm']['delete_alarm']);
		unset($event['duration']);

		if (in_array($button,array('ignore','freetime','reedit','confirm_edit_series')))
		{
			// no conversation necessary, event is already in the right format
		}
		else
		{
			// convert content => event
			if ($content['whole_day'])
			{
				$event['start'] = $this->bo->date2array($event['start']);
				$event['start']['hour'] = $event['start']['minute'] = 0; unset($event['start']['raw']);
				$event['start'] = $this->bo->date2ts($event['start']);
				$event['end'] = $this->bo->date2array($event['end']);
				$event['end']['hour'] = 23; $event['end']['minute'] = $event['end']['second'] = 59; unset($event['end']['raw']);
				$event['end'] = $this->bo->date2ts($event['end']);
			}
			// some checks for recurrences, if you give a date, make it a weekly repeating event and visa versa
			if ($event['recur_type'] == MCAL_RECUR_NONE && $event['recur_data']) $event['recur_type'] = MCAL_RECUR_WEEKLY;
			if ($event['recur_type'] == MCAL_RECUR_WEEKLY && !$event['recur_data'])
			{
				$event['recur_data'] = 1 << (int)date('w',$event['start']);
			}
			if ($event['recur_type'] != MCAL_RECUR_NONE && !isset($event['recur_enddate']))
			{
				// No recur end date, make sure it's set to something or it won't be changed
				$event['recur_enddate'] = 0;
			}
			if (isset($content['participants']))
			{

				$event['participants'] = $event['participant_types'] = array();

				foreach($content['participants'] as $key => $data)
				{
					switch($key)
					{
						case 'delete':		// handled in default
						case 'quantity':	// handled in new_resource
						case 'role':		// handled in add, account or resource
						case 'cal_resources':
						case 'status_date':
							break;
						case 'notify_externals':
							$event['##notify_externals'] = $data;
							break;
						case 'participant':
							foreach($data as $participant)
							{
								if (is_null($participant))
								{
									continue;
								}

								// email or rfc822 addresse (eg. "Ralf Becker <ralf@domain.com>")
								$email = array();
								if(preg_match('/^(.*<)?([a-z0-9_.+#$%&-]+@[a-z0-9_.-]{5,})>?$/i',$participant,$email))
								{
									$status = calendar_so::combine_status('U',$content['participants']['quantity'],$content['participants']['role']);
									if (($data = $GLOBALS['egw']->accounts->name2id($email[2],'account_email')) && $this->bo->check_acl_invite($data))
									{
										$event['participants'][$data] = $event['participant_types']['u'][$data] = $status;
									}
									elseif ((list($data) = ExecMethod2('addressbook.addressbook_bo.search',array(
										'email' => $email[2],
										'email_home' => $email[2],
									),true,'','','',false,'OR')))
									{
										$event['participants']['c'.$data['id']] = $event['participant_types']['c'][$data['id']] = $status;
									}
									else
									{
										$event['participants']['e'.$participant] = $event['participant_types']['e'][$participant] = $status;
									}
								}
								else
								{
									if(is_numeric($participant))
									{
										$uid = $participant;
										$id = $participant;
										$resource = $this->bo->resources[''];
									}
									else
									{
										$uid = $participant;
										$id = substr($participant,1);
										$resource = $this->bo->resources[$participant[0]];
									}
									if(!$this->bo->check_acl_invite($uid))
									{
										if(!$msg_permission_denied_added)
										{
											$msg .= lang('Permission denied!');
											$msg_permission_denied_added = true;
										}
										continue;
									}

									$type = $resource['type'];
									$status = isset($this->bo->resources[$type]['new_status']) ?
										ExecMethod($this->bo->resources[$type]['new_status'],$id) :
										($uid == $this->bo->user ? 'A' : 'U');

									// Expand mailing lists
									if($type == 'l')
									{
										// Ignore ACL here, allow inviting anyone in the list
										foreach($this->bo->enum_mailing_list($participant, true) as $contact)
										{
											// Mailing lists can contain users, so allow for that possibility
											$_type = is_numeric($contact) ? '' : $contact[0];
											$_uid = is_numeric($contact) ? $contact : substr($contact,1);
											$event['participants'][$contact] = $event['participant_types'][$_type][$_uid] =
												calendar_so::combine_status($status,$content['participants']['quantity'],$content['participants']['role']);
										}
										continue;
									}
									if ($status)
									{
										$res_info = $this->bo->resource_info($uid);
										// todo check real availability = maximum - already booked quantity
										if (isset($res_info['useable']) && $content['participants']['quantity'] > $res_info['useable'])
										{
											$msg .= lang('Maximum available quantity of %1 exceeded!',$res_info['useable']);
											foreach(array('quantity','resource','role') as $n)
											{
												$event['participants'][$n] = $content['participants'][$n];
											}
											continue;
										}
										else
										{
											$event['participants'][$uid] = $event['participant_types'][$type][$id] =
												calendar_so::combine_status($status,$content['participants']['quantity'],$content['participants']['role']);
										}
									}
								}
							}
							break;
						case 'add':
							if (!$content['participants']['participant'])
							{
								$msg = lang('You need to select an account, contact or resource first!');
							}
							break;

						default:		// existing participant row
							if (!is_array($data)) continue 2;	// widgets in participant tab, above participant list
							$quantity = $status = $role = null;
							foreach(array('uid','status','quantity','role') as $name)
							{
								$$name = $data[$name];
							}
							if ($content['participants']['delete'][$uid] || $content['participants']['delete'][md5($uid)])
							{
								$uid = false;	// entry has been deleted
							}
							elseif ($uid)
							{
								if (is_numeric($uid))
								{
									$id = $uid;
									$type = '';
								}
								else
								{
									$id = substr($uid,1);
									$type = $uid[0];
								}
								if ($data['old_status'] != $status && !(!$data['old_status'] && $status == 'G'))
								{
									//echo "<p>$uid: status changed '$data[old_status]' --> '$status<'/p>\n";
									$new_status = calendar_so::combine_status($status, $quantity, $role);
									if ($this->bo->set_status($event['id'],$uid,$new_status,isset($content['edit_single']) ? $content['participants']['status_date'] : 0, false, true, $content['no_notifications']))
									{
										// Update main window
										$d = new Api\DateTime($content['edit_single'], Api\DateTime::$user_timezone);
										$client_updated = $this->update_client($event['id'], $d);

										// refreshing the calendar-view with the changed participant-status
										if($event['recur_type'] != MCAL_RECUR_NONE)
										{
											$msg = lang('Status for all future scheduled days changed');
										}
										else
										{
											if(isset($content['edit_single']))
											{
												$msg = lang('Status for this particular day changed');
												// prevent accidentally creating a real exception afterwards
												$view = true;
												$hide_delete = true;
											}
											else
											{
												$msg = lang('Status changed');
												//Refresh the event in the main window after changing status
												Framework::refresh_opener($msg, 'calendar', $event['id'], $client_updated ? 'update' : 'delete');
											}
										}
										if (!$content['no_popup'])
										{
											//we are handling refreshing for status changes on client side
										}
										if ($status == 'R' && $event['alarm'])
										{
											// remove from bo->set_status deleted alarms of rejected users from UI too
											foreach($event['alarm'] as $alarm_id => $alarm)
											{
												if ((string)$alarm['owner'] === (string)$uid)
												{
													unset($event['alarm'][$alarm_id]);
												}
											}
										}
									}
								}
								if ($uid && $status != 'G')
								{
									$event['participants'][$uid] = $event['participant_types'][$type][$id] =
										calendar_so::combine_status($status,$quantity,$role);
								}
							}
							break;
					}
				}
			}
		}
		$preserv = array(
			'view'			=> $view,
			'hide_delete'	=> $hide_delete,
			'edit_single'	=> $content['edit_single'],
			'reference'		=> $content['reference'],
			'recurrence'	=> $content['recurrence'],
			'actual_date'	=> $content['actual_date'],
			'no_popup'		=> $content['no_popup'],
			'tabs'			=> $content['tabs'],
			'template'      => $content['template'],
		);
		$noerror=true;

		//error_log(__METHOD__.$button.'#'.array2string($content['edit_single']).'#');

		$ignore_conflicts = $status_reset_to_unknown = false;

		switch((string)$button)
		{
			case 'ignore':
				$ignore_conflicts = true;
				$button = $event['button_was'];	// save or apply
				unset($event['button_was']);
				break;

		}

		switch((string)$button)
		{
		case 'exception':	// create an exception in a recuring event
			$msg = $this->_create_exception($event,$preserv);
			break;
		case 'edit':
			// Going from add dialog to full edit dialog
			unset($preserv['template']);
			unset($event['template']);
			break;

		case 'copy':	// create new event with copied content, some content need to be unset to make a "new" event
			unset($event['id']);
			unset($event['uid']);
			unset($event['reference']);
			unset($preserv['reference']);
			unset($event['recurrence']);
			unset($preserv['recurrence']);
			unset($event['recur_exception']);
			unset($event['edit_single']);	// in case it has been set
			unset($event['modified']);
			unset($event['modifier']);
			unset($event['caldav_name']);
			$event['owner'] = !(int)$event['owner'] || !$this->bo->check_perms(Acl::ADD,0,$event['owner']) ? $this->user : $event['owner'];

			// Clear participant stati
			foreach($event['participant_types'] as $type => &$participants)
			{
				foreach($participants as $id => &$p_response)
				{
					if($type == 'u' && $id == $event['owner']) continue;
					calendar_so::split_status($p_response, $quantity, $role);
					// if resource defines callback for status of new status (eg. Resources app acknowledges direct booking acl), call it
					$status = isset($this->bo->resources[$type]['new_status']) ? ExecMethod($this->bo->resources[$type]['new_status'],$id) : 'U';
					$p_response = calendar_so::combine_status($status,$quantity,$role);
				}
			}

			// Copy alarms
			if (is_array($event['alarm']))
			{
				$alarm_index = 0;
				$alarms = $event['alarm'];
				$event['alarm'] = Array();
				foreach($alarms as $n => $alarm)
				{
					unset($alarm['cal_id']);
					$alarm['id'] = $alarm_index++;
					$event['alarm'][] = $alarm;
				}
			}

			// Get links to be copied
			// With no ID, $content['link_to']['to_id'] is used
			$content['link_to'] = array('to_app' => 'calendar', 'to_id' => 0);
			foreach(Link::get_links('calendar', $content['id']) as $link)
			{
				if ($link['app'] != Link::VFS_APPNAME)
				{
					Link::link('calendar', $content['link_to']['to_id'], $link['app'], $link['id'], $link['remark']);
				}
				elseif ($link['app'] == Link::VFS_APPNAME)
				{
					Link::link('calendar', $content['link_to']['to_id'], Link::VFS_APPNAME, array(
						'tmp_name' => Link::vfs_path($link['app2'], $link['id2']).'/'.$link['id'],
						'name' => $link['id'],
					), $link['remark']);
				}
			}
			unset($link);
			$preserv['view'] = $preserv['edit_single'] = false;
			$msg = lang('%1 copied - the copy can now be edited', lang(Link::get_registry('calendar','entry')));
			$event['title'] = lang('Copy of:').' '.$event['title'];
			break;

		case 'mail':
		case 'sendrequest':
		case 'save':
		case 'print':
		case 'apply':
		case 'infolog':
			if ($event['id'] && !$this->bo->check_perms(Acl::EDIT,$event))
			{
				$msg = lang('Permission denied');
				$button = '';
				break;
			}
			if ($event['start'] > $event['end'])
			{
				$msg = lang('Error: Starttime has to be before the endtime !!!');
				$button = '';
				break;
			}
			if ($event['recur_type'] != MCAL_RECUR_NONE && $event['recur_enddate'] && $event['start'] > $event['recur_enddate'])
			{
				$msg = lang('repetition').': '.lang('Error: Starttime has to be before the endtime !!!');
				$button = '';
				break;
			}
			if ($event['recur_type'] != MCAL_RECUR_NONE && $event['end']-$event['start'] > calendar_rrule::recurrence_interval($event['recur_type'], $event['recur_interval']))
			{
				$msg = lang('Error: Duration of event longer then recurrence interval!');
				$button = '';
				break;
			}
			if (!$event['participants'])
			{
				$msg = lang('Error: no participants selected !!!');
				$button = '';
				break;
			}
			// if private event with ressource reservation is forbidden
			if (!$event['public'] && $GLOBALS['egw_info']['server']['no_ressources_private'])
			{
				foreach (array_keys($event['participants']) as $uid)
				{
					if ($uid[0] == 'r') //ressource detection
					{
						$msg = lang('Error: ressources reservation in private events is not allowed!!!');
						$button = '';
						break 2; //break foreach and case
					}
				}
			}
			if ($content['edit_single'])	// we edited a single event from a series
			{
				$event['reference'] = $event['id'];
				$event['recurrence'] = $content['edit_single'];
				unset($event['id']);
				$conflicts = $this->bo->update($event,$ignore_conflicts,true,false,true,$messages,$content['no_notifications']);
				if (!is_array($conflicts) && $conflicts)
				{
					// now we need to add the original start as recur-execption to the series
					$recur_event = $this->bo->read($event['reference']);
					$recur_event['recur_exception'][] = $content['edit_single'];
					// check if we need to move the alarms, because they are next on that exception
					foreach($recur_event['alarm'] as $id => $alarm)
					{
						if ($alarm['time'] == $content['edit_single'] - $alarm['offset'])
						{
							$rrule = calendar_rrule::event2rrule($recur_event, true);
							foreach ($rrule as $time)
							{
								if ($content['edit_single'] < $time->format('ts'))
								{
									$alarm['time'] = $time->format('ts') - $alarm['offset'];
									$this->bo->save_alarm($event['reference'], $alarm);
									break;
								}
							}
						}
					}
					unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
					unset($recur_event['alarm']);	// unsetting alarms too, as they cant be updated without start!
					$this->bo->update($recur_event,true);	// no conflict check here

					// Save links
					if($content['links'])
					{
						Link::link('calendar', $event['id'], $content['links']['to_id']);
					}

					if(Api\Json\Response::isJSONResponse())
					{
						// Sending null will trigger a removal of the original
						// for that date
						Api\Json\Response::get()->generic('data', array('uid' => 'calendar::'.$content['reference'].':'.$content['actual_date'], 'data' => null));
					}

					unset($recur_event);
					unset($event['edit_single']);			// if we further edit it, it's just a single event
					unset($preserv['edit_single']);
				}
				else	// conflict or error, we need to reset everything to the state befor we tried to save it
				{
					$event['id'] = $event['reference'];
					$event['reference'] = $event['recurrence'] = 0;
					$event['uid'] = $content['uid'];
				}
				$update_type = 'edit';
			}
			else	// we edited a non-reccuring event or the whole series
			{
				if (($old_event = $this->bo->read($event['id'])))
				{
					if ($event['recur_type'] != MCAL_RECUR_NONE)
					{
						$update_type = 'edit';

						// we edit a existing series event
						if ($event['start'] != $old_event['start'] ||
							$event['whole_day'] != $old_event['whole_day'] ||
							$event['end'] != $old_event['end'])
						{
							// calculate offset against old series start or clicked recurrance,
							// depending on which is smaller
							$offset = $event['start'] - $old_event['start'];
							if (abs($offset) > abs($off2 = $event['start'] - $event['actual_date']))
							{
								$offset = $off2;
							}
							$msg = $this->_break_recurring($event, $old_event, $event['actual_date'] + $offset,$content['no_notifications']);
							if($msg)
							{
								$noerror = false;
							}
						}
					}
					else
					{
						if ($old_event['start'] != $event['start'] ||
							$old_event['end'] != $event['end'] ||
							$event['whole_day'] != $old_event['whole_day'])
						{
							// check if we need to move the alarms, because they are relative
							$this->bo->check_move_alarms($event, $old_event);
						}
					}
				}
				// Update alarm (default alarm or set alarm before change start date)
				// for new event.
				elseif (is_array($event['alarm']) && ($event['alarm'][1]['time'] + $event['alarm'][1]['offset'] != $event['start']))
				{
					$this->bo->check_move_alarms($event);
				}
				// Adding participants needs to be done as an edit, in case we
				// have participants visible in seperate calendars
				if(is_array($old_event['participants']) && count(array_diff_key($event['participants'], $old_event['participants'])))
				{
					$update_type = 'edit';
				}
				// Changing category may affect event filtering
				if($this->cal_prefs['saved_states']['cat_id'] && $old_event['category'] != $event['category'])
				{
					$update_type = 'edit';
				}
				$conflicts = $this->bo->update($event,$ignore_conflicts,true,false,true,$messages,$content['no_notifications']);
				unset($event['ignore']);
			}
			if (is_array($conflicts))
			{
				$event['button_was'] = $button;	// remember for ignore
				return $this->conflicts($event,$conflicts,$preserv);
			}

			// Event spans multiple days, need an edit to make sure they all get updated
			// We could check old date, as removing from days could still be an update
			if(date('Ymd', $event['start']) != date('Ymd', $event['end']))
			{
				$update_type = 'edit';
			}
			// check if there are messages from update, eg. removed participants or Api\Categories because of missing rights
			if ($messages)
			{
				$msg  .= ($msg ? ', ' : '').implode(', ',$messages);
			}
			if ($conflicts === 0)
			{
				$msg .= ($msg ? ', ' : '') .lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars(Egw::link('/index.php',array(
								'menuaction' => 'calendar.calendar_uiforms.edit',
								'cal_id'    => $content['id'],
							))).'">','</a>');
				$noerror = false;
			}
			elseif ($conflicts > 0)
			{
				// series moved by splitting in two --> move alarms and exceptions
				if ($old_event && $old_event['id'] != $event['id'])
				{
					$update_type = 'edit';
					foreach ((array)$old_event['alarms'] as $alarm)
					{
						// check if alarms still needed in old event, if not delete it
						$event_time = $alarm['time'] + $alarm['offset'];
						if ($event_time >= $this->bo->now_su)
						{
							$this->bo->delete_alarm($alarm['id']);
						}
						$alarm['time'] += $offset;
						unset($alarm['id']);
						// if alarm would be in the past (eg. event moved back) --> move to next possible recurrence
						if ($alarm['time'] < $this->bo->now_su)
						{
							if (($next_occurrence = $this->bo->read($event['id'], $this->bo->now_su+$alarm['offset'], true)))
							{
								$alarm['time'] =  $next_occurrence['start'] - $alarm['offset'];
							}
							else
							{
								$alarm = false;	// no (further) recurence found --> ignore alarm
							}
						}
						// alarm is currently on a previous recurrence --> set for first recurrence of new series
						elseif ($event_time < $event['start'])
						{
							$alarm['time'] =  $event['start'] - $alarm['offset'];
						}
						if ($alarm)
						{
							$alarm['id'] = $this->bo->save_alarm($event['id'], $alarm);
							$event['alarm'][$alarm['id']] = $alarm;
						}
					}
					// attach all future exceptions to the new series
					$events =& $this->bo->search(array(
						'query' => array('cal_uid' => $old_event['uid']),
						'filter' => 'owner',  // return all possible entries
						'daywise' => false,
						'date_format' => 'ts',
					));
					foreach ((array)$events as $exception)
					{
						if ($exception['recurrence'] > $this->bo->now_su)
						{
							$exception['recurrence'] += $offset;
							$exception['reference'] = $event['id'];
							$exception['uid'] = $event['uid'];
							$msg = null;
							$this->bo->update($exception, true, true, true, true, $msg, $content['no_notifications']);
						}
					}
				}

				$message = lang('Event saved');
				if ($status_reset_to_unknown)
				{
					foreach((array)$event['participants'] as $uid => $status)
					{
						if ($uid[0] != 'c' && $uid[0] != 'e' && $uid != $this->bo->user)
						{
							calendar_so::split_status($status,$q,$r);
							$status = calendar_so::combine_status('U',$q,$r);
							$this->bo->set_status($event['id'], $uid, $status, 0, true);
						}
					}
					$message .= lang(', stati of participants reset');
				}

				$response = Api\Json\Response::get();
				if($response && $update_type != 'delete' && !$client_updated)
				{
					$client_updated = $this->update_client($event['id'], null, is_array($old_event) ? $old_event : []);
				}

				$msg = $message . ($msg ? ', ' . $msg : '');
				Framework::refresh_opener($msg, 'calendar', $event['id'], $client_updated ? ($event['recur_type'] ? 'edit' : $update_type) : 'delete');
				// writing links for new entry, existing ones are handled by the widget itself
				if (!$content['id'] && is_array($content['link_to']['to_id']))
				{
					Link::link('calendar',$event['id'],$content['link_to']['to_id']);
				}
			}
			else
			{
				$msg = lang('Error: saving the event !!!');
			}
			break;

		case 'cancel':
			if($content['cancel_needs_refresh'])
			{
				Framework::refresh_opener($msg, 'calendar');
			}
			break;

		case 'delete':					// delete of event (regular or series)
			$exceptions_kept = null;
			if ($this->bo->delete($event['id'], (int)$content['edit_single'], false, $event['no_notifications'],
				$content['delete_exceptions'] == 'true', $exceptions_kept))
			{
				if ($event['recur_type'] != MCAL_RECUR_NONE && $content['reference'] == 0 && !$content['edit_single'])
				{
					$msg = lang('Series deleted');
					if ($exceptions_kept) $msg .= lang(', exceptions preserved');
				}
				else
				{
					$msg = lang('Event deleted');
				}

			}
			break;

		case 'freetime':
			// the "click" has to be in onload, to make sure the button is already created
			$event['button_was'] = $button;
			break;

		case 'add_alarm':
			$time = $content['start'];
			$offset = $time - $content['new_alarm']['date'];
			if ($event['recur_type'] != MCAL_RECUR_NONE &&
				($next_occurrence = $this->bo->read($event['id'], $this->bo->now_su + $offset, true)) &&
				$time < $next_occurrence['start'])
			{
				$content['new_alarm']['date'] = $next_occurrence['start'] - $offset;
			}
			// Avoid duplicates
			foreach($content['alarm'] as $key => $alarm)
			{
				if($alarm['offset'] == $offset && (
						($alarm['all'] && $content['new_alarm']['owner'] == 0) ||
						(!$alarm['all'] && $alarm['owner'] == $content['new_alarm']['owner'])
				))
				{
					break 2;
				}
			}
			if ($this->bo->check_perms(Acl::EDIT,!$content['new_alarm']['owner'] ? $event : 0,$content['new_alarm']['owner']))
			{
				$alarm = array(
					'offset' => $offset,
					'time'   => $content['new_alarm']['date'],
					'all'    => !$content['new_alarm']['owner'],
					'owner'  => $content['new_alarm']['owner'] ? $content['new_alarm']['owner'] : $this->user,
				);
				if ($alarm['time'] < $this->bo->now_su)
				{
					$msg = lang("Can't add alarms in the past !!!");
				}
				elseif ($event['id'])	// save the alarm immediatly
				{
					if (($alarm_id = $this->bo->save_alarm($event['id'],$alarm)))
					{
						$alarm['id'] = $alarm_id;
						$event['alarm'][$alarm_id] = $alarm;

						$msg = lang('Alarm added');
						Framework::refresh_opener($msg,'calendar', $event['id'], 'update');
					}
					else
					{
						$msg = lang('Error adding the alarm');
					}
				}
				else
				{
					for($alarm['id']=1; isset($event['alarm'][$alarm['id']]); $alarm['id']++) {}	// get a temporary non-conflicting, numeric id
					$event['alarm'][$alarm['id']] = $alarm;
				}
			}
			else
			{
				$msg = lang('Permission denied');
			}
			break;
		}
		// add notification-errors, if we have some
		if (($notification_errors = notifications::errors(true)))
		{
			$msg .= ($msg ? "\n" : '').implode("\n", $notification_errors);
		}
		// New event, send data before updating so it's there
		$response = Api\Json\Response::get();
		if($response && !$content['id'] && $event['id'] && !$client_updated)
		{
			$client_updated = $this->update_client($event['id']);
		}
		if (in_array($button,array('cancel','save','delete','delete_exceptions','delete_keep_exceptions')) && $noerror)
		{
			if ($content['lock_token'])	// remove an existing lock
			{
				Vfs::unlock(Vfs::app_entry_lock_path('calendar',$content['id']),$content['lock_token'],false);
			}
			if ($content['no_popup'])
			{
				Egw::redirect_link('/index.php',array(
					'menuaction' => 'calendar.calendar_uiviews.index',
					'msg'        => $msg,
					'ajax'       => 'true'
				));
			}
			if (in_array($button,array('delete_exceptions','delete_keep_exceptions')) || $content['recur_type'] && $button == 'delete')
			{
				Framework::refresh_opener($msg,'calendar');
			}
			else
			{
				Framework::refresh_opener($msg, 'calendar',
					$event['id'] . ($content['edit_single'] ? ':' . (int)$content['edit_single'] : '' ),
					$button == 'save' && $client_updated ? ($content['id'] ? $update_type : 'add') : 'delete'
				);
			}
			// Don't try to close quick add, it's not in a popup
			if($content['template'] !== 'calendar.add')
			{
				Framework::window_close();
			}
			exit();
		}
		unset($event['no_notifications']);
		return $this->edit($event,$preserv,$msg,$event['id'] ? $event['id'] : $content['link_to']['to_id']);
	}

	/**
	 * Create an exception from the clicked event
	 *
	 * It's not stored to the DB unless the user saves it!
	 *
	 * @param array &$event
	 * @param array &$preserv
	 * @return string message that exception was created
	 */
	function _create_exception(&$event,&$preserv)
	{
		// In some cases where the user makes the first day an exception, actual_date may be missing
		$preserv['actual_date'] = $preserv['actual_date'] ? $preserv['actual_date'] : $event['start'];

		$event['end'] += $preserv['actual_date'] - $event['start'];
		$event['reference'] = $preserv['reference'] = $event['id'];
		$event['recurrence'] = $preserv['recurrence'] = $preserv['actual_date'];
		$event['start'] = $preserv['edit_single'] = $preserv['actual_date'];
		$event['recur_type'] = MCAL_RECUR_NONE;
		foreach(array('recur_enddate','recur_interval','recur_exception','recur_data') as $name)
		{
			unset($event[$name]);
		}
		// add all alarms as new alarms to execption
		$event['alarm'] = array_values((array)$event['alarm']);
		foreach($event['alarm'] as &$alarm)
		{
			unset($alarm['uid'], $alarm['id'], $alarm['time']);
		}

		// Copy links
		if(!is_array($event['link_to'])) $event['link_to'] = array();
		$event['link_to']['to_app'] = 'calendar';
		$event['link_to']['to_id'] = 0;

		foreach(Link::get_links($event['link_to']['to_app'], $event['id']) as $link)
		{
			if(!$link['id']) continue;
			if ($link['app'] != Link::VFS_APPNAME)
			{
				Link::link('calendar', $event['link_to']['to_id'], $link['app'], $link['id'], $link['remark']);
			}
			elseif ($link['app'] == Link::VFS_APPNAME)
			{
				Link::link('calendar', $event['link_to']['to_id'], Link::VFS_APPNAME, array(
					'tmp_name' => Link::vfs_path($link['app2'], $link['id2']).'/'.$link['id'],
					'name' => $link['id'],
				), $link['remark']);
			}
		}

		$event['links'] = $event['link_to'];

		if($this->bo->check_perms(Acl::EDIT,$event))
		{
			return lang('Save event as exception - Delete single occurrence - Edit status or alarms for this particular day');
		}
		return lang('Edit status or alarms for this particular day');
	}

	/**
	 * Since we cannot change recurrences in the past, break a recurring
	 * event (that starts in the past), and create a new event.
	 *
	 * $old_event will be ended (if needed) and $event will be modified with the
	 * new start date and time.  It is not allowed to edit events in the past,
	 * so if $as_of_date is in the past, it will be adjusted to today.
	 *
	 * @param array &$event Event to be modified
	 * @param array $old_event Unmodified (original) event, as read from the database
	 * @param date $as_of_date If provided, the break will be done as of this
	 *	date instead of today
	 * @param boolean $no_notifications Toggle notifications to participants
	 *
	 * @return false or error message
	 */
	function _break_recurring(&$event, $old_event, $as_of_date = null, $no_notifications = true)
	{
		$msg = false;

		if(!$as_of_date )
		{
			$as_of_date = time();
		}

		//error_log(__METHOD__ . Api\DateTime::to($old_event['start']) . ' -> '. Api\DateTime::to($event['start']) . ' as of ' . Api\DateTime::to($as_of_date));

		if(!($next_occurrence = $this->bo->read($event['id'], $this->bo->now_su + 1, true)))
		{
			$msg = lang("Error: You can't shift a series from the past!");
			return $msg;
		}

		// Hold on to this in case something goes wrong
		$orig_event = $event;

		$offset = $event['start'] - $old_event['start'];
		$duration = $event['duration'] ? $event['duration'] : $event['end'] - $event['start'];

		// base start-date of new series on actual / clicked date
		$event['start'] = $as_of_date ;

		if (Api\DateTime::to($old_event['start'],'Ymd') < Api\DateTime::to($as_of_date,'Ymd') ||
			// Adjust for requested date in the past
			Api\DateTime::to($as_of_date,'ts') < time()
		)
		{
			// copy event by unsetting the id(s)
			unset($event['id']);
			unset($event['uid']);
			unset($event['caldav_name']);
			$event['alarm'] = array();

			// set enddate of existing event
			$rriter = calendar_rrule::event2rrule($old_event, true);
			$rriter->rewind();
			$last = $rriter->current();
			do
			{
				$rriter->next_no_exception();
				$occurrence = $rriter->current();
			}
			while ($rriter->valid()  && (
				Api\DateTime::to($occurrence, 'ts') <= time() ||
				Api\DateTime::to($occurrence, 'Ymd') < Api\DateTime::to($as_of_date,'Ymd')
			) && ($last = $occurrence));


			// Make sure as_of_date is still valid, may have to move forward
			if(Api\DateTime::to($as_of_date,'ts') < Api\DateTime::to($last,'ts') ||
				Api\DateTime::to($as_of_date, 'Ymd') == Api\DateTime::to($last, 'Ymd'))
			{
				$event['start'] = Api\DateTime::to($rriter->current(),'ts') + $offset;
			}

			//error_log(__METHOD__ ." Series should end at " . Api\DateTime::to($last) . " New series starts at " . Api\DateTime::to($event['start']));
			if ($duration)
			{
				$event['end'] = $event['start'] + $duration;
			}
			elseif($event['end'] < $event['start'])
			{
				$event['end'] = $old_event['end'] - $old_event['start'] + $event['start'];
			}
			//error_log(__LINE__.": event[start]=$event[start]=".Api\DateTime::to($event['start']).", duration={$duration}, event[end]=$event[end]=".Api\DateTime::to($event['end']).", offset=$offset\n");

			$event['participants'] = $old_event['participants'];
			foreach ($old_event['recur_exception'] as $key => $exdate)
			{
				if ($exdate > Api\DateTime::to($last,'ts'))
				{
					//error_log("Moved exception on " . Api\DateTime::to($exdate));
					unset($old_event['recur_exception'][$key]);
					$event['recur_exception'][$key] += $offset;
				}
				else
				{
					//error_log("Kept exception on ". Api\DateTime::to($exdate));
					unset($event['recur_exception'][$key]);
				}
			}
			$last->setTime(0, 0, 0);
			$old_event['recur_enddate'] = Api\DateTime::to($last, 'ts');
			$dummy = null;
			if (!$this->bo->update($old_event,true,true,false,true,$dummy, $no_notifications))
			{
				$msg .= ($msg ? ', ' : '') .lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
					lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
						htmlspecialchars(Egw::link('/index.php',array(
							'menuaction' => 'calendar.calendar_uiforms.edit',
							'cal_id'    => $event['id'],
						))).'">','</a>');
				$event = $orig_event;
			}
		}
		$event['start'] = Api\DateTime::to($event['start'],'ts');
		return $msg;
	}

	/**
	 * return javascript to open mail compose window with preset content to mail all participants
	 *
	 * @param array $event
	 * @param boolean $added
	 * @return string javascript window.open command
	 */
	function ajax_custom_mail($event,$added,$asrequest=false)
	{
		$to = array();

		foreach($event['participants'] as $uid => $status)
		{
			//error_log(__METHOD__.__LINE__.' '.$uid.':'.array2string($status));
			if (empty($status)) continue;
			if(!is_array($status))
			{
				$quantity = $role = null;
				calendar_so::split_status($status,$quantity,$role);
				$status = array(
					'status' => $status,
					'uid' => $uid,
				);
			}
			$toadd = '';
			if ((isset($status['status']) && $status['status'] == 'R') || (isset($status['uid']) && $status['uid'] == $this->user)) continue;

			if (isset($status['uid']) && is_numeric($status['uid']) && $GLOBALS['egw']->accounts->get_type($status['uid']) == 'u')
			{
				if (!($email = $GLOBALS['egw']->accounts->id2name($status['uid'],'account_email'))) continue;

				$toadd = $GLOBALS['egw']->accounts->id2name($status['uid'], 'account_firstname').' '.
					$GLOBALS['egw']->accounts->id2name($status['uid'], 'account_lastname').' <'.$email.'>';

				if (!in_array($toadd,$to)) $to[] = $toadd;
			}
			elseif ($uid < 0)
			{
				foreach($GLOBALS['egw']->accounts->members($uid,true) as $uid)
				{
					if (!($email = $GLOBALS['egw']->accounts->id2name($uid,'account_email'))) continue;

					$toadd = $GLOBALS['egw']->accounts->id2name($uid, 'account_firstname').' '.
						$GLOBALS['egw']->accounts->id2name($uid, 'account_lastname').' <'.$email.'>';

					// dont add groupmembers if they already rejected the event, or are the current user
					if (!in_array($toadd,$to) && ($event['participants'][$uid] !== 'R' && $uid != $this->user)) $to[] = $toadd;
				}
			}
			elseif(!empty($status['uid'])&& !is_numeric(substr($status['uid'],0,1)) && ($info = $this->bo->resource_info($status['uid'])))
			{
				$to[] = $info['email'];
				//error_log(__METHOD__.__LINE__.array2string($to));
			}
			elseif(!is_numeric(substr($uid,0,1)) && ($info = $this->bo->resource_info($uid)))
			{
				$to[] = $info['email'];
				//error_log(__METHOD__.__LINE__.array2string($to));
			}
		}
		// prefer event description over standard notification text
		if (empty($event['description']))
		{
			list(,$body) = $this->bo->get_update_message($event,$added ? MSG_ADDED : MSG_MODIFIED);	// update-message is in TZ of the user
		}
		else
		{
			$body = $event['description'];
		}
		// respect user preference about html mail
		if ($GLOBALS['egw_info']['user']['preferences']['mail']['composeOptions'] != 'text')
		{
			$body = '<pre>'.$body.'</pre>';
		}
		//error_log(__METHOD__.print_r($event,true));
		$boical = new calendar_ical();
		// we need to pass $event[id] so iCal class reads event again,
		// as event is in user TZ, but iCal class expects server TZ!
		$ics = $boical->exportVCal(array($event['id']),'2.0','REQUEST',false);

		$ics_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'ics');
		if(($f = fopen($ics_file,'w')))
		{
			fwrite($f,$ics);
			fclose($f);
		}
		//error_log(__METHOD__.__LINE__.array2string($to));
		$vars = array(
			'menuaction'      => 'mail.mail_compose.compose',
			'mimeType'		  => $GLOBALS['egw_info']['user']['preferences']['mail']['composeOptions'] != 'text' ? 'html' : 'plain',
			'preset[subject]' => $event['title'],
			'preset[body]'    => $body,
			'preset[name]'    => 'event.ics',
			'preset[file]'    => $ics_file,
			'preset[type]'    => 'text/calendar'.($asrequest?'; method=REQUEST':''),
			'preset[size]'    => filesize($ics_file),
		);
		$vars[$asrequest?'preset[to]': 'preset[bcc]'] = $to;
		if ($asrequest) $vars['preset[msg]'] = lang('You attempt to mail a meetingrequest to the recipients above. Depending on the client this mail is opened with, the recipient may or may not see the mailbody below, but only see the meeting request attached.');
		$response = Api\Json\Response::get();
		$response->call('app.calendar.custom_mail', $vars);
	}

	/**
	 * Get title of a uid / calendar participant
	 *
	 * @param int|string $uid
	 * @return string
	 */
	public function get_title($uid)
	{
		if (is_numeric($uid))
		{
			return Api\Accounts::username($uid);
		}
		elseif (($info = $this->bo->resource_info($uid)))
		{
			if ($uid[0] == 'e' && $info['name'] && $info['name'] != $info['email'])
			{
				return $info['name'].' <'.$info['email'].'>';
			}
			return $info['name'] ? $info['name'] : $info['email'];
		}
		return '#'.$uid;
	}

	/**
	 * Compare two uid by there title
	 *
	 * @param int|string $uid1
	 * @param int|string $uid2
	 * @return int see strnatcasecmp
	 */
	public function uid_title_cmp($uid1, $uid2)
	{
		return strnatcasecmp($this->get_title($uid1), $this->get_title($uid2));
	}

	public function ajax_add()
	{
		// This tells etemplate to send as JSON response, not full
		// This avoids errors from trying to send header again
		if(Api\Json\Request::isJSONRequest())
		{
			$GLOBALS['egw']->framework->response = Api\Json\Response::get();
		}

		$this->edit();
	}

	/**
	 * Get conflict dialog via ajax.  Used by quick add.
	 *
	 */
	public function ajax_conflicts()
	{
		$content = $this->default_add_event();

		// Process edit wants to see input values
		$participants = array(1=> false);
		$participants['cal_resources'] = '';
		foreach($content['participants'] as $id => $status)
		{
			$quantity = $role = '';
			calendar_so::split_status($status,$quantity,$role);
			$participants[] = array(
				'uid' => $id,
				'status' => $status,
				'quantity' => $quantity,
				'role' => $role
			);
		}
		$content['participants'] = $participants;
		$content['button'] = array('save' => true);
		return $this->process_edit($content);
	}

	/**
	 * Edit a calendar event
	 *
	 * @param array $event Event to edit, if not $_GET['cal_id'] contains the event-id
	 * @param array $preserv following keys:
	 *	view boolean view-mode, if no edit-access we automatic fallback to view-mode
	 *	hide_delete boolean hide delete button
	 *	no_popup boolean use a popup or not
	 *	edit_single int timestamp of single event edited, unset/null otherwise
	 * @param string $msg ='' msg to display
	 * @param mixed $link_to_id ='' from or for the link-widget
	 * @param string $msg_type =null default automatic detect, if it contains "error"
	 */
	function edit($event=null,$preserv=null,$msg='',$link_to_id='',$msg_type=null)
	{
		$sel_options = array(
			'recur_type' => &$this->bo->recur_types,
			'status'     => $this->bo->verbose_status,
			'duration'   => $this->durations,
			'role'       => $this->bo->roles,
			'new_alarm[options]' => $this->bo->alarms + array(0 => lang('Custom')),
			'action'     => array(
				'copy' => array('label' => 'Copy', 'title' => 'Copy this event'),
				'ical' => array('label' => 'Export', 'title' => 'Download this event as iCal'),
				'print' => array('label' => 'Print', 'title' => 'Print this event'),
				'infolog' => array('label' => 'InfoLog', 'title' => 'Create an InfoLog from this event'),
				'mail' => array('label' => 'Mail all participants', 'title' => 'Compose a mail to all participants after the event is saved'),
				'sendrequest' => array('label' => 'Meetingrequest to all participants', 'title' => 'Send meetingrequest to all participants after the event is saved'),
			),
			'participants[notify_externals]' => [
				'yes'            => lang('Yes').', '.lang('Notify all externals (non-users) about this event'),
				'no'             => lang('No').', '.lang('Do NOT notify externals (non-users) about this event'),
				'never'          => lang('Never notify externals (non-users) about events I create'),
				'add_cancel'     => lang('Always').', '.lang('on invitation / cancellation only'),
				'time_change_4h' => lang('Always').', '.lang('on time change of more than 4 hours too'),
				'time_change'    => lang('Always').', '.lang('on any time change too'),
				'modifications'  => lang('Always').', '.lang('on all modification, but responses'),
				'responses'      => lang('Always').', '.lang('on participant responses too'),
			],
		);
		unset($sel_options['status']['G']);
		if (!is_array($event))
		{
			$preserv = array(
				'no_popup' => isset($_GET['no_popup']),
				'template' => isset($_GET['template']) ? $_GET['template'] : (isset($_REQUEST['print']) ? 'calendar.print' : 'calendar.edit'),
			);
			if(!isset($_REQUEST['print']) && !empty($preserv['template']) && $this->cal_prefs['new_event_dialog'] == 'edit')
			{
				// User wants full thing
				unset($preserv['template']);
			}
			$cal_id = (int) $_GET['cal_id'];
			if($_GET['action'])
			{
				$event = $this->bo->read($cal_id);
				$event['action'] = $_GET['action'];
				unset($event['participants']);
				return $this->process_edit($event);
			}
			// vfs url
			if (!empty($_GET['ical_url']) && parse_url($_GET['ical_url'], PHP_URL_SCHEME) == 'vfs')
			{
				$_GET['ical_vfs'] = parse_url($_GET['ical_url'], PHP_URL_PATH);
			}
			// vfs path
			if (!empty($_GET['ical_vfs']) &&
				(!Vfs::file_exists($_GET['ical_vfs']) || !($_GET['ical'] = file_get_contents(Vfs::PREFIX.$_GET['ical_vfs']))))
			{
				//error_log(__METHOD__."() Error: importing the iCal: vfs file not found '$_GET[ical_vfs]'!");
				$msg = lang('Error: importing the iCal').': '.lang('VFS file not found').': '.$_GET['ical_vfs'];
				$event =& $this->default_add_event();
			}
			if (!empty($_GET['ical_data']) &&
				!($_GET['ical'] = Link::get_data($_GET['ical_data'])))
			{
				//error_log(__METHOD__."() Error: importing the iCal: data not found '$_GET[ical_data]'!");
				$msg = lang('Error: importing the iCal').': '.lang('Data not found').': '.$_GET['ical_data'];
				$event =& $this->default_add_event();
			}
			if (!empty($_GET['ical']))
			{
				$ical = new calendar_ical();
				if (!($events = $ical->icaltoegw($_GET['ical'], '', 'utf-8')))
				{
					error_log(__METHOD__."('$_GET[ical]') error parsing iCal!");
					$msg = lang('Error: importing the iCal');
					$event =& $this->default_add_event();
				}
				else
				{
					if (count($events) > 1)
					{
						$msg = lang('%1 events in iCal file, only first one imported and displayed!', count($events));
						$msg_type = 'notice';	// no not hide automatic
					}
					// as icaltoegw returns timestamps in server-time, we have to convert them here to user-time
					$this->bo->db2data($events, 'ts');

					$event = array_shift($events);
					if (($existing_event = $this->bo->read($event['uid'])))
					{
						$event = $existing_event;
					}
					else
					{
						$event['participant_types'] = array();
						foreach($event['participants'] as $uid => $status)
						{
							$user_type = $user_id = null;
							calendar_so::split_user($uid, $user_type, $user_id);
							$event['participant_types'][$user_type][$user_id] = $status;
						}
					}
					//error_log(__METHOD__."(...) parsed as ".array2string($event));
				}
				unset($ical);
			}
			elseif (!$cal_id || $cal_id && !($event = $this->bo->read($cal_id)))
			{
				if ($cal_id)
				{
					if (!$preserv['no_popup'])
					{
						Framework::window_close(lang('Permission denied'));
					}
					else
					{
						$GLOBALS['egw']->framework->render('<p class="message" align="center">'.lang('Permission denied')."</p>\n",null,true);
						exit();
					}
				}
				$event =& $this->default_add_event();
			}
			else
			{
				$preserv['actual_date'] = $event['start'];		// remember the date clicked
				if ($event['recur_type'] != MCAL_RECUR_NONE)
				{
					if (empty($event['whole_day']))
					{
						$date = $_GET['date'];
					}
					else
					{
						$date = $this->bo->so->startOfDay(new Api\DateTime($_GET['date'], Api\DateTime::$user_timezone));
						$date->setUser();
					}
					$event = $this->bo->read($cal_id, $date, true);
					$preserv['actual_date'] = $event['start'];		// remember the date clicked
					if ($_GET['exception'])
					{
						$msg = $this->_create_exception($event,$preserv);
					}
					else
					{
						$event = $this->bo->read($cal_id, null, true);
					}
				}
			}
			// set new start and end if given by $_GET
			if(isset($_GET['start'])) { $event['start'] = Api\DateTime::to($_GET['start'],'ts'); }
			if(isset($_GET['end'])) { $event['end'] = Api\DateTime::to($_GET['end'],'ts'); }
			if(isset($_GET['non_blocking'])) { $event['non_blocking'] = (bool)$_GET['non_blocking']; }
			// check if the event is the whole day
			$start = $this->bo->date2array($event['start']);
			$end = $this->bo->date2array($event['end']);
			$event['whole_day'] = !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;

			$link_to_id = $event['id'];
			if (!$event['id'] && isset($_REQUEST['link_app']) && isset($_REQUEST['link_id']))
			{
				$link_ids = is_array($_REQUEST['link_id']) ? $_REQUEST['link_id'] : array($_REQUEST['link_id']);
				foreach(is_array($_REQUEST['link_app']) ? $_REQUEST['link_app'] : array($_REQUEST['link_app']) as $n => $link_app)
				{
					$link_id = $link_ids[$n];
					if(!preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$link_app.':'.$link_id))	// guard against XSS
					{
						continue;
					}
					if(!$n)
					{
						$event['title'] = Link::title($link_app,$link_id);
						// ask first linked app via "calendar_set" hook, for further data to set, incl. links
						if (($set = Api\Hooks::single($event+array('location'=>'calendar_set','entry_id'=>$link_id),$link_app)))
						{
							foreach((array)$set['link_app'] as $i => $l_app)
							{
								if (($l_id=$set['link_id'][$i])) Link::link('calendar',$event['link_to']['to_id'],$l_app,$l_id);
							}
							unset($set['link_app']);
							unset($set['link_id']);

							$event = array_merge($event,$set);
						}
					}
					Link::link('calendar',$link_to_id,$link_app,$link_id);
				}
			}
		}
		// set videoconference from existence of url in cfs
		if (!isset($event['videoconference']) && !empty($event['##videoconference']))
		{
			$event['videoconference'] = !empty($event['##videoconference']);
		}

		$etpl = new Etemplate();
		if (!$etpl->read($preserv['template']))
		{
			$etpl->read($preserv['template'] = 'calendar.edit');
		}
		$view = $preserv['view'] = $preserv['view'] || $event['id'] && !$this->bo->check_perms(Acl::EDIT,$event);
		//echo "view=$view, event="; _debug_array($event);
		// shared locking of entries to edit
		if (!$view && ($locktime = $GLOBALS['egw_info']['server']['Lock_Time_Calender']) && $event['id'])
		{
			$lock_path = Vfs::app_entry_lock_path('calendar',$event['id']);
			$lock_owner = 'mailto:'.$GLOBALS['egw_info']['user']['account_email'];

			$scope = 'shared';
			$type = 'write';
			if (($preserv['lock_token'] = $event['lock_token']))		// already locked --> refresh the lock
			{
				Vfs::lock($lock_path,$preserv['lock_token'],$locktime,$lock_owner,$scope,$type,true,false);
			}
			if (($lock = Vfs::checkLock($lock_path)) && $lock['owner'] != $lock_owner)
			{
				$msg .= ' '.lang('This entry is currently opened by %1!',
					(($lock_uid = $GLOBALS['egw']->accounts->name2id(substr($lock['owner'],7),'account_email')) ?
					Api\Accounts::username($lock_uid) : $lock['owner']));
			}
			elseif($lock)
			{
				$preserv['lock_token'] = $lock['token'];
			}
			elseif(Vfs::lock($lock_path,$preserv['lock_token'],$locktime,$lock_owner,$scope,$type,false,false))
			{
				//We handle AJAX_REQUEST in client-side for unlocking the locked entry, in case of closing the entry by X button or close button
			}
			else
			{
				$msg .= ' '.lang("Can't aquire lock!");		// eg. an exclusive lock via CalDAV ...
				$view = true;
			}
		}
		$content = array_merge($event,array(
			'cal_id'  => $event['id'],
			'link_to' => array(
				'to_id'  => $link_to_id,
				'to_app' => 'calendar',
			),
			'edit_single' => $preserv['edit_single'],	// need to be in content too, as it is used in the template
			'tabs'   => $preserv['tabs'],
			'view' => $view,
			'query_delete_exceptions' => (int)($event['recur_type'] && $event['recur_exception']),
		));
		Framework::message($msg, $msg_type);
		$content['duration'] = $content['end'] - $content['start'];
		if (isset($this->durations[$content['duration']])) $content['end'] = '';

		$readonlys = $content['participants'] = $preserv['participants'] = array();
		// preserve some ui elements, if set eg. under error-conditions
		foreach(array('quantity','resource','role') as $n)
		{
			if (isset($event['participants'][$n])) $content['participants'][$n] = $event['participants'][$n];
		}
		$this->setup_participants($event,$content,$sel_options, $readonlys,$preserv,$view);

		$content['participants']['status_date'] = $preserv['actual_date'];
		// set notify_externals in participants from cfs
		if(!empty($event['##notify_externals']))
		{
			$content['participants']['notify_externals'] = $event['##notify_externals'];
		}
		else
		{
			$content['participants']['notify_externals'] = $this->cal_prefs['notify_externals'];
		}
		$preserved = array_merge($preserv, $content);
		// Don't preserve link_to, it causes problems if user removes a link
		unset($preserved['link_to']);
		$event['new_alarm']['options'] = $content['new_alarm']['options'];
		if($event['alarm'])
		{
			// makes keys of the alarm-array starting with 1
			$content['alarm'] = array(false);
			foreach(array_values($event['alarm']) as $id => $alarm)
			{
				if(!$alarm['all'] && !$this->bo->check_perms(Acl::READ, 0, $alarm['owner']))
				{
					continue;    // no read rights to the calendar of the alarm-owner, dont show the alarm
				}
				$alarm['all'] = (int) $alarm['all'];
				// fix alarm time in case of alread run alarms, where the time will be their keep_time / when they will be cleaned up otherwise
				$alarm['time'] = $event['start'] - $alarm['offset'];
				$after = false;
				if($alarm['offset'] < 0)
				{
					$after = true;
					$alarm['offset'] = -1 * $alarm['offset'];
				}
				$days = (int) ($alarm['offset'] / DAY_s);
				$hours = (int) (($alarm['offset'] % DAY_s) / HOUR_s);
				$minutes = (int) (($alarm['offset'] % HOUR_s) / 60);
				$label = array();
				if ($days) $label[] = $days.' '.lang('days');
				if ($hours) $label[] = $hours.' '.lang('hours');
				if ($minutes) $label[] = $minutes.' '.lang('Minutes');
				if (!$label)
				{
					$alarm['offset'] = lang('at start of the event');
				}
				else
				{
					$alarm['offset'] = implode(', ',$label) . ' ' . ($after ? lang('after') : lang('before'));
				}
				$content['alarm'][] = $alarm;

				$readonlys['alarm[delete_alarm]['.$alarm['id'].']'] = !$this->bo->check_perms(Acl::EDIT,$alarm['all'] ? $event : 0,$alarm['owner']);
			}
			if (count($content['alarm']) == 1)
			{
				$content['alarm'] = false; // no alarms added to content array
			}
		}
		else
		{
			$content['alarm'] = false;
		}
		$content['msg'] = $msg;

		if ($view)
		{
			$readonlys['__ALL__'] = true;	// making everything readonly, but widgets set explicitly to false
			$readonlys['button[cancel]'] = $readonlys['action'] =
				$readonlys['before_after'] = $readonlys['button[add_alarm]'] = $readonlys['new_alarm[owner]'] =
				$readonlys['new_alarm[options]'] = $readonlys['new_alarm[date]'] = false;

			$content['participants']['no_add'] = true;

			if(!$event['whole_day'])
			{
				$etpl->setElementAttribute('whole_day', 'disabled', true);
			}

			// respect category permissions
			if(!empty($event['category']))
			{
				$content['category'] = $this->categories->check_list(Acl::READ, $event['category']);
			}
		}
		else
		{
			$readonlys['recur_exception'] = true;

			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$readonlys['recur_exception'] = empty($content['recur_exception']);	// otherwise we get a delete button
				//$onclick =& $etpl->get_cell_attribute('button[delete]','onclick');
				//$onclick = str_replace('Delete this event','Delete this series of recuring events',$onclick);
			}
			elseif ($event['reference'] != 0)
			{
				$readonlys['recur_type'] = $readonlys['recur_enddate'] = true;
				$readonlys['recur_interval'] = $readonlys['recur_data'] = true;
			}
		}
		if ($content['category'] && !is_array($content['category']))
		{
			$content['category'] = explode(',', $content['category']);
		}
		// disabling the custom fields tab, if there are none
		$readonlys['tabs'] = array(
			'custom' => !count($this->bo->customfields),
			'participants' => $this->accountsel->account_selection == 'none',
			'history' => !$event['id'],
		);
		if (!isset($GLOBALS['egw_info']['user']['apps']['mail']))	// no mail without mail-app
		{
			unset($sel_options['action']['mail']);
			unset($sel_options['action']['sendmeetingrequest']);
		}
		if (!$event['id'])	// no ical export for new (not saved) events
		{
			$readonlys['action'] = true;
		}
		if (!($readonlys['button[exception]'] = !$this->bo->check_perms(Acl::EDIT,$event) || $event['recur_type'] == MCAL_RECUR_NONE || ($event['recur_enddate'] &&$event['start'] > $event['recur_enddate'])))
		{
			$content['exception_label'] = $this->bo->long_date(max($preserved['actual_date'], $event['start']));
		}
		$readonlys['button[delete]'] = !$event['id'] || $preserved['hide_delete'] || !$this->bo->check_perms(Acl::DELETE, $event);
		if($readonlys['action'])
		{
			// Hide action entirely, not just readonly
			$content['action_class'] = 'hideme';
		}

		if (!$event['id'] || $this->bo->check_perms(Acl::EDIT,$event))	// new event or edit rights to the event ==> allow to add alarm for all users
		{
			$sel_options['owner'][0] = lang('All participants');
		}
		if (isset($event['participant_types']['u'][$this->user]))
		{
			$sel_options['owner'][$this->user] = $this->bo->participant_name($this->user);
		}
		foreach((array) $event['participant_types']['u'] as $uid => $status)
		{
			if ($uid != $this->user && $status != 'R' && $this->bo->check_perms(Acl::EDIT,0,$uid))
			{
				$sel_options['owner'][$uid] = $this->bo->participant_name($uid);
			}
		}
		$content['no_add_alarm'] = empty($sel_options['owner']) || !count((array)$sel_options['owner']);	// no rights to set any alarm
		if (!$event['id'])
		{
			$etpl->set_cell_attribute('button[new_alarm]','type','checkbox');
		}
		if ($preserved['no_popup'])
		{
			// If not a popup, load the normal calendar interface on cancel
			$etpl->set_cell_attribute('button[cancel]','onclick','app.calendar.linkHandler(\'index.php?menuaction=calendar.calendar_uiviews.index&ajax=true\')');
		}

		// Allow admins to restore deleted events
		if ($event['deleted'])
		{
			$content['deleted'] = $preserved['deleted'] = null;
			$etpl->set_cell_attribute('button[save]', 'label', 'Recover');
			$etpl->set_cell_attribute('button[apply]', 'disabled', true);
		}
		// Allow users to prevent notifications?
		$etpl->set_cell_attribute('no_notifications', 'disabled', !$GLOBALS['egw_info']['server']['calendar_allow_no_notification']);

		// Setup history tab
		$this->setup_history($content, $sel_options);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - '
			. (!$event['id'] ? lang('Add')
				: ($view ? ($content['edit_single'] ? lang('View exception') : ($content['recur_type'] ? lang('View series') : lang('View')))
					: ($content['edit_single'] ? lang('Create exception') : ($content['recur_type'] ? lang('Edit series') : lang('Edit')))));

		$content['cancel_needs_refresh'] = (bool)$_GET['cancel_needs_refresh'];

		if (!empty($preserved['lock_token'])) $content['lock_token'] = $preserved['lock_token'];

		//Disable videoconference if the module is not enabled
		$etpl->disableElement('videoconference', calendar_hooks::isVideoconferenceDisabled());

		// non_interactive==true from $_GET calls immediate save action without displaying the edit form
		if(isset($_GET['non_interactive']) && (bool)$_GET['non_interactive'] === true)
		{
			unset($_GET['non_interactive']);	// prevent process_exec <--> edit loops
			$content['button']['save'] = true;
			$this->process_edit(array_merge($content,$preserved));
		}
		else
		{
			$etpl->exec('calendar.calendar_uiforms.process_edit',$content,$sel_options,$readonlys,$preserved+[
				'##videoconference' => $content['##videoconference'],
			],$preserved['no_popup'] ? 0 : 2);
		}
	}

	/**
	 * Set up the participants for display in edit dialog
	 *
	 * @param array $event
	 * @param array $content
	 * @param array $sel_options
	 * @param array $readonlys
	 * @param array $preserv
	 * @param string $view
	 */
	protected function setup_participants(array $event, array &$content, array &$sel_options, array  &$readonlys, array &$preserv, $view)
	{
		$row = 3;
		foreach($event['participant_types'] as $type => $participants)
		{
			$name = 'accounts';
			if (isset($this->bo->resources[$type]))
			{
				$name = $this->bo->resources[$type]['app'];
			}
			// sort participants (in there group/app) by title
			uksort($participants, array($this, 'uid_title_cmp'));
			foreach($participants as $id => $status)
			{
				$uid = $type == 'u' ? $id : $type.$id;
				$quantity = $role = null;
				calendar_so::split_status($status,$quantity,$role);
				$preserv['participants'][$row] = $content['participants'][$row] = array(
						'app'      => $name == 'accounts' ? ($GLOBALS['egw']->accounts->get_type($id) == 'g' ? 'Group' : 'User') : $name,
						'uid'      => $uid,
						'status'   => $status,
						'old_status' => $status,
						'quantity' => $quantity > 1 || $uid[0] == 'r' ? $quantity : '',	// only display quantity for resources or if > 1
						'role'     => $role,
				);
				// replace iCal roles with a nicer label and remove regular REQ-PARTICIPANT
				if (isset($this->bo->roles[$role]))
				{
					$content['participants'][$row]['role_label'] = lang($this->bo->roles[$role]);
				}
				// allow third party apps to use categories for roles
				elseif(substr($role,0,6) == 'X-CAT-')
				{
					$content['participants'][$row]['role_label'] = $GLOBALS['egw']->categories->id2name(substr($role,6));
				}
				else
				{
					$content['participants'][$row]['role_label'] = lang(str_replace('X-','',$role));
				}
				$content['participants'][$row]['delete_id'] = strpbrk($uid,'"\'<>') !== false ? md5($uid) : $uid;
				//echo "<p>$uid ($quantity): $role --> {$content['participants'][$row]['role']}</p>\n";

				if (($no_status = !$this->bo->check_status_perms($uid,$event)) || $view)
					$readonlys['participants'][$row]['status'] = $no_status;
				if ($preserv['hide_delete'] || !$this->bo->check_perms(Acl::EDIT,$event))
					$readonlys['participants']['delete'][$uid] = true;
				// todo: make the participants available as links with email as title
				$content['participants'][$row++]['title'] = $this->get_title($uid);
				// enumerate group-invitations, so people can accept/reject them
				if ($name == 'accounts' && $GLOBALS['egw']->accounts->get_type($id) == 'g' &&
						($members = $GLOBALS['egw']->accounts->members($id,true)))
				{
					$sel_options['status']['G'] = lang('Select one');
					// sort members by title
					usort($members, array($this, 'uid_title_cmp'));
					foreach($members as $member)
					{
						if (!isset($participants[$member]) && $this->bo->check_perms(Acl::READ,0,$member))
						{
							$preserv['participants'][$row] = $content['participants'][$row] = array(
									'app'      => 'Group invitation',
									'uid'      => $member,
									'status'   => 'G',
							);
							$readonlys['participants'][$row]['quantity'] = $readonlys['participants']['delete'][$member] = true;
							// read access is enough to invite participants, but you need edit rights to change status
							$readonlys['participants'][$row]['status'] = !$this->bo->check_perms(Acl::EDIT,0,$member);
							$content['participants'][$row++]['title'] = Api\Accounts::username($member);
						}
					}
				}
			}
			// resouces / apps we shedule, atm. resources and addressbook
			$content['participants']['cal_resources'] = '';
			foreach($this->bo->resources as $data)
			{
				if ($data['app'] == 'email') continue;	// make no sense, as we cant search for email
				$content['participants']['cal_resources'] .= ','.$data['app'];
			}
		}
	}

	/**
	 * Remove (shared) lock via ajax, when edit popup get's closed
	 *
	 * @param int $id
	 * @param string $token
	 */
	function ajax_unlock($id,$token)
	{
		$lock_path = Vfs::app_entry_lock_path('calendar',$id);
		$lock_owner = 'mailto:'.$GLOBALS['egw_info']['user']['account_email'];

		if (($lock = Vfs::checkLock($lock_path)) && $lock['owner'] == $lock_owner || $lock['token'] == $token)
		{
			Vfs::unlock($lock_path,$token,false);
		}
	}

	/**
	 * Get email of participant
	 *
	 * @param string $uid
	 * @return string|null
	 * @throws Exception
	 */
	public static function participantEmail($uid)
	{
		if (is_numeric($uid))
		{
			$email = Api\Accounts::id2name($uid, 'account_email') ?: null;
		}
		elseif ($uid[0] === 'e')
		{
			$email = substr($uid, 1);
		}
		elseif ($uid[0] === 'c' && ($contact = (new Api\Contacts)->read(substr($uid, 1))))
		{
			$email = $contact['email'] ?? $contact['email_home'];
		}
		if (!empty($email) && preg_match('/<([^>]+?)>$/', $email, $matches))
		{
			$email = $matches[1];
		}
		return $email;
	}

	/**
	 * Display iCal meeting request for EMail app and allow to accept, tentative or reject it or a reply and allow to apply it
	 *
	 * @todo Handle situation when user is NOT invited, but eg. can view that mail ...
	 * @param array $event = null; special usage if $event is array('event'=>null,'msg'=>'','useSession'=>true) we
	 * 		are called by new mail-app; and we intend to use the stuff passed on by session
	 * @param string $msg = null
	 */
	function meeting(array $event=null, $msg=null)
	{
		$user = $GLOBALS['egw_info']['user']['account_id'];
		$readonlys['button[apply]'] = true;
		$_usesession=!is_array($event);
		//special usage if $event is array('event'=>null,'msg'=>'','useSession'=>true) we
		//are called by new mail-app; and we intend to use the stuff passed on by session
		if ($event == array('event'=>null,'msg'=>'','useSession'=>true))
		{
			$event=null; // set to null
			$_usesession=true; // trigger session read
		}
		if (!is_array($event))
		{
			$ical_charset = 'utf-8';
			$ical_string = $_GET['ical'];
			if ($ical_string == 'session' || $_usesession)
			{
				$session_data = Api\Cache::getSession('calendar', 'ical');
				$ical_string = $session_data['attachment'];
				$ical_charset = $session_data['charset'];
				$ical_method = $session_data['method'];
				$ical_sender = $session_data['sender'];
				unset($session_data);
			}
			$ical = new calendar_ical();
			if (!($events = $ical->icaltoegw($ical_string, '', $ical_charset)) || count($events) != 1)
			{
				error_log(__METHOD__."('$_GET[ical]') error parsing iCal!");
				$GLOBALS['egw']->framework->render(Api\Html::fieldset('<pre>'.htmlspecialchars($ical_string).'</pre>',
					lang('Error: importing the iCal')));
				return;
			}
			$event = array_shift($events);

			// convert event from servertime returned by calendar_ical to user-time
			$this->bo->server2usertime($event);

			// Check if this is an exception
			if($event['recur_type'] && count($event['recur_exception']) && !$event['recurrence'])
			{
				$diff = $event['recur_exception'][0] - $event['start'];
				$event['start'] += $diff;
				$event['end'] += $diff;
			}

			if (($existing_event = $this->bo->read($event['uid'], $event['recurrence'], false, 'ts', null, true)) && // true = read the exception
				!$existing_event['deleted'])
			{
				// check if mail is from extern organizer
				$from_extern_organizer = false;
				if (strtolower($ical_method) !== 'reply' &&
					($extern_organizer = !empty($ical_sender) ? array_filter($existing_event['participants'] ?? [], static function($status, $user)
				{
					calendar_so::split_status($status, $quantity, $role);
					return $role === 'CHAIR' && is_string($user) && in_array($user[0], ['e', 'c']);
				}, ARRAY_FILTER_USE_BOTH) : []) &&
					!($from_extern_organizer = $ical_sender === strtolower($organizer=self::participantEmail(key($extern_organizer)))))
				{
					$event['sender_warning'] = lang('The sender "%1" is NOT the extern organizer "%2", proceed with caution!', $ical_sender, $organizer);
				}
				switch(strtolower($ical_method))
				{
					case 'reply':
						// first participant is the one replying (our iCal parser adds owner first!)
						$parts = $event['participants'];
						unset($parts[$existing_event['owner']]);
						$event['ical_sender_uid'] = key($parts);
						$event['ical_sender_status'] = current($parts);
						$quantity = $role = null;
						calendar_so::split_status($event['ical_sender_status'], $quantity, $role);
						// let user know, that sender is not the participant
						if ($ical_sender !== strtolower($participant=self::participantEmail($event['ical_sender_uid'])))
						{
							$event['sender_warning'] = lang('The sender "%1" is NOT the participant replying "%2", proceed with caution!', $ical_sender, $participant);
						}
						if ($event['ical_sender_uid'] && $this->bo->check_status_perms($event['ical_sender_uid'], $existing_event))
						{
							$existing_status = $existing_event['participants'][$event['ical_sender_uid']];
							// check if email matches, in case we have now something like "Name <email>"
							if (!isset($existing_status) && $event['ical_sender_uid'][0] === 'e')
							{
								foreach((array)$existing_event['participant_types']['e'] as $email => $status)
								{
									if (preg_match('/<(.*)>$/', $email, $matches)) $email = $matches[1];
									if (strtolower($email) === strtolower($participant))
									{
										$existing_status = $status;
										break;
									}
								}
							}
							// warn user about party-crashers (non-participants sending a reply)
							if (!isset($existing_status))
							{
								if (!empty($event['sender_warning'])) $event['sender_warning'] .= "\n";
								$event['sender_warning'] .= lang('Replying "%1" is NOT a participant of the event! Only continue if you want to add as new participant.', $participant);
							}
							calendar_so::split_status($existing_status, $quantity, $role);
							if ($existing_status != $event['ical_sender_status'])
							{
								$readonlys['button[apply]'] = false;
							}
							else
							{
								$event['error'] = lang('Status already applied');
							}
						}
						break;

					case 'request':
						$status = $existing_event['participants'][$user];
						calendar_so::split_status($status, $quantity, $role);
						if (!empty($extern_organizer) && self::event_changed($event, $existing_event))
						{
							$event['error'] = lang('The extern organizer changed the event!',);
							$readonlys['button[apply]'] = false;
						}
						elseif (isset($existing_event['participants'][$user]) &&
							$status != 'U' && isset($this->bo->verbose_status[$status]))
						{
							$event['error'] = lang('You already replied to this invitation with').': '.lang($this->bo->verbose_status[$status]);
						}
						else
						{
							$event['error'] = lang('Using already existing event on server.');
						}
						$user_and_memberships = $GLOBALS['egw']->accounts->memberships($user, true);
						$user_and_memberships[] = $user;
						if (!array_intersect(array_keys($event['participants'] ?? []), $user_and_memberships))
						{
							$event['error'] .= ($event['error'] ? "\n" : '').lang('You are not invited to that event!');
							if ($event['id'])
							{
								$readonlys['button[accept]'] = $readonlys['button[tentativ]'] = $readonlys['button[apply]'] =
									$readonlys['button[reject]'] = $readonlys['button[cancel]'] = true;
							}
						}
						break;
					case 'cancel':
						// first participant is the (external) organizer (our iCal parser adds owner first!)
						$parts = $event['participants'] ?? [];
						unset($parts[$existing_event['owner']]);
						$event['ical_sender_uid'] = key($parts);
						if (empty($existing_event['id']) || !$this->bo->check_perms(Acl::DELETE, $existing_event['id']))
						{
							$readonlys['button[delete]'] = true;
						}
				}
				$event['id'] = $existing_event['id'];
				if($existing_event['##videoconference'])
				{
					$event['##videoconference'] = $existing_event['##videoconference'];
				}
			}
			else	// event not in calendar
			{
				$readonlys['button[cancel]'] = true;	// no way to remove a canceled event not in calendar
			}
			$event['participant_types'] = array();
			foreach($event['participants'] as $uid => $status)
			{
				$user_type = $user_id = null;
				calendar_so::split_user($uid, $user_type, $user_id);
				$event['participants'][$uid] = $event['participant_types'][$user_type][$user_id] =
					$status && $status !== 'X' ? $status : 'U';	// X --> no status given --> U = unknown
			}
			//error_log(__METHOD__."(...) parsed as ".array2string($event));
			$event['recure'] = $this->bo->recure2string($event);
			$event['all_participants'] = implode(",\n",$this->bo->participants($event, true));

			// EGroupware event has been deleted, dont let user resurect it by accepting again
			if ($existing_event && $existing_event['deleted'] && strtolower($ical_method) !== 'cancel')
			{
				// check if this is an EGroupware event or has an external organizer
				foreach($existing_event['participants'] as $uid => $status)
				{
					$quantity = $role = null;
					calendar_so::split_status($status, $quantity, $role);
					if (!is_numeric($uid) && $role == 'CHAIR') break;
				}
				if (!(!is_numeric($uid) && $role == 'CHAIR'))
				{
					$event['error'] = lang('Event has been deleted by organizer!');
					$readonlys['button[accept]'] = $readonlys['button[tentativ]'] =
						$readonlys['button[reject]'] = $readonlys['button[cancel]'] = true;
				}
			}
			// ignore events in the past (for recurring events check enddate!)
			elseif ($this->bo->date2ts($event['start']) < $this->bo->now_su &&
				(!$event['recur_type'] || $event['recur_enddate'] && $event['recur_enddate'] < $this->bo->now_su))
			{
				$event['error'] = lang('Requested meeting is in the past!');
				$readonlys['button[accept]'] = $readonlys['button[tentativ]'] =
					$readonlys['button[reject]'] = $readonlys['button[cancel]'] = true;
			}
		}
		elseif (!empty($event['button']))
		{
			//_debug_array($event);
			$button = key($event['button']);
			unset($event['button']);

			// clear notification errors
			notifications::errors(true);

			$msg = [];
			// do we need to update the event itself (user-status is reset to old in event_changed!)
			if (strtolower($event['ics_method']) !== 'reply' && // do NOT apply (all) data from participants replying
				$button !== 'delete' && !empty($event['old']) && self::event_changed($event, $event['old']))
			{
				// check if we are allowed to update the event
				if($this->bo->check_perms(Acl::EDIT, $event['old']) || $event['extern_organizer'])
				{
					if ($event['recurrence'] && !$event['old']['reference'] && ($recur_event = $this->bo->read($event['id'])))
					{
						// first we need to add the exception to the recurrence master
						$recur_event['recur_exception'][] = $event['recurrence'];
						// check if we need to move the alarms, because they are next on that exception
						$this->bo->check_move_alarms($recur_event, null, $event['recurrence'], !empty($event['extern_organizer']));
						unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
						unset($recur_event['alarm']);	// unsetting alarms too, as they cant be updated without start!
						$this->bo->update($recur_event, $ignore_conflicts=true, true, !empty($event['extern_organizer']), true, $msg, true);

						// then we need to create the exception as new event
						unset($event['id']);
						$event['reference'] = $event['old']['id'];
						$event['caldav_name'] = $event['old']['caldav_name'];
					}
					else
					{
						// keep all EGroupware only values of existing events plus alarms
						unset($event['alarm'], $event['owner']);
						$event = array_merge($event['old'], $event);
					}
					unset($event['old']);

					if (($event['id'] = $this->bo->update($event, $ignore_conflicts=true, true, !empty($event['extern_organizer']), true, $msg, true)))
					{
						$msg[] = lang('Changed event-data applied');
					}
					else
					{
						$msg[] = lang('Error saving the event!');
						$button = false;
					}
				}
				else
				{
					$event['id'] = $event['old']['id'];
					// disable "warning" that we have no rights to store any modifications
					// as that confuses our users, who only want to accept or reject
					//$msg[] = lang('Not enough rights to update the event!');
				}
			}
			switch($button)
			{
				case 'reject':
					if (!$event['id'])
					{
						// send reply to organizer
						$this->bo->send_update(MSG_REJECTED,array('e'.$event['organizer'] => 'DCHAIR'),$event);
						break;	// no need to store rejected event
					}
					// fall-through
				case 'accept':
				case 'tentativ':
					$status = strtoupper($button[0]);	// A, R or T
					if (!$event['id'])
					{
						// if organizer is a EGroupware user, but we have no rights to organizers calendar
						if (isset($event['owner']) && !$this->bo->check_perms(Acl::ADD,0,$event['owner']))
						{
							// --> make organize a participant with role chair and current user the owner
							$event['participant_types']['u'] = $event['participants'][$event['owner']] =
								calendar_so::combine_status('A', 1, 'CHAIR');
							$event['owner'] = $this->user;
						}
						// store event without notifications!
						if (($event['id'] = $this->bo->update($event, $ignore_conflicts=true, true, false, true, $msg, true)))
						{
							$msg[] = lang('Event saved');
						}
						else
						{
							$msg[] = lang('Error saving the event!');
							break;
						}
					}
					else
					{
						$event['id'] = $event['old']['id'];
					}
					// set status and send notification / meeting response
					if ($this->bo->set_status($event['id'], $user, $status, $event['recurrence']))
					{
						$msg[] = lang('Status changed');
					}
					break;

				case 'apply':
					// set status and send notification / meeting response
					if (strtolower($event['ics_method']) === 'reply' && $this->bo->set_status($event['id'], $event['ical_sender_uid'], $event['ical_sender_status'], $event['recurrence']))
					{
						$msg[] = lang('Status changed');
					}
					break;

				case 'cancel':
					if ($event['id'] && $this->bo->set_status($event['id'], $user, 'R', $event['recurrence'],
						false, true, true))	// no reply to organizer
					{
						$msg[] = lang('Status changed');
					}
					break;

				case 'delete':
					if ($event['id'] &&	$this->bo->delete($event['id'], $event['recurrence'],
						false, [$event['ical_sender_uid']]))	// no reply to organizer
					{
						$msg[] = lang('Event deleted.');
					}
					break;
			}
			// add notification-errors, if we have some
			$msg = array_merge((array)$msg, notifications::errors(true));
		}
		Framework::message(implode("\n", (array)$msg));
		$readonlys['button[edit]'] = !$event['id'];
		$event['ics_method'] = strtolower($ical_method);
		switch(strtolower($ical_method))
		{
			case 'reply':
				$event['ics_method_label'] = lang('Reply to meeting request');
				break;
			case 'cancel':
				$event['ics_method_label'] = lang('Meeting canceled');
				break;
			case 'request':
			default:
				$event['ics_method_label'] = lang('Meeting request');
				break;
		}
		$tpl = new Etemplate('calendar.meeting');
		$tpl->exec('calendar.calendar_uiforms.meeting', $event, array(), $readonlys, $event+array(
			'old' => $existing_event,
			'extern_organizer' => $extern_organizer ?? [],
			'from_extern_organizer' => $from_extern_organizer ?? false,
		), 2);
	}

	/**
	 * Check if an event changed and need to be updated
	 *
	 * We are reseting (keeping) status of system users to old value, as they might have been updated!
	 *
	 * @param array& $_event invitation, on return user status changed to the one from old $old
	 * @param array $_old existing event on server
	 * @return boolean true if there are some changes, false if not
	 */
	function event_changed(array &$_event, array $_old)
	{
		static $keys_to_check = array('start', 'end', 'title', 'description', 'location', 'participants',
			'recur_type', 'recur_data', 'recur_interval', 'recur_exception');

		// only compare certain fields, taking account unset, null or '' values
		$event = array_intersect_key(array_diff($_event, [null, ''])+array('recur_exception'=>array()), array_flip($keys_to_check));
		$old = array_intersect_key(array_diff($_old, [null, '']), array_flip($keys_to_check));

		// keep the status of existing participants (users)
		foreach($old['participants'] as $uid => $status)
		{
			if (is_numeric($uid) && $uid > 0)
			{
				$event['participants'][$uid] = $_event['participants'][$uid] = $status;
			}
		}

		$ret = (bool)array_diff_assoc($event, $old);
		//error_log(__METHOD__."() returning ".array2string($ret)." diff=".array2string(array_udiff_assoc($event, $old, function($a, $b) { return (int)($a != $b); })));
		return $ret;
	}

	/**
	 * displays a scheduling conflict
	 *
	 * @param array $event
	 * @param array $conflicts array with conflicting events, the events are not garantied to be readable by the user!
	 * @param array $preserv data to preserv
	 */
	function conflicts($event,$conflicts,$preserv)
	{
		$etpl = new Etemplate('calendar.conflicts');
		$allConflicts = array();

		foreach($conflicts as $k => $conflict)
		{
			$is_readable = $this->bo->check_perms(Acl::READ,$conflict);

			$conflict_participants = $this->bo->participants(array(
					'participants' => array_intersect_key((array)$conflict['participants'],$event['participants']),
				),true,true);// show group invitations too
			$conflict_participant_list = [];
			foreach($conflict_participants as $c_id => $c_name)
			{
				$res_info = $this->bo->resource_info($c_id);

				$conflict_participant_list[] = array(
					'id' => $c_id,
					'name' => $c_name,
					'app' => $res_info['app'],
					'type' => $res_info['type']
				);
			}
			$conflicts[$k] += array(
				'icon_participants' => $is_readable ? (count($conflict['participants']) > 1 ? 'users' : 'single') : 'private',
				'tooltip_participants' => $is_readable ? implode(', ',$this->bo->participants($conflict)) : '',
				'time' => $this->bo->long_date($conflict['start'],$conflict['end'],true),
				'conflicting_participants' => $conflict_participant_list,
				'icon_recur' => $conflict['recur_type'] != MCAL_RECUR_NONE ? 'recur' : '',
				'text_recur' => $conflict['recur_type'] != MCAL_RECUR_NONE ? lang('Recurring event') : ' ',
			);
			$allConflicts += array_intersect_key((array)$conflict['participants'],$event['participants']);
		}
		$content = $event + array(
			'conflicts' => array_values($conflicts),	// conflicts have id-start as key
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('Scheduling conflict');
		$resources_config = Api\Config::read('resources');
		$readonlys = array();

		foreach (array_keys($allConflicts) as $pId)
		{
			if(substr($pId,0,1) == 'r' && $resources_config ) // resources Allow ignore conflicts
			{

				switch ($resources_config['ignoreconflicts'])
				{
					case 'no':
						$readonlys['button[ignore]'] = true;
						break;
					case 'allusers':
						$readonlys['button[ignore]'] = false;
						break;
					default:
						if (!$this->bo->check_status_perms($pId, $event))
						{
							$readonlys['button[ignore]'] = true;
							break;
						}
				}
			}
		}
		$etpl->exec('calendar.calendar_uiforms.process_edit',$content,array(),$readonlys,array_merge($event,$preserv),$preserv['no_popup'] ? 0 : 2);
	}

	/**
	 * Callback for freetimesearch button in edit
	 *
	 * It stores the data of the submitted form in the session under 'freetimesearch_args_'.$edit_content['id'],
	 * for later retrival of the freetimesearch method, called by the returned window.open() command.
	 *
	 * @param array $edit_content
	 * @return string with xajaxResponse
	 */
	function ajax_freetimesearch(array $edit_content)
	{
		$response = Api\Json\Response::get();
		//$response->addAlert(__METHOD__.'('.array2string($edit_content).')');

		// convert start/end date-time values to timestamps
		foreach(array('start', 'end') as $name)
		{
			if (!empty($edit_content[$name]))
			{
				$date = new Api\DateTime($edit_content[$name]);
				$edit_content[$name] = $date->format('ts');
			}
		}

		if ($edit_content['duration'])
		{
			$edit_content['end'] = $edit_content['start'] + $edit_content['duration'];
		}
		if ($edit_content['whole_day'])
		{
			$arr = $this->bo->date2array($edit_content['start']);
			$arr['hour'] = $arr['minute'] = $arr['second'] = 0; unset($arr['raw']);
			$edit_content['start'] = $this->bo->date2ts($arr);
			$earr = $this->bo->date2array($edit_content['end']);
			$earr['hour'] = 23; $earr['minute'] = $earr['second'] = 59; unset($earr['raw']);
			$edit_content['end'] = $this->bo->date2ts($earr);
		}
		$content = array(
			'start'    => $edit_content['start'],
			'duration' => $edit_content['end'] - $edit_content['start'],
			'end'      => $edit_content['end'],
			'cal_id'   => $edit_content['id'],
			'recur_type'   => $edit_content['recur_type'],
			'participants' => array(),
		);
		foreach($edit_content['participants'] as $key => $data)
		{
			if (is_numeric($key) && !$edit_content['participants']['delete'][$data['uid']] &&
				!$edit_content['participants']['delete'][md5($data['uid'])])
			{
				$content['participants'][] = $data['uid'];
			}
			elseif ($key == 'account' && !is_array($data) && $data)
			{
				$content['participants'][] = $data;
			}
		}
		// default search parameters
		$content['start_time'] = $edit_content['whole_day'] ? 0 : $this->cal_prefs['workdaystarts'];
		$content['end_time'] = $this->cal_prefs['workdayends'];
		if ($this->cal_prefs['workdayends']*HOUR_s < $this->cal_prefs['workdaystarts']*HOUR_s+$content['duration'])
		{
			$content['end_time'] = 0;	// no end-time limit, as duration would never fit
		}
		$content['weekdays'] = MCAL_M_WEEKDAYS;

		$content['search_window'] = 7 * DAY_s;

		// store content in session
		Api\Cache::setSession('calendar','freetimesearch_args_'.(int)$edit_content['id'],$content);

		//menuaction=calendar.calendar_uiforms.freetimesearch&values2url('start,end,duration,participants,recur_type,whole_day'),ft_search,700,500
		$link = 'calendar.calendar_uiforms.freetimesearch&cal_id='. $edit_content['id'];

		$response->call('app.calendar.freetime_search_popup',$link);

		//$response->addScriptCall('egw_openWindowCentered2',$link,'ft_search',700,500);

	}

	/**
	 * Freetime search
	 *
	 * As the function is called in a popup via javascript, parametes get initialy transfered via the url
	 * @param array $content=null array with parameters or false (default) to use the get-params
	 * @param string start[str] start-date
	 * @param string start[hour] start-hour
	 * @param string start[min] start-minutes
	 * @param string end[str] end-date
	 * @param string end[hour] end-hour
	 * @param string end[min] end-minutes
	 * @param string participants ':' delimited string of user-id's
	 */
	function freetimesearch($content = null)
	{
		$etpl = new Etemplate('calendar.freetimesearch');
		$sel_options['search_window'] = array(
			7*DAY_s		=> lang('one week'),
			14*DAY_s	=> lang('two weeks'),
			31*DAY_s	=> lang('one month'),
			92*DAY_s	=> lang('three month'),
			365*DAY_s	=> lang('one year'),
		);
		if (!is_array($content))
		{
			// get content from session (and delete it immediatly)
			$content = Api\Cache::getSession('calendar','freetimesearch_args_'.(int)$_GET['cal_id']);
			Api\Cache::unsetSession('calendar','freetimesearch_args_'.(int)$_GET['cal_id']);
			//Since the start_time and end_time from calendar_user_preferences are numbers, not timestamp, in order to show them on date-timeonly
			//widget we need to convert them from numbers to timestamps, only for the first time when we have template without content
			$sTime = $content['start_time'];
			$eTime = $content['end_time'];
			$content['start_time'] = strtotime(((strlen($content['start_time'])<2)?("0".$content['start_time']):$content['start_time']).":00");
			$content['end_time'] = strtotime(((strlen($content['end_time'])<2)?("0".$content['end_time']):$content['end_time']).":00");

			// pick a searchwindow fitting the duration (search for a 10 day slot in a one week window never succeeds)
			foreach(array_keys($sel_options['search_window']) as $window)
			{
				if ($window > $content['duration'])
				{
					$content['search_window'] = $window;
					break;
				}
			}
		}
		else
		{
			if (!$content['duration']) $content['duration'] = $content['end'] - $content['start'];
			$weekds = 0;
			foreach ($content['weekdays'] as &$wdays)
			{
				$weekds = $weekds + $wdays;
			}
			//split_freetime_daywise function expects to get start_time and end_time values as string numbers, only "hour", therefore, since the date-timeonly widget returns
			//always timestamp, we need to convert them to only "hour" string numbers.
			$sTime = date('H', $content['start_time']);
			$eTime = date('H', $content['end_time']);
		}

		if ($content['recur_type'])
		{
			$content['msg'] .= lang('Only the initial date of that recurring event is checked!');
		}
		$content['freetime'] = $this->freetime($content['participants'],$content['start'],$content['start']+$content['search_window'],$content['duration'],$content['cal_id']);
		$content['freetime'] = $this->split_freetime_daywise($content['freetime'],$content['duration'],(is_array($content['weekdays'])?$weekds:$content['weekdays']),$sTime,$eTime,$sel_options);

		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('freetime search');

		$sel_options['duration'] = $this->durations;
		if ($content['duration'] && isset($sel_options['duration'][$content['duration']])) $content['end'] = '';

		$etpl->exec('calendar.calendar_uiforms.freetimesearch',$content,$sel_options,NULL,array(
				'participants'	=> $content['participants'],
				'cal_id'		=> $content['cal_id'],
				'recur_type'	=> $content['recur_type'],
			),2);
	}

	/**
	 * calculate the freetime of given $participants in a certain time-span
	 *
	 * @param array $participants user-id's
	 * @param int $start start-time timestamp in user-time
	 * @param int $end end-time timestamp in user-time
	 * @param int $duration min. duration in sec, default 1
	 * @param int $cal_id own id for existing events, to exclude them from being busy-time, default 0
	 * @return array of free time-slots: array with start and end values
	 */
	function freetime($participants,$start,$end,$duration=1,$cal_id=0)
	{
		if ($this->debug > 2) $this->bo->debug_message(__METHOD__.'(participants=%1, start=%2, end=%3, duration=%4, cal_id=%5)',true,$participants,$start,$end,$duration,$cal_id);

		$busy = $this->bo->search(array(
			'start' => $start,
			'end'	=> $end,
			'users'	=> $participants,
			'ignore_acl' => true,	// otherwise we get only events readable by the user
		));
		$busy[] = array(	// add end-of-search-date as event, to cope with empty search and get freetime til that date
			'start'	=> $end,
			'end'	=> $end,
		);
		$ft_start = $start;
		$freetime = array();
		$n = 0;
		foreach($busy as $event)
		{
			if ((int)$cal_id && $event['id'] == (int)$cal_id) continue;	// ignore our own event

 			if ($event['non_blocking']) continue; // ignore non_blocking events

			// check if from all wanted participants at least one has a not rejected status in found event
			$non_rejected_found = false;
			foreach($participants as $uid)
			{
				$status = $event['participants'][$uid];
				$quantity = $role = null;
				calendar_so::split_status($status, $quantity, $role);
				if ($status == 'R' || $role == 'NON-PARTICIPANT') continue;

				if (isset($event['participants'][$uid]) ||
					$uid > 0 && array_intersect(array_keys((array)$event['participants']),
						$GLOBALS['egw']->accounts->memberships($uid, true)))
				{
					$non_rejected_found = true;
					break;
				}
			}
			if (!$non_rejected_found) continue;

			if ($this->debug)
			{
				echo "<p>ft_start=".date('D d.m.Y H:i',$ft_start)."<br>\n";
				echo "event[title]=$event[title]<br>\n";
				echo "event[start]=".date('D d.m.Y H:i',$event['start'])."<br>\n";
				echo "event[end]=".date('D d.m.Y H:i',$event['end'])."<br>\n";
			}
			// $events ends before our actual position ==> ignore it
			if ($event['end'] < $ft_start)
			{
				//echo "==> event ends before ft_start ==> continue<br>\n";
				continue;
			}
			// $events starts before our actual position ==> set start to it's end and go to next event
			if ($event['start'] < $ft_start)
			{
				//echo "==> event starts before ft_start ==> set ft_start to it's end & continue<br>\n";
				$ft_start = $event['end'];
				continue;
			}
			$ft_end = $event['start'];

			// only show slots equal or bigger to min_length
			if ($ft_end - $ft_start >= $duration)
			{
				$freetime[++$n] = array(
					'start'	=> $ft_start,
					'end'	=> $ft_end,
				);
				if ($this->debug > 1) echo "<p>freetime: ".date('D d.m.Y H:i',$ft_start)." - ".date('D d.m.Y H:i',$ft_end)."</p>\n";
			}
			$ft_start = $event['end'];
		}
		if ($this->debug > 0) $this->bo->debug_message('uiforms::freetime(participants=%1, start=%2, end=%3, duration=%4, cal_id=%5) freetime=%6',true,$participants,$start,$end,$duration,$cal_id,$freetime);

		return $freetime;
	}

	/**
	 * split the freetime in daywise slot, taking into account weekdays, start- and stop-times
	 *
	 * If the duration is bigger then the difference of start- and end_time, the end_time is ignored
	 *
	 * @param array $freetime free time-slots: array with start and end values
	 * @param int $duration min. duration in sec
	 * @param int $weekdays allowed weekdays, bitfield of MCAL_M_...
	 * @param int $_start_time minimum start-hour 0-23
	 * @param int $_end_time maximum end-hour 0-23, or 0 for none
	 * @param array $sel_options on return options for start-time selectbox
	 * @return array of free time-slots: array with start and end values
	 */
	function split_freetime_daywise($freetime, $duration, $weekdays, $_start_time, $_end_time, &$sel_options)
	{
		if ($this->debug > 1) $this->bo->debug_message('uiforms::split_freetime_daywise(freetime=%1, duration=%2, start_time=%3, end_time=%4)',true,$freetime,$duration,$_start_time,$_end_time);

		$freetime_daywise = array();
		if (!is_array($sel_options)) $sel_options = array();
		$time_format = $this->common_prefs['timeformat'] == 12 ? 'h:i a' : 'H:i';

		$start_time = (int) $_start_time;	// ignore leading zeros
		$end_time   = (int) $_end_time;

		// ignore the end_time, if duration would never fit
		if (($end_time - $start_time)*HOUR_s < $duration)
		{
			$end_time = 0;
			if ($this->debug > 1) $this->bo->debug_message('uiforms::split_freetime_daywise(, duration=%2, start_time=%3,..) end_time set to 0, it never fits durationn otherwise',true,$duration,$start_time);
		}
		$n = 0;
		foreach($freetime as $ft)
		{
			$adaybegin = $this->bo->date2array($ft['start']);
			$adaybegin['hour'] = $adaybegin['minute'] = $adaybegin['second'] = 0;
			unset($adaybegin['raw']);
			$daybegin = $this->bo->date2ts($adaybegin);

			for($t = $daybegin; $t < $ft['end']; $t += DAY_s,$daybegin += DAY_s)
			{
				$dow = date('w',$daybegin+DAY_s/2);	// 0=Sun, .., 6=Sat
				$mcal_dow = pow(2,$dow);
				if (!($weekdays & $mcal_dow))
				{
					//echo "wrong day of week $dow<br>\n";
					continue;	// wrong day of week
				}
				$start = $t < $ft['start'] ? $ft['start'] : $t;

				if ($start-$daybegin < $start_time*HOUR_s)	// start earlier then start_time
				{
					$start = $daybegin + $start_time*HOUR_s;
				}
				// if end_time given use it, else the original slot's end
				$end = $end_time ? $daybegin + $end_time*HOUR_s : $ft['end'];
				if ($end > $ft['end']) $end = $ft['end'];

				// slot to small for duration
				if ($end - $start < $duration)
				{
					//echo "slot to small for duration=$duration<br>\n";
					continue;
				}
				$freetime_daywise[++$n] = array(
					'start'	=> $start,
					'end'	=> $end,
				);
				$times = array();
				for ($s = $start; $s+$duration <= $end && $s < $daybegin+DAY_s; $s += 60*$this->cal_prefs['interval'])
				{
					$e = $s + $duration;
					$end_date = $e-$daybegin > DAY_s ? lang(date('l',$e)).' '.date($this->common_prefs['dateformat'],$e).' ' : '';
					$times[$s] = date($time_format,$s).' - '.$end_date.date($time_format,$e);
				}
				$sel_options[$n.'start'] = $times;
			}
		}
		return $freetime_daywise;
	}

	/**
     * Export events as vCalendar version 2.0 files (iCal)
     *
     * @param int|array $content numeric cal_id or submitted content from etempalte::exec
     * @param boolean $return_error should an error-msg be returned or a regular page with it generated (default)
     * @return string error-msg if $return_error
     */
    function export($content=0,$return_error=false)
    {
		$boical = new calendar_ical();
		#error_log(__METHOD__.print_r($content,true));
		if (is_numeric($cal_id = $content ? $content : $_REQUEST['cal_id']))
		{
			if (!($ical = $boical->exportVCal(array($cal_id),'2.0','PUBLISH',false)))
			{
				$msg = lang('Permission denied');

				if ($return_error) return $msg;
			}
			else
			{
				Api\Header\Content::type('event.ics','text/calendar',bytes($ical));
				echo $ical;
				exit();
			}
		}
		if (is_array($content))
		{
			$events =& $this->bo->search(array(
				'start' => $content['start'],
				'end'   => $content['end'],
				'enum_recuring' => false,
				'daywise'       => false,
				'owner'         => $this->owner,
				'date_format'   => 'server',    // timestamp in server time for boical class
			));
			if (!$events)
			{
				$msg = lang('No events found');
			}
			else
			{
				$ical = $boical->exportVCal($events,'2.0','PUBLISH',false);
				Api\Header\Content::type($content['file'] ? $content['file'] : 'event.ics','text/calendar',bytes($ical));
				echo $ical;
				exit();
			}
		}
		if (!is_array($content))
		{
			$content = array(
				'start' => $this->bo->date2ts($_REQUEST['start'] ? $_REQUEST['start'] : $this->date),
				'end'   => $this->bo->date2ts($_REQUEST['end'] ? $_REQUEST['end'] : $this->date),
				'file'  => 'event.ics',
				'version' => '2.0',
			);
		}
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('iCal Export');
		$etpl = new Etemplate('calendar.export');
		$etpl->exec('calendar.calendar_uiforms.export',$content);
    }

	/**
	 * Edit category ACL (admin only)
	 *
	 * @param array $_content
	 */
	function cat_acl(array $_content=null)
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new Api\Exception\NoPermission\Admin();
		}
		if (!empty($_content['button']))
		{
			$button = key($_content['button']);
			unset($_content['button']);
			if ($button != 'cancel')	// store changed Acl
			{
				foreach($_content as $data)
				{
					if (!($cat_id = $data['cat_id'])) continue;
					foreach(array_merge((array)$data['add'],(array)$data['status'],array_keys((array)$data['old'])) as $account_id)
					{
						$rights = 0;
						if (in_array($account_id,(array)$data['add'])) $rights |= calendar_boupdate::CAT_ACL_ADD;
						if (in_array($account_id,(array)$data['status'])) $rights |= calendar_boupdate::CAT_ACL_STATUS;
						if ($account_id) $this->bo->set_cat_rights($cat_id,$account_id,$rights);
					}
				}
			}
			if ($button != 'apply')	// end dialog
			{
				Egw::redirect_link('/index.php', array(
					'menuaction' => 'admin.admin_ui.index',
					'ajax' => 'true'
				), 'admin');
			}
		}
		$content= $preserv = array();
		$n = 1;
		foreach($this->bo->get_cat_rights() as $Lcat_id => $data)
		{
			$cat_id = substr($Lcat_id,1);
			$row = array(
				'cat_id' => $cat_id,
				'add' => array(),
				'status' => array(),
			);
			foreach($data as $account_id => $rights)
			{
				if ($rights & calendar_boupdate::CAT_ACL_ADD) $row['add'][] = $account_id;
				if ($rights & calendar_boupdate::CAT_ACL_STATUS) $row['status'][] = $account_id;
			}
			$content[$n] = $row;
			$preserv[$n] = array(
				'cat_id' => $cat_id,
				'old' => $data,
			);
			$readonlys[$n.'[cat_id]'] = true;
			++$n;
		}
		// add empty row for new entries
		$content[] = array('cat_id' => '');

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Calendar').' - '.lang('Category ACL');
		$tmp = new Etemplate('calendar.cat_acl');
		$GLOBALS['egw_info']['flags']['nonavbar'] = 1;
		$tmp->exec('calendar.calendar_uiforms.cat_acl',$content,null,$readonlys,$preserv);
	}

	/**
	* Set up the required fields to get the history tab
	*/
	public function setup_history(&$content, &$sel_options)
	{
		$status = 'history_status';

		$content['history'] = array(
			'id'    =>      $content['id'],
			'app'   =>      'calendar',
			'status-widgets' => array(
				'owner'        => 'select-account',
				'creator'      => 'select-account',
				'category'     => 'select-cat',
				'non_blocking' => array(''=>lang('No'), 1=>lang('Yes')),
				'public'       => array(''=>lang('No'), 1=>lang('Yes')),

				'start'		   => 'date-time',
				'end'		   => 'date-time',
				'deleted'      => 'date-time',
				'recur_enddate'=> 'date',

				'tz_id'        => 'select-timezone',

				// Participants
				'participants'	=>	array(
					'select-account',
					$sel_options['status'],
					$sel_options['role']
				),
				'participants-c'	=>	array(
					'link:addressbook',
					$sel_options['status'],
					'label',
					$sel_options['role']
				),
				'participants-r'	=>	array(
					'link:resources',
					$sel_options['status'],
					'label',
					$sel_options['role']
				),
			),
		);


		// Get participants for only this one, if it's recurring.  The date is on the end of the value.
		if($content['recur_type'] || $content['recurrence'])
		{
			$content['history']['filter'] = array(
				'(history_status NOT LIKE \'participants%\' OR (history_status LIKE \'participants%\' AND (
					history_new_value LIKE \'%' . Api\Storage\Tracking::ONE2N_SEPERATOR . $content['recurrence'] . '\' OR
					history_old_value LIKE \'%' . Api\Storage\Tracking::ONE2N_SEPERATOR . $content['recurrence'] . '\')))'
			);
		}

		// Translate labels
		$tracking = new calendar_tracking();
		foreach($tracking->field2label as $field => $label)
		{
			$sel_options[$status][$field] = lang($label);
		}
		// custom fields are now "understood" directly by historylog widget
	}

	/**
	 * moves an event to another date/time
	 *
	 * @param string $_eventId id of the event which has to be moved
	 * @param string $calendarOwner the owner of the calendar the event is in
	 * @param string $targetDateTime the datetime where the event should be moved to, format: YYYYMMDD
	 * @param string|string[] $targetOwner the owner of the target calendar
	 * @param string $durationT the duration to support resizable calendar event
	 * @param string $seriesInstance If moving a whole series, not an exception, this is
	 *	which particular instance was dragged
	 * @return string XML response if no error occurs
	 */
	function ajax_moveEvent($_eventId,$calendarOwner,$targetDateTime,$targetOwner,$durationT=null,$seriesInstance=null)
	{
		list($eventId, $date) = explode(':', $_eventId,2);
		$ignore_conflicts = false;

		// we do not allow dragging into another users calendar ATM
		if($targetOwner < 0)
		{
			$targetOwner = array($targetOwner);
		}
		if($targetOwner == 0 || is_array($targetOwner) && $targetOwner[0] == 0)
		{
			$targetOwner = $calendarOwner;
		}
		// But you may be viewing multiple users, or a group calendar and
		// dragging your event - dragging across calendars does not change owner
		if(is_array($targetOwner) && !in_array($calendarOwner, $targetOwner))
		{
			$return = true;
			foreach($targetOwner as $owner)
			{
				if($owner < 0 && in_array($calendarOwner, $GLOBALS['egw']->accounts->members($owner,true)))
				{
					$return = false;
					break;
				}
				else if ($owner > 0 && $this->bo->check_perms(Acl::EDIT, $eventId,0,'ts',$date))
				{
					$return = false;
					break;
				}
			}
			if($return) return;
		}
		$old_event=$event=$this->bo->read($eventId);
		if (!$durationT)
		{
			$duration=$event['end']-$event['start'];
		}
		// Drag a normal event to whole day non-blocking
		else if ($durationT == 'whole_day')
		{
			$event['whole_day'] = true;
			$event['non_blocking'] = true;
			// Make duration whole days, less 1 second
			$duration = round(($event['end']-$event['start'])/DAY_s) * DAY_s - 1;
		}
		else
		{
			$duration = (int)$durationT;
		}

		// If we have a recuring event for a particular day, make an exception
		if ($event['recur_type'] != MCAL_RECUR_NONE && $date)
		{
			$d = new Api\DateTime($date, Api\DateTime::$user_timezone);
			if (!empty($event['whole_day']))
			{
				$d =& $this->bo->so->startOfDay($d);
				$d->setUser();
			}
			$event = $this->bo->read($eventId, $d, true);

			// For DnD, create an exception if they gave the date
			$preserv = null;
			$this->_create_exception($event,$preserv);
			unset($event['id']);
			$links = $event['link_to']['to_id'];

			$messages = null;
			$conflicts = $this->bo->update($event,false,true,false,true,$messages);
			if (!is_array($conflicts) && $conflicts)
			{
				// now we need to add the original start as recur-execption to the series
				$recur_event = $this->bo->read($event['reference']);
				$recur_event['recur_exception'][] = $d->format('ts');
				// check if we need to move the alarms, because they are next on that exception
				$this->bo->check_move_alarms($recur_event, null, $d);
				unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
				unset($recur_event['alarm']);	// unsetting alarms too, as they cant be updated without start!
				$this->bo->update($recur_event,true);	// no conflict check here

				// Sending null will trigger a removal of the original for that date
				Api\Json\Response::get()->generic('data', array('uid' => 'calendar::'.$_eventId, 'data' => null));

				unset($recur_event);
				unset($event['edit_single']);			// if we further edit it, it's just a single event
				unset($preserv['edit_single']);
			}
		}

		$d = new Api\DateTime($targetDateTime, Api\DateTime::$user_timezone);
		$event['start'] = $d->format('ts');
		$event['end'] = $event['start']+$duration;

		if ($event['recur_type'] != MCAL_RECUR_NONE && !$date && $seriesInstance)
		{
			// calculate offset against clicked recurrance,
			// depending on which is smaller
			$offset = Api\DateTime::to($targetDateTime,'ts') - Api\DateTime::to($seriesInstance,'ts');
			$event['start'] = $old_event['start'] + $offset;
			$event['duration'] = $duration;

			// We have a recurring event starting in the past -
			// stop it & create a new one.
			$this->_break_recurring($event, $old_event, $this->bo->date2ts($targetDateTime));

			// Can't handle conflict.  Just ignore it.
			$ignore_conflicts = true;
		}
		if(!$event['recur_type'])
		{
			$this->bo->check_move_alarms($event, $old_event);
		}

		// Drag a whole day to a time
		if($durationT && $durationT != 'whole_day')
		{
			$event['whole_day'] = ($duration == DAY_s);
			$event['non_blocking'] = false;
			// If there's a conflict, it won't save the change and the conflict popup will be blank
			// so save the change now, and then let the conflict check happen.
			$message = null;
			$this->bo->update($event,true, true, false, true, $message,true);

			// Whole day non blocking with DAY_s would add a day
			if($duration==DAY_s) $duration=0;
		}

		$status_reset_to_unknown = false;
		$sameday = (date('Ymd', $old_event['start']) == date('Ymd', $event['start']));

		$message = false;
		$conflicts=$this->bo->update($event,$ignore_conflicts, true, false, true, $message);

		// Save links
		if($links)
		{
			Link::link('calendar', $event['id'], $links);
		}

		$this->update_client($event['id'],$d);
		$response = Api\Json\Response::get();
		if(!is_array($conflicts) && $conflicts)
		{
			if(is_int($conflicts))
			{
				$event['id'] = $conflicts;
				$response->call('egw.refresh', '','calendar',$event['id'],'edit');
			}
		}
		else if ($conflicts)
		{
			$response->call(
				'egw_openWindowCentered2',
				$GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=calendar.calendar_uiforms.edit
					&cal_id='.$event['id']
					.'&start='.$event['start']
					.'&end='.$event['end']
					.'&non_interactive=true'
					.'&cancel_needs_refresh=true',
				'',750,410);
		}
		else if ($message)
		{
			$response->call('egw.message',  implode('<br />', $message));
		}
		if($event['id'] != $eventId && !$date) $this->update_client($_eventId);
	}

	/**
	 * Change the status via ajax
	 * @param string $_eventId
	 * @param integer $uid
	 * @param string $status
	 */
	function ajax_status($_eventId, $uid, $status)
	{
		list($eventId, $date) = explode(':', $_eventId);
		$event = $this->bo->read($eventId);
		if($date)
		{
			$d = new Api\DateTime($date, Api\DateTime::$user_timezone);
		}

		// If we have a recuring event for a particular day, make an exception
		if ($event['recur_type'] != MCAL_RECUR_NONE && $date)
		{
			if (!empty($event['whole_day']))
			{
				$d =& $this->bo->so->startOfDay($date);
				$d->setUser();
			}
			$event = $this->bo->read($eventId, $d, true);
			$date = $d->format('ts');
		}
		if($event['participants'][$uid])
		{
			$q = $r = null;
			calendar_so::split_status($event['participants'][$uid],$q,$r);
			$event['participants'][$uid] = $status = calendar_so::combine_status($status,$q,$r);
			$this->bo->set_status($event['id'],$uid,$status,$date,true);
		}
		else
		{
			// Group membership
			foreach(array_keys($event['participants'] ?? []) as $id)
			{
				if($GLOBALS['egw']->accounts->get_type($id) == 'g' && in_array($uid,$GLOBALS['egw']->accounts->members($id,true)))
				{
					calendar_so::split_status($event['participants'][$uid],$q,$r);
					$event['participants'][$uid] = $status = calendar_so::combine_status($status,$q,$r);
					$this->bo->set_status($event['id'],$uid,$status,$date,true);
					break;
				}
			}
		}
	}

	/**
	 * Deletes an event
	 */
	public function ajax_delete($eventId)
	{
		$response = Api\Json\Response::get();
		list($id, $date) = explode(':',$eventId);
		// let integration take care of delete for integration-events
		if (!is_numeric($id) && preg_match('/^([^\d]+)(\d+)$/', $id, $matches) &&
			!empty($app_data = calendar_bo::integration_get_data($matches[1], 'delete', true)))
		{
			try {
				$msg = is_callable($app_data) ? $app_data($matches[2]) : ExecMethod2($app_data, $matches[2]);
				$response->call('egw.refresh', $msg, 'calendar', $eventId, 'delete');
			}
			catch (\Exception $e) {
				$response->apply('egw.message', $e->getMessage(), 'error');
			}
			return;
		}
		$event=$this->bo->read($id);

		if ($this->bo->delete($event['id'], (int)$date))
		{
			if ($event['recur_type'] != MCAL_RECUR_NONE && !$date)
			{
				$msg = lang('Series deleted');
			}
			else
			{
				$msg = lang('Event deleted');
			}
			$response->apply('egw.refresh', Array($msg,'calendar',$eventId,'delete'));
		}
		else
		{
			$response->apply('egw.message', Array(lang('Error')),'error');
		}
	}

	/**
	 *
	 * @param string $_eventId id of the event to be changed.  For recurring events
	 *	it may contain the instance date
	 * @param string[] $invite Resources to invite
	 * @param string[] $remove Remove resource from participants
	 */
	public function ajax_invite($_eventId, $invite = array(), $remove = array())
	{
		list($eventId, $date) = explode(':', $_eventId,2);

		$event = $this->bo->read($eventId);
		if($date)
		{
			$d = new Api\DateTime($date, Api\DateTime::$user_timezone);
		}

		// If we have a recuring event for a particular day, make an exception
		if ($event['recur_type'] != MCAL_RECUR_NONE && $date)
		{
			if (!empty($event['whole_day']))
			{
				$d =& $this->bo->so->startOfDay($date);
				$d->setUser();
			}
			$event = $this->bo->read($eventId, $d, true);
			// For DnD, create an exception if they gave the date
			$preserv = null;
			$this->_create_exception($event,$preserv);
			unset($event['id']);

			$messages = null;
			$conflicts = $this->bo->update($event,true,true,false,true,$messages);
			if (!is_array($conflicts) && $conflicts)
			{
				// now we need to add the original start as recur-execption to the series
				$recur_event = $this->bo->read($event['reference']);
				$recur_event['recur_exception'][] = $d->format('ts');
				// check if we need to move the alarms, because they are next on that exception
				$this->bo->check_move_alarms($recur_event, null, $d);
				unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
				unset($recur_event['alarm']);	// unsetting alarms too, as they cant be updated without start!
				$this->bo->update($recur_event,true);	// no conflict check here

				// Sending null will trigger a removal of the original for that date
				Api\Json\Response::get()->generic('data', array('uid' => 'calendar::'.$_eventId, 'data' => null));

				unset($recur_event);
				unset($event['edit_single']);			// if we further edit it, it's just a single event
				unset($preserv['edit_single']);
			}
		}
		foreach($remove as $participant)
		{
			unset($event['participants'][$participant]);
		}
		foreach($invite as $participant)
		{
			$event['participants'][$participant] = 'U';
		}
		$message = null;
		$conflicts=$this->bo->update($event,false, true, false, true, $message);

		$response = Api\Json\Response::get();

		if (is_array($conflicts) && $conflicts)
		{
			// Save it anyway, was done with explicit user interaction,
			// and if we don't we lose the invite
			$this->bo->update($event,true);	// no conflict check here
			$this->update_client($event['id'],$d);
			$response->call(
				'egw_openWindowCentered2',
				$GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=calendar.calendar_uiforms.edit
					&cal_id='.$event['id']
					.'&start='.$event['start']
					.'&end='.$event['end']
					.'&non_interactive=true'
					.'&cancel_needs_refresh=true',
				'',750,410);
		}
		else if ($message)
		{
			$response->call('egw.message',  implode('<br />', $message));
		}
		if($conflicts)
		{
			$this->update_client($event['id'],$d);
			if(is_int($conflicts))
			{
				$event['id'] = $conflicts;
			}
			if($event['id'])
			{
				$response->call('egw.refresh', '','calendar',$event['id'],'edit');
			}
		}
	}

	/**
	 * imports a mail as Calendar
	 *
	 * @param array $mailContent = null mail content
	 * @return  array
	 */
	function mail_import(array $mailContent=null)
	{
		// It would get called from compose as a popup with egw_data
		if (!is_array($mailContent) && ($_GET['egw_data']))
		{
			// get raw mail data
			Link::get_data ($_GET['egw_data']);
			return false;
		}

		if (is_array($mailContent))
		{
			// Addressbook
			$AB = new Api\Contacts();
			$accounts = array(0 => $GLOBALS['egw_info']['user']['account_id']);

			$participants[0] = array(
				'uid'        => $GLOBALS['egw_info']['user']['account_id'],
				'delete_id'  => $GLOBALS['egw_info']['user']['account_id'],
				'status'     => 'A',
				'old_status' => 'A',
				'app'        => 'api-accounts',
				'role'       => 'REQ-PARTICIPANT'
			);
			foreach($mailContent['addresses'] as $address)
			{
				// Get available contacts from the email
				$contacts = $AB->search(array(
											'email' => $address['email'],
																																												   'email_home' => $address['email']
										), 'contact_id,contact_email,contact_email_home,egw_addressbook.account_id as account_id', '', '', '', false, 'OR', false, array('owner' => 0), '', false);
				if (is_array($contacts))
				{
					foreach($contacts as $account)
					{
						$accounts[] = $account['account_id'];
					}
				}
				else
				{
					$participants []= array (
						'app' => 'email',
						'uid' => 'e'.$address['email'],
						'status' => 'U',
						'old_status' => 'U'
					);
				}
			}
			$participants = array_merge($participants , array(
				"participant" => $accounts,
				"role" => "REQ-PARTICIPANT",
				"add" => "pressed"
			));

			// Prepare calendar event draft
			$event = $this->default_add_event();
			$event = array_merge($event, array(
				'title' => $mailContent['subject'],
				'description' => $mailContent['message'],
				'participants' => $participants,
				'link_to' => array(
					'to_app' => 'calendar',
					'to_id' => 0,
				),
				'duration' => 60 * $this->cal_prefs['interval'],
				'owner' => $GLOBALS['egw_info']['user']['account_id']
			));
			$ts = new Api\DateTime();
			$ts->setUser();
			if($mailContent['date'] >= $ts->format('ts'))
			{
				// Mail from the future!  Ok, use that date
				$event['start'] = $mailContent['date'];
			}

			if (is_array($mailContent['attachments']))
			{
				foreach ($mailContent['attachments'] as $attachment)
				{
					if($attachment['egw_data'])
					{
						Link::link('calendar',$event['link_to']['to_id'],Link::DATA_APPNAME,  $attachment);
					}
					else if(is_readable($attachment['tmp_name']) ||
						(Vfs::is_readable($attachment['tmp_name']) && parse_url($attachment['tmp_name'], PHP_URL_SCHEME) === 'vfs'))
					{
						Link::link('calendar',$event['link_to']['to_id'],'file',  $attachment);
					}
				}
			}
		}
		else
		{
			Framework::window_close(lang('No content found to show up as calendar entry.'));
		}

		return $this->process_edit($event);
	}

	/**
	 * Immediately send notification to selected users
	 *
	 * @param array $content
	 * @throws Api\Exception\AssertionFailed
	 */
	public function notify($content=array())
	{
		list($id, $date) = explode(':',$_GET['id']?:$content['id']);
		$event = $this->bo->read($id, $date);
		if(is_array($content) && $content['button'])
		{
			$participants = array_filter($content['participants']['notify']);
			$this->bo->send_update(MSG_REQUEST,$participants,$event,null,0,null,true);
			Framework::window_close();
		}

		$content = $readonlys = $preserve = array();
		$sel_options = array(
			'recur_type' => &$this->bo->recur_types,
			'status'     => $this->bo->verbose_status,
			'duration'   => $this->durations,
			'role'       => $this->bo->roles
		);
		$this->setup_participants($event, $content, $sel_options, $readonlys,$preserve,true);
		$content = array_merge($event, $content);

		$readonlys = [];

		$etpl = new Etemplate('calendar.notify_dialog');
		$preserve = $content;
		$preserve['id'] = $_GET['id'];

		$etpl->exec('calendar.calendar_uiforms.notify', $content, $sel_options, $readonlys, $preserve,2);
	}
}