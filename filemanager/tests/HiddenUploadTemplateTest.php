<?php
/**
 * EGroupware filemanager: the upload widget must be where the hidden upload share looks for it
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Filemanager;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A share with a hidden upload folder takes over the upload widget on the server
 * (Sharing\HiddenUpload::listview() sets path, conflict strategy and onFinish through
 * setElementAttribute()), so visitors upload into the hidden folder instead of the share root they
 * may not write to ("Permission denied").  setElementAttribute() addresses the widget by its full
 * id and silently does nothing when no widget has that id.
 *
 * The id is a namespace path: a widget inside a container with an id gets that id as a prefix.
 * The desktop template has the upload widget inside the toolbar, so it is "toolbar[upload]".  The
 * mobile template had it next to the toolbar, as "upload", and visitors on a phone uploaded into
 * the share root.
 *
 * No server, session or database needed - this only reads the templates and the PHP source.
 */
class HiddenUploadTemplateTest extends TestCase
{
	private const TEMPLATES = [
		'desktop' => __DIR__.'/../templates/default/index.xet',
		'mobile'  => __DIR__.'/../templates/mobile/index.xet',
	];

	/**
	 * Full id the server addresses the upload widget by, read from the code that does it
	 *
	 * @return string[]
	 */
	private function serverTargetedIds() : array
	{
		$source = file_get_contents(__DIR__.'/../src/Sharing/HiddenUpload.php');
		$this->assertNotFalse($source, 'Could not read Sharing/HiddenUpload.php');
		preg_match_all("/setElementAttribute\(\s*'([^']*upload[^']*)'/", $source, $matches);
		$ids = array_values(array_unique($matches[1]));
		$this->assertNotEmpty($ids, "HiddenUpload::listview() no longer addresses an upload widget");
		return $ids;
	}

	/**
	 * Full ids of the upload widgets of a template: ids of the containers around it, then its own
	 *
	 * Templates (et2-template) group widgets but do not add a namespace.
	 *
	 * @param string $file
	 * @return string[]
	 */
	private function uploadWidgetIds(string $file) : array
	{
		$dom = new \DOMDocument();
		$this->assertTrue($dom->load($file), "Could not parse $file");
		$ids = [];
		foreach((new \DOMXPath($dom))->query('//et2-vfs-upload') as $upload)
		{
			$ids[] = $this->namespacedId($upload);
		}
		return $ids;
	}

	private function namespacedId(\DOMElement $widget) : string
	{
		$path = [];
		for($node = $widget; $node instanceof \DOMElement; $node = $node->parentNode)
		{
			if($node->localName !== 'et2-template' && $node->hasAttribute('id'))
			{
				array_unshift($path, $node->getAttribute('id'));
			}
		}
		$id = array_shift($path);
		return $id . ($path ? '[' . implode('][', $path) . ']' : '');
	}

	#[DataProvider('templateProvider')]
	public function testUploadWidgetHasTheIdTheServerOverrides(string $name, string $file)
	{
		$widget_ids = $this->uploadWidgetIds($file);
		$this->assertNotEmpty($widget_ids, "The $name index template has no upload widget");

		$targeted = $this->serverTargetedIds();
		foreach($widget_ids as $id)
		{
			$this->assertContains($id, $targeted,
				"The $name template's upload widget is '$id', but a hidden upload share overrides " .
				implode(', ', $targeted) . ", so on this template it would upload into the share root");
		}
	}

	public static function templateProvider() : array
	{
		$data = [];
		foreach(self::TEMPLATES as $name => $file)
		{
			$data[$name] = [$name, $file];
		}
		return $data;
	}
}
