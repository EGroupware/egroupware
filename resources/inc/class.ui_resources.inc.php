<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Lukas Weiss [ichLukas@gmx.net] and                            *
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
				if (isset($content['nm']['rows']['edit']))
				{ 
					list($id) = each($content['nm']['rows']['edit']);
					return $this->edit($id);
				}
				elseif (isset($content['nm']['rows']['delete']))
				{
					list($id) = each($content['nm']['rows']['delete']);
					return $this->delete($id);
				}
			}
			if (isset($content['add']))
			{
				return $this->edit(0);
			}
		}
		
		$content['nm']['get_rows'] 	= 'resources.bo_resources.get_rows';
		$content['nm']['no_filter'] 	= False;
		$content['nm']['filter_label']	= 'Category';
		$content['nm']['filter_help']	= 'Select a category'; // is this used???
		$content['nm']['options-filter']= array('0'=>'all categories')+(array)$this->bo->acl->get_cats(PHPGW_ACL_READ);
		$content['nm']['no_filter2']	= True;
		$content['nm']['no_cat']	= True;
		
		// check if user is permitted to add resources
		if(!$this->bo->acl->get_cats(PHPGW_ACL_ADD))
		{
			$no_button['add'] = true;
		}
		
		$this->tmpl->read('resources.show');
		$this->tmpl->exec('resources.ui_resources.index',$content,$sel_options,$no_button,$preserv);
	}

	/*!
		@function edit
		@abstract invokes add or edit dialog for resources
		@autor Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
		@param mixed $content int for resource_id to edit ( 0 for new ). array if callback from dialog.
		@param string $msg message to display on top of dialog
	*/
	function edit($content='',$msg='')
	{
		$sel_options = array('cat_id' => $this->bo->acl->get_cats(PHPGW_ACL_ADD));
		$no_button = array();
		
		if (is_array($content))
		{
			if(isset($content['delete']))
			{
				return $this->delete($content['id']);
			}
			if(isset($content['save']))
			{
				if(!$content['cat_id'] || !$content['name'])
				{
					$content['msg'] = 'You need to choose at least a name and a category!';
					$this->tmpl->read('resources.edit');
					$this->tmpl->exec('resources.ui_resources.edit',$content,$sel_options,$no_button);
					return;
				}
				
				$content['msg'] = $this->bo->save($content);
				if($content['msg'])
				{
					$this->tmpl->read('resources.edit');
					$this->tmpl->exec('resources.ui_resources.edit',$content,$sel_options,$no_button);
				}
			}
			return $this->index();
		}
		
		if ($content > 0)
		{
			$preserv = array('id' => $content);
			$content = $this->bo->read($content);
		}
		else
		{
			$content = array();
		}
		$content['resource_picture'] = $this->bo->get_picture($content['id'],$content['picture_src'],$size=true);
		$content['msg'] = $msg;
		$preserv = (array)$preserv + $content; // debug for eTemplate tabs don't know if really needed atm.
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
// 		Wollen sie Dieses bla bla wirklich lˆschen --> ja (Wie bekommt man mit eTemplate ein Javascript Dialog???)
 		$this->bo->delete($id);
		return $this->show();
	}
}
