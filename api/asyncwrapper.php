#!/usr/bin/env php
<?php
/**
 * API - run Timed Asynchron Services for all EGroupware domain/instances
 *
 * To work around caching-problems (PHP cli uses different shared memory then FPM) we
 * try first to call asyncservices.php via HTTP, before falling back to the old PHP cli call.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 */

$path_to_egroupware = realpath(__DIR__.'/..');	//  need to be adapted if this script is moved somewhere else

// we try http://nginx/ (Docker), http://localhost/ or cli
$methods = ['http://localhost/egroupware/api/asyncservices.php','cli'];
if (gethostbyname('nginx.') !== 'nginx.')
{
	array_unshift($methods, 'http://nginx/egroupware/api/asyncservices.php');
}

foreach(file($path_to_egroupware. '/header.inc.php') as $line)
{
	if(preg_match("/GLOBALS\['egw_domain']\['(.*)']/", $line, $matches))
	{
		foreach($methods as $key => $url)
		{
			if ($url !== 'cli')
			{
				$url .= '?run_by=crontab&domain='.urlencode($matches[1]);
				if (file_get_contents($url) !== false) break;
			}
			else
			{
				// -d memory_limit=-1 --> no memory limit
				system(PHP_BINARY. ' -q -d memory_limit=-1 '.$path_to_egroupware.'/api/asyncservices.php '. $matches[1]);
			}
		}
	}
}
