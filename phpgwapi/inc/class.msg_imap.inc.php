<?php
  /**************************************************************************\
  * phpGroupWare Email - IMAP abstraction				*
  * http://www.phpgroupware.org/api					*
  * This file written by Itzchak Rehberg <izzy@phpgroupware.org>	*
  * and Joseph Engo <jengo@phpgroupware.org>				*
  * Mail function abstraction for IMAP servers				*
  * Copyright (C) 2000, 2001 Itzchak Rehberg				*
  * -------------------------------------------------------------------------		*
  * This library is part of phpGroupWare (http://www.phpgroupware.org)       * 
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,	*
  * or any later version.							*
  * This library is distributed in the hope that it will be useful, but	*
  * WITHOUT ANY WARRANTY; without even the implied warranty of	*
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	*
  * See the GNU Lesser General Public License for more details.		*
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA	*
  \**************************************************************************/

  /* $Id$ */

	class msg extends msg_base
	{
		function append($stream, $folder, $message, $flags=0)
		{
			$folder = $this->utf7_encode($folder);
			return imap_append($stream, $folder, $message, $flags);
		}

		function base64($text)
		{
			return imap_base64($text);
		}

		function close($stream,$flags='')
		{
			return imap_close($stream,$flags);
		}

		function createmailbox($stream,$mailbox)
		{
			$mailbox = $this->utf7_encode($mailbox);
			$this->folder_list_changed = True;
			return imap_createmailbox($stream,$mailbox);
		}

		function deletemailbox($stream,$mailbox)
		{
			$this->folder_list_changed = True;
			$mailbox = $this->utf7_encode($mailbox);
			return imap_deletemailbox($stream,$mailbox);
		} 

		function renamemailbox($stream,$mailbox_old,$mailbox_new)
		{
			$this->folder_list_changed = True;
			$mailbox_old = $this->utf7_encode($mailbox_old);
			$mailbox_new = $this->utf7_encode($mailbox_new);
			return imap_renamemailbox($stream,$mailbox_old,$mailbox_new);
		}

		function delete($stream,$msg_num,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			return imap_delete($stream,$msg_num,$flags);
		}

		function expunge($stream)
		{
			return imap_expunge($stream);
		}

		function fetchbody($stream,$msgnr,$partnr,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			return imap_fetchbody($stream,$msgnr,$partnr,$flags);
		}

		function header($stream,$msg_nr,$fromlength='',$tolength='',$defaulthost='')
		{
			// do we need to temporarily switch to regular msg num sequence for this function?
			if ($this->force_msg_uids == True)
			{
				// this function can nothandle UIDs, switch to sequence number
				$new_msg_nr = imap_msgno($stream,$msg_nr);
				if ($new_msg_nr)
				{
					$msg_nr = $new_msg_nr;
				}
			}
			return imap_header($stream,$msg_nr,$fromlength,$tolength,$defaulthost);
		}
		
		function headers($stream)
		{
			return imap_headers($stream);
		} 

		function fetch_raw_mail($stream,$msg_num,$flags=0)
		{
			$flags |= FT_PREFETCHTEXT;
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			return imap_fetchheader($stream,$msg_num,$flags);
		}

		function fetchheader($stream,$msg_num,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			return imap_fetchheader($stream,$msg_num,$flags);
		}

		function fetchstructure($stream,$msg_num,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			return imap_fetchstructure($stream,$msg_num,$flags);
		}

		function get_body($stream,$msg_num,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			return imap_body($stream,$msg_num,$flags);
		}

		function get_header($stream,$msg_num,$flags)
		{
			// alias for compatibility with some old code
			return $this->fetchheader($stream,$msg_num,$flags);
		}

		function listmailbox($stream,$ref,$pattern)
		{
			//return imap_listmailbox($stream,$ref,$pattern);
			$pattern = $this->utf7_encode($pattern);
			$return_list = imap_listmailbox($stream,$ref,$pattern);
			return $this->utf7_decode($return_list);
		}

		function mailboxmsginfo($stream)
		{
			return imap_mailboxmsginfo($stream);
		}

		function mailcopy($stream,$msg_list,$mailbox,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & CP_UID)) )
			{
				$flags |= CP_UID;
			}
			$mailbox = $this->utf7_encode($mailbox);
			return imap_mail_copy($stream,$msg_list,$mailbox,$flags);
		}

		function move($stream,$msg_list,$mailbox,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & CP_UID)) )
			{
				$flags |= CP_UID;
			}
			$mailbox = $this->utf7_encode($mailbox);
			return imap_mail_move($stream,$msg_list,$mailbox,$flags);
		}

		function num_msg($stream) // returns number of messages in the mailbox
		{ 
			return imap_num_msg($stream);
		}
		
		function noop_ping_test($stream)
		{ 
			return imap_ping($stream);
		}

		function open($mailbox,$username,$password,$flags=0)
		{
			$mailbox = $this->utf7_encode($mailbox);
			return imap_open($mailbox,$username,$password,$flags);
		}

		function qprint($message)
		{
			// return quoted_printable_decode($message);
			$str = quoted_printable_decode($message);
			return str_replace("=\n",'',$str);
		}

		function reopen($stream,$mailbox,$flags=0)
		{
			$mailbox = $this->utf7_encode($mailbox);
			return imap_reopen($stream,$mailbox,$flags);
		}

		function server_last_error()
		{
			// supported in PHP >= 3.0.12
			return imap_last_error();
		}

		function i_search($stream,$criteria,$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & SE_UID)) )
			{
				$flags |= SE_UID;
			}
			return imap_search($stream,$criteria,$flags);
		}
		
		function sort($stream,$criteria,$reverse='',$flags=0)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & SE_UID)) )
			{
				$flags |= SE_UID;
			}
			//echo 'class dcom: sort: $this->force_msg_uids= '.serialize($this->force_msg_uids).'; $flags: ['.serialize($flags).']<br>';
			return imap_sort($stream,$criteria,$reverse,$flags);
		}

		function status($stream,$mailbox,$options=0)
		{
			$mailbox = $this->utf7_encode($mailbox);
			return imap_status($stream,$mailbox,$options);
		}

		function construct_folder_str($folder)
		{ 
			/* This is only used by the login() function */
			// Cyrus style: INBOX.Junque
			// UWash style: ./aeromail/Junque
			return $GLOBALS['phpgw']->msg->get_folder_long($folder);
		}

		function deconstruct_folder_str($folder)
		{
			//  This is only used by the login() function
			// Cyrus style: INBOX.Junque
			// UWash style: ./aeromail/Junque
			return $GLOBALS['phpgw']->msg->get_folder_short($folder);
		}

		/* rfc_get_flag() is more "rfc safe", as RFC822 allows
			the content of the header to be on several lines.

			Quote from RFC822 3.1.1:
			<quote>
				For convenience, the field-body  portion  of  this  conceptual
				entity  can be split into a multiple-line representation; this
				is called "folding".  The general rule is that wherever  there
				may  be  linear-white-space  (NOT  simply  LWSP-chars), a CRLF
				immediately followed by AT LEAST one LWSP-char may instead  be
				inserted.
			</quote>

			Note:	$flag should _NOT_ begin with a space
			$field_no should be given strarting at 1
		*/
		function get_flag($stream,$msg_num,$flags=0,$field_no=1)
		{
			// do we force use of msg UID's 
			if ( ($this->force_msg_uids == True)
			&& (!($flags & FT_UID)) )
			{
				$flags |= FT_UID;
			}
			$fieldCount = 0;
			$header = imap_fetchheader ($stream, $msg_num, $flags);
			$header = explode("\n", $header);
			$flag = strtolower($flag);

			for ($i=0; $i < count($header); $i++)
			{
				// The next check for the $flag _requires_ the field to
				// start at the first character (unless some person
				// adds a space in the beginning of $flag.
				// I believe this is correct according to the RFC.

				if (strcmp (substr(strtolower($header[$i]),0,strlen($flag) + 1), $flag.':')==0)
				{
					$fieldFound = true;
					$fieldCount++;
				}
				else
				{
					$fieldFound = false;
				}
		
				if ($fieldFound && $fieldCount == $field_no)
				{
					// We now need to see if the next lines belong to this  message. 
					$header_begin = $i;
					// make sure we don't go too far:)
					// and if the line begins with a space then
					// we'll increment the counter with one.
					$i++;

					while ($i < count($header) 
						&& strcmp(substr($header[$i],0,1), ' ') == 0)
					{
						$i++;
					}

					// Remove the "field:" from this string.
					$return_tmp = explode (':', $header[$header_begin]);
					$tmp_flag = $return_tmp[0];
					$return_string = trim ($return_tmp[1]);

					if (strcasecmp ($flag, $tmp_flag) != 0)
					{
						return false;
					}
					// Houston, we have a _problem_
					// add the rest of the content

					for ($j=$header_begin+1; $j < $i; $j++)
					{
						$return_string .= $header[$j];
					}

					return $return_string;
				}
			}
			// failed to find $flag
			return false;
		}
	}
?>
