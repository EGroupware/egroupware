<?php
/**
 * eGroupWare - Calendar hooks
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;

/**
 * diverse static calendar hooks
 */
class calendar_hooks
{
	/**
	 * Hook called by link-class to include calendar in the appregistry of the linkage
	 *
	 * @param array|string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		unset($location);	// not used, but in function signature for hooks
		return array(
			'query' => 'calendar.calendar_bo.link_query',
			'title' => 'calendar.calendar_bo.link_title',
			'view'  => array(
				'menuaction' => 'calendar.calendar_uiforms.edit',
			),
			'view_id'    => 'cal_id',
			'view_popup' => '850x590',
			'edit_popup' => '850x590',
			'list'  => array(
				'menuaction' => 'calendar.calendar_uiviews.index',
				'view' => 'listview',
				'ajax'=>'true'
			),
			// If calendar is not loaded, load it first, then add
			'add'        => 'javascript:var promise = framework.setActiveApp(framework.getApplicationByName(\'calendar\')); if(promise) {promise.then(function() {et2_call(\'app.calendar.add\',params);});} else { app.calendar.add(params);}',
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'file_access' => 'calendar.calendar_bo.file_access',
			'file_access_user' => true,	// file_access supports 4th parameter $user
			'mime' => array(
				'text/calendar' => array(
					'menuaction' => 'calendar.calendar_uiforms.edit',
					'mime_data' => 'ical_data',
					'mime_url' => 'ical_url',
					'mime_popup' => '850x590',
					'mime_target' => '_blank'
				),
				'application/ics' => array(
					'menuaction' => 'calendar.calendar_uiforms.edit',
					'mime_data' => 'ical_data',
					'mime_url' => 'ical_url',
					'mime_popup' => '850x590',
					'mime_target' => '_blank'
				),
			),
			'merge' => true,
			'entry' => 'Event',
			'entries' => 'Events',
			'push_data'  => self::class.'::prepareEventPush',
		);
	}


	/**
	 * Prepare event to be pushed via Link::notify_update()
	 *
	 * Remove privacy sensitive data:
	 * - participants of type email
	 *
	 * @param $event
	 * @return array
	 */
	static public function prepareEventPush($event)
	{
		$send_keys = ['id', 'owner', 'participants', 'start', 'end'];
		if($event['recur_type'])
		{
			// If it's a recurring event, we're only sending the first instance, which may be outside of the current
			// view and therefore would be ignored by the client.  Include range for additional check.
			$send_keys[] = 'range_start';
			$send_keys[] = 'range_end';
		}
		$event = array_intersect_key($event, array_flip($send_keys));
		foreach($event['participants'] as $uid => $status)
		{
			if($uid[0] === 'e')
			{
				unset($event['participants'][$uid]);
			}
		}
		return $event;
	}

	/**
	 * Hook called to retrieve a app specific exportLimit
	 *
	 * @param array|string $location location and other parameters (not used)
	 * @return the export_limit to be applied for the app, may be empty, int or string
	 */
	static function getAppExportLimit($location)
	{
		unset($location);	// not used, but in function signature for hooks
		return $GLOBALS['egw_info']['server']['calendar_export_limit'];
	}

	/**
	 * Entries for calendar's admin menu
	 */
	static function admin()
	{
		$file = array(
			'Site Configuration' => Egw::link('/index.php', 'menuaction=admin.admin_config.index&appname=calendar&ajax=true'),
			'Custom fields'      => Egw::link('/index.php', 'menuaction=admin.admin_customfields.index&appname=calendar&ajax=true'),
			'Global Categories'  => Egw::link('/index.php', 'menuaction=admin.admin_categories.index&appname=calendar&ajax=true'),
			'Category ACL'       => Egw::link('/index.php', 'menuaction=calendar.calendar_uiforms.cat_acl&appname=calendar&ajax=true'),
			'Update timezones'   => Egw::link('/index.php', 'menuaction=calendar.calendar_timezones.update'),
		);
		display_section('calendar','calendar',$file);
	}

