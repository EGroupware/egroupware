<?php
/**
 * EGroupware Mail: typed compose preset body regression tests
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use PHPUnit\Framework\TestCase;

require_once realpath(__DIR__.'/../inc/class.mail_compose.inc.php');

/**
 * Tests the isolated preset-body merge used by mail_compose::compose().
 *
 * Calendar sends event descriptions as typed plain-text presets.  The helper is invoked through
 * reflection so no mail account, database, signature or Etemplate setup is needed.  Pass criteria:
 * HTML compose converts and escapes plain text while preserving line and paragraph breaks; plain
 * compose keeps the bytes unchanged; and callers without a declared preset type retain the legacy
 * behaviour.
 */
class ComposePresetBodyTest extends TestCase
{
	private function mergePresetBody(array $content, array $preset) : array
	{
		$method = new ReflectionMethod(mail_compose::class, 'mergePresetBody');
		$method->setAccessible(true);
		$method->invokeArgs(null, array(&$content, &$preset));

		return array($content, $preset);
	}

	public function testPlainPresetIsConvertedForEmptyHtmlComposeBody()
	{
		[$content, $preset] = $this->mergePresetBody(
			array('mimeType' => 'html', 'body' => ''),
			array('mimeType' => 'plain', 'body' => "First line\nsecond <line> &amp; entity\n\nNext paragraph")
		);

		$this->assertSame(
			"<p>First line<br />\nsecond &lt;line&gt; &amp;amp; entity</p>\n<p>Next paragraph</p>\n",
			$content['body'],
			'plain text must become safe HTML with line and paragraph breaks even without a signature'
		);
		$this->assertSame('html', $content['mimeType']);
		$this->assertSame('html', $preset['mimeType']);
	}

	public function testConvertedPresetStaysBeforeExistingHtmlSignature()
	{
		[$content] = $this->mergePresetBody(
			array('mimeType' => 'html', 'body' => '<hr><!-- HTMLSIGBEGIN -->Signature<!-- HTMLSIGEND -->'),
			array('mimeType' => 'plain', 'body' => "One\nTwo")
		);

		$this->assertSame(
			"<p>One<br />\nTwo</p>\n<hr><!-- HTMLSIGBEGIN -->Signature<!-- HTMLSIGEND -->",
			$content['body'],
			'converted preset text must remain above the existing HTML body/signature'
		);
	}

	public function testPlainPresetRemainsPlainForPlainCompose()
	{
		$body = "One\r\nTwo\n\n<literal markup> & entity";
		[$content, $preset] = $this->mergePresetBody(
			array('mimeType' => 'plain', 'body' => ''),
			array('mimeType' => 'plain', 'body' => $body)
		);

		$this->assertSame($body, $content['body'], 'plain compose must preserve the preset text byte-for-byte');
		$this->assertSame('plain', $content['mimeType']);
		$this->assertSame('plain', $preset['mimeType']);
	}

	public function testUntypedPresetKeepsLegacyHtmlBehaviour()
	{
		$body = '<p>Existing caller supplied HTML</p>';
		[$content, $preset] = $this->mergePresetBody(
			array('mimeType' => 'html', 'body' => ''),
			array('body' => $body)
		);

		$this->assertSame($body, $content['body'], 'untyped preset bodies must not be converted or escaped');
		$this->assertArrayNotHasKey('mimeType', $preset);
	}

	public function testUntypedPresetKeepsLegacyMergeWithExistingBody()
	{
		[$content, $preset] = $this->mergePresetBody(
			array('mimeType' => 'plain', 'body' => "Existing\nbody"),
			array('body' => '<p>Untyped preset</p>')
		);

		$this->assertSame(
			"<p>Untyped preset</p><pre>Existing\nbody</pre>\n",
			$content['body'],
			'untyped presets combined with an existing body must retain the previous HTML merge'
		);
		$this->assertSame('html', $content['mimeType']);
		$this->assertSame('html', $preset['mimeType']);
	}
}
