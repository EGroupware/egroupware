#!/usr/bin/python

# $Id$

from xmlrpclib import *
import sys

server = Server("http://www.phpgroupware.org/cvsdemo/xmlrpc.php");

try:
    print "Listing methods:\n"
    r = server.system.listMethods();
    print r

    print "\nTrying to login:\n"
    up = {'domain': 'default', 'username': 'demo', 'password': 'guest'}
    l = server.system.login(up);
    print l

    print "\nTrying name/age struct/array test:\n"
    # name/age example. this exercises structs and arrays
    a = [ {'name': 'Dave', 'age': 35}, {'name': 'Edd', 'age': 45 },
          {'name': 'Fred', 'age': 23}, {'name': 'Barney', 'age': 36 }]
    r = server.examples.sortByAge(a)
    print r

    print "\nTrying base 64 test:\n"
    # test base 64
    b = Binary("Mary had a little lamb She tied it to a pylon")
    b.encode(sys.stdout)
    r = server.examples.decode64(b)
    print r
    
    print "\nTrying to logout:\n"
    sk = {'sessionid': l['sessionid'], 'kp3': l['kp3']}
    r = server.system.logout(sk);
    print r

except Error, v:
    print "XML-RPC Error:",v
