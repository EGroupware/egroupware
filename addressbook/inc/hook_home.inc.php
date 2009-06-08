<?php
/**
 * Addressbook - birthday reminder on home-page
 *
 * @package addressbook
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if ($GLOBALS['egw_info']['user']['apps']['addressbook'] &&
	($days = $GLOBALS['egw_info']['user']['preferences']['addressbook']['mainscreen_showbirthdays']))
{
	echo "\n<!-- Birthday info -->\n";

	if (!(int) $days) $days = 1;	// old pref

	$contacts = new addressbook_bo();

	$month_start = date('*-m-*',$contacts->now_su);
	$bdays =& $contacts->search(array('bday' => $month_start),array('id','n_family','n_given','bday'),'n_given,n_family');

	if (($month_end = date('*-m-*',$contacts->now_su+$days*24*3600)) != $month_start)
	{
		if (($bdays2 =& $contacts->search(array('bday' => $month_end),array('id','n_family','n_given','bday'),'n_given,n_family')))
		{
			$bdays = !$bdays ? $bdays2 : array_merge($bdays,$bdays2);
		}
		unset($bdays2);
	}
	unset($month_start); unset($month_end);

	if ($bdays)
	{
		$portalbox = CreateObject('phpgwapi.listbox',
			Array(
				'title'     => lang('Birthdays'),
				'primary'   => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'secondary' => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'tertiary'  => $GLOBALS['egw_info']['theme']['navbar_bg'],
				'width'     => '100%',
				'outerborderwidth' => '0',
				'header_background_image' => $GLOBALS['egw']->common->image($GLOBALS['egw']->common->get_tpl_dir('phpgwapi'),'bg_filler')
			)
		);
		$app_id = $GLOBALS['egw']->applications->name2id('addressbook');
		$GLOBALS['portal_order'][] = $app_id;
		foreach(Array(
			'up'       => Array('url' => '/set_box.php', 'app' => $app_id),
			'down'     => Array('url' => '/set_box.php', 'app' => $app_id),
			'close'    => Array('url' => '/set_box.php', 'app' => $app_id),
			'question' => Array('url' => '/set_box.php', 'app' => $app_id),
			'edit'     => Array('url' => '/set_box.php', 'app' => $app_id)
		) as $key => $contactue)
		{
			$portalbox->set_controls($key,$contactue);
		}
		$portalbox->data = Array();
		for($n = 0; $n <= $days; ++$n)
		{
			$day = date('-m-d',$contacts->now_su+$n*24*3600);
			foreach($bdays as $contact)
			{
				if(substr($contact['bday'],-6) == $day)
				{
					if (!$ab_lang_loaded++) $GLOBALS['egw']->translation->add_app('addressbook');
					switch($n)
					{
						case 0:
							$text = lang("Today is %1's birthday!", $contact['n_given'].' '.$contact['n_family']);
							break;
						case 1:
							$text = lang("Tomorrow is %1's birthday.", $contact['n_given'].' '.$contact['n_family']);
							break;
						default:
							list($y,$m,$d) = explode('-',$contact['bday']);
							$text = lang("In %1 days (%2) is %3's birthday.",$n,
								$GLOBALS['egw']->common->dateformatorder($y,$m,$d,true),
								$contact['n_given'].' '.$contact['n_family']);
							break;
					}
					$portalbox->data[] = array(
						'text' => $text,
						'link' => $GLOBALS['egw']->link('/index.php','menuaction=addressbook.addressbook_ui.view&contact_id=' . $contact['id'])
					);
				}
			}
		}
		if(count($portalbox->data))
		{
			echo $portalbox->draw();
		}
		unset($portalbox);
		unset($days); unset($day);
		unset($n); unset($y); unset($m); unset($d);
	}
	unset($contacts); unset($bdays);

	echo "\n<!-- Birthday info -->\n";
}