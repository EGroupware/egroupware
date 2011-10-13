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
 * ACL userinterface object for resources
 *
 * @package resources
 */
class ui_acl
{
	var $start = 0;
	var $query = '';
	var $sort  = '';
	var $order = '';
	var $bo;
	var $nextmatchs = '';
	var $rights;
	var $public_functions = array(
		'acllist' 	=> True,
		);

	function ui_acl()
	{
		$this->bo = createobject('resources.bo_acl',True);
		$this->nextmatchs = createobject('phpgwapi.nextmatchs');
		$this->start = $this->bo->start;
		$this->query = $this->bo->query;
		$this->order = $this->bo->order;
		$this->sort = $this->bo->sort;
		$this->cat_id = $this->bo->cat_id;
	}

	function acllist()
	{
		if (!$GLOBALS['egw']->acl->check('run',1,'admin'))
		{
			$this->deny();
		}

		if ($_POST['btnDone'])
		{
			egw::redirect_link('/admin/index.php');
		}

		common::egw_header();
		echo parse_navbar();

		if ($_POST['btnSave'])
		{
			foreach($_POST['catids'] as $cat_id)
			{
				$this->bo->set_rights($cat_id,$_POST['inputread'][$cat_id],$_POST['inputwrite'][$cat_id],
					$_POST['inputcalread'][$cat_id],$_POST['inputcalbook'][$cat_id],$_POST['inputadmin'][$cat_id]);
			}
			config::save_value('location_cats', implode(',', $_POST['location_cats']), 'resources');
		}
		$template            =& CreateObject('phpgwapi.Template',EGW_APP_TPL);
		$template->set_file(array('acl' => 'acl.tpl'));
		$template->set_block('acl','cat_list','Cblock');
		$template->set_var(array(
			'title' => $GLOBALS['egw_info']['apps']['resources']['title'] . ' - ' . lang('Configure Access Permissions'),
			'lang_search' => lang('Search'),
			'lang_save' => lang('Save'),
			'lang_done' => lang('Done'),
			'lang_read' => lang('Read permissions'),
			'lang_write' => lang('Write permissions'),
			'lang_implies_read' => lang('implies read permission'),
			'lang_calread' => lang('Read Calendar permissions'),
			'lang_calbook' => lang('Direct booking permissions'),
			'lang_implies_book' => lang('implies booking permission'),
			'lang_cat_admin' => lang('Categories admin'),
			'lang_locations_rooms' => lang('Locations / rooms'),
		));

		$left  = $this->nextmatchs->left('/index.php',$this->start,$this->bo->catbo->total_records,'menuaction=resources.ui_acl.acllist');
		$right = $this->nextmatchs->right('/index.php',$this->start,$this->bo->catbo->total_records,'menuaction=resources.ui_acl.acllist');

		$template->set_var(array(
			'left' => $left,
			'right' => $right,
			'lang_showing' => $this->nextmatchs->show_hits($this->bo->catbo->total_records,$this->start),
			'th_bg' => $GLOBALS['egw_info']['theme']['th_bg'],
			'sort_cat' => $this->nextmatchs->show_sort_order(
				$this->sort,'cat_name','cat_name','/index.php',lang('Category'),'&menuaction=resources.ui_acl.acllist'
			),
			'query' => $this->query,
		));

		if ($this->bo->cats)
		{
			$config = config::read('resources');
			$location_cats = $config['location_cats'] ? explode(',', $config['location_cats']) : array();
			foreach($this->bo->cats as $cat)
			{
				$this->rights = $this->bo->get_rights($cat['id']);

				$tr_color = $this->nextmatchs->alternate_row_color($tr_color);
				$template->set_var(array(
					'tr_color' => $tr_color,
					'catname' => $cat['name'],
					'catid' => $cat['id'],
					'read' => $this->selectlist(EGW_ACL_READ),
					'write' => $this->selectlist(EGW_ACL_ADD),
					'calread' => $this->selectlist(EGW_ACL_CALREAD),
					'calbook' =>$this->selectlist(EGW_ACL_DIRECT_BOOKING),
					'admin' => '<option value="" selected="1">'.lang('choose categories admin').'</option>'.$this->selectlist(EGW_ACL_CAT_ADMIN,true),
					'location_checked' => in_array($cat['id'], $location_cats) ? 'checked="1"' : '',
				));
				$template->parse('Cblock','cat_list',True);
			}
		}
		$template->pfp('out','acl',True);
	}

	function selectlist($right,$users_only=false)
	{
		switch($GLOBALS['egw_info']['user']['preferences']['common']['account_display'])
		{
			case 'firstname':
			case 'firstall':
				$order = 'n_given,n_family';
				break;
			case 'lastall':
			case 'lastname':
				$order = 'n_family,n_given';
				break;
			default:
				$order = 'account_lid,n_family,n_given';
				break;
		}
		foreach ($GLOBALS['egw']->accounts->search(array(
			'type' => 'both',
			'order' => $order,
		)) as $num => $account)
		{
			if(!($users_only && $account['account_type'] == 'g'))
			{
				$selectlist .= '<option value="' . $account['account_id'] . '"';
				if($this->rights[$account['account_id']] & $right)
				{
					$selectlist .= ' selected="selected"';
				}
				$selectlist .= '>' . common::display_fullname($account['account_lid'],$account['account_firstname'],
					$account['account_lastname'],$account['account_id']) . '</option>' . "\n";
			}
		}
		return $selectlist;
	}

	function deny()
	{
		echo '<p><center><b>'.lang('Access not permitted').'</b></center>';
		common::egw_exit(True);
	}
}
