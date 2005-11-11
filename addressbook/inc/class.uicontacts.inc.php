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
 * @package addressbook
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @copyright (c) 2005 by Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class uicontacts extends bocontacts
{
	var $public_functions = array(
		'search'	=> True,
		'edit'		=> True,
		'view'		=> True,
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
	
	/**
	*
	* @param int $_GET['contact_id'] contact_id manly for popup use
	* @param bool $_GET['makecp'] ture if you want do copy the contact given by $_GET['contact_id']
	*/
	function edit($content='')
	{
		if (is_array($content))
		{
			if (isset($content['button']['save']))
			{
				$this->save($content);
				echo "<html><body><script>var referer = opener.location;opener.location.href = referer;window.close();</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
			elseif (isset($content['button']['apply']))
			{
				$content = $this->save($content);
				$GLOBALS['egw_info']['flags']['java_script'] .= "<script LANGUAGE=\"JavaScript\">
					var referer = opener.location;
					opener.location.href = referer;</script>";
			}
			elseif (isset($content['button']['delete']))
			{
				if(!$this->delete($content));
				{
				echo "<html><body><script>var referer = opener.location;opener.location.href = referer;window.close();</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
				}
			}
		}
		else
		{
			$content = array();
			$content_id = $_GET['contact_id'] ? $_GET['contact_id'] : 0;
			$view = $_GET['view'];// == 1 ? true : false;
			
			if ($content_id != 0)
			{
				$content = $this->read($content_id);
			}
			if($_GET['makecp']) unset($content['id']);
		}

		//_debug_array($content);
		$readonlys['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[copy]'] = true;
		$readonlys['button[edit]'] = true;

		$preserv = array(
			'id' => $content['id'],
			'lid' => $content['lid'],
			'tid' => $content['tid'],
			'owner' => $content['owner'],
			'fn' => $content['fn'],
			'geo' => $content['geo'],
			'access' => $content['access'],
		);
		
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		
		$this->tmpl->read('addressbook.edit');
		return $this->tmpl->exec('addressbook.uicontacts.edit',$content,$sel_options,$readonlys,$preserv, 2);
	}
	
	function view($content='')
	{
		if(is_array($content))
		{
			if (isset($content['button']['vcard']))
			{
				$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uivcard.out&ab_id=' .$content['id']);
			}
			elseif (isset($content['button']['cancel']))
			{
				$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uiaddressbook.index');
			}
			elseif (isset($content['button']['delete']))
			{
				if(!$this->delete($content))
				{
					$content['msg'] = lang('Something wen\'t wrong by deleting this contact');
				}
				else
				{
					$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uiaddressbook.index');
				}
			}
	
		}
		else
		{
			$contact_id = $_GET['contact_id'];
			if(!$contact_id) return false;

			$content = $this->read($contact_id);
		}
		$content['view'] = true;
		
		// privat
		foreach(array(
			'adr_two_street'      => 'home street',
			'adr_two_locality'    => 'home city',
			'adr_two_region'      => 'home state',
			'adr_two_postalcode'  => 'home zip code',
			'adr_two_countryname' => 'home country',
			'adr_two_type'        => 'home address type',
		 	) as $field => $name)
		{
			if($content[$field] == '') continue;
			$content['personal_entries'][] = array(
				'field' => $name,
				'value' => $content[$field],
			);
		}
		// tel numbers
		foreach(array(
			'tel_work'            => 'business phone',
			'tel_home'            => 'home phone',
			'tel_voice'           => 'voice phone',
			'tel_msg'             => 'message phone',
			'tel_fax'             => 'fax',
			'tel_pager'           => 'pager',
			'tel_cell'            => 'mobile phone',
			'tel_bbs'             => 'bbs phone',
			'tel_modem'           => 'modem phone',
			'tel_isdn'            => 'isdn phone',
			'tel_car'             => 'car phone',
			'tel_video'           => 'video phone',
			'ophone'              => 'other phone',
			'tel_prefer'          => 'preferred phone',

			) as $field => $name)
		{
			if($content[$field] == '') continue;
			$content['phone_entries'][] = array(
				'field' => $name,
				'value' => $content[$field],
			);
		}
		// organisation
		foreach(array(
			'adr_one_street'      => 'business street',
			'address2'            => 'address line 2',
			'address3'            => 'address line 3',
			'adr_one_locality'    => 'business city',
			'adr_one_region'      => 'business state',
			'adr_one_postalcode'  => 'business zip code',
			'adr_one_countryname' => 'business country',
			'adr_one_type'        => 'business address type',
			) as $field => $name)
		{
			if($content[$field] == '') continue;
			$content['organisation_entries'][] = array(
				'field' => $name,
				'value' => $content[$field],
			);
		}
		// emails
		foreach(array(
			'email'               => 'business email',
			'email_home'          => 'home email',
			) as $field => $name)
		{
			if($content[$field] == '') continue;
			$content['email_entries'][] = array(
				'field' => $name,
				'value' => $content[$field],
			);
		}
		//urls
		foreach(array(
			'url'                 => 'url'
			) as $field => $name)
		{
			if($content[$field] == '') continue;
			$content['url_entries'][] = array(
				'field' => $name,
				'value' => $content[$field],
			);
		}
		if($content['tz'] == '') $content['tz'] = 0;
		
		$readonlys['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[private]'] = $content['private'] == 1 ? false :true;
		$this->tmpl->read('addressbook.view');
		$this->tmpl->exec('addressbook.uicontacts.view',$content,$sel_options,$readonlys,array('id' => $content['id']));
		
		$GLOBALS['egw']->hooks->process(array(
			'location' => 'addressbook_view',
			'ab_id'    => $content['id']
		));
	}
	
	function search($content='')
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook'). ' - '. lang('Advanced search');
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
			'email' => lang('work email'),
			'tel_home' => lang('tel home'),
		);
		
		$content['advs']['row_actions'] = array(
			'edit' => array(
				'type' => 'button',
				'options' => array(
					'size' => 'edit',
					'onclick' => 'window.open(\''.
					$GLOBALS['egw']->link('/index.php?menuaction=addressbook.uicontacts.edit').
					'&contact_id=$row_cont[id] \',\'\',\'dependent=yes,width=800,height=600,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\');
					return false;',
				)),
			'delete' => array(
				'type' => 'button',
				'method' => 'addressbook.bocontacts.delete',
				'options' => array(
					'size' => 'delete',
					'onclick' => 'if(!confirm(\''. lang('Do your really want to delete this contact?'). '\')) return false;',
				)),
		);
/*		$content['advs']['actions']['email'] = array(
				'type' => 'button',
				'options' => array(
					'label' => lang('email'),
					'no_lang' => true,
		));
		$content['advs']['actions']['export'] = array(
				'type' => 'button',
				'options' => array(
					'label' => lang('export'),
					'no_lang' => true,
		));*/
		$content['advs']['actions']['delete'] = array(
				'type' => 'button',
				'method' => 'addressbook.bocontacts.delete',
				'options' => array(
					'label'  => lang('delete'),
					'no_lang' => true,
					'onclick' => 'if(!confirm(\''. lang('WARNING: All contacts found will be deleted!'). '\')) return false;',
		));
		
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz + array('' => lang('doesn\'t matter'));
		
		$this->tmpl->read('addressbook.search');
		return $this->tmpl->exec('addressbook.uicontacts.search',$content,$sel_options,$readonlys,$preserv);
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
