<?php
	/*******************************************************************\
	* phpGroupWare API - help system manager                            *
	* Written by Bettina Gille [ceb@phpgroupware.org]                   *
	* Manager for the phpGroupWare help system                          *
	* Copyright (C) 2002, 2003 Bettina Gille                            *
	* ----------------------------------------------------------------- *
	* This library is part of the phpGroupWare API                      *
	* http://www.phpgroupware.org                                       * 
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

	require_once('class.help.inc.php');

	class help_helper extends help
	{
		function help_helper()
		{
			$this->help();
		}

		function set_params($param)
		{
			$this->help(True);
			@reset($param);
			while(list($key,$value) = each($param))
			{
				if($key != 'title')
				{
					//echo 'Setting '.$key.':'.$value."<br>\n";
					$this->setvar($key,$value);
				}
			}
			$this->title = $param['title'];

			if(is_array($param['controls']))
			{
				while(list($key,$value) = each($param['controls']))
				{
					$this->set_controls('app',$key,$value);
				}
				$this->set_controls('base');
			}
		}

		function draw($extra_data='')
		{
			if(is_array($this->data) && !empty($this->data))
			{
				for ($x = 0; $x < count($this->data); $x++)
				{
					$var[] = array
					(
						'text'					=> $this->data[$x]['text'],
						'link'					=> $this->data[$x]['link'],
						'lang_link_statustext'	=> $this->data[$x]['lang_link_statustext']
					);
				}
				$this->listbox = $var;
			}
			$this->set_internal($extra_data);
			$this->draw_box();
		}

		function xdraw($extra_data='')
		{
			if ($extra_data)
			{
				$this->start_template();
			}

			if(is_array($this->data) && !empty($this->data))
			{
				for ($x = 0; $x < count($this->data); $x++)
				{
					$var[] = array
					(
						'text'					=> $this->data[$x]['text'],
						'link'					=> $this->data[$x]['link'],
						'lang_link_statustext'	=> $this->data[$x]['lang_link_statustext']
					);
				}
				$this->listbox = $var;
			}
			$this->set_xinternal($extra_data);
			$this->draw_box();
		}
	}
?>
