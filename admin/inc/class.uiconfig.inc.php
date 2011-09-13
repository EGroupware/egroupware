<?php
/**
 * EGgroupware admin - site configuration
 *
 * @link http://www.egroupware.org
 * @author Miles Lott <milos@groupwhere.org>
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Site configuration for all apps using an $app/templates/default/config.tpl
 */
class uiconfig
{
	var $public_functions = array('index' => True);

	function index()
	{
		if ($GLOBALS['egw']->acl->check('site_config_access',1,'admin'))
		{
			egw::redirect_link('/index.php');
		}
		$referer = $_POST['submit'] || $_POST['cancel'] ? $_POST['referer'] :
			common::get_referer('/admin/index.php',$_POST['referer']);
		list(,$show_app) = explode('/',$referer);
		if (!$show_app) $show_app = 'admin';

		// load the translations of the app we show too, so they dont need to be in admin!
		if ($_GET['appname'] != 'admin')
		{
			translation::add_app($_GET['appname']);
		}

		if(get_magic_quotes_gpc() && is_array($_POST['newsettings']))
		{
			$_POST['newsettings'] = array_stripslashes($_POST['newsettings']);
		}

		switch($_GET['appname'])
		{
			case 'admin':
			case 'addressbook':
			case 'calendar':
			case 'email':
			case 'nntp':
				/*
				Other special apps can go here for now, e.g.:
				case 'bogusappname':
				*/
				$appname = $_GET['appname'];
				$config_appname = 'phpgwapi';
				break;
			case 'phpgwapi':
			case '':
				/* This keeps the admin from getting into what is a setup-only config */
				egw::redirect_link('/admin/index.php');
				break;
			default:
				$appname = $_GET['appname'];
				$config_appname = $appname;
				break;
		}
		$t = new Template(common::get_tpl_dir($appname));
		$t->set_unknowns('keep');
		$t->set_file(array('config' => 'config.tpl'));
		$t->set_block('config','header','header');
		$t->set_block('config','body','body');
		$t->set_block('config','footer','footer');

		$c = new config($config_appname);
		$c->read_repository();

		if ($_POST['cancel'] || $_POST['submit'] && $GLOBALS['egw']->acl->check('site_config_access',2,'admin'))
		{
			egw::redirect_link($referer);
		}

		if ($_POST['submit'])
		{
			/* Load hook file with functions to validate each config (one/none/all) */
			$GLOBALS['egw']->hooks->single('config_validate',$appname);

			foreach($_POST['newsettings'] as $key => $config)
			{
				if ($config)
				{
					$c->config_data[$key] = $config;
					if($GLOBALS['egw_info']['server']['found_validation_hook'] && function_exists($key))
					{
						call_user_func($key,$config);
						if($GLOBALS['config_error'])
						{
							$errors .= lang($GLOBALS['config_error']) . '&nbsp;';
							$GLOBALS['config_error'] = False;
						}
					}
				}
				/* don't erase passwords, since we also don't print them */
				elseif(strpos($key,'passwd') === false && strpos($key,'password') === false && strpos($key,'root_pw') === false)
				{
					unset($c->config_data[$key]);
				}
			}
			if($GLOBALS['egw_info']['server']['found_validation_hook'] && function_exists('final_validation'))
			{
				final_validation($_POST['newsettings']);
				if($GLOBALS['config_error'])
				{
					$errors .= lang($GLOBALS['config_error']) . '&nbsp;';
					$GLOBALS['config_error'] = False;
				}
				unset($GLOBALS['egw_info']['server']['found_validation_hook']);
			}

			$c->save_repository();

			if(!$errors)
			{
				egw::redirect_link($referer);
			}
		}

		if($errors)
		{
			$t->set_var('error',lang('Error') . ': ' . $errors);
			$t->set_var('th_err','#FF8888');
			unset($errors);
			unset($GLOBALS['config_error']);
		}
		else
		{
			$t->set_var('error','');
			$t->set_var('th_err',$GLOBALS['egw_info']['theme']['th_bg']);
		}
		$t->set_var('title',lang('Site Configuration'));
		$t->set_var('action_url',$GLOBALS['egw']->link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname));
		$t->set_var('th_bg',     $GLOBALS['egw_info']['theme']['th_bg']);
		$t->set_var('th_text',   $GLOBALS['egw_info']['theme']['th_text']);
		$t->set_var('row_on',    $GLOBALS['egw_info']['theme']['row_on']);
		$t->set_var('row_off',   $GLOBALS['egw_info']['theme']['row_off']);
		$t->set_var('hidden_vars','<input type="hidden" name="referer" value="'.$referer.'">');

		$vars = $t->get_undefined('body');

		if ($GLOBALS['egw']->hooks->single('config',$appname))	// reload the config-values, they might have changed
		{
			$c->read_repository();
		}
		foreach($vars as $value)
		{
			$valarray = explode('_',$value);
			$type = array_shift($valarray);
			$newval = implode(' ',$valarray);

			switch ($type)
			{
				case 'lang':
					$t->set_var($value,lang($newval));
					break;
				case 'value':
					$newval = str_replace(' ','_',$newval);
					/* Don't show passwords in the form */
					if(strpos($value,'passwd') !== false || strpos($value,'password') !== false || strpos($value,'root_pw') !== false)
					{
						$t->set_var($value,'');
					}
					else
					{
						$t->set_var($value,$c->config_data[$newval]);
					}
					break;
				/*
				case 'checked':
					$newval = str_replace(' ','_',$newval);
					if ($c->config_data[$newval])
					{
						$t->set_var($value,' checked');
					}
					else
					{
						$t->set_var($value,'');
					}
					break;
				*/
				case 'selected':
					$configs = array();
					$config  = '';
					$newvals = explode(' ',$newval);
					$setting = end($newvals);
					for ($i=0;$i<(count($newvals) - 1); $i++)
					{
						$configs[] = $newvals[$i];
					}
					$config = implode('_',$configs);
					/* echo $config . '=' . $c->config_data[$config]; */
					if ($c->config_data[$config] == $setting)
					{
						$t->set_var($value,' selected');
					}
					else
					{
						$t->set_var($value,'');
					}
					break;
				case 'hook':
					$newval = str_replace(' ','_',$newval);
					if(function_exists($newval))
					{
						$t->set_var($value,$newval($c->config_data));
					}
					else
					{
						$t->set_var($value,'');
					}
					break;
				case 'call':	// eg. call_class::method or call_app.class.method
					$newval = str_replace(' ','_',$newval);
					$t->set_var($value,ExecMethod($newval,$c->config_data));
					break;
				default:
					$t->set_var($value,'');
					break;
			}
		}
		$t->set_var('lang_submit', $GLOBALS['egw']->acl->check('site_config_access',2,'admin') ? lang('Cancel') : lang('Save'));
		$t->set_var('lang_cancel', lang('Cancel'));

		// set currentapp to our calling app, to show the right sidebox-menu
		$GLOBALS['egw_info']['flags']['currentapp'] = $show_app;

		// render the page
		$GLOBALS['egw']->framework->render(
			$t->parse('out','header').
			$t->fp('out','body').
			$t->fp('out','footer'),
			null,true
		);
	}
}
