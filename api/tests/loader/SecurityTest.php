<?php

/**
 * Tests for api/src/loader/security.php
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
namespace EGroupware\Api;

require_once realpath(__DIR__.'/../../src/loader/common.php');	// autoloader & check_load_extension
//
// We're testing security.php
require_once realpath(__DIR__.'/../../src/loader/security.php');

use PHPUnit\Framework\TestCase as TestCase;


class SecurityTest extends TestCase {

	/**
	 * Test safe unserialization
	 *
	 * @param String $str Serialized string to be checked
	 * @param boolean $result If we expect the string to fail or not
	 *
	 */
	#[\PHPUnit\Framework\Attributes\RequiresPhp('>= 7.0.0')]
	#[\PHPUnit\Framework\Attributes\DataProvider('unserializeProvider')]
	public function testObjectsCannotBeUnserializedInPhp7($str, $result)
	{
		$r=@php_safe_unserialize($str);

		if((bool)($r) !== $result)
		{
			if (!$result)
			{
				$matches = null;
				if (preg_match_all('/([^ ]+) Object\(/', array2string($r), $matches))
				{
					foreach($matches[1] as $class)
					{
						if (!preg_match('/^__PHP_Incomplete_Class(#\d+)?$/', $class))
						{
							$this->fail($str);
						}
					}
				}
			}
			else
			{
				$this->fail("false positive: $str");
			}
		}
		// Avoid this test getting reported as no assertions, we do the testing
		// in the foreach loop
		$this->assertTrue(true);
	}

	/**
	 * Data set for unserialize test
	 */
	public static function unserializeProvider()
	{
		$tests = array(
			// Serialized string, expected result
			// things unsafe to unserialize
			Array("O:34:\"Horde_Kolab_Server_Decorator_Clean\":2:{s:43:\"\x00Horde_Kolab_Server_Decorator_Clean\x00_server\";", false),
			Array("O:20:\"Horde_Prefs_Identity\":2:{s:9:\"\x00*\x00_prefs\";O:11:\"Horde_Prefs\":2:{s:8:\"\x00*\x00_opts\";a:1:{s:12:\"sizecallback\";", false),
			Array("a:2:{i:0;O:12:\"Horde_Config\":1:{s:13:\"\x00*\x00_oldConfig\";s:#{php_injection.length}:\"#{php_injection}\";}i:1;s:13:\"readXMLConfig\";}}", false),
			Array('a:6:{i:0;i:0;i:1;d:2;i:2;s:4:"ABCD";i:3;r:3;i:4;O:8:"my_Class":2:{s:1:"a";r:6;s:1:"b";N;};i:5;C:16:"SplObjectStorage":14:{x:i:0;m:a:0:{}}', false),
			Array(serialize(new \stdClass()), false),
			Array(serialize(array(new \stdClass(), new \SplObjectStorage())), false),
			// string content, safe to unserialize
			Array(serialize('O:8:"stdClass"'), true),
			Array(serialize('C:16:"SplObjectStorage"'), true),
			Array(serialize(array('a', 'O:8:"stdClass"', 'b', 'C:16:"SplObjectStorage"')), true)
		);
		if (PHP_VERSION >= 7)
		{
			// Fails our php<7 regular expression, because it has correct delimiter (^|;|{) in front of pattern :-(
			$tests[] = Array(serialize('O:8:"stdClass";C:16:"SplObjectStorage"'), true);
		}
		return $tests;
	}
}
