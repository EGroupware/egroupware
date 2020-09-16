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
	'account_lid' => 'ralf',
];

var_dump(Vfs\StreamWrapper::mount());

var_dump(file_put_contents('vfs://default/home/ralf/test.txt', "Just a test ;)\n"));
var_dump("Vfs::proppatch('/home/ralf/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])=".array2string(Vfs::proppatch('/home/ralf/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])),
	"Vfs::propfind('/home/ralf/test.txt')=".json_encode(Vfs::propfind('/home/ralf/test.txt'), JSON_UNESCAPED_SLASHES));

var_dump($f=fopen('vfs://default/home/ralf/test.txt', 'r'), fread($f, 100), fclose($f));

Vfs::$is_root = true;
var_dump(file_put_contents('vfs://default/home/birgit/test.txt', "Just a test ;)\n"));
var_dump("Vfs::proppatch('/home/birgit/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])=".array2string(Vfs::proppatch('/home/birgit/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])),
	"Vfs::propfind('/home/birgit/test.txt')=".json_encode(Vfs::propfind('/home/birgit/test.txt'), JSON_UNESCAPED_SLASHES));
var_dump(Vfs\StreamWrapper::mount('vfs://birgit@default/home/birgit', '/home/ralf/birgit'));
Vfs::$is_root = false;

var_dump(Vfs\StreamWrapper::mount());

var_dump("Vfs::resolve_url('/home/ralf/birgit/test.txt')=".Vfs::resolve_url('/home/ralf/birgit/test.txt'));
var_dump("Vfs::url_stat('/home/ralf/birgit/test.txt')=".json_encode(Vfs::stat('/home/ralf/birgit/test.txt'), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::is_readable('/home/ralf/birgit/test.txt')=".json_encode(Vfs::is_readable('/home/ralf/birgit/test.txt')));
var_dump("fopen('vfs://default/home/ralf/birgit/test.txt', 'r')", $f=fopen('vfs://default/home/ralf/birgit/test.txt', 'r'), fread($f, 100), fclose($f));
var_dump("Vfs::propfind('/home/ralf/birgit/test.txt')", Vfs::propfind('/home/ralf/birgit/test.txt'));
var_dump("Vfs::proppatch('/home/ralf/birgit/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something else']])=".array2string(Vfs::proppatch('/home/ralf/birgit/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something else']])),
	"Vfs::propfind('/home/ralf/birgit/test.txt')=".json_encode(Vfs::propfind('/home/ralf/birgit/test.txt'), JSON_UNESCAPED_SLASHES));

var_dump("Vfs::url_stat('/home/ralf/birgit/test.txt')=".json_encode(Vfs::stat('/home/ralf/birgit/test-dir')));
var_dump("Vfs::mkdir('/home/ralf/birgit/test-dir')=".json_encode(Vfs::mkdir('/home/ralf/birgit/test-dir')));
var_dump("Vfs::url_stat('/home/ralf/birgit/test.txt')=".json_encode(Vfs::stat('/home/ralf/birgit/test-dir'), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::rmdir('/home/ralf/birgit/test-dir')=".json_encode(Vfs::rmdir('/home/ralf/birgit/test-dir')));
var_dump("Vfs::url_stat('/home/ralf/birgit/test.txt')=".json_encode(Vfs::stat('/home/ralf/birgit/test-dir')));

var_dump("Vfs::scandir('/home/ralf/birgit')=".json_encode(Vfs::scandir('/home/ralf/birgit'), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::remove('/home/ralf/birgit/test.txt')=".json_encode(Vfs::remove('/home/ralf/birgit/test.txt')));
var_dump("Vfs::scandir('/home/ralf/birgit')=".json_encode(Vfs::scandir('/home/ralf/birgit'), JSON_UNESCAPED_SLASHES));
