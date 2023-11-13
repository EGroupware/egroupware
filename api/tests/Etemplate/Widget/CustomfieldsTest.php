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
	 * @dataProvider validProvider
	 */
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
	 * @dataProvider validProvider
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
	 * @dataProvider validProvider
	 * @return void
	 * @throws \Exception
	 */
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
	 * @dataProvider fieldFilterProvider
	 */
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
	public static function validProvider()
	{
		return array(
			[['#text' => 'text', '#required' => 'required', '#private' => 'private', '#type_limited' => 'type_limited']]
		);
	}

	/**
	 * Check filtering each field individually, then filtering to allow all fields
	 */
	public function fieldFilterProvider()
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
