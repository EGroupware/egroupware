<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * The file written by Joseph Engo <jengo@phpgroupware.org>                 *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  if (!isset($sessionid) || !$sessionid) {
     Header("Location: login.php");
     exit;
  }

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "home",
                               "enable_network_class" => True, "enable_todo_class" => True,
                               "enable_addressbook_class" => True
                              );
  include("header.inc.php");
  // Note: I need to add checks to make sure these apps are installed.

  if (($phpgw_info["user"]["preferences"]["common"]["useframes"] && $phpgw_info["server"]["useframes"] == "allowed")
     || ($phpgw_info["server"]["useframes"] == "always")) {

     if ($cd == "yes") {

        if (! $navbarframe && ! $framebody) {
           $tpl = new Template($phpgw_info["server"]["template_dir"]);
           $tpl->set_file(array("frames"       => "frames.tpl",
                                "frame_body"   => "frames_body.tpl",
                                "frame_navbar" => "frames_navbar.tpl"
                               ));
   
           $tpl->set_var("navbar_link",$phpgw->link("index.php","navbarframe=True&cd=yes"));
           if ($forward) {
              $tpl->set_var("body_link",$phpgw->link($phpgw_info["server"]["webserver_url"] . $forward));
           } else {
              $tpl->set_var("body_link",$phpgw->link("index.php","framebody=True&cd=yes"));
           }
   
           if ($phpgw_info["user"]["preferences"]["common"]["frame_navbar_location"] == "bottom") {
              $tpl->set_var("frame_size","*,60");
              $tpl->parse("frames_","frame_body",True);
              $tpl->parse("frames_","frame_navbar",True);
           } else {
              $tpl->set_var("frame_size","60,*");        
              $tpl->parse("frames_","frame_navbar",True);
              $tpl->parse("frames_","frame_body",True);
           }
           $tpl->pparse("out","frames");
        }
        if ($navbarframe) {
           $phpgw->common->phpgw_header();
           echo parse_navbar();
        }
    }
  } elseif ($cd=="yes" && $phpgw_info["user"]["preferences"]["common"]["default_app"]
      && $phpgw_info["user"]["apps"][$phpgw_info["user"]["preferences"]["common"]["default_app"]]) {
     $phpgw->redirect($phpgw->link($phpgw_info["server"]["webserver_url"] . "/"
		  . $phpgw_info["user"]["preferences"]["common"]["default_app"] . "/"
		  . ($phpgw_info["user"]["preferences"]["common"]["default_app"]=="calendar"?$phpgw_info["user"]["preferences"]["calendar"]["defaultcalendar"]:"index.php")));
     $phpgw->common->phpgw_exit();
  } else {
     $phpgw->common->phpgw_header();
     echo parse_navbar();  
  }

  

  //$phpgw->hooks->proccess("location","mainscreen");

// $phpgw->preferences->read_preferences("addressbook");
//  $phpgw->preferences->read_preferences("email");
//  $phpgw->preferences->read_preferences("calendar");
//  $phpgw->preferences->read_preferences("stocks");
  
  $phpgw->db->query("select app_version from applications where app_name='admin'",__LINE__,__FILE__);
  $phpgw->db->next_record();

  if ($phpgw_info["server"]["versions"]["phpgwapi"] > $phpgw->db->f("app_version")) {
     echo "<p><b>" . lang("Your are running a newer version of phpGroupWare then your database is setup for")
        . "<br>" . lang("It is recommend that you run setup to upgrade your tables to the current version")
        . "</b>";
  }

  $phpgw->translation->add_app("mainscreen");  
  if (lang("mainscreen_message") != "mainscreen_message*") {
     echo "<center>" . stripslashes(lang("mainscreen_message")) . "</center>";
  }

  if ((isset($phpgw_info["user"]["apps"]["admin"]) &&
       $phpgw_info["user"]["apps"]["admin"]) && 
      (isset($phpgw_info["server"]["checkfornewversion"]) &&
       $phpgw_info["server"]["checkfornewversion"])) {
     $phpgw->network->set_addcrlf(False);
     $lines = $phpgw->network->gethttpsocketfile("http://www.phpgroupware.org/currentversion");
     for ($i=0; $i<count($lines); $i++) {
         if (ereg("currentversion",$lines[$i])) {
            $line_found = explode(":",chop($lines[$i]));
         }
     }
     if ($line_found[1] > $phpgw_info["server"]["versions"]["phpgwapi"]) {
        echo "<p>There is a new version of phpGroupWare avaiable. <a href=\""
	   . "http://www.phpgroupware.org\">http://www.phpgroupware.org</a>";
     }
  }

  echo '<p><table border="0" width="100%" align="center">';
?>
 <script langague="JavaScript">
    function opennotifywindow()
    {
      window.open("<?php echo $phpgw->link("notify.php")?>", "phpGroupWare", "width=150,height=25,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=yes");
    }
 </script>

<?php
  //echo '<a href="javascript:opennotifywindow()">Open notify window</a>';
  
  if ($phpgw_info["user"]["apps"]["stocks"] && $phpgw_info["user"]["preferences"]["stocks"]["enabled"]) {
     include($phpgw_info["server"]["server_root"] . "/stocks/inc/functions.inc.php");
     echo '<tr><td align="right">' . return_quotes($quotes) . '</td></tr>';
  }  
  $phpgw->common->hook("",array("email","calendar"));
  if ($phpgw_info["user"]["apps"]["addressbook"]
  && $phpgw_info["user"]["preferences"]["addressbook"]["mainscreen_showbirthdays"]) {
    echo "<!-- Birthday info -->\n";
    $phpgw->db->query("select ab_firstname,ab_lastname from addressbook where "
                    . "ab_bday like '" . $phpgw->common->show_date(time(),"n/d")
                    . "/%' and (ab_owner='" . $phpgw_info["user"]["account_id"] . "' or ab_access='"
                    . "public')",__LINE__,__FILE__);
      while ($phpgw->db->next_record()) {
        echo "<tr><td>" . lang("Today is x's birthday!", $phpgw->db->f("ab_firstname") . " "
	  . $phpgw->db->f("ab_lastname")) . "</td></tr>\n";
      }
      $tommorow = $phpgw->common->show_date(mktime(0,0,0,
      $phpgw->common->show_date(time(),"m"),
      $phpgw->common->show_date(time(),"d")+1,
      $phpgw->common->show_date(time(),"Y")),"n/d" );
      $phpgw->db->query("select ab_firstname,ab_lastname from addressbook where "
                      . "ab_bday like '$tommorow/%' and (ab_owner='"
                      . $phpgw_info["user"]["account_id"] . "' or ab_access='public')",__LINE__,__FILE__);
      while ($phpgw->db->next_record()) {
        echo "<tr><td>" . lang("Tommorow is x's birthday.", $phpgw->db->f("ab_firstname") . " "
	  . $phpgw->db->f("ab_lastname")) . "</td></tr>\n";
      }
      echo "<!-- Birthday info -->\n";
  }
  //$phpgw->common->debug_phpgw_info();
  //$phpgw->common->debug_list_core_functions();
?>
<TR><TD></TD></TR>
</TABLE>
<?php
  include($phpgw_info["server"]["api_inc"] . "/footer.inc.php");
?>
