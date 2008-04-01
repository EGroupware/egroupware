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
 * @copyright 2001-2008 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id$
 */

/**
 * Generalized linking between entries of eGroupware apps
 * 
 * Please note: this class can NOT and does not need to be initialised, all methods are static
 * 
 * To participate in the linking an applications has to implement the following hooks:
 * 
 * 	/**
 *	 * Hook called by link-class to include app in the appregistry of the linkage
 *	 *
 *	 * @param array/string $location location and other parameters (not used)
 *	 * @return array with method-names
 *	 *%
 *	function search_link($location)
 *	{
 *		return array(
 *			'query' => 'app.class.link_query',		// method to search app for a pattern: array link_query(string $pattern)
 *			'title' => 'app.class.link_title',		// method to return title of an entry of app: string/false/null link_title(int/string $id)
 *			'titles' => 'app.class.link_titles',	// method to return multiple titles: array link_title(array $ids)
 *			'view'  => array(						// get parameters to view an entry of app
 *				'menuaction' => 'app.class.method',
 *			),
 *			'view_id' => 'app_id',					// name of get parameter of the id
 *          'view_popup' => '400x300',				// size of popup (XxY), if view is in popup 
 *			'add' => array(							// get parameter to add an empty entry to app
 *				'menuaction' => 'app.class.method',
 *			),
 *			'add_app'    => 'link_app',				// name of get parameter to add links to other app
 *			'add_id'     => 'link_id',				// --------------------- " ------------------- id
 *          'add_popup' => '400x300',				// size of popup (XxY), if add is in popup 
 *			'notify' => 'app.class.method',			// method to be called if an other applications liks or unlinks with app: notify(array $data)
 *		);
 *	}
 * All entries are optional, thought you only get conected functionality, if you implement them ...
 *
 * The BO-layer implementes some extra features on top of the so-layer:
 * 1) It handles links to not already existing entries. This is used by the eTemplate link-widget, which allows to
 *    setup links even for new / not already existing entries, before they get saved.
 * 	  In that case you have to set the first id to 0 for the link-static function and pass the array returned in that id 
 * 	  (not the return-value) after saveing your new entry again to the link static function.
 * 2) Attaching files: they are saved in the vfs and not the link-table (!).
 *    Attached files are stored under $vfs_basedir='/infolog' in the vfs!
 * 3) It manages the link-registry, in which apps can register themselfs by implementing some hooks
 * 4) It notifies apps, who registered for that service, about changes in the links their entries
 */
class egw_link extends solink
{
	/**
	 * Basepath for attached files
	 * 
	 * @todo change to '/apps' once the new vfs is complete
	 */
	const VFS_BASEDIR = '/infolog';
	/**
	 * appname used for returned attached files (!= 'filemanager'!)
	 */
	const VFS_APPNAME = 'file';		// pseudo-appname for own file-attachments in vfs, this is NOT the vfs-app
	/**
	 * Turns on debug-messages
	 */
	const DEBUG = false;
	/**
	 * other apps can participate in the linking by implementing a 'search_link' hook, which
	 * has to return an array in the format of an app_register entry below
	 * 
	 * @var array
	 */
	static $app_register = array(
		'felamimail' => array(
			'add' => array(
				'menuaction' => 'felamimail.uicompose.compose',
			),
			'add_popup' => '700x750',
		),
	);
	/**
	 * Instance of the vfs class
	 *
	 * @var vfs
	 */
	private static $vfs;
	private static $link_pathes = array();
	private static $send_file_ips = array();
	/**
	 * Caches link titles for a better performance
	 *
	 * @var array
	 */
	private static $title_cache = array();

	/**
	 * Private constructor to forbid instanciated use
	 *
	 */
	private function __construct()
	{
		
	}

	/**
	 * initialize our static vars
	 */
	static function init_static( )
	{
		self::$vfs = new vfs();

		self::$link_pathes   =& $GLOBALS['egw_info']['server']['link_pathes'];
		self::$send_file_ips =& $GLOBALS['egw_info']['server']['send_file_ips'];
		
		// other apps can participate in the linking by implementing a search_link hook, which
		// has to return an array in the format of an app_register entry
		// for performance reasons, we do it only once / cache it in the session
		if (!($search_link_hooks = $GLOBALS['egw']->session->appsession('search_link_hooks','phpgwapi')))
		{
			$search_link_hooks = $GLOBALS['egw']->hooks->process('search_link');
			$GLOBALS['egw']->session->appsession('search_link_hooks','phpgwapi',$search_link_hooks);
		}
		if (is_array($search_link_hooks))
		{
			foreach($search_link_hooks as $app => $data)
			{
				if (is_array($data))
				{
					self::$app_register[$app] = $data;
				}
			}
		}
		if (!(self::$title_cache = $GLOBALS['egw']->session->appsession('link_title_cache','phpgwapi')))
		{
			self::$title_cache = array();
		}
	}
	
