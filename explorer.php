<?php
/********************************
Simple PHP File Manager
Copyright John Campbell (jcampbell1)

Liscense: MIT
********************************/

//Disable error report for undefined superglobals
error_reporting(error_reporting() & ~E_NOTICE);

//Security options
$allow_delete = true; // Set to false to disable delete button and delete POST request.
$allow_create_folder = true; // Set to false to disable folder creation
$allow_upload = true; // Set to true to allow upload files
$allow_direct_link = true; // Set to false to only allow downloads and not direct link
// Sometimes you may need to hide sensitive files. Add them here
$files_to_skip = array(
    '.',
    // '..',
    // 'index.php'
);

/* Uncomment section below, if you want a trivial password protection */

/*
$PASSWORD = 'sfm';
session_start();
if(!$_SESSION['_sfm_allowed']) {
    // sha1, and random bytes to thwart timing attacks.  Not meant as secure hashing.
    $t = bin2hex(openssl_random_pseudo_bytes(10));
    if($_POST['p'] && sha1($t.$_POST['p']) === sha1($t.$PASSWORD)) {
        $_SESSION['_sfm_allowed'] = true;
        header('Location: ?');
    }
    echo '<html><body><form action=? method=post>PASSWORD:<input type=password name=p /></form></body></html>';
    exit;
}
*/

// must be in UTF-8 or `basename` doesn't work
setlocale(LC_ALL, 'en_US.UTF-8');

// $tmp = realpath($_REQUEST['file']);
// if ($tmp === false) {
//     err(404, 'File or Directory Not Found');
// }
// if (substr($tmp, 0, strlen(__DIR__)) !== __DIR__) {
//     err(403, "Forbidden");
// }

if (!$_COOKIE['_sfm_xsrf']) {
    setcookie('_sfm_xsrf', bin2hex(openssl_random_pseudo_bytes(16)));
}
if ($_POST) {
    if ($_COOKIE['_sfm_xsrf'] !== $_POST['xsrf'] || !$_POST['xsrf']) {
        err(403, "XSRF Failure");
    }
}

$file = $_SERVER['DOCUMENT_ROOT'] . '/' . ($_REQUEST['file'] ?: '.');
if ($_GET['do'] == 'list') {
    if (is_dir($file)) {
        $directory = $file;
        $result = array();
        $files = array_diff(scandir($directory), $files_to_skip);
        foreach ($files as $entry) {
            if ($entry !== basename(__DIR__)) {
                $i = $directory . '/' . $entry;
                $stat = stat($i);
                $result[] = array(
                    'mtime' => $stat['mtime'],
                    'size' => $stat['size'],
                    'name' => basename($i),
                    'path' => preg_replace('@^\./@', '', preg_replace('@^' . $_SERVER['DOCUMENT_ROOT'] . '/@', '', $i)),
                    'is_dir' => is_dir($i),
                    'is_deleteable' => $allow_delete && ((!is_dir($i) && is_writable($directory)) ||
                        (is_dir($i) && is_writable($directory) && is_recursively_deleteable($i))),
                    'is_readable' => is_readable($i),
                    'is_writable' => is_writable($i),
                    'is_executable' => is_executable($i),
                    'is_symbolic_link' => is_link($i),
                );
            }
        }
    } else err(412, "Not a Directory");

    echo json_encode(array('success' => true, 'is_writable' => is_writable($file), 'results' => $result, 'root' => $_SERVER['DOCUMENT_ROOT']));
    exit;
} elseif ($_POST['do'] == 'delete') {
    if ($allow_delete) {
        rmrf($file);
    }
    exit;
} elseif ($_POST['do'] == 'mkdir' && $allow_create_folder== true) {
    // don't allow actions outside root. we also filter out slashes to catch args like './../outside'
    $name = ltrim($_POST['name'], './');
    chdir($file);
    if ($name{strlen($name) - 1} === '/') {
        @mkdir(rtrim($name, '/'), 0755, true);
    } else {
        @mkdir(dirname($name), 0755, true);
        @touch($name);
    }
    exit;
} elseif ($_POST['do'] == 'upload' && $allow_upload == true) {
    var_dump($_POST);
    var_dump($_FILES);
    var_dump($_FILES['file_data']['tmp_name']);
    var_dump(move_uploaded_file($_FILES['file_data']['tmp_name'], $file.'/'.$_FILES['file_data']['name']));
    exit;
} elseif ($_GET['do'] == 'download') {
    $filename = basename($file);
    header('Content-Type: ' . detectFileMimeType($file));
    header('Content-Length: '. filesize($file));
    header(sprintf(
        'Content-Disposition: attachment; filename=%s',
        strpos('MSIE', $_SERVER['HTTP_REFERER']) ? rawurlencode($filename) : "\"$filename\""
    ));
    ob_flush();
    readfile($file);
    exit;
}
// As mime_content_type() fails on Windows
function detectFileMimeType($filename='')
{
    $filename = escapeshellarg($filename);
    $command = "file -b --mime-type -m /usr/share/misc/magic {$filename}";
    $mimeType = shell_exec($command);
    return trim($mimeType);
}
function rmrf($dir)
{
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            rmrf("$dir/$file");
        }
        if (is_link($dir)) {
            unlink($dir);
        } else {
            rmdir($dir);
        }
    } else {
        unlink($dir);
    }
}
function is_recursively_deleteable($d)
{
    $stack = array($d);
    while ($dir = array_pop($stack)) {
        if (!is_readable($dir) || !is_writable($dir)) {
            return false;
        }
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            if (is_dir($file)) {
                $stack[] = "$dir/$file";
            }
        }
    }
    return true;
}

function err($code, $msg)
{
    echo json_encode(array('error' => array('code'=>intval($code), 'msg' => $msg)));
    exit;
}

function asBytes($ini_v)
{
    $ini_v = trim($ini_v);
    $s = array('g'=> 1<<30, 'm' => 1<<20, 'k' => 1<<10);
    return intval($ini_v) * ($s[strtolower(substr($ini_v, -1))] ?: 1);
}
$MAX_UPLOAD_SIZE = min(asBytes(ini_get('post_max_size')), asBytes(ini_get('upload_max_filesize')));
?>
<!DOCTYPE html>
<html><head>
<title>Simple PHP File Manager</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="assets/style.css">
<script src="assets/jquery.min.js"></script>
<script> var MAX_UPLOAD_SIZE = parseInt('<?= $MAX_UPLOAD_SIZE ?>') </script>
<script src="assets/script.js" defer></script>
</head><body>
<div id="top">
   <?php if ($allow_upload): ?>
    <form action="?" method="post" id="mkdir" />
        <label for=dirname>Create New File <small>(Directory ends in /)</small></label><input id=dirname type=text name=name value="" />
        <input type="submit" value="create" />
    </form>

   <?php endif; ?>

   <?php if ($allow_upload): ?>

    <div id="file_drop_target">
        Drag Files Here To Upload
        <b>or</b>
        <input type="file" multiple />
    </div>
   <?php endif; ?>
    <div id="breadcrumb">&nbsp;</div>
</div>

<div id="upload_progress"></div>
<table id="table"><thead><tr>
    <th>Name</th>
    <th>Size</th>
    <th>Modified</th>
    <th>Permissions</th>
    <th>Actions</th>
</tr></thead><tbody id="list">

</tbody></table>
<footer><a href="https://github.com/thinkdj/simple-php-file-manager" target="_blank">Simple PHP File Manager</a> (forked from <a href="https://github.com/jcampbell1/simple-file-manager" target="_blank">simple-file-manager</a>)</footer>
</body></html>
