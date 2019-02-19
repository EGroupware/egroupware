<?php
/**
 * EGroupware generalized SQL Storage Object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage storage
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;

/**
 * generalized SQL Storage Object
 *
 * the class can be used in following ways:
 * 1) by calling the constructor with an app and table-name or
 * 2) by setting the following documented class-vars in a class derived from this one
 * Of cause you can derive from the class and call the constructor with params.
 *
 * @todo modify search() to return an interator instead of an array
 */
class Base
{
 	/**
 	 * need to be set in the derived class to the db-table-name
 	 *
 	 * @var string
 	 */
	var $table_name;
 	/**
 	 * db-col-name of autoincrement id or ''
 	 *
 	 * @var string
 	 */
	var $autoinc_id = '';
	/**
	 * all cols in data which are not (direct)in the db, for data_merge
	 *
	 * @var array
	 */
	var $non_db_cols = array();
	/**
	 * 4 turns on the so_sql debug-messages, default 0
	 *
	 * @var int
	 */
	var $debug = 0;
	/**
	 * string to be written to db if a col-value is '', eg. "''" or 'NULL' (default)
	 *
	 * @var string
	 */
	var $empty_on_write = 'NULL';
	/**
	 * total number of entries of last search with start != false
	 *
	 * @var int|boolean
	 */
	var $total = false;
	/**
	 * protected instance or reference (depeding on $no_clone param of constructor) of the db-object
	 *
	 * @var Api\Db
	 */
	protected $db;
	/**
	 * unique keys/index, set by derived class or via so_sql($app,$table)
	 *
	 * @var array
	 */
	var $db_uni_cols = array();
	/**
	 * db-col-name / internal-name pairs, set by derived calls or via so_sql($app,$table)
	 *
	 * @var array
	 */
	var $db_key_cols = array();
	/**
	 * db-col-name / internal-name pairs, set by derived calls or via so_sql($app,$table)
	 *
	 * @var array
	 */
	var $db_data_cols = array();
	/**
	 * @var array $db_cols all columns = $db_key_cols + $db_data_cols, set in the constructor
	 */
	var $db_cols = array();
	/**
	 * eGW table definition
	 *
	 * @var array
	 */
	var $table_def = array();
	/**
	 * Appname to use in all queries, set via constructor
	 *
	 * @var string
	 */
	var $app;
	/**
	 * holds the content of all columns
	 *
	 * @var array
	 */
	var $data = array();
	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $timestamps = array();
	/**
	 * Type of timestamps returned by this class (read and search methods), default null means leave them unchanged
	 *
	 * Possible values:
	 * - 'ts'|'integer' convert every timestamp to an integer unix timestamp
	 * - 'string' convert every timestamp to a 'Y-m-d H:i:s' string
	 * - 'object' convert every timestamp to a Api\DateTime object
	 *
	 * @var string
	 */
	public $timestamp_type;
	/**
	 * Offset in secconds between user and server-time,	it need to be add to a server-time to get the user-time
	 * or substracted from a user-time to get the server-time
	 *
	 * @var int
	 * @deprecated use Api\DateTime methods instead, as the offset between user and server time is only valid for current time
	 */
	var $tz_offset_s;
	/**
	 * Current time in user timezone
	 *
	 * @var int|string|DateTime format depends on $this->timestamp_type
	 */
	var $now;
	/**
	 * Which columns should be searched, if a non-empty string is passed to criteria parameter of search()
	 *
	 * If not set (by extending class), all data columns will be searched.
	 *
	 * @var array
	 */
	var $columns_to_search;

	/**
	 * Table has boolean fields, which need automatic conversation, got set automatic by call to setup_table
	 *
	 * Set it to false, if you dont want automatic conversation
	 *
	 * @var boolean
	 */
	protected $has_bools = false;

	/**
	 * Should search return an iterator (true) or an array (false = default)
	 *
	 * @var boolean
	 */
	public $search_return_iterator = false;

	/**
	 * constructor of the class
	 *
	 * NEED to be called from the constructor of the derived class !!!
	 *
	 * @param string $app should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param string $table should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param Api\Db $db database object, if not the one in $GLOBALS['egw']->db should be used, eg. for an other database
	 * @param string $column_prefix ='' column prefix to automatic remove from the column-name, if the column name starts with it
	 * @param boolean $no_clone =false can we avoid to clone the db-object, default no
	 * 	new code using appnames and foreach(select(...,$app) can set it to avoid an extra instance of the db object
	 * @param string $timestamp_type =null default null=leave them as is, 'ts'|'integer' use integer unix timestamps,
	 * 	'object' use Api\DateTime objects or 'string' use DB timestamp (Y-m-d H:i:s) string
	 */
	function __construct($app='',$table='',Api\Db $db=null,$column_prefix='',$no_clone=false,$timestamp_type=null)
	{
		// ease the transition to api
		if ($app == 'phpgwapi') $app = 'api';

		if ($no_clone)
		{
			$this->db = is_object($db) ? $db : $GLOBALS['egw']->db;
		}
		else
		{
			$this->db = is_object($db) ? clone($db) : clone($GLOBALS['egw']->db);
		}
		$this->db_cols = $this->db_key_cols + $this->db_data_cols;

		if ($app)
		{
			$this->app = $app;

			if (!$no_clone) $this->db->set_app($app);

			if ($table) $this->setup_table($app,$table,$column_prefix);
		}
		$this->init();

		if ((int) $this->debug >= 4)
		{
			echo "<p>".__METHOD__."('$app','$table')</p>\n";
			_debug_array($this);
		}
		$this->set_times($timestamp_type);
	}

