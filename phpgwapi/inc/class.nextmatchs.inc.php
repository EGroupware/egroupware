<?php
	/**************************************************************************\
	* phpGroupWare API - next                                                  *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* Handles limiting number of rows displayed                                *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
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
		/*!
		@class nextmatchs
		@abstract
		*/
	class nextmatchs
	{
		/*!
		@function set_icon
		@abstract ?
		@param $tpl ? 
		@param $align ?
		@param $img_src ?
		@param $label ?
		*/
		function set_icon(&$tpl,$align,$img_src,$label)
		{
			$tpl->set_var('align',$align);
			$tpl->set_var('img_src',PHPGW_IMAGES_DIR . $img_src);
			$tpl->set_var('label',lang($label));
			$tpl->parse('out','link',True);
		}
		/*!
		@function set_link
		@abstract ?
		@param $tpl ?
		@param $img_src ?
		@param $label ?
		@param $link ?
		@param $extravars ?
		*/
		function set_link(&$tpl,$align,$img_src,$label,$link,$extravars)
		{
			global $phpgw;

			$tpl->set_var('align',$align);
			if ($link)
			{
				$tpl->set_var('a_open','<a href="' . $phpgw->link($link,$extravars) . '">');
				$tpl->set_var('a_closed','</a>');
			}
			$tpl->set_var('img_src',PHPGW_IMAGES_DIR . $img_src);
			$tpl->set_var('label',lang($label));
			$tpl->parse('out','link',True);		
		}

		/*!
		@function show_tpl
		@abstract ?
		@param $sn ?
		@param $start ?
		@param $total ?
		@param $extra ?
		@param $twidth ?
		@param $bgtheme ?
		@param $search_obj ?
		@param $filter_obj ?
		@param $showsearch ?
		*/
		function show_tpl($sn,$start,$total,$extra, $twidth, $bgtheme,$search_obj=0,$filter_obj=1,$showsearch=1)
		{
			global $filter, $qfield, $start, $order, $sort, $query, $phpgw, $phpgw_info;
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_file(array(
				'nextmatchs' => 'nextmatchs.tpl'
			));

			$tpl->set_var('form_action',$phpgw->link($sn));
			$tpl->set_var('filter_value',$filter);
			$tpl->set_var('qfield_value',$qfield);
			$tpl->set_var('start_value',$start);
			$tpl->set_var('order_value',$order);
			$tpl->set_var('sort_value',$sort);
			$tpl->set_var('query_value',$query);
			$tpl->set_var('table_width',$twidth);
			$tpl->set_var('th_bg',$phpgw_info['theme']['th_bg']);

			$tpl->set_var('left',$this->left($sn,$start,$total,$extra));

			if ($showsearch == 1)
			{
				$tpl->set_var(search,$this->search($search_obj));
			}

			if ($filter_obj)
			{
				$tpl->set_var('filter',$this->filter($filter_obj));
			}
			else
			{
				$tpl->set_var('filter','');
			}
			$tpl->set_var('right',$this->right($sn,$start,$total,$extra));

			return $tpl->fp('out','nextmatchs');
		} 
		/*!
		@function left
		@abstract ?
		@param $scriptname ?
		@param $start ?
		@param $total ?
		@param $extradate ?
		*/  
		function left($scriptname,$start,$total,$extradata = '')
		{
			global $filter, $qfield, $order, $sort, $query, $phpgw_info, $phpgw;
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_file(array(
				'link'       => 'nextmatchs_link.tpl'
			));

			$maxmatchs = $phpgw_info['user']['preferences']['common']['maxmatchs'];

			if (($start != 0) && ($start > $maxmatchs))
			{
				$this->set_link(&$tpl,'left','/first.gif','First page',$scriptname,'start=0&order=' . $order . '&filter='
						. $filter . '&qfield=' . $qfield . '&sort=' . $sort . '&query=' . $query . $extradata);
			}
			else
			{
				$this->set_icon(&$tpl,'left','/first-grey.gif','First page');
			}

			if ($start != 0)
			{
				// Changing the sorting order screaws up the starting number
				if (($start - $maxmatchs) < 0)
				{
					$t_start = 0;
				}
				else
				{
					$t_start = ($start - $maxmatchs);
				}

				$this->set_link(&$tpl,'left','/left.gif','Previous page',$scriptname,'start=' . $t_start
					. '&order=' . $order . '&filter=' . $filter . '&qfield=' . $qfield . '&sort=' . $sort
					. '&query=' . $query . $extradata);
			}
			else
			{
				$this->set_icon(&$tpl,'left','/left-grey.gif','Previous page');
			}

			return $tpl->fp('out_','out');
		} /* left() */
		
		/*!
		@function right
		@abstract ?
		@param $scriptname ?
		@param $start ?
		@param $total ?
		@param $extradate ?
		*/
		function right($scriptname,$start,$total,$extradata = '')
		{
			global $filter, $qfield, $order, $sort, $query, $phpgw_info, $phpgw;
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_file(array(
				'link'       => 'nextmatchs_link.tpl'
			));
			$maxmatchs = $phpgw_info['user']['preferences']['common']['maxmatchs'];

			if (($total > $maxmatchs) && ($total > $start + $maxmatchs))
			{
				$this->set_link(&$tpl,'right','/right.gif','Next page',$scriptname,'start='
					. ($start+$maxmatchs) . '&order=' . $order . '&filter=' . $filter . '&qfield=' . $qfield
					. '&sort=' . $sort . '&query=' . $query . $extradata);
			}
			else
			{
				$this->set_icon(&$tpl,'right','/right-grey.gif','Next page');
			}

			if (($start != $total - $maxmatchs) && (($total - $maxmatchs) > ($start + $maxmatchs)))
			{
				$this->set_link(&$tpl,'right','/last.gif','Last page',$scriptname,'start='
					. ($total-$maxmatchs) . '&order=' . $order . '&filter=' . $filter . '&qfield=' .$qfield
					. '&sort=' . $sort . '&query=' . $query . $extradata);
			}
			else
			{
				$this->set_icon(&$tpl,'right','/last-grey.gif','Last page');
			}

			return $tpl->fp('out_','out');
		} /* right() */
		
		/*!
		@function search
		@abstract ?
		@param $search_obj default 0
		*/
		function search($search_obj=0)
		{
			global $query;
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_file(array(
				'search'       => 'nextmatchs_search.tpl'
			));
			$tpl->set_var('query_value',$query);
			$tpl->set_var('searchby',$this->searchby($search_obj));
			$tpl->set_var('lang_search',lang('Search'));
      
			return $tpl->fp('out','search');
		} /* search() */

		/*!
		@function filterobj
		@abstract ?
		@param $filtertable
		@param $indxfieldname ?
		@param $strfieldname ?
		*/
		function filterobj($filtertable, $idxfieldname, $strfieldname)
		{
			global $phpgw;

			$filter_obj = array(array('none','show all'));
			$index = 0;

			$phpgw->db->query("SELECT $idxfieldname, $strfieldname from $filtertable",__LINE__,__FILE__);
			while($phpgw->db->next_record())
			{
				$index++;
				$filter_obj[$index][0] = $phpgw->db->f($idxfieldname);
				$filter_obj[$index][1] = $phpgw->db->f($strfieldname);
			}
      
			return $filter_obj;
		} /* filterobj() */

		/*!
		@function searchby
		@abstract ?
		@param $search_obj ?
		*/  
		function searchby($search_obj)
		{
			global $qfield, $phpgw, $phpgw_info;

			if (is_array($search_obj))
			{
				$str = '<select name="qfield">';
          
				$indexlimit = count($search_obj);
				for ($index=0; $index<$indexlimit; $index++)
				{
					if ($qfield == '')
					{
						$qfield = $search_obj[$index][0];
					}
              
					$str .= '<option value="' . $search_obj[$index][0] . '"';

					if ($qfield == $search_obj[$index][0])
					{
						$str .= ' selected';
					}

					$str .= '>' . lang($search_obj[$index][1]) . '</option>';
				}
				$str .= '</select>' . "\n";
			}
			return $str;
		} /* searchby() */

		/*!
		@function filter
		@abstract ?
		@param $filter_obj
		*/  
		function filter($filter_obj)
		{
			global $filter, $phpgw, $phpgw_info;
			$tpl = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$tpl->set_file(array(
				'filter' => 'nextmatchs_filter.tpl'
			));

			if (is_long($filter_obj))
			{
				if ($filter_obj == 1)
				{
					$user_groups = $phpgw->accounts->memberships($phpgw_info['user']['account_id']);
					$indexlimit = count($user_groups);
              
					$filter_obj = array(array('none',lang('Show all')),
                                  array('private',lang('Only yours')));
					for ($index=0; $index<$indexlimit; $index++)
					{
						$filter_obj[2+$index][0] = $user_groups[$index]['account_id'];
						$filter_obj[2+$index][1] = 'Group - ' . $user_groups[$index]['account_name'];
					}
				}
			}
      
			if (is_array($filter_obj))
			{
				$str .= '<select name="filter">'."\n";
          
				$indexlimit = count($filter_obj);

				for ($index=0; $index<$indexlimit; $index++)
				{
					if ($filter == '')
					{
						$filter = $filter_obj[$index][0];
					}
              
					$str .= '<option value="' . $filter_obj[$index][0] . '"';

					if ($filter == $filter_obj[$index][0])
					{
						$str .= ' selected';
					}

					$str .= '>' . $filter_obj[$index][1] . '</option>'."\n";
				}
          
				$str .= '</select>'."\n";
				$tpl->set_var('select',$str);
				$tpl->set_var('lang_filter',lang('Filter'));
			}
     
			return $tpl->fp('out','filter');
		} /* filter() */

		/*!
		@function alternate_row_color
		@abstract alternate row colour
		@param $currentcolor default ''
		*/
		function alternate_row_color($currentcolor = '')
		{
			global $phpgw_info;

			if (! $currentcolor)
			{
				global $tr_color;
				$currentcolor = $tr_color;
			}
    
			if ($currentcolor == $phpgw_info['theme']['row_on'])
			{
				$tr_color = $phpgw_info['theme']['row_off'];
			}
			else
			{
				$tr_color = $phpgw_info['theme']['row_on'];
			}

			return $tr_color;
		}

		// If you are using the common bgcolor="{tr_color}"
		// This function is a little cleanier approch
		/*!
		@function template_alternate_row_color
		@abstract ?
		@param $tpl ?
		*/
		function template_alternate_row_color(&$tpl)
		{
			$tpl->set_var('tr_color',$this->alternate_row_color());
		}
		
		/*!
		@function show_sort_order
		@abstract ?
		@param $sort ?
		@param $var ?
		@param $order ?
		@param $program ?
		@param $text ?
		@param $extra default ''
		*/
		function show_sort_order($sort,$var,$order,$program,$text,$extra='')
		{
			global $phpgw, $filter, $qfield, $start, $query;

			if (($order == $var) && ($sort == 'ASC'))
			{
				$sort = 'DESC';
			}
			else if (($order == $var) && ($sort == 'DESC'))
			{
				$sort = 'ASC';
			}
			else
			{
				$sort = 'ASC';
			}

			return '<a href="' . $phpgw->link($program,"order=$var&sort=$sort&filter=$filter&"
				. "qfield=$qfield&start=$start&query=$query" . $extra) . '">' . $text . '</a>';
		}

	}		// End of nextmatchs class
?>
