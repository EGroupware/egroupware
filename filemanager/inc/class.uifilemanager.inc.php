<?php
	/**************************************************************************\
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

	class uifilemanager
	{

		var $public_functions = array(
			'index'	=> True,
			'help'	=> True,
			'view'  => True,
			'history' => True,
			'edit' => True,
			'download'=>True
		);

		//keep
		var $bo;
		var $t; //template object
		var $dispath; 
		var $cwd;
		var $lesspath;
		var $readable_groups;
		var $files_array; 
		var $numoffiles;
		var $dispsep;

		var $target;
		

		var $prefs;//array

		//originally post_vars
		var $goto;
		var $todir;
		var $createdir;
		var $newfile_or_dir;
		var $newdir;
		var $newfile;
		var $createfile;
		var $delete;
		var $renamefiles;
		var $rename;
		var $move_to;
		var $copy_to;
		var $edit;
		var $edit_file;
		var $edit_preview;
		var $edit_save;
		var $edit_save_done;
		var $edit_cancel;
		var $comment_files;
		var $upload;
		var $uploadprocess;

		// this ones must be checked thorougly;
		var $fileman;
		var $path;
		var $file;
		var $sortby;
		var $messages;
		var $show_upload_boxes;

		var $debug = false;
		var $now;

		function uifilemanager()
		{
//			error_reporting (8);

			$this->now = date ('Y-m-d');

			$this->bo = CreateObject('filemanager.bofilemanager');

			$this->t = $GLOBALS['phpgw']->template;

			// here local vars are created from the HTTP vars
			@reset ($GLOBALS['HTTP_POST_VARS']);
			while (list ($name,) = @each ($GLOBALS['HTTP_POST_VARS']))
			{
				$this->$name = $GLOBALS['HTTP_POST_VARS'][$name];
			}
			
			@reset ($GLOBALS['HTTP_GET_VARS']);
			while (list ($name,) = @each ($GLOBALS['HTTP_GET_VARS']))
			{
				$$name = $GLOBALS['HTTP_GET_VARS'][$name];
			}

			$to_decode = array
			(
				/*
				Decode
				'var'	when	  'avar' == 'value'
				or
				'var'	when	  'var'  is set
				*/
				'op'	=> array ('op' => ''),
				'path'	=> array ('path' => ''),
				'file'	=> array ('file' => ''),
				'sortby'	=> array ('sortby' => ''),
//				'fileman'	=> array ('fileman' => ''),
				'messages'	=> array ('messages'	=> ''),
//				'help_name'	=> array ('help_name' => ''),
//				'renamefiles'	=> array ('renamefiles' => ''),
				'comment_files'	=> array ('comment_files' => ''),
				'show_upload_boxes'	=> array ('show_upload_boxes' => '')
			);

			reset ($to_decode);
			while (list ($var, $conditions) = each ($to_decode))
			{
				while (list ($condvar, $condvalue) = each ($conditions))
				{
					if (isset ($$condvar) && ($condvar == $var || $$condvar == $condvalue))
					{
						if (is_array ($$var))
						{
							$temp = array ();
							while (list ($varkey, $varvalue) = each ($$var))
							{
								if (is_int ($varkey))
								{
									$temp[$varkey] = stripslashes (base64_decode(urldecode(($varvalue))));
								}
								else
								{
									$temp[stripslashes (base64_decode(urldecode(($varkey))))] = $varvalue;
								}
							}
							$this->$var = $temp;
						}
						elseif (isset ($$var))
						{
							$this->$var = stripslashes (base64_decode(urldecode ($$var)));
						}
					}
				}
			}

			// get appl. and user prefs 
			$pref = CreateObject ('phpgwapi.preferences', $GLOBALS['userinfo']['username']);
			$pref->read_repository (); 
