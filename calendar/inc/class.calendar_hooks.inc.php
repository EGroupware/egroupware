<?php
/**
 * eGroupWare - Calendar hooks
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-11 by RalfBecker-At-outdoor-training.de
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
			'view_list'  => 'calendar.calendar_uilist.listview',
			'add'        => array(
				'menuaction' => 'calendar.calendar_uiforms.edit',
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '750x400',
			'file_access' => 'calendar.calendar_bo.file_access',
			'file_access_user' => true,	// file_access supports 4th parameter $user
		);
	}

	/**
	 * Hook called to retrieve a app specific exportLimit
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return the export_limit to be applied for the app, may be empty, int or string
	 */
	static function getAppExportLimit($location)
	{
		return bo_merge::getExportLimit($GLOBALS['egw_info']['server']['calendar_export_limit']);
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

			if ($GLOBALS['egw_info']['user']['preferences']['calendar']['mainscreen_showevents'] == 'listview')
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
			'Global Categories' => egw::link('/index.php','menuaction=admin.admin_categories.index&appname=calendar'),
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
			'Edit Categories' => egw::link('/index.php','menuaction=preferences.preferences_categories_ui.index&cats_app=calendar&cats_level=True&global_cats=True'),
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
		$mainscreen = array(
			'0'            => lang('None'),
			'day'          => lang('Dayview'),
			'day4'         => lang('Four days view'),
			'1'            => lang('Weekview'),
			'weekN'        => lang('Multiple week view'),
			'month'        => lang('Monthview'),
			'year'         => lang('yearview'),
			'planner_cat'  => lang('Planner by category'),
			'planner_user' => lang('Planner by user'),
			'listview'     => lang('Listview'),
		);
		$yesno = array(
			'1' => lang('Yes'),
			'0' => lang('No'),
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
			'addressbook'   => lang('addressbook'),
			'home-accounts' => lang('Accounts'),
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
				$options[$group['account_id']] = common::grab_owner_name($group['account_id']);
			}
			$freebusy_url = calendar_bo::freebusy_url($GLOBALS['egw_info']['user']['account_lid'],$GLOBALS['egw_info']['user']['preferences']['calendar']['freebusy_pw']);
			$freebusy_url = '<a href="'.$freebusy_url.'" target="_blank">'.$freebusy_url.'</a>';
			$freebusy_help = lang('Should not loged in persons be able to see your freebusy information? You can set an extra password, different from your normal password, to protect this informations. The freebusy information is in iCal format and only include the times when you are busy. It does not include the event-name, description or locations. The URL to your freebusy information is');
			$freebusy_help .= ' ' . $freebusy_url;

			// Timezone for file exports
			$export_tzs = array('0' => 'Use Event TZ');
			$export_tzs += egw_time::getTimezones();
		}

		$settings = array(
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
				'label'  => 'Which view to show on home page',
				'name'   => 'mainscreen_showevents',
				'values' => $mainscreen,
				'help'   => 'Displays this calendar view on the home page (page you get when you enter EGroupware or click on the home page icon)?',
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> '1',	// 1 = week
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
			'limit_des_lines' => array(
				'type'   => 'input',
				'size'   => 5,
				'label'  => 'Limit number of description lines in list view (default 5, 0 for no limit)',
				'name'   => 'limit_des_lines',
				'help'   => 'How many describtion lines should be directly visible. Further lines are available via a scrollbar.',
				'xmlrpc' => True,
				'admin'  => False
			),
			'display_color' => array(
				'type'   => 'input',
				'size'   => 9,
				'label'  => 'Select a color for this calendar',
				'name'   => 'display_color',
				'help'   => 'The Apple iCal Apps use this color to display events from this calendar.',
				'forced' => '#0040A0FF',
				'xmlrpc' => True,
				'admin'  => False
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
			'reset_stati'	=> array(
				'type'   => 'select',
				'label'  => 'Reset participant stati on event shifts',
				'name'   => 'reset_stati',
				'help'   => 'Select whether you want the pariticpant stati reset to unkown, if an event is shifted later on.',
				'values' => $reset_stati_on_shifts,
				'default' => 'no',
				'xmlrpc' => True,
				'admin'  => False,
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
				'type'  => 'select',
				'label' => 'Make freebusy information available to not loged in persons?',
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
			'display_holidays_event' => array(
                'type'   => 'select',
                'label'  => 'Display holidays or birthdays as events in dayview',
                'name'   => 'display_holidays_event',
                'values' => array(
					'0'	 => lang('Display in header'), //Please note that these values are a binary mask
					'1' => lang('Birthdays only'),
					'2' => lang('Holidays only'),
					'3' => lang('Both, holidays and birthdays')
				),
                'help'   => "When selected, birthdays and/or holidays will be displayed as events in your calendar. Please note that this option only changes the appereance inside of EGroupware, but does not change the information being sent via iCal or other calendar interfaces.",
                'xmlrpc' => True,
                'admin'  => False,
                'default'=> '0',
			),
		);

		// Merge print
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$link = egw::link('/index.php','menuaction=calendar.calendar_merge.show_replacements');

			$settings['default_document'] = array(
				'type'   => 'input',
				'size'   => 60,
				'label'  => 'Default document to insert entries',
				'name'   => 'default_document',
				'help'   => lang('If you specify a document (full vfs path) here, %1 displays an extra document icon for each entry. That icon allows to download the specified document with the data inserted.',lang('calendar')).' '.
					lang('The document can contain placeholder like {{%3}}, to be replaced with the data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>','calendar_title').' '.
					lang('The following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
			$settings['document_dir'] = array(
				'type'   => 'input',
				'size'   => 60,
				'label'  => 'Directory with documents to insert entries',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, %1 displays an action for each document. That action allows to download the specified document with the data inserted.',lang('calendar')).' '.
					lang('The document can contain placeholder like {{%3}}, to be replaced with the data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>','calendar_title').' '.
					lang('The following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default' => '/templates/calendar',
			);
		}
		// Import / Export for nextmatch
		if ($GLOBALS['egw_info']['user']['apps']['importexport'])
		{
			$definitions = new importexport_definitions_bo(array(
				'type' => 'export',
				'application' => 'calendar'
			));
			$options = array();
			foreach ((array)$definitions->get_definitions() as $identifier)
			{
				try
				{
					$definition = new importexport_definition($identifier);
				}
				catch (Exception $e)
				{
					// permission error
					continue;
				}
				if ($title = $definition->get_title())
				{
					$options[$title] = $title;
				}
				unset($definition);
			}
			$default_def = 'export-calendar-csv';
			$settings['nextmatch-export-definition'] = array(
				'type'   => 'select',
				'values' => $options,
				'label'  => 'Export definition to use for nextmatch export',
				'name'   => 'nextmatch-export-definition',
				'help'   => lang('If you specify an export definition, it will be used when you export'),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> isset($options[$default_def]) ? $default_def : false,
			);
		}
		return $settings;
	}

	public static function config_validate() {
		$GLOBALS['egw_info']['server']['found_validation_hook'] = True;
	}
}

// Not part of the class, since config hooks are still using the old style
function calendar_purge_old($config)
{
	$id = 'calendar_purge';

	// Cancel old purge
	ExecMethod('phpgwapi.asyncservice.cancel_timer', $id);

	if((float)$config > 0)
	{
		$result = ExecMethod2('phpgwapi.asyncservice.set_timer',
			array('month' => '*', 'day' => 1),
			$id,
			'calendar.calendar_boupdate.purge',
			(float)$config
		);
	}
	if(!$result)
	{
		$GLOBALS['config_error'] = 'Unable to schedule purge';
	}
}
