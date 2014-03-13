<?php
/**
 * InfoLog - homepage hook
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

if (($showevents = $GLOBALS['egw_info']['user']['preferences']['infolog']['homeShowEvents']))
{
	$save_app = $GLOBALS['egw_info']['flags']['currentapp'];
	$GLOBALS['egw_info']['flags']['currentapp'] = 'infolog';

	$GLOBALS['egw']->translation->add_app('infolog');

	$app_id = $GLOBALS['egw']->applications->name2id('infolog');
	$GLOBALS['portal_order'][] = $app_id;

	$infolog = new infolog_ui();
	$infolog->called_by = 'home';

	if (in_array($showevents,array('1','2'))) $showevents = 'own-open-today';
	$html = $infolog->index(array('nm' => array('filter' => $showevents)),'','',0,False,True);
	$title = lang('InfoLog').' - '.lang($infolog->filters[$showevents]);
	unset($infolog);

	$portalbox =& CreateObject('phpgwapi.listbox',array(
		'title'     => $title,
		'primary'   => $GLOBALS['egw_info']['theme']['navbar_bg'],
		'secondary' => $GLOBALS['egw_info']['theme']['navbar_bg'],
		'tertiary'  => $GLOBALS['egw_info']['theme']['navbar_bg'],
		'width'     => '100%',
		'outerborderwidth' => '0',
		'header_background_image' => $GLOBALS['egw']->common->image('phpgwapi/templates/default','bg_filler')
	));
	foreach(array('up','down','close','question','edit') as $key)
	{
		$portalbox->set_controls($key,Array('url' => '/set_box.php', 'app' => $app_id));
	}
	$portalbox->data = $data;

	if (!file_exists(EGW_SERVER_ROOT.($et_css_file ='/etemplate/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
	{
		$et_css_file = '/etemplate/templates/default/app.css';
	}
	if (!file_exists(EGW_SERVER_ROOT.($css_file ='/infolog/templates/'.$GLOBALS['egw_info']['user']['preferences']['common']['template_set'].'/app.css')))
	{
		$css_file = '/infolog/templates/default/app.css';
	}
	echo '
<!-- BEGIN InfoLog info -->
<style type="text/css">
<!--
	@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$et_css_file.');
	@import url('.$GLOBALS['egw_info']['server']['webserver_url'].$css_file.');
-->
</style>
'.	$portalbox->draw($html)."\n<!-- END InfoLog info -->\n";

	unset($css_file); unset($et_css_file);
	unset($portalbox);
	unset($html);
	$GLOBALS['egw_info']['flags']['currentapp'] = $save_app;
	$GLOBALS['egw_info']['flags']['app_header']= lang($save_app);
}
unset($showevents);
