<?php
/**
 * EGroupware API - Authentication via SAML or everything supported by SimpleSAMLphp
 *
 * @link https://www.egroupware.org
 * @link https://simplesamlphp.org/docs/stable/
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 */

use EGroupware\Api;

// we have to set session-cookie name used by EGroupware!
ini_set('session.name', 'sessionid');

require_once __DIR__.'/../api/src/autoload.php';

$GLOBALS['egw_info'] = [
	'flags' => [
		//'currentapp' => 'login',	// db connection, no auth
		'noapi' => true,	// no db connection, but autoloader, files_dir MUST be set correct!
	],
	'server' => [
		// default files and temp directories for name based instances (eg. our hosting) or container installation
		'files_dir' => file_exists('/var/lib/egroupware/'.Api\Header\Http::host().'/files') ?
			'/var/lib/egroupware/'.Api\Header\Http::host().'/files' : '/var/lib/egroupware/default/files',
		'temp_dir'  => file_exists('/var/lib/egroupware/'.Api\Header\Http::host().'/tmp') ?
			'/var/lib/egroupware/'.Api\Header\Http::host().'/tmp' : '/tmp',
	],
];
require_once __DIR__.'/../header.inc.php';

Api\Auth\Saml::checkDefaultConfig();