#!/bin/bash
#
# This script merges multiple events with same uid existing due to importing each users calendar without participant or chair information.
#
# The event-owner is kinda random, as events are merged in the one with the smallest cal_id / earliest imported!
#

DB=${1:-egroupware}

# merge multiple single events or series masters with same uid
mysql $DB --execute "SELECT min(cal_id),cal_uid FROM egw_cal where cal_reference=0 group by cal_uid having count(*) > 1" --skip-column-names |
while read ID UUID; do
#echo "#$ID: $UUID"
cat << EOF | mysql $DB
update ignore egw_cal_user set cal_id=$ID where cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference=0);
delete from egw_cal_user where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference=0);
update ignore egw_cal_dates set cal_id=$ID where cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference=0);
delete from egw_cal_dates where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference=0);
delete from egw_cal_repeats where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference=0);
update egw_cal set cal_reference=$ID where cal_reference<>0 and cal_uid='$UUID';
delete from egw_cal where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference=0);
EOF
done

# merge multiple recurrences/exceptions at same time
mysql $DB --execute "SELECT min(cal_id),cal_uid,cal_recurrence FROM egw_cal where cal_reference<>0 group by cal_uid,cal_recurrence having count(*) > 1" --skip-column-names |
while read ID UUID RECURRENCE; do
#echo "#$ID: $UUID: RECURRENCE"
cat << EOF | mysql $DB
update ignore egw_cal_user set cal_id=$ID where cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference<>0 and cal_recurrence=$RECURRENCE);
delete from egw_cal_user where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference<>0 and cal_recurrence=$RECURRENCE);
delete from egw_cal_dates where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference<>0 and cal_recurrence=$RECURRENCE);
delete from egw_cal where cal_id<>$ID AND cal_id in (select cal_id from egw_cal where cal_uid='$UUID' and cal_reference<>0 and cal_recurrence=$RECURRENCE);
EOF
done