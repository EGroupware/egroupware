<?php





	class bofilemanager
	{
		var $public_functions = array(
			'delete'	=> True
		);

		var $so;
		var $vfs;
		var $rootdir;
		var $fakebase;
		var $appname;
		var $settings;
		var $filesdir;
		var $hostname;
		var $userinfo = Array();
		var $homedir;
		var $file_attributes;
		var $help_info;

		var $errors;

		var $rename;
		var $delete;
		var $go;
		var $copy;
		var $move;
		var $download;
		var $createdir;
		var $newdir;
		var $createfile;
		var $newfile;

		var $fileman = Array();
		var $changes = Array();
		var $upload_comment = Array();
		var $upload_file = Array();
		var $op;
		var $file;
		var $help_name;
		var $path;
		var $disppath;
		var $dispsep;
		var $sortby = 'name';
		var $messages = Array();
		var $renamefiles;
		var $comment_files = Array();
		var $show_upload_boxes = 5;
		var $memberships;
		var $now;
		var $matches;

//		var $debug = True;
		var $debug = False;

		function bofilemanager()
		{
			$this->vfs = CreateObject('phpgwapi.vfs');

			$to_decode = Array(
				/*
					Decode
					'var'	when	  'avar' == 'value'
							or
					'var'	when	  'var'  is set
				*/

				'errors',
				'op',
				'path',
				'file',
				'todir',
				'sortby',
				'fileman',
				'upload_file',
				'upload_comment',
				'upload_name',
				'messages',
				'help_name',
				'renamefiles',
				'comment_files',
				'show_upload_boxes',
				'submit',
				'cancel',
				'rename',
				'upload',
				'edit_comments',
				'apply_edit_comment',
				'apply_edit_name',
				'changes',
				'delete',
				'edit',
				'go',
				'copy',
				'move',
				'download',
				'newfile',
				'createfile',
				'newdir',
				'createdir'
			);

			$c_to_decode = count($to_decode);
			for($i=0;$i<$c_to_decode;$i++)
			{
				$this->initialize_vars($to_decode[$i]);
			}

			$this->rootdir = $this->vfs->basedir;
			$this->fakebase = $this->vfs->fakebase;
			$this->appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			$this->settings = $GLOBALS['phpgw_info']['user']['preferences'][$this->appname];
			
			if(stristr($this->rootdir,PHPGW_SERVER_ROOT))
			{
				$this->filesdir = substr($this->rootdir,strlen(PHPGW_SERVER_ROOT));
			}
			else
			{
				$this->filesdir = '';
			}
			$this->hostname = $GLOBALS['phpgw_info']['server']['webserver_url'].$this->filesdir;
			$this->userinfo['username'] = $GLOBALS['phpgw_info']['user']['account_id'];
			$this->userinfo['account_lid'] = $GLOBALS['phpgw']->accounts->id2name($this->userinfo['username']);
			$this->userinfo['hdspace'] = 10000000000;
			$this->homedir = $this->fakebase.SEP.$this->userinfo['account_lid'];

			if(!defined('NULL'))
			{
				define('NULL','');
			}

			$this->so = CreateObject('filemanager.sofilemanager');

			$this->file_attributes = Array(
				'name' => 'Filename',
				'deletable' => 'Deletable',
				'mime_type' => 'MIME Type',
				'size' => 'Size',
				'created' => 'Created',
				'modified' => 'Modified',
				'owner' => 'Owner',
				'createdby_id' => 'Created by',
				'modifiedby_id' => 'Modified by',
				'app' => 'Application',
				'comment' => 'Comment',
				'version' => 'Version'
			);

			if($this->go)
			{
				$this->path = $this->todir;
			}

			if($this->debug)
			{
				echo 'DEBUG: bo.bofilemanager: PATH = '.$this->path.'<br>'."\n";
			}

			if(!$this->path)
			{
				$this->path = $this->vfs->pwd();
				if (!$this->path || $this->vfs->pwd(array(
					'full' => False
					)) == '')
				{
					$this->path = $this->homedir;
				}
			}
			$this->vfs->cd(array(
				'relative' => False,
				'relatives'=>Array(RELATIVE_NONE)
				));
			$this->vfs->cd(array(
				'string' => $this->path,
				'relative' => False,
				'relatives' => Array(RELATIVE_NONE)
				));

			$this->pwd = $this->vfs->pwd();

			if (!$this->cwd = substr($this->path,strlen($this->homedir) + 1))
			{
				$this->cwd = '/';
			}
			else
			{
				$this->cwd = substr($this->pwd,strrpos($this->pwd,'/')+1);
			}

			$this->disppath = $this->path;

			/* This just prevents // in some cases */
			if($this->path == '/')
			{
				$this->dispsep = '';
			}
			else
			{
				$this->dispsep = '/';
			}

			if (!($this->lesspath = substr($this->path,0,strrpos($this->path, '/'))))
			{
				$this->lesspath = '/';
			}

			$this->now = date('Y-m-d');

			if($this->debug)
			{
				echo '<b>Filemanager debug:</b><br>'
					. 'path: '.$this->path.'<br>'
					. 'disppath: '.$this->disppath.'<br>'
					. 'cwd: '.$this->cwd.'<br>'
					. 'lesspath: '.$this->lesspath.'<br>'
					. 'fakebase: '.$this->fakebase.'<br>'
					. 'homedir: '.$this->homedir.'<p>'
					. '<b>phpGW debug:</b><br>'
					. 'real cabsolutepath: '.$this->vfs->getabsolutepath(array(
									'string' => False, 
									'fake' => False
									)).'<br>'
					. 'fake getabsolutepath: '.$this->vfs->getabsolutepath().'<br>'
					. 'appsession: '.$GLOBALS['phpgw']->session->appsession('vfs','').'<br>'
					. 'pwd: '.$this->vfs->pwd().'<br>';
			}


			###
			# Get their memberships to be used throughout the script
			###

			$this->memberships = $GLOBALS['phpgw']->accounts->membership($this->userinfo['username']);

			if(!is_array($this->memberships))
			{
				settype($this->memberships,'array');
				$this->memberships = Array();
			}

			$group_applications = CreateObject('phpgwapi.applications');
			while(list($num,$group_array) = each($this->memberships))
			{
				$group_applications->account_id = get_account_id($GLOBALS['phpgw']->accounts->name2id($group_array['account_name']));
				$this->membership_applications[$group_array['account_name']] = $group_applications->read_account_specific();
			}

			###
			# We determine if they're in their home directory or a group's directory,
			# and set the VFS working_id appropriately
			###
			if((preg_match('+^'.$this->fakebase.'\/(.*)(\/|$)+U',$this->path,$this->matches)) && $this->matches[1] != $this->userinfo['account_lid'])
			{
				$this->vfs->working_id = $GLOBALS['phpgw']->accounts->name2id($matches[1]);
			}
			else
			{
				$this->vfs->working_id = $this->userinfo['username'];
			}
		}

		function initialize_vars($name)
		{
			$var = get_var($name,Array('GET','POST'));
			
			//to get the file uploads, without requiring register_globals in php.ini
			if(phpversion() >= '4.2.0')
			{
				$meth = '_FILES';
			}
			else
			{
				$meth = 'HTTP_POST_FILES';
			}
			if(@isset($GLOBALS[$meth][$name]))
			{
				$var = $GLOBALS[$meth][$name];
			}
			if($this->debug)
			{
				echo '<!-- '.$name.' = '.$var.' -->'."\n";
			}
			if(is_array($this->$name) && $var)
			{
				$temp = Array();
				while(list($varkey,$varvalue) = each($var))
				{
					if(is_int($varkey))
					{
						$temp[$varkey] = urldecode($varvalue);
					}
					else
					{
						$temp[urldecode($varkey)] = $varvalue;
					}
				}
			}
			elseif($var)
			{
				$temp = urldecode($var);
			}
			if(isset($temp))
			{
				$this->$name = $temp;
			}
		}

		function load_files()
		{
			###
			# Read in file info from database to use in the rest of the script
			# $fakebase is a special directory.  In that directory, we list the user's
			# home directory and the directories for the groups they're in
			###

			if ($this->path == $this->fakebase)
			{
				if (!$this->vfs->file_exists(array(
					'string' => $this->homedir,
					'relatives' =>Array(RELATIVE_NONE)
					)))
				{
					$this->vfs->mkdir(array(
						'string' => $this->homedir,
						'relatives' => Array(RELATIVE_NONE)
						));
				}

				$ls_array = $this->vfs->ls(array(
					'string' => $this->homedir,
					'relatives' =>Array(RELATIVE_NONE),
					'checksubdirs' => False,
					'nofiles' => True
					));
				$this->files_array[] = $ls_array[0];

				reset ($this->memberships);
				while(list($num, $group_array) = each($this->memberships))
				{
					###
					# If the group doesn't have access to this app, we don't show it
					###

					if (!$this->membership_applications[$group_array['account_name']][$GLOBALS['appname']]['enabled'])
					{
						continue;
					}

					if (!$this->vfs->file_exists(array(
						'string' => $this->fakebase.'/'.$group_array['account_name'],
						'relatives' => Array(RELATIVE_NONE)
						)))
					{
						$this->vfs->mkdir(array(
							'string' => $this->fakebase.'/'.$group_array['account_name'],
							'relatives' => Array(RELATIVE_NONE)
							));
						$this->vfs->set_attributes(array(
							'string' => $this->fakebase.'/'.$group_array['account_name'],
							'relatives' => Array(RELATIVE_NONE),
							'attributes'=> Array('owner_id' => $group_array['account_id'], 'createdby_id' => $group_array['account_id'])
							));
					}

					$ls_array = $this->vfs->ls(array(
						'string' => $this->fakebase.'/'.$group_array['account_name'],
						'relatives' => Array(RELATIVE_NONE),
						'checksubdirs' => False,
						'nofiles' => True
						));

					$this->files_array[] = $ls_array[0];
				}
			}
			else
			{
				$ls_array = $this->vfs->ls(array(
					'string' => $this->path,
					'relatives' => Array(RELATIVE_NONE),
					'checksubdirs' => False,
					'orderby' =>$this->sortby
					));

				if ($this->debug)
				{
					echo '# of files found in "'.$this->path.'" : '.count($ls_array).'<br>'."\n";
				}

				while(list($num,$file_array) = each($ls_array))
				{
					$this->files_array[] = $file_array;
					if ($this->debug)
					{
						echo 'Filename: '.$file_array['name'].'<br>'."\n";
					}
				}
			}
			if(!is_array($this->files_array))
			{
				$this->files_array = Array();
			}
			return $this->files_array;
		}

		function convert_date($data)
		{
			if($data && $data != '0000-00-00')
			{
				$year = substr($data,0,4);
				$month = substr($data,5,2);
				$day = substr($data,8,2);
				$datetime = mktime(0,0,0,$month,$day,$year);
				$data = date($GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'],$datetime);
			}
			else
			{
				$data = '&nbsp;';
			}
			return $data;
		}

		function f_go()
		{
			$this->path = $this->todir;
		}
		function f_apply_edit_comment()
		{
			$result='';
			for ($i=0; $i<count($this->fileman) ; $i++)
			{
				$file = $this->fileman[$i];
				
				if (!$this->vfs->set_attributes (array (
					'string'	=> $file,
					'relatives'	=> array (RELATIVE_ALL),
					'attributes'	=> array (
							'comment' => stripslashes ($this->changes[$file])
						)
					)
				))
				{
					$result .= lang(' Error: failed to change comment for :').$file."\n";
				}
			}

			return $result;
		}
		
		function f_apply_edit_name()
		{
			$result='';
			while (list ($from, $to) = each ($this->changes))
			{
				if ($badchar = $this->bad_chars ($to, True, True))
				{
			         $result .= 'File names cannot contain "'.$badchar.'"';
					continue;
				}
		
				if (ereg ("/", $to) || ereg ("\\\\", $to))
				{
					//echo $GLOBALS['phpgw']->common->error_list (array ("File names cannot contain \\ or /"));
		        	$result .= "File names cannot contain \\ or /";
				}
				elseif (!$this->vfs->mv (array (
							'from'	=> $from,
							'to'	=> $to
					))
				)
				{
					//echo $GLOBALS['phpgw']->common->error_list (array ('Could not rename '.$disppath.'/'.$from.' to '.$disppath.'/'.$to));
		        	$result .= 'Could not rename '.$this->path.'/'.$from.' to '.$this->path.'/'.$to;
				}
				else 
				{
					$result .= 'Renamed '.$this->path.'/'.$from.' to '.$this->path.'/'.$to;
				}
			}
		   /*html_break (2);
		   html_link_back ();*/
		
		
			/*echo "f_apply_edit_name()";
			print_r($this->fileman);
			echo '<br />';
			print_r($this->changes);
			die();
			
			$result='';
			for ($i=0; $i<count($this->fileman) ; $i++)
			{
				$file = $this->fileman[$i];
				
				if (!$this->vfs->mv (array (
					'from'	=> $file,
					'relatives'	=> array (RELATIVE_ALL),
					'to'	=> $this->changes[$file]
					)
				))
				{
					$result .= lang(' Error: failed to rename :').$file."\n";
				}
			}
*/
			return $result;
		}

		function f_delete()
		{
			$numoffiles = count($this->fileman);
			for($i=0;$i!=$numoffiles;$i++)
			{
				if($this->fileman[$i])
				{
					$ls_array = $this->vfs->ls(array(
						'string' => $this->path.SEP.$this->fileman[$i],
						'relatives' => Array(RELATIVE_NONE),
						'checksubdirs' =>False,
						'nofiles' => True
						));
					$fileinfo = $ls_array[0];
					if($fileinfo)
					{
						if($fileinfo['mime_type'] == 'Directory')
						{
							$mime_type = $fileinfo['mime_type'];
						}
						else
						{
							$mime_type = 'File';
						}
						if($this->vfs->delete(array(
							'string' => $this->path.SEP.$this->fileman[$i],
							'relatives' => Array(RELATIVE_USER_NONE)
							)))
						{
							$errors[] = '<font color="#0000FF">'.$mime_type.' Deleted: '.$this->path.SEP.$this->fileman[$i].'</font>';
						}
						else
						{
							$errors[] = '<font color="#FF0000">Could not delete '.$this->path.SEP.$this->fileman[$i].'</font>';
						}
					}
					else
					{
						$errors[] = '<font color="#FF0000">'.$this->path.SEP.$this->fileman[$i].' does not exist!</font>';
					}
				}
			}
			return $errors;
		}

		function f_copy()
		{
			$numoffiles = count($this->fileman);
			for($i=0;$i!=$numoffiles;$i++)
			{
				if($this->fileman[$i])
				{
					if($this->vfs->cp(array(
						'from' => $this->path.SEP.$this->fileman[$i],
						'to' => $this->todir.SEP.$this->fileman[$i],
						'relatives' => Array(RELATIVE_NONE,RELATIVE_NONE)
						)))
					{
						$errors[] = '<font color="#0000FF">File copied: '.$this->path.SEP.$this->fileman[$i].' to '.$this->todir.SEP.$this->fileman[$i].'</font>';
					}
					else
					{					
						$errors[] = '<font color="#FF0000">Could not copy '.$this->path.SEP.$this->fileman[$i].' to '.$this->todir.SEP.$this->fileman[$i].'</font>';
					}
				}
			}
			return $errors;
		}

		function f_move()
		{
			$numoffiles = count($this->fileman);
			for($i=0;$i!=$numoffiles;$i++)
			{
				if($this->fileman[$i])
				{
					if($this->vfs->mv(array(
						'from' => $this->path.SEP.$this->fileman[$i],
						'to' => $this->todir.SEP.$this->fileman[$i],
						'relatives' => Array(RELATIVE_NONE,RELATIVE_NONE)
						)))
					{
						$errors[] = '<font color="#0000FF">File moved: '.$this->path.SEP.$this->fileman[$i].' to '.$this->todir.SEP.$this->fileman[$i].'</font>';
					}
					else
					{					
						$errors[] = '<font color="#FF0000">Could not move '.$this->path.SEP.$this->fileman[$i].' to '.$this->todir.SEP.$this->fileman[$i].'</font>';
					}
				}
			}
			return $errors;
		}

		function f_download()
		{
			$numoffiles = count($this->fileman);
			for($i=0;$i!=$numoffiles;$i++)
			{
				if($this->fileman[$i] && $this->vfs->file_exists(array(
					'string' => $this->bo->path.SEP.$this->bo->fileman[$i],
					'relatives' => Array(RELATIVE_NONE)
					)))
				{
					execmethod($this->appname.'.ui'.$this->appname.'.view_file',
						Array(
							'path' => $this->path,
							'file' => $this->fileman[$i]
						)
					);
					$errors[] = '<font color="#0000FF">File downloaded: '.$this->path.SEP.$this->fileman[$i].'</font>';
				}
				else
				{
					$errors[] = '<font color="#FF0000">File does not exist: '.$this->path.SEP.$this->fileman[$i].'</font>';
				}
			}
			return $errors;
		}

		function f_newdir()
		{
			if ($this->newdir && $this->createdir)
			{
				if ($badchar = $this->bad_chars($this->createdir,True,True))
				{
					$errors[] = '<font color="#FF0000">Directory names cannot contain "'.$badchar.'"</font>';
					return $errors;
				}
	
				if (substr($this->createdir,strlen($this->createdir)-1,1) == ' ' || substr($this->createdir,0,1) == ' ')
				{
					$errors[] = '<font color="#FF0000">Cannot create directory because it begins or ends in a space</font>';
					return $errors;
				}

				$ls_array = $this->vfs->ls(array(
					'string' => $this->path.SEP.$this->createdir,
					'relatives' => Array(RELATIVE_NONE),
					'checksubdirs' => False,
					'nofiles' => True
					));
				$fileinfo = $ls_array[0];

				if ($fileinfo['name'])
				{
					if ($fileinfo['mime_type'] != 'Directory')
					{
						$errors[] = '<font color="#FF0000">'.$fileinfo['name'].' already exists as a file</font>';
					}
					else
					{
						$errors[] = '<font color="#FF0000">Directory '.$fileinfo['name'].' already exists.</font>';
					}
				}
				else
				{
					if ($this->vfs->mkdir(array(
						'string' => $this->path.SEP.$this->createdir,
						'relatives' => Array(RELATIVE_NONE)
						)))
					{
						$errors[] = '<font color="#0000FF">Created directory '.$this->path.SEP.$this->createdir.'</font>';
//						$this->path = $this->path.SEP.$this->createdir;
					}
					else
					{
						$errors[] = '<font color="#FF0000">Could not create '.$this->path.SEP.$this->createdir.'</font>';
					}
				}
			}
			return $errors;
		}

		function f_newfile()
		{
			if ($this->newfile && $this->createfile)
			{
				if($badchar = $this->bad_chars($this->createfile,True,True))
				{
					$errors[] = '<font color="#FF0000">Filenames cannot contain "'.$badchar.'"</font>';
					return $errors;
				}
				if($this->vfs->file_exists(array(
					'string' => $this->path.SEP.$this->createfile,
					'relatives' => Array(RELATIVE_NONE)
					)))
				{
					$errors[] = '<font color="#FF0000">File '.$this->path.SEP.$this->createfile.' already exists.  Please edit it or delete it first.</font>';
					return $errors;
				}
				if(!$this->vfs->touch(array(
					'string' => $this->path.SEP.$this->createfile,
					'relatives' => Array(RELATIVE_NONE)
					)))
				{
					$errors[] = '<font color="#FF0000">File '.$this->path.SEP.$this->createfile.' could not be created.</font>';
				}
			}
			else
			{
				$errors[] = '<font color="#FF0000">Filename not provided!</font>';
			}
			return $errors;
		}
		
		function f_upload()
		{
		/*	echo 'sub:'.$this->show_upload_boxes .' uf: ';
			
			print_r($this->upload_file);
			echo  ' cf: '; print_r($this->upload_comment);
			echo ' files: '; print_r($HTTP_POST_FILES);
			die();*/
			//echo (($show_upload_boxes > 1) ? $head_pre.$msg_top : $head_top);
			for ($i = 0; $i != $this->show_upload_boxes; $i++)
			{
				if ($badchar = $this->bad_chars ($this->upload_file['name'][$i], True, True))
				{
					array_push($err_msgs,$this->html_encode ('Filenames cannot contain "'.$badchar.'"', 1));
		         //echo $GLOBALS['phpgw']->common->error_list (array (html_encode ('Filenames cannot contain "'.$badchar.'"', 1)));
					continue;
				}
		
				###
				# Check to see if the file exists in the database, and get its info at the same time
				###
		
				$ls_array = $this->vfs->ls (array (
						'string'	=> $this->path . '/' . $this->upload_file['name'][$i],
						'relatives'	=> array (RELATIVE_NONE),
						'checksubdirs'	=> False,
						'nofiles'	=> True
					)
				);
		
				$fileinfo = $ls_array[0];
		
				if ($fileinfo['name'])
				{
					if ($fileinfo['mime_type'] == 'Directory')
					{
						array_push($err_msgs,'Cannot replace '.$fileinfo['name'].' because it is a directory');
		            //echo $GLOBALS['phpgw']->common->error_list (array ('Cannot replace '.$fileinfo['name'].' because it is a directory'));
						continue;
					}
				}
		
				if ($this->upload_file['size'][$i] > 0)
				{
					if ($fileinfo['name'] && $fileinfo['deleteable'] != 'N')
					{
						if (
		      				$this->vfs->cp (array (
		                     'from'	=> $this->upload_file['tmp_name'][$i],
		                     'to'	=> $this->upload_file['name'][$i],
		                     'relatives'	=> array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
		                  )
		               )
		            ) {
		               $this->vfs->set_attributes (array (
		                     'string'	=> $this->upload_file['name'][$i],
		                     'relatives'	=> array (RELATIVE_ALL),
		                     'attributes'	=> array (
		                              'owner_id' => $GLOBALS['userinfo']['username'],
		                              'modifiedby_id' => $GLOBALS['userinfo']['username'],
		                              'modified' => $now,
		                              'size' => $this->upload_file['size'][$i],
		                              'mime_type' => $this->upload_file['type'][$i],
		                              'deleteable' => 'Y',
		                              'comment' => stripslashes ($upload_comment[$i])
		                           )
		                  )
		               );
		               
		            } else {
		               array_push($err_msgs,'Failed to upload file: '.$this->upload_file['name'][$i]);
		               continue;
		            }
		           
						$result .=' Replaced '.$disppath.'/'.$this->upload_file['name'][$i].' '.$this->upload_file['size'][$i];
					}
					else
					{
						if (
		               $this->vfs->cp (array (
		                     'from'	=> $this->upload_file['tmp_name'][$i],
		                     'to'	=> $this->upload_file['name'][$i],
		                     'relatives'	=> array (RELATIVE_NONE|VFS_REAL, RELATIVE_ALL)
		                  )
		               )
		            ) {
		
		               $this->vfs->set_attributes (array (
		                     'string'	=> $this->upload_file['name'][$i],
		                     'relatives'	=> array (RELATIVE_ALL),
		                     'attributes'	=> array (
		                              'mime_type' => $this->upload_file_['type'][$i],
		                              'comment' => stripslashes ($this->upload_comment[$i])
		                           )
		                  )
		               );
		            } else {
		               array_push($err_msgs,'Failed to upload file: '.$this->upload_file['name'][$i]);
		               continue;
		            }
						$result .= 'Created '.$this->path.'/'.$this->upload_file['name'][$i] .' '. $this->upload_file['size'][$i];
					}
				}
				elseif ($this->upload_file['name'][$i])
				{
					$this->vfs->touch (array (
							'string'	=> $this->upload_file['name'][$i],
							'relatives'	=> array (RELATIVE_ALL)
						)
					);
		
					$this->vfs->set_attributes (array (
							'string'	=> $this->upload_file['name'][$i],
							'relatives'	=> array (RELATIVE_ALL),
							'attributes'	=> array (
										'mime_type' => $this->upload_file['type'][$i],
										'comment' => $this->upload_comment[$i]
									)
						)
					);
		
					$result .= 'Created '.$this->path.'/'.$this->upload_file['name'][$i].' '. $this->file_size[$i];
				}
			}
		
		   //output any error messages
		 //  $backlink = ($show_upload_boxes > 1) ? '<a href="javascript:window.close();">Back to file manager</a>' : html_link_back(1);
		   $refreshjs = '
		   <script language="javascript">
		      window.opener.processIt(\'update\');
		   </script>';
		   
//		   if (sizeof($err_msgs)) echo $GLOBALS['phpgw']->common->error_list ($err_msgs,'Error',$backlink);

			return $result.$err_msgs;
		
		}
		function load_help_info()
		{
			$this->help_info = Array(
				array ("up", "The Up button takes you to the directory above the current directory.  For example, if you're in /home/jdoe/mydir, the Up button would take you to /home/jdoe."),
				array ("directory_name", "The name of the directory you're currently in."),
				array ("home", "The Home button takes you to your personal home directory."),
				array ("sort_by", "Click on any of the column headers to sort the list by that column."),
				array ("filename", "The name of the file or directory."),
				array ("mime_type", "The MIME-type of the file.  Examples include text/plain, text/html, image/jpeg.  The special MIME-type Directory is used for directories."),
				array ("size", "The size of the file or directory in the most convenient units: bytes (B), kilobytes (KB), megabytes (MB), gigabytes (GB).  Sizes for directories include subfiles and subdirectories."),
				array ("created", "When the file or directory was created."),
				array ("modified", "When the file or directory was last modified."),
				array ("owner", "The owner of the file or directory.  This can be a user or group name."),
				array ("created_by", "Displays who created the file or directory."),
				array ("modified_by", "Displays who last modified the file or directory."),
				array ("application", "The application associated with the file or directory.  Usually the application used to create it.  A blank application field is ok."),
				array ("comment", "The comment for the file or directory.  Comments can be set when creating the file or directory, and created or edited any time thereafter."),
				array ("version", "The current version for the file or directory.  Clicking on the version number will display a list of changes made to the file or directory."),
				array ("edit", "Edit the text of the selected file(s).  You can select more than one file; this is useful when you want to copy part of one file into another.  Clicking Preview will show you a preview of the file.  Click Save to save your changes."),
				array ("rename", "Rename the selected file(s).  You can select as many files or directories as you want.  You are presented with a text field to enter the new name of each file or directory."),
				array ("delete", "Delete the selected file(s).  You can select as many files or directories as you want.  When deleting directories, the entire directory and all of its contents are deleted.  You will not be prompted to make sure you want to delete the file(s); make sure you really want to delete them before clicking Delete."),
				array ("edit_comments", "Create a comment for a file or directory, or edit an existing comment.  You can select as many files or directories as you want."),
				array ("go_to", "The Go to button takes you to the directory selected in the drop down [directory_list|Directory List]."),
				array ("copy_to", "This will copy all selected files and directories to the directory selected in the drop down [directory_list|Directory List]."),
				array ("move_to", "This will move all selected files and directories to the directory selected in the drop down [directory_list|Directory List]."),
				array ("directory_list", "The Directory List contains a list of all directories you have (at least) read access to.  Selecting a directory and clicking one of the [go_to|Go to]/[copy_to|Copy to]/[move_to|Move to] buttons will perform the selected action on that directory.  For example, if you select \"/home/somegroup/reports\" from the Directory List, and click the \"[copy_to|Copy to]\" button, all selected files and directories will be copied to \"/home/somegroup/reports\"."),
				array ("download", "Download the first selected file to your local computer.  You can only download one file at a time.  Directories cannot be downloaded, only files."),
				array ("create_folder", "Creates a directory (folder == directory).  The name of the directory is specified in the text box next to the Create Folder button."),
				array ("create_file", "Creates a file in the current directory.  The name of the file is specified in the text box next to the Create File button.  After clicking the Create File button you will be presented with the [edit|Edit] screen, where you may edit the file you just created.  If you do not with to make any changes to the file at this time, simply click the Save button and the file will be saved as an empty file."),
				array ("command_line", "Enter a Unix-style command line here, which will be executed when the [execute|Execute] button is pressed.  If you don't know what this is, you probably should turn the option off in the Preferences."),
				array ("execute", "Clicking the Execute button will execute the Unix-style [command_line|command line] specified in the text box above.  If you don't know what this is, you probably should turn the option off in the Preferences."),
				array ("update", "Sync the database with the filesystem for the current directory.  This is useful if you use another interface to access the same files.  Any new files or directories in the current directory will be read in, and the attributes for the other files will be updated to reflect any changes to the filesystem.  Update is run automatically every few page loads (currently every 20 page loads as of this writing, but that may have changed by now)."),
				array ("file_stats", "Various statistics on the number and size of the files in the current directory.  In some situations, these reflect different statistics.  For example, when in / or the base directory."),
				array ("upload_file", "The full path of the local file to upload.  You can type it in or use the Browse.. button to select it.  The file will be uploaded to the current directory.  You cannot upload directories, only files."),
				array ("upload_comment", "The inital comment to use for the newly uploaded file.  Totally optional and completely arbitrary.  You can [edit_comments|create or edit the comment] at any time in the future."),
				array ("upload_files", "This will upload the files listed in the input boxes above, and store them in the current directory."),
				array ("show_upload_fields", "This setting determines how many [upload_files|upload fields] will be shown at once.  You can change the default number that will be shown in the preferences.")
			);
		}

		function borkb ($size,$enclosed = NULL,$return = 0)
		{
			if(!$size)
			{
				$size = 0;
			}

			if($enclosed)
			{
				$left = '(';
				$right = ')';
			}

			if($size<1024)
			{
				return $left.$size.'&nbsp;B&nbsp;&nbsp;'.$right;
			}
			else
			{
				return $left.round($size/1024).'&nbsp;KB'.$right;
			}
		}

		###
		# Check for and return the first unwanted character
		###

		function bad_chars($string,$all = True,$return = 0)
		{
			if($all)
			{
				if (preg_match("-([\\/<>\'\"\&])-", $string, $badchars))
				{
					$rstring = $badchars[1];
				}
			}
			else
			{
				if (preg_match("-([\\/<>])-", $string, $badchars))
				{
					$rstring = $badchars[1];
				}
			}
			return $rstring;
		}

		###
		# Match character in string using ord ().
		###
		function ord_match($string, $charnum)
		{
			for ($i=0;$i<strlen($string);$i++)
			{
				$character = ord(substr($string,$i,1));

				if ($character == $charnum)
				{
					return True;
				}
			}
			return False;
		}

		###
		# Decide whether to echo or return.  Used by HTML functions
		###

		function eor($rstring,$return)
		{
			if($return)
			{
				return $rstring;
			}
			else
			{
				html_text($rstring."\n");
				return 0;
			}
		}

		###
		# URL encode a string
		# First check if its a query string, then if its just a URL, then just encodes it all
		# Note: this is a hack.  It was made to work with form actions, form values, and links only,
		# but should be able to handle any normal query string or URL
		###

		function string_encode($string,$return = False)
		{
			if (preg_match("/=(.*)(&|$)/U",$string))
			{
				$rstring = preg_replace("/=(.*)(&|$)/Ue","'='.rawurlencode(base64_encode ('\\1')).'\\2'",$string);
			}
			elseif (ereg('^'.$this->hostname,$string))
			{
				$rstring = ereg_replace('^'.$this->hostname.'/','',$string);
				$rstring = preg_replace("/(.*)(\/|$)/Ue","rawurlencode (base64_encode ('\\1')).'\\2'",$rstring);
				$rstring = $this->hostname.'/'.$rstring;
			}
			else
			{
				$rstring = rawurlencode($string);

				/* Terrible hack, decodes all /'s back to normal */  
				$rstring = preg_replace("/%2F/",'/',$rstring);
			}

			return($this->eor($rstring,$return));
		}

		function string_decode($string,$return = False)
		{
			$rstring = rawurldecode($string);

			return($this->eor($rstring,$return));
		}

		###
		# HTML encode a string
		# This should be used with anything in an HTML tag that might contain < or >
		###

		function html_encode($string, $return)
		{
			return($this->eor(htmlspecialchars($string),$return));
		}

		function translate ($text)
		{
			return($GLOBALS['phpgw']->lang($text));
		}
	}
?>
