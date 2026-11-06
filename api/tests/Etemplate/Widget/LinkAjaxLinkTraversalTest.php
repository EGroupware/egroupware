<?php
/**
 * EGroupware Api: regression test for Etemplate\Widget\Link::ajax_link()'s tmp-file traversal guard
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once __DIR__.'/../../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * Regression coverage for commit 133266b4ef ("hardened tmp-file uses", one of 4 sites - see
 * doc/ai/projects/security-regression-test-coverage.md): ajax_link() built a tmp-file path via
 * `$GLOBALS['egw_info']['server']['temp_dir'].'/'.$link['id']`, where $link['id'] is the raw,
 * client-supplied "temp name" for a file attachment - no traversal check. Unlike the sibling
 * Etemplate\Widget\File::validate() site, this one is a REAL, currently-exploitable arbitrary
 * file disclosure: the resulting array is handed to Api\Link::attach_file(), which calls
 * Vfs::copy_uploaded(..., $check_is_uploaded_file=false) - deliberately WITHOUT an
 * is_uploaded_file() check (see that call site's own "no is_uploaded_file() check!" comment) - so
 * whatever local file the constructed path resolves to gets copied into the VFS and exposed as an
 * attachment on the target entry, readable by anyone with access to it.
 *
 * Fix: `$tmp_name = basename((string)$link['id']);` before using it in the path - confines the
 * lookup to a plain filename directly inside temp_dir, discarding any directory component.
 *
 * Test approach: a real end-to-end call through ajax_link() -> Api\Link::link() ->
 * attach_file() -> Vfs::copy_uploaded(), using a fixture file placed in a SUBDIRECTORY of
 * temp_dir (not outside it - no real path traversal outside temp_dir is exercised, since basename()
 * defeats ANY embedded path separator identically, traversal or not, and confining the fixture to
 * temp_dir's own tree keeps this test from ever touching a real system file). Pass criterion: the
 * fixture must NOT get attached - proving the constructed tmp_name was confined to temp_dir's
 * top level (where the fixture does NOT exist), not the subdirectory path the client supplied.
 */
class LinkAjaxLinkTraversalTest extends LoggedInTest
{
	protected $contact_id;
	protected $fixture_dir;

	protected function tearDown() : void
	{
		if ($this->contact_id)
		{
			(new Api\Contacts())->delete($this->contact_id);
			$this->contact_id = null;
		}
		if ($this->fixture_dir && is_dir($this->fixture_dir))
		{
			array_map('unlink', glob($this->fixture_dir.'/*'));
			rmdir($this->fixture_dir);
		}
		parent::tearDown();
	}

	public function testSubdirectoryComponentInLinkIdIsNotAttached()
	{
		$temp_dir = rtrim($GLOBALS['egw_info']['server']['temp_dir'], '/');
		$subdir = 'link_traversal_test_'.bin2hex(random_bytes(4));
		$this->fixture_dir = "$temp_dir/$subdir";
		mkdir($this->fixture_dir, 0777, true);
		$marker_content = 'SECRET-MARKER-'.bin2hex(random_bytes(8));
		file_put_contents("$this->fixture_dir/marker.txt", $marker_content);

		$contacts = new Api\Contacts();
		$data = [
			'n_fn' => 'LinkAjaxLinkTraversalTest '.bin2hex(random_bytes(4)),
			'owner' => $GLOBALS['egw_info']['user']['account_id'],
		];
		$this->contact_id = $contacts->save($data);
		$this->assertNotFalse($this->contact_id, 'Did not create test contact');

		$links = [
			['app' => Api\Link::VFS_APPNAME, 'id' => "$subdir/marker.txt", 'name' => 'attack.txt'],
		];
		@Link::ajax_link('addressbook', $this->contact_id, $links);
		Api\Json\Response::get()->initResponseArray();	// reset the response singleton, same as PasswordDecryptAclTest.php

		$attached = Api\Link::get_links('addressbook', $this->contact_id, Api\Link::VFS_APPNAME);
		$this->assertEmpty($attached,
			'the fixture file, only reachable via the subdirectory component in link id, must never '.
			'get attached - if it does, the tmp-file path was not confined to a plain temp_dir basename '.
			'and the marker content is now exposed as an attachment on this entry');
	}
}
