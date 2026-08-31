<?php
/**
 * EGroupware Mail: tests for AttachmentJmap::createAttachmentBlock()'s per-mime-type UI dispatch
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Mail\Ui\AttachmentJmap;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * AttachmentJmap::createAttachmentBlock() is mail's analogue of filemanager_ui::get_rows(): given
 * a flat list of attachment metadata it decides, per mime type, which popup/window a click opens
 * (a message/rfc822 attachment opens mail's own message display; text/calendar and text/(x-)vcard
 * open the calendar/addressbook "add" popups instead of downloading; everything else downloads),
 * and re-derives a bare application/octet-stream mime type from the filename extension. Every
 * attachment fixture here supplies a 'blobId', which routes link-building through the JMAP
 * fetchBlobBytes() target instead of Api\Mail::getAttachmentAccount() - so no real IMAP fetch
 * happens for any of these assertions, only the mime-type dispatch logic itself is exercised.
 *
 * $rowID only needs to resolve to a real, cheap-to-resolve profileID (Mail::splitRowID() below is
 * pure string-parsing plus an in-memory Mail::getInstance() lookup for an already-configured
 * account - no IMAP connection is made merely to obtain a profileID); the folder/uid segments are
 * never dereferenced by the attachment fixtures below since they all carry their own 'blobId'.
 *
 * The EGW_USER (doc/phpunit.xml) test account has no real mail account of its own in general -
 * nothing provisions one, and CI's docker-compose stack runs no IMAP/SMTP server at all - so
 * relying on Mail\Account::get_default_acc_id() made every test in this class fail with
 * "Account not found!" the moment that assumption didn't hold, as it did not in CI. A throwaway
 * account row is created once for the class instead: acc_smtp_type=Smtp::class makes
 * Mail\Account::is_imap() return true without ever trying to connect (its own short-circuit for
 * that type), so Mail::getInstance()'s account lookup succeeds without any real IMAP/SMTP
 * server - matching this class's own "no IMAP connection" guarantee even where no mail server
 * exists at all.
 */
