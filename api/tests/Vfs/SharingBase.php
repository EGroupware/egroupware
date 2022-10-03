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

		// Remove any added files (as root to limit versioning issues)
		if(in_array('/',$this->files))
		{
			$this->fail('Tried to remove root');
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
		$this->shareLink($dir, $mode, $extra);

		$files = Vfs::find('/', static::VFS_OPTIONS);

		if(static::LOG_LEVEL > 1)
		{
			echo "\nLinked files:\n".implode("\n", $files)."\n";
		}

		// Make sure files are the same
		$this->assertEquals($logged_in_files, $files);

		// Make sure all are readonly
		foreach($files as $file)
		{
			$this->checkOneFile($file, $mode);
		}
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
					$this->assertNotFalse(file_put_contents(Vfs::PREFIX.$file, 'Writable check'));
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
				'?user='.$GLOBALS['egw_info']['user']['account_id'].'&group=Default&mode=775';
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


		$backup = Vfs::$is_root;
		Vfs::$is_root = true;

		// I guess merge needs the dir in SQLFS first
		if(!Vfs::is_dir($dir)) Vfs::mkdir($path);
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
	 */
	public function shareLink($path, $mode, $extra = array())
	{
		if(static::LOG_LEVEL > 1)
		{
			echo __METHOD__ . "('$path',$mode)\n";
		}
		// Setup - create path and share
		$_SERVER['HTTP_HOST'] = 'localhost';
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
			$this->checkDirectoryLink($link, $share);
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

		// Our path should be mounted to root
		$this->assertTrue(Vfs::is_readable('/'), 'Could not read root (/) from link');

		// Check other paths
		$this->assertFalse(Vfs::is_readable($path), "Was able to read $path as anonymous, it should be mounted as /");
	}

	/**
	 * Test to make sure that a directory link leads to a limited filemanager
	 * interface (not a file or 404).
	 *
	 * @param type $link
	 * @param type $share
	 */
	public function checkDirectoryLink($link, $share)
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
		$html = curl_exec($curl);
		curl_close($curl);

		if(!$html)
		{
			// No response - could mean something is terribly wrong, or it could
			// mean we're running on Travis with no webserver to answer the
			// request
			return;
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
		$this->assertEquals('/', $data->data->content->nm->path, "Share was not mounted at /");

		unset($data->data->content->nm->actions);
		//var_dump($data->data->content->nm);
	}

	/**
	 * Check that we actually find the file we shared at the target link
	 *
	 * @param $link Share URL
	 * @param $file Vfs path to file
	 */
	public function checkSharedFile($link, $mimetype, $share)
	{
		$context = stream_context_create(
				array(
						'http' => array(
								'method' => 'HEAD',
				        'header' => "Cookie: XDEBUG_SESSION=PHPSTORM;".Api\Session::EGW_SESSION_NAME.'=' . $share['share_with']
						)
				)
		);
		$headers = get_headers($link, false, $context);
		$this->assertEquals('200', substr($headers[0], 9, 3), 'Did not find the file, got ' . $headers[0]);

		$indexed_headers = array();
		foreach($headers as &$header)
		{
			list($key, $value) = explode(': ', $header);
			if(is_string($indexed_headers[$key]))
			{
				$indexed_headers[$key] = array($indexed_headers[$key]);
			}
			if(is_array($indexed_headers[$key]))
			{
				$indexed_headers[$key][] = $value;
			}
			else
			{
				$indexed_headers[$key] = $value;
			}
		}

		$this->assertStringContainsString($mimetype, $indexed_headers['Content-Type'], 'Wrong file type');
	}

	/**
	 * Ask the server for the given share link.  Returns the response.
	 *
	 * @param $link
	 * @param $data Data passed to the etemplate
	 * @param $keep_session = true Keep the current session, or access with new session as anonymous
	 */
	public function getShare($link, &$data, $keep_session = true, &$_curl = null)
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

		// Setting this lets us debug the request too
		$cookie = 'XDEBUG_SESSION=PHPSTORM';
		if($keep_session)
		{
			 $cookie .= ';'.Api\Session::EGW_SESSION_NAME."={$GLOBALS['egw']->session->sessionid};kp3={$GLOBALS['egw']->session->kp3}";
		}
		curl_setopt($curl, CURLOPT_COOKIE, $cookie);
		$html = curl_exec($curl);
		if($_curl == null)
		{
			curl_close($curl);
		}

		if(!$html)
		{
			// No response - could mean something is terribly wrong, or it could
			// mean we're running on Travis with no webserver to answer the
			// request
			return;
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
		$this->assertNotNull($form, "Didn't find template in response");
		$data = json_decode($form->getAttribute('data-etemplate'), true);

		return $form;
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