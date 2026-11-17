<?php

namespace Storage;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestMerge.php';

/**
 * Tests for the "link to each entry" option of Merge::merge_entries()
 *
 * CONTRACT
 * A merged document is linked (moved into the entry's file folder) only for entries the user has
 * edit rights on. If no entry takes it, the document stays where it was merged to and the result
 * names it under 'not_linked', so the caller can tell the user where it is. It is never deleted
 * without having been copied to an entry.
 *
 * SETUP
 * A fake application is registered with Api\Link, whose 'file_access' answer the test controls
 * per entry id, so nothing depends on the ACL of the instance the test runs against. The
 * template is a plain text file in the user's home directory.
 */
class MergeLinkTest extends LoggedInTest
{
	const APP = 'test_merge_link';
	const EDITABLE = 101;
	const EDITABLE_TOO = 102;
	const READONLY = 201;
	const READONLY_TOO = 202;

	/**
	 * @var TestMerge
	 */
	private $merge;

	/**
	 * VFS path of the template
	 *
	 * @var string
	 */
	private $template;

	/**
	 * VFS paths created by a test, removed in tearDown()
	 *
	 * @var string[]
	 */
	private $files = [];

	/**
	 * Edit rights of the fake application: only ids in the 100s are editable
	 */
	public static function fileAccess($id, $required)
	{
		return $required == Api\Acl::READ || (int)$id < 200;
	}

	protected function setUp() : void
	{
		Api\Link::$app_register[self::APP] = [
			'file_access' => [self::class, 'fileAccess'],
			'title'       => function ($id) { return 'Entry ' . $id; },
		];
		// the /apps file folders are only writable for applications the user may run
		$GLOBALS['egw_info']['user']['apps'][self::APP] = ['name' => self::APP];
		$this->merge = new TestMerge();
		$this->merge->setReplacements(['$$replacement$$' => 'merged']);

		$this->template = Vfs::get_home_dir() . '/MergeLinkTest ' . uniqid() . '.txt';
		file_put_contents(Vfs::PREFIX . $this->template, '{{replacement}}');
		$this->files[] = $this->template;
	}

	protected function tearDown() : void
	{
		foreach([self::EDITABLE, self::EDITABLE_TOO, self::READONLY, self::READONLY_TOO] as $id)
		{
			$dir = Api\Link::vfs_path(self::APP, $id, '', true);
			foreach(Vfs::file_exists($dir) ? (array)Vfs::scandir($dir) : [] as $file)
			{
				Vfs::unlink($dir . '/' . $file);
			}
			if(Vfs::file_exists($dir))
			{
				Vfs::rmdir($dir);
			}
		}
		foreach($this->files as $file)
		{
			if(Vfs::file_exists($file))
			{
				Vfs::unlink($file);
			}
		}
		$this->files = [];
		unset(Api\Link::$app_register[self::APP], $GLOBALS['egw_info']['user']['apps'][self::APP], $_REQUEST['document']);
		parent::tearDown();
	}

	/**
	 * Merge the template for the given entries with "link to each entry" set
	 *
	 * @param int[] $ids
	 * @return array [paths of merged documents, paths reported as not linked]
	 */
	private function mergeAndLink(array $ids) : array
	{
		$_REQUEST['document'] = [$this->template];
		$result = TestMerge::merge_entries($ids, $this->merge, [
			'link'       => true,
			'app'        => self::APP,
			'pdf'        => false,
			'individual' => false,
			'download'   => false,
		], true);

		$paths = array_values(array_filter((array)$result, 'is_string'));
		$not_linked = array_merge(...array_column(array_filter((array)$result, 'is_array'), 'not_linked'));
		// whatever the merge left outside an entry folder is ours to remove
		foreach(array_merge($paths, $not_linked) as $path)
		{
			$this->files[] = Vfs::parse_url($path, PHP_URL_PATH) ?: $path;
		}
		return [$paths, $not_linked];
	}

	/**
	 * Names of the files in an entry's file folder
	 */
	private function entryFiles(int $id) : array
	{
		$dir = Api\Link::vfs_path(self::APP, $id, '', true);
		return Vfs::file_exists($dir) ? array_values((array)Vfs::scandir($dir)) : [];
	}

	public function testEditableEntryGetsTheDocument()
	{
		[$paths, $not_linked] = $this->mergeAndLink([self::EDITABLE]);

		$this->assertSame([], $not_linked);
		$this->assertCount(1, $paths);
		$files = $this->entryFiles(self::EDITABLE);
		$this->assertCount(1, $files, 'the document is in the entry folder');
		$this->assertSame('merged', file_get_contents(Api\Link::vfs_path(self::APP, self::EDITABLE, $files[0])));
		$this->assertStringContainsString('/apps/' . self::APP . '/' . self::EDITABLE . '/', $paths[0]);
	}

	public function testReadonlyEntryKeepsTheDocumentAndReportsWhere()
	{
		[$paths, $not_linked] = $this->mergeAndLink([self::READONLY]);

		$this->assertSame([], $this->entryFiles(self::READONLY), 'nothing is linked to the entry');
		$this->assertCount(1, $not_linked, 'the unlinked document is reported');
		$this->assertSame($paths, $not_linked, 'the reported location is where the document is');
		$this->assertStringStartsNotWith('/apps/', $not_linked[0]);
		$this->assertTrue(Vfs::file_exists($not_linked[0]), 'the document was not deleted');
		$this->assertSame('merged', file_get_contents(Vfs::PREFIX . $not_linked[0]));
	}

	public function testSeveralReadonlyEntriesKeepOneDocument()
	{
		[, $not_linked] = $this->mergeAndLink([self::READONLY, self::READONLY_TOO]);

		$this->assertCount(1, $not_linked);
		$this->assertTrue(Vfs::file_exists($not_linked[0]));
		$this->assertSame([], $this->entryFiles(self::READONLY));
		$this->assertSame([], $this->entryFiles(self::READONLY_TOO));
	}

	public function testMixedSelectionLinksOnlyToEditableEntries()
	{
		[$paths, $not_linked] = $this->mergeAndLink([self::READONLY, self::EDITABLE]);

		$this->assertSame([], $not_linked, 'an entry took the document, nothing is left over');
		$this->assertSame([], $this->entryFiles(self::READONLY));
		$this->assertCount(1, $this->entryFiles(self::EDITABLE));
		$this->assertStringContainsString('/apps/' . self::APP . '/' . self::EDITABLE . '/', $paths[0]);
	}

	public function testEveryEditableEntryGetsItsOwnCopy()
	{
		[, $not_linked] = $this->mergeAndLink([self::EDITABLE, self::EDITABLE_TOO]);

		$this->assertSame([], $not_linked);
		$this->assertCount(1, $this->entryFiles(self::EDITABLE));
		$this->assertCount(1, $this->entryFiles(self::EDITABLE_TOO));
	}
}
