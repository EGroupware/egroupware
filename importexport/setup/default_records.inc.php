<?php
/**
 * eGroupWare - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.bodefinitions.inc.php');
require_once(EGW_INCLUDE_ROOT. '/phpgwapi/inc/class.accounts.inc.php');
require_once(EGW_INCLUDE_ROOT. '/phpgwapi/inc/class.translation.inc.php');

// some globals we need
if ( !is_object($GLOBALS['egw']->accounts) ) $GLOBALS['egw']->accounts = new accounts();
if ( !is_object($GLOBALS['egw']->translation) ) $GLOBALS['egw']->translation = new translation();
if ( !is_object($GLOBALS['egw']->db)) $GLOBALS['egw']->db = $GLOBALS['egw_setup']->db;

// apps, whose definitions should be installed automatically
// i don't know how to ask setup which apps are / ore are going to be installed.
$appnames = array (
	'addressbook',
);	

foreach ($appnames as $appname) {
	$defdir = EGW_INCLUDE_ROOT. "/$appname/importexport/definitions";
	if(!is_dir($defdir)) continue;
	$d = dir($defdir);
	
	// step through each file in appdir
	while (false !== ($entry = $d->read())) {
		$file = $defdir. '/'. $entry;
		list( $filename, $extension) = explode('.',$entry);
		if ( $extension != 'xml' ) continue;
		bodefinitions::import( $file );			
	}
}
