<?php

/**
 * Collabeditor Hooks Class
 *
 * @link http://www.egroupware.org
 * @package collabeditor
 * @author Hadi Nategh <hn-AT-egroupware.de>
 * @copyright (c) 2016 by Hadi Nategh <hn-AT-egroupware.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
namespace EGroupware\Collabeditor;

/**
 * Description of Hooks
 *
 * @author hadi
 */
class Hooks {

	/**
	 * Gets links for open handler of collabeditor supported mime types
	 *
	 * @return array
	 */
	public static function getEditorLink()
	{
		return array (
			'edit' => array(
				'menuaction' => 'collabeditor.EGroupware\\collabeditor\\Ui.editor',
			),
			'edit_popup' => '980x750',
			'mime' => array (
				'application/vnd.oasis.opendocument.text' => array (
					'mime_popup' => '' // try to avoid mime_open exception
				),
			)
		);
	}
}
