<?php

/**
 * Tests for URL-email widget
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

class UrlEmailTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.email_test';

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
	 * These are all valid, most from https://blogs.msdn.microsoft.com/testing123/2009/02/06/email-address-test-cases/
	 *
	 */
	public function validProvider()
	{
		return array(
			array(''),
			array("Ralf Becker <rb@stylite.de>"),
			array("Ralf Becker (Stylite AG) <rb@stylite.de>"),
			array("<rb@stylite.de>"),
			array("rb@stylite.de"),
			array('"Becker), Ralf" <rb@stylite.de>'),
			array("'Becker), Ralf' <rb@stylite.de>"),

			array('umlaut-in@domäin.com'),              // We allow umlauts in domain

			array('email@domain.com'),					// Valid email
			array('firstname.lastname@domain.com'),	    // Email contains dot in the address field
			array('email@subdomain.domain.com'),		// Email contains dot with subdomain
			array('firstname+lastname@domain.com'),     // Plus sign is considered valid character
			array('1234567890@domain.com'),             // Digits in address are valid
			array('email@domain-one.com'),              // Dash in domain name is valid
			array('_______@domain.com'),                // Underscore in the address field is valid
			array('email@domain.name'),                 // .name is valid Top Level Domain name
			array('email@domain.co.jp'),                // Dot in Top Level Domain name also considered valid (use co.jp as example here)
			array('firstname-lastname@domain.com'),     // Dash in address field is valid
			array('x@egroupware.org'),                  // one letter name-part is valid, but failed validation before

			// Supposedly valid, but we don't
		//	array('"email"@domain.com'),                // Quotes around email is considered valid
		//	array('email@123.123.123.123'),             // Domain is valid IP address
		//	array('email@[123.123.123.123]'),           // Square bracket around IP address is considered valid
		);
	}

	/**
	 * Check validation with failing strings
	 *
	 * @param type $content
	 * @param type $validation_errors
	 *
	 * @dataProvider validationProvider
	 */
	public function testValidation($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array('widget' => $content);

		$this->validateRoundTrip($etemplate, Array(), $content, Array(), array_flip(array_keys($content)));
	}

	/*
	 * These are all invalid, most from https://blogs.msdn.microsoft.com/testing123/2009/02/06/email-address-test-cases/
	 */
	public function validationProvider()
	{
		// All these are invalid, and should not give a value back
		return array(
			array("Becker, Ralf <rb@stylite.de>"),    // Contains comma outside " or ' enclosed block
			array("Becker < Ralf <rb@stylite.de>"),   // Contains <    ----------- " ---------------
			array('plainaddress'),                    // Missing @ sign and domain
			array('#@%^%#$@#$@#.com'),                // Garbage
			array('@domain.com'),                     // Missing username
			array('email.domain.com'),                // Missing @
			array('email@domain@domain.com'),         // Two @ sign
			array('me@home.com, me@work.com'),        // Two addresses
			//array('.email@domain.com'),               // Leading dot in address is not allowed
			array('email.@domain.com'),               // Trailing dot in address is not allowed
			//array('email..email@domain.com'),         // Multiple dots
			//array('あいうえお@domain.com'),             // Unicode char as address
			array('email@domain.com (Joe Smith)'),    // Text followed email is not allowed
			array('email@domain'),                    // Missing top level domain (.com/.net/.org/etc)
			array('email@-domain.com'),               // Leading dash in front of domain is invalid
			//array('email@domain.web'),                // .web is not a valid top level domain, but we don't care
			array('email@111.222.333.44444'),         // Invalid IP format
			array('email@domain..com'),               // Multiple dot in the domain portion is invalid
		);
	}
}
