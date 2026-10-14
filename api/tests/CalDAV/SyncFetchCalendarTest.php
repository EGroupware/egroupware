<?php
/**
 * EGroupware API: regression test for fetching an ics file of a subscribed calendar (CalDAV\Sync::fetchCalendar())
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage caldav
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api\Exception\Http as HttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sync with scripted responses instead of real HTTP requests
 */
class SyncFetchCalendarStub extends Sync
{
	/**
	 * @var string[] requests made: method, plus "+inm" if it had an If-None-Match header
	 */
	public array $calls = [];
	/**
	 * @var array scripted responses, one per expected request: [$response_header, $body] or a Throwable to throw
	 */
	public array $script = [];

	public function api(string $url, string $method='GET', $body='', array $header=['Content-Type: application/json'], ?array &$response_header=null,
		int $follow=3, bool $only_public=true, bool $verify_peer=true, ?int $connect_timeout=null)
	{
		$this->calls[] = $method.(preg_grep('/^If-None-Match:/i', $header) ? '+inm' : '');
		$step = array_shift($this->script);
		if ($step instanceof \Throwable)
		{
			throw $step;
		}
		[$response_header, $body] = $step;
		return $body;
	}

	/**
	 * @param string|null $token sync-token of last sync
	 * @param array|null &$response_header
	 * @param string|null &$new_token on return the (maybe updated) token
	 */
	public function fetch(?string $token, ?array &$response_header=null, ?string &$new_token=null) : ?string
	{
		$new_token = $token;
		return $this->fetchCalendar($new_token, $response_header);
	}

	public static function token(?string $etag, string $ics) : string
	{
		return self::icsToken($etag, $ics);
	}

	public static function parseToken(?string $token) : array
	{
		return self::parseIcsToken($token);
	}

	public static function hash(string $ics) : string
	{
		return self::icsHash($ics);
	}
}

/**
 * A HEAD request has no body: if the calendar changed, it has to be fetched with a GET.
 *
 * Importing the empty HEAD response (as "$response ?? GET" did, as '' is not null) imported nothing and
 * then deleted ALL events of the subscription, as none of them was found in the empty "calendar".
 * Same for an error page, an empty or a truncated response: we must never import / delete from it.
 *
 * As the ETag of some servers changes with every request, unchanged content is detected via a hash too.
 */
class SyncFetchCalendarTest extends TestCase
{
	const ICS = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:1@example.org\r\nDTSTAMP:20261014T070000Z\r\nSUMMARY:Test\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	const URL = 'https://example.org/calendar.ics';

	protected function sync(array $script, string $url=self::URL) : SyncFetchCalendarStub
	{
		$sync = new SyncFetchCalendarStub($url);
		$sync->script = $script;
		return $sync;
	}

