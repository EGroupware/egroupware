<?php
	if (!$phpgw_info["server"]["contact_repository"]) { $phpgw_info["server"]["contact_repository"] = "sql"; }
	include(PHPGW_INCLUDE_ROOT."/addressbook/inc/class.contacts_".$phpgw_info["server"]["contact_repository"].".inc.php");
	include(PHPGW_INCLUDE_ROOT."/addressbook/inc/class.contacts_shared.inc.php");
?>
