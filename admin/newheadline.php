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
  if (! $submit) {
     ?>
       <form method="POST" action="<?php echo $phpgw->link("newheadline.php"); ?>">
        <center>
         <table border=0 width=65%>
           <tr>
             <td><?php echo lang_admin("Display"); ?></td>
             <td><input name="n_display"></td>
           </tr>
           <tr>
             <td><?php echo lang_admin("Base URL"); ?></td>
             <td><input name="n_base_url"></td>
           </tr>
           <tr>
             <td><?php echo lang_admin("News File"); ?></td>
             <td><input name="n_newsfile"></td>
           </tr>
           <tr>
             <td><?php echo lang_admin("Minutes between Reloads"); ?></td>
             <td><input name="n_cachetime"></td>
           </tr>
           <tr>
             <td><?php echo lang_admin("Listings Displayed"); ?></td>
             <td><input name="n_listings"></td>
           </tr>
           <tr>
             <td><?php echo lang_admin("News Type"); ?></td>
             <td>
<?
	 $news_type = array('rdf','fm','lt','sf','rdf-chan');
         for($i=0;$i<count($news_type);$i++) {
           echo "<input type=\"radio\" name=\"n_newstype\" value=\""
                . $news_type[$i] . "\">&nbsp;$news_type[$i]<br>";
         }
?>
             </td>
           </tr>
           <tr>
             <td colspan=2>
              <input type="submit" name="submit" value="<?php echo lang_common("submit"); ?>">
             </td>
           </tr>
         </table>
        </center>
       </form>
     <?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");

  } else {
     if (! $n_display)
        $error = "<br>" . lang_admin("You must enter a display");

     if (! $n_base_url)
        $error = "<br>" . lang_admin("You must enter a base url");

     if (! $n_newsfile)
        $error = "<br>" . lang_admin("You must enter a news url");

     if (! $n_cachetime)
        $error = "<br>" . lang_admin("You must enter the number of minutes between reload");

     if (! $n_listings)
        $error = "<br>" . lang_admin("You must enter the number of listings display");

     if (! $n_newstype)
        $error = "<br>" . lang_admin("You must select a file type");

     if ($error) 
        exit;

     $phpgw->db->query("select display from news_site where base_url='"
		 . addslashes(strtolower($n_base_url)) . "' and newsfile='"
		 . addslashes(strtolower($n_newsfile)) . "'");
     $phpgw->db->next_record();
     if ($phpgw->db->f("display")) {
        navigation_bar();
        echo "<center>" . lang_admin("That site has already been entered") . "</center>";
        exit;
     }

     $phpgw->db->lock("news_site");

     $sql = "insert into news_site (display,base_url,newsfile,"
	  . "lastread,newstype,cachetime,listings) "
	  . "values ('" . addslashes($n_display) . "','"
	  . addslashes(strtolower($n_base_url)) . "','" 
	  . addslashes(strtolower($n_newsfile)) . "',0,'"
	  . $n_newstype . "',$n_cachetime,$n_listings)";

     $phpgw->db->query($sql);

     $phpgw->db->unlock();

     Header("Location: " . $phpgw->link($directorys["webserver_url"]
	  . "/admin/headlines.php", "cd=28"));
  }
?>
