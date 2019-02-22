# php filemanager
A simple web-based filemanager with an editor.

## Requirements
- php 7+

## Usage
Assuming `localhost:8080` as my http server and `/var/www/mysite/index.php` as
my website entrypoint, I do following steps:
```sh
cd /var/www/mysite
git clone https://github.com/ShahinSorkh/php-filemanager.git fmgr
firefox http://localhost:8080/fmgr/explorer.php
```

## Credits
- [thinkdj](https://github.com/thinkdj/simple-php-file-manager) - for explorer base source code
- [ajaxorg](https://github.com/ajaxorg/ace) - for amazing ace editor
