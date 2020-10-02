<?php

use EGroupware\Api;
use EGroupware\Api\Vfs;

$GLOBALS['egw_info'] = [
	'flags' => [
		'currentapp' => 'login',
	],
];
require_once __DIR__.'/header-default.inc.php';
$_SESSION = [];	// reset session, specially cache for links
$egw->session->create($sysop='ralf', '', '', true, false);

$so = new infolog_so();
$so->delete(['info_id' => [1, 2]]);
$infolog_sysop = $so->write(['info_id' => 1, 'info_owner' => 5, 'info_subject' => 'Test-InfoLog Ralf', 'info_type' => 'task'], 0, null, true);
// anonymous user give not grants, has not shared groups with Ralf or sysop
$infolog_anon = $so->write(['info_id' => 2, 'info_owner' => ($anon=Api\Accounts::getInstance()->name2id('anonymous')), 'info_subject' => 'Test-InfoLog Anonymous', 'info_type' => 'task'], 0, null, true);
//var_dump($so->read(['info_id' => $infolog_sysop]), $so->read(['info_id' => $infolog_anon]));
// anonymous user needs infolog run rights for further tests
$acl = new Api\Acl($anon);
$acl->add_repository('infolog', 'run', $anon, 1);

$schema = 'stylite.links';	//'links';
Vfs::$is_root = true;
Vfs::mount("$schema://default/apps", '/apps', false, false);
Vfs::$is_root = false;
var_dump(Vfs::mount());

$infolog_sysop_dir = "/apps/infolog/$infolog_sysop";
$infolog_anon_dir = "/apps/infolog/$infolog_anon";
var_dump(file_put_contents("vfs://default$infolog_sysop_dir/test.txt", "Just a test ;)\n"));
var_dump("Vfs::proppatch('$infolog_sysop_dir/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])=" . array2string(Vfs::proppatch("$infolog_sysop_dir/test.txt", [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])),
	"Vfs::propfind('$infolog_sysop_dir/test.txt')=" . json_encode(Vfs::propfind("$infolog_sysop_dir/test.txt"), JSON_UNESCAPED_SLASHES));

var_dump($f = fopen("vfs://default$infolog_sysop_dir/test.txt", 'r'), fread($f, 100), fclose($f));

Vfs::$is_root = true;
var_dump(file_put_contents("vfs://default$infolog_anon_dir/test.txt", "Just a test ;)\n"));
var_dump("Vfs::proppatch('$infolog_anon_dir/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])=" . array2string(Vfs::proppatch("$infolog_anon_dir/test.txt", [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']])),
	"Vfs::propfind('$infolog_anon_dir/test.txt')=" . json_encode(Vfs::propfind("$infolog_anon_dir/test.txt"), JSON_UNESCAPED_SLASHES));
var_dump(Vfs::mount(/*"$schema://anonymous@default$infolog_anon_dir"*/"vfs://anonymous@default$infolog_anon_dir", $share_dir = "/home/$sysop/anon-infolog", false, false));
Vfs::$is_root = false;

var_dump(Vfs::mount());

var_dump("Vfs::resolve_url('$share_dir/test.txt')=" . Vfs::resolve_url("$share_dir/test.txt"));
var_dump("Vfs::url_stat('$share_dir/test.txt')=" . json_encode(Vfs::stat("$share_dir/test.txt"), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::is_readable('$share_dir/test.txt')=" . json_encode(Vfs::is_readable("$share_dir/test.txt")));
var_dump("fopen('vfs://default$share_dir/test.txt', 'r')", $f = fopen("vfs://default$share_dir/test.txt", 'r'), fread($f, 100), fclose($f));
var_dump("Vfs::propfind('$share_dir/test.txt')", Vfs::propfind("$share_dir/test.txt"));
var_dump("Vfs::proppatch('$share_dir/test.txt', [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something else']])=" . array2string(Vfs::proppatch("$share_dir/test.txt", [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something else']])),
	"Vfs::propfind('$share_dir/test.txt')=" . json_encode(Vfs::propfind("$share_dir/test.txt"), JSON_UNESCAPED_SLASHES));

var_dump("Vfs::url_stat('$share_dir/test-dir')=" . json_encode(Vfs::stat("$share_dir/test-dir")));
var_dump("Vfs::mkdir('$share_dir/test-dir')=" . json_encode(Vfs::mkdir("$share_dir/test-dir")));
var_dump("Vfs::url_stat('$share_dir/test-dir')=" . json_encode(Vfs::stat("$share_dir/test-dir"), JSON_UNESCAPED_SLASHES));
var_dump(file_put_contents("vfs://default$share_dir/test-dir/test.txt", "Just a test ;)\n"));
var_dump("Vfs::url_stat('$share_dir/test-dir/test.txt')=" . json_encode(Vfs::stat("$share_dir/test-dir"), JSON_UNESCAPED_SLASHES));
var_dump(file_get_contents("vfs://default$share_dir/test-dir/test.txt"));
var_dump("Vfs::unlink('$share_dir/test-dir/test.txt')=" . json_encode(Vfs::unlink("$share_dir/test-dir/test.txt")));
var_dump("Vfs::rmdir('$share_dir/test-dir')=" . json_encode(Vfs::rmdir("$share_dir/test-dir")));
var_dump("Vfs::url_stat('$share_dir/test-dir')=" . json_encode(Vfs::stat("$share_dir/test-dir")));

var_dump("Vfs::scandir('$share_dir')=" . json_encode(Vfs::scandir($share_dir), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::remove('$share_dir/test.txt')=" . json_encode(Vfs::remove("$share_dir/test.txt"), JSON_UNESCAPED_SLASHES));
var_dump("Vfs::scandir('$share_dir')=" . json_encode(Vfs::scandir($share_dir), JSON_UNESCAPED_SLASHES));
