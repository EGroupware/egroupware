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

		var $public_functions = array(
			'list_log' => True
		);

		function uilog()
		{
			$_cols    = $GLOBALS['HTTP_POST_VARS']['_cols'];
			$nocols   = $GLOBALS['HTTP_POST_VARS']['nocols'];
			$_delcol  = $GLOBALS['HTTP_POST_VARS']['_delcol'];
			$layout   = $GLOBALS['HTTP_POST_VARS']['layout'];
			$editable = $GLOBALS['HTTP_GET_VARS']['editable'];
			$modifytable = $GLOBALS['HTTP_GET_VARS']['modifytable'] ? $GLOBALS['HTTP_GET_VARS']['modifytable'] : $GLOBALS['HTTP_POST_VARS']['modifytable'];

			$this->bolog					= CreateObject('admin.bolog',True);
			$this->html						= createobject('admin.html');
			$this->t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('admin'));
			$this->lastid					= '';
			$this->editmode					= false;

			// Handle the Edit Table Button
			if (isset($editable))
			{
				$this->editmode = $editable;
			}

			// Handle return from Modify Table form...
			if ($modifytable)
			{
				// the delete column must not be empty
				if (!isset($_delcol))
				{
					$_delcol = array();
				}

				// Build New fields_inc array...
				if (isset($_cols))
				{
					$c = array();
					for ($i=0;$i<count($_cols);$i++)
					{
						if (!in_array($i, $_delcol))
						{
							$c[] = $_cols[$i];
						}
					}
					$this->fields_inc = $c;
				}

				// Reset Mode to display...
				$this->editmode = false;
				$this->layout = $layout;

				// Save the fields_inc values in Session and User Preferences...
				$data = array('fields_inc'=>$this->fields_inc,'layout'=>$layout);
				$GLOBALS['phpgw']->session->appsession('session_data','log',$data);
				$GLOBALS['phpgw']->preferences->read_repository();
				$GLOBALS['phpgw']->preferences->delete('log','fields_inc');
				$GLOBALS['phpgw']->preferences->add('log','fields_inc',$this->fields_inc);
				$GLOBALS['phpgw']->preferences->delete('log','layout');
				$GLOBALS['phpgw']->preferences->add('log','layout',$this->layout);
				$GLOBALS['phpgw']->preferences->save_repository();
			}

			// Make sure that $this->fields_inc is filled
			if ( !isset($this->field_inc))
			{
				// Need to fill from Session Data...
				$data = $GLOBALS['phpgw']->session->appsession('session_data','log');
				if (isset($data) && isset($data['fields_inc']))
				{
					$this->fields_inc = $data['fields_inc'];
					$this->layout = $data['layout'];
				}
				else
				{
					$GLOBALS['phpgw']->preferences->read_repository();
					// Get From User Profile...
					if (@$GLOBALS['phpgw_info']['user']['preferences']['log']['fields_inc'])
					{
						$fields_inc = $GLOBALS['phpgw_info']['user']['preferences']['log']['fields_inc'];
						$this->fields_inc = $fields_inc;
						$layout = $GLOBALS['phpgw_info']['user']['preferences']['log']['layout'];
						$this->layout = $layout;
						$GLOBALS['phpgw']->session->appsession('session_data','log',array('fields_inc'=>$fields_inc,'layout'=>$layout));
					}
					else
					{
						// Use defaults...
						$this->fields_inc = array(
							'log_severity',
							'log_id',
							'log_date_e',
							'log_app',
							'log_full_name',
							'log_msg_seq_no',
							'log_msg_date_e',
							'log_msg_severity',
							'log_msg_code',
							'log_msg_text',
							'log_msg_file',
							'log_msg_line'
						);
						$this->layout[]= array(0,1,2,3,4,5,6,7,8,9);
						$this->layout[]= array(0,1,2,3,4,5,6,7,10,11);

						// Store defaults in session data...
						$GLOBALS['phpgw']->session->appsession(
							'session_data',
							'log',
							array(
								'fields_inc'=>$this->fields_inc,
								'layout'=>$this->layout
							)
						);
					}
				}

			} // Values already filled...
			reset($this->fields_inc);
			while(list($cno,$cname)=each($this->fields_inc))
			{
				$this->column[$cname]=$cno;
			}
		}

		function list_log()
		{
			if (false) // add some errors to the log...
			{
				// Test 1: single Error line immedeately to errorlog 
				// (could be type Debug, Info, Warning, or Error)
				$GLOBALS['phpgw']->log->write(array('text'=>'I-TestWrite, write: %1','p1'=>'This message should appear in log','file'=>__FILE__,'line'=>__LINE__));

				// Test 2: A message should appear in log even if clearstack is called
				$GLOBALS['phpgw']->log->message(array('text'=>'I-TestMsg, msg: %1','p1'=>'This message should appear in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should not be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->clearstack();
				$GLOBALS['phpgw']->log->commit();  // commit error stack to log...

				// Test 3: one debug message
				$GLOBALS['phpgw']->log->error(array('text'=>'D-Debug, dbg: %1','p1'=>'This debug statment should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->commit();  // commit error stack to log...

				// Test 3: debug and one informational
				$GLOBALS['phpgw']->log->error(array('text'=>'D-Debug, dbg: %1','p1'=>'This debug statment should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->commit();  // commit error stack to log...

				// Test 4: an informational and a Warning
				$GLOBALS['phpgw']->log->error(array('text'=>'D-Debug, dbg: %1','p1'=>'This debug statment should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'W-TestWarn, warn: %1','p1'=>'This is a test Warning','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->commit();  // commit error stack to log...

				// Test 5: and an error
				$GLOBALS['phpgw']->log->error(array('text'=>'D-Debug, dbg: %1','p1'=>'This debug statment should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'W-TestWarn, warn: %1','p1'=>'This is a test Warning','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'E-TestError, err: %1','p1'=>'This is a test Error','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->commit();  // commit error stack to log...

				// Test 6: and finally a fatal...
				$GLOBALS['phpgw']->log->error(array('text'=>'D-Debug, dbg: %1','p1'=>'This debug statment should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'I-TestInfo, info: %1','p1'=>'This Informational should be in log','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'W-TestWarn, warn: %1','p1'=>'This is a test Warning','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'E-TestError, err: %1','p1'=>'This is a test Error','file'=>__FILE__,'line'=>__LINE__));
				$GLOBALS['phpgw']->log->error(array('text'=>'F-Abend, abort: %1','p1'=>'Force abnormal termination','file'=>__FILE__,'line'=>__LINE__));
			}
			$this->t->set_file(array('log_list_t' => 'log.tpl'));

			// -------------------------- Layout Description -------------------------------
			$phycols = array('2%', '2%', '15%', '10%', '15%', '2%', '20%', '2%', '7%', '25%');
			// -------------------------- end Layout Description ---------------------------

			// Get list of Possible Columns
			$header = $this->bolog->get_error_cols_e();

			// Describe table layout...
			$header['#phycols'] = $phycols;
			$header['#layout'] = $this->layout;

			// Set User Configured List of columns to show
			$header['_cols']= $this->fields_inc;

			// Set Table formating parameters
			$header['#table_parms']=array('width'=>"98%", 'bgcolor'=>"000000", 'border'=>"0");

			// Set Header formating parameters
			$header['#head_parms']=array('bgcolor'=>"D3DCFF");

			// Column Log_ID
			$header['log_id']['#parms_hdr'] = array('align'=>"center");
			$header['log_id']['#title'] = 'Id';
			$header['log_id']['align'] = 'center';

			// Column Log_Severity
			$header['log_severity']['#parms_hdr'] = array('align'=>"center");
			$header['log_severity']['#title'] = 'S';
			$header['log_severity']['align'] = 'center';

			// Column Trans Date
			$header['log_date_e']['#title'] = 'Tans. Date';

			// Column Application
			$header['log_app']['#title'] = 'App.';

			// Column FullName
			$header['log_full_name']['#title'] = 'User';
			$header['log_full_name']['align'] = 'center';

			// Column log_msg_seq_no
			$header['log_msg_seq_no']['#parms_hdr'] = array('align'=>"center");
			$header['log_msg_seq_no']['#title'] = 'Sno';
			$header['log_msg_seq_no']['align'] = 'center';

			// Column log_msg_seq_no
			$header['log_msg_date_e']['#title'] = 'TimeStamp';
			$header['log_msg_severity']['#title'] = 'S';
			$header['log_msg_severity']['align'] = 'center';
			$header['log_msg_code']['#title'] = 'Code';
			$header['log_msg_text']['#title'] = 'Error Msg';
			$header['log_msg_file']['#title'] = 'File';
			$header['log_msg_line']['#title'] = 'Line';

			// Set up Grouping, Suppression...
			$header['_groupby']=array('log_id'=>1);
			$header['_supres']=array('log_id'=>1,'log_severity'=>1,'log_date_e'=>1,'log_app'=>1,'log_full_name'=>1);

			// Hack Get All Rows
			$rows = $this->bolog->get_error_e(array('orderby'=>array('log_id','log_msg_log_id')));
			$norows = count($rows);
			$header['_edittable']=$this->editmode;
			$table = $this->html->hash_table($rows,$header,$this, 'format_row');
			$this->t->set_var('event_list',$table);

			$GLOBALS['phpgw']->common->phpgw_header();
			echo parse_navbar();
			$this->t->pfp('out','log_list_t');
//			$this->set_app_langs();
		}

		function format_row($rno, $row)
		{
			switch($row['log_severity']['value'])
			{
				case 'D': $row['log_severity']['bgcolor'] = 'D3DCFF'; break;
				case 'I': $row['log_severity']['bgcolor'] = 'C0FFC0'; break;
				case 'W': $row['log_severity']['bgcolor'] = 'FFFFC0'; break;
				case 'E': $row['log_severity']['bgcolor'] = 'FFC0C0'; break;
				case 'F': $row['log_severity']['bgcolor'] = 'FF0909'; break;
			}

			switch($row['log_msg_severity']['value'])
			{
				case 'D': $color = 'D3DCFF'; break;
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
					$row[$fld]['bgcolor'] = $color;
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
