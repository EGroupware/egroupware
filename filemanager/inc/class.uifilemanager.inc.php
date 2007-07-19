<?php
	/**************************************************************************\
	* eGroupWare - Filemanager                                                 *
	* http://www.egroupware.org                                                *
	* ------------------------------------------------------------------------ *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */
	require_once(EGW_INCLUDE_ROOT.'/filemanager/inc/class.bofilemanager.inc.php');

	class uifilemanager extends bofilemanager
	{
		var $public_functions = array(
			'index' => True,
			'help'  => True,
			'view'  => True,
			'history' => True,
			'edit'  => True,
			'download'=>True,
			'search_tpl'=>True,
		);

		//keep
		var $bo;
		var $t; 
		//template object
		/**
		* instantiation of the etemplate as classenvariable
		*
		* @var etemplate
		*/
        var $tmpl;
		var $search_options;
		var $disppath;
		var $cwd;
		var $lesspath;
		var $readable_groups;
		var $files_array;
		var $dirs_options;
		var $numoffiles;
		var $dispsep;

		var $target;

		var $prefs;//array

		var $groups_applications;

		//originally post_vars
		//		var $goto;
		var $goto_x;
		var $download_x;
		var $todir;
		var $changedir; // for switching dir.
		var $cdtodir; // for switching dir.
//		var $createdir;
		var $newfile_or_dir;
		var $newdir_x;
		var $newfile_x;
		var $createfile_var;
		var $delete_x;
		var $renamefiles;
		var $rename_x;
		var $move_to_x;
		//		var $copy_to;
		var $copy_to_x;
		var $edit_x;
		var $edit_comments_x;
		var $edit_file;
		var $edit_preview_x;
		var $edit_save_x;
		var $edit_save_done_x;
		var $edit_cancel_x;
		var $comment_files;
		var $upload_x;
		var $uploadprocess;

		// this ones must be checked thorougly;
		var $fileman = Array();
		//var $fileman;
		var $path;
		var $file; // FIXME WHERE IS THIS FILLED?
		var $sortby;
		var $messages = array();
		var $show_upload_boxes;

		//var $debug = false;
		var $debug = false;
		var $now;

		function uifilemanager()
		{
			// discarded becouse of class extension
			//$this->bo =& CreateObject('filemanager.bofilemanager');
			$this->bofilemanager();

			//KL begin 200703 searchtemplate
			// etemplate stuff
			$this->tmpl =& CreateObject('etemplate.etemplate');
			$this->html =& $GLOBALS['egw']->html;
			// this may be needed using etemplates
			if(!@is_object($GLOBALS['egw']->js))
			{
			   $GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
			}
			//KL end 200703 searchtemplate
			
			//			error_reporting(8);
			$GLOBALS['egw']->browser =& CreateObject('phpgwapi.browser');

			$this->dateformat=$GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];

			//$this->now = date($this->dateformat);
			$this->now = date('Y-m-d');
			
			$this->t = $GLOBALS['egw']->template;

			// here local vars are created from the HTTP vars
			@reset($_POST);
			while(list($name,) = @each($_POST))
			{
				$this->$name = $_POST[$name];
			}

			@reset($_GET);
			while(list($name,) = @each($_GET))
			{
				$$name = $_GET[$name];
			}

			$to_decode = array
			(
				/*
				Decode
				'var'	when	  'avar' == 'value'
				or
				'var'	when	  'var'  is set
				*/
				'op'	=> array('op' => ''),
				'path'	=> array('path' => ''),
				'file'	=> array('file' => ''),
				'sortby'	=> array('sortby' => ''),
				//				'fileman'	=> array('fileman' => ''),
				'messages'	=> array('messages'	=> ''),
				//				'help_name'	=> array('help_name' => ''),
				//				'renamefiles'	=> array('renamefiles' => ''),
				'comment_files'	=> array('comment_files' => ''),
				'show_upload_boxes'	=> array('show_upload_boxes' => '')
			);

			reset($to_decode);
			while(list($var, $conditions) = each($to_decode))
			{
				while(list($condvar, $condvalue) = each($conditions))
				{
					if(isset($$condvar) && ($condvar == $var || $$condvar == $condvalue))
					{
						if(is_array($$var))
						{
							$temp = array();
							while(list($varkey, $varvalue) = each($$var))
							{
								if(is_int($varkey))
								{
									$temp[$varkey] = stripslashes(base64_decode(urldecode(($varvalue))));
								}
								else
								{
									$temp[stripslashes(base64_decode(urldecode(($varkey))))] = $varvalue;
								}
							}
							$this->$var = $temp;
						}
						elseif(isset($$var))
						{
							$this->$var = stripslashes(base64_decode(urldecode($$var)));
						}
					}
				}
			}

			// get appl. and user prefs
			$pref =& CreateObject('phpgwapi.preferences', $this->userinfo['username']);
			$pref->read_repository();
			//			$GLOBALS['egw']->hooks->single('add_def_pref', $GLOBALS['appname']);
			$pref->save_repository(True);
			$pref_array = $pref->read_repository();
			$this->prefs = $pref_array[$this->appname]; //FIXME check appname var in _debug_array

			//always show name

			$this->prefs['name'] = 1;

			if($this->prefs['viewinnewwin'])
			{
				$this->target = '_blank';
			}

			/*
				Check for essential directories
				admin must be able to disable these tests
			*/

			// check if basedir exist
			$test=$this->vfs->get_real_info(array('string' => $this->basedir, 'relatives' => array(RELATIVE_NONE), 'relative' => False));
			if($test['mime_type'] != 'Directory')
			{
				die('Base directory does not exist, Ask adminstrator to check the global configuration.');
			}

			$test=$this->vfs->get_real_info(array('string' => $this->basedir.$this->fakebase, 'relatives' => array(RELATIVE_NONE), 'relative' => False));
			if($test['mime_type'] != 'Directory')
			{
				$this->vfs->override_acl = 1;

				$this->vfs->mkdir(array(
					'string' => $this->fakebase,
					'relatives' => array(RELATIVE_NONE)
				));

				$this->vfs->override_acl = 0;

				//test one more time
				$test=$this->vfs->get_real_info(array('string' => $this->basedir.$this->fakebase, 'relatives' => array(RELATIVE_NONE), 'relative' => False));

				if($test['mime_type']!='Directory')
				{
					die('Fake Base directory does not exist and could not be created, please ask the administrator to check the global configuration.');
				}
				else
				{
					$this->messages[]= $GLOBALS['egw']->common->error_list(array(
						lang('Fake Base Dir did not exist, eGroupWare created a new one.')
					));
				}
			}

//			die($this->homedir);
			$test=$this->vfs->get_real_info(array('string' => $this->basedir.$this->homedir, 'relatives' => array(RELATIVE_NONE), 'relative' => False));
			if($test['mime_type'] != 'Directory')
			{
				$this->vfs->override_acl = 1;

				$this->vfs->mkdir(array(
					'string' => $this->homedir,
					'relatives' => array(RELATIVE_NONE)
				));

				$this->vfs->override_acl = 0;

				//test one more time
				$test=$this->vfs->get_real_info(array('string' => $this->basedir.$this->homedir, 'relatives' => array(RELATIVE_NONE), 'relative' => False));

				if($test['mime_type'] != 'Directory')
				{
					die('Your Home Dir does not exist and could not be created, please ask the adminstrator to check the global configuration.');
				}
				else
				{
					$this->messages[]= $GLOBALS['egw']->common->error_list(array(
						lang('Your Home Dir did not exist, eGroupWare created a new one.')
					));
					// FIXME we just created a fresh home dir so we know there nothing in it so we have to remove all existing content
				}
			}
			$GLOBALS['uifilemanager'] =& $this;	// make ourself available for ExecMethod of get_rows function

		}
		
		function index()
		{
			//echo "<p>call index</p>";
			if ($_GET['action']=='search')
			{
				$this->search_tpl();
			}
			else
			{
				$this->index_2();
			}
		}
		
		function index_2()
		{
			//echo "<p>call index_2</p>";
			//_debug_array($this->tmpl);
			$sessiondata = $this->read_sessiondata();
			if($noheader || $nofooter || ($this->download_x && (count($this->fileman) > 0)))
			{
				$noheader = True;
				$nofooter = True;
				$noappheader= True;
				$nonavbar= True;
			}
			else
			{
				$GLOBALS['egw_info']['flags'] = array
				(
					'currentapp'	=> 'filemanager',
					'noheader'	=> $noheader,
					'nonavbar' => $nonavbar,
					'nofooter'	=> $nofooter,
					'noappheader'	=> $noappheader,
					'enable_browser_class'	=> True
				);
				$GLOBALS['egw']->common->egw_header();
			}

			# Page to process users
			# Code is fairly hackish at the beginning, but it gets better
			# Highly suggest turning wrapping off due to long SQL queries

			###
			# Some hacks to set and display directory paths correctly
			###
/*
			if($this->goto || $this->goto_x)
			{
				$this->path = $this->cdtodir;
			}
*/
			// new method for switching to a new dir.
			if($this->changedir=='true' && $this->cdtodir || $this->goto_x)
			{
				$this->path = $this->cdtodir;
			}

			if(!$this->path)
			{
				$this->path = $this->vfs->pwd();

				if(!$this->path || $this->vfs->pwd(array('full' => False)) == '')
				{
					$this->path = $this->homedir;
				}
			}

			$this->vfs->cd(array('string' => False, 'relatives' => array(RELATIVE_NONE), 'relative' => False));
			$this->vfs->cd(array('string' => $this->path, 'relatives' => array(RELATIVE_NONE), 'relative' => False));

			$pwd = $this->vfs->pwd();

			if(!$this->cwd = substr($this->path, strlen($this->homedir) + 1))
			{
				$this->cwd = '/';
			}
			else
			{
				$this->cwd = substr($pwd, strrpos($pwd, '/') + 1);
			}

			$this->disppath = $this->path;
			$sessiondata['workingdir']="$this->disppath";
			/* This just prevents // in some cases */
			if($this->path == '/')
			{
				$this->dispsep = '';
			}
			else
			{
				$this->dispsep = '/';
			}

			if(!($this->lesspath = substr($this->path, 0, strrpos($this->path, '/'))))
			{
				$this->lesspath = '/';
			}
			//echo "<p>#$this->path#</p>";
			# Get their readable groups to be used throughout the script
			$groups = array();

			$groups = $GLOBALS['egw']->accounts->get_list('groups');
			$this->readable_groups = Array();

			while(list($num, $account) = each($groups))
			{
				if($this->vfs->acl_check(array('owner_id' => $account['account_id'],'operation' => EGW_ACL_READ)))
				{
					$this->readable_groups[$account['account_lid']] = Array('account_id' => $account['account_id'], 'account_name' => $account['account_lid']);
				}
			}
			$sessiondata['readable_groups']=$this->readable_groups;
			$this->groups_applications = array();

			while(list($num, $group_array) = each($this->readable_groups))
			{
				$group_id = $GLOBALS['egw']->accounts->name2id($group_array['account_name']);

				$applications =& CreateObject('phpgwapi.applications', $group_id);
				$this->groups_applications[$group_array['account_name']] = $applications->read_account_specific();
			}
			$sessiondata['groups_applications']=$this->groups_applications;

			# We determine if they're in their home directory or a group's directory,
			# and set the VFS working_id appropriately
			if((preg_match('+^'.$this->fakebase.'\/(.*)(\/|$)+U', $this->path, $matches)) && $matches[1] != $this->userinfo['account_lid']) //FIXME matches not defined
			{
				$this->vfs->working_id = $GLOBALS['egw']->accounts->name2id($matches[1]);//FIXME matches not defined
			}
			else
			{
				$this->vfs->working_id = $this->userinfo['username'];
			}
			$this->save_sessiondata($sessiondata);

			# FIXME # comment what happens here
			if($this->path != $this->homedir && $this->path != $this->fakebase && $this->path != '/' && !$this->vfs->acl_check(array('string' => $this->path, 'relatives' => array(RELATIVE_NONE),'operation' => EGW_ACL_READ)))
			{
				echo "<p>died for some reasons</p>";
				$this->messages[]= $GLOBALS['egw']->common->error_list(array(lang('You do not have access to %1', $this->path)));
				$this->html_link('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->homedir, lang('Go to your home directory'));

				$GLOBALS['egw']->common->egw_footer();
				$GLOBALS['egw']->common->egw_exit();
			}

			$this->userinfo['working_id'] = $this->vfs->working_id;
			$this->userinfo['working_lid'] = $GLOBALS['egw']->accounts->id2name($this->userinfo['working_id']);

			# If their home directory doesn't exist, we try to create it
			# Same for group directories


			// Moved to constructor
			/*
			if(($this->path == $this->homedir)	&& !$this->vfs->file_exists($pim_tmp_arr))
			{
				$this->vfs->override_acl = 1;

				if(!$this->vfs->mkdir(array(
					'string' => $this->homedir,
					'relatives' => array(RELATIVE_NONE)
				)))
				{
					$p = $this->vfs->path_parts($pim_tmp_arr);

					$this->messages[]= $GLOBALS['egw']->common->error_list(array(
						lang('Could not create directory %1',
						$this->homedir . ' (' . $p->real_full_path . ')'
					)));
				}

				$this->vfs->override_acl = 0;
			}
			*/

			# Verify path is real
			if($this->path != $this->homedir && $this->path != '/' && $this->path != $this->fakebase)
			{
				if(!$this->vfs->file_exists(array(
					'string' => $this->path,
					'relatives' => array(RELATIVE_NONE)
				)))
				{
					echo "<p>died for some other reasons</p>";
					$this->messages[] = $GLOBALS['egw']->common->error_list(array(lang('Directory %1 does not exist', $this->path)));
					$this->html_link('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->homedir, lang('Go to your home directory'));
					$GLOBALS['egw']->common->egw_footer();
					$GLOBALS['egw']->common->egw_exit();
				}
			}

			/* Update if they request it, or one out of 20 page loads */
			srand((double) microtime() * 1000000);
			if($_GET['update'] || rand(0, 19) == 4)
			{
				$this->vfs->update_real(array(
					'string' => $this->path,
					'relatives' => array(RELATIVE_NONE)
				));
			}

			# Check available permissions for $this->path, so we can disable unusable operations in user interface
			if($this->vfs->acl_check(array(
				'string' => $this->path,
				'relatives' => array(RELATIVE_NONE),
				'operation' => EGW_ACL_ADD
			)))
			{
				$this->can_add = True;
			}

			# Default is to sort by name
			if(!$this->sortby)
			{
				$this->sortby = 'name';
			}

			if($this->debug)
			{
				$this->debug_filemanager();
			}

			# main action switch
			// FIXME this will become a switch
			if($this->newfile_x && $this->newfile_or_dir) // create new textfile
			{
				$this->createfile();
			}
			elseif($this->newfile_or_dir && $this->newdir_x)
			{
				$this->createdir();
			}
			elseif($this->uploadprocess)
			{
				$this->fileUpload();
			}
			elseif($this->upload_x || $this->show_upload_boxes)
			{
				$this->showUploadboxes();
			}
			elseif($this->copy_to_x)
			{
				$this->copyTo();
			}
			elseif($this->move_to_x)
			{
				$this->moveTo();
			}
			elseif($this->download_x)
			{
				$this->download();
			}
			elseif($this->renamefiles)
			{
				$this->rename();
			}
			elseif($this->comment_files)
			{
				$this->editComment();
			}
			elseif($this->edit_cancel_x)
			{
				$this->readFilesInfo();
				$this->fileListing();
			}
			elseif($this->edit_x || $this->edit_preview_x || $this->edit_save_x || $this->edit_save_done_x)
			{
				$this->edit();
			}
			elseif($this->delete_x)
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

			$vars['form_action']=$this->encode_href('/index.php', 'menuaction=filemanager.uifilemanager.index','path='.$this->path);
			if($this->numoffiles || $this->cwd)
			{
				while(list($num, $name) = each($this->prefs))
				{
					if($name)
					{
						$columns++;
					}
				}
				$columns++;

				$vars['toolbar0'] = $this->toolbar('location');
				$vars['toolbar1'] = $this->toolbar('list_nav');

				if(count($this->messages)>0)
				{
					foreach($this->messages as $msg)
					{
						$messages.='<p>'.$msg.'</p>';
					}
				}

				$vars['messages'] = $messages;

				$this->t->set_var($vars);
				$this->t->pparse('out','filemanager_header');

				###
				# Start File Table Column Headers
				# Reads values from $file_attributes array and preferences
				###
				$this->t->set_var('actions',lang('select'));

				reset($this->file_attributes);

				if($this->numoffiles>0)
				{
					while(list($internal, $displayed) = each($this->file_attributes))
					{
						if($this->prefs[$internal])
						{
							$col_data='<span><a href="'.$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path.'&sortby='.$internal).'">'.$displayed.'</a></span>';
							$this->t->set_var('col_data',$col_data);
							$this->t->parse('columns','column',True);
						}
					}

					$this->t->set_var('row_tr_color','#dedede');

					//kan dit weg?
					$this->t->parse('rows','row');

					$this->t->pparse('out','row');
				}
				else
				{
					$lang_nofiles=lang('No files in this directory.');
				}
				$vars['lang_no_files'] = $lang_nofiles;

				if($this->prefs['dotdot'] && $this->prefs['name'] && $this->path != '/')
				{
					$this->t->set_var('actions','');

					$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->lesspath);

					$col_data='<a href="'.$link.'"><img src="'.$GLOBALS['egw']->common->image('filemanager','mime16up').' "alt="'.lang('Folder Up').'" /></a>';
					$col_data.='&nbsp;<a href="'.$link.'">..</a>';

					$this->t->set_var('col_data',$col_data);
					$this->t->parse('columns','column');

					if($this->prefs['mime_type'])
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
				@reset($this->files_array);
				for($i = 0; $i != $this->numoffiles; $i++)
				{
					$files = $this->files_array[$i];

					if($this->rename_x || $this->edit_comments_x)
					{
						unset($this_selected);
						unset($renamethis);
						unset($edit_this_comment);

						for($j = 0; $j != $this->numoffiles; $j++)
						{
							if($this->fileman[$j] == $files['name'])
							{
								$this_selected = 1;
								break;
							}
						}

						if($this->rename_x && $this_selected)
						{
							$renamethis = 1;
						}
						elseif($this->edit_comments_x && $this_selected)
						{
							$edit_this_comment = 1;
						}
					}

					if(!$this->prefs['dotfiles'] && ereg("^\.", $files['name']))
					{
						continue;
					}

					# Checkboxes
					//if(!$this->rename_x && !$this->edit_comments_x && $this->path != $this->fakebase && $this->path != '/')
					if(!$this->rename_x && !$this->edit_comments_x  && $this->path != '/')
					{
						$cbox='<input type="checkbox" name="fileman['.$i.']" value="'.$files['name'].'">';
						$this->t->set_var('actions',$cbox);
					}
					elseif($renamethis)
					{
						$cbox=$this->html_form_input('hidden', 'fileman[' . base64_encode($files['name']) . ']', $files['name'], NULL, NULL, 'checked');
						$this->t->set_var('actions',$cbox);
					}
					else
					{
						$this->t->set_var('actions','');
					}

					# File name and icon
					if($renamethis)
					{
						$col_data=$this->mime_icon($files['mime_type']);
						$col_data.='<input type="text" maxlength="255" name="renamefiles[' . $files['name'] . ']" value="'.$files['name'].'">';
					}
					else
					{
						if($files['mime_type'] == 'Directory')
						{
							$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path.$this->dispsep.$files['name']);

							$icon=$this->mime_icon($files['mime_type']);

							$col_data='<a href="'.$link.'">'.$icon.'</a>&nbsp;';
							$col_data.='<a href="'.$link.'">'.$files['name'].'</a>&nbsp;';
						}
						else
						{

							if($this->prefs['viewonserver'] && isset($this->filesdir) && !$files['link_directory'])
							{
								#FIXME
								$clickview = $this->filesdir.$pwd.'/'.$files['name'];

								if($phpwh_debug)
								{
									echo 'Setting clickview = '.$clickview.'<br>'."\n";
									$this->html_link($clickview,'', '',$files['name'], 0, 1, 0, '');
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
					if($this->prefs['mime_type'])
					{
						$col_data=$files['mime_type'];
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# File size
					if($this->prefs['size'])
					{
						// KL to fetch the size of the object here is just WRONG, since the array may be already sorted by size
						//$tmp_arr=array(
						//	'string'	=> $files['directory'] . '/' . $files['name'],
						//	'relatives'	=> array(RELATIVE_NONE)
						//;
						//if($files['mime_type'] != 'Directory') $tmp_arr['checksubdirs'] = false;
						//$size = $this->vfs->get_size($tmp_arr);
						$size = $files['size'];

						$col_data=$this->borkb($size);

						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Date created
					if($this->prefs['created'])
					{
						$col_data=date($this->dateformat,strtotime($files['created']));
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Date modified
					if($this->prefs['modified'])
					{
						if($files['modified'] != '0000-00-00')
						{
							$col_data=date($this->dateformat,strtotime($files['modified']));
						}
						else
						{
							$col_data='';
						}

						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Owner name
					if($this->prefs['owner'])
					{
						// KL to fetch the name of the object here is just WRONG, since the array may be already sorted by id
						//$this->t->set_var('col_data',$GLOBALS['egw']->accounts->id2name($files['owner_id']));
						$this->t->set_var('col_data',$files['owner_name']);
						$this->t->parse('columns','column',True);
					}

					# Creator name
					if($this->prefs['createdby_id'])
					{
						$this->html_table_col_begin();
						if($files['createdby_id'])
						{
							// KL to fetch the name of the object here is just WRONG, since the array may be already sorted by id
							//$col_data=$GLOBALS['egw']->accounts->id2name($files['createdby_id']);
							$col_data=$files['createdby_name'];
						}
						else $col_data='';

						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Modified by name
					if($this->prefs['modifiedby_id'])
					{
						if($files['modifiedby_id'])
						{
							// KL to fetch the name of the object here is just WRONG, since the array may be already sorted by id
							//$col_data=$GLOBALS['egw']->accounts->id2name($files['modifiedby_id']);
							$col_data=$files['modifiedby_name'];
						}
						else $col_data='';
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Application
					if($this->prefs['app'])
					{
						$col_data=$files['app'];
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Comment
					if($this->prefs['comment'])
					{
						if($edit_this_comment)
						{
							$col_data='<input type="text" name="comment_files[' . $files['name'] . ']" value="'.$files['comment'].'" maxlength="255">';
						}
						else
						{
							$col_data=$files['comment'];
						}
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					# Version
					if($this->prefs['version'])
					{
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.history','file='.$files['name'].'&path='.$this->path);
						$col_data='<a href="'.$link.'" target="_blank">'.$files['version'].'</a>';
						$this->t->set_var('col_data',$col_data);
						$this->t->parse('columns','column',True);
					}

					if($files['mime_type'] == 'Directory')
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
				if($this->rename_x || $this->edit_comments_x)
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
			$vars['lang_files_in_this_dir'] = lang('Files in this directory');
			$vars['files_in_this_dir'] = $this->numoffiles;

			$vars['lang_used_space'] = lang('Used space');
			$vars['used_space'] = $this->borkb($usedspace, NULL, 1);

			if($this->path == $this->homedir || $this->path == $this->fakebase)
			{
				$vars['lang_unused_space'] = lang('Unused space');
				$vars['unused_space'] = $this->borkb($this->userinfo['hdspace'] - $usedspace, NULL, 1);

				$tmp_arr=array(
					'string'	=> $this->path,
					'relatives'	=> array(RELATIVE_NONE)
				);

				$ls_array = $this->vfs->ls($tmp_arr);

				$vars['lang_total_files'] = lang('Total Files');
				$vars['total_files'] = count($ls_array);
			}

			$this->t->set_var($vars);
			$this->t->pparse('out','filemanager_footer');

			$GLOBALS['egw']->common->egw_footer();
			$GLOBALS['egw']->common->egw_exit();
		}

		function readFilesInfo()
		{
			// start files info

			# Read in file info from database to use in the rest of the script
			# $fakebase is a special directory.  In that directory, we list the user's
			# home directory and the directories for the groups they're in
			$this->numoffiles = 0;
			if($this->path == $this->fakebase)
			{
				// FIXME this test can be removed
				if(!$this->vfs->file_exists(array('string' => $this->homedir, 'relatives' => array(RELATIVE_NONE))))
				{
					$this->vfs->mkdir(array('string' => $this->homedir, 'relatives' => array(RELATIVE_NONE)));
				}
				reset($this->readable_groups);
				// create the directorys of the readableGroups if they do not exist
				while(list($num, $group_array) = each($this->readable_groups))
				{
					# If the group doesn't have access to this app, we don't show it, and do not appkly any action here
					if(!$this->groups_applications[$group_array['account_name']][$this->appname]['enabled'])
					{
						continue;
					}

					if(!$this->vfs->file_exists(array('string' => $this->fakebase.'/'.$group_array['account_name'],'relatives'	=> array(RELATIVE_NONE))))
					{
						$this->vfs->override_acl = 1;
						$this->vfs->mkdir(array(
							'string' => $this->fakebase.'/'.$group_array['account_name'],
							'relatives' => array(RELATIVE_NONE)
						));
						// FIXME we just created a fresh group dir so we know there nothing in it so we have to remove all existing content
						$this->vfs->override_acl = 0;
						$this->vfs->set_attributes(array('string' => $this->fakebase.'/'.$group_array['account_name'],'relatives'	=> array(RELATIVE_NONE),'attributes' => array('owner_id' => $group_array['account_id'],'createdby_id' => $group_array['account_id'])));
					}
				}
			}

            // read the list of the existing directorys/files
            $ls_array = $this->vfs->ls(array(
                'string' => $this->path,
                'relatives' => array(RELATIVE_NONE),
                'checksubdirs' => false,
                'nofiles'   => false,
                'orderby'   => $this->sortby
            ));
            $heimatverz=explode('/',$this->homedir);
            // process the list: check if we are allowed to read it, get the real size, and count the files/dirs
            while(list($num, $file_array) = each($ls_array))
            {
                if($this->path == $this->fakebase)
                {
					if ($file_array['name'] && (array_key_exists($file_array['name'],$this->readable_groups) || $this->fakebase.'/'.$file_array['name']  == $this->homedir || $file_array['name'] == $heimatverz[2]))
					{
						if(!$this->groups_applications[$file_array['name']][$this->appname]['enabled'] && $this->fakebase.'/'.$file_array['name']  != $this->homedir && $file_array['name'] != $heimatverz[2])
						{
							continue;
						}
					}
                    if ($file_array['name'] && !array_key_exists($file_array['name'],$this->readable_groups) && !($this->fakebase.'/'.$file_array['name']  == $this->homedir || $file_array['name'] == $heimatverz[2]))
                    {
                        continue;
                    }
				}
				// get additional info, which was not retrieved meeting our needs -> size, ids
                if($this->prefs['size'])
                {
                    //KL get the real size of the object
                    $tmp_arr=array(
                        'string'    => $file_array['directory'] . '/' . $file_array['name'],
                        'relatives' => array(RELATIVE_NONE)
                    );
                    if($file_array['mime_type'] != 'Directory') $tmp_arr['checksubdirs'] = false;
                    $file_array['size']=$this->vfs->get_size($tmp_arr);
                    // KL got the real size
                }
                if($this->prefs['owner'])
                {
                    $file_array['owner_name']=$GLOBALS['egw']->accounts->id2name($file_array['owner_id']);
                }

                # Creator name
                if($this->prefs['createdby_id'])
                {
                    if($file_array['createdby_id'])
                    {
                        //$col_data=$GLOBALS['egw']->accounts->id2name($files['createdby_lid']);
                        $file_array['createdby_name']=$GLOBALS['egw']->accounts->id2name($file_array['createdby_id']);
                    }
                    else
                    {
                        $file_array['createdby_name']='';
                    }
                }

                # Modified by name
                if($this->prefs['modifiedby_id'])
                {
                    if($file_array['modifiedby_id'])
                    {
                        $file_array['modifiedby_name']=$GLOBALS['egw']->accounts->id2name($file_array['modifiedby_id']);
                    }
                    else
                    {
                        $file_array['modifiedby_name']='';
                    }
                }
				// got additional info
                $this->numoffiles++;
                $this->files_array[] = $file_array;
                if($phpwh_debug)
                {
                    echo 'Filename: '.$file_array['name'].'<br>'."\n";
                }
            }


			if( !is_array($this->files_array) )
			{
				$this->files_array = array();
			}
			else
			{
				// KL sorting by multisort, if sort-param is set.
				if ($this->sortby)
				{
					$mysorting=$this->sortby;
					if ($mysorting=='owner')
					{
						$mysorting='owner_name';
					}
					elseif ($mysorting=='createdby_id')
					{
						$mysorting='createdby_name';
					}
					elseif($mysorting=='modifiedby_id')
					{
						$mysorting='modifiedby_name';
					}
					foreach ($this->files_array as $key => $row) {
					   $file[$key]  = $row[$mysorting];
					}

					// cast and sort file as container of the sort-key-column ascending to sort
					//  $files_array (as last Param), by the common key
					array_multisort(array_map('strtolower',$file), SORT_ASC,  $this->files_array);
				}
				// KL sorting done
			}
		}

		function toolbar($type)
		{
			//echo "<p> call toolbar </p>";
			switch($type)
			{
				case 'location':
					$toolbar='
					<div id="fmLocation">
					<table cellspacing="1" cellpadding="0" border="0">
					<tr>
					';
					$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('phpgwapi','buttonseparator').'" height="27" width="8"></td>';
					$toolbar.='
					<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

					// go up icon when we're not at the top, dont allow to go outside /home = fakebase
					if($this->path != '/' && $this->path != $this->fakebase)
					{
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->lesspath);
						$toolbar.=$this->buttonImage($link,'up',lang('go up'));
					}

					// go home icon when we're not home already
					if($this->path != $this->homedir)
					{
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->homedir);
						$toolbar.=$this->buttonImage($link,'home',lang('go home'));
					}

					// reload button with this url
					$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index&update=1','path='.$this->path);
					$toolbar.=$this->buttonImage($link,'reload',lang('reload'));

					$toolbar.='<td>'.lang('Location').':&nbsp;';
					//$toolbar.='<input id="fmInputLocation" type="text" size="20" disabled="disabled" name="location" value="'.$this->disppath.'"/>&nbsp;';
					$current_option='<option>'.$this->disppath.'</option>';
					// selectbox for change/move/and copy to
					
					$this->dirs_options=$this->all_other_directories_options();
					$toolbar.='<select name="cdtodir" onChange="document.formfm.changedir.value=\'true\';document.formfm.submit()">'.$current_option.$this->dirs_options.'</select>
					<input type="hidden" name="changedir" value="false"></td>
					';
					$toolbar.=$this->inputImage('goto','goto',lang('Quick jump to'));
					// upload button
					if($this->path != '/' && $this->path != $this->fakebase && $this->can_add)
					{

						$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';
						$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('phpgwapi','buttonseparator').'" height="27" width="8"></td>';
						$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

						//						$toolbar.=$this->inputImage('download','download',lang('Download'));
						// upload button
						$toolbar.=$this->inputImage('upload','upload',lang('Upload'));
					}
					$toolbar.='</tr></table>';
					$toolbar.='</div>';
					break;
				case 'list_nav':
					$toolbar='
					<table cellspacing="1" cellpadding="0" border="0">
					<tr>';
					// selectbox for change/move/and copy to
					// submit buttons for
					if($this->path != '/' && $this->path != $this->fakebase)
					{
						$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('phpgwapi','buttonseparator').'" height="27" width="8"></td>';
						$toolbar.='
						<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

						if(!$this->rename_x && !$this->edit_comments_x)
						{
							// edit text file button
							$toolbar.=$this->inputImage('edit','edit',lang('edit'));
						}

						if(!$this->edit_comments_x)
						{
							$toolbar.=$this->inputImage('rename','rename',lang('Rename'));
						}

						if(!$this->rename_x && !$this->edit_comments_x)
						{
							$toolbar.=$this->inputImage('delete','delete',lang('Delete'));
						}

						if(!$this->rename_x)
						{
							$toolbar.=$this->inputImage('edit_comments','edit_comments',lang('Edit comments'));
						}
						$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';
					}
					else
					{
						if ($this->path = $this->fakebase)
						{
							$toolbar.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('phpgwapi','buttonseparator').'" height="27" width="8"></td>';
							$toolbar.='
							<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';
							if(!$this->rename_x)
							{
								$toolbar.=$this->inputImage('edit_comments','edit_comments',lang('Edit comments'));
							}
						}
					}

					//	$toolbar.='</tr></table>';
					if(!$this->rename_x && !$this->edit_comments_x)
					{
						// copy and move buttons
						if($this->path != '/' && $this->path != $this->fakebase)
						{
							$toolbar3.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('phpgwapi','buttonseparator').'" height="27" width="8"></td>';
							$toolbar3.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

							if (!$this->dirs_options) $this->dirs_options=$this->all_other_directories_options();
							$toolbar3.='<td><select name="todir">'.$this->dirs_options.'</select></td>';

							$toolbar3.=$this->inputImage('copy_to','copy_to',lang('Copy to'));
							$toolbar3.=$this->inputImage('move_to','move_to',lang('Move to'));

							$toolbar3.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

						}

						// create dir and file button
						if($this->path != '/' && $this->path != $this->fakebase && $this->can_add)
						{
							$toolbar3.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('phpgwapi','buttonseparator').'" height="27" width="8"></td>';
							$toolbar3.='<td><img alt="spacer" src="'.$GLOBALS['egw']->common->image('filemanager','spacer').'" height="27" width="1"></td>';

							$toolbar3.='<td><input type=text size="15" name="newfile_or_dir" value="" /></td>';
							$toolbar3.=$this->inputImage('newdir','createdir',lang('Create Folder'));
							$toolbar3.=$this->inputImage('newfile','createfile',lang('Create File'));
						}

						if($toolbar3)
						{
							$toolbar.=$toolbar3;
						/*	$toolbar.='
							<table cellspacing="1" cellpadding="0" border="0">
							<tr>'.$toolbar3;*/
						}
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

			if($this->path != '/' && $this->path != $this->fakebase)
			{
				for($i = 0; $i != $this->show_upload_boxes; $i++)
				{
					if($badchar = $this->bad_chars($_FILES['upload_file']['name'][$i], True, True))
					{
						$this->messages[]= $GLOBALS['egw']->common->error_list(array($this->html_encode(lang('File names cannot contain "%1"', $badchar), 1)));

						continue;
					}
					if ($_FILES['upload_file']['tmp_name'][$i]=='')
					{
						$this->messages[]= $GLOBALS['egw']->common->error_list(array($this->html_encode(lang('File %1 may be too big. Contact your Systemadministrator for further info', $_FILES['upload_file']['name']), 1)));
						continue;
					}
					# Check to see if the file exists in the database, and get its info at the same time
					$ls_array = $this->vfs->ls(array(
						'string'=> $this->path . '/' . $_FILES['upload_file']['name'][$i],
						'relatives'	=> array(RELATIVE_NONE),
						'checksubdirs'	=> False,
						'nofiles'	=> True
					));

					$fileinfo = $ls_array[0];

					if($fileinfo['name'])
					{
						if($fileinfo['mime_type'] == 'Directory')
						{
							$this->messages[]= $GLOBALS['egw']->common->error_list(array(lang('Cannot replace %1 because it is a directory', $fileinfo['name'])));
							continue;
						}
					}
					
					if($_FILES['upload_file']['size'][$i] > 0)
					{
						if($fileinfo['name'] && $fileinfo['deleteable'] != 'N')
						{
							$tmp_arr=array(
								'string'=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array(RELATIVE_ALL),
								'attributes'	=> array(
									'owner_id' => $this->userinfo['username'],
									'modifiedby_id' => $this->userinfo['username'],
									'modified' => $this->now,
									'size' => $_FILES['upload_file']['size'][$i],
									'mime_type' => $_FILES['upload_file']['type'][$i],
									'deleteable' => 'Y',
									'comment' => stripslashes($_POST['upload_comment'][$i])
								)
							);
							$this->vfs->set_attributes($tmp_arr);

							$tmp_arr=array(
								'from'	=> $_FILES['upload_file']['tmp_name'][$i],
								'to'	=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array(RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
							);
							$this->vfs->cp($tmp_arr);

							$this->messages[]=lang('Replaced %1', $this->disppath.'/'.$_FILES['upload_file']['name'][$i]);
						}
						else
						{
							$this->vfs->cp(array(
								'from'=> $_FILES['upload_file']['tmp_name'][$i],
								'to'=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array(RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
							));

							$this->vfs->set_attributes(array(
								'string'=> $_FILES['upload_file']['name'][$i],
								'relatives'	=> array(RELATIVE_ALL),
								'attributes'=> array(
									'mime_type' => $_FILES['upload_file']['type'][$i],
									'comment' => stripslashes($_POST['upload_comment'][$i])
								)
							));

							$this->messages[]=lang('Created %1,%2', $this->disppath.'/'.$_FILES['upload_file']['name'][$i], $_FILES['upload_file']['size'][$i]);
						}
					}
					elseif($_FILES['upload_file']['name'][$i])
					{
						$this->vfs->touch(array(
							'string'=> $_FILES['upload_file']['name'][$i],
							'relatives'	=> array(RELATIVE_ALL)
						));

						$this->vfs->set_attributes(array(
							'string'=> $_FILES['upload_file']['name'][$i],
							'relatives'	=> array(RELATIVE_ALL),
							'attributes'=> array(
								'mime_type' => $_FILES['upload_file']['type'][$i],
								'comment' => stripslashes($_POST['upload_comment'][$i])
							)
						));

						$this->messages[]=lang('Created %1,%2', $this->disppath.'/'.$_FILES['upload_file']['name'][$i], $file_size[$i]);
					}
				}

				$this->readFilesInfo();
				$this->filelisting();
			}
		}

		# Handle Editing comments
		function editComment()
		{
			while(list($file) = each($this->comment_files))
			{
				if($badchar = $this->bad_chars($this->comment_files[$file], False, True))
				{
					$this->messages[]=$GLOBALS['egw']->common->error_list(array($file . $this->html_encode(': ' . lang('Comments cannot contain "%1"', $badchar), 1)));
					continue;
				}

				$this->vfs->set_attributes(array(
					'string'	=> $file,
					'relatives'	=> array(RELATIVE_ALL),
					'attributes'	=> array(
						'comment' => stripslashes($this->comment_files[$file])
					)
				));

				$this->messages[]=lang('Updated comment for %1', $this->path.'/'.$file);
			}

			$this->readFilesInfo();
			$this->filelisting();
		}

		# Handle Renaming Files and Directories
		function rename()
		{
			while(list($from, $to) = each($this->renamefiles))
			{
				if($badchar = $this->bad_chars($to, True, True))
				{
					$this->messages[]=$GLOBALS['egw']->common->error_list(array($this->html_encode(lang('File names cannot contain "%1"', $badchar), 1)));
					continue;
				}

				if(ereg("/", $to) || ereg("\\\\", $to))
				{
					$this->messages[]=$GLOBALS['egw']->common->error_list(array(lang("File names cannot contain \\ or /")));
				}
				elseif(!$this->vfs->mv(array(
					'from'	=> $from,
					'to'	=> $to
				)))
				{
					$this->messages[]= $GLOBALS['egw']->common->error_list(array(lang('Could not rename %1 to %2', $this->disppath.'/'.$from, $this->disppath.'/'.$to)));
				}
				else
				{
					$this->messages[]=lang('Renamed %1 to %2', $this->disppath.'/'.$from, $this->disppath.'/'.$to);
				}
			}
			$this->readFilesInfo();
			$this->filelisting();
		}

		# Handle Moving Files and Directories
		function moveTo()
		{
			if(!$this->todir)
			{
				$this->messages[] = $GLOBALS['egw']->common->error_list(array(lang('Could not move file because no destination directory is given ', $this->disppath.'/'.$file)));

			}
			else
			{

				while(list($num, $file) = each($this->fileman))
				{
					if($this->vfs->mv(array(
						'from'	=> $file,
						'to'	=> $this->todir . '/' . $file,
						'relatives'	=> array(RELATIVE_ALL, RELATIVE_NONE)
					)))
					{
						$moved++;
						$this->messages[]=lang('Moved %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file);
					}
					else
					{
						$this->messages[] = $GLOBALS['egw']->common->error_list(array(lang('Could not move %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file)));
					}
				}
			}

			if($moved)
			{
				$x=0;
			}

			$this->readFilesInfo();
			$this->filelisting();
		}

		// Handle Copying of Files and Directories
		function copyTo()
		{
			if(!$this->todir)
			{
				$this->messages[] = $GLOBALS['egw']->common->error_list(array(lang('Could not copy file because no destination directory is given ', $this->disppath.'/'.$file)));

			}
			else
			{
				while(list($num, $file) = each($this->fileman))
				{
				
					if($this->vfs->cp(array(
						'from'	=> $file,
						'to'	=> $this->todir . '/' . $file,
						'relatives'	=> array(RELATIVE_ALL, RELATIVE_NONE)
					)))
					{
						$copied++;
						$this->message .= lang('Copied %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file);
					}
					else
					{
						$this->message .= $GLOBALS['egw']->common->error_list(array(lang('Could not copy %1 to %2', $this->disppath.'/'.$file, $this->todir.'/'.$file)));
					}
				}
			}
			if($copied)
			{
				$x=0;
			}

			$this->readFilesInfo();
			$this->filelisting();
		}

		function createdir()
		{
			if($this->newdir_x && $this->newfile_or_dir)
			{
				if($this->badchar = $this->bad_chars($this->newfile_or_dir, True, True))
				{
					$this->messages[]= $GLOBALS['egw']->common->error_list(array($this->html_encode(lang('Directory names cannot contain "%1"', $badchar), 1)));
				}

				/* TODO is this right or should it be a single $ ? */
				if($this->newfile_or_dir[strlen($this->newfile_or_dir)-1] == ' ' || $this->newfile_or_dir[0] == ' ')
				{
					$this->messages[]= $GLOBALS['egw']->common->error_list(array(lang('Cannot create directory because it begins or ends in a space')));
				}

				$ls_array = $this->vfs->ls(array(
					'string'	=> $this->path . '/' . $this->newfile_or_dir,
					'relatives'	=> array(RELATIVE_NONE),
					'checksubdirs'	=> False,
					'nofiles'	=> True
				));

				$fileinfo = $ls_array[0];

				if($fileinfo['name'])
				{
					if($fileinfo['mime_type'] != 'Directory')
					{
						$this->messages[]= $GLOBALS['egw']->common->error_list(array(
							lang('%1 already exists as a file',
							$fileinfo['name'])
						));
					}
					else
					{
						$this->messages[]= $GLOBALS['egw']->common->error_list(array(lang('Directory %1 already exists', $fileinfo['name'])));
					}
				}
				else
				{
					if($this->vfs->mkdir(array('string' => $this->newfile_or_dir)))
					{
						$this->messages[]=lang('Created directory %1', $this->disppath.'/'.$this->newfile_or_dir);
					}
					else
					{
						$this->messages[]=$GLOBALS['egw']->common->error_list(array(lang('Could not create %1', $this->disppath.'/'.$this->newfile_or_dir)));
					}
				}

				$this->readFilesInfo();
				$this->filelisting();
			}
		}

		function delete()
		{
			if( is_array($this->fileman) && count($this->fileman) >= 1)
			{
				foreach($this->fileman as $filename)
				{
					if($this->vfs->delete(array('string' => $filename)))
					{
						$this->messages[]= lang('Deleted %1', $this->disppath.'/'.$filename).'<br/>';
					}
					else
					{
						$this->messages[]=$GLOBALS['egw']->common->error_list(array(lang('Could not delete %1', $this->disppath.'/'.$filename)));
					}
				}
			}
			else
			{
				// make this a javascript func for quicker respons
				$this->messages[]=$GLOBALS['egw']->common->error_list(array(lang('Please select a file to delete.')));
			}
			$this->readFilesInfo();
			$this->filelisting();
		}

		function debug_filemanager()
		{
			error_reporting(8);

			echo "<b>Filemanager debug:</b><br>
			path: {$this->path}<br>
			disppath: {$this->disppath}<br>
			cwd: {$this->cwd}<br>
			lesspath: {$this->lesspath}
			<p>
			<b>eGroupware debug:</b><br>
			real getabsolutepath: " . $this->vfs->getabsolutepath(array('target' => False, 'mask' => False, 'fake' => False)) . "<br>
			fake getabsolutepath: " . $this->vfs->getabsolutepath(array('target' => False)) . "<br>
			appsession: " . $GLOBALS['egw']->session->appsession('vfs','') . "<br>
			pwd: " . $this->vfs->pwd() . "<br>";

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
			if(!$this->show_upload_boxes || $this->show_upload_boxes <= 0)
			{
				if(!$this->show_upload_boxes = $this->prefs['show_upload_boxes'])
				{
					$this->show_upload_boxes = 1;
				}
			}

			# Show file upload boxes. Note the last argument to html().  Repeats $this->show_upload_boxes times
			if($this->path != '/' && $this->path != $this->fakebase && $this->can_add)
			{
				$vars['form_action']=$GLOBALS['egw']->link('/index.php','menuaction=filemanager.uifilemanager.index');
				$vars['path']=$this->path;
				$vars['lang_file']=lang('File');
				$vars['lang_comment']=lang('Comment');
				$vars['num_upload_boxes']=$this->show_upload_boxes;
				$this->t->set_var($vars);
				$this->t->pparse('out','upload_header');

				for($i=0;$i<$this->show_upload_boxes;$i++)
				{
					$this->t->set_var('row_tr_color',$tr_color);
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');
				}

				$vars['lang_upload']=lang('Upload files');
				$vars['change_upload_boxes'].=lang('Show') . '&nbsp;';
				$links.= $this->html_link('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=5', '5');
				$links.='&nbsp;';
				$links.= $this->html_link('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=10', '10');
				$links.='&nbsp;';
				$links.= $this->html_link('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=20', '20');
				$links.='&nbsp;';
				$links.= $this->html_link('/index.php','menuaction=filemanager.uifilemanager.index','show_upload_boxes=50', '50');
				$links.='&nbsp;';
				$links.= lang('upload fields');
				$vars['change_upload_boxes'].=$links;
				$this->t->set_var($vars);
				$this->t->pparse('out','upload_footer');
			}
		}

		/* create textfile */
		function createfile()
		{
			$this->createfile_var=$this->newfile_or_dir;
			if($this->createfile_var)
			{
				if($badchar = $this->bad_chars($this->createfile_var, True, True))
				{
					$this->messages[] = $GLOBALS['egw']->common->error_list(array(
						lang('File names cannot contain "%1"',$badchar),
						1)
					);

					$this->fileListing();
				}

				if($this->vfs->file_exists(array(
					'string'=> $this->createfile_var,
					'relatives'	=> array(RELATIVE_ALL)
				)))
				{
					$this->messages[]=$GLOBALS['egw']->common->error_list(array(lang('File %1 already exists. Please edit it or delete it first.', $this->createfile_var)));
					$this->fileListing();
				}

				if($this->vfs->touch(array(
					'string'	=> $this->createfile_var,
					'relatives'	=> array(RELATIVE_ALL)
				)))
				{
					$this->fileman = array();
					$this->fileman[0] = $this->createfile_var;
					$this->edit = 1;
					$this->numoffiles++;
					$this->edit();
				}
				else
				{
					$this->messages[]=$GLOBALS['egw']->common->error_list(array(lang('File %1 could not be created.', $this->createfile_var)));
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

			$vars['preview_content'] = '';
			if($this->edit_file)
			{
				$this->edit_file_content = stripslashes($this->edit_file_content);
			}

			if($this->edit_preview_x)
			{
				$content = $this->edit_file_content;

				$vars['lang_preview_of'] = lang('Preview of %1', $this->path.'/'.$edit_file);

				$vars['preview_content'] = nl2br($content);
			}
			elseif($this->edit_save_x || $this->edit_save_done_x)
			{
				$content = $this->edit_file_content;
				//die( $content);
				if($this->vfs->write(array(
					'string'	=> $this->edit_file,
					'relatives'	=> array(RELATIVE_ALL),
					'content'	=> $content
				)))
				{
					$this->messages[]=lang('Saved %1', $this->path.'/'.$this->edit_file);

					if($this->edit_save_done_x)
					{
						$this->readFilesInfo();
						$this->fileListing();
						exit;
					}
				}
				else
				{
					$this->messages[]=lang('Could not save %1', $this->path.'/'.$this->edit_file);
				}
			}

			# Now we display the edit boxes and forms
			for($j = 0; $j != $this->numoffiles; $j++)
			{
				# If we're in preview or save mode, we only show the file
				# being previewed or saved
				if($this->edit_file &&($this->fileman[$j] != $this->edit_file))
				{
					continue;
				}

				if($this->fileman[$j] && $this->vfs->file_exists(array(
					'string'	=> $this->fileman[$j],
					'relatives'	=> array(RELATIVE_ALL)
				)))
				{
					if($this->edit_file)
					{
						$content = stripslashes($this->edit_file_content);
					}
					else
					{
						$content = $this->vfs->read(array('string' => $this->fileman[$j]));
					}

					$vars['form_action'] = $GLOBALS['egw']->link('/index.php','menuaction=filemanager.uifilemanager.index','path='.$this->path);
					$vars['edit_file'] = $this->fileman[$j];

					# We need to include all of the fileman entries for each file's form,
					# so we loop through again
					for($i = 0; $i != $this->numoffiles; $i++)
					{
						if($this->fileman[$i])
						{
							$value='value="'.$this->fileman[$i].'"';
						}
						$vars['filemans_hidden'] = '<input type="hidden" name="fileman['.$i.']" '.$value.' />';
					}

					$vars['file_content'] = $content;

					$vars['buttonPreview'] = $this->inputImage('edit_preview','edit_preview',lang('Preview %1', $this->html_encode($this->fileman[$j], 1)));
					$vars['buttonSave'] = $this->inputImage('edit_save','save',lang('Save %1', $this->html_encode($this->fileman[$j], 1)));
					$vars['buttonDone'] = $this->inputImage('edit_save_done','ok',lang('Save %1, and go back to file listing ', $this->html_encode($this->fileman[$j], 1)));
					$vars['buttonCancel'] = $this->inputImage('edit_cancel','cancel',lang('Cancel editing %1 without saving', $this->html_encode($this->fileman[$j], 1)));
					$this->t->set_var($vars);
					$this->t->parse('rows','row');
					$this->t->pparse('out','row');

				}
			}
		}

		function history()
		{
			if($this->file) // FIXME this-file is never defined
			{
				$journal_array = $this->vfs->get_journal(array(
					'string'	=> $this->file,//FIXME
					'relatives'	=> array(RELATIVE_ALL)
				));

				if(is_array($journal_array))
				{
					$this->html_table_begin();
					$this->html_table_row_begin();
					$this->html_table_col_begin();
					echo lang('Date');
					$this->html_table_col_end();
					$this->html_table_col_begin();
					echo lang('Version');
					$this->html_table_col_end();
					$this->html_table_col_begin();
					echo lang('Who');
					$this->html_table_col_end();
					$this->html_table_col_begin();
					echo lang('Operation');
					$this->html_table_col_end();
					$this->html_table_row_end();

					while(list($num, $journal_entry) = each($journal_array))
					{
						$this->html_table_row_begin();
						$this->html_table_col_begin();
						$this->html_text($journal_entry['created'] . '&nbsp;&nbsp;&nbsp;');
						$this->html_table_col_end();
						$this->html_table_col_begin();
						$this->html_text($journal_entry['version'] . '&nbsp;&nbsp;&nbsp;' );
						$this->html_table_col_end();
						$this->html_table_col_begin();
						$this->html_text($GLOBALS['egw']->accounts->id2name($journal_entry['owner_id']) . '&nbsp;&nbsp;&nbsp;');
						$this->html_table_col_end();
						$this->html_table_col_begin();
						$this->html_text($journal_entry['comment']);
						$this->html_table_col_end();
					}

					$this->html_table_end();
					$GLOBALS['egw']->common->egw_footer();
					$GLOBALS['egw']->common->egw_exit();
				}
				else
				{
					echo lang('No version history for this file/directory');
				}
			}
		}

		function view()
		{
			if($this->file) //FIXME
			{
				$mime_type='unknown';
				$ls_array = $this->vfs->ls(array(
						'string'        => $this->path.'/'.$this->file,//FIXME
						'relatives'     => array(RELATIVE_ALL),
						'checksubdirs'  => False,
						'nofiles'       => True
				));

				if($ls_array[0]['mime_type'])
				{
						$mime_type = $ls_array[0]['mime_type'];
				}
                		else
                		{
					$parts = explode('.',$this->file);
					$_ext = array_pop($parts);
					$mime_type = ExecMethod('phpgwapi.mime_magic.ext2mime',$_ext);
                		}
				// check if the prefs are set for viewing unknown extensions as text/plain and
				// check if the mime_type is unknown, empty or not found (application/octet)
				// or check if the mimetype contains text,
				// THEN set the mime_type text/plain
				if(($this->prefs['viewtextplain'] && ($mime_type=='' or $mime_type=='unknown' or $mime_type=='application/octet-stream')) or strpos($mime_type, 'text/')!==false)
				{
				
					$mime_type = 'text/plain';
				}
				
				// we want to define pdfs and text files as viewable
				$viewable = array('','text/plain','text/csv','text/html','text/text','application/pdf');
				// we want to view pdfs and text files within the browser
				if(in_array($mime_type,$viewable) && !$_GET['download'])
				{
						// if you add attachment; to the Content-disposition between disposition and filename
						// you get a download dialog even for viewable files
						header('Content-type: ' . $mime_type);
						header('Content-disposition: filename="' . $this->file . '"');//FIXME
						Header("Pragma: public");
				}
				else
				{

						$GLOBALS['egw']->browser->content_header($this->file,$mime_type);//FIXME
				}
				echo $this->vfs->read(array(
					'string'	=> $this->path.'/'.$this->file,//FIXME
					'relatives'	=> array(RELATIVE_NONE)
				));
				$GLOBALS['egw']->common->egw_exit();
			}
		}

		function download()
		{
			for($i = 0; $i != $this->numoffiles; $i++)
			{
				if(!$this->fileman[$i])
				{
					continue;
				}

				$download_browser =& CreateObject('phpgwapi.browser');
				$download_browser->content_header($this->fileman[$i]);
				echo $this->vfs->read(array('string' => $this->fileman[$i]));
				$GLOBALS['egw']->common->egw_exit();
			}
		}

		//give back an array with all directories except current and dirs that are not accessable
		function all_other_directories_options()
		{
			# First we get the directories in their home directory
			$dirs = array();
			$dirs[] = array('directory' => $this->fakebase, 'name' => $this->userinfo['account_lid']);

			$tmp_arr=array(
				'string'	=> $this->homedir,
				'relatives'	=> array(RELATIVE_NONE),
				'checksubdirs'	=> True,
				'mime_type'	=> 'Directory'
			);

			$ls_array = $this->vfs->ls($tmp_arr);

			while(list($num, $dir) = each($ls_array))
			{
				$dirs[] = $dir;
			}


			# Then we get the directories in their readable groups' home directories
			reset($this->readable_groups);
			while(list($num, $group_array) = each($this->readable_groups))
			{
				# Don't list directories for groups that don't have access
				if(!$this->groups_applications[$group_array['account_name']][$this->appname]['enabled'])
				{
					continue;
				}

				$dirs[] = array('directory' => $this->fakebase, 'name' => $group_array['account_name']);

				$tmp_arr=array(
					'string'	=> $this->fakebase.'/'.$group_array['account_name'],
					'relatives'	=> array(RELATIVE_NONE),
					'checksubdirs'	=> True,
					'mime_type'	=> 'Directory'
				);

				$ls_array = $this->vfs->ls($tmp_arr);
				while(list($num, $dir) = each($ls_array))
				{
					$dirs[] = $dir;
				}
			}

			reset($dirs);
			// key for the sorted array
			$i=0;
			while(list($num, $dir) = each($dirs))
			{
				if(!$dir['directory'])
				{
					continue;
				}

				# So we don't display //
				if($dir['directory'] != '/')
				{
					$dir['directory'] .= '/';
				}

				# No point in displaying the current directory, or a directory that doesn't exist
				if((($dir['directory'] . $dir['name']) != $this->path) && $this->vfs->file_exists(array('string' => $dir['directory'] . $dir['name'],'relatives' => array(RELATIVE_NONE))))
				{
					//set the content of the sorted array
					$i++;
					$dirs_sorted[$i]=$dir['directory'] . $dir['name'];
				}
			}
			// sort the directory optionlist
			natcasesort($dirs_sorted);
			//_debug_array($dirs_sorted);
			// set the optionlist
			foreach ($dirs_sorted as $key => $row) {
					//FIXME replace the html_form_option function
					//$options .= $this->html_form_option($dir['directory'] . $dir['name'], $dir['directory'] . $dir['name']);
					$options .= $this->html_form_option($row, $row);
			}
			// save some information with the session for retrieving it later
			if ($dirs_sorted) $this->save_sessiondata($dirs_sorted,'dirs_options_array');
			return $options;
		}

		/* seek icon for mimetype else return an unknown icon */
		function mime_icon($mime_type, $size=16)
		{
			if(!$mime_type)
			{
				$mime_type='unknown';
			}

			$mime_type=	str_replace	('/','_',$mime_type);

			$img=$GLOBALS['egw']->common->image('filemanager','mime'.$size.'_'.strtolower($mime_type));
			if(!$img)
			{
				$img = $GLOBALS['egw']->common->image('filemanager','mime'.$size.'_unknown');
			}

			$icon='<img src="'.$img.' "alt="'.lang($mime_type).'" />';
			return $icon;
		}

		function buttonImage($link,$img='',$help='')
		{
			$image=$GLOBALS['egw']->common->image('filemanager','button_'.strtolower($img));

			if($img)
			{
				return '<td class="fmButton" align="center" valign="middle" height="28" width="28">
				<a href="'.$link.'" title="'.$help.'"><img src="'.$image.'" alt="'.$help.'"/></a>
				</td>';
			}
		}

		function inputImage($name,$img='',$help='')
		{
			$image=$GLOBALS['egw']->common->image('filemanager','button_'.strtolower($img));

			if($img)
			{
				return '<td class="fmButton" align="center" valign="middle" height="28" width="28">
				<input title="'.$help.'" name="'.$name.'" type="image" alt="'.$name.'" src="'.$image.'" value="clicked" />
				</td>';
			}
		}

		function html_form_input($type = NULL, $name = NULL, $value = NULL, $maxlength = NULL, $size = NULL, $checked = NULL, $string = '', $return = 1)
		{
			$text = ' ';
			if($type != NULL && $type)
			{
				if($type == 'checkbox')
				{
					$value = $this->string_encode($value, 1);
				}
				$text .= 'type="'.$type.'" ';
			}
			if($name != NULL && $name)
			{
				$text .= 'name="'.$name.'" ';
			}
			if($value != NULL && $value)
			{
				$text .= 'value="'.$value.'" ';
			}
			if(is_int($maxlength) && $maxlength >= 0)
			{
				$text .= 'maxlength="'.$maxlength.'" ';
			}
			if(is_int($size) && $size >= 0)
			{
				$text .= 'size="'.$size.'" ';
			}
			if($checked != NULL && $checked)
			{
				$text .= 'checked ';
			}

			return '<input'.$text.$string.'>';
		}

		function html_form_option($value = NULL, $displayed = NULL, $selected = NULL, $return = 0)
		{
			$text = ' ';
			if($value != NULL && $value)
			{
				$text .= ' value="'.$value.'" ';
			}
			if($selected != NULL && $selected)
			{
				$text .= ' selected';
			}
			return  '<option'.$text.'>'.$displayed.'</option>';
		}

		function encode_href($href = NULL, $args = NULL , $extra_args)
		{
			$href = $this->string_encode($href, 1);
			$all_args = $args.'&'.$this->string_encode($extra_args, 1);

			$address = $GLOBALS['egw']->link($href, $all_args);

			return $address;
		}

		function html_link($href = NULL, $args = NULL , $extra_args, $text = NULL, $return = 1, $encode = 1, $linkonly = 0, $target = NULL)
		{
			//	unset($encode);
			if($encode)
			{
				$href = $this->string_encode($href, 1);
				$all_args = $args.'&'.$this->string_encode($extra_args, 1);
			}
			else
			{
				//				$href = $this->string_encode($href, 1);
				$all_args = $args.'&'.$extra_args;
			}
			###
			# This decodes / back to normal
			###
			//			$all_args = preg_replace("/%2F/", "/", $all_args);
			//			$href = preg_replace("/%2F/", "/", $href);

			/* Auto-detect and don't disturb absolute links */
			if(!preg_match("|^http(.{0,1})://|", $href))
			{
				//Only add an extra / if there isn't already one there

				// die(SEP);
				if(!($href[0] == SEP))
				{
					$href = SEP . $href;
				}

				/* $GLOBALS['egw']->link requires that the extra vars be passed separately */
				//				$link_parts = explode("?", $href);
				$address = $GLOBALS['egw']->link($href, $all_args);
				//				$address = $GLOBALS['egw']->link($href);
			}
			else
			{
				$address = $href;
			}

			/* If $linkonly is set, don't add any HTML */
			if($linkonly)
			{
				$rstring = $address;
			}
			else
			{
				if($target)
				{
					$target = 'target='.$target;
				}

				$text = trim($text);
				$rstring = '<a href="'.$address.'" '.$target.'>'.$text.'</a>';
			}

			return($this->eor($rstring, $return));
		}

		function html_table_begin($width = NULL, $border = NULL, $cellspacing = NULL, $cellpadding = NULL, $rules = NULL, $string = '', $return = 0)
		{
			if($width != NULL && $width)
			{
				$width = "width=$width";
			}
			if(is_int($border) && $border >= 0)
			{
				$border = "border=$border";
			}
			if(is_int($cellspacing) && $cellspacing >= 0)
			{
				$cellspacing = "cellspacing=$cellspacing";
			}
			if(is_int($cellpadding) && $cellpadding >= 0)
			{
				$cellpadding = "cellpadding=$cellpadding";
			}
			if($rules != NULL && $rules)
			{
				$rules = "rules=$rules";
			}

			$rstring = "<table $width $border $cellspacing $cellpadding $rules $string>";
			return($this->eor($rstring, $return));
		}

		function html_table_end($return = 0)
		{
			$rstring = "</table>";
			return($this->eor($rstring, $return));
		}

		function html_table_row_begin($align = NULL, $halign = NULL, $valign = NULL, $bgcolor = NULL, $string = '', $return = 0)
		{
			if($align != NULL && $align)
			{
				$align = "align=$align";
			}
			if($halign != NULL && $halign)
			{
				$halign = "halign=$halign";
			}
			if($valign != NULL && $valign)
			{
				$valign = "valign=$valign";
			}
			if($bgcolor != NULL && $bgcolor)
			{
				$bgcolor = "bgcolor=$bgcolor";
			}
			$rstring = "<tr $align $halign $valign $bgcolor $string>";
			return($this->eor($rstring, $return));
		}

		function html_table_row_end($return = 0)
		{
			$rstring = "</tr>";
			return($this->eor($rstring, $return));
		}

		function html_table_col_begin($align = NULL, $halign = NULL, $valign = NULL, $rowspan = NULL, $colspan = NULL, $string = '', $return = 0)
		{
			if($align != NULL && $align)
			{
				$align = "align=$align";
			}
			if($halign != NULL && $halign)
			{
				$halign = "halign=$halign";
			}
			if($valign != NULL && $valign)
			{
				$valign = "valign=$valign";
			}
			if(is_int($rowspan) && $rowspan >= 0)
			{
				$rowspan = "rowspan=$rowspan";
			}
			if(is_int($colspan) && $colspan >= 0)
			{
				$colspan = "colspan=$colspan";
			}

			$rstring = "<td $align $halign $valign $rowspan $colspan $string>";
			return($this->eor($rstring, $return));
		}

		function html_table_col_end($return = 0)
		{
			$rstring = "</td>";
			return($this->eor($rstring, $return));
		}
		
		function search_tpl($content=null)
		{
			//echo "<p>search_tpl</p>";
			//_debug_array($content);
			$debug=0;
			$content['message']='';
			if ($_GET['action']=='search') 
			{
				$content['searchcreated']=1;
				$content['datecreatedfrom']=date("U")-24*60*60;
				$content['start_search']=lang('start search');
			}
			
			if ($content['start_search'] && strlen($content['start_search'])>0)
			{
				$searchactivated=1;
				$read_onlys['searchstring']=true;
			}
			$content['nm']=$this->read_sessiondata('nm');
			$this->search_options=$content['nm']['search_options'];
			$content['nm']['search']=$content['searchstring'];
			$debug=$content['debug'];

			// initialisieren des nextmatch widgets, durch auslesen der sessiondaten
			// wenn leer, bzw. kein array dann von hand initialisieren
			$content['nm']=$this->read_sessiondata('nm');
			$content['message'].= "<p>content may be set</p>";
			if (!is_array($content['nm']))
			{
				$content['message'].= "<p>content is not set</p>";
				$content['debug']=$debug;
				$content['nm'] = array(		// I = value set by the app, 0 = value on return / output
					'get_rows'       =>	'filemanager.uifilemanager.get_rows', // I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'filter_label'   =>	'', // I  label for filter    (optional)
					'filter_help'    =>	'', // I  help-msg for filter (optional)
					'no_filter'      => True, // I  disable the 1. filter
					'no_filter2'     => True, // I  disable the 2. filter (params are the same as for filter)
					'no_cat'         => True, // I  disable the cat-selectbox
					//'template'       =>	, // I  template to use for the rows, if not set via options
					//'header_left'    =>	,// I  template to show left of the range-value, left-aligned (optional)
					//'header_right'   =>	,// I  template to show right of the range-value, right-aligned (optional)
					//'bottom_too'     => True, // I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
					'never_hide'     => True, // I  never hide the nextmatch-line if less then maxmatch entrie
					'lettersearch'   => True,// I  show a lettersearch
					'searchletter'   =>	false,// I0 active letter of the lettersearch or false for [all]
					'start'          =>	0,// IO position in list
					//'num_rows'       =>	// IO number of rows to show, defaults to maxmatches from the general prefs
					//'cat_id'         =>	// IO category, if not 'no_cat' => True
					//'search'         =>	// IO search pattern
					'order'          =>	'vfs_created',// IO name of the column to sort after (optional for the sortheaders)
					'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
					'col_filter'     =>	array(),// IO array of column-name value pairs (optional for the filterheaders)
					//'filter'         =>	// IO filter, if not 'no_filter' => True
					//'filter_no_lang' => True// I  set no_lang for filter (=dont translate the options)
					//'filter_onchange'=> 'this.form.submit();'// I onChange action for filter, default: this.form.submit();
					//'filter2'        =>	// IO filter2, if not 'no_filter2' => True
					//'filter2_no_lang'=> True// I  set no_lang for filter2 (=dont translate the options)
					//'filter2_onchange'=> 'this.form.submit();'// I onChange action for filter, default: this.form.submit();
					//'rows'           =>	//  O content set by callback
					//'total'          =>	//  O the total number of entries
					//'sel_options'    =>	//  O additional or changed sel_options set by the callback and merged into $tmpl->sel_options
					'no_columnselection'=>false,
				);
			
			} else {
				// lesen wenn gesetzt
				$content['message'].= "<p>content is set</p>";
				//_debug_print($content);
			}
			//echo "<br>";
			// loeschen wenn gesetzt
			if (($content['clear_search']&&strlen($content['clear_search'])>0) or $_GET['actioncd']=='clear')
			{
				$content['debug']=0;
				$content['nm']['search_options']=array();
				unset($content['nm']['search']);
				$searchactivated=0;
				$content['checkall']=0;
				$content['checkonlyfiles']=0;
				$content['checkonlydirs']=0;
				$content['searchstring']='';
				$content['searchcreated']=0;
				$content['datecreatedto']='';
				$content['datecreatedfrom']='';
				$content['searchmodified']=0;
				$content['datemodifiedto']='';
				$content['datemodifiedfrom']='';
				$read_onlys=array();
				$this->search_options=array();
			}

	        $sel_options = array(
	                'vfs_mime_type' => array('directory'=>'directory', ''=>'')
	        );

			$this->tmpl->read('filemanager.search');
			// the call of this function switches from enabled to disabled for various fields of the search dialog
			//enable_disable_SearchFields($searchactivated);
			//echo "<p>enable_disable_SearchFields</p>";
			//echo "<p>".$content['datecreatedfrom']."</p>";
			$switchflag=$searchactivated;
			$this->tmpl->set_cell_attribute('checkall','disabled',$switchflag);
			if ($content['checkall']) $this->tmpl->set_cell_attribute('alllabel','label',lang($this->tmpl->get_cell_attribute('alllabel','label')).'(x)');
			$this->tmpl->set_cell_attribute('checkonlyfiles','disabled',$switchflag);
			if ($content['checkonlyfiles']) $this->tmpl->set_cell_attribute('filelabel','label',lang($this->tmpl->get_cell_attribute('filelabel','label')).'(x)');
			$this->tmpl->set_cell_attribute('checkonlydirs','disabled',$switchflag);
			if ($content['checkonlydirs']) $this->tmpl->set_cell_attribute('dirlabel','label',lang($this->tmpl->get_cell_attribute('dirlabel','label')).'(x)');
			$this->tmpl->set_cell_attribute('searchstring','disabled',$switchflag);
			//search created date
			if ($content['searchcreated'] or $content['datecreatedfrom']!='' or $content['datecreatedto']!='') 
			{
				$content['searchcreated']=1;
				$this->tmpl->set_cell_attribute('createdlabel','label',lang($this->tmpl->get_cell_attribute('createdlabel','label')).'(x)');
				$read_onlys['datecreatedto']=$switchflag;
				$read_onlys['datecreatedfrom']=$switchflag;
				if (($content['datecreatedfrom']=='' && $content['datecreatedto']) or ($content['datecreatedfrom'] && $content['datecreatedto']=='') ) $content['searchcreatedtext']=lang('Choosing only one date (from/to) will result in a search returning all entries older/younger than the entered date');
				if (($content['datecreatedfrom']!='' && $content['datecreatedto']!='' && $content['datecreatedto']<$content['datecreatedfrom']) ) $content['searchcreatedtext']=lang('Choosing dates where to-date is smaller than the from-date, will result in a search returning all entries but thoose between the two entered dates');
			}
			else
			{
				$content['searchcreatedtext']='';
			}
			$this->tmpl->set_cell_attribute('searchcreated','disabled',$switchflag);
			//search modified date
			if ($content['searchmodified'] or $content['datemodifiedfrom']!='' or $content['datemodifiedto']!='') 
			{
				$content['searchmodified']=1;
				$this->tmpl->set_cell_attribute('modifiedlabel','label',lang($this->tmpl->get_cell_attribute('modifiedlabel','label')).'(x)');
				$read_onlys['datemodifiedto']=$switchflag;
				$read_onlys['datemodifiedfrom']=$switchflag;
				if (($content['datemodifiedfrom']=='' && $content['datemodifiedto']) or ($content['datemodifiedfrom'] && $content['datemodifiedto']=='') ) $content['searchmodifiedtext']=lang('Choosing only one date (from/to) will result in a search returning all entries older/younger than the entered date');
				if (($content['datemodifiedfrom']!='' && $content['datemodifiedto']!='' && $content['datemodifiedto']<$content['datemodifiedfrom']) ) $content['searchmodifiedtext']=lang('Choosing dates where to-date is smaller than the from-date, will result in a search returning all entries but thoose between the two entered dates');
			}
			else
			{
				$content['searchmodifiedtext']='';
			}
			$this->tmpl->set_cell_attribute('searchmodified','disabled',$switchflag);
			$this->tmpl->set_cell_attribute('debuginfos','disabled',!$debug);
			
			//_debug_array($content);
			//echo "<p>#$debug,$searchactivated#</p>";
			$this->search_options['checkall']=$content['checkall'];
			$this->search_options['checkonlyfiles']=$content['checkonlyfiles'];
			$this->search_options['checkonlydirs']=$content['checkonlydirs'];
			$this->search_options['searchstring']=$content['searchstring'];
			$this->search_options['searchcreated']=$content['searchcreated'];
			$this->search_options['datecreatedto']=$content['datecreatedto'];
			$this->search_options['datecreatedfrom']=$content['datecreatedfrom'];
			$this->search_options['searchmodified']=$content['searchmodified'];
			$this->search_options['datemodifiedto']=$content['datemodifiedto'];
			$this->search_options['datemodifiedfrom']=$content['datemodifiedfrom'];
			

			$content['nm']['search_options']=$this->search_options;
			
			$content['nm']['search']=$content['searchstring'];
			$content['nm']['start_search']=$content['start_search'];

			$this->save_sessiondata($content['nm'],'nm');
			// call and execute thge template
			$content['message'].= "<p>execute the template</p>";
			echo $this->tmpl->exec('filemanager.uifilemanager.search_tpl',$content,$sel_options,$read_onlys,array('vfs_file_id'=>$this->data['vfs_file_id']));
			// the debug info will be displayed at the very end of the page
			//_debug_array($content);
				
		}
		/**
		* the call of this function switches from enabled to disabled for various fields of the search dialog
		*/
		function enable_disable_SearchFields($switchflag)
		{
			//does not work at all. The calling of $this->tmpl returns nothing
			return;
			echo "<p>enable_disable_SearchFields</p>";
			$this->tmpl->set_cell_attribute('checkall','disabled',$switchflag);
			if ($content['checkall']) $this->tmpl->set_cell_attribute('alllabel','label',lang($this->tmpl->get_cell_attribute('alllabel','label')).'(x)');
			$this->tmpl->set_cell_attribute('checkonlyfiles','disabled',$switchflag);
			if ($content['checkonlyfiles']) $this->tmpl->set_cell_attribute('filelabel','label',lang($this->tmpl->get_cell_attribute('filelabel','label')).'(x)');
			$this->tmpl->set_cell_attribute('checkonlydirs','disabled',$switchflag);
			if ($content['checkonlydirs']) $this->tmpl->set_cell_attribute('dirlabel','label',lang($this->tmpl->get_cell_attribute('dirlabel','label')).'(x)');
			$this->tmpl->set_cell_attribute('searchstring','disabled',$switchflag);		
			//return $template;
			return true;
		}
		/**
		 * Saves state of the filemanager list in the session
		 *
		 * @param array $values
		 */
		function save_sessiondata($values,$key='')
		{
			if (strlen($key)>0)
			{
				$internalbuffer=$this->read_sessiondata();
				$internalbuffer[$key]=$values;
				$this->save_sessiondata($internalbuffer);
			}
			else
			{
				//echo "<p>$for: uifilemanager::save_sessiondata(".print_r($values,True).") called_by='$this->called_by', for='$for'<br />".function_backtrace()."</p>\n";
				$GLOBALS['egw']->session->appsession(@$this->called_by.'session_data','filemanager',$values);
			}
		}

		/**
		 * reads list-state from the session
		 *
		 * @return array
		 */
		function read_sessiondata($key='')
		{
			$values = $GLOBALS['egw']->session->appsession(@$this->called_by.'session_data','filemanager');
			if (strlen($key)>0)
			{
				return $values[$key];
			}
			else
			{
				return $values;
			}
		}
		/**
		 * query rows for the nextmatch widget
		 *
		 * @param array $query with keys 'start', 'search', 'order', 'sort', 'col_filter'
		 *	For other keys like 'filter', 'cat_id' you have to reimplement this method in a derived class.
		 * @param array &$rows returned rows/competitions
		 * @param array &$readonlys eg. to disable buttons based on acl, not use here, maybe in a derived class
		 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or 
		 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
		 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
		 * @return int total number of rows
		 * optional not used here: $join='',$need_full_no_count=false
		 */
		function get_rows(&$query,&$rows,&$readonlys)
		{
			//echo "<p>retrieve rows</p>";
			$startposition=$query['start'];
			//$this->save_sessiondata($query);
			$sessiondata = $this->read_sessiondata();
			//_debug_array($sessiondata['dirs_options_array']);
			//_debug_array($sessiondata['readable_groups']);
			//_debug_array($sessiondata['groups_applications']);
			if (!($this->search_options))
			{
				$sessiondata['start']=$startposition;
				$this->search_options=$sessiondata['nm']['search_options'];
			}
			//_debug_array($this->search_options);
			$additionalwhereclause=", (select vfs_file_id as fileid, concat(concat(vfs_directory,'/'),vfs_name) as fulldir from egw_vfs WHERE vfs_mime_type <> 'journal' and vfs_mime_type <> 'journal-deleted' and vfs_name is not null and vfs_name <>'' and vfs_name<>'home' and vfs_app='filemanager') vfs2 WHERE vfs2.fileid=egw_vfs.vfs_file_id";
			//search options
			if ($this->search_options['checkonlyfiles'] && !$this->search_options['checkonlydirs']) 
			{
				$additionalwhereclause.=" and vfs_mime_type<>'directory' ";
			}
			elseif ($this->search_options['checkonlydirs'] && !$this->search_options['checkonlyfiles']) 
			{
				$additionalwhereclause.=" and vfs_mime_type='directory' ";
			}
			elseif ($this->search_options['checkonlyfiles'] && $this->search_options['checkonlydirs'])
			{
				
			}
			// timespecific search options
			if ($this->search_options['searchcreated'] && $this->search_options['datecreatedfrom'])
			{
				$additionalwhereclause.=" and vfs_created >=FROM_UNIXTIME(".$this->search_options['datecreatedfrom'].")";
			}
			if ($this->search_options['searchcreated'] && $this->search_options['datecreatedto'])
			{
				$additionalwhereclause.=" and vfs_created <=FROM_UNIXTIME(".$this->search_options['datecreatedto'].")";
			}
			if ($this->search_options['searchmodified'] && $this->search_options['datemodifiedfrom'])
			{
				$additionalwhereclause.=" and vfs_modified >=FROM_UNIXTIME(".$this->search_options['datemodifiedfrom'].")";
			}
			if ($this->search_options['searchmodified'] && $this->search_options['datemodifiedto'])
			{
				$additionalwhereclause.=" and vfs_modified <=FROM_UNIXTIME(".$this->search_options['datemodifiedto'].")";
			}
			// only show contacts if the order-criteria starts with the given letter
			if ($query['searchletter']!=false)
			{
				$additionalwhereclause .= " and ".($query['order']).' '.$GLOBALS['egw']->db->capabilities['case_insensitive_like'].' '.$GLOBALS['egw']->db->quote($query['searchletter'].'%');
			}
			else
			{
				//echo "<p>reset colfilter?!</p>";
				$query['searchletter']=false;
			}
			
			// filter for allowed groups
			$firstleveldirs[]=array();
			$count_fld=0;
			$or='';
			$aclcondition=" ( ";
			array_push($sessiondata['dirs_options_array'],$sessiondata['workingdir']);
			foreach ($sessiondata['dirs_options_array'] as $dir)
			{
				$splitteddir=explode('/',$dir);
				$nix=array_shift($splitteddir);
				$vfsbase=array_shift($splitteddir);
				$vfs1stleveldir=array_shift($splitteddir);
				if (!in_array("/$vfsbase/$vfs1stleveldir", $firstleveldirs)) 
				{
					$count_fld++;
					if ($count_fld>1) $or='or';
					array_push($firstleveldirs,"/$vfsbase/$vfs1stleveldir");
					$aclcondition.=" $or concat(concat(vfs_directory,'/'),vfs_name) like '/$vfsbase/$vfs1stleveldir%' and vfs_mime_type='directory' ";
					$aclcondition.=" or vfs_directory like '/$vfsbase/$vfs1stleveldir%' ";
					//$aclcondition.=" or (vfs_directory='".implode('/',$splitteddir)."' and vfs_name='".$vfsname."')";
				}
			}
			$aclcondition.=")";
			if ($count_fld>0) $additionalwhereclause .= " and ".$aclcondition;
			//echo "<p>$aclcondition</p>";
			
			// save the nextmatch entrys/settings with the sessiondata
			if (!$_POST['exec']['clear_search'])
			{
				$query['search_options']=$this->search_options;
			} 
			else
			{
				//echo "<p>retrieve rows, clear search</p>";
				unset($query['search']);
				unset($query['start_search']);
				$switchflag=0;
				
			}
			$this->save_sessiondata($query,'nm');
			// defaultfilter  we dont want journal, and a whole lot of other stuff excluded, so we use the Join part of get_rows to do that
			// echo "<p>$additionalwhereclause</p>";
	    	$rc=parent::get_rows($query,$rows,$readonlys, $additionalwhereclause);
			//set the applicationheader
			$GLOBALS['egw_info']['flags']['app_header'] = lang('filemanager');
			foreach ($rows as $key => $row)
			{
				$plink=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$rows[$key]['vfs_directory']);
				$linktodir='<a href="'.$plink.'">'.$rows[$key]['vfs_directory'].'</a>&nbsp;';
				if(strtolower($rows[$key]['vfs_mime_type']) == 'directory')
				{
					$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.index','path='.$rows[$key]['vfs_directory']."/".$rows[$key]['vfs_name']);
					$icon=$this->mime_icon($rows[$key]['vfs_mime_type']);
					$col_data='<a href="'.$link.'">'.$icon.'</a>&nbsp;';
					$col_data.='<a href="'.$link.'">'.$rows[$key]['vfs_directory']."/".$rows[$key]['vfs_name'].'</a>&nbsp;';
				}
				else
				{
					if($this->prefs['viewonserver'] && isset($this->filesdir) && !$rows[$key]['vfs_link_directory'])
					{
						#FIXME
						$clickview = $rows[$key]['vfs_directory'].'/'.$rows[$key]['vfs_name'];
					}
					else
					{
						$icon=$this->mime_icon($rows[$key]['vfs_mime_type']);
						$link=$this->encode_href('/index.php','menuaction=filemanager.uifilemanager.view','file='.$rows[$key]['vfs_name'].'&path='.$rows[$key]['vfs_directory']);
						$col_data='<a href="'.$link.'" target="'.$this->target.'">'.$icon.'</a>&nbsp;<a href="'.$link.'" target="'.$this->target.'">'.$rows[$key]['vfs_directory']."/".$rows[$key]['vfs_name'].'</a>';
					}
				}
				$rows[$key]['fulldir']=$col_data;
				$rows[$key]['vfs_directory']=$linktodir;
			}
			// add some info to the appheader that the user may be informed about the search-base of its query-result
			if ($query['searchletter'])
			{
				$order = $order;
				$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang("%1 starts with '%2'",$order,$query['searchletter']);
			}
			if ($query['search'])
			{
				$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang("Search for '%1'",$query['search']);
			}
			return $rc;
	    }
		
	}
