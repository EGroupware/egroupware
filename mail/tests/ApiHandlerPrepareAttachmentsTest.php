<?php
/**
 * EGroupware Mail: unit tests for ApiHandler::prepareAttachments()
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Mail\ApiHandler;

/**
 * prepareAttachments() coverage for both REST attachment flows: opening a compose window (POST
 * .../compose, $compose=true) and sending directly (POST /mail, $compose=false), each with 0, 1
 * or several attachment ids - the "/mail/attachments/<token>" shape a prior POST
 * /mail/attachments/ upload returns (storeAttachment()). Needs a real DB/VFS session
 * (Api\Vfs::mime_content_type() calls resolve_url_symlinks() unconditionally, even for a bare
 * local temp-file path with no VFS scheme at all), unlike ApiHandlerJmapRestTest.php's bare
 * TestCase siblings - a real temp file matching storeAttachment()'s own naming convention stands
 * in for an actual upload, VFS-path attachments aren't covered here (would need a real mail
 * account's VFS content, not just a live session).
 *
 * Regression coverage (found live, ticket "customer on 26 can't add attachments via REST API"):
 * compose mode used to put the token's local path/name into $ret['file'][]/$ret['name'][] - a
 * shape nothing client-side has read since the classic mail_compose.inc.php was replaced by the
 * JMAP-native compose bootstrap (MailCompose.applyPresetFiles()/applyPresetAttachmentUrls()/
 * applyPresetAttachmentContent() only understand "files"/"attachmentUrls"/"attachmentContents")
 * - so an attachment uploaded and then referenced to open a compose window silently never
 * appeared in it. The send ($compose=false) branch already used the correct $ret['attachments'][]
 * shape throughout and is covered here purely as a regression guard against the compose-mode fix
 * accidentally touching it too.
 *
 * A follow-up fix (tickets #125601/#125621) then moved compose mode off inlining the token's full
 * content as base64 into "attachmentContents" - fine for calendar's own freshly-generated .ics
 * (never staged anywhere, nothing else to give it), but a real-world REST-uploaded attachment
 * (found live: a customer's 128KB PDF) made the preset - which travels a server push and then a
 * browser form-POST back to compose.php - large enough to risk silent truncation somewhere along
 * that path. The token is a stable server-side reference (ApiHandler::get()'s own
 * '/mail/attachments/<token>' branch already serves it back) - a lightweight "attachmentUrls"
 * reference is enough, same principle as "files"'s VFS-path reference; the popup fetches and
 * uploads it itself (MailCompose.applyPresetAttachmentUrls()), the same JMAP-blob pipeline a
 * user's own drag-and-drop attach already uses.
 */
class ApiHandlerPrepareAttachmentsTest extends Api\LoggedInTest
{
	private function invokeApiHandler(string $method, array $args)
	{
		$reflection = new \ReflectionMethod(ApiHandler::class, $method);
		$reflection->setAccessible(true);
		return $reflection->invoke(null, ...$args);
	}

	/**
	 * @return array{0: string[], 1: string[]} [tokens, temp-file paths] - caller unlinks the temp
	 *  files itself
	 */
	private function makeAttachmentTokens(array $namesAndContents) : array
	{
		$tokens = $paths = [];
		foreach ($namesAndContents as $name => $content)
		{
			$path = tempnam($GLOBALS['egw_info']['server']['temp_dir'], "attach--$name--");
			file_put_contents($path, $content);
			$paths[] = $path;
			$tokens[] = '/mail/attachments/'.substr(basename($path), 8);
		}
		return [$tokens, $paths];
	}

	/**
	 * Confirmed failing pre-fix: asserted $result['file'][0] === $path instead.
	 */
	public function testComposeModeWithOneTokenProducesAttachmentUrls()
	{
		$content = "hello attachment\x00binary\xffbytes";
		[$tokens, $paths] = $this->makeAttachmentTokens(['report.pdf' => $content]);
		try
		{
			$result = $this->invokeApiHandler('prepareAttachments', [$tokens, null, null, null, true]);

			$this->assertArrayNotHasKey('file', $result, 'must not use the dead classic file[]/name[] shape');
			$this->assertArrayNotHasKey('name', $result);
			$this->assertArrayNotHasKey('attachmentContents', $result,
				'a real attachment must be referenced, not inlined as base64 - see the class docblock');
			$this->assertCount(1, $result['attachmentUrls'] ?? []);
			$this->assertSame('report.pdf', $result['attachmentUrls'][0]['name']);
			// a fully-qualified URL (via groupdav.php), NOT the bare "/mail/attachments/<token>"
			// pattern this method itself matches against - that bare form is only a server-side
			// matching pattern, not something a browser's fetch() can route through the REST
			// dispatch at all (found live 2026-09-29: it hit the site root instead)
			$this->assertStringEndsWith('/groupdav.php'.$tokens[0], $result['attachmentUrls'][0]['url'],
				'must be a fully-qualified, fetchable URL referencing the same token the upload step returned');
			$this->assertSame(strlen($content), $result['attachmentUrls'][0]['size']);
			$this->assertSame('attach', $result['filemode']);
		}
		finally
		{
			array_map('unlink', $paths);
		}
	}

