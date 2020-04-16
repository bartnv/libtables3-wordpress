Most options shown here can be combined within the options array passed into lt_table() and lt_insert(). For example:

    lt_table('users', 'All users', "SELECT id, name FROM users", [ 'sortable' => true, 'filter' => true ]);

# sortable

  * sortable (boolean): set to true to add sorting functionality to the column headers
    This setting doesn't affect (or even know) the initial sorting order of the data, which is
    specified in the SQL query.
```php
  'sortable' => true
```

# filter
  * filter (boolean): add a filter row just below the headers of the table. Filter fields for numeric fields
    accept numeric logic like "< 4" or "= 10". Filter fields for text columns support regular expression matching
    like "(red|green)" or "^start". Multiple fields combine with AND logic. The filter icon at the top left of the
    table briefly lists these capabilities in its mouseover text.
```php
  'filter' => true
```

# limit
  * limit (integer): limit the table to x rows and add pagination controls to page through the results
```php
  'limit' => 25
```

# hideheader
  * hideheader (boolean): hides the top row of header names
```php
  'hideheader' => true
```

# sum
  * sum (array): adds one or more summation fields at the bottom of the specified column(s)

  The example below adds a sum at the bottom of columns 1 and 3, but only if these columns are indeed numeric.
```php
  'sum' => [
    1 => true,
    3 => true
  ]
```

# mouseover
  * mouseover (array): uses the specified column as a mouseover text for the previous column; the mouseover column is not shown in the regular way.
    The mouseover column continues to be counted with the column numbers.
```php
  'mouseover' => [
    2 => true,
    5 => true
  ]
```

# hidecolumn
  * hidecolumn (array): hides the specified column. This allows the column data to be used for actions or decisions, but not show to the user.
    The hidden column continues to be counted with the column numbers.
```php
  'hidecolumn' => [ 2 => true ]
```

# class
  * class (array): assigns a CSS class to the specified columns or the table as a whole.
```php
  'class' => [
    'table' => 'MyTable',
    3 => 'ColumnThree'
  ]
```

# format
  * format (string): lays out the cells of one row according to the specified format. This option implies 'limit => 1' because only one row can be shown at the same
    time this way. 'H' inserts the next header in sequence, 'C' the next cell, '-' is a horizontal continuation of whatever is left of it, '|' is a vertical continuation,
    'I' is the next insert field. 'S' is the submit button for the insert function and may only appear once. 'A' places the content of the 'appendcell' option, which may also appear only once. 'x' (lowercase) yields an unused cell, by default styled in light grey.
```php
  'format' => 'HH
               CC
               H-
               C-
               H-
               C-'
```

# pagetitle
  * pagetitle (string): updates the HTML &lt;title> element with the specified string. You can use #-tags to insert column data into the string. If you display a full table,
    this will leave the title with the contents of the last row. A more logical use is with the 'format' or 'limit => 1' options.
```php
  'pagetitle' => 'Showing row #0'
```

# emptycelltext
  * emptycelltext (string): replacement text to put in cells that would otherwise be empty
```php
  'emptycelltext' => 'No data'
```

# textifempty
  * textifempty (string): replacement text to show instead of the table if there are no rows in the table
```php
  'textifempty' => 'No rows to show yet'
```

# hideifempty
  * hideifempty (boolean): hide the whole table element if there are no rows in the table
```php
  'hideifempty' => true
```

# appendcell
  * appendcell (string): string to show in a &lt;td> element appended to each normal table row. Can contain any valid HTML and also #-tags which get replaced with
    data from the corresponding row.
```php
  'appendcell' => '<a href="https://www.google.com/search?q=#1">Search Google for #1</a>'
```

# appendrow
  * appendrow (string): string to append as the last row to a table. Should include the relevant &lt;td> tags. No #-tags can be used. Use it to show general information
    about the data in a table or to link to more resources.
```php
  'appendrow' => '<td>General information about the table above...</td>'
```

