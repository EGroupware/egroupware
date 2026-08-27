<?php
/**
 * EGroupware Admin: tests for admin_mail's DNS/ISPDB-based mail server discovery
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail\Jmap\Http as JmapHttp;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Test-only subclass exposing admin_mail's DNS/HTTP seam (dnsQuery()/ispdbHttpGet(), added
 * specifically to make guess_hosts()/mozilla_ispdb() unit-testable without live network/DNS
 * access) with fixture maps instead of real dns_get_record()/file_get_contents() calls.
 *
 * A lookup missing from the fixture map throws, so a test can't accidentally pass because it
 * forgot to stub a call the code path actually makes.
 */
class TestableAdminMail extends admin_mail
{
	/** @var array [hostname][type] => dns_get_record()-shaped result|false */
	public static $dnsFixtures = array();
	/** @var array [url] => string|false */
	public static $httpFixtures = array();
	/** @var array [host] => JmapHttp|\Throwable */
	public static $jmapFixtures = array();

	protected static function dnsQuery(string $hostname, int $type)
	{
		if (!array_key_exists($hostname, self::$dnsFixtures) || !array_key_exists($type, self::$dnsFixtures[$hostname]))
		{
			throw new \RuntimeException("Unstubbed dnsQuery('$hostname', $type) call");
		}
		return self::$dnsFixtures[$hostname][$type];
	}

	protected static function ispdbHttpGet(string $url)
	{
		if (!array_key_exists($url, self::$httpFixtures))
		{
			throw new \RuntimeException("Unstubbed ispdbHttpGet('$url') call");
		}
		return self::$httpFixtures[$url];
	}

	protected static function jmapClient(string $host, string $username, string $password, ?string &$accountId=null, bool $verify=true) : JmapHttp
	{
		if (!array_key_exists($host, self::$jmapFixtures))
		{
			throw new \RuntimeException("Unstubbed jmapClient('$host') call");
		}
		// a plain array of results = a queue (first call gets index 0, retry gets index 1, ...) -
		// used to test the certificate-verification-failure-then-retry path, where the SAME url
		// is called twice with different $verify values
		if (is_array(self::$jmapFixtures[$host]) && array_is_list(self::$jmapFixtures[$host]))
		{
			$result = array_shift(self::$jmapFixtures[$host]);
		}
		else
		{
			$result = self::$jmapFixtures[$host];
		}
		if ($result instanceof \Throwable) throw $result;
		return $result;
	}
}

/**
 * Tests admin_mail::guess_hosts() (DNS-based hostname guessing) and ::mozilla_ispdb()
 * (Thunderbird autoconfig XML + MX-based retry), the two discovery mechanisms
 * admin_mail::autoconfig() falls back to when no OAuth provider matches the domain and no
 * explicit host was given.
 *
 * guess_hosts() is a non-static instance method, and admin_mail::__construct() needs a DB
 * session (Api\Translation::add_app()/Api\Preferences::setlocale()), so this extends
 * Api\LoggedInTest - not because guess_hosts()/mozilla_ispdb()'s own logic touches the
 * database, but because instantiating admin_mail at all requires one.
 *
 * NOT covered here: autoconfig() itself (which calls these and then opens a real IMAP
 * connection) - see doc/ai/projects/mail-wizard-jmap-oauth.md for the full coverage map.
 */
#[AllowMockObjectsWithoutExpectations]
class AdminMailHostDiscoveryTest extends Api\LoggedInTest
{
	protected function setUp() : void
	{
		TestableAdminMail::$dnsFixtures = array();
		TestableAdminMail::$httpFixtures = array();
		TestableAdminMail::$jmapFixtures = array();
	}

	/**
	 * Build a JmapHttp stub bypassing its real (network-performing) constructor
	 */
	private function jmapStub(array $accountCapabilities=[], bool $passwordGrantSucceeds=true) : JmapHttp
	{
		$mock = $this->getMockBuilder(JmapHttp::class)
			->disableOriginalConstructor()
			->onlyMethods(['passwordGrant', '__get'])
			->getMock();
		$mock->method('passwordGrant')->willReturn($passwordGrantSucceeds ?
			array('access_token' => 'x', 'refresh_token' => 'y', 'expires_in' => 3600) : null);
		$mock->method('__get')->willReturnCallback(function($name) use ($accountCapabilities) {
			return $name === 'accountCapabilities' ? $accountCapabilities : null;
		});
		return $mock;
	}

