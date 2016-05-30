#!/bin/bash
################################################################################
### Tool to check for PHP Syntax errors
### Usage: doc/php_syntax_check [file or directory, defaults to whole egrouware]
### Will output all PHP Fatal, Parse erros and also Deprecated incl. filename
### Exit-status: 0 on no error, but maybe Deprecated warnings, 1 on error
################################################################################

cd `dirname $0`
cd ..

# exclude old / not used PEAR Autoloader giving PHP Fatal error:  Method PEAR_Autoloader::__call() must take exactly 2 arguments
# exclude composer conditional included autoload_static.php, as it requires PHP 5.6+

find ${@-.} -name '*.php' \
	-a ! -path '*vendor/pear-pear.php.net/PEAR/PEAR/Autoloader.php' \
	-a ! -path '*vendor/composer/autoload_static.php' \
	-exec php -l {} \; 2>&1 | \
	#grep -v 'No syntax errors detected in' | \
	egrep '^(PHP|Parse error)' | \
	perl -pe 'END { exit $status } $status=1 if /^(PHP Fatal|(PHP )?Parse error)/;'
