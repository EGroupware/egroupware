<?php
	/**************************************************************************\
	* phpGroupWare - generalized SQL Storage Object                            *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

/*!
@class so_sql
@author ralfbecker
@abstract generalized SQL Storage Object
@discussion the class can be used in following ways:
@discussion 1) by calling the constructor with an app and table-name or
@discussion 2) by setting the following class-vars in a class derifed from this one
@discussion Of cause can you derife the class and call the constructor with params.
@param $table_name need to be set in the derived class to the db-table-name
@param $autoinc_id db-col-name of autoincrement id or ''
@param $db_key_cols array of all primary-key-columns in form dbName => internalName
@discussion the save function does NOT touch any other cols in the table!!!
@param $db_data_cols array of all data-cols
@param $debug turns on debug-messages
@param $empty_on_write string to be written to db if a col-value is '', eg. "''" or 'NULL' (default)
@param $non_db_cols all cols in data which are not (direct)in the db, for data_merge
*/
class so_sql
{
	var $public_functions = array(
		'init'	=> True,
		'data_merge' => True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	);
	var $db,$table_name;
	var $autoinc_id = '';
	var $db_key_cols = array(),$db_data_cols = array(); // key_cols mean primary keys
	var $db_uni_cols = array();
	var $db_cols;	// = $db_key_cols + $db_data_cols
	var $non_db_cols = array();
	var $data;		// holds the content of all db_cols
	var $debug = 0;
	var $empty_on_write = 'NULL';

	/*!
	@function so_sql
	@syntax so_sql( $app='',$table='' )
	@author ralfbecker
	@abstract constructor of the class
	@discussion NEED to be called from the constructor of the derived class
	@param $app, $table should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	*/
	function so_sql($app='',$table='')
	{
		$this->db = $GLOBALS['phpgw']->db;
		$this->db_cols = $this->db_key_cols + $this->db_data_cols;

		if ($app && $table)
		{
			$this->setup_table($app,$table);
		}
		$this->init();

		if ($this->debug)
		{
			echo "<p>so_sql('$app','$table')</p>\n";
			_debug_array($this);
		}
	}

	/*!
	@function setup_table
	@syntax setup_table( $app,$table )
	@author ralfbecker
	@abstract reads table-definition from <app>/setup/tables_current.inc.php
	@discussion Does NOT set a different internal-data-name. If you want this, you have to do so
	@discussion in a derifed class !!!
	*/
	function setup_table($app,$table)
	{
		include(PHPGW_SERVER_ROOT . "/$app/setup/tables_current.inc.php");

		if (!isset($phpgw_baseline[$table]))
		{
			echo "<p>Can't find table-definitions for App. '$app', Table '$table' !!!</p>\n";
			exit();
		}
		$this->table_name = $table;

		$table_def = $phpgw_baseline[$table];
		$this->db_key_cols = $this->db_data_cols = $this->db_cols = array();
		$this->autoinc_id = '';
		foreach($table_def['fd'] as $name => $def)
		{
			if (in_array($name,$table_def['pk']))
			{
				$this->db_key_cols[$name] = $name;
			}
			else
			{
				$this->db_data_cols[$name] = $name;
			}
			$this->db_cols[$name] = $name;

			if ($def['type'] == 'auto')
			{
				$this->autoinc_id = $name;
			}
			if (in_array($name,$table_def['uc']))
			{
				$this->db_uni_cols[$name] = $name;
			}
		}
	}

	/*!
	@function so_data_merge
	@syntax so_data_merge( $new )
	@author ralfbecker
	@abstract merges in new values from the given new data-array
	@param $new array in form col => new_value with values to set
	*/
	function data_merge($new)
	{
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
	}

	/*!
	@function db2data
	@abstract changes the data from the db-format to your work-format
	@discussion it gets called everytime when data is read from the db
	@discussion This function needs to be reimplemented in the derived class
	@param $data if given works on that array and returns result, else works on internal data-array
	*/
	function db2data($data=0)
	{
		if ($intern = !is_array($data))
		{
			$data = $this->data;
		}
		// do the necessare changes here

		if ($intern)
		{
			$this->data = $data;
		}
		return $data;
	}

