<?php

/**
 * Test file for the base widget class
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

require_once realpath(__DIR__.'/WidgetBaseTest.php');

/**
 * Tests for the base widget class
 *
 * Widget scans the apps for widgets, which needs the app list, pulled from the
 * database, so we need to log in.
 */
class WidgetTest extends WidgetBaseTest {
	
	/**
	 * @var Array Used as a common content for expansion
	 */
	static $expand = array(
		'cont' => array(
			'expand_me'	=> 'expanded',
			'expand_2'	=> 'also_expanded',
			0	=> array(
				'id'	=> 'row_id'
			)
		),
		'row'	=> 0,
		'c'		=> 0
	);

	/**
	 * Test that setting and retrieving widget attributes is working as expected
	 */
	public function testAttributes()
	{
		$xml = '<widget id="test" attribute="set" />';
		$widget = new Widget($xml);
		

		$this->assertEquals('test', $widget->id, 'ID was not set');

		// Set in XML goes into attributes
		$this->assertEquals('set', $widget->attrs['attribute'], 'XML attribute missing');

		// get/setElementAttribute do not include xml
		$this->assertNull($widget->getElementAttribute('test','attribute'));

		// Calling setElementAttribute without a request will give an error when
		// it tries to set the header.
		ob_start();

		// XML does not include get/setElementAttribute
		$widget->setElementAttribute('test', 'other_attribute', 'set');
		$this->assertEquals('set', $widget->getElementAttribute('test','other_attribute'));
		$this->assertNull($widget->attrs['other_attribute']);
		
		ob_end_clean();
	}

	/**
	 * Check to make sure form name building is still working.
	 * Uses expansion array
	 *
	 *
	 * @param string $base Base or container / parent ID
	 * @param string $element Element ID
	 * @param string $expected Expected result
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('formNameProvider')]
	public function testFormName($base, $element, $expected)
	{
		$this->assertEquals($expected, Widget::form_name($base, $element, self::$expand));
	}

	/**
	 * Provides data for testFormName
	 *
	 * Each dataset is base (container or parent ID), input ID, expected result
	 * when using self::$expand to fill expansion variables.
	 */
	public static function formNameProvider()
	{
		return array(
			// Base name, element name, expected
			['', 'input', 'input'],
			['', 'del[$cont[expand_me]]', 'del[expanded]'],
			['container', 'input', 'container[input]'],
			['grid[sub]', 'input', 'grid[sub][input]'],
			['grid', 'sub[input]', 'grid[sub][input]'],
			['grid[sub]', 'sub[input]', 'grid[sub][sub][input]'],
			['', '@expand_me', 'expanded'],
			['@expand_me', 'input', '@expand_me[input]'],
			['container', '@expand_me', 'container[expanded]'],

			// Rows
			['', '$row', '0'],
			['$row', '', '$row[]'],		// Expansion only on element name
			['grid', '$row', 'grid[0]'],
			['grid', '$cont[$row]', 'grid[Array]'],
			['grid', '$row_cont[id]', 'grid[row_id]'],
			['', '$row_cont[id]', 'row_id'],
			['container', '$row_cont[id]', 'container[row_id]'],

			// ${row} / deprecated {$row} alias: literal substitution of $row's value,
			// not a variable reference, so it also works embedded in surrounding text
			['', '${row}', '0'],
			['', '{$row}', '0'],
			['', '${row}_suffix', '0_suffix'],
			['grid', '${row}', 'grid[0]'],

			// ${row}[key]: by far the most common real-world "id" attribute pattern in
			// .xet files (grid row widget ids, eg. id="${row}[account_lid]") - $row's
			// value is substituted literally first, the [key] part is untouched literal
			// text, then form_name()'s own bracket-splitting turns it into a nested name
			['', '${row}[account_lid]', '0[account_lid]'],
			['grid', '${row}[account_lid]', 'grid[0][account_lid]'],

			// $cont[key] direct (not wrapped in another container)
			['', '$cont[expand_me]', 'expanded'],
			['', '$cont[expand_2]', 'also_expanded'],

			// Known quirk: simple double-quote interpolation only expands ONE level of
			// [..], so a 2nd, chained [..] (as used e.g. by mail's "$cont[fromlavatar][fname]"
			// et2-lavatar attrs) is NOT resolved - $cont[0] is an array, gets stringified to
			// "Array", and the 2nd [id] is left as literal trailing text.
			['', '$cont[0][id]', 'Array[id]'],

			// Column
			['', '$c', '0'],
			['$c', '', '$c[]'],		// Expansion only on element name
			['grid', '$c', 'grid[0]'],

			// Maybe not right, but this is what it gives
			['container', '@expand_me[input]', 'container[]'],
			['container', 'input[@expand_me]', 'container[input][@expand_me]'],
			['container', '@expand_2[@expand_me]', 'container[]']
		);
	}

