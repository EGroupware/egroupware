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

if (php_sapi_name() !== 'cli') die("This is a commandline ONLY tool!\n");

/**
 * iSchedule command line client, primary for testing and development purpose
 *
 * @link https://tools.ietf.org/html/draft-desruisseaux-ischedule-03 iSchedule draft from 2013-01-22
 */
function usage($err=null)
{
	echo "\nUsage: ".basename(__FILE__).": [options] recipient-email [originator-email]\n\n";
	echo "available options:\n\n";
	echo "\t--url ischedule-url\n";
	echo "\t--component (VEVENT|VFREEBUSY|VTODO) (-|ical-filename)\n";
	echo "\t--method (REQUEST(default)|REPLY|CANCEL|ADD)\n";
	echo "\t--generate-key-pair : generates and stores a new key pair\n";
	echo "\t--public-key : outputs public key\n";
	echo "\t-v|--verbose output posted message too\n";
	echo "\n";
	if ($err) echo "$err\n\n";
	exit;
}

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'login',
	)
);
// set a domain for mserver
$_REQUEST['domain'] = 'ralfsmacbook.local';

// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(dirname(__FILE__));
include($egw_dir.'/header.inc.php');

$args = $_SERVER['argv'];
array_shift($args);
$method = 'REQUEST';
while($args[0][0] == '-')
{
	$option = array_shift($args);
	switch($option)
	{
		case '--url':
			if (count($args) < 2) usage("Missing arguments for '$option'!");
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
			if (count($args) < 2) usage("Missing arguments for '$option'!");
			$method = strtoupper(array_shift($args));
			if (!in_array($method, array('REQUEST','REPLY','CANCEL','ADD')))
			{
				usage ("Invalid method name '$method'!");
			}
			break;

		case '-v':
		case '--verbose':
			$verbose = true;
			break;

		case '--generate-key-pair':
			$GLOBALS['egw_info']['server']['dkim_public_key'] = ischedule_client::generateKeyPair();
			echo "\nKey pair generated\n";
			// fall through
		case '--public-key':
			if (empty($GLOBALS['egw_info']['server']['dkim_public_key'])) die("\nYou need to generate a key pair first!\n\n");
			echo "\nYou need following DNS record:\n";
			$public_key = preg_replace('/([-]+(BEGIN|END) PUBLIC KEY[-]+|\s*)/m', '', $GLOBALS['egw_info']['server']['dkim_public_key']);
			echo "\ncalendar._domainkey IN TXT \"v=DKIM1;k=rsa;h=sha1;s=calendar;t=s;p=$public_key\"\n\n";
			exit;

		default:
			usage("Unknown option '$option'!");
	}
}
if (!count($args) && !($public_key || $generate_key_pair)) usage();

$recipient = array_shift($args);
if ($args) $originator = array_shift($args);

try {
	$client = new ischedule_client($recipient, $url);

	echo "\nUsing iSchedule URL: $client->url\n\n";
	if ($originator) $client->setOriginator($originator);
	if ($component)
	{
		$content_type = 'text/calendar; component='.$component.'; method='.$method;
		$response = $client->post_msg($content, $content_type, $verbose);
		echo $response;
	}
	else
	{
		$client->capabilities();
	}
}
catch(Exception $e) {
	if (!$verbose) echo "\n".($e->getCode() ? $e->getCode().' ' : '').$e->getMessage()."\n\n";
}