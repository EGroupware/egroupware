<?php
/**
 * EGroupware API: Basic and Digest Auth
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 * (This makes authentication details available in PHP as $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage auth
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

use EGroupware\Api\Header\Authenticate;

/**
 * Class to authenticate via basic or digest auth
 *
 * @deprecated use EGroupware\Api\Header\Authenticate
 */
class egw_digest_auth extends Authenticate {}
