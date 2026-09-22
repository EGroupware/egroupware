<?php
/**
 * EGroupware API: containment coverage for Vfs\Sharing\StreamWrapper::share2url()
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs\Sharing;

require_once __DIR__ . '/../../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

/**
 * share2url() turns a share plus a client-supplied relative path into a vfs:// url, and is the
 * only thing standing between a "sharing://<token>/<rel_path>" url and the rest of the VFS:
 * Vfs::concat() collapses "/../" segments but never checks where the result landed, so the
 * containment check added in 44290eb572 has to.
 *
 * Both directions are covered here deliberately.  The escape cases are the reason the check
 * exists and must never regress.  The contained cases are the reason this file was written: the
 * check rejected the share's own root whenever share_path carried a trailing slash, because
 * Vfs::concat() strips one from its base and validate_path() preserves one on the way in, so
 * "vfs://u@default/home/u/share/" and its own root "vfs://u@default/home/u/share" compared
 * unequal.  Every share of a slash-terminated path answered 500 from the moment the containment
 * check landed - an uncaught NotFound - and no test caught it for five weeks because the share
 * tests that exercise this path were skipping in CI.
 *
 * share2url() is static and touches neither the session nor the share table; it only needs
 * Accounts::id2name() to resolve share_owner, so these run against a fabricated share array with
 * the logged-in user as owner, and no share is created.
 */
class ShareRootContainmentTest extends LoggedInTest
{
	/**
	 * A share array with just the keys share2url() reads
	 *
	 * @param string $share_path
	 * @param bool $writable
	 * @return array
	 */
	protected function share(string $share_path, bool $writable = false) : array
	{
		return [
			'share_owner'    => $GLOBALS['egw_info']['user']['account_id'],
			'share_path'     => $share_path,
			'share_writable' => $writable ? 1 : 0,
		];
	}

	/**
	 * The url share2url() should consider the root of a share of $share_path
	 *
	 * @param string $share_path
	 * @return string
	 */
	protected function expectedRoot(string $share_path) : string
	{
		return 'vfs://' . $GLOBALS['egw_info']['user']['account_lid'] . '@default' .
			rtrim(Api\Vfs::parse_url($share_path, PHP_URL_PATH), '/');
	}

	public static function containedPaths() : array
	{
		// [share_path, rel_path, expected suffix below the share root]
		return [
			'root itself'                      => ['/home/demo/share', '', ''],
			'root itself, trailing slash'      => ['/home/demo/share/', '', ''],
			'file'                             => ['/home/demo/share', '/file.txt', '/file.txt'],
			'file, trailing slash'             => ['/home/demo/share/', '/file.txt', '/file.txt'],
			'nested file'                      => ['/home/demo/share', '/sub/dir/file.txt', '/sub/dir/file.txt'],
			'nested file, trailing slash'      => ['/home/demo/share/', '/sub/dir/file.txt', '/sub/dir/file.txt'],
			'harmless dot-dot inside a share'  => ['/home/demo/share', '/sub/../file.txt', '/file.txt'],
			'name merely starting with a dot'  => ['/home/demo/share', '/..hidden.txt', '/..hidden.txt'],
			'name containing dots'             => ['/home/demo/share', '/a..b/c..d.txt', '/a..b/c..d.txt'],
			'whole-vfs share'                  => ['/', '/home/demo/file.txt', '/home/demo/file.txt'],
		];
	}

	/**
	 * A path that stays inside the share must resolve, with or without a trailing slash on the share
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('containedPaths')]
	public function testContainedPathResolves(string $share_path, string $rel_path, string $expected_suffix)
	{
		$url = StreamWrapper::share2url($this->share($share_path), $rel_path);

		// readonly shares get a ?ro=1 query appended, which is not part of the path
		[$path] = explode('?', $url, 2);

		$this->assertEquals($this->expectedRoot($share_path) . $expected_suffix, $path,
			"share_path '$share_path' + rel_path '$rel_path' did not resolve inside the share");
	}

	public static function escapingPaths() : array
	{
		// Each is run against a share both with and without a trailing slash, see the test
		return [
			'parent'                        => ['/..'],
			'parent with slash'             => ['/../'],
			'straight out of the vfs'       => ['/../../etc/passwd'],
			'another user home'             => ['/../../other/private.txt'],
			// The classic prefix attack: "share2" is not inside "share", even though the string
			// "…/share" is a prefix of "…/share2" - this is what the trailing slash on the
			// compared prefix defends against
			'sibling sharing our prefix'    => ['/../share2/secret.txt'],
			'sibling, no file'              => ['/../share2'],
			'deep then out'                 => ['/sub/dir/../../../escaped.txt'],
			'dot-dot at the end'            => ['/sub/../..'],
			'repeated traversal'            => ['/../../../../../../../../etc/shadow'],
			'traversal then back in'        => ['/../share2/../../etc/passwd'],
		];
	}

	/**
	 * A path that leaves the share must be rejected, whether or not the share has a trailing slash
	 *
	 * The trailing-slash fix normalises the root, so it has to be proven not to have opened any
	 * of these up on either spelling of the same share.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('escapingPaths')]
	public function testEscapingPathIsRejected(string $rel_path)
	{
		foreach(['/home/demo/share', '/home/demo/share/'] as $share_path)
		{
			try
			{
				$url = StreamWrapper::share2url($this->share($share_path), $rel_path);
				$this->fail("share_path '$share_path' + rel_path '$rel_path' was allowed to resolve to '$url'");
			}
			catch(Api\Exception\NotFound $e)
			{
				$this->assertEquals('Path escapes share root', $e->getMessage(),
					"Rejected for the wrong reason: share_path '$share_path', rel_path '$rel_path'");
			}
		}
	}

	/**
	 * Whatever an exotic path does, it must not end up outside the share root
	 *
	 * Kept separate from the list above because these are not required to throw - a null byte or
	 * a backslash may legitimately resolve to a (useless) name inside the share.  What must never
	 * happen is that they resolve outside it, so that is what is asserted.
	 */
	public function testExoticPathsNeverEscape()
	{
		$exotic = [
			"/\0/../../etc/passwd",
			'/..\\..\\etc\\passwd',
			'/....//....//etc/passwd',
			'/%2e%2e/%2e%2e/etc/passwd',
			'/sub/%2e%2e/%2e%2e/etc/passwd',
			'//../../etc/passwd',
			'/./././../../etc/passwd',
		];

		foreach(['/home/demo/share', '/home/demo/share/'] as $share_path)
		{
			$root = $this->expectedRoot($share_path);
			foreach($exotic as $rel_path)
			{
				try
				{
					$url = StreamWrapper::share2url($this->share($share_path), $rel_path);
				}
				catch(Api\Exception\NotFound $e)
				{
					// rejected outright, which is fine
					continue;
				}
				[$path] = explode('?', $url, 2);
				$this->assertTrue($path === $root || str_starts_with($path, $root . '/'),
					"rel_path '" . addcslashes($rel_path, "\0") . "' on share '$share_path' resolved to '$path', outside '$root'");
			}
		}
	}

	/**
	 * A share of a path that does not belong to the sharer is not this check's job, but the owner
	 * has to be resolvable at all - an unknown one must not fall through to a url
	 */
	public function testUnknownOwnerIsRejected()
	{
		$this->expectException(Api\Exception\NotFound::class);
		$this->expectExceptionMessage('Share owner not found');

		StreamWrapper::share2url(['share_owner' => 0, 'share_path' => '/home/demo/share'], '/file.txt');
	}
}
