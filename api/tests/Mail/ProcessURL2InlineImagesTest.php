<?php
/**
 * EGroupware Api: Test Mail::processURL2InlineImages()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail;
use EGroupware\Api\Mailer;
use PHPUnit\Framework\TestCase;

/**
 * Ticket #125961: a `webdav.php`-referenced `<img>` must never be auto-converted into a CID
 * attachment here any more - such a URL only ever works for whoever has this exact account's
 * own session, which used to mean an embedded one broke the moment someone forwarded it. Mail
 * compose no longer produces a `webdav.php` URL at all (it inserts a `data:` URI directly), so
 * one reaching this far is leftover/foreign content - left as a plain, unconverted URL, same as
 * any other external image reference.
 *
 * A `data:` URI must still convert to a real CID MIME attachment, unconditionally (quoted
 * content or not) - this is still needed for the new compose-time insertion path.
 *
 * `new Mailer(false)` skips all account/SMTP setup entirely ("used to parse mails, not sending
 * them") - exactly what a pure MIME-part-bookkeeping test like this needs, no real account/
 * session dependency.
 */
class ProcessURL2InlineImagesTest extends TestCase
{
	public function testWebdavUrlIsLeftCompletelyUntouched()
	{
		$mailer = new Mailer(false);
		$html = '<img src="/webdav.php/home/ralf/.tmp/abc123/logo.png">';

		$result = Mail::processURL2InlineImages($mailer, $html, null);

		$this->assertSame('<img src="/webdav.php/home/ralf/.tmp/abc123/logo.png">', $html,
			'a webdav.php url must never be rewritten to a cid: reference any more');
		$this->assertCount(1, $result, 'the loop still produces one (empty/unresolved) attachment-data entry');
		$this->assertSame('', $result[0]['file'], 'nothing was ever fetched/embedded for it');
	}

	/**
	 * Same guarantee for a webdav.php url that WOULD previously have been recognised as quoted
	 * content (inside a <blockquote>) - now identical to the non-quoted case, since there is no
	 * quoted-vs-not distinction left to make at all.
	 */
	public function testWebdavUrlInsideBlockquoteIsAlsoLeftUntouched()
	{
		$mailer = new Mailer(false);
		$html = '<blockquote type="cite"><img src="/webdav.php/home/someone/.tmp/x/logo.png"></blockquote>';
		$before = $html;

		Mail::processURL2InlineImages($mailer, $html, null);

		$this->assertSame($before, $html);
	}

	public function testDataUriStillConvertsToARealCidAttachment()
	{
		$mailer = new Mailer(false);
		// 1x1 transparent PNG
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
		$html = '<img src="data:image/png;base64,'.base64_encode($png).'">';

		$result = Mail::processURL2InlineImages($mailer, $html, null);

		$this->assertMatchesRegularExpression('#^<img src="cid:[0-9a-f]{32}">$#', $html,
			'a data: uri must still be rewritten to a cid: reference');
		$this->assertCount(1, $result);
		$this->assertStringStartsWith('image/', $result[0]['type']);
		$this->assertNotSame('', $result[0]['file'], 'a real temp file must have been written');
		@unlink($result[0]['file']);
	}

	public function testPlainExternalUrlIsLeftUntouchedToo()
	{
		$mailer = new Mailer(false);
		$html = '<img src="https://example.com/some/other/image.png">';
		$before = $html;

		Mail::processURL2InlineImages($mailer, $html, null);

		$this->assertSame($before, $html);
	}

	public function testNoImagesAtAllReturnsNull()
	{
		$mailer = new Mailer(false);
		$html = '<p>Just text, no images</p>';

		$this->assertNull(Mail::processURL2InlineImages($mailer, $html, null));
	}
}
