<?php
	// include galaxia's configuration tailored to egroupware
	require_once(PHPGW_API_INC . SEP . 'galaxia_workflow/config.egw.inc.php');

	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'ProcessMonitor' . SEP . 'ProcessMonitor.php');

	class workflow_processmonitor extends ProcessMonitor
	{
		function workflow_processmonitor()
		{
			parent::ProcessMonitor($GLOBALS['phpgw']->ADOdb);
		}
	}
?>
