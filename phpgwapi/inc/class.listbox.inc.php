<?php
	/**************************************************************************\
	* phpGroupWare API - Link box generator                                    *
	* http://www.phpgroupware.org/api                                          *
	* Written by Mark Peters <skeeter@phpgroupware.org>                        *
	* Creates listboxes using templates                                        *
	* Copyright (C) 2000 - 2002 Mark Peters                                    *
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

	CreateObject('phpgwapi.portalbox');

	class listbox extends portalbox
	{
		/*
		 Set up the Object. You will notice, we have not reserved
		 memory space for variables. In this circumstance it is not necessary.
		 */

		/*
		 This is the constructor for the listbox. The only thing this does
		 is to call the constructor of the parent class. Why? Well, whilst
		 PHP manages a certain part of OO, one of the bits it falls down on
		 (at the moment) is constructors within sub-classes. So, to
		 be sure that the sub-class is instantiated with the constructor of
		 the parent class, I simply call the parent constructor. Of course,
		 if I then wanted to override any of the values, I could easily do so.
		*/
		function listbox($param)
		{
			@reset($param);
			while(list($key,$value) = each($param))
			{
				if($key != 'title')
				{
					//echo 'Setting '.$key.':'.$value."<br>\n";
					$this->setvar($key,$value);
				}
			}
			$this->portalbox($param['title']);

			if($param['app_id'])
			{
				$app_id = $this->getvar('app_id');

				$var = Array
				(
					'up'       => Array('url' => '/set_box.php', 'app' => $app_id),
					'down'     => Array('url' => '/set_box.php', 'app' => $app_id),
					'close'    => Array('url' => '/set_box.php', 'app' => $app_id),
					'question' => Array('url' => '/set_box.php', 'app' => $app_id),
					'edit'     => Array('url' => '/set_box.php', 'app' => $app_id)
				);

				while(list($key,$value) = each($var))
				{
					$this->set_controls($key,$value);
				}
			}
		}

		/*
		 This is the only method within the class. Quite simply, as you can see
		 it draws the table(s), placing the required data in the appropriate place.
		*/
		function draw($extra_data='')
		{
			$this->start_template();

			if(count($this->data))
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
				$this->output[]['listbox'] = $var;
			}
			$this->set_internal($extra_data);
			$this->draw_box();
		}

		function xdraw($extra_data='')
		{
			if ($extra_data)
			{
				$this->start_template(True);
			}
			else
			{
				$this->start_template();
			}

			if(count($this->data))
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
				$this->output[]['listbox'] = $var;
			}
			$this->set_xinternal($extra_data);
			$this->draw_box();
		}
	}
?>
