<?php
/**
 * EGgroupware admin - Deny access
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Framework;


/**
 * Deny access to certain parts of admin
 */
class admin_denyaccess
{
	var $template;
	var $nextmatchs;
	var $public_functions = array(
		'list_apps'    => True,
		'access_form'  => True,
		'account_list' => True
	);

	function __construct()
	{
		$this->account_id = (int)$_GET['account_id'];
		if (!$this->account_id || $GLOBALS['egw']->acl->check('account_access',64,'admin'))
		{
			$GLOBALS['egw']->redirect_link('/index.php');
		}
		$this->template = new Framework\Template(Framework\Template::get_dir('admin'));
	}

	function common_header()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Admin') . ' - ' . lang('ACL Manager') .
			': ' . Api\Accounts::username($this->account_id);
		echo $GLOBALS['egw']->framework->header();
	}

	function list_apps()
	{
		$this->common_header();

		Api\Hooks::process('acl_manager',array('preferences'));

		$this->template->set_file(array(
			'app_list'   => 'acl_applist.tpl'
		));
		$this->template->set_block('app_list','list');
		$this->template->set_block('app_list','app_row');
		$this->template->set_block('app_list','app_row_noicon');
		$this->template->set_block('app_list','link_row');
		$this->template->set_block('app_list','spacer_row');

		if (is_array($GLOBALS['acl_manager']))
		{
			foreach($GLOBALS['acl_manager'] as $app => $locations)
			{
				$icon = Api\Image::find($app,array('navbar.png',$app.'png','navbar.gif',$app.'.gif'));
				$this->template->set_var('icon_backcolor',$GLOBALS['egw_info']['theme']['row_off']);
				$this->template->set_var('link_backcolor',$GLOBALS['egw_info']['theme']['row_off']);
				$this->template->set_var('app_name',$GLOBALS['egw_info']['apps'][$app]['title']);
				$this->template->set_var('app_icon',$icon);

				if ($icon)
				{
					$this->template->fp('rows','app_row',True);
				}
				else
				{
					$this->template->fp('rows','app_row_noicon',True);
				}

				if (is_array($locations))
				{
					foreach($locations as $loc => $value)
					{
						$link_values = array(
							'menuaction' => 'admin.admin_denyaccess.access_form',
							'location'   => $loc,
							'acl_app'    => $app,
							'account_id' => $this->account_id
						);

						$this->template->set_var('link_location',$GLOBALS['egw']->link('/index.php',$link_values));
						$this->template->set_var('lang_location',lang($value['name']));
						$this->template->fp('rows','link_row',True);
					}
				}

				$this->template->parse('rows','spacer_row',True);
			}
		}
		$this->template->set_var(array(
			'cancel_action' => $GLOBALS['egw']->link('/admin/index.php'),
			'lang_cancel'   => lang('Cancel')
		));
		$this->template->pfp('out','list');
		echo $GLOBALS['egw']->framework->footer();
	}

	function access_form()
	{
		$location = $_GET['location'];

		// for POST (not GET or cli call via setup_cmd_admin) validate CSRF token
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			Api\Csrf::validate($_POST['csrf_token'], __FILE__);
		}
		if ($_POST['submit'] || $_POST['cancel'])
		{
			if ($_POST['submit'])
			{
				$total_rights = 0;
				if (is_array($_POST['acl_rights']))
				{
					foreach($_POST['acl_rights'] as $rights)
					{
						$total_rights += $rights;
					}
				}
				if ($total_rights)
				{
					$GLOBALS['egw']->acl->add_repository($_GET['acl_app'], $location, $this->account_id, $total_rights);
				}
				else	// we dont need to save 0 rights (= no restrictions)
				{
					$GLOBALS['egw']->acl->delete_repository($_GET['acl_app'], $location, $this->account_id);
				}
			}
			$this->list_apps();
			return;
		}
		Api\Hooks::single('acl_manager',$_GET['acl_app']);
		$acl_manager = $GLOBALS['acl_manager'][$_GET['acl_app']][$location];

		$this->common_header();
		$this->template->set_file('form','acl_manager_form.tpl');
		$this->template->set_var('csrf_token', Api\Csrf::token(__FILE__));

		$afn = Api\Accounts::username($this->account_id);

		$this->template->set_var('lang_message',lang('Check items to <b>%1</b> to %2 for %3',lang($acl_manager['name']),$GLOBALS['egw_info']['apps'][$_GET['acl_app']]['title'],$afn));
		$link_values = array(
			'menuaction' => 'admin.admin_denyaccess.access_form',
			'acl_app'    => $_GET['acl_app'],
			'location'   => urlencode($_GET['location']),
			'account_id' => $this->account_id
		);

		$acl    = new Api\Acl($this->account_id);
		$acl->read_repository();
		$grants = $acl->get_rights($location,$_GET['acl_app']);

		$this->template->set_var('form_action',$GLOBALS['egw']->link('/index.php',$link_values));

		foreach($acl_manager['rights'] as $name => $value)
		{
			$cb .= '<input type="checkbox" name="acl_rights[]" value="'.$value.'"'.($grants & $value ? ' checked' : '').'>&nbsp;'.lang($name)."<br>\n";
		}
		$this->template->set_var('select_values',$cb);
		$this->template->set_var('lang_submit',lang('Save'));
		$this->template->set_var('lang_cancel',lang('Cancel'));

		$this->template->pfp('out','form');
		echo $GLOBALS['egw']->framework->footer();
	}
}
