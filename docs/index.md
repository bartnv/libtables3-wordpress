# Welcome to the Libtables3 documentation

Libtables allows for rapid development of dynamic, interactive web- applications that leverage the power of a relational
database backend. It is aimed at developers with a firm grasp of SQL who wish to minimize the time spent on writing the
PHP and Javascript plumbing for their web-application. Its features include:

 * Live-updating tables
 * Paginated, sortable and filterable tables
 * Inline editing of table-cells (edit-in-place)
 * Validated input forms inserting into multiple tables at once with foreign keys
 * Pulldown-menus based on 1-to-many relationships with option to add entries
 * Cell tooltips based on hidden columns
 * Custom table layouts

All client-server interaction is AJAX-based, so libtables3 is suitable to use in single-page, load-once web-applications.

## Requirements

 * PHP 7.0 or newer
 * jQuery 3.0 or newer
 * Browser: ECMAscript 2015 (ES6) support
 * Database: PostgreSQL 9.5+, MySQL 5.6+, MariaDB 10.0+ or SQLite 3+

## Installation

  * Grab the files from https://github.com/bartnv/libtables3
  * Put 'clientside.js', 'data.php' and 'libtables.php' in the webroot directory of your website
  * Create a subdirectory in the webroot to store your Libtables blocks (for instance 'blocks')
  * Create a file 'config.php' in the webroot with the following template:

```php
<?php

$dbh = new PDO('pgsql:dbname=<databasename>', '<username>', '<password>');

$lt_settings = [
  'blocks_dir' => 'blocks/',
  'transl_query' => "SELECT id, orig, nl_NL, en_US FROM interface_translate",
  'error_rewrite' => [
  ],
  'id_columns' => [
  ]
];
```

  * Modify the PDO connection string to match your local configuration (see [PHP PDO documentation](https://secure.php.net/manual/en/pdo.construct.php))
  * Set the 'blocks_dir' to your chosen subdirectory name ending with a slash
  * Leave the 'error_rewrite' array empty for now; it can later be used to translate SQL errors into more user-friendly messages (see [Libtables configuration](configuration/) for more information)
  * Likewise for the 'id_columns' array; it is only needed if your primary key columns are not named 'id'

Integration of Libtables3 exists for WordPress. Installation is different in this case. See:
   * https://github.com/bartnv/libtables3-wordpress

## Upgrading from Libtables2 to version 3

  * Make sure all block files start with "<?php "
  * Change all parameters (SQL ? positional and $params) to the new lt_setvar() system
  * Change all $_SESSION variables to also use the lt_setvar() system
