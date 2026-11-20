<?php
/**
 * EGroupware Api: regression test for Accounts\Sql::name2id()'s unescaped (I)LIKE special chars
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
 * Accounts\Sql::name2id()'s case-insensitive account_lid lookup (the default, unless
 * case_sensitive_username is set) used to pass the caller's value straight into a LIKE comparison
 * without escaping LIKE's own special characters ('_' matches any single character, '%' any
 * sequence) - so a value like "sys_p" would also match the unrelated, pre-existing account
 * "sysop" ('_' standing in for 'o').
 */
class Name2idLikeWildcardTest extends LoggedInTest
{
	public function testWildcardCharactersDoNotMatchAnUnrelatedAccount()
	{
		$accounts = Api\Accounts::getInstance();

		// sanity: the built-in 'sysop' account must exist on any real install
		$this->assertNotFalse($accounts->name2id('sysop', 'account_lid', 'u'),
			"the built-in 'sysop' account must exist - if this fails, the test environment itself is broken");

		// '_' in "sys_p" must not act as a single-character wildcard matching "sysop"
		$this->assertFalse($accounts->name2id('sys_p', 'account_lid', 'u'),
			"'_' in a looked-up name must never act as a LIKE wildcard matching a different account");

		// '%' in "sys%" must not act as a wildcard matching "sysop" either
		$this->assertFalse($accounts->name2id('sys%', 'account_lid', 'u'),
			"'%' in a looked-up name must never act as a LIKE wildcard matching a different account");

		// the lookup must stay genuinely case-insensitive (no over-correction to case-sensitive "=")
		$this->assertSame($accounts->name2id('sysop', 'account_lid', 'u'), $accounts->name2id('SYSOP', 'account_lid', 'u'),
			'the lookup must still be case-insensitive after escaping LIKE special characters');
	}
}
