<?php
  /**************************************************************************\
  * phpGroupWare - addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
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
			global $phpgw;

			$this->template = $phpgw->template;
			$this->cat      = CreateObject('phpgwapi.categories');
			$this->bo       = CreateObject('addressbook.boXport',True);
			$this->browser  = CreateObject('phpgwapi.browser');

			$this->start    = $this->bo->start;
			$this->limit    = $this->bo->limit;
			$this->query    = $this->bo->query;
			$this->sort     = $this->bo->sort;
			$this->order    = $this->bo->order;
			$this->filter   = $this->bo->filter;
			$this->cat_id   = $this->bo->cat_id;
		}

		/* Return a select form element with the categories option dialog in it */
		function cat_option($cat_id='',$notall=False,$java=True,$multiple=False)
		{
			if ($java)
			{
				$jselect = ' onChange="this.form.submit();"';
			}
			/* Setup all and none first */
			$cats_link  = "\n" .'<select name="cat_id'.($multiple?'[]':'').'"' .$jselect . ($multiple ? 'multiple size="3"' : '') . ">\n";
			if (!$notall)
			{
				$cats_link .= '<option value=""';
				if ($cat_id=="all")
				{
					$cats_link .= ' selected';
				}
				$cats_link .= '>'.lang("all").'</option>'."\n";
			}

			/* Get global and app-specific category listings */
			$cats_link .= $this->cat->formated_list('select','all',$cat_id,True);
			$cats_link .= '</select>'."\n";
			return $cats_link;
		}

		function import()
		{
			global $phpgw,$convert,$download,$tsvfile,$private,$conv_type;

			if ($convert)
			{
				$buffer = $this->bo->import($tsvfile,$conv_type,$private);

				if ($download == '')
				{
					if($conv_type == 'Debug LDAP' || $conv_type == 'Debug SQL' )
					{
						// filename, default application/octet-stream, length of file, default nocache True
						$phpgw->browser->content_header($tsvfilename,'',strlen($buffer));
						echo $buffer;
					}
					else
					{
						$phpgw->common->phpgw_header();
						echo parse_navbar();
						echo "<pre>$buffer</pre>";
						echo '<a href="'.$phpgw->link('/index.php','menuaction=addressbook.uiaddressbook.get_list') . '">'.lang("OK").'</a>';
						$phpgw->common->phpgw_footer();
					}
				}
				else
				{
					$phpgw->common->phpgw_header();
					echo parse_navbar();
					echo "<pre>$buffer</pre>";
					echo '<a href="'.$phpgw->link('/index.php','menuaction=addressbook.uiaddressbook.get_list'). '">'.lang("OK").'</a>';
					$phpgw->common->phpgw_footer();
				}

			}
			else
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();

				$this->template->set_file(array('import' => 'import.tpl'));

				$dir_handle = opendir(PHPGW_APP_INC . SEP . 'import');
				$i=0; $myfilearray = '';
				while ($file = readdir($dir_handle))
				{
					//echo "<!-- ".is_file($phpgw_info["server"]["app_root"].$sep."import".$sep.$file)." -->";
					if ((substr($file, 0, 1) != '.') && is_file(PHPGW_APP_INC . SEP . 'import' . SEP . $file) )
					{
						$myfilearray[$i] = $file;
						$i++;
					}
				}
				closedir($dir_handle);
				sort($myfilearray);
				for ($i=0;$i<count($myfilearray);$i++)
				{
					$fname = ereg_replace('_',' ',$myfilearray[$i]);
					$conv .= '<OPTION VALUE="' . $myfilearray[$i].'">' . $fname . '</OPTION>';
				}

				$this->template->set_var('lang_cancel',lang('Cancel'));
				$this->template->set_var('lang_cat',lang('Select Category'));
				$this->template->set_var('cancel_url',$phpgw->link('/index.php','menuaction=addressbook.uiaddressbook.get_list'));
				$this->template->set_var('navbar_bg',$phpgw_info['theme']['navbar_bg']);
				$this->template->set_var('navbar_text',$phpgw_info['theme']['navbar_text']);
				$this->template->set_var('import_text',lang('Import from LDIF, CSV, or VCard'));
				$this->template->set_var('action_url',$phpgw->link('/index.php','menuaction=addressbook.uiXport.import'));
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
				$this->template->pparse('out','import');
			}
			$phpgw->common->phpgw_footer();
		}

		function export()
		{
			global $phpgw,$phpgw_info,$convert,$tsvfilename,$cat_id,$download,$conv_type;

			if ($convert)
			{
				$buffer = $this->bo->export($cat_id);

				if ($conv_type == 'none')
				{
					$phpgw_info['flags']['noheader'] = False;
					$phpgw_info['flags']['noheader'] = True;
					$phpgw->common->phpgw_header();
					echo parse_navbar();
					echo lang('<b>No conversion type &lt;none&gt; could be located.</b>  Please choose a conversion type from the list');
					echo '&nbsp<a href="'.$phpgw->link('/index.php','menuaction=addressbook.uiXport.export') . '">' . lang('OK') . '</a>';
					$phpgw->common->phpgw_footer();
					$phpgw->common->phpgw_exit();
				}

				if ( ($download == 'on') || ($o->type == 'pdb') )
				{
					// filename, default application/octet-stream, length of file, default nocache True
					$this->browser->content_header($tsvfilename,'application/octet-stream',strlen($buffer));
					echo $buffer;
				}
				else
				{
					$phpgw->common->phpgw_header();
					echo parse_navbar();
					echo "<pre>\n";
					echo $buffer;
					echo "\n</pre>\n";
					echo '<a href="'.$phpgw->link('/index.php','menuaction=addressbook.uiXport.export') . '">' . lang('OK') . '</a>';
					$phpgw->common->phpgw_footer();
				}
			}
			else
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();

				$this->template->set_file(array('export' => 'export.tpl'));

				$dir_handle = opendir(PHPGW_APP_INC. SEP . 'export');
				$i=0; $myfilearray = '';
				while ($file = readdir($dir_handle))
				{
					#echo "<!-- ".is_file($phpgw_info["server"]["app_root"].$sep."conv".$sep.$file)." -->";
					if ((substr($file, 0, 1) != '.') && is_file(PHPGW_APP_INC . SEP . 'export' . SEP . $file) )
					{
						$myfilearray[$i] = $file;
						$i++;
					}
				}
				closedir($dir_handle);
				sort($myfilearray);
				for ($i=0;$i<count($myfilearray);$i++)
				{
					$fname = ereg_replace('_',' ',$myfilearray[$i]);
					$conv .= '        <option value="'.$myfilearray[$i].'">'.$fname.'</option>'."\n";
				}

				$this->template->set_var('lang_cancel',lang('Cancel'));
				$this->template->set_var('lang_cat',lang('Select Category'));
				$this->template->set_var('cat_link',$this->cat_option($this->cat_id,False,False));
				$this->template->set_var('cancel_url',$phpgw->link('/addressbook/index.php'));
				$this->template->set_var('navbar_bg',$phpgw_info['theme']['navbar_bg']);
				$this->template->set_var('navbar_text',$phpgw_info['theme']['navbar_text']);
				$this->template->set_var('export_text',lang('Export from Addressbook'));
				$this->template->set_var('action_url',$phpgw->link('/index.php','menuaction=addressbook.uiXport.export'));
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

				$phpgw->common->phpgw_footer();
			}
		}
	}
?>
