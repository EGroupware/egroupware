<?php
/**
 * EGroupware filemanager: tests for mime-type handling in get_rows()/get_vfs_options()
 * and the collabora-editor-link hook
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Vfs;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * filemanager_ui::get_rows() relies on Vfs::find()'s 'need_mime' option to attach a
 * per-row 'mime' field (server-side, extension-derived via sqlfs' stored fs_mime column -
 * see Vfs::mime_content_type()), and forwards $query['col_filter']['mime'] straight into
 * Vfs::find()'s 'mime' option, which supports three distinct matching modes (Vfs::_check_add()):
 * exact match, a leading-'/'-delimited perl regex, or a bare main-type ('image', no subtype)
 * prefix match. This test builds a small fixture directory with one file per mime type and
 * exercises get_rows() against it directly, plus the collabora-editor-link hook's
 * no-app-enabled fallback (the only branch of that hook testable without a real Collabora
 * server - see filemanager_hooks::getEditorLink()).
 *
 * Fixture files only need the right extension, not real matching content: sqlfs computes
 * mime purely from the filename at creation time (Api\MimeMagic::filename2mime()) and never
 * re-sniffs file content for vfs:// paths.
 */
class FilemanagerMimeTypeTest extends \EGroupware\Api\AppTest
{
	/** @var string test-scoped fixture directory under the current user's home */
	private $dir;