	/**
	 * Characterize that PHP-code-injection attempts via a widget name/id/attribute
	 * are never executed by expand_name(), which resolves $var / $var[key] / ${row} /
	 * {$row} purely via preg_replace_callback() - there is no eval() left to break out
	 * of, so these assert "left untouched/inert", not "specially disarmed".
	 *
	 * These do NOT necessarily give a "clean" or useful result, they just must never
	 * execute attacker-supplied code.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('expandNameHardeningProvider')]
	public function testExpandNameHardening($name, $expected)
	{
		$this->assertEquals($expected, Widget::form_name('', $name, self::$expand));
	}

	/**
	 * Provides data for testExpandNameHardening
	 *
	 * Each dataset is the raw (attacker-influenced) name/id/attribute value, and the
	 * expected (inert) result when using self::$expand to fill expansion variables.
	 */
	public static function expandNameHardeningProvider()
	{
		return array(
			// ${...} / {$...} (other than the literal ${row}/{$row} alias) is PHP's
			// variable-variable / complex-interpolation syntax - our regex only matches
			// a bare '$' immediately followed by \w+, so '${...}' never matches at all
			// and is left completely untouched, never executed
			['${system(\'id\')}', '${system(\'id\')}'],
			['${phpinfo()}', '${phpinfo()}'],
			['${passthru("id")}', '${passthru("id")}'],
			['{$row}${system(\'id\')}', '0${system(\'id\')}'],

			// backtick shell-exec operator is source-code-only syntax, never shell-exec'd
			// in a plain PHP string value - inert regardless of whether '$' is present
			['`id`', '`id`'],
			['a`whoami`b', 'a`whoami`b'],
			['${row}`id`', '0`id`'],

			// there is no eval()'d string to break out of any more, so quotes/backslashes
			// are just literal characters the regex doesn't match against
			['\\" . system("id") . "', '\\" . system("id") . "'],
			['" . exec("id") . "', '" . exec("id") . "'],
		);
	}

	/**
	 * Test that the widget loads the xml and gets all children
	 */
	public function testSimpleLoad()
	{
		$test_template = <<<EOT
<widget id="main_parent">
	<widget id="first_child"/>
	<widget id="second_child">
		<widget id="sub_widget"/>
	</widget>
	<widget id="third_child">
		<widget id="@expand_me"/>
	</widget>
</widget>
EOT;
		
		$widget = new Widget($test_template);

		// getElementsByType does not include the widget itself
		$this->assertEquals(5, count($widget->getElementsByType('widget')), 'Missing children');

		// Check that it can find the sub
		$this->assertNotNull($widget->getElementById('sub_widget'), 'Could not find sub_widget');

		// Check that it can find the un-expanded - expansion doesn't happen on load
		$this->assertNotNull($widget->getElementById('@expand_me'), 'Could not find @expand_me');

		// Check failure
		$this->assertNull($widget->getElementById('totally_invalid'), 'Found widget that is not there');
	}
}
