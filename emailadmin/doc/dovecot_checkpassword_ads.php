#!/usr/bin/php -Cq
<?php
/**
 * EGroupware -checkpasswd for Dovecot and Active Directory
 *
 * Quota is stored with "quota:" prefix in multivalued proxyAddresses attribute.
 * Group-memberships are passed to Dovecot to use them in ACL.
 *
 * Reads descriptor 3 through end of file and then closes descriptor 3.
 * There must be at most 512 bytes of data before end of file.
 *
 * The information supplied on descriptor 3 is a login name terminated by \0, a password terminated by \0,
 * a timestamp terminated by \0, and possibly more data.
 * There are no other restrictions on the form of the login name, password, and timestamp.
 *
 * If the password is unacceptable, checkpassword exits 1. If checkpassword is misused, it may instead exit 2.
 * If user is not found, checkpassword exits 3.
 * If there is a temporary problem checking the password, checkpassword exits 111.
 *
 * If the password is acceptable, checkpassword runs prog. prog consists of one or more arguments.
 *
 * Following enviroment variables are used by Dovecot:
 * - SERVICE: contains eg. imap, pop3 or smtp
 * - TCPLOCALIP and TCPREMOTEIP: Client socket's IP addresses if available
 * Following is document, but does NOT work:
 * - MASTER_USER: If master login is attempted. This means that the password contains the master user's password and the normal username contains the user who master wants to log in as.
 * Found working:
 * - AUTH_LOGIN_USER: If master login is attempted. This means that username/password are from master, AUTH_LOGIN_USER is user master wants to log in as.
 *
 * Following enviroment variables are used on return:
 * - USER: modified user name
 * - HOME: mail_home
 * - EXTRA: userdb extra fields eg. "system_groups_user=... userdb_quota_rule=*:storage=10000"
 *
 * @author rb(at)stylite.de
 * @copyright (c) 2012-13 by rb(at)stylite.de
 * @package emailadmin
 * @link http://wiki2.dovecot.org/AuthDatabase/CheckPassword
 * @link http://cr.yp.to/checkpwd/interface.html
 * @version $Id$
 */

// protect from being called via HTTP
if (php_sapi_name() !== 'cli') die('This is a command line only script!');

// uncomment to write to log-file, otherwise errors go to stderr
//$log = '/var/log/dovecot_checkpassword.log';
//$log_verbose = true;	// error's are always logged, set to true to log auth failures and success too

// ldap server settings
$ldap_uri = 'ldaps://10.7.102.13/';
$ldap_base = 'CN=Users,DC=gruene,DC=intern';
$bind_dn = "CN=Administrator,$ldap_base";
//$bind_dn = "Administrator@gruene.intern";
//$bind_pw = 'secret';
$version = 3;
$use_tls = false;
$search_base = $ldap_base;//'o=%d,dc=egroupware';
$passdb_filter = $userdb_filter = '(&(objectCategory=person)(sAMAccountName=%s))';
// %d for domain and %s for username given by Dovecot is set automatic
$user_attrs = array(
	'%u' => 'samaccountname',	// do NOT remove!
//	'%n' => 'uidnumber',
//	'%h' => 'mailmessagestore',
	'%q' => '{quota:}proxyaddresses',
	'%x' => 'dn',
);
$user_name = '%u';	// '%u@%d';
$user_home = '/var/dovecot/imap/gruene/%u';	//'/var/dovecot/imap/%d/%u';	// mailbox location
$extra = array(
	'userdb_quota_rule' => '*:bytes=%q',
/* only for director
	'proxy' => 'Y',
	'nologin' => 'Y',
	'nopassword' => 'Y',
*/
);
// get host by not set l attribute
/* only for director
$host_filter = 'o=%d';
$host_base = 'dc=egroupware';
$host_attr = 'l';
$host_default = '10.40.8.200';
*/

// to return Dovecot extra system_groups_user
$group_base = $ldap_base;
$group_filter = '(&(objectCategory=group)(member=%x))';
$group_attr = 'cn';
$group_append = '';	//'@%d';

$master_dn = $bind_dn;	//"cn=admin,dc=egroupware";
//$domain_master_dn = "cn=admin,o=%d,dc=egroupware";

