<?php
	/**************************************************************************\
	* phpGroupWare - Preferences                                               *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
/* $Id$ */
	class uipreferences
	{
		function uipreferences()
		{
			$templates = $GLOBALS['phpgw']->common->list_templates();
			while (list($var,$value) = each($templates))
			{
				$_templates[$var] = $templates[$var]['title'];
			}
		
			$themes = $GLOBALS['phpgw']->common->list_themes();
			while (list(,$value) = each($themes))
			{
				$_themes[$value] = $value;
			}
	
			$this->bo = CreateObject('filemanager.bofilemanager');
			$this->pref_type = $GLOBALS['type'];
		}

		function display_item($label_name, $preference_name, $s)
		{
			global $t;
	
			$_appname = check_app();
			if (is_forced_value($_appname,$preference_name))
			{
				return True;
			}
	
			$GLOBALS['phpgw']->nextmatchs->template_alternate_row_color($t);
	
			$t->set_var('row_name',$label_name);
	
			switch ($GLOBALS['type'])
			{
				case 'user':
						$t->set_var('row_value',$s );
					break;
				case 'default':
					$t->set_var('row_value', $s );
					break;
				case 'forced':
					$t->set_var('row_value',  $s);
					break;
			}
	
			$t->fp('rows','row',True);
		}
		
		function index()
		{
			$phpgw_info = $GLOBALS['phpgw_info'];
			echo '<b>Current Preferences</b> ';
			//print_r($phpgw_info[$this->pref_type]['preferences']['filemanager']);
			
			/*
			   To add an on/off preference, just add it here.  Key is internal name, value is displayed name
			*/
			$other_checkboxes = array ("viewinnewwin" => lang("View documents in new window"), "viewonserver" => lang("View documents on server (if available)"), "viewtextplain" => lang("Unknown MIME-type defaults to text/plain when viewing"), "dotdot" => lang("Show .."), "dotfiles" => lang("Show .files"), "show_help" => lang("Show help"), "show_command_line" => lang("Show command line (EXPERIMENTAL. DANGEROUS.)"));
		
			/*
			   To add a dropdown preferences, add it here.  Key is internal name, value key is
			   displayed name, value values are choices in the dropdown
			*/
			//$other_dropdown = array ("show_upload_boxes" => array (, "5", "10", "20", "30"));
		
			create_select_box(lang("Default number of upload fields to show"), 'show_upload_boxes', array ("5"=>"5", "10"=>'10', "20"=>'20', "30"=>'30'));		
			$this->display_item('<b>'.lang('File attributes to display:').'</b>','','');
			while (list ($internal, $displayed) = each ($this->bo->file_attributes))
			{
				unset ($checked);
				
				if ($phpgw_info[$this->pref_type]['preferences']['filemanager'][$internal])
				{
					$checked = '1';
					$extra = 'checked';
				}
				else
				{
					$checked = '0';
					$extra = '';
				}
				$str = '<input type="checkbox" name="'.$this->pref_type.'['. $internal .']" value="'. $checked.'" '.$extra.' />';
				//$this->display_item (lang($displayed), $internal, $str);		
				create_select_box(lang("$displayed"), $internal, array ("1"=>"yes", "0"=>'no'));			
			}
			$this->display_item ('<hr />','','');
			
			reset ($other_checkboxes);
				while (list ($internal, $displayed) = each ($other_checkboxes))
			{
				unset ($checked);
				if ($phpgw_info[$this->pref_type]['preferences']['filemanager'][$internal])
				{
					$checked = 1;
					$extra = 'checked';
				}
				else
				{
					$checked = 0;
					$extra = '';
				}
		
				$str = '<input type="checkbox" name="'.$this->pref_type.'['. $internal .']" value="'. $checked.'" />';
				$this->display_item ($displayed, $internal, $str);
			}
		}	
	}

	$a = new uipreferences();
	$a->index();

