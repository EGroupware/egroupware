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

		var $output;
		var $data = array();

		// Textual variables
		var $title;

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

		/*
		This is the constructor for the object.
		*/
		function portalbox($title = '')
		{
			$this->setvar('title',$title);

			// echo 'After SetVar Title = '.$this->getvar('title')."<br>\n";
		}

		function start_template($extra = '')
		{
			if ($extra && $this->getvar('app_name'))
			{
				$GLOBALS['phpgw']->xslttpl->add_file(array('portal',$GLOBALS['phpgw']->common->get_tpl_dir($this->getvar('app_name'),'default') . SEP . 'extrabox'));
			}
			else
			{
				$GLOBALS['phpgw']->xslttpl->add_file(array('portal'));
			}

			$this->output = array
			(
				'title'	=> $this->getvar('title'),
				'space'	=> '&nbsp;'
			);
		}

		function set_controls($control='',$control_param='')
		{
			//echo '<br>Control: ' . $control . ', control_param="' . $control_param . '"';

			if($control != '' && is_array($control_param))
			{
				$this->setvar($control,$GLOBALS['phpgw']->link($control_param['url'],'app='.$control_param['app'].'&control='.$control));
			}
		}

		function set_internal($data='')
		{
			if($data=='' && !count($this->data))
			{
				$data = '';
			}
			$this->output['extrabox'] = $data;
		}

		function set_xinternal($data='')
		{
			if($data=='' && !count($this->data))
			{
				$data = '';
			}
			$this->output['xextrabox'] = $data;
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

				$this->output['control_link'] = $control_link;
			}
			$GLOBALS['phpgw']->xslttpl->set_var('portal',$this->output);
			return $GLOBALS['phpgw']->xslttpl->parse();
		}
	}
