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
		      "searchfilter"		=> "searchfilter.tpl",
		      "body"			=> "list.tpl",
		      "addressbook_footer"	=> "footer.tpl" ));

  $this = CreateObject("addressbook.addressbook");
  $entries = $this->get_entries($query,$filter,$sort,$order,$start);

  $rows="";
  $columns_to_display=$this->columns_to_display;
  if($phpgw_info["user"]["preferences"]["common"]["maxmatchs"] ) {
    $limit = $phpgw_info["user"]["preferences"]["common"]["maxmatchs"];
  } else { // this must be broken, but it works
    $limit = 15;
  }

  for ($i=0;$i<$limit;$i++) { // each entry
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $rows .= '<tr bgcolor="#'.$tr_color . '">';
    while ($column = each($columns_to_display)) { // each entry column
      $colname = $this->colname($column[0],$i);
      $myid = $entries->ab_id[$i];
      $myowner = $entries->owner[$i];
      // Some fields require special formatting.       
      if ($column[0] == "url") {
        $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
        . '<a href="' . $colname . '" target="_new">' . $colname . '</a>&nbsp;</font></td>';
      } else if ($column[0] == "email") {
        if ($phpgw_info["user"]["apps"]["email"]) {
          $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
          . '<a href="' . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/email/compose.php",
          "to=" . urlencode($colname)) . '" target="_new">' . $colname . '</a>&nbsp;</font></td>';
        } else {
          $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
          . '<a href="mailto:' . $colname . '">' . $colname . '</a>&nbsp;</font></td>';
        }
      } else { // But these do not
        $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
        . $colname . '&nbsp;</font></td>';
      }
    }

    reset($columns_to_display); // If we don't reset it, our inside while won't loop

    $rows .= '<td valign="top" width="3%">
    <font face="'.$phpgw_info["theme"]["font"].'" size="2">
    <a href="'. $phpgw->link("view.php","ab_id=$myid&start=$start&order=$order&filter="
	      . "$filter&query=$query&sort=$sort").'
     ">'.lang("View").'</a>
     </font>
    </td>
     <td valign=top width=3%>
      <font face="'.$phpgw_info["theme"]["font"].'" size=2>
        <a href="'.$phpgw->link("vcardout.php","ab_id=$myid&start=$start&order=$order&filter="
                  . "$filter&query=$query&sort=$sort").'
        ">'.lang("vcard").'</a>
      </font>
     </td>
    <td valign="top" width="5%">
     <font face="'.$phpgw_info["theme"]["font"].'" size="2">
      '.$phpgw->common->check_owner($myowner,"edit.php",lang("edit"),"ab_id=".$myid."&start=".$start."&sort=".$sort."&order=".$order."&query=".$query."&sort=".$sort).'
     </font>
    </td>
   </tr>
';
  }

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
  $t->set_var("lang_view",lang("View"));
  $t->set_var("lang_vcard",lang("VCard"));
  $t->set_var("lang_edit",lang("Edit"));
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
  $t->set_var("rows",$rows);

  $t->parse("out","addressbook_header");
  $t->pparse("out","addressbook_header");
  $t->parse("out","searchfilter");
  $t->pparse("out","searchfilter");
  $t->parse("out","body");
  $t->pparse("out","body");
  $t->parse("out","addressbook_footer");
  $t->pparse("out","addressbook_footer");

  $phpgw->common->phpgw_footer();
?>
