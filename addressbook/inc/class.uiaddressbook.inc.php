<?php
  /**************************************************************************\
  * phpGroupWare - Addressbook                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Joseph Engo <jengo@phpgroupware.org> and                      *
  * Miles Lott <miloschphpgroupware.org>                                     *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class uiaddressbook
	{
		var $template;
		var $contacts;
		var $bo;
		var $cat;
		var $company;
		var $prefs;

		var $debug = False;

		var $start;
		var $limit;
		var $query;
		var $sort;
		var $order;
		var $filter;
		var $cat_id;

		var $template;
		var $public_functions = array(
			'get_list' => True,
			'view' => True,
			'add'  => True,
			'edit' => True
		);

	 	var $extrafields = array(
			'ophone'   => 'ophone',
			'address2' => 'address2',
			'address3' => 'address3'
		);

		function uiaddressbook()
		{
			global $phpgw,$phpgw_info;

			$phpgw->country    = CreateObject('phpgwapi.country');
			$phpgw->browser    = CreateObject('phpgwapi.browser');
			$phpgw->nextmatchs = CreateObject('phpgwapi.nextmatchs');

			$this->bo       = CreateObject('addressbook.boaddressbook',True);
			$this->template = &$phpgw->template;
			$this->contacts = &$phpgw->contacts;
			$this->cat      = CreateObject('phpgwapi.categories');
			$this->company  = CreateObject('phpgwapi.categories','addressbook_company');
			$this->prefs    = &$phpgw_info['user']['preferences']['addressbook'];

			$this->start    = &$this->bo->start;
			$this->limit    = &$this->bo->limit;
			$this->query    = &$this->bo->query;
			$this->sort     = &$this->bo->sort;
			$this->order    = &$this->bo->order;
			$this->filter   = &$this->bo->filter;
			$this->cat_id   = &$this->bo->cat_id;
			if($this->debug) { $this->_debug_sqsof(); }
/*			_debug_array($this); */
		}

		function _debug_sqsof()
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
			echo '<br>UI:';
			_debug_array($data);
		}

		/* Called only by get_list(), just prior to page footer. */
		function save_sessiondata()
		{
			$this->bo->save_sessiondata();
		}

		function formatted_list($name,$list,$id='',$default=False,$java=False)
		{
			if ($java)
			{
				$jselect = ' onChange="this.form.submit();"';
			}

			$select  = "\n" .'<select name="' . $name . '"' . $jselect . ">\n";
			if($default)
			{
				$select .= '<option value="">' . lang('Please Select') . '</option>'."\n";
			}
			while (list($key,$val) = each($list))
			{
				$select .= '<option value="' . $key . '"';
				if ($key == $id && $id != '')
				{
					$select .= ' selected';
				}
				$select .= '>' . $val . '</option>'."\n";
			}

			$select .= '</select>'."\n";
			$select .= '<noscript><input type="submit" name="' . $name . '_select" value="True"></noscript>' . "\n";

			return $select;
		}

		function read_custom_fields()
		{
			$i = 0; $j = 0;
			$fields = array();
			@reset($this->prefs);
			while (list($col,$descr) = @each($this->prefs))
			{
				if ( substr($col,0,6) == 'extra_' )
				{
					$fields[$j]['name'] = ereg_replace('extra_','',$col);
					$fields[$j]['name'] = ereg_replace(' ','_',$fields[$j]['name']);
					$fields[$j]['id'] = $i;

					if ($query && ($fields[$j]['name'] != $query))
					{
						unset($fields[$j]['name']);
						unset($fields[$j]['id']);
					}
					else
					{
						/* echo "<br>".$j.": '".$fields[$j]['name']."'"; */
						$j++;
					}
				}
				$i++;
			}
			@reset($fields);
			return $fields;
		}

		function save_custom_field($old='',$new='')
		{
			global $phpgw,$phpgw_info;
		
			$phpgw->preferences->read_repository($phpgw_info['user']['account_id']);
			if ($old)
			{
				$phpgw->preferences->delete("addressbook","extra_".$old);
			}
			if($new)
			{
				$phpgw->preferences->add("addressbook","extra_".$new);
			}
			$phpgw->preferences->save_repository(1);
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

		/* this cleans up the fieldnames for display */
		function display_name($column)
		{
			$abc = array(
				'fn'                  => 'full name',
				'sound'               => 'Sound',
				'org_name'            => 'company name',
				'org_unit'            => 'department',
				'title'               => 'title',
				'n_prefix'            => 'prefix',
				'n_given'             => 'first name',
				'n_middle'            => 'middle name',
				'n_family'            => 'last name',
				'n_suffix'            => 'suffix',
				'label'               => 'label',
				'adr_one_street'      => 'business street',
				'adr_one_locality'    => 'business city',
				'adr_one_region'      => 'business state',
				'adr_one_postalcode'  => 'business zip code',
				'adr_one_countryname' => 'business country',
				'adr_one_type'        => 'business address type',
				'adr_two_street'      => 'home street',
				'adr_two_locality'    => 'home city',
				'adr_two_region'      => 'home state',
				'adr_two_postalcode'  => 'home zip code',
				'adr_two_countryname' => 'home country',
				'adr_two_type'        => 'home address type',
				'tz'                  => 'time zone',
				'geo'                 => 'geo',
				'tel_work'            => 'business phone',
				'tel_home'            => 'home phone',
				'tel_voice'           => 'voice phone',
				'tel_msg'             => 'message phone',
				'tel_fax'             => 'fax',
				'tel_pager'           => 'pager',
				'tel_cell'            => 'mobile phone',
				'tel_bbs'             => 'bbs phone',
				'tel_modem'           => 'modem phone',
				'tel_isdn'            => 'isdn phone',
				'tel_car'             => 'car phone',
				'tel_video'           => 'video phone',
				'tel_prefer'          => 'preferred phone',
				'email'               => 'business email',
				'email_type'          => 'business email type',
				'email_home'          => 'home email',
				'email_home_type'     => 'home email type',
				'address2'            => 'address line 2',
				'address3'            => 'address line 3',
				'ophone'              => 'Other Phone',
				'bday'                => 'birthday',
				'url'                 => 'url',
				'pubkey'              => 'public key',
				'note'                => 'notes'
			);
		
			if ($abc[$column])
			{
				return lang($abc[$column]);
			}
			else
			{
				return;
			}
		}

		/*
			Former index.php
		*/
		function get_list()
		{
			global $phpgw,$phpgw_info;

			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$this->template->set_file(array('addressbook_list_t' => 'index.tpl'));
			$this->template->set_block('addressbook_list_t','addressbook_header','addressbook_header');
			$this->template->set_block('addressbook_list_t','column','column');
			$this->template->set_block('addressbook_list_t','row','row');
			$this->template->set_block('addressbook_list_t','addressbook_footer','addressbook_footer');

			$customfields = $this->read_custom_fields();

			if (!isset($this->cat_id))
			{
				$this->cat_id = $this->prefs['default_category'];
			} 
			if ($this->prefs['autosave_category'])
			{
				$phpgw->preferences->read_repository();
				$phpgw->preferences->delete('addressbook','default_category');
				$phpgw->preferences->add('addressbook','default_category',$this->cat_id);
				$phpgw->preferences->save_repository();
			}

			/* $qfields = $contacts->stock_contact_fields + $extrafields + $customfields; */
			/* create column list and the top row of the table based on user prefs */
			while ($column = each($this->contacts->stock_contact_fields))
			{
				$test = strtolower($column[0]);
				if (isset($this->prefs[$test]) && $this->prefs[$test])
				{
					$showcol = $this->display_name($column[0]);
					$cols .= '  <td height="21">' . "\n";
					$cols .= '    <font size="-1" face="Arial, Helvetica, sans-serif">';
					$cols .= $phpgw->nextmatchs->show_sort_order($this->sort,
						$column[0],$this->order,"/addressbook/main.php",$showcol,'&menuaction=addressbook.uiaddressbook.get_list');
					$cols .= "</font>\n  </td>";
					$cols .= "\n";

					/* To be used when displaying the rows */
					$columns_to_display[$column[0]] = True;
				}
			}
			/* Setup the columns for non-standard fields, since we don't allow sorting */
			$nonstd = $this->extrafields + $customfields;
			while ($column = each($nonstd))
			{
				$test = strtolower($column[1]);
				if (isset($this->prefs[$test]) && $this->prefs[$test])
				{
					$showcol = $this->display_name($column[0]);
					/* This must be a custom field */
					if (!$showcol) { $showcol = $column[1]; }
					$cols .= '  <td height="21">' . "\n";
					$cols .= '    <font size="-1" face="Arial, Helvetica, sans-serif">';
					$cols .= $showcol;
					$cols .= "</font>\n  </td>";
					$cols .= "\n";

					/* To be used when displaying the rows */
					$columns_to_display[$column[0]] = True;
				}
			}
			/* Check if prefs were set, if not, create some defaults */
			if (!$columns_to_display )
			{
				$columns_to_display = array(
					'n_given'  => 'n_given',
					'n_family' => 'n_family',
					'org_name' => 'org_name'
				);
				$columns_to_display = $columns_to_display + $customfields;
				/* No prefs,. so cols above may have been set to "" or a bunch of <td></td> */
				$cols='';
				while ($column = each($columns_to_display))
				{
					$showcol = $this->display_name($column[0]);
					if (!$showcol) { $showcol = $column[1]; }
					$cols .= '  <td height="21">' . "\n";
					$cols .= '    <font size="-1" face="Arial, Helvetica, sans-serif">';
					$cols .= $phpgw->nextmatchs->show_sort_order($this->sort,
						$column[0],$this->order,"/addressbook/main.php",$showcol,'menuaction=addressbook.uiaddressbook.get_list&cat_id='.$this->cat_id);
					$cols .= "</font>\n  </td>";
					$cols .= "\n";
				}
				$noprefs=lang('Please set your preferences for this app');
			}

			if (!$this->start)
			{
				$this->start = 0;
			}

			if($phpgw_info['user']['preferences']['common']['maxmatchs'] &&
				$phpgw_info['user']['preferences']['common']['maxmatchs'] > 0)
			{
				$this->limit = $phpgw_info['user']['preferences']['common']['maxmatchs'];
			}
			else
			{
				$this->limit = 30;
			}

			global $filter;
			if (empty($filter) || !isset($filter))
			{
				if($this->prefs['default_filter'])
				{
					$this->filter = $this->prefs['default_filter'];
					$this->query = '';
				}
				else
				{
					$this->filter = 'none';
				}
			}

			/*
			Set qfilter to display entries where tid=n (normal contact entry),
			else they may be accounts, etc.
			*/
			$qfilter = 'tid=n';
			switch ($this->filter)
			{
				case 'blank':
					$nosearch = True;
					break;
				case 'none':
					break;
				case 'private':
					$qfilter .= ',access=private';	/* fall through */
				case 'yours':
					$qfilter .= ',owner='.$phpgw_info['user']['account_id'];
					break;
				default:
					$qfilter .= ',owner='.$this->filter;
			}
			if ($this->cat_id)
			{
				$qfilter .= ',cat_id='.$this->cat_id;
			}
 
			if (!$userid) { $userid = $phpgw_info['user']['account_id']; }

			if ($nosearch && !$this->query)
			{
				$entries = array();
				$this->contacts->total_records = 0;
			}
			else
			{
				/* read the entry list */
				$entries = $this->bo->read_entries($this->start,$this->limit,$columns_to_display,$qfilter,$userid);
			}

			/* global here so nextmatchs accepts our setting of $query */
			global $query;

			$query = $this->query;
			$search_filter = $phpgw->nextmatchs->show_tpl('/addressbook/main.php',
				$this->start, $this->contacts->total_records,'&menuaction=addressbook.uiaddressbook.get_list',"75%",
				$phpgw_info["theme"]["th_bg"],1,1,1,1);

			$lang_showing = $phpgw->nextmatchs->show_hits($this->contacts->total_records,$this->start);

			/* set basic vars and parse the header */
			$this->template->set_var('font',$phpgw_info['theme']['font']);
			$this->template->set_var('lang_view',lang('View'));
			$this->template->set_var('lang_vcard',lang('VCard'));
			$this->template->set_var('lang_edit',lang('Edit'));
			$this->template->set_var('lang_owner',lang('Owner'));

			$this->template->set_var('searchreturn',$noprefs . ' ' . $searchreturn);
			$this->template->set_var('lang_showing',$lang_showing);
			$this->template->set_var('search_filter',$search_filter);
			$this->template->set_var('cats',lang('Category'));
			$this->template->set_var('cats_url',$phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
			$this->template->set_var('cats_link',$this->cat_option($this->cat_id));
			$this->template->set_var('lang_cats',lang('Select'));
			$this->template->set_var('lang_addressbook',lang('Address book'));
			$this->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);
			$this->template->set_var('th_font',$phpgw_info['theme']['font']);
			$this->template->set_var('th_text',$phpgw_info['theme']['th_text']);
			$this->template->set_var('lang_add',lang('Add'));
			$this->template->set_var('add_url',$phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.add'));
			$this->template->set_var('lang_addvcard',lang('AddVCard'));
			$this->template->set_var('vcard_url',$phpgw->link('/addressbook/main.php','menuaction=addressbook.uivcard.in'));
			$this->template->set_var('lang_import',lang('Import Contacts'));
			$this->template->set_var('import_url',$phpgw->link('/addressbook/main.php','menuaction=addressbook.uiXport.import'));
			$this->template->set_var('lang_import_alt',lang('Alt. CSV Import'));
			$this->template->set_var('import_alt_url',$phpgw->link('/addressbook/csv_import.php'));
			$this->template->set_var('lang_export',lang('Export Contacts'));
			$this->template->set_var('export_url',$phpgw->link('/addressbook/main.php','menuaction=addressbook.uiXport.export'));

			$this->template->set_var('start',$this->start);
			$this->template->set_var('sort',$this->sort);
			$this->template->set_var('order',$this->order);
			$this->template->set_var('filter',$this->filter);
			$this->template->set_var('query',$this->query);
			$this->template->set_var('cat_id',$this->cat_id);

			$this->template->set_var('qfield',$qfield);
			$this->template->set_var('cols',$cols);

			$this->template->pparse('out','addressbook_header');

			/* Show the entries */
			/* each entry */
			for ($i=0;$i<count($entries);$i++)
			{
				$this->template->set_var('columns','');
				$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
				$this->template->set_var('row_tr_color',$tr_color);
				$myid    = $entries[$i]['id'];
				$myowner = $entries[$i]['owner'];

				/* each entry column */
				while ($column = each($columns_to_display))
				{
					$ref = $data='';
					$coldata = $entries[$i][$column[0]];
					/* Some fields require special formatting. */
					if ($column[0] == 'url')
					{
						if ( !empty($coldata) && (substr($coldata,0,7) != 'http://') ) { $coldata = 'http://' . $coldata; }
						$ref='<a href="'.$coldata.'" target="_new">';
						$data=$coldata.'</a>';
					}
					elseif ( ($column[0] == 'email') || ($column[0] == 'email_home') )
					{
						if ($phpgw_info['user']['apps']['email'])
						{
							$ref='<a href="'.$phpgw->link("/email/compose.php","to=" . urlencode($coldata)).'" target="_new">';
						}
						else
						{
							$ref='<a href="mailto:'.$coldata.'">';
						}
						$data=$coldata . '</a>';
					}
					else /* But these do not */
					{
						$ref = ''; $data = $coldata;
					}
					$this->template->set_var('col_data',$ref.$data);
					$this->template->parse('columns','column',True);
				}

				if (1)
				{
					$this->template->set_var('row_view_link',$phpgw->link('/addressbook/main.php',
						'menuaction=addressbook.uiaddressbook.view&ab_id='.$entries[$i]['id']));
				}
				else
				{
					$this->template->set_var('row_view_link','');
					$this->template->set_var('lang_view',lang('Private'));
				}

				$this->template->set_var('row_vcard_link',$phpgw->link('/addressbook/main.php',
					'menuaction=addressbook.uivcard.out&ab_id='.$entries[$i]['id']));
				/* echo '<br>: ' . $contacts->grants[$myowner] . ' - ' . $myowner; */
				if ($this->contacts->check_perms($this->contacts->grants[$myowner],PHPGW_ACL_EDIT) || $myowner == $phpgw_info['user']['account_id'])
				{
					$this->template->set_var('row_edit','<a href="' . $phpgw->link("/addressbook/main.php",
						'menuaction=addressbook.uiaddressbook.edit&ab_id='.$entries[$i]['id']) . '">' . lang('Edit') . '</a>');
				}
				else
				{
					$this->template->set_var('row_edit','&nbsp;');
				}

				$this->template->set_var('row_owner',$phpgw->accounts->id2name($myowner));

				$this->template->parse('rows','row',True);
				$this->template->pparse('out','row');
				reset($columns_to_display);
			}

			$this->template->pparse('out','addressbook_footer');
			$this->save_sessiondata();
			$phpgw->common->phpgw_footer();
		}

		/* Following used for add/edit */
		function addressbook_form($format,$action,$title='',$fields='',$customfields='',$cat_id='')
		{
			global $phpgw,$phpgw_info,$referer;

			$this->template->set_file(array('form' => 'form.tpl'));

			if ( ($phpgw_info['server']['countrylist'] == 'user_choice' &&
				$phpgw_info['user']['preferences']['common']['countrylist'] == 'use_select') ||
				($phpgw_info['server']['countrylist'] == 'force_select'))
			{
				$countrylist  = True;
			}

			$email      = $fields['email'];
			$emailtype  = $fields['email_type'];
			$hemail     = $fields['email_home'];
			$hemailtype = $fields['email_home_type'];
			$firstname  = $fields['n_given'];
			$middle     = $fields['n_middle'];
			$prefix     = $fields['n_prefix'];
			$suffix     = $fields['n_suffix'];
			$lastname   = $fields['n_family'];
			$title      = $fields['title'];
			$wphone     = $fields['tel_work'];
			$hphone     = $fields['tel_home'];
			$fax        = $fields['tel_fax'];
			$pager      = $fields['tel_pager'];
			$mphone     = $fields['tel_cell'];
			$ophone     = $fields['ophone'];
			$msgphone   = $fields['tel_msg'];
			$isdnphone  = $fields['tel_isdn'];
			$carphone   = $fields['tel_car'];
			$vidphone   = $fields['tel_video'];
			$preferred  = $fields['tel_prefer'];

			$bstreet    = $fields['adr_one_street'];
			$address2   = $fields['address2'];
			$address3   = $fields['address3'];
			$bcity      = $fields['adr_one_locality'];
			$bstate     = $fields['adr_one_region'];
			$bzip       = $fields['adr_one_postalcode'];
			$bcountry   = $fields['adr_one_countryname'];
			$one_dom    = $fields['one_dom'];
			$one_intl   = $fields['one_intl'];
			$one_parcel = $fields['one_parcel'];
			$one_postal = $fields['one_postal'];

			$hstreet    = $fields['adr_two_street'];
			$hcity      = $fields['adr_two_locality'];
			$hstate     = $fields['adr_two_region'];
			$hzip       = $fields['adr_two_postalcode'];
			$hcountry   = $fields['adr_two_countryname'];
			$btype      = $fields['adr_two_type'];
			$two_dom    = $fields['two_dom'];
			$two_intl   = $fields['two_intl'];
			$two_parcel = $fields['two_parcel'];
			$two_postal = $fields['two_postal'];

			$timezone   = $fields['tz'];
			$bday       = $fields['bday'];
			$notes      = stripslashes($fields['note']);
			$label      = stripslashes($fields['label']);
			$company    = $fields['org_name'];
			$department = $fields['org_unit'];
			$url        = $fields['url'];
			$pubkey     = $fields['pubkey'];
			$access     = $fields['access'];
			if(!$cat_id)
			{
				$cat_id = $fields['cat_id'];
			}
			/* allow multiple categories on sql */
			$cats_link = $this->cat_option(
				$cat_id,
				True,
				False,
				!$phpgw_info['server']['contact_repository'] || $phpgw_info['server']['contact_repository'] == 'sql'
			);

			if ($access == 'private')
			{
				$access_check = ' checked';
			}
			else
			{
				$access_check = '';
			}

			if ($customfields)
			{
				while(list($name,$value) = each($customfields))
				{
					$value = ereg_replace('_',' ',$value);
					$custom .= '
  <tr bgcolor="' . $phpgw_info['theme']['row_off'] . '">
    <td>&nbsp;</td>
    <td><font color="' . $phpgw_info['theme']['th_text'] . '" face="" size="-1">'.$value.':</font></td>
    <td colspan="3"><INPUT size="30" name="entry[' . $name . ']" value="' . $fields[$name] . '"></td>
  </tr>
';
				}
			}

			if ($format != "view")
			{
				// Preferred phone number radio buttons
				$pref[0] = '<font size="-2">';
				$pref[1] = '(' . lang('pref') . ')</font>';
				while (list($name,$val) = each($this->contacts->tel_types))
				{
					$str[$name] = "\n".'      <input type="radio" name="entry[tel_prefer]" value="'.$name.'"';
					if ($name == $preferred)
					{
						$str[$name] .= ' checked';
					}
					$str[$name] .= '>';
					$str[$name] = $pref[0].$str[$name].$pref[1];
					$this->template->set_var("pref_".$name,$str[$name]);
				}

				if (strlen($bday) > 2)
				{
					list( $month, $day, $year ) = split( '/', $bday );
					$temp_month[$month] = ' selected';
					$bday_month = '<select name="entry[bday_month]">'
						. '<option value=""'   . $temp_month[0]  . '>' . '</option>'
						. '<option value="1"'  . $temp_month[1]  . '>' . lang('january')   . '</option>' 
						. '<option value="2"'  . $temp_month[2]  . '>' . lang('february')  . '</option>'
						. '<option value="3"'  . $temp_month[3]  . '>' . lang('march')     . '</option>'
						. '<option value="4"'  . $temp_month[4]  . '>' . lang('april')     . '</option>'
						. '<option value="5"'  . $temp_month[5]  . '>' . lang('may')       . '</option>'
						. '<option value="6"'  . $temp_month[6]  . '>' . lang('june')      . '</option>' 
						. '<option value="7"'  . $temp_month[7]  . '>' . lang('july')      . '</option>'
						. '<option value="8"'  . $temp_month[8]  . '>' . lang('august')    . '</option>'
						. '<option value="9"'  . $temp_month[9]  . '>' . lang('september') . '</option>'
						. '<option value="10"' . $temp_month[10] . '>' . lang('october')   . '</option>'
						. '<option value="11"' . $temp_month[11] . '>' . lang('november')  . '</option>'
						. '<option value="12"' . $temp_month[12] . '>' . lang('december')  . '</option>'
						. '</select>';
					$bday_day  = '<input maxlength="2" name="entry[bday_day]"  value="' . $day . '" size="2">';
					$bday_year = '<input maxlength="4" name="entry[bday_year]" value="' . $year . '" size="4">';
				}
				else
				{
					$bday_month = '<select name="entry[bday_month]">'
						. '<option value="" selected> </option>'
						. '<option value="1">'  . lang('january')   . '</option>'
						. '<option value="2">'  . lang('february')  . '</option>'
						. '<option value="3">'  . lang('march')     . '</option>'
						. '<option value="4">'  . lang('april')     . '</option>'
						. '<option value="5">'  . lang('may')       . '</option>'
						. '<option value="6">'  . lang('june')      . '</option>'
						. '<option value="7">'  . lang('july')      . '</option>'
						. '<option value="8">'  . lang('august')    . '</option>'
						. '<option value="9">'  . lang('september') . '</option>'
						. '<option value="10">' . lang('october')   . '</option>'
						. '<option value="11">' . lang('november')  . '</option>'
						. '<option value="12">' . lang('december')  . '</option>'
						. '</select>';
					$bday_day  = '<input name="entry[bday_day]"  size="2" maxlength="2">';
					$bday_year = '<input name="entry[bday_year]" size="4" maxlength="4">';
				}

				$time_zone = '<select name="entry[timezone]">' . "\n";
				for ($i = -23; $i<24; $i++)
				{
					$time_zone .= "<option value=\"$i\"";
					if ($i == $timezone)
					{
						$time_zone .= " selected";
					}
					if ($i < 1)
					{
						$time_zone .= ">$i</option>\n";
					}
					else
					{
						$time_zone .= ">+$i</option>\n";
					}
				}
				$time_zone .= "</select>\n";

				$email_type = '<select name=entry[email_type]>';
				while ($type = each($this->contacts->email_types))
				{
					$email_type .= '<option value="'.$type[0].'"';
					if ($type[0] == $emailtype) { $email_type .= ' selected'; }
					$email_type .= '>'.$type[1].'</option>';
				}
				$email_type .= "</select>";

				reset($this->contacts->email_types);
				$hemail_type = '<select name=entry[hemail_type]>';
				while ($type = each($this->contacts->email_types))
				{
					$hemail_type .= '<option value="'.$type[0].'"';
					if ($type[0] == $hemailtype) { $hemail_type .= ' selected'; }
					$hemail_type .= '>'.$type[1].'</option>';
				}
				$hemail_type .= "</select>";

				reset($this->contacts->adr_types);
				while (list($type,$val) = each($this->contacts->adr_types))
				{
					$badrtype .= "\n".'<INPUT type="checkbox" name="entry[one_'.$type.']"';
					$ot = 'one_'.$type;
					eval("
						if (\$$ot=='on') {
							\$badrtype \.= ' value=\"on\" checked';
						}
					");
					$badrtype .= '>'.$val;
				}

				reset($this->contacts->adr_types);
				while (list($type,$val) = each($this->contacts->adr_types))
				{
					$hadrtype .= "\n".'<INPUT type="checkbox" name="entry[two_'.$type.']"';
					$tt = 'two_'.$type;
					eval("
						if (\$$tt=='on') {
							\$hadrtype \.= ' value=\"on\" checked';
						}
					");
					$hadrtype .= '>'.$val;
				}

				$notes  = '<TEXTAREA cols="60" name="entry[notes]" rows="4">' . $notes . '</TEXTAREA>';
				$label  = '<TEXTAREA cols="60" name="entry[label]" rows="6">' . $label . '</TEXTAREA>';
				$pubkey = '<TEXTAREA cols="60" name="entry[pubkey]" rows="6">' . $pubkey . '</TEXTAREA>';
			}
			else
			{
				$notes = '<form><TEXTAREA cols="60" name="entry[notes]" rows="4">'
						. $notes . '</TEXTAREA></form>';
				if ($bday == "//")
				{
					$bday = "";
				}
			}

			if ($action)
			{
				echo '<FORM action="' . $phpgw->link('/addressbook/main.php', $action . '&referer='.urlencode($referer)).'" method="post">';
			}

			if (!ereg("^http://",$url))
			{
				$url = "http://". $url;
			} 

			$birthday = $phpgw->common->dateformatorder($bday_year,$bday_month,$bday_day)
				. '<font face="'.$theme["font"].'" size="-2">(e.g. 1969)</font>';
			if ($format == 'edit')
			{
				$create .= '<tr bgcolor="' . $phpgw_info['theme']['th_bg'] . '"><td colspan="2"><font size="-1">' . lang("Created by") . ':</font></td>'
					. '<td colspan="3"><font size="-1">'
					. $phpgw->common->grab_owner_name($fields["owner"]);
			}
			else
			{
				$create .= '';
			}
 
			$this->template->set_var('lang_home',lang('Home'));
			$this->template->set_var('lang_business',lang('Business'));
			$this->template->set_var('lang_personal',lang('Personal'));

			$this->template->set_var('lang_lastname',lang('Last Name'));
			$this->template->set_var('lastname',$lastname);
			$this->template->set_var('lang_firstname',lang('First Name'));
			$this->template->set_var('firstname',$firstname);
			$this->template->set_var('lang_middle',lang('Middle Name'));
			$this->template->set_var('middle',$middle);
			$this->template->set_var('lang_prefix',lang('Prefix'));
			$this->template->set_var('prefix',$prefix);
			$this->template->set_var('lang_suffix',lang('Suffix'));
			$this->template->set_var('suffix',$suffix);
			$this->template->set_var('lang_birthday',lang('Birthday'));
			$this->template->set_var('birthday',$birthday);

			$this->template->set_var('lang_company',lang('Company Name'));
			$this->template->set_var('company',$company);
			$this->template->set_var('lang_department',lang('Department'));
			$this->template->set_var('department',$department);
			$this->template->set_var('lang_title',lang('Title'));
			$this->template->set_var('title',$title);
			$this->template->set_var('lang_email',lang('Business Email'));
			$this->template->set_var('email',$email);
			$this->template->set_var('lang_email_type',lang('Business EMail Type'));
			$this->template->set_var('email_type',$email_type);
			$this->template->set_var('lang_url',lang('URL'));
			$this->template->set_var('url',$url);
			$this->template->set_var('lang_timezone',lang('time zone offset'));
			$this->template->set_var('timezone',$time_zone);
			$this->template->set_var('lang_fax',lang('Business Fax'));
			$this->template->set_var('fax',$fax);
			$this->template->set_var('lang_wphone',lang('Business Phone'));
			$this->template->set_var('wphone',$wphone);
			$this->template->set_var('lang_pager',lang('Pager'));
			$this->template->set_var('pager',$pager);
			$this->template->set_var('lang_mphone',lang('Cell Phone'));
			$this->template->set_var('mphone',$mphone);
			$this->template->set_var('lang_msgphone',lang('Message Phone'));
			$this->template->set_var('msgphone',$msgphone);
			$this->template->set_var('lang_isdnphone',lang('ISDN Phone'));
			$this->template->set_var('isdnphone',$isdnphone);
			$this->template->set_var('lang_carphone',lang('Car Phone'));
			$this->template->set_var('carphone',$carphone);
			$this->template->set_var('lang_vidphone',lang('Video Phone'));
			$this->template->set_var('vidphone',$vidphone);

			$this->template->set_var('lang_ophone',lang('Other Number'));
			$this->template->set_var('ophone',$ophone);
			$this->template->set_var('lang_bstreet',lang('Business Street'));
			$this->template->set_var('bstreet',$bstreet);
			$this->template->set_var('lang_address2',lang('Address Line 2'));
			$this->template->set_var('address2',$address2);
			$this->template->set_var('lang_address3',lang('Address Line 3'));
			$this->template->set_var('address3',$address3);
			$this->template->set_var('lang_bcity',lang('Business City'));
			$this->template->set_var('bcity',$bcity);
			$this->template->set_var('lang_bstate',lang('Business State'));
			$this->template->set_var('bstate',$bstate);
			$this->template->set_var('lang_bzip',lang('Business Zip Code'));
			$this->template->set_var('bzip',$bzip);
			$this->template->set_var('lang_bcountry',lang('Business Country'));
			$this->template->set_var('bcountry',$bcountry);
			if ($countrylist)
			{
				$this->template->set_var('bcountry',$phpgw->country->form_select($bcountry,'entry[bcountry]'));
			}
			else
			{
				 $this->template->set_var('bcountry','<input name="entry[bcountry]" value="' . $bcountry . '">');
			}
			$this->template->set_var('lang_badrtype',lang('Address Type'));
			$this->template->set_var('badrtype',$badrtype);

			$this->template->set_var('lang_hphone',lang('Home Phone'));
			$this->template->set_var('hphone',$hphone);
			$this->template->set_var('lang_hemail',lang('Home Email'));
			$this->template->set_var('hemail',$hemail);
			$this->template->set_var('lang_hemail_type',lang('Home EMail Type'));
			$this->template->set_var('hemail_type',$hemail_type);
			$this->template->set_var('lang_hstreet',lang('Home Street'));
			$this->template->set_var('hstreet',$hstreet);
			$this->template->set_var('lang_hcity',lang('Home City'));
			$this->template->set_var('hcity',$hcity);
			$this->template->set_var('lang_hstate',lang('Home State'));
			$this->template->set_var('hstate',$hstate);
			$this->template->set_var('lang_hzip',lang('Home Zip Code'));
			$this->template->set_var('hzip',$hzip);
			$this->template->set_var('lang_hcountry',lang('Home Country'));
			if ($countrylist)
			{
				$this->template->set_var('hcountry',$phpgw->country->form_select($hcountry,'entry[hcountry]'));
			}
			else
			{
				$this->template->set_var('hcountry','<input name="entry[hcountry]" value="' . $hcountry . '">');
			}
			$this->template->set_var('lang_hadrtype',lang('Address Type'));
			$this->template->set_var('hadrtype',$hadrtype);

			$this->template->set_var('create',$create);
			$this->template->set_var('lang_notes',lang('notes'));
			$this->template->set_var('notes',$notes);
			$this->template->set_var('lang_label',lang('label'));
			$this->template->set_var('label',$label);
			$this->template->set_var('lang_pubkey',lang('Public Key'));
			$this->template->set_var('pubkey',$pubkey);
			$this->template->set_var('access_check',$access_check);

			$this->template->set_var('lang_private',lang('Private'));

			$this->template->set_var('lang_cats',lang('Category'));
			$this->template->set_var('cats_link',$cats_link);
			if ($customfields)
			{
				$this->template->set_var('lang_custom',lang('Custom Fields').':');
				$this->template->set_var('custom',$custom);
			}
			else
			{
				$this->template->set_var('lang_custom','');
				$this->template->set_var('custom','');
			}
			$this->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);
			$this->template->set_var('th_text',$phpgw_info['theme']['th_text']);
			$this->template->set_var('row_on',$phpgw_info['theme']['row_on']);
			$this->template->set_var('row_off',$phpgw_info['theme']['row_off']);
			$this->template->set_var('row_text',$phpgw_info['theme']['row_text']);

			$this->template->pfp('out','form');
		} //end form function

		function add()
		{
			global $phpgw,$phpgw_info,$referer;

			$this->template->set_file(array('add' => 'add.tpl'));

			$phpgw->common->phpgw_header();
			echo parse_navbar();

			$this->addressbook_form('','menuaction=addressbook.boaddressbook.add_entry','Add','',$customfields,$this->cat_id);

			$this->template->set_var('lang_ok',lang('ok'));
			$this->template->set_var('lang_clear',lang('clear'));
			$this->template->set_var('lang_cancel',lang('cancel'));
			$this->template->set_var('cancel_url',$phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
			$this->template->parse('out','add');
			$this->template->pparse('out','add');

			$phpgw->common->phpgw_footer();
		}

		function edit()
		{
			global $phpgw,$phpgw_info,$ab_id,$referer;

			/* First, make sure they have permission to this entry */
			$check = $this->bo->read_entry($ab_id,array('owner' => 'owner', 'tid' => 'tid'));

			if ( !$this->contacts->check_perms($this->contacts->grants[$check[0]['owner']],PHPGW_ACL_EDIT) && ($check[0]['owner'] != $phpgw_info['user']['account_id']) )
			{
				Header("Location: " . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
				$phpgw->common->phpgw_exit();
			}

			if (!$ab_id)
			{
				Header("Location: " . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
				$phpgw->common->phpgw_exit();
			}

			$phpgw->common->phpgw_header();
			echo parse_navbar();

			/* Read in user custom fields, if any */
			$customfields = $this->read_custom_fields();

			/* merge in extra fields */
			$qfields = $this->contacts->stock_contact_fields + $this->extrafields + $customfields;
			$fields = $this->bo->read_entry($ab_id,$qfields);
			$this->addressbook_form('edit','menuaction=addressbook.boaddressbook.update_entry',lang('Edit'),$fields[0],$customfields);

			$this->template->set_file(array('edit' => 'edit.tpl'));

			$this->template->set_var('th_bg',$phpgw_info['theme']['th_bg']);
			$this->template->set_var('ab_id',$ab_id);
			$this->template->set_var('tid',$check[0]['tid']);
			$this->template->set_var('referer',$referer);
			$this->template->set_var('lang_ok',lang('ok'));
			$this->template->set_var('lang_clear',lang('clear'));
			$this->template->set_var('lang_cancel',lang('cancel'));
			$this->template->set_var('lang_submit',lang('submit'));
			$this->template->set_var('cancel_link','<form method="POST" action="' . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list') . '">');

			if (($this->contacts->grants[$check[0]['owner']] & PHPGW_ACL_DELETE) || $check[0]['owner'] == $phpgw_info['user']['account_id'])
			{
				$this->template->set_var('delete_link','<form method="POST" action="'.$phpgw->link("/addressbook/delete.php") . '">');
				$this->template->set_var('delete_button','<input type="submit" name="delete" value="' . lang('Delete') . '">');
			}

			$this->template->pfp('out','edit');
			$phpgw->common->phpgw_footer();
		}

		function view()
		{
			global $phpgw,$phpgw_info,$ab_id,$submit,$referer;

			// First, make sure they have permission to this entry
			$check = $this->bo->read_entry($ab_id,array('owner' => 'owner'));
			$perms = $this->contacts->check_perms($this->contacts->grants[$check[0]['owner']],PHPGW_ACL_READ);

			if ( (!$perms) && ($check[0]['owner'] != $phpgw_info['user']['account_id']) )
			{
				Header("Location: " . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
				$phpgw->common->phpgw_exit();
			}

			if (!$ab_id)
			{
				Header("Location: " . $phpgw->link('/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
				$phpgw->common->phpgw_exit();
			}
			elseif (!$submit && $ab_id)
			{
				$phpgw->common->phpgw_header();
				echo parse_navbar();
			}

			$this->template->set_file(array('view_t' => 'view.tpl'));
			$this->template->set_block('view_t','view_header','view_header');
			$this->template->set_block('view_t','view_row','view_row');
			$this->template->set_block('view_t','view_footer','view_footer');
			$this->template->set_block('view_t','view_buttons','view_buttons');

			$customfields = $this->read_custom_fields();

			while ($column = each($this->contacts->stock_contact_fields))
			{
				if (isset($this->prefs[$column[0]]) && $this->prefs[$column[0]])
				{
					$columns_to_display[$column[0]] = True;
					$colname[$column[0]] = $column[0];
				}
			}

			// No prefs?
			if (!$columns_to_display )
			{
				$columns_to_display = array(
					'n_given'    => 'n_given',
					'n_family'   => 'n_family',
					'org_name'   => 'org_name',
					'tel_work'   => 'tel_work',
					'tel_home'   => 'tel_home',
					'email'      => 'email',
					'email_home' => 'email_home'
				);
				while ($column = each($columns_to_display))
				{
					$colname[$column[0]] = $column[1];
				}
				$noprefs = " - " . lang('Please set your preferences for this app');
			}

			/* merge in extra fields */
			$qfields = $this->contacts->stock_contact_fields + $this->extrafields + $customfields;

			$fields = $this->bo->read_entry($ab_id,$qfields);

			$record_owner = $fields[0]['owner'];

			if ($fields[0]["access"] == 'private')
			{
				$access_check = lang('private');
			}
			else
			{
				$access_check = lang('public');
			}

			$this->template->set_var('lang_viewpref',lang("Address book - view") . $noprefs);

			@reset($qfields);
			while (list($column,$null) = @each($qfields))
			{
				if($this->display_name($colname[$column]))
				{
					$this->template->set_var('display_col',$this->display_name($colname[$column]));
				}
				elseif($this->display_name($column))
				{
					$this->template->set_var('display_col',$this->display_name($column));
				}
				else
				{
					$this->template->set_var('display_col',ucfirst($column));
				}
				$ref = $data = "";
				if ($fields[0][$column])
				{
					$tr_color = $phpgw->nextmatchs->alternate_row_color($tr_color);
					$this->template->set_var('th_bg',$tr_color);
					$coldata = $fields[0][$column];
					/* Some fields require special formatting. */
					if ( ($column == 'note' || $column == 'pubkey') && $coldata )
					{
						$datarray = explode ("\n",$coldata);
						if ($datarray[1])
						{
							while (list($key,$info) = each ($datarray))
							{
								if ($key)
								{
									$data .= '</td></tr><tr bgcolor="'.$tr_color.'"><td width="30%">&nbsp;</td><td width="70%">' .$info;
								}
								else
								{
									/* First row, don't close td/tr */
									$data .= $info;
								}
							}
							$data .= "</tr>";
						}
						else
						{
							$data = $coldata;
						}
					}
					elseif($column == 'label' && $coldata)
					{
						$data .= $this->contacts->formatted_address($fields[0]['id'],'',False);
					}
					elseif ($column == "url" && $coldata)
					{
						$ref = '<a href="' . $coldata . '" target="_new">';
						$data = $coldata . '</a>';
					}
					elseif ( (($column == "email") || ($column == "email_home")) && $coldata)
					{
						if ($phpgw_info["user"]["apps"]["email"])
						{
							$ref='<a href="' . $phpgw->link("/email/compose.php","to="
								. urlencode($coldata)) . '" target="_new">';
						}
						else
						{
							$ref = '<a href="mailto:'.$coldata.'">';
						}
						$data = $coldata."</a>";
					}
					else
					{
						/* But these do not */
						$ref = ""; $data = $coldata;
					}

					if (!$data)
					{
						$this->template->set_var('ref_data',"&nbsp;");
					}
					else
					{
						$this->template->set_var('ref_data',$ref . $data);
					}
					$this->template->parse('cols','view_row',True);
				}
			}
			/* Following cleans up view_row, since we were only using it to fill {cols} */
			$this->template->set_var('view_row','');

			$fields['cat_id'] = is_array($this->cat_id) ? implode(',',$this->cat_id) : $this->cat_id;

			$cats = explode(',',$fields[0]['cat_id']);
			if ($cats[1])
			{
				while (list($key,$contactscat) = each($cats))
				{
					if ($contactscat)
					{
						$catinfo = $this->cat->return_single($contactscat);
						$catname .= $catinfo[0]['name'] . '; ';
					}
				}
				if (!$this->cat_id)
				{
					$this->cat_id = $cats[0];
				}
			}
			else
			{
				$fields[0]['cat_id'] = ereg_replace(',','',$fields[0]['cat_id']);
				$catinfo = $this->cat->return_single($fields[0]['cat_id']);
				$catname = $catinfo[0]['name'];
				if (!$this->cat_id)
				{
					$this->cat_id = $fields[0]['cat_id'];
				}
			}

			if (!$catname) { $catname = lang('none'); }

			// These are in the footer
			$this->template->set_var('lang_owner',lang('Record owner'));
			$this->template->set_var('owner',$phpgw->common->grab_owner_name($record_owner));
			$this->template->set_var('lang_access',lang("Record access"));
			$this->template->set_var('access',$access_check);
			$this->template->set_var('lang_category',lang('Category'));
			$this->template->set_var('catname',$catname);

			if (($this->contacts->grants[$record_owner] & PHPGW_ACL_EDIT) || ($record_owner == $phpgw_info['user']['account_id']))
			{
				$extra_vars = array('cd' => 16,'query' => $this->query,'cat_id' => $this->cat_id);

				if ($referer) $extra_vars += array( 'referer' => urlencode($referer));

				$this->template->set_var('edit_button',$this->html_1button_form('edit','Edit',array(),'/addressbook/main.php','menuaction=addressbook.uiaddressbook.edit&ab_id=' .$ab_id));
			}
			$this->template->set_var('copy_button',$this->html_1button_form('submit','copy',array(),
				'/addressbook/main.php','menuaction=addressbook.boaddressbook.copy_entry&ab_id=' . $fields[0]['id']));

			if ($fields[0]['n_family'] && $fields[0]['n_given'])
			{
				$this->template->set_var('vcard_button',$this->html_1button_form('VCardForm','VCard',array(),'/addressbook/main.php','menuaction=addressbook.uivcard.out&ab_id=' .$ab_id));
			}
			else
			{
				$this->template->set_var('vcard_button',lang('no vcard'));
			}

			$this->template->set_var('done_button',$this->html_1button_form('DoneForm','Done',array(),
				$referer ? ereg_replace('/phpgroupware','',$referer) : '/addressbook/main.php','menuaction=addressbook.uiaddressbook.get_list'));
			$this->template->set_var('access_link',$access_link);

			$this->template->pfp('out','view_t');

			$phpgw->common->hook('addressbook_view');

			$phpgw->common->phpgw_footer();
		}

		function html_input_hidden($vars)
		{
			if (!is_array($vars)) { return ''; }
			while (list($name,$value) = each($vars))
			{
				if ($value != '')					// dont need to send all the empty vars
				{
					$html .= '<input type="hidden" name="' . $name . '" value="' . $value . '">' . "\n";
				}
			}
			return $html;
		}

		function html_submit_button($name,$lang)
		{
			return '<input type="submit" name="' . $name .'" value="' . lang($lang) . '">' . "\n";
		}

		function phpgw_link($url,$vars='')
		{
			global $phpgw;
			if (is_array( $vars ))
			{
				while(list($name,$value) = each($vars))
				{
					if ($value != '')				// dont need to send all the empty vars
					{
						$v[] = "$name=$value";
					}
				}
				$vars = implode('&',$v);
			}
			return $phpgw->link($url,$vars);
		}				

		function html_1button_form($name,$lang,$hidden_vars,$url,$url_vars='',$method='POST')
		{
			$html = "<form method=\"$method\" action=\"" . $this->phpgw_link($url,$url_vars)."\">\n";
			$html .= $this->html_input_hidden($hidden_vars);
			$html .= $this->html_submit_button($name,$lang);
			$html .= "</form>\n";
			return $html;
		}
	}
?>