	/**
	 * Called by egw::egw_final to store the title-cache in the session
	 *
	 */
	static function save_session_cache()
	{
		$GLOBALS['egw']->session->appsession('link_title_cache','phpgwapi',self::$title_cache);
	}
	
	/**
	 * creats a link between $app1,$id1 and $app2,$id2 - $id1 does NOT need to exist yet
	 *
	 * Does NOT check if link already exists.
	 * File-attachments return a negative link-id !!!
	 *
	 * @param string $app1 app of $id1
	 * @param string/array &$id1 id of item to linkto or 0 if item not yet created or array with links 
	 * 	of not created item or $file-array if $app1 == self::VFS_APPNAME (see below).
	 * 	If $id==0 it will be set on return to an array with the links for the new item.
	 * @param string/array $app2 app of 2.linkend or array with links ($id2 not used)
	 * @param string $id2='' id of 2. item of $file-array if $app2 == self::VFS_APPNAME (see below)<br>
	 * 	$file array with informations about the file in format of the etemplate file-type<br>
	 * 	$file['name'] name of the file (no directory)<br>
	 * 	$file['type'] mine-type of the file<br>
	 * 	$file['tmp_name'] name of the uploaded file (incl. directory)<br>
	 * 	$file['path'] path of the file on the client computer<br>
	 * 	$file['ip'] of the client (path and ip in $file are only needed if u want a symlink (if possible))
	 * @param string $remark='' Remark to be saved with the link (defaults to '')
	 * @param int $owner=0 Owner of the link (defaults to user)
	 * @param int $lastmod=0 timestamp of last modification (defaults to now=time())
	 * @param int $no_notify=0 &1 dont notify $app1, &2 dont notify $app2
	 * @return int/boolean False (for db or param-error) or on success link_id (Please not the return-value of $id1)
	 */
	static function link( $app1,&$id1,$app2,$id2='',$remark='',$owner=0,$lastmod=0,$no_notify=0 )
	{
		if (self::DEBUG)
		{
			echo "<p>egw_link::link('$app1',$id1,'".print_r($app2,true)."',".print_r($id2,true).",'$remark',$owner,$lastmod)</p>\n";
		}
		if (!$app1 || !$app2 || $app1 == $app2 && $id1 == $id2)
		{
			return False;
		}
		if (is_array($id1) || !$id1)		// create link only in $id1 array
		{
			if (!is_array($id1))
			{
				$id1 = array( );
			}
			$link_id = self::temp_link_id($app2,$id2);

			$id1[$link_id] = array(
				'app' => $app2,
				'id'  => $id2,
				'remark' => $remark,
				'owner'  => $owner,
				'link_id' => $link_id,
				'lastmod' => time()
			);
			if (self::DEBUG)
			{
				_debug_array($id1);
			}
			return $link_id;
		}
		if (is_array($app2) && !$id2)
		{
			reset($app2);
			$link_id = True;
			while ($link_id && list(,$link) = each($app2))
			{
				if (!is_array($link))	// check for unlink-marker
				{
					//echo "<b>link='$link' is no array</b><br>\n";
					continue;
				}
				if ($link['app'] == self::VFS_APPNAME)
				{
					$link_id = self::attach_file($app1,$id1,$link['id'],$link['remark']);
				}
				else
				{
					$link_id = solink::link($app1,$id1,$link['app'],$link['id'],
						$link['remark'],$link['owner'],$link['lastmod']);
					
					// notify both sides
					if (!($no_notify&2)) self::notify('link',$link['app'],$link['id'],$app1,$id1,$link_id);
					if (!($no_notify&1)) self::notify('link',$app1,$id1,$link['app'],$link['id'],$link_id);
				}
			}
			return $link_id;
		}
		if ($app1 == self::VFS_APPNAME)
		{
			return self::attach_file($app2,$id2,$id1,$remark);
		}
		elseif ($app2 == self::VFS_APPNAME)
		{
			return self::attach_file($app1,$id1,$id2,$remark);
		}
		$link_id = solink::link($app1,$id1,$app2,$id2,$remark,$owner);

		if (!($no_notify&2)) self::notify('link',$app2,$id2,$app1,$id1,$link_id);
		if (!($no_notify&1)) self::notify('link',$app1,$id1,$app2,$id2,$link_id);
		
		return $link_id;
	}

	/**
	 * generate temporary link_id used as array-key
	 *
	 * @param string $app app-name
	 * @param mixed $id
	 * @return string
	 */
	static function temp_link_id($app,$id)
	{
		return $app.':'.($app != self::VFS_APPNAME ? $id : $id['name']);
	}

