<?php
	if (empty($phpgw_info["server"]["account_repository"]))
	{
		if (!empty($phpgw_info["server"]["auth_type"]))
		{
			$phpgw_info["server"]["account_repository"] = $phpgw_info["server"]["auth_type"];
		}
		else
		{
			$phpgw_info["server"]["account_repository"] = "sql";
		}
	}
	include(PHPGW_API_INC."/class.accounts_".$phpgw_info["server"]["account_repository"].".inc.php");
	include(PHPGW_API_INC."/class.accounts_shared.inc.php");
?>
