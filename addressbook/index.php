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

  $phpgw_info["flags"] = array("currentapp" => "addressbook", "enable_addressbook_class" => True,
                               "enable_nextmatchs_class" => True);
  include("../header.inc.php");

  //echo "<br>Time track = " . $phpgw_info["apps"]["timetrack"]["enabled"];

  if (! $start)
     $start = 0;

  $t = new Template($phpgw_info["server"]["app_tpl"]);
  $t->set_file(array( "addressbook_header"	=> "header.tpl",
		      "column"			=> "column.tpl",
		      "row"			=> "row.tpl",
		      "addressbook_footer"	=> "footer.tpl" ));

  $this = CreateObject("addressbook.addressbook");
  $entries = $this->get_entries($query,$filter,$sort,$order,$start);

  $columns_to_display=$this->columns_to_display;
  if($phpgw_info["user"]["preferences"]["common"]["maxmatchs"] ) {
    $limit = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
  } else { // this must be broken, but it works
    $limit = 15;
  }

  $t->set_var(font,$phpgw_info["theme"]["font"]);
  $t->set_var("lang_view",lang("View"));
  $t->set_var("lang_vcard",lang("VCard"));
  $t->set_var("lang_edit",lang("Edit"));

  $t->set_var(searchreturn,$this->searchreturn);
  $t->set_var(lang_showing,$this->lang_showing);
  $t->set_var(search_filter,$this->search_filter);
  $t->set_var("lang_addressbook",lang("Address book"));
  $t->set_var("th_bg",$phpgw_info["theme"]["th_bg"]);
  $t->set_var("th_font",$phpgw_info["theme"]["font"]);
  $t->set_var("th_text",$phpgw_info["theme"]["th_text"]);
  $t->set_var("lang_add",lang("Add"));
  $t->set_var("lang_addvcard",lang("AddVCard"));
  $t->set_var("lang_import",lang("Import File"));
  $t->set_var("import_url",$phpgw->link("import.php"));
  $t->set_var("start",$start);
  $t->set_var("sort",$sort);
  $t->set_var("order",$order);
  $t->set_var("filter",$filter);
  $t->set_var("qfield",$qfield);
  $t->set_var("query",$query);
  $t->set_var("actionurl",$phpgw->link("add.php","sort=$sort&order=$order&filter=$filter&start=$start"));
  $t->set_var("start",$start);
  $t->set_var("filter",$filter);
  $t->set_var("cols",$this->cols);

  $t->pparse("out","addressbook_header");

  for ($i=0;$i<$limit;$i++) { // each entry
    $t->set_var(columns,"");
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $t->set_var(row_tr_color,$tr_color);
    while ($column = each($columns_to_display)) { // each entry column
      $ref=$data="";
      $coldata = $this->coldata($column[0],$i);
      $myid = $entries->ab_id[$i];
      $myowner = $entries->owner[$i];
      // Some fields require special formatting.       
      if ($column[0] == "url") {
        $ref='<a href="'.$coldata.'" target="_new">';
	$data=$coldata.'</a>';
      } elseif ($column[0] == "email") {
        if ($phpgw_info["user"]["apps"]["email"]) {
          $ref='<a href="'.$phpgw->link($phpgw_info["server"]["webserver_url"]
	    . "/email/compose.php","to=" . urlencode($coldata)).'" target="_new">';
        } else {
          $ref='<a href="mailto:"'.$coldata.'">'.$coldata.'</a>';
        }
        $data=$coldata."</a>";
      } else { // But these do not
        $ref=""; $data=$coldata;
      }
      $t->set_var(col_data,$ref.$data);
      $t->parse("columns","column",True);
    }
    
    reset($columns_to_display); // If we don't reset it, our inside while won't loop

    $t->set_var(row_view_link,$phpgw->link("view.php","ab_id=$myid&start=$start&order=$order&filter="
      . "$filter&query=$query&sort=$sort"));
    $t->set_var(row_vcard_link,$phpgw->link("vcardout.php","ab_id=$myid&start=$start&order=$order&filter="
      .  "$filter&query=$query&sort=$sort"));
    $t->set_var(row_edit_link,$phpgw->common->check_owner($myowner,"edit.php",lang("edit"),"ab_id="
      .$myid."&start=".$start."&sort=".$sort."&order=".$order."&query=".$query."&sort=".$sort));

    $t->parse("rows","row",True);
    $t->pparse("out","row");
  }

  $t->pparse("out","addressbook_footer");

  $phpgw->common->phpgw_footer();
?>
