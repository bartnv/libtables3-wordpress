# Libtables configuration

## $lt_settings global variable

### error_rewrite array

This is an associative array of possible database error messages (as the key) and
the user-friendly text to show instead (as the value). The key can either be a plain
string (to be matched literally against the error message) or be a regular expression.
The latter should start and end with a slash as is usual in PHP. When using a regular
expression, replacement patterns like $1, $2 can be used in the user-friendly text to
be replaced with the matches from the regular expression.

Example:
```php
  'error_rewrite' => [
    'Database connection failed' => 'The database is currently unavailable; please try again later',
    "/.*Duplicate entry '(.*)' for key 'username_unique'/" => 'Username "$1" is already present in the database'
  ]
```
