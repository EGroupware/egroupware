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

	class db
	{
		/* public: connection parameters */
		var $Host     = '';
		var $Database = '';
		var $User     = '';
		var $Password = '';

		/* public: configuration parameters */
		var $auto_stripslashes = False;
		var $Auto_Free     = 0;     ## Set to 1 for automatic mysql_free_result()
		var $Debug         = 0;     ## Set to 1 for debugging messages.
		var $Halt_On_Error = 'yes'; ## "yes" (halt with message), "no" (ignore errors quietly), "report" (ignore errror, but spit a warning)
		var $Seq_Table     = 'db_sequence';

		/* public: result array and current row number */
		var $Record   = array();
		var $Row;

		/* public: current error number and error text */
		var $Errno    = 0;
		var $Error    = '';

		/* public: this is an api revision, not a CVS revision. */
		var $type     = 'mysql';
		var $revision = '1.2';

		/* private: link and query handles */
		var $Link_ID  = 0;
		var $Query_ID = 0;

		/* public: constructor */
		function db($query = '')
		{
			$this->query($query);
		}

		/* public: some trivial reporting */
		function link_id()
		{
			return $this->Link_ID;
		}

		function query_id()
		{
			return $this->Query_ID;
		}

		/* public: connection management */
		function connect($Database = '', $Host = '', $User = '', $Password = '')
		{
			/* Handle defaults */
			if ('' == $Database)
			{
				$Database = $this->Database;
			}
			if ('' == $Host)
			{
				$Host     = $this->Host;
			}
			if ('' == $User)
			{
				$User     = $this->User;
			}
			if ('' == $Password)
			{
				$Password = $this->Password;
			}
			/* establish connection, select database */
			if ( 0 == $this->Link_ID )
			{
				if ($GLOBALS['phpgw_info']['server']['db_persistent'])
				{
					$this->Link_ID=mysql_pconnect($Host, $User, $Password);
				}
				else
				{
					$this->Link_ID=mysql_connect($Host, $User, $Password);
				}

				if (!$this->Link_ID)
				{
					$this->halt(($GLOBALS['phpgw_info']['server']['db_persistent']?'p':'')."connect($Host, $User, \$Password) failed.");
					return 0;
				}

				if (!@mysql_select_db($Database,$this->Link_ID))
				{
					$this->halt("cannot use database ".$this->Database);
					return 0;
				}
			}
			return $this->Link_ID;
		}

		/* This only affects systems not using persistant connections */
		function disconnect()
		{
			if($this->Link_ID <> 0)
			{
				@mysql_close($this->Link_ID);
				$this->Link_ID = 0;
				return 1;
			}
			else
			{
				return 0;
			}
		}

		function db_addslashes($str)
		{
			if (!IsSet($str) || $str == '')
				return '';

			return addslashes($str);
		}

		function to_timestamp($epoch)
		{
			return date('Y-m-d H:i:s',$epoch);
		}

		function from_timestamp($timestamp)
		{
			ereg('([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})',$timestamp,$parts);

			return mktime($parts[4],$parts[5],$parts[6],$parts[2],$parts[3],$parts[1]);
		}

		function limit($start)
		{
			global $phpgw_info;

			echo '<b>Warning: limit() is no longer used, use limit_query()</b>';

			if ($start == 0)
			{
				$s = 'limit ' . $phpgw_info['user']['preferences']['common']['maxmatchs'];
			}
			else
			{
				$s = "limit $start," . $phpgw_info['user']['preferences']['common']['maxmatchs'];
			}
			return $s;
		}

		/* public: discard the query result */
		function free()
		{
			@mysql_free_result($this->Query_ID);
			$this->Query_ID = 0;
		}

		/* public: perform a query */
		/* I added the line and file section so we can have better error reporting. (jengo) */
		function query($Query_String, $line = '', $file = '')
		{
			/* No empty queries, please, since PHP4 chokes on them. */
			/* The empty query string is passed on from the constructor,
			* when calling the class without a query, e.g. in situations
			* like these: '$db = new db_Subclass;'
			*/
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

			if ($this->Debug)
			{
				printf("Debug: query = %s<br>\n", $Query_String);
			}

			$this->Query_ID = @mysql_query($Query_String,$this->Link_ID);
			$this->Row   = 0;
			$this->Errno = mysql_errno();
			$this->Error = mysql_error();
			if (! $this->Query_ID)
			{
				$this->halt("Invalid SQL: ".$Query_String, $line, $file);
			}

			# Will return nada if it fails. That's fine.
			return $this->Query_ID;
		}

		// public: perform a query with limited result set
		function limit_query($Query_String, $offset, $line = '', $file = '', $num_rows = '')
		{
			global $phpgw_info;

			if (! $num_rows)
			{
				$num_rows = $phpgw_info['user']['preferences']['common']['maxmatchs'];
			}

			if ($offset == 0)
			{
				$Query_String .= ' LIMIT ' . $num_rows;
			}
			else
			{
				$Query_String .= ' LIMIT ' . $offset . ',' . $num_rows;
			}

			if ($this->Debug)
			{
				printf("Debug: limit_query = %s<br>offset=%d, num_rows=%d<br>\n", $Query_String, $offset, $num_rows);
			}

			return $this->query($Query_String, $line, $file);
		}

		/* public: walk result set */
		function next_record()
		{
			if (!$this->Query_ID)
			{
				$this->halt('next_record called with no query pending.');
				return 0;
			}

			$this->Record = @mysql_fetch_array($this->Query_ID);
			$this->Row   += 1;
			$this->Errno  = mysql_errno();
			$this->Error  = mysql_error();

			$stat = is_array($this->Record);
			if (!$stat && $this->Auto_Free)
			{
				$this->free();
			}
			return $stat;
		}

		/* public: position in result set */
		function seek($pos = 0)
		{
			$status = @mysql_data_seek($this->Query_ID, $pos);
			if ($status)
			{
				$this->Row = $pos;
			}
			else
			{
				$this->halt("seek($pos) failed: result has ".$this->num_rows()." rows");
				/* half assed attempt to save the day, 
				* but do not consider this documented or even
				* desireable behaviour.
				*/
				@mysql_data_seek($this->Query_ID, $this->num_rows());
				$this->Row = $this->num_rows;
				return 0;
			}
			return 1;
		}

		function transaction_begin()
		{
			return True;
		}

		function transaction_commit()
		{
			return True;
		}

		function transaction_abort()
		{
			return True;
		}

		function get_last_insert_id($table, $field)
		{
			/* This will get the last insert ID created on the current connection.  Should only be called
			 * after an insert query is run on a table that has an auto incrementing field.  $table and
			 * $field are required, but unused here since it's unnecessary for mysql.  For compatibility
			 * with pgsql, the params must be supplied.
			 */

			if (!isset($table) || $table == '' || !isset($field) || $field == '')
				return -1;

			return @mysql_insert_id($this->Link_ID);
		}

		/* public: table locking */
		function lock($table, $mode='write')
		{
			$this->connect();

			$query = "lock tables ";
			if (is_array($table))
			{
				while (list($key,$value)=each($table))
				{
					if ($key == "read" && $key!=0)
					{
						$query .= "$value read, ";
					}
					else
					{
						$query .= "$value $mode, ";
					}
				}
				$query = substr($query,0,-2);
			}
			else
			{
				$query .= "$table $mode";
			}
			$res = @mysql_query($query, $this->Link_ID);
			if (!$res)
			{
				$this->halt("lock($table, $mode) failed.");
				return 0;
			}
			return $res;
		}

		function unlock()
		{
			$this->connect();

			$res = @mysql_query("unlock tables");
			if (!$res)
			{
				$this->halt("unlock() failed.");
				return 0;
			}
			return $res;
		}


		/* public: evaluate the result (size, width) */
		function affected_rows()
		{
			return @mysql_affected_rows($this->Link_ID);
		}

		function num_rows()
		{
			return @mysql_num_rows($this->Query_ID);
		}

		function num_fields()
		{
			return @mysql_num_fields($this->Query_ID);
		}

		/* public: shorthand notation */
		function nf()
		{
			return $this->num_rows();
		}

		function np()
		{
			print $this->num_rows();
		}

		function f($Name, $strip_slashes = "")
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

		function p($Name)
		{
			print $this->Record[$Name];
		}

		/* public: sequence numbers */
		function nextid($seq_name)
		{
			$this->connect();

			if ($this->lock($this->Seq_Table))
			{
				/* get sequence number (locked) and increment */
				$q  = sprintf("select nextid from %s where seq_name = '%s'",
					$this->Seq_Table,
					$seq_name);
				$id  = @mysql_query($q, $this->Link_ID);
				$res = @mysql_fetch_array($id);

				/* No current value, make one */
				if (!is_array($res))
				{
					$currentid = 0;
					$q = sprintf("insert into %s values('%s', %s)",
						$this->Seq_Table,
						$seq_name,
						$currentid);
					$id = @mysql_query($q, $this->Link_ID);
				}
				else
				{
					$currentid = $res["nextid"];
				}
				$nextid = $currentid + 1;
				$q = sprintf("update %s set nextid = '%s' where seq_name = '%s'",
					$this->Seq_Table,
					$nextid,
					$seq_name);
				$id = @mysql_query($q, $this->Link_ID);
				$this->unlock();
			}
			else
			{
				$this->halt("cannot lock ".$this->Seq_Table." - has it been created?");
				return 0;
			}
			return $nextid;
		}

		/* public: return table metadata */
		function metadata($table='',$full=false)
		{
			$count = 0;
			$id    = 0;
			$res   = array();

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

			/* if no $table specified, assume that we are working with a query */
			/* result */
			if ($table)
			{
				$this->connect();
				$id = @mysql_list_fields($this->Database, $table);
				if (!$id)
				{
					$this->halt("Metadata query failed.");
				}
			}
			else
			{
				$id = $this->Query_ID; 
				if (!$id)
				{
					$this->halt("No query specified.");
				}
			}
 
			$count = @mysql_num_fields($id);

			/* made this IF due to performance (one if is faster than $count if's) */
			if (!$full)
			{
				for ($i=0; $i<$count; $i++)
				{
					$res[$i]['table'] = @mysql_field_table ($id, $i);
					$res[$i]['name']  = @mysql_field_name  ($id, $i);
					$res[$i]['type']  = @mysql_field_type  ($id, $i);
					$res[$i]['len']   = @mysql_field_len   ($id, $i);
					$res[$i]['flags'] = @mysql_field_flags ($id, $i);
				}
			}
			else
			{
				/* full */
				$res["num_fields"]= $count;

				for ($i=0; $i<$count; $i++)
				{
					$res[$i]['table'] = @mysql_field_table ($id, $i);
					$res[$i]['name']  = @mysql_field_name  ($id, $i);
					$res[$i]['type']  = @mysql_field_type  ($id, $i);
					$res[$i]['len']   = @mysql_field_len   ($id, $i);
					$res[$i]['flags'] = @mysql_field_flags ($id, $i);
					$res['meta'][$res[$i]['name']] = $i;
				}
			}

			/* free the result only if we were called on a table */
			if ($table)
			{
				@mysql_free_result($id);
			}
			return $res;
		}

		/* private: error handling */
		function halt($msg, $line = '', $file = '')
		{
			global $phpgw;
			$this->unlock();	/* Just in case there is a table currently locked */

			$this->Error = @mysql_error($this->Link_ID);
			$this->Errno = @mysql_errno($this->Link_ID);
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

			if ($this->Halt_On_Error != "report")
			{
				echo "<p><b>Session halted.</b>";
				$phpgw->common->phpgw_exit(True);
			}
		}

		function haltmsg($msg)
		{
			printf("<b>Database error:</b> %s<br>\n", $msg);
			if ($this->Errno != "0" && $this->Error != "()")
			{
				printf("<b>MySQL Error</b>: %s (%s)<br>\n",$this->Errno,$this->Error);
			}
		}

		function table_names()
		{
			$this->query("SHOW TABLES");
			$i=0;
			while ($info=mysql_fetch_row($this->Query_ID))
			{
				$return[$i]['table_name'] = $info[0];
				$return[$i]['tablespace_name'] = $this->Database;
				$return[$i]['database'] = $this->Database;
				$i++;
			}
			return $return;
		}

		function create_database($adminname = '', $adminpasswd = '')
		{
			$currentUser = $this->User;
			$currentPassword = $this->Password;
			$currentDatabase = $this->Database;

			if ($adminname != '')
			{
				$this->User = $adminname;
				$this->Password = $adminpasswd;
				$this->Database = "mysql";
			}
			$this->disconnect();
			$this->query("CREATE DATABASE $currentDatabase");
			$this->query("grant all on $currentDatabase.* to $currentUser@localhost identified by '$currentPassword'");
			$this->disconnect();

			$this->User = $currentUser;
			$this->Password = $currentPassword;
			$this->Database = $currentDatabase;
			$this->connect();
			/*return $return; */
		}
	}
?>