	private function tryJmap(array &$content) : bool
	{
		$ref = new ReflectionMethod(TestableAdminMail::class, 'tryJmap');
		$ref->setAccessible(true);
		// invokeArgs(), not invoke(): tryJmap()'s $content parameter is by-reference and
		// invoke() silently passes it by value instead (with a deprecation warning)
		return $ref->invokeArgs(new TestableAdminMail(), array(&$content));
	}

	private function guessHosts(string $email, string $type='imap')
	{
		$ref = new ReflectionMethod(TestableAdminMail::class, 'guess_hosts');
		$ref->setAccessible(true);
		return $ref->invoke(new TestableAdminMail(), $email, $type);
	}

	private function mozillaIspdb(string $domain, bool $try_mx=true)
	{
		$ref = new ReflectionMethod(TestableAdminMail::class, 'mozilla_ispdb');
		$ref->setAccessible(true);
		return $ref->invoke(null, $domain, $try_mx);
	}

	private function ispdbXml(string $displayName, string $imapHost, string $smtpHost) : string
	{
		return '<?xml version="1.0"?>'.
			'<clientConfig version="1.1"><emailProvider id="example.org">'.
			'<displayName>'.$displayName.'</displayName>'.
			'<incomingServer type="imap">'.
				'<hostname>'.$imapHost.'</hostname><port>993</port>'.
				'<socketType>SSL</socketType><username>%EMAILADDRESS%</username>'.
			'</incomingServer>'.
			'<outgoingServer type="smtp">'.
				'<hostname>'.$smtpHost.'</hostname><port>465</port>'.
				'<socketType>SSL</socketType><username>%EMAILADDRESS%</username>'.
			'</outgoingServer>'.
			'</emailProvider></clientConfig>';
	}

	// --- guess_hosts() ---

	/**
	 * Without any usable MX record, imap.$domain and mail.$domain must still be generated as
	 * candidates and returned (given they resolve) - the "usual names" guess never depends on
	 * MX data being available at all.
	 */
	public function testGuessHostsAlwaysAddsTypeAndMailPrefixedCandidates()
	{
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = false;
		TestableAdminMail::$dnsFixtures['imap.example.org'][DNS_A] = array(array('type' => 'A'));
		TestableAdminMail::$dnsFixtures['mail.example.org'][DNS_A] = array(array('type' => 'A'));

		$result = $this->guessHosts('user@example.org', 'imap');

		$this->assertSame(array('imap.example.org', 'mail.example.org'), array_keys($result));
	}

	/**
	 * send.$domain is only ever a candidate for SMTP discovery, never for IMAP.
	 */
	public function testGuessHostsAddsSendPrefixOnlyForSmtpType()
	{
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = false;
		foreach (array('smtp.example.org', 'mail.example.org', 'send.example.org') as $host)
		{
			TestableAdminMail::$dnsFixtures[$host][DNS_A] = array(array('type' => 'A'));
		}

		$result = $this->guessHosts('user@example.org', 'smtp');

		$this->assertArrayHasKey('send.example.org', $result);
	}

	/**
	 * A resolvable MX record must contribute type- and mail-prefixed hostnames derived from
	 * the MX target itself, plus the raw MX target as a candidate.
	 */
	public function testGuessHostsDerivesCandidatesFromMxTarget()
	{
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = array(array('target' => 'mx1.example.net'));
		foreach (array('imap.example.org', 'mail.example.org', 'imap.example.net', 'mail.example.net', 'mx1.example.net') as $host)
		{
			TestableAdminMail::$dnsFixtures[$host][DNS_A] = array(array('type' => 'A'));
		}

		$result = $this->guessHosts('user@example.org', 'imap');

		$this->assertArrayHasKey('imap.example.net', $result);
		$this->assertArrayHasKey('mail.example.net', $result);
		$this->assertArrayHasKey('mx1.example.net', $result);
	}

