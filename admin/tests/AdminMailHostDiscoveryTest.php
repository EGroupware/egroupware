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
class AdminMailHostDiscoveryTest extends Api\LoggedInTest
{
	protected function setUp() : void
	{
		TestableAdminMail::$dnsFixtures = array();
		TestableAdminMail::$httpFixtures = array();
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
}
