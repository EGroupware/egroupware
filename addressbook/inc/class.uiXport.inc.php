<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Miles Lott <milos@groupwhere.org>                             *
  * ------------------------------------------------------------------------ *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uiXport
	{
		var $template;
		var $public_functions = array(
			'import' => True,
			'export' => True
		);
		var $bo;
		var $cat;

		var $start;
		var $limit;
		var $query;
		var $sort;
		var $order;
		var $filter;
		var $cat_id;

		function uiXport()
		{
			$this->template = $GLOBALS['phpgw']->template;
			$this->cat      = CreateObject('phpgwapi.categories');
			$this->bo       = CreateObject('addressbook.boXport',True);
			$this->browser  = CreateObject('phpgwapi.browser');

			$this->_set_sessiondata();
		}

		function _set_sessiondata()
		{
			$this->start    = $this->bo->start;
			$this->limit    = $this->bo->limit;
			$this->query    = $this->bo->query;
			$this->sort     = $this->bo->sort;
			$this->order    = $this->bo->order;
			$this->filter   = $this->bo->filter;
			$this->cat_id   = $this->bo->cat_id;
			if($this->debug) { $this->_debug_sqsof(); }
		}

		/* Called only by index(), just prior to page footer. */
		function save_sessiondata()
		{
			$data = array(
				'start'  => $this->start,
				'limit'  => $this->limit,
				'query'  => $this->query,
				'sort'   => $this->sort,
				'order'  => $this->order,
				'filter' => $this->filter,
				'cat_id' => $this->cat_id
			);
			$this->bo->save_sessiondata($data);
		}

		/* Return a select form element with the categories option dialog in it */
		function cat_option($cat_id='',$notall=False,$java=True,$multiple=False)
		{
			if($java)
			{
				$jselect = ' onChange="this.form.submit();"';
			}
			/* Setup all and none first */
			$cats_link  = "\n" .'<select name="fcat_id'.($multiple?'[]':'').'"' .$jselect . ($multiple ? 'multiple size="3"' : '') . ">\n";
			if(!$notall)
			{
				$cats_link .= '<option value=""';
				if($cat_id=='all')
				{
					$cats_link .= ' selected';
				}
				$cats_link .= '>'.lang('all').'</option>'."\n";
			}

			/* Get global and app-specific category listings */
			$cats_link .= $this->cat->formated_list('select','all',$cat_id,True);
			$cats_link .= '</select>'."\n";
			return $cats_link;
		}

		function import()
		{
			$tsvfile   = get_var('tsvfile','FILES');
			$tsvfilename = get_var('tsvfilename','POST');
			$convert   = get_var('convert','POST');
			$conv_type = get_var('conv_type','POST');
			$cat_id    = get_var('cat_id','POST');
			$fcat_id   = get_var('fcat_id','POST');
			$private   = get_var('private','POST');
			$download  = get_var('download','POST');

			if($convert)
			{
				if($conv_type == 'none')
				{
					$GLOBALS['phpgw_info']['flags']['noheader'] = False;
					$GLOBALS['phpgw_info']['flags']['noheader'] = True;
					$GLOBALS['phpgw']->common->phpgw_header();
					echo parse_navbar();
					echo lang('<b>No conversion type &lt;none&gt; could be located.</b>  Please choose a conversion type from the list');
					echo '&nbsp<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiXport.import') . '">' . lang('OK') . '</a>';
					$GLOBALS['phpgw']->common->phpgw_footer();
					$GLOBALS['phpgw']->common->phpgw_exit();
				}
				$buffer = $this->bo->import($tsvfile,$conv_type,$private,$fcat_id);

				if($download == '')
				{
					if($conv_type == 'Debug LDAP' || $conv_type == 'Debug SQL')
					{
						// filename, default application/octet-stream, length of file, default nocache True
						$GLOBALS['phpgw']->browser->content_header($tsvfilename,'',strlen($buffer));
						echo $buffer;
					}
					else
					{
						$GLOBALS['phpgw']->common->phpgw_header();
						echo parse_navbar();
						echo "<pre>$buffer</pre>";
						echo '<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.index') . '">'.lang('OK').'</a>';
						$GLOBALS['phpgw']->common->phpgw_footer();
					}
				}
				else
				{
					$GLOBALS['phpgw']->common->phpgw_header();
					echo parse_navbar();
					echo "<pre>$buffer</pre>";
					echo '<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.index'). '">'.lang('OK').'</a>';
					$GLOBALS['phpgw']->common->phpgw_footer();
				}
			}
			else
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();

				set_time_limit(0);

				$this->template->set_file(array('import' => 'import.tpl'));

				$dir_handle = opendir(PHPGW_APP_INC . SEP . 'import');
				$i=0; $myfilearray = '';
				while($file = readdir($dir_handle))
				{
					if((substr($file, 0, 1) != '.') && is_file(PHPGW_APP_INC . SEP . 'import' . SEP . $file) )
					{
						$myfilearray[$i] = $file;
						$i++;
					}
				}
				closedir($dir_handle);
				sort($myfilearray);
				for($i=0;$i<count($myfilearray);$i++)
				{
					$fname = ereg_replace('_',' ',$myfilearray[$i]);
					$conv .= '<OPTION VALUE="' . $myfilearray[$i].'">' . $fname . '</OPTION>';
				}

				$this->template->set_var('lang_cancel',lang('Cancel'));
				$this->template->set_var('lang_cat',lang('Select Category'));
				$this->template->set_var('cancel_url',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.index'));
				$this->template->set_var('navbar_bg',$GLOBALS['phpgw_info']['theme']['navbar_bg']);
				$this->template->set_var('navbar_text',$GLOBALS['phpgw_info']['theme']['navbar_text']);
				$this->template->set_var('import_text',lang('Import from LDIF, CSV, or VCard'));
				$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiXport.import'));
				$this->template->set_var('cat_link',$this->cat_option($this->cat_id,True,False));
				$this->template->set_var('tsvfilename','');
				$this->template->set_var('conv',$conv);
				$this->template->set_var('debug',lang('Debug output in browser'));
				$this->template->set_var('filetype',lang('LDIF'));
				$this->template->set_var('download',lang('Submit'));
				$this->template->set_var('start',$this->start);
				$this->template->set_var('sort',$this->sort);
				$this->template->set_var('order',$this->order);
				$this->template->set_var('filter',$this->filter);
				$this->template->set_var('query',$this->query);
				$this->template->set_var('cat_id',$this->cat_id);
				$this->template->set_var('lang_import_instructions',lang('import_instructions'));
				if(extension_loaded('zip'))
				{
					$this->template->set_var('zip_note',lang('zip_note'));
				}
				else
				{
					$this->template->set_var('zip_note','');
				}
				$this->template->set_var('lang_exported_file',lang('enter the path to the exported file here'));
				$this->template->set_var('lang_conv_type',lang('select the type of conversion'));
				$this->template->set_var('lang_mark_priv',lang('Mark records as private'));
				$this->template->set_var('lang_debug',lang('Debug output in browser'));
				$this->template->pparse('out','import');
			}
