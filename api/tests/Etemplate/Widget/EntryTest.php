<?php

/**
 * Test for special entry widget, which displays the value of a particular field
 * (specified by field attribute) from a particular egroupware entry (specified
 * by value attribute).
 *
 * Just simple stuff here, the overriding classes have their own tests.
 *
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

class EntryTest extends \EGroupware\Api\Etemplate\WidgetBaseTest {

	const TEST_TEMPLATE = 'api.entry_test';

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		Etemplate::registerWidget(EntryTestWidget::class, 'entry');
	}

	public function setUp() {
		parent::setUp();

		Etemplate::reset_request();
	}
	public static function getEntry($value, $attrs)
	{
		$entry = array();

		$entry['7'] = array(
			'entry_id'	=> 7,
			'entry_field_1' => 'Field 1',
			'entry_field_2' => 'Field 2',
			'entry_field_3' => 'Field 3',
			'entry_num_1'	=> 1,
			'entry_num_2'	=> 2,
			'entry_num_3'	=> 3,
			'entry_date'	=> '2018-11-12'
		);
		return $entry[$value];
	}
	/**
	 * Test that the correct value is extracted, based on the value attribute,
	 * or if it is missing try the ID
	 */
	public function testValueAttr()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Content - entry ID is important, it should match what getEntry() gives
		$content = array(
			'entry_id'            =>	'7',
			'not_entry_id'        =>	'123'
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

		// Check that the entry was not found
		$this->assertEmpty($data['content'][Entry::ID_PREFIX.'no_value']);

		// Check that the value is present - exact value is pulled client side
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'widget']['entry_field_1']);
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'entry_id']['entry_field_2']);

		// No errors
		$this->assertEmpty($data['validation_errors']);
	}

	/**
	 * Check on compare attribute.
	 * Actual comparison is done client side, but here we check that the value is found.
	 */
	public function testCompareAttr()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Content - entry ID is important, it should match what getEntry() gives
		$content = array(
			'entry_id'            =>	'7',
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
		
		// Check that the value is present - exact value is pulled client side
		$this->assertNotEmpty($data['content'][Entry::ID_PREFIX.'compare']['entry_num_1']);
	}
}

/**
 * Testable entry widget
 */
class EntryTestWidget extends \EGroupware\Api\Etemplate\Widget\Entry
{
	public function get_entry($value, array $attrs)
	{
		$entry = EntryTest::getEntry($value, $attrs);

		return $entry;
	}
}