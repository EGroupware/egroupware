<?php
/**
 * EGroupware importexport: tests for importexport_arrayxml::array2xml()/xml2array()
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/src/loader/common.php');

use PHPUnit\Framework\TestCase;

/**
 * importexport_arrayxml round-trips a PHP array through an XML string (used to store
 * plugin_options/filter on importexport_definition, and for export/import of whole
 * definitions). This is a fully self-contained, DB-free static class - no session or
 * database is touched.
 *
 * Pass criteria per test: array2xml() followed by xml2array() reproduces the original
 * structure, with these known, deliberate type quirks (not treated as bugs - the class
 * docblock explicitly acknowledges the array/XML impedance mismatch):
 * - every leaf value round-trips as a string EXCEPT booleans, which round-trip as real
 *   PHP booleans (array2xml stores them as the literal text 'TRUE'/'FALSE' and xml2array
 *   converts back based on that literal).
 * - the top-level $_name argument becomes the sole top-level key of the returned array.
 * - an empty array round-trips to an empty string, not an empty array, because xml2array()
 *   decides "is this a scalar leaf or a nested array" purely from DOMNode::childNodes count,
 *   and an empty <entry/> has none either way.
 * - malformed XML input triggers a DOMDocument::loadXML() PHP warning (suppressed here,
 *   not something this test is trying to change) and xml2array() falls back to array().
 */
class ImportexportArrayxmlTest extends TestCase
{
	/**
	 * A representative mix of scalar types round-trips with the type quirks documented
	 * above: strings/ints/floats become strings, booleans stay booleans, empty string
	 * stays an empty string.
	 */
	public function testScalarTypesRoundTrip()
	{
		$data = array(
			'name' => 'Test',
			'count' => 5,
			'price' => 3.14,
			'active' => true,
			'inactive' => false,
			'empty' => '',
		);

		$xml = importexport_arrayxml::array2xml($data, 'root');
		$back = importexport_arrayxml::xml2array($xml);

		$this->assertSame(array(
			'name' => 'Test',
			'count' => '5',
			'price' => '3.14',
			'active' => true,
			'inactive' => false,
			'empty' => '',
		), $back['root'] ?? null,
			'scalar values must round-trip as strings, except real booleans');
	}

	/**
	 * Nested associative arrays, including a single-key nested array (exercises the
	 * childNodes-count-based array/scalar detection with the minimum possible child count),
	 * must round-trip intact.
	 */
	public function testNestedArraysRoundTrip()
	{
		$data = array(
			'nested' => array('a' => 1, 'b' => 2),
			'single' => array('only' => 'value'),
		);

		$xml = importexport_arrayxml::array2xml($data, 'root');
		$back = importexport_arrayxml::xml2array($xml);

		$this->assertSame(array(
			'nested' => array('a' => '1', 'b' => '2'),
			'single' => array('only' => 'value'),
		), $back['root'] ?? null,
			'nested arrays, including single-key ones, must round-trip intact');
	}

	/**
	 * A numeric-indexed (list-style) array round-trips with its keys preserved as
	 * sequential strings-cast-to-int, since xml2array() always assigns into $xml_array[$name]
	 * where $name is the original key read back from the 'name' XML attribute.
	 */
	public function testNumericIndexedArrayRoundTrips()
	{
		$data = array('list' => array('x', 'y', 'z'));

		$xml = importexport_arrayxml::array2xml($data, 'root');
		$back = importexport_arrayxml::xml2array($xml);

		$this->assertSame(array('list' => array('x', 'y', 'z')), $back['root'] ?? null,
			'a plain list array must round-trip with the same sequential keys');
	}

	/**
	 * A scalar (non-array) passed as the top-level $_data becomes a single leaf entry
	 * named by $_name, and xml2array() returns it keyed the same way.
	 */
	public function testTopLevelScalarRoundTrips()
	{
		$xml = importexport_arrayxml::array2xml('just a string', 'root');
		$back = importexport_arrayxml::xml2array($xml);

		$this->assertSame(array('root' => 'just a string'), $back);
	}

	/**
	 * Known quirk, not a bug: an empty array has no child nodes, which is
	 * indistinguishable (by childNodes->length) from an empty scalar leaf, so it comes
	 * back as an empty string rather than an empty array. Documents current behaviour.
	 */
	public function testEmptyArrayRoundTripsToEmptyString()
	{
		$xml = importexport_arrayxml::array2xml(array(), 'root');
		$back = importexport_arrayxml::xml2array($xml);

		$this->assertSame(array('root' => ''), $back);
	}

	/**
	 * Malformed XML must not throw - xml2array() catches the loadXML() failure and
	 * falls back to an empty array. DOMDocument::loadXML() emits a PHP warning for the
	 * malformed input, which is expected and suppressed here rather than asserted on.
	 */
	public function testMalformedXmlReturnsEmptyArray()
	{
		$back = @importexport_arrayxml::xml2array('not xml at all');

		$this->assertSame(array(), $back);
	}

	/**
	 * Empty/falsy input (no XML at all) also returns an empty array, without attempting
	 * to parse anything.
	 */
	public function testEmptyInputReturnsEmptyArray()
	{
		$this->assertSame(array(), importexport_arrayxml::xml2array(''));
	}
}
