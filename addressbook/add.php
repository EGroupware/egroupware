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

  if ($submit || $AddVcard) {
     $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_info["flags"]["currentapp"] = "addressbook";
  $phpgw_info["flags"]["enable_addressbook_class"] = True;
  include("../header.inc.php");
  
  $t = new Template($phpgw_info["server"]["app_tpl"]);
  $t->set_file(array( "add"	=> "add.tpl"));

  $this = CreateObject("addressbook.addressbook");

  if ($AddVcard){
       Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] .
              "/addressbook/vcardin.php"));
  }
  else if ($add_email) {
     list($fields["firstname"],$fields["lastname"]) = explode(" ", $name);
     $fields["email"] = $add_email;
     form("","add.php","Add",$fields);
  } else if (! $submit && ! $add_email) {
     form("","add.php","Add","","","");
  } else {
     if (! $bday_month && ! $bday_day && ! $bday_year) {
        $bday = "";
     } else {
        $bday = "$bday_month/$bday_day/$bday_year";
     }
     if ($access != "private" && $access != "public") {
        $access = $phpgw->accounts->array_to_string($access,$n_groups);
     }
     if ($url == "http://") {
        $url = "";
     }

     $this->id         = $ab_id;
     $this->company    = $company;
     $this->company_id = $company_id;
     $this->firstname  = $firstname;
     $this->lastname   = $lastname;
     $this->email      = $email;
     $this->title      = $title;
     $this->wphone     = $wphone;
     $this->hphone     = $hphone;
     $this->fax        = $fax;
     $this->pager      = $pager;
     $this->mphone     = $mphone;
     $this->ophone     = $ophone;
     $this->street     = $street;
     $this->address2   = $address2;
     $this->city       = $city;
     $this->state      = $state;
     $this->zip        = $zip;
     $this->bday       = $bday;
     $this->url        = $url;
     $this->notes      = $notes;
     $this->access     = $access;

     $this->add_entry();

     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/addressbook/",
            "cd=14"));
  }

  $t->set_var("lang_ok",lang("ok"));
  $t->set_var("lang_clear",lang("clear"));
  $t->set_var("lang_cancel",lang("cancel"));
  $t->set_var("cancel_url",$phpgw->link("index.php?sort=$sort&order=$order&filter=$filter&start=$start"));
  $t->parse("out","add");
  $t->pparse("out","add");

  $phpgw->common->phpgw_footer();
?>
