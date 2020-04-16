# Libtables Functions

This page documents all PHP functions of Libtables. The lt_table(), lt_insert(), lt_control()
and lt_text() functions can only be used within a block[^1]. The other functions can also be used
in your site's PHP code, as long as you include() the 'libtables.php' file.

[^1]: for an explanation on blocks, read the [concepts item on blocks](concepts/#blocks)

## lt_table

The lt_table() function adds a table to the current block.

    lt_table(tag, title, query, options)

Parameters:

  * tag (required): unique name for the table within this block, only lowercase letters allowed
  * title (required): name of the table, displayed within a <th> tag at the top
  * query (required): the SQL query to generate the rows for this table[^2]
  * options (optional): an array of options to add functionality such as insert, delete, edit-in-place,
    filtering, sorting, etc to your table

Basic example:
```php
  lt_table('users', 'All users', "SELECT id, name FROM users", [ 'sortable' => true ]);
```

See [lt_table() options](table_options_display/) for more information.

[^2]: in special cases you can pass in an array of column names instead of a query, to generate
a 'table' with no data but only insert fields (deprecated in favor of the lt_insert function below)

## lt_insert

The lt_insert() function is like lt_table(), except that it only shows insert fields and no
data rows. Instead of an SQL query you need to pass in an array of column names that are used
as labels for the insert fields as usual.

lt_insert(tag, title, column_names, options)

Parameters:

* tag (required): unique name for the table within this block, only lowercase letters allowed
* title (required): name of the table, displayed within a <th> tag at the top
* column_names (required): an array of the column names to use
* options (optional): an array of options that defines the insert functionality

Basic example:
```php
  lt_insert('users', 'Add user', [ 'id', 'name' ], [
    'insert' => [
      1 => [ 'users.name' ]
    ]
  ]);
```

All insert functionality from [lt_table() database mutation options](table_options_database/) can be used here.

## lt_print_block

Insert the contents of the specified Libtables block at this point in your website code.
The block can be simple HTML (using a .html file extension) or PHP/HTML (using a .php file
extension). The block content is wrapped in a DIV element with its DOM id set to the block
name.

    lt_print_block(name, options)

Parameters:

  * name (required): the name of the block to use; libtables will search for this name, first
    with '.html' appended and, if not found, with .php appended, in the configured blocks_dir
  * options (optional): an array of options for the block
    * class: the CSS class to set for this block's DIV element

```php
  lt_print_block('productinfo', [ 'class' => 'info' ]);
```

This function is the primary way to integrate the libtables functionality in your own PHP code.

## lt_query_single

Returns the value of the first row, first column resulting from the passed query
and optional parameters. Usually used with a SELECT or an "INSERT ... RETURNING"
query. If any error is encountered or the query yields no result, null is returned. Further error
reporting is done to de PHP error_log.

    lt_query_single(query, parameters)

Parameters:

  * query (string, required): the SQL to generate the single row, single cell output
  * parameters (array, optional): an associative array of additional parameters that can be used
  in the SQL query (besides the regular variables set with lt_setvar() which are always available)

```php
  lt_query_single('SELECT city.populationcount FROM city WHERE name = :name', [ 'name' => $_POST['cityname'] ]);
```

Reminder: don't use $_POST variables within a libtables block, use lt_setvar() instead

## lt_control [experimental]

The lt_control() function adds a control-flow button to the current block. You can use this function
multiple times, for instance to implement 'previous step' and 'next step' functionality.

    lt_control(tag, options)

Parameters:

  * tag (string, required): unique name for the controls within this block, only lowercase letters allowed
  * options (array, required): an array of options to specify the functionality of the buttons
    * next (array, required): first element is the block name to load when the next button is clicked, second element is the text to show on the button
    * verify (string): an SQL query to run before any action is taken; if the query returns no results, stop the action and show the error text
    * error (string): the error text to show if the verify fails
    * php (string): PHP code to run before any action is taken; if the PHP returns text, stop the action and show this text in an alert
    * class (string): the CSS class to assign to the button

```php
  lt_control('buttons', [
    'next' => [ 'payment', 'Go to payment' ],
    'verify' => "SELECT product_id FROM cart WHERE id = :cart",
    'error' => "you have no items in your cart",
    'php' => "if (!check_stock(lt_getvar('cart'))) return 'Insufficient stock';",
    'class' => 'importantButton'
  ]);
```

## lt_text [experimental]

The lt_text() function generates a text from a database query and adds it to the current block.
The text is live-updated by AJAX just as the tables are.

    lt_text(tag, query, format)

Parameters:

  * tag (required): unique name for the text within this block, only lowercase letters allowed
  * query (required): the SQL query generating the source data for this text (should only result in one row)
  * format (required): a format string; hashtags within this string will be replaced with the column values out of the query

```php
  lt_text('cartinfo',
    "SELECT SUM(amount) FROM cart WHERE user = :user",
    'Items in cart: #0'
  );
```
