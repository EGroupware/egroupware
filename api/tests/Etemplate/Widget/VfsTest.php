<?php

/**
 * Test for the vfs widget's path and attach-file helpers
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

// the widget classes cannot even be loaded without an environment: Widget.php reads the app list
require_once realpath(__DIR__.'/../../LoggedInTest.php');

/**
 * Two things the markdown editor's attach button depends on.
 *
 * can_attach_file() decides whether it is offered at all.  It attaches through
 * ajax_htmlarea_upload(), which reads its target from the named widget's server-side content -
 * normally "link_to", ie. {to_app, to_id} - and an entry with no id yet would put the file in a
 * temp directory and leave the link in the text dangling once the save filed it away.  Only the
 * server can see the difference, which is why this lives here.
 *
 * get_vfs_path() is where that upload ends up.  It has to name a *directory*: store_file() reads
 * a path without a trailing slash as the target file name.
 *
 * Both are pure string handling - nothing is uploaded and no template is executed - so a failure
 * here is a logic bug in the helper, not an environment problem.
 */
class VfsTest extends \EGroupware\Api\LoggedInTest
{
	/**
	 * A saved entry: {to_app, to_id} with a real id, as every app with a Links tab supplies
	 */
	public function testAllowsAttachingToASavedEntry()
	{
		$this->assertTrue(Vfs::can_attach_file('link_to',
			['link_to' => ['to_app' => 'tracker', 'to_id' => 42]]));
		// a string id is just as saved - some apps have non-numeric ids
		$this->assertTrue(Vfs::can_attach_file('link_to',
			['link_to' => ['to_app' => 'addressbook', 'to_id' => '4711']]));
	}

	/**
	 * An unsaved entry has no id at all, or an array of links accumulated until it is saved
	 */
	public function testRefusesAnUnsavedEntry()
	{
		$this->assertFalse(Vfs::can_attach_file('link_to',
			['link_to' => ['to_app' => 'tracker', 'to_id' => null]]));
		$this->assertFalse(Vfs::can_attach_file('link_to',
			['link_to' => ['to_app' => 'tracker', 'to_id' => '']]));
		$this->assertFalse(Vfs::can_attach_file('link_to',
			['link_to' => ['to_app' => 'tracker', 'to_id' => ['file' => ['name' => 'pending.png']]]]));
	}

	public function testRefusesWhenThereIsNothingToAttachTo()
	{
		$this->assertFalse(Vfs::can_attach_file('link_to', []));
		$this->assertFalse(Vfs::can_attach_file('link_to', ['link_to' => null]));
		$this->assertFalse(Vfs::can_attach_file('link_to', ['link_to' => '']));
		// no to_app is as useless as no to_id
		$this->assertFalse(Vfs::can_attach_file('link_to', ['link_to' => ['to_id' => 42]]));
	}

	/**
	 * ajax_htmlarea_upload()'s other branch: content holding a literal directory, which names a
	 * fixed place with nothing to wait for
	 */
	public function testAllowsALiteralPath()
	{
		$this->assertTrue(Vfs::can_attach_file('somewhere', ['somewhere' => '/home/demo/shared']));
		// ... but a relative string is not a path, and means nothing to the endpoint
		$this->assertFalse(Vfs::can_attach_file('somewhere', ['somewhere' => 'home/demo']));
	}

	/**
	 * The widget names which content to read; unnamed falls back to link_to, as et2-link-to uses
	 */
	public function testDefaultsToTheLinkToContent()
	{
		$content = ['link_to' => ['to_app' => 'tracker', 'to_id' => 42]];

		$this->assertTrue(Vfs::can_attach_file(null, $content));
		$this->assertTrue(Vfs::can_attach_file('', $content));
		$this->assertFalse(Vfs::can_attach_file('attachments', $content), 'a key that is not there');
	}

	/**
	 * "app:id:" is the entry's DIRECTORY, not a file named after the entry.
	 *
	 * store_file() decides which by the trailing slash: without one it treats the path as the
	 * target file name, so an upload to "tracker:42:" landed as /apps/tracker/42.png - beside the
	 * entry directory rather than in it, and not an attachment at all.  Found live.
	 */
	public function testEntryPathNamesTheDirectory()
	{
		$this->assertEquals('/apps/tracker/42/', Vfs::get_vfs_path('tracker:42:'));
		$this->assertEquals('/apps/tracker/42/', Vfs::get_vfs_path('tracker:42'));
	}

	public function testKeepsAGivenRelativePath()
	{
		$this->assertEquals('/apps/tracker/42/comments/.new/',
			Vfs::get_vfs_path('tracker:42:comments/.new/'));
		$this->assertEquals('/apps/tracker/42/report.pdf',
			Vfs::get_vfs_path('tracker:42:report.pdf'));
	}

	/**
	 * An entry that does not exist yet parks its uploads in a temp directory - which has to be a
	 * directory too, or the first upload becomes <tempdir>.png
	 */
	public function testNewEntryPathNamesADirectory()
	{
		$this->assertStringEndsWith('/', Vfs::get_vfs_path('tracker::'));
	}

	/**
	 * ajax_htmlarea_upload() appends its own trailing slash (its other branch is a literal path
	 * from content, which has none), so the result must not grow a second one
	 */
	public function testEntryPathDoesNotDoubleItsTrailingSlash()
	{
		$this->assertEquals('/apps/tracker/42/', rtrim(Vfs::get_vfs_path('tracker:42'), '/').'/');
		$this->assertStringNotContainsString('//', Vfs::get_vfs_path('tracker:42:'));
	}
}
