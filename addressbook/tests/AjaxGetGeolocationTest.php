<?php
/**
 * EGroupware Addressbook: test addressbook_ui::ajax_get_geolocation()'s caching save
 *
 * @link https://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Regression test for ticket #90341's follow-up (Birgit, 2026-10-15): ajax_get_geolocation()
 * passed an array literal straight to Contacts::save(&$contact, ...), a by-reference parameter -
 * PHP 8 rejects passing a non-variable expression by reference with a fatal
 * "could not be passed by reference" Error, which the surrounding catch (\Exception $e) never
 * even caught (Error doesn't extend Exception), crashing the whole request.
 *
 * Only testLiveNominatimStillAnswersAsExpected talks to the public Nominatim service, and it
 * skips when the service cannot be asked (it rate-limits shared CI addresses, which used to make
 * this test fail intermittently).  The others override the static:: seams of addressbook_ui -
 * geocodeAddress() for the save test, fetchGeocode() for the parsing tests.
 */
class AjaxGetGeolocationTest extends LoggedInTest
{
	const GEO = '48.775846,9.182932';
	protected ?int $contact_id = null;

	protected function tearDown() : void
	{
		if($this->contact_id)
		{
			(new Api\Contacts())->delete($this->contact_id);
		}
		parent::tearDown();
	}

	public function testSavesLookedUpGeoWithoutError() : void
	{
		$bo = new Api\Contacts();
		$contact = [
			'n_family' => 'AjaxGetGeolocationTest',
			'adr_one_street' => 'Marienstr. 1',
			'adr_one_postalcode' => '70178',
			'adr_one_locality' => 'Stuttgart',
			'adr_one_countrycode' => 'DE',
		];
		$this->contact_id = $bo->save($contact);
		$this->assertNotFalse($this->contact_id);

		$ui = new class extends addressbook_ui {
			public static array $addresses = [];
			protected static function geocodeAddress($address)
			{
				self::$addresses[] = $address;
				return AjaxGetGeolocationTest::GEO;
			}
		};
		$ui::$addresses = [];
		ob_start();
		$ui->ajax_get_geolocation($this->contact_id, 'one');
		ob_end_clean();

		$this->assertCount(1, $ui::$addresses);
		$this->assertStringContainsString('Stuttgart', $ui::$addresses[0]);
		$saved = $bo->read($this->contact_id);
		$this->assertSame(self::GEO, $saved['geo'] ?? null,
			'the looked-up geo value must be cached on the contact');
	}

	/**
	 * Run a canned Nominatim body through geocodeAddress(), i.e. the real parsing
	 *
	 * @param ?string $body what fetchGeocode() returns, null for a failed request
	 * @return ?string
	 */
	protected function geocodeCanned(?string $body) : ?string
	{
		$ui = new class extends addressbook_ui {
			public static ?string $body = null;
			protected static function fetchGeocode($address)
			{
				return self::$body;
			}
			public static function geocode($address)
			{
				return static::geocodeAddress($address);
			}
		};
		$ui::$body = $body;
		return $ui::geocode('Marienstr. 1 70178 Stuttgart');
	}

	public function testParsesFirstMatchRoundedToSixDecimals() : void
	{
		$this->assertSame('48.775846,9.182932', $this->geocodeCanned(
			'[{"lat":"48.7758459","lon":"9.1829321","display_name":"Stuttgart"}]'));
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('unusableResponses')]
	public function testUnusableResponseGivesNull(?string $body) : void
	{
		$this->assertNull($this->geocodeCanned($body));
	}

	public static function unusableResponses() : array
	{
		return [
			'request failed' => [null],
			'no match' => ['[]'],
			'not JSON' => ['<html>Too Many Requests</html>'],
			'JSON but not a list' => ['"slow down"'],
			'lat missing' => ['[{"lon":"9.18"}]'],
			'lon missing' => ['[{"lat":"48.77"}]'],
		];
	}

	/**
	 * Decide what a live Nominatim answer means for the contract test below
	 *
	 * Only a problem on our side of the contract is a failure.  Not being able to ask at all
	 * (no network, DNS, timeout), being throttled or blocked (429, 403 - a shared CI address
	 * looks the same as a blocked User-Agent) or a server error (5xx) says nothing about whether
	 * the API still works, so those skip.  An answer that is not what we ask for - a redirect or
	 * 4xx (the endpoint moved or was removed), or a 200 whose body does not parse - is the API
	 * having changed under us.
	 *
	 * @param int $status HTTP status, 0 if there was no HTTP answer
	 * @return string 'skip' or 'check' (the body must parse to a position)
	 */
	public static function classifyNominatimStatus(int $status) : string
	{
		return $status === 0 || $status === 403 || $status === 429 || $status >= 500 ? 'skip' : 'check';
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('statusClasses')]
	public function testClassifyNominatimStatus(int $status, string $expected) : void
	{
		$this->assertSame($expected, self::classifyNominatimStatus($status));
	}

	public static function statusClasses() : array
	{
		return [
			'no answer' => [0, 'skip'],
			'blocked' => [403, 'skip'],
			'throttled' => [429, 'skip'],
			'server error' => [500, 'skip'],
			'unavailable' => [503, 'skip'],
			'ok' => [200, 'check'],
			'moved' => [301, 'check'],
			'removed' => [404, 'check'],
			'bad request' => [400, 'check'],
			'gone' => [410, 'check'],
		];
	}

	/**
	 * Canary for the real service: our request is still understood and the answer still parses.
	 *
	 * Asks for a city, not a street, so a change in OpenStreetMap's data does not fail it; only the
	 * API (endpoint, parameters, response format) or the service going away does.
	 */
	#[\PHPUnit\Framework\Attributes\Group('network')]
	public function testLiveNominatimStillAnswersAsExpected() : void
	{
		$ui = new class extends addressbook_ui {
			public static function request($address) : array
			{
				return static::nominatimRequest($address);
			}
			public static function parse($body) : ?string
			{
				return static::parseGeocode($body);
			}
		};
		$response = $ui::request('Stuttgart, Germany');

		if (self::classifyNominatimStatus($response['status']) === 'skip')
		{
			$this->markTestSkipped("Nominatim could not be asked (HTTP {$response['status']} {$response['error']}): "
				.'says nothing about whether its API still works');
		}
		$this->assertSame(200, $response['status'], 'Nominatim no longer answers our search request');

		$geo = $ui::parse((string)$response['body']);
		$this->assertNotNull($geo, 'Nominatim answered, but no position could be read from: '
			.substr((string)$response['body'], 0, 300));
		[$lat, $lon] = array_map('floatval', explode(',', $geo));
		$this->assertEqualsWithDelta(48.78, $lat, 0.3, 'latitude is not in the Stuttgart area');
		$this->assertEqualsWithDelta(9.18, $lon, 0.3, 'longitude is not in the Stuttgart area');
	}
}
