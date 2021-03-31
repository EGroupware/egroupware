<?php
/**
 * EGroupware API: Database api deprecated functionality
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Db;

use EGroupware\Api;

/**
 * Deprecated functionality we still need to support :-(
 *
 * This functions store result of last query in class variable Query_ID,
 * instead of operating on iterator / record-set returned by query method.
 *
 * You only need to clone the global database object $GLOBALS['egw']->db if:
 * - you use the old methods f(), next_record(), row(), num_fields(), num_rows()
 * - you access an application table (non phpgwapi) and you want to call set_app()
 *
 * Otherwise you can simply use $GLOBALS['egw']->db or a reference to it.
 *
 * Avoiding next_record() or row() can be done by looping with the recordset returned by query() or select():
 *
 * @deprecated use just EGroupware\Api\Db
 */
class Deprecated extends Api\Db
{
	/**
	 * ADOdb record set of the current query
	 *
	 * @var \ADORecordSet
	 */
	var $Query_ID = 0;

	/**
	* Execute a query
	*
	* @param string $Query_String the query to be executed
	* @param int $line the line method was called from - use __LINE__
	* @param string $file the file method was called from - use __FILE__
	* @param int $offset row to start from, default 0
	* @param int $num_rows number of rows to return (optional), default -1 = all, 0 will use $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']
	* @param array|boolean $inputarr array for binding variables to parameters or false (default)
	* @param int $fetchmode =self::FETCH_BOTH self::FETCH_BOTH (default), self::FETCH_ASSOC or self::FETCH_NUM
	* @param boolean $reconnect =true true: try reconnecting if server closes connection, false: dont (mysql only!)
	* @return \ADORecordSet or false, if the query fails
	* @throws Api\Db\Exception\InvalidSql with $this->Link_ID->ErrorNo() as code
	*/
	function query($Query_String, $line = '', $file = '', $offset=0, $num_rows=-1, $inputarr=false, $fetchmode=self::FETCH_BOTH, $reconnect=true)
	{
		// New query, discard previous result.
		if ($this->Query_ID)
		{
			$this->free();
		}
		return $this->Query_ID = parent::query($Query_String, $line, $file, $offset, $num_rows, $inputarr, $fetchmode, $reconnect);
	}

	/**
	 * Return the result-object of the last query
	 *
	 * @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	 * @return \ADORecordSet
	 */
	function query_id()
	{
		return $this->Query_ID;
	}

	/**
	* Escape strings before sending them to the database
	*
	* @deprecated use quote($value,$type='') instead
	* @param string $str the string to be escaped
	* @return string escaped sting
	*/
	function db_addslashes($str)
	{
		if (!isset($str) || $str == '')
		{
			return '';
		}
		if (!$this->Link_ID && !$this->connect())
		{
			return False;
		}
		return $this->Link_ID->addq($str);
	}

	/**
	 * Discard the current query result
	 *
	 * @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	 */
	function free()
	{
		unset($this->Query_ID);	// else copying of the db-object does not work
		$this->Query_ID = 0;
	}

	/**
	* Move to the next row in the results set
	*
	* Specifying a fetch_mode only works for newly fetched rows, the first row always gets fetched by query!!!
	*
	* @deprecated use foreach(query() or foreach(select() to loop over the query using the global db object
	* @param int $fetch_mode self::FETCH_BOTH = numerical+assoc keys (eGW default), self::FETCH_ASSOC or self::FETCH_NUM
	* @return bool was another row found?
	*/
	function next_record($fetch_mode=self::FETCH_BOTH)
	{
		if (!$this->Query_ID)
		{
			throw new Exception('next_record called with no query pending.');
		}
		if ($this->Row)	// first row is already fetched
		{
			$this->Query_ID->MoveNext();
		}
		++$this->Row;

		$this->Record = $this->Query_ID->fields;

		if ($this->Query_ID->EOF || !$this->Query_ID->RecordCount() || !is_array($this->Record))
		{
			return False;
		}
		if ($this->capabilities[self::CAPABILITY_NAME_CASE] == 'upper')	// maxdb, oracle, ...
		{
			switch($fetch_mode)
			{
				case self::FETCH_ASSOC:
					$this->Record = array_change_key_case($this->Record);
					break;
				case self::FETCH_NUM:
					$this->Record = array_values($this->Record);
					break;
				default:
					$this->Record = array_change_key_case($this->Record);
					if (!isset($this->Record[0]))
					{
						$this->Record += array_values($this->Record);
					}
					break;
			}
		}
		// fix the result if it was fetched ASSOC and now NUM OR BOTH is required, as default for select() is now ASSOC
		elseif ($this->Link_ID->fetchMode != $fetch_mode)
		{
			if (!isset($this->Record[0]))
			{
				$this->Record += array_values($this->Record);
			}
		}
		return True;
	}

