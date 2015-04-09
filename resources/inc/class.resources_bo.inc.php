<?php
/**
 * EGroupware - resources
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
 * @version $Id$
 */


/**
 * General business object for resources
 *
 * @package resources
 */
class resources_bo
{
	const DELETED = 'deleted';
	const PICTURE_NAME = '.picture.jpg';
	var $resource_icons = '/resources/templates/default/images/resource_icons/';
	var $debug = 0;
	/**
	 * Instance of resources so object
	 *
	 * @var resources_so
	 */
	var $so;
	/**
	 * Instance of resources acl class
	 *
	 * @var bo_acl
	 */
	var $acl;
	/**
	 * Instance of categories class for resources
	 */
	var $cats;

	/**
	 * List of filter options
	 */
	public static $filter_options = array(
		-1 => 'resources',
		-2 => 'accessories',
		-3 => 'resources and accessories'
		// Accessories of a resource added when resource selected
	);

	public static $field2label = array(
		'res_id'	=> 'Resource ID',
		'name'		=> 'name',
		'short_description'	=> 'short description',
		'cat_id'	=> 'Category',
		'quantity'	=> 'Quantity',
		'useable'	=> 'Useable',
		'location'	=> 'Location',
		'storage_info'	=> 'Storage',
		'bookable'	=> 'Bookable',
		'buyable'	=> 'Buyable',
		'prize'		=> 'Prize',
		'long_description'	=> 'Long description',
		'inventory_number'	=> 'inventory number',
		'accessory_of'	=> 'Accessory of'
	);

	/**
	 * Constructor
	 *
	 * @param int $user=null account_id of user to use for acl, default current user
	 */
	function __construct($user=null)
	{
		$this->so = new resources_so();
		$this->acl = CreateObject('resources.bo_acl', $user);
		$this->cats = $this->acl->egw_cats;

		$this->cal_right_transform = array(
			EGW_ACL_CALREAD 	=> EGW_ACL_READ,
			EGW_ACL_DIRECT_BOOKING 	=> EGW_ACL_READ | EGW_ACL_ADD | EGW_ACL_EDIT | EGW_ACL_DELETE,
			EGW_ACL_CAT_ADMIN 	=> EGW_ACL_READ | EGW_ACL_ADD | EGW_ACL_EDIT | EGW_ACL_DELETE,
		);
	}

