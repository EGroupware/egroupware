<?php
   /**************************************************************************\
   * eGroupWare API - Wrapper for the creditspoint credits check			  *
   * Written by Rob van Kraanen<rob@lingewoud.nl>                             *
   *                                                                          *
   * Wrapper for the savant2 template engine www.phpsavant.com                *
   * Copyright (C) 2005 Lingewoud BV and Rob van Kraanen					  *
   * -------------------------------------------------------------------------*
   * This library is part of the eGroupWare API                               *
   * http://www.egroupware.org                                                * 
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

   class creditspoint 
   {
	  var $cpapi;
	  var $useCP = false;


	  function creditspoint()
	  {
		 $found = false;
		 foreach($GLOBALS['phpgw_info']['user']['acl'] as $acl)
		 {
			if($acl['appname'] == 'creditspoint')
			{
			   $found =true;
			}
		 }
		 if( is_array($GLOBALS['phpgw_info']['apps']['creditspoint']) and $found)
		 {
			$this->cpapi = CreateObject('creditspoint.api');
			$this->useCP = true;
		 }
	  }

	  function exec_service_plain($appname, $service, $link, $uniqid)
	  {
		 if($this->useCP)
		 {
			return $this->cpapi->exec_service_plain($appname, $service, $link, $uniqid);
		 }
		 else
		 {
			return $link;
		 }
	  }

	  function exec_service_link($appname, $service, $link, $linkname, $uniqid)
	  {
		 if($this->useCP)
		 {
			return $this->cpapi->exec_service_link($appname, $service, $link, $linkname, $uniqid);
		 }
		 else
		 {
			return $link;
		 }
	  }

	  function exec_service_button($appname, $service, $link, $buttonlabel, $uniqid)
	  {
		 if($this->useCP)
		 {
			return $this->cpapi->exec_service_button($appname, $service, $link, $buttonlabel, $uniqid);
		 }
		 else
		 {
			return $link;
		 }
	  }

	  function exec_service_img($appname, $service, $link, $imgsrc, $uniqid)
	  {
		 if($this->useCP)
		 {
			return $this->cpapi->exec_service_img($appname, $service, $link, $imgsrc, $uniqid);
		 }
		 else
		 {
			return $link;
		 }
	  }

	  function confirm($uniqid)
	  {
		 if($this->useCP)
		 {
			return $this->cpapi->confirm($uniqid);
		 }
		 else
		 {
			return $link;
		 }
	  }
	  
	  function refund($uniqid)
	  {
		 if($this->useCP)
		 {
			return $this->cpapi->refund($uniqid);
		 }
		 else
		 {
			return $link;
		 }
	  }
   }