	/**
	 * returns array of links to $app,$id (reimplemented to deal with not yet created items)
	 *
	 * @param string $app appname
	 * @param string/array $id id of entry in $app or array of links if entry not yet created
	 * @param string $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
	 * @param string $order='link_lastmod DESC' defaults to newest links first
	 * @return array of links or empty array if no matching links found
	 */
	static function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
	{
		if (self::DEBUG) echo "<p>egw_link::get_links(app='$app',id='$id',only_app='$only_app',order='$order')</p>\n";

		if (is_array($id) || !$id)
		{
			$ids = array();
			if (is_array($id))
			{
				if (($not_only = $only_app[0] == '!'))
				{
					$only_app = substr(1,$only_app);
				}
				foreach (array_reverse($id) as $link) 
				{
					if (is_array($link)  // check for unlink-marker
						&&  !($only_app && $not_only == ($link['app'] == $only_app)))
					{
						$ids[$link['link_id']] = $only_app ? $link['id'] : $link;
					}
				}
			}
			return $ids;
		}
		$ids = solink::get_links($app,$id,$only_app,$order);
		if (empty($only_app) || $only_app == self::VFS_APPNAME ||
		    ($only_app[0] == '!' && $only_app != '!'.self::VFS_APPNAME))
		{
			if ($vfs_ids = self::list_attached($app,$id))
			{
				$ids += $vfs_ids;
			}
		}
		//echo "ids=<pre>"; print_r($ids); echo "</pre>\n";

		return $ids;
	}
	
	/**
	 * Query the links of multiple entries of one application
	 *
	 * @ToDo also query the attachments in a single query, eg. via a directory listing of /apps/$app
	 * @param string $app
	 * @param array $ids
	 * @param boolean $cache_titles=true should all titles be queryed and cached (allows to query each link app only once!)
	 * @param string $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
	 * @param string $order='link_lastmod DESC' defaults to newest links first
	 * @return array of $id => array($links) pairs
	 */
	static function get_links_multiple($app,array $ids,$cache_titles=true,$only_app='',$order='link_lastmod DESC' )
	{
		if (self::DEBUG) echo "<p>".__METHOD__."('$app',".print_r($ids,true).",$cache_titles,'$only_app','$order')</p>\n";

		if (!$ids)
		{
			return array();		// no ids are linked to nothing
		}
		$links = solink::get_links($app,$ids,$only_app,$order);

		if (empty($only_app) || $only_app == self::VFS_APPNAME ||
		    ($only_app[0] == '!' && $only_app != '!'.self::VFS_APPNAME))
		{
			// todo do that in a single query, eg. directory listing, too
			foreach($ids as $id)
			{
				if (!isset($links[$id]))
				{
					$links[$id] = array();
				}
				if ($vfs_ids = self::list_attached($app,$id))
				{
					$links[$id] += $vfs_ids;
				}
			}
		}
		if ($cache_titles)
		{
			// agregate links by app
			$app_ids = array();
			foreach($links as $src_id => &$targets)
			{
				foreach($targets as $link)
				{
					$app_ids[$link['app']][] = $link['id'];
				}
			}
			foreach($app_ids as $app => $a_ids)
			{
				self::titles($app,array_unique($a_ids));
			}
		}
		return $links;
	}

	/**
	 * Read one link specified by it's link_id or by the two end-points
	 *
	 * If $id is an array (links not yet created) only link_ids are allowed.
	 *
	 * @param int/string $app_link_id > 0 link_id of link or app-name of link
	 * @param string/array $id='' id if $app_link_id is an appname or array with links, if 1. entry not yet created
	 * @param string $app2='' second app
	 * @param string $id2='' id in $app2
	 * @return array with link-data or False
	 */ 
	static function get_link($app_link_id,$id='',$app2='',$id2='')
	{
		if (self::DEBUG)
		{
			echo '<p>'.__METHOD__."($app_link_id,$id,$app2,$id2)</p>\n"; echo function_backtrace(); 
		}
		if (is_array($id))
		{
			if (strpos($app_link_id,':') === false) $app_link_id = self::temp_link_id($app2,$id2);	// create link_id of temporary link, if not given
			
			if (isset($id[$app_link_id]) && is_array($id[$app_link_id]))	// check for unlinked-marker
			{
				return $id[$app_link_id];
			}
			return False;
		}
		if ((int)$app_link_id < 0 || $app_link_id == self::VFS_APPNAME || $app2 == self::VFS_APPNAME)
		{
			if ((int)$app_link_id < 0)	// vfs link_id ?
			{
				return self::fileinfo2link(-$app_link_id);
			}
			if ($app_link_id == self::VFS_APPNAME)
			{
				return self::info_attached($app2,$id2,$id);
			}
			return self::info_attached($app_link_id,$id,$id2);
		}
		return solink::get_link($app_link_id,$id,$app2,$id2);
	}

