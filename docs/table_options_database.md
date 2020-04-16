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
        * datauri: use a file upload element; the uploaded file should be an image and will be stored in the database as a data uri (to be used in conjunction with [transformations -> image](https://bart.noordervliet.net/lt-docs/table_options_display/#transformations-experimental))
        note: there is currently no size limit and automatic resizing is not yet implemented, so beware of bloating your database with giant files
        * color: [experimental] use a color picker as input element; requires https://github.com/mrgrain/colpick to be loaded. Saves the color's #-code in the cell
    * query (string): query to generate a pulldown menu from; should produce a foreign key and a description.
      The foreign key is stored in the 'target' column, the description is shown in the user interface.
    * required (boolean or array): enforce that the field cannot be left empty
        * regex (string): only accept input matching this regular expression
        * message (string): show this message if the input is invalid as defined by the regex, or empty
    * condition (string): sets a condition that must be true for the field to be editable. This condition should be a valid javascript comparison, for instance "#2 == 'member'". Any hash-tags like '#2' will be interpreted in the usual manner, meaning 'the value of column 2 in this row'.
    Please note that this is not a security feature since it only operates clientside.
    * show (string): [experimental] only supports the value 'always' currently, which makes the edit input always visible, not just after clicking; mostly useful in combination with type => checkbox.
    * trigger (string): refresh the indicated other table whenever this table is changed through edit; needs to contain the 'tag' name of the other table
    * idcolumn (integer): normally, the row and table to edit are defined by the first column in the select query containing the id of the row to update after an edit; if the cell to be updated is in an other table, the idcolumn makes it possible to specify the row and table that has to be updated. See example below.

Full example:
```php
  'edit' => [
    1 => 'table.column1',
    2 => [ 'target' => 'table.column2', 'type' => 'multiline' ],
    3 => [ 'target' => 'table.column3', 'type' => 'checkbox', 'truevalue' => 't', 'falsevalue' => 'f' ],
    4 => [ 'target' => 'table.column4', 'type' => 'number', 'min' => 1, 'max' => 100 ],
    5 => [ 'target' => 'table.column9', 'type' => 'datauri' ],
    6 => [ 'target' => 'table.column5', 'query' => 'SELECT id, description FROM othertable' ],
    7 => [ 'target' => 'table.column6', 'required' => true ],
    8 => [ 'target' => 'table.column7', 'required' => [ 'regex' => '\d{4}', 'message' => 'Input is not a 4-digit code' ] ],
    9 => [ 'target' => 'table.column8', 'condition' => "#3 > 10" ]
    'trigger' => 'tag'
  ]
```

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
    * query (string): query to generate a pulldown menu from; should produce a foreign key and a description.
      The foreign key is stored in the 'target' column, the description is shown in the user interface.
    * required (boolean or array): enforce that the field cannot be left empty
        * regex (string): only accept input matching this regular expression
        * message (string): show this message if the input is invalid as defined by the regex, or empty
    * default (string): default value to put in the input element when it is created or emptied
    * placeholder (string): HTML placeholder attribute to set on the input element; shown whenever the input element is empty
    * submit (string): label to be used as a text on the insert button
    * class (string): CSS class name to set on the input element, in addition to the default ones
    * trigger (string): refresh the indicated other table whenever this table is changed through edit; needs to contain the 'tag' name of the other table
    * next (string): when the insert button is clicked, replace this block with the block named in this option; within the new block the new entry's insert id is available using lt_getvar('insertid') or ":insertid" in a query.
    * include (string): input definitions to reuse; currently only supports 'edit' to use the edit-definitions
    * noclear (boolean): if set to true, the insert input fields are not cleared after each insert is done
    * onsuccessalert (string): text to show in a javascript alert() after the insert was done succesfully
    * onsuccessscript (string): code to be evaluated in a javascript eval() after the insert was done succesfully
    * hidden (array): hidden data to insert alongside the user-entered fields (may also be an array of arrays to insert multiple hidden fields)
        * target (string): the column to store the hidden data in &lt;table>.&lt;column> format
        * value (string): the value to store; this is commonly an lt_getvar() call

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
    * runsql (string): query to run on the database when the action button is clicked; hashtags in this string are interpreted; for output, if any, only the first column of the first row is returned as a string
    * runphp (string): php code to run when the action button is clicked; hashtags in this string are interpreted
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

The various run\* options can reference the output of each other. Within the SQL context (runsql), the :lt_phpoutput and :lt_blockoutput named parameters are available. In PHP context (runphp and runblock), the $lt_sqloutput, $lt_phpoutput and $lt_blockoutput variables are available. Please note: each of the run\* options can only be used once per rowaction.


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
