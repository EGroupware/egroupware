<?php

include_once('PEAR.php');

/**
 * The IMP_IMAPClient:: class enables connection to an IMAP server through
 * built-in PHP functions.
 *
 * TODO: This should eventually be moved to Horde 4.0/framework.
 *
 * $Horde: imp/lib/IMAP/Client.php,v 1.21.2.21 2006/03/30 10:15:31 selsky Exp $
 *
 * Copyright 2005-2006 Michael Slusarz <slusarz@horde.org>
 *
 * Based on code from:
 *   + auth.php (1.49)
 *   + imap_general.php (1.212)
 *   + strings.php (1.184.2.35)
 *   from the Squirrelmail project.
 *   Copyright (c) 1999-2005 The SquirrelMail Project Team
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @since   IMP 4.1
 * @package IMP
 */
class imap_client {

    /**
     * The list of capabilities of the IMAP server.
     *
     * @var array
     */
    var $_capability = null;

    /**
     * The hostname of the IMAP server to connect to.
     *
     * @var string
     */
    var $_host;

    /**
     * The last message returned from the server.
     *
     * @var string
     */
    var $_message;

    /**
     * The namespace information.
     *
     * @var array
     */
    var $_namespace = null;

    /**
     * The port number of the IMAP server to connect to.
     *
     * @var string
     */
    var $_port;

    /**
     * The last response returned from the server.
     *
     * @var string
     */
    var $_response;

    /**
     * The unique session identifier ID to use when making an IMAP query.
     *
     * @var integer
     */
    var $_sessionid = 1;

    /**
     * The socket connection to the IMAP server.
     *
     * @var resource
     */
    var $_stream;

    /**
     * Are we using SSL to connect to the IMAP server?
     *
     * @var string
     */
    var $_usessl = false;

    /**
     * Are we using TLS to connect to the IMAP server?
     *
     * @var string
     */
    var $_usetls = false;

    /**
     * Constructor.
     *
     * @param string $host      The address/hostname of the IMAP server.
     * @param string $port      The port to connect to on the IMAP server.
     * @param string $protocol  The protocol string (See, e.g., servers.php).
     */
    function imap_client($host, $port, $protocol)
    {
        $this->_host = $host;
        $this->_port = $port;

        /* Split apart protocol string to discover if we need to use either
         * SSL or TLS. */
        $tmp = explode('/', strtolower($protocol));
        if (in_array('tls', $tmp)) {
            $this->_usetls = true;
        } elseif (in_array('ssl', $tmp)) {
            $this->_usessl = true;
        }
    }

    /**
     * Are we using TLS to connect and is it supported?
     *
     * @return mixed  Returns true if TLS is being used to connect, false if
     *                is not, and PEAR_Error if we are attempting to use TLS
     *                and this version of PHP doesn't support it.
     */
    function useTLS()
    {
        if ($this->_usetls) {
            /* There is no way in PHP 4 to open a TLS connection to a
             * non-secured port.  See http://bugs.php.net/bug.php?id=26040 */
            if (!function_exists('stream_socket_enable_crypto')) {
                return PEAR::raiseError(lang("To use a TLS connection, you must be running a version of PHP 5.1.0 or higher."), 'horde.error');
            }
        }

        return $this->_usetls;
    }

    /**
     * Generates a new IMAP session ID by incrementing the last one used.
     *
     * @access private
     *
     * @return string  IMAP session id of the form 'A000'.
     */
    function _generateSid()
    {
        return sprintf("A%03d", $this->_sessionid++);
    }

