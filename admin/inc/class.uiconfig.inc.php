<?php
  /**************************************************************************\
  * phpGroupWare - Admin config                                              *
  * Written by Miles Lott <milosch@phpgroupware.org>                         *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uiconfig
	{
		var $public_functions = array('index' => True);

		function index()
		{
			$referer = urldecode($GLOBALS['HTTP_GET_VARS']['referer']);

			if($referer)
			{
				$_redir = $referer;
				$GLOBALS['phpgw']->session->appsession('session_data','admin_config',$referer);
			}
			else
			{
				$referer = $GLOBALS['phpgw']->session->appsession('session_data','admin_config');
				if($referer == '-1')
				{
					$referer = '';
				}
				$_redir  = $referer ? $referer : $GLOBALS['phpgw']->link('/admin/index.php');
			}

			switch($GLOBALS['HTTP_GET_VARS']['appname'])
			{
				case 'admin':
				case 'preferences':
					$appname = 'preferences';
					$config_appname = 'phpgwapi';
					break;
				case 'addressbook':
				case 'calendar':
				case 'email':
				case 'nntp':
					/*
					Other special apps can go here for now, e.g.:
					case 'bogusappname':
					*/
					$appname = $GLOBALS['HTTP_GET_VARS']['appname'];
					$config_appname = 'phpgwapi';
					break;
				case 'phpgwapi':
				case '':
					/* This keeps the admin from getting into what is a setup-only config */
					Header('Location: ' . $_redir);
					break;
				default:
					$appname = $GLOBALS['HTTP_GET_VARS']['appname'];
					$config_appname = $appname;
					break;
			}

			$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir($appname));
			$t->set_unknowns('keep');
			$t->set_file(array('config' => 'config.tpl'));
			$t->set_block('config','header','header');
			$t->set_block('config','body','body');
			$t->set_block('config','footer','footer');

			$c = CreateObject('phpgwapi.config',$config_appname);
			$c->read_repository();

			if ($c->config_data)
			{
				$current_config = $c->config_data;
			}

			if ($GLOBALS['HTTP_POST_VARS']['cancel'])
			{
				Header('Location: ' . $_redir);
			}

			if ($GLOBALS['HTTP_POST_VARS']['submit'])
			{
				/* Load hook file with functions to validate each config (one/none/all) */
				$GLOBALS['phpgw']->hooks->single('config_validate',$appname);

				while (list($key,$config) = each($GLOBALS['HTTP_POST_VARS']['newsettings']))
				{
					if ($config)
					{
						if($GLOBALS['phpgw_info']['server']['found_validation_hook'] && function_exists($key))
						{
							call_user_func($key,$config);
							if($GLOBALS['config_error'])
							{
								$errors .= lang($GLOBALS['config_error']) . '&nbsp;';
								$GLOBALS['config_error'] = False;
							}
							else
							{
								$c->config_data[$key] = $config;
							}
						}
						else
						{
							$c->config_data[$key] = $config;
						}
					}
					else
					{
						/* don't erase passwords, since we also don't print them */
						if(!ereg('passwd',$key) && !ereg('password',$key) && !ereg('root_pw',$key))
						{
							unset($c->config_data[$key]);
						}
					}
				}
				if($GLOBALS['phpgw_info']['server']['found_validation_hook'] && function_exists('final_validation'))
				{
					final_validation($newsettings);
					if($GLOBALS['config_error'])
					{
						$errors .= lang($GLOBALS['config_error']) . '&nbsp;';
						$GLOBALS['config_error'] = False;
					}
					unset($GLOBALS['phpgw_info']['server']['found_validation_hook']);
				}

				$c->save_repository(True);

				if(!$errors)
				{
					$GLOBALS['phpgw']->session->appsession('session_data','admin_config',-1);
					Header('Location: ' . $_redir);
					$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
					exit;
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
			}

			$GLOBALS['phpgw']->common->phpgw_header();

			$t->set_var('title',lang('Site Configuration'));
			$t->set_var('action_url',$GLOBALS['phpgw']->link('/index.php','menuaction=admin.uiconfig.index&appname=' . $appname));
			$t->pparse('out','header');

			$vars = $t->get_undefined('body');

			$GLOBALS['phpgw']->hooks->single('config',$appname);

			while (list($null,$value) = each($vars))
			{
				$valarray = explode('_',$value);
				$type = $valarray[0];
				$new = $newval = '';

				while($chunk = next($valarray))
				{
					$new[] = $chunk;
				}
				$newval = implode(' ',$new);

				switch($type)
				{
					case 'lang':
						$t->set_var($value,lang($newval));
						break;
					case 'value':
						$newval = ereg_replace(' ','_',$newval);
						/* Don't show passwords in the form */
						if(ereg('passwd',$value) || ereg('password',$value) || ereg('root_pw',$value))
						{
							$t->set_var($value,'');
						}
						else
						{
							$t->set_var($value,$current_config[$newval]);
						}
						break;
					case 'checked':
						/* '+' is used as a delimiter for the check value */
						list($newvalue,$check) = split('\+',$newval);
						$newval = ereg_replace(' ','_',$newvalue);
						if($current_config[$newval] == $check)
						{
							$t->set_var($value, ' checked');
						}
						else
						{
							$t->set_var($value, '');
						}
						break;
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
						/* echo $config . '=' . $current_config[$config]; */
						if ($current_config[$config] == $setting)
						{
							$t->set_var($value,' selected');
						}
						else
						{
							$t->set_var($value,'');
						}
						break;
					case 'hook':
						$newval = ereg_replace(' ','_',$newval);
						if(function_exists($newval))
						{
							$t->set_var($value,$newval($current_config));
						}
						else
						{
							$t->set_var($value,'');
						}
						break;
					default:
						$t->set_var($value,'');
						break;
				}
			}

			$t->pfp('out','body');

			$t->set_var('lang_submit', lang('submit'));
			$t->set_var('lang_cancel', lang('cancel'));
			$t->pfp('out','footer');
		}
	}
?>
