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

			if (!in_array($filename,$this->filenames)) $this->filenames[] = $filename;
		}
	}

	/**
	 * Ajax callback to load next chunk of log-file
	 *
	 * @param string $filename
	 * @param int $start=0 last position in log-file
	 */
	public function ajax_chunk($filename,$start=0)
	{
		if (!in_array($filename,$this->filenames))
		{
			throw new egw_exception_wrong_parameter("Not allowed to view '$filename'!");
		}
		if ($filename[0] != '/') $filename = $GLOBALS['egw_info']['server']['files_dir'].'/'.$filename;

		if (!$start || $start < 0)
		{
			$start = filesize($filename) - 4*self::MAX_CHUNK_SIZE;
			if ($start < 0) $start = 0;
		}
		$content = file_get_contents($filename, false, null, $start, self::MAX_CHUNK_SIZE);
		$length = bytes($content);

		$response = egw_json_response::get();
		$response->data(array(	// send all responses as data
			'next' => $start + $length,
			'length' => $length,
			'content' => $content,
		));
	}

	/**
	 * Return html & javascript for logviewer
	 *
	 * @param string $id='log'
	 * @return string
	 */
	public function show($id='log')
	{
		if (!isset($this->filename))
		{
			throw new egw_exception_wrong_parameter("Must be instanciated with filename!");
		}
		return '
<script type="text/javascript">
var '.$id.'_tail_start = 0;
function refresh_'.$id.'()
{
	var ajax = new egw_json_request("home.egw_tail.ajax_chunk",["'.$this->filename.'",'.$id.'_tail_start]);
	ajax.sendRequest(true,function(_data) {
		if (_data.length) {
			'.$id.'_tail_start = _data.next;
			var log = $j("#'.$id.'").append(_data.content.replace(/</g,"&lt;"));
			log.animate({ scrollTop: log.attr("scrollHeight") - log.height() + 20 }, 500);
		}
		window.setTimeout(refresh_'.$id.',_data.length?200:2000);
	});
}
$j(document).ready(function()
{
	var log = $j("#'.$id.'");
	log.width(log.width());
	refresh_'.$id.'();
});
</script>
<pre class="tail" id="'.$id.'" style="width: 100%; border: 2px groove silver; height: 480px; overflow: auto;"></pre>';
	}
}

// some testcode, if this file is called via it's URL
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'admin',
		),
	);
	include_once '../../header.inc.php';

	$error_log = new egw_tail($file='/opt/local/apache2/logs/error_log');
	echo "<h3>$file</h3>\n";
	echo $error_log->show();
}