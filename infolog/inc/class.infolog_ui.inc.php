<?php
/**
 * InfoLog - User interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/**
 * This class is the UI-layer (user interface) of InfoLog
 */
class infolog_ui
{
	var $public_functions = array(
		'index'       => True,
		'edit'        => True,
		'delete'      => True,
		'close'       => True,
		'admin'       => True,
		'hook_view'   => True,
		'writeLangFile' => True,
		'mail_import' => True
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
	 * @var infolog_bo
	 */
	var $bo;
	/**
	 * instance of the etemplate class
	 *
	 * @var Etemplate
	 */
	var $tmpl;
	/**
	 * allowed units and hours per day, can be overwritten by the projectmanager configuration, default all units, 8h
	 *
	 * @var string
	 */
	var $duration_format = '';

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
			'close'     => 'done.gif',      'close_alt'     => 'Close' ,
			'close_all' => 'done_all.gif',  'close_all_alt' => 'Close' ),
		'status' => array(
			'billed'    => 'billed.gif',    'billed_alt'    => 'billed',
			'done'      => 'done.gif',      'done_alt'      => 'done',
			'will-call' => 'will-call.gif', 'will-call_alt' => 'will-call',
			'call'      => 'call.gif',      'call_alt'      => 'call',
			'ongoing'   => 'ongoing.gif',   'ongoing_alt'   => 'ongoing',
			'offer'     => 'offer.gif',     'offer_alt'     => 'offer' )
	);
	var $filters;
	var $messages = array(
		'edit'    => 'InfoLog - Edit',
		'add'     => 'InfoLog - New',
		'add_sub' => 'InfoLog - New Subproject',
		'sp'      => '- Subprojects from',
	);

	/**
	 * Constructor
	 *
	 * @return infolog_ui
	 */
	function __construct(Etemplate $etemplate = null)
	{
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'infolog') Api\Translation::add_app('infolog');

		// Make sure Global category is infolog - on first load, it may not be
		if($GLOBALS['egw_info']['flags']['currentapp'] == 'infolog' && !$GLOBALS['egw']->categories->app_name)
		{
			$GLOBALS['egw']->categories = new Api\Categories();
		}

		$this->bo = new infolog_bo();

		if($etemplate === null)
		{
			$etemplate = new Etemplate();
		}
		$this->tmpl = $etemplate;

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['infolog'];

		// read the duration format from project-manager
		if ($GLOBALS['egw_info']['apps']['projectmanager'])
		{
			$pm_config = Api\Config::read('projectmanager');
			$this->duration_format = str_replace(',','',implode('', (array)$pm_config['duration_units']));
			//error_log(__METHOD__."() ".__LINE__." duration_format=$this->duration_format, duration_unit=".array2string($pm_config['duration_units']));
			$this->hours_per_workday = $pm_config['hours_per_workday'];
			unset($pm_config);
		}
		$this->filters =& $this->bo->filters;
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
		$GLOBALS['infolog_ui'] =& $this;	// make ourself availible for ExecMethod of get_rows function
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
		$done = $info['info_status'] == 'done' || $info['info_status'] == 'billed' || $info['info_status'] == 'cancelled'; //cancelled is regarded as a completed status as well in bo
		// regard an infolog as done/billed/cancelled if its percentage is 100% when there is to status like the above for that type
		if (!$done && !isset($this->bo->status[$info['info_type']]['done']) && !isset($this->bo->status[$info['info_type']]['billed']) &&
			!isset($this->bo->status[$info['info_type']]['cancelled']) && (int)$info['info_percent']==100) $done = true ;
		$info['sub_class'] = $this->bo->enums['priority'][$info['info_priority']] . ($done ? '_done' : '');
		if (!$done && $info['info_enddate'] < $this->bo->user_time_now)
		{
			$info['end_class'] = 'infolog_overdue';
		}
		if (!isset($info['info_anz_subs'])) $info['info_anz_subs'] = $this->bo->anzSubs($id);
		$this->bo->link_id2from($info,$action,$action_id);	// unset from for $action:$action_id
		$info['info_percent'] = (int) $info['info_percent'].'%';
		$editrights = $this->bo->check_access($info,Acl::EDIT);
		$isresposible = $this->bo->is_responsible($info);
		if ((!($editrights || // edit rights or more then standard responsible rights
			$isresposible && array_diff($this->bo->responsible_edit,array('info_status','info_percent','info_datecompleted')))))
		{
			$info['class'] .= 'rowNoEdit ';
		}
		if ($info['status'] == 'deleted' && !$this->bo->check_access($info, infolog_bo::ACL_UNDELETE))
		{
			$info['class'] .= 'rowNoUndelete ';
		}
		if (($done || (!($editrights || $isresposible))))
		{
			$info['class'] .= 'rowNoClose ';
		}
		// this one is supressed, when you are not allowed to edit, or not responsible, or the entry is closed
		// and has no children. If you want that this one is shown if there are children regardless of the status of the current or its childs,
		// then modify ($done) to ($done && !$info['info_anz_subs'])
		if ($done || !$info['info_anz_subs'] || (!($editrights || $isresposible)))
		{
			$info['class'] .= 'rowNoCloseAll ';
		}
		if (!$this->bo->check_access($info,Acl::DELETE))
		{
			$info['class'] .= 'rowNoDelete ';
		}
		if (!$this->bo->check_access($info,Acl::ADD))
		{
			$info['class'] .= 'rowNoSubs ';
		}
		if ($info['info_id_parent']) $info['class'] .= 'infolog_rowHasParent ';
		if ($info['info_anz_subs'] > 0) $info['class'] .= 'infolog_rowHasSubs ';

		$info['row_mod'] = $info['info_datemodified'];

		if (!$show_links) $show_links = $this->prefs['show_links'];
		if (($show_links != 'none' && $show_links != 'no_describtion' ||
			 $this->prefs['show_times'] || isset($GLOBALS['egw_info']['user']['apps']['timesheet'])) &&
			(isset($info['links']) || ($info['links'] = Link::get_links('infolog',$info['info_id'],'','link_lastmod DESC',true,true))))
		{
			$timesheets = array();
			foreach ($info['links'] as $link)
			{
				// incl. link modification time into row_mod (link's lastmod is always in server-time!)
				$link_mod = Api\DateTime::server2user($link['lastmod']);
				if ($info['row_mod'] < $link_mod) $info['row_mod'] = $link_mod;

				if ($link['deleted']) continue;	// skip deleted links, but incl. them in row_mod!

				if ($show_links != 'none' && $show_links != 'no_describtion' &&
					$link['link_id'] != $info['info_link_id'] &&
				    ($link['app'] != $action || $link['id'] != $action_id) &&
					($show_links == 'all' || ($show_links == 'links') === ($link['app'] != Link::VFS_APPNAME)))
				{
					$info['filelinks'][] = $link;
				}
				if (!$info['pm_id'] && $link['app'] == 'projectmanager')
				{
					$info['pm_id'] = $link['id'];
				}
				if ($link['app'] == 'timesheet') $timesheets[] = $link['id'];
			}
			if ($this->prefs['show_times'] && isset($GLOBALS['egw_info']['user']['apps']['timesheet']) && $timesheets)
			{
				$sum = ExecMethod('timesheet.timesheet_bo.sum',$timesheets);
				$info['info_sum_timesheets'] = $sum['duration'];
				// incl. max timesheet modification in row_mod
				if ($info['row_mod'] < $sum['max_modified']) $info['row_mod'] = $sum['max_modified'];
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
		//error_log(__METHOD__."() returning ".array2string($info));
		return $info;
	}

	/**
	 * Check if no filter is active
	 *
	 * @param array $query
	 * @return string name of 1. filter found or null
	 */
	protected static function filter(array $query)
	{
		$filter = $query['filter'] ? 'filter' : ($query['cat_id'] ? 'cat_id' : null);
		foreach((array)$query['col_filter'] as $name => $value)
		{
			if ((string)$value !== '')
			{
				$filter = $name;
				break;
			}
		}
		//error_log(__METHOD__."(col_filter=".array2string($query['col_filter']).") returning ".array2string($filter));
		return $filter;
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
		//error_log(__METHOD__."() query[csv_export]=".array2string($query['csv_export']).", query[filter]=".array2string($query['filter']).", query[col_filter]=".array2string(array_diff($query['col_filter'],array('',0))).' '.function_backtrace());
		if (!$query['csv_export'])
		{
			unset($query['no_actions']);
			if (!$query['col_filter']['parent_id'] && !$query['search'] &&
				($this->prefs['listNoSubs'] == '1' || $this->prefs['listNoSubs'] === 'filter' && !self::filter($query)))
			{
				$parent_id = 0;
			}
			else
			{
				$parent_id = $query['col_filter']['parent_id'];
			}
			//error_log(__METHOD__."() prefs[listNoSubs]=".array2string($this->prefs['listNoSubs'])." --> parent_id=$parent_id");
			unset($query['col_filter']['parent_id']);
			if(!$query['action'])
			{
				Api\Cache::setSession('infolog', $query['session_for'].'session_data',
					array_diff_key ($query, array_flip(array('rows','actions','action_links','placeholder_actions'))));
			}
			$query['actions'] = $this->get_actions($query);
			$query['row_id'] = 'info_id';
			$query['row_modified'] = 'row_mod';
			$query['parent_id'] = 'info_id_parent';
			$query['is_parent'] = 'info_anz_subs';
			$query['action_var'] = 'multi_action';	// as 'action' is already used in infolog
		}
		// nextmatch opened an infolog containing children --> do not filter them, always show all children
		elseif($query['csv_export'] === 'children')
		{
			$query['filter'] = $query['search'] = $query['cat_id'] = '';
			$query['col_filter'] = array('info_id_parent' => $query['col_filter']['info_id_parent']);
		}

		$GLOBALS['egw']->session->commit_session();
		$orginal_colfilter = $query['col_filter'];
		if (isset($parent_id)) $query['col_filter']['info_id_parent'] = (string)$parent_id;

		//echo "<p>infolog_ui.get_rows(start=$query[start],search='$query[search]',filter='$query[filter]',cat_id=$query[cat_id],action='$query[action]/$query[action_id]',col_filter=".print_r($query['col_filter'],True).",sort=$query[sort],order=$query[order])</p>\n";
		if (!isset($query['start'])) $query['start'] = 0;

		// handle action and linked filter (show only entries linked to a certain other entry)
		$link_filters = array();
		$links = array();
		if ($query['col_filter']['linked'])
		{
			$link_filters['linked'] = $query['col_filter']['linked'];
			$links['linked'] = array();
			unset($query['col_filter']['linked']);
		}

		// Querying for a particular ID.  If linked is a list of IDs, reset the linked or we won't find the ID we want.
		if (!empty($query['col_filter']['info_id']) && !empty($link_filters['linked']) &&
			(!is_array($link_filters['linked']) || empty($link_filters['linked']['app'])))
		{
			unset($links['linked']);
			unset($link_filters['linked']);
		}

		if($query['action'] && in_array($query['action'], array_keys($GLOBALS['egw_info']['apps'])) && $query['action_id'])
		{
			$link_filters['action'] = array('app'=>$query['action'], 'id' => $query['action_id']);
			$links['action'] = array();
		}

		// Process links
		$linked = $this->link_filters($links, $link_filters, $query, $rows);
		if($linked === 0)
		{
			// Link filter but no results, early exit
			return 0;
		}

		// check if we have a custom, type-specific template
		$old_template = $query['template'];

		// Reset custom, type-specific template if type was cleared (without changing it for home)
		if(!$query['template'] || stripos($query['template'], 'infolog.index.rows') === 0)
		{
			$query['template'] = 'infolog.index.rows';
		}
		unset($query['custom_fields']);
		if ($query['col_filter']['info_type'])
		{
			$tpl = new Etemplate;
			if ($tpl->read('infolog.index.rows.'.$query['col_filter']['info_type']))
			{
				$query['template'] = $tpl->name;
				$query['custom_fields'] = true;	// read the custom fields too
			}
			// If status is not valid for selected type, clear status filter
			if($query['col_filter']['info_status'] && $query['col_filter']['info_status'] != 'deleted' &&
				!in_array($query['col_filter']['info_status'], array_keys($this->bo->status[$query['col_filter']['info_type']])))
			{
				$query['col_filter']['info_status'] = '';
				$clear_status_filter = true;
			}
		}
		// Framework\Template change forces the UI to do a full update first, no point in getting rows right now
		if($old_template && $old_template != $query['template']) return 0;

		// do we need to read the custom fields, depends on the column is enabled and customfields exist, prefs are filter specific
		// so we have to check that as well
		$details = $query['filter2'] == 'all';
		$columnselection_pref = 'nextmatch-'.($query['action'] ? 'infolog.'.$query['action'] : ($tpl && $tpl->name == $query['template'] ? $query['template'] : 'infolog.index.rows'))
			.($details ? '-details' : '');
		//error_log(__METHOD__."(start=$query[start], num_rows=$query[num_rows]) query[col_filter][info_type]={$query['col_filter']['info_type']} --> query[template]=$query[template], columselection_pref=$columnselection_pref");

		$columselection = $this->prefs[$columnselection_pref];

		if (!$query['selectcols'] && $columselection)
		{
			$columselection = is_array($columselection) ? $columselection : explode(',',$columselection);
		}
		else
		{
			$columselection = $query['selectcols'] ? (is_array($query['selectcols']) ? $query['selectcols'] : explode(',',$query['selectcols'])) : array();
		}
		// do we need to query the cf's
		$query['custom_fields'] = $this->bo->customfields && (!$columselection || in_array('customfields',$columselection));

		$query['limit_modified_n_month'] = $this->bo->limit_modified_n_month;
		$infos = $this->bo->search($query);
		// if limit modified optimization has been used, blur the wrong/not exact total
		if (!empty($query['limit_modified_n_month']))
		{
			Api\Json\Response::get()->call('app.infolog.blurCount', $this->bo->total === infolog_bo::LIMIT_MODIFIED_TOTAL);
		}
		$query['col_filter'] = $orginal_colfilter;
		if (!is_array($infos))
		{
			$infos = array( );
		}
		// add a '-details' to the name of the columnselection pref
		if ($details)
		{
			$query['default_cols'] = '!cat_id,info_used_time_info_planned_time,info_used_time_info_planned_time_info_replanned_time,info_id';
		}
		else
		{
			$query['default_cols'] = '!cat_id,info_datemodified,info_used_time_info_planned_time,info_used_time_info_planned_time_info_replanned_time,info_id';
		}
		// set old show_times pref, that get_info calculates the cumulated time of the timesheets (we only check used&planned to work for both time cols)
		$this->prefs['show_times'] = strpos($this->prefs[$query['columnselection_pref']],'info_used_time_info_planned_time') !== false;

		// query all links and sub counts in one go
		if ($infos && (!$query['csv_export'] || !is_array($query['csv_export'])))
		{
			$links = Link::get_links_multiple('infolog',array_keys($infos),true,'','link_lastmod DESC',true);	// true=incl. deleted
			$anzSubs = $this->bo->anzSubs(array_keys($infos));
		}
		$rows = array();

		// Don't add parent in if info_id_parent (expanding to show subs)
		if ($query['action_id'] && $query['csv_export'] !== 'children')
		{
			$parents = $query['action'] == 'sp' && $query['action_id'] ? (array)$query['action_id'] : array();
			if (!empty($parents) && count($parents) == 1 && is_array($query['action_id']))
			{
				$query['action_id'] = array_shift($query['action_id']);	// display single parent as app_header
			}
		}

		$parent_first = !empty($parents) && count($parents) == 1;
		$parent_index = 0;
		// et2 nextmatch listens to total, and only displays that many rows, so add parent in or we'll lose the last row
		if($parent_first || $query['action'] == 'sp' && is_array($query['action_id'])) $query['total']++;

		// Check to see if we need to remove description
		foreach($infos as $id => $info)
		{
			if (!$query['csv_export'] || !is_array($query['csv_export']))
			{
				$info['links'] =& $links[$id];
				$info['info_anz_subs'] = (int)$anzSubs[$id];
				$info = $this->get_info($info,$readonlys,null,null,false,$details);
			}
			// for subs view ('sp') add parent(s) in front of subs once(!)
			if ( $parent_first && ($main = $this->bo->read($query['action_id'])) ||
				$parents && ($parent_index = array_search($info['info_id_parent'], $parents)) !== false &&
				($main = $this->bo->read($info['info_id_parent'])))
			{
				$main = $this->get_info($main, $readonlys);
				$main['class'] .= 'th ';
				// if only certain custom-fields are to be displayed, we need to unset the not displayed ones manually
				// as read() always read them all, while search() only reads the selected ones
				if ($query['custom_fields'])
				{
					foreach($columselection as $col)
					{
						if ($col[0] == '#')
						{
							foreach(array_keys($main) as $n)
							{
								if ($n[0] == '#' && !in_array($n, $columselection)) unset($main[$n]);
							}
							break;
						}
					}
				}
				$parent_first = false;
				if($query['start'] == 0)
				{
					array_splice($rows, $id, 0, array($main));
					unset($parents[$parent_index]);
				}
			}
			$rows[] = $info;
		}
		unset($links);

		if ($query['cat_id']) $rows['no_cat_id'] = true;
		if ($query['no_actions']) $rows['no_actions'] = true;
		$rows['no_timesheet'] = !isset($GLOBALS['egw_info']['user']['apps']['timesheet']);
		if($clear_status_filter)
		{
			$rows['info_status'] = '';
		}

		// switch cf column off, if we have no cf's
		$rows['no_customfields'] = !$query['custom_fields'];

		$rows['no_info_owner_info_responsible'] = (
			$GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin'])
		);

		// if filtered by type, show only the stati of the filtered type
		$rows['sel_options']['info_status'] = $this->bo->get_status($query['col_filter']['info_type']);

		// Update actions for selected type / status / etc.
		$query['actions'] = $this->get_actions($query);

		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'infolog' && !$this->called_by)
		{
			$headers = array();
			if ($query['filter'] != '' && !empty($this->filters[$query['filter']]))
			{
				$headers[] = lang($this->filters[$query['filter']]);
			}
			if ($query['action'] && ($title = $query['action_title'] || is_array($query['action_id']) ?
				$query['action_title'] : Link::title($query['action']=='sp'?'infolog':$query['action'],$query['action_id'])))
			{
				$headers[] = $title;
			}
			if ($query['search'])
			{
				 $headers[] = lang("Search for '%1'", $query['search']);
			}
			$GLOBALS['egw_info']['flags']['app_header'] = implode(': ', $headers);
		}

		if (isset($linked)) $query['col_filter']['linked'] = $linked;  // add linked back to the colfilter

		return $query['total'];
	}

	/**
	 * Deal with link filters and translate them into something we can filter on, ids.
	 *
	 * @param $links
	 * @param $link_filters
	 * @param $query
	 * @param $rows
	 * @return int
	 */
	public function   link_filters(&$links, $link_filters, &$query, &$rows)
	{
		foreach($link_filters as $key => $link)
		{
			if(!is_array($link))
			{
				// Legacy string style
				list($app,$id) = explode(':',$link);
			}
			else
			{
				// Full info
				$app = $link['app'];
				$id = $link['id'];
			}
			if(!is_array($id)) $id = explode(',',$id);
			if (!($linked = Link::get_links_multiple($app,$id,true,'infolog','',$query['col_filter']['info_status'] == 'deleted')))
			{
				$rows = array();	// no infologs linked to selected link --> no rows to return
				return 0;
			}


			foreach($linked as $infos)
			{
				$links[$key] = array_merge($links[$key],$infos);
			}
			$links[$key] = array_unique($links[$key]);
			if($key == 'linked')
			{
				$linked = array('app' => $app, 'id' => $id, 'title' => (count($id) == 1 ? Link::title($app, $id) : lang('multiple')));
			}
		}

		if($query['col_filter']['info_id'])
		{
			$links['info_id'] = $query['col_filter']['info_id'];
		}
		if(count($links))
		{
			$query['col_filter']['info_id'] = count($links) > 1 ? array_intersect(...array_map(static function($ids)
			{
				return (array)$ids;
			}, array_values($links))) : $links[$key ?? 'info_id'];
		}
		return $linked;
	}

	/**
	 * Hook for timesheet to set some extra data and links
	 *
	 * @param array $data
	 * @param int $data[id] info_id
	 * @return array with key => value pairs to set in new timesheet and link_app/link_id arrays
	 */
	function timesheet_set($data)
	{
		$set = array();
		if ((int)$data['id'] && ($info = $this->bo->read($data['id'])))
		{
			if ($info['info_cat']) $set['cat_id'] = $info['info_cat'];
			if ($info['info_used_time'])
			{
				$set['ts_duration'] = $info['info_used_time'];
			}
			if ($info['pl_id'])
			{
				$set['pl_id'] = $info['pl_id'];
			}
			if ($info['info_price'])
			{
				$set['ts_unitprice'] = $info['info_price'];
			}

			foreach(Link::get_links('infolog',$info['info_id'],'','link_lastmod DESC',true) as $link)
			{
				if ($link['app'] != 'timesheet' && $link['app'] != Link::VFS_APPNAME)
				{
					$set['link_app'][] = $link['app'];
					$set['link_id'][]  = $link['id'];
				}
			}

		}
		return $set;
	}

	/**
	 * Hook for calendar to set some extra data and links
	 *
	 * @param array $data event-array preset by calendar plus
	 * @param int $data[entry_id] info_id
	 * @return array with key => value pairs to set in new event and link_app/link_id arrays
	 */
	function calendar_set($data)
	{
		if (!($infolog = $this->bo->read($data['entry_id'])))
		{
			return $data;
		}
		$event = array_merge($data,array(
			'category'	=> $GLOBALS['egw']->categories->check_list(Acl::READ, $infolog['info_cat']),
			'priority'	=> $infolog['info_priority'] + 1,
			'public'	=> $infolog['info_access'] != 'private',
			'title'		=> $infolog['info_subject'],
			'description'	=> $infolog['info_des'],
			'location'	=> $infolog['info_location'],
			'start'		=> $infolog['info_startdate'],
			'end'		=> $infolog['info_enddate'] ? $infolog['info_enddate'] : $infolog['info_datecompleted']
		));
		unset($event['entry_id']);
		if (!$event['end']) $event['end'] = $event['start'] + (int) $GLOBALS['egw_info']['user']['preferences']['calendar']['defaultlength']*60;

		// Match Api\Categories by name
		$event['category'] = $GLOBALS['egw']->categories->name2id(Api\Categories::id2name($infolog['info_cat']));

		// make current user the owner of the new event, not the selected calendar, if current user has rights for it
		$event['owner'] = $user = $GLOBALS['egw_info']['user']['account_id'];

		// add/modify participants according to prefs
		$prefs = explode(',',$this->prefs['calendar_set'] ? $this->prefs['calendar_set'] : 'responsible,contact,user');

		// if no default participants (selected calendars) --> remove all
		if (!in_array('selected',$prefs))
		{
			$event['participants'] = $event['participant_types'] = array();
		}
		// Add responsible as participant
		if (in_array('responsible',$prefs))
		{
			foreach($infolog['info_responsible'] as $responsible)
			{
				$event['participants'][$responsible] = $event['participant_types']['u'][$responsible] =
					calendar_so::combine_status($user==$responsible?'A':'U');
			}
		}
		// Add linked contact as participant
		if (in_array('contact',$prefs) && $infolog['info_link']['app'] == 'addressbook')
		{
			$event['participants'][calendar_so::combine_user('c',$infolog['info_link']['id'])] =
				$event['participant_types']['c'][$infolog['info_link']['id']] = calendar_so::combine_status('U');
		}
		if (in_array('owner',$prefs))
		{
			$event['participants'][$infolog['info_owner']] = $event['participant_types']['u'][$infolog['info_owner']] =
				calendar_so::combine_status('A',1,'CHAIR');
		}
		// Add current user, if set or no other participants, which is not allowed
		if (in_array('user',$prefs))
		{
			$event['participants'][$user] = $event['participant_types']['u'][$user] =
				calendar_so::combine_status('A',1,'CHAIR');
		}

		// Add infolog link to calendar entry
		$event['link_app'][] = $infolog['info_link']['app'];
		$event['link_id'][]  = $infolog['info_link']['id'];

		// Copy infolog's links
		foreach(Link::get_links('infolog',$infolog['info_id'],'','link_lastmod DESC',true) as $link)
		{
			if ($link['app'] != Link::VFS_APPNAME)
			{
				$event['link_app'][] = $link['app'];
				$event['link_id'][]  = $link['id'];
			}
		}
		// Copy same custom fields
		foreach(array_keys(Api\Storage\Customfields::get('calendar')) as $name)
		{
			if ($this->bo->customfields[$name]) $event['#'.$name] = $infolog['#'.$name];
		}
		//error_log(__METHOD__.'('.array2string($data).') infolog='.array2string($infolog).' returning '.array2string($event));
		return $event;
	}

	/**
	 * hook called be calendar to include events or todos in the cal-dayview
	 * Since the hook has no idea about infolog or preferences, we add the user's
	 * current sorting for infolog here so they're in the expected order
	 *
	 * @param int $args[year], $args[month], $args[day] date of the events
	 * @param int $args[owner] owner of the events
	 * @param string $args[location] calendar_include_{events|todos}
	 * @return array of events (array with keys starttime, endtime, title, view, icon, content)
	 */
	public function cal_to_include($args)
	{
		$nm = Api\Cache::getSession('infolog', 'session_data');
		if($nm)
		{
			$args['order'] = $nm['order'];
			$args['sort'] = $nm['sort'];
		}

		return $this->bo->cal_to_include($args);
	}

	/**
	 * Shows the infolog list
	 *
	 * @param array/string $values etemplate content or 'reset_action_view' if called by index.php to reset an action-view
	 * @param string $action if set only entries liked to that $action:$action_id are shown
	 * @param string $action_id if set only entries liked to that $action:$action_id are shown
	 * @param mixed $called_as is how we got called, for a hook eg. the call-params of that page containing the hook
	 * @param boolean $e$extra_app_header * @param boolean $return_html=false
	 * @param string $own_referer='' this is our own referer
	 * @param string $action_title='' app_header for the action, if '' we try the link-title
	 */
	function index($values = null,$action='',$action_id='',$called_as=0,$extra_app_header=False,$return_html=False,$own_referer='',$action_title='')
	{
		unset($extra_app_header);	// not used, but dont want to change signature
		if (is_array($values))
		{
			$called_as = $values['called_as'];
			$own_referer = $values['own_referer'];
		}
		elseif ($own_referer === '')
		{
			$own_referer = Api\Header\Referer::get();
			if (strpos($own_referer,'menuaction=infolog.infolog_ui.edit') !== false)
			{
				$own_referer = Api\Cache::getSession('infolog', 'own_session');
			}
			else
			{
				Api\Cache::setSession('infolog', 'own_session', $own_referer);
			}
		}

		// Handle legacy buttons like actions
		if(is_array($values))
		{
			foreach(array('document', 'view', 'delete') as $button)
			{
				if(isset($values['nm']['rows'][$button]))
				{
					$id = @key($values['nm']['rows'][$button]);
					$values['nm']['multi_action'] = $button;
					$values['nm']['selected'] = array($id);
					break; // Only one can come per submit
				}
			}
		}
		if (is_array($values) && !empty($values['nm']['multi_action']))
		{
			if (empty($values['nm']['selected']) && !$values['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first');
			}
			else
			{
				// Some processing to add values in for links and cats
				$multi_action = $values['nm']['multi_action'];
				// Action has an additional action - add / delete, etc.  Buttons named <multi-action>_action[action_name]
				if(in_array($multi_action, array('link', 'responsible', 'startdate', 'enddate')))
				{
					// eTemplate ignores the _popup namespace, but et2 doesn't
					if($values[$multi_action.'_popup'])
					{
						$popup =& $values[$multi_action.'_popup'];
					}
					else
					{
						$popup =& $values;
					}
					$values['nm']['multi_action'] .= '_' . key($popup[$multi_action . '_action'] ?? []);
					if($multi_action == 'link')
					{
						$popup[$multi_action] = $popup['link']['app'] . ':'.$popup['link']['id'];
					}
					else if(is_array($popup[$multi_action]))
					{
						$popup[$multi_action] = implode(',',$popup[$multi_action]);
					}
					$values['nm']['multi_action'] .= '_' . $popup[$multi_action];
					unset($values[$multi_action.'_popup']);
					unset($values[$multi_action]);
				}
				$success = $failed = $action_msg = null;
				if ($this->action($values['nm']['multi_action'], $values['nm']['selected'], $values['nm']['select_all'],
					$success, $failed, $action_msg, $values['nm'], $msg, $values['nm']['checkboxes']['no_notifications']))
				{
					$msg .= lang('%1 entries %2',$success,$action_msg);
					Framework::message($msg);
				}
				elseif(is_null($msg))
				{
					$msg .= lang('%1 entries %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					Framework::message($msg,'error');
				}
				elseif($msg)
				{
					$msg .= "\n".lang('%1 entries %2, %3 failed.',$success,$action_msg,$failed);
					Framework::message($msg,'error');
				}
				unset($values['nm']['multi_action']);
				unset($values['nm']['select_all']);
			}
		}
		if (!$action)
		{
			$action = is_array($values) && $values['action'] ? $values['action'] : $_REQUEST['action'];
			$action_id = is_array($values) && $values['action_id'] ? $values['action_id'] : $_REQUEST['action_id'];
			$action_title = is_array($values) && $values['action_title'] ? $values['action_title'] : $_REQUEST['action_title'];
		}
		//echo "<p>".__METHOD__."(action='$action/$action_id',called_as='$called_as/$values[referer]',own_referer='$own_referer') values=\n"; _debug_array($values);
		if (!is_array($values))
		{
			$nm = Api\Cache::getSession('infolog', $this->called_by.'session_data');
			unset($nm['rows']);
			if ($values === 'reset_action_view')
			{
				$action = '';
				$action_id = 0;
				$action_title = '';
			}
			if($_GET['ajax'] === 'true')
			{
				$nm['action'] = '';
				$nm['action_id'] = 0;
				$nm['action_title'] = '';
				// check if action-view reset filter and restore it
				if (($filter = Api\Cache::getSession('infolog', 'filter_reset_from')))
				{
					$nm['filter'] = $filter;
					Api\Cache::unsetSession('infolog', 'filter_reset_from');
				}
			}
			$values = array('nm' => $nm);

			if (isset($_GET['filter']) && $_GET['filter'] != 'default' || !isset($values['nm']['filter']) && !$this->called_by)
			{
				$values['nm']['filter'] = $_GET['filter'] && $_GET['filter'] != 'default' ? $_GET['filter'] :
					($this->prefs['defaultFilter'] == 'none' ? '' : $this->prefs['defaultFilter']);
			}
			if (!isset($values['nm']['order']) || !$values['nm']['order'])
			{
				$values['nm']['order'] = 'info_datemodified';
				$values['nm']['sort'] = 'DESC';
			}

			if (!$values['nm']['session_for'] && $this->called_by) $values['nm']['session_for'] = $this->called_by;

			$action_id = $values['action_id'] = $action ? $action_id : $nm['action_id'];
			$action_title = $values['action_title'] = $action ? $action_title : $nm['action_title'];
			$action = $values['action'] = $action ? $action : $nm['action'];
		}
		if($_GET['search']) $values['nm']['search'] = $_GET['search'];

		if ($values['nm']['add'])
		{
			$values['add'] = $values['nm']['add'];
			unset($values['nm']['add']);
		}
		unset($values['nm']['rows']['checked']);	// not longer used, but hides button actions

		switch ($action)
		{
			case 'sp':
				if (!is_array($action_id) && strpos($action_id, 'infolog:') === 0) $action_id = (int)substr($action_id, 8);
				if ((is_array($action_id) && !$this->bo->read(current($action_id))) || !$this->bo->read($action_id))
				{
					$action = '';
					$action_id = 0;
					break;
				}
				else
				{
					$values['nm']['col_filter']['info_id_parent'] = $action_id;
				}
				break;
			default:
				// Nothing
		}
		$readonlys['cancel'] = $action != 'sp';

		$this->tmpl->read('infolog.index');
		$values['nm']['disable_autorefresh'] = true;	// we have push
		$values['nm']['options-filter'] = $this->filters;
		$values['nm']['get_rows'] = 'infolog.infolog_ui.get_rows';
		$values['nm']['add_on_top_sort_field'] = 'info_datemodified';
		$values['nm']['options-filter2'] = (in_array($this->prefs['show_links'],array('all','no_describtion')) ? array() : array(
			''               => 'default',
		)) + array(
			'no_describtion' => 'no details',
			'all'            => 'details',
		);

		//apply infolog_filter_change javascript method (hide/show of date filter form) over onchange filter
		$values['nm']['filter_onchange'] = "app.infolog.filter_change();";

		//apply infolog_filter2_change javascript method (show/hide details each rows) over onchange filter2
		$values['nm']['filter2_onchange'] = "return app.infolog.filter2_change(ev, widget)";

		// disable favories dropdown button, if not running as infolog
		if ($called_as && $called_as != 'infolog')
		{
			$values['nm']['favorites'] = false;
		}
		else
		{
			// Allow saving parent ID into favorites
			$values['nm']['favorites'] = array('action','action_id');
		}

		// Allow add actions even when there's no rows
		$values['nm']['placeholder_actions'] = array('new');

		if(!isset($values['nm']['filter2'])) $values['nm']['filter2'] = $this->prefs['nextmatch-'.($action ? 'infolog.'.$action : 'infolog.index.rows').'-details-pref'];

		// disable columns for main entry as set in the pref for details or no details
		$values['nm']['columnselection_pref'] = 'nextmatch-'.($action ? 'infolog.'.$action : 'infolog.index.rows')
			.($values['nm']['filter2']=='all' ? '-details' : '');
		if ($action == 'sp')
		{
			$pref = $values['nm']['columnselection_pref'];
			foreach(array('info_used_time_info_planned_time_info_replanned_time','info_datemodified','info_owner_info_responsible','customfields') as $name)
			{
				$values['main']['no_'.$name] = strpos($this->prefs[$pref],$name) === false;
			}
			if (!$values['main']['no_customfields'])
			{
				// set the column-header of the main table for the customfields.
				foreach(array_keys($this->bo->customfields) as $lname)
				{
					$values['main']['customfields'].=$lname."\n";
				}
			}
		}
		if ($values['nm']['filter']=='bydate')
		{
			foreach (array_keys($values['nm']['col_filter']) as $colfk)
			{
				if (is_int($colfk)) unset($values['nm']['col_filter']);
			}
		}
		$values['action'] = $persist['action'] = $values['nm']['action'] = $action;
		$values['action_id'] = $persist['action_id'] = $values['nm']['action_id'] = $action_id;
		$values['action_title'] = $persist['action_title'] = $values['nm']['action_title'] = $action_title;
		$values['duration_format'] = $this->duration_format;
		$persist['called_as'] = $called_as;
		$persist['own_referer'] = $own_referer;

		// store whole $values[nm] in etemplate request
		unset($values['nm']['rows']);
		$persist['nm'] = $values['nm'];

		if (!$called_as)
		{
			$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualInfologIndex');
		}
		else
		{
			$values['css'] = '<style type="text/css">@import url('.$GLOBALS['egw_info']['server']['webserver_url'].'/infolog/templates/default/app.css);'."</style>";
		}
		// add scrollbar to long description, if user choose so in his prefs
		if ($this->prefs['limit_des_lines'] > 0 || (string)$this->prefs['limit_des_lines'] == '')
		{
			$values['css'] .= '<style type="text/css">@media screen { .infoDes {  '.
				' max-height: '.
				(($this->prefs['limit_des_lines'] ? $this->prefs['limit_des_lines'] : 5) * 1.35).	// dono why em is not real lines
				'em; overflow: auto; }}</style>';
		}

		$sel_options = array(
			'info_type'     => $this->bo->enums['type'],
			'pm_id'      => array(lang('No project')),
			'info_priority' => $this->bo->enums['priority'],
		);

		// remove group-types user has not any rights to as filter
		// does not take implicit rights as delegated into account, so they will not be available as filters
		foreach($this->bo->group_owners as $type => $group)
		{
			if (!isset($this->bo->grants[$group])) unset($sel_options['info_type'][$type]);
		}

		return $this->tmpl->exec('infolog.infolog_ui.index',$values,$sel_options,$readonlys,$persist,$return_html ? -1 : 0);
	}

	/**
	 * Get valid types
	 *
	 * @return array - array of valid types
	 */
	private function get_validtypes()
	{
		// Types
		$types = $this->bo->enums['type'];
		if ($this->bo->group_owners)
		{
			// remove types owned by groups the user has no edit grant
			foreach($this->bo->group_owners as $type => $group)
			{
				if (!($this->bo->grants[$group] & Acl::EDIT))
				{
					unset($types[$type]);
				}
			}
		}
		return $types;
	}

	/**
	 * Get actions / context menu items
	 *
	 * @param array $query
	 * @return array see nextmatch_widget::get_actions()
	 */
	public function get_actions(array $query = [])
	{
		for($i = 0; $i <= 100; $i += 10)
		{
			$percent[$i] = $i.'%';
		}
		// Types
		$types = $this->get_validtypes();
		$types_add = array();
		// Do not add deleted type to add or change menus
		unset($types['delete']);
		foreach($types as $type => &$data)
		{
			$image_exists = Api\Image::find('infolog',$type);
			$data = array(
				'caption' => $data,
				'icon' => $image_exists ? $type : 'infolog/navbar',
			);
			$types_add[$type] = $data + array(
				'onExecute' => "javaScript:app.infolog.add_action_handler"
			);
		}

		$icons = null;
		$statis = $this->bo->get_status($query['col_filter']['info_type'], $icons);
		foreach($statis as $type => &$data)
		{
			$image_exists = Api\Image::find('infolog',$icons[$type]);
			$data = array(
				'caption' => $data,
				'icon' => $image_exists ? $icons[$type] : 'infolog/status',
			);
		}

		$actions = array(
			'open' => array(
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'onExecute' => Api\Header\UserAgent::mobile()?'javaScript:app.infolog.viewEntry':'',
				'url' => 'menuaction=infolog.infolog_ui.edit&info_id=$id',
				'popup' => Link::get_registry('infolog', 'add_popup'),
				'group' => $group=1,
				'mobileViewTemplate' => 'view?'.filemtime(Api\Etemplate\Widget\Template::rel2path('/infolog/templates/mobile/view.xet'))
			),
			'parent' => array(
				'caption' => 'View parent with children',
				'icon' => 'up.grey',
				'allowOnMultiple' => false,
				'enabled' => 'javaScript:app.infolog.has_parent',
				'onExecute' => 'javaScript:app.infolog.view_parent',
				'group' => $group,
				'hideOnMobile' => true
			),
			'add' => array(
				'caption' => 'Add',
				'group' => $group,
				'children' => array(
					'new' => array(
						'caption' => 'New',
						'children' => $types_add,
						'icon' => 'task',
					),
					'sub' => array(
						'caption' => 'Sub-entry',
						'url' => 'menuaction=infolog.infolog_ui.edit&action=sp&action_id=$id',
						'popup' => Link::get_registry('infolog', 'add_popup'),
						'allowOnMultiple' => false,
						'hint' => 'Add a new sub-task, -note, -call to this entry',
						'icon' => 'new',
					),
					'copy' => array(
						'caption' => 'Copy',
						'url' => 'menuaction=infolog.infolog_ui.edit&action=copy&info_id=$id',
						'popup' => Link::get_registry('infolog', 'add_popup'),
						'allowOnMultiple' => false,
						'icon' => 'copy',
					),
				),
				'hideOnMobile' => true
			),
			'no_notifications' => array(
				'caption' => 'Do not notify',
				'checkbox' => true,
				'confirm_mass_selection' => "You are going to change %1 entries: Are you sure you want to send notifications about this change?",
				'hint' => 'Do not notify of these changes',
				'group' => $group,
			),
			// modifying content of one or multiple infolog(s)
			'change' => array(
				'caption' => 'Change',
				'group' => ++$group,
				'icon' => 'edit',
				'disableClass' => 'rowNoEdit',
				'confirm_mass_selection' => true,
				'children' => array(
					'type' => array(
						'caption' => 'Type',
						'prefix' => 'type_',
						'children' => $types,
						'group' => $group,
						'icon' => 'task',
					),
					'status' => array(
						'caption' => 'Status',
						'prefix' => 'status_',
						'children' => $statis,
						'group' => $group,
						'icon' => 'ongoing',
					),
					'completion' => array(
						'caption' => 'Completed',
						'prefix' => 'completion_',
						'children' => $percent,
						'group' => $group,
						'icon' => 'completed',
					),
					'cat' =>  Etemplate\Widget\Nextmatch::category_action(
						'infolog',$group,'Change category','cat_'
					),
					'startdate' => array(
						'caption' => 'Start date',
						'group' => $group,
						'nm_action' => 'open_popup',
					),
					'enddate' => array(
						'caption' => 'Due date',
						'group' => $group,
						'nm_action' => 'open_popup',
					),
					'responsible' => array(
						'caption' => 'Delegation',
						'group' => $group,
						'icon' => 'users',
						'nm_action' => 'open_popup',
						'onExecute' => 'javaScript:app.infolog.change_responsible'
					),
					'link' => array(
						'caption' => 'Links',
						'group' => $group,
						'nm_action' => 'open_popup',
					),
				),
				'hideOnMobile' => true
			),
			'close' => array(
				'caption' => 'Close',
				'icon' => 'done',
				'group' => $group,
				'disableClass' => 'rowNoClose',
				'confirm_mass_selection' => true,
			),
			'close_all' => array(
				'caption' => 'Close all',
				'icon' => 'done_all',
				'group' => $group,
				'hint' => 'Sets the status of this entry and its subs to done',
				'allowOnMultiple' => false,
				'disableClass' => 'rowNoCloseAll',
				'confirm_mass_selection' => true,
			),
			'print' => array(
				'caption' => 'Print',
				'icon' => 'print',
				'group' => $group,
				'onExecute' => 'javaScript:app.infolog.infolog_menu_print'
			)
		);
		++$group;	// integration with other apps
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'url' => 'menuaction=filemanager.filemanager_ui.index&path=/apps/infolog/$id&ajax=true',
				'allowOnMultiple' => false,
				'group' => $group,
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['calendar'])
		{
			$actions['calendar'] = array(	// interactive add for a single event
				'icon' => 'calendar/navbar',
				'caption' => 'Schedule appointment',
				'group' => $group,
				'url' => 'menuaction=calendar.calendar_uiforms.edit&'.
					Link::get_registry('calendar', 'add_app') . '[]=infolog&'.Link::get_registry('calendar','add_id').'[]=$id',
				'allowOnMultiple' => false,
				'popup' => Link::get_registry('calendar', 'edit_popup')
			);
		}
		if ($GLOBALS['egw_info']['user']['apps']['timesheet'])
		{
			$actions['timesheet'] = array(	// interactive add for a single event
				'icon' => 'timesheet/navbar',
				'caption' => 'Timesheet',
				'group' => $group,
				'allowOnMultiple' => false,

				'children' => array(
					'timesheet_list' => array(
						'caption' => lang('View linked %1 entries', lang('timesheet')),
						'icon' => 'infolog/navbar',
						'onExecute' => 'javaScript:app.infolog.timesheet_list',
						'allowOnMultiple' => false,
						'hideOnDisabled' => true,
					),
					'timesheet_add' => array(
						'icon' => 'timesheet/navbar',
						'caption' => 'Add timesheet entry',
						'url' => 'menuaction=timesheet.timesheet_ui.edit&link_app[]=infolog&link_id[]=$id',
						'popup' => Link::get_registry('timesheet', 'add_popup'),
					)
				)
			);
			// if specific timer is NOT disabled, allow to book further time on existing sheets
			$config = Api\Config::read('timesheet');
			if (!in_array('specific', $config['disable_timer'] ?? []))
			{
				$actions['timesheet']['children']['timer'] = array(
					'icon' => 'timesheet/navbar',
					'caption' => 'Start timer',
					'onExecute' => 'javaScript:app.timesheet.egw.start_timer',
					'allowOnMultiple' => false,
				);
			}
		}
		if ($GLOBALS['egw_info']['user']['apps']['tracker'])
		{
			$actions['to_tracker'] = array(
				'icon' => 'tracker/navbar',
				'caption' => 'Tracker',
				'hint' => 'Convert to a ticket',
				'group' => $group,
				'url' => 'menuaction=tracker.tracker_ui.edit&'.
					Link::get_registry('tracker', 'add_app') . '[]=infolog&'.Link::get_registry('tracker','add_id').'[]=$id',
				'allowOnMultiple' => false,
				'popup' => Link::get_registry('tracker', 'add_popup'),
			);
		}

		$actions['documents'] = infolog_merge::document_action(
			$this->prefs['document_dir'], ++$group, 'Insert in document', 'document_',
			$this->prefs['default_document']
		);
		$actions['ical'] = array(
			'icon' => 'ical',
			'caption' => 'Export iCal',
			'postSubmit' => true,	// download needs post submit to work
			'group' => $group,
			'allowOnMultiple' => true,
			'hideOnMobile' => true
		);

		$actions['delete'] = array(
			'caption' => 'Delete',
			'group' => ++$group,
			'disableClass' => 'rowNoDelete',
			'onExecute' => 'javaScript:app.infolog.confirm_delete',
			'confirm_mass_selection' => true,
		);
		if ($query['col_filter']['info_status'] == 'deleted')
		{
			$actions['undelete'] = array(
				'caption' => 'Un-Delete',
				'group' => $group,
				'icon' => 'revert',
				'disableClass' => 'rowNoUndelete',
				'confirm_mass_selection' => true,
			);
		}
		$actions['info_drop_mail'] = array(
			'type' => 'drop',
			'caption' => 'Link mail',
			'acceptedTypes' => 'mail',
			'onExecute' => 'javaScript:app.infolog.handle_dropped_mail',
			'hideOnDisabled' => true
		);
		//echo "<p>".__METHOD__."($do_email, $tid_filter, $org_view)</p>\n"; _debug_array($actions);
		return $actions;
	}

	/**
	 * Apply an action to multiple events, but called via AJAX instead of submit
	 *
	 * @param string $action
	 * @param string[] $selected
	 * @param bool $all_selected All events are selected, not just what's in $selected
	 */
	public function ajax_action($action, $selected, $all_selected)
	{
		$success = 0;
		$failed = 0;
		$action_msg = '';
		$session_name = 'calendar_list';

		if($this->action($action, $selected, $all_selected, $success, $failed, $action_msg, [], $msg))
		{
			$msg = lang('%1 entries %2',$success,$action_msg);
		}
		elseif(is_null($msg))
		{
			$msg = lang('%1 entries %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
		}
		$app = Api\Json\Push::onlyFallback() || $all_selected ? 'infolog' : 'msg-only-push-refresh';
		Api\Json\Response::get()->call('egw.refresh', $msg, $app, $selected[0], $all_selected || count($selected) > 1 ? null :
			($action === 'delete' ? 'delete' : 'edit'), $app, null, null, $failed ? 'error' : 'success');
	}

	/**
	 * Handles actions on multiple infologs
	 *
	 * @param string $_action
	 * @param array $checked contact id's to use if !$use_all
	 * @param boolean $use_all if true use all entries of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like '%1 entries deleted'
	 * @param array $query get_rows parameter
	 * @param string &$msg on return user feedback
	 * @param boolean $skip_notifications true to NOT notify users about changes
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($_action, $checked, $use_all, &$success, &$failed, &$action_msg,
		array $query, &$msg, $skip_notifications = false)
	{
		//echo '<p>'.__METHOD__."('$action',".array2string($checked).','.(int)$use_all.",...)</p>\n";
		$success = $failed = 0;
		if ($use_all)
		{
			@set_time_limit(0);                     // switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;        // all
			$result = $readonlys = null;
			$this->get_rows($query,$result,$readonlys);
			$checked = array();
			foreach($result as $key => $info)
			{
				if(is_numeric($key))
				{
					$checked[] = $info['info_id'];
				}
			}
		}

		// Actions with options in the selectbox
		list($action, $settings) = explode('_', $_action, 2);

		// Actions that can handle a list of IDs
		switch($action)
		{
			case 'link':
				list($add_remove, $link) = explode('_', $settings, 2);
				list($app, $link_id) = explode(strpos($link,':') !== false ? ':' : ',', $link);
				if(!$link_id)
				{
					$action_msg = 'linked';
					$msg = lang('You need to select an entry for linking.');
					break;
				}
				$title = Link::title($app, $link_id);
				foreach($checked as $id)
				{
					if(!$this->bo->check_access($id, Acl::EDIT))
					{
						$failed++;
						continue;
					}
					if($add_remove == 'add')
					{
						$action_msg = lang('linked to %1', $title);
						if(Link::link('infolog', $id, $app, $link_id))
						{
							$success++;
						}
						else
						{
							$failed++;
						}
					}
					else
					{
						$action_msg = lang('unlinked from %1', $title);
						$count = Link::unlink(0, 'infolog', $id, '', $app, $link_id);
						$success += $count;
					}
				}
				return $failed == 0;

			case 'document':
				if (!$settings) $settings = $this->prefs['default_document'];
				$document_merge = new infolog_merge();
				$msg = $document_merge->download($settings, $checked, '', $this->prefs['document_dir']);
				$failed = count($checked);
				return false;
			case 'ical':
				// infolog_ical lets horde be auto-loaded, so it must go first
				$boical = new infolog_ical();
				Api\Header\Content::type('todo.ics','text/calendar');
				echo $boical->exportvCalendar($checked);
				exit();

		}

		// Actions that need to loop
		foreach($checked as $id)
		{
			if(!$entry = $this->bo->read($id))
			{
				continue;
			}
			switch($action)
			{
				case 'close':
					$action_msg = lang('closed');
					$this->close($id, '', false, $skip_notifications);
					$success++;
					break;

				case 'delete':
					$action_msg = $settings == 'sub' ? lang(' (and children) deleted') : lang('deleted');
					$result = $this->bo->delete($id, $settings=='sub', false, $skip_notifications);
					if($result == true)
					{
						$success++;
					}
					else
					{
						$failed++;
					}
					break;

				case 'type':
					$action_msg = lang('changed type');
					// Dont allow to change the type, if user has no delete rights from the group-owner
					if ($id && !($this->bo->grants[$entry['info_owner']] & Acl::DELETE))
					{
						$failed++;
						break;
					}
					$entry['info_type'] = $settings;
					try {
						$this->bo->write($entry, true,true,true,$skip_notifications,true); // Throw exceptions
					}
					catch (Api\Exception\WrongUserinput $e)
					{
						$msg .= "\n".$e->getMessage();
						$failed++;
						break;
					}
					$success++;
					break;

				case 'completion':
					$action_msg = lang('changed completion to %1%', $settings);
					$entry['info_percent'] = $settings;
					// Done and not-started entries will get changed right back if we don't change the status too
					if(in_array($entry['info_status'],array('not-started','done','billed','cancelled','archive')))
					{
						$entry['info_status'] = 'ongoing';
					}
					if($entry['info_percent'] == 0)
					{
						$entry['info_status'] = 'not-started';
					}
					else if ($entry['info_percent'] == 100)
					{
						$entry['info_status'] = 'done';
					}
					if($this->bo->write($entry, true,true,true,$skip_notifications))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
					break;

				case 'undelete':	// set it to valid status != 'deleted' for that type
					$settings = isset($this->bo->status[$entry['info_type']]['done']) ?
						$this->bo->status[$entry['info_type']]['done'] :
						$this->bo->status['defaults'][$entry['info_type']];
					// fall-through
				case 'status':
					if(isset($this->bo->status[$entry['info_type']][$settings]))
					{
						$action_msg = lang('changed status to %1', lang($this->bo->status[$entry['info_type']][$settings]));
						if(!in_array($settings,array('done','billed','cancelled','archive')) && $entry['info_percent'] == 100)
						{
							// Done entries will get changed right back if we don't change the completion too
							$entry['info_percent'] = 10;
						}
						if(in_array($settings, array('not-started')) && $entry['info_percent'] > 0)
						{
							$entry['info_percent'] = 0;
						}
						$entry['info_status'] = $settings;
						if($this->bo->write($entry, true,true,true,$skip_notifications))
						{
							$success++;
						}
					}
					else
					{
						$msg .= lang('Invalid status for entry type %1.', lang($this->bo->enums['type'][$entry['info_type']]));
						$failed++;
					}
					break;

				case 'cat':
					if($settings)
					{
						$cat_name = Api\Categories::id2name($settings);
						$action_msg = lang('changed category to %1', $cat_name);
					}
					else
					{
						$action_msg = lang('removed category');
					}
					$entry['info_cat'] = $settings;
					if($this->bo->write($entry, true,true,true,$skip_notifications))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
					break;

				case 'responsible':
					list($add_remove, $user_str) = explode('_', $settings, 2);
					$action_msg = ($add_remove == 'ok' ? lang('changed') : ($add_remove == 'add' ? lang('added') : lang('removed'))) . ' ';
					$names = array();
					$users = explode(',', $user_str);
					foreach($users as $account_id)
					{
						$names[] = Api\Accounts::username($account_id);
					}
					$action_msg .= implode(', ', $names);
					if($add_remove == 'ok')
					{
						$entry['info_responsible'] = (array)$users;
					}
					else
					{
						$function = $add_remove == 'add' ? 'array_merge' : 'array_diff';
						$entry['info_responsible'] = array_unique($function($entry['info_responsible'], (array)$users));
					}
					if($this->bo->write($entry, true,true,true,$skip_notifications))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
					break;
				case 'startdate':
				case 'enddate':
					$field = $action == 'startdate' ? 'info_startdate' : 'info_enddate';
					list($ok, $date) = explode('_', $settings, 2);
					$entry[$field] = $date ? Api\DateTime::to($date, 'ts') : null;
					$action_msg = lang('changed');
					if($this->bo->write($entry, true,true,true,$skip_notifications))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
					break;
			}
		}
		return $failed == 0;
	}

	/**
	 * Closes an infolog
	 *
	 * @param int|array $values=0 info_id (default _GET[info_id])
	 * @param string $_referer=''
	 * @param boolean $closesingle=false
	 */
	function close($values=0,$_referer='',$closesingle=false,$skip_notification = false)
	{
		//echo "<p>".__METHOD__."($values,$referer,$closeall)</p>\n";
		$info_id = (int) (is_array($values) ? $values['info_id'] : ($values ? $values : $_GET['info_id']));
		$referer = is_array($values) ? $values['referer'] : $_referer;

		if ($info_id)
		{
			$info = $this->bo->read($info_id);
			#_debug_array($info);
			$status = $info['info_status'];
			// closed stati assumed array('done','billed','cancelled')
			if (isset($this->bo->status[$info['info_type']]['done'])) {
				$status ='done';
			} elseif (isset($this->bo->status[$info['info_type']]['billed'])) {
				$status ='billed';
			} elseif (isset($this->bo->status[$info['info_type']]['cancelled'])) {
				$status ='cancelled';
			}
			#_debug_array($status);
			$values = array(
				'info_id'     => $info_id,
				'info_type'   => $info['info_type'],
				'info_status' => $status,
				'info_percent'=> 100,
				'info_datecompleted' => $this->bo->now_su,
			);
			$this->bo->write($values, true,true,true,$skip_notification);

			$query = array('action'=>'sp','action_id'=>$info_id);
			if (!$closesingle) {
				foreach((array)$this->bo->search($query) as $info)
				{
					if ($info['info_id_parent'] == $info_id)	// search also returns linked entries!
					{
						$this->close($info['info_id'],$referer,$closesingle,$skip_notification);	// we call ourselfs recursive to process subs from subs too
					}
				}
			}
		}
		if ($referer) $this->tmpl->location($referer);
	}

	/**
	 * Deletes an InfoLog entry
	 *
	 * @param array|int $values info_id (default _GET[info_id])
	 * @param string $_referer
	 * @param string $called_by
	 * @param boolean $skip_notification Do not send notification of deletion
	 */
	function delete($values=0,$_referer='',$called_by='',$skip_notification=False)
	{
		$info_id = (int) (is_array($values) ? $values['info_id'] : ($values ? $values : $_GET['info_id']));
		$referer = is_array($values) ? $values['referer'] : $_referer;

		if (!is_array($values) && $info_id > 0 && !$this->bo->anzSubs($info_id))	// entries without subs get confirmed by javascript
		{
			$values = array('delete' => true);
		}
		//echo "<p>infolog_ui::delete(".print_r($values,true).",'$referer','$called_by') info_id=$info_id</p>\n";

		if (is_array($values) || $info_id <= 0)
		{
			if (($values['delete'] || $values['delete_subs']) && $info_id > 0 && $this->bo->check_access($info_id,Acl::DELETE))
			{
				$deleted = $this->bo->delete($info_id,$values['delete_subs'],$values['info_id_parent'], $skip_notification);
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
			'get_rows'       => 'infolog.infolog_ui.get_rows',
			'no_filter2'     => True
		);
		$values['main']['no_actions'] = $values['nm']['no_actions'] = True;

		$persist['info_id'] = $info_id;
		$persist['referer'] = $referer;
		$persist['info_id_parent'] = $values['main'][1]['info_id_parent'];
		$persist['called_by'] = $called_by;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Delete');
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualInfologDelete');

		$this->tmpl->exec('infolog.infolog_ui.delete',$values,array(),$readonlys,$persist,$called_by == 'edit' ? 2 : 0);
	}

	/**
	 * Edit/Create an InfoLog Entry
	 *
	 * @param array $content Content from the eTemplate Exec call or info_id on inital call
	 * @param string $action Name of an app of 'sp' for a infolog-sub
	 * @param int $action_id of app-entry to which a link is created
	 * @param string $type Type of log-entry: note,todo,task
	 * @param string $referer array with param/get-vars of the refering page
	 */
	function edit($content = null,$action = '',$action_id=0,$type='',$referer='')
	{
		if (($submit = is_array($content)))
		{
			//echo "infolog_ui::edit: content="; _debug_array($content);
			$info_id   = $content['info_id'];
			$action    = $content['action'];    unset($content['action']);
			$action_id = $content['action_id']; unset($content['action_id']);
			$referer   = $content['referer'];   unset($content['referer']);
			$no_popup  = $content['no_popup'];  unset($content['no_popup']);

			$button = @key((array)$content['button']);
			if (!$button && $action) $button = $action;	// action selectbox
			//info_cc expects an comma separated string
			//error_log(__METHOD__.__LINE__.array2string($content));
			if (empty($content['info_cc'])) $content['info_cc'] = "";
			if (is_array($content['info_cc']))
			{
				foreach($content['info_cc'] as $i => $value)
				{
					//imap_rfc822 should not be used, but it works reliable here, until we have some regex solution or use horde stuff
					$addresses = imap_rfc822_parse_adrlist($value, '');
					//error_log(__METHOD__.__LINE__.$value.'->'.array2string($addresses[0]));
					$content['info_cc'][$i]=$addresses[0]->host ? $addresses[0]->mailbox.'@'.$addresses[0]->host : $addresses[0]->mailbox;
				}
				if (!empty($content['info_cc'])) $content['info_cc'] = implode(',',$content['info_cc']);
			}
			unset($content['button']);
			if ($button)
			{
				// Copy or schedule Infolog
				if (in_array($button,array('copy','schedule','ical','tracker')))
				{
					$action = $button;
					if (!$info_id || $this->bo->check_access($info_id,Acl::EDIT))
					{
						$button = 'apply';	// need to store infolog first
					}
				}
				//Validate the enddate must be greater than startdate
				if (!empty($content['info_enddate']) && !empty($content['info_startdate']))
				{
					$duration_date = $content['info_enddate']-$content['info_startdate'];
					if ($duration_date < 0)
					{
						$this->tmpl->set_validation_error('info_startdate', lang('Startdate must be before Enddate!!!'));
						$button = $action = '';	// stop save or apply
					}
				}
				//echo "<p>infolog_ui::edit(info_id=$info_id) '$button' button pressed, content="; _debug_array($content);
				if (($button == 'save' || $button == 'apply') && isset($content['info_subject']) && empty($content['info_subject']))
				{
					$this->tmpl->set_validation_error('info_subject',lang('Field must not be empty !!!'));
					$button = $action = '';	// stop save or apply
				}
				if (($button == 'save' || $button == 'apply') && $info_id)
				{
					$old = $this->bo->read($info_id);
					if (!($edit_acl = $this->bo->check_access($info_id,Acl::EDIT)))
					{
						$status_only = $this->bo->is_responsible($old);
						$undelete = $this->bo->check_access($old,infolog_bo::ACL_UNDELETE);
					}
					// enddate in the past gives warning
					if (isset($content['info_enddate'])
							&& $content['info_enddate'] < $this->bo->user_time_now
							&& !$this->bo->allow_past_due_date && !($content['info_status'] == 'done'
							|| $content['info_status'] == 'archive'))
					{
						$this->tmpl->set_validation_error('info_enddate', lang('Due date must be in the future!!!'));
					}
				}
				if (($button == 'save' || $button == 'apply') && (!$info_id || $edit_acl || $status_only || $undelete))
				{
					$operation = $info_id ?  'edit' : 'add';

					if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
					{
						$content['info_link_id'] = 0;	// as field has to be int
					}
					$active_tab = $content['tabs'];
					if (!($info_id = $this->bo->write($content, true, true, true, $content['no_notifications'])))
					{
						$content['msg'] = $info_id !== 0 || !$content['info_id'] ? lang('Error: saving the entry') :
							lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars(Egw::link('/index.php',array(
									'menuaction' => 'infolog.infolog_ui.edit',
									'info_id'    => $content['info_id'],
									'no_popup'   => $no_popup,
									'referer'    => $referer,
								))).'">','</a>');
						$button = $action = '';	// not exiting edit
						$info_id = $content['info_id'];
					}
					else
					{
						$GLOBALS['egw']->preferences->add('infolog','preferred_type',$content['info_type']);
						$GLOBALS['egw']->preferences->save_repository(false,'user',false);
						$content['msg'] = lang('InfoLog entry saved');
						Framework::refresh_opener($content['msg'],'infolog',$info_id,$operation);
					}
					$content['tabs'] = $active_tab;

					$pm_links = Link::get_links('infolog',$content['info_id'],'projectmanager');

					$content['link_to']['to_app'] = 'infolog';
					$content['link_to']['to_id'] = $info_id;
					/* $info_link_id is never defined
					if ($info_link_id && strpos($info_link_id,':') !== false)	// updating info_link_id if necessary
					{
						list($app,$id) = explode(':',$info_link_id);
						$link = Link::get_link('infolog',$info_id,$app,$id);
						if ((int) $content['info_link_id'] != (int) $link['link_id'])
						{
							$content['info_link_id'] = $link['link_id'];

							$to_write = array(
								'info_id'      => $content['info_id'],
								'info_link_id' => $content['info_link_id'],
								'info_from'    => $content['info_from'],
								'info_type'    => $content['info_type'],
								'info_owner'   => $content['info_owner'],
							);
							//echo "<p>updating info_link_id: ".print_r($to_write,true)."</p>\n";
							$this->bo->write($to_write,False,true,true,true);	// last true = no notifications, as no real change

							// Do not override info_contact if is already filled with contact
							if ($content['info_contact'])
							{
								unset($to_write['info_contact']);
								unset($to_write['blur_title']);
							}

							// we need eg. the new modification date, for further updates
							$content = array_merge($content,$to_write);
						}
					}*/

					// Need to purge description history after encryption?
					if($content['clean_history'])
					{
						$history = new Api\Storage\History('infolog');
						$record_count = $history->delete_field($info_id, 'De');
					}
				}
				elseif ($button == 'delete' && $info_id > 0)
				{
					if (!$referer && $action) $referer = array(
						'menuaction' => 'infolog.infolog_ui.index',
						'action' => $action,
						'action_id' => $action_id
					);
					if (!($content['msg'] = $this->delete($info_id,$referer,'edit'))) return;	// checks ACL first

					Framework::refresh_opener($content['msg'],'infolog',$info_id,'delete');
				}
				// called again after delete confirmation dialog
				elseif ($button == 'deleted'  && $content['msg'])
				{
					Framework::refresh_opener($content['msg'],'infolog',$info_id,'delete');
				}
				if ($button == 'save' || $button == 'cancel' || $button == 'delete' || $button == 'deleted')
				{
					if ($no_popup)
					{
						Egw::redirect_link($referer,array('msg' => $content['msg']));
					}
					Framework::window_close();
				}
			}
			// on a type-change, set the status to the default status of that type, if the actual status is not supported by the new type
			if (!array_key_exists($content['info_status'],$this->bo->status[$content['info_type']]))
			{
				$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
				// Make sure we don't end up with invalid status / percent combinations
				if ($content['info_status'] != 'done')
				{
					$content['info_datecompleted'] = '';
					if((int)$content['info_percent'] === 100)
					{
						$content['info_percent'] = 10;
					}
				}
				else
				{
					$content['info_percent'] = 100;
				}
				if($content['info_status'] != 'not-started' && (int)$content['info_percent'] == 0)
				{
					$content['info_percent'] = 10;
				}
			}
		}
		else	// new call via GET
		{
			//echo "<p>infolog_ui::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";
			$action    = $action    ? $action    : $_REQUEST['action'];
			$action_id = $action_id ? $action_id : $_REQUEST['action_id'];
			$info_id   = $content   ? $content   : $_REQUEST['info_id'];
			$type      = $type      ? $type      : $_REQUEST['type'];
			$referer   = $referer !== '' ? $referer : ($_GET['referer'] ? $_GET['referer'] :
				Api\Header\Referer::get('/index.php?menuaction=infolog.infolog_ui.index'));
			if (strpos($referer, 'msg=') !== false) $referer = preg_replace('/([&?]{1})msg=[^&]+&?/','\\1',$referer);	// remove previou/old msg from referer
			$no_popup  = $_GET['no_popup'];
			$print = (int) $_REQUEST['print'];
			//echo "<p>infolog_ui::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";

			if (($content = $this->bo->read( $info_id || $action != 'sp' ? $info_id : $action_id )) === false)
			{
				Framework::window_close(lang('Permission denied!'));
			}
			if (is_numeric($_REQUEST['cat_id']))
			{
				$content['info_cat'] = (int)$_REQUEST['cat_id'];
			}
			if (!$content)
			{
				$content['info_cat'] = $this->prefs['cat_add_default'];
			}
			if ($_GET['msg']) $content['msg'] = strip_tags($_GET['msg']);	// dont allow HTML!

			switch($this->prefs['set_start'])
			{
				case 'date': default: $set_startdate = mktime(0,0,0,date('m',$this->bo->user_time_now),date('d',$this->bo->user_time_now),date('Y',$this->bo->user_time_now)); break;
				case 'datetime':      $set_startdate = $this->bo->user_time_now; break;
				case 'empty':         $set_startdate = 0; break;
			}
			if ((int)$content['info_link_id'] > 0 && !Link::get_link($content['info_link_id']))
			{
				$content['info_link_id'] = 0;	// link has been deleted
				if (!$content['info_custom_link']) $content['info_from'] = '';
			}
			if (!$info_id && $action_id && $action == 'sp')    // new SubProject
			{
				if (!$this->bo->check_access($action_id,Acl::ADD))
				{
					return $referer ? $this->tmpl->location($referer) : $this->index(0,$action,$action_id);
				}
			}
			else
			{
				$undelete = $this->bo->check_access($content,infolog_bo::ACL_UNDELETE);
			}
			$content['links'] = $content['link_to'] = array(
				'to_id' => $info_id,
				'to_app' => 'infolog',
			);
		}
		// new call via GET or some actions handled here, as they can happen both ways ($_GET[action] or button/action in GUI)
		if (!$submit || in_array($action,array('sp','copy','schedule','ical','to_tracker')))
		{
			switch ($action)
			{
				case 'schedule':
					Egw::redirect_link('/index.php',array(
						'menuaction' => 'calendar.calendar_uiforms.edit',
						'link_app' => 'infolog',
						'link_id' => $info_id,
					));
					break;
				case 'ical':
					$boical = new infolog_ical();
					$result = $boical->exportVTODO($content,'2.0','PUBLISH',false);
					Api\Header\Content::type('todo.ics', 'text/calendar');
					echo $result;
					exit();
				case 'sp':
				case 'copy':
					$info_id = 0;
					$this->create_copy($content, $action == 'sp');
					if ($action == 'sp')	// for sub-entries use type or category, like for new entries
					{
						if ($type) $content['info_type'] = $type;
						if (is_numeric($_REQUEST['cat_id'])) $content['info_cat'] = (int) $_REQUEST['cat_id'];
					}
					unset($action);	// it get stored in $content and will cause an other copy after [apply]
					break;
				case 'to_tracker':
					Egw::redirect_link('/index.php',array(
						'menuaction' => 'tracker.tracker_ui.edit',
						Link::get_registry('tracker', 'add_app').'[]' => 'infolog',
						Link::get_registry('tracker','add_id').'[]' => $info_id,
					));
					break;
				case 'projectmanager':
					$content['pm_id'] = $action_id;
				default:	// to allow other apps to participate
					$content['info_subject'] = Link::title($action, $id);
					$action_ids = explode(',',$action_id);
					if(count($action_ids) == 1)
					{
						$content['info_contact'] = array('app' => $action, 'id' => $action_id);
					}
					foreach ($action_ids as $n => $id)
					{
						Link::link('infolog', $content['link_to']['to_id'], $action, $id);

						// calling "infolog_set" hook for first, in case app wants to set some more values
						if (!$n && ($set = Api\Hooks::single(array('location'=>'infolog_set','id'=>$action_id),$action)))
						{
							foreach((array)$set['link_app'] as $i => $l_app)
							{
								if (($l_id=$set['link_id'][$i])) Link::link('infolog',$content['link_to']['to_id'],$l_app,$l_id);
							}
							unset($set['link_app']);
							unset($set['link_id']);

							$content = array_merge($content, $set);
						}
					}
					// fall through
				case '':
					if ($info_id)
					{
						if (!isset($pm_links))
						{
							$pm_links = Link::get_links('infolog',$info_id,'projectmanager');
						}
						break;	// normal edit
					}
				case 'new':		// new entry, set some defaults, if not set by infolog_set hook
					if (empty($content['info_startdate'])) $content['info_startdate'] = (int) $_GET['startdate'] ? (int) $_GET['startdate'] : $set_startdate;
					if (empty($content['info_priority'])) $content['info_priority'] = 1; // normal
					$content['info_owner'] = $this->user;
					if ($type != '' && empty($content['info_type']))
					{
						$content['info_type'] = $type;
					}
					else if ($type == '' && empty($content['info_type']) && isset($GLOBALS['egw_info']['user']['preferences']['infolog']['preferred_type']))
					{
						$content['info_type'] = $GLOBALS['egw_info']['user']['preferences']['infolog']['preferred_type'];
					}
					if (empty($content['info_status'])) $content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
					if (empty($content['info_percent'])) $content['info_percent'] = $content['info_status'] == 'done' ? '100%' : '0%';
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
				if (!(($this->bo->grants[$group]??0) & Acl::EDIT))
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
				if ($info_id && !($this->bo->grants[$content['info_owner']] & Acl::DELETE))
				{
					//echo "<p>setting type to r/o as user has no delete rights from group #$group</p>\n";
					$readonlys['info_type'] = true;
				}
				// disable info_access for group-owners
				$readonlys['info_access'] = true;
			}
			elseif($GLOBALS['egw']->accounts->get_type($content['info_owner']) == 'g')
			{
				$content['info_owner'] = $this->user;
			}
		}
		$preserv = $content;

		// Don't preserve message or links
		unset($preserv['msg']);
		unset($preserv['links']); unset($preserv['link_to']);

		// for no edit rights or implizit edit of responsible user make all fields readonly, but status and percent
		if ($info_id && !$this->bo->check_access($content, Acl::EDIT) && !$undelete)
		{
			$readonlys['__ALL__'] = true;	// make all fields not explicitly set readonly
			if ($this->bo->is_responsible($content))
			{
				foreach($this->bo->responsible_edit as $name)
				{
					$readonlys[$name] = false;
				}
				$readonlys['timestamp'] = $readonlys['info_des'];
				$readonlys['button[edit]'] = $readonlys['button[save]'] = $readonlys['button[apply]'] = $readonlys['no_notifications'] = false;
			}
			$readonlys['action'] = $readonlys['button[cancel]'] = false;	// always allowed
		}
		elseif (!$info_id)
		{
			$readonlys['action'] = true;
		}
		// ToDo: use the old status before the delete
		if ($info_id && !empty($undelete))
		{
			$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
			$this->tmpl->setElementAttribute('button[save]', 'label', 'Un-Delete');
		}

		if (!($readonlys['button[delete]'] = !$info_id || !$this->bo->check_access($content, Acl::DELETE)))
		{
			$content['info_anz_subs'] = $this->bo->anzSubs($info_id);	// to determine js confirmation of delete or not
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang($this->messages[$info_id ? 'edit' : ($action == 'sp' ? 'add_sub' : 'add')]);

		// use a typ-specific template (infolog.edit.xyz), if one exists, otherwise fall back to the generic one
		if (!$this->tmpl->read('infolog.edit.'.$content['info_type']))
		{
			$this->tmpl->read(!empty($print) ? 'infolog.edit.print' : 'infolog.edit');
		}
		if ($this->bo->has_customfields($content['info_type']))
		{
			$content['customfields'] = $content['info_type'];
		}
		else
		{
			$readonlys['tabs']['customfields'] = true;
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['projectmanager']))
		{
			$readonlys['tabs']['project'] = true;	// disable the project tab
		}

		// Don't send '0' if there is no category
		if(!$content['info_cat'])
		{
			unset($content['info_cat']);
		}
		// Don't send '0' if there is no price list
		if($content['pl_id'] == '0')
		{
			unset($content['pl_id']);
		}
		$content['duration_format'] = $this->duration_format;
		$content['hours_per_workday'] = $this->hours_per_workday;
		if ($this->prefs['show_id']) $content['info_number'] = $info_id;

		// Check no notification preference, update if type changed
		if($content['info_type'] != $content['old_type'])
		{
			$content['no_notifications'] = in_array($content['info_type'], !is_array($this->prefs['no_notification_types']) ?
				explode(',', $this->prefs['no_notification_types']) :
				$this->prefs['no_notification_types']
			);
		}

		$content['info_anz_subs'] = (int)$content['info_anz_subs'];    // gives javascript error if empty!

		if(is_array($pm_links))
		{
			$old_pm_id = $content['pm_id'] && in_array($content['pm_id'], $pm_links) ? $content['pm_id'] : array_shift($pm_links);
		}
		else
		{
			$old_pm_id = $content['old_pm_id'];
		}
		unset($content['old_pm_id']);

		if($info_id && $this->bo->history)
		{
			$content['history'] = array(
				'id'             => $info_id,
				'app'            => 'infolog',
				'status-widgets' => array(
					'Ty'        => $types,
					//'Li',	// info_link_id
					'parent'    => 'link-entry:infolog',
					'Ca'        => 'select-cat',
					'Pr'        => $this->bo->enums['priority'],
					'Ow'        => 'select-account',
					//'Ac',	//	info_access: private||public
					'St'        => (array)$this->bo->status[$content['info_type']] + array('deleted' => 'deleted'),
					'Pe'        => 'select-percent',
					'Co'        => 'date-time',
					'st'        => 'date-time',
					'Mo' => 'date-time',
					'En' => 'date',
					'Re' => 'select-account',
					// PM fields, ToDo: access control!!!
					'pT' => 'date-duration',
					'uT' => 'date-duration',
					'replanned' => 'date-duration',
//					'pL' => 'projectmanager-pricelist',
					'pr' => 'float',
				),
			);
			$history_stati = array();
			$tracking = new infolog_tracking($this);
			foreach($tracking->field2history as $field => $history)
			{
				$history_stati[$history] = $tracking->field2label[$field] ?? null;
			}
			// Modified date removed from field2history, we don't need that in the history
			$history_stati['Mo'] = $tracking->field2label['info_datemodified'];
			unset($tracking);
		}
		else
		{
			$readonlys['tabs']['history'] = true;
		}
		$sel_options = array(
			'info_type'     => $types,
			'info_priority' => $this->bo->enums['priority'],
			'info_confirm'  => $this->bo->enums['confirm'],
			'info_status'   => $this->bo->status[$content['info_type']],
			'status'        => $history_stati,
			'action'        => array(
				'copy'  => array('label' => 'Copy', 'title' => 'Copy this Infolog'),
				'sp'    => 'Sub-entry',
				'print' => array('label' => 'Print', 'title' => 'Print this Infolog'),
				'ical' => array('label' => 'Export iCal', 'title' => 'Export iCal'),
				'to_tracker' => array('label' => 'Tracker', 'title' => 'Convert to a ticket'),
			),
		);
		if (!empty($GLOBALS['egw_info']['user']['apps']['calendar']))
		{
			$sel_options['action']['schedule'] = array('label' => 'Schedule', 'title' => 'Schedule appointment');
		}
		if (!empty($GLOBALS['egw_info']['user']['apps']['stylite']) && empty($GLOBALS['egw_info']['server']['disable_pgp_encryption']))
		{
			$content['encryption_ts'] = filemtime(EGW_SERVER_ROOT.'/stylite/js/app.js');
		}
		elseif (!empty($GLOBALS['egw_info']['server']['disable_pgp_encryption']))
		{
			$readonlys['encrypt'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.
			(!empty($content['status_only']) ? lang('Edit Status') : lang('Edit'));
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => ($info_id ? 'ManualInfologEdit' : 'ManualInfologAdd'));
		//error_log(substr($content['info_des'],1793,10));
		//$content['info_des'] = substr($content['info_des'],0,1793);
		//echo "<p>infolog_ui.edit(info_id='$info_id',action='$action',action_id='$action_id') readonlys="; print_r($readonlys); echo ", content = "; _debug_array($content);
		//$content['info_cc'] is expected (by the widget) to be an array of emailaddresses, but is stored as comma separated string
		if (!empty($content['info_cc'])&&!is_array($content['info_cc']))$content['info_cc'] = explode(',',$content['info_cc']);
		$preserve = array_merge( $preserv, array(	// preserved values
			'info_id'       => $info_id,
			'action'        => $action,
			'action_id'     => $action_id,
			'referer'       => $referer,
			'no_popup'      => $no_popup,
			'old_pm_id'     => $old_pm_id,
			'old_type'      => $content['info_type'],
		));
		$this->tmpl->exec('infolog.infolog_ui.edit',$content,$sel_options,$readonlys,$preserve,$no_popup ? 0 : 2);
	}

	/**
	 * Create copy or sub-entry from an entry currently read into $content
	 *
	 * Taking into account prefs and config about what to copy
	 *
	 * @param array &$content
	 * @param boolean $create_sub true: create a sub-entry instead of a copy, default false to create a copy
	 */
	private function create_copy(array &$content, $create_sub=false)
	{
		$info_id = $content['info_id'];	// it will be unset by exclude-fields

		// empty fields configured to be excluded (also contains id, uid, ...)
		$exclude_fields = $create_sub ? $this->bo->sub_excludefields : $this->bo->copy_excludefields;
		foreach ($exclude_fields as $field)
		{
			unset($content[$field]);
			if ($field == 'info_from') unset($content['info_link_id']);	// both together is called contact in UI
		}
		if ($create_sub)
		{
			$content['info_id_parent'] = $info_id;
		}
		// no startdate or startdate in the past --> set startdate from pref
		if (!isset($content['info_startdate']) || $content['info_startdate'] < $this->bo->user_time_now)
		{
			switch($this->prefs['set_start'])
			{
				case 'date': default: $set_startdate = mktime(0,0,0,date('m',$this->bo->user_time_now),date('d',$this->bo->user_time_now),date('Y',$this->bo->user_time_now)); break;
				case 'datetime':      $set_startdate = $this->bo->user_time_now; break;
				case 'empty':         $set_startdate = 0; break;
			}
			$content['info_startdate'] = $set_startdate;
		}
		// enddate in the past --> uset it
		if (isset($content['info_enddate']) || $content['info_enddate'] < $this->bo->user_time_now)
		{
			unset($content['info_enddate']);
		}
		if (!isset($content['info_type']))
		{
			$types = array_keys($this->get_validtypes());
			$content['info_type'] = $types[0];
		}
		// get a consistent status, percent and date-completed
		if (!isset($content['info_status'])) $content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
		if (!isset($content['info_percent'])) $content['info_percent'] = $content['info_status'] == 'done' ? '100%' : '0%';
		$content['info_datecompleted'] =$content['info_status'] == 'done' ? $this->bo->user_time_now : 0;

		if (!isset($content['info_cat'])) $content['info_cat'] = $this->prefs['cat_add_default'];

		if(!is_array($content['link_to'])) $content['link_to'] = array();
		$content['link_to']['to_app'] = 'infolog';
		$content['link_to']['to_id'] = 0;
		// Get links to be copied, if not excluded
		if (!in_array('link_to',$exclude_fields) || !in_array('attachments',$exclude_fields))
		{
			foreach(Link::get_links($content['link_to']['to_app'], $info_id) as $link)
			{
				if ($link['app'] != Link::VFS_APPNAME && !in_array('link_to', $exclude_fields))
				{
					Link::link('infolog', $content['link_to']['to_id'], $link['app'], $link['id'], $link['remark']);
				}
				elseif ($link['app'] == Link::VFS_APPNAME && !in_array('attachments', $exclude_fields))
				{
					Link::link('infolog', $content['link_to']['to_id'], Link::VFS_APPNAME, array(
						'tmp_name' => Link::vfs_path($link['app2'], $link['id2']).'/'.$link['id'],
						'name' => $link['id'],
					), $link['remark']);
				}
			}
		}
		$content['links'] = $content['link_to'];

		if ($content['info_link_id'])
		{
			$info_link_id = $content['info_link_id'];
			// we need this if copy is triggered via context menu action
			if (!isset($content['info_contact']) || empty($content['info_contact']) || $content['info_contact'] === 'copy:')
			{
				$linkinfos = Link::get_link($info_link_id);
				$content['info_contact'] = $linkinfos['link_app1']=='infolog'?
						array('app' => $linkinfos['link_app2'], 'id' => $linkinfos['link_id2']):
						array('app' => $linkinfos['link_app1'], 'id' => $linkinfos['link_id1']);
				if ($content['info_contact']['app'] == 'projectmanager')
				{
					$content['pm_id'] = $linkinfos['link_app1']=='projectmanager'? $linkinfos['link_id1']:$linkinfos['link_id2'];
				}
			}
			unset($content['info_link_id']);
		}
		$content['info_owner'] = !(int)$this->owner || !$this->bo->check_perms(Acl::ADD,0,$this->owner) ? $this->user : $this->owner;

		if (!empty($content['info_subject']))
		{
			if ($create_sub)
			{
				$config = Api\Config::read('infolog');
				$prefix = lang(empty($config['sub_prefix']) ? 'Re:': $config['sub_prefix']);
			}
			else
			{
				$prefix = lang('Copy of:');
			}
			$content['info_subject'] = $prefix.' '.$content['info_subject'];
		}
		if (!$create_sub)
		{
			$content['msg'] .= ($content['msg']?"\n":'').lang('%1 copied - the copy can now be edited', lang(Link::get_registry('infolog','entry')));
		}
	}

	function icon($cat,$id,$status='')
	{
		if (!$status || !($icon = $this->icons[$cat][$id.'_'.$status]))
		{
			$icon = $this->icons[$cat][$id];
		}
		if ($icon && !Api\Image::find('infolog', $icon))
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
		return $icon ? Api\Html::image('infolog',$icon,lang($alt),'border=0') : lang($alt);
	}

	/**
	 * Infolog's site configuration
	 *
	 */
	public function admin($content = array())
	{
		$fields = array(
			['value' => 'info_status', 'label' => 'Status', 'disabled' => true],
			['value' => 'info_percent', 'label' => 'Percent', 'disabled' => true],
			['value' => 'info_datecompleted', 'label' => 'date completed', 'disabled' => true],
			'info_cat'          => 'Category',
			'info_from'         => 'Contact',
			'info_subject'      => 'Subject',
			'info_des'          => 'Description',
			'link_to'           => 'Links',
			'info_priority'     => 'Priority',
			'info_location'     => 'Location',
			'info_planned_time' => 'Planned time',
			'info_used_time'    => 'Used time',
		);
		$excludefields = array(
			'info_cat'            => 'Category',
			'info_from'           => 'Contact',
			'info_subject'        => 'Subject',
			'info_des'            => 'Description',
			'link_to'             => 'Links',
			'attachments'         => 'Attachments',
			'info_priority'       => 'Priority',
			'info_location'       => 'Location',
			'info_planned_time'   => 'Planned time',
			'info_used_time'      => 'Used time',
			'info_type'           => 'Type',
			'info_owner'          => 'Owner',
			'info_responsible'    => 'Responsible',
			'info_access'         => 'Access',
			'info_startdate'      => 'Startdate',
			'info_enddate'        => 'Enddate',
			'info_id_parent'      => 'Parent',
			'info_status'         => 'Status',
			'info_confirm'        => 'Confirm',
			'pl_id'               => 'pricelist',
			'info_price'          => 'price',
			'info_percent'        => 'completed',
			'info_datecompleted'  => 'date completed',
			'info_replanned_time' => 're-planned time',
			'info_cc'             => 'CC',
			['value' => 'info_id', 'label' => 'ID', 'disabled' => true],
			['value' => 'info_uid', 'label' => 'UID', 'disabled' => true],
			['value' => 'info_etag', 'label' => 'ETag', 'disabled' => true],
			['value' => 'info_created', 'label' => 'Created date', 'disabled' => true],
			['value' => 'info_creator', 'label' => 'Creator', 'disabled' => true],
			['value' => 'info_datemodified', 'label' => 'Modified date', 'disabled' => true],
			['value' => 'info_modifier', 'label' => 'Modified by', 'disabled' => true],
			['value' => 'caldav_name', 'label' => 'CalDAV name', 'disabled' => true],
		);
		// add customfields to field list
		foreach(Api\Storage\Customfields::get('infolog') as $name => $data)
		{
			$excludefields['#'.$name] = $data['label'];
		}
		$sub_excludefields = $excludefields;
		unset($sub_excludefields['info_id_parent']);	// always set to parent!

		$config = Api\Config::read('infolog');
		Api\Translation::add_app('infolog');

		if($content)
		{
			// Save
			$button = key($content['button'] ?? []);
			if($button == 'save' || $button == 'apply')
			{
				$this->bo->responsible_edit = array('info_status','info_percent','info_datecompleted');

				if ($content['responsible_edit'])
				{
					$extra = array_intersect($content['responsible_edit'],array_keys($fields));
					$this->bo->responsible_edit = array_unique(array_merge($this->bo->responsible_edit,$extra));
				}
				Api\Config::save_value('copy_excludefields', $content['copy_excludefields'] ? $content['copy_excludefields'] : null, 'infolog');
				Api\Config::save_value('sub_excludefields', $content['sub_excludefields'] ? $content['sub_excludefields'] : array('*NONE*'), 'infolog');
				Api\Config::save_value('responsible_edit', $this->bo->responsible_edit, 'infolog');
				Api\Config::save_value('implicit_rights', $this->bo->implicit_rights = $content['implicit_rights'] == 'edit' ? 'edit' : 'read', 'infolog');
				Api\Config::save_value('history', $this->bo->history = $content['history'], 'infolog');
				Api\Config::save_value('index_load_cfs', implode(',', (array)$content['index_load_cfs']), 'infolog');
				Api\Config::save_value('sub_prefix', $content['sub_prefix'], 'infolog');
				Api\Config::save_value('allow_past_due_date', $content['allow_past_due_date'], 'infolog');
				Api\Config::save_value('limit_modified_n_month', $content['limit_modified_n_month'], 'infolog');
				// Notifications
				$notifications =& $config[infolog_tracking::CUSTOM_NOTIFICATION];
				$notifications[$content['notification_type']] = $content['notification'];
				Api\Config::save_value(infolog_tracking::CUSTOM_NOTIFICATION, $notifications,'infolog');
			}

			if($button == 'save' || $button == 'cancel')
			{
				Api\Json\Response::get()->apply('app.admin.load');
			}
		}
		else
		{
			// Load
			$content = $config;

			$content['implicit_rights'] = $this->bo->implicit_rights;
			$content['responsible_edit'] = $this->bo->responsible_edit;
			$content['copy_excludefields'] = $this->bo->copy_excludefields;
			$content['sub_excludefields'] = $this->bo->sub_excludefields;
			$content['history'] = $this->bo->history;
		}

		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Site configuration');

		// Load selected custom notification
		if(!$content['notification_type'])
		{
			$content['notification_type'] = '~global~';
		}
		$content['notification'] = $config[infolog_tracking::CUSTOM_NOTIFICATION][$content['notification_type']];
		$sel_options = array(
			'implicit_rights'    => array(
				'read' => 'read rights (default)',
				'edit' => 'edit rights (full edit rights incl. making someone else responsible!)',
			),
			'responsible_edit'   => $fields,
			'copy_excludefields' => $excludefields,
			'sub_excludefields'  => $sub_excludefields,
			'history'            => array(
				'history'              => lang('Yes, with purging of deleted items possible'),
				'history_admin_delete' => lang('Yes, only admins can purge deleted items'),
				'history_no_delete'    => lang('Yes, noone can purge deleted items'),
			),
			'index_load_cfs'     => $this->bo->enums['type'],
			'notification_type'  => array('~global~' => 'all') + $this->bo->enums['type']
		);
		$preserve['notification_old_type'] = $content['notification_type'];
		$this->tmpl->read('infolog.config');
		$this->tmpl->exec('infolog.infolog_ui.admin',$content,$sel_options,array(),$preserve);
	}

	/**
	 * imports a mail as infolog
	 *
	 * @param array $mailContent = null content of mail
	 * @return  array
	 */
	function mail_import(array $mailContent=null)
	{
		// It would get called from compose as a popup with egw_data
		if (!is_array($mailContent) && ($_GET['egw_data']))
		{
			// get the mail raw data
			Link::get_data ($_GET['egw_data']);
			return false;
		}

		return $this->edit($this->bo->import_mail($mailContent['addresses'],
				$mailContent['subject'],
				$mailContent['message'],
				$mailContent['attachments'],
				$mailContent['date']));
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
	 * 	Api\Hooks::process(array( \
	 * 		 * 'location' => 'infolog', \
	 * 		 * 'app'      => <your app>, \
	 * 		 * 'view_id'  => <id name>, \
	 * 		 * <id name>  => <id value>, \
	 * 		 * 'view'     => <menuaction to view an entry in your app> \
	 * 	));
	 */
	function hook_view($args)
	{
		// Load JS for infolog actions
		Framework::includeJS('.','app','infolog');

		switch ($args['location'])
		{
			case 'addressbook_view':
				$app     = 'addressbook';
				$view_id = 'ab_id';
				$view_id2 = 'contact_id';
				$view    = 'addressbook.addressbook_ui.view';
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
			echo "<p>infolog_ui::hook_view("; print_r($args); echo "): app='$app', $view_id='$args[$view_id]', view='$view'</p>\n";
		}
		if (!isset($app) || !isset($args[$view_id]))
		{
			return False;
		}
		$this->called_by = $app;	// for read/save_sessiondata, to have different sessions for the hooks

		// Set to calling app, so actions wind up in the correct place client side
		$GLOBALS['egw_info']['flags']['currentapp'] = $app;

		Api\Translation::add_app('infolog');

		// Still want infolog Api\Categories though
		$GLOBALS['egw']->categories = new Api\Categories('','infolog');
		$this->index(null,$app,$args[$view_id],array(
			'menuaction' => $view,
			isset($view_id2) ? $view_id2 : $view_id => $args[$view_id]
		),True);
	}

	/**
	 * Defines the fields for the csv export
	 *
	 * @param string $type infolog type to include only the matching custom fields if set
	 * @return array
	 */
	function csv_export_fields($type=null)
	{
		$fields = array(
			'info_type'          => lang('Type'),
			'info_from'          => lang('Contact'),
//			'info_link_id'       => lang('primary link'),
			'info_cat'           => array('label' => lang('Category'),'type' => 'select-cat'),
			'info_priority'      => lang('Priority'),
			'info_owner'         => array('label' => lang('Owner'),'type' => 'select-account'),
			'info_access'        => lang('Access'),
			'info_status'        => lang('Status'),
			'info_percent'       => lang('Completed'),
			'info_datecompleted' => lang('Date completed'),
			'info_datemodified'  => lang('Last modified'),
			'info_modifier'      => array('label' => lang('Modifier'),'type' => 'select-account'),
			'info_location'      => lang('Location'),
			'info_startdate'     => lang('Startdate'),
			'info_enddate'       => lang('Enddate'),
			'info_responsible'   => array('label' => lang('Responsible'),'type' => 'select-account'),
			'info_subject'       => lang('Subject'),
			'info_des'           => lang('Description'),
			'info_id'            => lang('Id'),
			// PM fields
			'info_planned_time'  => lang('planned time'),
			'info_used_time'     => lang('used time'),
			'pl_id'              => lang('pricelist'),
			'info_price'         => lang('price'),
		);
		foreach($this->bo->timestamps as $name)
		{
			$fields[$name] = array('label' => $fields[$name],'type' => 'date-time');
		}
		foreach($this->bo->customfields as $name => $data)
		{
			if ($data['type2'] && $type && !in_array($type,explode(',',$data['type2']))) continue;

			$fields['#'.$name] = array(
				'label' => $data['label'],
				'type'  => $data['type'],
			);
		}
		return $fields;
	}
}