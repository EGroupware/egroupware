<?php
	/**************************************************************************\
	* phpGroupWare - log                                                       *
	* http://www.phpgroupware.org                                              *
	* This application written by jerry westrick <jerry@westrick.com>          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class log
	{
		/***************************\
		*	Instance Variables...   *
		\***************************/
		var $errorstack = array();
		var $public_functions = array(
			'message',
			'error',
			'iserror',
			'severity',
			'commit',
			'clearstack',
			'astable'
		);

		function message($etext,$p0='',$p1='',$p2='',$p3='',$p4='',$p5='',$p6='',$p7='',$p8='',$p9='')
		{
			$parms = array($p0,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,$p9);
			CreateObject('phpgwapi.error',$etext,$parms,1);
		}

		function error($etext,$p0='',$p1='',$p2='',$p3='',$p4='',$p5='',$p6='',$p7='',$p8='',$p9='')
		{
			$parms = array($p0,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8,$p9);
			CreateObject('phpgwapi.error',$etext,$parms,false);
		}

		function iserror($ecode)
		{
			$errorstack = $this->errorstack;
			reset($errorstack);
			while(list(,$err)=each($errorstack))
			{
				if ($ecode == $err->code)
				{
					return true;
				}
			}
			return false;
		}

		function severity()
		{
			$max = 'I';
			$errorstack = $this->errorstack;
			reset($errorstack);
			while(list(,$err)=each($errorstack))
			{
				switch($err->severity)
				{
					case 'F':
						return 'F';
						break;
					case 'E':
						$max = 'E';
						break;
					case 'W':
					if ($max == 'I') 
					{ 
						$max = 'W';
					}
					break;
				}
			}
			return $max;
		}

		function commit()
		{
			$db = $GLOBALS['phpgw']->db;
			$db->query("insert into phpgw_log (log_date, log_user, log_app, log_severity) values "
				."('". $GLOBALS['phpgw']->db->to_timestamp(time())
				."','".$GLOBALS['phpgw']->session->account_id
				."','".$GLOBALS['phpgw_info']['flags']['currentapp']."'"
				.",'".$this->severity()."'"
				.")"
				,__LINE__,__FILE__);

			$errorstack = $this->errorstack;
			for ($i = 0; $i < count($errorstack); $i++)
			{
				$err = $errorstack[$i];
				$db->query("insert into phpgw_log_msg "
					. "(log_msg_seq_no, log_msg_date, "
					. "log_msg_severity, log_msg_code, log_msg_msg, log_msg_parms) values "
					. "(" . $i
					. ", '" . $GLOBALS['phpgw']->db->to_timestamp($err->timestamp)
					. "', '". $err->severity . "'"
					. ", '". $err->code      . "'"
					. ", '". $err->msg       . "'"
					. ", '". addslashes(implode('|',$err->parms)). "'"
					. ")",__LINE__,__FILE__);
			}
			unset ($errorstack);
			unset ($this->errorstack);
			$this->errorstack = array();
		}

		function clearstack()
		{
			$new = array();
			reset($this->errorstack);
			for ($i = 0; $i < count($this->errorstack); $i++)
			{
				$err = $this->errorstack[$i];
				if ($err->ismsg)
				{
					$new[] = $err;
				}
			}
			unset ($this->errorstack);
			$this->errorstack = $new;
		}

		function astable()
		{
			$html  = "<center>\n";
			$html .= "<table width=\"98%\">\n";
			$html .= "\t<tr bgcolor=\"D3DCFF\">\n";
			$html .= "\t\t<td width=\"2%\">No</td>\n";
			$html .= "\t\t<td width=\"16%\">Date</td>\n";
			$html .= "\t\t<td width=\"15%\">App</td>\n";
			$html .= "\t\t<td align=\"center\", width=\"2%\">S</td>\n";
			$html .= "\t\t<td width=\"10%\">Error Code</td>\n";
			$html .= "\t\t<td >Msg</td>\n";
			$html .= "\t</tr>\n";

			$errorstack = $this->errorstack;
			for ($i = 0; $i < count($errorstack); $i++)
			{
				$err = $errorstack[$i];
				switch ($err->severity)
				{
					case 'I':
						$color = 'C0FFC0';
						break;
					case 'W':
						$color = 'FFFFC0';
						break;
					case 'E':
						$color = 'FFC0C0';
						break;
					case 'F':
						$color = 'FF0909';
						break;
				}

				$html .= "\t<tr bgcolor=".'"'.$color.'"'.">\n";
				$html .= "\t\t<td align=center>".$i."</td>\n";
				$html .= "\t\t<td>".$GLOBALS['phpgw']->common->show_date($err->timestamp)."</td>\n";
				$html .= "\t\t<td>".$GLOBALS['phpgw_info']['flags']['currentapp']."&nbsp </td>\n";
				$html .= "\t\t<td align=center>".$err->severity."</td>\n";
				$html .= "\t\t<td>".$err->code."</td>\n";
				$html .= "\t\t<td>".$err->langmsg()."</td>\n";
				$html .= "\t</tr>\n";
			}
			$html .= "</table>\n";
			$html .= "</center>\n";

			return $html;
		}
	}
