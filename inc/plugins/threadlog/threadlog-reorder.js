let threadlog = null;
jQuery(() => {
  // on click button that enters edit mode
  $('#edit-threadlog-btn').click((e) => {
    e.preventDefault();
    // copy the original table rows
    threadlog = $('#threadlog tr[data-entry]').clone();
    // enter edit mode
    $('#threadlog tbody').sortable({
      helper: forceCellWidths
    });
    $(e.target).hide();
    $('#save-threadlog-btn').show();
    $('#cancel-threadlog-btn').show();
  });

  $('#cancel-threadlog-btn').click(() => {
    exitEditMode();
    removeEdits();
  });

  $('#save-threadlog-btn').click(() => {
    exitEditMode();
    saveEdits();
  });

  // single move actions
  $('select.threadrow-reorder').change(onReorderSelectChange);
});
// artifically force the width because table cells are stupid
const forceCellWidths = (_, ui) => {
  if (ui.get(0).tagName !== 'TR') return ui;
  ui.children().each((_, cell) => {
    $(cell).width($(cell).width());
  });
  return ui;
};

const onReorderSelectChange = (e) => {
  if (e.target.value === '') return;
  $entryRow = $(e.target).closest('[data-entry]');
  console.log(e.target.value, $entryRow.data('entry'));
  $.ajax({
    type: 'POST',
    url: 'xmlhttp.php?action=threadlog&reorder=single',
    dataType: 'json',
    data: {
      ajax: 1,
      direction: e.target.value,
      entry: $entryRow.data('entry')
    }
  }).done((res) => {
    $(e.target).val('');
    // if this item was the first in the list and moved up OR
    // if this item was the last in the list and moved down
    if ((!$entryRow.prev() && e.target.value === 'up') || (!$entryRow.next() && e.target.value === 'down')) {
      // replace the data in this row with the response data we got back
      $entryRow.parent().append(unescape(res));
      $entryRow.remove();
    }
  }).fail((res, status, err) => console.error(res, status, err));
};

const exitEditMode = () => {
  $('#save-threadlog-btn').hide();
  $('#cancel-threadlog-btn').hide();
  $('#threadlog tbody').sortable('destroy');
  $('#edit-threadlog-btn').show();
};

// replace the table rows with the old ones we copied
const removeEdits = () => {
  const $tbody = $('#threadlog tbody');
  $tbody.children('[data-entry]').remove();
  $tbody.append(threadlog);
};

const saveEdits = () => {
  threadlog = null; // trash the clone
  // get the entry id and positions
  const rowPositions = $('#threadlog tr[data-entry]').map((_, row) => {
    return $(row).data('entry');
  }).get();
  const params = new URLSearchParams(window.location.search);
  // make the request
  $.ajax({
      type: 'POST',
      url: 'xmlhttp.php?action=threadlog&reorder=multi',
      dataType: 'json',
      data: {
        ajax: 1,
        page: params.get('page') || 1, //get the query param,
        threadlogEntries: rowPositions
      }
  }).done((res) => console.log(res))
    .fail((_, status, err) => console.error(_, status, err));
};