<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"]["currentapp"] = "admin";

  include("../header.inc.php");
  if (! $con)
     Header("Location: ".$phpgw->link("headlines.php"));

  $phpgw->db->query("select * from news_site where con=$con");
  $phpgw->db->next_record();

  ?>
   <center>
   <table border=0 width=65%>
    <tr><td><?php echo lang("Display"); ?></td> <td><?php echo $phpgw->db->f("display"); ?></td></tr>
    <tr><td><?php echo lang("Base Url"); ?></td> <td><?php echo $phpgw->db->f("base_url"); ?></td></tr>
    <tr><td><?php echo lang("News File"); ?></td> <td><?php echo $phpgw->db->f("newsfile"); ?></td></tr>
    <tr><td><?php echo lang("Last Time Read"); ?></td> <td><?php echo $phpgw->common->show_date($phpgw->db->f("lastread")); ?></td></tr>
    <tr><td><?php echo lang("minutes between reloads"); ?></td> <td><?php echo $phpgw->db->f("cachetime"); ?></td></tr>
    <tr><td><?php echo lang("Listings Displayed"); ?></td> <td><?php echo $phpgw->db->f("listings"); ?></td></tr>
    <tr><td><?php echo lang("News Type"); ?></td> <td><?php echo $phpgw->db->f("newstype"); ?></td></tr>
<?php
  $phpgw->db->query("select title,link from news_headlines where site=$con");

  if ($phpgw->db->num_rows() <> 0) {
    echo "<tr><td><br><br><hr></td><td><br><br><hr></td></tr>";
    while($phpgw->db->next_record()) {
?>
      <tr><td><a href="<?php echo $phpgw->db->f("link") ?>" target="_new"><?php echo $phpgw->db->f("title") ?></a></td></tr>
<?php
    }
  }
?>
   </table>
   </center>

<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>
