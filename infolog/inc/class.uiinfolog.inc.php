<?php
/**
 * InfoLog - User interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-6 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.boinfolog.inc.php');

/**
 * This class is the UI-layer (user interface) of InfoLog
 */
class uiinfolog
{
	var $public_functions = array
	(
		'index'       => True,
		'edit'        => True,
		'delete'      => True,
		'close'       => True,
		'admin'       => True,
		'hook_view'   => True,
		'writeLangFile' => True,
		'import_mail' => True,
	);
	/**
	 * reference to the infolog preferences of the user
	 *
	 * @var array
	 */
	var $prefs;
	/**
	 * instance of the bo-class
	 * 
	 * @var boinfolog
	 */
	var $bo;
	/**
	 * reference to instance of the link-class of bo
	 * 
	 * @var bolink
	 */
	var $link;
	/**
	 * instance of the etemplate class
	 *
	 * @var etemplate
	 */
	var $tmpl;
	/**
	 * reference to the html object of etemplate
	 *
	 * @var html
	 */
	var $html;
	/**
	 * allowed units and hours per day, can be overwritten by the projectmanager configuration, default all units, 8h
	 * 
	 * @var string
	 */
	var $duration_format = ',';	// comma is necessary!
	
	var $icons = array(
		'type' => array(
			'task'      => 'task.gif',      'task_alt'      => 'Task',
			'phone'     => 'phone.gif',     'phone_alt'     => 'Phonecall',
			'note'      => 'note.gif',      'note_alt'      => 'Note',
			'confirm'   => 'confirm.gif',   'confirm_alt'   => 'Confirmation',
			'reject'    => 'reject.gif',    'reject_alt'    => 'Reject',
			'email'     => 'email.gif',     'email_alt'     => 'Email' ),
		'action' => array(
			'new'       => 'new.gif',       'new_alt'       => 'Add Sub',
			'view'      => 'view.gif',      'view_alt'      => 'View Subs',
			'parent'    => 'parent.gif',    'parent_alt'    => 'View other Subs',
			'edit'      => 'edit.gif',      'edit_alt'      => 'Edit',
			'addfile'   => 'addfile.gif',   'addfile_alt'   => 'Add a file',
			'delete'    => 'delete.gif',    'delete_alt'    => 'Delete',
			'close'     => 'done.gif',      'close_alt'     => 'Close' ),
		'status' => array(
			'billed'    => 'billed.gif',    'billed_alt'    => 'billed',
			'done'      => 'done.gif',      'done_alt'      => 'done',
			'will-call' => 'will-call.gif', 'will-call_alt' => 'will-call',
			'call'      => 'call.gif',      'call_alt'      => 'call',
			'ongoing'   => 'ongoing.gif',   'ongoing_alt'   => 'ongoing',
			'offer'     => 'offer.gif',     'offer_alt'     => 'offer' )
	);
	var $filters = array(
		'none'                     => 'no Filter',
		'done'                     => 'done',
		'responsible'              => 'responsible',
		'responsible-open-today'   => 'responsible open',
		'responsible-open-overdue' => 'responsible overdue',
		'responsible-upcoming'     => 'responsible upcoming',
		'delegated'                => 'delegated',
		'delegated-open-today'     => 'delegated open',
		'delegated-open-overdue'   => 'delegated overdue',
		'delegated-upcoming'       => 'delegated upcomming',
		'own'                      => 'own',
		'own-open-today'           => 'own open',
		'own-open-overdue'         => 'own overdue',
		'own-upcoming'             => 'own upcoming',
		'open-today'               => 'open',
		'open-overdue'             => 'overdue',
		'upcoming'                 => 'upcoming',
	);
	var $messages = array(
		'edit'    => 'InfoLog - Edit',
		'add'     => 'InfoLog - New',
		'add_sub' => 'InfoLog - New Subproject',
		'sp'      => '- Subprojects from',
		're'      => 'Re:'
	);

	/**
	 * Constructor
	 *
	 * @return uiinfolog
	 */
	function uiinfolog()
	{
		$this->bo =& new boinfolog();

		$this->link = &$this->bo->link;
		
		$this->tmpl =& CreateObject('etemplate.etemplate');
		$this->html =& $this->tmpl->html;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];
		
		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['infolog'];
		
