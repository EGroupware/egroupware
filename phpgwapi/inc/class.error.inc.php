<?php
	/**************************************************************************\
	* phpGroupWare - eventlog                                                  *
	* http://www.phpgroupware.org                                              *
	* This application written by jerry westrick <jerry@westrick.com>          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class error
	{
		/***************************\
		*	Instance Variables...   *
		\***************************/
		var $severity = 'E';
		var $code = 'Unknown';
		var $msg  = 'Unknown error';
		var $parms = array();
		var $ismsg = 0;
		var $timestamp;
		
		var $public_functions = array();

		/*******************************************\
		* Constructor                               *
		* to be accessed as new error()             *
		\*******************************************/
		// Translate Message into Language
		function langmsg()
		{
			return lang($this->msg,$this->parms);		
		}		


		function error($etext, $parms, $ismsg)
		{
			global $phpgw;
			if (eregi('([IWEF])-(.*)[\,](.*)',$etext,$match))
			{
				$this->severity = strtoupper($match[1]);
				$this->code     = $match[2];
				$this->msg      = trim($match[3]);
			}
			else
			{
				$this->msg = trim($etext);
			}
			$this->timestamp = time();
			$this->parms     = $parms;
			$this->ismsg     = $ismsg;

			$phpgw->log->errorstack[] = $this;
			if ($this->severity == 'F')
			{
				// This is it...  Don't return
				// do rollback...
				// Hmmm this only works if UI!!!!
				// What Do we do if it's a SOAP/XML?
				echo "<Center>";
				echo "<h1>Fatal Error</h1>";
				echo "<h2>Error Stack</h2>";
				echo $phpgw->log->astable();
				echo "</center>";
				// Commit stack to log
				$phpgw->log->commit();
				exit();
			}
		}
	}
