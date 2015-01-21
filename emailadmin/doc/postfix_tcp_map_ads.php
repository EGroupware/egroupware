#!/usr/bin/php -Cq
<?php
/**
 * EGroupware - tcp-map for Postfix and Active Directory
 *
 * Using multivalued proxyAddresses attribute as implemented in emailadmin_smtp_ads:
 * - "smtp:<email>" allows to receive mail for given <email>
 *   (includes aliases AND primary email)
 * - "forward:<email>" forwards received mail to given <email>
 *   (requires account to have at an "smtp:<email>" value!)
 * - ("forwardOnly" is used for no local mailbox, only forwards, not implemented!)
 * - ("quota:<quota>" is used to store quota)
 *
 * Groups can be used as distribution lists by assigning them an
 * email address via there mail attribute (no proxyAddress)
 *
 * PROTOCOL DESCRIPTION
 * The TCP map class implements a very simple  protocol:  the
 * client  sends  a  request, and the server sends one reply.
 * Requests and replies are sent as one line of  ASCII  text,
 * terminated  by  the  ASCII  newline character. Request and
 * reply parameters (see below) are separated by  whitespace.
 *
 * REQUEST FORMAT
 * Each request specifies a command, a lookup key, and possi-
 * bly a lookup result.
 *
 * get SPACE key NEWLINE
 *     Look up data under the specified key.
 *
 * put SPACE key SPACE value NEWLINE
 *     This request is currently not implemented.
 *
 * REPLY FORMAT
 * Each  reply specifies a status code and text. Replies must
 * be no longer than 4096 characters  including  the  newline
 * terminator.
 *
 * 500 SPACE text NEWLINE
 *     In  case  of  a  lookup request, the requested data
 *     does not exist.  In case of an update request,  the
 *     request  was  rejected.   The  text  describes  the
 *     nature of the problem.
 *
 * 400 SPACE text NEWLINE
 *     This  indicates  an  error  condition.   The   text
 *     describes  the  nature  of  the problem. The client
 *     should retry the request later.
 *
 * 200 SPACE text NEWLINE
 *     The request was successful. In the case of a lookup
 *     request,  the  text  contains an encoded version of
 *     the requested data.
 *
 * ENCODING
 * In request and reply parameters,  the  character  %,  each
 * non-printing character, and each whitespace character must
 * be replaced by %XX, where XX is  the  corresponding  ASCII
 * hexadecimal  character value. The hexadecimal codes can be
 * specified in any case (upper, lower, mixed).
 *
 * The Postfix client always encodes a request.   The  server
 * may  omit  the encoding as long as the reply is guaranteed
 * to not contain the % or NEWLINE character.
 *
 * @author rb(at)stylite.de
 * @copyright (c) 2012-13 by rb(at)stylite.de
 * @package emailadmin
 * @link http://www.postfix.org/tcp_table.5.html
 * @version $Id$
 */

// protect from being called via HTTP
if (php_sapi_name() !== 'cli') die('This is a command line only script!');

// our defaults
$default_host = 'localhost';
$verbose = false;

// allow only clients matching that preg to access, should be only mserver IP
//$only_client = '/^10\.40\.8\.210:/';

// uncomment to write to log-file, otherwise errors go to stderr
//$log = 'syslog';	// or not set (stderr) or filename '/var/log/postfix_tcp_map.log';
//$log_verbose = true;	// error's are always logged, set to true to log failures and success too

// ldap server settings
$ldap_uri = 'ldaps://10.7.102.13/';
$base = 'CN=Users,DC=gruene,DC=intern';
//$bind_dn = "CN=Administrator,$base";
//$bind_dn = "Administrator@gruene.intern";
//$bind_pw = 'secret';
$version = 3;
$use_tls = false;
// supported maps
$maps = array(
	// virtual mailbox map
	'mailboxes' => array(
		'base' => $base,
		'filter' => '(&(objectCategory=person)(proxyAddresses=smtp:%s))',
		'attrs' => 'samaccountname',	// result-attrs must be lowercase!
		'port' => 2001,
	),
	// virtual alias maps
	'aliases' => array(
		'base' => $base,
		'filter' => '(&(objectCategory=person)(proxyAddresses=smtp:%s))',
		'attrs' => array('samaccountname','{forward:}proxyaddresses'),
		'port' => 2002,
	),
	// groups as distribution list
	'groups' => array(
		'base' => $base,
		'filter' => '(&(objectCategory=group)(mail=%s))',
		'attrs' => 'dn',
		// continue with resulting dn
		'filter1' => '(&(objectCategory=person)(proxyAddresses=smtp:*)(memberOf=%s))',
		'attrs1' => array('samaccountname','{forward:}proxyaddresses'),
		'port' => 2003,
	),
);

