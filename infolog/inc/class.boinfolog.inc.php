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
			'init'		=>	True,		// in class soinfolog
			'read'		=> True,
			'write'		=>	True,
			'delete'		=>	True,
			'check_access'	=> True,
			'readProj'	=> True,
			'readAddr'	=>	True,
			'anzSubs'	=>	True,
			'readIdArray' => True,
			'accountInfo'	=> True,	// in class boinfolog (this class)
			'addr2name'	=>	True
		);
		var $enums;
		var $so;

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

			$this->read( $info_id);
		}

		function accountInfo($id,$account_data=0)
		{
			if (!$id) return '&nbsp;';

			if (!is_array($account_data))
			{
				$GLOBALS['phpgw']->accounts->read_repository();
				$account_data = $GLOBALS['phpgw']->accounts->data;
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
					$this->projects = createobject('projects.projects');
				}
				if (list( $proj ) = $this->projects->read_single_project( $proj_id))
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

		function readIdArray($order,$sort,$filter,$cat_id,$query,$action,$addr_id,
									$proj_id,$info_id,$ordermethod,&$start,&$total)
		{
			return $this->so->readIdArray($order,$sort,$filter,$cat_id,$query,
								  					$action,$addr_id,$proj_id,$info_id,
													$ordermethod,$start,$total);
		}
	}
