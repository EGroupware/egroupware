<?php
/**************************************************************************\
* eGroupWare - Calendar - Views and Widgets                                *
* http://www.egroupware.org                                                *
* Written and (c) 2004 by Ralf Becker <RalfBecker@outdoor-training.de>     *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

include_once(PHPGW_INCLUDE_ROOT . '/calendar/inc/class.uical.inc.php');

/**
 * Class to generate the calendar views and the necesary widgets
 *
 * @package calendar
 * @author RalfBecker@outdoor-training.de
 * @license GPL
 */
class uiviews extends uical
{
	var $public_functions = array(
		'day'   => True,
		'week'  => True,
		'month' => True,
		'test'  => True,
	);
	/**
	 * @var $debug mixed integer level or string function- or widget-name
	 */
	var $debug=False;

	/**
	 * @var minimum width for an event
	 */
	var $eventCol_min_width = 80;

	var $timeRow_width = 40;

	/**
	 * Constructor
	 */
	function uiviews()
	{
		$this->width = $this->establish_width();

		$this->uical();	// call the parent's constructor

		$GLOBALS['phpgw_info']['flags']['nonavbar'] = False;
		$app_header = array(
			'calendar.uiviews.day' => lang('Dayview'),
			'calendar.uiviews.week' => lang('Weekview'),
			'calendar.uiviews.month' => lang('Monthview'),
		);
		$GLOBALS['phpgw_info']['flags']['app_header'] = $GLOBALS['phpgw_info']['apps']['calendar']['title'].
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
	 * Displays the monthview or a multiple week-view
	 */
	function month($weeks=0)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(weeks=%1) date=%2',True,$weeks,$this->date);

		$first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day=1);
		if ($weeks)
		{
			$last = $first + $weeks * 7 * DAY_s - 1;
		}
		else
		{
			$last = $this->datetime->get_weekday_start($this->year,$this->month,
				$days_in_month=$this->datetime->days_in_month($this->month,$this->year));
			$last += WEEK_s - 1;
		}
		if ($this->debug > 0)$this->bo->debug_message('uiviews::month(%1) date=%2: first=%3, last=%4',False,$weeks,$this->date,$this->bo->date2string($first),$this->bo->date2string($last));

		$GLOBALS['phpgw_info']['flags']['app_header'] .= ': '.lang(date('F',$this->bo->date2ts($this->date))).' '.$this->year;
		$GLOBALS['phpgw']->common->phpgw_header();

		$search_params = $this->search_params;

		$days = $this->bo->search(array(
			'start'   => $first,
			'end'     => $last,
		)+$this->search_params);

		for ($week_start = $first; $week_start < $last; $week_start += WEEK_s)
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
			$title = lang('Wk').' '.date('W',$week_start);
			$title = $this->html->a_href($title,$week_view,'',' title="'.lang('Weekview').'"');

