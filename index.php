<?
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

  if (! $sessionid) {
     Header("Location: login.php");
  }

  $phpgw_flags = array("noheader" => True, "nonavbar" => True, "currentapp" => "home");
  include("header.inc.php");
  // Note: I need to add checks to make sure these apps are installed.

  if ($cd=="yes" && $phpgw_info["user"]["preferences"]["default_app"]
      && $phpgw_info["user"]["permissions"][$phpgw_info["user"]["preferences"]["default_app"]]) {
     Header("Location: " . $phpgw->link($phpgw_info["server"]["webserver_url"] . "/"
							  . $phpgw_info["user"]["preferences"]["default_app"]));
     exit;
  }
  $phpgw->common->header();
  $phpgw->common->navbar();


  if ($phpgw_info["user"]["permissions"]["admin"] && $phpgw_info["server"]["checkfornewversion"]) {
     $phpgw->network = new network;
     $phpgw->network->set_addcrlf(False);
     if ($phpgw->network->open_port("phpgroupware.org",80,30)) {
	 $phpgw->network->write_port("GET /currentversion HTTP/1.0\nHOST: www.phpgroupware.org\n\n");
	 while ($line = $phpgw->network->read_port())
	     $lines[] = $line;
	 $phpgw->network->close_port();
     }

     for ($i=0; $i<count($lines); $i++) {
         if (ereg("currentversion",$lines[$i])) {
            $line_found = explode(":",chop($lines[$i]));
         }
     }
     if ($line_found[1] > $phpgw_info["server"]["version"]) {
        echo "<p>There is a new version of phpGroupWare avaiable. <a href=\""
	   . "http://www.phpgroupware.org\">http://www.phpgroupware.org</a>";
     }
  }

  echo '<TABLE border="0">';
?>
 <script langague="JavaScript">
    function opennotifywindow()
    {
      window.open("<?php echo $phpgw->link("notify.php")?>", "phpGroupWare", "width=150,height=25,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=yes");
    }
 </script>

<?php
  //echo '<a href="javascript:opennotifywindow()">Open notify window</a>';

  if ($phpgw_info["user"]["permissions"]["email"]
  && $phpgw_info["user"]["preferences"]["mainscreen_showmail"]) {
    echo "<!-- Mailox info -->\n";

    $mbox = $phpgw->msg->login();
    if (! $mbox) {
      echo "Mail error: can not open connection to mail server";
      exit;
    }

  	$mailbox_status = $phpgw->msg->status($mbox,"{" . $phpgw_info["server"]["mail_server"] . ":" . $phpgw_info["server"]["mail_port"] . "}INBOX",SA_UNSEEN);
    if ($mailbox_status->unseen == 1) {
      echo "<tr><td><A href=\"" . $phpgw->link("email/") . "\"> "
	 . lang_common("You have 1 new message!") . "</A></td></tr>\n";
    }
    if ($mailbox_status->unseen > 1) {
      echo "<tr><td><A href=\"" . $phpgw->link("email/") . "\"> "
	 . lang_common("You have x new messages!",$mailbox_status->unseen) . "</A></td></tr>";
    }
    echo "<!-- Mailox info -->\n";
  }

  if ($phpgw_info["user"]["permissions"]["addressbook"]
  && $phpgw_info["user"]["preferences"]["mainscreen_showbirthdays"]) {
    echo "<!-- Birthday info -->\n";
    $phpgw->db->query("select DISTINCT firstname,lastname from addressbook where "
      . "bday like '" . $phpgw->common->show_date(time(),"n/d")
      . "/%' and (owner='" . $phpgw->session->loginid . "' or access='"
      . "public')");
      while ($phpgw->db->next_record()) {
        echo "<tr><td>" . lang_common("Today is x's birthday!", $phpgw->db->f("firstname") . " "
	  . $phpgw->db->f("lastname")) . "</td></tr>\n";
      }
      $tommorow = $phpgw->common->show_date(mktime(0,0,0,
      $phpgw->common->show_date(time(),"m"),
      $phpgw->common->show_date(time(),"d")+1,
      $phpgw->common->show_date(time(),"Y")),"n/d" );
      $phpgw->db->query("select firstname,lastname from addressbook where "
        . "bday like '$tommorow/%' and (owner='"
        . $phpgw->session->loginid . "' or access='public')");
      while ($phpgw->db->next_record()) {
        echo "<tr><td>" . lang_common("Tommorow is x's birthday.", $phpgw->db->f("firstname") . " "
	  . $phpgw->db->f("lastname")) . "</td></tr>\n";
      }
      echo "<!-- Birthday info -->\n";
  }

  // Reaccuring events have not been added yet and this needs to be updated
  // to handle global public and group events.


  // This is disbaled until I can convert the calendar over
  if ($phpgw_info["user"]["permissions"]["calendar"]
  && $phpgw_info["user"]["preferences"]["mainscreen_showevents"]) {
    echo "<!-- Calendar info -->\n";
    include($phpgw_info["server"]["server_root"] . "/calendar/inc/functions.inc.php");
    $repeated_events = read_repeated_events($phpgw->session->loginid);
    $phpgw->db->query("select count(*) from webcal_entry,webcal_entry_user"
      . " where cal_date='" . $phpgw->common->show_date(time(),"Ymd")
      . "' and (webcal_entry_user.cal_login='" . $phpgw->session->loginid
      . "' and webcal_entry.cal_id = webcal_entry_user.cal_id) and "
      . "(cal_priority='3')");
    $phpgw->db->next_record();
    $check = $phpgw->db->f(0);
    if ($check == 1) { 
        $key = "You have 1 high priority event on your calendar today.";
    }
    if ($check > 1) {
      $key = "You have x high priority events on your calendar today.";
    }
    if ($check > 0) echo "<tr><td>" . lang_common($key,$check) . "</td></tr>";

    echo "<!-- Calendar info -->\n";
  } 


?>
<TR><TD></TD></TR>
</TABLE>

<?php
  include($phpgw_info["server"]["api_dir"] . "/footer.inc.php");
?>

