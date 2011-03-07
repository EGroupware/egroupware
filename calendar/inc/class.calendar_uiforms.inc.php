<?php
/**
 * eGroupWare - Calendar's forms of the UserInterface
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-10 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
		'edit' => true,
		'process_edit' => true,
		'export' => true,
		'import' => true,
		'cat_acl' => true,
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
	 * @var locktime in seconds
	 */
	var $locktime_default=1;

	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct(true);	// call the parent's constructor

		for ($n=15; $n <= 8*60; $n+=($n < 60 ? 15 : ($n < 240 ? 30 : 60)))
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
		$extra_participants = $_GET['participants'] ? explode(',',$_GET['participants']) : array();

		if (isset($_GET['owner']))
		{
			$owner = $_GET['owner'];
		}
		// dont set the planner start group as owner/participants if called from planner
		elseif ($this->view != 'planner' || $this->owner != $this->cal_prefs['planner_start_with_group'])
		{
			$owner = $this->owner;
		}

		if (!$owner || !is_numeric($owner) || $GLOBALS['egw']->accounts->get_type($owner) != 'u' ||
			!$this->bo->check_perms(EGW_ACL_ADD,0,$owner))
		{
			if ($owner)	// make an owner who is no user or we have no add-rights a participant
			{
				// if we come from ressources we don't need any users selected in calendar
				if (!isset($_GET['participants']) || $_GET['participants'][0] != 'r')
				{
					foreach(explode(',',$owner) as $uid)
					{
						// only add users or a single ressource, not all ressources displayed by a category
						if (is_numeric($uid) || $owner == $uid)
						{
							$extra_participants[] = $uid;
						}
					}
				}
			}
			$owner = $this->user;
		}
		//echo "<p>this->owner=$this->owner, _GET[owner]=$_GET[owner], user=$this->user => owner=$owner, extra_participants=".implode(',',$extra_participants)."</p>\n";

		// by default include the owner as participant (the user can remove him)
		$extra_participants[] = $owner;

		$start = $this->bo->date2ts(array(
			'full' => isset($_GET['date']) && (int) $_GET['date'] ? (int) $_GET['date'] : $this->date,
			'hour' => (int) (isset($_GET['hour']) && (int) $_GET['hour'] ? $_GET['hour'] : $this->bo->cal_prefs['workdaystarts']),
			'minute' => (int) $_GET['minute'],
		));
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
					($uid == $this->user || ($uid == $owner && $this->bo->check_perms(EGW_ACL_ADD,0,$owner))) ? 'CHAIR' : 'REQ-PARTICIPANT');
			}
			elseif (is_array($this->bo->resources[$uid[0]]))
			{
				// if contact is a user, use the user instead (as the GUI)
				if ($uid[0] == 'c' && ($account_id = $GLOBALS['egw']->accounts->name2id(substr($uid,1),'person_id')))
				{
					$uid = $account_id;
					$participants[$uid] = $participant_types['u'][$uid] =
						calendar_so::combine_status($uid == $this->user ? 'A' : 'U',1,
						($uid == $this->user || ($uid == $owner && $this->bo->check_perms(EGW_ACL_ADD,0,$owner))) ? 'CHAIR' : 'REQ-PARTICIPANT');
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
		return array(
			'participant_types' => $participant_types,
			'participants' => $participants,
			'owner' => $owner,
			'start' => $start,
			'end'   => $start + (int) $this->bo->cal_prefs['defaultlength']*60,
			'tzid'  => $this->bo->common_prefs['tz'],
			'priority' => 2,	// normal
			'public'=> $this->cal_prefs['default_private'] ? 0 : 1,
			'alarm' => array(),
		);
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
		$messages = null;
		$msg_permission_denied_added = false;
		list($button) = @each($content['button']);
		if (!$button && $content['action']) $button = $content['action'];	// action selectbox
		unset($content['button']); unset($content['action']);

		$view = $content['view'];
		if ($button == 'ical')
		{
			$msg = $this->export($content['id'],true);
		}
		// delete a recur-exception
		if ($content['recur_exception']['delete_exception'])
		{
			list($date) = each($content['recur_exception']['delete_exception']);
			unset($content['recur_exception']['delete_exception']);
			if (($key = array_search($date,$content['recur_exception'])) !== false)
			{
				// propagate the exception to a single event
				$recur_exceptions = $this->bo->so->get_related($content['uid']);
				foreach ($recur_exceptions as $id)
				{
					if (!($exception = $this->bo->read($id)) ||
							$exception['recurrence'] != $content['recur_exception'][$key]) continue;
					$exception['uid'] = common::generate_uid('calendar', $id);
					$exception['reference'] = $exception['recurrence'] = 0;
					$this->bo->update($exception, true);
					break;
				}
				unset($content['recur_exception'][$key]);
				$content['recur_exception'] = array_values($content['recur_exception']);
			}
		}
		// delete an alarm
		if ($content['alarm']['delete_alarm'])
		{
			list($id) = each($content['alarm']['delete_alarm']);
			//echo "delete alarm $id"; _debug_array($content['alarm']['delete_alarm']);

			if ($content['id'])
			{
				if ($this->bo->delete_alarm($id))
				{
					$msg = lang('Alarm deleted');
					unset($content['alarm'][$id]);
					$js = "opener.location.search += (opener.location.search?'&msg=':'?msg=')+'".
						addslashes($msg)."';";
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
		if ($content['duration'])
		{
			$content['end'] = $content['start'] + $content['duration'];
		}
		$event = $content;
		unset($event['new_alarm']);
		unset($event['alarm']['delete_alarm']);
		unset($event['duration']);

		if (in_array($button,array('ignore','freetime','reedit')))
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

						case 'add':
							// email or rfc822 addresse (eg. "Ralf Becker <ralf@domain.com>") in the search field
							// ToDo: get eTemplate to return that field
							if (($email = $_POST['exec']['participants']['resource']['query']) &&
									(preg_match('/^(.*<)?([a-z0-9_.-]+@[a-z0-9_.-]{5,})>?$/i',$email,$matches)))
							{
								$status = calendar_so::combine_status('U',$content['participants']['quantity'],$content['participants']['role']);
								// check if email belongs to account or contact --> prefer them over just emails (if we are allowed to invite him)
								if (($data = $GLOBALS['egw']->accounts->name2id($matches[2],'account_email')) && $this->bo->check_acl_invite($data))
								{
									$event['participants'][$data] = $event['participant_types']['u'][$data] = $status;
								}
								elseif ((list($data) = ExecMethod2('addressbook.addressbook_bo.search',array(
									'email' => $matches[2],
									'email_home' => $matches[2],
								),true,'','','',false,'OR')))
								{
									$event['participants']['c'.$data['id']] = $event['participant_types']['c'][$data['id']] = $status;
								}
								else
								{
									$event['participants']['e'.$email] = $event['participant_types']['e'][$email] = $status;
								}
							}
							elseif (!$content['participants']['account'] && !$content['participants']['resource'])
							{
								$msg = lang('You need to select an account, contact or resource first!');
							}
							break;

						case 'resource':
							if (is_array($data) && isset($data['current']) )
							{
								list($app,$id) = explode(':',$data['current']);
							}
							else
							{
								list($app,$id) = explode(':',$data);
							}
							foreach($this->bo->resources as $type => $data) if ($data['app'] == $app) break;
							$uid = $this->bo->resources[$type]['app'] == $app ? $type.$id : false;
							// check if new entry is no account (or contact entry of an account)
							if ($app != 'addressbook' || !($data = $GLOBALS['egw']->accounts->name2id($id,'person_id')) || !$this->bo->check_acl_invite($data))
							{
								if ($uid && $id)
								{
									$status = isset($this->bo->resources[$type]['new_status']) ? ExecMethod($this->bo->resources[$type]['new_status'],$id) : 'U';
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
										}
										else
										{
											$event['participants'][$uid] = $event['participant_types'][$type][$id] =
												calendar_so::combine_status($status,$content['participants']['quantity'],$content['participants']['role']);
										}
									}
									elseif(!$msg_permission_denied_added)
									{
										$msg .= lang('Permission denied!');
										$msg_permission_denied_added = true;
									}
								}
								break;
							}
							// fall-through for accounts entered as contact
						case 'account':
							foreach(is_array($data) ? $data : explode(',',$data) as $uid)
							{
								if ($uid && $this->bo->check_acl_invite($uid))
								{
									$event['participants'][$uid] = $event['participant_types']['u'][$uid] =
										calendar_so::combine_status($uid == $this->bo->user ? 'A' : 'U',1,$content['participants']['role']);
								}
								elseif($uid && !$msg_permission_denied_added)
								{
									$msg .= lang('Permission denied!');
									$msg_permission_denied_added = true;
								}
							}
							break;

						default:		// existing participant row
							foreach(array('uid','status','quantity','role') as $name)
							{
								$$name = $data[$name];
							}
							if ($content['participants']['delete'][$uid] || $content['participants']['delete'][md5($uid)])
							{
								$uid = false;	// entry has been deleted
							}
							else
							{
								if (is_numeric($uid))
								{
									$id = $uid;
									$type = 'u';
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
									if ($this->bo->set_status($event['id'],$uid,$new_status,isset($content['edit_single']) ? $content['participants']['status_date'] : 0))
									{
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
											}
										}
										if (!$content['no_popup'])
										{
											$js = "opener.location.search += (opener.location.search?'&msg=':'?msg=')+'".
												addslashes($msg)."';";
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

		$ignore_conflicts = $edit_series_confirmed = $status_reset_to_unknown = false;

		switch((string)$button)
		{
			case 'ignore':
				$ignore_conflicts = true;
				$button = $event['button_was'];	// save or apply
				unset($event['button_was']);
				break;

			case 'confirm_edit_series':
				$edit_series_confirmed = true;
				$button = $event['button_was'];	// save or apply
				unset($event['button_was']);
		}

		switch((string)$button)
		{
		case 'exception':	// create an exception in a recuring event
			$msg = $this->_create_exception($event,$preserv);
			break;

		case 'copy':	// create new event with copied content, some content need to be unset to make a "new" event
			unset($event['id']);
			unset($event['uid']);
			unset($event['alarm']);
			unset($event['reference']);
			unset($event['recurrence']);
			unset($event['recur_exception']);
			unset($event['edit_single']);	// in case it has been set
			unset($event['modified']);
			unset($event['modifier']);
			$event['owner'] = !(int)$this->owner || !$this->bo->check_perms(EGW_ACL_ADD,0,$this->owner) ? $this->user : $this->owner;

			// Clear participant stati
			foreach($event['participant_types'] as $type => &$participants)
			{
				foreach($participants as $id => &$response)
				{
					if($type == 'u' && $id == $event['owner']) continue;
					calendar_so::split_status($status, $quantity, $role);
					$response = calendar_so::combine_status('U',$quantity,$role);
				}
			}
			$preserv['view'] = $preserv['edit_single'] = false;
			$msg = lang('Event copied - the copy can now be edited');
			$event['title'] = lang('Copy of:').' '.$event['title'];
			break;

		case 'mail':
		case 'save':
		case 'print':
		case 'apply':
			if ($event['id'] && !$this->bo->check_perms(EGW_ACL_EDIT,$event))
			{
				switch ($button)
				{
					case 'mail':	// just mail without edit-rights is ok
						$js = $this->custom_mail($event,false);
						break 2;
					case 'print':	// just print without edit-rights is ok
						$js = $this->custom_print($event,false);
						break 2;
				}
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
			if (!$event['participants'])
			{
				$msg = lang('Error: no participants selected !!!');
				$button = '';
				break;
			}
			// if private event with ressource reservation is forbidden
			if (!$event['public'] && $GLOBALS['egw_info']['server']['no_ressources_private'])
			{
				foreach ($event['participants'] as $uid => $value)
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
				$conflicts = $this->bo->update($event,$ignore_conflicts,true,false,true,$messages);
				if (!is_array($conflicts) && $conflicts)
				{
					// now we need to add the original start as recur-execption to the series
					$recur_event = $this->bo->read($event['reference']);
					$recur_event['recur_exception'][] = $content['edit_single'];
					unset($recur_event['start']); unset($recur_event['end']);	// no update necessary
					$this->bo->update($recur_event,true);	// no conflict check here
					unset($recur_event);
					unset($event['edit_single']);			// if we further edit it, it's just a single event
					unset($preserv['edit_single']);
				}
				else	// conflict or error, we need to reset everything to the state befor we tried to save it
				{
					$event['id'] = $event['reference'];
					unset($event['reference']);
					$event['uid'] = $content['uid'];
				}
			}
			else	// we edited a non-reccuring event or the whole series
			{
				if ($old_event = $this->bo->read($event['id']))
				{
					if ($event['recur_type'] != MCAL_RECUR_NONE)
					{
						// we edit a existing series event
						if ($event['start'] != $old_event['start'] ||
							$event['whole_day'] != $old_event['whole_day'])
						{
							if(!($next_occurrence = $this->bo->read($event['id'], $this->bo->now_su + 1, true)))
							{
								$msg = lang("Error: You can't shift a series from the past!");
								$noerror = false;
								break;
							}
							if ($edit_series_confirmed)
							{
								$orig_event = $event;

								$offset = $event['start'] - $old_event['start'];
								//$event['start'] = $next_occurrence['start'] + $offset;
								//$event['end'] = $next_occurrence['end'] + $offset;
								$event['participants'] = $old_event['participants'];
								foreach ($old_event['recur_exception'] as $key => $exdate)
								{
									if ($exdate > $this->bo->now_su)
									{
										unset($old_event['recur_exception'][$key]);
										$event['recur_exception'][$key] += $offset;
									}
									else
									{
										unset($event['recur_exception'][$key]);
									}
								}
								if ($old_event['start'] > $this->bo->now_su)
								{
									// delete the original event
									if (!$this->bo->delete($old_event['id']))
									{
										$msg = lang("Error: Can't delete original series!");
										$noerror = false;
										$event = $orig_event;
										break;
									}
								}
								else
								{
									$rriter = calendar_rrule::event2rrule($old_event, true);
									$rriter->rewind();
									$last = $rriter->current();
									do
									{
										$rriter->next_no_exception();
										$occurrence = $rriter->current();
									}
									while ($rriter->valid() &&
											egw_time::to($occurrence, 'ts') < $this->bo->now_su &&
											($last = $occurrence));
									$last->setTime(0, 0, 0);
									$old_event['recur_enddate'] = egw_time::to($last, 'ts');
									if (!$this->bo->update($old_event,true))
									{
										$msg .= ($msg ? ', ' : '') .lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
											lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
												htmlspecialchars(egw::link('/index.php',array(
													'menuaction' => 'calendar.calendar_uiforms.edit',
													'cal_id'    => $content['id'],
													'referer'    => $referer,
												))).'">','</a>');
										$noerror = false;
										$event = $orig_event;
										break;
									}
								}
								unset($orig_event);
								unset($event['uid']);
								unset($event['id']);
								$event['alarm'] = array();
							}
							else
							{
								$event['button_was'] = $button;	// remember for confirm
								return $this->confirm_edit_series($event,$preserv);
							}
						}
					}
					else
					{
						if ($old_event['start'] != $event['start'] ||
							$old_event['end'] != $event['end'] ||
							$event['whole_day'] != $old_event['whole_day'])
						{
							$sameday = (date('Ymd', $old_event['start']) == date('Ymd', $event['start']));
							foreach((array)$event['participants'] as $uid => $status)
							{
								calendar_so::split_status($status,$q,$r);
								if ($uid[0] != 'c' && $uid[0] != 'e' && $uid != $this->bo->user && $status != 'U')
								{
									$preferences = CreateObject('phpgwapi.preferences',$uid);
									$part_prefs = $preferences->read_repository();
									switch ($part_prefs['calendar']['reset_stati'])
									{
										case 'no':
											break;
										case 'startday':
											if ($sameday) break;
										default:
											$status_reset_to_unknown = true;
											$event['participants'][$uid] = calendar_so::combine_status('U',$q,$r);
											// todo: report reset status to user
									}
								}
							}
						}
					}
				}
				$edit_series_confirmed = false;
				$conflicts = $this->bo->update($event,$ignore_conflicts,true,false,true,$messages);
				unset($event['ignore']);
			}
			if (is_array($conflicts))
			{
				$event['button_was'] = $button;	// remember for ignore
				return $this->conflicts($event,$conflicts,$preserv);
			}
			// check if there are messages from update, eg. removed participants or categories because of missing rights
			if ($messages)
			{
				$msg  .= ($msg ? ', ' : '').implode(', ',$messages);
			}
			if ($conflicts === 0)
			{
				$msg .= ($msg ? ', ' : '') .lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars(egw::link('/index.php',array(
								'menuaction' => 'calendar.calendar_uiforms.edit',
								'cal_id'    => $content['id'],
							))).'">','</a>');
				$noerror = false;
			}
			elseif ($conflicts > 0)
			{
				if ($edit_series_confirmed &&
					($event = $this->bo->read($conflicts)))
				{
					// set the alarms again
					foreach ($old_event['alarm'] as $alarm)
					{
						if ($alarm['time'] > $this->bo->now_su)
						{
							// delete future alarm of the old series
							$this->bo->delete_alarm($alarm['id']);
						}
						$alarm['time'] += $offset;
						unset($alarm['id']);
						if (($next_occurrence = $this->bo->read($event['id'], $this->bo->now_su + $alarm['offset'], true)) &&
							$alarm['time'] < $next_occurrence['start'])
						{
							$alarm['time'] =  $next_occurrence['start'] - $alarm['offset'];
						}
						$this->bo->save_alarm($event['id'], $alarm);
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
							$exception['start'] += $offset;
							$exception['end'] += $offset;
							$exception['whole_day'] = $event['whole_day'];
							$alarms = array();
							foreach ($exception['alarm'] as $id => &$alarm)
							{
								$alarm['time'] = $exception['start'] - $alarm['offset'];
								$alarms[] = $alarm;
							}
							$event['alarm'] = $alarms;
							$this->bo->update($exception, true, true, true);
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

				$msg = $message . ($msg ? ', ' . $msg : '');

				// writing links for new entry, existing ones are handled by the widget itself
				if (!$content['id'] && is_array($content['link_to']['to_id']))
				{
					egw_link::link('calendar',$event['id'],$content['link_to']['to_id']);
				}
				$js = "opener.location.search += (opener.location.search?'&msg=':'?msg=')+'".
					addslashes($msg)."';";

				if ($button == 'print')
				{
					$js = $this->custom_print($event,!$content['id'])."\n".$js;	// first open the new window and then update the view
				}

				if ($button == 'mail')
				{
					$js = $this->custom_mail($event,!$content['id'])."\n".$js;	// first open the new window and then update the view
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
				$js = "opener.location.search += (opener.location.search?'&msg=':'?msg=')+'".
					addslashes($msg)."';";
			}
			break;

		case 'delete':					// delete of regular event
		case 'delete_keep_exceptions':	// series and user selected to keep the exceptions
		case 'delete_exceptions':		// series and user selected to delete the exceptions too
			if ($this->bo->delete($event['id'],(int)$content['edit_single']))
			{
				if ($event['recur_type'] != MCAL_RECUR_NONE && $content['reference'] == 0 && !$content['edit_single'])
				{
					$msg = lang('Series deleted');
					$delete_exceptions = $button == 'delete_exceptions';
					$exceptions_kept = false;
					// Handle the exceptions
					$recur_exceptions = $this->bo->so->get_related($event['uid']);
					foreach ($recur_exceptions as $id)
					{
						if ($delete_exceptions)
						{
							$this->bo->delete($id);
						}
						else
						{
							if (!($exception = $this->bo->read($id))) continue;
							$exception['uid'] = common::generate_uid('calendar', $id);
							$exception['reference'] = $exception['recurrence'] = 0;
							$this->bo->update($exception, true);
							$exceptions_kept = true;
						}
					}
					if ($exceptions_kept)
					{
						$msg .= lang(', exceptions preserved');
					}
				}
				else
				{
					$msg = lang('Event deleted');
				}
				$js = "opener.location += (opener.location.search?'&msg=':'?msg=')+'".
					addslashes($msg)."';";
			}
			break;

		case 'freetime':
			// the "click" has to be in onload, to make sure the button is already created
			$GLOBALS['egw']->js->set_onload("document.getElementsByName('exec[freetime]')[0].click();");
			break;

		case 'add_alarm':
			$time = ($content['actual_date'] ? $content['actual_date'] : $content['start']);
			$offset = DAY_s * $content['new_alarm']['days'] + HOUR_s * $content['new_alarm']['hours'] + 60 * $content['new_alarm']['mins'];
			if($content['before_after']) $offset *= -1;
			if ($event['recur_type'] != MCAL_RECUR_NONE &&
				($next_occurrence = $this->bo->read($event['id'], $this->bo->now_su + $offset, true)) &&
				$time < $next_occurrence['start'])
			{
				$time = $next_occurrence['start'];
			}
			if ($this->bo->check_perms(EGW_ACL_EDIT,!$content['new_alarm']['owner'] ? $event : 0,$content['new_alarm']['owner']))
			{
				$alarm = array(
					'offset' => $offset,
					'time'   => $time - $offset,
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
						$js = "opener.location.search += (opener.location.search?'&msg=':'?msg=')+'".
							addslashes($msg)."';";
					}
					else
					{
						$msg = lang('Error adding the alarm');
					}
				}
				else
				{
					for($alarm['id']=1; isset($event['alarm'][$alarm['id']]); $alarm['id']++);	// get a temporary non-conflicting, numeric id
					$event['alarm'][$alarm['id']] = $alarm;
				}
			}
			else
			{
				$msg = lang('Permission denied');
			}
			break;
		}
		if (in_array($button,array('cancel','save','delete','delete_exceptions','delete_keep_exceptions')) && $noerror)
		{
			if ($content['lock_token'])	// remove an existing lock
			{
				egw_vfs::unlock(egw_vfs::app_entry_lock_path('calendar',$content['id']),$content['lock_token'],false);
			}
			if ($content['no_popup'])
			{
				egw::redirect_link('/index.php',array(
					'menuaction' => 'calendar.calendar_uiviews.index',
					'msg'        => $msg,
				));
			}
			$js .= 'window.close();';
			echo "<html><body onload=\"$js\"></body></html>\n";
			common::egw_exit();
		}
		return $this->edit($event,$preserv,$msg,$js,$event['id'] ? $event['id'] : $content['link_to']['to_id']);
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
		$event['end'] += $preserv['actual_date'] - $event['start'];
		$event['reference'] = $preserv['reference'] = $event['id'];
		$event['recurrence'] = $preserv['recurrence'] = $preserv['actual_date'];
		$event['start'] = $preserv['edit_single'] = $preserv['actual_date'];
		$event['recur_type'] = MCAL_RECUR_NONE;
		foreach(array('recur_enddate','recur_interval','recur_exception','recur_data') as $name)
		{
			unset($event[$name]);
		}
		if($this->bo->check_perms(EGW_ACL_EDIT,$event))
		{
			return lang('Save event as exception - Delete single occurrence - Edit status or alarms for this particular day');
		}
		return lang('Edit status or alarms for this particular day');
	}

	/**
	 * return javascript to open felamimail compose window with preset content to mail all participants
	 *
	 * @param array $event
	 * @param boolean $added
	 * @return string javascript window.open command
	 */
	function custom_mail($event,$added)
	{
		$to = array();

		foreach($event['participants'] as $uid => $status)
		{
			$toadd = '';
			if ($status == 'R' || $uid == $this->user) continue;

			if (is_numeric($uid) && $GLOBALS['egw']->accounts->get_type($uid) == 'u')
			{
				if (!($email = $GLOBALS['egw']->accounts->id2name($uid,'account_email'))) continue;

				$GLOBALS['egw']->accounts->get_account_name($uid,$lid,$firstname,$lastname);

				$toadd = $firstname.' '.$lastname.' <'.$email.'>';
				if (!in_array($toadd,$to)) $to[] = $toadd;
			}
			elseif ($uid < 0)
			{
				foreach($GLOBALS['egw']->accounts->members($uid,true) as $uid)
				{
					if (!($email = $GLOBALS['egw']->accounts->id2name($uid,'account_email'))) continue;

					$GLOBALS['egw']->accounts->get_account_name($uid,$lid,$firstname,$lastname);

					$toadd = $firstname.' '.$lastname.' <'.$email.'>';
					// dont add groupmembers if they already rejected the event, or are the current user
					if (!in_array($toadd,$to) && ($event['participants'][$uid] !== 'R' && $uid != $this->user)) $to[] = $toadd;
				}
			}
			elseif(($info = $this->bo->resource_info($uid)))
			{
				$to[] = $info['email'];
			}
		}
		list($subject,$body) = $this->bo->get_update_message($event,$added ? MSG_ADDED : MSG_MODIFIED);	// update-message is in TZ of the user
		#error_log(__METHOD__.print_r($event,true));
		$boical = new calendar_ical();
		$ics = $boical->exportVCal(array($event),'2.0','request',false);

		$ics_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'ics');
		if(($f = fopen($ics_file,'w')))
		{
			fwrite($f,$ics);
			fclose($f);
		}
		$vars = array(
			'menuaction'      => 'felamimail.uicompose.compose',
			'mimeType'		  => 'plain', // force type to plain as thunderbird seems to try to be smart while parsing html messages with ics attachments
			'preset[to]'      => $to,
			'preset[subject]' => $subject,
			'preset[body]'    => $body,
			'preset[name]'    => 'event.ics',
			'preset[file]'    => $ics_file,
			'preset[type]'    => 'text/calendar; method=request',
			'preset[size]'    => filesize($ics_file),
		);
		return "window.open('".egw::link('/index.php',$vars)."','_blank','width=700,height=700,scrollbars=yes,status=no');";
	}

	/**
	 * return javascript to open compose window to print the event
	 *
	 * @param array $event
	 * @param boolean $added
	 * @return string javascript window.open command
	 */
	function custom_print($event,$added)
	{
			$vars = array(
			'menuaction'      => 'calendar.calendar_uiforms.edit',
			'cal_id'      => $event['id'],
			'print' => true,
			);
		return "window.open('".egw::link('/index.php',$vars)."','_blank','width=700,height=700,scrollbars=yes,status=no');";
	}


	/**
	 * Edit a calendar event
	 *
	 * @param array $event=null Event to edit, if not $_GET['cal_id'] contains the event-id
	 * @param array $perserv=null following keys:
	 *	view boolean view-mode, if no edit-access we automatic fallback to view-mode
	 *	hide_delete boolean hide delete button
	 *	no_popup boolean use a popup or not
	 *	edit_single int timestamp of single event edited, unset/null otherwise
	 * @param string $msg='' msg to display
	 * @param string $js='window.focus();' javascript to include in the page
	 * @param mixed $link_to_id='' $content from or for the link-widget
	 */
	function edit($event=null,$preserv=null,$msg='',$js = 'window.focus();',$link_to_id='')
	{
		$sel_options = array(
			'recur_type' => &$this->bo->recur_types,
			'status'     => $this->bo->verbose_status,
			'duration'   => $this->durations,
			'role'       => $this->bo->roles,
			'before_after'=>array(0 => lang('Before'), 1 => lang('After')),
			'action'     => array(
				'copy' => array('label' => 'Copy', 'title' => 'Copy this event'),
				'ical' => array('label' => 'Export', 'title' => 'Download this event as iCal'),
				'print' => array('label' => 'Print', 'title' => 'Print this event'),
				'mail' => array('label' => 'Mail all participants', 'title' => 'compose a mail to all participants after the event is saved'),
			),
		);
		unset($sel_options['status']['G']);
		if (!is_array($event))
		{
			$preserv = array(
				'no_popup' => isset($_GET['no_popup']),
				'template' => isset($_GET['template']) ? $_GET['template'] : (isset($_REQUEST['print']) ? 'calendar.print' : 'calendar.edit'),
			);
			$cal_id = (int) $_GET['cal_id'];

			if (!$cal_id || $cal_id && !($event = $this->bo->read($cal_id)))
			{
				if ($cal_id)
				{
					if (!$preserv['no_popup'])
					{
						$js = "alert('".lang('Permission denied')."'); window.close();";
					}
					else
					{
						$GLOBALS['egw']->framework->render('<p class="redItalic" align="center">'.lang('Permission denied')."</p>\n",null,true);
						common::egw_exit();
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
						$date = new egw_time($_GET['date'], egw_time::$user_timezone);
						$date =& $this->bo->so->startOfDay($date);
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
			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				//$js .= $this->delete_series();
			}
			// set new start and end if given by $_GET
			if(isset($_GET['start'])) { $event['start'] = $_GET['start']; }
			if(isset($_GET['end'])) { $event['end'] = $_GET['end']; }
			// check if the event is the whole day
			$start = $this->bo->date2array($event['start']);
			$end = $this->bo->date2array($event['end']);
			$event['whole_day'] = !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;

			$link_to_id = $event['id'];
			if (!$add_link && !$event['id'] && isset($_GET['link_app']) && isset($_GET['link_id']) &&
				preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$_GET['link_app'].':'.$_GET['link_id']))	// gard against XSS
			{
				egw_link::link('calendar',$link_to_id,$_GET['link_app'],$_GET['link_id']);
			}
		}
		$etpl = new etemplate();
		if (!$etpl->read($preserv['template']))
		{
			$etpl->read($preserv['template'] = 'calendar.edit');
		}
		$view = $preserv['view'] = $preserv['view'] || $event['id'] && !$this->bo->check_perms(EGW_ACL_EDIT,$event);
		//echo "view=$view, event="; _debug_array($event);
		// shared locking of entries to edit
		if (!$view && ($locktime = $GLOBALS['egw_info']['server']['Lock_Time_Calender']) && $event['id'])
		{
			$lock_path = egw_vfs::app_entry_lock_path('calendar',$event['id']);
			$lock_owner = 'mailto:'.$GLOBALS['egw_info']['user']['account_email'];

			if (($preserv['lock_token'] = $content['lock_token']))		// already locked --> refresh the lock
			{
				egw_vfs::lock($lock_path,$preserv['lock_token'],$locktime,$lock_owner,$scope='shared',$type='write',true,false);
			}
			if (($lock = egw_vfs::checkLock($lock_path)) && $lock['owner'] != $lock_owner)
			{
				$msg .= ' '.lang('This entry is currently opened by %1!',
					(($lock_uid = $GLOBALS['egw']->accounts->name2id(substr($lock['owner'],7),'account_email')) ?
					common::grab_owner_name($lock_uid) : $lock['owner']));
			}
			elseif($lock)
			{
				$preserv['lock_token'] = $lock['token'];
			}
			elseif(egw_vfs::lock($lock_path,$preserv['lock_token'],$locktime,$lock_owner,$scope='shared',$type='write',false,false))
			{
                // install ajax handler to unlock the entry again, if the window get's closed by the user (X of window or our [Close] button)
                $GLOBALS['egw']->js->set_onunload("if (do_onunload) xajax_doXMLHTTPsync('calendar.calendar_uiforms.ajax_unlock',$event[id],'$preserv[lock_token]');");
                $GLOBALS['egw']->js->set_onload("replace_eTemplate_onsubmit();");

                // overwrite submit method of eTemplate form AND onSubmit event, to switch off onUnload handler for regular form submits
                // selectboxes use onchange(this.form.submit()) which does not fire onSubmit event --> need to overwrite submit() method
                // regular submit buttons dont call submit(), but trigger onSubmit event --> need to overwrite onSubmit event
                $GLOBALS['egw_info']['flags']['java_script'] .= '
<script>
var do_onunload = true;
function replace_eTemplate_onsubmit()
{
    document.eTemplate.old_submit = document.eTemplate.submit;
    document.eTemplate.submit = function()
    {
        do_onunload = false;
        this.old_submit();
    }
    document.eTemplate.old_onsubmit = document.eTemplate.onsubmit;
    document.eTemplate.onsubmit = function()
    {
        do_onunload = false;
        this.old_onsubmit();
    }
}
</script>
';
			}
			else
			{
				$msg .= ' '.lang("Can't aquire lock!");		// eg. an exclusive lock via CalDAV ...
				$view = true;
			}
			//echo "<p>lock_path=$lock_path, lock_owner=$lock_owner, lock_token=$preserv[lock_token], msg=$msg</p>\n";
		}
		$content = array_merge($event,array(
			'link_to' => array(
				'to_id'  => $link_to_id,
				'to_app' => 'calendar',
			),
			'edit_single' => $preserv['edit_single'],	// need to be in content too, as it is used in the template
			'tabs'   => $preserv['tabs'],
			'view' => $view,
			'msg' => $msg,
		));
		$content['duration'] = $content['end'] - $content['start'];
		if (isset($this->durations[$content['duration']])) $content['end'] = '';

		$row = 2;
		$readonlys = $content['participants'] = $preserv['participants'] = array();
		// preserve some ui elements, if set eg. under error-conditions
		foreach(array('quantity','resource','role') as $n)
		{
			if (isset($event['participants'][$n])) $content['participants'][$n] = $event['participants'][$n];
		}
		foreach($event['participant_types'] as $type => $participants)
		{
			$name = 'accounts';
			if (isset($this->bo->resources[$type]))
			{
				$name = $this->bo->resources[$type]['app'];
			}
			foreach($participants as $id => $status)
			{
				$uid = $type == 'u' ? $id : $type.$id;
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
				$readonlys[$row.'[status]'] = !$this->bo->check_status_perms($uid,$event);
				$readonlys["delete[$uid]"] = $preserv['hide_delete'] || !$this->bo->check_perms(EGW_ACL_EDIT,$event);
				// todo: make the participants available as links with email as title
				if ($name == 'accounts')
				{
					$content['participants'][$row++]['title'] = common::grab_owner_name($id);
				}
				elseif (($info = $this->bo->resource_info($uid)))
				{
					$content['participants'][$row++]['title'] = $info['name'] ? $info['name'] : $info['email'];
				}
				else
				{
					$content['participants'][$row++]['title'] = '#'.$uid;
				}
				// enumerate group-invitations, so people can accept/reject them
				if ($name == 'accounts' && $GLOBALS['egw']->accounts->get_type($id) == 'g' &&
					($members = $GLOBALS['egw']->accounts->members($id,true)))
				{
					$sel_options['status']['G'] = lang('Select one');
					foreach($members as $member)
					{
						if (!isset($participants[$member]) && $this->bo->check_perms(EGW_ACL_READ,0,$member))
						{
							$preserv['participants'][$row] = $content['participants'][$row] = array(
								'app'      => 'Group invitation',
								'uid'      => $member,
								'status'   => 'G',
							);
							// read access is enough to invite participants, but you need edit rights to change status
							if (!$this->bo->check_perms(EGW_ACL_EDIT,0,$member))
							{
								$readonlys[$row.'[quantity]'] = $readonlys["delete[$member]"] =$readonlys[$row]['status']= true;
							}
							else
							{
								$readonlys[$row.'[quantity]'] = $readonlys["delete[$member]"] = true;
							}
							$content['participants'][$row++]['title'] = common::grab_owner_name($member);
						}
					}
				}
			}
			// resouces / apps we shedule, atm. resources and addressbook
			$content['participants']['cal_resources'] = '';
			foreach($this->bo->resources as $data)
			{
				$content['participants']['cal_resources'] .= ','.$data['app'];
			}
			// adding extra content for the resource link-entry widget to
			// * select resources or addressbook as a default selection on the app selectbox based on prefs
			$content['participants']['resource']['default_sel'] = $this->cal_prefs['defaultresource_sel'];
			// * get informations from the event on the ajax callback
			if (in_array($content['participants']['resource']['default_sel'],array('resources_conflict','resources_without_conflict')))
			{
				// fix real app string
				$content['participants']['resource']['default_sel'] = 'resources';
				// this will be used to get reservation information on the resource select list
				$content['participants']['resource']['extra'] = "values2url(this.form,'start,end,duration,participants,recur_type,whole_day')".
					"+'&exec[event_id]=".$content['id']."'"."+'&exec[show_conflict]=".
					(($this->cal_prefs['defaultresource_sel'] == 'resources_without_conflict')? '0':'1')."'";
			}
		}
		$content['participants']['status_date'] = $preserv['actual_date'];
		$preserv = array_merge($preserv,$content);

		if ($event['alarm'])
		{
			// makes keys of the alarm-array starting with 1
			$content['alarm'] = array(false);
			if (!$content['edit_single'])
			{
				foreach(array_values($event['alarm']) as $id => $alarm)
				{
					if (!$alarm['all'] && !$this->bo->check_perms(EGW_ACL_READ,0,$alarm['owner']))
					{
						continue;	// no read rights to the calendar of the alarm-owner, dont show the alarm
					}
					$alarm['all'] = (int) $alarm['all'];
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
					$alarm['offset'] = implode(', ',$label) . ' ' . ($after ? lang('after') : lang('before'));
					$content['alarm'][] = $alarm;

					$readonlys['delete_alarm['.$alarm['id'].']'] = !$this->bo->check_perms(EGW_ACL_EDIT,$alarm['all'] ? $event : 0,$alarm['owner']);
				}
			}
			else
			{
				// hide the alarm tab for newly created exceptions
				$readonlys['tabs']['alarms'] = true;

				// disable the alarm tab functionality
				$readonlys['button[add_alarm]'] = true;
				$readonlys['new_alarm[days]'] = true;
				$readonlys['new_alarm[hours]'] = true;
				$readonlys['new_alarm[mins]'] = true;
				$readonlys['new_alarm[owner]'] = true;
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
			foreach($event as $key => $val)
			{
				if ($key != 'alarm') $readonlys[$key] = true;
			}
			// we need to unset the tab itself, as this would make all content (incl. the change-status selects) readonly
			unset($readonlys['tabs']);
			// participants are handled individual
			unset($readonlys['participants']);

			$readonlys['button[save]'] = $readonlys['button[apply]'] = $readonlys['freetime'] = true;
			$readonlys['link_to'] = $readonlys['customfields'] = true;
			$readonlys['duration'] = true;

			$content['participants']['no_add'] = true;

			// respect category permissions
			if(!empty($event['category']))
			{
				$content['category'] = $this->categories->check_list(EGW_ACL_READ, $event['category']);
			}
		}
		else
		{
			//Add the check_recur_type function to onload, which disables recur_data function
			//if recur_type is not repeat weekly.
			$onload = "check_recur_type('recur_type',2);";
			// We hide the enddate if one of our predefined durations fits
			// the call to set_style_by_class has to be in onload, to make sure the function and the element is already created
			$onload .= " set_style_by_class('table','end_hide','display','".($content['duration'] && isset($sel_options['duration'][$content['duration']]) ? 'none' : 'block')."');";

			$GLOBALS['egw']->js->set_onload($onload);

			$readonlys['recur_exception'] = true;

			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$readonlys['recur_exception'] = !count($content['recur_exception']);	// otherwise we get a delete button
				$onclick =& $etpl->get_cell_attribute('button[delete]','onclick');
				// $onclick = 'delete_series('.$event['id'].');';
				$onclick = str_replace('Delete this event','Delete this series of recuring events',$onclick);

				// some fundamental values of an existing series should not be changed by the user
				//$readonlys['start'] = $readonlys['whole_day'] = true;
				$readonlys['recur_type'] = $readonlys['recur_data'] = true;
				$readonlys['recur_interval'] = $readonlys['tzid'] = true;
			}
			elseif ($event['reference'] != 0)
			{
				$readonlys['recur_type'] = $readonlys['recur_enddate'] = true;
				$readonlys['recur_interval'] = $readonlys['recur_data'] = true;
			}
		}
		// disabling the custom fields tab, if there are none
		$readonlys['tabs'] = array(
			'custom' => !count($this->bo->config['customfields']),
			'participants' => $this->accountsel->account_selection == 'none',
		);
		if (!isset($GLOBALS['egw_info']['user']['apps']['felamimail']))	// no mail without mail-app
		{
			unset($sel_options['action']['mail']);
		}
		if (!$event['id'])	// no ical export for new (not saved) events
		{
			$readonlys['action'] = true;
		}
		if (!($readonlys['button[exception]'] = !$this->bo->check_perms(EGW_ACL_EDIT,$event) || $event['recur_type'] == MCAL_RECUR_NONE))
		{
			$content['exception_label'] = $this->bo->long_date($preserv['actual_date']);
		}
		$readonlys['button[delete]'] = !$event['id'] || $preserv['hide_delete'] || !$this->bo->check_perms(EGW_ACL_DELETE,$event);

		if (!$event['id'] || $this->bo->check_perms(EGW_ACL_EDIT,$event))	// new event or edit rights to the event ==> allow to add alarm for all users
		{
			$sel_options['owner'][0] = lang('All participants');
		}
		if (isset($event['participant_types']['u'][$this->user]))
		{
			$sel_options['owner'][$this->user] = $this->bo->participant_name($this->user);
		}
		foreach((array) $event['participant_types']['u'] as $uid => $status)
		{
			if ($uid != $this->user && $status != 'R' && $this->bo->check_perms(EGW_ACL_EDIT,0,$uid))
			{
				$sel_options['owner'][$uid] = $this->bo->participant_name($uid);
			}
		}
		$content['no_add_alarm'] = !count($sel_options['owner']);	// no rights to set any alarm
		if (!$event['id'])
		{
			$etpl->set_cell_attribute('button[new_alarm]','type','checkbox');
		}
		if ($preserv['no_popup'])
		{
			$etpl->set_cell_attribute('button[cancel]','onclick','');
		}

		// Allow admins to restore deleted events
		$config = config::read('phpgwapi');
		if($config['calendar_delete_history'] && $event['deleted'] && $GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$content['deleted'] = $preserv['deleted'] = false;
			$etpl->set_cell_attribute('button[save]', 'label', 'recover');
			$etpl->set_cell_attribute('button[apply]', 'disabled', true);
		}

		// Setup history tab
		$this->setup_history($content, $sel_options);

		//echo "content="; _debug_array($content);
		//echo "preserv="; _debug_array($preserv);
 		//echo "readonlys="; _debug_array($readonlys);
 		//echo "sel_options="; _debug_array($sel_options);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - '
			. (!$event['id'] ? lang('Add')
				: ($view ? ($content['edit_single'] ? lang('View exception') : ($content['recur_type'] ? lang('View series') : lang('View')))
					: ($content['edit_single'] ? lang('Create exception') : ($content['recur_type'] ? lang('Edit series') : lang('Edit')))));

		//Function for disabling the recur_data multiselect box
		$js .=
			"\nfunction check_recur_type(_id, _ind)\n{\negw_set_checkbox_multiselect_enabled('recur_data',".
			"document.getElementById('exec['+_id+']').selectedIndex == _ind);\n}\n";

		$GLOBALS['egw_info']['flags']['java_script'] .= "<script>\n$js\n</script>\n";

		$content['cancel_needs_refresh'] = (bool)$_GET['cancel_needs_refresh'];

		// non_interactive==true from $_GET calls immediate save action without displaying the edit form
		if(isset($_GET['non_interactive']) && (bool)$_GET['non_interactive'] === true)
		{
			unset($_GET['non_interactive']);	// prevent process_exec <--> edit loops
			$content['button']['save'] = true;
			$this->process_edit(array_merge($content,$preserv));
		}
		else
		{
			$etpl->exec('calendar.calendar_uiforms.process_edit',$content,$sel_options,$readonlys,$preserv,$preserv['no_popup'] ? 0 : 2);
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
		$lock_path = egw_vfs::app_entry_lock_path('calendar',$id);
		$lock_owner = 'mailto:'.$GLOBALS['egw_info']['user']['account_email'];

		if (($lock = egw_vfs::checkLock($lock_path)) && $lock['owner'] == $lock_owner || $lock['token'] == $token)
		{
			egw_vfs::unlock($lock_path,$token,false);
		}
		$response = new xajaxResponse();
		$response->addScript('window.close();');
		return $response->getXML();
	}

	/**
	 * displays a sheduling conflict
	 *
	 * @param array $event
	 * @param array $conflicts array with conflicting events, the events are not garantied to be readable by the user!
	 * @param array $preserv data to preserv
	 */
	function conflicts($event,$conflicts,$preserv)
	{
		$etpl = CreateObject('etemplate.etemplate','calendar.conflicts');

		foreach($conflicts as $k => $conflict)
		{
			$is_readable = $this->bo->check_perms(EGW_ACL_READ,$conflict);

			$conflicts[$k] += array(
				'icon_participants' => $is_readable ? (count($conflict['participants']) > 1 ? 'users' : 'single') : 'private',
				'tooltip_participants' => $is_readable ? implode(', ',$this->bo->participants($conflict)) : '',
				'time' => $this->bo->long_date($conflict['start'],$conflict['end'],true),
				'conflicting_participants' => implode(",\n",$this->bo->participants(array(
					'participants' => array_intersect_key($conflict['participants'],$event['participants']),
				),true,true)),	// show group invitations too
				'icon_recur' => $conflict['recur_type'] != MCAL_RECUR_NONE ? 'recur' : '',
				'text_recur' => $conflict['recur_type'] != MCAL_RECUR_NONE ? lang('Recurring event') : ' ',
			);
		}
		$content = $event + array(
			'conflicts' => array_values($conflicts),	// conflicts have id-start as key
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('Scheduling conflict');

		$etpl->exec('calendar.calendar_uiforms.process_edit',$content,false,false,array_merge($event,$preserv),$preserv['no_popup'] ? 0 : 2);
	}

	/**
	 * displays a confirmation window for changed start dates of series events
	 *
	 * @param array $event
	 * @param array $preserv data to preserv
	 */
	function confirm_edit_series($event,$preserv)
	{
		$etpl = CreateObject('etemplate.etemplate','calendar.confirm_edit_series');

		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('Start of Series Event Changed');

		$etpl->exec('calendar.calendar_uiforms.process_edit',$content,false,false,array_merge($event,$preserv),$preserv['no_popup'] ? 0 : 2);
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
		$response = new xajaxResponse();
		//$response->addAlert(__METHOD__.'('.array2string($edit_content).')');

		if ($edit_content['duration'])
		{
			$edit_content['end'] = $edit_content['start'] + $edit_content['duration'];
		}
		if ($edit_content['whole_day'])
		{
			$arr = $this->bo->date2array($edit_content['start']);
			$arr['hour'] = $arr['minute'] = $arr['second'] = 0; unset($arr['raw']);
			$edit_content['start'] = $this->bo->date2ts($arr);
			$arr = $this->bo->date2array($edit_content['end']);
			$arr['hour'] = 23; $arr['minute'] = $arr['second'] = 59; unset($arr['raw']);
			$edit_content['end'] = $this->bo->date2ts($arr);
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
		egw_cache::setSession('calendar','freetimesearch_args_'.(int)$edit_content['id'],$content);

		//menuaction=calendar.calendar_uiforms.freetimesearch&values2url('start,end,duration,participants,recur_type,whole_day'),ft_search,700,500
		$link = egw::link('/index.php',array(
			'menuaction' => 'calendar.calendar_uiforms.freetimesearch',
			'cal_id'     => $edit_content['id'],
		));

		$response->addScriptCall('egw_openWindowCentered2',$link,'ft_search',700,500);

		return $response->getXML();
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
		$etpl = new etemplate('calendar.freetimesearch');

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
			$content = egw_cache::getSession('calendar','freetimesearch_args_'.(int)$_GET['cal_id']);
			egw_cache::unsetSession('calendar','freetimesearch_args_'.(int)$_GET['cal_id']);

			// pick a searchwindow fitting the duration (search for a 10 day slot in a one week window never succeeds)
			foreach($sel_options['search_window'] as $window => $label)
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

			if (is_array($content['freetime']['select']))
			{
				list($selected) = each($content['freetime']['select']);
				//echo "$selected = ".date('D d.m.Y H:i',$content['freetime'][$selected]['start']);
				$start = (int) $content['freetime'][$selected]['start'];
				$end = $start + $content['duration'];
				/**
				 * ToDo: make this an eTemplate function to transmit content back to the opener
				 */
				$fields_to_set = array(
					'exec[start][str]'	=> date($this->common_prefs['dateformat'],$start),
					'exec[start][i]'	=> (int) date('i',$start),
					'exec[end][str]'	=> date($this->common_prefs['dateformat'],$end),
					'exec[end][i]'		=> (int) date('i',$end),
					'exec[duration]'    => $content['duration'],
				);
				if ($this->common_prefs['timeformat'] == 12)
				{
					$fields_to_set += array(
						'exec[start][H]'	=> date('h',$start),
						'exec[start][a]'	=> date('a',$start),
						'exec[end][H]'		=> date('h',$end),
						'exec[end][a]'		=> date('a',$end),
					);
				}
				else
				{
					$fields_to_set += array(
						'exec[start][H]'	=> (int) date('H',$start),
						'exec[end][H]'		=> (int) date('H',$end),
					);
				}
				echo "<html>
<script>
	var fields = Array('".implode("','",array_keys($fields_to_set))."');
	var values = Array('".implode("','",$fields_to_set)."');
	for (i=0; i < fields.length; ++i) {
		elements = opener.document.getElementsByName(fields[i]);
		if (elements) {
			if (elements.length == 1)
				elements[0].value = values[i];
			else
				for (n=0; n < elements.length; ++n) {
					if (elements[n].value == values[i]) elements[n].checked = true;
				}
		}
	}
	window.close();
</script>
</html>\n";
				exit;
			}
		}
		if ($content['recur_type'])
		{
			$content['msg'] .= lang('Only the initial date of that recuring event is checked!');
		}
		$content['freetime'] = $this->freetime($content['participants'],$content['start'],$content['start']+$content['search_window'],$content['duration'],$content['cal_id']);
		$content['freetime'] = $this->split_freetime_daywise($content['freetime'],$content['duration'],$content['weekdays'],$content['start_time'],$content['end_time'],$sel_options);

		//echo "<pre>".print_r($content,true)."</pre>\n";
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('freetime search');
		// let the window popup, if its already there
		$GLOBALS['egw_info']['flags']['java_script'] .= "<script>\nwindow.focus();\n</script>\n";

		if (!is_object($GLOBALS['egw']->js))
		{
			$GLOBALS['egw']->js = CreateObject('phpgwapi.javascript');
		}
		$sel_options['duration'] = $this->durations;
		if ($content['duration'] && isset($sel_options['duration'][$content['duration']])) $content['end'] = '';
		// We hide the enddate if one of our predefined durations fits
		// the call to set_style_by_class has to be in onload, to make sure the function and the element is already created
		$GLOBALS['egw']->js->set_onload("set_style_by_class('table','end_hide','visibility','".($content['duration'] && isset($sel_options['duration'][$content['duration']]) ? 'hidden' : 'visible')."');");

		$etpl->exec('calendar.calendar_uiforms.freetimesearch',$content,$sel_options,'',array(
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
		if ($this->debug > 2) $this->bo->debug_message('uiforms::freetime(participants=%1, start=%2, end=%3, duration=%4, cal_id=%5)',true,$participants,$start,$end,$duration,$cal_id);

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
	 * @param int $start_time minimum start-hour 0-23
	 * @param int $end_time maximum end-hour 0-23, or 0 for none
	 * @param array $sel_options on return options for start-time selectbox
	 * @return array of free time-slots: array with start and end values
	 */
	function split_freetime_daywise($freetime,$duration,$weekdays,$start_time,$end_time,&$sel_options)
	{
		if ($this->debug > 1) $this->bo->debug_message('uiforms::split_freetime_daywise(freetime=%1, duration=%2, start_time=%3, end_time=%4)',true,$freetime,$duration,$start_time,$end_time);

		$freetime_daywise = array();
		if (!is_array($sel_options)) $sel_options = array();
		$time_format = $this->common_prefs['timeformat'] == 12 ? 'h:i a' : 'H:i';

		$start_time = (int) $start_time;	// ignore leading zeros
		$end_time   = (int) $end_time;

		// ignore the end_time, if duration would never fit
		if (($end_time - $start_time)*HOUR_s < $duration)
		{
			$end_time = 0;
			if ($this->debug > 1) $this->bo->debug_message('uiforms::split_freetime_daywise(, duration=%2, start_time=%3,..) end_time set to 0, it never fits durationn otherwise',true,$duration,$start_time);
		}
		$n = 0;
		foreach($freetime as $ft)
		{
			$daybegin = $this->bo->date2array($ft['start']);
			$daybegin['hour'] = $daybegin['minute'] = $daybegin['second'] = 0;
			unset($daybegin['raw']);
			$daybegin = $this->bo->date2ts($daybegin);

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
				$sel_options[$n.'[start]'] = $times;
			}
		}
		return $freetime_daywise;
	}

	/**
	 * Export events as vCalendar version 2.0 files (iCal)
	 *
	 * @param int|array $content=0 numeric cal_id or submitted content from etempalte::exec
	 * @param boolean $return_error=false should an error-msg be returned or a regular page with it generated (default)
	 * @return string error-msg if $return_error
	 */
	function export($content=0,$return_error=false)
	{
        $boical = new calendar_ical();
		#error_log(__METHOD__.print_r($content,true));
		if (is_numeric($cal_id = $content ? $content : $_REQUEST['cal_id']))
		{
			if (!($ical =& $boical->exportVCal(array($cal_id),'2.0','PUBLISH',false)))
			{
				$msg = lang('Permission denied');

				if ($return_error) return $msg;
			}
			else
			{
				html::content_header('event.ics','text/calendar',bytes($ical));
				echo $ical;
				common::egw_exit();
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
				'date_format'   => 'server',	// timestamp in server time for boical class
			));
			if (!$events)
			{
				$msg = lang('No events found');
			}
			else
			{
				$ical =& $boical->exportVCal($events,'2.0','PUBLISH',false);
				html::content_header($content['file'] ? $content['file'] : 'event.ics','text/calendar',bytes($ical));
				echo $ical;
				common::egw_exit();
			}
		}
		if (!is_array($content))
		{
			$view = $GLOBALS['egw']->session->appsession('view','calendar');

			$content = array(
				'start' => $this->bo->date2ts($_REQUEST['start'] ? $_REQUEST['start'] : $this->date),
				'end'   => $this->bo->date2ts($_REQUEST['end'] ? $_REQUEST['end'] : $this->date),
				'file'  => 'event.ics',
				'version' => '2.0',
			);
		}
		$content['msg'] = $msg;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('iCal Export');
		$etpl = new etemplate('calendar.export');
		$etpl->exec('calendar.calendar_uiforms.export',$content);
	}

	/**
	 * Import events as vCalendar version 2.0 files (iCal)
	 *
	 * @param array $content=null submitted content from etempalte::exec
	 */
	function import($content=null)
	{
		if (is_array($content))
		{
			if (is_array($content['ical_file']) && is_uploaded_file($content['ical_file']['tmp_name']))
			{
				@set_time_limit(0);	// try switching execution time limit off
				$start = microtime(true);

				$calendar_ical = new calendar_ical;
				$calendar_ical->setSupportedFields('file', '');
				if (!$calendar_ical->importVCal($f=fopen($content['ical_file']['tmp_name'],'r')))
				{
					$msg = lang('Error: importing the iCal');
				}
				else
				{
					$msg = lang('iCal successful imported').' '.lang('(%1 events in %2 seconds)',
						$calendar_ical->events_imported,number_format(microtime(true)-$start,1));
				}
				if ($f) fclose($f);
			}
			else
			{
				$msg = lang('You need to select an iCal file first');
			}
		}
		$content = array(
			'msg' => $msg,
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('iCal Import');
		$etpl = new etemplate('calendar.import');

		$etpl->exec('calendar.calendar_uiforms.import',$content);
	}

	/**
	 * Edit category ACL (admin only)
	 *
	 * @param array $content=null
	 */
	function cat_acl(array $content=null)
	{
		if (!$GLOBALS['egw_info']['user']['apps']['admin'])
		{
			throw new egw_exception_no_permission_admin();
		}
		if ($content)
		{
			list($button) = each($content['button']);
			unset($content['button']);
			if ($button != 'cancel')	// store changed acl
			{
				foreach($content['rows'] as $data)
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
				egw::redirect_link('/index.php',array('menuaction' => $this->view_menuaction));
			}
		}
		$content['rows'] = $preserv['rows'] = array();
		$n = 1;
		foreach($this->bo->get_cat_rights() as $Lcat_id => $data)
		{
			$cat_id = (int)substr($Lcat_id,1);
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
			$content['rows'][$n] = $row;
			$preserv['rows'][$n] = array(
				'cat_id' => $cat_id,
				'old' => $data,
			);
			$readonlys[$n.'[cat_id]'] = true;
			++$n;
		}
		// add empty row for new entries
		$content['rows'][] = array('cat_id' => '');

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Calendar').' - '.lang('Category ACL');
		$tmp = new etemplate('calendar.cat_acl');
		$tmp->exec('calendar.calendar_uiforms.cat_acl',$content,null,$readonlys,$preserv);
	}

	/**
	* Set up the required fields to get the history tab
	*/
	public function setup_history(&$content, &$sel_options) {
		$status = 'history_status';

		$content['history'] = array(
			'id'    =>      $content['id'],
			'app'   =>      'calendar',
			'status-widgets'        =>      array(
				'owner'         =>      'select-account',
				'cat_id'        =>      'select-cat',
				'non_blocking'	=>	array(''=>lang('No'), 1=>lang('Yes')),

				'start'		=>	'date-time',
				'end'		=>	'date-time',

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
			),
		);


		// Get participants for only this one, if it's recurring.  The date is on the end of the value.
		if($content['recur_type'] || $content['recurrence']) {
			$content['history']['filter'] = array(
				'(history_status NOT LIKE \'participants%\' OR (history_status LIKE \'participants%\' AND (
					history_new_value LIKE \'%' . bo_tracking::ONE2N_SEPERATOR . $content['recurrence'] . '\' OR
					history_old_value LIKE \'%' . bo_tracking::ONE2N_SEPERATOR . $content['recurrence'] . '\')))'
			);
		}

		// Translate labels
		$tracking = new calendar_tracking();
		foreach($tracking->field2label as $field => $label) {
			$sel_options[$status][$field] = lang($label);
		}
		// Get custom field options
		$custom = config::get_customfields('calendar', true);
		if(is_array($custom)) {
			foreach($custom as $name => $settings) {
				if(!is_array($settings['values'])) {
					$content['history']['status-widgets']['#'.$name] = $settings['type'];
				} elseif($settings['values']['@']) {
					$content['history']['status-widgets']['#'.$name] = customfields_widget::_get_options_from_file($settings['values']['@']);
				} else {
					$content['history']['status-widgets']['#'.$name] = $settings['values'];
				}
			}
		}
	}

	/**
	 * Return HTML and Javascript to query user how to handle the exceptions while deleting the series
	 *
	 * Layout is defined in eTemplate 'calendar.delete_series'
	 *
	 * @param string $link=null url without cal_id and date GET parameters, default calendar.calendar_uiforms.edit
	 * @param string $target='_blank' target
	 * @return string
	 */
	function delete_series($link=null, $target='_blank')
	{
		if (is_null($link)) $link = egw::link('/index.php',array('menuaction'=>'calendar.calendar_uiforms.edit'));

		return '
var calendar_edit_id;
function delete_series(id)
{
	calendar_edit_id = id;

	document.getElementById("delete_series").style.display = "inline";

	return false;
}
function delete_exceptions(delete)
{
	document.getElementById("delete_series").style.display = "none";

	var extra = "&cal_id="+calendar_edit_id+"&action=delete";
	if (delete) extra += "&exceptions=1";

	'.$this->popup($link."'+extra+'").';
}';
	}
}
