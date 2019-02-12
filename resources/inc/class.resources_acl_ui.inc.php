<?php
/**
 * eGroupWare - resources
 *
 * @license http://www.gnu.org/licenses/gpl.Api\Html GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Categories;
use EGroupware\Api\Etemplate;

/**
 * ACL userinterface object for resources
 *
 * @package resources
 */
class resources_acl_ui
{
	/**
	 * Which methods of this class can be called as menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'index' => true,
		'edit'  => true,
	);

	public static $acl_map = array(
		'read' => Acl::READ,
		'write' => Acl::ADD,
		'calread' => resources_acl_bo::CAL_READ,
		'calwrite' => resources_acl_bo::DIRECT_BOOKING,
		'admin' => resources_acl_bo::CAT_ADMIN
	);

	function __construct()
	{
		$this->bo = new resources_acl_bo(True);
	}

	/**
	 * Display a list of categories with ACL
	 *
	 * @param Array $content Returned content from etemplate
	 */
	public function index($content = array())
	{
		if (!$GLOBALS['egw']->acl->check('run',1,'admin'))
		{
			$this->deny();
		}

		$content['nm'] = array(
			'get_rows'      =>	'resources.resources_acl_ui.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
			'no_search'      => True,
			'no_filter'      => True,        // I  disable the 1. filter
			'no_filter2'     => True,        // I  disable the 2. filter (params are the same as for filter)
			'no_cat'         => True,        // I  disable the cat-selectbox
			'row_id'         => 'id',    // I  key into row content to set it's value as row-id, eg. 'id'
			'parent_id'      =>	'parent',// I  key into row content of children linking them to their parent, also used as col_filter to query children
			'dataStorePrefix'=> 'categories',// Avoid conflict with user list when in admin
			'actions'        => self::get_actions(),    // I  array with actions, see nextmatch_widget::egw_actions
			'placeholder_actions' => array('add')    //  I Array Optional list of actions allowed on the placeholder.  If not provided, it's ["add"].
		);
		$template = new Etemplate('resources.acl');
		$GLOBALS['egw_info']['flags']['app_header'] = $GLOBALS['egw_info']['apps']['resources']['title'] . ' - ' . lang('Configure Access Permissions');

		$template->exec(__METHOD__, $content, $sel_options, $readonlys);
	}

	protected static function get_actions($appname='resources') {

		$actions = array(
			'open' => array(        // does edit if allowed, otherwise view
				'caption' => 'Open',
				'default' => true,
				'allowOnMultiple' => false,
				'url' => 'menuaction=resources.resources_acl_ui.edit&cat_id=$id',
				'popup' => '600x420',
				'group' => $group=1,
			),
			'add' => array(
				'caption' => 'Add',
				'allowOnMultiple' => false,
				'icon' => 'new',
				'url' => 'menuaction=admin.admin_categories.edit&appname=resources',
				'popup' => '600x380',
				'group' => $group,
			),
		);

		return $actions;
	}

	/**
	 * query rows for the nextmatch widget
	 *
	 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on Acl, not use here, maybe in a derived class
	 * @return int total number of rows
	 */
	public function get_rows(&$query,&$rows,&$readonlys)
	{

		Api\Cache::setSession('resources','acl-nm',$query);

		if($query['filter'] > 0 || $query['col_filter']['owner'])
		{
			$owner = $query['col_filter']['owner'] ? $query['col_filter']['owner'] : $query['filter'];
		}

		$cats = new Api\Categories($owner,'resources');
		$parent = $query['search'] ? false : 0;
		$rows = $cats->return_sorted_array($query['start'],false,$query['search'],$query['sort'],$query['order'],'all_no_acl',$parent,true,$filter);
		$count = $cats->total_records;


		$config = Api\Config::read('resources');
		$location_cats = $config['location_cats'] ? explode(',', $config['location_cats']) : array();

		foreach($rows as $key => &$row)
		{
			$row['owner'] = explode(',',$row['owner']);

			$row['level_spacer'] = str_repeat('&nbsp; &nbsp; ',$row['level']);
			$row['location'] = (in_array($row['id'], $location_cats));

			if ($row['data']['icon'])
			{
				$row['icon_url'] = $GLOBALS['egw_info']['server']['webserver_url'].  resources_bo::ICON_PATH.'/'.$row['data']['icon'];
			}

			$row['subs'] = count($row['children']);

			$row['class'] = 'level'.$row['level'];

			foreach(self::$acl_map  as $field => $acl)
			{
				$row[$field] = $GLOBALS['egw']->acl->get_ids_for_location('L'.$row['id'], $acl, 'resources');
			}

		}
		$rows = $count <= $query['num_rows'] ? array_values($rows) : array_slice($rows, $query['start'], $query['num_rows']);

		return $count;
	}