	/**
	* Move to position in result set
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @param int $pos required row (optional), default first row
	* @return boolean true if sucessful or false if not found
	*/
	function seek($pos = 0)
	{
		if (!$this->Query_ID  || !$this->Query_ID->Move($this->Row = $pos))
		{
			throw new Exception("seek($pos) failed: resultset has " . $this->num_rows() . " rows");
		}
		return True;
	}

	/**
	* Lock a table
	*
	* @deprecated not used anymore as it costs to much performance, use transactions if needed
	* @param string $table name of table to lock
	* @param string $mode type of lock required (optional), default write
	* @return bool True if sucessful, False if fails
	*/
	function lock($table, $mode='write')
	{
		unset($table, $mode);	// not used anymore
	}

	/**
	* Unlock a table
	*
	* @deprecated not used anymore as it costs to much performance, use transactions if needed
	* @return bool True if sucessful, False if fails
	*/
	function unlock()
	{}

	/**
	* Number of rows in current result set
	*
	* @deprecated use the result-object returned by query/select()->NumRows(), so you can use the global db-object and not a clone
	* @return int number of rows
	*/
	function num_rows()
	{
		return $this->Query_ID ? $this->Query_ID->RecordCount() : False;
	}

	/**
	* Number of fields in current row
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @return int number of fields
	*/
	function num_fields()
	{
		return $this->Query_ID ? $this->Query_ID->FieldCount() : False;
	}

	/**
	* @deprecated use num_rows()
	*/
	function nf()
	{
		return $this->num_rows();
	}

	/**
	* @deprecated use print num_rows()
	*/
	function np()
	{
		print $this->num_rows();
	}

	/**
	* Return the value of a column
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @param string|integer $Name name of field or positional index starting from 0
	* @param bool $strip_slashes string escape chars from field(optional), default false
	*	depricated param, as correctly quoted values dont need any stripslashes!
	* @return string the field value
	*/
	function f($Name, $strip_slashes = False)
	{
		if ($strip_slashes)
		{
			return stripslashes($this->Record[$Name]);
		}
		return $this->Record[$Name];
	}

	/**
	* Print the value of a field
	*
	* @deprecated use the result-object returned by query() or select() direct, so you can use the global db-object and not a clone
	* @param string $Name name of field to print
	* @param bool $strip_slashes string escape chars from field(optional), default false
	*	depricated param, as correctly quoted values dont need any stripslashes!
	*/
	function p($Name, $strip_slashes = True)
	{
		print $this->f($Name, $strip_slashes);
	}

	/**
	* Returns a query-result-row as an associative array (no numerical keys !!!)
	*
	* @deprecated use foreach(query() or foreach(select() to loop over the query using the global db object
	* @param bool $do_next_record should next_record() be called or not (default not)
	* @param string $strip ='' string to strip of the column-name, default ''
	* @return array/bool the associative array or False if no (more) result-row is availible
	*/
	function row($do_next_record=False,$strip='')
	{
		if ($do_next_record && !$this->next_record(self::FETCH_ASSOC) || !is_array($this->Record))
		{
			return False;
		}
		$result = array();
		foreach($this->Record as $column => $value)
		{
			if (!is_numeric($column))
			{
				if ($strip) $column = str_replace($strip,'',$column);

				$result[$column] = $value;
			}
		}
		return $result;
	}
}

/**
 * @deprecated use EGroupware\Api\Db\CallbackIterator
 */
class egw_db_callback_iterator extends Api\Db\CallbackIterator {}
