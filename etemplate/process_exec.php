<?php
/**
 * eGroupWare - eTemplates - Editor
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package etemplate
 * @copyright (c) 2002-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

list($app) = explode('.',$_GET['menuaction']);

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> $app,
		'noheader'		=> True,
		'nonavbar'		=> True,
	),
);
include('../header.inc.php');

if($_GET['ajax'])
{
	ExecMethod('etemplate.etemplate_new.process_exec');
}
else
{
	ExecMethod('etemplate.etemplate.process_exec');
}
