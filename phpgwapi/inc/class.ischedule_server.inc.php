<?php
/**
 * EGroupware: iSchedule server
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * iSchedule server: serverside of iSchedule
 *
 * @link https://tools.ietf.org/html/draft-desruisseaux-ischedule-01 iSchedule draft from 2010
 */
class ischedule_server
{
	/**
	 * iSchedule xml namespace
	 */
	const ISCHEDULE = 'urn:ietf:params:xml:ns:ischedule';

	/**
	 * Own iSchedule version
	 */
	const VERSION = '1.0';

	/**
	 * Serve an iSchedule request
	 */
	public function ServeRequest()
	{
		// install our own exception handler sending exceptions as http status
		set_exception_handler(array(__CLASS__, 'exception_handler'));

		switch($_SERVER['REQUEST_METHOD'])
		{
			case 'GET':
				$this->get();
				break;

			case 'POST':
				$this->post();
				break;

			default:
				error_log(__METHOD__."() invalid iSchedule request using {$_SERVER['REQUEST_METHOD']}!");
				header("HTTP/1.1 400 Bad Request");
		}
	}

	/**
	 * Serve an iSchedule POST request
	 */
	protected function post()
	{
		static $required_headers = array('Host','Recipient','Originator','Content-Type','DKIM-Signature');
		$headers = array();
		foreach($required_headers as $header)
		{
			$server_name = strtoupper(str_replace('-', '_', $header));
			if (strpos($server_name, 'CONTENT_') !== 0) $server_name = 'HTTP_'.$server_name;
			if (!empty($_SERVER[$server_name])) $headers[$header] = $_SERVER[$server_name];
		}
		if (($missing = array_diff($required_headers, array_keys($headers))))
		{
			throw new Exception ('Bad Request: missing '.implode(', ', $missing).' header(s)', 403);
		}
		if (!$this->dkim_validate($headers))
		{
			throw new Exception('Bad Request: DKIM signature invalid', 403);
		}
		// parse iCal

		// validate originator matches organizer or attendee
		throw new exception('Not yet implemented!');
	}

	/**
	 * Validate DKIM signature
	 *
	 * @param array $headers
	 * @return boolean true if signature could be validated, false otherwise
	 * @todo
	 */
	public function dkim_validate(array $headers)
	{
		return isset($headers['DKIM-Signature']);
	}

