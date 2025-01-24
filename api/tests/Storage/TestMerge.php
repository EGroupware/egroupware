<?php

namespace Storage;

use EGroupware\Api\Storage\Merge;

/**
 * Concrete Merge class for testing
 */
class TestMerge extends Merge
{

	private $replacements = [];

	public function setReplacements(array $replacements)
	{
		$this->replacements = $replacements;
	}

	public function setParseHtmlStyles($parseHtmlStyles)
	{
		$this->parseHtmlStyles = $parseHtmlStyles;
	}

	/**
	 * @inheritDoc
	 */
	protected function get_replacements($id, &$content = null)
	{
		return $this->replacements;
	}
}