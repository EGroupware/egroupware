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

			// Not connected? Then connect?
			if (! $this->Link_ID)
			{
				// Check for local connect
				$this->Link_ID = empty($Host)?
					$this->Link_ID=msql_pconnect():
					$this->Link_ID=msql_pconnect($Host);
			}

			// Still not connected? Raise error.
			if (! $this->Link_ID )
			{
				$this->halt('Link-ID == false, pconnect failed');
			}

			// Select current database
			if (!msql_select_db($Database, $this->Link_ID))
			{
				$this->halt('cannot use database '.$Database);
			}
		}

		function query($Query_String)
		{
			$this->connect();

			#   printf("Debug: query = %s<br>\n", $Query_String);

			$this->Query_ID = msql_query($Query_String,$this->Link_ID);
			$this->Row   = 0;
			$this->Error = msql_error();
			if (!$this->Query_ID)
			{
				$this->halt('Invalid SQL: '.$Query_String);
			}
			return $this->Query_ID;
		}

		function next_record()
		{
			$this->Record = msql_fetch_array($this->Query_ID);
			$this->Row   += 1;
			$this->Error = msql_error();

			$stat = is_array($this->Record);
			if (!$stat && $this->Auto_Free)
			{
				msql_free_result($this->Query_ID);
				$this->Query_ID = 0;
			}
			return $stat;
		}

		function seek($pos)
		{
			$status = msql_data_seek($this->Query_ID, $pos);
			if ($status)
			{
				$this->Row = $pos;
			}
			return;
		}

		function metadata($table)
		{
			$count = 0;
			$id    = 0;
			$res   = array();

			$this->connect();
			$id = @msql_list_fields($this->Database, $table);
			if ($id < 0)
			{
				$this->Error = msql_error();
				$this->halt('Metadata query failed.');
			}
			$count = msql_num_fields($id);

			for ($i=0; $i<$count; $i++)
			{
				$res[$i]['table'] = msql_fieldtable ($id, $i);
				$res[$i]['name']  = msql_fieldname  ($id, $i);
				$res[$i]['type']  = msql_fieldtype  ($id, $i);
				$res[$i]['len']   = msql_fieldlen   ($id, $i);
				$res[$i]['flags'] = msql_fieldflags ($id, $i);
				$res['meta'][$res[$i]['name']] = $i;
				$res['num_fields']= $count;
			}

			msql_free_result($id);
			return $res;
		}

		function affected_rows()
		{
			return msql_affected_rows($this->Query_ID);
		}

		function num_rows()
		{
			return msql_num_rows($this->Query_ID);
		}

		function num_fields()
		{
			return msql_num_fields($this->Query_ID);
		}

		function halt($msg)
		{
			printf("<b>Database error:</b> %s<br>\n", $msg);
			printf("<b>MSQL Error</b>: %s<br>\n", $this->Error);
			die('Session halted.');
		}
	}
?>
