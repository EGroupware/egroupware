## Configure EGroupware for SSO via SAML with Univention

### SAML IdP need to be enabled, see [UCS Manual about login](https://docs.software-univention.de/manual/5.0/en/central-management-umc/login.html#central-management-umc-login)
    
* ```ucs-sso.<domain>``` need to resolve to one or more primary or secondary domain controllers
* if you use LetsEncrypt, you should add the above domain to your certificate
* UCS config registry variable ```portal/auth-mode``` has to be set to ```saml```
* portal server needs to be restarted: ```systemctl restart univention-portal-server.service```

### EGroupware needs to be configured for SAML via Setup (```https://egw.example.org/egroupware/setup/```)
  * Login into setup with user ```admin``` and the password from ```/var/lib/egroupware/egroupware-docker-install.log```
  * Go to [Edit current configuration]
  
<html>
<table border="0" align="center" cellspacing="0" width="90%">
<tbody>
   <tr class="th">
    <td colspan="2"><b>If using SAML 2.0 / Shibboleth / SimpleSAMLphp:</b></td>
   </tr>

   <tr class="row_off">
    <td>Label to display as option on login page:<br>or leave empty and select SAML as authentication type above for single sign on</td>
    <td>Test SSO</td>
   </tr>

   <tr class="row_on">
    <td>Identity Provider:<br>You can specify multiple IdP on separate lines.</td>
    <td>https://ucs.example.org/simplesamlphp/saml2/idp/metadata.php</td>
   </tr>

   <tr class="row_off">
    <td>
     Metadata:<br/>
     refresh [ just now   v]
    </td>
    <td>
     https://ucs.example.org/simplesamlphp/saml2/idp/metadata.php
    </td>
   </tr>
   <tr class="row_on">
    <td>Certificate Metadata is signed with: (Will be downloaded once, unless changed.)</td>
    <td>
        https://ucs.example.org/simplesamlphp/saml2/idp/certificate
    </td>
   </tr>
   <tr class="row_off">
    <td>Result data to use as username:</td>
    <td>
     [ uid   v]
    </td>
   </tr>
   <tr class="row_on">
    <td>Result data to add or remove extra membership:</td>
    <td>
     [ eduPersonAffiliation  v]
    </td>
   </tr>
   <tr class="row_off">
    <td>Result values (comma-separated) and group-name to add or remove:</td>
    <td>
     Staff<br/>
     Teachers
    </td>
   </tr>
   <tr class="row_on">
    <td>Allow SAML logins to join existing accounts:<br>(Requires SAML optional on login page and user to specify username and password)</td>
    <td>
     [ No     v]
    </td>
   </tr>
   <tr class="row_off">
    <td>Match SAML usernames to existing ones (use strings or regular expression):</td>
    <td>
      
   </td>
   </tr>
   <tr class="row_on" height="25">
    <td>Some information for the own Service Provider metadata:</td>
    <td><a href="/egroupware/saml/module.php/saml/sp/metadata.php/default-sp">Metadata URL</a></td>
   </tr>
   <tr class="row_off">
    <td>Name for Service Provider:</td>
    <td>EGroupware</td>
   </tr>
   <tr class="row_on">
    <td>Technical contact:</td>
    <td>
     Ralf Becker<br/>
     rb@egroupware.org
    </td>
   </tr>
</tbody></table>
</html>

> For Univention the Metadata-URL is also the ID of the IdP!

### Configure EGroupware as service-provide in your UCS domain: **Domain > LDAP directory > SAML service provider**
* Add: Type: SAML service provider

```
X Service provider activation status
Service provider identifier: https://egw.example.org/egroupware/saml/module.php/saml/sp/metadata.php/default-sp
Respond to this service provider URL after login: https://egw.example.org/egroupware/saml/module.php/saml/sp/saml2-acs.php/default-sp
Single logout URL for this service provider: https://egw.example.org/egroupware/saml/module.php/saml/sp/saml2-logout.php/default-sp
Format of NameID attribute:
Name of the attribute that is used as NameID: uid
Name of the organization for this service provider: EGroupware
Description of this service provider:
X Enable signed Logouts
```
* After saving the above, you have to edit the `Extended Settings` of your new Service Provide
```
X Allow transfering LDAP attributes to the Service Provider
LDAP Attribute Name: uid
LDAP Attribute Name: mailPrimaryAddress
LDAP Attribute Name: givenName
LDAP Attribute Name: sn
```

* If you want an automatic SAML SingleSignOn, eg. by clicking on an EGroupware tile in the portal, 
you need to switch in Setup > Site configuration ```Authentication``` to ```SAML``` and remove the
```Test SSO``` label from the beginning of the SAML configuration.
* To be able to use a password login in the above case, you need to add the following to your DB:
```sql
INSERT INTO egw_config VALUES ('phpgwapi', 'univention_discovery', 'true');
```
&nbsp; &nbsp; &nbsp; &nbsp; Clear the cache and use the following URL: ```https://example.org/egroupware/login.php?auth=univention```

* Some useful links
    * [How does Single Sign-on work?](https://www.univention.com/blog-en/2021/08/how-does-single-sign-on-work-with-saml-and-openidconnect/)
    * [Reconfigure UCS Single Sign On](https://help.univention.com/t/reconfigure-ucs-single-sign-on/16161)
    * [Create an SSO Login for Applications to Groups](https://www.univention.com/blog-en/2020/07/sso-login-for-groups/)
    * [Adding a new external service provider](https://docs.software-univention.de/manual/5.0/en/domain-ldap/saml.html#domain-saml-additional-serviceprovider)

### Configure EMail access without password

> EGroupware normally use the session password to authenticate with the mail-server / Dovecot. If you use SSO (single sign on), EGroupware does not know your password and therefore can not pass it to the mail server.

* login via ssh as user root to your mailserver
* note the password from /etc/dovecot/master-users (secretpassword in the example below)
```
dovecotadmin:{PLAIN}secretpassword::::::
```
* add the following line to your /etc/dovecot/global-acls
```shell
echo "* user=dovecotadmin lra" >> /etc/dovecot/global-acls
doveadm reload
```
* login with a user that has EGroupware admin rights
* go to **Administration**, right-click on a user and select **mail-account**
* in IMAP tab fill in the credentials:
```
Admin user: dovecotadmin
Password:   secretpassword
            X Use admin credentials to connect without a session-password, e.g. for SSO
```
> Currently, there are two bugs, you need to work around:
> 1. EGroupware checks the above user/password as an IMAP user, so you need to additionally create him as UCS user with mail, in order to be able to store the dialog.
> 2. The account you use for testing, must NOT have any additional personal mail accounts, as you get an error in that case, when you open the mail app.
* log out and in again with SSO and check everything works
