<?php
	// include galaxia's configuration tailored to egroupware
	require_once(PHPGW_API_INC . SEP . 'galaxia_workflow/config.egw.inc.php');

	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'ProcessManager' . SEP . 'RoleManager.php');
	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'ProcessManager' . SEP . 'ProcessManager.php');
	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'ProcessManager' . SEP . 'ActivityManager.php');

	class workflow_processmanager extends ProcessManager
	{
		function workflow_processmanager()
		{
			parent::ProcessManager($GLOBALS['phpgw']->ADOdb);
		}
	}
?>