	/** @var array extension => expected mime type for the fixture files created below */
	private const FIXTURES = array(
		'txt'  => 'text/plain',
		'pdf'  => 'application/pdf',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'jpg'  => 'image/jpeg',
		'png'  => 'image/png',
	);

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = filemanager_ui::get_home_dir().'/phpunit_mime_'.bin2hex(random_bytes(6));
		Vfs::mkdir($this->dir, 0750, true);
		foreach (self::FIXTURES as $ext => $mime)
		{
			file_put_contents(Vfs::PREFIX.$this->dir.'/file.'.$ext, 'phpunit fixture');
		}
		Vfs::mkdir($this->dir.'/subdir', 0750, true);
	}

	protected function tearDown(): void
	{
		if ($this->dir)
		{
			Vfs::remove($this->dir);
			$this->dir = null;
		}
	}

	/**
	 * Minimal, valid $query array for get_rows(), scoped to the fixture directory.
	 */
	private function baseQuery(array $overrides = array()): array
	{
		return array_merge(array(
			'col_filter' => array('dir' => $this->dir),
			'filter'     => 1,
			'order'      => 'name',
			'sort'       => 'ASC',
			'num_rows'   => 100,
			'start'      => 0,
		), $overrides);
	}

	/**
	 * Index the fixture rows returned by get_rows() by filename, for easy per-extension lookup.
	 */
	private function rowsByName(array $rows): array
	{
		$by_name = array();
		foreach ($rows as $row)
		{
			if (is_array($row) && isset($row['name']))
			{
				$by_name[$row['name']] = $row;
			}
		}
		return $by_name;
	}

	/**
	 * Pass criteria: each fixture file's row carries the mime type its extension maps to,
	 * and the subdirectory's row carries Vfs::DIR_MIME_TYPE - proving get_rows() actually
	 * surfaces per-row mime rather than leaving it unset (need_mime=true takes effect).
	 */
	public function testRowsCarryMimeTypePerExtension()
	{
		$ui = new filemanager_ui();
		$query = $this->baseQuery();
		$rows = array();
		$ui->get_rows($query, $rows);
		$by_name = $this->rowsByName($rows);

		foreach (self::FIXTURES as $ext => $expected_mime)
		{
			$this->assertArrayHasKey('file.'.$ext, $by_name, "fixture file.$ext must come back from get_rows()");
			$this->assertSame($expected_mime, $by_name['file.'.$ext]['mime'] ?? null,
				"file.$ext must report mime type $expected_mime");
		}

		$this->assertArrayHasKey('subdir', $by_name, 'fixture subdir must come back from get_rows()');
		$this->assertSame(Vfs::DIR_MIME_TYPE, $by_name['subdir']['mime'] ?? null,
			'a directory row must report the directory mime type, not a file mime type');
	}

	/**
	 * Pass criteria: an exact col_filter['mime'] match returns only the one file with that
	 * exact mime type - not files that merely share the main type (eg. other image/* files).
	 */
	public function testColFilterMimeExactMatch()
	{
		$ui = new filemanager_ui();
		$query = $this->baseQuery(array('col_filter' => array('dir' => $this->dir, 'mime' => 'image/jpeg')));
		$rows = array();
		$ui->get_rows($query, $rows);
		$by_name = $this->rowsByName($rows);

		$this->assertArrayHasKey('file.jpg', $by_name, 'exact-mime filter must include the matching file');
		$this->assertArrayNotHasKey('file.png', $by_name, 'exact-mime filter must exclude a different exact mime type');
		$this->assertArrayNotHasKey('file.txt', $by_name, 'exact-mime filter must exclude an unrelated mime type');
	}

	/**
	 * Pass criteria: a bare main-type filter (no subtype, eg. 'image') matches every fixture
	 * file whose mime starts with 'image/', and excludes every non-image fixture file -
	 * proving Vfs::find()'s "no subtype --> check only the main type" branch is reachable
	 * through filemanager's col_filter['mime'] passthrough.
	 */
	public function testColFilterMimeMainTypePrefix()
	{
		$ui = new filemanager_ui();
		$query = $this->baseQuery(array('col_filter' => array('dir' => $this->dir, 'mime' => 'image')));
		$rows = array();
		$ui->get_rows($query, $rows);
		$by_name = $this->rowsByName($rows);

		$this->assertArrayHasKey('file.jpg', $by_name, "main-type filter 'image' must include file.jpg");
		$this->assertArrayHasKey('file.png', $by_name, "main-type filter 'image' must include file.png");
		foreach (array('file.txt', 'file.pdf', 'file.docx') as $non_image)
		{
			$this->assertArrayNotHasKey($non_image, $by_name, "main-type filter 'image' must exclude $non_image");
		}
	}

	/**
	 * Pass criteria: a regular-expression col_filter['mime'] (leading '/', per Vfs::_check_add())
	 * matches across otherwise-unrelated main types, proving the regex branch (distinct from
	 * the exact-match and bare-main-type branches above) is reachable the same way.
	 */
	public function testColFilterMimeRegex()
	{
		$ui = new filemanager_ui();
		$query = $this->baseQuery(array('col_filter' => array(
			'dir'  => $this->dir,
			'mime' => '/^(application\/pdf|image\/png)$/',
		)));
		$rows = array();
		$ui->get_rows($query, $rows);
		$by_name = $this->rowsByName($rows);

		$this->assertArrayHasKey('file.pdf', $by_name, 'regex filter must include file.pdf');
		$this->assertArrayHasKey('file.png', $by_name, 'regex filter must include file.png');
		foreach (array('file.txt', 'file.docx', 'file.jpg') as $excluded)
		{
			$this->assertArrayNotHasKey($excluded, $by_name, "regex filter must exclude $excluded");
		}
	}

	/**
	 * Regression guard for the "Don't store mime filter from expose" behaviour: get_rows()
	 * must not persist col_filter['mime'] into the session query, so a mime-filtered Expose
	 * view never silently "sticks" on a plain reload of the index.
	 *
	 * Pass criteria: after a get_rows() call with a mime filter, the cached session query's
	 * col_filter has no 'mime' key.
	 */
	public function testMimeFilterNotPersistedToSession()
	{
		$ui = new filemanager_ui();
		$query = $this->baseQuery(array('col_filter' => array('dir' => $this->dir, 'mime' => 'image/jpeg')));
		$rows = array();
		$ui->get_rows($query, $rows);

		$stored = \EGroupware\Api\Cache::getSession('filemanager', 'index');
		$this->assertArrayNotHasKey('mime', $stored['col_filter'] ?? array(),
			'col_filter[mime] must not be persisted to the session index query');
	}

	/**
	 * filemanager_hooks::getEditorLink() only returns a link for an app whose hook is both
	 * present in Api\Hooks::process('filemanager-editor-link', 'collabora') AND enabled for
	 * the current user (checked via $GLOBALS['egw_info']['user']['apps'][$app]). With the
	 * collabora app absent from the user's enabled apps, no candidate app can pass that
	 * check - this exercises the "Collabora not available" fallback without needing a real,
	 * network-reachable Collabora server (which the collabora app's own tests skip when
	 * unavailable, see collabora/tests/EditTest.php).
	 *
	 * Pass criteria: getEditorLink() returns a falsy value when collabora is not in the
	 * user's enabled apps.
	 */
	public function testGetEditorLinkFalsyWithoutCollaboraApp()
	{
		$had_collabora = array_key_exists('collabora', $GLOBALS['egw_info']['user']['apps']);
		$prev_value = $GLOBALS['egw_info']['user']['apps']['collabora'] ?? null;
		unset($GLOBALS['egw_info']['user']['apps']['collabora']);

		try
		{
			$link = filemanager_hooks::getEditorLink();
			$this->assertEmpty($link, 'getEditorLink() must return a falsy value when no editor app is enabled');
		}
		finally
		{
			if ($had_collabora)
			{
				$GLOBALS['egw_info']['user']['apps']['collabora'] = $prev_value;
			}
		}
	}
}
