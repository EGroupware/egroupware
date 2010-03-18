#!/usr/bin/php -qC
<?php
/**
 * API - run Timed Asynchron Services for all EGroupware domain/instances
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 * @version $Id$
 */

$path_to_egroupware = realpath(dirname(__FILE__).'/../..');	//  need to be adapted if this script is moved somewhere else
$php = isset($_ENV['_']) ? $_ENV['_'] : $_SERVER['_'];

foreach(file($path_to_egroupware. '/header.inc.php') as $line)
{
	if(preg_match("/GLOBALS\['egw_domain']\['(.*)']/", $line, $matches))
	{
		system($php. ' -q -d memory_limit=64M '.$path_to_egroupware.'/phpgwapi/cron/asyncservices.php '. $matches[1]);
	}
}
