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
 * Exercises the real Nominatim geocoding lookup (no mocking: geocodeAddress() is called via
 * self:: inside ajax_get_geolocation(), which - unlike static:: - is NOT late-static-bound and so
 * can't be overridden by a test subclass; same reasoning the PGP keyserver test's docblock gives
 * for not mocking its own http call) - a single one-off request, same as any real user's click.
 */
class AjaxGetGeolocationTest extends LoggedInTest
{
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

		$ui = new addressbook_ui();
		ob_start();
		$ui->ajax_get_geolocation($this->contact_id, 'one');
		ob_end_clean();

		$saved = $bo->read($this->contact_id);
		$this->assertNotEmpty($saved['geo'] ?? null,
			'the looked-up geo value must be cached on the contact');
	}
}