	/**
	 * Regression coverage for a live follow-up report: "clicking on the attachment ... does NOT
	 * work, while it works when ... attaching an image [through the UI]" - Api\Vfs::
	 * mime_content_type()'s very first step (resolve_url_symlinks()) returns null for an ordinary
	 * path outside the VFS root (a temp_dir path always is), so it always short-circuited
	 * straight to `false` - the attachment reached the compose window fine (the tests above), but
	 * with no usable type at all, breaking whatever client-side code (an image preview) branches
	 * on it.
	 */
	public function testComposeModeDetectsARealMimeTypeNotVfsFalse()
	{
		[$tokens, $paths] = $this->makeAttachmentTokens(['note.txt' => "plain text content\n"]);
		try
		{
			$result = $this->invokeApiHandler('prepareAttachments', [$tokens, null, null, null, true]);

			$this->assertNotFalse($result['attachmentUrls'][0]['type']);
			$this->assertSame('text/plain', $result['attachmentUrls'][0]['type']);
		}
		finally
		{
			array_map('unlink', $paths);
		}
	}

	public function testSendModeWithOneTokenProducesAttachmentsArray()
	{
		$content = 'plain send content';
		[$tokens, $paths] = $this->makeAttachmentTokens(['note.txt' => $content]);
		try
		{
			$result = $this->invokeApiHandler('prepareAttachments', [$tokens, null, null, null, false]);

			$this->assertArrayNotHasKey('attachmentContents', $result);
			$this->assertArrayNotHasKey('attachmentUrls', $result);
			$this->assertCount(1, $result['attachments'] ?? []);
			$this->assertSame('note.txt', $result['attachments'][0]['name']);
			$this->assertSame($paths[0], $result['attachments'][0]['file']);
			$this->assertSame(strlen($content), $result['attachments'][0]['size']);
		}
		finally
		{
			array_map('unlink', $paths);
		}
	}

	public function testThrowsForAnUnknownToken()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('NOT found');

		$this->invokeApiHandler('prepareAttachments',
			[['/mail/attachments/does-not-exist--abcdef'], null, null, null, true]);
	}

	/**
	 * Zero attachments (a plain compose-open/send with nothing attached) must stay a no-op - $ret
	 * itself must stay empty, not just e.g. "attachmentUrls empty" (the caller's array_filter()
	 * over the preset relies on this to drop the key entirely, same as classic compose() calls
	 * with no attachments never carried an empty "attachments" key either).
	 */
	public function testComposeModeWithNoAttachmentsReturnsEmptyArray()
	{
		$result = $this->invokeApiHandler('prepareAttachments', [[], null, null, null, true]);

		$this->assertSame([], $result);
	}

	public function testSendModeWithNoAttachmentsReturnsEmptyArray()
	{
		$result = $this->invokeApiHandler('prepareAttachments', [[], null, null, null, false]);

		$this->assertSame([], $result);
	}

	public function testComposeModeWithMultipleTokensKeepsThemInOrder()
	{
		[$tokens, $paths] = $this->makeAttachmentTokens(['first.txt' => 'AAA', 'second.txt' => 'BBB']);
		try
		{
			$result = $this->invokeApiHandler('prepareAttachments', [$tokens, null, null, null, true]);

			$this->assertCount(2, $result['attachmentUrls']);
			$this->assertSame('first.txt', $result['attachmentUrls'][0]['name']);
			$this->assertStringEndsWith('/groupdav.php'.$tokens[0], $result['attachmentUrls'][0]['url']);
			$this->assertSame('second.txt', $result['attachmentUrls'][1]['name']);
			$this->assertStringEndsWith('/groupdav.php'.$tokens[1], $result['attachmentUrls'][1]['url']);
		}
		finally
		{
			array_map('unlink', $paths);
		}
	}

	public function testSendModeWithMultipleTokensKeepsThemInOrder()
	{
		[$tokens, $paths] = $this->makeAttachmentTokens(['first.txt' => 'AAA', 'second.txt' => 'BBB']);
		try
		{
			$result = $this->invokeApiHandler('prepareAttachments', [$tokens, null, null, null, false]);

			$this->assertCount(2, $result['attachments']);
			$this->assertSame('first.txt', $result['attachments'][0]['name']);
			$this->assertSame($paths[0], $result['attachments'][0]['file']);
			$this->assertSame('second.txt', $result['attachments'][1]['name']);
			$this->assertSame($paths[1], $result['attachments'][1]['file']);
		}
		finally
		{
			array_map('unlink', $paths);
		}
	}
}
