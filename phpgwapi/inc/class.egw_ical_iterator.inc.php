<?php
/**
 * EGroupware: Iterator for iCal files
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-15 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

// required for tests at the end of this file (run if file called directly)
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login',
			'nonavbar'   => 'true',
		),
	);
	include('../../header.inc.php');
}

/**
 * Iterator for iCal files
 *
 * try {
 * 		$ical_file = fopen($path,'r');
 * 		$ical_it = new egw_ical_iterator($ical_file,'VCALENDAR');
 * }
 * catch (Exception $e)
 * {
 * 		// could not open $path or no valid iCal file
 * }
 * foreach($ical_it as $vevent)
 * {
 * 		// do something with $vevent
 * }
 * fclose($ical_file)
 */
class egw_ical_iterator extends Horde_Icalendar implements Iterator
{
	/**
	 * File we work on
	 *
	 * @var resource
	 */
	protected $ical_file;

	/**
	 * Base name of container, eg. 'VCALENDAR', as passed to the constructor
	 *
	 * @var string
	 */
	protected $base;

	/**
	 * Does ical_file contain a container: BEGIN:$base ... END:$base
	 *
	 * @var boolean
	 */
	protected $container;

	/**
	 * Charset passed to the constructor
	 *
	 * @var string
	 */
	protected $charset;

	/**
	 * Current component, as it get's returned by current() method
	 *
	 * @var Horde_Icalendar
	 */
	protected $component;

	/**
	 * Callback to call with component in current() method, if returning false, item get's ignored
	 *
	 * @var callback
	 */
	protected $callback;

	/**
	 * Further parameters for the callback, 1. parameter is component
	 *
	 * @var array
	 */
	protected $callback_params = array();

	/**
	 * Constructor
	 *
	 * @param string|resource $ical_file file opened for reading or string
	 * @param string $base ='VCALENDAR' container
	 * @param string $charset =null
	 * @param callback $callback =null callback to call with component in current() method, if returning false, item get's ignored
	 * @param array $callback_params =array() further parameters for the callback, 1. parameter is component
	 */
	public function __construct($ical_file,$base='VCALENDAR',$charset=null,$callback=null,array $callback_params=array())
	{
		// call parent constructor
		parent::__construct();

		$this->base = $base;
		$this->charset = $charset;
		if (is_callable($callback))
		{
			$this->callback = $callback;
			$this->callback_params = $callback_params;
		}
		if (is_string($ical_file))
		{
			$this->ical_file = fopen('php://temp', 'w+');
			fwrite($this->ical_file, $ical_file);
			fseek($this->ical_file, 0, SEEK_SET);
		}
		else
		{
			$this->ical_file = $ical_file;
		}
		if (!is_resource($this->ical_file))
		{
			throw new egw_exception_wrong_parameter(__METHOD__.'($ical_file, ...) NO resource! $ical_file='.substr(array2string($ical_file),0,100));
		}
	}

	/**
	 * Stack with not yet processed lines
	 *
	 * @var string
	 */
	protected $unread_lines = array();

	/**
	 * Read and return one line from file (or line-buffer)
	 *
	 * We do NOT handle folding, that's done by Horde_Icalendar and not necessary for us as BEGIN: or END: component is never folded
	 *
	 * @return string|boolean string with line or false if end-of-file or end-of-container reached
	 */
	protected function read_line()
	{
		if ($this->unread_lines)
		{
			$line = array_shift($this->unread_lines);
		}
		elseif(feof($this->ical_file))
		{
			$line = false;
		}
		else
		{
			$line = fgets($this->ical_file);
		}
		// check if end of container reached
		if ($this->container && $line && substr($line,0,4+strlen($this->base)) === 'END:'.$this->base)
		{
			$this->unread_line($line);	// put back end-of-container, to continue to return false
			$line = false;
		}
		//error_log(__METHOD__."() returning ".($line === false ? 'FALSE' : "'$line'"));

		return $line;
	}

	/**
	 * Take back on line, already read with read_line
	 *
	 * @param string $line
	 */
	protected function unread_line($line)
	{
		//error_log(__METHOD__."('$line')");
		array_unshift($this->unread_lines,$line);
	}

	/**
	 * Return the current element
	 *
	 * @return Horde_Icalendar or whatever a given callback returns
	 */
	public function current()
	{
		//error_log(__METHOD__."() returning a ".gettype($this->component));
		if ($this->callback)
		{
			$ret = is_a($this->component,'Horde_Icalendar');
			do {
				if ($ret === false) $this->next();
				if (!is_a($this->component,'Horde_Icalendar')) return false;
				$params = $this->callback_params;
				array_unshift($params,$this->component);
			}
			while(($ret = call_user_func_array($this->callback,$params)) === false);

			return $ret;
		}
		return $this->component;
	}

	/**
	 * Return the key of the current element
	 *
	 * @return int|string
	 */
	public function key()
	{
		//error_log(__METHOD__."() returning ".$this->component->getAttribute('UID'));
		return $this->component ? $this->component->getAttribute('UID') : false;
	}

