<?php
/**
 * Module for quick & easy resource reservation
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @author Nathan Gray
 * @copyright (c) 2011 Nathan Gray
 * @version $Id$
*/

/**
 * Needs permission to resources to read the resource, calendar app to properly push everything through
 */
class module_resources_reservation extends sitemgr_module
{
	function __construct()
	{
		parent::__construct();
		$this->title = lang('Reserve');
		$this->description = lang('Simple reservation of a single item');
		$this->etemplate_method = 'resources.resources_reserve.book';
		
		$categories = new categories('', 'resources');
		$cat_list = $categories->return_sorted_array();
		$cat_options = array();
		foreach($cat_list as $category)
		{
			$cat_options[$category['id']] = $category['name'];
		}
		$this->arguments = array(
			'category' => array(
				'type'	=> 'select',
				'label' => lang('Category'),
				'options' => $cat_options
			),
			'resource' => array(
				'type'	=> 'select',
				'label' => lang('Resource'),
				'options' => array(
				)
			),
			'contact_form' => array(
				'type' => 'textfield',
				'label' => lang('Custom eTemplate for the contactform'),
				'params' => array('size' => 40),
			),
			'confirmation' => array(
				'type'	=> 'checkbox',
				'label'	=> lang('Require confirmation'),
			),
			'email_message' => array(
				'type' => 'textarea',
				'large' => true,
				'label' => lang('Confirmation email text').'<br />%1 = ' . lang('Event start').'<br/>%2 = link<br />%3 = '.lang('expiry'),
				'params' => array(
					'rows'	=> 8,
					'cols'	=> 110
				)
			),
			'confirmed_addressbook' => array(
				'type' => 'select',
				'label' => lang('Confirmed addressbook.').' ('.lang('The anonymous user needs add rights for it!').')',
				'options' => array(
					'' => lang('None'),
				)+registration_bo::get_allowed_addressbooks(registration_bo::CONFIRMED)
			),
			'include_group' => array(
				'type' => 'select',
				'label' => lang('Add group to event participants'),
				'options' => array(
					'' => lang('None'),
				) + $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'])
			)
		);
	}

	public function get_user_interface()
	{
		$query = array(
			// Resources uses filter, not cat_id
			'filter' => $this->block->arguments['category'],
			'bookable' => true
		);

		// Add resources from selected category
		$bo = new resources_bo();
		$bo->get_rows($query, $list, $readonlys);
		foreach($list as $resource)
		{
			$this->arguments['resource']['options'][$resource['res_id']] = $resource['name'];
		}
		return parent::get_user_interface();
	}

	/**
         * generate the module content AND process submitted forms
	 * Overridden from parent to pass arguments
         *
         * @param array &$arguments $arguments['arg1']-$arguments['arg3'] will be passed for non-submitted forms (first call)
         * @param array $properties
         * @return string the html content
         */
        function get_content(&$arguments,$properties)
        {
                list($app) = explode('.',$this->etemplate_method);
                $GLOBALS['egw']->translation->add_app($app);

                $extra = "<style type=\"text/css\">\n<!--\n@import url(".$GLOBALS['egw_info']['server']['webserver_url'].
                        "/etemplate/templates/default/app.css);\n";

                if ($app != 'etemplate' && file_exists(EGW_SERVER_ROOT.'/'.$app.'/templates/default/app.css'))
                {
                        $extra .= "@import url(".$GLOBALS['egw_info']['server']['webserver_url'].
                                '/'.$app."/templates/default/app.css);\n";
                }
                $extra .= "-->\n</style>\n";
                $extra .= '<script src="'.$GLOBALS['egw_info']['server']['webserver_url'].'/etemplate/js/etemplate.js" type="text/javascript"></script>'."\n";
                $ret = false;
                if($_POST['etemplate_exec_id'])
                {
                        $ret = ExecMethod('etemplate.etemplate.process_exec');
                }
		if($_GET['date']) $arguments['date'] = strtotime($_GET['date']);
		$arguments['link'] = $this->link();
		$arguments['sitemgr_version'] = $this->block->version;
                return $extra.($ret ? $ret : ExecMethod2($this->etemplate_method,null,$arguments));
        }

}
