<?php

/**
 * Test the basic Vfs::StreamWrapper
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs\Links;

require_once __DIR__ . '/../StreamWrapperBase.php';

use EGroupware\Api;
use EGroupware\Api\Vfs;


class StreamWrapperTest extends Vfs\StreamWrapperBase
{
	protected $entries = [];

	protected function setUp() : void
	{
		parent::setUp();

	}

	protected function tearDown() : void
	{
		// Do local stuff first, parent will remove stuff that is needed

		$bo = new \infolog_bo();
		foreach($this->entries as $entry)
		{
			$bo->delete($entry);
		}

		parent::tearDown();
	}

	public function testSimpleReadWrite(): string
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		return parent::testSimpleReadWrite();
	}

	public function testNoReadAccess(): void
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		parent::testNoReadAccess();
	}

	public function testWithAccess(): void
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		parent::testWithAccess();
	}
	/**
	 * Test that we can work through/with a symlink
	 *
	 * @throws Api\Exception\AssertionFailed
	 */
	public function testSymlinkFromFolder($test_file = '') : void
	{
		$info_id = $this->make_infolog();
		$this->files[] = $this->test_file = $this->getInfologFilename(null, $info_id);

		parent::testSymlinkFromFolder($this->test_file);
	}

	/**
	 * Links\StreamWrapper::url_stat() (Links/StreamWrapper.php:159-208) fakes
	 * a directory entry for /apps/$app/$id whenever the underlying sqlfs
	 * table has no real row for it yet (no file has ever been uploaded) -
	 * as long as the current user has read access to the linked entry
	 * itself. Not covered by the shared StreamWrapperBase tests, which all
	 * write a file first.
	 */
	public function testEntryDirIsVirtualDirectoryBeforeAnyFileUploaded() : void
	{
		$info_id = $this->make_infolog();
		$entry_dir = '/apps/infolog/' . $info_id;

		$this->assertTrue(Vfs::is_dir($entry_dir), 'Entry dir should exist virtually even with no real fs row');
		$this->assertFalse(Vfs::is_link($entry_dir));
	}

	/**
	 * Links\StreamWrapper::eacl()/get_eacl() are reimplemented as pure
	 * no-ops (Links/StreamWrapper.php:221-241) - access to a link entry is
	 * governed entirely by the underlying app's own ACL (via
	 * check_extended_acl()), custom eACLs can't be layered on top the way
	 * they can for plain sqlfs paths (see SqlfsBackendTest::
	 * testEaclGrantChangesAccessDecision for the sqlfs-side behavior this
	 * deliberately does NOT have).
	 */
	public function testEaclIsUnsupportedNoop() : void
	{
		$info_id = $this->make_infolog();
		$entry_dir = '/apps/infolog/' . $info_id;

		$this->assertFalse(Vfs::eacl($entry_dir, Vfs::READABLE, $GLOBALS['egw_info']['user']['account_id']));
		$this->assertFalse(Vfs::get_eacl($entry_dir));
	}

	/**
	 * Links\StreamWrapper::rmdir() is reimplemented to silently no-op
	 * (return true without touching anything) for the entry-dir itself
	 * (Links/StreamWrapper.php:296-308, "never delete entry-dir, as it makes
	 * attic inaccessible") - only a real sub-path underneath it can actually
	 * be removed.
	 */
	public function testRmdirOnEntryDirItselfIsNoop() : void
	{
		$info_id = $this->make_infolog();
		$entry_dir = '/apps/infolog/' . $info_id;
		$this->files[] = $test_file = $this->getInfologFilename(null, $info_id);
		file_put_contents(Vfs::PREFIX . $test_file, 'x');

		$this->assertTrue(Vfs::rmdir($entry_dir), 'rmdir() on the entry-dir itself should no-op, not fail');
		// the entry-dir (and its content) must still be usable afterward
		$this->assertTrue(Vfs::is_dir($entry_dir));
		$this->assertTrue(Vfs::file_exists($test_file));
	}

	protected function allowAccess(string $test_name, string &$test_file, int $test_user, string $needed) : void
	{
		// Make sure user has infolog run rights
		$command = new \admin_cmd_acl(true, $test_user,'infolog','run',Api\Acl::READ);
		$command->run();

		// We'll allow access by putting test user in responsible
		$so = new \EGroupware\Infolog\Storage();
		$element = $so->read(Array('info_id' => $this->entries[0]));
		$element['info_responsible'] = [$test_user];
		$so->write($element);
	}

	protected function mount() : void
	{
		$this->mountLinks('/apps');
	}

	/**
	 * Make an infolog entry
	 */
	protected function make_infolog()
	{
		$bo = new \infolog_bo();
		$element = array(
				'info_subject' => "Test infolog for #{$this->name()}",
				'info_des' => 'Test element for ' . $this->name() . "\n" . Api\DateTime::to(),
				'info_status' => 'open'
		);

		$element_id = $bo->write($element, true, true, true, true);
		$this->entries[] = $element_id;
		return $element_id;
	}

	/**
	 * Make a filename that reflects the current test
	 * @param $info_id
	 * @return string
	 * @throws \ReflectionException
	 */
	protected function getInfologFilename($path, $info_id)
	{
		if(is_null($path)) $path = '/apps/infolog/';
		if(substr($path,-1,1) !== '/') $path = $path . '/';
		$reflect = new \ReflectionClass($this);
		return $path .$info_id .'/'. $reflect->getShortName() . '_' . $this->name() . '.txt';
	}

}