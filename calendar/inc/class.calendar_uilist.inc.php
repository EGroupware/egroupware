<?php
/**
 * eGroupWare - Calendar's Listview and Search
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2005-9 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

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
		'all'    => 'All events',
		'custom' => 'Selected range',
	);

	/**
	 * Constructor
	 *
	 * @param array $set_states=null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function __construct($set_states=null)
	{
		parent::__construct(true,$set_states);	// call the parent's constructor

		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['calendar']['title'].' - '.lang('Listview').
			// for a single owner we add it's name to the app-header
			(count(explode(',',$this->owner)) == 1 ? ': '.$this->bo->participant_name($this->owner) : '');

		foreach($this->date_filters as $name => $label)
		{
			$this->date_filters[$name] = lang($label);
		}

		$this->check_owners_access();
	}

	/**
	 * Show the calendar on the home page
	 *
	 * @return string with content
	 */
	function &home()
	{
		// set the defaults for the home-page
		$this->__construct(array(
			'date'       => $this->bo->date2string($this->bo->now_su),
			'cat_id'     => 0,
			'filter'     => 'all',
			'owner'      => $this->user,
			'multiple'   => 0,
			'view'       => $this->bo->cal_prefs['mainscreen_showevents'],
		));
		egw_session::appsession('calendar_list','calendar','');	// in case there's already something set

		return $this->listview(null,'',true);
	}

	/**
	 * Show the listview
	 */
	function listview($content=null,$msg='',$home=false)
	{
		if ($_GET['msg']) $msg .= $_GET['msg'];
		if ($this->group_warning) $msg .= $this->group_warning;

		$etpl = new etemplate('calendar.list');

		if (is_array($content) && $content['nm']['rows']['delete'])
		{
			// handle a single delete like delete with the checkboxes
			list($id) = @each($content['nm']['rows']['delete']);
			$content['action'] = 'delete';
			$content['nm']['rows']['checked'] = array($id);
		}
		if (is_array($content) && $content['nm']['rows']['timesheet'])
		{
			// handle a single timesheet like timesheet with the checkboxes
			list($id) = @each($content['nm']['rows']['timesheet']);
			$content['action'] = 'timesheet-add';
			$content['nm']['rows']['checked'] = array($id);
		}
	
		// Handle actions
		if ($content['action'] != '')
		{
			if (!count($content['nm']['rows']['checked']) && !$content['use_all']) {
				$msg = lang('You need to select some events first');
			}
			else
			{
				if ($this->action($content['action'],$content['nm']['rows']['checked'],$content['use_all'],
					$success,$failed,$action_msg,'calendar_list',$msg))
				{
					$msg .= lang('%1 event(s) %2',$success,$action_msg);
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 event(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
				}
			}
			$content['nm']['rows']['checked'] = array();
		}
		$content = array(
			'nm'  => egw_session::appsession('calendar_list','calendar'),
			'msg' => $msg,
		);
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'calendar.calendar_uilist.get_rows',
	 			'filter_no_lang' => True,	// I  set no_lang for filter (=dont translate the options)
				'no_filter2'     => True,	// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,	// I  disable the cat-selectbox
//				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'filter'         => 'after',
				'order'          =>	'cal_start',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',// IO direction of the sort: 'ASC' or 'DESC'
				'default_cols'   => '!week,weekday,cal_title,cal_description,recure,cal_location,cal_owner,cat_id,pm_id',
				'filter_onchange' => "set_style_by_class('table','custom_hide','visibility',this.value == 'custom' ? 'visible' : 'hidden'); if (this.value != 'custom') this.form.submit();",
				'header_left'    => 'calendar.list.dates',
			);
		}
		if ($_GET['search']) {
			$content['nm']['search'] = $_GET['search'];
		}
		if (isset($_REQUEST['keywords']))	// new search => set filters so every match is shown
		{
			$this->adjust_for_search($_REQUEST['keywords'],$content['nm']);
		}
		$sel_options = array(
			'action'     => array(
				'delete' => array('label' => 'Delete', 'title' => 'Delete this event'),
                                'ical' => array('label' => 'Export (iCal)', 'title' => 'Download this event as iCal'),
                        ),
			'filter'	=> &$this->date_filters
		);

		// More actions
		if($GLOBALS['egw_info']['apps']['timesheet']) {
			$sel_options['action']['timesheet-add'] = array('label' => 'Create timesheet', 'title' => 'Create a timesheet entry from this event');
		}
		// Add in deleted for admins
		$config = config::read('phpgwapi');
		if($config['calendar_delete_history'] && $GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$sel_options['action']['undelete'] = array('label' => 'Un-Delete', 'title' => 'Recover this event');
		}
		foreach($this->bo->verbose_status as $key => $value)
		{
			if($key == 'G') continue;
			$sel_options['action'][lang('Change your participant status')]['status-'.$key] = $value;
		}
		// add scrollbar to long describtion, if user choose so in his prefs
		if ($this->prefs['limit_des_lines'] > 0 || (string)$this->prefs['limit_des_lines'] == '')
		{
			$content['css'] .= '<style type="text/css">@media screen { .listDescription {  max-height: '.
				(($this->prefs['limit_des_lines'] ? $this->prefs['limit_des_lines'] : 5) * 1.35).       // dono why em is not real lines
				'em; overflow: auto; }}</style>';
		}
		unset($sel_options['action'][lang('Change your participant status')]['G']);
		$GLOBALS['egw_info']['flags']['java_script'] .= $this->get_javascript();

		$html = $etpl->exec('calendar.calendar_uilist.listview',$content,$sel_options,$readonlys,'',$home ? -1 : 0);

		// Not sure why this has to be echoed instead of appended, but that's what works.
		echo calendar_uiviews::edit_series();

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
			$params['filter'] = 'all';
			$params['sort'] = 'DESC';
			unset($params['col_filter']['participant']);
		}
		else
		{
			$params['filter'] = 'after';
			$params['sort'] = 'ASC';
		}
	}

	/**
	 * query calendar for nextmatch in the listview
	 *
	 * @internal
	 * @param array &$params parameters
	 * @param array &$rows returned rows/events
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows(&$params,&$rows,&$readonlys)
	{
		//echo "uilist::get_rows() params="; _debug_array($params);
		if (isset($_GET['listview_days']) && is_numeric($_GET['listview_days']))
		{
			$params['filter'] = 'fixed';
		}
		if ($params['filter'] == 'custom')
		{
			if (!$params['startdate'] && !$params['enddate'])
			{
				$params['filter'] = 'all';
			}
			elseif (!$params['startdate'])
			{
				$params['filter'] = 'before';
				$this->manage_states(array('date' => $this->bo->date2string($params['enddate'])));
			}
			elseif (!$params['enddate'])
			{
				$params['filter'] = 'after';
				$this->manage_states(array('date' => $this->bo->date2string($params['startdate'])));
			}
		}
		$old_params = egw_session::appsession('calendar_list','calendar');
		if ($old_params['filter'] && $old_params['filter'] != $params['filter'])	// filter changed => order accordingly
		{
			$params['order'] = 'cal_start';
			$params['sort'] = $params['filter'] == 'after' ? 'ASC' : 'DESC';
		}
		if ($old_params['search'] != $params['search'])
		{
			$this->adjust_for_search($params['search'],$params);
		}
		if (!$params['csv_export']) egw_session::appsession('calendar_list','calendar',$params);

		// do we need to query custom fields and which
		$select_cols = explode(',',$params['selectcols']);
		if (in_array('cfs',$select_cols))
		{
			$cfs = array();
			foreach($select_cols as $col)
			{
				if ($col[0] == '#') $cfs[] = substr($col,1);
			}
		}
		$search_params = array(
			'cat_id'  => $this->cat_id,
			'filter'  => $this->filter,
			'query'   => $params['search'],
			'offset'  => (int) $params['start'],
			'num_rows'=> $params['num_rows'],
			'order'   => $params['order'] ? $params['order'].' '.$params['sort'] : 'cal_start',
			'cfs'     => $params['csv_export'] ? array() : $cfs,
		);
		switch($params['filter'])
		{
			case 'all':
				break;
			case 'before':
				$search_params['end'] = $this->date;
				$label = lang('Before %1',$this->bo->long_date($this->date));
				break;
			case 'custom':
				$GLOBALS['egw']->js->set_onload("set_style_by_class('table','custom_hide','visibility','visible');");
				$this->first = $search_params['start'] = $params['startdate'];
				$this->last  = $search_params['end'] = $params['enddate'];
				$label = $this->bo->long_date($this->first,$this->last);
				break;
			case 'fixed':
				if ($this->listview_days == 5 || $this->listview_days == 7)	// weekview
				{
					$this->first = $this->datetime->get_weekday_start($this->year,$this->month,$this->day);
					$this->last = $this->bo->date2array($this->first);
					$this->last['day'] += (int) $this->listview_days - 1;
					$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
					unset($this->last['raw']);
					$this->last = $this->bo->date2ts($this->last);
					$this->date_filters['fixed'] = $label = lang('Week').' '.adodb_date('W',$this->first).': '.$this->bo->long_date($this->first,$this->last);
					$search_params['start'] = $this->first;
					$search_params['end'] = $this->last;
					break;
				}
				elseif ((string)$this->listview_days === '0')	// monthview
				{
					$this->first = $this->bo->date2array($this->date);
					$this->first['day'] = 1;
					unset($this->first['raw']);
					$this->last = $this->first;
					$this->last['month'] += 1;
					$this->first = $this->bo->date2ts($this->first);
					$this->last = $this->bo->date2ts($this->last);
					$this->last--;
					$this->date_filters['fixed'] = $label = lang(adodb_date('F',$this->bo->date2ts($this->date))).' '.$this->year;
					$search_params['start'] = $this->first;
					$search_params['end'] = $this->last;
					break;
				}
				// fall through to after given date
			case 'after':
			default:
				$label = lang('After %1',$this->bo->long_date($this->date));
				$search_params['start'] = $this->date;
				break;
		}
		if ($label)
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$label;
		}
		if ((int) $params['col_filter']['participant'])
		{
			$search_params['users'] = (int) $params['col_filter']['participant'];
		}
		elseif(empty($params['search']))	// active search displays entries from all users
		{
			$search_params['users'] = explode(',',$this->owner);
		}
		$rows = array();
		foreach((array) $this->bo->search($search_params) as $event)
		{
			$readonlys['view['.$event['id'].']'] = !($readonlys['edit['.$event['id'].']'] = !$this->bo->check_perms(EGW_ACL_EDIT,$event));
			// Delete disabled for other applications
			$readonlys['delete['.$event['id'].']'] = !$this->bo->check_perms(EGW_ACL_DELETE,$event) || !is_numeric($event['id']);
			// Filemanager disabled for other applications
			$readonlys['filemanager['.$event['id'].']'] = !is_numeric($event['id']);

			$event['recure'] = $this->bo->recure2string($event);
			if ($params['csv_export'])
			{
				$event['participants'] = implode(",\n",$this->bo->participants($event,true));
			}
			else
			{
				$event['parts'] = implode(",\n",$this->bo->participants($event,true));
				$event['date'] = $this->bo->date2string($event['start']);
			}
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
				if (($is_private = calendar_bo::integration_get_private($app,$app_id,$event)))
				{
					$icons[] = html::image('calendar','private');
				}
				else
				{
					$icons = calendar_uiviews::integration_get_icons($app,$app_id,$event);
				}
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
			}
			elseif ($event['recur_type'] != MCAL_RECUR_NONE)
			{
				$event['edit_link'] = "edit_series({$event['id']}, {$event['start']});return false;";
				$event['app_id'] .= ':'.$event['recur_date'];
			}
			else
			{
				$view_link = egw::link('/index.php',array('menuaction'=>'calendar.calendar_uiforms.edit','cal_id'=>$event['id'],'date'=>$this->bo->date2string($event['start'])));
                                $event['edit_link'] = $this->popup($view_link).'; return false;';
			}

			$rows[] = $event;
			unset($app);
			unset($app_id);
		}
		$wv=0;
		$dv=0;
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
        function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg)
        {
		//echo '<p>' . __METHOD__ . "('$action',".print_r($checked,true).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		$msg = null;

		// Split out combined values
		if(strpos($action, 'status') !== false) {
			list($action, $status) = explode('-', $action);
		}

                if ($use_all) 
		{
                        // get the whole selection
                        $query = is_array($session_name) ? $session_name : egw_session::appsession($session_name,'calendar');
			@set_time_limit(0);                     // switch off the execution time limit, as for big selections it's too small
			$query['num_rows'] = -1;        // all
			$this->get_rows($query,$checked,$readonlys,($action != 'ical'));       // true = only return the id's
                } else {
			// Pull the date for recurring events
			$split = array();
			foreach($checked as $key) {
				list($id, $recur_date) = explode(':', $key);
				$split[] = array(
					'id'	=>	$id,
					'recur_date'	=>	$recur_date
				);
			}
			$checked = $split;
		}

		// Actions where one action is done to the group
		switch($action) 
		{
			case 'ical':
				$boical = new calendar_ical();
				$ical =& $boical->exportVCal($checked,'2.0','PUBLISH',false);
                                html::content_header($content['file'] ? $content['file'] : 'event.ics','text/calendar',bytes($ical));
                                echo $ical;
                                common::egw_exit();
				break;
			
		}

		// Actions where the action is applied to each entry
		if(strpos($action, 'timesheet') !== false) {
			$timesheet_bo = new timesheet_bo();
		}
		foreach($checked as $event) 
		{
			if(!(int)$event['id'] && preg_match('/^([a-z_-]+)([0-9]+)$/i',$event['id'],$matches))
			{
				$app = $matches[1];
				$app_id = $matches[2];
			} else {
				$id = $event['id'];
				$recur_date = $event['recur_date'];
			}
			switch($action) 
			{
				case 'delete':
					$action_msg = lang('deleted');
                                        if ($id && $this->bo->check_perms(EGW_ACL_DELETE,$id))
					{
						if($this->bo->delete($id, $recur_date)) 
						{
							$success++;
						}
					}
					else
					{
						$failed++;
					}
					break;
				case 'undelete':
					$action_msg = lang('recovered');
                                        if ($id && ($event = $this->bo->read($id, $recur_date)) && $this->bo->check_perms(EGW_ACL_EDIT,$id) &&
						is_array($event) && $event['deleted'])
					{
						$event['deleted'] = false;
						if($this->bo->save($event))
						{
							$success++;
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
						calendar_so::split_status($old_status, $quantity, $role);
						if ($old_status != $status)
						{
							//echo "<p>$uid: status changed '$data[old_status]' --> '$status<'/p>\n";
							$new_status = calendar_so::combine_status($status, $quantity, $role);
							if ($this->bo->set_status($id,$GLOBALS['egw_info']['user']['account_id'],$new_status,$recur_date))
							{
								$success++;
								//$msg = lang('Status changed');
							}
							else
							{
								$failed++;
							}
						}
					} else {
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
						$query = egw_session::appsession('calendar_list','calendar');
						$query['query'] = $app_id;
						$query['search'] = $app_id;
						$result = $this->bo->search($query);
						$event = $result[$app.$app_id];
					}
					if(!$event) 
					{
						$failed++;
						continue;
					}
					$timesheet = array(
						'ts_title'		=>	$event['title'],
						'ts_description'	=>	$event['description'],
						'ts_start'		=>	$event['start'],
						'ts_duration'		=>	($event['end'] - $event['start']) / 60,
						'ts_quantity'		=>	($event['end'] - $event['start']) / 3600,
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
					if(!$err) {
						$success++;

						// Can't link to just one of a recurring series of events
						if(!$recur_date || $app) {
							// Create link
							$link_id = $app ? $app_id : $id;
							egw_link::link($app ? $app : 'calendar', $link_id, 'timesheet', $timesheet_bo->data['ts_id']);
						}
					} 
					else
					{
						$failed++;
					}
					$msg = lang('Timesheet entries created for ');
					break;
			}
			unset($app);
			unset($app_id);
		}
		//error_log(__METHOD__."('$action', ".array2string($checked).', '.array2string($use_all).") sucess=$success, failed=$failed, action_msg='$action_msg', msg=".array2string($msg).' returning '.array2string(!$failed));
		return !$failed;
	}

	public function get_javascript() 
	{
		return '<script LANGUAGE="JavaScript">
		function do_action(selbox)
		{
			if (selbox.value != "")
			{
				if (selbox.value == "delete") 
				{
					if (confirm("' . lang('Delete') . '")) selbox.form.submit();
				}
				else
				{
					selbox.form.submit();
				}
			}
				selbox.value = "";
		}
		</script>';
	}
}