    /**
     * Perform a command on the IMAP server.
     * This command sets the $_response and $_message variable.
     *
     * @access private
     *
     * @param string $query  IMAP command.
     *
     * @return mixed  Returns PEAR_Error on error.  On success, returns an
     *                array of the IMAP return text.
     */
    function _runCommand($query)
    {
        $message = $response = array();

        $sid = $this->_generateSid();
        fwrite($this->_stream, $sid . ' ' . $query . "\r\n");
        $tag_uid_a = explode(' ', trim($sid));
        $tag = $tag_uid_a[0];

        $res = $this->_retrieveIMAPResponse($tag, $response, $message);
        if (is_a($res, 'PEAR_Error')) {
            $this->_message = $this->_response = '';
            return $res;
        }

        /* retrieve the response and the message */
        $this->_response = $response[$tag];
        $this->_message  = $message[$tag];

        return (!empty($res[$tag])) ? $res[$tag][0] : $res[$tag];
    }

    /**
     * Custom fgets function - get a line from the IMAP server no matter how
     * large the line may be.
     *
     * @access private
     *
     * @return string  The next line in the IMAP stream.
     */
    function _fgets()
    {
        $buffer = 4096;
        $offset = 0;
        $results = '';

        while (strpos($results, "\r\n", $offset) === false) {
            if (!($read = fgets($this->_stream, $buffer))) {
                $results = '';
                break;
            }
            if ($results != '') {
                $offset = strlen($results) - 1;
            }
            $results .= $read;
        }

        return $results;
    }

    /**
     * Reads the output from the IMAP stream.
     *
     * @access private
     *
     * @param string $tag      The IMAP SID tag.
     * @param array $response  The response information.
     * @param array $message   The message information.
     *
     * @return mixed  PEAR_Error on error, response string on success.
     */
    function _retrieveIMAPResponse($tag, &$response, &$message)
    {
        $aResponse = $read = '';
        $data = $resultlist = array();
        $i = 0;

        $read = $this->_fgets();
        while ($read) {
            $char = $read{0};
            switch ($char) {
            case '+':
            default:
                $read = $this->_fgets();
                break;

            case $tag{0}:
                /* Get the command. */
                $arg = '';
                $i = strlen($tag) + 1;
                $s = substr($read, $i);
                if (($j = strpos($s, ' ')) || ($j = strpos($s, "\n"))) {
                    $arg = substr($s, 0, $j);
                }
                $found_tag = substr($read, 0, $i - 1);
                if ($found_tag) {
                    $response[$found_tag] = $arg;
                    $message[$found_tag] = trim(substr($read, $i + strlen($arg)));
                    if (!empty($data)) {
                        $resultlist[] = $data;
                    }
                    $aResponse[$found_tag] = $resultlist;
                    $data = $resultlist = array();
                    if ($found_tag == $tag) {
                        break 2;
                    }
                    break;
                }

                $read = $this->_fgets();
                if ($read === false) {
                    break 2; /* switch while */
                }
                break;

            case '*':
                if (preg_match('/^\*\s\d+\sFETCH/', $read)) {
                    /* check for literal */
                    $s = substr($read, -3);
                    $fetch_data = array();
                    do {
                        /* Outer loop: continue until next untagged fetch
                           or tagged reponse. */
                        do {
                            /* Innerloop for fetching literals. with this
                               loop we prohibit that literal responses appear
                               in the outer loop so we can trust the untagged
                               and tagged info provided by $read. */
                            if ($s === "}\r\n") {
                                $j = strrpos($read, '{');
                                $iLit = substr($read, $j + 1, -3);
                                $fetch_data[] = $read;
                                $sLiteral = fread($this->_stream, $iLit);
                                if ($sLiteral === false) { /* error */
                                    break 4; /* while while switch while */
                                }
                                /* backwards compattibility */
                                $aLiteral = explode("\n", $sLiteral);

                                unset($sLiteral);

                                foreach ($aLiteral as $line) {
                                    $fetch_data[] = $line ."\n";
                                }

                                unset($aLiteral);

                                /* Next fgets belongs to this fetch because
                                   we just got the exact literalsize and data
                                   must follow to complete the response. */
                                $read = $this->_fgets();
                                if ($read === false) { /* error */
                                    break 4; /* while while switch while */
                                }
                            }
                            $fetch_data[] = $read;

                            /* Retrieve next line and check in the while
                               statements if it belongs to this fetch
                               response. */
                            $read = $this->_fgets();
                            if ($read === false) { /* error */
                                break 4; /* while while switch while */
                            }
                            /* Check for next untagged reponse and break. */
                            if ($read{0} == '*') {
                                break 2;
                            }
                            $s = substr($read, -3);
                        } while ($s === "}\r\n");

                        $s = substr($read,-3);
                    } while (($read{0} !== '*') &&
                             (substr($read, 0, strlen($tag)) !== $tag));

                    $resultlist[] = $fetch_data;
                    unset($fetch_data);
                } else {
                    $s = substr($read, -3);
                    do {
                        if ($s === "}\r\n") {
                            $j = strrpos($read, '{');
                            $iLit = substr($read, $j + 1, -3);
                            $data[] = $read;
                            $sLiteral = fread($this->_stream, $iLit);
                            if ($sLiteral === false) { /* error */
                                $read = false;
                                break 3; /* while switch while */
                            }
                            $data[] = $sLiteral;
                            $data[] = $this->_fgets();
                        } else {
                            $data[] = $read;
                        }
                        $read = $this->_fgets();
                        if ($read === false) {
                            break 3; /* while switch while */
                        } elseif ($read{0} == '*') {
                            break;
                        }
                        $s = substr($read,-3);
                    } while ($s === "}\r\n");
                    break;
                }
                break;
            }
        }

        /* Error processing in case $read is false. */
        if ($read === false) {
            /* Try to retrieve an untagged bye respons from the results. */
            $sResponse = array_pop($data);
            if (($sResponse !== NULL) &&
                (strpos($sResponse,'* BYE') !== false)) {
                return PEAR::raiseError(lang("IMAP server closed the connection."), 'horde.error');
            } else {
                return PEAR::raiseError(lang("Connection dropped by IMAP server."), 'horde.error');
            }
        }

        switch ($response[$tag]) {
        case 'OK':
            return $aResponse;
            break;

        case 'NO':
            /* Ignore this error from M$ exchange, it is not fatal (aka bug). */
            if (strpos($message[$tag], 'command resulted in') === false) {
                return PEAR::raiseError(sprintf(lang("Could not complete request. Reason Given: %s"), $message[$tag]), 'horde.error', null, null, $response[$tag]);
            }
            break;

        case 'BAD':
            return PEAR::raiseError(sprintf(lang("Bad or malformed request. Server Responded: %s"), $message[$tag]), 'horde.error', null, null, $response[$tag]);
            break;

        case 'BYE':
            return PEAR::raiseError(sprintf(lang("IMAP Server closed the connection. Server Responded: %s"), $message[$tag]), 'horde.error', null, null, $response[$tag]);
            break;

        default:
            return PEAR::raiseError(sprintf(lang("Unknown IMAP response from the server. Server Responded: %s"), $message[$tag]), 'horde.error', null, null, $response[$tag]);
            break;
        }
    }

