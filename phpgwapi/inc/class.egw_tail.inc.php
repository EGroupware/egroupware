<?php
/**
 * EGroupware - Ajax log file viewer (tail -f)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2012 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @version $Id$
 */

/**
 * Ajax log file viewer (tail -f)
 *
 * To not allow to view arbitrary files, allowed filenames are stored in the session.
 * Class fetches log-file periodically in chunks for 8k.
 * If fetch returns no new content next request will be in 2s, otherwise in 200ms.
 * As logfiles can be quiet huge, we display at max the last 32k of it!
 *
 * Example usage:
 *
 * $error_log = new egw_tail('/var/log/apache2/error_log');
 * echo $error_log->show();
 *
 * Strongly prefered for security reasons is to use a path relative to EGroupware's files_dir,
 * eg. new egw_tail('groupdav/somelog')!
 */
class egw_tail
{
	/**
	 * Maximum size of single ajax request
	 *
	 * Currently also maximum size / 4 of displayed logfile content!
	 */
	const MAX_CHUNK_SIZE = 8192;

	/**
	 * Contains allowed filenames to display, we can NOT allow to display arbitrary files!
	 *
	 * @param array
	 */
	protected $filenames;

	/**
	 * Filename class is instanciated to view, set by constructor
	 *
	 * @param string
	 */
	protected $filename;

	/**
	 * Methods allowed to call via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'download' => true,
	);

	/**
	 * Constructor
	 *
	 * @param string $filename=null if not starting with as slash relative to EGw files dir (this is strongly prefered for security reasons)
	 */
	public function __construct($filename=null)
	{
		$this->filenames =& egw_cache::getSession('phpgwapi', __CLASS__);

		if ($filename)
		{
			$this->filename = $filename;

			if (!$this->filenames || !in_array($filename,$this->filenames)) $this->filenames[] = $filename;
		}
	}

	/**
	 * Ajax callback to load next chunk of log-file
	 *
	 * @param string $filename
	 * @param int $start=0 last position in log-file
	 * @throws egw_exception_wrong_parameter
	 */
	public function ajax_chunk($filename,$start=0)
	{
		if (!in_array($filename,$this->filenames))
		{
			throw new egw_exception_wrong_parameter("Not allowed to view '$filename'!");
		}
		if ($filename[0] != '/') $filename = $GLOBALS['egw_info']['server']['files_dir'].'/'.$filename;

		if (file_exists($filename))
		{
			$size = filesize($filename);
			if (!$start || $start < 0 || $start > $size || $size-$start > 4*self::MAX_CHUNK_SIZE)
			{
				$start = $size - 4*self::MAX_CHUNK_SIZE;
				if ($start < 0) $start = 0;
			}
			$size = egw_vfs::hsize($size);
			$content = file_get_contents($filename, false, null, $start, self::MAX_CHUNK_SIZE);
			$length = bytes($content);
			$writable = is_writable($filename) || is_writable(dirname($filename));
		}
		else
		{
			$start = $length = 0;
			$content = '';
			$writable = $size = false;
		}
		$response = egw_json_response::get();
		$response->data(array(	// send all responses as data
			'size' => $size,
			'writable' => $writable,
			'next' => $start + $length,
			'length' => $length,
			'content' => $content,
		));
	}

	/**
	 * Ajax callback to delete log-file
	 *
	 * @param string $filename
	 * @param boolean $truncate=false true: truncate file, false: delete file
	 * @throws egw_exception_wrong_parameter
	 */
	public function ajax_delete($filename,$truncate=false)
	{
		if (!in_array($filename,$this->filenames))
		{
			throw new egw_exception_wrong_parameter("Not allowed to view '$filename'!");
		}
		if ($filename[0] != '/') $filename = $GLOBALS['egw_info']['server']['files_dir'].'/'.$filename;
		if ($truncate)
		{
			file_put_contents($filename, '');
		}
		else
		{
			unlink($filename);
		}
	}

	/**
	 * Return html & javascript for logviewer
	 *
	 * @param string $header=null default $this->filename
	 * @return string
	 * @throws egw_exception_wrong_parameter
	 */
	public function show($header=null)
	{
		if (!isset($this->filename))
		{
			throw new egw_exception_wrong_parameter("Must be instanciated with filename!");
		}
		if (is_null($header)) $header = $this->filename;

		return '
<p style="float: left; margin: 5px"><b>'.htmlspecialchars($header).'</b></p>
<div style="float: right; margin: 2px; margin-right: 5px">
	'.html::form(
		html::input('clear_log',lang('Clear window'),'button','id="clear_log"')."\n".
		html::input('delete_log',lang('Delete file'),'button','id="purge_log"')."\n".
		html::input('empty_log',lang('Empty file'),'button','id="empty_log"')."\n".
		html::input('download_log',lang('Download'),'submit','id="download_log"'),
		'','/index.php',array(
		'menuaction' => 'phpgwapi.egw_tail.download',
		'filename' => $this->filename,
	)).'
</div>
<pre class="tail" id="log" data-filename="'.$this->filename.'" style="clear: both; width: 99.5%; border: 2px groove silver; margin-bottom: 0; overflow: auto;"></pre>';
	}

	/**
	 * Download a file specified per GET parameter (must be in $this->filesnames!)
	 *
	 * @throws egw_exception_wrong_parameter
	 */
	public function download()
	{
		$filename = $_GET['filename'];
		if (!in_array($filename,$this->filenames))
		{
			throw new egw_exception_wrong_parameter("Not allowed to download '$filename'!");
		}
		html::content_header(basename($filename),'text/plain');
		if ($filename[0] != '/') $filename = $GLOBALS['egw_info']['server']['files_dir'].'/'.$filename;
		for($n=ob_get_level(); $n > 0; --$n) ob_end_clean();	// stop all output buffering, to NOT run into memory_limit
		readfile($filename);
		common::egw_exit();
	}
}

// some testcode, if this file is called via it's URL (you need to uncomment and adapt filename!)
/*if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'admin',
			'nonavbar' => true,
		),
	);
	include_once '../../header.inc.php';

	$error_log = new egw_tail('/opt/local/apache2/logs/error_log');
	echo $error_log->show();
}*/
