<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Lukas Weiss [ichLukas@gmx.de] and                             *
	*            Cornelius Weiss [nelius@gmx.net]                              *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

class ui_resources
{
	var $public_functions = array(
		'index'		=> True,
		'edit'		=> True,
		'show'		=> True,
		'admin'		=> True,
		'writeLangFile'	=> True
		);

	/*!
		@function ui_resources
		@abstract constructor of class ui_resources
	*/
	function ui_resources()
	{
// 		print_r($GLOBALS['phpgw_info']); die();
		$this->tmpl	= CreateObject('etemplate.etemplate','resources.show');
		$this->bo	= CreateObject('resources.bo_resources');
		
		if(!@is_object($GLOBALS['phpgw']->js))
		{
			$GLOBALS['phpgw']->js = CreateObject('phpgwapi.javascript');
		}
	}

	/*!
		@function index
		@abstract main resources list.
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param array $content content from eTemplate callback
		
		FIXME don't translate cats in nextmach
	*/
	function index($content='')
	{
		if (is_array($content))
		{
			if (isset($content['nm']['rows']))
			{
				unset($content['nm']['rows']['checkbox']);
				switch (key($content['nm']['rows']))
				{
					case 'edit':
						list($id) = each($content['nm']['rows']['edit']);
						return $this->edit($id);
					case 'delete':
						list($id) = each($content['nm']['rows']['delete']);
						return $this->delete($id);
					case 'new_acc':
						list($id) = each($content['nm']['rows']['new_acc']);
						return $this->edit(array('id' => 0, 'accessory_of' => $id));
					case 'view_acc':
						list($id) = each($content['nm']['rows']['view_acc']);
						$content['view_accs_of'] = $id;
						break;
					case 'view':
					case 'bookable':
					case 'buyable':
				}
			}
			if (isset($content['add']))
			{
				return $content['view_accs_of'] ? $this->edit(array('id' => 0, 'accessory_of' => $id)) : $this->edit(0);
			}
			if (isset($content['back']))
			{
				return $this->index();
			}
			
		}
		$this->tmpl->read('resources.show');
		
		$content['nm']['get_rows'] 	= 'resources.bo_resources.get_rows';
		$content['nm']['no_filter'] 	= False;
		$content['nm']['filter_label']	= 'Category';
		$content['nm']['filter_help']	= lang('Select a category'); // is this used???
		$content['nm']['options-filter']= array('0'=>lang('all categories'))+(array)$this->bo->acl->get_cats(PHPGW_ACL_READ);
		$content['nm']['no_filter2']	= true;
		$content['nm']['filter_no_lang'] = true;
		$content['nm']['no_cat']	= true;
		
		// check if user is permitted to add resources
		if(!$this->bo->acl->get_cats(PHPGW_ACL_ADD))
		{
			$no_button['add'] = true;
		}
		$no_button['back'] = true;
		
		if($content['view_accs_of'])
		{
			$content['nm']['get_rows'] 	= 'resources.bo_resources.get_rows';
			$content['nm']['no_filter'] 	= true;
			$content['nm']['no_filter2'] 	= true;
			$content['nm']['view_accs_of']	= $content['view_accs_of'];
			$no_button['back'] = false;
		}
		$preserv = $content;
		$this->tmpl->exec('resources.ui_resources.index',$content,$sel_options,$no_button,$preserv);
	}

	/*!
		@function edit
		@syntax edit($content=0)
		@author Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@abstract invokes add or edit dialog for resources
		@param $content   Content from the eTemplate Exec call or id on inital call
	*/
	function edit($content=0)
	{
		if (is_array($content))
		{
			if(isset($content['save']) || isset($content['delete']))
			{
				if(isset($content['save']))
				{
					unset($content['save']);
					if($content['id'] != 0)
					{
						// links are already saved by eTemplate
						unset($resource['link_to']['to_id']);
					}
					$content['msg'] = $this->bo->save($content);
				}
				if(isset($content['delete']))
				{
					unset($content['delete']);
					$content['msg'] = $this->delete($content['id']);
				}
				return $content['msg'] ? $this->edit($content) : $content['accessory_of'] ? $this->index(array('view_accs_of' => $content['accessory_of'])) : $this->index();
			}
			elseif($content['cancel'])
			{
				return $this->index();
			}
		}
		else
		{
			$id = $content;
			$content = array('id' => $id);
			
			if ($id > 0)
			{
				$content = $this->bo->read($id);
				$content['gen_src_list'] = strstr($content['picture_src'],'.') ? $content['picture_src'] : false;
				$content['picture_src'] = strstr($content['picture_src'],'.') ? 'gen_src' : $content['picture_src'];
				$content['link_to'] = array(
					'to_id' => $id,
					'to_app' => 'resources'
				);
			}
			
		}
		// some presetes
		$content['resource_picture'] = $this->bo->get_picture($content['id'],$content['picture_src'],$size=true);
		$content['accessory_of'] = $content['accessory_of'] ? $content['accessory_of'] : -1;
		$content['quantity'] = $content['quantity'] ? $content['quantity'] : 1;
		$content['useable'] = $content['useable'] ? $content['useable'] : 1;
		
		$sel_options['cat_id'] =  $this->bo->acl->get_cats(PHPGW_ACL_ADD);
		$sel_options['cat_id'] = count($sel_options['cat_id']) == 1 ? $sel_options['cat_id'] : array('' => lang('select one')) + $sel_options['cat_id'];
		if($content['accessory_of'] != -1)
		{
			$catofmaster = $this->bo->so->get_value('cat_id',$content['accessory_of']);
			$sel_options['cat_id'] = array($catofmaster => $sel_options['cat_id'][$catofmaster]);
		}
		
		$sel_options['gen_src_list'] = $this->bo->get_genpicturelist();
		
		$no_button = array(); // TODO: show delete button only if allowed to delete resource
		$preserv = $content;
		$this->tmpl->read('resources.edit');
		$this->tmpl->exec('resources.ui_resources.edit',$content,$sel_options,$no_button,$preserv);
		
	}
	
	/*!
		@function show
		@abstract showes a single resource
		@param int $id resource id
	*/
	function show($id)
	{
	
	}

	function delete($id)
	{
 		$this->bo->delete($id);
		return $this->index();
	}
}
