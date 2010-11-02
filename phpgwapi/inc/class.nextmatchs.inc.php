<?php
	/**************************************************************************\
	* eGroupWare API - nextmatchs                                              *
	* Written by Joseph Engo <jengo@phpgroupware.org>                          *
	*        and Bettina Gille [ceb@phpgroupware.org]                          *
	* Handles limiting number of rows displayed                                *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
	* Copyright (C) 2002, 2003 Joseph Engo, Bettina Gille                      *
	* ------------------------------------------------------------------------ *
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org                                                *
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
	/* $Source$ */

	/*!
	@class nextmatchs
	@abstract
	*/
	class nextmatchs
	{
		var $maxmatches;
		var $action;
		var $template;
		var $extra_filters = array();

		function nextmatchs($website=False)
		{
			if(!$website)
			{
				$this->template = createobject('phpgwapi.Template',EGW_TEMPLATE_DIR);
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
			}

			if(isset($GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']) &&
				(int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] > 0)
			{
				$this->maxmatches = (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			}
			else
			{
				$this->maxmatches = 15;
			}

			$this->_start = (int) get_var('start',array('GLOBAL','POST','GET'));

			foreach(array('menuaction','filter','qfield','order','sort') as $name)
			{
				$var = '_'.$name;
				$this->$var = get_var($name,array('GLOBAL','POST','GET'));
				if (!preg_match('/^[a-z0-9_. -]*$/i',$this->$var))
				{
					$this->$var = '';
				}
			}
			if (!is_object($GLOBALS['egw']->html))
			{
				$GLOBALS['egw']->html = CreateObject('phpgwapi.html');
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
			$var = array(
				'align'  => $align,
				'img'    => $GLOBALS['egw']->common->image('phpgwapi',$img),
				'label'  => lang($label),
				'border' => 0
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
			if($extravars && is_string($extravars) && substr($extravars,0,1)!='&')
			{
				$extras = '&'.$extravars;
			}
			elseif($extravars && is_array($extravars))
			{
				foreach($extravars as $var => $value)
				{
					if($var != 'menuaction')
					{
						$t_extras[] = $var.'='.$value;
					}
				}
				$extras = implode($t_extras,'&');
			}

			return $GLOBALS['egw']->link('/index.php','menuaction='.$this->_menuaction.$extras);
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
			$var = Array(
				'align'     => $align,
				'action'    => ($this->_menuaction?$this->page():$GLOBALS['egw']->link($link)),
				'form_name' => $img,
				'hidden'    => $GLOBALS['egw']->html->input_hidden($extravars),
				'img'       => $GLOBALS['egw']->common->image('phpgwapi',$img),
				'label'     => $alt,
				'border'    => 0,
				'start'     => $extravars['start']
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
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories = CreateObject('phpgwapi.categories');
			}
			$extravars = $this->split_extras($extravars,$extra);

			$var = array(
				'form_action'   => ($this->_menuaction?$this->page($extra):$GLOBALS['egw']->link($sn, $extra)),
				'lang_category' => lang('Category'),
				'lang_all'      => lang('All'),
				'lang_select'   => lang('Select'),
				'cat_field'     => $cat_field,
				'categories'    => $GLOBALS['egw']->categories->formatted_list('select','all',$cat_id,'True'),
				'hidden'       => $GLOBALS['egw']->html->input_hidden(array(
					'filter' => $this->_filter,
					'qfield' => $this->_qfield,
					'start'  => (int)$localstart,
					'order'  => $this->_order,
					'sort'   => $this->_sort,
					'query'  => $GLOBALS['query'],
				)),
				'query_value'   => $GLOBALS['egw']->html->htmlspecialchars($GLOBALS['query']),
				'table_width'   => $twidth,
				'th_bg'         => $GLOBALS['egw_info']['theme']['th_bg'],
				'left'          => $this->left($sn,(int)$localstart,$total,$extra),
				'search'        => ($showsearch?$this->search($search_obj):''),
				'filter'        => ($filter_obj?$this->filter($filter_obj,$yours):''),
				'right'         => $this->right($sn,(int)$localstart,$total,$extra)
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
				if(!is_array($extradata))
				{
					parse_str($extradata,$extradata);
				}
				foreach($extradata as $var => $value)
				{
					if($var != 'menuaction')
					{
						$extravars[$var] = $value;
					}
					else
					{
						$this->_menuaction = $value;
					}
				}
			}
			return $extravars;
		}

		function extras_to_string($extra)
		{
			if(is_array($extra))
			{
				foreach($extra as $var => $value)
				{
					$t_extras[] = $var . '=' . urlencode($value);
				}
				$extra_s = '&' . implode('&',$t_extras);
			}
			return $extra_s;
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
			$extravars = Array(
				'order'   => $this->_order,
				'filter'  => $this->_filter,
				'q_field' => $this->_qfield,
				'sort'    => $this->_sort,
				'query'   => stripslashes(@$GLOBALS['query'])
			);

			$extravars = $this->split_extras($extravars,$extradata);
			$ret_str = '';

			$start = (int) $start;

			if ($start != 0)
			{
				$extravars['start'] = 0;
				$ret_str .= $this->set_link('left','first.png',$scriptname,lang('First page'),$extravars);
				// Changing the sorting order screaws up the starting number
				if (($start - $this->maxmatches) < 0)
				{
					$extravars['start'] = 0;
				}
				else
				{
					$extravars['start'] = ($start - $this->maxmatches);
				}
				$ret_str .= $this->set_link('left','left.png',$scriptname,lang('Previous page'),$extravars);
			}
			else
			{
				$ret_str .= $this->set_icon('left','first-grey.png',lang('First page'));
				$ret_str .= $this->set_icon('left','left-grey.png',lang('Previous page'));
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
			$extravars = Array(
				'order'   => $this->_order,
				'filter'  => $this->_filter,
				'q_field' => $this->_qfield,
				'sort'    => $this->_sort,
				'query'   => stripslashes(@$GLOBALS['query'])
			);

			$extravars = $this->split_extras($extravars,$extradata);
			$ret_str = '';

			$start = (int) $start;

			if (($total > $this->maxmatches) &&
				($total > $start + $this->maxmatches))
			{
				$extravars['start'] = ($start + $this->maxmatches);
				$ret_str .= $this->set_link('right','right.png',$scriptname,lang('Next page'),$extravars);
				$extravars['start'] = ($total - $this->maxmatches);
				$ret_str .= $this->set_link('right','last.png',$scriptname,lang('Last page'),$extravars);
			}
			else
			{
				$ret_str .= $this->set_icon('right','right-grey.png',lang('Next page'));
				$ret_str .= $this->set_icon('right','last-grey.png',lang('Last page'));
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
			$var = array(
				'form_action'  => ($this->_menuaction?$this->page($extra):$GLOBALS['egw']->link($sn, $extra)),
				'th_bg'        => $GLOBALS['egw_info']['theme']['th_bg'],
				'hidden'       => $GLOBALS['egw']->html->input_hidden(array(
					'filter' => $this->_filter,
					'qfield' => $this->_qfield,
					'start'  => 0,
					'order'  => $this->_order,
					'sort'   => $this->_sort,
					'query'  => $GLOBALS['query'],
				)),
				'search'       => $this->search($search_obj),
				'filter'       => ($filter_obj?$this->filter($filter_obj,$yours):'')
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
			if (!is_object($GLOBALS['egw']->categories))
			{
				$GLOBALS['egw']->categories  = CreateObject('phpgwapi.categories');
			}
			$var = array(
				'form_action'   => ($this->_menuaction?$this->page($extra):$GLOBALS['egw']->link($sn, $extra)),
				'lang_category' => lang('Category'),
				'lang_all'      => lang('All'),
				'lang_select'   => lang('Select'),
				'cat_field'     => $cat_field,
				'categories'    => $GLOBALS['egw']->categories->formatted_list('select','all',(int)$cat_id,'True'),
				'hidden'       => $GLOBALS['egw']->html->input_hidden(array(
					'filter' => $this->_filter,
					'qfield' => $this->_qfield,
					'start'  => 0,
					'order'  => $this->_order,
					'sort'   => $this->_sort,
					'query'  => $GLOBALS['query'],
				)),
				'th_bg'         => $GLOBALS['egw_info']['theme']['th_bg'],
				'search'        => $this->search($search_obj),
				'filter'        => ($filter_obj?$this->filter($filter_obj,$yours):'')
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
			if(is_array($search_obj))
			{
				$_query    = stripslashes($search_obj['query']);
				$search_obj = $search_obj['search_obj'];
			}
			else
			{
				$_query = stripslashes($GLOBALS['query']);
			}

			// If they place a '"' in their search, it will mess everything up
			// Our only option is to remove it
			if(strpos($_query,'"')!==false)
			{
				$_query = str_replace('"','',$_query);
			}
			$var = array
			(
				'query_value'   => $GLOBALS['egw']->html->htmlspecialchars($_query),
				'lang_search' => lang('Search')
			);

			if (is_array($search_obj))
			{
				$var['searchby'] = $this->searchby($search_obj);
			}

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
			$filter_obj = array(array('none','show all'));
			$index = 0;

			$GLOBALS['egw']->db->query("SELECT $idxfieldname, $strfieldname FROM $filtertable",__LINE__,__FILE__);
			while($GLOBALS['egw']->db->next_record())
			{
				$index++;
				$filter_obj[$index][0] = $GLOBALS['egw']->db->f($idxfieldname);
				$filter_obj[$index][1] = $GLOBALS['egw']->db->f($strfieldname);
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
			$str = '';
			if (is_array($search_obj))
			{
				$indexlimit = count($search_obj);
				for ($index=0; $index<$indexlimit; $index++)
				{
					if ($this->_qfield == '')
					{
						$this->_qfield = $search_obj[$index][0];
					}
					$str .= '<option value="' . $search_obj[$index][0] . '"' . ($this->_qfield == $search_obj[$index][0]?' selected':'') . '>' . lang($search_obj[$index][1]) . '</option>';
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
			if (is_array($yours))
			{
				$params = $yours;
				$this->_filter = $params['filter'];
				$yours  = $params['yours'];
			}

			if (is_long($filter_obj))
			{
				if ($filter_obj == 1)
				{
					//  $user_groups = $GLOBALS['egw']->accounts->membership($GLOBALS['egw_info']['user']['account_id']);
					$indexlimit = count($user_groups);

					if ($yours)
					{
						$filter_obj = array
						(
							array('none',lang('Show all')),
							array('yours',lang('Only yours')),
							array('private',lang('private'))
						);
					}
					else
					{
						$filter_obj = array
						(
							array('none',lang('Show all')),
							array('private',lang('private'))
						);
					}

					while (is_array($this->extra_filters) && list(,$efilter) = each($this->extra_filters))
					{
						$filter_obj[] = $efilter;
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
					if ($this->_filter == '')
					{
						$this->_filter = $filter_obj[$index][0];
					}
					$str .= '         <option value="' . $filter_obj[$index][0] . '"'.($this->_filter == $filter_obj[$index][0]?' selected="1"':'') . '>' . $filter_obj[$index][1] . '</option>'."\n";
				}

				$str = '        <select name="filter" onChange="this.form.submit()">'."\n" . $str . '        </select>';
				$this->template->set_var('select',$str);
				$this->template->set_var('lang_filter',lang('Filter'));
			}

			return $this->template->fp('out','filter');
		} /* filter() */

		/* replacement for function filter */
		function new_filter($data=0)
		{
			if(is_array($data))
			{
				$filter = (isset($data['filter'])?$data['filter']:'');
				$format = (isset($data['format'])?$data['format']:'all');
			}
			else
			{
				//$filter = get_var('filter',Array('GET','POST'));
				$filter = $data;
				$format = 'all';
			}

			switch($format)
			{
				case 'yours':
					$filter_obj = array
					(
						array('none',lang('show all')),
						array('yours',lang('only yours'))
					);
					break;
				case 'private':
					$filter_obj = array
					(
						array('none',lang('show all')),
						array('private',lang('only private'))
					);
					break;
				default:
					$filter_obj = array
					(
						array('none',lang('show all')),
						array('yours',lang('only yours')),
						array('private',lang('only private'))
					);
			}

			$str = '';
			$indexlimit = count($filter_obj);

			for($index=0; $index<$indexlimit; $index++)
			{
				if($filter == '')
				{
					$filter = $filter_obj[$index][0];
				}
				$str .= '         <option value="' . $filter_obj[$index][0] . '"'.($filter == $filter_obj[$index][0]?' selected="1"':'') . '>' . $filter_obj[$index][1] . '</option>'."\n";
			}

			$str = '        <select name="filter" onChange="this.form.submit()">'."\n" . $str . '        </select>';
			$this->template->set_var('select',$str);
			$this->template->set_var('lang_filter',lang('Filter'));

			return $this->template->fp('out','filter');
		} /* filter() */

		/*!
		@function alternate_row_color
		@abstract alternate row colour
		@param $currentcolor default ''
		@param $do_class boolean default False return the color-value or just the class-name
		*/
		function alternate_row_color($currentcolor = '',$do_class=False)
		{
			if (! $currentcolor)
			{
				$currentcolor = @$GLOBALS['tr_color'];
			}
			if ($do_class)
			{
				$row_on_color = 'row_on';
				$row_off_color = 'row_off';
			}
			else	// this is for old apps relying on the old themes
			{
				$row_on_color = '" class="row_on';
				$row_off_color = '" class="row_off';
			}
			if ($currentcolor == $row_on_color)
			{
				$GLOBALS['tr_color'] = $row_off_color;
			}
			else
			{
				$GLOBALS['tr_color'] = $row_on_color;
			}

			return $GLOBALS['tr_color'];
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
			if ($order == $var)
			{
				$sort = $sort == 'ASC' ? 'DESC' : 'ASC';

				$text = '<b>'.$text.'</b> <img border="0" src="'.$GLOBALS['egw']->common->image('phpgwapi',$sort=='ASC'?'up':'down').'">';
			}
			else
			{
				$sort = 'ASC';
			}

			if (is_array($extra))
			{
				$extra = $this->extras_to_string($extra);
			}

			$extravar = 'order='.$var.'&sort='.$sort.'&filter='.$this->_filter.'&qfield='.$this->_qfield.'&start='.$this->_start.'&query='.urlencode(stripslashes(@$GLOBALS['query'])).$extra;

			$link = ($this->_menuaction?$this->page($extravar):$GLOBALS['egw']->link($program,$extravar));

			if ($build_a_href)
			{
				return '<a href="' . $link . '">' . $text . '</a>';
			}
			else
			{
				return $link;
			}
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
				return lang('showing %1 - %2 of %3',($start + 1),$end,$total_records);
			}
			else
			{
				return lang('showing %1',$total_records);
			}
		}

		/*!
		@function show_sort_order_imap
		@abstract ?
		@param $old_sort : the current sort value
		@param $new_sort : the sort value you want if you click on this
		@param $default_order : users preference for ordering list items (force this when a new [different] sorting is requested)
		@param $order : the current order (will be flipped if old_sort = new_sort)
		@param $program : script file name
		@param $text : Text the link will show
		@param $extra : any extra stuff you want to pass, url style
		*/
		function show_sort_order_imap($old_sort,$new_sort,$default_order,$order,$program,$text,$extra='')
		{
			if(is_array($extra))
			{
				$extra = $this->extras_to_string($extra);
			}
			if($old_sort == $new_sort)
			{
				// alternate order, like on outkrook, click on present sorting reverses order
				if((int)$order == 1)
				{
					$our_order = 0;
				}
				elseif((int)$order == 0)
				{
					$our_order = 1;
				}
				else
				{
					// we should never get here
					$our_order = 1;
				}
			}
			else
			{
				//user has selected a new sort scheme, reset the order to users default
				$our_order = $default_order;
			}

			$extravar = 'order='.$our_order.'&sort='.$new_sort.$extra;

			$link = ($this->_menuaction?$this->page($extravar):$GLOBALS['egw']->link($program,$extravar));
			return '<a href="' .$link .'">' .$text .'</a>';
		}

		/*!
		@function nav_left_right_imap
		@abstract same code as left and right (as of Dec 07, 2001) except all combined into one function
		@param feed_vars : array with these elements: <br>
			start
			total
			cmd_prefix
			cmd_suffix
		@return array, combination of functions left and right above, with these elements:
			first_page
			prev_page
			next_page
			last_page
		@author: jengo, some changes by Angles
		*/
		function nav_left_right_imap($feed_vars)
		{
			$return_array = Array(
				'first_page' => '',
				'prev_page'  => '',
				'next_page'  => '',
				'last_page'  => ''
			);
			$out_vars = array();
			// things that might change
			$out_vars['start'] = $feed_vars['start'];
			// things that stay the same
			$out_vars['common_uri'] = $feed_vars['common_uri'];
			$out_vars['total'] = $feed_vars['total'];

			// first page
			if(($feed_vars['start'] != 0) &&
				($feed_vars['start'] > $this->maxmatches))
			{
				$out_vars['start'] = 0;
				$return_array['first_page'] = $this->set_link_imap('left','first.png',lang('First page'),$out_vars);
			}
			else
			{
				$return_array['first_page'] = $this->set_icon_imap('left','first-grey.png',lang('First page'));
			}
			// previous page
			if($feed_vars['start'] != 0)
			{
				// Changing the sorting order screaws up the starting number
				if(($feed_vars['start'] - $this->maxmatches) < 0)
				{
					$out_vars['start'] = 0;
				}
				else
				{
					$out_vars['start'] = ($feed_vars['start'] - $this->maxmatches);
				}
				$return_array['prev_page'] = $this->set_link_imap('left','left.png',lang('Previous page'),$out_vars);
			}
			else
			{
				$return_array['prev_page'] = $this->set_icon_imap('left','left-grey.png',lang('Previous page'));
			}

			// re-initialize the out_vars
			// things that might change
			$out_vars['start'] = $feed_vars['start'];
			// next page
			if(($feed_vars['total'] > $this->maxmatches) &&
				($feed_vars['total'] > $feed_vars['start'] + $this->maxmatches))
			{
				$out_vars['start'] = ($feed_vars['start'] + $this->maxmatches);
				$return_array['next_page'] = $this->set_link_imap('right','right.png',lang('Next page'),$out_vars);
			}
			else
			{
				$return_array['next_page'] = $this->set_icon_imap('right','right-grey.png',lang('Next page'));
			}
			// last page
			if(($feed_vars['start'] != $feed_vars['total'] - $this->maxmatches) &&
				(($feed_vars['total'] - $this->maxmatches) > ($feed_vars['start'] + $this->maxmatches)))
			{
				$out_vars['start'] = ($feed_vars['total'] - $this->maxmatches);
				$return_array['last_page'] = $this->set_link_imap('right','last.png',lang('Last page'),$out_vars);
			}
			else
			{
				$return_array['last_page'] = $this->set_icon_imap('right','last-grey.png',lang('Last page'));
			}
			return $return_array;
		}

		/*!
		@function set_link_imap
		@abstract ?
		@param $img_src ?
		@param $label ?
		@param $link ?
		@param $extravars ?
		*/
		function set_link_imap($align,$img,$alt_text,$out_vars)
		{
			$img_full = $GLOBALS['egw']->common->image('phpgwapi',$img);
			$image_part = '<img src="'.$img_full.'" border="0" alt="'.$alt_text.'" width="12" height="12">';
			return '<a href="'.$out_vars['common_uri'].'&start='.$out_vars['start'].'">'.$image_part.'</a>';
		}

		function set_icon_imap($align,$img,$alt_text)
		{
			$img_full = $GLOBALS['egw']->common->image('phpgwapi',$img);
			return '<img src="'.$img_full.'" border="0" width="12" height="12" alt="'.$alt_text.'">'."\r\n";
		}
	} // End of nextmatchs class
?>
