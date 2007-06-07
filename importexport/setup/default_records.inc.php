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

// apps, whose definitions should be installed automatically
$appnames = array (
	'addressbook',
);	

foreach ($appnames as $appname) {
	$defdir = EGW_INCLUDE_ROOT. "/$appname/importexport/definitions";
	if(!is_dir($defdir)) continue;
	$d = dir($defdir);
	
	// step through each file in appdir
	while (false !== ($entry = $d->read())) {
		$file = $appdir. '/'. $entry;
		list( $filename, $extension) = explode('.',$entry);
		if ( $extension != 'xml' ) continue;
		bodefinitions::import( $file );			
	}
}
