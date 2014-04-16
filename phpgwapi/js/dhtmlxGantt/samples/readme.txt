### Prerequisites

Some samples require PHP and Database support. By default they use SQLLite
database ( samples/common/samples.sqlite ), but you can reconfigure samples
and work with MySQL, to do so

- import dump samples/common/dump.sql
- comment SQLite section and uncomment MySQL section in 
  samples/common/config.php



### Database and language support

PHP connectors supports MySQL, MsSQ, Oracle, FireBird, SQLite, PostgreSQL, SQLAnywhere

We don't have connectors for Java and .Net currently, but you can use any custom code which will generate valid JSON data.