		// read the duration format from project-manager
		if ($GLOBALS['egw_info']['apps']['projectmanager'])
		{
			$pm_config =& CreateObject('phpgwapi.config','projectmanager');
			$pm_config->read_repository();
			$this->duration_format = str_replace(',','',$pm_config->config_data['duration_units']).','.$pm_config->config_data['hours_per_workday'];
			unset($pm_config);
		}
		if ($this->bo->history)
		{
			$this->filters['deleted'] = 'deleted';
		}
		/* these are just for testing of the notifications
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['delegated-open-enddate'.date('Y-m-d',time()+$i*24*60*60)] = "delegated due in $i day(s)";
		}
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['responsible-open-enddate'.date('Y-m-d',time()+$i*24*60*60)] = "responsible due in $i day(s)";
		}
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['delegated-open-date'.date('Y-m-d',time()+$i*24*60*60)] = "delegated starting in $i day(s)";
		}
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['responsible-open-date'.date('Y-m-d',time()+$i*24*60*60)] = "responsible starting in $i day(s)";
		}
		*/
		$GLOBALS['uiinfolog'] =& $this;	// make ourself availible for ExecMethod of get_rows function
	}

	/**
	 * Sets additional fields for one infolog entry, which are not persistent in the DB
	 *
	 * @param array $info infolog entry read from the db
	 * @param array &$readonlys ACL specific settings for the buttons
	 * @param string $action
	 * @param string/int $action_id
	 * @param boolean $show_links
	 * @param int $details
	 * @return array
	 */
	function get_info($info,&$readonlys,$action='',$action_id='',$show_links=false,$details = 1)
	{
		if (!is_array($info))
		{
			$info = $this->bo->read($info);
		}
		$id = $info['info_id'];
		$done = $info['info_status'] == 'done' || $info['info_status'] == 'billed';
		$info['sub_class'] = $this->bo->enums['priority'][$info['info_priority']] . ($done ? '_done' : '');
		if (!$done && $info['info_enddate'] < $this->bo->user_time_now)
		{
			$info['end_class'] = 'overdue';
		}
		if (!isset($info['info_anz_subs'])) $info['info_anz_subs'] = $this->bo->anzSubs($id);
		$this->bo->link_id2from($info,$action,$action_id);	// unset from for $action:$action_id
		$info['info_percent'] = (int) $info['info_percent'].'%';
		
		$readonlys["edit[$id]"] = !($this->bo->check_access($info,EGW_ACL_EDIT) || // edit rights or more then standard responsible rights
			$this->bo->is_responsible($info) && array_diff($this->bo->responsible_edit,array('info_status','info_percent','info_datecompleted')));
		$readonlys["close[$id]"] = $done || ($readonlys["edit_status[$id]"] = 
			!($this->bo->check_access($info,EGW_ACL_EDIT) || $this->bo->is_responsible($info)));
		$readonlys["edit_status[$id]"] = $readonlys["edit_percent[$id]"] = 
			!$this->bo->check_access($info,EGW_ACL_EDIT) && !$this->bo->is_responsible($info) && 
			!$this->bo->check_access($info,EGW_ACL_UNDELETE);	// undelete is handled like status edit
		$readonlys["delete[$id]"] = !$this->bo->check_access($info,EGW_ACL_DELETE);
		$readonlys["sp[$id]"] = !$this->bo->check_access($info,EGW_ACL_ADD);
		$readonlys["view[$id]"] = $info['info_anz_subs'] < 1;
		$readonlys['view[0]'] = True;	// no parent
		$readonlys["timesheet[$id]"] = !isset($GLOBALS['egw_info']['user']['apps']['timesheet']);

		if (!$show_links) $show_links = $this->prefs['show_links'];

		if (($show_links != 'none' && $show_links != 'no_describtion' || 
			 $this->prefs['show_times'] && isset($GLOBALS['egw_info']['user']['apps']['timesheet'])) && 
			($links = $this->link->get_links('infolog',$info['info_id'])))
		{
			$timesheets = array();
			foreach ($links as $link)
			{
				if ($show_links != 'none' && $show_links != 'no_describtion' &&
					$link['link_id'] != $info['info_link_id'] &&
				    ($link['app'] != $action || $link['id'] != $action_id) &&
					($show_links == 'all' || ($show_links == 'links') === ($link['app'] != $this->link->vfs_appname)))
				{
					$info['filelinks'][] = $link;
				}
				if (!$info['pm_id'] && $link['app'] == 'projectmanager')
				{
					$info['pm_id'] = $link['id'];
				}
				if ($link['app'] == 'timesheet') $timesheets[] = $link['id'];
				
				if ($link['app'] != 'timesheet' && $link['app'] != $this->link->vfs_appname)
				{
					$info['extra_links'] .= '&link_app[]='.$link['app'].'&link_id[]='.$link['id'];
				}
			}
			if ($this->prefs['show_times'] && isset($GLOBALS['egw_info']['user']['apps']['timesheet']) && $timesheets)
			{
				$sum = ExecMethod('timesheet.botimesheet.sum',$timesheets);
				$info['info_sum_timesheets'] = $sum['duration'];
			}
		}
		$info['info_type_label'] = $this->bo->enums['type'][$info['info_type']];
		$info['info_status_label'] = isset($this->bo->status[$info['info_type']][$info['info_status']]) ? 
			$this->bo->status[$info['info_type']][$info['info_status']] : $info['info_status'];
		
		if (!$this->prefs['show_percent'] || $this->prefs['show_percent'] == 2 && !$details)
		{
			if ($info['info_status'] == 'ongoing' && $info['info_type'] != 'phone')
			{
				$info['info_status'] = $info['info_status_label'] = $info['info_percent'];
			}
			$readonlys["edit_percent[$id]"] = true;
		}
		elseif($readonlys["edit_percent[$id]"])	// show percent, but button is switched off
		{
			$info['info_percent2'] = $info['info_percent'];
		}
		if ($this->prefs['show_id'] == 1 || $this->prefs['show_id'] == 2 && $details)
		{
			$info['info_number'] = $info['info_id'];
		}
		return $info;
	}

	/**
	 * Saves state of the infolog list in the session
	 *
	 * @param array $values
	 */
	function save_sessiondata($values)
	{
		$for = @$values['session_for'] ? $values['session_for'] : @$this->called_by;
		//echo "<p>$for: uiinfolog::save_sessiondata(".print_r($values,True).") called_by='$this->called_by', for='$for'<br />".function_backtrace()."</p>\n";
		$GLOBALS['egw']->session->appsession($for.'session_data','infolog',array(
			'search' => $values['search'],
			'start'  => $values['start'],
			'num_rows' => $values['num_rows'],
			'filter' => $values['filter'],
			'filter2' => $values['filter2'],
			'cat_id' => $values['cat_id'],
			'order'  => $values['order'],
			'sort'   => $values['sort'],
			'action' => $values['action'],
			'action_id' => $values['action_id'],
			'action_title' => $values['action_title'],
			'col_filter' => $values['col_filter'],
			'session_for' => $for
		));
	}

	/**
	 * reads list-state from the session
	 *
	 * @return array
	 */
	function read_sessiondata()
	{
		$values = $GLOBALS['egw']->session->appsession(@$this->called_by.'session_data','infolog');
		if (!@$values['session_for'] && $this->called_by)
		{
			$values['session_for'] = $this->called_by;
			$this->save_sessiondata($values);
		}
		//echo "<p>called_by='$this->called_by': uiinfolog::read_sessiondata() = ".print_r($values,True)."</p>\n";
		return $values;
	}

	/**
	 * Callback for nextmatch widget
	 *
	 * @param array &$query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	function get_rows(&$query,&$rows,&$readonlys)
	{
		//echo "<p>uiinfolog.get_rows(start=$query[start],search='$query[search]',filter='$query[filter]',cat_id=$query[cat_id],action='$query[action]/$query[action_id]',col_filter=".print_r($query['col_filter'],True).")</p>\n";
		if (!isset($query['start'])) $query['start'] = 0;

		$this->save_sessiondata($query);

		// check if we have a custom, type-specific template
		unset($query['template']);
		unset($query['custom_fields']);
		if ($query['col_filter']['info_type'])
		{
			$tpl =& new etemplate;
			if ($tpl->read('infolog.index.rows.'.$query['col_filter']['info_type']))
			{
				$query['template'] =& $tpl;
				$query['custom_fields'] = true;	// read the custom fields too
			}
			//echo "<p align=right>template ='".'infolog.index.rows.'.$query['col_filter']['info_type']."'".(!$query['template'] ? ' not' : '')." found</p>\n";
		}
		$ids = $this->bo->search($query);
		if (!is_array($ids))
		{
			$ids = array( );
		}
		$details = $query['filter2'] == 'all';
		// add a '-details' to the name of the columnselection pref
		if ($details)
		{
			$query['columnselection_pref'] = (is_object($query['template'])?$query['template']->name:'infolog.index.rows').'-details';
			$query['default_cols'] = '!cat_id,info_used_time_info_planned_time,info_id';
		}
		else
		{
			$query['columnselection_pref'] = 'infolog.index.rows';
			$query['default_cols'] = '!cat_id,info_datemodified,info_used_time_info_planned_time,info_id';
		}
		// set old show_times pref, that get_info calculates the cumulated time of the timesheets
		$this->prefs['show_times'] = strpos($this->prefs['nextmatch-'.$query['columnselection_pref']],'info_used_time_info_planned_time') !== false;

		$readonlys = $rows = array();
		foreach($ids as $id => $info)
		{
			$info = $this->get_info($info,$readonlys,$query['action'],$query['action_id'],$query['filter2'],$details);
			if (!$query['filter2'] && $this->prefs['show_links'] == 'no_describtion' ||
				$query['filter2'] == 'no_describtion')
			{
				unset($info['info_des']);
			}
			$rows[] = $info;
		}
		if ($query['cat_id']) $rows['no_cat_id'] = true;
		if ($query['no_actions']) $rows['no_actions'] = true;
		$rows['no_timesheet'] = !isset($GLOBALS['egw_info']['user']['apps']['timesheet']);
		$rows['duration_format'] = ','.$this->duration_format.',,1';
		if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$rows['no_info_owner_info_responsible'] = true;
			// dont show owner, responsible in the columnselection
			$query['options-selectcols']['info_owner'] = $query['options-selectcols']['info_responsible'] = false;
		}
		//echo "<p>readonlys = "; _debug_array($readonlys);
		//echo "rows=<pre>".print_r($rows,True)."</pre>\n";
		
		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'infolog')
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Infolog');
			if ($query['filter'] != 'none')
			{
				$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang($this->filters[$query['filter']]);
			}
			if ($query['action'] && ($title = $query['action_title'] ? $query['action_title'] : $this->link->title($query['action'],$query['action_id'])))
			{
				$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$title;
			}
		}
		return $query['total'];
	}

	/**
	 * Shows the infolog list
	 *
	 * @param array/string $values=null etemplate content or 'reset_action_view' if called by index.php to reset an action-view
	 * @param string $action='' if set only entries liked to that $action:$action_id are shown
	 * @param string $action_id='' if set only entries liked to that $action:$action_id are shown
	 * @param mixed $called_as=0 this is how we got called, for a hook eg. the call-params of that page containing the hook
	 * @param boolean $extra_app_header=false
	 * @param boolean $return_html=false
	 * @param string $own_referer='' this is our own referer
	 * @param string $action_title='' app_header for the action, if '' we try the link-title
	 */
	function index($values = null,$action='',$action_id='',$called_as=0,$extra_app_header=False,$return_html=False,$own_referer='',$action_title='')
	{
		if (is_array($values))
		{
			$called_as = $values['called_as'];
			$own_referer = $values['own_referer'];
		}
		elseif ($own_referer === '')
		{
			$own_referer = $GLOBALS['egw']->common->get_referer();
			if (strpos($own_referer,'menuaction=infolog.uiinfolog.edit') !== false)
			{
				$own_referer = $GLOBALS['egw']->session->appsession('own_session','infolog');
			}
			else
			{
				$GLOBALS['egw']->session->appsession('own_session','infolog',$own_referer);
			}
		}
		if (!$action)
		{
			$action = $values['action'] ? $values['action'] : get_var('action',array('POST','GET'));
			$action_id = $values['action_id'] ? $values['action_id'] : get_var('action_id',array('POST','GET'));
			$action_title = $values['action_title'] ? $values['action_title'] : get_var('action_title',array('POST','GET'));

			if ($values === 'reset_action_view')	// only read action from session, if not called by index.php
			{
				$session = $this->read_sessiondata();
				$session['action'] = $action = '';
				$session['action_id'] = $action_id = 0;
				$session['action_title'] = $action_title = '';
				$this->save_sessiondata($session);
				unset($session);
			}
			elseif (!$action)
			{
				$session = $this->read_sessiondata();
				$action = $session['action'];
				$action_id = $session['action_id'];
				$action_title = $session['action_title'];
				unset($session);
			}
		}
		//echo "<p align=right>uiinfolog::index(action='$action/$action_id',called_as='$called_as/$values[referer]',own_referer='$own_referer') values=\n"; _debug_array($values);
		if (!is_array($values))
		{
			$values = array('nm' => $this->read_sessiondata());
			if (isset($_GET['filter']) && $_GET['filter'] != 'default' || !isset($values['nm']['filter']) && !$this->called_by)
			{
				$values['nm']['filter'] = $_GET['filter'] && $_GET['filter'] != 'default' ? $_GET['filter'] :
					$this->prefs['defaultFilter'];
			}
			if (!isset($values['nm']['order']) || !$values['nm']['order'])
			{
				$values['nm']['order'] = 'info_datemodified';
				$values['nm']['sort'] = 'DESC';
			}
			$values['msg'] = $_GET['msg'];
			$values['action'] = $action;
			$values['action_id'] = $action_id;
		}
		if ($values['nm']['add'])
		{
			$values['add'] = $values['nm']['add'];
			unset($values['nm']['add']);
		}
		if ($values['add'] || $values['cancel'] || isset($values['nm']['rows']) || isset($values['main']))
		{
			if ($values['add'])
			{
				list($type) = each($values['add']);
				return $this->edit(0,$action,$action_id,$type,$called_as);
			}
			elseif ($values['cancel'] && $own_referer)
			{
				$session = $this->read_sessiondata();
				unset($session['action']);
				unset($session['action_id']);
				$this->save_sessiondata($session);
				$this->tmpl->location($own_referer);					
			}
			else
			{
				list($do,$do_id) = isset($values['main']) ? each($values['main']) : @each($values['nm']['rows']);
				list($do_id) = @each($do_id);
				//echo "<p>infolog::index: do='$do/$do_id', referer="; _debug_array($called_as);
				switch($do)
				{
					case 'edit':
					case 'edit_status':
						return $this->edit($do_id,$action,$action_id,'',$called_as);
					case 'delete':
						if (!($values['msg'] = $this->delete($do_id,$called_as,$called_as ? '' : 'index'))) return;
						// did we deleted the entries, whos subentries we are showing?
						if ($action == 'sp' && $action_id == $do_id)
						{
							// redirect to our referer or reset the subentry view
							if (!$called_as && $own_referer)
							{
								$this->tmpl->location($own_referer);	// eg. redirect back to calendar
							}
							else
							{
								unset($action_id); unset($action);
							}
						}
						break;
					case 'close':
						$this->close($do_id,$called_as);
						break;
					case 'sp':
						return $this->edit(0,'sp',$do_id,'',$called_as);
					case 'view':
						$value = array();
						$action = 'sp';
						$action_id = $do_id;
						break;
					default:
						$value = array();
						$action = '';
						$action_id = 0;
						break;
				}
			}
		}
		switch ($action)
		{
			case 'sp':
				if (!$this->bo->read($action_id))
				{
					$action = '';
					$action_id = 0;
					break;
				}
				$values['main'][1] = $this->get_info($action_id,$readonlys['main']);
				break;
		}
		$readonlys['cancel'] = $action != 'sp';

		$this->tmpl->read('infolog.index');

		$values['nm']['options-filter'] = $this->filters;
		$values['nm']['get_rows'] = 'infolog.uiinfolog.get_rows';
		$values['nm']['options-filter2'] = (in_array($this->prefs['show_links'],array('all','no_describtion')) ? array() : array(
			''               => 'default',
		)) + array(
			'no_describtion' => 'no details',
			'all'            => 'details',
		);
		if(!isset($values['nm']['filter2'])) $values['nm']['filter2'] = $this->prefs['show_links'];
		// disable columns for main entry as set in the pref for details or no details
		if ($action == 'sp')
		{
			$pref = 'nextmatch-infolog.index.rows'.($values['nm']['filter2']=='all'?'-details':'');
			foreach(array('info_used_time_info_planned_time','info_datemodified','info_owner_info_responsible') as $name)
			{
				$values['main']['no_'.$name] = strpos($this->prefs[$pref],$name) === false;
			}
		}
		$values['nm']['header_right'] = 'infolog.index.header_right';
		if ($extra_app_header)
		{
			$values['nm']['header_left'] = 'infolog.index.header_left';
		}
		$values['nm']['bottom_too'] = True;
		$values['nm']['never_hide'] = isset($this->prefs['never_hide']) ? 
			$this->prefs['never_hide'] : $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] > 15;
		$values['action'] = $persist['action'] = $values['nm']['action'] = $action;
		$values['action_id'] = $persist['action_id'] = $values['nm']['action_id'] = $action_id;
		$values['action_title'] = $persist['action_title'] = $values['nm']['action_title'] = $action_title;
		$persist['called_as'] = $called_as;
		$persist['own_referer'] = $own_referer;

		$all_stati = array();
		foreach($this->bo->status as $typ => $stati)
		{
			if ($typ != 'defaults') $all_stati += $stati;
		}
		if (!$called_as)
		{
			$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualInfologIndex');
		}
		else
		{
			$values['css'] = '<style type="text/css">@import url('.$GLOBALS['egw_info']['server']['webserver_url'].'/infolog/templates/default/app.css);'."</style>";
		}
		return $this->tmpl->exec('infolog.uiinfolog.index',$values,array(
			'info_type'     => $this->bo->enums['type'],
			'info_status'   => $all_stati
		),$readonlys,$persist,$return_html ? -1 : 0);
	}

	function close($values=0,$referer='')
	{
		$info_id = (int) (is_array($values) ? $values['info_id'] : ($values ? $values : $_GET['info_id']));
		$referer = is_array($values) ? $values['referer'] : $referer;
		
		if ($info_id)
		{
			$values = array(
				'info_id'     => $info_id,
				'info_status' => 'done',
				'info_percent'=> 100,
				'info_datecompleted' => $this->bo->now_su,
			);
			$this->bo->write($values);
			
			$query = array('action'=>'sp','action_id'=>$info_id);
			foreach((array)$this->bo->search($query) as $info)
			{
				if ($info['info_id_parent'] == $info_id)	// search also returns linked entries!
				{
					$this->close($info['info_id'],$referer);	// we call ourselfs recursive to process subs from subs too
				}
			}
		}
		if ($referer) $this->tmpl->location($referer);
	}

	function delete($values=0,$referer='',$called_by='')
	{
		$info_id = (int) (is_array($values) ? $values['info_id'] : ($values ? $values : $_GET['info_id']));
		$referer = is_array($values) ? $values['referer'] : $referer;
		
		if (!is_array($values) && $info_id > 0 && !$this->bo->anzSubs($info_id))	// entries without subs get confirmed by javascript
		{
			$values = array('delete' => true);
		}
		//echo "<p>uiinfolog::delete(".print_r($values,true).",'$referer','$called_by') info_id=$info_id</p>\n";

		if (is_array($values) || $info_id <= 0)
		{
			if (($values['delete'] || $values['delete_subs']) && $info_id > 0 && $this->bo->check_access($info_id,EGW_ACL_DELETE))
			{
				$deleted = $this->bo->delete($info_id,$values['delete_subs'],$values['info_id_parent']);
			}
			if ($called_by)		// direct call from the same request
			{
				return $deleted ? lang('InfoLog entry deleted') : '';
			}
			if ($values['called_by'] == 'edit')	// we run in the edit popup => give control back to edit
			{
				$this->edit(array(
					'info_id' => $info_id,
					'button'  => array('deleted' => true),	// not delete!
					'referer' => $referer,
					'msg'     => $deleted ? lang('Infolog entry deleted') : '',
				));
			}
			return $referer ? $this->tmpl->location($referer) : $this->index();
		}
		$readonlys = $values = array();
		$values['main'][1] = $this->get_info($info_id,$readonlys['main']);

		$this->tmpl->read('infolog.delete');

		$values['nm'] = array(
			'action'         => 'sp',
			'action_id'      => $info_id,
			'options-filter' => $this->filters,
			'get_rows'       => 'infolog.uiinfolog.get_rows',
			'no_filter2'     => True,
			'never_hide'     => isset($this->prefs['never_hide']) ? 
				$this->prefs['never_hide'] : 
				$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] > 15,
		);
		$values['main']['no_actions'] = $values['nm']['no_actions'] = True;

		$persist['info_id'] = $info_id;
		$persist['referer'] = $referer;
		$persist['info_id_parent'] = $values['main'][1]['info_id_parent'];				
		$persist['called_by'] = $called_by;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Delete');
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualInfologDelete');

		$this->tmpl->exec('infolog.uiinfolog.delete',$values,'',$readonlys,$persist,$called_by == 'edit' ? 2 : 0);
	}
	
	/**
	 * Edit/Create an InfoLog Entry
	 *
	 * @param array $content=null Content from the eTemplate Exec call or info_id on inital call
	 * @param string $action='' Name of an app of 'sp' for a infolog-sub
	 * @param int $action_id=0 Id of app-entry to which a link is created
	 * @param string $type='' Type of log-entry: note,todo,task
	 * @param string $referer='' array with param/get-vars of the refering page
	 */
	function edit($content = null,$action = '',$action_id=0,$type='',$referer='')
	{
		$tabs = 'description|links|delegation|project|customfields|history';

		if (is_array($content))
		{
			//echo "uiinfolog::edit: content="; _debug_array($content);
			$info_id   = $content['info_id'];
			$action    = $content['action'];    unset($content['action']);
			$action_id = $content['action_id']; unset($content['action_id']);
			$referer   = $content['referer'];   unset($content['referer']);
			$no_popup  = $content['no_popup'];  unset($content['no_popup']);
			$caller    = $content['caller'];    unset($content['caller']);
			
			// convert custom from to 0 or 1, it's unset if not checked, which starts the detection
			$content['info_custom_from'] = (int)$content['info_custom_from'];

			list($button) = @each($content['button']);
			unset($content['button']);
			if ($button)
			{
				//echo "<p>uiinfolog::edit(info_id=$info_id) '$button' button pressed, content="; _debug_array($content);
				if (($button == 'save' || $button == 'apply') && isset($content['info_subject']) && empty($content['info_subject']))
				{
					$this->tmpl->set_validation_error('info_subject',lang('Field must not be empty !!!'));
					$button = '';	// stop save or apply
				}
				if (($button == 'save' || $button == 'apply') && $info_id)
				{
					if (!($edit_acl = $this->bo->check_access($info_id,EGW_ACL_EDIT)))
					{
						$old = $this->bo->read($info_id);
						$status_only = $this->bo->is_responsible($old);
						$undelete = $this->bo->check_access($old,EGW_ACL_UNDELETE);
					}
				}
				if (($button == 'save' || $button == 'apply') && (!$info_id || $edit_acl || $status_only || $undelete))
				{
					if ($content['info_contact'])
					{
						$old_link_id = (int)$content['info_link_id'];
						list($app,$id) = explode(':',$content['info_contact']);
						$content['info_link_id'] = (int)($info_link_id = $this->link->link('infolog',$content['link_to']['to_id'],$app,$id));
						if ($old_link_id && $old_link_id != $content['info_link_id']) $this->link->unlink($old_link_id);
					}
					if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
					{
						$content['info_link_id'] = 0;	// as field has to be int
					}
					$active_tab = $content[$tabs];
					if (!($info_id = $this->bo->write($content)))
					{
						$content['msg'] = $info_id !== 0 || !$content['info_id'] ? lang('Error: saving the entry') :
							lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars($GLOBALS['egw']->link('/index.php',array(
									'menuaction' => 'infolog.uiinfolog.edit',
									'info_id'    => $content['info_id'],
									'no_popup'   => $no_popup,
									'referer'    => $referer,
								))).'">','</a>');
						$button = '';	// not exiting edit
						$info_id = $content['info_id'];
					}
					else
					{
						$content['msg'] = lang('InfoLog entry saved');
						if ($referer !== false)
						{
							$content['js'] = "opener.location.href='".($link=$GLOBALS['egw']->link($referer,array('msg' => $content['msg'])))."';";
						}
					}
					$content[$tabs] = $active_tab;
					if ((int) $content['pm_id'] != (int) $content['old_pm_id'])
					{
						//echo "<p>pm_id changed: $content[old_pm_id] -> $content[pm_id]</p>\n";
						// update links accordingly, if selected project changed
						if ($content['pm_id'])
						{
							//echo "<p>this->link->link('infolog',{$content['link_to']['to_id']},'projectmanager',{$content['pm_id']});</p>";
							$this->link->link('infolog',$content['link_to']['to_id'],'projectmanager',$content['pm_id']);
							// making the project the selected link, if no other link selected
							if (!$info_link_id || $info_link_id == 'projectmanager:'.$content['old_pm_id'])
							{
								$info_link_id = 'projectmanager:'.$content['pm_id'];
							}
						}
						if ($content['old_pm_id'])
						{
							//echo "<p>this->link->unlink2(0,infolog,{$content['link_to']['to_id']},0,'projectmanager',{$content['old_pm_id']});</p>\n";
							$this->link->unlink2(0,infolog,$content['link_to']['to_id'],0,'projectmanager',$content['old_pm_id']);
							$content['old_pm_id'] = $content['pm_id'];
						}
					}
					// writing links for a new entry
					if ($info_id && is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))	
					{
						//echo "<p>writing links for new entry $info_id</p>\n"; _debug_array($content['link_to']['to_id']);
						$this->link->link('infolog',$info_id,$content['link_to']['to_id']);
						$content['link_to']['to_id'] = $info_id;
					}
					if ($info_link_id && strpos($info_link_id,':') !== false)	// updating info_link_id if necessary
					{
						list($app,$id) = explode(':',$info_link_id);
						$link = $this->link->get_link('infolog',$info_id,$app,$id);
						if ((int) $content['info_link_id'] != (int) $link['link_id'])
						{
							$content['info_link_id'] = $link['link_id'];

							$to_write = array(
								'info_id'      => $content['info_id'],
								'info_link_id' => $content['info_link_id'],
								'info_from'    => $content['info_from'],
								'info_owner'   => $content['info_owner'],
								'info_custom_from' => $content['info_custom_from'],
							);
							//echo "<p>updating info_link_id: ".print_r($to_write,true)."</p>\n";
							$this->bo->write($to_write,False);
							// we need eg. the new modification date, for further updates
							$content = array_merge($content,$to_write);
						}
					}
				}
				elseif ($button == 'delete' && $info_id > 0)
				{
					if (!$referer && $action) $referer = array(
						'menuaction' => 'infolog.uiinfolog.index',
						'action' => $action,
						'action_id' => $action_id
					);
					if (!($content['msg'] = $this->delete($info_id,$referer,'edit'))) return;	// checks ACL first

					$content['js'] = "opener.location.href='".$GLOBALS['egw']->link($referer,array('msg' => $content['msg']))."';";
				}
				// called again after delete confirmation dialog
				elseif ($button == 'deleted'  && $content['msg'])
				{
					$content['js'] = "opener.location.href='".$GLOBALS['egw']->link($referer,array('msg' => $content['msg']))."';";
				}
				if ($button == 'save' || $button == 'cancel' || $button == 'delete' || $button == 'deleted')
				{
					if ($no_popup)
					{
						$GLOBALS['egw']->redirect_link($referer,array('msg' => $content['msg']));
					}
					$content['js'] .= 'window.close();';
					echo '<html><body onload="'.$content['js'].'"></body></html>';
					$GLOBALS['egw']->common->egw_exit();
				}
				if ($content['js']) $content['js'] = '<script>'.$content['js'].'</script>';
			}
			// on a type-change, set the status to the default status of that type, if the actual status is not supported by the new type
			if (!in_array($content['info_status'],$this->bo->status[$content['info_type']]))
			{
				$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
				if ($content['info_status'] != 'done') $content['info_datecompleted'] = '';
			}
		}
		else
		{
			//echo "<p>uiinfolog::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";
			$action    = $action    ? $action    : get_var('action',   array('POST','GET'));
			$action_id = $action_id ? $action_id : get_var('action_id',array('POST','GET'));
			$info_id   = $content   ? $content   : get_var('info_id',  array('POST','GET'));
			$type      = $type      ? $type      : get_var('type',     array('POST','GET'));
			$ref=$referer   = $referer !== '' ? $referer : ($_GET['referer'] ? $_GET['referer'] :
				$GLOBALS['egw']->common->get_referer('/index.php?menuaction=infolog.uiinfolog.index'));
			$referer = preg_replace('/([&?]{1})msg=[^&]+&?/','\\1',$referer);	// remove previou/old msg from referer
			$no_popup  = $_GET['no_popup'];
			//echo "<p>uiinfolog::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";

			$content = $this->bo->read( $info_id || $action != 'sp' ? $info_id : $action_id );
			
			if (is_numeric($_REQUEST['cat_id']))
			{
				$content['info_cat'] = (int) $_REQUEST['cat_id'];
			}
			switch($this->prefs['set_start'])
			{
				case 'date': default: $set_startdate = mktime(0,0,0,date('m',$this->bo->user_time_now),date('d',$this->bo->user_time_now),date('Y',$this->bo->user_time_now)); break;
				case 'datetime':      $set_startdate = $this->bo->user_time_now; break;
				case 'empty':         $set_startdate = 0; break;
			}
			if ((int)$content['info_link_id'] > 0 && !$this->link->get_link($content['info_link_id']))
			{
				$content['info_link_id'] = 0;	// link has been deleted
				if (!$content['info_custom_link']) $content['info_from'] = '';
			}
			if (!$info_id && $action_id && $action == 'sp')    // new SubProject
			{
				if (!$this->bo->check_access($action_id,EGW_ACL_ADD))
				{
					return $referer ? $this->tmpl->location($referer) : $this->index(0,$action,$action_id);
				}
				$parent = $this->bo->so->data;
				$content['info_id'] = $info_id = 0;
				$content['info_owner'] = $this->user;
				$content['info_id_parent'] = $parent['info_id'];
				/*
				if ($parent['info_type']=='task' && $parent['info_status']=='offer')
				{
					$content['info_type'] = 'confirm';   // confirmation to parent
					$content['info_responsible'] = $parent['info_owner'];
				}
				*/
				$content['info_type'] = $parent['info_type'];
				$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
				$content['info_percent'] = $content['info_status'] == 'done' ? '100%' : '0%';
				$content['info_datecompleted'] =$content['info_status'] == 'done' ? $this->bo->user_time_now : 0;
				$content['info_confirm'] = 'not';
				$content['info_subject']=lang($this->messages['re']).' '.$parent['info_subject'];
				$content['info_des'] = '';
				$content['info_lastmodified'] = '';
				if ($content['info_startdate'] < $this->bo->user_time_now)	// parent-startdate is in the past => today
				{
					$content['info_startdate'] = $set_startdate;
				}
				if ($content['info_enddate'] < $this->bo->user_time_now)		// parent-enddate is in the past => empty
				{
					$content['info_enddate'] = '';
				}
			}
			else
			{
				if ($info_id && !$this->bo->check_access($info_id,EGW_ACL_EDIT) && 
					!($undelete = $this->bo->check_access($info_id,EGW_ACL_UNDELETE)) &&
					!$this->bo->is_responsible($content))
				{
					if ($no_popup)
					{
						$GLOBALS['egw']->common->egw_header();
						parse_navbar();
						echo '<p class="redItalic" align="center">'.lang('Permission denied')."</p>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					$js = "alert('".lang('Permission denied')."'); window.close();";
					echo '<html><body onload="'.$js.'"></body></html>';
					$GLOBALS['egw']->common->egw_exit();
				}
			}
			$content['links'] = $content['link_to'] = array(
				'to_id' => $info_id,
				'to_app' => 'infolog',
			);
			switch ($action)
			{
				case 'sp':
					$links = $this->link->get_links('infolog',$parent['info_id'],'!'.$this->link->vfs_appname);
					foreach($links as $link)
					{
						$link_id = $this->link->link('infolog',$content['link_to']['to_id'],$link['app'],$link['id'],$link['remark']);

						if ($parent['info_link_id'] == $link['link_id'])
						{
							$content['info_link_id'] = $link_id;
						}
					}
					break;

				case 'projectmanager':
					$pm_links = array($action_id);
				case 'addressbook':
				case 'projects':
				case 'calendar':
				default:	// to allow other apps to participate
					$content['info_contact'] = $action.':'.$action_id;
					$content['blur_title']   = $this->link->title($action,$action_id);

				case '':
					if ($info_id)
					{
						if (!isset($pm_links))
						{
							$pm_links = $this->link->get_links('infolog',$info_id,'projectmanager');
						}
						break;	// normal edit
					}
				case 'new':		// new entry
					$content['info_startdate'] = (int) $_GET['startdate'] ? (int) $_GET['startdate'] : $set_startdate;
					$content['info_priority'] = 1; // normal
					$content['info_owner'] = $this->user;
					if ($type != '')
					{
						$content['info_type'] = $type;
					}
					$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
					$content['info_percent'] = $content['info_status'] == 'done' ? '100%' : '0%';
					break;
			}
			if (!isset($this->bo->enums['type'][$content['info_type']]))
			{
				$content['info_type'] = 'note';
			}
		}
		// group owners
		$types = $this->bo->enums['type'];
		if ($this->bo->group_owners)
		{
			// remove types owned by groups the user has no edit grant (current type is made readonly)
			foreach($this->bo->group_owners as $type => $group)
			{
				if (!($this->bo->grants[$group] & EGW_ACL_EDIT))
				{
					if ($type == $content['info_type'])
					{
						//echo "<p>setting type to r/o as user has no edit rights from group #$group</p>\n";
						$readonlys['info_type'] = true;
					}
					else
					{
						unset($types[$type]);
					}
				}
			}
			// set group as owner if type has a group-owner set
			if (isset($this->bo->group_owners[$content['info_type']]))
			{
				$content['info_owner'] = $this->bo->group_owners[$content['info_type']];
				// Dont allow to change the type, if user has no delete rights from the group-owner
				if ($info_id && !($this->bo->grants[$content['info_owner']] & EGW_ACL_DELETE))
				{
					//echo "<p>setting type to r/o as user has no delete rights from group #$group</p>\n";
					$readonlys['info_type'] = true;
				}
			}
			elseif($GLOBALS['egw']->accounts->get_type($content['info_owner']) == 'g')
			{
				$content['info_owner'] = $this->user;
			}
		}
		$preserv = $content;
		// for implizit edit of responsible user make all fields readonly, but status and percent
		if ($info_id && !$this->bo->check_access($info_id,EGW_ACL_EDIT) && $this->bo->is_responsible($content) && !$undelete)
		{
			$content['status_only'] = !in_array('link_to',$this->bo->responsible_edit);
			foreach(array_diff(array_merge(array_keys($content),array('pm_id')),$this->bo->responsible_edit) as $name)
			{
				$readonlys[$name] = true;
			}
			unset($readonlys[$tabs]);
			// need to set all customfields extra, as they are not set if empty
			foreach($this->bo->customfields as $name => $value)
			{
				$readonlys['#'.$name] = true;
			}
		}
		// ToDo: use the old status before the delete
		if ($undelete) $content['info_status'] = $this->bo->status['defaults'][$content['info_type']];

		$content['hide_from_css'] = $content['info_custom_from'] ? '' : 'hideFrom';

		if (!($readonlys['button[delete]'] = !$info_id || !$this->bo->check_access($info_id,EGW_ACL_DELETE)))
		{
			$content['info_anz_subs'] = $this->bo->anzSubs($info_id);	// to determine js confirmation of delete or not
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang($this->messages[$info_id ? 'edit' : ($action == 'sp' ? 'add_sub' : 'add')]);

		// use a typ-specific template (infolog.edit.xyz), if one exists, otherwise fall back to the generic one
		if (!$this->tmpl->read('infolog.edit.'.$content['info_type']))
		{
			$this->tmpl->read('infolog.edit');
		}
		if ($this->bo->has_customfields($content['info_type']))
		{
			$content['customfields'] = $content['info_type'];
		}
		else
		{
			$readonlys[$tabs]['customfields'] = true;
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['projectmanager']))
		{
			$readonlys[$tabs]['project'] = true;	// disable the project tab
		}
		$readonlys[$tabs]['delegation'] = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']);

		$content['duration_format'] = $this->duration_format;
		if ($this->prefs['show_id']) $content['info_number'] = $info_id;

		$old_pm_id = is_array($pm_links) ? array_shift($pm_links) : $content['old_pm_id'];
		if (!isset($content['pm_id']) && $old_pm_id) $content['pm_id'] = $old_pm_id;

		if ($info_id && $this->bo->history)
		{
			$content['history'] = array(
				'id'  => $info_id,
				'app' => 'infolog',
				'status-widgets' => array(
					'Ty' => $types,
					//'Li',	// info_link_id
					'Ca' => 'select-cat',
					'Pr' => $this->bo->enums['priority'],
					'Ow' => 'select-account',
					//'Ac',	//	info_access: private||public 
					'St' => $this->bo->status[$content['info_type']]+array('deleted' => 'deleted'),
					'Pe' => 'select-percent',
					'Co' => 'date-time',
					'st' => 'date-time',
					'En' => 'date',
					'Re' => 'select-account',
					// PM fields, ToDo: access control!!!
					'pT' => 'date-duration',
					'uT' => 'date-duration',
//					'pL' => 'projectmanager-pricelist',
					'pr' => 'float',
				),
			);
			$history_stati = array();
			require_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.infolog_tracking.inc.php');
			$tracking = new infolog_tracking($this);
			foreach($tracking->field2history as $field => $history)
			{
				$history_stati[$history] = $tracking->field2label[$field];
			}
			unset($tracking);
		}
		else
		{
			$readonlys[$tabs]['history'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.
			($content['status_only'] ? lang('Edit Status') : lang('Edit'));
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => ($info_id ? 'ManualInfologEdit' : 'ManualInfologAdd'));
		//echo "<p>uiinfolog.edit(info_id='$info_id',action='$action',action_id='$action_id') readonlys="; print_r($readonlys); echo ", content = "; _debug_array($content);
		$this->tmpl->exec('infolog.uiinfolog.edit',$content,array(
			'info_type'     => $types,
			'info_priority' => $this->bo->enums['priority'],
			'info_confirm'  => $this->bo->enums['confirm'],
			'info_status'   => $this->bo->status[$content['info_type']],
			'status'        => $history_stati,
		),$readonlys,$preserv+array(	// preserved values
			'info_id'       => $info_id,
			'action'        => $action,
			'action_id'     => $action_id,
			'referer'       => $referer,
			'no_popup'      => $no_popup,
			'old_pm_id'     => $old_pm_id,
		),$no_popup ? 0 : 2);
	}

	function menuaction($action = 'get_list',$app='infolog')
	{
		return array( 'menuaction' => "$app.ui$app.$action" );
	}

	function icon($cat,$id,$status='')
	{
		if (!$status || !($icon = $this->icons[$cat][$id.'_'.$status]))
		{
			$icon = $this->icons[$cat][$id];
		}
		if ($icon && !is_readable($GLOBALS['egw']->common->get_image_dir() . '/' . $icon))
		{
			$icon = False;
		}
		if (!$status || !($alt = $this->icons[$cat][$id.'_'.$status.'_alt']))
		{
			if (!($alt = $this->icons[$cat][$id.'_alt']))
			{
				$alt = $id;
			}
		}
		return $icon ? $this->html->image('infolog',$icon,lang($alt),'border=0') : lang($alt);
	}

	/**
	 * stripping slashes from an array
	 *
	 * @static 
	 * @param array $arr
	 * @return array
	 */
	function array_stripslashes($arr)
	{
		foreach($arr as $key => $val)
		{
			if (is_array($val))
			{
				$arr[$key] = self::array_stripslashes($var);
			}
			else
			{
				$arr[$key] = stripslashes($val);
			}
		}
		return $arr;
	}

	/**
	 * Infolog's site configuration
	 *
	 */
	function admin( )
	{
		$fields = array(
			'info_cat'      => 'Category',
			'info_from'     => 'Contact',
			'info_addr'     => 'Phone/Email',
			'info_subject'  => 'Subject',
			'info_des'      => 'Description',
			'link_to'       => 'Links',
			'info_priority' => 'Priority',
			'info_location' => 'Location',
			'info_planned_time' => 'Planned time',
			'info_used_time'    => 'Used time',
		);
		if($_POST['save'] || $_POST['apply'])
		{
			if (get_magic_quotes_gpc())
			{
				$_POST = self::array_stripslashes($_POST);
			}
			$this->bo->config->config_data['link_pathes'] = $this->bo->link_pathes = array();
			$this->bo->config->config_data['send_file_ips'] = $this->bo->send_file_ips = array();

			$valid = get_var('valid',Array('POST'));
			$trans = get_var('trans',Array('POST'));
			$ip = get_var('ip',Array('POST'));
			foreach($valid as $key => $val)
			{
				if($val)
				{
					$this->bo->config->config_data['link_pathes'][$val] = $this->bo->link_pathes[$val] = $trans[$key];
					$this->bo->config->config_data['send_file_ips'][$val] = $this->bo->send_file_ips[$val] = $ip[$key];
				}
			}
			$this->bo->responsible_edit = array('info_status','info_percent','info_datecompleted');

			if ($_POST['responsible_edit']) 
			{
				$extra = array_intersect($_POST['responsible_edit'],array_keys($fields));
				$this->bo->config->config_data['responsible_edit'] = $this->bo->responsible_edit = array_merge($this->bo->responsible_edit,$extra);
			}
			$this->bo->config->config_data['implicit_rights'] = $this->bo->implicit_rights = $_POST['implicit_rights'] == 'edit' ? 'edit' : 'read';
			
			$this->bo->config->config_data['history'] = $this->bo->history = $_POST['history'];

			$this->bo->config->save_repository(True);
		}
		if($_POST['cancel'] || $_POST['save'])
		{
			$GLOBALS['egw']->redirect_link('/infolog/index.php');
		}

		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Site configuration');
		$GLOBALS['egw']->common->egw_header();

		$GLOBALS['egw']->template->set_file(array('info_admin_t' => 'admin.tpl'));
		$GLOBALS['egw']->template->set_block('info_admin_t', 'admin_line');
		$GLOBALS['egw']->template->set_block('info_admin_t', 'info_admin');

		$GLOBALS['egw']->template->set_var(Array(
			'lang_responsible_rights' => lang('Rights for the responsible'),
			'lang_implicit_rights' => lang('Which implicit ACL rights should the responsible get?'),
			'implicit_rights' => $this->html->select('implicit_rights',$this->bo->implicit_rights,array(
				'read' => 'read rights (default)',
				'edit' => 'edit rights (full edit rights incl. making someone else responsible!)',
			)),
			'lang_responsible_edit' => lang('Which additional fields should the responsible be allowed to edit without having edit rights?<br />Status, percent and date completed are always allowed.'),
			'responsible_edit' => $this->html->checkbox_multiselect('responsible_edit',$this->bo->responsible_edit,$fields,false,'',11),
			'text' => lang('<b>file-attachments via symlinks</b> instead of uploads and retrieval via file:/path for direct lan-clients'),
			'action_url'  => $this->html->link('/index.php',$this->menuaction('admin')),
			'save_button' => $this->html->submit_button('save','Save'),
			'apply_button' => $this->html->submit_button('apply','Apply'),
			'cancel_button' => $this->html->submit_button('cancel','Cancel'),
			'lang_valid'  => lang('valid path on clientside<br>eg. \\\\Server\\Share or e:\\'),
			'lang_trans'  => lang('path on (web-)serverside<br>eg. /var/samba/Share'),
			'lang_ip'     => lang('reg. expr. for local IP\'s<br>eg. ^192\\.168\\.1\\.'),
			'lang_history'=> lang('History logging'),
			'lang_history2'=> lang('History logging and deleting of items'),
			'history'     => $this->html->select('history',$this->bo->history,array(
				'' => lang('No'),
				'history' => lang('Yes, with purging of deleted items possible'),
				'history_admin_delete' => lang('Yes, only admins can purge deleted items'),
				'history_no_delete' => lang('Yes, noone can purge deleted items'),
			))
		));

		if (!is_array($this->bo->send_file_ips))
		{
			$this->bo->send_file_ips = $this->bo->link_pathes = array();
		}
		$i = 0; @reset($this->bo->link_pathes);
		do {
			list($valid,$trans) = @each($this->bo->link_pathes);
			$GLOBALS['egw']->template->set_var(array(
				'tr_color'  => $i & 1 ? 'row_off' : 'row_on',
				'num'       => $i+1,
				'val_valid' => $this->html->input("valid[$i]",$valid),
				'val_trans' => $this->html->input("trans[$i]",$trans),
				'val_ip'    => $this->html->input("ip[$i]",$this->bo->send_file_ips[$valid])
			));
			$GLOBALS['egw']->template->parse('admin_lines','admin_line',True);
			++$i;
		} while ($valid);

		if (!$this->tmpl->xslt)
		{
			echo parse_navbar();
			$GLOBALS['egw']->template->pfp('phpgw_body','info_admin');
		}
		else
		{
			$GLOBALS['egw']->template->fp('phpgw_body','info_admin');
		}
	}
	
	/**
	 * imports a mail as infolog
	 * two possible calls: 
	 * 1. with function args set. (we come from send mail)
	 * 2. with $_GET['uid] = someuid (we come from display mail)
	 * 
	 * @author Cornelius Weiss <nelius@cwtech.de>
	 * @param string $_to_emailAddress
	 * @param string $_subject
	 * @param string $_body
	 * @param array $_attachments
	 * @param string $_date
	 */
	function import_mail($_to_emailAddress=false,$_subject=false,$_body=false,$_attachments=false,$_date=false)
	{
		$uid = $_GET['uid'];
		$mailbox = $_GET['mailbox'];
		
		if (!empty($_to_emailAddress))
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'infolog';
			$GLOBALS['egw']->translation->add_app($GLOBALS['egw_info']['flags']['currentapp']);
			echo '<script>window.resizeTo(750,550);</script>';

			if (is_array($_attachments))
			{
				foreach ($_attachments as $attachment)
				{
					$attachments[] = array(
						'name' => $attachment['name'],
						'mimeType' => $attachment['type'],
						'tmp_name' => $attachment['file'],
						'size' => $attachment['size'],
					);
				}
			}
			
			$body = strip_tags($_body);
			$this->edit($this->bo->import_mail(
				implode(',',$_to_emailAddress),$_subject,$body,$attachments,''
			));
			exit;
		}
		elseif ($uid && $mailbox)
		{
			$bofelamimail =& CreateObject('felamimail.bofelamimail',$GLOBALS['egw']->translation->charset());
			$bopreferences =& CreateObject('felamimail.bopreferences');
			$bofelamimail->openConnection();
			$bofelamimail->reopen($mailbox);
			
			$headers = $bofelamimail->getMessageHeader($uid);
			$bodyParts = $bofelamimail->getMessageBody($uid,'text/plain');
			$attachments = $bofelamimail->getMessageAttachments($uid);
			
			if ($mailbox == 'Sent') $mailaddress = $bofelamimail->decode_header($headers['TO']);
			elseif (isset($headers['FROM'])) $mailaddress = $bofelamimail->decode_header($headers['FROM']);
			elseif (isset($headers['SENDER'])) $mailaddress = $bofelamimail->decode_header($headers['SENDER']);
			
			$subject = $bofelamimail->decode_header($headers['SUBJECT']);
			
			for($i=0; $i<count($bodyParts); $i++)
			{
				// add line breaks to $bodyParts
				$newBody  = $GLOBALS['egw']->translation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
				$newBody = strip_tags($newBody);
				$newBody  = explode("\n",$newBody);
				// create it new, with good line breaks
				reset($newBody);
				while(list($key,$value) = @each($newBody))
				{
					$value .= "\n";
					$bodyAppend = $bofelamimail->wordwrap($value,75,"\n");
					$message .= $bodyAppend;
				}
			}

			if (is_array($attachments))
			{
				foreach ($attachments as $num => $attachment)
				{
					$attachments[$num] = array_merge($attachments[$num],$bofelamimail->getAttachment($uid, $attachment['partID']));
					if (isset($attachments[$num]['charset'])) {
						$GLOBALS['egw']->translation->convert($attachments[$num]['attachment'],$attachments[$num]['charset']);
					}
					$attachments[$num]['type'] = $attachments[$num]['mimeType'];					
					$attachments[$num]['tmp_name'] = tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
					$tmpfile = fopen($attachments[$num]['tmp_name'],'w');
					fwrite($tmpfile,$attachments[$num]['attachment']);
					fclose($tmpfile);
					unset($attachments[$num]['attachment']);
				}
			}
			
			return $this->edit($this->bo->import_mail(
				$mailaddress,
				$subject,
				$message,
				$attachments,
				strtotime($headers['DATE'])
			));
		}
		$GLOBALS['egw']->common->egw_header();
		echo "<script> window.close(); alert('Error: no mail (Mailbox / UID) given!');</script>";
		$GLOBALS['egw']->common->egw_exit();
		exit;
	}
	
	/**
	 * shows infolog in other applications
	 *
	 * @param $args['location'] location des hooks: {addressbook|projects|calendar}_view|infolog
	 * @param $args['view']     menuaction to view, if location == 'infolog'
	 * @param $args['app']      app-name, if location == 'infolog'
	 * @param $args['view_id']  name of the id-var for location == 'infolog'
	 * @param $args[$args['view_id']] id of the entry
	 * this function can be called for any app, which should include infolog: \
	 * 	$GLOBALS['egw']->hooks->process(array( \
	 * 		 * 'location' => 'infolog', \
	 * 		 * 'app'      => <your app>, \
	 * 		 * 'view_id'  => <id name>, \
	 * 		 * <id name>  => <id value>, \
	 * 		 * 'view'     => <menuaction to view an entry in your app> \
	 * 	));
	 */
	function hook_view($args)
	{
		switch ($args['location'])
		{
			case 'addressbook_view':
				$app     = 'addressbook';
				$view_id = 'ab_id';
				$view_id2 = 'contact_id';
				$view    = 'addressbook.uicontacts.view';
				break;
			case 'projects_view':
				$app     = 'projects';
				$view_id = 'project_id';
				$view    = 'projects.uiprojects.view';
				break;
			default:
				$app     = $args['app'];
				$view_id = $args['view_id'];
				$view    = $args['view'];
		}
		if (!is_array($args) || $args['debug'])
		{
			echo "<p>uiinfolog::hook_view("; print_r($args); echo "): app='$app', $view_id='$args[$view_id]', view='$view'</p>\n";
		}
		if (!isset($app) || !isset($args[$view_id]))
		{
			return False;
		}
		$this->called_by = $app;	// for read/save_sessiondata, to have different sessions for the hooks

		$GLOBALS['egw']->translation->add_app('infolog');

		$GLOBALS['egw_info']['etemplate']['hooked'] = True;
		$this->index(0,$app,$args[$view_id],array(
			'menuaction' => $view,
			isset($view_id2) ? $view_id2 : $view_id => $args[$view_id]
		),True);
		unset($GLOBALS['egw_info']['etemplate']['hooked']);
	} 
}
