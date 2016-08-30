<?php
/**
 * TimeSheet - business object
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
use EGroupware\Api\Acl;

if (!defined('TIMESHEET_APP'))
{
	define('TIMESHEET_APP','timesheet');
}


/**
 * Business object of the TimeSheet
 *
 * Uses eTemplate's Api\Storage as storage object (Table: egw_timesheet).
 */
class timesheet_bo extends Api\Storage
{
	/**
	 * Flag for timesheets deleted, but preserved
	 */
	const DELETED_STATUS = -1;

	/**
	 * Timesheets Api\Config data
	 *
	 * @var array
	 */
	var $config_data = array();
	/**
	 * Should we show a quantity sum, makes only sense if we sum up identical units (can be used to sum up negative (over-)time)
	 *
	 * @var boolean
	 */
	var $quantity_sum=false;
	/**
	 * current user
	 *
	 * @var int
	 */
	var $user;
	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $timestamps = array(
		'ts_start','ts_modified'
	);
	/**
	 * Start of today in user-time
	 *
	 * @var int
	 */
	var $today;
	/**
	 * Filter for search limiting the date-range
	 *
	 * @var array
	 */
	var $date_filters = array(	// Start: year,month,day,week, End: year,month,day,week
		'Today'       => array(0,0,0,0,  0,0,1,0),
		'Yesterday'   => array(0,0,-1,0, 0,0,0,0),
		'This week'   => array(0,0,0,0,  0,0,0,1),
		'Last week'   => array(0,0,0,-1, 0,0,0,0),
		'This month'  => array(0,0,0,0,  0,1,0,0),
		'Last month'  => array(0,-1,0,0, 0,0,0,0),
		'2 month ago' => array(0,-2,0,0, 0,-1,0,0),
		'This year'   => array(0,0,0,0,  1,0,0,0),
		'Last year'   => array(-1,0,0,0, 0,0,0,0),
		'2 years ago' => array(-2,0,0,0, -1,0,0,0),
		'3 years ago' => array(-3,0,0,0, -2,0,0,0),
	);
	/**
	 * Grants: $GLOBALS['egw']->acl->get_grants(TIMESHEET_APP);
	 *
	 * @var array
	 */
	var $grants;
	/**
	 * Sums of the last search in keys duration and price
	 *
	 * @var array
	 */
	var $summary;
	/**
	 * Array with boolean values in keys 'day', 'week' or 'month', for the sums to return in the search
	 *
	 * @var array
	 */
	var $show_sums;
	/**
	 * Array with custom fileds
	 *
	 * @var array
	 */
	var $customfields=array();
	/**
	 * Array with status label
	 *
	 * @var array
	 */
	var $status_labels = array();
	/**
	 * Array with status label configuration
	 *
	 * @var array
	 */
	var $status_labels_config = array();
	/**
	 * Instance of the timesheet_tracking object
	 *
	 * @var timesheet_tracking
	 */
	var $tracking;
	/**
	 * Translates field / acl-names to labels
	 *
	 * @var array
	 */
	var $field2label = array(
		'ts_project'     => 'Project',
		'ts_title'     	 => 'Title',
		'cat_id'         => 'Category',
		'ts_description' => 'Description',
		'ts_start'       => 'Start',
		'ts_duration'    => 'Duration',
		'ts_quantity'    => 'Quantity',
		'ts_unitprice'   => 'Unitprice',
		'ts_owner'       => 'Owner',
		'ts_modifier'    => 'Modifier',
		'ts_status'      => 'Status',
		'pm_id'		     => 'Projectid',
		// pseudo fields used in edit
		//'link_to'        => 'Attachments & Links',
		'customfields'   => 'Custom fields',
	);
	/**
	 * Name of the timesheet table storing custom fields
	 */
	const EXTRA_TABLE = 'egw_timesheet_extra';

