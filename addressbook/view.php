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

  $phpgw_info["flags"] = array("currentapp" => "addressbook",
			       "enable_contacts_class" => True,
                               "enable_nextmatchs_class" => True);

  include("../header.inc.php");

  $t = new Template($phpgw_info["server"]["app_tpl"]);
  $t->set_file(array( "view"	=> "view.tpl"));

  $this = CreateObject("phpgwapi.contacts");

  if (! $ab_id) {
    Header("Location: " . $phpgw->link("index.php"));
  }

  // Need to replace abc with $this->stock_addressbook_fields
  while ($column = each($abc)) {
    if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) &&
      $phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) {
      $columns_to_display[$column[0]] = True;
      $colname[$column[0]] = $column[1];
    }
  }

  $fields = $this->read_single_entry($ab_id,$this->stock_addressbook_fields);

  $access = $fields[0]["access"];
  $owner  = $fields[0]["owner"];
 
  $view_header  = "<p>&nbsp;<b>" . lang("Address book - view") . "</b><hr><p>";
  $view_header .= '<table border="0" cellspacing="2" cellpadding="2" width="80%" align="center">';
 
  while ($column = each($columns_to_display)) { // each entry column
    $columns_html .= "<tr><td><b>" . lang($colname[$column[0]]) . "</b>:</td>";
    $ref=$data="";
    $coldata = $fields[0][$column[0]];
    // Some fields require special formatting.       
    if ($column[0] == "url") {
      $ref='<a href="'.$coldata.'" target="_new">';
      $data=$coldata.'</a>';
    } elseif ($column[0] == "email") {
      if ($phpgw_info["user"]["apps"]["email"]) {
        $ref='<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"]
            . "/email/compose.php","to=" . urlencode($coldata)).'" target="_new">';
      } else {
        $ref='<a href="mailto:'.$coldata.'">';
      }
      $data=$coldata."</a>";
    } else { // But these do not
      $ref=""; $data=$coldata;
    }
    $columns_html .= "<td>" . $ref . $data . "</td>";
  }

  $columns_html .= '<tr><td colspan="4">&nbsp;</td></tr>'
  . '<tr><td><b>' . lang("Record owner") . '</b></td><td>'
  . $phpgw->common->grab_owner_name($owner) . '</td><td><b>'
  . lang("Record Access") . '</b></td><td></table>';
     
  if ($access != "private" && $access != "public") {
    $access_link .= lang("Group access") . " - " . $phpgw->accounts->convert_string_to_names_access($access);
  } else {
    $access_link .= $access;
  }

  $editlink  = $phpgw->common->check_owner($owner,"edit.php",lang("edit"),"ab_id=" . $ab_id . "&start=".$start."&sort=".$sort."&order=".$order);
  $vcardlink = '<form action="'.$phpgw->link("vcardout.php","ab_id=$ab_id&order=$order&start=$start&filter=$filter&query=$query&sort=$sort").'">';
  $donelink  = '<form action="'.$phpgw->link("index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort").'">';

  $t->set_var("ab_id",$ab_id);
  $t->set_var("sort",$sort);
  $t->set_var("order",$order);
  $t->set_var("filter",$filter);
  $t->set_var("start",$start);
  $t->set_var("view_header",$view_header);
  $t->set_var("cols",$columns_html);
  $t->set_var("lang_ok",lang("ok"));
  $t->set_var("lang_done",lang("done"));
  $t->set_var("lang_edit",lang("edit"));
  $t->set_var("lang_submit",lang("submit"));
  $t->set_var("lang_vcard",lang("vcard"));
  $t->set_var("done_link",$donelink);
  $t->set_var("edit_link",$editlink);
  $t->set_var("vcard_link",$vcardlink);

  $t->parse("out","view");
  $t->pparse("out","view");

  $phpgw->common->phpgw_footer();
?>
