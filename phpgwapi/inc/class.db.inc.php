<?php 
  /**************************************************************************\
  * phpGroupWare API - MySQL database support                                *
  * Copyright (c) 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp      *
  * ------------------------------------------------------------------------ *
  * This is not part of phpGroupWare, but is used by phpGroupWare.           * 
  * http://www.phpgroupware.org/                                             * 
  * ------------------------------------------------------------------------ *
  * This program is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published    *
  * by the Free Software Foundation; either version 2.1 of the License, or   *
  * any later version.                                                       *
  \**************************************************************************/

  /* $Id$ */
  
	if (empty($GLOBALS['phpgw_info']['server']['db_type']))
	{
		$GLOBALS['phpgw_info']['server']['db_type'] = 'mysql';
	}
	include(PHPGW_API_INC.'/class.db_'.$GLOBALS['phpgw_info']['server']['db_type'].'.inc.php');

	/**
       * Database abstraction library
       * 
       * This allows phpGroupWare to use multiple database backends 
       *
       * @package phpgwapi
       * @subpackage db
       * @abstract
       * @author NetUSE AG Boris Erdmann, Kristian Koehntopp <br> hacked on by phpGW
       * @copyright &copy; 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp <br> 2003 FreeSoftware Foundation
       * @license LGPL
       * @link http://www.sanisoft.com/phplib/manual/DB_sql.php
       */

	class db_
	{
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
		var $Halt_On_Error = 'yes';
        
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

		/**
        * @param string $query query to be executed (optional)
        */
 
		function db_($query = '')
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
        * @param string $User name of database user (optional)
        * @var string $Password password for database user (optional)
        */
		function connect($Database = '', $Host = '', $User = '', $Password = '')
		{}

		/**
        * Close a connection to a database - only needed for persistent connections
        */
		function disconnect()
		{}

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

			return addslashes($str);
		}

		/**
        * Convert a unix timestamp to a rdms specific timestamp
        *
        * @param int unix timestamp
        * @return string rdms specific timestamp
        */
        function to_timestamp($epoch)
		{}

		/**
        * Convert a rdms specific timestamp to a unix timestamp 
        *
        * @param string rdms specific timestamp
        * @return int unix timestamp
        */
        function from_timestamp($timestamp)
		{}

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
			@mysql_free_result($this->Query_ID);
			$this->Query_ID = 0;
		}

		/**
        * Execute a query
        *
        * @param string $Query_String the query to be executed
        * @param mixed $line the line method was called from - use __LINE__
        * @param string $file the file method was called from - use __FILE__
        * @return int current query id if sucesful and null if fails
        */
		function query($Query_String, $line = '', $file = '')
		{}

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
		{}

		
        /**
        * Move to the next row in the results set
        *
        * @return bool was another row found?
        */
		function next_record()
		{}

		/**
        * Move to position in result set
        *
        * @param int $pos required row (optional), default first row
        * @return int 1 if sucessful or 0 if not found
        */
		function seek($pos = 0)
		{}

		/**
        * Begin Transaction
        *
        * @return int current transaction id
        */
        function transaction_begin()
		{
        	return True;
		}

        /**
        * Complete the transaction
        *
        * @return bool True if sucessful, False if fails
        */ 
		function transaction_commit()
		{
        	return True;
		}

        /**
        * Rollback the current transaction
        *
        * @return bool True if sucessful, False if fails
        */
		function transaction_abort()
		{
        	return True;
		}

		/**
        * Find the primary key of the last insertion on the current db connection
        *
        * @param string $table name of table the insert was performed on
        * @param string $field the autoincrement primary key of the table
        * @return int the id, -1 if fails
        */
        function get_last_insert_id($table, $field)
		{}

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
		{}

        /**
        * Number of rows in current result set
        *
        * @return int number of rows
        */
		function num_rows()
		{}

		/**
        * Number of fields in current row
        *
        * @return int number of fields
        */
        function num_fields()
		{}

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
        * Return the value of a filed
        * 
        * @param string $String name of field
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
        * Get the id for the next sequence - not implemented!
        *
        * @param string $seq_name name of the sequence
        * @return int sequence id
        */
		function nextid($seq_name)
		{}

		/**
        * Get description of a table
        *
        * @param string $table name of table to describe
        * @param bool $full optional, default False summary information, True full information
        * @return array table meta data
        */  
		function metadata($table='',$full=false)
		{
			/*
			 * Due to compatibility problems with Table we changed the behavior
			 * of metadata();
			 * depending on $full, metadata returns the following values:
			 *
			 * - full is false (default):
			 * $result[]:
			 *   [0]["table"]  table name
			 *   [0]["name"]   field name
			 *   [0]["type"]   field type
			 *   [0]["len"]    field length
			 *   [0]["flags"]  field flags
			 *
			 * - full is true
			 * $result[]:
			 *   ["num_fields"] number of metadata records
			 *   [0]["table"]  table name
			 *   [0]["name"]   field name
			 *   [0]["type"]   field type
			 *   [0]["len"]    field length
			 *   [0]["flags"]  field flags
			 *   ["meta"][field name]  index of field named "field name"
			 *   The last one is used, if you have a field name, but no index.
			 *   Test:  if (isset($result['meta']['myfield'])) { ...
			 */
		}

		/**
        * Error handler
        *
        * @param string $msg error message
        * @param int $line line of calling method/function (optional)
        * @param string $file file of calling method/function (optional)
        */
		function halt($msg, $line = '', $file = '')
		{}

        /**
        * Get a list of table names in the current database
        *
        * @return array list of the tables
        */
		function table_names()
		{
		}

        /**
        * Return a list of indexes in current database
        *
        * @return array list of indexes
        */
		function index_names()
		{
        	return array();
		}

        /**
        * Create a new database
        *
        * @param string $adminname name of database administrator user (optional)
        * @param string $adminpasswd password for the database administrator user (optional)
        */
		function create_database($adminname = '', $adminpasswd = '')
		{}
	}
?>
