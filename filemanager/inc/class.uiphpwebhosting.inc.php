<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * This file written by Mark A Peters (Skeeter) <skeeter@phpgroupware.org>  *
  * This class user interface for the phpwebhosting app                      *
  * Copyright (C) 2002 Mark A Peters                                         *
  * -------------------------------------------------------------------------*
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id$ */

	class uiphpwebhosting
	{

		var $public_functions = array(
			'index'	=> True,
			'action'	=> True,
			'help'	=> True,
			'history'	=> True,
			'view'	=> True,
			'view_file'	=> True,
			'edit'	=> True
		);

		var $bo;
		var $nextmatchs;
		var $browser;
		var $template_dir;
		var $help_info;

		function uiphpwebhosting()
		{
			$this->bo = CreateObject('phpwebhosting.bophpwebhosting');
			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$this->browser = CreateObject('phpgwapi.browser');
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir($GLOBALS['phpgw_info']['flags']['currentapp']);
			$this->check_access();
			$this->create_home_dir();
			$this->verify_path();
			$this->update();
		}

		function load_header()
		{
			unset($GLOBALS['phpgw_info']['flags']['noheader']);
			unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
			unset($GLOBALS['phpgw_info']['flags']['noappheader']);
			unset($GLOBALS['phpgw_info']['flags']['noappfooter']);
			$GLOBALS['phpgw']->common->phpgw_header();
		}

		function check_access()
		{
			if($this->bo->path != $this->bo->homedir && $this->bo->path != $this->bo->fakebase && $this->bo->path != '/' && !$this->bo->vfs->acl_check($this->bo->path,Array(RELATIVE_NONE),PHPGW_ACL_READ))
			{
				$this->no_access_exists(lang('You do not have access to %1',$this->bo->path));			
			}
			$this->bo->userinfo['working_id'] = $this->bo->vfs->working_id;
			$this->bo->userinfo['working_lid'] = $GLOBALS['phpgw']->accounts->id2name($this->bo->userinfo['working_id']);
		}

		function set_col_headers(&$p,$var,$append=True)
		{
			$p->set_var($var);
			$p->parse('col_headers','column_headers',$append);
		}

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
			$GLOBALS['phpgw']->common->phpgw_exit();	
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

			if(($this->bo->path == $this->bo->homedir) && !$this->bo->vfs->file_exists($this->bo->homedir,Array(RELATIVE_NONE)))
			{
				//$this->bo->vfs->override_acl = 1;
				if (!$this->bo->vfs->mkdir($this->bo->homedir,Array(RELATIVE_NONE)))
				{
					echo lang('failed to create directory') . ' <b>'. $this->bo->homedir . '</b><br><br>';
				}
				//$this->bo->vfs->override_acl = 0;
			}
			elseif(preg_match("|^".$this->bo->fakebase."\/(.*)$|U",$this->bo->path,$this->bo->matches))
			{
				if (!$this->bo->vfs->file_exists($this->bo->path,Array(RELATIVE_NONE)))
				{
					//$this->bo->vfs->override_acl = 1;
					if (!$this->bo->vfs->mkdir($this->bo->homedir,Array(RELATIVE_NONE)))
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
						$this->bo->vfs->set_attributes($this->bo->path,Array(RELATIVE_NONE),Array('owner_id' => $group_id, 'createdby_id' => $group_id));
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
				echo 'DEBUG: ui.verify_path: exists = '.$this->bo->vfs->file_exists($this->bo->path,Array(RELATIVE_NONE)).'<br>'."\n";
			}
			
			if($this->bo->path != $this->bo->homedir &&
				$this->bo->path != '/' &&
				$this->bo->path != $this->bo->fakebase &&
				!$this->bo->vfs->file_exists($this->bo->path,Array(RELATIVE_NONE)))
			{
				$this->no_access_exists(lang('Directory %1 does not exist',$this->bo->path));
			}
		}

		function update()
		{
			/* Update if they request it, or one out of 20 page loads */
			srand((double)microtime() * 1000000);
			if($this->bo->update || rand(0,19) == 4)
			{
				$this->bo->vfs->update_real($this->bo->path,Array(RELATIVE_NONE));
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

		function action()
		{
			$actions = Array(
				'rename'	=> lang('Rename'),
				'delete'	=> lang('Delete'),
				'go'	=> lang('Go To'),
				'copy'	=> lang('Copy To'),
				'move'	=> lang('Move To'),
				'download'	=> lang('Download'),
				'newdir'	=> lang('Create Folder'),
				'newfile'	=> lang('Create File')
			);
			@reset($actions);
			while(list($function,$text) = each($actions))
			{
				if(isset($this->bo->$function) && !empty($this->bo->$function) && trim(strtolower($this->bo->$function)) == strtolower($text))
				{
					$f_function = 'f_'.$function;
					$errors = $this->bo->$f_function();
					$var = Array(
						'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
						'path'	=> urlencode($this->bo->path)
					);
					if($function == 'newfile')
					{
						$var = Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.edit',
							'path'	=> urlencode($this->bo->path),
							'file'	=> urlencode($this->bo->createfile)
						);
					}
					elseif(is_array($errors))
					{
						$var['errors'] = urlencode(base64_encode(serialize($errors)));
					}
					Header('Location: '.$GLOBALS['phpgw']->link('/index.php',$var));
				}
			}
		}

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
			$GLOBALS['phpgw']->common->phpgw_exit ();
		}

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

		function image($image,$alt)
		{
			return '<img src="'.$GLOBALS['phpgw']->common->image($this->bo->appname,$image).'" alt="'.$alt.'" align="center" border="0">';
		}

		function link($array_params,$text)
		{
			return '<a href="'.$GLOBALS['phpgw']->link('/index.php',$array_params).'">'.$text.'</a>';
		}

		function build_upload_choices($number)
		{
			return $this->link(
				Array(
					'menuaction'	=> get_var('menuaction',Array('GET')),
					'path'	=> $this->bo->path,
					'show_upload_boxes'	=> $number
				),
				$number).'&nbsp;&nbsp;';
		}

		function column_header(&$p,$internal,$displayed,$link=True)
		{
			if($link)
			{
				$header_str = '<a href="'
					. $GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
							'sortby'		=> $internal
						)
					).'"><b>'.lang($displayed).'</b></a>';
			}
			else
			{
				$header_str = $displayed;
			}
			$this->set_col_headers(
				$p,
				Array(
					'td_extras'	=> '',
					'column_header'	=> $header_str.$this->build_help($internal)
				)
			);
		}

		function display_buttons()
		{
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'_buttons'	=> 'small_table.tpl'
				)
			);
			$p->set_block('_buttons','table','table');
			$p->set_block('_buttons','column_headers','column_headers');
			$p->set_block('_buttons','column_headers_normal','column_headers_normal');
			$p->set_block('_buttons','column_rows','column_rows');

			$var = Array(
				'table_extras'	=> '',
				'tr_extras'	=> '',
				'td_extras'	=> ' align="center" width="25%"'
			);

			$var['column_header']	= '<input type="submit" name="edit" value="     '.lang('Edit').'     ">'.$this->build_help('edit');
			$this->set_col_headers($p,$var,False);

			$var['column_header']	= '<input type="submit" name="rename" value="  '.lang('Rename').'  ">'.$this->build_help('rename');
			$this->set_col_headers($p,$var);

			$var['column_header']	= '<input type="submit" name="delete" value="     '.lang('Delete').'     ">'.$this->build_help('delete');
			$this->set_col_headers($p,$var);

			$var['column_header']	= '<input type="submit" name="edit_comments" value=" '.lang('Edit Comments').' ">'.$this->build_help('edit_comments');
			$this->set_col_headers($p,$var);
			$p->parse('list','column_rows',True);

			$var['column_header']	= '<input type="submit" name="go" value="  '.lang('Go To').'  ">'.$this->build_help('go_to');
			$this->set_col_headers($p,$var,False);

			$var['column_header']	= '<input type="submit" name="copy" value=" '.lang('Copy To').' ">'.$this->build_help('copy_to');
			$this->set_col_headers($p,$var);

			$var['column_header']	= '<input type="submit" name="move" value=" '.lang('Move To').' ">'.$this->build_help('move_to');
			$this->set_col_headers($p,$var);

			###
			# First we get the directories in their home directory
			###

			$dirs[] = Array(
				'directory' => $this->bo->fakebase,
				'name' => $this->bo->userinfo['account_lid']
			);
			$ls_array = $this->bo->vfs->ls($this->bo->homedir,Array(RELATIVE_NONE),True,'Directory');
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

				$ls_array = $this->bo->vfs->ls($this->bo->fakebase.SEP.$group_array['account_name'],Array(RELATIVE_NONE),True,'Directory');
				while(list($num,$dir) = each($ls_array))
				{
					$dirs[] = $dir;
				}
			}

			$dir_list = '';
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
			
				if((($dir['directory'].$dir['name']) != $this->bo->path) && $this->bo->vfs->file_exists($dir['directory'].$dir['name'],Array(RELATIVE_NONE)))
				{
					$dir_list .= '<option value="'.urlencode($dir['directory'].$dir['name']).'"'.$selected.'>'.$dir['directory'].$dir['name'].'</option>';
				}
			}

			$var['column_header']	= '<select name="todir">'.$dir_list.'</select>'.$this->build_help('directory_list');
			$this->set_col_headers($p,$var);
			$p->parse('list','column_rows',True);
			$p->set_var('col_headers','');

			$var = Array(
				'tr_extras'	=> '',
				'td_extras'	=> ' colspan="2" align="center" width="50%"'
			);

			if($this->bo->path != '/' && $this->bo->path != $this->bo->fakebase)
			{
				$var['column_header']	= '<input type="submit" name="download" value=" '.lang('Download').' ">'.$this->build_help('download');
				$this->set_col_headers($p,$var);

				$var['column_header']	= '&nbsp;&nbsp;&nbsp;<input type="text" name="createdir" maxlength="255" size="15">&nbsp;<input type="submit" name="newdir" value=" '.lang('Create Folder').' ">'.$this->build_help('create_folder');
				$this->set_col_headers($p,$var);
				$p->parse('list','column_rows',True);
			}

			$var['column_header']	= '<input type="submit" name="update" value="     '.lang('Update').'     ">'.$this->build_help('update');
			$this->set_col_headers($p,$var,False);

			if($this->bo->path != '/' && $this->bo->path != $this->bo->fakebase)
			{
				$var['column_header']	= '&nbsp;&nbsp;&nbsp;<input type="text" name="createfile" maxlength="255" size="15">&nbsp;<input type="submit" name="newfile" value="    '.lang('Create File').'    ">'.$this->build_help('create_file');
			}
			else
			{
				$var['column_header']	= '&nbsp;';
			}
			$this->set_col_headers($p,$var);
			$p->parse('list','column_rows',True);
			$p->set_var('col_headers','');

			if($this->bo->settings['show_command_line'])
			{
				$var = Array(
					'tr_extras'	=> '',
					'td_extras'	=> ' colspan="4" align="center" width="100%"',
					'column_header'	=> '<input type="text" name="command_line" size="50">'.$this->build_help('command_line').'</br><input type="submit" name="execute" value="'.lang('Execute').'">'.$this->build_help('execute')
				);
				$this->set_col_headers($p,$var);
				$p->parse('list','column_rows',True);
				$p->set_var('col_headers','');
			}
			return $p->fp('output','table');
		}

		function display_summary_info($numoffiles,$usedspace)
		{
			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'_info'	=> 'small_table.tpl'
				)
			);
			$p->set_block('_info','table','table');
			$p->set_block('_info','column_headers','column_headers');
			$p->set_block('_info','column_headers_normal','column_headers_normal');
			$p->set_block('_info','column_rows','column_rows');
			$this_homedir = ($this->bo->path == $this->bo->homedir || $this->bo->path == $this->bo->fakedir);
			$info_columns = 4 + ($this_homedir?2:0);

			$var = Array(
				'table_extras'	=> ' cols="'.$info_columns.'"',
				'tr_extras'	=> '',
				'td_extras'	=> ' colspan="'.$info_columns.'" align="center" width="100%"',
				'column_header'	=> $this->build_help('file_stats')
			);
			$this->set_col_headers($p,$var,False);
			$p->parse('list','column_rows',True);
			$p->set_var('col_headers','');

			$var = Array(
				'tr_extras'	=> '',
				'td_extras'	=> ' align="right"'
			);
				
			$var['column_header'] = '<b>'.lang('Files').'</b>:';
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',False);

			$var['td_extras']	= ' align="left"';
			$var['column_header'] = $numoffiles;
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			$var['td_extras']	= ' align="right"';
			$var['column_header'] = '<b>'.lang('Used Space').'</b>:';
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			$var['td_extras']	= ' align="left"';
			$var['column_header'] = $this->bo->borkb($usedspace);
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			if($this_homedir)
			{
				$var['td_extras']	= ' align="right"';
				$var['column_header'] = '<b>'.lang('Unused space').'</b>:';
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
				$var['column_header'] = '<b>'.lang('Total Files').'</b>:';
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',False);

				$var['td_extras']	= ' colspan="'.($info_columns / 2).'" align="left" width="50%"';
				$var['column_header'] = count($this->bo->vfs->ls($this->bo->path,Array(RELATIVE_NONE)));
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',True);

				$p->parse('list','column_rows',True);
				$p->set_var('col_headers','');
			}
			return $p->fp('output','table');
		}

		function display_uploads()
		{

			$p = CreateObject('phpgwapi.Template',$this->template_dir);
			$p->set_file(
				Array(
					'_uploads'	=> 'small_table.tpl'
				)
			);
			$p->set_block('_uploads','table','table');
			$p->set_block('_uploads','column_headers','column_headers');
			$p->set_block('_uploads','column_headers_normal','column_headers_normal');
			$p->set_block('_uploads','column_rows','column_rows');

			$var = Array(
				'table_extras'	=> ' cols="3"',
				'tr_extras'	=> ''
			);

			$var['td_extras']	= ' align="right" width="45%"';
			$var['column_header'] = '<b>'.lang('File').'</b>'.$this->build_help('upload_file');
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',False);

			$var['td_extras']	= ' align="center" width="10%"';
			$var['column_header'] = '&nbsp;';
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			$var['td_extras']	= ' align="left" width="45%"';
			$var['column_header'] = '<b>'.lang('Comment').'</b>'.$this->build_help('upload_comment');
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',True);

			$p->parse('list','column_rows',True);

			$input_file = '<input type="file" name="upload_file[]" maxlength="255">';
			$input_comment = '<input type="text" name="upload_comment[]">';

			$var['tr_extras'] = '';
			$var['td_extras'] = ' colspan="3" align="center"';
			$var['column_header'] = '<input type="hidden" name="show_upload_boxes" value="'.$this->bo->show_upload_boxes.'">'."\n".$input_file.$input_comment;
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',False);
			$p->parse('list','column_rows',True);

			for($i=1;$i<$this->bo->show_upload_boxes;$i++)
			{
				$var['column_header'] = $input_file.$input_comment;
				$p->set_var($var);
				$p->parse('col_headers','column_headers_normal',False);
				$p->parse('list','column_rows',True);
			}

			$var['column_header'] = '<input type="submit" name="upload_files" value="'.lang('Upload Files').'">'.$this->build_help('upload_files');
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',False);
			$p->parse('list','column_rows',True);

			$var['column_header'] = lang('Show').'&nbsp;&nbsp;'.$this->build_upload_choices(5).$this->build_upload_choices(10).$this->build_upload_choices(20).$this->build_upload_choices(30).lang('upload fields').$this->build_help('show_upload_fields');
			$p->set_var($var);
			$p->parse('col_headers','column_headers_normal',False);
			$p->parse('list','column_rows',True);

			return '<form action="'.$GLOBALS['phpgw']->link('/index.php',
					Array(
						'menuaction'	=> $this->bo->appname.'.bo'.$this->bo->appname.'.upload',
						'path'	=> $this->bo->path
					)
				).'" method="post" enctype="multipart/form-data">'."\n".$p->fp('output','table').'</form>'."\n";
		}

		function index()
		{
			$this->load_header();
			$files_array = $this->bo->load_files();
			if(count($files_array) || $this->bo->cwd)
			{
				$p = CreateObject('phpgwapi.Template',$this->template_dir);
				$p->set_unknowns('remove');

				$p->set_file(
					Array(
						'_index'	=> 'index.tpl'
					)
				);
				$p->set_block('_index','index','index');
				$p->set_block('_index','column_headers','column_headers');
				$p->set_block('_index','column_headers_normal','column_headers_normal');
				$p->set_block('_index','column_rows','column_rows');
				
				$GLOBALS['tr_color'] = $GLOBALS['phpgw_info']['theme']['row_off'];
				$var = Array(
					'error'	=> (isset($this->bo->errors) && is_array(unserialize(base64_decode($this->bo->errors)))?$GLOBALS['phpgw']->common->error_list(unserialize(base64_decode($this->bo->errors)),'Results'):''),
					'tr_extras'	=> ' bgcolor="'.$this->nextmatchs->alternate_row_color().'" border="0"',
					'form_action'	=> $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.action',
								'path'		=> urlencode($this->bo->path)
							)
						),
					'img_up'	=> $this->link(Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
								'path'		=> urlencode($this->bo->lesspath)
							),
						$this->image('folder_up.gif',lang('Up'))),
					'help_up'	=> $this->build_help('up'),
					'img_home'	=> $this->image('folder_home.gif',lang('Folder')),
					'dir'		=> '<font size="4" color="maroon" >'."\n"
				       . '       <b>'.strtoupper($this->bo->path).'</b>'."\n"
				       . '      </font>',
					'help_home'	=> $this->build_help('home'),
					'col_headers'	=> '',
					'column_header'	=> ''
				);	
				$p->set_var($var);
				
				$this->column_header($p,'sort_by','Sort By',False);

				$columns = 1;
				@reset($this->bo->file_attributes);
				while(list($internal,$displayed) = each($this->bo->file_attributes))
				{
					if ($this->bo->settings[$internal])
					{
						$this->column_header($p,$internal,$displayed,True);
						$columns++;
					}
				}
				$p->parse('col_row','column_rows',True);
				$p->set_var('col_headers','');

