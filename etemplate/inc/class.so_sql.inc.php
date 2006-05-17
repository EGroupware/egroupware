<?php
/**
 * eGroupWare generalized SQL Storage Object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * generalized SQL Storage Object
 *
 * the class can be used in following ways:
 * 1) by calling the constructor with an app and table-name or
 * 2) by setting the following documented class-vars in a class derifed from this one
 * Of cause can you derife the class and call the constructor with params.
 *
 * @package etemplate
 * @subpackage api
 * @author RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class so_sql
{
 	/** 
 	 * @var string $table_name need to be set in the derived class to the db-table-name 
 	 */
	var $table_name;
 	/**
 	 * @var string $autoinc_id db-col-name of autoincrement id or ''
 	 */
	var $autoinc_id = '';
	/**
	 * @var array $non_db_cols all cols in data which are not (direct)in the db, for data_merge
	 */
	var $non_db_cols = array();
	/**
	 * @var int $debug=0 4 turns on the so_sql debug-messages
	 */
	var $debug = 0;
	/**
	 * @var string $empty_on_write string to be written to db if a col-value is '', eg. "''" or 'NULL' (default)
	 */
	var $empty_on_write = 'NULL';
	/**
	 * @var int/boolean $total total number of entries of last search with start != false
	 */
	var $total = false;
	/**
	 * @var db-object $db privat instance of the db-object
	 */
	var $db;
	/**
	 * @var array $db_uni_cols unique keys/index, set by derived class or via so_sql($app,$table)
	 */
	var $db_uni_cols = array();
	/**
	 * @var array $db_key_cols db-col-name / internal-name pairs, set by derived calls or via so_sql($app,$table) 
	 */
	var $db_key_cols = array();
	/**
	 * @var array $db_data_cols db-col-name / internal-name pairs, set by derived calls or via so_sql($app,$table) 
	 */
	var $db_data_cols = array();
	/**
	 * @var array $db_cols all columns = $db_key_cols + $db_data_cols, set in the constructor
	 */
	var $db_cols = array();
	/**
	 * @var array $this->table_def eGW table definition
	 */
	var $table_def = array();
	/**
	 * @var array $data holds the content of all columns
	 */
	var $data = array();
	/**
	 * @deprecated  a SO class dont need to and should NOT export functions (make them callable via menuaction)
	 * @var array $public_functions
	 */
	var $public_functions = array();

	/**
	 * constructor of the class
	 *
	 * NEED to be called from the constructor of the derived class !!!
	 *
	 * @param string $app should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param string $table should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param object/db $db database object, if not the one in $GLOBALS['egw']->db should be used, eg. for an other database
	 * @param string $colum_prefix='' column prefix to automatic remove from the column-name, if the column name starts with it
	 */
	function so_sql($app='',$table='',$db=null,$column_prefix='')
	{
		$this->db = is_object($db) ? clone($db) : clone($GLOBALS['egw']->db);
		$this->db_cols = $this->db_key_cols + $this->db_data_cols;

		if ($app)
		{
			$this->db->set_app($app);

			if ($table) $this->setup_table($app,$table,$column_prefix);
		}
		$this->init();

		if ((int) $this->debug >= 4)
		{
			echo "<p>so_sql('$app','$table')</p>\n";
			_debug_array($this);
		}
	}

	/**
	 * sets up the class for an app and table (by using the table-definition of $app/setup/tables_current.inc.php
	 *
	 * If you need a more complex conversation then just removing the column_prefix, you have to do so in a derifed class !!!
	 *
	 * @param string $app app-name $table belongs too
	 * @param string $table table-name
	 * @param string $colum_prefix='' column prefix to automatic remove from the column-name, if the column name starts with it
	 */
	function setup_table($app,$table,$colum_prefix='')
	{
		$this->table_name = $table;
		$this->table_def = $this->db->get_table_definitions($app,$table);
		if (!$this->table_def || !is_array($this->table_def['fd']))
		{
			echo "<p>so_sql::setup_table('$app','$table'): No table definitions found !!!<br>\n".function_backtrace()."</p>\n";
		}
		$this->db_key_cols = $this->db_data_cols = $this->db_cols = array();
		$this->autoinc_id = '';
		$len_prefix = strlen($colum_prefix);
		foreach($this->table_def['fd'] as $col => $def)
		{
			$name = $col;
			if ($len_prefix && substr($name,0,$len_prefix) == $colum_prefix)
			{
				$name = substr($col,$len_prefix);
			}
			if (in_array($col,$this->table_def['pk']))
			{
				$this->db_key_cols[$col] = $name;
			}
			else
			{
				$this->db_data_cols[$col] = $name;
			}
			$this->db_cols[$col] = $name;

			if ($def['type'] == 'auto')
			{
				$this->autoinc_id = $col;
			}
			if (in_array($name,$this->table_def['uc']))
			{
				$this->db_uni_cols[$col] = $name;
			}
		}
	}

	/**
	 * merges in new values from the given new data-array
	 *
	 * @param $new array in form col => new_value with values to set
	 */
	function data_merge($new)
	{
		if ((int) $this->debug >= 4) echo "<p>so_sql::data_merge(".print_r($new,true).")</p>\n";

		if (!is_array($new) || !count($new))
		{
			return;
		}
		foreach($this->db_cols as $db_col => $col)
		{
			if (isset($new[$col]))
			{
				$this->data[$col] = $new[$col];
			}
		}
		foreach($this->non_db_cols as $db_col => $col)
		{
			if (isset($new[$col]))
			{
				$this->data[$col] = $new[$col];
			}
		}
		if ((int) $this->debug >= 4) _debug_array($this->data);
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * it gets called everytime when data is read from the db
	 * This function needs to be reimplemented in the derived class
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data = &$this->data;
		}
		// do the necessare changes here

		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches
	 * this needs to be reimplemented in the derived class
	 *
	 * @param array $data if given works on that array and returns result, else works on internal data-array
	 */
	function data2db($data=null)
	{
		if ($intern = !is_array($data))
		{
			$data = &$this->data;
		}
		// do the necessary changes here

		return $data;
	}

	/**
	 * initializes data with the content of key
	 *
	 * @param array $keys array with keys in form internalName => value
	 */
	function init($keys=array())
	{
		$this->data = array();

		$this->db2data();

		$this->data_merge($keys);
	}

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string/array $extra_cols string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 * @return array/boolean data if row could be retrived else False
	*/
	function read($keys,$extra_cols='',$join='')
	{
		if (!is_array($keys))
		{
			$pk = array_values($this->db_key_cols);
			if ($pk) $keys = array($pk[0] => $keys);
		}	
		
		$this->init($keys);
		$this->data2db();

		$query = false;
		foreach ($this->db_key_cols as $db_col => $col)
		{
			if ($this->data[$col] != '')
			{
				$query[$db_col] = $this->data[$col];
			}
		}
		if (!$query)	// no primary key in keys, lets try the data_cols for a unique key
		{
			foreach($this->db_uni_cols as $db_col => $col)
			{
				if ($this->data[$col] != '')
				{
					$query[$db_col] = $this->data[$col];
				}
			}
		}
		if (!$query)	// keys has no cols
		{
			$this->db2data();

			return False;
		}
		$this->db->select($this->table_name,'*'.($extra_cols?','.(is_array($extra_cols)?implode(',',$extra_cols):$extra_cols):''),
			$query,__LINE__,__FILE__,False,'',False,0,$join);

		if (!($row = $this->db->row(true)))
		{
			if ($this->autoinc_id)
			{
				unset($this->data[$this->db_key_cols[$this->autoinc_id]]);
			}
			if ((int) $this->debug >= 4) echo "nothing found !!!</p>\n";

			$this->db2data();

			return False;
		}
		$cols = $this->db_cols;
		if ($extra_cols)	// extra columns to report
		{
			foreach(is_array($extra_cols) ? $extra_cols : array($extra_cols) as $col)
			{
				if (stristr($col,'as')) $col = preg_replace('/^.*as *([a-z0-9_]+) *$/i','\\1',$col);
				$cols[$col] = $col;
			}
		}
		foreach ($cols as $db_col => $col)
		{
			$this->data[$col] = $row[$db_col];
		}
		$this->db2data();

		if ((int) $this->debug >= 4)
		{
			echo "data =\n"; _debug_array($this->data);
		}
		return $this->data;
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		$this->data2db();

		if ((int) $this->debug >= 4) { echo "so_sql::save(".print_r($keys,true).") autoinc_id='$this->autoinc_id', data="; _debug_array($this->data); }

		if ($this->autoinc_id && !$this->data[$this->db_key_cols[$this->autoinc_id]])	// insert with auto id
		{
			foreach($this->db_cols as $db_col => $col)
			{
				if (!$this->autoinc_id || $db_col != $this->autoinc_id)	// not write auto-inc-id
				{
					if (!isset($this->data[$col]) && 	// handling of unset columns in $this->data
						(isset($this->table_def['fd'][$db_col]['default']) ||	// we have a default value
						 !isset($this->table_def['fd'][$db_col]['nullable']) || $this->table_def['fd'][$db_col]['nullable']))	// column is nullable
					{
						continue;	// no need to write that (unset) column
					}
					$data[$db_col] = (string) $this->data[$col] === '' && $this->empty_on_write == 'NULL' ? null : $this->data[$col];
				}
			}
			$this->db->insert($this->table_name,$data,false,__LINE__,__FILE__);

			if ($this->autoinc_id)
			{
				$this->data[$this->db_key_cols[$this->autoinc_id]] = $this->db->get_last_insert_id($this->table_name,$this->autoinc_id);
			}
		}
		else // insert in table without auto id or update of existing row, dont write colums unset in $this->data
		{
			foreach($this->db_data_cols as $db_col => $col)
			{
				if (!isset($this->data[$col]) &&	// handling of unset columns in $this->data
					($this->autoinc_id ||			// update of table with auto id or
					 isset($this->table_def['fd'][$db_col]['default']) ||	// we have a default value or
					 !isset($this->table_def['fd'][$db_col]['nullable']) || $this->table_def['fd'][$db_col]['nullable']))	// column is nullable
				{
					continue;	// no need to write that (unset) column
				}
				$data[$db_col] = (string) $this->data[$col] === '' && $this->empty_on_write == 'NULL' ? null : $this->data[$col];
			}
			$keys = '';
			foreach($this->db_key_cols as $db_col => $col)
			{
				$keys[$db_col] = $this->data[$col];
			}
			if (!$data && !$this->autoinc_id)	// happens if all columns are in the primary key
			{
				$data = $keys;
				$keys = False;
			}
			if (!$this->autoinc_id)	// always try an insert if we have no autoinc_id, as we dont know if the data exists
			{
				$this->db->insert($this->table_name,$data,$keys,__LINE__,__FILE__);
			}
			else
			{
				$this->db->update($this->table_name,$data,$keys,__LINE__,__FILE__);
			}
		}
		$this->db2data();

		return $this->db->Errno;
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null)
	{
		if (!is_array($keys) || !count($keys))	// use internal data
		{
			$data = $this->data;
			$keys = $this->db_key_cols;
		}
		else	// data and keys are supplied in $keys
		{
			$data = $keys; $keys = array();
			foreach($this->db_cols as $db_col => $col)
			{
				if (isset($data[$col]))
				{
					$keys[$db_col] = $col;
				}
			}
		}
		$data = $this->data2db($data);

		foreach($keys as $db_col => $col)
		{
			$query[$db_col] = $data[$col];
		}
		$this->db->delete($this->table_name,$query,__LINE__,__FILE__);

		return $this->db->affected_rows();
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or 
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return boolean/array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		if ((int) $this->debug >= 4) echo "<p>so_sql::search(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

		if (!is_array($criteria))
		{
			$query = $criteria;
		}
		else
		{
			$criteria = $this->data2db($criteria);
			foreach($criteria as $col => $val)
			{
				if (is_int($col))
				{
					$query[] = $val;
				}
				elseif ($empty || $val != '')
				{
					if (!($db_col = array_search($col,$this->db_cols)))
					{
						$db_col = $col;
					}
					if ($wildcard || strstr($criteria[$col],'*') || strstr($criteria[$col],'?') || $criteria[$col]{0} == '!')
					{
						$cmp_op = ' LIKE ';
						if ($criteria[$col]{0} == '!')
						{
							$cmp_op = ' NOT LIKE ';
							$criteria[$col] = substr($criteria[$col],1);
						}
						$query[] = $db_col.$cmp_op.$this->db->quote($wildcard.str_replace(array('%','_','*','?'),array('\\%','\\_','%','_'),$criteria[$col]).$wildcard);
					}
					elseif (strstr($db_col,'.'))	// we have a table-name specified
					{
						list($table,$only_col) = explode('.',$db_col);
						
						$table_def = $this->db->get_table_definitions(true,$table);
						
						$query[] = $db_col.(is_array($val) ? ' IN ' : '=').$this->db->quote($val,$table_def['fd'][$only_col]);
					}
					else
					{
						$query[$db_col] = $criteria[$col];
					}
				}
			}
			if (is_array($query) && $op != 'AND') $query = $this->db->column_data_implode(' '.$op.' ',$query);
		}
		if (is_array($filter))
		{
			$db_filter = array();
			$data2db_filter = $this->data2db($filter);
			if (!is_array($data2db_filter)) {
				echo function_backtrace()."<br/>\n";
				echo "filter=";_debug_array($filter);
				echo "data2db(filter)=";_debug_array($data2db_filter);
			}	
			foreach($data2db_filter as $col => $val)
			{
				if ($val !== '')
				{
					// check if a db-internal name conversation necessary
					if (!is_numeric($col) && ($c = array_search($col,$this->db_cols))) 
					{
						$col = $c;
					}
					if ($val === "!''")
					{
						$db_filter[] = $col." != ''";
					}
					else
					{
						$db_filter[$col] = $val;
					}
				}
			}
			if ($query) 
			{
				if ($op != 'AND')
				{
					$db_filter[] = '('.$this->db->column_data_implode(' '.$op.' ',$query).')';
				}
				else 
				{
					$db_filter = array_merge($db_filter,$query);
				}
			}
			$query = $db_filter;
		}
		if ((int) $this->debug >= 4)
		{
			echo "<p>so_sql::search(,only_keys=$only_keys,order_by='$order_by',wildcard='$wildcard',empty=$empty,$op,start='$start',".print_r($filter,true).") query=".print_r($query,true).", total='$this->total'</p>\n";
			echo "<br>criteria = "; _debug_array($criteria);
		}
		if ($only_keys === true)
		{
			$colums = implode(',',array_keys($this->db_key_cols));
		}
		elseif (is_array($only_keys))
		{
			$colums = array();
			foreach($only_keys as $key => $col)
			{
				$colums[] = ($db_col = array_search($col,$this->db_cols)) ? $db_col : $col;
			}
			$colums = implode(',',$colums);
		}
		elseif (!$only_keys)
		{
			$colums = '*';
		}
		else
		{
			$colums = $only_keys;
		}
		if ($extra_cols) $colums .= ($colums ? ',' : '').(is_array($extra_cols) ? implode(',',$extra_cols) : $extra_cols);

		$num_rows = 0;	// as spec. in max_matches in the user-prefs
		if (is_array($start)) list($start,$num_rows) = $start;
		
		if ($order_by && !stristr($order_by,'ORDER BY') && !stristr($order_by,'GROUP BY'))
		{
			$order_by = 'ORDER BY '.$order_by;
		}
		static $union = array();
		static $union_cols = array();
		if ($start === 'UNION' || $union)
		{
			if ($start === 'UNION')
			{
				$union[] = array(
					'table'  => $this->table_name,
					'cols'   => $colums,
					'where'  => $query,
					'append' => $order_by,
					'join'   => $join,
				);
				if (!$union_cols)	// union used the colum-names of the first query
				{
					$union_cols = $this->_get_columns($only_keys,$extra_cols);
				}
				return true;	// waiting for further calls, before running the union-query
			}
			// running the union query now
			if ($start !== false && $num_rows != 1)	// need to get the total too, saved in $this->total
			{
				if ($this->db->Type == 'mysql' && $this->db->ServerInfo['version'] >= 4.0)
				{
					$union[0]['cols'] = ($mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ').$union[0]['cols'];
				}
				else	// cant do a count, have to run the query without limit
				{
					$this->db->union($union,__LINE__,__FILE__);
					$this->total = $this->db->num_rows();
				}
			}
			$this->db->union($union,__LINE__,__FILE__,$order_by,$start,$num_rows);

			$cols = $union_cols;
			$union = $union_cols = array();
		}
		else	// no UNION
		{
			if ($start !== false && $num_rows != 1)	// need to get the total too, saved in $this->total
			{
				if ($this->db->Type == 'mysql' && $this->db->ServerInfo['version'] >= 4.0)
				{
					$mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ';
				}
				elseif (!$need_full_no_count && (!$join || stristr($join,'LEFT JOIN')))
				{
					$this->db->select($this->table_name,'COUNT(*)',$query,__LINE__,__FILE__);
					$this->total = $this->db->next_record() ? (int) $this->db->f(0) : false;
				}
				else	// cant do a count, have to run the query without limit
				{
					$this->db->select($this->table_name,$colums,$query,__LINE__,__FILE__,false,$order_by,false,0,$join);
					$this->total = $this->db->num_rows();
				}
			}
			$this->db->select($this->table_name,$mysql_calc_rows.$colums,$query,__LINE__,__FILE__,
				$start,$order_by,false,$num_rows,$join);

			$cols = $this->_get_columns($only_keys,$extra_cols);
		}
		if ((int) $this->debug >= 4) echo "<p>sql='{$this->db->Query_ID->sql}'</p>\n";

		if ($mysql_calc_rows)
		{
			$this->total = $this->db->Link_ID->GetOne('SELECT FOUND_ROWS()');
		}
		$arr = array();
		for ($n = 0; ($row = $this->db->row(true)); ++$n)
		{
			$data = array();
			foreach($cols as $db_col => $col)
			{
				$data[$col] = $row[$db_col];
			}
			$arr[] = $this->db2data($data);
		}
		return $n ? $arr : False;
	}

	/**
	 * extract the requested columns from $only_keys and $extra_cols param of a search
	 *
	 * @internal 
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @return array with columns as db-name => internal-name pairs
	 */	 
	function _get_columns($only_keys,$extra_cols)
	{
		//echo "_get_columns() only_keys="; _debug_array($only_keys); echo "extra_cols="; _debug_array($extra_cols);
		if ($only_keys === true)	// only primary key
		{
			$cols = $this->db_key_cols;
		}
		else
		{
			$cols = array();
			foreach(is_array($only_keys) ? $only_keys : explode(',',str_replace(array('DISTINCT ','distinct '),'',$only_keys)) as $col)
			{
				if (!$col || $col == '*' || $col == $this->table_name.'.*')	// all columns
				{
					$cols = array_merge($cols,$this->db_cols);
				}
				else	// only the specified columns
				{
					if (stristr($col,'as')) $col = preg_replace('/^.*as +([a-z0-9_]+) *$/i','\\1',$col);
					if (($db_col = array_search($col,$this->db_cols)) !== false)
					{
						$cols[$db_col] = $col;
					}
					else
					{
						$cols[$col] = isset($this->db_cols[$col]) ? $this->db_cols[$col] : $col;
					}
				}
			}
		}
		if ($extra_cols)	// extra columns to report
		{
			foreach(is_array($extra_cols) ? $extra_cols : explode(',',$extra_cols) as $col)
			{
				if (stristr($col,'as ')) $col = preg_replace('/^.*as +([a-z0-9_]+) *$/i','\\1',$col);
				if (($db_col = array_search($col,$this->db_cols)) !== false)
				{
					$cols[$db_col] = $col;
				}
				else
				{
					$cols[$col] = isset($this->db_cols[$col]) ? $this->db_cols[$col] : $col;
				}
			}
		}
		return $cols;
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys,$join='',$need_full_no_count=false)
	{
		if ((int) $this->debug >= 4)
		{
			echo "<p>so_sql::get_rows(".print_r($query,true).",,)</p>\n";
		}
		$criteria = array();
		if ($query['search'])
		{
			foreach($this->db_cols as $col)	// we search all cols
			{
				$criteria[$col] = $query['search'];
			}
		}
		$rows = (array) $this->search($criteria,false,$query['order']?$query['order'].' '.$query['sort']:'',
			'','%',false,'OR',(int)$query['start'],$query['col_filter'],$join,$need_full_no_count);

		return $this->total;
	}
		
	/**
	 * Check if values for unique keys are unique
	 *
	 * @param array $data data-set to check, defaults to $this->data
	 * @return int 0: all keys are unique, 1: first key not unique, 2: ...
	 */
	function not_unique($data=null)
	{
		if (!is_array($data))
		{
			$data = $this->data;
		}
		$n = 1;
		foreach($this->db_uni_cols as $db_col => $col)
		{
			if (list($other) = $this->search(array($db_col => $data[$col]),false,'','','',false,'AND',false,null,''))
			{
				foreach($this->db_key_cols as $db_key_col => $key_col)
				{
					if ($data[$key_col] != $other[$key_col])
					{
						if ((int) $this->debug >= 4)
						{
							echo "<p>not_unique in '$col' as for '$key_col': '${data[$key_col]}' != '${other[$key_col]}'</p>\n";
						}
						return $n;	// different entry => $n not unique
					}
				}
			}
			++$n;
		}
		return 0;
	}
	
	/**
	 * Query DB for a list / array with one colum as key and an other one(s) as value, eg. id => title pairs
	 *
	 * We do some caching as these kind of function is usualy called multiple times, eg. for option-lists.
	 *
	 * @param string $value_col array of column-names for the values of the array, can also be an expression aliased with AS,
	 *	if more then one column given, an array with keys identical to the given ones is returned and not just the value of the column
	 * @param string $key_col='' column-name for the keys, default '' = same as (first) $value_col: returns a distinct list
	 * @param array $filter=array() to filter the entries
	 * @param string $order='' order, default '' = same as (first) $value_col
	 * @return array with key_col => value_col pairs or array if more then one value_col given (keys as in value_col)
	 */
	function query_list($value_col,$key_col='',$filter=array(),$order='')
	{
		static $cache = array();
		
		$cache_key = serialize($value_col).'-'.$key_col.'-'.serialize($filter).'-'.$order;
		
		if (isset($cache[$cache_key]))
		{
			return $cache[$cache_key];
		}
		if (!is_array($value_col)) $value_col = array($value_col);
		
		$cols = array();
		foreach($value_col as $key => $col)
		{
			$cols[$key] = preg_match('/AS ([a-z_0-9]+)$/i',$col,$matches) ? $matches[1] : $col;
		}			
		if (!$order) $order = current($cols);

		if (($search =& $this->search(array(),($key_col ? $key_col.',' : 'DISTINCT ').implode(',',$value_col),$order,'','',false,'AND',false,$filter)))
		{
			if (preg_match('/AS ([a-z_0-9]+)$/i',$key_col,$matches))
			{
				$key_col = $matches[1];
			}
			elseif (!$key_col)
			{
				$key_col = current($cols);
			}
			foreach($search as $row)
			{
				if (count($cols) > 1)
				{
					$data = array();
					foreach($cols as $key => $col)
					{
						$data[$key] = $row[$col];
					}
				}
				else
				{
					$data = $row[current($cols)];
				}
				$ret[$row[$key_col]] = $data;
			}
		}
		return $cache[$cache_key] =& $ret;
	}
}
