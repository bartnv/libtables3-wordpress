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

require('libtables.php');

global $dbh;

function fatalerr($msg, $redirect = "") {
  global $lt_settings;
  $ret['error'] = $msg;
  if (!empty($lt_settings['error_transl'])) {
    foreach ($lt_settings['error_transl'] as $key => $value) {
      if (strpos($msg, $key) !== FALSE) {
        $ret['error'] = $value;
        $ret['details'] = $msg;
      }
    }
  }
  if (!empty($redirect)) $ret['redirect'] = $redirect;
  print json_encode($ret, JSON_PARTIAL_OUTPUT_ON_ERROR);
  exit;
}

function lt_col_allow_null($table, $column) {
  global $dbh;

  if (!($dbtype = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME))) fatalerr('Unable to query SQL server type');
  if ($dbtype == 'mysql') {
    if (!($res = $dbh->query("DESC $table $column"))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL-error: " . $err[2]);
    }
    if ($res->rowCount() != 1) fatalerr('editselect target query returned invalid results');
    $row = $res->fetch();
    if (empty($row['Null'])) fatalerr('editselect target query did not contain a "Null" column');
    if ($row['Null'] == "YES") return true;
    elseif ($row['Null'] == "NO") return false;
    else fatalerr('editselect target query returned invalid "Null" column');
  }
  elseif ($dbtype == 'sqlite') {
    if (!($res = $dbh->query("PRAGMA table_info($table)"))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL-error: " . $err[2]);
    }
    foreach ($res as $row) {
      if ($row['name'] == $column) {
        $found = $row;
        break;
      }
    }
    if (!$found) fatalerr('editselect target query did not return data for column ' . $column);
    if (!isset($found['notnull'])) fatalerr('editselect target query did not contain a "notnull" column');
    if ($found['notnull'] == "1") return false;
    elseif ($found['notnull'] == "0") return true;
    else fatalerr('editselect target query returned invalid "notnull" column');
  }
  elseif ($dbtype == 'pgsql') {
    if (!($res = $dbh->query("SELECT is_nullable FROM information_schema.columns WHERE table_name = '$table' AND column_name = '$column'"))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL-error: " . $err[2]);
    }
    if ($res->columnCount() != 1) fatalerr('editselect target query returned invalid results');
    $row = $res->fetch();
    if (empty($row['is_nullable'])) fatalerr('editselect target query for table ' . $table . ' column ' . $column . ' did not contain a "is_nullable" column (does it exist?)');
    if ($row['is_nullable'] == "YES") return true;
    elseif ($row['is_nullable'] == "NO") return false;
    else fatalerr('editselect target query returned invalid "is_nullable" column');
  }
}

function lt_find_table($src, $params = []) {
  global $lt_settings;
  global $tables;
  global $mch; // May be used in block definitions
  global $parseonly;
  $parseonly = true;

  $src = explode(':', $src);
  if (is_array($lt_settings['blocks_dir'])) $dirs = $lt_settings['blocks_dir'];
  else $dirs[] = $lt_settings['blocks_dir'];

  foreach($dirs as $dir) {
    if (function_exists('yaml_parse_file') && file_exists($dir . $src[0] . '.yml')) {
      $yaml = yaml_parse_file($dir . $src[0] . '.yml', -1);
      if ($yaml === false) fatalerr('YAML syntax error in block ' . $src[0]);
      else {
        foreach ($yaml as $table) {
          lt_table($table[0], $table[1], $table[2], isset($table[3])?$table[3]:array());
        }
      }
      break;
    }
    elseif (file_exists($dir . $src[0] . '.php')) {
      ob_start();
      if (eval(file_get_contents($dir . $src[0] . '.php')) === FALSE) fatalerr('PHP syntax error in block ' . $src[0]);
      ob_end_clean();
      break;
    }
  }

  if (!empty($error)) fatalerr($error, $redirect);
  if (count($tables) == 0) fatalerr('Source ' . $src[0] . ':' . $src[1] . ' not found');

  $table = 0;
  foreach ($tables as $atable) {
    if (isset($atable['tag']) && ($atable['tag'] === $src[1])) {
      $atable['block'] = $src[0];
      if (!empty($lt_settings['default_options'])) $atable['options'] = array_merge($lt_settings['default_options'], $atable['options']);
      return $atable;
    }
  }
  fatalerr('Table ' . $src[1] . ' not found in block ' . $src[0]);
}

function lt_find_pk_column($table) {
  global $lt_settings;
  if (!empty($lt_settings['pk_columns']) && !empty($lt_settings['pk_columns'][$table])) return $lt_settings['pk_columns'][$table];
  return 'id';
}

function allowed_block($block) {
  if (!empty($lt_settings['security']) && ($lt_settings['security'] == 'php')) {
    if (empty($lt_settings['allowed_blocks_query'])) fatalerr("Configuration sets security to 'php' but no allowed_blocks_query defined");
    if (!($res = $dbh->query($lt_settings['allowed_blocks_query']))) {
      $err = $dbh->errorInfo();
      fatalerr("Allowed-blocks query returned error: " . $err[2]);
    }
    $allowed_blocks = $res->fetchAll(\PDO::FETCH_COLUMN, 0);
    if (!in_array($basename, $allowed_blocks)) return false;
  }
  return true;
}

