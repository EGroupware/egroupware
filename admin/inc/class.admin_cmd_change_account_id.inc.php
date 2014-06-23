<?php
/**
 * eGgroupWare admin - admin command: change an account_id
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */


/**
 * admin command: change an account_id
 */
class admin_cmd_change_account_id extends admin_cmd
{
	/**
	 * Constructor
	 *
	 * @param array $change array with old => new id pairs
	 */
	function __construct(array $change)
	{
		if (!isset($change['change']))
		{
			$change = array(
				'change' => $change,
			);
		}
		admin_cmd::__construct($change);
	}

	/**
	 * Query changes from all apps
	 *
	 * Apps mark columns containing account-ids in "meta" attribute as (account|user|group)[-(abs|commasep|serialized)]
	 *
	 * @return array appname => array( table => array(column(s)))
	 */
	private function get_changes()
	{
		$changes = array();
		foreach($GLOBALS['egw_info']['apps'] as $app => $app_data)
		{
			if (!file_exists($path=EGW_SERVER_ROOT.'/'.$app.'/setup/setup.inc.php') || !include($path)) continue;

			foreach((array)$setup_info[$app]['tables'] as $table)
			{
				if (!($definition = $GLOBALS['egw']->db->get_table_definitions($app, $table))) continue;

				$cf = array();
				foreach($definition['fd'] as $col => $data)
				{
					if (!empty($data['meta']))
					{
						foreach((array)$data['meta'] as $key => $val)
						{
							unset($subtype);
							list($type, $subtype) = explode('-', $val);
							if (in_array($type, array('account', 'user', 'group')))
							{
								if (!is_numeric($key) || !empty($subtype))
								{
									$col = array($col);
									if (!is_numeric($key)) $col[] = $key;
									if (!empty($subtype)) $col['.type'] = $subtype;
								}
								$changes[$app][$table][] = $col;
							}
							if (in_array($type, array('cfname', 'cfvalue')))
							{
								$cf[$type] = $col;
							}
						}
					}
				}
				// we have a custom field table and cfs containing accounts
				if ($cf && !empty($cf['cfname']) && !empty($cf['cfvalue']) &&
					($account_cfs = egw_customfields::get_account_cfs($app == 'phpgwapi' ? 'addressbook' : $app)))
				{
					foreach($account_cfs as $type => $names)
					{
						unset($subtype);
						list($type, $subtype) = explode('-', $type);
						$col = array($cf['cfvalue']);
						if (!empty($subtype)) $col['.type'] = $subtype;
						$col[$cf['cfname']] = $names;
						$changes[$app][$table][] = $col;
					}
				}
			}
			if (isset($changes[$app])) ksort($changes[$app]);
		}
		ksort($changes);
		//print_r($changes);
		return $changes;
	}

	/**
	 * give or remove run rights from a given account and application
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws egw_exception_no_admin
	 * @throws egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws egw_exception_wrong_userinput(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
	 */
	protected function exec($check_only=false)
	{
		foreach($this->change as $from => $to)
		{
			if (!(int)$from || !(int)$to)
			{
				throw new egw_exception_wrong_userinput(lang("Account-id's have to be integers!"),16);
			}
			if (($from < 0) != ($to < 0))
			{
				throw new egw_exception_wrong_userinput(lang("Can NOT change users into groups, same sign required!"),17);
			}
			if (!($from_exists = $GLOBALS['egw']->accounts->exists($from)))
			{
				throw new egw_exception_wrong_userinput(lang("Source account #%1 does NOT exist!", $from),18);
			}
			if ($from_exists !== ($from > 0 ? 1 : 2))
			{
				throw new egw_exception_wrong_userinput(lang("Group #%1 must have negative sign!", $from),19);
			}
			if ($GLOBALS['egw']->accounts->exists($to) && !isset($this->change[$to]))
			{
				throw new egw_exception_wrong_userinput(lang("Destination account #%1 does exist and is NOT renamed itself! Can not merge accounts, it will violate unique contains. Delete with transfer of data instead.", $to),20);
			}
		}
		$columns2change = $this->get_changes();
		$total = 0;
		foreach($columns2change as $app => $data)
		{
			if (!isset($GLOBALS['egw_info']['apps'][$app])) continue;	// $app is not installed

			$db = clone($GLOBALS['egw']->db);
			$db->set_app($app);
			if ($check_only) $db->log_updates = true;

			foreach($data as $table => $columns)
			{
				$db->column_definitions = $db->get_table_definitions($app,$table);
				$db->column_definitions = $db->column_definitions['fd'];
				if (!$columns)
				{
					echo "$app: $table no columns with account-id's\n";
					continue;	// noting to do for this table
				}
				if (!is_array($columns)) $columns = array($columns);

				foreach($columns as $column)
				{
					$type = $where = null;
					if (is_array($column))
					{
						$type = $column['.type'];
						unset($column['.type']);
						$where = $column;
						$column = array_shift($where);
					}
					$total += ($changed = self::_update_account_id($this->change,$db,$table,$column,$where,$type));
					if (!$check_only && $changed) echo "$app:\t$table.$column $changed id's changed\n";
				}
			}
		}
		if (!$check_only)
		{
			foreach($GLOBALS['egw_info']['apps'] as $app => $data)
			{
				$total += ($changed = egw_customfields::change_account_ids($app, $this->change));
				if ($changed) echo "$app:\t$changed id's in definition of private custom fields changed\n";
			}
		}
		if ($total) egw_cache::flush(egw_cache::INSTANCE);

		return lang("Total of %1 id's changed.",$total)."\n";
	}

