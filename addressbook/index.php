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

  $phpgw_info["flags"]["currentapp"] = "addressbook";

  include("../header.inc.php");

  echo "<center>" . lang("Address book");
  //echo "<br>Time track = " . $phpgw_info["apps"]["timetrack"]["enabled"];

  if (! $start)
     $start = 0;

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
       . "'%$query%' OR c.company_name like '%$query%' OR a.ab_url like '%$query%')");
//       . "'%$query%' OR c.company_name like '%$query%')"
//       . " $ordermethod limit $limit");
     } else {
     $phpgw->db->query("SELECT count(*) "
       . "from addressbook "
       . "WHERE $filtermethod AND (ab_lastname like '"
       . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
       . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
       . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
       . "'%$query%' OR ab_company like '%$query%' OR ab_url like '%$query$%')");
//       . "'%$query%' OR ab_company like '%$query%')"
//       . " $ordermethod limit $limit");
     }

    $phpgw->db->next_record();

     if ($phpgw->db->f(0) == 1)
        echo "<br>" . lang("your search returned 1 match");
     else
        echo "<br>" . lang("your search returned x matchs",$phpgw->db->f(0));
  } else {
     $phpgw->db->query("select count(*) from addressbook where $filtermethod");
     $phpgw->db->next_record();
  }
  if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
     $company_sortorder = "c.company_name";
  } else {
     $company_sortorder = "ab_company";
  }

  //$phpgw->db->next_record();

  if ($phpgw->db->f(0) > $phpgw_info["user"]["preferences"]["common"]["maxmatchs"])
     echo "<br>" . lang("showing x - x of x",($start + 1),
			   ($start + $phpgw_info["user"]["preferences"]["common"]["maxmatchs"]),$phpgw->db->f(0));
  else
     echo "<br>" . lang("showing x",$phpgw->db->f(0)); 
?>

<?php
 $phpgw->nextmatchs->show("index.php",$start,$phpgw->db->f(0),"&order=$order&filter=$filter&sort="
		              . "$sort&query=$query", "75%", $phpgw_info["theme"]["th_bg"]);
?>

  <table width=75% border=0 cellspacing=1 cellpadding=3>
    <tr bgcolor="<?php echo $phpgw_info["theme"]["th_bg"]; ?>">
    <?php    
       while ($column = each($abc)) {
          if ($phpgw_info["user"]["preferences"]["addressbook"][$column[0]]) {
             echo '<td height="21">';
             echo '<font size="-1" face="Arial, Helvetica, sans-serif">';
             echo $phpgw->nextmatchs->show_sort_order($sort,"ab_" . $column[0],$order,"index.php",lang($column[1]));
             echo '</font></td>';
             echo "\n";
             
             // To be used when displaying the rows
             $columns_to_display[$column[0]] = True;
          }
       }
    ?>

      <td width="3%" height="21">
       <font face="Arial, Helvetica, sans-serif" size="-1">
         <?php echo lang("View"); ?>
       </font>
      </td>
      <td width="5%" height="21">
       <font face="Arial, Helvetica, sans-serif" size="-1">
         <?php echo lang("Edit"); ?>
       </font>
      </td>
    </tr>
  </form>

<?php
  if ($query) {
     if ($phpgw_info["apps"]["timetrack"]["enabled"]){
        $phpgw->db->query("SELECT a.ab_id,a.ab_owner,a.ab_firstname,a.ab_lastname,"
                        . "a.ab_email,a.ab_wphone,c.company_name "
                        . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
                        . "AND $filtermethod AND (a.ab_lastname like '"
                        . "%$query%' OR a.ab_firstname like '%$query%' OR a.ab_email like '%$query%' OR "
                        . "a.ab_street like '%$query%' OR a.ab_city like '%$query%' OR a.ab_state "
                        . "like '%$query%' OR a.ab_zip like '%$query%' OR a.ab_notes like "
                        . "'%$query%' OR c.company_name like '%$query%') $ordermethod limit $limit");
     } else {
       $phpgw->db->query("SELECT ab_id,ab_owner,ab_firstname,ab_lastname,"
                       . "ab_email,ab_wphone,ab_company "
                       . "from addressbook "
                       . "WHERE $filtermethod AND (ab_lastname like '"
                       . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
                       . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
                       . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
                       . "'%$query%' OR ab_company like '%$query%') $ordermethod limit $limit");
    }
  } else {
    if ($phpgw_info["apps"]["timetrack"]["enabled"]){
       $phpgw->db->query("SELECT a.ab_id,a.ab_owner,a.ab_firstname,a.ab_lastname,"
                       . "a.ab_email,a.ab_wphone,c.company_name "
                       . "from addressbook as a, customers as c where a.ab_company_id = c.company_id "
                       . "AND $filtermethod $ordermethod limit $limit");
    } else {
       $phpgw->db->query("SELECT * from addressbook WHERE $filtermethod $ordermethod limit $limit");
    }
  }		// else $query

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
    echo '<tr bgcolor="#' . $tr_color . '">';
    
    $ab_id = $phpgw->db->f("ab_id");
    
    while ($column = each($columns_to_display)) {
       if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
          if ($column[0] == "company") {
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
          echo '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
             . '<a href="' . $field . '" target="_new">' . $field. '</a>&nbsp;</font></td>';
       } else if ($column[0] == "email") {
          if ($phpgw_info["user"]["apps"]["email"]) {
             echo '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
                . '<a href="' . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/email/compose.php",
                                            "to=" . urlencode($field)) . '" target="_top">' . $field . '</a>&nbsp;</font></td>';
          } else {
             echo '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
                . '<a href="mailto:' . $field . '" target="_top">' . $field. '</a>&nbsp;</font></td>';
          }
       } else {
          echo '<td valign="top"><font face="' . $phpgw_info["theme"]["font"] . '" size="2">'
             . $field . '&nbsp;</font></td>';
       }
       
    }
    reset($columns_to_display);		// If we don't reset it, our inside while won't loop
    ?>
    <td valign="top" width="3%">
 	<font face="<?php echo $phpgw_info["theme"]["font"]; ?>" size="2">
      <a href="<?php echo $phpgw->link("view.php","ab_id=$ab_id&start=$start&order=$order&filter="
								 . "$filter&query=$query&sort=$sort");
	  ?>"> <?php echo lang("View"); ?> </a>
     </font>
    </td>
    <td valign="top" width="5%">
     <font face="<?php echo $phpgw_info["theme"]["font"]; ?>" size="2">
      <?php echo $phpgw->common->check_owner($phpgw->db->f("ab_owner"),"edit.php",lang("edit"),"ab_id=" . $phpgw->db->f("ab_id")); ?>
     </font>
    </td>
   </tr>
   <?php
  }

?>
  </table>

  <form method="POST" action="<?php echo $phpgw->link("add.php"); ?>">
   <input type="hidden" name="sort" value="<?php echo $sort; ?>">
   <input type="hidden" name="order" value="<?php echo $order; ?>">
   <input type="hidden" name="query" value="<?php echo $query; ?>">
   <input type="hidden" name="start" value="<?php echo $start; ?>">
   <input type="hidden" name="filter" value="<?php echo $filter; ?>">
  <table width="75%" border="0" cellspacing="0" cellpadding="4">
    <tr> 
      <td width="4%"> 
        <div align="right"> 
          <input type="submit" name="Add" value="<?php echo lang("Add"); ?>">
        </div>
      </td>
      <td width="72%">&nbsp;</td>
      <td width="24%">&nbsp;</td>
    </tr>
  </table>
  </form>
</center>

<?php
  $phpgw->common->phpgw_footer();
?>