	/**
	* Columns to search when user does a text search
	*/
	var $columns_to_search = array('egw_timesheet.ts_id', 'ts_project', 'ts_title', 'ts_description', 'ts_duration', 'ts_quantity', 'ts_unitprice');

	/**
	 * all cols in data which are not (direct)in the db, for data_merge
	 *
	 * @var array
	 */
	var $non_db_cols = array('pm_id');

	function __construct()
	{
		parent::__construct(TIMESHEET_APP,'egw_timesheet',self::EXTRA_TABLE,'','ts_extra_name','ts_extra_value','ts_id');

		if ($this->customfields) $this->columns_to_search[] = self::EXTRA_TABLE.'.ts_extra_value';
		$this->config_data = Api\Config::read(TIMESHEET_APP);
		$this->quantity_sum = $this->config_data['quantity_sum'] == 'true';

		// Load & process statuses
		if($this->config_data['status_labels']) $this->load_statuses();

		$this->today = mktime(0,0,0,date('m',$this->now),date('d',$this->now),date('Y',$this->now));

		// save us in $GLOBALS['timesheet_bo'] for ExecMethod used in hooks
		if (!is_object($GLOBALS['timesheet_bo']))
		{
			$GLOBALS['timesheet_bo'] =& $this;
		}
		$this->grants = $GLOBALS['egw']->acl->get_grants(TIMESHEET_APP);
	}

	/**
	 * Load status labels
	 */
	protected function load_statuses()
	{
		$this->status_labels =&  $this->config_data['status_labels'];
		if (!is_array($this->status_labels)) $this->status_labels= array($this->status_labels);

		foreach ($this->status_labels as $status_id => $label)
		{
			if (!is_array($label))
			{	//old values, before parent status
				$name = $label;
				$label=array();
				$label['name'] = $name;
				$label['parent'] = '';
			}
			$label['id'] = $status_id;
			$this->status_labels_config[$status_id] = $label;
		}

		// Organise into tree structure
		$map = array(
			'' => array('substatus' => array())
		);
		foreach($this->status_labels_config as $id => &$status)
		{
			$status['substatus'] = array();
			$map[$id] = &$status;
		}
		foreach($this->status_labels_config as &$status)
		{
			$map[$status['parent']]['substatus'][] = &$status;
		}
		$tree = $map['']['substatus'];

		// Make nice selectbox labels
		$this->status_labels = array();
		$this->make_status_labels($tree, $this->status_labels);

		// Sort Api\Config based on tree
		$sorted = array();
		foreach($this->status_labels as $status_id => $label)
		{
			$sorted[$status_id] = $this->status_labels_config[$status_id];
			//$sorted[$status_id]['name'] = $label;
			unset($sorted[$status_id]['substatus']);
		}
		$this->status_labels_config = $sorted;
	}

	/**
	 * Return evtl. existing sub-statuses of given status
	 *
	 * @param int $status
	 * @return array|int with sub-statuses incl. $status or just $status
	 */
	function get_sub_status($status)
	{
		if (!isset($this->status_labels_config)) $this->load_statuses();
		$stati = array($status);
		foreach($this->status_labels_config as $stat)
		{
			if ($stat['parent'] && in_array($stat['parent'], $stati))
			{
				$stati[] = $stat['id'];
			}
		}
		//error_log(__METHOD__."($status) returning ".array2string(count($stati) == 1 ? $status : $stati));
		return count($stati) == 1 ? $status : $stati;
	}

	/**
	 * Make nice labels with leading spaces depending on depth
	 *
	 * @param statuses List of statuses to process, with sub-statuses in a 'substatus' array
	 * @param labels Array of labels, pass array() and labels will be built in it
	 * @param depth Current depth
	 *
	 * @return None, labels are built in labels parameter
	 */
	protected function make_status_labels($statuses, &$labels, $depth=0)
	{
		foreach($statuses as $status)
		{
			$labels[$status['id']] = str_pad('',$depth*12, "&nbsp;",STR_PAD_LEFT).trim(str_replace('&nbsp;','',$status['name']));
			if(count($status['substatus']) > 0)
			{
				$this->make_status_labels($status['substatus'], $labels, $depth+1);
			}
		}
	}

