<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 * Libtables3: framework for building web-applications on relational databases *
 * Version 3.0.0-alpha / Copyright (C) 2019  Bart Noordervliet, MMVI           *
 *                                                                             *
 * This program is free software: you can redistribute it and/or modify        *
 * it under the terms of the GNU Affero General Public License as              *
 * published by the Free Software Foundation, either version 3 of the          *
 * License, or (at your option) any later version.                             *
 *                                                                             *
 * This program is distributed in the hope that it will be useful,             *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of              *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               *
 * GNU Affero General Public License for more details.                         *
 *                                                                             *
 * You should have received a copy of the GNU Affero General Public License    *
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.       *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

if (!session_id()) session_start();
require('config.php');
if (is_file('local.php')) {
  if (is_readable('local.php')) include('local.php');
  else error_log("Libtables error: local.php exists but is not readable for the PHP user");
}

$tables = array();

function lt_var($name, $value = null) {
  if ($value === null) {
    if (isset($_SESSION[$name])) return $_SESSION[$name];
    throw new Exception("Request for undefined libtables variable '$name'");
  }
  else {
    $_SESSION[$name] = $value;
    return $value;
  }
}

function lt_control($tag, $options) {
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = [];
    $table['tag'] = $tag;
    $table['options'] = $options;
    $tables[] = $table;
    return;
  }

  $divstr = ' <div id="' . $tag . '" class="lt-control" data-source="' . $basename . ':' . $tag . '"';
  $divstr .= ' data-options="' . base64_encode(json_encode($options)) . '"></div>';
  print $divstr;
}

function lt_text($tag, $query, $funcparams, $format, $options = array()) {
  global $tables;
  global $basename;

  if (!$basename) {
    $table = array();
    $table['tag'] = $tag;
    $table['query'] = $query;
    $table['options'] = $options;
    $table['format'] = $format;
    $tables[] = $table;
    return;
  }

  if (!empty($options['classes']['div'])) $divclasses = 'lt-div-text ' . $options['classes']['div'];
  else $divclasses = 'lt-div-text';

  $divstr = ' <div id="' . $tag . '" class="' . $divclasses . '" data-source="' . $basename . ':' . $tag . '"';
  if (!empty($funcparams)) $divstr .= ' data-params="' . base64_encode(json_encode($funcparams)) . '"';
  if (!empty($options['embed'])) $divstr .= ' data-embedded="' . base64_encode(lt_query_to_string($query, $funcparams, $format)) . '"';
  print $divstr . "></div>\n";
}

function lt_table($tag, $title, $query, $options = array()) {
  global $lt_settings;
  global $tables;
  global $basename; // Set by lt_print_block()
  global $block_options; // Set by lt_print_block()

  if (!$basename) { // lt_table run from data.php
    $table = array();
    $table['tag'] = $tag;
    $table['title'] = $title;
    $table['query'] = $query;
    $table['options'] = $options;
    $tables[] = $table;
    return;
  }

  if (empty($tag)) {
    print "<p>Table in block $basename has no tag specified</p>";
    return;
  }
  if (!is_string($tag)) {
    print "<p>Table in block $basename has an invalid tag specified (is not a string)</p>";
    return;
  }
  if (empty($title)) {
    print "<p>Table $tag in block $basename has no title specified</p>";
    return;
  }
  if (!is_string($title)) {
    print "<p>Table in block $basename has an invalid title specified (is not a string)</p>";
    return;
  }

  if (!empty($block_options['params'])) $params = $block_options['params'];
  elseif (!empty($options['params'])) {
    if (is_numeric($options['params'])) $params = array();
    elseif (is_array($options['params'])) {
      $params = array();
      foreach ($options['params'] as $param) {
        if (!empty($_GET[$param])) $params[] = $_GET[$param];
        else {
          print "<p>Table $tag in block $basename requires $param parameter</p>";
          return;
        }
      }
    }
  }
  else $params = array();
  if (!empty($options['classes']['div'])) $divclasses = 'lt-div ' . $options['classes']['div'];
  else $divclasses = 'lt-div';

  $divstr = ' <div id="' . $tag . '" class="' . $divclasses . '" data-source="' . $basename . ':' . $tag . '"';

  if (!empty($options['embed'])) {
    if (!empty($query)) {
      $data = lt_query($query, $params);
      if (isset($data['error'])) {
        print '<p>Query for table ' . $table['title'] . ' in block ' . $basename . ' returned error: ' . $data['error'] . '</p>';
        return;
      }
      if (empty($lt_settings['checksum']) || ($lt_settings['checksum'] == 'php')) $data['crc'] = crc32(json_encode($data['rows']));
      elseif ($lt_settings['checksum'] == 'psql') {
        $data['crc'] = lt_query_single("SELECT md5(string_agg(q::text, '')) FROM ($query) AS q)");
        if (strpos($data['crc'], 'Error:') === 0) {
          print '<p>Checksum query for table ' . $table['title'] . ' in block ' . $basename . ' returned error: ' . substr($data['crc'], 6);
          return;
        }
      }
    }
    $data['options'] = $options;
    $data['title'] = $title;
    $data['block'] = $basename;
    $data['tag'] = $tag;

    if (!empty($params)) $data['params'] = base64_encode(json_encode($params));
    $divstr .= ' data-embedded="' . "\n" . chunk_split(base64_encode(json_encode($data)), 79, "\n") . '"';
  }

  if (empty($params)) {
    if (!empty($options['params'])) $divstr .= ' data-params="-"';
    else; // This is the default case; no addition to $divstr necessary
  }
  else $divstr .= ' data-params="' . base64_encode(json_encode($params)) . '"';

  if (!empty($block_options['active'])) $divstr .= ' data-active="' . $block_options['active'] . '"';

  print $divstr . '>Loading table ' . $title . "...</div>\n";
}

