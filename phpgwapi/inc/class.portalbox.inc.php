<?php
	/**************************************************************************\
	* phpGroupWare API - Portal Box manager                                    *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	* Helps manage the portal boxes for phpGroupWares main page                *
	* Copyright (C) 2000 - 2002  Joseph Engo                                   *
	* ------------------------------------------------------------------------ *
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/api                                          * 
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/
	/* $Id$ */

	class portalbox
	{
		//Set up the Object, reserving memory space for variables

		var $app_name;
		var $app_id;
		var $controls;
		var $up;
		var $down;
		var $close;
		var $question;
		var $edit;

		var $extrabox;
		var $xextrabox;
		var $listbox;

		var $output;
		var $data;

		// Textual variables
		var $title;

		/* This is the constructor for the object. */

		function portalbox()
		{
			$this->title = '';
			$this->app_name = '';
			$this->app_id = 0;

			$this->up = '';
			$this->down = '';
			$this->close = '';
			$this->question = '';
			$this->edit = '';

			$this->extrabox = '';
			$this->xextrabox = '';
			$this->listbox = '';

			$this->output;
			$this->data = array();
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

		function start_template($extra = '')
		{
			echo 'APPNAME: ' . $this->app_name;

			if ($extra && $this->app_name)
			{
				$GLOBALS['phpgw']->xslttpl->add_file(array('portal',$GLOBALS['phpgw']->common->get_tpl_dir($this->app_name,'default') . SEP . 'extrabox'));
			}
			else
			{
				$GLOBALS['phpgw']->xslttpl->add_file('portal');
			}
		}

		function set_controls($control='',$control_param='')
		{
			//echo '<br>Control: ' . $control . ', control_param="' . $control_param . '"';

			if($control != '' && is_array($control_param))
			{
				$this->setvar($control,$GLOBALS['phpgw']->link($control_param['url'],'app='.$control_param['app'].'&control='.$control));
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
				$this->xextrabox = $extra_data;
			}
		}

		function draw_box()
		{
			$control = '';
			if($this->up || $this->down || $this->close || $this->question || $this->edit)
			{
				$control_array = array
				(
					'up',
					'down',
					'question',
					'close',
					'edit'
				);
				@reset($control_array);
				while(list($key,$param) = each($control_array))
				{
					if(isset($this->$param) && $this->$param)
					{
						$image_width = 15;
						if($param == 'edit')
						{
							$image_width = 30;
						}

						$control_link[] = array
						(
							'param_url' 			=> $this->$param,
							'link_img'				=> $GLOBALS['phpgw']->common->image('phpgwapi',$param.'.button'),
							'img_width'				=> $image_width,
							'lang_param_statustext'	=> lang($param)
						);
					}
				}

				$this->output['portal_data'][] = array
				(
					'title'			=> $this->title,
					'control_link' 	=> $control_link,
					'listbox'		=> $this->listbox,
					'extrabox'		=> $this->extrabox,
					'xextrabox'		=> $this->xextrabox
				);

				for ($i=0;$i<count($this->output['portal_data']);$i++)
				{
					if ($this->output['portal_data'][$i]['listbox'] == '')
					{
						unset($this->output['portal_data'][$i]['listbox']);
					}
					if ($this->output['portal_data'][$i]['extrabox'] == '')
					{
						unset($this->output['portal_data'][$i]['extrabox']);
					}
					if ($this->output['portal_data'][$i]['xextrabox'] == '')
					{
						unset($this->output['portal_data'][$i]['xextrabox']);
					}
				}
			}
		}
	}
?>
