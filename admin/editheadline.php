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

  if ($submit) {
     $phpgw_flags = array("noheader" => True, "nonavbar" => True);
  }

  $phpgw_flags["currentapp"] = "admin";
  include("../header.inc.php");
  if (! $con)
     Header("Location: " . $phpgw->link("headlines.php"));

  if ((! $submit) && ($con)) {

     $phpgw->db->query("select * from news_site where con=$con");
     $phpgw->db->next_record();

     ?>

     <form method="POST" action="editheadline.php">
      <?php echo $phpgw->session->hidden_var(); ?>
      <input type="hidden" name="o_con" value="<? echo $con; ?>">

      <center>
       <table border=0 width=65%>
        <tr>
         <td><?php echo lang_admin("Display"); ?></td>
         <td><input name="n_display" value="<?echo $phpgw->db->f("display"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang_admin("Base URL"); ?></td>
         <td><input name="n_base_url" value="<? echo $phpgw->db->f("base_url"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang_admin("News File"); ?></td>
         <td><input name="n_newsfile" value="<? echo $phpgw->db->f("newsfile"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang_admin("Minutes between Reloads"); ?></td>
         <td><input name="n_cachetime" value="<? echo $phpgw->db->f("cachetime"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang_admin("Listings Displayed"); ?></td>
         <td><input name="n_listings" value="<? echo $phpgw->db->f("listings"); ?>"></td>
        </tr>
        <tr>
         <td><?php echo lang_admin("News Type"); ?></td>
         <td>
<?
	 $news_type = array('rdf','fm','lt','sf','rdf-chan');
         for ($i=0;$i<count($news_type);$i++) {
           echo "<input type=\"radio\" name=\"n_newstype\" value=\"" . $news_type[$i] . "\"";
           if($phpgw->db->f("newstype") == $news_type[$i]) echo " checked";
           echo ">&nbsp;$news_type[$i]<br>";
         }
?>
         </td>
        </tr>
        <tr>
         <td colspan=2><input type="submit" name="submit" value="<?php echo lang_common("submit"); ?>"></td>
        </tr>
       </table>
      </center>
     </form>
     <?php
     include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

  } else {
     $phpgw->db->lock("news_site");

     $phpgw->db->query("UPDATE news_site SET display='" . addslashes($n_display) . "', " 
	  	    . "base_url='" . addslashes($n_base_url) . "', "
	  		. "newsfile='" . addslashes($n_newsfile) . "', "
	  		. "lastread=0, newstype='" . $n_newstype . "', "
	  		. "cachetime=$n_cachetime, listings=$n_listings "
	  		. "WHERE con=$o_con");

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"]
	  . "/admin/headlines.php", "cd=27"));
  }
?>