ini_set('display_errors',false);
error_reporting(E_ALL & ~E_NOTICE);
if ($log) ini_set('error_log',$log);

function usage($extra=null)
{
	global $maps;
	fwrite(STDERR, "\nUsage: $cmd [-v|--verbose] [-h|--help] [-l|--log (syslog|path)] [-q|--query <email-addresse> (mailboxes|alias|groups)] [host]\n\n");
	fwrite(STDERR, print_r($maps,true)."\n");
	if ($extra) fwrite(STDERR, "\n\n$extra\n\n");
	exit(2);
}

$cmd = basename(array_shift($_SERVER['argv']));

while (($arg = array_shift($_SERVER['argv'])) && $arg[0] == '-')
{
	switch($arg)
	{
		case '-v': case '--verbose':
			$verbose = $log_verbose = true;
			break;

		case '-h': case '--help':
			usage();
			break;

		case '-l': case '--log':
			$log = array_shift($_SERVER['argv']);
			break;

		case '-q': case '--query':
			if (count($_SERVER['argv']) == 2)       // need 2 arguments
			{
				$request = 'get '.array_shift($_SERVER['argv'])."\n";
				$map = array_shift($_SERVER['argv']);
				echo respond($request, $map)."\n";
				exit;
			}
			usage();
			break;

		default:
			usage("Unknown option '$arg'!");
	}
}
if ($_SERVER['argv']) usage();

if ($arg)
{
	$host = $arg;
}
else
{
	$host = $default_host;
}

if ($verbose) echo "using $host\n";

$servers = $clients = $buffers = array();

// Create the server socket
foreach($maps as $map => $data)
{
	$addr = 'tcp://'.$host.':'.$data['port'];
	if (!($server = stream_socket_server($addr, $errno, $errstr)))
	{
		fwrite(STDERR, date('Y-m-d H:i:s').": Error calling stream_socket_server('$addr')!\n");
		fwrite(STDERR, $errstr." ($errno)\n");
		exit($errno);
	}
	$servers[$data['port']] = $server;
	$clients[$data['port']] = array();
}
while (true)	// mail loop of tcp server --> never exits
{
	$read = $servers;
	if ($clients) $read = array_merge($read, call_user_func_array('array_merge', array_values($clients)));
	if ($verbose) print 'about to call socket_select(array('.implode(',',$read).', ...) waiting... ';
	if (stream_select($read, $write=null, $except=null, null))	// null = block forever
	{
		foreach($read as $sock)
		{
			if (($port = array_search($sock, $servers)) !== false)
			{
				$client = stream_socket_accept($sock,$timeout,$client_addr);	// @ required to get not timeout warning!

				if ($verbose) echo "accepted connection $client from $client_addr on port $port\n";

				if ($only_client && !preg_match($only_client,$client_addr))
				{
					fwrite($client,"Go away!\r\n");
					fclose($client);
					error_log(date('Y-m-d H:i:s').": Connection $client from wrong client $client_addr (does NOT match '$only_client') --> terminated");
					continue;
				}
				$clients[$port][] = $client;
			}
			elseif (feof($sock))	// client connection closed
			{
				if ($verbose) echo "client $sock closed connection\n";

				foreach($clients as $port => &$socks)
				{
					if (($key = array_search($sock, $socks, true)) !== false)
					{
						unset($socks[$key]);
					}
				}
			}
			else	// client send something
			{
				$buffer =& $buffers[$sock];

				$buffer .= fread($sock, 8096);

				if (strpos($buffer, "\n") !== false)
				{
					list($request, $buffer) = explode("\n", $buffer, 2);

					foreach($maps as $map => $data)
					{
						if (($key = array_search($sock, $clients[$data['port']], true)) !== false)
						{
							if ($verbose) echo date('Y-m-d H:i:s').": client send: $request for map $map\n";

							// Respond to client
							fwrite($sock,  respond($request, $map));
							break;
						}
					}
				}
			}
		}
		if ($except)
		{
			echo "Exception: "; print_r($except);
		}
	}
	else
	{
		// timeout expired
	}
}

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

