<?php
/**************************************************************************\
* phpGroupWare API - Accounts manager for SQL                              *
* This file written by Joseph Engo <jengo@phpgroupware.org>                *
* View and manipulate contact records using SQL                            *
* Copyright (C) 2001 Joseph Engo                                           *
* -------------------------------------------------------------------------*
* This library is part of the phpGroupWare API                             *
* http://www.phpgroupware.org/api                                          * 
* ------------------------------------------------------------------------ *
* This library is free software; you can redistribute it and/or modify it  *
* under the terms of the GNU Lesser General Public License as published by *
* the Free Software Foundation; either version 2.1 of the License,         *
* or any later version.                                                    *
* This library is distributed in the hope that it will be useful, but      *
* WITHOUT ANY WARRANTY; without even the implied warranty of               *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
* See the GNU Lesser General Public License for more details.              *
* You should have received a copy of the GNU Lesser General Public License *
* along with this library; if not, write to the Free Software Foundation,  *
* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
\**************************************************************************/

/* $Id$ */

	/*
	phpgw_addressbook_extra (
		contact_id          int,
		contact_owner       int,
		contact_name        varchar(255),
		contact_value       varchar(255)
	);
	*/
  
	/* ldap is a copy of sql for now */
  
	class contacts_
	{
		var $db;
		var $std_table="phpgw_addressbook";
		var $ext_table="phpgw_addressbook_extra";
		var $account_id;
		var $stock_contact_fields;         // This is an array of almost the fields in the phpgw_addressbook table, except id,owner,lid,tid
		var $email_types;                  // VCard email type array
		var $total_records;                // This will contain numrows for data retrieved

     function contacts_()
     {
        global $phpgw, $phpgw_info;

        $this->db = $phpgw->db;

        $this->account_id = $phpgw_info["user"]["account_id"];
        // rework the following to be a simple sed style creation
        $this->stock_contact_fields = array("fn"              => "fn",        //'firstname lastname'
                                            "sound"           => "sound",
                                            "org_name"        => "org_name",  //company
                                            "org_unit"        => "org_unit",  //division
                                            "title"           => "title",
                                            "n_given"         => "n_given",   //firstname
                                            "n_family"        => "n_family",  //lastname
                                            "n_middle"        => "n_middle",
                                            "n_prefix"        => "n_prefix",
                                            "n_suffix"        => "n_suffix",
                                            "label"           => "label",
                                            "adr_street"      => "adr_street",
                                            "adr_locality"    => "adr_locality",   //city
                                            "adr_region"      => "adr_region",     //state
                                            "adr_postalcode"  => "adr_postalcode", //zip
                                            "adr_countryname" => "adr_countryname",
                                            "adr_work"        => "adr_work",   //yn
                                            "adr_home"        => "adr_home",   //yn
                                            "adr_parcel"      => "adr_parcel", //yn
                                            "adr_postal"      => "adr_postal", //yn
                                            "tz"              => "tz",
                                            "geo"             => "geo",
                                            "a_tel"           => "a_tel",
                                            "a_tel_work"      => "a_tel_work",   //yn
                                            "a_tel_home"      => "a_tel_home",   //yn
                                            "a_tel_voice"     => "a_tel_voice",  //yn
                                            "a_tel_msg"       => "a_tel_msg",    //yn
                                            "a_tel_fax"       => "a_tel_fax",    //yn
                                            "a_tel_prefer"    => "a_tel_prefer", //yn
                                            "b_tel"           => "b_tel",
                                            "b_tel_work"      => "b_tel_work",   //yn
                                            "b_tel_home"      => "b_tel_home",   //yn
                                            "b_tel_voice"     => "b_tel_voice",  //yn
                                            "b_tel_msg"       => "b_tel_msg",    //yn
                                            "b_tel_fax"       => "b_tel_fax",    //yn
                                            "b_tel_prefer"    => "b_tel_prefer", //yn
                                            "c_tel"           => "c_tel",
                                            "c_tel_work"      => "c_tel_work",   //yn
                                            "c_tel_home"      => "c_tel_home",   //yn
                                            "c_tel_voice"     => "c_tel_voice",  //yn
                                            "c_tel_msg"       => "c_tel_msg",    //yn
                                            "c_tel_fax"       => "c_tel_fax",    //yn
                                            "c_tel_prefer"    => "c_tel_prefer", //yn
                                            "d_email"         => "d_email",
                                            "d_emailtype"     => "d_emailtype",   //'INTERNET','CompuServe',etc...
                                            "d_email_work"    => "d_email_work",  //yn
                                            "d_email_home"    => "d_email_home",  //yn
                                            );

        $this->email_types = array("INTERNET"   => "INTERNET",
                                   "CompuServe" => "CompuServe",
                                   "AOL"        => "AOL",
                                   "Prodigy"    => "Prodigy",
                                   "eWorld"     => "eWorld",
                                   "AppleLink"  => "AppleLink",
                                   "AppleTalk"  => "AppleTalk",
                                   "PowerShare" => "PowerShare",
                                   "IBMMail"    => "IBMMail",
                                   "ATTMail"    => "ATTMail",
                                   "MCIMail"    => "MCIMail",
                                   "X.400"      => "X.400",
                                   "TLX"        => "TLX"
                                   );
     }

     function read_single_entry($id,$fields) // send this the id and whatever fields you want to see
     {
        list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);
        if (count($stock_fieldnames)) {
           $t_fields = "," . implode(",",$stock_fieldnames);
           if ($t_fields == ",") {
              unset($t_fields);
           }
        }

        $this->db2 = $this->db;
 
        $this->db->query("select id,lid,tid,owner $t_fields from $this->std_table WHERE id='$id'");
        $this->db->next_record();
       
        $return_fields[0]["id"]     = $this->db->f("id"); // unique id
	$return_fields[0]["lid"]    = $this->db->f("lid"); // lid for group/account records
	$return_fields[0]["tid"]    = $this->db->f("tid"); // type id (g/u) for groups/accounts
        $return_fields[0]["owner"]  = $this->db->f("owner"); // id of owner/parent for the record
        if (gettype($stock_fieldnames) == "array") {
          while (list($f_name) = each($stock_fieldnames)) {
            $return_fields[0][$f_name] = $this->db->f($f_name);
          }
        }
        $this->db2->query("select contact_name,contact_value from $this->ext_table where contact_id='"
                           . $this->db->f("id") . "'",__LINE__,__FILE__);
        while ($this->db2->next_record()) {
          // If its not in the list to be returned, don't return it.
          // This is still quicker then 5(+) separate queries
          if ($extra_fields[$this->db2->f("contact_name")]) {
            $return_fields[0][$this->db2->f("contact_name")] = $this->db2->f("contact_value");
          }
        }

        return $return_fields;
     }

     function read($start,$offset,$fields=array(),$query="",$filter="",$sort="",$order="") // send this the range,query,sort,order
                                                                                   // and whatever fields you want to see
     {
        global $phpgw,$phpgw_info;
	$DEBUG = 1;

        list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);
        if (count($stock_fieldnames)) {
           $t_fields = "," . implode(",",$stock_fieldnames);
           if ($t_fields == ",") {
              unset($t_fields);
           }
        }

        if ($filter) {
          if ($DEBUG) { echo "DEBUG - Inbound filter is: #".$filter."#"; }
          $filterarray = split(',',$filter);
          if ($filterarray[1]) {
            $i=0;
            while (list($name,$value) = split('=',$filterarray[$i])) {
              $filterfields[$i] .= array($name => $value);
              $i++;
            }
          } else {
            list($name,$value) = split('=',$filter);
            if ($DEBUG) { echo "<br>DEBUG - Filter intermediate strings 1: #".$name."# => #".$value."#"; }
            $filterfields = array($name => $value);
          }

          $i=0;
          while (list($name,$value) = each($filterfields)) {
            if ($DEBUG) { echo "<br>DEBUG - Filter intermediate strings 2: #".$name."# => #".$value."#"; }
            $filterlist .= $name."='".$value."',";
            $i++;
          }
          $filterlist = substr($filterlist,0,-1);
  
          if ($DEBUG) { echo "<br>DEBUG - Filter output string: #".$filterlist."#"; }
	  
          list($fields,$fieldnames,$extra) = $this->split_stock_and_extras($filterfields); 
          
          if ($extra) {
            while (list($name,$value) = each($extra)) {
              $value = "%," . $value . ",%";
              $filterextra .= " AND contact_name='".$name."' AND contact_value like '".$value."',";
            }
            $filterextra = substr($filterextra,0,-1);
          } else {
            $filterstock = " AND ($filterlist) ";
          }
        }
	if ($DEBUG && ($filterextra || $filterstock)) {
	  if ($filterextra) {
	    echo "<br>DEBUG - Filtering on extra fields with: #" . $filterextra . "#";
	  }
          if ($filterstock) {
	    echo "<br>DEBUG - Filtering on standard fields with: #" . $filterstock . "#";
          }
        }

        if (!$sort)   { $sort = "ASC";  }

        if ($order) {
           $ordermethod = "order by $order $sort ";
        }  else {
           $ordermethod = "order by n_family,n_given,d_email $sort";
        }

        $this->db3 = $this->db2 = $this->db; // Create new result objects before our queries

        if ($query) {
            $this->db3->query("SELECT * from $this->std_table WHERE (n_family like '"
                     . "%$query%' OR n_given like '%$query%' OR d_email like '%$query%' OR "
                     . "adr_street like '%$query%' OR adr_locality like '%$query%' OR adr_region "
                     . "like '%$query%' OR adr_postalcode like '%$query%' OR org_unit like "
                     . "'%$query%' OR org_name like '%$query%') " . $filterstock . $ordermethod,__LINE__,__FILE__);
            $this->total_records = $this->db3->num_rows();

            $this->db->query("SELECT * from $this->std_table WHERE (n_family like '"
                     . "%$query%' OR n_given like '%$query%' OR d_email like '%$query%' OR "
                     . "adr_street like '%$query%' OR adr_locality like '%$query%' OR adr_region "
                     . "like '%$query%' OR adr_postalcode like '%$query%' OR org_unit like "
                     . "'%$query%' OR ORG_Name like '%$query%') " . $filterstock . $ordermethod . " "
		     . $this->db->limit($start,$offset),__LINE__,__FILE__);
        }  else  {
           $this->db3->query("select id,lid,tid,owner $t_fields from $this->std_table "
                       . $filterstock,__LINE__,__FILE__);
           $this->total_records = $this->db3->num_rows();
	
           $this->db->query("select id,lid,tid,owner $t_fields from $this->std_table "
                       . $filterstock . " " . $ordermethod . " " . $this->db->limit($start,$offset),__LINE__,__FILE__);
        }

        $i=0;
        while ($this->db->next_record()) {
           $return_fields[$i]["id"]     = $this->db->f("id"); // unique id 
	   $return_fields[$i]["lid"]    = $this->db->f("lid"); // lid for group/account records
	   $return_fields[$i]["tid"]    = $this->db->f("tid"); // type id (g/u) for groups/accounts
           $return_fields[$i]["owner"]  = $this->db->f("owner"); // id of owner/parent for the record
           if (gettype($stock_fieldnames) == "array") {
              while (list($f_name) = each($stock_fieldnames)) {
                 $return_fields[$i][$f_name] = $this->db->f($f_name);
              }
              reset($stock_fieldnames);
           }

           $this->db2->query("select contact_name,contact_value from $this->ext_table where contact_id='"
                           . $this->db->f("id") . "'" .$filterextra,__LINE__,__FILE__);
           while ($this->db2->next_record()) {
              // If its not in the list to be returned, don't return it.
              // This is still quicker then 5(+) separate queries
              if ($extra_fields[$this->db2->f("contact_name")]) {
                 $return_fields[$i][$this->db2->f("contact_name")] = $this->db2->f("contact_value");
              }
           }
           $i++;
        }
        
        return $return_fields;
     }

     function add($owner,$fields)
     {
        list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);

        //$this->db->lock(array("contacts"));
        $this->db->query("insert into $this->std_table (owner,"
                       . implode(",",$this->stock_contact_fields)
                       . ") values ('$owner','"
                       . implode("','",$this->loop_addslashes($stock_fields)) . "')",__LINE__,__FILE__);

        $this->db->query("select max(id) from $this->std_table ",__LINE__,__FILE__);
        $this->db->next_record();
        $id = $this->db->f(0);
        //$this->db->unlock();

        if (count($extra_fields)) {
           while (list($name,$value) = each($extra_fields)) {
              $this->db->query("insert into $this->ext_table values ('$id','" . $this->account_id . "','"
                             . addslashes($name) . "','" . addslashes($value) . "')",__LINE__,__FILE__);
           }
        }
     }

     function field_exists($id,$field_name)
     {
        $this->db->query("select count(*) from $this->ext_table where contact_id='$id' and contact_name='"
                       . addslashes($field_name) . "'",__LINE__,__FILE__);
        $this->db->next_record();
        return $this->db->f(0);
     }

     function add_single_extra_field($id,$owner,$field_name,$field_value)
     {
        $this->db->query("insert into $this->ext_table values ($id,'$owner','" . addslashes($field_name)
                       . "','" . addslashes($field_value) . "')",__LINE__,__FILE__);
     }

     function delete_single_extra_field($id,$field_name)
     {
        $this->db->query("delete from $this->ext_table where contact_id='$id' and contact_name='"
                       . addslashes($field_name) . "'",__LINE__,__FILE__);
     }

     function update($id,$owner,$fields)
     {
        // First make sure that id number exists
        $this->db->query("select count(*) from $this->std_table where id='$id'",__LINE__,__FILE__);
        $this->db->next_record();
        if (! $this->db->f(0)) {
           return False;
        }

        list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);
        if (count($stock_fields)) {
           while (list($stock_fieldname) = each($stock_fieldnames)) {
              $ta[] = $stock_fieldname . "='" . addslashes($stock_fields[$stock_fieldname]) . "'";
           }
           $fields_s = "," . implode(",",$ta);
           if ($field_s == ",") {
              unset($field_s);
           }
           $this->db->query("update $this->std_table set owner='$owner' $fields_s where "
                          . "id='$id'",__LINE__,__FILE__);
        }

        while (list($x_name,$x_value) = each($extra_fields)) {
           if ($this->field_exists($id,$x_name)) {
              if (! $x_value) {
                 $this->delete_single_extra_field($id,$x_name);
              } else {
                 $this->db->query("update $this->ext_table set contact_value='" . addslashes($x_value)
                                . "',contact_owner='$owner' where contact_name='" . addslashes($x_name)
                                . "' and contact_id='$id'",__LINE__,__FILE__);
              }
           } else {
              $this->add_single_extra_field($id,$owner,$x_name,$x_value);
           }
        }
     }

     // This is where the real work of delete() is done
     function delete_($id)
     {
        $this->db->query("delete from $this->std_table where owner='" . $this->account_id . "' and "
                       . "id='$id'",__LINE__,__FILE__);
        $this->db->query("delete from $this->ext_table where contact_id='$id' and contact_owner='"
                       . $this->account_id . "'",__LINE__,__FILE__);
     }

  }

?>
