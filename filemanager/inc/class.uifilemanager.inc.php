<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * This file written by Mark A Peters (Skeeter) <skeeter@phpgroupware.org>  *
  * Modified by Jonathon Sim <sim@zeald.com> for Zeald Ltd                   *
  * User interface for the filemanager app                                   *
  * Copyright (C) 2002 Mark A Peters  (C) 2003 Zeald Ltd                     *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/


  /* $Id$ */
  define('UI_DEBUG', 0);

	class uifilemanager
	{

		var $public_functions = array(
			'index'	=> True,
			'action'	=> True,
			'help'	=> True,
			'history'	=> True,
			'view'	=> True,
			'view_file'	=> True,
			'edit'	=> True,
			'rename' => True,
			'edit_comments' => True
		);
		
		var $bo;
		var $nextmatchs;
		var $browser;
		var $template_dir;
		var $help_info;
		var $mime_ico = array (
	'application/pdf' => 'pdf',
	'application/postscript' => 'postscript',
	'application/msword' => 'word',
	'application/vnd.ms-excel' => 'excel',
	'application/vnd.ms-powerpoint' => 'ppt',
	'application/x-gzip' => 'tgz',
	'application/x-bzip' => 'tgz',
	'application/zip' => 'tgz',
	'application/x-debian-package' => 'deb',
	'application/x-rpm' => 'rpm',
	'application' => 'document',
	'application/octet-stream' => 'unknown',
	'audio' => 'sound',
	'audio/mpeg' => 'sound',
	'Directory' => 'folder',
	'exe' => 'exe',
	'image' => 'image',
	'text' => 'txt',
	'text/html' => 'html',
	'text/plain' => 'txt',
	'text/xml' => 'html',
	'text/x-vcalendar' => 'vcalendar',
	'text/calendar' => 'vcalendar',
	'text/x-vcard' => 'vcard',
	'text/x-tex' => 'tex',
	'unknown' => 'unknown',
	'video' => 'video',
	'message' => 'message'
);

		function uifilemanager()
		{
			$this->no_header();
			$this->actions = CreateObject('filemanager.uiactions');
			$this->bo = CreateObject('filemanager.bofilemanager');
			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$this->browser = CreateObject('phpgwapi.browser');
			//$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir($GLOBALS['phpgw_info']['flags']['currentapp']);
			$this->check_access();
			$this->create_home_dir();
			$this->verify_path();
			$this->update();
			$GLOBALS['phpgw']->xslttpl->add_file(array('widgets'));
		}

		function load_header()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			unset($GLOBALS['phpgw_info']['flags']['noappheader']);
			unset($GLOBALS['phpgw_info']['flags']['noappfooter']);
			$GLOBALS['phpgw']->xslttpl->add_file(array($GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'app_header'));
		}
		function no_header()
		{
			$GLOBALS['phpgw_info']['flags']['noheader'] = True;
			$GLOBALS['phpgw_info']['flags']['nonavbar'] = True;
			$GLOBALS['phpgw_info']['flags']['noappheader'] = True;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = True;
			 $GLOBALS['phpgw_info']['flags']['headonly'] = True;
 


		}
		function check_access()
		{
			if($this->bo->path != $this->bo->homedir && $this->bo->path != $this->bo->fakebase && $this->bo->path != '/' && !$this->bo->vfs->acl_check(array(
				'string'=>$this->bo->path,
				'relatives' => Array(RELATIVE_NONE),
				'operation' => PHPGW_ACL_READ)))
			{
				$this->no_access_exists(lang('you do not have access to %1',$this->bo->path));			
			}
			$this->bo->userinfo['working_id'] = $this->bo->vfs->working_id;
			$this->bo->userinfo['working_lid'] = $GLOBALS['phpgw']->accounts->id2name($this->bo->userinfo['working_id']);
		}

		/**TODO: xslt-ise this (and get rid of the hard-coded html)*/
		function no_access_exists($error_msg)
		{
			if($this->bo->debug)
			{
				echo 'DEBUG: ui.no_access_exists: you do not have access to this directory<br>'."\n";
			}
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_unknowns('remove');

			$p->set_file(
				Array(
					'_errors'	=> 'errors.tpl'
				)
			);
			$p->set_block('_errors','error_page','error_page');
			$p->set_block('_errors','ind_error','ind_error');

			$p->set_var('error',$error_msg);
			$p->parse('errors','ind_error',True);

			$p->set_var('error','<br><br>Go to your <a href="'.$GLOBALS['phpgw']->link('/index.php',
					Array(
						'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
						'path'	=> urlencode($this->bo->homedir)
					)
				).'">Home</a> directory'
			);
			$p->parse('errors','ind_error',True);
			$p->pfp('output','error_page');
			exit();
		}

		function create_home_dir()
		{
			###
			# If their home directory doesn't exist, we create it
			# Same for group directories
			###

			if($this->bo->debug)
			{
				echo 'DEBUG: ui.create_home_dir: PATH = '.$this->bo->path.'<br>'."\n";
				echo 'DEBUG: ui.create_home_dir: PATH = '.urlencode($this->bo->path).'<br>'."\n";
			}

			if(($this->bo->path == $this->bo->homedir) && !$this->bo->vfs->file_exists(array(
					'string' => $this->bo->homedir,
					'relatives' => Array(RELATIVE_NONE)
				)))
			{
				$this->bo->vfs->override_acl = 1;
				if (!$this->bo->vfs->mkdir(array(
					'string' => $this->bo->homedir,
					'relatives' => Array(RELATIVE_NONE)
					)))
				{
					echo lang('failed to create directory') . ' :'. $this->bo->homedir . "\n";
				}
				$this->bo->vfs->override_acl = 0;
			}
			elseif(preg_match("|^".$this->bo->fakebase."\/(.*)$|U",$this->bo->path,$this->bo->matches))
			{
				if (!$this->bo->vfs->file_exists(array(
					'string' => $this->bo->path,
					'relatives' => Array(RELATIVE_NONE)
					)))
				{
					//$this->bo->vfs->override_acl = 1;
					if (!$this->bo->vfs->mkdir(array(
						'string' => $this->bo->homedir,
						'relatives' => Array(RELATIVE_NONE)
						)))
					{
						echo lang('failed to create directory') . ' <b>'. $this->bo->homedir . '</b><br><br>';
					}
					//$this->bo->vfs->override_acl = 0;

					if($this->bo->debug)
					{
						echo 'DEBUG: ui.create_home_dir: PATH = '.$this->bo->path.'<br>'."\n";
						echo 'DEBUG: ui.create_home_dir(): matches[1] = '.$this->bo->matches[1].'<br>'."\n";
					}
					
					$group_id = $GLOBALS['phpgw']->accounts->name2id($this->bo->matches[1]);
					if($group_id)
					{
						$this->bo->vfs->set_attributes(array(
							'string' => $this->bo->path,
							'relatives' => Array(RELATIVE_NONE),
							'attributes' => Array('owner_id' => $group_id, 'createdby_id' => $group_id)
							));
					}
				}
			}
		}

		function verify_path()
		{
			###
			# Verify path is real
			###

			if($this->bo->debug)
			{
				echo 'DEBUG: ui.verify_path: PATH = '.$this->bo->path.'<br>'."\n";
				echo 'DEBUG: ui.verify_path: exists = '.$this->bo->vfs->file_exists(array(
					'string' => $this->bo->path,
					'relatives' => Array(RELATIVE_NONE))).'<br>'."\n";
			}
			
			if($this->bo->path != $this->bo->homedir &&
				$this->bo->path != '/' &&
				$this->bo->path != $this->bo->fakebase &&
				!$this->bo->vfs->file_exists(array(
					'string' => $this->bo->path,
					'relatives' => Array(RELATIVE_NONE)
					)))
			{
				$this->no_access_exists(lang('directory %1 does not exist',$this->bo->path));
			}
		}

		function update()
		{
			/* Update if they request it, or one out of 20 page loads */
			srand((double)microtime() * 1000000);
			if($this->bo->update || rand(0,19) == 4)
			{
				$this->bo->vfs->update_real( array(
					'string' => $this->bo->path,
					'relatives' =>Array(RELATIVE_NONE)
					));
			}
			if($this->bo->update)
			{
				Header('Location: '.$GLOBALS['phpgw']->link(
						'/index.php',
						Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
							'path'	=> urlencode($this->bo->path)
						)
					)
				);
			}
		}
		//Dispatches various file manager actions to the appropriate handler
		function action()
		{
			if (UI_DEBUG)
			{
				echo " Debug mode <br>";
				echo "function : ".$this->bo->$function;
				print_r($_POST);
				die();

			}
			$actions = Array(
				'rename'	=> lang('rename'),
				'delete'	=> lang('delete'),
				'go'	=> lang('go to'),
				'copy'	=> lang('copy to'),
				'move'	=> lang('move to'),
				'download'	=> lang('download'),
				'newdir'	=> lang('create folder'),
				'newfile'	=> lang('create file'),
				'edit'		=> lang('edit'),
				'edit_comments'		=> lang('edit comments'),
				'apply_edit_comment'		=> '1',
				'apply_edit_name'		=> '1',
				'cancel'	=> lang('cancel'),
				'upload'  => lang('upload files')
			);
			
			$local_functions = array(
				'rename',
				'edit_comments'			
			);
			$bo_functions = array(
				'apply_edit_comment',
				'apply_edit_name',
				'copy',
				'delete',
				'move',
				'newdir',
				'newfile',
				'go',
				'upload',
				'download'
			);
			if (trim(strtolower($this->bo->cancel)) == strtolower(lang('cancel'))) {
				$this->cancel();
				exit();				
			}
			//If the action is a "uiaction" (ie it has its own seperate interface), this will run it
			$this->actions->dispatch($this);
			@reset($actions);
			while(list($function,$text) = each($actions))
			{
				if(isset($this->bo->$function) && !empty($this->bo->$function) && trim(strtolower($this->bo->$function)) == strtolower($text))
				{
					//If the action is provided by this class, this'l do it
					if (in_array($function, $local_functions))
					{
						echo " uifunction $function "; 
						//Header('Location: '.$GLOBALS['phpgw']->link('/index.php',$var));
						$this->$function();
						exit();
					} //For actions provided by the back-end, with no gui
					elseif (in_array($function, $bo_functions))
					{
						echo " bofunction $function ";
						$f_function = 'f_'.$function;
						$errors = implode("\n", $this->bo->$f_function());
						$var = Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
							'path'	=> urlencode($this->bo->path)
						);
						if($function == 'newfile')
						{
							$var = Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.action',
								'uiaction' => 'edit',
								'path'	=> urlencode($this->bo->path),
								'file'	=> urlencode($this->bo->createfile)
							);
						}
						elseif(strlen($errors))
						{
							$var['errors'] = $errors;
						}
						Header('Location: '.$GLOBALS['phpgw']->link('/index.php',$var));
						exit();
					}	
				}

			}
			Header('Location: '.$GLOBALS['phpgw']->link('/index.php',Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
							'path'	=> urlencode($this->bo->path),
							'errors' => lang('unknown action!')
						)));
		}

		/**TODO : xslt-ise this */
		function help()
		{
			$this->load_header();
			$this->bo->load_help_info();
			@reset($this->bo->help_info);
			while(list($num,$help_array) = each($this->bo->help_info))
			{
				if ($help_array[0] != $this->bo->help_name)
				{
					continue;
				}

				$help_array[1] = preg_replace("/\[(.*)\|(.*)\]/Ue","\$this->build_help('\\1','\\2')",$help_array[1]);
				$help_array[1] = preg_replace("/\[(.*)\]/Ue","\$this->build_help('\\1','\\1')",$help_array[1]);

				echo '<font size="+4">'."\n".ucwords(str_replace('_',' ',$help_array[0]))."\n".'</font></br>'."\n";
				echo '<font size="+2">'."\n".$help_array[1].'</font>';
			}
			exit();
		}
		/**TODO : xslt-ise this */
		function build_help($help_option,$text='')
		{
			if($this->bo->settings['show_help'])
			{
				$help = ($text?'':'<font size="-2" color="maroon" >'."\n");
				$help .= '      <a href="'
					. $GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.help',
							'help_name'	=> urlencode($help_option),
							'op'	=> 'help'
						)
					)
					. '" target="_new">';
				$help .= ($text?$text:'[?]');
				$help .= '</a>';
				$help .= ($text?'':"\n".'     </font>');
				return $help;	
			}
			else
			{
				return '';
			}
		}
		
		function display_buttons()
		{
			$var = array();

			$button['type'] = 'submit';
			$button['name'] = 'uiaction_edit';
			$button['value'] = lang('edit');
			$button['caption'] = $this->build_help('edit');
			
			$var[] = array('widget' => $button);
			$button['name'] = "rename";
			$button['value'] =lang('rename');
			$button['caption'] = $this->build_help('rename');
			
			$var[] = array('widget' => $button);
			$button['name'] = "delete";
			$button['value'] =lang('delete');
			$button['caption'] = $this->build_help('delete');
			$var[] = array('widget' => $button);
			
			$button['name'] = "edit_comments";
			$button['value'] =lang('edit comments');
			$button['caption'] = $this->build_help('edit_comments');
			$var[] = array('widget' => $button);	
	
			$var[] = array('widget' => array( 'type' => 'seperator' ));

			$button['name'] = "go";
			$button['value'] = lang('go to');
			$button['caption'] = $this->build_help('go_to');
			$var[] = array('widget' => $button);
			
			$button['name'] = "copy";
			$button['value'] = lang('copy to');
			$button['caption'] = $this->build_help('copy_to');
			$var[] = array('widget' => $button);
			
			$button['name'] = "move";
			$button['value'] = lang('move to');
			$button['caption'] =$this->build_help('move_to');
			$var[] = array('widget' => $button);


			###
			# First we get the directories in their home directory
			###

			$dirs[] = Array(
				'directory' => $this->bo->fakebase,
				'name' => $this->bo->userinfo['account_lid']
			);
			$ls_array = $this->bo->vfs->ls(array(
				'string' => $this->bo->homedir,
				'relatives' => Array(RELATIVE_NONE),
				'nofiles' => True,
				'mime_type' => 'Directory'));
			while(list($num,$dir) = each($ls_array))
			{
				$dirs[] = $dir;
			}

			###
			# Then we get the directories in their membership's home directories
			###

			reset($this->bo->memberships);
			while(list($num,$group_array) = each($this->bo->memberships))
			{
				###
				# Don't list directories for groups that don't have access
				###

				if(!$this->bo->membership_applications[$group_array['account_name']][$this->bo->appname]['enabled'])
				{
					continue;
				}

				$dirs[] = Array(
					'directory' => $this->bo->fakebase,
					'name' => $group_array['account_name']
				);

				$ls_array = $this->bo->vfs->ls(array(
					'string' => $this->bo->fakebase.SEP.$group_array['account_name'],
					'relatives' => Array(RELATIVE_NONE),
					'nofiles' => True,
					'mime_type' => 'Directory'
					));
				while(list($num,$dir) = each($ls_array))
				{
					$dirs[] = $dir;
				}
			}

			$dir_list = array();
			reset($dirs);
			while(list($num, $dir) = each($dirs))
			{
				if(!$dir['directory'])
				{
					continue;
				}
		
				###
				# So we don't display //
				###

				if($dir['directory'] != '/')
				{
					$dir['directory'] .= SEP;
				}

				$selected = '';
				if($num == 0)
				{
					$selected = ' selected';
				}

				###
				# No point in displaying the current directory, or a directory that doesn't exist
				###
			
				if((($dir['directory'].$dir['name']) != $this->bo->path) && $this->bo->vfs->file_exists(array(
					'string' => $dir['directory'].$dir['name'],
					'relatives' => Array(RELATIVE_NONE)
					)))
				{
					$dir_list[] =array('option'=> array('value'=> urlencode($dir['directory'].$dir['name']),
							'selected' => $selected,
							'caption' => $dir['directory'].$dir['name']
							));
				}
			}

			$var[]	= array('widget' => array( 'type' => 'select',
									'name'=> 'todir',
									'options' => $dir_list,
									'caption' => $this->build_help('directory_list')
									));

			if($this->bo->path != '/' && $this->bo->path != $this->bo->fakebase)
			{
				$var[]	= array('widget' => array('type'=>'submit',
											'name'=> 'download',
											'value' => lang('download'),
											'caption' => $this->build_help('download')
											));
				$var[] = array('widget' => array( 'type' => 'seperator' ));
				$var[]	= array('widget' => array('type' => 'text',
												 'name' => 'createdir',
												 'maxlength' => '255',
												 'size' => '15'
												 ));
				$var[]	= array('widget' => array('type' => 'submit',
													'name' => 'newdir',
													'value' => lang('create folder'),
													'caption' => $this->build_help('create_folder')
												));
				$var[] = array('widget' => array( 'type' => 'seperator' ));
			}
	/*		$var[] = array('widget' => array('type' => 'submit',
											'name' => 'update',
											'value' => lang('update'),
											'caption' => $this->build_help('update')
										));
*/
			if($this->bo->path != '/' && $this->bo->path != $this->bo->fakebase)
			{
				$var[] = array('widget' => array( 'type' => 'text',
												'name' => 'createfile',
												'maxlength' => '255',
												'size' => '15'
											));
				$var[] = array('widget' => array('type' => 'submit',
												 'name' => 'newfile',
												 'value' => lang('create file'),
												 'caption' => $this->build_help('create_file')
												));
				$var[] = array('widget' => array( 'type' => 'seperator' ));
			}

			if($this->bo->settings['show_command_line'])
			{
	
				$var[] = array('widget' => array( 'type' => 'text' ,
												'name'=> 'command_line',
												'size' => '50',
												'caption' => $this->build_help('command_line')
											));
				$var[] = array('widget' => array( 'type' => 'submit',
												'name' => 'execute',
												'value' => lang('execute'),
												'caption' => $this->build_help('execute')
											));
				$var[] = array('widget' => array( 'type' => 'seperator' ));
			}
			return $var;
		}
