<?php
/**
 * EGroupware - Calendar's shared base-class of all UI classes
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;

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
	 * Instance of Api\Categories class
	 *
	 * @var Api\Categories
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
	 * @var int $status_filter session-state: selected filter, at the moment all or hideprivate
	 */
	var $status_filter;
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
	var $states_to_save = array('owner','status_filter','filter','cat_id','view','sortby','planner_view','weekend');

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

		$this->categories = new Api\Categories($this->user,'calendar');

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
		if (!Api\Hooks::exists('export_limit','calendar')) Api\Hooks::read(true);
	}

	/**
	 * Checks and terminates (or returns for home) with a message if $this->owner include a user/resource we have no read-access to
	 *
	 * If currentapp == 'home' we return the error instead of terminating with it !!!
	 *
	 * @return boolean/string false if there's no error or string with error-message
	 */
	function check_owners_access($users = null, &$no_access = array())
	{
		$no_access = $no_access_group = array();
		$owner_array = $users ? $users : explode(',',$this->owner);
		foreach($owner_array as $idx => $owner)
		{
			$owner = trim($owner);
			if (is_numeric($owner) && $GLOBALS['egw']->accounts->get_type($owner) == 'g')
			{
				foreach($GLOBALS['egw']->accounts->members($owner, true) as $member)
				{
					if (!$this->bo->check_perms(Acl::READ|calendar_bo::ACL_READ_FOR_PARTICIPANTS|calendar_bo::ACL_FREEBUSY,0,$member))
					{
						$no_access_group[$member] = $this->bo->participant_name($member);
					}
				}
			}
			elseif (!$this->bo->check_perms(Acl::READ|calendar_bo::ACL_READ_FOR_PARTICIPANTS|calendar_bo::ACL_FREEBUSY,0,$owner))
			{
				$no_access[$owner] = $this->bo->participant_name($owner);
				unset($owner_array[$idx]);
			}
		}
		if (count($no_access))
		{
			$message = lang('Access denied to the calendar of %1 !!!',implode(', ',$no_access));
			Framework::message($message,'error');
			$this->owner = implode(',',$owner_array);
			return $message;
		}
		if (count($no_access_group))
		{
			$this->bo->warnings['groupmembers'] = lang('Groupmember(s) %1 not included, because you have no access.',implode(', ',$no_access_group));
		}
		return false;
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
	 *	- status_filter: the used filter: all or hideprivate
	 *	- sortby: category or user of planner
	 *	- view: the actual view, where dialogs should return to or which they refresh
	 * @param array $set_states array to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function manage_states($set_states=NULL)
	{
		// retrieve saved states from prefs
		$states = is_array($this->bo->cal_prefs['saved_states']) ?
			$this->bo->cal_prefs['saved_states'] : unserialize($this->bo->cal_prefs['saved_states']);

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
			'status_filter'     => 'default',
			'owner'      => $this->user,
			'save_owner' => 0,
			'sortby'     => 'category',
			'planner_view'=> 'month',	// full month
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
					$set_owners = is_array($set_states['owner']) ? $set_states['owner'] : explode(',',$set_states['owner']);
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
						$owners = $states['owner'] ? $states['owner'] : $default;
						if(!is_array($owners))
						{
							$owners = explode(',',$owners);
						}
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
		if(is_array($this->owner))
		{
			$this->owner = implode(',',$this->owner);
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
			$this->view = $states['view'] = $func;
		}
		$this->view_menuaction = $this->view == 'listview' ? 'calendar.calendar_uilist.listview' : 'calendar.calendar_uiviews.index';

		if ($this->debug > 0 || $this->debug == 'manage_states') $this->bo->debug_message('uical::manage_states(%1), states now %3',True,$set_states,$states);
		// save the states in the session only when we are in calendar
		if ($GLOBALS['egw_info']['flags']['currentapp']=='calendar')
		{
			// save defined states into the user-prefs
			if(!empty($states) && is_array($states))
			{
				$saved_states = array_intersect_key($states,array_flip($this->states_to_save));
				if ($saved_states != $this->cal_prefs['saved_states'])
				{
					$GLOBALS['egw']->preferences->add('calendar','saved_states',$saved_states);
					$GLOBALS['egw']->preferences->save_repository(false,'user',true);
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
		$is_private = !$event['public'] && !$this->bo->check_perms(Acl::READ,$event);

		$icons = array();
		if (!$is_private)
		{
			if($event['priority'] == 3)
			{
				$icons[] = Api\Html::image('calendar','high',lang('high priority'));
			}
			if($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$icons[] = Api\Html::image('calendar','recur',lang('recurring event'));
			}
			// icons for single user, multiple users or group(s) and resources
			foreach(array_keys($event['participants']) as  $uid)
			{
				if(is_numeric($uid) || !isset($this->bo->resources[$uid[0]]['icon']))
				{
					if (isset($icons['single']) || $GLOBALS['egw']->accounts->get_type($uid) == 'g')
					{
						unset($icons['single']);
						$icons['multiple'] = Api\Html::image('calendar','users');
					}
					elseif (!isset($icons['multiple']))
					{
						$icons['single'] = Api\Html::image('calendar','single');
					}
				}
				elseif(!isset($icons[$uid[0]]) && isset($this->bo->resources[$uid[0]]) && isset($this->bo->resources[$uid[0]]['icon']))
				{
				 	$icons[$uid[0]] = Api\Html::image($this->bo->resources[$uid[0]]['app'],
				 		($this->bo->resources[$uid[0]]['icon'] ? $this->bo->resources[$uid[0]]['icon'] : 'navbar'),
				 		lang($this->bo->resources[$uid[0]]['app']),
				 		'width="16px" height="16px"');
				}
			}
		}
		if($event['non_blocking'])
		{
			$icons[] = Api\Html::image('calendar','nonblocking',lang('non blocking'));
		}
		if($event['public'] == 0)
		{
			$icons[] = Api\Html::image('calendar','private',lang('private'));
		}
		if(isset($event['alarm']) && count($event['alarm']) >= 1 && !$is_private)
		{
			$icons[] = Api\Html::image('calendar','alarm',lang('alarm'));
		}
		if($event['participants'][$this->user][0] == 'U')
		{
			$icons[] = Api\Html::image('calendar','needs-action',lang('Needs action'));
		}
		return $icons;
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
		return Api\Html::a_href($content,'',$vars,' data-date="' .$vars['date'].'|'.$vars['hour'].'|'.$vars['minute']
				. '" title="'.Api\Html::htmlspecialchars(lang('Add')).'"');
	}

	/**
	 * creates the content for the sidebox-menu, called as hook
	 */
	function sidebox_menu()
	{
		// Magic etemplate2 favorites menu (from framework)
		display_sidebox('calendar', lang('Favorites'), Framework\Favorites::list_favorites('calendar'));

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
			$file['Placeholders'] = Egw::link('/index.php','menuaction=calendar.calendar_merge.show_replacements');
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
				'Site configuration'=>Egw::link('/index.php','menuaction=admin.admin_config.index&appname=calendar&ajax=true'),
				'Custom Fields'=>Egw::link('/index.php','menuaction=admin.admin_customfields.index&appname=calendar&ajax=true'),
				'Global Categories' =>Egw::link('/index.php','menuaction=admin.admin_categories.index&appname=calendar&ajax=true'),
			);
			$GLOBALS['egw']->framework->sidebox($appname,lang('Admin'),$file,'admin');
		}
		display_sidebox('calendar', lang('Utilities'), array('Category report' => "javascript:egw_openWindowCentered2('".
					Egw::link('/index.php',array('menuaction'=>'calendar.calendar_category_report.index','ajax'=>true),false).
					"','_blank',870,500,'yes')" ));
	}

	/**
	 * Makes the sidebox content with etemplate, after hook is processed
	 */
	function sidebox_etemplate($content = array())
	{
		Etemplate::reset_request();
		$sidebox = new Etemplate('calendar.sidebox');

		$cont = $this->cal_prefs['saved_states'];
		if (!is_array($cont)) $cont = array();
		$cont['view'] = $this->view ? $this->view : 'week';
		$cont['date'] = $this->date ? $this->date : new Api\DateTime();
		$cont['owner'] = $this->owner ? (is_array($this->owner) ? $this->owner : explode(',',$this->owner) ) : $cont['owner'];

		$cont['year'] = (int)Api\DateTime::to($cont['date'],'Y');

		$readonlys = array();
		$sel_options['status_filter'] = array(
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
		$sel_options['status_filter'][] = array('value' => 'deleted', 'label' => lang('Deleted'), 'title' => lang('Show events that have been deleted'));

		// Merge print
		try {
			if (class_exists('EGroupware\\collabora\\Bo') &&
					$GLOBALS['egw_info']['user']['apps']['collabora'] &&
					$discovery = \EGroupware\collabora\Bo::discover()
			)
			{
				$cont['collabora_enabled'] = true;
			}
		}
		catch (\Exception $e)
		{
			// ignore failed discovery
			unset($e);
		}
		if($GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir'])
		{
			$sel_options['merge'] = calendar_merge::get_documents($GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir'], '', null, 'calendar');

		}
		else
		{
			$readonlys['merge'] = true;
		}

		// Add integration UI into sidemenu
		$integration_data = Api\Hooks::process(array('location' => 'calendar_search_union'));
		foreach($integration_data as $app => $app_hooks)
		{
			foreach($app_hooks as $data)
			{
				// App might have multiple hooks, let it specify something else
				$app = $data['selects']['app'] ?: $app;

				if (is_array($data) && array_key_exists('sidebox_template', $data))
				{
					$cont['integration'][] = ['template' => $data['sidebox_template'], 'app' => $app];
				}
			}
		}

		// Sidebox?
		$sidebox->exec('calendar.calendar_ui.sidebox_etemplate', $cont, $sel_options, $readonlys);
	}

	/**
	 * Get the data for the given event IDs in a format suitable for the client.
	 *
	 * Used to get new data when Push tells us.  Push doesn't have the full event data,
	 * just the minimum, so the client needs to ask for it.
	 *
	 * @param string[] $event_ids
	 */
	public function ajax_get($event_ids)
	{
		foreach($event_ids as $id)
		{
			$this->update_client($id);
		}
	}

	/**
	 * Send updated event information to the client via ajax
	 *
	 * This allows to pass only changed information for a single (recurring) event
	 * and update the UI without a refreshing any more than needed.  If adding,
	 * a notification via Framework::refresh_opener() is still needed but
	 * edits, updates and deletes will be automatic.
	 * If the event is recurring, we send the next month's worth of recurrences
	 * for lack of a better way to determine how much to send.
	 *
	 * @param int $event_id
	 * @param Api\DateTime $recurrence_date
	 * @param array|bool|int|null $old_event
	 *
	 * @return boolean True if the event was updated, false if it could not be
	 *    updated or was removed.
	 */
	public function update_client($event_id, Api\DateTime $recurrence_date = null, $old_event = array())
	{
		if(!$event_id)
		{
			return false;
		}
		if(is_string($event_id) && strpos($event_id, ':') !== FALSE)
		{
			list($event_id, $date) = explode(':', $event_id);
			$recurrence_date = new Api\DateTime($date);
		}

		// Directly update stored data.
		// Make sure we have the whole event
		$event = $this->bo->read($event_id, $recurrence_date, false, 'ts', $this->cal_prefs['saved_states']['owner']);
		$response = Api\Json\Response::get();


		// Check filters to see if they still match, may have to remove
		// the event because it should no longer be displayed
		$filter_match = true;
		if($event && ($this->cal_prefs['saved_states']['status_filter'] != 'all' ||
			$this->cal_prefs['saved_states']['cat_id']))
		{
			$filter_check = array(
				'start' => $event['start'],
				'users' => $this->cal_prefs['saved_states']['owner'],
				'cat_id' => $this->cal_prefs['saved_states']['cat_id'],
				'filter' => $this->cal_prefs['saved_states']['status_filter'],
				'num_rows' => 1
			);
			$filter_match = (bool)$this->bo->search($filter_check, $this->bo->so->cal_table.".cal_id = {$event['id']}");
		}

		if(!$event || !$filter_match)
		{
			// Sending null will trigger a removal
			$uid = 'calendar::' . $event_id;
			if ($recurrence_date)
			{
				$uid .= ':' . $recurrence_date->getTimestamp();
			}
			$response->generic('data', array('uid' => $uid, 'data' => null));
			return false;
		}

		if(!$event['recur_type'] || $recurrence_date)
		{
			$this->to_client($event);
			$response->generic('data', array('uid' => 'calendar::' . $event['row_id'], 'data' => $event));
		}
		// If it is (or was) recurring, try to send the next month or so
		if($event['recur_type'] || (!$event['recur_type'] && $old_event['recur_type']))
		{
			$this_month = new Api\DateTime('next month');
			$data = [];
			if($old_event && ($old_event['start'] != $event['start'] || $old_event['recur_enddate'] != $event['recur_enddate']))
			{
				// Set up to clear old events in case recurrence start/end date changed
				$old_rrule = calendar_rrule::event2rrule($old_event, true);

				$old_rrule->rewind();
				do
				{
					$occurrence = $old_rrule->current();
					$data['calendar::' . $old_event['id'] . ':' . $occurrence->format('ts')] = null;
					$old_rrule->next();
				}
				while($old_rrule->valid() && $occurrence <= $this_month);
			}
			if($event['recur_type'])
			{
				$rrule = calendar_rrule::event2rrule($event, true);
				$rrule->rewind();
				do
				{
					$occurrence = $rrule->current();
					$converted = $this->bo->read($event['id'], $occurrence);
					$this->to_client($converted);
					$data['calendar::' . $converted['row_id']] = $converted;
					$rrule->next();
				}
				while($rrule->valid() && $occurrence <= $this_month);
			}

			// Now we have to go through and send each one individually, since client side data can't handle more than one
			foreach($data as $uid => $cal_data)
			{
				$response->apply('egw.dataStoreUID', [$uid, $cal_data]);
			}
			$response->apply('app.calendar.update_events', [array_keys($data)]);
		}
		return true;
	}

	/**
	 * Prepare an array of event information for sending to the client
	 *
	 * This involves changing timestamps into strings with timezone so javascript
	 * does not change them, and making sure we have everything the client needs
	 * for proper display.
	 *
	 * @param type $event
	 */
	public function to_client(&$event)
	{
		if(!$event || !is_array($event)) return false;

		static $sent_groups = array();

		if (!is_numeric($event['id']) || !$this->bo->check_perms(Acl::EDIT,$event))
		{
			$event['class'] .= 'rowNoEdit ';
		}

		if (is_numeric($event['id']) && !$this->bo->check_perms(Acl::DELETE, $event))
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
			$event['category'] = $this->categories->check_list(Acl::READ, $event['category']);
		}
		$event['non_blocking'] = (bool)$event['non_blocking'];

		$matches = null;
		if(!(int)$event['id'] && preg_match('/^([a-z_-]+)([0-9]+)$/i',$event['id'],$matches))
		{
			$app = $matches[1];
			$app_id = $matches[2];
			$icons = array();
			if(!($is_private = calendar_bo::integration_get_private($app,$app_id,$event)))
			{
				$icons = calendar_uiviews::integration_get_icons($app,$app_id,$event);
			}
			$event['app'] = $app;
			$event['app_id'] = $app_id;
			// check if integration-app allows/supports delete
			if (!calendar_bo::integration_deletable($app, $event))
			{
				$event['class'] .= 'rowNoDelete';
			}
		}
		else
		{
			$is_private = !$this->bo->check_perms(Acl::READ,$event);
		}
		if ($is_private)
		{
			$event['is_private'] = true;
			$event['class'] .= 'rowNoView ';
		}

		if(!$event['app'])
		{
			$event['app'] = 'calendar';
		}
		if(!$event['app_id'])
		{
			$event['app_id'] = $event['id'];
		}

		if ($event['recur_type'] != MCAL_RECUR_NONE)
		{
			$event['app_id'] .= ':'.Api\DateTime::to($event['recur_date'] ? $event['recur_date'] : $event['start'],'ts');
		}
		// set id for grid
		$event['row_id'] = $event['id'].($event['recur_type'] ? ':'.Api\DateTime::to($event['recur_date'] ? $event['recur_date'] : $event['start'],'ts') : '');

		// Set up participant section of tooltip
		$participants = $this->bo->participants($event,false);
		$event['parts'] = implode("\n",$participants);
		$event['participant_types'] = array();
		foreach($participants as $uid => $text)
		{
			$user_type = $user_id = null;
			calendar_so::split_user($uid, $user_type, $user_id);
			$type_name = lang($this->bo->resources[$user_type]['app']);
			$event['participant_types'][$type_name ? $type_name : ''][] = $text;
			if(is_int($uid) && $uid < 0 && !in_array($uid, $sent_groups))
			{
				// Make sure group membership info is on the client
				Api\Json\Response::get()->apply(
					'egw.set_account_cache', array(
					array($uid => $GLOBALS['egw']->accounts->members($uid) ),
					'account_id'
				));
			}
		}
		$event['date'] = $this->bo->date2string($event['start']);

		// Change dates
		foreach(calendar_egw_record::$types['date-time'] as $field)
		{
			if(is_int($event[$field]))
			{
				$event[$field] = Api\DateTime::to($event[$field], Api\DateTime::ET2);
			}
		}
	}
}
