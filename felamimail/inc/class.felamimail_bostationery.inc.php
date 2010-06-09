<?php
/**
 * eGroupWare - Felamimail stationery
 *
 * @link http://www.egroupware.org
 * @package felamimail
 * @author Christian Binder <christian@jaytraxx.de>
 * @copyright (c) 2009 by christian@jaytraxx.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.felamimail_bostationery.inc.php 27660 2009-08-17 14:45:42Z jaytraxx $
 */

class felamimail_bostationery
{
	/**
	 * application stationery is working for
	 *
	 */
	const _appname = 'felamimail';
	
	/**
	 * prefix for the etemplate stationery templates
	 *
	 */
	const _stationery_prefix = 'felamimail.stationery';
	
	/**
	 * etemplate object
	 * @var object
	 */
	private $etemplate;
	
	/**
	 * constructor of bostationery
	 *
	 */
	public function __construct()
	{
		$this->etemplate = new etemplate();
	}
	
	/*
	 * returns all active templates set in emailadmin profile
	 *
	 * @return array $index => $id pairs or empty array if no active templates found
	 */
	private function get_active_templates()
	{
		$boemailadmin = new emailadmin_bo();
		$profile_data = $boemailadmin->getUserProfile(self::_appname);
		$active_templates = $profile_data->ea_stationery_active_templates;
			
		return $active_templates;
	}
	
	/*
	 * returns all stored etemplate templates
	 *
	 * @return array $id => $description pairs or empty array if no stored templates found
	 */
	public function get_stored_templates()
	{
		// ensure that templates are actually loaded into the database
		$this->etemplate->test_import(self::_appname);
		
		$templates = $this->etemplate->search(self::_stationery_prefix);
		$stored_templates = array();
				
		if(is_array($templates) && count($templates) > 0)
		{
			foreach($templates as $template)
			{
				list(,,$template_description) = explode('.',$template['name']);
				$stored_templates[$template['name']] = $template_description;
			}
		}
			
		return $stored_templates;
	}
	
	/*
	 * returns all valid templates
	 * a valid template is a template that is set active in emailadmin
	 * AND exists (is stored) in etemplate
	 *
	 * @return array $id => $description pairs or empty array if no valid templates found
	 */
	public function get_valid_templates()
	{
		$active_templates = $this->get_active_templates();
		$stored_templates = $this->get_stored_templates();
		
		$valid_templates = array();
		foreach((array)$active_templates as $index => $id)
		{
			if(isset($stored_templates[$id]))
			{
				$valid_templates[$id] = $stored_templates[$id];
			}
		}
			
		return $valid_templates;
	}
	
	/*
	 * renders the mail body with a given stationery template
	 *
	 * @param string $_id the stationery id to render
	 * @param string $_message the mail message
	 * @param string $_signature='' the mail signature
	 *
	 * @return string complete html rendered mail body
	 */
	function render($_id,$_message,$_signature='')
	{
		$content = array();
		$content['message'] = $_message;
		$content['signature'] = $_signature;
		
		$this->etemplate->read($_id);
		$mail_body = $this->etemplate->exec(false, $content, false, false, false, 3);
		
		return $mail_body;
	}
}
