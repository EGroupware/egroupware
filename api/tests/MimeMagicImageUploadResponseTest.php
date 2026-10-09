<?php
/**
 * EGroupware Api: test MimeMagic::imageUploadResponse() (ticket #125961)
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\MimeMagic;
use PHPUnit\Framework\TestCase;

/**
 * Shared by every "paste/drop/insert-from-VFS an inline image" endpoint that wants a
 * self-contained data: URI instead of a VFS/webdav.php reference - Mail\Compose's
 * ajax_uploadInlineImage()/ajax_resizeVfsImageForCompose() (mail body images) and
 * Mail\Account's ajax_uploadSignatureImage() (signature images). The $_FILES/VFS-read/
 * exit-ending methods themselves aren't unit-tested directly (same reason
 * Api\Etemplate\Widget\Vfs::ajax_htmlarea_upload() isn't either - they end in echo+exit, which
 * would terminate the whole PHPUnit process) - this covers the pure byte-in/array-out logic
 * they all delegate to.
 */
class MimeMagicImageUploadResponseTest extends TestCase
{
	const THRESHOLD = 32768;

	private function call(string $bytes) : array
	{
		return MimeMagic::imageUploadResponse($bytes, self::THRESHOLD);
	}

	private function makePng(int $width, int $height) : string
	{
		$image = imagecreatetruecolor($width, $height);
		imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
		ob_start();
		imagepng($image);
		$bytes = ob_get_clean();
		imagedestroy($image);
		return $bytes;
	}

	public function testSmallImageIsReturnedAsDataUriWithoutResizing()
	{
		$bytes = $this->makePng(50, 50);

		$result = $this->call($bytes);

		$this->assertStringStartsWith('data:image/png;base64,', $result['location']);
		$this->assertSame('data:image/png;base64,'.base64_encode($bytes), $result['location'],
			'below the resize threshold, the original bytes must be carried through unchanged');
	}

	public function testOversizedImageIsResizedBeforeEncoding()
	{
		// random noise, not a solid fill - a solid-colour PNG compresses far below the resize
		// threshold regardless of pixel dimensions, so this needs actual high-entropy content to
		// genuinely cross THRESHOLD in byte size
		$image = imagecreatetruecolor(640, 640);
		mt_srand(1);
		for ($x = 0; $x < 640; $x += 2)
		{
			for ($y = 0; $y < 640; $y += 2)
			{
				imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
			}
		}
		ob_start();
		imagepng($image, null, 0); // no compression, keeps the noise large
		$bytes = ob_get_clean();
		imagedestroy($image);
		$this->assertGreaterThan(self::THRESHOLD, strlen($bytes), 'fixture must actually exceed the threshold');

		$result = $this->call($bytes);

		$this->assertStringStartsWith('data:image/', $result['location']);
		$this->assertNotSame('data:image/png;base64,'.base64_encode($bytes), $result['location'],
			'an oversized image must be resized, not passed through as-is');
	}

	/**
	 * Mail\Compose::ajax_uploadInlineImage() (ticket #126241) passes its own $max_w, read from
	 * the 'inlineImageMaxWidth' preference, through to here - confirm it actually reaches
	 * MimeMagic::resizeImage() instead of being silently ignored in favour of the method's own
	 * 320px default.
	 */
	public function testCustomMaxWidthIsForwardedToResize()
	{
		$image = imagecreatetruecolor(640, 640);
		mt_srand(1);
		for ($x = 0; $x < 640; $x += 2)
		{
			for ($y = 0; $y < 640; $y += 2)
			{
				imagesetpixel($image, $x, $y, imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
			}
		}
		ob_start();
		imagepng($image, null, 0);
		$bytes = ob_get_clean();
		imagedestroy($image);
		$this->assertGreaterThan(self::THRESHOLD, strlen($bytes), 'fixture must actually exceed the threshold');

		$result = MimeMagic::imageUploadResponse($bytes, self::THRESHOLD, 100);

		$data = base64_decode(substr($result['location'], strpos($result['location'], 'base64,') + strlen('base64,')));
		$resized = imagecreatefromstring($data);
		$this->assertSame(100, imagesx($resized), 'the caller-supplied max_w must be used, not the 320px default');
		imagedestroy($resized);
	}

	public function testNonImageBytesAreRejected()
	{
		$result = $this->call('this is not an image at all');

		$this->assertSame(['location' => 'Not an image'], $result);
	}

	public function testEmptyBytesAreRejected()
	{
		$result = $this->call('');

		$this->assertSame(['location' => 'Not an image'], $result);
	}
}
