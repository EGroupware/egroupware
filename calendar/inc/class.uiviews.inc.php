<?php
/**************************************************************************\
* eGroupWare - Calendar - Views and Widgets                                *
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
 * Class to generate the calendar views and the necesary widgets
 *
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004/5 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uiviews extends uical
{
	var $public_functions = array(
		'day'   => True,
		'week'  => True,
		'month' => True,
		'planner' => True,
	);
	/**
	 * @var $debug mixed integer level or string function- or widget-name
	 */
	var $debug=false;

	/**
	 * @var minimum width for an event
	 */
	var $eventCol_min_width = 80;
	
	/**
	 * @var int $extraRows extra rows above and below the workday
	 */
	var $extraRows = 1;

	var $timeRow_width = 40;

	/**
	 * @var int $rowsToDisplay how many rows per day get displayed, gets set be the timeGridWidget
	 */
	var $rowsToDisplay;

	/**
	 * @var int $rowHeight height in percent of one row, gets set be the timeGridWidget
	 */
	var $rowHeigth;
	
	/**
	 * @var array $search_params standard params for calling bocal::search for all views, set by the constructor
	 */
	var $search_params;
	
	/**
	 * Constructor
	 *
	 * @param array $set_states=null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function uiviews($set_states=null)
	{
		$this->uical(false,$set_states);	// call the parent's constructor

		$GLOBALS['egw_info']['flags']['nonavbar'] = False;
		$app_header = array(
			'calendar.uiviews.day'   => lang('Dayview'),
			'calendar.uiviews.week'  => lang('Weekview'),
			'calendar.uiviews.month' => lang('Monthview'),
			'calendar.uiviews.planner' => lang('Group planner'),
		);
		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].
			(isset($app_header[$_GET['menuaction']]) ? ' - '.$app_header[$_GET['menuaction']] : '');

		// standard params for calling bocal::search for all views
		$this->search_params = array(
			'start'   => $this->date,
			'cat_id'  => $this->cat_id,
			'users'   => $this->is_group ? $this->g_owner : explode(',',$this->owner),
			'filter'  => $this->filter,
			'daywise' => True,
		);
		$this->holidays = $this->bo->read_holidays($this->year);
	}
	
	/**
	 * Show the last view or the default one, if no last
	 */
	function index()
	{
		if (!$this->view) $this->view = 'week';

		// handle views in other files
		if (!isset($this->public_functions[$this->view]))
		{
			$GLOBALS['egw']->redirect_link('/index.php',array('menuaction'=>$this->view_menuaction));
		}
		// get manual to load the right page
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualCalendar'.ucfirst($this->view));
		
		$this->{$this->view}();
	}
	
	/**
	 * Show the calendar on the home page
	 *
	 * @return string with content
	 */
	function &home()
	{
		// set some stuff for the home-page
		$this->uiviews(array(
			'date'       => $this->bo->date2string($this->bo->now_su),
			'cat_id'     => 0,
			'filter'     => 'all',
			'owner'      => substr($this->cal_prefs['defaultcalendar'],0,7) == 'planner' && $this->cal_prefs['planner_start_with_group'] ? 
				$this->cal_prefs['planner_start_with_group'] : $this->user,
			'multiple'   => 0,
			'view'       => $this->bo->cal_prefs['defaultcalendar'],			
		));
		switch($this->cal_prefs['defaultcalendar'])
		{
			case 'planner_user':
			case 'planner_cat':
			case 'planner':
				return $this->planner(true);

			case 'month':
				return $this->month(0,true);

			default:
			case 'week':
				return $this->week(0,true);
				
			case 'day':
				return $this->day(true);
		}
	}
	
	/**
	 * Displays the planner view
	 *
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &planner($home=false)
	{
		if (!$this->planner_days)	// planner monthview
		{
			if ($this->day < 15)	// show one complete month
			{
				$this->_week_align_month($this->first,$this->last);
				$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang(adodb_date('F',$this->bo->date2ts($this->date))).' '.$this->year;
			}
			else	// show 2 half month
			{
				$this->_week_align_month($this->first,$this->last,15);
				$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang(adodb_date('F',$this->first)).' / '.lang(adodb_date('F',$this->last)).' '.$this->year;
			}
		}
		elseif ($this->planner_days >= 5)	// weeekview
		{
			$this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
			$this->last = $this->bo->date2array($this->first);
			$this->last['day'] += (int) $this->planner_days - 1;
			$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
			unset($this->last['raw']);
			$this->last = $this->bo->date2ts($this->last);
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Week').' '.adodb_date('W',$this->first).': '.$this->bo->long_date($this->first,$this->last);
		}
		else // dayview
		{
			$this->first = $this->bo->date2ts($this->date);
			$this->last = $this->bo->date2array($this->first);
			$this->last['day'] += (int) $this->planner_days - 1;
			$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
			unset($this->last['raw']);
			$this->last = $this->bo->date2ts($this->last);
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.($this->planner_days == 1 ? lang(date('l',$this->first)).', ' : '').
				$this->bo->long_date($this->first,$this->planner_days > 1 ? $this->last : 0);
		}
		
		$search_params = $this->search_params;
		$search_params['daywise'] = false;
		$search_params['start'] = $this->first;
		$search_params['end'] = $this->last;
		$search_params['enum_groups'] = $this->sortby == 'user';
		$events = $this->bo->search($search_params);

		if ($this->debug > 0) $this->bo->debug_message('uiviews::planner() date=%1: first=%2, last=%3',False,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		$content =& $this->plannerWidget($events,$this->first,$this->last,$this->sortby == 'user' ? false : (int) $this->cat_id);

		if (!$home)
		{
			$GLOBALS['egw']->common->egw_header();
			if ($_GET['msg']) echo '<p class="redItalic" align="center">'.$this->html->htmlspecialchars($_GET['msg'])."</p>\n";
		
			echo $content;
		}
		return $content;
	}

	/**
	 * Displays the monthview or a multiple week-view
	 *
	 * @param int $weeks=0 number of weeks to show, if 0 (default) all weeks of one month are shows
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &month($weeks=0,$home=false)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(weeks=%1) date=%2',True,$weeks,$this->date);

		if ($weeks)
		{
			$this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day=1);
			$this->last = $this->first + $weeks * 7 * DAY_s - 1;
		}
		else
		{
			$this->_week_align_month($this->first,$this->last);
		}
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(%1) date=%2: first=%3, last=%4',False,$weeks,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang(adodb_date('F',$this->bo->date2ts($this->date))).' '.$this->year;

		$days =& $this->bo->search(array(
			'start'   => $this->first,
			'end'     => $this->last,
		)+$this->search_params);

		$content = '';
		// we add DAY_s/2 to $this->first (using 12h), to deal with daylight saving changes
		for ($week_start = $this->first+DAY_s/2; $week_start < $this->last; $week_start += WEEK_s)
		{
			$week = array();
			for ($i = 0; $i < 7; ++$i)
			{
				$day_ymd = $this->bo->date2string($week_start+$i*DAY_s);
				$week[$day_ymd] = array_shift($days);
			}
			$week_view = array(
				'menuaction' => 'calendar.uiviews.week',
				'date' => $this->bo->date2string($week_start),
			);
			$title = lang('Wk').' '.adodb_date('W',$week_start);
			$title = $this->html->a_href($title,$week_view,'',' title="'.lang('Weekview').'"');

			$content .= $this->timeGridWidget($week,60,200,'',$title);
		}
		if (!$home)
		{
			$GLOBALS['egw']->common->egw_header();
			if ($_GET['msg']) echo '<p class="redItalic" align="center">'.$this->html->htmlspecialchars($_GET['msg'])."</p>\n";
		
			echo $content;
		}
		return $content;
	}

	/**
	 * get start and end of a month aligned to full weeks
	 *
	 * @param int &$first timestamp 0h of first day of week containing the first of the current month
	 * @param int &$last timestamp 23:59:59 of last day of week containg the last day of the current month
	 * @param int $day=1 should the alignment be based on the 1. of the month or an other date, eg. the 15.
	 */
	function _week_align_month(&$first,&$last,$day=1)
	{
		$first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day=$day);
		if ($day == 1)
		{
			$last = $this->datetime->get_weekday_start($this->year,$this->month,
				$this->datetime->days_in_month($this->month,$this->year));
		}
		else
		{
			$last = $this->datetime->get_weekday_start($this->year,$this->month+1,$day);
		}			
		// now we need to calculate the end of the last day of that week
		// as simple $last += WEEK_s - 1; does NOT work, if daylight saving changes in that week!!!
		$last = $this->bo->date2array($last);
		$last['day'] += 6; 
		$last['hour'] = 23; 
		$last['min'] = $last['sec'] = 59;
		unset($last['raw']);	// otherwise date2ts does not calc raw new, but uses it
		$last = $this->bo->date2ts($last);
	}

	/**
	 * Displays the weekview, with 5 or 7 days
	 *
	 * @param int $days=0 number of days to show, if 0 (default) the value from the URL or the prefs is used
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function week($days=0,$home=false)
	{
		if (!$days)
		{
			$days = isset($_GET['days']) ? $_GET['days'] : $this->cal_prefs['days_in_weekview'];
			if ($days != 5) $days = 7;
			if ($days != $this->cal_prefs['days_in_weekview'])	// save the preference
			{
				$GLOBALS['egw']->preferences->add('calendar','days_in_weekview',$days);
				$GLOBALS['egw']->preferences->save_repository();
				$this->cal_prefs['days_in_weekview'] = $days;
			}
		}
		if ($this->debug > 0) $this->bo->debug_message('uiviews::week(days=%1) date=%2',True,$days,$this->date);

		$wd_start = $this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
		if ($days == 5)		// no weekend-days
		{
			switch($this->cal_prefs['weekdaystarts'])
			{
				case 'Saturday':
					$this->first += DAY_s;
					// fall through
				case 'Sunday':
					$this->first += DAY_s;
					break;
			}
		}
		//echo "<p>weekdaystarts='".$this->cal_prefs['weekdaystarts']."', get_weekday_start($this->year,$this->month,$this->day)=".date('l Y-m-d',$wd_start).", first=".date('l Y-m-d',$this->first)."</p>\n";
		$this->last = $this->first + $days * DAY_s - 1;

		$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Week').' '.adodb_date('W',$this->first).': '.$this->bo->long_date($this->first,$this->last);

		$search_params = array(
				'start'   => $this->first,
				'end'     => $this->last,
			) + $this->search_params;

		$users = $this->search_params['users'];
		if (!is_array($users)) $users = array($users);
		
		// for more then 3 users, show all in one row
		if (count($users) > 3) $users = array($users);
		
		$content = '';
		foreach($users as $user)
		{
			$search_params['users'] = $user;
			if (count($users) > 1)
			{
				$content .= '<b>'.$this->bo->participant_name($user)."</b>\n";
			}
			$content .= $this->timeGridWidget($this->bo->search($search_params),
				count($users) * $this->cal_prefs['interval'],400 / count($users),'','',count($users) > 1 ? $user : 0);
		}
		if (!$home)
		{
			$GLOBALS['egw']->common->egw_header();
			if ($_GET['msg']) echo '<p class="redItalic" align="center">'.$this->html->htmlspecialchars($_GET['msg'])."</p>\n";
			
			echo $content;
		}
		return $content;
	}

	/**
	 * Displays the dayview
	 *
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &day($home=false)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::day() date=%1',True,$this->date);

		$this->last = $this->first = $this->bo->date2ts((string)$this->date);
		$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang(adodb_date('l',$this->first)).', '.$this->bo->long_date($this->first);
		
		$this->search_params['end'] = $this->last = $this->first+DAY_s-1;
		
		if (!$home)
		{
			$GLOBALS['egw']->common->egw_header();
			if ($_GET['msg']) echo '<p class="redItalic" align="center">'.$this->html->htmlspecialchars($_GET['msg'])."</p>\n";
	
			$users = $this->search_params['users'];
			if (!is_array($users)) $users = array($users);

			// for more then 3 users, show all in one row
			if (count($users) == 1 || count($users) > 3) 
			{
				$dayEvents =& $this->bo->search($this->search_params);
				$owner = 0;
			}
			else
			{
				$dayEvents = array();
				$search_params = $this->search_params;
				foreach($users as $user)
				{
					$title = '<b>'.$this->bo->participant_name($user).'</b>';
					$search_params['users'] = $user;
					list(,$dayEvents[$title]) = each($this->bo->search($search_params));
				}
				$owner = $users;
			}
			$cols = array();
			$cols[0] =& $this->timeGridWidget($dayEvents,$this->cal_prefs['interval'],450,'','',$owner);

			if (($todos = $this->get_todos($todo_label)) !== false)
			{
				if ($GLOBALS['egw_info']['user']['apps']['infolog'])
				{
					foreach(array('task','phone','note') as $type)
					{
						$todo_label .= '&nbsp;'.$this->html->a_href( $this->html->image('infolog',$type,lang('Add')),'infolog.uiinfolog.edit',array(
							'type' => $type,
							'start_time' => $ts,
						),' target="_blank" onclick="window.open(this.href,this.target,\'dependent=yes,width=750,height=550,scrollbars=yes,status=yes\'); return false;"');
					}
				}
				$cols[1] = $this->html->div(
					$this->html->div($todo_label,'','calDayTodosHeader th')."\n".
					$this->html->div($todos,'','calDayTodosTable'),'','calDayTodos');
				$cols['.1'] = 'width=30%';
				echo $this->html->table(array(
					0 => $cols,
					'.0' => 'valign="top"'
				),'class="calDayView"');
			}
			else
			{
				echo $cols[0];
			}
		}
		else
		{
			return $this->timeGridWidget($this->bo->search($this->search_params),$this->cal_prefs['interval'],300);
		}
	}

	/**
	 * Query the open ToDo's via a hook from InfoLog or any other 'calendar_include_todos' provider
	 *
	 * @param array/string $todo_label label for the todo-box or array with 2 values: the label and a boolean show_all
	 *	On return $todo_label contains the label for the todo-box
	 * @return string/boolean html with a table of open todo's or false if no hook availible
	 */
	function get_todos(&$todo_label)
	{
		$todos_from_hook = $GLOBALS['egw']->hooks->process(array(
			'location'  => 'calendar_include_todos',
			'year'      => $this->year,
			'month'     => $this->month,
			'day'       => $this->day,
			'owner'     => $this->owner	// num. id of the user, not necessary current user
		));

		if(is_array($todo_label))
		{
			list($label,$showall)=$todo_label;
		}
		else
		{
			$label=$todo_label;
			$showall=true;
		}
		$maxshow = (int)$GLOBALS['egw_info']['user']['preferences']['infolog']['mainscreen_maxshow'];
		if($maxshow <= 0)
		{
			$maxshow=10;
		}
		//print_debug("get_todos(): label=$label; showall=$showall; max=$maxshow");

		$content = $todo_label = '';
		if (is_array($todos_from_hook) && count($todos_from_hook))
		{
			foreach($todos_from_hook as $todos)
			{
				$i = 0;
				if (is_array($todos))
				{
					$todo_label = !empty($label) ? $label : lang("open ToDo's:");

					foreach($todos as $todo)
					{
						if(!$showall && ($i++ > $maxshow))
						{
							break;
						}
						$icons = '';
						foreach($todo['icons'] as $name => $app)
						{
							$icons .= ($icons?' ':'').$GLOBALS['egw']->html->image($app,$name,lang($name),'border="0" width="15" height="15"');
						}
						$class = $class == 'row_on' ? 'row_off' : 'row_on';

						$content .= " <tr class=\"$class\">\n  <td valign=\"top\" width=\"15%\" nowrap>".
							($this->bo->printer_friendly?$icons:$GLOBALS['egw']->html->a_href($icons,$todo['view'])).
							"</td>\n  <td>".($this->printer_friendly?$todo['title']:
							$GLOBALS['egw']->html->a_href($todo['title'],$todo['view']))."</td>\n </tr>\n";
						/**
						 * ToDo: add delete and closing action
						 */
					}
				}
			}
		}
		if (!empty($content))
		{
			return "<table border=\"0\" width=\"100%\">\n$content</table>\n";
		}
		return $todo_label ? '' : false;
	}
		
	/**
	 * Calculates the vertical position based on the time
	 *
	 * workday start- and end-time, is taken into account, as well as timeGrids px_m - minutes per pixel param
	 *
	 * @param int $time in minutes
	 * @return float position in percent
	 */
	function time2pos($time)
	{
		// time before workday => condensed in the first $this->extraRows rows
		if ($this->wd_start > 0 && $time < $this->wd_start)
		{
			$pos = (1 + $this->extraRows * $time / $this->wd_start) * $this->rowHeight;	// 1 for the header
		}
		// time after workday => condensed in the last row
		elseif ($this->wd_end < 24*60 && $time > $this->wd_end+1*$this->granularity_m)
		{
			$pos = 100 - ($this->extraRows * $this->rowHeight * (1 - ($time - $this->wd_end) / (24*60 - $this->wd_end)));
		}
		// time during the workday => 2. row on (= + granularity)
		else
		{
			$pos = $this->rowHeight * (1+$this->extraRows+($time-$this->wd_start)/$this->granularity_m);
		}
		$pos = round($pos,1);

		if ($this->debug > 3) $this->bo->debug_message('uiviews::time2pos(%1)=%2',False,$time,$pos);

		return $pos;
	}

	/**
	 * Calculates the height of a differenc between 2 times
	 *
	 * workday start- and end-time, is taken into account, as well as timeGrids px_m - minutes per pixel param
	 *
	 * @param int $start time in minutes
	 * @param int $end time in minutes
	 * @param int $minimum=0 minimum height
	 * @return float height in percent
	 */
	function times2height($start,$end,$minimum=0)
	{
		$minimum = $this->rowHeigth;
		$height = $this->time2pos($end) - $this->time2pos($start);

		if ($this->debug > 3) $this->bo->debug_message('uiviews::times2height(%1,%2,min=%3)=%4',False,$start,$end,$minimum,$height);

		return $height >= $minimum ? $height : $minimum;
	}

	/**
	 * Creates a grid with rows for the time, columns for (multiple) days containing events
	 *
	 * Uses the dayColWidget to display each day.
	 *
	 * @param $daysEvents array with subarrays of events for each day to show, day as YYYYMMDD as key
	 * @param int $granularity_m=30 granularity in minutes of the rows
	 * @param int $height=400 height of the widget
	 * @param string $indent='' string for correct indention
	 * @param string $title='' title of the time-grid
	 * @param int/array $owner=0 owner of the calendar (default 0 = $this->owner) or array with owner for each column
	 */
	function &timeGridWidget($daysEvents,$granularity_m=30,$height=400,$indent='',$title='',$owner=0)
	{
		if ($this->debug > 1 || $this->debug==='timeGridWidget') $this->bo->debug_message('uiviews::timeGridWidget(events=%1,granularity_m=%2,height=%3,,title=%4)',True,$daysEvents,$granularity_m,$height,$title);

		$this->granularity_m = $granularity_m;
		$this->display_start = $this->wd_start - ($this->extraRows * $this->granularity_m);
		$this->display_end	= $this->wd_end + ($this->extraRows * $this->granularity_m);

		$wd_end = ($this->wd_end === 0 ? 1440 : $this->wd_end);
		$totalDisplayMinutes	= $wd_end - $this->wd_start;
		$this->rowsToDisplay	= ($totalDisplayMinutes/$granularity_m)+2+2*$this->extraRows;
		$this->rowHeight		= round(100/$this->rowsToDisplay,1);

		$html = $indent.'<div class="calTimeGrid" style="height: '.$height.'px;">'."\n";

		$html .= $indent."\t".'<div class="calGridHeader row_on" style="width: 47px; height: '.
			$this->rowHeight.'%;">'.$title."</div>\n";

		$off = false;	// Off-row means a different bgcolor
		$add_links = count($daysEvents) == 1;

		// the hour rows
		for($i=1; $i < $this->rowsToDisplay; $i++)
		{
			$currentTime = $this->display_start + (($i-1) * $this->granularity_m);
			if($this->wd_start <= $currentTime && $this->wd_end >= $currentTime)
			{
				$html .= $indent."\t".'<div class="calTimeRow'.($off ? 'Off row_off' : ' row_on').
					'" style="height: '.$this->rowHeight.'%; top:'. $i*$this->rowHeight .'%;">'."\n";
				$time = $GLOBALS['egw']->common->formattime(sprintf('%02d',$currentTime/60),sprintf('%02d',$currentTime%60));
				if ($add_links) $time = $this->add_link($time,$this->date,(int) ($currentTime/60),$currentTime%60);
				$html .= $indent."\t\t".'<div class="calTimeRowTime">'.$time."</div>\n";
				$html .= $indent."\t</div>\n";	// calTimeRow
				$off = !$off;
			}
		}

		if (is_array($daysEvents) && count($daysEvents))
		{
			$numberOfDays	= count($daysEvents);
			$dayColWidth	= 100/$numberOfDays;

			$dayCols_width = $width - $this->timeRow_width - 1;

			// Lars Kneschke 2005-08-28
			// why do we use a div in a div which has the same height and width???
			// To make IE6 happy!!! Whithout the second div you can't use 
			// style="left: 50px; right: 0px;"
			$html .= $indent."\t".'<div id="calDayCols" class="calDayCols"><div style="width=100%; height: 100%;">'."\n";
			$dayCol_width = $dayCols_width / count($daysEvents);
			$n = 0;
			foreach($daysEvents as $day => $events)
			{
				$short_title = count($daysEvents) > 1;
				$col_owner = $owner;
				if (!is_numeric($day))
				{
					$short_title = $day;
					$day = $this->date;
					$col_owner = $owner[$n];
				}
				$html .= $this->dayColWidget($day,$events,$n*$dayColWidth,
					$dayColWidth,$indent."\t\t",$short_title,++$on_off & 1,$col_owner);
				++$n;
			}
			$html .= $indent."\t</div></div>\n";	// calDayCols
		}
		$html .= $indent."</div>\n";	// calTimeGrid

		return $html;
	}

	/**
	 * Creates (if necessary multiple) columns for the events of a day
	 *
	 * Uses the eventColWidget to display each column.
	 *
	 * @param string/int $day_ymd date as Ymd
	 * @param array $events of events to show
	 * @param int $left start of the widget
	 * @param int $width width of the widget
	 * @param string $indent string for correct indention
	 * @param boolean/string $short_title=True should we add a label (weekday, day) with link to the day-view above each day or string with title
	 * @param boolean $on_off=false start with row_on or row_off, default false=row_off
	 * @param int $owner=0 if != 0 owner to add to the add-event link
	 */
	function dayColWidget($day_ymd,$events,$left,$width,$indent,$short_title=True,$on_off=False,$owner=0)
	{
		if ($this->debug > 1 || $this->debug==='dayColWidget') $this->bo->debug_message('uiviews::dayColWidget(%1,%2,left=%3,width=%4,)',False,$day_ymd,$events,$left,$width);

		$day_start = $this->bo->date2ts((string)$day_ymd);

		// sorting the event into columns with none-overlapping events, the events are already sorted by start-time
		$eventCols = $col_ends = array();
		foreach($events as $event)
		{
			$event['multiday'] = False;
			$event['start_m'] = ($event['start'] - $day_start) / 60;
			if ($event['start_m'] < 0)
			{
				$event['start_m'] = 0;
				$event['multiday'] = True;
			}
			$event['end_m'] = ($event['end'] - $day_start) / 60;
			if ($event['end_m'] >= 24*60)
			{
				$event['end_m'] = 24*60-1;
				$event['multiday'] = True;
			}
			for($c = 0; $event['start_m'] < $col_ends[$c]; ++$c);
			$eventCols[$c][] = $event;
			$col_ends[$c] = $event['end_m'];
		}

		if (count($eventCols))
		{
			/* code to overlay the column, not used at the moment
			$eventCol_dist = $eventCol_width = round($width / count($eventCols));
			$eventCol_min_width = 80;
			if ($eventCol_width < $eventCol_min_width)
			{
				$eventCol_width = $eventCol_dist = $eventCol_min_width;
				if (count($eventCols) > 1)
				{
					$eventCol_dist = round(($width - $eventCol_min_width) / (count($eventCols)-1));
				}
			}*/
			$eventCol_dist = $eventCol_width = round(100 / count($eventCols));
		}

		$html = $indent.'<div id="calColumn'.$this->calColumnCounter++.'" class="calDayCol" style="left: '.$left.
			'%; width: '.$width.'%;">'."\n";

		// Creation of the header-column with date, evtl. holiday-names and a matching background-color
		$ts = $this->bo->date2ts((string)$day_ymd);
		$title = is_bool($short_title) ? (lang(adodb_date('l',$ts)).', '.($short_title ? adodb_date('d.',$ts) : $this->bo->long_date($ts))) : $short_title;
		$day_view = array(
			'menuaction' => 'calendar.uiviews.day',
			'date' => $day_ymd,
		);
		if ($short_title === true)
		{
			$title = $this->html->a_href($title,$day_view,'',
				!isset($this->holidays[$day_ymd])?' title="'.lang('Dayview').'"':'');
		}
		$this->_day_class_holiday($day_ymd,$class,$holidays);
		// the weekday and date
		$html .= $indent."\t".'<div style="height: '. $this->rowHeight .'%" class="calDayColHeader '.$class.'"'.($holidays ? ' title="'.$holidays.'"':'').'>'.
			$title.(!$short_title && $holidays ? ': '.$holidays : '')."</div>\n";

		// adding divs to click on for each row / time-span
		for($counter = 1; $counter < $this->rowsToDisplay; $counter++)
		{
			//print "$counter - ". $counter*$this->rowHeight ."<br>";
			$linkData = array(
				'menuaction'	=>'calendar.uiforms.edit',
				'date'		=> $day_ymd,
				'hour'		=> floor(($this->wd_start + (($counter-$this->extraRows-1)*$this->granularity_m))/60),
				'minute'	=> floor(($this->wd_start + (($counter-$this->extraRows-1)*$this->granularity_m))%60),
			);
			if ($owner) $linkData['owner'] = $owner;
			
			if ($this->html->user_agent != 'msie')	// disable add event for IE, as IE cant manage to get the right div
			{
				$onclick = 'onclick="'.$this->popup($GLOBALS['egw']->link('/index.php',$linkData)).';return false;"';
			}
			$html .= $indent."\t".'<div style="height:'. $this->rowHeight .'%; top: '. $counter*$this->rowHeight .
				'%;" class="calAddEvent" '.$onclick.'></div>'."\n";
		}
		// displaying all event columns of the day
		foreach($eventCols as $n => $eventCol)
		{
			$html .= $this->eventColWidget($eventCol,$n*$eventCol_dist,$eventCol_width,$indent."\t");
		}
		$html .= $indent."</div>\n";	// calDayCol

		return $html;
	}
	
	/**
	 * get the CSS class and holidays for a given day
	 *
	 * @param string $day_ymd date
	 * @param string &$class class to use
	 * @param string &$holidays commaseparted holidays or empty if none
	 * @param boolean $only_weekend=false show only the weekend in header-color, otherwise every second days is shown too
	 */
	function _day_class_holiday($day_ymd,&$class,&$holidays,$only_weekend=false)
	{
		$class = $holidays = '';
		$bday = false;
		if (isset($this->holidays[$day_ymd]))
		{
			foreach($this->holidays[$day_ymd] as $holiday)
			{
				if (isset($holiday['birthyear']))
				{
					$bday = true;
				}
				else
				{
					$class = 'calHoliday';
				}
				$holidays[] = $holiday['name'];
			}
			$holidays = implode(', ',$holidays);
		}
		if (!$class)
		{
			if ($day_ymd == $this->bo->date2string($this->bo->now_su))
			{
				$class = 'calToday';
			}
			else
			{
				$day = (int) date('w',$this->bo->date2ts((string) $day_ymd));
	
				if ($only_weekend)
				{
					$class = $day == 0 || $day == 6 ? 'th' : 'row_off';
				}
				else
				{
					$class = $day & 1 ? 'row_on' : 'th';
				}
			}
		}
		if ($bday) $class .= ' calBirthday';
	}

	/**
	 * Creates colunm for non-overlaping (!) events
	 *
	 * Uses the eventWidget to display each event.
	 *
	 * @param array $events of events to show
	 * @param int $left start of the widget
	 * @param int $width width of the widget
	 * @param string $indent string for correct indention
	 */
	function eventColWidget($events,$left,$width,$indent)
	{
		if ($this->debug > 1 || $this->debug==='eventColWidget') $this->bo->debug_message('uiviews::eventColWidget(%1,left=%2,width=%3,)',False,$events,$left,$width);

		$html = $indent.'<div class="calEventCol" style="left: '.$left.'%; width:'.$width.'%;">'."\n";
		foreach($events as $event)
		{
			$html .= $this->eventWidget($event,$width,$indent."\t");
		}
		$html .= $indent."</div>\n";

		return $html;
	}

	/**
	 * Shows one event
	 *
	 * The display of the event and it's tooltip is done via the event_widget.tpl template
	 *
	 * @param $event array with the data of event to show
	 * @param $width int width of the widget
	 * @param string $indent string for correct indention
	 * @param boolean $return_array=false should an array with keys(tooltip,popup,html) be returned or the complete widget as string
	 * @param string $block='event_widget' template used the render the widget
	 * @return string/array 
	 */
	function eventWidget($event,$width,$indent,$return_array=false,$block='event_widget')
	{
		if ($this->debug > 1 || $this->debug==='eventWidget') $this->bo->debug_message('uiviews::eventWidget(%1,width=%2)',False,$event,$width);

		static $tpl = False;
		if (!$tpl)
		{
			$tpl = $GLOBALS['egw']->template;
			$tpl->set_root($GLOBALS['egw']->common->get_tpl_dir('calendar'));
			$tpl->set_file('event_widget_t','event_widget.tpl');
			$tpl->set_block('event_widget_t','event_widget');
			$tpl->set_block('event_widget_t','event_tooltip');
			$tpl->set_block('event_widget_t','planner_event');
		}

		if ($event['start_m'] == 0 && $event['end_m'] >= 24*60-1)
		{
			$timespan = lang('all day');
		}
		else
		{
			foreach(array($event['start_m'],$event['end_m']) as $minutes)
			{
				$timespan[] = $GLOBALS['egw']->common->formattime(sprintf('%02d',$minutes/60),sprintf('%02d',$minutes%60));
			}
			$timespan = implode(' - ',$timespan);
		}
		$is_private = !$this->bo->check_perms(EGW_ACL_READ,$event);

		$icons = !$is_private ? $this->event_icons($event) : array($this->html->image('calendar','private',lang('private')));
		$cats  = $this->bo->categories($event['category'],$color);

		// these values control varius aspects of the geometry of the eventWidget
		$small_trigger_width = 120 + 20*count($icons);
		$corner_radius=$width > $small_trigger_width ? 10 : 5;
		$header_height=$width > $small_trigger_width ? 19 : 12;	// multi_3 icon has a height of 19=16+2*1padding+1border !
		$height = $this->times2height($event['start_m'],$event['end_m'],$header_height);
		//$body_height = max(0,$height - $header_height - $corner_radius);
		$border=1;
		$headerbgcolor = $color ? $color : '#808080';
		// the body-colors (gradient) are calculated from the headercolor, which depends on the cat of an event
		$bodybgcolor1 = $this->brighter($headerbgcolor,170);
		$bodybgcolor2 = $this->brighter($headerbgcolor,220);
		
		// seperate each participant types
		$part_array = array();
		foreach($this->bo->participants($event) as $part_key => $participant)
		{
			if(is_numeric($part_key))
			{
				$part_array[lang('Participants')][$part_key] = $participant;
			}
			elseif(isset($this->bo->resources[$part_key{0}]))
			{
				 $part_array[((isset($this->bo->resources[$part_key{0}]['participants_header'])) ? $this->bo->resources[$part_key{0}]['participants_header'] : lang($this->bo->resources[$part_key{0}]['app']))][$part_key] = $participant;
			}
		}
		foreach($part_array as $part_group => $participant)
		{
			$participants .= $this->add_nonempty($participant,$part_group,True);
		}
		
		$tpl->set_var(array(
			// event-content, some of it displays only if it really has content or is needed
			'header_icons' => $width > $small_trigger_width ? implode("",$icons) : '',
			'body_icons' => $width > $small_trigger_width ? '' : implode("\n",$icons),
			'icons' => implode("\n",$icons),
			'timespan' => $timespan,
			'header' => !$is_private ? $this->html->htmlspecialchars($event['title']) : lang('private'), // $timespan,
			'title'   => !$is_private ? $this->html->htmlspecialchars($event['title']) : lang('private'),
			'description' => !$is_private ? nl2br($this->html->htmlspecialchars($event['description'])) : '',
			'location'   => !$is_private ? $this->add_nonempty($event['location'],lang('Location')) : '',
			'participants' => $participants,
			'times' => !$event['multiday'] ? $this->add_nonempty($timespan,lang('Time')) :
				$this->add_nonempty($GLOBALS['egw']->common->show_date($event['start']),lang('Start')).
				$this->add_nonempty($GLOBALS['egw']->common->show_date($event['end']),lang('End')),
			'multidaytimes' => !$event['multiday'] ? '' :
				$this->add_nonempty($GLOBALS['egw']->common->show_date($event['start']),lang('Start')).
				$this->add_nonempty($GLOBALS['egw']->common->show_date($event['end']),lang('End')),
			'category' => !$is_private ? $this->add_nonempty($cats,lang('Category')) : '',
			// the tooltip is based on the content of the actual widget, this way it takes no extra bandwidth/volum
//			'tooltip' => $this->html->tooltip(False,False,array('BorderWidth'=>0,'Padding'=>0)),
			// various aspects of the geometry or style
			'corner_radius'  => $corner_radius.'px',
			'header_height' => $header_height.'px',
			//'body_height' => $body_height.'px',
			'height' => $height,
			'width' => ($width-20).'px',
			'border' => $border,
			'bordercolor' => $headerbgcolor,
			'headerbgcolor' => $headerbgcolor,
			'bodybackground' => 'url('.$GLOBALS['egw_info']['server']['webserver_url'].
				'/calendar/inc/gradient.php?color1='.urlencode($bodybgcolor1).'&color2='.urlencode($bodybgcolor2).
				'&width='.$width.') repeat-y '.$bodybgcolor2,
			'Small' => $width > $small_trigger_width ? '' : 'Small',	// to use in css class-names
			// otherwise a click in empty parts of the event, will "click through" and create a new event
		));
		foreach(array(
			'upper_left'=>array('width'=>-$corner_radius,'height'=>$header_height,'border'=>0,'bgcolor'=>$headerbgcolor),
			'upper_right'=>array('width'=>$corner_radius,'height'=>$header_height,'border'=>0,'bgcolor'=>$headerbgcolor),
			'lower_left'=>array('width'=>-$corner_radius,'height'=>-$corner_radius,'border'=>$border,'color'=>$headerbgcolor,'bgcolor'=>$bodybgcolor1),
			'lower_right'=>array('width'=>$corner_radius,'height'=>-$corner_radius,'border'=>$border,'color'=>$headerbgcolor,'bgcolor'=>$bodybgcolor2),
		) as $name => $data)
		{
			$tpl->set_var($name.'_corner',$GLOBALS['egw_info']['server']['webserver_url'].
				'/calendar/inc/round_corners.php?width='.$data['width'].'&height='.$data['height'].
				'&bgcolor='.urlencode($data['bgcolor']).
				(isset($data['color']) ? '&color='.urlencode($data['color']) : '').
				(isset($data['border']) ? '&border='.urlencode($data['border']) : ''));
		}
		$tooltip = $tpl->fp('tooltip','event_tooltip');
		$tpl->set_var('tooltip',$this->html->tooltip($tooltip,False,array('BorderWidth'=>0,'Padding'=>0)));
		$html = $tpl->fp('out',$block);

		$view_link = $GLOBALS['egw']->link('/index.php',array('menuaction'=>'calendar.uiforms.view','cal_id'=>$event['id'],'date'=>$this->bo->date2string($event['start'])));
		$popup = $is_private ? '' : ' onclick="'.$this->popup($view_link).'; return false;"';
		
		if ($return_array)
		{
			return array(
				'tooltip' => $tooltip,
				'popup'   => $popup,
				'html'    => $html,
				'private' => $is_private,
				'color'   => $color,
			);
		}
		//_debug_array($event);
		return $indent.'<div class="calEvent'.($is_private ? 'Private' : '').'" style="top: '.$this->time2pos($event['start_m']).
			'%; height: '.$height.'%;"'.$popup.'>'."\n".$html.$indent."</div>\n";
	}

	function add_nonempty($content,$label,$one_per_line=False)
	{
		if (is_array($content))
		{
			$content = implode($one_per_line ? ",\n" : ', ',$content);
		}
		if (!empty($content))
		{
			return '<span class="calEventLabel">'.$label.'</span>:'.
				($one_per_line ? '<br>' : ' ').
				nl2br($this->html->htmlspecialchars($content)).'<br>';
		}
		return '';
	}

	/**
	* Calculates a brighter color for a given color
	*
	* @param $rgb string color as #rrggbb value
	* @param $decr int value to add to each component, default 64
	* @return string the brighter color
	*/
	function brighter($rgb,$decr=64)
	{
		if (!preg_match('/^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/',$rgb,$components))
		{
			return '#ffffff';
		}
		$brighter = '#';
		for ($i = 1; $i <=3; ++$i)
		{
			$val = hexdec($components[$i]) + $decr;
			if ($val > 255) $val = 255;
			$brighter .= sprintf('%02x',$val);
		}
		//echo "brighter($rgb=".print_r($components,True).")=$brighter</p>\n";
		return $brighter;
	}
	
	
	/**
	 * Creates a planner view: grid with columns for the time and rows for categories or users
	 *
	 * Uses the plannerRowWidget to display rows
	 *
	 * @param array $events events to show
	 * @param mixed $start start-time of the grid
	 * @param mixed $end end-time of the grid
	 * @param boolean/int $by_cat rows by sub-categories of $by_cat (cat_id or 0 for upmost level) or by users (false)
	 * @param string $indent='' string for correct indention
	 * @return string with widget
	 */
	function &plannerWidget($events,$start,$end,$by_cat=0,$indent='')
	{
		$content = $indent.'<div class="plannerWidget">'."\n";
		
		// display the header, containing a headerTitle and multiple headerRows with the scales
		$content .= $indent."\t".'<div class="plannerHeader">'."\n";
		// display the headerTitle
		$title = $by_cat === false ? lang('User') : lang('Category');
		$content .= $indent."\t\t".'<div class="plannerHeaderTitle th">'.$title."</div>\n";
		
		// display the headerRows with the scales
		$content .= $indent."\t\t".'<div class="plannerHeaderRows">'."\n";
		// set start & end to timestamp and first & last to timestamp of 12h midday, to avoid trouble with daylight saving
		foreach(array('start' => 'first','end' => 'last') as $t => $v)
		{
			$$t = $this->bo->date2ts($$t);
			$$v = $this->bo->date2array($$t);
			unset(${$v}['raw']);
			${$v}['hour'] = 12;
			${$v}['minute'] = ${$v}['second'] = 0;
			${$v} = $this->bo->date2ts($$v);
		}
		$days = 1 + (int) round(($last - $first) / DAY_s);	// we have to use round to get the right number if daylight saving changes
		if ($days >= 28)	// display the month scale
		{
			$content .= $this->plannerMonthScale($first,$days,$indent."\t\t\t");
		}
		if ($days >= 5)	// display the week scale
		{
			$content .= $this->plannerWeekScale($first,$days,$indent."\t\t\t");
		}
		$content .= $this->plannerDayScale($first,$days,$indent."\t\t\t");		// day-scale, always displayed
		if ($days <= 7)	// display the hour scale
		{
			$content .= $this->plannerHourScale($start,$days,$indent."\t\t\t");
		}
		$content .= $indent."\t\t</div>\n";	// end of the plannerHeaderRows
		$content .= $indent."\t</div>\n";	// end of the plannerHeader
		
		// sort the events after user or category
		$rows = $sort2label = array();
		if ($by_cat === false)	// planner by user
		{
			$sort2label = $this->_get_planner_users();
		}
		foreach($events as $key => $event)
		{
			if ($by_cat === false)	// planner by user
			{
				foreach($event['participants'] as $sort => $status)
				{
					if (isset($sort2label[$sort]))
					{
						$rows[$sort][] =& $events[$key];
					}
				}
			}
			else	// planner by cat
			{
				foreach($this->_get_planner_cats($event['category'],$sort2label,$sort2color) as $sort)
				{
					if (!is_array($rows[$sort])) $rows[$sort] = array();

					$rows[$sort][] =& $events[$key];
				}
			}
		}
		// display a plannerRowWidget for each row (user or category)
		foreach($sort2label as $sort => $label)
		{
			if (!isset($rows[$sort])) continue;		// dont show empty categories (user-rows get all initialised

			$class = $class == 'row_on' ? 'row_off' : 'row_on';
			$content .= $this->plannerRowWidget(isset($rows[$sort]) ? $rows[$sort] : array(),$start,$end,$label,$class,$indent."\t");
		}
		$content .= $indent."</div>\n";		// end of the plannerWidget
		
		return $content;
	}
	
	/**
	 * get all users to display in the planner_by_user
	 *
	 * @return array with uid => label pairs
	 */
	function _get_planner_users()
	{
		$users = array();
		foreach(explode(',',$this->owner) as $user)
		{
			if (is_numeric($user) && $GLOBALS['egw']->accounts->get_type($user) == 'g')
			{
				foreach((array) $GLOBALS['egw']->accounts->member($user) as $data)
				{
					$user = $data['account_id'];
					if ($this->bo->check_perms(EGW_ACL_READ,0,$user))
					{
						$users[$user] = $this->bo->participant_name($user);
					}
				}
			}
			else
			{
				$users[$user] = $this->bo->participant_name($user);
			}
			asort($users);	
		}
		return $users;
	}
	
	/**
	 * get all categories used as sort criteria for the planner by category
	 * 
	 * the returned cat is as direct sub-category of $this->cat_id or a main (level 1) category if !$this->cat_id
	 *
	 * @param string $cats comma-delimited cat_id's or empty for no cat
	 * @param array &$sort2label labels for the returned cats
	 * @return array with cat_id's
	 */
	function _get_planner_cats($cats,&$sort2label)
	{
		static $cat2sort;
		
		if (!is_array($cat2sort))
		{
			$cat2sort = array();
			foreach((array)$this->cats->return_array('all',0,false,'','','',true) as $data)
			{
				if ($data['parent'] == $this->cat_id || $data['id'] == $this->cat_id)	// cat is a direct sub of $this->cat_id
				{
					$cat2sort[$data['id']] = $data['id'];
					$sort2label[$data['id']] = stripslashes($data['name']);
				}
				elseif(isset($cat2sort[$data['parent']]))	// parent is already in the array => add us with same target
				{
					$cat2sort[$data['id']] = $cat2sort[$data['parent']];
				} 
			}
		}
		$ret = array();
		foreach(!is_array($cats) ? explode(',',$cats) : $cats as $cat)
		{
			if (isset($cat2sort[$cat]) && !in_array($cat2sort[$cat],$ret))
			{
				$ret[] = $cat2sort[$cat];
			}
		}
		if (!count($ret))
		{
			$sort2label[0] = lang('none');
			$ret[] = 0;
		}
		//echo "<p>uiviews::_get_planner_cats($cats=".$this->cats->id2name($cats).") (this->cat_id=$this->cat_id) = ".print_r($ret,true).'='.$this->cats->id2name($ret[0])."</p>\n";
		return $ret;
	}

	/**
	 * Creates month scale for the planner
	 *
	 * @param int $start start-time (12h) of the scale
	 * @param int $days number of days to display
	 * @param string $indent='' string for correct indention
	 * @return string with scale
	 */
	function plannerMonthScale($start,$days,$indent)
	{
		$day_width = round(100 / $days,2);

		$content .= $indent.'<div class="plannerScale">'."\n";
		for($t = $start,$left = 0,$i = 0; $i < $days; $t += $days_in_month*DAY_s,$left += $days_in_month*$day_width,$i += $days_in_month)
		{
			$t_arr = $this->bo->date2array($t);
			unset($t_arr['raw']);	// force recalculation
			unset($t_arr['full']);
			$days_in_month = $this->datetime->days_in_month($t_arr['month'],$t_arr['year']) - ($t_arr['day']-1);
			if ($i + $days_in_month > $days)
			{
				$days_in_month = $days - $i;
			}
			if ($days_in_month > 10)
			{
				$title = lang(date('F',$t)).' '.$t_arr['year'];
				// previous links
				$prev = $t_arr;
				$prev['day'] = 1;
				if ($prev['month']-- <= 1)
				{
					$prev['month'] = 12;
					$prev['year']--;
				}
				if ($this->bo->date2ts($prev) < $start-20*DAY_s)
				{
					$prev['day'] = $this->day;
					$full = $this->bo->date2string($prev);
					if ($this->day >= 15) $prev = $t_arr;		// we stay in the same month
					$prev['day'] = $this->day < 15 ? 15 : 1;
					$half = $this->bo->date2string($prev);
					$title = $this->html->a_href($this->html->image('phpgwpai','first',lang('back one month'),$options=' alt="<<"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => $full,
					)) . ' &nbsp; '.
					$this->html->a_href($this->html->image('phpgwpai','left',lang('back half a month'),$options=' alt="<"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => $half,
					)) . ' &nbsp; '.$title;
				}
				// next links
				$next = $t_arr;
				if ($next['month']++ >= 12)
				{
					$next['month'] = 1;
					$next['year']++;
				}
				// dont show next scales, if there are more then 10 days in the next month or there is no next month
				$days_in_next_month = (int) date('d',$end = $start+$days*DAY_s);
				if ($days_in_next_month <= 10 || date('m',$end) == date('m',$t))
				{
					if ($this->day >= 15) $next = $t_arr;		// we stay in the same month
					$next['day'] = $this->day;
					$full = $this->bo->date2string($next);
					if ($this->day < 15) $next = $t_arr;		// we stay in the same month
					$next['day'] = $this->day < 15 ? 15 : 1;
					$half = $this->bo->date2string($next);
					$title .= ' &nbsp; '.$this->html->a_href($this->html->image('phpgwpai','right',lang('forward half a month'),$options=' alt=">>"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => $half,
					)). ' &nbsp; '.
					$this->html->a_href($this->html->image('phpgwpai','last',lang('forward one month'),$options=' alt=">>"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => $full,
					));
				}
			}
			else
			{
				$title = '&nbsp;';
			}
			$class = $class == 'row_on' ? 'th' : 'row_on';
			$content .= $indent."\t".'<div class="plannerMonthScale '.$class.'" style="left: '.$left.'%; width: '.($day_width*$days_in_month).'%;">'.
				$title."</div>\n";
		}
		$content .= $indent."</div>\n";		// end of plannerScale

		return $content;
	}
	
	/**
	 * Creates a week scale for the planner
	 *
	 * @param int $start start-time (12h) of the scale
	 * @param int $days number of days to display
	 * @param string $indent='' string for correct indention
	 * @return string with scale
	 */
	function plannerWeekScale($start,$days,$indent)
	{
		$week_width = round(100 / $days * ($days <= 7 ? $days : 7),2);

		$content .= $indent.'<div class="plannerScale">'."\n";
		for($t = $start,$left = 0,$i = 0; $i < $days; $t += 7*DAY_s,$left += $week_width,$i += 7)
		{
			$title = lang('Week').' '.date('W',$t);
			if ($days > 7)
			{
				$title = $this->html->a_href($title,array(
					'menuaction' => 'calendar.uiviews.planner',
					'planner_days' => 7,
					'date'       => date('Ymd',$t),
				),false,' title="'.$this->html->htmlspecialchars(lang('Weekview')).'"');
			}
			else
			{
				// prev. week
				$title = $this->html->a_href($this->html->image('phpgwpai','first',lang('previous'),$options=' alt="<<"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',$t-7*DAY_s),
				)) . ' &nbsp; <b>'.$title;
				// next week
				$title .= '</b> &nbsp; '.$this->html->a_href($this->html->image('phpgwpai','last',lang('next'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',$t+7*DAY_s),
				));
			}
			$class = $class == 'row_on' ? 'th' : 'row_on';
			$content .= $indent."\t".'<div class="plannerWeekScale '.$class.'" style="left: '.$left.'%; width: '.$week_width.'%;">'.$title."</div>\n";
		}
		$content .= $indent."</div>\n";		// end of plannerScale

		return $content;
	}
	
	/**
	 * Creates day scale for the planner
	 *
	 * @param int $start start-time (12h) of the scale
	 * @param int $days number of days to display
	 * @param string $indent='' string for correct indention
	 * @return string with scale
	 */
	function plannerDayScale($start,$days,$indent)
	{
		$day_width = round(100 / $days,2);

		$content .= $indent.'<div class="plannerScale'.($days > 3 ? 'Day' : '').'">'."\n";
		for($t = $start,$left = 0,$i = 0; $i < $days; $t += DAY_s,$left += $day_width,++$i)
		{
			$this->_day_class_holiday($this->bo->date2string($t),$class,$holidays,$days > 7);

			if ($days <= 3)
			{
				$title = '<b>'.lang(date('l',$t)).', '.date('j',$t).'. '.lang(date('F',$t)).'</b>';
			}
			elseif ($days <= 7)
			{
				$title = lang(date('l',$t)).'<br />'.date('j',$t).'. '.lang(date('F',$t));
			}
			else
			{
				$title = substr(lang(date('D',$t)),0,2).'<br />'.date('j',$t);	
			}
			if ($days > 1)
			{
				$title = $this->html->a_href($title,array(
					'menuaction'   => 'calendar.uiviews.planner',
					'planner_days' => 1,
					'date'         => date('Ymd',$t),
				),false,strstr($class,'calHoliday') || strstr($class,'calBirthday') ? '' : ' title="'.$this->html->htmlspecialchars(lang('Dayview')).'"');
			}
			if ($days < 5)
			{
				if (!$i)	// prev. day only for the first day
				{
					$title = $this->html->a_href($this->html->image('phpgwpai','first',lang('previous'),$options=' alt="<<"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => date('Ymd',$start-DAY_s),
					)) . ' &nbsp; '.$title;
				}
				if ($i == $days-1)	// next day only for the last day
				{
					$title .= ' &nbsp; '.$this->html->a_href($this->html->image('phpgwpai','last',lang('next'),$options=' alt=">>"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => date('Ymd',$start+DAY_s),
					));
				}
			}
			$content .= $indent."\t".'<div class="plannerDayScale '.$class.'" style="left: '.$left.'%; width: '.$day_width.'%;"'.
				($holidays ? ' title="'.$this->html->htmlspecialchars($holidays).'"' : '').'>'.$title."</div>\n";
		}
		$content .= $indent."</div>\n";		// end of plannerScale

		return $content;
	}
	
	/**
	 * Creates hour scale for the planner
	 *
	 * @param int $start start-time (12h) of the scale
	 * @param int $days number of days to display
	 * @param string $indent='' string for correct indention
	 * @return string with scale
	 */
	function plannerHourScale($start,$days,$indent)
	{
		foreach(array(1,2,3,4,6,8,12) as $d)	// numbers dividing 24 without rest
		{
			if ($d > $days) break;
			$decr = $d;
		}
		$hours = $days * 24;
		if ($days == 1)			// for a single day we calculate the hours of a days, to take into account daylight saving changes (23 or 25 hours)
		{
			$t_arr = $this->bo->date2array($start);
			unset($t_arr['raw']);
			$t_arr['hour'] = $t_arr['minute'] = $t_arr['second'] = 0;
			$s = $this->bo->date2ts($t_arr);
			$t_arr['hour'] = 23; $t_arr['minute'] = $t_arr['second'] = 59;
			$hours = ($this->bo->date2ts($t_arr) - $s) / HOUR_s;
		}
		$cell_width = round(100 / $hours * $decr,2);
		
		$content .= $indent.'<div class="plannerScale">'."\n";
		for($t = $start,$left = 0,$i = 0; $i < $hours; $t += $decr*HOUR_s,$left += $cell_width,$i += $decr)
		{
			$title = date($this->cal_prefs['timeformat'] == 12 ? 'ha' : 'H',$t);

			$class = $class == 'row_on' ? 'th' : 'row_on';
			$content .= $indent."\t".'<div class="plannerHourScale '.$class.'" style="left: '.$left.'%; width: '.($cell_width).'%;">'.$title."</div>\n";
		}
		$content .= $indent."</div>\n";		// end of plannerScale

		return $content;
	}
	
	/**
	 * Creates a row for one user or category, with a header (user or category name) and (multiple) rows with non-overlapping events
	 *
	 * Uses the eventRowWidget to display a row of non-overlapping events
	 *
	 * @param array $events to show
	 * @param int $start start-time of the row
	 * @param int $end end-time of the row
	 * @param string $header user or category name for the row-header
	 * @param string $class additional css class for the row
	 * @param string $indent='' string for correct indention
	 * @return string with widget
	 */
	function plannerRowWidget($events,$start,$end,$header,$class,$indent='')
	{
		$content = $indent.'<div class="plannerRowWidget '.$class.'">'."\n";

		// display the row-header
		$content .= $indent."\t".'<div class="plannerRowHeader">'.$header."</div>\n";
		
		// sorting the events in non-overlapping rows
		$rows = array(array());
		$row_end = array();
		foreach($events as $n => $event)
		{
			for($row = 0; (int) $row_end[$row] > $event['start']; ++$row);	// find a "free" row (no other event)
			$rows[$row][] =& $events[$n];
			$row_end[$row] = $event['end'];
		}
		//echo $header; _debug_array($rows);
		// display the rows
		$content .= $indent."\t".'<div class="eventRows">'."\n";
		foreach($rows as $row)
		{
			$content .= $this->eventRowWidget($row,$start,$end,$indent."\t\t");
		}
		$content .= $indent."\t</div>\n";	// end of the eventRows

		$content .= $indent."</div>\n";		// end of the plannerRowWidget
		
		return $content;
	}

	/**
	 * Creates a row with non-overlapping events
	 *
	 * Uses the plannerEventWidget to display the events
	 *
	 * @param array $events non-overlapping events to show
	 * @param int $start start-time of the row
	 * @param int $end end-time of the row
	 * @param string $indent='' string for correct indention
	 * @return string with widget
	 */
	function eventRowWidget($events,$start,$end,$indent='')
	{
		$content = $indent.'<div class="eventRowWidget">'."\n";
		
		foreach($events as $event)
		{
			$content .= $this->plannerEventWidget($event,$start,$end,$indent."\t");
		}
		$content .= $indent."</div>\n";
		
		return $content;
	}
	
	/**
	 * Calculate a time-dependent position in the planner
	 *
	 * We use a non-linear scale in the planner monthview, which shows the workday start or end
	 * as start or end of the whole day. This improves the resolution a bit.
	 *
	 * @param int $time
	 * @param int $start start-time of the planner
	 * @param int $end end-time of the planner
	 * @return float percentage position between 0-100
	 */
	function _planner_pos($time,$start,$end)
	{
		if ($time <= $start) return 0;	// we are left of our scale
		if ($time >= $end) return 100;	// we are right of our scale
		
		if ($this->planner_days)
		{
			$percent = ($time - $start) / ($end - $start);
		}
		else	// monthview
		{
			$t_arr = $this->bo->date2array($time);
			$day_start = $this->bo->date2ts((string)$t_arr['full']);
			$percent = ($day_start - $start) / ($end - $start);
	
			$time_of_day = 60 * $t_arr['hour'] + $t_arr['minute'];
			if ($time_of_day >= $this->wd_start)
			{
				if ($time_of_day > $this->wd_end)
				{
					$day_percentage = 1;
				}
				else
				{
					$day_percentage = ($time_of_day-$this->wd_start) / ($this->wd_end - $this->wd_start);		// between 0 and 1
				}
				$days = ($end - $start) / DAY_s;
				$percent += $day_percentage / $days;
			}
		}
		$percent = round(100 * $percent,2);
		
		//echo "<p>_planner_pos(".date('Y-m-d H:i',$time).', '.date('Y-m-d H:i',$start).', '.date('Y-m-d H:i',$end).") = $percent</p>\n";
		return $percent;
	}

	/**
	 * Displays one event for the planner, using the eventWidget of the other views
	 *
	 * @param array $event
	 * @param int $start start-time of the planner
	 * @param int $end end-time of the planner
	 * @return string with widget
	 */
	function plannerEventWidget($event,$start,$end,$indent='')
	{
		$event['multiday'] = true;	// otherwise eventWidgets displays only the time and expects start_m to be set
		$data = $this->eventWidget($event,200,$indent,true,'planner_event');
		
		$left = $this->_planner_pos($event['start'],$start,$end);
		$width = $this->_planner_pos($event['end'],$start,$end) - $left;
		$color = $data['color'] ? $data['color'] : 'gray';
		
		return $indent.'<div class="plannerEvent'.($data['private'] ? 'Private' : '').'" style="left: '.$left.
			'%; width: '.$width.'%; background-color: '.$color.';"'.$data['popup'].' '.
			$this->html->tooltip($data['tooltip'],False,array('BorderWidth'=>0,'Padding'=>0)).'>'."\n".$data['html'].$indent."</div>\n";
	}
}
