<?php
/**************************************************************************\
* eGroupWare - Calendar - shared base-class of all calendar UserInterfaces *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

/**
 * shared base-class of all calendar UserInterfaces
 *
 * It manages eg. the state of the controls in the UI and generated the calendar navigation (sidebox-menu)
 *
 * @package calendar
 * @author RalfBecker@outdoor-training.de
 * @license GPL
 */
class uical
{
	/**
	 * @var $debug mixed integer level or string function-name
	 */
	var $debug=False;
	
	/**
	 * @var $bo class bocal
	 */
	var $bo;

	/**
	 * Constructor
	 */
	function uical()
	{
		foreach(array(
			'bo'    => 'calendar.bocal',
			'jscal' => 'phpgwapi.jscalendar',	// for the sidebox-menu
			'html'  => 'phpgwapi.html',
			'datetime' => 'phpgwapi.datetime',
			'cats'  => 'phpgwapi.categories',
			'accountsel' => 'phpgwapi.uiaccountsel',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['phpgw']->$class))
			{
				$GLOBALS['phpgw']->$class = CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['phpgw']->$class;
		}
		$this->common_prefs = &$GLOBALS['phpgw_info']['user']['preferences']['common'];
		$this->cal_prefs = &$GLOBALS['phpgw_info']['user']['preferences']['calendar'];
		$this->wd_start = 60*$this->cal_prefs['workdaystarts'];
		$this->wd_end   = 60*$this->cal_prefs['workdayends'];
		$this->interval_m   = $this->cal_prefs['interval'];

		$this->user = $GLOBALS['phpgw_info']['user']['account_id'];

		$this->manage_states();

		$GLOBALS['uical'] = &$this;	// make us available for ExecMethod, else it creates a new instance
		
		// calendar does not work with hidden sidebox atm.
		unset($GLOBALS['phpgw_info']['user']['preferences']['common']['auto_hide_sidebox']);
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
	 *	- return_to: the view the dialogs should return to
	 * @param set_states array to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function manage_states($set_states=NULL)
	{
		$states = $states_session = $GLOBALS['phpgw']->session->appsession('session_data','calendar');

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
			'multiple'   => 0,
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
		// set the actual view as return_to
		list($app,$class,$func) = explode('.',$_GET['menuaction']);
		if ($class == 'uiviews' && $func)
		{
			$states['return_to'] = $_GET['menuaction'];
		}
		// deal with group-owners
		if (substr($this->owner,0,2) == 'g_' || $GLOBALS['phpgw']->accounts->get_type($this->owner) == 'g')
		{
			$this->set_owner_to_group($this->owner);
			$states['owner'] = $this->owner;
		}
		$states['multiple'] = $this->multiple = $_GET['multiple'] || count(explode(',',$this->owner)) > 1;

		if ($this->debug > 0 || $this->debug == 'menage_states') $this->bo->debug_message('uical::manage_states(%1) session was %2, states now %3, is_group=%4, g_owner=%5',True,$set_states,$states_session,$states,$this->is_group,$this->g_owner);
		// save the states in the session
		$GLOBALS['phpgw']->session->appsession('session_data','calendar',$states);
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
		$members = $GLOBALS['phpgw']->accounts->member($this->owner);
		if (is_array($members))
		{
			foreach($members as $user)
			{
				// use only members which gave the user a read-grant
				if ($this->bo->check_perms(PHPGW_ACL_READ,0,$user['account_id']))
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
		$is_private = !$event['public'] && !$this->bo->check_perms(PHPGW_ACL_READ,$event);
		$viewable = !$this->bo->printer_friendly && $this->bo->check_perms(PHPGW_ACL_READ,$event);

		if (!$is_private)
		{
			if($event['priority'] == 3)
			{
				$icons[] = $this->html->image('calendar','high',lang('high priority'));
			}
			if($event['recur_type'] == MCAL_RECUR_NONE)
			{
				//$icons[] = $this->html->image('calendar','circle',lang('single event'));
			}
			else
			{
				$icons[] = $this->html->image('calendar','recur',lang('recurring event'));
			}
			$icons[] = $this->html->image('calendar',count($event['participants']) > 1 ? 'multi_3' : 'single',
				implode(",\n",$this->bo->participants($event['participants'])));
		}
		if($event['public'] == 0)
		{
			$icons[] = $this->html->image('calendar','private',lang('private'));
		}
		if(isset($event['alarm']) && count($event['alarm']) >= 1 && !$is_private)
		{
			$icons[] = $this->html->image('calendar','alarm',lang('alarm'));
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
		$select = ' <select style="width: 100%;" name="'.$name.'" onchange="'.$onchange.'" title="'.
			lang('Select a %1',lang($title)).'">'.
			$options."</select>\n";

		return array(
			'text' => $select,
			'no_lang' => True,
			'link' => False
		);
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

		// Toolbar with the views
		$views = '<table style="width: 100%;"><tr>'."\n";
		foreach(array(
			'add' => array('icon'=>'new3','text'=>'add','menuaction'=>'calendar.uicalendar.add'),
			'day' => array('icon'=>'today','text'=>'Today','menuaction' => 'calendar.uiviews.day'),
			'week' => array('icon'=>'week','text'=>'This week','menuaction' => 'calendar.uiviews.week'),
			'month' => array('icon'=>'month','text'=>'This month','menuaction' => 'calendar.uiviews.month'),
			'year'  => array('icon'=>'year','text'=>'This year','menuaction' => 'calendar.uicalendar.year'),
			'planner' => array('icon'=>'planner','text'=>'Group Planner','menuaction' => 'calendar.uicalendar.planner'),
			'matrixselect' => array('icon'=>'view','text'=>'Daily Matrix View','menuaction' => 'calendar.uicalendar.matrixselect'),
		) as $view => $data)
		{
			$vars = $link_vars;
			$vars['menuaction'] = $data['menuaction'];
			if ($view == 'day')
			{
				$vars['date'] = $this->bo->date2string($this->bo->now_su);	// go to today
			}
			$views .= '<td align="center"><a href="'.$GLOBALS['phpgw']->link('/index.php',$vars).'"><img src="'.
				$GLOBALS['phpgw']->common->find_image('calendar',$data['icon']).
				'" title="'.lang($data['text']).'"></a></td>'."\n";
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
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.day' && $_GET['days'] != 2,
			),
			/*array(
				'text' => lang('two dayview'),
				'value' => 'menuaction=calendar.uiviews.week&days=2',
				'selected' => $_GET['menuaction'] == 'calendar.uiviews.day' && $_GET['days'] == 2,
			),*/
			array(
				'text' => lang('weekview'),
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
				'text' => lang('yearview'),
				'value' => 'menuaction=calendar.uicalendar.year',
				'selected' => $_GET['menuaction'] == 'calendar.uicalendar.year',
			),
			array(
				'text' => lang('planner by category'),
				'value' => 'menuaction=calendar.uicalendar.planner&sortby=category',
				'selected' => $_GET['menuaction'] == 'calendar.uicalendar.planner' && $this->sort_by != 'user',
			),
			array(
				'text' => lang('planner by user'),
				'value' => 'menuaction=calendar.uicalendar.planner&sortby=user',
				'selected' => $_GET['menuaction'] == 'calendar.uicalendar.planner' && $this->sort_by == 'user',
			),
			array(
				'text' => lang('matrixview'),
				'value' => 'menuaction=calendar.uicalendar.matrixselect',
				'selected' => $_GET['menuaction'] == 'calendar.uicalendar.matrixselect' ||
					$_GET['menuaction'] == 'calendar.uicalendar.viewmatrix',
			),
		) as $data)
		{
			$options .= '<option value="'.$data['value'].'"'.($data['selected'] ? ' selected="1"' : '').'>'.$this->html->htmlspecialchars($data['text'])."</option>\n";
		}
		$file[++$n] = $this->_select_box('displayed view','view',$options,$GLOBALS['phpgw']->link('/index.php'));

		// Search
		$blur = addslashes($this->html->htmlspecialchars(lang('Search').'...'));
		$value = @$_POST['keywords'] ? $_POST['keywords'] : $blur;
		$file[++$n] = array(
			'text' => $this->html->form('<input name="keywords" value="'.$value.'" style="width: 100%;"'.
				' onFocus="if(this.value==\''.$blur.'\') this.value=\'\';"'.
				' onBlur="if(this.value==\'\') this.value=\''.$blur.'\';" title="'.lang('Search').'">',
				$base_hidden_vars,'/index.php',array('menuaction'=>'calendar.uicalendar.search')),
			'no_lang' => True,
			'link' => False,
		);

		// Minicalendar
		foreach(array(
			'day'=>'calendar.uiviews.day',
			'week'=>'calendar.uiviews.week',
			'month'=>'calendar.uiviews.month') as $view => $menuaction)
		{
			$link_vars['menuaction'] = $view == 'month' && $_GET['menuaction'] == 'calendar.uicalendar.planner' ?
				'calendar.uicalendar.planner' : $menuaction;	// stay in the planner
			unset($link_vars['date']);	// gets set in jscal
			$link[$view] = $GLOBALS['phpgw']->link('/index.php',$link_vars);
		}
		$jscalendar = $GLOBALS['phpgw']->jscalendar->flat($link['day'],$this->date,
			$link['week'],lang('show this week'),$link['month'],lang('show this month'));
		$file[++$n] = array('text' => $jscalendar,'no_lang' => True,'link' => False,'icon' => False);

		// Category Selection
		$file[++$n] = $this->_select_box('Category','cat_id',
			'<option value="0">'.lang('All categories').'</option>'.
		$this->cats->formated_list('select','all',$this->cat_id,'True'));

		// we need a form for the select-boxes => insert it in the first selectbox
		$file[$n]['text'] = $this->html->form(False,$base_hidden_vars,'/index.php',array('menuaction' => $_GET['menuaction'])) .
			$file[$n]['text'];

		// Filter all or private
		if($this->bo->check_perms(PHPGW_ACL_PRIVATE,0,$this->owner))
		{
			$file[] = $this->_select_box('Filter','filter',
				'<option value=" all "'.($this->filter==' all '?' selected="1"':'').'>'.lang('No filter').'</option>'."\n".
				'<option value=" private "'.($this->filter==' private '?' selected="1"':'').'>'.lang('Private Only').'</option>'."\n");
		}

		// Calendarselection: User or Group
		if(count($this->bo->grants) > 0 && (!isset($GLOBALS['phpgw_info']['server']['deny_user_grants_access']) ||
			!$GLOBALS['phpgw_info']['server']['deny_user_grants_access']))
		{
			$grants = array();
			foreach($this->bo->list_cals() as $grant)
			{
				$grants[] = $grant['grantor'];
			}
			if ($this->multiple)
			{
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
					$this->accountsel->selection('owner','uical_select_owner',$this->owner,'calendar+',3,False,
						' style="width: 85%;" title="'.lang('select a %1',lang('user')).'"','',$grants).
						$this->html->submit_button('go','>>',"load_cal('".$GLOBALS['phpgw']->link('/index.php',array(
							'menuaction' => $_GET['menuaction'],
							'date' => $this->date,
						))."','uical_select_owner'); return false;",True,' title="'.lang('Go!').'"'),
					'no_lang' => True,
					'link' => False
				);

			}
			else
			{
				$file[] = array(
					'text' => $this->accountsel->selection('owner','uical_select_owner',$this->owner,'calendar+',0,False,
						' style="width: 85%;" title="'.lang('select a %1',lang('user')).'"',
						"location=location+(location.search.length ? '&' : '?')+'owner='+this.value;",$grants).
						$this->html->a_href($this->html->image('phpgwapi','users',lang('show the calendar of multiple users')),array(
							'menuaction' => $_GET['menuaction'],
							'multiple' => 1,
							'date' => $this->date,
						)),
					'no_lang' => True,
					'link' => False
				);
			}
		}

		// Import & Export
		$file['Export'] = $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicalendar.export');
		$file['Import'] = $GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uiicalendar.import');

		// we need to set the sidebox-width a bit wider, as idots.css sets it to 147, to small for the jscal
		// setting it to auto, uses the smallest possible size, but IE kills the jscal if the width is set to auto !!!
		echo '<style>
.divSidebox
{
	width: '.($this->html->user_agent=='msie'?'180px':'auto').';
	max-width: 180px;
}
</style>'."\n";
		$menu_title = $GLOBALS['phpgw_info']['apps'][$appname]['title'] . ' '. lang('Menu');
		display_sidebox($appname,$menu_title,$file);
		echo "</form>\n";

		if ($GLOBALS['phpgw_info']['user']['apps']['preferences'])
		{
			$menu_title = lang('Preferences');
			$file = Array(
				'Calendar preferences'=>$GLOBALS['phpgw']->link('/preferences/preferences.php','appname=calendar'),
				'Grant Access'=>$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app=calendar'),
				'Edit Categories' =>$GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=calendar&cats_level=True&global_cats=True'),
			);
			display_sidebox($appname,$menu_title,$file);
		}

		if ($GLOBALS['phpgw_info']['user']['apps']['admin'])
		{
			$menu_title = lang('Administration');
			$file = Array(
				'Configuration'=>$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiconfig.index&appname=calendar'),
				'Custom Fields'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uicustom_fields.index'),
				'Holiday Management'=>$GLOBALS['phpgw']->link('/index.php','menuaction=calendar.uiholiday.admin'),
				'Import CSV-File' => $GLOBALS['phpgw']->link('/calendar/csv_import.php'),
				'Global Categories' =>$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uicategories.index&appname=calendar'),
			);
			display_sidebox($appname,$menu_title,$file);
		}
	}
}