	/**
	 * An MX target ending in ".mail.protection.outlook.com" (Office365's hosted-MX pattern)
	 * must add the well-known outlook.office365.com/smtp.office365.com hosts as candidates.
	 */
	public function testGuessHostsDetectsOffice365ByMxProtectionSuffix()
	{
		$mxTarget = 'x.mail.protection.outlook.com';
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = array(array('target' => $mxTarget));
		foreach (array(
			'imap.example.org', 'mail.example.org', 'outlook.office365.com',
			'imap.mail.protection.outlook.com', 'mail.mail.protection.outlook.com', $mxTarget,
		) as $host)
		{
			TestableAdminMail::$dnsFixtures[$host][DNS_A] = array(array('type' => 'A'));
		}

		$result = $this->guessHosts('user@example.org', 'imap');

		$this->assertArrayHasKey('outlook.office365.com', $result);
	}

	/**
	 * A candidate host that fails the final DNS A-record verification must be dropped, even
	 * though it was generated as a plausible guess.
	 */
	public function testGuessHostsDropsCandidatesFailingFinalDnsAVerification()
	{
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = false;
		TestableAdminMail::$dnsFixtures['imap.example.org'][DNS_A] = array(array('type' => 'A'));
		TestableAdminMail::$dnsFixtures['mail.example.org'][DNS_A] = false;

		$result = $this->guessHosts('user@example.org', 'imap');

		$this->assertArrayHasKey('imap.example.org', $result);
		$this->assertArrayNotHasKey('mail.example.org', $result);
	}

	/**
	 * If every generated candidate fails DNS A-record verification, the result must be an
	 * empty array, not eg. false or null.
	 */
	public function testGuessHostsReturnsEmptyArrayWhenNoCandidateResolves()
	{
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = false;
		TestableAdminMail::$dnsFixtures['imap.example.org'][DNS_A] = false;
		TestableAdminMail::$dnsFixtures['mail.example.org'][DNS_A] = false;

		$result = $this->guessHosts('user@example.org', 'imap');

		$this->assertSame(array(), $result);
	}

	// --- mozilla_ispdb() ---

	/**
	 * A valid Thunderbird-autoconfig XML response must parse into displayName plus imap/smtp
	 * server arrays with hostname/port/socketType/username fields.
	 */
	public function testMozillaIspdbParsesIncomingAndOutgoingServersFromXml()
	{
		$url = 'https://autoconfig.thunderbird.net/v1.1/example.org';
		TestableAdminMail::$httpFixtures[$url] = $this->ispdbXml('Example Provider', 'imap.example.org', 'smtp.example.org');

		$result = $this->mozillaIspdb('example.org');

		$this->assertSame('Example Provider', $result['displayName']);
		$this->assertSame('imap.example.org', $result['imap'][0]['hostname']);
		$this->assertSame('993', $result['imap'][0]['port']);
		$this->assertSame('smtp.example.org', $result['smtp'][0]['hostname']);
	}

	/**
	 * An invalid/unparseable response with MX fallback disabled must return an empty array,
	 * not throw or emit a bogus provider.
	 */
	public function testMozillaIspdbReturnsEmptyArrayOnInvalidXmlWithMxDisabled()
	{
		$url = 'https://autoconfig.thunderbird.net/v1.1/example.org';
		TestableAdminMail::$httpFixtures[$url] = 'not valid xml';

		$result = $this->mozillaIspdb('example.org', false);

		$this->assertSame(array(), $result);
	}

	/**
	 * When the direct domain lookup fails, mozilla_ispdb() must retry against the domain's MX
	 * target before giving up - some hosted-email providers only register the MX target's
	 * domain in ISPDB, not the customer's own domain.
	 */
	public function testMozillaIspdbFallsBackToMxTargetDomainOnDirectLookupFailure()
	{
		$directUrl = 'https://autoconfig.thunderbird.net/v1.1/example.org';
		$mxUrl = 'https://autoconfig.thunderbird.net/v1.1/mx.otherdomain.com';
		TestableAdminMail::$httpFixtures[$directUrl] = 'not valid xml';
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = array(array('target' => 'mx.otherdomain.com'));
		TestableAdminMail::$httpFixtures[$mxUrl] = $this->ispdbXml('MX Provider', 'imap.otherdomain.com', 'smtp.otherdomain.com');

		$result = $this->mozillaIspdb('example.org');

		$this->assertSame('MX Provider', $result['displayName']);
	}