function lt_calendar($tag, $queries, $options = array()) {
  global $lt_settings;
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = array();
    $table['tag'] = $tag;
    $table['queries'] = $queries;
    $table['options'] = $options;
    $tables[] = $table;
  }
}
function lt_gantt($tag, $queries, $options = array()) {
  global $lt_settings;
  global $tables;
  global $basename;

  if (!$basename) { // run from data.php
    $table = array();
    $table['tag'] = $tag;
    $table['queries'] = $queries;
    $table['options'] = $options;
    $tables[] = $table;
  }
}

function lt_print_block($block, $params = array(), $options = array()) {
  global $lt_settings;
  global $basename;
  global $block_options;
  global $block_params;
  global $mch; // May be used in block definitions

  if (!is_array($params)) {
    print "Second parameter to lt_print_block('$block', ...) is not an array";
    return;
  }
  if (!is_array($options)) {
    print "Third parameter to lt_print_block('$block', ...) is not an array";
    return;
  }

  $basename_prev = $basename;
  $basename = $block;
  $block_options = $options;
  $block_params = $params;

  // if ($lt_settings['security'] == 'php') {
  //   if (empty($lt_settings['allowed_blocks_query'])) {
  //     print "Configuration sets security to 'php' but no allowed_blocks_query defined";
  //     return;
  //   }
  //   if (!($res = $dbh->query($lt_settings['allowed_blocks_query']))) {
  //     $err = $dbh->errorInfo();
  //     print "Allowed-blocks query returned error: " . $err[2];
  //     return;
  //   }
  //   $allowed_blocks = $res->fetchAll(PDO::FETCH_COLUMN, 0);
  //   if (!in_array($basename, $allowed_blocks)) {
  //     print "Block $basename is not in our list of allowed blocks";
  //     return;
  //   }
  // }

  if (is_array($lt_settings['blocks_dir'])) $dirs = $lt_settings['blocks_dir'];
  else $dirs[] = $lt_settings['blocks_dir'];

  print '<div id="block_' . $basename . '" class="lt-block';
  if (!empty($block_options['class'])) print ' ' . $block_options['class'];
  print "\">\n";

  foreach($dirs as $dir) {
    if (file_exists($dir . $basename . '.html')) {
      readfile($dir . $basename . '.html');
      print "</div>\n";
      $basename = $basename_prev;
      return;
    }
    if (file_exists($dir . $basename . '.yml')) {
      if (!function_exists('yaml_parse_file')) {
        print "YAML block found but the PHP YAML parser is not installed";
        $basename = $basename_prev;
        return;
      }
      $yaml = yaml_parse_file($dir . $basename . '.yml', -1);
      if ($yaml === false) print("<p>YAML syntax error in block $basename</p>");
      else {
        foreach ($yaml as $table) {
          lt_table($table[0], $table[1], $table[2], isset($table[3])?$table[3]:array());
        }
      }
      print "</div>\n";
      $basename = $basename_prev;
      return;
    }
    if (file_exists($dir . $basename . '.php')) {
      // if (!empty($params)) $block_options['params'] = $params;
      try {
        $ret = eval(file_get_contents($dir . $basename . '.php'));
      } catch (Exception $e) {
        print "PHP error in block $basename: " . $e->getMessage();
      }
      print "</div>\n";
      $basename = $basename_prev;
      return $ret;
    }
  }

  print "Block $basename not found in blocks_dir " . implode(", ", $dirs) . " (CWD: " . getcwd() . ")";
  $basename = $basename_prev;
}

