<?php

use EGroupware\Api\Vfs;
use Grale\WebDav;

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
	],
];
require_once __DIR__.'/header-default.inc.php';

$GLOBALS['egw_info']['user'] = [
	'account_id' => 5,
	'account_lid' => $sysop='ralf',
];

$other = 'birgit';

WebDav\StreamWrapper::register();

var_dump(file_put_contents("vfs://default/home/$sysop/test.txt", "Just a test ;)\n"));

$base = "webdavs://$sysop:secret@boulder.egroupware.org/egroupware/webdav.php";
var_dump(scandir("$base/home"));

Vfs::$is_root = true;
Vfs::mount("$base/home/$sysop", "/home/$sysop/webdav", false, false);
Vfs::$is_root = false;
var_dump(Vfs::mount());
var_dump(Vfs::scandir("/home/$sysop/webdav"));
var_dump(file_get_contents("vfs://default/home/$sysop/webdav/test.txt"));
var_dump(Vfs::find("/home/$sysop/webdav", ['maxdepth' => 1], true));
//var_dump(Vfs::scandir("/home/$sysop"));

var_dump(scandir($share = "webdavs://pole.egroupware.org/egroupware/share.php/c2nqd6plwiTT8ha6U22sZXsLc7vkVdM3"));
Vfs::$is_root = true;
Vfs::mount("$share", "/home/$sysop/shares/PressRelease-20.1", false, false);
Vfs::$is_root = false;
var_dump(Vfs::find("/home/$sysop/shares/PressRelease-20.1", ['maxdepth' => 1], true));
