<?php

/**
 * Test Api\Vfs's fixture-backed facade methods: the CRUD/stat/permission/eACL
 * surface most app code actually calls (as opposed to the pure path-helpers
 * covered by PathHelpersTest, or the per-backend behavior covered by the
 * StreamWrapper*Test suites).
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class FacadeTest extends LoggedInTest
{
	/**
	 * @var string root of a scratch area explicitly mounted onto sqlfs:// for
	 * this test class, see setUp() - deliberately NOT relying on wherever '/'
	 * or '/home' happen to be mounted by default (in this dev environment
	 * that's stylite.s3://, which has no working local test backend, see
	 * doc/ai/projects/vfs-test-coverage.md Phase 6).
	 */
	protected $test_root;

	/**
	 * @var string[] paths to clean up in tearDown(), in creation order
	 */
	protected $files = [];

	protected function setUp() : void
	{
		parent::setUp();

		$this->test_root = '/facadetest_' . substr(md5(uniqid('', true)), 0, 8);
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		// Deliberately NOT followed by Vfs\StreamWrapper::init_static(): that
		// unconditionally reloads self::$fstab from the server-persisted
		// config whenever one exists (StreamWrapper.php:1041-1044), which
		// would immediately wipe this non-persistent ($persistent_mount=
		// false) mount right back out in any environment that happens to
		// have a persisted server-wide fstab override (as this dev
		// environment does, for stylite.s3:// - see
		// doc/ai/projects/vfs-test-coverage.md Phase 6). Vfs::mount() already
		// does everything needed (incl. load_wrapper()) for the mount to be
		// usable immediately.
		$this->assertTrue(
			Vfs::mount('sqlfs://default' . $this->test_root, $this->test_root, false, false),
			"Unable to mount a scratch sqlfs:// area at $this->test_root"
		);
		Vfs::clearstatcache();
		Vfs::mkdir($this->test_root);
		// A dir created while Vfs::$is_root is true is owned uid=0/gid=0 with
		// no owner/group mode bits at all - hand it to the real test user, or
		// nothing below can be written by them.
		Vfs::chown($this->test_root, $GLOBALS['egw_info']['user']['account_id']);
		Vfs::chmod($this->test_root, 0750);
		Vfs::$is_root = $backup;
		Vfs::clearstatcache();
	}

	protected function tearDown() : void
	{
		// Session-only eACLs (Vfs::eacl($url,...,$session_only=true)) are NOT
		// scoped to $this->test_root and outlive it in Api\Cache's session
		// storage - if left behind they leak into every later test in this
		// same PHPUnit process (one shared PHP session for the whole run),
		// and can trip a real bug: Vfs::get_eacl() used to crash with a
		// TypeError (see api/src/Vfs.php:884-911 fix) whenever a session
		// eACL exists for a path with no backend-persisted eACL - which the
		// notification hooks (filemanager_hooks::vfs_hooks()) hit on every
		// later mkdir/rmdir, in completely unrelated tests.
		Api\Cache::setSession(Vfs::class, Vfs::SESSION_EACL, null);

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		foreach(array_reverse($this->files) as $file)
		{
			if(Vfs::is_dir($file) && !Vfs::is_link($file))
			{
				Vfs::remove($file);
			}
			elseif(Vfs::file_exists($file) || Vfs::is_link($file))
			{
				Vfs::unlink($file);
			}
		}
		if($this->test_root)
		{
			Vfs::remove($this->test_root);
			Vfs::umount($this->test_root);
		}
		Vfs::$is_root = $backup;
		parent::tearDown();
	}

	protected function getFilename($suffix = '') : string
	{
		$reflect = new \ReflectionClass($this);
		return $this->test_root . '/' . $reflect->getShortName() . '_' . $this->name() . $suffix;
	}

	// ---------------------------------------------------------------
	// mkdir / rmdir / touch / unlink
	// ---------------------------------------------------------------

	public function testMkdirRmdir() : void
	{
		$dir = $this->getFilename('_dir');
		$this->assertTrue(Vfs::mkdir($dir), "mkdir($dir) failed");
		$this->assertTrue(Vfs::is_dir($dir));
		$this->assertTrue(Vfs::rmdir($dir), "rmdir($dir) failed");
		$this->assertFalse(Vfs::file_exists($dir));
	}

	public function testMkdirRecursive() : void
	{
		$base = $this->getFilename('_recursive');
		$this->files[] = $base;
		$deep = $base . '/sub/deep';
		$this->assertTrue(Vfs::mkdir($deep, 0750, true), "recursive mkdir failed");
		$this->assertTrue(Vfs::is_dir($deep));
		$this->assertTrue(Vfs::is_dir($base . '/sub'));
	}

	public function testTouchCreatesFile() : void
	{
		$file = $this->files[] = $this->getFilename();
		$this->assertFalse(Vfs::file_exists($file));
		$this->assertTrue(Vfs::touch($file));
		$this->assertTrue(Vfs::file_exists($file));
	}

	public function testTouchSetsMtime() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		$past = time() - 100000;
		$this->assertTrue(Vfs::touch($file, $past));
		Vfs::clearstatcache();
		$this->assertEquals($past, Vfs::stat($file)['mtime']);
	}

	public function testUnlink() : void
	{
		$file = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		$this->assertTrue(Vfs::file_exists($file));
		$this->assertTrue(Vfs::unlink($file));
		$this->assertFalse(Vfs::file_exists($file));
	}

	// ---------------------------------------------------------------
	// copy / rename / remove
	// ---------------------------------------------------------------

	public function testCopy() : void
	{
		$src = $this->files[] = $this->getFilename();
		$dst = $this->files[] = $this->getFilename('.copy');
		file_put_contents(Vfs::PREFIX . $src, 'contents to copy');

		// copy() returns the destination's stat array on success, not a plain
		// boolean (it delegates to copy_uploaded())
		$this->assertNotFalse(Vfs::copy($src, $dst));
		$this->assertEquals('contents to copy', file_get_contents(Vfs::PREFIX . $dst));
		// source is untouched by a copy
		$this->assertTrue(Vfs::file_exists($src));
	}

	public function testRename() : void
	{
		$src = $this->getFilename();
		$dst = $this->files[] = $this->getFilename('.renamed');
		file_put_contents(Vfs::PREFIX . $src, 'contents to move');

		$this->assertTrue(Vfs::rename($src, $dst));
		$this->assertFalse(Vfs::file_exists($src));
		$this->assertEquals('contents to move', file_get_contents(Vfs::PREFIX . $dst));
	}

	public function testRemoveRecursive() : void
	{
		$dir = $this->getFilename('_removeme');
		Vfs::mkdir($dir);
		file_put_contents(Vfs::PREFIX . $dir . '/file.txt', 'x');
		Vfs::mkdir($dir . '/sub');
		file_put_contents(Vfs::PREFIX . $dir . '/sub/file2.txt', 'x');

		Vfs::remove($dir);
		$this->assertFalse(Vfs::file_exists($dir));
	}

	public static function protectedDirProvider() : array
	{
		return [
			'root' => ['/'],
			'home' => ['/home'],
			'apps' => ['/apps'],
		];
	}

	/**
	 * Vfs::remove() refuses to touch a small set of protected top-level dirs,
	 * see Vfs::isProtectedDir() (api/src/Vfs.php:683-688).
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('protectedDirProvider')]
	public function testRemoveRefusesProtectedDir(string $dir) : void
	{
		$this->expectException(Exception\ProtectedDirectory::class);
		Vfs::remove($dir);
	}

	// ---------------------------------------------------------------
	// stat / lstat / is_dir / is_link / file_exists
	// ---------------------------------------------------------------

	public function testStatNonExistentReturnsNull() : void
	{
		$this->assertNull(Vfs::stat($this->getFilename('_does_not_exist')));
	}

	public function testStatExisting() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'hello');

		$stat = Vfs::stat($file);
		$this->assertIsArray($stat);
		$this->assertEquals(5, $stat['size']);
	}

	public function testIsDirIsLinkFileExists() : void
	{
		$dir = $this->files[] = $this->getFilename('_isdir');
		Vfs::mkdir($dir);
		$this->assertTrue(Vfs::is_dir($dir));
		$this->assertFalse(Vfs::is_link($dir));
		$this->assertTrue(Vfs::file_exists($dir));

		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		$this->assertFalse(Vfs::is_dir($file));
		$this->assertTrue(Vfs::file_exists($file));
	}

	/**
	 * Vfs::clearstatcache() must make a later stat() see a write that
	 * happened through a channel (plain file_put_contents()) the stream
	 * wrapper itself didn't just perform and cache the result of.
	 */
	public function testClearstatcache() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'short');
		$size_short = Vfs::stat($file)['size'];

		file_put_contents(Vfs::PREFIX . $file, 'a much longer content string here');
		Vfs::clearstatcache();
		$size_long = Vfs::stat($file)['size'];

		$this->assertNotEquals($size_short, $size_long);
		$this->assertEquals(strlen('a much longer content string here'), $size_long);
	}

	// ---------------------------------------------------------------
	// chmod / chown / chgrp
	// ---------------------------------------------------------------

	public function testChmod() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');

		$this->assertTrue(Vfs::chmod($file, 0640));
		Vfs::clearstatcache();
		$this->assertEquals(0640, Vfs::stat($file)['mode'] & 0777);
	}

	/**
	 * chown requires root rights (Vfs::$is_root), see Vfs::chown()'s docblock.
	 */
	public function testChownRequiresRoot() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		$original_uid = $GLOBALS['egw_info']['user']['account_id'];
		$other_uid = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);
		$this->assertNotEquals($original_uid, $other_uid, 'Test needs two distinct real accounts');

		$this->assertFalse(Vfs::chown($file, $other_uid), 'chown succeeded without root rights');

		Vfs::$is_root = true;
		try
		{
			$this->assertTrue(Vfs::chown($file, $other_uid));
			Vfs::clearstatcache();
			$this->assertEquals($other_uid, Vfs::stat($file)['uid']);
		}
		finally
		{
			// restore ownership, so our own (non-root) teardown can still remove the file
			Vfs::chown($file, $original_uid);
			Vfs::$is_root = false;
		}
	}

	public function testChgrp() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');

		Vfs::$is_root = true;
		try
		{
			$this->assertTrue(Vfs::chgrp($file, 'Default'));
			Vfs::clearstatcache();
			// Accounts::name2id() returns group ids as negative (EGroupware's
			// account-id convention); stat()'s 'gid' is the plain, unsigned
			// group number, so compare against abs().
			$this->assertEquals(
				abs($GLOBALS['egw']->accounts->name2id('Default')),
				Vfs::stat($file)['gid']
			);
		}
		finally
		{
			Vfs::$is_root = false;
		}
	}

	// ---------------------------------------------------------------
	// is_readable / is_writable / is_executable (mode-bit gated)
	// ---------------------------------------------------------------

	public function testIsReadableWritableExecutableFollowModeBits() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');

		Vfs::chmod($file, 0700);
		Vfs::clearstatcache();
		$this->assertTrue(Vfs::is_readable($file));
		$this->assertTrue(Vfs::is_writable($file));
		$this->assertTrue(Vfs::is_executable($file));

		Vfs::chmod($file, 0400);
		Vfs::clearstatcache();
		$this->assertTrue(Vfs::is_readable($file));
		$this->assertFalse(Vfs::is_writable($file));
		$this->assertFalse(Vfs::is_executable($file));
	}

	// ---------------------------------------------------------------
	// eacl / get_eacl
	// ---------------------------------------------------------------

	public function testEaclSetGetDelete() : void
	{
		$dir = $this->files[] = $this->getFilename('_eacldir');
		Vfs::mkdir($dir);
		$other_uid = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);

		// eacl() can return a non-strict-boolean truthy value (eg. an int from
		// the underlying backend call), not necessarily literal true
		$this->assertNotFalse(Vfs::eacl($dir, Vfs::READABLE, $other_uid));

		// A backend-persisted (non-session-only) entry's 'path' comes back as
		// the full backend URL (eg. "sqlfs://demo@default/facadetest_.../dir"),
		// NOT the plain vfs path that session-only entries use (Vfs::eacl()'s
		// $session_only branch, api/src/Vfs.php:864-870) - match on suffix
		// rather than exact equality to cover both shapes.
		$matches_dir = fn($e) => str_ends_with($e['path'], $dir) && (int)$e['owner'] === $other_uid;

		$eacl = Vfs::get_eacl($dir);
		$this->assertIsArray($eacl);
		$match = current(array_filter($eacl, $matches_dir));
		$this->assertNotFalse($match, 'Set eACL not found in get_eacl(): ' . json_encode($eacl));
		$this->assertEquals(Vfs::READABLE, $match['rights']);

		// null rights deletes the entry
		$this->assertNotFalse(Vfs::eacl($dir, null, $other_uid));
		$eacl_after = Vfs::get_eacl($dir);
		$still_there = array_filter((array)$eacl_after, $matches_dir);
		$this->assertCount(0, $still_there, 'eACL entry still present after deletion');
	}

	public function testEaclSessionOnly() : void
	{
		$dir = $this->files[] = $this->getFilename('_sesseacldir');
		Vfs::mkdir($dir);
		$other_uid = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);

		$this->assertTrue(Vfs::eacl($dir, Vfs::READABLE, $other_uid, true));

		$eacl = Vfs::get_eacl($dir);
		$match = current(array_filter((array)$eacl, fn($e) => $e['path'] === $dir && (int)$e['owner'] === $other_uid));
		$this->assertNotFalse($match, 'Session-only eACL not returned by get_eacl()');
	}

	// ---------------------------------------------------------------
	// scheme2class / load_wrapper
	// ---------------------------------------------------------------

	public function testScheme2classKnownSchemes() : void
	{
		$this->assertEquals(\EGroupware\Api\Vfs\Sqlfs\StreamWrapper::class, Vfs::scheme2class('sqlfs'));
		$this->assertEquals(\EGroupware\Api\Vfs\Links\StreamWrapper::class, Vfs::scheme2class('links'));
	}

	public function testScheme2classUnknownSchemeReturnsFalsy() : void
	{
		$this->assertFalse(class_exists((string)Vfs::scheme2class('no_such_scheme_at_all')));
	}

	public function testLoadWrapperKnownScheme() : void
	{
		$this->assertTrue(Vfs::load_wrapper('sqlfs'));
		$this->assertTrue(in_array('sqlfs', stream_get_wrappers()));
	}

	// ---------------------------------------------------------------
	// find()
	// ---------------------------------------------------------------

	public function testFindReturnsAllEntries() : void
	{
		$file1 = $this->getFilename('_f1');
		file_put_contents(Vfs::PREFIX . $file1, 'x');
		$subdir = $this->getFilename('_dir');
		Vfs::mkdir($subdir);
		$file2 = $subdir . '/f2';
		file_put_contents(Vfs::PREFIX . $file2, 'y');

		$found = Vfs::find($this->test_root);
		$this->assertContains($file1, $found);
		$this->assertContains($subdir, $found);
		$this->assertContains($file2, $found);
	}

	public function testFindTypeFilter() : void
	{
		$file = $this->getFilename('_f');
		file_put_contents(Vfs::PREFIX . $file, 'x');
		$dir = $this->getFilename('_d');
		Vfs::mkdir($dir);

		$files_only = Vfs::find($this->test_root, ['type' => 'f']);
		$this->assertContains($file, $files_only);
		$this->assertNotContains($dir, $files_only);

		$dirs_only = Vfs::find($this->test_root, ['type' => 'd']);
		$this->assertContains($dir, $dirs_only);
		$this->assertNotContains($file, $dirs_only);
	}

	public function testFindMaxdepth() : void
	{
		$sub = $this->getFilename('_sub');
		Vfs::mkdir($sub);
		$deep = $sub . '/deep';
		Vfs::mkdir($deep);
		$deep_file = $deep . '/f.txt';
		file_put_contents(Vfs::PREFIX . $deep_file, 'x');

		// maxdepth=1: only test_root's direct children, not anything nested further
		$shallow = Vfs::find($this->test_root, ['maxdepth' => 1]);
		$this->assertContains($sub, $shallow);
		$this->assertNotContains($deep, $shallow);
		$this->assertNotContains($deep_file, $shallow);

		$all = Vfs::find($this->test_root);
		$this->assertContains($deep_file, $all);
	}

	public function testFindNamePattern() : void
	{
		$txt = $this->getFilename('_match.txt');
		file_put_contents(Vfs::PREFIX . $txt, 'x');
		$log = $this->getFilename('_nomatch.log');
		file_put_contents(Vfs::PREFIX . $log, 'x');

		$found = Vfs::find($this->test_root, ['name' => '*.txt']);
		$this->assertContains($txt, $found);
		$this->assertNotContains($log, $found);
	}

	// ---------------------------------------------------------------
	// lock / unlock / checkLock
	// ---------------------------------------------------------------

	public function testLockUnlock() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');

		$token = null;
		$timeout = 3600;
		$owner = null;
		$scope = 'exclusive';
		$type = 'write';
		$this->assertTrue(Vfs::lock($file, $token, $timeout, $owner, $scope, $type));
		$this->assertNotEmpty($token);

		$lock = Vfs::checkLock($file);
		$this->assertIsArray($lock);
		$this->assertEquals('exclusive', $lock['scope']);
		$this->assertEquals('write', $lock['type']);

		$this->assertTrue(Vfs::unlock($file, $token));
		$this->assertFalse(Vfs::checkLock($file));
	}

	public function testLockExclusiveRejectsSecondLock() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');

		$token1 = null;
		$timeout1 = 3600;
		$owner1 = null;
		$scope1 = 'exclusive';
		$type1 = 'write';
		$this->assertTrue(Vfs::lock($file, $token1, $timeout1, $owner1, $scope1, $type1));

		$token2 = null;
		$timeout2 = 3600;
		$owner2 = null;
		$scope2 = 'exclusive';
		$type2 = 'write';
		$this->assertFalse(Vfs::lock($file, $token2, $timeout2, $owner2, $scope2, $type2));

		Vfs::unlock($file, $token1);
	}

	public function testLockRequiresWriteAccess() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		Vfs::chmod($file, 0400);

		$token = null;
		$timeout = 3600;
		$owner = null;
		$scope = 'exclusive';
		$type = 'write';
		try
		{
			$this->assertFalse(Vfs::lock($file, $token, $timeout, $owner, $scope, $type));
		}
		finally
		{
			// restore write access so this session's own tearDown() can remove it
			Vfs::chmod($file, 0700);
		}
	}
}
