<?php
  /**************************************************************************\
  * phpGroupWare API - Select Box 2                                          *
  * Written by Ralf Becker <RalfBecker@outdoor-training.de>                  *
  * Class for creating select boxes for addresse, projects, array items, ... *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
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

if(!isset($GLOBALS['phpgw_info']['flags']['included_classes']['sbox']))
{
	include(PHPGW_API_INC . '/class.sbox.inc.php');
	$GLOBALS['phpgw_info']['flags']['included_classes']['sbox'] = True;
}

	class sbox2 extends sbox
	{
		/*
		 * Function: search for an id of an db-entry, eg. an address
		 * Parameter: $name   base name for all template-vars and of the submitted vars (not to conflict with other template-var-names !!!)
		 *            $lang_name titel of the field
		 *            $prompt  for the JavaScript prompt()
		 *            $id_name  id of previosly selected entry
		 *            $content  from id (eg. 'company: lastname, givenname' for address $id) if $id != 0, or
		 *                      array with searchresult (id's as key), if array is empty if search was unsucsessful
		 * Returns:  array with vars to set in temaplate, the vars are:
		 *           {doSearchFkt}  Javascript Funktion, place somewhere in Template (before rest of the vars)
		 *           {$name.'_title} button with titel $lang_name (if JS) or just $lang_name
		 *           {$name}    content of $id if != 0, or lang('use Button to search for').$lang_name
		 *           {$name.'_nojs}  searchfield + button if we have no JavaScript, else empty
		 *
		 * To use call $template->set_var(getIdSearch( ... ));
		 * the template should look like {doSeachFkt} <tr><td>{XXX_title}</td><td>{XXX}</td><td>{XXX_nojs}</td></tr>   (XXX is content of $name)
		 * In the submitted page the vars $query_XXX and $id_XXX are set according to what is selected, see getAddress as Example
		 */

		function getId($name,$lang_name,$prompt,$id_name,$content='',$note='')
		{
			// echo "<p>getId('$name','$lang_name','$prompt',$id_name,'$content') =";
			$ret['doSearchFkt'] = 
'<script language="JavaScript">'."\n".
" function doSearch(field,ask) {\n".
"  field.value = prompt(ask,'');\n".
"  if (field.value != 'null') {\n".
"   if (field.value.length == 0)\n".
"    field.value = '%';\n".
"   field.form.submit();\n".
"  } else\n".
"   field.value = ''\n".
" }\n".
'</script>';

			$ret[$name.'_title'] = is_array($content) && count($content) ? $lang_name : 
'<script language="JavaScript">'."\n".
" document.writeln('<input type=\"hidden\" name=\"query_$name\" value=\"\">');\n".
" document.writeln('<input type=\"button\" onClick=\"doSearch(this.form.query_$name,\'$prompt\')\" value=\"$lang_name\">');\n".
"</script>\n".
"<noscript>\n".
" $lang_name\n".
"</noscript>";

			if (is_array($content))
			{	// result from search
				if (!count($content))
				{	// search was unsuccsessful
					$ret[$name] = lang('no entries found, try again ...');
				}
				else
				{
					$ret[$name.'_OK'] = '';	// flag we have something so select
					$ret[$name] = "<select name=\"id_$name\">\n";
					while (list( $id,$text ) = each( $content ))
					{
						$ret[$name] .= "<option value=\"$id\">" . $GLOBALS['phpgw']->strip_html($text) . "\n";
					}
					$ret[$name] .= '<option value="0">'.lang('none')."\n";
					$ret[$name] .= '</select>';
				}
			}
			else
			{
				if ($id_name)
				{
					$ret[$name] = $content . "\n<input type=\"hidden\" name=\"id_$name\" value=\"$id_name\">";
				}
				else
				{
					$ret[$name] = "<span class=note>$note</span>";
				}
			}

			$ret[$name.'_nojs'] =
"<noscript>\n".
" <input name=\"query_$name\" value=\"\" size=10> &nbsp;	<input type=\"submit\" value=\"?\">\n".
"</noscript>";

			// print_r($ret);
			return $ret;
		}

		function addr2name( $addr )
		{
			$name = $addr['n_family'];
			if ($addr['n_given'])
			{
				$name .= ', '.$addr['n_given'];
			}
			else
			{
				if ($addr['n_prefix'])
				{
					$name .= ', '.$addr['n_prefix'];
				}
			}
			if ($addr['org_name'])
			{
				$name = $addr['org_name'].': '.$name;
			}
			return $GLOBALS['phpgw']->strip_html($name);
		}

		/*
		 * Function		Allows you to show and select an address from the addressbook (works with and without javascript !!!)
		 * Parameters	$name 		string with basename of all variables (not to conflict with the name other template or submitted vars !!!)
		 *					$id_name		id of the address for edit or 0 if none selected so far
		 *					$query_name have to be called $query_XXX, the search pattern after the submit, has to be passed back to the function
		 * On Submit	$id_XXX		contains the selected address (if != 0)
		 *					$query_XXX	search pattern if the search button is pressed by the user, or '' if regular submit
		 * Returns		array with vars to set for the template, set with: $template->set_var( getAddress( ... )); (see getId( ))
		 *
		 * Note			As query's for an address are submitted, you have to check $query_XXX if it is a search or a regular submit (!$query_string)
		 */
		 
		function getAddress( $name,$id_name,$query_name,$title='')
		{
			// echo "<p>getAddress('$name',$id_name,'$query_name','$title')</p>";
			if ($id_name || $query_name)
			{
				$contacts = createobject('phpgwapi.contacts');

				if ($query_name)
				{
					$addrs = $contacts->read( 0,0,'',$query_name,'','DESC','org_name,n_family,n_given' );
					$content = array( );
					while ($addrs && list( $key,$addr ) = each( $addrs ))
					{
						$content[$addr['id']] = $this->addr2name( $addr );
					}
				}
				else
				{
					list( $addr ) = $contacts->read_single_entry( $id_name );
					if (count($addr))
					{
						$content = $this->addr2name( $addr );
					}
				}
			}
			if (!$title)
			{
				$title = lang('Addressbook');
			}
			
			return $this->getId($name,$title,lang('Pattern for Search in Addressbook'),$id_name,$content,lang('use Button to search for Address'));
		}

		function addr2email( $addr,$home='' )
		{
			if (!is_array($addr))
			{
				$home = substr($addr,-1) == 'h';
				$contacts = createobject('phpgwapi.contacts');
				list( $addr ) = $contacts->read_single_entry( intval($addr) );
			}
			if ($home)
			{
				$home = '_home';
			}

			if (!count($addr) || !$addr['email'.$home])
			{
				return False;
			}

			if ($addr['n_given'])
			{
				$name = $addr['n_given'];
			}
			else
			{
				if ($addr['n_prefix'])
				{
					$name = $addr['n_prefix'];
				}
			}
			$name .= ($name ? ' ' : '') . $addr['n_family'];

			return $name.' <'.$addr['email'.$home].'>';
		}

		function getEmail( $name,$id_name,$query_name,$title='')
		{
			// echo "<p>getAddress('$name',$id_name,'$query_name','$title')</p>";
			if ($id_name || $query_name)
			{
				$contacts = createobject('phpgwapi.contacts');

				if ($query_name)
				{
					$addrs = $contacts->read( 0,0,'',$query_name,'','DESC','org_name,n_family,n_given' );
					$content = array( );
					while ($addrs && list( $key,$addr ) = each( $addrs ))
					{
						if ($addr['email'])
						{
							$content[$addr['id']] = $this->addr2email( $addr );
						}
						if ($addr['email_home'])
						{
							$content[$addr['id'].'h'] = $this->addr2email( $addr,'_home' );
						}
					}
				}
				else
				{
					$content = $this->addr2email( $id_name );
				}
			}
			if (!$title)
			{
				$title = lang('Addressbook');
			}

			return $this->getId($name,$title,lang('Pattern for Search in Addressbook'),$id_name,$content);
		}

		/*
		 * Function		Allows you to show and select an project from the projects-app (works with and without javascript !!!)
		 * Parameters	$name 		string with basename of all variables (not to conflict with the name other template or submitted vars !!!)
		 *					$id_name		id of the project for edit or 0 if none selected so far
		 *					$query_name have to be called $query_XXX, the search pattern after the submit, has to be passed back to the function
		 * On Submit	$id_XXX		contains the selected address (if != 0)
		 *					$query_XXX	search pattern if the search button is pressed by the user, or '' if regular submit
		 * Returns		array with vars to set for the template, set with: $template->set_var( getProject( ... )); (see getId( ))
		 *
		 * Note			As query's for an address are submitted, you have to check $query_XXX if it is a search or a regular submit (!$query_string)
		 */
		function getProject( $name,$id_name,$query_name,$title='' )
		{
			// echo "<p>getProject('$name',$id_name,'$query_name','$title')</p>";
			if ($id_name || $query_name)
			{
				$projects = createobject('projects.projects');
				if ($query_name)
				{
					$projs = $projects->read_projects( 0,0,$query_name,'','','','',0 );
					$content = array();
					while ($projs && list( $key,$proj ) = each( $projs ))
					{
						$content[$proj['id']] = $proj['title'];
					}
				}
				else
				{
					list( $proj ) = $projects->read_single_project( $id_name );
					if (count($proj))
					{
						$content = $proj['title'];
						// $customer_id = $proj['customer'];
					}
				}
			}
			if (!$title)
			{
				$title = lang('Project');
			}

			return $this->getId($name,$title,lang('Pattern for Search in Projects'),$id_name,$content,lang('use Button to search for Project'));
		}

		/*
		 * Function:		Allows to show and select one item from an array
		 *	Parameters:		$name		string with name of the submitted var which holds the key of the selected item form array
		 *						$key		key(s) of already selected item(s) from $arr, eg. '1' or '1,2' or array with keys
		 *						$arr		array with items to select, eg. $arr = array ( 'y' => 'yes','n' => 'no','m' => 'maybe');
		 *						$no_lang	if !$no_lang send items through lang()
		 *						$options	additional options (e.g. 'multiple')
		 * On submit		$XXX		is the key of the selected item (XXX is the content of $name)
		 * Returns:			string to set for a template or to echo into html page
		 */
		function getArrayItem($name, $key, $arr=0,$no_lang=0,$options='',$multiple=0)
		{	// should be in class common.sbox
			if (!is_array($arr))
			{
				$arr = array('no','yes');
			}
			if (0+$multiple > 0)
			{
				$options .= ' MULTIPLE SIZE='.(0+$multiple);
				if (substr($name,-2) != '[]')
					$name .= '[]';
			}
			$out = "<select name=\"$name\" $options>\n";

			if (is_array($key)) $key = implode(',',$key);

			while (list($k,$text) = each($arr))
			{
				$out .= '<option value="'.$k.'"';
				if($k == $key || strstr(",$key,",",$k,")) $out .= " SELECTED";
				$out .= ">" . ($no_lang || $text == '' ? $text : lang($text)) . "</option>\n";
			}
			$out .= "</select>\n";

			return $out;
		}

		function getPercentage($name, $selected=0,$options='')
		{	// reimplemented using getArrayItem
			for ($i=0; $i <= 100; $i+=10)
				$arr[$i] = "$i%";

			return $this->getArrayItem($name,$selected,$arr,1,$options);
		}

		function getPriority($name, $selected=2,$options='')
		{	// reimplemented using getArrayItem
			$arr = array('','low','normal','high');

			return $this->getArrayItem($name,$selected,$arr,0,$options);
		}

		function getAccessList($name,$selected='private',$options='')
		{	// reimplemented using getArrayItem
			$arr = array(
				"private" => "Private",
				"public" => "Global public",
				"group" => "Group public"
			);

			if (strstr($selected,','))
			{
				$selected = "group";
			}

			return $this->getArrayItem($name,$selected,$arr,0,$options);
		}

		function getCountry($name='country',$selected='  ',$options='')
		{	// reimplemented using getArrayItem
			return $this->getArrayItem($name,$selected,$this->country_array,0,$options);
		}

		function form_select($name='country',$selected='  ',$options='')
		{	// reimplemented using getArrayItem (stupid name!!!)
			return getCountry($name,$selected,$options);
		}

		function accountInfo($id,$account_data=0,$longnames=0,$show_type=0)
		{
			if (!$id)
			{
				return '&nbsp;';
			}

			if (!is_array($account_data))
			{
				$accounts = createobject('phpgwapi.accounts',$id);
				$accounts->db = $GLOBALS['phpgw']->db;
				$accounts->read_repository();
				$account_data = $accounts->data;
			}
			$info = $show_type ? '('.$account_data['account_type'].') ' : '';

			switch ($longnames)
			{
				case 2: $info .= '&lt;'.$account_data['account_lid'].'&gt; '; // fall-through
				case 1: $info .= $account_data['account_firstname'].' '.$account_data['account_lastname']; break;
				default: $info .= $account_data['account_lid']; break;
			}
			return $info;
		}

		/*
		 * Function:		Allows to select one accountname
		 *	Parameters:		$name		string with name of the submitted var, which holds the account_id or 0 after submit
		 *						$id		account_id of already selected account
		 *						$longnames 0=account_lid 1=firstname lastname
		 */

		function getAccount($name,$id,$longnames=0,$type='accounts',$multiple=0,$options='')
		{
			$accounts = createobject('phpgwapi.accounts');
			$accounts->db = $GLOBALS['phpgw']->db;
			$accs = $accounts->get_list($type);

			if ($multiple < 0)
				$aarr[] = lang('not assigned');

			while ($a = current($accs))
			{
				$aarr[$a['account_id']] = $this->accountInfo($a['account_id'],$a,$longnames,$type=='both');
				next($accs);
			}
			return $this->getArrayItem($name,$id,$aarr,1,$options,$multiple);
		}

		function getDate($n_year,$n_month,$n_day,$date,$options='')
		{
			if (is_array($date))
				list($year,$month,$day) = $date;
			elseif (!$date)
				$day = $month = $year = 0;
			else
			{
				$day = date('d',$date);
				$month = date('m',$date);
				$year = date('Y',$date);
			}
			return $GLOBALS['phpgw']->common->dateformatorder(
				$this->getYears($n_year,$year),
				$this->getMonthText($n_month,$month),
				$this->getDays($n_day,$day)
			);
		}

		function getCategory($name,$cat_id='',$notall=False,$jscript=True,$multiple=0,$options='')
		{
			if (!is_object($this->cat))
				$this->cat = CreateObject('phpgwapi.categories');

			if ($jscript)
			{
				$options .= ' onChange="this.form.submit();"';
			}
			if (0+$multiple > 0)
			{
				$options .= ' MULTIPLE SIZE='.(0+$multiple);
				if (substr($name,-2) != '[]')
					$name .= '[]';
			}
			/* Setup all and none first */
			$cats_link  = "\n<SELECT NAME=\"$name\" $options>\n";

			if (!$notall)
			{
				$cats_link .= '<option value=""';
				if ($cat_id=='all')
				{
					$cats_link .= ' selected';
				}
				$cats_link .= '>'.lang("all")."</option>\n";
			}

			/* Get global and app-specific category listings */
			$cats_link .= $this->cat->formated_list('select','all',$cat_id,True);
			$cats_link .= '</select>'."\n";
			return $cats_link;
		}

	}
