<?php
	/**************************************************************************\
	* eGroupWare API - Record history logging                                  *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* Copyright (C) 2001 Joseph Engo                                           *
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            *
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

	// $Id$

	class historylog
	{
		var $db;
		var $table = 'egw_history_log';
		var $appname;
		var $template;
		var $nextmatchs;
		var $types = array(
			'C' => 'Created',
			'D' => 'Deleted',
			'E' => 'Edited'
		);
		var $alternate_handlers = array();

		function historylog($appname='')
		{
			if (!$appname)
			{
				$appname = $GLOBALS['egw_info']['flags']['currentapp'];
			}
			$this->appname = $appname;
			
			if (is_object($GLOBALS['egw_setup']->db))
			{
				$this->db      = clone($GLOBALS['egw_setup']->db);
			}
			else
			{
				$this->db      = clone($GLOBALS['egw']->db);
			}
			$this->db->set_app('phpgwapi');
		}

		function delete($record_id)
		{
			if (is_array($record_id) || is_numeric($record_id))
			{
				$where = array(
					'history_record_id' => $record_id,
					'history_appname'   => $this->appname,
				);
			}
			else
			{
				$where = array('history_appname'   => $record_id);
			}
			$this->db->delete($this->table,$where,__LINE__,__FILE__);

			return $this->db->affected_rows();
		}

		function add($status,$record_id,$new_value,$old_value)
		{
			if ($new_value != $old_value)
			{
				$this->db->insert($this->table,array(
					'history_record_id' => $record_id,
					'history_appname'   => $this->appname,
					'history_owner'     => $GLOBALS['egw_info']['user']['account_id'],
					'history_status'    => $status,
					'history_new_value' => $new_value,
					'history_old_value' => $old_value,
					'history_timestamp' => time(),
				),false,__LINE__,__FILE__);
			}
		}

		// array $filter_out
		function return_array($filter_out,$only_show,$_orderby = '',$sort = '', $record_id)
		{
			
			if (!$_orderby || !preg_match('/^[a-z0-9_]+$/i',$_orderby) || !preg_match('/^(asc|desc)?$/i',$sort))
			{
				$orderby = 'ORDER BY history_timestamp,history_id';
			}
			else
			{
				$orderby = "ORDER BY $_orderby $sort";
			}

			$where = array(
				'history_appname'   => $this->appname,
				'history_record_id' => $record_id,
			);
			if (is_array($filter_out))
			{
				foreach($filter_out as $_filter)
				{
					$where[] = 'history_status != '.$this->db->quote($_filter);
				}
			}
			if (is_array($only_show) && count($only_show))
			{
				$to_or = array();
				foreach($only_show as $_filter)
				{
					$to_or[] = 'history_status = '.$this->db->quote($_filter);
				}
				$where[] = '('.implode(' OR ',$to_or).')';
			}
			
			$this->db->select($this->table,'*',$where,__LINE__,__FILE__,false,$orderby);
			while ($this->db->next_record())
			{
				$return_values[] = array(
					'id'         => $this->db->f('history_id'),
					'record_id'  => $this->db->f('history_record_id'),
					'owner'      => $GLOBALS['egw']->accounts->id2name($this->db->f('history_owner')),
					'status'     => str_replace(' ','',$this->db->f('history_status')),
					'new_value'  => $this->db->f('history_new_value'),
					'old_value'  => $this->db->f('history_old_value'),
					'datetime'   => $this->db->from_timestamp($this->db->f('history_timestamp'))
				);
			}
			return $return_values;
		}

		function return_html($filter_out,$orderby = '',$sort = '', $record_id)
		{
			$this->template   =& CreateObject('phpgwapi.Template',EGW_TEMPLATE_DIR);
			$this->nextmatchs =& CreateObject('phpgwapi.nextmatchs');

			$this->template->set_file('_history','history_list.tpl');

			$this->template->set_block('_history','row_no_history');
			$this->template->set_block('_history','list');
			$this->template->set_block('_history','row');

			$this->template->set_var('lang_user',lang('User'));
			$this->template->set_var('lang_date',lang('Date'));
			$this->template->set_var('lang_action',lang('Action'));
			$this->template->set_var('lang_new_value',lang('New Value'));

			$this->template->set_var('th_bg',$GLOBALS['egw_info']['theme']['th_bg']);
			$this->template->set_var('sort_date',lang('Date'));
			$this->template->set_var('sort_owner',lang('User'));
			$this->template->set_var('sort_status',lang('Status'));
			$this->template->set_var('sort_new_value',lang('New value'));
			$this->template->set_var('sort_old_value',lang('Old value'));

			$values = $this->return_array($filter_out,array(),$orderby,$sort,$record_id);

			if (!is_array($values))
			{
				$this->template->set_var('tr_color',$GLOBALS['egw_info']['theme']['row_off']);
				$this->template->set_var('lang_no_history',lang('No history for this record'));
				$this->template->fp('rows','row_no_history');
				return $this->template->fp('out','list');
			}

			foreach($values as $value)
			{
				$this->nextmatchs->template_alternate_row_color($this->template);

				$this->template->set_var('row_date',$GLOBALS['egw']->common->show_date($value['datetime']));
				$this->template->set_var('row_owner',$value['owner']);

				if ($this->alternate_handlers[$value['status']])
				{
					eval('\$s = ' . $this->alternate_handlers[$value['status']] . '(' . $value['new_value'] . ');');
					$this->template->set_var('row_new_value',$s);
					unset($s);

					eval('\$s = ' . $this->alternate_handlers[$value['status']] . '(' . $value['old_value'] . ');');
					$this->template->set_var('row_old_value',$s);
					unset($s);
				}
				else
				{
					$this->template->set_var('row_new_value',$value['new_value']);
					$this->template->set_var('row_old_value',$value['old_value']);
				}

				$this->template->set_var('row_status',$this->types[$value['status']]);

				$this->template->fp('rows','row',True);
			}
			return $this->template->fp('out','list');
		}
	}
