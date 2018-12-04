<?php
/**
 * EGroupware - Calendar setup
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$phpgw_baseline = array(
	'egw_cal' => array(
		'fd' => array(
			'cal_id' => array('type' => 'auto','nullable' => False,'comment' => 'calendar id'),
			'cal_uid' => array('type' => 'ascii','precision' => '255','nullable' => False,'comment' => 'unique id of event(-series)'),
			'cal_owner' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'event owner / calendar'),
			'cal_category' => array('type' => 'ascii','meta' => 'category','precision' => '64','comment' => 'category id(s)'),
			'cal_modified' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'ts of last modification'),
			'cal_priority' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '2','comment' => 'priority: 1=Low, 2=Normal, 3=High'),
			'cal_public' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '1','comment' => '1=public, 0=private event'),
			'cal_title' => array('type' => 'varchar','precision' => '255','nullable' => False,'comment' => 'title of event'),
			'cal_description' => array('type' => 'varchar','precision' => '16384','comment' => 'description'),
			'cal_location' => array('type' => 'varchar','precision' => '255','comment' => 'location'),
			'cal_reference' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0','comment' => 'cal_id of series for exception'),
			'cal_modifier' => array('type' => 'int','meta' => 'user','precision' => '4','comment' => 'user who last modified event'),
			'cal_non_blocking' => array('type' => 'int','precision' => '2','default' => '0','comment' => '1 for non-blocking events'),
			'cal_special' => array('type' => 'int','precision' => '2','default' => '0'),
			'cal_etag' => array('type' => 'int','precision' => '4','default' => '0','comment' => 'etag for optimistic locking'),
			'cal_creator' => array('type' => 'int','meta' => 'user','precision' => '4','nullable' => False,'comment' => 'creating user'),
			'cal_created' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'creation time of event'),
			'cal_recurrence' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'default' => '0','comment' => 'cal_start of original recurrence for exception'),
			'tz_id' => array('type' => 'int','precision' => '4','comment' => 'key into egw_cal_timezones'),
			'cal_deleted' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'ts when event was deleted'),
			'caldav_name' => array('type' => 'ascii','precision' => '260','comment' => 'name part of CalDAV URL, if specified by client'),
			'range_start' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'startdate (of range)'),
			'range_end' => array('type' => 'int','meta' => 'timestamp','precision' => '8','comment' => 'enddate (of range, UNTIL of RRULE)')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array('cal_uid','cal_owner','cal_modified','cal_reference','cal_deleted','caldav_name'),
		'uc' => array()
	),
	'egw_cal_repeats' => array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'recur_type' => array('type' => 'int','precision' => '2','nullable' => False),
			'recur_interval' => array('type' => 'int','precision' => '2','default' => '1'),
			'recur_data' => array('type' => 'int','precision' => '2','default' => '1')
		),
		'pk' => array('cal_id'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_cal_user' => array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_recur_date' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'default' => '0'),
			'cal_user_type' => array('type' => 'ascii','precision' => '1','nullable' => False,'default' => 'u','comment' => 'u=user, g=group, c=contact, r=resource, e=email'),
			'cal_user_id' => array('type' => 'ascii','meta' => array("cal_user_type='u'" => 'account'),'precision' => '32','nullable' => False,'comment' => 'id or md5(email-address) for type=e'),
			'cal_status' => array('type' => 'ascii','precision' => '1','default' => 'A','comment' => 'U=unknown, A=accepted, R=rejected, T=tentative'),
			'cal_quantity' => array('type' => 'int','precision' => '4','default' => '1','comment' => 'only for certain types (eg. resources)'),
			'cal_role' => array('type' => 'ascii','precision' => '64','default' => 'REQ-PARTICIPANT','comment' => 'CHAIR, REQ-PARTICIPANT, OPT-PARTICIPANT, NON-PARTICIPANT, X-CAT-$cat_id'),
			'cal_user_modified' => array('type' => 'timestamp','default' => 'current_timestamp','comment' => 'automatic timestamp of last update'),
			'cal_user_auto' => array('type' => 'auto','nullable' => False),
			'cal_user_attendee' => array('type' => 'varchar','precision' => '255','comment' => 'email or json object with attr. cn, url, ...')
		),
		'pk' => array('cal_user_auto'),
		'fk' => array(),
		'ix' => array('cal_user_modified',array('cal_user_type','cal_user_id')),
		'uc' => array(array('cal_id','cal_recur_date','cal_user_type','cal_user_id'))
	),
	'egw_cal_extra' => array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_extra_name' => array('type' => 'varchar','meta' => 'cfname','precision' => '40','nullable' => False),
			'cal_extra_value' => array('type' => 'varchar','meta' => 'cfvalue','precision' => '16384','nullable' => False,'default' => '')
		),
		'pk' => array('cal_id','cal_extra_name'),
		'fk' => array(),
		'ix' => array(),
		'uc' => array()
	),
	'egw_cal_dates' => array(
		'fd' => array(
			'cal_id' => array('type' => 'int','precision' => '4','nullable' => False),
			'cal_start' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'starttime in server time'),
			'cal_end' => array('type' => 'int','meta' => 'timestamp','precision' => '8','nullable' => False,'comment' => 'endtime in server time'),
			'recur_exception' => array('type' => 'bool','nullable' => False,'default' => '','comment' => 'date is an exception')
		),
		'pk' => array('cal_id','cal_start'),
		'fk' => array(),
		'ix' => array(array('recur_exception','cal_id')),
		'uc' => array()
	),
	'egw_cal_timezones' => array(
		'fd' => array(
			'tz_id' => array('type' => 'auto','nullable' => False),
			'tz_tzid' => array('type' => 'ascii','precision' => '128','nullable' => False),
			'tz_alias' => array('type' => 'int','precision' => '4','comment' => 'tz_id for data'),
			'tz_latitude' => array('type' => 'int','precision' => '4'),
			'tz_longitude' => array('type' => 'int','precision' => '4'),
			'tz_component' => array('type' => 'ascii','precision' => '8192','comment' => 'iCal VTIMEZONE component')
		),
		'pk' => array('tz_id'),
		'fk' => array(),
		'ix' => array('tz_alias'),
		'uc' => array('tz_tzid')
	)
);