	/**
	 * Calendar preferences
	 *
	 * @param array $hook_data
	 */
	static function settings($hook_data)
	{
		if (!$hook_data['setup'])	// does not work on setup time
		{
			$bo = new calendar_bo();
			$bo->check_set_default_prefs();
		}
		$yesno = array(
			'1' => lang('Yes'),
			'0' => lang('No'),
		);
		$list_views = array(
			0 => lang('None'),
			'month' => lang('Monthview'),
			'weekN' => lang('Multiple week view'),
			'week' => lang('Weekview'),
			'day4' => lang('Four days view'),
			'day' => lang('Dayview'),
		);
		$updates = array(
			'no'             => lang('Never'),
			'add_cancel'     => lang('on invitation / cancellation only'),
			'time_change_4h' => lang('on time change of more than 4 hours too'),
			'time_change'    => lang('on any time change too'),
			'modifications'  => lang('on all modification, but responses'),
			'responses'      => lang('on participant responses too')
		);
		$update_formats = array(
			'none'     => lang('None'),
			'extended' => lang('Extended'),
			'ical'     => lang('iCal / rfc2445'),
		);
		$event_details = array(
			'to-fullname'  => lang('Fullname of person to notify'),
			'to-firstname' => lang('Firstname of person to notify'),
			'to-lastname'  => lang('Lastname of person to notify'),
			'title'        => lang('Title of the event'),
			'description'  => lang('Description'),
			'startdate'    => lang('Start Date/Time'),
			'enddate'      => lang('End Date/Time'),
			'olddate'      => lang('Old Startdate'),
			'category'     => lang('Category'),
			'location'     => lang('Location'),
			'priority'     => lang('Priority'),
			'participants' => lang('Participants'),
			'owner'        => lang('Owner'),
			'repetition'   => lang('Repetitiondetails (or empty)'),
			'action'       => lang('Action that caused the notify: Added, Canceled, Accepted, Rejected, ...'),
			'link'         => lang('Link to view the event'),
			'disinvited'   => lang('Participants uninvited from an event'),
			'fullname'     => lang('Current user'),
			'date'         => lang('Current date')
		);
		$weekdaystarts = array(
			'Monday'   => lang('Monday'),
			'Sunday'   => lang('Sunday'),
			'Saturday' => lang('Saturday')
		);
		$birthdays_as_events = array(
			'none'     => lang('None'),
			'birthday' => lang('Birthdays'),
			'holiday'  => lang('Holidays')
		);

		if (!isset($hook_data['setup']))
		{
			$times = Api\Etemplate\Widget\Select::typeOptions('select-hour', '');
			$default_cat_seloptions = Api\Etemplate\Widget\Select::typeOptions('select-cat', ',,,calendar');
		}
		for ($i = 2; $i <= 9; ++$i)
		{
			$muliple_weeks[$i] = lang('%1 weeks',$i);
		}

		for ($i = 2; $i <= 20; $i++)
		{
			$consolidated[$i] = $i;
		}
		$intervals = array(
			5	=> '5',
			10	=> '10',
			15	=> '15',
			20	=> '20',
			30	=> '30',
			45	=> '45',
			60	=> '60'
		);
		$default_participants = array(
			0 => lang('Just me'),
			'selected' => lang('Selected users/groups')
		);
		$defaultresource_sel = array(
			'resources_conflict'    => lang('resources with conflict detection'),
			'resources_without_conflict'    => lang('resources except conflicting ones')
		);
		$reset_stati_on_shifts = array(
			'no'		=> lang('Never'),
			'all'		=> lang('Always'),
			'startday'	=> lang('If start day differs'),
		);
		$freebusy_values = array(
			0		=> lang('No'),
			1		=> lang('Yes'),
			2		=> lang('With credentials included'),
		);
		if (!$hook_data['setup'])	// does not work at setup time
		{
			$options = array('0' => lang('none'));
			foreach($GLOBALS['egw']->accounts->search(array('type' => 'owngroups','app' => 'calendar')) as $group)
			{
				$options[$group['account_id']] = Api\Accounts::username($group['account_id']);
			}
			$freebusy_url = calendar_bo::freebusy_url($GLOBALS['egw_info']['user']['account_lid'],$GLOBALS['egw_info']['user']['preferences']['calendar']['freebusy_pw']);
			$freebusy_url = '<a href="'.$freebusy_url.'" target="_blank">'.$freebusy_url.'</a>';
			$freebusy_help = lang('Should not loged in persons be able to see your freebusy information? You can set an extra password, different from your normal password, to protect this informations. The freebusy information is in iCal format and only include the times when you are busy. It does not include the event-name, description or locations. The URL to your freebusy information is');
			$freebusy_help .= ' ' . $freebusy_url;

			// Timezone for file exports
			$export_tzs = array(['value' => '0', 'label' => lang('Use Event TZ')]);
			$export_tzs += Api\DateTime::getTimezones();
		}
		$link_title_options = calendar_bo::get_link_options();
		$settings = array(
			'1.section' => array(
				'type'  => 'section',
				'title' => lang('General settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			/* disabled until we have a home app again
			'mainscreen_showevents' => array(
				'type'   => 'select',
				'label'  => 'Which view to show on home page',
				'name'   => 'mainscreen_showevents',
				'values' => $mainscreen,
				'help'   => 'Displays this calendar view on the home page (page you get when you enter EGroupware or click on the home page icon)?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '1',	// 1 = week
			),*/
			'multiple_weeks' => array(
				'type'   => 'select',
				'label'  => 'Weeks in multiple week view',
				'name'   => 'multiple_weeks',
				'values' => $muliple_weeks,
				'help'   => 'How many weeks should the multiple week view show?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 2,
			),
			'weekdaystarts' => array(
				'type'   => 'select',
				'label'  => 'weekday starts on',
				'name'   => 'weekdaystarts',
				'values' => $weekdaystarts,
				'help'   => 'This day is shown as first day in the week or month view.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'Monday',
			),
			'workdaystarts' => array(
				'type'   => 'select',
				'label'  => 'work day starts on',
				'name'   => 'workdaystarts',
				'values' => $times,
				'help'   => 'This defines the start of your dayview. Events before this time, are shown above the dayview.<br>This time is also used as a default starttime for new events.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 9,
			),
			'workdayends' => array(
				'type'   => 'select',
				'label'  => 'work day ends on',
				'name'   => 'workdayends',
				'values' => $times,
				'help'   => 'This defines the end of your dayview. Events after this time, are shown below the dayview.',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 18,
			),
			'interval' => array(
				'type'   => 'select',
				'label'  => 'Length of the time interval',
				'name'   => 'interval',
				'values' => $intervals,
				'help'   => 'How many minutes should each interval last?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 30,
			),
			'day_consolidate' => array(
				'type'  => 'select',
				'label' => 'Minimum number of users for showing day view as consolidated.',
				'name'  => 'day_consolidate',
				'values'=> $consolidated,
				'help'  => 'How many separate calendars to show before merging them together',
				'default'=> 6
			),
			'week_consolidate' => array(
				'type'  => 'select',
				'label' => 'Minimum number of users for showing week view as consolidated.',
				'name'  => 'week_consolidate',
				'values'=> $consolidated,
				'help'  => 'How many separate calendars to show before merging them together',
				'default'=> 4
			),
			'use_time_grid' => array(
				'type'   => 'multiselect',
				'label'  => 'Views showing a list of events',
				'name'   => 'use_time_grid',
				'values' => $list_views,
				'help'   => 'For which views should calendar just a list of events instead of distinct lines with a fixed time interval.',
				'xmlrpc' => True,
				'admin'  => False,
				'default' => ['weekN', 'month'],
			),
			'2.section' => array(
				'type'  => 'section',
				'title' => lang('appointment settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'defaultlength' => array(
				'type'    => 'input',
				'label'   => 'default appointment length (in minutes)',
				'name'    => 'defaultlength',
				'help'    => 'Default length of newly created events. The length is in minutes, eg. 60 for 1 hour.',
				'default' => '',
				'size'    => 3,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 60,
			),
			'default_participant' => array(
				'type'	=> 'select',
				'label'	=> 'New event participants',
				'name'	=> 'default_participant',
				'values'=>	$default_participants,
				'help'	=> 'Participants automatically added to new events',
				'default'	=> 'selected',
				'xmlrpc' => False,
				'admin'  => False
			),
			'default_category'       => array(
				'type'    => 'multiselect',
				'label'   => 'New event category',
				'name'    => 'default_category',
				'help'    => 'Category automatically added to new events',
				'values'  => $default_cat_seloptions,
				'default' => '',
				'xmlrpc'  => False,
				'admin'   => False
			),
			'default-alarm'          => array(
				'type'   => 'date-duration',//'select',
				'label'  => lang('Default alarm for regular events').' ('.lang('empty = no alarm').')',
				'name'   => 'default-alarm',
				'help'   => 'Alarm added automatic to new events before event start-time',
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '',
			),
			'default-alarm-wholeday' => array(
				'type'    => 'date-duration',//'select',
				'label'   => lang('Default alarm for whole-day events') . ' (' . lang('empty = no alarm') . ')',
				'name'    => 'default-alarm-wholeday',
				'help'    => lang('Alarm added automatic to new events before event start-time') . ' (' . lang('Midnight') . ')',
				'xmlrpc'  => True,
				'admin'   => False,
				'default' => '',
			),
			'default-alarm-for'      => array(
				'type'    => 'select',
				'label'   => lang('Default alarm for'),
				'name'    => 'default-alarm-for',
				'values'  => [lang("just me"), 'all' => lang('all participants')],
				'help'    => lang('Default alarm added for yourself or all participants'),
				'xmlrpc'  => True,
				'admin'   => False,
				'default' => '',
			),
		);
		if (isset($bo))	// add custom time-spans set by CalDAV clients, not in our prefs
		{
			$prefs = $GLOBALS['egw_info']['user']['preferences']['calendar'];
			$data = array(
				'prefs' => &$prefs,	// use reference to get preference value back
				'preprocess' => true,
				'type' => 'user',
			);
			self::verify_settings_reference($data);
		}
		$settings += array(
			'defaultresource_sel' => array(
				'type'		=> 'select',
				'label'		=> 'default type of resources selection',
				'name'		=> 'defaultresource_sel',
				'values'	=> $defaultresource_sel,
				'help'		=> 'Default type of resources application selected in the calendar participants research form.',
				'xmlrpc'	=> True,
				'admin'		=> False,
				'default'	=> 'resources'
			),
			'default_private' => array(
				'type'  => 'check',
				'label' => 'Set new events to private',
				'name'  => 'default_private',
				'help'  => 'Should new events created as private by default ?',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
			),
			'reset_stati'	=> array(
				'type'   => 'select',
				'label'  => 'Reset participant stati on event shifts',
				'name'   => 'reset_stati',
				'help'   => 'Select whether you want the participant stati reset to unknown, if an event is shifted later on.',
				'values' => $reset_stati_on_shifts,
				'default' => 'all',
				'xmlrpc' => True,
				'admin'  => False,
			),
			'no_category_custom_color' => array(
				'type' => 'color',
				'label' => 'Custom event color',
				'no_lang' => true,
				'name' => 'no_category_custom_color',
				'help' => lang('Custom color for events without category color'),
				'xmlrpc' => True,
				'admin'  => False,
			),
			'2.5.section' => array(
				'type'  => 'section',
				'title' => lang('Configuration settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'new_event_dialog' => array(
				'type'	=> 'select',
				'label'	=> 'Add appointments via shortened dialog or complete edit window',
				'name'	=> 'new_event_dialog',
				'values'=>	array('add' => lang('Quick add'), 'edit' => lang('Regular edit')),
				'help'	=> 'Use quick add or full edit dialog when creating a new event',
				'default'	=> 'add',
			),
			'limit_des_lines' => array(
				'type'   => 'input',
				'size'   => 5,
				'label'  => 'Limit number of description lines in list view (default 5, 0 for no limit)',
				'name'   => 'limit_des_lines',
				'help'   => 'How many description lines should be directly visible. Further lines are available via a scrollbar.',
				'xmlrpc' => True,
				'admin'  => False
			),
			'limit_all_day_lines' => array(
				'type'   => 'input',
				'size'   => 5,
				'label'  => 'Limit number of lines for all day events',
				'name'   => 'limit_all_day_lines',
				'help'   => 'How many lines of all day events should be directly visible. Further lines are available via a mouseover.',
				'xmlrpc' => True,
				'default'=> 3,
				'admin'  => False
			),
			'planner_show_empty_rows' => array(
				'type'   => 'select',
				'label'  => 'Show empty rows in Planner',
				'name'   => 'planner_show_empty_rows',
				'values' => array(
					0 => lang('no'),
					'user' => lang('Planner by user'),
					'cat'  => lang('Planner by category'),
					'both' => lang('All'),
				),
				'help'   => 'Should the planner display an empty row for users or categories without any appointment.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'user',
			),
			'birthdays_as_events' => array(
				'type'   => 'multiselect',
				'values' => $birthdays_as_events,
				'label'  => 'Show birthdays as events',
				'name'   => 'birthdays_as_events',
				'help'   => 'Show birthdays as all day non-blocking events as well as via mouseover of the date.',
				'default'=> 'none'
			),
			'link_title' => array(
				'type'   => 'multiselect',
				'label'  => 'Link title for events to show',
				'name'   => 'link_title',
				'values' => $link_title_options,
				'help'   => 'What should links to the calendar events display in other applications.',
				'xmlrpc' => True,
				'admin'  => false,
				'default'=> '',
			),

			'3.section' => array(
				'type'  => 'section',
				'title' => lang('notification settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
			'receive_updates' => array(
				'type'   => 'select',
				'label'  => 'Receive email updates',
				'name'   => 'receive_updates',
				'values' => $updates,
				'help'   => "Do you want to be notified about new or changed appointments? You be notified about changes you make yourself.<br>You can limit the notifications to certain changes only. Each item includes all the notification listed above it. All modifications include changes of title, description, participants, but no participant responses. If the owner of an event requested any notifcations, he will always get the participant responses like acceptions and rejections too.",
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'time_change',
			),
			'receive_own_updates' => array(
				'type'   => 'select',
				'label'  => 'Receive notifications about events you created/modified/deleted',
				'name'   => 'receive_own_updates',
				'values' => $yesno,
				'help'   => "Do you want to be notified about changes of appointments you modified?",
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'false',
			),
			'receive_not_participating' => array(
				'type'   => 'select',
				'label'  => 'Do you want responses from events you created, but are not participating in?',
				'name'   => 'receive_not_participating',
				'values' => $yesno,
				'help'   => 'Do you want to be notified about participant responses from events you created, but are not participating in?',
				'default'=> '1'
			),
			'notify_externals' => array(
				'type'   => 'select',
				'label'  => 'Notify non-EGroupware users about event updates',
				'name'   => 'notify_externals',
				'values' => $updates,
				'help'   => 'Do you want non-EGroupware participants of events you created to be automatically notified about new or changed appointments?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'no',
			),
			'update_format' => array(
				'type'   => 'select',
				'label'  => 'Format of event updates',
				'name'   => 'update_format',
				'values' => $update_formats,
				'help'   => 'Extended updates always include the complete event-details. iCal\'s can be imported by certain other calendar-applications.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'ical',
			),
			'notifyAdded' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for added events',
				'name'   => 'notifyAdded',
				'rows'   => 5,
				'help'   => 'This message is sent to every participant of events you own, who has requested notifcations about new events.<br>You can use certain variables which get substituted with the data of the event. The first line is the subject of the email.',
				'default' => '',
				'values' => $event_details,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notifyCanceled' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for canceled events',
				'name'   => 'notifyCanceled',
				'rows'   => 5,
				'help'   => 'This message is sent for canceled or deleted events.',
				'default' => '',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notifyModified' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for modified events',
				'name'   => 'notifyModified',
				'rows'   => 5,
				'help'   => 'This message is sent for modified or moved events.',
				'default' => '',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notifyDisinvited' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for uninvited participants',
				'name'   => 'notifyDisinvited',
				'rows'   => 5,
				'help'   => 'This message is sent to uninvited participants.',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notifyResponse' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for your responses',
				'name'   => 'notifyResponse',
				'rows'   => 5,
				'help'   => 'This message is sent when you accept, tentative accept or reject an event.',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notifyAlarm' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for your alarms',
				'name'   => 'notifyAlarm',
				'rows'   => 5,
				'help'   => 'This message is sent when you set an Alarm for a certain event. Include all information you might need.',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'4.section' => array(
				'type'  => 'section',
				'title' => lang('Data exchange settings'),
				'no_lang'=> true,
				'xmlrpc' => False,
				'admin'  => False
			),
		);
		// Merge print
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$merge = new calendar_merge();
			$settings += $merge->merge_preferences();
		}

		$settings += array(
			'export_timezone' => array(
				'type'   => 'select',
				'label'  => 'Timezone of event iCal file import/export',
				'name'   => 'export_timezone',
				'values' => $export_tzs,
				'help'   => 'Use this timezone to import/export calendar data.',
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '0', // Use event's TZ
			),
			'freebusy' => array(
				'type'  => 'select',
				'label' => 'Make freebusy information available to not logged in persons?',
				'name'  => 'freebusy',
				'help'  => $freebusy_help,
				'values'	=> $freebusy_values,
				'run_lang' => false,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 0,
			),
			'freebusy_pw' => array(
				'type'  => 'input',
				'label' => 'Password for not loged in users to your freebusy information?',
				'name'  => 'freebusy_pw',
				'help'  => 'If you dont set a password here, the information is available to everyone, who knows the URL!!!',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => ''
			),
		);

		return $settings;
	}

	/**
	 * Verify settings hook called to generate errors about settings used here to store default alarms in CalDAV prefs
	 *
	 * @param array $data
	 *  array $data['prefs']
	 *  string $data['type'] 'user', 'default' or 'forced'
	 *  boolean $data['preprocess'] true data just shown to user, false: data stored by user
	 */
	public static function verify_settings(array $data)
	{
		self::verify_settings_reference($data);
	}

	/**
	 * Verify settings hook called to generate errors about settings used here to store default alarms in CalDAV prefs
	 *
	 * @param array& $data
	 *  array $data['prefs']
	 *  string $data['type'] 'user', 'default' or 'forced'
	 *  boolean $data['preprocess'] true data just shown to user, false: data stored by user
	 */
	public static function verify_settings_reference(array &$data)
	{
		//error_log(__METHOD__."(".array2string($data).")");
		// caldav perfs are always user specific and cant by switched off
		if ($data['type'] != 'user') return;

		$account_lid = $GLOBALS['egw_info']['user']['account_lid'];
		foreach(array(
			'default-alarm' => 'default-alarm-vevent-datetime:/'.$account_lid.'/:urn:ietf:params:xml:ns:caldav',
			'default-alarm-wholeday' => 'default-alarm-vevent-date:/'.$account_lid.'/:urn:ietf:params:xml:ns:caldav',
		) as $name => $dav)
		{
			$pref =& $GLOBALS['egw_info']['user']['preferences']['groupdav'][$dav];
			if (true) $pref = str_replace("\r", '', $pref);	// remove CR messing up multiline preg_match
			$val =& $data['prefs'][$name];

			//error_log(__METHOD__."() groupdav[$dav]=$pref, calendar[$name]=$val");

			if ($data['preprocess'])	// showing preferences
			{
				if (!isset($val))	// no calendar pref --> read value from caldav
				{
					$matches = null;
					if (preg_match('/^ACTION:NONE$/mi', $pref))
					{
						$val = '';
					}
					elseif (preg_match('/^TRIGGER:-PT(\d+(M|H|D))$/mi', $pref, $matches))
					{
						static $factors = array(
							'M' => 1,
							'H' => 60,
							'D' => 1440,
						);
						$factor = $factors[strtoupper($matches[2])];
						$val = $factor*(int)$matches[1];
					}
					else
					{
						$val = '';
					}
					$GLOBALS['egw']->preferences->add('calendar', $name, $val, 'user');
					//error_log(__METHOD__."() setting $name={$val} from $dav='$pref'");
				}
			}
			else	// storing preferences
			{
				if (empty($pref) || !preg_match('/^TRIGGER:/m', $pref))
				{
					$pref = 'BEGIN:VALARM
TRIGGER:-PT1H
ATTACH;VALUE=URI:Basso
ACTION:AUDIO
END:VALARM';
				}
				$trigger = $val < 0 ? 'TRIGGER:PT' : 'TRIGGER:-PT';
				if ((string)$val === '')
				{
					$pref = preg_replace('/^ACTION:.*$/m', 'ACTION:NONE', $pref);
				}
				elseif (abs($val) < 60)
				{
					$pref = preg_replace('/^TRIGGER:.*$/m', $trigger.number_format(abs($val), 0).'M', $pref);
				}
				else
				{
					$pref = preg_replace('/^TRIGGER:.*$/m', $trigger.number_format(abs($val)/60, 0).'H', $pref);
				}
				$GLOBALS['egw']->preferences->add('groupdav', $dav, $pref, 'user');
				//error_log(__METHOD__."() storing $name=$val --> $dav='$pref'");
			}
		}
	}

	/**
	 * Sync default alarms from CalDAV to Calendar
	 *
	 * Gets called by Api\CalDAV::PROPPATCH() for 'default-alarm-vevent-date(time)' changes
	 */
	public static function sync_default_alarms()
	{
		self::verify_settings(array(
			'prefs' => array(),
			'preprocess' => true,
			'type' => 'user',
		));
	}

	public static function config_validate()
	{
		$GLOBALS['egw_info']['server']['found_validation_hook'] = array('calendar_purge_old');
	}

	/**
	 * ACL rights and labels used
	 *
	 * @param string|array string with location or array with parameters incl. "location", specially "owner" for selected Acl owner
	 * @return array Acl::(READ|ADD|EDIT|DELETE|PRIVAT|CUSTOM(1|2|3)) => $label pairs
	 */
	public static function acl_rights($params)
	{
		$rights = array(
			Acl::CUSTOM2 => 'freebusy',
			Acl::CUSTOM3 => 'invite',
			Acl::READ    => 'read',
			Acl::ADD     => 'add',
			Acl::EDIT    => 'edit',
			Acl::DELETE  => 'delete',
			Acl::PRIVAT  => 'private',
		);
		$require_acl_invite = $GLOBALS['egw_info']['server']['require_acl_invite'];

		if (!$require_acl_invite || $require_acl_invite == 'groups' && !($params['owner'] < 0))
		{
			unset($rights[Acl::CUSTOM3]);
		}
		return $rights;
	}

	/**
	 * Hook to tell framework we use standard categories method
	 *
	 * @param string|array $data hook-data or location
	 * @return boolean
	 */
	public static function categories($data)
	{
		unset($data);	// not used, but in function signature for hooks
		return true;
	}

	/**
	 * Mail integration hook to import mail message contents into a calendar entry
	 *
	 * @return string method to be executed for calendar mail integration
	 */
	public static function mail_import($args)
	{
		unset($args);	// not used, but required by function signature

		return array (
			'menuaction' => 'calendar.calendar_uiforms.mail_import',
			'popup' => Link::get_registry('calendar', 'edit_popup')
		);
	}

	/**
	 * Method to construct notifications actions
	 *
	 * @param type $params
	 * @return type
	 */
	public static function notifications_actions ($params)
	{
		Api\Translation::add_app('calendar');
		// do not set actions for alarm type
		if (isset($params['data']['type']) && $params['data']['type'] == 6)
		{
			if (!empty($params['data']['videoconference'])
				&& !self::isVideoconferenceDisabled())
			{
				return [
					array(
						'id' => 'J',
						'caption' => lang('Join'),
						'icon' => 'accept_call',
						'onExecute' => 'app.status.openCall("'.$params['data']['videoconference'].'");'
					)
				];
			}
			return array();
		}
		if (!isset($params['data']['event_id'])) $params['data']['event_id'] = '';
		if (!isset($params['data']['user_id'])) $params['data']['user_id'] = '';
		return array(
			array(
				'id' => 'A',
				'caption' => lang('Accept'),
				'icon' => 'accepted',
				'onExecute' => 'egw().json("calendar.calendar_uiforms.ajax_status",['.$params['data']['event_id'].','.$params['data']['user_id'].','.'"A"'.']).sendRequest(true);this.button_delete(arguments[0], arguments[1]);'
			),
			array(
				'id' => 'R',
				'caption' => lang('Reject'),
				'icon' => 'rejected',
				'onExecute' => 'egw().json("calendar.calendar_uiforms.ajax_status",['.$params['data']['event_id'].','.$params['data']['user_id'].','.'"R"'.']).sendRequest(true);this.button_delete(arguments[0], arguments[1]);'
			),
			array(
				'id' => 'T',
				'caption' => lang('Tentative'),
				'icon' => 'tentative',
				'onExecute' => 'egw().json("calendar.calendar_uiforms.ajax_status",['.$params['data']['event_id'].','.$params['data']['user_id'].','.'"T"'.']).sendRequest(true);this.button_delete(arguments[0], arguments[1]);'
			)
		);
	}

	/**
	 * Wrapper function to check Status app videoconference status
	 * it makes sure first the Status app is there before calling its method.
	 *
	 * @return false|mixed
	 */
	public static function isVideoconferenceDisabled()
	{
		if ($GLOBALS['egw_info']['user']['apps']['status'] && class_exists(\EGroupware\Status\Hooks::class)
			&& method_exists(\EGroupware\Status\Hooks::class, 'isVideoconferenceDisabled'))
		{
			return EGroupware\Status\Hooks::isVideoconferenceDisabled();
		}
		return true;
	}

	/**
	 * Wrapper function to check Status app videoConference recording status
	 * it makes sure first the Status app is there before calling its method.
	 *
	 * @return bool
	 */
	public static function isVCRecordingSupported()
	{
		if ($GLOBALS['egw_info']['user']['apps']['status'] && class_exists(\EGroupware\Status\Hooks::class)
			&& method_exists(\EGroupware\Status\Hooks::class, 'isVCRecordingSupported'))
		{
			return EGroupware\Status\Hooks::isVCRecordingSupported();
		}
		return false;
	}

}

// Not part of the class, since config hooks are still using the old style
function calendar_purge_old($config)
{
	$id = 'calendar_purge';

	// Cancel old purge
	$async = new Api\Asyncservice();
	$async->cancel_timer($id);

	if((float)$config > 0)
	{
		$result = $async->set_timer(
			array('month' => '*', 'day' => 1),
			$id,
			'calendar.calendar_boupdate.purge',
			(float)$config
		);
		if(!$result)
		{
			$GLOBALS['config_error'] = 'Unable to schedule purge';
		}
	}
}
