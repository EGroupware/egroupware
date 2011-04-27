<?php
/**
 * TimeSheet - user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @copyright (c) 2005-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * User interface object of the TimeSheet
 */
class timesheet_ui extends timesheet_bo
{
	var $public_functions = array(
		'view' => true,
		'edit' => true,
		'index' => true,
		'editstatus' => true,
	);
	/**
	 * ProjectManager integration: 'none', 'full' or default null
	 *
	 * @var string
	 */
	var $pm_integration;

	/**
	 * TimeSheet view type: 'short' or 'normal'
	 *
	 * @var string
	 */
	var $ts_viewtype;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$this->pm_integration = $this->config_data['pm_integration'];
		$this->ts_viewtype = $this->config_data['ts_viewtype'];
	}

	function view()
	{
		$this->edit(null,true);
	}

	function edit($content = null,$view = false)
	{
		$etpl = new etemplate('timesheet.edit');
		if (!is_array($content))
		{
			if ($_GET['msg']) $msg = strip_tags($_GET['msg']);

			if ($view || (int)$_GET['ts_id'])
			{
				if (!$this->read((int)$_GET['ts_id']))
				{
					$GLOBALS['egw']->common->egw_header();
					echo "<script>alert('".lang('Permission denied!!!')."'); window.close();</script>\n";
					$GLOBALS['egw']->common->egw_exit();
				}
				if (!$view && !$this->check_acl(EGW_ACL_EDIT))
				{
					$view = true;
				}
			}
			else	// new entry
			{
				$this->data = array(
					'ts_start' => $this->today,
					'end_time' => egw_time::to($this->now,'H:i'),
					'ts_owner' => $GLOBALS['egw_info']['user']['account_id'],
					'cat_id'   => (int) $_REQUEST['cat_id'],
					'ts_status'=> $GLOBALS['egw_info']['user']['preferences']['timesheet']['predefined_status'],
				);
			}
			$referer = preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ? $matches[1] :
				(strpos($_SERVER['HTTP_REFERER'],'/infolog/index.php') !== false ? 'infolog.infolog_ui.index' : TIMESHEET_APP.'.timesheet_ui.index');

			if (!isset($GLOBALS['egw_info']['user']['apps']['admin']) && $this->data['ts_status'])
			{
				if ($this->status_labels_config[$this->data['ts_status']]['admin'])
				{
					$view = true;  //only admin can edit with this status
					$only_admin_edit = true;
					$msg = lang('only Admin can edit this status');
				}
			}
		}
		else
		{
			//echo "<p>ts_start=$content[ts_start], start_time=$content[start_time], end_time=$content[end_time], ts_duration=$content[ts_duration], ts_quantity=$content[ts_quantity]</p>\n";
			if (!isset($GLOBALS['egw_info']['user']['apps']['admin']) && $content['ts_status'])
			{
				if ($this->status_labels_config[$content['ts_status']]['admin'])
				{
					$view = true;  //only admin can edit with this status
					$only_admin_edit = true;
					$msg = lang('only Admin can edit this status');
				}
			}
			if ($this->ts_viewtype == 'short')
			{
				$content['start_time']=$content['end_time']='00:00';
			}
			// we only need 2 out of 3 values from start-, end-time or duration (the date in ts_start is always required!)
			if (isset($content['start_time']) && $content['start_time'] != '00:00')		// start-time specified
			{
				//$content['ts_start'] += $content['start_time'];
				$start = new egw_time($content['ts_start']);
				$start_time = explode(':',$content['start_time']);
				$start->setTime($start_time[0],$start_time[1]);
				$content['ts_start'] = $start->format('ts');
			}
			if (isset($content['end_time']) && $content['end_time'] != '00:00')		// end-time specified
			{
				$end = new egw_time($content['ts_start']);
				$end_time = explode(':',$content['end_time']);
				$end->setTime($end_time[0],$end_time[1]);
			}
			if ($end && $start)	// start- & end-time --> calculate the duration
			{
				$content['ts_duration'] = ($end->format('ts') - $start->format('ts')) / 60;
				//echo "<p>end_time=$content[end_time], start_time=$content[start_time] --> duration=$content[ts_duration]</p>\n";
			}
			elseif ($content['ts_duration'] && $end)	// no start, calculate from end and duration
			{
				$content['ts_start'] = $end->format('ts') - 60*$content['ts_duration'];
				//echo "<p>end_time=$content[end_time], duration=$content[ts_duration] --> ts_start=$content[ts_start]=".egw_time::to($content['ts_start'])."</p>\n";
			}
			if ($content['ts_duration'] > 0) unset($content['end_time']);
			// now we only deal with start (date+time) and duration
			list($button) = @each($content['button']);
			$view = $content['view'];
			$referer = $content['referer'];
			$this->data = $content;
			foreach(array('button','view','referer','tabs','start_time') as $key)
			{
				unset($this->data[$key]);
			}
			switch($button)
			{
				case 'edit':
					if ($this->check_acl(EGW_ACL_EDIT) && !$only_admin_edit) $view = false;
					break;

				case 'save':
				case 'save_new':
				case 'apply':
					if ((!$this->data['ts_quantity'] || $this->ts_viewtype == 'short') && $this->data['ts_duration'])	// set the quantity (in h) from the duration (in min)
					{
						$this->data['ts_quantity'] = $this->data['ts_duration'] / 60.0;
					}
					if (!$this->data['ts_quantity'])
					{
						$etpl->set_validation_error('ts_quantity',lang('Field must not be empty !!!'));
					}
					if ($this->data['ts_duration'] < 0)	// for layout purpose we show the error behind the quantity field
					{
						$etpl->set_validation_error('ts_quantity',lang('Starttime has to be before endtime !!!'));
					}
					//echo "<p>ts_start=$content[ts_start], start_time=$content[start_time], end_time=$content[end_time], ts_duration=$content[ts_duration], ts_quantity=$content[ts_quantity]</p>\n";
					if (!$this->data['ts_project']) $this->data['ts_project'] = $this->data['ts_project_blur'];
					// set ts_title to ts_project if short viewtype (title is not editable)
					if($this->ts_viewtype == 'short')
					{
						$this->data['ts_title'] = $this->data['ts_project'];
					}
					if (!$this->data['ts_title'])
					{
						$this->data['ts_title'] = $this->data['ts_title_blur'] ?
							$this->data['ts_title_blur'] : $this->data['ts_project_blur'];

						if (!$this->data['ts_title'])
						{
							$etpl->set_validation_error('ts_title',lang('Field must not be empty !!!'));
						}
					}
					if ($etpl->validation_errors()) break;	// the user need to fix the error, before we can save the entry

					if ($this->save() != 0)
					{
						$msg = lang('Error saving the entry!!!');
						$button = '';
					}
					else
					{
						$msg = lang('Entry saved');
						if ((int) $this->data['pm_id'] != (int) $this->data['old_pm_id'])
						{
							// update links accordingly
							if ($this->data['pm_id'])
							{
								egw_link::link(TIMESHEET_APP,$content['link_to']['to_id'],'projectmanager',$this->data['pm_id']);
							}
							if ($this->data['old_pm_id'])
							{
								egw_link::unlink2(0,TIMESHEET_APP,$content['link_to']['to_id'],0,'projectmanager',$this->data['old_pm_id']);
								unset($this->data['old_pm_id']);
							}
						}
						if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
						{
							egw_link::link(TIMESHEET_APP,$this->data['ts_id'],$content['link_to']['to_id']);
						}
					}
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
						'menuaction' => $referer,
						'msg'        => $msg,
					))."';";
					if ($button == 'apply') break;
					if ($button == 'save_new')
					{
						$msg .= ', '.lang('creating new entry');		// giving some feedback to the user

						if (!is_array($content['link_to']['to_id']))	// set links again, so new entry gets the same links as the existing one
						{
							$content['link_to']['to_id'] = 0;
							foreach(egw_link::get_links(TIMESHEET_APP,$this->data['ts_id'],'!'.egw_link::VFS_APPNAME) as $link)
							{
								egw_link::link(TIMESHEET_APP,$content['link_to']['to_id'],$link['app'],$link['id'],$link['remark']);
							}
						}
						// create a new entry
						$this->data['ts_start'] += 60 * $this->data['ts_duration'];
						foreach(array('ts_id','ts_title','ts_description','ts_duration','ts_quantity','ts_modified','ts_modifier') as $name)
						{
							unset($this->data[$name]);
						}
						// save the selected project, to delete the project-link, if the user changes the project
						$this->data['old_pm_id'] = $this->data['pm_id'];
						break;
					}
					// fall-through for save
				case 'delete':
					if ($button == 'delete')
					{
						if ($this->delete())
						{
							$msg = lang('Entry deleted');
							$js = "opener.location.href=opener.location.href+'&msg=$msg';";
						}
						else
						{
							$msg = lang('Error deleting the entry!!!');
							break;	// dont close window
						}
					}
					// fall-through for save
				case 'cancel':
					$js .= 'window.close();';
					echo "<html>\n<body>\n<script>\n$js\n</script>\n</body>\n</html>\n";
					$GLOBALS['egw']->common->egw_exit();
					break;
			}
		}
		$preserv = $this->data + array(
			'view'    => $view,
			'referer' => $referer,
			'ts_title_blur' => $content['ts_title_blur'],
		);
		$content = array_merge($this->data,array(
			'msg'  => $msg,
			'view' => $view,
			'tabs'  => $content['tabs'],
			'link_to' => array(
				'to_id' => $this->data['ts_id'] ? $this->data['ts_id'] : $content['link_to']['to_id'],
				'to_app' => TIMESHEET_APP,
			),
			'js' => "<script>\n$js\n</script>\n",
			'ts_quantity_blur' => $this->data['ts_duration'] ? round($this->data['ts_duration'] / 60.0,3) : '',
			'start_time' => egw_time::to($this->data['ts_start'],'H:i'),
			'pm_integration' => $this->pm_integration,
			'no_ts_status' => !$this->status_labels,
		));
		$links = array();
		// create links specified in the REQUEST (URL)
		if (!$this->data['ts_id'] && isset($_REQUEST['link_app']) && isset($_REQUEST['link_id']) && !is_array($content['link_to']['to_id']))
		{
			$link_ids = is_array($_REQUEST['link_id']) ? $_REQUEST['link_id'] : array($_REQUEST['link_id']);
			foreach(is_array($_REQUEST['link_app']) ? $_REQUEST['link_app'] : array($_REQUEST['link_app']) as $n => $link_app)
			{
				$link_id = $link_ids[$n];
				if (preg_match('/^[a-z_0-9-]+:[:a-z_0-9-]+$/i',$link_app.':'.$link_id))	// gard against XSS
				{
					switch ($link_app)
					{
						case 'calendar':
							if (!$n)	// only if calendar is first link_app (clicked on ts icon in calendar)!
							{
								$calendar_bo = new calendar_bo();
								list($link_id, $recurrence) = explode(':', $link_id);
								$event = $calendar_bo->read($link_id, $recurrence);
								$content['ts_start'] = $event['start'];
								$content['ts_title'] = $calendar_bo->link_title($event);
								$content['start_time'] = egw_time::to($event['start'],'H:i');
								$content['ts_description'] = $event['description'];
								$content['ts_duration']	= ($event['end'] - $event['start']) / 60;
								$content['ts_quantity'] = ($event['end'] - $event['start']) / 3600;
								unset($content['end_time']);
							}
							break;
						case 'projectmanager':
							$links[] = $link_id;
							// fall-through;
						default:
							// get title from first linked app
							if(!$n) $preserv['ts_title_blur'] = egw_link::title($link_app,$link_id);
							break;
					}
					egw_link::link(TIMESHEET_APP,$content['link_to']['to_id'],$link_app,$link_id);
				}
			}
		}
		elseif ($this->data['ts_id'])
		{
			$links = egw_link::get_links(TIMESHEET_APP,$this->data['ts_id'],'projectmanager');
		}
		// make all linked projects availible for the pm-pricelist widget, to be able to choose prices from all
		$content['all_pm_ids'] = array_values($links);

		// set old id, pm selector (for later removal)
		if (count($links) > 0)
		{
			$preserv['old_pm_id'] = array_shift($links);
		}
		if (!isset($this->data['pm_id']) && $preserv['old_pm_id'])
		{
			$content['pm_id'] = $preserv['old_pm_id'];
		}
		if ($content['pm_id'])
		{
			$preserv['ts_project_blur'] = $content['ts_project_blur'] = egw_link::title('projectmanager',$content['pm_id']);
		}
		if ($this->pm_integration == 'full')
		{
			$preserv['ts_project'] = $preserv['ts_project_blur'];
		}
		$content['history'] = array(
				'id'  => $this->data['ts_id'],
				'app' => 'timesheet',
				'status-widgets' => array(
					'ts_status' => $this->status_labels,
					'ts_modifier' => 'select-account',
					'cat_id' => 'select-cat',
				),
		);
		$sel_options['status'] = $this->field2label;

		// the actual title-blur is either the preserved title blur (if we are called from infolog entry),
		// or the preserved project-blur comming from the current selected project
		$content['ts_title_blur'] = $preserv['ts_title_blur'] ? $preserv['ts_title_blur'] : $preserv['ts_project_blur'];
		$readonlys = array(
			'button[delete]'   => !$this->data['ts_id'] || !$this->check_acl(EGW_ACL_DELETE),
			'button[edit]'     => !$view || !$this->check_acl(EGW_ACL_EDIT),
			'button[save]'     => $view,
			'button[save_new]' => $view,
			'button[apply]'    => $view,
		);

		if ($view)
		{
			foreach(array_merge(array_keys($this->data),array('pm_id','pl_id','link_to')) as $key)
			{
				$readonlys[$key] = true;
			}
			$readonlys['start_time'] = $readonlys['end_time'] = true;
		}
		$edit_grants = $this->grant_list(EGW_ACL_EDIT);
		if (count($edit_grants) == 1)
		{
			$readonlys['ts_owner'] = true;
		}
		// in view mode, we need to add the owner, if it does not exist, otherwise it's displayed empty
		if ($view && $content['ts_owner'] && !isset($edit_grants[$content['ts_owner']]))
		{
			$edit_grants[$content['ts_owner']] = common::grab_owner_name($content['ts_owner']);
		}
		$sel_options['ts_owner']  = $edit_grants;
		$sel_options['ts_status']  = $this->status_labels;
		$GLOBALS['egw_info']['flags']['app_header'] = lang('timesheet').' - '.
			($view ? lang('View') : ($this->data['ts_id'] ? lang('Edit') : lang('Add')));

		// supress unknow widget 'projectmanager-*', if projectmanager is not installed or old
		if (!@file_exists(EGW_INCLUDE_ROOT.'/projectmanager/inc/class.projectmanager_widget.inc.php'))
		{
			$etpl->set_cell_attribute('pm_id','disabled',true);
			$etpl->set_cell_attribute('pl_id','disabled',true);
		}

		if($this->ts_viewtype == 'short')
		{
			$content['ts_viewtype'] = $readonlys['tabs']['notes'] = true;
		}
		if (!$this->customfields) $readonlys['tabs']['customfields'] = true;	// suppress tab if there are not customfields
		if (!$this->data['ts_id']) $readonlys['tabs']['history']    = true;   //suppress history for the first loading without ID

		return $etpl->exec(TIMESHEET_APP.'.timesheet_ui.edit',$content,$sel_options,$readonlys,$preserv,2);
	}

	/**
	 * Calculate the time from a timestamp containing date & time
	 *
	 * @param int $datetime
	 * @return int
	 */
	function datetime2time($datetime)
	{
		if (!$datetime) return 0;

		return $datetime - mktime(0,0,0,date('m',$datetime),date('d',$datetime),date('Y',$datetime));
	}

	/**
	 * query projects for nextmatch in the projects-list
	 *
	 * reimplemented from so_sql to disable action-buttons based on the acl and make some modification on the data
	 *
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on acl
	 * @param boolean $id_only=false if true only return (via $rows) an array of contact-ids, dont save state to session
	 * @return int total number of contacts matching the selection
	 */
	function get_rows(&$query_in,&$rows,&$readonlys,$id_only=false)
	{
		$this->show_sums = false;
		if ($query_in['filter'])
		{
			$date_filter = $this->date_filter($query_in['filter'],$query_in['startdate'],$query_in['enddate']);

			$start = explode('-',date('Y-m-d',$query_in['startdate']+12*60*60));
			$end   = explode('-',date('Y-m-d',$query_in['enddate'] ? $query_in['enddate'] : $query_in['startdate']+7.5*24*60*60));

			// show year-sums, if we are year-aligned (show full years)?
			if ((int)$start[2] == 1 && (int)$start[1] == 1 && (int)$end[2] == 31 && (int)$end[1] == 12)
			{
				$this->show_sums[] = 'year';
			}
			// show month-sums, if we are month-aligned (show full monthes)?
			if ((int)$start[2] == 1 && (int)$end[2] == (int)date('d',mktime(12,0,0,$end[1]+1,0,$end[0])))
			{
				$this->show_sums[] = 'month';
			}
			// show week-sums, if we are week-aligned (show full weeks)?
			$week_start_day = $GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'];
			if (!$week_start_day) $week_start_day = 'Sunday';
			switch($week_start_day)
			{
				case 'Sunday': $week_end_day = 'Saturday'; break;
				case 'Monday': $week_end_day = 'Sunday'; break;
				case 'Saturday': $week_end_day = 'Friday'; break;
			}
			$filter_start_day = date('l',$query_in['startdate']+12*60*60);
			$filter_end_day   = $query_in['enddate'] ? date('l',$query_in['enddate']+12*60*60) : false;
			//echo "<p align=right>prefs: $week_start_day - $week_end_day, filter: $filter_start_day - $filter_end_day</p>\n";
			if ($filter_start_day == $week_start_day && (!$filter_end_day || $filter_end_day == $week_end_day))
			{
				$this->show_sums[] = 'week';
			}
			// show day-sums, if range <= 5 weeks
			if (!$query_in['enddate'] || $query_in['enddate'] - $query_in['startdate'] < 36*24*60*60)
			{
				$this->show_sums[] = 'day';
			}
		}
		//echo "<p align=right>show_sums=".print_r($this->show_sums,true)."</p>\n";
		if (!$id_only) $GLOBALS['egw']->session->appsession('index',TIMESHEET_APP,$query_in);
		$query = $query_in;	// keep the original query

		if($this->ts_viewtype == 'short') $query_in['options-selectcols'] = array('ts_quantity'=>false,'ts_unitprice'=>false,'ts_total'=>false);
		if ($query['no_status']) $query_in['options-selectcols']['ts_status'] = false;

		//_debug_array($query['col_filter']);
		//echo "PM Integration:".$this->pm_integration.'<br>';
		// PM project filter for the PM integration
		if ($this->pm_integration == 'full')
		{
			unset($query['col_filter']['ts_project']);
		}
		if ((string)$query['col_filter']['pm_id'] != '' && (string)$query['col_filter']['pm_id'] != '0')
		{
			//$query['col_filter']['ts_id'] = egw_link::get_links('projectmanager',$query['col_filter']['pm_id'],'timesheet');
			$query['col_filter']['ts_id'] = $this->get_ts_links($query['col_filter']['pm_id']);
			if (empty($query['col_filter']['ts_id'])) $query['col_filter']['ts_id'] = -1;
			if (!$query['col_filter']['ts_id']) $query['col_filter']['ts_id'] = 0;
		}
		if ((string)$query['col_filter']['pm_id'] != '' && (string)$query['col_filter']['pm_id'] == '0')
		{
			$query['col_filter']['ts_project'] = 0;
			unset($query['col_filter']['ts_id']);
		}
		unset($query['col_filter']['pm_id']);

		// handle linked filter (show only entries linked to a certain other entry)
		if ($query['col_filter']['linked'])
		{
			list($app,$id) = explode(':',$query['col_filter']['linked']);
			if (!($links = egw_link::get_links($app,$id,'timesheet')))
			{
				$rows = array();	// no infologs linked to project --> no rows to return
				return 0;
			}
			if (!$query['col_filter']['ts_id'])
			{
				$query['col_filter']['ts_id'] = array_values(array_unique($links));
			}
			// allow to combine with other filters using ts_id --> intersect ids
			elseif (!($query['col_filter']['ts_id'] = array_intersect((array)$query['col_filter']['ts_id'],array_values(array_unique($links)))))
			{
				$rows = array();	// no infologs linked to project --> no rows to return
				return 0;
			}
		}
		unset($query['col_filter']['linked']);

		// filter for no project
		if ((string)$query['col_filter']['ts_project'] == '0')
		{
			$query['col_filter']['ts_project'] = null;
		}
		// filter for no status
		if ((string)$query['col_filter']['ts_status'] == '0')
		{
			$query['col_filter']['ts_status'] = null;
		}
		#_debug_array($query['col_filter']);
		if (isset($this->status_labels_substatus[$query['col_filter']['ts_status']]))
		{
			$query['col_filter']['ts_status'] = $this->status_labels_substatus[$query['col_filter']['ts_status']];
			foreach ($query['col_filter']['ts_status'] as $status_id)
			{
				if (isset($this->status_labels_substatus['2level'][$status_id]))
				{
					$query['col_filter']['ts_status'] = array_merge($query['col_filter']['ts_status'],$this->status_labels_substatus[$status_id]);
				}
			}
		}
		if ((int)$query['filter2'] != (int)$GLOBALS['egw_info']['user']['preferences'][TIMESHEET_APP]['show_details'])
		{
			$GLOBALS['egw']->preferences->add(TIMESHEET_APP,'show_details',(int)$query['filter2']);
			$GLOBALS['egw']->preferences->save_repository(true);
		}
		// category filter: cat_id or ''=All cats or 0=No cat
		if ($query['cat_id'])
		{
			$cats = $GLOBALS['egw']->categories->return_all_children((int)$query['cat_id']);
			$query['col_filter']['cat_id'] = count($cats) > 1 ? $cats : $query['cat_id'];
		}
		elseif ((string)$query['cat_id'] == '0')	// no category
		{
			$query['col_filter']['cat_id'] = null;
		}
		else	// all cats --> no filter
		{
			unset($query['col_filter']['cat_id']);
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('timesheet');
		if ($query['col_filter']['ts_owner'])
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$GLOBALS['egw']->common->grab_owner_name($query['col_filter']['ts_owner']);
			#if ($GLOBALS['egw']->accounts->get_type($query['col_filter']['ts_owner']) == 'g') $GLOBALS['egw_info']['flags']['app_header'] .= ' '. lang("and its members");
			#_debug_array($GLOBALS['egw']->accounts->members($query['col_filter']['ts_owner'],true));
			if ($query['col_filter']['ts_owner']<0) $query['col_filter']['ts_owner'] = array_merge(array($query['col_filter']['ts_owner']),$GLOBALS['egw']->accounts->members($query['col_filter']['ts_owner'],true));
		}
		else
		{
			unset($query['col_filter']['ts_owner']);
		}
		if ($query['filter'])
		{
			$query['col_filter'][0] = $date_filter;

			// generate a meaningful app-header / report title
			if ($this->show_sums['month'])
			{
				if ((int)$start[1] == 1 && (int) $end[1] == 12)		// whole year(s)
				{
					$GLOBALS['egw_info']['flags']['app_header'] .= ': ' . $start[0] . ($start[0] != $end[0] ? ' - '.$end[0] : '');
				}
				else
				{
					$GLOBALS['egw_info']['flags']['app_header'] .= ': ' . lang(date('F',$query['startdate']+12*60*60)) . ' ' . $start[0];
					if ($start[0] != $end[0] || $start[1] != $end[1])
					{
						$GLOBALS['egw_info']['flags']['app_header'] .= ' - ' . lang(date('F',$query['enddate']+12*60*60)) . ' ' . $end[0];
					}
				}
			}
			elseif ($this->show_sums['week'])
			{
				$GLOBALS['egw_info']['flags']['app_header'] .= ': ' . lang('week') . ' ' . date('W',$query['startdate']+36*60*60) . '/' . $start[0];
				if ($query['enddate'] && $query['enddate'] - $query['startdate'] > 10*24*60*60)
				{
					$GLOBALS['egw_info']['flags']['app_header'] .= ' - ' . date('W',$query['enddate']-36*60*60) . '/' . $end[0];
				}
			}
			else
			{
				$df = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
				$GLOBALS['egw_info']['flags']['app_header'] .= ': ' . $GLOBALS['egw']->common->show_date($query['startdate']+12*60*60,$df,false);
				if ($start != $end)
				{
					$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.$GLOBALS['egw']->common->show_date($query['enddate']+12*60*60,$df,false);
				}
			}
			if ($query['filter'] == 'custom')	// show the custom dates
			{
				$GLOBALS['egw']->js->set_onload("set_style_by_class('table','custom_hide','visibility','visible');");
			}
		}
		$total = parent::get_rows($query,$rows,$readonlys);

		$ids = array();
		foreach($rows as &$row)
		{
			if ($row['ts_id'] > 0) $ids[] = $row['ts_id'];
		}
		if ($id_only)
		{
			$rows = $ids;
			return $this->total;	// no need to set other fields or $readonlys
		}
		$links3 = egw_link::get_links_multiple(TIMESHEET_APP,$ids,true,'projectmanager');	// only check for pm links!
		#_debug_array($links);
		//as the full array is expected, we must supply the missing but needed (since expected further down) information
		if (is_array($links3))
		{
			foreach ($links3 as $likey => $liarray)
			{
				#echo "$likey";_debug_array($liarray);echo"<br>";
				if (is_array($liarray))
				{
					foreach ($liarray as $li2key => $lival)
					{
						$links[$likey][$li2key]['id'] = $lival;
						$links[$likey][$li2key]['app'] = 'projectmanager';
					}
				}
			}
			if (!is_array($links)) $links = array();
		}
		else
		{
			$links = array();
		}
		#_debug_array($links);
		unset($query['col_filter'][0]);

		$readonlys = array();
		$have_cats = false;
		foreach($rows as &$row)
		{
			if ($row['cat_id']) $have_cats = true;

			$row['class'] = 'row';
			if ($row['ts_id'] <= 0)	// sums
			{
				$readonlys["view[$row[ts_id]]"] = $readonlys["edit[$row[ts_id]]"] = $readonlys["delete[$row[ts_id]]"] = true;
				$readonlys["checked[{$row[ts_id]}]"] = true;
				$readonlys["document[{$row[ts_id]}]"] = true;
				if ($query['sort'] == 'ASC') $row['ts_start'] -= 7200;	// fix for DSL change
				switch($row['ts_id'])
				{
					case 0:	// day-sum
						$row['ts_title'] = lang('Sum %1:',lang(date('l',$row['ts_start'])).' '.$GLOBALS['egw']->common->show_date($row['ts_start'],
						$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],false));

						// For some reason day sum checkbox on the etemplate is checked[1] instead of checked[0]
						$readonlys["checked[1]"] = true;
						break;
					case -1:	// week-sum
						$row['ts_title'] = lang('Sum %1:',lang('week').' '.substr($row['ts_week'],4).'/'.substr($row['ts_week'],0,4));
						break;
					case -2:	// month-sum
						$row['ts_title'] = lang('Sum %1:',lang(date('F',$row['ts_start'])).' '.substr($row['ts_month'],0,4));
						break;
					case -3:	// year-sum
						$row['ts_title'] = lang('Sum %1:',$row['ts_year']);
						break;
				}
				$row['ts_start'] = $row['ts_unitprice'] = '';
				if (!$this->quantity_sum) $row['ts_quantity'] = '';
				$row['class'] = 'th';
				$row['titleClass'] = 'titleSum';
				unset($row['ts_id']);	// otherwise row would be selectable action-wise
				continue;
			}
			if (!$this->check_acl(EGW_ACL_EDIT,$row))
			{
				$readonlys["edit[$row[ts_id]]"] = true;
				$row['class'] .= ' rowNoEdit';
			}
			if (!$this->check_acl(EGW_ACL_DELETE,$row))
			{
				$readonlys["delete[$row[ts_id]]"] = true;
				$row['class'] .= ' rowNoDelete';
			}
			if ($query['col_filter']['ts_project'] || !$query['filter2'])
			{
				unset($row['ts_project']);	// dont need or want to show it
			}
			elseif ($links[$row['ts_id']])
			{
				foreach($links[$row['ts_id']] as $link)
				{
					if ($link['app'] == 'projectmanager')
					{
						$row['ts_link'] = $link;
						$row['ts_link']['title'] = $row['ts_project'];
						break;
					}
				}
			}
			$readonlys["document[{$row['ts_id']}]"] = !$GLOBALS['egw_info']['user']['preferences']['timesheet']['default_document'];
			if (!$query['filter2'])
			{
				unset($row['ts_description']);
			}
			else
			{
				$row['titleClass'] = 'titleDetails';
			}
		}
		if (!$have_cats || $query['cat_id']) $rows['no_cat_id'] = true;
		if ($query['col_filter']['ts_owner']) $rows['ownerClass'] = 'noPrint';
		$rows['no_owner_col'] = $query['no_owner_col'];
		if (!$rows['no_owner_col'] && $query['selectcols'] && !strpos($query['selectcols'],'ts_owner')) $rows['no_owner_col'] = 1;
		if ($query['filter'])
		{
			$rows += $this->summary;
		}
		$rows['pm_integration'] = $this->pm_integration;
		$rows['ts_viewtype'] =  $rows['no_ts_quantity'] =  $rows['no_ts_unitprice'] =  $rows['no_ts_total'] = $this->ts_viewtype == 'short';
		if (!$rows['ts_viewtype'])
		{
			#_debug_array($query['selectcols']);
			#ts_quantity,ts_unitprice,ts_total
			if ($query['selectcols'] && strpos($query['selectcols'],'ts_quantity')===false) $rows['no_ts_quantity'] = 1;
			if ($query['selectcols'] && strpos($query['selectcols'],'ts_unitprice')===false) $rows['no_ts_unitprice'] = 1;
			if ($query['selectcols'] && strpos($query['selectcols'],'ts_total')===false) $rows['no_ts_total'] = 1;
		}
		$rows['no_ts_status'] = strpos($query['selectcols'], 'ts_status') === false || $query['no_status'];

		return $total;
	}

	/**
	 * List timesheet entries
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function index($content = null,$msg='')
	{
		$etpl = new etemplate('timesheet.index');

		if ($_GET['msg']) $msg = $_GET['msg'];
		if ($content['nm']['rows']['delete'])
		{
			list($ts_id) = each($content['nm']['rows']['delete']);
			if ($this->delete($ts_id))
			{
				$msg = lang('Entry deleted');
			}
			else
			{
				$msg = lang('Error deleting the entry!!!');
			}
		}
		if (is_array($content) && isset($content['nm']['rows']['document']))  // handle insert in default document button like an action
		{
			list($id) = @each($content['nm']['rows']['document']);
			$content['nm']['action'] = 'document';
			$content['nm']['selected'] = array($id);
		}
		if ($content['nm']['action'])
		{
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
			}
			else
			{
				if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
					$success,$failed,$action_msg,'index',$msg))
				{
					$msg .= lang('%1 timesheets(s) %2',$success,$action_msg);
				}
				elseif(empty($msg))
				{
					$msg .= lang('%1 timesheets(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
				}
			}
		}

		$content = array(
			'nm' => $GLOBALS['egw']->session->appsession('index',TIMESHEET_APP),
			'msg' => $msg,
		);
		if (!is_array($content['nm']))
		{
			$date_filters = array('All');
			foreach($this->date_filters as $name => $date)
			{
				$date_filters[$name] = $name;
			}
			$date_filters['custom'] = 'custom';

			$content['nm'] = array(
				'get_rows'       =>	TIMESHEET_APP.'.timesheet_ui.get_rows',
				'options-filter' => $date_filters,
				'options-filter2' => array('No details','Details'),
				'order'          =>	'ts_start',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'header_left'    => 'timesheet.index.dates',
				'header_right'   => 'timesheet.index.add',
				'filter_onchange' => "set_style_by_class('table','custom_hide','visibility',this.value == 'custom' ? 'visible' : 'hidden'); if (this.value != 'custom') this.form.submit();",
				'filter2'        => (int)$GLOBALS['egw_info']['user']['preferences'][TIMESHEET_APP]['show_details'],
				'row_id'         => 'ts_id',
				//'actions'        => $this->get_actions(),
				'default_cols'   => '!legacy_actions',	// switch legacy actions column and row off by default
			);
		}
		$content['nm']['actions'] = $this->get_actions();

		if($_GET['search'])
		{
			$content['nm']['search'] = $_GET['search'];
		}
		$read_grants = $this->grant_list(EGW_ACL_READ);
		$content['nm']['no_owner_col'] = count($read_grants) == 1;
		if ($GLOBALS['egw_info']['user']['preferences']['timesheet']['nextmatch-timesheet.index.rows']) $content['nm']['selectcols'] = $GLOBALS['egw_info']['user']['preferences']['timesheet']['nextmatch-timesheet.index.rows'];
		$sel_options = array(
			'ts_owner'   => $read_grants,
			'pm_id'      => array(lang('No project')),
			'cat_id'     => array(lang('None')),
			'ts_status'  => $this->status_labels+array(lang('No status')),
		);
		$content['nm']['no_status'] = count($sel_options['ts_status']) <= 1;	// 1 because of 'No status'

		if ($this->pm_integration != 'full')
		{
			$projects =& $this->query_list('ts_project');
			if (!is_array($projects)) $projects = array();
			$sel_options['ts_project'] = $projects + array(lang('No project'));
		}

		// to be moved in a seperate file if rewrite is over
		$GLOBALS['egw_info']['flags']['java_script'] .= $this->js();

		// dont show [Export] button if app is not availible to the user or we are on php4
		$readonlys['export'] = !$GLOBALS['egw_info']['user']['apps']['importexport'] || (int) phpversion() < 5;
		return $etpl->exec(TIMESHEET_APP.'.timesheet_ui.index',$content,$sel_options,$readonlys,$preserv);
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content['nm'] get stored in session!
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	private function get_actions()
	{
		$actions = array(
			'open' => array(	// does edit if allowed, otherwise view
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=timesheet.timesheet_ui.edit&ts_id=$id',
				'popup' => egw_link::get_registry('timesheet', 'add_popup'),
				'group' => $group=1,
			),
/*
			'view' => array(
				'caption' => 'View',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=timesheet.timesheet_ui.view&ts_id=$id',
				'popup' => egw_link::get_registry('timesheet', 'view_popup'),
				'group' => $group=1,
			),
			'edit' => array(
				'caption' => 'Edit',
				'allowOnMultiple' => false,
				'url' => 'menuaction=timesheet.timesheet_ui.edit&ts_id=$id',
				'popup' => egw_link::get_registry('timesheet', 'add_popup'),
				'group' => $group,
				'disableClass' => 'rowNoEdit',
			),
*/
			'add' => array(
				'caption' => 'Add',
				'url' => 'menuaction=timesheet.timesheet_ui.edit',
				'popup' => egw_link::get_registry('timesheet', 'add_popup'),
				'group' => $group,
			),
			'select_all' => array(
				'caption' => 'Whole query',
				'checkbox' => true,
				'hint' => 'Apply the action on the whole query, NOT only the shown timesheets!!!',
				'group' => ++$group,
			),
			'cat' => nextmatch_widget::category_action(
				'timesheet',++$group,'Change category','cat_'
			),
			'status' => array(
				'icon' => 'apply',
				'caption' => 'Modify status',
				'group' => $group,
				'children' => $this->status_labels,
				'prefix' => 'to_status_',
				'enabled' => (boolean)$this->status_labels,
			),
			'documents' => timesheet_merge::document_action(
				$GLOBALS['egw_info']['user']['preferences']['timesheet']['document_dir'],
				$group, 'Insert in document', 'document_',
				$GLOBALS['egw_info']['user']['preferences']['timesheet']['default_document']
			),
			'delete' => array(
				'caption' => 'Delete',
				'confirm' => 'Delete this entry',
				'confirm_multiple' => 'Delete these entries',
				'group' => ++$group,
				'disableClass' => 'rowNoDelete',
			),
		);
		// enable additonal edit check for following actions, if they are generally available
		foreach(array('cat','status') as $action)
		{
			if ($actions[$action]['enabled'])
			{
				$actions[$action]['enabled'] = 'javaScript:nm_not_disableClass';	// required!
				$actions[$action]['disableClass'] = 'rowNoEdit';
			}
		}
		//_debug_array($actions);
		return $actions;
	}

	/**
	 * apply an action to multiple timesheets
	 *
	 * @param string/int $action 'status_to',set status to timeshhets
	 * @param array $checked timesheet id's to use if !$use_all
	 * @param boolean $use_all if true use all timesheets of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 timesheets 'deleted'
	 * @param string/array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg)
	{
		$success = $failed = 0;
		if ($use_all)
		{
			// get the whole selection
			$query = is_array($session_name) ? $session_name : $GLOBALS['egw']->session->appsession($session_name,'timesheet');

			if ($use_all)
			{
				@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
				$query['num_rows'] = -1;	// all
				$this->get_rows($query,$checked,$readonlys,true);	// true = only return the id's
			}
		}
		//error_log(__METHOD__."('$action', ".array2string($checked).', '.array2string($use_all).",,, '$session_name')");

		if (substr($action,0,9) == 'to_status')
		{
			$to_status = (int)substr($action,10);
			$action = 'to_status';
		}
		else
		{
			// Dialogs to get options
			list($action, $settings) = explode('_', $action, 2);
		}

		switch($action)
		{
			case 'delete':
				$action_msg = lang('deleted');
				foreach((array)$checked as $n => $id)
				{
					if ($this->delete($id))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				break;
			case 'to_status':
				$action_msg =lang('changed status');
				foreach((array)$checked as $n => $id)
				{
					if ($this->set_status($id,$to_status))
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				break;
			case 'cat':
				$cat_name = categories::id2name($settings);
				$action_msg = lang('changed category to %1', $cat_name);
				foreach((array)$checked as $n => $id) {
					$entry = $this->read($id);
					$entry['cat_id'] = $settings;
					if($this->save($entry) == 0)
					{
						$success++;
					}
					else
					{
						$failed++;
					}
				}
				break;

			case 'document':
				$msg = $this->download_document($checked,$settings);
				$failed = count($checked);
				return false;
		}

		return !$failed;
	}

	/**
	 * function for setting individual Status
	 *
	 * @param conetnt
	 * @param view
	 */
	function editstatus($content = null,$msg='')
	{
		// this function requires admin rights
		$GLOBALS['egw_info']['flags']['admin_only'] = true;
		$GLOBALS['egw']->check_app_rights();

		if (is_array($content))
		{
			list($button) = @each($content['button']);
			unset ($content['button']);

			switch($button)
			{
				case 'delete':
					break;
				case 'apply':
				case 'save':
					foreach($content['statis'] as $cat)
					{
						$id = $cat['id'];
						if (($cat ['name'] !== $this->status_labels_config[$id]) && ($cat ['name'] !== '') || ($cat ['parent'] !== $this->status_labels_config[$id]['parent']) && ($cat ['parent'] !== ''))
						{
							$this->status_labels[$id] = $cat['name'];
							$this->status_labels_config[$id] = array(
							'name'   => $cat['name'],
							'parent' => $cat['parent'],
							'admin'  => $cat['admin']);
							$need_update = true;
						}
					}
					if ($need_update)
					{
						config::save_value('status_labels',$this->status_labels_config,TIMESHEET_APP);
						$msg .= lang('Status updated.');
					}
					if ($button == 'apply') break;
					// fall-through
				case 'cancel':
					$GLOBALS['egw']->redirect_link('/index.php',array(
						'menuaction' => 'timesheet.timesheet_ui.index',
						'msg' => $msg,
					));
					break;
			}
		}
		if (isset($content['statis']['delete']))
		{
			list($id) = each($content['statis']['delete']);
			if (isset($this->status_labels_config[$id]))
			{
				unset($this->status_labels_config[$id]);
				config::save_value('status_labels',$this->status_labels_config,TIMESHEET_APP);
				unset($this->status_labels[$id]);
				$msg .= lang('Status deleted.');
			}
		}

		$i = 1;
		unset($content['statis']);
		foreach($this->status_labels_config as $id => $label)
		{
			$content['statis'][$i]['name']= $label['name'];
			$content['statis'][$i]['id']= $id;
			$content['statis'][$i]['parent']= $label['parent'];
			$content['statis'][$i]['admin']= $label['admin'];
			$i++;
		}
		$content['statis'][$i]['name'] = '';
		$content['statis'][$i]['parent'];
		$content['statis'][$i]['admin'] = '';
		$content['statis'][$i]['id'] = ++$id;

		$content['msg'] = $msg;
		$preserv = $content;
		$sel_options['parent'] = $this->status_labels;
		$etpl = new etemplate('timesheet.editstatus');
		$etpl->exec('timesheet.timesheet_ui.editstatus',$content,$sel_options,$readonlys,$preserv);
	}

	function js()
	{
		return '<script LANGUAGE="JavaScript">

		function timesheet_export()
		{
			egw_openWindowCentered(
				"'. $GLOBALS['egw']->link('/index.php','menuaction=importexport.uiexport.export_dialog&appname=timesheet&selection=use_all') . '",
				"Export",400,400);
			return false;
		}
</script>';
	}

	/**
	 * Download a document with inserted entries
	 *
	 * @param array $ids timesheet-ids
	 * @param string $document vfs-path of document
	 * @return string error-message or error, otherwise the function does NOT return!
	 */
	function download_document($ids,$document='')
	{
		if (!$document)
		{
			$document = $GLOBALS['egw_info']['user']['preferences']['timesheet']['default_document'];
		}
		else
		{
			$document = $GLOBALS['egw_info']['user']['preferences']['timesheet']['document_dir'].'/'.$document;
		}
		if (!@egw_vfs::stat($document))
		{
			return lang("Document '%1' does not exist or is not readable for you!",$document);
		}
		$document_merge = new timesheet_merge();

		return $document_merge->download($document,$ids);
	}
}
