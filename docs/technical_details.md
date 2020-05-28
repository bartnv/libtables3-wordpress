# Libtables technical details

## Block execution contexts

Libtables blocks can be executed in two different contexts:

 - a direct GET or POST request triggered by a page load
 - an AJAX call from a loaded page (only from blocks containing one or more
   lt_table(), lt_control() or lt_text() elements)

Blocks containing one or more lt_table(), lt_control() or lt_text() elements are
thus executed multiple times. For this reason you should avoid using code in
those blocks that should not be run repeatedly (like sending an email).

Additionally, GET and POST variables sent by the client are only available in
the first context, so if you have conditional logic in your block based on
those, you should not include lt_table(), lt_control() or lt_text() elements in
the same block. You can however use the lt_print_block() function to include
other blocks that can contain those elements. That's because the AJAX calls will
then go directly to the included block and not execute the referring block.

An example:
```php
  if (isset($_GET['date'])) lt_setvar('pickup-date', $_GET['date']);
  else lt_setvar('pickup-date', null);

  if (lt_isvar('pickup-date')) lt_print_block('date-chosen');
  else lt_print_block('date-listing');
```

The block 'date-chosen' will then probably use the ':pickup-date' named parameter
in its lt_table() query to show information for the correct date.
