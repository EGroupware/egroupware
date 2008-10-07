<?php
/**
 * eGroupWare - EditableTemplates
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-8 by RalfBecker@outdoor-training.de
 * @package etemplate
 * @subpackage setup
 * @version $Id$
 */

$setup_info['etemplate']['name']      = 'etemplate';
$setup_info['etemplate']['version']   = '1.6';
$setup_info['etemplate']['app_order'] = 60;	// just behind the developers-tools
$setup_info['etemplate']['tables']    = array('egw_etemplate');
$setup_info['etemplate']['enable']    = 1;

$setup_info['etemplate']['author'] =
	$setup_info['etemplate']['maintainer'] = array(
	'name'  => 'Ralf Becker',
	'email' => 'ralfbecker@outdoor-training.de'
);
$setup_info['etemplate']['license']   = 'GPL';
$setup_info['etemplate']['description'] =
	'<b>eTemplates</b> are a new widget-based template system for eGroupWare with an
	interactive editor and a database table-editor (creates tables_current.inc.php and
	updates automaticaly tables_update.inc.php).';
$setup_info['etemplate']['note'] =
	'For <b>more information</b> check out the <a href="etemplate/doc/etemplate.html" target="_blank">Tutorial</a>,
	the <a href="etemplate/doc/referenz.html" target="_blank">Referenz Documentation</a>
	or the <a href="http://www.egroupware.org/wiki/etemplate" target="_blank">eTemplate page in our Wiki</a>.';

/* The hooks this app includes, needed for hooks registration */
$setup_info['etemplate']['hooks'][] = 'sidebox_menu';

/* Dependencies for this app to work */
$setup_info['etemplate']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('1.5','1.6','1.7')
);

