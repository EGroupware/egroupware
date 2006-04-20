<?php
	/**
	 * eGroupWare - Translation Editor
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	class solangfile
	{
		var $total;
		var $debug = False;

		var $langarray;   // Currently loaded translations
		// array of missing phrases.
		var $missingarray;
		var $src_file;
		var $tgt_file;
		var $loaded_apps = array(); // Loaded app langs
		
		var $functions = array(		// functions containing phrases to translate and param#
			'lang'                => array(1),
			'create_input_box'    => array(1,3),
			'create_check_box'    => array(1,3),
			'create_select_box'   => array(1,4),
			'create_text_area'    => array(1,5),
			'create_notify'       => array(1,5),
			'create_password_box' => array(1,3)
		);
		var $files = array(
			'config.tpl' => 'config',
			'hook_admin.inc.php' => 'file_admin',
			'hook_preferences.inc.php' => 'file_preferences',
			'hook_settings.inc.php' => 'file',
			'hook_sidebox_menu.inc.php' => 'file',
			'hook_acl_manager.inc.php' => 'acl_manager'
		);

		var $public_functions = array(
			'index' => True
		);

		function solangfile()
		{
			$this->db = $GLOBALS['egw']->db;
		}

		function fetch_keys($app,$arr)
		{
			if (!is_array($arr))
			{
				return;
			}
			foreach($arr as $key => $val)
			{
				$this->plist[$key] = $app;
			}
		}

		function config_file($app,$fname)
		{
			//echo "<p>solangfile::config_file(app='$app',fname='$fname')</p>\n";
			$lines = file($fname);

			if ($app != 'setup')
			{
				$app = 'admin';
			}
			foreach($lines as $n => $line)
			{
				while (ereg('\{lang_([^}]+)\}(.*)',$line,$found))
				{
					$lang = str_replace('_',' ',$found[1]);
					$this->plist[$lang] = $app;

					$line = $found[2];
				}
			}
		}

		function special_file($app,$fname,$langs_in)
		{
			//echo "<p>solangfile::special_file(app='$app',fname='$fname',langs_in='$langs_in')</p>\n";
			switch ($langs_in)
			{
			 	case 'config':
					$this->config_file($app,$fname);
					return;
				case 'file_admin':
				case 'file_preferences':
					$app = substr($langs_in,5);
					break;
				case 'phpgwapi':
					$app = 'common';
					break;
			}
			$GLOBALS['file'] = $GLOBALS['settings'] = array();
			unset($GLOBALS['acl_manager']);

			ob_start();		// suppress all output
			include($fname);
			ob_end_clean();

			if (isset($GLOBALS['acl_manager']))	// hook_acl_manager
			{
				foreach($GLOBALS['acl_manager'] as $app => $data)
				{
					foreach ($data as $item => $arr)
					{
						foreach ($arr as $key => $val)
						{
							switch ($key)
							{
								case 'name':
									$this->plist[$val] = $app;
									break;
								case 'rights':
									foreach($val as $lang => $right)
									{
										$this->plist[$lang] = $app;
									}
									break;
							}
						}
					}
				}
			}
			if (count($GLOBALS['file']))	// hook_{admin|preferences|sidebox_menu}
			{
				foreach ($GLOBALS['file'] as $lang => $link)
				{
					$this->plist[$lang] = $app;
				}
			}
			foreach($GLOBALS['settings'] as $data)
			{
				foreach(array('label','help') as $key)
				{
					if (isset($data[$key]) && !empty($data[$key]))
					{
						$this->plist[$data[$key]] = $app;
					}
				}
			}
		}

		function parse_php_app($app,$fd)
		{
			$reg_expr = '('.implode('|',array_keys($this->functions)).")[ \t]*\([ \t]*(.*)$";
			define('SEP',filesystem_separator());
			$d=dir($fd);
			while ($fn=$d->read())
			{
				if (@is_dir($fd.$fn.SEP))
				{
					if (($fn!='.')&&($fn!='..')&&($fn!='CVS'))
					{
						$this->parse_php_app($app,$fd.$fn.SEP);
					}
				}
				elseif (is_readable($fd.$fn))
				{
					if (isset($this->files[$fn]))
					{
						$this->special_file($app,$fd.$fn,$this->files[$fn]);
					}
					if (strpos($fn,'.php') === False)
					{
						continue;
					}
					$lines = file($fd.$fn);

					foreach($lines as $n => $line)
					{
						//echo "line='$line', lines[1+$n]='".$lines[1+$n]."'<br>\n";
						while (eregi($reg_expr,$line,$parts))
						{
							//echo "***func='$parts[1]', rest='$parts[2]'<br>\n";
							$args = $this->functions[$parts[1]];
							$rest = $parts[2];
							for($i = 1; $i <= $args[0]; ++$i)
							{
								$next = 1;
								if (!$rest || empty($del) || strpos($rest,$del,1) === False)
								{
									$rest .= trim($lines[++$n]);
								}
								$del = $rest[0];
								if ($del == '"' || $del == "'")
								{
									//echo "rest='$rest'<br>\n";
									while (($next = strpos($rest,$del,$next)) !== False && $rest[$next-1] == '\\')
									{
										$rest = substr($rest,0,$next-1).substr($rest,$next);
									}
									if ($next === False)
									{
										break;
									}
									$phrase = str_replace('\\\\','\\',substr($rest,1,$next-1));
									//echo "next2=$next, phrase='$phrase'<br>\n";
									if ($args[0] == $i)
									{
										//if (!isset($this->plist[$phrase])) echo ">>>$phrase<<<<br>\n";
										$this->plist[$phrase] = $app;
										array_shift($args);
										if (!count($args))
										{
											break;	// no more args needed
										}
									}
									$rest = substr($rest,$next+1);
								}
								if(!ereg("[ \t\n]*,[ \t\n]*(.*)$",$rest,$parts))
								{
									break;	// nothing found
								}
								$rest = $parts[1];
							}
							$line = $rest;
						}
					}
				}
			}
			$d->close();
		}

		function missing_app($app,$userlang=en)
		{
			$cur_lang=$this->load_app($app,$userlang);
			define('SEP',filesystem_separator());
			$fd = EGW_SERVER_ROOT . SEP . $app . SEP;
			$this->plist = array();
			$this->parse_php_app($app == 'phpgwapi' ? 'common' : $app,$fd);

			reset($this->plist);
			return($this->plist);
		}

		/**
		 * loads all app phrases into langarray
		 *
		 * @param $lang user lang variable (defaults to en)
		 */
		function load_app($app,$userlang='en',$target=True)
		{
			define('SEP',filesystem_separator());

			$langarray = array();
			$fd = EGW_SERVER_ROOT . SEP . $app . SEP . ($app == 'setup' ? 'lang' : 'setup');
			$fn = $fd . SEP . 'phpgw_' . $userlang . '.lang';
			if (@is_writeable($fn) || is_writeable($fd))
			{
				$wr = True;
			}
			if (!$target) $this->src_apps = array();

			$from = $GLOBALS['egw']->translation->charset($userlang);
			$to = $GLOBALS['egw']->translation->charset();
			//echo "<p>solangfile::load_app('$app','$userlang') converting from charset('$userlang')='$from' to '$to'</p>\n";

			if (file_exists($fn))
			{
				if ($fp = @fopen($fn,'rb'))
				{
					 while ($data = fgets($fp,8000))
					 {
						list($message_id,$app_name,,$content) = explode("\t",$data);
						if(!$message_id)
						{
							continue;
						}
						if (empty($app_name))
						{
							$app_name = $app;	// fix missing app_name
						}
						//echo '<br>load_app(): adding phrase: $this->langarray["'.$message_id.'"]=' . trim($content)."' for $app_name";
						$_mess_id = strtolower(trim($message_id));
						$langarray[$_mess_id]['message_id'] = $_mess_id;
						$app_name   = trim($app_name);
						$langarray[$_mess_id]['app_name']   = $app_name;
						if (!$target)
						{
							$this->src_apps[$app_name] = $app_name;
						}
						$langarray[$_mess_id]['content']    =
							$GLOBALS['egw']->translation->convert(trim($content),$from,$to);
					 }
					 fclose($fp);
				}
			}
			else
			{
				$fn = lang('no file');
			}
			if ($target)
			{
				$this->tgt_file = $fn;
			}
			else
			{
				$this->src_file = $fn;
			}
			// stuff class array listing apps that are included already
			$this->loaded_apps[$userlang]['filename']  = $fn;
			$this->loaded_apps[$userlang]['writeable'] = $wr;

			if (!$target) ksort($this->src_apps);

			if($this->debug) { _debug_array($langarray); }
			@ksort($langarray);
			return $langarray;
		}

		function write_file($app_name,$langarray,$userlang,$which='target')
		{
			$to = $GLOBALS['egw']->translation->charset($userlang);
			$from = $GLOBALS['egw']->translation->charset();
			//echo "<p>solangfile::write_file('$app_name',,'$userlang') converting from '$from' to charset('$userlang')='$to'</p>\n";

			$fn = EGW_SERVER_ROOT . SEP . $app_name . SEP . ($app_name == 'setup' ? 'lang' : 'setup') . SEP . 'phpgw_' . $userlang . '.lang';
			if (file_exists($fn))
			{
				$backup = $fn . '.old';
				@unlink($backup);
				@rename($fn,$backup);
			}
			$fp = fopen($fn,'wb');
			while(list($mess_id,$data) = @each($langarray))
			{
				$data['content'] = $GLOBALS['egw']->translation->convert(trim($data['content']),$from,$to);

				// dont write empty content
				if (!empty($data['content']))
				{
					fwrite($fp,$mess_id . "\t" . $data['app_name'] . "\t" . $userlang . "\t" . $data['content'] . "\n");
				}
			}
			fclose($fp);

			if ($which == 'source')
			{
				$this->src_file = $fn;
			}
			else
			{
				$this->tgt_file = $fn;
			}
			return $fn;
		}

		function loaddb($app_name,$userlangs)
		{
			if (!is_array($userlangs))
			{
				$userlangs = array($userslangs => $userlangs);
			}
			$GLOBALS['egw']->translation->install_langs($userlangs,'addmissing',$app_name);

			return lang('done');
		}
	}

	/*
	 * Helper functions for searching new phrases in sidebox, preferences or admin menus
	 */
	if (!function_exists('display_sidebox') && $_GET['menuaction'] == 'developer_tools.uilangfile.missingphrase')
	{
		function display_sidebox($appname,$menu_title,$file)	// hook_sidebox_menu
		{
			if (!is_array($file)) return;

			unset($file['_NewLine_']);
			if (is_array($GLOBALS['file']))
			{
				$GLOBALS['file'] = $file;
			}
			else
			{
				$GLOBALS['file'] += $file;
			}
		}
		function display_section($appname,$file,$file2='')		// hook_preferences, hook_admin
		{
			if (is_array($file2))
			{
				$file = $file2;
			}
			if (!is_array($file)) return;

			if (is_array($GLOBALS['file']))
			{
				$GLOBALS['file'] = $file;
			}
			else
			{
				$GLOBALS['file'] += $file;
			}
		}
	}
?>
