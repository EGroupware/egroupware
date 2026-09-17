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

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Etemplate\Widget\Ai;
use EGroupware\AiTools\Prompts;

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

	/**
	 * Ai::enabled()==2 (main provider unconfigured, DeepL still configured) used to set
	 * prompts=["#translate"] - a bare string, not a real {id,label} prompt object, and "#translate"
	 * is never referenced anywhere client-side (Et2Ai.ts). That left the client with a single
	 * broken/blank menu-item: the AI icon still showed, but its dropdown menu was effectively empty -
	 * reported live 2026-09-17 (ralf, boulder.egroupware.org) after disabling the main AI provider
	 * while DeepL stayed configured, for Et2Textarea/Et2Htmlarea alike (both just wrap <et2-ai>).
	 *
	 * Fix: use the real translate-only prompt list (Bo::get_predefined_prompts(false, true) - the
	 * same method/shape the fully-enabled branch's own translate submenu already uses).
	 */
	public function testTranslateOnlyPromptsAreRealPromptObjects()
	{
		$apps =& $GLOBALS['egw_info']['user']['apps'];
		$had_aitools = array_key_exists('aitools', $apps);
		$backup = $apps['aitools'] ?? null;
		$apps['aitools'] = true;	// Ai::enabled() short-circuits to 0 without run-rights

		// force Ai::enabled() to 2 (DeepL-only) without touching real AiTools config/provider
		Api\Cache::setInstance('aitools', 'configured', 2, 7200);

		// get_translation_prompts() needs a real "aiassist.translate" prompt template to build
		// anything from - this container's test DB has none seeded (confirmed: Prompts::prompts()
		// returns empty here), unlike a real instance's default data, so seed one ourselves
		$prompts = new Prompts();
		$this->assertSame(0, $prompts->save([
			'name'  => 'aiassist.translate',
			'label' => 'Translate',
			'text'  => 'Translate the following text to {$lang}: {$text}',
		]), 'Could not create test "aiassist.translate" prompt');
		$prompt_id = $prompts->data['id'];

		try
		{
			Etemplate::clientSideBootstrap('mail.compose');

			$prompts = Widget::setElementAttribute(Ai::GLOBAL_VALS, 'prompts');

			$this->assertIsArray($prompts);
			$this->assertNotEmpty($prompts, 'enabled()==2 must still yield a usable (non-empty) prompt menu');
			$this->assertNotSame(['#translate'], $prompts,
				'"#translate" is a placeholder never resolved by Et2Ai.ts - a broken/empty menu, not a real prompt');
			foreach ($prompts as $prompt)
			{
				$this->assertIsArray($prompt);
				$this->assertArrayHasKey('id', $prompt);
				$this->assertArrayHasKey('label', $prompt);
				$this->assertNotEmpty($prompt['id']);
			}
		}
		finally
		{
			(new Prompts())->delete(['id' => $prompt_id]);
			Api\Cache::unsetInstance('aitools', 'configured');
			if ($had_aitools) $apps['aitools'] = $backup;
			else unset($apps['aitools']);
		}
	}
}
