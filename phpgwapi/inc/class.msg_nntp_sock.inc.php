<?php
  /**************************************************************************\
  * phpGroupWare API - NNTP                                                  *
  * This file written by Mark Peters <skeeter@phpgroupware.org>              *
  * Handles specific operations in dealing with NNTP                         *
  * Copyright (C) 2001 Mark Peters                                           *
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

	class msg extends msg_base
	{
		var $db;
		var $folder;
		var $start_msg;
		var $end_msg;

		function mode_reader()
		{
			return $this->msg2socket('mode reader','^20[01]',&$response);
		}

		function login ($user,$passwd,$server,$port,$folder = '')
		{
			$this->db = $GLOBALS['phpgw']->db;

			if(@!$server)
			{
				echo 'Error: Configuration Error! The administrator has not configured the NNTP Server.';
			}

			if(@!$port)
			{
				$port = 119;
			}

			if (!$this->open_port($server,$port,15))
			{
				$this->error();
			}
			$this->read_port();

			if ($user <> '' && $passwd <> '')
			{
				if (!$this->msg2socket('authinfo user '.$user,'^381',&$response))
				{
					$this->error();
				}
				if (!$this->msg2socket('authinfo pass '.$passwd,'^281',&$response))
				{
					$this->error();
				}
			}
			if (!$this->mode_reader())
			{
				$this->error();
			}
			if(!$folder)
			{
				$folder = $this->get_first_folder();
				if(!$folder)
				{
					$this->error();
				}
			}
			$this->folder = $folder;
			$this->mailbox = $this->get_mailbox_name($folder);
			$this->num_msgs = $this->num_msg($this->mailbox);
			$this->start_msg = $this->first_message($this->mailbox);
			$this->end_msg = $this->last_message($this->mailbox);
			echo 'Successful connection to '.$this->mailbox."<br>\n";
		}

		function fix_folder($folder='')
		{
			if($folder=='')
			{
				$mailbox = $this->mailbox;
			}
			elseif(is_int($folder))
			{
				$mailbox = $this->get_mailbox_name($folder);
			}
			else
			{
				$mailbox = $folder;
			}
			return $mailbox;
		}

		function get_first_folder()
		{
			if(@!$GLOBALS['phpgw_info']['user']['preferences']['nntp'])
			{
				$this->set_error('Configuration','User Preferences','You have not set your user preferences in NNTP.');
				$this->error();
			}
			else
			{
				$pref = @each($GLOBALS['phpgw_info']['user']['preferences']['nntp']);
				return $pref[0];
			}
		}

		function get_mailbox_name($folder)
		{
			$active = False;
			$this->db->query('SELECT name,active FROM newsgroups WHERE con='.$folder,_LINE__,__FILE__);
			if ($this->db->num_rows() > 0)
			{
				$this->db->next_record();
				$mailbox = $this->db->f('name');
			}
			if ($this->db->f('active') != 'Y')
			{
				$GLOBALS['phpgw']->preferences->delete('nntp',$folder);
				$GLOBALS['phpgw']->preferences->save_repository();

				$this->set_error('Administration','Automatic Disabling','The newsgroup '.$mailbox.' is not activated by the Administrator.');
				$this->error();
			}
			return $mailbox;
		}

		function get_mailbox_counts($folder='',$index=1)
		{
			$mailbox = $this->fix_folder($folder);
			if (!$this->msg2socket('group '.$mailbox,'^211',&$response))
			{
				$this->error();
			}
			$temp_array = explode(' ',$response);
			return $temp_array[$index];
		}

		function num_msg($folder='')
		{
			if(($folder == '' || $folder == $this->mailbox) && isset($this->num_msgs))
			{
				return $this->num_msgs;
			}
			return $this->get_mailbox_counts($folder,1);
		}

		function first_message($folder='')
		{
			if(($folder == '' || $folder == $this->mailbox) && isset($this->start_msg))
			{
				return $this->start_msg;
			}
			return $this->get_mailbox_counts($folder,2);
		}

		function last_message($folder='')
		{
			if(($folder == '' || $folder == $this->mailbox) && isset($this->end_msg))
			{
				return $this->end_msg;
			}
			return $this->get_mailbox_counts($folder,3);
		}

		function mailboxmsginfo($folder='')
		{
			$info = new msg_mb_info;
			if($folder=='' || $folder==$this->mailbox || $folder==$this->folder)
			{
				if(isset($this->num_msgs))
				{
					$info->messages = $this->num_msgs;
				}
				else
				{
					if($folder==$this->folder)
					{
						$this->mailbox = $this->get_mailbox_name($folder);
					}
					$info->messages = $this->num_msg($this->mailbox);
				}
				$info->size  = 0;
				if ($info->messages)
				{
					return $info;
				}
				else
				{
					return False;
				}
			}
			else
			{
				$mailbox = $this->fix_folder($folder);
			}

			$info->messages = $this->num_msgs($mailbox);
			$info->size  = 0;

			$this->num_msgs($this->mailbox);

			if ($info->messages)
			{
				return $info;
			}
			else
			{
				return False;
			}
		}

		function fetch_field($start,$stop,$element)
		{
			if (!$this->msg2socket('XHDR '.$element.' '.$start.'-'.$stop,'^221',&$response))
			{
				$this->error();
			}

			$field_element = Array();
			while ($line = $this->read_port())
			{
				$line = chop($line);
				if ($line == '.')
				{
					break;
				}
				$breakpos = strpos($line,' ');

				$field_element[intval(substr($line,0,$breakpos-1))] = $this->phpGW_quoted_printable_decode2(substr($line,$breakpos+1));
			}
			return $field_element;
		}

		function status($folder='',$options=SA_ALL)
		{
			$info = new mailbox_status;
			$info->messages = $this->num_msg($folder);
			return $info;
		}

		function sort($folder='',$criteria=SORTDATE,$reverse=False,$options='')
		{
			if($folder == '' || $folder == $this->mailbox)
			{
				$mailbox = $this->mailbox;
				$start_msg = $this->start_msg;
				$end_msg = $this->end_msg;
			}
			else
			{
				$mailbox = $this->fix_folder($folder);
				$start_msg = $this->first_message($mailbox);
				$end_msg = $this->last_message($mailbox);
			}

			switch($criteria)
			{
				case SORTDATE:
					$old_list = $this->fetch_field($start_msg,$end_msg,'Date');
					$field_list = $this->convert_date_array($old_list);
					break;
				case SORTARRIVAL:
					break;
				case SORTFROM:
					$field_list = $this->fetch_field($start_msg,$end_msg,'From');
					break;
				case SORTSUBJECT:
					$field_list = $this->fetch_field($start_msg,$end_msg,'Subject');
					break;
				case SORTTO:
					$field_list = $this->fetch_field($start_msg,$end_msg,'To');
					break;
				case SORTCC:
					$field_list = $this->fetch_field($start_msg,$end_msg,'cc');
					break;
				case SORTSIZE:
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
				echo '('.$i++.') Field: <b>'.$value."</b>\t\tMsg Num: <b>".$key."</b><br>\n";
			}
			@reset($return_array);
			return $return_array;
		}
	}
?>
