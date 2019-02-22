<?php
function die_as_error($msg = '')
{
    http_response_code(500);
    die($msg);
}

$filename = '../' . ($_GET['file'] ?: '');

if (($_GET['do'] ?? null) === 'save') {
    $content = file_get_contents('php://input');
    try {
        file_put_contents($filename, $content);
        echo strlen($content) . ' bytes has written';
        exit;
    } catch (Exception $e) {
        die_as_error($e->getMessage());
    }
}

if (empty($filename)) {
    die_as_error('You must specify a filename using query string [?file=/path/to/file].');
}
if (!is_file($filename)) {
    die_as_error($filename . ' is not a valid file.');
}

$allowed_file_types = [
    'javascript', 'typescript', 'css', 'html', 'c', 'cpp', 'h', 'hpp',
    'py', 'json', 'less', 'sass', 'scss', 'markdown', 'sql', 'text', 'yaml',
    'xml', 'ini', 'php_laravel_blade', 'sh', 'apache_conf',
];
$file_type = pathinfo($filename, PATHINFO_EXTENSION);

define('LOAD_EMMET', in_array($file_type, ['html', 'php']));

switch ($file_type) {
case 'htaccess':
    $file_type = 'apache_conf';
    break;
case 'yml':
    $file_type = 'yaml';
    break;
case 'js':
    $file_type = 'javascript';
    break;
case 'ts':
    $file_type = 'typescript';
    break;
case 'py':
    $file_type = 'python';
    break;
case 'c':
case 'cpp':
case 'h':
case 'hpp':
    $file_type = 'c_cpp';
    break;
case 'md':
    $file_type = 'markdown';
    break;
case 'txt':
    $file_type = 'text';
    break;
case 'php':
    $file_type = 'php_laravel_blade';
    break;
case 'zsh':
case 'bash':
    $file_type = 'sh';
    break;
}

if (!in_array($file_type, $allowed_file_types)) {
    $file_type = 'text';
}
$content = file_get_contents($filename);
$file_full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $_GET['file'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit <?= $file_full_path ?></title>
    <style>
    body {
        margin: 0;
        padding: 0;
        overflow: hidden;
        background-color: #141414;
    }
    #editor {
        margin: 0;
        position: absolute;
        left: 0;
        bottom: 0;
        top: 50px;
        right: 0;
    }
    #menu {
        margin: 0;
        padding: 0;
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 30px;
        color: #e2e2e2;
        border-bottom: solid 20px #232323;
    }
    #menu button {
        width: auto;
        height: 100%;
        padding: 0 5px;
        margin: 0 1px;
        border: none;
        border-radius: 0;
        background-color: #414141;
        float: left;
    }
    #menu input {
        margin: 0 10px 0;
        padding: 0;
        font-family: monospace;
        height: 100%;
        border: none;
        border-radius: 0;
        background-color: #141414;
        color: #e2e2e2;
    }
    </style>
</head>
<body>
  <div id="menu">
    <button id="btn-reload">RELOAD</button>
    <button id="btn-save">SAVE</button>
    <input id="filename" type="text" value="<?= $file_full_path ?>" readonly>
  </div>
  <pre id="editor"><?= htmlspecialchars($content) ?></pre>
  <script src="assets/jquery.min.js"></script>
  <script src="assets/ace/ace.js"></script>
  <script src="assets/ace/ext-language_tools.js"></script>
  <?php if (LOAD_EMMET): ?><script src="assets/ace/emmet.js"></script><script src="assets/ace/ext-emmet.js"></script><?php endif; ?>
  <script src="assets/ace/theme-twilight.js"></script>
  <script src="assets/ace/mode-<?= $file_type ?>.js"></script>
  <script>
    ace.require('ace/ext/language_tools');
    var editor = ace.edit('editor');
    editor.setTheme('ace/theme/twilight');
    editor.session.setMode('ace/mode/<?= $file_type ?>');
    editor.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true,
    });
    <?php if (LOAD_EMMET): ?>editor.setOption('enableEmmet', true);<?php endif; ?>

    $('#btn-save').click(function () {
        $.ajax({
            type: 'post',
            url: '<?= $_SERVER["REQUEST_URI"] ?>&do=save',
            data: editor.getSession().getValue(),
            error: function (xhr, e, status) { alert([status, xhr.responseText].filter(function (el) { return el }).join('\n')) },
            success: function (data) { alert('ok: ' + data) },
        })
    })

    $('#btn-reload').click(function () { window.location.reload() })

    var resizeFilenameInput = function () {
        var computeWidth = function (el) {
            return parseInt(window.getComputedStyle(document.querySelector(el)).width)
        }
        var left = computeWidth('#btn-save') + computeWidth('#btn-reload')
        var filename = document.querySelector('#filename')
        filename.style.width = (window.innerWidth - left - 24) + 'px'
    }
    window.onresize = resizeFilenameInput
    resizeFilenameInput()
  </script>
</body>
</html>
