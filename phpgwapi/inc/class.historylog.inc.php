<?php
	/**************************************************************************\
	* phpGroupWare API - Record history logging                                *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* Copyright (C) 2001 Joseph Engo                                           *
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

	// $Id$
	// $Source$

	class historylog
	{
		var $db;
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
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			$this->appname = $appname;
			$this->db      = $GLOBALS['phpgw']->db;
		}

		function delete($record_id)
		{
			$appname = intval($record_id) ? $this->appname : $record_id;
			$record_id = intval($record_id);
			$this->db->query('DELETE FROM phpgw_history_log WHERE'.
				($record_id ? " history_record_id='$record_id' AND" : '').
				" history_appname='$appname'",__LINE__,__FILE__);

			return $this->db->affected_rows();
		}

		function add($status,$record_id,$new_value,$old_value)
		{
			if ($new_value != $old_value)
			{
				$this->db->query("insert into phpgw_history_log (history_record_id,"
					. "history_appname,history_owner,history_status,history_new_value,history_old_value,history_timestamp) "
					. "values ('".intval($record_id)."','" . $this->appname . "','"
					. $GLOBALS['phpgw_info']['user']['account_id'] . "','$status','"
					. addslashes($new_value) . "','" . addslashes($old_value) . "','" . $this->db->to_timestamp(time())
					. "')",__LINE__,__FILE__);
			}
		}

		// array $filter_out
		function return_array($filter_out,$only_show,$_orderby = '',$sort = '', $record_id)
		{
			
			if (! $sort || ! $_orderby)
			{
				$orderby = 'order by history_timestamp,history_id';
			}
			else
			{
				$orderby = "order by $_orderby $sort";
			}

			while (is_array($filter_out) && list(,$_filter) = each($filter_out))
			{
				$filtered[] = "history_status != '$_filter'";
			}

			if (is_array($filtered))
			{
				$filter = ' and ' . implode(' and ',$filtered);
			}

			while (is_array($only_show) && list(,$_filter) = each($only_show))
			{
				$_only_show[] = "history_status='$_filter'";
			}

			if (is_array($_only_show))
			{
				$only_show_filter = ' and (' . implode(' or ',$_only_show). ')';
			}

			$this->db->query("select * from phpgw_history_log where history_appname='"
				. $this->appname . "' and history_record_id='".intval($record_id)."' $filter $only_show_filter "
				. "$orderby",__LINE__,__FILE__);
			while ($this->db->next_record())
			{
				$return_values[] = array(
					'id'         => $this->db->f('history_id'),
					'record_id'  => $this->db->f('history_record_id'),
					'owner'      => $GLOBALS['phpgw']->accounts->id2name($this->db->f('history_owner')),
//					'status'     => lang($this->types[$this->db->f('history_status')]),
					'status'     => ereg_replace(' ','',$this->db->f('history_status')),
					'new_value'  => $this->db->f('history_new_value'),
					'old_value'  => $this->db->f('history_old_value'),
					'datetime'   => $this->db->from_timestamp($this->db->f('history_timestamp'))
				);
			}
			return $return_values;
		}

		function return_html($filter_out,$orderby = '',$sort = '', $record_id)
		{
			$this->template   = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$this->nextmatchs = createobject('phpgwapi.nextmatchs');

			$this->template->set_file('_history','history_list.tpl');

			$this->template->set_block('_history','row_no_history');
			$this->template->set_block('_history','list');
			$this->template->set_block('_history','row');

			$this->template->set_var('lang_user',lang('User'));
			$this->template->set_var('lang_date',lang('Date'));
			$this->template->set_var('lang_action',lang('Action'));
			$this->template->set_var('lang_new_value',lang('New Value'));

			$this->template->set_var('th_bg',$GLOBALS['phpgw_info']['theme']['th_bg']);
			$this->template->set_var('sort_date',lang('Date'));
			$this->template->set_var('sort_owner',lang('User'));
			$this->template->set_var('sort_status',lang('Status'));
			$this->template->set_var('sort_new_value',lang('New value'));
			$this->template->set_var('sort_old_value',lang('Old value'));

			$values = $this->return_array($filter_out,array(),$orderby,$sort,$record_id);

			if (! is_array($values))
			{
				$this->template->set_var('tr_color',$GLOBALS['phpgw_info']['theme']['row_off']);
				$this->template->set_var('lang_no_history',lang('No history for this record'));
				$this->template->fp('rows','row_no_history');
				return $this->template->fp('out','list');
			}

			while (list(,$value) = each($values))
			{
				$this->nextmatchs->template_alternate_row_color(&$this->template);

				$this->template->set_var('row_date',$GLOBALS['phpgw']->common->show_date($value['datetime']));
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
