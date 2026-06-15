# EGroupware API Documentation

## Overview

EGroupware's API is a comprehensive PHP framework for building web applications. It provides a robust foundation for database access, authentication, caching, and more. The API is designed with a PSR-4 autoloading structure and follows modern PHP practices (PHP 8.2+ required).

**Location:** `/api/src/`

**Total Files:** ~264 PHP files

---

## Architecture

### Core Entry Points

| File | Purpose |
|------|---------|
| `api/src/loader.php` | Main loader - initializes autoloader, security, exceptions |
| `api/src/loader/common.php` | Common functions, security checks, XSS protection |
| `api/src/autoload.php` | PSR-4 autoloader for namespaced classes |
| `api/src/Egw.php` | Main framework class - instantiates all sub-classes |

### Directory Structure

```
api/src/
├── Api/                    # (legacy - mostly empty)
├── Accounts/               # User account management
│   ├── Ads.php            # Active Directory support
│   ├── Import.php         # Account import functionality
│   ├── Ldap.php           # LDAP account source
│   └── Sql.php            # SQL-based accounts
├── Auth/                   # Authentication backends
│   ├── Ads.php            # Active Directory auth
│   ├── Apppassword.php    # Application passwords
│   ├── Cas.php            # CAS single sign-on
│   ├── Http.php           # HTTP Basic auth
│   ├── Ldap.php           # LDAP authentication
│   ├── Mail.php           # Email-based auth
│   ├── Multiple.php       # Multiple auth methods
│   ├── Pam.php            # PAM authentication
│   ├── Saml.php           # SAML SSO
│   ├── Sql.php            # SQL database auth
│   ├── Token.php          # Token-based auth
│   └── ...
├── Cache/                  # Caching infrastructure
│   ├── Apc.php            # APC cache provider
│   ├── Apcu.php           # APC user cache provider
│   ├── Base.php           # Base cache provider class
│   ├── Files.php          # File-based cache
│   ├── Memcache.php       # Memcache provider
│   ├── Memcached.php      # Memcached provider
│   └── Provider.php       # Cache provider interface
├── CalDAV/                 # CalDAV protocol implementation
│   ├── Handler.php        # CalDAV request handler
│   ├── JsCalendar.php     # JavaScript calendar integration
│   ├── Principals.php     # CalDAV principals
│   └── Sync.php           # Synchronization
├── Contacts/               # Contact management
│   ├── Ads.php            # Active Directory contacts
│   ├── Ldap.php           # LDAP contacts
│   ├── Sql.php            # SQL contacts
│   ├── Storage.php        # Contact storage
│   └── ...
├── Db/                     # Database abstraction layer
│   ├── Backup.php         # Database backup utilities
│   ├── Deprecated.php     # Deprecated methods
│   ├── Exception/         # Database exceptions
│   ├── Pdo.php            # PDO wrapper
│   └── Schema.php         # Database schema management
├── Egw/                    # EGroupware core
│   └── Applications.php   # Application registry
├── Framework/              # Web framework
│   ├── Bundle.php         # JavaScript bundling
│   ├── CssIncludes.php    # CSS management
│   ├── Extras.php         # Framework base
│   ├── IncludeMgr.php     # JavaScript inclusion manager
│   ├── Login.php          # Login handling
│   ├── Updates.php        # Update checks
│   └── ...
├── Header/                 # HTTP header utilities
│   ├── Authenticate.php   # Auth header parsing
│   ├── Content.php        # Content-Type handling
│   ├── ContentSecurityPolicy.php
│   ├── Http.php           # HTTP utilities
│   ├── Referer.php        # Referer checking
│   └── UserAgent.php      # User agent detection
├── Json/                   # JSON response handling
├── Ldap/                   # LDAP utilities
├── Mail/                   # Email handling
│   ├── Imap/              # IMAP client
│   ├── Smime/             # S/MIME support
│   ├── Smtp/              # SMTP client
│   └── Sieve/             # Sieve filtering
├── Storage/                # Generic storage abstraction
├── Vfs/                    # Virtual filesystem
│   ├── Dav/               # WebDAV support
│   ├── Filesystem/        # Filesystem wrappers
│   ├── Links/             # Link management
│   ├── Sqlfs/             # SQL-based filesystem
│   └── Sharing/           # File sharing
└── [Top-level classes]     # See below
```

---

## Top-Level API Classes

### Core Classes

| Class | Description |
|-------|-------------|
| `Api\Accounts` | User account management, LDAP/AD integration |
| `Api\Acl` | Access Control List management |
| `Api\Asyncservice` | Background task processing |
| `Api\Auth` | Authentication factory/class |
| `Api\Cache` | Multi-level caching (TREE, INSTANCE, SESSION, REQUEST) |
| `Api\CalDAV` | CalDAV protocol handler |
| `Api\Categories` | Category/favorite management |
| `Api\Config` | Configuration storage |
| `Api\Contacts` | Contact management |
| `Api\Country` | Country data |
| `Api\Db` | Database abstraction (ADOdb wrapper) |
| `Api\Egw` | Main framework object - instantiates all components |
| `Api\Etemplate` | eTemplate templating engine |
| `Api\Framework` | Web framework (header, navbar, footer) |
| `Api\Hooks` | Hook system for extensibility |
| `Api\Html` | HTML utilities and escaping |
| `Api\Image` | Image handling |
| `Api\Link` | Link registry and management |
| `Api\Ldap` | LDAP client |
| `Api\Mail` | Email sending/receiving |
| `Api\Mailer` | Mail transport |
| `Api\MimeMagic` | MIME type detection |
| `Api\Preferences` | User preferences |
| `Api\Session` | Session management |
| `Api\Translation` | Internationalization (i18n) |
| `Api\Vfs` | Virtual filesystem |

