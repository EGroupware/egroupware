<?php
/**
 * EGroupware - Home - A simple portlet for displaying an entry
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @subpackage portlet
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Vfs;
use EGroupware\Api\Etemplate;

 /**
  * A single entry is displayed with its application icon and title
  */
class home_link_portlet extends home_portlet
{

	/**
	 * Context for this portlet
	 */
	protected $context = array();

	/**
	 * Title of entry
	 */
	protected $title = 'Link';

	/**
	 * Image shown.  Leave at false to have it automatically set an icon based
	 * on the entry or customize it for the context.
	 */
	protected $image = false;

	/**
	 * Base name for template
	 * @var string
	 */
	protected $template_name = 'home.link';

	/**
	 * Construct the portlet
	 *
	 * @param boolean $need_reload Flag to indicate that the portlet needs to be reloaded (exec will be called)
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		// Process dropped data into something useable
		if($context['dropped_data'])
		{
			list($context['entry']['app'], $context['entry']['id']) = explode('::', $context['dropped_data'][0], 2);
			unset($context['dropped_data']);
			$need_reload = true;
		}
		if($context['entry'] && is_array($context['entry']))
		{
			$this->title = $context['entry']['title'] = Link::title($context['entry']['app'], $context['entry']['id']);

			// Reload to get the latest title
			// TODO: This is a performance hit, it would be good to do this less
			$need_reload |= (boolean)$context['entry']['id'];
		}
		$this->context = $context;
	}

	/**
	 * Some descriptive information about the portlet, so that users can decide if
	 * they want it or not, and for inclusion in lists, hover text, etc.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @return Array with keys
	 * - displayName: Used in lists
	 * - title: Put in the portlet header
	 * - description: A short description of what this portlet does or displays
	 */
	public function get_description()
	{
		return array(
			'displayName'=> 'Single Entry',
			'title'=>	$this->context['entry'] ? lang($this->context['entry']['app']) : lang('None'),
			'description'=>	lang('Show one entry')
		);
	}

	/**
	 * Generate display
	 *
	 * @param id String unique ID, provided to the portlet so it can make sure content is
	 * 	unique, if needed.
	 */
	public function exec($id = null, Etemplate &$etemplate = null)
	{
		// Check for custom template for app
		$custom_template = false;
		if($this->context && $this->context['entry'] && $this->context['entry']['app'] &&
			$etemplate->read($this->context['entry']['app'] . '.' . $this->template_name))
		{
			// No action needed, custom template loaded as side-effect
			$custom_template = true;
		}
		else
		{
			$etemplate->read($this->template_name);
		}


		$etemplate->set_dom_id($id);

		$content = array(
			'image'	=>	$this->image
		);

		// Try to load entry
		if($this->context['entry'] && $this->context['entry']['app'])
		{

			// Always load app's css
			Framework::includeCSS($this->context['entry']['app'], 'app-'.$GLOBALS['egw_info']['user']['preferences']['common']['theme']) ||
				Framework::includeCSS($this->context['entry']['app'],'app');

			try
			{
				$classname = $this->context['entry']['app'] . '_egw_record';
				if(class_exists($classname))
				{
					$record = new $classname($this->context['entry']['id']);
					if($record && $record->get_record_array())
					{
						// If there's a custom template, send the full record
						if($custom_template)
						{
							$content += $record->get_record_array();
						}
						// Use calendar hover for calendar
						if($this->context['entry']['app'] == 'calendar')
						{
							$etemplate->setElementAttribute('tooltip','class', 'tooltip calendar_uitooltip');
						}
						if($content['image'] == false)
						{
							$content['image'] = $record->get_icon();
						}
					}
				}
			}
			catch(Exception $e)
			{
				error_log("Problem loading record " . array2string($this->context['entry']));
				throw $e;
			}

			// Set a fallback image
			if($content['image'] == false)
			{
				if($this->context['entry'] && $this->context['entry']['app'])
				{
					$content['image'] = $this->context['entry']['app'] . '/navbar';
				}
				else
				{
					$content['image'] = 'home';
				}
			}
		}

		// Filemanager support - links need app = 'file' and type set
		if($this->context && $this->context['entry'] && $this->context['entry']['app'] == 'filemanager')
		{
			$this->context['entry']['app'] = 'file';
			$this->context['entry']['path'] = $this->context['entry']['title'] = $this->context['entry']['id'];
			$this->context['entry']['type'] = Vfs::mime_content_type($this->context['entry']['id']);
			$content['image'] = Framework::link('/api/thumbnail.php',array('path' => $this->context['entry']['id']));
		}

		$content += $this->context;

		if(!is_array($content['entry']))
		{
			$content['entry'] = null;
		}

		$etemplate->exec('home.home_link_portlet.exec',$content);
	}

	/**
	 * Return a list of allowable actions for the portlet.
	 *
	 * These actions will be merged with the default porlet actions.
	 * We add an 'edit' action as default so double-clicking the widget
	 * opens the entry
	 */
	public function get_actions()
	{
		$actions = array(
			'view' => array(
				'icon' => 'view',
				'caption' => lang('open'),
				'default' => true,
				'hideOnDisabled' => false,
				'onExecute' => 'javaScript:app.home.open_link',
			),
			'edit_settings' => array(
				'default' => false
			)
		);
		$actions['view']['enabled'] = (bool)$this->context['entry'];

		return $actions;
	}

	/**
	 * This portlet accepts files and links
	 *
	 * @return boolean|String[]
	 */
	public function accept_drop()
	{
		return array('file', 'link');
	}

	public function get_type()
	{
		return "et2-portlet-link";
	}
}
