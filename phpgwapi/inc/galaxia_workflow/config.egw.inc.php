<?php

/**
 * Configuration of the Galaxia Workflow Engine for E-Groupware
 */

// Common prefix used for all database table names, e.g. galaxia_
if (!defined('GALAXIA_TABLE_PREFIX')) {
    define('GALAXIA_TABLE_PREFIX', 'egw_wf_');
}

// Directory containing the Galaxia library, e.g. lib/Galaxia
if (!defined('GALAXIA_LIBRARY')) {
    define('GALAXIA_LIBRARY', dirname(__FILE__));
}

// filesystem Operations
$GLOBALS['phpgw']->vfs = createobject('phpgwapi.vfs');

// check if basedir exists
$test=$GLOBALS['phpgw']->vfs->get_real_info(array('string' => '/', 'relatives' => array(RELATIVE_NONE), 'relative' => False));
if($test[mime_type]!='Directory')
{
	die(lang('Base directory does not exist, please ask adminstrator to check the global configuration'));
}

// check if /workflow  exists
$test = @$GLOBALS['phpgw']->vfs->get_real_info(array('string' => '/workflow', 'relatives' => array(RELATIVE_NONE), 'relative' => False));
if($test[mime_type]!='Directory')
{
	// if not, create it
	$GLOBALS['phpgw']->vfs->override_acl = 1;
	$GLOBALS['phpgw']->vfs->mkdir(array(
		'string' => '/workflow',
		'relatives' => array(RELATIVE_NONE)
	));
	$GLOBALS['phpgw']->vfs->override_acl = 0;

	// test one more time
	$test = $GLOBALS['phpgw']->vfs->get_real_info(array('string' => '/workflow', 'relatives' => array(RELATIVE_NONE), 'relative' => False));
	if($test[mime_type]!='Directory')
	{
		die(lang('/workflow directory does not exist and could not be created, please ask adminstrator to check the global configuration'));
	}
}
			
// Directory where the Galaxia processes will be stored, e.g. lib/Galaxia/processes
if (!defined('GALAXIA_PROCESSES'))
{
    // Note: this directory must be writeable by the webserver !
    define('GALAXIA_PROCESSES', $GLOBALS['phpgw']->vfs->basedir.SEP.'workflow');
}

// Directory where a *copy* of the Galaxia activity templates will be stored, e.g. templates
// Define as '' if you don't want to copy templates elsewhere
if (!defined('GALAXIA_TEMPLATES')) {
    // Note: this directory must be writeable by the webserver !
    define('GALAXIA_TEMPLATES', '');
}

// Default header to be added to new activity templates
if (!defined('GALAXIA_TEMPLATE_HEADER')) {
    define('GALAXIA_TEMPLATE_HEADER', '');
}

// File where the ProcessManager logs for Galaxia will be saved, e.g. lib/Galaxia/log/pm.log
// Define as '' if you don't want to use logging
if (!defined('GALAXIA_LOGFILE')) {
    // Note: this file must be writeable by the webserver !
    //define('GALAXIA_LOGFILE', GALAXIA_LIBRARY . '/log/pm.log');
    define('GALAXIA_LOGFILE', '');
}

// Directory containing the GraphViz 'dot' and 'neato' programs, in case
// your webserver can't find them via its PATH environment variable
if (!defined('GRAPHVIZ_BIN_DIR')) {
    define('GRAPHVIZ_BIN_DIR', '');
    //define('GRAPHVIZ_BIN_DIR', 'd:/wintools/ATT/GraphViz/bin');
}

// language function
function tra($msg, $m1='', $m2='', $m3='', $m4='')
{
	return lang($msg, $m1, $m2, $m3, $m4);
}

// Specify how error messages should be shown
if (!function_exists('galaxia_show_error')) {
    function galaxia_show_error($msg)
    {
		die("Error: $msg");
    }
}

// Specify how to execute a non-interactive activity (for use in src/API/Instance.php)
if (!function_exists('galaxia_execute_activity')) {
    function galaxia_execute_activity($activityId = 0, $iid = 0, $auto = 1)
    {
      // Now execute the code for the activity but we are in a method!
      // so just use an fopen with http mode

/*      $parsed = parse_url($_SERVER["REQUEST_URI"]);
      $URI = 'http'.((isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on')) ? 's' : '').'://'.$_SERVER['HTTP_HOST'].$parsed["path"];
      $parts = explode('/',$URI);
      $parts[count($parts)-1] = "index.php?sessionid=".SID."&menuaction=workflow.run_activity&activityId=$activityId&amp;iid=$iid&amp;auto=$auto";
      $URI = implode('/',$parts);

      $fp = fopen($URI,"r");
      $data = '';
      if (!$fp) {
        trigger_error(tra("Fatal error: cannot execute automatic activity $activityId"),E_USER_WARNING);
        die;
      }
      while (!feof($fp)) {
        $data.=fread($fp,8192);
      }
      fclose($fp);

*/
		$run_activity = CreateObject('workflow.run_activity.go');
		$data = $run_activity->go($activityId, $iid, $auto);
    }
}

?>
