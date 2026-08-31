<?php
/**
 * EGroupware API - fake "contacts stored in the account source" backend for write-back tests
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Accounts\Tests\Fixtures;

/**
 * Stands in for the real Api\Contacts\Ldap object Api\Contacts\Storage's constructor would assign
 * to $this->so_accounts (`new $class()` where $class = 'EGroupware\Api\Contacts\'.ucfirst($account_repository)`
 * - a live-connecting LDAP object, same problem class as Api\Accounts\Ldap - see
 * doc/ai/projects/accounts-import-test-coverage.md, "Why not mock the LDAP protocol").
 *
 * Only implements what Contacts\Storage::save() actually uses on it: the `$data`/`save()`
 * Api\Storage\Base-style contract (assign to ->data, call save() with no args, read
 * ->data['id']/['etag'] back afterwards) - see Storage::save()'s `$this->so_accounts->data = ...;
 * $error_nr = $this->so_accounts->save();` pair.
 */
class FakeContactsSoAccounts
{
	/** @var array the contact data to save - set by the caller before save(), like Api\Storage\Base */
	public array $data = [];

	/** @var array<string, array> every successful save()'s $data, keyed by the id it was saved under - for test assertions */
	public array $saved = [];

	/** @var int|string|false what save() should return next - falsy (0/false) = success, truthy = "error" */
	public $nextSaveResult = 0;

	function save()
	{
		if ($this->nextSaveResult)
		{
			return $this->nextSaveResult;
		}
		$this->saved[$this->data['id']] = $this->data;
		return 0;
	}
}
