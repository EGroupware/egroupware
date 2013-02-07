<?php
/**
 * EGroupware - eTemplate custom filter widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

/**
 * A filter widget that fakes another widget and turns it into a nextmatch filter widget.
 * It's best to not use this, but instead make the appropriate filter widget
 *
 */
class etemplate_widget_nextmatch_customfilter extends etemplate_widget_transformer
{

	protected $legacy_options = 'type,options';

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		self::$transformation['type'] = $this->attrs['type'];
		$form_name = self::form_name($cname, $this->id, $expand);
		$this->setElementAttribute($form_name, 'options', $this->attrs['options']);

		return parent::beforeSendToClient($cname);
	}
}
