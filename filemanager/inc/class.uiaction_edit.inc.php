<?php
require_once('class.uiaction_base.inc.php');
define('UIEDIT_DEBUG',0);

	class uiaction_edit extends uiaction_base
	{
		//Lists the suported actions (human readable) indexed by their function name
		var $actions=array(
			'edit' => 'Edit',
			'edit_save' => false,
			'edit_preview' => false,
			'edit_cancel' => false
		);
		
		function uiaction_edit()
		{			
					$this->bo = CreateObject('filemanager.bofilemanager');

					$GLOBALS['phpgw']->xslttpl->add_file(array('widgets',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'app_header'));

					$var = Array(
					'img_up' => array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'up'),
											'alt' => lang('Up'),
											'link' => $GLOBALS['phpgw']->link('/index.php',Array(
													'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
													'path'		=> urlencode($this->bo->lesspath)
												))
											)),

					'img_home'	=> array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'folder_home'),
											'alt' => lang('Folder'),
											'link' => $GLOBALS['phpgw']->link('/index.php',Array(
													'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.index',
													'path' => urlencode($this->bo->homedir)
												))										
											)),
											
					'dir' => $this->bo->path,
					'img_dir' => array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'folder_large'),
											'alt' => lang('Folder')									
											)),
				);	
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('filemanager_nav' => $var));

			$this->template_dir = $GLOBALS['phpgw']->common->get_tpl_dir($GLOBALS['phpgw_info']['flags']['currentapp']);		
		}
		function edit($parent, $param=false)
		{
		
			$GLOBALS['phpgw']->xslttpl->add_file(array('edit',$GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'app_header'));
			$GLOBALS['phpgw']->xslttpl->set_var('phpgw',array('form_action' =>$GLOBALS['phpgw']->link('/index.php',
										Array(
											'menuaction'	=> $this->bo->appname.'.ui'.$this->bo->appname.'.action',
											'path'	=> $this->bo->path
										)
									)));
			$this->load_header();
			$this->bo = &$parent->bo;
			if (UIEDIT_DEBUG) echo ' action::edit ';
//			$this->load_header();
			$edit_file = get_var('file', array('GET', 'POST'));
			if (!strlen($edit_file))
			{		
				$edit_file = $this->bo->fileman[0];
			}

			
/*			$this->bo->vfs->cd(array(
				'string' => $this->bo->path,
				'relatives' => array(RELATIVE_NONE)
				));
			
	//		echo "path ".$this->bo->vfs->pwd()." Editing: ".$edit_file;
*/
			$vars = array();
			$vars['action1'][] = array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'preview'),
											));
			$vars['action1'][] = array('widget' => array('type' => 'submit',
						 'name' => "uiaction_edit_preview",
						 'value'=>lang('Preview')
						 ));
			//$this->action_link('edit_preview');
			$vars['action2'][] = array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'filesave'),
											));
			$vars['action2'][]  = array('widget' => array('type' => 'submit',
					'name' => 'uiaction_edit_save',
					'value'=>lang('Save')
					));
			$vars['action3'][] = array('widget' => array('type' => 'img',
											'src' => $GLOBALS['phpgw']->common->image($this->bo->appname,'button_cancel'),
											));

			$vars['action3'][] = array('widget' => array('type' => 'submit',
					'name' => 'uiaction_edit_cancel',
					'value'=>lang('Close')
					));
			$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('nav_data' => $vars));
			$vars = array('filename' => $edit_file);
			
			if (get_var('edited', array('GET', 'POST')))
			{
				$content = get_var('edit_file_content', array('GET', 'POST'));
			}
			else
			{
				$content = $this->bo->vfs->read (array ('string' => $edit_file));
			}
			
			if ($param == 'edit_preview')
			{	
				$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('preview'=>$content));
			//	$vars['preview'] = nl2br($content);
			}
			
			elseif ($param =='edit_save')
			{			 
				if ($this->bo->vfs->write (array (
						'string'	=> $parent->bo->path.'/'.$edit_file ,
						'relatives' => array(RELATIVE_NONE),
						'content'	=> $content
					))
				)
				{
					$vars['output'] = lang('Saved x', $parent->bo->path.'/'.$edit_file);
				}
				else
				{
					$vars['output'] = lang('Could not save x', $parent->bo->path.'/'.$edit_file);
				}
			}

				if ($edit_file && $this->bo->vfs->file_exists (array (
								'string'	=> $edit_file,
								'relatives'	=> array (RELATIVE_ALL)
					))
				)
				{
					$vars['form_data'][] = array('widget' => array('type' => "hidden" ,
											'name'=> "edited",
											'value'=>"1"
											));
											
					$vars['form_data'][] = array('widget' => array('type' => "hidden",
						'name' => 'edit_file',
						'value' => $edit_file
						));

					$vars['form_data'][] = array('widget' => array('type'=>"hidden",
								'name'=> "fileman[0]",
								'value' => $this->bo->html_encode($edit_file,1)
								));
					$vars['file_content'] =  $content;
				}
			//}
			$GLOBALS['phpgw']->xslttpl->set_var('phpgw', array('filemanager_edit' => $vars));
		}
		
		function edit_cancel($parent)
		{
			 $url = $GLOBALS['phpgw']->link('/index.php',
							Array(
								'menuaction'	=> $parent->bo->appname.'.ui'.$parent->bo->appname.'.index',
								'path'		=> urlencode($parent->bo->path),
							)
						);
			//echo "cancel : $url";
			header('Location: '. $url);
			exit();
		}
		function edit_save($parent)
		{
			$this->edit($parent, 'edit_save');
		}	
		
		function edit_preview($parent)
		{
			$this->edit($parent, 'edit_preview');
		}	
	}
?>