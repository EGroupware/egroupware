<?php
        $setup_info['et_media']['name']      = 'et_media';
        $setup_info['et_media']['title']     = 'eT-Media';
        $setup_info['et_media']['version']   = '0.9.15.001';
        $setup_info['et_media']['app_order'] = 100;     // at the end
        $setup_info['et_media']['tables']    = array('phpgw_et_media');
        $setup_info['et_media']['enable']    = 1;

        /* Dependencies for this app to work */
        $setup_info['et_media']['depends'][] = array(
                 'appname' => 'phpgwapi',
                 'versions' => Array('0.9.13','0.9.14','0.9.15')
        );
        $setup_info['et_media']['depends'][] = array(   // this is only necessary as long the etemplate-class is not in the api
                 'appname' => 'etemplate',
                 'versions' => Array('0.9.13','0.9.14','0.9.15')
        );