function respond($request, $map, $extra='', $reconnect=false)
{
	static $ds;
	global $ldap_uri, $version, $use_tls, $bind_dn, $bind_pw;
	global $maps, $log_verbose;

	if (($map == 'aliases' || $map == 'groups') && strpos($request,'@') === false && !$extra)
	{
		return "500 No domain aliases yet\n";
	}
	if (!isset($ds) || $reconnect)
	{
		$ds = ldap_connect($ldap_uri);
		if ($version) ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, $version);
		if ($use_tls) ldap_start_tls($ds);

		if (!@ldap_bind($ds, $bind_dn, $bind_pw))
		{
			error_log("$map: Can't connect to LDAP server $ldap_uri!");
			$ds = null;
			return "400 Can't connect to LDAP server $ldap_uri!\n";	// 400 (temp.) error
		}
	}
	if (!preg_match('/^get ([^\n]+)\n?$/', $request, $matches))
	{
		error_log("$map: Wrong format '$request'!");
		return "400 Wrong format '$request'!\n";	// 400 (temp.) error
	}
	$username = $matches[1];

	list($name,$domain) = explode('@',$username);

	/* check if we are responsible for the given domain
	if ($domain && $map != 'domains' && (int)($response = respond("get $domain", 'domains')) != 200)
	{
		return $response;
	}*/
	$replace = array(
		'%n' => quote($name),
		'%d' => quote($domain),
		'%s' => quote($username),
	);
	$base = strtr($maps[$map]['base'], $replace);
	$filter = strtr($maps[$map]['filter'.$extra], $replace);
	$prefix = isset($maps[$map]['prefix'.$extra]) ? str_replace(array('%n','%d','%s'),array($name,$domain,$username),$maps[$map]['prefix']) : '';
	$search_attrs = $attrs = (array)$maps[$map]['attrs'.$extra];
	// remove prefix like "{smtp:}proxyaddresses"
	foreach($search_attrs as &$attr)
	{
		if ($attr[0] == '{') list(,$attr) = explode('}', $attr);
	}
	unset($attr);

	if (!($sr = @ldap_search($ds, $base, $filter, $search_attrs)))
	{
		$errno = ldap_errno($ds);
		$error = ldap_error($ds).' ('.$errno.')';

		if ($errno == -1)		// eg. -1 lost connection to ldap
		{
			// as DC closes connections quickly, first try to reconnect once, before returning a temp. failure
			if (!$reconnect) return respond($request, $map, $extra, true);

			error_log("$map: get '$username' --> 400 $error: !ldap_search(\$ds, '$base', '$filter')");
			ldap_close($ds);
			$ds = null;	// force new connection on next lookup
			return "400 $error\n";	// 400 (temp.) error
		}
		else	// happens if base containing domain does not exist
		{
			if ($log_verbose) error_log("$map: get '$username' --> 500 Not found: $error: !ldap_search(\$ds, '$base', '$filter')");
			return "500 Not found: $error\n";	// 500 not found
		}
	}
	$entries = ldap_get_entries($ds, $sr);

	if (!$entries['count'])
	{
		if ($log_verbose) error_log("$map: get '$username' --> 500 not found ldap_search(\$ds, '$base', '$filter') no entries");
		return "500 Not found\n";	// 500: Query returned no result
	}
	$response = array();
	foreach($entries as $key => $entry)
	{
		if ($key === 'count') continue;

		foreach($attrs as $attr)
		{
			unset($filter_prefix);
			if ($attr[0] == '{')
			{
				list($filter_prefix, $attr) = explode('}', substr($attr, 1));
			}
			foreach((array)$entry[$attr] as $k => $mail)
			{
				if ($k !== 'count' && ($mail = trim($mail)))
				{
					if ($filter_prefix)
					{
						if (stripos($mail, $filter_prefix) === 0)
						{
							$mail = substr($mail, strlen($filter_prefix));
						}
						else
						{
							continue;
						}
					}
					$response[] = isset($maps[$map]['return']) ? $maps[$map]['return'] : $prefix.$mail;
				}
			}
		}
	}
	if (!$response)
	{
		if ($log_verbose) error_log("$map: get '$username' --> 500 not found ldap_search(\$ds, '$base', '$filter') no response");
		return "500 Not found\n";	// 500: Query returned no result
	}
	if (isset($maps[$map]['filter'.(1+$extra)]) && isset($maps[$map]['attrs'.(1+$extra)]))
	{
		return respond('get '.$response[0], $map, 1+$extra);
	}
	$response = '200 '.implode(',',$response)."\n";
	if ($log_verbose) error_log("$map: get '$username' --> $response");
	return $response;
}
