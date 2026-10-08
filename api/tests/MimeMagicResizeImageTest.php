<?php
/**
 * EGroupware Api: Test MimeMagic::resizeImage()
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\MimeMagic;
use PHPUnit\Framework\TestCase;

/**
 * Shared GD resize helper, originally Mail\Account::resizeSignatureImage() (still covered
 * indirectly via AccountEmbedSignatureImagesTest), relocated here as a public, app-agnostic
 * utility so mail's inline-body-image compose endpoints (ticket #125961) can reuse the exact
 * same recipe.
 */
class MimeMagicResizeImageTest extends TestCase
{
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
		[$result, $mime] = MimeMagic::resizeImage($bytes, 'image/png');
		$this->assertSame($bytes, $result, 'width below the threshold must not be touched at all');
		$this->assertSame('image/png', $mime);
	}

	public function testWidePngIsResizedAndKeepsPngMimeAndAlpha()
	{
		$bytes = $this->makePng(640, 320, true);
		[$result, $mime] = MimeMagic::resizeImage($bytes, 'image/png', 320);

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
		[$result, $mime] = MimeMagic::resizeImage($bytes, 'image/gif', 320);

		$this->assertSame('image/jpeg', $mime);
		$resized = imagecreatefromstring($result);
		$this->assertNotFalse($resized);
		$this->assertSame(320, imagesx($resized));
		imagedestroy($resized);
	}

	public function testUndecodableBytesAreReturnedUnchangedRatherThanDropped()
	{
		$garbage = 'this is not an image '.random_bytes(16);
		[$result, $mime] = MimeMagic::resizeImage($garbage, 'image/png');
		$this->assertSame($garbage, $result);
		$this->assertSame('image/png', $mime);
	}
}