    /**
     * Connects to the IMAP server.
     *
     * @access private
     *
     * @return mixed  Returns true on success, PEAR_Error on error.
     */
    function _createStream()
    {
        if (($this->_usessl || $this->_usetls) &&
            !function_exists('openssl_pkcs7_sign')) {
            return PEAR::raiseError(lang("If using SSL or TLS, you must have the PHP openssl extension loaded."), 'horde.error');
        }

        if ($res = $this->useTLS()) {
            if (is_a($res, 'PEAR_Error')) {
                return $res;
            } else {
                $this->_host = $this->_host . ':' . $this->_port;
            }
        }

        if ($this->_usessl) {
            $this->_host = 'ssl://' . $this->_host;
        }
        $error_number = $error_string = '';
        $timeout = 10;

        if ($this->_usetls) {
            $this->_stream = stream_socket_client($this->_host, $error_number, $error_string, $timeout);
            if (!$this->_stream) {
                return PEAR::raiseError(sprintf(lang("Error connecting to IMAP server. %s : %s."), $error_number, $error_string), 'horde.error');
            }

            /* Disregard any server information returned. */
            fgets($this->_stream, 1024);

            /* Send the STARTTLS command. */
            fwrite($this->_stream, $this->_generateSid() . " STARTTLS\r\n");

            /* Disregard any server information returned. */
            fgets($this->_stream, 1024);

            /* Switch over to a TLS connection. */
            $res = stream_socket_enable_crypto($this->_stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$res) {
                return PEAR::raiseError(lang("Could not open secure connection to the IMAP server. %s : %s."), 'horde.error');
            }
        } else {
            $this->_stream = fsockopen($this->_host, $this->_port, $error_number, $error_string, $timeout);
        }

        /* Do some error correction */
        if (!$this->_stream) {
            return PEAR::raiseError(sprintf(lang("Error connecting to IMAP server. %s : %s."), $error_number, $error_string), 'horde.error');
        }

        /* Disregard any server information. */
        fgets($this->_stream, 1024);
    }

