<?php
/**
 * eGroupWare - resources
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Acl;
use EGroupware\Api\Etemplate;

/**
 * General userinterface object for resources
 *
 * @package resources
 */
class resources_ui
{
	var $public_functions = array(
		'index'		=> True,
		'edit'		=> True,
		'select'	=> True,
		'writeLangFile'	=> True
	);

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
// 		print_r($GLOBALS['egw_info']); die();
		$this->tmpl	= new Etemplate('resources.show');
		$this->bo	= new resources_bo();
// 		$this->calui	= CreateObject('resources.ui_calviews');
	}

	/**
	 * main resources list.
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param array $content content from eTemplate callback
	 *
	 */
	function index($content='')
	{
		if (is_array($content))
		{
			$sessiondata = $content['nm'];
			unset($sessiondata['rows']);
			Api\Cache::setSession('resources', 'index_nm', $sessiondata);

			if (isset($content['btn_delete_selected']))
			{
				foreach($content['nm']['rows'] as $row)
				{
					if($res_id = $row['checkbox'][0])
					{
						$msg .= '<p>'. $this->bo->delete($res_id). '</p><br>';
					}
				}
				return $this->index($msg);
			}
			foreach($content['nm']['rows'] as $row)
			{
				if(isset($row['delete']))
				{
					$res_id = array_search('pressed',$row['delete']);
					return $this->index($this->bo->delete($res_id));
				}
				if(isset($row['view_acc']))
				{
					$sessiondata['filter2'] = array_search('pressed',$row['view_acc']);
					Api\Cache::setSession('resources', 'index_nm', $sessiondata);
					return $this->index();
				}
			}
			if ($content['nm']['action'])
			{
				if (!count($content['nm']['selected']) && !$content['nm']['select_all'])
				{
					$msg = lang('You need to select some entries first!');
				}
				else
				{
					if ($this->action($content['nm']['action'],$content['nm']['selected'],$content['nm']['select_all'],
						$success,$failed,$action_msg,'resources_index_nm',$msg))
					{
						$msg .= lang('%1 resource(s) %2',$success,$action_msg);
					}
					elseif(empty($msg))
					{
						$msg .= lang('%1 resource(s) %2, %3 failed because of insufficent rights !!!',$success,$action_msg,$failed);
					}
					else
					{
						$msg .= lang('%1 resource(s) %2, %3 failed',$success,$action_msg,$failed);
					}
				}
			}
		} else {
			$msg = $content;
		}
		$content = array();
		$content['msg'] = $msg ? $msg : $_GET['msg'];

		$content['nm']['get_rows']		= 'resources.resources_bo.get_rows';
		$content['nm']['no_filter'] 	= False;
		$content['nm']['filter_no_lang'] = true;
		$content['nm']['no_cat']	= true;
		$content['nm']['bottom_too']	= true;
		$content['nm']['order']		= 'name';
		$content['nm']['sort']		= 'ASC';
		$content['nm']['store_state']	= 'get_rows';
		$content['nm']['row_id']	= 'res_id';
		$content['nm']['favorites'] = true;

		$nm_session_data = Api\Cache::getSession('resources', 'index_nm');
		if($nm_session_data)
		{
			$content['nm'] = $nm_session_data;
		}
		$content['nm']['options-filter']= array(''=>lang('all categories'))+(array)$this->bo->acl->get_cats(Acl::READ);
		$content['nm']['options-filter2'] = resources_bo::$filter_options;
		if(!$content['nm']['filter2'])
		{
			$content['nm']['filter2'] = key(resources_bo::$filter_options);
		}

		$config = Api\Config::read('resources');
		if($config['history'])
		{
			$content['nm']['options-filter2'][resources_bo::DELETED] = lang('Deleted');
		}

		if($_GET['search']) {
			$content['nm']['search'] = $_GET['search'];
		}
		if($_GET['view_accs_of'])
		{
			$content['nm']['filter2'] = (int)$_GET['view_accs_of'];
		}
		$content['nm']['actions']	= $this->get_actions();
		$content['nm']['placeholder_actions'] = array('add');

		// check if user is permitted to add resources
		// If they can't read any categories, they won't be able to save it
		if(!$this->bo->acl->get_cats(Acl::ADD) || !$this->bo->acl->get_cats(Acl::READ))
		{
			$no_button['add'] = $no_button['nm']['add'] = true;
		}
		$no_button['back'] = true;
		$GLOBALS['egw_info']['flags']['app_header'] = lang('resources');

		Framework::includeJS('.','resources','resources');

		if($content['nm']['filter2'] > 0)
		{
			$master = $this->bo->so->read(array('res_id' => $content['nm']['filter2']));
			$content['nm']['options-filter2'] = resources_bo::$filter_options + array(
				$master['res_id'] => lang('accessories of') . ' ' . $master['name']
			);
			$content['nm']['get_rows'] 	= 'resources.resources_bo.get_rows';
			$GLOBALS['egw_info']['flags']['app_header'] = lang('resources') . ' - ' . lang('accessories of '). ' '. $master['name'] .
				($master['short_description'] ? ' [' . $master['short_description'] . ']' : '');
		}
		$preserv = $content;

		$options = array();

		Api\Cache::setSession('resources', 'index_nm', $content['nm']);
		$this->tmpl->read('resources.show');
		return $this->tmpl->exec('resources.resources_ui.index',$content,$sel_options,$no_button,$preserv);
	}

	/**
	 * Get actions / context menu for index
	 *
	 * @return array see nextmatch_widget::egw_actions()
	 */
	public function get_actions()
	{
		$actions = array(
			'edit' => array(
				'default' => true,
				'caption' => 'open',
				'allowOnMultiple' => false,
				'url' => 'menuaction=resources.resources_ui.edit&res_id=$id',
				'popup' => Link::get_registry('resources', 'add_popup'),
				'group' => $group=1,
				'disableClass' => 'rowNoEdit',
				'onExecute' => Api\Header\UserAgent::mobile()?'javaScript:app.resources.viewEntry':'',
				'mobileViewTemplate' => 'view?'.filemtime(Api\Etemplate\Widget\Template::rel2path('/resources/templates/mobile/view.xet'))
			),
			'add' => array(
				'caption' => 'New resource',
				'url' => 'menuaction=resources.resources_ui.edit&accessory_of=-1',
				'popup' => Link::get_registry('resources', 'add_popup'),
				'group' => $group,
				'hideOnMobile' => true
			),
			'view-acc' => array(
				'caption' => 'View accessories',
				'icon' => 'view_acc',
				'allowOnMultiple' => false,
				'url' => 'menuaction=resources.resources_ui.index&view_accs_of=$id',
				'group' => $group,
				'enableClass' => 'hasAccessories'
			),
			'new_accessory' => array(
				'caption' => 'New accessory',
				'icon' => 'new',
				'group' => $group,
				'url' => 'menuaction=resources.resources_ui.edit&res_id=0&accessory_of=$id',
				'popup' => Link::get_registry('resources', 'add_popup'),
				'disableClass' => 'no_new_accessory',
				'allowOnMultiple' => false
			),


			'select_all' => array(
				'caption' => 'Select all',
				'hint' => 'Apply the action on the whole query, NOT only the shown entries',
				'group' => ++$group,
			),
			'view-calendar' => array(
				'caption' => 'View calendar',
				'icon' => 'calendar/planner',
				'group' => ++$group,
				'allowOnMultiple' => true,
				'disableClass' => 'no_view_calendar',
				'onExecute' => 'javaScript:app.resources.view_calendar',
			),
			'book' => array(
				'caption' => 'Book resource',
				'icon' => 'navbar',
				'group' => $group,
				'allowOnMultiple' => true,
				'disableClass' => 'no_book',
				'onExecute' => 'javaScript:app.resources.book',
			),

			'delete' => array(
				'caption' => 'Delete',
				'group' => ++$group,
				'disableClass' => 'no_delete',
				'nm_action' => 'open_popup',
				'hideOnDisabled' => true
			),
			'restore' => array(
				'caption' => 'Un-delete',
				'icon' => 'revert',
				'enableClass' => 'deleted',
				'hideOnDisabled' => true,
				'nm_action' => 'open_popup',
				'group' => $group,
			)
		);
		return $actions;
	}

	/**
	 * apply an action to multiple timesheets
	 *
	 * @param string/int $action 'status_to',set status to timeshhets
	 * @param array $checked timesheet id's to use if !$use_all
	 * @param boolean $use_all if true use all timesheets of the current selection (in the session)
	 * @param int &$success number of succeded actions
	 * @param int &$failed number of failed actions (not enought permissions)
	 * @param string &$action_msg translated verb for the actions, to be used in a message like %1 timesheets 'deleted'
	 * @param string/array $session_name 'index' or 'email', or array with session-data depending if we are in the main list or the popup
	 * @return boolean true if all actions succeded, false otherwise
	 */
	function action($action,$checked,$use_all,&$success,&$failed,&$action_msg,$session_name,&$msg)
	{
		$success = $failed = 0;
		if ($use_all)
		{
			// get the whole selection
			$query = is_array($session_name) ? $session_name : Api\Cache::getSession($session_name, 'session_data');

			@set_time_limit(0);                     // switch off the execution time limit, as it's for big selections to small
			$query['num_rows'] = -1;        // all
			$this->bo->get_rows($query,$resources,$readonlys);
			$checked = array();
			foreach($resources as $resource)
			{
				$checked[] = $resource['res_id'];
			}
		}
		//echo __METHOD__."('$action', ".array2string($checked).', '.array2string($use_all).",,, '$session_name')";

		// Dialogs to get options
		list($action, $settings) = explode('_', $action, 2);

		switch($action)
		{
			case 'restore':
				$action_msg = lang('restored');
				foreach($checked as $n=>$id)
				{
					// Extra data
					if(!$id) continue;
					$resource = $this->bo->read($id);
					$resource['deleted'] = null;
					if($resource['accessory_of'] > 0)
					{
						/*
						If restoring an accessory, and parent is deleted, and not in
						the list of resources to be restored right now, un-parent
						*/
						$parent = $this->bo->read($resource['accessory_of']);
						$checked_key = array_search($parent['res_id'], $checked);
						if($checked_key === false && $parent['deleted'])
						{
							$resource['accessory_of'] = -1;
						}
					}

					$this->bo->save($resource);
					if($settings == 'accessories')
					{
						// Restore accessories too
						$accessories = $this->bo->get_acc_list($id,true);
						foreach($accessories as $acc_id => $name)
						{
							$acc = $this->bo->read($acc_id);
							$acc['deleted'] = null;
							$this->bo->save($acc);
							$restored_accessories++;
						}
					}
					$success++;
				}
				if($restored_accessories) $action_msg .= ", " . lang('%1 accessories restored',$restored_accessories);
				break;
			case 'delete':
				$action_msg = lang('deleted');
				$promoted_accessories = 0;
				foreach($checked as $n => &$id)
				{
					// Extra data
					if(!$id) continue;
					$resource = $this->bo->read($id);
					if($settings == 'promote')
					{
						// Handle a selected accessory
						if($resource['accessory_of'] > 0)
						{
							$resource['accessory_of'] = -1;
							$this->bo->save($resource);
							$promoted_accessories++;
							continue;
						}

						// Make associated accessories into resources - include deleted
						$accessories = $this->bo->get_acc_list($id,true);
						foreach($accessories as $acc_id => $name)
						{
							$acc = $this->bo->read($acc_id);
							$acc['accessory_of'] = -1;
							// Restore them if deleted
							$acc['deleted'] = null;
							$this->bo->save($acc);
							$promoted_accessories++;

							// Don't need to process these ones now
							$checked_key = array_search($acc_id, $checked);
							if($checked_key !== false) unset($checked[$checked_key]);
						}
					}
					else
					{
						// Remove checked accessories, deleting resource will remove them
						// We get an error if we try to delete them after they're gone
						$accessories = $this->bo->get_acc_list($id,$resource['deleted']);

						foreach($accessories as $acc_id => $name)
						{
							$checked_key = array_search($acc_id, $checked);
							if($checked_key !== false)
							{
								$success++;
								unset($checked[$checked_key]);
							}
						}
					}
					$error = $this->bo->delete($id);
					if (!$error)
					{
						$success++;
					}
					else
					{
						$msg = $error . "\n";
						$failed++;
					}
				}
				if($promoted_accessories) $action_msg .= ", " . lang('%1 accessories now resources',$promoted_accessories);
				break;
		}
		return $failed == 0;
	}

	/**
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * invokes add or edit dialog for resources
	 *
	 * @param $content   Content from the eTemplate Exec call or id on inital call
	 */
	function edit($content=0,$accessory_of = -1)
	{
		if (is_array($content))
		{
			$button = @key($content['button']);
			unset($content['button']);
			switch($button)
			{
				case 'save':
				case 'apply':
					unset($content['save']);
					unset($content['apply']);
// 					if($content['id'] != 0)
// 					{
// 						// links are already saved by eTemplate
// 						unset($resource['link_to']['to_id']);
// 					}
					if($content['res_id'])
					{
						 $acc_count = count($this->bo->get_acc_list($content['res_id']));
					}
					$result = $this->bo->save($content);

					if(is_numeric($result))
					{
						$msg = lang('Resource %1 saved!',$result);
						$content['res_id'] = $result;
						if($acc_count && $content['accessory_of'] != -1)
						{
							// Resource with accessories changed into accessory
							if($acc_count) $msg = lang('%1 accessories now resources',$acc_count);
						}
					}
					else
					{
						$msg = $result;
					}

					break;
				case 'delete':
					unset($content['delete']);
					if(!$this->bo->delete($content['res_id']))
					{
						$msg = lang('Resource %1 deleted!',$content['res_id']);
					}
					else
					{
						$msg = lang('Resource %1 faild to be deleted!', $content['res_id']);
						break;
					}
			}
			Framework::refresh_opener($msg, 'resources',$content['res_id'],($button == 'delete'?'delete':'edit'));

			if($button != 'apply')
			{
				Framework::window_close();
			}

		}

		$nm_session_data = Api\Cache::getSession('resources', 'index_nm');
		$res_id = is_numeric($content) ? (int)$content : $content['res_id'];
		if (isset($_GET['res_id'])) $res_id = $_GET['res_id'];
		if (isset($nm_session_data['filter2']) && $nm_session_data['filter2'] > 0) $accessory_of = $nm_session_data['filter2'];
		if (isset($_GET['accessory_of'])) $accessory_of = $_GET['accessory_of'];
		$content = array('res_id' => $res_id);
		if ($res_id > 0)
		{
			$content = $this->bo->read($res_id);
			$content['picture_src'] = strpos($content['picture_src'],'.') !== false ? 'gen_src' : $content['picture_src'];
			$content['link_to'] = array(
				'to_id' => $res_id,
				'to_app' => 'resources'
			);
		} elseif ($accessory_of > 0) {
			// Pre-set according to parent
			$owner = $this->bo->read($accessory_of);
			if($owner['accessory_of'] > 0)
			{
				// Accessory of accessory not allowed, grab parent resource
				$accessory_of = $owner['accessory_of'];
				$owner = $this->bo->read($accessory_of);
			}
			$content['cat_id'] = $owner['cat_id'];
			$content['bookable'] = true;
		} else {
			// New resource
			$content['cat_id'] = $nm_session_data['filter'];
			$content['bookable'] = true;
		}
		if($msg) {
			$content['msg'] = $msg;
		}

		if ($_GET['msg']) $content['msg'] = strip_tags($_GET['msg']);

		// some presetes
		$content['resource_picture'] = $this->bo->get_picture($content['res_id'],false);
		// Set original size picture
		$content['picture_original'] = $content['picture_src'] == 'own_src'?
				'webdav.php/apps/resources/'.$content['res_id'].'/.picture.jpg': $this->bo->get_picture($content['res_id'],true);

		$content['quantity'] = $content['quantity'] ? $content['quantity'] : 1;
		$content['useable'] = $content['useable'] ? $content['useable'] : 1;
		$content['accessory_of'] = $content['accessory_of'] ? $content['accessory_of'] : $accessory_of;

		if($content['res_id'] && $content['accessory_of'] == -1)
		{
			$content['acc_count'] = count($this->bo->get_acc_list($content['res_id']));
		}
		$sel_options['status'] = resources_bo::$field2label;

		//$sel_options['gen_src_list'] = $this->bo->get_genpicturelist();
		$sel_options['cat_id'] =  $this->bo->acl->get_cats(Acl::ADD);
		$sel_options['cat_id'] = count($sel_options['cat_id']) == 1 ? $sel_options['cat_id'] :
			array('' => lang('select one')) + $sel_options['cat_id'];
		if($accessory_of > 0 || $content['accessory_of'] > 0)
		{
			$content['accessory_of'] = $content['accessory_of'] ? $content['accessory_of'] : $accessory_of;
		}
		$search_options = array('accessory_of' => -1);

		$content['history'] = array(
			'id' => $res_id,
			'app' => 'resources',
			'status-widgets' => array(
				'accessory_of' => $sel_options['accessory_of'],
				'long_description' => 'html'
			)
		);

		$sel_options['accessory_of'] = array(-1 => lang('none')) + (array)$this->bo->link_query('',$search_options);
		if($res_id) unset($sel_options['accessory_of'][$res_id]);

// 		$content['general|page|pictures|links'] = 'resources.edit_tabs.page';  //debug

		// Permissions
		$read_only = array();
		if($res_id && !$this->bo->acl->is_permitted($content['cat_id'],Acl::EDIT))
		{
			$read_only['__ALL__'] = true;
		}
		$config = Api\Config::read('resources');
		if(!$this->bo->acl->is_permitted($content['cat_id'],Acl::DELETE) ||
			($content['deleted'] && !$GLOBALS['egw_info']['user']['apps']['admin'] && $config['history'] == 'history'))
		{
			$read_only['delete'] = true;
		}

		// Can't make a resource with accessories an accessory
		$read_only['accessory_of'] = $content['acc_count'];
		if($read_only['accessory_of'])
		{
			$content['accessory_label'] = lang('Remove accessories before changing Accessory of');
		}

		// Disable custom tab if there are no custom fields defined
		$read_only['tabs']['custom'] = !(Api\Storage\Customfields::get('resources',true));
		$read_only['tabs']['history'] = ($content['history']['id'] != 0?false:true);

		$preserv = $content;

		$this->tmpl->read('resources.edit');
		return $this->tmpl->exec('resources.resources_ui.edit',$content,$sel_options,$read_only,$preserv,2);
	}

}