class CreateAttachmentBlockTest extends \EGroupware\Api\AppTest
{
	private static ?int $fixtureAccId = null;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		self::$fixtureAccId = (int)Api\Mail\Account::write([
			'acc_name'          => 'phpunit-CreateAttachmentBlockTest-fixture',
			'acc_imap_host'     => 'phpunit-fixture.invalid',
			'acc_imap_username' => 'phpunit-fixture',
			'acc_imap_type'     => Api\Mail\Imap::class,
			'acc_smtp_type'     => Api\Mail\Smtp::class,
			'acc_smtp_host'     => 'phpunit-fixture.invalid',
			'account_id'        => $GLOBALS['egw_info']['user']['account_id'],
			'ident_realname'    => 'PHPUnit Fixture',
			'ident_email'       => 'phpunit-fixture@example.invalid',
		])['acc_id'];
	}

	public static function tearDownAfterClass() : void
	{
		if (self::$fixtureAccId)
		{
			Api\Mail\Account::delete(self::$fixtureAccId);
			self::$fixtureAccId = null;
		}
		parent::tearDownAfterClass();
	}

	private function rowID() : string
	{
		return 'mail::'.$GLOBALS['egw_info']['user']['account_id'].'::'.self::$fixtureAccId.'::'.base64_encode('INBOX').'::1';
	}

	private function attachment(string $mimeType, string $name, array $overrides = array()) : array
	{
		return array_merge(array(
			'mimeType'   => $mimeType,
			'name'       => $name,
			'partID'     => '2',
			'blobId'     => 'phpunit-blob-'.bin2hex(random_bytes(4)),
			'size'       => 123,
			'is_winmail' => false,
			'smime_type' => null,
		), $overrides);
	}

	private function block(array $attachments) : array
	{
		return AttachmentJmap::createAttachmentBlock($attachments, $this->rowID(), 1, 'INBOX');
	}

	/**
	 * Pass criteria: a message/rfc822 attachment's window name and click handler target mail's
	 * own displayMessage action, not the generic getAttachment download action every other mime
	 * type falls through to.
	 */
	public function testForwardedMessageOpensMailDisplayNotDownload()
	{
		$result = $this->block(array($this->attachment('message/rfc822', 'fwd.eml')));

		$this->assertStringStartsWith('displayMessage_', $result[0]['windowName'],
			'message/rfc822 must open via mail_ui.displayMessage, not the generic getAttachment download link');
	}

	/**
	 * Regression test for a bug found live 2026-08-31: every attachment fixture here carries a real
	 * 'blobId' (see this class' own docblock), which routes createAttachmentBlock() into setting a
	 * 'mime_data' token (Api\Link::set_data(), for AttachmentJmap::fetchBlobBytes()) UNCONDITIONALLY
	 * - before this fix, that token then took priority over the correctly-built 'mime_url'
	 * (mail_ui::displayMessage) client-side (Et2Description._handleClick()'s own
	 * "mimeData || href" preference), and egw_open.ts's open_link() - given a `mime` type alongside
	 * a bare, non-URL mime_data token - resolved it through mail_hooks.inc.php's own, completely
	 * UNRELATED message/rfc822 registry entry (mail.mail_ui.importMessageFromVFS2DraftAndDisplay,
	 * meant for importing a VFS-stored .eml file) instead. The PRECEDING test only ever checked
	 * 'windowName' (set unconditionally, regardless of this bug) - never actually checking which of
	 * mime_url/mime_data survives, so it never caught this at all.
	 *
	 * Pass criteria: message/rfc822 (and, same "dedicated special popup" reasoning, vcard/calendar)
	 * must always end up with a real 'mime_url' and NO 'mime_data', even when a blobId is present -
	 * never routed through the generic, type-keyed mime_data mechanism.
	 */
	public function testForwardedMessageNeverGetsMimeDataToken()
	{
		$result = $this->block(array($this->attachment('message/rfc822', 'fwd.eml')));

		$this->assertArrayNotHasKey('mime_data', $result[0],
			'message/rfc822 must never carry a mime_data token, even with a real blobId present');
		$this->assertStringContainsString('mail_ui.displayMessage', $result[0]['mime_url'] ?? '',
			'message/rfc822 must have a real mime_url pointing at mail_ui.displayMessage');
	}

	/**
	 * Pass criteria: a text/calendar attachment opens a calendar-specific popup (windowName
	 * prefixed 'displayEvent_'), distinguishing it from the generic download fallback and from
	 * the vCard branch below.
	 */
	public function testIcsAttachmentOpensCalendarEventPopup()
	{
		$result = $this->block(array($this->attachment('text/calendar', 'invite.ics')));

		$this->assertStringStartsWith('displayEvent_', $result[0]['windowName'],
			'text/calendar must open the calendar event popup, not a plain download');
	}

	/**
	 * Pass criteria: a text/vcard attachment opens an addressbook-specific popup (windowName
	 * prefixed 'displayContact_'), not the calendar popup and not a plain download.
	 */
	public function testVcardAttachmentOpensAddressbookContactPopup()
	{
		$result = $this->block(array($this->attachment('text/vcard', 'contact.vcf')));

		$this->assertStringStartsWith('displayContact_', $result[0]['windowName'],
			'text/vcard must open the addressbook contact popup, not a plain download');
	}

	/**
	 * Pass criteria: a .vcf file that the mail server mislabeled as text/plain (common for
	 * clients that don't set a precise Content-Type) still gets promoted to the vCard popup
	 * branch - the extension-based re-sniff must override the mislabeled mime type.
	 */
	public function testMislabeledPlainTextVcfStillOpensAsVcard()
	{
		$result = $this->block(array($this->attachment('text/plain', 'contact.vcf')));

		$this->assertStringStartsWith('displayContact_', $result[0]['windowName'],
			'a .vcf file mislabeled text/plain must still be promoted to the vCard popup via its extension');
	}

	/**
	 * Pass criteria: a generic application/octet-stream attachment (the "browser/server has no
	 * idea what this is" mime type) gets its real type re-derived from the filename extension,
	 * and consequently falls through to the generic download action rather than a popup - proving
	 * the octet-stream re-derivation actually feeds back into the dispatch switch, not just a
	 * cosmetic label.
	 */
	public function testOctetStreamTypeIsRederivedFromExtension()
	{
		$result = $this->block(array($this->attachment('application/octet-stream', 'report.pdf')));

		$this->assertSame('application/pdf', $result[0]['type'],
			'application/octet-stream must be re-derived to the real mime type from the filename extension');
	}

	/**
	 * Pass criteria: an unremarkable mime type (a zip archive) falls through to the default
	 * branch, which (unlike every other branch) never sets 'windowName' at all - so on its own,
	 * as the only attachment, it must come back null rather than any popup window name.
	 */
	public function testUnrecognizedMimeTypeFallsBackToPlainDownload()
	{
		$result = $this->block(array($this->attachment('application/zip', 'archive.zip')));

		$this->assertNull($result[0]['windowName'] ?? null,
			"an unremarkable mime type's default branch never sets windowName - it must not carry any popup name");
	}

	/**
	 * KNOWN BUG, documented not fixed (see mail/src/Ui/AttachmentJmap.php:42 createAttachmentBlock()):
	 * $windowName (and $reg2) are set inside the switch's popup-branch cases but never reset at
	 * the top of the foreach loop (only $mode is explicitly reset, per the source's own "reset
	 * mode array" comment). So a plain-download attachment (eg. a zip) immediately following a
	 * popup-type attachment (eg. text/calendar) silently inherits the *previous* attachment's
	 * 'displayEvent_...'/'displayContact_...' windowName instead of null - meaning
	 * egw.openPopup()'s named-window reuse could point the "download" link's popup call at the
	 * wrong, already-open window slot. The default branch's actual link target ($linkView) is
	 * still correct; only the window name used to open/reuse a window is affected.
	 *
	 * This test locks in the CURRENT (buggy) behaviour as a regression guard, not as an
	 * endorsement - if a future fix resets $windowName per iteration, this assertion should be
	 * updated to assertNull() at that point rather than treated as a regression.
	 */
	public function testDownloadAttachmentAfterPopupAttachmentInheritsStaleWindowName()
	{
		$result = $this->block(array(
			$this->attachment('text/calendar', 'invite.ics'),
			$this->attachment('application/zip', 'archive.zip'),
		));

		$this->assertStringStartsWith('displayEvent_', $result[0]['windowName'],
			'the ics entry must get its own calendar popup');
		$this->assertStringStartsWith('displayEvent_', $result[1]['windowName'] ?? '',
			'documents the known bug: the zip entry currently inherits the previous attachment\'s '.
			'stale windowName instead of getting none - see class docblock');
	}

	/**
	 * Pass criteria: when the filemanager app is not enabled for the current user, every
	 * attachment is flagged no_vfs so the client hides "save to filemanager" - mirrors
	 * FilemanagerMimeTypeTest::testGetEditorLinkFalsyWithoutCollaboraApp's approach of directly
	 * toggling $GLOBALS['egw_info']['user']['apps'] for the duration of one assertion.
	 */
	public function testNoVfsFlagSetWithoutFilemanagerApp()
	{
		$had_filemanager = array_key_exists('filemanager', $GLOBALS['egw_info']['user']['apps']);
		$prev_value = $GLOBALS['egw_info']['user']['apps']['filemanager'] ?? null;
		unset($GLOBALS['egw_info']['user']['apps']['filemanager']);

		try
		{
			$result = $this->block(array($this->attachment('application/pdf', 'report.pdf')));
			$this->assertTrue($result[0]['no_vfs'] ?? false,
				'no_vfs must be set when the filemanager app is not enabled for the current user');
		}
		finally
		{
			if ($had_filemanager)
			{
				$GLOBALS['egw_info']['user']['apps']['filemanager'] = $prev_value;
			}
		}
	}
}
