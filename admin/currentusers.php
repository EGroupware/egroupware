<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");
  if (! $start)
     $start = 0;

  $limit =$phpgw->nextmatchs->sql_limit($start);
  $phpgw->db->query("select count(*) from sessions");
  $phpgw->db->next_record();

  $total = $phpgw->db->f(0);
  $limit = $phpgw->nextmatchs->sql_limit($start);
?>
<center>
<?php echo lang_admin("List of current users"); ?>:
<table border="0" width="50%">
<tr bgcolor="<?php echo $phpgw_info["theme"][bg_color]; ?>">
   <?php echo $phpgw->nextmatchs->left("currentusers.php",$start,$total);
   ?>
   <td>&nbsp;</td>
   <?php echo $phpgw->nextmatchs->right("currentusers.php",$start,$total);
   ?>
 </tr> 

 <tr bgcolor="<?php echo $phpgw_info["theme"][th_bg]; ?>">
  <?php // This will pass unneeded vars, not a big deal ?>
  <td><?php echo $phpgw->nextmatchs->show_sort_order($sort,"loginid",$order,"currentusers.php",
				 lang_admin("LoginID")); ?></td>
  <td><?php echo $phpgw->nextmatchs->show_sort_order($sort,"ip",$order,"currentusers.php",
				 lang_admin("IP")); ?></td>
  <td><?php echo $phpgw->nextmatchs->show_sort_order($sort,"logintime",$order,"currentusers.php",
				 lang_admin("Login Time"));?></td>
  <td><?php echo $phpgw->nextmatchs->show_sort_order($sort,"dla",$order,"currentusers.php",
				 lang_admin("idle")); ?></td>
  <td><?php echo lang_admin("Kill"); ?></td>
 </tr>
<?php
   if ($order)
      $ordermethod = "order by $order $sort";
   else
      $ordermethod = "order by dla asc";

   $phpgw->db->query("select * from sessions $ordermethod limit $limit");

   while ($phpgw->db->next_record()) {
     $tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);

     ?>
      <tr bgcolor="<?php echo $tr_color; ?>">
        <td><?php echo $phpgw->db->f("loginid"); ?></td>
        <td><?php echo $phpgw->db->f("ip"); ?></td>
        <td><?php echo $phpgw->preferences->show_date($phpgw->db->f("logintime")); ?></td>
        <td><?php echo gmdate("G:i:s",(time() - $phpgw->db->f("dla")) ); ?></td>
        <td><?php if ($phpgw->db->f("sessionid") != $phpgw->session->id) {
                     echo "<a href=\"" . $phpgw->link("killsession.php","ksession="
				. $phpgw->db->f("sessionid") . "&kill=true\">"
				. lang_admin("Kill"));
                  } else {
			 echo "&nbsp;";
                  }
	   ?></a></td>
      </tr>
     <?php
   }

?>
</center>
</table>

<?php include($phpgw_info["server"]["api_dir"] . "/footer.inc.php"); ?>