//			$GLOBALS['phpgw']->hooks->single ('add_def_pref', $GLOBALS['appname']);
			$pref->save_repository (True);
			$pref_array = $pref->read_repository ();
			$this->prefs = $pref_array[$GLOBALS['appname']];
	
			//always show name
			
			$this->prefs[name] =1;


			if ($this->prefs['viewinnewwin'])
			{
				$this->target = '_blank';
			}
		}

		function index()
		{
			if ($noheader || $nofooter || ($this->download && (count ($this->fileman) > 0)))
			{
				$noheader = True;
				$nofooter = True;
				$noappheader= True;
				$nonavbar=True;
			}
			else
			{
				$GLOBALS['phpgw_info']['flags'] = array
				(
					'currentapp'	=> 'filemanager',
					'noheader'	=> $noheader,
					'nonavbar' => $nonavbar,
					'nofooter'	=> $nofooter,
					'noappheader'	=> $noappheader,
					'enable_browser_class'	=> True
				);

				$GLOBALS['phpgw']->common->phpgw_header();

			}

			//var_dump($GLOBALS[HTTP_POST_VARS]);
			
			
			# Page to process users
			# Code is fairly hackish at the beginning, but it gets better
			# Highly suggest turning wrapping off due to long SQL queries

			###
			# Some hacks to set and display directory paths correctly
			###

			if ($this->goto)
			{
				$this->path = $this->todir;
			}
			
			if (!$this->path)
			{
				$this->path = $this->bo->vfs->pwd ();

				if (!$this->path || $this->bo->vfs->pwd (array ('full' => False)) == '')
				{
					$this->path = $GLOBALS['homedir'];
				}
			}

			$this->bo->vfs->cd (array ('string' => False, 'relatives' => array (RELATIVE_NONE), 'relative' => False));
			$this->bo->vfs->cd (array ('string' => $this->path, 'relatives' => array (RELATIVE_NONE), 'relative' => False));

			$pwd = $this->bo->vfs->pwd ();

			if (!$this->cwd = substr ($this->path, strlen ($GLOBALS['homedir']) + 1))
			{
				$this->cwd = '/';
			}
			else
			{
				$this->cwd = substr ($pwd, strrpos ($pwd, '/') + 1);
			}

			$this->disppath = $this->path;

			/* This just prevents // in some cases */
			if ($this->path == '/')
			$this->dispsep = '';
			else
			$this->dispsep = '/';

			if (!($this->lesspath = substr ($this->path, 0, strrpos ($this->path, '/'))))
			$this->lesspath = '/';

			# Get their readable groups to be used throughout the script
			$groups = array();

			$groups = $GLOBALS['phpgw']->accounts->get_list ('groups');

			$this->readable_groups = array();

			while (list ($num, $account) = each ($groups))
			{
				if ($this->bo->vfs->acl_check (array ('owner_id' => $account['account_id'],	'operation' => PHPGW_ACL_READ)))
				{
					$this->readable_groups[$account['account_lid']] = Array('account_id' => $account['account_id'], 'account_name' => $account['account_lid']);
				}
			}

			$groups_applications = array ();

			while (list ($num, $group_array) = each ($this->readable_groups))
			{
				$group_id = $GLOBALS['phpgw']->accounts->name2id ($group_array['account_name']);

				$applications = CreateObject('phpgwapi.applications', $group_id);
				$groups_applications[$group_array['account_name']] = $applications->read_account_specific ();
			}

			# We determine if they're in their home directory or a group's directory,
			# and set the VFS working_id appropriately
			if ((preg_match ('+^'.$GLOBALS['fakebase'].'\/(.*)(\/|$)+U', $this->path, $matches)) && $matches[1] != $GLOBALS['userinfo']['account_lid'])
			{
				$this->bo->vfs->working_id = $GLOBALS['phpgw']->accounts->name2id ($matches[1]);
			}
			else
			{
				$this->bo->vfs->working_id = $GLOBALS['userinfo']['username'];
			}
			
			
			# FIXME # comment waht happens here	
			if ($this->path != $GLOBALS['homedir'] && $this->path != $GLOBALS['fakebase'] && $this->path != '/' && !$this->bo->vfs->acl_check (array ( 'string' => $this->path,	'relatives' => array (RELATIVE_NONE),'operation' => PHPGW_ACL_READ )))
			{
				$this->messages.= $GLOBALS['phpgw']->common->error_list (array (lang('You do not have access to %1', $this->path)));
				$this->html_link ('/index.php','menuaction=filemanager.uifilemanager.index','path='.$GLOBALS['homedir'], lang('Go to your home directory'));

				$GLOBALS['phpgw']->common->phpgw_footer ();
				$GLOBALS['phpgw']->common->phpgw_exit ();
			}

			$GLOBALS['userinfo']['working_id'] = $this->bo->vfs->working_id;
			$GLOBALS['userinfo']['working_lid'] = $GLOBALS['phpgw']->accounts->id2name ($GLOBALS['userinfo']['working_id']);

			# If their home directory doesn't exist, we try to create it
			# Same for group directories
			if (($this->path == $GLOBALS['homedir'])	&& !$this->bo->vfs->file_exists ($pim_tmp_arr))
			{
				$this->bo->vfs->override_acl = 1;

				if (!$this->bo->vfs->mkdir (array (
					'string' => $GLOBALS['homedir'],
					'relatives' => array (RELATIVE_NONE)	
				)))
				{
					$p = $this->bo->vfs->path_parts ($pim_tmp_arr);

					$this->messages= $GLOBALS['phpgw']->common->error_list (array (
						lang('Could not create directory %1', 
						$GLOBALS['homedir'] . ' (' . $p->real_full_path . ')'
					)));
				}

				$this->bo->vfs->override_acl = 0;
			}

			# Verify path is real
			if ($this->path != $GLOBALS['homedir'] && $this->path != '/' && $this->path != $GLOBALS['fakebase'])
			{
				if (!$this->bo->vfs->file_exists (array (
					'string' => $this->path,
					'relatives' => array (RELATIVE_NONE)
				)))
				{
					$this->messages = $GLOBALS['phpgw']->common->error_list (array (lang('Directory %1 does not exist', $this->path)));
					$this->html_link ('/index.php','menuaction=filemanager.uifilemanager.index','path='.$GLOBALS['homedir'], lang('Go to your home directory'));

					$GLOBALS['phpgw']->common->phpgw_footer ();
					$GLOBALS['phpgw']->common->phpgw_exit ();
				}
			}

			/* Update if they request it, or one out of 20 page loads */
			srand ((double) microtime() * 1000000);
			if ($update || rand (0, 19) == 4)
			{
				$this->bo->vfs->update_real (array (
					'string' => $this->path, 
					'relatives' => array (RELATIVE_NONE)
				));
			}

			# Check available permissions for $this->path, so we can disable unusable operations in user interface
			if ($this->bo->vfs->acl_check (array(
				'string' => $this->path,	
				'relatives' => array (RELATIVE_NONE),
				'operation' => PHPGW_ACL_ADD
			)))
			{
				$this->can_add = True;
			}


			# Default is to sort by name
			if (!$this->sortby)
			{
				$this->sortby = 'name';
			}

			if($this->debug) $this->debug_filemanager();   

			# main action switch
			// FIXME this will become a switch
			if($this->newfile && $this->newfile_or_dir) // create new textfile
			{
				$this->createfile();
			}
			elseif($this->newfile_or_dir && $this->newdir)
			{
				$this->createdir();
			}
			elseif ($this->uploadprocess)
			{
				$this->fileUpload();
			}
			elseif ($this->upload || $this->show_upload_boxes)
			{
				$this->showUploadboxes();
			}
			elseif ($this->copy_to)
			{
				$this->copyTo();
			}
			elseif ($this->move_to)
			{
				$this->moveTo();
			}
			elseif ($this->download)
			{
				$this->download();
			}
			elseif ($this->renamefiles)
			{
				$this->rename();
			}
			elseif ($this->comment_files)
			{
				$this->editComment();
			}
			elseif ($this->edit_cancel)
			{
				$this->readFilesInfo();
				$this->fileListing();
			}
			elseif ($this->edit || $this->edit_preview || $this->edit_save)
			{
				$this->edit();
			}
			elseif ($this->delete)
			{
				$this->delete();
			}
			else
			{
				$this->readFilesInfo();
				$this->fileListing();
			}

		}

		function fileListing()
		{
			$this->t->set_file(array('filemanager_list_t' => 'filelisting.tpl'));
			$this->t->set_block('filemanager_list_t','filemanager_header','filemanager_header');
			$this->t->set_block('filemanager_list_t','column','column');
			$this->t->set_block('filemanager_list_t','row','row');
			$this->t->set_block('filemanager_list_t','filemanager_footer','filemanager_footer');

			$vars['form_action']=$this->encode_href ('/index.php', 'menuaction=filemanager.uifilemanager.index','path='.$this->path);
			if ($this->numoffiles || $this->cwd)
			{
				while (list ($num, $name) = each ($this->prefs))
				{
					if ($name)
					{
						$columns++;
					}
				}
				$columns++;

				$vars[toolbar0]=$this->toolbar('location');
				$vars[toolbar1]=$this->toolbar('list_nav');
				$vars[messages]=$this->messages;
				
				$this->t->set_var($vars);
				$this->t->pparse('out','filemanager_header');


				###
				# Start File Table Column Headers
				# Reads values from $file_attributes array and preferences
				###
				$this->t->set_var('actions',lang('select'));

				reset ($this->bo->file_attributes);

				if($this->numoffiles>0)
				{
					while (list ($internal, $displayed) = each ($this->bo->file_attributes))
					{
						if ($this->prefs[$internal])
						{
							$col_data='<span><a href="'.$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path.'&sortby='.$internal).'">'.$displayed.'</a></span>';
							$this->t->set_var('col_data',$col_data);
							$this->t->parse('columns','column',True);
						}
					}

					$this->t->set_var('row_tr_color','#cbcbcb');
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');
				}
				else
				{
					$lang_nofiles=lang('No files in this directory.');
				}
				$vars[lang_no_files]=$lang_nofiles;
						

				if ($this->prefs['dotdot'] && $this->prefs['name'] && $this->path != '/')
				{
					$this->t->set_var('actions','');

					$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->lesspath);

					$col_data='<a href="'.$link.'"><img src="'.$GLOBALS['phpgw']->common->image('filemanager','folder').' "alt="'.lang('Folder').'" /></a>';
					$col_data.='&nbsp;<a href="'.$link.'">..</a>';

					$this->t->set_var('col_data',$col_data);
					$this->t->parse('columns','column');

					if ($this->prefs['mime_type'])
					{
						$col_data=lang('Directory');
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}


					$this->t->set_var('row_tr_color',$tr_color);
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');
				}

				# List all of the files, with their attributes
				reset ($this->files_array);
				for ($i = 0; $i != $this->numoffiles; $i++)
				{
					$files = $this->files_array[$i];

					if ($this->rename || $this->edit_comments)
					{
						unset ($this_selected);
						unset ($renamethis);
						unset ($edit_this_comment);

						for ($j = 0; $j != $this->numoffiles; $j++)
						{
							if ($this->fileman[$j] == $files['name'])
							{
								$this_selected = 1;
								break;
							}
						}

						if ($this->rename && $this_selected)
						{
							$renamethis = 1;
						}
						elseif ($this->edit_comments && $this_selected)
						{
							$edit_this_comment = 1;
						}
					}

					if (!$this->prefs['dotfiles'] && ereg ("^\.", $files['name']))
					{
						continue;
					}

					# Checkboxes
					if (!$this->rename && !$this->edit_comments && $this->path != $GLOBALS['fakebase'] && $this->path != '/')
					{
						$cbox='<input type="checkbox" name="fileman['.$i.']" value="'.$files['name'].'">';
						$this->t->set_var('actions',$cbox);
					}
					elseif ($renamethis)
					{
						$cbox=$this->html_form_input ('hidden', 'fileman[' . base64_encode ($files['name']) . ']', $files['name'], NULL, NULL, 'checked');
						$this->t->set_var('actions',$cbox);
					}
					else
					{
						$this->t->set_var('actions','');
					}

					# File name and icon
					if ($renamethis)
					{
						$col_data=$this->mime_icon($files['mime_type']);
						$col_data.='<input type="text" maxlength="255" name="renamefiles[' . $files['name'] . ']" value="'.$files['name'].'">';
					}
					else
					{
						if ($files['mime_type'] == 'Directory')
						{
							$link=$this->encode_href ('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path.$this->dispsep.$files['name']);

							$icon=$this->mime_icon($files['mime_type']);

							$col_data='<a href="'.$link.'">'.$icon.'</a>&nbsp;';
							$col_data.='<a href="'.$link.'">'.$files['name'].'</a>&nbsp;';
						}
						else
						{

							if ($this->prefs['viewonserver'] && isset ($GLOBALS['filesdir']) && !$files['link_directory'])
							{
								#FIXME
								$clickview = $GLOBALS['filesdir'].$pwd.'/'.$files['name'];

								if ($phpwh_debug)
								{
									echo 'Setting clickview = '.$clickview.'<br>'."\n";
									$this->html_link ($clickview,'', '',$files['name'], 0, 1, 0, '');
								}
							}
							else
							{
								$icon=$this->mime_icon($files['mime_type']);
								$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.view','file='.$files['name'].'&path='.$this->path);

								$col_data='<a href="'.$link.'" target="'.$this->target.'">'.$icon.'</a>&nbsp;<a href="'.$link.'" target="'.$this->target.'">'.$files['name'].'</a>';
							}
						}
					}

					$this->t->set_var('col_data',$col_data);
					$this->t->parse('columns','column');

					# MIME type
					if ($this->prefs['mime_type'])
					{
						$col_data=$files['mime_type'];
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# File size
					if ($this->prefs['size'])
					{
						$tmp_arr=array(
							'string'	=> $files['directory'] . '/' . $files['name'],	
							'relatives'	=> array (RELATIVE_NONE)							
						);

						$size = $this->bo->vfs->get_size($tmp_arr);

						$col_data=$this->bo->borkb ($size);

						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Date created
					if ($this->prefs['created'])
					{
						$col_data=$files['created'];
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Date modified
					if ($this->prefs['modified'])
					{
						if ($files['modified'] != '0000-00-00')	$col_data=$files['modified'];
						else $col_data='';

						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Owner name
					if ($this->prefs['owner'])
					{
						$this->t->set_var('col_data',$GLOBALS['phpgw']->accounts->id2name ($files['owner_id']));
						$this->t->parse('columns','column',True);
					}

					# Creator name
					if ($this->prefs['createdby_id'])
					{
						$this->html_table_col_begin ();
						if ($files['createdby_id'])
						{
							$col_data=$GLOBALS['phpgw']->accounts->id2name ($files['createdby_id']);
						}
						else $col_data='';

						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Modified by name
					if ($this->prefs['modifiedby_id'])
					{
						if ($files['modifiedby_id'])
						{
							$col_data=$GLOBALS['phpgw']->accounts->id2name ($files['modifiedby_id']);
						}
						else $col_data='';
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Application
					if ($this->prefs['app'])
					{
						$col_data=$files['app'];
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Comment
					if ($this->prefs['comment'])
					{
						if ($edit_this_comment)
						{
							$col_data='<input type="text" name="comment_files[' . $files['name'] . ']" value="'.$files['comment'].'" maxlength="255">'	;
						}
						else
						{
							$col_data=$files['comment'];
						}
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Version
					if ($this->prefs['version'])
					{
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.history','file='.$files['name'].'&path='.$this->path);
						$col_data='<a href="'.$link.'" target="_blank">'.$files['version'].'</a>';
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

							

					if ($files['mime_type'] == 'Directory')
					{
						$usedspace += $fileinfo[0];
					}
					else
					{
						$usedspace += $files['size'];
					}

					$this->t->set_var('row_tr_color','');
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');
				}

				// when renaming or changing comments render extra sumbmit button
				if ($this->rename || $this->edit_comments)
				{
					$col_data='<br/><input type="submit" name="save_changes" value="'.lang('Save changes').'">';
					$this->t->set_var('col_data',$col_data);
					$this->t->parse('columns','column');
					$this->t->set_var('row_tr_color','');
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');
				}


			}


			
			// The file and directory information
			$vars[lang_files_in_this_dir]=lang('Files is this directory');
			$vars[files_in_this_dir]=$this->numoffiles;

			$vars[lang_used_space]=lang('Used space');
			$vars[used_space]=$this->bo->borkb ($usedspace, NULL, 1);

			if ($this->path == $GLOBALS['homedir'] || $this->path == $GLOBALS['fakebase'])
			{
				$vars[lang_unused_space]=lang('Unused space');
				$vars[unused_space]=$this->bo->borkb ($GLOBALS['userinfo']['hdspace'] - $usedspace, NULL, 1);

				$tmp_arr=array (
					'string'	=> $this->path,
					'relatives'	=> array (RELATIVE_NONE)
				);

				$ls_array = $this->bo->vfs->ls ($tmp_arr);


				$vars[lang_total_files]=lang('Total Files');
				$vars[total_files]=	count ($ls_array);

			}

			$this->t->set_var($vars);
			$this->t->pparse('out','filemanager_footer');

			$GLOBALS['phpgw']->common->phpgw_footer ();	
			$GLOBALS['phpgw']->common->phpgw_exit ();

		}

		function readFilesInfo()
		{
			// start files info
			
			# Read in file info from database to use in the rest of the script
			# $fakebase is a special directory.  In that directory, we list the user's
			# home directory and the directories for the groups they're in
			$this->numoffiles = 0;
			if ($this->path == $GLOBALS['fakebase'])
			{
				if (!$this->bo->vfs->file_exists (array ('string' => $GLOBALS['homedir'], 'relatives' => array (RELATIVE_NONE))))
				{
					$this->bo->vfs->mkdir (array ('string' => $GLOBALS['homedir'], 'relatives' => array (RELATIVE_NONE)));
				}

				$ls_array = $this->bo->vfs->ls (array (					'string'	=> $GLOBALS['homedir'],					'relatives'	=> array (RELATIVE_NONE),					'checksubdirs'	=> False,					'nofiles'	=> True				)			);
				$this->files_array[] = $ls_array[0];
				$this->numoffiles++;

				reset ($this->readable_groups);
				while (list ($num, $group_array) = each ($this->readable_groups))
				{
					# If the group doesn't have access to this app, we don't show it
					if (!$groups_applications[$group_array['account_name']][$GLOBALS['appname']]['enabled'])
					{
						continue;
					}


					if (!$this->bo->vfs->file_exists (array ('string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],'relatives'	=> array (RELATIVE_NONE)	))		)
					{
						$this->bo->vfs->override_acl = 1;
						$this->bo->vfs->mkdir (array (						'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],						'relatives'	=> array (RELATIVE_NONE)					)				);

						$this->bo->vfs->override_acl = 0;

						$this->bo->vfs->set_attributes (array (			'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],			'relatives'	=> array (RELATIVE_NONE),			'attributes'	=> array (				'owner_id' => $group_array['account_id'],				'createdby_id' => $group_array['account_id']			)		)	);
					}

					$ls_array = $this->bo->vfs->ls (array (			'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],			'relatives'	=> array (RELATIVE_NONE),			'checksubdirs'	=> False,			'nofiles'	=> True		)	);

					$this->files_array[] = $ls_array[0];

					$this->numoffiles++;
				}
			}
			else
			{
				$ls_array = $this->bo->vfs->ls (array (			'string'	=> $this->path,			'relatives'	=> array (RELATIVE_NONE),			'checksubdirs'	=> False,			'nofiles'	=> False,			'orderby'	=> $this->sortby		)	);

				if ($phpwh_debug)
				{
					echo '# of files found in "'.$this->path.'" : '.count($ls_array).'<br>'."\n";
				}

				while (list ($num, $file_array) = each ($ls_array))
				{
					$this->numoffiles++;
					$this->files_array[] = $file_array;
					if ($phpwh_debug)
					{
						echo 'Filename: '.$file_array['name'].'<br>'."\n";
					}
				}
			}

			if (!is_array ($this->files_array))
			{
				$this->files_array = array ();
			}
			// end file count
		}
		function toolbar($type)
		{
			switch($type)
			{
				case 'location':
					$toolbar='
					<div id="fmLocation">
					'.lang('location').':&nbsp;<input id="fmInputLocation" type="text" size="50" name="location" value="'.$this->disppath.'"/>
					</div>';
					break;
				case 'list_nav':
					$toolbar='
					<table cellspacing="1" cellpadding="0" border="0">				
					<tr>
					<td><img alt="spacer" src="'.$GLOBALS['phpgw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

					// go up icon when we're not at the top
					if ($this->path != '/')
					{							
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->lesspath);
						$toolbar.=$this->buttonImage($link,'up',lang('go up'));
					}

					// go home icon when we're not home already
					if ($this->path == $GLOBALS['homedir'])
					{
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$GLOBALS['homedir']);
						$toolbar.=$this->buttonImage($link,'home',lang('go home'));
					}

					// reload button with this url
					$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path);
					$toolbar.=$this->buttonImage($link,'reload',lang('reload'));

					// selectbox for change/move/and copy to
					$dirs_options=$this->all_other_directories_options();
					$toolbar.='<td><select name="todir">'.$dirs_options.'</select></td>';

					$toolbar.=$this->inputImage('goto','goto','Quick jump to');

					if (!$this->rename && !$this->edit_comments)
					{
						// copy and move buttons
						if ($this->path != '/' && $this->path != $GLOBALS['fakebase'])
						{
							$toolbar.=$this->inputImage('copy_to','copy_to',lang('Copy to'));
							$toolbar.=$this->inputImage('move_to','move_to',lang('Move to'));
						}


						// submit buttons for
						if ($this->path != '/' && $this->path != $GLOBALS['fakebase'])
						{
							if (!$this->rename && !$this->edit_comments)
							{
								// edit text file button
								$toolbar.=$this->inputImage('edit','edit',lang('edit'));
							}

							if (!$this->edit_comments)
							{
								$toolbar.=$this->inputImage('rename','rename',lang('Rename'));
							}

							if (!$this->rename && !$this->edit_comments)
							{
								$toolbar.=$this->inputImage('delete','delete',lang('Delete'));
							}

							if (!$this->rename)
							{
								$toolbar.=$this->inputImage('edit_comments','edit_comments',lang('Edit comments'));
							}
						}

						// create dir and file button
						if ($this->path != '/' && $this->path != $GLOBALS['fakebase'] && $this->can_add)
						{
							$toolbar.='<td><input type=text size="15" name="newfile_or_dir" value="" /></td>';
							$toolbar.=$this->inputImage('newdir','createdir',lang('Create Folder'));
							$toolbar.=$this->inputImage('newfile','createfile',lang('Create File'));
						}

						$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['phpgw']->common->image('filemanager','spacer').'" height="27" width="3"></td>';

						// download button
						if ($this->path != '/' && $this->path != $GLOBALS['fakebase'] && $this->can_add)
						{
							$toolbar.=$this->inputImage('download','download',lang('Download'));
						}
						// upload button
						$toolbar.=$this->inputImage('upload','upload',lang('Upload'));

					}
		
					$toolbar.='</tr></table>';
					break;
				default:$x='';
				}


			if($toolbar)
			{
				return $toolbar;
			}
		}
		

	// move to bo
	# Handle File Uploads
	function fileUpload()
		{
			if ($this->path != '/' && $this->path != $GLOBALS['fakebase'])
			{
				for ($i = 0; $i != $this->show_upload_boxes; $i++)
				{
					if ($badchar = $this->bo->bad_chars ($_FILES['upload_file']['name'][$i], True, True))
					{
						$this->messages.= $GLOBALS['phpgw']->common->error_list (array ($this->bo->html_encode (lang('File names cannot contain "%1"', $badchar), 1)));

						continue;
					}

					# Check to see if the file exists in the database, and get its info at the same time
					$ls_array = $this->bo->vfs->ls (array (
						'string'=> $this->path . '/' . $_FILES['upload_file']['name'][$i],	
						'relatives'	=> array (RELATIVE_NONE),
						'checksubdirs'	=> False,	
						'nofiles'	=> True		
					));

					$fileinfo = $ls_array[0];

					if ($fileinfo['name'])
					{
						if ($fileinfo['mime_type'] == 'Directory')
						{
							$this->messages.= $GLOBALS['phpgw']->common->error_list (array (lang('Cannot replace %1 because it is a directory', $fileinfo['name'])));
							continue;
						}
					}

					if ($_FILES['upload_file']['size'][$i] > 0)
					{
						if ($fileinfo['name'] && $fileinfo['deleteable'] != 'N')
						{
							$tmp_arr=array(	
								'string'=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array (RELATIVE_ALL),
								'attributes'	=> array (
									'owner_id' => $GLOBALS['userinfo']['username'],
									'modifiedby_id' => $GLOBALS['userinfo']['username'],
									'modified' => $this->now,
									'size' => $_FILES['upload_file']['size'][$i],
									'mime_type' => $_FILES['upload_file']['type'][$i],
									'deleteable' => 'Y',
									'comment' => stripslashes ($upload_comment[$i])
								)
							);
							$this->bo->vfs->set_attributes($tmp_arr);

							$tmp_arr=array (
								'from'	=> $_FILES['upload_file']['tmp_name'][$i],
								'to'	=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
							);		
							$this->bo->vfs->cp($tmp_arr);

							$this->messages.=lang('Replaced %1', $this->disppath.'/'.$_FILES['upload_file']['name'][$i]);
						}
						else
						{

							$this->bo->vfs->cp (array (
								'from'=> $_FILES['upload_file']['tmp_name'][$i],
								'to'=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
							));

							$this->bo->vfs->set_attributes (array (	
								'string'=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array (RELATIVE_ALL),
								'attributes'=> array (
									'mime_type' => $_FILES['upload_file']['type'][$i],
									'comment' => stripslashes ($upload_comment[$i])
								)
							));

							$this->messages.=lang('Created %1,%2', $this->disppath.'/'.$_FILES['upload_file']['name'][$i], $_FILES['upload_file']['size'][$i]);
						}
					}
					elseif ($_FILES['upload_file']['name'][$i])
					{
						$this->bo->vfs->touch (array (
							'string'=> $_FILES['upload_file']['name'][$i],
							'relatives'	=> array (RELATIVE_ALL)
						));

						$this->bo->vfs->set_attributes (array (
							'string'=> $_FILES['upload_file']['name'][$i],
							'relatives'	=> array (RELATIVE_ALL),
							'attributes'=> array (
								'mime_type' => $_FILES['upload_file']['type'][$i],
								'comment' => $upload_comment[$i]
							)
						));
						
						$this->messages.=lang('Created %1,%2', $this->disppath.'/'.$_FILES['upload_file']['name'][$i], $file_size[$i]);
					}
				}
			
				$this->readFilesInfo();
				$this->filelisting();
			}

		}

		# Handle Editing comments
		function editComment()
		{
			while (list ($file) = each ($this->comment_files))
			{
				if ($badchar = $this->bo->bad_chars ($this->comment_files[$file], False, True))
				{
					$this->messages=$GLOBALS['phpgw']->common->error_list (array ($file . $this->bo->html_encode (': ' . lang('Comments cannot contain "%1"', $badchar), 1)));
					continue;
				}

				$this->bo->vfs->set_attributes (array (						'string'	=> $file,						'relatives'	=> array (RELATIVE_ALL),						'attributes'	=> array (							'comment' => stripslashes ($this->comment_files[$file])						)					)				);

				$this->messages=lang('Updated comment for %1', $this->path.'/'.$file);
			}
			
			$this->readFilesInfo();
			$this->filelisting();
		}

		# Handle Renaming Files and Directories
		function rename()
		{
				while (list ($from, $to) = each ($this->renamefiles))
				{
					if ($badchar = $this->bo->bad_chars ($to, True, True))
					{
						$this->messages=$GLOBALS['phpgw']->common->error_list (array ($this->bo->html_encode (lang('File names cannot contain "%1"', $badchar), 1)));
						continue;
					}

					if (ereg ("/", $to) || ereg ("\\\\", $to))
					{
						$this->messages=$GLOBALS['phpgw']->common->error_list (array (lang("File names cannot contain \\ or /")));
					}
					elseif (!$this->bo->vfs->mv (array (					'from'	=> $from,					'to'	=> $to				))			)
					{
						$this->messages= $GLOBALS['phpgw']->common->error_list (array (lang('Could not rename %1 to %2', $this->disppath.'/'.$from, $this->disppath.'/'.$to)));
					}
					else 
					{
						$this->messages=lang('Renamed %1 to %2', $this->disppath.'/'.$from, $this->disppath.'/'.$to);
					}
				}
				$this->readFilesInfo();
				$this->filelisting();
		}

		# Handle Moving Files and Directories
		function moveTo()
		{
				while (list ($num, $file) = each ($this->fileman))
				{
					if ($this->bo->vfs->mv (array (
						'from'	=> $file,		
						'to'	=> $this->todir . '/' . $file,
						'relatives'	=> array (RELATIVE_ALL, RELATIVE_NONE)
					)))
					{
						$moved++;
						$this->messages=lang('Moved %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file);
					}
					else
					{
						$this->messages = $GLOBALS['phpgw']->common->error_list (array (lang('Could not move %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file)));
					}
				}

				if ($moved)
				{
					$x=0;	
				}

				$this->readFilesInfo();
				$this->filelisting();
		}

		// Handle Copying of Files and Directories
		function copyTo()
		{
				while (list ($num, $file) = each ($this->fileman))
				{
					if ($this->bo->vfs->cp (array (			'from'	=> $file,			'to'	=> $this->todir . '/' . $file,			'relatives'	=> array (RELATIVE_ALL, RELATIVE_NONE)		))	)
					{
						$copied++;
						$this->message .= lang('Copied %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file);
					}
					else
					{
						$this->message .= $GLOBALS['phpgw']->common->error_list (array (lang('Could not copy %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file)));
					}
				}

				if ($copied)
				{
					$x=0;	
				}

				$this->readFilesInfo();
				$this->filelisting();

		}

		function createdir()
		{
			if ($this->newdir && $this->newfile_or_dir)
			{
				if ($this->bo->badchar = $this->bo->bad_chars ($this->newfile_or_dir, True, True))
				{
					$this->messages= $GLOBALS['phpgw']->common->error_list (array ($this->bo->html_encode (lang('Directory names cannot contain "%1"', $badchar), 1)));
				}

				if ($$this->newfile_or_dir[strlen($this->newfile_or_dir)-1] == ' ' || $this->newfile_or_dir[0] == ' ')
				{
					$this->messages= $GLOBALS['phpgw']->common->error_list (array (lang('Cannot create directory because it begins or ends in a space')));
				}

				$ls_array = $this->bo->vfs->ls (array (					
					'string'	=> $this->path . '/' . $this->newfile_or_dir,	
					'relatives'	=> array (RELATIVE_NONE),
					'checksubdirs'	=> False,	
					'nofiles'	=> True
				));

				$fileinfo = $ls_array[0];

				if ($fileinfo['name'])
				{
					if ($fileinfo['mime_type'] != 'Directory')
					{
						$this->messages= $GLOBALS['phpgw']->common->error_list (array (
							lang('%1 already exists as a file', 
							$fileinfo['name'])
						));
					}
					else
					{
						$this->messages= $GLOBALS['phpgw']->common->error_list (array (lang('Directory %1 already exists', $fileinfo['name'])));
					}
				}
				else
				{
					if ($this->bo->vfs->mkdir (array ('string' => $this->newfile_or_dir)))
					{
						$this->messages=lang('Created directory %1', $this->disppath.'/'.$this->newfile_or_dir);
					}
					else
					{
						$this->messages=$GLOBALS['phpgw']->common->error_list (array (lang('Could not create %1', $this->disppath.'/'.$this->newfile_or_dir)));
					}
				}

				$this->readFilesInfo();
				$this->filelisting();
			}

		}

		function delete()
		{
			for ($i = 0; $i != $this->numoffiles; $i++)
			{
				if ($this->fileman[$i])
				{
					if ($this->bo->vfs->delete (array ('string' => $this->fileman[$i])))
					{
						$this->messages .= lang('Deleted %1', $this->disppath.'/'.$this->fileman[$i]).'<br/>';
					}
					else
					{
						$this->messages=$GLOBALS['phpgw']->common->error_list (array (lang('Could not delete %1', $this->disppath.'/'.$this->fileman[$i])));
					}
				}
			}
			$this->readFilesInfo();
			$this->filelisting();
		}


		function debug_filemanager()
		{
			error_reporting (8);

			echo "<b>Filemanager debug:</b><br>
			path: {$this->path}<br>
			disppath: {$this->disppath}<br>
			cwd: {$this->cwd}<br>
			lesspath: {$this->lesspath}
			<p>
			<b>eGroupware debug:</b><br>
			real getabsolutepath: " . $this->bo->vfs->getabsolutepath (array ('target' => False, 'mask' => False, 'fake' => False)) . "<br>
			fake getabsolutepath: " . $this->bo->vfs->getabsolutepath (array ('target' => False)) . "<br>
			appsession: " . $GLOBALS['phpgw']->session->appsession ('vfs','') . "<br>
			pwd: " . $this->bo->vfs->pwd () . "<br>";

			echo '<p></p>';
			var_dump($this);


		}

		function showUploadboxes()
		{
			$this->t->set_file(array('upload' => 'upload.tpl'));
			$this->t->set_block('upload','upload_header','upload_header');
			$this->t->set_block('upload','row','row');
			$this->t->set_block('upload','upload_footer','upload_footer');
			
			# Decide how many upload boxes to show
			if (!$this->show_upload_boxes || $this->show_upload_boxes <= 0)
			{
				if (!$this->show_upload_boxes = $this->prefs['show_upload_boxes'])
				{
					$this->show_upload_boxes = 1;
				}
			}

			# Show file upload boxes. Note the last argument to html ().  Repeats $this->show_upload_boxes times
			if ($this->path != '/' && $this->path != $GLOBALS['fakebase'] && $this->can_add)
			{
				$vars[form_action]=$GLOBALS[phpgw]->link('/index.php','menuaction=filemanager.uifilemanager.index');
				$vars[path]=$this->path;
				$vars[lang_file]=lang('File');
				$vars[lang_comment]=lang('Comment');
				$vars[num_upload_boxes]=$this->show_upload_boxes;
				$this->t->set_var($vars);
				$this->t->pparse('out','upload_header');

				for($i=0;$i<$this->show_upload_boxes;$i++)
				{
					$this->t->set_var('row_tr_color',$tr_color);
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');
				}

				$vars[lang_upload]=lang('Upload files');
				$vars[change_upload_boxes].=lang('Show') . '&nbsp;';
				$links.= $this->html_link ('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=5', '5');
				$links.='&nbsp;';
				$links.= $this->html_link ('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=10', '10');
				$links.='&nbsp;';
				$links.= $this->html_link ('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=20', '20');
				$links.='&nbsp;';
				$links.= $this->html_link ('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=50', '50');
				$links.='&nbsp;';
				$links.= lang('upload fields');
				$vars[change_upload_boxes].=$links;
				$this->t->set_var($vars);
				$this->t->pparse('out','upload_footer');
		}
		}

		/* create textfile */
		function createfile()
		{
			$this->createfile=$this->newfile_or_dir;
			if ($this->createfile)
			{
				if ($badchar = $this->bo->bad_chars ($this->createfile, True, True))
				{
					$this->messages = $GLOBALS['phpgw']->common->error_list (array (
						lang('File names cannot contain "%1"',$badchar), 
						1)
					);

					$this->fileListing();
				}

				if ($this->bo->vfs->file_exists (array (
					'string'=> $this->createfile,
					'relatives'	=> array (RELATIVE_ALL)	
				)))
				{
					 $this->messages=$GLOBALS['phpgw']->common->error_list (array (lang('File %1 already exists. Please edit it or delete it first.', $this->createfile)));
					$this->fileListing();
				}


				if ($this->bo->vfs->touch (array (	'string'	=> $this->createfile,	'relatives'	=> array (RELATIVE_ALL)	))	)
				{
					$this->fileman = array ();
					$this->fileman[0] = $this->createfile;
					$this->edit = 1;
					$this->numoffiles++;
					$this->edit();
				}
				else
				{
					$this->messages=$GLOBALS['phpgw']->common->error_list (array (lang('File %1 could not be created.', $this->createfile)));
					$this->fileListing();
				}
			}
		}

		# Handle Editing files
		function edit()
		{
			$this->readFilesInfo();

			$this->t->set_file(array('filemanager_edit' => 'edit_file.tpl'));
			$this->t->set_block('filemanager_edit','row','row');

			$vars[preview_content]='';
			if ($this->edit_file)
			{
				$this->edit_file_content = stripslashes ($this->edit_file_content);
			}

			if ($this->edit_preview)
			{
				$content = $this->edit_file_content;

				$vars[lang_preview_of]=lang('Preview of %1', $this->path.'/'.$edit_file);

				$vars[preview_content]=nl2br($content);
			}
			elseif ($this->edit_save || $this->edit_save_done)
			{
				$content = $this->edit_file_content;

				if ($this->bo->vfs->write (array (
					'string'	=> $this->edit_file,
					'relatives'	=> array (RELATIVE_ALL),
					'content'	=> $content
				))
			)
			{
				$this->messages=lang('Saved %1', $this->path.'/'.$this->edit_file);

				if($this->edit_save_done)
				{
					$this->readFilesInfo();
					$this->fileListing();
					exit;
				}
			}
			else
			{
				$this->messages=lang('Could not save %1', $this->path.'/'.$this->edit_file);
			}
		}

		# Now we display the edit boxes and forms
		for ($j = 0; $j != $this->numoffiles; $j++)
		{
			# If we're in preview or save mode, we only show the file
			# being previewed or saved
			if ($this->edit_file && ($this->fileman[$j] != $this->edit_file))
			{
				continue;
			}

			if ($this->fileman[$j] && $this->bo->vfs->file_exists (array (				'string'	=> $this->fileman[$j],				'relatives'	=> array (RELATIVE_ALL)			))		)
			{
				if ($this->edit_file)
				{
					$content = stripslashes ($this->edit_file_content);
				}
				else
				{
					$content = $this->bo->vfs->read (array ('string' => $this->fileman[$j]));
				}

				$vars[form_action]= $GLOBALS['phpgw']->link('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path);
				$vars[edit_file]=$this->fileman[$j];

				# We need to include all of the fileman entries for each file's form,
				# so we loop through again
				for ($i = 0; $i != $this->numoffiles; $i++)
				{
					if($this->fileman[$i]) $value='value="'.$this->fileman[$i].'"';
					$vars[filemans_hidden]='<input type="hidden" name="fileman['.$i.']" '.$value.' />';

				}

				$vars[file_content]=$content;


				$vars[buttonPreview]=$this->inputImage('edit_preview','edit_preview',lang('Preview %1', $this->bo->html_encode ($this->fileman[$j], 1)));
				$vars[buttonSave]=$this->inputImage('edit_save','save',lang('Save %1', $this->bo->html_encode ($this->fileman[$j], 1)));
				$vars[buttonDone]=$this->inputImage('edit_save_done','ok',lang('Save %1, and go back to file listing ', $this->bo->html_encode ($this->fileman[$j], 1)));
				$vars[buttonCancel]=$this->inputImage('edit_cancel','cancel',lang('Cancel editing %1 without saving', $this->bo->html_encode ($this->fileman[$j], 1)));
				$this->t->set_var($vars);
				$this->t->parse('rows','row');
				$this->t->pparse('out','row');

			}
		}
	}



	function history()
	{
		if ($this->file)
		{
			$journal_array = $this->bo->vfs->get_journal (array (
				'string'	=> $this->file,
				'relatives'	=> array (RELATIVE_ALL)
			));

			if (is_array ($journal_array))
			{
				$this->html_table_begin ();
				$this->html_table_row_begin ();
				$this->html_table_col_begin ();
				echo lang('Date');
				$this->html_table_col_end ();
				$this->html_table_col_begin ();
				echolang('Version');
				$this->html_table_col_end ();
				$this->html_table_col_begin ();
				echo lang('Who');
				$this->html_table_col_end ();
				$this->html_table_col_begin ();
				echo lang('Operation');
				$this->html_table_col_end ();
				$this->html_table_row_end ();

				while (list ($num, $journal_entry) = each ($journal_array))
				{
					$this->html_table_row_begin ();
					$this->html_table_col_begin ();
					$this->bo->html_text ($journal_entry['created'] . '&nbsp;&nbsp;&nbsp;');
					$this->html_table_col_end ();
					$this->html_table_col_begin ();
					$this->bo->html_text ($journal_entry['version'] . '&nbsp;&nbsp;&nbsp;' );
					$this->html_table_col_end ();
					$this->html_table_col_begin ();
					$this->bo->html_text ($GLOBALS['phpgw']->accounts->id2name ($journal_entry['owner_id']) . '&nbsp;&nbsp;&nbsp;');
					$this->html_table_col_end ();
					$this->html_table_col_begin ();
					$this->bo->html_text ($journal_entry['comment']);
					$this->html_table_col_end ();
				}

				$this->html_table_end ();
        $GLOBALS['phpgw']->common->phpgw_footer ();
		        $GLOBALS['phpgw']->common->phpgw_exit ();
			}
			else
			{
				echo lang('No version history for this file/directory');
			}

		}

	}

	function view()
	{
		
		if ($this->file)
		{
			$ls_array = $this->bo->vfs->ls (array (
				'string'	=> $this->path.'/'.$this->file,
				'relatives'	=> array (RELATIVE_ALL),
				'checksubdirs'	=> False,
				'nofiles'	=> True
			));

			if ($ls_array[0]['mime_type'])
			{
				$mime_type = $ls_array[0]['mime_type'];
			}
			elseif ($this->prefs['viewtextplain'])
			{
				$mime_type = 'text/plain';
			}

			header('Content-type: ' . $mime_type);
			echo $this->bo->vfs->read (array (
				'string'	=> $this->path.'/'.$this->file,
				'relatives'	=> array (RELATIVE_NONE)
			));
			$GLOBALS['phpgw']->common->phpgw_exit ();
		}
	}

	function download()
	{
		for ($i = 0; $i != $this->numoffiles; $i++)
		{
			if (!$this->fileman[$i])
			{
				continue;
			}

			$download_browser = CreateObject ('phpgwapi.browser');
			$download_browser->content_header ($this->fileman[$i]);
			echo $this->bo->vfs->read (array ('string' => $this->fileman[$i]));
			$GLOBALS['phpgw']->common->phpgw_exit ();
		}
	}

	//give back an array with all directories except current and dirs that are not accessable
	function all_other_directories_options()
	{
		# First we get the directories in their home directory
		$dirs = array ();
		$dirs[] = array ('directory' => $GLOBALS['fakebase'], 'name' => $GLOBALS['userinfo']['account_lid']);

		$tmp_arr=array (
			'string'	=> $GLOBALS['homedir'],
			'relatives'	=> array (RELATIVE_NONE),
			'checksubdirs'	=> True,
			'mime_type'	=> 'Directory'
		);

		$ls_array = $this->bo->vfs->ls ($tmp_arr);

		while (list ($num, $dir) = each ($ls_array))
		{
			$dirs[] = $dir;
		}


		# Then we get the directories in their readable groups' home directories
		reset ($this->readable_groups);
		while (list ($num, $group_array) = each ($this->readable_groups))
		{
			# Don't list directories for groups that don't have access
			if (!$groups_applications[$group_array['account_name']][$GLOBALS['appname']]['enabled'])
			{
				continue;
			}

			$dirs[] = array ('directory' => $GLOBALS['fakebase'], 'name' => $group_array['account_name']);

			$tmp_arr=array (
				'string'	=> $GLOBALS['fakebase'].'/'.$group_array['account_name'],
				'relatives'	=> array (RELATIVE_NONE),
				'checksubdirs'	=> True,
				'mime_type'	=> 'Directory'
			);

			$ls_array = $this->bo->vfs->ls ($tmp_arr);
			while (list ($num, $dir) = each ($ls_array))
			{
				$dirs[] = $dir;
			}
		}

		reset ($dirs);
		while (list ($num, $dir) = each ($dirs))
		{
			if (!$dir['directory'])
			{
				continue;
			}

			# So we don't display //
			if ($dir['directory'] != '/')
			{
				$dir['directory'] .= '/';
			}

			# No point in displaying the current directory, or a directory that doesn't exist
			if ((($dir['directory'] . $dir['name']) != $this->path)		&& $this->bo->vfs->file_exists (array (			'string'	=> $dir['directory'] . $dir['name'],			'relatives'	=> array (RELATIVE_NONE)		))	)
			{
				//FIXME replace the html_form_option function
				$options.=$this->html_form_option ($dir['directory'] . $dir['name'], $dir['directory'] . $dir['name']);
			}
		}

		return $options;
	}


	/* seek icon for mimetype else return an unknown icon */
	function mime_icon($mime_type, $size=16)
	{
		if(!$mime_type) $mime_type='unknown';

		$mime_type=	str_replace	('/','_',$mime_type);

		$img=$GLOBALS['phpgw']->common->image('filemanager','mime'.$size.'_'.strtolower($mime_type));
		if(!$img) $img=$GLOBALS['phpgw']->common->image('filemanager','mime'.$size.'_unknown');

		$icon='<img src="'.$img.' "alt="'.lang($mime_type).'" />';
		return $icon;
	}

	function buttonImage($link,$img='',$help='')
	{

		$image=$GLOBALS['phpgw']->common->image('filemanager','button_'.strtolower($img));

		if($img)
		{
			return '<td class="fmButton" align="center" valign="middle" height="28" width="28">
			<a href="'.$link.'" title="'.$help.'"><img src="'.$image.'" alt="'.$help.'"/></a>	
			</td>';
		}
	}

	function inputImage($name,$img='',$help='')
	{
		$image=$GLOBALS['phpgw']->common->image('filemanager','button_'.strtolower($img));

		if($img)
		{
			return '<td class="fmButton" align="center" valign="middle" height="28" width="28">
			<input title="'.$help.'" name="'.$name.'" type="image" alt="'.$name.'" src="'.$image.'" value="clicked" />
			</td>';
		}
		
	
	}

	function html_form_input ($type = NULL, $name = NULL, $value = NULL, $maxlength = NULL, $size = NULL, $checked = NULL, $string = '', $return = 1)
	{
		$text = ' ';
		if ($type != NULL && $type)
		{
			if ($type == 'checkbox')
			{
				$value = $this->bo->string_encode ($value, 1);
			}
			$text .= 'type="'.$type.'" ';
		}
		if ($name != NULL && $name)
		{
			$text .= 'name="'.$name.'" ';
		}
		if ($value != NULL && $value)
		{
			$text .= 'value="'.$value.'" ';
		}
		if (is_int ($maxlength) && $maxlength >= 0)
		{
			$text .= 'maxlength="'.$maxlength.'" ';
		}
		if (is_int ($size) && $size >= 0)
		{
			$text .= 'size="'.$size.'" ';
		}
		if ($checked != NULL && $checked)
		{
			$text .= 'checked ';
		}

		return '<input'.$text.$string.'>';
	}

	function html_form_option ($value = NULL, $displayed = NULL, $selected = NULL, $return = 0)
	{
		$text = ' ';
		if ($value != NULL && $value)
		{
			$text .= ' value="'.$value.'" ';
		}
		if ($selected != NULL && $selected)
		{
			$text .= ' selected';
		}
		return  '<option'.$text.'>'.$displayed.'</option>';
	}


		function encode_href($href = NULL, $args = NULL , $extra_args)
		{
			$href = $this->bo->string_encode ($href, 1);
			$all_args = $args.'&'.$this->bo->string_encode ($extra_args, 1);

			$address = $GLOBALS['phpgw']->link ($href, $all_args);

			return $address;

		}

		function html_link ($href = NULL, $args = NULL , $extra_args, $text = NULL, $return = 1, $encode = 1, $linkonly = 0, $target = NULL)
		{
			//	unset($encode);
			if ($encode)
			{
				$href = $this->bo->string_encode ($href, 1);
				$all_args = $args.'&'.$this->bo->string_encode ($extra_args, 1);
			}
			else
			{
				//				$href = $this->bo->string_encode ($href, 1);
				$all_args = $args.'&'.$extra_args;

			}
			###
			# This decodes / back to normal 
			###
			//			$all_args = preg_replace ("/%2F/", "/", $all_args);
			//			$href = preg_replace ("/%2F/", "/", $href);


			/* Auto-detect and don't disturb absolute links */
			if (!preg_match ("|^http(.{0,1})://|", $href))
			{
				//Only add an extra / if there isn't already one there

				// die(SEP);
				if (!($href[0] == SEP))
				{
					$href = SEP . $href;
				}

				/* $phpgw->link requires that the extra vars be passed separately */
				//				$link_parts = explode ("?", $href);
				$address = $GLOBALS['phpgw']->link ($href, $all_args);
				//				$address = $GLOBALS['phpgw']->link ($href);
			}
			else
			{
				$address = $href;
			}

			/* If $linkonly is set, don't add any HTML */
			if ($linkonly)
			{
				$rstring = $address;
			}
			else
			{
				if ($target)
				{
					$target = 'target='.$target;
				}

				$text = trim ($text);
				$rstring = '<a href="'.$address.'" '.$target.'>'.$text.'</a>';
			}

			return ($this->bo->eor ($rstring, $return));
		}

		function html_table_begin ($width = NULL, $border = NULL, $cellspacing = NULL, $cellpadding = NULL, $rules = NULL, $string = '', $return = 0)
		{
			if ($width != NULL && $width)
			$width = "width=$width";
			if (is_int ($border) && $border >= 0)
			$border = "border=$border";
			if (is_int ($cellspacing) && $cellspacing >= 0)
			$cellspacing = "cellspacing=$cellspacing";
			if (is_int ($cellpadding) && $cellpadding >= 0)
			$cellpadding = "cellpadding=$cellpadding";
			if ($rules != NULL && $rules)
			$rules = "rules=$rules";

			$rstring = "<table $width $border $cellspacing $cellpadding $rules $string>";
			return ($this->bo->eor ($rstring, $return));
		}


		function html_table_end ($return = 0)
		{
			$rstring = "</table>";
			return ($this->bo->eor ($rstring, $return));
		}

		function html_table_row_begin ($align = NULL, $halign = NULL, $valign = NULL, $bgcolor = NULL, $string = '', $return = 0)
		{
			if ($align != NULL && $align)
			$align = "align=$align";
			if ($halign != NULL && $halign)
			$halign = "halign=$halign";
			if ($valign != NULL && $valign)
			$valign = "valign=$valign";
			if ($bgcolor != NULL && $bgcolor)
			$bgcolor = "bgcolor=$bgcolor";
			$rstring = "<tr $align $halign $valign $bgcolor $string>";
			return ($this->bo->eor ($rstring, $return));
		}

		function html_table_row_end ($return = 0)
		{
			$rstring = "</tr>";
			return ($this->bo->eor ($rstring, $return));
		}

		function html_table_col_begin ($align = NULL, $halign = NULL, $valign = NULL, $rowspan = NULL, $colspan = NULL, $string = '', $return = 0)
		{
			if ($align != NULL && $align)
			$align = "align=$align";
			if ($halign != NULL && $halign)
			$halign = "halign=$halign";
			if ($valign != NULL && $valign)
			$valign = "valign=$valign";
			if (is_int ($rowspan) && $rowspan >= 0)
			$rowspan = "rowspan=$rowspan";
			if (is_int ($colspan) && $colspan >= 0)
			$colspan = "colspan=$colspan";

			$rstring = "<td $align $halign $valign $rowspan $colspan $string>";
			return ($this->bo->eor ($rstring, $return));
		}

		function html_table_col_end ($return = 0)
		{
			$rstring = "</td>";
			return ($this->bo->eor ($rstring, $return));
		}
	
	}
