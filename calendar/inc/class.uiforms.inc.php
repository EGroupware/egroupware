<?php
/**************************************************************************\
* eGroupWare - Calendar - forms of the UserInterface                       *
* http://www.egroupware.org                                                *
* Written and (c) 2004/5 by Ralf Becker <RalfBecker@outdoor-training.de>   *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(EGW_INCLUDE_ROOT . '/calendar/inc/class.uical.inc.php');

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
 *
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004/5 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uiforms extends uical
{
	var $public_functions = array(
		'freetimesearch'  => True,
		'edit' => true,
		'view' => true,
		'export' => true,
		'import' => true,
	);
	
	/**
	 * Constructor
	 */
	function uiforms()
	{
		$this->uical(true);	// call the parent's constructor
		
		$this->link =& $this->bo->link;
	}
	
	/**
	 * View a calendar event
	 */
	function view()
	{
		return $this->edit(null,array('view' => true));
	}

	/**
	 * Create a default event (adding a new event) by evaluating certain _GET vars
	 *
	 * @return array event-array
	 */ 
	function &default_add_event()
	{
		$extra_participants = $_GET['participants'] ? explode(',',$_GET['participants']) : array();

		$owner = (int) (isset($_GET['owner']) ? $_GET['owner'] : $this->owner);
		if (!$owner || !is_numeric($owner) || $GLOBALS['egw']->accounts->get_type($owner) != 'u' || 
			!$this->bo->check_perms(EGW_ACL_ADD,0,$owner))
		{
			if ($owner)	// make an owner who is no user or we have no add-rights a participant
			{
				$extra_participants[] = $owner;
			}
			$owner = $this->user;
		}
		//echo "<p>this->owner=$this->owner, _GET[owner]=$_GET[owner], user=$this->user => owner=$owner</p>\n";

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

			if (is_numeric($uid))
			{
				$participants[$uid] = $participant_types['u'][$uid] = $uid == $this->user ? 'A' : 'U';
			}
			elseif (is_array($this->bo->resources[$uid{0}]))
			{
				$res_data = $this->bo->resources[$uid{0}];
				list($id,$quantity) = explode(':',substr($uid,1));
				$participants[$uid] = $participant_types[$uid{0}][$id] = ($res_data['new_status'] ? ExecMethod($res_data['new_status'],$id) : 'U').
					((int) $quantity > 1 ? (int)$quantity : '');
				// if new_status == 'x', resource is not bookable
				if(strstr($participant_types[$uid{0}][$id],'x')) 
				{
					unset($participant_types[$uid{0}][$id]);
					unset($participants[$uid]);
				}
			}
		}
		return array(
			'participant_types' => $participant_types,
			'participants' => $participants,
			'owner' => $owner,
			'start' => $start,
			'end'   => $start + (int) $this->bo->cal_prefs['defaultlength']*60,
			'priority' => 2,	// normal
			'public'=> $this->cal_prefs['default_private'] ? 0 : 1,
			'alarm' => array(),
		);
	}
	
	/**
	 * Process the edited event and evtl. call edit to redisplay it
	 *
	 * @param array $content posted eTemplate content
	 */
	function process_edit($content)
	{
		//echo "content submitted="; _debug_array($content);
		list($button) = @each($content['button']);
		unset($content['button']);

		$view = $content['view'] && $button != 'edit' && $button != 'edit_series';
		// if we edit a single recurrance, we need to remember it for save and need to reset the recur information
		if ($button == 'edit' && $content['recur_type'] != MCAL_RECUR_NONE)
		{
			$content['edit_single'] = $content['start'];
			$content['recur_type'] = MCAL_RECUR_NONE;
			foreach(array('recur_enddate','recur_interval','recur_exception','recur_data') as $name)
			{
				unset($content[$name]);
			}
		}
		if ($view && $button == 'vcal')
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
		$event = $content;
		unset($event['new_alarm']);
		unset($event['alarm']['delete_alarm']);

		if (isset($content['participants']['accounts']) && !(isset($content['view']) && $content['view']))	// convert content => event
		{
			//echo "participants-data"; _debug_array($content['participants']);
			$event['participants'] = $event['participant_types'] = array();
			foreach($content['participants'] as $app => $participants)
			{
				if (!$participants) continue;

				$type = 'u';
				foreach($this->bo->resources as $t => $data)
				{
					if ($data['app'] == $app)
					{
						$type = $t;
						break;
					}
				}
				foreach(is_array($participants) ? $participants : explode(',',$participants) as $id)
				{
					if (is_array($id)) continue;	// ignore the status
					list($id,$quantity) = explode(':',$id);
					$event['participants'][$type == 'u' ? (int) $id : $type.$id] = $event['participant_types'][$type][$id] = 
						// for existing participants use their old status (dont change it)
						(isset($content['participant_types'][$type][$id]) ? $content['participant_types'][$type][$id]{0} : 
						// for new participants check if they have a 'new_status' resource-methode to determine the status
						(isset($this->bo->resources[$type]['new_status']) ? ExecMethod($this->bo->resources[$type]['new_status'],$id) : 
						// if not use 'A'=accepted for the current user and 'U' otherwise
						($type == 'u' && $id == $this->bo->user ? 'A' : 'U'))).((int) $quantity > 1 ? (int)$quantity : '');
						// ToDo: move this logic into bocal
				}
			}
			if ($content['whole_day'])
			{
				$event['start'] = $this->bo->date2array($event['start']);
				$event['start']['hour'] = $event['start']['minute'] = 0; unset($event['start']['raw']);
				$event['start'] = $this->bo->date2ts($event['start']);
				$event['end'] = $this->bo->date2array($event['end']);
				$event['end']['hour'] = 23; $event['end']['minute'] = $event['end']['second'] = 59; unset($event['end']['raw']);
				$event['end'] = $this->bo->date2ts($event['end']);
			}
			// some checks for recurances, if you give a date, make it a weekly repeating event and visa versa
			if ($event['recur_type'] == MCAL_RECUR_NONE && $event['recur_data']) $event['recur_type'] = MCAL_RECUR_WEEKLY;
			if ($event['recur_type'] == MCAL_RECUR_WEEKLY && !$event['recur_data'])
			{
				$event['recur_data'] = 1 << (int)date('w',$event['start']);
			}
		}
		else	// status change view
		{
			foreach($event['participants'] as $name => $data)
			{
				if (!is_array($data)) continue;

				$type = 'u';
				foreach($this->bo->resources as $t => $d)
				{
					if ($d['app'] == $name)
					{
						$type = $t;
						break;
					}
				}
				// checking for status changes
				foreach($data[$name.'_status'] as $uid => $status)
				{
					list($uid,$quantity) = explode(':',$uid);
					//echo "checking $type: $uid $status against old ".$event['participant_types'][$type][$uid]."<br />\n";
					if ($event['participant_types'][$type][$uid]{0} != $status{0})	// status changed by user
					{
						if ($this->bo->set_status($event['id'],$type,$uid,$status,$event['recur_type'] != MCAL_RECUR_NONE ? $event['start'] : 0))
						{
							$event['participants'][$type == 'u' ? (int) $uid : $type.$uid] = 
								$event['participant_types'][$type][$uid] = $status.$quantity;
							// refreshing the calendar-view with the changed participant-status
							$msg = lang('Status changed');
							if (!$preserv['no_popup'])
							{
								$js = 'opener.location.href=\''.addslashes($GLOBALS['egw']->link('/index.php',array(
									'menuaction' => $content['referer'],
									'msg' => $msg,
								))).'\';';
							}
						}
					}
				}
				unset($event['participants'][$name]);	// unset the status-changes from the event
			}
		}
		$preserv = array(
			'view'        => $view,
			'edit_single' => $content['edit_single'],
			'referer'     => $content['referer'],
			'no_popup'    => $content['no_popup'],
		);
		switch($button)
		{
		case 'edit_series':
			if ($content['recur_type'] != MCAL_RECUR_NONE)
			{
				// need to reload start and end of the serie
				$event = $this->bo->read($event['id'],0);
			}
			break;
				
		case 'copy':	// create new event with copied content, some content need to be unset to make a "new" event
			unset($event['id']);
			unset($event['uid']);
			unset($event['alarm']);
			unset($event['reference']);
			unset($event['recur_exception']);
			unset($event['edit_single']);	// in case it has been set
			unset($event['modified']);
			unset($event['modifier']);
			$event['owner'] = !(int)$this->owner || !$this->bo->check_perms(EGW_ACL_ADD,0,$this->owner) ? $this->user : $this->owner;
			$preserv['view'] = $preserv['edit_single'] = false;
			$msg = lang('Event copied - the copy can now be edited');
			$event['title'] = lang('Copy of:').' '.$event['title'];
			break;
			
		case 'ignore':
			$ignore_conflicts = true;
			$button = $event['button_was'];	// save or apply
			unset($event['button_was']);
			// fall through
		case 'save':
		case 'apply':
			if ($event['id'] && !$this->bo->check_perms(EGW_ACL_EDIT,$event)) 
			{ 
				$msg = lang('Permission denied'); 
				break; 
			}
			if ($event['start'] > $event['end'])
			{
				$msg = lang('Error: Starttime has to be before the endtime !!!');
				$button = '';
				break;
			}
			if (!$event['participants'])
			{
				$msg = lang('Error: no participants selected !!!');
				$button = '';
				break;
			}
			if ($content['edit_single'])	// we edited a single event from a series
			{
				$event['reference'] = $event['id'];
				unset($event['id']);
				unset($event['uid']);
				$conflicts = $this->bo->update($event,$ignore_conflicts);
				if (!is_array($conflicts) && $conflicts)
				{
					// now we need to add the original start as recur-execption to the series
					$recur_event = $this->bo->read($event['reference']);
					$recur_event['recur_exception'][] = $content['edit_single'];
					$this->bo->update($recur_event,true);	// no conflict check here
					unset($recur_event);
					unset($event['edit_single']);			// if we further edit it, it's just a single event
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
				$conflicts = $this->bo->update($event,$ignore_conflicts);
				unset($event['ignore']);
			}
			if (is_array($conflicts))
			{
				$event['button_was'] = $button;	// remember for ignore
				return $this->conflicts($event,$conflicts,$preserv);
			}
			elseif($conflicts)	// true and non-array means Ok ;-)
			{
				$msg .= ($msg ? ', ' : '') . lang('Event saved');

				// writing links for new entry, existing ones are handled by the widget itself
				if (!$content['id'] && is_array($content['link_to']['to_id']))	
				{
					$this->link->link('calendar',$event['id'],$content['link_to']['to_id']);
				}
				$js = 'opener.location.href=\''.addslashes($GLOBALS['egw']->link('/index.php',array(
					'menuaction' => $content['referer'],
					'msg' => $msg,
				))).'\';';

				if ($content['custom_mail'])
				{
					$js = $this->custom_mail($event,!$content['id'])."\n".$js;	// first open the new window and then update the view
					unset($event['custom_mail']);
				}
			}
			else
			{
				$msg = lang('Error: saving the event !!!');
			}
			break;
			
		case 'delete':
		case 'delete_series':
			if (!$this->bo->check_perms(EGW_ACL_DELETE,$event)) break;
			if (!$content['edit_series'] && $button == 'delete' && $event['recur_type'] != MCAL_RECUR_NONE)	// delete a single event from a recuring event
			{
				$this->bo->delete($event['id'],$event['start']);
			}
			else
			{
				$this->bo->delete($event['id']);
			}
			$msg = lang('Event deleted');
			$js = 'opener.location.href=\''.addslashes($GLOBALS['egw']->link('/index.php',array(
				'menuaction' => $content['referer'],
				'msg' => $msg,
			))).'\';';
			break;
			
		case 'freetime':
			if (!is_object($GLOBALS['egw']->js))
			{
				$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			// the "click" has to be in onload, to make sure the button is already created
			$GLOBALS['egw']->js->set_onload("document.getElementsByName('exec[freetime]')[0].click();");
			break;
			
		case 'add_alarm':
			if ($this->bo->check_perms(EGW_ACL_EDIT,!$content['new_alarm']['owner'] ? $event : 0,$content['new_alarm']['owner']))
			{
				$offset = DAY_s * $content['new_alarm']['days'] + HOUR_s * $content['new_alarm']['hours'] + 60 * $content['new_alarm']['mins'];
				$alarm = array(
					'offset' => $offset,
					'time'   => $content['start'] - $offset,
					'all'    => !$content['new_alarm']['owner'],
					'owner'  => $content['new_alarm']['owner'] ? $content['new_alarm']['owner'] : $this->user,
				);
				if ($alarm['time'] < $this->bo->now_su)
				{
					$msg = lang("Can't add alarms in the past !!!");
				}
				elseif ($event['id'])	// save the alarm immediatly
				{
					if(($alarm_id = $this->bo->save_alarm($event['id'],$alarm)))
					{
						$alarm['id'] = $alarm_id;
						$event['alarm'][$alarm_id] = $alarm;
						
						$msg = lang('Alarm added');
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
		if (in_array($button,array('cancel','save','delete','delete_series')))
		{
			if ($content['no_popup'])
			{
				$GLOBALS['egw']->redirect_link('/index.php',array(
					'menuaction' => $content['referer'],
					'msg'        => $msg,
				));
			}
			$js .= 'window.close();';
			echo "<html><body onload=\"$js\"></body></html>\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		return $this->edit($event,$preserv,$msg,$js,$event['id'] ? $event['id'] : $content['link_to']['to_id']);
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
			if (is_numeric($uid) && $uid != $this->user && $status != 'R' && $GLOBALS['egw']->accounts->get_type($uid) == 'u')
			{
				$GLOBALS['egw']->accounts->get_account_name($uid,$lid,$firstname,$lastname);
				 
				$to[] = '"'.$GLOBALS['egw']->common->display_fullname('',$firstname,$lastname).
					'" <'.$GLOBALS['egw']->accounts->id2name($uid,'account_email').'>';
			}
		}
		list($subject,$body) = $this->bo->get_update_message($event,$added ? MSG_ADDED : MSG_MODIFIED);	// update-message is in TZ of the user

		$boical =& CreateObject('calendar.boical');
		$ics = $boical->exportVCal(array($event),'2.0','request');

		$ics_file = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'ics');
		if(($f = fopen($ics_file,'w')))
		{
			fwrite($f,$ics);
			fclose($f);
		}
		$vars = array(
			'menuaction'      => 'felamimail.uicompose.compose',
			'preset[to]'      => implode(', ',$to),
			'preset[subject]' => $subject,
			'preset[body]'    => $body,
			'preset[name]'    => 'event.ics',
			'preset[file]'    => $ics_file,
			'preset[type]'    => 'text/calendar; method=request',
			'preset[size]'    => filesize($ics_file),
		);
		return "window.open('".addslashes($GLOBALS['egw']->link('/index.php',$vars))."','_blank','width=700,height=700,scrollbars=yes,status=no');";
	}

	/**
	 * Edit a calendar event
	 *
	 * @param array $event=null Event to edit, if not $_GET['cal_id'] contains the event-id
	 * @param array $perserv=null following keys:
	 *	view boolean view-mode, if no edit-access we automatic fallback to view-mode
	 *	referer string menuaction of the referer
	 *	no_popup boolean use a popup or not
	 *	edit_single int timestamp of single event edited, unset/null otherwise
	 * @param string $msg='' msg to display
	 * @param string $js='window.focus();' javascript to include in the page
	 * @param mixed $link_to_id='' $content from or for the link-widget
	 */
	function edit($event=null,$preserv=null,$msg='',$js = 'window.focus();',$link_to_id='')
	{
		$etpl =& CreateObject('etemplate.etemplate','calendar.edit');
		
		$sel_options = array(
			'recur_type' => &$this->bo->recur_types,
			'accounts_status' => $this->bo->verbose_status,
			'owner'           => array(),
		);
		if (!is_array($event))
		{
			$preserv = array(
				'no_popup' => isset($_GET['no_popup']),
				'referer'  => preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ? $matches[1] : $this->view_menuaction,
				'view'     => $preserv['view'],
			);
			$cal_id = (int) $_GET['cal_id'];
			
			if (!$cal_id || $cal_id && !($event = $this->bo->read($cal_id,$_GET['date'])) || !$this->bo->check_perms(EGW_ACL_READ,$event))
			{
				if ($cal_id) 
				{
					if (!$preserv['no_popup'])
					{
						$js = "alert('".lang('Permission denied')."'); window.close();";
					}
					else
					{
						$GLOBALS['egw']->common->egw_header();
						parse_navbar();
						echo '<p class="redItalic" align="center">'.lang('Permission denied')."</p>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
				}
				$event =& $this->default_add_event();
			}
			// check if the event is the whole day
			$start = $this->bo->date2array($event['start']);
			$end = $this->bo->date2array($event['end']);
			$event['whole_day'] = !$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59;

			$link_to_id = $event['id'];
			if (!$add_link && !$event['id'] && isset($_GET['link_app']) && isset($_GET['link_id']) &&
				preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$_GET['link_app'].':'.$_GET['link_id']))	// gard against XSS
			{
				$this->link->link('calendar',$link_to_id,$_GET['link_app'],$_GET['link_id']);
			}
		}
		$view = $preserv['view'] = $preserv['view'] || $event['id'] && !$this->bo->check_perms(EGW_ACL_EDIT,$event);
		//echo "view=$view, event="; _debug_array($event);
		$content = array_merge($event,array(
			'link_to' => array(
				'to_id'  => $link_to_id,
				'to_app' => 'calendar',
			),
			'edit_single' => $preserv['edit_single'],	// need to be in content too, as it is used in the template
			'view' => $view,
		));
		$content['participants'] = array();
		foreach($event['participant_types'] as $type => $participants)
		{
			$name = 'accounts';
			if (isset($this->bo->resources[$type]))
			{
				$name = $this->bo->resources[$type]['app'];
			}
// 			_debug_array($participants);
			foreach($participants as $id => $status)
			{
				$content['participants'][$name][] = $id . (substr($status,1) > 1 ? (':'.substr($status,1)) : '');
			}

			if ($view)
			{
				$stati =& $content['participants'][$name][$name.'_status'];
				$stati = $participants;
				// enumerate group-invitations, so people can accept/reject them
				if ($name == 'accounts')
				{
					foreach($participants as $id => $status)
					{
						if ($GLOBALS['egw']->accounts->get_type($id) == 'g' &&
							($members = $GLOBALS['egw']->accounts->member($id)))
						{
							$sel_options['accounts_status']['G'] = lang('Select one');
							foreach($members as $member)
							{
								if (!isset($stati[$member['account_id']]) && $this->bo->check_perms(EGW_ACL_EDIT,0,$member['account_id']))
								{
									$stati[$member['account_id']] = 'G';	// status for invitation via membership in group
									$content['participants'][$name][] = $member['account_id'];
								}
							}
						}
					}
				}
				foreach($stati as $id => $status)
				{
					$readonlys[$name.'_status['.$id.']'] = !$this->bo->check_perms(EGW_ACL_EDIT,0,($type != 'u' ? $type : '').$id);
				}
			}
		}
// 		echo '$content[participants]'; _debug_array($content['participants']);
// 		echo '$content[participant_types]'; _debug_array($content['participant_types']);
// 		_debug_array($sel_options);
		$preserv = array_merge($preserv,$view ? $event : $content);

		if ($event['alarm'])
		{
			// makes keys of the alarm-array starting with 1
			$content['alarm'] = array(false);
			foreach(array_values($event['alarm']) as $id => $alarm)
			{
				if (!$alarm['all'] && !$this->bo->check_perms(EGW_ACL_READALARM,0,$alarm['owner']))
				{
					continue;	// no read rights to the calendar of the alarm-owner, dont show the alarm
				}
				$alarm['all'] = (int) $alarm['all'];
				$days = (int) ($alarm['offset'] / DAY_s);
				$hours = (int) (($alarm['offset'] % DAY_s) / HOUR_s);
				$minutes = (int) (($alarm['offset'] % HOUR_s) / 60);
				$label = array();
				if ($days) $label[] = $days.' '.lang('days');
				if ($hours) $label[] = $hours.' '.lang('hours');
				if ($minutes) $label[] = $minutes.' '.lang('Minutes');
				$alarm['offset'] = implode(', ',$label);
				$content['alarm'][] = $alarm;
				
				$readonlys['delete_alarm['.$id.']'] = !$this->bo->check_perms(EGW_ACL_EDIT,$alarm['all'] ? $event : 0,$alarm['owner']);
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
			unset($readonlys['general|description|participants|recurrence|custom|links|alarms']);

			$readonlys['button[save]'] = $readonlys['button[apply]'] = $readonlys['freetime'] = true;
			$readonlys['link'] = $readonlys['link_to'] = $readonlys['customfields'] = true;
		}
		else
		{
			$readonlys['button[copy]'] = $readonlys['button[vcal]'] = true;
			unset($preserv['participants']);	// otherwise deleted participants are still reported
			$readonlys['recur_exception'] = !count($content['recur_exception']);	// otherwise we get a delete button
		}
		// disabling the custom fields tab, if there are none
		$readonlys['general|description|participants|recurrence|custom|links|alarms'] = array(
			'custom' => !count($this->bo->config['customfields'])
		);
		if ($view || !isset($GLOBALS['egw_info']['user']['apps']['felamimail']))
		{
			$etpl->disable_cells('custom_mail');
		}
		if (!($readonlys['button[edit_series]'] = $readonlys['button[edit]'] = !$view || !$this->bo->check_perms(EGW_ACL_EDIT,$event)))
		{
			$readonlys['button[edit_series]'] = $event['recur_type'] == MCAL_RECUR_NONE;
		}
		if (!($readonlys['button[delete_series]'] = $readonlys['button[delete]'] = !$event['id'] || !$this->bo->check_perms(EGW_ACL_DELETE,$event)))
		{
			$readonlys['button[delete_series]'] = !$view || $event['recur_type'] == MCAL_RECUR_NONE;
		}
		if ($event['id'] || $this->bo->check_perms(EGW_ACL_EDIT,$event))	// new event or edit rights to the event ==> allow to add alarm for all users
		{
			$sel_options['owner'][0] = lang('All participants');
		}
		foreach((array) $event['participant_types']['u'] as $uid => $status)
		{
			if ($status != 'R' && $this->bo->check_perms(EGW_ACL_EDIT,0,$uid))
			{
				$sel_options['owner'][$uid] = $this->bo->participant_name($uid);
			}
		}
		$content['no_add_alarm'] = !count($sel_options['owner']);	// no rights to set any alarm
		if (!$event['id'])
		{
			$etpl->set_cell_attribute('button[new_alarm]','type','checkbox');	
		}
		foreach($this->bo->resources as $res_data)
		{
			$sel_options[$res_data['app'].'_status'] =& $this->bo->verbose_status;
		}
		if ($preserv['no_popup'])
		{
			$etpl->set_cell_attribute('button[cancel]','onclick','');
		}
		//echo "content="; _debug_array($content);
		//echo "preserv="; _debug_array($preserv);
 		//echo "readonlys="; _debug_array($readonlys);
 		//echo "sel_options="; _debug_array($sel_options);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . ($event['id'] ? ($view ? lang('View') : lang('Edit')) : lang('Add'));
		$GLOBALS['egw_info']['flags']['java_script'] .= "<script>\n$js\n</script>\n";
		$etpl->exec('calendar.uiforms.process_edit',$content,$sel_options,$readonlys,$preserv,$preserv['no_popup'] ? 0 : 2);
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
		$etpl =& CreateObject('etemplate.etemplate','calendar.conflicts');
		
		foreach($conflicts as $k => $conflict)
		{
			$is_readable = $this->bo->check_perms(EGW_ACL_READ,$conflict);
			
			$conflicts[$k] += array(
				'icon_participants' => $is_readable ? (count($conflict['participants']) > 1 ? 'users' : 'single') : 'private',
				'tooltip_participants' => $is_readable ? implode(', ',$this->bo->participants($conflict)) : '',
				'time' => $this->bo->long_date($conflict['start'],$conflict['end'],true),
				'conflicting_participants' => implode(",\n",$this->bo->participants(array(
					'participants' => array_intersect_key($conflict['participants'],$event['participants']),
				))),
				'icon_recur' => $conflict['recur_type'] != MCAL_RECUR_NONE ? 'recur' : '',
				'text_recur' => $conflict['recur_type'] != MCAL_RECUR_NONE ? lang('Recurring event') : ' ',
			);		
		}
		$content = $event + array(
			'conflicts' => array_values($conflicts),	// conflicts have id-start as key
		);
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('Scheduling conflict');

		$etpl->exec('calendar.uiforms.process_edit',$content,false,false,array_merge($event,$preserv),$preserv['no_popup'] ? 0 : 2);
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
		$etpl =& CreateObject('etemplate.etemplate','calendar.freetimesearch');

		$sel_options['search_window'] = array(
			7*DAY_s		=> lang('one week'),
			14*DAY_s	=> lang('two weeks'),
			31*DAY_s	=> lang('one month'),
			92*DAY_s	=> lang('three month'),
			365*DAY_s	=> lang('one year'),
		);
		if (!is_array($content))
		{
			$edit_content = $etpl->process_values2url();
			
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
			foreach($edit_content['participants'] as $app => $ids)
			{
				if ($app == 'accounts')
				{
					$content['participants'] += $ids;
				}
				elseif ($ids)
				{
					foreach($this->bo->resources as $type => $data)
					{
						if ($data['app'] == $app) break;
					}
					foreach($ids as $id)
					{
						$content['participants'][] = $type . $id;
					}
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
		$sel_options['duration'][] = lang('use end date');
		for ($n=15; $n <= 8*60; $n+=($n < 60 ? 15 : ($n < 240 ? 30 : 60)))
		{
			$sel_options['duration'][$n*60] = sprintf('%d:%02d',$n/60,$n%60);
		}
		if ($content['duration'] <= 8*HOUR_s) $content['end'] = '';

		//echo "<pre>".print_r($content,true)."</pre>\n";
		$GLOBALS['egw_info']['flags']['app_header'] = lang('calendar') . ' - ' . lang('freetime search');
		// let the window popup, if its already there
		$GLOBALS['egw_info']['flags']['java_script'] .= "<script>\nwindow.focus();\n</script>\n";
		
		if (!is_object($GLOBALS['egw']->js))
		{
			$GLOBALS['egw']->js = CreateObject('phpgwapi.javascript');
		}
		// We hide the enddate if one of our predefined durations fits
		// the call to set_style_by_class has to be in onload, to make sure the function and the element is already created
		$GLOBALS['egw']->js->set_onload("set_style_by_class('*','end_hide','visibility','".($content['duration'] && isset($sel_options['duration'][$content['duration']]) ? 'hidden' : 'visible')."');");
		$etpl->set_cell_attribute('duration','onchange',"set_style_by_class('*','end_hide','visibility',this.value == 0 ? 'visible' : 'hidden');");

		$etpl->exec('calendar.uiforms.freetimesearch',$content,$sel_options,'',array(
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

			if ($this->debug)
			{
				echo "<p>ft_start=".date('D d.m.Y H:i',$ft_start)."<br>\n";
				echo "event[title]=$event[title]<br>\n";
				echo "event[start]=".date('D d.m.Y H:i',$event['start']['raw'])."<br>\n";
				echo "event[end]=".date('D d.m.Y H:i',$event['end']['raw'])."<br>\n";
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
	 * @param int/array $content=0 numeric cal_id or submitted content from etempalte::exec
	 * @param boolean $return_error=false should an error-msg be returned or a regular page with it generated (default)
	 * @return string error-msg if $return_error
	 */
	function export($content=0,$return_error=false)
	{
		if (is_numeric($cal_id = $content ? $content : $_REQUEST['cal_id']))
		{
			if (!($ical =& ExecMethod2('calendar.boical.exportVCal',$cal_id,'2.0')))
			{
				$msg = lang('Permission denied');
				
				if ($return_error) return $msg;
			}
			else
			{
				$GLOBALS['egw']->browser =& CreateObject('phpgwapi.browser');
				$GLOBALS['egw']->browser->content_header('event.ics','text/calendar',strlen($ical));
				echo $ical;
				$GLOBALS['egw']->common->egw_exit();
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
				$ical =& ExecMethod('calendar.boical.exportVCal',$events,'2.0'/*$content['version']*/);
				$GLOBALS['egw']->browser =& CreateObject('phpgwapi.browser');
				$GLOBALS['egw']->browser->content_header($content['file'] ? $content['file'] : 'event.ics','text/calendar',strlen($ical));
				echo $ical;
				$GLOBALS['egw']->common->egw_exit();
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
		$etpl =& CreateObject('etemplate.etemplate','calendar.export');
		
		$etpl->exec('calendar.uiforms.export',$content);
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
				if (!ExecMethod('calendar.boical.importVCal',file_get_contents($content['ical_file']['tmp_name'])))
				{
					$msg = lang('Error: importing the iCal');
				}
				else
				{
					$msg = lang('iCal successful imported');
				}
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
		$etpl =& CreateObject('etemplate.etemplate','calendar.import');
		
		$etpl->exec('calendar.uiforms.import',$content);
	}
}
