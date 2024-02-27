#!/usr/bin/env php
<?php
/**
 * VFS - filesystem check via cli
 *
 * @link https://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2024 by Ralf Becker <rb-AT-egroupware.org>
 */

use EGroupware\api\Vfs\Sqlfs;

if (php_sapi_name() !== 'cli')	// security precaution: forbid calling via web
{
	die('<h1>fsck.php must NOT be called as web-page --> exiting !!!</h1>');
}

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
		'no_exception_handler' => 'cli',
	]
];
require(dirname(__DIR__).'/header.inc.php');

$msgs = Sqlfs\Utils::fsck($check_only = ($_SERVER['argv'][1] ?? '') !== '--yes');
echo implode("\n", $msgs)."\n";
if (!$msgs)
{
    echo "fsck found NO problems :)\n";
}
elseif ($check_only)
{
	echo "\nUse --yes to fix problems found\n";
}