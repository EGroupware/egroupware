<?php
	/**************************************************************************\
	* phpGroupWare - InfoLog                                                   *
	* http://www.phpgroupware.org                                              *
	* Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
	* originaly based on todo written by Joseph Engo <jengo@phpgroupware.org>  *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	class boinfolog 			// BO: buiseness objects: internal logic
	{
		var $public_functions = array
		(
			'init'           => True,	// in class soinfolog
			'read'           => True,
			'write'          => True,
			'delete'         => True,
			'check_access'   => True,
			'readProj'       => True,
			'readAddr'       => True,
			'anzSubs'        => True,
			'readIdArray'    => True,
			'accountInfo'    => True,	// in class boinfolog (this class)
			'addr2name'      => True,
			'attach_file'    => True,
			'delete_attached'=> True,
			'info_attached'  => True,
			'list_attached'  => True,
			'read_attached'  => True,
			'attached_local' => True
		);
		var $enums;
		var $so;
		var $vfs;
		var $vfs_basedir='/infolog';
		var $valid_pathes = array();
		var $send_file_ips = array();

		function boinfolog( $info_id = 0)
		{
			$this->enums = array(
				'priority' => array (
					'urgent' => 'urgent','high' => 'high','normal' => 'normal',
					'low' => 'low' ),
				'status'   => array(
					'offer' => 'offer','ongoing' => 'ongoing','call' => 'call',
					'will-call' => 'will-call','done' => 'done',
					'billed' => 'billed' ),
				'confirm'   => array(
					'not' => 'not','accept' => 'accept','finish' => 'finish',
					'both' => 'both' ),
				'type'      => array(
					'task' => 'task','phone' => 'phone','note' => 'note'
				/*	,'confirm' => 'confirm','reject' => 'reject','email' => 'email',
					'fax' => 'fax' no implemented so far */ )
			);
			$this->status = array(
				'defaults' => array(
					'task' => 'ongoing', 'phone' => 'call', 'note' => 'done'),
				'task' => array(
					'offer' => 'offer','ongoing' => 'ongoing',
					'done' => 'done', 'billed' => 'billed' ),
				'phone' => array(
					'call' => 'call','will-call' => 'will-call',
					'done' => 'done', 'billed' => 'billed' ),
				'note' => array(
					'ongoing' => 'ongoing', 'done' => 'done'
			));

			$this->so = CreateObject('infolog.soinfolog');
			$this->vfs = CreateObject('infolog.vfs');

			$this->config = CreateObject('phpgwapi.config');
			$this->config->read_repository();

			if ($this->config->config_data)
			{
				$this->link_pathes   = unserialize($this->config->config_data['link_pathes']);
				$this->send_file_ips = unserialize($this->config->config_data['send_file_ips']);
			}

			$this->read( $info_id);
		}

		function accountInfo($id,$account_data=0)
		{
			if (!$id) return '&nbsp;';

			if (!is_array($account_data))
			{
				if (!isset($this->account_data[$id]))		// do some cacheing
				{
					$GLOBALS['phpgw']->accounts->accounts($id);
					$GLOBALS['phpgw']->accounts->read_repository();
					$this->account_data[$id] = $GLOBALS['phpgw']->accounts->data;
				}
				$account_data = $this->account_data[$id];
			}
			if ($GLOBALS['phpgw_info']['user']['preferences']['infolog']['longNames'])
			{
				return $account_data['firstname'].' '.$account_data['lastname'];
			}
			return $account_data['account_lid'];
		}

		function addr2name( $addr )
		{
			$name = $addr['n_family'];
			if ($addr['n_given'])
			{
				$name .= ', '.$addr['n_given'];
			}
			else
			{
				if ($addr['n_prefix'])
				{
					$name .= ', '.$addr['n_prefix'];
				}
			}
			if ($addr['org_name'])
			{
				$name = $addr['org_name'].': '.$name;
			}
			return $GLOBALS['phpgw']->strip_html($name);
		}

		function readProj($proj_id)
		{
			if ($proj_id)
			{
				if (!is_object($this->projects))
				{
					$this->projects = createobject('projects.boprojects');
				}
				if (is_object($this->projects) && (list( $proj ) = $this->projects->read_single_project( $proj_id)))
				{
					return $proj;
				}
			}
			return False;
		}

		function readAddr($addr_id)
		{
			if ($addr_id)
			{
				if (!is_object($this->contacts))
				{
					$this->contacts = createobject('phpgwapi.contacts');
				}
				if (list( $addr ) = $this->contacts->read_single_entry( $addr_id ))
				{
					return $addr;
				}
			}
			return False;
		}

		/*
		 * check's if user has the requiered rights on entry $info_id
		 */
		function check_access( $info_id,$required_rights )
		{
			return $this->so->check_access( $info_id,$required_rights );
		}

		function init()
		{
			$this->so->init();
		}      

		function read($info_id)
		{
			$this->so->read($info_id);
				
			if ($this->so->data['info_subject'] ==
				 (substr($this->so->data['info_des'],0,60).' ...'))
			{
				$this->so->data['info_subject'] = '';
			}
			if ($this->so->data['info_addr_id'] && $this->so->data['info_from'] ==
				 $this->addr2name( $this->readAddr( $this->so->data['info_addr_id'] )))
			{
				$this->so->data['info_from'] = '';
			}
			return $this->so->data;
		}

		function delete($info_id)
		{
			$this->delete_attached($info_id);

			$this->so->delete($info_id);
		}

		function write($values)
		{
			if ($values['responsible'] && $values['status'] == 'offer')
			{
				$values['status'] = 'ongoing';   // have to match if not finished
			}
			if (!$values['info_id'] && !$values['owner'])
			{
				$values['owner'] = $this->so->user;
			}
			$values['datecreated'] = time(); // is now MODIFICATION-date

			if (!$values['subject'])
			{
				$values['subject'] = substr($values['des'],0,60).' ...';
			}
			if ($values['addr_id'] && !$values['from'])
			{
				$values['from'] = $this->addr2name( $this->readAddr( $values['addr_id'] ));
			}
			$this->so->write($values);
		}

		function anzSubs( $info_id )
		{
			return $this->so->anzSubs( $info_id );
		}

		function readIdArray($order,$sort,$filter,$cat_id,$query,$action,$action_id,
									$ordermethod,&$start,&$total)
		{
			return $this->so->readIdArray($order,$sort,$filter,$cat_id,$query,
								  					$action,$action_id,$ordermethod,$start,$total);
		}


		function vfs_path($info_id,$file='')
		{
			return $this->vfs_basedir . '/' . $info_id . ($file ? '/' . $file : '');
		}

		/*
		**	Put a file to the corrosponding place in the VFS and set the attributes
		**	ACL check is done by the VFS
		*/
		function attach_file($info_id,$filepos,$name,$size,$type,$comment='',$full_fname='',$ip='')
		{
			//echo "<p>attach_file: info_id='$info_id', filepos='$filepos', name='$name', size='$size', type='$type', comment='$comment', full_fname='$full_fname', ip='$ip'</p>\n";

			// create the root for attached files in infolog, if it does not exists
			if (!($this->vfs->file_exists($this->vfs_basedir,array(RELATIVE_ROOT))))
			{
				$this->vfs->override_acl = 1;
				$this->vfs->mkdir($this->vfs_basedir,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
			}

			$dir=$this->vfs_path($info_id);
			if (!($this->vfs->file_exists($dir,array(RELATIVE_ROOT))))
			{
				$this->vfs->override_acl = 1;
				$this->vfs->mkdir($dir,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
			}
			$fname = $this->vfs_path($info_id,$name);
			$tfname = '';
			if ($full_fname)
			{
				$full_fname = str_replace('\\\\','/',$full_fname);	// vfs uses only '/'
				@reset($this->link_pathes);
				while ((list($valid,$trans) = @each($this->link_pathes)) && !$tfname)
				{  // check case-insensitive for WIN etc.
					$check = $valid[0] == '\\' || strstr(':',$valid) ? 'eregi' : 'ereg';
					$valid2 = str_replace('\\','/',$valid);
					//echo "<p>attach_file: ereg('".$this->send_file_ips[$valid]."', '$ip')=".ereg($this->send_file_ips[$valid],$ip)."</p>\n";
					if ($check('^('.$valid2.')(.*)$',$full_fname,$parts) &&
					    ereg($this->send_file_ips[$valid],$ip) &&     // right IP
					    $this->vfs->file_exists($trans.$parts[2],array(RELATIVE_NONE|VFS_REAL)))
					{
						$tfname = $trans.$parts[2];
					}
					//echo "<p>attach_file: full_fname='$full_fname', valid2='$valid2', trans='$trans', check=$check, tfname='$tfname', parts=(x,'${parts[1]}','${parts[2]}')</p>\n";
				}
				if ($tfname && !$this->vfs->securitycheck($tfname))
				{
					return lang('Invalid filename').': '.$tfname;
				}
			}
			$this->vfs->override_acl = 1;
			if ($tfname)	// file is local
			{
				$this->vfs->symlink($tfname,$fname,array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT));
			}
			else
			{
				$this->vfs->cp($filepos,$fname,array(RELATIVE_NONE|VFS_REAL,RELATIVE_ROOT));
			}
			$this->vfs->set_attributes ($fname, array (RELATIVE_ROOT),
				array ('mime_type' => $type,
						 'comment' => stripslashes ($comment),
						 'app' => 'infolog'));
			$this->vfs->override_acl = 0;
		}

		function delete_attached($info_id,$fname = '')
		{
			$file = $this->vfs_path($info_id,$fname);

			if ($this->vfs->file_exists($file,array(RELATIVE_ROOT)))
			{
				$this->vfs->override_acl = 1;
				$this->vfs->delete($file,array(RELATIVE_ROOT));
				$this->vfs->override_acl = 0;
			}
		}

		function info_attached($info_id,$filename)
		{
			$this->vfs->override_acl = 1;
			$attachments = $this->vfs->ls($this->vfs_path($info_id,$filename),array(REALTIVE_NONE));
			$this->vfs->override_acl = 0;

			if (!count($attachments) || !$attachments[0]['name'])
			{
				return False;
			}
			return $attachments[0];
		}

		function list_attached($info_id)
		{
			$this->vfs->override_acl = 1;
			$attachments = $this->vfs->ls($this->vfs_path($info_id),array(REALTIVE_NONE));
			$this->vfs->override_acl = 0;

			if (!count($attachments) || !$attachments[0]['name'])
			{
				return False;
			}
			while (list($keys,$fileinfo) = each($attachments))
			{
				$attached[$fileinfo['name']] = $fileinfo['comment'];
			}
			return $attached;
		}

		function is_win_path($path)
		{
			return $path[0] == '\\' || strstr($path,':');
		}

		function read_attached($info_id,$filename)
		{
			if (!$info_id || !$filename || !$this->check_access($info_id,PHPGW_ACL_READ))
			{
				return False;
			}
			$this->vfs->override_acl = 1;
			return $this->vfs->read($this->vfs_path($info_id,$filename),array(RELATIVE_ROOT));
		}

		/*
		 * Checks if filename should be local availible and if so returns 'file:/path' for HTTP-redirect
		 * else return False
		 */
		function attached_local($info_id,$filename,$ip,$win_user)
		{
			//echo "<p>attached_local(info_id='$info_id', filename='$filename', ip='$ip', win_user='$win_user', count(send_file_ips)=".count($this->send_file_ips).")</p>\n";

			if (!$info_id || !$filename || !$this->check_access($info_id,PHPGW_ACL_READ) ||
			    !count($this->send_file_ips))
			{
				return False;
			}
			$link = $this->vfs->readlink ($this->vfs_path($info_id,$filename), array (RELATIVE_ROOT));

			if ($link)
			{
				reset($this->link_pathes); $fname = '';
				while ((list($valid,$trans) = each($this->link_pathes)) && !$fname)
				{
					if (!$this->is_win_path($valid) == !$win_user && // valid for this OS
					    eregi('^'.$trans.'(.*)$',$link,$parts)  &&    // right path
					    ereg($this->send_file_ips[$valid],$ip))      // right IP
					{
						$fname = $valid . $parts[1];
						$fname = !$win_user ? str_replace('\\','/',$fname) : str_replace('/','\\',$fname);
						return 'file:'.($win_user ? '//' : '' ).$fname;
					}
					// echo "<p>attached_local: link=$link, valid=$valid, trans='$trans', fname='$fname', parts=(x,'${parts[1]}','${parts[2]}')</p>\n";
				}
			}
			return False;
		}
	}
