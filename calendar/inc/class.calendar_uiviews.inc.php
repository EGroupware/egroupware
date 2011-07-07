<?php
/**
 * eGroupWare - Calendar's views and widgets
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-10 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Class to generate the calendar views and the necesary widgets
 *
 * The listview is in a separate class calendar_uilist!
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * The state of the UI elements is managed in the uical class, which all UI classes extend.
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the calendar_bo class !!!
 */
class calendar_uiviews extends calendar_ui
{
	var $public_functions = array(
		'day'   => True,
		'day4'  => True,
		'week'  => True,
		'weekN' => True,
		'month' => True,
		'year'  => True,
		'planner' => True,
		'index' => True,
	);

	/**
	 * integer level or string function- or widget-name
	 *
	 * @var mixed
	 */
	var $debug=false;

	/**
	 * minimum width for an event
	 *
	 * @var int
	 */
	var $eventCol_min_width = 80;

	/**
	 * extra rows above and below the workday
	 *
	 * @var int
	 */
	var $extraRows = 2;

	/**
	 * removes n extra rows below the workday
	 *
	 * @var int
	 */
	var $remBotExtraRows = 0;

	/**
	 * extra rows original (save original value even if it gets changed in the class)
	 *
	 * @var int
	 */
	var $extraRowsOriginal;

	var $timeRow_width = 40;

	/**
	 * how many rows per day get displayed, gets set by the timeGridWidget
	 *
	 * @var int
	 */
	var $rowsToDisplay;

	/**
	 * height in percent of one row, gets set by the timeGridWidget
	 *
	 * @var int
	 */
	var $rowHeight;

	/**
	 * standard params for calling bocal::search for all views, set by the constructor
	 *
	 * @var array
	 */
	var $search_params;

	/**
	 * should we use a time grid, can be set for week- and month-view to false by the cal_pref no_time_grid
	 *
	 * @var boolean
	 */
	var $use_time_grid=true;

	/**
	 * Dragdrop Object
	 *
	 * @var dragdrop;
	 */
	var $dragdrop;

	/**
	 * Can we display the whole day in a timeGrid of the size of the workday and just scroll to workday start
	 *
	 * @var boolean
	 */
	var $scroll_to_wdstart=false;

	/**
	 * counter for the current whole day event of a single day
	 *
	 * @var int
	 */
	var $wholeDayPosCounter=1;

	/**
	 * Switch to disable private data and possibility to view and edit events
	 * in case of a public view (sitemgr)
	 */
	var $allowEdit = true;

	var $display_holiday_event_types = array(
		'bdays' => false,
		'hdays' => false
	);

	/**
	 * Constructor
	 *
	 * @param array $set_states=null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function __construct($set_states=null)
	{
		parent::__construct(false,$set_states);	// call the parent's constructor
		$this->extraRowsOriginal = $this->extraRows; //save original extraRows value

		$GLOBALS['egw_info']['flags']['nonavbar'] = False;
		$app_header = array(
			'day'   => lang('Dayview'),
			'4day'  => lang('Four days view'),
			'week'  => lang('Weekview'),
			'month' => lang('Monthview'),
			'year' => lang('yearview'),
			'planner' => lang('Group planner'),
		);
		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].
			(isset($app_header[$this->view]) ? ' - '.$app_header[$this->view] : '').
			// for a single owner we add it's name to the app-header
			(count(explode(',',$this->owner)) == 1 ? ': '.$this->bo->participant_name($this->owner) : '');

		// standard params for calling bocal::search for all views
		$this->owner = str_replace('%2C',',',$this->owner);
		$this->search_params = array(
			'start'   => $this->date,
			'cat_id'  => $this->cat_id ? explode(',',$this->cat_id) : 0,
			'users'   => explode(',',$this->owner),
			'filter'  => $this->filter,
			'daywise' => True,
		);
		$this->holidays = $this->bo->read_holidays($this->year);

		$this->check_owners_access();

		//Load the ""show holiday as event" preference here and set the event
		//types mask accordingly.
		$display_holidays_event = $GLOBALS['egw_info']['user']['preferences']['calendar']['display_holidays_event'];
		$this->display_holiday_event_types = array(
			'bdays' => ((int)$display_holidays_event & 1) != 0,
			'hdays' => ((int)$display_holidays_event & 2) != 0
		);

		if($GLOBALS['egw_info']['user']['preferences']['common']['enable_dragdrop'])
		{
			$this->dragdrop = new dragdrop();
			// if the object would auto-disable itself unset object
			// to avoid unneccesary dragdrop calls later
			if(!$this->dragdrop->validateBrowser()) $this->dragdrop = false;
		}
	}


	/**
	 * Calculate iso8601 week-number, which is defined for Monday as first day of week only
	 *
	 * We addjust the day, if user prefs want a different week-start-day
	 *
	 * @param int|string|DateTime $time
	 * @return string
	 */
	public function week_number($time)
	{
		if (!is_a($time,'DateTime')) $time = new egw_time($time);

		// if week does not start Monday and $time is Sunday --> add one day
		if ($this->cal_prefs['weekdaystarts'] != 'Monday' && !($wday = $time->format('w')))
		{
			$time->modify('+1day');
		}
		// if week does start Saturday and $time is Saturday --> add two days
		elseif ($this->cal_prefs['weekdaystarts'] == 'Saturday' && $wday == 6)
		{
			$time->modify('+2days');
		}
		return $time->format('W');
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
		$this->__construct(array(
			'date'       => $this->bo->date2string($this->bo->now_su),
			'cat_id'     => 0,
			'filter'     => 'all',
			'owner'      => substr($this->cal_prefs['mainscreen_showevents'],0,7) == 'planner' && $this->cal_prefs['planner_start_with_group'] ?
				$this->cal_prefs['planner_start_with_group'] : $this->user,
			'multiple'   => 0,
			'view'       => $this->bo->cal_prefs['mainscreen_showevents'],
		));

		if (($error = $this->check_owners_access()))
		{
			return $error;
		}
		if ($this->group_warning)
		{
			$group_warning = '<p class="redItalic" align="center">'.$this->group_warning."</p>\n";
		}
		switch($this->cal_prefs['mainscreen_showevents'])
		{
			case 'planner_user':
			case 'planner_cat':
			case 'planner':
				return $group_warning.$this->planner(true);

			case 'year':
				return $group_warning.$this->year(true);

			case 'month':
				return $group_warning.$this->month(0,true);

			case 'weekN':
				return $group_warning.$this->weekN(true);

			default:
			case 'week':
				return $group_warning.$this->week(0,true);

			case 'day':
				return $group_warning.$this->day(true);
			case 'day4':
				return $group_warning.$this->week(4,true);
		}
	}

	/**
	 * Displays the planner view
	 *
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &planner($home=false)
	{
		if ($this->sortby == 'month')	// yearly planner with month rows
		{
			$this->first = $this->bo->date2array($this->date);
			$this->first['day'] = 1;
			unset($this->first['raw']);
			$this->last = $this->first;
			$this->last['year']++;
			$this->last = $this->bo->date2ts($this->last)-1;
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('yearly planner').' '.
				lang(egw_time::to($this->first,'F')).' '.egw_time::to($this->first,'Y').' - '.
				lang(egw_time::to($this->last,'F')).' '.egw_time::to($this->last,'Y');
		}
		elseif (!$this->planner_days)	// planner monthview
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
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Week').' '.$this->week_number($this->first).': '.$this->bo->long_date($this->first,$this->last);
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

		$merge = $this->merge();
		if($merge)
		{
			egw::redirect_link('/index.php',array(
				'menuaction' => 'calendar.calendar_uiviews.index',
				'msg'        => $merge,
			));
		}

		$search_params = $this->search_params;
		$search_params['daywise'] = false;
		$search_params['start'] = $this->first;
		$search_params['end'] = $this->last;
		$search_params['enum_groups'] = $this->sortby == 'user';
		$events =& $this->bo->search($search_params);

		if ($this->debug > 0) $this->bo->debug_message('uiviews::planner() date=%1: first=%2, last=%3',False,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		$content =& $this->plannerWidget($events,$this->first,$this->last,$this->sortby != 'category' ? $this->sortby : (int) $this->cat_id);

		$content .= $this->edit_series();

		if (!$home)
		{
			$this->do_header();

			echo $content;
		}
		return $content;
	}

	/**
	 * Displays a multiple week-view
	 *
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &weekN($home=false)
	{
		if (($num = (int)$this->cal_prefs['multiple_weeks']) < 2) $num = 3;	// default 3 weeks

		return $this->month($num,$home);
	}

	/** Month column width (usually 3 or 4) in year view */
	const YEARVIEW_COLS = 3;

