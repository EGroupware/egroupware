<?php
  /**************************************************************************\
  * phpGroupWare API -                                                       *
  * Copyright (c) 1998,1999 SH Online Dienst GmbH Boris Erdmann,             *
  * Kristian Koehntopp                                                       *
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
		var $Host     = '';
		var $Database = '';
		var $User     = '';
		var $Password = '';

		var $auto_stripslashes = False;

		/* "yes" (halt with message), "no" (ignore errors quietly), "report" (ignore errror, but spit a warning) */
		var $Halt_On_Error = 'yes';

		var $Link_ID  = 0;
		var $Query_ID = 0;
		var $Record   = array();
		var $Row      = 0;

		var $Seq_Table = 'db_sequence';

		var $Errno    = 0;
		var $Error    = '';

		/* Set this to 1 for automatic pg_freeresult on last record. */
		var $Auto_Free = 0;

		// PostgreSQL changed somethings from 6.x -> 7.x
		var $db_version;

		// For our error handling
		var $xmlrpc = False;
		var $soap   = False;

		function ifadd($add, $me)
		{
			if('' != $add)
			{
				return ' ' . $me . $add;
			}
		}

		/* public: constructor */
		function db($query = '')
		{
			$this->query($query);

			if (ereg('xmlrpc.php',$GLOBALS['PHP_SELF']))
			{
				$this->xmlrpc = True;
			}

			if (ereg('soap.php',$GLOBALS['PHP_SELF']))
			{
				$this->soap = True;
			}
		}

		function connect()
		{
			if (0 == $this->Link_ID)
			{
				$cstr = 'dbname=' . $this->Database
					. $this->ifadd($this->Host, 'host=')
					. $this->ifadd($this->Port, 'port=')
					. $this->ifadd($this->User, 'user=')
					. $this->ifadd("'".$this->Password."'", 'password=');
				if ($GLOBALS['phpgw_info']['server']['db_persistent'])
				{
					$this->Link_ID=pg_pconnect($cstr);
				}
				else
				{
					$this->Link_ID=pg_connect($cstr);
				}

				if (! $this->Link_ID)
				{
					$this->halt('Link-ID == false, '.($GLOBALS['phpgw_info']['server']['db_persistent']?'p':'').'connect failed');
				}
				else
				{
					$this->query("select version()",__LINE__,__FILE__);
					$this->next_record();

					$version          = $this->f('version');
					$parts            = explode(' ',$version);
					$this->db_version = $parts[1];
				}
			}
		}

		function to_timestamp($epoch)
		{
			$db_version = $this->db_version;
			if (floor($db_version) == 6)
			{
				return $this->to_timestamp_6($epoch);
			}
			else
			{
				return $this->to_timestamp_7($epoch);
			}
		}

		function from_timestamp($timestamp)
		{
			if (floor($this->db_version) == 6)
			{
				return $this->from_timestamp_6($timestamp);
			}
			else
			{
				return $this->from_timestamp_7($timestamp);
			}
		}

		// For PostgreSQL 6.x
		function to_timestamp_6($epoch)
		{

		}

		// For PostgreSQL 6.x
		function from_timestamp_6($timestamp)
		{

		}

		// For PostgreSQL 7.x
		function to_timestamp_7($epoch)
		{
			// This needs the GMT offset!
			return date('Y-m-d H:i:s-00',$epoch);
		}

		// For PostgreSQL 7.x
		function from_timestamp_7($timestamp)
		{
			ereg('([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})',$timestamp,$parts);
	
			return mktime($parts[4],$parts[5],$parts[6],$parts[2],$parts[3],$parts[1]);
		}



		function limit($start)
		{
			echo '<b>Warning: limit() is no longer used, use limit_query()</b>';

			if ($start == 0)
			{
				$s = 'limit ' . $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}
			else
			{
				$s = 'limit ' . $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'] . ',' . $start;
			}
			return $s;
		}

		/* This only affects systems not using persistant connections */
		function disconnect()
		{
			return @pg_close($this->Link_ID);
		}

		function db_addslashes($str)
		{
			if (!IsSet($str) || $str == '')
			{
				return '';
			}

			return addslashes($str);
		}

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

			$this->connect();

			/* printf("<br>Debug: query = %s<br>\n", $Query_String); */

			$this->Query_ID = @pg_Exec($this->Link_ID, $Query_String);
			$this->Row   = 0;

			$this->Error = pg_ErrorMessage($this->Link_ID);
			$this->Errno = ($this->Error == '') ? 0 : 1;
			if (! $this->Query_ID)
			{
				$this->halt('Invalid SQL: ' . $Query_String, $line, $file);
			}

			return $this->Query_ID;
		}

		/* public: perform a query with limited result set */
		function limit_query($Query_String, $offset, $line = '', $file = '', $num_rows = '')
		{
			if (! $num_rows)
			{
				$num_rows = $GLOBALS['phpgw_info']['user']['preferences']['common']['maxmatchs'];
			}

			if ($offset == 0)
			{
				$Query_String .= ' LIMIT ' . $num_rows;
			}
			else
			{
				$Query_String .= ' LIMIT ' . $num_rows . ',' . $offset;
			}

			if ($this->Debug)
			{
				printf("Debug: limit_query = %s<br>offset=%d, num_rows=%d<br>\n", $Query_String, $offset, $num_rows);
			}

			return $this->query($Query_String, $line, $file);
		}

		// public: discard the query result
		function free()
		{
			@pg_freeresult($this->Query_ID);
			$this->Query_ID = 0;
		}

		function next_record()
		{
			$this->Record = @pg_fetch_array($this->Query_ID, $this->Row++);

			$this->Error = pg_ErrorMessage($this->Link_ID);
			$this->Errno = ($this->Error == '') ? 0 : 1;

			$stat = is_array($this->Record);
			if (!$stat && $this->Auto_Free)
			{
				pg_freeresult($this->Query_ID);
				$this->Query_ID = 0;
			}
			return $stat;
		}

		function seek($pos)
		{
			$this->Row = $pos;
		}

		function transaction_begin()
		{
			return $this->query('begin');
		}

		function transaction_commit()
		{
			if (! $this->Errno)
			{
				return pg_Exec($this->Link_ID,'commit');
			}
			else
			{
				return False;
			}
		}

		function transaction_abort()
		{
			return pg_Exec($this->Link_ID,'rollback');
		}

		function get_last_insert_id($table, $field)
		{
			/* This will get the last insert ID created on the current connection.  Should only be called
			 * after an insert query is run on a table that has an auto incrementing field.  Of note, table
			 * and field are required because pgsql returns the last inserted OID, which is unique across
			 * an entire installation.  These params allow us to retrieve the sequenced field without adding
			 * conditional code to the apps.
			 */
			if (!isset($table) || $table == '' || !isset($field) || $field == '')
			{
				return -1;
			}

			$oid = pg_getlastoid($this->Query_ID);
			if ($oid == -1)
			{
				return -1;
			}

			$result = @pg_Exec($this->Link_ID, "select $field from $table where oid=$oid");
			if (!$result)
			{
				return -1;
			}

			$Record = @pg_fetch_array($result, 0);
			@pg_freeresult($result);
			if (!is_array($Record)) /* OID not found? */
			{
				return -1;
			}

			return $Record[0];
		}

		function lock($table, $mode = 'write')
		{
			$result = $this->transaction_begin();

			if ($mode == 'write')
			{
				if (is_array($table))
				{
					while ($t = each($table))
					{
						$result = pg_Exec($this->Link_ID,'lock table ' . $t[1] . ' in share mode');
					}
				}
				else
				{
					$result = pg_Exec($this->Link_ID, 'lock table ' . $table . ' in share mode');
				}
			}
			else
			{
				$result = 1;
			}

			return $result;
		}

		function unlock()
		{
			return $this->transaction_commit();
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
				$id  = @pg_Exec($this->Link_ID, $q);
				$res = @pg_Fetch_Array($id, 0);

				/* No current value, make one */
				if (!is_array($res))
				{
					$currentid = 0;
					$q = sprintf("insert into %s values('%s', %s)",
						$this->Seq_Table,
						$seq_name,
						$currentid);
					$id = @pg_Exec($this->Link_ID, $q);
				}
				else
				{
					$currentid = $res['nextid'];
				}
				$nextid = $currentid + 1;
				$q = sprintf("update %s set nextid = '%s' where seq_name = '%s'",
					$this->Seq_Table,
					$nextid,
					$seq_name);
				$id = @pg_Exec($this->Link_ID, $q);
				$this->unlock();
			}
			else
			{
				$this->halt('cannot lock ' . $this->Seq_Table . ' - has it been created?');
				return 0;
			}
			return $nextid;
		}

		function metadata($table)
		{
			$count = 0;
			$id    = 0;
			$res   = array();

			$this->connect();
			$id = pg_exec($this->Link_ID, "select * from $table");
			if ($id < 0)
			{
				$this->Error = pg_ErrorMessage($id);
				$this->Errno = 1;
				$this->halt('Metadata query failed.');
			}
			$count = pg_NumFields($id);

			for ($i=0; $i<$count; $i++)
			{
				$res[$i]['table'] = $table;
				$res[$i]['name']  = pg_FieldName  ($id, $i); 
				$res[$i]['type']  = pg_FieldType  ($id, $i);
				$res[$i]['len']   = pg_FieldSize  ($id, $i);
				$res[$i]['flags'] = '';
			}

			pg_FreeResult($id);
			return $res;
		}

		function affected_rows()
		{
			return pg_cmdtuples($this->Query_ID);
		}

		function num_rows()
		{
			return pg_numrows($this->Query_ID);
		}

		function num_fields()
		{
			return pg_numfields($this->Query_ID);
		}

		function nf()
		{
			return $this->num_rows();
		}

		function np()
		{
			print $this->num_rows();
		}

		function f($Name,$strip_slashes = '')
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

		function halt($msg, $line = '', $file = '')
		{
			if ($this->Halt_On_Error == 'no')
			{
				return;
			}

			/* Just in case there is a table currently locked */
			$this->transaction_abort();


			if ($this->xmlrpc || $this->soap)
			{
				$s = sprintf("Database error: %s\n", $msg);
				$s .= sprintf("PostgreSQL Error: %s\n\n (%s)\n\n",$this->Errno,$this->Error);
			}
			else
			{
				$s = sprintf("<b>Database error:</b> %s<br>\n", $msg);
				$s .= sprintf("<b>PostgreSQL Error</b>: %s (%s)<br>\n",$this->Errno,$this->Error);
			}

			if ($file)
			{
				if ($this->xmlrpc || $this->soap)
				{
					$s .=	sprintf("File: %s\n",$file);
				}
				else
				{
					$s .=	sprintf("<br><b>File:</b> %s",$file);
				}
			}

			if ($line)
			{
				if ($this->xmlrpc || $this->soap)
				{
					$s .=	sprintf("Line: %s\n",$line);
				}
				else
				{
					$s .=	sprintf("<br><b>Line:</b> %s",$line);
				}
			}

			if ($this->Halt_On_Error == 'yes')
			{
				if (! $this->xmlrpc && ! $this->soap)
				{
					$s .= '<p><b>Session halted.</b>';
				}
			}

			if ($this->xmlrpc)
			{
				xmlrpcfault($s);
			}
			elseif ($this->soap)
			{

			}
			else
			{
				echo $s;
				$GLOBALS['phpgw']->common->phpgw_exit(True);
			}
		}

		function table_names()
		{
			$this->query("select relname from pg_class where relkind = 'r' and not relname like 'pg_%'");
			$i=0;
			while ($this->next_record())
			{
				$return[$i]['table_name']= $this->f(0);
				$return[$i]['tablespace_name']=$this->Database;
				$return[$i]['database']=$this->Database;
				$i++;
			}
			return $return;
		}

		function index_names()
		{
			$this->query("SELECT relname FROM pg_class WHERE NOT relname ~ 'pg_.*' AND relkind ='i' ORDER BY relname");
			$i=0;
			while ($this->next_record())
			{
				$return[$i]['index_name']= $this->f(0);
				$return[$i]['tablespace_name']=$this->Database;
				$return[$i]['database']=$this->Database;
				$i++;
			}
			return $return;
		}

		function create_database($adminname = '', $adminpasswd = '')
		{
			$currentUser = $this->User;
			$currentPassword = $this->Password;
			$currentDatabase = $this->Database;

			if ($adminname != "")
			{
				$this->User = $adminname;
				$this->Password = $adminpasswd;
			}

			if (! $this->Host)
			{
				system('createdb ' . $currentDatabase, $outval);
			}
			else
			{
				system('createdb -h ' . $this->Host . ' ' . $currentDatabase, $outval);
			}

			if($outval != 0) 
			{
				/* either the rights r not available or the postmaster is not running .... */
				echo 'database creation failure <BR>';
				echo 'please setup the postreSQL database manually<BR>';
			}

			$this->User = $currentUser;
			$this->Password = $currentPassword;
			$this->Database = $currentDatabase;
			$this->connect();
			//return $return;
		}
	}