	/**
	 * Set class vars timestamp_type, now and tz_offset_s
	 *
	 * @param string|boolean $timestamp_type =false default false do NOT set time_stamptype,
	 * 	null=leave them as is, 'ts'|'integer' use integer unix timestamps, 'object' use Api\DateTime objects,
	 *  'string' use DB timestamp (Y-m-d H:i:s) string
	 */
	public function set_times($timestamp_type=false)
	{
		if ($timestamp_type !== false) $this->timestamp_type = $timestamp_type;

		// set current time
		switch($this->timestamp_type)
		{
			case 'object':
				$this->now = new Api\DateTime('now');
				break;
			case 'string':
				$this->now = Api\DateTime::to('now',Api\DateTime::DATABASE);
				break;
			default:
				$this->now = Api\DateTime::to('now','ts');
		}
		$this->tz_offset_s = Api\DateTime::tz_offset_s();
	}

	/**
	 * sets up the class for an app and table (by using the table-definition of $app/setup/tables_current.inc.php
	 *
	 * If you need a more complex conversation then just removing the column_prefix, you have to do so in a derifed class !!!
	 *
	 * @param string $app app-name $table belongs too
	 * @param string $table table-name
	 * @param string $colum_prefix ='' column prefix to automatic remove from the column-name, if the column name starts with it
	 */
	function setup_table($app,$table,$colum_prefix='')
	{
		$this->table_name = $table;
		$this->table_def = $this->db->get_table_definitions($app,$table);
		if (!$this->table_def || !is_array($this->table_def['fd']))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."('$app','$table'): No table definition for '$table' found !!!");
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
			if ($def['type'] == 'bool') $this->has_bools = true;

