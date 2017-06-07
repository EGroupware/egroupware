#!/usr/bin/env php
<?php
/**
 * EGroupware Test Runner
 *
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

if (php_sapi_name() !== 'cli')	// security precaution: forbit calling as web-page
{
	die('<h1>test-cli.php must NOT be called as web-page --> exiting !!!</h1>');
}

ini_set('apc.enable_cli', true);

require_once dirname(__DIR__).'/api/src/loader/common.php';

$_SERVER['argv'][] = '--verbose';
$_SERVER['argv'][] = 'EgroupwareTestRunner';
$_SERVER['argv'][] = __FILE__;
PHPUnit_TextUI_Command::main();

/**
 * Run all AllTests.php files
 */
class EgroupwareTestRunner
{
	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('EGroupware Test Runner');

		$basedir = dirname(__DIR__);

		// Find all /test/*Test.php files/classes
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basedir)) as $file)
		{
			if ($file->isFile() && preg_match('|/test/[^/]+Test\.php$|', $path=$file->getPathname()))
			{
				// Include the test suite, as it is NOT autoloadable in test directory!
				require_once($path);


				$matches = null;
				// tests of namespaced classes in $app/src/.*/test/$classTest.php
				if (preg_match('|/([^/]+)/src/((.*)/)?test/[^/]+Test\.php$|', $path, $matches))
				{
					$class = 'EGroupware\\'.ucfirst($matches[1]);
					if (!empty($matches[2]))
					{
						foreach(explode('/', $matches[3]) as $name)
						{
							$class .= '\\'.ucfirst($name);
						}
					}
					$class .= '\\'.$file->getBasename('.php');
				}
				// non-namespaced class in $app/test/class.$classTest.inc.php
				elseif (preg_match('|/test/class\.([^./]+)\.inc\.php$|', $path, $matches))
				{
					$class = $matches[1];
				}
				else
				{
					continue;
				}
				echo "$path: $class\n";
				$suite->addTestSuite($class);
			}
		}
		return $suite;
	}
}
