<?php

/**
 * Base for testing sharing
 *
 * This holds some common things so we can re-use them for the various places
 * that use sharing (API, Collabora)
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/../LoggedInTest.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;
use EGroupware\Stylite\Vfs\Versioning;


class SharingBase extends LoggedInTest
{
	/**
	 * How much should be logged to the console (stdout)
	 *
	 * 0 = Nothing
	 * 1 = info
	 * 2 = debug
	 */
	const LOG_LEVEL = 0;

	/**
	 * Environment variable saying that nothing is expected to answer the share link.
	 *
	 * These tests fetch the share over real HTTP, because that is the only way to check the
	 * thing that actually matters: that someone we sent a link to gets the files.  Where no
	 * webserver is running at all - a bare checkout, a container with nothing on the port in
	 * EGW_URL - that is an environment problem and skipping is honest.  Set this there.
	 *
	 * Everywhere else a share request that goes unanswered, redirects away, comes back empty
	 * or comes back as the wrong page IS the bug these tests exist to catch, and is reported
	 * as a failure.  Inferring "no webserver" from those symptoms instead, as this class used
	 * to, meant a broken share reported as "skipped" and the suite still exited 0.
	 */
	const NO_WEBSERVER_ENV = 'EGW_TEST_NO_WEBSERVER';

	/**
	 * host[:port] to build share links against.
	 *
	 * EGW_URL wins, the way every other test that needs a webserver resolves it (WebDAVTest,
	 * CalDAVTest, LogoutRedirectXssTest).  This used to consult EGW_URL only when the
	 * webserver_url config was *empty* - but that config is normally a bare path ("/egroupware"),
	 * which is non-empty and carries no host, so the fallback never ran and every share link was
	 * pinned to localhost:80.  Where something happens to listen there it silently worked; in CI
	 * nothing does, and the resulting connection failure was reported as a skipped test.
	 *
	 * @return string
	 */
	protected static function webserverHost() : string
	{
		foreach([
			getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null),
			$GLOBALS['egw_info']['server']['webserver_url'] ?? null,
		] as $url)
		{
			if(empty($url) || empty($parts = parse_url($url)) || empty($parts['host']))
			{
				continue;
			}
			return $parts['host'].(!empty($parts['port']) ? ':'.$parts['port'] : '');
		}
		return 'localhost';
	}

	/**
	 * Hand our session over before asking the webserver to use it.
	 *
	 * The keep-session paths send this process's own session cookie.  PHP locks a session file
	 * exclusively, so while we still hold it open the webserver blocks in session_start() until
	 * the request times out - fatal against a single-threaded `php -S`, which is what CI runs.
	 */
	protected function releaseSessionForWebserver() : void
	{
		if(isset($GLOBALS['egw']->session) && session_status() === PHP_SESSION_ACTIVE)
		{
			$GLOBALS['egw']->session->commit_session();
		}
	}

	/**
	 * Nothing answered the share request at all (no HTTP status).
	 *
	 * Skipped only where the caller has declared there is no webserver, otherwise a failure.
	 *
	 * @param string $message
	 */
	protected function noWebserverResponse(string $message) : void
	{
		if((string)getenv(self::NO_WEBSERVER_ENV) !== '')
		{
			$this->markTestSkipped($message);
		}
		$this->fail($message . "\nIf this environment has no webserver, set " . self::NO_WEBSERVER_ENV . "=1 to skip instead.");
	}

	/**
	 * Keep track of shares to remove after
	 */
	protected $shares = Array();

	/**
	 * Keep track of files to remove after
	 * @var Array
	 */
	protected $files = Array();

	/**
	 * Keep track of mounts to remove after
	 */
	protected $mounts = Array();

	/**
	 * Entries that have to be deleted after
	 */
	protected $entries = Array();

	/**
	 * Options for searching the Vfs (Vfs::find())
	 */
	const VFS_OPTIONS = array(
		'maxdepth' => 5
	);

	protected function setUp() : void
	{
		// Check we have basic access
		if(!is_readable($GLOBALS['egw_info']['server']['files_dir']))
		{
			$this->markTestSkipped('No read access to files dir "' .$GLOBALS['egw_info']['server']['files_dir'].'"' );
		}
		if(!is_writable($GLOBALS['egw_info']['server']['files_dir']))
		{
			$this->markTestSkipped('No write access to files dir "' .$GLOBALS['egw_info']['server']['files_dir'].'"' );
		}

	}

	protected function tearDown() : void
	{
		try
		{
			// Some tests may leave us logged out, which will cause failures in parent cleanup
			LoggedInTest::tearDownAfterClass();
		}
		catch(\Throwable $e) {}

		LoggedInTest::setupBeforeClass();


		// Need to ask about mounts, or other tests fail
		Vfs::mount();

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;

		if(static::LOG_LEVEL > 1)
		{
			error_log($this->getName() . ' files for removal:');
			error_log(implode("\n",$this->files));
			error_log($this->getName() . ' mounts for removal:');
			error_log(implode("\n",$this->mounts));
			error_log($this->getName() . ' shares for removal:');
			error_log(implode("\n",$this->shares));
		}

		// Remove any added files (as root to limit versioning issues).
		// Strict comparison: loose in_array('/') also matches a bare true, which any
		// "Vfs::touch($p) ?: $p" in a test quietly puts here, so a working test reported
		// "Tried to remove root".  Non-strings are rejected on their own terms instead,
		// since passing one on to Vfs::is_dir() is no better.
		if(in_array('/', $this->files, true))
		{
			$this->fail('Tried to remove root');
		}
		foreach($this->files as $file)
		{
			$this->assertIsString($file, 'Non-path ' . gettype($file) . ' in the cleanup list - a test pushed a return value instead of a path');
		}
		foreach($this->files as $file)
		{
			if(Vfs::is_dir($file) && !Vfs::is_link(($file)))
			{
				Vfs::rmdir($file);
			}
			else
			{
				Vfs::unlink($file);
			}
		}
		Vfs::remove($this->files);

		// Remove any mounts
		foreach($this->mounts as $mount)
		{
			// Do not remove /apps
			if($mount == '/apps') continue;

			Vfs::umount($mount);
		}

		// Remove any added shares
		foreach($this->shares as $share)
		{
			Sharing::delete($share);
		}

		foreach($this->entries as $entry)
		{
			list($callback, $params) = $entry;
			call_user_func_array($callback, $params);
		}


		Vfs::$is_root = $backup;
	}


	/**
	 * Check a given directory to see that a link to it works.
	 *
	 * We check
	 * - Files/directories available to original user are available through share
	 * - Permissions match share (Read / Write)
	 * - Files are not empty
	 *
	 * @param string $dir
	 * @param string $mode
	 */
	protected function checkDirectory($dir, $mode)
	{
		if(static::LOG_LEVEL)
		{
			echo "\n".__METHOD__ . "($dir, $mode)\n";
		}
		if(substr($dir, -1) != '/')
		{
			$dir .= '/';
		}
		if(!Vfs::is_readable($dir))
		{
			Vfs::mkdir($dir);
			$this->files[] = $dir;
		}
		$this->files += $this->addFiles($dir);

		$logged_in_files = array_map(
			function($path) use ($dir) {return str_replace($dir, '/', $path);},
			Vfs::find($dir, static::VFS_OPTIONS)
		);

		if(static::LOG_LEVEL > 1)
		{
			echo "\n".$this->getName();
			echo "\nLogged in files:\n".implode("\n", $logged_in_files)."\n";
		}

		// Create and use link
		$extra = array();
		$this->getShareExtra($dir, $mode, $extra);
		$mounted = $this->shareLink($dir, $mode, $extra);

		$files = array_map(
			function ($path) use ($mounted)
			{
				return str_replace($mounted, '', $path) ?: '/';
			},
			Vfs::find($mounted, static::VFS_OPTIONS)
		);

		if(static::LOG_LEVEL > 1)
		{
			echo "\nLinked files:\n".implode("\n", $files)."\n";
		}

		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are readonly
		foreach($files as $file)
		{
			$this->checkOneFile($mounted . $file, $mode);
		}
		return $mounted;
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
	 * Check the access permissions for one file/directory
	 *
	 * @param string $file
	 * @param string $mode
	 */
	protected function checkOneFile($file, $mode)
	{
		if(static::LOG_LEVEL > 1)
		{
			$stat = Vfs::stat($file);
			echo "\t".Vfs::int2mode($stat['mode'])."\t$file\n";
		}
		$this->assertTrue(file_exists(Vfs::PREFIX . $file), "$file does not exist");

		// All the test files have something in them
		if(!Vfs::is_dir($file))
		{
			$this->assertNotEmpty(file_get_contents(Vfs::PREFIX.$file), "$file was empty");
		}

		// Check permissions
		switch($mode)
		{
			case Sharing::READONLY:
				$this->assertFalse(Vfs::is_writable($file), "Readonly share file '$file' is writable");
				if(!Vfs::is_dir($file))
				{
					// We expect this to fail
					$this->assertFalse(@file_put_contents(Vfs::PREFIX.$file, 'Writable check'));
				}
				break;
			case Sharing::WRITABLE:
				// Root is not writable
				if($file == '/') break;

				$this->assertTrue(Vfs::is_writable($file), $file . ' was not writable');
				if(!Vfs::is_dir($file))
				{
					$content = file_get_contents(Vfs::PREFIX . $file);
					$this->assertNotFalse(file_put_contents(Vfs::PREFIX.$file, 'Writable check'));
					// Put it back
					file_put_contents(Vfs::PREFIX . $file, $content);
				}
				break;
		}

	}

	/**
	 * Mount the app entries into the filesystem
	 *
	 * @param string $path
	 */
	protected function mountLinks($path)
	{
		Vfs::$is_root = true;
		$url = Links\StreamWrapper::PREFIX . '/apps';
		$this->assertTrue(
				Vfs::mount($url, $path, false, false),
				"Unable to mount $url => $path"
		);
		Vfs::$is_root = false;

		$this->mounts[] = $path;
	}

	/**
	 * Start versioning for the given path
	 *
	 * @param string $path
	 */
	protected function mountVersioned($path)
	{
		if(!class_exists('EGroupware\Stylite\Vfs\Versioning\StreamWrapper'))
		{
			$this->markTestSkipped("No versioning available");
		}
		if(substr($path, -1) == '/')
		{
			$path = substr($path, 0, -1);
		}
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$url = Versioning\StreamWrapper::PREFIX . $path;
		$this->assertTrue(Vfs::mount($url, $path, false), "Unable to mount $path as versioned");
		Vfs::$is_root = $backup;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * Mount a test filesystem path (api/tests/fixtures/Vfs/filesystem_mount)
	 * at the given VFS path
	 *
	 * @param string $path
	 */
	protected function mountFilesystem($path)
	{
		// Vfs breaks if path has trailing /
		if(substr($path, -1) == '/') $path = substr($path, 0, -1);

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		$fs_path = realpath(__DIR__ . '/../fixtures/Vfs/filesystem_mount');
		if(!file_exists($fs_path))
		{
			$this->fail("Missing filesystem test directory 'api/tests/fixtures/Vfs/filesystem_mount'");
		}
		$url = Filesystem\StreamWrapper::SCHEME.'://default'. $fs_path.
			'?user=' . $GLOBALS['egw_info']['user']['account_id'] . '&group=Default&mode=770';
		$this->assertTrue(Vfs::mount($url,$path), "Unable to mount $url to $path");
		Vfs::$is_root = $backup;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * Merge a test filesystem path (api/tests/fixtures/Vfs/filesystem_mount)
	 * with the given VFS path
	 *
	 * @param string $path
	 */
	protected function mountMerge($path)
	{
		// Vfs breaks if path has trailing /
		if(substr($path, -1) == '/') $path = substr($path, 0, -1);

		$this->assertNotFalse(realpath(__DIR__ . '/../fixtures/Vfs/filesystem_mount'), "Missing filesystem test directory 'api/tests/fixtures/Vfs/filesystem_mount'");

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;

		// I guess merge needs the dir in SQLFS first
		if(!Vfs::is_dir($path))
		{
			Vfs::mkdir($path);
		}
		Vfs::chmod($path, 0750);
		Vfs::chown($path, $GLOBALS['egw_info']['user']['account_id']);

		$url = \EGroupware\Stylite\Vfs\Merge\StreamWrapper::SCHEME . '://default' . $path . '?merge=' . realpath(__DIR__ . '/../fixtures/Vfs/filesystem_mount');
		$this->assertTrue(Vfs::mount($url, $path, false), "Unable to mount $url to $path");
		Vfs::$is_root = $backup;

		$this->mounts[] = $path;
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();
	}

	/**
	 * Add some files to the given path so there's something to find.
	 *
	 * @param string $path
	 *
	 * @return array of paths
	 */
	protected function addFiles($path, $content = false)
	{
		if(substr($path, -1) != '/')
		{
			$path .= '/';
		}
		if(!$content)
		{
			$content = 'Test for ' . $this->getName() ."\n". Api\DateTime::to();
		}
		$files = array();

		// Plain file
		$files[] = $file = $path.'test_file.txt';
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, $content) !== FALSE,
			'Unable to write test file "' . Vfs::PREFIX . $file .'" - check file permissions for CLI user'
		);
		$this->assertEquals($content, file_get_contents(Vfs::PREFIX . $file), "Unable to write test file (empty)");

		// Subdirectory
		$files[] = $dir = $path.'sub_dir/';
		if(Vfs::is_dir($dir))
		{
			Vfs::remove($dir);
		}
		$this->assertTrue(
			Vfs::mkdir($dir),
			'Unable to create subdirectory ' . $dir
		);

		// File in a subdirectory
		$files[] = $file = $dir.'subdir_test_file.txt';
		$this->assertTrue(
			file_put_contents(Vfs::PREFIX.$file, $content) !== FALSE,
			'Unable to write test file "' . Vfs::PREFIX . $file .'" - check file permissions for CLI user'
		);

		// Symlinked file
		/* We don't test these because they don't work - the target will always
		 * be outside the share root
		// Always says its empty
		$files[] = $symlink = $path.'symlink.txt';
		if(Vfs::file_exists($symlink)) Vfs::remove($symlink);
		$this->assertTrue(
			Vfs::symlink($file, $symlink),
			"Unable to create symlink $symlink => $file"
		);

		// Symlinked dir
		$files[] = $symlinked_dir = $path.'sym_dir/';
		$this->assertTrue(
			Vfs::symlink($dir, $symlinked_dir),
			'Unable to create symlinked directory ' . $symlinked_dir
		);
*/
		return $files;
	}

	/**
	 * Test that readable shares are actually readable
	 *
	 * @param string $path
	 */
	public function createShare($path, $mode, $extra = array())
	{
		// Every test turns its share into a link, and share2link() resolves the host through
		// Http::host(true), which prefers a configured hostname over HTTP_HOST - so on any
		// install that has one the link points at that install instead of the webserver the
		// test was told to use, and the test quietly checks the wrong server.
		$_SERVER['HTTP_HOST'] = $GLOBALS['egw_info']['server']['hostname'] = static::webserverHost();

		// Make sure the path is there
		if(!Vfs::is_readable($path))
		{
			$this->assertTrue(
					Vfs::is_dir($path) ? Vfs::mkdir($path,0750,true) : Vfs::touch($path),
					"Share path $path does not exist"
			);
		}

		// Create share
		$this->shares[] = $share = TestSharing::create('', $path, $mode, $name, $recipients, $extra);

		return $share;
	}

	public function readShare($share_id)
	{
		foreach ($GLOBALS['egw']->db->select(Sharing::TABLE, '*',
				array(
						'share_id' => (int)$share_id
				),
				__LINE__, __FILE__, false) as $share)
		{
			return $share;
		}
		return array();
	}

	/**
	 * Make an infolog entry
	 */
	protected function make_infolog()
	{
		$bo = new \infolog_bo();
		$element = array(
				'info_subject' => "Test infolog for #{$this->getName()}",
				'info_des' => 'Test element for ' . $this->getName() . "\n" . Api\DateTime::to(),
				'info_status' => 'open'
		);

		$element_id = $bo->write($element, true, true, true, true);
		return $element_id;
	}

	/**
	 * Test that a share link can be made, and that only that path is available
	 *
	 * @param string $path
	 *
	 * @return mounted path (as anonymous user)
	 */
	public function shareLink($path, $mode, $extra = array())
	{
		if(static::LOG_LEVEL > 1)
		{
			echo __METHOD__ . "('$path',$mode)\n";
		}
		// Setup - create path and share.  createShare() points the host at the test webserver
		// too, but a subclass may override it (SharingACLTest does, for hidden-upload shares),
		// so the link built below gets the same treatment either way.
		$_SERVER['HTTP_HOST'] = $GLOBALS['egw_info']['server']['hostname'] = static::webserverHost();
		$share = $this->createShare($path, $mode, $extra);
		$link = Vfs\Sharing::share2link($share);

		if(static::LOG_LEVEL)
		{
			echo __METHOD__ . " link: $link\n";
			echo __METHOD__ . " share: " . array2string($share) . "\n";
		}

		// Setup for share to load
		$_GET['access_token'] = $share['share_token'];
		$_SERVER['REQUEST_URI'] = $link;
		preg_match('|^https?://[^/]+(/.*)share.php/'.$share['share_token'].'$|', $path_info=$_SERVER['REQUEST_URI'], $matches);
        $_SERVER['SCRIPT_NAME'] = $matches[1];
		$is_dir = Vfs::is_dir($path);
		$mimetype = Vfs::mime_content_type($path);
		$mounted_path = '/'.Vfs::basename(trim($share['share_path'], '/'));


		// Re-init, since they look at user, fstab, etc.
		// Also, further tests that access the filesystem fail if we don't
		Vfs::clearstatcache();
		Vfs::init_static();
		Vfs\StreamWrapper::init_static();

		// Log out & clear cache
		LoggedInTest::tearDownAfterClass();

		// If it's a directory, check to make sure it gives the filemanager UI
		if($is_dir)
		{
			$mounted_path = $this->checkDirectoryLink($link, $share) ?: $mounted_path;
		}
		else
		{
			// If it's a file, check to make sure we get the file
			$this->checkSharedFile($link, $mimetype, $share);
		}

		// Load share
		$this->setup_info();

		// Sometimes Vfs::$db gets lost.  Reason unknown.
		Vfs::$db = $GLOBALS['egw']->db;

		if(static::LOG_LEVEL > 1)
		{
			echo "Sharing mounts:\n";
			var_dump(Vfs::mount());
		}

		// Our path should not be mounted to root
		$this->assertFalse(Vfs::is_readable('/'), 'Share was mounted to root and will conflict with other shares');

		// Check other paths
		//$this->assertFalse(Vfs::is_readable($path), "Was able to read $path as anonymous, it should be mounted as $mounted_path");

		return $mounted_path;
	}

	/**
	 * Test to make sure that a directory link leads to a limited filemanager
	 * interface (not a file or 404).
	 *
	 * @param type $link
	 * @param type $share
	 */
	public function checkDirectoryLink($link, $share, $expect_rows = true)
	{
		// Set up curl
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 3);
		$cookie = '';
		if($GLOBALS['egw']->session->sessionid || $share['share_with'])
		{
			$session_id = $GLOBALS['egw']->session->sessionid ?: $share['share_with'];
			$cookie .= ';'.Api\Session::EGW_SESSION_NAME."={$session_id}";
		}
		curl_setopt($curl, CURLOPT_COOKIE, $cookie);
		$this->releaseSessionForWebserver();
		$html = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$curl_errno = curl_errno($curl);
		$curl_error = curl_error($curl);
		$effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
		curl_close($curl);

		if(!$html)
		{
			// This used to return quietly, which passed the test: the whole web-facing half of
			// the check disappeared without a word in the report while the VFS-level checks in
			// checkDirectory() carried on and went green.
			if($http_code === 0)
			{
				$this->noWebserverResponse("No webserver response for share link '$link' (curl errno $curl_errno: $curl_error)");
			}
			$this->fail("Share link '$link' returned no content (HTTP $http_code, effective URL '$effective_url')");
		}

		// Parse & check for nextmatch
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new \DOMXPath($dom);
		$form = $xpath->query ('//form')->item(0);
		if(!$form && static::LOG_LEVEL)
		{
			echo "Didn't find filemanager interface\n";
			if(static::LOG_LEVEL > 1)
			{
				echo $form."\n\n";
			}
		}
		$this->assertNotNull($form, "Didn't find filemanager interface");
		$data = json_decode($form->getAttribute('data-etemplate'));

		$this->assertEquals('filemanager.index', $data->name);

		// Make sure we start at root, not somewhere else like the token mounted
		// as a sub-directory
		$expected_path = '/' . Vfs::basename(trim($share['share_path'], '/'));
		$this->assertEquals($expected_path, $data->data->content->nm->path, "Share was not mounted at $expected_path");

		// The recipient has to actually see the files.  Nothing here checked that, so a share
		// that rendered the filemanager UI over an empty list - the most common way this breaks
		// for a client - passed.  Every caller shares a directory with files in it; a test that
		// deliberately shares an empty directory should pass $expect_rows = false.
		if($expect_rows)
		{
			$this->assertNotEmpty(
				(array)($data->data->content->nm->rows ?? []),
				"Share link '$link' rendered the filemanager but listed no files"
			);
		}

		unset($data->data->content->nm->actions);
		//var_dump($data->data->content->nm);

		return $expected_path;
	}

	/**
	 * Check that we actually find the file we shared at the target link
	 *
	 * @param $link Share URL
	 * @param $file Vfs path to file
	 */
	public function checkSharedFile($link, $mimetype, $share, $expected_content = null)
	{
		$curl = curl_init($link);
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
		$cookie = 'XDEBUG_SESSION=PHPSTORM';
		if(($GLOBALS['egw']->session->sessionid ?? null) || ($share['share_with'] ?? null))
		{
			$session_id = ($GLOBALS['egw']->session->sessionid ?? null) ?: $share['share_with'];
			$cookie .= ';'.Api\Session::EGW_SESSION_NAME.'='.$session_id;
		}
		curl_setopt($curl, CURLOPT_COOKIE, $cookie);
		$this->releaseSessionForWebserver();
		curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$content_type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
		$curl_errno = curl_errno($curl);
		$curl_error = curl_error($curl);
		$effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);

		// Some CI/webserver stacks do not complete HEAD redirect chains as expected.
		// Retry with GET before asserting, to avoid false negatives.
		if($http_code >= 300 && $http_code < 400)
		{
			curl_setopt($curl, CURLOPT_NOBODY, false);
			curl_setopt($curl, CURLOPT_HTTPGET, true);
			curl_exec($curl);
			$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$content_type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
			$curl_errno = curl_errno($curl);
			$curl_error = curl_error($curl);
			$effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
		}
		curl_close($curl);

		if($http_code === 0)
		{
			$this->noWebserverResponse("No webserver response for share link '$link' (curl errno $curl_errno: $curl_error)");
		}
		$this->assertLessThan(300, $http_code, "Share link '$link' ended in HTTP $http_code at '$effective_url' - a recipient following this link does not reach the file");
		$this->assertEquals(200, $http_code, "Did not find the file, got HTTP status $http_code at '$effective_url'");
		$this->assertStringContainsString($mimetype, $content_type, 'Wrong file type');

		// If we have expected content, make sure the body actually contains it.
		// A 0-byte or wrong-content response (e.g. an error page) would otherwise
		// pass the HEAD-only checks above. Use GET since the body is what matters.
		if($expected_content !== null)
		{
			curl_setopt($curl, CURLOPT_NOBODY, false);
			curl_setopt($curl, CURLOPT_HTTPGET, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			$body = curl_exec($curl);
			$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			$this->assertEquals(200, $http_code, "GET for content returned HTTP $http_code at '$effective_url'");
			$this->assertNotEmpty($body, "Share link '$link' returned an empty body");
			$this->assertStringContainsString($expected_content, $body,
				"Share link '$link' did not return the expected file content");
		}
	}

	/**
	 * Ask the server for the given share link.  Returns the response.
	 *
	 * @param $link
	 * @param $data Data passed to the etemplate
	 * @param $keep_session = true Keep the current session, or access with new session as anonymous
	 */
	public function getShare($link, &$data, $keep_session = true, &$_curl = null, $expect_nextmatch = true)
	{
		// Set up curl
		if($_curl == null)
		{
			$curl = curl_init($link);
		}
		else
		{
			$curl = $_curl;
			curl_setopt($curl, CURLOPT_URL, $link);
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($curl, CURLOPT_TIMEOUT, 15);
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);

		// Setting this lets us debug the request too
		$cookies = ['XDEBUG_SESSION' => 'PHPSTORM'];
		if($keep_session)
		{
			$cookies[Api\Session::EGW_SESSION_NAME] = $GLOBALS['egw']->session->sessionid;
			$cookies['kp3'] = $GLOBALS['egw']->session->kp3;
		}
		$this->addCookies($curl, $cookies);

		// Keep the response headers: when this fails the body is usually empty, and the headers
		// are then the only thing that says why - EGroupware reports a refused share through
		// them (X-WebDAV-Status, or the exception message as a basic-auth realm), and they are
		// in hand here whether or not anything reached the server log.
		$response_headers = [];
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$response_headers)
		{
			if(trim($header) !== '')
			{
				$response_headers[] = trim($header);
			}
			return strlen($header);
		});

		$this->releaseSessionForWebserver();
		$html = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$effective_url = (string)curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
		$curl_errno = curl_errno($curl);
		$curl_error = curl_error($curl);
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, null);
		$header_dump = $response_headers ? "\nResponse headers:\n  " . implode("\n  ", $response_headers) : '';
		if($_curl == null)
		{
			curl_close($curl);
		}

		if(!$html)
		{
			if($http_code === 0)
			{
				$this->noWebserverResponse("No webserver response for share link '$link' (curl errno $curl_errno: $curl_error)");
			}
			// An empty body with a real HTTP status is the blank-page the recipient sees
			$this->fail("Share link '$link' returned no content (HTTP $http_code, effective URL '$effective_url')" . $header_dump);
		}

		// Parse & check for nextmatch
		$dom = new \DOMDocument();
		@$dom->loadHTML($html);
		$xpath = new \DOMXPath($dom);
		$form = $xpath->query ('//form')->item(0);
		if(!$form && static::LOG_LEVEL)
		{
			echo "Didn't find editor\n";
			if(static::LOG_LEVEL > 1)
			{
				echo "Got this instead:\n".($form?$form:$html)."\n\n";
			}
		}
		$this->assertNotNull($form, "Share link '$link' did not return the expected template (HTTP $http_code, effective URL '$effective_url')"
			. $header_dump . "\nFirst 500 bytes of the body:\n" . substr($html, 0, 500));
		$data = json_decode($form->getAttribute('data-etemplate'), true);

		// Not asserted non-empty here: a caller sharing an empty directory legitimately gets no
		// rows, and the callers that know which files to expect check them via checkNextmatch().
		// The structure itself must be there though - without it every such check silently
		// compares against nothing.  Not every share response is a listing: a share opened by a
		// logged-in user answers the mount dialog instead, so that caller passes false.
		if($expect_nextmatch)
		{
			$this->assertArrayHasKey('nm', $data['data']['content'] ?? [], "Share response carried no nextmatch at all (HTTP $http_code, '$effective_url')");
			$this->assertIsArray($data['data']['content']['nm']['rows'] ?? null, "Share response's nextmatch had no rows array (HTTP $http_code, '$effective_url')");
		}

		return $form;
	}

	private function addCookies($curl, $cookies)
	{
		// Read cookies from cURL
		$existing = curl_getinfo($curl, CURLINFO_COOKIELIST) ?: [];

		$cookieMap = [];

		// Parse cookies
		foreach($existing as $line)
		{
			// domain \t flag \t path \t secure \t expiry \t name \t value
			$parts = explode("\t", $line);
			if(count($parts) >= 7)
			{
				$cookieMap[$parts[5]] = $parts[6];
			}
		}

		// Merge (new cookies override old)
		$cookieMap = array_merge($cookieMap, $cookies);

		// Rebuild Cookie header
		$cookieHeader = '';
		foreach($cookieMap as $name => $value)
		{
			$cookieHeader .= "$name=$value; ";
		}

		curl_setopt($curl, CURLOPT_COOKIE, rtrim($cookieHeader, '; '));
	}


	protected function setup_info()
	{
		// Copied from share.php
		$GLOBALS['egw_info'] = array(
			'flags' => array(
				'disable_Template_class' => true,
				'noheader'  => true,
				'nonavbar' => 'always',	// true would cause eTemplate to reset it to false for non-popups!
				'currentapp' => 'filemanager',
				'autocreate_session_callback' => 'EGroupware\\Api\\Vfs\\TestSharing::create_session',
				'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
			)
		);

		ob_start();
		static::load_egw('anonymous','','',$GLOBALS['egw_info']);
		if(static::LOG_LEVEL > 1)
		{
			ob_end_flush();
		}
		else
		{
			ob_end_clean();
		}
	}
}