	/**
	 * Move forward to next component (called after each foreach loop)
	 */
	public function next()
	{
		unset($this->component);

		while (($line = $this->read_line()) && substr($line,0,6) !== 'BEGIN:')
		{
			// ignore it
		}
		if ($line === false)	// end-of-file or end-of-container
		{
			$this->component = false;
			return;
		}
		$type = substr(trim($line),6);

		//error_log(__METHOD__."() found $type component");

		$data = $line;
		while (($line = $this->read_line()) && substr($line,0,4+strlen($type)) !== 'END:'.$type)
		{
			$data .= $line;
		}
		$data .= $line;

		$this->component = Horde_Icalendar::newComponent($type, $this);
		//error_log(__METHOD__."() this->component = Horde_Icalendar::newComponent('$type', \$this) = ".array2string($this->component));
        if ($this->component === false)
        {
        	error_log(__METHOD__."() Horde_Icalendar::newComponent('$type', \$this) returned FALSE");
        	return;
            //return PEAR::raiseError("Unable to create object for type $type");
        }
		//error_log(__METHOD__."() about to call parsevCalendar('".substr($data,0,100)."...','$type','$this->charset')");
		$this->component->parsevCalendar($data, $type, $this->charset);

		// VTIMEZONE components are NOT returned, they are only processed internally
		if ($type == 'VTIMEZONE')
		{
			$this->addComponent($this->component);
			// calling ourself recursive, to set next non-VTIMEZONE component
			$this->next();
		}
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind()
	{
		fseek($this->ical_file,0,SEEK_SET);

		// advance to begin of container
		while(($line = $this->read_line()) && substr($line,0,6+strlen($this->base)) !== 'BEGIN:'.$this->base)
		{

		}
		// if no container start found --> use whole file (rewind) and set container marker
		if (!($this->container = $line !== false))
		{
			fseek($this->ical_file,0,SEEK_SET);
		}
		//error_log(__METHOD__."() $this->base container ".($this->container ? 'found' : 'NOT found'));

		$data = $line;
		// advance to first component
		while (($line = $this->read_line()) && substr($line,0,6) !== 'BEGIN:')
		{
			$matches = null;
			if (preg_match('/^VERSION:(\d\.\d)\s*$/ism', $line, $matches))
			{
				// define the version asap
				$this->setAttribute('VERSION', $matches[1]);
			}
			$data .= $line;
		}
		// fake end of container to get it parsed by Horde code
		if ($this->container)
		{
			$data .= "END:$this->base\n";
			//error_log(__METHOD__."() about to call this->parsevCalendar('$data','$this->base','$this->charset')");
			$this->parsevCalendar($data,$this->base,$this->charset);
		}
		if ($line) $this->unread_line($line);

		// advance to first element
		$this->next();
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid ()
	{
		//error_log(__METHOD__."() returning ".(is_a($this->component,'Horde_Icalendar') ? 'TRUE' : 'FALSE').' get_class($this->component)='.get_class($this->component));
		return is_a($this->component,'Horde_Icalendar');
	}
}

// some tests run if file called directly
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$ical_file = 'BEGIN:VCALENDAR
PRODID:-//Microsoft Corporation//Outlook 12.0 MIMEDIR//EN
VERSION:2.0
METHOD:PUBLISH
X-CALSTART:19980101T000000
X-WR-RELCALID:{0000002E-1BB2-8F0F-1203-47B98FEEF211}
X-WR-CALNAME:Fxlxcxtxsxxxxxxxxxxxxx
X-PRIMARY-CALENDAR:TRUE
X-OWNER;CN="Fxlxcxtxsxxxxxxx":mailto:xexixixax.xuxaxa@xxxxxxxxxxxxxxx-berli
 n.de
X-MS-OLK-WKHRSTART;TZID="Westeuropäische Normalzeit":080000
X-MS-OLK-WKHREND;TZID="Westeuropäische Normalzeit":170000
X-MS-OLK-WKHRDAYS:MO,TU,WE,TH,FR
BEGIN:VTIMEZONE
TZID:Westeuropäische Normalzeit
BEGIN:STANDARD
DTSTART:16011028T030000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010325T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
ATTENDEE;CN=Vorstand;RSVP=TRUE:mailto:xoxsxaxd@xxxxxxxxxxxxxxx-berlin.de
ATTENDEE;CN=\'voxrxtx@xxxxxxxx.de\';RSVP=TRUE:mailto:voxrxtx@xxxxxxxx.de
ATTENDEE;CN=Pressestelle;RSVP=TRUE:mailto:xrxsxexxexlx@xxxxxxxxxxxxxxx-berl
 in.de
ATTENDEE;CN="Dxuxe Nxcxxlxxx";ROLE=OPT-PARTICIPANT;RSVP=TRUE:mailto:xjxkx.x
 ixkxxsxn@xxxxxxxxxxxxxxx-berlin.de
ATTENDEE;CN="Mxxxaxx Sxxäxxr";ROLE=OPT-PARTICIPANT;RSVP=TRUE:mailto:xixhxe
 x.xcxaxfxr@xxxxxxxxxxxxxxx-berlin.de
CLASS:PUBLIC
CREATED:20100408T232652Z
DESCRIPTION:\n
DTEND;TZID="Westeuropäische Normalzeit":20100414T210000
DTSTAMP:20100406T125856Z
DTSTART;TZID="Westeuropäische Normalzeit":20100414T190000
LAST-MODIFIED:20100408T232653Z
LOCATION:Axtx Fxuxrxaxhx\, Axex-Sxrxnxxxxxxxxxxxxxx
ORGANIZER;CN="Exixaxexhxxxxxxxxxxxräxx":mailto:xxx.xxxxxxxxxxx@xxxxxxxxxxx
 xxxx-berlin.de
PRIORITY:5
RECURRENCE-ID;TZID="Westeuropäische Normalzeit":20100414T190000
SEQUENCE:0
SUMMARY;LANGUAGE=de:Aktualisiert: LA  - mit Ramona
TRANSP:OPAQUE
UID:040000008200E00074C5B7101A82E00800000000D0AFE96CB462CA01000000000000000
 01000000019F8AF4D13C91844AA9CE63190D3408D
X-ALT-DESC;FMTTYPE=text/html:<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 3.2//E
 N">\n<HTML>\n<HEAD>\n<META NAME="Generator" CONTENT="MS Exchange Server ve
 rsion 08.00.0681.000">\n<TITLE></TITLE>\n</HEAD>\n<BODY>\n<!-- Converted f
 rom text/rtf format -->\n<BR>\n\n</BODY>\n</HTML>
X-MICROSOFT-CDO-BUSYSTATUS:TENTATIVE
X-MICROSOFT-CDO-IMPORTANCE:1
X-MICROSOFT-DISALLOW-COUNTER:FALSE
X-MS-OLK-ALLOWEXTERNCHECK:TRUE
X-MS-OLK-APPTSEQTIME:20091111T085039Z
X-MS-OLK-AUTOSTARTCHECK:FALSE
X-MS-OLK-CONFTYPE:0
END:VEVENT
BEGIN:VEVENT
CLASS:PUBLIC
CREATED:20100331T125400Z
DTEND:20100409T110000Z
DTSTAMP:20100409T123209Z
DTSTART:20100409T080000Z
LAST-MODIFIED:20100331T125400Z
PRIORITY:5
SEQUENCE:0
SUMMARY;LANGUAGE=de:Marissa
TRANSP:OPAQUE
UID:AAAAAEyulq85HfZCtWDOITo5tZQHABE65KS0gg5Fu6X1g2z9eWUAAAAA3BAAABE65KS0gg5
 Fu6X1g2z9eWUAAAIQ6D0AAA==
X-MICROSOFT-CDO-BUSYSTATUS:BUSY
X-MICROSOFT-CDO-IMPORTANCE:1
X-MS-OLK-ALLOWEXTERNCHECK:TRUE
X-MS-OLK-AUTOSTARTCHECK:FALSE
X-MS-OLK-CONFTYPE:0
END:VEVENT
BEGIN:VEVENT
CLASS:PUBLIC
CREATED:20100331T124848Z
DTEND;VALUE=DATE:20100415
DTSTAMP:20100409T123209Z
DTSTART;VALUE=DATE:20100414
LAST-MODIFIED:20100331T124907Z
PRIORITY:5
SEQUENCE:0
SUMMARY;LANGUAGE=de:MELANIE wieder da
TRANSP:TRANSPARENT
UID:AAAAAEyulq85HfZCtWDOITo5tZQHABE65KS0gg5Fu6X1g2z9eWUAAAAA3BAAABE65KS0gg5
 Fu6X1g2z9eWUAAAIQ6DsAAA==
X-MICROSOFT-CDO-BUSYSTATUS:FREE
X-MICROSOFT-CDO-IMPORTANCE:1
X-MS-OLK-ALLOWEXTERNCHECK:TRUE
X-MS-OLK-AUTOSTARTCHECK:FALSE
X-MS-OLK-CONFTYPE:0
END:VEVENT
END:VCALENDAR
';
	common::egw_header();
	//$ical_file = fopen('/tmp/KalenderFelicitasKubala.ics');
	if (!is_resource($ical_file)) echo "<pre>$ical_file</pre>\n";
	//$calendar_ical = new calendar_ical();
	//$calendar_ical->setSupportedFields('file');
	$ical_it = new egw_ical_iterator($ical_file);//,'VCALENDAR','iso-8859-1',array($calendar_ical,'_ical2egw_callback'),array('Europe/Berlin'));
	foreach($ical_it as $uid => $vevent)
	{
		echo "$uid<pre>".print_r($vevent->toHash(), true)."</pre>\n";
	}
	if (is_resource($ical_file)) fclose($ical_file);
}