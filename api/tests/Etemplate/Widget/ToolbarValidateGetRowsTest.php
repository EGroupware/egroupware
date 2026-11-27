<?php
/**
 * EGroupware Api: regression test for Etemplate\Widget\Toolbar::validate()'s get_rows stripping
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once __DIR__.'/../../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Etemplate\Request;
use EGroupware\Api\Etemplate\Widget as BaseWidget;

/**
 * get_rows must always be decided server-side, never taken from submitted content.
 */
class ToolbarValidateGetRowsTest extends LoggedInTest
{
	/**
	 * @var \ReflectionProperty
	 */
	protected $requestProperty;
	protected $origRequest;

	protected function setUp() : void
	{
		parent::setUp();
		// Toolbar::validate() dereferences the protected static Widget::$request (via is_readonly()
		// and get_array()) - give it a minimal-but-real Request object, see FileValidateTraversalTest.
		$this->requestProperty = new \ReflectionProperty(BaseWidget::class, 'request');
		$this->requestProperty->setAccessible(true);
		$this->origRequest = $this->requestProperty->getValue();
		$this->requestProperty->setValue(null, (new \ReflectionClass(Request::class))->newInstanceWithoutConstructor());
	}

	protected function tearDown() : void
	{
		$this->requestProperty->setValue(null, $this->origRequest);
		parent::tearDown();
	}

	public function testGetRowsIsStrippedFromSubmittedContent()
	{
		$content = [
			'toolbar' => [
				'filter'   => 'some-filter',
				// must never survive validate(): would override the server-side get_rows callback
				'get_rows' => 'EGroupware\\Api\\Etemplate\\Widget\\ToolbarValidateGetRowsTest::other_get_rows',
			],
		];
		$validated = [];

		$widget = new Toolbar('<toolbar id="toolbar"/>');
		$widget->validate('', [], $content, $validated);

		$this->assertArrayNotHasKey('get_rows', $validated['toolbar'] ?? [],
			"a 'get_rows' key submitted via a toolbar must never survive validation");
		$this->assertSame('some-filter', $validated['toolbar']['filter'] ?? null,
			'other submitted toolbar content must still be validated through');
	}

	public static function other_get_rows(&$query, &$rows, &$readonlys)
	{
		$rows = array();
		return 0;
	}

	public function testNonArrayToolbarContentDoesNotFatal()
	{
		// a toolbar submission is not always an array (eg. an empty string) - validate() must
		// still go through cleanly instead of trying to unset a key on a non-array value
		$content = [
			'toolbar' => '',
		];
		$validated = [];

		$widget = new Toolbar('<toolbar id="toolbar"/>');
		$widget->validate('', [], $content, $validated);

		$this->assertSame('', $validated['toolbar'] ?? null,
			'a non-array toolbar submission must still validate through unchanged');
	}
}