    /**
     * Log the user into the IMAP server.
     *
     * @param string $username  Username.
     * @param string $password  Encrypted password.
     *
     * @return mixed  True on success, PEAR_Error on error.
     */
    function login($username, $password)
    {
        $res = $this->_createStream();
        if (is_a($res, 'PEAR_Error')) {
#LK            Horde::logMessage($res, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $res;
        }

        $imap_auth_mech = array();

        /* Use md5 authentication, if available. But no need to use special
         * authentication if we are already using an encrypted connection. */
        $auth_methods = $this->queryCapability('AUTH');
        if ((!$this->_usessl || !$this->_usetls) && !empty($auth_methods)) {
            if (in_array('CRAM-MD5', $auth_methods)) {
                $imap_auth_mech[] = 'cram-md5';
            }
            if (in_array('DIGEST-MD5', $auth_methods)) {
                $imap_auth_mech[] = 'digest-md5';
            }
        }

        /* Next, try 'PLAIN' authentication. */
        if (!empty($auth_methods) && in_array('PLAIN', $auth_methods)) {
            $imap_auth_mech[] = 'plain';
        }

        /* Fall back to 'LOGIN' if available. */
        if (!$this->queryCapability('LOGINDISABLED')) {
            $imap_auth_mech[] = 'login';
        }

        if (empty($imap_auth_mech)) {
            return PEAR::raiseError(lang("No supported IMAP authentication method could be found."), 'horde.error');
        }

        foreach ($imap_auth_mech as $method) {
            $res = $this->_login($username, $password, $method);
            if (!is_a($res, 'PEAR_Error')) {
                return true;
            }
        }

        return $res;
    }

