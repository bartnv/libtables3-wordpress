# edit
  * edit (array): allow in-cell editing of the specified columns

Basic example:
```php
  'edit' => [
    3 => 'user.firstname',
    4 => 'user.lastname'
  ]
```

  * edit (array): all options
    * target (string): the column to store the entered data in &lt;table>.&lt;column> format
    * type (string): change the type of the used input element; available options are:
        * multiline: use a &lt;textarea> element allowing line-breaks in the input
        * checkbox: use a &lt;input type="checkbox"> element; also allows the use of the 'truevalue' and 'falsevalue' suboptions to change the way boolean values are interpreted
          from and written to the database
        * date: use a &lt;input type="date"> element which presents a native date-picker UI in supporting browsers; falls back to normal text input otherwise
        * password: use a &lt;input type="password"> element which masks out the input
        * email: use a &lt;input type="email"> element which enforces a valid email address as input in supporting browsers; falls back to normal text input otherwise
        * number: use a &lt;input type="number"> element which presents up/down arrows in supporting browsers; falls back to normal text input otherwise. Also allows the use of 'min' and 'max' suboptions that set limits on the permitted input
        * datauri: use a file upload element; the uploaded file should be an image and will be stored in the database as a data uri (to be used in conjunction with [transformation -> image](../table_options_display/#transformation))
        note: there is currently no size limit and automatic resizing is not yet implemented, so beware of bloating your database with giant files
        * color: [experimental] use a color picker as input element; requires https://github.com/mrgrain/colpick to be loaded. Saves the color's #-code in the cell
    * query (string): SQL query to generate a pulldown menu from; should produce a foreign key and a description.
      The foreign key is stored in the 'target' column, the description is shown in the user interface.
    * required (boolean or array): enforce that the field cannot be left empty
        * regex (string): only accept input matching this regular expression
        * message (string): show this message if the input is invalid as defined by the regex, or empty
    * condition (string): sets a condition that must be true for the field to be editable. This condition should be a valid javascript comparison, for instance "#2 == 'member'". Any hash-tags like '#2' will be interpreted in the usual manner, meaning 'the value of column 2 in this row'.
      Please note that this is not a security feature since it only operates clientside.
    * show (string): [experimental] only supports the value 'always' currently, which makes the edit input always visible, not just after clicking; mostly useful in combination with type => checkbox.
    * trigger (string): refresh the indicated other table whenever this table is changed through edit; needs to contain the 'tag' name of the other table
    * idcolumn (integer): normally, the row and table to edit are defined by the first column in the select query containing the id of the row to update after an edit; if the cell to be updated is in an other table, the idcolumn makes it possible to specify the row and table that has to be updated. See example below.
    * sqlfunction (string): SQL function to change the new value before being stored in the database; this string is used as the to-be-updated field with the single '?' within it replaced by the entered value; within this string, hash-tags like '#2' will be replaced with the corresponding value from another column in the row
    * phpfunction (string): PHP function to change the new value before being stored in the database; this string is evaluated as PHP with the single '?' within it replaced by the entered value; within this string, hash-tags like '#2' will be replaced with the corresponding value from another column in the row
    * runsql (string): SQL query to run on the database after the new value is stored in the database; the row's id is available using ":lt_id" in the query; for output, if any, only the first column of the first row is returned as a string
    * runphp (string): PHP code to run after the new value is stored in the database; the row's id is available using lt_getvar('lt_id') in the PHP
    * runblock (string): libtables block to run after the new value is stored in the database; the row's id is available within the block using lt_getvar('lt_id') in PHP or ":lt_id" in a query
    * runorder (array): define the order in which the run* options are executed; the default order is [ 'sql', 'php', 'block' ]; [Notes about output variables](#-output-variables)
    * output (string): how to use the output coming out of the last of the runsql/runphp/runblock parameters (as defined by runorder above); valid options are 'block', 'alert', 'location' or 'function';
    * functionname (string): the name of the javascript function to run when using output: 'function'

Full example:
```php
  'edit' => [
    1 => 'table.column1',
    2 => [ 'target' => 'table.column2', 'type' => 'multiline' ],
    3 => [ 'target' => 'table.column3', 'type' => 'checkbox', 'truevalue' => 't', 'falsevalue' => 'f' ],
    4 => [ 'target' => 'table.column4', 'type' => 'number', 'min' => 1, 'max' => 100 ],
    5 => [ 'target' => 'table.column5', 'type' => 'datauri' ],
    6 => [ 'target' => 'photo.filename', 'type' => 'file', 'path' => 'photos/' ],  // The directory path is interpreted relative to the data.php of your website and must be writable for the PHP process
    7 => [ 'target' => 'table.column7', 'query' => 'SELECT id, description FROM othertable' ],
    8 => [ 'target' => 'table.column8', 'required' => true ],
    9 => [ 'target' => 'table.column9', 'required' => [ 'regex' => '\d{4}', 'message' => 'Input is not a 4-digit code' ] ],
    10 => [ 'target' => 'table.column10', 'condition' => "#4 > 10" ],              // Only allow editing of column10 for rows where column4 contains a value larger than 10
    11 => [ 'target' => 'table.email', 'sqlfunction' => 'LOWER(?)', 'runblock' => 'verify_email' ],
    12 => [ 'target' => 'table.password', 'phpfunction' => 'password_hash(?, PASSWORD_DEFAULT)' ],
    13 => [ 'target' => 'table.visits', 'runorder' => [ 'sql', 'php' ], 'runsql' => 'SELECT SUM(visits) FROM table', 'runphp' => 'if ($lt_sqloutput == 1000) { print "This is the 1000th visitor!" }', 'output' => 'alert' ],
    'trigger' => 'tag'
  ]
```
Edit line 11 requires you to create a block verify_email.php that uses :lt_id to fetch the emailaddress from the database and verifies it.

Example for idcolumn:
```php
  lt_table('example', 'Example', "SELECT table.id, othertable.id, table.field, othertable.field FROM table JOIN othertable ON ...",
  [
    'edit' => [
      2 => 'table.field',
      3 => [ 'target' => 'othertable.field', 'idcolumn' => 1 ]
    ]
  ]);
```


# insert
  * insert (array): add a row to the table to insert new data

Basic example:
```php
  'insert' => [
    3 => 'user.firstname',
    4 => 'user.lastname'
  ]
```

  * insert (array): it is common to allow insertion of all columns that are already configured for 'edit'; this can be done with the 'include' suboption. The example below
    includes the 'edit' configuration and adds column 5 purely for the insert function.

Include example:
```php
  'insert' => [
    'include' => 'edit',
    5 => 'table.column5'
  ]
```

  * insert (array): all options
    * target (string): the column to store the entered data in &lt;table>.&lt;column> format
    * type (string): change the type of the used input element; available options are:
        * multiline: use a &lt;textarea> element allowing line-breaks in the input
        * checkbox: use a &lt;input type="checkbox"> element; also allows the use of the 'truevalue' and 'falsevalue' suboptions to change the way boolean values are interpreted
          from and written to the database
        * date: use a &lt;input type="date"> element which presents a native date-picker UI in supporting browsers; falls back to normal text input otherwise
        * password: use a &lt;input type="password"> element which masks out the input
        * email: use a &lt;input type="email"> element which enforces a valid email address as input in supporting browsers; falls back to normal text input otherwise
        * color: use a color picker as input element; requires https://github.com/mrgrain/colpick to be loaded. Saves the color's #-code in the cell
        * number: use a &lt;input type="number"> element which presents up/down arrows in supporting browsers; falls back to normal text input otherwise. Also allows the use of
          'min' and 'max' suboptions that set limits on the permitted input
        * file: use a file upload element; stores the file in the directory indicated by the required extra parameter 'path' and inserts the path to the uploaded file in the target column
    * query (string): SQL query to generate a pulldown menu from; should produce a foreign key and a description.
      The foreign key is stored in the 'target' column, the description is shown in the user interface.
    * required (boolean or array): enforce that the field cannot be left empty
        * regex (string): only accept input matching this regular expression
        * message (string): show this message if the input is invalid as defined by the regex, or empty
    * default (string): default value to put in the input element when it is created or emptied
    * placeholder (string): HTML placeholder attribute to set on the input element; shown whenever the input element is empty
    * submit (string): label to be used as a text on the insert button
    * class (string): CSS class name to set on the input element, in addition to the default ones
    * trigger (string): refresh the indicated other table whenever this table is changed through edit; needs to contain the 'tag' name of the other table
    * include (string): input definitions to reuse; currently only supports 'edit' to use the edit-definitions
    * noclear (boolean): if set to true, the insert input fields are not cleared after each insert is done
    * onsuccessalert (string): text to show in a javascript alert() after the insert was done succesfully
    * onsuccessscript (string): code to be evaluated in a javascript eval() after the insert was done succesfully
    * hidden (array): hidden data to insert alongside the user-entered fields (may also be an array of arrays to insert multiple hidden fields)
        * target (string): the column to store the hidden data in &lt;table>.&lt;column> format
        * value (string): the value to store; this is commonly an lt_getvar() call
    * keys (array): specifies the relation of tables if you insert into multiple tables at once; each entry links a primary key (the key of the array entry) to a foreign key (the value of the array entry); this causes the newly generated id's to automatically be inserted into the indicated foreign key fields
    * sqlfunction (string): SQL function to change the new value before being stored in the database; this string is used as the to-be-updated field with the single '?' within it replaced by the entered value
    * phpfunction (string): PHP function to change the new value before being stored in the database; this string is evaluated as PHP with the single '?' within it replaced by the entered value
    * runsql (string): SQL query to run on the database after the new row is stored in the database; the new entry's insert id is available using ":lt_id" in the query; for output, if any, only the first column of the first row is returned as a string
    * runphp (string): PHP code to run after the new row is stored in the database; the new entry's insert id is available using lt_getvar('lt_id') in the PHP
    * runblock (string): libtables block to run after the new row is stored in the database; the new entry's insert id is available within the block using lt_getvar('lt_id') in PHP or ":lt_id" in a query
    * runorder (array): define the order in which the run* options are executed; the default order is [ 'sql', 'php', 'block' ]; [Notes about output variables](#-output-variables)
    * output (string): how to use the output coming out of the last of the runsql/runphp/runblock parameters (as defined by runorder above); valid options are 'block', 'alert', 'location' or 'function';
    * functionname (string): the name of the javascript function to run when using output: 'function'
    * onconflict (array): SQL fragments to add to the INSERT statement to make use of the internal conflict resolution of PostgreSQL or MySQL/MariaDB (e.g. dealing with collisions on unique constraints). Each array entry consists of the table name as the key and the SQL fragment as a string value. The fragment is added to the SQL of the INSERT statement after the VALUES and before the RETURNING clause.
        For PostgreSQL, this string should start with "ON CONFLICT"
        For MySQL, this string should start with "ON DUPLICATE KEY"
      Within the string, input values can be used with #<columnname> and libtables variables can be used with :<varname> as usual.

Full example:
```php
  'insert' => [
    1 => 'table.column1',
    2 => [ 'target' => 'table.column2', 'type' => 'multiline' ],
    3 => [ 'target' => 'table.column3', 'type' => 'checkbox', 'truevalue' => 't', 'falsevalue' => 'f' ],
    4 => [ 'target' => 'table.column4', 'type' => 'number', 'min' => 1, 'max' => 100 ],
    5 => [ 'target' => 'table.column5', 'query' => 'SELECT id, description FROM othertable' ],
    6 => [ 'target' => 'table.column6', 'required' => true ],
    7 => [ 'target' => 'table.column7', 'required' => [ 'regex' => '\d{4}', 'message' => 'Input is not a 4-digit code' ] ],
    8 => [ 'target' => 'table.email', 'sqlfunction' => 'LOWER(?)' ],
    9 => [ 'target' => 'table.password', 'phpfunction' => 'password_hash(?, PASSWORD_DEFAULT)' ],
    'trigger' => 'tag',
    'noclear' => true,
    'onsuccessalert' => 'Row inserted succesfully',
    'onsuccessscript' => "$('#status').addClass('status-ok')",
    'hidden' => [
      'target' => 'table.userid',
      'value' => lt_getvar('userid');
    ]
  ]
```

Keys example:
```php
  'insert' => [
    1 => 'person.firstname',
    2 => 'person.lastname',
    3 => 'emailaddress.email',
    'keys' => [
      'person.id' => 'emailaddress.personid'
    ]
  ]
```
This causes the person entry to be inserted first, followed by an entry into the emailaddress table with the new person id inserted into the foreign key column 'personid'. The `lt_id` variable in this case will be the person id.

Onconflict example for PostgreSQL (assuming the email column has a UNIQUE constraint set on it):
```php
  'insert' => [
    1 => 'users.email',
    2 => 'users.name'
    'onconflict' => [
      'users' => 'ON CONFLICT (email) DO UPDATE SET name = #name'
    ]
  ]
```

Onconflict example for MySQL/MariaDB (assuming the email column has a UNIQUE constraint set on it):
```php
  'insert' => [
    1 => 'users.email',
    2 => 'users.name'
    'onconflict' => [
      'users' => 'ON DUPLICATE KEY UPDATE name = #name'
    ]
  ]
```

Examples for the run* options (including output and function) can be seen under the 'action' section below.

# delete
  * delete (array): render a row-delete button at the end of each row

Basic example:
```php
  'delete' => [
    'table' => 'user',
    'text' => 'Delete user',
    'confirm' => 'Are you sure you want to delete user with ID #0?'
  ]
```

  * delete (array): all options
    * text (string): text to use on the button instead of the Unicode cross-symbol (✖)
    * html (string): html to use instead of the &lt;input type="button"> element; will be wrapped with an &lt;a> element to handle the onclick
    * confirm (string): text to show in a javascript confirm() dialog to request confirmation from the user; hashtags in this string are interpreted
    * notids (array): id-numbers of rows that may not be deleted. This only functions clientside, so don't use this as a security feature.

Full example:
```php
  'delete' => [
    'table' => 'user',
    'html' => '<img src="delete.svg">',
    'notids' => [ lt_getvar('userid') ]
  ]
```

# selectany [experimental]
  * selectany (array): render checkboxes to select multiple rows in the table; this is saved to the specified linktable (many-to-many relationship) using the
    first parameter of this block (the first field in 'fields') and the id of the row (the second field in 'fields')
    * name (string): name used for the checkbox column in the table header
    * linktable (string): SQL table name of the linktable
    * fields (array of strings): the two column names for the foreign keys going into the linktable
    * id (integer): the id to use for the first foreign key instead of the first block parameter which is the default
```php
  'selectany' => [
    'name' => 'Select',
    'linktable' => 'user_groups',
    'fields' => [ 'user_id', 'group_id'],
    'id' => lt_getvar('userid')
  ]
```

# rowaction
  * rowaction (array of arrays): render one or more buttons at the end of each row to perform an action
    * text (string, required): text to be printed on the action button; hashtags in this string are interpreted
    * condition (array): condition whether or not to show the action button for that row (condition is also checked serverside when the button is activated); see [Condition arrays](#-condition-arrays) for more information
    * confirm (string): text to show in a javascript confirm() dialog to request confirmation from the user before running the action; hashtags in this string are interpreted
    * setvar (array): associative array with 'name' => 'value' pairs of libtables variables to set before the run* actions are performed; hashtags in both the name and the value are interpreted
    * runsql (string): SQL query to run on the database when the action button is clicked; hashtags in this string are interpreted; for output, if any, only the first column of the first row is returned as a string
    * runphp (string): PHP code to run when the action button is clicked; hashtags in this string are interpreted
    * runblock (string): libtables block to run when the action button is clicked; hashtags in this string are interpreted
    * runorder (array): define the order in which the run* options are executed; the default order is [ 'sql', 'php', 'block' ]; [Notes about output variables](#-output-variables)
    * output (string): how to use the output coming out of the last of the runsql/runphp/runblock parameters (as defined by runorder above); valid options are 'block', 'alert', 'location' or 'function';
    * functionname (string): the name of the javascript function to run when using output: 'function'
```php
  'rowaction' => [
    [ 'text' => 'Mark paid', 'condition' => [ '#2', '==', 'no' ], 'confirm' => 'Mark invoice #1 as paid?', 'runsql' => 'UPDATE payment SET paid = true WHERE id = #0' ],
    [ 'text' => 'Review payment', 'condition' => [ '#2', '==', 'yes' ], 'runblock' => 'show-payment', 'setvar' => [ 'payment_id' => '#0' ] ],
    [ 'text' => 'Send email', 'runorder' => [ 'sql', 'block' ], 'runsql' => 'SELECT email FROM user WHERE id = #0', 'runblock' => 'send-email', 'output' => 'function', 'functionname' => 'play-mailsent-sound' ],
    [ 'text' => 'Update invoice label', 'condition' => [ '#3', '!regex', '^CompanyName-' ], 'runsql' => "UPDATE invoice SET label = CONCAT('CompanyName-', label) WHERE id = #0" ]
  ]
```

### - condition arrays

This array should consist of 3 or 4 elements. The first 3 determine the condition to be checked. The optional 4th element is the error message
to be shown when the serverside condition check fails (this can happen with concurrent edits on the database).

The 3 condition elements are:
 * Left parameter: can be a string, a number or null; in strings, hashtags will be interpreted
 * Comparison operator: must be a string, one of '==', '!=', '<=', '<', '>=', '>', 'regex' or '!regex'; the Regular Expressions are unanchored by default
 * Right parameter: can be a string, a number or null; in strings, hashtags will be interpreted

Warning: due to differences in type coersion between Javascript (which executes the clientside condition to show the button) and PHP (which executes the serverside check), you should not enter numeric parameters as strings.

### - output variables

The various run\* options can reference the output of each other. Within the SQL context (runsql),
the :lt_phpoutput and :lt_blockoutput named parameters are available. In PHP context (runphp and
runblock), the $lt_sqloutput, $lt_phpoutput and $lt_blockoutput variables are available. The
$lt_sqloutput variable contains the value from first row, first cell of the SQL output.

Please note: each of the run\* options can only be used once per rowaction.


# tableaction
  * tableaction (array): render a button at the top of the table running an action for the table as a whole
    * text (string, required): text to be printed on the action button
    * sqlcondition (string): SQL query that determines whether or not to show the action button for this table (condition is also checked when the button is activated); when the query returns one or more rows, the condition is true, otherwise it is false
    * confirm (string): text to show in a Javascript confirm() dialog to request confirmation from the user; hashtags in this string are interpreted
    * runsql (string): SQL query to run when the button is clicked
    * addparam (array): request an additional parameter from the user to be appended to the parameters for the query
      text (string, required): text to show the user in the parameter dialog
```php
  'tableaction' => [
    'text' => 'Confirm',
    'sqlcondition' => 'SELECT id FROM reservation WHERE confirmed = false AND user = :user',
    'confirm' => 'Are you sure you want to confirm all your reservations?',
    'runsql' => 'UPDATE reservation SET confirmed = true WHERE confirmed = false AND user = :user'
  ]
```
