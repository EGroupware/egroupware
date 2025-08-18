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
		$popups_options = [
			'popup_window' => 'Popup Window',
			'same_window'  => 'Same Window'
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
			],
			'open_popups_in' => [
				'type'   => 'select',
				'label'  => 'Open popups in',
				'name'   => 'open_popups_in',
				'values' => $popups_options
			]
		);

		return $preferences;
	}
}