<?php

/**
 * This web page receives requests for web-pages hosted by modules, and directs them to
 * the process() handler in the Module class.
 */

require_once('_include.php');

try {
	\SimpleSAML\Module::process()->send();
}
catch(\SimpleSAML\Error\NoState $e) {
	// fix/hack NOSTATE error caused by EGroupware and therefore SimpleSAMLphp session lost due logout
	if (strpos($_SERVER['PHP_SELF'], '/saml/module.php/saml/sp/saml2-logout.php/default-sp') !== false)
	{
		\EGroupware\Api\Egw::redirect(str_replace('/saml/module.php/saml/sp/saml2-logout.php/default-sp', '/logout.php', $_SERVER['PHP_SELF']));
	}
	throw $e;
}