<?php
  /**************************************************************************\
  * phpGroupWare API - Portal Box manager                                    *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * Helps manage the portal boxes for phpGroupWares main page                *
  * Copyright (C) 2000, 2001  Joseph Engo                                    *
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

	class portalbox
	{
		//Set up the Object, reserving memory space for variables

		var $outerwidth;
		var $outerbordercolor;
		var $outerborderwidth;
		var $titlebgcolor;
		var $width;
		var $innerwidth;
		var $innerbgcolor;

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
				echo 'Programming Error: '.$this->classname().'->getvar('.$var.')!<br>\n';
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			//echo "Var = ".$var."<br>\n";
			//echo $var." = ".$this->$var."<br>\n";
			return $this->$var;
		}

		/*
		This is the constructor for the object.
		*/
		function portalbox($title='', $primary='', $secondary='', $tertiary='')
		{
			$this->setvar('title',$title);
			// echo 'After SetVar Title = '.$this->getvar('title')."<br>\n";
			$this->setvar('outerborderwidth',1);
			$this->setvar('titlebgcolor',$primary);
			$this->setvar('innerbgcolor',$secondary);
			$this->setvar('outerbordercolor',$tertiary);
		}
	}
