<?php
  /**************************************************************************\
  * eGroupWare - Setup - change / convert system-charset                     *
  * http://www.eGroupWareare.org                                             *
  * Written by RalfBecker@outdoor-training.de                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$diagnostics = 1;	// can be set to 0=non, 1=some (default for now), 2=all

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

	$GLOBALS['phpgw_setup']->translation->setup_translation_sql();
	$translation = &$GLOBALS['phpgw_setup']->translation->sql;
	$translation->translation(True);	// to get the mbstring warnings

	$tpl_root = $GLOBALS['phpgw_setup']->html->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head' => 'head.tpl',
		'T_footer' => 'footer.tpl',
		'T_system_charset' => 'system_charset.tpl',
	));

	$stage_title = lang('Change system-charset');
	$stage_desc  = lang('This program will convert your database to a new system-charset.');

	if ($diagnostics || !@$_POST['convert'])
	{
		$GLOBALS['phpgw_setup']->html->show_header($stage_title,False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]['db_type'] . ')');
	}
	if (@$_POST['convert'])
	{
		if (empty($_POST['current_charset']))
		{
			$errors[] = lang('You need to select your current charset!');
			$GLOBALS['phpgw_setup']->html->show_header($stage_title,False,'config',$ConfigDomain . '(' . $phpgw_domain[$ConfigDomain]['db_type'] . ')');
		}
		else
		{
			convert_db($_POST['current_charset'],$_POST['new_charset'],$diagnostics);

			if (!$diagnostics)
			{
				Header('Location: index.php');
			}
			echo '<h3>'.lang("Database successfully converted from '%1' to '%2'",$_POST[current_charset],$_POST[new_charset])."</h3>\n";
			echo '<p>'.lang('Click <a href="index.php">here</a> to return to setup.')."</p>\n";
			exit;
		}
	}

	function convert_db($from,$to,$diagnostics=1)
	{
		if ($diagnostics) echo "<h3>Converting database from '$from' to '$to'</h3>\n";

		@set_time_limit(0);		// this might take a while

		$db2 = $GLOBALS['phpgw_setup']->db;
		$setup_info = $GLOBALS['phpgw_setup']->detection->get_versions();
		$setup_info = $GLOBALS['phpgw_setup']->detection->get_db_versions($setup_info);
		// Visit each app/setup dir, look for a phpgw_lang file

		foreach($setup_info as $app => $data)
		{
			if ($diagnostics) echo "<p><b>$app</b>: ";

			if (!isset($data['tables']) || !count($data['tables']) ||
			    !$GLOBALS['phpgw_setup']->app_registered($app) ||
				!($table_definitions = $db2->get_table_definitions($app)))
			{
				if ($diagnostics) echo "skipping (no tables or not installed)</p>\n";
				continue;
			}
			foreach($table_definitions as $table => $definition)
			{
				if ($diagnostics) { echo "<br>start converting table '$table' ... "; flush(); }
				$db2->set_column_definitions($definitions['fd']);
				$updates = 0;
				$GLOBALS['phpgw_setup']->db->query("SELECT * FROM $table",__LINE__,__FILE__);
				while($columns = $GLOBALS['phpgw_setup']->db->row(True))
				{
					$update = array();
					foreach($columns as $name => $data)
					{
						switch($definition['fd'][$name]['type'])
						{
							case 'char':
							case 'varchar':
							case 'text':
							case 'longtext':
								$converted = $GLOBALS['translation']->convert($data,$from,$to);
								if ($converted != $data)
								{
									$update[$name] = $converted;
								}
								break;
						}
					}
					if (count($update))
					{
						if (count($definition['pk']))
						{
							$db2->query($query="UPDATE $table SET ".$db2->column_data_implode(',',$update)." WHERE ".$db2->column_data_implode(' AND ',$columns,True,$definition['pk']),__LINE__,__FILE__);
						}
						else
						{
							// if we have no primary key, we need to delete and re-write the row
							$db2->query($query="DELETE FROM $table  WHERE ".$db2->column_data_implode(' AND ',$columns),__LINE__,__FILE__);
							if ($diagnostics > 1) echo " &nbsp; $query<br>\n";
							$db2->query($query="INSERT INTO $table (".implode(',',array_keys($columns)).") VALUES (".$db2->column_data_implode(',',array_merge($columns,$update),False).")",__LINE__,__FILE__);
						}
						if ($diagnostics > 1) echo " &nbsp; $query<p>\n";
						++$updates;
					}
				}
				if ($diagnostics)
				{
					$GLOBALS['phpgw_setup']->db->query("SELECT count(*) FROM $table",__LINE__,__FILE__);
					$GLOBALS['phpgw_setup']->db->next_record();
					$total = $GLOBALS['phpgw_setup']->db->f(0);
					echo " done, $updates/$total rows updated";
				}
			}
		}
		@$GLOBALS['phpgw_setup']->db->query("DELETE FROM phpgw_config WHERE config_app='phpgwapi' AND config_name='system_charset'",__LINE__,__FILE__);
		$GLOBALS['phpgw_setup']->db->query("INSERT INTO phpgw_config (config_app,config_name,config_value) VALUES ('phpgwapi','system_charset','$to')",__LINE__,__FILE__);
	}

	$setup_tpl->set_var('stage_title',$stage_title);
	$setup_tpl->set_var('stage_desc',$stage_desc);
	$setup_tpl->set_var('error_msg',is_array($errors) ? implode('<br>',$errors) : '&nbsp');

	$setup_tpl->set_var('lang_convert',lang('Convert'));
	$setup_tpl->set_var('lang_cancel',lang('Cancel'));
	$setup_tpl->set_var('lang_current',lang('Current system-charset'));
	$setup_tpl->set_var('lang_convert_to',lang('Charset to convert to'));
	$setup_tpl->set_var('lang_warning','<b>Warning</b>: <font color="red">This feature is still experimental. <b>DO NOT USE IT ON A PRODUCTION SYSTEM.</b></font><br>Hopefully you know what you do ;-)');

	$installed_charsets = $translation->get_installed_charsets();
	if ($translation->system_charset || count($installed_charsets) == 1)
	{
		reset($installed_charsets);
		list($current_charset) = each($installed_charsets);
		if ($translation->system_charset)
		{
			$current_charset = $translation->system_charset;
		}
		$setup_tpl->set_var('current_charset',"<b>$current_charset</b>".
			"<input type=\"hidden\" name=\"current_charset\" value=\"$current_charset\">\n");
	}
	else
	{
		$options = '<option value="">'.lang('select one...')."</option>\n";
		foreach($installed_charsets as $charset => $description)
		{
			$options .= "<option value=\"$charset\">$description</option>\n";
		}
		$setup_tpl->set_var('current_charset',"<select name=\"current_charset\">\n$options</select>\n");
	}
	if ($translation->system_charset == 'utf-8' || count($installed_charsets) == 1)
	{
		reset($installed_charsets);
		list($other_charset) = each($installed_charsets);
		if (!$translation->system_charset || $other_charset == $translation->system_charset)
		{
			$other_charset = 'utf-8';
		}
		$setup_tpl->set_var('new_charset',"<b>$other_charset</b><input type=\"hidden\" name=\"new_charset\" value=\"$other_charset\">\n");
	}
	else
	{
		if ($translation->system_charset != 'utf-8')
		{
			$options = '<option value="utf-8">'.lang('utf-8 (Unicode)')."</option>\n";
		}
		foreach($installed_charsets as $charset => $description)
		{
			if ($charset != $translation->system_charset)
			{
				$options .= "<option value=\"$charset\">$description</option>\n";
			}
		}
		$setup_tpl->set_var('new_charset',"<select name=\"new_charset\">\n$options</select>\n");
	}
	$setup_tpl->pparse('out','T_system_charset');
	$GLOBALS['phpgw_setup']->html->show_footer();
?>