ini_set('display_errors',false);
error_reporting(E_ALL & ~E_NOTICE);
if ($log) ini_set('error_log',$log);

if ($_SERVER['argc'] < 2)
{
	fwrite(STDERR,"\nUsage: {$_SERVER['argv'][0]} prog-to-exec\n\n");
	fwrite(STDERR,"To test run:\n");
	fwrite(STDERR,"echo -en 'username\\0000''password\\0000' | {$_SERVER['argv'][0]} env 3<&0 ; echo $?\n");
	fwrite(STDERR,"echo -en 'username\\0000' | AUTHORIZED=1 {$_SERVER['argv'][0]} env 3<&0 ; echo $?\n");
	fwrite(STDERR,"echo -en '(dovecode-admin@domain|dovecot|cyrus)\\0000''master-password\\0000' | AUTH_LOGIN_USER=username {$_SERVER['argv'][0]} env 3<&0 ; echo $?\n\n");
	exit(2);
}

list($username,$password) = explode("\0",file_get_contents('php://fd/3'));
if (isset($_SERVER['AUTH_LOGIN_USER']))
{
	$master = $username;
	$username = $_SERVER['AUTH_LOGIN_USER'];
}
//error_log("dovecot_checkpassword '{$_SERVER['argv'][1]}': username='$username', password='$password', master='$master'");

$ds = ldap_connect($ldap_uri);
if ($version) ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $version);
if ($use_tls) ldap_start_tls($ds);

if (!@ldap_bind($ds, $bind_dn, $bind_pw))
{
	error_log("Can't connect to LDAP server $ldap_uri!");
	exit(111);	// 111 = temporary problem
}
list(,$domain) = explode('@',$username);
if (preg_match('/^(.*)\.imapc$/',$domain,$matches))
{
	$domain = $matches[1];

	$username = explode('.', $username);
	array_pop($username);
	$username = implode('.',$username);

	$user_home = '/var/tmp/imapc-%d/%s';
	$extra = array(
		'userdb_mail' => 'imapc:/var/tmp/imapc-'.$domain.'/'.$username,
		//'userdb_imapc_password' => $password,
		//'userdb_imapc_host' => 'hugo.de',
	);
}

$replace = array(
	'%d' => $domain,
	'%s' => $username,
);
$base = strtr($search_base, $replace);

if (($passdb_query = !isset($_SERVER['AUTHORIZED']) || $_SERVER['AUTHORIZED'] != 1))
{
	$filter = $passdb_filter;

	// authenticate with master user/password
	// master user name is hardcoded "dovecot", "cyrus" or "dovecot-admin@domain" and mapped currently to cn=admin,[o=domain,]dc=egroupware
	if (isset($master))
	{
		list($n,$d) = explode('@', $master);
		if (!($n === 'dovecot-admin' && $d === $domain || in_array($master,array('dovecot','cyrus'))))
		{
			// no valid master-user for given domain
			exit(1);
		}
		$dn = $d ? strtr($domain_master_dn,array('%d'=>$domain)) : $master_dn;
		if (!@ldap_bind($ds, $dn, $password))
		{
			if ($log_verbose) error_log("Can't bind as '$dn' with password '$password'! Authentication as master '$master' for user '$username' failed!");
			exit(111);	// 111 = temporary problem
		}
		if ($log_verbose) error_log("Authentication as master '$master' for user '$username' succeeded!");
		$passdb_query = false;
		$filter = $userdb_filter;
	}
}
else
{
	$filter = $userdb_filter;
	putenv('AUTHORIZED=2');
}
$filter = strtr($filter, quote($replace));

// remove prefixes eg. "{quota:}proxyaddresses"
$attrs = $user_attrs;
foreach($attrs as &$a) if ($a[0] == '{') list(,$a) = explode('}', $a);

if (!($sr = ldap_search($ds, $base, $filter, array_values($attrs))))
{
	error_log("Error ldap_search(\$ds, '$base', '$filter')!");
	exit(111);	// 111 = temporary problem
}
$entries = ldap_get_entries($ds, $sr);

if (!$entries['count'])
{
	if ($log_verbose) error_log("User '$username' NOT found!");
	exit(3);
}

if ($entries['count'] > 1)
{
	// should not happen for passdb, but could happen for aliases ...
	error_log("Error ldap_search(\$ds, '$base', '$filter') returned more then one user!");
	exit(111);	// 111 = temporary problem
}
//print_r($entries);

