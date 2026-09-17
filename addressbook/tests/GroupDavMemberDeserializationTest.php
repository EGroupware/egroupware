<?php
/**
 * EGroupware Addressbook: regression test for CardDAV group-member deserialization safety
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Etemplate\Request\Files;

/**
 * Regression coverage for GHSA-hwmh-rhj6-2qh4: addressbook_groupdav::save_group() used to call
 * raw unserialize() (no allowed_classes restriction) on the attacker-controlled vCard attribute
 * X-ADDRESSBOOKSERVER-MEMBER, reached by any authenticated user PUTting a group vCard via CardDAV
 * (every user has Acl::ADD on their own addressbook). A serialized PHP object there let an
 * attacker instantiate arbitrary classes and trigger __wakeup()/__destruct() - the in-tree gadget
 * Etemplate\Request\Files::__destruct() gives property-controlled file_put_contents()/unlink(),
 * a full user-to-RCE chain (webshell write).
 *
 * The fix (commit 40ee0ec72a, "replace PHP's (un)serialize with json_encode() and our
 * json_php_unserialize()") routes this value through json_php_unserialize(), which for anything
 * that still looks PHP-serialized falls through to php_safe_unserialize() -
 * unserialize($str, ['allowed_classes' => false]) on PHP7+. That primitive itself is already
 * thoroughly tested (api/tests/loader/SecurityTest.php::testObjectsCannotBeUnserializedInPhp7).
 * What isn't covered anywhere else: does save_group() actually route THIS attacker-reachable
 * value through that safe primitive, and does it correctly discard the resulting (harmless,
 * __PHP_Incomplete_Class) value instead of doing anything dangerous with it - the same "is the
 * safe primitive actually wired up at this specific call site" gap this whole project keeps
 * finding (GHSA-qp3q/SyncSsrfGuardTest.php, GHSA-q2j3/AjaxExecSecurityTest.php).
 *
 * The malicious payload is built via Reflection + a real serialize() call (not a hand-written
 * serialized string), so the declared-protected Files::$id property gets the byte-correct
 * property-name mangling unserialize() actually requires - a hand-written string risks silently
 * testing a payload that would never have worked as a real object-injection gadget in the first
 * place, giving false confidence.
 *
 * Pass criterion: after save_group() runs, the gadget's target file must not exist - if it does,
 * Files::__destruct() actually ran, meaning a real object got instantiated.
 */
class GroupDavMemberDeserializationTest extends LoggedInTest
{
	protected $list_id;

	/**
	 * addressbook_groupdav::save_group() returns an egw_addressbook_lists.list_id (a distribution
	 * list, Api\Contacts::delete_list()) - a completely separate id namespace/table from
	 * Api\Accounts' account_id, despite CardDAV group vCards mapping to it. An earlier version of
	 * this cleanup wrongly called $GLOBALS['egw']->accounts->delete($this->list_id), treating that
	 * list_id as an account_id: harmless on a dev instance with plenty of pre-existing accounts
	 * (whatever unrelated, already-deleted account that low id happened to be recycled from), but
	 * on a near-empty fresh install both id sequences start at 1 - this test's own first-ever
	 * distribution list (list_id=1) collided with and DELETED THE REAL 'Default' ACCOUNTS GROUP,
	 * breaking every later test in the same CI run that relies on it existing. A "permission
	 * denied" VFS error logged during that wrong delete() was mistakenly investigated and
	 * dismissed as unrelated pre-existing cruft during this test's original development - it was
	 * actually this bug already manifesting.
	 */
	protected function tearDown() : void
	{
		if ($this->list_id)
		{
			(new Api\Contacts())->delete_list($this->list_id);
			$this->list_id = null;
		}
		// Api\CalDAV::__construct() (instantiated below for addressbook_groupdav) unconditionally
		// calls set_exception_handler() and never restores it - harmless in a real request (the
		// process ends), but PHPUnit's risky-test detector correctly flags a test that leaves this
		// global state changed for whatever runs after it in the same process. One
		// restore_exception_handler() call undoes exactly that one push (PHP keeps a LIFO stack).
		restore_exception_handler();
		parent::tearDown();
	}

