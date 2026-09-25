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
 * JMAP-native compose bootstrap (MailCompose.applyPresetFiles()/applyPresetAttachmentContent()
 * only understand "files"/"attachmentContents") - so an attachment uploaded and then referenced
 * to open a compose window silently never appeared in it. The send ($compose=false) branch
 * already used the correct $ret['attachments'][] shape throughout and is covered here purely as
 * a regression guard against the compose-mode fix accidentally touching it too.
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
	public function testComposeModeWithOneTokenProducesAttachmentContents()
	{
		$content = "hello attachment\x00binary\xffbytes";
		[$tokens, $paths] = $this->makeAttachmentTokens(['report.pdf' => $content]);
		try
		{
			$result = $this->invokeApiHandler('prepareAttachments', [$tokens, null, null, null, true]);

			$this->assertArrayNotHasKey('file', $result, 'must not use the dead classic file[]/name[] shape');
			$this->assertArrayNotHasKey('name', $result);
			$this->assertCount(1, $result['attachmentContents'] ?? []);
			$this->assertSame('report.pdf', $result['attachmentContents'][0]['name']);
			$this->assertSame($content, base64_decode($result['attachmentContents'][0]['content']),
				'content must round-trip byte-for-byte through base64, including non-UTF8 bytes');
			$this->assertSame('attach', $result['filemode']);
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
	 * itself must stay empty, not just e.g. "attachmentContents empty" (the caller's array_filter()
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

			$this->assertCount(2, $result['attachmentContents']);
			$this->assertSame('first.txt', $result['attachmentContents'][0]['name']);
			$this->assertSame('AAA', base64_decode($result['attachmentContents'][0]['content']));
			$this->assertSame('second.txt', $result['attachmentContents'][1]['name']);
			$this->assertSame('BBB', base64_decode($result['attachmentContents'][1]['content']));
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
