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

	$phpgw_info = array();
	if (!$included)
	{
		$GLOBALS['phpgw_info']['flags'] = array(
			'noheader' => True,
			'nonavbar' => True,
			'currentapp' => 'home',
			'noapi' => True
		);
		include('./inc/functions.inc.php');
		// Authorize the user to use setup app and load the database
		// Does not return unless user is authorized
		if (!$GLOBALS['phpgw_setup']->auth('Config'))
		{
			Header('Location: index.php');
			exit;
		}
		$GLOBALS['phpgw_setup']->loaddb();

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

	if (@$GLOBALS['HTTP_POST_VARS']['submit'])
	{
		$lang_selected = @$GLOBALS['HTTP_POST_VARS']['lang_selected'];
		$upgrademethod = @$GLOBALS['HTTP_POST_VARS']['upgrademethod'];
		$GLOBALS['phpgw_setup']->db->transaction_begin();
		if (count($lang_selected))
		{
			if ($upgrademethod == 'dumpold')
			{
				$GLOBALS['phpgw_setup']->db->query("DELETE FROM phpgw_lang",__LINE__,__FILE__);
				//echo '<br>Test: dumpold';
			}
			while (list($null,$lang) = each($lang_selected))
			{
				//echo '<br>Working on: ' . $lang;
				$addlang = False;
				if ($upgrademethod == 'addonlynew')
				{
					//echo "<br>Test: addonlynew - select count(*) from phpgw_lang where lang='".$lang."'";
					$GLOBALS['phpgw_setup']->db->query("SELECT COUNT(*) FROM phpgw_lang WHERE lang='".$lang."'",__LINE__,__FILE__);
					$GLOBALS['phpgw_setup']->db->next_record();

					if ($GLOBALS['phpgw_setup']->db->f(0) == 0)
					{
						//echo '<br>Test: addonlynew - True';
						$addlang = True;
					}
				}
				if (($addlang && $upgrademethod == 'addonlynew') || ($upgrademethod != 'addonlynew'))
				{
					//echo '<br>Test: loop above file()';
					$setup_info = $GLOBALS['phpgw_setup']->detection->get_versions();
					$setup_info = $GLOBALS['phpgw_setup']->detection->get_db_versions($setup_info);
					$raw = $raw_file = array();
					// Visit each app/setup dir, look for a lang file
					while (list($key,$app) = each($setup_info))
					{
						$appfile = PHPGW_SERVER_ROOT . SEP . $app['name'] . SEP . 'setup' . SEP . 'phpgw_' . strtolower($lang) . '.lang';
						//echo '<br>Checking in: ' . $app['name'];
						if($GLOBALS['phpgw_setup']->app_registered($app['name']) && file_exists($appfile))
						{
							//echo '<br>Including: ' . $appfile;
							$raw[] = file($appfile);
						}
					}
					@reset($raw);
					while (list($a,$raw_file) = @each($raw))
					{
						while (list($_null,$line) = @each($raw_file))
						{
							$addit = False;
							list($message_id,$app_name,$GLOBALS['phpgw_setup']->db_lang,$content) = explode("\t",$line);
							$message_id = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($message_id));
							//echo '<br>APPNAME:' . $app_name . ' PHRASE:' . $message_id;
							$app_name   = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($app_name));
							$GLOBALS['phpgw_setup']->db_lang    = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($GLOBALS['phpgw_setup']->db_lang));
							$content    = $GLOBALS['phpgw_setup']->db->db_addslashes(chop($content));
							if ($upgrademethod == 'addmissing' && $upgrademethod == 'dumpold')
							{
								//echo '<br>Test: addmissing';
								$GLOBALS['phpgw_setup']->db->query("SELECT COUNT(*) FROM phpgw_lang WHERE message_id='".$message_id."' and lang='".$GLOBALS['phpgw_setup']->db_lang."' and (app_name='".$app_name."' or app_name='common')",__LINE__,__FILE__);
								$GLOBALS['phpgw_setup']->db->next_record();

								if ($GLOBALS['phpgw_setup']->db->f(0) == 0)
								{
									//echo '<br>Test: addmissing - True - Total: ' . $GLOBALS['phpgw_setup']->db->f(0);
									$addit = True;
								}
							}

//							if ($addit || ($upgrademethod == 'dumpold' || $newinstall || $upgrademethod == 'addonlynew'))
							if ($addit || ($newinstall || $upgrademethod == 'addonlynew'))
							{
								if($message_id && $content)
								{
									//echo "<br>adding - insert into lang values ('".$message_id."','".$app_name."','".$GLOBALS['phpgw_setup']->db_lang."','".$content."')";
									$result = $GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_lang(message_id,app_name,lang,content) VALUES('".$message_id."','".$app_name."','".$GLOBALS['phpgw_setup']->db_lang."','".$content."')",__LINE__,__FILE__);
									if (intval($result) <= 0)
									{
										echo "<br>Error inserting record: phpgw_lang values ('".$message_id."','".$app_name."','".$GLOBALS['phpgw_setup']->db_lang."','".$content."')";
									}
								}
							}
						}
					}
				}
			}
			$GLOBALS['phpgw_setup']->db->transaction_commit();
		}

		if(!$included)
		{
			Header('Location: index.php');
			exit;
		}
	}
	else
	{
		if ($GLOBALS['HTTP_POST_VARS']['cancel'])
		{
			Header('Location: index.php');
			exit;
		}

		if (!$included)
		{
			$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
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
			$GLOBALS['phpgw_setup']->db->query("select lang_id,lang_name from phpgw_languages where available='Yes'");
			while ($GLOBALS['phpgw_setup']->db->next_record())
			{
				$select_box_langs = 
					$select_box_langs 
					.'<option value="' . $GLOBALS['phpgw_setup']->db->f('lang_id') . '">'
					. $GLOBALS['phpgw_setup']->db->f('lang_name') . '</option>'
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

			$ConfigDomain = $GLOBALS['HTTP_COOKIE_VARS']['ConfigDomain'] ? $GLOBALS['HTTP_COOKIE_VARS']['ConfigDomain'] : $GLOBALS['HTTP_POST_VARS']['ConfigDomain'];
			$GLOBALS['phpgw_setup']->html->show_header("$stage_title",False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]['db_type'] . ')');
			$setup_tpl->pparse('out','T_lang_main');
			$GLOBALS['phpgw_setup']->html->show_footer();
		}
	}
?>
