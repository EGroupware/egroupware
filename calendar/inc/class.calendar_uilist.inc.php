<?php
/**
 * EGroupware - Calendar's Listview and Search
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Framework;
use EGroupware\Api\Link;

/**
 * Class to generate the calendar listview and the search
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * The state of the UI elements is managed in the uical class, which all UI classes extend.
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the bocal class !!!
 */
class calendar_uilist extends calendar_ui
{
	var $public_functions = array(
		'listview'  => True,
	);
	/**
	 * integer level or string function- or widget-name
	 *
	 * @var mixed
	 */
	var $debug=false;
	/**
	 * Filternames
	 *
	 * @var array
	 */
	var $date_filters = array(
		'after'  => 'After current date',
		'before' => 'Before current date',
		'today'  => 'Today',
		'week'   => 'Week',
		'month'  => 'Month',
		'all'	=> 'All events',
		'custom' => 'Selected range',
	);

	/**
	 * Constructor
	 *
	 * @param array $set_states =null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function __construct($set_states=null)
	{
		parent::__construct(true,$set_states);	// call the parent's constructor

		foreach($this->date_filters as $name => $label)
		{
			$this->date_filters[$name] = lang($label);
		}

		$this->check_owners_access();
	}

	/**
	 * Show the listview
	 */
	function listview($_content=null,$msg='',$home=false)
	{
		if ($_GET['msg']) $msg .= $_GET['msg'];
		if ($this->group_warning) $msg .= $this->group_warning;

		$etpl = new Etemplate('calendar.list');

		// Handle merge from sidebox
		if($_GET['merge'])
		{
			$_content['nm']['action'] = 'document_'.$_GET['merge'];
			$_content['nm']['select_all'] = true;
		}

		if (is_array($_content))
		{
			// handle a single button like actions
			foreach(array('delete','timesheet','document') as $button)
			{
				if (!empty($_content['nm']['rows'][$button]))
				{
					$id = key($_content['nm']['rows'][$button]);
					$_content['nm']['action'] = $button;
					$_content['nm']['selected'] = array($id);
				}
			}
			// Handle actions
			if ($_content['nm']['action'])
			{
				// Allow merge using the date range filter
				if(strpos($_content['nm']['action'],'document') !== false &&
					!count($_content['nm']['selected']) && !$_content['nm']['select_all']) {
					$_content['nm']['selected'][] = $this->get_merge_range($_content['nm']);
				}
				if (!count($_content['nm']['selected']) && !$_content['nm']['select_all'])
				{
					$msg = lang('You need to select some events first');
				}
				else
				{
					$success = $failed = $action_msg = null;
					if ($this->action($_content['nm']['action'],$_content['nm']['selected'],$_content['nm']['select_all'],
						$success,$failed,$action_msg,'calendar_list',$msg, $_content['nm']['checkboxes']['no_notifications']))
					{
						$msg .= lang('%1 event(s) %2',$success,$action_msg);
					}
					elseif(is_null($msg))
					{
						$msg .= lang('%1 event(s) %2, %3 failed because of insufficient rights !!!',$success,$action_msg,$failed);
					}
				}
			}
		}
		$content = array(
			'nm'  => Api\Cache::getSession('calendar', 'calendar_list'),
		);
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'        =>	'calendar.calendar_uilist.get_rows',
	 			'filter_no_lang'  => True,	// I  set no_lang for filter (=dont translate the options)
				'no_filter2'      => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'          => True,	// I  disable the cat-selectbox
				'filter'          => 'month',
				'order'           => 'cal_start',// IO name of the column to sort after (optional for the sortheaders)
				'sort'            => 'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'default_cols'    => '!week,weekday,cal_title,cal_description,recure,cal_location,cal_owner,cat_id,pm_id',
				'filter_onchange' => "app.calendar.filter_change",
				'row_id'          => 'row_id',	// set in get rows "$event[id]:$event[recur_date]"
				'row_modified'    => 'modified',
				'placeholder_actions' => array('add')
			);
		}
		$content['nm']['favorites'] = false;
		$content['nm']['actions'] = $this->get_actions();

		// Skip first load if view is not listview
		if($this->view && $this->view !== 'listview')
		{
			$content['nm']['num_rows'] = 0;
		}

		if (isset($_GET['filter']) && in_array($_GET['filter'],array_keys($this->date_filters)))
		{
			$content['nm']['filter'] = $_GET['filter'];
		}
		if ($_GET['search'])
		{
			$content['nm']['search'] = $_GET['search'];
		}
		if($this->owner)
		{
			$content['nm']['col_filter']['participant'] = is_array($this->owner) ? $this->owner : explode(',',$this->owner);
		}
		// search via jdots ajax_exec uses $_REQUEST['json_data'] instead of regular GET parameters
		if (isset($_REQUEST['json_data']) && ($json_data = json_decode($_REQUEST['json_data'], true)) &&
			!empty($json_data['request']['parameters'][0]))
		{
			$params = null;
			parse_str(substr($json_data['request']['parameters'][0], 10), $params);	// cut off "/index.php?"
			if (isset($params['keywords']))	// new search => set filters so every match is shown
			{
				$this->adjust_for_search($params['keywords'], $content['nm']);
			}
			unset($params['keywords']);
		}
		if (isset($_REQUEST['keywords']))	// new search => set filters so every match is shown
		{
			$this->adjust_for_search($_REQUEST['keywords'],$content['nm']);
			unset($_REQUEST['keywords']);
		}
		$sel_options['filter'] = &$this->date_filters;

		// Send categories for row styling - calendar uses no_cat, so they don't go automatically
		$sel_options['category'] = array('' => lang('all')) + Etemplate\Widget\Select::typeOptions('select-cat', ',,calendar');
		// Prevent double encoding - widget does this on its own, but we're just grabbing the options
		foreach($sel_options['category'] as &$label)
		{
			if(!is_array($label))
			{
				$label = html_entity_decode($label, ENT_NOQUOTES,'utf-8');
			}
			elseif($label['label'])
			{
				$label['label'] = html_entity_decode($label['label'], ENT_NOQUOTES,'utf-8');
			}
		}

		// add scrollbar to long describtion, if user choose so in his prefs
		if ($this->prefs['limit_des_lines'] > 0 || (string)$this->prefs['limit_des_lines'] == '')
		{
			$content['css'] .= '<style type="text/css">@media screen { .listDescription {  max-height: '.
				(($this->prefs['limit_des_lines'] ? $this->prefs['limit_des_lines'] : 5) * 1.35).	   // dono why em is not real lines
				'em; overflow: auto; }}</style>';
		}

		if($msg)
		{
			Framework::message($msg);
		}
		$html = $etpl->exec('calendar.calendar_uilist.listview',$content,$sel_options,array(),array(),$home ? -1 : 0);

		// Not sure why this has to be echoed instead of appended, but that's what works.
		//echo calendar_uiviews::edit_series();

		return $html;
	}

	/**
	 * set filter for search, so that everything is shown
	 */
	function adjust_for_search($keywords,&$params)
	{
		$params['search'] = $keywords;
		$params['start']  = 0;
		$params['order'] = 'cal_start';
		if ($keywords)
		{
			$params['sort'] = 'DESC';
			unset($params['col_filter']['participant']);
		}
		else
		{
			$params['sort'] = 'ASC';
		}
	}

	/**
	 * query calendar for nextmatch in the listview
	 *
	 * @internal
	 * @param array &$params parameters
	 * @param array &$rows returned rows/events
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 */
	function get_rows(&$params,&$rows,&$readonlys)
	{
		unset($readonlys);	// not used;
		//echo "uilist::get_rows() params="; _debug_array($params);
		$this->filter = $params['filter'];
		if ($params['filter'] == 'custom')
		{
			if (!$params['startdate'] && !$params['enddate'])
			{
				$this->filter = 'all';
			}
			elseif (!$params['startdate'])
			{
				$this->filter = 'before';
				$this->manage_states(array('date' => $this->bo->date2string($params['enddate'])));
			}
			elseif (!$params['enddate'])
			{
				$this->filter = 'after';
				$this->manage_states(array('date' => $this->bo->date2string($params['startdate'])));
			}
		}
		$old_params = Api\Cache::getSession('calendar', 'calendar_list');
		if (is_array($old_params))
		{
			if ($old_params['filter'] && $old_params['filter'] != $params['filter'])	// filter changed => order accordingly
			{
				$params['order'] = 'cal_start';
				$params['sort'] = $params['filter'] == 'before' ? 'DESC' : 'ASC';
			}
			if ($old_params['search'] != $params['search'])
			{
				$this->adjust_for_search($params['search'],$params);
				$this->filter = $params['filter'];
			}
		}

		if (!$params['csv_export'])
		{
			Api\Cache::setSession('calendar', 'calendar_list',
				array_diff_key ($params, array_flip(array('rows', 'actions', 'action_links', 'placeholder_actions'))));
		}

		// release session to allow parallel requests to run
		$GLOBALS['egw']->session->commit_session();

		// do we need to query custom fields and which
		// Check stored preference if selectcols isn't available (ie: first call)
		$select_cols = $params['selectcols'] ? $params['selectcols'] : $GLOBALS['egw_info']['user']['preferences']['calendar']['nextmatch-calendar.list.rows'];
		if(!is_array($params['selectcols']))
		{
			$select_cols = explode(',',$select_cols);
		}
		if (in_array('cfs',$select_cols))
		{
			$cfs = array();
			foreach($select_cols as $col)
			{
				if ($col[0] == '#') $cfs[] = substr($col,1);
			}
		}
		// check if we have videoconferences enabled and need to query the room url
		// temp. disabled check, as preconfigured server is NOT stored
		//$status_config = Api\Config::read('status');
		//if (!empty($status_config['videoconference']['jitsi']['jitsi_domain']))
		{
			$cfs[] = '#videoconference';
		}
		$search_params = array(
			'cat_id'  => $params['cat_id'] ? $params['cat_id'] : 0,
			'filter'  => $this->filter,
			'query'   => $params['search'],
			'offset'  => (int) $params['start'],
			'num_rows'=> $params['num_rows'],
			'order'   => $params['order'] ? $params['order'].' '.$params['sort'] : 'cal_start ASC',
			'cfs'	 => $params['csv_export'] ? array() : $cfs,
		);
		// Non-blocking events above blocking
		$search_params['order'] .= ', cal_non_blocking DESC';

		switch($this->filter)
		{
			case 'all':
				break;
			case 'before':
				$search_params['end'] = $params['date'] ? Api\DateTime::to($params['date'],'ts') : $this->date;
				$label = lang('Before %1',$this->bo->long_date($search_params['end']));
				break;
			case 'custom':
				$this->first = $search_params['start'] = Api\DateTime::to($params['startdate'],'ts');
				$this->last  = $search_params['end'] = strtotime('+1 day', $this->bo->date2ts($params['enddate']))-1;
				$label = $this->bo->long_date($this->first,$this->last);
				break;
			case 'today':
				$today = new Api\DateTime();
				$today->setTime(0, 0, 0);
				$this->first = $search_params['start'] = $today->format('ts');
				$today->setTime(23,59,59);
				$this->last  = $search_params['end'] = $today->format('ts');
				break;
			case 'week':
				$start = new Api\DateTime($params['date'] ? $params['date'] : $this->date);
				$start->setWeekstart();
				$this->first = $start->format('ts');
				$this->last = $this->bo->date2array($this->first);
				$this->last['day'] += ($params['weekend'] == 'true' ? 7 : 5) - 1;
				$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
				unset($this->last['raw']);
				$this->last = $this->bo->date2ts($this->last);
				$this->date_filters['week'] = $label = lang('Week').' '.adodb_date('W',$this->first).': '.$this->bo->long_date($this->first,$this->last);
				$search_params['start'] = $this->first;
				$search_params['end'] = $this->last;
				$params['startdate'] = Api\DateTime::to($this->first, Api\DateTime::ET2);
				$params['enddate'] = Api\DateTime::to($this->last, Api\DateTime::ET2);
				break;

			case 'month':
			default:
				$this->first = $this->bo->date2array($params['date'] ? $params['date'] : $this->date);
				$this->first['day'] = 1;
				unset($this->first['raw']);
				$this->last = $this->first;
				$this->last['month'] += 1;
				$this->date_filters['month'] = $label = lang(adodb_date('F',$this->bo->date2ts($params['date']))).' '.$this->first['year'];
				$this->first = $this->bo->date2ts($this->first);
				$this->last = $this->bo->date2ts($this->last);
				$this->last--;
				$search_params['start'] = $this->first;
				$search_params['end'] = $this->last;
				$params['startdate'] = Api\DateTime::to($this->first, Api\DateTime::ET2);
				$params['enddate'] = Api\DateTime::to($this->last, Api\DateTime::ET2);
				break;

			case 'after':
				$this->date = $params['startdate'] ? Api\DateTime::to($params['startdate'],'ts') : $this->date;
				$label = lang('After %1',$this->bo->long_date($this->date));
				$search_params['start'] = $this->date;
				break;
		}
		if($params['status_filter'])
		{
			$search_params['filter'] = $params['status_filter'];
		}
		if ($params['col_filter']['participant'])
		{
			$search_params['users'] = is_array($params['col_filter']['participant']) ? $params['col_filter']['participant'] : array( $params['col_filter']['participant']);
		}
		elseif (!$params['col_filter'] || !$params['col_filter']['participant'])
		{
			$search_params['users'] = $params['owner'] ? $params['owner'] : explode(',',$this->owner);
		}
		// Allow private to stay for all viewed owners, even if in separate calendars
		$search_params['private_allowed'] = (array)$params['selected_owners'] + (array)$search_params['users'];

		if ($params['col_filter'])
		{
			$col_filter = array();
			foreach($params['col_filter'] as $name => $val)
			{
				if(!in_array($name, array('participant', 'row_id')) && (string)$val !== '')
				{
					$col_filter[$name] = $val;
				}
				elseif($name == 'row_id' && (string)$val !== '')
				{
					$col_filter['cal_id'] = $val;
				}
				if($name[0] == '#')
				{
					$search_params['cfs'][] = $name;
				}
			}
			// Videocalls
			if(array_key_exists('include_videocalls',$params['col_filter']))
			{
				$status_config = Api\Config::read("status");
				if (!$col_filter['include_videocalls'])
				{
					$col_filter[] = 'cal_category != ' . $this->bo->so->db->quote( $status_config['status_cat_videocall']);
				}
				unset($col_filter['include_videocalls']);
			}
		}
		$rows = $js_integration_data = array();

		// App header is mostly taken care of on the client side, but here we update
		// it to match changing list filters
		if($params['view'] && $params['view'] == 'listview' && Api\Json\Response::isJSONResponse())
		{
			Api\Json\Response::get()->call('app.calendar.set_app_header',
				(count($search_params['users']) == 1 ? $this->bo->participant_name($search_params['users'][0]).': ' : '') .
				$label);
		}
		foreach((array) $this->bo->search($search_params, !empty($col_filter) ? $col_filter : null) as $event)
		{

			if ($params['csv_export'] && $params['csv_export'] !== "refresh")
			{
				$event['participants'] = implode(",\n",$this->bo->participants($event,true));
			}
			else
			{
				$this->to_client($event);
			}

			$matches = null;
			if(!(int)$event['id'] && preg_match('/^([a-z_-]+)([0-9]+)$/i',$event['id'],$matches))
			{
				$app = $matches[1];
				$app_id = $matches[2];
				$icons = array();
				if (($is_private = calendar_bo::integration_get_private($app,$app_id,$event)))
				{
					$icons[] = Api\Html::image('calendar','private');
				}
				else
				{
					$icons = calendar_uiviews::integration_get_icons($app,$app_id,$event);
				}
				$event['icons'] = $icons;
			}
			else
			{
				$is_private = !$this->bo->check_perms(Acl::READ,$event);
			}
			if ($is_private)
			{
				$event['class'] .= 'rowNoView ';
			}

			$event['app'] = 'calendar';
			$event['app_id'] = $event['id'];

			// Edit link
			if($app && $app_id)
			{
				$popup = calendar_uiviews::integration_get_popup($app,$app_id);

				// Need to strip off 'onclick'
				$event['edit_link'] = preg_replace('/ ?onclick="(.+)"/i', '$1', $popup);

				$event['app'] = $app;
				$event['app_id'] = $app_id;

				// populate js_integration_data, if not already set
				if (!isset($js_integration_data[$app]))
				{
					$js_integration_data[$app] = calendar_bo::integration_get_data($app,'edit_link');
				}
			}
			elseif ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$event['app_id'] .= ':'.Api\DateTime::to($event['recur_date'] ? $event['recur_date'] : $event['start'],'ts');
			}

			// Format start and end with timezone
			foreach(array('start','end') as $time)
			{
				$event[$time] = Api\DateTime::to($event[$time],'Y-m-d\TH:i:s\Z');
			}

			$rows[] = $event;
			unset($app);
			unset($app_id);
		}
		// set js_calendar_integration object, to use it in app.js cal_open() function
		$params['js_integration_data'] = json_encode($js_integration_data);

		$wv=0;
		$dv=0;

		// Add in some select options
		$users = is_array($search_params['users']) ? $search_params['users'] : explode(',',$search_params['users']);

		$this->bo->warnings['groupmembers'] = '';
		if(($message = $this->check_owners_access($users)))
		{
			Api\Json\Response::get()->error($message);
		}
		else if($this->bo->warnings['groupmembers'])
		{
			Api\Json\Response::get()->error($this->bo->warnings['groupmembers']);
		}
		$rows['sel_options']['filter'] = $this->date_filters;
		if($label)
		{
			$rows['sel_options']['filter'][$params['filter']] = $label;
		}
		foreach($users as $owner)
		{
			if(!is_int($owner) && $this->bo->resources[$owner[0]])
			{
				$app = $this->bo->resources[$owner[0]]['app'];
				$_owner = substr($owner,1);
				// Try link first
				$title = Link::title($app, $_owner );
				if($title)
				{
					$rows['sel_options']['owner'][$owner] = $title;
				}
			}
		}
		$params['options-selectcols']['week'] = lang('Week');
		$params['options-selectcols']['weekday'] = lang('Weekday');
		if ((substr($this->cal_prefs['nextmatch-calendar.list.rows'],0,4) == 'week' && strlen($this->cal_prefs['nextmatch-calendar.list.rows'])==4) || substr($this->cal_prefs['nextmatch-calendar.list.rows'],0,5) == 'week,')
		{
			$rows['format'] = '32';	// prefix date with week-number
			$wv=1;
		}
		if (!(strpos($this->cal_prefs['nextmatch-calendar.list.rows'],'weekday')===FALSE))
		{
			$rows['format'] = '16';
			$dv=1;
		}
		if ($wv && $dv)
		{
			$rows['format'] = '64';
		}
		if ($this->cat_id) $rows['no_cat_id'] = true;
		if (!$GLOBALS['egw_info']['user']['apps']['projectmanager'])
		{
			$params['options-selectcols']['pm_id'] = false;
		}
		//_debug_array($rows);
		return $this->bo->total;
	}

	/**
	 * Apply an action to multiple events, but called via AJAX instead of submit
	 *
	 * @param string $action
	 * @param string[] $selected
	 * @param bool $all_selected All events are selected, not just what's in $selected
	 * @param bool $skip_notification
	 */
	public function ajax_action($action, $selected, $all_selected, $skip_notification = false)
	{
		$success = 0;
		$failed = 0;
		$action_msg = '';
		$session_name = 'calendar_list';

		if($this->action($action, $selected, $all_selected, $success, $failed, $action_msg, $session_name, $msg, $skip_notification))
		{
			$msg = lang('%1 event(s) %2',$success,$action_msg);
		}
		elseif(is_null($msg))
		{
			$msg .= lang('%1 event(s) %2, %3 failed because of insufficient rights !!!',$success,$action_msg,$failed);
		}
		Api\Json\Response::get()->message($msg);
	}

	/**
	 * apply an action to multiple events
	 *
	 * @param string/int $action 'delete', 'ical', 'print', 'email'
	 * @param array $checked event id's to use if !$use_all
	 * @param boolean $use_all if true use all events of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 events 'deleted'
	 * @param string/array $session_name 'calendar_list'
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg,$skip_notification=false)
	{
		//error_log(__METHOD__."('$action', ".array2string($checked).', all='.(int)$use_all.", ...)");
		$success = $failed = 0;
		$msg = null;

		// Split out combined values
		if(strpos($action, 'status') !== false)
		{
			list($action, $status) = explode('-', $action);
		}
		elseif (strpos($action, '_') !== false)
		{
			list($action, $settings) = explode('_', $action,2);
		}

		if ($use_all)
		{
			// get the whole selection
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession('calendar', $session_name);
			@set_time_limit(0);				// switch off the execution time limit, as for big selections it's too small
			$query['num_rows'] = -1;		// all
			$readonlys = null;
			$this->get_rows($query,$checked,$readonlys,!in_array($action,array('ical','document')));	   // true = only return the id's
			// Get rid of any extras (rows that aren't events)
			if(in_array($action,array('ical','document')))
			{
				foreach($checked as $key => $event)
				{
					if(!is_numeric($key))
					{
						unset($checked[$key]);
					}
				}
			}
		}
		// for calendar integration we have to fetch all rows and unset the not selected ones, as we can not filter by id
		elseif($action == 'document')
		{
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession('calendar', $session_name);
			@set_time_limit(0);				// switch off the execution time limit, as for big selections it's too small
			$events = null;
			$this->get_rows($query,$events,$readonlys);
			foreach($events as $key => $event)
			{
				$recur_date = Api\DateTime::to($event['recur_date'],'ts');
				if (!in_array($event['id'],$checked) && !in_array($event['id'].':'.$recur_date, $checked)) unset($events[$key]);
			}
			$checked = array_values($events); // Clear keys
		}

		// Actions where one action is done to the group
		switch($action)
		{
			case 'ical':
				// compile list of unique cal_id's, as iCal should contain whole series, not recurrences
				// calendar_ical->exportVCal needs to read events again, to get them in server-time
				$ids = array();
				foreach($checked as $id)
				{
					if (is_array($id)) $id = $id['id'];
					// get rid of recurrences, doublicate series and calendar-integration events
					if (($id = (int)$id))
					{
						$ids[$id] = $id;
					}
				}
				$boical = new calendar_ical();
				$ical =& $boical->exportVCal($ids, '2.0', 'PUBLISH');
				Api\Header\Content::type('event.ics', 'text/calendar', bytes($ical));
				echo $ical;
				exit();

			case 'document':
				if (!$settings) $settings = $GLOBALS['egw_info']['user']['preferences']['calendar']['default_document'];
				$document_merge = new calendar_merge();
				$msg = $document_merge->download($settings, $checked, '', $GLOBALS['egw_info']['user']['preferences']['calendar']['document_dir']);
				$failed = count($checked);
				error_log($msg);
				return false;
		}

		// Actions where the action is applied to each entry
		if(strpos($action, 'timesheet') !== false)
		{
			$timesheet_bo = new timesheet_bo();
		}
		foreach($checked as &$id)
		{
			$recur_date = $app = $app_id = null;
			if(is_array($id) && $id['id'])
			{
				$id = $id['id'];
			}
			$matches = null;
			if(!(int)$id && preg_match('/^([a-z_-]+)([0-9]+)$/i',$id,$matches))
			{
				$app = $matches[1];
				$app_id = $matches[2];
				$id = null;
			}
			else
			{
				list($id,$recur_date) = explode(':',$id);
			}
			switch($action)
			{
				case 'delete':
					$action_msg = lang('deleted');
					if($settings == 'series')
					{
						// Delete the whole thing
						$recur_date = 0;
					}
					if ($id && $this->bo->delete($id, $recur_date,false,$skip_notification))
					{
						$success++;
						if(!$recur_date && $settings == 'series')
						{
							// If there are multiple events in a series selected, the next one could purge
							foreach($checked as $key => $c_id)
							{
								list($c_id,$recur_date) = explode(':',$c_id);
								if($c_id == $id)
								{
									unset($checked[$key]);
								}
							}
						}

						if(Api\Json\Request::isJSONRequest())
						{
							Api\Json\Response::get()->call('egw.refresh','','calendar',$recur_date ? "$id:$recur_date" : $id,'delete');
						}
					}
					else
					{
						$failed++;
					}
					break;
				case 'undelete':
					$action_msg = lang('recovered');
					if($settings == 'series')
					{
						// unDelete the whole thing
						$recur_date = 0;
					}
					if ($id && ($event = $this->bo->read($id, $recur_date)) && $this->bo->check_perms(Acl::EDIT,$id) &&
						is_array($event) && $event['deleted'])
					{
						$event['deleted'] = null;
						if($this->bo->save($event))
						{
							$success++;

							if(Api\Json\Request::isJSONRequest())
							{
								Api\Json\Response::get()->call('egw.dataStoreUID','calendar::'.$id,$this->to_client($this->bo->read($id,$recur_date)));
								Api\Json\Response::get()->call('egw.refresh','','calendar',$id,'edit');
							}
							break;
						}
					}
					$failed++;
					break;
				case 'status':
					$action_msg = lang('Status changed');
					if($id && ($event = $this->bo->read($id, $recur_date)))
					{
						$old_status = $event['participants'][$GLOBALS['egw_info']['user']['account_id']];
						$quantity = $role = null;
						calendar_so::split_status($old_status, $quantity, $role);
						if ($old_status != $status)
						{
							//echo "<p>$uid: status changed '$data[old_status]' --> '$status<'/p>\n";
							$new_status = calendar_so::combine_status($status, $quantity, $role);
							if ($this->bo->set_status($event,$GLOBALS['egw_info']['user']['account_id'],$new_status,$recur_date,
								false,true,$skip_notification))
							{
								if(Api\Json\Request::isJSONRequest())
								{
									Api\Json\Response::get()->call('egw.dataStoreUID','calendar::'.$id,$this->to_client($this->bo->read($id,$recur_date)));
								}
								$success++;
								//$msg = lang('Status changed');
							}
							else
							{
								$failed++;
							}
						}
					}
					else
					{
						$failed++;
					}
					break;
				case 'timesheet-add':
					if($id && !$app)
					{
						$event = $this->bo->read($id, $recur_date);
					}
					elseif ($app)
					{
						$query = Api\Cache::getSession('calendar', 'calendar_list');
						$query['query'] = $app_id;
						$query['search'] = $app_id;
						$result = $this->bo->search($query);
						$event = $result[$app.$app_id];
					}
					if(!$event)
					{
						$failed++;
						continue 2;	// +1 for switch
					}
					$timesheet = array(
						'ts_title'		=>	$event['title'],
						'ts_description' =>	$event['description'],
						'ts_start'		=>	$event['start'],
						'ts_duration'	=>	($event['end'] - $event['start']) / 60,
						'ts_quantity'	=>	($event['end'] - $event['start']) / 3600,
						'ts_owner'		=>	$GLOBALS['egw_info']['user']['account_id'],
						'cat_id'		=>	null,
						'pl_id'			=>	null
					);

					// Add global categories
					$categories = explode(',',$event['category']);
					$global_categories = array();
					foreach($categories as $cat_id)
					{
						if($GLOBALS['egw']->categories->is_global($cat_id))
						{
							$global_categories[] = $cat_id;
						}
					}
					if(count($global_categories))
					{
						$timesheet['cat_id'] = implode(',', $global_categories);
					}
					$timesheet_bo->data = array();
					$err = $timesheet_bo->save($timesheet);

					//get the project manager linked to the calnedar entry
					$calApp_links = Link::get_links('calendar', $event['id']);
					foreach ($calApp_links as $l_app)
					{
						if ($l_app['app'] == 'projectmanager')
						{
							$prj_links = $l_app;
							//Links timesheet to projectmanager
							Link::link('timesheet', $timesheet_bo->data['ts_id'], 'projectmanager', $prj_links['id']);

						}
					}

					if(!$err)
					{
						$success++;

						// Can't link to just one of a recurring series of events
						if(!$recur_date || $app) {
							// Create link
							$link_id = $app ? $app_id : $id;
							Link::link($app ? $app : 'calendar', $link_id, 'timesheet', $timesheet_bo->data['ts_id']);
						}
					}
					else
					{
						$failed++;
					}
					$msg = lang('Timesheet entries created for ');
					break;
			}
		}
		//error_log(__METHOD__."('$action', ".array2string($checked).', '.array2string($use_all).") sucess=$success, failed=$failed, action_msg='$action_msg', msg=".array2string($msg).' returning '.array2string(!$failed));
		return !$failed;
	}

	/**
	 * Get date ranges to select for merging instead of individual events
	 *
	 * @param $nm nextmatch array from submit
	 *
	 * @return array of ranges
	 */
	protected function get_merge_range($nm)
	{
		$checked = array();
		if($nm['filter'] == 'fixed')
		{
			$checked['start'] = $nm['startdate'];
			$last = $this->bo->date2array($nm['enddate']);
			$last['hour'] = '23'; $last['minute'] = $last['sec'] = '59';
			$checked['end'] = $this->bo->date2ts($last);
		}
		else
		{
			switch($nm['filter'])
			{
				case 'after':
					$checked['start'] = $nm['startdate'] ? $nm['startdate'] : strtotime('today');
					break;
				case 'before':
					$checked['end'] = $nm['enddate'] ? $nm['enddate'] : strtotime('tomorrow');
					break;
				case 'custom':
					$checked['start'] = $nm['startdate'];
					$checked['end'] = $nm['enddate'];
					break;
				default:
					$date = date_create_from_format('Ymd',$this->date);
					$checked['start']= $date->format('U');
			}
		}
		return $checked;
	}

	/**
	 * Get actions / context menu items
	 *
	 * @return array see nextmatch_widget::get_actions()
	 */
	public function get_actions()
	{
		$actions = array(
			'add' => array(
				'caption' => 'Add',
				'egw_open' => 'add-calendar',
				'hideOnMobile' => true
			),
			'open' => array(
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=calendar.calendar_uiforms.edit&cal_id=$id',
				'popup' => Link::get_registry('calendar', 'view_popup'),
				'group' => $group=1,
				'onExecute' => 'javaScript:app.calendar.cal_open',
				'disableClass' => 'rowNoView',
			),
			'copy' => array(
				'caption' => 'Copy',
				'group' => $group,
				'disableClass' => 'rowNoView',
				'url' => 'menuaction=calendar.calendar_uiforms.edit&cal_id=$id&action=copy',
				'popup' => Link::get_registry('calendar', 'view_popup'),
				'allowOnMultiple' => false,
			),
			'print' => array(
				'caption' => 'Print',
				'group' => $group,
				'disableClass' => 'rowNoView',
				'url' => 'menuaction=calendar.calendar_uiforms.edit&cal_id=$id&print=1',
				'popup' => Link::get_registry('calendar', 'view_popup'),
				'allowOnMultiple' => false,
			),
			'select_all' => array(
				'caption' => 'Whole query',
				'hint' => 'Apply the action on the whole query, NOT only the shown events',
				'group' => ++$group,
			),
			'no_notifications' => array(
				'caption' => 'Do not notify',
				'checkbox' => true,
				'hint' => 'Do not notify of these changes',
				'group' => $group,
			),
		);
		$status = array_map('lang',$this->bo->verbose_status);
		unset($status['G']);
		$actions['status'] = array(
			'caption' => 'Change your status',
			'icon' => 'check',
			'prefix' => 'status-',
			'children' => $status,
			'group' => ++$group,
		);
		++$group;	// integration with other apps: infolog, calendar, filemanager

		if ($GLOBALS['egw_info']['user']['apps']['status'])
		{
			Api\Translation::add_app('status');
			$actions['videoconference'] = [
				'icon' => 'status/videoconference',
				'caption' => 'Video Conference',
				'group' => $group,
				'allowOnMultiple' => false,
				'disableClass' => 'rowNoView',
				'enabled' => 'javaScript:app.calendar.isVideoConference',
				'children' => [
					'join' => [
						'caption' => 'Join',
						'icon' => 'status/videoconference_join',
						'onExecute' => 'javaScript:app.calendar.videoConferenceAction',
						'enabled' => !calendar_hooks::isVideoconferenceDisabled(),
						'allowOnMultiple' => false,
					],
					'recordings' => [
						'caption' => 'Recordings',
						'icon' => 'status/videoconference_recordings',
						'onExecute' => 'javaScript:app.calendar.videoConferenceAction',
						'enabled' => !calendar_hooks::isVideoconferenceDisabled()
							|| calendar_hooks::isVCRecordingSupported(),
						'allowOnMultiple' => false,
					]
				]
			];
		}

		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'url' => 'menuaction=filemanager.filemanager_ui.index&path=/apps/calendar/$id&ajax=true',
				'group' => $group,
				'allowOnMultiple' => false,
				'onExecute' => 'javaScript:app.calendar.cal_fix_app_id',
				'disableClass' => 'rowNoView',
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['infolog'])
		{
			$actions['infolog_app'] = array(
				'caption' => 'InfoLog',
				'icon' => 'infolog/navbar',
				'group' => $group,
				'allowOnMultiple' => false,
				'url' => 'menuaction=infolog.infolog_ui.edit&type=task&action=calendar&action_id=$id',
				'popup' => Link::get_registry('infolog', 'add_popup'),
				// Limit infolog convert to only the apps that support it (some entries may be other apps via integration)
				'enabled' => 'javaScript:app.calendar.action_convert_enabled_check',
				'convert_apps' => Api\Hooks::implemented('infolog_set')
			);
		}
		if($GLOBALS['egw_info']['user']['apps']['mail'])
		{
			//Send to email
			$actions['email'] = array(
				'caption' => 'Email',
				'icon'	=> 'mail/navbar',
				'hideOnDisabled' => true,
				'group' => $group,
				'allowOnMultiple' => false,
				'children' => array(
					'mail' => array(
						'caption' => 'Mail all participants',
						'onExecute' => 'javaScript:app.calendar.action_mail',

					),
					'sendrequest' => array(
						'caption' => 'Meetingrequest to all participants',
						'onExecute' => 'javaScript:app.calendar.action_mail',
					)
				),
			);
		}

		$actions['notifications'] = array(
			'caption' => 'Send notifications',
			'hint' => 'Send notifications to users right now',
			'icon' => 'notifications/navbar',
			'url' => 'calendar.calendar_uiforms.notify&id=$app_id',
			'popup' => Link::get_registry('calendar', 'view_popup'),
			'allowOnMultiple' => false,
			'group' => $group,
			'disableClass' => 'rowNoView',
		);
		if ($GLOBALS['egw_info']['user']['apps']['timesheet'])
		{
			$actions['timesheet'] = array(	// interactive add for a single event
				'icon' => 'timesheet/navbar',
				'caption' => 'Timesheet',
				'group' => $group,
				'allowOnMultiple' => false,
				'hideOnDisabled' => true,	// show only one timesheet action in context menu
				'onExecute' => 'javaScript:app.calendar.action_open',
				'open' => '{"app": "timesheet", "type": "add", "extra": "link_app[]=$app&link_id[]=$app_id"}',
				'popup' => Link::get_registry('timesheet', 'add_popup'),
				// Limit timesheet convert to only the apps that support it (some entries may be other apps via integration)
				'enabled' => 'javaScript:app.calendar.action_convert_enabled_check',
				'convert_apps' => Api\Hooks::implemented('timesheet_set')
			);
			$actions['timesheet-add'] = array(	// automatic add for multiple events
				'icon' => 'timesheet/navbar',
				'caption' => 'Timesheet',
				'group' => $group,
				'allowOnMultiple' => 'only',
				'hideOnDisabled' => true,	// show only one timesheet action in context menu
			);
			// if specific timer is NOT disabled, allow to book further time on existing sheets
			$config = Api\Config::read('timesheet');
			if (!in_array('specific', $config['disable_timer'] ?? []))
			{
				$actions['timesheet'] = [
					'icon' => 'timesheet/navbar',
					'caption' => 'Timesheet',
					'group' => $group,
					'children' => array_map(static function($child) { unset($child['group']); return $child; }, [
						'timesheet-interactive' => ['caption' => 'Add']+$actions['timesheet'],
						'timesheet-add' => ['caption' => 'Add']+$actions['timesheet-add'],
						'timer' => [
							'icon' => 'timesheet/navbar',
							'caption' => 'Start timer',
							'onExecute' => 'javaScript:app.timesheet.egw.start_timer',
							'allowOnMultiple' => false,
						],
					]),
				];
				unset($actions['timesheet-add']);
			}
		}
		$actions['ical'] = array(
			'icon' => 'ical',
			'caption' => 'Export iCal',
			'group' => ++$group,
			'hint' => 'Download this event as iCal',
			'disableClass' => 'rowNoView',
			'postSubmit' => true,	// download needs post submit (not Ajax) to work
		);
		$actions['documents'] = calendar_merge::document_action(
			$this->bo->cal_prefs['document_dir'], ++$group, 'Insert in document', 'document_',
			$this->bo->cal_prefs['default_document'],Api\Storage\Merge::getExportLimit('calendar')
		);
		++$group;
		$actions['delete'] = array(
			'caption' => 'Delete',
			'onExecute' => 'javaScript:app.calendar.cal_delete',
			'group' => $group,
			'disableClass' => 'rowNoDelete',
			'confirm' => lang('Delete this entry')
		);
		// Add in deleted for admins
		$actions['undelete'] = array(
			'caption' => 'Un-delete',
			'onExecute' => 'javaScript:app.calendar.cal_delete',
			'icon' => 'revert',
			'hint' => 'Recover this event',
			'group' => $group,
			'enableClass' => 'rowDeleted',
			'hideOnDisabled' => true,
		);

		//_debug_array($actions);
		return $actions;
	}
}