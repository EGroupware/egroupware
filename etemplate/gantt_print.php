<?php

 /*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

 $GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'projectmanager',
		'noheader'		=> True,
		'nonavbar'		=> True
));
include('../header.inc.php');

egw_framework::csp_script_src_attrs(array('https://export.dhtmlx.com/gantt/api.js'));
egw_framework::csp_connect_src_attrs('http://export.dhtmlx.com');

egw_framework::validate_file('/api/js/dhtmlxtree/codebase/dhtmlxcommon.js');
egw_framework::validate_file('/api/js/dhtmlxGantt/codebase/dhtmlxgantt.js');

egw_framework::includeCSS('/api/js/dhtmlxGantt/codebase/dhtmlxgantt.css');

echo $GLOBALS['egw']->framework->header();
?>
<?php
 ?>