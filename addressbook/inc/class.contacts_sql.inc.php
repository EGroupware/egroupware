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

  	/*!
	 @class acl
	 @abstract Contact List System
	 @discussion Author: jengo/Milosch <br>
	 This class provides a contact database scheme. <br>
	 It attempts to be based on the vcard 2.1 standard, with mods as needed to make for more reasonable sql storage. <br>
	 Syntax: CreateObject('phpgwapi.contacts'); <br>
	 Example1: $contacts = CreateObject('phpgwapi.contacts');
	*/
	class contacts_
	{
		var $db;
		var $std_table="phpgw_addressbook";
		var $ext_table="phpgw_addressbook_extra";

		var $account_id;
		var $stock_contact_fields;	// This is an array of almost the fields in the phpgw_addressbook table, except id,owner,lid,tid
		var $email_types;			// VCard email type array
		var $total_records;			// This will contain numrows for data retrieved

		function contacts_()
		{
			global $phpgw, $phpgw_info;

			$this->db = $phpgw->db;
			$this->account_id = $phpgw_info["user"]["account_id"];

    	    $this->stock_contact_fields = array(
				"fn"                     => "fn",        // 'prefix given middle family suffix'
				"n_given"                => "n_given",   // firstname
				"n_family"               => "n_family",  // lastname
				"n_middle"               => "n_middle",
				"n_prefix"               => "n_prefix",
				"n_suffix"               => "n_suffix",
				"sound"                  => "sound",
				"bday"                   => "bday",
				"note"                   => "note",
				"tz"                     => "tz",
				"geo"                    => "geo",
				"url"                    => "url",
				"pubkey"                 => "pubkey",

				"org_name"               => "org_name",  // company
				"org_unit"               => "org_unit",  // division
				"title"                  => "title",

				"adr_one_street"         => "adr_one_street",
				"adr_one_locality"       => "adr_one_locality", 
				"adr_one_region"         => "adr_one_region", 
				"adr_one_postalcode"     => "adr_one_postalcode",
				"adr_one_countryname"    => "adr_one_countryname",
				"adr_one_type"           => "adr_one_type", // address is domestic/intl/postal/parcel/work/home
				"label"                  => "label", // address label

				"adr_two_street"         => "adr_two_street",
				"adr_two_locality"       => "adr_two_locality", 
				"adr_two_region"         => "adr_two_region", 
				"adr_two_postalcode"     => "adr_two_postalcode",
				"adr_two_countryname"    => "adr_two_countryname",
				"adr_two_type"           => "adr_two_type", // address is domestic/intl/postal/parcel/work/home

				"tel_work"               => "tel_work",
				"tel_home"               => "tel_home",
				"tel_voice"              => "tel_voice",
				"tel_fax"                => "tel_fax", 
				"tel_msg"                => "tel_msg",
				"tel_cell"               => "tel_cell",
				"tel_pager"              => "tel_pager",
				"tel_bbs"                => "tel_bbs",
				"tel_modem"              => "tel_modem",
				"tel_car"                => "tel_car",
				"tel_isdn"               => "tel_isdn",
				"tel_video"              => "tel_video",
				"tel_prefer"             => "tel_prefer", // home, work, voice, etc
				"email"                  => "email",
				"email_type"             => "email_type", //'INTERNET','CompuServe',etc...
				"email_home"             => "email_home",
				"email_home_type"        => "email_home_type" //'INTERNET','CompuServe',etc...
			);

			/* Used to flag an address as being:
			   domestic OR  international(default)
			   parcel(default)
			   postal(default)
			   work(default) OR home
			*/
			$this->adr_types = array(
				"dom"    => lang("Domestic"),
				"intl"   => lang("International"),
				"parcel" => lang("Parcel"),
				"postal" => lang("Postal")
			);

			// Used to set preferred number field
			$this->tel_types = array(
				"work"  => "work",
				"home"  => "home",
				"voice" => "voice",
				"fax"   => "fax",
				"msg"   => "msg",
				"cell"  => "cell",
				"pager" => "pager",
				"bbs"   => "bbs",
				"modem" => "modem",
				"car"   => "car",
				"isdn"  => "isdn",
				"video" => "video"
			);

			// Used to set email_type fields
	        $this->email_types = array(
				"INTERNET"   => "INTERNET",
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

		// send this the id and whatever fields you want to see
		function read_single_entry($id,$fields="")
		{
			if (!$fields || empty($fields)) { $fields = $this->stock_contact_fields; }
			list($stock_fields,$stock_fieldnames,$extra_fields) =
				$this->split_stock_and_extras($fields);

			if (count($stock_fieldnames)) {
				$t_fields = "," . implode(",",$stock_fieldnames);
				if ($t_fields == ",") {
					unset($t_fields);
				}
			}

			$this->db2 = $this->db;
 
			$this->db->query("select id,lid,tid,owner $t_fields from $this->std_table WHERE id='$id'");
			$this->db->next_record();
       
			$return_fields[0]["id"]        = $this->db->f("id"); // unique id
			$return_fields[0]["lid"]       = $this->db->f("lid"); // lid for group/account records
			$return_fields[0]["tid"]       = $this->db->f("tid"); // type id (g/u) for groups/accounts
			$return_fields[0]["owner"]     = $this->db->f("owner"); // id of owner/parent for the record

			// Setup address type fields
			if ($this->db->f("adr_one_type")) {
				$one_type = $this->db->f("adr_one_type");
				reset($this->adr_types);
				while (list($name,$val) = each($this->adr_types)) {
					eval("if (strstr(\$one_type,\$name)) { \$return_fields[0][\"one_\$name\"] = \"on\"; }");
				}
			}
			if ($this->db->f("adr_two_type")) {
				$two_type = $this->db->f("adr_two_type");
				reset($this->adr_types);
				while (list($name,$val) = each($this->adr_types)) {
					eval("if (strstr(\$two_type,\$name)) { \$return_fields[0][\"two_\$name\"] = \"on\"; }");
				}
			}

			if (gettype($stock_fieldnames) == "array") {
				while (list($f_name) = each($stock_fieldnames)) {
					$return_fields[0][$f_name] = $this->db->f($f_name);
				}
			}
			$this->db2->query("select contact_name,contact_value from $this->ext_table where contact_id='" . $this->db->f("id") . "'",__LINE__,__FILE__);
			while ($this->db2->next_record()) {
				// If its not in the list to be returned, don't return it.
				// This is still quicker then 5(+) separate queries
				if ($extra_fields[$this->db2->f("contact_name")]) {
					$return_fields[0][$this->db2->f("contact_name")] = $this->db2->f("contact_value");
				}
			}
			return $return_fields;
		}

		function read_last_entry($fields="")
		{
			if (!$fields || empty($fields)) { $fields = $this->stock_contact_fields; }
			list($stock_fields,$stock_fieldnames,$extra_fields) =
				$this->split_stock_and_extras($fields);

			if (count($stock_fieldnames)) {
				$t_fields = "," . implode(",",$stock_fieldnames);
				if ($t_fields == ",") {
					unset($t_fields);
				}
			}

			$this->db2 = $this->db;
 
			$this->db->query("select max(id) from $this->std_table");
			$this->db->next_record();
       
			$id = $this->db->f("max(id)");
			
			$this->db->query("select id,lid,tid,owner $t_fields from $this->std_table WHERE id='$id'");
			$this->db->next_record();
			
			$return_fields[0]["id"]		= $this->db->f("id");
			$return_fields[0]["lid"]    = $this->db->f("lid");
			$return_fields[0]["tid"]    = $this->db->f("tid");
			$return_fields[0]["owner"]  = $this->db->f("owner");
			if (gettype($stock_fieldnames) == "array") {
				while (list($f_name) = each($stock_fieldnames)) {
					$return_fields[0][$f_name] = $this->db->f($f_name);
				}
			}
			$this->db2->query("select contact_name,contact_value from $this->ext_table where contact_id='" . $this->db->f("id") . "'",__LINE__,__FILE__);
			while ($this->db2->next_record()) {
				// If its not in the list to be returned, don't return it.
				// This is still quicker then 5(+) separate queries
				if ($extra_fields[$this->db2->f("contact_name")]) {
					$return_fields[0][$this->db2->f("contact_name")] = $this->db2->f("contact_value");
				}
			}
			return $return_fields;
		}


		// send this the range, query, sort, order and whatever fields you want to see
		// 'rights' is unused at this time
		function read($start,$offset,$fields="",$query="",$filter="",$sort="",$order="",$rights="")
		{
			global $phpgw,$phpgw_info;

			$tmp_table="phpgw_addressbook_user".$phpgw_info["user"]["account_id"];

			if (!$fields || empty($fields)) { $fields = $this->stock_contact_fields; }
			$DEBUG = 0;

			list($stock_fields,$stock_fieldnames,$extra_fields) = $this->split_stock_and_extras($fields);
			if (count($stock_fieldnames)) {
				$t_fields = "," . implode(",",$stock_fieldnames);
				if ($t_fields == ",") {
					unset($t_fields);
				}
			}

			// turn filter's a=b,c=d OR a=b into an array
			if ($filter) {
				if ($DEBUG) { echo "DEBUG - Inbound filter is: #".$filter."#"; }
				$filterarray = split(',',$filter);
				if ($filterarray[1]) {
					$i=0;
					for ($i=0;$i<count($filterarray);$i++) {
						list($name,$value) = split("=",$filterarray[$i]);
						if ($name) {
							if ($DEBUG) { echo "<br>DEBUG - Filter intermediate strings 1: #".$name."# => #".$value."#"; }
							$filterfields[$name] = $value;
						}
					}
				} else {
					list($name,$value) = split('=',$filter);
					if ($DEBUG) {
						echo "<br>DEBUG - Filter intermediate strings 1: #".$name."# => #".$value."#";
					}
					$filterfields = array($name => $value);
				}

				// now check each element of the array and convert into SQL for queries
				// below
				$i=0;
				reset($filterfields);
				while (list($name,$value) = each($filterfields)) {
					if ($DEBUG) { echo "<br>DEBUG - Filter intermediate strings 2: #".$name."# => #".$value."#"; }
					$isstd=0;
					if ($name && empty($value)) {
						if ($DEBUG) { echo "<br>DEBUG - filter field '".$name."' is empty (NULL)"; }
						$check_stock = $this->stock_contact_fields + array('id' => 'id', 'tid' => 'tid', 'lid' => 'lid', 'owner' => 'owner');
						while (list($fname,$fvalue)=each($check_stock)) {
							if ($fvalue==$name) {
								$filterlist .= $name.' is NULL,';
								if ($DEBUG) { echo "<br>DEBUG - filter field '".$name."' is a stock field"; }
								break;
							}
						}
					} elseif($name && $value) {
						reset($stock_fields);
						while (list($fname,$fvalue)=each($stock_fields)) {
							if ($fvalue==$name) {
								$filterlist .= $name.'="'.$value.'",';
								break;
							}
						}
					}
					$i++;
				}
				$filterlist  = substr($filterlist,0,-1);
				$filterlist  = ereg_replace(","," AND ",$filterlist);

				if ($DEBUG) {
					echo "<br>DEBUG - Filter output string: #".$filterlist."#";
				}

				if ($filterlist) {
					$filtermethod = '('.$filterlist.') ';
					$fwhere = ' WHERE '; $fand = ' AND ';
				}
			}
			if ($DEBUG && $filtermethod) {
				echo "<br>DEBUG - Filtering with: #" . $filtermethod . "#";
			}

			if (!$sort) { $sort = "ASC";  }

			if ($order) {
				$ordermethod = "order by $order $sort ";
			}  else {
				$ordermethod = "order by n_family,n_given,email $sort";
			}

			if ($DEBUG && $ordermethod) {
				echo "<br>DEBUG - $ordermethod";
			}

			$this->db3 = $this->db2 = $this->db; // Create new result objects before our queries

			if ($query) {
				$this->db3->query("SELECT * FROM $this->std_table WHERE (n_family LIKE '"
					. "%$query%' OR n_given LIKE '%$query%' OR email LIKE '%$query%' OR "
					. "adr_one_street LIKE '%$query%' OR adr_one_locality LIKE '%$query%' OR adr_one_region LIKE '%$query%' OR "
					. "adr_one_postalcode LIKE '%$query%' OR adr_one_countryname LIKE '%$query%' OR "
					. "adr_two_street LIKE '%$query%' OR adr_two_locality LIKE '%$query%' OR adr_two_region LIKE '%$query%' OR "
					. "adr_two_postalcode LIKE '%$query%' OR adr_two_countryname LIKE '%$query%' OR "
					. "org_name LIKE '%$query%' OR org_unit LIKE '%$query%') " . $fand . $filtermethod . $ordermethod,__LINE__,__FILE__); 
				$this->total_records = $this->db3->num_rows();

				$this->db->query("SELECT * FROM $this->std_table WHERE (n_family LIKE '"
					. "%$query%' OR n_given LIKE '%$query%' OR email LIKE '%$query%' OR "
					. "adr_one_street LIKE '%$query%' OR adr_one_locality LIKE '%$query%' OR adr_one_region LIKE '%$query%' OR "
					. "adr_one_postalcode LIKE '%$query%' OR adr_one_countryname LIKE '%$query%' OR "
					. "adr_two_street LIKE '%$query%' OR adr_two_locality LIKE '%$query%' OR adr_two_region LIKE '%$query%' OR "
					. "adr_two_postalcode LIKE '%$query%' OR adr_two_countryname LIKE '%$query%' OR "
					. "org_name LIKE '%$query%' OR org_unit LIKE '%$query%') " . $fand . $filtermethod . $ordermethod . " "
					. $this->db->limit($start,$offset),__LINE__,__FILE__);
			}  else  {
				$this->db3->query("SELECT id,lid,tid,owner $t_fields FROM $this->std_table " . $fwhere
					. $filtermethod,__LINE__,__FILE__);
				$this->total_records = $this->db3->num_rows();

				$this->db->query("SELECT id,lid,tid,owner $t_fields FROM $this->std_table " . $fwhere
				. $filtermethod . " " . $ordermethod . " " . $this->db->limit($start,$offset),__LINE__,__FILE__);
			}

			$i=0;
			while ($this->db->next_record()) {
				// unique id, lid for group/account records,
				// type id (g/u) for groups/accounts, and
				// id of owner/parent for the record
				$return_fields[$i]["id"]     = $this->db->f("id");
				$return_fields[$i]["lid"]    = $this->db->f("lid");
				$return_fields[$i]["tid"]    = $this->db->f("tid");
				$return_fields[$i]["owner"]  = $this->db->f("owner");

				if (gettype($stock_fieldnames) == "array") {
					while (list($f_name) = each($stock_fieldnames)) {
						$return_fields[$i][$f_name] = $this->db->f($f_name);
					}
					reset($stock_fieldnames);
				}
				$this->db2->query("SELECT contact_name,contact_value FROM $this->ext_table WHERE contact_id='"
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

		// This is where the real work of delete() is done, shared class file contains calling function
		function delete_($id)
		{
			$this->db->query("delete from $this->std_table where owner='" . $this->account_id . "' and "
			. "id='$id'",__LINE__,__FILE__);
			$this->db->query("delete from $this->ext_table where contact_id='$id' and contact_owner='"
			. $this->account_id . "'",__LINE__,__FILE__);
		}

	}

?>