	/**
	 * Remove link with $link_id or all links matching given $app,$id
	 *
	 * Note: if $link_id != '' and $id is an array: unlink removes links from that array only
	 * 	unlink has to be called with &$id to see the result (depricated) or unlink2 has to be used !!!
	 *
	 * @param $link_id link-id to remove if > 0
	 * @param string $app='' appname of first endpoint
	 * @param string/array $id='' id in $app or array with links, if 1. entry not yet created
	 * @param string $app2='' app of second endpoint
	 * @param string $id2='' id in $app2
	 * @return the number of links deleted
	 */
	static function unlink($link_id,$app='',$id='',$owner='',$app2='',$id2='')
	{
		return self::unlink2($link_id,$app,$id,$owner,$app2,$id2);
	}

	/**
	 * Remove link with $link_id or all links matching given $app,$id
	 *
	 * @param $link_id link-id to remove if > 0
	 * @param string $app='' appname of first endpoint
	 * @param string/array &$id='' id in $app or array with links, if 1. entry not yet created
	 * @param string $app2='' app of second endpoint, or !file (other !app are not yet supported!)
	 * @param string $id2='' id in $app2
	 * @return the number of links deleted
	 */
	static function unlink2($link_id,$app,&$id,$owner='',$app2='',$id2='')
	{
		if (self::DEBUG)
		{
			echo "<p>egw_link::unlink('$link_id','$app','$id','$owner','$app2','$id2')</p>\n";
		}
		if ($link_id < 0)	// vfs-link?
		{
			return self::delete_attached(-$link_id);
		}
		elseif ($app == self::VFS_APPNAME)
		{
			return self::delete_attached($app2,$id2,$id);
		}
		elseif ($app2 == self::VFS_APPNAME)
		{
			return self::delete_attached($app,$id,$id2);
		}
		if (!is_array($id))
		{
			if (!$link_id && !$app2 && !$id2 && $app2 != '!'.self::VFS_APPNAME)
			{
				self::delete_attached($app,$id);	// deleting all attachments
				unset(self::$title_cache[$app.':'.$id]);
			}
			$deleted =& solink::unlink($link_id,$app,$id,$owner,$app2 != '!'.self::VFS_APPNAME ? $app2 : '',$id2);
			
			// only notify on real links, not the one cached for writing or fileattachments
			self::notify_unlink($deleted);

			return count($deleted);
		}
		if (!$link_id) $link_id = self::temp_link_id($app2,$id2);	// create link_id of temporary link, if not given

		if (isset($id[$link_id]))
		{
			$id[$link_id] = False;	// set the unlink marker

			if (self::DEBUG)
			{
				_debug_array($id);
			}
			return True;
		}
		return False;
	}

	/**
	 * get list/array of link-aware apps the user has rights to use
	 *
	 * @param string $must_support capability the apps need to support, eg. 'add', default ''=list all apps
	 * @return array with app => title pairs
	 */
	static function app_list($must_support='')
	{
		$apps = array();
		foreach(self::$app_register as $app => $reg)
		{
			if ($must_support && !isset($reg[$must_support])) continue;

			if ($GLOBALS['egw_info']['user']['apps'][$app])
			{
				$apps[$app] = $GLOBALS['egw_info']['apps'][$app]['title'];
			}
		}
		return $apps;
	}

	/**
	 * Searches for a $pattern in the entries of $app
	 *
	 * @param string $app app to search
	 * @param string $pattern pattern to search
	 * @return array with $id => $title pairs of matching entries of app
	 */
	static function query($app,$pattern)
	{
		if ($app == '' || !is_array($reg = self::$app_register[$app]) || !isset($reg['query']))
		{
			return array();
		}
		$method = $reg['query'];

		if (self::DEBUG)
		{
			echo "<p>egw_link::query('$app','$pattern') => '$method'</p>\n";
		}
		return ExecMethod($method,$pattern);
	}

	/**
	 * returns the title (short description) of entry $id and $app
	 *
	 * @param string $app appname
	 * @param string $id id in $app 
	 * @param array $link=null link-data for file-attachments
	 * @return string/boolean string with title, null if $id does not exist in $app or false if no perms to view it
	 */
	static function title($app,$id,$link=null)
	{
		if (!$id) return '';
		
		if (isset(self::$title_cache[$app.':'.$id]))
		{
			if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id')='".self::$title_cache[$app.':'.$id]."' (from cache)</p>\n";
			return self::$title_cache[$app.':'.$id];
		}
		if ($app == self::VFS_APPNAME)
		{
			if (is_array($id) && $link)
			{
				$link = $id;
				$id = $link['name'];
			}
			if (is_array($link))
			{
				$size = $link['size'];
				if ($size_k = (int)($size / 1024))
				{
					if ((int)($size_k / 1024))
					{
						$size = sprintf('%3.1dM',doubleval($size_k)/1024.0);
					}
					else
					{
						$size = $size_k.'k';
					}
				}
				$extra = ': '.$link['type'] . ' '.$size;
			}
			return self::$title_cache[$app.':'.$id] = $id.$extra;
		}
		if ($app == '' || !is_array($reg = self::$app_register[$app]) || !isset($reg['title']))
		{
			if (self::DEBUG) echo "<p>".__METHOD__."('$app','$id') something is wrong!!!</p>\n";
			return array();
		}
		$method = $reg['title'];

		$title = ExecMethod($method,$id);

		if ($id && is_null($title))	// $app,$id has been deleted ==> unlink all links to it
		{
			self::unlink(0,$app,$id);
			if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id') unlinked, as $method returned null</p>\n";
			return False;
		}
		if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id')='$title' (from $method)</p>\n";

		return self::$title_cache[$app.':'.$id] = $title;
	}
	