			foreach($this->table_def['uc'] as $k => $uni_index)
			{
				if (is_array($uni_index) && in_array($name,$uni_index))
				{
					$this->db_uni_cols[$k][$col] = $name;
				}
				elseif($name === $uni_index)
				{
					$this->db_uni_cols[$col] = $name;
				}
			}
		}
	}

	/**
	 * Add all timestamp fields to $this->timestamps to get automatically converted to usertime
	 *
	 */
	function convert_all_timestamps()
	{
		$check_already_included = !empty($this->timestamps);
		foreach($this->table_def['fd'] as $name => $data)
		{
			if ($data['type'] == 'timestamp' && (!$check_already_included || !in_array($name,$this->timestamps)))
			{
				$this->timestamps[] = $name;
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
			if (array_key_exists($col,$new))
			{
				$this->data[$col] = $new[$col];
			}
		}
		foreach($this->non_db_cols as $db_col => $col)
		{
			if (array_key_exists($col,$new))
			{
				$this->data[$col] = $new[$col];
			}
		}
		if (isset($new[self::USER_TIMEZONE_READ]))
		{
			$this->data[self::USER_TIMEZONE_READ] = $new[self::USER_TIMEZONE_READ];
		}
		if ((int) $this->debug >= 4) _debug_array($this->data);
	}

	/**
	 * changes the data from the db-format to your work-format
	 *
	 * It gets called everytime when data is read from the db.
	 * This default implementation only converts the timestamps mentioned in $this->timestamps from server to user time.
	 * You can reimplement it in a derived class like this:
	 *
	 * function db2data($data=null)
	 * {
	 * 		if (($intern = !is_array($data)))
	 * 		{
	 * 			$data =& $this->data;
	 * 		}
	 * 		// do your own modifications here
	 *
	 * 		return parent::db2data($intern ? null : $data);	// important to use null, if $intern!
	 * }
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function db2data($data=null)
	{
		if (!is_array($data))
		{
			$data = &$this->data;
		}
		if ($this->timestamps)
		{
			foreach($this->timestamps as $name)
			{
				if (isset($data[$name]) && $data[$name])
				{
					if ($data[$name] === '0000-00-00 00:00:00')
					{
						$data[$name] = null;
					}
					else
					{
						$data[$name] = Api\DateTime::server2user($data[$name],$this->timestamp_type);
					}
				}
			}
		}
		// automatic convert booleans (eg. PostgreSQL stores 't' or 'f', which both evaluate to true!)
		if ($this->has_bools !== false)
		{
			if (!isset($this->table_def))
			{
				$this->table_def = $this->db->get_table_definitions($this->app, $this->table);
				if (!$this->table_def || !is_array($this->table_def['fd']))
				{
					throw new Api\Exception\WrongParameter(__METHOD__."(): No table definition for '$this->table' found !!!");
				}
			}
			foreach($this->table_def['fd'] as $col => $def)
			{
				if ($def['type'] == 'bool' && isset($data[$col]))
				{
					$data[$col] = $this->db->from_bool($data[$col]);
				}
			}
		}
		return $data;
	}

	/**
	 * changes the data from your work-format to the db-format
	 *
	 * It gets called everytime when data gets writen into db or on keys for db-searches.
	 * This default implementation only converts the timestamps mentioned in $this->timestampfs from user to server time.
	 * You can reimplement it in a derived class like this:
	 *
	 * function data2db($data=null)
	 * {
	 * 		if (($intern = !is_array($data)))
	 * 		{
	 * 			$data =& $this->data;
	 * 		}
	 * 		// do your own modifications here
	 *
	 * 		return parent::data2db($intern ? null : $data);	// important to use null, if $intern!
	 * }
	 *
	 * @param array $data =null if given works on that array and returns result, else works on internal data-array
	 * @return array
	 */
	function data2db($data=null)
	{
		if (!is_array($data))
		{
			$data = &$this->data;
		}
		if ($this->timestamps)
		{
			foreach($this->timestamps as $name)
			{
				if (isset($data[$name]) && $data[$name])
				{
					$data[$name] = Api\DateTime::user2server($data[$name],$this->timestamp_type);
				}
			}
		}
		return $data;
	}

	/**
	 * initializes data with the content of key
	 *
	 * @param array $keys =array() array with keys in form internalName => value
	 * @return array internal data after init
	 */
	function init($keys=array())
	{
		$this->data = array();

		$this->db2data();

		$this->data_merge($keys);

		return $this->data;
	}

	/**
	 * Name of automatically set user timezone field from read
	 */
	const USER_TIMEZONE_READ = 'user_timezone_read';

	/**
	 * reads row matched by key and puts all cols in the data array
	 *
	 * @param array $keys array with keys in form internalName => value, may be a scalar value if only one key
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 * @return array|boolean data if row could be retrived else False
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
				if (!is_array($col) && $this->data[$col] != '')
				{
					$query[$db_col] = $this->data[$col];
				}
				elseif(is_array($col))
				{
					$q = array();
					foreach($col as $db_c => $c)
					{
						if ($this->data[$col] == '')
						{
							$q = null;
							break;
						}
						$q[$db_c] = $this->data[$c];
					}
					if ($q) $query += $q;
				}
			}
		}
		if (!$query)	// no unique key in keys, lets try everything else
		{
			foreach($this->db_data_cols as $db_col => $col)
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
		if ($join)	// Prefix the columns with the table-name, as they might exist in the join
		{
			foreach($query as $col => $val)
			{
				if (is_int($col) || strpos($join,$col) === false) continue;
				$query[] = $this->db->expression($this->table_name,$this->table_name.'.',array($col=>$val));
				unset($query[$col]);
			}
		}
		// copy direct SQL parts from $keys
		for($i = 0; is_array($keys) && isset($keys[$i]); ++$i)
		{
			$query[] = $keys[$i];
		}
		foreach($this->db->select($this->table_name,'*'.($extra_cols?','.(is_array($extra_cols)?implode(',',$extra_cols):$extra_cols):''),
			$query,__LINE__,__FILE__,False,'',$this->app,0,$join) as $row)
		{
			$cols = $this->db_cols;
			if ($extra_cols)	// extra columns to report
			{
				foreach(is_array($extra_cols) ? $extra_cols : array($extra_cols) as $col)
				{
					if (FALSE!==stripos($col,' as ')) $col = preg_replace('/^.* as *([a-z0-9_]+) *$/i','\\1',$col);
					$cols[$col] = $col;
				}
			}
			foreach ($cols as $db_col => $col)
			{
				$this->data[$col] = $row[$db_col];
			}
			$this->db2data();

			// store user timezone used for reading
			$this->data[self::USER_TIMEZONE_READ] = Api\DateTime::$user_timezone->getName();

			if ((int) $this->debug >= 4)
			{
				echo "data =\n"; _debug_array($this->data);
			}
			return $this->data;
		}
		if ($this->autoinc_id)
		{
			unset($this->data[$this->db_key_cols[$this->autoinc_id]]);
		}
		if ((int) $this->debug >= 4) echo "nothing found !!!</p>\n";

		$this->db2data();

		return False;
	}

	/**
	 * saves the content of data to the db
	 *
	 * @param array $keys =null if given $keys are copied to data before saveing => allows a save as
	 * @param string|array $extra_where =null extra where clause, eg. to check an etag, returns true if no affected rows!
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function save($keys=null,$extra_where=null)
	{
		if (is_array($keys) && count($keys)) $this->data_merge($keys);

		// check if data contains user timezone during read AND user changed timezone since then
		// --> load old timezone for the rest of this request
		// this only a grude hack, better handle this situation in app code:
		// history logging eg. depends on old data read before calling save, which is then in new timezone!
		// anyway it's better fixing it here then not fixing it at all ;-)
		if (isset($this->data[self::USER_TIMEZONE_READ]) && $this->data[self::USER_TIMEZONE_READ] != Api\DateTime::$user_timezone->getName())
		{
			//echo "<p>".__METHOD__."() User change TZ since read! tz-read=".$this->data[self::USER_TIMEZONE_READ].' != current-tz='.Api\DateTime::$user_timezone->getName()." --> fixing</p>\n";
			error_log(__METHOD__."() User changed TZ since read! tz-read=".$this->data[self::USER_TIMEZONE_READ].' != current-tz='.Api\DateTime::$user_timezone->getName()." --> fixing</p>");
			$GLOBALS['egw_info']['user']['preferences']['common']['tz'] = $this->data[self::USER_TIMEZONE_READ];
			Api\DateTime::setUserPrefs($this->data[self::USER_TIMEZONE_READ]);
			$this->set_times();
		}
		$this->data2db();

		if ((int) $this->debug >= 4) { echo "so_sql::save(".print_r($keys,true).") autoinc_id='$this->autoinc_id', data="; _debug_array($this->data); }

		if ($this->autoinc_id && !$this->data[$this->db_key_cols[$this->autoinc_id]])	// insert with auto id
		{
			foreach($this->db_cols as $db_col => $col)
			{
				if (!$this->autoinc_id || $db_col != $this->autoinc_id)	// not write auto-inc-id
				{
					if (!array_key_exists($col,$this->data) && 	// handling of unset columns in $this->data
						(isset($this->table_def['fd'][$db_col]['default']) ||	// we have a default value
						 !isset($this->table_def['fd'][$db_col]['nullable']) || $this->table_def['fd'][$db_col]['nullable']))	// column is nullable
					{
						continue;	// no need to write that (unset) column
					}
					if ($this->table_def['fd'][$db_col]['type'] == 'varchar' &&
						strlen($this->data[$col]) > $this->table_def['fd'][$db_col]['precision'])
					{
						// truncate the field to mamimum length, if upper layers didn't care
						$data[$db_col] = substr($this->data[$col],0,$this->table_def['fd'][$db_col]['precision']);
					}
					else
					{
						$data[$db_col] = (string) $this->data[$col] === '' && $this->empty_on_write == 'NULL' ? null : $this->data[$col];
					}
				}
			}
			$this->db->insert($this->table_name,$data,false,__LINE__,__FILE__,$this->app);

			if ($this->autoinc_id)
			{
				$this->data[$this->db_key_cols[$this->autoinc_id]] = $this->db->get_last_insert_id($this->table_name,$this->autoinc_id);
			}
		}
		else // insert in table without auto id or update of existing row, dont write colums unset in $this->data
		{
			foreach($this->db_data_cols as $db_col => $col)
			{
				// we need to update columns set to null: after a $this->data[$col]=null:
				// - array_key_exits($col,$this->data) === true
				// - isset($this->data[$col]) === false
				if (!array_key_exists($col,$this->data) &&	// handling of unset columns in $this->data
					($this->autoinc_id ||			// update of table with auto id or
					 isset($this->table_def['fd'][$db_col]['default']) ||	// we have a default value or
					 !isset($this->table_def['fd'][$db_col]['nullable']) || $this->table_def['fd'][$db_col]['nullable']))	// column is nullable
				{
					continue;	// no need to write that (unset) column
				}
				$data[$db_col] = !is_object($this->data[$col]) && (string) $this->data[$col] === '' && $this->empty_on_write == 'NULL' ? null : $this->data[$col];
			}
			// allow to add direct sql updates, eg. "etag=etag+1" with int keys
			if (is_array($keys) && isset($keys[0]))
			{
				for($n=0; isset($keys[$n]); ++$n)
				{
					$data[] = $keys[$n];
				}
			}
			$keys = $extra_where;
			foreach($this->db_key_cols as $db_col => $col)
			{
				$keys[$db_col] = $this->data[$col];
			}
			if (!$data && !$this->autoinc_id)	// happens if all columns are in the primary key
			{
				$data = $keys;
				$keys = False;
			}
			if ($this->autoinc_id)
			{
				$this->db->update($this->table_name,$data,$keys,__LINE__,__FILE__,$this->app);
				if (($nothing_affected = !$this->db->Errno && !$this->db->affected_rows()) && $extra_where)
				{
					return true;	// extra_where not met, eg. etag wrong
				}
			}
			// always try an insert if we have no autoinc_id, as we dont know if the data exists
			if (!$this->autoinc_id || $nothing_affected)
			{
				$this->db->insert($this->table_name,$data,$keys,__LINE__,__FILE__,$this->app);
			}
		}
		$this->db2data();

		return $this->db->Errno;
	}

	/**
	 * Update only the given fields, if the primary key is not given, it will be taken from $this->data
	 *
	 * @param array $_fields
	 * @param boolean $merge =true if true $fields will be merged with $this->data (after update!), otherwise $this->data will be just $fields
	 * @return int|boolean 0 on success, or errno != 0 on error, or true if $extra_where is given and no rows affected
	 */
	function update($_fields,$merge=true)
	{
		if ($merge) $this->data_merge($_fields);

		$fields = $this->data2db($_fields);

		// extract the keys from $fields or - if not set there - from $this->data
		$keys = array();
		foreach($this->db_key_cols as $col => $name)
		{
			$keys[$col] = isset($fields[$name]) ? $fields[$name] : $this->data[$name];
			unset($fields[$name]);
		}
		// extract the data from $fields
		$data = array();
		foreach($this->db_data_cols as $col => $name)
		{
			if (array_key_exists($name,$fields))
			{
				$data[$col] = $fields[$name];
				unset($fields[$name]);
			}
		}
		// add direct sql like 'etag=etag+1' (it has integer keys)
		foreach($fields as $key => $value)
		{
			if (is_int($key))
			{
				$data[] = $value;
			}
		}
		if (!$data)
		{
			return 0;	// nothing to update
		}
		if (!$this->db->update($this->table_name,$data,$keys,__LINE__,__FILE__,$this->app))
		{
			return $this->db->Errno;
		}
		return 0;
	}

	/**
	 * deletes row representing keys in internal data or the supplied $keys if != null
	 *
	 * @param array|int $keys =null if given array with col => value pairs to characterise the rows to delete, or integer autoinc id
	 * @param boolean $only_return_query =false return $query of delete call to db object, but not run it (used by so_sql_cf!)
	 * @return int|array affected rows, should be 1 if ok, 0 if an error or array with id's if $only_return_ids
	 */
	function delete($keys=null,$only_return_query=false)
	{
		if ($this->autoinc_id && $keys && !is_array($keys))
		{
			$keys = array($this->autoinc_id => $keys);
		}
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
			// keep sql fragments (with integer key)
			foreach($data as $key => $val)
			{
				if (is_int($key))
				{
					$keys[] = $val;
				}
			}
		}
		$data = $this->data2db($data);

		foreach($keys as $db_col => $col)
		{
			if (is_int($db_col))
			{
				$query[] = $col;
			}
			else
			{
				$query[$db_col] = $data[$col];
			}
		}
		if ($only_return_query) return $query;

		$this->db->delete($this->table_name,$query,__LINE__,__FILE__,$this->app);

		return $this->db->affected_rows();
	}

	/**
	 * searches db for rows matching searchcriteria
	 *
	 * '*' and '?' are replaced with sql-wildcards '%' and '_'
	 *
	 * For a union-query you call search for each query with $start=='UNION' and one more with only $order_by and $start set to run the union-query.
	 *
	 * @param array|string $criteria array of key and data cols, OR string with search pattern (incl. * or ? as wildcards)
	 * @param boolean|string|array $only_keys =true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. "JOIN table2 ON x=y" or
	 *	"LEFT JOIN table2 ON (x=y AND z=o)", Note: there's no quoting done on $join, you are responsible for it!!!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @todo return an interator instead of an array
	 * @return array|NULL array of matching rows (the row is an array of the cols) or NULL
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false)
	{
		//error_log(__METHOD__.'('.array2string(array_combine(array_slice(array('criteria','only_keys','order_by','extra_cols','wildcard','empty','op','start','filter','join','need_full_no_count'), 0, count(func_get_args())), func_get_args())).')');
		if ((int) $this->debug >= 4) echo "<p>so_sql::search(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";

		// if extending class or instanciator set columns to search, convert string criteria to array
		if ($criteria && !is_array($criteria))
		{
			$search = $this->search2criteria($criteria,$wildcard,$op);
			$criteria = array($search);
		}
		if (!is_array($criteria))
		{
			$query = $criteria;
		}
		else
		{
			$query = $this->parse_search($criteria, $wildcard, $empty, $op);
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
					if (!is_int($col) && ($c = array_search($col,$this->db_cols)))
					{
						$col = $this->table_name . '.' . $c;
					}
					if(is_int($col))
					{
						$db_filter[] = $val;
					}
					elseif ($val === "!''")
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
			$colums = array_keys($this->db_key_cols);
			foreach($colums as &$column)
			{
				$column = $this->table_name . '.' . $column;
			}
		}
		elseif (is_array($only_keys))
		{
			$colums = array();
			foreach($only_keys as $key => $col)
			{
				//Convert ambiguous columns to prefixed tablename.column name
				$colums[] = ($db_col = array_search($col,$this->db_cols)) ?  $this->table_name .'.'.$db_col.' AS '.$col :$col;
			}
		}
		elseif (!$only_keys)
		{
			$colums = '*';
		}
		else
		{
			$colums = $only_keys;
		}
		if ($extra_cols)
		{
			if (!is_array($colums))
			{
				$colums .= ','.(is_array($extra_cols) ? implode(',', $extra_cols) : $extra_cols);
			}
			else
			{
				$colums = array_merge($colums, is_array($extra_cols) ? $extra_cols : explode(',', $extra_cols));
			}
		}

		// add table-name to otherwise ambiguous id over which we join (incl. "AS id" to return it with the right name)
		if ($join && $this->autoinc_id)
		{
			if (is_array($colums) && ($key = array_search($this->autoinc_id, $colums)) !== false)
			{
				$colums[$key] = $this->table_name.'.'.$this->autoinc_id.' AS '.$this->autoinc_id;
			}
			elseif (!is_array($colums) && strpos($colums,$this->autoinc_id) !== false)
			{
				$colums = preg_replace('/(?<! AS)([ ,]+)'.preg_quote($this->autoinc_id).'([ ,]+)/','\\1'.$this->table_name.'.'.$this->autoinc_id.' AS '.$this->autoinc_id.'\\2',$colums);
			}
		}
		$num_rows = 0;	// as spec. in max_matches in the user-prefs
		if (is_array($start)) list($start,$num_rows) = $start;

		// fix GROUP BY clause to contain all non-aggregate selected columns
		if ($order_by && stripos($order_by,'GROUP BY') !== false)
		{
			$order_by = $this->fix_group_by_columns($order_by, $colums, $this->table_name, $this->autoinc_id);
		}
		elseif ($order_by && stripos($order_by,'ORDER BY')===false && stripos($order_by,'GROUP BY')===false && stripos($order_by,'HAVING')===false)
		{
			$order_by = 'ORDER BY '.$order_by;
		}
		if (is_array($colums))
		{
			$colums = implode(',', $colums);
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
			if ($start !== false)	// need to get the total too, saved in $this->total
			{
				if ($this->db->Type == 'mysql' && $this->db->ServerInfo['version'] >= 4.0)
				{
					$union[0]['cols'] = ($mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ').$union[0]['cols'];
				}
				else	// cant do a count, have to run the query without limit
				{
					$this->total = $this->db->union($union,__LINE__,__FILE__)->NumRows();
				}
			}
			$rs = $this->db->union($union,__LINE__,__FILE__,$order_by,$start,$num_rows);
			if ($this->debug) error_log(__METHOD__."() ".$this->db->Query_ID->sql);

			$cols = $union_cols;
			$union = $union_cols = array();
		}
		else	// no UNION
		{
			if ($start !== false)	// need to get the total too, saved in $this->total
			{
				if ($this->db->Type == 'mysql' && $this->db->ServerInfo['version'] >= 4.0)
				{
					$mysql_calc_rows = 'SQL_CALC_FOUND_ROWS ';
				}
				elseif (!$need_full_no_count && (!$join || stripos($join,'LEFT JOIN')!==false))
				{
					$this->total = $this->db->select($this->table_name,'COUNT(*)',$query,__LINE__,__FILE__,false,'',$this->app,0,$join)->fetchColumn();
				}
				else	// cant do a count, have to run the query without limit
				{
					$this->total = $this->db->select($this->table_name,$colums,$query,__LINE__,__FILE__,false,$order_by,false,0,$join)->NumRows();
				}
			}
			$rs = $this->db->select($this->table_name,$mysql_calc_rows.$colums,$query,__LINE__,__FILE__,
				$start,$order_by,$this->app,$num_rows,$join);
			if ($this->debug) error_log(__METHOD__."() ".$this->db->Query_ID->sql);
			$cols = $this->_get_columns($only_keys,$extra_cols);
		}
		if ((int) $this->debug >= 4) echo "<p>sql='{$this->db->Query_ID->sql}'</p>\n";

		if ($mysql_calc_rows)
		{
			$this->total = $this->db->query('SELECT FOUND_ROWS()')->fetchColumn();
		}
		// ToDo: Implement that as an iterator, as $rs is also an interator and we could return one instead of an array
		if ($this->search_return_iterator)
		{
			return new Db2DataIterator($this,$rs);
		}
		$arr = array();
		$n = 0;
		if ($rs) foreach($rs as $row)
		{
			$data = array();
			foreach($cols as $db_col => $col)
			{
				$data[$col] = (isset($row[$db_col]) ? $row[$db_col] : $row[$col]);
			}
			$arr[] = $this->db2data($data);
			$n++;
		}
		return $n ? $arr : null;
	}

	/**
	 * Parse an array of search criteria into something that can be passed on
	 * to the DB
	 *
	 * @param array $_criteria
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @return Array
	 * @throws Api\Db\Exception
	 */
	protected function parse_search(Array $_criteria, $wildcard, $empty, $op)
	{
		$criteria = $this->data2db($_criteria);
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
				if ($val === '')
				{
					if (isset($this->table_def['fd'][$db_col]) &&
						$this->table_def['fd'][$db_col]['type'] == 'varchar' &&
						$this->table_def['fd'][$db_col]['nullable'] !== false)
					{
						unset($criteria[$col]);
						$query[] =  '(' . $db_col . ' IS NULL OR ' . $db_col . " = '')";
					}
					else
					{
						$query[$db_col] = '';
					}
				}
				elseif ($wildcard || $criteria[$col][0] == '!' ||
					is_string($criteria[$col]) && (strpos($criteria[$col],'*')!==false || strpos($criteria[$col],'?')!==false))
				{
					// if search pattern alread contains a wildcard, do NOT add further ones automatic
					if (is_string($criteria[$col]) && (strpos($criteria[$col],'*')!==false || strpos($criteria[$col],'?')!==false))
					{
						$wildcard = '';
					}
					$cmp_op = ' '.$this->db->capabilities['case_insensitive_like'].' ';
					$negate = false;
					if ($criteria[$col][0] == '!')
					{
						$cmp_op = ' NOT'.$cmp_op;
						$criteria[$col] = substr($criteria[$col],1);
						$negate = true;
					}
					foreach(explode(' ',$criteria[$col]) as $crit)
					{
						$query[] = ($negate ? ' ('.$db_col.' IS NULL OR ' : '').$db_col.$cmp_op.
							$this->db->quote($wildcard.str_replace(array('%','_','*','?'),array('\\%','\\_','%','_'),$crit).$wildcard).
							($negate ? ') ' : '');
					}
				}
				elseif (strpos($db_col,'.') !== false)	// we have a table-name specified
				{
					list($table,$only_col) = explode('.',$db_col);
					$type = $this->db->get_column_attribute($only_col, $table, true, 'type');
					if (empty($type))
					{
						throw new Api\Db\Exception("Can not determine type of column '$only_col' in table '$table'!");
					}
					if (is_array($val) && count($val) > 1)
					{
						foreach($val as &$v)
						{
							$v = $this->db->quote($v, $type);
						}
						$query[] = $sql = $db_col.' IN (' .implode(',',$val).')';
					}
					else
					{
						$query[] = $db_col.'='.$this->db->quote(is_array($val)?array_shift($val):$val,$type);
					}
				}
				else
				{
					$query[$db_col] = $criteria[$col];
				}
			}
		}
		if (is_array($query) && $op != 'AND') $query = $this->db->column_data_implode(' '.$op.' ',$query);
		return $query;
	}

	/**
	 * Fix GROUP BY clause to contain all non-aggregate selected columns
	 *
	 * No need to call for MySQL because MySQL does NOT give an error in above case.
	 * (Of cause developer has to make sure to group by enough columns, eg. a unique key, for selected columns to be defined.)
	 *
	 * MySQL also does not allow to use [tablename.]* in GROUP BY, which PostgreSQL allows!
	 * (To use this for MySQL too, we would have to replace * with all columns of a table.)
	 *
	 * @param string $group_by [GROUP BY ...[HAVING ...]][ORDER BY ...]
	 * @param string|array $columns better provide an array as exploding by comma can lead to error with functions containing one
	 * @param string $table_name table-name
	 * @param string $autoinc_id id-column
	 * @return string
	 */
	public static function fix_group_by_columns($group_by, &$columns, $table_name, $autoinc_id)
	{
		$matches = null;
		if (substr($GLOBALS['egw']->db->Type, 0, 5) == 'mysql' || !preg_match('/(GROUP BY .*)(HAVING.*|ORDER BY.*)?$/iU', $group_by, $matches))
		{
			return $group_by;	// nothing to do
		}
		$changes = 0;
		$group_by_cols = preg_split('/, */', trim(substr($matches[1], 9)));

		if (!is_array($columns))
		{
			$columns = preg_split('/, */', $columns);

			// fix columns containing commas as part of function calls
			for($n = 0; $n < count($columns); ++$n)
			{
				$col =& $columns[$n];
				while (substr_count($col, '(') > substr_count($col, ')') && ++$n < count($columns))
				{
					$col .= ','.$columns[$n];
					unset($columns[$n]);
				}
			}
			unset($col);
		}
		foreach($columns as $n => $col)
		{
			if ($col == '*')
			{
				// MySQL does NOT allow to GROUP BY table.*
				$col = $columns[$n] = $table_name.'.'.($GLOBALS['egw']->db->Type == 'mysql' ? $autoinc_id : '*');
				++$changes;
			}
			// only check columns and non-aggregate functions
			if (strpos($col, '(') === false || !preg_match('/(COUNT|MIN|MAX|AVG|SUM|BIT_[A-Z]+|STD[A-Z_]*|VAR[A-Z_]*|ARRAY_AGG)\(/i', $col))
			{
				if (($pos = stripos($col, 'DISTINCT ')) !== false)
				{
					$col = substr($col, $pos+9);
				}
				$alias = $col;
				if (stripos($col, ' AS ')) list($col, $alias) = preg_split('/ +AS +/i', $col);
				// do NOT group by constant expressions
				if (preg_match('/^ *(-?[0-9]+|".*"|\'.*\'|NULL) *$/i', $col)) continue;
				if (!in_array($col, $group_by_cols) && !in_array($alias, $group_by_cols))
				{
					// instead of aliased primary key, we have to use original column incl. table-name as alias is ambigues
					$group_by_cols[] = $col == $table_name.'.'.$autoinc_id ? $col : $alias;
					//error_log(__METHOD__."() col=$col, alias=$alias --> group_by_cols=".array2string($group_by_cols));
					++$changes;
				}
			}
		}
		$ret = $group_by;
		if ($changes)
		{
			$ret = str_replace($matches[1], 'GROUP BY '.implode(',', $group_by_cols).' ',  $group_by);
			//error_log(__METHOD__."('$group_by', ".array2string($columns).") group_by_cols=".array2string($group_by_cols)." changed to $ret");
		}
		return $ret;
	}

	/**
	 * Return criteria array for a given search pattern
	 *
	 * We handle quoted text, wildcards and boolean operators (+/-, AND/OR).  If
	 * the pattern is '#' followed by an integer, the search is limited to just
	 * the primary key.
	 *
	 * @param string $_pattern search pattern incl. * or ? as wildcard, if no wildcards used we append and prepend one!
	 * @param string &$wildcard ='' on return wildcard char to use, if pattern does not already contain wildcards!
	 * @param string &$op ='AND' on return boolean operation to use, if pattern does not start with ! we use OR else AND
	 * @param string $extra_col =null extra column to search
	 * @param array $search_cols =array() List of columns to search.  If not provided, all columns in $this->db_cols will be considered
	 * @return array or column => value pairs
	 */
	public function search2criteria($_pattern,&$wildcard='',&$op='AND',$extra_col=null, $search_cols = array())
	{
		$pattern = trim($_pattern);
		// This function can get called multiple times.  Make sure it doesn't re-process.
		if (empty($pattern) || is_array($pattern)) return $pattern;
		if(strpos($pattern, 'CAST(COALESCE(') !== false)
		{
			return $pattern;
		}

		$criteria = array();
		$filter = array();
		$columns = array();

		/*
		* Special handling for numeric columns.  They are only considered if the pattern is numeric.
		* If the pattern is numeric, an equality search is used instead.
		*/
		$numeric_types = array('auto', 'int', 'float', 'double', 'decimal');
		$numeric_columns = array();

		// Special handling for an ID search, #<int>
		if(strpos($_pattern, '#') === 0 && is_numeric(substr($_pattern, 1)))
		{
			return array('(' . $this->table_name.'.'. $this->autoinc_id . '=' . (int)substr($_pattern,1) . ')');
		}
		if(!$search_cols)
		{
			$search_cols = $this->get_default_search_columns();
		}
		// Concat all fields to be searched together, so the conditions operate across the whole record
		foreach($search_cols as $col)
		{
			$col_name = $col;
			$table = $this->table_name;
			if (strpos($col,'.') !== false)
			{
				list($table,$col_name) = explode('.',$col);
			}
			$table_def = $table == $this->table_name ? $this->table_def : $this->db->get_table_definitions(true,$table);
			if ($table_def['fd'][$col_name] && in_array($table_def['fd'][$col_name]['type'], $numeric_types))
			{
				$numeric_columns[] = $col;
				continue;
			}
			if ($this->db->Type == 'mysql' && $table_def['fd'][$col_name]['type'] === 'ascii' && preg_match('/[\x80-\xFF]/', $_pattern))
			{
				continue;	// will only give sql error
			}
			$columns[] = sprintf($this->db->capabilities[Api\Db::CAPABILITY_CAST_AS_VARCHAR],"COALESCE($col,'')");
		}
		if(!$columns)
		{
			return array();
		}

		// Break the search string into tokens
		$break = ' ';
		$token = strtok($pattern, $break);

		while($token)
		{
			if($token == strtoupper(lang('AND')) || $token == 'AND')
			{
				$token = '+'.strtok($break);
			}
			elseif ($token == strtoupper(lang('OR')) || $token == 'OR')
			{
				$token = strtok($break);
				continue;
			}
			elseif ($token == strtoupper(lang('NOT')) || $token == 'NOT')
			{
				$token = '-'.strtok($break);
			}
			if ($token[0]=='"')
			{
				$token = substr($token, 1,strlen($token));
 				if(substr($token, -1) != '"')
				{
					$token .= ' '.strtok('"');
				}
				else
				{
					$token = substr($token, 0, -1);
				}
			}

			// prepend and append extra wildcard %, if pattern does NOT already contain wildcards
			if (strpos($token,'*') === false && strpos($token,'?') === false)
			{
				$wildcard = '%';	// if pattern contains no wildcards, add them before AND after the pattern
			}
			else
			{
				$wildcard = '';		// no extra wildcard, if pattern already contains some
			}

			switch($token[0])
			{
				case '+':
					$op = 'AND';
					$token = substr($token, 1, strlen($token));
					break;
				case '-':
				case '!':
					$op = 'NOT';
					$token = substr($token, 1, strlen($token));
					break;
				default:
					$op = 'OR';
					break;
			}
			$search_token = $wildcard.str_replace(array('%','_','*','?'),array('\\%','\\_','%','_'),$token).$wildcard;
			$token_filter = ' '.call_user_func_array(array($GLOBALS['egw']->db,'concat'),$columns).' '.
				$this->db->capabilities['case_insensitive_like'] . ' ' .
				$GLOBALS['egw']->db->quote($search_token);

			// if we have customfields and this is Api\Storage (not Api\Storage\Base)
			if (is_a($this, __NAMESPACE__))
			{
				// add custom-field search: OR id IN (SELECT id FROM extra_table WHERE extra_value LIKE '$search_token')
				$token_filter .= $this->cf_match($search_token);
			}

			// Compare numeric token as equality for numeric columns
			// skip user-wildcards (*,?) in is_numeric test, but not SQL wildcards, which get escaped and give sql-error
			if (is_numeric(str_replace(array('*','?'), '', $token)))
			{
				$numeric_filter = array();
				foreach($numeric_columns as $col)
				{
					if($wildcard == '')
					{
						// Token has a wildcard from user, use LIKE
						$numeric_filter[] = "($col IS NOT NULL AND CAST($col AS CHAR) " .
							$this->db->capabilities['case_insensitive_like'] . ' ' .
							$GLOBALS['egw']->db->quote(str_replace(array('*','?'), array('%','_'), $token)) . ')';
					}
					else
					{
						$numeric_filter[] = "($col IS NOT NULL AND $col = $token)";
					}
				}
				if(count($numeric_filter) > 0)
				{
					$token_filter .= ' OR ' . implode(' OR ', $numeric_filter);
				}
			}
			$criteria[$op][] = '('.$token_filter.')';

			$token = strtok($break);
		}

		if($criteria['NOT'])
		{
			$filter[] = 'NOT (' . implode(' OR ', $criteria['NOT']) . ') ';
		}
		if($criteria['AND'])
		{
			$filter[] = implode(' AND ', $criteria['AND']) . ' ';
		}
		if($criteria['OR'])
		{
			$filter[] = '(' . implode(' OR ', $criteria['OR']) . ') ';
		}

		if(count($filter))
		{
			$result = '(' . implode(' AND ', $filter) . ')';
		}

		// OR extra column on the end so a null or blank won't block a hit in the main columns
		if ($extra_col)
		{
			$result .= (strlen($result) ? ' OR ' : ' ') . "$extra_col = " . $GLOBALS['egw']->db->quote($pattern);
		}

		$op = 'OR';
		return array('(' . $result . ')');
	}

	/**
	* Get a default list of columns to search
	* This is to be used as a fallback, for when the extending class does not define
	* $this->columns_to_search.  All the columns are considered, and any with $skip_columns_with in
	* their name are discarded because these columns are expected to be foreign keys or other numeric
	* values with no meaning to the user.
	*
	* @return array of column names
	*/
	protected function get_default_search_columns()
	{
		$skip_columns_with = array('_id', 'modified', 'modifier', 'status', 'cat_id', 'owner');
		$search_cols = is_null($this->columns_to_search) ? $this->db_cols : $this->columns_to_search;
		$numeric_types = array('auto', 'int', 'float', 'double');

		// Skip some numeric columns that don't make sense to search if we have to default to all columns
		if(is_null($this->columns_to_search))
		{
			foreach($search_cols as $key => &$col)
			{
				// If the name as given isn't a real column name, and adding the prefix doesn't help, skip it
				if(!$this->table_def['fd'][$col] && !($col = $this->prefix.array_search($col, $search_cols))) {
					// Can't search this column
					unset($search_cols[$key]);
					continue;
				}
				if(in_array($this->table_def['fd'][$col]['type'], $numeric_types))
				{
					foreach($skip_columns_with as $bad)
					{
						if(strpos($col, $bad) !== false)
						{
							unset($search_cols[$key]);
							continue 2;
						}
					}
				}
				// Prefix with table name to avoid ambiguity
				$col = $this->table_name.'.'.$col;
			}
		}
		return $search_cols;
	}

	/**
	 * extract the requested columns from $only_keys and $extra_cols param of a search
	 *
	 * @internal
	 * @param boolean|string $only_keys =true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
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
			$distinct_checked = false;
			foreach(is_array($only_keys) ? $only_keys : explode(',', $only_keys) as $col)
			{
				if (!$distinct_checked)
				{
					if (stripos($col, 'DISTINCT ') === 0) $col = substr($col, 9);
					$distinct_checked = true;
				}
				if (!$col || $col == '*' || $col == $this->table_name.'.*')	// all columns
				{
					$cols = array_merge($cols,$this->db_cols);
				}
				else	// only the specified columns
				{
					if (stripos($col,'as'))	// if there's already an explicit naming of the column, just use it
					{
						$col = preg_replace('/^.*as +([a-z0-9_]+) *$/i','\\1',$col);
						$cols[$col] = $col;
						continue;
					}
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
				if (stripos($col,'as ')!==false) $col = preg_replace('/^.*as +([a-z0-9_]+) *$/i','\\1',$col);
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
	 * @param string $join ='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count =false If true an unlimited query is run to determine the total number of rows, default false
	 * @param mixed $only_keys =false, see search
	 * @param string|array $extra_cols =array()
	 * @return int total number of rows
	 */
	function get_rows($query,&$rows,&$readonlys,$join='',$need_full_no_count=false,$only_keys=false,$extra_cols=array())
	{
		unset($readonlys);	// required by function signature, but not used in this default implementation
		if ((int) $this->debug >= 4)
		{
			echo "<p>so_sql::get_rows(".print_r($query,true).",,)</p>\n";
		}
		$criteria = array();
		$op = 'AND';
		if ($query['search'])
		{
			$criteria = $query['search'];
		}
		$rows = $this->search($criteria,$only_keys,$query['order']?$query['order'].' '.$query['sort']:'',$extra_cols,
			'',false,$op,$query['num_rows']?array((int)$query['start'],$query['num_rows']):(int)$query['start'],
			$query['col_filter'],$join,$need_full_no_count);

		if (!$rows) $rows = array();	// otherwise false returned from search would be returned as array(false)

		return $this->total;
	}

	/**
	 * Check if values for unique keys and the primary keys are unique are unique
	 *
	 * @param array $data =null data-set to check, defaults to $this->data
	 * @return int 0: all keys are unique, 1: first key not unique, 2: ...
	 */
	function not_unique($data=null)
	{
		if (!is_array($data))
		{
			$data = $this->data;
		}
		$n = 1;
		$uni_keys = $this->db_uni_cols;
		// add the primary key, only if it's NOT an auto id
		if (!$this->autoinc_id)
		{
			$uni_keys[] = $this->db_key_cols;
		}
		foreach($uni_keys as $db_col => $col)
		{
			if (is_array($col))
			{
				$query = array();
				foreach($col as $db_c => $c)
				{
					$query[$db_c] = $data[$c];
				}
			}
			else
			{
				$query = array($db_col => $data[$col]);
			}
			foreach($this->db->select($this->table_name,$this->db_key_cols,$query,__LINE__,__FILE__,false,'',$this->app) as $other)
			{
				foreach($this->db_key_cols as $key_col)
				{
					if ($data[$key_col] != $other[$key_col])
					{
						if ((int) $this->debug >= 4)
						{
							echo "<p>not_unique in ".array2string($col)." as for '$key_col': '${data[$key_col]}' != '${other[$key_col]}'</p>\n";
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
	 * @param string $key_col ='' column-name for the keys, default '' = same as (first) $value_col: returns a distinct list
	 * @param array $filter =array() to filter the entries
	 * @param string $order ='' order, default '' = same as (first) $value_col
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

		$cols = $ret = array();
		foreach($value_col as $key => $col)
		{
			$matches = null;
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
				if ($data) $ret[$row[$key_col]] = $data;
			}
		}
		return $cache[$cache_key] =& $ret;
	}

	/**
	 * Get comments for all columns or a specific one
	 *
	 * @param string $column =null name of column or null for all (default)
	 * @return array|string array with internal-name => comment pairs, or string with comment, if $column given
	 */
	public function get_comments($column=null)
	{
		static $comments=null;

		if (is_null($comments))
		{
			foreach($this->db_cols as $db_col => $col)
			{
				$comments[$col] = $this->table_def['fd'][$db_col]['comment'];
			}
		}
		return is_null($column) ? $comments : $comments[$column];
	}
}
