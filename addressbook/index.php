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

#  $t->set_block("addressbook_header","searchfilter","body",
#  		      "addressbook_footer","output");

  $limit =$phpgw->nextmatchs->sql_limit($start);

  if ($order)
     $ordermethod = "order by $order $sort";
  else
     $ordermethod = "order by ab_lastname,ab_firstname,ab_email asc";

  if (! $filter) {
     $filter = "none";
  }

  if ($filter != "private") {
     if ($filter != "none") {
        $filtermethod = " ab_access like '%,$filter,%' ";
     } else {
        $filtermethod = " (ab_owner='" . $phpgw_info["user"]["account_id"] ."' OR ab_access='public' "
                      . $phpgw->accounts->sql_search("ab_access") . " ) ";
     }
  } else {
     $filtermethod = " ab_owner='" . $phpgw_info["user"]["account_id"] . "' ";
  }

  if ($query) {
    if ($phpgw_info["apps"]["timetrack"]["enabled"]){
     $phpgw->db->query("SELECT count(*) "
       . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
       . "AND $filtermethod AND (a.ab_lastname like '"
       . "%$query%' OR a.ab_firstname like '%$query%' OR a.ab_email like '%$query%' OR "
       . "a.ab_street like '%$query%' OR a.ab_city like '%$query%' OR a.ab_state "
       . "like '%$query%' OR a.ab_zip like '%$query%' OR a.ab_notes like "
       . "'%$query%' OR c.company_name like '%$query%' OR a.ab_url like '%$query%')",__LINE__,__FILE__);
//       . "'%$query%' OR c.company_name like '%$query%')"
//       . " $ordermethod limit $limit");
     } else {
     $phpgw->db->query("SELECT count(*) "
       . "from addressbook "
       . "WHERE $filtermethod AND (ab_lastname like '"
       . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
       . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
       . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
       . "'%$query%' OR ab_company like '%$query%' OR ab_url like '%$query$%')",__LINE__,__FILE__);
//       . "'%$query%' OR ab_company like '%$query%')"
//       . " $ordermethod limit $limit");
     }

    $phpgw->db->next_record();

     if ($phpgw->db->f(0) == 1)
        $t->set_var(searchreturn,lang("your search returned 1 match"));
     else
        $t->set_var(searchreturn,lang("your search returned x matchs",$phpgw->db->f(0)));
  } else {
     $t->set_var(searchreturn,"");
     $phpgw->db->query("select count(*) from addressbook where $filtermethod",__LINE__,__FILE__);
     $phpgw->db->next_record();
  }
  if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
     $company_sortorder = "c.company_name";
  } else {
     $company_sortorder = "ab_company";
  }

  //$phpgw->db->next_record();

  if ($phpgw->db->f(0) > $phpgw_info["user"]["preferences"]["common"]["maxmatchs"])
     $t->set_var(lang_showing,lang("showing x - x of x",($start + 1),($start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]),$phpgw->db->f(0)));
  else
     $t->set_var(lang_showing,lang("showing x",$phpgw->db->f(0)));
  
  $t->set_var("search_filter",$phpgw->nextmatchs->show_tpl("index.php",$start,$phpgw->db->f(0),"&order=$order&filter=$filter&sort=$sort&query=$query", "75%", $phpgw_info["theme"]["th_bg"]));

  while ($column = each($abc)) {
    if (isset($phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) &&
      $phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) {
      $cols .= '<td height="21">';
      $cols .= '<font size="-1" face="Arial, Helvetica, sans-serif">';
      $cols .= $phpgw->nextmatchs->show_sort_order($sort,"ab_" . $column[0],$order,"index.php",lang($column[1]));
      $cols .= '</font></td>';
      $cols .= "\n";
             
      // To be used when displaying the rows
      $columns_to_display[$column[0]] = True;
    }
  }

  if (isset($query) && $query) {
     if (isset($phpgw_info["apps"]["timetrack"]["enabled"]) &&
	 $phpgw_info["apps"]["timetrack"]["enabled"]) {
        $phpgw->db->query("SELECT a.ab_id,a.ab_owner,a.ab_firstname,a.ab_lastname,a.ab_company_id,"
                        . "a.ab_email,a.ab_wphone,c.company_name,a.ab_hphone,a.ab_fax,a.ab_mphone "
                        . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
                        . "AND $filtermethod AND (a.ab_lastname like '"
                        . "%$query%' OR a.ab_firstname like '%$query%' OR a.ab_email like '%$query%' OR "
                        . "a.ab_street like '%$query%' OR a.ab_city like '%$query%' OR a.ab_state "
                        . "like '%$query%' OR a.ab_zip like '%$query%' OR a.ab_notes like "
                        . "'%$query%' OR c.company_name like '%$query%') $ordermethod limit $limit",__LINE__,__FILE__);
     } else {
       $phpgw->db->query("SELECT * from addressbook WHERE $filtermethod AND (ab_lastname like '"
                       . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
                       . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
                       . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
                       . "'%$query%' OR ab_company like '%$query%') $ordermethod limit $limit",__LINE__,__FILE__);
    }
  } else {
    if ($phpgw_info["apps"]["timetrack"]["enabled"]){
       $phpgw->db->query("SELECT a.ab_id,a.ab_owner,a.ab_firstname,a.ab_lastname,"
                       . "a.ab_email,a.ab_wphone,c.company_name "
                       . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
                       . "AND $filtermethod $ordermethod limit $limit",__LINE__,__FILE__);
    } else {
       $phpgw->db->query("SELECT * from addressbook WHERE $filtermethod $ordermethod limit $limit",__LINE__,__FILE__);
    }
  }		// else $query
  $rows="";
  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    $rows .= '<tr bgcolor="#'.$tr_color . '">';
    
    $ab_id = $phpgw->db->f("ab_id");
    
    while ($column = each($columns_to_display)) {
      if ($column[0] == "company") {
        if ($phpgw_info["apps"]["timetrack"]["enabled"]) {        
          $field   = $phpgw->db->f("company_name");
        } else {
          $field = $phpgw->db->f("ab_company");
        }
      } else {
        $field = $phpgw->db->f("ab_" . $column[0]);
      }

      $field = htmlentities($field);

      // Some fields require special formating.       
      if ($column[0] == "url") {
        if (! ereg("^http://",$field)) {
          $data = "http://" . $field;
        }
        $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
          . '<a href="' . $field . '" target="_new">' . $field. '</a>&nbsp;</font></td>';
        } else if ($column[0] == "email") {
          if ($phpgw_info["user"]["apps"]["email"]) {
          $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
             . '<a href="' . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/email/compose.php",
                                         "to=" . urlencode($field)) . '" target="_new">' . $field . '</a>&nbsp;</font></td>';
          } else {
          $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
             . '<a href="mailto:' . $field . '">' . $field. '</a>&nbsp;</font></td>';
          }
      } else {
        $rows .= '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
          . $field . '&nbsp;</font></td>';
      }
      #echo '</tr>';
    }
    reset($columns_to_display);		// If we don't reset it, our inside while won't loop
    $rows .= '<td valign="top" width="3%">
 	<font face="'.$phpgw_info["theme"]["font"].'" size="2">
      <a href="'. $phpgw->link("view.php","ab_id=$ab_id&start=$start&order=$order&filter="
								 . "$filter&query=$query&sort=$sort").'
	  ">'.lang("View").'</a>
     </font>
    </td>
     <td valign=top width=3%>
      <font face="'.$phpgw_info["theme"]["font"].'" size=2>
        <a href="'.$phpgw->link("vcardout.php","ab_id=$ab_id&start=$start&order=$order&filter="
                . "$filter&query=$query&sort=$sort").'
        ">'.lang("vcard").'</a>
      </font>
     </td>
    <td valign="top" width="5%">
     <font face="'.$phpgw_info["theme"]["font"].'" size="2">
      '.$phpgw->common->check_owner($phpgw->db->f("ab_owner"),"edit.php",lang("edit"),"ab_id=" . $phpgw->db->f("ab_id")."&start=".$start."&sort=".$sort."&order=".$order).'
     </font>
    </td>
   </tr>
';
  }

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
  $t->set_var("actionurl",$phpgw->link("add.php?sort=$sort&order=$order&filter=$filter&start=$start"));
  $t->set_var("start",$start);
  $t->set_var("filter",$filter);
  $t->set_var("cols",$cols);
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