	/**
	 * When both the direct domain AND the full MX target fail, a third attempt must strip the
	 * MX target's leading host label and retry against just its domain part (eg. some 1&1-style
	 * hosted providers register only the bare domain, not the specific mail-exchanger host).
	 */
	public function testMozillaIspdbStripsHostnameFromMxTargetOnSecondFallback()
	{
		$directUrl = 'https://autoconfig.thunderbird.net/v1.1/example.org';
		$mxHostUrl = 'https://autoconfig.thunderbird.net/v1.1/mx1.example.net';
		$mxDomainUrl = 'https://autoconfig.thunderbird.net/v1.1/example.net';
		TestableAdminMail::$httpFixtures[$directUrl] = 'not valid xml';
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = array(array('target' => 'mx1.example.net'));
		TestableAdminMail::$httpFixtures[$mxHostUrl] = 'not valid xml either';
		TestableAdminMail::$httpFixtures[$mxDomainUrl] = $this->ispdbXml('Stripped Domain Provider', 'imap.example.net', 'smtp.example.net');

		$result = $this->mozillaIspdb('example.org');

		$this->assertSame('Stripped Domain Provider', $result['displayName']);
	}

	/**
	 * When the direct lookup fails and there is no MX record at all, the result must be an
	 * empty array - there is nothing left to retry against.
	 */
	public function testMozillaIspdbReturnsEmptyWhenMxLookupFindsNothing()
	{
		$directUrl = 'https://autoconfig.thunderbird.net/v1.1/example.org';
		TestableAdminMail::$httpFixtures[$directUrl] = 'not valid xml';
		TestableAdminMail::$dnsFixtures['example.org'][DNS_MX] = false;

		$result = $this->mozillaIspdb('example.org');

		$this->assertSame(array(), $result);
	}

	// --- tryJmap() ---

