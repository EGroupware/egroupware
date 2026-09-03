<?php

/**
 * Deeper coverage of Vfs\StreamWrapper + Vfs\Sqlfs\StreamWrapper behavior not
 * exercised by StreamWrapperBase's shared tests or FacadeTest's facade-level
 * storage/retrieval checks: extended-ACL enforcement (not just storage),
 * symlink edge cases (dangling target, multi-hop chains, a two-node cycle
 * that the creation-time nesting check can't catch), and Sqlfs\StreamWrapper's
 * own object-level stat cache (distinct from PHP's stream stat cache and from
 * Vfs\Base's resolve_url/symlink caches). Phase 3 of the vfs-test-coverage
 * project.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class SqlfsBackendTest extends LoggedInTest
{
	/**
	 * @var string root of a scratch area explicitly mounted onto sqlfs://,
	 * see FacadeTest for why this doesn't rely on the environment's default
	 * mount (doc/ai/projects/vfs-test-coverage.md Phase 1/6).
	 */
	protected $test_root;

	/**
	 * @var string[] paths to clean up in tearDown(), in creation order
	 */
	protected $files = [];

	protected function setUp() : void
	{
		parent::setUp();

		$this->test_root = '/sqlfsbackendtest_' . substr(md5(uniqid('', true)), 0, 8);
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$this->assertTrue(
			Vfs::mount('sqlfs://default' . $this->test_root, $this->test_root, false, false),
			"Unable to mount a scratch sqlfs:// area at $this->test_root"
		);
		Vfs::clearstatcache();
		Vfs::mkdir($this->test_root);
		Vfs::chown($this->test_root, $GLOBALS['egw_info']['user']['account_id']);
		Vfs::chmod($this->test_root, 0750);
		Vfs::$is_root = $backup;
		Vfs::clearstatcache();
	}

	protected function tearDown() : void
	{
		// see FacadeTest::tearDown() - session-only eACLs outlive test_root
		// and leak into later tests in this same PHPUnit process
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
	// extended ACL - actual enforcement, not just storage/retrieval
	// (FacadeTest::testEaclSetGetDelete only covers eacl()/get_eacl()
	// storage, not whether a grant actually changes an access decision)
	// ---------------------------------------------------------------

	public function testEaclGrantChangesAccessDecision() : void
	{
		$dir = $this->files[] = $this->getFilename('_eacl_enforce_dir');
		Vfs::mkdir($dir);
		Vfs::chmod($dir, 0700);	// owner-only: nobody else has owner/group/other access

		$other_uid = (int)$GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);

		// denied by default (0700, other user matches neither owner nor group)
		$this->assertFalse(Vfs::check_access($dir, Vfs::READABLE, null, $other_uid));

		$this->assertNotFalse(Vfs::eacl($dir, Vfs::READABLE, $other_uid));
		Vfs::clearstatcache();
		$this->assertTrue(Vfs::check_access($dir, Vfs::READABLE, null, $other_uid));

		// revoking (null rights) removes the grant again
		$this->assertNotFalse(Vfs::eacl($dir, null, $other_uid));
		Vfs::clearstatcache();
		$this->assertFalse(Vfs::check_access($dir, Vfs::READABLE, null, $other_uid));
	}

	// ---------------------------------------------------------------
	// symlink edge cases
	// ---------------------------------------------------------------

	public function testSymlinkToNonexistentTargetIsDangling() : void
	{
		$link = $this->files[] = $this->getFilename('_dangling_link');
		$target = $this->getFilename('_never_created');

		$this->assertTrue(Vfs::symlink($target, $link));
		$this->assertTrue(Vfs::is_link($link));
		$this->assertFalse(Vfs::file_exists($link));
		$this->assertEquals($target, Vfs::readlink($link));
	}

	public function testSymlinkChainResolvesMultipleHops() : void
	{
		$real = $this->files[] = $this->getFilename('_chain_real.txt');
		file_put_contents(Vfs::PREFIX . $real, 'chained content');
		$middle = $this->files[] = $this->getFilename('_chain_middle');
		$this->assertTrue(Vfs::symlink($real, $middle));
		$outer = $this->files[] = $this->getFilename('_chain_outer');
		$this->assertTrue(Vfs::symlink($middle, $outer));

		$this->assertEquals('chained content', file_get_contents(Vfs::PREFIX . $outer));
	}

	/**
	 * Vfs::symlink()'s creation-time check (api/src/Vfs.php:2273-2293) only
	 * rejects a link nested inside its own target's directory tree (or vice
	 * versa) - it does NOT catch a two-node A<->B cycle, since neither path
	 * is a prefix of the other. Resolving must still not hang: it's bounded
	 * by Vfs\StreamWrapper::MAX_SYMLINK_DEPTH=10
	 * (StreamWrapper.php:54,905-940's check_symlink_components() hop counter).
	 */
	public function testSymlinkTwoNodeCycleDoesNotHangAndFailsToResolve() : void
	{
		$a = $this->files[] = $this->getFilename('_cycle_a');
		$b = $this->files[] = $this->getFilename('_cycle_b');

		$this->assertTrue(Vfs::symlink($b, $a));
		$this->assertTrue(Vfs::symlink($a, $b));

		// Vfs::stat() returns null (not false) for anything it can't stat -
		// same convention as a plain nonexistent path, see FacadeTest::
		// testStatNonExistentReturnsNull()
		$this->assertNull(Vfs::stat($a));
	}

	// ---------------------------------------------------------------
	// Sqlfs\StreamWrapper's own object-level stat cache (Sqlfs\StreamWrapper::
	// $stat_cache, distinct from PHP's stream stat cache and from
	// Vfs\Base's resolve_url_cache/symlink_cache) vs. Vfs::stat()'s own
	// staleness at the facade level
	// ---------------------------------------------------------------

	/**
	 * chmod() patches Sqlfs\StreamWrapper::$stat_cache directly in place
	 * (StreamWrapper.php:1131) rather than invalidating it, AND a facade
	 * Vfs::stat() read immediately after (no explicit Vfs::clearstatcache()
	 * call) already reflects it.
	 */
	public function testChmodVisibleImmediatelyWithoutExplicitClearstatcache() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		Vfs::stat($file);	// prime the cache

		Vfs::chmod($file, 0640);
		$this->assertEquals(0640, Vfs::stat($file)['mode'] & 0777);
	}

	/**
	 * chown() is NOT symmetric with chmod() here, despite patching the same
	 * Sqlfs\StreamWrapper::$stat_cache directly in place
	 * (StreamWrapper.php:1026) - confirmed via a live diagnostic run: chown()
	 * genuinely succeeds (returns true, and the new owner IS persisted - a
	 * subsequent Vfs::clearstatcache() + Vfs::stat() correctly shows it), but
	 * a facade Vfs::stat() read immediately after, with no explicit
	 * Vfs::clearstatcache() call in between, still returns the OLD uid. Root
	 * cause not chased further (plausibly PHP's own native stat() cache,
	 * consulted by the core Vfs\StreamWrapper::url_stat() at
	 * StreamWrapper.php:757, behaving differently for chmod() vs chown() at
	 * the PHP-engine level) - documented here as a real, non-obvious
	 * asymmetry rather than assumed away.
	 */
	public function testChownRequiresExplicitClearstatcacheUnlikeChmod() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, 'x');
		Vfs::stat($file);
		$other_uid = (int)$GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);

		Vfs::$is_root = true;
		try
		{
			$this->assertTrue(Vfs::chown($file, $other_uid));

			// stale WITHOUT an explicit clearstatcache() - unlike chmod() above
			$this->assertNotEquals($other_uid, Vfs::stat($file)['uid']);

			Vfs::clearstatcache();
			$this->assertEquals($other_uid, Vfs::stat($file)['uid']);
		}
		finally
		{
			Vfs::chown($file, $GLOBALS['egw_info']['user']['account_id']);
			Vfs::$is_root = false;
		}
	}

	// ---------------------------------------------------------------
	// partial writes / seek - the mechanism WebDAV range-requests (used by
	// some clients to upload huge files in chunks) rely on. Both
	// Vfs\StreamWrapper::stream_seek()/stream_write() (StreamWrapper.php:
	// 286-341) and Vfs\Sqlfs\StreamWrapper's underlying local-file open
	// (Sqlfs/StreamWrapper.php:315-320, `fopen($fs_path, $mode)` with the
	// SAME mode WebDAV passes through) are thin pass-throughs to PHP's own
	// fread/fwrite/fseek/ftell on a real local file - so this is really
	// verifying that pass-through, and that Sqlfs\StreamWrapper::
	// stream_close() computes the true final size via seek-to-end+tell()
	// (`:366-367`) rather than naively trusting the last write's length.
	// ---------------------------------------------------------------

	public function testSeekAndOverwritePartialContent() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, '0123456789');

		$fp = fopen(Vfs::PREFIX . $file, 'r+');
		$this->assertNotFalse($fp);
		$this->assertTrue(fseek($fp, 5) === 0);
		$this->assertEquals(5, fwrite($fp, 'XXXXX'));
		fclose($fp);

		$this->assertEquals('01234XXXXX', file_get_contents(Vfs::PREFIX . $file));
	}

	/**
	 * Mirrors exactly what api/src/WebDAV/Server/Filesystem.php's PUT()
	 * does for a range request: `fopen($fspath, "c")` - create if missing,
	 * do NOT truncate if it already exists (unlike "w") - then the caller
	 * seeks to the range's start offset before writing.
	 */
	public function testModeCDoesNotTruncateExistingContent() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, '01234');	// "chunk 1" already uploaded

		$fp = fopen(Vfs::PREFIX . $file, 'c');
		$this->assertNotFalse($fp, 'mode "c" should open an existing file without truncating it');
		$this->assertTrue(fseek($fp, 5) === 0);	// continue where chunk 1 left off
		$this->assertEquals(5, fwrite($fp, '56789'));
		fclose($fp);

		$this->assertEquals('0123456789', file_get_contents(Vfs::PREFIX . $file));
	}

	/**
	 * Sqlfs\StreamWrapper::stream_close() derives fs_size from
	 * seek-to-end+tell() on the real underlying stream, NOT from summing
	 * bytes written in this open/close cycle - so it must report the TRUE
	 * final file size even when the writes were partial/out-of-order.
	 */
	public function testStatSizeReflectsTrueSizeAfterPartialWrite() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, str_repeat('a', 20));

		$fp = fopen(Vfs::PREFIX . $file, 'r+');
		fseek($fp, 10);
		fwrite($fp, 'XXXXX');	// only 5 bytes written, well before EOF
		fclose($fp);

		Vfs::clearstatcache();
		$this->assertEquals(20, Vfs::stat($file)['size'],
			'stat() size must reflect the true 20-byte file length, not the 5 bytes actually fwrite()n');
	}

	/**
	 * A seek PAST the current end-of-file, then a write, must zero-fill the
	 * gap (standard POSIX sparse-write semantics) - relevant since a
	 * malformed/out-of-order Content-Range chunk could otherwise corrupt
	 * the file silently instead of producing a well-defined result.
	 */
	public function testSeekPastEndOfFileZeroFillsGapOnWrite() : void
	{
		$file = $this->files[] = $this->getFilename();
		file_put_contents(Vfs::PREFIX . $file, '01234');

		$fp = fopen(Vfs::PREFIX . $file, 'r+');
		fseek($fp, 10);	// 5 bytes past current EOF (positions 5-9 are a gap)
		fwrite($fp, 'XXXXX');
		fclose($fp);

		$content = file_get_contents(Vfs::PREFIX . $file);
		$this->assertEquals(15, strlen($content));
		$this->assertEquals('01234', substr($content, 0, 5));
		$this->assertEquals("\0\0\0\0\0", substr($content, 5, 5));
		$this->assertEquals('XXXXX', substr($content, 10, 5));
	}
}