	/**
	 * Edit / add a category ACL
	 *
	 * @param array $content = null
	 * @param string $msg = ''
	 */
	public function edit(array $content=null,$msg='')
	{
		if (!$GLOBALS['egw']->acl->check('run',1,'admin'))
		{
			$this->deny();
		}

		$config = Api\Config::read('resources');
		$location_cats = $config['location_cats'] ? explode(',', $config['location_cats']) : array();

		if (!isset($content))
		{
			if (!(isset($_GET['cat_id']) && $_GET['cat_id'] > 0 &&
				($content = Categories::read($_GET['cat_id']))))
			{
				$content = array('data' => array());
			}
		}
		elseif ($content['button'])
		{
			$cats = new Categories($content['owner'] ? $content['owner'] : Categories::GLOBAL_ACCOUNT,'resources');

			$button = key($content['button']);
			unset($content['button']);

			$refresh_app = 'admin';

			switch($button)
			{
				case 'save':
				case 'apply':
					if(is_array($content['owner'])) $content['owner'] = implode(',',$content['owner']);
					if($content['owner'] == '') $content['owner'] = 0;
					if ($content['id'])
					{

						$data = $cats->id2name($content['id'],'data');
						try {
							$cats->edit($content);
							resources_acl_bo::set_rights(
								$content['id'], $content['read'], $content['write'], $content['calread'], $content['calwrite'], Array($content['admin'])
							);
							if($content['location'])
							{
								$location_cats[] = $content['id'];
								$location_cats = array_unique($location_cats);
							}
							else if(($key = array_search($content['id'], $location_cats)) !== false)
							{
								unset($location_cats[$key]);
							}
							Api\Config::save_value('location_cats', implode(',', $location_cats), 'resources');
							$msg = lang('Category saved.');
						}
						catch (Api\Exception\WrongUserinput $e)
						{
							$msg = lang('Unwilling to save category with current settings. Check for inconsistency:').$e->getMessage();	// display conflicts etc.
						}
					}
					else
					{
						$msg = lang('Permission denied!');
						unset($button);
					}
					if ($button == 'save')
					{
						Framework::refresh_opener($msg, $refresh_app, $content['id'], 'update', 'admin');
						Framework::window_close();
					}
					break;
			}
			// This should probably refresh the application $this->appname in the target tab $refresh_app, but that breaks pretty much everything
			Framework::refresh_opener($msg, $refresh_app, $content['id'], 'update', 'resources');
		}

		$content['appname'] = 'resources';
		if($content['data']['icon'])
		{
			$content['icon_url'] = $content['base_url'] . $content['data']['icon'];
		}


		foreach(self::$acl_map as $field => $acl)
		{
			$content[$field] = $GLOBALS['egw']->acl->get_ids_for_location('L'.$content['id'], $acl, 'resources');
		}

		// Make sure $content['admin'] is an array otherwise it wont show up values in the multiselectbox
		if($content['admin'] == 0)
		{
			unset($content['admin']);
		}
		else if (!is_array($content['admin']))
		{
			$content['admin'] = explode(',',$content['admin']);
		}

		// Location
		$content['location'] = in_array($content['id'],$location_cats);

		$tmpl = new Etemplate('resources.acl_edit');
		$tmpl->exec('resources.resources_acl_ui.edit',$content,$sel_options,$readonlys,$content,2);
	}

	function deny()
	{
		echo '<p><center><b>'.lang('Access not permitted').'</b></center>';
		exit(True);
	}
}