function lt_remove_parens($str) {
  $c = 0;
  $ret = "";
  for ($i = 0; $i < strlen($str); $i++) {
    if ($str[$i] == '(') $c++;
    elseif ($str[$i] == ')') {
      $c--;
      continue;
    }
    if ($c == 0) $ret .= $str[$i];
  }
  return $ret;
}
function lt_edit_from_query($query) {
  if (!preg_match('/^\s*SELECT (.*) FROM (\S+)(.*)$/i', $_POST['sql'], $matches)) return false;
  $cols = preg_split('/\s*,\s*/', lt_remove_parens($matches[1]));
  $firsttable = $matches[2];
  $joins = array();
  preg_match_all('/JOIN\s+([^ ]+)\s+ON\s+([^ .]+\.[^ ]+)\s*=\s*([^ .]+\.[^ ]+)/i', $matches[3], $sets, PREG_SET_ORDER);
  foreach ($sets as $set) {
    $left = explode('.', $set[2]);
    $right = explode('.', $set[3]);
    if (($left[0] == $set[1]) && ($left[1] == 'id') && ($right[0] == $firsttable)) $joins[$set[1]] = array('pk' => $set[2], 'fk' => $set[3]);
    elseif (($right[1] == 'id') && ($left[0] == $firsttable)) $joins[$set[1]] = array('pk' => $set[3], 'fk' => $set[2]);
  }
  $edit = array();
  for ($i = 0; $i < count($cols); $i++) {
    if (strpos($cols[$i], '.') === false) continue;
    $val = explode('.', $cols[$i]);
    if ($val[0] == $firsttable) {
      if ($i) $edit[$i] = $cols[$i];
    }
    elseif ($i == 0) return false;
    elseif ($joins[$val[0]]) {
      $edit[$i] = array($joins[$val[0]]['fk'], 'SELECT id, ' . $val[1] . ' FROM ' . $val[0]);
    }
  }
  return $edit;
}

function replaceHashes($str, $row) {
  $str = str_replace('#id', $row[0], $str);
  if (strpos($str, '#') !== FALSE) {
    for ($i = count($row)-1; $i >= 0; $i--) $str = str_replace('#' . $i, $row[$i], $str);
  }
  return $str;
}

if (!empty($_GET['mode'])) $mode = $_GET['mode'];
elseif (!empty($_POST['mode'])) $mode = $_POST['mode'];
else fatalerr('No mode specified');

