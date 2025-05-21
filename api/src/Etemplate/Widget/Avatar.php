<?php


namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Contacts;
use EGroupware\Api\Etemplate;
use EGroupware\Api\Json\Response;

/**
 * eTemplate Avatar widget
 */
class Avatar extends Etemplate\Widget
{
	/**
	 * Constructor
	 *
	 * @param string $xml
	 */
	public function __construct($xml = '')
	{
		if($xml)
		{
			parent::__construct($xml);
		}
	}

	/**
	 * Checks images via AJAX for given contact IDs.
	 *
	 * contactIds is actually an array of parameters, the first of which is the contact ID.
	 *
	 * @param array $contactIds Array of parameters (contact IDs) to process, one per Avatar
	 * @return array An array containing the results of the image check
	 */
	public function ajax_image_check(array $contactIds) : array
	{
		$result = [];
		$contacts = new Contacts();
		foreach($contactIds as $parameters)
		{
			[$type, $parsedId] = explode(':', $contactId=current($parameters))+[null, null];
			switch($type)
			{
				case 'account':
				case 'account_id':
					$filter = ['egw_addressbook.account_id' => (int)$parsedId];
					break;

				case 'email':
					if (preg_match('/<([^<>]+)>$/', $parsedId, $matches))
					{
						$parsedId = $matches[1];
					}
					$filter = ['email' => $parsedId, 'email_home' => $parsedId];
					break;

				case 'contact':
				default:
					if ($type !== 'contact') $parsedId = $type;
					$filter = ['contact_id' => (int)$parsedId];
					break;
			}

			$matches = empty($parsedId) ? null : $contacts->search($filter,
				['contact_id', 'email', 'email_home', 'n_fn', 'n_given', 'n_family', 'contact_files', 'etag'],
				'contact_files & ' . Contacts::FILES_BIT_PHOTO . ' DESC',
				!empty($GLOBALS['egw_info']['user']['preferences']['common']['avatar_display']) ? ['account_lid'] : [],
				'', false, 'OR', [0, 1]);
			// Result key matches parameters
			$result[json_encode([$contactId])] = is_array($matches) && count($matches) ? Contacts::hasPhoto($matches[0]) : false;
		}

		Response::get()->data($result);
		return $result;
	}
}

Etemplate\Widget::registerWidget(__NAMESPACE__ . '\\Avatar', array('et2-avatar', 'et2-lavatar'));