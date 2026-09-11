<?php
/**
 * Test EGroupware addressbook_bo::ajax_pubkey_upload()'s input validation (Phase 5 item 1's
 * "Known follow-up" fix, doc/ai/projects/mail-pgp-signature-verification.md)
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;

/**
 * ajax_pubkey_upload() (addressbook/js/app.ts's pubkeyUploadStart(), replacing Et2VfsUpload's own
 * default raw-to-VFS write - see that JS method's own docblock for the full "why") - this file
 * covers only its early input-validation guard (an armored blob that isn't actually a PGP/S-MIME
 * key at all, rejected before ever touching a contact), which never reaches $this->search()/
 * read()/save() and so is safe to exercise directly, unlike the actual merge path
 * (merge_key_for_contact_id()/set_pgp_keys()/set_smime_keys()) - those share
 * AddressbookBoMultiKeyStorageTest.php's own pre-existing environment-hang limitation for any
 * $this->search()-touching call, already documented there, not re-tested here.
 *
 * Api\Json\Response is a process-wide singleton (Api\Json\Response::get()) - returnResult() both
 * reads AND resets its accumulated state (responseArray/hasData, see its own
 * initResponseArray()), so each test here is self-contained with no reflection needed to reset
 * anything between them.
 */
class AddressbookBoPubkeyUploadTest extends \EGroupware\Api\LoggedInTest
{
	private function bo() : addressbook_bo
	{
		return (new ReflectionClass(addressbook_bo::class))->newInstanceWithoutConstructor();
	}

	public function testRejectsAnInvalidPgpUpload()
	{
		$this->bo()->ajax_pubkey_upload(999999, true, "this is definitely not a PGP key");

		$result = Api\Json\Response::returnResult();

		$this->assertCount(1, $result);
		$this->assertSame('data', $result[0]['type']);
		$this->assertStringContainsString('PGP', $result[0]['data']['message']);
	}

	public function testRejectsAnInvalidSmimeUpload()
	{
		$this->bo()->ajax_pubkey_upload(999999, false, "this is definitely not an S/MIME cert");

		$result = Api\Json\Response::returnResult();

		$this->assertCount(1, $result);
		$this->assertSame('data', $result[0]['type']);
		$this->assertStringContainsString('S/MIME', $result[0]['data']['message']);
	}

	public function testRejectsEvenWithAMatchingClientSuppliedAddress()
	{
		// the format check happens before $addresses is ever consulted - a malicious/buggy client
		// claiming a match must not bypass it
		$this->bo()->ajax_pubkey_upload(999999, true, "garbage", ['addr@example.invalid']);

		$result = Api\Json\Response::returnResult();

		$this->assertStringContainsString('PGP', $result[0]['data']['message']);
	}
}
