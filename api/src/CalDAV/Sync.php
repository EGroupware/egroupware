<?php
/**
 * EGroupware Calendar - CalDAV sync to subscribe other
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package calendar
 * @copyright (c) 2025 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

require EGW_INCLUDE_ROOT.'/doc/REST-CalDAV-CardDAV/api-client.php';

class Sync
{
	protected $url;
	protected $user;
	protected $password;
	public function __construct(string $url, string $user, string $password)
	{
		$this->url = $url;
		$this->user = $user;
		$this->password = $password;
	}

	/**
	 * Check given URL is a CalDAV calendar collection optionally supporting sync-collection report
	 *
	 * @ToDo autodetection if only a hostname is given
	 * @return string full URL after autodetection
	 * @throws \Exception
	 */
	public function test()
	{
		$xml = api($this->url, 'PROPFIND', '', [
				'Accept: application/xml',
				'Depth: 0',
				'Authorization: Basic '.base64_encode($this->user.':'.$this->password),
				'User-Agent: '.str_replace('\\', '/', __CLASS__).'/'.$GLOBALS['egw_info']['apps']['api']['version'],
			], $response_header);

		if (!isset($response_header['x-webdav-status']) || (int)$response_header['x-webdav-status'] !== 207)
		{
			throw new \Exception('Given URL is not a CalDAV or WebDAV server: no X-WebDAV-Status header found!');
		}
		$xml_reader = new \SimpleXMLElement($xml, 0, false, 'DAV', false);
		$reports = $resource_types = [];
		$xml_reader->registerXPathNamespace('D', 'DAV:');
		foreach($xml_reader->xpath('//D:resourcetype') as $type)
		{
			$resource_types[] = key($type->children(Api\CalDAV::CALDAV));
		}
		if (!in_array('calendar', $resource_types))
		{
			throw new \Exception("Given URL is not a CalDAV or WebDAV server: missing resourcetype 'calendar'!");
		}
		foreach($xml_reader->xpath('//D:report') as $report)
		{
			$reports[] = key($report->children(Api\CalDAV::DAV)) ?? key($report->children(Api\CalDAV::CALDAV));
		}
		if (!array_intersect(['sync-collection', 'calendar-query'], $reports))
		{
			throw new \Exception("Given URL is not a CalDAV or WebDAV server: missing supported-report 'sync-collection' or 'calendar-query'!");
		}
		if (!in_array('sync-collection', $reports))
		{
			// ToDo: check ctag is supported
		}
		return $this->url;
	}

	/**
	 * Multistatus response for sync-collection REPORT
	 *
	 * @param string|null &$sync_token
	 * @param bool $yield_href_ical=false
	 * @return \Generator $yield_href_ical=false: SimpleXMLElement $response->children('DAV:') with: (all need to be cast to string!)
	 * ->href
	 * ->propstat->status
	 * ->propstat->prop->etag
	 * ->propstat->prop->children(Api\CalDAV::CALDAV)->{'calendar-data'} (iCal)
	 * $yield_href_ical = true: key: href, value: iCal string or null (for 404 Not found)
	 * @throws \Exception on error
	 */
	public function sync_collection(?string &$sync_token=null, bool $yield_href_ical=false)
	{
		$xml = api($this->url, 'REPORT', $body=<<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<D:sync-collection xmlns:D="DAV:">
  <D:sync-token>$sync_token</D:sync-token>
  <D:sync-level>1</D:sync-level>
  <D:prop>
    <D:getetag/>
    <C:calendar-data xmlns:C="urn:ietf:params:xml:ns:caldav"/>
  </D:prop>
</D:sync-collection>
EOT, [
			'Content-Type: application/xml; charset=utf-8',
			'Accept: application/xml',
			'Depth: 1',
			'Authorization: Basic '.base64_encode($this->user.':'.$this->password),
			'User-Agent: '.str_replace('\\', '/', __CLASS__).'/'.$GLOBALS['egw_info']['apps']['api']['version'],
		], $response_header);
		if (($http_status = explode(' ', $response_header[0])[1]) != 207)
		{
			throw new HttpException("Unexpected HTTP status code $http_status for sync-collection REPORT: ".
				($response_header['www-authenticate'] ?? ''), (int)$http_status,
				'REPORT', $this->url, $body, $response_header);
		}
		$xml_reader = new \SimpleXMLElement($xml, 0, false, 'DAV', false);
		$xml_reader->registerXPathNamespace('D', 'DAV:');

		foreach($xml_reader->xpath('//D:response') as $response)
		{
			$dav_children = $response->children(Api\CalDAV::DAV);
			if ($yield_href_ical)
			{
				yield (string)$dav_children->href => (int)(string)$dav_children->propstat->status === 404 ?
					null : (string)$dav_children->propstat->prop->children(Api\CalDAV::CALDAV)->{'calendar-data'};
			}
			else
			{
				yield $dav_children;
			}
		}
		$sync_token = (string)$xml_reader->xpath('//D:sync-token')[0];
	}

	public function sync(?string &$sync_token, int $cat_id, array $participants=[])
	{
		$ical_class = new \calendar_ical();
		foreach($this->sync_collection($sync_token, true) as $href => $ical)
		{
			if ($ical)
			{
				$ical_class->event_callback = static function(array &$event) use ($href, $cat_id, $participants)
				{
					$event['#sync-href'] = $href;
					$event['category'] = empty($event['category']) ? $cat_id : $event['category'].','.$cat_id;
					foreach($participants as $uid)
					{
						if (!isset($event['participants'][$uid]))
						{
							$event['participants'][$uid] = 'U';
						}
					}
					return true;
				};
				$ical_class->importVCal($ical);
			}
			elseif (($event = $ical_class->read(['#sync-href' => $href])))
			{
				$ical_class->delete($event['id']);
			}
		}
	}
}