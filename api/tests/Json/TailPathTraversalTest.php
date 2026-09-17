<?php
/**
 * EGroupware API: regression test for Json\Tail's path-traversal guard
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage json
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Json;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for GHSA-9q77-2jxr-h9mp (CalDAV Hooks::log / Json\Tail path traversal,
 * high severity).
 *
 * Tail::__construct() used to sanitize the caller-controlled filename with a NON-RECURSIVE
 * `str_replace('../', '', $filename)` - the payload `....//` collapses to `../` after exactly one
 * pass and survives. The sanitized (but still-traversal) value was then stored in a
 * session-persisted allowlist, and the download/ajax_chunk/ajax_delete sinks only ever re-checked
 * that allowlist (`in_array($filename, $this->filenames)`), never re-running any path check -
 * so once a traversal path was in the allowlist, it stayed usable for read AND delete/truncate of
 * arbitrary .log-suffixed files as the web-server user.
 *
 * The fix replaces the strip-and-hope sanitizer with a reject-outright guard: any filename
 * containing the literal two-character substring ".." anywhere is refused before it can ever
 * enter the allowlist. This closes the "....//" bypass class generically - "...." on its own
 * already contains ".." as a substring, so no amount of dot/slash creativity smuggles a traversal
 * segment past it (unlike the old strip-once approach, which only failed on a SPECIFIC repeated
 * pattern). Since only the constructor ever writes to the allowlist
 * (Api\Cache::setSession('phpgwapi', Tail::class, ...)), the sinks correctly trusting that
 * allowlist afterward is sound BY CONSTRUCTION, not a missing defense-in-depth layer - what this
 * test actually needs to prove is that the single choke point (the constructor) really does
 * reject every shape of traversal attempt, since nothing downstream will catch one that gets past
 * it.
 */
class TailPathTraversalTest extends LoggedInTest
{
	/**
	 * @return array<string, array{0:string}>
	 */
	public static function traversalPayloadProvider()
	{
		return [
			'plain ../' => ['../../../etc/passwd'],
			'advisory PoC shape: repeated ....// collapsing to ../ after one strip' =>
				['groupdav/demo/....//....//....//var/log/egroupware.log'],
			'traversal in the middle, not just a prefix' => ['groupdav/demo/foo/../../../etc/passwd'],
			'single .. with no slash at all' => ['..'],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('traversalPayloadProvider')]
	public function testConstructorRejectsTraversalPayload(string $payload)
	{
		$this->expectException(\InvalidArgumentException::class);

		new Tail($payload);
	}

	public function testConstructorAcceptsAndAllowlistsALegitimatePlainFilename()
	{
		$filename = 'groupdav/demo/tail-test-'.bin2hex(random_bytes(4)).'.log';

		$tail = new Tail($filename);

		// must not throw when later used via the sink methods, ie. it really did get allowlisted
		$rows = $readonlys = null;
		try {
			$tail->ajax_chunk($filename);
			$this->addToAssertionCount(1);
		}
		catch (\EGroupware\Api\Exception\WrongParameter $e) {
			$this->fail("a legitimately-constructed, non-traversal filename must be usable via the sinks afterward: ".$e->getMessage());
		}
	}

	/**
	 * The sinks (ajax_chunk/ajax_delete/download) only re-check the session allowlist, never any
	 * path-safety condition of their own - proving that's still a hard gate (not accidentally
	 * dropped/weakened) for a filename that was simply never allowlisted at all, independent of
	 * whether it would itself look like a traversal attempt.
	 */
	public function testAjaxChunkRejectsFilenameNeverAllowlisted()
	{
		// a perfectly plain, non-traversal filename - the point is it was never passed to the
		// constructor of THIS Tail instance, so it must not be in its allowlist
		$never_allowlisted = 'groupdav/demo/never-allowlisted-'.bin2hex(random_bytes(4)).'.log';

		$tail = new Tail(); // no filename - nothing added to the allowlist by this instance

		$this->expectException(\EGroupware\Api\Exception\WrongParameter::class);

		$tail->ajax_chunk($never_allowlisted);
	}
}
