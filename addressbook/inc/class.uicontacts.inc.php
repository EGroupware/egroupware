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
	* Edit a contact 
	*
	* @param array $content=null submitted content
	* @param int $_GET['contact_id'] contact_id manly for popup use
	* @param bool $_GET['makecp'] ture if you want do copy the contact given by $_GET['contact_id']
	*/
	function edit($content=null)
	{
		if (!is_object($this->link))
		{
			if (!is_object($GLOBALS['egw']->link))
			{
				$GLOBALS['egw']->link =& CreateObject('phpgwapi.bolink');
			}
			$this->link =& $GLOBALS['egw']->link;
		}
		if (is_array($content))
		{
			list($button) = @each($content['button']);
			switch($button)
			{
				case 'save':
				case 'apply':
					$links = false;
					if (!$content['id'] && is_array($content['link_to']['to_id']))
					{
						$links = $content['link_to']['to_id'];
					}
					$content = $this->save($content);
					// writing links for new entry, existing ones are handled by the widget itself
					if ($links && $content['id'])	
					{
						$this->link->link('addressbook',$content['id'],$links);
					}
					if ($button == 'save')
					{
						echo "<html><body><script>var referer = opener.location;opener.location.href = referer;window.close();</script></body></html>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					$content['link_to']['to_id'] = $content['id'];
					$GLOBALS['egw_info']['flags']['java_script'] .= "<script LANGUAGE=\"JavaScript\">
						var referer = opener.location;
						opener.location.href = referer;</script>";
					break;
					
				case 'delete':
					if(!$this->delete($content));
					{
						echo "<html><body><script>var referer = opener.location;opener.location.href = referer;window.close();</script></body></html>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					break;
			}
			// type change
		}
		else
		{
			$content = array();
			$contact_id = $_GET['contact_id'] ? $_GET['contact_id'] : 0;
			$view = $_GET['view'];
			
			if ($contact_id)
			{
				$content = $this->read($contact_id);
			}
			else // look if we have presets for a new contact
			{
				$new_type = array_keys($this->content_types);
				$content['tid'] = $_GET['typeid'] ? $_GET['typeid'] : $new_type[0];
				foreach($this->get_contact_conlumns() as $field => $data)
				{
					if ($_GET['presets'][$field]) $content[$field] = $_GET['presets'][$field];
				}
			}
			
			if($content && $_GET['makecp'])	// copy the contact
			{
				$content['link_to']['to_id'] = 0;
				$this->link->link('addressbook',$content['link_to']['to_id'],'addressbook',$content['id'],
					lang('Copied by %1, from record #%2.',$GLOBALS['egw']->common->display_fullname('',
					$GLOBALS['egw_info']['user']['account_firstname'],$GLOBALS['egw_info']['user']['account_lastname']),
					$content['id']));
				unset($content['id']);
			}
			else
			{
				$content['link_to']['to_id'] = (int) $contact_id;
			}
		}

		//_debug_array($content);
		$readonlys['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[copy]'] = $readonlys['button[edit]'] = $readonlys['button[vcard]'] = true;

		$preserv = array(
			'id' => $content['id'],
			'lid' => $content['lid'],
			'owner' => $content['owner'],
			'fn' => $content['fn'],
			'geo' => $content['geo'],
			'access' => $content['access'],
		);
		
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];
		foreach($GLOBALS['egw']->acl->get_all_location_rights($GLOBALS['egw']->acl->account_id,'addressbook',true) as $id => $right)
		{
			if($id < 0) $sel_options['published_groups'][$id] = $GLOBALS['egw']->accounts->id2name($id);
		}
		$content['typegfx'] = $GLOBALS['egw']->html->image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['link_to']['to_id'],
		);
		$this->tmpl->read($this->content_types[$content['tid']]['options']['template']);
		return $this->tmpl->exec('addressbook.uicontacts.edit',$content,$sel_options,$readonlys,$preserv, 2);
	}
	
	function view($content=null)
	{
		if(is_array($content))
		{
			list($button) = each($content['button']);
			switch ($button)
			{
				case 'vcard':
					$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uivcard.out&ab_id=' .$content['id']);

				case 'cancel':
					$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.uiaddressbook.index');

				case 'delete':
					if(!$this->delete($content))
					{
						$content['msg'] = lang('Something went wrong by deleting this contact');
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
		foreach((array)$content as $key => $val)
		{
			$readonlys[$key] = true;
			if (in_array($key,array('tel_home','tel_work','tel_cell')))
			{
				$readonlys[$key.'2'] = true;
				$content[$key.'2'] = $content[$key];
			}				
		}
		$content['view'] = true;
		$content['link_to'] = array(
			'to_app' => 'addressbook',
			'to_id'  => $content['id'],
		);
		$readonlys['link_to'] = $readonlys['customfields'] = true;
		$readonlys['button[save]'] = $readonlys['button[apply]'] = true;
		$readonlys['button[delete]'] = !$this->check_perms(EGW_ACL_DELETE,$content);
		$readonlys['button[edit]'] = !$this->check_perms(EGW_ACL_EDIT,$content);
		
		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;

		for($i = -23; $i<=23; $i++) $tz[$i] = ($i > 0 ? '+' : '').$i;
		$sel_options['tz'] = $tz;
		$content['tz'] = $content['tz'] ? $content['tz'] : 0;
		foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];
		foreach(explode(',',$content['published_groups']) as $id)
		{
			$sel_options['published_groups'][$id] = $GLOBALS['egw']->accounts->id2name($id);
		}
		$content['typegfx'] = $GLOBALS['egw']->html->image('addressbook',$this->content_types[$content['tid']]['options']['icon'],'',' width="16px" height="16px"');
		$this->tmpl->read($this->content_types[$content['tid']]['options']['template']);
		foreach(array('email','email_home','url') as $name)
		{
			if ($content[$name] )
			{
				$url = $name == 'url' ? $content[$name] : $this->email2link($content[$name]);
				if (!is_array($url))
				{
					$this->tmpl->set_cell_attribute($name,'size','b,,1');
				}
				elseif ($url)
				{
					$content[$name.'_link'] = $url;
					$this->tmpl->set_cell_attribute($name,'size','b,@'.$name.'_link,,,_blank');
				}
				$this->tmpl->set_cell_attribute($name,'type','label');
				$this->tmpl->set_cell_attribute($name,'no_lang',true);
			}
		}
		$this->tmpl->exec('addressbook.uicontacts.view',$content,$sel_options,$readonlys,array('id' => $content['id']));
		
		$GLOBALS['egw']->hooks->process(array(
			'location' => 'addressbook_view',
			'ab_id'    => $content['id']
		));
	}
	
	/**
	 * convert email-address in compose link
	 *
	 * @param string $email email-addresse
	 * @return array/string array with get-params or mailto:$email, or '' or no mail addresse
	 */
	function email2link($email)
	{
		if (!strstr($email,'@')) return '';

		if($GLOBALS['egw_info']['user']['apps']['felamimail'])
		{
			return array(
				'menuaction' => 'felamimail.uicompose.compose',
				'send_to'    => base64_encode($email)
			);
		}
		if($GLOBALS['egw_info']['user']['apps']['email'])
		{
			return array(
				'menuaction' => 'email.uicompose.compose',
				'to' => $email,
			);
		}
		return 'mailto:' . $email;
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
			'view' => array(
				'type' => 'button',
				'options' => array(
					'size' => 'view',
					'onclick' => "location.href='".$GLOBALS['egw']->link('/index.php',
					array('menuaction' => 'addressbook.uicontacts.view',)).'&contact_id=$row_cont[id]\';return false;',
			)),
			'edit' => array(
				'type' => 'button',
				'options' => array(
					'size' => 'edit',
					'onclick' => 'window.open(\''.
					$GLOBALS['egw']->link('/index.php?menuaction=addressbook.uicontacts.edit').
					'&contact_id=$row_cont[id] \',\'\',\'dependent=yes,width=850,height=440,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes\');
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
		$sel_options['tid'][] = lang('all');
		//foreach($this->content_types as $type => $data) $sel_options['tid'][$type] = $data['name'];
		$this->tmpl->read('addressbook.search');
		return $this->tmpl->exec('addressbook.uicontacts.search',$content,$sel_options,$readonlys,$preserv);
	}
	
	function js()
	{
		return '<script LANGUAGE="JavaScript">
		
		function showphones(form) 
		{
			set_style_by_class("table","editphones","display","inline");
			if (form) {
				copyvalues(form,"tel_home","tel_home2");
				copyvalues(form,"tel_work","tel_work2");
				copyvalues(form,"tel_cell","tel_cell2");
			}
		}
		
		function hidephones(form) 
		{
			set_style_by_class("table","editphones","display","none");
			if (form) {
				copyvalues(form,"tel_home2","tel_home");
				copyvalues(form,"tel_work2","tel_work");
				copyvalues(form,"tel_cell2","tel_cell");
			}
		}
		
		function copyvalues(form,src,dst){
			var srcelement = getElement(form,src);  //ById("exec["+src+"]");
			var dstelement = getElement(form,dst);  //ById("exec["+dst+"]");
			if (srcelement && dstelement) {
				dstelement.value = srcelement.value;
			}
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