---

## Key Features

### 1. Database Abstraction (`Api\Db`)

ADOdb-based database layer supporting:
- Multiple databases: MySQL, PostgreSQL, MSSQL, Oracle
- Prepared statements
- Transaction support
- Table metadata queries
- Cross-database compatibility (e.g., `GROUP_CONCAT`, `REGEXP_REPLACE`)

**Usage:**
```php
// Basic query
foreach($GLOBALS['egw']->db->select('table_name', '*', ['id' => 1], __LINE__, __FILE__) as $row) {
    print_r($row);
}

// Insert/Update/Delete
$GLOBALS['egw']->db->insert('table', ['col' => 'value'], false, __LINE__, __FILE__);
$GLOBALS['egw']->db->update('table', ['col' => 'new'], ['id' => 1], __LINE__, __FILE__);
$GLOBALS['egw']->db->delete('table', ['id' => 1], __LINE__, __FILE__);
```

### 2. Caching (`Api\Cache`)

Four-level caching system:
- **TREE**: Shared across all instances (file-based or external)
- **INSTANCE**: Per-instance cache
- **SESSION**: Per-user session cache
- **REQUEST**: Request-scoped static cache

**Usage:**
```php
// Get with callback
$data = Api\Cache::getInstance('myapp', 'key', function() {
    return expensive_operation();
}, [], 3600); // 1 hour expiry

// Set
Api\Cache::setSession('myapp', 'key', $value);

// Get session cache reference
$value =& Api\Cache::getSession('myapp', 'key');
```

### 3. Authentication (`Api\Auth`)

Multiple authentication backends:
- SQL database
- LDAP/Active Directory
- SAML, CAS, OpenID Connect
- HTTP Basic Auth
- Application passwords
- Token-based auth

**Usage:**
```php
// Check authentication
if (Api\Auth::check_password_change($message)) {
    // Password needs changing
}
```

### 4. Framework (`Api\Framework`)

Web framework providing:
- Header/navbar/footer rendering
- CSS/JS bundling
- Template management
- Link generation
- Content Security Policy

**Usage:**
```php
// Render a page
$GLOBALS['egw']->framework->render($content);

// Include CSS/JS
Api\Framework::includeCSS('myapp', 'app');
Api\Framework::includeJS('myapp', 'app');

// Generate links
$url = Api\Framework::link('/index.php?menuaction=myapp.myclass.action');
```

### 5. Virtual Filesystem (`Api\Vfs`)

Virtual filesystem abstraction:
- WebDAV support
- SQL-based storage (`Sqlfs`)
- File sharing
- Stream wrapper implementation

### 6. CalDAV (`Api\CalDAV`)

Full CalDAV protocol implementation for calendar sharing and synchronization.

### 7. Contacts (`Api\Contacts`)

Contact management with multiple backends:
- SQL
- LDAP
- Active Directory

---

## Autoloading

EGroupware uses a PSR-4 autoloader (`api/src/autoload.php`):

```php
// Maps to /api/src/ClassName.php
class_exists('\EGroupware\Api\ClassName');

// Sub-namespaced classes
class_exists('\EGroupware\Api\Vfs\Sqlfs\StreamWrapper');
class_exists('\EGroupware\Api\Cache\Memcached');
```

---

## Exception Handling

EGroupware has a comprehensive exception system:

```php
Api\Exception\
├── AssertionFailed
├── NotFound
├── NoPermission
│   ├── Admin
│   ├── App
│   └── AuthenticationRequired
├── Redirect
└── WrongParameter
└── WrongUserinput
```

Database exceptions:
```php
Api\Db\Exception\
├── Connection
├── InvalidSql
└── Setup
```

---

## Security Features

Built-in security measures (from `loader/security.php`):
- XSS protection for `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`
- Script tag detection and removal
- `register_globals` protection
- Safe unserialize (`php_safe_unserialize`) for PHP serialized data
- CSRF protection (`Api\Csrf`)
- Content Security Policy enforcement

---

## Integration Points

### Application Structure

Applications typically follow this pattern:
```
myapp/
├── inc/
│   └── class.myapp_myclass.inc.php
├── js/
│   └── app.js
├── setup/
│   └── tables_current.inc.php
└── templates/
    └── default/
```

### Hook System

EGroupware provides hooks for extensibility:
```php
// Call a hook
Hooks::process('hook_name', $params, $return);

// Implemented hooks include:
// - topmenu_info
// - framework_avatar_stat
// - settings
// - acl_rights
// - categories
// - preferences_security
```

---

## Common Patterns

### Accessing the Egw Object

```php
// Main framework object
$GLOBALS['egw']->db
$GLOBALS['egw']->accounts
$GLOBALS['egw']->acl
$GLOBALS['egw']->preferences
$GLOBALS['egw']->session
$GLOBALS['egw']->framework
```

### Using the API Directly

```php
// Namespaced class access
use EGroupware\Api;

$data = Api\Cache::getInstance('myapp', 'key');
$result = Api\Db::getInstance()->select('table', '*', $where);
```

### JSON API

EGroupware supports JSON API requests:
```php
// JSON response
Json\Response::get()->data($data);

// Redirect in JSON context
Json\Response::get()->redirect($url);
```

---

## Notes

- EGroupware requires PHP 8.2+
- The framework uses session-based caching for performance
- Database passwords are stored in instance cache, not session
- The `api` app is always enabled
- Template system supports multiple themes (kdots, default, mobile, etc.)

---

*Generated from `/api/src/` structure analysis*
*Date: 2026-04-22*