//			$GLOBALS['phpgw']->common->phpgw_footer();
		}

		function export()
		{
			$convert   = get_var('convert','POST');
			$conv_type = get_var('conv_type','POST');
			$cat_id    = get_var('cat_id','POST');
			$download  = get_var('download','POST');
			$tsvfilename = get_var('tsvfilename','POST');

			if($convert)
			{
				if($conv_type == 'none')
				{
					$GLOBALS['phpgw_info']['flags']['noheader'] = False;
					$GLOBALS['phpgw_info']['flags']['noheader'] = True;
					$GLOBALS['phpgw']->common->phpgw_header();
					echo parse_navbar();
					echo lang('<b>No conversion type &lt;none&gt; could be located.</b>  Please choose a conversion type from the list');
					echo '&nbsp<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiXport.export') . '">' . lang('OK') . '</a>';
					$GLOBALS['phpgw']->common->phpgw_footer();
					$GLOBALS['phpgw']->common->phpgw_exit();
				}

				$buffer = $this->bo->export($conv_type,$cat_id);

				if(($download == 'on') || ($conv_type == 'Palm_PDB') )
				{
					// filename, default application/octet-stream, length of file, default nocache True
					$this->browser->content_header($tsvfilename,'application/x-octet-stream',strlen($buffer));
					echo $buffer;
				}
				else
				{
					$GLOBALS['phpgw']->common->phpgw_header();
					echo parse_navbar();
					echo "<pre>\n";
					echo $buffer;
					echo "\n</pre>\n";
					echo '<a href="'.$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiXport.export') . '">' . lang('OK') . '</a>';
					$GLOBALS['phpgw']->common->phpgw_footer();
				}
			}
			else
			{
				$GLOBALS['phpgw']->common->phpgw_header();
				echo parse_navbar();

				set_time_limit(0);
				
				$this->template->set_file(array('export' => 'export.tpl'));

				$dir_handle = opendir(PHPGW_APP_INC. SEP . 'export');
				$i=0; $myfilearray = '';
				while($file = readdir($dir_handle))
				{
					if((substr($file, 0, 1) != '.') && is_file(PHPGW_APP_INC . SEP . 'export' . SEP . $file) )
					{
						$myfilearray[$i] = $file;
						$i++;
					}
				}
				closedir($dir_handle);
				sort($myfilearray);
				for($i=0;$i<count($myfilearray);$i++)
				{
					$fname = ereg_replace('_',' ',$myfilearray[$i]);
					$conv .= '        <option value="'.$myfilearray[$i].'">'.$fname.'</option>'."\n";
				}

				$this->template->set_var('lang_cancel',lang('Cancel'));
				$this->template->set_var('lang_cat',lang('Select Category'));
				$this->template->set_var('cat_link',$this->cat_option($this->cat_id,False,False));
				$this->template->set_var('cancel_url',$GLOBALS['phpgw']->link('/addressbook/index.php'));
				$this->template->set_var('navbar_bg',$GLOBALS['phpgw_info']['theme']['navbar_bg']);
				$this->template->set_var('navbar_text',$GLOBALS['phpgw_info']['theme']['navbar_text']);
				$this->template->set_var('export_text',lang('Export from Addressbook'));
				$this->template->set_var('action_url',$GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiXport.export'));
				$this->template->set_var('filename',lang('Export file name'));
				$this->template->set_var('conv',$conv);
				$this->template->set_var('debug',lang(''));
				$this->template->set_var('download',lang('Submit'));
				$this->template->set_var('start',$this->start);
				$this->template->set_var('sort',$this->sort);
				$this->template->set_var('order',$this->order);
				$this->template->set_var('filter',$this->filter);
				$this->template->set_var('query',$this->query);
				$this->template->set_var('cat_id',$this->cat_id);
				$this->template->pparse('out','export');

				$GLOBALS['phpgw']->common->phpgw_footer();
			}
		}
	}
?>
