<?php
  /**************************************************************************\
  * eGroupWare - Setup                                                       *
  * http://www.egroupware.org                                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include('./inc/functions.inc.php');
	// Authorize the user to use setup app and load the database
	// Does not return unless user is authorized
	if (!$GLOBALS['phpgw_setup']->auth('Config') || @$_POST['cancel'])
	{
		Header('Location: index.php');
		exit;
	}
	$GLOBALS['phpgw_setup']->loaddb();

	if (@$_POST['submit'])
	{
		$GLOBALS['phpgw_setup']->translation->setup_translation_sql();
		$GLOBALS['phpgw_setup']->translation->sql->install_langs(@$_POST['lang_selected'],@$_POST['upgrademethod']);

		
		if( !$GLOBALS['phpgw_setup']->translation->sql->line_rejected )
		{
			Header('Location: index.php');
			exit;
		}
	}

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
	$stage_desc  = lang('This program will help you upgrade or install different languages for eGroupWare');
	$tbl_width   = @$newinstall ? '60%' : '80%';
	$td_colspan  = @$newinstall ? '1' : '2';
	$td_align    = @$newinstall ? ' align="center"' : '';
	$hidden_var1 = @$newinstall ? '<input type="hidden" name="newinstall" value="True">' : '';

	if (!@$newinstall && !isset($GLOBALS['phpgw_info']['setup']['installed_langs']))
	{
		$GLOBALS['phpgw_setup']->detection->check_lang(false);	// get installed langs
	}
	$select_box_desc = lang('Select which languages you would like to use');
	$select_box = '';
	$languages = get_langs();
	uasort($languages,create_function('$a,$b','return strcmp(@$a[\'descr\'],@$b[\'descr\']);'));
	foreach($languages as $id => $data)
	{
		$select_box_langs =
			@$select_box_langs
			.'<option value="' . $id . '"'
			.(@$GLOBALS['phpgw_info']['setup']['installed_langs'][$id]?' SELECTED="1"':'').'>'
			. $data['descr'] . '</option>'
			."\n";
	}

	if (!@$newinstall)
	{
		$meth_desc = lang('Select which method of upgrade you would like to do');
		$blurb_addonlynew = lang('Only add languages that are not in the database already');
		$blurb_addmissing = lang('Only add new phrases');
		$blurb_dumpold = lang('Delete all old languages and install new ones');

		$setup_tpl->set_var('meth_desc',$meth_desc);
		$setup_tpl->set_var('blurb_addonlynew',$blurb_addonlynew);
		$setup_tpl->set_var('blurb_addmissing',$blurb_addmissing);
		$setup_tpl->set_var('blurb_dumpold',$blurb_dumpold);
		$setup_tpl->set_var('lang_debug',lang('enable for extra debug-messages'));
		$setup_tpl->parse('V_choose_method','B_choose_method');
	}
	else
	{
		$setup_tpl->set_var('V_choose_method','');
	}

	// Rejected Lines
	if($_POST['debug'] && count($GLOBALS['phpgw_setup']->translation->sql->line_rejected))
	{
		$str = '';
		foreach($GLOBALS['phpgw_setup']->translation->sql->line_rejected as $badline)
		{
			$_f_buffer = split("[/\\]", $badline['appfile']);
			$str .= lang('Application: %1, File: %2, Line: "%3"','<b>'.$_f_buffer[count($_f_buffer)-3].'</b>',
				'<b>'.$_f_buffer[count($_f_buffer)-1].'</b>',$badline['line'])."<br>\n";
		}
		$setup_tpl->set_var('V_alert_word', lang('Rejected lines'));
		$setup_tpl->set_var('V_alert_msg', $str);
		$alert = TRUE;
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

	$GLOBALS['phpgw_setup']->html->show_header("$stage_title",False,'config',$GLOBALS['phpgw_setup']->ConfigDomain . '(' . $phpgw_domain[$GLOBALS['phpgw_setup']->ConfigDomain]['db_type'] . ')');
	$setup_tpl->pparse('out','T_lang_main');
	
	if($alert)
		$setup_tpl->pparse('out','T_alert_msg');
		
	$GLOBALS['phpgw_setup']->html->show_footer();
?>