	/**
	 * Serve an iSchedule GET request, currently only query=capabilities
	 *
	 * GET /.well-known/ischedule?query=capabilities HTTP/1.1
	 * Host: cal.example.com
	 *
	 * HTTP/1.1 200 OK
	 * Date: Mon, 15 Dec 2008 09:32:12 GMT
	 * Content-Type: application/xml; charset=utf-8
	 * Content-Length: xxxx
	 * iSchedule-Version: 1.0
	 * ETag: "afasdf-132afds"
	 *
	 * <?xml version="1.0" encoding="utf-8" ?>
	 * <query-result xmlns="urn:ietf:params:xml:ns:ischedule">
	 *   <capability-set>
	 *     <supported-version-set>
	 *       <version>1.0</version>
	 *     </supported-version-set>
	 *     <supported-scheduling-message-set>
	 *       <comp name="VEVENT">
	 *         <method name="REQUEST"/>
	 *         <method name="ADD"/>
	 *         <method name="REPLY"/>
	 *         <method name="CANCEL"/>
	 *       </comp>
	 *       <comp name="VTODO"/>
	 *       <comp name="VFREEBUSY"/>
	 *     </supported-scheduling-message-set>
	 *     <supported-calendar-data-type>
	 *       <calendar-data-type content-type="text/calendar" version="2.0"/>
	 *     </supported-calendar-data-type>
	 *     <supported-attachment-values>
	 *       <inline-attachment/>
	 *       <external-attachment/>
	 *     </supported-attachment-values>
	 *     <supported-recipient-uri-scheme-set>
	 *       <scheme>mailto</scheme>
	 *     </supported-recipient-uri-scheme-set>
	 *     <max-content-length>102400</max-content-length>
	 *     <min-date-time>19910101T000000Z</min-date-time>
	 *     <max-date-time>20381231T000000Z</max-date-time>
	 *     <max-instances>150</max-instances>
	 *     <max-recipients>250</max-recipients>
	 *     <administrator>mailto:ischedule-admin@example.com</administrator>
	 *   </capability-set>
	 * </query-result>
	 */
	protected function get()
	{
		if (!isset($_GET['query']) || $_GET['query'] !== 'capabilities')
		{
			error_log(__METHOD__."() invalid iSchedule request using GET without query=capabilities!");
			header("HTTP/1.1 400 Bad Request");
			return;
		}

		// generate capabilities
		/*$xml = new XMLWriter;
		$xml->openMemory();
		$xml->setIndent(true);
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElementNs(null, 'query-result', self::ISCHEDULE);
		$xml->startElement('capability-set');

		foreach(array(
			'supported-version-set' => array('version' => array('1.0')),
			'supported-scheduling-message-set' => array(
				'comp' => array('.name' => array(
					'VEVENT' => array('method' => array('REQUEST', 'ADD', 'REPLY', 'CANCEL')),
					'VTODO' => '',
					'VFREEBUSY' => '',
				)),
			)
		) as $name => $data)
		{
			$xml->writeElement($name, $data);
		}

		$xml->endElement();	// capability-set
		$xml->endElement();	// query-result
		$xml->endDocument();
		$capabilities = $xml->outputMemory();*/

		$capabilities = '<?xml version="1.0" encoding="utf-8" ?>
  <query-result xmlns="urn:ietf:params:xml:ns:ischedule">
    <capability-set>
      <supported-version-set>
        <version>1.0</version>
      </supported-version-set>
      <supported-scheduling-message-set>
        <comp name="VEVENT">
          <method name="REQUEST"/>
          <method name="ADD"/>
          <method name="REPLY"/>
          <method name="CANCEL"/>
        </comp>
        <comp name="VTODO"/>
        <comp name="VFREEBUSY"/>
      </supported-scheduling-message-set>
      <supported-calendar-data-type>
        <calendar-data-type content-type="text/calendar" version="2.0"/>
      </supported-calendar-data-type>
      <supported-attachment-values>
        <inline-attachment/>
        <external-attachment/>
      </supported-attachment-values>
      <supported-recipient-uri-scheme-set>
        <scheme>mailto</scheme>
      </supported-recipient-uri-scheme-set>
      <max-content-length>102400</max-content-length>
      <min-date-time>19910101T000000Z</min-date-time>
      <max-date-time>20381231T000000Z</max-date-time>
      <max-instances>150</max-instances>
      <max-recipients>250</max-recipients>
      <administrator>mailto:ischedule-admin@example.com</administrator>
    </capability-set>
  </query-result>';

		// returning capabilities
		header('Content-Type: application/xml; charset=utf-8');
		header('iSchedule-Version: '.self::VERSION);
		header('Content-Length: '.bytes($capabilites));
		header('ETag: "'.md5($capabilites).'"');

		echo $capabilities;
		common::egw_exit();
	}

	/**
	 * Exception handler, which additionally logs the request (incl. a trace)
	 *
	 * Does NOT return and get installed in constructor.
	 *
	 * @param Exception $e
	 */
	public static function exception_handler(Exception $e)
	{
		// logging exception as regular egw_execption_hander does
		_egw_log_exception($e,$headline);

		// exception handler sending message back to the client as http status
		$code = $e->getCode();
		$msg = $e->getMessage();
		if (!in_array($code, array(400, 403, 407, 503))) $code = 500;
		header('HTTP/1.1 '.$code.' '.$msg);

		// if our groupdav logging is active, log the request plus a trace, if enabled in server-config
		if (self::$request_starttime && isset($GLOBALS['groupdav']) && is_a($GLOBALS['groupdav'],'groupdav'))
		{
			$GLOBALS['groupdav']->_http_status = '401 Unauthorized';	// to correctly log it
			if ($GLOBALS['egw_info']['server']['exception_show_trace'])
			{
				$GLOBALS['groupdav']->log_request("\n".$e->getTraceAsString()."\n");
			}
			else
			{
				$GLOBALS['groupdav']->log_request();
			}
		}
		if (is_object($GLOBALS['egw']))
		{
			common::egw_exit();
		}
		exit;
	}
}
