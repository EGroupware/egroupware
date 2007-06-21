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

// This sets up $GLOBALS['egw']->accounts and $GLOBALS['egw']->db
$GLOBALS['egw_setup']->setup_account_object();

// Fetch translation object
$GLOBALS['egw_setup']->translation->setup_translation_sql();
if ( !is_object($GLOBALS['egw']->translation) ) $GLOBALS['egw']->translation = $GLOBALS['egw_setup']->translation->sql;

// step through every source code intstalled app
$egwdir = dir(EGW_INCLUDE_ROOT);
while (false !== ($appdir = $egwdir->read())) {
	$defdir = EGW_INCLUDE_ROOT. "/$appdir/importexport/definitions";
	if ( !is_dir( $defdir ) ) continue;

		// step through each file in defdir of app
		$d = dir($defdir);
		while (false !== ($entry = $d->read())) {
			$file = $defdir. '/'. $entry;
			list( $filename, $extension) = explode('.',$entry);
			if ( $extension != 'xml' ) continue;
			bodefinitions::import( $file );			
		}
}
