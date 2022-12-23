<?php
/**
 * EGroupware Api: OpenIDConnectClient redirect endpoint
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2013-22 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'api',
		'nonavbar' => true,
		'noheader' => true,
	],
];
require_once __DIR__.'/../header.inc.php';

use EGroupware\Api\Auth\OpenIDConnectClient;

OpenIDConnectClient::process();