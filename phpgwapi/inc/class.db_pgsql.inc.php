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

class db {
  var $Host     = "";
  var $Database = "";
  var $User     = "";
  var $Password = "";

  var $auto_stripslashes = False;
  
  var $Halt_On_Error = "yes"; ## "yes" (halt with message), "no" (ignore errors quietly), "report" (ignore errror, but spit a warning)    

  var $Link_ID  = 0;
  var $Query_ID = 0;
  var $Record   = array();
  var $Row      = 0;

  var $Seq_Table     = "db_sequence";

  var $Errno    = 0;
  var $Error    = "";

  var $Auto_Free = 0; # Set this to 1 for automatic pg_freeresult on 
                      # last record.

  function ifadd($add, $me) {
	  if("" != $add) return " ".$me.$add;
  }
  
  /* public: constructor */
  function db($query = "") {
      $this->query($query);
  }

  function connect() {
	  if ( 0 == $this->Link_ID ) {
		  $cstr = "dbname=".$this->Database.
		  $this->ifadd($this->Host, "host=").
		  $this->ifadd($this->Port, "port=").
		  $this->ifadd($this->User, "user=").
		  $this->ifadd($this->Password, "password=");
		  $this->Link_ID=pg_pconnect($cstr);
		  if (!$this->Link_ID) {
			  $this->halt("Link-ID == false, pconnect failed");
		  }
	  }
  }

  function limit($start)
  {
     global $phpgw_info;

     if ($start == 0) {
        $s = "limit " . $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
     } else {
        $s = "limit " . $phpgw_info["user"]["preferences"]["common"]["maxmatchs"] . ",$start";
     }
     return $s;
  }

  // This only affects systems not using persistant connections
  function disconnect()
  {
    return @pg_close($this->Link_ID);
  }

  // I added the line and file section so we can have better error reporting. (jengo)
  function query($Query_String, $line = "", $file = "") {
    /* No empty queries, please, since PHP4 chokes on them. */
    if ($Query_String == "")
      /* The empty query string is passed on from the constructor,
       * when calling the class without a query, e.g. in situations
       * like these: '$db = new db_Subclass;'
       */
      return 0;

    $this->connect();

#   printf("<br>Debug: query = %s<br>\n", $Query_String);

    $this->Query_ID = pg_Exec($this->Link_ID, $Query_String);
    $this->Row   = 0;

    $this->Error = pg_ErrorMessage($this->Link_ID);
    $this->Errno = ($this->Error == "")?0:1;
    if (! $this->Query_ID) {
       $this->halt("Invalid SQL: ".$Query_String, $line, $file);
    }

    return $this->Query_ID;
  }

  // public: discard the query result
  function free() {
    @pg_freeresult($this->Query_ID);
    $this->Query_ID = 0;
  }
  
  function next_record() {
    $this->Record = @pg_fetch_array($this->Query_ID, $this->Row++);
    
    $this->Error = pg_ErrorMessage($this->Link_ID);
    $this->Errno = ($this->Error == "")?0:1;

    $stat = is_array($this->Record);
    if (!$stat && $this->Auto_Free) {
      pg_freeresult($this->Query_ID);
      $this->Query_ID = 0;
    }
    return $stat;
  }

  function seek($pos) {
    $this->Row = $pos;
  }

  function lock($table, $mode = "write") {
    $result = pg_Exec($this->Link_ID, "begin work");
    if ($mode == "write") {
       if (is_array($table)) {
          while ($t = each($table)) {
	    $result = pg_Exec($this->Link_ID,"lock table $t[1] in share mode");
          }
       } else {
	  $result = pg_Exec($this->Link_ID, "lock table $table in share mode");
       }
    } else {
      $result = 1;
    }
    return $result;
  }
  
  function unlock() {
    return pg_Exec($this->Link_ID, "commit work");
  }


  /* public: sequence numbers */
  function nextid($seq_name) {
    $this->connect();

    if ($this->lock($this->Seq_Table)) {
      /* get sequence number (locked) and increment */
      $q  = sprintf("select nextid from %s where seq_name = '%s'",
                $this->Seq_Table,
                $seq_name);
      $id  = @pg_Exec($this->Link_ID, $q);
      $res = @pg_Fetch_Array($id, 0);
      
      /* No current value, make one */
      if (!is_array($res)) {
        $currentid = 0;
        $q = sprintf("insert into %s values('%s', %s)",
                 $this->Seq_Table,
                 $seq_name,
                 $currentid);
        $id = @pg_Exec($this->Link_ID, $q);
      } else {
        $currentid = $res["nextid"];
      }
      $nextid = $currentid + 1;
      $q = sprintf("update %s set nextid = '%s' where seq_name = '%s'",
               $this->Seq_Table,
               $nextid,
               $seq_name);
      $id = @pg_Exec($this->Link_ID, $q);
      $this->unlock();
    } else {
      $this->halt("cannot lock ".$this->Seq_Table." - has it been created?");
      return 0;
    }
    return $nextid;
  }



  function metadata($table) {
    $count = 0;
    $id    = 0;
    $res   = array();

    $this->connect();
    $id = pg_exec($this->Link_ID, "select * from $table");
    if ($id < 0) {
      $this->Error = pg_ErrorMessage($id);
      $this->Errno = 1;
      $this->halt("Metadata query failed.");
    }
    $count = pg_NumFields($id);
    
    for ($i=0; $i<$count; $i++) {
      $res[$i]["table"] = $table;
      $res[$i]["name"]  = pg_FieldName  ($id, $i); 
      $res[$i]["type"]  = pg_FieldType  ($id, $i);
      $res[$i]["len"]   = pg_FieldSize  ($id, $i);
      $res[$i]["flags"] = "";
    }
    
    pg_FreeResult($id);
    return $res;
  }

  function affected_rows() {
    return pg_cmdtuples($this->Query_ID);
  }

  function num_rows() {
    return pg_numrows($this->Query_ID);
  }

  function num_fields() {
    return pg_numfields($this->Query_ID);
  }

  function nf() {
    return $this->num_rows();
  }

  function np() {
    print $this->num_rows();
  }

  function f($Name,$strip_slashes = "")
  {
     if ($strip_slashes || ($this->auto_stripslashes && ! $strip_slashes)) {
        return stripslashes($this->Record[$Name]);
     } else {
        return $this->Record[$Name];
     }
  }

  function p($Name) {
    print $this->Record[$Name];
  }
  
  function halt($msg, $line = "", $file = "")
  {
     global $phpgw;
 
     if($this->Halt_On_Error == "no") {
        return;
     }
     $this->unlock();				// Just in case there is a table currently locked
 
     printf("<b>Database error:</b> %s<br>\n", $msg);
     printf("<b>PostgreSQL Error</b>: %s (%s)<br>\n",
       $this->Errno,
       $this->Error);
     if ($file) {
        printf("<br><b>File:</b> %s",$file);
     }
     if ($line) {
        printf("<br><b>Line:</b> %s",$line);
     }
 
     if ($this->Halt_On_Error == "yes") {
        echo "<p><b>Session halted.</b>";
        $phpgw->common->phpgw_exit(True);
     }
  }

  function table_names() {
    $this->query("select relname from pg_class where relkind = 'r' and not relname like 'pg_%'");
    $i=0;
    while ($this->next_record())
     {
      $return[$i]["table_name"]= $this->f(0);
      $return[$i]["tablespace_name"]=$this->Database;
      $return[$i]["database"]=$this->Database;
      $i++;
     }
    return $return;
  }
}
