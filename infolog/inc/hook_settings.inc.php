<?php
	/**************************************************************************\
	* eGroupWare - InfoLog Preferences                                         *
	* http://www.eGroupWare.org                                                *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	/* Setup some values to fill the array of this app's settings below */
	$ui =& CreateObject('infolog.uiinfolog');	// need some labels from
	$filters = $show_home = array();
	$show_home[] = lang("DON'T show InfoLog");
	foreach($ui->filters as $key => $label)
	{
		$show_home[$key] = $filters[$key] = lang($label);
	}
	$have_custom_fields = count($ui->bo->customfields) > 0;
	unset($ui);

	// migrage old filter-pref 1,2 to the filter one 'own-open-today'
	if (in_array($GLOBALS['egw']->preferences->{$GLOBALS['type']}['homeShowEvents'],array('1','2')))
	{
		$GLOBALS['egw']->preferences->add('infolog','homeShowEvents','own-open-today',$GLOBALS['type']);
		$GLOBALS['egw']->preferences->save_repository();
	}
	$show_links = array(
		'all'    => lang('all links and attachments'),
		'links'  => lang('only the links'),
		'attach' => lang('only the attachments'),
		'none'   => lang('no links or attachments'),
 		'no_describtion' => lang('no describtion, links or attachments'),
	);
	$show_details = array(
		0 => lang('No'),
		1 => lang('Yes'),
		2 => lang('Only for details'),
	);
	/* Settings array for this app */
	$GLOBALS['settings'] = array(
		'defaultFilter' => array(
			'type'   => 'select',
			'label'  => 'Default Filter for InfoLog',
			'name'   => 'defaultFilter',
			'values' => $filters,
			'help'   => 'This is the filter InfoLog uses when you enter the application. Filters limit the entries to show in the actual view. There are filters to show only finished, still open or futures entries of yourself or all users.',
			'xmlrpc' => True,
			'admin'  => False
		),
		'homeShowEvents' => array(
			'type'   => 'select',
			'label'  => 'InfoLog filter for the main screen',
			'name'   => 'homeShowEvents',
			'values' => $show_home,
			'help'   => 'Should InfoLog show up on the main screen and with which filter. Works only if you dont selected an application for the main screen (in your preferences).',
			'xmlrpc' => True,
			'admin'  => False
		),
		'listNoSubs' => array(
			'type'   => 'check',
			'label'  => 'List no Subs/Childs',
			'name'   => 'listNoSubs',
			'help'   => 'Should InfoLog show Subtasks, -calls or -notes in the normal view or not. You can always view the Subs via there parent.',
			'xmlrpc' => True,
			'admin'  => False
		),
		'show_links' => array(
			'type'   => 'select',
			'label'  => 'Show in the InfoLog list',
			'name'   => 'show_links',
			'values' => $show_links,
			'help'   => 'Should InfoLog show the links to other applications and/or the file-attachments in the InfoLog list (normal view when you enter InfoLog).',
			'xmlrpc' => True,
			'admin'  => False
		),
		'never_hide' => array(
			'type'   => 'check',
			'label'  => 'Never hide search and filters',
			'name'   => 'never_hide',
			'help'   => 'If not set, the line with search and filters is hidden for less entries then "max matches per page" (as defined in your common preferences).',
			'xmlrpc' => True,
			'admin'  => False
		),
		'show_percent' => array(
			'type'   => 'select',
			'label'  => 'Show status and percent done separate',
			'name'   => 'show_percent',
			'values' => $show_details,
			'help'   => 'Should the Infolog list show the percent done only for status ongoing or two separate icons.',
			'xmlrpc' => True,
			'admin'  => False
		),
		'show_id' => array(
			'type'   => 'select',
			'label'  => 'Show ticket Id',
			'name'   => 'show_id',
			'values' => $show_details,
			'help'   => 'Should the Infolog list show a unique numerical Id, which can be used eg. as ticket Id.',
			'xmlrpc' => True,
			'admin'  => False
		),
		'set_start' => array(
			'type'   => 'select',
			'label'  => 'Startdate for new entries',
			'name'   => 'set_start',
			'values' => array(
				'date'     => lang('todays date'),
				'datetime' => lang('actual date and time'),
				'empty'    => lang('leave it empty'),
			),
			'help'   => 'To what should the startdate of new entries be set.',
			'xmlrpc' => True,
			'admin'  => False
		),
	);
	if ($have_custom_fields)
	{
		$GLOBALS['settings']['cal_show_custom'] = array(
			'type'   => 'check',
			'label'  => 'Should the calendar show custom types too',
			'name'   => 'cal_show_custom',
			'help'   => 'Do you want to see custom InfoLog types in the calendar?',
			'xmlrpc' => True,
			'admin'  => False
		);
	}
	unset($show_home);
	unset($show_details);
	unset($filters);
	unset($show_links);
