<?php
	if (!$phpgw_info["server"]["contacts_repository"]) { $phpgw_info["server"]["contacts_repository"] = "sql"; }
	include(PHPGW_INCLUDE_ROOT."/addressbook/inc/class.contacts_".$phpgw_info["server"]["contacts_repository"].".inc.php");
	include(PHPGW_INCLUDE_ROOT."/addressbook/inc/class.contacts_shared.inc.php");
?>
