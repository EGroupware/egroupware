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
			// nextmatchs
			$this->start					= 0;
			$this->nextmatchs				= CreateObject('phpgwapi.nextmatchs');



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
			reset($this->fields_inc);
			while(list($cno,$cname)=each($this->fields_inc))
			{
				$this->column[$cname]=$cno;
			};
		} 

		function list_log()
		{
			global $phpgw, $phpgw_info;
/*
$phpgw->log->write(array('text'=>'I-TestWrite, write: %1','p1'=>'This message should appear in log'));
$phpgw->log->message(array('text'=>'I-TestMsg, msg: %1','p1'=>'This message should appear in log'));
$phpgw->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should not be in log'));
$phpgw->log->clearstack();
$phpgw->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should be in log'));
$phpgw->log->error(array('text'=>'W-TestWarn, warn: %1','p1'=>'This is a test Warning'));
$phpgw->log->error(array('text'=>'E-TestError, err: %1','p1'=>'This is a test Error'));
$phpgw->log->error(array('text'=>'F-Abend, abort: %1','p1'=>'Force abnormal termination'));
$phpgw->log->commit();  // commit error stack to log...
*/
			$this->t->set_file(array('log_list_t' => 'log.tpl'));
/*
// --------------------------------- nextmatch ---------------------------

			$left = $this->nextmatchs->left('/admin/log.php',$this->start,$this->this->total_records,'&menuaction=admin.uilog.list_log');
			$right = $this->nextmatchs->right('/admin/log.php',$this->start,$this->this->total_records,'&menuaction=admin.uilog.list_log');
			$this->t->set_var('left',$left);
			$this->t->set_var('right',$right);

			$this->t->set_var('search_log',$this->nextmatchs->show_hits($this->bolog->total_records,$this->start));

// -------------------------- end nextmatch ------------------------------------
*/

			// Get list of Possible Columns
			$header = $this->bolog->get_error_cols_e();

			// Set Table formating parameters
			$header['_table_parms']='width="98%", bgcolor="D3DCFF" border="0"';

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

			// Set up Grouping, Suppression...
			$header['_groupby']=array('log_id'=>1);
			$header['_supres']=array('log_id'=>1,'log_severity'=>1,'log_date_e'=>1,'log_app'=>1,'log_full_name'=>1);


			// Hack Get All Rows
			$rows = $this->bolog->get_error_e(array('orderby'=>array('log_id','log_msg_log_id')));
			$norows = count($rows);
			$header['_edittable']=$this->editmode;
			$table = $this->html->hash_table($rows,$header,$this, 'format_row');
			$this->t->set_var('event_list',$table);
			$this->t->pfp('out','log_list_t');
			$phpgw->common->phpgw_footer();
//			$this->set_app_langs();
		}

		function format_row($rno, $row)
		{
						
			switch($row[$this->column['log_severity']]['value'])
			{
				
				case 'I': $row[$this->column['log_severity']]['bgcolor'] = 'C0FFC0'; break;
				case 'W': $row[$this->column['log_severity']]['bgcolor'] = 'FFFFC0'; break;
				case 'E': $row[$this->column['log_severity']]['bgcolor'] = 'FFC0C0'; break;
				case 'F': $row[$this->column['log_severity']]['bgcolor'] = 'FF0909'; break;
			}

			switch($row[$this->column['log_msg_severity']]['value'])
			{
				case 'I': $color = 'C0FFC0'; break;
				case 'W': $color = 'FFFFC0'; break;
				case 'E': $color = 'FFC0C0'; break;
				case 'F': $color = 'FF0909'; break;
			}
			reset($this->fields_inc);
			while(list($cno,$fld) = each($this->fields_inc))
			{
				if (substr($fld,0,7) == 'log_msg')
				{
					$row[$cno]['bgcolor'] = $color;
				}
				else
				{
//					$row[$cno]['bgcolor'] = $lcolor;
				}
			}				
			return $row;
		}
	}
?>
