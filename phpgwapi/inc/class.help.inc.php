<?php
	/*******************************************************************\
	* phpGroupWare API - help system manager                            *
	* Written by Bettina Gille [ceb@phpgroupware.org]                   *
	* Manager for the phpGroupWare help system                          *
	* Copyright (C) 2002  Bettina Gille                                 *
	* ----------------------------------------------------------------- *
	* This library is part of the phpGroupWare API                      *
	* http://www.phpgroupware.org/theapi                                * 
	* ----------------------------------------------------------------- *
	* This library is free software; you can redistribute it and/or     *
	* modify it under the terms of the GNU General Public License as    *
	* published by the Free Software Foundation; either version 2 of    *
	* the License, or (at your option) any later version.               *
	*                                                                   *
	* This program is distributed in the hope that it will be useful,   *
	* but WITHOUT ANY WARRANTY; without even the implied warranty of    *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU  *
	* General Public License for more details.                          *
	*                                                                   *
	* You should have received a copy of the GNU General Public License *
	* along with this program; if not, write to the Free Software       *
	* Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.         *
	\*******************************************************************/
	/* $Id$ */

	class help
	{
		var $lang;
		var $app_name;
		var $app_version;
		var $app_id;
		var $up;
		var $down;
		var $intro;
		var $app_intro;
		var $note;

		var $extrabox;
		var $xhelp;
		var $listbox;

		var $output;
		var $data;

		var $title;

		/* This is the constructor for the object. */

		function help($reset = False)
		{
			$this->lang			= $GLOBALS['phpgw_info']['user']['preferences']['common']['lang'];
			$this->title		= '';
			$this->app_name		= '';
			$this->app_version	= '';
			$this->app_id		= 0;

			$this->up			= '';
			$this->down			= '';
			$this->intro		= '';
			$this->app_intro	= '';
			$this->note			= '';

			$this->extrabox		= '';
			$this->xhelp		= '';
			$this->listbox		= '';
			$this->data			= array();

			if (!$reset)
			{
				$this->output = array();
			}
			$GLOBALS['phpgw']->xslttpl->add_file($GLOBALS['phpgw']->common->get_tpl_dir('phpgwapi','default') . SEP . 'help');
		}

		/*
		Use these functions to get and set the values of this
		object's variables. This is good OO practice, as it means
		that datatype checking can be completed and errors raised accordingly.
		*/
		function setvar($var,$value='')
		{
			if ($value=='')
			{
				global $$var;
				$value = $$var;
			}
			$this->$var = $value;
			// echo $var." = ".$this->$var."<br>\n";
		}

		function getvar($var='')
		{
			if ($var=='' || !isset($this->$var))
			{
				echo 'Programming Error: '.$this->getvar('classname').'->getvar('.$var.')!<br>'."\n";
				$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
				exit;
			}
			//echo "Var = ".$var."<br>\n";
			//echo $var." = ".$this->$var."<br>\n";
			return $this->$var;
		}

		function start_template()
		{
			if ($this->app_name)
			{
				$GLOBALS['phpgw']->xslttpl->add_file($GLOBALS['phpgw']->common->get_tpl_dir($this->app_name,'default') . SEP . 'help_data');
			}
		}

		function set_controls($type = 'app', $control='', $control_url='')
		{
			switch($type)
			{
				case 'app':
					if($control != '' && $control_url != '')
					{
						$this->setvar($control,$this->check_help_file($control_url));
					}
					break;
				default:
					$this->setvar('intro',$GLOBALS['phpgw']->link('/help.php'));
					$this->setvar('note',$GLOBALS['phpgw']->link('/help.php','note=True'));
					break;
			}
		}

		function set_internal($extra_data = '')
		{
			if($extra_data !='')
			{
				$this->extrabox = $extra_data;
			}
		}

		function set_xinternal($extra_data='')
		{
			if($extra_data !='')
			{
				$this->xhelp = $extra_data;
			}
		}

		function draw_box()
		{
			$control_array = array
			(
				'intro'		=> True
			);

			if($this->app_intro)
			{
				$control_array['app_intro'] = True;
			}
			if($this->up)
			{
				$control_array['up'] = True;
			}
			if($this->down)
			{
				$control_array['down'] = True;
			}
			$control_array['note'] = True;

			//_debug_array($control_array);

			@reset($control_array);
			while(list($param,$value) = each($control_array))
			{
				if(isset($this->$param) && $this->$param)
				{
					$image_width = 15;

					$control_link[] = array
					(
						'param_url' 		=> $this->$param,
						'link_img'			=> $GLOBALS['phpgw']->common->image('phpgwapi',$param.'_help'),
						'img_width'			=> $image_width,
						'lang_param_title'	=> lang($param)
					);
				}
			}

			$this->output['help_values'][] = array
			(
				'img'			=> $GLOBALS['phpgw']->common->image($this->app_name,'navbar','',True),
				'title'			=> $this->title,
				'lang_version'	=> lang('version'),
				'version'		=> $this->app_version,
				'control_link' 	=> $control_link,
				'listbox'		=> $this->listbox,
				'extrabox'		=> $this->extrabox,
				'xhelp'			=> $this->xhelp
			);
		}

		function check_file($file)
		{
			$check_file = PHPGW_SERVER_ROOT . $file;

			if(@is_file($check_file))
			{
				return $file;
			}
			else
			{
				return '';
			}
		}

		function check_help_file($file)
		{
			$lang = strtoupper($this->lang);

			$help_file = $this->check_file('/' . $this->app_name . '/help/'. $lang . '/' . $file);

			if($help_file == '')
			{
				$help_file = $this->check_file('/' . $this->app_name . '/help/EN/' . $file);
			}

			if ($help_file)
			{
				return $GLOBALS['phpgw']->link($help_file);
			}

			return False;
		}

		/*function display_manual_section($appname,$file)
		{
			$font = $GLOBALS['phpgw_info']['theme']['font'];
		$navbar = $GLOBALS['phpgw_info']['user']['preferences']['common']['navbar_format'];
		$lang = strtoupper($GLOBALS['phpgw_info']['user']['preferences']['common']['lang']);
		$GLOBALS['treemenu'][] = '..'.($navbar != 'text'?'<img src="'.$GLOBALS['phpgw']->common->image($appname,'navbar').'" border="0" alt="'.ucwords($appname).'">':'').($navbar != 'icons'?'<font face="'.$font.'">'.lang($appname).'</font>':'').'|'.$GLOBALS['phpgw']->link('/'.$appname.'/help/index.php');

		$help_file = check_help_file($appname,$lang,$appname.'.php');
		if($help_file != '')
		{
			$GLOBALS['treemenu'][] = '...<font face="'.$font.'">'.lang('Overview').'</font>|'.$GLOBALS['phpgw']->link($help_file);
		}
		while(list($title,$filename) = each($file))
		{
			$help_file = check_help_file($appname,$lang,$filename);
			if($help_file != '')
			{
				$GLOBALS['treemenu'][] = '...<font face="'.$font.'">'.lang($title).'</font>|'.$GLOBALS['phpgw']->link($help_file);
			}
		}
	}

	function show_menu($expandlevels)
	{
		$menutree = CreateObject('phpgwapi.menutree','text');
		$menutree->set_lcs(300);

		$str  = '<table cellpadding="10" width="20%"><td>';
		$str .= '<font face="'.$GLOBALS['phpgw_info']['theme']['font'].'" size="2">';
		$str .= 'Note: Some of this information is out of date<br>';

		$GLOBALS['treemenu'] = Array();

		$GLOBALS['phpgw']->hooks->process('manual',array('manual','preferences'));

		reset($GLOBALS['treemenu']);

		$str .= $menutree->showtree($GLOBALS['treemenu'],$expandlevels).'</td></table>';

		return $str;
	}*/
	}
?>
