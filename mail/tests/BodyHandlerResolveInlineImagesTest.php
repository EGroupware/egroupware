<?php
/**
 * EGroupware Mail: Test Ui\BodyHandler's inline (cid:) image resolution
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * doc/ai/projects/mail-test-coverage.md's priority-4 entry: BodyHandler.php (extracted from Ui,
 * see its own class docblock) had zero coverage - genuinely real risk here, since a broken regex
 * for any of the 4 inline-image forms (plain/src/url/background) would leave real emails showing
 * broken image icons instead of their actual inline content.
 *
 * resolveInlineImageByType() accepts an explicit $_link_callback, sidestepping its own default
 * callback's DB/IMAP dependency (Mail::getInstance()->getAttachmentByCID(), only reached anyway
 * when the callback returns an empty URL - a data-uri fallback, not exercised here) entirely - so
 * most of this is tested with a bare TestCase-shaped callback. resolveInlineImages()'s own
 * html-vs-plain dispatch has no such override, so those few tests use the real default callback
 * (Api\Egw::link()), needing Api\LoggedInTest's real bootstrap - the whole class extends it for
 * consistency (mixing LoggedInTest with a bare TestCase in one phpunit invocation is a known
 * process-shared-static-state hazard, see project memory).
 */
class BodyHandlerResolveInlineImagesTest extends Api\LoggedInTest
{
	private function linkCallback() : callable
	{
		return fn($cid) => 'https://example.org/img/'.rawurlencode($cid);
	}

	public function testPlainTypeReplacesBracketedCidWithAnImgTag()
	{
		$result = BodyHandler::resolveInlineImageByType('[cid:image1]', 'INBOX', '42', '1', 'plain', $this->linkCallback());

		$this->assertSame('<img src="https://example.org/img/image1" />', $result);
	}

	public function testSrcTypeReplacesDoubleQuotedCidSrcAttribute()
	{
		$result = BodyHandler::resolveInlineImageByType('<img src="cid:image1">', 'INBOX', '42', '1', 'src', $this->linkCallback());

		$this->assertSame('<img src="https://example.org/img/image1">', $result);
	}

	public function testSrcTypeReplacesSingleQuotedCidSrcAttribute()
	{
		$result = BodyHandler::resolveInlineImageByType("<img src='cid:image1'>", 'INBOX', '42', '1', 'src', $this->linkCallback());

		$this->assertSame('<img src="https://example.org/img/image1">', $result);
	}

	/** Only the 'src' type URL-decodes the matched CID before handing it to the callback. */
	public function testSrcTypeUrlDecodesTheCidBeforeCallingTheLinkCallback()
	{
		$result = BodyHandler::resolveInlineImageByType('<img src="cid:foo%40bar">', 'INBOX', '42', '1', 'src', $this->linkCallback());

		$this->assertSame('<img src="https://example.org/img/foo%40bar">', $result,
			"foo%40bar decodes to foo@bar, then gets re-encoded by the test callback back to foo%40bar - round-trip proves decoding happened");
	}

	public function testUrlTypeReplacesCssUrlFunction()
	{
		$result = BodyHandler::resolveInlineImageByType('background-image: url(cid:image1);', 'INBOX', '42', '1', 'url', $this->linkCallback());

		$this->assertSame('background-image: url(https://example.org/img/image1);', $result);
	}

	public function testBackgroundTypeReplacesBackgroundAttribute()
	{
		$result = BodyHandler::resolveInlineImageByType('<td background="cid:image1">', 'INBOX', '42', '1', 'background', $this->linkCallback());

		$this->assertSame('<td background="https://example.org/img/image1">', $result);
	}

	public function testEachOccurrenceIsResolvedWithItsOwnCid()
	{
		$result = BodyHandler::resolveInlineImageByType('<img src="cid:one"><img src="cid:two">', 'INBOX', '42', '1', 'src', $this->linkCallback());

		$this->assertSame('<img src="https://example.org/img/one"><img src="https://example.org/img/two">', $result);
	}

	public function testBodyWithNoMatchingCidReferenceIsReturnedUnchanged()
	{
		$result = BodyHandler::resolveInlineImageByType('<p>no images here</p>', 'INBOX', '42', '1', 'src', $this->linkCallback());

		$this->assertSame('<p>no images here</p>', $result);
	}

	// --- resolveInlineImages() dispatch (real default callback, needs Api\Egw::link()) ---

	/**
	 * Regression test for a real bug found live 2026-09-10 (ralf: a screenshot showing "CreateObject(Ui)
	 * file .../class.Ui.inc.php not found!" after clicking an attached .eml's inline image): the
	 * default link callback's menuaction was the bare, un-namespaced 'mail.Ui.displayImage' instead
	 * of the fully-namespaced 'mail.EGroupware\Mail\Ui.displayImage' every other menuaction in this
	 * codebase uses for this class - the dispatcher then tried (and failed) to load the classic
	 * mail/inc/class.Ui.inc.php, which was retired long ago. Asserting on the exact menuaction
	 * string (not just "displayImage" appearing somewhere) is what actually catches this - the
	 * previous, looser assertion here passed even with the bug present.
	 */
	public function testResolveInlineImagesPlainMessageTypeOnlyAppliesThePlainForm()
	{
		$result = BodyHandler::resolveInlineImages('[cid:image1]', 'INBOX', '42', '1', 'plain');

		$this->assertStringStartsWith('<img src="', $result);
		$this->assertStringContainsString('menuaction=mail.EGroupware%5CMail%5CUi.displayImage', $result);
	}

	public function testResolveInlineImagesHtmlMessageTypeAppliesSrcUrlAndBackgroundForms()
	{
		$body = '<img src="cid:one"><div style="background:url(cid:two);"><td background="cid:three">';

		$result = BodyHandler::resolveInlineImages($body, 'INBOX', '42', '1', 'html');

		$this->assertStringNotContainsString('cid:one', $result);
		$this->assertStringNotContainsString('cid:two', $result);
		$this->assertStringNotContainsString('cid:three', $result);
		$this->assertSame(3, substr_count($result, 'menuaction=mail.EGroupware%5CMail%5CUi.displayImage'),
			"all three inline forms must have been resolved, each via the correctly-namespaced menuaction");
	}
}
