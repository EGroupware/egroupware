<?php
/**
 * Test the nextmatch header conversion of api/etemplate.php
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

use PHPUnit\Framework\TestCase;

/**
 * Converts a fixture with every legacy nextmatch header name through the etemplate.php CLI
 *
 * The CLI keeps filters as filter headers (it never replaces them with plain headers, as serving does),
 * so each legacy name has to arrive as its own web-component:
 * - nextmatch-header (self-closing or with a closing tag) --> et2-nextmatch-header
 * - nextmatch-filterheader, -taglistheader, -filter, -header-filter --> et2-nextmatch-header-filter
 * - nextmatch-accountfilter --> et2-nextmatch-header-account
 * - nextmatch-customfilter with a type --> et2-nextmatch-header-custom
 * - nextmatch-entryheader, -entry --> et2-nextmatch-header-entry
 * - nextmatch-sortheader --> et2-nextmatch-sortheader, its options= (the default sort direction) becoming
 *   sortmode=, also for an already converted et2-nextmatch-sortheader, an explicit sortmode= winning
 * - already converted et2-nextmatch-header-* stay as they are
 *
 * etemplate.php runs its conversion on include, so it is run as a separate process.
 */
class ConvertNextmatchHeadersTest extends TestCase
{
	const FIXTURE = '/api/tests/fixtures/etemplate/nextmatch_headers.xet';

	/**
	 * @var \DOMXPath of the converted fixture
	 */
	protected static $xpath;

	public static function setUpBeforeClass() : void
	{
		$root = dirname(__DIR__, 3);
		$output = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/api/etemplate.php').' '.
			escapeshellarg(self::FIXTURE).' 2>/dev/null');
		$dom = new \DOMDocument();
		if (empty($output) || !$dom->loadXML($output))
		{
			self::fail("Converting ".self::FIXTURE." gave no valid XML:\n".$output);
		}
		self::$xpath = new \DOMXPath($dom);
	}

	public static function headerProvider() : array
	{
		return [
			'header with closing tag'  => ['header_closed', 'et2-nextmatch-header', ['label' => 'Closed']],
			'self-closing header'      => ['header_short', 'et2-nextmatch-header', ['label' => 'Short']],
			'sortheader'               => ['sort', 'et2-nextmatch-sortheader', ['sortmode' => 'DESC', 'options' => null]],
			'converted sortheader'     => ['et2_sort', 'et2-nextmatch-sortheader', ['sortmode' => 'DESC', 'options' => null]],
			'sortheader with sortmode' => ['sort_both', 'et2-nextmatch-sortheader', ['sortmode' => 'ASC', 'options' => null]],
			'filterheader'             => ['filterheader', 'et2-nextmatch-header-filter', ['emptyLabel' => 'Type']],
			'taglistheader'            => ['taglistheader', 'et2-nextmatch-header-filter', ['emptyLabel' => 'Tags']],
			'filter'                   => ['filter', 'et2-nextmatch-header-filter', ['emptyLabel' => 'Filter']],
			'accountfilter'            => ['accountfilter', 'et2-nextmatch-header-account', ['emptyLabel' => 'Modified by']],
			'customfilter'             => ['customfilter', 'et2-nextmatch-header-custom', ['widgetType' => 'select-country', 'type' => null]],
			// without a type the server-side Customfilter transformer picks the widget from its options
			'untyped customfilter'     => ['customfilter_untyped', 'nextmatch-customfilter', ['options' => 'link-entry']],
			'entryheader'              => ['entryheader', 'et2-nextmatch-header-entry', ['emptyLabel' => 'Entry']],
			'entry'                    => ['entry', 'et2-nextmatch-header-entry', ['emptyLabel' => 'Entry']],
			'dashed header-filter'     => ['header_filter', 'et2-nextmatch-header-filter', ['emptyLabel' => 'Dashed']],
			'converted account header' => ['et2_account', 'et2-nextmatch-header-account', ['emptyLabel' => 'Owner']],
			'converted custom header'  => ['et2_custom', 'et2-nextmatch-header-custom', ['widgetType' => 'select-app']],
			'customfields'             => ['customfields', 'et2-nextmatch-header-customfields', []],
		];
	}

	/**
	 * @param string $id header id in the fixture
	 * @param string $tag expected tag
	 * @param array $attrs expected attribute values, null for an attribute that must be gone
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('headerProvider')]
	public function testHeaderConversion(string $id, string $tag, array $attrs)
	{
		$found = self::$xpath->query('//*[@id="'.$id.'"]');
		$this->assertEquals(1, $found->length, "header id=\"$id\" not found once in the converted template");
		$header = $found->item(0);
		$this->assertEquals($tag, $header->tagName, "header id=\"$id\"");
		foreach ($attrs as $name => $value)
		{
			$this->assertEquals($value, $header->hasAttribute($name) ? $header->getAttribute($name) : null,
				"attribute $name of header id=\"$id\"");
		}
	}
}