			echo $this->timeGridWidget($week,max($this->width,7*$this->eventCol_min_width+$this->timeRow_width),60,5,'',$title);
		}
	}

	/**
	 * Displays the weekview, with 5 or 7 days
	 *
	 * @param int $days=0 number of days to show, if 0 (default) the value from the URL or the prefs is used
	 */
	function week($days=0)
	{
		if (!$days)
		{
			$days = isset($_GET['days']) ? $_GET['days'] : $this->cal_prefs['days_in_weekview'];
			if ($days != 5) $days = 7;
			if ($days != $this->cal_prefs['days_in_weekview'])	// save the preference
			{
				$GLOBALS['phpgw']->preferences->add('calendar','days_in_weekview',$days);
				$GLOBALS['phpgw']->preferences->save_repository();
				$this->cal_prefs['days_in_weekview'] = $days;
			}
		}
		if ($this->debug > 0) $this->bo->debug_message('uiviews::week(days=%1) date=%2',True,$days,$this->date);

		$wd_start = $first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
		if ($days == 5)		// no weekend-days
		{
			switch($this->cal_prefs['weekdaystarts'])
			{
				case 'Saturday':
					$first += DAY_s;
					// fall through
				case 'Sunday':
					$first += DAY_s;
					break;
			}
		}
		//echo "<p>weekdaystarts='".$this->cal_prefs['weekdaystarts']."', get_weekday_start($this->year,$this->month,$this->day)=".date('l Y-m-d',$wd_start).", first=".date('l Y-m-d',$first)."</p>\n";
		$last = $first + $days * DAY_s - 1;

		$GLOBALS['phpgw_info']['flags']['app_header'] .= ': '.lang('Wk').' '.date('W',$first).
			': '.$this->bo->long_date($first,$last);
		$GLOBALS['phpgw']->common->phpgw_header();

		$search_params = array(
				'start'   => $first,
				'end'     => $last,
			) + $this->search_params;

		$users = $this->search_params['users'];
		if (!is_array($users)) $users = array($users);
		
		foreach($users as $user)
		{
			$search_params['users'] = $user;
			echo $this->timeGridWidget($this->bo->search($search_params),
				max($this->width,$days*$this->eventCol_min_width+$this->timeRow_width),
				count($users) * $this->cal_prefs['interval'],count($users) * 1.7,'',
				count($users) > 1 ? $GLOBALS['phpgw']->common->grab_owner_name($user) : '');
		}
	}

	/**
	 * Displays the dayview
	 */
	function day()
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::day() date=%1',True,$this->date);

		$ts = $this->bo->date2ts((string)$this->date);
		$GLOBALS['phpgw_info']['flags']['app_header'] .= ': '.lang(date('l',$ts)).', '.$this->bo->long_date($ts);
		$GLOBALS['phpgw']->common->phpgw_header();

		$todos = $this->get_todos(&$todo_label);

		echo $this->html->table(array(0 => array(
			$this->timeGridWidget($this->bo->search($this->search_params),$this->width-250,$this->cal_prefs['interval'],1.7),
			$this->html->div(
				$this->html->div($todo_label,'','calDayTodosHeader th')."\n".
				$this->html->div($todos,'','calDayTodosTable'),
			'','calDayTodos')
		),'.0' => 'valign="top"'),'width="100%"');
	}

	/**
	 * Query the open ToDo's via a hook from InfoLog or any other 'calendar_include_todos' provider
	 *
	 * @param array/string $todo_label label for the todo-box or array with 2 values: the label and a boolean show_all
	 *	On return $todo_label contains the label for the todo-box
	 * @return string html with a table of open todo's
	 */
	function get_todos(&$todo_label)
	{
		$todos_from_hook = $GLOBALS['phpgw']->hooks->process(array(
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
		$maxshow = (int)$GLOBALS['phpgw_info']['user']['preferences']['infolog']['mainscreen_maxshow'];
		if($maxshow <= 0)
		{
			$maxshow=10;
		}
		//print_debug("get_todos(): label=$label; showall=$showall; max=$maxshow");

		$content = $todo_label = '';
		if (is_array($todos_from_hook) && count($todos_from_hook))
		{
			$todo_label = !empty($label) ? $label : lang("open ToDo's:");

			foreach($todos_from_hook as $todos)
			{
				$i = 0;
				if (is_array($todos) && count($todos))
				{
					foreach($todos as $todo)
					{
						if(!$showall && ($i++ > $maxshow))
						{
							break;
						}
						$icons = '';
						foreach($todo['icons'] as $name => $app)
						{
							$icons .= ($icons?' ':'').$GLOBALS['phpgw']->html->image($app,$name,lang($name),'border="0" width="15" height="15"');
						}
						$class = $class == 'row_on' ? 'row_off' : 'row_on';

						$content .= " <tr class=\"$class\">\n  <td valign=\"top\" width=\"15%\" nowrap>".
							($this->bo->printer_friendly?$icons:$GLOBALS['phpgw']->html->a_href($icons,$todo['view'])).
							"</td>\n  <td>".($this->printer_friendly?$todo['title']:
							$GLOBALS['phpgw']->html->a_href($todo['title'],$todo['view']))."</td>\n </tr>\n";
					}
				}
			}
		}
		if (!empty($content))
		{
			return "<table border=\"0\" width=\"100%\">\n$content</table>\n";
		}
		return False;
	}
		
	/**
	 * Calculates the vertical position based on the time
	 *
	 * workday start- and end-time, is taken into account, as well as timeGrids px_m - minutes per pixel param
	 * @param int $time in minutes
	 * @return int position in pixels
	 */
	function time2pos($time)
	{
		// time before workday => condensed in the first row
		if ($this->wd_start > 0 && $time < $this->wd_start)
		{
			$pos = round($time / $this->px_m / $this->wd_start);
		}
		// time after workday => condensed in the last row
		elseif ($this->wd_end < 24*60 && $time > $this->wd_end+2*$this->granularity_m)
		{
			$pos = $this->time2pos($this->wd_end+2*$this->granularity_m) +
				@round(($time - ($this->wd_end+2*$this->granularity_m)) / $this->px_m /
				(24*60 - ($this->wd_end+2*$this->granularity_m)));
		}
		// time during the workday => 2. row on (= + granularity)
		else
		{
			$pos = round(($time - $this->wd_start + $this->granularity_m) / $this->px_m);
		}
		if ($this->debug > 3) $this->bo->debug_message('uiviews::time2pos(%1)=%2',False,$time,$pos);

		return $pos;
	}

	/**
	 * Calculates the height of a differenc between 2 times
	 *
	 * workday start- and end-time, is taken into account, as well as timeGrids px_m - minutes per pixel param
	 * @param int $start time in minutes
	 * @param int $end time in minutes
	 * @param int $minimum=0 minimum height
	 * @return int height in pixel
	 */
	function times2height($start,$end,$minimum=0)
	{
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
	 * @param int $width width of the widget
	 * @param int $granularity_m granularity in minutes of the rows
	 * @param int/float $px_m minutes per pixel - pixel in minutes ;-)
	 * @param string $indent string for correct indention
	 * @param string $title title of the time-grid
	 */
	function timeGridWidget($daysEvents,$width,$granularity_m=30,$px_m=1.7,$indent='',$title='')
	{
		// Get Owner/Participants
		$_session_data = $GLOBALS['phpgw']->session->appsession("session_data", "calendar");
		$participants = $_session_data["owner"];
		unset($_session_data);

		if ($this->debug > 1 || $this->debug==='timeGridWidget') $this->bo->debug_message('uiviews::timeGridWidget(events=%1,width=%2,granularity_m=%3,px_m=%4,)',True,$daysEvents,$width,$granularity_m,$px_m);

		$this->px_m = $px_m;	// for time2pos()
		$this->granularity_m = $granularity_m;

		$html = $indent.'<div class="calTimeGrid" style="width: '.$width.'px;">'."\n";

		if ($title)
		{
			$html .= $indent."\t".'<div class="calGridHeader">'.$title."</div>\n";
		}
		$off = True;	// Off-row means a different bgcolor
		for ($t = 0; $t < 24*60; $t += $inc)
		{
			$inc = $granularity_m;
			if (!$t)
			{
				$inc = $this->wd_start;
			}
			elseif ($t > $this->wd_end)
			{
				$inc = 24*60 - $this->wd_end;
			}
			$html .= $indent."\t".'<div class="calTimeRowOff'.($off ? ' row_off' : ' row_on').
				'" style="height: '.($this->times2height($t,$t + $inc)).'px;">'."\n";

			$add_links = count($daysEvents) == 1 && $this->bo->check_perms(PHPGW_ACL_ADD,0,$this->owner);
			$add = array(
				'menuaction' => 'calendar.uicalendar.add',
				'date' => $this->date,
				'owner' => $participants,
			);
			if ($t >= $this->wd_start && $t <= $this->wd_end)
			{
				$time = $GLOBALS['phpgw']->common->formattime(sprintf('%02d',$t/60),sprintf('%02d',$t%60));
				if ($add_links)
				{
					$add['hour'] = (int) ($t/60);
					$add['minute'] = $t%60;
					$time = $this->html->a_href($time,$add,'',' title="'.lang('Add').'"');
				}
				$html .= $indent."\t\t".'<div class="calTimeRowTime">'.$time."</div>\n";
			}
			$html .= $indent."\t</div>\n";	// calTimeRow

			$off = !$off;
		}

		if (is_array($daysEvents) && count($daysEvents))
		{
			$dayCols_width = $width - $this->timeRow_width - 1;
			$html .= $indent."\t".'<div class="calDayCols" style="left: '.$this->timeRow_width.'px; width: '.$dayCols_width.'px;">'."\n";
			$dayCol_width = $dayCols_width / count($daysEvents);
			$n = 0;
			foreach($daysEvents as $day => $events)
			{
				$html .= $this->dayColWidget($day,$events,(int)($n*$dayCol_width),(int)$dayCol_width,$indent."\t\t",count($daysEvents) != 1,++$on_off & 1);
				++$n;
			}
			$html .= $indent."\t</div>\n";	// calDayCols
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
	 * @param boolean $short_title should we add a label (weekday, day) with link to the day-view above each day
	 * @param boolean $on_off start with row_on or row_off, default false=row_off
	 */
	function dayColWidget($day_ymd,$events,$left,$width,$indent,$short_title=True,$on_off=False)
	{
		if ($this->debug > 1 || $this->debug==='dayColWidget') $this->bo->debug_message('uiviews::dayColWidget(%1,%2,left=%3,width=%4,)',False,$day_ymd,$events,$left,$width);

		$day_start = $this->bo->date2ts((string)$day_ymd);

		// sorting the event into columns with none-overlapping events, the events are already sorted by start-time
		$eventCols = $col_ends = array();
		foreach($events as $event)
		{
			$event['multiday'] = False;
			$event['start_m'] = ($this->bo->date2ts($event['start']) - $day_start) / 60;
			if ($event['start_m'] < 0)
			{
				$event['start_m'] = 0;
				$event['multiday'] = True;
			}
			$event['end_m'] = ($this->bo->date2ts($event['end']) - $day_start) / 60;
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
			$eventCol_dist = $eventCol_width = round($width / count($eventCols));
			$eventCol_min_width = 80;
			if ($eventCol_width < $eventCol_min_width)
			{
				$eventCol_width = $eventCol_dist = $eventCol_min_width;
				if (count($eventCols) > 1)
				{
					$eventCol_dist = round(($width - $eventCol_min_width) / (count($eventCols)-1));
				}
			}
		}

		$html = $indent.'<div class="calDayCol" style="left: '.$left.'px; width: '.$width.'px;">'."\n";

		// Creation of the header-column with date, evtl. holiday-names and a matching background-color
		$ts = $this->bo->date2ts((string)$day_ymd);
		$title = lang(date('l',$ts)).', '.($short_title ? date('d.',$ts) : $this->bo->long_date($ts));
		$day_view = array(
			'menuaction' => 'calendar.uiviews.day',
			'date' => $day_ymd,
		);
		if ($short_title)
		{
			$title = $this->html->a_href($title,$day_view,'',
				!isset($this->holidays[$day_ymd])?' title="'.lang('Dayview').'"':'');
		}
		if (isset($this->holidays[$day_ymd]))
		{
			$class = 'calHoliday';
			foreach($this->holidays[$day_ymd] as $holiday)
			{
				$holidays[] = $holiday['name'];
			}
			$holidays = implode(', ',$holidays);
		}
		elseif ($day_ymd == $this->bo->date2string($this->bo->now_su))
		{
			$class = 'calToday';
		}
		else
		{
			$class = $on_off ? 'row_on' : 'row_off';
		}
		$html .= $indent."\t".'<div class="calDayColHeader '.$class.'"'.($holidays ? ' title="'.$holidays.'"':'').'>'.
			$title.(!$short_title && $holidays ? ': '.$holidays : '')."</div>\n";

		foreach($eventCols as $n => $eventCol)
		{
			$html .= $this->eventColWidget($eventCol,$n*$eventCol_dist,$eventCol_width,$indent."\t");
		}
		$html .= $indent."</div>\n";	// calDayCol

		return $html;
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

		$html = $indent.'<div class="calEventCol" style="left: '.((int)$left).'px; width: '.((int)$width).'px;">'."\n";
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
	 */
	function eventWidget($event,$width,$indent)
	{
		if ($this->debug > 1 || $this->debug==='eventWidget') $this->bo->debug_message('uiviews::eventWidget(%1)',False,$event);

		static $tpl = False;
		if (!$tpl)
		{
			$tpl = $GLOBALS['phpgw']->template;
			$tpl->set_file('event_widget_t','event_widget.tpl');
			$tpl->set_block('event_widget_t','event_widget');
			$tpl->set_block('event_widget_t','event_tooltip');
		}

		if ($event['start_m'] == 0 && $event['end_m'] == 24*60-1)
		{
			$timespan = lang('all day');
		}
		else
		{
			foreach(array($event['start_m'],$event['end_m']) as $minutes)
			{
				$timespan[] = $GLOBALS['phpgw']->common->formattime(sprintf('%02d',$minutes/60),sprintf('%02d',$minutes%60));
			}
			$timespan = implode(' - ',$timespan);
		}
		$is_private = !$this->bo->check_perms(PHPGW_ACL_READ,$event);

		$icons = !$is_private ? $this->event_icons($event) : array($this->html->image('calendar','private',lang('private')));
		$cats  = $this->bo->categories($event['category'],$color);

		// these values control varius aspects of the geometry of the eventWidget
		$small_trigger_width = 120 + 20*count($icons);
		$corner_radius=$width > $small_trigger_width ? 10 : 5;
		$header_height=$width > $small_trigger_width ? 19 : 12;	// multi_3 icon has a height of 19=16+2*1padding+1border !
		$height = $this->times2height($event['start_m'],$event['end_m'],$header_height);
		$body_height = max(0,$height - $header_height - $corner_radius);
		$border=1;
		$headerbgcolor = $color ? $color : '#808080';
		// the body-colors (gradient) are calculated from the headercolor, which depends on the cat of an event
		$bodybgcolor1 = $this->brighter($headerbgcolor,170);
		$bodybgcolor2 = $this->brighter($headerbgcolor,220);
		
		$tpl->set_var(array(
			// event-content, some of it displays only if it really has content or is needed
			'header_icons' => $width > $small_trigger_width ? implode("",$icons) : '',
			'body_icons' => $width > $small_trigger_width ? '' : implode("\n",$icons).'<br>',
			'icons' => implode("\n",$icons),
			'header' => $timespan,
			'title'   => !$is_private ? $this->html->htmlspecialchars($event['title']) : lang('private'),
			'description' => !$is_private ? nl2br($this->html->htmlspecialchars($event['description'])) : '',
			'location'   => !$is_private ? $this->add_nonempty($event['location'],lang('Location')) : '',
			'participants' => count($event['participants']) == 1 && isset($event['participants'][$this->user]) || $is_private ? '' :
				$this->add_nonempty($this->bo->participants($event['participants']),lang('Participants'),True),
			'multidaytimes' => !$event['multiday'] ? '' :
				$this->add_nonempty($GLOBALS['phpgw']->common->show_date($this->bo->date2ts($event['start'])),lang('Start Date/Time')).
				$this->add_nonempty($GLOBALS['phpgw']->common->show_date($this->bo->date2ts($event['end'])),lang('End Date/Time')),
			'category' => !$is_private ? $this->add_nonempty($cats,lang('Category')) : '',
			// the tooltip is based on the content of the actual widget, this way it takes no extra bandwidth/volum
//			'tooltip' => $this->html->tooltip(False,False,array('BorderWidth'=>0,'Padding'=>0)),
			// various aspects of the geometry or style
			'corner_radius'  => $corner_radius.'px',
			'header_height' => $header_height.'px',
			'body_height' => $body_height.'px',
			'height' => $height,
			'width' => ($width-20).'px',
			'border' => $border,
			'bordercolor' => $headerbgcolor,
			'headerbgcolor' => $headerbgcolor,
			'bodybackground' => 'url('.$GLOBALS['phpgw_info']['server']['webserver_url'].
				'/calendar/inc/gradient.php?color1='.urlencode($bodybgcolor1).'&color2='.urlencode($bodybgcolor2).
				'&width='.$width.') repeat-y '.$bodybgcolor2,
			'Small' => $width > $small_trigger_width ? '' : 'Small',	// to use in css class-names
		));
		foreach(array(
			'upper_left'=>array('width'=>-$corner_radius,'height'=>$header_height,'border'=>0,'bgcolor'=>$headerbgcolor),
			'upper_right'=>array('width'=>$corner_radius,'height'=>$header_height,'border'=>0,'bgcolor'=>$headerbgcolor),
			'lower_left'=>array('width'=>-$corner_radius,'height'=>-$corner_radius,'border'=>$border,'color'=>$headerbgcolor,'bgcolor'=>$bodybgcolor1),
			'lower_right'=>array('width'=>$corner_radius,'height'=>-$corner_radius,'border'=>$border,'color'=>$headerbgcolor,'bgcolor'=>$bodybgcolor2),
		) as $name => $data)
		{
			$tpl->set_var($name.'_corner',$GLOBALS['phpgw_info']['server']['webserver_url'].
				'/calendar/inc/round_corners.php?width='.$data['width'].'&height='.$data['height'].
				'&bgcolor='.urlencode($data['bgcolor']).
				(isset($data['color']) ? '&color='.urlencode($data['color']) : '').
				(isset($data['border']) ? '&border='.urlencode($data['border']) : ''));
		}
		$tooltip = $tpl->fp('tooltip','event_tooltip');
		$tpl->set_var('tooltip',$this->html->tooltip($tooltip,False,array('BorderWidth'=>0,'Padding'=>0)));
		$html = $tpl->fp('out','event_widget');

		$view_link = $GLOBALS['phpgw']->link('/index.php',array('menuaction'=>'calendar.uicalendar.view','cal_id'=>$event['id'],'date'=>$event['start']['full']));

		return $indent.'<div class="calEvent" style="top: '.$this->time2pos($event['start_m']).'px; height: '.
			$height.'px;" onclick="document.location=\''.$view_link.'\';">'."\n".
			$html.$indent."</div>\n";
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
	 * trys to figure out the inner window width from the browser and installs an onResize handler
	 *
	 * If no $_REQUEST[windowInnerWidth] is present, the pages get reloaded with some javascript, which sets
	 * the width in the URL. If a width could be determined, an onResize handler gets installed, which refreshes
	 * the page, with the new width set in the URL.
	 * If we are allowed to set cookies, we additionaly set a cookie with the width, to save the additonal reload.
	 * An onLoad handler checks if we operate with the correct width, or reloads the page if not.
	 */
	function establish_width()
	{
		if (!isset($_REQUEST['windowInnerWidth']))	// we neither have a cookie nor an url-var, get one ...
		{
?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=<?php echo $GLOBALS['phpgw']->translation->charset(); ?>">
<meta http-equiv="refresh" content="1; URL=<?php echo $_SERVER['PHP_SELF'].'?'.($_SERVER['QUERY_STRING'] ? $_SERVER['QUERY_STRING'].'&':'').'windowInnerWidth=Off'; ?>">
</head>
<body onLoad="location = location + (location.search.length ? '&' : '?') + 'windowInnerWidth=' + (window.innerWidth ? window.innerWidth : document.body.clientWidth);">
<noscript>
<p><?php echo lang('Determining window width ...'); ?></p>
<p><?php echo lang('Needs javascript to be enabled !!!'); ?></p>
</noscript>
</body>
</html>
<?php
			$GLOBALS['phpgw']->common->phpgw_exit();
		}

		$width = (int) $_GET['windowInnerWidth'] ? $_GET['windowInnerWidth'] : $_COOKIE['windowInnerWidth'];

		if ($GLOBALS['phpgw_info']['server']['usecookies'])
		{
			$GLOBALS['phpgw']->session->phpgw_setcookie('windowInnerWidth',$width);
		}
		if ($width)
		{
			$GLOBALS['phpgw_info']['flags']['java_script'] = '
<script type="text/javascript">
	    
function MM_findObj(n, d) { //v4.0

  var p,i,x;  if(!d) d=document; if((p=n.indexOf("?"))>0&&parent.frames.length) {
  
      d=parent.frames[n.substring(p+1)].document; n=n.substring(0,p);}
      
        if(!(x=d[n])&&d.all) x=d.all[n]; for (i=0;!x&&i<d.forms.length;i++) x=d.forms[i][n];
	
	  for(i=0;!x&&d.layers&&i<d.layers.length;i++) x=MM_findObj(n,d.layers[i].document);
	  
	    if(!x && document.getElementById) x=document.getElementById(n); return x;
	    
	    }
function MM_showHideLayers() { //v3.0

  var i,p,v,obj,args=MM_showHideLayers.arguments;
  
    for (i=0; i<(args.length-2); i+=3) if ((obj=MM_findObj(args[i]))!=null) { v=args[i+2];
    
        if (obj.style) { obj=obj.style; v=(v==\'show\')?\'visible\':(v=\'hide\')?\'hidden\':v; }
	
	    obj.visibility=v; }
	    
	    }

function reload_inner_width() {
	var width = window.innerWidth ? window.innerWidth : document.body.clientWidth;

	MM_showHideLayers(\'processing\',\'\',\'show\') ;	
	
	if (location.search.indexOf("windowInnerWidth=") < 0)
	{
		location = location+(location.search.length ? "&" : "?")+"windowInnerWidth="+width;
	}
	else
	{
		var loc = location.toString();
		location = loc.replace(/^(.*windowInnerWidth=)[0-9]*(.*)$/,"$1"+width+"$2");
	}
	
}
</script>
';
			if (!is_object($GLOBALS['phpgw']->js))
			{
				$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
			}
			$GLOBALS['phpgw']->js->set_onresize('reload_inner_width();');
			
			// need to be instanciated here, as the constructor is not yet finished
			if (!is_object($GLOBALS['phpgw']->html))
			{
				$GLOBALS['phpgw']->html = CreateObject('phpgwapi.html');
			}
			$GLOBALS['phpgw']->js->set_onresize('reload_inner_width();');
			// dont check for MS IE the window-width on load, as some versions report constantly changing sizes
			if ($GLOBALS['phpgw']->html->user_agent != 'msie') 
			{
				//$GLOBALS['phpgw']->js->set_onload('if ('.$width.'!=(window.innerWidth ? window.innerWidth : document.body.clientWidth)) reload_inner_width();');
				$GLOBALS['phpgw']->js->set_onload('if ('.$width.'!=window.innerWidth) reload_inner_width();');
			}
		}
		else
		{
			$width = 1000;	// default, if the browser does not report it
		}
//		print $width ;
		return $width - 250;	// 180 for sidebox-menu, TODO: this need to come from the template
	}
}
