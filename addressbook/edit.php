<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if ($submit || ! $ab_id) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "addressbook";
  $phpgw_info["flags"]["enable_addressbook_class"] = True;
  include("../header.inc.php");
  
  $t = new Template($phpgw_info["server"]["app_tpl"]);
  $t->set_file(array( "edit"	=> "edit.tpl"));

  if (! $ab_id) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]. "/addressbook/",
	       "cd=16&order=$order&sort=$sort&filter=$filter&start=$start&query=$query"));
     $phpgw->common->phpgw_exit();
  }

  $this = CreateObject("phpgwapi.contacts");

  if (! $submit) {
    // merge in what are now extra fields
    $extrafields = array ("pager" => "pager",
                          "mphone" => "mphone",
			  "ophone" => "ophone",
			  "address2" => "address2",
			  "bday" => "bday",
			  "url" => "url",
			  "notes" => "notes");
    $qfields = $this->stock_contact_fields + $extrafields;
    $fields = $this->read_single_entry($ab_id,$qfields);
    form("","edit.php","Edit",$fields[0]);
  } else {
    if ($url == "http://") {
      $url = "";
    }
    if (! $bday_month && ! $bday_day && ! $bday_year) {
      $bday = "";
    } else {
      $bday = "$bday_month/$bday_day/$bday_year";
    }
    if ($access != "private" && $access != "public") {
      $access = $phpgw->accounts->array_to_string($access,$n_groups);
    }

    $fields["ORG_Name"]       = $company;
    $fields["N_Given"]        = $firstname;
    $fields["N_Family"]       = $lastname;
    $fields["D_EMAIL"]        = $email;
    $fields["TITLE"]          = $title;
    $fields["A_TEL"]          = $wphone;
    $fields["B_TEL"]          = $hphone;
    $fields["C_TEL"]          = $fax;
    $fields["pager"]          = $pager;
    $fields["mphone"]         = $mphone;
    $fields["ophone"]         = $ophone;
    $fields["ADR_Street"]     = $street;
    $fields["address2"]       = $address2;
    $fields["ADR_Locality"]   = $city;
    $fields["ADR_Region"]     = $state;
    $fields["ADR_PostalCode"] = $zip;
    $fields["bday"]           = $bday;
    $fields["url"]            = $url;
    $fields["notes"]          = $notes;

    $this->update($ab_id,$phpgw_info["user"]["account_id"],$fields);
    
    Header("Location: " . $phpgw->link("view.php","&ab_id=$ab_id&order=$order&sort=$sort&filter="
      . "$filter&start=$start"));
    $phpgw->common->phpgw_exit();
  }

  $t->set_var("ab_id",$ab_id);
  $t->set_var("sort",$sort);
  $t->set_var("order",$order);
  $t->set_var("filter",$filter);
  $t->set_var("start",$start);
  $t->set_var("lang_ok",lang("ok"));
  $t->set_var("lang_clear",lang("clear"));
  $t->set_var("lang_cancel",lang("cancel"));
  $t->set_var("lang_delete",lang("delete"));
  $t->set_var("lang_submit",lang("submit"));
  $t->set_var("cancel_link",'<form action="'.$phpgw->link("index.php","sort=$sort&order=$order&filter=$filter&start=$start") . '">');
  $t->set_var("delete_link",'<form action="'.$phpgw->link("delete.php","ab_id=$ab_id") . '">');

  $t->parse("out","edit");
  $t->pparse("out","edit");

  $phpgw->common->phpgw_footer();
?>
