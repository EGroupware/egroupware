<?php
/**
 * EGroupware Api: Test Mail\Smtp\Stalwart's handling of alias-domains
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail\Smtp\Stalwart;
use PHPUnit\Framework\TestCase;

/**
 * Stalwart knows an alias-domain (eg. meissen-inklusiv.de for lebenshilfe-meissen.de) only as key of the "aliases" map of its main domain, never as
 * domain of its own. An address on an alias-domain has the same name-parts as the main domain: it is an alias of that name on the main domain.
 * Found with the first switch of a domain, which had users with a primary email on its alias-domain which differs from the account name
 * (s.hinkel@meissen-inklusiv.de for shinkel): the email was neither created as alias, nor accepted (422), nor did the user get an identity to send with.
 *
 * No live server needed: the JMAP connection is a stand-in with one main domain, its alias-domain and an unrelated second domain.
 */
class StalwartAliasDomainTest extends TestCase
{
	private function stalwart() : Stalwart
	{
		$jmap = new class
		{
			public int $calls = 0;
			public function jmapCall(array $calls, array $using=[]) : array
			{
				++$this->calls;
				[$method, $args] = $calls[0];
				if ($method !== 'x:Domain/get')
				{
					throw new \Exception("unexpected $method");
				}
				// main = "1" with its alias-domain, other = "2" with an alias-domain of its own
				$aliases = ['1' => ['Meissen-Inklusiv.de' => true], '2' => ['alias.other.example' => true]];
				return ['methodResponses' => [['x:Domain/get', ['list' => [['id' => $args['ids'][0], 'aliases' => $aliases[$args['ids'][0]] ?? []]]], 'a']]];
			}
		};
		$stalwart = new class(null, null) extends Stalwart
		{
			public $jmapStandIn;
			public function __construct($a, $b)
			{
			}
			public function jmapClient(bool $adminConnection=true)
			{
				return $this->jmapStandIn;
			}
			public function domainId(string $domain) : ?string
			{
				return ['main.example' => '1', 'other.example' => '2'][strtolower($domain)] ?? null;
			}
			public function aliases(array $addresses, string $primary) : array
			{
				return $this->aliasObjects($addresses, $primary);
			}
		};
		$stalwart->defaultDomain = 'main.example';
		$stalwart->jmapStandIn = $jmap;
		return $stalwart;
	}

	protected function setUp() : void
	{
		(new \ReflectionProperty(Stalwart::class, 'aliasDomains'))->setValue(null, []);
		Stalwart::$skipTakenAliases = false;
		Stalwart::$warnings = [];
	}

	public function testAliasDomainsOfTheAccountsDomainOnly()
	{
		$stalwart = $this->stalwart();
		$this->assertSame(['meissen-inklusiv.de'], $stalwart->aliasDomains(), 'lower-case names of the alias-domains of the account\'s domain');
		$this->assertSame(['alias.other.example'], $stalwart->aliasDomains('other.example'));
	}

	public function testAliasDomainsAreFetchedOnlyOnce()
	{
		$stalwart = $this->stalwart();
		$stalwart->aliasDomains();
		$stalwart->aliasDomains();
		$this->assertSame(1, $stalwart->jmapStandIn->calls);
	}

	public function testDomainIdOrAliasDomain()
	{
		$stalwart = $this->stalwart();
		$this->assertSame('1', $stalwart->domainIdOrAliasDomain('main.example'));
		$this->assertSame('2', $stalwart->domainIdOrAliasDomain('other.example'), 'a domain of its own is found as before');
		$this->assertSame('1', $stalwart->domainIdOrAliasDomain('meissen-inklusiv.de'), 'alias-domain of the account\'s domain: the main domain');
		$this->assertSame('1', $stalwart->domainIdOrAliasDomain('MEISSEN-Inklusiv.DE'));
		$this->assertNull($stalwart->domainIdOrAliasDomain('alias.other.example'), 'alias-domain of an other domain is not the account\'s');
		$this->assertNull($stalwart->domainIdOrAliasDomain('unknown.example'));
	}

	public function testPrimaryEmailOnAnAliasDomainBecomesAnAliasOnTheMainDomain()
	{
		$aliases = $this->stalwart()->aliases(['s.hinkel@meissen-inklusiv.de', 'j.havekost@main.example'], 's.hinkel@meissen-inklusiv.de');

		$this->assertSame(['s.hinkel', 'j.havekost'], array_column($aliases, 'name'));
		$this->assertSame(['1', '1'], array_column($aliases, 'domainId'));
		$this->assertSame([true, true], array_column($aliases, 'enable'));
	}

	public function testSameNameOnMainAndAliasDomainIsOneAlias()
	{
		$aliases = $this->stalwart()->aliases(['x.y@main.example', 'x.y@meissen-inklusiv.de', 'X.Y@MEISSEN-INKLUSIV.DE'], 'x.y@main.example');

		$this->assertCount(1, $aliases);
		$this->assertSame('x.y', $aliases[0]['name']);
	}

	public function testUnknownDomainIsStillRefused()
	{
		$this->expectException(\Exception::class);
		$this->expectExceptionCode(422);
		$this->stalwart()->aliases(['a@unknown.example'], 'b@main.example');
	}

	public function testUnknownDomainIsSkippedWithAWarningForTheRestApi()
	{
		Stalwart::$skipTakenAliases = true;
		$aliases = $this->stalwart()->aliases(['a@unknown.example', 'b@meissen-inklusiv.de'], 'c@main.example');

		$this->assertSame(['b'], array_column($aliases, 'name'));
		$this->assertCount(1, Stalwart::$warnings);
	}

	public function testExternalPrimaryEmailIsSkippedWithoutWarning()
	{
		$aliases = $this->stalwart()->aliases(['ext@elsewhere.example'], 'ext@elsewhere.example');

		$this->assertSame([], $aliases);
		$this->assertSame([], Stalwart::$warnings);
	}
}