	/**
	 * get list of specified grants as uid => Username pairs
	 *
	 * @param int $required =Acl::READ
	 * @param boolean $hide_deactive =null default only Acl::EDIT hides deactivates users
	 * @return array with uid => Username pairs
	 */
	function grant_list($required=Acl::READ, $hide_deactive=null)
	{
		if (!isset($hide_deactive)) $hide_deactive = $required == Acl::EDIT;

		$result = array();
		foreach($this->grants as $uid => $grant)
		{
			if ($grant & $required && (!$hide_deactive || Api\Accounts::getInstance()->is_active($uid)))
			{
				$result[$uid] = Api\Accounts::username($uid);
			}
		}
		natcasesort($result);

		return $result;
	}

	/**
	 * checks if the user has enough rights for a certain operation
	 *
	 * Rights are given via status Api\Config admin/noadmin
	 *
	 * @param array|int $data =null use $this->data or $this->data['ts_id'] (to fetch the data)
	 * @param int $user =null for which user to check, default current user
	 * @return boolean true if the rights are ok, false if no rights
	 */
	function check_statusForEditRights($data=null,$user=null)
	{
		if (is_null($data) || (int)$data == $this->data['ts_id'])
		{
			$data =& $this->data;
		}
		if (!is_array($data))
		{
			$save_data = $this->data;
			$data = $this->read($data,true);
			$this->data = $save_data;

			if (!$data) return null; 	// entry not found
		}
		if (!$user) $user = $this->user;
		if (!isset($GLOBALS['egw_info']['user']['apps']['admin']) && $data['ts_status'])
		{
			if ($this->status_labels_config[$data['ts_status']]['admin'])
			{
				return false;
			}
		}
		return true;
	}