//				$var = Array(
//					'tr_extras'	=> ' bgcolor="'.$this->nextmatchs->alternate_row_color().'" border="0"'
//				);
//				$this->set_col_headers($p,$var,True);

				$p->set_var('colspan',$columns);
				
				if($this->bo->settings['dotdot'] && $this->bo->settings['name'] && $this->bo->path != '/')
				{
					$this->set_col_headers(
						$p,
						Array(
							'tr_extras'	=> ' bgcolor="'.$this->nextmatchs->alternate_row_color().'" border="0"',
							'col_headers'	=> '',
							'td_extras'	=> '',
							'column_header'	=> '&nbsp;'
						)
					);

					$this->set_col_headers(
						$p,
						Array(
							'column_header'	=> $this->image('folder.gif','folder')
								.$this->link(
									Array(
										'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
										'path'		=> $this->bo->lesspath
									),
									'<b>..</b>'
								)
						)
					);

					$loop_cntr = 2;

					if($this->bo->settings['mime_type'])
					{
						$this->set_col_headers(
							$p,
							Array(
								'column_header'	=> 'Directory'
							)
						);
						$loop_cntr++;
					}

					for($i=$loop_cntr;$i<$columns;$i++)
					{
						$this->set_col_headers(
							$p,
							Array(
								'column_header'	=> '&nbsp;'
							)
						);
					}
					$p->parse('col_row','column_rows',True);
					$p->set_var('col_headers','');
				}

				$usedspace = 0;
				reset($files_array);
				$numoffiles = count($files_array);
				for($i=0;$i!=$numoffiles;$i++)
				{
					$files = $files_array[$i];
					$var = Array(
						'tr_extras'	=> ' bgcolor="'.$this->nextmatchs->alternate_row_color().'" border="0"',
						'td_extras'	=> '',
						'column_header'	=> '<input type="checkbox" name="fileman[]" value="'.urlencode($files['name']).'">'
					);
					$this->set_col_headers($p,$var,False);
//					$p->set_var($var);
//					$p->parse('col_headers','column_headers');

					$usedspace += $files['size'];

					@reset($this->bo->file_attributes);
					while(list($internal,$displayed) = each($this->bo->file_attributes))
					{
						if($this->bo->settings[$internal])
						{
							$var = Array(
								'td_extras'	=> ''
							);
							switch($internal)
							{
								case 'name':
									switch($files['mime_type'])
									{
										case 'Directory':
											$var['column_header']	= $this->image('folder.gif','folder')
													.$this->link(
														Array(
															'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
															'path'		=> $this->bo->path.SEP.$files['name']
														),
														'<b>'.$files['name'].'</b>'
													);
											break;
										default:
											$var['column_header']	= $this->link(
												Array(
													'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.view',
													'path'		=> urlencode($this->bo->path),
													'file'		=> urlencode($files['name'])
												),
												'<b>'.$files['name'].'</b>'
											);
											break;
									}
									break;
								case 'deletable':
									if ($files['deleteable'] == 'N')
									{
										$var['column_header'] = $this->image('locked.gif','locked');
									}
									else
									{
										$var['column_header'] = '&nbsp;';
									}
									break;
								case 'size':
									$var['column_header']	= $this->bo->borkb($files['size']);
									$var['td_extras']	= ' align="right"';
									break;
								case 'version':
									$var['column_header']	= $this->link(
										Array(
											'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.history',
											'path'	=> $this->bo->path,
											'file'	=> $files['name']
										),
										$files['version']
									);
									break;
								case 'modified':
								case 'created':
									$var['column_header'] = $this->bo->convert_date($files[$internal]);
									break;
								case 'owner':
								case 'createdby_id':
								case 'modifiedby_id':
									switch($internal)
									{
										case 'owner':
											$ivar = 'owner_id';
											break;
										default:
											$ivar = $internal;
											break;
									}
									$var['column_header']	= ($files[$ivar]?$GLOBALS['phpgw']->accounts->id2name($files[$ivar]):'&nbsp;');
									break;
								default:
									$var['column_header']	= ($files[$internal]?$files[$internal]:'&nbsp;');
									break;
							}
							$this->set_col_headers($p,$var);
						}
					}
					$p->parse('col_row','column_rows',True);
					$p->set_var('col_headers','');
				}

				$p->set_var('buttons',$this->display_buttons());
				$p->set_var('info',$this->display_summary_info($numoffiles,$usedspace));
				$p->set_var('uploads',$this->display_uploads());

				$p->pfp('output','index');
			}
		}

		function view()
		{
			$this->load_header();
			if($this->bo->vfs->file_exists($this->bo->path.'/'.$this->bo->file,Array(RELATIVE_NONE)))
			{
				$content_type = $this->bo->vfs->file_type($this->bo->path.$this->bo->dispsep.$this->bo->file,Array(RELATIVE_NONE));
				if($content_type)
				{
					$cont_type = explode('/',$content_type);
					$content_type = $cont_type[1];
				}
				else
				{
				}
				switch($content_type)
				{
					case 'jpeg':
					case 'gif':
					case 'bmp':
					case 'png':
						$alignment = 'center';
						$file_content = '<img src="'.$GLOBALS['phpgw']->link('/index.php',
								Array(
									'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.view_file',
									'op'		=> 'view',
									'path'		=> urlencode($this->bo->path),
									'file'		=> urlencode($this->bo->file)
								)
							).'">'."\n";
						break;
					default:
						$alignment = 'left';
						$file_content = nl2br($this->bo->vfs->read($this->bo->path.$this->bo->dispsep.$this->bo->file,Array(RELATIVE_NONE)));
						break;
				}
				$file = $this->bo->path.$this->bo->dispsep.$this->bo->file;
				$GLOBALS['tr_color'] = $GLOBALS['phpgw_info']['theme']['row_off'];
			
				echo '<table border="0" align="center" border="1">'."\n"
					. ' <tr align="left" bgcolor="'.$this->nextmatchs->alternate_row_color().'">'."\n"
					. '  <td>'."\n"
					. '   <b>TYPE:</b> '.$this->bo->vfs->file_type($file,Array(RELATIVE_NONE)).'<br>'."\n"
					. '  </td>'."\n"
					. ' </tr>'."\n"
					. ' <tr align="left" bgcolor="'.$this->nextmatchs->alternate_row_color().'">'."\n"
					. '  <td>'."\n"
					. '   <b>FILENAME:</b> '.$file."\n"
					. '  </td>'."\n"
					. ' </tr>'."\n"
					. ' <tr align="left" bgcolor="'.$this->nextmatchs->alternate_row_color().'">'."\n"
					. '  <td>'."\n"
					. '   <b>VERSION:</b> '.$this->bo->vfs->get_version($file,Array(RELATIVE_NONE))."\n"
					. '  </td>'."\n"
					. ' </tr>'."\n"
					. ' <tr align="'.$alignment.'" bgcolor="'.$this->nextmatchs->alternate_row_color().'">'."\n"
					. '  <td>'."\n"
					. $file_content."\n"
					. '  </td>'."\n"
					. ' </tr>'."\n"
					. '</table>'."\n";
			}
		}

		function history()
		{
			$this->load_header();
			$file = $this->bo->path.$this->bo->dispsep.$this->bo->file;
			if($this->bo->vfs->file_exists($file,Array(RELATIVE_NONE)))
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

				$journal_array = $this->bo->vfs->get_journal($file,Array(RELATIVE_NONE));
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
			if($this->bo->vfs->file_exists($file,Array(RELATIVE_NONE)))
			{
				$browser = CreateObject('phpgwapi.browser');
				$browser->content_header($this->bo->file,$this->bo->vfs->file_type($file,Array(RELATIVE_NONE)),$this->bo->vfs->get_size($file,Array(RELATIVE_NONE)),True);
//				$browser->content_header($this->bo->file);
				echo $this->bo->vfs->read($file,Array(RELATIVE_NONE));
				flush();
			}
			if(!is_array($file_array))
			{
				$GLOBALS['phpgw']->common->phpgw_exit ();
			}
		}

		function edit()
		{
			$this->load_header();
		}
	}
