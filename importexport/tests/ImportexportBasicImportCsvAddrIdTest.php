<?php
/**
 * EGroupware importexport: tests for importexport_basic_import_csv::addr_id()
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * addr_id() searches the real addressbook (Api\Contacts) by family/given/org name -
 * this is a genuine DB lookup, so needs a logged-in session. A temp contact is created
 * in setUp() and deleted in tearDown() (even if the test fails), so no test data is
 * left in the shared dev DB.
 *
 * Only the family+given lookup path is covered here (both with and without a real
 * match). While investigating this method for test coverage, the org-name-only and
 * single-combined-name (family only, matched against n_fn) paths turned out to already
 * be broken on PHP 8 and were deliberately left as-is (not part of this pass's approved
 * fixes): they call Api\Contacts::read() with extra search-style arguments
 * (class.importexport_basic_import_csv.inc.php:574,577,586) that Contacts::read()
 * (which only accepts $contact_id, $ignore_acl) silently ignores - so these always look
 * up contact id 0 (never found) instead of actually searching. Do not use this test
 * file as evidence those two paths work - they don't, and are not tested here on
 * purpose.
 *
 * (Previously, even the working family+given path crashed with a TypeError -
 * "count(): ... null given" - whenever there was genuinely no match, because
 * Api\Contacts::read()/search() are documented to return an array OR false, never
 * guaranteed an array, and addr_id() called count() on that unconditionally. Fixed by
 * normalising every $addrs assignment to `?: array()`; testNoMatchReturnsFalse below is
 * the regression test for that fix.)
 */
class ImportexportBasicImportCsvAddrIdTest extends \EGroupware\Api\AppTest
{
	private $contact_id;
	private $family;
	private $given;

	protected function setUp(): void
	{
		$suffix = bin2hex(random_bytes(4));
		$this->family = 'PhpunitAddrIdFamily'.$suffix;
		$this->given = 'PhpunitAddrIdGiven'.$suffix;

		$contacts = new EGroupware\Api\Contacts();
		$data = array(
			'n_family' => $this->family,
			'n_given' => $this->given,
			'org_name' => 'PhpunitAddrIdOrg'.$suffix,
		);
		$contacts->save($data);
		$this->contact_id = $data['id'];
		$this->assertNotEmpty($this->contact_id, 'test contact must have been created before the addr_id() test runs');
	}

	protected function tearDown(): void
	{
		if ($this->contact_id)
		{
			// Deliberately bypass Api\Contacts::delete() here: cleanup must not
			// depend on whatever permission/business-logic checks it applies (found
			// via this test occasionally leaving contacts behind) - go straight to
			// the underlying table.
			$so = new EGroupware\Api\Storage\Base('api', 'egw_addressbook');
			$so->delete(array('contact_id' => $this->contact_id));
			$this->contact_id = null;
		}
	}

	/**
	 * A family + given name match (the one lookup path in addr_id() that actually
	 * works) must return the matching contact's id.
	 */
	public function testMatchesByFamilyAndGivenName()
	{
		$result = importexport_basic_import_csv::addr_id($this->family, $this->given);

		$this->assertEquals($this->contact_id, $result,
			'addr_id() must find the contact by family+given name (search() may return the id as a numeric string)');
	}

	/**
	 * Regression test for the count(null)/count(false) TypeError fix: a family+given
	 * combination that matches no contact must return false, not fatally error.
	 */
	public function testNoMatchReturnsFalseInsteadOfCrashing()
	{
		$result = importexport_basic_import_csv::addr_id('NoSuchFamilyXYZ123', 'NoSuchGivenXYZ123');

		$this->assertFalse($result);
	}
}
