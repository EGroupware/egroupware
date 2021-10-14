<?php

/**
 * Test for contact entry widget, which displays the value of a particular field
 * (specified by field attribute) from a particular contact (specified
 * by value attribute).
 *
 * This is just the server side, client has it's own issues (but no automatic test)
 * see infolog/templates/test/entry_test.xet for a test template to use for
 * manual client side verification.
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2019  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class ContactEntryTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.entry_test_contact';

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();
	}

	protected function tearDown() : void
	{
		// Delete all elements
		foreach($this->elements as $id)
		{
			list($app, $id) = explode(':',$id);

			$bo_class = "{$app}_bo";
			$bo = new $bo_class();
			$bo->delete($id, true, false, true);
		}

		parent::tearDown();

	}

	/**
	 * Test that the correct value is extracted, based on the value attribute,
	 * or if it is missing try the ID.  This goes to contact, rather than
	 * abstract test class.
	 */
	public function testContact()
	{
		// Create a test contact
		$test_contact = $this->make_contact();

		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Content - IDs are important, they should match valid contacts
		$content = array(
			'entry_id'       =>	$GLOBALS['egw_info']['user']['person_id'],
			'info_contact'   => $test_contact
		);

		$result = $this->mockedExec($etemplate, $content);
		$data = array();
		foreach($result as $response)
		{
			if($response['type'] == 'et2_load')
			{
				$data = $response['data']['data'];
				break;
			}
		}

		// Check that the entry was found
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'widget']);
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'entry_id']);
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'info_contact_email']);
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'info_contact']);
		$this->assertEmpty($data['content'][Entry::ID_PREFIX.'no_value']);

		// Check that the value is present - exact value is pulled client side
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'widget']['email']);
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'entry_id']['n_fn']);

		$this->assertEquals($GLOBALS['egw_info']['user']['account_email'], $data['content'][Entry::ID_PREFIX.'entry_id']['email']);
		$this->assertEquals($GLOBALS['egw_info']['user']['account_fullname'], $data['content'][Entry::ID_PREFIX.'entry_id']['n_fn']);

		// Values are to be done as labels
		$this->assertEquals('label', $data['modifications'][Entry::ID_PREFIX.'widget[email]']['type'], 'Unexpected widget type');
		$this->assertEquals('label', $data['modifications'][Entry::ID_PREFIX.'entry_id[n_fn]']['type'], 'Unexpected widget type');
		$this->assertEquals('label', $data['modifications'][Entry::ID_PREFIX.'info_contact_email[email]']['type'], 'Unexpected widget type');
		$this->assertEquals('label', $data['modifications'][Entry::ID_PREFIX.'info_contact[email]']['type'], 'Unexpected widget type');
		$this->assertEquals('label', $data['modifications'][Entry::ID_PREFIX.'no_value[email]']['type'], 'Unexpected widget type');

		// No errors
		$this->assertEmpty($data['validation_errors']);
	}

	/**
	 * Make a contact so we can test
	 */
	protected function make_contact()
	{
		$bo = new \addressbook_bo();
		$element = array(
			'n_fn' => "Mr Test Guy",
			'n_family' => 'Guy',
			'n_fileas' => 'Test Organisation: Guy, Test',
			'n_given' => 'Test',
			'n_prefix' => 'Mr',
			'org_name' => 'Test Organisation',
			'tel_cell' => '555-4321',
			'tel_home' => '66 12 34 56',
			'bday' => "1995-07-28T10:52:54Z"
		);
		$element_id = $bo->save($element, true, true, true, true);
		$this->elements[] = 'addressbook:'.$element_id;
		return $element_id;
	}
}
