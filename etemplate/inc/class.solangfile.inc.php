<?php
  /**************************************************************************\
  * phpGroupWare - Translation Editor                                        *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

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
			'lang' => array(1),
			'create_input_box' => array(1,3),
			'create_check_box' => array(1,3),
			'create_select_box' => array(1,4),
			'create_text_area' => array(1,5),
			'create_password_box' => array(1,3)
		);

		var $public_functions = array(
			'index' => True
		);

		function solangfile()
		{
			$this->db = $GLOBALS['phpgw']->db;
		}

		function parse_php_app($fd,$plist)
		{
			$reg_expr = '('.implode('|',array_keys($this->functions)).")[ \t]*\([ \t]*(.*)$";
			define('SEP',filesystem_separator());
			list($app) = explode(SEP,$fd);
			$d=dir($fd);
			while ($fn=$d->read())
			{
				if (@is_dir($fd.$fn.SEP))
				{
					if (($fn!='.')&&($fn!='..')&&($fn!='CVS'))
					{
						$plist=$this->parse_php_app($fd.$fn.SEP,$plist);
					}
				}
				elseif ((strpos($fn,'.php')>1) && (is_readable($fd.$fn)))
				{
					$lines = file($fd.SEP.$fn);

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
								if (!$rest || strpos($rest,$del,1) === False)
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
										//if (!isset($plist[$phrase])) echo ">>>$phrase<<<<br>\n";
										$plist[$phrase] = $app;
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
			return ($plist);
		}

		function missing_app($app,$userlang=en)
		{
			$cur_lang=$this->load_app($app,$userlang);
			define('SEP',filesystem_separator());
			$fd = PHPGW_SERVER_ROOT . SEP . $app . SEP;
			$plist=array();
			$plist = $this->parse_php_app($fd,$plist);
			reset($plist);
			return($plist);
		}

		/*!
		@function add_app
		@abstract loads all app phrases into langarray
		@param $lang	user lang variable (defaults to en)
		*/
		function add_app($app,$userlang='en')
		{
			define('SEP',filesystem_separator());

			$fd = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup';
			$fn = $fd . SEP . 'phpgw_' . $userlang . '.lang';
			if (@is_writeable($fn) || is_writeable($fd))
			{
				$wr = True;
			}
			$this->src_apps = array($app => $app);

			if (file_exists($fn))
			{
				$this->src_file = $fn;
				$fp = fopen($fn,'rb');
				while ($data = fgets($fp,8000))
				{
					list($message_id,$app_name,$null,$content) = explode("\t",$data);
					if(!$message_id)
					{
						continue;
					}
					//echo '<br>add_app(): adding phrase: $this->langarray["'.$message_id.'"]=' . trim($content);
					$_mess_id = strtolower(trim($message_id));
					$app_name = trim($app_name);
					$this->langarray[$_mess_id]['message_id'] = $_mess_id;
					$this->langarray[$_mess_id]['app_name']   = $app_name;
					$this->langarray[$_mess_id]['content']    = trim($content);
					$this->src_apps[$app_name] = $app_name;
				}
				fclose($fp);
			}
			else
			{
				$this->src_file = lang('no file');
			}
			// stuff class array listing apps that are included already
			$this->loaded_apps[$userlang]['filename']  = $fn;
			$this->loaded_apps[$userlang]['writeable'] = $wr;

			if($this->debug) { _debug_array($this->langarray); }
			@ksort($this->langarray);
			return $this->langarray;
		}

		/*!
		@function load_app
		@abstract loads all app phrases into langarray
		@param $lang user lang variable (defaults to en)
		*/
		function load_app($app,$userlang='en')
		{
			define('SEP',filesystem_separator());

			$fd = PHPGW_SERVER_ROOT . SEP . $app . SEP . 'setup';
			$fn = $fd . SEP . 'phpgw_' . $userlang . '.lang';
			if (@is_writeable($fn) || is_writeable($fd))
			{
				$wr = True;
			}

			if (file_exists($fn))
			{
				$this->tgt_file = $fn;
				if ($fp = @fopen($fn,'rb'))
				{
				   while ($data = fgets($fp,8000))
				   {
					   list($message_id,$app_name,$null,$content) = explode("\t",$data);
					   if(!$message_id)
					   {
						   continue;
					   }
					   //echo '<br>add_app(): adding phrase: $this->langarray["'.$message_id.'"]=' . trim($content);
					   $_mess_id = strtolower(trim($message_id));
					   $langarray[$_mess_id]['message_id'] = $_mess_id;
					   $langarray[$_mess_id]['app_name']   = trim($app_name);
					   $langarray[$_mess_id]['content']    = trim($content);
				   }
				   fclose($fp);
				}
			}
			else
			{
				$this->tgt_file = lang('no file');
			}
			// stuff class array listing apps that are included already
			$this->loaded_apps[$userlang]['filename']  = $fn;
			$this->loaded_apps[$userlang]['writeable'] = $wr;
			if($this->debug) { _debug_array($langarray); }
			@ksort($langarray);
			return $langarray;
		}

		function list_langs()
		{
			$this->db->query("SELECT DISTINCT lang FROM phpgw_lang");
			while($this->db->next_record())
			{
				$lang = $this->db->f('lang');
				$installed[] = $lang;
			}
			$installed = "('".implode("','",$installed)."')"; 
			
			// this shows first the installed, then the available and then the rest
			$this->db->query("SELECT lang_id,lang_name,lang_id IN $installed as installed FROM phpgw_languages ORDER BY installed DESC,available DESC,lang_name");
			$i = 0;
			while ($this->db->next_record())
			{
				$languages[$i]['lang_id']   = $this->db->f('lang_id');
				$languages[$i]['lang_name'] = $this->db->f('lang_name');
				$i++;
			}
			@reset($languages);
			if($this->debug) { _debug_array($languages); }
			return $languages;
		}

		function write_file($app_name,$langarray,$userlang)
		{
			$fn = PHPGW_SERVER_ROOT . SEP . $app_name . SEP . 'setup' . SEP . 'phpgw_' . $userlang . '.lang';
			if (file_exists($fn))
			{
				$backup = $fn . '.old';
				@unlink($backup);
				@rename($fn,$backup);
			}
			$fp = fopen($fn,'wb');
			while(list($mess_id,$data) = @each($langarray))
			{
				fwrite($fp,$mess_id . "\t" . $data['app_name'] . "\t" . $userlang . "\t" . $data['content'] . "\n");
			}
			fclose($fp);
			return;
		}

		function loaddb($app_name,$userlang)
		{
			$this->db->transaction_begin();

			$langarray = $this->load_app($app_name,$userlang);

			@reset($langarray);
			while (list($x,$data) = @each($langarray))
			{
				if (strlen($data['message_id']) > MAX_MESSAGE_ID_LENGTH)
				{
					$data['message_id'] = substr($data['message_id'],0,MAX_MESSAGE_ID_LENGTH);
				}
				
				$message_id = $this->db->db_addslashes(substr($data['message_id'],0,MAX_MESSAGE_ID_LENGTH));

				$addit = False;
				/*echo '<br><br><pre> checking ' . $data['message_id'] . "\t" . $data['app_name'] . "\t" . $userlang . "\t" . $data['content'];*/
				$this->db->query("SELECT COUNT(*) FROM phpgw_lang"
					."  WHERE message_id='$message_id' AND lang='$userlang' AND app_name='$app_name'",__LINE__,__FILE__);
				$this->db->next_record();

				if ($this->db->f(0) == 0)
				{
					$addit = True;
					/* echo '... no</pre>'; */
				}
				else
				{
					/* echo '... yes</pre>'; */
				}

				if ($addit)
				{
					if($data['message_id'] && $data['content'])
					{
						/* echo "<br>adding - insert into lang values ('" . $data['message_id'] . "','$app_name','$userlang','" . $data['content'] . "')"; */
						$this->db->query("INSERT into phpgw_lang VALUES ('$message_id','$app_name','$userlang','"
							. $this->db->db_addslashes($data['content']) . "')",__LINE__,__FILE__);
					}
				}
				else
				{
					if($data['message_id'] && $data['content'])
					{
						$this->db->query("UPDATE phpgw_lang SET content='". $this->db->db_addslashes($data['content']) . "'"
							. " WHERE message_id='message_id'"
							. " AND app_name='$app_name' AND lang='$userlang'",__LINE__,__FILE__);
						if ($this->db->affected_rows() > 0)
						{
/*
							echo "<br>changing - update lang set content='". $data['content'] . "'"
								. " where message_id='" . $data['message_id'] ."'"
								. " and app_name='$app_name' and lang='$userlang'";
*/
						}
					}
				}
			}
			$this->db->transaction_commit();
			return lang('done');
		}
	}
?>
