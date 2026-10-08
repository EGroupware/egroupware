<?php
/**
 * EGroupware Api: Test hl_email_tag_transform()'s same-origin image-blocking exemption
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use PHPUnit\Framework\TestCase;

require_once realpath(__DIR__.'/../../src/Html/HtmLawed.php');

/**
 * Ticket #125961: a same-origin '/webdav.php' image reference must NOT get the general
 * "own webserver url is not external" pass any more - it only works for whoever has this exact
 * account's own session, so it is no safer than any other external image reference (eg. a stray
 * one left over in forwarded/quoted content from before compose stopped producing these).
 *
 * hl_email_tag_transform() is a plain namespaced function (htmLawed's own tag-transform
 * callback contract, not a class method) - called directly here, same as the library itself
 * calls it, with the global preference/server-config state it reads set up per test.
 */
class HlEmailTagTransformWebdavTest extends TestCase
{
	private $origPrefs;
	private $origWebserverUrl;

	protected function setUp() : void
	{
		$this->origPrefs = $GLOBALS['egw_info']['user']['preferences']['mail'] ?? null;
		$this->origWebserverUrl = $GLOBALS['egw_info']['server']['webserver_url'] ?? null;
		$GLOBALS['egw_info']['server']['webserver_url'] = 'https://my.egroupware.example';
		$GLOBALS['egw_info']['user']['preferences']['mail'] = [
			'allowExternalIMGs' => 2,
			'allowExternalDomains' => [],
		];
	}

	protected function tearDown() : void
	{
		$GLOBALS['egw_info']['user']['preferences']['mail'] = $this->origPrefs;
		$GLOBALS['egw_info']['server']['webserver_url'] = $this->origWebserverUrl;
	}

	private function transformImg(string $src) : string
	{
		return \EGroupware\Api\Html\hl_email_tag_transform('img', ['src' => $src]);
	}

	public function testSameOriginNonWebdavImageIsLeftUnblocked()
	{
		$result = $this->transformImg('https://my.egroupware.example/api/templates/default/images/foo.png');

		$this->assertStringContainsString('src="https://my.egroupware.example/api/templates/default/images/foo.png"', $result);
		$this->assertStringNotContainsString('blocked external image', $result);
	}

	public function testSameOriginWebdavImageIsBlocked()
	{
		$result = $this->transformImg('https://my.egroupware.example/webdav.php/home/someone/.tmp/x/logo.png');

		$this->assertStringContainsString('blocked external image:https://my.egroupware.example/webdav.php/home/someone/.tmp/x/logo.png', $result);
		$this->assertStringNotContainsString('src="https://my.egroupware.example/webdav.php', $result);
	}

	public function testAllowlistedDomainStillRescuesAWebdavImage()
	{
		$GLOBALS['egw_info']['user']['preferences']['mail']['allowExternalDomains'] = ['my.egroupware.example'];

		$result = $this->transformImg('https://my.egroupware.example/webdav.php/home/someone/.tmp/x/logo.png');

		$this->assertStringContainsString('src="https://my.egroupware.example/webdav.php/home/someone/.tmp/x/logo.png"', $result);
		$this->assertStringNotContainsString('blocked external image', $result);
	}

	public function testForeignHostWebdavImageIsStillBlockedAsBefore()
	{
		$result = $this->transformImg('https://attacker.example/webdav.php/x.png');

		$this->assertStringContainsString('blocked external image:https://attacker.example/webdav.php/x.png', $result);
	}
}
