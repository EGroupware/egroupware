<?php

/**
 * Test Api\Vfs's pure path-manipulation helpers: parse_url, concat, build_url,
 * basename, dirname, mode2int, int2mode, hsize, int_size
 *
 * These are plain string/bitmask logic with no DB/session dependency, so they're
 * tested directly against a bare TestCase (no LoggedInTest bootstrap needed).
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use EGroupware\Api\Vfs;

class PathHelpersTest extends TestCase
{
	public static function basenameProvider() : array
	{
		return [
			'simple file' => ['/a/b/c.txt', 'c.txt'],
			'strips query string' => ['/a/b/c.txt?query=1', 'c.txt'],
			'trailing slash yields empty' => ['/a/b/', ''],
			'no slash at all' => ['noslash', 'noslash'],
			'root' => ['/', ''],
			'empty string' => ['', ''],
		];
	}

	#[DataProvider('basenameProvider')]
	public function testBasename(string $path, string $expected) : void
	{
		$this->assertSame($expected, Vfs::basename($path));
	}

	public function testParseUrlComponents() : void
	{
		$url = 'vfs://default/home/user/file.txt?a=1#frag';
		$this->assertSame('vfs', Vfs::parse_url($url, PHP_URL_SCHEME));
		$this->assertSame('default', Vfs::parse_url($url, PHP_URL_HOST));
		$this->assertSame('/home/user/file.txt', Vfs::parse_url($url, PHP_URL_PATH));
		$this->assertSame('a=1', Vfs::parse_url($url, PHP_URL_QUERY));
		$this->assertSame('frag', Vfs::parse_url($url, PHP_URL_FRAGMENT));
	}

	public function testParseUrlWithoutComponentReturnsArray() : void
	{
		$result = Vfs::parse_url('sqlfs://default/some/path');
		$this->assertIsArray($result);
		$this->assertSame('sqlfs', $result['scheme']);
		$this->assertSame('default', $result['host']);
		$this->assertSame('/some/path', $result['path']);
	}

	public function testParseUrlMissingComponentReturnsNull() : void
	{
		$this->assertNull(Vfs::parse_url('vfs://default/home/user', PHP_URL_FRAGMENT));
	}

	/**
	 * PHP's native parse_url() returns false for "scheme://user@/path" (empty
	 * host). Vfs::parse_url() specifically works around this - used when Vfs
	 * builds a "user@" url with no meaningful host - by retrying with "@/"
	 * replaced by "@default/" (api/src/Vfs.php:1231-1234).
	 */
	public function testParseUrlUserWithoutHostFallsBackToDefault() : void
	{
		$url = 'sqlfs://someuser@/home/x';
		$this->assertSame('default', Vfs::parse_url($url, PHP_URL_HOST));
		$this->assertSame('someuser', Vfs::parse_url($url, PHP_URL_USER));
		$this->assertSame('/home/x', Vfs::parse_url($url, PHP_URL_PATH));
	}

	/**
	 * parse_url() caches its full parsed result per exact url string
	 * (api/src/Vfs.php:1210-1212), independent of which $component is asked
	 * for on a given call - a second call with a different $component but the
	 * SAME url must still return the right value, not a stale/partial one.
	 */
	public function testParseUrlCacheIndependentOfComponent() : void
	{
		$url = 'vfs://default/home/user/file.txt?a=1';
		// Prime the cache asking for just the scheme
		Vfs::parse_url($url, PHP_URL_SCHEME);
		// A later call for a different component on the same url must still work
		$this->assertSame('/home/user/file.txt', Vfs::parse_url($url, PHP_URL_PATH));
		$this->assertSame('a=1', Vfs::parse_url($url, PHP_URL_QUERY));
	}

	public function testDirname() : void
	{
		$this->assertSame('/a/b', Vfs::dirname('/a/b/c.txt'));
		$this->assertSame('/a', Vfs::dirname('/a/b'));
		$this->assertFalse(Vfs::dirname('/'));
	}

	public function testDirnamePreservesQuery() : void
	{
		$this->assertSame('/a/b?x=1', Vfs::dirname('/a/b/c.txt?x=1'));
	}

	public function testConcatBasic() : void
	{
		$this->assertSame('/a/b/c', Vfs::concat('/a/b', 'c'));
		$this->assertSame('/a/b/c', Vfs::concat('/a/b/', 'c'));
		// leading-slash relative is still appended (concat(), unlike a URL
		// resolver, never treats a leading "/" as "replace the base")
		$this->assertSame('/a/b/c', Vfs::concat('/a/b', '/c'));
		// empty relative just returns the (trailing-slash-stripped) base
		$this->assertSame('/a/b', Vfs::concat('/a/b/', ''));
	}

	public function testConcatNormalizesDotDot() : void
	{
		$this->assertSame('/a/c', Vfs::concat('/a/b/..', 'c'));
		$this->assertSame('/a/c', Vfs::concat('/a/b', '../c'));
	}

	public function testConcatPreservesQuery() : void
	{
		$this->assertSame('/a/b/c?x=1', Vfs::concat('/a/b?x=1', 'c'));
	}

	public function testBuildUrlRoundTripsParseUrl() : void
	{
		$parts = [
			'scheme' => 'vfs',
			'host'   => 'default',
			'user'   => 'someuser',
			'pass'   => 'secret',
			'path'   => '/home/user/file.txt',
			'query'  => 'a=1',
		];
		$built = Vfs::build_url($parts);
		$this->assertSame('vfs://someuser:secret@default/home/user/file.txt?a=1', $built);

		$reparsed = Vfs::parse_url($built);
		$this->assertSame('vfs', $reparsed['scheme']);
		$this->assertSame('default', $reparsed['host']);
		$this->assertSame('someuser', $reparsed['user']);
		$this->assertSame('secret', $reparsed['pass']);
		$this->assertSame('/home/user/file.txt', $reparsed['path']);
	}

	public function testBuildUrlPathOnly() : void
	{
		// scheme unset => no "scheme://host" prefix is built at all
		$this->assertSame('/just/a/path', Vfs::build_url(['path' => '/just/a/path']));
	}

	public static function mode2intProvider() : array
	{
		return [
			'already an int' => [0750, 0, 0750],
			'octal string' => ['750', 0, 0750],
			'owner rwx via symbolic' => ['u+rwx', 0, 0700],
			'group read/write via symbolic' => ['g+rw', 0, 0060],
			'all read via symbolic' => ['a+r', 0, 0444],
			// No u/g/o/a prefix at all is a no-op, NOT a "default to all" - the
			// per-char loop building $m only runs over matches[1], which is empty
			// here, so its `default: $m = ...` branch is never reached either.
			'missing ugoa prefix is a no-op' => ['+r', 0, 0],
			'combining add onto existing mode' => ['o+w', 0700, 0700 | 0002],
			// '=' assigns $mode = $m directly - it replaces the WHOLE mode, not
			// just the bits in the given scope (u/g/o/a), discarding the rest.
			'set (=) replaces the whole mode, not just its own scope' => ['u=r', 0777, 0400],
			'subtract (-) clears bits' => ['a-x', 0777, 0777 & ~(0111)],
		];
	}

	#[DataProvider('mode2intProvider')]
	public function testMode2int($set, int $mode, int $expected) : void
	{
		$this->assertSame($expected, Vfs::mode2int($set, $mode));
	}

	public function testMode2intInvalidThrows() : void
	{
		$this->expectException(\EGroupware\Api\Exception\WrongUserinput::class);
		Vfs::mode2int('not-a-valid-mode');
	}

	public static function int2modeProvider() : array
	{
		return [
			'regular file rw-r--r--' => [0100644, '-rw-r--r--'],
			'directory rwxr-xr-x' => [0040755, 'drwxr-xr-x'],
			'symlink rwxrwxrwx' => [Vfs::MODE_LINK | 0777, 'lrwxrwxrwx'],
			'setuid owner' => [0104755, '-rwsr-xr-x'],
			'setuid bit without exec shows S' => [0104655, '-rwSr-xr-x'],
			'sticky bit on world-executable dir' => [0041777, 'drwxrwxrwt'],
			'sticky bit without world-exec shows T' => [0041776, 'drwxrwxrwT'],
		];
	}

	#[DataProvider('int2modeProvider')]
	public function testInt2mode(int $mode, string $expected) : void
	{
		$this->assertSame($expected, Vfs::int2mode($mode));
	}

	public static function hsizeProvider() : array
	{
		return [
			'bytes unchanged' => [512, 2, 512],
			'kilobytes' => [2048, 2, '2.00k'],
			'megabytes' => [5 * 1024 * 1024, 2, '5.00M'],
			'gigabytes' => [3 * 1024 * 1024 * 1024, 1, '3.0G'],
		];
	}

	#[DataProvider('hsizeProvider')]
	public function testHsize(int $size, int $digits, $expected) : void
	{
		$this->assertSame($expected, Vfs::hsize($size, $digits));
	}

	public static function intSizeProvider() : array
	{
		return [
			'plain bytes' => ['512', 512],
			'kilobytes' => ['2k', 2 * 1024],
			'megabytes uppercase' => ['5M', 5 * 1024 * 1024],
			'gigabytes' => ['1G', 1024 * 1024 * 1024],
			'empty is zero' => ['', 0],
			'with whitespace' => [' 3 M ', 3 * 1024 * 1024],
		];
	}

	#[DataProvider('intSizeProvider')]
	public function testIntSize($val, int $expected) : void
	{
		$this->assertSame($expected, Vfs::int_size($val));
	}
}
