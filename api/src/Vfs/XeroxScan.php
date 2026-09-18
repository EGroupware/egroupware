<?php
/**
 * EGroupware API: bridge for Xerox (and similar MFP) "Scan to HTTP/HTTPS" CGI-script protocol
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage vfs
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__.'/../WebDAV/Server.php';

use EGroupware\Api;
use EGroupware\Api\Vfs;
use HTTP_WebDAV_Server;

/**
 * Bridge implementing the CGI-script protocol Xerox (and some other) MFPs speak for their
 * "HTTP/HTTPS" Workflow/Network Scanning filing destination - configured on the device itself as
 * a "Script Name and Path" (Properties > Apps > Workflow Scanning > File Repository Setup, or
 * similar depending on model) pointing at this script's URL.
 *
 * This is deliberately NOT WebDAV and NOT a generic <form enctype="multipart/form-data"> upload -
 * it's the device POSTing multipart/form-data with its OWN fixed field names:
 * - "theOperation": one of "ListDir", "MakeDir", "PutFile" (also "RemoveDir"/"DeleteFile" per the
 *   protocol, not implemented here - a scan-to-folder workflow never needs them, see below)
 * - "destDir": target directory, relative to this script's own URL path (like webdav.php's home
 *   directory routing - eg. a "Script Name" of ".../xerox-scan.php/home/scanner/" plus a "destDir"
 *   of "incoming" targets "/home/scanner/incoming")
 * - "destName": filename (PutFile only)
 * - "sendfile": the file content (PutFile only) - the device may send this as a real file part
 *   (with its own filename=, landing in $_FILES) or as a plain field (landing in $_POST) -
 *   both are handled.
 *
 * These field names are NOT from an official, public Xerox specification (none exists) - they
 * come from Xerox's own downloadable sample CGI scripts, available from the printer's own web
 * admin UI (Properties > Apps > Workflow Scanning > File Repository Setup > Get Example Scripts),
 * as documented by a third-party reverse-engineered server implementation. The response
 * status/bodies this class sends back on success are accordingly a best-effort guess, not a
 * confirmed contract - if a real device doesn't accept them, enable filemanager's "WebDAV logging"
 * preference (@see Api\WebDAV\Hooks, shared with webdav.php) to see exactly what was
 * sent/received and adjust from there.
 */
class XeroxScan
{
	/**
	 * Handle the current request: parse operation + parameters, dispatch, send the response
	 */
	public function run()
	{
		$starttime = microtime(true);
		$base_path = rtrim($_SERVER['PATH_INFO'] ?? '/', '/');
		if ($base_path === '') $base_path = '/';
		$operation = $_POST['theOperation'] ?? '';

		try
		{
			switch ($operation)
			{
				case 'ListDir':
					$status = $this->listDir($base_path);
					break;
				case 'MakeDir':
					$status = $this->makeDir($base_path);
					break;
				case 'PutFile':
					$status = $this->putFile($base_path);
					break;
				case 'RemoveDir':
				case 'DeleteFile':
					// not needed for a scan-to-folder workflow; avoid guessing at a destructive
					// operation's exact contract without a real device to verify against
					$status = '501 Not Implemented';
					break;
				default:
					$status = '400 Bad Request';
			}
		}
		catch (\Throwable $e)
		{
			error_log(__METHOD__.'() '.$e->getMessage());
			$status = '500 Internal Server Error';
		}

		http_response_code((int)$status);
		header('HTTP/1.1 '.$status);

		// never let a logging bug corrupt an already-decided response into the generic
		// exception-handler's 401 (happened once during development - see git history)
		try
		{
			$this->log($operation, $status, $starttime);
		}
		catch (\Throwable $e)
		{
			error_log(__METHOD__.'() logging failed: '.$e->getMessage());
		}
	}

	/**
	 * "ListDir": reply with a plain, one-name-per-line directory listing if destDir exists
	 *
	 * @param string $base_path
	 * @return string HTTP status
	 */
	protected function listDir($base_path)
	{
		$target = Vfs::concat($base_path, $_POST['destDir'] ?? '');

		if (!Vfs::is_dir($target))
		{
			return '404 Not Found';
		}
		if (!Vfs::is_readable($target))
		{
			return '403 Forbidden';
		}
		header('Content-Type: text/plain; charset=utf-8');
		foreach ((array)Vfs::scandir($target) as $entry)
		{
			if ($entry !== '.' && $entry !== '..') echo $entry."\n";
		}
		return '200 OK';
	}

	/**
	 * "MakeDir": create destDir if it does not already exist (idempotent)
	 *
	 * @param string $base_path
	 * @return string HTTP status
	 */
	protected function makeDir($base_path)
	{
		$target = Vfs::concat($base_path, $_POST['destDir'] ?? '');

		if (Vfs::is_dir($target))
		{
			return '200 OK';	// already there - treat as success, the device may probe repeatedly
		}
		$parent = Vfs::dirname($target);
		if (!$parent || !Vfs::is_dir($parent) || !Vfs::is_writable($parent))
		{
			return '403 Forbidden';
		}
		if (!Vfs::mkdir($target, 0777, true))
		{
			return '500 Internal Server Error';
		}
		return '201 Created';
	}

