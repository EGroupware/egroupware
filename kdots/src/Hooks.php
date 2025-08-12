<?php

namespace EGroupware\Kdots;
class Hooks
{
	public static function common_preferences()
	{
		$keep_nm = [
			"none"    => "Unchanged",
			"replace" => "Clean"
		];
		$preferences = array(
			'prefssection'   => array(
				'type'    => 'section',
				'title'   => lang('Preferences for the %1 template set', $GLOBALS['egw_info']['apps']['kdots']['title']),
				'no_lang' => true,
				'xmlrpc'  => False,
				'admin'   => False,
			),
			'keep_nm_header' => [
				'type'    => 'select',
				'label'   => 'Entry list headers',
				'name'    => 'keep_nm_header',
				'values'  => $keep_nm,
				'help'    => '',
				'xmlrpc'  => false,
				'admin'   => false,
				'default' => 'replace',
			]
		);

		return $preferences;
	}
}