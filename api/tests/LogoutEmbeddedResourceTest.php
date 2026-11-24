<?php
/**
 * EGroupware API: tests logout.php ignores a logout requested by an embedded resource
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;

/**
 * Logging out is a navigation: a link, a redirect or the frame of a single logout.
 *
 * An image pointing to logout.php, eg. in the html of a mail or a notification,
 * would otherwise log out everyone looking at it.
 * Browsers tell what a request is for in the Sec-Fetch-Dest header.
 *
 * Black-box HTTP test against the real script, like LogoutRedirectXssTest.php. No login is needed:
 * the request is rejected before the session is looked at.
 */
class LogoutEmbeddedResourceTest extends TestCase
{
	private function egwUrl() : string
	{
		$egw_url = getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null) ?:
			'http://localhost/egroupware';
		return rtrim($egw_url, '/');
	}

	/**
	 * Status of a request to logout.php
	 *
	 * @param ?string $dest value of the Sec-Fetch-Dest header, null to not send one
	 * @return int
	 */
	private function logoutStatus(?string $dest) : int
	{
		return (new Client([
			RequestOptions::HTTP_ERRORS => false,
			RequestOptions::ALLOW_REDIRECTS => false,
			RequestOptions::CONNECT_TIMEOUT => 5,
			RequestOptions::TIMEOUT => 10,
		]))->get($this->egwUrl().'/logout.php', [
			RequestOptions::HEADERS => isset($dest) ? ['Sec-Fetch-Dest' => $dest] : [],
		])->getStatusCode();
	}

	public static function embeddedResources() : array
	{
		return [['image'], ['script'], ['style'], ['font'], ['audio'], ['video'], ['object'], ['embed']];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('embeddedResources')]
	public function testEmbeddedResourceIsRejected(string $dest) : void
	{
		$this->assertSame(403, $this->logoutStatus($dest));
	}

	public static function navigations() : array
	{
		// no header: a browser not sending it or another client
		return [['document'], ['iframe'], ['frame'], ['empty'], [null]];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('navigations')]
	public function testNavigationIsNotRejected(?string $dest) : void
	{
		$this->assertNotSame(403, $this->logoutStatus($dest));
	}
}
