<?php
/**
 * EGroupware Api: asyncservices.php 'domain' parameter XSS regression test
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Regression coverage for GHSA-mf42-4cj5-vfpx (high): api/asyncservices.php, reached fully
 * unauthenticated, copied the raw 'domain' GET parameter into its unknown-domain error body
 * (die("...Domain '$_REQUEST[domain]' is not configured or renamed...\n")) with no HTML output
 * encoding, so a value like <details open ontoggle=...> executed as script in the browser of
 * anyone who opened a crafted link.
 *
 * Fix (commit cfa7f7dc20, predates the advisory by a month): htmlspecialchars() the value before
 * the die() that echoes it, and a fixed "500 Internal Server Error" reason phrase instead of
 * interpolating the raw value into the HTTP status line.
 *
 * Black-box HTTP test against the real script (modeled on LogoutRedirectXssTest.php): this is a
 * raw top-level entry script with its own bootstrap, not includable in isolation. No login is
 * needed - the vulnerable path is reached before any authentication.
 */
class AsyncservicesXssTest extends TestCase
{
	private function egwUrl() : string
	{
		$egw_url = getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null) ?:
			'http://localhost/egroupware';
		return rtrim($egw_url, '/');
	}

	private function client() : Client
	{
		return new Client([
			RequestOptions::HTTP_ERRORS => false,
			RequestOptions::ALLOW_REDIRECTS => false,
			RequestOptions::CONNECT_TIMEOUT => 5,
			RequestOptions::TIMEOUT => 10,
		]);
	}

	public function testUnknownDomainPayloadIsEscapedNotExecutable()
	{
		$payload = '<details open ontoggle=alert(document.domain)>';
		// guaranteed to never match a real configured domain
		$domain = 'ghsa-mf42-test-'.bin2hex(random_bytes(4)).$payload;

		$response = $this->client()->get($this->egwUrl().'/api/asyncservices.php', [
			RequestOptions::QUERY => ['domain' => $domain],
		]);
		$body = (string)$response->getBody();

		$this->assertSame(500, $response->getStatusCode(),
			'an unconfigured domain must still be rejected with a 500');
		$this->assertStringNotContainsString($payload, $body,
			'The raw, unescaped domain-parameter payload must never appear in asyncservices.php\'s response');
		$this->assertStringContainsString(htmlspecialchars($domain, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'), $body,
			'The htmlspecialchars-escaped value should appear - proves the unknown-domain path was '.
			'actually reached and reflected safely, not silently changed to something else');
	}

	/**
	 * Behavior (security-relevant, part of the same fix): the HTTP status line's reason phrase
	 * must be a fixed string, never the raw request value - some clients/proxies render or log the
	 * status line in a way that would also be exploitable if it echoed attacker input directly.
	 */
	public function testUnknownDomainStatusLineIsFixedNotReflected()
	{
		$payload = 'X"><script>1</script>';
		$domain = 'ghsa-mf42-statusline-test-'.bin2hex(random_bytes(4)).$payload;

		$response = $this->client()->get($this->egwUrl().'/api/asyncservices.php', [
			RequestOptions::QUERY => ['domain' => $domain],
		]);

		$this->assertSame(500, $response->getStatusCode());
		$this->assertStringNotContainsString($payload, $response->getReasonPhrase(),
			'the HTTP reason phrase must be fixed, never contain the raw request value');
	}
}
