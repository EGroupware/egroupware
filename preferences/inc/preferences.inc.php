<?php
{
  $img = "/" . $appname . "/images/" . $appname .".gif";
  if (file_exists($phpgw_info["server"]["server_root"].$img)) {
    $img = $phpgw_info["server"]["webserver_url"].$img;
  } else {
    $img = "/" . $appname . "/images/navbar.gif";
    if (file_exists($phpgw_info["server"]["server_root"].$img)) {
      $img=$phpgw_info["server"]["webserver_url"].$img;
    } else {
    $img = "";
    }
  }
  section_start("Account Preferences",$img);


  // Actual content
  echo "<br><a href=\"" . $phpgw->link("changepassword.php") . "\">"
     . lang("change your password") . "</a>";
  echo "<br><a href=\"" . $phpgw->link("changetheme.php") . "\">"
     . lang("select different theme") . "</a>";
  echo "<br><a href=\"" . $phpgw->link("settings.php") . "\">"
     . lang("change your settings") . "</a>";
  echo "<br><a href=\"" . $phpgw->link("changeprofile.php") . "\">"
     . lang("change your profile") . "</a>";


  section_end(); 
}
?>
