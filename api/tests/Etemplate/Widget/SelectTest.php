<?php

/**
 * App
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class SelectTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.select_test';

	/**
	 * Test options, used throughout.
	 * Note that those are the Greek uppercase, not Latin.
	 * That's not A,B,E,Z,H,I..., and they don't match.
	 */
	const VALUES = array(
		'Α'=>	'α	Alpha',
		'Β'=>	'β	Beta',
		'Γ'=>	'γ	Gamma',
		'Δ'=>	'δ	Delta',
		'Ε'=>	'ε	Epsilon',
		'Ζ'=>	'ζ	Zeta',
		'Η'=>	'η	Eta',
		'Θ'=>	'θ	Theta',
		'Ι'=>	'ι	Iota',
		'Κ'=>	'κ	Kappa',
		'Λ'=>	'λ	Lambda',
		'Μ'=>	'μ	Mu',
		'Ν'=>	'ν	Nu',
		'Ξ'=>	'ξ	Xi',
		'Ο'=>	'ο	Omicron',
		'Π'=>	'π	Pi',
		'Ρ'=>	'ρ	Rho',
		'Σ'=>	'σ	Sigma',
		'Τ'=>	'τ	Tau',
		'Υ'=>	'υ	Upsilon',
		'Φ'=>	'φ	Phi',
		'Χ'=>	'χ	Chi',
		'Ψ'=>	'ψ	Psi',
		'Ω'=>	'ω	Omega',
	);

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
		$result = $this->mockedRoundTrip($etemplate, array('widget' => $content), array('widget' => self::VALUES));

		// Test it
		$this->validateTest($result, array('widget' => $content));
	}

	/**
	 * These are all valid
	 *
	 */
	public function validProvider()
	{
		$values = array(array(''));
		foreach(self::VALUES as $key => $label)
		{
			$values[] = array($key);
		}
		return $values;
	}


	/**
	 * Check validation with failing values
	 *
	 * @param string $content
	 *
	 * @dataProvider validationProvider
	 */
	public function testValidation($content)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Send it around
		$result = $this->mockedRoundTrip($etemplate, array('widget' => $content), array('widget' => self::VALUES));

		// Test it
		$this->validateTest($result, array(),  array('widget'=> true));
	}

	/**
	 * These are all invalid
	 */
	public function validationProvider()
	{
		// All these are invalid, and should not give a value back
		return array(
			array('0'),
			array('Alpha'),
			array('A'),         // This is ASCII A, not Alpha
			array('Α,Β'),
		);
	}

	/**
	 * Test to make sure a selectbox that accepts multiple actually does
	 *
	 * @param Array $content
	 * @param Array $expected
	 *
	 * @dataProvider multipleProvider
	 */
	public function testMultiple($content, $expected)
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Send it around
		$result = $this->mockedRoundTrip($etemplate, $content, array(
			'widget' => self::VALUES,
			'widget_multiple' => self::VALUES
		));

		// Test it
		$this->validateTest($result, $expected);
	}

	public function multipleProvider()
	{
		return array(
			// Test
			array(
				array('widget' => '', 'widget_multiple' => ''),     // Content
				array('widget' => '', 'widget_multiple' => ''),     // Expected
			),
			array(
				array('widget' => 'Α', 'widget_multiple' => 'Α'),
				array('widget' => 'Α', 'widget_multiple' => 'Α'),
			),
			// Check for CSV - should fail
			array(
				array('widget' => 'Α,Β', 'widget_multiple' => 'Α,Β'),
				array('widget' => '', 'widget_multiple' => ''),
			),
			// Check for array - should work
			array(
				array('widget' => array('Α','Β'), 'widget_multiple' => array('Α','Β')),
				array('widget' => 'Α', 'widget_multiple' => array('Α','Β')),
			),
		);
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
			'widget'            =>	'Α',
			'widget_readonly'   =>	'Α',
			'widget_multiple'   =>	'Α'
		);

		// Set non-readonly widgets to read-only via parameter to Etemplate::exec()
		$result = $this->mockedRoundTrip($etemplate, $content,
				array('widget' => self::VALUES, 'widget_readonly' => self::VALUES, 'widget_multiple' => self::VALUES),
				array('widget' => true, 'widget_multiple' => true)
		);

		// Check - nothing comes back
		$this->assertEquals(array(), $result);
	}

	/**
	 * Test that an edited read-only widget does not return a value, even if the
	 * client side gives one, which should be an unusual occurrence.
	 */
	public function testEditedReadonly()
	{
		// Instanciate the template
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// Exec
		$content = array(
			'widget'            =>	'Α',
			'widget_readonly'   =>	'Α',
			'widget_multiple'   =>	'Α'
		);
		$result = $this->mockedExec($etemplate, $content,
				array('widget' => self::VALUES, 'widget_readonly' => self::VALUES, 'widget_multiple' => self::VALUES),
				array('widget' => true, 'widget_multiple' => true)
		);

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

		// 'Edit' the data client side
		$data['data']['content'] = array(
			'widget'            =>	'Ω',
			'widget_readonly'   =>	'Ω',
			'widget_multiple'   =>	'Ω'
		);

		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		// Nothing comes back, even though edited since it's readonly
		$this->assertEquals(array(), $content);
	}
}