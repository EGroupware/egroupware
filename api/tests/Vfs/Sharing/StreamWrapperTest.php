<?php

/**
 * Test the basics of Sharing::StreamWrapper
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs\Sharing;

require_once __DIR__ . '/../StreamWrapperBase.php';

use EGroupware\Api;
use EGroupware\Api\Vfs;
use EGroupware\Api\Vfs\Sharing;


class StreamWrapperTest extends Vfs\StreamWrapperBase
{
	protected $share = [];

	static $test_dir = 'TestShareFolder';

	protected function setUp() : void
	{
		$this->files[] = $this->getFilename('',false);
		$this->createShare();
		parent::setUp();
	}

	protected function tearDown() : void
	{
		parent::tearDown();
	}

	public function testSimpleReadWrite(): string
	{
		$this->files[] = $this->test_file = $this->getFilename('',false);

		return parent::testSimpleReadWrite();
	}

	public function testNoReadAccess(): void
	{
		$this->files[] = $this->test_file = $this->getFilename('',false);

		parent::testNoReadAccess();
	}

	public function testWithAccess(): void
	{
		$this->files[] = $this->test_file = $this->getFilename('',false);

		parent::testWithAccess();
	}

	protected function allowAccess(string $test_name, string &$test_file, int $test_user, string $needed) : void
	{
		// Anyone who mounts will have access, but the available path changes
		$test_file = '/home/'.  $GLOBALS['egw']->accounts->id2name($test_user) . '/' .
				Vfs\Sharing::SHARES_DIRECTORY .'/'.static::$test_dir .'/'. Vfs::basename($test_file);
	}

	public function mount() : void
	{
		$this->files[] = Vfs::get_home_dir() . '/'. static::$test_dir;
		Api\Vfs\Sharing::setup_share(true,$this->share);
		Vfs::clearstatcache();
	}

	public function createShare(&$dir='', $extra = array(), $create = 'createShare')
	{
		// First, create the directory to be shared
		$this->files[] = $dir = Vfs::get_home_dir() . '/'. static::$test_dir;
		Vfs::mkdir($dir);

		// Create and use link
		$this->getShareExtra($dir, Sharing::WRITABLE, $extra);

		$this->share = Vfs\Sharing::create('',$dir,Sharing::WRITABLE,$dir,'',$extra);
		$link = Vfs\Sharing::share2link($this->share);

		return $link;
	}


	/**
	 * Get the extra information required to create a share link for the given
	 * directory, with the given mode
	 *
	 * @param string $dir Share target
	 * @param int $mode Share mode
	 * @param Array $extra
	 */
	protected function getShareExtra($dir, $mode, &$extra)
	{
		switch($mode)
		{
			case Sharing::WRITABLE:
				$extra['share_writable'] = TRUE;
				break;
		}
	}

	/**
	 * Make a filename that reflects the current test
	 * @param $path
	 * @param bool $mounted Get the path if the share is mounted, or the original
	 * @return string
	 */
	protected function getFilename($path = null, $mounted = true) : string
	{
		return parent::getFilename(Vfs::get_home_dir() . '/'.
				($mounted ? Vfs\Sharing::SHARES_DIRECTORY .'/' : '').static::$test_dir .'/'. $path);
	}

}