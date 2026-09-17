<?php
/**
 * EGroupware Api: regression test for Etemplate\Widget\Link::validate()'s tmp-file traversal guard
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
 * Regression coverage for commit 133266b4ef ("hardened tmp-file uses", one of 4 sites - see
 * doc/ai/projects/security-regression-test-coverage.md): Etemplate\Widget\Link::validate()'s
 * "files dragged in" branch built a tmp_name via
 * `$GLOBALS['egw_info']['server']['temp_dir'].'/'.$name`, where $name is the ARRAY KEY of the
 * client-submitted "<id>_file" value - entirely client-controlled, with no traversal check. This
 * constructed array (with app=>VFS_APPNAME) flows into the same Api\Link::link()/attach_file()/
 * Vfs::copy_uploaded(..., false) chain as the sibling ajax_link() site (see
 * LinkAjaxLinkTraversalTest.php) - attach_file() deliberately skips is_uploaded_file(), so this
 * was a real arbitrary-file-disclosure primitive, not just defense-in-depth.
 *
 * Fix: `'tmp_name' => $path.basename((string)$name)` - confines the path to a plain filename
 * directly inside temp_dir, discarding any directory component.
 *
 * This is a pure PHP-level test of validate()'s path construction (like FileValidateTraversalTest),
 * not a full attach_file() round-trip (like LinkAjaxLinkTraversalTest) - the security-relevant
 * behavior here is entirely in how $name gets turned into tmp_name, mirroring the sibling File.php
 * widget's own validate() site exactly.
 */
class LinkValidateFileTraversalTest extends LoggedInTest
{
	/**
	 * @var \ReflectionProperty
	 */
	protected $requestProperty;
	protected $origRequest;

	protected function setUp() : void
	{
		parent::setUp();
		// Widget::validate()/is_readonly() dereference the protected static Widget::$request -
		// see FileValidateTraversalTest.php for why a minimal Request stub is sufficient here.
		// MUST be restored in tearDown() - leaving this stub in place corrupted an unrelated later
		// test badly enough to crash the whole PHPUnit process in CI.
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

	public function testTraversalKeyIsConfinedToTempDirBasename()
	{
		$payload = '../../../etc/passwd';
		$content = [
			// $this->id is unset for a bare `new Link()` - validate() requires it truthy, so give
			// it one directly (Widget's $id is a plain writable property, no XML parse needed)
			'link_widget_file' => [
				$payload => ['name' => 'evil.txt', 'type' => 'text/plain'],
			],
		];
		$validated = [];

		$widget = new Link();
		$widget->id = 'link_widget';
		@$widget->validate('', ['cont' => []], $content, $validated);

		$to_id = $validated['link_widget']['to_id'] ?? [];
		$this->assertNotEmpty($to_id, 'validate() did not produce any to_id entries at all');
		$tmp_name = $to_id[0]['id']['tmp_name'] ?? null;
		$this->assertNotNull($tmp_name, 'the constructed link entry has no tmp_name');
		$this->assertStringNotContainsString('..', $tmp_name,
			'the traversal payload must never survive into the constructed tmp-file path');
		$this->assertSame(
			rtrim($GLOBALS['egw_info']['server']['temp_dir'], '/').'/passwd',
			$tmp_name,
			'the path must be confined to temp_dir + basename(key), discarding every ../ segment'
		);
	}
}
