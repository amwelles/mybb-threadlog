$(() => {
    /**
     * settings and outer scope vars
    */
    let threadlog = null;
    const settings = {
        reorderable: false,
        describeable: false,
        dateable: false
    };
    let threadlogEditingEid = null;
    const elem = document.querySelector('#threadlog');
    settings.reorderable = elem.hasAttribute('reorderable');
    settings.describeable = elem.hasAttribute('describeable');
    settings.dateable = elem.hasAttribute('dateable');

    /**
     * Function definitions
     */
    const enterSingleEditMode = (e) => {
        e.preventDefault();
        const $row = $(e.target).closest('.threadlogrow');
        threadlogEditingEid = $row.data('entry');
        $row.find('.edit-single').hide();
        $row.find('.edit-single-cancel').show();
        $row.find('.edit-single-save').show();
        if (settings.describeable) {
            $row.find('.description').hide();
            const $descrip = $row.find('input[name="description"]');
            $descrip.show();
            $descrip.focus();
        }
    };

    const commitSingleEdit = (type) => {
        if (!threadlogEditingEid) return;
        const $row = $(`.threadlogrow[data-entry="${threadlogEditingEid}"]`);
        if (!$row || !type) return;

        switch(type) {
            case 'description':
                const $input = $row.find('input[name="description"]');
                const $description = $row.find('.description');
                // change the text description to match the one the user entered
                if ($description.text() == $input.val()) {
                    break;
                }
                $description.text($input.val());
                $description.show();
                $input.hide();
                // update
                $.ajax({
                    type: 'POST',
                    url: `xmlhttp.php?action=threadlog&update=single&field=description`,
                    dataType: 'json',
                    data: {
                        ajax: 1,
                        description: $input.val(),
                        entry: threadlogEditingEid
                    }
                }).done(() => {
                    const event = new CustomEvent('threadlog-description-updated', {
                        bubbles: true,
                        composed: false
                    });
                    $row[0].dispatchEvent(event);
                    threadlogEditingEid = null;
                }).fail((res, status, err) => console.error(res, status, err));
                break;
            case 'date':
                break;
        }
        exitSingleEditMode($row);
    };

    const exitSingleEditMode = ($row) => {
        threadlogEditingEid = null;
        $row.find('.description').show();
        $row.find('input').hide();
        $row.find('.edit-single').show();
        $row.find('.edit-single-cancel').hide();
        $row.find('.edit-single-save').hide();
    };

    const enterEditMode = () => {
        // copy the original table rows
        threadlog = $('#threadlog .threadlogrow').clone();
        if (settings.reorderable) {
            $('#threadlog .threadrow-container').sortable({
                // artifically force the width because table cells are stupid
                helper: (_, ui) => {
                    if (ui.get(0).tagName !== 'TR') return ui;
                    ui.children().each((_, cell) => {
                        $(cell).width($(cell).width());
                    });
                    return ui;
                }
            });
        }
        $('#edit-threadlog-btn').hide();
        $('#save-threadlog-btn').show();
        $('#cancel-threadlog-btn').show();
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
            url: `xmlhttp.php?action=threadlog&field=reorder&update=single${firstOrLast ? '&template=' + $direction : ''}`,
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
        if (settings.reorderable) {
            $('#threadlog .threadrow-container').sortable('destroy');
        }
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
        /**
         * Save multiple reordered items
         */
        if (settings.reorderable) {
            // get the entry id and positions
            const rowPositions = $('#threadlog .threadlogrow[data-entry]').map((_, row) => {
                return $(row).data('entry');
            }).get();
            const params = new URLSearchParams(window.location.search);
            // make the request
            $.ajax({
                type: 'POST',
                url: 'xmlhttp.php?action=threadlog&field=reorder&update=multi',
                dataType: 'json',
                data: {
                    ajax: 1,
                    page: params.get('page') || 1, //get the query param,
                    threadlogEntries: rowPositions
                }
            }).fail((_, status, err) => console.error(_, status, err));
        }
    };

    /**
     * Event Handlers
     */
    // on click button that enters edit mode
    $('#edit-threadlog-btn').click((e) => {
        e.preventDefault();
        enterEditMode();
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

    if (settings.reorderable) {
        // single move actions
        $('select.threadrow-reorder').change(onReorderSelectChange);
    }

    if (settings.describeable || settings.dateable) {
        const exitCurrentEdit = (e) => {
            e.preventDefault();
            e.stopPropagation();
            threadlogEditingEid && exitSingleEditMode($(`.threadlogrow[data-entry="${threadlogEditingEid}"]`));
        }
        // edit single row
        $('.threadlogrow .description').click(enterSingleEditMode);
        $('.threadlogrow .edit-single').click(enterSingleEditMode);
        $('.threadlogrow .edit-single-save').click(() => {
            commitSingleEdit('description');
            // todo: commit the date too
        });
        $('.threadlogrow .edit-single-cancel').click(exitCurrentEdit);
        // commit description
        $('body').click((e) => {
            if (!threadlogEditingEid) return;
            const $row = $(`.threadlogrow[data-entry="${threadlogEditingEid}"]`);
            // if the thing we clicked is or is contained in this row, forget it
            if (e.target === $row[0] || $row.find(e.target).length) return;
            commitSingleEdit('description');
        });
        // todo: do something similar for date
        $('.threadlogrow input[name="description"]').keydown((e) => {
            switch (e.key) {
                case 'Enter':
                    commitSingleEdit('description');
                    break;
                case 'Escape':
                    exitCurrentEdit(e);
                    break;
            }
        });
    }
});