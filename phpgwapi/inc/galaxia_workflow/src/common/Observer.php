<?php
//!! Observer
//! An abstract class implementing observer objects
/*!
  \abstract
  Methods to override: notify($event, $msg)
  This implements the Observer design pattern defining the Observer class.
  Observer objects can be "attached" to Observable objects to listen for
  a specific event.
  Example:
  
  $log = new Logger($logfile); //Logger extends Observer
  $foo = new Foo(); //Foo extends Observable
  $foo->attach('moo',$log); //Now $log observers 'moo' events in $foo class
  // of
  $foo->attach_all($log); // Same but all events are listened
*/

class Observer {
  ///This will be assigned by an observable object when attaching.
  var $_observerId='';
  
  function notify($event, $msg) {
    // do something...
  }
}
?>
