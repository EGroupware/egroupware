#!/bin/bash
################################################################################
### Tool to check for PHP Syntax errors
###
### Usage: doc/php_syntax_check [file or directory, defaults to whole egrouware]
###
### Will output all PHP Fatal, Parse errors and also Deprecated incl. filename
###
### Exit-status: 0 on no error, but maybe Deprecated warnings, 1 on error
###
### Use PHP environment variable to point to a certain PHP binary.
################################################################################

find ${@-$(dirname $0)} -name '*.php' -exec ${PHP:-php} -l {} \; 2>&1 | \
	# only show errors and PHP Deprecated, no success messages
	egrep '^(PHP|Parse error)' | \
	# suppress everything in vendor, as they need to be solved by the vendor
	egrep -v 'vendor/' | \
	# output everything to stderr
	tee /dev/fd/2 | \
	perl -pe 'END { exit $status } $status=1 if /^(PHP Fatal|(PHP )?Parse error)/;'  > /dev/null