	/**
	 * "PutFile": write destName's content (destDir/destName) - same quota hook PUT()/POST() use
	 *
	 * @param string $base_path
	 * @return string HTTP status
	 */
	protected function putFile($base_path)
	{
		$dir = Vfs::concat($base_path, $_POST['destDir'] ?? '');
		if (!($name = Vfs::sanitize_leaf_name($_POST['destName'] ?? '')))
		{
			return '400 Bad Request';
		}
		if (!Vfs::is_dir($dir) || !Vfs::is_writable($dir))
		{
			return '403 Forbidden';
		}
		$target = Vfs::concat($dir, $name);
		if (Vfs::is_dir($target))
		{
			return '403 Forbidden';	// can not overwrite a directory with a file
		}
		if (Vfs::file_exists($target) && !Vfs::is_writable($target))
		{
			return '403 Forbidden';
		}

		// the device may send the file as a real upload (filename=, lands in $_FILES) or as a
		// plain field (lands in $_POST) - handle both
		if (!empty($_FILES['sendfile']['tmp_name']) && is_uploaded_file($_FILES['sendfile']['tmp_name']))
		{
			if ($_FILES['sendfile']['error'] !== UPLOAD_ERR_OK)
			{
				return '400 Bad Request';
			}
			$src = fopen($_FILES['sendfile']['tmp_name'], 'r');
			$size = $_FILES['sendfile']['size'];
		}
		elseif (isset($_POST['sendfile']))
		{
			$src = fopen('php://temp', 'r+');
			fwrite($src, $_POST['sendfile']);
			rewind($src);
			$size = strlen($_POST['sendfile']);
		}
		else
		{
			return '400 Bad Request';
		}

		try
		{
			Api\Hooks::process(array(
				'location' => 'vfs_pre-write',
				'path'     => $target,
				'length'   => $size,
			));
		}
		catch (\Exception $e)
		{
			fclose($src);
			return '413 Payload Too Large';
		}

		if (!($dst = fopen(Vfs::PREFIX.$target, 'w')))
		{
			fclose($src);
			return '500 Internal Server Error';
		}
		$ok = stream_copy_to_stream($src, $dst) !== false;
		fclose($src);
		fclose($dst);

		return $ok ? '200 OK' : '500 Internal Server Error';
	}

	/**
	 * Log the request/response, if filemanager's "debug_level" preference is enabled - shares that
	 * preference (and the "WebDAV logging" tab/log-viewer) with webdav.php, @see Api\WebDAV\Hooks
	 *
	 * @param string $operation
	 * @param string $status
	 * @param float $starttime
	 */
	protected function log($operation, $status, $starttime)
	{
		$debug_level = $GLOBALS['egw_info']['user']['preferences']['filemanager']['debug_level'] ?? null;
		if ($debug_level !== 'r' && $debug_level !== 'f')
		{
			return;
		}

		$fields = $_POST;
		if (isset($fields['sendfile']))
		{
			// never log the actual (potentially huge, binary) file content
			$fields['sendfile'] = '<'.strlen((string)$fields['sendfile']).' bytes>';
		}

		$content = sprintf("*** %s %s\n", $_SERVER['REMOTE_ADDR'] ?? '?', date('c'));
		$content .= sprintf("POST %s%s HTTP/1.1\n", $_SERVER['SCRIPT_NAME'] ?? '', $_SERVER['PATH_INFO'] ?? '');
		foreach ($_SERVER as $name => $value)
		{
			$is_http = strncmp($name, 'HTTP_', 5) === 0;
			if ($is_http || strncmp($name, 'CONTENT_', 8) === 0)
			{
				$header = str_replace('_', '-', $is_http ? substr($name, 5) : $name);
				$content .= $header.': '.($header === 'AUTHORIZATION' ? 'Basic ***************' : $value)."\n";
			}
		}
		$content .= "\n\$_POST: ".json_encode($fields, JSON_UNESCAPED_SLASHES)."\n";
		if (!empty($_FILES))
		{
			$content .= '$_FILES: '.json_encode(array_map(static function($file)
			{
				return array('name' => $file['name'] ?? null, 'size' => $file['size'] ?? null,
					'error' => $file['error'] ?? null);
			}, $_FILES), JSON_UNESCAPED_SLASHES)."\n";
		}
		$content .= sprintf("--> operation=%s status=%s took %5.3fs\n\n",
			$operation !== '' ? $operation : '(none)', $status, microtime(true) - $starttime);

		if ($debug_level === 'f')
		{
			$log_dir = $GLOBALS['egw_info']['server']['files_dir'].'/filemanager/'.
				HTTP_WebDAV_Server::sanitize_filename($GLOBALS['egw_info']['user']['account_lid']);
			if (!file_exists($log_dir) && !mkdir($log_dir, 0700, true) && !is_dir($log_dir))
			{
				error_log(__METHOD__."() Could NOT create directory '$log_dir'!");
				return;
			}
			file_put_contents($log_dir.'/xerox-scan.log', $content, FILE_APPEND|LOCK_EX);
		}
		else
		{
			foreach (explode("\n", $content) as $line)
			{
				error_log($line);
			}
		}
	}
}
