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
		var $maxmatches;
		var $action;
		var $template;

		function nextmatchs()
		{
			global $phpgw_info, $menuaction;

			$this->template = createobject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
			$this->template->set_file(array(
				'_nextmatchs' => 'nextmatchs.tpl'
			));
			$this->template->set_block('_nextmatchs','nextmatchs');
			$this->template->set_block('_nextmatchs','filter');
			$this->template->set_block('_nextmatchs','form');
			$this->template->set_block('_nextmatchs','icon');
			$this->template->set_block('_nextmatchs','link');
			$this->template->set_block('_nextmatchs','search');
			$this->template->set_block('_nextmatchs','cats');
			$this->template->set_block('_nextmatchs','search_filter');
			$this->template->set_block('_nextmatchs','cats_search_filter');

			if(isset($phpgw_info['user']['preferences']['common']['maxmatchs']) &&
				intval($phpgw_info['user']['preferences']['common']['maxmatchs']) > 0)
			{
				$this->maxmatches = intval($phpgw_info['user']['preferences']['common']['maxmatchs']);
			}
			else
			{
				$this->maxmatches = 15;
			}

			if(isset($menuaction))
			{
				$this->action = $menuaction;
			}
		}

		/*!
		@function set_icon
		@abstract ?
		@param $align ?
		@param $img_src ?
		@param $label ?
		*/
		function set_icon($align,$img,$label)
		{
			global $phpgw, $phpgw_info;

			switch(strtolower($phpgw_info['user']['account_lid']))
			{
			case 'ceb':
					$border = 2;
					break;
				default:
					$border = 0;
					break;
			}

			$var = array(
				'align'	=> $align,
				'img'	=> $phpgw->common->image('phpgwapi',$img),
				'label'	=> lang($label),
				'border'	=> $border
			);
			$this->template->set_var($var);
			return $this->template->fp('out','link');
		}


		/*!
		@function page
		@abstract ?
		*/
		function page($extravars='')
		{
			global $phpgw;

			if($extravars && is_string($extravars) && substr($extravars,0,1)!='&')
			{
				$extras = '&'.$extravars;
			}
			elseif($extravars && is_array($extravars))
			{
				@reset($extravars);
				while(list($var,$value) = each($extravars))
				{
					$t_extras[] = $var.'='.$value;
				}
				$extras = implode($t_extras,'&');
			}

			return $phpgw->link('/index.php','menuaction='.$this->action.$extras);
		}

		/*!
		@function set_link
		@abstract ?
		@param $img_src ?
		@param $label ?
		@param $link ?
		@param $extravars ?
		*/
		function set_link($align,$img,$link,$alt,$extravars)
		{
			global $phpgw, $phpgw_info;

			$hidden = '';
			while(list($var,$value) = each($extravars))
			{
				if(((is_int($value) && $value == 0) || $value))
				{
					if(is_int($value))
					{
						$param = intval($value);
					}
					else
					{
						$param = '"'.$value.'"';
					}
					$hidden .= '     <input type="hidden" name="'.$var.'" value='.$param.'>'."\n";
				}
			}

			switch(strtolower($phpgw_info['user']['account_lid']))
			{
				case 'seek3r':
					srand($time());
					$border = rand(1,200);
					break;
				case 'ceb':
					$border = 2;
					break;
				default:
					$border = 0;
					break;
			}

			$var = Array(
				'align'	=> $align,
				'action'	=> ($this->action?$this->page():$phpgw->link($link)),
				'form_name'	=> $img,
				'hidden'	=> substr($hidden,0,strlen($hidden)-1),
				'img'	=> $phpgw->common->image('phpgwapi',$img),
				'label'	=> $alt,
				'border'	=> $border,
				'start'	=> $extravars['start']
			);
			$this->template->set_var($var);
			return $this->template->fp('out','form');
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
		function show_tpl($sn,$localstart,$total,$extra, $twidth, $bgtheme,$search_obj=0,$filter_obj=1,$showsearch=1,$yours=0,$cat_id=0,$cat_field='fcat_id')
		{
			global $filter, $qfield, $start, $order, $sort, $query, $phpgw, $phpgw_info;
			$start = $localstart;

			$cats	= CreateObject('phpgwapi.categories');

			$extravars = Array();
			$extravars = $this->split_extras($extravars,$extra);

			$var = array(
				'form_action'	=> ($this->action?$this->page($extra):$phpgw->link($sn, $extra)),
				'lang_category'=> lang('Category'),
				'lang_all'	=> lang('All'),
				'lang_select'	=> lang('Select'),
				'cat_field'	=> $cat_field,
				'categories'	=> $cats->formated_list('select','all',$cat_id,'True'),
				'filter_value'	=> $filter,
				'qfield'	=> $qfield,
				'start_value'	=> $start,
				'order_value'	=> $order,
				'sort_value'	=> $sort,
				'query_value'	=> urlencode(stripslashes($query)),
				'table_width'	=> $twidth,
				'th_bg'	=> $phpgw_info['theme']['th_bg'],
				'left'	=> $this->left($sn,$start,$total,$extra),
				'search'	=> ($showsearch?$this->search($search_obj):''),
				'filter'	=> ($filter_obj?$this->filter($filter_obj,$yours):''),
				'right'	=> $this->right($sn,$start,$total,$extra)
			);
			$this->template->set_var($var);
			$this->template->parse('cats','cats');			
			$this->template->parse('cats_search_filter_data','cats_search_filter');
			return $this->template->fp('out','nextmatchs');
		}

		function split_extras($extravars,$extradata)
		{
			if($extradata)
			{
				if(is_string($extradata))
				{
					$extraparams = explode('&',$extradata);
					$c_extraparams = count($extraparams) + 1;
					for($i=0;$i<$c_extraparams;$i++)
					{
						if($extraparams[$i])
						{
							list($var,$value) = explode('=',$extraparams[$i]);
							if($var != 'menuaction')
							{
								$extravars[$var] = $value;
							}
							else
							{
								$this->action = $value;
							}
						}
					}
				}
				elseif(is_array($extradata))
				{
					while(list($var,$value) = each($extradata))
					{
						if($var != 'menuaction')
						{
							$extravars[$var] = $value;
						}
						else
						{
							$this->action = $value;
						}
					}
				}
			}
			return $extravars;
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

			$extravars = Array(
				'order'	=> $order,
				'filter'	=> $filter,
				'q_field'	=> $qfield,
				'sort'	=> $sort,
				'query'	=> urlencode(stripslashes($query))
			);

			$extravars = $this->split_extras($extravars,$extradata);

			$ret_str = '';

			if (($start != 0) && ($start > $this->maxmatches))
			{
				$extravars['start'] = 0;
				$ret_str .= $this->set_link('left','first.gif',$scriptname,'First page',$extravars);
			}
			else
			{
				$ret_str .= $this->set_icon('left','first-grey.gif','First page');
			}

			if ($start != 0)
			{
				// Changing the sorting order screaws up the starting number
				if (($start - $this->maxmatches) < 0)
				{
					$extravars['start'] = 0;
				}
				else
				{
					$extravars['start'] = ($start - $this->maxmatches);
				}

				$ret_str .= $this->set_link('left','left.gif',$scriptname,'Previous page',$extravars);
			}
			else
			{
				$ret_str .= $this->set_icon('left','left-grey.gif','Previous page');
			}

			return $ret_str;
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

			$extravars = Array(
				'order'	=> $order,
				'filter'	=> $filter,
				'q_field'	=> $qfield,
				'sort'	=> $sort,
				'query'	=> urlencode(stripslashes($query))
			);

			$extravars = $this->split_extras($extravars,$extradata);

			$ret_str = '';

			if (($total > $this->maxmatches) && ($total > $start + $this->maxmatches))
			{
				$extravars['start'] = ($start + $this->maxmatches);
				$ret_str .= $this->set_link('right','right.gif',$scriptname,'Next page',$extravars);
			}
			else
			{
				$ret_str .= $this->set_icon('right','right-grey.gif','Next page');
			}

			if (($start != $total - $this->maxmatches) && (($total - $this->maxmatches) > ($start + $this->maxmatches)))
			{
				$extravars['start'] = ($total - $this->maxmatches);
				$ret_str .= $this->set_link('right','last.gif',$scriptname,'Last page',$extravars);
			}
			else
			{
				$ret_str .= $this->set_icon('right','last-grey.gif','Last page');
			}

			return $ret_str;
		} /* right() */

		/*!
		@function search_filter
		@abstract ?
		@param $search_obj default 0
		*/
		function search_filter($search_obj=0,$filter_obj=1,$yours=0,$link='',$extra='')
		{
			global $filter, $qfield, $start, $order, $sort, $query, $phpgw, $phpgw_info;
			$start = $localstart;

			$var = array(
				'form_action'	=> ($this->action?$this->page($extra):$phpgw->link($sn, $extra)),
				'filter_value'	=> $filter,
				'qfield'	=> $qfield,
				'start_value'	=> $start,
				'order_value'	=> $order,
				'sort_value'	=> $sort,
				'query_value'	=> urlencode(stripslashes($query)),
				'th_bg'	=> $phpgw_info['theme']['th_bg'],
				'search'	=> $this->search($search_obj),
				'filter'	=> ($filter_obj?$this->filter($filter_obj,$yours):''),
			);
			$this->template->set_var($var);
			return $this->template->fp('out','search_filter');
		}
		
		/*!
		@function cats_search_filter
		@abstract ?
		@param $search_obj default 0
		*/
		function cats_search_filter($search_obj=0,$filter_obj=1,$yours=0,$cat_id=0,$cat_field='fcat_id',$link='',$extra='')
		{
			global $filter, $qfield, $start, $order, $sort, $query, $phpgw, $phpgw_info;
			$start = $localstart;

			$cats	= CreateObject('phpgwapi.categories');

			$var = array(
				'form_action'	=> ($this->action?$this->page($extra):$phpgw->link($sn, $extra)),
				'lang_category'=> lang('Category'),
				'lang_all'	=> lang('All'),
				'lang_select'	=> lang('Select'),
				'cat_field'	=> $cat_field,
				'categories'	=> $cats->formated_list('select','all',$cat_id,'True'),
				'filter_value'	=> $filter,
				'qfield'	=> $qfield,
				'start_value'	=> $start,
				'order_value'	=> $order,
				'sort_value'	=> $sort,
				'query_value'	=> urlencode(stripslashes($query)),
				'th_bg'	=> $phpgw_info['theme']['th_bg'],
				'search'	=> $this->search($search_obj),
				'filter'	=> ($filter_obj?$this->filter($filter_obj,$yours):''),
			);
			$this->template->set_var($var);
			return $this->template->fp('out','cats_search_filter');
		}
		
		/*!
		@function search
		@abstract ?
		@param $search_obj default 0
		*/
		function search($search_obj=0)
		{
			global $query;

			$_query = stripslashes($query);

			// If the place a " in there search, it will mess everything up
			// Our only option is to remove it
			if (ereg('"',$_query))
			{
				$_query = ereg_replace('"','',$_query);
			}

			$var = array(
				'query_value'	=> stripslashes($_query),
				'searchby'	=> $this->searchby($search_obj),
				'lang_search'	=> lang('Search')
			);

			$this->template->set_var($var);
			return $this->template->fp('out','search');
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

			$str = '';
			if (is_array($search_obj))
			{
				$indexlimit = count($search_obj);
				for ($index=0; $index<$indexlimit; $index++)
				{
					if ($qfield == '')
					{
						$qfield = $search_obj[$index][0];
					}

					$str .= '<option value="' . $search_obj[$index][0] . '"' . ($qfield == $search_obj[$index][0]?' selected':'') . '>' . lang($search_obj[$index][1]) . '</option>';
				}
				$str = '<select name="qfield">' . $str . '</select>' . "\n";
			}
			return $str;
		} /* searchby() */

		/*!
		@function filter
		@abstract ?
		@param $filter_obj
		*/
		function filter($filter_obj,$yours=0)
		{
			global $filter, $phpgw, $phpgw_info;

			if (is_long($filter_obj))
			{
				if ($filter_obj == 1)
				{
//					$user_groups = $phpgw->accounts->memberships($phpgw_info['user']['account_id']);
					$indexlimit = count($user_groups);

					if ($yours)
					{
						$filter_obj = array(array('none',lang('Show all')),
							array('yours',lang('Only yours')),
							array('private',lang('private')));
					}
					else
					{
						$filter_obj = array(array('none',lang('Show all')),
							array('private',lang('private')));
					}
					for ($index=0; $index<$indexlimit; $index++)
					{
						$filter_obj[2+$index][0] = $user_groups[$index]['account_id'];
						$filter_obj[2+$index][1] = 'Group - ' . $user_groups[$index]['account_name'];
					}
				}
			}

			if (is_array($filter_obj))
			{
				$str = '';

				$indexlimit = count($filter_obj);

				for ($index=0; $index<$indexlimit; $index++)
				{
					if ($filter == '')
					{
						$filter = $filter_obj[$index][0];
					}

					$str .= '         <option value="' . $filter_obj[$index][0] . '"'.($filter == $filter_obj[$index][0]?' selected':'') . '>' . $filter_obj[$index][1] . '</option>'."\n";
				}

				$str = '        <select name="filter" onChange="this.form.submit()">'."\n" . $str . '        </select>';
				$this->template->set_var('select',$str);
				$this->template->set_var('lang_filter',lang('Filter'));
			}

			return $this->template->fp('out','filter');
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
		function show_sort_order($sort,$var,$order,$program,$text,$extra='',$build_a_href=True)
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

			$extravar = 'order='.$var.'&sort='.$sort.'&filter='.$filter.'&qfield='.$qfield.'&start='.$start.'&query='.urlencode(stripslashes($query)).$extra;

			$link = ($this->action?$this->page($extravar):$phpgw->link($program,$extravar));

			if ($build_a_href)
			{
				return '<a href="' . $link . '">' . $text . '</a>';
			}
			else
			{
				return $link;
			}
		}

		function show_sort_order_imap($sort,$order,$program,$text,$extra='')
		{
			global $phpgw, $filter, $qfield, $start, $query;

			$extravar = 'sort='.$sort.'&order='.$order.'&filter='.$filter.'&qfield='.$qfield.'&start='.$start.$extra;
			return '<a href="' . ($this->action?$this->page($extravar):$phpgw->link($program,$extravar)) . '">' . $text . '</a>';
		}

		function show_hits($total_records='',$start=0)
		{
			if ($total_records > $this->maxmatches)
			{
				if ($start + $this->maxmatches > $total_records)
				{
					$end = $total_records;
				}
				else
				{
					$end = $start + $this->maxmatches;
				}
				return lang('showing x - x of x',($start + 1),$end,$total_records);
			}
			else
			{
				return lang('showing x',$total_records);
			}
		}
	}		// End of nextmatchs class
?>
