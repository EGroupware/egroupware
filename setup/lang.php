<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	if (!$included)
	{
		$phpgw_info['flags'] = array(
			'noheader' => True,
			'nonavbar' => True,
			'currentapp' => 'home',
			'noapi' => True
		);
		include('./inc/functions.inc.php');
		include('../header.inc.php');
		// Authorize the user to use setup app and load the database
		// Does not return unless user is authorized
		if (!$phpgw_setup->auth('Config'))
		{
			Header('Location: index.php');
			exit;
		}
		$phpgw_setup->loaddb();

		include(PHPGW_API_INC.'/class.common.inc.php');
		$common = new common;
		// this is not used
		//$sep = $common->filesystem_separator();
	}
	else
	{
		$newinstall          = True;
		$lang_selected['en'] = 'en';
		$submit              = True;
	}

	if ($HTTP_POST_VARS['submit'])
	{
		$lang_selected = $HTTP_POST_VARS['lang_selected'];
		$upgrademethod = $HTTP_POST_VARS['upgrademethod'];
		$phpgw_setup->db->transaction_begin();
		if (count($lang_selected))
		{
			if ($upgrademethod == 'dumpold')
			{
				$phpgw_setup->db->query("DELETE FROM lang",__LINE__,__FILE__);
				//echo '<br>Test: dumpold';
			}
			while (list($null,$lang) = each($lang_selected))
			{
				//echo '<br>Working on: ' . $lang;
				$addlang = False;
				if ($upgrademethod == 'addonlynew')
				{
					//echo "<br>Test: addonlynew - select count(*) from lang where lang='$lang'";
					$phpgw_setup->db->query("SELECT COUNT(*) FROM lang WHERE lang='$lang'",__LINE__,__FILE__);
					$phpgw_setup->db->next_record();

					if ($phpgw_setup->db->f(0) == 0)
					{
						//echo '<br>Test: addonlynew - True';
						$addlang = True;
					}
				}
				if (($addlang && $upgrademethod == 'addonlynew') || ($upgrademethod != 'addonlynew'))
				{
					//echo '<br>Test: loop above file()';
					$setup_info = $phpgw_setup->get_versions();
					$setup_info = $phpgw_setup->get_db_versions($setup_info);
					$raw = $raw_file = array();
					// Visit each app/setup dir, look for a lang file
					while (list($key,$app) = each($setup_info))
					{
						$appfile = PHPGW_SERVER_ROOT . SEP . $app['name'] . SEP . 'setup' . SEP . 'phpgw_' . strtolower($lang) . '.lang';
						//echo '<br>Checking in: ' . $app['name'];
						if($phpgw_setup->app_registered($app['name']) && file_exists($appfile))
						{
							//echo '<br>Including: ' . $appfile;
							$raw[] = file($appfile);
						}
					}
					@reset($raw);
					while (list($a,$raw_file) = @each($raw))
					{
						while (list($null,$line) = @each($raw_file))
						{
							$addit = False;
							list($message_id,$app_name,$phpgw_setup->db_lang,$content) = explode("\t",$line);
							$message_id = $phpgw_setup->db->db_addslashes(chop($message_id));
							//echo '<br>APPNAME:' . $app_name . ' PHRASE:' . $message_id;
							$app_name   = $phpgw_setup->db->db_addslashes(chop($app_name));
							$phpgw_setup->db_lang    = $phpgw_setup->db->db_addslashes(chop($phpgw_setup->db_lang));
							$content    = $phpgw_setup->db->db_addslashes(chop($content));
							if ($upgrademethod == 'addmissing')
							{
								//echo '<br>Test: addmissing';
								$phpgw_setup->db->query("SELECT COUNT(*) FROM lang WHERE message_id='$message_id' and lang='$phpgw_setup->db_lang'",__LINE__,__FILE__);
								$phpgw_setup->db->next_record();

								if ($phpgw_setup->db->f(0) == 0)
								{
									//echo '<br>Test: addmissing - True - Total: ' . $phpgw_setup->db->f(0);
									$addit = True;
								}
							}

							if ($addit || ($upgrademethod == 'dumpold' || $newinstall || $upgrademethod == 'addonlynew'))
							{
								if($message_id && $content)
								{
									//echo "<br>adding - insert into lang values ('$message_id','$app_name','$phpgw_setup->db_lang','$content')";
									$phpgw_setup->db->query("INSERT into lang VALUES ('$message_id','$app_name','$phpgw_setup->db_lang','$content')",__LINE__,__FILE__);
								}
							}
						}
					}
				}
			}
			$phpgw_setup->db->transaction_commit();
		}

		if (! $included)
		{
			Header('Location: index.php');
			exit;
		}

	}
	else
	{
		if ($HTTP_POST_VARS['cancel'])
		{
			Header('Location: index.php');
			exit;
		}

		if (!$included)
		{
			$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
			$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
			$setup_tpl->set_file(array(
				'T_head' => 'head.tpl',
				'T_footer' => 'footer.tpl',
				'T_alert_msg' => 'msg_alert_msg.tpl',
				'T_lang_main' => 'lang_main.tpl'
			));

			$setup_tpl->set_block('T_lang_main','B_choose_method','V_choose_method');

			$stage_title = lang('Multi-Language support setup');
			$stage_desc  = lang('This program will help you upgrade or install different languages for phpGroupWare');
			$tbl_width   = $newinstall ? '60%' : '80%';
			$td_colspan  = $newinstall ? '1' : '2';
			$td_align    = $newinstall ? ' align="center"' : '';
			$hidden_var1 = $newinstall ? '<input type="hidden" name="newinstall" value="True">' : '';

			$select_box_desc = lang('Select which languages you would like to use');
			$select_box = '';
			$phpgw_setup->db->query("select lang_id,lang_name from languages where available='Yes'");
			while ($phpgw_setup->db->next_record())
			{
				$select_box_langs = 
					$select_box_langs 
					.'<option value="' . $phpgw_setup->db->f('lang_id') . '">'
					. $phpgw_setup->db->f('lang_name') . '</option>'
					."\n";
			}

			if (! $newinstall)
			{
				$meth_desc = lang('Select which method of upgrade you would like to do');
				$blurb_addonlynew = lang('Only add languages that are not in the database already');
				$blurb_addmissing = lang('Only add new phrases');
				$blurb_dumpold = lang('Delete all old languages and install new ones');

				$setup_tpl->set_var('meth_desc',$meth_desc);
				$setup_tpl->set_var('blurb_addonlynew',$blurb_addonlynew);
				$setup_tpl->set_var('blurb_addmissing',$blurb_addmissing);
				$setup_tpl->set_var('blurb_dumpold',$blurb_dumpold);
				$setup_tpl->parse('V_choose_method','B_choose_method');
			}
			else
			{
				$setup_tpl->set_var('V_choose_method','');
			}

			$setup_tpl->set_var('stage_title',$stage_title);
			$setup_tpl->set_var('stage_desc',$stage_desc);
			$setup_tpl->set_var('tbl_width',$tbl_width);
			$setup_tpl->set_var('td_colspan',$td_colspan);
			$setup_tpl->set_var('td_align',$td_align);
			$setup_tpl->set_var('hidden_var1',$hidden_var1);
			$setup_tpl->set_var('select_box_desc',$select_box_desc);
			$setup_tpl->set_var('select_box_langs',$select_box_langs);

			$setup_tpl->set_var('lang_install',lang('install'));
			$setup_tpl->set_var('lang_cancel',lang('cancel'));

			// True = do not show logout link in top right corner
			$phpgw_setup->show_header("$stage_title",True,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]['db_type'] . ')');
			$setup_tpl->pparse('out','T_lang_main');
			$phpgw_setup->show_footer();
		}
	}
?>