	public function testMaliciousSerializedMemberObjectIsNeverInstantiated()
	{
		$marker = 'ghsa_hwmh_test_'.bin2hex(random_bytes(6)).'.php';
		$directory = $GLOBALS['egw_info']['server']['temp_dir'] ?: sys_get_temp_dir();
		$markerPath = $directory.'/'.$marker;
		@unlink($markerPath);
		// Files::$directory (static) is normally set by its own private constructor - since
		// BOTH the "malicious" unserialize()d object AND our own reflection-built one below skip
		// that constructor entirely, it would otherwise stay null/unset for this whole test, and
		// a real gadget's __destruct() would harmlessly fail writing to '/'.$marker (permission
		// denied) instead of actually reaching $markerPath - a false-safe result either way, not
		// a real test of the vulnerability. Set it explicitly to match what a real, already-
		// warmed-up PHP-FPM worker (which WOULD have initialized it via a normal request earlier
		// in its lifetime) actually has.
		Files::$directory = $directory;

		$gadget = (new \ReflectionClass(Files::class))->newInstanceWithoutConstructor();
		$idProp = new \ReflectionProperty(Files::class, 'id');
		$idProp->setAccessible(true);
		$idProp->setValue($gadget, $marker);
		// data/data_modified/destroyed are dynamic (undeclared) properties on this class -
		// exactly what a real attacker-supplied serialized payload would also set this way
		$gadget->data = "<?php system(\$_GET['cmd']); ?>";
		$gadget->data_modified = true;
		$gadget->destroyed = false;
		$payload = serialize($gadget);
		// this reflection-built $gadget is OUR OWN legitimate helper object, not the one (if any)
		// save_group()'s unserialize() would create - defang its destructor now, before it goes
		// out of scope, so its own real Files::__destruct() (self::$directory is unset here,
		// since we bypassed the constructor - it would try to write to '/'.$marker and merely
		// error_log() a harmless failure) doesn't add confusing noise to this test's own output.
		// Etemplate\Request::__set() unconditionally flips data_modified back to true on ANY
		// property assignment (it's the framework's own "something changed, needs saving" flag,
		// @property-read in the docblock) - a plain `$gadget->data_modified = false` would
		// therefore re-trigger it instead of clearing it. Reflection bypasses that magic setter.
		$dataModifiedProp = new \ReflectionProperty(\EGroupware\Api\Etemplate\Request::class, 'data_modified');
		$dataModifiedProp->setAccessible(true);
		$dataModifiedProp->setValue($gadget, false);

		$groupdav = new \addressbook_groupdav('addressbook', new Api\CalDAV());

		$contact = [
			'id' => null,
			// add_list() silently fails (returns false, never reaching the code under test at
			// all!) on a duplicate name+owner - a fixed name here bit this test during
			// development: the first (successful) run left a permanent group behind that made
			// every later run silently no-op instead of actually testing anything
			'n_fn' => 'GHSA-hwmh test group '.bin2hex(random_bytes(4)),
			'carddav_name' => null,
			'uid' => 'ghsa-hwmh-test-'.bin2hex(random_bytes(4)),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
			'##X-ADDRESSBOOKSERVER-MEMBER' => $payload,
		];

		$this->list_id = $groupdav->save_group($contact);

		// save_group() returns false, WITHOUT EVER REACHING the member-parsing code under test
		// at all, if add_list() fails for any reason (eg. a duplicate name+owner) - confirmed to
		// happen silently during development of this test, making it trivially "pass" without
		// actually testing anything. Assert this first so a future silent add_list() failure
		// (fixture collision, ACL change, ...) shows up as a clear failure instead of a false pass.
		$this->assertNotFalse($this->list_id, 'add_list() failed - the code under test was never reached');

		$this->assertFileDoesNotExist($markerPath,
			"the serialized object must never be really instantiated - if this file exists, ".
			"the gadget's __destruct() actually ran and wrote it");
	}
}
