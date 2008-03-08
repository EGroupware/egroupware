<?php
/**
 * API - Interapplicaton links BO layer
 *
 * Links have two ends each pointing to an entry, each entry is a double:
 * 	 - app   app-name or directory-name of an egw application, eg. 'infolog'
 * 	 - id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id$
 */

/**
 * Generalized linking between entries of eGroupware apps - BO layer
 * 
 * @deprecated use egw_link class with it's static methods instead
 */
class bolink extends egw_link
{
	var $public_functions = array(
		'get_file' => true,
	);
	/**
	 * @deprecated use egw_link::VFS_APPNAME
	 */
	var $vfs_appname = egw_link::VFS_APPNAME;
	/**
	 * @deprecated use solink::TABLE
	 */
	var $link_table = solink::TABLE;
	
	/**
	 * Overwrite private constructor of egw_links, to allow (depricated) instancated usage
	 *
	 */
	function __construct()
	{
		
	}

	/**
	 * Download an attached file
	 *
	 * @todo replace it with egw_vfs::download_url, once egw_vfs/webdav supports the attachments
	 * @param array $link=null
	 * @return array with params (eg. menuaction) for download link
	 */
	function get_file(array $link=null)
	{
		if (is_array($link))
		{
			return array(
				'menuaction' => 'phpgwapi.bolink.get_file',
				'app' => $link['app2'],
				'id'  => $link['id2'],
				'filename' => $link['id']
			);
		}
		$app = $_GET['app'];
		$id  = $_GET['id'];
		$filename = $_GET['filename'];

		if (empty($app) || empty($id) || empty($filename) || !$this->title($app,$id))
		{
			$GLOBALS['egw']->framework->render('<h1 style="text-align: center; color: red;">'.lang('Access not permitted')." !!!</h1>\n",
				lang('Access not permitted'),true);
			$GLOBALS['egw']->common->egw_exit();
		}
		$browser = new browser();

		$local = $this->attached_local($app,$id,$filename,$_SERVER['REMOTE_ADDR'],$browser->is_windows());

		if ($local)
		{
			Header('Location: ' . $local);
		}
		else
		{
			$info = $this->info_attached($app,$id,$filename);
			$browser->content_header($filename,$info['type']);
			echo $this->read_attached($app,$id,$filename);
		}
		$GLOBALS['egw']->common->egw_exit();
	}	
}