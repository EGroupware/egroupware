<?php
  /**************************************************************************\
  * phpGroupWare API - POP3                                                  *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Handles specific operations in dealing with POP3                       *
  * Copyright (C) 2001 Mark Peters and Angelo "Angles" Puglisi                       *
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

	/*!
	@class msg (sockets)
	@abstract part of mail Data Communications class
	@discussion mail Extends msg_base which Extends phpgw api class network
	This is a top level class msg is designed specifically POP3
	@syntax CreateObject('email.mail');
	@author Angles, Skeeter, Itzchak Rehberg, Joseph Engo
	@copyright LGPL
	@package email (to be moved to phpgwapi when mature)
	@access	public
	*/
	class msg extends msg_base
	{
		/**************************************************************************\
		*	Functions that DO NOTHING in POP3  
		\**************************************************************************/
		function createmailbox($stream,$mailbox) 
		{
			return true;
		}
		function deletemailbox($stream,$mailbox)
		{
			return true;
		}
		function expunge($stream)
		{
			return true;
		}
		function listmailbox($stream,$ref,$pattern)
		{
			return False;
		}
		function mailcopy($stream,$msg_list,$mailbox,$flags)
		{
			return False;
		}
		function move($stream,$msg_list,$mailbox)
		{
			return False;
		}
		function reopen($stream,$mailbox,$flags = "")
		{
			return False;
		}
		function append($stream, $folder = "Sent", $header, $body, $flags = "")
		{
			return False;
		}
		/**************************************************************************\
		*	Functions Not Yet Implemented  in POP3
		\**************************************************************************/
		function fetch_overview($stream,$sequence,$flags)
		{
			return False;
		}
		function noop_ping_test($stream)
		{
			return False;
		}
		function server_last_error()
		{
			return '';
		}
		
		/**************************************************************************\
		*	OPEN and CLOSE Server Connection
		\**************************************************************************/
		/*!
		@function open
		@abstract implements php function IMAP_OPEN
		@param $fq_folder : string : {SERVER_NAME:PORT/OPTIONS}FOLDERNAME
		@param $user :  string : account name to log into on the server
		@param $pass :  string : password for this account on the mail server
		@param $flags :  NOT YET IMPLEMENTED
		@discussion implements the functionality of php function IMAP_OPEN
		note that php's IMAP_OPEN applies to IMAP, POP3 and NNTP servers
		@syntax ?
		@author Angles, skeeter
		@access	public
		*/
		function open ($fq_folder, $user, $pass, $flags='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering open<br>'; }
			
			// fq_folder is a "fully qualified folder", seperate the parts:
			$svr_data = array();
			$svr_data = $this->distill_fq_folder($fq_folder);
			$folder = $svr_data['folder'];
			$server = $svr_data['server'];
			$port = $svr_data['port'];
			if ($this->debug >= 1) { echo 'pop3: open: svr_data:<br>'.serialize($svr_data).'<br>'; }
			
			//$port = 110;
			if (!$this->open_port($server,$port,15))
			{
				echo '<p><center><b>' . lang('There was an error trying to connect to your POP3 server.<br>Please contact your admin to check the servername, username or password.').'</b></center>';
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			$this->read_port();
			if(!$this->msg2socket('USER '.$user,"^\+ok",&$response) || !$this->msg2socket('PASS '.$pass,"^\+ok",&$response))
			{
				$this->error();
				if ($this->debug >= 1) { echo 'pop3: Leaving open with Error<br>'; }
				return False;
			}
			else
			{
				//echo "Successful POP3 Login.<br>\n";
				if ($this->debug >= 1) { echo 'pop3: open: Successful POP3 Login<br>'; }
				if ($this->debug >= 1) { echo 'pop3: Leaving open<br>'; }
				return $this->socket;
			}
		}
		
		function close($flags='')
		{
			if (!$this->msg2socket('QUIT',"^\+ok",&$response))
			{
				$this->error();
				if ($this->debug >= 1) { echo 'pop3: close: Error<br>'; }
				return False;
			}
			else
			{
				if ($this->debug >= 1) { echo 'pop3: close: Successful POP3 Logout<br>'; }
				return True;
			}
		}
		
		/**************************************************************************\
		*	Mailbox Status and Information
		\**************************************************************************/
		
		function mailboxmsginfo($stream_notused='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering mailboxmsginfo<br>'; }
			// caching this with POP3 is OK but will cause HAVOC with IMAP or NNTP
			// do we have a cached header_array  ?
			//if ($this->mailbox_msg_info != '')
			//{
			//	if ($this->debug >= 1) { echo 'pop3: Leaving mailboxmsginfo returning cached data<br>'; }
			//	return $this->mailbox_msg_info;
			//}
			// NO cached data, so go get it
			// initialize the structure
			$info = new mailbox_msg_info;
			$info->Date = '';
			$info->Driver ='';
			$info->Mailbox = '';
			$info->Nmsgs = '';
			$info->Recent = '';
			$info->Unread = '';
			$info->Size = '';
			// POP3 will only give 2 items:
			// 1)  number of messages
			// 2) total size of mailbox
			// imap_mailboxmsginfo is the only function to return both of these
			if (!$this->msg2socket('STAT',"^\+ok",&$response))
			{
				$this->error();
				return False;
			}
			$num_msg = explode(' ',$response);
			// fill the only 2 data items we have
			$info->Nmsgs = trim($num_msg[1]);
			$info->Size  = trim($num_msg[2]);
			if ($info->Nmsgs)
			{
				if ($this->debug >= 2)
				{
					echo 'pop3: mailboxmsginfo: info->Nmsgs: '.$info->Nmsgs.'<br>';
					echo 'pop3: mailboxmsginfo: info->Size: '.$info->Size.'<br>';
				}
				if ($this->debug >= 1) { echo 'pop3: Leaving mailboxmsginfo<br>'; }
				// save this data for future use
				//$this->mailbox_msg_info = $info;
				return $info;
			}
			else
			{
				if ($this->debug >= 1) { echo 'pop3: mailboxmsginfo: returining False<br>'; }
				if ($this->debug >= 1) { echo 'pop3: Leaving mailboxmsginfo<br>'; }
				return False;
			}
		}
		
		function status($stream_notused='', $fq_folder='',$options=SA_ALL)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering status<br>'; }
			// POP3 has only INBOX so ignore $fq_folder
			// assume option is SA_ALL for POP3 because POP3 returns so little info anyway
			// initialize structure
			$info = new mailbox_status;
			$info->messages = '';
			$info->recent = '';
			$info->unseen = '';
			$info->uidnext = '';
			$info->uidvalidity = '';
			// POP3 only knows:
			// 1) many messages are in the box, which is:
			//	a) returned by imap_ mailboxmsginfo as ->Nmsgs (in IMAP this is thefolder opened)
			//	b) returned by imap_status (THIS) as ->messages (in IMAP used for folders other than the opened one)
			// 2) total size of the box, which is:
			//	returned by imap_ mailboxmsginfo as ->Size		
			// Most Efficient Method:
			//	call mailboxmsginfo and fill THIS structurte from that
			$mailbox_msg_info = $this->mailboxmsginfo($stream_notused);
			// all POP3 can return from imap_status is messages
			$info->messages = $mailbox_msg_info->Nmsgs;
			if ($this->debug >= 1) { echo 'pop3: status: info->messages: '.$info->messages.'<br>'; }
			if ($this->debug >= 1) { echo 'pop3: Leaving status<br>'; }
			return $info;
		}
		
		// returns number of messages in the mailbox
		function num_msg($stream_notused='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering num_msg<br>'; }
			// Most Efficient Method:
			//	call mailboxmsginfo and fill THIS size data from that
			$mailbox_msg_info = $this->mailboxmsginfo($stream_notused);
			$return_num_msg = $mailbox_msg_info->Nmsgs;
			if ($this->debug >= 1) { echo 'pop3: num_msg: '.$return_num_msg.'<br>'; }
			if ($this->debug >= 1) { echo 'pop3: Leaving num_msg<br>'; }
			return $return_num_msg;
		}
		
		
		/**************************************************************************\
		*	Message Sorting
		\**************************************************************************/
		/*!
		@function sort
		@abstract implements IMAP_SORT
		@param $stream_notused : socket class handles stream reference internally
		@param $criteria :  integer : HOW to sort the messages, we prefer SORTARRIVAL, or "1" as default
			SORTDATE:  0:  This is the Date that the senders email client stamps the message with
			SORTARRIVAL: 1:  This is the date the email arrives at your email server (MTA)
			SORTFROM:  2
			SORTSUBJECT: 3
			SORTSIZE:  6
		@param $reverse : boolean : the ordering if the messages , low to high, or high to low
			FALSE: 0:  lowest to highest  (default for php's builtin imap)
			TRUE: 1:  highest to lowest, a.k.a. "Reverse Sorting"
		@param $options : not implemented
		@result returns an array of integers which are messages numbers for the
		messages sorted as requested.
		@discussion: using SORTDATE can cause some messages to be displayed in the wrong
		cronologicall order, because the sender's MUA can be innaccurate in date stamping
		@author Angles, Skeeter, Itzchak Rehberg, Joseph Engo
		@access	public
		*/
		function sort($stream_notused='',$criteria=SORTARRIVAL,$reverse=False,$options='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering sort<br>'; }
			
			// nr_of_msgs on pop server
			$msg_num = $this->num_msg($stream_notused);
			
			// no msgs - no sort.
			if (!$msg_num)
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving sort with Error<br>'; }
				return false;
			}
			if ($this->debug >= 1) { echo 'pop3: sort: Number of Msgs:'.$msg_num.'<br>'; }
			switch($criteria)
			{
				case SORTDATE:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTDATE<br>'; }
					$old_list = $this->fetch_header_element(1,$msg_num,'Date');
					$field_list = $this->convert_date_array($old_list);
					if ($this->debug >= 2) { echo 'pop3: sort: field_list: '.serialize($field_list).'<br><br>'; }
					break;
				case SORTARRIVAL:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTARRIVAL<br>'; }
					// TEST
					if (!$this->msg2socket('LIST',"^\+ok",&$response))
					{
						$this->error();
					}
					$response = $this->read_port_glob('.');
					// expected array should NOT start at element 0, instead start it at element 1
					$field_list = $this->glob_to_array($response, False, ' ',True,1);
					if ($this->debug >= 2) { echo 'pop3: sort: field_list: '.serialize($field_list).'<br><br><br>'; }
					break;
				case SORTFROM:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTFROM<br>'; }
					$field_list = $this->fetch_header_element(1,$msg_num,'From');
					break;
				case SORTSUBJECT:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTSUBJECT<br>'; }
					$field_list = $this->fetch_header_element(1,$msg_num,'Subject');
					break;
				case SORTTO:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTTO<br>'; }
					$field_list = $this->fetch_header_element(1,$msg_num,'To');
					break;
				case SORTCC:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTCC<br>'; }
					$field_list = $this->fetch_header_element(1,$msg_num,'cc');
					break;
				case SORTSIZE:
					if ($this->debug >= 1) { echo 'pop3: sort: case SORTSIZE<br>'; }
					$field_list = $this->fetch_header_element(1,$msg_num,'Size');
					break;
			}
			@reset($field_list);
			if($criteria == SORTSUBJECT)
			{
				if(!$reverse)
				{
					uasort($field_list,array($this,"ssort_ascending"));
				}
				else
				{
					uasort($field_list,array($this,"ssort_decending"));
				}			
			}
			elseif(!$reverse)
			{
				asort($field_list);
			}
			else
			{
				arsort($field_list);
			}
			$return_array = Array();
			@reset($field_list);
			$i = 1;
			while(list($key,$value) = each($field_list))
			{
				$return_array[] = $key;
				//echo '('.$i.') Field: <b>'.$value."</b>\t\tMsg Num: <b>".$key."</b><br>\n";
				$i++;
			}
			@reset($return_array);
			if ($this->debug >= 2) { echo 'pop3: sort: return_array: '.serialize($return_array).'<br><br>'; }
			if ($this->debug >= 1) { echo 'pop3: Leaving sort<br>'; }
			return $return_array;
		}
		
		function fetch_header_element($start,$stop,$element)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering fetch_header_element<br>'; }
			for($i=$start;$i<=$stop;$i++)
			{
				if ($this->debug >= 1) { echo 'pop3: fetch_header_element: issue "TOP '.$i.' 0"<br>'; }
				if(!$this->write_port('TOP '.$i.' 0'))
				{
					$this->error();
				}
				$this->read_and_load('.');
				if($this->header[$element])
				{
					$field_element[$i] = $this->phpGW_quoted_printable_decode2($this->header[$element]);
					//echo $field_element[$i].' = '.$this->phpGW_quoted_printable_decode2($this->header[$element])."<br>\n";
					if ($this->debug >= 1) { echo 'pop3: fetch_header_element: field_element['.$i.']: '.$field_element[$i].'<br>'; }
				}
				else
				{
					$field_element[$i] = $this->phpGW_quoted_printable_decode2($this->header[strtoupper($element)]);
					//echo $field_element[$i].' = '.$this->phpGW_quoted_printable_decode2($this->header[strtoupper($element)])."<br>\n";
					if ($this->debug >= 1) { echo 'pop3: fetch_header_element: field_element['.$i.']: '.$field_element[$i].'<br>'; }
				}
				
			}
			if ($this->debug >= 1) { echo 'pop3: fetch_header_element: field_element: '.serialize($field_element).'<br><br><br>'; }
			if ($this->debug >= 1) { echo 'pop3: Leaving fetch_header_element<br>'; }
			return $field_element;
		}
	
		/**************************************************************************\
		*
		*	Message Structural Information
		*
		\**************************************************************************/
		/*!
		@function fetchstructure
		@abstract implements IMAP_FETCHSTRUCTURE
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num :  integer
		@param $flags : integer - FT_UID (not implimented)
		@result returns an instance of Class "msg_structure" is sucessful, False if error
		@discussion  basiclly a replacement for PHP's c-client logic which is missing if IMAP is not builtin
		@author Angles, (some sub-parts by Skeeter, Itzchak Rehberg, Joseph Engo)
		@access	public
		*/
		function fetchstructure($stream_notused,$msg_num,$flags="")
		{
			// outer control structure for the multi-pass functions
			if ($this->debug >= 1) { echo 'pop3: Entering fetchstructure<br>'; }
			
			// do we have a cached fetchstructure ?
			if (($this->msg_structure != '')
			&& ((int)$this->msg_structure_msgnum == (int)($msg_num)))
			{
				if ($this->debug >= 1) { echo 'pop3: fetchstructure: using cached msg_structure data<br>'; }
				if ($this->debug >= 1) { echo 'pop3: Leaving fetchstructure<br>'; }
				return $this->msg_structure;
			}
			// NO cached fetchstructure data - so make it
			// this will fill $this->msg_structure *TopLevel* only
			if ($this->fill_toplevel_fetchstructure($stream_notused,$msg_num,$flags) == False)
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving fetchstructure with Error from Toplevel<br>'; }
				return False;
			}
			// by now we have these created and stored (cached)
			// $this->header_array
			// $this->header_array_msgnum
			// $this->body_array
			// $this->body_array_msgnum
			// $this->msg_structure  (PARTIAL - INCOMPLETE, completed below)
			// $this->msg_structure_msgnum
			
			/*
			// ---  Create Sub-Parts FetchStructure Data  (if necessary)  ---
			// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
			$this->create_embeded_fetchstructure(&$this->msg_structure);
			// TEST: attempt 3rd level MIME discovery
			$level_3_loops = count($this->msg_structure->parts);
			for ($i=0; $i < $level_3_loops ;$i++)
			{
				if (count($this->msg_structure->parts[$i]) > 0)
				{
					// grap 3rd level embedded data (if any)
					if ($this->debug >= 2) { echo 'pop3: fetchstructure: attempting ['.$i.'] 3rd level parts embedded discovery<br>'; }
					// ---  Create 3rd Level Sub-Parts FetchStructure Data  (if necessary)  ---
					// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
					$this->create_embeded_fetchstructure(&$this->msg_structure->parts[$i]);
				}
				else
				{
					if ($this->debug >= 2) { echo 'pop3: fetchstructure: this ['.$i.'] 3rd level part is empty<br>'; }
				}
			}
			*/

			// ---  Create Sub-Parts FetchStructure Data  (if necessary)  ---
			// first call to $this->create_embeded_fetchstructure fills $this->msg_structure->parts IF there are any subparts
			// that is the 1st level of subparts if they exist, then we know we need to discover those subparts
			// if we have an "old school" very simple email, there will be NO 1st level of subparts
			// in that case the only body that exists is considered part #1
			// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
			$this->create_embeded_fetchstructure(&$this->msg_structure);
			
			// if there are subparts, we need to discover the details of those parts now
			// FOUR PASS ANALYSIS
			if (isset($this->msg_structure->parts))
			{
				for ($lev_1=0; $lev_1 < count($this->msg_structure->parts) ;$lev_1++)
				{				
					// grap 1st level embedded data (if any)
					if ($this->debug >= 2) { echo '<br>***<br>* * * * * * * * *<br>pop3: fetchstructure: attempting this->msg_structure->parts['.$lev_1.'] of ['.(string)(count($this->msg_structure->parts)-1).'] embedded parts discovery * * * * *<br>'; }
					// Create Sub-Parts FetchStructure Data  (if necessary)  ---
					// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
					$this->create_embeded_fetchstructure(&$this->msg_structure->parts[$lev_1]);
					
					// go deeper
					if (isset($this->msg_structure->parts[$lev_1]->parts))
					{
						for ($lev_2=0; $lev_2 < count($this->msg_structure->parts[$lev_1]->parts) ;$lev_2++)
						{
							// grap 2nd level embedded data (if any)
							if ($this->debug >= 2) { echo '<br>***<br>* * * * * * * * *<br>pop3: fetchstructure: attempting this->msg_structure->parts['.$lev_1.']->parts['.$lev_2.'] of ['.(string)(count($this->msg_structure->parts[$lev_1]->parts)-1).'] embedded parts discovery * * * * *<br>'; }
							// Create Sub-Parts FetchStructure Data  (if necessary)  ---
							// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
							$this->create_embeded_fetchstructure(&$this->msg_structure->parts[$lev_1]->parts[$lev_2]);
							
							// go deeper
							if (isset($this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts))
							{
								for ($lev_3=0; $lev_3 < count($this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts) ;$lev_3++)
								{
									// grap 3rd level embedded data (if any)
									if ($this->debug >= 2) { echo '<br>***<br>* * * * * * * * *<br>pop3: fetchstructure: attempting this->msg_structure->parts['.$lev_1.']->parts['.$lev_2.']->parts['.$lev_3.'] of ['.(string)(count($this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts)-1).'] embedded parts discovery * * * * *<br>'; }
									// Create 3rd Level Sub-Parts FetchStructure Data  (if necessary)  ---
									// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
									$this->create_embeded_fetchstructure(&$this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts[$lev_3]);
									
									// go deeper
									if (isset($this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts[$lev_3]->parts))
									{
										for ($lev_4=0; $lev_4 < count($this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts[$lev_3]->parts) ;$lev_4++)
										{
											// grap 3rd level embedded data (if any)
											if ($this->debug >= 2) { echo '<br>***<br>* * * * * * * * *<br>pop3: fetchstructure: attempting this->msg_structure->parts['.$lev_1.']->parts['.$lev_2.']->parts['.$lev_3.']->parts['.$lev_4.'] of ['.(string)(count($this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts[$lev_3]->parts)-1).'] embedded parts discovery * * * * *<br>'; }
											// Create Sub-Parts FetchStructure Data  (if necessary)  ---
											// NOTE: param to  create_embeded_fetchstructure  is a REFERENCE
											$this->create_embeded_fetchstructure(&$this->msg_structure->parts[$lev_1]->parts[$lev_2]->parts[$lev_3]->parts[$lev_4]);
										}
									}
									else
									{
										if ($this->debug >= 2) { echo '<br>***<br>pop3: fetchstructure: Traversal SKIP FOUTRH PASS level parts NOT SET<br>'; }
									}
								}
							}
							else
							{
								if ($this->debug >= 2) { echo '<br>***<br>pop3: fetchstructure: Traversal SKIP THIRD PASS level parts NOT SET<br>'; }
							}
						}
					}
					else
					{
						if ($this->debug >= 2) { echo '<br>***<br>pop3: fetchstructure: Traversal SKIP SECOND PASS level parts NOT SET<br>'; }
					}
				}
			}
			else
			{
				if ($this->debug >= 2) { echo 'pop3: fetchstructure: Traversal SKIP FIRST PARTS level parts NOT SET<br>'; }
			}
			
			if ($this->debug >= 2) { echo '<br>***<br>pop3: fetchstructure: * * * * * * Traversal OVER * * * * * * * * * * <br>'; }
			
			if ($this->debug >= 2)
			{
				echo '<br>dumping fetchstructure FINAL data: <br>';
				var_dump($this->msg_structure);
				echo '<br><br><br>';
			}
			
			if ($this->debug >= 1) { echo 'pop3: Leaving fetchstructure<br>'; }
			return $this->msg_structure;
		}

		/*!
		@function fill_toplevel_fetchstructure
		@abstract HELPER  function for fetchstructure / IMAP_FETCHSTRUCTURE
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num :  integer
		@param $flags : integer - FT_UID (not implimented)
		@result returns an instance of Class "msg_structure" is sucessful, False if error
		@discussion  basiclly a replacement for PHP's c-client logic which is missing if IMAP is not builtin
		@author Angles, (some sub-parts by Skeeter, Itzchak Rehberg, Joseph Engo)
		@access	private
		*/
		function fill_toplevel_fetchstructure($stream_notused,$msg_num,$flags="")
		{
			if ($this->debug >= 1) { echo 'pop3: Entering fill_toplevel_fetchstructure<br>'; }
			
			// --- Header Array  ---
			$header_array = $this->get_header_array($stream_notused,$msg_num,$flags);
			// --- Body Array  ---
			// do we have a cached body_array ?
			if ((count($this->body_array) > 0)
			&& ((int)$this->body_array_msgnum == (int)($msg_num)))
			{
				if ($this->debug >= 1) { echo 'pop3: fill_toplevel_fetchstructure: using cached body_array data<br>'; }
				$body_array = $this->body_array;
			}
			else
			{
				// NO cached data, get it
				// calling get_body automatically fills $this->body_array
				$this->get_body($stream_notused,$msg_num,$flags='',False);
				$body_array = $this->body_array;
				
				if ($this->debug >= 2)
				{
					echo 'pop3: fill_toplevel_fetchstructure: this->body_array DUMP<pre>';
					for ($i=0; $i < count($this->body_array) ;$i++)
					{
						echo '+['.$i.'] '.htmlspecialchars($this->body_array[$i])."\r\n";
					}
					echo '</pre><br><br>';
				}
			}
			if ($this->debug >= 2)
			{
				echo 'pop3: fill_toplevel_fetchstructure header_array iteration:<br>';
				for($i=0;$i < count($header_array);$i++)
				{
					echo '+'.htmlspecialchars($header_array[$i]).'<br>';
				}
			}
			if (!$header_array)
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving fill_toplevel_fetchstructure with error<br>'; }
				return False;
			}
			
			// ---  Create Class Base Fetchstructure Object  ---
			$this->msg_structure_msgnum = (int)$msg_num;
			$this->msg_structure = nil;
			$this->msg_structure = new msg_structure;
			$this->msg_structure->custom['top_level'] = True;
			$this->msg_structure->custom['parent_cookie'] = ''; // no parent at top level
			$this->msg_structure->custom['detect_state'] = 'out'; // not doing multi part detection on this yet
			// ---  Fill  Top Level Fetchstructure  ---
			// NOTE: first param to sub_get_structure is a REFERENCE
			$this->sub_get_structure(&$this->msg_structure,$header_array);
			
			// ---  Fill Any Missing Necessary Data  ---
			// --Bytes-- top level msg Size (bytes) is obtainable from the server
			if (!$this->msg2socket('LIST '.$msg_num,"^\+ok",&$response))
			{
				$this->error();
				if ($this->debug >= 1) { echo 'pop3: Leaving fill_toplevel_fetchstructure with error<br>'; }
				return False;
			}
			$list_response = explode(' ',$response);
			$this->msg_structure->bytes = (int)trim($list_response[2]);
			// --Lines-- php's fetchstructure seems to always include number of lines in it's msg_structure data
			// whether or not that data is present in the headers
			// top level # of lines is the # of lines in the entire body, we do not care about subparts here
			if ((!isset($this->msg_structure->lines))
			|| ((string)$this->msg_structure->lines == ''))
			{
				// earlier in this function we filled $this->body_array
				// the count of that array is the number of lines in the messages full body
				$this->msg_structure->lines = count($this->body_array);
			}
			// make sure some necessary information is present, use RFC defaults if necessary
			//if ((!isset($this->msg_structure->type))
			//|| ((string)$this->msg_structure->type == ''))
			//{
			//	// default type - RFC says is Text (unless you are dealing with an attachment)
			//	$this->msg_structure->type = $this->default_type(True);
			//}
			//if ((!isset($this->msg_structure->ifsubtype))
			//|| ($this->msg_structure->ifsubtype != True))
			//{
			//	// if no type we should NOT have a subtype, or else something is wrong
			//	$this->msg_structure->subtype = $this->default_subtype($this->msg_structure->type);
			//	$this->msg_structure->ifsubtype = True;
			//}
			if ((!isset($this->msg_structure->encoding))
			|| ((string)$this->msg_structure->encoding == ''))
			{
				$this->msg_structure->encoding = $this->default_encoding();
			}
			// unset any elements that have not been filled
			// NOTE: param to  unset_unfilled_fetchstructure  is a REFERENCE
			$this->unset_unfilled_fetchstructure(&$this->msg_structure);
			if ($this->debug >= 2)
			{
				echo '<br>dumping fill_toplevel_fetchstructure TOP-LEVEL data: <br>';
				var_dump($this->msg_structure);
				echo '<br><br><br>';
			}
			if ($this->debug >= 1) { echo 'pop3: Leaving fill_toplevel_fetchstructure<br>'; }
			return True;
		}

		/*!
		@function create_embeded_fetchstructure
		@abstract HELPER  function for fetchstructure / IMAP_FETCHSTRUCTURE
		@param $info : **REFERENCE** to a class "msg_structure" object
		@result NONE : this function DIRECTLY manipulates the referenced object
		@discussion  as implemented, reference is to some part of class var $this->msg_structure
		@author Angles
		@access	private
		*/
		function create_embeded_fetchstructure($info)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering create_embeded_fetchstructure<br>'; }
			// --- Do We Have SubParts To Discover  ---
			
			// Test 1: Detect Boundary Paramaters
			// initialize boundary holder
			$info->custom['my_cookie'] = '';
			if ($info->ifparameters)
			{
				// if we have a boundary paramater, then we have a multi-part message
				for ($x=0; $x < count($info->parameters) ;$x++)
				{
					$these_params = $info->parameters[$x];
					if (strtolower($these_params->attribute) == 'boundary')
					{
						// store it in custom["my_cookie"] for easy access
						$info->custom['my_cookie'] = $these_params->value;
						break;
					}
				}
			}
			// --- Handle Multi-Part MIME ---
			if (($info->custom['my_cookie'] != '')
			&& (count($info->parts) == 0))
			{
				// Boundry Based Multi-Part MIME In Need Of Discovered
				if ($this->debug >= 1) { echo 'pop3: create_embeded_fetchstructure: Discovery Needed for boundary param: '.$info->custom['my_cookie'].'<br>'; }
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: begin "mime loop", iterate thru body_array<br>'; }
				// look for any parts using this boundary/cookie
				for ($x=0; $x < count($this->body_array) ;$x++)
				{
					// search line by line thru the body
					$body_line = $this->body_array[$x];
					if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop ['.$x.']: '.htmlspecialchars($body_line).'<br>'; }
					if ((strstr($body_line,'--'.$info->custom['my_cookie']))
					&& (strpos($body_line,'--'.$info->custom['my_cookie']) == 0)
					// but NOT the final boundary
					&& (!strstr($body_line,'--'.$info->custom['my_cookie'].'--')))
					{
						// we found a body part
						
						// BEGINNING of a new part is ALSO the ENDING of a prevoius part
						// if we were in the state of "IN" on that prevoius part (if any previous part exists)
						$cur_part_idx = count($info->parts) - 1;
						if ((isset($info->parts[$cur_part_idx]))
						&& ($info->parts[$cur_part_idx]->custom['detect_state'] == 'in'))
						{
							// we were already "in" so we found ENDING data
							// for the previous part, (as well as BEGINING data for the next part)
							// --Bytes-- we have a running total of byte size, but in testing against UWash, I was over by 2 bytes, so fix that
							$info->parts[$cur_part_idx]->bytes = $info->parts[$cur_part_idx]->bytes - 2;
							$info->parts[$cur_part_idx]->custom['part_end'] = $x-1;
							// --Lines-- we know beginning line and ending line, so calculate # lines for this part
							$info->parts[$cur_part_idx]->lines = (int)$info->parts[$cur_part_idx]->custom['part_end'] - (int)$info->parts[$cur_part_idx]->custom['part_start'];
							if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop: current part end at ['.(string)($x-1).'] byte cumula: ['.$info->parts[$cur_part_idx]->bytes.'] lines: ['.$info->parts[$cur_part_idx]->lines.']<br>'; }
							// this individual part has completed discovery, it os now "OUT"
							$info->parts[$cur_part_idx]->custom['detect_state'] = 'out';
							// we are DONE with this part for now 
							// unset any unfilled elements
							// NOTE: param to  unset_unfilled_fetchstructure  is a REFERENCE
							$this->unset_unfilled_fetchstructure(&$info->parts[$cur_part_idx]);
						}
						// so now deal with this NEW part we just discovered
						if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop: begin part discovery<br>'; }
						// Create New Sub Part Object
						$new_part_idx = count($info->parts);
						$info->parts[$new_part_idx] = new msg_structure;
						$info->parts[$new_part_idx]->bytes = 0;
						$info->parts[$new_part_idx]->custom['top_level'] = False;
						$info->parts[$new_part_idx]->custom['parent_cookie'] = $info->custom['my_cookie'];
						// state info: we are now "IN" doing multi part detection on this part
						$info->parts[$new_part_idx]->custom['detect_state'] = 'in';
						// get this part's headers
						// start 1 line after the cookie, and end with the first blank line
						// part header starts next line after the boundary/cookie
						$info->parts[$new_part_idx]->custom['header_start'] = $x+1;
						$part_header_blob = '';
						for ($y=$x+1; $y < count($this->body_array) ;$y++)
						{
							if ($this->body_array[$y] != '')
							{
								// grap this part header line
								$part_header_blob .= $this->body_array[$y]."\r\n";
								if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop: part part_header_blob line['.$y.']: '.$this->body_array[$y].'<br>'; }
							}
							else
							{
								// reached end of this part's headers
								// headers actually ended 1 line above this blank line
								$info->parts[$new_part_idx]->custom['header_end'] = (int)($y-1);
								// break out of this sub loop
								break;
							}
						}
						// get rid of that last CRLF
						$part_header_blob = trim($part_header_blob);
						// RFC2822 "unfold" the grabbed header
						// unfold any unfolded headers - using CR_LF_TAB as rfc822 "whitespace"
						$part_header_blob = str_replace("\r\n\t"," ",$part_header_blob);
						// unfold any unfolded headers - using CR_LF_SPACE as rfc822 "whitespace"
						$part_header_blob = str_replace("\r\n "," ",$part_header_blob);
						// make the header blob into an array of strings, one array element per header line, throw away blank lines
						$part_header_array = Array();
						$part_header_array = $this->glob_to_array($part_header_blob, False, '', True);
						if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop: part_header_array:'.serialize($part_header_array).'<br>'; }
						// since we just passed the headers, and this is NOT a final boundary
						// this MUST be a start point for the next part
						$info->parts[$new_part_idx]->custom['part_start'] = (int)($y+1);
						// fill the conventional info on this fetchstructure sub-part
						// NOTE: first param to sub_get_structure is a REFERENCE
						$this->sub_get_structure(&$info->parts[$new_part_idx],$part_header_array);
						// ADVANCE INDEX $x TO AFTER WHAT WE'VE ALREADY LOOKED AT
						if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop: advance x from ['.$x.'] to ['.$y.']<br>'; }
						$x = $y;
					}
					elseif ((strstr($body_line,'--'.$info->custom['my_cookie'].'--'))
					&& (strpos($body_line,'--'.$info->custom['my_cookie'].'--') == 0))
					{
						// we found the CLOSING BOUNDARY
						$cur_part_idx = count($info->parts) - 1;
						$info->parts[$cur_part_idx]->custom['part_end'] = $x-1;
						// --Bytes-- we have a running total of byte size, but in testing against UWash, I was over by 2 bytes, so fix that
						$info->parts[$cur_part_idx]->bytes = $info->parts[$cur_part_idx]->bytes - 2;
						// --Lines-- we know beginning line and ending line, so calculate # lines for this part
						$info->parts[$cur_part_idx]->lines = $info->parts[$cur_part_idx]->custom['part_end'] - $info->parts[$cur_part_idx]->custom['part_start'];
						$info->parts[$cur_part_idx]->custom['detect_state'] = 'out';
						// we are DONE with this part for now 
						if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: mime loop: final boundary at ['.(string)($x-1).'] byte cumula: ['.$info->parts[$cur_part_idx]->bytes.'] lines: ['.$info->parts[$cur_part_idx]->lines.']<br>'; }
						// unset any unfilled elements
						// NOTE: param to  unset_unfilled_fetchstructure  is a REFERENCE
						$this->unset_unfilled_fetchstructure(&$info->parts[$cur_part_idx]);
					}
					else
					{
						// running byte size of this part (if any)
						$cur_part_idx = count($info->parts) - 1;
						if ((isset($info->parts[$cur_part_idx]))
						&& ($info->parts[$cur_part_idx]->custom['detect_state'] == 'in'))
						{
							// previous count
							$prev_bytes = $info->parts[$cur_part_idx]->bytes;
							// add new count, +2 for the \r\n that will end the line when we feed it to the client
							$add_bytes = strlen($body_line) + 2;
							$info->parts[$cur_part_idx]->bytes = $prev_bytes + $add_bytes;
						}
					}
				}
			}
			// do we have an encapsulated (non-boundry based) Embedded Part
			elseif ( (isset($info->type))
			&& ($info->type == TYPEMESSAGE)
			&& (isset($info->subtype))
			&& (strtolower($info->subtype) == 'rfc822')
			&& (count($info->parts) == 0))
			{
				// Encapsulated "message/rfc822" MIME Part In Need Of Discovered
				if ($this->debug >= 1) { echo 'pop3: create_embeded_fetchstructure: Discovery Needed for Encapsulated "message/rfc822" MIME Part<br>'; }
				$range_start = $info->custom['part_start'];
				$range_end = $info->custom['part_end'];
				// is this range data valid
				if ( (!isset($info->custom['part_start']))
				|| (!isset($info->custom['part_end']))
				|| ($info->custom['part_end'] <= $info->custom['part_start']))
				{
					if ($this->debug >= 1) { echo 'pop3: Leaving create_embeded_fetchstructure with Error in "message/rfc2822" range<br>'; }
					return False;
				}
				
				// note that below we will iterate thru this range
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: "mime loop", will iterate thru parents body_array range ['.$range_start.'] to ['.$range_end.']<br>'; }
				
				// encapsulated is not that tricky, we must so this
				// 1) Create New Sub Part Object
				$enc_part_idx = count($info->parts);
				$info->parts[$enc_part_idx] = new msg_structure;
				$info->parts[$enc_part_idx]->bytes = 0;
				$info->parts[$enc_part_idx]->custom['top_level'] = False;
				// ??? encapsulated part's parent does not have a boundary ???
				$info->parts[$enc_part_idx]->custom['parent_cookie'] = '';
				
				// 2) Get This Part's Headers
				// encapsulated headers begin immediately in the encapsulated part
				$info->parts[$enc_part_idx]->custom['header_start'] = $range_start;
				// encapsulated headers end with the 1st blank line
				$part_header_blob = '';
				for ($y=$range_start; $y < $range_end+1 ;$y++)
				{
					if ($this->body_array[$y] != '')
					{
						// grap this part header line
						$part_header_blob .= $this->body_array[$y]."\r\n";
						if ($this->debug >= 2) { echo 'pop3: enc mime loop: part part_header_blob line['.$y.']: '.htmlspecialchars($this->body_array[$y]).'<br>'; }
					}
					else
					{
						// reached end of this part's headers
						// headers actually ended 1 line above this blank line
						$info->parts[$enc_part_idx]->custom['header_end'] = (int)($y-1);
						// break out of this sub loop
						break;
					}
				}
				// get rid of that last CRLF
				$part_header_blob = trim($part_header_blob);
				// RFC2822 "unfold" the grabbed header
				// unfold any unfolded headers - using CR_LF_TAB as rfc822 "whitespace"
				$part_header_blob = str_replace("\r\n\t"," ",$part_header_blob);
				// unfold any unfolded headers - using CR_LF_SPACE as rfc822 "whitespace"
				$part_header_blob = str_replace("\r\n "," ",$part_header_blob);
				// make the header blob into an array of strings, one array element per header line, throw away blank lines
				$part_header_array = Array();
				$part_header_array = $this->glob_to_array($part_header_blob, False, '', True);
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: enc mime loop: part_header_array:'.serialize($part_header_array).'<br>'; }				
				
				// 2) Feed these Headers thru "sub_get_structure"
				// fill the conventional info on this fetchstructure sub-part
				// NOTE: first param to sub_get_structure is a REFERENCE
				$this->sub_get_structure(&$info->parts[$enc_part_idx],$part_header_array);
				
				// ==  CONTROVESTIAL DEFAULT UWASH VALUE ASSIGNMENTS  ==
				// close study of UWash IMAP indicates the an immediate child message part of a RFC822 package will:
				// (A) SUBTYPE
				// will get a default value of "plain" from UWash imap WHEN NO TYPE was specified for this part
				// I assume if a type was specified then UWash would not do this
				// in fact UWash *may* fill a default subtype if a type IS specified (it's in the UWash code)
				// so I will imitate UWash IMAP and assign a subtype of "plain" when NO type is specified
				if ((!isset($info->parts[$enc_part_idx]->subtype))
				|| ((string)$info->parts[$enc_part_idx]->subtype == ''))
				{
					if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: enc mime loop: CONTROVERSIAL uwash imitation: adding subtype "plain" to immediate RFC822 child part, none was specified<br>'; }
					$info->parts[$enc_part_idx]->ifsubtype = True;
					$info->parts[$enc_part_idx]->subtype = 'plain';
				}
				// (B) PARAM "charset=US-ASCII" 
				// gets added if no charset is specified for this immediate RFC822 child
				// I know it hurts, but I'm just copying UWash !!!
				$found_charset = False;
				for ($ux=0; $ux < count($info->parts[$enc_part_idx]->parameters) ;$ux++)
				{
					if (stristr($info->parts[$enc_part_idx]->parameters[$new_idx]->attribute,'charset'))
					{
						$found_charset = True;
						break;
					}
				}
				// do that crappy adding of charset param if necessary
				if ($found_charset == False)
				{
					if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: enc mime loop: CONTROVERSIAL uwash imitation: adding param "charset=US-ASCII" to immediate RFC822 child part, none was specified<br>'; }
					$new_idx = count($info->parts[$enc_part_idx]->parameters);
					$info->parts[$enc_part_idx]->parameters[$new_idx] = new msg_params('charset','US-ASCII');
					$info->parts[$enc_part_idx]->ifparameters = true;
				}
				// ends CONTROVESTIAL uwash inmitation code
				
				// 3) fill Part Start and Part End
				// encapsulated body STARTS at the first line after the blank line header sep above
				$info->parts[$enc_part_idx]->custom['part_start'] = (int)($y+1);
				// encapsulated body ENDS at the end of the partnts range
				$info->parts[$enc_part_idx]->custom['part_end'] = $range_end;

				// 4) calculate byte size and # of lines of the content within this parts start and end
				$my_start = $info->parts[$enc_part_idx]->custom['part_start'];
				$my_end = $info->parts[$enc_part_idx]->custom['part_end'];
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: enc mime loop: this body range ['.$my_start.'] to ['.$my_end.']<br>'; }
				for ($x=$my_start; $x < $my_end+1 ;$x++)
				{
					// running byte size of this part
					$body_line = $this->body_array[$x];
					if ($this->debug >= 2) { echo 'pop3: encap mime size loop ['.$x.']: '.htmlspecialchars($body_line).'<br>'; }
					// prevoius count
					$prev_bytes = $info->parts[$enc_part_idx]->bytes;
					// add new count, +2 for the \r\n that will end the line when we feed it to the client
					$add_bytes = strlen($body_line) + 2;
					$info->parts[$enc_part_idx]->bytes = $prev_bytes + $add_bytes;
				}
				// --Bytes-- we made a running total of byte size, but in testing against UWash, I was over by 2 bytes, so fix that
				$info->parts[$enc_part_idx]->bytes = $info->parts[$enc_part_idx]->bytes - 2;
				// --Lines-- we know beginning line and ending line, so calculate # lines for this part
				$info->parts[$enc_part_idx]->lines = $my_end - $my_start;
				// we're done with the loop so the bytes have been calculated in that loop
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: this part range byte size ['.$info->parts[$enc_part_idx]->bytes.'] lines: ['.$info->parts[$enc_part_idx]->lines.']<br>'; }
			}
			// no embedded parts, why not?
			elseif ( (isset($info->type))
			&& ($info->type == TYPEMESSAGE)
			&& (isset($info->subtype))
			&& (strtolower($info->subtype) == 'rfc822')
			&& (count($info->parts) == 0))
			{
				// do NOTHING - this level has ALREADY been filled
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: feed info encapsulated "message/rfc822" ALREADY filled<br>'; }
				return False;
			}
			elseif ($info->custom['my_cookie'] == '')
			{
				// do NOTHING - this is NOT multipart
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: feed info not multipart<br>'; }
				if ($this->debug >= 2)
				{
					echo 'pop3: create_embeded_fetchstructure: feed info not multipart DUMP EXAMINE:<br>';
					var_dump($info);
					echo '<br><br>';
				}
				return False;
			}
			elseif (($info->custom['my_cookie'] != '')
			&& (count($info->parts) > 0))
			{
				// do NOTHING - this level has ALREADY been filled
				if ($this->debug >= 2) { echo 'pop3: create_embeded_fetchstructure: feed info multipart ALREADY filled<br>'; }
				return False;
			}
			else
			{
				if ($this->debug >= 1) { echo 'pop3: create_embeded_fetchstructure: * * no mans land * *<br>'; }			
			}
			//if ($this->debug >= 2)
			//{
			//	echo '<br>dumping create_embeded_fetchstructure return info: <br>';
			//	var_dump($info);
			//	echo '<br><br>';
			//}
			if ($this->debug >= 1) { echo 'pop3: Leaving create_embeded_fetchstructure<br>'; }
			return True;
		}
		
		/*!
		@function sub_get_structure
		@abstract HELPER  function for fetchstructure / IMAP_FETCHSTRUCTURE
		@param $info : **REFERENCE** to a class "msg_structure" object
		@param $header_array : array of headers to process
		@result NONE : this function DIRECTLY manipulates the referenced object
		@discussion  as implemented, reference is to some part of class var $this->msg_structure
		@author Angles, Itzchak Rehberg, Joseph Engo
		@access	private
		*/
		function sub_get_structure($info,$header_array)
		{
			// set debug flag
			if ($this->debug >= 2)
			{
				$debug_mime = True;			
			}
			else
			{
				$debug_mime = False;
			}
			
			if ($this->debug >= 1) { echo 'pop3: Entering sub_get_structure<br>'; }
			/*
			// initialize the structure
			$info->custom['top_level'] = $extra_args['top_level'];
			$info->custom['detect_state'] = 'in'; // = 'out';
			$info->custom['parent_cookie'] = '';
			$info->custom['my_cookie'] = ''; // for recursive sub-parts
			$info->custom['my_header_array'] = '';
			$info->custom['header_start'] = ''; // this parts MIME headers start index in body array
			$info->custom['header_end'] = ''; // this parts MIME headers ending index in body array
			$info->custom['part_start'] = $extra_args['part_start'];
			$info->custom['part_end'] = ''; // unknown ending point at this stage, we just got past it's headers
			*/
			// FILL THE DATA
			for ($i=0; $i < count($header_array) ;$i++)
			{
				$pos = strpos($header_array[$i],' ');
				//if ($debug_mime) { echo 'header_array['.$i.']: '.$header_array[$i].'<br>'; }
				if (is_int($pos) && ($pos==0))
				{
					continue;
				}
				$keyword = strtolower(substr($header_array[$i],0,$pos));
				$content = trim(substr($header_array[$i],$pos+1));
				if ($debug_mime)
				{
					//echo 'pos: '.$pos.'<br>';
					echo 'pop3: sub_get_structure: keyword: ['.htmlspecialchars($keyword).']'
						.' content: ['.htmlspecialchars($content).']<br>';
				}
				switch ($keyword)
				{
				  case 'content-type:' :
					// this will fill type and (hopefully) subtype
					// NOTE: first param to  parse_type_subtype  is a REFERENCE
					$this->parse_type_subtype(&$info,$content);
					// ALSO, typically Paramaters are on this line as well
					$pos_param = strpos($content,';');
					if ($pos_param > 0)
					{
						if ($this->debug >= 2) { echo 'pop3: sub_get_structure: apparent params exist in content ['.$content.']<br>'; }
						// feed the whole param line into this function
						$content = substr($content,$pos_param+1);
						if ($this->debug >= 2) { echo 'pop3: sub_get_structure: calling parse_msg_params, feeding content ['.$content.']<br>'; }
						// False = this is NOT a disposition param, this is the more common regular param
						// NOTE: first param to  parse_msg_params  is a REFERENCE
						$this->parse_msg_params(&$info,$content,False);
					}
					break;
				  case 'content-transfer-encoding:' :
					$info->encoding = $this->encoding_str_to_int($content);
					break;
				  case 'content-description:' :
					$info->description   = $content;
					//$i = $this->more_info($msg_part,$i,&$info,"description");
					$info->ifdescription = true;
					break;
				  case 'content-disposition:' :
					// disposition MAY have Paramaters on this line as well
					$pos_param = strpos($content,';');
					if ($pos_param > 0)
					{
						$content = substr($content,0,$pos_param);
					}
					$info->disposition = $content;
					$info->ifdisposition = True;
					// parse paramaters if any
					if ($pos_param > 0)
					{
						// feed the whole param line into this function
						$content = substr($content,$pos_param+1);
						// NOTE: first param to  parse_msg_params  is a REFERENCE
						$this->parse_msg_params(&$info,$content,False);
					}
					break;
				  case 'content-identifier:' :
				  case 'content-id:' :
				  case 'message-id:' :
					if ((strstr($content, '<'))
					&& (strstr($content, '>')))
					{
						$content = str_replace('<','',$content);
						$content = str_replace('>','',$content);
					}
					//$i = $this->more_info($msg_part,$i,&$info,"id");
					$info->id   = $content;
					$info->ifid = true;
					break;
				  case 'content-length:' :
					$info->bytes = (int)$content;
					break;
				  case 'content-disposition:' :
					$info->disposition   = $content;
					//$i = $this->more_info($msg_part,$i,&$info,"disposition");
					$info->ifdisposition = true;
					break;
				  case 'lines:' :
					$info->lines = (int)$content;
					break;
				  /*
				  case 'mime-version:' :
					$new_idx = count($info->parameters);
					$info->parameters[$new_idx] = new msg_params("Mime-Version",$content);
					$info->ifparameters = true;
					break;
				  */
				  default : break;
				}
			}

			if ($this->debug >= 2)
			{
				echo 'pop3: sub_get_structure: info->encoding ['.(string)$info->encoding.'] (if empty here it will get a default value later)<br>';
			}
			if ($this->debug >= 1) { echo 'pop3: Leaving sub_get_structure<br>'; }
			return $info;
		}
		
		/*!
		@function unset_unfilled_fetchstructure
		@abstract HELPER  function for fetchstructure / IMAP_FETCHSTRUCTURE
		@param $info : **REFERENCE** to a class "msg_structure" object
		@result NONE : this function DIRECTLY manipulates the referenced object
		@discussion  as implemented, reference is to some part of class var $this->msg_structure
		unsets any unfilled elements of the referenced part in the fetchstructure object 
		to mimic PHP's return structure
		@author Angles
		@access	private
		*/
		function unset_unfilled_fetchstructure($info)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering unset_unfilled_fetchstructure<br>'; }
			// unset any unfilled elements, ALWAYS leave parts and custom
			if ((string)$info->type == '')
			{
				$info->type = NIL;
				unset($info->type);
			}
			if ((string)$info->encoding == '')
			{
				$info->encoding = NIL;
				unset($info->encoding);
			}
			//$info->ifsubtype = False;
			if ((string)$info->subtype == '')
			{
				$info->subtype = NIL;
				unset($info->subtype);
			}
			//$info->ifdescription = False;
			if ((string)$info->description == '')
			{
				$info->description = NIL;
				unset($info->description);
			}
			//$info->ifid = False;
			if ((string)$info->id == '')
			{
				$info->id = NIL;
				unset($info->id);
			}
			if ((string)$info->lines == '')
			{
				$info->lines = NIL;
				unset($info->lines);
			}
			if ((string)$info->bytes == '')
			{
				$info->bytes = NIL;
				unset($info->bytes);
			}
			//$info->ifdisposition = False;
			if ((string)$info->disposition == '')
			{
				$info->disposition = NIL;
				unset($info->disposition);
			}
			//$info->ifdparameters = False;
			if (count($info->dparameters) == 0)
			{
				$info->dparameters = NIL;
				unset($info->dparameters);
			}
			//$info->ifparameters = False;
			if (count($info->parameters) == 0)
			{
				$info->parameters = NIL;
				unset($info->parameters);
			}
			//$info->custom = array();
			//$info->parts = array();
			if ($this->debug >= 1) { echo 'pop3: Leaving unset_unfilled_fetchstructure<br>'; }
		}
		
		/*!
		@function parse_type_subtype
		@abstract HELPER  function for sub_get_structure / IMAP_FETCHSTRUCTURE
		@param $info : **REFERENCE** to a class "msg_structure" object
		@param $content : the text associated with the "content-type:" header
		@result NONE : this function DIRECTLY manipulates the referenced object
		@discussion  as implemented, reference is to some part of class var $this->msg_structure
		parses "content-type:" header into fetchstructure data ->type and ->subtype
		@author Angles, Itzchak Rehberg, Joseph Engo
		@access	private
		*/
		function parse_type_subtype($info,$content)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering parse_type_subtype<br>'; }
			// used by pop_fetchstructure only
			// get rid of any other params that might be here
			$pos = strpos($content,';');
			if ($pos > 0)
			{
				$content = substr($content,0,$pos);
			}
			// split type from subtype
			$pos = strpos($content,'/');
			if ($pos > 0)
			{
				$prim_type = strtolower(substr($content,0,$pos));
				$info->subtype = strtolower(substr($content,$pos+1));
				$info->ifsubtype = True;
			}
			else
			{
				$prim_type = strtolower($content);
			}
			if ($this->debug >= 2) { echo 'pop3: parse_type_subtype: prim_type: '.$prim_type.'<br>'; }
			$info->type = $this->type_str_to_int($prim_type);
			if ($info->ifsubtype == False)
			{
				// use RFC default for subtype
				$info->subtype = $this->default_subtype($info->type);
				$info->ifsubtype = True;
			}
			if ($this->debug >= 2)
			{
				echo 'pop3: parse_type_subtype: info->type ['.$info->type.'] aka "'.$this->type_int_to_str($info->type).'"<br>';
				echo 'pop3: parse_type_subtype: info->ifsubtype ['.$info->ifsubtype.']<br>';
				echo 'pop3: parse_type_subtype: info->subtype "'.$info->subtype.'"<br>';
			}
			if ($this->debug >= 1) { echo 'pop3: Leaving parse_type_subtype<br>'; }
		}
		
		/*!
		@function parse_msg_params
		@abstract HELPER  function for sub_get_structure / IMAP_FETCHSTRUCTURE
		@param $info : **REFERENCE** to a class "msg_structure" object
		@param $content : string from the "content-type:" or "content-disposition:" header
		@param $is_disposition_param : boolean : true if parsing "content-disposition:" header string
		tells this function to fill info->dparameters instead of the more common info->parameters
		@result NONE : this function DIRECTLY manipulates the referenced object
		@discussion  as implemented, reference is to some part of class var $this->msg_structure
		parses "content-type:" header string into fetchstructure data info->parameters
		 or "content-disposition:" header string into fetchstructure data info->dparameters
		@author Angles, Itzchak Rehberg, Joseph Engo
		@access	private
		*/
		function parse_msg_params($info,$content,$is_disposition_param=False)
		{
			if ($this->debug >= 2) {
				//echo 'pop3: *in parse_msg_params<br>';
				echo 'pop3: *in parse_msg_params: content ['.$content.']<br>';
				echo 'pop3: *in parse_msg_params: is_disposition_param ['.(string)$is_disposition_param.']<br>';
			}
			// bogus data detection
			if (trim($content) == '')
			{
				// we need to exit this function, we were fed bogus (empty) $content
				// this function does not actually return anything
				// instead it directly manipulates the referenced $info param
				// thus we can call "return" to exit the function with no effect on data flow
				return;
			}
			// seperate param strings into an string list array
			$param_list = Array();
			if (strstr($content, ';'))
			{
				$param_list = explode(';',$content);
			}
			else
			{
				$param_list[0] = $content;
			}
			// process each param string
			for ($x=0; $x < count($param_list) ;$x++)
			{
				$pos_token = strpos($param_list[$x],"=");
				if ($pos_token == 0)
				{
					// error - not a regular param=value pair
					$param_attrib = trim($param_list[$x]);
					$param_value = 'UNKNOWN_PARAM_VALUE';
				}
				else
				{
					$param_attrib = trim(substr($param_list[$x],0,$pos_token));
					$param_value = trim(substr($param_list[$x],$pos_token+1));
					$param_value = str_replace("\"","",$param_value);
				}
				// are these typical message paramaters or the more rare "disposition" params
				if ($is_disposition_param == False)
				{
					// typical msg params
					$new_idx = count($info->parameters);
					$info->parameters[$new_idx] = new msg_params($param_attrib,$param_value);
					$info->ifparameters = true;
				}
				else
				{
					// content-disposition paramaters are pretty rare
					$new_idx = count($info->dparameters);
					$info->dparameters[$new_idx] = new msg_params($param_attrib,$param_value);
					$info->ifparameters = true;
				}
			}
		}

		function type_str_to_int($type_str)
		{
			// fallback value
			$type_int = TYPEOTHER;
			switch ($type_str)
			{
				case 'text'		: $type_int = TYPETEXT; break;
				case 'multipart'	: $type_int = TYPEMULTIPART; break;
				case 'message'		: $type_int = TYPEMESSAGE; break;
				case 'application'	: $type_int = TYPEAPPLICATION; break;
				case 'audio'		: $type_int = TYPEAUDIO; break;
				case 'image'		: $type_int = TYPEIMAGE; break;
				case 'video'		: $type_int = TYPEVIDEO; break;
				// this causes errors under php 4.0.6, but used to work before that, I think
				//defaut			: $type_int = TYPEOTHER; break;
			}
			return $type_int;
		}

		function default_type($probably_text=True)
		{
			if ($probably_text)
			{
				return TYPETEXT;
			}
			else
			{
				return TYPEAPPLICATION;
			}
		}
	
		function default_subtype($type_int=TYPEAPPLICATION)
		{
			// APPLICATION/OCTET-STREAM is the default when NO info is available
			switch ($type_int)
			{
				case TYPETEXT		: return 'plain'; break;
				case TYPEMULTIPART	: return 'mixed'; break;
				case TYPEMESSAGE		: return 'rfc822'; break;
				case TYPEAPPLICATION	: return 'octet-stream'; break;
				case TYPEAUDIO		: return 'basic'; break;
				default			: return 'unknown'; break;
			}
		}
	
		function default_encoding()
		{
			return ENC7BIT;
		}
	
		// MAY BE OBSOLETED
		function more_info($header,$i,$info,$infokey)
		{
			// used by pop_fetchstructure only
			do
			{
				$pos = strpos($header[$i+1],' ');
				if (is_int($pos) && !$pos)
				{
					$i++;
					$info->$infokey .= ltrim($header[$i]);
				}
			}
			while (is_int($pos) && !$pos);
			return $i;
		}
	
		function encoding_str_to_int($encoding_str)
		{
			switch (strtolower($encoding_str))
			{
				case '7bit'		: $encoding_int = ENC7BIT; break;
				case '8bit'		: $encoding_int = ENC8BIT; break;
				case 'binary'		: $encoding_int = ENCBINARY; break;
				case 'base64'		: $encoding_int = ENCBASE64; break;
				case 'quoted-printable' : $encoding_int = ENCQUOTEDPRINTABLE; break;
				case 'other'		: $encoding_int = ENCOTHER; break;
				case 'uu'		: $encoding_int = ENCUU; break;
				default			: $encoding_int = ENCOTHER; break;
			}
			return $encoding_int;
		}
	
		function size_msg($stream_notused,$msg_num)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering size_msg<br>'; }
			if (!$this->msg2socket('LIST '.$msg_num,"^\+ok",&$response))
			{
				$this->error();
				return False;
			}
			$list_response = explode(' ',$response);
			$return_size = trim($list_response[2]);
			$return_size = (int)$return_size * 1;
			if ($this->debug >= 1) { echo 'pop3: size_msg: '.$return_size.'<br>'; }
			if ($this->debug >= 1) { echo 'pop3: Leaving size_msg<br>'; }
			return $return_size;
		}
	
		/**************************************************************************\
		*	Message Envelope (Header Info) Data
		\**************************************************************************/
		/*!
		@function header
		@abstract implements IMAP_HEADER	(alias to IMAP_HEADERINFO)
		@abstract implements IMAP_HEADERINFO
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : intefer
		@param $fromlength ?
		@param $tolength ?
		@param $defaulthost ?
		@result returns an instance of Class "hdr_info_envelope", or returns False on error
		@discussion  ?
		@author Angles, Skeeter, Itzchak Rehberg, Joseph Engo
		@access	public
		*/
		function header($stream_notused,$msg_num,$fromlength='',$tolength='',$defaulthost='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering header<br>'; }
			$info = new hdr_info_envelope;
			$info->Size = $this->size_msg($stream_notused,$msg_num);
			$info->size = $info->Size;
			$header_array = $this->get_header_array($stream_notused,$msg_num);
			if (!$header_array)
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving header with error<br>'; }
				return False;
			}
			for ($i=0; $i < count($header_array); $i++)
			{
				// POP3 ONLY !!! - POP3 considers ALL messages as "unseen" and/or "recent"
				// because POP3 does not retain such info as seen or unseen
				// I *may* comment that out because I find this annoying
				//$info->Unseen = 'U';
				$pos = strpos($header_array[$i],' ');
				if (is_int($pos) && !$pos)
				{
					continue;
				}
				$keyword = strtolower(substr($header_array[$i],0,$pos));
				$content = trim(substr($header_array[$i],$pos+1));
				switch ($keyword)
				{
					case 'date:'	:
					  $info->date  = $content;
					  $info->udate = $this->make_udate($content);
					  break;
					case 'subject'	:
					case 'subject:'	:
					  $pos = strpos($header_array[$i+1],' ');
					  if (is_int($pos) && !$pos)
					  {
						$i++; $content .= chop($header_array[$i]);
					  }
					  $info->subject = htmlspecialchars($content);
					  $info->Subject = htmlspecialchars($content);
					  break;
					case 'in-reply-to:' :
					  $info->in_reply_to = htmlspecialchars($content);
					  break;
					case 'message-id'  :
					case 'message-id:' :
					  $info->message_id = htmlspecialchars($content);
					  break;
					case 'newsgroups:' :
					  $info->newsgroups = htmlspecialchars($content);
					  break;
					case 'followup-to:' :
					  $info->follow_up_to = htmlspecialchars($content);
					  break;
					case 'references:' :
					  $info->references = htmlspecialchars($content);
					  break;
					case 'to'	:
					case 'to:'	: 
					  // following two lines need to be put into a loop!
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->to   = $this->get_addr_details('to',$content,&$header_array,&$i);
					  break;
					case 'from'	:
					case 'from:'	:
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->from = $this->get_addr_details('from',$content,&$header_array,&$i);
					  break;
					case 'cc'	:
					case 'cc:'	:
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->cc   = $this->get_addr_details('cc',$content,&$header_array,&$i);
					  break;
					case 'bcc'	:
					case 'bcc:'	:
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->bcc  = $this->get_addr_details('bcc',$content,&$header_array,&$i);
					  break;
					case 'reply-to'	:
					case 'reply-to:'	:
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->reply_to = $this->get_addr_details('reply_to',$content,&$header_array,&$i);
					  break;
					case 'sender'	:
					case 'sender:'	:
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->sender = $this->get_addr_details('sender',$content,&$header_array,&$i);
					  break;
					case 'return-path'	:
					case 'return-path:'	:
					  // NOTE: 3rd and 4th params to  get_addr_details  are REFERENCES
					  $info->return_path = $this->get_addr_details('return_path',$content,&$header_array,&$i);
					  break;
					default	:
					  break;
				}
			}
			if ($this->debug >= 1)
			{
				echo 'pop3: Leaving header<br>';
			}
			return $info;
		}

		/*!
		@function get_addr_details
		@abstract HELPER function to header / IMAP_HEADER
		@param ?
		@param ?
		@param ?
		@param ?
		@result ?
		@discussion ?
		@author Itzchak Rehberg, Joseph Engo
		@access	private
		*/
		function get_addr_details($people,$address,$header,$count)
		{
			if ($this->debug >= 1) { echo 'pop3: Entering get_addr_details<br>'; }
			if (!trim($address))
			{
				return False;
			}
			// check wether this header info is split to multiple lines
			$done = false;
			do
			{
				$pos = strpos($header[$count+1],' ');
				if (is_int($pos) && !$pos)
				{
					$count++;
					$address .= chop($header[$count]);
				}
				else
				{
					$done = true;
				}
			}
			while (!$done);
			$temp = $people . 'address';
			
			if ($people == 'return_path')
			{
				$this->$people = htmlspecialchars($address);
			}
			else
			{
				$this->$temp = htmlspecialchars($address);
			}
			
			for ($i=0,$pos=1;$pos;$i++)
			{
				//$addr_details = new msg_aka;
				$addr_details = new address;
				$pos = strpos($address,'<');
				$pos3 = strpos($address,'(');
				if (is_int($pos))
				{
					$pos2 = strpos($address,'>');
					if ($pos2 == $pos+1)
					{
						$addr_details->adl = 'nobody@nowhere';
					}
					else
					{
						$addr_details->adl = substr($address,$pos+1,$pos2 - $pos -1);
					}
					if ($pos)
					{
						$addr_details->personal = substr($address,0,$pos - 1);
					}
				}
				elseif (is_int($pos3))
				{
					$pos2 = strpos($address,')');
					if ($pos2 == $pos3+1)
					{
						$addr_details->personal = 'nobody';
					}
					else
					{
						$addr_details->personal = substr($address, $pos3+1, $pos2-$pos3 - 1);
					}
					if ($pos3)
					{
						$addr_details->adl = substr($address,0,$pos3 - 1);
					}
				}
				else
				{
					$addr_details->adl = $address;
					$addr_details->personal = $address;
				}		
				$pos3 = strpos($addr_details->adl,'@');
				if (!$pos3)
				{
					if (!$pos)
					{
						$addr_details->mailbox = $addr_details->adl;
					}
					$addr_details->host = $GLOBALS['phpgw_info']['server']['imap_suffix'];
					$details[$i] = $addr_details;
					return $details;
				}
				$addr_details->mailbox = substr($addr_details->adl,0,$pos3);
				$addr_details->host    = substr($addr_details->adl,$pos3+1);
				$pos = ereg("\"",$addr_details->personal);
				if ($pos)
				{
					$addr_details->personal = substr($addr_details->personal,1,strlen($addr_details->personal)-2);
				}
				$pos = strpos($address,',');
				if ($pos)
				{
					$address = trim(substr($address,$pos+1));
				}
				$details[$i] = $addr_details;
			}
			return $details;
		}
		
		/**************************************************************************\
		*	More Data Communications (dcom) With POP3 Server
		\**************************************************************************/
	
		/**************************************************************************\
		*	DELETE a Message From the Server
		\**************************************************************************/
		/*!
		@function delete
		@abstract implements IMAP_DELETE
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : either an integer OR a comma seperated string of integers and/or ranges (21:23, 26, 69)
		@param $flags : integer - FT_UID (not implimented)
		@result returns True if able to mark a message for deletion, False if not
		@discussion  Similar to an IMAP server, POP3 must be expunged to actually delete marked messages
		This is done (1) by immediately closing the connection after your done marking, this will cause POP3 to expunge
		or (2) by issuing PHP's buildin IMAP_EXPUNGE command which we DO NOT emulate here
		@author Angles
		@access	public
		*/
		function delete($stream_notused,$msg_num,$flags="")
		{
			if ($this->debug >= 1) { echo 'pop3: Entering delete<br>'; }
			// in PHP 4 msg_num can be
			// a) an integer referencing a single message
			// b1) a comma seperated list of message numbers "1,2,6"
			// b2) and/or a range of messages format [STARTRANGE][COLON][ENDRANGE] "1:5"  "6:*"
			// make an array of message numbers to delete
			$tmp_array = Array();
			$tmp_array = explode(',',(string)$msg_num);
			// process the array, and clean any empty elements (explode can suck like that sometimes)
			$msg_num_array = Array();
			for($i=0;$i < count($tmp_array);$i++)
			{
				$this_element = (string)$tmp_array[$i];
				if ($this->debug >= 2) { echo 'pop3: delete prep: this_element: '.$this_element.'<br>'; }
				$this_element = trim($this_element);
				// do nothing if this is an empty array element
				if ($this_element != '')
				{
					// not empty - process it
					// do we have a range
					$cookie = strpos($this_element,':');
					if ($cookie > 0)
					{
						$start_num = substr($this_element,0,$cookie);
						$end_num = substr($this_element,$cookie+1);
						// wildcard * used?
						if ($end_num == '*')
						{
							$end_num = $this->num_msg($stream_notused);
						}
						// make sure we are dealing with integers now
						$start_num = (int)$start_num;
						$end_num = (int)$end_num;
						// add each number in this range to the msg_num_array
						for($z=$start_num; $z >= $end_num; $z++)
						{
							// add to the msg_num_array
							$new_idx = count($msg_num_array);
							$msg_num_array[$new_idx] = (int)$z;
							if ($this->debug >= 2) { echo 'pop3: delete prep: range: msg_num_array['.$new_idx.'] = '.$z.'<br>'; }
						}
					}
					else
					{
						// not a range, should be a single msg_num
						// add to the msg_num_array
						$new_idx = count($msg_num_array);
						$msg_num_array[$new_idx] = (int)$this_element;
						if ($this->debug >= 2) { echo 'pop3: delete prep: msg_num_array['.$new_idx.'] = '.$this_element.'<br>'; }
					}
				}
			}
			// we should now have a reliable array of msg_nums we need to delete from the server
			for($i=0;$i < count($msg_num_array);$i++)
			{
				$this_msg_num = $msg_num_array[$i];
				if ($this->debug >= 2) { echo 'pop3: delete: deleting this_msg_num '.$this_msg_num.'<br>'; }
				if (!$this->msg2socket('DELE '.$this_msg_num,"^\+ok",&$response))
				{
					$this->error();
					if ($this->debug >= 1) { echo 'pop3: Leaving delete with error deleting msgnum '.$this_msg_num.'<br>'; }
					return False;
				}
			}
			// these messages are now marked for deletion by the POP3 server
			// they will be expunged when user sucessfully explicitly logs out
			// if we make it here I have to assume no errors
			if ($this->debug >= 1) { echo 'pop3: Leaving delete<br>'; }
			return True;
		}
	
		/**************************************************************************\
		*	Get Message Headers From Server
		\**************************************************************************/
		/*!
		@function fetchheader
		@abstract implements IMAP_FETCHHEADER
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : integer
		@param $flags : integer - FT_UID; FT_INTERNAL; FT_PREFETCHTEXT
		@result returns string which is complete, unfiltered RFC2822  format header of the specified message
		@discussion  This function implements the  FT_PREFETCHTEXT text option
		This function uses the helper function "get_header_raw"
		@author Angles
		@access	public
		*/
		function fetchheader($stream_notused,$msg_num,$flags='')
		{
			// NEEDED: code for flags: FT_UID; FT_INTERNAL; FT_PREFETCHTEXT
			if ($this->debug >= 1) { echo 'pop3: Entering fetchheader<br>'; }
			
			$header_glob = $this->get_header_raw($stream_notused,$msg_num,$flags);
			
			// do we also need to get the text of the message?
			if ((int)$flags == FT_PREFETCHTEXT)
			{
				// what the user really wants here is the whole enchalada, i.e. the headers AND the message
				$header_glob = $header_glob
					."\r\n"
					.$this->get_body($stream_notused,$msg_num,$flags);
			}
			
			if ($this->debug >= 1) { echo 'pop3: Leaving fetchheader<br>'; }
			return $header_glob;
		}
	
		/*!
		@function get_header_array
		@abstract Custom Function - Similar to IMAP_FETCHHEADER - EXCEPT returns a string list array
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : integer
		@param $flags : integer - FT_UID; (FT_INTERNAL; FT_PREFETCHTEXT) none implemented
		@result returns headers exploded into a string list array, one array element per Un-Folded header line 
		@discussion  This function UN-FOLDS the headers as per RFC2822 "folding, so each element is 
		in fact the intended complete header line, eliminates partial "folded" lines
		@author Angles
		@access	public (custom function, also used privately)
		*/
		function get_header_array($stream_notused,$msg_num,$flags='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering get_header_array<br>'; }
			// do we have a cached header_array  ?
			if ((count($this->header_array) > 0)
			&& ((int)$this->header_array_msgnum == (int)($msg_num)))
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving get_header_array returning cached data<br>'; }
				return $this->header_array;
			}
			// NO cached data, get it
			// first get the raw glob header
			$header_glob = $this->get_header_raw($stream_notused,$msg_num,$flags);
			// unwrap any wrapped headers - using CR_LF_TAB as rfc822 "whitespace"
			$header_glob = str_replace("\r\n\t",' ',$header_glob);
			// unwrap any wrapped headers - using CR_LF_SPACE as rfc822 "whitespace"
			$header_glob = str_replace("\r\n ",' ',$header_glob);
			// make the header blob into an array of strings, one array element per header line, throw away blank lines
			$header_array = Array();
			$header_array = $this->glob_to_array($header_glob, False, '', True);
			// cache this data for future use
			$this->header_array = $header_array;
			$this->header_array_msgnum = (int)($msg_num);
			if ($this->debug >= 1) { echo 'pop3: Leaving get_header_array<br>'; }
			return $header_array;
		}
	
		/*!
		@function get_header_raw
		@abstract HELPER function for "fetchheader" / IMAP_FETCHHEADER
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : integer
		@param $flags : Not Used in helper function
		@result returns returns unprocessed glob header string of the specified message
		@discussion  This function causes a fetch of the complete, unfiltered RFC2822  format 
		header of the specified message as a text string and returns that text string (i.e. glob)
		@author Angles
		@access	private
		*/
		function get_header_raw($stream_notused,$msg_num,$flags='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering get_header_raw<br>'; }
			if ((!isset($msg_num))
			|| (trim((string)$msg_num) == ''))
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving get_header_raw with error: Invalid msg_num<br>'; }
				return False;
			}
			// do we have a cached header_glob ?
			if (($this->header_glob != '')
			&& ((int)$this->header_glob_msgnum == (int)($msg_num)))
			{
				if ($this->debug >= 1) { echo 'pop3: Leaving get_header_raw returning cached data<br>'; }
				return $this->header_glob;
			}
			// NO cached data, get it
			if ($this->debug >= 1) { echo 'pop3: get_header_raw: issuing: TOP '.$msg_num.' 0 <br>'; }
			if (!$this->msg2socket('TOP '.$msg_num.' 0',"^\+ok",&$response))
			{
				$this->error();
				if ($this->debug >= 1) { echo 'pop3: Leaving get_header_raw with error<br>'; }
				return False;
			}
			$glob = $this->read_port_glob('.');
			// save this info for future ues
			$this->header_glob = $glob;
			$this->header_glob_msgnum = (int)$msg_num;
			if ($this->debug >= 1) { echo 'pop3: Leaving get_header_raw<br>'; }
			return $glob;
		}
	
		/**************************************************************************\
		*	Get Message Body (Parts) From Server
		\**************************************************************************/
		
		/*!
		@function fetchbody
		@abstract implements IMAP_FETCHBODY
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : integer
		@param $part_num : integer or a string of integers seperated by dots  "2.4.1"
		references the MIME part number, or section, inside of the message
		@param $flags : Not Used in helper function
		@result returns string which is the desired message / part
		@discussion  NOTE: as of Oct 17, 2001, the $part_num used here is not always
		the same as the part number used for official imap servers. But because this same 
		class produced the fetchstructure, and provided it to the client, and that client 
		will again use this class to get that part, the part number is consistant internally 
		and is MUCH easier to implement in the fetchbody code. However, in the future, the 
		part numbering logic in fetchbody will be coded to exactly match what an official imap 
		server would expect. In the msg_msg class I refer to this "inexact" part number 
		as "mime number dumb" as it is based only on the part's position in the 
		fetchstructure array, before the processing to convert to official imap part 
		number, which msg_msg class refers to as "mime number smart", which 
		is used to access mime parts when using PHP's builtin IMAP module.
		@author Angles
		@access	public
		*/
		function fetchbody($stream_notused,$msg_num,$part_num='',$flags='')
		{
			if ($this->debug >= 1) { echo 'pop3: Entering fetchbody<br>'; }
			if ($this->debug >= 1) { echo 'pop3: fetchbody: attempt to return part '.$part_num.'<br>'; }
			// totally under construction

			// FORCE a pass thru fetchstructure to ENSURE all necessary data is present and cached
			if ($this->debug >= 2) { echo 'pop3: fetchbody: force a pass thru fetchstructure to ensure necessary data is present and cached<br>'; }
			$bogus_data = $this->fetchstructure($stream_notused,$msg_num,$flags);
			
			// EXTREMELY BASIC part handling
			// handle request for top level message headers
			if ((int)$part_num == 0)
			{
				if ($this->debug >= 1) { echo 'pop3: fetchbody: returning top-level headers, part '.$part_num.', internally ['.$the_part.']<br>'; }
				// grab the headers, as a glob, i.e. a string NOT an array
				$header_glob = $this->get_header_raw($stream_notused,$msg_num,'');
				// put this data in the var we will return below
				$body_blob = $header_glob;
			}
			// handle 1st level parts
			elseif (strlen((string)$part_num) == 1)
			{
				// convert to fetchstructure part number
				$the_part = (int)$part_num;
				$the_part = $the_part - 1;
				// return part one
				if ($this->debug >= 1) { echo 'pop3: fetchbody: returning part '.$part_num.', internally ['.$the_part.']<br>'; }
				if ((!isset($this->msg_structure->parts[$the_part]->custom['part_start']))
				|| (!isset($this->msg_structure->parts[$the_part]->custom['part_start'])))
				{
					if ($this->debug >= 1) { echo 'pop3: fetchbody: ERROR: required part data not present for '.$part_num.', internally ['.$the_part.']<br>'; }
					// screw it, just return the whole thing
					if ($this->debug >= 1) { echo 'pop3: fetchbody - using fallback pass thru<br>'; }
					$body_blob = $this->get_body($stream_notused,$msg_num,$flags,False);				
				}
				else
				{
					// attempt to make the part
					$part_start = (int)$this->msg_structure->parts[$the_part]->custom['part_start'];
					$part_end = (int)$this->msg_structure->parts[$the_part]->custom['part_end'];
					if ($this->debug >= 1) { echo 'pop3: fetchbody: returning part '.$part_num.' starts ['.$part_start.'] ends ['.$part_end.']<br>'; }
					// assemble the body [art part
					$body_blob = '';
					for($i=$part_start;$i < $part_end+1;$i++)
					{
						$body_blob .= $this->body_array[$i]."\r\n";
					}
				}
			}
			// handle multiple parts
			elseif (strlen((string)$part_num) > 2)
			{
				// explode part number into its component part numbers
				$the_part_array = Array();
				$the_part_array = explode('.',$part_num);
				// convert to fetchstructure part number
				for($i=0;$i < count($the_part_array);$i++)
				{
					$the_part_array[$i] = (int)$the_part_array[$i];
					$the_part_array[$i] = $the_part_array[$i] - 1;
				}
				// build the recursive parts structure to obtain this parts data
				// use REFERENCES to do this
				$temp_part = &$this->msg_structure;
				for($i=0;$i < count($the_part_array);$i++)
				{
					$target_part = $temp_part->parts[$the_part_array[$i]];
					$temp_part = &$target_part;
				}
				// verify part data exists
				if ($this->debug >= 1) { echo 'pop3: fetchbody: returning part '.$part_num.', internally ['.serialize($the_part_array).']<br>'; }
				if ((!isset($target_part->custom['part_start']))
				|| (!isset($target_part->custom['part_start'])))
				{
					if ($this->debug >= 1) { echo 'pop3: fetchbody: ERROR: required part data not present for '.$part_num.', internally ['.serialize($the_part).']<br>'; }
					// screw it, just return the whole thing
					if ($this->debug >= 1) { echo 'pop3: fetchbody - using fallback pass thru<br>'; }
					$body_blob = $this->get_body($stream_notused,$msg_num,$flags,False);				
				}
				else
				{
					// attempt to make the part
					$part_start = (int)$target_part->custom['part_start'];
					$part_end = (int)$target_part->custom['part_end'];
					if ($this->debug >= 1) { echo 'pop3: fetchbody: returning part '.$part_num.' starts ['.$part_start.'] ends ['.$part_end.']<br>'; }
					// assemble the body [art part
					$body_blob = '';
					for($i=$part_start;$i < $part_end+1;$i++)
					{
						$body_blob .= $this->body_array[$i]."\r\n";
					}
				}
			}
			else
			{
				// screw it, just return the whole thing
				if ($this->debug >= 1) { echo 'pop3: fetchbody - something is unsupported, using fallback pass thru<br>'; }
				// the false arg here is a temporary, custom option, says to NOT include the headers in the return
				$body_blob = $this->get_body($stream_notused,$msg_num,$flags,False);
			}
			
			if ($this->debug >= 1) { echo 'pop3: Leaving fetchbody<br>'; }
			return $body_blob;
		}
	
		/*!
		@function get_body
		@abstract implements IMAP_BODY
		@param $stream_notused : socket class handles stream reference internally
		@param $msg_num : integer
		@param $flags : integer - FT_UID; FT_INTERNAL; FT_PEEK; FT_NOT
		@param$phpgw_include_header : boolean (for custom use - not a PHP option)
		@result returns string which is a verbatim copy of the message body (i.e. glob)
		@discussion  This function implements the  IMAP_BODY and also includes a custom
		boolean param "phpgw_include_header" which also includes unfiltered headers in the return string
		@author Angles
		@access	public
		*/
		function get_body($stream_notused,$msg_num,$flags='',$phpgw_include_header=True)
		{
			// NEEDED: code for flags: FT_UID; maybe FT_INTERNAL; FT_NOT; flag FT_PEEK has no effect on POP3
			if ($this->debug >= 1) { echo 'pop3: Entering get_body<br>'; }
	
			// do we have a cached body_array ?
			if ((count($this->body_array) > 0)
			&& ((int)$this->body_array_msgnum == (int)($msg_num))
			// do we have a cached header_array  ?
			&& (count($this->header_array) > 0)
			&& ((int)$this->header_array_msgnum == (int)($msg_num)))
			{
				if ($this->debug >= 1) { echo 'pop3: get_body: using cached body_array and header_array data imploded into a glob<br>'; }
				// implode the header_array into a glob
				$header_glob = implode("\r\n",$this->header_array);
				// implode the body_array into a glob
				$body_glob = implode("\r\n",$this->body_array);
			}
			else
			{
				if ($this->debug >= 1) { echo 'pop3: get_body: NO Cached Data<br>'; }
				// NO cached data we can use
				// issue command to retrieve body
				if (!$this->msg2socket('RETR '.$msg_num,"^\+ok",&$response))
				{
					$this->error();
					if ($this->debug >= 1) { echo 'pop3: Leaving get_body with error<br>'; }
					return False;
				}
				// ---  Get Header  ---
				// we can NOT cache the header in THIS function because we may need to BYPASS them
				// to do that we need to grab it from the stream,  then start filling body_glob
				// AFTER we have passed the header in the stream
				$header_glob = '';
				while ($line = $this->read_port())
				{
					if ((chop($line) == '.')
					|| (chop($line) == ''))
					{
						break;
					}
					$header_glob .= $line;
				}
				// ---  Get Body  ---
				// we know we have passed the headers because we did that above
				$body_glob = '';
				$body_glob = $this->read_port_glob('.');
				// --- Explode Into an Array and Save for Future use with Fetchstructure
				$this->body_array = explode("\r\n",$body_glob);
				$this->body_array_msgnum = (int)$msg_num;
			}
			// ---  Include Headers With Body Or Not  ---
			if (($flags == FT_NOT) || ($phpgw_include_header == True))
			{
				// we need to include the header here
				$body_glob = $header_glob ."\r\n" .$body_glob;
			}
			/*
			if ($this->debug >= 2)
			{
				echo 'pop3: get_body DUMP<br>= = = First DUMP: header_glob<br>';
				echo '<pre>'.htmlspecialchars($header_glob).'</pre><br><br>';
				echo 'pop3: get_body DUMP<br>= = = Second DUMP: body_glob<br>';
				echo '<pre>'.htmlspecialchars($body_glob).'</pre><br><br>';
			}
			*/
			if ($this->debug >= 1) { echo 'pop3: Leaving get_body<br>'; }
			return $body_glob;
		}
	}
?>
