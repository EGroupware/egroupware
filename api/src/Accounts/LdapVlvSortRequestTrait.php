<?php
/**
 * API - accounts LDAP backend - VLV & sort-request trait
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api\Accounts;

use EGroupware\Api\Ldap\ServerInfo;

trait LdapVlvSortRequestTrait
{
	/**
	 * @var ServerInfo
	 */
	public $serverinfo;

	/**
	 * Get connection to ldap server and optionally reconnect
	 *
	 * @param boolean $reconnect =false true: reconnect even if already connected
	 * @return resource|object
	 */
	public function ldap_connection(bool $reconnect=false)
	{
		throw new \Exception(__METHOD__."() is not overwritten!");
	}

	/**
	 * Get value(s) for LDAP_CONTROL_SORTREQUEST
	 *
	 * Sorting by multiple criteria is supported in LDAP RFC 2891, but - at least with Univention Samba - gives wired results,
	 * Windows AD does NOT support it and gives an error if the oid is specified!
	 *
	 * @param ?string $order_by sql order string eg. "contact_email ASC"
	 * @return array of arrays with values for keys 'attr', 'oid' (caseIgnoreMatch='2.5.13.3') and 'reverse'
	 */
	protected function sortValues($order_by)
	{
		$values = [];
		while (!empty($order_by) && preg_match("/^(account_)?([^ ]+)( ASC| DESC)?,?/i", $order_by, $matches))
		{
			if (($attr = array_search('account_'.$matches[2], $this->timestamps2egw+$this->other2egw)))
			{
				$values[] = [
					'attr' => $attr,
					// use default match 'oid' => '',
					'reverse' => strtoupper($matches[3]) === ' DESC',
				];
			}
			elseif (($attr = array_search('account_'.$matches[2], $this->attributes2egw)))
			{
				$value = [
					'attr' => $attr,
					'oid' => '2.5.13.3',    // caseIgnoreMatch
					'reverse' => strtoupper($matches[3]) === ' DESC',
				];
				// Windows AD does NOT support caseIgnoreMatch sorting, only it's default sorting
				if ($this->serverinfo->activeDirectory(true)) unset($value['oid']);
				$values[] = $value;
			}
			$order_by = substr($order_by, strlen($matches[0]));
			if ($values) break;	// sorting by multiple criteria gives no result for Windows AD and wired result for Samba4
		}
		return $values;
	}

	/**
	 * Run a limited and sorted LDAP query, if server supports that
	 *
	 * @param string $context
	 * @param string $filter array with attribute => value pairs or filter string or empty
	 * @param array $attrs attributes to query
	 * @param string $order_by sql order string eg. "account_email ASC"
	 * @param ?int& $start on return null, if result sorted and limited by server
	 * @param int $num_rows number of rows to return if isset($start)
	 * @param ?int $total on return total number of rows
	 * @return array|false result of ldap_get_entries with key 'count' unset
	 */
	protected function vlvSortQuery(string $context, string $filter, array $attrs, string $order_by=null, int &$start=null, int$num_rows=null, int &$total=null)
	{
		// check if we require sorting and server supports it
		$control = [];
		if (PHP_VERSION >= 7.3 && !empty($order_by) && is_numeric($start) &&
			$this->serverinfo->supportedControl(LDAP_CONTROL_SORTREQUEST, LDAP_CONTROL_VLVREQUEST) &&
			($sort_values = $this->sortValues($order_by)))
		{
			$control = [
				[
					'oid' => LDAP_CONTROL_SORTREQUEST,
					//'iscritical' => TRUE,
					'value' => $sort_values,
				],
				[
					'oid' => LDAP_CONTROL_VLVREQUEST,
					//'iscritical' => TRUE,
					'value' => [
						'before'	=> 0, // Return 0 entry before target
						'after'		=> $num_rows-1, // total-1
						'offset'	=> $start+1, // first = 1, NOT 0!
						'count'		=> 0, // We have no idea how many entries there are
					]
				]
			];
		}

		if (!($sri = ldap_search($ds=$this->ldap_connection(), $context, $filter, $attrs, null, null, null, null, $control)))
		{
			if (($list_view_error = ldap_errno($ds) === 76))	// 76: Virtual List View error --> retry without
			{
				$control = [];
			}
			error_log(__METHOD__."() ldap_search(\$ds, '$context', '$filter') returned ".array2string($sri).' '.ldap_error($ds).
				($list_view_error ? ' retrying without virtual list view ...' : ' trying to reconnect ...'));

			$sri = ldap_search($ds=$this->ldap_connection(!$list_view_error), $context, $filter,
				$attrs, null, null, null, null, $control);
		}

		if ($sri && ($allValues = ldap_get_entries($ds, $sri)))
		{
			// check if given controls succeeded
			if ($control && ldap_parse_result($ds, $sri, $errcode, $matcheddn, $errmsg, $referrals, $serverctrls) &&
				(isset($serverctrls[LDAP_CONTROL_VLVRESPONSE]['value']['count'])))
			{
				$total = $serverctrls[LDAP_CONTROL_VLVRESPONSE]['value']['count'];
				$start = null;	// so caller does NOT run it's own limit
			}
			else
			{
				$total = $allValues['count'];
			}
			unset($allValues['count']);
		}
		else error_log(__METHOD__."() ldap_search(\$ds, '$context', '$filter') returned ".array2string($sri)." allValues=".array2string($allValues));

		//error_log(date('Y-m-d H:i:s ').__METHOD__."('$context', '$filter', ".json_encode($attrs).", order_by=$order_by, start=$start, num_rows=$num_rows) ldap_search($ds, '$context', '$filter')\n==> returning ".count($allValues)."/$total ".substr(array2string($allValues), 0, 1024)."\n--> ".function_backtrace()."\n\n", 3, '/var/lib/egroupware/ads.log');
		return $allValues ?? false;
	}
}