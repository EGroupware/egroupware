<?php
	/**************************************************************************\
	* eGroupWare API - database support via ADOdb                              *
	* ------------------------------------------------------------------------ *
	* This program is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published    *
	* by the Free Software Foundation; either version 2.1 of the License, or   *
	* any later version.                                                       *
	\**************************************************************************/

	/* $Id$ */

	/*
	 * Database abstraction library
	 *
	 * This allows eGroupWare to use multiple database backends via ADOdb 4.20
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
	include_once(PHPGW_API_INC.'/adodb/adodb.inc.php');

	class db
	{
		/**
		* @var string $type database type
		*/
		var $Type     = '';

		/**
		* @var string $Host database host to connect to
		*/
		var $Host     = '';

		/**
		* @var string $Port port number of database to connect to
		*/
		var $Port     = '';

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
		var $privat_Link_ID = False;	// do we use a privat Link_ID or a reference to the global ADOdb object
		var $Query_ID = 0;

		/**
		* @param string $query query to be executed (optional)
		*/

		function db($query = '')
		{
			$this->query($query);
		}

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
		* @param string $Port database port to connect to (optional)
		* @param string $User name of database user (optional)
		* @param string $Password password for database user (optional)
		*/
		function connect($Database = NULL, $Host = NULL, $Port = NULL, $User = NULL, $Password = NULL,$Type = NULL)
		{
			/* Handle defaults */
			if (!is_null($Database) && $Database)
			{
				$this->Database = $Database;
			}
			if (!is_null($Host) && $Host)
			{
				$this->Host     = $Host;
			}
			if (!is_null($Port) && $Port)
			{
				$this->Port     = $Port;
			}
			if (!is_null($User) && $User)
			{
				$this->User     = $User;
			}
			if (!is_null($Password) && $Password)
			{
				$this->Password = $Password;
			}
			if (!is_null($Type) && $Type)
			{
				$this->Type = $Type;
			}
			elseif (!$this->Type)
			{
				$this->Type = $GLOBALS['phpgw_info']['server']['db_type'];
			}

			if (!$this->Link_ID)
			{
				foreach(array('Host','Database','User','Password') as $name)
				{
					$$name = $this->$name;
				}
				$type = $this->Type;

				switch($this->Type)	// convert to ADO db-type-names
				{
					case 'pgsql':
						$type = 'postgres';
						// create our own pgsql connection-string, to allow unix domain soccets if !$Host
						$Host = "dbname=$this->Database".($this->Host ? " host=$this->Host".($this->Port ? " port=$this->Port" : '') : '').
							" user=$this->User".($this->Password ? " password='".addslashes($this->Password)."'" : '');
						$User = $Password = $Database = '';	// to indicate $Host is a connection-string
						break;
					case 'mssql':
						if ($this->Port) $Host .= ','.$this->Port;
						break;
					default:
						if ($this->Port) $Host .= ':'.$this->Port;
						break;
				}

				if (!is_object($GLOBALS['phpgw']->ADOdb) ||	// we have no connection so far
					(is_object($GLOBALS['phpgw']->db) &&	// we connect to a different db, then the global one
						($this->Type != $GLOBALS['phpgw']->db->Type ||
						$this->Database != $GLOBALS['phpgw']->db->Database ||
						$this->User != $GLOBALS['phpgw']->db->User ||
						$this->Host != $GLOBALS['phpgw']->db->Host ||
						$this->Port != $GLOBALS['phpgw']->db->Port)))
				{
					if (!is_object($GLOBALS['phpgw']->ADOdb))	// use the global object to store the connection
					{
						$this->Link_ID = &$GLOBALS['phpgw']->ADOdb;
					}
					else
					{
						$this->privat_Link_ID = True;	// remember that we use a privat Link_ID for disconnect
					}
					$this->Link_ID = ADONewConnection($type);
					if (!$this->Link_ID)
					{
						$this->halt("No ADOdb support for '$type' !!!");
						return 0;	// in case error-reporting = 'no'
					}
					$connect = $GLOBALS['phpgw_info']['server']['db_persistent'] ? 'PConnect' : 'Connect';
					if (!$this->Link_ID->$connect($Host, $User, $Password, $Database))
					{
						$this->halt("ADOdb::$connect($Host, $User, \$Password, $Database) failed.");
						return 0;	// in case error-reporting = 'no'
					}
					//echo "new ADOdb connection<pre>".print_r($GLOBALS['phpgw']->ADOdb,True)."</pre>\n";

					if ($this->Type == 'mssql')
					{
						// this is the format ADOdb expects
						$this->Link_ID->Execute('SET DATEFORMAT ymd');
						// sets the limit to the maximum
						ini_set('mssql.textlimit',2147483647);
						ini_set('mssql.sizelimit',2147483647);
					}
				}
				else
				{
					$this->Link_ID = &$GLOBALS['phpgw']->ADOdb;
				}
			}
			//echo "<p>".print_r($this->Link_ID->ServerInfo(),true)."</p>\n";
			return $this->Link_ID;
		}

		/**
		* Close a connection to a database
		*/
		function disconnect()
		{
			if (!$this->privat_Link_ID)
			{
				unset($GLOBALS['phpgw']->ADOdb);
			}
			unset($this->Link_ID);
			$this->Link_ID = 0;
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			return $this->Link_ID->UnixTimeStamp($timestamp);
		}

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
		* @return ADORecordSet or false, if the query fails
		*/
		function query($Query_String, $line = '', $file = '', $offset=0, $num_rows=-1)
		{
			if ($Query_String == '')
			{
				return 0;
			}
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}

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
				$this->Query_ID = $this->Link_ID->SelectLimit($Query_String,$num_rows,(int)$offset);
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
		* @return ADORecordSet or false, if the query fails
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
			if ($this->Type == 'sapdb')
			{
				foreach($this->Record as $column => $value)
				{
					// add a lowercase version 
					$this->Record[strtolower($column)] = $value;
					// add a numeric version
					$this->Record[] = $value;
				}
			}
			return True;
		}

		/**
		* Move to position in result set
		*
		* @param int $pos required row (optional), default first row
		* @return boolean true if sucessful or false if not found
		*/
		function seek($pos = 0)
		{
			if (!$this->Query_ID  || !$this->Query_ID->Move($this->Row = $pos))
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
		* @return int/boolean current transaction-id, of false if no connection
		*/
		function transaction_begin()
		{
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			//$id = $this->Link_ID->PO_Insert_ID($table,$field);
			$id = $this->Link_ID->PO_Insert_ID($table,$field);	// simulates Insert_ID with "SELECT MAX($field) FROM $table" if not native availible

			if ($id === False)	// function not supported
			{
				echo "<p>db::get_last_insert_id(table='$table',field='$field') not yet implemented for db-type '$this->Type'</p>\n";
				return -1;
			}
			return $id;
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			return $this->Link_ID->Affected_Rows();
		}

		/**
		* Number of rows in current result set
		*
		* @return int number of rows
		*/
		function num_rows()
		{
			return $this->Query_ID ? $this->Query_ID->RecordCount() : False;
		}

		/**
		* Number of fields in current row
		*
		* @return int number of fields
		*/
		function num_fields()
		{
			return $this->Query_ID ? $this->Query_ID->FieldCount() : False;
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
					if ($this->Type == 'sapdb') $column = strtolower($column);

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
				if (is_object($GLOBALS['phpgw']->common))
				{
					$GLOBALS['phpgw']->common->phpgw_exit(True);
				}
				else	// happens eg. in setup
				{
					exit();
				}
			}
		}

		function haltmsg($msg)
		{
			printf("<p><b>Database error:</b> %s<br>\n", $msg);
			if (($this->Errno || $this->Error) && $this->Error != "()")
			{
				printf("<b>$this->Type Error</b>: %s (%s)<br>\n",$this->Errno,$this->Error);
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			$columns = $this->Link_ID->MetaColumns($table);
			//$columns = $this->Link_ID->MetaColumnsSQL($table);
			//echo "<b>metadata</b>('$table')=<pre>\n".print_r($columns,True)."</pre>\n";

			$metadata = array();
			$i = 0;
			foreach($columns as $column)
			{
				// for backwards compatibilty (depreciated)
				unset($flags);
				if($column->auto_increment) $flags .= "auto_increment ";
				if($column->primary_key) $flags .= "primary_key ";
				if($column->binary) $flags .= "binary ";

//				_debug_array($column);
				$metadata[$i] = array(
					'table' => $table,
					'name'  => $column->name,
					'type'  => $column->type,
					'len'   => $column->max_length,
					'flags' => $flags, // for backwards compatibilty (depreciated) used by JiNN atm
					'not_null' => $column->not_null,
					'auto_increment' => $column->auto_increment,
					'primary_key' => $column->primary_key,
					'binary' => $column->binary,
					'has_default' => $column->has_default,
					'default'  => $column->default_value,
				);
				$metadata[$i]['table'] = $table;
				if ($full)
				{
					$metadata['meta'][$column->name] = $i;
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
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			$result = array();
			$tables = $this->Link_ID->MetaTables('TABLES');
			if (is_array($tables))
			{
				foreach($tables as $table)
				{
					if ($this->Type == 'sapdb')
					{
						$table = strtolower($table);
					}
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
			$indices = array();
			if ($this->Type != 'pgsql')
			{
				echo "<p>db::index_names() not yet implemented for db-type '$this->Type'</p>\n";
				return $indices;
			}
			$this->query("SELECT relname FROM pg_class WHERE NOT relname ~ 'pg_.*' AND relkind ='i' ORDER BY relname");
			while ($this->next_record())
			{
				$indices[] = array(
					'index_name'      => $this->f(0),
					'tablespace_name' => $this->Database,
					'database'        => $this->Database,
				);
			}
			return $indices;
		}

		/**
		* Returns an array containing column names that are the primary keys of $tablename.
		*
		* @return array of columns
		*/
		function pkey_columns($tablename)
		{
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			return $this->Link_ID->MetaPrimaryKeys($tablename);
		}

		/**
		* Create a new database
		*
		* @param string $adminname name of database administrator user (optional)
		* @param string $adminpasswd password for the database administrator user (optional)
		*/
		function create_database($adminname = '', $adminpasswd = '')
		{
			$currentUser = $this->User;
			$currentPassword = $this->Password;
			$currentDatabase = $this->Database;

			$extra = array();
			switch ($this->Type)
			{
				case 'pgsql':
					$meta_db = 'template1';
					break;
				case 'mysql':
					$meta_db = 'mysql';
					$extra[] = "grant all on $currentDatabase.* to $currentUser@localhost identified by '$currentPassword'";
					break;
				default:
					echo "<p>db::create_database(user='$adminname',\$pw) not yet implemented for DB-type '$this->Type'</p>\n";
					break;
			}
			if ($adminname != '')
			{
				$this->User = $adminname;
				$this->Password = $adminpasswd;
				$this->Database = $meta_db;
			}
			$this->disconnect();
			$this->query("CREATE DATABASE $currentDatabase");
			foreach($extra as $sql)
			{
				$this->query($sql);
			}
			$this->disconnect();

			$this->User = $currentUser;
			$this->Password = $currentPassword;
			$this->Database = $currentDatabase;
			$this->connect();
		}

		/**
		 * concat a variable number of strings together, to be used in a query
		 *
		 * Example: $db->concat($db->quote('Hallo '),'username') would return
		 *	for mysql "concat('Hallo ',username)" or "'Hallo ' || username" for postgres
		 * @param string $str1 already quoted stringliteral or column-name, variable number of arguments
		 * @return string to be used in a query
		 */
		function concat($str1)
		{
			$args = func_get_args();

			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			return call_user_func_array(array(&$this->Link_ID,'concat'),$args);
		}

		/**
		* Correctly Quote Identifiers like table- or colmnnames for use in SQL-statements
		*
		* This is mostly copy & paste from adodb's datadict class
		* @param $name string
		* @return string quoted string
		*/
		function name_quote($name = NULL)
		{
			if (!is_string($name)) {
				return FALSE;
			}

			$name = trim($name);

			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}

			$quote = $this->Link_ID->nameQuote;

			// if name is of the form `name`, quote it
			if ( preg_match('/^`(.+)`$/', $name, $matches) ) {
				return $quote . $matches[1] . $quote;
			}

			// if name contains special characters, quote it
			if ( preg_match('/\W/', $name) ) {
				return $quote . $name . $quote;
			}

			return $name;
		}

		/**
		* Escape values before sending them to the database - prevents SQL injunction and SQL errors ;-)
		*
		* Please note that the quote function already returns necessary quotes: quote('Hello') === "'Hello'".
		* Int and Auto types are casted to int: quote('1','int') === 1, quote('','int') === 0, quote('Hello','int') === 0
		*
		* @param mixed $value the value to be escaped
		* @param string/boolean $type string the type of the db-column, default False === varchar
		* @param boolean $not_null is column NOT NULL, default true, else php null values are written as SQL NULL
		* @return string escaped sting
		*/
		function quote($value,$type=False,$not_null=true)
		{
			if ($this->Debug) echo "<p>db::quote('$value','$type')</p>\n";
			
			if (!$not_null && is_null($value))	// writing unset php-variables and thouse set to NULL now as SQL NULL
			{
				return 'NULL';
			}
			switch($type)
			{
				case 'int':
				case 'auto':
					return (int) $value;
			}
			if (!$this->Link_ID && !$this->connect())
			{
				return False;
			}
			switch($type)
			{
				case 'blob':
					if ($this->Type == 'mysql')
					{
						break;	// ADOdb has no BlobEncode for mysql and returns an unquoted string !!!
					}
					return "'" . $this->Link_ID->BlobEncode($value) . "'";
				case 'date':
					return $this->Link_ID->DBDate($value);
				case 'timestamp':
					return $this->Link_ID->DBTimeStamp($value);
			}
			return $this->Link_ID->quote($value);
		}

		/**
		* Implodes an array of column-value pairs for the use in sql-querys.
		* All data is run through quote (does either addslashes() or (int)) - prevents SQL injunction and SQL errors ;-).
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $glue in most cases this will be either ',' or ' AND ', depending you your query
		* @param array $array column-name / value pairs, if the value is an array all its array-values will be quoted
		*	according to the type of the column, and the whole array with be formatted like (val1,val2,...)
		*	If $use_key == True, an ' IN ' instead a '=' is used. Good for category- or user-lists.
		*	If the key is numerical (no key given in the array-definition) the value is used as is, eg.
		*	array('visits=visits+1') gives just "visits=visits+1" (no quoting at all !!!)
		* @param boolean/string $use_key If $use_key===True a "$key=" prefix each value (default), typically set to False
		*	or 'VALUES' for insert querys, on 'VALUES' "(key1,key2,...) VALUES (val1,val2,...)" is returned
		* @param array/boolean $only if set to an array only colums which are set (as data !!!) are written
		*	typicaly used to form a WHERE-clause from the primary keys.
		*	If set to True, only columns from the colum_definitons are written.
		* @param array/boolean $column_definitions this can be set to the column-definitions-array
		*	of your table ($tables_baseline[$table]['fd'] of the setup/tables_current.inc.php file).
		*	If its set, the column-type-data determinates if (int) or addslashes is used.
		* @return string SQL
		*/
		function column_data_implode($glue,$array,$use_key=True,$only=False,$column_definitions=False)
		{
			if (!is_array($array))	// this allows to give an SQL-string for delete or update
			{
				return $array;
			}
			if (!$column_definitions)
			{
				$column_definitions = $this->column_definitions;
			}
			if ($this->Debug) echo "<p>db::column_data_implode('$glue',".print_r($array,True).",'$use_key',".print_r($only,True).",<pre>".print_r($column_definitions,True)."</pre>\n";

			$keys = $values = array();
			foreach($array as $key => $data)
			{
				if (!$only || $only === True && isset($column_definitions[$key]) || is_array($only) && in_array($key,$only))
				{
					$keys[] = $this->name_quote($key);
					
					if (!is_int($key) && is_array($column_definitions) && !isset($column_definitions[$key]))
					{
						// give a warning that we have no column-type
						$this->halt("db::column_data_implode('$glue',".print_r($array,True).",'$use_key',".print_r($only,True).",<pre>".print_r($column_definitions,True)."</pre><b>nothing known about column '$key'!</b>");
					}
					$column_type = is_array($column_definitions) ? @$column_definitions[$key]['type'] : False;
					$not_null = is_array($column_definitions) && isset($column_definitions[$key]['nullable']) ? !$column_definitions[$key]['nullable'] : True;

					if (is_array($data))
					{
						foreach($data as $k => $v)
						{
							$data[$k] = $this->quote($v,$column_type,$not_null);
						}
						$values[] = ($use_key===True ? $key.' IN ' : '') . '('.implode(',',$data).')';
					}
					elseif (is_int($key) && $use_key===True)
					{
						$values[] = $data;
					}
					else
					{
						$values[] = ($use_key===True ? $this->name_quote($key) . '=' : '') . $this->quote($data,$column_type,$not_null);
					}
				}
			}
			return ($use_key==='VALUES' ? '('.implode(',',$keys).') VALUES (' : '').
				implode($glue,$values) . ($use_key==='VALUES' ? ')' : '');
		}

		/**
		* Sets the default column-definitions for use with column_data_implode()
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param array/boolean $column_definitions this can be set to the column-definitions-array
		*	of your table ($tables_baseline[$table]['fd'] of the setup/tables_current.inc.php file).
		*	If its set, the column-type-data determinates if (int) or addslashes is used.
		*/
		function set_column_definitions($column_definitions=False)
		{
			$this->column_definitions=$column_definitions;
		}

		/**
		 * Sets the application in which the db-class looks for table-defintions 
		 *
		 * Used by table_definitions, insert, update, select, expression and delete. If the app is not set via set_app, 
		 * it need to be set for these functions on every call
		 *
		 * @param string $app the app-name
		 */
		function set_app($app)
		{
			$this->app = $app;
		}

		/**
		* reads the table-definitions from the app's setup/tables_current.inc.php file
		*
		* The already read table-definitions are shared between all db-instances via $GLOBALS['phpgw_info']['apps'][$app]['table_defs']
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param bool/string $app name of the app or default False to use the app set by db::set_app or the current app
		* @param bool/string $table if set return only defintions of that table, else return all defintions
		* @return mixed array with table-defintions or False if file not found
		*/
		function get_table_definitions($app=False,$table=False)
		{
			if (!$app)
			{
				$app = $this->app ? $this->app : $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			if (isset($GLOBALS['phpgw_info']['apps']))	// this happens during the eGW startup, dont set it then !!!
			{
				$this->app_data = &$GLOBALS['phpgw_info']['apps'][$app];
			}
			if (!isset($this->app_data['table_defs']))
			{
				$tables_current = PHPGW_INCLUDE_ROOT . "/$app/setup/tables_current.inc.php";

				if (!@file_exists($tables_current))
				{
					return $this->app_data['table_defs'] = False;
				}
				include($tables_current);
				$this->app_data['table_defs'] = &$phpgw_baseline;
			}
			if ($table && (!$this->app_data['table_defs'] || !isset($this->app_data['table_defs'][$table])))
			{
				return False;
			}
			return $table ? $this->app_data['table_defs'][$table] : $this->app_data['table_defs'];
		}

		/**
		* Insert a row of data into a table or updates it if $where is given, all data is quoted according to it's type
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $table name of the table
		* @param array $data with column-name / value pairs
		* @param mixed $where string with where clause or array with column-name / values pairs to check if a row with that keys already exists, or false for an unconditional insert
		*	if the row exists db::update is called else a new row with $date merged with $where gets inserted (data has precedence)
		* @param int $line line-number to pass to query
		* @param string $file file-name to pass to query
		* @param string/boolean $app string with name of app or False to use the current-app
		* @return ADORecordSet or false, if the query fails
		*/
		function insert($table,$data,$where,$line,$file,$app=False)
		{
			if ($this->Debug) echo "<p>db::insert('$table',".print_r($data,True).",".print_r($where,True).",$line,$file,'$app')</p>\n";

			$table_def = $this->get_table_definitions($app,$table);

			$sql_append = '';
			if (is_array($where) && count($where))
			{
				switch($this->Type)
				{
					case 'sapdb': case 'maxdb':
						$sql_append = ' UPDATE DUPLICATES';
						break;
					default:
						$this->select($table,'count(*)',$where,$line,$file);
						if ($this->next_record() && $this->f(0))
						{
							return !!$this->update($table,$data,$where,$line,$file,$app);
						}
						break;
				}
				$data = array_merge($where,$data);	// the checked values need to be inserted too, value in data has precedence
			}
			$sql = "INSERT INTO $table ".$this->column_data_implode(',',$data,'VALUES',False,$table_def['fd']).$sql_append;

			return $this->query($sql,$line,$file);
		}

		/**
		* Updates the data of one or more rows in a table, all data is quoted according to it's type
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $table name of the table
		* @param array $data with column-name / value pairs
		* @param array $where column-name / values pairs and'ed together for the where clause
		* @param int $line line-number to pass to query
		* @param string $file file-name to pass to query
		* @param string/boolean $app string with name of app or False to use the current-app
		* @return ADORecordSet or false, if the query fails
		*/
		function update($table,$data,$where,$line,$file,$app=False)
		{
			if ($this->Debug) echo "<p>db::update('$table',".print_r($data,true).','.print_r($where,true).",$line,$file,'$app')</p>\n";
			$table_def = $this->get_table_definitions($app,$table);
			
			// use insert for MaxDB (with UPDATE DUBLICATES) if $data and $where dont intersect 
			if ($this->Type == 'sapdb')
			{
				// check if data contains any LONG columns
				foreach($data as $col => $val)
				{
					switch ($table_def['fd'][$col]['type'])
					{
						case 'text':
						case 'longtext':
						case 'blob':
							if (!count(array_intersect_assoc($data,$where)))
							{
								if ($this->Debug) echo "<p>db::update using db::insert('$table',,$line,$file,'$app') db::Type='$this->Type'</p>\n";
								return $this->insert($table,$data,$where,$line,$file,$app);
							}
							break;
					}
				}
			}
			$sql = "UPDATE $table SET ".
				$this->column_data_implode(',',$data,True,False,$table_def['fd']).' WHERE '.
				$this->column_data_implode(' AND ',$where,True,False,$table_def['fd']);

			return $this->query($sql,$line,$file);
		}

		/**
		* Deletes one or more rows in table, all data is quoted according to it's type
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $table name of the table
		* @param array $where column-name / values pairs and'ed together for the where clause
		* @param int $line line-number to pass to query
		* @param string $file file-name to pass to query
		* @param string/boolean $app string with name of app or False to use the current-app
		* @return ADORecordSet or false, if the query fails
		*/
		function delete($table,$where,$line,$file,$app=False)
		{
			$table_def = $this->get_table_definitions($app,$table);
			$sql = "DELETE FROM $table WHERE ".
				$this->column_data_implode(' AND ',$where,True,False,$table_def['fd']);

			return $this->query($sql,$line,$file);
		}

		/**
		 * Formats and quotes a sql expression to be used eg. as where-clause
		 *
		 * The function has a variable number of arguments, from which the expession gets constructed
		 * eg. db::expression('my_table','(',array('name'=>"test'ed",'lang'=>'en'),') OR ',array('owner'=>array('',4,10)))
		 * gives "(name='test\'ed' AND lang='en') OR 'owner' IN (0,4,5,6,10)" if name,lang are strings and owner is an integer
		 * @param $table string name of the table
		 * @param $args mixed variable number of arguments of the following types:
		 *	string: get's as is into the result
		 *	array:	column-name / value pairs: the value gets quoted according to the type of the column and prefixed
		 *		with column-name=, multiple pairs are AND'ed together, see db::column_data_implode
		 *	bool: If False or is_null($arg): the next 2 (!) arguments gets ignored
		 * @return string the expression generated from the arguments
		 */
		function expression($table,$args)
		{
			$table_def = $this->get_table_definitions($app,$table);
			$sql = '';
			$ignore_next = 0;
			foreach(func_get_args() as $n => $arg)
			{
				if ($n < 1) continue;	// table-name

				if ($ignore_next)
				{
					--$ignore_next;
					continue;
				}
				if (is_null($arg)) $arg = False;

				switch(gettype($arg))
				{
					case 'string':
						$sql .= $arg;
						break;
					case 'boolean':
						$ignore_next += !$arg ? 2 : 0;
						break;
					case 'array':
						$sql .= $this->column_data_implode(' AND ',$arg,True,False,$table_def['fd']);
						break;
				}
			}
			if ($this->Debug) echo "<p>db::expression($table,<pre>".print_r(func_get_args(),True)."</pre>) ='$sql'</p>\n";
			return $sql;
		}

		/**
		* Selects one or more rows in table depending on where, all data is quoted according to it's type
		*
		* @author RalfBecker<at>outdoor-training.de
		*
		* @param string $table name of the table
		* @param array/string $cols string or array of column-names / select-expressions
		* @param array/string $where string or array with column-name / values pairs AND'ed together for the where clause
		* @param int $line line-number to pass to query
		* @param string $file file-name to pass to query
		* @param int/bool $offset offset for a limited query or False (default)
		* @param string $append string to append to the end of the query, eg. ORDER BY ...
		* @param string/boolean $app string with name of app or False to use the current-app
		* @param int $num_rows number of rows to return if offset set, default 0 = use default in user prefs
		* @return ADORecordSet or false, if the query fails
		*/
		function select($table,$cols,$where,$line,$file,$offset=False,$append='',$app=False,$num_rows=0)
		{
			if ($this->Debug) echo "<p>db::select('$table',".print_r($cols,True).",".print_r($where,True).",$line,$file,$offset,'$app')</p>\n";

			$table_def = $this->get_table_definitions($app,$table);
			if (is_array($cols))
			{
				$cols = implode(',',$cols);
			}
			if (is_array($where))
			{
				$where = $this->column_data_implode(' AND ',$where,True,False,$table_def['fd']);
			}
			$sql = "SELECT $cols FROM $table WHERE ".($where ? $where : '1=1').
				($append ? ' '.$append : '');

			if ($this->Debug) echo "<p>sql='$sql'</p>";

			return $this->query($sql,$line,$file,$offset,$offset===False ? -1 : (int)$num_rows);
		}
	}