	public function testChangedEtagFetchesTheCalendarWithGetAndNotTheEmptyHeadBody()
	{
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"new"'], ''],		// HEAD: changed ETag, but NO body
			[['HTTP/2 200', 'etag' => 'W/"new2"'], self::ICS],	// GET: the changed calendar
		]);
		$this->assertSame(self::ICS, $sync->fetch('W/"old"', $header));
		$this->assertSame(['HEAD+inm', 'GET'], $sync->calls);
		$this->assertSame('W/"new2"', $header['etag'], 'ETag has to be the one of the GET request, which returned the imported content');
	}

	public function testUnchangedEtagReturnsNullWithoutFetchingTheCalendar()
	{
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"same"'], ''],
		]);
		$this->assertNull($sync->fetch('W/"same"', $header, $token));
		$this->assertSame(['HEAD+inm'], $sync->calls);
		$this->assertSame('W/"same"', $token, 'token is unchanged');
	}

	public function testNotModifiedStatusReturnsNull()
	{
		// api() throws for every non-2xx status, incl. 304 Not Modified
		$sync = $this->sync([
			new HttpException('Unexpected HTTP status code 304', 304, 'HEAD', self::URL, '', ['HTTP/2 304', 'etag' => 'W/"same"'], ''),
		]);
		$this->assertNull($sync->fetch('W/"same"', $header));
		$this->assertSame(['HEAD+inm'], $sync->calls);
		$this->assertSame('W/"same"', $header['etag']);
	}

	public function testWithoutEtagTheCalendarIsFetchedWithGet()
	{
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"first"'], self::ICS],
		]);
		$this->assertSame(self::ICS, $sync->fetch(null, $header));
		$this->assertSame(['GET'], $sync->calls);
		$this->assertSame('W/"first"', $header['etag']);
	}

	public function testICloudUsesGetDirectlyAndKeepsItsBody()
	{
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"new"'], self::ICS],
		], 'https://p01-caldav.icloud.com/published/2/abc');
		$this->assertSame(self::ICS, $sync->fetch('W/"old"'));
		$this->assertSame(['GET+inm'], $sync->calls, 'no second GET, the body of the first one is the calendar');
	}

	public function testBadRequestForHeadFallsBackToGet()
	{
		$sync = $this->sync([
			new HttpException('Unexpected HTTP status code 400', 400, 'HEAD', self::URL, '', ['HTTP/2 400'], ''),
			[['HTTP/2 200', 'etag' => 'W/"new"'], self::ICS],
		]);
		$this->assertSame(self::ICS, $sync->fetch('W/"old"'));
		$this->assertSame(['HEAD+inm', 'GET+inm'], $sync->calls);
	}

	public function testOtherErrorsAreNotHidden()
	{
		$sync = $this->sync([
			new HttpException('Unexpected HTTP status code 500', 500, 'HEAD', self::URL, '', ['HTTP/2 500'], ''),
		]);
		$this->expectException(HttpException::class);
		$sync->fetch('W/"old"');
	}

	/**
	 * @dataProvider notIcsProvider
	 */
	#[DataProvider('notIcsProvider')]
	public function testNothingIsImportedFromAnErrorPageAnEmptyOrTruncatedResponse(string $body)
	{
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"new"'], $body],
		]);
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('NOT importing');
		$sync->fetch(null);
	}

	public static function notIcsProvider() : array
	{
		return [
			'empty'     => [''],
			'html page' => ['<html><body>Maintenance</body></html>'],
			'truncated' => ["BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:1@example.org\r\n"],
		];
	}

	public function testByteOrderMarkAndLeadingWhitespaceAreAccepted()
	{
		$ics = "\xEF\xBB\xBF\r\n".self::ICS;
		$sync = $this->sync([
			[['HTTP/2 200'], $ics],
		]);
		$this->assertSame($ics, $sync->fetch(null));
	}

	public function testChangedEtagWithUnchangedContentIsUnchangedAndTheEtagNotUsedAnymore()
	{
		$hash = SyncFetchCalendarStub::hash(self::ICS);
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"volatile-2"'], ''],	// HEAD: ETag changed ...
			[['HTTP/2 200', 'etag' => 'W/"volatile-2"'], str_replace('20261014T070000Z', '20261014T073000Z', self::ICS)],	// ... but content only differs in DTSTAMP
		]);
		$this->assertNull($sync->fetch('W/"volatile-1" sha256:'.$hash, $header, $token));
		$this->assertSame(['HEAD+inm', 'GET'], $sync->calls);
		$this->assertSame('sha256:'.$hash, $token, 'ETag is unreliable for this server, only the hash is kept');
	}

	public function testWithOnlyAHashTheCalendarIsFetchedWithGetAndUnchangedContentReturnsNull()
	{
		$hash = SyncFetchCalendarStub::hash(self::ICS);
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"volatile-3"'], self::ICS],
		]);
		$this->assertNull($sync->fetch('sha256:'.$hash, $header, $token));
		$this->assertSame(['GET'], $sync->calls, 'no HEAD request with an ETag we do not use anymore');
		$this->assertSame('sha256:'.$hash, $token);
	}

	public function testChangedContentIsReturnedEvenWithHash()
	{
		$old_token = 'W/"old" sha256:'.SyncFetchCalendarStub::hash(self::ICS);
		$sync = $this->sync([
			[['HTTP/2 200', 'etag' => 'W/"new"'], ''],
			[['HTTP/2 200', 'etag' => 'W/"new"'], str_replace('SUMMARY:Test', 'SUMMARY:Changed', self::ICS)],
		]);
		$changed = $sync->fetch($old_token, $header, $token);
		$this->assertStringContainsString('SUMMARY:Changed', $changed);
		$this->assertSame($old_token, $token, 'token is only updated by the caller after a successful import');
	}

	public function testHashIgnoresDtstampAndLineEndingsButNotRealChanges()
	{
		$hash = SyncFetchCalendarStub::hash(self::ICS);
		$this->assertSame($hash, SyncFetchCalendarStub::hash(str_replace('20261014T070000Z', '20991231T235959Z', self::ICS)));
		$this->assertSame($hash, SyncFetchCalendarStub::hash(str_replace("\r\n", "\n", self::ICS)));
		$this->assertSame($hash, SyncFetchCalendarStub::hash(str_replace('DTSTAMP:', 'DTSTAMP;VALUE=DATE-TIME:', self::ICS)));
		$this->assertNotSame($hash, SyncFetchCalendarStub::hash(str_replace('SUMMARY:Test', 'SUMMARY:Test 2', self::ICS)));
		$this->assertNotSame($hash, SyncFetchCalendarStub::hash(str_replace('UID:1@', 'UID:2@', self::ICS)));
	}

	public function testSyncToken()
	{
		$hash = SyncFetchCalendarStub::hash(self::ICS);
		$this->assertSame('W/"x" sha256:'.$hash, SyncFetchCalendarStub::token('W/"x"', self::ICS));
		$this->assertSame('sha256:'.$hash, SyncFetchCalendarStub::token(null, self::ICS));
		$this->assertSame(['W/"x"', $hash], SyncFetchCalendarStub::parseToken('W/"x" sha256:'.$hash));
		$this->assertSame(['', $hash], SyncFetchCalendarStub::parseToken('sha256:'.$hash));
		$this->assertSame(['W/"x"', ''], SyncFetchCalendarStub::parseToken('W/"x"'), 'a plain ETag of an older sync keeps working');
		$this->assertSame(['', ''], SyncFetchCalendarStub::parseToken(null));
		$this->assertSame(['', ''], SyncFetchCalendarStub::parseToken(''));
	}
}
