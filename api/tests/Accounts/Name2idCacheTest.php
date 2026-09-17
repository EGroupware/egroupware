<?php
/**
 * EGroupware Api: regression test for Accounts::name2id()'s per-$account_type caching
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Tests\Accounts;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for a cache-key bug in Api\Accounts::name2id() found while investigating
 * a batch of seemingly-unrelated CI failures (calendar, SmallParT, Collabora, Tracker all failing
 * with "Unknown account" errors on the well-known built-in 'Default' group).
 *
 * name2id() caches its result in self::$cache['name_list'][$which][$name], keyed only by $which
 * (eg. 'account_lid') and $name - NOT by the $account_type filter parameter ('u'/'g'/null), even
 * though $account_type genuinely changes what gets looked up (Accounts\Sql::name2id() adds
 * "AND account_type = ?" to the query when given). So a type-filtered lookup that legitimately
 * finds nothing - eg. name2id('Default', 'account_lid', 'u'), asking "is there a USER named
 * Default" when 'Default' is really a group - permanently cached `false` under a key that any
 * LATER, differently- or un-filtered lookup of the same name also reads, breaking it for the rest
 * of the process. This is reachable from real code:
 * Api\CalDAV\JsBase::parseAccount() (used by eg. SmallParT's REST API for its 'org' field) calls
 * name2id($value, ..., $user ? 'u' : 'g'), so a single REST request asking whether a group name
 * is a valid USER poisons every later, unrelated call site that relies on the group actually
 * existing (eg. admin_cmd_edit_user's "auto-assign the Default group as primary group" fallback,
 * used by any test that creates an account without specifying one).
 *
 * Fix: key the cache by $which and $account_type together.
 */
class Name2idCacheTest extends LoggedInTest
{
	public function testTypeFilteredMissDoesNotPoisonUnfilteredLookup()
	{
		$accounts = Api\Accounts::getInstance();

		// sanity: the built-in 'Default' group must exist on any real install
		$this->assertNotFalse($accounts->name2id('Default'),
			'the built-in Default group must exist - if this fails, the test environment itself is broken');

		// a type-filtered lookup asking "is there a USER named Default" - Default is a group, so
		// this must legitimately return false, exactly like the real code path in JsBase::parseAccount()
		$this->assertFalse($accounts->name2id('Default', 'account_lid', 'u'),
			'Default is a group, not a user - a user-type-filtered lookup for it must return false');

		// the bug: that false result must not have poisoned the cache for other lookups of the
		// same name - an unfiltered lookup, and a group-type-filtered one, must still resolve
		$this->assertNotFalse($accounts->name2id('Default'),
			'an unfiltered name2id() lookup for Default must still resolve after a type-filtered '.
			'miss for the same name - if this fails, the cache key collision bug has regressed');
		$this->assertNotFalse($accounts->name2id('Default', 'account_lid', 'g'),
			'a group-type-filtered lookup for Default must still resolve after a user-type-filtered '.
			'miss for the same name');
		$this->assertSame(2, $accounts->exists('Default'),
			'exists() (used by admin_cmd_edit_user\'s Default-group auto-assignment fallback) must '.
			'still recognize Default as an existing group');
	}
}
