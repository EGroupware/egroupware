<?php
/**
 * API - Record history logging
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

use EGroupware\Api;

/**
 * Record history logging service
 *
 * This class need to be instanciated for EACH app, which wishes to use it!
 */
class historylog extends Api\Storage\History
{
	var $template;
	var $nextmatchs;
	var $alternate_handlers = array();

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
		if (!is_numeric($record_id))
		{
			return array();
		}
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
		$this->template   = new Template(EGW_TEMPLATE_DIR);
		$this->nextmatchs = new nextmatchs();

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

			$this->template->set_var('row_date',common::show_date($value['datetime']));
			$this->template->set_var('row_owner',$value['owner']);

			if ($this->alternate_handlers[$value['status']])
			{
				$this->template->set_var('row_new_value',
					call_user_func($this->alternate_handlers[$value['status']], array($value['new_value'])));

				$this->template->set_var('row_old_value',
					call_user_func($this->alternate_handlers[$value['status']], array($value['old_value'])));
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
