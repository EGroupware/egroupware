<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $phpgw_info["flags"] = array("noheader" => True, "nonavbar" => True, "currentapp" => "notifywindow");
  include("header.inc.php");
?>
<body bgcolor="<?php echo $phpgw_info["theme"]["bg_color"]; ?>" alink="blue" vlink="blue" link="blue">
<head>

</head>
  <meta http-equiv="Refresh" content="300">
<head>
<?php
  if ($phpgw_info["user"]["permissions"]["email"]) {
    $mbox = $phpgw->msg->login();
    if (! $mbox) {
       echo "Mail error: can not open connection to mail server";
       $phpgw->common->phpgw_exit();
    }

    if (hasmsg == "yes"){
      $mailbox_status = $phpgw->msg->status($mbox,"{" . $phpgw_info["server"]["mail_server"] . ":" . $phpgw_info["server"]["mail_port"] . "}INBOX",SA_UNSEEN);
      if ($mailbox_status->unseen == 1) {
        echo "<tr><td><A href=\"" . $phpgw->link("email/") . "\" target=\"phpGroupWare\"> "
          . lang("You have 1 new message!") . "</A></td></tr>\n";
      }
      if ($mailbox_status->unseen > 1) {
        echo "<tr><td><A href=\"" . $phpgw->link("email/") . "\" target=\"phpGroupWare\"> "
          . lang("You have x new messages!",$mailbox_status->unseen) . "</A></td></tr>";
      }
    } else {
?>
      <script langague="JavaScript">
        function opennotifymsg()
        {
          window.open("<?php echo $phpgw->link("notify.php"), "hasmsg=yes"?>", "phpGW-notify-msg", "width=150,height=150,location=no,menubar=no,directories=no,toolbar=no,scrollbars=no,resizable=no,status=yes");
        }
        function launchphpGroupWare() {
          window.open("phpGroupWare/index.php", "phpGroupWare", "width=800,height=600,location=no,menubar=no,directories=no,toolbar=no,scrollbars=yes,resizable=yes,status=yes");
        }
      </script>
<?php
        $mailbox_status = $phpgw->msg->status($mbox,"{" . $phpgw_info["server"]["mail_server"] . ":" . $phpgw_info["server"]["mail_port"] . "}INBOX",SA_UNSEEN);
        if ($mailbox_status->unseen == 1) {
          echo "<br><a href=\"javascript:opennotifymsg()\">".lang("You have 1 new message!")."</a>";
        }
        if ($mailbox_status->unseen > 1) {
          echo "<br><a href=\"javascript:opennotifymsg()\">".lang("You have x new messages!",$mailbox_status->unseen)."</a>";
        }
        echo "<a href=\"javascript:opennotifymsg()\"><BR><BR>Open phpGroupWare</a>";
    }
  }
?>