	/*!
	@function data2db
	@abstract changes the data from your work-format to the db-format
	@discussion It gets called everytime when data gets writen into db or on keys for db-searches
	@discussion this needs to be reimplemented in the derived class
	@param $data if given works on that array and returns result, else works on internal data-array
	*/
	function data2db($data=0)
	{
		if ($intern = !is_array($data))
		{
			$data = $this->data;
		}
		// do the necessary changes here

		if ($intern)
		{
			$this->data = $data;
		}
		return $data;
	}

	/*!
	@function init
	@abstract initializes data with the content of key
	@param $keys array with keys in form internalName => value
	@result void
	*/
	function init($keys=array())
	{
		$this->data = array();

		$this->db2data();

		$this->data_merge($keys);
	}

	/*!
	@function read
	@abstract reads row matched by key and puts all cols in the data array
	@param $keys array with keys in form internalName => value, may be a scalar value if only one key
	@result data array if row could be retrived else False and data = array()
	*/
	function read($keys)
	{
		if (!is_array($keys))
		{
			$pk = array_keys($this->db_key_cols);
			if ($pk) $keys = array($pk[0] => $keys);
		}	
		
		$this->init($keys);

		$this->data2db();
		foreach ($this->db_key_cols as $db_col => $col)
		{
			if ($this->data[$col] != '')
			{
				$query .= ($query ? ' AND ':'')."$db_col='".addslashes($this->data[$col])."'";
			}
		}
		if (!$query)	// no primary key in keys, lets try the data_cols for a unique key
		{
			foreach($this->db_data_cols as $db_col => $col)
			{
				if ($this->data[$col] != '')
				{
					$query .= ($query ? ' AND ':'')."$db_col='".addslashes($this->data[$col])."'";
				}
			}
		}
		if (!$query)	// keys has no cols
		{
			$this->db2data();

			return False;
		}
		$this->db->query($sql = "SELECT * FROM $this->table_name WHERE $query",__LINE__,__FILE__);

		if ($this->debug)
		{
			echo "<p>read(): sql = '$sql': ";
		}
		if (!$this->db->next_record())
		{
			if ($this->autoinc_id)
			{
				unset($this->data[$this->db_key_cols[$this->autoinc_id]]);
			}
			if ($this->debug) echo "nothing found !!!</p>\n";

			$this->db2data();

			return False;
		}
		foreach ($this->db_cols as $db_col => $col)
		{
			$this->data[$col] = $this->db->f($db_col);
		}
		$this->db2data();

		if ($this->debug)
		{
			echo "data =\n"; _debug_array($this->data);
		}
		return $this->data;
	}

	/*!
	@function save
	@abstracts saves the content of data to the db
	@param $keys if given $keys are copied to data before saveing => allows a save as
	@result 0 on success and errno != 0 else
	*/
	function save($keys='')
	{
		$this->data_merge($keys);

		if (!$this->autoinc_id)	// no autoincrement id, so we need to find out with read if key already in db
		{
			$data = $this->data;
			$new = !$this->read($data);
			$this->data = $data;
		}
		else
		{
			$new = !$this->data[$this->db_key_cols[$this->autoinc_id]];	// autoincrement idx is 0 => new
		}
		$this->data2db();

		if ($new)	// prepare an insert
		{
			foreach($this->db_cols as $db_col => $col)
			{
				if (!$this->autoinc_id || $db_col != $this->autoinc_id)	// not write auto-inc-id
				{
					$cols .= ($cols ? ',' : '') . $db_col;
					$vals .= ($vals ? ',' : '') . ($this->data[$col] == '' ?
						$this->empty_on_write : "'".addslashes($this->data[$col])."'");
				}
			}
			$this->db->query($sql = "INSERT INTO $this->table_name ($cols) VALUES ($vals)",__LINE__,__FILE__);

			if ($this->autoinc_id)
			{
				$this->data[$this->db_key_cols[$this->autoinc_id]] = $this->db->get_last_insert_id($this->table_name,$this->autoinc_id);
			}
		}
		else //update existing row, preserv other cols not used here
		{
			foreach($this->db_data_cols as $db_col => $col)
			{
				$vals .= ($vals ? ',':'') . "$db_col=".($this->data[$col] == '' ?
						$this->empty_on_write : "'".addslashes($this->data[$col])."'");
			}
			$keys = '';
			foreach($this->db_key_cols as $db_col => $col)
			{
				$keys .= ($keys ? ' AND ':'') . "$db_col='".addslashes($this->data[$col])."'";
			}
			$this->db->query($sql = "UPDATE $this->table_name SET $vals WHERE $keys",__LINE__,__FILE__);
		}
		if ($this->debug)
		{
			echo "<p>save(): sql = '$sql'</p>\n";
		}
		$this->db2data();

		return $this->db->errno;
	}

