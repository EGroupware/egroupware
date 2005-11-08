<?php
/**************************************************************************\
* eGroupWare - Calendar - shared base-class of all calendar UI classes     *
* http://www.egroupware.org                                                *
* Written and (c) 2004/5 by Ralf Becker <RalfBecker@outdoor-training.de>   *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * Shared base-class of all calendar UserInterface classes
 *
 * It manages eg. the state of the controls in the UI and generated the calendar navigation (sidebox-menu)
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the bocal class !!!
 *
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004/5 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uical
{
	/**
	 * @var $debug mixed integer level or string function-name
	 */
	var $debug=false;
	/**
	 * @var $bo class bocal
	 */
	var $bo,$jscal,$html,$datetime,$cats,$accountsel;
	/**
	 * @var array $common_prefs reference to $GLOBALS['egw_info']['user']['preferences']['common']
	 */
	var $common_prefs;
	/**
	 * @var array $cal_prefs reference to $GLOBALS['egw_info']['user']['preferences']['calendar']
	 */
	var $cal_prefs;
	/**
	 * @var int $wd_start user pref. workday start
	 */
	var $wd_start;
	/**
	 * @var int $wd_start user pref. workday end
	 */
	var $wd_end;
	/**
	 * @var int $interval_m user pref. interval
	 */
	var $interval_m;
	/**
	 * @var int $user account_id of loged in user
	 */
	var $user;
	/**
	 * @var string $date session-state: date (Ymd) of shown view
	 */
	var $date;
	/**
	 * @var int $cat_it session-state: selected category
	 */
	var $cat_id;
	/**
	 * @var int $filter session-state: selected filter
	 */
	var $filter;
	/**
	 * @var int/array $owner session-state: selected owner(s) of shown calendar(s)
	 */
	var $owner;
	/**
	 * @var boolean $multiple session-state: true multiple owners selected, false single user/group
	 */
	var $multiple;
	/**
	 * @var int $num_month session-state: number of month shown
	 */
	var $num_month;
	/**
	 * @var string $sortby session-state: filter of planner: 'category' or 'user'
	 */
	var $sortby;
	/**
	 * @var string $view session-state: selected view
	 */
	var $view;
	/**
	 * @var string $view menuaction of the selected view
	 */
	var $view_menuaction;
	
	/**
	 * @var int $first first day of the shown view
	 */
	var $first;
	/**
	 * @var int $last last day of the shown view
	 */
	var $last;

	/**
	 * Constructor
	 *
	 * @param boolean $use_bocalupdate use bocalupdate as parenent instead of bocal
	 * @param array $set_states=null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function uical($use_bocalupdate=false,$set_states=NULL)
	{
		foreach(array(
			'bo'    => $use_bocalupdate ? 'calendar.bocalupdate' : 'calendar.bocal',
			'jscal' => 'phpgwapi.jscalendar',	// for the sidebox-menu
			'html'  => 'phpgwapi.html',
			'datetime' => 'phpgwapi.datetime',
			'accountsel' => 'phpgwapi.uiaccountsel',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['egw']->$class))
			{
				$GLOBALS['egw']->$class =& CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['egw']->$class;
		}
		if (!is_object($this->cats))
		{
			$this->cats =& CreateObject('phpgwapi.categories','','calendar');	// we need an own instance to get the calendar cats
		}
		$this->common_prefs	= &$GLOBALS['egw_info']['user']['preferences']['common'];
		$this->cal_prefs	= &$GLOBALS['egw_info']['user']['preferences']['calendar'];
		$this->wd_start		= 60*$this->cal_prefs['workdaystarts'];
		$this->wd_end		= 60*$this->cal_prefs['workdayends'];
		$this->interval_m	= $this->cal_prefs['interval'];

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->manage_states($set_states);

		$GLOBALS['uical'] = &$this;	// make us available for ExecMethod, else it creates a new instance
		
		// calendar does not work with hidden sidebox atm.
		unset($GLOBALS['egw_info']['user']['preferences']['common']['auto_hide_sidebox']);
	}

	/**
	 * Manages the states of certain controls in the UI: date shown, category selected, ...
	 *
	 * The state of all these controls is updated if they are set in $_REQUEST or $set_states and saved in the session.
	 * The following states are used:
	 *	- date or year, month, day: the actual date of the period displayed
	 *	- cat_id: the selected category
	 *	- owner: the owner of the displayed calendar
	 *	- save_owner: the overriden owner of the planner
	 *	- filter: the used filter: no filter / all or only privat
	 *	- num_month: number of month shown in the planner
	 *	- sortby: category or user of planner
	 *	- view: the actual view, where dialogs should return to or which they refresh
	 * @param set_states array to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function manage_states($set_states=NULL)
	{
		$states = $states_session = $GLOBALS['egw']->session->appsession('session_data','calendar');

		if (is_null($set_states))
		{
			$set_states = $_REQUEST;
		}

		if (!$states['date'] && $states['year'] && $states['month'] && $states['day'])
		{
			$states['date'] = $this->bo->date2string($states);
		}

		foreach(array(
			'date'       => $this->bo->date2string($this->bo->now_su),
			'cat_id'     => 0,
			'filter'     => 'all',
			'owner'      => $this->user,
			'num_month'  => 1,
			'save_owner' => 0,
			'sortby'     => 'category',
			'planner_days'=> 0,	// full month
			'multiple'   => 0,
			'view'       => $this->bo->cal_prefs['defaultcalendar'],
		) as $state => $default)
		{
			if (isset($set_states[$state]))
			{
				$states[$state] = $set_states[$state];
			}
			elseif (!is_array($states) || !isset($states[$state]))
			{
				$states[$state] = $default;
			}
			if ($state == 'date')
			{
				$date_arr = $this->bo->date2array($states['date']);
				foreach(array('year','month','day') as $name)
				{
					$this->$name = $states[$name] = $date_arr[$name];
				}
			}
			$this->$state = $states[$state];
		}
		if (substr($this->view,0,8) == 'planner_')
		{
			$states['sortby'] = $this->sortby = $this->view == 'planner_cat' ? 'category' : 'user';
			$states['view'] = $this->view = 'planner';
		}
		// set the actual view as return_to
		list($app,$class,$func) = explode('.',$_GET['menuaction']);
		if (($class == 'uiviews' || $class == 'uilist') && $func)
		{
			// if planner_start_with_group is set in the users prefs: switch owner for planner to planner_start_with_group and back
			if ($this->cal_prefs['planner_start_with_group'])
			{
				if ($func == 'planner' && $this->view != 'planner' && $this->owner == $this->user)
				{
					//echo "<p>switched for planner to {$this->cal_prefs['planner_start_with_group']}, view was $this->view, func=$func, owner was $this->owner</p>\n";
					$states['save_owner'] = $this->save_owner = $this->owner;
					$states['owner'] = $this->owner = $this->cal_prefs['planner_start_with_group'];
				}
				elseif ($func != 'planner' && $this->view == 'planner' && $this->owner == $this->cal_prefs['planner_start_with_group'] && $this->save_owner)
				{
					//echo "<p>switched back to $this->save_owner, view was $this->view, func=$func, owner was $this->owner</p>\n";
					$states['owner'] = $this->owner = $this->save_owner;
					$states['save_owner'] = $this->save_owner = 0;
				}
			}
			$this->view = $states['view'] = $func;
		}
		$this->view_menuaction = $this->view == 'listview' ? 'calendar.uilist.listview' : 'calendar.uiviews.'.$this->view;
		// deal with group-owners
		if (substr($this->owner,0,2) == 'g_' || $GLOBALS['egw']->accounts->get_type($this->owner) == 'g')
		{
			$this->set_owner_to_group($this->owner);
			$states['owner'] = $this->owner;
		}
		$states['multiple'] = $this->multiple = $_GET['multiple'] || count(explode(',',$this->owner)) > 1;

		if ($this->debug > 0 || $this->debug == 'menage_states') $this->bo->debug_message('uical::manage_states(%1) session was %2, states now %3, is_group=%4, g_owner=%5',True,$set_states,$states_session,$states,$this->is_group,$this->g_owner);
		// save the states in the session
		$GLOBALS['egw']->session->appsession('session_data','calendar',$states);
	}

	/**
	 * Sets a group as owner (of the events to show)
	 *
	 * It set $this->is_group and $this->g_owner - array with user-id's of the group-members who gave read-grants
	 * @param group-id or 'g_'+group-id
	 */
	function set_owner_to_group($owner)
	{
		$this->owner = (int) (substr($owner,0,2) == 'g_' ? substr($owner,2) : $owner);
		$this->is_group = True;
		$this->g_owner = Array();
		$members = $GLOBALS['egw']->accounts->member($this->owner);
		if (is_array($members))
		{
			foreach($members as $user)
			{
				// use only members which gave the user a read-grant
				if ($this->bo->check_perms(EGW_ACL_READ,0,$user['account_id']))
				{
					$this->g_owner[] = $user['account_id'];
				}
			}
		}
		if ($this->debug > 2 || $this->debug == 'set_owner_to_group') $this->bo->debug_message('uical::set_owner_to_group(%1): owner=%2, g_owner=%3',True,$owner,$this->owner,$this->g_owner);
	}

	/**
	* gets the icons displayed for a given event
	*
	* @param $event array
	* @return array of 'img' / 'title' pairs
	*/
	function event_icons($event)
	{
		$is_private = !$event['public'] && !$this->bo->check_perms(EGW_ACL_READ,$event);
		$viewable = !$this->bo->printer_friendly && $this->bo->check_perms(EGW_ACL_READ,$event);

		if (!$is_private)
		{
			if($event['priority'] == 3)
			{
				$icons[] = $this->html->image('calendar','high',lang('high priority'));
			}
			if($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$icons[] = $this->html->image('calendar','recur',lang('recurring event'));
			}
			$icons[] = $this->html->image('calendar',count($event['participants']) > 1 ? 'users' : 'single',
				implode(",\n",$this->bo->participants($event)));
		}
		if($event['public'] == 0)
		{
			$icons[] = $this->html->image('calendar','private',lang('private'));
		}
		if(isset($event['alarm']) && count($event['alarm']) >= 1 && !$is_private)
		{
			$icons[] = $this->html->image('calendar','alarm',lang('alarm'));
		}
		foreach($event['participants'] as  $participant => $status)
		{
			if(is_numeric($participant)) continue;
			if(isset($this->bo->resources[$participant{0}]) && isset($this->bo->resources[$participant{0}]['icon']) && !$seticon[$participant{0}])
			{
				$seticon[$participant{0}] = true;
			 	$icons[] = $this->html->image($this->bo->resources[$participant{0}]['app'],
			 		($this->bo->resources[$participant{0}]['icon'] ? $this->bo->resources[$participant{0}]['icon'] : 'navbar'),
			 		lang($this->bo->resources[$participant{0}]['app']),
			 		'width="16px" height="16px"');
			}
		}
		return $icons;
	}

	/**
	* Create a select-box item in the sidebox-menu
	* @privat used only by sidebox_menu !
	*/
	function _select_box($title,$name,$options,$baseurl='')
	{
		if ($baseurl)	// we append the value to the baseurl
		{
			$baseurl .= strstr($baseurl,'?') === False ? '?' : '&';
			$onchange="location='$baseurl'+this.value;";
		}
		else			// we add $name=value to the actual location
		{
			$onchange="location=location+(location.search.length ? '&' : '?')+'".$name."='+this.value;";
		}
		$select = ' <select style="width: 185px;" name="'.$name.'" onchange="'.$onchange.'" title="'.
			lang('Select a %1',lang($title)).'">'.
			$options."</select>\n";

		return array(
			'text' => $select,
			'no_lang' => True,
			'link' => False
		);
	}

	/**
	 * Generate a link to add an event, incl. the necessary popup
	 *
	 * @param string $content content of the link
	 * @param string $date=null which date should be used as start- and end-date, default null=$this->date
	 * @param int $hour=null which hour should be used for the start, default null=$this->hour
	 * @param int $minute=0 start-minute
	 * @return string the link incl. content
	 */
	function add_link($content,$date=null,$hour=null,$minute=0)
	{
		$vars = array(
			'menuaction'=>'calendar.uiforms.edit',
			'date' => $date ? $date : $this->date,
		);
		if (!is_null($hour))
		{
			$vars['hour'] = $hour;
			$vars['minute'] = $minute;
		}
		return $this->html->a_href($content,'/index.php',$vars,' target="_blank" title="'.$this->html->htmlspecialchars(lang('Add')).
			'" onclick="'.$this->popup('this.href','this.target').'; return false;"');
	}
	
	/**
	 * returns javascript to open a popup window: window.open(...)
	 *
	 * @param string $link link or this.href
	 * @param string $target='_blank' name of target or this.target
	 * @param int $width=750 width of the window
	 * @param int $height=400 height of the window
	 * @return string javascript (using single quotes)
	 */
	function popup($link,$target='_blank',$width=750,$height=410)
	{
		return 'egw_openWindowCentered2('.($link == 'this.href' ? $link : "'".$link."'").','.
			($target == 'this.target' ? $target : "'".$target."'").",$width,$height,'yes')";

		return 'window.open('.($link == 'this.href' ? $link : "'".$link."'").','.
			($target == 'this.target' ? $target : "'".$target."'").','.
			"'dependent=yes,width=$width,height=$height,scrollbars=yes,status=yes')";
	}

	/**
	 * creates the content for the sidebox-menu, called as hook
	 */
	function sidebox_menu()
	{
		$base_hidden_vars = $link_vars = array();
		if (@$_POST['keywords'])
		{
			$base_hidden_vars['keywords'] = $_POST['keywords'];
		}

		$n = 0;	// index for file-array

		$planner_days_for_view = false;
		switch($this->view)
		{
			case 'month': $planner_days_for_view = 0; break;
			case 'week':  $planner_days_for_view = $this->cal_prefs['days_in_weekview'] == 5 ? 5 : 7; break;
			case 'day':   $planner_days_for_view = 1; break;
		}
		// Toolbar with the views
		$views = '<table style="width: 100%;"><tr>'."\n";
		foreach(array(
			'add' => array('icon'=>'new','text'=>'add'),
			'day' => array('icon'=>'today','text'=>'Today','menuaction' => 'calendar.uiviews.day','date' => $this->bo->date2string($this->bo->now_su)),
			'week' => array('icon'=>'week','text'=>'Weekview','menuaction' => 'calendar.uiviews.week'),
			'month' => array('icon'=>'month','text'=>'Monthview','menuaction' => 'calendar.uiviews.month'),
			'planner' => array('icon'=>'planner','text'=>'Group planner','menuaction' => 'calendar.uiviews.planner','sortby' => $this->sortby)+
				($planner_days_for_view !== false ? array('planner_days' => $planner_days_for_view) : array()),
			'list' => array('icon'=>'list','text'=>'Listview','menuaction'=>'calendar.uilist.listview'),
		) as $view => $data)
		{
			$icon = array_shift($data);
			$title = array_shift($data);
			$vars = array_merge($link_vars,$data);

			$icon = $this->html->image('calendar',$icon,lang($title));
			$link = $view == 'add' ? $this->add_link($icon) : $this->html->a_href($icon,'/index.php',$vars);

			$views .= '<td align="center">'.$link."</a></td>\n";
		}
		$views .= "</tr></table>\n";

		$file[++$n] = array('text' => $views,'no_lang' => True,'link' => False,'icon' => False);

		// special views and view-options menu
		$options = '';
		foreach(array(
			array(
				'text' => lang('select one'),
				'value' => '',
				'selected' => False,
			),
			array(
				'text' => lang('dayview'),
				'value' => 'menuaction=calendar.uiviews.day',
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.day',
			),
			array(
				'text' => lang('weekview with weekend'),
				'value' => 'menuaction=calendar.uiviews.week&days=7',
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.week' && $this->cal_prefs['days_in_weekview'] != 5,
			),
			array(
				'text' => lang('weekview without weekend'),
				'value' => 'menuaction=calendar.uiviews.week&days=5',
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.week' && $this->cal_prefs['days_in_weekview'] == 5,
			),
			array(
				'text' => lang('monthview'),
				'value' => 'menuaction=calendar.uiviews.month',
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.month',
			),
			array(
				'text' => lang('planner by category'),
				'value' => 'menuaction=calendar.uiviews.planner&sortby=category'.
					($planner_days_for_view !== false ? '&planner_days='.$planner_days_for_view : ''),
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.planner' && $this->sortby != 'user',
			),
			array(
				'text' => lang('planner by user'),
				'value' => 'menuaction=calendar.uiviews.planner&sortby=user'.
					($planner_days_for_view !== false ? '&planner_days='.$planner_days_for_view : ''),
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.planner' && $this->sortby == 'user',
			),
			array(
				'text' => lang('listview'),
				'value' => 'menuaction=calendar.uilist.list',
				'selected' => $_GET['menuaction'] == 'calendar.uilist.listview',
			),
		) as $data)
		{
			$options .= '<option value="'.$data['value'].'"'.($data['selected'] ? ' selected="1"' : '').'>'.$this->html->htmlspecialchars($data['text'])."</option>\n";
		}
		$file[++$n] = $this->_select_box('displayed view','view',$options,$GLOBALS['egw']->link('/index.php'));

		// Search
		$blur = addslashes($this->html->htmlspecialchars(lang('Search').'...'));
		$value = @$_POST['keywords'] ? $this->html->htmlspecialchars($_POST['keywords']) : $blur;
		$file[++$n] = array(
			'text' => $this->html->form('<input name="keywords" value="'.$value.'" style="width: 185px;"'.
				' onFocus="if(this.value==\''.$blur.'\') this.value=\'\';"'.
				' onBlur="if(this.value==\'\') this.value=\''.$blur.'\';" title="'.lang('Search').'">',
				'','/index.php',array('menuaction'=>'calendar.uilist.listview')),
			'no_lang' => True,
			'link' => False,
		);
		// Minicalendar
		$link = array();
		foreach(array(
			'day'   => 'calendar.uiviews.day',
			'week'  => 'calendar.uiviews.week',
			'month' => 'calendar.uiviews.month') as $view => $menuaction)
		{
			if ($this->view == 'planner')	
			{
				switch($view)
				{
					case 'day':   $link_vars['planner_days'] = 1; break;
					case 'week':  $link_vars['planner_days'] = $this->cal_prefs['days_in_weekview'] == 5 ? 5 : 7; break;
					case 'month': $link_vars['planner_days'] = 0; break;
				}
				$link_vars['menuaction'] = $this->view_menuaction;	// stay in the planner
			}
			elseif ($this->view == 'listview')
			{
				$link_vars['menuaction'] = $this->view_menuaction;	// stay in the listview
			}
			else
			{
				$link_vars['menuaction'] = $menuaction;
			}
			unset($link_vars['date']);	// gets set in jscal
			$link[$view] = $l = $GLOBALS['egw']->link('/index.php',$link_vars);
		}
		$jscalendar = $GLOBALS['egw']->jscalendar->flat($link['day'],$this->date,
			$link['week'],lang('show this week'),$link['month'],lang('show this month'));
		$file[++$n] = array('text' => $jscalendar,'no_lang' => True,'link' => False,'icon' => False);

		// Category Selection
		$file[++$n] = $this->_select_box('Category','cat_id',
			'<option value="0">'.lang('All categories').'</option>'.
		$this->cats->formatted_list('select','all',$this->cat_id,'True'));

		// we need a form for the select-boxes => insert it in the first selectbox
		$file[$n]['text'] = $this->html->form(False,$base_hidden_vars,'/index.php',array('menuaction' => $_GET['menuaction'])) .
			$file[$n]['text'];

		// Filter all or private
		if(is_numeric($this->owner) && $this->bo->check_perms(EGW_ACL_PRIVATE,0,$this->owner))
		{
			$file[] = $this->_select_box('Filter','filter',
				'<option value=" all "'.($this->filter==' all '?' selected="1"':'').'>'.lang('No filter').'</option>'."\n".
				'<option value=" private "'.($this->filter==' private '?' selected="1"':'').'>'.lang('Private Only').'</option>'."\n");
		}

		// Calendarselection: User or Group
		if(count($this->bo->grants) > 0 && (!isset($GLOBALS['egw_info']['server']['deny_user_grants_access']) ||
			!$GLOBALS['egw_info']['server']['deny_user_grants_access']))
		{
			$grants = array();
			foreach($this->bo->list_cals() as $grant)
			{
				$grants[] = $grant['grantor'];
			}
			// exclude non-accounts from the account-selection
			$accounts = array();
			foreach(explode(',',$this->owner) as $owner)
			{
				if (is_numeric($owner)) $accounts[] = $owner;
			}
			$file[] = array(
				'text' => "
<script type=\"text/javascript\">
function load_cal(url,id) {
	selectBox = document.getElementById(id);
	owner='';
	for(i=0; i < selectBox.length; ++i) {
		if (selectBox.options[i].selected) {
			owner += (owner ? ',' : '') + selectBox.options[i].value;
		}
	}
	if (owner) {
		location=url+'&owner='+owner;
	}
}
</script>
".
				$this->accountsel->selection('owner','uical_select_owner',$accounts,'calendar+',$this->multiple ? 3 : 1,False,
					' style="width: '.($this->multiple && $this->common_prefs['account_selection']=='selectbox' ? 185 : 165).'px;"'.
					' title="'.lang('select a %1',lang('user')).'" onchange="load_cal(\''.
					$GLOBALS['egw']->link('/index.php',array(
						'menuaction' => $this->view_menuaction,
						'date' => $this->date,
					)).'\',\'uical_select_owner\');"','',$grants),
				'no_lang' => True,
				'link' => False
			);
		}
		// Import & Export
		$file[] = array(
			'text' => lang('Export').': '.$this->html->a_href(lang('iCal'),'calendar.uiforms.export',$this->first ? array(
				'start' => $this->bo->date2string($this->first),
				'end'   => $this->bo->date2string($this->last),
			) : false),
			'no_lang' => True,
			'link' => False,
		);
		$file[] = array(
			'text' => lang('Import').': '.$this->html->a_href(lang('iCal'),'calendar.uiforms.import').
				' &amp; '.$this->html->a_href(lang('CSV'),'/calendar/csv_import.php'),
			'no_lang' => True,
			'link' => False,
		);
/*
		$print_functions = array(
			'calendar.uiviews.day'	=> 'calendar.pdfcal.day',
			'calendar.uiviews.week'	=> 'calendar.pdfcal.week',
		);
		if (isset($print_functions[$_GET['menuaction']]))
		{
			$file[] = array(
				'text'	=> 'pdf-export / print',
				'link'	=> $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => $print_functions[$_GET['menuaction']],
					'date' => $this->date,
				)),
				'target' => '_blank',
			);
		}				
*/
		// we need to set the sidebox-width a bit wider, as idots.css sets it to 147, to small for the jscal
		// setting it to auto, uses the smallest possible size, but IE kills the jscal if the width is set to auto !!!
		$width = 203;
		echo '<style>
.divSidebox
{
	width: '.($this->html->user_agent == 'msie' ? $width.'px' : 'auto; max-width: '.$width.'px;').';
}
</style>'."\n";
		$appname = 'calendar';
		$menu_title = $GLOBALS['egw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
		display_sidebox($appname,$menu_title,$file);
		echo "</form>\n";
		
		// resources menu hooks
 		foreach ($this->bo->resources as $resource)
		{
			if(!is_array($resource['cal_sidebox'])) continue;
			$menu_title = $resource['cal_sidebox']['menu_title'] ? $resource['cal_sidebox']['menu_title'] : lang($resource['app']);
			$file = ExecMethod($resource['cal_sidebox']['file'], $this->view_menuaction, $this->date);
			display_sidebox($appname,$menu_title,$file);
		}


		if ($GLOBALS['egw_info']['user']['apps']['preferences'])
		{
			$menu_title = lang('Preferences');
			$file = Array(
				'Calendar preferences'=>$GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname=calendar'),
				'Grant Access'=>$GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=calendar'),
				'Edit Categories' =>$GLOBALS['egw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=calendar&cats_level=True&global_cats=True'),
			);
			display_sidebox($appname,$menu_title,$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$menu_title = lang('Administration');
			$file = Array(
				'Configuration'=>$GLOBALS['egw']->link('/index.php','menuaction=admin.uiconfig.index&appname=calendar'),
				'Custom Fields'=>$GLOBALS['egw']->link('/index.php','menuaction=admin.customfields.edit&appname=calendar'),
				'Holiday Management'=>$GLOBALS['egw']->link('/index.php','menuaction=calendar.uiholiday.admin'),
				'Global Categories' =>$GLOBALS['egw']->link('/index.php','menuaction=admin.uicategories.index&appname=calendar'),
			);
			display_sidebox($appname,$menu_title,$file);
		}
	}
}
