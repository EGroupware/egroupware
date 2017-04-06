<?php

/**
 * Test Etemplate main file
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use EGroupware\Api;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * Test the main class of Etemplate
 *
 * Etemplate Widget base classscans the apps for widgets, which needs the app
 * list, pulled from the database, so we need to log in.
 */
class EtemplateTest extends \EGroupware\Api\LoggedInTest {

	/**
	 * Test reading xml files
	 *
	 * This really just tests that the files can be found.  Testing the parsing
	 * is done in the Template test.
	 */
	public function testRead()
	{
		$etemplate = new Etemplate();

		// Test missing template fails
		$this->assertEquals(false, $etemplate->read('totally invalid'), 'Reading invalid template');

		// Templates must be in the correct templates directory - use one from API
		$this->assertEquals(true, $etemplate->read('api.prompt'));
	}

	public function testSetDOMId()
	{

		$etemplate = new Etemplate();
/*
		Etemplate::$response = $this->getMockBuilder(Etemplate\Api\Json\Response)
			->disableOriginalConstructor()
			->setMethods(['generic'])
			->getMock($etemplate);
		Etemplate::$response->expects($this->once())
				->method('generic');
		*/
		$etemplate->set_dom_id('test_id');

		$this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
	}

	public function testArrayMerge()
	{
		$this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
	}

	public function testExec()
	{
		$this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
	}
}
