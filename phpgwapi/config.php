<?php
/**
 * API: loading translation from from browser
 *
 * Usage: /egroupware/phpgwapi/lang.php?app=infolog&lang=de
 *
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'home',
		'noheader' => true,
		'nocachecontrol' => true,
	)
);

include '../header.inc.php';

// use an etag over config and link-registry
$config = config::clientConfigs();
$link_registry = egw_link::json_registry();
$etag = '"'.md5(serialize($config).$link_registry).'"';

// headers to allow caching
Header('Content-Type: text/javascript; charset=utf-8');
Header('Cache-Control: public, no-transform');
Header('Pragma: cache');
Header('ETag: '.$etag);

// if servers send a If-None-Match header, response with 304 Not Modified, if etag matches
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
{
	header("HTTP/1.1 304 Not Modified");
	common::egw_exit();
}

echo 'egw.set_configs('.json_encode($config).");\n";
echo 'egw.set_link_registry('.$link_registry.");\n";

// Content-Lenght header is important, otherwise browsers dont cache!
Header('Content-Length: '.ob_get_length());