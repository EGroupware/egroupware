<?php

/**
 * Test for Etemplate::clientSideBootstrap() replicating Widget\Ai::beforeSendToClient()'s global
 * "disable AI tools" check
 *
 * @link http://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

use EGroupware\Api\Etemplate;
use EGroupware\Api\Etemplate\Widget\Ai;

require_once realpath(__DIR__.'/WidgetBaseTest.php');

class ClientSideBootstrapAiTest extends WidgetBaseTest
{
	/**
	 * clientSideBootstrap() deliberately skips the full widget-tree walk exec() does (see its own
	 * docblock) - which is what normally runs every HtmlArea/Textbox's beforeSendToClient(), which
	 * in turn wraps its content in <et2-ai> and runs THAT widget's own beforeSendToClient(), the
	 * one that actually disables the AI UI client-side via a global '~ai~' modification when AI
	 * tools aren't configured/enabled for this user. Found live 2026-09-17 (ralf): a client-side-
	 * only compose popup always showed AI tools regardless of Ai::enabled() - none of that ever ran.
	 */
	public function testUiDisabledWhenAiNotEnabled()
	{
		$apps =& $GLOBALS['egw_info']['user']['apps'];
		$had_aitools = array_key_exists('aitools', $apps);
		$backup = $apps['aitools'] ?? null;
		unset($apps['aitools']);	// Ai::enabled() short-circuits to 0 (NOT enabled) without this

		try
		{
			Etemplate::clientSideBootstrap('mail.compose');

			$this->assertTrue(Widget::setElementAttribute(Ai::GLOBAL_VALS, 'uiDisabled'),
				"clientSideBootstrap() did not disable the AI UI although 'aitools' is not available");
		}
		finally
		{
			if ($had_aitools) $apps['aitools'] = $backup;
		}
	}
}