	/*!
	@function delete
	@abstract deletes row representing keys in internal data or the supplied $keys if != ''
	@param $keys if not '', array with col => value pairs to characterise the rows to delete
	@result affected rows, should be 1 if ok, 0 if an error
	*/
	function delete($keys='')
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
			$query .= ($query ? ' AND ' : '') . $db_col . "='" . addslashes($data[$col]) . "'";
		}
		$this->db->query($sql = "DELETE FROM $this->table_name WHERE $query",__LINE__,__FILE__);

		if ($this->debug)
		{
			echo "<p>delete(): sql = '$sql'</p>\n";
		}
		return $this->db->affected_rows();
	}

	/*!
	@function search
	@abstract searches db for rows matching searchcriteria
	@discussion '*' and '?' are replaced with sql-wildcards '%' and '_'
	@param $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	@param $only_keys True returns only keys, False returns all cols
	@param $order_by fieldnames + {ASC|DESC} separated by colons ','
	@param $extra_cols string to be added to the SELECT, eg. (count(*) as num)
	@param $wildcard string appended befor and after each criteria
	@param $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	@param $op defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	@result array of matching rows (the row is an array of the cols) or False
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND')
	{
		if (!is_array($criteria))
		{
			$query = $criteria ? ' WHERE '.$criteria : '';
		}
		else
		{
			$criteria = $this->data2db($criteria);
			foreach($this->db_cols as $db_col => $col)
			{	//echo "testing col='$col', criteria[$col]='".$criteria[$col]."'<br>";
				if (array_key_exists($col,$criteria) && ($empty || $criteria[$col] != ''))
				{
					if ($criteria[$col] === NULL)
					{
						$query .= ($query ? " $op " : ' WHERE ') . "$db_col IS NULL";
					}	
					else
					{
						$query .= ($query ? " $op " : ' WHERE ') . $db_col .
						($wildcard || strstr($criteria[$col],'*') || strstr($criteria[$col],'?') ?
						" LIKE '$wildcard".strtr(str_replace('_','\\_',addslashes($criteria[$col])),'*?','%_')."$wildcard'" :
						"='".addslashes($criteria[$col])."'");
					}
				}
			}
		}
		$this->db->query($sql = 'SELECT '.($only_keys ? implode(',',$this->db_key_cols) : '*').
		   ($extra_cols != '' ? ",$extra_cols" : '')." FROM $this->table_name $query" .
			($order_by != '' ? " ORDER BY $order_by" : ''),__LINE__,__FILE__);

		if ($this->debug)
		{
			echo "<p>search(only_keys=$only_keys,order_by='$order_by',wildcard='$wildcard',empty=$empty)<br>sql = '$sql'</p>\n";
			echo "<br>criteria = "; _debug_array($criteria);
		}
		$arr = array();
		$cols = $only_keys ? $this->db_key_cols : $this->db_cols;
		for ($n = 0; $this->db->next_record(); ++$n)
		{
			$row = array();
			foreach($cols as $db_col => $col)
			{
				$row[$col] = $this->db->f($db_col);
			}
			$arr[] = $this->db2data($row);
		}
		return $n ? $arr : False;
	}

	/*!
	@function not_unique
	@syntax not_unique( $data='' )
	@author ralfbecker
	@abstract Check if values for unique keys are unique
	@param $data data-set to check, defaults to $this->data
	@result 0: all keys are unique, 1: first key not unique, 2: ...
	*/
	function not_unique($data='')
	{
		if (!is_array($data))
		{
			$data = $this->data;
		}
		$n = 1;
		foreach($this->db_uni_cols as $db_col => $col)
		{
			if (list($other) = $this->search(array($db_col => $data[$col])))
			{
				foreach($this->db_key_cols as $db_key_col => $key_col)
				{
					if ($data[$key_col] != $other[$key_col]) 
					{
						if ($this->debug)
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
};