# subtable
  * subtables (array): in the specified column(s), replace each cell with an entire table defined in another block. These subtables can use the same parameters as
    are available in the current block. Note that the columns need to be present in the SQL query, but they may just be empty.
```php
  'subtables' => [
    5 => 'subtables:remarks',
    6 => 'subtables:photos'
  ]
```

# trigger
  * trigger (string): trigger a reload on another table within the user's browser whenever this table gets changed locally. The string should be the lt_table() tag of
    the target table.
```php
  'trigger' => 'statistics'
```

# callbacks
  * callbacks (array): array of events and the corresponding custom javascript function (part of your website) to call when the event happens. Currently available are
    'change' (triggers whenever the user changes data locally) and 'loadAll' (triggers when all the tables on the page have finished loading).
```php
  'callbacks' => [
    'change' => 'changeSize()',
    'loadAll' => 'doScroll()'
  ]
```

# display
  * display (string): render the table with different elements than the normal &lt;table>, &lt;tr> and &lt;td>. Available options are 'list' (rendered as &lt;ul> with
    each row a &lt;li>), 'divs' (renders as a &lt;div> with class "lt-div-table" with each row as a &lt;div> with class "lt-div-row"), 'select' (renders as a &lt;select>
    with each row as an &lt;option>).
```php
  'display' => 'list'
```

# style
  * style (array): assigns direct CSS styling to the specified elements. You can use the contents of other columns (using #0, #1 notation) as part of the CSS here. If you don't need that,
    please use the 'class' option. This option supports adding style to specific colums, rows from tables with 'display => list', or tables with the 'selectone' option in use.
      * list
      * selectone

Set the background-color of each list entry to the value of column 2:
```php
  'style' => [
    'list' => 'background-color: #2'
  ]
```

Hide column 2 and use its value as the CSS height for column 3
```php
  'hidecolumn' => [ 2 => true ],
  'style' => [
    3 => 'height: #2px'
  ]
```

# transformation
  * transformation (array): applies a clientside transformation to the data in the specified column(s). Currently available are 'round', which rounds a numeric
    value to the requested number of decimal digits, and 'image', which interprets the data as the src attribute of an &lt;img> tag. In the latter case the data can
    either be a valid URL or a data URI containing the image data itself.
```php
  'transformation' => [
    3 => [ 'round' => 2 ]
  ]
```
```php
  'transformation' => [
    1 => [ 'image' => '#1' ]
  ]
```

# rowlink
  * rowlink (string): currently only used for display => divs, wraps each &lt;div> in a link (&lt;a>-element) with this string as the href-attribute. #-tags can be used.
```php
  'rowlink' => 'product.php?id=#0'
```

# renderfunction
  * renderfunction (string): provide the name of a javascript function that completely replaces the normal lt_table() rendering. This function will be called with 2
    parameters, the first is the &lt;div> with the source attributes, the second is the data object coming from the server. You can render the data however you like to a
    jQuery DOM-object (called 'content' here) and then end the function with:
```javascript
  var key = table.attr('id');
  tables[key].table = content;
```
Usage:
```php
  'renderfunction' => 'renderListing'
```

# export [experimental]
  * export (array): add links to the bottom of the table to export the data in various formats. Available (experimentally) for now are: 'xlsx' (export as a modern Excel-sheet)
    and 'image' (export as a PNG image). The Excel export needs "xlsxwriter.class.php" from https://github.com/mk-j/PHP_XLSXWriter/ to be in the main libtables directory. The
    PNG export automatically pulls the required third-party script from CDNjs. Modifiers are: 'nopreview' (don't show the data itself, but only the number of rows, in the HTML
    version of the table) and 'hideid' (don't show the row id's in the export, just as they are hidden in the HTML version). The export is done
    serverside, so clientside modifiers such a transformation are not applied. This function has problems with datatype conversion from PHP to Excel,
    so you need to verify for yourself that the output is as you expect.
```php
  'export' => [
    'xlsx' => true,
    'nopreview' => true,
    'hideid' => true
  ]
```
