<?php
/**
 * API - Record history logging
 *
 * This class extends a backend class (at them moment SQL or LDAP) and implements some
 * caching on to top of the backend functions. The cache is share for all instances of
 * the accounts class and for LDAP it is persistent through the whole session, for SQL
 * it's only on a per request basis.
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @copyright 2001 by Joseph Engo <jengo@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> new DB-methods and search
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @access public
 * @version $Id$
 */

/**
 * Record history logging service
 *
 * This class need to be instanciated for EACH app, which wishes to use it!
 */
class historylog
{
	/**
	 * Reference to the global db object
	 *
	 * @var egw_db
	 */
	var $db;
	const TABLE = 'egw_history_log';
	/**
	 * App.name this class is instanciated for / working on
	 *
	 * @var string
	 */
	var $appname;
	/**
	 * offset in secconds between user and server-time,
	 *	it need to be add to a server-time to get the user-time or substracted from a user-time to get the server-time
	 *
	 * @var int
	 */
	var $tz_offset_s;
	var $user;
	var $template;
	var $nextmatchs;
	var $types = array(
		'C' => 'Created',
		'D' => 'Deleted',
		'E' => 'Edited'
	);
	var $alternate_handlers = array();

	/**
	 * Constructor
	 *
	 * @param string $appname app name this instance operates on
	 * @return historylog
	 */
	function historylog($appname='',$user=null)
	{
		$this->appname = $appname ? $appname : $GLOBALS['egw_info']['flags']['currentapp'];
		$this->user = !is_null($user) ? $user : $GLOBALS['egw_info']['user']['account_id'];

		if (is_object($GLOBALS['egw_setup']->db))
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw']->db;
		}
		$this->tz_offset_s = $GLOBALS['egw']->datetime->tz_offset;
	}

	/**
	 * Delete the history-log of one or multiple records of $this->appname
	 *
	 * @param int/array $record_id one or more id's of $this->appname, or null to delete ALL records of $this->appname
	 * @return int number of deleted records/rows (0 is not necessaryly an error, it can just mean there's no record!)
	 */
	function delete($record_id)
	{
		$where = array('history_appname' => $this->appname);

		if (is_array($record_id) || is_numeric($record_id))
		{
			$where['history_record_id'] = $record_id;
		}
		$this->db->delete(self::TABLE,$where,__LINE__,__FILE__);

		return $this->db->affected_rows();
	}

	/**
	 * Add a history record, if $new_value != $old_value
	 *
	 * @param string $status 2 letter code: eg. $this->types: C=Created, D=Deleted, E=Edited
	 * @param int $record_id it of the record in $this->appname (set by the constructor)
	 * @param string $new_value new value
	 * @param string $old_value old value
	 */
	function add($status,$record_id,$new_value,$old_value)
	{
		if ($new_value != $old_value)
		{
			$this->db->insert(self::TABLE,array(
				'history_record_id' => $record_id,
				'history_appname'   => $this->appname,
				'history_owner'     => $this->user,
				'history_status'    => $status,
				'history_new_value' => $new_value,
				'history_old_value' => $old_value,
				'history_timestamp' => time(),
			),false,__LINE__,__FILE__);
		}
	}

	/**
	 * Search history-log
	 *
	 * @param array/int $filter array with filters, or int record_id
	 * @param string $order='history_id' sorting after history_id is identical to history_timestamp
	 * @param string $sort='ASC'
	 * @return array of arrays with keys id, record_id, appname, owner (account_id), status, new_value, old_value,
	 * 	timestamp (Y-m-d H:i:s in servertime), user_ts (timestamp in user-time)
	 */
	function search($filter,$order='history_id',$sort='DESC')
	{
		if (!is_array($filter)) $filter = (int)$filter ? array('history_record_id' => $filter) : array();

		if (!$_orderby || !preg_match('/^[a-z0-9_]+$/i',$_orderby) || !preg_match('/^(asc|desc)?$/i',$sort))
		{
			$orderby = 'ORDER BY history_id DESC';
		}
		else
		{
			$orderby = "ORDER BY $_orderby $sort";
		}
		foreach($filter as $col => $value)
		{
			if (!is_numeric($col) && substr($col,0,8) != 'history_')
			{
				$filter['history_'.$col] = $value;
				unset($filter[$col]);
			}
		}
		if (!isset($filter['history_appname'])) $filter['history_appname'] = $this->appname;

		$rows = array();
		foreach($this->db->select(self::TABLE,'*',$filter,__LINE__,__FILE__,false,$orderby) as $row)
		{
			$row['user_ts'] = $this->db->from_timestamp($row['history_timestamp']) + 3600 * $GLOBALS['egw_info']['user']['preferences']['common']['tz_offset'];
			$rows[] = egw_db::strip_array_keys($row,'history_');
		}
		return $rows;
	}

	/**
	 * return history-log for one record of $this->appname
	 *
	 * @deprecated use search
	 * @param array $filter_out stati to NOT show
	 * @param array $only_show stati to show
	 * @param string $_orderby column name to order, default history_timestamp,history_id
	 * @param string $sort ASC,DESC
	 * @param int $record_id id of the record in $this->appname (set by the constructor)
	 * @return array of arrays with keys id, record_id, owner (account_lid!), status, new_value, old_value, datetime (timestamp in servertime)
	 */
	function return_array($filter_out,$only_show,$_orderby,$sort, $record_id)
	{

		if (!$_orderby || !preg_match('/^[a-z0-9_]+$/i',$_orderby) || !preg_match('/^(asc|desc)?$/i',$sort))
		{
			$orderby = 'ORDER BY history_timestamp,history_id';
		}
		else
		{
			$orderby = "ORDER BY $_orderby $sort";
		}

		$where = array(
			'history_appname'   => $this->appname,
			'history_record_id' => $record_id,
		);
		if (is_array($filter_out))
		{
			foreach($filter_out as $_filter)
			{
				$where[] = 'history_status != '.$this->db->quote($_filter);
			}
		}
		if (is_array($only_show) && count($only_show))
		{
			$to_or = array();
			foreach($only_show as $_filter)
			{
				$to_or[] = 'history_status = '.$this->db->quote($_filter);
			}
			$where[] = '('.implode(' OR ',$to_or).')';
		}

		foreach($this->db->select(self::TABLE,'*',$where,__LINE__,__FILE__,false,$orderby) as $row)
		{
			$return_values[] = array(
				'id'         => $row['history_id'],
				'record_id'  => $row['history_record_id'],
				'owner'      => $row['history_owner'] ? $GLOBALS['egw']->accounts->id2name($row['history_owner']) : lang('eGroupWare'),
				'status'     => str_replace(' ','',$row['history_status']),
				'new_value'  => $row['history_new_value'],
				'old_value'  => $row['history_old_value'],
				'datetime'   => $this->db->from_timestamp($row['history_timestamp']),
			);
		}
		return $return_values;
	}

	/**
	 * Creates html to show the history-log of one record
	 *
	 * @deprecated use eg. the historylog_widget of eTemplate or your own UI
	 * @param array $filter_out see stati to NOT show
	 * @param string $orderby column-name to order by
	 * @param string $sort ASC, DESC
	 * @param int $record_id id of the record in $this->appname (set by the constructor)
	 * @return string the html
	 */
	function return_html($filter_out,$orderby,$sort, $record_id)
	{
		$this->template   =& CreateObject('phpgwapi.Template',EGW_TEMPLATE_DIR);
		$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');

		$this->template->set_file('_history','history_list.tpl');

		$this->template->set_block('_history','row_no_history');
		$this->template->set_block('_history','list');
		$this->template->set_block('_history','row');

		$this->template->set_var('lang_user',lang('User'));
		$this->template->set_var('lang_date',lang('Date'));
		$this->template->set_var('lang_action',lang('Action'));
		$this->template->set_var('lang_new_value',lang('New Value'));

		$this->template->set_var('th_bg',$GLOBALS['egw_info']['theme']['th_bg']);
		$this->template->set_var('sort_date',lang('Date'));
		$this->template->set_var('sort_owner',lang('User'));
		$this->template->set_var('sort_status',lang('Status'));
		$this->template->set_var('sort_new_value',lang('New value'));
		$this->template->set_var('sort_old_value',lang('Old value'));

		$values = $this->return_array($filter_out,array(),$orderby,$sort,$record_id);

		if (!is_array($values))
		{
			$this->template->set_var('tr_color',$GLOBALS['egw_info']['theme']['row_off']);
			$this->template->set_var('lang_no_history',lang('No history for this record'));
			$this->template->fp('rows','row_no_history');
			return $this->template->fp('out','list');
		}

		foreach($values as $value)
		{
			$this->nextmatchs->template_alternate_row_color($this->template);

			$this->template->set_var('row_date',$GLOBALS['egw']->common->show_date($value['datetime']));
			$this->template->set_var('row_owner',$value['owner']);

			if ($this->alternate_handlers[$value['status']])
			{
				eval('\$s = ' . $this->alternate_handlers[$value['status']] . '(' . $value['new_value'] . ');');
				$this->template->set_var('row_new_value',$s);
				unset($s);

				eval('\$s = ' . $this->alternate_handlers[$value['status']] . '(' . $value['old_value'] . ');');
				$this->template->set_var('row_old_value',$s);
				unset($s);
			}
			else
			{
				$this->template->set_var('row_new_value',$value['new_value']);
				$this->template->set_var('row_old_value',$value['old_value']);
			}

			$this->template->set_var('row_status',$this->types[$value['status']]);

			$this->template->fp('rows','row',True);
		}
		return $this->template->fp('out','list');
	}
}
