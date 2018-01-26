#!/usr/bin/env php
<?php
/**
 * API - run Timed Asynchron Services for all EGroupware domain/instances
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 */

$path_to_egroupware = realpath(__DIR__.'/..');	//  need to be adapted if this script is moved somewhere else

foreach(file($path_to_egroupware. '/header.inc.php') as $line)
{
	if(preg_match("/GLOBALS\['egw_domain']\['(.*)']/", $line, $matches))
	{
		// -d memory_limit=-1 --> no memory limit
		system(PHP_BINARY. ' -q -d memory_limit=-1 '.$path_to_egroupware.'/api/asyncservices.php '. $matches[1]);
	}
}