	/**
	 * checks if the user has enough rights for a certain operation
	 *
	 * Rights are given via owner grants or role based Acl
	 *
	 * @param int $required Acl::READ, EGW_ACL_WRITE, Acl::ADD, Acl::DELETE, EGW_ACL_BUDGET, EGW_ACL_EDIT_BUDGET
	 * @param array|int $data =null project or project-id to use, default the project in $this->data
	 * @param int $user =null for which user to check, default current user
	 * @return boolean true if the rights are ok, null if not found, false if no rights
	 */
	function check_acl($required,$data=null,$user=null)
	{
		if (is_null($data) || (int)$data == $this->data['ts_id'])
		{
			$data =& $this->data;
		}
		if (!is_array($data))
		{
			$save_data = $this->data;
			$data = $this->read($data,true);
			$this->data = $save_data;

			if (!$data) return null; 	// entry not found
		}
		if (!$user) $user = $this->user;
		if ($user == $this->user)
		{
			$grants = $this->grants;
		}
		else
		{
			$grants = $GLOBALS['egw']->acl->get_grants(TIMESHEET_APP,true,$user);
		}
		$ret = $data && !!($grants[$data['ts_owner']] & $required);

		if(($required & Acl::DELETE) && $this->config_data['history'] == 'history' &&
			$data['ts_status'] == self::DELETED_STATUS)
		{
			$ret = !!($GLOBALS['egw_info']['user']['apps']['admin']);
		}
		//error_log(__METHOD__."($required,$data[ts_id],$user) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * return SQL implementing filtering by date
	 *
	 * @param string $name
	 * @param int &$start
	 * @param int &$end
	 * @return string
	 */
	function date_filter($name,&$start,&$end)
	{
		return Api\DateTime::sql_filter($name, $start, $end, 'ts_start', $this->date_filters);
	}

	/**
	 * search the timesheet
	 *
	 * reimplemented to limit result to users we have grants from
	 *
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean|string $only_keys =true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @param boolean $only_summary =false If true only return the sums as array with keys duration and price, default false
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false,$only_summary=false)
	{
		//error_log(__METHOD__."(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')");
		//echo "<p>".__METHOD__."(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";
		// postgres can't round from double precission, only from numeric ;-)
		$total_sql = $this->db->Type != 'pgsql' ? "round(ts_quantity*ts_unitprice,2)" : "round(cast(ts_quantity*ts_unitprice AS numeric),2)";

		if (!is_array($extra_cols))
		{
			$extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		}
		if ($only_keys === false || $this->show_sums && strpos($order_by,'ts_start') !== false)
		{
			$extra_cols[] = $total_sql.' AS ts_total';
		}
		if (!isset($filter['ts_owner']) || !count($filter['ts_owner']))
		{
			$filter['ts_owner'] = array_keys($this->grants);
		}
		else
		{
			if (!is_array($filter['ts_owner'])) $filter['ts_owner'] = array($filter['ts_owner']);

			foreach($filter['ts_owner'] as $key => $owner)
			{
				if (!isset($this->grants[$owner]))
				{
					unset($filter['ts_owner'][$key]);
				}
			}
		}
		if (isset($filter['ts_status']) && $filter['ts_status'] && $filter['ts_status'] != self::DELETED_STATUS)
		{
			$filter['ts_status'] = $this->get_sub_status($filter['ts_status']);
		}
		else
		{
			$filter[] = '(ts_status ' . ($filter['ts_status'] == self::DELETED_STATUS ? '=':'!= ') . self::DELETED_STATUS .
				($filter['ts_status'] == self::DELETED_STATUS ? '':' OR ts_status IS NULL') . ')';
		}
		if (!count($filter['ts_owner']))
		{
			$this->total = 0;
			$this->summary = array();
			return array();
		}
		if ($only_summary==false && $criteria && $this->show_sums)
		{
			// if we have a criteria AND intend to show the sums we first query the affected ids,
			// then we throw away criteria and filter, and replace the filter with the list of ids
			$ids = parent::search($criteria,'egw_timesheet.ts_id as id','','',$wildcard,$empty,$op,false,$filter,$join);
			//_debug_array($ids);
			if (empty($ids))
			{
				$this->summary = array('duration'=>0,'price'=>null,'quantity'=>0);
				return array();
			}
			unset($criteria);
			foreach ($ids as $v)
			{
				$id_filter[] = $v['id'];
			}
			$filter = array('ts_id'=>$id_filter);
		}
		// if we only want to return the summary (sum of duration and sum of price) we have to take care that the customfield table
		// is not joined, as the join causes a multiplication of the sum per customfield found
		// joining of the cutomfield table is triggered by criteria being set with either a string or an array
		$this->summary = parent::search($only_summary ? null : $criteria,
			"SUM(ts_duration) AS duration,SUM($total_sql) AS price,MAX(ts_modified) AS max_modified".
				($this->quantity_sum ? ",SUM(ts_quantity) AS quantity" : ''),
			'', '', $wildcard, $empty, $op, false,
			$only_summary && is_array($criteria) ? ($filter ? array_merge($criteria, (array)$filter) : $criteria) : $filter,
			$only_summary ? '' : $join);
		$this->summary = $this->summary[0];
		$this->summary['max_modified'] = Api\DateTime::server2user($this->summary['max_modified']);

		if ($only_summary) return $this->summary;

		if ($this->show_sums && strpos($order_by,'ts_start') !== false && 	// sums only make sense if ordered by ts_start
			$this->db->capabilities['union'] && ($from_unixtime_ts_start = $this->db->from_unixtime('ts_start')))
		{
			$sum_sql = array(
				'year'  => $this->db->date_format($from_unixtime_ts_start,'%Y'),
				'month' => $this->db->date_format($from_unixtime_ts_start,'%Y%m'),
				'week'  => $this->db->date_format($from_unixtime_ts_start,$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] == 'Sunday' ? '%X%V' : '%x%v'),
				'day'   => $this->db->date_format($from_unixtime_ts_start,'%Y-%m-%d'),
			);
			foreach($this->show_sums as $type)
			{
				$extra_cols[] = $sum_sql[$type].' AS ts_'.$type;
				$extra_cols[] = '0 AS is_sum_'.$type;
				$sum_extra_cols[] = str_replace('ts_start','MIN(ts_start)',$sum_sql[$type]);	// as we dont group by ts_start
				$sum_extra_cols[$type] = '0 AS is_sum_'.$type;
			}
			// regular entries
			parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,'UNION',$filter,$join,$need_full_no_count);

			$sort = substr($order_by,8);
			$union_order = array();
			$sum_ts_id = array('year' => -3,'month' => -2,'week' => -1,'day' => 0);
			foreach($this->show_sums as $type)
			{
				$union_order[] = 'ts_'.$type . ' ' . $sort;
				$union_order[] = 'is_sum_'.$type;
				$sum_extra_cols[$type]{0} = '1';
				// the $type sum
				parent::search($criteria,array(
					(string)$sum_ts_id[$type],"''","''","''",'MIN(ts_start)','SUM(ts_duration) AS ts_duration',
					($this->quantity_sum ? "SUM(ts_quantity) AS ts_quantity" : '0'),
					'0','NULL','0','0','0','0','0',"SUM($total_sql) AS ts_total"
				),'GROUP BY '.$sum_sql[$type],$sum_extra_cols,$wildcard,$empty,$op,'UNION',$filter,$join,$need_full_no_count);
				$sum_extra_cols[$type]{0} = '0';
			}
			$union_order[] = 'ts_start '.$sort;
			return parent::search('','',implode(',',$union_order),'','',false,'',$start);
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}

	/**
	 * read a timesheet entry
	 *
	 * @param int $ts_id
	 * @param boolean $ignore_acl =false should the Acl be checked
	 * @return array|boolean array with timesheet entry, null if timesheet not found or false if no rights
	 */
	function read($ts_id,$ignore_acl=false)
	{
		//error_log(__METHOD__."($ts_id,$ignore_acl) ".function_backtrace());
		if (!(int)$ts_id || (int)$ts_id != $this->data['ts_id'] && !parent::read($ts_id))
		{
			return null;	// entry not found
		}
		if (!$ignore_acl && !($ret = $this->check_acl(Acl::READ)))
		{
			return false;	// no read rights
		}
		return $this->data;
	}

	/**
	 * saves a timesheet entry
	 *
	 * reimplemented to notify the link-class
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param boolean $touch_modified =true should modification date+user be set, default yes
	 * @param boolean $ignore_acl =false should the Acl be checked, returns true if no edit-rigts
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$touch_modified=true,$ignore_acl=false)
	{
		if ($keys) $this->data_merge($keys);

		if (!$ignore_acl && $this->data['ts_id'] && !$this->check_acl(Acl::EDIT))
		{
			return true;
		}
		if ($touch_modified)
		{
			$this->data['ts_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['ts_modified'] = $this->now;
			$this->user = $this->data['ts_modifier'];
		}

		// check if we have a real modification of an existing record
		if ($this->data['ts_id'])
		{
			$new =& $this->data;
			unset($this->data);
			$this->read($new['ts_id']);
			$old =& $this->data;
			$this->data =& $new;
			$changed = array();
			if (isset($old)) foreach($old as $name => $value)
			{
				if (isset($new[$name]) && $new[$name] != $value) $changed[] = $name;
			}
		}
		if (isset($old) && !$changed)
		{
			return false;
		}

		// Check for restore of deleted contact, restore held links
		if($old && $old['ts_status'] == self::DELETED_STATUS && $new['ts_status'] != self::DELETED_STATUS)
		{
			Link::restore(TIMESHEET_APP, $new['ts_id']);
		}

		if (!is_object($this->tracking))
		{
			$this->tracking = new timesheet_tracking($this);

			$this->tracking->html_content_allow = true;
		}
		if ($this->tracking->track($this->data,$old,$this->user) === false)
		{
			return implode(', ',$this->tracking->errors);
		}
		if (!($err = parent::save()))
		{
			// notify the link-class about the update, as other apps may be subscribt to it
			Link::notify_update(TIMESHEET_APP,$this->data['ts_id'],$this->data);
		}

		return $err;
	}

	/**
	 * deletes a timesheet entry identified by $keys or the loaded one, reimplemented to notify the link class (unlink)
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @param boolean $ignore_acl =false should the Acl be checked, returns false if no delete-rigts
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null,$ignore_acl=false)
	{
		if (!is_array($keys) && (int) $keys)
		{
			$keys = array('ts_id' => (int) $keys);
		}
		$ts_id = is_null($keys) ? $this->data['ts_id'] : $keys['ts_id'];

		if (!$ignore_acl && !$this->check_acl(Acl::DELETE,$ts_id) || !($old = $this->read($ts_id)))
		{
			return false;
		}

		// check if we only mark timesheets as deleted, or really delete them
		if ($old['ts_owner'] && $this->config_data['history'] != '' && $old['ts_status'] != self::DELETED_STATUS)
		{
			$delete = $old;
			$delete['ts_status'] = self::DELETED_STATUS;
			$ret = !($this->save($delete));
			Link::unlink(0,TIMESHEET_APP,$ts_id,'','','',true);
		}
		elseif (($ret = parent::delete($keys)) && $ts_id)
		{
			// delete all links to timesheet entry $ts_id
			Link::unlink(0,TIMESHEET_APP,$ts_id);
		}
		return $ret;
	}

	/**
	 * delete / move all timesheets of a given user
	 *
	 * @param array $data
	 * @param int $data['account_id'] owner to change
	 * @param int $data['new_owner']  new owner or 0 for delete
	 */
	function deleteaccount($data)
	{
		$account_id = $data['account_id'];
		$new_owner =  $data['new_owner'];

		if (!$new_owner)
		{
			Link::unlink(0, TIMESHEET_APP, '', $account_id);
			parent::delete(array('ts_owner' => $account_id));
		}
		else
		{
			$this->db->update($this->table_name, array(
				'ts_owner' => $new_owner,
			), array(
				'ts_owner' => $account_id,
			), __LINE__, __FILE__, TIMESHEET_APP);
		}
	}

	/**
	 * set a status for timesheet entry identified by $keys
	 *
	 * @param array $keys =null if given array with col => value pairs to characterise single timesheet or null for $this->data
	 * @param int $status =0
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function set_status($keys=null, $status=0)
	{
		$ret = true;
		if (!is_array($keys) && (int) $keys)
		{
			$keys = array('ts_id' => (int) $keys);
		}
		$ts_id = is_null($keys) ? $this->data['ts_id'] : $keys['ts_id'];

		if (!$this->check_acl(Acl::EDIT,$ts_id) || !$this->read($ts_id,true))
		{
			return false;
		}

		$this->data['ts_status'] = $status;
		if ($this->save($ts_id)!=0) $ret = false;

		return $ret;
	}

	/**
	 * Get the time-, price-, quantity-sum and max. modification date for the given timesheet entries
	 *
	 * @param array $ids array of timesheet id's
	 * @return array with values for keys "duration", "price", "max_modified" and "quantity"
	 */
	function sum($ids)
	{
		if (!$ids)
		{
			return array('duration' => 0, 'quantity' => 0, 'price' => 0, 'max_modified' => null);
		}
		return $this->search(array('ts_id'=>$ids),true,'','','',false,'AND',false,null,'',false,true);
	}

	/**
	 * get title for a timesheet entry identified by $entry
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param int|array $entry int ts_id or array with timesheet entry
	 * @return string/boolean string with title, null if timesheet not found, false if no perms to view it
	 */
	function link_title( $entry )
	{
		if (!is_array($entry))
		{
			// need to preserve the $this->data
			$backup =& $this->data;
			unset($this->data);
			$entry = $this->read( $entry,false,false);
			// restore the data again
			$this->data =& $backup;
		}
		if (!$entry)
		{
			return $entry;
		}
		$format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
		if (date('H:i',$entry['ts_start']) != '00:00')	// dont show 00:00 time, as it means date only
		{
			$format .= ' '.($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i');
		}
		return date($format,$entry['ts_start']).': '.$entry['ts_title'];
	}

	/**
	 * get title for multiple timesheet entries identified by $ids
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param array $ids array with ts_id's
	 * @return array with titles, see link_title
	 */
	function link_titles( array $ids )
	{
		$titles = array();
		if (($entries = $this->search(array('ts_id' => $ids),'ts_id,ts_title,ts_start')))
		{
			foreach($entries as $entry)
			{
				$titles[$entry['ts_id']] = $this->link_title($entry);
			}
		}
		// we assume all not returned entries are not readable by the user, as we notify Link about all deletes
		foreach($ids as $id)
		{
			if (!isset($titles[$id]))
			{
				$titles[$id] = false;
			}
		}
		return $titles;
	}

	/**
	 * query timesheet for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @param array $options Array of options for the search
	 * @return array with ts_id - title pairs of the matching entries
	 */
	function link_query( $pattern, Array &$options = array() )
	{
		$limit = false;
		$need_count = false;
		if($options['start'] || $options['num_rows']) {
			$limit = array($options['start'], $options['num_rows']);
			$need_count = true;
		}
		$result = array();
		foreach((array) $this->search($pattern,false,'','','%',false,'OR', $limit, null, '', $need_count) as $ts )
		{
			if ($ts) $result[$ts['ts_id']] = $this->link_title($ts);
		}
		$options['total'] = $need_count ? $this->total : count($result);
		return $result;
	}

	/**
	 * Check access to the file store
	 *
	 * @param int|array $id id of entry or entry array
	 * @param int $check Acl::READ for read and Acl::EDIT for write or delete access
	 * @param string $rel_path =null currently not used in InfoLog
	 * @param int $user =null for which user to check, default current user
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path=null,$user=null)
	{
		unset($rel_path);	// not used, but required by function signature

		return $this->check_acl($check,$id,$user);
	}

	/**
	 * updates the project titles in the timesheet application (called whenever a project name is changed in the project manager)
	 *
	 * Todo: implement via notification
	 *
	 * @param string $oldtitle => the origin title of the project
	 * @param string $newtitle => the new title of the project
	 * @return boolean true for success, false for invalid parameters
	 */
	 function update_ts_project($oldtitle='', $newtitle='')
	 {
		if(strlen($oldtitle) > 0 && strlen($newtitle) > 0)
		{
			$this->db->update('egw_timesheet',array(
				'ts_project' => $newtitle,
				'ts_title' => $newtitle,
			),array(
				'ts_project' => $oldtitle,
			),__LINE__,__FILE__,TIMESHEET_APP);

			return true;
		}
		return false;
	 }

	/**
	 * returns array with relation link_id and ts_id (necessary for project-selection)
	 *
	 * @param int $pm_id ID of selected project
	 * @return array containing link_id and ts_id
	 */
	function get_ts_links($pm_id=0)
	{
		if($pm_id && isset($GLOBALS['egw_info']['user']['apps']['projectmanager']))
		{
			$pm_ids = ExecMethod('projectmanager.projectmanager_bo.children',$pm_id);
			$pm_ids[] = $pm_id;
			$links = Link\Storage::get_links('projectmanager',$pm_ids,'timesheet');	// Link\Storage::get_links not egw_links::get_links!
			if ($links)
			{
				$links = array_unique(call_user_func_array('array_merge',$links));
			}
			return $links;
		}
		return array();
	}

	/**
	 * receives notifications from the link-class: new, deleted links to timesheets, or updated content of linked entries
	 *
	 * Function makes sure timesheets linked or unlinked to projects via projectmanager behave like ones
	 * linked via timesheets project-selector, thought timesheet only stores project-title, not the id!
	 *
	 * @param array $data array with keys type, id, target_app, target_id, link_id, data
	 */
	function notify($data)
	{
		//error_log(__METHOD__.'('.array2string($data).')');
		$backup =& $this->data;	// backup internal data in case class got re-used by ExecMethod
		unset($this->data);

		if ($data['target_app'] == 'projectmanager' && $this->read($data['id']))
		{
			$old_title = isset($data['data']) ? $data['data'][Link::OLD_LINK_TITLE] : null;
			switch($data['type'])
			{
				case 'link':
				case 'update':
					if (empty($this->data['ts_project']) ||	// timesheet has not yet project set --> set just linked one
						isset($old_title) && $this->data['ts_project'] === $old_title)
					{
						$pm_id = $data['target_id'];
						$update['ts_project'] = Link::title('projectmanager', $pm_id);
						if (isset($old_title) && $this->data['ts_title'] === $old_title)
						{
							$update['ts_title'] = $update['ts_project'];
						}
					}
					break;

				case 'unlink':	// if current project got unlinked --> unset it
					if ($this->data['ts_project'] == projectmanager_bo::link_title($data['target_id']))
					{
						$pm_id = 0;
						$update['ts_project'] = null;

					}
					break;
			}
			if (isset($update))
			{
				$this->update($update);
				// do NOT notify about title-change, as this will lead to an infinit loop!
				// Link::notify_update(TIMESHEET_APP, $this->data['ts_id'],$this->data);
				//error_log(__METHOD__."() setting pm_id=$pm_id --> ".array2string($update));
			}
		}
		if ($backup) $this->data = $backup;
	}


	/**
	 * changes the data from the db-format to your work-format
	 *
	 * Reimplemented to store just ts_project in db, but have pm_id and ts_project in memory,
	 * with ts_project only set, if it contains a custom project name.
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}
		// get pm_id from links and ts_project: either project matching ts_project or first found project
		if (!isset($data['pm_id']) && $data['ts_id'])
		{
			$first_pm_id = null;
			foreach(Link::get_links('timesheet', $data['ts_id'], 'projectmanager') as $pm_id)
			{
				if (!isset($first_pm_id)) $first_pm_id = $pm_id;
				if ($data['ts_project'] == Link::title('projectmanager', $pm_id))
				{
					$data['pm_id'] = $pm_id;
					$data['ts_project_blur'] = $data['ts_project'];
					$data['ts_project'] = '';
					break;
				}
			}
			if (!isset($data['pm_id']) && isset($first_pm_id)) $data['pm_id'] = $first_pm_id;
		}
		elseif ($data['ts_id'] && $data['pm_id'] && Link::title('projectmanager', $data['pm_id']) == $data['ts_project'])
		{
			$data['ts_project_blur'] = $data['ts_project'];
			$data['ts_project'] = '';
		}
		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * Reimplemented to store just ts_project in db, but have pm_id and ts_project in memory,
	 * with ts_project only set, if it contains a custom project name.
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (($intern = !is_array($data)))
		{
			$data =& $this->data;
		}
		// allways store ts_project to be able to search for it, even if no custom project is set
		if (empty($data['ts_project']) && !is_null($data['ts_project']))
		{
			$data['ts_project'] = $data['pm_id'] ? Link::title('projectmanager', $data['pm_id']) : '';
		}
		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	}
}
