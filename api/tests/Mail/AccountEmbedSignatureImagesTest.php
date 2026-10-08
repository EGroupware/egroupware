<?php
/**
 * EGroupware Api: Test Mail\Account::embedSignatureImages()/resizeSignatureImage()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * embedSignatureImages() is protected static - called via Reflection, same pattern
 * AccountJmapUrlTest.php already uses for Mail\Account's own pure helpers. Its own resize step
 * now delegates to Api\MimeMagic::resizeImage() (relocated there, ticket #125961, so mail's
 * inline-body-image compose endpoints can reuse the exact same recipe) - covered directly by
 * MimeMagicResizeImageTest, not re-tested here.
 *
 * Still extends Api\LoggedInTest rather than a bare TestCase, even though neither method touches
 * the DB directly - merely autoloading Mail\Account from a bare TestCase can poison its static
 * $db for the rest of the PHPUnit process (see [[feedback_bare_testcase_poisons_account_db]]).
 *
 * embedSignatureImages()'s own successful "real VFS read" path is NOT covered here - that needs
 * a real mounted VFS (StreamWrapperBase's heavyweight admin-command/DB-user setup), out of scope
 * for what's otherwise a pure regex/host-check/base64 helper. Covered instead: every path that
 * does NOT require a real file to exist (pass-through, foreign host, non-webdav src, and a
 * same-origin webdav url whose file genuinely isn't there - the exact graceful-failure shape a
 * stale/already-deleted VFS reference would hit live).
 */
class AccountEmbedSignatureImagesTest extends Api\LoggedInTest
{
	private $originalHttpHost;

	protected function setUp() : void
	{
		parent::setUp();
		// deterministic "own host" for the same-origin check, independent of whatever this
		// environment's own EGW_URL/Host header happens to be
		$this->originalHttpHost = $_SERVER['HTTP_HOST'] ?? null;
		$_SERVER['HTTP_HOST'] = 'own.example.org';
	}

	protected function tearDown() : void
	{
		if ($this->originalHttpHost === null)
		{
			unset($_SERVER['HTTP_HOST']);
		}
		else
		{
			$_SERVER['HTTP_HOST'] = $this->originalHttpHost;
		}
		parent::tearDown();
	}

	private function embedSignatureImages($html)
	{
		$method = new \ReflectionMethod(Mail\Account::class, 'embedSignatureImages');
		$method->setAccessible(true);
		return $method->invoke(null, $html);
	}

	// --- embedSignatureImages() ---

	public function testNullPassesThrough()
	{
		$this->assertNull($this->embedSignatureImages(null));
	}

	public function testEmptyStringPassesThrough()
	{
		$this->assertSame('', $this->embedSignatureImages(''));
	}

	public function testHtmlWithoutAnyWebdavUrlIsUnchanged()
	{
		$html = '<p>Just text</p><img src="https://own.example.org/some/other/image.png">';
		$this->assertSame($html, $this->embedSignatureImages($html));
	}

	/**
	 * A foreign host's own "/webdav.php" must NEVER be read from our own VFS.
	 */
	public function testForeignHostWebdavUrlIsLeftUnchanged()
	{
		$html = '<img src="https://attacker.example/webdav.php/home/someone/.tmp/x/logo.png">';
		$this->assertSame($html, $this->embedSignatureImages($html));
	}

	/**
	 * No host at all (root-relative) is same-origin by construction - but the referenced file
	 * genuinely does not exist here, so this must fail gracefully (leave the <img> alone) rather
	 * than throw or corrupt the HTML - the exact shape a stale/already-deleted VFS reference hits.
	 */
	public function testSameOriginWebdavUrlWithMissingFileIsLeftUnchanged()
	{
		$html = '<img src="/webdav.php/home/someone/.tmp/does-not-exist-'.uniqid().'/logo.png">';
		$this->assertSame($html, $this->embedSignatureImages($html));
	}

	public function testSameOriginSchemedWebdavUrlWithMissingFileIsLeftUnchanged()
	{
		$html = '<img src="https://own.example.org/webdav.php/home/someone/.tmp/does-not-exist-'.uniqid().'/logo.png">';
		$this->assertSame($html, $this->embedSignatureImages($html));
	}

	public function testOtherHtmlAroundTheImageSurvivesUntouched()
	{
		$html = '<p>Best regards</p><img src="https://attacker.example/webdav.php/x.png"><p>More text</p>';
		$this->assertSame($html, $this->embedSignatureImages($html));
	}
}
