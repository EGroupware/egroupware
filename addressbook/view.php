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
  include("../header.inc.php");

  if (! $ab_id) {
    Header("Location: " . $phpgw->link("index.php"));
  }

  if ($filter != "private")
 {
     $filtermethod = " or ab_access='public' " . $phpgw->accounts->sql_search("ab_access");
  }

  if ($phpgw_info["apps"]["timetrack"]["enabled"]) {
     $phpgw->db->query("SELECT * FROM addressbook as a, customers as c WHERE a.ab_company_id = c.company_id "
		             . "AND ab_id=$ab_id AND (ab_owner='"
	                 . $phpgw_info["user"]["account_id"] . "' $filtermethod)");
  } else {
     $phpgw->db->query("SELECT * FROM addressbook WHERE ab_id=$ab_id AND (ab_owner='"
                     . $phpgw_info["user"]["account_id"] . "' $filtermethod)");
  }
  $phpgw->db->next_record();

  echo "<p>&nbsp;<b>" . lang("Address book - view") . "</b><hr><p>";

  $i = 0;
  while ($column = each($abc)) {
     if ($phpgw->db->f("ab_" . $column[0])) {
        $columns_to_display[$i]["field_name"]  = $column[1];
        $columns_to_display[$i]["field_value"] = $phpgw->db->f("ab_" . $column[0]);
        $i++;
     }
  }

  echo '<table border="0" cellspacing="2" cellpadding="2" width="80%" align="center">';
  for ($i=0;$i<200;) {		// The $i<200 is only used for a brake
      if (! $columns_to_display[$i]["field_name"]) break;

      $columns_html .= "<tr><td><b>" . lang($columns_to_display[$i]["field_name"]) . "</b>:</td>"
                     . "<td>" . $columns_to_display[$i]["field_value"] . "</td>";

      $i++;

      if (! $columns_to_display[$i]["field_name"]) break;

      $columns_html .= "<td><b>" . lang($columns_to_display[$i]["field_name"]) . "</b>:</td>"
                     . "<td>" . $columns_to_display[$i]["field_value"];

      $i++;
	  $columns_html .= "</td></tr>";
  }
  $owner  = $phpgw->db->f("ab_owner");
  $access = $phpgw->db->f("ab_access");
  
  echo $columns_html . '<tr><td colspan="4">&nbsp;</td></tr>';
  echo "<tr><td><b>" . lang("Record owner") . "</b></td><td>"
     . $phpgw->common->grab_owner_name($phpgw->db->f("ab_owner")) . "</td><td><b>"
     . lang("Record Access") . "</b></td><td>";
     
  if ($access != "private" && $access != "public") {
	 echo lang("Group access") . $phpgw->accounts->convert_string_to_names_access($access);
  } else {
     
     echo $access;
  }
     
  echo "</td></tr></table>";
?>
 <TABLE border="0" cellpadding="1" cellspacing="1">
  <TR> 
   <TD align="left">
    <?php
      echo $phpgw->common->check_owner($owner,"edit.php","Edit");
    ?>
   </TD>
   <TD align="left">
    <a href="<?php echo $phpgw->link("index.php","order=$order&start=$start&filter=$filter&query=$query&sort=$sort"); ?>">Done</a>
   </TD>
  </TR>
 </TABLE>

<?php
  $phpgw->common->phpgw_footer();
?>
