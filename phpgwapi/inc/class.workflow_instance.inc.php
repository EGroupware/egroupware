<?php
	// include galaxia's configuration tailored to egroupware
	require_once(PHPGW_API_INC . SEP . 'galaxia_workflow/config.egw.inc.php');

	require_once(GALAXIA_LIBRARY . SEP . 'src' . SEP . 'API' . SEP . 'Instance.php');

	class workflow_instance extends Instance
	{
		function workflow_Instance()
		{
			parent::Instance($GLOBALS['phpgw']->ADOdb);
		}
	}
?>
