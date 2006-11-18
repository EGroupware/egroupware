<?php
/**
 * eGroupWare editable Templates - Example media database (et_media)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage et_media
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'	=> 'et_media',
		'noheader'	=> True,
		'nonavbar'	=> True,
	),
);
include('../header.inc.php');

$GLOBALS['egw']->redirect_link('/index.php','menuaction=et_media.ui_et_media.edit');
