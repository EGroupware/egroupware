<?

require ("main.inc");
error_reporting (4);

if ($htaccess)
{
	$username = $PHP_AUTH_USER;
	$password = $PHP_AUTH_PW;
}

if ($username && $password)
{
	$query = sql_query ("SELECT * FROM userinfo WHERE username = '$username'");

	if (!mysql_num_rows($query))
	{
	        echo "No such username";
		login();
	}

	$query = sql_query ("SELECT * FROM userinfo WHERE username = '$username' AND password = PASSWORD('$password')");

	if (!mysql_num_rows($query))
	{
	        echo "Invalid password";
	        login ();
	}

	setcookie ("cookieusername", $username, 0, "$hostname_path", "$hostname_domain");
	setcookie ("cookiepassword", $password, 0, "$hostname_path", "$hostname_domain");

	$query = sql_query ("UPDATE userinfo SET lastlogin = NOW() WHERE username = '$username'");
	$query = sql_query ("UPDATE userinfo SET lastip = '$REMOTE_ADDR' WHERE username = '$username'");

	header ("Location: $hostname/users.php");
}

if ($cookieusername && $cookiepassword)
{
	header ("Location: $hostname/users.php");
	exit;
}

if (!$username || !$password)
	login();

?>
