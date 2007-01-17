<?php
/**************************************************************************\
* eGroupWare                                                               *
* http://www.egroupware.org                                                *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$GLOBALS['egw_info'] = array(
	'flags' => array(
	'currentapp' => isset($_GET['app']) && $_GET['app'] != 'eGroupWare' ? $_GET['app'] : 'about',
	'disable_Template_class' => True,
	'noheader' => True,
	)
);
include('header.inc.php');

// create the about page
$aboutPage = new about();


class about 
{
	/**
	 * template object
	 */
	var $tpl;

	/**
	 * constructor of class about
	 * sets template and calls list or detail view
	 */
	function about()
	{
		// create template object
		$this->tpl =& CreateObject('phpgwapi.Template', $GLOBALS['egw']->common->get_tpl_dir('phpgwapi'));
		$this->tpl->set_file(array(
	        'phpgw_about'         => 'about.tpl',
	        'phpgw_about_unknown' => 'about_unknown.tpl'
	    ));
		
		$title = isset($GLOBALS['egw_info']['apps'][$_GET['app']]) ? $GLOBALS['egw_info']['apps'][$_GET['app']]['title'] : 'eGroupWare';
	    $GLOBALS['egw_info']['flags']['app_header'] = lang('About %1',$title);
		$GLOBALS['egw']->common->egw_header();
		
		// list or detail view
		$app = isset($_GET['app']) && $_GET['app'] != 'eGroupWare' ? basename($_GET['app']) : 'about';
		if ($app) {
			if (!($included = $GLOBALS['egw']->hooks->single('about',$app))) {
				$detail = $included = file_exists(EGW_INCLUDE_ROOT . "/$app/setup/setup.inc.php");
			}
		} else {
			$detail = false;
		}
		if ($detail) {
			$this->_detailView();
		} else {
			$this->_listView();
		}

		$GLOBALS['egw']->common->egw_footer();
	}



	/**
	 * output of list view
	 */
	function _listView()
	{
		// eGW info
		$this->tpl->set_block('phpgw_about', 'egroupware','egroupware');
		$this->tpl->set_var('phpgw_logo',$GLOBALS['egw']->common->image('phpgwapi','logo.gif'));
        $this->tpl->set_var('phpgw_version',lang('eGroupWare API version %1',$GLOBALS['egw_info']['server']['versions']['phpgwapi']));
        $this->tpl->set_var('phpgw_message',lang('%1eGroupWare%2 is a multi-user, web-based groupware suite written in %3PHP%4.',
               '<a href="http://www.eGroupWare.org" target="_blank">','</a>','<a href="http://www.php.net" target="_blank">','</a>'));
		$this->tpl->pparse('overview', 'egroupware');
		
		// app_list_tablestart
	    $this->tpl->set_block('phpgw_about', 'app_list_tablestart','app_list_tablestart');
		$this->tpl->set_var('phpgw_app_listinfo', 			lang('List of your available applications'));
		$this->tpl->set_var('phpgw_about_th_name', 			lang('name'));
		$this->tpl->set_var('phpgw_about_th_author', 		lang('author'));
		$this->tpl->set_var('phpgw_about_th_maintainer', 	lang('maintainer'));
		$this->tpl->set_var('phpgw_about_th_version', 		lang('version'));
		$this->tpl->set_var('phpgw_about_th_license', 		lang('license'));
		$this->tpl->set_var('phpgw_about_th_details', 		lang('details'));
		$this->tpl->pparse('phpgw_about', 'app_list_tablestart', true);

		// app_list_tablerows
		$this->tpl->set_block('phpgw_about', 'app_list_tablerow', 'app_list_tablerow');
		foreach ($GLOBALS['egw_info']['user']['apps'] as $app => $appinfo) {
			// get additional information about the application
			$info = $this->_getParsedAppInfo($app);

			$this->tpl->set_var('phpgw_about_td_image',			$GLOBALS['egw']->common->image($app,array('navbar','nonav')));
			$this->tpl->set_var('phpgw_about_td_title',			$appinfo['title']);
			$this->tpl->set_var('phpgw_about_td_author',		$info['author']);
			$this->tpl->set_var('phpgw_about_td_maintainer',	$info['maintainer']);
			$this->tpl->set_var('phpgw_about_td_version',		$info['version']);
			$this->tpl->set_var('phpgw_about_td_license',		$info['license']);
			$this->tpl->set_var('phpgw_about_td_details_img',	$GLOBALS['egw']->common->image('phpgwapi','view.png'));
			$this->tpl->set_var('phpgw_about_td_details_url',	$GLOBALS['egw_info']['server']['webserver_url'].'/about.php?app='.$app);
			$this->tpl->pparse('phpgw_about', 'app_list_tablerow', true);
		}

		// app_list_table_stop
		$this->tpl->set_block('phpgw_about', 'app_list_tablestop','app_list_tablestop');
		$this->tpl->pparse('phpgw_about', 'app_list_tablestop');
		
	}



    /**
     * output of detail view
     */
    function _detailView()
    {
        echo '<!-- _detailView -->';
		$app = basename($_GET['app']);
        include(EGW_INCLUDE_ROOT . "/$app/setup/setup.inc.php");
        $info = $setup_info[$app];
        $info['icon'] = $GLOBALS['egw']->common->image($app,array('navbar','nonav'));
        $info['title'] = $GLOBALS['egw_info']['apps'][$app]['title'];
        
		$other_infos = array(
            'author'     => lang('Author'),
            'maintainer' => lang('Maintainer'),
            'version'    => lang('Version'),
            'license'    => lang('License'),
        );
        if($info[icon])
        {
            $icon = $info[icon];
        }
        $s = "<table width='70%' cellpadding='4'>\n";
        if(trim($icon) != "")
        {
            $s.= "<tr>
            <td align='left'><img src='$icon' alt=\"$info[title]\" /></td><td align='left'><h2>$info[title]</h2></td></tr>";
        }
        else
        {
            $s .= "<tr>
            <td align='left'></td><td align='left'><h2>$info[title]</h2></td></tr>";
        }
        if ($info['description'])
        {
            $info['description'] = lang($info['description']);
            $s .= "<tr><td colspan='2' align='left'>$info[description]</td></tr>\n";
            if ($info['note'])
            {
                $info['note'] = lang($info['note']);
                $s .= "<tr><td colspan='2' align='left'><i>$info[note]</i></td></tr>\n";
            }

        }
        foreach ($other_infos as $key => $val)
        {
            if (isset($info[$key]))
            {
                $s .= "<tr><td width='1%' align='left'>$val</td><td>";
                $infos = $info[$key];
                for ($n = 0; is_array($info[$key][$n]) && ($infos = $info[$key][$n]) || !$n; ++$n)
                {
                    if (!is_array($infos) && isset($info[$key.'_email']))
                    {
                        $infos = array('email' => $info[$key.'_email'],'name' => $infos);
                    }
                    elseif(!is_array($infos) && isset($info[$key.'_url']))
                    {
                        $infos = array('url' => $info[$key.'_url'],'name' => $infos);
                    }
                    if (is_array($infos))
                    {
                        if ($infos['email'])
                        {
                            $names = explode('<br>',$infos['name']);
                            $emails = split('@|<br>',$infos['email']);
                            if (count($names) < count($emails)/2)
                            {
                                $names = '';
                            }
                            $infos = '';
                            while (list($user,$domain) = $emails)
                            {
                                if ($infos) $infos .= '<br>';
                                $name = $names ? array_shift($names) : $user;
                                $infos .= "<a href='mailto:$user at $domain'><span onClick=\"document.location='mailto:$user'+'@'+'$domain'; return false;\">$name</span></a>";
                                array_shift($emails); array_shift($emails);
                            }
                        }
                        elseif($infos['url'])
                        {
                            $img = $info[$key.'_img'];
                            if ($img)
                            {
                                $img_url = $GLOBALS['egw']->common->image('phpgwapi',$img);
                                if (!$img_url)
                                {
                                    $img_url = $GLOBALS['egw']->common->image($info['name'],$img);
                                }
                                $infos = '<table border="0"><tr><td style="text-align:center;"><a href="'.$infos['url'].'"><img src="'.$img_url.'" border="0"><br>'.$infos['name'].'</a></td></tr></table>';
                            }
                            else
                            {
                                $infos = '<a href="'.$infos['url'].'">'.$infos['name'].'</a>';
                            }
                        }
                    }
                    $s .= ($n ? '<br>' : '') . $infos;
                }
                $s .= "</td></tr>\n";
            }
        }

        if ($info['extra_untranslated'])
        {
            $s .= "<tr><td colspan='2' align='left'>$info[extra_untranslated]</td></tr>\n";
        }

        $s .= "</table>\n";


		$this->tpl->set_block('phpgw_about', 'application','application');
		$this->tpl->set_var('phpgw_app_about', $s);
        $this->tpl->pparse('phpgw_about', 'application', True);
    }



	/**
	 * parse informations from setup.inc.php file
	 *
	 * @param	string	app	application name
	 * @return	array	html formated informations about author(s), 
	 *					maintainer(s), version, license of the 
	 *					given application 
	 */
	function _getParsedAppInfo($app)
	{
		// define the return array
		$ret = array(
			'author'		=> '',
			'maintainer'	=> '',
			'version'		=> '',
			'license'		=> ''
		);
		
		if (!file_exists(EGW_INCLUDE_ROOT . "/$app/setup/setup.inc.php")) {
			return $ret;
		}
		
		include(EGW_INCLUDE_ROOT . "/$app/setup/setup.inc.php");
		
		$ret['license'] = $setup_info[$app]['license'];
		$ret['version'] = $setup_info[$app]['version'];
		$ret['author'] = $this->_getHtmlPersonalInfo($setup_info, $app, 'author');
		$ret['maintainer'] = $this->_getHtmlPersonalInfo($setup_info, $app, 'maintainer');

		return $ret;
	}



	/**
	 * helper to parse author and maintainer info
	 *
	 * @param	array	setup_info	setup_info[$app] array
	 * @param	string	app			application name
	 * @param	string	f			'author' or 'maintainer'
	 *
	 * @return	string	html formated informations of s
	 */
	function _getHtmlPersonalInfo($setup_info, $app, $f = 'author')
	{
		$authors = array();
        // get the author(s)
        if ($setup_info[$app][$f]) {
            // author is set
            if (!is_array($setup_info[$app][$f])) {
                // author is no array
                $authors[0]['name'] = $setup_info[$app][$f];
                if ($setup_info[$app][$f.'_email']) {
                    $authors[0]['email'] = $setup_info[$app][$f.'_email'];
                }
                if ($setup_info[$app][$f.'_url']) {
                    $authors[0]['url'] = $setup_info[$app][$f.'_url'];
                }

            } else {
                // author is array
                if ($setup_info[$app][$f]['name']) {
                    // only one author
                    $authors[0]['name'] = $setup_info[$app][$f]['name'];
                    if ($setup_info[$app][$f]['email']) {
                        $authors[0]['email'] = $setup_info[$app][$f]['email'];
                    }
                    if ($setup_info[$app][$f]['url']) {
                        $authors[0]['url'] = $setup_info[$app][$f]['url'];
                    }
                } else {
                    // may be more authors
                    foreach ($setup_info[$app][$f] as $number => $values) {
                        if ($setup_info[$app][$f][$number]['name']) {
                            $authors[$number]['name'] = $setup_info[$app][$f][$number]['name'];
                        }
                        if ($setup_info[$app][$f][$number]['email']) {
                            $authors[$number]['email'] = $setup_info[$app][$f][$number]['email'];
                        }
                        if ($setup_info[$app][$f][$number]['url']) {
                            $authors[$number]['url'] = $setup_info[$app][$f][$number]['url'];
                        }
                    }
                }
            }
        }

		// html format authors
		$s = '';
        foreach ($authors as $author) {
            if ($s != '') {
                $s .= '<br />';
            }
            $s .= lang('name').': '.$author['name'];
            if ($author['email']) {
                $s .= '<br />'.lang('email').': <a href="mailto:'.$author['email'].'">'.$author['email'].'</a>';
            }
            if ($author['url']) {
                $s .= '<br />'.lang('url').': <a href="'.$author['url'].'" target="_blank">'.$author['url'].'</a>';
            }
        }
        return $s;
	}
}

?>
