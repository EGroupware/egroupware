<?php
  /**************************************************************************\
  * phpGroupWare API - Result box                                            *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Creates result boxes using templates                                     *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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

	class resultbox extends portalbox
	{
		/* 
		 Set up the Object. You will notice, we have not reserved memory
		 space for variables. In this circumstance it is not necessary.
		*/
		//constructor 
		function resultbox($title='', $primary='', $secondary='', $tertiary='')
		{
			$this->portalbox($title, $primary, $secondary, $tertiary);
			$this->setvar('outerwidth',400);
			$this->setvar('innerwidth',400);
		}

		/*
		 This is the only method within the class. Quite simply, as you can see
		 it draws the table(s), placing the required data in the appropriate place.
		*/
		function draw()
		{
			echo '<table border="'.$this->getvar('outerborderwidth')
				. '" cellpadding="0" cellspacing="0" width="' . $this->getvar('outerwidth')
				. '" bordercolor="' . $this->getvar('outerbordercolor')
				. '" bgcolor="' . $this->getvar('titlebgcolor') . '">';
			echo '<tr><td align="center">'.$this->getvar("title") . '</td></tr>';
			echo '<tr><td>';
			echo '<table border="0" cellpadding="0" cellspacing="0" width="'.$this->getvar('innerwidth')
				. '" bgcolor="' . $this->getvar('innerbgcolor') . '">';
			for ($x = 0; $x < count($this->data); $x++)
			{
				echo '<tr>';
				echo '<td width="50%">' . $this->data[$x][0] . '</td>';
				echo '<td width="50%">' . $this->data[$x][1] . '</td>';
				echo '</tr>';
			}
			echo '</table>';
			echo '</td></tr>';
			echo '</table>';
		}
	}
