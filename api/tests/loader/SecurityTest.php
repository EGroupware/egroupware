<?php

/**
 * Tests for XSS
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

	protected function setUp() : void
	{
		// _check_script_tag uses HtmLawed, which calls GLOBALS['egw']->link()
		$GLOBALS['egw'] = $this->getMockBuilder('Egw')
			->disableOriginalConstructor()
			->setMethods(['link', 'setup'])
			->getMock();
	}

	protected function tearDown() : void
	{
		unset($GLOBALS['egw_inset_vars']);

		// Must remember to clear this, or other tests may break
		unset($GLOBALS['egw']);
	}

	/**
	 * Test some strings for bad stuff
	 *
	 * @param String $pattern String to check
	 * @param boolean $should_fail If we expect this string to fail
	 *
	 * @dataProvider patternProvider
	 */
	public function testPatterns($pattern, $should_fail)
	{
		$test = array($pattern);
		unset($GLOBALS['egw_unset_vars']);
		_check_script_tag($test,'test', false);
		$this->assertEquals(isset($GLOBALS['egw_unset_vars']), $should_fail);
	}

	public function patternProvider()
	{
		return array(
			// pattern, true: should fail, false: should not fail
			Array('< script >alert(1)< / script >', true),
			Array('<span onMouseOver ="alert(1)">blah</span>', true),
			Array('<a href=          "JaVascript: alert(1)">Click Me</a>', true),
			// from https://www.acunetix.com/websitesecurity/cross-site-scripting/
			Array('<body onload=alert("XSS")>', true),
			Array('<body background="javascript:alert("XSS")">', true),
			Array('<iframe src=”http://evil.com/xss.html”>', true),
			Array('<input type="image" src="javascript:alert(\'XSS\');">', true),
			Array('<link rel="stylesheet" href="javascript:alert(\'XSS\');">', true),
			Array('<table background="javascript:alert(\'XSS\')">', true),
			Array('<td background="javascript:alert(\'XSS\')">', true),
			Array('<div style="background-image: url(javascript:alert(\'XSS\'))">', true),
			Array('<div style="width: expression(alert(\'XSS\'));">', true),
			Array('<object type="text/x-scriptlet" data="http://hacker.com/xss.html">', true),
			// false positiv tests
			Array('If 1 < 2, what does that mean for description, if 2 > 1.', false),
			Array('If 1 < 2, what does that mean for a script, if 2 > 1.', false),
			Array('<div>Script and Javascript: not evil ;-)', false),
			Array('<span>style=background-color', false),
			Array('<font face="Script MT Bold" size="4"><span style="font-size:16pt;">Hugo Sonstwas</span></font>', false),
			Array('<mathias@stylite.de>', false)
		);
	}

	/**
	 * Test some URLs with bad stuff
	 *
	 * @param String $url
	 * @param Array $vectors
	 *
	 * @dataProvider urlProvider
	 */
	public function testURLs($url, $vectors = FALSE)
	{
		// no all xss attack vectors from http://ha.ckers.org/xssAttacks.xml are relevant here! (needs interpretation)
		if (!$vectors)
		{
			$this->markTestSkipped("Could not download or parse $url with attack vectors");
			return;
		}
		foreach($vectors as $line => $pattern)
		{
			$test = array($pattern);
			_check_script_tag($test, 'line '.(1+$line), false);

			$this->assertTrue(isset($GLOBALS['egw_unset_vars']), $line . ': ' . $pattern);
		}
	}

	public function urlProvider()
	{
		$urls = array(
			// we currently fail 76 of 666 test, thought they seem not to apply to our use case, as we check request data
			'https://gist.github.com/JohannesHoppe/5612274' => file(
				'https://gist.githubusercontent.com/JohannesHoppe/5612274/raw/60016bccbfe894dcd61a6be658a4469e403527de/666_lines_of_XSS_vectors.html'),
			// we currently fail 44 of 140 tests, thought they seem not to apply to our use case, as we check request data
			'https://html5sec.org/' => call_user_func(function() {
				$payloads = $items = null;
				try
				{
					if (!($items_js = file_get_contents('https://html5sec.org/items.js')) ||
						!preg_match_all("|^\s+'data'\s+:\s+'(.*)',$|m", $items_js, $items, PREG_PATTERN_ORDER) ||
						!($payload_js = file_get_contents('https://html5sec.org/payloads.js')) ||
						!preg_match_all("|^\s+'([^']+)'\s+:\s+'(.*)',$|m", $payload_js, $payloads, PREG_PATTERN_ORDER))
					{
						return false;
					}
				}
				catch (Exception $e)
				{
					unset($e);
					return false;
				}
				$replace = array(
					"\\'" => "'",
					'\\\\'=> '\\,',
					'\r'  => "\r",
					'\n'  => "\n",
				);
				foreach($payloads[1] as $n => $from) {
					$replace['%'.$from.'%'] = $payloads[2][$n];
				}
				return array_map(function($item) use ($replace) {
					return strtr($item, $replace);
				}, $items[1]);
			}),
		);

		$test_data = array();

		foreach($urls as $url => $vectors)
		{
			$test_data[] = array(
				$url, $vectors
			);
		}
		return $test_data;
	}

	/**
	 * Test safe unserialization
	 *
	 * @param String $str Serialized string to be checked
	 * @param boolean $result If we expect the string to fail or not
	 *
	 * @dataProvider unserializeProvider
	 * @requires PHP < 7
	 */
	public function testObjectsCannotBeUnserializedInPhp5($str, $result)
	{
		$r=@php_safe_unserialize($str);

		$this->assertSame($result, (bool)$r, 'Save unserialize failed');
	}

	/**
	 * Test safe unserialization
	 *
	 * @param String $str Serialized string to be checked
	 * @param boolean $result If we expect the string to fail or not
	 *
	 * @dataProvider unserializeProvider
	 * @requires PHP 7
	 */
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
	public function unserializeProvider()
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