if ($passdb_query)
{
	// now authenticate user by trying to bind to found dn with given password
	if (!@ldap_bind($ds, $entries[0]['dn'], $password))
	{
		if ($log_verbose) error_log("Can't bind as '{$entries[0]['dn']}' with password '$password'! Authentication for user '$username' failed!");
		exit(1);
	}
	if ($log_verbose) error_log("Successfull authentication user '$username' dn='{$entries[0]['dn']}'.");
}
else	// user-db query, no authentication
{
	if ($log_verbose) error_log("User-db query for user '$username' dn='{$entries[0]['dn']}'.");
}

// add additional placeholders from $user_attrs
foreach($user_attrs as $placeholder => $attr)
{
	if ($attr[0] == '{')	// prefix given --> ignore all values without and remove it
	{
		list($prefix, $attr) = explode('}', substr($attr, 1));
		foreach($entries[0][$attr] as $key => $value)
		{
			if ($key === 'count') continue;
			if (strpos($value, $prefix) !== 0) continue;
			$replace[$placeholder] = substr($value, strlen($prefix));
			break;
		}
	}
	else
	{
		$replace[$placeholder] = is_array($entries[0][$attr]) ? $entries[0][$attr][0] : $entries[0][$attr];
	}
}

// search memberships
if (isset($group_base) && $group_filter && $group_attr)
{
	$base = strtr($group_base, $replace);
	$filter = strtr($group_filter, quote($replace));
	$append = strtr($group_append, $replace);
	if (($sr = ldap_search($ds, $base, $filter, array($group_attr))) &&
		($groups = ldap_get_entries($ds, $sr)) && $groups['count'])
	{
		//print_r($groups);
		$system_groups_user = array();
		foreach($groups as $key => $group)
		{
			if ($key === 'count') continue;
			$system_groups_user[] = $group[$group_attr][0].$append;
		}
		$extra['system_groups_user'] = implode(',', $system_groups_user);	// todo: check separator
	}
	else
	{
		error_log("Error searching for memberships ldap_search(\$ds, '$base', '$filter')!");
	}
}

// set host attribute for director to old imap
if (isset($host_base) && isset($host_filter))
{
	if (!($sr = ldap_search($ds, $host_base, $filter=strtr($host_filter, quote($replace)), array($host_attr))))
	{
		error_log("Error ldap_search(\$ds, '$host_base', '$filter')!");
		exit(111);	// 111 = temporary problem
	}
	$entries = ldap_get_entries($ds, $sr);
	if ($entries['count'] && !isset($entries[0][$host_attr]))
	{
		$extra['host'] = $host_default;
	}
}
// close ldap connection
ldap_unbind($ds);

// build command to run
array_shift($_SERVER['argv']);
$cmd = array_shift($_SERVER['argv']);
foreach($_SERVER['argv'] as $arg)
{
	$cmd .= ' '.escapeshellarg($arg);
}

// setting USER, HOME, EXTRA
putenv('USER='.strtr($user_name, $replace));
if ($user_home) putenv('HOME='.strtr($user_home, $replace));
if ($extra)
{
	foreach($extra as $name => $value)
	{
		if (($pos = strpos($value,'%')) !== false)
		{
			// check if replacement is set, otherwise skip whole extra-value
			if (!isset($replace[substr($value,$pos,2)]))
			{
				unset($extra[$name]);
				continue;
			}
			$value = strtr($value,$replace);
		}
		putenv($name.'='.$value);
	}
	putenv('EXTRA='.implode(' ', array_keys($extra)));
}

// call given command and exit with it's exit-status
passthru($cmd, $ret);

exit($ret);

/**
 * escapes a string for use in searchfilters meant for ldap_search.
 *
 * Escaped Characters are: '*', '(', ')', ' ', '\', NUL
 * It's actually a PHP-Bug, that we have to escape space.
 * For all other Characters, refer to RFC2254.
 *
 * @param string|array $string either a string to be escaped, or an array of values to be escaped
 * @return string
 */
function quote($string)
{
	return str_replace(array('\\','*','(',')','\0',' '),array('\\\\','\*','\(','\)','\\0','\20'),$string);
}
