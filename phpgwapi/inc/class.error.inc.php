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
		var $fname;
		var $line;
		var $app;
		
		var $public_functions = array();

		// Translate Message into Language
		function langmsg()
		{
			return lang($this->msg,$this->parms);		
		}		

		function error($parms)
		{
			global $phpgw;
			if ($parms == '')
			{
				return;
			};
			$etext =$parms['text'];
			$parray = array($parms['p1'],$parms['p2'],$parms['p3'],$parms['p4'],$parms['p5'],$parms['p6'],$parms['p7'],$parms['p8'],$parms['p9'],$parms['p10']);
			$fname = $parms['file'];
			$line  = $parms['line'];
			if (eregi('([DIWEF])-(.*)[\,](.*)',$etext,$match))
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
			$this->parms     = $parray;
			$this->ismsg     = $parms['ismsg'];
			$this->fname     = $fname;
			$this->line		 = $line;
			$this->app 		 = $phpgw_info['flags']['currentapp'];
			
 			if (!$this->fname or !$this->line)
			{
				$phpgw->log->error(array('text'=>'W-PGMERR, Programmer failed to pass __FILE__ and/or __LINE__ in next log message',
										'file'=>__FILE__,'line'=>__LINE__
										)
								  );
			}
 
			$phpgw->log->errorstack[] = $this;
			if ($this->severity == 'F')
			{
				// This is it...  Don't return
				// do rollback!
				// Hmmm this only works if UI!!!!
				// What Do we do if it's a SOAP/XML?
				echo "<Center>";
				echo "<h1>Fatal Error</h1>";
				echo "<h2>Error Stack</h2>";
				echo $phpgw->log->astable();
				echo "</center>";
				// Commit stack to log
				$phpgw->log->commit();
				$phpgw->common->phpgw_exit(True);
			}
		}
	}
