<?php
  if (!$phpgw_info["server"]["contacts_application"]) { $phpgw_info["server"]["contacts_application"] = "addressbook"; }
  include($phpgw_info["server"]["include_root"]."/".$phpgw_info["server"]["contact_application"]."/inc/class.contacts.inc.php");
?>
