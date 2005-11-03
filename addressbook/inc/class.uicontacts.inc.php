<?php
/**************************************************************************\
* eGroupWare - Adressbook - General user interface object                  *
* http://www.egroupware.org                                                *
* Written and (c) 2005 by Cornelius_weiss <egw@von-und-zu-weiss.de>        *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.bocontacts.inc.php');

/**
 * General user interface object of the adressbook
 *
 * @package adressbook
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @copyright (c) 2005 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uicontacts extends bocontacts
{
	var $public_functions = array(
		'search'	=> True,
		'edit'		=> True,
	);

	function uicontacts($contact_app='addressbook')
	{
		$this->bocontacts($contact_app);
		foreach(array(
			'tmpl'    => 'etemplate.etemplate',
		) as $my => $app_class)
		{
			list(,$class) = explode('.',$app_class);

			if (!is_object($GLOBALS['egw']->$class))
			{
				$GLOBALS['egw']->$class =& CreateObject($app_class);
			}
			$this->$my = &$GLOBALS['egw']->$class;
		}
		// our javascript
		// to be moved in a seperate file if rewrite is over
		$GLOBALS['egw_info']['flags']['java_script'] .= $this->js();

	}
	
	function edit($content='')
	{
		if (is_array($content))
		{
			if (isset($content['button']['save']))
			{
				$this->save($content);
				$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',
					array('menuaction' => 'addressbook.uiaddressbook.index'))."';";
				$js .= 'window.close();';
				echo "<html><body><script>$js</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
			elseif (isset($content['button']['apply']))
			{
				$content = $this->save($content);
				$GLOBALS['egw_info']['flags']['java_script'] .= "<script LANGUAGE=\"JavaScript\">opener.location.href='".
					$GLOBALS['egw']->link('/index.php',array('menuaction' => 'addressbook.uiaddressbook.index'))."';</script>";
			}
			elseif (isset($content['button']['delete']))
			{
				if(!$this->delete($content));
				{
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',
						array('menuaction' => 'addressbook.uiaddressbook.index'))."';";
					$js .= 'window.close();';
					echo "<html><body><script>$js</script></body></html>\n";
					$GLOBALS['egw']->common->egw_exit();
				}
			}
		}
		else
		{
			$content = array();
			$content_id = $_GET['contact_id'] ? $_GET['contact_id'] : 0;
			if ($content_id != 0)
			{
				$content = $this->read($content_id);
			}
		}
		
		//_debug_array($content);
		$no_button['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$no_button['button[copy]'] = true;
		$no_button['button[edit]'] = !$view;
		
		$preserv = array(
			'id' => $content['id'],
			'lid' => $content['lid'],
			'tid' => $content['tid'],
			'owner' => $content['owner'],
			'fn' => $content['fn'],
			'geo' => $content['geo'],
		);
		
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		
		$this->tmpl->read('addressbook.edit');
		return $this->tmpl->exec('addressbook.uicontacts.edit',$content,$sel_options,$no_button,$preserv,2);
	}
	
	function search($content='')
	{
		if(!($GLOBALS['egw_info']['server']['contact_repository'] == 'sql' || !isset($GLOBALS['egw_info']['server']['contact_repository'])))
		{
			$GLOBALS['egw']->common->phpgw_header();
			echo parse_navbar();
			echo '<p> Advanced Search is not supported for ldap storage yet. Sorry! </p>';
			$GLOBALS['egw']->common->egw_exit();
		}
		
		// This is no fun yet, as we dont have a sortorder in prefs now, AND as we are not able to sort within cf.
// 		$prefs = $GLOBALS['egw']->preferences->read_repository();
// 		foreach($prefs['addressbook'] as $key => $value)
// 		{
// 			if($value == 'addressbook_on') $content['advs']['colums_to_present'][$key] = lang($key);
// 		}
	
// 		echo 'addressbook.uicontacts.search->content:'; _debug_array($content);
		$content['advs']['hidebuttons'] = true;
		$content['advs']['input_template'] = 'addressbook.edit';
		$content['advs']['search_method'] = 'addressbook.bocontacts.search';
		$content['advs']['search_class_constructor'] = $contact_app;
		$content['advs']['colums_to_present'] = array(
			'id' => 'id',
			'n_given' => lang('first name'),
			'n_family' => lang('last name'),
			'email_home' => lang('home email'),
			'email_work' => lang('work email'),
			'tel_home' => lang('tel home'),
		);
		
		$content['advs']['actions'] = array(
// 			'email' => array(
// 				'type' => 'button',
// 				'options' => array(
// 					'label' => lang('email'),
// 					'no_lang' => true,
// 				)),
			'delete' => array(
				'type' => 'button',
				'method' => 'addressbook.bocontacts.delete',
				'options' => array(
					'label'  => lang('delete'),
					'no_lang' => true,
					'onclick' => 'if(!confirm(\''. lang('Do you really want to delte this contacts?'). '\')) return false;',
				)),
// 			'export' => array(
// 				'type' => 'button',
// 				'options' => array(
// 					'label' => lang('export'),
// 					'no_lang' => true,
// 				)),
		);

		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz + array('' => lang('doesn\'t matter'));
		
		$this->tmpl->read('addressbook.search');
		return $this->tmpl->exec('addressbook.uicontacts.search',$content,$sel_options,$no_button,$preserv);
	}
	
	function js()
	{
		return '<script LANGUAGE="JavaScript">
		
		function showphones(form){
			set_style_by_class("table","editphones","display","inline");
			copyvalues(form,"tel_home","tel_home2");
			copyvalues(form,"tel_work","tel_work2");
			copyvalues(form,"tel_cell","tel_cell2");
			return;
		}
		
		function hidephones(form){
			set_style_by_class("table","editphones","display","none");
			copyvalues(form,"tel_home2","tel_home");
			copyvalues(form,"tel_work2","tel_work");
			copyvalues(form,"tel_cell2","tel_cell");
			return;
		}
		
		function copyvalues(form,src,dst){
			var srcelement = getElement(form,src);  //ById("exec["+src+"]");
			var dstelement = getElement(form,dst);  //ById("exec["+dst+"]");
			dstelement.value = srcelement.value;
			return;
		}
		
		function getElement(form,pattern){
			for (i = 0; i < form.length; i++){
				if(form.elements[i].name){
					var found = form.elements[i].name.search(pattern);
					if (found != -1){
						return form.elements[i];
					}
				}
			}
		}
		
		</script>';
	}
}