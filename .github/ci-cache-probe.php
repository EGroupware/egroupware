<?php
/**
 * TEMPORARY CI diagnostic - what does this process resolve for its caches?
 *
 * Copied to the document root and requested over HTTP to see the *webserver's* view, and run on
 * the command line to see the test runner's.  They only agree on the tree cache if the instance
 * config says so, and that is what makes Api\Cache::generate_instance_key() cross process.
 */
error_reporting(E_ERROR | E_PARSE);
$GLOBALS['egw_info']['flags'] = ['noapi' => true, 'currentapp' => 'api', 'noheader' => true];
include __DIR__ . '/header.inc.php';

use EGroupware\Api\Cache;

$header = file_get_contents(__DIR__ . '/header.inc.php');
$cfg = [];
foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $k)
{
	preg_match("/'$k'\s*=>\s*'([^']*)'/", $header, $m);
	$cfg[$k] = $m[1] ?? '';
}
$pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']}", $cfg['db_user'], $cfg['db_pass']);
foreach (['install_id', 'temp_dir'] as $k)
{
	$GLOBALS['egw_info']['server'][$k] =
		$pdo->query("SELECT config_value FROM egw_config WHERE config_name='$k'")->fetchColumn();
}
if (PHP_SAPI !== 'cli') header('Content-Type: text/plain');
printf("  sapi=%s apcu=%s temp_dir=%s\n", PHP_SAPI,
	function_exists('apcu_enabled') && apcu_enabled() ? 'yes' : 'no',
	$GLOBALS['egw_info']['server']['temp_dir']);
printf("  cache_provider_tree config = %s\n",
	str_replace("\n", '', var_export($GLOBALS['egw_info']['server']['cache_provider_tree'] ?? null, true)));
printf("  provider INSTANCE=%s TREE=%s\n", Cache::getProvider(Cache::INSTANCE), Cache::getProvider(Cache::TREE));
printf("  instance key = %s\n", Cache::keys(Cache::INSTANCE)[0]);
if (PHP_SAPI === 'cli' && in_array('--bump', $argv ?? []))
{
	printf("  BUMPED to    = %s\n", Cache::generate_instance_key());
}
