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
        $filtermethod = " (ab_owner='" . $phpgw_info["user"]["userid"] ."' OR ab_access='public' "
		            . $phpgw->accounts->sql_search("ab_access") . " ) ";
     }
  } else {
     $filtermethod = " ab_owner='" . $phpgw_info["user"]["userid"] . "' ";
  }

  if ($query) {
     $phpgw->db->query("select count(*) from addressbook where $filtermethod AND (ab_lastname "
			      . "like '%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%"
			      . "' OR ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state like '"
			      . "%$query%' OR ab_zip like '%$query%' OR ab_notes like '%$query%' OR ab_company"
			      . " like '%$query%')");

    $phpgw->db->next_record();

     if ($phpgw->db->f(0) == 1)
        echo "<br>" . lang("your search returned 1 match");
     else
        echo "<br>" . lang("your search returned x matchs",$phpgw->db->f(0));
  } else {
     $phpgw->db->query("select count(*) from addressbook where $filtermethod");
  }

  $phpgw->db->next_record();

  if ($phpgw->db->f(0) > $phpgw_info["user"]["preferences"]["maxmatchs"])
     echo "<br>" . lang("showing x - x of x",($start + 1),
			   ($start + $phpgw_info["user"]["preferences"]["maxmatchs"]),$phpgw->db->f(0));
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
       if ( $phpgw_info["user"]["preferences"]["addressbook_view_company"] == "True" ) {
          echo '<td height="21">';
          echo '<font size="-1" face="Arial, Helvetica, sans-serif">';
          echo $phpgw->nextmatchs->show_sort_order($sort,"ab_company",$order,"index.php",lang("Company Name"));
          echo '</font></td>';
       }
       if ( $phpgw_info["user"]["preferences"]["addressbook_view_lastname"] == "True" ) {
           echo '<td height="21">';
           echo '<font size="-1" face="Arial, Helvetica, sans-serif">';
           echo $phpgw->nextmatchs->show_sort_order($sort,"ab_lastname",$order,"index.php",
                              lang("Last Name"));
           echo '</font></td>';
       }
       if ( $phpgw_info["user"]["preferences"]["addressbook_view_firstname"] == "True" ) {
           echo '<td height="21">';
           echo '<font size="-1" face="Arial, Helvetica, sans-serif">';
           echo $phpgw->nextmatchs->show_sort_order($sort,"ab_firstname",$order,"index.php",
                              lang("First Name"));
           echo '</font></td>';
        }
       if ( $phpgw_info["user"]["preferences"]["addressbook_view_email"] == "True" ) {
           echo '<td height="21">';
           echo '<font size="-1" face="Arial, Helvetica, sans-serif">';
           echo $phpgw->nextmatchs->show_sort_order($sort,"ab_email",$order,"index.php",
                              lang("Email"));
           echo '</font></td>';
       }
       if ( $phpgw_info["user"]["preferences"]["addressbook_view_wphone"] == "True" ) {
           echo '<td height="21">';
           echo '<font size="-1" face="Arial, Helvetica, sans-serif">';
           echo $phpgw->nextmatchs->show_sort_order($sort,"ab_wphone",$order,"index.php",
                              lang("Work Phone"));
           echo '</font></td>';
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
     $phpgw->db->query("SELECT * FROM addressbook WHERE $filtermethod AND (ab_lastname like '"
			      . "%$query%' OR ab_firstname like '%$query%' OR ab_email like '%$query%' OR "
	                . "ab_street like '%$query%' OR ab_city like '%$query%' OR ab_state "
	                . "like '%$query%' OR ab_zip like '%$query%' OR ab_notes like "
	                . "'%$query%' OR ab_company like %$query%') $ordermethod limit $limit");
  } else {
     $phpgw->db->query("SELECT * FROM addressbook WHERE $filtermethod $ordermethod limit $limit");
  }

  while ($phpgw->db->next_record()) {
    $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

    $firstname	= $phpgw->db->f("ab_firstname");
    $lastname 	= $phpgw->db->f("ab_lastname");
    $email     = $phpgw->db->f("ab_email");
    $company   = $phpgw->db->f("ab_company");
    $wphone    = $phpgw->db->f("ab_wphone");
    $ab_id	= $phpgw->db->f("ab_id");

    if($firstname == "") $firstname = "&nbsp;";
    if($lastname  == "") $lastname  = "&nbsp;";
    if($email     == "") $email     = "&nbsp;";
    if($company   == "") $company   = "&nbsp;";
    if($wphone    == "") $wphone    = "&nbsp;";

    ?>
    <?php
     echo '<tr bgcolor="#'.$tr_color.'";>';
     if ( $phpgw_info["user"]["preferences"]["addressbook_view_company"] == 'True' ) {
         echo '<td valign=top>';
         echo '<font face=Arial, Helvetica, sans-serif size=2>';
         echo $company;
         echo '</font></td>';
     };
     if ( $phpgw_info["user"]["preferences"]["addressbook_view_lastname"] == 'True' ) {
         echo '<td valign=top>';
         echo '<font face=Arial, Helvetica, sans-serif size=2>';
         echo $lastname;
         echo '</font></td>';
     };
     if ( $phpgw_info["user"]["preferences"]["addressbook_view_firstname"] == 'True' ) {
         echo '<td valign=top>';
         echo '<font face=Arial, Helvetica, sans-serif size=2>';
         echo $firstname;
         echo '</font></td>';
     };
     if ( $phpgw_info["user"]["preferences"]["addressbook_view_email"] == 'True' ) {
         echo '<td valign=top>';
         echo '<font face=Arial, Helvetica, sans-serif size=2>';
         echo $email;
         echo '</font></td>';
     };
     if ( $phpgw_info["user"]["preferences"]["addressbook_view_wphone"] == 'True' ) {
         echo '<td valign=top>';
         echo '<font face=Arial, Helvetica, sans-serif size=2>';
         echo $wphone;
         echo '</font></td>';
     };
     ?>
       <td valign=top width=3%>
	<font face=Arial, Helvetica, sans-serif size=2>
          <a href="<?php echo $phpgw->link("view.php","ab_id=$ab_id&start=$start&order=$order&filter="
								 . "$filter&query=$query&sort=$sort");
	  ?>"> <?php echo lang("View"); ?> </a>
        </font>
       </td>
       <td valign=top width=5%>
        <font face=Arial, Helvetica, sans-serif size=2>
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
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
