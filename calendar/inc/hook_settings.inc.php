<?php
	/**************************************************************************\
	* phpGroupWare - Calendar Preferences                                      *
	* http://www.phpgroupware.org                                              *
	* Based on Webcalendar by Craig Knudsen <cknudsen@radix.net>               *
	*          http://www.radix.net/~cknudsen                                  *
	* Modified by Mark Peters <skeeter@phpgroupware.org>                       *
	* Modified by Ralf Becker <ralfbecker@outdoor-training.de>                 *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	create_check_box('show default view on main screen','mainscreen_showevents');
	
	create_select_box('weekday starts on','weekdaystarts',array(
		'Monday'   => lang('Monday'),
		'Sunday'   => lang('Sunday'),
		'Saturday' => lang('Saturday')
	));
	
	for ($i=0; $i < 24; ++$i)
	{
		$options[$i] = $GLOBALS['phpgw']->common->formattime($i,'00');
	}
	create_select_box('work day starts on','workdaystarts',$options);
	create_select_box('work day ends on','workdayends',$options);
	unset($options);
	
	create_select_box('default calendar view','defaultcalendar',array(
		'planner_cat'  => lang('Planner by category'),
		'planner_user' => lang('Planner by user'),
		'year'         => lang('Yearly'),
		'month'        => lang('Monthly'),
		'week'         => lang('Weekly'),
		'day'          => lang('Daily')
	));
	$groups = $GLOBALS['phpgw']->accounts->membership($GLOBALS['phpgw_info']['user']['account_id']);
	$options = array(-1 => lang('none'));
	foreach($groups as $group)
	{
		$options[$group['account_id']] = $GLOBALS['phpgw']->common->grab_owner_name($group['account_id']);
	}
	create_select_box('Preselected group for entering the planner','planner_start_with_group',$options);
	unset($groups); 
	unset($options); 
	unset($group);
	
	create_select_box('Default calendar filter','defaultfilter',array(
		'all'     => lang('all'),
		'private' => lang('private only'),
//		'public'  => lang('global public only'),
//		'group'   => lang('group public only'),
//		'private+public' => lang('private and global public'),
//		'private+group'  => lang('private and group public'),
//		'public+group'   => lang('global public and group public')
	));

	create_select_box('Display interval in Day View','interval',array(
		5	=> '5',
		10	=> '10',
		15	=> '15',
		20	=> '20',
		30	=> '30',
		45	=> '45',
		60	=> '60'
	));
	
	create_select_box('Number of Intervals per Day in Planner View','planner_intervals_per_day',array(
		1	=> '1',
		2	=> '2',
		3	=> '3',
		4	=> '4',
	));

	create_check_box('Send/receive updates via email','send_updates');
	create_check_box('Receive extra information in event mails','send_extra');

	create_check_box('Display status of events','display_status');

	create_check_box('When creating new events default set to private','default_private');

	create_check_box('Display mini calendars when printing','display_minicals');

	create_check_box('Print calendars in black & white','print_black_white');
