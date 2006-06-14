<?php
	/**************************************************************************\
	* eGroupWare - errorlog                                                    *
	* http://www.egroupware.org                                                *
	* This application written by jerry westrick <jerry@westrick.com>          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class errorlog
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
		var $log_table = 'egw_log';
		var $msg_table = 'egw_log_msg';

		function message($parms)
		{
			$parms['ismsg']=1;
			CreateObject('phpgwapi.error',$parms);
			return true;
		}

		function error($parms)
		{
			$parms['ismsg']=0;
			CreateObject('phpgwapi.error',$parms);
			return true;
		}

		function write($parms)
		{
			$parms['ismsg']=0;
			$save = $this->errorstack;
			$this->errorstack = array();
			CreateObject('phpgwapi.error',$parms);
			$this->commit();
			$this->errorstack = $save;
			return true;
		}

		function iserror($parms)
		{
			$ecode = $parms['code'];
			foreach($this->errorstack as $err)
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
			$max = 'D';
			foreach($this->errorstack as $err)
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
						if ($max != 'E') 
						{
							$max = 'W';
						}
						break;
					case 'I':
						if ($max == 'D')
						{
							$max = 'I';
						}
						break;
					default:
						break;
				}
			}
			return $max;
		}

		function commit()
		{
			$db = clone($GLOBALS['egw']->db);

			$db->insert($this->log_table,array(
				'log_date' => $GLOBALS['egw']->db->to_timestamp(time()),
				'log_user' => $GLOBALS['egw_info']['user']['account_id'],
				'log_app'  => $GLOBALS['egw_info']['flags']['currentapp'],
				'log_severity' => $this->severity(),
			),false,__LINE__,__FILE__);

			$log_id = $db->get_last_insert_id($this->log_table,'log_id');

			foreach($this->errorstack as $i => $err)
			{
				$db->insert($this->msg_table,array(
					'log_msg_log_id' => $log_id,
					'log_msg_seq_no' => $i,
					'log_msg_date'   => $GLOBALS['egw']->db->to_timestamp($err->timestamp),
					'log_msg_severity' => $err->severity,
					'log_msg_code'   => $err->code,
					'log_msg_msg'    => $err->msg,
					'log_msg_parms'  => implode('|',(array)$err->parms),
					'log_msg_file'   => $err->fname,
					'log_msg_line'   => $err->line,
				),false,__LINE__,__FILE__);
			}
			$this->errorstack = array();

			return true;
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
				};
			}
			unset ($this->errorstack);
			$this->errorstack = $new;
			return true;
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
			$html .= "\t\t<td >File</td>\n";
			$html .= "\t\t<td >Line</td>\n";
			$html .= "\t</tr>\n";

			$errorstack = $this->errorstack;
			for ($i = 0; $i < count($errorstack); $i++)
			{
				$err = $errorstack[$i];
				switch ($err->severity)
				{
					case 'D': $color = 'D3DCFF'; break;
					case 'I': $color = 'C0FFC0'; break;
					case 'W': $color = 'FFFFC0'; break;
					case 'E': $color = 'FFC0C0'; break;
					case 'F': $color = 'FF0909'; break;
				}

				$html .= "\t<tr bgcolor=".'"'.$color.'"'.">\n";
				$html .= "\t\t<td align=center>".$i."</td>\n";
				$html .= "\t\t<td>".$GLOBALS['egw']->common->show_date($err->timestamp)."</td>\n";
				$html .= "\t\t<td>".$err->app."&nbsp </td>\n";
				$html .= "\t\t<td align=center>".$err->severity."</td>\n";
				$html .= "\t\t<td>".$err->code."</td>\n";
				$html .= "\t\t<td>".$err->langmsg()."</td>\n";
				$html .= "\t\t<td>".$err->fname."</td>\n";
				$html .= "\t\t<td>".$err->line."</td>\n";
				$html .= "\t</tr>\n";
			}
			$html .= "</table>\n";
			$html .= "</center>\n";

			return $html;
		}
	}
