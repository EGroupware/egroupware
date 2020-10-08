<?php

use EGroupware\Api\Vfs;

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

$schema = 'stylite.versioning';	//'sqlfs';
Vfs::$is_root = true;
Vfs::mount("$schema://default/home", '/home', false, false);
Vfs::$is_root = false;
var_dump(Vfs::mount());
var_dump(Vfs::scandir('/home'));
var_dump(Vfs::find('/home', ['maxdepth' => 1]));
//var_dump(Vfs::scandir("/home/$sysop"));

Vfs::$is_root = true;
Vfs::mount('filesystem://default/var/lib/egroupware', "/home/$other/something", false, false);
Vfs::$is_root = false;
var_dump(Vfs::stat("/home/$other/something"));

var_dump(file_put_contents("vfs://default/home/$sysop/test.txt", "Just a test ;)\n"));
var_dump("Vfs::proppatch('/home/$sysop/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])=".array2string(Vfs::proppatch("/home/$sysop/test.txt", [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])),
	"Vfs::propfind('/home/$sysop/test.txt')=".json_encode(Vfs::propfind("/home/$sysop/test.txt"), JSON_UNESCAPED_SLASHES));

var_dump($f=fopen("vfs://default/home/$sysop/test.txt", 'r'), fread($f, 100), fclose($f));
//var_dump(Vfs::find("/home/$sysop", ['maxdepth' => 1]));

Vfs::$is_root = true;
var_dump(file_put_contents("vfs://default/home/$other/test.txt", "Just a test ;)\n"));
var_dump("Vfs::proppatch('/home/$other/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])=".array2string(Vfs::proppatch("/home/$other/test.txt", [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])),
	"Vfs::propfind('/home/$other/test.txt')=".json_encode(Vfs::propfind("/home/$other/test.txt"), JSON_UNESCAPED_SLASHES));
var_dump(Vfs::mount("vfs://$other@default/home/$other", "/home/$sysop/$other", false, false));
Vfs::$is_root = false;

var_dump(Vfs::mount());

var_dump("Vfs::resolve_url('/home/$sysop/$other/test.txt')=".Vfs::resolve_url("/home/$sysop/$other/test.txt"));
var_dump("Vfs::url_stat('/home/$sysop/$other/test.txt')=".json_encode(Vfs::stat("/home/$sysop/$other/test.txt"), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::is_readable('/home/$sysop/$other/test.txt')=".json_encode(Vfs::is_readable("/home/$sysop/$other/test.txt")));
var_dump("fopen('vfs://default/home/$sysop/$other/test.txt', 'r')", $f=fopen("vfs://default/home/$sysop/$other/test.txt", 'r'), fread($f, 100), fclose($f));
var_dump("Vfs::propfind('/home/$sysop/$other/test.txt')", Vfs::propfind("/home/$sysop/$other/test.txt"));
var_dump("Vfs::proppatch('/home/$sysop/$other/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something else']])=".array2string(Vfs::proppatch("/home/$sysop/$other/test.txt", [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something else']])),
	"Vfs::propfind('/home/$sysop/$other/test.txt')=".json_encode(Vfs::propfind("/home/$sysop/$other/test.txt"), JSON_UNESCAPED_SLASHES));
	

var_dump("Vfs::url_stat('/home/$sysop/$other/test-dir')=".json_encode(Vfs::stat("/home/$sysop/$other/test-dir")));
var_dump("Vfs::mkdir('/home/$sysop/$other/test-dir')=".json_encode(Vfs::mkdir("/home/$sysop/$other/test-dir")));
var_dump("Vfs::url_stat('/home/$sysop/$other/test-dir')=".json_encode(Vfs::stat("/home/$sysop/$other/test-dir"), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::rmdir('/home/$sysop/$other/test-dir')=".json_encode(Vfs::rmdir("/home/$sysop/$other/test-dir")));
var_dump("Vfs::url_stat('/home/$sysop/$other/test-dir')=".json_encode(Vfs::stat("/home/$sysop/$other/test-dir")));

var_dump("Vfs::scandir('/home/$sysop/$other')=".json_encode(Vfs::scandir("/home/$sysop/$other"), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::remove('/home/$sysop/$other/test.txt')=".json_encode(Vfs::remove("/home/$sysop/$other/test.txt"), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::scandir('/home/$sysop/$other')=".json_encode(Vfs::scandir("/home/$sysop/$other"), JSON_UNESCAPED_SLASHES));
