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

  $this = CreateObject("addressbook.addressbook");

  if (! $submit) {
    $fields = $this->get_entry($ab_id);
     form("","edit.php","Edit",$fields);
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

    $this->update_entry();
    
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
