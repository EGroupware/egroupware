<?php
	// include galaxia's configuration tailored to egroupware
	require_once(PHPGW_API_INC . SEP . 'galaxia_workflow/config.egw.inc.php');

	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'ProcessManager' . SEP . 'InstanceManager.php');

	class workflow_instancemanager extends InstanceManager
	{
		function workflow_instancemanager()
		{
			parent::InstanceManager($GLOBALS['phpgw']->ADOdb);
		}
	}
?>
