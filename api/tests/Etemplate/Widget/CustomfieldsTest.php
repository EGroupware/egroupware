<?php

/**
 * Test for Customfields widget
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage etemplate
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__ . '/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class CustomfieldsTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.customfields_test';

	/**
	 * Mocked customfields used throughout.
	 */
	const cf_list = [
		'text'         => array(
			'id'      => '1',
			'app'     => 'infolog',
			'name'    => 'text',
			'label'   => 'text',
			'type'    => 'text',
			'needed'  => false,
			'private' => [],
		),
		'required'     => array(
			'id'      => '2',
			'app'     => 'infolog',
			'name'    => 'required',
			'label'   => 'required',
			'type'    => 'text',
			'needed'  => true,
			'private' => [],
		),
		'private'      => array(
			'id'      => '3',
			'app'     => 'infolog',
			'name'    => 'private',
			'label'   => 'private',
			'type'    => 'text',
			'needed'  => false,
			'private' => ['-1'],
		),
		'type_limited' => array(
			'id'     => '4',
			'app'    => 'infolog',
			'name'   => 'type_limited',
			'label'  => 'type limited',
			'type'   => 'text',
			'type2'  => ['subtype'],
			'needed' => false,
		)
	];

	/**
	 * Test the widget's basic functionality - we put data in, it comes back
	 * unchanged.
	 *
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('validProvider')]
	public function testBasic($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Set CFs
		$etemplate->setElementAttribute('widget', 'customfields', self::cf_list);

		// Send it around
		$result = $this->mockedRoundTrip($etemplate, array('widget' => $content));

		// Test it
		$this->validateTest($result, array('widget' => $content));
	}

	/**
	 * Test that the widget does not return a value if readonly
	 *
	 *
	 * It's the client that removes them here, so we can't really test without.
	 *
	 * public function testReadonly($content)
	 * {
	 * // Instanciate the template
	 * $etemplate = new Etemplate();
	 * $etemplate->read(static::TEST_TEMPLATE, 'test');
	 *
	 * // Set CFs
	 * $etemplate->setElementAttribute('widget', 'customfields', self::cf_list);
	 *
	 * // Exec
	 * $result = $this->mockedRoundTrip($etemplate, ['widget' => $content], array(), array('widget' => true));
	 *
	 * // Check
	 * $this->assertEquals(array(), $result);
	 * }
	 */

	/**
	 * @return void
	 * @throws \Exception
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('validProvider')]
	public function testPrivateFilter($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Set CFs
		$etemplate->setElementAttribute('widget', 'customfields', self::cf_list);

		// Exec
		$result = $this->mockedRoundTrip($etemplate, ['private' => true, 'widget' => $content]);

		// Check for only the private field
		$this->assertSame(['widget' => ['#private' => 'private']], $result);


		// Now the opposite, only non-private
		unset($content['#private']);
		// Exec
		$etemplate->setElementAttribute('widget', 'customfields', self::cf_list);
		$result = $this->mockedRoundTrip($etemplate, ['private' => '0', 'widget' => $content]);

		// Check for all but the private field
		$this->assertSame(['widget' => $content], $result);
	}

	/**
	 * Check field filtering
	 *
	 * @param type $content
	 * @param type $validation_errors
	 *
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('fieldFilterProvider')]
	public function testFieldFilter($content, $fields, $expected)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Set CFs
		$etemplate->setElementAttribute('widget', 'customfields', self::cf_list);

		// Filter
		$etemplate->setElementAttribute('widget', 'fields', $fields);

		$content = array('widget' => $content);
		$result = $this->mockedExec($etemplate, $content);

		// Check for the load
		$data = array();
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}

		// Make sure we're sending what is expected
		$this->assertContains(key($expected), array_keys($data['data']['content']['widget']), "Filtered field missing");
		$this->assertEquals(current($expected), $data['data']['content']['widget'][key($expected)], "Filtered field's value missing");
	}

	/**
	 * These are all valid, since fields are all text.
	 * The individual widgets to their own type validation.
	 */
	/**
	 * Mocked customfield linking to an entry of any app, a "Select entry" field.
	 */
	const link_entry_cf = [
		'entry' => array(
			'id'      => '5',
			'app'     => 'infolog',
			'name'    => 'entry',
			'label'   => 'entry',
			'type'    => 'link-entry',
			'needed'  => false,
			'private' => [],
		)
	];

	/**
	 * A template with the customfield linking to an entry of any app.
	 *
	 * A template of its own: a template's widgets are shared between the tests of a run and keep
	 * the customfields the first test gave them, so the other tests' template would never show
	 * this field.
	 */
	protected function linkEntryTemplate() : Etemplate
	{
		$etemplate = new Etemplate();
		$etemplate->read('api.customfields_link_test', 'test');
		$etemplate->setElementAttribute('widget', 'customfields', self::link_entry_cf);
		return $etemplate;
	}

	/**
	 * A field linking to an entry of any app is submitted as an app and id pair, and has to be
	 * saved as app:id.  Joined any other way, the entry can not be opened again: loading the
	 * form rejects the value.
	 */
	public function testLinkEntrySavedAsAppAndId()
	{
		$etemplate = $this->linkEntryTemplate();

		$this->validateRoundTrip($etemplate,
			array('widget' => []),
			array('widget' => ['#entry' => ['app' => 'addressbook', 'id' => '237']]),
			array('widget' => ['#entry' => 'addressbook:237'])
		);
	}

	/**
	 * An id holding a colon of its own, eg. a project's element, is saved whole.
	 */
	public function testLinkEntryIdWithColonSavedWhole()
	{
		$etemplate = $this->linkEntryTemplate();

		$this->validateRoundTrip($etemplate,
			array('widget' => []),
			array('widget' => ['#entry' => ['app' => 'projectmanager', 'id' => '5:7']]),
			array('widget' => ['#entry' => 'projectmanager:5:7'])
		);
	}

	/**
	 * With no entry selected there is nothing to save, whichever app was picked to search in.
	 */
	public function testLinkEntryWithoutIdSavedEmpty()
	{
		$etemplate = $this->linkEntryTemplate();

		$this->validateRoundTrip($etemplate,
			array('widget' => []),
			array('widget' => ['#entry' => ['app' => 'addressbook', 'id' => '']]),
			array('widget' => ['#entry' => false])
		);
	}

	/**
	 * A stored link to an entry of any app loads into the form as its app and id, whether it
	 * was stored as app:id or as app,id - which is how forms saved it for a long time, with
	 * whatever else the widget submitted joined on behind.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('storedLinkEntryProvider')]
	public function testStoredLinkEntryLoads($stored, $expected = ['app' => 'addressbook', 'id' => '237'])
	{
		$etemplate = $this->linkEntryTemplate();

		$result = $this->mockedExec($etemplate, array('widget' => ['#entry' => $stored]));

		$data = array();
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}
		$this->assertEquals(
			$expected,
			$data['data']['content']['widget']['#entry'] ?? null,
			"Stored value '$stored' did not load as its app and id"
		);
	}

	public static function storedLinkEntryProvider()
	{
		return array(
			'app:id'               => ['addressbook:237'],
			'app,id'               => ['addressbook,237'],
			'app,id and more'      => ['addressbook,237,'],
			// an id can hold a separator of its own, eg. a project's element or constraint
			'app:id with colons'   => ['projectmanager:5:7:9', ['app' => 'projectmanager', 'id' => '5:7:9']],
			'app,id with colons'   => ['projectmanager,5:7:9', ['app' => 'projectmanager', 'id' => '5:7:9']],
		);
	}

	public static function validProvider()
	{
		return array(
			[['#text' => 'text', '#required' => 'required', '#private' => 'private', '#type_limited' => 'type_limited']]
		);
	}

	/**
	 * Check filtering each field individually, then filtering to allow all fields
	 */
	public static function fieldFilterProvider()
	{
		$field_values = CustomfieldsTest::validProvider()[0][0];
		$field_filters = array();
		foreach(CustomfieldsTest::cf_list as $field_name => $field)
		{
			$field_filters[] = array(
				$field_values,
				$field_name,
				['#' . $field_name => $field_values['#' . $field_name]]
			);
		}
		$field_filters[] = array(
			$field_values,
			array_keys(CustomfieldsTest::cf_list),
			$field_values
		);
		return $field_filters;
	}
}
