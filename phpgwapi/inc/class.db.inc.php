<?php
	/**************************************************************************\
	* phpGroupWare API - database support via ADOdb                            *
	* ------------------------------------------------------------------------ *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published    *
	* by the Free Software Foundation; either version 2.1 of the License, or   *
	* any later version.                                                       *
	\**************************************************************************/

	/* $Id$ */

   /**
	* Database abstraction library
	*
	* This allows eGroupWare to use multiple database backends via ADOdb
	*
	* @package phpgwapi
	* @subpackage db
	* @author RalfBecker@outdoor-training.de
	* @license LGPL
	*/

	if(empty($GLOBALS['phpgw_info']['server']['db_type']))
	{
		$GLOBALS['phpgw_info']['server']['db_type'] = 'mysql';
	}
	if (@$GLOBALS['phpgw_info']['server']['use_adodb'])
	{
		include_once('adodb/adodb.inc.php');
	}

	class db_
	{
		/**
		* @var string $type database type
		*/
		var $type     = '';

		/**
		* @var string $Host database host to connect to
		*/
		var $Host     = '';

		/**
		* @var string $Database name of database to use
		*/
		var $Database = '';
        
		/**
		* @var string $User name of database user
		*/
		var $User     = '';
        
		/**
		* @var string $Password password for database user
		*/
		var $Password = '';

		/**
		* @var bool $auto_stripslashes automatically remove slashes when returning field values - default False
		*/
		var $auto_stripslashes = False;
        
		/**
		* @var int $Auto_Free automatically free results - 0 no, 1 yes
		*/
		var $Auto_Free     = 0;
        
		/**
		* @var int $Debug enable debuging - 0 no, 1 yes
		*/
		var $Debug         = 0;
        
		/**
		* @var string $Halt_On_Error "yes" (halt with message), "no" (ignore errors quietly), "report" (ignore errror, but spit a warning)
		*/
		var $Halt_On_Error = 'no';//'yes';
        
		/**
		* @var string $Seq_Table table for storing sequences ????
		*/
		var $Seq_Table     = 'db_sequence';

		/**
		* @var array $Record current record
		*/
		var $Record   = array();
        
		/**
		* @var int row number for current record
		*/
		var $Row;

		/**
		* @var int $Errno internal rdms error number for last error
		*/
		var $Errno    = 0;
        
		/**
		* @var string descriptive text from last error
		*/
		var $Error    = '';

		//i am not documenting private vars - skwashd :)
        var $xmlrpc = False;
		var $soap   = False;
		var $Link_ID = 0;
		var $Query_ID = 0;

		/**
		* @param string $query query to be executed (optional)
		*/

		function db($query = '')
		{
			$this->query($query);
		}

		function db_($query='') {}	// only for NOT useing ADOdb

		/**
		* @return int current connection id
		*/
		function link_id()
		{
			return $this->Link_ID;
		}

		/**
		* @return int id of current query
		*/
		function query_id()
		{
			return $this->Query_ID;
		}

		/**
		* Open a connection to a database
		*
		* @param string $Database name of database to use (optional)
		* @param string $Host database host to connect to (optional)
		* @param string $User name of database user (optional)
		* @var string $Password password for database user (optional)
		*/
		function connect($Database = '', $Host = '', $User = '', $Password = '')
		{
			/* Handle defaults */
			if ($Database == '')
			{
				$Database = $this->Database;
			}
			if ($Host == '')
			{
				$Host     = $this->Host;
			}
			if ($User == '')
			{
				$User     = $this->User;
			}
			if ($Password == '')
			{
				$Password = $this->Password;
			}
			if (!$this->Link_ID)
			{
				$this->type = $GLOBALS['phpgw_info']['server']['db_type'];
				if (!is_object($GLOBALS['phpgw']->ADOdb))
				{
					switch($this->type)	// convert to ADO db-type-names
					{
						case 'pgsql':
							$type = 'postgres';
							break;
						default:
							$type = $this->type;
					}
					$GLOBALS['phpgw']->adodb = ADONewConnection($type);
					if (!$GLOBALS['phpgw']->adodb)
					{
						$this->halt("No ADOdb support for '$type' !!!");
					}
					$connect = $GLOBALS['phpgw_info']['server']['db_persistent'] ? 'PConnect' : 'Connect';
					if (!$connect = $GLOBALS['phpgw']->adodb->$connect($Host, $User, $Password, $Database))
					{
						$this->halt("ADOdb::$connect($Host, $User, \$Password, $Database) failed.");
					}
				}
				$this->Link_ID = &$GLOBALS['phpgw']->adodb;
			}
			return $this->Link_ID;
		}

		/**
		* Close a connection to a database - not needed for ADOdb connection
		*/
		function disconnect()
		{

		}

		/**
		* Escape strings before sending them to the database
		*
		* @param string $str the string to be escaped
		* @return string escaped sting
		*/
		function db_addslashes($str)
		{
			if (!isset($str) || $str == '')
			{
				return '';
			}
			// REMOVE-IF-ONLY-ADODB
			if (!@$GLOBALS['phpgw_info']['server']['use_adodb'])
			{
				return addslashes($str);
			}
			if (!$this->Link_ID)
			{
				$this->connect();
			}
			// the substring is needed as the string is already in quotes
			return substr($this->Link_ID->quote($str),1,-1);
		}

		/**
		* Convert a unix timestamp to a rdms specific timestamp
		*
		* @param int unix timestamp
		* @return string rdms specific timestamp
		*/
		function to_timestamp($epoch)
		{
			// the substring is needed as the string is already in quotes
			return substr($this->Link_ID->DBTimeStamp($epoch),1,-1);
		}

		/**
		* Convert a rdms specific timestamp to a unix timestamp
		*
		* @param string rdms specific timestamp
		* @return int unix timestamp
		*/
		function from_timestamp($timestamp)
		{
			return $this->Link_ID->UnixTimeStamp($timestamp);
		}

		/**
		* @deprecated
		* @see limit_query()
		*/
		function limit($start)
		{}

		/**
		* Discard the current query result
		*/
		function free()
		{
			unset($this->Query_ID);	// else copying of the db-object does not work
			$this->Query_ID = 0;
		}

		/**
		* Execute a query
		*
		* @param string $Query_String the query to be executed
		* @param mixed $line the line method was called from - use __LINE__
		* @param string $file the file method was called from - use __FILE__
		* @param int $offset row to start from
		* @param int $num_rows number of rows to return (optional), if unset will use $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']
		* @return int current query id if sucesful and null if fails
		*/
		function query($Query_String, $line = '', $file = '', $offset=0, $num_rows=-1)
		{
			if ($Query_String == '')
			{
				return 0;
			}
			if (!$this->connect())
			{
				return 0; /* we already complained in connect() about that. */
			};

			# New query, discard previous result.
			if ($this->Query_ID)
			{
				$this->free();
			}
			if ($this->Link_ID->fetchMode != ADODB_FETCH_BOTH)
			{
				$this->Link_ID->SetFetchMode(ADODB_FETCH_BOTH);
			}
			if (! $num_rows)
			{
				$num_rows = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}
			if ($num_rows > 0)
			{
				$this->Query_ID = $this->Link_ID->SelectLimit($Query_String,$num_rows,intval($offset));
			}
			else
			{
				$this->Query_ID = $this->Link_ID->Execute($Query_String);
			}
			$this->Row = 0;
			$this->Errno  = $this->Link_ID->ErrorNo();
			$this->Error  = $this->Link_ID->ErrorMsg();

			if (! $this->Query_ID)
			{
				$this->halt("Invalid SQL: ".$Query_String, $line, $file);
			}
			return $this->Query_ID;
		}

		/**
		* Execute a query with limited result set
		*
		* @param string $Query_String the query to be executed
		* @param int $offset row to start from
		* @param mixed $line the line method was called from - use __LINE__
		* @param string $file the file method was called from - use __FILE__
		* @param int $num_rows number of rows to return (optional), if unset will use $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs']
		* @return int current query id if sucesful and null if fails
		*/
		function limit_query($Query_String, $offset, $line = '', $file = '', $num_rows = '')
		{
			return $this->query($Query_String,$line,$file,$offset,$num_rows);
		}

		/**
		* Move to the next row in the results set
		*
		* @return bool was another row found?
		*/
		function next_record()
		{
			if (!$this->Query_ID)
			{
				$this->halt('next_record called with no query pending.');
				return 0;
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

			return True;
		}

		/**
		* Move to position in result set
		*
		* @param int $pos required row (optional), default first row
		* @return int 1 if sucessful or 0 if not found
		*/
		function seek($pos = 0)
		{
			if (!$this->Query_ID->Move($this->Row = $pos))
			{
				$this->halt("seek($pos) failed: resultset has " . $this->num_rows() . " rows");
				$this->Query_ID->Move( $this->num_rows() );
				$this->Row = $this->num_rows();
				return False;
			}
			return True;
		}

		/**
		* Begin Transaction
		*
		* @return int current transaction id
		*/
		function transaction_begin()
		{
			//return $this->Link_ID->BeginTrans();
			return $this->Link_ID->StartTrans();
		}

		/**
		* Complete the transaction
		*
		* @return bool True if sucessful, False if fails
		*/
		function transaction_commit()
		{
			//return $this->Link_ID->CommitTrans();
			return $this->Link_ID->CompleteTrans();
		}

		/**
		* Rollback the current transaction
		*
		* @return bool True if sucessful, False if fails
		*/
		function transaction_abort()
		{
			//return $this->Link_ID->RollbackTrans();
			return $this->Link_ID->FailTrans();
		}

		/**
		* Find the primary key of the last insertion on the current db connection
		*
		* @param string $table name of table the insert was performed on
		* @param string $field the autoincrement primary key of the table
		* @return int the id, -1 if fails
		*/
		function get_last_insert_id($table, $field)
		{
			$id = $this->Link_ID->Insert_ID();

			if ($id === False)	// function not supported
			{
				echo "<p>db::get_last_insert_id(table='$table',field='$field') not yet implemented for db-type '$this->type'</p>\n";
				return -1;
			}
			if ($this->type != 'pgsql' || $id == -1)
			{
				return $id;
			}
			// pgsql code to transform the OID into the real id
			$id = $this->Link_ID->GetOne("SELECT $field FROM $table WHERE oid=$id");

			return $id !== False ? $id : -1;
		}

		/**
		* Lock a table
		*
		* @param string $table name of table to lock
		* @param string $mode type of lock required (optional), default write
		* @return bool True if sucessful, False if fails
		*/
		function lock($table, $mode='write')
		{}

		/**
		* Unlock a table
		*
		* @return bool True if sucessful, False if fails
		*/
		function unlock()
		{}

		/**
		* Get the number of rows affected by last update
		*
		* @return int number of rows
		*/
		function affected_rows()
		{
			return $this->Link_ID->Affected_Rows();
		}

		/**
		* Number of rows in current result set
		*
		* @return int number of rows
		*/
		function num_rows()
		{
			return $this->Query_ID->RecordCount();
		}

		/**
		* Number of fields in current row
		*
		* @return int number of fields
		*/
		function num_fields()
		{
			return $this->Query_ID->FieldCount();
		}

		/**
		* short hand for @see num_rows()
		*/
		function nf()
		{
			return $this->num_rows();
		}

		/**
		* short hand for print @see num_rows
		*/
		function np()
		{
			print $this->num_rows();
		}

		/**
		* Return the value of a column
		*
		* @param string/integer $Name name of field or positional index starting from 0
		* @param bool $strip_slashes string escape chars from field(optional), default false
		* @return string the field value
		*/
		function f($Name, $strip_slashes = False)
		{
			if ($strip_slashes || ($this->auto_stripslashes && ! $strip_slashes))
			{
				return stripslashes($this->Record[$Name]);
			}
			else
			{
				return $this->Record[$Name];
			}
		}

		/**
		* Print the value of a field
		*
		* @param string $Name name of field to print
		* @param bool $strip_slashes string escape chars from field(optional), default false
		*/
		function p($Name, $strip_slashes = True)
		{
			print $this->f($Name, $strip_slashes);
		}

		/**
		* Returns a query-result-row as an associative array (no numerical keys !!!)
		*
		* @param bool $do_next_record should next_record() be called or not (default not)
		* @return array/bool the associative array or False if no (more) result-row is availible
		*/
		function row($do_next_record=False)
		{
			if ($do_next_record && !$this->next_record() || !is_array($this->Record))
			{
				return False;
			}
			$result = array();
			foreach($this->Record as $column => $value)
			{
				if (!is_numeric($column))
				{
					$result[$column] = $value;
				}
			}
			return $result;
		}

		/**
		* Get the id for the next sequence - not implemented!
		*
		* This seems not to be used anywhere in eGroupWhere !!!
		*
		* @param string $seq_name name of the sequence
		* @return int sequence id
		*/
		function nextid($seq_name)
		{
			echo "<p>db::nextid(sequence='$seq_name') not yet implemented</p>\n";
		}

		/**
		* Error handler
		*
		* @param string $msg error message
		* @param int $line line of calling method/function (optional)
		* @param string $file file of calling method/function (optional)
		*/
		function halt($msg, $line = '', $file = '')
		{
			if ($this->Link_ID)		// only if we have a link, else infinite loop
			{
				$this->Error = $this->Link_ID->ErrorMsg();	// need to be BEFORE unlock,
				$this->Errno = $this->Link_ID->ErrorNo();	// else we get its error or none

				$this->unlock();	/* Just in case there is a table currently locked */
			}
			if ($this->Halt_On_Error == "no")
			{
				return;
			}
			$this->haltmsg($msg);

			if ($file)
			{
				printf("<br><b>File:</b> %s",$file);
			}
			if ($line)
			{
				printf("<br><b>Line:</b> %s",$line);
			}
			printf("<br><b>Function:</b> %s\n",function_backtrace(2));

			if ($this->Halt_On_Error != "report")
			{
				echo "<p><b>Session halted.</b>";
				$GLOBALS['phpgw']->common->phpgw_exit(True);
			}
		}

		function haltmsg($msg)
		{
			printf("<p><b>Database error:</b> %s<br>\n", $msg);
			if ($this->Errno != "0" && $this->Error != "()")
			{
				printf("<b>$this->type Error</b>: %s (%s)<br>\n",$this->Errno,$this->Error);
			}
		}

		/**
		* Get description of a table
		*
		* Beside the column-name all other data depends on the db-type !!!
		*
		* @param string $table name of table to describe
		* @param bool $full optional, default False summary information, True full information
		* @return array table meta data
		*/
		function metadata($table='',$full=false)
		{
			$columns = $this->Link_ID->MetaColumns($table);
			//echo "<b>metadata</b>('$table')=<pre>\n".print_r($columns,True)."</pre>\n";

			$metadata = array();
			$i = 0;
			foreach($columns as $column)
			{
				$metadata[$i] = $column;
				$metadata[$i]['table'] = $table;
				if ($full)
				{
					$metadata['meta'][$column['name']] = $i;
				}
				++$i;
			}
			if ($full)
			{
				$metadata['num_fields'] = $i;
			}
			return $metadata;
		}

		/**
		* Get a list of table names in the current database
		*
		* @return array list of the tables
		*/
		function table_names()
		{
			$this->connect();
			$result = array();
			$tables = $this->Link_ID->MetaTables('TABLES');
			if (is_array($tables))
			{
				foreach($tables as $table)
				{
					$result[] = array(
						'table_name'      => $table,
						'tablespace_name' => $this->Database,
						'database'        => $this->Database
     				);
				}
			}
			return $result;
		}

		/**
		* Return a list of indexes in current database
		*
		* @return array list of indexes
		*/
		function index_names()
		{
			echo "<p>db::index_names() not yet implemented</p>\n";
			return array();
		}

		/**
		* Create a new database
		*
		* @param string $adminname name of database administrator user (optional)
		* @param string $adminpasswd password for the database administrator user (optional)
		*/
		function create_database($adminname = '', $adminpasswd = '')
		{
			echo "<p>db::create_database(user='$adminname',\$pw) not yet implemented</p>\n";
		}

		/**
		* Implodes an array of column-value pairs for the use in sql-querys.
		* All data is either run through addslashes() or intval().
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $glue in most cases this will be either ',' or ' AND ', depending you your query
		* @param array  $array column-value pairs
		* @param boolean $use_key should a "$key=" prefix each value, typicaly set to False for insert querys
		* @param array/boolean $only if set to an array only colums which are set (as data !!!) are written
		*	typicaly used to form a WHERE-clause from the primary keys
		* @param array/boolean $column_definitions this can be set to the column-definitions-array
		*	of your table ($tables_baseline[$table]['fd'] of the setup/tables_current.inc.php file).
		*	If its set, the column-type-data determinates if intval() or addslashes is used.
		*/
		function column_data_implode($glue,$array,$use_key=True,$only=False,$column_definitions=False)
		{
			if (!$column_definitions)
			{
				$column_definitions = $this->column_definitions;
			}
			$pairs = array();
			foreach($array as $key => $data)
			{
				if (!$only || in_array($key,$only))
				{
					$column_type = is_array($column_definitions) ? @$colum_definitions[$key]['type'] : False;
					$values[] = ($use_key ? $key.'=' : '').
						($column_type == 'int' || $colum_type == 'auto' ?
						intval($data) : "'".$this->db_addslashes($data)."'");
				}
			}
			return implode($glue,$values);
		}

		/**
		* Sets the default column-definitions for use with column_data_implode()
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param array/boolean $column_definitions this can be set to the column-definitions-array
		*	of your table ($tables_baseline[$table]['fd'] of the setup/tables_current.inc.php file).
		*	If its set, the column-type-data determinates if intval() or addslashes is used.
		*/
		function set_column_definitions($column_definitions=False)
		{
			$this->column_definitions=$column_definitions;
		}

		/**
		* reads the table-definitions from the app's setup/tables_current.inc.php file
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $app name of the app
		* @param bool/string $table if set return only defintions of that table, else return all defintions
		* @return the table-defintions or False if file not found
		*/
		function get_table_definitions($app,$table=False)
		{
			if (!isset($this->table_definitions[$app]))
			{
				$tables_current = PHPGW_INCLUDE_ROOT . "/$app/setup/tables_current.inc.php";

				if (!@file_exists($tables_current))
				{
					return $this->table_definitions[$app] = False;
				}
				include($tables_current);
				$this->table_definitions[$app] = $phpgw_baseline;
			}
			if ($table && (!$this->table_definitions[$app] || !isset($this->table_definitions[$app][$table])))
			{
				return False;
			}
			return $table ? $this->table_definitions[$app][$table] : $this->table_definitions[$app];
		}
	}

	// REMOVE-IF-ONLY-ADODB
	if (!@$GLOBALS['phpgw_info']['server']['use_adodb'])
	{
		include(PHPGW_API_INC.'/class.db_'.$GLOBALS['phpgw_info']['server']['db_type'].'.inc.php');
	}
	else
	{
		class db extends db_{}
	}
?>
