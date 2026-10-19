<?php
/**
 * Test for api/filter-template.php generating the filter-template from a nextmatch row-template
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

use PHPUnit\Framework\TestCase;

class FilterTemplateTest extends TestCase
{
	/**
	 * Run api/filter-template.php in CLI mode for a test template and return the generated XML
	 *
	 * @param string $fspath eg. /api/templates/test/filter_custom_test.xet
	 * @return string
	 */
	protected function generate(string $fspath) : string
	{
		$script = dirname(__DIR__, 2).'/filter-template.php';
		$out = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($fspath).' 2>/dev/null');
		$this->assertNotEmpty($out, 'filter-template.php generated nothing for '.$fspath);
		return $out;
	}

	/**
	 * A legacy "link-entry,infolog" customfilter option must not end up in the element name:
	 * that made the generated XML unparsable and dropped every column filter behind it.
	 */
	public function testCommaSeparatedCustomfilterOptionsGiveValidXmlWithAllFilters()
	{
		$xml = $this->generate('/api/templates/test/filter_custom_test.xet');

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $dom->loadXML($xml);
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		$this->assertTrue($loaded, 'generated filter-template is not well-formed: '.($errors ? trim($errors[0]->message) : ''));

		$ids = [];
		foreach ($dom->getElementsByTagName('et2-details')->item(0)->childNodes as $node)
		{
			if ($node instanceof \DOMElement) $ids[$node->getAttribute('id')] = $node;
		}
		$this->assertSame(['col_filter[before_id]', 'col_filter[contact_id]', 'col_filter[after_id]'], array_keys($ids),
			'all column filters, also the ones behind the customfilter, must be generated');

		$entry = $ids['col_filter[contact_id]'];
		$this->assertSame('et2-link-entry', $entry->tagName);
		$this->assertSame('infolog', $entry->getAttribute('onlyApp'), 'application of "link-entry,infolog" becomes onlyApp');
		$this->assertSame('Customer', $entry->getAttribute('label'), 'legacy blur text is used as label');
		$this->assertFalse($entry->hasAttribute('blur'));
	}
}
