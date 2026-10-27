<?php
/**
 * Accounts search: a positive account_id list must restrict the SQL search itself
 *
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/LoggedInTest.php';

/**
 * Contract: Accounts::link_query() with $options['account_id'] returns only those accounts, even
 * when they sort far behind the num_rows limit of the unfiltered search.  Callers like the calendar
 * owner search rely on it to apply grants before the limit instead of after.
 *
 * Setup: take the last three matches (in sort order) of a search that has far more than 50 matches.
 * Pass: the unfiltered, limited search contains none of them, the filtered search returns exactly them.
 * Needs more than 53 accounts matching "user" (skipped otherwise).
 */
class AccountsSearchAccountIdFilterTest extends LoggedInTest
{
	public function testAccountIdFilterAppliedBeforeLimit()
	{
		$options = ['num_rows' => 5000, 'account_type' => 'both'];
		$all = array_keys(Accounts::link_query('user', $options));
		if (count($all) < 54)
		{
			$this->markTestSkipped('Needs more than 53 accounts matching "user"');
		}
		$late = array_slice($all, -3);

		$options = ['num_rows' => 50, 'account_type' => 'both'];
		$limited = Accounts::link_query('user', $options);
		$this->assertEmpty(array_intersect_key($limited, array_flip($late)), 'late matches must be beyond the limit');

		$options = ['num_rows' => 50, 'account_type' => 'both', 'account_id' => $late];
		$filtered = Accounts::link_query('user', $options);
		$this->assertEqualsCanonicalizing($late, array_keys($filtered));
	}
}
