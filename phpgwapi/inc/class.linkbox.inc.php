<?php
  /**************************************************************************\
  * phpGroupWare API - Link box generator                                    *
  * http://www.phpgroupware.org/api                                          *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Creates linkboxes using templates                                        *
  * Copyright (C) 2000, 2001 Mark Peters                                     *
  * -------------------------------------------------------------------------*
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

	class linkbox extends portalbox
	{
		/*
		 Set up the Object. You will notice, we have not reserved
		 memory space for variables. In this circumstance it is not necessary.
		 */

		/*
		 This is the constructor for the linkbox. The only thing this does
		 is to call the constructor of the parent class. Why? Well, whilst
		 PHP manages a certain part of OO, one of the bits it falls down on
		 (at the moment) is constructors within sub-classes. So, to
		 be sure that the sub-class is instantiated with the constructor of
		 the parent class, I simply call the parent constructor. Of course,
		 if I then wanted to override any of the values, I could easily do so.
		*/
		function linkbox($param)
		{
			$title = $param[0];
			$primary = $param[1];
			$secondary =$param[2];
			$tertiary = $param[3];
			$this->portalbox($title, $primary, $secondary, $tertiary);
			$this->setvar("outerwidth",300);
			$this->setvar("innerwidth",300);
			$this->setvar("width",300);
		}

		/*
		 This is the only method within the class. Quite simply, as you can see
		 it draws the table(s), placing the required data in the appropriate place.
		*/
		function draw()
		{
			$p = new Template($GLOBALS['phpgw']->common->get_tpl_dir('home'));
			$p->set_file(array('portal_main' => 'portal_main.tpl',
				'portal_linkbox_header' => 'portal_linkbox_header.tpl',
				'portal_linkbox' => 'portal_linkbox.tpl',
				'portal_linkbox_footer' => 'portal_linkbox_footer.tpl'));
			$p->set_block('portal_main','portal_linkbox_header','portal_linkbox','portal_linkbox_footer');

			$p->set_var('outer_border',$this->getvar('outerborderwidth'));
			$p->set_var('outer_width',$this->getvar('width'));
			$p->set_var('outer_bordercolor',$this->getvar('outerbordercolor'));
			$p->set_var('outer_bgcolor',$this->getvar('titlebgcolor'));
			$p->set_var('title',$this->getvar('title'));
			$p->set_var('inner_width',$this->getvar('width'));
			$p->set_var('inner_bgcolor',$this->getvar('innerbgcolor'));
			$p->set_var('header_background_image',$this->getvar('header_background_image'));
			$p->parse('output','portal_linkbox_header',True);

			for ($x = 0; $x < count($this->data); $x++)
			{
				$p->set_var('link',$this->data[$x][1]);
				$p->set_var('text',$this->data[$x][0]);
				$p->parse('output','portal_linkbox',True);
			}
			$p->parse('output','portal_linkbox_footer',True);
			return $p->parse('out','portal_main');
		}
	}
