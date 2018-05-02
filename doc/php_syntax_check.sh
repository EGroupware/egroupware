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

cd ${1:-$(dirname $0)/..}

find ${@-.} -name '*.php' -exec ${PHP:-php} -l {} \; 2>&1 | \
	# only show errors and PHP Deprecated, no success messages
	egrep '^(PHP|Parse error)' | \
	# suppress PHP Deprecated in vendor, as they need to be solved by the vendor
	egrep -v '^PHP Deprecated.*/vendor/' | \
	# output everything to stderr
	tee /dev/fd/2 | \
	# exclude several known problems, to be able to find new ones
	# exclude old / not used PEAR Autoloader giving PHP Fatal error:  Method PEAR_Autoloader::__call() must take exactly 2 arguments
	grep -v 'vendor/pear-pear.php.net/PEAR/PEAR/Autoloader.php' | \
	# exclude composer conditional included autoload_static.php, as it requires PHP 5.6+
	grep -v 'vendor/composer/autoload_static.php' | \
	# exclude vendor/phpunit it shows many PHP Parse errors in PHP < 7.0
	grep -v 'vendor/phpunit' | \
	# suppress PHP Parse errors in PHP < 7.0 in dependency of phpunit: phpspec/prophecy
	grep -v 'vendor/phpspec/prophecy' | \
	# phpFreeChat does not work with PHP7
	grep -v 'phpfreechat/phpfreechat/' | \
	# not used part of ADOdb give PHP Fatal error: Cannot unset $this
	grep -v 'adodb-xmlschema' | \
	perl -pe 'END { exit $status } $status=1 if /^(PHP Fatal|(PHP )?Parse error)/;'  > /dev/null
