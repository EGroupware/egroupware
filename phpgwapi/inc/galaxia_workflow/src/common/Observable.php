<?php
//!! Observable
//! An abstract class implementing observable objects
/*!
  \abstract
  Methods to override: NONE
  This class implements the Observer design pattern defining Observable
  objects, when a class extends Observable Observers can be attached to
  the class listening for some event. When an event is detected in any
  method of the derived class the method can call notifyAll($event,$msg)
  to notify all the observers listening for event $event.
  The Observer objects must extend the Observer class and define the
  notify($event,$msg) method.
*/
class Observable {
  var $_observers=Array();
  
  function Observable() {
  
  }
  
  /*!
   This method can be used to attach an object to the class listening for
   some specific event. The object will be notified when the specified
   event is triggered by the derived class.
  */
  function attach($event, &$obj)
  {
    if (!is_object($obj)) {
    	return false;
    }
    $obj->_observerId = uniqid(rand());
    $this->_observers[$event][$obj->_observerId] = &$obj;
  }
  
  /*!
   Attaches an object to the class listening for any event.
   The object will be notified when any event occurs in the derived class.
  */
  function attach_all(&$obj)
  {
    if (!is_object($obj)) {
    	return false;
    }
    $obj->_observerId = uniqid(rand());
    $this->_observers['all'][$obj->_observerId] = &$obj;
  }
  
  /*!
   Detaches an observer from the class.
  */
  function dettach(&$obj)
  {
  	if (isset($this->_observers[$obj->_observerId])) {
    	unset($this->_observers[$obj->_observerId]);
    }
  }
  
  /*!
  \protected
  Method used to notify objects of an event. This is called in the
  methods of the derived class that want to notify some event.
  */
  function notify_all($event, $msg)
  {
  	//reset($this->_observers[$event]);
  	if(isset($this->_observers[$event])) {
    	foreach ($this->_observers[$event] as $observer) {
    		$observer->notify($event,$msg);
    	}
    }
	if(isset($this->_observers['all'])) {
    	foreach ($this->_observers['all'] as $observer) {
    		$observer->notify($event,$msg);
    	}
    }
    
  } 

}
?>