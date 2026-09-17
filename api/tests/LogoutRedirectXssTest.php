<?php
/**
 * EGroupware Api: logout redirect-target XSS regression test
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Tests;

require_once __DIR__.'/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Regression coverage for a session-stored XSS in the shared 'login'/'referer' value that
 * api/ntlm/index.php and login.php both write and logout.php reflects.
 *
 * api/ntlm/index.php's forward parameter only required a leading "/" (check_domain()), with no
 * further sanitization, before storing it verbatim via Api\Cache::setSession('login','referer',
 * $forward). logout.php later echoed that stored value, completely unescaped, into two HTML
 * attributes (<meta ... content="1;url=...">, <a href="...">). A value like
 * /x"><details open ontoggle=...> broke out of the attribute and executed under the EGroupware
 * origin for anyone routed through logout.php after visiting the crafted ntlm/index.php link -
 * reported externally (Mike Jenkinson, 2026-09-16), fixed in commit 9854756816: htmlspecialchars()
 * at both echo sites in logout.php, plus tightening check_domain() to reject "//host" (browsers
 * treat that as scheme-relative to a different host) and any value containing '"', "'", '<' or '>'.
 *
 * Black-box HTTP tests against the real scripts (modeled on api/tests/CalDAVTest.php /
 * openid/tests/OpenIDTestBase.php), not unit tests of check_domain() directly: both files are raw
 * top-level entry scripts with bootstrap/session side effects, not includable in isolation - the
 * actual regression risk is in the full round-trip (stored by one script, reflected by another),
 * which only a real request/response pair actually proves.
 *
 * Pass criteria:
 * - a forward value that starts with "/" but has no HTML-breaking characters (the legitimate
 *   case) must still reach logout.php's redirect target, proving the tightened check_domain()
 *   didn't overcorrect and break normal same-origin forwarding
 * - a breakout payload delivered via login.php's Referer-header writer (which has no
 *   check_domain()-style gate at all) must appear in logout.php's response only in
 *   htmlspecialchars-escaped form, never raw - proving the sink-level fix, independent of
 *   check_domain(), protects the writer that fix doesn't even touch
 * - a value check_domain() must reject via api/ntlm/index.php's forward parameter
 *   (protocol-relative "//host", or any breakout characters) must not appear anywhere in
 *   logout.php's response at all, escaped or not - proving it was never stored
 */
class LogoutRedirectXssTest extends LoggedInTest
{
	private function egwUrl() : string
	{
		$egw_url = getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null) ?:
			'http://localhost/egroupware';
		return rtrim($egw_url, '/');
	}

	/**
	 * @param ?string $referer optional Referer header on the login POST itself - login.php
	 *   stores it verbatim into the same 'login'/'referer' session slot logout.php reflects,
	 *   whenever it doesn't contain the login request's own REQUEST_URI (real second writer
	 *   into that slot, independent of api/ntlm/index.php's forward parameter)
	 * @return array{0: Client, 1: CookieJar}|null null if login failed (markTestSkipped then)
	 */
	private function httpLogin(?string $referer=null) : ?array
	{
		$jar = new CookieJar();
		$client = new Client([
			RequestOptions::HTTP_ERRORS => false,
			RequestOptions::ALLOW_REDIRECTS => false,
			RequestOptions::CONNECT_TIMEOUT => 5,
			RequestOptions::TIMEOUT => 10,
			RequestOptions::COOKIES => $jar,
		]);
		$response = $client->post($this->egwUrl().'/login.php', [
			RequestOptions::ALLOW_REDIRECTS => true,
			RequestOptions::FORM_PARAMS => [
				'login' => $GLOBALS['EGW_USER'],
				'passwd' => $GLOBALS['EGW_PASSWORD'],
				'passwd_type' => 'text',
				'submitit' => 'Login',
			],
			RequestOptions::HEADERS => $referer ? ['Referer' => $referer] : [],
		]);
		$body = (string)$response->getBody();
		if ($response->getStatusCode() !== 200 || !$jar->getCookieByName('sessionid') ||
			str_contains($body, 'name="passwd"') || str_contains($body, '[Login]'))
		{
			return null;
		}
		return [$client, $jar];
	}

	/**
	 * Drive the forward -> logout round-trip for one $forward value, returning logout.php's body.
	 */
	private function forwardThenLogout(Client $client, string $forward) : string
	{
		$client->get($this->egwUrl().'/api/ntlm/index.php', [
			RequestOptions::QUERY => ['forward' => $forward],
		]);
		return (string)$client->get($this->egwUrl().'/logout.php')->getBody();
	}

	public function testLegitimateForwardStillReflected()
	{
		[$client] = $this->httpLogin() ?? [null];
		if (!$client) $this->markTestSkipped('Could not log in as phpunit test user');

		$body = $this->forwardThenLogout($client, '/index.php');

		$this->assertStringContainsString('/index.php', $body,
			'A plain same-origin forward path must still reach logout.php\'s redirect target');
	}

	/**
	 * check_domain() now rejects any breakout character, so api/ntlm/index.php's forward
	 * parameter can no longer deliver one - but login.php's OTHER writer into the same session
	 * slot (the request's Referer header, see httpLogin()) has no such gate at all. The sink-level
	 * htmlspecialchars() fix in logout.php is what protects that writer, and is exercised here
	 * directly: a real login request whose Referer header carries the breakout payload.
	 */
	public function testLoginRefererPayloadIsEscapedNotExecutable()
	{
		$payload = 'https://attacker.example/x"><details open ontoggle=alert(document.domain)>';
		[$client] = $this->httpLogin($payload) ?? [null];
		if (!$client) $this->markTestSkipped('Could not log in as phpunit test user');

		$body = (string)$client->get($this->egwUrl().'/logout.php')->getBody();

		$this->assertStringNotContainsString($payload, $body,
			'The raw, unescaped Referer-derived payload must never appear in logout.php\'s response');
		$this->assertStringContainsString(htmlspecialchars($payload, ENT_QUOTES), $body,
			'The htmlspecialchars-escaped payload should appear - proves it was stored (login.php\'s ' .
			'Referer writer has no check_domain()-style gate) and reflected safely (escaped at the ' .
			'sink), not silently dropped');
	}

	public function testBreakoutAndProtocolRelativeForwardsAreRejected()
	{
		foreach ([
			'protocol-relative (scheme-relative to a different host)' => '//evil.example/phish',
			'quote-containing, even with a single leading slash' => '/x"onmouseover=alert(1)',
		] as $description => $payload)
		{
			// fresh login per payload: logout.php destroys the session, so one client can't
			// drive the forward->logout round-trip twice
			[$client] = $this->httpLogin() ?? [null];
			if (!$client) $this->markTestSkipped('Could not log in as phpunit test user');

			$body = $this->forwardThenLogout($client, $payload);

			$this->assertStringNotContainsString($payload, $body,
				"check_domain() must reject $description: it must never reach logout.php's response, escaped or not");
		}
	}
}