	/**
	 * A resolvable _jmap._tcp SRV record must be tried, and success must set acc_imap_type to
	 * the Stalwart class plus stash the bootstrapped session's accountCapabilities for sieve().
	 */
	public function testTryJmapUsesSrvRecordAndSetsStalwartType()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] =
			array(array('target' => 'jmap.example.org', 'port' => 443, 'pri' => 0, 'weight' => 0));
		TestableAdminMail::$jmapFixtures['https://jmap.example.org'] =
			$this->jmapStub(array('urn:ietf:params:jmap:sieve' => array()));

		$content = array('ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org', 'acc_imap_password' => 'secret');
		$result = $this->tryJmap($content);

		$this->assertTrue($result);
		$this->assertSame('jmap.example.org', $content['acc_imap_host']);
		$this->assertSame(Api\Mail\Imap\Stalwart::class, $content['acc_imap_type']);
		$this->assertSame(admin_mail::JMAP_HTTPS | admin_mail::VERIFY_ENABLED, $content['acc_imap_ssl']);
		$this->assertSame('jmap', $content['connected']);
		$this->assertArrayHasKey('urn:ietf:params:jmap:sieve', $content['_jmap_account_capabilities']);
	}

	/**
	 * A manually selected "JMAP (http)" protocol must be honored - tried over plain http, not
	 * the https default - so a user can point the wizard at a non-standard JMAP endpoint.
	 */
	public function testTryJmapHonorsManualHttpProtocolSelection()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['http://stalwart.example.org'] = $this->jmapStub();

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
			'acc_imap_ssl' => admin_mail::JMAP_HTTP,
		);
		$result = $this->tryJmap($content);

		$this->assertTrue($result);
		$this->assertSame(admin_mail::JMAP_HTTP | admin_mail::VERIFY_ENABLED, $content['acc_imap_ssl']);
	}

	/**
	 * A manually entered non-standard port must be included in the URL tried.
	 */
	public function testTryJmapHonorsCustomPort()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://stalwart.example.org:8443'] = $this->jmapStub();

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
			'acc_imap_port' => 8443,
		);
		$result = $this->tryJmap($content);

		$this->assertTrue($result);
	}

	/**
	 * A certificate-verification failure on the first (strict) attempt DOES try a lenient
	 * retry internally (to tell a genuine certificate problem apart from a truly unreachable
	 * host), but must NOT silently accept the account through it - admin_mail::
	 * pauseForCertReview() catches exactly this case (verification still undecided, only the
	 * lenient fallback worked) and tryJmap() defers to the classic IMAP trial loop instead,
	 * which shows the proper certificate-diagnosis warning and lets the user explicitly decide,
	 * rather than silently persisting VERIFY_DISABLED (found live 2026-08-26: tryJmap() had its
	 * own, separate optimistic-verify implementation that was never wired into
	 * pauseForCertReview() when that was introduced, so a certificate problem on a host that
	 * also answers JMAP bypassed the classic IMAP loop's fix entirely and kept silently
	 * advancing).
	 */
	public function testTryJmapDefersToClassicImapOnCertificateFailure()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://stalwart.example.org'] = [
			new Api\Exception\Http('SSL certificate problem: self-signed certificate', 0, 'GET', 'https://stalwart.example.org', ''),
			$this->jmapStub(),
		];

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
		);
		$result = $this->tryJmap($content);

		$this->assertFalse($result);
	}

	/**
	 * A non-certificate failure on the first attempt must NOT trigger the lenient retry - it's
	 * a real error (wrong credentials, host down, ...) that must surface normally.
	 */
	public function testTryJmapDoesNotRetryOnNonCertificateFailure()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://stalwart.example.org'] =
			new Api\Exception\Http('Unexpected HTTP status code 401: ', 401, 'GET', 'https://stalwart.example.org', '');

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
		);
		$result = $this->tryJmap($content);

		$this->assertFalse($result);
	}

	/**
	 * With no SRV record, an explicitly entered host (manual setup) must still be tried as JMAP
	 * - this is the only path currently exercisable against egroupware.org, which has no
	 * _jmap._tcp record published yet.
	 */
	public function testTryJmapTriesExplicitHostWhenNoSrvRecord()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://stalwart.example.org'] = $this->jmapStub();

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
		);
		$result = $this->tryJmap($content);

		$this->assertTrue($result);
		$this->assertSame('stalwart.example.org', $content['acc_imap_host']);
	}

	/**
	 * A host that isn't a JMAP server at all (or otherwise fails to connect) must make tryJmap()
	 * return false without touching acc_imap_type - so autoconfig() falls through to its
	 * existing IMAP trial unchanged.
	 */
	public function testTryJmapReturnsFalseWhenNotAJmapServer()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://imap.example.org'] =
			new Api\Exception('imap.example.org is NOT a JMAP server!');

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'imap.example.org',
		);
		$result = $this->tryJmap($content);

		$this->assertFalse($result);
		$this->assertArrayNotHasKey('acc_imap_type', $content);
		$this->assertArrayNotHasKey('_jmap_account_capabilities', $content);
	}

	/**
	 * A failed Stalwart OAuth-login workaround (passwordGrant() returning null) must NOT block
	 * account creation - it's a live-validation nicety, the account still works via plain
	 * password authentication. Since passwordGrant() only ever succeeds against a real Stalwart
	 * server (a Stalwart-specific proprietary endpoint, see Api\Jmap::passwordGrant()'s
	 * docblock), its result doubles as a first, cheap way to tell a real Stalwart server apart
	 * from a generic JMAP server (ralf, 2026-08-24) - acc_imap_type falls back to the more
	 * generic Imap\Jmap::class rather than Imap\Stalwart::class when it fails.
	 */
	public function testTryJmapFallsBackToPlainJmapIfPasswordGrantFails()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://stalwart.example.org'] = $this->jmapStub([], false);

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
		);
		$result = $this->tryJmap($content);

		$this->assertTrue($result);
		$this->assertSame(Api\Mail\Imap\Jmap::class, $content['acc_imap_type']);
	}

	/**
	 * A successful Stalwart OAuth-login workaround confirms the server really is Stalwart, not
	 * just some generic JMAP server - acc_imap_type must be the Stalwart-specific subclass, which
	 * carries the admin-automation/OAuth-bootstrap capabilities the plain Jmap class doesn't.
	 */
	public function testTryJmapUsesStalwartClassIfPasswordGrantSucceeds()
	{
		TestableAdminMail::$dnsFixtures['_jmap._tcp.example.org'][DNS_SRV] = false;
		TestableAdminMail::$jmapFixtures['https://stalwart.example.org'] = $this->jmapStub([], true);

		$content = array(
			'ident_email' => 'user@example.org', 'acc_imap_username' => 'user@example.org',
			'acc_imap_password' => 'secret', 'acc_imap_host' => 'stalwart.example.org',
		);
		$result = $this->tryJmap($content);

		$this->assertTrue($result);
		$this->assertSame(Api\Mail\Imap\Stalwart::class, $content['acc_imap_type']);
	}
}
