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

if (!extension_loaded('dom'))
{
	echo "<p>Required PHP DOM extension missing, installation of ImportExport definitions aborted.</p>\n";
	return;	// otherwise we mess up the whole eGroupware install process
}
require_once(EGW_INCLUDE_ROOT. '/importexport/inc/class.importexport_definitions_bo.inc.php');

// This sets up $GLOBALS['egw']->accounts and $GLOBALS['egw']->db
$GLOBALS['egw_setup']->setup_account_object();

// step through every source code intstalled app
$egwdir = dir(EGW_INCLUDE_ROOT);
while (false !== ($appdir = $egwdir->read())) {
	$defdir = EGW_INCLUDE_ROOT. "/$appdir/setup/";
	if ( !is_dir( $defdir ) ) continue;

		// step through each file in defdir of app
		$d = dir($defdir);
		while (false !== ($entry = $d->read())) {
			$file = $defdir. '/'. $entry;
			list( $filename, $extension) = explode('.',$entry);
			if ( $extension != 'xml' ) continue;
			importexport_definitions_bo::import( $file );
		}

		// Set as default definition for the app, if there is no site default yet
		if(!$GLOBALS['egw']->preferences->default[$appdir]['nextmatch-export-definition']) {
			$bo = new importexport_definitions_bo(array('name' => "export-$appdir"));
			$definitions = $bo->get_definitions();
			if($definitions[0]) {
				$definition = $definition[0];
				$GLOBALS['egw']->preferences->add($appdir, 'nextmatch-export-definition', "export-$appdir", 'default');
				$GLOBALS['egw']->preferences->save_repository(true, 'default');
			}
		}
}
