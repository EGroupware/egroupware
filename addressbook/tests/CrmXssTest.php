<?php
/**
 * EGroupware Addressbook: crm.php 'from' parameter XSS regression test
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

/**
 * Regression coverage for GHSA-983h-jwwj-64cc (high): addressbook/crm.php's Basic-auth CTI helper
 * copied the raw 'from' GET parameter into its no-contact-found error body
 * (die("No contact for from=$from found!\n")) with no HTML output encoding, so a value like
 * <details open ontoggle=...> executed as script in the authenticated caller's browser once
 * Api\Contacts::openCrmView($from) threw (no matching contact).
 *
 * Fix (commit 27668b8ed4, predates the advisory by a month): htmlspecialchars() the value before
 * the die() that echoes it.
 *
 * Black-box HTTP test against the real script (modeled on LogoutRedirectXssTest.php): crm.php is
 * a raw top-level entry script with its own bootstrap/auth handling, not includable in isolation.
 */
class CrmXssTest extends LoggedInTest
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
			RequestOptions::AUTH => [$GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']],
		]);
	}

	public function testNoContactPayloadIsEscapedNotExecutable()
	{
		$payload = '<details open ontoggle=alert(document.domain)>';
		// guaranteed to never match a real contact - openCrmView() must throw, reaching the sink
		$from = 'ghsa-983h-test-'.bin2hex(random_bytes(4)).$payload;

		$body = (string)$this->client()->get($this->egwUrl().'/addressbook/crm.php', [
			RequestOptions::QUERY => ['from' => $from],
		])->getBody();

		$this->assertStringNotContainsString($payload, $body,
			'The raw, unescaped from-parameter payload must never appear in crm.php\'s response');
		$this->assertStringContainsString(htmlspecialchars($from, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'), $body,
			'The htmlspecialchars-escaped value should appear - proves the no-contact-found path was '.
			'actually reached and reflected safely, not silently changed to something else');
	}

	public function testMissingFromParameterStillRejected()
	{
		$response = $this->client()->get($this->egwUrl().'/addressbook/crm.php');

		$this->assertSame(400, $response->getStatusCode(),
			'crm.php must still reject a request with no from parameter at all');
	}
}
