<?php
/**
 * eGroupWare - resources
 *
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @version $Id$
 */

/**
 * ACL business object for resources
 *
 * @package resources
 */
class bo_acl
{
	/**
	* @var $permissions Holds alls permissions for resources of user
	*/
	var $permissions;

	var $acl;
	var $start = 0;
	var $query = '';
	var $sort  = '';
	var $total = 0;
	var $cats;

	var $debug;
	var $use_session = False;

	/**
	 * Instance of categories class for resources
	 *
	 * @var categories
	 */
	var $egw_cats;

	function bo_acl($session=False)
	{
		define('EGW_ACL_CAT_ADMIN',64);
		define('EGW_ACL_DIRECT_BOOKING',128);
		define('EGW_ACL_CALREAD',256);

		$this->permissions = $GLOBALS['egw']->acl->get_all_location_rights($GLOBALS['egw_info']['user']['account_id'],'resources',true);
		$this->egw_cats = new categories('','resources');
		$this->debug = False;

		//all this is only needed when called from uiacl.
		if($session)
		{
			$this->read_sessiondata();
			$this->use_session = True;
			foreach(array('start','query','sort','order') as $var)
			{
				if (isset($_POST[$var]))
				{
					$this->$var = $_POST[$var];
				}
				elseif (isset($_GET[$var]))
				{
					$this->$var = $_GET[$var];
				}
			}
			$this->save_sessiondata();
			$this->cats = $this->egw_cats->return_sorted_array(0,false,'','','',true);
		}
	}

	/**
	* get list of cats where current user has given rights
	*
	* @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	* @param int $perm_type one of EGW_ACL_READ, EGW_ACL_ADD, EGW_ACL_EDIT, EGW_ACL_DELETE, EGW_ACL_DIRECT_BOOKING
	* @param int $parent_id=0 cat_id of parent to return only children of that category
	* @return array cat_id => cat_name
	* TODO mark subcats and so on!
	*/
	function get_cats($perm_type,$parent_id=0)
	{
		$cats = $this->egw_cats->return_sorted_array(0,false,'','','',true,$parent_id);
		#_debug_array($cats);
		if (!is_array($cats)) $cats = array();
		foreach($cats as $key=>$cat) {
			#echo "key:$key"._debug_array($value)."<br>";
			#_debug_array($cat)."hier<br>";
			if($this->is_permitted($cat['id'],$perm_type))
			{
				$s = str_repeat('&nbsp;',$cat['level']) . stripslashes($cat['name']);
				if ($cat['app_name'] == 'phpgw' || $cat['owner'] == '-1')
				{
					$s .= ' &#9830;';
				}
				$perm_cats[$cat['id']] = $s;
			}
		}
		return $perm_cats;
	}


	/**
	* gets name of category
	*
	* @author Lukas Weiss <wnz.gh05t@users.sourceforge.net>
	* @param int $cat_id
	* @return mixed name of category
	*/
	function get_cat_name($cat_id)
	{
		return $this->egw_cats->id2name($cat_id);
	}

	/**
	* gets userid of admin for given category
	*
	* @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	* @param int $cat_id
	* @return int userid of cat admin
	*/
	function get_cat_admin($cat_id)
	{
		$cat_rights = $this->get_rights($cat_id);
		foreach ($cat_rights as $userid => $right)
		{
			if ($right & EGW_ACL_CAT_ADMIN)
			{
				return $userid;
			}
		}
		return lang('none');
	}

	/**
	* cheks one of the following rights for current user:
	*
	* EGW_ACL_READ, EGW_ACL_ADD, EGW_ACL_EDIT, EGW_ACL_DELETE, EGW_ACL_DIRECT_BOOKING
	*
	* @param int $cat_id
	* @param int $right
	* @return bool user is permitted or not for right
	*/
	function is_permitted($cat_id,$right)
	{
		return $this->permissions['L'.$cat_id] & $right;
	}

	/**
	* gets all rights from all user for given cat
	*
	* @param int $cat_id
	* @return array userid => right
	*/
	function get_rights($cat_id)
	{
		return $GLOBALS['egw']->acl->get_all_rights('L'.$cat_id,'resources');
	}


	// privat functions from here on -------------------------------------------------------------------------
	function save_sessiondata()
	{
		$data = array(
			'start' => $this->start,
			'query' => $this->query,
			'sort'  => $this->sort,
			'order' => $this->order,
			'limit' => $this->limit,
		);
		if($this->debug) { echo '<br>Read:'; _debug_array($data); }
		$GLOBALS['egw']->session->appsession('session_data','resources_acl',$data);
	}

	function read_sessiondata()
	{
		$data = $GLOBALS['egw']->session->appsession('session_data','resources_acl');
		if($this->debug) { echo '<br>Read:'; _debug_array($data); }

		$this->start  = $data['start'];
		$this->query  = $data['query'];
		$this->sort   = $data['sort'];
		$this->order  = $data['order'];
		$this->limit = $data['limit'];
	}

	function set_rights($cat_id,$read,$write,$calread,$calbook,$admin)
	{
		$readcat = $read ? $read : array();
		$writecat = $write ? $write : array();
		$calreadcat = $calread ? $calread : array();
		$calbookcat = $calbook ? $calbook : array();
		$admincat = $admin ? $admin : array();

		$GLOBALS['egw']->acl->delete_repository('resources','L' . $cat_id,false);

		foreach($GLOBALS['egw']->accounts->get_list() as $num => $account)
		{
			$account_id = $account['account_id'];
			$rights = false;
			$rights = in_array($account_id,$readcat) ? ($rights | EGW_ACL_READ) : false;
			$rights = in_array($account_id,$writecat) ? ($rights | EGW_ACL_READ | EGW_ACL_ADD | EGW_ACL_EDIT | EGW_ACL_DELETE): $rights;
			$rights = in_array($account_id,$calreadcat) ? ($rights | EGW_ACL_CALREAD) : $rights;
			$rights = in_array($account_id,$calbookcat) ? ($rights | EGW_ACL_DIRECT_BOOKING | EGW_ACL_CALREAD) : $rights;
			$rights = in_array($account_id,$admincat) ? ($rights = 511) : $rights;
			if ($rights)
			{
				$GLOBALS['egw']->acl->add_repository('resources','L'.$cat_id,$account_id,$rights);
			}
		}
	}
}