    /**
     * Log the user into the IMAP server.
     *
     * @access private
     *
     * @param string $username  Username.
     * @param string $password  Encrypted password.
     * @param string $method    IMAP login method.
     *
     * @return mixed  True on success, PEAR_Error on error.
     */
    function _login($username, $password, $method)
    {
        switch ($method) {
        case 'cram-md5':
        case 'digest-md5':
            /* If we don't have Auth_SASL package install, return error. */
            if (!@include_once 'Auth/SASL.php') {
                return PEAR::raiseError(lang("CRAM-MD5 or DIGEST-MD5 requires the Auth_SASL package to be installed."), 'horde.error');
            }

            $tag = $this->_generateSid();
            fwrite($this->_stream, $tag . ' AUTHENTICATE ' . strtoupper($method) . "\r\n");
            $challenge = explode(' ', $this->_fgets(), 3);

            if ($method == 'cram-md5') {
                $auth_sasl = Auth_SASL::factory('crammd5');
                $response = $auth_sasl->getResponse($username, $password, base64_decode($challenge[1]));
                fwrite($this->_stream, base64_encode($response) . "\r\n");
                $read = $this->_fgets();
            } elseif ($method == 'digest-md5') {
                $auth_sasl = Auth_SASL::factory('digestmd5');
                $response = $auth_sasl->getResponse($username, $password, base64_decode($challenge[1]), $this->_host, 'imap');
                fwrite($this->_stream, base64_encode($response) . "\r\n");
                $response = explode(' ', $this->_fgets());
                $response = base64_decode($response[1]);
                if (strpos($response, 'rspauth=') === false) {
                    return PEAR::raiseError(lang("Unexpected response from server to Digest-MD5 response."), 'horde.error');
                }
                fwrite($this->_stream, "\r\n");
                $read = $this->_fgets();
            } else {
                return PEAR::raiseError(lang("The IMAP server does not appear to support the authentication method selected. Please contact your system administrator."), 'horde.error');
            }
            break;

        case 'login':
            $tag = $this->_generateSid();
            $query = $tag . " LOGIN $username {" . strlen($password) . "}\r\n";
            fwrite($this->_stream, $query);
            $read = $this->_fgets();
            if (substr($read, 0, 1) == '+') {
                fwrite($this->_stream, "$password\r\n");
                $read = $this->_fgets();
            } else {
                return PEAR::raiseError(lang("Unexpected response from server to LOGIN command."), 'horde.error');
            }
            break;

        case 'plain':
            $tag = $this->_generateSid();
            $sasl = $this->queryCapability('SASL-IR');
            $auth = base64_encode("$username\0$username\0$password");
            if ($sasl) {
                // IMAP Extension for SASL Initial Client Response
                // <draft-siemborski-imap-sasl-initial-response-01b.txt>
                $query = $tag . " AUTHENTICATE PLAIN $auth\r\n";
                fwrite($this->_stream, $query);
                $read = $this->_fgets();
            } else {
                $query = $tag . " AUTHENTICATE PLAIN\r\n";
                fwrite($this->_stream, $query);
                $read = $this->_fgets();
                if (substr($read, 0, 1) == '+') {
                    fwrite($this->_stream, "$auth\r\n");
                    $read = $this->_fgets();
                } else {
                    return PEAR::raiseError(lang("Unexpected response from server to AUTHENTICATE command."), 'horde.error');
                }
            }
            break;
        }

        /* Check for failed login. */
        $results = explode(' ', $read, 3);
        $response = $results[1];

        if ($response != 'OK') {
            $message = !empty($results[2]) ? htmlspecialchars($results[2]) : lang("No message returned.");

            switch ($response) {
            case 'NO':
                return PEAR::raiseError(sprintf(lang("Bad login name or password."), $message), 'horde.error');

            case 'BAD':
            default:
                return PEAR::raiseError(sprintf(lang("Bad request: %s"), $message), 'horde.error');
            }
        }

        return true;
    }

    /**
     * Log out of the IMAP session.
     */
    function logout()
    {
        /* Logout is not valid until the server returns 'BYE'
         * If we don't have an imap_ stream we're already logged out */
        if (isset($this->_stream) && $this->_stream) {
            $this->_runCommand('LOGOUT');
        }
    }

    /**
     * Get the CAPABILITY string from the IMAP server.
     *
     * @access private
     */
    function _capability()
    {
        if (!is_null($this->_capability)) {
            return;
        }

        $this->_capability = array();
        $read = $this->_runCommand('CAPABILITY');
        if (is_a($read, 'PEAR_Error')) {
#LK            Horde::logMessage($read, __FILE__, __LINE__, PEAR_LOG_ERR);
            return;
        }

        $c = explode(' ', trim($read[0]));
        for ($i = 2; $i < count($c); $i++) {
            $cap_list = explode('=', $c[$i]);
            if (isset($cap_list[1])) {
                if (!isset($this->_capability[$cap_list[0]])) {
                    $this->_capability[$cap_list[0]] = array();
                }
                $this->_capability[$cap_list[0]][] = $cap_list[1];
            } else {
                $this->_capability[$cap_list[0]] = true;
            }
        }
    }

    /**
     * Returns whether the IMAP server supports the given capability.
     *
     * @param string $capability  The capability string to query.
     *
     * @param mixed  True if the server supports the queried capability,
     *               false if it doesn't, or an array if the capability can
     *               contain multiple values.
     */
    function queryCapability($capability)
    {
        $this->_capability();
        return isset($this->_capability[$capability]) ? $this->_capability[$capability] : false;
    }

