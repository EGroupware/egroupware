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
			'view'       => $this->bo->cal_prefs['defaultcalendar'],
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
			list($id) = each($content['nm']['rows']['delete']);

			if ($this->bo->delete($id))
			{
				$msg = lang('Event deleted');
			}
		}
		//Delete all selected Entrys
		if (is_array($content) && $content['deleteall'])
		{
			//_debug_array($content);

			foreach($content['nm']['rows']['checked'] as $num => $id)
			{
				if ($this->bo->delete($id))
				{
					$msg .= lang('Event deleted');
				}
			}
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
		if (isset($_REQUEST['keywords']))	// new search => set filters so every match is shown
		{
			$this->adjust_for_search($_REQUEST['keywords'],$content['nm']);
		}
		return $etpl->exec('calendar.calendar_uilist.listview',$content,array(
			'filter' => &$this->date_filters,
		),$readonlys,'',$home ? -1 : 0);
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
		if (is_array($old_params))
		{
			if ($old_params['filter'] && $old_params['filter'] != $params['filter'])	// filter changed => order accordingly
			{
				$params['order'] = 'cal_start';
				$params['sort'] = $params['filter'] == 'after' ? 'ASC' : 'DESC';
			}
			if ($old_params['search'] != $params['search'])
			{
				$this->adjust_for_search($params['search'],$params);
			}
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
			$readonlys['delete['.$event['id'].']'] = !$this->bo->check_perms(EGW_ACL_DELETE,$event);

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

			$rows[] = $event;
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
}
