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
				
				if (htmlentities($title) == $info['info_from'])
				{
					$info['info_from'] = $title;	// correct old entries
				}
				if ($link['link_app'.$nr] == $not_app && $link['link_id'.$nr] == $not_id)
				{
					if ($title == $info['info_from'])
					{
						$info['info_from'] = '';
					}
					return False;
				}
				$info['info_link_view'] = $this->link->view($link['link_app'.$nr],$link['link_id'.$nr]);
				$info['info_link_title'] = $title;
				
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
;
			if ($data['info_link_title'] == $data['info_from'])
			{
				$data['info_from'] = '';
			}
			return $err ? False : $data;
		}

		function delete($info_id)
		{
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
				if (!$values['info_enddate'] && 
					($values['info_status'] == 'done' || $values['info_status'] == 'billed'))
				{
					$values['info_enddate'] = time();	// set enddate to today if status == done
				}
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
			}
			if ($values['info_link_id'] && isset($values['info_from']) && empty($values['info_from']))
			{
				$values['info_from'] = $this->link_id2from($values);
			}
			$values['info_datemodified'] = time();
			$values['info_modifier'] = $this->so->user;

			return $this->so->write($values);
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
