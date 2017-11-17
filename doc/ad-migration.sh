#!/bin/bash
#
# Usage: ./ad-migration.sh < user.csv | bash
#
# STDIN is csv with: <account_lid>,<account_id>,<AD-user>,<SID>,<RID>
#
# <account_id> has to be negative number for groups (eg. -123 for account_id=123)!
#
# Following command should be used as startpoint for the list:
# mysql -B -e "select account_lid,CASE account_type WHEN 'u' THEN account_id ELSE -account_id END,account_lid FROM egw_accounts ORDER BY account_type!='u',account_lid" egroupware | sed 's/  /,/g'
# Change <AD-user> if not equal to <account_lid> and append ,,<RID> to each line.
#
# If migration to LDAP instead of AD use uidNumber (gidNumber for groups) as <RID>.
# <SID> is NOT used and can be empty.
#
# Change following 2 lines to an EGroupware user with admin rights and his password
#
ADMIN=sysop
PASSWD=PW

CHANGE=
while IFS=, read account_lid account_id ad_user SID RID rest
do
	if [ -n "$account_id" -a -n "$RID" ]
	then
		[ -z "$account_lid" -o -z "$ad_user" -o "$account_lid" = "$ad_user" ] && {
			echo -n "#"
		}
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
