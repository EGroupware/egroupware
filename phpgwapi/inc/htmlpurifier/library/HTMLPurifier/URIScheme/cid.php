<?php

/**
 * Validates cid (internal images in emails)
 * @todo Filter allowed query parameters
 */

class HTMLPurifier_URIScheme_cid extends HTMLPurifier_URIScheme {

    public $browsable = true;
    // this is actually irrelevant since we only write out the path
    // component
    public $may_omit_host = true;

    public function doValidate(&$uri, $config, $context) {
        //error_log(__METHOD__." calledi with:".print_r($uri,true));
        //parent::validate($uri, $config, $context);
        $uri->userinfo = null;
        $uri->host     = null;
        $uri->port     = null;
        $uri->query    = null;
        if (!empty($uri->path)) return true;
        return false;
    }

}
HTMLPurifier_URISchemeRegistry::instance()->register('cid', new HTMLPurifier_URIScheme_cid());
// vim: et sw=4 sts=4
