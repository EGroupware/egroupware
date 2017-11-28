<?php

/**
 * Test for URL widget
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage etemplate
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class UrlTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.url_test';

	/**
	 * Test the widget's basic functionality - we put data in, it comes back
	 * unchanged.
	 *
	 * @dataProvider validProvider
	 */
	public function testBasic($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Send it around
		$result = $this->mockedRoundTrip($etemplate, array('widget' => $content));

		// Test it
		$this->validateTest($result, array('widget' => $content));
	}

	/**
	 * Test that the widget does not return a value if readonly
	 */
	public function testReadonly()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Exec
		$content = array(
			'widget'            =>	'google.com',
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array('widget' => true));

		// Check
		$this->assertEquals(array(), $result);
	}

	/**
	 * Check validation with failing strings
	 *
	 * @param type $content
	 * @param type $validation_errors
	 *
	 * @dataProvider invalidProvider
	 */
	public function testValidation($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array('widget' => $content);

		$this->validateRoundTrip($etemplate, Array(), $content, Array(), array_flip(array_keys($content)));
	}

	/**
	 * URL samples from https://mathiasbynens.be/demo/url-regex
	 */
	public function validProvider()
	{
		return array(
			array('http://foo.com/blah_blah'),
			array('http://foo.com/blah_blah/'),
			array('http://foo.com/blah_blah_(wikipedia)'),
			array('http://foo.com/blah_blah_(wikipedia)_(again)'),
			array('http://www.example.com/wpstyle/?p=364'),
			array('https://www.example.com/foo/?bar=baz&inga=42&quux'),
			array('http://✪df.ws/123'),
			array('http://userid:password@example.com:8080'),
			array('http://userid:password@example.com:8080/'),
			array('http://userid@example.com'),
			array('http://userid@example.com/'),
			array('http://userid@example.com:8080'),
			array('http://userid@example.com:8080/'),
			array('http://userid:password@example.com'),
			array('http://userid:password@example.com/'),
			array('http://142.42.1.1/'),
			array('http://142.42.1.1:8080/'),
			array('foo.com'),                                         // We prepend http in this case

			// We use filter_var, and it can't handle these
			/*
			array('http://➡.ws/䨹'),
			array('http://⌘.ws'),
			array('http://⌘.ws/'),
			array('http://foo.com/blah_(wikipedia)#cite-1'),
			array('http://foo.com/blah_(wikipedia)_blah#cite-1'),
			array('http://foo.com/unicode_(✪)_in_parens'),
			array('http://foo.com/(something)?after=parens'),
			array('http://☺.damowmow.com/'),
			array('http://code.google.com/events/#&product=browser'),
			array('http://j.mp'),
			array('ftp://foo.bar/baz'),
			array('http://foo.bar/?q=Test%20URL-encoded%20stuff'),
			array('http://مثال.إختبار	'),
			array('http://例子.测试'),
			array('http://उदाहरण.परीक्षा'),
			array("http://-.~_!$&'()*+,;=:%40:80%2f::::::@example.com"),
			array('http://1337.net'),
			array('http://a.b-c.de'),
			array('http://223.255.255.254'),
			 *
			 */
		);
	}

	/**
	 * URL samples from https://mathiasbynens.be/demo/url-regex
	 */
	public function invalidProvider()
	{
		return array(
			array('http://'),
			array('http://.'),
			array('http://..'),
			array('http://../'),
			array('http://?'),
			array('http://??'),
			array('http://??/'),
			array('http://#'),
			array('http://##'),
			array('http://##/'),
			array('http://foo.bar?q=Spaces should be encoded'),
			array('//'),
			array('//a'),
			array('///a'),
			array('///'),
			array('http:///a'),
			// We don't check protocol
			//array('rdar://1234'),
			//array('h://test'),
			//array('ftps://foo.bar/'),
			array('http:// shouldfail.com'),
			array(':// should fail'),
			array('http://foo.bar/foo(bar)baz quux'),
			array('http://-error-.invalid/'),
			array('http://-a.b.co'),
			array('http://a.b-.co'),
			array('http://3628126748'),
			array('http://.www.foo.bar/'),
			array('http://www.foo.bar./'),
			array('http://.www.foo.bar./'),
		);
	}
}
