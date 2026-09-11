<?php
/**
 * EGroupware Api: mail custom labels / keyword search criteria
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Categories;
use EGroupware\Api\Exception\WrongParameter;
use EGroupware\Api\Mail;

/**
 * Custom mail labels (backed by the "mail" Categories app) and the IMAP keyword search criteria
 * built from them, extracted from Api\Mail (mail_bo).
 *
 * The only state this touches on Api\Mail itself is the public $customLabels/$customLabelsCache
 * statics, kept there rather than moved here since $customLabels is public API a deployment could
 * conceivably set directly (a fallback used when Categories aren't available) - see
 * doc/ai/projects/mail-bo-decoupling.md.
 */
class CustomLabels
{
	/**
	 * Return configured custom mail labels
	 *
	 * Mail categories are used as the persistent configuration. Api\Mail::$customLabels remains
	 * empty by default and is only a fallback for contexts in which categories are unavailable.
	 *
	 * @return array<string,array{name:string,color:string,icon?:string}>
	 */
	public static function getCustomLabels() : array
	{
		if (Mail::$customLabelsCache !== null)
		{
			return Mail::$customLabelsCache;
		}
		try
		{
			$categories = new Categories($GLOBALS['egw_info']['user']['account_id'] ?? '', 'mail');
			$labels = self::categoriesToCustomLabels($categories->return_array(
				'all', 0, false, '', 'ASC', 'name', false, null, -1, '', null
			));
			if ($labels)
			{
				return Mail::$customLabelsCache = $labels;
			}
		}
		catch (\Throwable $e)
		{
			// Categories are not available during some setup / unit-test contexts.
		}
		return Mail::$customLabels;
	}

	/**
	 * Convert Mail categories into custom-label metadata
	 *
	 * Category names are stable UI ids. The optional description is the displayed caption, while
	 * color and icon are stored in category data. Invalid IMAP keyword ids are ignored.
	 *
	 * @param array $categories
	 * @return array<string,array{name:string,color:string,icon?:string}>
	 */
	private static function categoriesToCustomLabels(array $categories) : array
	{
		$labels = [];
		foreach ($categories as $category)
		{
			$id = (string)($category['name'] ?? '');
			if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/Di', $id))
			{
				continue;
			}
			$data = is_array($category['data'] ?? null) ? $category['data'] : [];
			$labels[$id] = [
				'name' => (string)(($category['description'] ?? '') ?: $id),
				'color' => (string)($data['color'] ?? ''),
				'icon' => (string)($data['icon'] ?? ''),
			];
		}
		return $labels;
	}

	/**
	 * Validate a custom-label identifier before using it as an IMAP keyword
	 *
	 * Public because Api\Mail::prepareFlagsArray() (still IMAP-connection-coupled, not part of
	 * this extraction) also needs it.
	 *
	 * @param string $keyword
	 * @return string
	 * @throws WrongParameter
	 */
	public static function validateKeyword($keyword)
	{
		$keyword = is_string($keyword) ? strtolower($keyword) : $keyword;
		if (!is_string($keyword) || !preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $keyword))
		{
			throw new WrongParameter('Invalid IMAP keyword');
		}
		return $keyword;
	}

	/**
	 * Resolve a configured label id case-insensitively
	 *
	 * @param string $keyword
	 * @return string|null exact configured UI id
	 */
	private static function customLabelId($keyword)
	{
		if (!is_string($keyword))
		{
			return null;
		}
		foreach (array_keys(self::getCustomLabels()) as $id)
		{
			if (strcasecmp($id, $keyword) === 0)
			{
				return $id;
			}
		}
		return null;
	}

	/**
	 * Check if an ID is a built-in or configured mail label keyword
	 *
	 * @param string $keyword
	 * @return bool
	 */
	public static function isLabelKeyword($keyword) : bool
	{
		return is_string($keyword) &&
			(preg_match('/^label[1-5]$/Di', $keyword) || self::customLabelId($keyword) !== null);
	}

	/**
	 * Build an explicit positive or negative label search criterion
	 *
	 * @param string $keyword
	 * @param bool $set true to search for the label, false to search for messages without it
	 * @return array{keyword:string,set:bool}
	 * @throws WrongParameter
	 */
	public static function labelSearchCriterion($keyword, $set) : array
	{
		if (!self::isLabelKeyword($keyword))
		{
			throw new WrongParameter('Unknown mail label');
		}
		return [
			'keyword' => self::validateKeyword($keyword),
			'set' => (bool)$set,
		];
	}

	/**
	 * Normalize a label status or explicit label search criterion
	 *
	 * Existing keyword1..5 statuses remain aliases for label1..5.
	 *
	 * @param mixed $criteria
	 * @return array{keyword:string,set:bool}|null
	 * @throws WrongParameter
	 */
	public static function labelSearchFromStatus($criteria) : ?array
	{
		if (is_array($criteria))
		{
			if (!array_key_exists('keyword', $criteria) || !array_key_exists('set', $criteria))
			{
				throw new WrongParameter('Invalid mail label search criterion');
			}
			return self::labelSearchCriterion($criteria['keyword'], $criteria['set']);
		}
		if (!is_string($criteria))
		{
			return null;
		}

		$keyword = strtolower($criteria);
		if (preg_match('/^keyword([1-5])$/D', $keyword, $matches))
		{
			$keyword = 'label'.$matches[1];
		}
		return self::isLabelKeyword($keyword) ?
			self::labelSearchCriterion($keyword, true) : null;
	}
}
