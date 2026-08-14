<?php
/**
 * EGroupware Api: mail folder-name/list pure helpers
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Mail;

/**
 * Pure folder-name decoding and folder-array search helpers, extracted from Api\Mail (mail_bo).
 * None of these touch an IMAP connection - they only transform strings or search an
 * already-fetched folder array, which is what makes them unit-testable on their own.
 *
 * The `uasort()` comparators that used to live here as named methods (sortByMailbox,
 * sortByDisplayName, sortByAutoFolderPos, sortByAutoFolder) are inline closures at their call
 * sites in Api\Mail instead - PHP resolves a string/array callable's method name
 * case-insensitively, which silently masked a typo ('sortByAutofolder', lowercase f) for who
 * knows how long; a closure has no name to typo. See doc/ai/projects/mail-bo-decoupling.md.
 *
 * Api\Mail::encodeFolderName() was NOT extracted here - a repo-wide search found zero callers
 * anywhere, so it was dead code and got removed instead of moved.
 */
class FolderHelpers
{
	/**
	 * Remove HTML entities from a folder name
	 *
	 * @param string $_folderName
	 * @return string
	 */
	public static function decodeEntityFolderName($_folderName)
	{
		return html_entity_decode($_folderName, ENT_QUOTES, Mail::$displayCharset);
	}

	/**
	 * Helper function to search for a specific value within the foldertree objects
	 *
	 * @param string $needle
	 * @param array $haystack array of folderobjects
	 * @return string|false the key the value was found under, or false
	 */
	public static function searchValueInFolderObjects($needle, $haystack)
	{
		foreach ($haystack as $k => $v)
		{
			foreach ($v as $sv)
			{
				if (trim($sv) == trim($needle))
				{
					return $k;
				}
			}
		}
		return false;
	}

	/**
	 * Get folder data from path
	 *
	 * @param string $_path a node path
	 * @param string $_hDelimiter hierarchy delimiter
	 * @return array returns an array of data extracted from given node path
	 */
	public static function pathToFolderData($_path, $_hDelimiter)
	{
		if (!strpos($_path, Mail::DELIMITER))
		{
			$_path = Mail::DELIMITER.$_path;
		}
		list(, $path) = explode(Mail::DELIMITER, $_path);
		$path_chain = $parts = explode($_hDelimiter, $path);
		$name = array_pop($parts);
		return [
			'name' => $name,
			'mailbox' => $path,
			'parent' => implode($_hDelimiter, $parts),
			'text' => $name,
			'tooltip' => $name,
			'path' => $path_chain,
		];
	}
}
