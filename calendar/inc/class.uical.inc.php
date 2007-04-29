<?php
/**
 * eGroupWare - Calendar's shared base-class of all UI classes
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-7 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
 */
class uical
{
	/**
	 * @var $debug mixed integer level or string function-name
	 */
	var $debug=false;
	/**
	 * instance of the bocal or bocalupdate class
	 * 
	 * @var bocalupdate
	 */
	var $bo;
	/**
	 * instance of jscalendar
	 *
	 * @var jscalendar
	 */
	var $jscal;
	/**
	 * Reference to global html class
	 *
	 * @var html
	 */
	var $html;
	/**
	 * Reference to global datetime class
	 *
	 * @var datetime
	 */
	var $datetime;
	/**
	 * Reference to global categories class
	 *
	 * @var categories
	 */
	var $cats;
	/**
	 * Reference to global uiaccountsel class
	 *
	 * @var uiaccountsel
	 */
	var $accountsel;
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
	 * @var int $filter session-state: selected filter, at the moment all or hideprivate
	 */
	var $filter;
	/**
	 * @var int/array $owner session-state: selected owner(s) of shown calendar(s)
	 */
	var $owner;
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
		$this->bo->check_set_default_prefs();

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
	 * Checks and terminates (or returns for home) with a message if $this->owner include a user/resource we have no read-access to
	 *
	 * If currentapp == 'home' we return the error instead of terminating with it !!!
	 *
	 * @return boolean/string false if there's no error or string with error-message
	 */
	function check_owners_access()
	{
		$no_access = $no_access_group = array();
		foreach(explode(',',$this->owner) as $owner)
		{
			if (is_numeric($owner) && $GLOBALS['egw']->accounts->get_type($owner) == 'g')
			{
				foreach($GLOBALS['egw']->accounts->member($owner) as $member)
				{
					$member = $member['account_id'];
					if (!$this->bo->check_perms(EGW_ACL_READ,0,$member))
					{
						$no_access_group[$member] = $this->bo->participant_name($member);
					} 
				}
			}
			elseif (!$this->bo->check_perms(EGW_ACL_READ,0,$owner))
			{
				$no_access[$owner] = $this->bo->participant_name($owner);
			}
		}
		if (count($no_access))
		{
			$msg = '<p class="redItalic" align="center">'.lang('Access denied to the calendar of %1 !!!',implode(', ',$no_access))."</p>\n";
		
			if ($GLOBALS['egw_info']['flags']['currentapp'] == 'home')
			{
				return $msg;
			}
			$GLOBALS['egw']->common->egw_header();
			if ($GLOBALS['egw_info']['flags']['nonavbar']) parse_navbar();

			echo $msg;

			$GLOBALS['egw']->common->egw_footer();
			$GLOBALS['egw']->common->egw_exit();
		}
		if (count($no_access_group))
		{
			$this->group_warning = lang('Groupmember(s) %1 not included, because you have no access.',implode(', ',$no_access_group));
		}
		return false;
	}
	
