<?php
/**
 * EGroupware Api: attachments which are symlinks can be listed without resolving their mime type
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Link;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once realpath(__DIR__.'/../LoggedInTest.php');

/**
 * Finding the mime type of a symlinked attachment stats the symlink's target, which is a request
 * over the network if the target is on a remote mount.  Link::list_attached() can leave the mime
 * type of such attachments out ($lazy_mime), Link::attachment_mime() answers for one of them.
 */
class LazyAttachmentMimeTest extends LoggedInTest
{
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string Vfs path of the entry's attachments
	 */
	protected $dir;

	/**
	 * @var string[] Vfs paths to remove
	 */
	protected $cleanup = array();

	protected function setUp() : void
	{
		$bo = new \infolog_bo();
		$entry = array(
			'info_subject' => 'LazyAttachmentMime #'.$this->name(),
			'info_status'  => 'open',
		);
		$this->id = $bo->write($entry, true, true, true, true);

		$this->dir = Api\Link::vfs_path('infolog', $this->id, '', true);
		Api\Vfs::mkdir($this->dir, 0750, true);
		$this->cleanup[] = $this->dir;
	}

	protected function tearDown() : void
	{
		foreach(array_reverse($this->cleanup) as $path)
		{
			Api\Vfs::remove($path);
		}
		$this->cleanup = array();
		$bo = new \infolog_bo();
		Api\Link::unlink(0, 'infolog', $this->id);
		// first delete() only flags an entry deleted and keeps the row, a second removes it
		$bo->delete($this->id);
		$bo->delete($this->id);

		parent::tearDown();
	}

	/**
	 * Attach a regular file, and a symlink to a file
	 *
	 * @return string path of the symlink's target
	 */
	protected function attach()
	{
		$target = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/lazy-mime-'.$this->id.'.png';
		file_put_contents(Api\Vfs::PREFIX.$target, 'not really a picture');
		$this->cleanup[] = $target;

		file_put_contents(Api\Vfs::PREFIX.$this->dir.'/plain.txt', 'text');
		$this->assertTrue(Api\Vfs::symlink($target, $this->dir.'/photo.png'));

		return $target;
	}

	/**
	 * @param array $links
	 * @return array name => link
	 */
	protected function byName(array $links)
	{
		$result = array();
		foreach($links as $link)
		{
			$result[$link['id']] = $link;
		}
		return $result;
	}

	public function testByDefaultEveryAttachmentHasItsMime()
	{
		$this->attach();

		$links = $this->byName(Api\Link::list_attached('infolog', $this->id));

		$this->assertEquals('text/plain', $links['plain.txt']['type']);
		$this->assertEquals('image/png', $links['photo.png']['type']);
	}

	public function testLazyLeavesOutTheMimeOfSymlinksOnly()
	{
		$this->attach();

		$links = $this->byName(Api\Link::list_attached('infolog', $this->id, '', true));

		$this->assertEquals('text/plain', $links['plain.txt']['type']);
		$this->assertNull($links['photo.png']['type']);
	}

	public function testLazyChangesNothingButTheMime()
	{
		$this->attach();

		$eager = $this->byName(Api\Link::list_attached('infolog', $this->id));
		$lazy = $this->byName(Api\Link::list_attached('infolog', $this->id, '', true));

		$this->assertSame(array_keys($eager), array_keys($lazy));
		foreach($eager as $name => $link)
		{
			unset($link['type'], $lazy[$name]['type']);
			$this->assertEquals($link, $lazy[$name], $name);
		}
	}

	public function testGetLinksPassesLazyOn()
	{
		$this->attach();

		$links = $this->byName(Api\Link::get_links('infolog', $this->id, Api\Link::VFS_APPNAME, 'link_lastmod DESC', false, false, null, true));

		$this->assertNull($links['photo.png']['type']);
		$this->assertEquals('text/plain', $links['plain.txt']['type']);
	}

	public function testAttachmentMimeAnswersForASymlink()
	{
		$this->attach();

		$this->assertEquals('image/png', Api\Link::attachment_mime('infolog', $this->id, 'photo.png'));
	}

	public function testAttachmentMimeAgreesWithTheEagerList()
	{
		$this->attach();
		$eager = $this->byName(Api\Link::list_attached('infolog', $this->id));

		foreach($eager as $name => $link)
		{
			$this->assertSame($link['type'], Api\Link::attachment_mime('infolog', $this->id, $name), $name);
		}
	}

	public function testAttachmentMimeOfASymlinkedDirectory()
	{
		$target = '/home/'.$GLOBALS['egw_info']['user']['account_lid'].'/lazy-mime-dir-'.$this->id;
		Api\Vfs::mkdir($target, 0750, true);
		$this->cleanup[] = $target;
		$this->assertTrue(Api\Vfs::symlink($target, $this->dir.'/folder'));

		$this->assertEquals(Api\Vfs::DIR_MIME_TYPE, Api\Link::attachment_mime('infolog', $this->id, 'folder'));
	}

	/**
	 * A symlink which can not be followed has no mime type, and the answer is not kept
	 */
	public function testAttachmentMimeOfABrokenSymlinkIsFalse()
	{
		$target = $this->attach();
		Api\Vfs::unlink($target);

		$this->assertFalse(Api\Link::attachment_mime('infolog', $this->id, 'photo.png'));
		// still answering for it later, when the target is back
		file_put_contents(Api\Vfs::PREFIX.$target, 'back again');
		$this->assertEquals('image/png', Api\Link::attachment_mime('infolog', $this->id, 'photo.png'));
	}

	public function testAttachmentMimeRefusesWhatIsNotAnAttachmentOfTheEntry()
	{
		foreach(array(
			array('infolog', $this->id, '../'.$this->id.'/plain.txt'),
			array('infolog', $this->id, ''),
			array('infolog', $this->id, '.'),
			array('infolog', $this->id, '/plain.txt'),
			array('infolog', $this->id, 'sub//plain.txt'),
			array('infolog', $this->id, "plain.txt\0.png"),
			// the id is checked for access, but is also a path component
			array('infolog', $this->id.'/../'.$this->id, 'plain.txt'),
			array('infolog', $this->id.'/..', 'plain.txt'),
			array('infolog/'.$this->id.'/..', $this->id, 'plain.txt'),
			array('infolog', array($this->id), 'plain.txt'),
			array('infolog', $this->id, array('plain.txt')),
			array('infolog', 0, 'plain.txt'),
			array('', $this->id, 'plain.txt'),
		) as $args)
		{
			try
			{
				Api\Link::attachment_mime(...$args);
				$this->fail('No exception for '.json_encode($args));
			}
			catch (Api\Exception\NoPermission $e)
			{
				$this->assertTrue(true);
			}
		}
	}

	/**
	 * Without a check mime_content_type() would answer from the extension alone
	 */
	public function testAttachmentMimeOfAFileWhichDoesNotExistIsFalse()
	{
		$this->attach();

		$this->assertFalse(Api\Link::attachment_mime('infolog', $this->id, 'nothing-here.png'));
	}

	/**
	 * Same answer for an entry which does not exist and one the user may not read
	 */
	public function testAttachmentMimeDoesNotTellAnEntryWhichDoesNotExist()
	{
		$this->expectException(Api\Exception\NoPermission::class);
		Api\Link::attachment_mime('infolog', 999999999, 'plain.txt');
	}
}