	/**
	 * Query the titles off multiple id's of one app
	 * 
	 * Apps can implement that hook, if they have a quicker (eg. less DB queries) method to query the title of multiple entries.
	 * If it's not implemented, we call the regular title method multiple times.
	 *
	 * @param string $app
	 * @param array $ids
	 */
	static function titles($app,array $ids)
	{
		if (self::DEBUG)
		{
			echo "<p>".__METHOD__."($app,".implode(',',$ids).")</p>\n";
		}
		$titles = $ids_to_query = array();
		foreach($ids as $id)
		{
			if (!isset(self::$title_cache[$app.':'.$id]))
			{
				if (isset(self::$app_register[$app]['titles']))
				{
					$ids_to_query[] = $id;	// titles method --> collect links to query at once
				}
				else
				{
					self::title($app,$id);	// no titles method --> fallback to query each link separate
				}
			}
			$titles[$id] = self::$title_cache[$app.':'.$id];
		}
		if ($ids_to_query)
		{
			foreach(ExecMethod(self::$app_register[$app]['titles'],$ids_to_query) as $id => $title)
			{
				$titles[$id] = self::$title_cache[$app.':'.$id] = $title;
			}
		}
		return $titles;
	}

	/**
	 * Add new entry to $app, evtl. already linked to $to_app, $to_id
	 *
	 * @param string $app appname of entry to create
	 * @param string $to_app appname to link the new entry to
	 * @param string $to_id id in $to_app 
	 * @return array/boolean with name-value pairs for link to add-methode of $app or false if add not supported
	 */
	static function add($app,$to_app='',$to_id='')
	{
		//echo "<p>egw_link::add('$app','$to_app','$to_id') app_register[$app] ="; _debug_array($app_register[$app]);
		if ($app == '' || !is_array($reg = self::$app_register[$app]) || !isset($reg['add']))
		{
			return false;
		}
		$params = $reg['add'];
		
		if ($reg['add_app'] && $to_app && $reg['add_id'] && $to_id)
		{
			$params[$reg['add_app']] = $to_app;
			$params[$reg['add_id']] = $to_id;
		}
		return $params;
	}

	/**
	 * view entry $id of $app
	 *
	 * @ToDo use webdav url of new vfs, once ACL for /apps is ready
	 * @param string $app appname
	 * @param string $id id in $app 
	 * @param array $link=null link-data for file-attachments
	 * @return array with name-value pairs for link to view-methode of $app to view $id
	 */
	static function view($app,$id,$link=null)
	{
		if ($app == self::VFS_APPNAME && !empty($id) && is_array($link))
		{
			return array(
				'menuaction' => 'phpgwapi.bolink.get_file',
				'app' => $link['app2'],
				'id'  => $link['id2'],
				'filename' => $link['id']
			);
		}
		if ($app == '' || !is_array($reg = self::$app_register[$app]) || !isset($reg['view']) || !isset($reg['view_id']))
		{
			return array();
		}
		$view = $reg['view'];

		$names = explode(':',$reg['view_id']);
		if (count($names) > 1)
		{
			$id = explode(':',$id);
			while (list($n,$name) = each($names))
			{
				$view[$name] = $id[$n];
			}
		}
		else
		{
			$view[$reg['view_id']] = $id;
		}
		return $view;
	}

	/**
	 * Check if $app uses a popup for $action
	 *
	 * @param string $app app-name
	 * @param string $action='view' name of the action, atm. 'view' or 'add'
	 * @return boolean/string false if no popup is used or $app is not registered, otherwise string with the prefered popup size (eg. '640x400)
	 */
	static function is_popup($app,$action='view')
	{
		if (!($reg = self::$app_register[$app]) || !$reg[$action.'_popup'])
		{
			return false;
		}
		return $reg[$action.'_popup'];
	}	

