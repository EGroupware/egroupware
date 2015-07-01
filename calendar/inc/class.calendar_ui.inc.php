<?php
/**
 * eGroupWare - Calendar's shared base-class of all UI classes
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-15 by RalfBecker-At-outdoor-training.de
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
class calendar_ui
{
	/**
	 * @var $debug mixed integer level or string function-name
	 */
	var $debug=false;
	/**
	 * instance of the bocal or bocalupdate class
	 *
	 * @var calendar_boupdate
	 */
	var $bo;
	/**
	 * instance of jscalendar
	 *
	 * @var jscalendar
	 */
	var $jscal;
	/**
	 * Reference to global datetime class
	 *
	 * @var egw_datetime
	 */
	var $datetime;
	/**
	 * Instance of categories class
	 *
	 * @var categories
	 */
	var $categories;
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
	 * @var boolean test checkbox checked
	 */
	var $test;

	/**
	 * @var int $first first day of the shown view
	 */
	var $first;
	/**
	 * @var int $last last day of the shown view
	 */
	var $last;

	/**
	 * @var array $states_to_save all states that will be saved to the user prefs
	 */
	var $states_to_save = array('owner','filter','cat_id','view','sortby','planner_days');

	/**
	 * Constructor
	 *
	 * @param boolean $use_boupdate use bocalupdate as parenent instead of bocal
	 * @param array $set_states to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function __construct($use_boupdate=false,$set_states=NULL)
	{
		if ($use_boupdate)
		{
			$this->bo = new calendar_boupdate();
		}
		else
		{
			$this->bo = new calendar_bo();
		}
		$this->jscal = $GLOBALS['egw']->jscalendar;
		$this->datetime = $GLOBALS['egw']->datetime;
		$this->accountsel = $GLOBALS['egw']->uiaccountsel;

		$this->categories = new categories($this->user,'calendar');

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

		// make sure the hook for export_limit is registered
		if (!$GLOBALS['egw']->hooks->hook_exists('export_limit','calendar')) $GLOBALS['egw']->hooks->register_single_app_hook('calendar','export_limit');
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
			$owner = trim($owner);
			if (is_numeric($owner) && $GLOBALS['egw']->accounts->get_type($owner) == 'g')
			{
				foreach($GLOBALS['egw']->accounts->member($owner) as $member)
				{
					$member = $member['account_id'];
					if (!$this->bo->check_perms(EGW_ACL_READ|EGW_ACL_READ_FOR_PARTICIPANTS|EGW_ACL_FREEBUSY,0,$member))
					{
						$no_access_group[$member] = $this->bo->participant_name($member);
					}
				}
			}
			elseif (!$this->bo->check_perms(EGW_ACL_READ|EGW_ACL_READ_FOR_PARTICIPANTS|EGW_ACL_FREEBUSY,0,$owner))
			{
				$no_access[$owner] = $this->bo->participant_name($owner);
			}
		}
		if (count($no_access))
		{
			$msg = '<p class="message" align="center">'.htmlspecialchars(lang('Access denied to the calendar of %1 !!!',implode(', ',$no_access)))."</p>\n";

			if ($GLOBALS['egw_info']['flags']['currentapp'] == 'home')
			{
				return $msg;
			}
			common::egw_header();
			if ($GLOBALS['egw_info']['flags']['nonavbar']) parse_navbar();

			echo $msg;

			common::egw_footer();
			common::egw_exit();
		}
		if (count($no_access_group))
		{
			$this->bo->warnings['groupmembers'] = lang('Groupmember(s) %1 not included, because you have no access.',implode(', ',$no_access_group));
		}
		return false;
	}

	/**
	 * show the egw-framework plus evtl. $this->group_warning from check_owner_access
	 */
	function do_header()
	{
		// Include the jQuery-UI CSS - many more complex widgets use it
		$theme = 'redmond';
		egw_framework::includeCSS("/phpgwapi/js/jquery/jquery-ui/$theme/jquery-ui-1.10.3.custom.css");
		// Load our CSS after jQuery-UI, so we can override it
		egw_framework::includeCSS('/etemplate/templates/default/etemplate2.css');

		// load etemplate2
		egw_framework::validate_file('/etemplate/js/etemplate2.js');

		// load our app.js file
		egw_framework::validate_file('/calendar/js/app.js');

		common::egw_header();

		if ($this->bo->warnings) echo '<pre class="message" align="center">'.html::htmlspecialchars(implode("\n",$this->bo->warnings))."</pre>\n";
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
	 * @param array $set_states array to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function manage_states($set_states=NULL)
	{
		$states = $states_session = $GLOBALS['egw']->session->appsession('session_data','calendar');

		// retrieve saved states from prefs
		if(!$states)
		{
			$states = unserialize($this->bo->cal_prefs['saved_states']);
			error_log(array2string($states));
		}
		// only look at _REQUEST, if we are in the calendar (prefs and admin show our sidebox menu too!)
		if (is_null($set_states))
		{
			// ajax-exec call has get-parameter in some json
			if (isset($_REQUEST['json_data']) && ($json_data = json_decode($_REQUEST['json_data'], true)) &&
				!empty($json_data['request']['parameters'][0]))
			{
				if (is_array($json_data['request']['parameters'][0]))
				{
					//error_log(__METHOD__.__LINE__.array2string($json_data['request']['parameters'][0]));
					$set_states = $json_data['request']['parameters'][0];
				}
				else
				{
					parse_str(substr($json_data['request']['parameters'][0], 10), $set_states);	// cut off "/index.php?"
				}
			}
			else
			{
				$set_states = substr($_GET['menuaction'],0,9) == 'calendar.' ? $_REQUEST : array();
			}
		}
		if (!$states['date'] && $states['year'] && $states['month'] && $states['day'])
		{
			$states['date'] = $this->bo->date2string($states);
		}

		foreach(array(
			'date'       => $this->bo->date2string($this->bo->now_su),
			'cat_id'     => 0,
			'filter'     => 'default',
			'owner'      => $this->user,
			'save_owner' => 0,
			'sortby'     => 'category',
			'planner_days'=> 0,	// full month
			'view'       => ($this->bo->cal_prefs['defaultcalendar']?$this->bo->cal_prefs['defaultcalendar']:'day'), // use pref, if exists else use the dayview
			'listview_days'=> '',	// no range
			'test'       => 'false',
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
						if ($set_states['owner'] === '0,r0')	// small fix for resources
						{
							$set_states['owner'] = $default;	// --> set default, instead of none
						}
						else
						{
							$set_states['owner'] = substr($set_states['owner'],2);
						}
					}
					else	// change only the owners of the given type
					{
						$res_type = is_numeric($set_owners[0]) ? false : $set_owners[0][0];
						$owners = explode(',',$states['owner'] ? $states['owner'] : $default);
						foreach($owners as $key => $owner)
						{
							if (!$res_type && is_numeric($owner) || $res_type && $owner[0] == $res_type)
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
				if (substr($_GET['menuaction'],0,25) == 'calendar.calendar_uiforms')
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
		// remove a given calendar from the view
		if (isset($_GET['close']) && ($k = array_search($_GET['close'], $owners=explode(',',$this->owner))) !== false)
		{
			unset($owners[$k]);
			$this->owner = $states['owner'] = implode(',',$owners);
		}

		if (substr($this->view,0,8) == 'planner_')
		{
			$states['sortby'] = $this->sortby = $this->view == 'planner_cat' ? 'category' : 'user';
			$states['view'] = $this->view = 'planner';
		}
		// set the actual view as return_to
		if (isset($_GET['menuaction']))
		{
			list(,$class,$func) = explode('.',$_GET['menuaction']);
			if ($func == 'index')
			{
				$func = $this->view; $this->view = 'index';	// switch to the default view
			}
		}
		else	// eg. calendar/index.php
		{
			$func = $this->view;
			$class = $this->view == 'listview' ? 'calendar_uilist' : 'calendar_uiviews';
		}
		if ($class == 'calendar_uiviews' || $class == 'calendar_uilist')
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
		$this->view_menuaction = $this->view == 'listview' ? 'calendar.calendar_uilist.listview' : 'calendar.calendar_uiviews.index';

		if ($this->debug > 0 || $this->debug == 'manage_states') $this->bo->debug_message('uical::manage_states(%1) session was %2, states now %3',True,$set_states,$states_session,$states);
		// save the states in the session only when we are in calendar
		if ($GLOBALS['egw_info']['flags']['currentapp']=='calendar')
		{
			$GLOBALS['egw']->session->appsession('session_data','calendar',$states);
			// save defined states into the user-prefs
			if(!empty($states) && is_array($states))
			{
				$saved_states = serialize(array_intersect_key($states,array_flip($this->states_to_save)));
				if ($saved_states != $this->cal_prefs['saved_states'])
				{
					$GLOBALS['egw']->preferences->add('calendar','saved_states',$saved_states);
					$GLOBALS['egw']->preferences->save_repository(false,'user',true);
				}

				// store state in request for clientside favorites to use
				// remove date and other states never stored in a favorite
				$states = array_diff_key($states,array('date'=>false,'year'=>false,'month'=>false,'day'=>false,'save_owner'=>false));
				if (strpos($_GET['menuaction'], 'ajax_sidebox') !== false)
				{
					// sidebox request is from top frame, which has app.calendar NOT loaded by time response arrives
				}
				elseif (egw_json_request::isJSONRequest())// && strpos($_GET['menuaction'], 'calendar_uiforms') === false)
				{
					$response = egw_json_response::get();
					//$response->apply('app.calendar.set_state', array($states, $_GET['menuaction']));
				}
				else
				{
					egw_framework::set_extra('calendar', 'state', $states);
				}

			}
		}
	}

	/**
	* gets the icons displayed for a given event
	*
	* @param array $event
	* @return array of 'img' / 'title' pairs
	*/
	function event_icons($event)
	{
		$is_private = !$event['public'] && !$this->bo->check_perms(EGW_ACL_READ,$event);

		$icons = array();
		if (!$is_private)
		{
			if($event['priority'] == 3)
			{
				$icons[] = html::image('calendar','high',lang('high priority'));
			}
			if($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$icons[] = html::image('calendar','recur',lang('recurring event'));
			}
			// icons for single user, multiple users or group(s) and resources
			foreach(array_keys($event['participants']) as  $uid)
			{
				if(is_numeric($uid) || !isset($this->bo->resources[$uid[0]]['icon']))
				{
					if (isset($icons['single']) || $GLOBALS['egw']->accounts->get_type($uid) == 'g')
					{
						unset($icons['single']);
						$icons['multiple'] = html::image('calendar','users');
					}
					elseif (!isset($icons['multiple']))
					{
						$icons['single'] = html::image('calendar','single');
					}
				}
				elseif(!isset($icons[$uid[0]]) && isset($this->bo->resources[$uid[0]]) && isset($this->bo->resources[$uid[0]]['icon']))
				{
				 	$icons[$uid[0]] = html::image($this->bo->resources[$uid[0]]['app'],
				 		($this->bo->resources[$uid[0]]['icon'] ? $this->bo->resources[$uid[0]]['icon'] : 'navbar'),
				 		lang($this->bo->resources[$uid[0]]['app']),
				 		'width="16px" height="16px"');
				}
			}
		}
		if($event['non_blocking'])
		{
			$icons[] = html::image('calendar','nonblocking',lang('non blocking'));
		}
		if($event['public'] == 0)
		{
			$icons[] = html::image('calendar','private',lang('private'));
		}
		if(isset($event['alarm']) && count($event['alarm']) >= 1 && !$is_private)
		{
			$icons[] = html::image('calendar','alarm',lang('alarm'));
		}
		if($event['participants'][$this->user][0] == 'U')
		{
			$icons[] = html::image('calendar','cnr-pending',lang('Needs action'));
		}
		return $icons;
	}

	/**
	* Create a select-box item in the sidebox-menu
	* @privat used only by sidebox_menu !
	*/
	function _select_box($title,$name,$options,$width='99%')
	{
		$select = " <select style=\"width: $width;\" name=\"".$name.'" id="calendar_'.$name.'" title="'.
			lang('Select a %1',lang($title)).'">'.
			$options."</select>\n";

		return array(
			'text' => $select,
			'no_lang' => True,
			'link' => False,
			'icon' => false,
		);
	}

	/**
	 * Generate a link to add an event, incl. the necessary popup
	 *
	 * @param string $content content of the link
	 * @param string $date which date should be used as start- and end-date, default null=$this->date
	 * @param int $hour which hour should be used for the start, default null=$this->hour
	 * @param int $minute start-minute
	 * @param array $vars
	 * @return string the link incl. content
	 */
	function add_link($content,$date=null,$hour=null,$minute=0,array $vars=null)
	{
		$vars['menuaction'] = 'calendar.calendar_uiforms.edit';
		$vars['date'] =  $date ? $date : $this->date;

		if (!is_null($hour))
		{
			$vars['hour'] = $hour;
			$vars['minute'] = $minute;
		}
		return html::a_href($content,'',$vars,' data-date="' .$vars['date'].'|'.$vars['hour'].'|'.$vars['minute']
				. '" title="'.html::htmlspecialchars(lang('Add')).'"');
	}

	/**
	 * returns javascript to open a popup window: window.open(...)
	 *
	 * @param string $link link or this.href
	 * @param string $target name of target or this.target
	 * @param int $width width of the window
	 * @param int $height height of the window
	 * @return string javascript (using single quotes)
	 */
	function popup($link,$target='_blank',$width=750,$height=410)
 	{
		return 'egw_openWindowCentered2('.($link == 'this.href' ? $link : "'".$link."'").','.
			($target == 'this.target' ? $target : "'".$target."'").",$width,$height,'yes')";
 	}

	/**
	 * creates the content for the sidebox-menu, called as hook
	 */
	function sidebox_menu()
	{
		$link_vars = array();
		// Magic etemplate2 favorites menu (from framework)
		display_sidebox('calendar', lang('Favorites'), egw_framework::favorite_list('calendar'));

		$file = array('menuOpened' => true);	// menu open by default

		// Target for etemplate
		$file[] = array(
			'no_lang' => true,
			'text'=>'<span id="calendar-et2_target" />',
			'link'=>false,
			'icon' => false
		);

		// Merge print placeholders (selectbox in etemplate)
		if ($GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir'])
		{			
			$file['Placeholders'] = egw::link('/index.php','menuaction=calendar.calendar_merge.show_replacements');
		}
		$appname = 'calendar';
		$menu_title = lang('Calendar Menu');
		display_sidebox($appname,$menu_title,$file);

		$this->sidebox_etemplate();

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

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Configuration'=>egw::link('/index.php','menuaction=admin.uiconfig.index&appname=calendar'),
				'Custom Fields'=>egw::link('/index.php','menuaction=admin.customfields.index&appname=calendar'),
				'Holiday Management'=>egw::link('/index.php','menuaction=calendar.uiholiday.admin'),
				'Global Categories' =>egw::link('/index.php','menuaction=admin.admin_categories.index&appname=calendar'),
			);
			$GLOBALS['egw']->framework->sidebox($appname,lang('Admin'),$file,'admin');
		}
	}

	/**
	 * Makes the sidebox content with etemplate, after hook is processed
	 */
	function sidebox_etemplate($content = array())
	{
		if($content['merge'])
		{
			// View from sidebox is JSON encoded
			$this->manage_states(array_merge($content,json_decode($content['view'],true)));
			if($content['first'])
			{
				$this->first = egw_time::to($content['first'],'ts');
			}
			if($content['last'])
			{
				$this->last = egw_time::to($content['last'],'ts');
			}
			$_GET['merge'] = $content['merge'];
			$this->merge();
			return;
		}
		$sidebox = new etemplate_new('calendar.sidebox');


		$content['view'] = $this->view ? $this->view : 'week';
		$content['date'] = $this->date ? $this->date : egw_time();
		$owners = $this->owner ? is_array($this->owner) ? array($this->owner) : explode(',',$this->owner) : array($GLOBALS['egw_info']['user']['account_id']);
/*
		foreach($owners as $owner)
		{
			$app = 'home-accounts';
			switch(substr($owner, 0,1))
			{
				case 'r':
					$app = 'resources';
					break;
			}
			$content['owner'][] = array('app' => $app, 'id' => (int)$owner ? $owner : substr($owner,1));
		}
*/
		$sel_options = array();
		$readonlys = array();
		foreach(array(
			array(
				'text' => lang('dayview'),
				'value' => '{"view":"day"}',
				'selected' => $this->view == 'day',
			),
			array(
				'text' => lang('four days view'),
				'value' => '{"view":"day4","days":4}',
				'selected' => $this->view == 'day4',
			),
			array(
				'text' => lang('weekview with weekend'),
				'value' => '{"view":"week","days":7}',
				'selected' => $this->view == 'week' && $this->cal_prefs['days_in_weekview'] != 5,
			),
			array(
				'text' => lang('weekview without weekend'),
				'value' => '{"view":"week","days":5}',
				'selected' => $this->view == 'week' && $this->cal_prefs['days_in_weekview'] == 5,
			),
			array(
				'text' => lang('Multiple week view'),
				'value' => '{"view":"weekN"}',
				'selected' => $this->view == 'weekN',
			),
			array(
				'text' => lang('monthview'),
				'value' => '{"view":"month"}',
				'selected' => $this->view == 'month',
			),
			array(
				'text' => lang('yearview'),
				'value' => '{"view":"year", "menuaction":"calendar.calendar_uiviews.index"}',
				'selected' => $this->view == 'year',
			),
			array(
				'text' => lang('planner by category'),
				'value' => '{"view":"planner", "sortby":"category"}',
				'selected' => $this->view == 'planner' && $this->sortby != 'user',
			),
			array(
				'text' => lang('planner by user'),
				'value' => '{"view":"planner","sortby":"user"}',
				'selected' => $this->view == 'planner' && $this->sortby == 'user',
			),
			array(
				'text' => lang('yearly planner'),
				'value' => '{"view":"planner","sortby":"month"}',
				'selected' => $this->view == 'planner' && $this->sortby == 'month',
			),
			array(
				'text' => lang('listview'),
				'value' => '{"view":"listview"}',
				'selected' => $this->view == 'listview',
			),
		)as $data)
		{
			if($data['selected'])
			{
				$content['view'] = $data['value'];
			}
			$sel_options['view'][] = array(
				'label' => $data['text'],
				'value' => $data['value']
			);
		}
		$sel_options['filter'] = array(
			array('value' => 'default',     'label' => lang('Not rejected'), 'title' => lang('Show all status, but rejected')),
			array('value' => 'accepted',    'label' => lang('Accepted'), 'title' => lang('Show only accepted events')),
			array('value' => 'unknown',     'label' => lang('Invitations'), 'title' => lang('Show only invitations, not yet accepted or rejected')),
			array('value' => 'tentative',   'label' => lang('Tentative'), 'title' => lang('Show only tentative accepted events')),
			array('value' => 'delegated',   'label' => lang('Delegated'), 'title' => lang('Show only delegated events')),
			array('value' => 'rejected',    'label' => lang('Rejected'),'title' => lang('Show only rejected events')),
			array('value' => 'owner',       'label' => lang('Owner too'),'title' => lang('Show also events just owned by selected user')),
			array('value' => 'all',         'label' => lang('All incl. rejected'),'title' => lang('Show all status incl. rejected events')),
			array('value' => 'hideprivate', 'label' => lang('Hide private infos'),'title' => lang('Show all events, as if they were private')),
			array('value' => 'showonlypublic',  'label' => lang('Hide private events'),'title' => lang('Show only events flagged as public, (not checked as private)')),
			array('value' => 'no-enum-groups', 'label' => lang('only group-events'),'title' => lang('Do not include events of group members')),
			array('value' => 'not-unknown', 'label' => lang('No meeting requests'),'title' => lang('Show all status, but unknown')),
		);

		// Merge print
		if ($GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir'])
		{
			$sel_options['merge'] = calendar_merge::get_documents($GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir'], '', null,'calendar');
		}
		else
		{
			$readonlys['merge'] = true;
		}

		// Sidebox?
		$sidebox->exec('calendar.calendar_ui.sidebox_etemplate', $content, $sel_options, $readonlys);
	}

	/**
	 * Prepare an array of event information for sending to the client
	 *
	 * This involves changing timestamps into strings with timezone
	 *
	 * @param type $event
	 */
	protected function to_client(&$event)
	{
		if (!$this->bo->check_perms(EGW_ACL_EDIT,$event))
		{
			$event['class'] .= 'rowNoEdit ';
		}

		// Delete disabled for other applications
		if (!$this->bo->check_perms(EGW_ACL_DELETE,$event) || !is_numeric($event['id']))
		{
			$event['class'] .= 'rowNoDelete ';
		}

		// mark deleted events
		if ($event['deleted'])
		{
			$event['class'] .= 'rowDeleted ';
		}

		$event['recure'] = $this->bo->recure2string($event);

		if (empty($event['description'])) $event['description'] = ' ';	// no description screws the titles horz. alignment
		if (empty($event['location'])) $event['location'] = ' ';	// no location screws the owner horz. alignment

		// respect category permissions
		if(!empty($event['category']))
		{
			$event['category'] = $this->categories->check_list(EGW_ACL_READ, $event['category']);
		}

		if(!(int)$event['id'] && preg_match('/^([a-z_-]+)([0-9]+)$/i',$event['id'],$matches))
		{
			$app = $matches[1];
			$app_id = $matches[2];
			$icons = array();
			if(!($is_private = calendar_bo::integration_get_private($app,$app_id,$event)))
			{
				$icons = calendar_uiviews::integration_get_icons($app,$app_id,$event);
			}
		}
		else
		{
			$is_private = !$this->bo->check_perms(EGW_ACL_READ,$event);
		}
		if ($is_private)
		{
			$event['is_private'] = true;
			$event['class'] .= 'rowNoView ';
		}

		$event['app'] = 'calendar';
		$event['app_id'] = $event['id'];

		if ($event['recur_type'] != MCAL_RECUR_NONE)
		{
			$event['app_id'] .= ':'.$event['recur_date'];
		}
		$event['parts'] = implode(",\n",$this->bo->participants($event,true));

		// Change dates
		foreach(calendar_egw_record::$types['date-time'] as $field)
		{
			if(is_int($event[$field]))
			{
				$event[$field] = egw_time::to($event[$field],'Y-m-d\TH:i:s').'Z';
			}
		}
	}
	
	public function merge($timespan = array())
	{
		// Merge print
		if($_GET['merge'])
		{
			if(!$timespan)
			{
				$timespan = array(array(
					'start' => is_array($this->first) ? $this->bo->date2ts($this->first) : $this->first,
					'end' => is_array($this->last) ? $this->bo->date2ts($this->last) : $this->last
				));
			}
			$merge = new calendar_merge();
			return $merge->download($_GET['merge'], $timespan, '', $GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir']);
		}
		return false;
	}
}
