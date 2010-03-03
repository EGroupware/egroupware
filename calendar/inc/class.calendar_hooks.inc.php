<?php
/**
 * eGroupWare - Calendar hooks
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-9 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * diverse static calendar hooks
 */
class calendar_hooks
{
	/**
	 * Hook called by link-class to include calendar in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		return array(
			'query' => 'calendar.calendar_bo.link_query',
			'title' => 'calendar.calendar_bo.link_title',
			'view'  => array(
				'menuaction' => 'calendar.calendar_uiforms.edit',
			),
			'view_id'    => 'cal_id',
			'view_popup' => '750x400',
			'add'        => array(
				'menuaction' => 'calendar.calendar_uiforms.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '750x400',
			'file_access' => 'calendar.calendar_bo.file_access',
		);
	}

	/**
	 * Draw calendar part of home
	 */
	static function home()
	{
		if($GLOBALS['egw_info']['user']['preferences']['calendar']['mainscreen_showevents'])
		{
			$GLOBALS['egw']->translation->add_app('calendar');

			$save_app_header = $GLOBALS['egw_info']['flags']['app_header'];

			if ($GLOBALS['egw_info']['user']['preferences']['calendar']['defaultcalendar'] == 'listview')
			{
				if (!file_exists(EGW_SERVER_ROOT.($et_css_file ='/etemplate/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
				{
					$et_css_file = '/etemplate/templates/default/app.css';
				}
				$content =& ExecMethod('calendar.calendar_uilist.home');
			}
			else
			{
				unset($et_css_file);
				$content =& ExecMethod('calendar.calendar_uiviews.home');
			}
			$portalbox =& CreateObject('phpgwapi.listbox',array(
				'title'	=> $GLOBALS['egw_info']['flags']['app_header'],
				'primary'	=> $GLOBALS['egw_info']['theme']['navbar_bg'],
				'secondary'	=> $GLOBALS['egw_info']['theme']['navbar_bg'],
				'tertiary'	=> $GLOBALS['egw_info']['theme']['navbar_bg'],
				'width'	=> '100%',
				'outerborderwidth'	=> '0',
				'header_background_image'	=> $GLOBALS['egw']->common->image('phpgwapi/templates/default','bg_filler')
			));
			$GLOBALS['egw_info']['flags']['app_header'] = $save_app_header;
			unset($save_app_header);

			$GLOBALS['portal_order'][] = $app_id = $GLOBALS['egw']->applications->name2id('calendar');
			foreach(array('up','down','close','question','edit') as $key)
			{
				$portalbox->set_controls($key,Array('url' => '/set_box.php', 'app' => $app_id));
			}
			$portalbox->data = Array();

			if (!file_exists(EGW_SERVER_ROOT.($css_file ='/calendar/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
			{
				$css_file = '/calendar/templates/default/app.css';
			}
			echo '
<!-- BEGIN Calendar info -->
<style type="text/css">
<!--';
			if ($et_css_file)	// listview
			{
				echo '
@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$et_css_file.');';
			}
			echo '
@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$css_file.');
-->
</style>
'.$portalbox->draw($content)."\n".'<!-- END Calendar info -->'."\n";
		}
	}

	/**
	 * Entries for calendar's admin menu
	 */
	static function admin()
	{
		$file = Array(
			'Site Configuration' => egw::link('/index.php','menuaction=admin.uiconfig.index&appname=calendar'),
			'Custom fields' => egw::link('/index.php','menuaction=admin.customfields.edit&appname=calendar'),
			'Calendar Holiday Management' => egw::link('/index.php','menuaction=calendar.uiholiday.admin'),
			'Global Categories' => egw::link('/index.php','menuaction=admin.uicategories.index&appname=calendar'),
			'Category ACL' => egw::link('/index.php','menuaction=calendar.calendar_uiforms.cat_acl'),
			'Update timezones' => egw::link('/index.php','menuaction=calendar.calendar_timezones.update'),
		);
		display_section('calendar','calendar',$file);
	}

	/**
	 * Entries for calendar's preferences menu
	 */
	static function preferences()
	{
		$file = array(
			'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname=calendar'),
			'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=calendar'),
			'Edit Categories' => egw::link('/index.php','menuaction=preferences.uicategories.index&cats_app=calendar&cats_level=True&global_cats=True'),
			'Import CSV-File' => egw::link('/calendar/csv_import.php'),
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
			ExecMethod('calendar.calendar_bo.check_set_default_prefs');
		}
		$default = array(
			'day'          => lang('Dayview'),
			'day4'         => lang('Four days view'),
			'week'         => lang('Weekview'),
			'weekN'        => lang('Multiple week view'),
			'month'        => lang('Monthview'),
			'planner_cat'  => lang('Planner by category'),
			'planner_user' => lang('Planner by user'),
			'listview'     => lang('Listview'),
		);
		$grid_views = array(
			'all' => lang('all'),
			'day_week' => lang('Dayview').', '.lang('Four days view').' &amp; '.lang('Weekview'),
			'day4' => lang('Dayview').' &amp; '.lang('Four days view'),
			'day' => lang('Dayview'),
		);
		/* Select list with number of day by week */
		$week_view = array(
			'5' => lang('Weekview without weekend'),
			'7' => lang('Weekview with weekend'),
		);
		$mainpage = $yesno = array(
			'1' => lang('Yes'),
			'0' => lang('No'),
		);
		$updates = array(
			'no'             => lang('Never'),
			'add_cancel'     => lang('on invitation / cancelation only'),
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
			'to-fullname' => lang('Fullname of person to notify'),
			'to-firstname'=> lang('Firstname of person to notify'),
			'to-lastname' => lang('Lastname of person to notify'),
			'title'       => lang('Title of the event'),
			'description' => lang('Description'),
			'startdate'   => lang('Start Date/Time'),
			'enddate'     => lang('End Date/Time'),
			'olddate'     => lang('Old Startdate'),
			'category'    => lang('Category'),
			'location'    => lang('Location'),
			'priority'    => lang('Priority'),
			'participants'=> lang('Participants'),
			'owner'       => lang('Owner'),
			'repetition'  => lang('Repetitiondetails (or empty)'),
			'action'      => lang('Action that caused the notify: Added, Canceled, Accepted, Rejected, ...'),
			'link'        => lang('Link to view the event'),
			'disinvited'  => lang('Participants disinvited from an event'),
		);
		$weekdaystarts = array(
			'Monday'   => lang('Monday'),
			'Sunday'   => lang('Sunday'),
			'Saturday' => lang('Saturday')
		);

		for ($i=0; $i < 24; ++$i)
		{
			$times[$i] = $GLOBALS['egw']->common->formattime($i,'00');
		}

		for ($i = 2; $i <= 9; ++$i)
		{
			$muliple_weeks[$i.' weeks'] = lang('%1 weeks',$i);
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
		$defaultresource_sel = array(
			'resources_conflict'    => lang('resources with conflict detection'),
			'resources_without_conflict'    => lang('resources except conflicting ones'),
			'resources'     => lang('resources'),
			'addressbook'   => lang('addressbook')
		);
		if (!$hook_data['setup'])	// does not working on setup time
		{
			$groups = $GLOBALS['egw']->accounts->membership($GLOBALS['egw_info']['user']['account_id']);
			$options = array('0' => lang('none'));
			if (is_array($groups))
			{
				foreach($groups as $group)
				{
					$options[$group['account_id']] = $GLOBALS['egw']->common->grab_owner_name($group['account_id']);
				}
			}
			$freebusy_url = calendar_bo::freebusy_url($GLOBALS['egw_info']['user']['account_lid'],$GLOBALS['egw_info']['user']['preferences']['calendar']['freebusy_pw']);
			$freebusy_help = lang('Should not loged in persons be able to see your freebusy information? You can set an extra password, different from your normal password, to protect this informations. The freebusy information is in iCal format and only include the times when you are busy. It does not include the event-name, description or locations. The URL to your freebusy information is %1.','<a href="'.$freebusy_url.'" target="_blank">'.$freebusy_url.'</a>');
		}

		return array(
			'defaultcalendar' => array(
				'type'   => 'select',
				'label'  => 'default calendar view',
				'name'   => 'defaultcalendar',
				'values' => $default,
				'help'   => 'Which of calendar view do you want to see, when you start calendar ?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 'week',
			),
			'days_in_weekview' => array(
				'type'   => 'select',
				'label'  => 'default week view',
				'name'   => 'days_in_weekview',
				'values' => $week_view,
				'help'   => 'Do you want a weekview with or without weekend?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '7',
			),
			'multiple_weeks' => array(
				'type'   => 'select',
				'label'  => 'Weeks in multiple week view',
				'name'   => 'multiple_weeks',
				'values' => $muliple_weeks,
				'help'   => 'How many weeks should the multiple week view show?',
				'xmlrpc' => True,
				'admin'  => False,
				'forced'=> 3,
			),
			'mainscreen_showevents' => array(
				'type'   => 'select',
				'label'  => 'show default view on main screen',
				'name'   => 'mainscreen_showevents',
				'values' => $mainpage,
				'help'   => 'Displays your default calendar view on the startpage (page you get when you enter eGroupWare or click on the homepage icon)?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '1',
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
			'use_time_grid' => array(
				'type'   => 'select',
				'label'  => 'Views with fixed time intervals',
				'name'   => 'use_time_grid',
				'values' => $grid_views,
				'help'   => 'For which views should calendar show distinct lines with a fixed time interval.',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'all',
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
			'defaultresource_sel' => array(
				'type'		=> 'select',
				'label'		=> 'default type of resources selection',
				'name'		=> 'defaultresource_sel',
				'values'	=> $defaultresource_sel,
				'help'		=> 'Default type of resources application selected in the calendar particpants research form.',
				'xmlrpc'	=> True,
				'admin'		=> False,
				'forced'    => 'addressbook',
			),
			'planner_start_with_group' => array(
				'type'   => 'select',
				'label'  => 'Preselected group for entering the planner',
				'name'   => 'planner_start_with_group',
				'values' => $options,
				'help'   => 'This group that is preselected when you enter the planner. You can change it in the planner anytime you want.',
				'xmlrpc' => True,
				'admin'  => False,
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
			'default_private' => array(
				'type'  => 'check',
				'label' => 'Set new events to private',
				'name'  => 'default_private',
				'help'  => 'Should new events created as private by default ?',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => '0',
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
				'cols'   => 50,
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
				'cols'   => 50,
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
				'cols'   => 50,
				'help'   => 'This message is sent for modified or moved events.',
				'default' => '',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'notifyDisinvited' => array(
				'type'   => 'notify',
				'label'  => 'Notification messages for disinvited participants',
				'name'   => 'notifyDisinvited',
				'rows'   => 5,
				'cols'   => 50,
				'help'   => 'This message is sent to disinvited participants.',
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
				'cols'   => 50,
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
				'cols'   => 50,
				'help'   => 'This message is sent when you set an Alarm for a certain event. Include all information you might need.',
				'values' => $event_details,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
			),
			'freebusy' => array(
				'type'  => 'check',
				'label' => 'Make freebusy information available to not loged in persons?',
				'name'  => 'freebusy',
				'help'  => $freebusy_help,
				'run_lang' => false,
				'subst_help' => False,
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => false,
			),
			'freebusy_pw' => array(
				'type'  => 'input',
				'label' => 'Password for not loged in users to your freebusy information?',
				'name'  => 'freebusy_pw',
				'help'  => 'If you dont set a password here, the information is available to everyone, who knows the URL!!!',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => 'no'
			)
		);
	}
}