	/**
	 * path to the attached files of $app/$ip or the directory for $app if no $id,$file given
	 *
	 * All link-files are based in the vfs-subdir '/infolog'. For other apps
	 * separate subdirs with name app are created.
	 *
	 * @ToDo change to new VFS_BASEDIR /apps (incl. /apps/infolog)
	 * @param string $app appname
	 * @param string $id='' id in $app 
	 * @param string $file='' filename
	 * @param boolean/array $relatives=False return path as array with path in string incl. relatives
	 * @return string/array path or array with path and relatives, depending on $relatives
	 */
	static function vfs_path($app,$id='',$file='',$relatives=False)
	{
		$path = self::VFS_BASEDIR . ($app == '' || $app == 'infolog' ? '' : '/'.$app) .
			($id != '' ? '/' . $id : '') . ($file != '' ? '/' . $file : '');
		
		if (self::DEBUG)
		{
			echo "<p>egw_link::vfs_path('$app','$id','$file') = '$path'</p>\n";
		}
		return $relatives ? array(
			'string' => $path,
			'relatives' => is_array($relatives) ? $relatives : array($relatives)
		) : $path;
	}

	/**
	 * Put a file to the corrosponding place in the VFS and set the attributes
	 *
	 * @param string $app appname to linke the file to
	 * @param string $id id in $app 
	 * @param array $file informations about the file in format of the etemplate file-type
	 * 	$file['name'] name of the file (no directory)
	 * 	$file['type'] mine-type of the file
	 * 	$file['tmp_name'] name of the uploaded file (incl. directory)
	 * 	$file['path'] path of the file on the client computer
	 * 	$file['ip'] of the client (path and ip are only needed if u want a symlink (if possible))
	 * @param string $comment='' comment to add to the link
	 * @return int negative id of phpgw_vfs table as negative link-id's are for vfs attachments
	 */
	static function attach_file($app,$id,$file,$comment='')
	{
		if (self::DEBUG)
		{
			echo "<p>attach_file: app='$app', id='$id', tmp_name='$file[tmp_name]', name='$file[name]', size='$file[size]', type='$file[type]', path='$file[path]', ip='$file[ip]', comment='$comment'</p>\n";
		}
		// create the root for attached files in infolog, if it does not exists
		$vfs_data = array('string'=>self::VFS_BASEDIR,'relatives'=>array(RELATIVE_ROOT));
		if (!(self::$vfs->file_exists($vfs_data)))
		{
			self::$vfs->override_acl = 1;
			self::$vfs->mkdir($vfs_data);
			self::$vfs->override_acl = 0;
		}

		$vfs_data = self::vfs_path($app,False,False,RELATIVE_ROOT);
		if (!(self::$vfs->file_exists($vfs_data)))
		{
			self::$vfs->override_acl = 1;
			self::$vfs->mkdir($vfs_data);
			self::$vfs->override_acl = 0;
		}
		$vfs_data = self::vfs_path($app,$id,False,RELATIVE_ROOT);
		if (!(self::$vfs->file_exists($vfs_data)))
		{
			self::$vfs->override_acl = 1;
			self::$vfs->mkdir($vfs_data);
			self::$vfs->override_acl = 0;
		}
		$fname = self::vfs_path($app,$id,$file['name']);
		$tfname = '';
		if (!empty($file['path']) && is_array(self::$link_pathes) && count(self::$link_pathes))
		{
			$file['path'] = str_replace('\\\\','/',$file['path']);	// vfs uses only '/'
			@reset(self::$link_pathes);
			while ((list($valid,$trans) = @each(self::$link_pathes)) && !$tfname)
			{  // check case-insensitive for WIN etc.
				$check = $valid[0] == '\\' || strpos(':',$valid) !== false ? 'eregi' : 'ereg';
				$valid2 = str_replace('\\','/',$valid);
				//echo "<p>attach_file: ereg('".self::$send_file_ips[$valid]."', '$file[ip]')=".ereg(self::$send_file_ips[$valid],$file['ip'])."</p>\n";
				if ($check('^('.$valid2.')(.*)$',$file['path'],$parts) &&
				    ereg(self::$send_file_ips[$valid],$file['ip']) &&     // right IP
				    self::$vfs->file_exists(array('string'=>$trans.$parts[2],'relatives'=>array(RELATIVE_NONE|VFS_REAL))))
				{
					$tfname = $trans.$parts[2];
				}
				//echo "<p>attach_file: full_fname='$file[path]', valid2='$valid2', trans='$trans', check=$check, tfname='$tfname', parts=(x,'${parts[1]}','${parts[2]}')</p>\n";
			}
			if ($tfname && !self::$vfs->securitycheck(array('string'=>$tfname)))
			{
				return False; //lang('Invalid filename').': '.$tfname;
			}
		}
		self::$vfs->override_acl = 1;
		self::$vfs->cp(array(
			'symlink' => !!$tfname,		// try a symlink
			'from' => $tfname ? $tfname : $file['tmp_name'],
			'to'   => $fname,
			'relatives' => array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT),
		));
		self::$vfs->set_attributes(array(
			'string' => $fname,
			'relatives' => array (RELATIVE_ROOT),
			'attributes' => array (
				'mime_type' => $file['type'],
				'comment' => stripslashes ($comment),
				'app' => $app
		)));
		self::$vfs->override_acl = 0;

