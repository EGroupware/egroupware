#!/bin/bash
################################################################################################
###  EGroupware - add encoding, eTempalte2 DTD and svn propset svn:keywords Id to all eTemplates
###
###  @link http://www.egroupware.org
###  @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
###  @author Ralf Becker <rb@stylite.de>
###  @copyright 2015 by Ralf Becker <rb@stylite.de>
###  @version $Id$
################################################################################################

cd `dirname $0`
cd ..

for f in */templates/default/*.xet
do
	if svn info $f > /dev/null 2>&1
	then
		grep -q '<?xml version="1.0" encoding="UTF-8"?>' $f || {
			echo "$f	encoding=\"UTF-8\" added"
			sed -i .bak 's/<?xml version="1.0"?>/<?xml version="1.0" encoding="UTF-8"?>/' $f
		}
		grep -q '\$Id' $f || {
			echo "$f	\$Id\$ added"
			sed -i .bak 's/<?xml version="1.0" encoding="UTF-8"?>/<?xml version="1.0" encoding="UTF-8"?>\
<!-- $Id$ -->/' $f
		}
		grep -q '\$Id\$' $f || {
			echo "$f	\$Id\$ needs svn propset"
			svn propset svn:keywords Id $f
		}
		grep -q DOCTYPE $f || {
			echo "$f	DOCTYPE missing"
			sed -i .bak 's/<?xml version="1.0" encoding="UTF-8"?>/<?xml version="1.0" encoding="UTF-8"?>\
<!DOCTYPE overlay PUBLIC "-\/\/Stylite AG\/\/eTemplate 2\/\/EN" "http:\/\/www.egroupware.org\/etemplate2.dtd">/' $f
		}
	fi
done
