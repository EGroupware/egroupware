<?php
  /**************************************************************************\
  * phpGroupWare API - MS SQL Server support                                 *
  * (C) Copyright 1998 Cameron Taggart (cameront@wolfenet.com)               *
  *  Modified by Guarneri carmelo (carmelo@melting-soft.com)                 *
  *	 Modified by Cameron Just     (C.Just@its.uq.edu.au)                     *
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

	/* echo "<BR>This is using the MSSQL class<BR>"; */
	// ^^ really ?! :)

	class db
	{
		var $VEOF         = -1;
		var $Transaction  = false;

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

			if (! $this->Link_ID )
			{
				if ($GLOBALS['phpgw_info']['server']['db_persistent'])
				{
					$this->Link_ID=mssql_pconnect($Host, $User, $Password);
				}
				else
				{
					$this->Link_ID=mssql_connect($Host, $User, $Password);
				}
				if (!$this->Link_ID)
				{
					$this->halt('Link-ID == false, mssql_'.($GLOBALS['phpgw_info']['server']['db_persistent']?'p':'').'connect failed');
				}
				else
				{
					mssql_select_db($Database, $this->Link_ID);
				}
			}
		}

		function db_addslashes($str)
		{
			if (!IsSet($str) || $str == '')
			{
				return '';
			}
			return str_replace("'", "''", $str);
		}

		function free_result()
		{
			if ($this->Query_ID)
			{
				mssql_free_result($this->Query_ID);
			}
			$this->Query_ID = 0;
			$this->VEOF = -1;
		}

		function query($Query_String, $line = '', $file = '')
		{
			$this->VEOF = -1;

			if (!$this->Link_ID)
			{
				$this->connect();
			}

			$this->Query_ID = mssql_query($Query_String, $this->Link_ID);
			$this->Row = 0;
			if (!$this->Query_ID)
			{
				$this->halt("Invalid SQL: " . $Query_String, $line, $file);
			}
			return $this->Query_ID;
		}

		// I don't have access to M$-SQL, can someone finish these 2 functions ?  (jengo)
		function to_timestamp($epoch)
		{
			return date('Y-m-d H:i:s', $epoch);
		}

		function from_timestamp($timestamp)
		{
			return strtotime($timestamp);
		}

		// public: perform a query with limited result set
		function limit_query($Query_String, $offset, $line = '', $file = '', $num_rows = '')
		{
			if (! $num_rows)
			{
				$num_rows = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}

			if ($this->Debug)
			{
				printf("Debug: limit_query = %s<br>offset=%d, num_rows=%d<br>\n", $Query_String, $offset, $num_rows);
			}

			$this->query($Query_String, $line, $file);
			if ($this->Query_ID)
			{
				$this->Row = $offset;
				// Push cursor to appropriate row in case next_record() is used
				if ($offset > 0)
				{
					@mssql_data_seek($this->Query_ID, $offset);
				}
				$this->VEOF = $offset + $num_rows - 1;
			}

			return $this->Query_ID;
		}

		function next_record()
		{
			if (!$this->Query_ID)
			{
				$this->halt("next_record called with no query pending.");
				return 0;
			}

			if ($this->VEOF == -1 || ($this->Row++ <= $this->VEOF))
			{
				// Work around for buggy mssql_fetch_array
				$rec = @mssql_fetch_row($this->Query_ID);
				if ($rec)
				{
					$this->Record = array();
					for ($i = 0; $i < count($rec); $i++)
					{
						$this->Record[$i] = $rec[$i];
						$o = mssql_fetch_field($this->Query_ID, $i);
						$this->Record[$o->name] = $rec[$i];
					}
				}
				else
				{
					$this->Record = NULL;
				}
			}
			else
			{
				$this->Record = NULL;
			}

			$stat = is_array($this->Record);
			if (!$stat && $this->Auto_Free)
			{
				$this->free();
			}

			return $stat;
		}

		function transaction_begin()
		{
			$this->Transaction = !!mssql_query('BEGIN TRAN', $this->Link_ID);
			return $this->Transaction;
		}

		function transaction_commit()
		{
			if (!$this->Errno && $this->Transaction)
			{
				$this->Transaction = false;
				return !!mssql_query('COMMIT TRAN', $this->Link_ID);
			}

			return False;
		}

		function transaction_abort()
		{
			if ($this->Transaction)
			{
				$this->Transaction = false;
				return !!mssql_query('ROLLBACK TRAN', $this->Link_ID);
			}

			return false;
		}

		function seek($pos)
		{
			mssql_data_seek($this->Query_ID,$pos);
			$this->Row = $pos;
		}

		function metadata($table)
		{
			$count = 0;
			$id    = 0;
			$res   = array();

			$this->connect();
			$id = mssql_query("select * from $table", $this->Link_ID);
			if (!$id)
			{
				$this->halt('Metadata query failed.');
			}

			$count = mssql_num_fields($id);

			for ($i=0; $i<$count; $i++)
			{
				$info = mssql_fetch_field($id, $i);
				$res[$i]['table'] = $table;
				$res[$i]['name']  = $info['name'];
				$res[$i]['len']   = $info['max_length'];
				$res[$i]['flags'] = $info['numeric'];
			}
			$this->free_result();
			return $res;
		}

		function affected_rows()
		{
			return mssql_affected_rows($this->Query_ID);
		}

		function num_rows()
		{
			if($this->Query_ID)
			{
				return mssql_num_rows($this->Query_ID);
			}
			else
			{
				return 0;
			}
		}

		function num_fields()
		{
			return mssql_num_fields($this->Query_ID);
		}

		function f($Field_Name)
		{
        	if ($strip_slashes || ($this->auto_stripslashes && ! $strip_slashes))
			{
				return str_replace("''", "'", $this->Record[$Name]);
			}
			else
			{
				return $this->Record[$Name];
			}
		}

		function get_last_insert_id($table, $field)
		{
			if (!isset($table) || $table == '' || !isset($field) || $field == '')
			{
			return -1;
			}

			$result = @mssql_query("select @@identity", $this->Link_ID);
			if (!$result)
			{
				return -1;
			}
			return mssql_result($result, 0, 0);
		}

		function lock($table, $mode="write")
		{
			// /me really, really, really hates locks - transactions serve just fine
			return $this->transaction_begin();
		}

		function unlock()
		{
			return $this->transaction_commit();
		}

		function halt($msg, $line = '', $file = '')
		{
			$this->unlock();

			$this->Errno = 1;
			$this->Error = mssql_get_last_message();
			if ($this->Error == '')
			{
				$this->Error = "General Error (The MS-SQL interface did not return a detailed error message).";
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

			if ($this->Halt_On_Error != "report")
			{
				echo "<p><b>Session halted.</b>";
				$GLOBALS['phpgw']->common->phpgw_exit(True);
			}
		}

		function haltmsg($msg)
		{
			printf("<b>Database error:</b> %s<br>\n", $msg);
			if ($this->Errno != "0" && $this->Error != "()")
			{
				printf("<b>MS-SQL Error</b>: %s (%s)<br>\n", $this->Errno, $this->Error);
			}
		}

		function table_names()
		{
			$this->query("select name from sysobjects where type='u' and name != 'dtproperties'");
			$i = 0;
			while ($info = @mssql_fetch_row($this->Query_ID))
			{
				$return[$i]['table_name'] = $info[0];
				$return[$i]['tablespace_name'] = $this->Database;
				$return[$i]['database'] = $this->Database;
				$i++;
			}
			return $return;
		}
	}
?>