function lt_query($query, $params = array(), $id = 0) {
  global $dbh;
  global $block_params;
  $ret = array();

  if (!empty($params)) $localparams = $params;
  elseif (!empty($block_params)) $localparams = $block_params;

  $start = microtime(TRUE);
  if (empty($localparams)) {
    if (!($res = $dbh->query($query))) {
      $err = $dbh->errorInfo();
      $ret['error'] = $err[2];
      return $ret;
    }
  }
  else {
    $paramcount = substr_count($query, '?');
    if (count($localparams) > $paramcount) $localparams = array_slice($localparams, 0, $paramcount);

    if (!($res = $dbh->prepare($query))) {
      $err = $dbh->errorInfo();
      $ret['error'] = $err[2];
      return $ret;
    }
    if (!$res->execute($localparams)) {
      $err = $res->errorInfo();
      $ret['error'] = $err[2];
      return $ret;
    }
  }
  $ret['querytime'] = intval((microtime(TRUE)-$start)*1000);

  if ($id) {
    while ($row = $res->fetch(PDO::FETCH_NUM)) {
      if ($row[0] == $id) {
        $ret['rows'][0] = $row;
        break;
      }
    }
    if (empty($ret['rows'][0])) $ret['error'] = 'row id ' . $id . ' not found';
  }
  else {
    $ret['headers'] = array();
    $ret['types'] = array();
    for ($i = 0; $i < $res->columnCount(); $i++) {
      $col = $res->getColumnMeta($i);
      $ret['headers'][] = $col['name'];
      $ret['types'][] = $col['native_type'];
    }
    $ret['rows'] = $res->fetchAll(PDO::FETCH_NUM);
    $ret['tables'] = lt_tables_from_query($query);

    // Do datatype correction because PHP PDO is dumb about floating point values
    for ($i = 0; $i < $res->columnCount(); $i++) {
      if ($ret['types'][$i] == 'float4') {
        foreach ($ret['rows'] as &$row) $row[$i] = floatval($row[$i]);
      }
    }
  }

  return $ret;
}

function lt_query_to_string($query, $params = array(), $format) {
  global $dbh;
  global $block_options; // Set by lt_print_block()

  if (!empty($params)) $localparams = $params;
  elseif (!empty($block_params)) $localparams = $block_params;

  if (empty($localparams)) {
    if (!($res = $dbh->query($query))) {
      $err = $dbh->errorInfo();
      return "SQL-error: " . $err[2];
    }
  }
  else {
    if (!($res = $dbh->prepare($query))) {
      $err = $dbh->errorInfo();
      return "SQL-error: " . $err[2];
    }
    if (!$res->execute($localparams)) {
      $err = $res->errorInfo();
      return "SQL-error: " . $err[2];
    }
  }
  if (!$res->rowCount()) return "Query for lt_query_to_string() did not return any rows";
  if (!$res->columnCount()) return "Query for lt_query_to_string() did not return any columns";

  $n = 0;
  $ret = "";
  while ($row = $res->fetch(PDO::FETCH_NUM)) {
    $str = $format;
    $n++;
    for ($i = $res->columnCount()-1; $i >= 0; $i--) {
      $str = str_replace('#'.$i, $row[$i], $str);
    }
    $str = str_replace('##', $n, $str);
    $ret .= $str;
  }
  return $ret;
}

function lt_query_single($query, $params = array()) {
  global $dbh;

  if (!empty($params)) {
    if (!($res = $dbh->prepare($query))) {
      $err = $dbh->errorInfo();
      return "Error: query prepare failed: " . $err[2];
    }
    if (!$res->execute($params)) {
      $err = $res->errorInfo();
      return "Error: query execute failed: " . $err[2];
    }
    if (!($row = $res->fetch())) return "";
  }
  else {
    if (!($res = $dbh->query($query))) {
      $err = $dbh->errorInfo();
      return "Error: query failed: " . $err[2];
    }
    if ($res->rowCount() == 0) return "";
    if (!($row = $res->fetch())) return "";
  }
  return $row[0];
}