	/**
	 * get rows for resources list
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		if(!$query['csv_export'])
		{
			$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$query);
		}
		if ($query['store_state'])	// request to store state in session and filter in prefs?
		{
			egw_cache::setSession('resources',$query['store_state'],$query);
			//echo "<p>".__METHOD__."() query[filter]=$query[filter], prefs[resources][filter]={$GLOBALS['egw_info']['user']['preferences']['resources']['filter']}</p>\n";
			if ($query['filter'] != $GLOBALS['egw_info']['user']['preferences']['resources']['filter'])
			{
				$GLOBALS['egw']->preferences->add('resources','filter',$query['filter'],'user');
				$GLOBALS['egw']->preferences->save_repository();
			}
		}
		if ($this->debug) _debug_array($query);
		$read_onlys = 'res_id,name,short_description,quantity,useable,bookable,buyable,cat_id,location,storage_info';

		$filter = array();
		$join = '';
		$extra_cols = array();

		// Sub-query to get the count of accessories
		$acc_join = "LEFT JOIN (SELECT accessory_of AS accessory_id, count(res_id) as acc_count FROM {$this->so->table_name} GROUP BY accessory_of) AS acc ON acc.accessory_id = {$this->so->table_name}.res_id ";

		switch($query['filter2'])
		{
			case -1:
				// Resources only
				$filter['accessory_of'] = -1;
				$join = $acc_join;
				$extra_cols[] = 'acc_count';
				break;
			case -2:
				// Accessories only
				$filter[] = 'accessory_of != -1';
				break;
			case -3:
				// All
				$join = $acc_join;
				$extra_cols[] = 'acc_count';
				break;
			case self::DELETED:
				$join = $acc_join;
				$extra_cols[] = 'acc_count';
				$filter[] = 'deleted IS NOT NULL';
				break;
			default:
				$filter['accessory_of'] = $query['filter2'];
		}
		if($query['filter2'] != self::DELETED)
		{
			$filter['deleted'] = null;
		}

		if ($query['filter'])
		{
			if (($children = $this->acl->get_cats(EGW_ACL_READ,$query['filter'])))
			{
				$filter['cat_id'] = array_keys($children);
				$filter['cat_id'][] = $query['filter'];
			}
			else
			{
				$filter['cat_id'] = $query['filter'];
			}
		}
		elseif (($readcats = $this->acl->get_cats(EGW_ACL_READ)))
		{
			$filter['cat_id'] = array_keys($readcats);
		}
		// if there is no catfilter -> this means you have no rights, so set the cat filter to null
		if (!isset($filter['cat_id']) || empty($filter['cat_id'])) {
			$filter['cat_id'] = NUll;
		}

		if ($query['show_bookable'])
		{
			$filter['bookable'] = true;
		}
		$order_by = $query['order'] ? $query['order'].' '. $query['sort'] : '';
		$start = (int)$query['start'];

		foreach ($filter as $k => $v) $query['col_filter'][$k] = $v;
		$this->so->get_rows($query, $rows, $readonlys, $join, false, false, $extra_cols);
		$nr = $this->so->total;

		// we are called to serve bookable resources (e.g. calendar-dialog)
		if($query['show_bookable'])
		{
			// This is somehow ugly, i know...
			foreach((array)$rows as $num => $resource)
			{
				$rows[$num]['default_qty'] = 1;
			}
			// we don't need all the following testing
			return $nr;
		}

		$config = config::read('resources');
		foreach($rows as $num => &$resource)
		{
			if (!$this->acl->is_permitted($resource['cat_id'],EGW_ACL_EDIT))
			{
				$readonlys["edit[$resource[res_id]]"] = true;
			}
			elseif($resource['deleted'])
			{
				$resource['class'] .= 'deleted ';
			}
			if (!$this->acl->is_permitted($resource['cat_id'],EGW_ACL_DELETE) ||
				($resource['deleted'] && !$GLOBALS['egw_info']['user']['apps']['admin'] && $config['history'] == 'history')
			)
			{
				$readonlys["delete[$resource[res_id]]"] = true;
				$resource['class'] .= 'no_delete ';
			}
			if ((!$this->acl->is_permitted($resource['cat_id'],EGW_ACL_ADD)) ||
				// Allow new accessory action when viewing accessories of a certain resource
				$query['filter2'] <= 0 && $resource['accessory_of'] != -1)
			{
				$readonlys["new_acc[$resource[res_id]]"] = true;
				$resource['class'] .= 'no_new_accessory ';
			}
			if (!$resource['bookable'])
			{
				$readonlys["bookable[$resource[res_id]]"] = true;
				$readonlys["calendar[$resource[res_id]]"] = true;
				$resource['class'] .= 'no_book ';
				$resource['class'] .= 'no_view_calendar ';
			}
			if(!$this->acl->is_permitted($resource['cat_id'],EGW_ACL_CALREAD))
			{
				$readonlys["calendar[$resource[res_id]]"] = true;
				$resource['class'] .= 'no_view_calendar ';
			}
			if (!$resource['buyable'])
			{
				$readonlys["buyable[$resource[res_id]]"] = true;
				$resource['class'] .= 'no_buy ';
			}
			$readonlys["view_acc[{$resource['res_id']}]"] = ($resource['acc_count'] == 0);
			$resource['class'] .= ($resource['accessory_of']==-1 ? 'resource ' : 'accessory ');
			if($resource['acc_count'])
			{
				$resource['class'] .= 'hasAccessories ';
				$accessories = $this->get_acc_list($resource['res_id'],$query['filter2']==self::DELETED);
				foreach($accessories as $acc_id => $acc_name)
				{
					$resource['accessories'][] = array('acc_id' => $acc_id, 'name' => $this->link_title($acc_id));
				}
			} elseif ($resource['accessory_of'] > 0) {
				$resource['accessory_of_label'] = $this->link_title($resource['accessory_of']);
			}

			if($resource['deleted'])
			{
				$rows[$num]['picture_thumb'] = 'deleted';
			}
			else
			{
				$rows[$num]['picture_thumb'] = $this->get_picture($resource, false);
				if ($rows[$num]['picture_src'] == 'own_src')
				{
					// VFS picture fullsize
					$rows[$num]['picture_original'] = 'webdav.php/apps/resources/'.$rows[$num]['res_id'].'/.picture.jpg';
				}
				else
				{
					// cat or generic icon fullsize
					$rows[$num]['picture_original'] = $this->get_picture($resource, true);
				}
			}
			$rows[$num]['admin'] = $this->acl->get_cat_admin($resource['cat_id']);
		}

		if(!config::get_customfields('resources'))
		{
			$rows['no_customfields'] = true;
		}
		return $nr;
	}

	/**
	 * reads a resource exept binary datas
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param int $res_id resource id
	 * @return array with key => value or false if not found or allowed
	 */
	function read($res_id)
	{
		if (!($data = $this->so->read(array('res_id' => $res_id))))
		{
			return null;	// not found
		}
		if (!$this->acl->is_permitted($data['cat_id'],EGW_ACL_READ))
		{
			return false;	// permission denied
		}
		return $data;
	}

