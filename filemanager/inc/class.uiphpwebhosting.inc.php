<?php

	class uiphpwebhosting
	{

		var $public_functions = array(
			'index'	=> True,
			'view'	=> True,
			'view_file'	=> True
		);

		var $bo;
		var $nextmatchs;
		var $browser;
		var $tempalte_dir;
		var $help_info;

		function uiphpwebhosting()
		{
			$this->bo = CreateObject('phpwebhosting.bophpwebhosting');
			$this->nextmatchs = CreateObject('phpgwapi.nextmatchs');
			$this->browser = CreateObject('phpgwapi.browser');
			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir($GLOBALS['phpgw_info']['flags']['currentapp']);
			$this->load_header();
			$this->check_access();
			$this->create_home_dir();
			$this->verify_path();
			$this->update();
		}

		function load_header()
		{
			if(($this->bo->download && (count($this->bo->fileman) > 0)) || ($this->bo->op == 'view' && $this->bo->file) || ($this->bo->op == 'history' && $this->bo->file) || ($this->bo->op == 'help' && $this->bo->help_name))
			{
			}
			else
			{
				unset($GLOBALS['phpgw_info']['flags']['noheader']);
				unset($GLOBALS['phpgw_info']['flags']['nonavbar']);
				unset($GLOBALS['phpgw_info']['flags']['noappheader']);
				unset($GLOBALS['phpgw_info']['flags']['noappfooter']);
				$GLOBALS['phpgw']->common->phpgw_header();
			}
		}

		function check_access()
		{
			if($this->bo->path != $this->bo->homedir && $this->bo->path != $this->bo->fakebase && $this->bo->path != '/' && !$this->bo->vfs->acl_check($this->bo->path,Array(RELATIVE_NONE),PHPGW_ACL_READ))
			{
				$this->no_access_exists(lang('You do not have access to X',$this->bo->path));			
			}
			$this->bo->userinfo['working_id'] = $this->bo->vfs->working_id;
			$this->bo->userinfo['working_lid'] = $GLOBALS['phpgw']->accounts->id2name($this->bo->userinfo['working_id']);
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
						'menuaction'	=> 'phpwebhosting.uiphpwebhosting.index',
						'path'	=> $this->bo->homedir
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
				$this->bo->vfs->mkdir($this->bo->homedir,Array(RELATIVE_NONE));
				//$this->bo->vfs->override_acl = 0;
			}
//			elseif(preg_match("|^".$this->bo->fakebase."\/(.*)$|U",$this->bo->path,$this->bo->matches))
//			{
//				if (!$this->bo->vfs->file_exists($this->bo->path,Array(RELATIVE_NONE)))
//				{
//					//$this->bo->vfs->override_acl = 1;
//					$this->bo->vfs->mkdir($this->bo->path,Array(RELATIVE_NONE));
//					//$this->bo->vfs->override_acl = 0;
//
//					if($this->bo->debug)
//					{
//						echo 'DEBUG: ui.create_home_dir: PATH = '.$this->bo->path.'<br>'."\n";
//						echo 'DEBUG: ui.create_home_dir(): matches[1] = '.$this->bo->matches[1].'<br>'."\n";
//					}
//					
//					$group_id = $GLOBALS['phpgw']->accounts->name2id($this->bo->matches[1]);
//					if($group_id)
//					{
//						$this->bo->vfs->set_attributes($this->bo->path,Array(RELATIVE_NONE),Array('owner_id' => $group_id, 'createdby_id' => $group_id));
//					}
//				}
//			}
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
				$this->no_access_exists(lang('Directory '.$this->bo->path.' does not exist'));
			}
		}

		function update()
		{
			/* Update if they request it, or one out of 20 page loads */
			srand ((double) microtime() * 1000000);
			if($this->bo->update || rand(0, 19) == 4)
			{
				$this->bo->vfs->update_real($this->bo->path,Array(RELATIVE_NONE));
			}
			if($this->bo->update)
			{
				// This needs to redirect to the index after the user issues an update.....
			}
		}

		function build_help($help_option)
		{
			if($this->bo->settings['show_help'])
			{
				return '<font size="-2" color="maroon" >'."\n"
					. '      <a href="'
					. $GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.help',
							'help_name'	=> urlencode($help_option),
						)
					)
					. '" target="_new">[?]</a>'."\n"
					. '     </font>';
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

		function column_header($internal,$displayed,$link=True)
		{
			if($link)
			{
				$header_str = '<a href="'
					. $GLOBALS['phpgw']->link('/index.php',
						Array(
							'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
							'sortby'		=> $internal
						)
					).'"><b>'.lang($displayed).'</b></a>'.$this->build_help(ereg_replace(' ','_',$displayed));
			}
			else
			{
				$header_str = $displayed.$this->build_help(ereg_replace(' ','_',$displayed));
			}
			return Array(
				'td_extras'	=> '',
				'column_header'	=> $header_str
			);
		}

		function index()
		{
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
				$p->set_block('_index','column_rows','column_rows');
				
				$tr = $this->nextmatchs->alternate_row_color($tr);
				$p->set_var('tr_extras',' bgcolor="'.$tr.'" border="0"');
				$var = Array(
					'img_up'	=> $this->link(Array(
								'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
								'path'		=> $this->bo->lesspath
							),
						$this->image('folder_up.gif',lang('Up'))),
					'help_up'	=> $this->build_help('up'),
					'img_home'	=> $this->image('folder_home.gif',lang('Folder')),
					'dir'		=> '<font size="4" color="maroon" >'."\n"
				       . '       <b>'.strtoupper($this->bo->path).'</b>'."\n"
				       . '      </font>',
					'help_home'	=> $this->build_help('home'),
				);	
				$p->set_var($var);

				$p->set_var($this->column_header('','Sort By:',False));
				$p->parse('col_headers','column_headers',True);

				$columns = 1;
				reset($this->bo->file_attributes);
				while(list($internal,$displayed) = each($this->bo->file_attributes))
				{
					if ($this->bo->settings[$internal])
					{
						$p->set_var($this->column_header($internal,$displayed,True));
						$p->parse('col_headers','column_headers',True);
						$columns++;
					}
				}
				$tr = $this->nextmatchs->alternate_row_color($tr);
				$p->set_var('tr_extras',' bgcolor="'.$tr.'" border="0"');
				$p->parse('col_row','column_rows',True);

				$p->set_var('colspan',$columns);
				
				if($this->bo->settings['dotdot'] && $this->bo->settings['name'] && $this->bo->path != '/')
				{
					$var = Array(
						'col_headers'	=> '',
						'td_extras'	=> '',
						'column_header'	=> '&nbsp;'
					);
					$p->set_var($var);
					$p->parse('col_headers','column_headers',True);

					$var = Array(
						'td_extras'	=> '',
						'column_header'	=> $this->image('folder.gif','folder')
							.$this->link(
								Array(
									'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
									'path'		=> $this->bo->lesspath
								),
								'<b>..</b>'
							)
					);
					$p->set_var($var);
					$p->parse('col_headers','column_headers',True);

					$loop_cntr = 2;

					if($this->bo->settings['mime_type'])
					{
						$var = Array(
							'td_extras'	=> '',
							'column_header'	=> 'Directory'
						);
						$loop_cntr++;
					}
					$p->set_var($var);
					$p->parse('col_headers','column_headers',True);

					$var = Array(
						'td_extras'	=> '',
						'column_header'	=> '&nbsp;'
					);
					for($i=$loop_cntr;$i<$columns;$i++)
					{
						$p->set_var($var);
						$p->parse('col_headers','column_headers',True);
					}
					$tr = $this->nextmatchs->alternate_row_color($tr);
					$p->set_var('tr_extras',' bgcolor="'.$tr.'" border="0"');
					$p->parse('col_row','column_rows',True);
					$p->set_var('col_headers','');
				}

				reset($files_array);
				$numoffiles = count($files_array);
				for($i=0;$i!=$numoffiles;$i++)
				{
					$files = $files_array[$i];
					$var = Array(
						'td_extras'	=> '',
						'column_header'	=> '<input type="checkbox" name="fileman[]" value="'.urlencode($files['name']).'">'
					);
					$p->set_var($var);
					$p->parse('col_headers','column_headers');

					reset($this->bo->file_attributes);
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
															'path'		=> $this->bo->path.$this->bo->dispsep.$files['name']
														),
														'<b>'.$files['name'].'</b>'
													);
											break;
										default:
											$var['column_header']	= $this->link(
												Array(
													'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.view',
//													'op'		=> 'view',
													'path'		=> urlencode($this->bo->path),
													'file'		=> urlencode($files['name'])
												),
												'<b>'.$files['name'].'</b>'
											);
