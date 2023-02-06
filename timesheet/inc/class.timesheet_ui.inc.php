<?php
/**
 * TimeSheet - user interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @copyright (c) 2005-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;
use EGroupware\Timesheet\Events;

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
		$etpl = new Etemplate('timesheet.edit');
		if (!is_array($content))
		{
			if ($_GET['msg']) $msg = strip_tags($_GET['msg']);

			if ($view || (int)$_GET['ts_id'])
			{
				if (!$this->read((int)$_GET['ts_id']))
				{
					Framework::window_close(lang('Permission denied!!!'));
				}
				if (!$view && !$this->check_acl(Acl::EDIT))
				{
					$view = true;
				}
				$this->data['events'] = Events::get($this->data['ts_id']);
			}
			else	// new entry
			{
				$this->data = array(
					'ts_start' => $this->today,
					'start_time' => '',    // force empty start-time
					'end_time' => Api\DateTime::to($this->now, 'H:i'),
					'ts_owner' => $GLOBALS['egw_info']['user']['account_id'],
					'cat_id' => (int)$_REQUEST['cat_id'],
					'ts_status' => $GLOBALS['egw_info']['user']['preferences']['timesheet']['predefined_status'],
					'ts_project' => $_REQUEST['ts_project'],
					'ts_title_blur' => $_REQUEST['ts_project'],
					'events' => [],
				);
				if (!is_numeric($_REQUEST['ts_project']))
				{
					$this->data['pm_id'] = $this->find_pm_id($_REQUEST['ts_project']);
				}
			}
			// are we supposed to add pending events, to a new or an existing timesheet
			if (isset($_REQUEST['events']))
			{
				$pending = Events::getPending($_REQUEST['events'] === 'overall', $time);
				$this->data['events'] = array_merge($this->data['events'], array_values($pending));
				$start = $this->data['events'][0]['tse_time'];
				$this->data['ts_start'] = $start;
				$this->data['start_time'] = Api\DateTime::server2user($start, 'H:s');
				$this->data['end_time'] = '';
				$this->data['ts_duration'] = (int)$this->data['ts_duration'] + round($time / 60); // minutes
				$this->data['ts_quantity'] = (float)$this->data['ts_quantity'] + $this->data['ts_duration'] / 60.0; // hours
				// check if any of the events contains an app::id to link the timesheet to
				foreach($pending as $event)
				{
					if (!empty($event['tse_app']) && $event['tse_app'] !== TIMESHEET_APP && !empty($event['tse_app_id']))
					{
						$_REQUEST['link_app'] = $event['tse_app'];
						$_REQUEST['link_id']  = $event['tse_app_id'];
					}
				}
			}
			if (!empty($this->data['events']))
			{
				array_unshift($this->data['events'], false);
			}
			$matches = null;
			$referer = preg_match('/menuaction=([^&]+)/',$_SERVER['HTTP_REFERER'],$matches) ? $matches[1] :
				(strpos($_SERVER['HTTP_REFERER'],'/infolog/index.php') !== false ? 'infolog.infolog_ui.index' : TIMESHEET_APP.'.timesheet_ui.index');

			if($_GET['action'] == 'copy')
			{
				$this->create_copy();
				$msg = lang('%1 copied - the copy can now be edited', lang(Link::get_registry(TIMESHEET_APP,'entry')));
			}
			if (!$this->check_statusForEditRights($this->data))
			{
				$view = true;  //only admin can edit with this status
				$only_admin_edit = true;
				$msg = lang('only Admin can edit this status');
			}
			if(!$this->data['ts_project_blur'])
			{
				$this->data['ts_project_blur'] = $this->data['pm_id'] ? Link::title('projectmanager', $this->data['pm_id']) : '';
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
				$content['ts_description'] = $content['ts_description_short'];
			}
			// we only need 2 out of 3 values from start-, end-time or duration (the date in ts_start is always required!)
			if (isset($content['start_time']))		// start-time specified
			{
				//$content['ts_start'] += $content['start_time'];
				$start = new Api\DateTime($content['ts_start']);
				$start_time = new Api\DateTime($content['start_time']);
				$start->setTime($start_time->format('H'),$start_time->format('i'));
				$content['ts_start'] = $start->format('ts');
			}
			if (isset($content['end_time']))		// end-time specified
			{
				$end = new Api\DateTime($content['ts_start']);
				$end_time = new Api\DateTime($content['end_time']);
				$end->setTime($end_time->format('H'),$end_time->format('i'));
			}
			if ($end && $start)	// start- & end-time --> calculate the duration
			{
				$content['ts_duration'] = ($end->format('ts') - $start->format('ts')) / 60;
				// check if negative duration is caused by wrap over midnight
				if ($content['ts_duration'] < 0 && $content['ts_duration'] > -24*60)
				{
					$content['ts_duration'] += 24*60;
				}
				//echo "<p>end_time=$content[end_time], start_time=$content[start_time] --> duration=$content[ts_duration]</p>\n";
			}
			elseif ($content['ts_duration'] && $end)	// no start, calculate from end and duration
			{
				$content['ts_start'] = $end->format('ts') - 60*$content['ts_duration'];
				//echo "<p>end_time=$content[end_time], duration=$content[ts_duration] --> ts_start=$content[ts_start]=".Api\DateTime::to($content['ts_start'])."</p>\n";
			}
			if ($content['ts_duration'] > 0) unset($content['end_time']);
			// now we only deal with start (date+time) and duration
			$button = @key($content['button']);
			$view = $content['view'];
			$referer = $content['referer'];
			$content['ts_project_blur'] = $content['pm_id'] ? Link::title('projectmanager', $content['pm_id']) : '';
			$this->data = $content;
			foreach(array('button','view','referer','tabs','start_time') as $key)
			{
				unset($this->data[$key]);
			}
			switch($button)
			{
				case 'edit':
					if ($this->check_acl(Acl::EDIT) && !$only_admin_edit) $view = false;
					break;

				case 'undelete':
					if($content['ts_status'] == self::DELETED_STATUS)
					{
						unset($content['ts_status']);
						$this->data['ts_status'] = '';
					}
					$button = 'apply';
					// fall through
				case 'save':
				case 'save_new':
				case 'apply':
					if (($this->data['ts_quantity'] === '' || $this->ts_viewtype == 'short') && $this->data['ts_duration'])	// set the quantity (in h) from the duration (in min)
					{
						// We need to keep the actual value of ts_quantity when we are storing it, as it is used in price calculation
						// and rounding it causes miscalculation on prices
						$this->data['ts_quantity'] = $this->data['ts_duration'] / 60.0;
					}
					if ($this->data['ts_quantity'] === '')
					{
						$etpl->set_validation_error('ts_quantity',lang('Field must not be empty !!!'));
					}
					if ($this->data['ts_duration'] < 0)
					{
						$etpl->set_validation_error('start_time',lang('Starttime has to be before endtime !!!'));
					}
					// set ts_title to ts_project if short viewtype (title is not editable)
					if($this->ts_viewtype == 'short')
					{
						$this->data['ts_title'] = $this->data['ts_project'] = $this->data['pm_id'] ?
								Link::title('projectmanager', $this->data['pm_id']) :
								$this->data['ts_project'];
					}
					if (!$this->data['ts_title'])
					{
						$this->data['ts_title'] = $this->data['ts_title_blur'] ?
							$this->data['ts_title_blur'] : $this->data['ts_project_blur'];

						if (!$this->data['ts_title'])
						{
							$etpl->set_validation_error('ts_title',lang('Field must not be empty !!!'));
						}
						elseif($button != 'save_new')
						{
							// remove title-blur for same behavior after apply, as for opening the saved entry again
							unset($this->data['ts_title_blur']);
							unset($content['ts_title_blur']);
							unset($this->data['ts_project_blur']);
							unset($content['ts_project_blur']);
						}
					}
					if ($etpl->validation_errors()) break;	// the user need to fix the error, before we can save the entry

					// account for changed project --> remove old one from links and add new one
					if ((int) $this->data['pm_id'] != (int) $this->data['old_pm_id'])
					{
						// update links accordingly
						if ($this->data['pm_id'])
						{
							Link::link(TIMESHEET_APP,$content['link_to']['to_id'],'projectmanager',$this->data['pm_id']);
						}
						if ($this->data['old_pm_id'])
						{
							Link::unlink2(0,TIMESHEET_APP,$content['link_to']['to_id'],0,'projectmanager',$this->data['old_pm_id']);
							if(is_array($content['link_to']['to_id']))
							{
								unset($content['link_to']['to_id']['projectmanager:' . $this->data['old_pm_id']]);
							}
							unset($this->data['old_pm_id']);
						}
					}
					// check if we are linked to a project, but that is NOT set as project
					if (!$this->data['pm_id'] && is_array($content['link_to']['to_id']))
					{
						foreach($content['link_to']['to_id'] as $data)
						{
							if ($data['app'] == 'projectmanager')
							{
								$this->data['pm_id'] = $data['id'];
								$this->data['ts_project_blur'] = Link::title('projectmanager', $data['id']);
								break;
							}
						}
					}

					if ($this->save() != 0)
					{
						$msg = lang('Error saving the entry!!!');
						$button = '';
					}
					else
					{
						$msg = lang('Entry saved');
						if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
						{
							Link::link(TIMESHEET_APP,$this->data['ts_id'],$content['link_to']['to_id']);
						}
						// associate events with the now stored timesheet (need to run for existing timesheets too, if new events are added!)
						if (!empty($content['events']))
						{
							Events::addToTimesheet($this->data['ts_id'], array_map(static function($event)
							{
								return $event['tse_id'];
							}, $content['events']));
						}
					}
					Framework::refresh_opener($msg, 'timesheet', $this->data['ts_id'], $content['ts_id'] ? 'edit' : 'add');
					if ($button == 'apply') break;
					if ($button == 'save_new')
					{
						$msg .= ', '.lang('creating new entry');		// giving some feedback to the user

						if (!is_array($content['link_to']['to_id']))	// set links again, so new entry gets the same links as the existing one
						{
							$content['link_to']['to_id'] = 0;
							foreach(Link::get_links(TIMESHEET_APP,$this->data['ts_id'],'!'.Link::VFS_APPNAME) as $link)
							{
								Link::link(TIMESHEET_APP,$content['link_to']['to_id'],$link['app'],$link['id'],$link['remark']);
							}
						}
						// create a new entry
						$this->data['ts_start'] += 60 * $this->data['ts_duration'];
						foreach(array('ts_id','ts_title','ts_description','ts_duration','ts_quantity','ts_modified','ts_modifier','link_to') as $name)
						{
							unset($this->data[$name]);
						}
						// save the selected project, to delete the project-link, if the user changes the project
						$this->data['old_pm_id'] = $this->data['pm_id'];
						if($this->pm_integration == 'none')
						{
							unset($this->data['pm_id']);
						}
						break;
					}
					// fall-through for save
				case 'delete':
					if ($button == 'delete')
					{
						if ($this->delete())
						{
							$msg = lang('Entry deleted');
							Framework::refresh_opener($msg, 'timesheet', $this->data['ts_id'], 'delete');
						}
						else
						{
							$msg = lang('Error deleting the entry!!!');
							break;	// dont close window
						}
					}
					// fall-through for save
				case 'cancel':
					Framework::window_close();
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
				'to_id' => $this->data['ts_id'] ? $this->data['ts_id'] :
					($this->data['link_to']['to_id'] ? $this->data['link_to']['to_id'] : $content['link_to']['to_id']),
				'to_app' => TIMESHEET_APP,
			),
			'ts_quantity_blur' => $this->data['ts_duration'] ? round($this->data['ts_duration'] / 60.0,3) : '',
			'ts_quantity' => $this->data['ts_duration']/60.0 == $this->data['ts_quantity'] ? null : $this->data['ts_quantity'],
			'start_time' => isset($this->data['start_time']) ? $this->data['start_time'] : $this->data['ts_start'],
			'pm_integration' => $this->pm_integration,
			'no_ts_status' => !$this->status_labels && ($this->data['ts_status'] != self::DELETED_STATUS),
			'tabs' => $_GET['tabs'] ?? 'general',
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
						case 'projectmanager':
							$links[] = $link_id;
							// fall-through;
						default:
							if(!$n)
							{
								// get title from first linked app
								$preserv['ts_title_blur'] = Link::title($link_app,$link_id);
								// ask first linked app via "timesheet_set" hook, for further data to set, incl. links
								if (($set = Api\Hooks::single(array('location'=>'timesheet_set','id'=>$link_id),$link_app)))
								{
									foreach((array)$set['link_app'] as $i => $l_app)
									{
										if (($l_id=$set['link_id'][$i])) Link::link(TIMESHEET_APP,$content['link_to']['to_id'],$l_app,$l_id);
										if ($l_app == 'projectmanager') $links[] = $l_id;
									}
									unset($set['link_app']);
									unset($set['link_id']);

									$content = array_merge($content,$set);
								}
							}
							break;
					}
					if($link_app == 'calendar')
					{
						list($link_id) = explode(':', $link_id);
					}
					Link::link(TIMESHEET_APP,$content['link_to']['to_id'],$link_app,$link_id);
				}
			}
		}
		elseif ($this->data['ts_id'])
		{
			$links = Link::get_links(TIMESHEET_APP,$this->data['ts_id'],'projectmanager');
		}
		// make all linked projects availible for the pm-pricelist widget, to be able to choose prices from all
		$content['all_pm_ids'] = array_values($links);

		// set old id, pm selector (for later removal)
		if (count($links) > 0)
		{
			$preserv['old_pm_id'] = array_shift($links);
		}
		if ((!isset($this->data['pm_id']) || $this->data['pm_id'] === false) && $preserv['old_pm_id'])
		{
			$content['pm_id'] = $preserv['old_pm_id'];
		}
		if ($this->pm_integration == 'full')
		{
			$preserv['ts_project'] = $preserv['ts_project_blur'];
		}
		$content['history'] = array(
			'id'  => $this->data['ts_id'],
			'app' => 'timesheet',
			'status-widgets' => array(
				'ts_status' => $this->status_labels + array(self::DELETED_STATUS => 'Deleted'),
				'ts_modifier' => 'select-account',
				'cat_id' => 'select-cat',
			),
		);
		$sel_options['status'] = $this->field2label;

		// the actual title-blur is either the preserved title blur (if we are called from infolog entry),
		// or the preserved project-blur comming from the current selected project
		$content['ts_title_blur'] = $preserv['ts_title_blur'] ? $preserv['ts_title_blur'] : $content['ts_project_blur'];
		$readonlys = array(
			'button[delete]'   => !$this->data['ts_id'] || !$this->check_acl(Acl::DELETE) ||
				$this->data['ts_status'] == self::DELETED_STATUS ||$only_admin_edit ,
			'button[undelete]' => $this->data['ts_status'] != self::DELETED_STATUS,
			'button[edit]'     => !$view || !$this->check_acl(Acl::EDIT) || $only_admin_edit,
			'button[save]'     => $view,
			'button[save_new]' => $view,
			'button[apply]'    => $view,
			'tabs[events]'     => empty($this->data['events']), // hide events tab, if we have none
		);

		if ($view)
		{
			foreach(array_merge(array_keys($this->data),array('pm_id','pl_id','link_to')) as $key)
			{
				$readonlys[$key] = true;
			}
			$readonlys['start_time'] = $readonlys['end_time'] = true;
		}
		$edit_grants = $this->grant_list(Acl::EDIT);
		if (count($edit_grants) == 1)
		{
			$readonlys['ts_owner'] = true;
		}
		// in view mode or when editing existing entries, we need to add the owner
		// if it does not exist, otherwise it's displayed empty or missing
		if (($view || $content['ts_id']) && $content['ts_owner'] && !isset($edit_grants[$content['ts_owner']]))
		{
			$edit_grants[$content['ts_owner']] = Api\Accounts::username($content['ts_owner']);
		}
		$sel_options['tse_type'] = [
			Events::START => 'start',
			Events::STOP  => 'stop',
			Events::PAUSE => 'pause',
			Events::OVERALL|Events::START => 'start',
			Events::OVERALL|Events::STOP  => 'stop',
			Events::OVERALL|Events::PAUSE => 'pause',
		];
		$sel_options['ts_owner']  = $edit_grants;
		$sel_options['ts_status']  = $this->get_status_labels($only_admin_edit);
		if($this->config_data['history'] && $content['ts_status'] == self::DELETED_STATUS)
		{
			$sel_options['ts_status'][self::DELETED_STATUS] = 'Deleted';
		}
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
			$content['ts_description_short'] = $content['ts_description'];
			if(!$content['pm_id'] && $this->pm_integration != 'full' && $content['ts_project'])
			{
				$etpl->setElementAttribute('pm_id','blur',$content['ts_project']);
			}
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
	 * reimplemented from Api\Storage\Base to disable action-buttons based on the Acl and make some modification on the data
	 *
	 * @param array &$query
	 * @param array &$rows returned rows/cups
	 * @param array &$readonlys eg. to disable buttons based on Acl
	 * @param boolean $id_only if true only return (via $rows) an array of contact-ids, dont save state to session
	 * @return int total number of contacts matching the selection
	 */
	function get_rrows(&$query_in,&$rows,&$readonlys,$id_only=false)
	{
		$this->show_sums = false;
		$end_date = false;

		// Date filter
		if($query_in['filter'] === 'custom')
		{
			$end_date = $query_in['enddate'] ? $query_in['enddate'] : false;
			$query_in['startdate'] = $query_in['startdate'] ? $query_in['startdate'] : 1;
		}
		$date_filter = $this->date_filter($query_in['filter'],$query_in['startdate'],$end_date);

		if ($query_in['startdate'])
		{
			$start = explode('-',date('Y-m-d',$query_in['startdate']+12*60*60));
			$end   = explode('-',date('Y-m-d',$end_date ? $end_date : $query_in['startdate']+7.5*24*60*60));

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
			$filter_end_day   = $end_date ? date('l',$end_date+12*60*60) : false;
			//echo "<p align=right>prefs: $week_start_day - $week_end_day, filter: $filter_start_day - $filter_end_day</p>\n";
			if ($filter_start_day == $week_start_day && (!$filter_end_day || $filter_end_day == $week_end_day))
			{
				$this->show_sums[] = 'week';
			}
			// show day-sums, if range <= 5 weeks
			if (!$end_date || $end_date - $query_in['startdate'] < 36*24*60*60)
			{
				$this->show_sums[] = 'day';
			}
		}
		//echo "<p align=right>show_sums=".print_r($this->show_sums,true)."</p>\n";
		if (!$id_only && !$query_in['csv_export']) Api\Cache::setSession(TIMESHEET_APP, 'index', $query_in);

		$GLOBALS['egw']->session->commit_session();

		// Refresh actions (undelete needs this)
		$query_in['actions'] = $this->get_actions($query_in);

		$query = $query_in;	// keep the original query
		$query['enddate'] = $end_date;

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
			//$query['col_filter']['ts_id'] = Link::get_links('projectmanager',$query['col_filter']['pm_id'],'timesheet');
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
			if(!is_array($query['col_filter']['linked']))
			{
				list($app,$id) = explode(':',$query['col_filter']['linked']);
			}
			else
			{
				$app = $query['col_filter']['linked']['app'];
				$id = $query['col_filter']['linked']['id'];
			}
			if (!($links = Link::get_links($app,$id,'timesheet')))
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
		if (!empty($query['col_filter']['ts_owner']))
		{
			$GLOBALS['egw_info']['flags']['app_header'] .= ': '.implode(', ',
				array_map(Api\Accounts::class.'::username', (array)$query['col_filter']['ts_owner']));

			if ($query['col_filter']['ts_owner'] < 0)
			{
				$query['col_filter']['ts_owner'] = array_merge(array($query['col_filter']['ts_owner']),
					$GLOBALS['egw']->accounts->members($query['col_filter']['ts_owner'],true));
			}
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
			elseif ($query['startdate'])
			{
				$df = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
				$GLOBALS['egw_info']['flags']['app_header'] .= ': ' . Api\DateTime::to($query['startdate']+12*60*60, $df);
				if ($start != $end)
				{
					$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.Api\DateTime::to($query['enddate'] ? $query['enddate']+12*60*60:'now', $df);
				}
			}
		}
		// Update start / end dates for custom
		if($query_in['filter'] != '0' && $query_in['filter'] != 'custom' && Api\Json\Response::isJSONResponse())
		{
			Api\Json\Response::get()->call(
				'app.timesheet.update_timespan',
				Api\DateTime::to($query['startdate'] ? $query['startdate'] : 'now' , Api\DateTime::ET2),
				$query['filter'] ? Api\DateTime::to($query['enddate'], Api\DateTime::ET2) : null
			);
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
		$links = array();
		$links3 = Link::get_links_multiple(TIMESHEET_APP,$ids,true,'projectmanager');	// only check for pm links!
		//as the full array is expected, we must supply the missing but needed (since expected further down) information
		if (is_array($links3))
		{
			foreach ($links3 as $likey => $liarray)
			{
				if (is_array($liarray))
				{
					foreach ($liarray as $li2key => $lival)
					{
						$links[$likey][$li2key]['id'] = $lival;
						$links[$likey][$li2key]['app'] = 'projectmanager';
					}
				}
			}
		}
		unset($query['col_filter'][0]);

		$readonlys = array();
		$have_cats = false;
		foreach($rows as &$row)
		{
			if ($row['cat_id']) $have_cats = true;

			$row['class'] = 'row';
			if ($row['ts_id'] <= 0)	// sums
			{
				if ($query['sort'] == 'ASC') $row['ts_start'] -= 7200;	// fix for DSL change

				// Remove fake modified date, it breaks nextmatch checks
				unset($row['ts_modified']);

				// Set flag to avoid actions on these rows
				$row['no_actions'] = true;

				switch($row['ts_id'])
				{
					case 0:	// day-sum
						$row['ts_title'] = lang('Sum %1:',lang(date('l',$row['ts_start'])).' '.Api\DateTime::to($row['ts_start'], $GLOBALS['egw_info']['user']['preferences']['common']['dateformat']));
						$row['ts_id'] = 'sum-day-'.$row['ts_start'];
						break;
					case -1:	// week-sum
						$row['ts_title'] = lang('Sum %1:',lang('week').' '.substr($row['ts_week'],4).'/'.substr($row['ts_week'],0,4));
						$row['ts_id'] = 'sum-week-'.$row['ts_week'];
						break;
					case -2:	// month-sum
						$row['ts_title'] = lang('Sum %1:',lang(date('F',$row['ts_start'])).' '.substr($row['ts_month'],0,4));
						$row['ts_id'] = 'sum-month-'.$row['ts_month'];
						break;
					case -3:	// year-sum
						$row['ts_title'] = lang('Sum %1:',$row['ts_year']);
						$row['ts_id'] = 'sum-year-'.$row['ts_year'];
						break;
				}
				$row['ts_start'] = $row['ts_unitprice'] = '';
				if (!$this->quantity_sum) $row['ts_quantity'] = '';
				$row['class'] = 'th rowNoEdit rowNoDelete rowNoUndelete';
				$row['titleClass'] = 'timesheet_titleSum';
				continue;
			}
			if($row['ts_quantity'])
			{
				$row['ts_quantity'] = round($row['ts_quantity'], 2);
			}
			if (!$this->check_acl(Acl::EDIT,$row))
			{
				$row['class'] .= ' rowNoEdit ';
			}
			if (!$this->check_statusForEditRights($row))
			{
				$row['class'] .= ' rowNoEdit ';
			}
			if (!$this->check_acl(Acl::DELETE,$row))
			{
				$row['class'] .= ' rowNoDelete ';
			}
			if($row['ts_status'] != self::DELETED_STATUS)
			{
				$row['class'] .= ' rowNoUndelete ';
			}
			if ($query['col_filter']['ts_project'])
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

			if(!$row['titleClass']) $row['titleClass'] = 'timesheet_titleDetails';

		}
		$rows['no_cat_id'] = (!$have_cats || $query['cat_id']);
		if ($query['col_filter']['ts_owner']) $rows['ownerClass'] = 'noPrint';
		$rows['no_owner_col'] = $query['no_owner_col'];
		if(is_string($query['selectcols']))
		{
			$query['selectcols'] = explode(',', $query['selectcols']);
		}
		if (!$rows['no_owner_col'] && $query['selectcols'] && !in_array('ts_owner', $query['selectcols']))
		{
			$rows['no_owner_col'] = 1;
		}

		$rows += $this->summary;

		$rows['pm_integration'] = $this->pm_integration;
		$rows['ts_viewtype'] =  $rows['no_ts_quantity'] =  $rows['no_ts_unitprice'] =  $rows['no_ts_total'] = $this->ts_viewtype == 'short';
		if (!$rows['ts_viewtype'])
		{
			#_debug_array($query['selectcols']);
			if(!is_array($query['selectcols'])){
				$query['selectcols'] = explode(',',$query['selectcols']);
			}
			#ts_quantity,ts_unitprice,ts_total
			if ($query['selectcols'] && in_array('ts_quantity_quantity',$query['selectcols'])===false) $rows['no_ts_quantity'] = 1;
			if ($query['selectcols'] && in_array('ts_unitprice', $query['selectcols'])===false) $rows['no_ts_unitprice'] = 1;
			if ($query['selectcols'] && in_array('ts_total_price',$query['selectcols'])===false) $rows['no_ts_total'] = 1;
		}
		$rows['no_ts_status'] = is_array($query['selectcols']) && in_array('ts_status', $query['selectcols']) === false && !$this->config_data['history'] ||
			$query['no_status'];

		if ($query['search'])
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang("Search for '%1'", $query['search']);
		}

		return $total;
	}

	/**
	 * List timesheet entries
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index($content = null,$msg='')
	{
		$etpl = new Etemplate('timesheet.index');

		if (!empty($_GET['msg'])) $msg = $_GET['msg'];
		if (!empty($content['nm']['rows']['delete']))
		{
			$ts_id = key($content['nm']['rows']['delete']);
			if ($this->delete($ts_id))
			{
				$msg = lang('Entry deleted');
			}
			else
			{
				$msg = lang('Error deleting the entry!!!');
			}
		}
		if (is_array($content) && !empty($content['nm']['rows']['document']))  // handle insert in default document button like an action
		{
			$id = @key($content['nm']['rows']['document']);
			$content['nm']['action'] = 'document';
			$content['nm']['selected'] = array($id);
		}
		if (!empty($content['nm']['action']))
		{
			// remove sum-* rows from checked rows
			$content['nm']['selected'] = array_filter($content['nm']['selected'], function($id)
			{
				return $id > 0;
			});
			if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
			{
				$msg = lang('You need to select some entries first!');
			}
			else
			{
				$success = $failed = $action_msg = null;
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
			'nm' => Api\Cache::getSession(TIMESHEET_APP, 'index'),
			'msg' => $msg,
		);
		if (!is_array($content['nm']))
		{
			$date_filters = array('' => 'All');
			foreach(array_keys($this->date_filters) as $name)
			{
				$date_filters[$name] = $name;
			}
			$date_filters['custom'] = 'custom';

			$content['nm'] = array(
				'get_rows'       =>	TIMESHEET_APP.'.timesheet_ui.get_rrows',
				'options-filter' => $date_filters,
				'options-filter2' => array('No details','Details'),
				'order'          =>	'ts_start',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
				'filter_onchange' => "app.timesheet.filter_change();",
				'filter2_onchange' => "app.timesheet.filter2_change();",
				'filter2'        => (int)$GLOBALS['egw_info']['user']['preferences'][TIMESHEET_APP]['show_details'],
				'row_id'         => 'ts_id',
				'row_modified'   => 'ts_modified',
				//'actions'        => $this->get_actions(),
				'default_cols'   => '!legacy_actions',	// switch legacy actions column and row off by default
				'pm_integration' => $this->pm_integration,
				'placeholder_actions' => array('add'),
				'disable_autorefresh' => true,	// we have push
			);
		}

		if($_GET['search'])
		{
			$content['nm']['search'] = $_GET['search'];
		}
		if($_GET['link_app'] && Link::get_registry($_GET['link_app'], 'query') && $_GET['link_id'])
		{
			$content['nm']['col_filter']['linked'] = array(
				'app' => $_GET['link_app'],
				'id' => $_GET['link_id']
			);
		}
		$read_grants = $this->grant_list(Acl::READ);
		$content['nm']['no_owner_col'] = count($read_grants) == 1;
		if ($GLOBALS['egw_info']['user']['preferences']['timesheet']['nextmatch-timesheet.index.rows']) $content['nm']['selectcols'] = $GLOBALS['egw_info']['user']['preferences']['timesheet']['nextmatch-timesheet.index.rows'];
		$sel_options = array(
			'ts_owner'   => $read_grants,
			'pm_id'      => array(lang('No project')),
			'cat_id'     => array(array('value' => '', 'label' => lang('all categories')), array('value' => 0, 'label'=>lang('None'))),
			'ts_status'  => $this->status_labels+array(lang('No status')),
		);
		if($this->config_data['history'])
		{
			$sel_options['ts_status'][self::DELETED_STATUS] = 'Deleted';
		}
		$content['nm']['no_status'] = count($sel_options['ts_status']) <= 1;	// 1 because of 'No status'
		$content['nm']['favorites'] = true;		//Enable favorite

		if ($this->pm_integration != 'full')
		{
			$projects =& $this->query_list('ts_project');
			if (!is_array($projects)) $projects = array();
			$sel_options['ts_project'] = $projects + array(lang('No project'));
		}

		// dont show [Export] button if app is not availible to the user or we are on php4
		$readonlys['export'] = !$GLOBALS['egw_info']['user']['apps']['importexport'] || (int) phpversion() < 5;
		return $etpl->exec(TIMESHEET_APP.'.timesheet_ui.index',$content,$sel_options,$readonlys);
	}

	/**
	 * Get actions / context menu for index
	 *
	 * Changes here, require to log out, as $content['nm'] get stored in session!
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	public function get_actions(Array $query)
	{
		$actions = array(
			'open' => array(	// does edit if allowed, otherwise view
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=timesheet.timesheet_ui.edit&ts_id=$id',
				'popup' => Link::get_registry('timesheet', 'add_popup'),
				'group' => $group=1,
				'disableClass' => 'th',
				'onExecute' => Api\Header\UserAgent::mobile()?'javaScript:app.timesheet.viewEntry':'',
				'mobileViewTemplate' => 'view?'.filemtime(Api\Etemplate\Widget\Template::rel2path('/timesheet/templates/mobile/view.xet'))
			),
/*
			'view' => array(
				'caption' => 'View',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=timesheet.timesheet_ui.view&ts_id=$id',
				'popup' => Link::get_registry('timesheet', 'view_popup'),
				'group' => $group=1,
			),
			'edit' => array(
				'caption' => 'Edit',
				'allowOnMultiple' => false,
				'url' => 'menuaction=timesheet.timesheet_ui.edit&ts_id=$id',
				'popup' => Link::get_registry('timesheet', 'add_popup'),
				'group' => $group,
				'disableClass' => 'rowNoEdit',
			),
*/
			'add' => array(
				'caption' => 'Add',
				'group' => $group,
				'children' => array(
					'new' => array(
						'caption' => 'New',
						'onExecute' => 'javaScript:app.timesheet.add_action_handler',
						'icon' => 'new',
					),
					'copy' => array(
						'caption' => 'Copy',
						'url' => 'menuaction=timesheet.timesheet_ui.edit&action=copy&ts_id=$id',
						'popup' => Link::get_registry('infolog', 'add_popup'),
						'allowOnMultiple' => false,
						'icon' => 'copy',
					),
				)
			),
			'cat' => Etemplate\Widget\Nextmatch::category_action(
				'timesheet',++$group,'Change category','cat_'
			),
			'status' => array(
				'icon' => 'apply',
				'caption' => 'Modify status',
				'group' => $group,
				'children' => $this->get_status_labels(),
				'prefix' => 'to_status_',
				'enabled' => (boolean)$this->get_status_labels(),
				'onExecute' => 'javaScript:app.timesheet.ajax_action',
			),
		);
		// Change category via AJAX
		$actions['cat']['onExecute'] = $actions['status']['onExecute'];

		// Other Api\Applications
		$group++;
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$actions['filemanager'] = array(
				'icon' => 'filemanager/navbar',
				'caption' => 'Filemanager',
				'url' => 'menuaction=filemanager.filemanager_ui.index&path=/apps/timesheet/$id&ajax=true',
				'allowOnMultiple' => false,
				'group' => $group,
				'disableClass' => 'th',
			);
		}
		// if specific timer is NOT disabled, allow to book further time on existing sheets
		$config = Api\Config::read(TIMESHEET_APP);
		if (!in_array('specific', $config['disable_timer'] ?? []))
		{
			$actions['timer'] = array(
				'icon' => 'timesheet/navbar',
				'caption' => 'Start timer',
				'onExecute' => 'javaScript:app.timesheet.egw.start_timer',
				'allowOnMultiple' => false,
				'group' => $group,
				'disableClass' => 'th',
			);
		}
		$group++;
		$actions += array(
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
				'onExecute' => 'javaScript:app.timesheet.ajax_action',
			),
		);
		if ($query['col_filter']['ts_status'] == self::DELETED_STATUS)
		{
			$actions['undelete'] = array(
				'caption' => 'Un-Delete',
				'confirm' => 'Recover this entry',
				'confirm_multiple' => 'Recover these entries',
				'icon' => 'revert',
				'group' => $group,
				'disableClass' => 'rowNoUndelete',
				'onExecute' => 'javaScript:app.timesheet.ajax_action',
			);
		}
		// enable additonal edit check for following actions, if they are generally available
		foreach(array('cat','status') as $action)
		{
			if ($actions[$action]['enabled'])
			{
				$actions[$action]['disableClass'] = 'rowNoEdit';
			}
		}
		//_debug_array($actions);
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
		$session_name = 'index';

		if($this->action($action, $selected, $all_selected, $success, $failed, $action_msg, $session_name, $msg))
		{
			$msg .= lang('%1 timesheets(s) %2',$success,$action_msg);
		}
		elseif(empty($msg))
		{
			$msg = lang('%1 timesheets(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
		}
		$app = Api\Json\Push::onlyFallback() || $all_selected ? 'timesheet' : 'msg-only-push-refresh';
		Api\Json\Response::get()->call('egw.refresh', $msg, $app, $selected[0], $all_selected || count($selected) > 1 ? null :
			($action === 'delete' ? 'delete' : 'update'), $app, null, null, $failed ? 'error' : 'success');
	}

	/**
	 * apply an action to multiple timesheets
	 *
	 * @param string/int $action 'status_to',set status to timeshhets
	 * @param array $checked timesheet id's to use if !$use_all
	 * @param boolean $use_all if true use all$key timesheets of the current selection (in the session)
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
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession('timesheet', $session_name);

			if ($use_all)
			{
				@set_time_limit(0);			// switch off the execution time limit, as it's for big selections to small
				$query['num_rows'] = -1;	// all
				$readonlys = null;
				$this->get_rrows($query,$checked,$readonlys,true);	// true = only return the id's
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
			case 'undelete':
				$action_msg =lang('recovered');
				foreach((array)$checked as $n => $id)
				{
					if ($this->set_status($id,''))
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
				$cat_name = Api\Categories::id2name($settings);
				$action_msg = lang('changed category to %1', $cat_name);
				foreach((array)$checked as $n => $id) {
					if (($entry = $this->read($id)) &&
						($entry['cat_id'] = $settings) &&
						$this->save($entry) == 0)
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
				if (!$settings) $settings = $GLOBALS['egw_info']['user']['preferences']['timesheet']['default_document'];
				$document_merge = new timesheet_merge();
				$msg = $document_merge->download($settings, $checked, '', $GLOBALS['egw_info']['user']['preferences']['timesheet']['document_dir']);
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
			$button = @key($content['button']);
			unset ($content['button']);

			switch($button)
			{
				case 'delete':
					break;
				case 'apply':
				case 'save':
					foreach($content['statis'] as &$cat)
					{
						$id = $cat['id'];
						if (($cat ['name'] !== $this->status_labels_config[$id]) && ($cat ['name'] !== '') || ($cat ['parent'] !== $this->status_labels_config[$id]['parent']) && ($cat ['parent'] !== ''))
						{
							$this->status_labels_config[$id] = array(
							'name'   => trim(str_replace('&nbsp;', '', $cat['name'])),
							'parent' => $cat['parent'],
							'admin'  => $cat['admin']);
							$need_update = true;
						}
					}
					if ($need_update)
					{
						Api\Config::save_value('status_labels',$this->status_labels_config,TIMESHEET_APP);
						$this->config_data = Api\Config::read(TIMESHEET_APP);
						$this->load_statuses();
						$msg .= lang('Status updated.');
					}
					if ($button == 'apply') break;
					// fall-through
				case 'cancel':
					$GLOBALS['egw']->redirect_link('/admin/index.php', null, 'admin');
			}
		}
		if (!empty($content['statis']['delete']))
		{
			$id = key($content['statis']['delete']);
			if (isset($this->status_labels_config[$id]))
			{
				unset($this->status_labels_config[$id]);
				Api\Config::save_value('status_labels',$this->status_labels_config,TIMESHEET_APP);
				unset($this->status_labels[$id]);
				$msg .= lang('Status deleted.');
			}
		}

		$i = 1;
		$max_id = 0;
		unset($content['statis']);
		foreach($this->status_labels_config as $id => $label)
		{
			$content['statis'][$i]['name']= $label['name'];
			$content['statis'][$i]['id']= $id;
			$content['statis'][$i]['parent']= $label['parent'];
			$content['statis'][$i]['admin']= $label['admin'];
			$i++;
			$max_id = max($id, $max_id);
		}
		$content['statis'][$i]['name'] = '';
		$content['statis'][$i]['admin'] = '';
		$content['statis'][$i]['id'] = ++$max_id;

		$content['msg'] = $msg;
		$preserv = $content;
		$sel_options['parent'] = $this->status_labels;
		$etpl = new Etemplate('timesheet.editstatus');
		$etpl->exec('timesheet.timesheet_ui.editstatus',$content,$sel_options,array(),$preserv);
	}

	/**
	 * Try to find a PM ID from project title
	 *
	 * @param string $project
	 */
	protected function find_pm_id($project)
	{
		list($pm_number, $pm_title) = explode(': ', $project, 2);
		if(!$pm_number || !$pm_title)
		{
			return false;
		}

		$pm = new projectmanager_bo();
		$pm_ids = $pm->search(array('pm_number' => $pm_number, 'pm_title' => $pm_title));
		if($pm_ids && count($pm_ids) >= 1)
		{
			return $pm_ids[0]['pm_id'];
		}
		return false;
	}

	/**
	 * Create a copy from an entry currently read into $this->data including all
	 * customfields, attachments and links
	 */
	private function create_copy()
	{
		$original_id = $this->data['ts_id'];
		unset($this->data['ts_id']);

		$this->data['ts_title'] = lang('Copy of:') . ' ' .$this->data['ts_title'];
		unset($this->data['ts_modified']);
		unset($this->data['ts_modifier']);
		$this->data['ts_owner'] = !(int)$this->data['ts_owner'] || !$this->check_acl(Acl::ADD,NULL,$this->data['ts_owner']) ? $this->user : $this->data['ts_owner'];

		// Copy links
		if(!is_array($this->data['link_to'])) $this->data['link_to'] = array();
		$this->data['link_to']['to_app'] = 'timesheet';
		$this->data['link_to']['to_id'] = 0;

		foreach(Link::get_links($this->data['link_to']['to_app'], $original_id) as $link)
		{
			if ($link['app'] != Link::VFS_APPNAME)
			{
				Link::link('timesheet', $this->data['link_to']['to_id'], $link['app'], $link['id'], $link['remark']);
			}
			elseif ($link['app'] == Link::VFS_APPNAME)
			{
				Link::link('timesheet', $this->data['link_to']['to_id'], Link::VFS_APPNAME, array(
					'tmp_name' => Link::vfs_path($link['app2'], $link['id2']).'/'.$link['id'],
					'name' => $link['id'],
				), $link['remark']);
			}
		}
	}
}