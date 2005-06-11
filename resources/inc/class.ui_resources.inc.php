<?php
	/**************************************************************************\
	* eGroupWare - resources - Resource Management System                      *
	* http://www.egroupware.org                                                *
	* Written by Lukas Weiss <wnz_gh05t@users.sourceforge.net> and             *
	*            Cornelius Weiss <egw@von-und-zu-weiss.de>                     *
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
		'select'	=> True,
		'admin'		=> True,
		'writeLangFile'	=> True
		);

	/**
	 * constructor of class ui_resources
	 *
	 */
	function ui_resources()
	{
// 		print_r($GLOBALS['egw_info']); die();
		$this->tmpl	=& CreateObject('etemplate.etemplate','resources.show');
		$this->bo	=& CreateObject('resources.bo_resources');
		
		if(!@is_object($GLOBALS['egw']->js))
		{
			$GLOBALS['egw']->js =& CreateObject('phpgwapi.javascript');
		}
	}

	/**
	 * main resources list.
	 *
	 * Cornelius Wei� <egw@von-und-zu-weiss.de>
	 * @param array $content content from eTemplate callback
	 * 
	 * FIXME don't translate cats in nextmach
	 */
	function index($content='')
	{
		if (is_array($content))
		{
			$sessiondata = $content['nm'];
			
			if (isset($content['nm']['rows']))
			{
				unset($sessiondata['rows']);
				$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);
				
				unset($content['nm']['rows']['checkbox']);
				switch (key($content['nm']['rows']))
				{
					case 'edit': // note: this is a popup dialog now
						list($id) = each($content['nm']['rows']['edit']);
						return $this->edit($id);
					case 'delete':
						list($id) = each($content['nm']['rows']['delete']);
						return $this->delete($id);
					case 'new_acc': // note: this is a popup dialog now
						list($id) = each($content['nm']['rows']['new_acc']);
						return $this->edit($id = 0, $accessory_of = $id);
					case 'view_acc':
						list($id) = each($content['nm']['rows']['view_acc']);
 						$sessiondata['view_accs_of'] = $id;
						$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);
						return $this->index();
					case 'view':
						list($id) = each($content['nm']['rows']['view']);
						return $this->show($id);
					case 'select': // note: this is a popup dialog
						return $this->select();
					case 'bookable':
					case 'buyable':
				}
			}
			if (isset($content['add'])) // note: this isn't used as add is a popup now!
			{
				$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);
				return $content['nm']['view_accs_of'] ? $this->edit(array('id' => 0, 'accessory_of' => $content['nm']['view_accs_of'])) : $this->edit(0);
			}
			if (isset($content['back']))
			{
				unset($sessiondata['view_accs_of']);
				$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);
				return $this->index();
			}
		}
		else
		{
			$content = array();
			$content['nm'] = $GLOBALS['egw']->session->appsession('session_data','resources_index_nm');
		}
		
		$content['nm']['get_rows'] 	= 'resources.bo_resources.get_rows';
		$content['nm']['no_filter'] 	= False;
		$content['nm']['filter_label']	= 'Category';
		$content['nm']['filter_help']	= lang('Select a category'); // is this used???
		$content['nm']['options-filter']= array('0'=>lang('all categories'))+(array)$this->bo->acl->get_cats(EGW_ACL_READ);
		$content['nm']['no_filter2']	= true;
		$content['nm']['filter_no_lang'] = true;
		$content['nm']['no_cat']	= true;
		
		// check if user is permitted to add resources
		if(!$this->bo->acl->get_cats(EGW_ACL_ADD))
		{
			$no_button['add'] = true;
		}
		$no_button['back'] = true;
		$no_button['add_sub'] = true;
		$GLOBALS['egw_info']['flags']['app_header'] = lang('resources');
		
		if($content['nm']['view_accs_of'])
		{
			$master = $this->bo->so->read($content['nm']['view_accs_of']);
			$content['view_accs_of'] = $content['nm']['view_accs_of'];
			$content['nm']['get_rows'] 	= 'resources.bo_resources.get_rows';
			$content['nm']['no_filter'] 	= true;
			$content['nm']['no_filter2'] 	= true;
			$no_button['back'] = false;
			$no_button['add'] = true;
			$no_button['add_sub'] = false;
			$GLOBALS['egw_info']['flags']['app_header'] = lang('resources') . ' - ' . lang('accessories of ') . $master['name'] .
				($master['short_description'] ? ' [' . $master['short_description'] . ']' : '');
		}
		$preserv = $content;
		$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$content['nm']);
		$this->tmpl->read('resources.show');
		$this->tmpl->exec('resources.ui_resources.index',$content,$sel_options,$no_button,$preserv);
	}

	/**
	 * @author Cornelius Wei� <egw@von-und-zu-weiss.de>
	 * invokes add or edit dialog for resources
	 *
	 * @param $content   Content from the eTemplate Exec call or id on inital call
	 */
	function edit($content=0,$accessory_of = -1)
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
				
				if($content['msg'])
				{
					return $this->edit($content);
				}
				$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',
					array('menuaction' => 'resources.ui_resources.index'))."';";
				$js .= 'window.close();';
				echo "<html><body><script>$js</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
			elseif($content['cancel'])
			{
				$js .= 'window.close();';
				echo "<html><body><script>$js</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
		}
		else
		{
			$id = $content;
			if (isset($_GET['id'])) $id = $_GET['id'];
			if (isset($_GET['accessory_of'])) $accessory_of = $_GET['accessory_of'];
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
		$content['quantity'] = $content['quantity'] ? $content['quantity'] : 1;
		$content['useable'] = $content['useable'] ? $content['useable'] : 1;
		
		$sel_options['gen_src_list'] = $this->bo->get_genpicturelist();
		$sel_options['cat_id'] =  $this->bo->acl->get_cats(EGW_ACL_ADD);
		$sel_options['cat_id'] = count($sel_options['cat_id']) == 1 ? $sel_options['cat_id'] : array('' => lang('select one')) + $sel_options['cat_id'];
		
		if($accessory_of > 0 || $content['accessory_of'] > 0)
		{
			$content['accessory_of'] = $content['accessory_of'] ? $content['accessory_of'] : $accessory_of;
			$catofmaster = $this->bo->so->get_value('cat_id',$content['accessory_of']);
			$sel_options['cat_id'] = array($catofmaster => $sel_options['cat_id'][$catofmaster]);
		}
		
		$content['general|page|pictures|links|calendar'] = 'resources.edit_tabs.page';  //debug
		$no_button = array(); // TODO: show delete button only if allowed to delete resource
		$preserv = $content;
		$this->tmpl->read('resources.edit');
		$this->tmpl->exec('resources.ui_resources.edit',$content,$sel_options,$no_button,$preserv,2);
		
	}
	
	/**
	 * adminsection of resources
	 *
	 * @author Cornelius Wei� <egw@von-und-zu-weiss.de>
	 */
	function admin($content='')
	{
		if(is_array($content))
		{
			if(isset($content['save']))
			{
				$this->bo->conf->read_repository();
				$this->bo->conf->save_value('dont_use_vfs',$content['dont_use_vfs']);
			}
			else
			{
				return $GLOBALS['egw']->redirect_link('/admin/index.php');
			}
		}
		$content = $this->bo->conf->read_repository();
		$this->tmpl->read('resources.admin');
		$this->tmpl->exec('resources.ui_resources.admin',$content,$sel_options,$no_button,$preserv);
	}

	/**
	 * showes a single resource
	 *
	 * @param int $id resource id
	 * @author Lukas Weiss <wnz.gh05t@users.sourceforge.net>
	 */
	function show($id=0)
	{
		if (isset($_GET['id'])) $id = $_GET['id'];

		$content = array('id' => $id);
		$content = $this->bo->read($id);
		$content['gen_src_list'] = strstr($content['picture_src'],'.') ? $content['picture_src'] : false;
		$content['picture_src'] = strstr($content['picture_src'],'.') ? 'gen_src' : $content['picture_src'];
		$content['link_to'] = array(
				'to_id' => $id,
						'to_app' => 'resources'
						);
	
		$content['resource_picture'] = $this->bo->get_picture($content['id'],$content['picture_src'],$size=true);
		$content['quantity'] = $content['quantity'] ? $content['quantity'] : 1;
		$content['useable'] = $content['useable'] ? $content['useable'] : 1;
		
		$content['quantity'] = ($content['useable'] == $content['quantity']) ? $content['quantity'] : $content['quantity'].' ('.lang('useable ').$content['useable'].')';
		
					//$sel_options['gen_src_list'] = $this->bo->get_genpicturelist();
				
		$content['cat_name'] =  $this->bo->acl->get_cat_name($content['cat_id']);
		$content['cat_admin'] = $this->bo->acl->get_cat_admin($content['cat_id']);
		
	/*	if($content['accessory_of'] > 0)
		{
			$catofmaster = $this->bo->so->get_value('cat_id',$content['accessory_of']);
			$sel_options['cat_id'] = array($catofmaster => $sel_options['cat_id'][$catofmaster]);
		} 
	*/
		$content['description'] = $content['long_description'] ? $content['long_description'] : $content['short_description'];
		$content['description'] = $content['description'] ? $content['description'] : lang('no description available');
		$sel_options = array();
		$no_button = array();
		$preserv = $content;
		//print_r($content);
		$this->tmpl->read('resources.showdetails');
		$this->tmpl->exec('resources.ui_resources.show',$content,$sel_options,$no_button,$preserv,2);
		
	}

	/**
	 * select resources
	 *
	 * @author Lukas Weiss <wnz.gh05t@users.sourceforge.net>
	 */
	function select($content='')
	{
		if($content['btn_close'])
		{
			echo "<html><body><script>window.close();</script></body></html>\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		
		if (isset($_GET['name'])) $name = $_GET['name'];
		$content=array('js_id' => $name);
		$content['js'] = "<script LANGUAGE=\"JavaScript\">
			window.focus();
		
			function addOption(id,label,value,multiple)
			{
				openerSelectBox = opener.document.getElementById(id);
		
				if (multiple && openerSelectBox) {
					select = '';
					for(i=0; i < openerSelectBox.length; i++) {
						with (openerSelectBox.options[i]) {
							if (selected || openerSelectBox.selectedIndex == i) {
								select += (value.slice(0,1)==',' ? '' : ',')+value;
							}
						}
					}
					select += (select ? ',' : '')+value;
					opener.addOption(id,label,value,0);
					opener.addOption(id,'multiple*',select,0);
				}
				else {
					opener.addOption(id,label,value,!multiple && openerSelectBox && openerSelectBox.selectedIndex < 0);
				}
				selectBox = document.getElementById('uiaccountsel_popup_selection');
				if (selectBox) {
					for (i=0; i < selectBox.length; i++) {
						if (selectBox.options[i].value == value) {
							selectBox.options[i].selected = true;
							break;
						}
					}
					if (i >= selectBox.length) {
						selectBox.options[selectBox.length] = new Option(label,value,false,true);
					}
				}
			}
		
			function removeSelectedOptions(id)
			{
				openerSelectBox = opener.document.getElementById(id);
				if (openerSelectBox == null) window.close();
				selectBox = document.getElementById('uiaccountsel_popup_selection');
				for (i=0; i < selectBox.length; i++) {
					if (selectBox.options[i].selected) {
						for (j=0; j < openerSelectBox.length; j++) {
							if (openerSelectBox[j].value == selectBox.options[i].value) {
								openerSelectBox.removeChild(openerSelectBox[j]);
							}
						}
						selectBox.options[i--] = null;
					}
				}
			}
		
			function copyOptions(id)
			{
				openerSelectBox = opener.document.getElementById(id);
				selectBox = document.getElementById('uiaccountsel_popup_selection');
				for (i=0; i < openerSelectBox.length; i++) {
					with (openerSelectBox.options[i]) {
						if (selected && value.slice(0,1) != ',') {
							selectBox.options[selectBox.length] =  new Option(text,value);
						}
					}
				}
			}
			
			function oneLineSubmit(id)
			{
				openerSelectBox = opener.document.getElementById(id);
		
				if (openerSelectBox) {
					if (openerSelectBox.selectedIndex >= 0) {
						selected = openerSelectBox.options[openerSelectBox.selectedIndex].value;
						if (selected.slice(0,1) == ',') selected = selected.slice(1);
						opener.addOption(id,'multiple*',selected,1);
					}
					else {
						for (i=0; i < openerSelectBox.length; i++) {
							with (openerSelectBox.options[i]) {
								if (selected) {
									opener.addOption(id,text,value,1);
									break;
								}
							}
						}	
					}
				}
				window.close();
			}</script>";
		
		$content['nm']['show_bookable'] = true;
		$content['nm']['get_rows'] 	= 'resources.bo_resources.get_rows';
		$content['nm']['no_filter'] 	= False;
		$content['nm']['filter_label']	= 'Category';
		$content['nm']['filter_help']	= lang('Select a category'); // is this used???
		$content['nm']['options-filter']= array('0'=>lang('all categories'))+(array)$this->bo->acl->get_cats(EGW_ACL_READ);
		$content['nm']['no_filter2']	= true;
		$content['nm']['filter_no_lang'] = true;
		$content['nm']['no_cat']	= true;
		
		$sel_options = array();
		$no_button = array();
		$preserv = $content;
		$this->tmpl->read('resources.resource_select');
		$this->tmpl->exec('resources.ui_resources.select',$content,$sel_options,$no_button,$preserv,2);
}

	/**
	 * deletes a resource
	 *
	 * @param int $id resource id
	 * @author Lukas Weiss <wnz.gh05t@users.sourceforge.net>
	 */
	function delete($id)
	{
 		$this->bo->delete($id);
		return $this->index();
	}
}