/**
 * Use this class for sharing so we can make sure we get a session ID, even
 * though we're on the command line
 */
if(!class_exists('TestSharing'))
{
class TestSharing extends Api\Vfs\Sharing {

	public static function create_new_session()
	{
		if (!($sessionid = $GLOBALS['egw']->session->create('anonymous@'.$GLOBALS['egw_info']['user']['domain'],
			'', 'text', false, false)))
		{
			// Allow for testing
			$sessionid = 'CLI_TEST ' . time();
			$GLOBALS['egw']->session->sessionid = $sessionid;
		}
		return $sessionid;
	}

	public static function get_share_class(array $share)
	{
		return __CLASS__;
	}
}
}

/**
 * Use this class for sharing so we can make sure we get a session ID, even
 * though we're on the command line
 */
if(!class_exists('TestHiddenSharing'))
{
	class TestHiddenSharing extends Api\Vfs\HiddenUploadSharing {

		public static function create_new_session()
		{
			if (!($sessionid = $GLOBALS['egw']->session->create('anonymous@'.$GLOBALS['egw_info']['user']['domain'],
					'', 'text', false, false)))
			{
				// Allow for testing
				$sessionid = 'CLI_TEST ' . time();
				$GLOBALS['egw']->session->sessionid = $sessionid;
			}
			return $sessionid;
		}

		public static function get_share_class(array $share)
		{
			return __CLASS__;
		}
	}
}
