<?php
	// include galaxia's configuration tailored to egroupware
	require_once(PHPGW_API_INC . SEP . 'galaxia_workflow/config.egw.inc.php');

	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'ProcessManager' . SEP . 'RoleManager.php');

	class workflow_rolemanager extends RoleManager
	{
		function workflow_rolemanager()
		{
			parent::RoleManager($GLOBALS['phpgw']->ADOdb);
		}
	}
?>