	/**
	 * Displays a year view
	 *
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &year($home=false)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::year date=%2',True,$this->date);

		$content = $this->edit_series();

		$this->_month_align_year($this->first,$this->last);

		$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$this->year;

		$merge = $this->merge();
		if($merge)
		{
			egw::redirect_link('/index.php',array(
				'menuaction' => 'calendar.calendar_uiviews.index',
				'msg'        => $merge,
			));
		}

		$days =& $this->bo->search(array(
			'start'   => $this->first,
			'end'     => $this->last,
		) + $this->search_params);

		/* Loop through the week-aligned months. */
		for ($month = 1; $month <= 12; $month++)
		{
			// The first date entry in the view may be in the last month.
			if (($month - 1) % self::YEARVIEW_COLS == 0)
			{
				$content .= '<div class="calTimeGrid" style="height: 162px;">'."\n";
				$content .= "\t".'<div class="calDayColsNoGrip">'."\n";
			}

			$month_start = $this->datetime->get_weekday_start($this->year,$month,1);
			// Beginning of the last week in the month
			$month_end = $this->datetime->get_weekday_start(
							$this->year,
							$month,
							$this->datetime->days_in_month($month,$this->year));
			// End of the last week in month
			$month_end = strtotime("+6 days",$month_end);

			$content .= "\t\t".'<div class="calDayCol" style="left: '.
				((($month - 1) % self::YEARVIEW_COLS) * (100 / self::YEARVIEW_COLS)).'%; width: '.
				((100 / self::YEARVIEW_COLS) - 0).'%;";>'."\n";

			// Year Header
			$content .= "\t\t\t".'<div class="calDayColHeader '.($month % 2 == 0 ? "th" : "row_on").'"'.
				' style="height: 20px; line-height: 20px; z-index: 0;" title="'.lang(adodb_date('F',strtotime("+1 week",$month_start))).' '.adodb_date('Y',strtotime("+1 week",$month_start)).'">'."\n";
			if (($month) == 1)
			{
				$content .= '<span style="position: absolute; left: 0px;">';
				$content .= html::a_href(html::image('phpgwapi','first',lang('previous'),$options=' alt="<<"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',strtotime('-1 year',strtotime($this->date))),
				));
				$content .= '</span>'."\n";
			}
			$content .= "\t\t\t\t".'<a href="'.$GLOBALS['egw']->link('/index.php',
				array('menuaction'=>'calendar.calendar_uiviews.month',
				'date'=>$this->year.$month.'01')).
				'" title="'.lang('Monthview').'">'.lang(adodb_date('F',strtotime("+1 week",$month_start))).'</a>'."\n";
			if ($month == self::YEARVIEW_COLS)
			{
				$content .= '<span style="position: absolute; right: 0px;">';
				$content .= html::a_href(html::image('phpgwapi','last',lang('next'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',strtotime('+1 year',strtotime($this->date))),
				));
				$content .= '</span>'."\n";
			}
			$content .= "\t\t\t".'</div>'."\n";

			$content .= "\t\t\t".'<div'.
					' style="position: absolute; width: 100%; height: 16px;'.
					' top: 24px;">'."\n";
			$content .= "\t\t\t\t".'<div class="cal_year_legend"'.
				' style="text-align: center; position: absolute; width: 11.5%; height: 16px; left: 0.5%;">'.lang('Wk').'</div>'."\n";
			// Day Columns, Legend
			for ($i = 0; $i <= 6; $i++)
			{
				$day_date = ($i ? strtotime("+$i days",$month_start) : $month_start);
				if (adodb_date('w',$day_date) % 6 == 0)
				{
					$style = 'cal_year_legend_weekend';
				}
				else
				{
					$style = 'cal_year_legend';
				}

				$content .= "\t\t\t\t".'<div class="'.$style.'"'.
					' style="text-align: center; position: absolute; width: 11.5%; height: 16px; left: '.((($i + 1) * 12.5) + 0.5).'%;">'.
					lang(adodb_date('D',$day_date)).'</div>'."\n";
			}
			$content .= "\t\t\t".'</div>'."\n";

			// Week rows in month
			$week_start = $month_start;
			for ($week_in_month = 1; $week_in_month <= 6; $week_in_month ++)
			{
				$content .= "\t\t\t".'<div'.
						' style="position: absolute; width: 100%; height: 16px;'.
						' top: '.((($week_in_month + 1) * 20) + 2).'px;">'."\n";

				$content .= "\t\t\t\t".'<div class="cal_year_legend"'.
					' style="text-align: center;position: absolute; width: 11.5%; height: 16px; left: 0.5%;" '.
					'title="'.lang('Wk').' '.$this->week_number($week_start).'/'.adodb_date('Y',$week_start).'">'."\n";
				$content .= "\t\t\t\t\t".
					'<a href="'.$GLOBALS['egw']->link('/index.php',
								array('menuaction'=>'calendar.calendar_uiviews.week',
								'date'=>$this->bo->date2string($week_start)));
				$content .= '">'.$this->week_number($week_start)."</a>\n";
				$content .= "\t\t\t\t".'</div>'."\n";
				// Day columns in week row
				for ($i = 0; $i <= 6; $i++)
				{
					$day_date = $i ? strtotime("+$i days",$week_start) : $week_start;
					$day_ymd = $this->bo->date2string($day_date);
					$eventcount = count($days[$day_ymd]);
					$in_month = true;
					$css_class = "";
					$this->_day_class_holiday($day_ymd,$class,$holidays,false,false);
					if (adodb_date('n',$day_date) != $month)
					{
						$css_class .= 'cal_year_legend';
						$in_month = false;
					}
					else
					{
						$css_class .= 'calEvent calEventAllAccepted';
						if (adodb_date('w',$day_date) % 6 == 0)
						{
							$css_class .= ' cal_year_weekend';
						}
						else
						{
							if ($holidays)
							{
								$css_class .= ' calHoliday';
							}
							else
							{
								$css_class .= ' cal_year_free';
							}
						}

						if ($day_ymd == $this->bo->date2string($this->bo->now_su))
						{
							$css_class .= ' cal_year_today';
						}
					}
					$content .= "\t\t\t\t".'<!-- Day cell -->'."\n";
					$content .= "\t\t\t\t".'<div class="'.$css_class.'"'.
						' style="position: absolute; width: 11.5%; height: 16px;'.
						' line-height: 16px; left: '.((($i + 1) * 12.5) + 0.5).'%;'.
						' "';
					if ($holidays)
					{
						$content .= ' title="'.$holidays.'"';
					}
					$content .= '>'.adodb_date('d',$day_date).'</div>'."\n";


					if (($in_month) && (count($days[$day_ymd])))
					{
						$eventCols = $this->getEventCols($day_ymd,$days[$day_ymd]);
						// displaying all event columns of the day
						$row_height = 100 / count($eventCols);
						$space_left = 4; //%
						$space_right = 1; //%
						$row_width = 11.5 - $space_left - $space_right;
						// settings for time2pos
						$this->scroll_to_wdstart = false;
						$this->wd_start = 0;
						$this->wd_end = 24*60;
						$this->granularity_m = 24 * 60;
						$this->extraRows = -1;
						$this->remBotExtraRows = 0;
						$this->rowHeight = $row_width;
						foreach($eventCols as $n => $eventCol)
						{
							foreach ($eventCol as $event)
							{
								$indent = "\t\t\t\t";
								// some fields set by the dayColWidget for the other views
								unset($event['whole_day_on_top']);
								$data = $this->eventWidget($event,25,$indent,$this->owner,true,'planner_event');

								$left = ((($i + 1) * 12.5) + 0.5 + $space_left + $this->time2pos($event['start_m']));
								$width = $this->time2pos($event['end_m'] - $event['start_m']);
								$color = $data['color'] ? $data['color'] : 'gray';

								$content .= $indent.'<div class="plannerEvent'.($data['private'] ? 'Private' : '').
									'" style="position: absolute; left: '.$left.'%; width: '.$width.'%; height: '.
									$row_height.'%; top: '.($n * $row_height).'%;'.
									'background-color: '.$color.';" '.$data['popup'].' '.
									html::tooltip($data['tooltip'],False,array('BorderWidth'=>0,'Padding'=>0)).
									'>'."\n".$data['html'].$indent."</div>\n";
							}
						}
					}
				}
				$week_start = strtotime("+1 week",$week_start);
				$content .= "\t\t\t".'</div>'."\n";
			}
			$content .= "\t\t".'</div>'."\n";

			if (($month) % self::YEARVIEW_COLS == 0)
			{
				$content .= "\t</div>\n";
				$content .= "</div>\n";
			}
		}

		if (!$home)
		{
			$this->do_header();

			echo $content;
		}

