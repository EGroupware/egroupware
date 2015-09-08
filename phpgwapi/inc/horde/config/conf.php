<?php
/* CONFIG START. DO NOT CHANGE ANYTHING IN OR AFTER THIS LINE. */
// $Horde: horde/config/conf.xml,v 1.79 2005/02/16 15:42:35 jan Exp $
$conf['debug_level'] = E_ERROR | E_WARNING | E_PARSE;
$conf['max_exec_time'] = 0;
$conf['use_ssl'] = 2;
$conf['server']['name'] = $_SERVER['SERVER_NAME'];
$conf['server']['port'] = $_SERVER['SERVER_PORT'];
$conf['compress_pages'] = true;
$conf['umask'] = 077;
$conf['session']['name'] = 'Horde';
$conf['session']['cache_limiter'] = 'nocache';
$conf['session']['timeout'] = 0;
$conf['cookie']['domain'] = $_SERVER['SERVER_NAME'];
$conf['cookie']['path'] = '/horde';
$conf['auth']['admins'] = array('Administrator');
$conf['auth']['checkip'] = true;
$conf['auth']['params']['username'] = 'Administrator';
$conf['auth']['params']['requestuser'] = false;
$conf['auth']['driver'] = 'auto';
$conf['log']['priority'] = PEAR_LOG_DEBUG;
$conf['log']['ident'] = 'EGWSYNC';
$conf['log']['params'] = array();
$conf['log']['name'] = '/tmp/egroupware_syncml-1.6.log';
$conf['log']['params']['append'] = true;
$conf['log']['type'] = 'error_log';
$conf['log']['enabled'] = false;
$conf['log_accesskeys'] = false;
$conf['prefs']['driver'] = 'none';
$conf['datatree']['params']['driverconfig'] = 'horde';
$conf['datatree']['driver'] = 'sql';
$conf['group']['driver'] = 'datatree';
#$conf['cache']['default_lifetime'] = 1800;
#$conf['cache']['params']['dir'] = Horde::getTempDir();
#$conf['cache']['driver'] = 'file';
$conf['token']['driver'] = 'none';
$conf['sessionhandler']['type'] = 'none';
$conf['problems']['email'] = 'webmaster@example.com';
$conf['hooks']['username'] = false;
$conf['hooks']['preauthenticate'] = false;
$conf['hooks']['postauthenticate'] = false;
$conf['hooks']['authldap'] = false;
$conf['kolab']['enabled'] = false;
/* CONFIG END. DO NOT CHANGE ANYTHING IN OR BEFORE THIS LINE. */
