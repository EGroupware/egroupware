<?php
	/***************************************************************************\
	* phpGroupWare - uilog                                                      *
	* http://www.phpgroupware.org                                               *
	* Written by : jerry westrick [jerry@westrick.com]                          *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id$ */

	class uilog
	{
		var $grants;
		var $cat_id;
		var $start;
		var $search;
		var $filter;

		var $public_functions = array
		(
			'list_log'	=> True
		);

		function uilog()
		{
			global $phpgw, $_cols, $editable, $modifytable, $nocols, $_delcol, $phpgw_info;

			$this->bolog					= CreateObject('admin.bolog',True);
			$this->html						= createobject('admin.html');
			$this->t = CreateObject('phpgwapi.Template',$phpgw->common->get_tpl_dir('admin'));
			$this->lastid					= "";
			$this->editmode					= false;


			// Handle the Edit Table Button
			if (isset($editable))	
			{
				$this->editmode = $editable;
			};
			

			// Handle return from Modify Table form...
			if (isset($modifytable))
			{			
				// the delete column must not be empty
				if (!isset($_delcol))
				{
					$_delcol = array();
				};
				
				// Build New fields_inc array...
				if (isset($_cols))
				{
					$c = array();
					for ($i=0;$i<$nocols;$i++)
					{
						if (!in_array($i, $_delcol))
						{
							$c[] = $_cols[$i];
						};
					}
					$this->fields_inc = $c;
				};													

				// Reset Mode to display...
				$this->editmode = false;
				
				// Save the fields_inc values in Session and User Preferences...
				$data = array('fields_inc'=>$this->fields_inc);
				$phpgw->session->appsession('session_data','log',$data);
				$phpgw->preferences->read_repository();
				$phpgw->preferences->delete('log','fields_inc');
				$phpgw->preferences->add('log','fields_inc',$this->fields_inc);
				$phpgw->preferences->save_repository();
			}


			// Make sure that $this->fields_inc is filled
			if ( !isset($this->field_inc))
			{			
				// Need to fill from Session Data...
				$data = $phpgw->session->appsession('session_data','log');
				if (isset($data) && isset($data['fields_inc']))
				{
				
					$this->fields_inc = $data['fields_inc'];
				}
				else
				{
					$phpgw->preferences->read_repository();
					// Get From User Profile...
					if (@$phpgw_info['user']['preferences']['log']['fields_inc'])
					{
						$fields_inc = $phpgw_info['user']['preferences']['log']['fields_inc'];
						$this->fields_inc = $fields_inc;
						$phpgw->session->appsession('session_data','log',array('fields_inc',$fields_inc));
					}
					else
					{
						// Use defaults...
						$this->fields_inc = array	('log_severity',
													'log_id',
													'log_date_e',
													'log_app',
													'log_full_name',
													'log_msg_seq_no',
													'log_msg_date_e',
													'log_msg_severity',
													'log_msg_code',
													'log_msg_text'
													);
						// Store defaults in session data...
						$phpgw->session->appsession ('session_data',
													'log',
													array	('fields_inc'
															=>$this->fields_inc
															)
													);

					}
				}

			} // Values already filled...
		} 

		function list_log()
		{
			global $phpgw, $phpgw_info;
/*
$phpgw->log->message('I-TestMsg, msg: %1','This message should appear in log');
$phpgw->log->error('I-TestInfo, info: %1','This Informational should not be in log');
$phpgw->log->clearstack();
$phpgw->log->error('I-TestInfo, info: %1','This Informational should be in log');
$phpgw->log->error('W-TestWarn, warn: %1','This is a test Warning');
$phpgw->log->error('E-TestError, err: %1','This is a test Error');
$phpgw->log->error('F-Abend, abort: %1','Force abnormal termination');
$phpgw->log->commit();  // commit error stack to log...
*/

			$this->t->set_file(array('log_list_t' => 'log.tpl'));


			// Get list of Possible Columns
			$header = $this->bolog->get_error_cols_e();

			// Set Table formating parameters
			$header['_table_parms']='width="98%", bgcolor="D3DCFF"';

			// Set User Configured List of columns to show
			$header['_cols']= $this->fields_inc;

			// Column Log_ID
			$header['log_id']['parms_hdr'] = 'align="center", width="2%"';
			$header['log_id']['title'] = 'Id';
			$header['log_id']['parms'] = 'align="center"';
		
			// Column Log_Severity
			$header['log_severity']['parms_hdr'] = 'align="center", width="2%"';
			$header['log_severity']['title'] = 'S';
			$header['log_severity']['parms'] = 'align="center"';
		
			// Column Trans Date
			$header['log_date_e']['title'] = 'Tans. Date';
			$header['log_date_e']['parms'] = '';

			// Column Application
			$header['log_app']['title'] = 'Application';
			$header['log_app']['parms'] = '';

			// Column FullName
			$header['log_full_name']['title'] = 'User';
			$header['log_full_name']['parms'] = 'align="center"';

			// Column log_msg_seq_no
			$header['log_msg_seq_no']['parms_hdr'] = 'align="center"';
			$header['log_msg_seq_no']['title'] = 'Sno';
			$header['log_msg_seq_no']['parms'] = 'align="center"';
		
			// Column log_msg_seq_no
			$header['log_msg_date_e']['title'] = 'TimeStamp';
			$header['log_msg_severity']['title'] = 'S';
			$header['log_msg_code']['title'] = 'Code';
			$header['log_msg_text']['title'] = 'Error Msg';

			// Hack Get All Rows
			$rows = $this->bolog->get_error_e(array('orderby'=>array('log_id','log_msg_log_id')));

			$header['_edittable']=$this->editmode;
			$table = $this->html->hash_table($rows,$header,$this, 'format_row');
			$this->t->set_var('event_list',$table);
			$this->t->pfp('out','log_list_t');
			$phpgw->common->phpgw_footer();
//			$this->set_app_langs();
		}

		function format_row($rno, $row)
		{
			if ($rno == 0)
			{
				$this->lastid = '';
			}
			
			if ($this->lastid != $row['log_id'])
			{
				$this->lastid = $row['log_id'];
			}
			else
			{
				$row['log_id'] = '&nbsp ';
				$row['log_severity'] = '&nbsp ';
				$row['log_date_e'] = '&nbsp ';
				$row['log_app'] = '&nbsp ';
				$row['log_full_name'] = '&nbsp ';
			}
			switch($row['log_severity'])
			{
				case 'I': $lcolor = 'C0FFC0'; break;
				case 'W': $lcolor = 'FFFFC0'; break;
				case 'E': $lcolor = 'FFC0C0'; break;
				case 'F': $lcolor = 'FF0909'; break;
			}

			switch($row['log_msg_severity'])
			{
				case 'I': $color = 'C0FFC0'; break;
				case 'W': $color = 'FFFFC0'; break;
				case 'E': $color = 'FFC0C0'; break;
				case 'F': $color = 'FF0909'; break;
			}
			reset($this->fields_inc);
			while(list(,$fld) = each($this->fields_inc))
			{
				if (substr($fld,0,7) == 'log_msg')
				{
					$c = $color;
				}
				else
				{
					if ($fld == 'log_severity' && $row['log_severity'] != '&nbsp ')
					{
						$c = $lcolor;
					}
					else
					{
						$c = "FFFFFF";
					}
				};
				$parms = 'bgcolor="' . $c . '"'; 
				$row['_'.$fld] = $parms;
			}				
			return $row;
		}
	}
?>
