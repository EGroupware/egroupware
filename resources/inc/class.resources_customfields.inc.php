<?php
/**
 * Resources - Custom fields
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package resources
 * @copyright (c) 2022 by Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Acl;

/**
 * Administration of custom fields, type and status
 *
 * Extending to use categories as type for custom fields
 */
class resources_customfields extends admin_customfields
{
	public $appname = 'resources';
	/**
	 * Instance of the resources BO class
	 *
	 * @var resources_bo
	 */
	var $bo;

	function __construct()
	{
		parent::__construct('resources');

		$this->bo = new resources_bo();
		$this->tmpl = new Etemplate();

		// For index - override so "add" button opens correctly
		$this->tmpl->setElementAttribute("nm", "header_left", "resources.customfields.add");

		$content_types = $this->bo->acl->get_cats(Acl::ADD);
		array_walk($content_types, function ($name, $id)
		{
			$this->content_types[$id] = array('name' => $name);
		});

		Api\Translation::add_app('resources');
	}

	public function edit($content = null)
	{
		// Turn private off
		$_GET['use_private'] = false;
		unset($content['use_private']);

		parent::edit($content);
	}
}
