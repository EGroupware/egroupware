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
			list($contactId) = $parameters;
			if(str_starts_with($contactId, 'account:'))
			{
				$id = 'egw_addressbook.account_id';
				$parsedId = (int)substr($contactId, 8);
			}
			elseif(str_starts_with($contactId, 'email:'))
			{
				$id = 'email';
				preg_match('/<([^<>]+)>$/', $contactId, $matches);
				$parsedId = $matches ? $matches[1] : substr($contactId, 6);
			}
			else
			{
				$id = 'contact_id';
				$parsedId = (int)str_replace('contact:', '', $contactId);
			}

			$matches = $contacts->search([$id => $parsedId], false);
			// Result key matches parameters
			$result[json_encode([$contactId])] = is_array($matches) && count($matches) ? Contacts::hasPhoto($matches[0]) : false;
		}

		Response::get()->data($result);
		return $result;
	}
}

Etemplate\Widget::registerWidget(__NAMESPACE__ . '\\Avatar', array('et2-avatar', 'et2-lavatar'));