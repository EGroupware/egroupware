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

// we have to set session-cookie name used by EGroupware!
ini_set('session.name', 'sessionid');

$GLOBALS['egw_info'] = [
	'flags' => [
		//'currentapp' => 'login',	// db connection, no auth
		'noapi' => true,	// no db connection, but autoloader, files_dir MUST be set correct!
	],
	'server' => [
		'files_dir' => '/var/lib/egroupware/default/files',
		'temp_dir'  => '/tmp',
	],
];
require_once __DIR__.'/../header.inc.php';

use EGroupware\Api;

Api\Auth\Saml::checkDefaultConfig();