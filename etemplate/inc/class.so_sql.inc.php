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
	var $non_db_cols = array();

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
			$this->setup_table($app,$table);

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
		reset($table_def['fd']);
		while (list($name,$def) = each($table_def['fd']))
		{
			if (in_array($name,$table_def['pk']))
				$this->db_key_cols[$name] = $name;
			else
				$this->db_data_cols[$name] = $name;
			$this->db_cols[$name] = $name;

			if ($def['type'] == 'auto')
				$this->autoinc_id = $name;

			if (in_array($name,$table_def['uc']))
				$this->db_uni_cols[$name] = $name;
		}
	}

	/*!
	@function so_data_merge
	@syntax so_data_merge( $new )
	@author ralfbecker
	@abstract merges in new values from the given new data-array
	@param $new array in form col => new_value with values to set
	*/
	function so_sql_data_merge($new)
	{
		if (!is_array($new) || !count($new))
			return;

		for (reset($this->db_cols); list($db_col,$col) = each($this->db_cols); )
			if (isset($new[$col]))
				$this->data[$col] = $new[$col];

		for (reset($this->non_db_cols); list($db_col,$col) = each($this->non_db_cols); )
			if (isset($new[$col]))
				$this->data[$col] = $new[$col];
	}

	/*!
	@function data_merge
	@abstract just an convinient alias for so_sql_data_merge, might be reimplemented in derived class
	@parms as for so_sql_data_merge
	*/
	function data_merge($new)
	{
		$this->so_sql_data_merge($new);
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
			$data = $this->data;

		// do the necessare changes here

		if ($intern)
			$this->data = $data;

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
			$data = $this->data;

		// do the necessary changes here

		if ($intern)
			$this->data = $data;

		return $data;
	}

	/*!
	@function so_sql_init
	@abstract initializes data with the content of key
	@param $keys array with keys in form internalName => value
	@result void
	*/
	function so_sql_init($keys=array())
	{
		$this->data = array();

		$this->db2data();

		$this->data_merge($keys);
	}

	/*!
	@function init
	@abstract just an convinient alias for so_sql_init, might be reimplemented in derived class
	@parms as for so_sql_init
	*/
	function init($keys=array())
	{
		$this->so_sql_init($keys);
	}

	/*!
	@function so_sql_read
	@abstract reads row matched by key and puts all cols in the data array
	@param $keys array with keys in form internalName => value, may be a scalar value if only one key
	@result data array if row could be retrived else False and data = array()
	*/
	function so_sql_read($keys)
	{
		$this->init($keys);

		$this->data2db();
		for(reset($this->db_key_cols); list($db_col,$col) = each($this->db_key_cols); )
		{
			if ($this->data[$col] != '')
				$query .= ($query ? ' AND ':'')."$db_col='".addslashes($this->data[$col])."'";
		}
		if (!$query)	// no primary key in keys, lets try the data_cols for a unique key
			for(reset($this->db_data_cols); list($db_col,$col) = each($this->db_data_cols); )
			{
				if ($this->data[$col] != '')
					$query .= ($query ? ' AND ':'')."$db_col='".addslashes($this->data[$col])."'";
			}

		if (!$query)	// keys has no cols
		{
			$this->db2data();

			return False;
		}
		$this->db->query($sql = "SELECT * FROM $this->table_name WHERE $query",__LINE__,__FILE__);

		if ($this->debug)
			echo "<p>so_sql_read(): sql = '$sql': ";

		if (!$this->db->next_record())
		{
			if ($this->autoinc_id)
				unset($this->data[$this->db_key_cols[$this->autoinc_id]]);

			if ($this->debug) echo "nothing found !!!</p>\n";

			$this->db2data();

			return False;
		}
		for (reset($this->db_cols); list($db_col,$col) = each($this->db_cols); )
			$this->data[$col] = $this->db->f($db_col);

		$this->db2data();

		if ($this->debug)
		{
			echo "data =\n"; _debug_array($this->data);
		}
		return $this->data;
	}

	/*!
	@function read
	@abstract just an convinient alias for so_sql_read, might be reimplemented in derived class
	@parms as for so_sql_read
	*/
	function read($keys=array())
	{
		return $this->so_sql_read($keys);
	}

	/*!
	@function so_sql_save
	@abstracts saves the content of data to the db
	@param $keys if given $keys are copied to data before saveing => allows a save as
	@result 0 on success and errno != 0 else
	*/
	function so_sql_save($keys='')
	{
		$this->data_merge($keys);

		if (!$this->autoinc_id)	// no autoincrement id, so we need to find out with read if key already in db
		{
			$data = $this->data;
			$new = !$this->read($data);
			$this->data = $data;
		}
		else
			$new = !$this->data[$this->db_key_cols[$this->autoinc_id]];	// autoincrement idx is 0 => new

		$this->data2db();

		if ($new)	// prepare an insert
		{
			for(reset($this->db_cols); list($db_col,$col) = each($this->db_cols); )
			{
				$cols .= ($cols ? ',' : '') . $db_col;
				$vals .= ($vals ? ',' : '') . ($this->data[$col] == '' ?
					$this->empty_on_write : "'".addslashes($this->data[$col])."'");
			}
			$this->db->query($sql = "INSERT INTO $this->table_name ($cols) VALUES ($vals)",__LINE__,__FILE__);

			if ($this->autoinc_id)
				$this->data[$this->db_key_cols[$this->autoinc_id]] = $this->db->get_last_insert_id($this->table_name,$this->autoinc_id);
		}
		else //update existing row, preserv other cols not used here
		{
			for(reset($this->db_data_cols); list($db_col,$col) = each($this->db_data_cols); )
				$vals .= ($vals ? ',':'') . "$db_col=".($this->data[$col] == '' ?
						$this->empty_on_write : "'".addslashes($this->data[$col])."'");

			$keys = '';
			for(reset($this->db_key_cols); list($db_col,$col) = each($this->db_key_cols); )
				$keys .= ($keys ? ',':'') . "$db_col='".addslashes($this->data[$col])."'";

			$this->db->query($sql = "UPDATE $this->table_name SET $vals WHERE $keys",__LINE__,__FILE__);
		}
		if ($this->debug)
			echo "<p>so_sql_save(): sql = '$sql'</p>\n";

		$this->db2data();

		return $this->db->errno;
	}

	/*!
	@function save
	@abstract just an convinient alias for so_sql_save, might be reimplemented in derived class
	@parms as for so_sql_save
	*/
	function save($keys='')
	{
		return $this->so_sql_save($keys);
	}

	/*!
	@function so_sql_delete
	@abstract deletes row representing keys in internal data or the supplied $keys if != ''
	@param $keys if not '', array with col => value pairs to characterise the rows to delete
	@result affected rows, should be 1 if ok, 0 if an error
	*/
	function so_sql_delete($keys='')
	{
		if (!is_array($keys) || !count($keys))	// use internal data
		{
			$data = $this->data;
			$keys = $this->db_key_cols;
		}
		else	// data and keys are supplied in $keys
		{
			$data = $keys; $keys = array();
			for(reset($this->db_cols); list($db_col,$col) = each($this->db_cols); )
				if (isset($data[$col]))
					$keys[$db_col] = $col;
		}
		$data = $this->data2db($data);

		for (reset($keys); list($db_col,$col) = each($keys); )
			$query .= ($query ? ' AND ' : '') . $db_col . "='" . addslashes($data[$col]) . "'";

		$this->db->query($sql = "DELETE FROM $this->table_name WHERE $query",__LINE__,__FILE__);

		if ($this->debug)
			echo "<p>so_sql_delete(): sql = '$sql'</p>\n";

		return $this->db->affected_rows();
	}

	/*!
	@function delete
	@abstract just an convinient alias for so_sql_delete, might be reimplemented in derived class
	@parms as for so_sql_delete
	*/
	function delete($keys='')
	{
		return $this->so_sql_delete($keys);
	}

	/*!
	@function so_sql_search
	@abstract searches db for rows matching searchcriteria
	@discussion '*' and '?' are replaced with sql-wildcards '%' and '_'
	@param $criteria array of key and data cols
	@param $only_keys True returns only keys, False returns all cols
	@param $order_by fieldnames + {ASC|DESC} separated by colons ','
	@param $wildcard string appended befor and after each criteria
	@param $empty False=empty criteria are ignored in query, True=empty have to be empty in row
	@result array of matching rows (the row is an array of the cols) or False
	*/
	function so_sql_search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False)
	{
		$criteria = $this->data2db($criteria);

		for (reset($this->db_cols); list($db_col,$col) = each($this->db_cols); )
		{	//echo "testing col='$col', criteria[$col]='".$criteria[$col]."'<br>";
			if (isset($criteria[$col]) && ($empty || $criteria[$col] != ''))
					$query .= ($query ? ' AND ' : ' WHERE ') . $db_col .
					($wildcard || strstr($criteria[$col],'*') || strstr($criteria[$col],'?') ?
					" LIKE '$wildcard".strtr(str_replace('_','\\_',addslashes($criteria[$col])),'*?','%_')."$wildcard'" :
					"='".addslashes($criteria[$col])."'");
		}
		$this->db->query($sql = 'SELECT '.($only_keys ? implode(',',$this->db_key_cols) : '*').
		   ($extra_cols != '' ? ",$extra_cols" : '')." FROM $this->table_name $query" .
			($order_by != '' ? " ORDER BY $order_by" : ''),__LINE__,__FILE__);

		if ($this->debug)
		{
			echo "<p>so_sql_search(only_keys=$only_keys,order_by='$order_by',wildcard='$wildcard',empty=$empty)<br>sql = '$sql'</p>\n";
			echo "<br>criteria = "; _debug_array($criteria);
		}
		$arr = array();
		$cols = $only_keys ? $this->db_key_cols : $this->db_cols;
		for ($n = 0; $this->db->next_record(); ++$n)
		{
			$row = array();
			for (reset($cols); list($db_col,$col) = each($cols); )
				$row[$col] = $this->db->f($db_col);

			$arr[] = $this->db2data($row);
		}
		return $n ? $arr : False;
	}

	/*!
	@function search
	@abstract just an convinient alias for so_sql_search, might be reimplemented in derived class
	@parms as for so_sql_search
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False)
	{
		return $this->so_sql_search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty);
	}

	/*!
	@function so_sql_not_unique
	@syntax so_sql_not_unique( $data='' )
	@author ralfbecker
	@abstract Check if values for unique keys are unique
	@param $data data-set to check, defaults to $this->data
	@result 0: all keys are unique, 1: first key not unique, 2: ...
	*/
	function so_sql_not_unique($data='')
	{
		if (!is_array($data))
			$data = $this->data;

		reset($this->db_uni_cols);
		for ($n=1; list($db_col,$col) = each($this->db_uni_cols); ++$n)
		{
			if (list($other) = $this->search(array($db_col => $data[$col])))
			{
				reset($this->db_key_cols);
				while (list($db_key_col,$key_col) = each($this->db_key_cols))
				{
					if ($data[$key_col] != $other[$key_col]) {
						if ($this->debug)
						{
							echo "<p>not_unique in '$col' as for '$key_col': '${data[$key_col]}' != '${other[$key_col]}'</p>\n";
						}
						return $n;	// different entry => $n not unique
					}
				}
			}
		}
		return 0;
	}

	/*!
	@function not_unique
	@syntax not_unique( $data='' )
	@author ralfbecker
	@abstract just an convinient alias for so_sql_search, might be reimplemented in derived class
	@parms as for so_sql_not_unique
	*/
	function not_unique($data='')
	{
		return $this->so_sql_not_unique($data);
	}
};