<?php
{ 
  // This block of code is included by the main Administration page
  // it points to the user, application and other global config
  // pages.
  // $appname is defined in the included file, (=="admin" for this file)
  // $phpgw and $phpgwinfo are also in scope
 
  // Find the icon to display
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

  // Show the header for the section
  section_start("Administration",$img);


  // actual items in this section
  echo "<a href='" . $phpgw->link("accounts.php") . "'>";
  echo lang("User accounts")."</a><br>\n";

  echo "<a href='" . $phpgw->link("groups.php") . "'>";
  echo lang("User groups")."</a><br>\n";

  echo "<p>\n";

  echo "<a href='" . $phpgw->link("applications.php") . "'>";
  echo lang("Applications")."</a><br>\n";

  echo "<p>\n";

  echo "<a href='" . $phpgw->link("currentusers.php") . "'>";
  echo lang("View sessions")."</a><br>\n";

  echo "<a href='" . $phpgw->link("accesslog.php") . "'>";
  echo lang("View Access Log")."</a><br>\n";

  section_end(); 
}
?>
