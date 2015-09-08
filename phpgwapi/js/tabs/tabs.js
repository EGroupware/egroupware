/**
 * Tabs class for handling HTML/CSS tabs
 *
 * Copyright (C) 2003 Dipl.-Inform. Kai Hofmann and probusiness AG
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * Contact information:
 * Dipl.-Inform. Kai Hofmann
 * Arberger Heerstr. 92
 * 28307 Bremen
 * Germany
 *
 *
 * probusiness AG
 * Expo-Plaza-Nr. 1
 * 30539 Hannover
 * Germany
 *
 *
 * @version 1.0
 * @author hofmann@hofmann-int.de
 *
 * @argument nrTabs Number of Tabs to handle
 * @argument activeCSSclass CSS class name for active tabs (display:inline)
 * @argument inactiveCSSclass CSS class name for inactive tabs (display:none)
 * @argument HTMLtabID HTML ID name prefix that would be used with the tab number as tab name.
 * @argument HTMLtabcontentID HTML ID prefix for the tab content used with the tab number
 * @argument HTMLtabselectorID HTML ID prefix for a selectbox used to switch between the tabs
 * @argument HTMLtabradioID HTML ID prefix for radio button input fields used to switch between the tabs
 * @argument tabPageKey URL parameter name to use for setting/getting the actual tab
 */
function Tabs(nrTabs,activeCSSclass,inactiveCSSclass,HTMLtabID,HTMLtabcontentID,HTMLtabselectorID,HTMLtabradioID,tabPageKey)
 {
  this.nrTabs            = nrTabs;
  this.activeCSSclass    = activeCSSclass;
  this.inactiveCSSclass  = inactiveCSSclass;
  this.HTMLtabID         = HTMLtabID;
  this.HTMLtabcontentID  = HTMLtabcontentID;
  this.HTMLtabselectorID = HTMLtabselectorID;
  this.HTMLtabradioID    = HTMLtabradioID;
  this.tabPageKey        = tabPageKey;

  if (typeof(_tabs_prototype_called) == 'undefined')
   {
    _tabs_prototype_called        = true;
    Tabs.prototype.setActive      = setActive;
    Tabs.prototype.setInactive    = setInactive;
    Tabs.prototype.isActive       = isActive;
    Tabs.prototype.getActive      = getActive;
    Tabs.prototype.disableAll     = disableAll;
    Tabs.prototype.display        = display;
    Tabs.prototype.changeToActive = changeToActive;
    Tabs.prototype.init           = init;
   }


  /**
   * Set tab as active
   *
   * @argument tabnr The tab number (1-nrTabs) of the tab that should be active
   */
  function setActive(tabnr)
   {
    if ((tabnr > 0) && (tabnr <= this.nrTabs))
     {
      if(document.getElementById(this.HTMLtabID   + tabnr))
        document.getElementById(this.HTMLtabID   + tabnr).className = this.activeCSSclass;
      if(document.getElementById(this.HTMLtabcontentID + tabnr))
        document.getElementById(this.HTMLtabcontentID + tabnr).className = this.activeCSSclass;

      if(document.getElementById(this.HTMLtabselectorID))
        document.getElementById(this.HTMLtabselectorID).selectedIndex = tabnr-1;
      if(document.getElementById(this.HTMLtabradioID   + tabnr))
        document.getElementById(this.HTMLtabradioID   + tabnr).checked = true;
     }
   }



  /**
   * Set tab as inactive
   *
   * @argument tabnr The tab number (1-nrTabs) of the tab that should be inactive
   */
  function setInactive(tabnr)
   {
    if ((tabnr > 0) && (tabnr <= this.nrTabs))
     {
      if(document.getElementById(this.HTMLtabID        + tabnr))
        document.getElementById(this.HTMLtabID        + tabnr).className = this.inactiveCSSclass;
      if(document.getElementById(this.HTMLtabcontentID + tabnr))
        document.getElementById(this.HTMLtabcontentID + tabnr).className = this.inactiveCSSclass;
     }
   }


  /**
   * Test if tab is active
   *
   * @argument tabnr The tab number (1-nrTabs) of the tab that should be tested
   * @returns boolean - true if tab is active, false otherwise
   */
  function isActive(tabnr)
   {
    return(document.getElementById(HTMLtabID + tabnr).className == this.activeCSSclass);
   }


  /**
   * Get the active tab number
   *
   * @returns Tab (1-nrTabs) that is currently active or 0 if non is active.
   */
  function getActive()
   {
    for (i = 1; i <= this.nrTabs; ++i)
     {
      if (this.isActive(i))
       {
        return(i);
       }
     }
    return(0);
   }


  /**
   * Disable all tabs
   */
  function disableAll()
   {
    for (i = 1; i <= this.nrTabs; ++i)
     {
      this.setInactive(i);
     }
   }


  /**
   * Disable all tabs and then display the tab number given
   *
   * @argument tabnr Tab number to display
   */
  function display(tabnr)
   {
     for (i = 1; i <= this.nrTabs; ++i)
     {
      if (i == tabnr)
       this.setActive(tabnr);
      else
       this.setInactive(i);
     }
   }


  /**
   * Loop over all tabs - switch off currently active tabs and display the new tab
   *
   * @argument tabnr Tab number to display
   */
  function changeToActive(tabnr)
   {
    for (i = 1; i <= this.nrTabs; ++i)
     {
      if (i == tabnr)
       {
        if (!this.isActive(i))
         {
          this.setActive(i);
         }
       }
      else
       {
        if (this.isActive(i))
         {
          this.setInactive(i);
         }
       }
     }
   }


  /**
   * Determine active tab from url parameter or selector and display it.
   */
  function init()
   {
    var tab = 0;
    // this line is not working in IE 6
    //var regexp = new RegExp('(^|&)' + this.tabPageKey + '=[0-9]{1,2}');
    var regexp = new RegExp(this.tabPageKey + '=[0-9]{1,2}');
    var urlparams = window.location.search;
    var urlparamstart = urlparams.search(regexp);

    // getting the active tab from the tabPageKey (url/get-var) if set
    if (this.tabPageKey && urlparamstart > -1)
     {
       urlparamstart = urlparamstart + ((urlparams[urlparamstart] == '&') ? 1 : 0);
       var urlparam = urlparams.substr(urlparamstart,urlparams.length - urlparamstart);
       var pos = urlparam.indexOf("&");
       if (pos > -1)
        {
         urlparam = urlparam.substr(0,pos);
        }
       pos = urlparam.indexOf("=");
       if (pos > -1)
        {
         var urlparamvalue = urlparam.substr(pos + 1,urlparam.length - (pos + 1));
         tab = urlparamvalue;
        }
     }
    else
     {
      // getting the active tab from the selector if set
      // the check for != '' is needed for Konqueror
      if(document.getElementById(this.HTMLtabselectorID) && this.HTMLtabselectorID != '')
       tab = document.getElementById(this.HTMLtabselectorID).selectedIndex+1;
      else
       tab = 1;
     }
    if ((tab <= 0) || (tab > this.nrTabs))
     {
      tab = 1;
     }
    this.display(tab);
   }
 }
