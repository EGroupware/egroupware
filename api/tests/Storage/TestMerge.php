<?php

namespace Storage;

use EGroupware\Api\Storage\Merge;

/**
 * Concrete Merge class for testing
 */
class TestMerge extends Merge
{

	// NOT named $replacements: Merge.php itself never declares a $replacements class property,
	// it only ever uses one dynamically/transiently (process_commands() does
	// `$this->replacements = $replacements; ... unset($this->replacements);` while resolving
	// $$IF/$$NELF/$$NENVLF/$$LETTERPREFIXCUSTOM$$). A same-named PRIVATE property declared here
	// would shadow that slot and make Merge's own methods unable to write to it ("Cannot access
	// private property TestMerge::$replacements", since a private property is only accessible
	// from ITS declaring class, not a parent class's methods) - discovered because no test
	// exercised those commands until the Api\Storage\Merge test-coverage project added some.
	private $testReplacements = [];

	/**
	 * Per-id replacements, keyed by id - used by tests needing different values per entry
	 * (eg. $$pagerepeat$$ with several ids). Falls back to the single shared $replacements
	 * array (setReplacements()) for any id not present here.
	 *
	 * @var array<int|string,array>
	 */
	private $perIdReplacements = [];

	public function setReplacements(array $replacements)
	{
		$this->testReplacements = $replacements;
	}

	/**
	 * Set distinct replacements for a specific id, for multi-id ($$pagerepeat$$) tests
	 *
	 * @param int|string $id
	 * @param array $replacements
	 */
	public function setReplacementsForId($id, array $replacements)
	{
		$this->perIdReplacements[$id] = $replacements;
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
		return $this->perIdReplacements[$id] ?? $this->testReplacements;
	}
}