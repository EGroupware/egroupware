<?php
/**
 * EGroupware Api: regression test for Etemplate\Widget\File::validate()'s tmp-file traversal guard
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
 * doc/ai/projects/security-regression-test-coverage.md): Etemplate\Widget\File::validate() built
 * a tmp-file path via `$GLOBALS['egw_info']['server']['temp_dir'].'/'.$tmp`, where $tmp is the
 * ARRAY KEY of the client-submitted 'file' value (the "temp name" the client claims its upload
 * was stored under) - entirely client-controlled, with no traversal check. A key like
 * '../../../etc/passwd' let a caller that later consumes the resulting tmp_name without its own
 * is_uploaded_file() check (see the sibling Link.php sites) read/reference an arbitrary file.
 *
 * Fix: `$tmp = basename((string)$tmp);` before using it in the path.
 *
 * This is a pure PHP-level test of validate()'s path construction, not a full upload round-trip -
 * the security-relevant behavior is entirely in how $tmp gets turned into $path.
 */
class FileValidateTraversalTest extends LoggedInTest
{
	protected function setUp() : void
	{
		parent::setUp();
		// Widget::validate()/is_readonly() dereference the protected static Widget::$request -
		// give it a minimal-but-real Request object (its $data property has inline defaults,
		// including readonlys=>[], applied even via newInstanceWithoutConstructor()) rather than
		// a full session-backed exec_id round-trip, which this path-construction test doesn't need.
		$reflection = new \ReflectionProperty(BaseWidget::class, 'request');
		$reflection->setAccessible(true);
		$reflection->setValue(null, (new \ReflectionClass(Request::class))->newInstanceWithoutConstructor());
	}

	public function testTraversalKeyIsConfinedToTempDirBasename()
	{
		$payload = '../../../etc/passwd';
		$content = [
			'file_widget' => [0 => [
				$payload => ['name' => 'evil.txt', 'type' => 'text/plain'],
			]],
		];
		$validated = [];

		$widget = new File();
		@$widget->validate('', [], $content, $validated);

		$tmp_name = $validated['file_widget']['tmp_name'] ?? null;
		$this->assertNotNull($tmp_name, 'validate() did not produce a tmp_name at all');
		$this->assertStringNotContainsString('..', $tmp_name,
			'the traversal payload must never survive into the constructed tmp-file path');
		$this->assertSame(
			rtrim($GLOBALS['egw_info']['server']['temp_dir'], '/').'/passwd',
			$tmp_name,
			'the path must be confined to temp_dir + basename(key), discarding every ../ segment'
		);
	}
}
