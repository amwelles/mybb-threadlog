let threadlog = null;
jQuery(() => {
    // on click button that enters edit mode
    $('#edit-threadlog-btn').click((e) => {
        e.preventDefault();
        // copy the original table rows
        threadlog = $('#threadlog .threadlogrow').clone();
        // enter edit mode
        $('#threadlog .threadrow-container').sortable({
            helper: forceCellWidths
        });
        $(e.target).hide();
        $('#save-threadlog-btn').show();
        $('#cancel-threadlog-btn').show();
    });

    $('#cancel-threadlog-btn').click((e) => {
        e.preventDefault();
        exitEditMode();
        removeEdits();
    });

    $('#save-threadlog-btn').click((e) => {
        e.preventDefault();
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
    let firstOrLast = null;
    if (!$entryRow.prev() && direction === 'up') {
        firstOrLast = true;
    } else if (!$entryRow.next() && direction === 'down') {
        firstOrLast = true;
    }
    $.ajax({
        type: 'POST',
        url: `xmlhttp.php?action=threadlog&reorder=single${firstOrLast ? '&template=' + $direction : ''}`,
        dataType: 'json',
        data: {
            ajax: 1,
            direction: e.target.value,
            entry: $entryRow.data('entry')
        }
    }).done((res) => {
        const direction = e.target.value;
        $(e.target).val('');
        // if this item was the first in the list and moved up OR
        // if this item was the last in the list and moved down
        if (firstOrLast) {
            // replace the data in this row with the response data we got back
            $entryRow.parent().append(unescape(res));
            $entryRow.remove();
        } else {
            let $swap = null;
            // swap them
            if (direction === 'up') {
                $swap = $entryRow.prev();
                // swap the elements
                $entryRow.after($swap);
            } else {
                $swap = $entryRow.next();
                // if a move up option doesn't exist, add it
                $entryRow.before($swap);
            }
            // swap the options too
            const $optionsTarget = $(e.target).children();
            const $optionsSwap = $swap.find('select.threadrow-reorder option');
            $optionsSwap.parent().html($optionsTarget.clone());
            $optionsTarget.parent().html($optionsSwap.clone());
        }
    }).fail((res, status, err) => console.error(res, status, err));
};

const exitEditMode = () => {
    $('#save-threadlog-btn').hide();
    $('#cancel-threadlog-btn').hide();
    $('#threadlog .threadrow-container').sortable('destroy');
    $('#edit-threadlog-btn').show();
};

// replace the table rows with the old ones we copied
const removeEdits = () => {
    const $rows = $('#threadlog .threadlogrow');
    const $parent = $rows.parent();
    $rows.remove();
    $parent.append(threadlog);
};

const saveEdits = () => {
    threadlog = null; // trash the clone
    // get the entry id and positions
    const rowPositions = $('#threadlog .threadlogrow[data-entry]').map((_, row) => {
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
    }).fail((_, status, err) => console.error(_, status, err));
};