function lt_tables_from_query($query) {
  if (!preg_match_all('/(?:from|join)\s+([^(\s]+)/i', $query, $matches)) {
    error_log('lt_tables_from_query() failed');
    return;
  }
  return array_keys(array_flip($matches[1]));
}

function lt_query_check($query, $funcparams = []) {
  global $dbh;
  global $block_params;

  if (!empty($funcparams)) $localparams = $funcparams;
  elseif (!empty($block_params)) $localparams = $block_params;

  if (!empty($localparams)) {
    $paramcount = substr_count($query, '?');
    if (count($localparams) > $paramcount) $localparams = array_slice($localparams, 0, $paramcount);
    if (!($res = $dbh->prepare($query))) {
      error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
      return false;
    }
    if (!$res->execute($localparams)) {
      error_log("Libtables error: query execute failed: " . $res->errorInfo()[2]);
      return false;
    }
    if (!($row = $res->fetch())) return false;
  }
  else {
    if (!($res = $dbh->query($query))) {
      error_log("Error: query failed: " . $dbh->errorInfo()[2]);
      return false;
    }
    if ($res->rowCount() == 0) return false;
    if (!($row = $res->fetch(PDO::FETCH_NUM))) return false;
    if (is_null($row[0])) return false;
  }
  return true;
}

function lt_query_count($query) {
  global $dbh;
  if (!($res = $dbh->query('SELECT COUNT(*) FROM (' . $query . ') AS tmp'))) return -1;
  if (!($row = $res->fetch())) return -1;
  if (!is_numeric($row[0])) return -1;
  return $row[0]+0;
}

function lt_update_count($query, $params = []) {
  global $dbh;

    if (!($res = $dbh->prepare($query))) {
      error_log("Libtables error: query prepare failed: " . $dbh->errorInfo()[2]);
      return -1;
    }
    if (!$res->execute($params)) {
      error_log("Libtables error: query execute failed: " . $res->errorInfo()[2]);
      return -1;
    }
    return $res->rowCount();
}

function lt_buttongrid($tag, $queries, $options) {
  print '<div class="buttongrid"><p>This\'ll be the buttongrid...</p></div>';
}

function lt_numpad($tag, $title) {
  print '<div class="numpad">' . $title . '<br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_7" class="numpad_button" type="button" value="7" onclick="numpad_click(\'' . $tag . '\', \'7\');">';
  print '<input id="numpad_button_8" class="numpad_button" type="button" value="8" onclick="numpad_click(\'' . $tag . '\', \'8\');">';
  print '<input id="numpad_button_9" class="numpad_button" type="button" value="9" onclick="numpad_click(\'' . $tag . '\', \'9\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_4" class="numpad_button" type="button" value="4" onclick="numpad_click(\'' . $tag . '\', \'4\');">';
  print '<input id="numpad_button_5" class="numpad_button" type="button" value="5" onclick="numpad_click(\'' . $tag . '\', \'5\');">';
  print '<input id="numpad_button_6" class="numpad_button" type="button" value="6" onclick="numpad_click(\'' . $tag . '\', \'6\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_1" class="numpad_button" type="button" value="1" onclick="numpad_click(\'' . $tag . '\', \'1\');">';
  print '<input id="numpad_button_2" class="numpad_button" type="button" value="2" onclick="numpad_click(\'' . $tag . '\', \'2\');">';
  print '<input id="numpad_button_3" class="numpad_button" type="button" value="3" onclick="numpad_click(\'' . $tag . '\', \'3\');">';
  print '</span><br>';
  print '<span class="numpad_row">';
  print '<input id="numpad_button_0" class="numpad_button" type="button" value="0" onclick="numpad_click(\'' . $tag . '\', \'0\');">';
  print '<div id="numpad_display"></div>';
  print '<input id="numpad_button_c" class="numpad_button" type="button" value="C" onclick="numpad_click(\'' . $tag . '\', null);">';
  print '</span><br>';
  print '</div>';
}

function lt_sqlrun() {
  global $basename; // Set by lt_print_block()

  if (!$basename) { // lt_table run from data.php
    return;
  }

  print <<<END
<p>
  <form action="data.php" method="post">
    <input type="hidden" name="mode" value="sqlrun">
    <textarea id="sqlrun" name="sql" oninput="check_sql(this)" autofocus="autofocus"></textarea><br>
    <input type="button" value="Run" onclick="run_sql(this.parentNode)">
  </form>
</p>
<p>
  <table id="sqlrun:table" class="lt-table"></table>
</p>
END;
}
