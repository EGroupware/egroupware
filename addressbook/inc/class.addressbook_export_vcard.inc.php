<?php
/**
 * vCard export plugin for importexport framework
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package addressbook
 * @subpackage importexport
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

/**
 * export addressbook contacts as vcard
 */
class addressbook_export_vcard implements importexport_iface_export_plugin {


	/**
	 * Exports records as defined in $_definition
	 *
	 * @param egw_record $_definition
	 */
	public function export( $_stream, importexport_definition $_definition) {

		$options = $_definition->plugin_options;
		$this->uicontacts = new addressbook_ui();
		$this->selection = array();

		// Addressbook defines its own export imits
		$limit_exception = bo_merge::is_export_limit_excepted();
		$export_limit = bo_merge::getExportLimit($app='addressbook');
		if (!$limit_exception) $export_object->export_limit = $export_limit; // we may not need that after all
		if($export_limit == 'no' && !$limit_exception) {
			return;
		}

		// Need to switch the app to get the same results
		$old_app = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw_info']['flags']['currentapp'] = 'addressbook';

		if ($options['selection'] == 'search') {
			// uicontacts selection with checkbox 'use_all'
			$query = $GLOBALS['egw']->session->appsession('index','addressbook');
			$query['num_rows'] = -1;	// all
			$query['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
			if(!array_key_exists('filter',$query)) $query['filter'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->uicontacts->get_rows($query,$this->selection,$readonlys, true);	// only return the ids
		}
		elseif ( $options['selection'] == 'all' ) {
			if ($GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts']) {
				$col_filter['account_id'] = null;
			}
			$this->selection = ExecMethod2('addressbook.addressbook_bo.search', array(), true, '', '','',false,'AND',false,$col_filter);
			//$this->uicontacts->get_rows($query,$this->selection,$readonlys,true);
		} else {
			$this->selection = explode(',',$options['selection']);
		}
		$GLOBALS['egw_info']['flags']['currentapp'] = $old_app;

		if(bo_merge::hasExportLimit($export_limit) && !$limit_exception) {
			$this->selection = array_slice($this->selection, 0, $export_limit);
		}

		foreach ($this->selection as &$_contact) {
			if(is_array($_contact) && ($_contact['id'] || $_contact['contact_id']))
			{
				$_contact = $_contact[$_contact['id'] ? 'id' : 'contact_id'];
			}
		}
		
		// vCard opens & closes the resource itself, but this doesn't seem to matter
		$meta = stream_get_meta_data($_stream);

		$vcard = new addressbook_vcal('addressbook','text/vcard');
		$vcard->export($this->selection, $meta['uri']);
	}

	/**
	 * returns translated name of plugin
	 *
	 * @return string name
	 */
	public static function get_name() {
		return lang('Addressbook vCard export');
	}

	/**
	 * returns translated (user) description of plugin
	 *
	 * @return string descriprion
	 */
	public static function get_description() {
		return lang("Exports contacts from your Addressbook into a vCard File.");
	}

	/**
	 * retruns file suffix for exported file
	 *
	 * @return string suffix
	 */
	public static function get_filesuffix() {
		return 'vcf';
	}

	public static function get_mimetype() {
		return 'text/x-vcard';
	}

	/**
	 * Suggest a file name for the downloaded file
	 * No suffix
	 */
	public function get_filename()
	{
		if(is_array($this->selection) && count($this->selection) == 1)
		{
			return $this->uicontacts->link_title($this->selection[0]);
		}
		return false;
	}

	/**
	 * return html for options.
	 * this way the plugin has all opertunities for options tab
	 *
	 * @return string html
	 */
	public function get_options_etpl() {
	}

	/**
	 * returns slectors of this plugin via xajax
	 *
	 */
	public function get_selectors_etpl() {
		return array(
			'name'		=> 'addressbook.export_vcard_selectors',
			'content'	=> 'all',
		);
	}
}
