<?php
/**
 * EGroupware - Calendar's views and widgets
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Image;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

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
		'index' => True,
	);

	/**
	 * integer level or string function- or widget-name
	 *
	 * @var mixed
	 */
	var $debug=false;

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
	 * Pref value of use_time_grid preference
	 * @var string
	 */
	var $use_time_grid_pref = '';

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

	/**
	 * Constructor
	 *
	 * @param array $set_states = null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function __construct($set_states=null)
	{
		parent::__construct(false,$set_states);	// call the parent's constructor
		$this->extraRowsOriginal = $this->extraRows; //save original extraRows value

		$GLOBALS['egw_info']['flags']['nonavbar'] = False;

		// Check for GET message (from merge)
		if($_GET['msg'])
		{
			Framework::message($_GET['msg']);
			unset($_GET['msg']);
		}
		// standard params for calling bocal::search for all views
		$this->owner = str_replace('%2C',',',$this->owner);
		$this->search_params = array(
			'start'   => $this->date,
			'cat_id'  => $this->cat_id ? (is_array($this->cat_id)?$this->cat_id:explode(',',$this->cat_id)) : 0,
			'users'   => explode(',',$this->owner),
			'owner'   => explode(',',$this->owner),
			'filter'  => $this->filter,
			'daywise' => True,
			'use_so_events' => $this->test === 'true',
		);
//		$this->holidays = $this->bo->read_holidays($this->year);

		$this->check_owners_access();

		//ATM: Forces use_time_grid preference to use all views by ignoring the preference value
		//@TODO: the whole use_time_grid preference should be removed (including dependent vars)
		// after we decided that is not neccessary to have it at all
		$this->use_time_grid_pref = 'all'; //$this->cal_prefs['use_time_grid'];
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
		if (!is_a($time,'DateTime')) $time = new Api\DateTime($time);

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
	 * Load all views used by calendar, client side switches between them as needed
	 */
	function index($content=array())
	{
		// handle views in other files
		if (!isset($this->public_functions[$this->view]) && $this->view !== 'listview')
		{
			$this->view = 'week';
		}
		// get manual to load the right page
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualCalendar'.ucfirst($this->view));

		// Sidebox & iframe for old views
		if(in_array($this->view,array('year')) && $_GET['view'])
		{
			$GLOBALS['egw_info']['flags']['nonavbar'] = true;
			$this->manage_states($_GET);
			$this->{$this->view}();
			return;
		}

		// Toolbar
		$tmpl = new Etemplate('calendar.toolbar');
		$tmpl->setElementAttribute('toolbar', 'actions', $this->getToolbarActions($content));
		// Adjust toolbar for mobile
		if(Api\Header\UserAgent::mobile()){
			$tmpl->setElementAttribute('toolbar','class', 'et2_head_toolbar');
			$tmpl->setElementAttribute('toolbar','view_range', '3');
		}
		$tmpl->exec('calendar_uiviews::index',array());

		// Load the different views once, we'll switch between them on the client side
		$todo = new Etemplate('calendar.todo');
		$label = '';
		$todo->exec('calendar_uiviews::index',array('todos'=>'', 'label' => $label));

		// Actually, this takes care of most of it...
		$this->week();

		$planner = new Etemplate('calendar.planner');
		// Get the actions
		$planner->setElementAttribute('planner','actions',$this->get_actions());
		$planner->exec('calendar_uiviews::index',array());

		// List view in a separate file
		$list_ui = new calendar_uilist();
		$list_ui->listview();
	}

	/**
	 * Generate the calendar toolbar actions
	 */
	protected function getToolbarActions()
	{
		$group = 0;
		$actions = array(
			'day_view' => array(
				'caption' => 'Dayview',
				'icon'	=> '1_day_view',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Dayview',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'day'))
			),
			'4day_view' => array(
				'caption' => 'Four days view',
				'icon'	=> '4_day_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Four days view',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'day4'))
			),
			'week_view' => array(
				'caption' => 'Weekview',
				'icon'	=> 'week_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Weekview',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'week'))
			),
			'weekN_view' => array(
				'caption' => 'Multiple week view',
				'icon'	=> 'multiweek_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Multiple week view',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'weekN'))
			),
			'month_view' => array(
				'caption' => 'Monthview',
				'icon'	=> 'month_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Monthview',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'month'))
			),
			'planner_category' => array(
				'caption' => 'Planner by category',
				'icon'	=> 'planner_category_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Planner by category',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'planner', 'sortby' => 'category')),
			),
			'planner_user' => array(
				'caption' => 'Planner by user',
				'icon'	=> 'planner_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Planner by user',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'planner', 'sortby' => 'user')),
			),
			'planner_month' => array(
				'caption' => 'Yearly planner',
				'icon'	=> 'year_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Yearly planner',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'planner', 'sortby' => 'month')),
			),
			'list' => array(
				'caption' => 'Listview',
				'icon'	=> 'list',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Listview',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'listview')),
			),
			'weekend' => array(
				'caption' => 'Weekend',
				'icon' => '7_day_view',
				'checkbox'	=> true,
				'checked' => is_array($this->cal_prefs['saved_states']) ? $this->cal_prefs['saved_states']['weekend']:false,
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Toggle weekend',
				'toolbarDefault' => false,
				'data' => array('toggle_off' => '5', 'toggle_on' => '7')
			),
			'previous' => array(
				'caption' => 'Previous',
				'icon'	=> 'previous',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Previous',
				'toolbarDefault' => true,
			),
			'today' => array(
				'caption' => 'Today',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Today',
				'toolbarDefault' => true,
				'icon' => Api\Header\UserAgent::mobile() ? 'today' : ''
			),
			'next' => array(
				'caption' => 'Next',
				'icon'	=> 'next',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Next',
				'toolbarDefault' => true,
			),
		);

		// Don't show videoconference action if videoconference is disabled or BBB is not configured
		if (calendar_hooks::isVCRecordingSupported()
			&& !calendar_hooks::isVideoconferenceDisabled())
		{
			// Add toggle for video calls
			$status_config = Api\Config::read("status");
			if($status_config["status_cat_videocall"])
			{
				$actions['video_toggle'] = array(
					'caption' => 'Video call',
					'iconUrl' => Image::find('status', 'videoconference_call'),
					'checkbox' => true,
					'hint' => lang("video call"),
					'group' => 'integration',
					'onExecute' => 'javaScript:app.calendar.toolbar_videocall_toggle_action',
					'checked' => in_array('video_toggle', (array)$this->cal_prefs['integration_toggle']),
					'data' => array('toggle_off' => '0', 'toggle_on' => '1')
				);
			}
		}

		// Add integrated app options
		$integration_data = Api\Hooks::process(array('location' => 'calendar_search_union'));
		foreach($integration_data as $app => $app_hooks)
		{
			foreach ($app_hooks as $data)
			{
				// App might have multiple hooks, let it specify something else
				$app = $data['selects']['app'] ?: $app;

				// Don't add if no access or app already added
				if (!array_key_exists($app, $GLOBALS['egw_info']['user']['apps']) ||
						(is_array($actions['integration']['children']) && array_key_exists($app, $actions['integration']['children']))
				)
				{
					continue;
				}
				// Don't show infolog if there are no configured types
				if($app == 'infolog' && empty($GLOBALS['egw_info']['user']['preferences']['infolog']['calendar_integration']))
				{
					continue;
				}
				$img = self::integration_get_icons($app, null, [])[0];
				preg_match('/<img src=\"(.*?)\".*\/>/', $img, $results);
				$actions['integration_'.$app] = array(
					'caption'   => $data['selects']['caption'] ?? $app,
					'iconUrl'   => $results[1] ?: "$app\navbar",
					'checkbox'  => true,
					'hint'      => lang("show %1 from %2", lang(Link::get_registry($app, 'entries') ?: 'entries'), lang(Link::get_registry($app, 'name'))),
					'group'     => 'integration',
					'onExecute' => 'javaScript:app.calendar.toolbar_integration_action',
					'checked'   => in_array($app, (array)$this->cal_prefs['integration_toggle']),
					'data'      => array('toggle_off' => '0', 'toggle_on' => '1')
				);

			}
		}
		if (Api\Header\UserAgent::mobile())
		{
			foreach (array_keys($actions) as $key)
			{
				if (!in_array($key, array('day_view','week_view','next', 'today','previous'))) {
					$actions[$key]['toolbarDefault'] = false;
				}
				else {
					$actions[$key]['toolbarDefault'] = true;
				}
			}
			$actions['weekend']['data'] = array('toggle_off' => '7', 'toggle_on' => '5');
			unset($actions['pgp']);
		}
		return $actions;
	}

	/**
	 * Displays the planner view
	 *
	 * @param boolean|Etemplate $home = false if etemplate return content suitable for home-page
	 */
	function &planner($content = array(), $home=false)
	{
		if ($this->sortby == 'month')	// yearly planner with month rows
		{
			$this->first = $this->bo->date2array($this->date);
			$this->first['day'] = 1;
			unset($this->first['raw']);
			$this->last = $this->first;
			$this->last['year']++;
			$this->last = $this->bo->date2ts($this->last)-1;
		}
		elseif (!$this->planner_view || $this->planner_view == 'month')	// planner monthview
		{
			if ($this->day < 15)	// show one complete month
			{
				$this->_week_align_month($this->first,$this->last);
			}
			else	// show 2 half month
			{
				$this->_week_align_month($this->first,$this->last,15);
			}
		}
		elseif ($this->planner_view == 'week' || $this->planner_view == 'weekN')	// weeekview
		{
			$start = new Api\DateTime($this->date);
			$start->setWeekstart();
			$this->first = $start->format('ts');
			$this->last = $this->bo->date2array($this->first);
			$this->last['day'] += ($this->planner_view == 'week' ? 7 : 7 * $this->cal_prefs['multiple_weeks'])-1;
			$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
			unset($this->last['raw']);
			$this->last = $this->bo->date2ts($this->last);
		}
		else // dayview
		{
			$this->first = $this->bo->date2ts($this->date);
			$this->last = $this->bo->date2array($this->first);
			$this->last['day'] += 0;
			$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
			unset($this->last['raw']);
			$this->last = $this->bo->date2ts($this->last);
		}

		$search_params = $this->search_params;
		$search_params['daywise'] = false;
		$search_params['start'] = $this->first;
		$search_params['end'] = $this->last;
		$search_params['enum_groups'] = $this->sortby == 'user';
		$content['planner'] = $this->bo->search($search_params);
		foreach($content['planner'] as &$event)
		{
			$this->to_client($event);
		}

		if ($this->debug > 0) $this->bo->debug_message('uiviews::planner() date=%1: first=%2, last=%3',False,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		$tmpl = $home ? $home : new Etemplate('calendar.planner');

		$tmpl->setElementAttribute('planner','start_date', Api\DateTime::to($this->first, Api\DateTime::ET2));
		$tmpl->setElementAttribute('planner','end_date', Api\DateTime::to($this->last, Api\DateTime::ET2));
		$tmpl->setElementAttribute('planner','owner', $search_params['users']);
		$tmpl->setElementAttribute('planner','group_by', $this->sortby);
		// Get the actions
		$tmpl->setElementAttribute('planner','actions',$this->get_actions());

		$tmpl->exec(__METHOD__, $content);
	}

	/**
	 * Displays the monthview or a multiple week-view
	 *
	 * Used for home app
	 *
	 * @param int $weeks = 0 number of weeks to show, if 0 (default) all weeks of one month are shown
	 * @param boolean|etemplate2 $home = false if not false return content suitable for home-page
	 */
	function &month($weeks=0,$home=false)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(weeks=%1) date=%2',True,$weeks,$this->date);

		if (!$home)
		{
			trigger_error(__METHOD__ .' only used by home app', E_USER_DEPRECATED);
			return;
		}

		$this->use_time_grid = !$this->use_time_grid_pref || $this->use_time_grid_pref == 'all';	// all views
		$granularity = 0;
		if($weeks)
		{
			$granularity = ($this->cal_prefs['interval'] ? (int)$this->cal_prefs['interval'] : 30);

			$list = $this->cal_prefs['use_time_grid'];
			if(!is_array($list))
			{
				$list = explode(',',$list);
			}
			if(is_array($list))
			{
				$granularity = in_array('weekN',$list) ? 0 : $granularity;
			}
		}
		$content = array('view' => array());

		if ($weeks)
		{
			$start = new Api\DateTime($this->date);
			$start->setWeekstart();
			$this->first = $start->format('ts');
			$this->last = strtotime("+$weeks weeks",$this->first) - 1;
			$weekNavH = "$weeks weeks";
			$navHeader = lang('Week').' '.$this->week_number($this->first).' - '.$this->week_number($this->last).': '.
				$this->bo->long_date($this->first,$this->last);
		}
		else
		{
			$this->_week_align_month($this->first,$this->last);
			$weekNavH = "1 month";
			$navHeader = lang(adodb_date('F',$this->bo->date2ts($this->date))).' '.$this->year;
		}
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(%1) date=%2: first=%3, last=%4',False,$weeks,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		// Loop through, using Api\DateTime to handle DST
		$week = 0;
		$week_start = new EGroupware\Api\DateTime($this->first);
		$week_start->setTime(0,0,0);
		$week_end = new Api\DateTime($week_start);
		$week_end->add(new DateInterval('P6DT23H59M59S'));
		$last = new EGroupware\Api\DateTime($this->last);
		for ($week_start; $week_start < $last; $week_start->add('1 week'), $week_end->add('1 week'))
		{
			$search_params = $this->search_params;
			$search_params['start'] = $week_start->format('ts');
			$search_params['end'] = $week_end->format('ts');

			$content['view'][] = (array)$this->tagWholeDayOnTop($this->bo->search($search_params)) +
			array(
				'id' => $week_start->format('Ymd')
			);
			$home->setElementAttribute("view[$week]",'onchange',false);
			$home->setElementAttribute("view[$week]",'granularity',$granularity);
			$home->setElementAttribute("view[$week]",'show_weekend', $this->search_params['weekend']);
			$week++;
		}

		// Get the actions
		$home->setElementAttribute('view','actions',$this->get_actions());

		$home->exec(__METHOD__, $content);
	}

	/**
	 * get start and end of a month aligned to full weeks
	 *
	 * @param int &$first timestamp 0h of first day of week containing the first of the current month
	 * @param int &$last timestamp 23:59:59 of last day of week containg the last day of the current month
	 * @param int $day = 1 should the alignment be based on the 1. of the month or an other date, eg. the 15.
	 */
	function _week_align_month(&$first,&$last,$day=1)
	{
		$start = new Api\DateTime($this->date);
		$start->setDate($this->year,$this->month,$this->day=$day);
		$start->setWeekstart();
		$first = $start->format('ts');
		$start->setDate($this->year,$this->month+1,$day);
		if ($day == 1) $start->add('-1day');
		$start->setWeekstart();
		// now we need to calculate the end of the last day of that week
		// as simple $last += WEEK_s - 1; does NOT work, if daylight saving changes in that week!!!
		$arr = $start->format('array');
		$arr['day'] += 6;
		$arr['hour'] = 23;
		$arr['min'] = $arr['sec'] = 59;
		unset($arr['raw']);	// otherwise date2ts does not calc raw new, but uses it
		$last = $this->bo->date2ts($arr);
	}

	/**
	 * Displays the weekview, with 5 or 7 days
	 *
	 * @param int $days = 0 number of days to show, if 0 (default) the value from the URL or the prefs is used
	 * @param boolean|etemplate2 $home = false if not false return content suitable for home-page
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

		if ($days <= 4)		// next 4 days view
		{
			$wd_start = $this->first = $this->bo->date2ts($this->date);
			$this->last = strtotime("+$days days",$this->first) - 1;
			$view = $days == 1 ? 'day' : 'day4';
		}
		else
		{
			$start = new Api\DateTime($this->date);
			$start->setWeekstart();
			$wd_start = $this->first = $start->format('ts');
			if ($days <= 5)		// no weekend-days
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
			$view = 'week';
		}

		$granularity = ($this->cal_prefs['interval'] ? (int)$this->cal_prefs['interval'] : 30);

		$list = $this->cal_prefs['use_time_grid'];
		if(!is_array($list))
		{
			$list = explode(',',$list);
		}
		if(is_array($list))
		{
			$granularity = in_array($view,$list) ? 0 : $granularity;
		}

		$search_params = array(
				'start'   => $this->first,
				'end'     => $this->last,
			) + $this->search_params;

		$users = $this->search_params['users'];
		if (!is_array($users)) $users = array($users);

		$content = array('view' => array());

		if(!$home)
		{
			// Fill with the minimum needed 'weeks'
			$min = max(
				6, // Some months need 6 weeks for full display
				$this->cal_prefs['multiple_weeks'],  // WeekN view
				$this->cal_prefs['week_consolidate'] // We collapse after this many users
			);
			for($i = 0; $i < $min; $i++)
			{
				$content['view'][] = array();
			}
		}
		else
		{
			// Always do 7 days for a week so scrolling works properly
			$this->last = ($days == 4 ? $this->last : $search_params['end'] = strtotime("+$days days",$this->first) - 1);
			if (count($users) == 1 || count($users) >= $this->cal_prefs['week_consolidate']	||// for more then X users, show all in one row
				$days == 1 // Showing just 1 day
			)
			{
				$content['view'][] = $this->tagWholeDayOnTop($this->bo->search($search_params)) +
					array('owner' => $users);
			}
			else
			{
				foreach($users as $uid)
				{
					$search_params['users'] = $uid;
					$content['view'][] = $this->tagWholeDayOnTop($this->bo->search($search_params))
						 + array('owner' => $uid);
				}
			}
		}
		$tmpl = $home ? $home :new Etemplate('calendar.view');
		foreach(array_keys($content['view']) as $index)
		{
			$tmpl->setElementAttribute("view[$index]",'granularity',$granularity);
			$tmpl->setElementAttribute("view[$index]",'show_weekend',$this->search_params['weekend']);
		}

		// Get the actions
		$tmpl->setElementAttribute('view','actions',$this->get_actions());

		$tmpl->exec(__METHOD__, $content);
	}

	/**
	 * Get todos via AJAX
	 */
	public static function ajax_get_todos($_date, $owner)
	{
		$date = Api\DateTime::to($_date, 'array');
		$ui = new calendar_uiviews();
		$ui->year = $date['year'];
		$ui->month = $date['month'];
		$ui->day = $date['day'];
		$ui->owner = (int)$owner;

		$label = '';
		$todos = $ui->get_todos($label);
		Api\Json\Response::get()->data(array(
			'label' => $label,
			'todos' => $todos
		));
	}

	/**
	 * Query the open ToDo's via a hook from InfoLog or any other 'calendar_include_todos' provider
	 *
	 * @param array/string $todo_label label for the todo-box or array with 2 values: the label and a boolean show_all
	 *	On return $todo_label contains the label for the todo-box
	 * @return string/boolean Api\Html with a table of open todo's or false if no hook availible
	 */
	function get_todos(&$todo_label)
	{
		$todos_from_hook = Api\Hooks::process(array(
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

		$todo_label = '';
		$todo_list = array();
		if (is_array($todos_from_hook) && count($todos_from_hook))
		{
			foreach($todos_from_hook as $todos)
			{
				$i = 0;
				if (is_array($todos))
				{
					$todo_label = !empty($label) ? $label : lang("open ToDo's:");

					foreach($todos as &$todo)
					{
						if(!$showall && ($i++ > $maxshow))
						{
							break;
						}
						$icons = '';
						foreach($todo['icons'] as $name => $alt)
						{
							$icons .= ($icons?' ':'').Api\Html::image('infolog',$name,lang($alt),'border="0" width="15" height="15"');
						}
						$todo['icons'] = $icons;
						if($todo['edit']) {
							$todo['edit_size'] = $todo['edit']['size'];
							unset($todo['edit']['size']);
							$edit_icon_href = Api\Html::a_href( $icons, $todo['edit'],'',' data-todo="app|750x590" ');
							$edit_href = Api\Html::a_href( $todo['title'], $todo['edit'],'',' data-todo="app|750x590" ');
							$todo['edit'] = Framework::link('/index.php',$todo['edit'],true);
						}
						$todo_list[] = $todo;
					}
				}
			}
		}
		return $todo_list;
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
	public static function integration_get_popup($app,$id)
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
			$edit = Link::edit($app,$id,$popup_size);
		}
		if ($edit)
		{
			if ($popup_size)
			{
				$popup = ' data-app="app|'.$popup_size.'"';
			}
			else
			{
				$popup = ' data-app="app|'.$app.'|'.'"';
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
				if (Api\Image::find($icon_app,$icon))
				{
					$icons[] = Api\Html::image($icon_app,$icon);
				}
			}
		}
		$app_data = calendar_bo::integration_get_data($app,'icons');
		if (is_null($app_data))
		{
			if (($icon = Api\Link::get_registry($app,'icon')) &&
				($icon = explode('/', $icon)))
			{

				$icons[] = Api\Html::image($icon[0], $icon[1]);	// use icon from link registry
			}
			else
			{
				$icons[] = Api\Html::image($app,'navbar');	// use navbar icon
			}
		}
		elseif ($app_data)
		{
			$icons += (array)ExecMethod2($app_data,$id,$event);
		}
		return $icons;
	}

	/**
	 * Get the actions for the non-list views
	 *
	 * We use the actions from the list as a base, and only change what we have to
	 * to get it to work outside of a nextmatch.
	 *
	 * @return Array
	 */
	protected static function get_actions()
	{
		// Just copy from the list, but change to match our needs
		$ui = new calendar_uilist();
		$actions = $ui->get_actions();

		unset($actions['no_notifications']);
		unset($actions['select_all']);

		// This disables the event actions for the grid rows (calendar weeks/owners)
		foreach($actions as $id => &$action)
		{
			if($id=='add') continue;
			if(!$action['enabled'])
			{
				$action['enabled'] = 'javaScript:app.calendar.is_event';
			}
		}
		$actions['add']['open'] = '{"app":"calendar","type":"add"}';
		$actions['add']['onExecute'] =  'javaScript:app.calendar.action_open';
		$actions['copy']['open'] = '{"app": "calendar", "type": "edit", "extra": "cal_id=$id&action=copy"}';
		$actions['copy']['onExecute'] = 'javaScript:app.calendar.action_open';
		$actions['print']['open'] = '{"app": "calendar", "type": "edit", "extra": "cal_id=$id&print=1"}';
		$actions['print']['onExecute'] = 'javaScript:app.calendar.action_open';
		$actions['notifications']['onExecute'] = 'javaScript:app.calendar.action_open';

		foreach($actions['status']['children'] as $id => &$status)
		{
			$status = array(
				'id' => $id,
				'caption' => $status,
				'onExecute' => 'javaScript:app.calendar.status'
			);
		}

		if ($actions['filemanager'])
		{
			$actions['filemanager']['url'] = '/index.php?'. $actions['filemanager']['url'];
			$actions['filemanager']['onExecute'] = 'javaScript:app.calendar.action_open';
		}
		if ($actions['infolog_app'])
		{
			$actions['infolog_app']['open'] = '{"app": "infolog", "type": "add", "extra": "type=task&action=$app&action_id=$id"}';
			$actions['infolog_app']['onExecute'] = 'javaScript:app.calendar.action_open';
		}
		$actions['ical']['onExecute'] = 'javaScript:app.calendar.ical';

		$actions['delete']['onExecute'] = 'javaScript:app.calendar.delete';

		return $actions;
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
					$this->to_client($dayEvents[$day][$num]);
				}
				// check after every day if we have to increase $this->extraRows
				if(($this->extraRowsOriginal+$extraRowsToAdd) > $this->extraRows)
				{
					$this->remBotExtraRows = $extraRowsToAdd;
					$this->extraRows = ($this->extraRowsOriginal+$extraRowsToAdd);
				}
			}
		}
		else
		{
			$dayEvents = [];    // search returns false or null for nothing found!
		}
		return $dayEvents;
 	}
}
