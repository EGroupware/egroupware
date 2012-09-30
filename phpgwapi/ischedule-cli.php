#!/usr/bin/php
<?php
/**
 * EGroupware: iSchedule command line client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

if (isset($_SERVER['HTTP_HOST'])) die("This is a commandline ONLY tool!\n");

/**
 * iSchedule command line client, primary for testing and development purpose
 *
 * @link https://tools.ietf.org/html/draft-desruisseaux-ischedule-01 iSchedule draft from 2010
 */
function usage($err=null)
{
	echo basename(__FILE__).": [--url ischedule-url] [--component (VEVENT|VFREEBUSY|VTODO) (-|ical-filename)] [--method (REQUEST(default)|RESPONSE)] recipient-email [originator-email]\n\n";
	if ($err) echo "$err\n\n";
	exit;
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'login',
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(dirname(__FILE__));
include($egw_dir.'/header.inc.php');

$args = $_SERVER['argv'];
array_shift($args);
$method = 'REQUEST';
while($args[0][0] == '-')
{
	$option = array_shift($args);
	if (count($args) < 2) usage("Missing arguments for '$option'!".array2string($args));
	switch($option)
	{
		case '--url':
			$url = array_shift($args);
			break;

		case '--component':
			if (count($args) < 3) usage('Missing arguments for --component');
			$component = strtoupper(array_shift($args));
			if (!in_array($component, array('VEVENT','VFREEBUSY','VTODO')))
			{
				usage ("Invalid component name '$component'!");
			}
			if (($filename = array_shift($args)) == '-') $filename = 'php://stdin';
			if (($content = file_get_contents($filename)) === false)
			{
				usage("Could not open '$filename'!");
			}
			break;

		case '--method':
			$method = strtoupper(array_shift($args));
			if (!in_array($method, array('REQUEST','REPLY','CANCEL','ADD')))
			{
				usage ("Invalid method name '$method'!");
			}
			break;

		default:
			usage("Unknown option '$option'!");
	}
}
if (!count($args)) usage();

$recipient = array_shift($args);
if ($args) $originator = array_shift($args);

try {
	$client = new ischedule_client($recipient, $url);
	echo "\nUsing iSchedule URL: $client->url\n\n";
	if ($originator) $client->setOriginator($originator);
	if ($component)
	{
		$content_type = 'text/calendar; component='.$component.'; method='.$method;
		echo $client->post_msg($content, $content_type);
	}
	else
	{
		$client->capabilities();
	}
}
catch(Exception $e) {
	echo "\n".($e->getCode() ? $e->getCode().' ' : '').$e->getMessage()."\n\n";
}