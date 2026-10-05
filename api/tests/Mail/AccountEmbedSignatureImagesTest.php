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
 * Both methods under test are protected static - called via Reflection, same pattern
 * AccountJmapUrlTest.php already uses for Mail\Account's own pure helpers.
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

	private function resizeSignatureImage(string $bytes, string $mime, int $max_w=320) : array
	{
		$method = new \ReflectionMethod(Mail\Account::class, 'resizeSignatureImage');
		$method->setAccessible(true);
		return $method->invoke(null, $bytes, $mime, $max_w);
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
	 * A foreign host's own "/webdav.php" must NEVER be read from our own VFS - same reasoning
	 * Mail::processURL2InlineImages() already documents for the identical check.
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

	// --- resizeSignatureImage() ---

	private function makePng(int $width, int $height, bool $withAlpha) : string
	{
		$image = imagecreatetruecolor($width, $height);
		if ($withAlpha)
		{
			imagealphablending($image, false);
			imagesavealpha($image, true);
			// half-transparent red, index 64 of 127 (0 = opaque, 127 = fully transparent)
			$color = imagecolorallocatealpha($image, 255, 0, 0, 64);
		}
		else
		{
			$color = imagecolorallocate($image, 255, 0, 0);
		}
		imagefill($image, 0, 0, $color);
		ob_start();
		imagepng($image);
		$bytes = ob_get_clean();
		imagedestroy($image);
		return $bytes;
	}

	public function testAlreadyNarrowImageIsReturnedUnchanged()
	{
		$bytes = $this->makePng(100, 50, false);
		[$result, $mime] = $this->resizeSignatureImage($bytes, 'image/png');
		$this->assertSame($bytes, $result, 'width below the threshold must not be touched at all');
		$this->assertSame('image/png', $mime);
	}

	public function testWidePngIsResizedAndKeepsPngMimeAndAlpha()
	{
		$bytes = $this->makePng(640, 320, true);
		[$result, $mime] = $this->resizeSignatureImage($bytes, 'image/png', 320);

		$this->assertSame('image/png', $mime, 'a PNG source must stay PNG, not be forced to JPEG');
		$this->assertNotSame($bytes, $result);

		$resized = imagecreatefromstring($result);
		$this->assertNotFalse($resized, 'resized output must still decode as a real image');
		$this->assertSame(320, imagesx($resized));
		$this->assertSame(160, imagesy($resized), 'aspect ratio must be preserved');

		// alpha channel must survive - a pixel from the filled (half-transparent) area
		$rgba = imagecolorat($resized, 10, 10);
		$alpha = ($rgba >> 24) & 0x7F;
		$this->assertGreaterThan(0, $alpha, 'resizing a transparent PNG must not flatten it to fully opaque');
		imagedestroy($resized);
	}

	/**
	 * The png/not-png branch is driven by the $mime PARAMETER, not re-sniffed from the bytes -
	 * deliberately exercised with real PNG bytes here to isolate just that branching decision
	 * (a real non-PNG fixture would only re-test GD's own format handling, not this method's logic).
	 */
	public function testNonPngSourceIsConvertedToJpeg()
	{
		$bytes = $this->makePng(640, 320, false);
		[$result, $mime] = $this->resizeSignatureImage($bytes, 'image/gif', 320);

		$this->assertSame('image/jpeg', $mime);
		$resized = imagecreatefromstring($result);
		$this->assertNotFalse($resized);
		$this->assertSame(320, imagesx($resized));
		imagedestroy($resized);
	}

	public function testUndecodableBytesAreReturnedUnchangedRatherThanDropped()
	{
		$garbage = 'this is not an image '.random_bytes(16);
		[$result, $mime] = $this->resizeSignatureImage($garbage, 'image/png');
		$this->assertSame($garbage, $result);
		$this->assertSame('image/png', $mime);
	}
}