/*
		function display_summary_info($numoffiles,$usedspace)
		{

			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			$var['td_extras']	= ' align="right"';
			$var['column_header'] = '<b>'.lang('used space').'</b>:';
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			$var['td_extras']	= ' align="left"';
			$var['column_header'] = $this->bo->borkb($usedspace);
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			if($this_homedir)
			{
				$var['td_extras']	= ' align="right"';
				$var['column_header'] = '<b>'.lang('unused space').'</b>:';
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',True);

				$var['td_extras']	= ' align="left"';
				$var['column_header'] = $this->bo->borkb($this->bo->userinfo['hdspace'] - $usedspace);
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',True);
			}

			$p->parse('list','column_rows',True);
			$p->set_var('col_headers','');

			if($this_homedir)
			{
				$var['td_extras']	= ' colspan="'.($info_columns / 2).'" align="right" width="50%"';
				$var['column_header'] = '<b>'.lang('total files').'</b>:';
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',False);

				$var['td_extras']	= ' colspan="'.($info_columns / 2).'" align="left" width="50%"';
				$var['column_header'] = count($this->bo->vfs->ls(array(
					'string' => $this->bo->path,
					'relatives' => Array(RELATIVE_NONE)
					)));
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',True);

				$p->parse('list','column_rows',True);
				$p->set_var('col_headers','');
			}
			return $p->fp('output','table');
		}
*/
		function display_uploads()
		{

			$var_head[] = array('widget' => array('type' => 'label',
				'caption' => lang('file').$this->build_help('upload_file')
				));


			$var_head[] = array('widget' => array('type' => 'label',
				'caption' => lang('comment').$this->build_help('upload_comment')
				));
			$table_head [] =array('table_col' => $var_head);		
		//	$var[] = array('widget' => array('type' => 'seperator'));	
			for($i=0;$i<$this->bo->show_upload_boxes;$i++)
			{
					$var = array();
					$var[] = array('widget' => array('type' => 'file',
							 'name' => 'upload_file[]' ,
							 'maxlength'=> '255'
							 ));
							 
					$var[] = array('widget' => array('type' => 'text',
							 'name' => 'upload_comment[]' 
							 ));

/*					$var[] = array('widget' => array('type' => 'label',
									'caption' => $input_file.$input_comment		 
								));	*/
					$table_rows [] = array('table_col' => $var);		
			}
			$var = array();
			$var[] =  array('widget' => array('type' =>'submit',
											'name' => 'upload',
											'value' => lang('upload Files'),
											'caption' => $this->build_help('upload_files')
											));
					
			$var[] = array('widget' => array('type' => 'hidden',
							 'name' => 'show_upload_boxes',
							 'value' => $this->bo->show_upload_boxes
							 ));
				
			$table_rows [] = array('table_col' => $var);					 

					
			return array('table' => array('table_head' =>$table_head ,
						'table_row' => $table_rows)
						);
		}

		function dirs_first($files_array)
		{
			$dirs = array();
			$files = array();
			$result = array(); 
						
			for($i=0;$i!=count($files_array);$i++)
			{
				$file = $files_array[$i];
				if ($file['mime_type'] == 'Directory')
				{
					$dirs[] = $file;
				}
				else
				{
					$files[] = $file;
				}
			}
			return array_merge($dirs, $files);
		}
		
		function index( $edit=array())
		{
			//This lets you use an alternate xslt by appending &template=<name> to the url
			if (!($template = get_var('template', array('GET', 'POST'))) )
			{
				$GLOBALS['phpgw']->xslttpl->add_file(array('index',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'app_header'));
			}
			else
			{
				$GLOBALS['phpgw']->xslttpl->add_file($template);
			}
			
			$files_array = $this->bo->load_files();
			$usage = 0;
			$files_array = $this->dirs_first($files_array);
			if(count($files_array) || $this->bo->cwd)
			{
				$file_output = array();
				for($i=0;$i!=count($files_array);$i++)
				{
					$file = $files_array[$i];
					$usage += $file['size'];
						if (!count($edit) )
						{
							$file_attributes['checkbox'] = '';
							$file_output[$i]['checkbox'] =array('widget' => array( 'type' => 'checkbox',
									'name' => 'fileman[]',
									'value' => $file['name']
								));
					}

					@reset($this->bo->file_attributes);
					while(list($internal,$displayed) = each($this->bo->file_attributes))
					{
						if ($this->bo->settings[$internal])
						{
							if ($internal==$edit[$file['name']])
							{
								$file_output[$i][$internal] = array('widget' => array('type' => 'text',
									'name' => 'changes['.$file['name'].']',
									'value' => $file[$internal]
									));
							}
							else
							{
								switch($internal)
								{
									case 'owner_id':
									case 'owner':
									case 'createdby_id':
									case 'modifiedby_id':
										$name = $GLOBALS['phpgw']->accounts->id2name($file[$internal]) ;
										$file_output[$i][$internal] = $name ? $name: '';
										break;
									case 'created':
									case 'modified':
										//Convert ISO 8601 date format used by DAV into something people can read
										$file_output[$i][$internal] =  $this->bo->convert_date($file[$internal]);
										break;
									case 'name':
										$mime_parts = explode('/',$file['mime_type']);		
											$file_icon = $this->mime_ico[$file['mime_type']];
											if (!$file_icon) {
												$file_icon = ( $this->mime_ico[$mime_parts[0]]) ?  $this->mime_ico[$mime_parts[0]] :  $this->mime_ico['unknown'];
												if (strpos($file['name'],'.exe') !== false) $file_icon =  $this->mime_ico['exe'];
											}

											$file_output[$i]['name']['icon'] = array(
												'widget' => array( 'type' => 'img',
												'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,$file_icon)
													));

										if ($file['mime_type']=='Directory')
										{
											$href = array('menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
													'path'		=> $this->bo->path.SEP.$file['name']
													);
										}
										else
										{
											$href = Array( 'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.view',
															'path'		=> urlencode($this->bo->path),
															'file'		=> urlencode($file['name'])
														);
										
										}
										$file_output[$i]['name']['link'] = array(
												"widget"=> array(
														'type' => 'link',
														'caption' => $file['name'],
														'href' =>  $GLOBALS['phpgw']->link('/index.php', $href)
												));
										if ($mime_parts[0] == 'text')
											{
												$href['menuaction'] = $this->bo->appname.'.ui'.$this->bo->appname.'.action';
												$href['uiaction'] = 'edit';
												$file_output[$i]['name']['edit'] = array('widget' => array( 'type' => 'img',
														'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'pencil'),
														'link' =>  $GLOBALS['phpgw']->link('/index.php', $href)
														));	
											}	
										break;
									default:
										$file_output[$i][$internal] = $file[$internal];
										
								}
							}
							$file_attributes[$internal] = array("widget"=> array(
									'type' => 'link',
									'caption' => $displayed,
									'href' =>  $GLOBALS['phpgw']->link('/index.php', array(
										'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
										'path'=>$this->bo->path,
										'sortby' => $internal
									))
								));
						}
					}
				}
				
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('summary' =>  array(
						'file_count' => count($files_array),
						'usage' => $usage					
				)));
				
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('file_attributes' => $file_attributes));
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array(
					'files' => array(
						'file' => $file_output
						)
					)
				);

				
				$GLOBALS['tr_color'] = $GLOBALS['phpgw_info']['theme']['row_off'];
				$var = Array(

					'form_action'	=> $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.action',
								'path'		=> urlencode($this->bo->path)
							)
							),
					'error'	=> (isset($this->bo->errors) && is_array(unserialize(base64_decode($this->bo->errors)))?$GLOBALS['phpgw']->common->error_list(unserialize(base64_decode($this->bo->errors)),'Results'):''),
					'img_up' => array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'up'),
											'alt' => lang('up'),
											'link' => $GLOBALS['phpgw']->link('/index.php',Array(
													'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
													'path'		=> urlencode($this->bo->lesspath)
												))
											)),
					'help_up'	=> $this->build_help('up'),
					'img_home'	=> array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'folder_home'),
											'alt' => lang('folder'),
											'link' => $GLOBALS['phpgw']->link('/index.php',Array(
													'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
													'path' => urlencode($this->bo->homedir)
												))										
											)),					
					'dir' => $this->bo->path,
					'img_dir' => array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'folder_large'),
											'alt' => lang('folder'),						'link' => $GLOBALS['phpgw']->link('/index.php',Array(
											'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
											'path' => urlencode($this->bo->path)
												))								
											)),
					'help_home'	=> $this->build_help('home'),
				);	
				if (strlen($this->bo->errors))
				{
					$var['errors'] = $this->bo->errors;
				}
				
				if (count($edit))
				{
					$var['img_cancel'] = array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'button_cancel'),
											'alt' => lang('folder')									
						));
					$var['button_cancel'] = array('widget' => array('type' => 'submit',
										'name' => 'cancel',
										'value' => lang('cancel')
						));
					$var['img_ok'] = array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'button_ok'),
											'alt' => lang('folder')									
						));
					$var['button_ok'] = array('widget' => array('type' => 'submit',
										'name' => 'submit',
										'value' => lang('ok')
						));
					@reset($edit);
					while( list($file,$prop) = each($edit))
					{
						$var['fileman'][] = array('widget' => array('type'=>'hidden',
										'name' => 'fileman[]',
										'value' => $file
							));
					}
					@reset($edit); list($file,$prop) = each($edit);
					$var['action'] = array('widget' => array('type'=>'hidden',
										'name' => 'apply_edit_'.$prop,
										'value' => 1
							));
				}
				
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('index' => $var));
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('sidebar' => array(
					'url' => $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
								'path'		=> urlencode($this->bo->path)
							), 1
							),
					'label' => lang('phpgroupware files'),
					'link_label' => lang('add mozilla/netscape sidebar tab')
						)
						));
				@reset($this->bo->file_attributes);
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('settings' => $this->bo->settings));
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('display_settings' => $GLOBALS['phpgw_info']['theme']));
				
				if(!count($edit))
				{
					$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('buttons'=> array('button' => $this->display_buttons()) ));
					$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('uploads' =>  $this->display_uploads() ));
				}
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('index' => $var));
			}

		}
		function cancel()
		{
			$var = Array(
					'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
					'path'	=> urlencode($this->bo->path)
				);
			Header('Location: '.$GLOBALS['phpgw']->link('/index.php',$var));	
		}
		function rename()
		{
			$edit=array();
			for ($i=0; $i!=count($this->bo->fileman);$i++)
			{
				$edit[$this->bo->fileman[$i]] = 'name';
			}
			$this->index($edit);
		}
		function edit_comments()
		{
			$edit=array();
			for ($i=0; $i!=count($this->bo->fileman);$i++)
			{
				$edit[$this->bo->fileman[$i]] = 'comment';
			}
			$this->index($edit);
		}
		function view()
		{	
			$GLOBALS['phpgw_info']['flags']['noheader'] = true;
			$GLOBALS['phpgw_info']['flags']['nonavbar'] = true;
			$GLOBALS['phpgw_info']['flags']['noappheader'] = true;
			$GLOBALS['phpgw_info']['flags']['noappfooter'] = true;

			$this->bo->vfs->view(array (
				'string'	=> $this->bo->path.'/'.$this->bo->file,
				'relatives'	=> array (RELATIVE_NONE)
			));
			exit();;
		}
		
		function history()
		{
			$this->load_header();
			$file = $this->bo->path.$this->bo->dispsep.$this->bo->file;
			if($this->bo->vfs->file_exists(array(
				'string' => $file,
				'relatives' => Array(RELATIVE_NONE)
				)))
			{
				$col_headers = Array(
					'Date'	=> 'created',
					'Version'	=> 'version',
					'Action Performed by'	=> 'owner_id',
					'Operation'	=> 'comment'
				);
				$p = CreateObject('phpgwapi.Template',$this->template_dir);
				$p->set_unknowns('remove');

				$p->set_file(
					Array(
						'_history'	=> 'history.tpl'
					)
				);
				$p->set_block('_history','history','history');
				$p->set_block('_history','column_headers','column_headers');
				$p->set_block('_history','column_rows','column_rows');

				$var = Array(
					'path'	=> $this->link(
							Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
								'path'		=> urlencode($this->bo->path)
							),
							$this->bo->path
						),
					'filename'	=> $this->link(
							Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.view',
								'path'		=> urlencode($this->bo->path),
								'file'		=> urlencode($this->bo->file)
							),
							$this->bo->file
						)
				);
				$p->set_var($var);

				$GLOBALS['tr_color'] = $GLOBALS['phpgw_info']['theme']['row_off'];
				$var = Array(
					'td_extras'	=> ''
				);
				@reset($col_headers);
				while(list($label,$field)= each($col_headers))
				{
					$var['column_header'] = '<b>'.$label.'</b>';
					$this->set_col_headers($p,$var);
				}
				$p->set_var('tr_extras',' bgcolor="'.$this->nextmatchs->alternate_row_color().'" border="0"');
				$p->parse('col_row','column_rows',True);
				$p->set_var('col_headers','');

				$journal_array = $this->bo->vfs->get_journal(array(
					'string' => $file,
					'relatives' => Array(RELATIVE_NONE)
					));
				while(list($num,$journal_entry) = each($journal_array))
				{
					@reset($col_headers);
					while(list($label,$field)= each($col_headers))
					{
						switch($field)
						{
							case 'owner_id':
								$var['column_header'] = '<font size="-2">'.$GLOBALS['phpgw']->accounts->id2name($journal_entry[$field]).'</font>';
								break;
							case 'created':
								$var['column_header'] = '<font size="-2">'.$this->bo->convert_date($journal_entry[$field]).'</font>';
								break;
							default:
								$var['column_header'] = '<font size="-2">'.$journal_entry[$field].'</font>';
								break;
						}
						$this->set_col_headers($p,$var);
					}
					$p->set_var('tr_extras',' bgcolor="'.$this->nextmatchs->alternate_row_color().'" border="0"');
					$p->parse('col_row','column_rows',True);
					$p->set_var('col_headers','');
				}
				$p->pfp('output','history');
			}
		}

		function view_file($file_array='')
		{
			if(is_array($file_array))
			{
				$this->bo->path = $file_array['path'];
				$this->bo->file = $file_array['file'];
			}
			$file = $this->bo->path.SEP.$this->bo->file;
			if($this->bo->vfs->file_exists(array(
				'string' => $file,
				'relatives' => Array(RELATIVE_NONE)
				)))
			{
				$browser = CreateObject('phpgwapi.browser');
				$browser->content_header($this->bo->file,$this->bo->vfs->file_type(array(
						'string' => $file,
						'relatives' => Array(RELATIVE_NONE))),
					$this->bo->vfs->get_size(array(
						'string' => $file,
						'relatives' => Array(RELATIVE_NONE),
						'checksubdirs' => True
						)));
//				$browser->content_header($this->bo->file);
				echo $this->bo->vfs->read(array(
					'string' => $file,
					'relatives' => Array(RELATIVE_NONE)
					));
				flush();
			}
			if(!is_array($file_array))
			{
				exit();
			}
		}

	}
