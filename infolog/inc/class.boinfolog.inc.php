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
			'link_id2from'   => True,
			'cal_to_include' => True
		);
		var $enums;
		var $so;
		var $vfs;
		var $vfs_basedir='/infolog';
		var $valid_pathes = array();
		var $send_file_ips = array();

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
			$this->vfs = CreateObject('infolog.vfs');
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

			$this->read( $info_id);
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

				if ($title == $info['info_from'] || htmlentities($title) == $info['info_from'])
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

		function read($info_id)
		{
			$err = $this->so->read($info_id) === False;
			$data = &$this->so->data;

			if ($data['info_subject'] == (substr($data['info_des'],0,60).' ...'))
			{
				$data['info_subject'] = '';
			}
			$this->link_id2from($data);

			return $err ? False : $data;
		}

		function delete($info_id)
		{
			$this->link->unlink(0,'infolog',$info_id);

			$this->so->delete($info_id);
		}

		function write($values,$check_defaults=True,$touch_modified=True)
		{
			while (list($key,$val) = each($values))
			{
				if ($key[0] != '#' && substr($key,0,5) != 'info_')
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

		function search($order,$sort,$filter,$cat_id,$query,$action,$action_id,$ordermethod,&$start,&$total)
		{
			return $this->so->search($order,$sort,$filter,$cat_id,$query,$action,$action_id,$ordermethod,$start,$total);
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
			return $info ? $info['info_subject'] : False;
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
			$start = 0;
			$to_include = array();
			$date_wanted = sprintf('%04d/%02d/%02d',$args['year'],$args['month'],$args['day']);
			while ($infos = $this->search('info_startdate'.($do_events?'':' DESC'),'',
				"user$user".($do_events?'date':'opentoday').$date_wanted,'','','','','',$start,$total))
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
					$content = '';
					foreach($icons = array(
						$info['info_type']   => 'infolog',
						$info['info_status'] => 'infolog'
					) as $name => $app)
					{
						$content .= $GLOBALS['phpgw']->html->image($app,$name,lang($name),'border="0" width="15" height="15"').' ';
					}
					$content = $GLOBALS['phpgw']->html->a_href($content.'&nbsp;'.$title,$view).'<br>';

					$to_include[] = array(
						'starttime' => $info['info_startdate']+$this->tz_offset_sec,
						'endtime'   => ($info['info_enddate'] ? $info['info_enddate'] : $info['info_startdate'])+$this->tz_offset_sec,
						'title'     => $title,
						'view'      => $view,
						'icons'     => $icons,
						'content'   => $content
					);
				}
				if ($total <= ($start+=count($infos)))
				{
					break;	// no more availible
				}
			}
			//echo "boinfolog::cal_to_include("; print_r($args); echo ")<pre>"; print_r($to_include); echo "</pre>\n";
			return $to_include;
		}
	}
