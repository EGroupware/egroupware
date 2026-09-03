<?php

/**
 * Test the Vfs mount table: Vfs\Base::mount()/umount()/resolve_url()/mount_url()
 * and scheme2class()/load_wrapper() - all inherited statics on the Vfs facade
 * (class Vfs extends Vfs\Base), Phase 2 of the vfs-test-coverage project.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;

class MountTableTest extends LoggedInTest
{
	/**
	 * @var string[] mount-point paths to umount() in tearDown(), in mount order
	 */
	protected $mounts = [];

	protected function tearDown() : void
	{
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		foreach(array_reverse($this->mounts) as $mount)
		{
			Vfs::umount($mount);
		}
		Vfs::$is_root = $backup;
		Vfs::clearstatcache();
		parent::tearDown();
	}

	protected function scratchPath(string $suffix = '') : string
	{
		$reflect = new \ReflectionClass($this);
		return '/mounttest_' . $reflect->getShortName() . '_' . $this->name() . $suffix;
	}

	// ---------------------------------------------------------------
	// mount() / umount()
	// ---------------------------------------------------------------

	public function testMountRequiresRoot() : void
	{
		$path = $this->scratchPath();
		$this->assertFalse(Vfs::$is_root);
		$this->assertFalse(Vfs::mount('sqlfs://default' . $path, $path, false, false));
		$fstab = Vfs::mount();
		$this->assertArrayNotHasKey($path, $fstab);
	}

	public function testMountAddsToFstab() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		Vfs::$is_root = true;
		try
		{
			$this->assertTrue(Vfs::mount('sqlfs://default' . $path, $path, false, false));
			$fstab = Vfs::mount();
			$this->assertEquals('sqlfs://default' . $path, $fstab[$path]);
		}
		finally
		{
			Vfs::$is_root = false;
		}
	}

	public function testMountAlreadyMountedIsNoopReturningTrue() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		$url = 'sqlfs://default' . $path;
		Vfs::$is_root = true;
		try
		{
			$this->assertTrue(Vfs::mount($url, $path, false, false));
			// mounting the exact same url+path again is a no-op, still returns true
			$this->assertTrue(Vfs::mount($url, $path, false, false));
		}
		finally
		{
			Vfs::$is_root = false;
		}
	}

	public function testMountDifferentUrlOverwritesExistingEntry() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		Vfs::$is_root = true;
		try
		{
			Vfs::mount('sqlfs://default' . $path, $path, false, false);
			Vfs::mount('links://$host/apps', $path, false, false);
			$fstab = Vfs::mount();
			$this->assertEquals('links://$host/apps', $fstab[$path]);
		}
		finally
		{
			Vfs::$is_root = false;
		}
	}

	public function testUmountRequiresRoot() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		Vfs::$is_root = true;
		Vfs::mount('sqlfs://default' . $path, $path, false, false);
		Vfs::$is_root = false;

		$this->assertFalse(Vfs::umount($path));
	}

	public function testUmountAcceptsMountedUrlAsWellAsPath() : void
	{
		$path = $this->scratchPath();
		$url = 'sqlfs://default' . $path;
		Vfs::$is_root = true;
		try
		{
			Vfs::mount($url, $path, false, false);
			// umount() accepts either the mount-point path OR the mounted url
			// (Base::umount() falls back to array_search() over the fstab values)
			$this->assertTrue(Vfs::umount($url));
			$fstab = Vfs::mount();
			$this->assertArrayNotHasKey($path, $fstab);
		}
		finally
		{
			Vfs::$is_root = false;
		}
	}

	public function testUmountNotMountedReturnsFalse() : void
	{
		Vfs::$is_root = true;
		try
		{
			$this->assertFalse(Vfs::umount($this->scratchPath('_never_mounted')));
		}
		finally
		{
			Vfs::$is_root = false;
		}
	}

	// ---------------------------------------------------------------
	// resolve_url()
	// ---------------------------------------------------------------

	public function testResolveUrlLongestMountWins() : void
	{
		$parent = $this->mounts[] = $this->scratchPath();
		$child = $this->mounts[] = $parent . '/child';

		Vfs::$is_root = true;
		try
		{
			Vfs::mount('sqlfs://default' . $parent, $parent, false, false);
			Vfs::mount('links://$host/apps', $child, false, false);
		}
		finally
		{
			Vfs::$is_root = false;
		}
		Vfs::clearstatcache();

		$resolved_child = Vfs::resolve_url($child . '/something');
		$this->assertStringStartsWith('links://', $resolved_child);

		$resolved_sibling = Vfs::resolve_url($parent . '/sibling');
		$this->assertStringStartsWith('sqlfs://', $resolved_sibling);
	}

	public function testResolveUrlNonVfsSchemePassesThrough() : void
	{
		// an already-resolved backend url (any scheme other than 'vfs') is
		// returned unchanged - Base::resolve_url() only rewrites plain paths
		$this->assertEquals(
			'sqlfs://default/already/resolved',
			Vfs::resolve_url('sqlfs://default/already/resolved')
		);
	}

	public function testResolveUrlSubstitutesHostPlaceholder() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		Vfs::$is_root = true;
		try
		{
			Vfs::mount('sqlfs://$host' . $path, $path, false, false);
		}
		finally
		{
			Vfs::$is_root = false;
		}
		Vfs::clearstatcache();

		$resolved = Vfs::resolve_url($path . '/file.txt');
		$this->assertStringNotContainsString('$host', $resolved);
	}

	public function testResolveUrlCachedUntilClearstatcache() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		Vfs::$is_root = true;
		try
		{
			Vfs::mount('sqlfs://default' . $path, $path, false, false);
		}
		finally
		{
			Vfs::$is_root = false;
		}
		Vfs::clearstatcache();

		$first = Vfs::resolve_url($path . '/f');

		// remount the SAME path onto a different backend
		Vfs::$is_root = true;
		Vfs::mount('links://$host/apps', $path, false, false);
		Vfs::$is_root = false;

		// without clearing the cache, the stale resolution is still returned
		$still_cached = Vfs::resolve_url($path . '/f');
		$this->assertEquals($first, $still_cached);

		Vfs::clearstatcache();
		$after_clear = Vfs::resolve_url($path . '/f');
		$this->assertNotEquals($first, $after_clear);
	}

	// ---------------------------------------------------------------
	// mount_url()
	// ---------------------------------------------------------------

	public function testMountUrlReturnsOwningMountEntry() : void
	{
		$path = $this->mounts[] = $this->scratchPath();
		Vfs::$is_root = true;
		try
		{
			Vfs::mount('sqlfs://default' . $path, $path, false, false);
		}
		finally
		{
			Vfs::$is_root = false;
		}
		Vfs::clearstatcache();

		$resolved = Vfs::resolve_url($path . '/file.txt');
		$this->assertEquals('sqlfs://default' . $path, Vfs::mount_url($resolved));
	}

	// ---------------------------------------------------------------
	// scheme2class() / load_wrapper()
	// ---------------------------------------------------------------

	public function testScheme2classCoreScheme() : void
	{
		// __CLASS__ inside Base::scheme2class() is compile-time-bound to
		// Vfs\Base (where it's written), not late-static-bound to Vfs - so
		// this returns Base::class even though Vfs::scheme2class() is how
		// it's actually called.
		$this->assertEquals(Base::class, Vfs::scheme2class('vfs'));
	}

	public function testScheme2classSimpleApiScheme() : void
	{
		$this->assertEquals(Sqlfs\StreamWrapper::class, Vfs::scheme2class('sqlfs'));
	}

	public function testScheme2classDottedAppScheme() : void
	{
		if(!class_exists(\EGroupware\Stylite\Vfs\Versioning\StreamWrapper::class))
		{
			$this->markTestSkipped('No EPL Versioning wrapper available');
		}
		$this->assertEquals(
			\EGroupware\Stylite\Vfs\Versioning\StreamWrapper::class,
			Vfs::scheme2class('stylite.versioning')
		);
	}

	public function testScheme2classUnknownReturnsNull() : void
	{
		$this->assertNull(Vfs::scheme2class('totally_unknown_scheme_xyz'));
	}

	public function testLoadWrapperKnownSchemeReturnsTrue() : void
	{
		$this->assertTrue(Vfs::load_wrapper('sqlfs'));
	}

	public function testLoadWrapperUnknownSchemeReturnsFalse() : void
	{
		$this->assertFalse(@Vfs::load_wrapper('totally_unknown_scheme_xyz'));
	}
}
