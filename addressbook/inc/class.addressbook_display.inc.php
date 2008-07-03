<?php
/**
 * Addressbook - Sitemgr display form
 *
 * @link http://www.egroupware.org
 * @author Stefan Becker <stefanBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2008 by Stefan Becker <StefanBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.addressbook_display.inc.php 24099 2008-02-18 16:29:06Z stefanbecker $
 */

/**
 * SiteMgr Display form for the addressbook
 *
 */
class addressbook_display extends addressbook_ui
{
	/**
	 * Shows the Addressbook Entry and stores the submitted data
	 *
	 * @param array $content=null submitted eTemplate content
	 * @param int $addressbook=null int owner-id of addressbook to save contacts too
	 * @param array $fields=null field-names to show
	 * @param string $msg=null message to show after submitting the form
	 * @param string $email=null comma-separated email addresses
	 * @param string $tpl_name=null custom etemplate to use
	 * @param string $subject=null subject for email
	 * @return string html content
	 */
	//
function get_rows(&$query,&$rows,&$readonlys,$id_only=false)
	{
		$query['sitemgr_display'] = ($readonlys['sitemgr_display'] ?$readonlys['sitemgr_display']:'addressbook.display');
		$total = parent::get_rows($query,$rows,$readonlys);
		$query['template'] = $query['sitemgr_display'].'.rows';

		if (is_array($query['fields'])) foreach($query['fields'] as $name)
		{
			$rows['show'][$name]=true;
		}

		return $total;

	}

	function display($content=null,$addressbook=null,$fields=null,$msg=null,$email=null,$tpl_name=null,$subject=null)
	{
		$tpl_name=($tpl_name ? $tpl_name : 'addressbook.display');
		$tpl = new etemplate($tpl_name);

		$content = array(
			'msg' => $msg ? $msg : $_GET['msg'],
		);
		$content['nm1'] = $GLOBALS['egw']->session->appsession(($tpl_name ? $tpl_name : 'index'),'addressbook');
		$readonlys['sitemgr_display']=$tpl_name;
		if (!is_array($content['nm1']))
		{
			$content['nm1'] = array(
				'get_rows'       =>	'addressbook.addressbook_display.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
				'bottom_too'     => false,		// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'never_hide'     => True,		// I  never hide the nextmatch-line if less then maxmatch entrie
				'start'          =>	0,			// IO position in list
				'cat_id'         =>	'',			// IO category, if not 'no_cat' => True
				'no_cat'         =>	'True',
				//	'options-cat_id' => array(lang('none')),
				'search'         =>	'',			// IO search pattern
				'order'          =>	'n_family',	// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'ASC',		// IO direction of the sort: 'ASC' or 'DESC'
			//	'col_filter'     =>	array(),	// IO array of column-name value pairs (optional for the filterheaders)
			//	'filter_label'   =>	lang('Addressbook'),	// I  label for filter    (optional)
				'filter'         =>	$addressbook,	// =All	// IO filter, if not 'no_filter' => True
			//	'filter_no_lang' => True,		// I  set no_lang for filter (=dont translate the options)
				'no_filter'      => True,		// I  disable the 1. filter (params are the same as for filter)
				'no_filter2'     => True,		// I  disable the 2. filter (params are the same as for filter)
			//	'filter2_label'  =>	lang('Distribution lists'),			// IO filter2, if not 'no_filter2' => True
			//	'filter2'        =>	'',			// IO filter2, if not 'no_filter2' => True
			//	'filter2_no_lang'=> True,		// I  set no_lang for filter2 (=dont translate the options)
			//	'filter2_onchange' => "if(this.value=='add') { add_new_list(document.getElementById(form::name('filter')).value); this.value='';} else this.form.submit();",
				'lettersearch'   => true,
				'do_email'       => $do_email,
				'default_cols'   => '!cat_id,contact_created_contact_modified',
				'manual' => $do_email ? ' ' : false,	// space for the manual icon
				'no_columnselection' => True,
				'csv_fields'     => false,
			);

			$content['nm1']['fields'] = $fields;
		}

		return $tpl->exec('addressbook.addressbook_display.display',$content,$sel_options,$readonlys,$preserv);
	}
}
