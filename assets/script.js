/* globals jQuery,$,MAX_UPLOAD_SIZE */
/* eslint-disable camelcase */

(function ($) {
  $.fn.tablesorter = function () {
    var $table = this
    this.find('th').click(function () {
      var idx = $(this).index()
      var direction = $(this).hasClass('sort_asc')
      $table.tablesortby(idx, direction)
    })
    return this
  }
  $.fn.tablesortby = function (idx, direction) {
    var $rows = this.find('tbody tr')
    function elementToVal (a) {
      var $a_elem = $(a).find('td:nth-child(' + (idx + 1) + ')')
      var a_val = $a_elem.attr('data-sort') || $a_elem.text()
      return (a_val === parseInt(a_val) ? parseInt(a_val) : a_val)
    }
    $rows.sort(function (a, b) {
      var a_val = elementToVal(a); var b_val = elementToVal(b)
      return (a_val > b_val ? 1 : (a_val === b_val ? 0 : -1)) * (direction ? 1 : -1)
    })
    this.find('th').removeClass('sort_asc sort_desc')
    $(this).find('thead th:nth-child(' + (idx + 1) + ')').addClass(direction ? 'sort_desc' : 'sort_asc')
    for (var i = 0; i < $rows.length; i++) { this.append($rows[i]) }
    this.settablesortmarkers()
    return this
  }
  $.fn.retablesort = function () {
    var $e = this.find('thead th.sort_asc, thead th.sort_desc')
    if ($e.length) { this.tablesortby($e.index(), $e.hasClass('sort_desc')) }

    return this
  }
  $.fn.settablesortmarkers = function () {
    this.find('thead th span.indicator').remove()
    this.find('thead th.sort_asc').append('<span class="indicator">&darr;<span>')
    this.find('thead th.sort_desc').append('<span class="indicator">&uarr;<span>')
    return this
  }
})(jQuery)
$(function () {
  var XSRF = (document.cookie.match('(^|; )_sfm_xsrf=([^;]*)') || 0)[2]
  var $tbody = $('#list')
  $(window).bind('hashchange', list).trigger('hashchange')
  $('#table').tablesorter()

  $('.delete').live('click', function (data) {
    if (window.confirm('WARNING! \nPermanently delete ' + $(this).attr('data-file') + ' ?')) {
      $.post('', { 'do': 'delete', file: $(this).attr('data-file'), xsrf: XSRF }, function (response) {
        list()
      }, 'json')
      return false
    } else console.log('Aborted Delete')
  })

  $('#mkdir').submit(function (e) {
    var hashval = window.location.hash.substr(1)

    var $dir = $(this).find('[name=name]')
    e.preventDefault()
    $dir.val().length && $.post('?', { 'do': 'mkdir', name: $dir.val(), xsrf: XSRF, file: hashval }, function (data) {
      list()
    }, 'json')
    $dir.val('')
    return false
  })
  // file upload stuff
  $('#file_drop_target').bind('dragover', function () {
    $(this).addClass('drag_over')
    return false
  }).bind('dragend', function () {
    $(this).removeClass('drag_over')
    return false
  }).bind('drop', function (e) {
    e.preventDefault()
    var files = e.originalEvent.dataTransfer.files
    $.each(files, function (k, file) {
      uploadFile(file)
    })
    $(this).removeClass('drag_over')
  })
  $('input[type=file]').change(function (e) {
    e.preventDefault()
    $.each(this.files, function (k, file) {
      uploadFile(file)
    })
  })

  function uploadFile (file) {
    var folder = window.location.hash.substr(1)

    if (file.size > MAX_UPLOAD_SIZE) {
      var $error_row = renderFileSizeErrorRow(file, folder)
      $('#upload_progress').append($error_row)
      window.setTimeout(function () { $error_row.fadeOut() }, 5000)
      return false
    }

    var $row = renderFileUploadRow(file, folder)
    $('#upload_progress').append($row)
    var fd = new window.FormData()
    fd.append('file_data', file)
    fd.append('file', folder)
    fd.append('xsrf', XSRF)
    fd.append('do', 'upload')
    var xhr = new window.XMLHttpRequest()
    xhr.open('POST', '?')
    xhr.onload = function () {
      $row.remove()
      list()
    }
    xhr.upload.onprogress = function (e) {
      if (e.lengthComputable) {
        $row.find('.progress').css('width', (e.loaded / e.total * 100 | 0) + '%')
      }
    }
    xhr.send(fd)
  }
  function renderFileUploadRow (file, folder) {
    return $('<div/>')
      .append($('<span class="fileuploadname" />').text((folder ? folder + '/' : '') + file.name))
      .append($('<div class="progress_track"><div class="progress"></div></div>'))
      .append($('<span class="size" />').text(formatFileSize(file.size)))
  };
  function renderFileSizeErrorRow (file, folder) {
    return $('<div class="error" />')
      .append($('<span class="fileuploadname" />').text('Error: ' + (folder ? folder + '/' : '') + file.name))
      .append($('<span/>').html(' file size - <b>' + formatFileSize(file.size) + '</b>' +
                ' exceeds max upload size of <b>' + formatFileSize(MAX_UPLOAD_SIZE) + '</b>'))
  }
  function list () {
    var hashval = window.location.hash.substr(1)
    var path = hashval.split('/')
    for (var i = path.length - 1; i; i--) {
      var filename = path.pop()
      if (filename === '..') {
        if (path.pop() === '..') break
        window.location.hash = path.join('/')
        break
      }
    }
    $.get('?', { do: 'list', file: hashval }, function (data) {
      $tbody.empty()
      $('#breadcrumb').empty().html(renderBreadcrumbs(data.root, hashval))
      if (data.success) {
        $.each(data.results, function (k, v) {
          $tbody.append(renderFileRow(v))
        })
        !data.results.length && $tbody.append('<tr><td class="empty" colspan=5>This folder is empty</td></tr>')
        data.is_writable ? $('body').removeClass('no_write') : $('body').addClass('no_write')
      } else {
        console.warn(data.error.msg)
      }
      $('#table').retablesort()
    }, 'json')
  }
  function renderFileRow (data) {
    var $link = $('<a class="name" />')
      .attr('href', (data.is_dir ? '#' : '/') + data.path)
      .attr('target', data.is_dir ? '_self' : '_blank')
      .text(data.name)
    var allow_direct_link = true
    if (!data.is_dir && !allow_direct_link) $link.css('pointer-events', 'none')
    var $dl_link = $('<a/>').attr('href', '?do=download&file=' + encodeURIComponent(data.path))
      .addClass('download').text('download')
    var $delete_link = $('<a href="#" />').attr('data-file', data.path).addClass('delete').text('delete')
    var $edit_link = $('<a target="_blank" />').attr('href', 'editor.php?file=' + encodeURIComponent(data.path)).addClass('edit').text('edit')
    var perms = [
      data.is_symbolic_link ? 'l' : (data.is_dir ? 'd' : '-'),
      data.is_readable ? 'r' : '-',
      data.is_writable ? 'w' : '-',
      data.is_executable ? 'x' : '-'
    ]
    var $html = $('<tr />')
      .addClass(data.is_symbolic_link ? 'is_link' : '')
      .addClass(data.is_dir ? 'is_dir' : '')
      .append($('<td class="first" />').append($link))
      .append($('<td/>').attr('data-sort', data.is_dir ? -1 : data.size)
        .html($('<span class="size" />').text(formatFileSize(data.size))))
      .append($('<td/>').attr('data-sort', data.mtime).text(formatTimestamp(data.mtime)))
      .append($('<td class="perms"/>').text(perms.join('')))
      .append($('<td/>').append($dl_link).append(data.is_writable ? $edit_link : '').append(data.is_deleteable ? $delete_link : ''))
    return $html
  }
  function renderBreadcrumbs (root, path) {
    var base = ''

    var $html = $('<div/>').append($('<a href=#>' + root + '</a></div>'))
    $.each(path.split('/'), function (k, v) {
      if (v) {
        $html.append($('<span/>').text(' ▸ '))
          .append($('<a/>').attr('href', '#' + base + v).text(v))
        base += v + '/'
      }
    })
    return $html
  }
  function formatTimestamp (unix_timestamp) {
    var m = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    var d = new Date(unix_timestamp * 1000)
    return [m[d.getMonth()], ' ', d.getDate(), ', ', d.getFullYear(), ' ',
      (d.getHours() % 12 || 12), ':', (d.getMinutes() < 10 ? '0' : '') + d.getMinutes(),
      ' ', d.getHours() >= 12 ? 'PM' : 'AM'].join('')
  }
  function formatFileSize (bytes) {
    var s = ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB']
    for (var pos = 0; bytes >= 1000; pos++, bytes /= 1024);
    var d = Math.round(bytes * 10)
    return pos ? [parseInt(d / 10), '.', d % 10, ' ', s[pos]].join('') : bytes + ' bytes'
  }
})
