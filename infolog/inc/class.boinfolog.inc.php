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
			'anzSubs'        => True,
			'search'         => True,
			'get_rows'       => True,
			'accountInfo'    => True,	// in class boinfolog (this class)
/*			'readProj'       => True,
			'readAddr'       => True,
			'addr2name'      => True,*/
			'attach_file'    => True,
			'delete_attached'=> True,
			'info_attached'  => True,
			'list_attached'  => True,
			'read_attached'  => True,
			'attached_local' => True,
			'link_title'     => True,
			'link_query'     => True,
			'link_id2from'   => True
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
/*				'status'   => array(
					'offer' => 'offer','ongoing' => 'ongoing','call' => 'call',
					'will-call' => 'will-call','done' => 'done',
					'billed' => 'billed' ),
*/				'confirm'   => array(
					'not' => 'not','accept' => 'accept','finish' => 'finish',
					'both' => 'both' ),
				'type'      => array(
					'task' => 'task','phone' => 'phone','note' => 'note'
				/*	,'confirm' => 'confirm','reject' => 'reject','email' => 'email',
					'fax' => 'fax' not implemented so far */ )
			);
			$this->status = array(
				'defaults' => array(
					'task' => 'ongoing', 'phone' => 'call', 'note' => 'done'),
				'task' => array(
					'offer' => 'offer','ongoing' => 'ongoing','done' => 'done',
					'0%' => '0%', '10%' => '10%', '20%' => '20%', '30%' => '30%', '40%' => '40%',
					'50%' => '50%', '60%' => '60%', '70%' => '70%', '80%' => '80%', '90%' => '90%',
					'billed' => 'billed' ),
				'phone' => array(
					'call' => 'call','will-call' => 'will-call',
					'done' => 'done', 'billed' => 'billed' ),
				'note' => array(
					'ongoing' => 'ongoing', 'done' => 'done'
			));

			$this->so = CreateObject('infolog.soinfolog');
			$this->vfs = CreateObject('infolog.vfs');
			$this->link = CreateObject('infolog.bolink');

			$this->config = CreateObject('phpgwapi.config');
			$this->config->read_repository();

			if ($this->config->config_data)
			{
				$this->link_pathes   = $this->config->config_data['link_pathes'];
				$this->send_file_ips = $this->config->config_data['send_file_ips'];
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

		function link_id2from(&$info,$not_app='',$not_id='')
		{
			//echo "<p>boinfolog::link_id2from(subject='$info[info_subject]', link_id='$info[info_link_id], from='$info[info_from]')";
			if ($info['info_link_id'] > 0 &&
				 ($link = $this->link->get_link($info['info_link_id'])) !== False)
			{
				$nr = $link['link_app1'] == 'infolog' && $link['link_id1'] == $info['info_id'] ? '2' : '1';
				$title = $this->link->title($link['link_app'.$nr],$link['link_id'.$nr]);
				if ($link['link_app'.$nr] == $not_app && $link['link_id'.$nr] == $not_id)
				{
					if ($title == $info['info_from'])
					{
						$info['info_from'] = '';
					}
					return False;
				}
				if ($info['info_from'] == '' || $info['info_from'] == $title)
				{
					$info['info_link_view'] = $this->link->view($link['link_app'.$nr],$link['link_id'.$nr]);
					$info['info_from'] = $info['info_link_title'] = $title;
				}
				//echo " title='$title'</p>\n";
				return $title;
			}
			return False;
		}

		function read($info_id)
		{
			$err = $this->so->read($info_id) === False;
			$data = &$this->so->data;

			if ($data['info_subject'] == (substr($data['info_des'],0,60).' ...'))
			{
				$data['info_subject'] = '';
			}
			$this->link_id2from($data);
			if ($data['info_link_title'] == $data['info_from'])
			{
				$data['info_from'] = '';
			}
			return $err ? False : $data;
		}

		function delete($info_id)
		{
			$this->delete_attached($info_id);
			$this->link->unlink(0,'infolog',$info_id);

			$this->so->delete($info_id);
		}

		function write($values,$check_defaults=True)
		{
			while (list($key,$val) = each($values))
			{
				if (substr($key,0,5) != 'info_')
				{
					$values['info_'.$key] = $val;
					unset($values[$key]);
				}
			}
			if ($check_defaults)
			{
				if ($values['info_responsible'] && $values['info_status'] == 'offer')
				{
					$values['info_status'] = 'ongoing';   // have to match if not finished
				}
				if (!$values['info_id'] && !$values['info_owner'])
				{
					$values['info_owner'] = $this->so->user;
				}
				if (!$values['info_subject'])
				{
					$values['info_subject'] = substr($values['info_des'],0,60).' ...';
				}
				if ($values['info_link_id'] && empty($values['info_from']))
				{
					$this->link_id2from($values);
				}
			}
			$values['info_datemodified'] = time();
			$values['info_modifier'] = $this->so->user;

			$this->so->write($values);
		}

		function anzSubs( $info_id )
		{
			return $this->so->anzSubs( $info_id );
		}

		function search($order,$sort,$filter,$cat_id,$query,$action,$action_id,
							 $ordermethod,&$start,&$total)
		{
			return $this->so->search($order,$sort,$filter,$cat_id,$query,
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
		
		/*!
		@function link_title
		@syntax link_title(  $id  )
		@author ralfbecker
		@abstract get title for an infolog entry identified by $id
		*/
		function link_title( $info )
		{
			if (!is_array($info))
			{
				$info = $this->read( $info );
			}
			return $info['info_subject'];
		}

		/*!
		@function link_query
		@syntax link_query(  $pattern  )
		@author ralfbecker
		@abstract query infolog for entries matching $pattern
		*/
		function link_query( $pattern )
		{
			$start = $total = 0;
			$ids = $this->search('','','','',$pattern,'','','',&$start,&$total);
			$content = array();
			while (is_array($ids) && list( $id,$info ) = each( $ids ))
			{
				$content[$id] = $this->link_title($id);
			}
			return $content;
		}
	}