//											$var['column_header']	= '<a href="'.$GLOBALS['phpgw']->link('/'.$this->bo->appname.'/view_file.php',
//												Array(
//													'path'		=> urlencode($this->bo->path),
//													'file'		=> urlencode($files['name'])
//												)
//											).'"><b>'.$files['name'].'</b></a>';
											break;
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
									if($files[$internal] && $files[$internal] != '0000-00-00')
									{
										$year = substr($files[$internal],0,4);
										$month = substr($files[$internal],5,2);
										$day = substr($files[$internal],8,2);
//										echo $files['name'].' : '.$internal.' : '.$year.'.'.$month.'.'.$day.'<br>'."\n";
										$datetime = mktime(0,0,0,$month,$day,$year);
										$var['column_header'] = date($GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'],$datetime);
									}
									else
									{
										$var['column_header'] = '&nbsp;';
									}
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
							$p->set_var($var);
							$p->parse('col_headers','column_headers',True);
						}
					}
					$tr = $this->nextmatchs->alternate_row_color($tr);
					$p->set_var('tr_extras',' bgcolor="'.$tr.'" border="0"');
					$p->parse('col_row','column_rows',True);
					$p->set_var('col_headers','');
				}
					

				$p->pfp('output','index');
			}
		}

		function view()
		{

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

		function view_file()
		{
			$file = $this->bo->path.$this->bo->dispsep.$this->bo->file;
			if($this->bo->vfs->file_exists($file,Array(RELATIVE_NONE)))
			{
				Header('Content-length: '.$this->bo->vfs->get_size($file,Array(RELATIVE_NONE)));
				Header('Content-type: '.$this->bo->vfs->file_type($file,Array(RELATIVE_NONE)));
				Header('Content-disposition: attachment; filename="'.$this->bo->file.'"');
				echo $this->bo->vfs->read($file,Array(RELATIVE_ALL));
				flush();
			}

			$GLOBALS['phpgw']->common->phpgw_exit ();
		}
	}
