<?php
	/**************************************************************************\
	* eGroupWare - InfoLog                                                     *
	* http://www.egroupware.org                                                *
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
			'link_id2from'   => True,
			'cal_to_include' => True
		);
		var $enums;
		var $so;
		var $vfs;
		var $vfs_basedir='/infolog';
		var $valid_pathes = array();
		var $send_file_ips = array();

		var $xmlrpc_methods = array();
		var $soap_functions = array(
			'read' => array(
				'in'  => array('int'),
				'out' => array('array')
			),
			'search' => array(
				'in'  => array('array'),
				'out' => array('array')
			),
			'write' => array(
				'in'  => array('array'),
				'out' => array()
			),
			'delete' => array(
				'in'  => array('int'),
				'out' => array()
			),
			'categories' => array(
				'in'  => array('bool'),
				'out' => array('array')
			),
		);
		var $xmlrpc = False;	// called via xmlrpc

		function boinfolog( $info_id = 0)
		{
			$this->enums = $this->stock_enums = array(
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
			$this->status = $this->stock_status = array(
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
			$this->link = CreateObject('infolog.bolink');

			$this->config = CreateObject('phpgwapi.config');
			$this->config->read_repository();

			$this->customfields = array();
			if ($this->config->config_data)
			{
				$this->link_pathes   = $this->config->config_data['link_pathes'];
				$this->send_file_ips = $this->config->config_data['send_file_ips'];

				if (isset($this->config->config_data['status']) && is_array($this->config->config_data['status']))
				{
					foreach($this->config->config_data['status'] as $key => $data)
					{
						if (!is_array($this->status[$key]))
						{
							$this->status[$key] = array();
						}
						$this->status[$key] += $this->config->config_data['status'][$key];
					}
				}
				if (isset($this->config->config_data['types']) && is_array($this->config->config_data['types']))
				{
					//echo "stock-types:<pre>"; print_r($this->enums['type']); echo "</pre>\n";
					//echo "config-types:<pre>"; print_r($this->config->config_data['types']); echo "</pre>\n";
					$this->enums['type'] += $this->config->config_data['types'];
					//echo "types:<pre>"; print_r($this->enums['type']); echo "</pre>\n";
				}
				if (isset($this->config->config_data['customfields']) && is_array($this->config->config_data['customfields']))
				{
					$this->customfields = $this->config->config_data['customfields'];
				}
			}
			$this->tz_offset = $GLOBALS['phpgw_info']['user']['preferences']['common']['tz_offset'];
			$this->tz_offset_sec = 60*60*$this->tz_offset;

			// are we called via xmlrpc?
			$this->xmlrpc = is_object($GLOBALS['server']) && $GLOBALS['server']->last_method;

			if ($info_id)
			{
				$this->read( $info_id );
			}
			else
			{
				$this->init();
			}
		}

		/*!
		@function has_customfields
		@abstract checks if there are customfields for typ $typ
		*/
		function has_customfields($typ)
		{
			foreach($this->customfields as $name => $field)
			{
				if (empty($field['typ']) || $field['typ'] == $typ)
				{
					return True;
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

		function link_id2from(&$info,$not_app='',$not_id='')
		{
			//echo "<p>boinfolog::link_id2from(subject='$info[info_subject]', link_id='$info[info_link_id], from='$info[info_from]', not_app='$not_app', not_id='$not_id')";
			if ($info['info_link_id'] > 0 &&
				 ($link = $this->link->get_link($info['info_link_id'])) !== False)
			{
				$nr = $link['link_app1'] == 'infolog' && $link['link_id1'] == $info['info_id'] ? '2' : '1';
				$title = $this->link->title($link['link_app'.$nr],$link['link_id'.$nr]);

				if ($title == $info['info_from'] || @htmlentities($title) == $info['info_from'])
				{
					$info['info_from'] = '';
				}
				if ($link['link_app'.$nr] == $not_app && $link['link_id'.$nr] == $not_id)
				{
					return False;
				}
				$info['info_link_view'] = $this->link->view($link['link_app'.$nr],$link['link_id'.$nr]);
				$info['info_link_title'] = !empty($info['info_from']) ? $info['info_from'] : $title;

				//echo " title='$title'</p>\n";
				return $info['blur_title'] = $title;
			}
			else
			{
				$info['info_link_title'] = $info['info_from'];
				$info['info_link_id'] = 0;	// link might have been deleted
			}
			return False;
		}

		function subject_from_des($des)
		{
			return substr($des,0,60).' ...';
		}

		function read($info_id)
		{
			if (is_array($info_id))
			{
				$info_id = (int)$info_id['info_id'];
			}

			if ($this->so->read($info_id) === False)
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
				}
				return False;
			}
			if (!$this->check_access($info_id,PHPGW_ACL_READ))	// check behind read, to prevent a double read
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
				}
				return False;
			}
			$data = &$this->so->data;

			if ($data['info_subject'] == $this->subject_from_des($data['info_des']))
			{
				$data['info_subject'] = '';
			}
			$this->link_id2from($data);

			if ($this->xmlrpc)
			{
				$data = $this->data2xmlrpc($data);
			}
			return $data;
		}

		function delete($info_id,$delete_children=False,$new_parent=False)
		{
			if (is_array($info_id))
			{
				$delete_children = $info_id['delete_children'];
				$new_parent = $info_id['new_parent'];
				$info_id = (int)(isset($info_id[0]) ? $info_id[0] : (isset($info_id['info_id']) ? $info_id['info_id'] : $info_id['info_id']));
			}
			if ($this->so->read($info_id) === False)
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['not_exist'],$GLOBALS['xmlrpcstr']['not_exist']);
				}
				return False;
			}
			if (!$this->check_access($info_id,PHPGW_ACL_DELETE))
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
				}
				return False;
			}

			$this->link->unlink(0,'infolog',$info_id);

			$this->so->delete($info_id,$delete_children,$new_parent);
		}

		function write($values,$check_defaults=True,$touch_modified=True)
		{
			foreach($values as $key => $val)
			{
				if ($key[0] != '#' && substr($key,0,5) != 'info_')
				{
					$values['info_'.$key] = $val;
					unset($values[$key]);
				}
			}
			$status_only = $values['info_id'] && $values['info_responsible'] == $this->user && 
				!$this->check_access($values['info_id'],PHPGW_ACL_EDIT);	// responsible has implicit right to change status

			if ($values['info_id'] && !$this->check_access($values['info_id'],PHPGW_ACL_EDIT) && !$status_only ||
			    !$values['info_id'] && $values['info_id_parent'] && !$this->check_access($values['info_id_parent'],PHPGW_ACL_ADD))
			{
				if ($this->xmlrpc)
				{
					$GLOBALS['server']->xmlrpc_error($GLOBALS['xmlrpcerr']['no_access'],$GLOBALS['xmlrpcstr']['no_access']);
				}
				return False;
			}
			if ($this->xmlrpc)
			{
				$values = $this->xmlrpc2data($values);
			}
			if ($status_only)	// make sure only status gets writen
			{
				$set_enddate = !$values['info_enddate'] &&	// set enddate of finished job, only if its not already set 
					($values['info_status'] == 'done' || $values['info_status'] == 'billed');

				$values = array(
					'info_id' => $values['info_id'],
					'info_status' => $values['info_status'],
				);
				if ($set_enddate)
				{
					$values['info_enddate'] = time();
				}
				$check_defaults = False;
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
				if (isset($values['info_subject']) && empty($values['info_subject']))
				{
					$values['info_subject'] = $this->subject_from_des($values['info_des']);
				}
			}
			if ($values['info_link_id'] && isset($values['info_from']) && empty($values['info_from']))
			{
				$values['info_from'] = $this->link_id2from($values);
			}
			if ($touch_modified || !$values['info_datemodified'])
			{
				$values['info_datemodified'] = time();
			}
			if ($touch_modified || !$values['info_modifier'])
			{
				$values['info_modifier'] = $this->so->user;
			}
			return $this->so->write($values);
		}

		function anzSubs( $info_id )
		{
			return $this->so->anzSubs( $info_id );
		}

		/*!
		@function search
		@abstract searches InfoLog for a certain pattern in $query
		@syntax search( $query )
		@param $query[order] column-name to sort after
		@param $query[sort] sort-order DESC or ASC
		@param $query[filter] string with combination of acl-, date- and status-filters, eg. 'own-open-today' or ''
		@param $query[cat_id] category to use or 0 or unset
		@param $query[search] pattern to search, search is done in info_from, info_subject and info_des
		@param $query[action] / $query[action_id] if only entries linked to a specified app/entry show be used
		@param &$query[start], &$query[total] nextmatch-parameters will be used and set if query returns less entries
		@param $query[col_filter] array with column-name - data pairs, data == '' means no filter (!)
		@returns array with id's as key of the matching log-entries
		*/
		function search(&$query)
		{
			//echo "<p>boinfolog::search(".print_r($query,True).")</p>\n";
			$ret = $this->so->search($query);
			if ($this->xmlrpc && is_array($ret))
			{
				$infos = $ret;
				$ret = array();
				foreach($infos as $id => $data)
				{
					$ret[] = $this->data2xmlrpc($data);
				}
			}
			//echo "<p>boinfolog::search(".print_r($query,True).")=<pre>".print_r($ret,True)."</pre>\n";
			return $ret;
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
			if (!$info)
			{
				return False;
			}
			return !empty($info['info_subject']) ? $info['info_subject'] :
				$this->subject_from_des($info['info_descr']);
		}

		/*!
		@function link_query
		@syntax link_query(  $pattern  )
		@author ralfbecker
		@abstract query infolog for entries matching $pattern
		*/
		function link_query( $pattern )
		{
			$query = array(
				'search' => $pattern,
				'start'  => 0,
			);
			$ids = $this->search($query);
			$content = array();
			if (is_array($ids))
			{
				foreach($ids as $id => $info )
				{
					$content[$id] = $this->link_title($id);
				}
			}
			return $content;
		}

		/*!
		@function cal_to_include
		@syntax cal_to_include( $args )
		@author ralfbecker
		@abstract hook called be calendar to include events or todos in the cal-dayview
		@param $args[year], $args[month], $args[day] date of the events
		@param $args[owner] owner of the events
		@param $args[location] calendar_include_{events|todos}
		@returns array of events (array with keys starttime, endtime, title, view, icon, content)
		*/
		function cal_to_include($args)
		{
			//echo "<p>cal_to_include("; print_r($args); echo ")</p>\n";
			$user = intval($args['owner']);
			if ($user <= 0 && !checkdate($args['month'],$args['day'],$args['year']))
			{
				return False;
			}
			if (!is_object($GLOBALS['phpgw']->html))
			{
				$GLOBALS['phpgw']->html = CreateObject('phpgwapi.html');
			}
			$GLOBALS['phpgw']->translation->add_app('infolog');

			$do_events = $args['location'] == 'calendar_include_events';
			$to_include = array();
			$date_wanted = sprintf('%04d/%02d/%02d',$args['year'],$args['month'],$args['day']);
			$query = array(
				'order' => 'info_startdate',
				'sort'  => $do_events ? 'ASC' : 'DESC',
				'filter'=> "user$user".($do_events ? 'date' : 'opentoday').$date_wanted,
				'start' => 0,
			);
			while ($infos = $this->search($query))
			{
				foreach($infos as $info)
				{
					$time = intval(date('Hi',$info['info_startdate']+$this->tz_offset_sec));
					$date = date('Y/m/d',$info['info_startdate']+$this->tz_offset_sec);
					if ($do_events && !$time ||
					    !$do_events && $time && $date == $date_wanted)
					{
						continue;
					}
					$title = ($do_events?$GLOBALS['phpgw']->common->formattime(date('H',$info['info_startdate']+$this->tz_offset_sec),date('i',$info['info_startdate']+$this->tz_offset_sec)).' ':'').
						$info['info_subject'];
					$view = $this->link->view('infolog',$info['info_id']);
					$content=array();
					foreach($icons = array(
						$info['info_type']   => 'infolog',
						$info['info_status'] => 'infolog'
					) as $name => $app)
					{
						$content[] = $GLOBALS['phpgw']->html->image($app,$name,lang($name),'border="0" width="15" height="15"').' ';
					}
					$content[] = $GLOBALS['phpgw']->html->a_href($title,$view);
					$content = $GLOBALS['phpgw']->html->table(array(1 => $content));

					$to_include[] = array(
						'starttime' => $info['info_startdate']+$this->tz_offset_sec,
						'endtime'   => ($info['info_enddate'] ? $info['info_enddate'] : $info['info_startdate'])+$this->tz_offset_sec,
						'title'     => $title,
						'view'      => $view,
						'icons'     => $icons,
						'content'   => $content
					);
				}
				if ($query['total'] <= ($query['start']+=count($infos)))
				{
					break;	// no more availible
				}
			}
			//echo "boinfolog::cal_to_include("; print_r($args); echo ")<pre>"; print_r($to_include); echo "</pre>\n";
			return $to_include;
		}

		function list_methods($_type='xmlrpc')
		{
			/*
			**  This handles introspection or discovery by the logged in client,
			**  in which case the input might be an array.  The server always calls
			**  this function to fill the server dispatch map using a string.
			*/

			if (is_array($_type))
			{
				$_type = $_type['type'] ? $_type['type'] : $_type[0];
			}

			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'read' => array(
							'function'  => 'read',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Read one record by passing its id.')
						),
						'search' => array(
							'function'  => 'search',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Returns a list / search for records.')
						),
						'write' => array(
							'function'  => 'write',
							'signature' => array(array(xmlrpcStruct,xmlrpcStruct)),
							'docstring' => lang('Write (add or update) a record by passing its fields.')
						),
						'delete' => array(
							'function'  => 'delete',
							'signature' => array(array(xmlrpcInt,xmlrpcInt)),
							'docstring' => lang('Delete one record by passing its id.')
						),
						'categories' => array(
							'function'  => 'categories',
							'signature' => array(array(xmlrpcBoolean,xmlrpcBoolean)),
							'docstring' => lang('List all categories')
						),
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
		}

		function data2xmlrpc($data)
		{
			$data['rights'] = $this->so->grants[$data['info_owner']];

			// translate timestamps
			foreach(array('info_startdate','info_enddate','info_datemodified') as $name)
			{
				if (isset($data[$name]))
				{
					$data[$name] = $GLOBALS['server']->date2iso8601($data[$name]);
				}
			}
			// translate cat_id
			if (isset($data['info_cat']))
			{
				$data['info_cat'] = $GLOBALS['server']->cats2xmlrpc(array($data['info_cat']));
			}
			foreach($data as $name => $val)
			{
				if (substr($name,0,5) == 'info_')
				{
					unset($data[$name]);
					$data[substr($name,5)] = $val;
				}
			}
			return $data;
		}

		function xmlrpc2data($data)
		{
			foreach($data as $name => $val)
			{
				if (substr($name,0,5) != 'info_')
				{
					unset($data[$name]);
					$data['info_'.$name] = $val;
				}
			}
			// translate timestamps
			foreach(array('info_startdate','info_enddate','info_datemodified') as $name)
			{
				if (isset($data[$name]))
				{
					$data[$name] = $GLOBALS['server']->iso86012date($data[$name],True);
				}
			}
			// translate cat_id
			if (isset($data['info_cat']))
			{
				$cats = $GLOBALS['server']->xmlrpc2cats($data['info_cat']);
				$data['info_cat'] = (int)$cats[0];
			}
			return $data;
		}

		// return array with all infolog categories (for xmlrpc)
		function categories($complete = False)
		{
			return $this->xmlrpc ? $GLOBALS['server']->categories($complete) : False;
		}
	}