	/**
	 * Update DB with changed account ids
	 *
	 * @param array $ids2change from-id => to-id pairs
	 * @param egw_db $db
	 * @param string $table
	 * @param string $column
	 * @param array $where
	 * @param string $type
	 * @return int number of changed ids
	 */
	private static function _update_account_id(array $ids2change,egw_db $db,$table,$column,array $where=null,$type=null)
	{
		$update_sql = '';
		foreach($ids2change as $from => $to)
		{
			$update_sql .= "WHEN ".$db->quote($from,$db->column_definitions[$column]['type'])." THEN ".$db->quote($to,$db->column_definitions[$column]['type'])." ";
		}
		$update_sql .= 'END';
		$update_sql_prefs .= 'END';
		if ($update_sql_abs) $update_sql_abs .= 'END';

		switch($type)
		{
			case 'commasep':
			case 'serialized':
				if (!$where) $where = array();
				$select = $where;
				$select[] = "$column IS NOT NULL";
				$select[] = "$column != ''";
				$change = array();
				foreach($db->select($table,'DISTINCT '.$column,$select,__LINE__,__FILE__) as $row)
				{
					$ids = $type != 'serialized' ? explode(',',$old_ids=$row[$column]) : unserialize($old_ids=$row[$column]);
					foreach($ids as $key => $id)
					{
						if (isset($ids2change[$id])) $ids[$key] = $ids2change[$id];
					}
					$ids = $type != 'serialized' ? implode(',',$ids) : serialize($ids);
					if ($ids != $old_ids)
					{
						$change[$old_ids] = $ids;
					}
				}
				$changed = 0;
				foreach($change as $from => $to)
				{
					$db->update($table,array($column=>$to),$where+array($column=>$from),__LINE__,__FILE__);
					$changed += $db->affected_rows();
				}
				break;

			case 'abs':
				if (!$where) $where = array();
				$where[$column] = array();
				foreach($ids2change as $from => $to)
				{
					$where[$column][] = abs($from);
				}
				$db->update($table,$column.'= CASE '.$column.' '.preg_replace('/-([0-9]+)/','\1',$update_sql),$where,__LINE__,__FILE__);
				$changed = $db->affected_rows();
				break;

			case 'prefs':	// prefs groups are shifted down by 2 as -1 and -2 are for default and forced prefs
				if (!$where) $where = array();
				$where[$column] = array();
				$update_sql = '';
				foreach($ids2change as $from => $to)
				{
					if ($from < 0) $from -= 2;
					if ($to < 0) $to -= 2;
					$where[$column][] = $from;
					$update_sql .= 'WHEN '.$db->quote($from,$db->column_definitions[$column]['type']).' THEN '.$db->quote($to,$db->column_definitions[$column]['type']).' ';
				}
				$db->update($table,$column.'= CASE '.$column.' '.$update_sql.'END',$where,__LINE__,__FILE__);
				$changed = $db->affected_rows();
				break;

			default:
				if (!$where) $where = array();
				$where[$column] = array_keys($ids2change);
				$db->update($table,$column.'= CASE '.$column.' '.$update_sql,$where,__LINE__,__FILE__);
				$changed = $db->affected_rows();
				break;
		}
		return $changed;
	}

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		$change = array();
		foreach($this->change as $from => $to) $change[] = $from.'->'.$to;

		return lang('Change account_id').': '.implode(', ',$change);
	}
}