switch ($mode) {
  case 'getblock':
    if (empty($_GET['block'])) fatalerr('No blockname specified in mode getblock');
    if (!allowed_block($_GET['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (preg_match('/(\.\.|\\|\/)/', $_GET['block'])) fatalerr('Invalid blockname in mode getblock');
    if (!empty($_GET['params'])) {
      if (!($params = json_decode(base64_decode($_GET['params'])))) fatalerr('Invalid params in mode getblock');
      lt_print_block($_GET['block'], $params);
    }
    else lt_print_block($_GET['block']);
  break;
  case 'gettable':
    if (empty($_GET['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_GET['src'])) fatalerr('Invalid src in mode gettable');
    if (!empty($_GET['params'])) $params = json_decode(base64_decode($_GET['params']));
    else $params = array();

    $table = lt_find_table($_GET['src'], $params);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (is_array($table['query'])) {
      $data['headers'] = $table['query'];
      $data['rowcount'] = -1;
    }
    elseif (isset($table['options']['export']['nopreview']) && $table['options']['export']['nopreview']) {
      $data = lt_query($table['query'] . ' LIMIT 0', $params);
      $data['rowcount'] = lt_query_single('SELECT COUNT(*) FROM (' . $table['query'] . ') AS tmp', $params);
    }
    else $data = lt_query($table['query'], $params);
    if (isset($data['error'])) fatalerr('Query for table ' . $table['title'] . ' in block ' . $table['block'] . " returned error:\n\n" . $data['error']);
    $data['block'] = $table['block'];
    $data['tag'] = $table['tag'];
    if (!empty($table['options']['titlequery'])) $data['title'] = lt_query_single($table['options']['titlequery'], $params);
    else $data['title'] = $table['title'];
    $data['options'] = $table['options'];
    if (!empty($data['options']['tablefunction']['hidecondition'])) $data['options']['tablefunction']['hidecondition'] = lt_query_single($data['options']['tablefunction']['hidecondition'], $params);
    if (!empty($data['options']['selectany'])) {
      $sa = $data['options']['selectany'];
      if (!empty($sa['id'])) $tmp = lt_query('SELECT ' . $sa['fields'][1] . ' FROM ' . $sa['linktable'] . ' WHERE ' . $sa['fields'][0] . ' = ?', [ $sa['id'] ]);
      else $tmp = lt_query('SELECT ' . $sa['fields'][1] . ' FROM ' . $sa['linktable'] . ' WHERE ' . $sa['fields'][0] . ' = ?', $params);
      $data['options']['selectany']['links'] = array_column($tmp['rows'], 0);
    }
    if (empty($lt_settings['checksum']) || ($lt_settings['checksum'] == 'php')) $data['crc'] = crc32(json_encode($data['rows'], JSON_PARTIAL_OUTPUT_ON_ERROR));
    elseif ($lt_settings['checksum'] == 'psql') {
      $data['crc'] = lt_query_single("SELECT md5(string_agg(q::text, '')) FROM (" . $table['query'] . ") AS q)");
      if (strpos($data['crc'], 'Error:') === 0) fatalerr('<p>Checksum query for table ' . $table['title'] . ' in block ' . $table['block'] . ' returned error: ' . substr($data['crc'], 6));
    }
    if ($params) $data['params'] = base64_encode(json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR));
    header('Content-type: application/json; charset=utf-8');
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'sqlrun':
    $matches = array();
    if (empty($lt_settings) || ($lt_settings['security'] != 'none')) fatalerr('SQLrun not enabled due to security setting');
    if (empty($_POST['sql']) || !preg_match('/^\s*SELECT /i', $_POST['sql'])) fatalerr('Invalid sql in mode sqlrun');
    $data = lt_query($_POST['sql']);
    $data['title'] = 'sqlrun';
    $data['tag'] = 'sqlrun';
    $data['options'] = [ 'sql' => $_POST['sql'], 'showid' => true, 'edit' => lt_edit_from_query($_POST['sql']) ];
    if (empty($lt_settings['checksum']) || ($lt_settings['checksum'] == 'php')) $data['crc'] = crc32(json_encode($data['rows'], JSON_PARTIAL_OUTPUT_ON_ERROR));
    elseif ($lt_settings['checksum'] == 'psql') {
      $data['crc'] = lt_query_single("SELECT md5(string_agg(q::text, '')) FROM (" . $_POST['sql'] . ") AS q)");
      if (strpos($data['crc'], 'Error:') === 0) fatalerr('<p>Checksum query for table sqlrun returned error: ' . substr($data['crc'], 6));
    }
    header('Content-type: application/json; charset=utf-8');
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'refreshtable':
    if (empty($_GET['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_GET['src'])) fatalerr('Invalid src in mode refreshtable');
    if (empty($_GET['crc'])) fatalerr('No crc passed in mode refreshtable');
    if (!empty($_GET['params'])) $params = json_decode(base64_decode($_GET['params']));
    else $params = array();

    $table = lt_find_table($_GET['src'], $params);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    $data = lt_query($table['query'], $params);
    if (isset($data['error'])) fatalerr('Query for table ' . $table['title'] . ' in block ' . $src[0] . ' returned error: ' . $data['error']);
    header('Content-type: application/json; charset=utf-8');
    if (empty($lt_settings['checksum']) || ($lt_settings['checksum'] == 'php')) $crc = crc32(json_encode($data['rows'], JSON_PARTIAL_OUTPUT_ON_ERROR));
    elseif ($lt_settings['checksum'] == 'psql') $crc = lt_query_single("SELECT md5(string_agg(q::text, '')) FROM (" . $table['query'] . ") AS q)");
    if ($crc == $_GET['crc']) {
      $ret['nochange'] = 1;
      print json_encode($ret, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    else {
      $data['crc'] = $crc;
      if (!empty($table['options']['tablefunction']['hidecondition'])) $data['options']['tablefunction']['hidecondition'] = lt_query_single($table['options']['tablefunction']['hidecondition'], $params);
      print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    break;
  case 'refreshtext':
    if (empty($_GET['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_GET['src'])) fatalerr('Invalid src in mode refreshtext');
    if (!empty($_GET['params'])) $params = json_decode(base64_decode($_GET['params']));
    else $params = array();

    $table = lt_find_table($_GET['src'], $params);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    $ret['text'] = lt_query_to_string($table['query'], $params, $table['format']);
    print json_encode($ret);
    break;
  case 'select':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode select');
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) fatalerr('Invalid row id in mode select');
    if (empty($_POST['link'])) fatalerr('Invalid link data in mode select');
    if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
    else $params = [];

    $table = lt_find_table($_POST['src'], $params);
    if (empty($table['options']['selectany'])) fatalerr('No selectany option found for table ' . $_POST['src']);
    if (empty($table['options']['selectany']['linktable'])) fatalerr('No linktable found for table ' . $_POST['src']);
    if (empty($table['options']['selectany']['fields'][0])) fatalerr('No left field found for table ' . $_POST['src']);
    if (empty($table['options']['selectany']['fields'][1])) fatalerr('No right field found for table ' . $_POST['src']);
    if (!empty($table['options']['selectany']['id'])) $params = [ $table['options']['selectany']['id'] ];
    if ($_POST['link'] === "true") {
      if (!($stmt = $dbh->prepare("INSERT INTO " . $table['options']['selectany']['linktable'] . " (" . $table['options']['selectany']['fields'][0] . ", " . $table['options']['selectany']['fields'][1] . ") VALUES (?, " . $_POST['id'] . ")"))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!$stmt->execute($params)) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    else {
      if (!($stmt = $dbh->prepare("DELETE FROM " . $table['options']['selectany']['linktable'] . " WHERE " . $table['options']['selectany']['fields'][0] . " = ? AND " . $table['options']['selectany']['fields'][1] . " = " . $_POST['id']))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!$stmt->execute($params)) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    print '{ "status": "ok" }';
    break;
  case 'inlineedit':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode inlineedit');
    if (empty($_POST['col']) || !is_numeric($_POST['col'])) fatalerr('Invalid column id in mode inlineedit');
    if (empty($_POST['row']) || !is_numeric($_POST['row'])) fatalerr('Invalid row id in mode inlineedit');
    if (!isset($_POST['val'])) fatalerr('No value specified in mode inlineedit');
    if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
    else $params = array();

    if (($_POST['src'] == 'sqlrun:table') && (!empty($_POST['sql']))) {
      if (!($edit = lt_edit_from_query($_POST['sql']))) fatalerr('Invalid SQL in sqlrun inlineedit');
      $table['title'] = 'sqlrun';
      $table['query'] = $_POST['sql'];
    }
    else {
      $table = lt_find_table($_POST['src'], $params);
      if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
      if (empty($table['options']['edit'][$_POST['col']])) fatalerr('No edit option found for column ' . $_POST['col'] . ' in table ' . $_POST['src']);
      $edit = $table['options']['edit'][$_POST['col']];
    }

    $type = 'default';
    if (is_array($edit)) {
      if (!empty($edit)) {
        if (!empty($edit['type'])) $type = $edit['type'];
        if (!empty($edit['target'])) $target = $edit['target'];
        elseif (count($edit) >= 2) $target = $edit[0];
        else fatalerr('Invalid edit settings for column ' . $_POST['col'] . ' in table ' . $_POST['src']);
      }
      else $target = $edit[0];
    }
    else $target = $edit;

    if (!preg_match('/^[a-z0-9_-]+\.[a-z0-9_-]+$/', $target)) fatalerr('Invalid target specified for column ' . $_POST['col'] . ' in table ' . $_POST['src'] . ' (' . $target . ')');
    $target = explode('.', $target);

    if ($_POST['val'] == '') {
      if (!($stmt = $dbh->prepare('UPDATE ' . $target[0] . ' SET ' . $target[1] . ' = NULL WHERE ' . lt_find_pk_column($target[0]) . ' = ?'))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($_POST['row'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    elseif (($type == 'checkbox') && !empty($edit['truevalue']) && ($edit['truevalue'] === $_POST['val'])) {
      if (!($stmt = $dbh->prepare('UPDATE ' . $target[0] . ' SET ' . $target[1] . ' = TRUE WHERE ' . lt_find_pk_column($target[0]) . ' = ?'))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($_POST['row'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    elseif (($type == 'checkbox') && !empty($edit['falsevalue']) && ($edit['falsevalue'] === $_POST['val'])) {
      if (!($stmt = $dbh->prepare('UPDATE ' . $target[0] . ' SET ' . $target[1] . ' = FALSE WHERE ' . lt_find_pk_column($target[0]) . ' = ?'))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($_POST['row'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    elseif (!empty($edit['phpfunction'])) {
      $func = 'return ' . str_replace('?', "'" . $_POST['val'] . "'", $edit['phpfunction']) . ';';
      $ret = eval($func);
      if (!($stmt = $dbh->prepare('UPDATE ' . $target[0] . ' SET ' . $target[1] . ' = ? WHERE ' . lt_find_pk_column($target[0]) . ' = ?'))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($ret, $_POST['row'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    elseif (!empty($edit['sqlfunction'])) {
      if (strpos($edit['sqlfunction'], '#') >= 0) {
        if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
        else $params = array();
        $data = lt_query($table['query'], $params, $_POST['row']);
        // Do search-and-replace of # here
        if (!empty($data['error'])) fatalerr($data['error']);
        $sqlfunc = str_replace('#id', $data['rows'][0][0], $edit['sqlfunction']);
        for ($i = count($data['rows'][0]); $i >= 0; $i--) {
          if ((strpos($sqlfunc, '#'.$i) >= 0) && !empty($data['rows'][0][$i])) $sqlfunc = str_replace('#'.$i, $data['rows'][0][$i], $sqlfunc);
        }
      }
      else $sqlfunc = $edit['sqlfunction'];
      if (!($stmt = $dbh->prepare('UPDATE ' . $target[0] . ' SET ' . $target[1] . ' = ' . $sqlfunc . ' WHERE ' . lt_find_pk_column($target[0]) . ' = ?'))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($_POST['val'], $_POST['row'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    else {
      if (!($stmt = $dbh->prepare('UPDATE ' . $target[0] . ' SET ' . $target[1] . ' = ? WHERE ' . lt_find_pk_column($target[0]) . ' = ?'))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($_POST['val'], $_POST['row'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }

    if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
    else $params = array();
    $data = lt_query($table['query'], $params, $_POST['row']);
    if (isset($data['error'])) fatalerr('Query for table ' . $table['title'] . ' in block ' . $src[0] . " returned error:\n\n" . $data['error']);
    $data['input'] = $_POST['val'];
    header('Content-type: application/json; charset=utf-8');
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);

    break;
  case 'selectbox':
    if (empty($_GET['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_GET['src'])) fatalerr('Invalid src in mode selectbox');
    if (empty($_GET['col']) || !is_numeric($_GET['col'])) fatalerr('Invalid column id in mode selectbox');
    if (!empty($_GET['params'])) $params = json_decode(base64_decode($_GET['params']));
    else $params = array();

    if (($_GET['src'] == 'sqlrun:table') && (!empty($_GET['sql']))) {
      if (!($edit = lt_edit_from_query($_GET['sql']))) fatalerr('Invalid SQL in sqlrun selectbox');
      $table['title'] = 'sqlrun';
      $table['query'] = $_GET['sql'];
    }
    else {
      $table = lt_find_table($_GET['src'], $params);
      if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
      $edit = $table['options']['edit'];
    }

    if (!empty($edit[$_GET['col']])) $edit = $edit[$_GET['col']];
    elseif (!empty($table['options']['insert']) && !empty($table['options']['insert'][$_GET['col']])) $edit = $table['options']['insert'][$_GET['col']];
    else fatalerr('No edit option found for column ' . $_GET['col'] . ' in table ' . $_GET['src']);

    if (!is_array($edit)) fatalerr('No editselect option found for column ' . $_GET['col'] . ' in table ' . $_GET['src']);
    if (count($edit) < 2) fatalerr('No valid editselect option found for column ' . $_GET['col'] . ' in table ' . $_GET['src']);
    if (!empty($edit['target'])) $target = $edit['target'];
    else $target = $edit[0];
    if (!empty($edit['query'])) $query = $edit['query'];
    else $query = $edit[1];
    if (!preg_match('/^[a-z0-9_-]+\.[a-z0-9_-]+$/', $target)) fatalerr('Invalid target specified for column ' . $_GET['col'] . ' in table ' . $_GET['src'] . ' (' . $target . ')');
    $target = explode('.', $target);

    if (!($res = $dbh->query($query))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL error: " . $err[2]);
    }
    $data = array();
    $data['items'] = $res->fetchAll(\PDO::FETCH_NUM);
    $data['null'] = lt_col_allow_null($target[0], $target[1]);
    if (!empty($edit['insert']) || !empty($edit[2])) $data['insert'] = true;
    header('Content-type: application/json; charset=utf-8');
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'function':
    if (empty($_POST['type'])) fatalerr('No type specified for mode function');
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode function');
    if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
    else $params = [];

    $table = lt_find_table($_POST['src']);
    $ret = [];
    if ($_POST['type'] == 'table') {
      if (empty($table['options']['tablefunction'])) fatalerr('No tablefunction defined in block ' . $_POST['src']);
      $action = $table['options']['tablefunction'];
      // if (empty($table['options']['tablefunction']['query'])) fatalerr('No tablefunction query defined in block ' . $_POST['src']);

      if ($table['options']['tablefunction']['runphp']) {
        try {
          $ret['output'] = eval(replaceHashes($table['options']['tablefunction']['runphp'], $params));
        } catch (Exception $e) {
          $ret['error'] = "PHP error in tablefunction runphp: " . $e->getMessage();
        }
      }

      if ($table['options']['tablefunction']['runquery']) {
        if (!($stmt = $dbh->prepare($table['options']['tablefunction']['query']))) {
          $err = $dbh->errorInfo();
          fatalerr("SQL prepare error: " . $err[2]);
        }
        if (!($stmt->execute($params))) {
          $err = $stmt->errorInfo();
          fatalerr("SQL execute error: " . $err[2]);
        }
        $ret['row'] = $stmt->fetch(\PDO::FETCH_NUM);
      }
      // if (!empty($table['options']['tablefunction']['redirect'])) {
      //   if (strpos($table['options']['tablefunction']['redirect'], '#id') !== FALSE) {
      //     $id = $dbh->lastInsertId();
      //     $ret['redirect'] = str_replace('#id', $id, $table['options']['tablefunction']['redirect']);
      //   }
      //   elseif (strpos($table['options']['tablefunction']['redirect'], '#') !== FALSE) {
      //     $row = $stmt->fetch(\PDO::FETCH_NUM);
      //     $str = $table['options']['tablefunction']['redirect'];
      //     for ($i = count($row)-1; $i >= 0; $i--) $str = str_replace('#' . $i, $row[$i], $str);
      //     $ret['redirect'] = $str;
      //   }
      // }
    }
    else if ($_POST['type'] == 'row') {
      if (empty($table['options']['actions'])) fatalerr('No row actions defined in block ' . $_POST['src']);
      if (!isset($_POST['action'])) fatalerr('No action id passed in mode function in block ' . $_POST['src']);
      if (empty($table['options']['actions'][intval($_POST['action'])])) fatalerr('Action #' . $_POST['action'] . ' not found for table ' . $_POST['src']);
      $action = $table['options']['actions'][intval($_POST['action'])];
      if (empty($_POST['row'])) fatalerr('No row id passed in mode function in block ' . $_POST['src']);
      if (!is_numeric($_POST['row'])) fatalerr('Invalid row id passed in mode function in block ' . $_POST['src']);
      $id = intval($_POST['row']);
      $data = lt_query($table['query'], $params, $id);
      if (empty($data['rows'])) fatalerr('Row with id ' . $_POST['row'] . ' not found in mode function in block ' . $_POST['src']);
      $ret = [];
      $ret['row'] = $data['rows'][0];
      // if (!empty($action['query'])) {
      //   $query = replaceHashes($action['query'], $row);
      //   if (!$dbh->query($query)) {
      //     $ret['error'] = $dbh->errorInfo()[2];
      //   }
      // }
    }
    else fatalerr('Invalid type in mode function');

    if (!empty($action['setvar'])) {
      foreach ($action['setvar'] as $name => $value) {
        lt_var($name, replaceHashes($value, $ret['row']));
      }
    }
    if (!empty($action['runblock'])) {
      ob_start();
      lt_print_block($action['runblock'], $params);
      $ret['output'] = ob_get_clean();
    }
    print json_encode($ret, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'excelexport':
    include('3rdparty/xlsxwriter.class.php');

    if (empty($_GET['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_GET['src'])) fatalerr('Invalid src in mode excelexport');

    $table = lt_find_table($_GET['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');

    $data = lt_query($table['query']);
    if (isset($data['error'])) fatalerr('Query for table ' . $table['title'] . ' in block ' . $src[0] . ' returned error: ' . $data['error']);
    $types = str_replace([ 'int4', 'int8', 'float4', 'float8', 'bool', 'text' ], [ 'integer', 'integer', '#,##0.00', '#,##0.00', 'integer', 'string' ], $data['types']);
    $headers = array_combine($data['headers'], $types);
    $writer = new \XLSXWriter();
    if (!empty($table['options']['export']['hideid']) && $table['options']['export']['hideid']) array_shift($headers);
    $writer->writeSheetHeader('Sheet1', $headers, array('font-style' => 'bold', 'border' => 'bottom'));
    foreach ($data['rows'] as $row) {
      if (!empty($table['options']['export']['hideid']) && $table['options']['export']['hideid']) array_shift($row);
      $writer->writeSheetRow('Sheet1', $row);
    }
    header('Content-disposition: attachment; filename="'.\XLSXWriter::sanitize_filename($table['title'] . '.xlsx').'"');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    $writer->writeToStdOut();
    break;
  case 'addoption':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode addoption');
    if (empty($_POST['col']) || !is_numeric($_POST['col'])) fatalerr('No (valid) column value passed in mode addoption');
    if (empty($_POST['option'])) fatalerr('No option value passed in mode addoption');
    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $table['block'] . ' denied');
    if (empty($table['options']['insert'])) fatalerr('Table ' . $_POST['src'] . ' has no insert option configured');
    if (!empty($table['options']['insert'][$_POST['col']])) $edit = $table['options']['insert'][$_POST['col']];
    elseif (!empty($table['options']['insert']['include']) && ($table['options']['insert']['include'] == 'edit') && !empty($table['options']['edit'][$_POST['col']])) {
      $edit = $table['options']['edit'][$_POST['col']];
    }
    else fatalerr('No matching insert/edit option found for table ' . $_POST['src'] . ' column ' . $_POST['col'] . ' in mode addoption');
    if (!empty($edit['insert'])) $insert = $edit['insert'];
    elseif (!empty($edit[2])) $insert = $edit[2];
    else fatalerr('No insert suboption within select option of table ' . $_POST['src'] . ' column ' . $_POST['col'] . ' in mode addoption');
    if (!empty($insert['idcolumn']) && !empty($insert['valuecolumn'])) {
      list($idtable, $idcolumn) = explode('.', $insert['idcolumn']);
      list($valuetable, $valuecolumn) = explode('.', $insert['valuecolumn']);
    }
    elseif (!empty($insert[0]) && !empty($insert[1])) {
      list($idtable, $idcolumn) = explode('.', $insert[0]);
      list($valuetable, $valuecolumn) = explode('.', $insert[1]);
    }
    else fatalerr('Invalid insert suboption found within select option of table ' . $_POST['src'] . ' column ' . $_POST['col'] . ' in mode addoption');
    if (!$idtable || !$idcolumn || !$valuetable || !$valuecolumn) fatalerr('Invalid data found in insert suboption within select option of table ' . $_POST['src'] . ' column ' . $_POST['col'] . ' in mode addoption');
    if ($idtable != $valuetable) fatalerr('Different id and value tables specified in insert suboption within select option of table ' . $_POST['src'] . ' column ' . $_POST['col'] . ' in mode addoption');
    $query['columns'][$valuecolumn] = $_POST['option'];
    $data['insertid'] = lt_run_insert($idtable, $query, $idcolumn);
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'insertrow':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode insertrow');
    if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
    else $params = array();

    $tableinfo = lt_find_table($_POST['src'], $params);
    if (!allowed_block($tableinfo['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    $tables = array();

    // Process the $_POST variables into a table -> column -> value multidimensional array
    foreach ($_POST as $key => $value) {
      if (strpos($key, ':')) {
        list($table, $column) = explode(':', $key);
        if (!$table || !$column) fatalerr('<p>Incorrect target specification in mode insert: ' . $key);
        if ($value === "") {
          // Check required fields here
        }
        else $tables[$table]['columns'][$column] = $value;
      }
    }

    // Check whether there is a matching insert field for each table.column combination
    $fields = $tableinfo['options']['insert'];
    if (!empty($fields['include']) && ($fields['include'] == 'edit')) $fields += $tableinfo['options']['edit'];
    if (!empty($tableinfo['options']['insert']['keys'])) $keys = $tableinfo['options']['insert']['keys'];
    else $keys = [];

    if (!empty($fields['onconflict'])) {
      foreach ($fields['onconflict'] as $tabname => $value) {
        $tables[$tabname]['onconflict'] = $fields['onconflict'][$tabname];
      }
    }

    foreach ($tables as $tabname => $insert) {
      foreach ($insert['columns'] as $colname => $value) {
        $found = null;
        foreach ($fields as $id => $options) {
          if (($id == 'keys') || ($id == 'include') || ($id =='onconflict') || ($id == 'noclear') || ($id == 'submit')) continue;
          if ($id == 'hidden') {
            foreach ($options as $hidden) {
              if (!empty($hidden['target']) && ($hidden['target'] == "$tabname.$colname")) {
                if (!isset($hidden['value'])) fatalerr("Hidden insert field has no value entry in block " . $_POST['src']);
                if (strpos($hidden['value'], '#') === FALSE) { // Hardcoded value, probably from $_SESSION
                  if ($hidden['value'] != $value) fatalerr("Illegal hidden value override in mode insertrow for table $tabname column $colname");
                }
                elseif (strpos($hidden['value'], '#param') === 0) { // Parameter value
                  $no = intval(substr($hidden['value'], 6));
                  if ($no <= 0) fatalerr("Invalid #param entry in hidden insert field $colname in block" . $_POST['src']);
                  if (!isset($params[$no-1])) fatalerr("Param no $no not found for hidden insert field $colname in block " . $_POST['src']);
                  if ($params[$no-1] != $value) fatalerr("Illegal hidden value override in mode insertrow for table $tabname column $colname");
                }
                $found = $options;
                break;
              }
            }
          }
          if (is_string($options)) $target = $options;
          elseif (!empty($options['target'])) $target = $options['target'];
          elseif (!empty($options[0])) $target = $options[0];
          if ($target == "$tabname.$colname") {
            $found = $options;
            break;
          }
          if (is_string($options)) continue;
          elseif (!empty($options['insert'])) $newinsert = $options['insert'];
          elseif (!empty($options[2])) $newinsert = $options[2];
          else continue;
          if (!empty($newinsert['target']) && ($newinsert['target'] == "$tabname.$colname")) {
            if (!empty($newinsert['id']) && !empty($target)) $keys[$newinsert['id']] = $target;
            $found = $options;
            break;
          }
          if (!empty($newinsert[1]) && ($newinsert[1] == "$tabname.$colname")) {
            if (!empty($newinsert[0]) && !empty($target)) $keys[$newinsert[0]] = $target;
            $found = $options;
            break;
          }
        }
        if (!$found) fatalerr("No valid insert option found for table $tabname column $colname");
        if (!empty($found['phpfunction'])) {
          $func = 'return ' . str_replace('?', "'" . $value . "'", $found['phpfunction']) . ';';
          $tables[$tabname]['columns'][$colname] = eval($func);
        }
        if (!empty($found['sqlfunction'])) {
          $tables[$tabname]['sqlfunction'][$colname] = $found['sqlfunction'];
        }
      }
    }

    if (empty($fields['onconflict'])) $dbh->query('BEGIN'); // Start a transaction so we can't have partial inserts with multiple tables

    // First insert the values that have explicit ordering requirements in the `keys` option
    if (!empty($keys)) {
      foreach ($keys as $pkey => $fkey) {
        list($ptable, $pcolumn) = explode('.', $pkey);
        list($ftable, $fcolumn) = explode('.', $fkey);
        if (!isset($tables[$ftable]['columns'])) fatalerr('Invalid sequence in insert keys option to block ' . $_POST['src']);
        $id = lt_run_insert($ptable, $tables[$ptable], $pcolumn);
        $tables[$ftable]['columns'][$fcolumn] = $id;
        unset($tables[$ptable]['columns']);
      }
    }
    // Then run the rest of the inserts
    foreach ($tables as $name => $value) {
      if (!isset($tables[$name]['columns'])) continue;
      $id = lt_run_insert($name, $tables[$name], lt_find_pk_column($name));
      unset($tables[$name]['columns']); // May not be necessary
    }

    if (empty($fields['onconflict'])) $dbh->query('COMMIT'); // Any errors will exit through fatalerr() and thus cause an implicit rollback

    if (!empty($tableinfo['options']['insert']['next'])) {
      $data = [];
      ob_start();
      lt_print_block($tableinfo['options']['insert']['next'], [ $id ]);
      $data['replace'] = ob_get_clean();
      print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
      break;
    }

    header('Content-type: application/json; charset=utf-8');
    if (is_string($tableinfo['query'])) {
      $data = lt_query($tableinfo['query'], $params);
      if (isset($data['error'])) fatalerr('Query for table ' . $tableinfo['title'] . ' in block ' . $src[0] . ' returned error: ' . $data['error']);
      if (empty($lt_settings['checksum']) || ($lt_settings['checksum'] == 'php')) $data['crc'] = crc32(json_encode($data['rows'], JSON_PARTIAL_OUTPUT_ON_ERROR));
      elseif ($lt_settings['checksum'] == 'psql') {
        $data['crc'] = lt_query_single("SELECT md5(string_agg(q::text, '')) FROM (" . $tableinfo['query'] . ") AS q)");
        if (strpos($data['crc'], 'Error:') === 0) fatalerr('<p>Checksum query for table ' . $tableinfo['title'] . ' returned error: ' . substr($data['crc'], 6));
      }
    }
    else $data = [ 'status' => 'ok' ];
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'deleterow':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode deleterow');
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) fatalerr('Invalid delete id in mode deleterow');
    if (!empty($_POST['params'])) $params = json_decode(base64_decode($_POST['params']));
    else $params = array();

    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (empty($table['options']['delete']['table'])) fatalerr('No table defined in delete option in block ' . $_POST['src']);
    $target = $table['options']['delete']['table'];

    if (!empty($table['options']['delete']['update'])) {
      if (empty($table['options']['delete']['update']['column'])) fatalerr('No column defined in update setting for delete option in block ' . $_POST['src']);
      if (!isset($table['options']['delete']['update']['value'])) fatalerr('No value defined in update setting for delete option in block ' . $_POST['src']);
      if (!($stmt = $dbh->prepare("UPDATE " . $target . " SET " . $table['options']['delete']['update']['column'] . " = ? WHERE ' . lt_find_pk_column($target) . ' = ?"))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($table['options']['delete']['update']['value'], $_POST['id'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    else {
      if (!($stmt = $dbh->prepare("DELETE FROM " . $target . " WHERE ' . lt_find_pk_column($target) . ' = ?"))) {
        $err = $dbh->errorInfo();
        fatalerr("SQL prepare error: " . $err[2]);
      }
      if (!($stmt->execute(array($_POST['id'])))) {
        $err = $stmt->errorInfo();
        fatalerr("SQL execute error: " . $err[2]);
      }
    }
    $data = lt_query($table['query'], $params);
    $ret = [ 'status' => 'ok', 'crc' => crc32(json_encode($data['rows'], JSON_PARTIAL_OUTPUT_ON_ERROR)) ];
    print json_encode($ret, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'donext':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode donext');
    if (empty($_POST['prev'])) fatalerr('Invalid data in mode donext');
    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    $data = [];

    if (($_POST['prev'] === 'true') && !empty($table['options']['prev'])) {
      ob_start();
      if (is_array($table['options']['prev'])) $res = lt_print_block($table['options']['prev'][0]);
      else $res = lt_print_block($table['options']['prev']);
      if (!empty($res['location'])) $data['location'] = $res['location'];
      else $data['replace'] = ob_get_clean();
    }
    else {
      $params = [];
      if (!empty($table['options']['fields'])) {
        $count = 0;
        foreach ($table['options']['fields'] as $field) {
          if (!empty($_POST['field_'.$field[0]])) {
            $params[$count] = $_POST['field_'.$field[0]];
            $params[$field[0]] = $_POST['field_'.$field[0]];
            $count++;
          }
        }
      }
      if (!empty($table['options']['verify'])) {
        if (!lt_query_check($table['options']['verify'], $params)) {
          if (!empty($table['options']['error'])) $data['error'] = $table['options']['error'];
          else $data['error'] = 'Step not complete';
          print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
          return;
        }
      }
      if (!empty($table['options']['php'])) {
        global $mch;
        $res = eval($table['options']['php']);
        if ($res === FALSE) fatalerr('Syntax error in lt_control ' . $_POST['src'] . ' php option');
        elseif (is_string($res)) {
          $data['error'] = $res;
          print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
          return;
        }
      }
      if (!empty($table['options']['next'])) {
        ob_start();
        if (is_array($table['options']['next'])) $res = lt_print_block($table['options']['next'][0], $params);
        else $res = lt_print_block($table['options']['next'], $params);
        if (!empty($res['location'])) $data['location'] = $res['location'];
        else $data['replace'] = ob_get_clean();
      }
    }
    print json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'calendarselect':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode calendarselect');
    if (empty($_POST['start'])) fatalerr('Invalid start date in mode calendarselect');
    if (empty($_POST['end'])) fatalerr('Invalid end date in mode calendarselect');
    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (empty($table['queries']['select'])) fatalerr('No select query defined in lt_calendar block ' . $_POST['src']);

    if (!($stmt = $dbh->prepare($table['queries']['select']))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL prepare error: " . $err[2]);
    }
    if (!($stmt->execute(array($_POST['start'], $_POST['end'])))) {
      $err = $stmt->errorInfo();
      fatalerr("SQL execute error: " . $err[2]);
    }

    $results = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
      $results[] = array(
        'id' => $row['id'],
        'subid' => isset($row['subid'])?$row['subid']:null,
        'src' => $_POST['src'],
        'title' => $row['title'],
        'start' => $row['start'],
        'end' => $row['end'],
        'color' => $row['color'],
        'subcolor' => isset($row['subcolor'])?$row['subcolor']:null,
        'allDay' => isset($row['allday'])?$row['allday']:false,
        'editable' => isset($row['editable'])?$row['editable']:null
      );
    }

    print json_encode($results, JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  case 'calendarupdate':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode calendarupdate');
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) fatalerr('Invalid id in mode calendarupdate');
    if (empty($_POST['start'])) fatalerr('Invalid start date in mode calendarupdate');
    if (empty($_POST['end'])) fatalerr('Invalid end date in mode calendarupdate');
    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (empty($table['queries']['update'])) fatalerr('No update query defined in lt_calendar block ' . $_POST['src']);

    if (!($stmt = $dbh->prepare($table['queries']['update']))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL prepare error: " . $err[2]);
    }
    if (!($stmt->execute(array($_POST['start'], $_POST['end'], $_POST['id'])))) {
      $err = $stmt->errorInfo();
      fatalerr("SQL execute error: " . $err[2]);
    }
    print '{ "status": "ok" }';
  break;
  case 'calendarinsert':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode calendarinsert');
    if (empty($_POST['start'])) fatalerr('Invalid start date in mode calendarinsert');
    if (empty($_POST['end'])) fatalerr('Invalid end date in mode calendarinsert');
    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (empty($table['queries']['insert'])) fatalerr('No insert query defined in lt_calendar block ' . $_POST['src']);

    if (!($stmt = $dbh->prepare($table['queries']['insert']))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL prepare error: " . $err[2]);
    }
    $params = array($_POST['start'], $_POST['end']);
    if (!empty($_POST['param1'])) {
      $params[] = $_POST['param1'];
      if (!empty($_POST['param2'])) $params[] = $_POST['param2'];
    }
    if (!empty($_POST['title'])) $params[] = $_POST['title'];
    if (!($stmt->execute($params))) {
      $err = $stmt->errorInfo();
      fatalerr("SQL execute error: " . $err[2] . "\nwith params: " . json_encode($params, JSON_PARTIAL_OUTPUT_ON_ERROR));
    }
    print '{ "status": "ok" }';
  break;
  case 'calendardelete':
    if (empty($_POST['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_POST['src'])) fatalerr('Invalid src in mode calendardelete');
    if (empty($_POST['id']) || !is_numeric($_POST['id'])) fatalerr('Invalid id in mode calendardelete');
    $table = lt_find_table($_POST['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (empty($table['queries']['delete'])) fatalerr('No insert query defined in lt_calendar block ' . $_POST['src']);

    if (!($stmt = $dbh->prepare($table['queries']['delete']))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL prepare error: " . $err[2]);
    }
    if (!($stmt->execute(array($_POST['id'])))) {
      $err = $stmt->errorInfo();
      fatalerr("SQL execute error: " . $err[2]);
    }
    print '{ "status": "ok" }';
  break;
  case 'ganttselect':
    if (empty($_GET['src']) || !preg_match('/^[a-z0-9_-]+:[a-z0-9_-]+$/', $_GET['src'])) fatalerr('Invalid src in mode ganttselect');
    $table = lt_find_table($_GET['src']);
    if (!allowed_block($table['block'])) fatalerr('Access to block ' . $_GET['block'] . ' denied');
    if (empty($table['queries']['select'])) fatalerr('No select query defined in lt_gantt block ' . $_GET['src']);

    if (!($stmt = $dbh->prepare($table['queries']['select']))) {
      $err = $dbh->errorInfo();
      fatalerr("SQL prepare error: " . $err[2]);
    }
    if (!($stmt->execute())) {
      $err = $stmt->errorInfo();
      fatalerr("SQL execute error: " . $err[2]);
    }

    $results = array();
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
      $results[] = array(
        'id' => $row['id'],
        'text' => $row['text'],
        'start_date' => $row['start'],
        'end_date' => $row['end']
      );
    }

    print json_encode(array('data' => $results, 'links' => []), JSON_PARTIAL_OUTPUT_ON_ERROR);
    break;
  default:
    // if (file_exists('custommodes2.php')) include('custommodes2.php');
    fatalerr('Invalid mode specified');
}

function lt_run_insert($table, $data, $idcolumn = '') {
  global $dbh;
  $driver = $dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);

  $values_str = "";
  foreach (array_keys($data['columns']) as $column) {
    if (!empty($data['sqlfunction'][$column])) $values_str .= $data['sqlfunction'][$column] . ", ";
    else $values_str .= "?, ";
  }
  $query = "INSERT INTO $table (" . implode(',', array_keys($data['columns'])) . ") VALUES (" . rtrim($values_str, ', ') . ")";
  if ($idcolumn && ($driver == 'pgsql')) $query .= " RETURNING $idcolumn";

  if (!($stmt = $dbh->prepare($query))) {
    $err = $dbh->errorInfo();
    fatalerr("SQL prepare error: " . $err[2]);
  }
  if (!($stmt->execute(array_values($data['columns'])))) {
    $err = $stmt->errorInfo();
    if ((strpos($err[2], 'duplicate key value') !== FALSE) && (!empty($data['onconflict']))) {
      $query = $data['onconflict'];
      $params = [];
      foreach ($data['columns'] as $colname => $value) {
        $count = 0;
        $query = str_replace("#$colname", '?', $query, $count);
        if ($count) $params[] = $value;
      }
      return lt_query_single($query, $params);
    }
    fatalerr("SQL execute error: " . $err[2]);
  }

  if ($idcolumn) {
    if ($driver == 'pgsql') {
      $row = $stmt->fetch(\PDO::FETCH_NUM);
      if (empty($row) || empty($row[0])) fatalerr("lastInsertId requested but not available");
      $id = $row[0];
    }
    else $id = $dbh->lastInsertId();
  }
  else $id = 0;

  return $id;
}