		return $content;
	}

	/**
	 * Displays the monthview or a multiple week-view
	 *
	 * @param int $weeks=0 number of weeks to show, if 0 (default) all weeks of one month are shown
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function &month($weeks=0,$home=false)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(weeks=%1) date=%2',True,$weeks,$this->date);

		$this->use_time_grid = !$this->cal_prefs['use_time_grid'] || $this->cal_prefs['use_time_grid'] == 'all';	// all views

		// Merge print
		if($weeks)
		{
			// Split up span into multiple weeks
			$timespan = array();
			$this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
			for($i = 0; $i < $weeks; $i++)
			{
				$timespan[] = array(
					'start' => strtotime("+$i weeks", $this->first),
					'end' => strtotime('+' . ($i+1).' weeks', $this->first) -1
				);
			}
		} else {
			$timespan[] = array(
				'start' => mktime(0,0,0,$this->month,1,$this->year),
				'end' => mktime(0,0,0,$this->month+1,1,$this->year)-1
			);
		}
		$merge = $this->merge($timespan);
		if($merge)
		{
			egw::redirect_link('/index.php',array(
				'menuaction' => 'calendar.calendar_uiviews.index',
				'msg'        => $merge,
			));
		}

		if ($weeks)
		{
			$this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
			$this->last = strtotime("+$weeks weeks",$this->first) - 1;
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

		$content = $this->edit_series();
		// we add DAY_s/2 to $this->first (using 12h), to deal with daylight saving changes
		for ($week_start = $this->first; $week_start < $this->last; $week_start = strtotime("+1 week",$week_start))
		{
			$week = array();
			for ($i = 0; $i < 7; ++$i)
			{
				$day_ymd = $this->bo->date2string($i ? strtotime("+$i days",$week_start) : $week_start);
				$week[$day_ymd] = array_shift($days);
			}
			$week_view = array(
				'menuaction' => 'calendar.calendar_uiviews.week',
				'date' => $this->bo->date2string($week_start),
			);
			$title = lang('Wk').' '.$this->week_number($week_start);
			if ($this->allowEdit)
			{
				$title = html::a_href($title,$week_view,'',' title="'.lang('Weekview').'"');
			}

			$content .= $this->timeGridWidget($this->tagWholeDayOnTop($week),$weeks == 2 ? 30 : 60,200,'',$title,0,$week_start+WEEK_s >= $this->last);
		}
		if (!$home)
		{
			$this->do_header();

			echo $content;
		}

		// make wz_dragdrop elements work
		if(is_object($this->dragdrop)) { $this->dragdrop->setJSCode(); }

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
	 * Get start and end of a year aligned to full months
	 *
	 * @param int &$first timestamp 0h of first day of week containing the first of the current year
	 * @param int &$last timestamp 23:59:59 of last day of week containg the last day of the current year
	 */
	function _month_align_year(&$first,&$last)
	{
		$first = $this->datetime->get_weekday_start($this->year,$this->month=1,$this->day=1);
		$last = $this->datetime->get_weekday_start($this->year,$this->month+12,
				$this->datetime->days_in_month($this->month+12,$this->year));
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
	 * Four days view, everythings done by the week-view code ...
	 *
	 * @param boolean $home=false if true return content suitable for home-page
	 * @return string
	 */
	function day4($home=false)
	{
		return $this->week(4,$home);
	}

	/**
	 * Displays the weekview, with 5 or 7 days
	 *
	 * @param int $days=0 number of days to show, if 0 (default) the value from the URL or the prefs is used
	 * @param boolean $home=false if true return content suitable for home-page
	 */
	function week($days=0,$home=false)
	{
		$this->use_time_grid = $days != 4 && !in_array($this->cal_prefs['use_time_grid'],array('day','day4')) ||
			$days == 4 && $this->cal_prefs['use_time_grid'] != 'day';

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

		if ($days == 4)		// next 4 days view
		{
			$wd_start = $this->first = $this->bo->date2ts($this->date);
			$this->last = strtotime("+$days days",$this->first) - 1;
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Four days view').' '.$this->bo->long_date($this->first,$this->last);
		}
		else
		{
			$wd_start = $this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
			if ($days == 5)		// no weekend-days
			{
				switch($this->cal_prefs['weekdaystarts'])
				{
					case 'Saturday':
						$this->first = strtotime("+2 days",$this->first);
						break;
					case 'Sunday':
						$this->first = strtotime("+1 day",$this->first);
						break;
				}
			}
			$this->last = strtotime("+$days days",$this->first) - 1;
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.lang('Week').' '.$this->week_number($this->first).': '.$this->bo->long_date($this->first,$this->last);
		}

        #	temporarly disabled, because it collides with the title for the website
        #
		#	// add navigation for previous and next
		#	// prev. week
		#	$GLOBALS['egw_info']['flags']['app_header'] = html::a_href(html::image('phpgwapi','first',lang('previous'),$options=' alt="<<"'),array(
		#		'menuaction' => $this->view_menuaction,
		#		'date'       => date('Ymd',$this->first-$days*DAY_s),
		#		)) . ' &nbsp; <b>'.$GLOBALS['egw_info']['flags']['app_header'];
		#	// next week
		#	$GLOBALS['egw_info']['flags']['app_header'] .= '</b> &nbsp; '.html::a_href(html::image('phpgwapi','last',lang('next'),$options=' alt=">>"'),array(
		#		'menuaction' => $this->view_menuaction,
		#		'date'       => date('Ymd',$this->last+$days*DAY_s),
		#		));
		#
		#		$class = $class == 'row_on' ? 'th' : 'row_on';
		//echo "<p>weekdaystarts='".$this->cal_prefs['weekdaystarts']."', get_weekday_start($this->year,$this->month,$this->day)=".date('l Y-m-d',$wd_start).", first=".date('l Y-m-d',$this->first)."</p>\n";

		$merge = $this->merge();
		if($merge)
		{
			egw::redirect_link('/index.php',array(
				'menuaction' => 'calendar.calendar_uiviews.index',
				'msg'        => $merge,
			));
		}

		$search_params = array(
				'start'   => $this->first,
				'end'     => $this->last,
			) + $this->search_params;

		$users = $this->search_params['users'];
		if (!is_array($users)) $users = array($users);

		if (count($users) == 1 || count($users) > $this->bo->calview_no_consolidate)	// for more then X users, show all in one row
		{
			$content = $this->timeGridWidget($this->tagWholeDayOnTop($this->bo->search($search_params)),$this->cal_prefs['interval']);
		}
		else
		{
			$content = '';
			foreach($this->_get_planner_users(false) as $uid => $label)
			{
				$search_params['users'] = $uid;
				$content .= '<b>'.$label."</b>\n";
				$content .= $this->timeGridWidget($this->tagWholeDayOnTop($this->bo->search($search_params)),
					count($users) * $this->cal_prefs['interval'],400 / count($users),'','',$uid);
			}
		}
		$content .= $this->edit_series();

		if (!$home)
		{
			$this->do_header();

			echo $content;
		}

		// make wz_dragdrop elements work
		if(is_object($this->dragdrop)) { $this->dragdrop->setJSCode(); }

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
		$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$this->bo->long_date($this->first,0,false,true);

		$this->use_time_grid = true;    // day-view always uses a time-grid, independent what's set in the prefs!

		$this->search_params['end'] = $this->last = $this->first+DAY_s-1;

		$merge = $this->merge();
		if($merge)
		{
			egw::redirect_link('/index.php',array(
				'menuaction' => 'calendar.calendar_uiviews.index',
				'msg'        => $merge,
			));
		}

		if (!$home)
		{
			$this->do_header();

			$users = $this->search_params['users'];
			if (!is_array($users)) $users = array($users);

			// for more then X users, show all in one row
			if (count($users) == 1 || count($users) > $this->bo->calview_no_consolidate)
			{
				$dayEvents =& $this->bo->search($this->search_params);
				$owner = 0;
			}
			else
			{
				$dayEvents = $owner = array();
				$search_params = $this->search_params;
				foreach($this->_get_planner_users(false) as $uid => $label)
				{
					$search_params['users'] = $uid;
					list(,$dayEvents['<b>'.$label.'</b>']) = each($this->bo->search($search_params));
					$owner[] = $uid;
				}
			}
			$cols = array();

			//Add the holiday events
			$holidays = $this->_get_holiday_events($this->date, $this->display_holiday_event_types);
			foreach($dayEvents as &$events)
			{
				$events = array_merge($events,$holidays);
			}
			unset($events);
			unset($holidays);

			$cols[0] =& $this->timeGridWidget($this->tagWholeDayOnTop($dayEvents),$this->cal_prefs['interval'],450,'','',$owner);

			$cols[0] .= $this->edit_series();

			// only show todo's for a single user
			if (count($users) == 1 && ($todos = $this->get_todos($todo_label)) !== false)
			{
				if ($GLOBALS['egw_info']['user']['apps']['infolog'])
				{
					foreach(array('task','phone','note') as $type)
					{
						$todo_label .= '&nbsp;'.html::a_href( html::image('infolog',$type,lang('Add')),'infolog.uiinfolog.edit',array(
							'type' => $type,
							'start_time' => $ts,
						),' target="_blank" onclick="window.open(this.href,this.target,\'dependent=yes,width=750,height=590,scrollbars=yes,status=yes\'); return false;"');
					}
				}
				$cols[1] = html::div(
					html::div($todo_label,'','calDayTodosHeader th')."\n".
					html::div($todos,'','calDayTodosTable'),'','calDayTodos');
				$cols['.1'] = 'width=30%';
				echo html::table(array(
					0 => $cols,
					'.0' => 'valign="top"'
				),'class="calDayView"');
			}
			else
			{
				echo $cols[0];
			}
			// make wz_dragdrop elements work
			if(is_object($this->dragdrop)) { $this->dragdrop->setJSCode(); }
		}
		else
		{
			$content = $this->timeGridWidget($this->bo->search($this->search_params),$this->cal_prefs['interval'],300);
			$content .= $this->edit_series();

			// make wz_dragdrop elements work
			if(is_object($this->dragdrop)) { $this->dragdrop->setJSCode(); }

			return $content;
		}
	}

	/**
	 * Return HTML and Javascript to query user about editing an event series or create an exception
	 *
	 * Layout is defined in eTemplate 'calendar.edit_series'
	 *
	 * @param string $link=null url without cal_id and date GET parameters, default calendar.calendar_uiforms.edit
	 * @param string $target='_blank' target
	 * @return string
	 */
	function edit_series($link=null,$target='_blank')
	{
		if (is_null($link)) $link = egw::link('/index.php',array('menuaction'=>'calendar.calendar_uiforms.edit'));

		$tpl = new etemplate('calendar.edit_series');

		return $tpl->show(array()).'<script type="text/javascript">
var calendar_edit_id;
var calendar_edit_date;
function edit_series(id,date)
{
	calendar_edit_id = id;
	calendar_edit_date = date;

	document.getElementById("edit_series").style.display = "inline";
}
function open_edit(series)
{
	document.getElementById("edit_series").style.display = "none";

	var extra = "&cal_id="+calendar_edit_id+"&date="+calendar_edit_date;
	if (!series) extra += "&exception=1";

	'.$this->popup($link."'+extra+'").';
}
</script>';
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
						if($todo['edit']) {
							list($width, $height) = explode('x', $todo['edit']['size']);
							unset($todo['edit']['size']);
							$edit_icon_href = html::a_href( $icons, $todo['edit'],'',' target="_blank" onclick="window.open(this.href,this.target,\'dependent=yes,width='.$width.',height='.$height.',scrollbars=yes,status=yes\'); return false;"');
							$edit_href = html::a_href( $todo['title'], $todo['edit'],'',' target="_blank" onclick="window.open(this.href,this.target,\'dependent=yes,width=750,height=590,scrollbars=yes,status=yes\'); return false;"');
						}
						$icon_href = html::a_href($icons,$todo['view']);
						$href = html::a_href($todo['title'], $todo['view']);
						$content .= " <tr class=\"$class\">\n  <td valign=\"top\" width=\"15%\" nowrap>".
							($this->bo->printer_friendly?$icons:($edit_icon_href ? $edit_icon_href : $icon_href)).
							"</td>\n  <td>".($this->printer_friendly?$todo['title']:
							$edit_href)."</td>\n </tr>\n";
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
		if ($this->scroll_to_wdstart)	// we display the complete day - thought only workday is visible without scrolling
		{
			return $this->rowHeight * (1 + $this->extraRows + $time/$this->granularity_m);
		}
		// time before workday => condensed in the first $this->extraRows rows
		if ($this->wd_start > 0 && $time < $this->wd_start)
		{
			$pos = (($this->extraRows - $this->extraRowsOriginal + 1) + ($time / $this->wd_start * ($this->extraRowsOriginal - 1))) * $this->rowHeight;
		}
		// time after workday => condensed in the last row
		elseif ($this->wd_end < 24*60 && $time > $this->wd_end+1*$this->granularity_m)
		{
			$pos = 100 - (($this->extraRows - $this->remBotExtraRows) * $this->rowHeight * (1 - ($time - $this->wd_end) / (24*60 - $this->wd_end)));
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
	 * Calculates the height of a difference between 2 times
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
		$minimum = $this->rowHeight;
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
	 * @param boolean $last=true last timeGrid displayed, default true
	 */
	function &timeGridWidget($daysEvents,$granularity_m=30,$height=400,$indent='',$title='',$owner=0,$last=true)
	{
		if ($this->debug > 1 || $this->debug==='timeGridWidget') $this->bo->debug_message('uiviews::timeGridWidget(events=%1,granularity_m=%2,height=%3,,title=%4)',True,$daysEvents,$granularity_m,$height,$title);

		// determine if the browser supports scrollIntoView: IE4+, firefox1.0+ and safari2.0+ does
		// then show all hours in a div of the size of the workday and scroll to the workday start
		// still disabled, as things need to be re-aranged first, to that the column headers are not scrolled
		$this->scroll_to_wdstart = false;/*$this->use_time_grid && (html::$user_agent == 'msie' ||
			html::$user_agent == 'mozilla' && html::ua_version >= 5.0 ||
			html::$user_agent == 'safari' && html::ua_version >= 2.0);*/

		if ($this->scroll_to_wdstart)
		{
			$this->extraRows = 0;	// no extra rows necessary
			$this->remBotExtraRows = 0;
			$overflow = 'overflow: scroll;';
		}
		$this->granularity_m = $granularity_m;
		$this->display_start = $this->wd_start - ($this->extraRows * $this->granularity_m);
		$this->display_end	= $this->wd_end + (($this->extraRows - $this->remBotExtraRows) * $this->granularity_m);

		if (!$this->wd_end) $this->wd_end = 1440;
		$totalDisplayMinutes	= $this->wd_end - $this->wd_start;
		$this->rowsToDisplay	= ($totalDisplayMinutes/$granularity_m)+2+2*$this->extraRows - $this->remBotExtraRows;
		$this->rowHeight		= round(100/$this->rowsToDisplay,1);

		// ensure a minimum height of each row
		if ($height < ($this->rowsToDisplay+1) * 12)
		{
			$height = ($this->rowsToDisplay+1) * 12;
		}
		$html = $indent.'<div class="calTimeGrid" style="height: '.$height.'px;'.$overflow.'">'."\n";

		$html .= $indent."\t".'<div class="calGridHeader" style="height: '.
			$this->rowHeight.'%;">'.$title."</div>\n";

		if ($this->use_time_grid)
		{
			$off = false;	// Off-row means a different bgcolor
			$add_links = count($daysEvents) == 1;

			// the hour rows
			for($t = $this->scroll_to_wdstart ? 0 : $this->wd_start,$i = 1+$this->extraRows;
				$t <= $this->wd_end || $this->scroll_to_wdstart && $t < 24*60;
				$t += $this->granularity_m,++$i)
			{
				$set_id = '';
				if ($t == $this->wd_start)
				{
					list($id) = @each($daysEvents);
					$id = 'wd_start_'.$id;
					$set_id = ' id="'.$id.'"';
				}
				$html .= $indent."\t".'<div'.$set_id.' class="calTimeRow'.($off ? 'Off row_off' : ' row_on').
					'" style="height: '.$this->rowHeight.'%; top:'. $i*$this->rowHeight .'%;">'."\n";
				// show time for full hours, allways for 45min interval and at least on every 3 row
				$time = '';
				static $show = array(
					5  => array(0,15,30,45),
					10 => array(0,30),
					15 => array(0,30),
					45 => array(0,15,30,45),
				);
				if (!isset($show[$this->granularity_m]) ? $t % 60 == 0 : in_array($t % 60,$show[$this->granularity_m]))
				{
					$time = $GLOBALS['egw']->common->formattime(sprintf('%02d',$t/60),sprintf('%02d',$t%60));
				}
				if ($add_links) $time = $this->add_link($time,$this->date,(int) ($t/60),$t%60);
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

			$html .= $indent."\t".'<div id="calDayCols" class="calDayCols'.
				($this->use_time_grid ? ($this->bo->common_prefs['timeformat'] == 12 ? '12h' : '') : 'NoTime').'">'."\n";

			if (html::$user_agent == 'msie')	// necessary IE hack - stupid thing ...
			{
				// Lars Kneschke 2005-08-28
				// why do we use a div in a div which has the same height and width???
				// To make IE6 happy!!! Without the second div you can't use
				// style="left: 50px; right: 0px;"
				//$html .= '<div style="width=100%; height: 100%;">'."\n";

				// Ralf Becker 2006-06-19
				// Lars original typo "width=100%; height: 100%;" is important ;-)
				// means you width: 100% does NOT work, you need no width!
				$html .= '<div style="height: 100%;">'."\n";
			}
			$dayCol_width = $dayCols_width / count($daysEvents);
			$n = 0;
			foreach($daysEvents as $day => $events)
			{
				$this->wholeDayPosCounter=1;
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
			if (html::$user_agent == 'msie') $html .= "</div>\n";

			$html .= $indent."\t</div>\n";	// calDayCols
		}
		$html .= $indent."</div>\n";	// calTimeGrid

		if ($this->scroll_to_wdstart)
		{
			$html .= "<script>\n\tdocument.getElementById('$id').scrollIntoView();\n";
			if ($last)	// last timeGrid --> scroll whole document back up
			{
				$html .= "\tdocument.getElementById('divMain').scrollIntoView();\n";
			}
			$html .= "</script>\n";
		}

		return $html;
	}

	/**
	 * Sorts the events of a day into columns with non-overlapping events, the events
	 * are already sorted by start-time
	 *
	 * @param string/int $day_ymd date as Ymd
	 * @param array &$events events to split into non-overlapping groups
	 */
	function getEventCols($day_ymd, &$events)
	{
		$day_start = $this->bo->date2ts((string)$day_ymd);

		// if daylight saving is switched on or off, correct $day_start
		// gives correct times after 2am, times between 0am and 2am are wrong
		if(($daylight_diff = $day_start + 12*HOUR_s - ($this->bo->date2ts($day_ymd."T120000"))))
		{
			$day_start -= $daylight_diff;
		}

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
			if ($this->use_time_grid && !$event['whole_day_on_top'])
			{
				for($c = 0; $event['start_m'] < $col_ends[$c]; ++$c);
				$col_ends[$c] = $event['end_m'];
			}
			else
			{
				$c = 0;		// without grid we only use one column
			}
			$eventCols[$c][] = $event;
		}
		return $eventCols;
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

		$html = $indent.'<div id="calColumn'.$this->calColumnCounter++.'" class="calDayCol" style="left: '.$left.
			'%; width: '.$width.'%;">'."\n";

		// Creation of the header-column with date, evtl. holiday-names and a matching background-color
		$ts = $this->bo->date2ts((string)$day_ymd);
		$title = !is_bool($short_title) ? $short_title :
			($short_title ? lang(adodb_date('l',$ts)).' '.adodb_date('d.',$ts) : $this->bo->long_date($ts,0,false,true));

		$day_view = array(
			'menuaction' => 'calendar.calendar_uiviews.day',
			'date' => $day_ymd,
		);
		$this->_day_class_holiday($day_ymd,$class,$holidays);
		// the weekday and date
		if (!$short_title && $holidays) $title .= html::htmlspecialchars(': '.$holidays);

		if ($short_title === true)
		{
			if ($this->allowEdit)
			{
				$title = html::a_href($title,$day_view,'',
					!isset($this->holidays[$day_ymd])?' title="'.lang('Dayview').'"':'');
			}
		}
		elseif ($short_title === false)
		{
			// add arrows to go to the previous and next day (dayview only)
			$day_view['date'] = $this->bo->date2string($ts -= 12*HOUR_s);
			if ($this->allowEdit)
			{
				$title = html::a_href(html::image('phpgwapi','left',$this->bo->long_date($ts)),$day_view).' &nbsp; '.$title;
			}
			else
			{
				$title = $day_view.' &nbsp; '.$title;
			}
			$day_view['date'] = $this->bo->date2string($ts += 48*HOUR_s);
			if ($this->allowEdit)
			{
				$title .= ' &nbsp; '.html::a_href(html::image('phpgwapi','right',$this->bo->long_date($ts)),$day_view);
			}
			else
			{
				$title .= ' &nbsp; '.$day_view;
			}
		}
		if (is_bool($short_title) || ($short_title != "")) {
			$html .= $indent."\t".'<div style="height: '. $this->rowHeight .'%;" class="calDayColHeader '.$class.'"'.
				($holidays ? ' title="'.html::htmlspecialchars($holidays).'"':'').'>'.$title."</div>\n";
		}

		if ($this->use_time_grid)
		{
			// drag and drop: check if the current user has EDIT permissions on the grid
			if(is_object($this->dragdrop))
			{
				if($owner)
				{
					$dropPermission = $this->bo->check_perms(EGW_ACL_EDIT,0,$owner);
				}
				else
				{
					$dropPermission = true;
				}
			}
			// adding divs to click on for each row / time-span
			for($t = $this->scroll_to_wdstart ? 0 : $this->wd_start,$i = 1 + $this->extraRows;
				$t <= $this->wd_end || $this->scroll_to_wdstart && $t < 24*60;
				$t += $this->granularity_m,++$i)
			{
				$linkData = array(
					'menuaction'	=>'calendar.calendar_uiforms.edit',
					'date'		=> $day_ymd,
					'hour'		=> sprintf("%02d",floor($t / 60)),
					'minute'	=> sprintf("%02d",floor($t % 60)),
				);
				if ($owner) $linkData['owner'] = $owner;

				$droppableDateTime = $linkData['date'] . "T" . $linkData['hour'] . $linkData['minute'];
				$droppableID='drop_'.$droppableDateTime.'_O'.($owner<0?str_replace('-','group',$owner):$owner);

				$html .= $indent."\t".'<div id="' . $droppableID . '" style="height:'. $this->rowHeight .'%; top: '. $i*$this->rowHeight .
					'%;" class="calAddEvent"';
				if ($this->allowEdit)
				{
					$html .= ' onclick="'.$this->popup($GLOBALS['egw']->link('/index.php',$linkData)).';return false;"';
				}
				$html .= '></div>'."\n";
				if(is_object($this->dragdrop) && $dropPermission)
				{
					$this->dragdrop->addDroppable(
						$droppableID,
						array(
							'datetime'=>$droppableDateTime,
							'owner'=>$owner ? $owner : $this->user,
						)
					);
				}
			}
		}

		$eventCols = $this->getEventCols($day_ymd,$events);
		// displaying all event columns of the day
		foreach($eventCols as $n => $eventCol)
		{
			// equal sized columns
			$width = 95.0 / count($eventCols);
			$left = 2.5 + $n * $width;
			// alternative overlapping columns
			if (count($eventCols) == 1)
			{
				$width = 95;
				$left = 2.5;
			}
			else
			{
				$width = !$n ? 80 : 50;
				$left = $n * (100.0 / count($eventCols));
			}
			if ($left + $width > 100.0) $width = 100.0 - $left;
			$html .= $this->eventColWidget($eventCol,$left,$width,$indent."\t",
				$owner ? $owner : $this->user, 20+10*$n);
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
	 * @param boolean $show_bdays=true If available, also show birthdays (or hide Bdays)
	 *        Note that this is not the place to disable a preference.
	 *        If the preferences allow birthdays to be displayed, they are cached within the holidays structure.
	 *        This setting just suppressing the available data in the view.
	 */
	function _day_class_holiday($day_ymd,&$class,&$holidays,$only_weekend=false,$show_bdays=true)
	{
		$class = $holidays = '';
		$bday = false;
		if (isset($this->holidays[$day_ymd]))
		{
			$h = array();
			foreach($this->holidays[$day_ymd] as $holiday)
			{
				if (isset($holiday['birthyear']))
				{
					if ($show_bdays)
					{
						$bday = true;

						//If the birthdays are already displayed as event, don't
						//show them in the caption
						if (!$this->display_holiday_event_types['bdays'])
						{
							$h[] = $holiday['name'];
						}
					}
				}
				else
				{
					$class = 'calHoliday';

					//If the birthdays are already displayed as event, don't
					//show them in the caption
					if (!$this->display_holiday_event_types['hdays'])
					{
						$h[] = $holiday['name'];
					}
				}
			}
			$holidays = implode(', ',$h);
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
	 * @param int $owner owner of the eventCol
	 */
	function eventColWidget($events,$left,$width,$indent,$owner,$z_index=null)
	{
		if ($this->debug > 1 || $this->debug==='eventColWidget') $this->bo->debug_message('uiviews::eventColWidget(%1,left=%2,width=%3,)',False,$events,$left,$width);

		$html = $indent.'<div class="calEventCol" style="left: '.$left.'%; width:'.$width.'%;'.
			// the "calEventCol" spans across a whole column (as the name suggests) - setting the
			// z-index here would give the whole invisible column a z-index and thus the underlying
			// regions are not clickable anymore. The z_index has now moved the the eventWidget
			// function.
			//(!is_null($z_index) ? ' z-index:'.$z_index.';' : '').
			(!$this->use_time_grid ? ' top: '.$this->rowHeight.'%;' : '').'">'."\n";
		foreach($events as $event)
		{
			$html .= $this->eventWidget($event,$width,$indent."\t",$owner,false,'event_widget',$z_index);
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
	 * @param int $owner owner of the calendar the event is in
	 * @param boolean $return_array=false should an array with keys(tooltip,popup,html) be returned or the complete widget as string
	 * @param string $block='event_widget' template used the render the widget
	 * @param int $z_index is the z-index of the drag-drobable outer box of the event.
	 * @return string/array
	 */
	function eventWidget($event,$width,$indent,$owner,$return_array=false,$block='event_widget',$z_index=null)
	{
		if ($this->debug > 1 || $this->debug==='eventWidget') $this->bo->debug_message('uiviews::eventWidget(%1,width=%2)',False,$event,$width);

		if($this->use_time_grid && $event['whole_day_on_top']) $block = 'event_widget_wholeday_on_top';

		static $tpl = False;
		if (!$tpl)
		{
			$tpl = new Template(common::get_tpl_dir('calendar'));

			$tpl->set_file('event_widget_t','event_widget.tpl');
			$tpl->set_block('event_widget_t','event_widget');
			$tpl->set_block('event_widget_t','event_widget_wholeday_on_top');
			$tpl->set_block('event_widget_t','event_tooltip');
			$tpl->set_block('event_widget_t','planner_event');
		}
		if (($return_array || $event['start_m'] == 0) && $event['end_m'] >= 24*60-1)
		{
			if ($return_array && $event['end_m'] > 24*60)
			{
				$timespan = $this->bo->format_date($event['start'],false).' - '.$this->bo->format_date($event['end']);
			}
			else
			{
				$timespan = lang('all day');
			}
		}
		else
		{
			$timespan = $this->bo->timespan($event['start_m'],$event['end_m']);
		}
		if(!(int)$event['id'] && preg_match('/^([a-z_-]+)([0-9]+)$/i',$event['id'],$matches))
		{
			$app = $matches[1];
			$app_id = $matches[2];
			$icons = array();
			if (($is_private = calendar_bo::integration_get_private($app,$app_id,$event)))
			{
				$icons[] = html::image('calendar','private');
			}
			else
			{
				$icons = self::integration_get_icons($app,$app_id,$event);
			}
		}
		else
		{
			if (($is_private = !$this->bo->check_perms(EGW_ACL_READ,$event)))
			{
				$icons = array(html::image('calendar','private'));
			}
			else
			{
				$icons = $this->event_icons($event);
			}
		}
		$cats  = $this->bo->categories($this->categories->check_list(EGW_ACL_READ, $event['category']),$color);
		// these values control varius aspects of the geometry of the eventWidget
		$small_trigger_width = 120 + 20*count($icons);
		$corner_radius=$width > $small_trigger_width ? 10 : 5;
		$header_height=$width > $small_trigger_width ? 19 : 12;	// multi_3 icon has a height of 19=16+2*1padding+1border !
		if (!$return_array)
		{
			$height = $this->times2height($event['start_m'],$event['end_m'],$header_height);
		}
		//$body_height = max(0,$height - $header_height - $corner_radius);
		$border=1;
		$headerbgcolor = $color ? $color : '#808080';
		$headercolor = self::brightness($headerbgcolor) > 128 ? 'black' : 'white';
		// the body-colors (gradient) are calculated from the headercolor, which depends on the cat of an event
		$bodybgcolor1 = $this->brighter($headerbgcolor,$headerbgcolor == '#808080' ? 100 : 170);
		$bodybgcolor2 = $this->brighter($headerbgcolor,220);

		// mark event as invitation, by NOT using category based background color, but plain white
		if ($event['participants'][$this->user][0] == 'U')
		{
			$bodybgcolor1 = $bodybgcolor2 = 'white';
		}

		// get status class of event: calEventAllAccepted, calEventAllAnswered or calEventSomeUnknown
		$status_class = 'calEventAllAccepted';
		foreach($event['participants'] as $id => $status)
		{
			calendar_so::split_status($status,$quantity,$role);

			switch ($status)
			{
				case 'A':
				case '':	// app without status
					break;
				case 'U':
					$status_class = 'calEventSomeUnknown';
					break 2;	// break foreach
				default:
					$status_class = 'calEventAllAnswered';
					break;
			}
		}
		// seperate each participant types
		$part_array = array();
		if ($this->allowEdit)
 		{
			foreach($this->bo->participants($event) as $part_key => $participant)
 			{
				if(is_numeric($part_key))
				{
					$part_array[lang('Participants')][$part_key] = $participant;
				}
				elseif(isset($this->bo->resources[$part_key[0]]))
				{
					 $part_array[((isset($this->bo->resources[$part_key[0]]['participants_header'])) ? $this->bo->resources[$part_key[0]]['participants_header'] : lang($this->bo->resources[$part_key[0]]['app']))][$part_key] = $participant;
				}
 			}
			foreach($part_array as $part_group => $participant)
 			{
				$participants .= $this->add_nonempty($participant,$part_group,True,False);
 			}
 		}
		// as we only deal with percentual widht, we consider only the full dayview (1 colum) as NOT small
		$small = $this->view != 'day' || $width < 50;
		// $small = $width <= $small_trigger_width

		$small_height = $this->use_time_grid && ( $event['end_m']-$event['start_m'] < 2*$this->granularity_m ||
			$event['end_m'] <= $this->wd_start || $event['start_m'] >= $this->wd_end);

		$tpl->set_var(array(
			// event-content, some of it displays only if it really has content or is needed
			'owner' => $GLOBALS['egw']->common->grab_owner_name($event['owner']),
			'header_icons' => $small ? '' : implode("",$icons),
			'body_icons' => $small ? implode("\n",$icons) : '',
			'icons' => implode('',$icons),
			'timespan' => $timespan,
			'title' => ($title = !$is_private ? html::htmlspecialchars($event['title']) : lang('private')),
			'header' => $small_height ? $title : $timespan,
			'description' => !$is_private ? nl2br(html::htmlspecialchars($event['description'])) : '',
			'location'   => !$is_private ? $this->add_nonempty($event['location'],lang('Location')) : '',
			'participants' => $participants,
			'times' => !$event['multiday'] ? $this->add_nonempty($this->bo->timespan($event['start_m'],$event['end_m'],true),lang('Time')) :
				$this->add_nonempty($this->bo->format_date($event['start']),lang('Start')).
				$this->add_nonempty($this->bo->format_date($event['end']),lang('End')),
			'multidaytimes' => !$event['multiday'] ? '' :
				$this->add_nonempty($this->bo->format_date($event['start']),lang('Start')).
				$this->add_nonempty($this->bo->format_date($event['end']),lang('End')),
			'category' => !$is_private ? $this->add_nonempty($cats,lang('Category')) : '',
			// the tooltip is based on the content of the actual widget, this way it takes no extra bandwidth/volum
//			'tooltip' => html::tooltip(False,False,array('BorderWidth'=>0,'Padding'=>0)),
			// various aspects of the geometry or style
			'corner_radius'  => $corner_radius.'px',
			'header_height' => $header_height.'px',
			//'body_height' => $body_height.'px',
			'height' => $height,
			'width' => ($width-20).'px',
			'border' => $border,
			'bordercolor' => $headerbgcolor,
			'headerbgcolor' => $headerbgcolor,
			'headercolor' => $headercolor,
			'bodybackground' => ($background = 'url('.$GLOBALS['egw_info']['server']['webserver_url'].
				'/calendar/inc/gradient.php?color1='.urlencode($bodybgcolor1).'&color2='.urlencode($bodybgcolor2).
				'&width='.$width.') repeat-y '.$bodybgcolor2),
			'Small' => $small ? 'Small' : '',	// to use in css class-names
			'indent' => $indent."\t",
			'status_class' => $status_class,
		));
/* not used at the moment
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
*/
		$tooltip = $tpl->fp('tooltip','event_tooltip');
		$html = $tpl->fp('out',$block);

		if ($is_private || !$this->allowEdit)
		{
			$popup = '';
		}
		elseif($app && $app_id)
		{
			$popup = $this->integration_get_popup($app,$app_id);
		}
		else
		{
			if ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$popup = ' onclick="edit_series('.$event['id'].','.$this->bo->date2string($event['start']).');"';
			}
			else
			{
				$view_link = egw::link('/index.php',array('menuaction'=>'calendar.calendar_uiforms.edit','cal_id'=>$event['id'],'date'=>$this->bo->date2string($event['start'])));

				$popup = ' onclick="'.$this->popup($view_link).'; return false;"';
			}
		}
		//_debug_array($event);

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

		$draggableID = 'drag_'.$event['id'].'_O'.$event['owner'].'_C'.($owner<0?str_replace('-','group',$owner):$owner);

		$ttip_options = array(
			'BorderWidth' => 0,		// as we use our round borders
			'Padding'     => 0,
			'Sticky'      => true,	// make long tooltips scrollable
			'ClickClose'  => true,
			'FOLLOWMOUSE' => false,
			'DELAY'		  => 600,
			//'FIX'		  => "['".$draggableID."',10,-5]",
			'SHADOW'	  => false,
			'WIDTH'		  => -400,
		);
		$ie_fix = '';
        if (html::$user_agent == 'msie')	// add a transparent image to make the event "opaque" to mouse events
        {
			$ie_fix = $indent."\t".html::image('calendar','transparent.gif','',
				html::tooltip($tooltip,False,$ttip_options).
				' style="top:0px; left:0px; position:absolute; height:100%; width:100%; z-index:1"') . "\n";
        }
		if ($this->use_time_grid)
		{
			if($event['whole_day_on_top'])
			{
					$style = 'top: '.($this->rowHeight*$this->wholeDayPosCounter).'%; height: '.$this->rowHeight.'%;';
					$this->wholeDayPosCounter++;
			}
			else
			{		$view_link = $GLOBALS['egw']->link('/index.php',array('menuaction'=>'calendar.calendar_uiforms.edit','cal_id'=>$event['id'],'date'=>$this->bo->date2string($event['start'])));

					$style = 'top: '.$this->time2pos($event['start_m']).'%; height: '.$height.'%;';
			}
		}
		else
		{
			$style = 'position: relative; margin-top: 3px;';
		}

		$prefix_icon = isset($event['prepend_icon']) ? $event['prepend_icon'] : '';

		$z_index = is_null($z_index) ? 20 : (int)$z_index;

		// ATM we do not support whole day events or recurring events for dragdrop
		$dd_emulation = "";
		if (is_object($this->dragdrop) &&
			$this->use_time_grid &&
			(int)$event['id'] && $this->bo->check_perms(EGW_ACL_EDIT,$event))
		{
			if (!$event['whole_day_on_top'] &&
				!$event['whole_day'] &&
				!$event['recur_type'])
			{
				// register event as draggable
				$this->dragdrop->addDraggable(
						$draggableID,
						array(
							'eventId'=>$event['id'],
							'eventOwner'=>$event['owner'],
							'calendarOwner'=>$owner,
							'errorImage'=>addslashes(html::image('phpgwapi','dialog_error',false,'style="width: 16px;"')),
							'loaderImage'=>addslashes(html::image('phpgwapi','ajax-loader')),
						),
						'calendar.dragDropFunctions.dragEvent',
						'calendar.dragDropFunctions.dropEvent',
						'top center 2'
				);
			}
			else
			{
				// If a event isn't drag-dropable, the drag drop event handling has to be fully disabled
				// for that object. Clicking on it - however - should still bring it to the foreground.
				$dd_emulation = ' onmousedown="dd.z++; this.style.zIndex = dd.z; event.cancelBubble=true;"'
					.'onmouseup="event.cancelBubble=true;"'
					.'onmousemove="event.cancelBubble=true;"';
			}
		}

		$html = $indent.'<div id="'.$draggableID.'" class="calEvent'.($is_private ? 'Private' : '').' '.$status_class.
			'" style="'.$style.' border-color: '.$headerbgcolor.'; background: '.$background.'; z-index: '.$z_index.';"'.
			$popup.' '.html::tooltip($tooltip,False,$ttip_options).
			$dd_emulation.'>'.$prefix_icon."\n".$ie_fix.$html."\n".
			$indent."</div>"."\n";

		return $html;
	}

	/**
	 * Get onclick attribute to open integration item for edit
	 *
	 * Name of the attribute is 'edit_link' and it should be an array with values for keys:
	 * - 'edit'    => array('menuaction' => 'app.class.method')
	 * - 'edit_id' => 'app_id'
	 * - 'edit_popup' => '400x300' (optional)
	 *
	 * @param string $app
	 * @param int|string $id
	 * @return string
	 */
	function integration_get_popup($app,$id)
	{
		$app_data = calendar_bo::integration_get_data($app,'edit_link');

		if (is_array($app_data) && isset($app_data['edit']))
		{
			$popup_size = $app_data['edit_popup'];
			$edit = $app_data['edit'];
			$edit[$app_data['edit_id']] = $id;
		}
		else
		{
			$edit = egw_link::edit($app,$id,$popup_size);
		}
		if ($edit)
		{
			$view_link = egw::link('/index.php',$edit);

			if ($popup_size)
			{
				list($w,$h) = explode('x',$popup_size);
				$popup = ' onclick="'.$this->popup($view_link,'_blank',$w,$h).'; return false;"';
			}
			else
			{
				$popup = ' onclick="location.href=\''.$view_link.'\'; return false;"';
			}
		}
		return $popup;
	}

	/**
	 * Get icons for an integration event
	 *
	 * Attribute 'icons' is either null (--> navbar icon), false (--> no icon)
	 * or a callback with parameters $id,$event
	 *
	 * Icons specified in $events['icons'] are always displayed!
	 *
	 * @param string $app
	 * @param int|string $id
	 * @param array $event
	 * @return array
	 */
	static function integration_get_icons($app,$id,$event)
	{
		$icons = array();
		if ($event['icons'])
		{
			foreach(explode(',',$event['icons']) as $icon)
			{
				list($icon_app,$icon) = explode(':',$icon);
				if (common::find_image($icon_app,$icon))
				{
					$icons[] = html::image($icon_app,$icon);
				}
			}
		}
		$app_data = calendar_bo::integration_get_data($app,'icons');
		if (is_null($app_data))
		{
			$icons[] = html::image($app,'navbar');	// use navbar icon
		}
		elseif ($app_data)
		{
			$icons += (array)ExecMethod2($app_data,$id,$event);
		}
		return $icons;
	}

	function add_nonempty($content,$label,$one_per_line=False,$space = True)
	{
		if (is_array($content))
		{
		   if($space)
		   {
			  $content = implode($one_per_line ? ",\n" : ', ',$content);
		   }
		   else
		   {
			  $content = implode($one_per_line ? "\n" : ', ',$content);
		   }
		}
		if (!empty($content))
		{
			return '<span class="calEventLabel">'.$label.'</span>:'.
				($one_per_line ? '<br>' : ' ').
				nl2br(html::htmlspecialchars($content)).'<br>';
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
	static function brighter($rgb,$decr=64)
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
	 * Calculates the brightness of a hexadecimal rgb color (median of the r, g and b components)
	 *
	 * @param string $rgb eg. #808080
	 * @return int between 0 and 255
	 */
	static function brightness($rgb)
	{
		if ($rgb[0] != '#' || strlen($rgb) != 7)
		{
			return 128;	// no rgb color, return some default
		}
		$dec = hexdec(substr($rgb,1));
		for($i = 0; $i < 24; $i += 8)
		{
			$sum += ($dec >> $i) & 255;
		}
		return (int)round($sum / 3.0, 0);
	}

	/**
	 * Number of month to display in yearly planner
	 */
	const YEARLY_PLANNER_NUM_MONTH = 12;

	/**
	 * Creates a planner view: grid with columns for the time and rows for categories or users
	 *
	 * Uses the plannerRowWidget to display rows
	 *
	 * @param array $events events to show
	 * @param mixed $start start-time of the grid
	 * @param mixed $end end-time of the grid
	 * @param string|int $by_cat rows by sub-categories of $by_cat (cat_id or 0 for upmost level) or by 'user' or 'month'
	 * @param string $indent='' string for correct indention
	 * @return string with widget
	 */
	function &plannerWidget(&$events,$start,$end,$by_cat=0,$indent='')
	{
		$content = $indent.'<div class="plannerWidget">'."\n";

		// display the header, containing a headerTitle and multiple headerRows with the scales
		$content .= $indent."\t".'<div class="plannerHeader">'."\n";

		// display the headerTitle, and get sort2labels
		switch($by_cat)
		{
			case 'user':
				$title = lang('User');
				$sort2label = $this->_get_planner_users();
				break;
			case 'month':
				$title = lang('Month');
				$sort2label = array();
				$time = new egw_time($start);
				for($n = 0; $n < self::YEARLY_PLANNER_NUM_MONTH; ++$n)
				{
					$sort2label[$time->format('Y-m')] = lang($time->format('F')).' '.$time->format('Y');
					$time->modify('+1 month');
				}
				break;
			default:
				$title = lang('Category');
				$sort2label = array();
				break;
		}
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
		if ($by_cat === 'month')
		{
			$content .= $this->plannerDayOfMonthScale($indent."\t\t\t");
		}
		else
		{
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
		}
		$content .= $indent."\t\t</div>\n";	// end of the plannerHeaderRows
		$content .= $indent."\t</div>\n";	// end of the plannerHeader

		// sort the events after user or category
		$rows = array();
		if (!is_array($events)) $events = array();

		if ($by_cat === 'user')	// planner by user
		{
			// convert filter to allowed status
			switch($this->filter)
			{
				case 'unknown':
					$status_to_show = array('U','G'); break;
				case 'accepted':
					$status_to_show = array('A'); break;
				case 'tentative':
					$status_to_show = array('T'); break;
				case 'rejected':
					$status_to_show = array('R'); break;
				case 'delegated':
					$status_to_show = array('D'); break;
				case 'all':
					$status_to_show = array('U','A','T','D','G','R'); break;
				default:
					$status_to_show = array('U','A','T','D','G'); break;
			}
		}
		foreach($events as $key => $event)
		{
			if ($by_cat === 'user')	// planner by user
			{
				foreach($event['participants'] as $sort => $status)
				{
					calendar_so::split_status($status,$nul,$nul);
					// only show if participant with status visible with current filter
					if (isset($sort2label[$sort]) && (in_array($status,$status_to_show) ||
						$this->filter == 'owner' && $event['owner'] == $sort))	// owner too additionally uses owner
					{
						$rows[$sort][] =& $events[$key];
					}
				}
			}
			elseif ($by_cat === 'month')	// planner by month / yearly planner
			{
				$sort = date('Y-m',$event['start']);
				$rows[$sort][] =& $events[$key];
				// end in a different month?
				if ($sort != ($end_sort = date('Y-m',$event['end'])))
				{
					while($sort != $end_sort)
					{
						list($y,$m) = explode('-',$sort);
						if (++$m > 12)
						{
							++$y;
							$m = 1;
						}
						$sort = sprintf('%04d-%02d',$y,$m);
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
			if (!isset($rows[$sort]) && (!$this->cal_prefs['planner_show_empty_rows'] ||
				$by_cat === 'user' && $this->cal_prefs['planner_show_empty_rows'] == 'cat' ||
				is_int($by_cat) && $this->cal_prefs['planner_show_empty_rows'] == 'user'))
			{
				continue;		// dont show empty categories or user rows
			}
			$class = $class == 'row_on' ? 'row_off' : 'row_on';
			if ($by_cat === 'month')
			{
				$time = new egw_time($sort.'-01');
				$start = $time->format('ts');
				$time->modify('+1month -1second');
				$end = $time->format('ts');
			}
			$content .= $this->plannerRowWidget(isset($rows[$sort]) ? $rows[$sort] : array(),$start,$end,$label,$class,$indent."\t");
		}
		$content .= $indent."</div>\n";		// end of the plannerWidget

		return $content;
	}

	/**
	 * get all users to display in the planner_by_user
	 *
	 * @param boolean $enum_groups=true should groups be returned as there members (eg. planner) or not (day & week)
	 * @return array with uid => label pairs, first all users alphabetically sorted, then all resources
	 */
	function _get_planner_users($enum_groups=true)
	{
		$users = $resources = array();
		foreach(explode(',',$this->owner) as $user)
		{
			if (!is_numeric($user))		// resources
			{
				$resources[$user] = $this->bo->participant_name($user);
			}
			elseif ($enum_groups && $GLOBALS['egw']->accounts->get_type($user) == 'g')	// groups
			{
				foreach((array) $GLOBALS['egw']->accounts->member($user) as $data)
				{
					$user = $data['account_id'];
					if ($this->bo->check_perms(EGW_ACL_READ | EGW_ACL_FREEBUSY,0,$user))
					{
						$users[$user] = $this->bo->participant_name($user);
					}
				}
			}
			else	// users
			{
				$users[$user] = $this->bo->participant_name($user);
			}
		}
		asort($users);
		asort($resources);

		return $users+$resources;
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
			foreach((array)$this->categories->return_sorted_array(0,false,'','','',true) as $data)
			{
				if (in_array($data['parent'], (array)$this->cat_id) || in_array($data['id'], (array)$this->cat_id))	// cat is a direct sub of $this->cat_id
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
		//echo "<p>uiviews::_get_planner_cats($cats=".$this->categories->id2name($cats).") (this->cat_id=$this->cat_id) = ".print_r($ret,true).'='.$this->categories->id2name($ret[0])."</p>\n";
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
					$title = html::a_href(html::image('phpgwapi','first',lang('back one month'),$options=' alt="<<"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => $full,
					)) . ' &nbsp; '.
					html::a_href(html::image('phpgwapi','left',lang('back half a month'),$options=' alt="<"'),array(
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
					$title .= ' &nbsp; '.html::a_href(html::image('phpgwapi','right',lang('forward half a month'),$options=' alt=">>"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => $half,
					)). ' &nbsp; '.
					html::a_href(html::image('phpgwapi','last',lang('forward one month'),$options=' alt=">>"'),array(
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
			$title = lang('Week').' '.$this->week_number($t);
			if ($days > 7)
			{
				$title = html::a_href($title,array(
					'menuaction' => 'calendar.calendar_uiviews.planner',
					'planner_days' => 7,
					'date'       => date('Ymd',$t),
				),false,' title="'.html::htmlspecialchars(lang('Weekview')).'"');
			}
			else
			{
				// prev. week
				$title = html::a_href(html::image('phpgwapi','first',lang('previous'),$options=' alt="<<"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => date('Ymd',$t-7*DAY_s),
				)) . ' &nbsp; <b>'.$title;
				// next week
				$title .= '</b> &nbsp; '.html::a_href(html::image('phpgwapi','last',lang('next'),$options=' alt=">>"'),array(
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
				$title = html::a_href($title,array(
					'menuaction'   => 'calendar.calendar_uiviews.planner',
					'planner_days' => 1,
					'date'         => date('Ymd',$t),
				),false,strpos($class,'calHoliday') !== false || strpos($class,'calBirthday') !== false ? '' : ' title="'.html::htmlspecialchars(lang('Dayview')).'"');
			}
			if ($days < 5)
			{
				if (!$i)	// prev. day only for the first day
				{
					$title = html::a_href(html::image('phpgwapi','first',lang('previous'),$options=' alt="<<"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => date('Ymd',$start-DAY_s),
					)) . ' &nbsp; '.$title;
				}
				if ($i == $days-1)	// next day only for the last day
				{
					$title .= ' &nbsp; '.html::a_href(html::image('phpgwapi','last',lang('next'),$options=' alt=">>"'),array(
						'menuaction' => $this->view_menuaction,
						'date'       => date('Ymd',$start+DAY_s),
					));
				}
			}
			$content .= $indent."\t".'<div class="plannerDayScale '.$class.'" style="left: '.$left.'%; width: '.$day_width.'%;"'.
				($holidays ? ' title="'.html::htmlspecialchars($holidays).'"' : '').'>'.$title."</div>\n";
		}
		$content .= $indent."</div>\n";		// end of plannerScale

		return $content;
	}

	/**
	 * Creates DayOfMonth scale for planner by month
	 *
	 * @param string $indent
	 * @return string
	 */
	function plannerDayOfMonthScale($indent)
	{
		$day_width = round(100 / 31,2);

		// month scale with navigation
		$content .= $indent.'<div class="plannerScale">'."\n";

		$title = lang(egw_time::to($this->first,'F')).' '.egw_time::to($this->first,'Y').' - '.
			lang(egw_time::to($this->last,'F')).' '.egw_time::to($this->last,'Y');

		// calculate date for navigation links
		$time = new egw_time($this->first);
		$time->modify('-1year');
		$last_year = $time->format('Ymd');
		$time->modify('+11month');
		$last_month = $time->format('Ymd');
		$time->modify('+2month');
		$next_month = $time->format('Ymd');
		$time->modify('+11month');
		$next_year = $time->format('Ymd');

		$title = html::a_href(html::image('phpgwapi','first',lang('back one year'),$options=' alt="<<"'),array(
				'menuaction' => $this->view_menuaction,
				'date'       => $last_year,
			)) . ' &nbsp; '.
			html::a_href(html::image('phpgwapi','left',lang('back one month'),$options=' alt="<"'),array(
				'menuaction' => $this->view_menuaction,
				'date'       => $last_month,
			)) . ' &nbsp; '.$title;
			$title .= ' &nbsp; '.html::a_href(html::image('phpgwapi','right',lang('forward one month'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => $next_month,
				)). ' &nbsp; '.
				html::a_href(html::image('phpgwapi','last',lang('forward one year'),$options=' alt=">>"'),array(
					'menuaction' => $this->view_menuaction,
					'date'       => $next_year,
				));

		$content .= $indent."\t".'<div class="plannerMonthScale th" style="left: 0; width: 100%;">'.
				$title."</div>\n";
		$content .= $indent."</div>\n";		// end of plannerScale

		// day of month scale
		$content .= $indent.'<div class="plannerScale">'."\n";
		$today = egw_time::to('now','d');
		for($left = 0,$i = 0; $i < 31; $left += $day_width,++$i)
		{
			$class = $i & 1 ? 'row_on' : 'row_off';
			$content .= $indent."\t".'<div class="plannerDayOfMonthScale '.$class.'" style="left: '.$left.'%; width: '.$day_width.'%;">'.
				(1+$i)."</div>\n";
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
		$content .= $indent."\t".'<div class="eventRows"';

		if ($this->sortby == 'month' && ($days = date('j',$end)) < 31)
		{
			$width = round(85*$days/31,2);
			$content .= ' style="width: '.$width.'%;"';
		}
		$content .= ">\n";

		// mark weekends and other special days in yearly planner
		if ($this->sortby == 'month')
		{
			$content .= $this->yearlyPlannerMarkDays($start,$days,$indent."\t\t");
		}
		foreach($rows as $row)
		{
			$content .= $this->eventRowWidget($row,$start,$end,$indent."\t\t");
		}
		$content .= $indent."\t</div>\n";	// end of the eventRows

		if ($this->sortby == 'month' && $days < 31)
		{
			// add a filler for non existing days in that month
			$content .= $indent."\t".'<div class="eventRowsFiller"'.
				' style="left:'.(15+$width).'%; width:'.(85-$width).'%;" ></div>'."\n";
		}
		$content .= $indent."</div>\n";		// end of the plannerRowWidget

		return $content;
	}

	/**
	 * Mark weekends and other special days in yearly planner
	 *
	 * @param int $start timestamp of start of row
	 * @param int $days number of days in month of row
	 * @param string $indent=''
	 * @return string
	 */
	function yearlyPlannerMarkDays($start,$days,$indent='')
	{
		$day_width = round(100/$days,2);
		for($t = $start,$left = 0,$i = 0; $i < $days; $t += DAY_s,$left += $day_width,++$i)
		{
			$this->_day_class_holiday($this->bo->date2string($t),$class,$holidays,true);

			$class = trim(str_replace(array('row_on','row_off'),'',$class));
			if ($class)	// no regular weekday
			{
				$content .= $indent.'<div class="eventRowsMarkedDay '.$class.
					'" style="left: '.$left.'%; width:'.$day_width.'%;"'.
					($holidays ? ' title="'.html::htmlspecialchars($holidays).'"' : '').
					' ></div>'."\n";
			}
		}
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

		if ($this->planner_days || $this->sortby == 'month')
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
					$wd_lenght = $this->wd_end - $this->wd_start;
					if ($wd_lenght <= 0) $wd_lenght = 24*60;
					$day_percentage = ($time_of_day-$this->wd_start) / $wd_lenght;		// between 0 and 1
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
		// some fields set by the dayColWidget for the other views
		$day_start = $this->bo->date2ts((string)$this->bo->date2string($event['start']));
		$event['start_m'] = ($event['start'] - $day_start) / 60;
		$event['end_m'] = round(($event['end'] - $day_start) / 60);
		$event['multiday'] = true;
		unset($event['whole_day_on_top']);

		$data = $this->eventWidget($event,200,$indent,$this->owner,true,'planner_event');

		$left = $this->_planner_pos($event['start'],$start,$end);
		$width = $this->_planner_pos($event['end'],$start,$end) - $left;
		$color = $data['color'] ? $data['color'] : 'gray';

		return $indent.'<div class="plannerEvent'.($data['private'] ? 'Private' : '').'" style="left: '.$left.
			'%; width: '.$width.'%; background-color: '.$color.';"'.$data['popup'].' '.
			html::tooltip($data['tooltip'],False,array('BorderWidth'=>0,'Padding'=>0)).'>'."\n".$data['html'].$indent."</div>\n";
	}

	/**
	 * Marks whole day events for later usage and increments extraRows
	 *
	 * @param array $dayEvents
	 * @return array $dayEvents
	 */
	function tagWholeDayOnTop($dayEvents)
	{
		$this->extraRows = $this->extraRowsOriginal;
		$this->remBotExtraRows = 0;

		if (is_array($dayEvents))
		{
			foreach ($dayEvents as $day=>$oneDayEvents)
			{
				$extraRowsToAdd = 0;
				foreach ($oneDayEvents as $num => $event)
				{
					$start = $this->bo->date2array($event['start']);
					$end = $this->bo->date2array($event['end']);
					if(!$start['hour'] && !$start['minute'] && $end['hour'] == 23 && $end['minute'] == 59)
					{
						if($event['non_blocking'])
						{
							$dayEvents[$day][$num]['whole_day_on_top']=true;
							$this->whole_day_positions[$num]=($this->rowHeight*($num+2));
							$extraRowsToAdd++;
						}
						else
						{
							$dayEvents[$day][$num]['whole_day']=true;
						}
					}
				}
				// check after every day if we have to increase $this->extraRows
				if(($this->extraRowsOriginal+$extraRowsToAdd) > $this->extraRows)
				{
					$this->remBotExtraRows = $extraRowsToAdd;
					$this->extraRows = ($this->extraRowsOriginal+$extraRowsToAdd);
				}
			}
		}
		return $dayEvents;
 	}

	/**
	 *
	 * Returns the special icon html code for holidays
	 *
	 * @param string $type is the type of the holiday, currently either 'hday' or
	 *    'bday'
	 */
	function _get_holiday_icon($type)
	{
		//Set the special icon which will be prepended to the event
		switch ($type) {
			case "bday":
				return html::image('calendar', 'cake', '', "style=\"float:left; padding: 1px 2px 0px 2px;\"");
			case "hday":
				return html::image('calendar', 'date', '', "style=\"float:left; padding: 1px 2px 0px 2px;\"");
		}
	}

	/**
	 *
	 * Creates a dummy holiday event. This event is shown in the day view, when
	 * added to the event list.
	 *
	 * @param int $day_start is a unix timestamp which contains the start of the day
	 *    when the event occurs.
	 * @param string $title is the title of the dummy event which will be shown
	 * @param string $description is the long description of the event which will
	 *    be shown in the event tooltip
	 */
	function _make_holiday_event($day_start, $title, $description, $type = 'bday')
	{
		//Calculate the end of the day by adding 23h:59min seconds
		$day_end = $day_start + 24 * 3600 - 60;

		//Setup the event data
		$event = array(
			'title' => $title,
			'description' => $description,
			'participants' => array(
				'-1' => 'U'
			),
			'whole_day_on_top' => true,
			'public' => true,
			'start' => $day_start,
			'end' => $day_end,
			'non_blocking' => true,
			'prepend_icon' => $this->_get_holiday_icon($type)
		);

		return $event;
	}

	/**
	 *
	 * Collects all holidays/birthdays corresponding to the given day and creates
	 * an array containing all this events.
	 *
	 * @param string $day_ymd contains the Ymd of the day
	 * @param array $types is an array which determines which types of events should
	 *    be added to the holiday list. May contain the indices "bdays" and "hdays".
	 *    The default is "bdays => true"
	 */
	function _get_holiday_events($day_ymd, $types = array("bdays" => true, "hdays" => false))
	{
		//Check whether there are any holidays set for the current day_ymd
		$events = array();
		if (isset($this->holidays[$day_ymd]))
		{
			//Translate the day_ymd to a timestamp
			$day_start = $this->bo->date2ts((string)$day_ymd);

			//Iterate over the holidays array and add those the the events list
			foreach($this->holidays[$day_ymd] as $holiday)
			{
				if (isset($holiday['birthyear']))
				{
					if (array_key_exists("bdays", $types) && $types['bdays'])
					{
						$events[] = $this->_make_holiday_event(
							$day_start, $holiday['name'],
							lang('Age:').(date('Y') - $holiday['birthyear']));
					}
				}
				else
				{
					if (array_key_exists("hdays", $types) && $types['hdays'])
					{
						$events[] = $this->_make_holiday_event($day_start, $holiday['name'], '', 'hday');
					}
				}
			}
		}

		return $events;
	}
}
