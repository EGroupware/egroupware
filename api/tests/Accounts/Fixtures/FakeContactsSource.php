<?php
/**
 * EGroupware API - fake LDAP-shaped contacts source for Import tests
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests\Fixtures;

/**
 * Stands in for Api\Contacts\Ldap (reached via Api\Contacts::$so_accounts in the real code) as
 * the source Import::$contacts pulls contact rows (incl. "aliases", "jpegphoto", "dn") from -
 * see doc/ai/projects/accounts-import-test-coverage.md. Does NOT extend Contacts\Ldap: nothing
 * in Import type-checks this object, and its constructor would otherwise live-connect to LDAP.
 *
 * Only implements what Import::run()/setMembers() actually call: search() (the main per-account
 * contact-row driver) and read() (used only when account_import_type includes "groups").
 */
class FakeContactsSource
{
	/** @var array[] list of contact rows, each requiring at least keys "account_id" and "modified" */
	private array $contacts;

	/**
	 * @param array[] $contacts list of contact rows in Import's expected shape (see class docblock)
	 */
	public function __construct(array $contacts = [])
	{
		$this->contacts = $contacts;
	}

	/**
	 * Matches Contacts\Ldap::search()'s parameter order - Import::run() calls it positionally.
	 * Only implements what Import actually uses: the "modified>=..." incremental filter appended
	 * to $filter (Import::run() line ~268) and single-page results ($start[2] is set to '' to
	 * end Import's do/while pagination loop after the one page).
	 *
	 * @param string $criteria unused (Import always passes '')
	 * @param bool $only_keys unused (Import always passes false)
	 * @param string $order_by unused
	 * @param string|array $extra_cols unused - fixtures already carry every column
	 * @param string $wildcard unused
	 * @param string $empty unused
	 * @param string $op unused
	 * @param array|bool $start [$offset, $num_rows, &$cookie] - $start[2] is set to '' (no more pages)
	 * @param array|null $filter 'owner' => '0' plus optionally a 'modified>=TS' element
	 * @return array[] matching contact rows
	 */
	function search($criteria, $only_keys = true, $order_by = '', $extra_cols = '', $wildcard = '', $empty = false,
		$op = 'AND', $start = false, $filter = null, $join = '', $need_full_no_count = false)
	{
		$modified_since = null;
		foreach ((array)$filter as $key => $value)
		{
			if (is_int($key) && preg_match('/^modified>=(\d+)$/', $value, $matches))
			{
				$modified_since = (int)$matches[1];
			}
		}
		$result = array_values(array_filter($this->contacts, static function (array $contact) use ($modified_since)
		{
			return !isset($modified_since) || (int)($contact['modified'] ?? 0) >= $modified_since;
		}));

		if (is_array($start))
		{
			$start[2] = '';    // single page only - fixtures are small enough to not need real pagination
		}
		return $result;
	}

	function read($id)
	{
		foreach ($this->contacts as $contact)
		{
			if (($contact['account_id'] ?? null) == $id || ($contact['id'] ?? null) === $id)
			{
				return $contact;
			}
		}
		return false;
	}
}
