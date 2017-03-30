#!/bin/bash
#
# Usage: ./ad-migration.sh < user.csv | bash
#
# STDIN is csv with: <account_lid>,<account_id>,<AD-user>,<SID>,<RID>
#
# change following 2 lines to EGroupware user with admin rights and his password
#
ADMIN=sysop
PASSWD=PW

CHANGE=
while IFS=, read account_lid account_id ad_user SID RID rest
do
	if [ -n "$account_id" -a -n "$RID" ]
	then
		if [ $account_id -gt 0 ]
		then
			echo "admin/admin-cli.php --edit-user '$ADMIN,$PASSWD,$account_lid=$ad_user'"
		else
			echo "admin/admin-cli.php --edit-group '$ADMIN,$PASSWD,$account_lid=$ad_user'"
			RID=-$RID
		fi
		[ -n "$CHANGE" ] && CHANGE=$CHANGE,
		CHANGE=$CHANGE$account_id,$RID
	fi
done

echo "admin/admin-cli.php --change-account-id '$ADMIN,$PASSWD,$CHANGE'"
