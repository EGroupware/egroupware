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
 * No test talks to the public Nominatim service (it rate-limits shared CI addresses, which made
 * this test fail intermittently): the addressbook_ui subclasses below override the static::
 * seams - geocodeAddress() for the save test, fetchGeocode() for the parsing tests.
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
}