	/**
	 * saves a resource. pictures are saved in vfs
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param array $resource array with key => value of all needed datas
	 * @return string msg if somthing went wrong; nothing if all right
	 */
	function save($resource)
	{
		if(!$this->acl->is_permitted($resource['cat_id'],EGW_ACL_EDIT))
		{
			return lang('You are not permitted to edit this resource!');
		}
		$old = array();
		// we need an id to save pictures and make links...
		if(!$resource['res_id'])
		{
			$resource['res_owner'] = $GLOBALS['egw_info']['user']['account_id'];
			$resource['res_created'] = egw_time::server2user(time(),'ts');
			$resource['res_id'] = $this->so->save($resource);
		}
		else
		{
			$resource['res_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
			$resource['res_modified'] = egw_time::server2user(time(),'ts');
			$old = $this->read($resource['res_id']);
		}

		switch ($resource['picture_src'])
		{
			case 'own_src':
				if($resource['own_file']['size'] > 0)
				{
					$msg = $this->save_picture($resource['own_file'],$resource['res_id']);
					unset($resource['own_file']);
					break;
				}
				elseif(@egw_vfs::stat('/apps/resources/'.$resource['res_id'].'/'.self::PICTURE_NAME))
				{
					break;
				}
				$resource['picture_src'] = 'cat_src';
			case 'cat_src':
				break;
			case 'gen_src':
				$resource['picture_src'] = 'gen_src';
				break;
			default:
				if($resource['own_file']['size'] > 0)
				{
					$resource['picture_src'] = 'own_src';
					$msg = $this->save_picture($resource['own_file'],$resource['res_id']);
				}
				else
				{
					$resource['picture_src'] = 'cat_src';
				}
		}
		// somthing went wrong on saving own picture
		if($msg)
		{
			return $msg;
		}

		// Check for restore of deleted, restore held links
                if($old && $old['deleted'] && !$resource['deleted'])
                {
                        egw_link::restore('resources', $resource['res_id']);
                }

		// delete old pictures
		if($resource['picture_src'] != 'own_src')
		{
			$this->remove_picture($resource['res_id']);
		}

		// Update link title
		egw_link::notify_update('resources',$resource['res_id'], $resource);
		// save links
		if(is_array($resource['link_to']['to_id']))
		{
			egw_link::link('resources',$resource['res_id'],$resource['link_to']['to_id']);
		}
		if($resource['accessory_of'] != $old['accessory_of'])
		{
			egw_link::unlink(0,'resources',$resource['res_id'],'','resources',$old['accessory_of']);

			// Check for resource changing to accessory - move its accessories to resource
			if($old['accessory_of'] == -1 && $accessories = $this->get_acc_list($resource['res_id']))
			{
				foreach($accessories as $accessory => $name)
				{
					egw_link::unlink(0,'resources',$accessory,'','resources',$resource['res_id']);
					$acc = $this->read($accessory);
					$acc['accessory_of'] = -1;
					$this->so->save($acc);
				}
			}
		}
		if($resource['accessory_of'] != -1)
		{
			egw_link::link('resources',$resource['res_id'],'resources',$resource['accessory_of']);
		}

		if(!empty($resource['res_id']) && $this->so->get_value("cat_id",$resource['res_id']) != $resource['cat_id'] && $resource['accessory_of'] == -1)
		{
			$accessories = $this->get_acc_list($resource['res_id']);
			foreach($accessories as $accessory => $name)
			{
				$acc = $this->so->read($accessory);
				$acc['cat_id'] = $resource['cat_id'];
				$this->so->data = $acc;
				$this->so->save();
			}
		}

		$res_id = $this->so->save($resource);

		// History & notifications
		if (!is_object($this->tracking))
		{
			$this->tracking = new resources_tracking();
		}
		if ($this->tracking->track($resource,$old,$this->user) === false)
		{
			return implode(', ',$this->tracking->errors);
		}

		return $res_id ? $res_id : lang('Something went wrong by saving resource');
	}

	/**
	 * deletes resource including pictures and links
	 *
	 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
	 * @param int $res_id id of resource
	 */
	function delete($res_id)
	{
		if(!$this->acl->is_permitted($this->so->get_value('cat_id',$res_id),EGW_ACL_DELETE))
		{
			return lang('You are not permitted to delete this resource!');
		}

		// check if we only mark resources as deleted, or really delete them
		$old = $this->read($res_id);
		$config = config::read('resources');
		if ($config['history'] != '' && $old['deleted'] == null)
		{
			$old['deleted'] = time();
			$this->save($old);
			egw_link::unlink(0,'resources',$res_id,'','','',true);
			$accessories = $this->get_acc_list($res_id);
			foreach($accessories as $acc_id => $name)
			{
				// Don't purge already deleted accessories
				$acc = $this->read($acc_id);
				if(!$acc['deleted'])
				{
					$acc['deleted'] = time();
					$this->save($acc);
					egw_link::unlink(0,'resources',$acc_id,'','','',true);
				}
			}
			return false;
		}
		elseif ($this->so->delete(array('res_id'=>$res_id)))
		{
			$accessories = $this->get_acc_list($res_id, true);
			foreach($accessories as $acc_id => $name)
			{
				if($this->delete($acc_id))
				{
					$acc = $this->read($acc_id);
					$acc['accessory_of'] = -1;
					$this->save($acc);
				}
			};
			$this->remove_picture($res_id);
	 		egw_link::unlink(0,'resources',$res_id);
	 		// delete the resource from the calendar
	 		ExecMethod('calendar.calendar_so.deleteaccount','r'.$res_id);
	 		return false;
		}
		return lang('Something went wrong by deleting resource');
	}

	/**
	 * gets list of accessories for resource
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param int $res_id id of resource
	 * @param boolean $deleted Include deleted accessories
	 * @return array
	 */
	function get_acc_list($res_id,$deleted=false)
	{
		if($res_id < 1){return;}
		$data = $this->so->search('','res_id,name,deleted','','','','','',$start,array('accessory_of' => $res_id),'',$need_full_no_count=true);
		$acc_list = array();
		if($data) {
			foreach($data as $num => $resource)
			{
				if($resource['deleted'] && !$deleted) continue;
				$acc_list[$resource['res_id']] = $resource['name'];
			}
		}
		return $acc_list;
	}

	/**
	 * returns info about resource for calender
	 * @author Cornelius Weiss<egw@von-und-zu-weiss.de>
	 * @param int|array $res_id single id or array $num => $res_id
	 * @return array
	 */
	function get_calendar_info($res_id)
	{
		//echo "<p>resources_bo::get_calendar_info(".print_r($res_id,true).")</p>\n";
		if(!is_array($res_id) && $res_id < 1) return;

		$data = $this->so->search(array('res_id' => $res_id),self::TITLE_COLS.',useable');
		if (!is_array($data))
		{
			error_log(__METHOD__." No Calendar Data found for Resource with id $res_id");
			return array();
		}
		foreach($data as $num => &$resource)
		{
			$resource['rights'] = false;
			foreach($this->cal_right_transform as $res_right => $cal_right)
			{
				if($this->acl->is_permitted($resource['cat_id'],$res_right))
				{
					$resource['rights'] = $cal_right;
				}
			}
			$resource['responsible'] = $this->acl->get_cat_admin($resource['cat_id']);

			// preseed the cache
			egw_link::set_cache('resources',$resource['res_id'],$t=$this->link_title($resource));
		}
		return $data;
	}

	/**
	 * returns status for a new calendar entry depending on resources ACL
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param int $res_id single id
	 * @return string|boolean false if resource not found, no read rights or not bookable, else A if user has direkt booking rights or U if no dirket booking
	 */
	function get_calendar_new_status($res_id)
	{
		if (!($data = $this->read($res_id)) || !$data['bookable'])
		{
			return false;
		}
		return $this->acl->is_permitted($data['cat_id'],EGW_ACL_DIRECT_BOOKING) ? A : U;
	}

	/**
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * query infolog for entries matching $pattern
	 * @param string|array $pattern if it's a string it is the string we will search for as a criteria, if it's an array we
	 * 	will seach for 'search' key in this array to get the string criteria. others keys handled are actually used
	 *	for calendar disponibility.
	 * @param array $options Array of options for the search
	 *
	 */
	function link_query( $pattern, Array &$options = array() )
	{
		if (is_array($pattern))
		{
			$criteria =array('name' => $pattern['search']
					,'short_description' => $pattern['search']);
		}
		else
		{
			$criteria = array('name' => $pattern
				, 'short_description' => $pattern);
		}
		$only_keys = 'res_id,name,short_description,bookable,useable';

		// If no read access to any category, just stop
		if(!$this->acl->get_cats(EGW_ACL_READ))
		{
			$options['total'] = 0;
			return array();
		}
		$filter = array(
			'cat_id' => array_flip((array)$this->acl->get_cats(EGW_ACL_READ)),
			//'accessory_of' => '-1'
			'deleted' => null
		);
		$limit = false;
		if($options['start'] || $options['num_rows']) {
			$limit = array($options['start'], $options['num_rows']);
		}
		if($options['accessory_of'])
		{
			$filter['accessory_of'] = $options['accessory_of'];
		}
		$data = $this->so->search($criteria,$only_keys,$order_by='name',$extra_cols='',$wildcard='%',$empty,$op='OR',$limit,$filter);
		// maybe we need to check disponibility of the searched resources in the calendar if $pattern ['exec'] contains some extra args
		$show_conflict=False;
		if ($options['exec'])
		{
			// we'll use a cache for resources info taken from database
			static $res_info_cache = array();
			$cal_info=$options['exec'];
			if ( isset($cal_info['start']) && isset($cal_info['duration']))
			{
				//get a calendar objet for reservations
				if ( (!isset($this->bocal)) || !(is_object($this->bocal)))
				{
					require_once(EGW_INCLUDE_ROOT.'/calendar/inc/class.calendar_bo.inc.php');
					$this->bocal =& CreateObject('calendar.calendar_bo');
				}
				$start = new egw_time($cal_info['start']);
				$startarr= getdate($start->format('ts'));
				if (isset($cal_info['whole_day'])) {
					$startarr['hour'] = $startarr['minute'] = 0;
					$start = new egw_time($startarr);
					$end = $start->format('ts') + 86399;
				} else {
					$start = $start->format('ts');
					$end = $start + ($cal_info['duration']);
				}

				// search events matching our timestamps
 				$resource_list=array();
				foreach($data as $num => $resource)
				{
					// we only need resources id for the search, but with a 'r' prefix
					// now we take this loop to store a new resource array indexed with resource id
					// and as we work for calendar we use only bookable resources
					if ((isset($resource['bookable'])) && ($resource['bookable'])){
						$res_info_cache[$resource['res_id']]=$resource;
						$resource_list[]='r'.$resource['res_id'];
					}
				}
				$overlapping_events =& $this->bocal->search(array(
					'start' => $start,
					'end'   => $end,
					'users' => $resource_list,
					'ignore_acl' => true,   // otherwise we get only events readable by the user
					'enum_groups' => false,  // otherwise group-events would not block time
				));

				// parse theses overlapping events
				foreach($overlapping_events as $event)
				{
					if ($event['non_blocking']) continue; // ignore non_blocking events
					if (isset($cal_info['event_id']) && $event['id']==$cal_info['event_id']) {
						continue; //ignore this event, it's the current edited event, no conflict by def
					}
					// now we are interested only on resources booked by theses events
					if (isset($event['participants']) && is_array($event['participants'])){
						foreach($event['participants'] as $part_key => $part_detail){
							if ($part_key{0}=='r')
							{ //now we gatta resource here
								//need to check the quantity of this resource
								$resource_id=substr($part_key,1);
								// if we do not find this resource in our indexed array it's certainly
								// because it was unset, non bookable maybe
								if (!isset($res_info_cache[$resource_id])) continue;
								// to detect ressources with default to 1 quantity
								if (!isset($res_info_cache[$resource_id]['useable'])) {
									$res_info_cache[$resource_id]['useable'] = 1;
								}
								// now decrement this quantity useable
								// TODO : decrement with real event quantity, not 1
								// but this quantity is not given by calendar search, we should re-use a cal object
								// to load specific cal infos, like quantity... lot of requests
								$res_info_cache[$resource_id]['useable']--;
							}
						}
					}
				}
			}
		}
		if (isset($res_info_cache)) {
			$show_conflict= (isset($options['exec']['show_conflict'])&& ($options['exec']['show_conflict']=='0'))? False:True;
			// if we have this array indexed on resource id it means non-bookable resource are removed and we are working for calendar
			// so we'll loop on this one and not $data
			foreach($res_info_cache as $id => $resource) {
				//maybe this resource is reserved
				if ( ($resource['useable'] < 1) )
				{
					if($show_conflict) {
						$list[$id] = ' ('.lang('conflict').') '.$resource['name']. ($resource['short_description'] ? ', ['.$resource['short_description'].']':'');
					}
				} else {
				        $list[$id] = $resource['name']. ($resource['short_description'] ? ', ['.$resource['short_description'].']':'');
				}
			}
		} else {
			// we are not working for the calendar, we loop on the initial $data
			if (is_array($data)) {
				foreach($data as $num => $resource)
				{
					$id=$resource['res_id'];
					$list[$id] = $resource['name']. ($resource['short_description'] ? ', ['.$resource['short_description'].']':'');
				}
			} else {
				error_log(__METHOD__." No Data found for Resource with id ".$resource['res_id']);
			}
		}
		$options['total'] = $this->so->total;
		return $list;
	}

	/**
	 * get title for an infolog entry identified by $res_id
	 *
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param int|array $resource
	 * @return string|boolean string with title, null if resource does not exist or false if no perms to view it
	 */
	function link_title( $resource )
	{
		if (!is_array($resource))
		{
			if (!($resource  = $this->read(array('res_id' => $resource)))) return $resource;
		}
		elseif (!$this->acl->is_permitted($resource['cat_id'],EGW_ACL_READ))
		{
			return false;
		}
		return $resource['name']. ($resource['short_description'] ? ', ['.$resource['short_description'].']':'');
	}

	/**
	 * Columns displayed in title (or required for ACL)
	 *
	 */
	const TITLE_COLS = 'res_id,name,short_description,cat_id';

	/**
	 * get title for multiple contacts identified by $ids
	 *
	 * Is called as hook to participate in the linking.
	 *
	 * @param array $ids array with resource-id's
	 * @return array with titles, see link_title
	 */
	function link_titles(array $ids)
	{
		$titles = array();
		if (($resources =& $this->so->search(array('res_id' => $ids),self::TITLE_COLS)))
		{
			foreach($resources as $resource)
			{
				$titles[$resource['res_id']] = $this->link_title($resource);
			}
		}
		// we assume all not returned contacts are not readable for the user (as we report all deleted contacts to egw_link)
		foreach($ids as $id)
		{
			if (!isset($titles[$id]))
			{
				$titles[$id] = false;
			}
		}
		return $titles;
	}

	/**
	 * saves a pictures in vfs
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param array $file array with key => value
	 * @param int $resource_id
	 * @return mixed string with msg if somthing went wrong; nothing if all right
	 */
	function save_picture($file,$resouce_id)
	{
		switch($file['type'])
		{
			case 'image/gif':
				$src_img = imagecreatefromgif($file['tmp_name']);
				break;
			case 'image/jpeg':
			case 'image/pjpeg':
				$src_img = imagecreatefromjpeg($file['tmp_name']);
				break;
			case 'image/png':
			case 'image/x-png':
				$src_img = imagecreatefrompng($file['tmp_name']);
				break;
			default:
				return lang('Picture type is not supported, sorry!');
		}

		$tmp_name = tempnam($GLOBALS['egw_info']['server']['temp_dir'],'resources-picture');
		imagejpeg($src_img,$tmp_name);
		imagedestroy($src_img);

		egw_link::attach_file('resources',$resouce_id,array(
			'tmp_name' => $tmp_name,
			'name'     => self::PICTURE_NAME,
			'type'     => 'image/jpeg',
		));
	}

	/**
	 * get resource picture either from vfs or from symlink
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param int|array $resource res-id or whole resource array
	 * @param bool $fullsize false = thumb, true = full pic
	 * @return string url of picture
	 */
	function get_picture($resource,$fullsize=false)
	{
		if ($resource && !is_array($resource)) $resource = $this->read($resource);

		switch($resource['picture_src'])
		{
			case 'own_src':
				$picture = egw_link::vfs_path('resources',$resource['res_id'],self::PICTURE_NAME,true);	// vfs path
				if ($fullsize)
				{
					$picture = egw::link(egw_vfs::download_url($picture));
				}
				else
				{
					$picture = egw::link('/etemplate/thumbnail.php', array(
						'path' => $picture
					));
				}
				break;

			case 'cat_src':
				list($picture) = $this->cats->return_single($resource['cat_id']);
				$picture = unserialize($picture['data']);
				if($picture['icon'])
				{
					$picture = !$fullsize?$GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/images/'.$picture['icon']:'/phpgwapi/images/'.$picture['icon'];
					break;
				}
				// fall through
			case 'gen_src':
			default :
				$src = $resource['picture_src'];
				$picture = !$fullsize?$GLOBALS['egw_info']['server']['webserver_url'].$this->resource_icons:$this->resource_icons;
				$picture .= strpos($src,'.') !== false ? $src : 'generic.png';
		}
		return $picture;
	}

	/**
	 * removes picture from vfs
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param int $res_id id of resource
	 * @return bool succsess or not
	 */
	function remove_picture($res_id)
	{
		if (($arr = egw_link::delete_attached('resources',$res_id,self::PICTURE_NAME)) && is_array($arr))
		{
			return array_shift($arr);	// $arr = array($path => (bool)$ok);
		}
		return false;
	}

	/**
	 * get_genpicturelist
	 * gets all pictures from 'generic picutres dir' in selectbox style for eTemplate
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @return array directory contens in eTemplates selectbox style
	 */
	function get_genpicturelist()
	{
		$icons['generic.png'] = lang('gernal resource');
		$dir = dir(EGW_SERVER_ROOT.$this->resource_icons);
		while($file = $dir->read())
		{
			if (preg_match('/\\.(png|gif|jpe?g)$/i',$file) && $file != 'generic.png')
			{
				$icons[$file] = substr($file,0,strpos($file,'.'));
			}
		}
		$dir->close();
		return $icons;
	}
}