    /**
     * Get the NAMESPACE information from the IMAP server.
     *
     * @param array $additional  If the server supports namespaces, any
     *                           additional namespaces to add to the
     *                           namespace list that are not broadcast by
     *                           the server.
     *
     * @return array  An array with the following format:
     * <pre>
     * Array
     * (
     *   [foo] => Array
     *   (
     *     [name] => (string)
     *     [delimiter] => (string)
     *     [type] => 'personal' | 'others' | 'shared'
     *     [hidden] => (boolean)
     *   )
     *
     *   [foo2] => Array
     *   (
     *     ...
     *   )
     * )
     * </pre>
     */
    function namespace($additional = array())
    {
        if (!is_null($this->_namespace)) {
            return $this->_namespace;
        }

        $namespace_array = array(
            1 => 'personal',
            2 => 'others',
            3 => 'shared'
        );

        if ($this->queryCapability('NAMESPACE')) {
            /*
             * According to rfc2342 response from NAMESPACE command is:
             * * NAMESPACE (PERSONAL NAMESPACES) (OTHER_USERS NAMESPACE) (SHARED NAMESPACES)
             */
            $read = $this->_runCommand('NAMESPACE');
            if (is_a($read, 'PEAR_Error')) {
#LK                Horde::logMessage($read, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $read;
            }

            if (eregi('\\* NAMESPACE +(\\( *\\(.+\\) *\\)|NIL) +(\\( *\\(.+\\) *\\)|NIL) +(\\( *\\(.+\\) *\\)|NIL)', $read[0], $data)) {
                for ($i = 1; $i <= 3; $i++) {
                    if ($data[$i] == 'NIL') {
                        continue;
                    }
                    $pna = explode(')(', $data[$i]);
                    while (list($k, $v) = each($pna)) {
                        $lst = explode('"', $v);
                        $delimiter = (isset($lst[3])) ? $lst[3] : '';
                        $this->_namespace[$lst[1]] = array('name' => $lst[1], 'delimiter' => $delimiter, 'type' => $namespace_array[$i], 'hidden' => false);
                    }
                }
            }

            foreach ($additional as $val) {
                /* Skip namespaces if we have already auto-detected them.
                 * Also, hidden namespaces cannot be empty. */
                $val = trim($val);
                if (empty($val) || isset($this->_namespace[$val])) {
                    continue;
                }
                $read = $this->_runCommand('LIST "" "' . $val . '"');
                if (is_a($read, 'PEAR_Error')) {
#LK                    Horde::logMessage($read, __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $res;
                }
                if (!empty($read) &&
                    preg_match("/^\* LIST \(.*\) \"(.*)\" \"?(.*?)\"?\s*$/", $read[0], $data) &&
                    ($data[2] == $val)) {
                    $this->_namespace[$val] = array('name' => $val, 'delimiter' => $data[1], 'type' => $namespace_array[3], 'hidden' => true);
                }
            }
        } else {
            $res = $this->_runCommand('LIST "" ""');
            if (is_a($res, 'PEAR_Error')) {
#LK                Horde::logMessage($res, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $res;
            }
            $quote_position = strpos($res[0], '"');
            $this->_namespace[''] = array('name' => '', 'delimiter' => substr($res[0], $quote_position + 1 , 1), 'type' => $namespace_array[1], 'hidden' => false);
        }

        return $this->_namespace;
    }

    /**
     * Determines whether the IMAP search command supports the optional
     * charset provided.
     *
     * @param string $charset  The character set to test.
     *
     * @return boolean  True if the IMAP search command supports the charset.
     */
    function searchCharset($charset)
    {
        $this->_runCommand('SELECT INBOX');
        $read = $this->_runCommand('SEARCH CHARSET ' . $charset . ' TEXT "charsettest" 1');
        return !is_a($read, 'PEAR_Error');
    }

}
