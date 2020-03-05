<?php
/**
 * Run before PHPUnit starts - common stuff for _all_ tests, like getting
 * the autoloader.
 * This file is automatically run once before starting.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// autoloader & check_load_extension
require_once realpath(__DIR__.'/../api/src/loader/common.php');

// backward compatibility with PHPunit 5.7
if (!class_exists('\PHPUnit\Framework\TestCase') && class_exists('\PHPUnit_Framework_TestCase'))
{
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
    class_alias('\PHPUnit_Framework_ExpectationFailedException', '\PHPUnit\Framework\ExpectationFailedException');
}

// Needed to let Cache work
$GLOBALS['egw_info']['server']['temp_dir'] = '/tmp';
$GLOBALS['egw_info']['server']['install_id'] = 'PHPUnit test';
// setting a working session.save_path
if (ini_get('session.save_handler') === 'files' && !is_writable(ini_get('session.save_path')) &&
	is_dir('/tmp') && is_writable('/tmp'))
{
	ini_set('session.save_path','/tmp');	// regular users may have no rights to apache's session dir
}
// set domain from doc/phpunit.xml
if (!isset($_SERVER['HTTP_HOST']) && $GLOBALS['EGW_DOMAIN'] !== 'default')
{
	$_SERVER['HTTP_HOST'] = $GLOBALS['EGW_DOMAIN'];
}

// Symlink api/src/fixtures/apps/* to root
foreach(scandir($path=__DIR__.'/../api/tests/fixtures/apps') as $app)
{
	if (is_dir($path.'/'.$app) && @file_exists($path.'/'.$app.'/setup/setup.inc.php')/* &&
		readlink(__DIR__.'/../'.$app) !== 'api/tests/fixtures/apps/'.$app*/)
	{
		@unlink(__DIR__.'/../'.$app);
		symlink('api/tests/fixtures/apps/'.$app, __DIR__.'/../'.$app);
		// install fixture app
		shell_exec(PHP_BINARY.' '.__DIR__.'/rpm-build/post_install.php --install-update-app '.$app);
	}
}