	/**
	 * show the egw-framework plus possible messages ($_GET['msg'] and $this->group_warning from check_owner_access)
	 */
	function do_header()
	{
		$GLOBALS['egw_info']['flags']['include_xajax'] = true;
		$GLOBALS['egw']->common->egw_header();
		
		if ($_GET['msg']) echo '<p class="redItalic" align="center">'.$this->html->htmlspecialchars($_GET['msg'])."</p>\n";

		if ($this->group_warning) echo '<p class="redItalic" align="center">'.$this->group_warning."</p>\n";
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
	 *	- filter: the used filter: all or hideprivate
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
			'save_owner' => 0,
			'sortby'     => 'category',
			'planner_days'=> 0,	// full month
			'view'       => $this->bo->cal_prefs['defaultcalendar'],
			'listview_days'=> '',	// no range
		) as $state => $default)
		{
			if (isset($set_states[$state]))
			{
				if ($state == 'owner')
				{
					// only change the owners of the same resource-type as given in set_state[owner]
					$set_owners = explode(',',$set_states['owner']);
					if ((string)$set_owners[0] === '0')	// set exactly the specified owners (without the 0)
					{
						$set_states['owner'] = substr($set_states['owner'],2);
					}
					else	// change only the owners of the given type
					{
						$res_type = is_numeric($set_owners[0]) ? false : $set_owners[0]{0};
						$owners = explode(',',$states['owner'] ? $states['owner'] : $default);
						foreach($owners as $key => $owner)
						{
							if (!$res_type && is_numeric($owner) || $res_type && $owner{0} == $res_type)
							{
								unset($owners[$key]);
							}
						}
						if (!$res_type || !in_array($res_type.'0',$set_owners))
						{
							$owners = array_merge($owners,$set_owners);
						}
						$set_states['owner'] = implode(',',$owners);
					}
				}
				// for the uiforms class (eg. edit), dont store the (new) owner, as it might change the view
				if (substr($_GET['menuaction'],0,16) == 'calendar.uiforms')
				{
					$this->owner = $set_states[$state];
					continue;
				}
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
		if ($_GET['menuaction'])
		{
			list($app,$class,$func) = explode('.',$_GET['menuaction']);
		}
		else	// eg. calendar/index.php
		{
			$func = $this->view;
			$class = $this->view == 'listview' ? 'uilist' : 'uiviews';
		}
		if ($class == 'uiviews' || $class == 'uilist')
		{
			// if planner_start_with_group is set in the users prefs: switch owner for planner to planner_start_with_group and back
			if ($this->cal_prefs['planner_start_with_group'])
			{
				if ($this->cal_prefs['planner_start_with_group'] > 0) $this->cal_prefs['planner_start_with_group'] *= -1;	// fix old 1.0 pref

				if (!$states_session && !$_GET['menuaction']) $this->view = '';		// first call to calendar 

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

		if ($this->debug > 0 || $this->debug == 'manage_states') $this->bo->debug_message('uical::manage_states(%1) session was %2, states now %3',True,$set_states,$states_session,$states);
		// save the states in the session
		$GLOBALS['egw']->session->appsession('session_data','calendar',$states);
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
			// icons for single user, multiple users or group(s) and resources
			foreach($event['participants'] as  $uid => $status)
			{
				if(is_numeric($uid))
				{
					if (isset($icons['single']) || $GLOBALS['egw']->accounts->get_type($uid) == 'g')
					{
						unset($icons['single']);
						$icons['multiple'] = $this->html->image('calendar','users');
					}
					elseif (!isset($icons['multiple']))
					{
						$icons['single'] = $this->html->image('calendar','single');
					}
				}					
				elseif(!isset($icons[$uid{0}]) && isset($this->bo->resources[$uid{0}]) && isset($this->bo->resources[$uid{0}]['icon']))
				{
				 	$icons[$uid{0}] = $this->html->image($this->bo->resources[$uid{0}]['app'],
				 		($this->bo->resources[$uid{0}]['icon'] ? $this->bo->resources[$uid{0}]['icon'] : 'navbar'),
				 		lang($this->bo->resources[$uid{0}]['app']),
				 		'width="16px" height="16px"');
				}
			}
		}
		if($event['non_blocking'])
		{
			$icons[] = $this->html->image('calendar','nonblocking',lang('non blocking'));
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
			$baseurl .= strpos($baseurl,'?') === False ? '?' : '&';
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
			'planner' => array('icon'=>'planner','text'=>'Group planner','menuaction' => 'calendar.uiviews.planner','sortby' => $this->sortby),
			'list' => array('icon'=>'list','text'=>'Listview','menuaction'=>'calendar.uilist.listview'),
		) as $view => $data)
		{
			$icon = array_shift($data);
			$title = array_shift($data);
			$vars = array_merge($link_vars,$data);

			$icon = $this->html->image('calendar',$icon,lang($title));
			$link = $view == 'add' ? $this->add_link($icon) : $this->html->a_href($icon,'/index.php',$vars);

			$views .= '<td align="center">'.$link."</td>\n";
		}
		$views .= "</tr></table>\n";

		$file[++$n] = array('text' => $views,'no_lang' => True,'link' => False,'icon' => False);

		// special views and view-options menu
		$options = '';
		foreach(array(
			array(
				'text' => lang('dayview'),
				'value' => 'menuaction=calendar.uiviews.day',
				'selected' => $this->view == 'day',
			),
			array(
				'text' => lang('four days view'),
				'value' => 'menuaction=calendar.uiviews.day4',
				'selected' => $this->view == 'day4',
			),
			array(
				'text' => lang('weekview with weekend'),
				'value' => 'menuaction=calendar.uiviews.week&days=7',
				'selected' => $this->view == 'week' && $this->cal_prefs['days_in_weekview'] != 5,
			),
			array(
				'text' => lang('weekview without weekend'),
				'value' => 'menuaction=calendar.uiviews.week&days=5',
				'selected' => $this->view == 'week' && $this->cal_prefs['days_in_weekview'] == 5,
			),
			array(
				'text' => lang('monthview'),
				'value' => 'menuaction=calendar.uiviews.month',
				'selected' => $this->view == 'month',
			),
			array(
				'text' => lang('planner by category'),
				'value' => 'menuaction=calendar.uiviews.planner&sortby=category'.
					($planner_days_for_view !== false ? '&planner_days='.$planner_days_for_view : ''),
				'selected' => $this->view == 'planner' && $this->sortby != 'user',
			),
			array(
				'text' => lang('planner by user'),
				'value' => 'menuaction=calendar.uiviews.planner&sortby=user'.
					($planner_days_for_view !== false ? '&planner_days='.$planner_days_for_view : ''),
				'selected' => $this->view == 'planner' && $this->sortby == 'user',
			),
			array(
				'text' => lang('listview'),
				'value' => 'menuaction=calendar.uilist.listview',
				'selected' => $this->view == 'listview',
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
			if ($this->view == 'planner' || $this->view == 'listview')	
			{
				switch($view)
				{
					case 'day':   $link_vars[$this->view.'_days'] = $this->view == 'planner' ? 1 : ''; break;
					case 'week':  $link_vars[$this->view.'_days'] = $this->cal_prefs['days_in_weekview'] == 5 ? 5 : 7; break;
					case 'month': $link_vars[$this->view.'_days'] = 0; break;
				}
				$link_vars['menuaction'] = $this->view_menuaction;	// stay in the planner
			}
			elseif ($view == 'day' && $this->view == 'day4')
			{
				$link_vars['menuaction'] = $this->view_menuaction;	// stay in the day-view
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

		// Filter all or hideprivate
		$file[] = $this->_select_box('Filter','filter',
			'<option value="all"'.($this->filter=='all'?' selected="selected"':'').'>'.lang('No filter').'</option>'."\n".
			'<option value="hideprivate"'.($this->filter=='hideprivate'?' selected="selected"':'').'>'.lang('Hide private infos').'</option>'."\n");

		// Calendarselection: User or Group
		if(count($this->bo->grants) > 0 && $this->accountsel->account_selection != 'none')
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
			if (!$accounts) $grants[''] = lang('None');
			$file[] = array(
				'text' => "
<script type=\"text/javascript\">
function load_cal(url,id) {
	var owner='';
	selectBox = document.getElementById(id);
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
				$this->accountsel->selection('owner','uical_select_owner',$accounts,'calendar+',count($accounts) > 1 ? 4 : 1,False,
					' style="width: '.(count($accounts) > 1 && $this->common_prefs['account_selection']=='selectbox' ? 185 : 165).'px;"'.
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
		$appname = 'calendar';
		$menu_title = lang('Calendar Menu');
		display_sidebox($appname,$menu_title,$file);
		
		// resources menu hooks
 		foreach ($this->bo->resources as $resource)
		{
			if(!is_array($resource['cal_sidebox'])) continue;
			$menu_title = $resource['cal_sidebox']['menu_title'] ? $resource['cal_sidebox']['menu_title'] : lang($resource['app']);
			$file = ExecMethod($resource['cal_sidebox']['file'],array(
				'menuaction' => $this->view_menuaction,
				'owner' => $this->owner,
			));
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
