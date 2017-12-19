<?php
/**
 * EGroupware - collabeditor - setup
 *
 * @link http://www.egroupware.org
 * @package collabeditor
 */

$setup_info['collabeditor']['name']    = 'collabeditor';
$setup_info['collabeditor']['title']   = 'Collabeditor';
$setup_info['collabeditor']['version'] = '17.1';
$setup_info['collabeditor']['app_order'] = 1;
$setup_info['collabeditor']['enable']  = 2;
$setup_info['collabeditor']['autoinstall'] = false;

$setup_info['collabeditor']['author'] = 'Hadi Nategh';
$setup_info['collabeditor']['maintainer'] = array(
	'name'  => 'EGroupware GmbH',
	'url'   => 'http://www.egroupware.org',
);
$setup_info['collabeditor']['license']  = 'GPL';
$setup_info['collabeditor']['description'] = 'Online document editing with webodf editor';

/* The hooks this app includes, needed for hooks registration */
$setup_info['collabeditor']['hooks']['filemanager-editor-link'] = 'EGroupware\collabeditor\Hooks::getEditorLink';

/* Dependencies for this app to work */
$setup_info['collabeditor']['depends'][] = array(
	'appname' => 'filemanager',
	'versions' => array('17.1')
);
$setup_info['collabeditor']['depends'][] = array(
	'appname' => 'api',
	'versions' => array('17.1')
);