		$link = self::info_attached($app,$id,$file['name']);

		return is_array($link) ? $link['link_id'] : False;
	}

	/**
	 * deletes an attached file
	 *
	 * @param int/string $app > 0: file_id of an attchemnt or $app/$id entry which linked to
	 * @param string $id='' id in app
	 * @param string $fname filename
	 */
	static function delete_attached($app,$id='',$fname = '')
	{
		if ((int)$app > 0)	// is file_id
		{
			$link  = self::fileinfo2link($file_id=$app);
			$app   = $link['app2'];
			$id    = $link['id2'];
			$fname = $link['id'];
		}
		if (self::DEBUG)
		{
			echo "<p>egw_link::delete_attached('$app','$id','$fname') file_id=$file_id</p>\n";
		}
		if (empty($app) || empty($id))
		{
			return False;	// dont delete more than all attachments of an entry
		}
		$vfs_data = self::vfs_path($app,$id,$fname,RELATIVE_ROOT);
		
		$Ok = false;
		if (self::$vfs->file_exists($vfs_data))
		{
			self::$vfs->override_acl = 1;
			$Ok = self::$vfs->delete($vfs_data);
			self::$vfs->override_acl = 0;
		}
		// if filename given (and now deleted) check if dir is empty and remove it in that case
		if ($fname && !count(self::$vfs->ls($vfs_data=self::vfs_path($app,$id,'',RELATIVE_ROOT))))
		{
			self::$vfs->override_acl = 1;
			self::$vfs->delete($vfs_data);
			self::$vfs->override_acl = 0;
		}
		return $Ok;
	}

	/**
	 * converts the infos vfs has about a file into a link
	 *
	 * @param string $app appname
	 * @param string $id id in app
	 * @param string $filename filename
	 * @return array 'kind' of link-array
	 */
	static function info_attached($app,$id,$filename)
	{
		self::$vfs->override_acl = 1;
		$attachments = self::$vfs->ls(self::vfs_path($app,$id,$filename,RELATIVE_NONE));
		self::$vfs->override_acl = 0;

		if (!count($attachments) || !$attachments[0]['name'])
		{
			return False;
		}
		return self::fileinfo2link($attachments[0]);
	}

	/**
	 * converts a fileinfo (row in the vfs-db-table) in a link
	 *
	 * @param array/int $fileinfo a row from the vfs-db-table (eg. returned by the vfs ls static function) or a file_id of that table
	 * @return array a 'kind' of link-array
	 */
	static function fileinfo2link($fileinfo)
	{
		if (!is_array($fileinfo))
		{
			$fileinfo = self::$vfs->ls(array('file_id' => $fileinfo));
			list(,$fileinfo) = each($fileinfo);

			if (!is_array($fileinfo))
			{
				return False;
			}
		}
		$lastmod = $fileinfo[!empty($fileinfo['modified']) ? 'modified' : 'created'];
		list($y,$m,$d) = explode('-',$lastmod);
		$lastmod = mktime(0,0,0,$m,$d,$y);

		$dir_parts = array_reverse(explode('/',$fileinfo['directory']));

		return array(
			'app'       => self::VFS_APPNAME,
			'id'        => $fileinfo['name'],
			'app2'      => $dir_parts[1],
			'id2'       => $dir_parts[0],
			'remark'    => $fileinfo['comment'],
			'owner'     => $fileinfo['owner_id'],
			'link_id'   => -$fileinfo['file_id'],
			'lastmod'   => $lastmod,
			'size'      => $fileinfo['size'],
			'type'      => $fileinfo['mime_type']
		);
	}

	/**
	 * lists all attachments to $app/$id
	 *
	 * @param string $app appname
	 * @param string $id id in app
	 * @return array with link_id => 'kind' of link-array pairs
	 */
	static function list_attached($app,$id)
	{
		self::$vfs->override_acl = 1;
		$attachments = self::$vfs->ls(self::vfs_path($app,$id,False,RELATIVE_ROOT));
		self::$vfs->override_acl = 0;

		if (!count($attachments) || !$attachments[0]['name'])
		{
			return False;
		}
		foreach($attachments as $fileinfo)
		{
			$link = self::fileinfo2link($fileinfo);
			$attached[$link['link_id']] = $link;
		}
		return $attached;
	}

	/**
	 * checks if path starts with a '\\' or has a ':' in it
	 *
	 * @param string $path path to check
	 * @return boolean true if windows path, false otherwise
	 */
	static function is_win_path($path)
	{
		return $path{0} == '\\' || $path{1} == ':';
	}

	/**
	 * reads the attached file and returns the content
	 *
	 * @param string $app appname
	 * @param string $id id in app
	 * @param string $filename filename
	 * @return string/boolean content of the attached file, null if $id not found, false if no view perms
	 */
	static function read_attached($app,$id,$filename)
	{
		$ret = null;
		if (empty($app) || !$id || empty($filename) || !($ret = self::title($app,$id)))
		{
			return $ret;
		}
		self::$vfs->override_acl = 1;
		$data = self::$vfs->read(self::vfs_path($app,$id,$filename,RELATIVE_ROOT));
		self::$vfs->override_acl = 0;

		return $data;
	}

	/**
	 * Checks if filename should be local availible and if so returns
	 *
	 * @param string $app appname
	 * @param string $id id in app
	 * @param string $filename filename
	 * @param string $id ip-address of user
	 * @param boolean $win_user true if user is on windows, otherwise false
	 * @return string 'file:/path' for HTTP-redirect else return False
	 */
	static function attached_local($app,$id,$filename,$ip,$win_user)
	{
		//echo "<p>attached_local(app=$app, id='$id', filename='$filename', ip='$ip', win_user='$win_user', count(send_file_ips)=".count(self::$send_file_ips).")</p>\n";

		if (!$id || !$filename || /* !self::check_access($info_id,EGW_ACL_READ) || */
		    !count(self::$send_file_ips))
		{
			return False;
		}
		$link = self::$vfs->ls(self::vfs_path($app,$id,$filename,RELATIVE_ROOT)+array('readlink'=>True));
		$link = @$link[0]['symlink'];

		if ($link && is_array(self::$link_pathes))
		{
			reset(self::$link_pathes); $fname = '';
			while ((list($valid,$trans) = each(self::$link_pathes)) && !$fname)
			{
				if (!self::is_win_path($valid) == !$win_user && // valid for this OS
				    $win_user &&                                 // only for IE/windows atm
				    eregi('^'.$trans.'(.*)$',$link,$parts)  &&   // right path
				    ereg(self::$send_file_ips[$valid],$ip))      // right IP
				{
					$fname = $valid . $parts[1];
					$fname = !$win_user ? str_replace('\\','/',$fname) : str_replace('/','\\',$fname);
					return 'file:'.($win_user ? '//' : '' ).$fname;
				}
				//echo "<p>attached_local: link=$link, valid=$valid, trans='$trans', fname='$fname', parts=(x,'${parts[1]}','${parts[2]}')</p>\n";
			}
		}
		return False;
	}

	/**
	 * reverse static function of htmlspecialchars()
	 *
	 * @param string $str string to decode
	 * @return string decoded string
	 */
	static private function decode_htmlspecialchars($str)
	{
		return str_replace(array('&amp;','&quot;','&lt;','&gt;'),array('&','"','<','>'),$str);
	}

	/**
	 * notify other apps about changed content in $app,$id
	 *
	 * @param string $app name of app in which the updated happend
	 * @param string $id id in $app of the updated entry
	 * @param array $data=null updated data of changed entry, as the read-method of the BO-layer would supply it
	 */
	static function notify_update($app,$id,$data=null)
	{
		foreach(self::get_links($app,$id,'!'.self::VFS_APPNAME) as $link_id => $link)
		{
			self::notify('update',$link['app'],$link['id'],$app,$id,$link_id,$data);
		}
		unset(self::$title_cache[$app.':'.$id]);
	}

	/**
	 * notify an application about a new or deleted links to own entries or updates in the content of the linked entry
	 *
	 * Please note: not all apps supply update notifications
	 *
	 * @internal 
	 * @param string $type 'link' for new links, 'unlink' for unlinked entries, 'update' of content in linked entries
	 * @param string $notify_app app to notify
	 * @param string $notify_id id in $notify_app
	 * @param string $target_app name of app whos entry changed, linked or deleted
	 * @param string $target_id id in $target_app
	 * @param array $data=null data of entry in app2 (optional)
	 */
	static private function notify($type,$notify_app,$notify_id,$target_app,$target_id,$link_id,$data=null)
	{
		if ($link_id && isset(self::$app_register[$notify_app]) && isset(self::$app_register[$notify_app]['notify']))
		{
			ExecMethod(self::$app_register[$notify_app]['notify'],array(
				'type'       => $type,
				'id'         => $notify_id,
				'target_app' => $target_app,
				'target_id'  => $target_id,
				'link_id'    => $link_id,
				'data'       => $data,
			));
		}
	}

	/**
	 * notifies about unlinked links
	 *
	 * @internal 
	 * @param array &$links unlinked links from the database
	 */
	static private function notify_unlink(&$links)
	{
		foreach($links as $link)
		{
			// we notify both sides of the link, as the unlink command NOT clearly knows which side initiated the unlink
			self::notify('unlink',$link['link_app1'],$link['link_id1'],$link['link_app2'],$link['link_id2'],$link['link_id']);
			self::notify('unlink',$link['link_app2'],$link['link_id2'],$link['link_app1'],$link['link_id1'],$link['link_id']);
		}	
	}
}
egw_link::init_static();