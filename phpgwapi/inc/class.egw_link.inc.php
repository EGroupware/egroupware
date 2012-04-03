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
 * @copyright 2001-2011 by RalfBecker@outdoor-training.de
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
 *			'query' => 'app.class.link_query',		// method to search app for a pattern: array link_query(string $pattern, array $options)
 *			'title' => 'app.class.link_title',		// method to return title of an entry of app: string/false/null link_title(int/string $id)
 *			'titles' => 'app.class.link_titles',	// method to return multiple titles: array link_title(array $ids)
 *			'view'  => array(						// get parameters to view an entry of app
 *				'menuaction' => 'app.class.method',
 *			),
 *			'types' => array(				// Optional list of sub-types to filter (eg organisations), app to handle different queries
 *				'type_key' => array(
 *					'name'	=>	'Human Reference',
 *					'icon'	=>	'app/icon'	// Optional icon to use for that sub-type
 *				)
 *			),
 *			'view_id' => 'app_id',					// name of get parameter of the id
 *          'view_popup' => '400x300',				// size of popup (XxY), if view is in popup
 *			'view_list'  => 'app.class.method'		// deprecated use 'list' instead
 *          'list' => array(						// Method to be called to display a list of links, method should check $_GET['search'] to filter
 *          	'menuaction' => 'app.class.method',
 *          ),
 *          'list_popup' => '400x300'
 *			'add' => array(							// get parameter to add an empty entry to app
 *				'menuaction' => 'app.class.method',
 *			),
 *			'add_app'    => 'link_app',				// name of get parameter to add links to other app
 *			'add_id'     => 'link_id',				// --------------------- " ------------------- id
 *          'add_popup' => '400x300',				// size of popup (XxY), if add is in popup
 *			'notify' => 'app.class.method',			// method to be called if an other applications liks or unlinks with app: notify(array $data)
 * 			'file_access' => 'app.class.method',	// method to be called to check file access rights of a given user, see links_stream_wrapper class
 *													// boolean file_access(string $id,int $check,string $rel_path=null,int $user=null)
 * 			'file_access_user' => false,			// true if file_access method supports 4th parameter $user, if app is NOT supporting it
 *                                                  // egw_link::file_access() returns false for $user != current user!
 *			'find_extra'  => array('name_preg' => '/^(?!.picture.jpg)$/')	// extra options to egw_vfs::find, to eg. remove some files from the list of attachments
 *			'edit' => array(
 *				'menuaction' => 'app.class.method',
 *			),
 *			'edit_id' => 'app_id',
 *			'edit_popup' => '400x300',
 *			'name' => 'Some name',					// Name to use instead of app-name
 *			'icon' => 'app/icon',					// Optional icon to use instead of app-icon
 *          'mime' => array(						// Optional register mime-types application can open
 *          	'text/something' => array(
 *          		'mime_id' => 'path',			// one of id (path) or url is required!
 *          		'mime_url' => 'url',
 *          		'menuaction' => 'app.class.method',	// method to call
 *          		'mime_popup' => '400x300',		// optional size of popup
 *          		'mime_target' => '_self',		// optional target, default _blank
 *          		// other get-parameters to set in url
 *          	),
 *          	// further mime types supported ...
 *          ),
 *			'additional' => array(					// allow one app to register sub-types,
 *				'app-sub' => array(					// different from 'types' approach above
 *					// every value defined above
 *				)
 *			)
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
	 * appname used for returned attached files (!= 'filemanager'!)
	 */
	const VFS_APPNAME = 'file';		// pseudo-appname for own file-attachments in vfs, this is NOT the vfs-app
	/**
	 * Baseurl for the attachments in the vfs
	 */
	const VFS_BASEURL = 'vfs://default/apps';
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
		'home-accounts' => array(	// user need run-rights for home
			'name' => 'Accounts',
			'icon' => 'addressbook/accounts',
			'query' => 'accounts::link_query',
			'title' => 'common::grab_owner_name',
		),
	);
	/**
	 * Caches link titles for a better performance
	 *
	 * @var array
	 */
	private static $title_cache = array();

	/**
	 * Cache file access permissions
	 *
	 * @var array
	 */
	private static $file_access_cache = array();

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
		// other apps can participate in the linking by implementing a search_link hook, which
		// has to return an array in the format of an app_register entry
		// for performance reasons, we do it only once / cache it in the session
		if (!($search_link_hooks = $GLOBALS['egw']->session->appsession('search_link_hooks','phpgwapi')))
		{
			$search_link_hooks = $GLOBALS['egw']->hooks->process('search_link', array(), true);
			$GLOBALS['egw']->session->appsession('search_link_hooks','phpgwapi',$search_link_hooks);
		}
		if (is_array($search_link_hooks))
		{
			foreach($search_link_hooks as $app => $data)
			{
				// allow apps to register additional types
				if (isset($data['additional']))
				{
					foreach($data['additional'] as $name => $values)
					{
						$values['app'] = $app;	// store name of registring app, to be able to check access
						self::$app_register[$name] = $values;
					}
					unset($data['additional']);
				}
				// support deprecated view_list attribute instead of new index attribute
				if (isset($data['view_list']) && !isset($data['list']))
				{
					$data['list'] = array('menuaction' => $data['view_list']);
				}
				elseif(isset($data['list']) && !isset($data['view_list']))
				{
					$data['view_list'] = $data['list']['menuaction'];
				}
				if (is_array($data))
				{
					self::$app_register[$app] = $data;
				}
			}
		}
		// disable ability to link to accounts for non-admins, if account-selection is disabled
		if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			unset(self::$app_register['home-accounts']);
		}
		if (!(self::$title_cache = $GLOBALS['egw']->session->appsession('link_title_cache','phpgwapi')))
		{
			self::$title_cache = array();
		}
		if (!(self::$file_access_cache = $GLOBALS['egw']->session->appsession('link_file_access_cache','phpgwapi')))
		{
			self::$file_access_cache = array();
		}
		//error_log(__METHOD__.'() items in title-cache: '.count(self::$title_cache).' file-access-cache: '.count(self::$file_access_cache));
	}

	/**
	 * Get clientside relevant attributes from app registry in json format
	 *
	 * Only transfering relevant information cuts approx. half of the size.
	 * Also only transfering information relevant to apps user has access too.
	 * Important eg. for mime-registry, to not use calendar for opening iCal files, if user has no calendar!
	 * As app can store additonal types, we have to check the registring app $data['app'] too!
	 *
	 * @return string json encoded object with app: object pairs with attributes "(view|add|edit)(|_id|_popup)"
	 */
	public static function json_registry()
	{
		$to_json = array();
		foreach(self::$app_register as $app => $data)
		{
			if (isset($GLOBALS['egw_info']['user']['apps'][$app]) ||
				isset($data['app']) && isset($GLOBALS['egw_info']['user']['apps'][$data['app']]))
			{
				$to_json[$app] = array_intersect_key($data, array_flip(array(
					'view','view_id','view_popup',
					'add','add_app','add_id','add_popup',
					'edit','edit_id','edit_popup',
					'list','list_popup',
					'name','icon','query',
					'mime',
				)));
			}
		}
		return json_encode($to_json);
	}

	/**
	 * Called by egw::egw_final to store the title-cache in the session
	 *
	 */
	static function save_session_cache()
	{
		//error_log(__METHOD__.'() items in title-cache: '.count(self::$title_cache).' file-access-cache: '.count(self::$file_access_cache));
		$GLOBALS['egw']->session->appsession('link_title_cache','phpgwapi',self::$title_cache);
		$GLOBALS['egw']->session->appsession('link_file_access_cache','phpgwapi',self::$file_access_cache);
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
	 * @param boolean $cache_titles=false should all titles be queryed and cached (allows to query each link app only once!)
	 * 	This option also removes links not viewable by current user from the result!
	 * @param boolean $deleted Include links that have been flagged as deleted, waiting for purge.
	 * @return array of links or empty array if no matching links found
	 */
	static function get_links( $app,$id,$only_app='',$order='link_lastmod DESC',$cache_titles=false,$deleted=false )
	{
		if (self::DEBUG) echo "<p>egw_link::get_links(app='$app',id='$id',only_app='$only_app',order='$order',deleted='$deleted')</p>\n";

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
		$ids = solink::get_links($app,$id,$only_app,$order,$deleted);
		if (empty($only_app) || $only_app == self::VFS_APPNAME ||
		    ($only_app[0] == '!' && $only_app != '!'.self::VFS_APPNAME))
		{
			if ($vfs_ids = self::list_attached($app,$id))
			{
				$ids += $vfs_ids;
			}
		}
		//echo "ids=<pre>"; print_r($ids); echo "</pre>\n";
		if ($cache_titles)
		{
			// agregate links by app
			$app_ids = array();
			foreach($ids as $link)
			{
				$app_ids[$link['app']][] = $link['id'];
			}
			foreach($app_ids as $appname => $a_ids)
			{
				self::titles($appname,array_unique($a_ids));
			}
			// remove links, current user has no access, from result
			foreach($ids as $key => $link)
			{
				if (!self::title($link['app'],$link['id']))
				{
					unset($ids[$key]);
				}
			}
			reset($ids);
		}
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
	 * @param boolean $hold_for_purge Don't really delete the link, just mark it as deleted and wait for final delete
	 * @return the number of links deleted
	 */
	static function unlink($link_id,$app='',$id='',$owner='',$app2='',$id2='',$hold_for_purge=false)
	{
		return self::unlink2($link_id,$app,$id,$owner,$app2,$id2,$hold_for_purge);
	}

	/**
	 * Remove link with $link_id or all links matching given $app,$id
	 *
	 * @param $link_id link-id to remove if > 0
	 * @param string $app='' appname of first endpoint
	 * @param string/array &$id='' id in $app or array with links, if 1. entry not yet created
	 * @param string $app2='' app of second endpoint, or !file (other !app are not yet supported!)
	 * @param string $id2='' id in $app2
	 * @param boolean $hold_for_purge Don't really delete the link, just mark it as deleted and wait for final delete
	 * @return the number of links deleted
	 */
	static function unlink2($link_id,$app,&$id,$owner='',$app2='',$id2='',$hold_for_purge=false)
	{
		if (self::DEBUG)
		{
			echo "<p>egw_link::unlink('$link_id','$app',".array2string($id).",'$owner','$app2','$id2', $hold_for_purge)</p>\n";
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
				self::delete_cache($app,$id);
			}
			$deleted =& solink::unlink($link_id,$app,$id,$owner,$app2 != '!'.self::VFS_APPNAME ? $app2 : '',$id2,$hold_for_purge);

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
		foreach(self::$app_register as $type => $reg)
		{
			if ($must_support && !isset($reg[$must_support])) continue;

			list($app) = explode('-', $type);
			if ($GLOBALS['egw_info']['user']['apps'][$app])
			{
				$apps[$type] = lang(self::get_registry($type, 'name'));
			}
		}
		return $apps;
	}

	/**
	 * Searches for a $pattern in the entries of $app
	 *
	 * @param string $app app to search
	 * @param string $pattern pattern to search
	 * @param string $type Search only a certain sub-type of records (optional)
	 * @return array with $id => $title pairs of matching entries of app
	 */
	static function query($app,$pattern, &$options = array())
	{
		if ($app == '' || !is_array($reg = self::$app_register[$app]) || !isset($reg['query']))
		{
			return array();
		}
		$method = $reg['query'];

		if (self::DEBUG)
		{
			echo "<p>egw_link::query('$app','$pattern') => '$method'</p>\n";
			echo "Options: "; _debug_array($options);
		}

		// See etemplate's nextmatch widget, following was copied from there
		// allow static callbacks
		if(strpos($method,'::') !== false)
		{
			//  workaround for php < 5.3: do NOT call it static, but allow application code to specify static callbacks
			if (version_compare(PHP_VERSION,'5.3','<')) list($class,$method) = explode('::',$method);
		}
		else
		{
			list($app,$class,$method) = explode('.',$method);
		}
		if ($class)
		{
			if (!$app && !is_object($GLOBALS[$class]))
			{
				$GLOBALS[$class] = new $class();
			}
			if (is_object($GLOBALS[$class]))        // use existing instance (put there by a previous CreateObject)
			{
				$obj = $GLOBALS[$class];
			}
			else
			{
				$obj = CreateObject($app.'.'.$class);
			}
		}
		if(is_callable($method))        // php5.3+ call
		{
			$result = call_user_func($method,$pattern,$options);
		}
		elseif(is_object($obj) && method_exists($obj,$method))
		{
			$result = $obj->$method($pattern,$options);
		}
		else
		{
			// Fall back to original method
			$result = ExecMethod2($method,$pattern,$options);
		}

		if (!isset($options['total']))
		{
		       $options['total'] = count($result);
		}
		if (is_array($result) && (isset($options['start']) || (isset($options['num_rows']) && count($result) > $options['num_rows'])))
		{
			$result = array_slice($result, $options['start'], (isset($options['num_rows']) ? $options['num_rows'] : count($result)), true);
		}

		return $result;
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

		$title =& self::get_cache($app,$id);
		if (isset($title) && !empty($title) && !is_array($id))
		{
			if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id')='$title' (from cache)</p>\n";
			return $title;
		}
		if ($app == self::VFS_APPNAME)
		{
			if (is_array($id) && $link)
			{
				$link = $id;
				$title = egw_vfs::decodePath($link['name']);
			}
			else
			{
				$title = $id;
			}
			/* disabling mime-type and size in link-title of attachments, as it clutters the UI
			   and users dont need it most of the time. These details can allways be views in filemanager.
			if (is_array($link))
			{
				$title .= ': '.$link['type'] . ' '.egw_vfs::hsize($link['size']);
			}*/
			if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id')='$title' (file)</p>\n";
			return $title;
		}
		if ($app == '' || !is_array($reg = self::$app_register[$app]) || !isset($reg['title']))
		{
			if (self::DEBUG) echo "<p>".__METHOD__."('$app','$id') something is wrong!!!</p>\n";
			return false; //array(); // not sure why it should return an array on failure, as the description states boolean/string
		}
		$method = $reg['title'];

		$title = ExecMethod($method,$id);

		if ($id && is_null($title))	// $app,$id has been deleted ==> unlink all links to it
		{
			static $unlinking = array();
			// check if we are already trying to unlink the entry, to avoid an infinit recursion
			if (!isset($unlinking[$app]) || !isset($unlinking[$app][$id]))
			{
				$unlinking[$app][$id] = true;
				self::unlink(0,$app,$id);
				unset($unlinking[$app][$id]);
			}
			if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id') unlinked, as $method returned null</p>\n";
			return False;
		}
		if (self::DEBUG) echo '<p>'.__METHOD__."('$app','$id')='$title' (from $method)</p>\n";

		return $title;
	}

	/**
	 * Maximum number of titles to query from an application at once (to NOT trash mysql)
	 */
	const MAX_TITLES_QUERY = 100;

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
			$title =& self::get_cache($app,$id);
			if (!isset($title))
			{
				if (isset(self::$app_register[$app]['titles']))
				{
					$ids_to_query[] = $id;	// titles method --> collect links to query at once
				}
				else
				{
					$title = self::title($app,$id);	// no titles method --> fallback to query each link separate
				}
			}
			$titles[$id] = $title;
		}
		if ($ids_to_query)
		{
			for ($n = 0; $ids = array_slice($ids_to_query,$n*self::MAX_TITLES_QUERY,self::MAX_TITLES_QUERY); ++$n)
			{
				foreach(ExecMethod(self::$app_register[$app]['titles'],$ids) as $id => $t)
				{
					$title =& self::get_cache($app,$id);
					$titles[$id] = $title = $t;
				}
			}
		}
		return $titles;
	}

	/**
	 * Add new entry to $app, evtl. already linked to $to_app, $to_id
	 *
	 * @param string $app appname of entry to create
	 * @param string $to_app='' appname to link the new entry to
	 * @param string $to_id =''id in $to_app
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
	 * Edit entry $id of $app
	 *
	 * @param string $app appname of entry
	 * @param string $id id in $app
	 * @param string &$popup=null on return popup size eg. '600x400' or null
	 * @return array|boolean with name-value pairs for link to edit-methode of $app or false if edit not supported
	 */
	static function edit($app,$id,&$popup=null)
	{
		//echo "<p>egw_link::add('$app','$to_app','$to_id') app_register[$app] ="; _debug_array($app_register[$app]);
		if (empty($app) || empty($id) || !is_array($reg = self::$app_register[$app]) || !isset($reg['edit']))
		{
			if ($reg && isset($reg['view']))
			{
				$popup = $reg['view_popup'];
				return self::view($app,$id);	// fallback to view
			}
			return false;
		}
		$params = $reg['edit'];
		$params[$reg['edit_id']] = $id;

		$popup = $reg['edit_popup'];

		return $params;
	}

	/**
	 * view entry $id of $app
	 *
	 * @param string $app appname
	 * @param string $id id in $app
	 * @param array $link=null link-data for file-attachments
	 * @return array with name-value pairs for link to view-methode of $app to view $id
	 */
	static function view($app,$id,$link=null)
	{
		if ($app == self::VFS_APPNAME && !empty($id) && is_array($link))
		{
			//return egw_vfs::download_url(self::vfs_path($link['app2'],$link['id2'],$link['id'],true));
			return self::mime_open(self::vfs_path($link['app2'],$link['id2'],$link['id'],true), $link['type']);
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
	 * Get mime-type information from app-registry
	 *
	 * Only return information from apps the user has access too (incl. registered sub-types of that apps).
	 *
	 * @param string $type
	 * @return array with values for keys 'menuaction', 'mime_id' (path) or 'mime_url' and options 'mime_popup' and other values to pass one
	 */
	static function get_mime_info($type)
	{
		foreach(self::$app_register as $app => $registry)
		{
			if (isset($registry['mime']) &&
				(isset($GLOBALS['egw_info']['user']['apps'][$app]) ||
				isset($registry['app']) && isset($GLOBALS['egw_info']['user']['apps'][$registry['app']])))
			{
				foreach($registry['mime'] as $mime => $data)
				{
					if ($mime == $type) return $data;
				}
			}
		}
		return null;
	}

	/**
	 * Get handler (link-data) for given path and mime-type
	 *
	 * @param string $path vfs path
	 * @param string $type=null default to egw_vfs::mime_content_type($path)
	 * @param string &$popup=null on return popup size or null
	 * @return string|array string with EGw relative link, array with get-parameters for '/index.php' or null (directory and not filemanager access)
	 */
	static function mime_open($path, $type=null, &$popup=null)
	{
		if (is_null($type)) $type = egw_vfs::mime_content_type($path);

		if (($data = self::get_mime_info($type)))
		{
			if (isset($data['mime_url']))
			{
				$data[$data['mime_url']] = egw_vfs::PREFIX.$path;
				unset($data['mime_url']);
			}
			elseif (isset($data['mime_id']))
			{
				$data[$data['mime_id']] = $path;
				unset($data['mime_id']);
			}
			else
			{
				throw egw_exception_assertion_failed("Missing 'mime_id' or 'mime_url' for mime-type '$type'!");
			}
			$popup = $data['mime_popup'];
			unset($data['mime_popup']);
		}
		else
		{
			$data = egw_vfs::download_url($path);
		}
		return $data;
	}

	/**
	 * Check if $app uses a popup for $action
	 *
	 * @param string $app app-name
	 * @param string $action='view' name of the action, atm. 'view' or 'add'
	 * @param array $link=null link-data for file-attachments
	 * @return boolean|string false if no popup is used or $app is not registered, otherwise string with the prefered popup size (eg. '640x400)
	 */
	static function is_popup($app, $action='view', $link=null)
	{
		$popup = self::get_registry($app,$action.'_popup');

		// for files/attachments check mime-registry
		if ($app == self::VFS_APPNAME && is_array($link) && !empty($link['type']))
		{
			$path = self::vfs_path($link['app2'], $link['id2'], $link['id'], true);
			if (self::mime_open($path, $link['type'], $p))
			{
				$popup = $p;
			}
		}
		//error_log(__METHOD__."('$app', '$action', ".array2string($link).') returning '.array2string($popup));
		return $popup;
	}

	/**
	 * Check if $app is in the registry and has an entry for $name
	 *
	 * @param string $app app-name
	 * @param string $name name / key in the registry, eg. 'view'
	 * @return boolean|string false if $app is not registered, otherwise string with the value for $name
	 */
	static function get_registry($app,$name)
	{
		$reg = self::$app_register[$app];

		if (!isset($reg)) return false;

		if (!isset($reg[$name]))	// some defaults
		{
			switch($name)
			{
				case 'name':
					$reg[$name] = $app;
					break;
				case 'icon':
					if (isset($GLOBALS['egw_info']['apps'][$app]['icon']))
					{
						$reg[$name] = ($GLOBALS['egw_info']['apps'][$app]['icon_app'] ? $GLOBALS['egw_info']['apps'][$app]['icon_app'] : $app).
							'/'.$GLOBALS['egw_info']['apps'][$app]['icon'];
					}
					else
					{
						$reg[$name] = $app.'/navbar';
					}
					break;
			}
		}

		return isset($reg) ? $reg[$name] : false;
	}

	/**
	 * path to the attached files of $app/$ip or the directory for $app if no $id,$file given
	 *
	 * All link-files are based in the vfs-subdir '/apps/'.$app
	 *
	 * @param string $app appname
	 * @param string $id='' id in $app
	 * @param string $file='' filename
	 * @param boolean $just_the_path=false return url or just the vfs path
	 * @return string/array path or array with path and relatives, depending on $relatives
	 */
	static function vfs_path($app,$id='',$file='',$just_the_path=false)
	{
		$path = self::VFS_BASEURL;

		if ($app)
		{
			$path .= '/'.$app;

			if ($id)
			{
				$path .= '/'.$id;

				if ($file)
				{
					$path .= '/'.$file;
				}
			}
		}
		if ($just_the_path)
		{
			$path = parse_url($path,PHP_URL_PATH);
		}
		else
		{
			$path = egw_vfs::resolve_url($path);
		}
		//error_log(__METHOD__."($app,$id,$file,$just_the_path)=$path");
		return $path;
	}

	/**
	 * Put a file to the corrosponding place in the VFS and set the attributes
	 *
	 * Does NO is_uploaded_file check, calling application is responsible for doing that for uploaded files!
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
	 * @return int negative id of egw_sqlfs table as negative link-id's are for vfs attachments
	 */
	static function attach_file($app,$id,$file,$comment='')
	{
		$entry_dir = self::vfs_path($app,$id);
		if (self::DEBUG)
		{
			echo "<p>attach_file: app='$app', id='$id', tmp_name='$file[tmp_name]', name='$file[name]', size='$file[size]', type='$file[type]', path='$file[path]', ip='$file[ip]', comment='$comment', entry_dir='$entry_dir'</p>\n";
		}
		if (file_exists($entry_dir) || ($Ok = mkdir($entry_dir,0,true)))
		{
			$Ok = egw_vfs::copy_uploaded($file, $p=self::vfs_path($app,$id,'',true), $comment, false);	// no is_uploaded_file() check!
			if (!$Ok) error_log(__METHOD__."('$app', '$id', ".array2string($file).", '$comment') called egw_vfs::copy_uploaded('$file[tmp_name]', '$p', '$comment', false)=".array2string($Ok));
		}
		else
		{
			error_log(__METHOD__."($app,$id,".array2string($file).",$comment) Can't mkdir $entry_dir!");
		}
		return $Ok ? -$Ok['ino'] : false;
	}

	/**
	 * deletes a single or all attached files of an entry (for all there's no acl check, as the entry probably not exists any more!)
	 *
	 * @param int/string $app > 0: file_id of an attchemnt or $app/$id entry which linked to
	 * @param string $id='' id in app
	 * @param string $fname='' filename
	 * @return boolean|array false on error ($app or $id not found), array with path as key and boolean result of delete
	 */
	static function delete_attached($app,$id='',$fname='')
	{
		if ((int)$app > 0)	// is file_id
		{
			$url = egw_vfs::resolve_url(sqlfs_stream_wrapper::id2path($app));
		}
		else
		{
			if (empty($app) || empty($id))
			{
				return False;	// dont delete more than all attachments of an entry
			}
			$url = self::vfs_path($app,$id,$fname);

			if (!$fname || !$id)	// we delete the whole entry (or all entries), which probably not exist anymore
			{
				$current_is_root = egw_vfs::$is_root;
				egw_vfs::$is_root = true;
			}
		}
		if (self::DEBUG)
		{
			echo '<p>'.__METHOD__."('$app','$id','$fname') url=$url</p>\n";
		}
		if (($Ok = !file_exists($url) || egw_vfs::remove($url,true)) && ((int)$app > 0 || $fname))
		{
			// try removing the dir, in case it's empty
			@egw_vfs::rmdir(egw_vfs::dirname($url));
		}
		if (!is_null($current_is_root))
		{
			egw_vfs::$is_root = $current_is_root;
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
		$path = self::vfs_path($app,$id,$filename,true);
		if (!($stat = egw_vfs::stat($path,STREAM_URL_STAT_QUIET)))
		{
			return false;
		}
		return self::fileinfo2link($stat,$path);
	}

	/**
	 * converts a fileinfo (row in the vfs-db-table) in a link
	 *
	 * @param array/int $fileinfo a row from the vfs-db-table (eg. returned by the vfs ls static function) or a file_id of that table
	 * @return array a 'kind' of link-array
	 */
	static function fileinfo2link($fileinfo,$url=null)
	{
		if (!is_array($fileinfo))
		{
			$url = sqlfs_stream_wrapper::id2path($fileinfo);
			if (!($fileinfo = egw_vfs::url_stat($url,STREAM_URL_STAT_QUIET)))
			{
				return false;
			}
		}
		list(,,$app,$id) = explode('/',$url[0] == '/' ? $url : parse_url($url,PHP_URL_PATH));	// /apps/$app/$id

		return array(
			'app'       => self::VFS_APPNAME,
			'id'        => $fileinfo['name'],
			'app2'      => $app,
			'id2'       => $id,
			'remark'    => '',					// only list_attached currently sets the remark
			'owner'     => $fileinfo['uid'],
			'link_id'   => -$fileinfo['ino'],
			'lastmod'   => $fileinfo['mtime'],
			'size'      => $fileinfo['size'],
			'type'      => $fileinfo['mime'],
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
		$path = self::vfs_path($app,$id);
		//error_log(__METHOD__."($app,$id) url=$url");

		if (!($extra = self::get_registry($app,'find_extra'))) $extra = array();

		// always use regular links stream wrapper here: extended one is unnecessary (slow) for just listing attachments
		if (substr($path,0,13) == 'stylite.links') $path = substr($path,8);

		$attached = array();
		if (($url2stats = egw_vfs::find($path,array('need_mime'=>true,'type'=>'F','url'=>true)+$extra,true)))
		{
			$props = egw_vfs::propfind(array_keys($url2stats));	// get the comments
			foreach($url2stats as $url => &$fileinfo)
			{
				$link = self::fileinfo2link($fileinfo,$url);
				if ($props && isset($props[$url]))
				{
					foreach($props[$url] as $prop)
					{
						if ($prop['ns'] == egw_vfs::DEFAULT_PROP_NAMESPACE && $prop['name'] == 'comment')
						{
							$link['remark'] = $prop['val'];
							break;
						}
					}
				}
				$attached[$link['link_id']] = $link;
			}
		}
		return $attached;
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
		self::delete_cache($app,$id);
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

	/**
	 * Get a reference to the cached value for $app/$id for $type
	 *
	 * @param string $app
	 * @param string|int $id
	 * @param string $type='title' 'title' or 'file_access'
	 * @return int|string can be null, if cache not yet set
	 */
	private static function &get_cache($app,$id,$type = 'title')
	{
		switch($type)
		{
			case 'title':
				return self::$title_cache[$app.':'.$id];
			case 'file_access':
				return self::$file_access_cache[$app.':'.$id];
			default:
				throw new egw_exception_wrong_parameter("Unknown type '$type'!");
		}
	}

	/**
	 * Set title and optional file_access cache for $app,$id
	 *
	 * Allows applications to set values for title and file access, eg. in their search method,
	 * to not be called again. This offloads the need to cache from the app to the link class.
	 * If there's no caching, items get read multiple times from the database!
	 *
	 * @param string $app
	 * @param int|string $id
	 * @param string $title title string or null
	 * @param int $file_access=null EGW_ACL_READ, EGW_ACL_EDIT or both or'ed together
	 */
	public static function set_cache($app,$id,$title,$file_access=null)
	{
		//error_log(__METHOD__."($app,$id,$title,$file_access)");
		if (!is_null($title))
		{
			$cache =& self::get_cache($app,$id);
			$cache = $title;
		}
		if (!is_null($file_access))
		{
			$cache =& self::get_cache($app,$id,'file_access');
			$cache = $file_access;
		}
	}

	/**
	 * Delete the diverse caches for $app/$id
	 *
	 * @param string $app app-name or null to delete the whole cache
	 * @param int|string $id id or null to delete only file_access cache of given app (keeps title cache, if app implements file_access!)
	 */
	private static function delete_cache($app,$id)
	{
		unset(self::$title_cache[$app.':'.$id]);
		unset(self::$file_access_cache[$app.':'.$id]);
	}

	/**
	 * Check the file access perms for $app/id and given user $user
	 *
	 * If $user given and != current user AND app does not set file_access_user=true,
	 * allways return false, as there's no way to check access for an other user!
	 *
	 * @ToDo $rel_path is not yet implemented, as no app use it currently
	 * @param string $app
	 * @param string|int $id id of entry
	 * @param int $required=EGW_ACL_READ EGW_ACL_{READ|EDIT}
	 * @param string $rel_path=null
	 * @param int $user=null default null = current user
	 * @return boolean true if access granted, false otherwise
	 */
	static function file_access($app,$id,$required=EGW_ACL_READ,$rel_path=null,$user=null)
	{
		// are we called for an other user
		if ($user && $user != self::$user)
		{
			// check if app supports file_access WITH 4th $user parameter --> return false if not
			if (!self::get_registry($app,'file_access_user') || !($method = self::get_registry($app,'file_access')))
			{
				$ret = false;
				$err = "(no file_access_user)";
			}
			else
			{
				$ret = ExecMethod2($method,$id,$required,$rel_path,$user);
				$err = "(from $method)";
			}
			//error_log(__METHOD__."('$app',$id,$required,'$rel_path',$user) returning $err ".array2string($ret));
			return $ret;
		}

		$cache =& self::get_cache($app,$id,'file_access');

		if (!isset($cache) || $required == EGW_ACL_EDIT && !($cache & $required))
		{
			if(($method = self::get_registry($app,'file_access')))
			{
				$cache |= ExecMethod2($method,$id,$required,$rel_path) ? $required : 0;
			}
			else
			{
				$cache |= self::title($app,$id) ? EGW_ACL_READ|EGW_ACL_EDIT : 0;
			}
			//error_log(__METHOD__."($app,$id,$required,$rel_path) got $cache --> ".($cache & $required ? 'true' : 'false'));
		}
		//else error_log(__METHOD__."($app,$id,$required,$rel_path) using cached value $cache --> ".($cache & $required ? 'true' : 'false'));
		return !!($cache & $required);
	}
}
egw_link::init_static();
