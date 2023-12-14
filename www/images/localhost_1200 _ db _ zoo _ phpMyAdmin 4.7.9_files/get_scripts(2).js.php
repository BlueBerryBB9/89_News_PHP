/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * function used in or for navigation panel
 *
 * @package phpMyAdmin-Navigation
 */

/**
 * updates the tree state in sessionStorage
 *
 * @returns void
 */
function navTreeStateUpdate() {
    // update if session storage is supported
    if (isStorageSupported('sessionStorage')) {
        var storage = window.sessionStorage;
        // try catch necessary here to detect whether
        // content to be stored exceeds storage capacity
        try {
            storage.setItem('navTreePaths', JSON.stringify(traverseNavigationForPaths()));
            storage.setItem('server', PMA_commonParams.get('server'));
            storage.setItem('token', PMA_commonParams.get('token'));
        } catch(error) {
            // storage capacity exceeded & old navigation tree
            // state is no more valid, so remove it
            storage.removeItem('navTreePaths');
            storage.removeItem('server');
            storage.removeItem('token');
        }
    }
}


/**
 * updates the filter state in sessionStorage
 *
 * @returns void
 */
function navFilterStateUpdate(filterName, filterValue) {
    if (isStorageSupported('sessionStorage')) {
        var storage = window.sessionStorage;
        try {
            var currentFilter = $.extend({}, JSON.parse(storage.getItem('navTreeSearchFilters')));
            var filter = {};
            filter[filterName] = filterValue;
            currentFilter = $.extend(currentFilter, filter);
            storage.setItem('navTreeSearchFilters', JSON.stringify(currentFilter));
        } catch (error) {
            storage.removeItem('navTreeSearchFilters');
        }
    }
}


/**
 * restores the filter state on navigation reload
 *
 * @returns void
 */
function navFilterStateRestore() {
    if (isStorageSupported('sessionStorage')
        && typeof window.sessionStorage.navTreeSearchFilters !== 'undefined'
    ) {
        var searchClauses = JSON.parse(window.sessionStorage.navTreeSearchFilters);
        if (Object.keys(searchClauses).length < 1) {
            return;
        }
        // restore database filter if present and not empty
        if (searchClauses.hasOwnProperty("dbFilter")
            && searchClauses.dbFilter.length
        ) {
            $obj = $('#pma_navigation_tree');
            if (! $obj.data('fastFilter')) {
                $obj.data(
                    'fastFilter',
                    new PMA_fastFilter.filter($obj, "")
                );
            }
            $obj.find('li.fast_filter.db_fast_filter input.searchClause')
                .val(searchClauses.dbFilter)
                .trigger('keyup');
        }
        // find all table filters present in the tree
        $tableFilters = $('#pma_navigation_tree li.database')
            .children('div.list_container')
            .find('li.fast_filter input.searchClause');
        // restore table filters
        $tableFilters.each(function () {
            $obj = $(this).closest('div.list_container');
            // aPath associated with this filter
            var filterName = $(this).siblings('input[name=aPath]').val();
            // if this table's filter has a state stored in storage
            if (searchClauses.hasOwnProperty(filterName)
                && searchClauses[filterName].length
            ) {
                // clear state if item is not visible,
                // happens when table filter becomes invisible
                // as db filter has already been applied
                if (! $obj.is(":visible")) {
                    navFilterStateUpdate(filterName, "");
                    return true;
                }
                if (! $obj.data('fastFilter')) {
                    $obj.data(
                        'fastFilter',
                        new PMA_fastFilter.filter($obj, "")
                    );
                }
                $(this).val(searchClauses[filterName])
                    .trigger('keyup');
            }
        });
    }
}

/**
 * Loads child items of a node and executes a given callback
 *
 * @param isNode
 * @param $expandElem expander
 * @param callback    callback function
 *
 * @returns void
 */
function loadChildNodes(isNode, $expandElem, callback) {

    var $destination = null;
    var params = null;

    if (isNode) {
        if (!$expandElem.hasClass('expander')) {
            return;
        }
        $destination = $expandElem.closest('li');
        params = {
            aPath: $expandElem.find('span.aPath').text(),
            vPath: $expandElem.find('span.vPath').text(),
            pos: $expandElem.find('span.pos').text(),
            pos2_name: $expandElem.find('span.pos2_name').text(),
            pos2_value: $expandElem.find('span.pos2_value').text(),
            searchClause: '',
            searchClause2: ''
        };
        if ($expandElem.closest('ul').hasClass('search_results')) {
            params.searchClause = PMA_fastFilter.getSearchClause();
            params.searchClause2 = PMA_fastFilter.getSearchClause2($expandElem);
        }
    } else {
        $destination = $('#pma_navigation_tree_content');
        params = {
            aPath: $expandElem.attr('aPath'),
            vPath: $expandElem.attr('vPath'),
            pos: $expandElem.attr('pos'),
            pos2_name: '',
            pos2_value: '',
            searchClause: '',
            searchClause2: ''
        };
    }

    var url = $('#pma_navigation').find('a.navigation_url').attr('href');
    $.get(url, params, function (data) {
        if (typeof data !== 'undefined' && data.success === true) {
            $destination.find('div.list_container').remove(); // FIXME: Hack, there shouldn't be a list container there
            if (isNode) {
                $destination.append(data.message);
                $expandElem.addClass('loaded');
            } else {
                $destination.html(data.message);
                $destination.children()
                    .first()
                    .css({
                        border: '0px',
                        margin: '0em',
                        padding : '0em'
                    })
                    .slideDown('slow');
            }
            if (data._errors) {
                var $errors = $(data._errors);
                if ($errors.children().length > 0) {
                    $('#pma_errors').replaceWith(data._errors);
                }
            }
            if (callback && typeof callback == 'function') {
                callback(data);
            }
        } else if(data.redirect_flag == "1") {
            if (window.location.href.indexOf('?') === -1) {
                window.location.href += '?session_expired=1';
            } else {
                window.location.href += '&session_expired=1';
            }
            window.location.reload();
        } else {
            var $throbber = $expandElem.find('img.throbber');
            $throbber.hide();
            var $icon = $expandElem.find('img.ic_b_plus');
            $icon.show();
            PMA_ajaxShowMessage(data.error, false);
        }
    });
}

/**
 * Collapses a node in navigation tree.
 *
 * @param $expandElem expander
 *
 * @returns void
 */
function collapseTreeNode($expandElem) {
    var $children = $expandElem.closest('li').children('div.list_container');
    var $icon = $expandElem.find('img');
    if ($expandElem.hasClass('loaded')) {
        if ($icon.is('.ic_b_minus')) {
            $icon.removeClass('ic_b_minus').addClass('ic_b_plus');
            $children.slideUp('fast');
        }
    }
    $expandElem.blur();
    $children.promise().done(navTreeStateUpdate);
}

/**
 * Traverse the navigation tree backwards to generate all the actual
 * and virtual paths, as well as the positions in the pagination at
 * various levels, if necessary.
 *
 * @return Object
 */
function traverseNavigationForPaths() {
    var params = {
        pos: $('#pma_navigation_tree').find('div.dbselector select').val()
    };
    if ($('#navi_db_select').length) {
        return params;
    }
    var count = 0;
    $('#pma_navigation_tree').find('a.expander:visible').each(function () {
        if ($(this).find('img').is('.ic_b_minus') &&
            $(this).closest('li').find('div.list_container .ic_b_minus').length === 0
        ) {
            params['n' + count + '_aPath'] = $(this).find('span.aPath').text();
            params['n' + count + '_vPath'] = $(this).find('span.vPath').text();

            var pos2_name = $(this).find('span.pos2_name').text();
            if (! pos2_name) {
                pos2_name = $(this)
                    .parent()
                    .parent()
                    .find('span.pos2_name:last')
                    .text();
            }
            var pos2_value = $(this).find('span.pos2_value').text();
            if (! pos2_value) {
                pos2_value = $(this)
                    .parent()
                    .parent()
                    .find('span.pos2_value:last')
                    .text();
            }

            params['n' + count + '_pos2_name'] = pos2_name;
            params['n' + count + '_pos2_value'] = pos2_value;

            params['n' + count + '_pos3_name'] = $(this).find('span.pos3_name').text();
            params['n' + count + '_pos3_value'] = $(this).find('span.pos3_value').text();
            count++;
        }
    });
    return params;
}

/**
 * Executed on page load
 */
$(function () {
    if (! $('#pma_navigation').length) {
        // Don't bother running any code if the navigation is not even on the page
        return;
    }

    // Do not let the page reload on submitting the fast filter
    $(document).on('submit', '.fast_filter', function (event) {
        event.preventDefault();
    });

    // Fire up the resize handlers
    new ResizeHandler();

    /**
     * opens/closes (hides/shows) tree elements
     * loads data via ajax
     */
    $(document).on('click', '#pma_navigation_tree a.expander', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var $icon = $(this).find('img');
        if ($icon.is('.ic_b_plus')) {
            expandTreeNode($(this));
        } else {
            collapseTreeNode($(this));
        }
    });

    /**
     * Register event handler for click on the reload
     * navigation icon at the top of the panel
     */
    $(document).on('click', '#pma_navigation_reload', function (event) {
        event.preventDefault();
        // reload icon object
        var $icon = $(this).find('img');
        // source of the hidden throbber icon
        var icon_throbber_src = $('#pma_navigation').find('.throbber').attr('src');
        // source of the reload icon
        var icon_reload_src = $icon.attr('src');
        // replace the source of the reload icon with the one for throbber
        $icon.attr('src', icon_throbber_src);
        PMA_reloadNavigation();
        // after one second, put back the reload icon
        setTimeout(function () {
            $icon.attr('src', icon_reload_src);
        }, 1000);
    });

    $(document).on("change", '#navi_db_select',  function (event) {
        if (! $(this).val()) {
            PMA_commonParams.set('db', '');
            PMA_reloadNavigation();
        }
        $(this).closest('form').trigger('submit');
    });

    /**
     * Register event handler for click on the collapse all
     * navigation icon at the top of the navigation tree
     */
    $(document).on('click', '#pma_navigation_collapse', function (event) {
        event.preventDefault();
        $('#pma_navigation_tree').find('a.expander').each(function() {
            var $icon = $(this).find('img');
            if ($icon.is('.ic_b_minus')) {
                $(this).click();
            }
        });
    });

    /**
     * Register event handler to toggle
     * the 'link with main panel' icon on mouseenter.
     */
    $(document).on('mouseenter', '#pma_navigation_sync', function (event) {
        event.preventDefault();
        var synced = $('#pma_navigation_tree').hasClass('synced');
        var $img = $('#pma_navigation_sync').children('img');
        if (synced) {
            $img.removeClass('ic_s_link').addClass('ic_s_unlink');
        } else {
            $img.removeClass('ic_s_unlink').addClass('ic_s_link');
        }
    });

    /**
     * Register event handler to toggle
     * the 'link with main panel' icon on mouseout.
     */
    $(document).on('mouseout', '#pma_navigation_sync', function (event) {
        event.preventDefault();
        var synced = $('#pma_navigation_tree').hasClass('synced');
        var $img = $('#pma_navigation_sync').children('img');
        if (synced) {
            $img.removeClass('ic_s_unlink').addClass('ic_s_link');
        } else {
            $img.removeClass('ic_s_link').addClass('ic_s_unlink');
        }
    });

    /**
     * Register event handler to toggle
     * the linking with main panel behavior
     */
    $(document).on('click', '#pma_navigation_sync', function (event) {
        event.preventDefault();
        var synced = $('#pma_navigation_tree').hasClass('synced');
        var $img = $('#pma_navigation_sync').children('img');
        if (synced) {
            $img
                .removeClass('ic_s_unlink')
                .addClass('ic_s_link')
                .attr('alt', PMA_messages.linkWithMain)
                .attr('title', PMA_messages.linkWithMain);
            $('#pma_navigation_tree')
                .removeClass('synced')
                .find('li.selected')
                .removeClass('selected');
        } else {
            $img
                .removeClass('ic_s_link')
                .addClass('ic_s_unlink')
                .attr('alt', PMA_messages.unlinkWithMain)
                .attr('title', PMA_messages.unlinkWithMain);
            $('#pma_navigation_tree').addClass('synced');
            PMA_showCurrentNavigation();
        }
    });

    /**
     * Bind all "fast filter" events
     */
    $(document).on('click', '#pma_navigation_tree li.fast_filter span', PMA_fastFilter.events.clear);
    $(document).on('focus', '#pma_navigation_tree li.fast_filter input.searchClause', PMA_fastFilter.events.focus);
    $(document).on('blur', '#pma_navigation_tree li.fast_filter input.searchClause', PMA_fastFilter.events.blur);
    $(document).on('keyup', '#pma_navigation_tree li.fast_filter input.searchClause', PMA_fastFilter.events.keyup);

    /**
     * Ajax handler for pagination
     */
    $(document).on('click', '#pma_navigation_tree div.pageselector a.ajax', function (event) {
        event.preventDefault();
        PMA_navigationTreePagination($(this));
    });

    /**
     * Node highlighting
     */
    $(document).on(
        'mouseover',
        '#pma_navigation_tree.highlight li:not(.fast_filter)',
        function () {
            if ($('li:visible', this).length === 0) {
                $(this).addClass('activePointer');
            }
        }
    );
    $(document).on(
        'mouseout',
        '#pma_navigation_tree.highlight li:not(.fast_filter)',
        function () {
            $(this).removeClass('activePointer');
        }
    );

    /** Create a Routine, Trigger or Event */
    $(document).on('click', 'li.new_procedure a.ajax, li.new_function a.ajax', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('routine');
        dialog.editorDialog(1, $(this));
    });
    $(document).on('click', 'li.new_trigger a.ajax', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('trigger');
        dialog.editorDialog(1, $(this));
    });
    $(document).on('click', 'li.new_event a.ajax', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('event');
        dialog.editorDialog(1, $(this));
    });

    /** Edit Routines, Triggers or Events */
    $(document).on('click', 'li.procedure > a.ajax, li.function > a.ajax', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('routine');
        dialog.editorDialog(0, $(this));
    });
    $(document).on('click', 'li.trigger > a.ajax', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('trigger');
        dialog.editorDialog(0, $(this));
    });
    $(document).on('click', 'li.event > a.ajax', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('event');
        dialog.editorDialog(0, $(this));
    });

    /** Execute Routines */
    $(document).on('click', 'li.procedure div a.ajax img,' +
        ' li.function div a.ajax img', function (event) {
        event.preventDefault();
        var dialog = new RTE.object('routine');
        dialog.executeDialog($(this).parent());
    });
    /** Export Triggers and Events */
    $(document).on('click', 'li.trigger div:eq(1) a.ajax img,' +
        ' li.event div:eq(1) a.ajax img', function (event) {
        event.preventDefault();
        var dialog = new RTE.object();
        dialog.exportDialog($(this).parent());
    });

    /** New index */
    $(document).on('click', '#pma_navigation_tree li.new_index a.ajax', function (event) {
        event.preventDefault();
        var url = $(this).attr('href').substr(
            $(this).attr('href').indexOf('?') + 1
        ) + '&ajax_request=true';
        var title = PMA_messages.strAddIndex;
        indexEditorDialog(url, title);
    });

    /** Edit index */
    $(document).on('click', 'li.index a.ajax', function (event) {
        event.preventDefault();
        var url = $(this).attr('href').substr(
            $(this).attr('href').indexOf('?') + 1
        ) + '&ajax_request=true';
        var title = PMA_messages.strEditIndex;
        indexEditorDialog(url, title);
    });

    /** New view */
    $(document).on('click', 'li.new_view a.ajax', function (event) {
        event.preventDefault();
        PMA_createViewDialog($(this));
    });

    /** Hide navigation tree item */
    $(document).on('click', 'a.hideNavItem.ajax', function (event) {
        event.preventDefault();
        $.ajax({
            type: 'POST',
            data: {
                server: PMA_commonParams.get('server'),
                token: PMA_commonParams.get('token')
            },
            url: $(this).attr('href') + '&ajax_request=true',
            success: function (data) {
                if (typeof data !== 'undefined' && data.success === true) {
                    PMA_reloadNavigation();
                } else {
                    PMA_ajaxShowMessage(data.error);
                }
            }
        });
    });

    /** Display a dialog to choose hidden navigation items to show */
    $(document).on('click', 'a.showUnhide.ajax', function (event) {
        event.preventDefault();
        var $msg = PMA_ajaxShowMessage();
        $.get($(this).attr('href') + '&ajax_request=1', function (data) {
            if (typeof data !== 'undefined' && data.success === true) {
                PMA_ajaxRemoveMessage($msg);
                var buttonOptions = {};
                buttonOptions[PMA_messages.strClose] = function () {
                    $(this).dialog("close");
                };
                $('<div/>')
                    .attr('id', 'unhideNavItemDialog')
                    .append(data.message)
                    .dialog({
                        width: 400,
                        minWidth: 200,
                        modal: true,
                        buttons: buttonOptions,
                        title: PMA_messages.strUnhideNavItem,
                        close: function () {
                            $(this).remove();
                        }
                    });
            } else {
                PMA_ajaxShowMessage(data.error);
            }
        });
    });

    /** Show a hidden navigation tree item */
    $(document).on('click', 'a.unhideNavItem.ajax', function (event) {
        event.preventDefault();
        var $tr = $(this).parents('tr');
        var $msg = PMA_ajaxShowMessage();
        $.ajax({
            type: 'POST',
            data: {
                server: PMA_commonParams.get('server'),
                token: PMA_commonParams.get('token')
            },
            url: $(this).attr('href') + '&ajax_request=true',
            success: function (data) {
                PMA_ajaxRemoveMessage($msg);
                if (typeof data !== 'undefined' && data.success === true) {
                    $tr.remove();
                    PMA_reloadNavigation();
                } else {
                    PMA_ajaxShowMessage(data.error);
                }
            }
        });
    });

    // Add/Remove favorite table using Ajax.
    $(document).on("click", ".favorite_table_anchor", function (event) {
        event.preventDefault();
        $self = $(this);
        var anchor_id = $self.attr("id");
        if($self.data("favtargetn") !== null) {
            if($('a[data-favtargets="' + $self.data("favtargetn") + '"]').length > 0)
            {
                $('a[data-favtargets="' + $self.data("favtargetn") + '"]').trigger('click');
                return;
            }
        }

        $.ajax({
            url: $self.attr('href'),
            cache: false,
            type: 'POST',
            data: {
                favorite_tables: (isStorageSupported('localStorage') && typeof window.localStorage.favorite_tables !== 'undefined')
                    ? window.localStorage.favorite_tables
                    : '',
                server: PMA_commonParams.get('server'),
                token: PMA_commonParams.get('token')
            },
            success: function (data) {
                if (data.changes) {
                    $('#pma_favorite_list').html(data.list);
                    $('#' + anchor_id).parent().html(data.anchor);
                    PMA_tooltip(
                        $('#' + anchor_id),
                        'a',
                        $('#' + anchor_id).attr("title")
                    );
                    // Update localStorage.
                    if (isStorageSupported('localStorage')) {
                        window.localStorage.favorite_tables = data.favorite_tables;
                    }
                } else {
                    PMA_ajaxShowMessage(data.message);
                }
            }
        });
    });
    // Check if session storage is supported
    if (isStorageSupported('sessionStorage')) {
        var storage = window.sessionStorage;
        // remove tree from storage if Navi_panel config form is submitted
        $(document).on('submit', 'form.config-form', function(event) {
            storage.removeItem('navTreePaths');
        });
        // Initialize if no previous state is defined
        if ($('#pma_navigation_tree_content').length &&
            typeof storage.navTreePaths === 'undefined'
        ) {
            PMA_reloadNavigation();
        } else if (PMA_commonParams.get('server') === storage.server &&
            PMA_commonParams.get('token') === storage.token
        ) {
            // Reload the tree to the state before page refresh
            PMA_reloadNavigation(navFilterStateRestore, JSON.parse(storage.navTreePaths));
        } else {
            // If the user is different
            navTreeStateUpdate();
        }
    }
});

/**
 * Expands a node in navigation tree.
 *
 * @param $expandElem expander
 * @param callback    callback function
 *
 * @returns void
 */
function expandTreeNode($expandElem, callback) {
    var $children = $expandElem.closest('li').children('div.list_container');
    var $icon = $expandElem.find('img');
    if ($expandElem.hasClass('loaded')) {
        if ($icon.is('.ic_b_plus')) {
            $icon.removeClass('ic_b_plus').addClass('ic_b_minus');
            $children.slideDown('fast');
        }
        if (callback && typeof callback == 'function') {
            callback.call();
        }
        $children.promise().done(navTreeStateUpdate);
    } else {
        var $throbber = $('#pma_navigation').find('.throbber')
            .first()
            .clone()
            .css({visibility: 'visible', display: 'block'})
            .click(false);
        $icon.hide();
        $throbber.insertBefore($icon);

        loadChildNodes(true, $expandElem, function (data) {
            if (typeof data !== 'undefined' && data.success === true) {
                var $destination = $expandElem.closest('li');
                $icon.removeClass('ic_b_plus').addClass('ic_b_minus');
                $children = $destination.children('div.list_container');
                $children.slideDown('fast');
                if ($destination.find('ul > li').length == 1) {
                    $destination.find('ul > li')
                        .find('a.expander.container')
                        .click();
                }
                if (callback && typeof callback == 'function') {
                    callback.call();
                }
                PMA_showFullName($destination);
            } else {
                PMA_ajaxShowMessage(data.error, false);
            }
            $icon.show();
            $throbber.remove();
            $children.promise().done(navTreeStateUpdate);
        });
    }
    $expandElem.blur();
}

/**
 * Auto-scrolls the newly chosen database
 *
 * @param  object   $element    The element to set to view
 * @param  boolean  $forceToTop Whether to force scroll to top
 *
 */
function scrollToView($element, $forceToTop) {
    navFilterStateRestore();
    var $container = $('#pma_navigation_tree_content');
    var elemTop = $element.offset().top - $container.offset().top;
    var textHeight = 20;
    var scrollPadding = 20; // extra padding from top of bottom when scrolling to view
    if (elemTop < 0 || $forceToTop) {
        $container.stop().animate({
            scrollTop: elemTop + $container.scrollTop() - scrollPadding
        });
    } else if (elemTop + textHeight > $container.height()) {
        $container.stop().animate({
            scrollTop: elemTop + textHeight - $container.height() + $container.scrollTop() + scrollPadding
        });
    }
}

/**
 * Expand the navigation and highlight the current database or table/view
 *
 * @returns void
 */
function PMA_showCurrentNavigation() {
    var db = PMA_commonParams.get('db');
    var table = PMA_commonParams.get('table');
    $('#pma_navigation_tree')
        .find('li.selected')
        .removeClass('selected');
    if (db) {
        var $dbItem = findLoadedItem(
            $('#pma_navigation_tree').find('> div'), db, 'database', !table
        );
        if ($('#navi_db_select').length &&
            $('option:selected', $('#navi_db_select')).length
        ) {
            if (! PMA_selectCurrentDb()) {
                return;
            }
            // If loaded database in navigation is not same as current one
            if ($('#pma_navigation_tree_content').find('span.loaded_db:first').text()
                !== $('#navi_db_select').val()
            ) {
                loadChildNodes(false, $('option:selected', $('#navi_db_select')), function (data) {
                    handleTableOrDb(table, $('#pma_navigation_tree_content'));
                    var $children = $('#pma_navigation_tree_content').children('div.list_container');
                    $children.promise().done(navTreeStateUpdate);
                });
            } else {
                handleTableOrDb(table, $('#pma_navigation_tree_content'));
            }
        } else if ($dbItem) {
            var $expander = $dbItem.children('div:first').children('a.expander');
            // if not loaded or loaded but collapsed
            if (! $expander.hasClass('loaded') ||
                $expander.find('img').is('.ic_b_plus')
            ) {
                expandTreeNode($expander, function () {
                    handleTableOrDb(table, $dbItem);
                });
            } else {
                handleTableOrDb(table, $dbItem);
            }
        }
    } else if ($('#navi_db_select').length && $('#navi_db_select').val()) {
        $('#navi_db_select').val('').hide().trigger('change');
    }
    PMA_showFullName($('#pma_navigation_tree'));

    function handleTableOrDb(table, $dbItem) {
        if (table) {
            loadAndHighlightTableOrView($dbItem, table);
        } else {
            var $container = $dbItem.children('div.list_container');
            var $tableContainer = $container.children('ul').children('li.tableContainer');
            if ($tableContainer.length > 0) {
                var $expander = $tableContainer.children('div:first').children('a.expander');
                $tableContainer.addClass('selected');
                expandTreeNode($expander, function () {
                    scrollToView($dbItem, true);
                });
            } else {
                scrollToView($dbItem, true);
            }
        }
    }

    function findLoadedItem($container, name, clazz, doSelect) {
        var ret = false;
        $container.children('ul').children('li').each(function () {
            var $li = $(this);
            // this is a navigation group, recurse
            if ($li.is('.navGroup')) {
                var $container = $li.children('div.list_container');
                var $childRet = findLoadedItem(
                    $container, name, clazz, doSelect
                );
                if ($childRet) {
                    ret = $childRet;
                    return false;
                }
            } else { // this is a real navigation item
                // name and class matches
                if (((clazz && $li.is('.' + clazz)) || ! clazz) &&
                        $li.children('a').text() == name) {
                    if (doSelect) {
                        $li.addClass('selected');
                    }
                    // taverse up and expand and parent navigation groups
                    $li.parents('.navGroup').each(function () {
                        var $cont = $(this).children('div.list_container');
                        if (! $cont.is(':visible')) {
                            $(this)
                                .children('div:first')
                                .children('a.expander')
                                .click();
                        }
                    });
                    ret = $li;
                    return false;
                }
            }
        });
        return ret;
    }

    function loadAndHighlightTableOrView($dbItem, itemName) {
        var $container = $dbItem.children('div.list_container');
        var $expander;
        var $whichItem = isItemInContainer($container, itemName, 'li.table, li.view');
        //If item already there in some container
        if ($whichItem) {
            //get the relevant container while may also be a subcontainer
            var $relatedContainer = $whichItem.closest('li.subContainer').length
                ? $whichItem.closest('li.subContainer')
                : $dbItem;
            $whichItem = findLoadedItem(
                $relatedContainer.children('div.list_container'),
                itemName, null, true
            );
            //Show directly
            showTableOrView($whichItem, $relatedContainer.children('div:first').children('a.expander'));
        //else if item not there, try loading once
        } else {
            var $sub_containers = $dbItem.find('.subContainer');
            //If there are subContainers i.e. tableContainer or viewContainer
            if($sub_containers.length > 0) {
                var $containers = [];
                $sub_containers.each(function (index) {
                    $containers[index] = $(this);
                    $expander = $containers[index]
                        .children('div:first')
                        .children('a.expander');
                    if (! $expander.hasClass('loaded')) {
                        loadAndShowTableOrView($expander, $containers[index], itemName);
                    }
                });
            // else if no subContainers
            } else {
                $expander = $dbItem
                    .children('div:first')
                    .children('a.expander');
                if (! $expander.hasClass('loaded')) {
                    loadAndShowTableOrView($expander, $dbItem, itemName);
                }
            }
        }
    }

    function loadAndShowTableOrView($expander, $relatedContainer, itemName) {
        loadChildNodes(true, $expander, function (data) {
            var $whichItem = findLoadedItem(
                $relatedContainer.children('div.list_container'),
                itemName, null, true
            );
            if ($whichItem) {
                showTableOrView($whichItem, $expander);
            }
        });
    }

    function showTableOrView($whichItem, $expander) {
        expandTreeNode($expander, function (data) {
            if ($whichItem) {
                scrollToView($whichItem, false);
            }
        });
    }

    function isItemInContainer($container, name, clazz)
    {
        var $whichItem = null;
        $items = $container.find(clazz);
        var found = false;
        $items.each(function () {
            if ($(this).children('a').text() == name) {
                $whichItem = $(this);
                return false;
            }
        });
        return $whichItem;
    }
}

/**
 * Disable navigation panel settings
 *
 * @return void
 */
function PMA_disableNaviSettings() {
    $('#pma_navigation_settings_icon').addClass('hide');
    $('#pma_navigation_settings').remove();
}

/**
 * Ensure that navigation panel settings is properly setup.
 * If not, set it up
 *
 * @return void
 */
function PMA_ensureNaviSettings(selflink) {
    $('#pma_navigation_settings_icon').removeClass('hide');

    if (!$('#pma_navigation_settings').length) {
        var params = {
            getNaviSettings: true,
            server: PMA_commonParams.get('server'),
            token: PMA_commonParams.get('token')
        };
        var url = $('#pma_navigation').find('a.navigation_url').attr('href');
        $.post(url, params, function (data) {
            if (typeof data !== 'undefined' && data.success) {
                $('#pma_navi_settings_container').html(data.message);
                setupRestoreField();
                setupValidation();
                setupConfigTabs();
                $('#pma_navigation_settings').find('form').attr('action', selflink);
            } else {
                PMA_ajaxShowMessage(data.error);
            }
        });
    } else {
        $('#pma_navigation_settings').find('form').attr('action', selflink);
    }
}

/**
 * Reloads the whole navigation tree while preserving its state
 *
 * @param  function     the callback function
 * @param  Object       stored navigation paths
 *
 * @return void
 */
function PMA_reloadNavigation(callback, paths) {
    var params = {
        reload: true,
        no_debug: true,
        server: PMA_commonParams.get('server'),
        token: PMA_commonParams.get('token')
    };
    paths = paths || traverseNavigationForPaths();
    $.extend(params, paths);
    if ($('#navi_db_select').length) {
        params.db = PMA_commonParams.get('db');
        requestNaviReload(params);
        return;
    }
    requestNaviReload(params);

    function requestNaviReload(params) {
        var url = $('#pma_navigation').find('a.navigation_url').attr('href');
        $.post(url, params, function (data) {
            if (typeof data !== 'undefined' && data.success) {
                $('#pma_navigation_tree').html(data.message).children('div').show();
                if ($('#pma_navigation_tree').hasClass('synced')) {
                    PMA_selectCurrentDb();
                    PMA_showCurrentNavigation();
                }
                // Fire the callback, if any
                if (typeof callback === 'function') {
                    callback.call();
                }
                navTreeStateUpdate();
            } else {
                PMA_ajaxShowMessage(data.error);
            }
        });
    }
}

function PMA_selectCurrentDb() {
    var $naviDbSelect = $('#navi_db_select');

    if (!$naviDbSelect.length) {
        return false;
    }

    if (PMA_commonParams.get('db')) { // db selected
        $naviDbSelect.show();
    }

    $naviDbSelect.val(PMA_commonParams.get('db'));
    return $naviDbSelect.val() === PMA_commonParams.get('db');

}

/**
 * Handles any requests to change the page in a branch of a tree
 *
 * This can be called from link click or select change event handlers
 *
 * @param object $this A jQuery object that points to the element that
 * initiated the action of changing the page
 *
 * @return void
 */
function PMA_navigationTreePagination($this) {
    var $msgbox = PMA_ajaxShowMessage();
    var isDbSelector = $this.closest('div.pageselector').is('.dbselector');
    var url, params;
    if ($this[0].tagName == 'A') {
        url = $this.attr('href');
        params = 'ajax_request=true&token=' + PMA_commonParams.get('token');
    } else { // tagName == 'SELECT'
        url = 'navigation.php';
        params = $this.closest("form").serialize() + '&ajax_request=true';
    }
    var searchClause = PMA_fastFilter.getSearchClause();
    if (searchClause) {
        params += '&searchClause=' + encodeURIComponent(searchClause);
    }
    if (isDbSelector) {
        params += '&full=true';
    } else {
        var searchClause2 = PMA_fastFilter.getSearchClause2($this);
        if (searchClause2) {
            params += '&searchClause2=' + encodeURIComponent(searchClause2);
        }
    }
    $.post(url, params, function (data) {
        if (typeof data !== 'undefined' && data.success) {
            PMA_ajaxRemoveMessage($msgbox);
            if (isDbSelector) {
                var val = PMA_fastFilter.getSearchClause();
                $('#pma_navigation_tree')
                    .html(data.message)
                    .children('div')
                    .show();
                if (val) {
                    $('#pma_navigation_tree')
                        .find('li.fast_filter input.searchClause')
                        .val(val);
                }
            } else {
                var $parent = $this.closest('div.list_container').parent();
                var val = PMA_fastFilter.getSearchClause2($this);
                $this.closest('div.list_container').html(
                    $(data.message).children().show()
                );
                if (val) {
                    $parent.find('li.fast_filter input.searchClause').val(val);
                }
                $parent.find('span.pos2_value:first').text(
                    $parent.find('span.pos2_value:last').text()
                );
                $parent.find('span.pos3_value:first').text(
                    $parent.find('span.pos3_value:last').text()
                );
            }
        } else {
            PMA_ajaxShowMessage(data.error);
            PMA_handleRedirectAndReload(data);
        }
        navTreeStateUpdate();
    });
}

/**
 * @var ResizeHandler Custom object that manages the resizing of the navigation
 *
 * XXX: Must only be ever instanciated once
 * XXX: Inside event handlers the 'this' object is accessed as 'event.data.resize_handler'
 */
var ResizeHandler = function () {
    /**
     * @var int panel_width Used by the collapser to know where to go
     *                      back to when uncollapsing the panel
     */
    this.panel_width = 0;
    /**
     * @var string left Used to provide support for RTL languages
     */
    this.left = $('html').attr('dir') == 'ltr' ? 'left' : 'right';
    /**
     * Adjusts the width of the navigation panel to the specified value
     *
     * @param int pos Navigation width in pixels
     *
     * @return void
     */
    this.setWidth = function (pos) {
        var $resizer = $('#pma_navigation_resizer');
        var resizer_width = $resizer.width();
        var $collapser = $('#pma_navigation_collapser');
        $('#pma_navigation').width(pos);
        $('body').css('margin-' + this.left, pos + 'px');
        $("#floating_menubar, #pma_console")
            .css('margin-' + this.left, (pos + resizer_width) + 'px');
        $resizer.css(this.left, pos + 'px');
        if (pos === 0) {
            $collapser
                .css(this.left, pos + resizer_width)
                .html(this.getSymbol(pos))
                .prop('title', PMA_messages.strShowPanel);
        } else {
            $collapser
                .css(this.left, pos)
                .html(this.getSymbol(pos))
                .prop('title', PMA_messages.strHidePanel);
        }
        setTimeout(function () {
            $(window).trigger('resize');
        }, 4);
    };
    /**
     * Returns the horizontal position of the mouse,
     * relative to the outer side of the navigation panel
     *
     * @param int pos Navigation width in pixels
     *
     * @return void
     */
    this.getPos = function (event) {
        var pos = event.pageX;
        var windowWidth = $(window).width();
        var windowScroll = $(window).scrollLeft();
        pos = pos - windowScroll;
        if (this.left != 'left') {
            pos = windowWidth - event.pageX;
        }
        if (pos < 0) {
            pos = 0;
        } else if (pos + 100 >= windowWidth) {
            pos = windowWidth - 100;
        } else {
            this.panel_width = 0;
        }
        return pos;
    };
    /**
     * Returns the HTML code for the arrow symbol used in the collapser
     *
     * @param int width The width of the panel
     *
     * @return string
     */
    this.getSymbol = function (width) {
        if (this.left == 'left') {
            if (width === 0) {
                return '&rarr;';
            } else {
                return '&larr;';
            }
        } else {
            if (width === 0) {
                return '&larr;';
            } else {
                return '&rarr;';
            }
        }
    };
    /**
     * Event handler for initiating a resize of the panel
     *
     * @param object e Event data (contains a reference to resizeHandler)
     *
     * @return void
     */
    this.mousedown = function (event) {
        event.preventDefault();
        $(document)
            .bind('mousemove', {'resize_handler': event.data.resize_handler},
                $.throttle(event.data.resize_handler.mousemove, 4))
            .bind('mouseup', {'resize_handler': event.data.resize_handler},
                event.data.resize_handler.mouseup);
        $('body').css('cursor', 'col-resize');
    };
    /**
     * Event handler for terminating a resize of the panel
     *
     * @param object e Event data (contains a reference to resizeHandler)
     *
     * @return void
     */
    this.mouseup = function (event) {
        $('body').css('cursor', '');
        $.cookie('pma_navi_width', event.data.resize_handler.getPos(event));
        $('#topmenu').menuResizer('resize');
        $(document)
            .unbind('mousemove')
            .unbind('mouseup');
    };
    /**
     * Event handler for updating the panel during a resize operation
     *
     * @param object e Event data (contains a reference to resizeHandler)
     *
     * @return void
     */
    this.mousemove = function (event) {
        event.preventDefault();
        var pos = event.data.resize_handler.getPos(event);
        event.data.resize_handler.setWidth(pos);
        if ($('.sticky_columns').length !== 0) {
            handleAllStickyColumns();
        }
    };
    /**
     * Event handler for collapsing the panel
     *
     * @param object e Event data (contains a reference to resizeHandler)
     *
     * @return void
     */
    this.collapse = function (event) {
        event.preventDefault();
        var panel_width = event.data.resize_handler.panel_width;
        var width = $('#pma_navigation').width();
        if (width === 0 && panel_width === 0) {
            panel_width = 240;
        }
        event.data.resize_handler.setWidth(panel_width);
        event.data.resize_handler.panel_width = width;
    };
    /**
     * Event handler for resizing the navigation tree height on window resize
     *
     * @return void
     */
    this.treeResize = function (event) {
        var $nav        = $("#pma_navigation"),
            $nav_tree   = $("#pma_navigation_tree"),
            $nav_header = $("#pma_navigation_header"),
            $nav_tree_content = $("#pma_navigation_tree_content");
        $nav_tree.height($nav.height() - $nav_header.height());
        if ($nav_tree_content.length > 0) {
            $nav_tree_content.height($nav_tree.height() - $nav_tree_content.position().top);
        } else {
            //TODO: in fast filter search response there is no #pma_navigation_tree_content, needs to be added in php
            $nav_tree.css({
                'overflow-y': 'auto'
            });
        }
        // Set content bottom space beacuse of console
        $('body').css('margin-bottom', $('#pma_console').height() + 'px');
    };
    /* Initialisation section begins here */
    if ($.cookie('pma_navi_width')) {
        // If we have a cookie, set the width of the panel to its value
        var pos = Math.abs(parseInt($.cookie('pma_navi_width'), 10) || 0);
        this.setWidth(pos);
        $('#topmenu').menuResizer('resize');
    }
    // Register the events for the resizer and the collapser
    $(document).on('mousedown', '#pma_navigation_resizer', {'resize_handler': this}, this.mousedown);
    $(document).on('click', '#pma_navigation_collapser', {'resize_handler': this}, this.collapse);

    // Add the correct arrow symbol to the collapser
    $('#pma_navigation_collapser').html(this.getSymbol($('#pma_navigation').width()));
    // Fix navigation tree height
    $(window).on('resize', this.treeResize);
    // need to call this now and then, browser might decide
    // to show/hide horizontal scrollbars depending on page content width
    setInterval(this.treeResize, 2000);
    this.treeResize();
}; // End of ResizeHandler

/**
 * @var object PMA_fastFilter Handles the functionality that allows filtering
 *                            of the items in a branch of the navigation tree
 */
var PMA_fastFilter = {
    /**
     * Construct for the asynchronous fast filter functionality
     *
     * @param object $this        A jQuery object pointing to the list container
     *                            which is the nearest parent of the fast filter
     * @param string searchClause The query string for the filter
     *
     * @return new PMA_fastFilter.filter object
     */
    filter: function ($this, searchClause) {
        /**
         * @var object $this A jQuery object pointing to the list container
         *                   which is the nearest parent of the fast filter
         */
        this.$this = $this;
        /**
         * @var bool searchClause The query string for the filter
         */
        this.searchClause = searchClause;
        /**
         * @var object $clone A clone of the original contents
         *                    of the navigation branch before
         *                    the fast filter was applied
         */
        this.$clone = $this.clone();
        /**
         * @var object xhr A reference to the ajax request that is currently running
         */
        this.xhr = null;
        /**
         * @var int timeout Used to delay the request for asynchronous search
         */
        this.timeout = null;

        var $filterInput = $this.find('li.fast_filter input.searchClause');
        if ($filterInput.length !== 0 &&
            $filterInput.val() !== '' &&
            $filterInput.val() != $filterInput[0].defaultValue
        ) {
            this.request();
        }
    },
    /**
     * Gets the query string from the database fast filter form
     *
     * @return string
     */
    getSearchClause: function () {
        var retval = '';
        var $input = $('#pma_navigation_tree')
            .find('li.fast_filter.db_fast_filter input.searchClause');
        if ($input.length && $input.val() != $input[0].defaultValue) {
            retval = $input.val();
        }
        return retval;
    },
    /**
     * Gets the query string from a second level item's fast filter form
     * The retrieval is done by trasversing the navigation tree backwards
     *
     * @return string
     */
    getSearchClause2: function ($this) {
        var $filterContainer = $this.closest('div.list_container');
        var $filterInput = $([]);
        if ($filterContainer
            .find('li.fast_filter:not(.db_fast_filter) input.searchClause')
            .length !== 0) {
            $filterInput = $filterContainer
                .find('li.fast_filter:not(.db_fast_filter) input.searchClause');
        }
        var searchClause2 = '';
        if ($filterInput.length !== 0 &&
            $filterInput.first().val() != $filterInput[0].defaultValue
        ) {
            searchClause2 = $filterInput.val();
        }
        return searchClause2;
    },
    /**
     * @var hash events A list of functions that are bound to DOM events
     *                  at the top of this file
     */
    events: {
        focus: function (event) {
            var $obj = $(this).closest('div.list_container');
            if (! $obj.data('fastFilter')) {
                $obj.data(
                    'fastFilter',
                    new PMA_fastFilter.filter($obj, $(this).val())
                );
            }
            if ($(this).val() == this.defaultValue) {
                $(this).val('');
            } else {
                $(this).select();
            }
        },
        blur: function (event) {
            if ($(this).val() === '') {
                $(this).val(this.defaultValue);
            }
            var $obj = $(this).closest('div.list_container');
            if ($(this).val() == this.defaultValue && $obj.data('fastFilter')) {
                $obj.data('fastFilter').restore();
            }
        },
        keyup: function (event) {
            var $obj = $(this).closest('div.list_container');
            var str = '';
            if ($(this).val() != this.defaultValue && $(this).val() !== '') {
                $obj.find('div.pageselector').hide();
                str = $(this).val();
            }

            /**
             * FIXME at the server level a value match is done while on
             * the client side it is a regex match. These two should be aligned
             */

            // regex used for filtering.
            var regex;
            try {
                regex = new RegExp(str, 'i');
            } catch (err) {
                return;
            }

            // this is the div that houses the items to be filtered by this filter.
            var outerContainer;
            if ($(this).closest('li.fast_filter').is('.db_fast_filter')) {
                outerContainer = $('#pma_navigation_tree_content');
            } else {
                outerContainer = $obj;
            }

            // filters items that are directly under the div as well as grouped in
            // groups. Does not filter child items (i.e. a database search does
            // not filter tables)
            var item_filter = function($curr) {
                $curr.children('ul').children('li.navGroup').each(function() {
                    $(this).children('div.list_container').each(function() {
                        item_filter($(this)); // recursive
                    });
                });
                $curr.children('ul').children('li').children('a').not('.container').each(function() {
                    if (regex.test($(this).text())) {
                        $(this).parent().show().removeClass('hidden');
                    } else {
                        $(this).parent().hide().addClass('hidden');
                    }
                });
            };
            item_filter(outerContainer);

            // hides containers that does not have any visible children
            var container_filter = function ($curr) {
                $curr.children('ul').children('li.navGroup').each(function() {
                    var $group = $(this);
                    $group.children('div.list_container').each(function() {
                        container_filter($(this)); // recursive
                    });
                    $group.show().removeClass('hidden');
                    if ($group.children('div.list_container').children('ul')
                            .children('li').not('.hidden').length === 0) {
                        $group.hide().addClass('hidden');
                    }
                });
            };
            container_filter(outerContainer);

            if ($(this).val() != this.defaultValue && $(this).val() !== '') {
                if (! $obj.data('fastFilter')) {
                    $obj.data(
                        'fastFilter',
                        new PMA_fastFilter.filter($obj, $(this).val())
                    );
                } else {
                    if (event.keyCode == 13) {
                        $obj.data('fastFilter').update($(this).val());
                    }
                }
            } else if ($obj.data('fastFilter')) {
                $obj.data('fastFilter').restore(true);
            }
            // update filter state
            var filterName;
            if ($(this).attr('name') == 'searchClause2') {
                filterName = $(this).siblings('input[name=aPath]').val();
            } else {
                filterName = 'dbFilter';
            }
            navFilterStateUpdate(filterName, $(this).val());
        },
        clear: function (event) {
            event.stopPropagation();
            // Clear the input and apply the fast filter with empty input
            var filter = $(this).closest('div.list_container').data('fastFilter');
            if (filter) {
                filter.restore();
            }
            var value = $(this).prev()[0].defaultValue;
            $(this).prev().val(value).trigger('keyup');
        }
    }
};
/**
 * Handles a change in the search clause
 *
 * @param string searchClause The query string for the filter
 *
 * @return void
 */
PMA_fastFilter.filter.prototype.update = function (searchClause) {
    if (this.searchClause != searchClause) {
        this.searchClause = searchClause;
        this.request();
    }
};
/**
 * After a delay of 250mS, initiates a request to retrieve search results
 * Multiple calls to this function will always abort the previous request
 *
 * @return void
 */
PMA_fastFilter.filter.prototype.request = function () {
    var self = this;
    if (self.$this.find('li.fast_filter').find('img.throbber').length === 0) {
        self.$this.find('li.fast_filter').append(
            $('<div class="throbber"></div>').append(
                $('#pma_navigation_content')
                    .find('img.throbber')
                    .clone()
                    .css({visibility: 'visible', display: 'block'})
            )
        );
    }
    if (self.xhr) {
        self.xhr.abort();
    }
    var url = $('#pma_navigation').find('a.navigation_url').attr('href');
    var params = self.$this.find('> ul > li > form.fast_filter').first().serialize();

    if (self.$this.find('> ul > li > form.fast_filter:first input[name=searchClause]').length === 0) {
        var $input = $('#pma_navigation_tree').find('li.fast_filter.db_fast_filter input.searchClause');
        if ($input.length && $input.val() != $input[0].defaultValue) {
            params += '&searchClause=' + encodeURIComponent($input.val());
        }
    }
    self.xhr = $.ajax({
        url: url,
        type: 'post',
        dataType: 'json',
        data: params,
        complete: function (jqXHR, status) {
            if (status != 'abort') {
                var data = JSON.parse(jqXHR.responseText);
                self.$this.find('li.fast_filter').find('div.throbber').remove();
                if (data && data.results) {
                    self.swap.apply(self, [data.message]);
                }
            }
        }
    });
};
/**
 * Replaces the contents of the navigation branch with the search results
 *
 * @param string list The search results
 *
 * @return void
 */
PMA_fastFilter.filter.prototype.swap = function (list) {
    this.$this
        .html($(list).html())
        .children()
        .show()
        .end()
        .find('li.fast_filter input.searchClause')
        .val(this.searchClause);
    this.$this.data('fastFilter', this);
};
/**
 * Restores the navigation to the original state after the fast filter is cleared
 *
 * @param bool focus Whether to also focus the input box of the fast filter
 *
 * @return void
 */
PMA_fastFilter.filter.prototype.restore = function (focus) {
    if(this.$this.children('ul').first().hasClass('search_results')) {
        this.$this.html(this.$clone.html()).children().show();
        this.$this.data('fastFilter', this);
        if (focus) {
            this.$this.find('li.fast_filter input.searchClause').focus();
        }
    }
    this.searchClause = '';
    this.$this.find('div.pageselector').show();
    this.$this.find('div.throbber').remove();
};

/**
 * Show full name when cursor hover and name not shown completely
 *
 * @param object $containerELem Container element
 *
 * @return void
 */
function PMA_showFullName($containerELem) {

    $containerELem.find('.hover_show_full').mouseenter(function() {
        /** mouseenter */
        var $this = $(this);
        var thisOffset = $this.offset();
        if($this.text() === '') {
            return;
        }
        var $parent = $this.parent();
        if(  ($parent.offset().left + $parent.outerWidth())
           < (thisOffset.left + $this.outerWidth()))
        {
            var $fullNameLayer = $('#full_name_layer');
            if($fullNameLayer.length === 0)
            {
                $('body').append('<div id="full_name_layer" class="hide"></div>');
                $('#full_name_layer').mouseleave(function() {
                    /** mouseleave */
                    $(this).addClass('hide')
                           .removeClass('hovering');
                }).mouseenter(function() {
                    /** mouseenter */
                    $(this).addClass('hovering');
                });
                $fullNameLayer = $('#full_name_layer');
            }
            $fullNameLayer.removeClass('hide');
            $fullNameLayer.css({left: thisOffset.left, top: thisOffset.top});
            $fullNameLayer.html($this.clone());
            setTimeout(function() {
                if(! $fullNameLayer.hasClass('hovering'))
                {
                    $fullNameLayer.trigger('mouseleave');
                }
            }, 200);
        }
    });
}
;

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * @fileoverview    function used for index manipulation pages
 * @name            Table Structure
 *
 * @requires    jQuery
 * @requires    jQueryUI
 * @required    js/functions.js
 */

/**
 * Returns the array of indexes based on the index choice
 *
 * @param index_choice index choice
 */
function PMA_getIndexArray(index_choice)
{
    var source_array = null;

    switch (index_choice.toLowerCase()) {
    case 'primary':
        source_array = primary_indexes;
        break;
    case 'unique':
        source_array = unique_indexes;
        break;
    case 'index':
        source_array = indexes;
        break;
    case 'fulltext':
        source_array = fulltext_indexes;
        break;
    case 'spatial':
        source_array = spatial_indexes;
        break;
    default:
        return null;
    }
    return source_array;
}

/**
 * Hides/shows the inputs and submits appropriately depending
 * on whether the index type chosen is 'SPATIAL' or not.
 */
function checkIndexType()
{
    /**
     * @var Object Dropdown to select the index choice.
     */
    var $select_index_choice = $('#select_index_choice');
    /**
     * @var Object Dropdown to select the index type.
     */
    var $select_index_type = $('#select_index_type');
    /**
     * @var Object Table header for the size column.
     */
    var $size_header = $('#index_columns').find('thead tr th:nth-child(2)');
    /**
     * @var Object Inputs to specify the columns for the index.
     */
    var $column_inputs = $('select[name="index[columns][names][]"]');
    /**
     * @var Object Inputs to specify sizes for columns of the index.
     */
    var $size_inputs = $('input[name="index[columns][sub_parts][]"]');
    /**
     * @var Object Footer containg the controllers to add more columns
     */
    var $add_more = $('#index_frm').find('.add_more');

    if ($select_index_choice.val() == 'SPATIAL') {
        // Disable and hide the size column
        $size_header.hide();
        $size_inputs.each(function () {
            $(this)
                .prop('disabled', true)
                .parent('td').hide();
        });

        // Disable and hide the columns of the index other than the first one
        var initial = true;
        $column_inputs.each(function () {
            $column_input = $(this);
            if (! initial) {
                $column_input
                    .prop('disabled', true)
                    .parent('td').hide();
            } else {
                initial = false;
            }
        });

        // Hide controllers to add more columns
        $add_more.hide();
    } else {
        // Enable and show the size column
        $size_header.show();
        $size_inputs.each(function () {
            $(this)
                .prop('disabled', false)
                .parent('td').show();
        });

        // Enable and show the columns of the index
        $column_inputs.each(function () {
            $(this)
                .prop('disabled', false)
                .parent('td').show();
        });

        // Show controllers to add more columns
        $add_more.show();
    }

    if ($select_index_choice.val() == 'SPATIAL' ||
            $select_index_choice.val() == 'FULLTEXT') {
        $select_index_type.val('').prop('disabled', true);
    } else {
        $select_index_type.prop('disabled', false)
    }
}

/**
 * Sets current index information into form parameters.
 *
 * @param array  source_array Array containing index columns
 * @param string index_choice Choice of index
 *
 * @return void
 */
function PMA_setIndexFormParameters(source_array, index_choice)
{
    if (index_choice == 'index') {
        $('input[name="indexes"]').val(JSON.stringify(source_array));
    } else {
        $('input[name="' + index_choice + '_indexes"]').val(JSON.stringify(source_array));
    }
}

/**
 * Removes a column from an Index.
 *
 * @param string col_index Index of column in form
 *
 * @return void
 */
function PMA_removeColumnFromIndex(col_index)
{
    // Get previous index details.
    var previous_index = $('select[name="field_key[' + col_index + ']"]')
        .attr('data-index');
    if (previous_index.length) {
        previous_index = previous_index.split(',');
        var source_array = PMA_getIndexArray(previous_index[0]);
        if (source_array == null) {
            return;
        }

        // Remove column from index array.
        var source_length = source_array[previous_index[1]].columns.length;
        for (var i=0; i<source_length; i++) {
            if (source_array[previous_index[1]].columns[i].col_index == col_index) {
                source_array[previous_index[1]].columns.splice(i, 1);
            }
        }

        // Remove index completely if no columns left.
        if (source_array[previous_index[1]].columns.length === 0) {
            source_array.splice(previous_index[1], 1);
        }

        // Update current index details.
        $('select[name="field_key[' + col_index + ']"]').attr('data-index', '');
        // Update form index parameters.
        PMA_setIndexFormParameters(source_array, previous_index[0].toLowerCase());
    }
}

/**
 * Adds a column to an Index.
 *
 * @param array  source_array Array holding corresponding indexes
 * @param string array_index  Index of an INDEX in array
 * @param string index_choice Choice of Index
 * @param string col_index    Index of column on form
 *
 * @return void
 */
function PMA_addColumnToIndex(source_array, array_index, index_choice, col_index)
{
    if (col_index >= 0) {
        // Remove column from other indexes (if any).
        PMA_removeColumnFromIndex(col_index);
    }
    var index_name = $('input[name="index[Key_name]"]').val();
    var index_comment = $('input[name="index[Index_comment]"]').val();
    var key_block_size = $('input[name="index[Key_block_size]"]').val();
    var parser = $('input[name="index[Parser]"]').val();
    var index_type = $('select[name="index[Index_type]"]').val();
    var columns = [];
    $('#index_columns').find('tbody').find('tr').each(function () {
        // Get columns in particular order.
        var col_index = $(this).find('select[name="index[columns][names][]"]').val();
        var size = $(this).find('input[name="index[columns][sub_parts][]"]').val();
        columns.push({
            'col_index': col_index,
            'size': size
        });
    });

    // Update or create an index.
    source_array[array_index] = {
        'Key_name': index_name,
        'Index_comment': index_comment,
        'Index_choice': index_choice.toUpperCase(),
        'Key_block_size': key_block_size,
        'Parser': parser,
        'Index_type': index_type,
        'columns': columns
    };

    // Display index name (or column list)
    var displayName = index_name;
    if (displayName == '') {
        var columnNames = [];
        $.each(columns, function () {
            columnNames.push($('input[name="field_name[' +  this.col_index + ']"]').val());
        });
        displayName = '[' + columnNames.join(', ') + ']';
    }
    $.each(columns, function () {
        var id = 'index_name_' + this.col_index + '_8';
        var $name = $('#' + id);
        if ($name.length == 0) {
            $name = $('<a id="' + id + '" href="#" class="ajax show_index_dialog"></a>');
            $name.insertAfter($('select[name="field_key[' + this.col_index + ']"]'));
        }
        var $text = $('<small>').text(displayName);
        $name.html($text);
    });

    if (col_index >= 0) {
        // Update index details on form.
        $('select[name="field_key[' + col_index + ']"]')
            .attr('data-index', index_choice + ',' + array_index);
    }
    PMA_setIndexFormParameters(source_array, index_choice.toLowerCase());
}

/**
 * Get choices list for a column to create a composite index with.
 *
 * @param string index_choice Choice of index
 * @param array  source_array Array hodling columns for particular index
 *
 * @return jQuery Object
 */
function PMA_getCompositeIndexList(source_array, col_index)
{
    // Remove any previous list.
    if ($('#composite_index_list').length) {
        $('#composite_index_list').remove();
    }

    // Html list.
    var $composite_index_list = $(
        '<ul id="composite_index_list">' +
        '<div>' + PMA_messages.strCompositeWith + '</div>' +
        '</ul>'
    );

    // Add each column to list available for composite index.
    var source_length = source_array.length;
    var already_present = false;
    for (var i=0; i<source_length; i++) {
        var sub_array_len = source_array[i].columns.length;
        var column_names = [];
        for (var j=0; j<sub_array_len; j++) {
            column_names.push(
                $('input[name="field_name[' + source_array[i].columns[j].col_index + ']"]').val()
            );

            if (col_index == source_array[i].columns[j].col_index) {
                already_present = true;
            }
        }

        $composite_index_list.append(
            '<li>' +
            '<input type="radio" name="composite_with" ' +
            (already_present ? 'checked="checked"' : '') +
            ' id="composite_index_' + i + '" value="' + i + '">' +
            '<label for="composite_index_' + i + '">' + column_names.join(', ') +
            '</lablel>' +
            '</li>'
        );
    }

    return $composite_index_list;
}

/**
 * Shows 'Add Index' dialog.
 *
 * @param array  source_array   Array holding particluar index
 * @param string array_index    Index of an INDEX in array
 * @param array  target_columns Columns for an INDEX
 * @param string col_index      Index of column on form
 * @param object index          Index detail object
 *
 * @return void
 */
function PMA_showAddIndexDialog(source_array, array_index, target_columns, col_index, index)
{
    // Prepare post-data.
    var $table = $('input[name="table"]');
    var table = $table.length > 0 ? $table.val() : '';
    var post_data = {
        server: PMA_commonParams.get('server'),
        token: PMA_commonParams.get('token'),
        db: $('input[name="db"]').val(),
        table: table,
        ajax_request: 1,
        create_edit_table: 1,
        index: index
    };

    var columns = {};
    for (var i=0; i<target_columns.length; i++) {
        var column_name = $('input[name="field_name[' + target_columns[i] + ']"]').val();
        var column_type = $('select[name="field_type[' + target_columns[i] + ']"]').val().toLowerCase();
        columns[column_name] = [column_type, target_columns[i]];
    }
    post_data.columns = JSON.stringify(columns);

    var button_options = {};
    button_options[PMA_messages.strGo] = function () {
        var is_missing_value = false;
        $('select[name="index[columns][names][]"]').each(function () {
            if ($(this).val() === '') {
                is_missing_value = true;
            }
        });

        if (! is_missing_value) {
            PMA_addColumnToIndex(
                source_array,
                array_index,
                index.Index_choice,
                col_index
            );
        } else {
            PMA_ajaxShowMessage(
                '<div class="error"><img src="themes/dot.gif" title="" alt=""' +
                ' class="icon ic_s_error" /> ' + PMA_messages.strMissingColumn +
                ' </div>', false
            );

            return false;
        }

        $(this).dialog('close');
    };
    button_options[PMA_messages.strCancel] = function () {
        if (col_index >= 0) {
            // Handle state on 'Cancel'.
            var $select_list = $('select[name="field_key[' + col_index + ']"]');
            if (! $select_list.attr('data-index').length) {
                $select_list.find('option[value*="none"]').attr('selected', 'selected');
            } else {
                var previous_index = $select_list.attr('data-index').split(',');
                $select_list.find('option[value*="' + previous_index[0].toLowerCase() + '"]')
                    .attr('selected', 'selected');
            }
        }
        $(this).dialog('close');
    };
    var $msgbox = PMA_ajaxShowMessage();
    $.post("tbl_indexes.php", post_data, function (data) {
        if (data.success === false) {
            //in the case of an error, show the error message returned.
            PMA_ajaxShowMessage(data.error, false);
        } else {
            PMA_ajaxRemoveMessage($msgbox);
            // Show dialog if the request was successful
            var $div = $('<div/>');
            $div
            .append(data.message)
            .dialog({
                title: PMA_messages.strAddIndex,
                width: 450,
                minHeight: 250,
                open: function () {
                    checkIndexName("index_frm");
                    PMA_showHints($div);
                    PMA_init_slider();
                    $('#index_columns').find('td').each(function () {
                        $(this).css("width", $(this).width() + 'px');
                    });
                    $('#index_columns').find('tbody').sortable({
                        axis: 'y',
                        containment: $("#index_columns").find("tbody"),
                        tolerance: 'pointer'
                    });
                    // We dont need the slider at this moment.
                    $(this).find('fieldset.tblFooters').remove();
                },
                modal: true,
                buttons: button_options,
                close: function () {
                    $(this).remove();
                }
            });
        }
    });
}

/**
 * Creates a advanced index type selection dialog.
 *
 * @param array  source_array Array holding a particular type of indexes
 * @param string index_choice Choice of index
 * @param string col_index    Index of new column on form
 *
 * @return void
 */
function PMA_indexTypeSelectionDialog(source_array, index_choice, col_index)
{
    var $single_column_radio = $('<input type="radio" id="single_column" name="index_choice"' +
        ' checked="checked">' +
        '<label for="single_column">' + PMA_messages.strCreateSingleColumnIndex + '</label>');
    var $composite_index_radio = $('<input type="radio" id="composite_index"' +
        ' name="index_choice">' +
        '<label for="composite_index">' + PMA_messages.strCreateCompositeIndex + '</label>');
    var $dialog_content = $('<fieldset id="advance_index_creator"></fieldset>');
    $dialog_content.append('<legend>' + index_choice.toUpperCase() + '</legend>');


    // For UNIQUE/INDEX type, show choice for single-column and composite index.
    $dialog_content.append($single_column_radio);
    $dialog_content.append($composite_index_radio);

    var button_options = {};
    // 'OK' operation.
    button_options[PMA_messages.strGo] = function () {
        if ($('#single_column').is(':checked')) {
            var index = {
                'Key_name': (index_choice == 'primary' ? 'PRIMARY' : ''),
                'Index_choice': index_choice.toUpperCase()
            };
            PMA_showAddIndexDialog(source_array, (source_array.length), [col_index], col_index, index);
        }

        if ($('#composite_index').is(':checked')) {
            if ($('input[name="composite_with"]').length !== 0 && $('input[name="composite_with"]:checked').length === 0
            ) {
                PMA_ajaxShowMessage(
                    '<div class="error"><img src="themes/dot.gif" title=""' +
                    ' alt="" class="icon ic_s_error" /> ' +
                    PMA_messages.strFormEmpty +
                    ' </div>',
                    false
                );
                return false;
            }

            var array_index = $('input[name="composite_with"]:checked').val();
            var source_length = source_array[array_index].columns.length;
            var target_columns = [];
            for (var i=0; i<source_length; i++) {
                target_columns.push(source_array[array_index].columns[i].col_index);
            }
            target_columns.push(col_index);

            PMA_showAddIndexDialog(source_array, array_index, target_columns, col_index,
             source_array[array_index]);
        }

        $(this).remove();
    };
    button_options[PMA_messages.strCancel] = function () {
        // Handle state on 'Cancel'.
        var $select_list = $('select[name="field_key[' + col_index + ']"]');
        if (! $select_list.attr('data-index').length) {
            $select_list.find('option[value*="none"]').attr('selected', 'selected');
        } else {
            var previous_index = $select_list.attr('data-index').split(',');
            $select_list.find('option[value*="' + previous_index[0].toLowerCase() + '"]')
                .attr('selected', 'selected');
        }
        $(this).remove();
    };
    var $dialog = $('<div/>').append($dialog_content).dialog({
        minWidth: 525,
        minHeight: 200,
        modal: true,
        title: PMA_messages.strAddIndex,
        resizable: false,
        buttons: button_options,
        open: function () {
            $('#composite_index').on('change', function () {
                if ($(this).is(':checked')) {
                    $dialog_content.append(PMA_getCompositeIndexList(source_array, col_index));
                }
            });
            $('#single_column').on('change', function () {
                if ($(this).is(':checked')) {
                    if ($('#composite_index_list').length) {
                        $('#composite_index_list').remove();
                    }
                }
            });
        },
        close: function () {
            $('#composite_index').off('change');
            $('#single_column').off('change');
            $(this).remove();
        }
    });
}

/**
 * Unbind all event handlers before tearing down a page
 */
AJAX.registerTeardown('indexes.js', function () {
    $(document).off('click', '#save_index_frm');
    $(document).off('click', '#preview_index_frm');
    $(document).off('change', '#select_index_choice');
    $(document).off('click', 'a.drop_primary_key_index_anchor.ajax');
    $(document).off('click', "#table_index tbody tr td.edit_index.ajax, #indexes .add_index.ajax");
    $(document).off('click', '#index_frm input[type=submit]');
    $('body').off('change', 'select[name*="field_key"]');
    $(document).off('click', '.show_index_dialog');
});

/**
 * @description <p>Ajax scripts for table index page</p>
 *
 * Actions ajaxified here:
 * <ul>
 * <li>Showing/hiding inputs depending on the index type chosen</li>
 * <li>create/edit/drop indexes</li>
 * </ul>
 */
AJAX.registerOnload('indexes.js', function () {
    // Re-initialize variables.
    primary_indexes = [];
    unique_indexes = [];
    indexes = [];
    fulltext_indexes = [];
    spatial_indexes = [];

    // for table creation form
    var $engine_selector = $('.create_table_form select[name=tbl_storage_engine]');
    if ($engine_selector.length) {
        PMA_hideShowConnection($engine_selector);
    }

    var $form = $("#index_frm");
    if ($form.length > 0) {
        showIndexEditDialog($form);
    }

    $(document).on('click', '#save_index_frm', function (event) {
        event.preventDefault();
        var $form = $("#index_frm");
        var submitData = $form.serialize() + '&do_save_data=1&ajax_request=true&ajax_page_request=true';
        var $msgbox = PMA_ajaxShowMessage(PMA_messages.strProcessingRequest);
        AJAX.source = $form;
        $.post($form.attr('action'), submitData, AJAX.responseHandler);
    });

    $(document).on('click', '#preview_index_frm', function (event) {
        event.preventDefault();
        PMA_previewSQL($('#index_frm'));
    });

    $(document).on('change', '#select_index_choice', function (event) {
        event.preventDefault();
        checkIndexType();
        checkIndexName("index_frm");
    });

    /**
     * Ajax Event handler for 'Drop Index'
     */
    $(document).on('click', 'a.drop_primary_key_index_anchor.ajax', function (event) {
        event.preventDefault();
        var $anchor = $(this);
        /**
         * @var $curr_row    Object containing reference to the current field's row
         */
        var $curr_row = $anchor.parents('tr');
        /** @var    Number of columns in the key */
        var rows = $anchor.parents('td').attr('rowspan') || 1;
        /** @var    Rows that should be hidden */
        var $rows_to_hide = $curr_row;
        for (var i = 1, $last_row = $curr_row.next(); i < rows; i++, $last_row = $last_row.next()) {
            $rows_to_hide = $rows_to_hide.add($last_row);
        }

        var question = escapeHtml(
            $curr_row.children('td')
                .children('.drop_primary_key_index_msg')
                .val()
        );

        $anchor.PMA_confirm(question, $anchor.attr('href'), function (url) {
            var $msg = PMA_ajaxShowMessage(PMA_messages.strDroppingPrimaryKeyIndex, false);
            var params = {
                'is_js_confirmed': 1,
                'ajax_request': true,
                'token' : PMA_commonParams.get('token')
            };
            $.post(url, params, function (data) {
                if (typeof data !== 'undefined' && data.success === true) {
                    PMA_ajaxRemoveMessage($msg);
                    var $table_ref = $rows_to_hide.closest('table');
                    if ($rows_to_hide.length == $table_ref.find('tbody > tr').length) {
                        // We are about to remove all rows from the table
                        $table_ref.hide('medium', function () {
                            $('div.no_indexes_defined').show('medium');
                            $rows_to_hide.remove();
                        });
                        $table_ref.siblings('div.notice').hide('medium');
                    } else {
                        // We are removing some of the rows only
                        $rows_to_hide.hide("medium", function () {
                            $(this).remove();
                        });
                    }
                    if ($('.result_query').length) {
                        $('.result_query').remove();
                    }
                    if (data.sql_query) {
                        $('<div class="result_query"></div>')
                            .html(data.sql_query)
                            .prependTo('#structure_content');
                        PMA_highlightSQL($('#page_content'));
                    }
                    PMA_commonActions.refreshMain(false, function () {
                        $("a.ajax[href^=#indexes]").click();
                    });
                    PMA_reloadNavigation();
                } else {
                    PMA_ajaxShowMessage(PMA_messages.strErrorProcessingRequest + " : " + data.error, false);
                }
            }); // end $.post()
        }); // end $.PMA_confirm()
    }); //end Drop Primary Key/Index

    /**
     *Ajax event handler for index edit
    **/
    $(document).on('click', "#table_index tbody tr td.edit_index.ajax, #indexes .add_index.ajax", function (event) {
        event.preventDefault();
        var url, title;
        if ($(this).find("a").length === 0) {
            // Add index
            var valid = checkFormElementInRange(
                $(this).closest('form')[0],
                'added_fields',
                'Column count has to be larger than zero.'
            );
            if (! valid) {
                return;
            }
            url = $(this).closest('form').serialize();
            title = PMA_messages.strAddIndex;
        } else {
            // Edit index
            url = $(this).find("a").attr("href");
            if (url.substring(0, 16) == "tbl_indexes.php?") {
                url = url.substring(16, url.length);
            }
            title = PMA_messages.strEditIndex;
        }
        url += "&ajax_request=true";
        indexEditorDialog(url, title, function () {
            // refresh the page using ajax
            PMA_commonActions.refreshMain(false, function () {
                $("a.ajax[href^=#indexes]").click();
            });
        });
    });

    /**
     * Ajax event handler for advanced index creation during table creation
     * and column addition.
     */
    $('body').on('change', 'select[name*="field_key"]', function () {
        // Index of column on Table edit and create page.
        var col_index = /\d+/.exec($(this).attr('name'));
        col_index = col_index[0];
        // Choice of selected index.
        var index_choice = /[a-z]+/.exec($(this).val());
        index_choice = index_choice[0];
        // Array containing corresponding indexes.
        var source_array = null;

        if (index_choice == 'none') {
            PMA_removeColumnFromIndex(col_index);
            return false;
        }

        // Select a source array.
        source_array = PMA_getIndexArray(index_choice);
        if (source_array == null) {
            return;
        }

        if (source_array.length === 0) {
            var index = {
                'Key_name': (index_choice == 'primary' ? 'PRIMARY' : ''),
                'Index_choice': index_choice.toUpperCase()
            };
            PMA_showAddIndexDialog(source_array, 0, [col_index], col_index, index);
        } else {
            if (index_choice == 'primary') {
                var array_index = 0;
                var source_length = source_array[array_index].columns.length;
                var target_columns = [];
                for (var i=0; i<source_length; i++) {
                    target_columns.push(source_array[array_index].columns[i].col_index);
                }
                target_columns.push(col_index);

                PMA_showAddIndexDialog(source_array, array_index, target_columns, col_index,
                 source_array[array_index]);
            } else {
                // If there are multiple columns selected for an index, show advanced dialog.
                PMA_indexTypeSelectionDialog(source_array, index_choice, col_index);
            }
        }
    });

    $(document).on('click', '.show_index_dialog', function (e) {
        e.preventDefault();

        // Get index details.
        var previous_index = $(this).prev('select')
            .attr('data-index')
            .split(',');

        var index_choice = previous_index[0];
        var array_index  = previous_index[1];

        var source_array = PMA_getIndexArray(index_choice);
        var source_length = source_array[array_index].columns.length;

        var target_columns = [];
        for (var i = 0; i < source_length; i++) {
            target_columns.push(source_array[array_index].columns[i].col_index);
        }

        PMA_showAddIndexDialog(source_array, array_index, target_columns, -1, source_array[array_index]);
    })
});
;

/* vim: set expandtab sw=4 ts=4 sts=4: */

$(function () {
    checkNumberOfFields();
});

/**
 * Holds common parameters such as server, db, table, etc
 *
 * The content for this is normally loaded from Header.php or
 * Response.php and executed by ajax.js
 */
var PMA_commonParams = (function () {
    /**
     * @var hash params An associative array of key value pairs
     * @access private
     */
    var params = {};
    // The returned object is the public part of the module
    return {
        /**
         * Saves all the key value pair that
         * are provided in the input array
         *
         * @param obj hash The input array
         *
         * @return void
         */
        setAll: function (obj) {
            var reload = false;
            var updateNavigation = false;
            for (var i in obj) {
                if (params[i] !== undefined && params[i] !== obj[i]) {
                    if (i == 'db' || i == 'table') {
                        updateNavigation = true;
                    }
                    reload = true;
                }
                params[i] = obj[i];
            }
            if (updateNavigation &&
                    $('#pma_navigation_tree').hasClass('synced')
            ) {
                PMA_showCurrentNavigation();
            }
        },
        /**
         * Retrieves a value given its key
         * Returns empty string for undefined values
         *
         * @param name string The key
         *
         * @return string
         */
        get: function (name) {
            return params[name] || '';
        },
        /**
         * Saves a single key value pair
         *
         * @param name  string The key
         * @param value string The value
         *
         * @return self For chainability
         */
        set: function (name, value) {
            var updateNavigation = false;
            if (name == 'db' || name == 'table' &&
                params[name] !== value
            ) {
                updateNavigation = true;
            }
            params[name] = value;
            if (updateNavigation &&
                    $('#pma_navigation_tree').hasClass('synced')
            ) {
                PMA_showCurrentNavigation();
            }
            return this;
        },
        /**
         * Returns the url query string using the saved parameters
         *
         * @return string
         */
        getUrlQuery: function () {
            var common = this.get('common_query');
            var separator = '?';
            if (common.length > 0) {
                separator = '&';
            }
            return PMA_sprintf(
                '%s%sserver=%s&db=%s&table=%s',
                this.get('common_query'),
                separator,
                encodeURIComponent(this.get('server')),
                encodeURIComponent(this.get('db')),
                encodeURIComponent(this.get('table'))
            );
        }
    };
})();

/**
 * Holds common parameters such as server, db, table, etc
 *
 * The content for this is normally loaded from Header.php or
 * Response.php and executed by ajax.js
 */
var PMA_commonActions = {
    /**
     * Saves the database name when it's changed
     * and reloads the query window, if necessary
     *
     * @param new_db string new_db The name of the new database
     *
     * @return void
     */
    setDb: function (new_db) {
        if (new_db != PMA_commonParams.get('db')) {
            PMA_commonParams.setAll({'db': new_db, 'table': ''});
        }
    },
    /**
     * Opens a database in the main part of the page
     *
     * @param new_db string The name of the new database
     *
     * @return void
     */
    openDb: function (new_db) {
        PMA_commonParams
            .set('db', new_db)
            .set('table', '');
        this.refreshMain(
            PMA_commonParams.get('opendb_url')
        );
    },
    /**
     * Refreshes the main frame
     *
     * @param mixed url Undefined to refresh to the same page
     *                  String to go to a different page, e.g: 'index.php'
     *
     * @return void
     */
    refreshMain: function (url, callback) {
        if (! url) {
            url = $('#selflink').find('a').attr('href');
            url = url.substring(0, url.indexOf('?'));
        }
        url += PMA_commonParams.getUrlQuery();
        $('<a />', {href: url})
            .appendTo('body')
            .click()
            .remove();
        AJAX._callback = callback;
    }
};

/**
 * Class to handle PMA Drag and Drop Import
 *      feature
 */
PMA_DROP_IMPORT = {
    /**
     * @var int, count of total uploads in this view
     */
    uploadCount: 0,
    /**
     * @var int, count of live uploads
     */
    liveUploadCount: 0,
    /**
     * @var  string array, allowed extensions
     */
    allowedExtensions: ['sql', 'xml', 'ldi', 'mediawiki', 'shp'],
    /**
     * @var  string array, allowed extensions for compressed files
     */
    allowedCompressedExtensions: ['gz', 'bz2', 'zip'],
    /**
     * @var obj array to store message returned by import_status.php
     */
    importStatus: [],
    /**
     * Checks if any dropped file has valid extension or not
     *
     * @param file filename
     *
     * @return string, extension for valid extension, '' otherwise
     */
    _getExtension: function(file) {
        var arr = file.split('.');
        ext = arr[arr.length - 1];

        //check if compressed
        if (jQuery.inArray(ext.toLowerCase(),
            PMA_DROP_IMPORT.allowedCompressedExtensions) !== -1) {
            ext = arr[arr.length - 2];
        }

        //Now check for extension
        if (jQuery.inArray(ext.toLowerCase(),
            PMA_DROP_IMPORT.allowedExtensions) !== -1) {
            return ext;
        }
        return '';
    },
    /**
     * Shows upload progress for different sql uploads
     *
     * @param: hash (string), hash for specific file upload
     * @param: percent (float), file upload percentage
     *
     * @return void
     */
    _setProgress: function(hash, percent) {
        $('.pma_sql_import_status div li[data-hash="' +hash +'"]')
            .children('progress').val(percent);
    },
    /**
     * Function to upload the file asynchronously
     *
     * @param formData FormData object for a specific file
     * @param hash hash of the current file upload
     *
     * @return void
     */
    _sendFileToServer: function(formData, hash) {
        var uploadURL ="./import.php"; //Upload URL
        var extraData ={};
        var jqXHR = $.ajax({
            xhr: function() {
                var xhrobj = $.ajaxSettings.xhr();
                if (xhrobj.upload) {
                    xhrobj.upload.addEventListener('progress', function(event) {
                        var percent = 0;
                        var position = event.loaded || event.position;
                        var total = event.total;
                        if (event.lengthComputable) {
                            percent = Math.ceil(position / total * 100);
                        }
                        //Set progress
                        PMA_DROP_IMPORT._setProgress(hash, percent);
                    }, false);
                }
                return xhrobj;
            },
            url: uploadURL,
            type: "POST",
            contentType:false,
            processData: false,
            cache: false,
            data: formData,
            success: function(data){
                PMA_DROP_IMPORT._importFinished(hash, false, data.success);
                if (!data.success) {
                    PMA_DROP_IMPORT.importStatus[PMA_DROP_IMPORT.importStatus.length] = {
                        hash: hash,
                        message: data.error
                    };
                }
            }
        });

        // -- provide link to cancel the upload
        $('.pma_sql_import_status div li[data-hash="' + hash +
            '"] span.filesize').html('<span hash="' +
            hash + '" class="pma_drop_file_status" task="cancel">' +
            PMA_messages.dropImportMessageCancel + '</span>');

        // -- add event listener to this link to abort upload operation
        $('.pma_sql_import_status div li[data-hash="' + hash +
            '"] span.filesize span.pma_drop_file_status')
            .on('click', function() {
                if ($(this).attr('task') === 'cancel') {
                    jqXHR.abort();
                    $(this).html('<span>' +PMA_messages.dropImportMessageAborted +'</span>');
                    PMA_DROP_IMPORT._importFinished(hash, true, false);
                } else if ($(this).children("span").html() ===
                    PMA_messages.dropImportMessageFailed) {
                    // -- view information
                    var $this = $(this);
                    $.each( PMA_DROP_IMPORT.importStatus,
                    function( key, value ) {
                        if (value.hash === hash) {
                            $(".pma_drop_result:visible").remove();
                            var filename = $this.parent('span').attr('data-filename');
                            $("body").append('<div class="pma_drop_result"><h2>' +
                                PMA_messages.dropImportImportResultHeader + ' - ' +
                                filename +'<span class="close">x</span></h2>' +value.message +'</div>');
                            $(".pma_drop_result").draggable();  //to make this dialog draggable
                        }
                    });
                }
            });
    },
    /**
     * Triggered when an object is dragged into the PMA UI
     *
     * @param event obj
     *
     * @return void
     */
    _dragenter : function (event) {

        // We don't want to prevent users from using
        // browser's default drag-drop feature on some page(s)
        if ($(".noDragDrop").length !== 0) {
            return;
        }

        event.stopPropagation();
        event.preventDefault();
        if (!PMA_DROP_IMPORT._hasFiles(event)) {
            return;
        }
        if (PMA_commonParams.get('db') === '') {
            $(".pma_drop_handler").html(PMA_messages.dropImportSelectDB);
        } else {
            $(".pma_drop_handler").html(PMA_messages.dropImportDropFiles);
        }
        $(".pma_drop_handler").fadeIn();
    },
    /**
     * Check if dragged element contains Files
     *
     * @param event the event object
     *
     * @return bool
     */
    _hasFiles: function (event) {
        return !(typeof event.originalEvent.dataTransfer.types === 'undefined' ||
            $.inArray('Files', event.originalEvent.dataTransfer.types) < 0 ||
            $.inArray(
                'application/x-moz-nativeimage',
                event.originalEvent.dataTransfer.types
            ) >= 0);
    },
    /**
     * Triggered when dragged file is being dragged over PMA UI
     *
     * @param event obj
     *
     * @return void
     */
    _dragover: function (event) {
        // We don't want to prevent users from using
        // browser's default drag-drop feature on some page(s)
        if ($(".noDragDrop").length !== 0) {
            return;
        }

        event.stopPropagation();
        event.preventDefault();
        if (!PMA_DROP_IMPORT._hasFiles(event)) {
            return;
        }
        $(".pma_drop_handler").fadeIn();
    },
    /**
     * Triggered when dragged objects are left
     *
     * @param event obj
     *
     * @return void
     */
    _dragleave: function (event) {
        // We don't want to prevent users from using
        // browser's default drag-drop feature on some page(s)
        if ($(".noDragDrop").length !== 0) {
            return;
        }
        event.stopPropagation();
        event.preventDefault();
        var $pma_drop_handler = $(".pma_drop_handler");
        $pma_drop_handler.clearQueue().stop();
        $pma_drop_handler.fadeOut();
        $pma_drop_handler.html(PMA_messages.dropImportDropFiles);
    },
    /**
     * Called when upload has finished
     *
     * @param string, unique hash for a certain upload
     * @param bool, true if upload was aborted
     * @param bool, status of sql upload, as sent by server
     *
     * @return void
     */
    _importFinished: function(hash, aborted, status) {
        $('.pma_sql_import_status div li[data-hash="' +hash +'"]')
            .children("progress").hide();
        var icon = 'icon ic_s_success';
        // -- provide link to view upload status
        if (!aborted) {
            if (status) {
                $('.pma_sql_import_status div li[data-hash="' + hash +
                   '"] span.filesize span.pma_drop_file_status')
                   .html('<span>' +PMA_messages.dropImportMessageSuccess +'</a>');
            } else {
                $('.pma_sql_import_status div li[data-hash="' + hash +
                   '"] span.filesize span.pma_drop_file_status')
                   .html('<span class="underline">' + PMA_messages.dropImportMessageFailed +
                   '</a>');
                icon = 'icon ic_s_error';
            }
        } else {
            icon = 'icon ic_s_notice';
        }
        $('.pma_sql_import_status div li[data-hash="' + hash +
            '"] span.filesize span.pma_drop_file_status')
            .attr('task', 'info');

        // Set icon
        $('.pma_sql_import_status div li[data-hash="' +hash +'"]')
            .prepend('<img src="./themes/dot.gif" title="finished" class="' +
            icon +'"> ');

        // Decrease liveUploadCount by one
        $('.pma_import_count').html(--PMA_DROP_IMPORT.liveUploadCount);
        if (!PMA_DROP_IMPORT.liveUploadCount) {
            $('.pma_sql_import_status h2 .close').fadeIn();
        }
    },
    /**
     * Triggered when dragged objects are dropped to UI
     * From this function, the AJAX Upload operation is initiated
     *
     * @param event object
     *
     * @return void
     */
    _drop: function (event) {
        // We don't want to prevent users from using
        // browser's default drag-drop feature on some page(s)
        if ($(".noDragDrop").length !== 0) {
            return;
        }

        var dbname = PMA_commonParams.get('db');
        var server = PMA_commonParams.get('server');

        //if no database is selected -- no
        if (dbname !== '') {
            var files = event.originalEvent.dataTransfer.files;
            if (!files || files.length === 0) {
                // No files actually transferred
                $(".pma_drop_handler").fadeOut();
                event.stopPropagation();
                event.preventDefault();
                return;
            }
            $(".pma_sql_import_status").slideDown();
            for (var i = 0; i < files.length; i++) {
                var ext  = (PMA_DROP_IMPORT._getExtension(files[i].name));
                var hash = AJAX.hash(++PMA_DROP_IMPORT.uploadCount);

                var $pma_sql_import_status_div = $(".pma_sql_import_status div");
                $pma_sql_import_status_div.append('<li data-hash="' +hash +'">' +
                    ((ext !== '') ? '' : '<img src="./themes/dot.gif" title="invalid format" class="icon ic_s_notice"> ') +
                    escapeHtml(files[i].name) + '<span class="filesize" data-filename="' +
                    escapeHtml(files[i].name) +'">' +(files[i].size/1024).toFixed(2) +
                    ' kb</span></li>');

                //scroll the UI to bottom
                $pma_sql_import_status_div.scrollTop(
                    $pma_sql_import_status_div.scrollTop() + 50
                );  //50 hardcoded for now

                if (ext !== '') {
                    // Increment liveUploadCount by one
                    $('.pma_import_count').html(++PMA_DROP_IMPORT.liveUploadCount);
                    $('.pma_sql_import_status h2 .close').fadeOut();

                    $('.pma_sql_import_status div li[data-hash="' +hash +'"]')
                        .append('<br><progress max="100" value="2"></progress>');

                    //uploading
                    var fd = new FormData();
                    fd.append('import_file', files[i]);
                    fd.append('noplugin', Math.random().toString(36).substring(2, 12));
                    fd.append('db', dbname);
                    fd.append('server', server);
                    fd.append('token', PMA_commonParams.get('token'));
                    fd.append('import_type', 'database');
                    // todo: method to find the value below
                    fd.append('MAX_FILE_SIZE', '4194304');
                    // todo: method to find the value below
                    fd.append('charset_of_file','utf-8');
                    // todo: method to find the value below
                    fd.append('allow_interrupt', 'yes');
                    fd.append('skip_queries', '0');
                    fd.append('format',ext);
                    fd.append('sql_compatibility','NONE');
                    fd.append('sql_no_auto_value_on_zero','something');
                    fd.append('ajax_request','true');
                    fd.append('hash', hash);

                    // init uploading
                    PMA_DROP_IMPORT._sendFileToServer(fd, hash);
                } else if (!PMA_DROP_IMPORT.liveUploadCount) {
                    $('.pma_sql_import_status h2 .close').fadeIn();
                }
            }
        }
        $(".pma_drop_handler").fadeOut();
        event.stopPropagation();
        event.preventDefault();
    }
};

/**
 * Called when some user drags, dragover, leave
 *       a file to the PMA UI
 * @param object Event data
 * @return void
 */
$(document).on('dragenter', PMA_DROP_IMPORT._dragenter);
$(document).on('dragover', PMA_DROP_IMPORT._dragover);
$(document).on('dragleave', '.pma_drop_handler', PMA_DROP_IMPORT._dragleave);

//when file is dropped to PMA UI
$(document).on('drop', 'body', PMA_DROP_IMPORT._drop);

// minimizing-maximising the sql ajax upload status
$(document).on('click', '.pma_sql_import_status h2 .minimize', function() {
    if ($(this).attr('toggle') === 'off') {
        $('.pma_sql_import_status div').css('height','270px');
        $(this).attr('toggle','on');
        $(this).html('-');  // to minimize
    } else {
        $('.pma_sql_import_status div').css("height","0px");
        $(this).attr('toggle','off');
        $(this).html('+');  // to maximise
    }
});

// closing sql ajax upload status
$(document).on('click', '.pma_sql_import_status h2 .close', function() {
    $('.pma_sql_import_status').fadeOut(function() {
        $('.pma_sql_import_status div').html('');
        PMA_DROP_IMPORT.importStatus = [];  //clear the message array
    });
});

// Closing the import result box
$(document).on('click', '.pma_drop_result h2 .close', function(){
    $(this).parent('h2').parent('div').remove();
});
;

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * @fileoverview    function used for page-related settings
 * @name            Page-related settings
 *
 * @requires    jQuery
 * @requires    jQueryUI
 * @required    js/functions.js
 */

function showSettings(selector) {
    var buttons = {};
    buttons[PMA_messages.strApply] = function() {
        $('.config-form').submit();
    };

    buttons[PMA_messages.strCancel] = function () {
        $(this).dialog('close');
    };

    // Keeping a clone to restore in case the user cancels the operation
    var $clone = $(selector + ' .page_settings').clone(true);
    $(selector)
    .dialog({
        title: PMA_messages.strPageSettings,
        width: 700,
        minHeight: 250,
        modal: true,
        open: function() {
            $(this).dialog('option', 'maxHeight', $(window).height() - $(this).offset().top);
        },
        close: function() {
            $(selector + ' .page_settings').replaceWith($clone);
        },
        buttons: buttons
    });
}

function showPageSettings() {
    showSettings('#page_settings_modal');
}

function showNaviSettings() {
    showSettings('#pma_navigation_settings');
}

AJAX.registerTeardown('page_settings.js', function () {
    $('#page_settings_icon').css('display', 'none');
    $('#page_settings_icon').unbind('click');
    $('#pma_navigation_settings_icon').unbind('click');
});

AJAX.registerOnload('page_settings.js', function () {
    if ($('#page_settings_modal').length) {
        $('#page_settings_icon').css('display', 'inline');
        $('#page_settings_icon').bind('click', showPageSettings);
    }
    $('#pma_navigation_settings_icon').bind('click', showNaviSettings);
});
;

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * @fileoverview    Handle shortcuts in various pages
 * @name            Shortcuts handler
 *
 * @requires    jQuery
 * @requires    jQueryUI
 */

/**
 * Register key events on load
 */
$(document).ready(function() {
    var databaseOp = false;
    var tableOp = false;
    var keyD = 68;
    var keyT = 84;
    var keyK = 75;
    var keyS = 83;
    var keyF = 70;
    var keyE = 69;
    var keyH = 72;
    var keyC = 67;
    var keyBackSpace = 8;
    $(document).keyup(function(e) {
        if( e.target.nodeName === 'INPUT' || e.target.nodeName === 'TEXTAREA' || e.target.nodeName === 'SELECT' ) {
            return;
        }

        if(e.keyCode === keyD) {
            setTimeout(function() {
                databaseOp = false;
            }, 2000);
        }
        else if(e.keyCode === keyT) {
            setTimeout(function() {
                tableOp = false;
            }, 2000);
        }
    });
    $(document).keydown(function(e) {
        if ( e.ctrlKey && e.altKey && e.keyCode === keyC ) {
            PMA_console.toggle();
        }

        if( e.ctrlKey && e.keyCode == keyK ) {
            e.preventDefault();
            PMA_console.toggle();
        }

        if( e.target.nodeName === 'INPUT' || e.target.nodeName === 'TEXTAREA' || e.target.nodeName === 'SELECT' ) {
            return;
        }

        var isTable;
        var isDb;
        if(e.keyCode === keyD) {
            databaseOp = true;
        }
        else if(e.keyCode === keyK) {
            e.preventDefault();
            PMA_console.toggle();
        }
        else if(e.keyCode === keyS) {
            if(databaseOp === true) {
                isTable = PMA_commonParams.get('table');
                isDb = PMA_commonParams.get('db');
                if(isDb && ! isTable) {
                    $('.tab .ic_b_props').first().trigger('click');
                }
            }
            else if(tableOp === true) {
                isTable = PMA_commonParams.get('table');
                isDb = PMA_commonParams.get('db');
                if(isDb && isTable) {
                    $('.tab .ic_b_props').first().trigger('click');
                }
            }
            else{
                $('#pma_navigation_settings_icon').trigger('click');
            }
        }
        else if(e.keyCode === keyF) {
            if(databaseOp === true) {
                isTable = PMA_commonParams.get('table');
                isDb = PMA_commonParams.get('db');
                if(isDb && ! isTable) {
                    $('.tab .ic_b_search').first().trigger('click');
                }
            }
            else if(tableOp === true) {
                isTable = PMA_commonParams.get('table');
                isDb = PMA_commonParams.get('db');
                if(isDb && isTable) {
                    $('.tab .ic_b_search').first().trigger('click');
                }
            }
        }
        else if(e.keyCode === keyT) {
            tableOp = true;
        }
        else if(e.keyCode === keyE) {
            $('.ic_b_export').first().trigger('click');
        }
        else if(e.keyCode === keyBackSpace) {
            window.history.back();
        }
        else if(e.keyCode === keyH) {
            $('.ic_b_home').first().trigger('click');
        }
    });
});
;

/**
 * @preserve jquery.fullscreen 1.1.5
 * https://github.com/kayahr/jquery-fullscreen-plugin
 * Copyright (C) 2012-2013 Klaus Reimer <k@ailis.de>
 * Licensed under the MIT license
 * (See http://www.opensource.org/licenses/mit-license)
 */
 
(function(jQuery) {

/**
 * Sets or gets the fullscreen state.
 * 
 * @param {boolean=} state
 *            True to enable fullscreen mode, false to disable it. If not
 *            specified then the current fullscreen state is returned.
 * @return {boolean|Element|jQuery|null}
 *            When querying the fullscreen state then the current fullscreen
 *            element (or true if browser doesn't support it) is returned
 *            when browser is currently in full screen mode. False is returned
 *            if browser is not in full screen mode. Null is returned if 
 *            browser doesn't support fullscreen mode at all. When setting 
 *            the fullscreen state then the current jQuery selection is 
 *            returned for chaining.
 * @this {jQuery}
 */
function fullScreen(state)
{
    var e, func, doc;
    
    // Do nothing when nothing was selected
    if (!this.length) return this;
    
    // We only use the first selected element because it doesn't make sense
    // to fullscreen multiple elements.
    e = (/** @type {Element} */ this[0]);
    
    // Find the real element and the document (Depends on whether the
    // document itself or a HTML element was selected)
    if (e.ownerDocument)
    {
        doc = e.ownerDocument;
    }
    else
    {
        doc = e;
        e = doc.documentElement;
    }
    
    // When no state was specified then return the current state.
    if (state == null)
    {
        // When fullscreen mode is not supported then return null
        if (!((/** @type {?Function} */ doc["exitFullscreen"])
            || (/** @type {?Function} */ doc["webkitExitFullscreen"])
            || (/** @type {?Function} */ doc["webkitCancelFullScreen"])
            || (/** @type {?Function} */ doc["msExitFullscreen"])
            || (/** @type {?Function} */ doc["mozCancelFullScreen"])))
        {
            return null;
        }
        
        // Check fullscreen state
        state = !!doc["fullscreenElement"]
            || !!doc["msFullscreenElement"]
            || !!doc["webkitIsFullScreen"]
            || !!doc["mozFullScreen"];
        if (!state) return state;
        
        // Return current fullscreen element or "true" if browser doesn't
        // support this
        return (/** @type {?Element} */ doc["fullscreenElement"])
            || (/** @type {?Element} */ doc["webkitFullscreenElement"])
            || (/** @type {?Element} */ doc["webkitCurrentFullScreenElement"])
            || (/** @type {?Element} */ doc["msFullscreenElement"])
            || (/** @type {?Element} */ doc["mozFullScreenElement"])
            || state;
    }
    
    // When state was specified then enter or exit fullscreen mode.
    if (state)
    {
        // Enter fullscreen
        func = (/** @type {?Function} */ e["requestFullscreen"])
            || (/** @type {?Function} */ e["webkitRequestFullscreen"])
            || (/** @type {?Function} */ e["webkitRequestFullScreen"])
            || (/** @type {?Function} */ e["msRequestFullscreen"])
            || (/** @type {?Function} */ e["mozRequestFullScreen"]);
        if (func) 
        {
            func.call(e);
        }
        return this;
    }
    else
    {
        // Exit fullscreen
        func = (/** @type {?Function} */ doc["exitFullscreen"])
            || (/** @type {?Function} */ doc["webkitExitFullscreen"])
            || (/** @type {?Function} */ doc["webkitCancelFullScreen"])
            || (/** @type {?Function} */ doc["msExitFullscreen"])
            || (/** @type {?Function} */ doc["mozCancelFullScreen"]);
        if (func) func.call(doc);
        return this;
    }
}

/**
 * Toggles the fullscreen mode.
 * 
 * @return {!jQuery}
 *            The jQuery selection for chaining.
 * @this {jQuery}
 */
function toggleFullScreen()
{
    return (/** @type {!jQuery} */ fullScreen.call(this, 
        !fullScreen.call(this)));
}

/**
 * Handles the browser-specific fullscreenchange event and triggers
 * a jquery event for it.
 *
 * @param {?Event} event
 *            The fullscreenchange event.
 */
function fullScreenChangeHandler(event)
{
    jQuery(document).trigger(new jQuery.Event("fullscreenchange"));
}

/**
 * Handles the browser-specific fullscreenerror event and triggers
 * a jquery event for it.
 *
 * @param {?Event} event
 *            The fullscreenerror event.
 */
function fullScreenErrorHandler(event)
{
    jQuery(document).trigger(new jQuery.Event("fullscreenerror"));
}

/**
 * Installs the fullscreenchange event handler.
 */
function installFullScreenHandlers()
{
    var e, change, error;
    
    // Determine event name
    e = document;
    if (e["webkitCancelFullScreen"])
    {
        change = "webkitfullscreenchange";
        error = "webkitfullscreenerror";
    }
    else if (e["msExitFullscreen"])
    {
        change = "MSFullscreenChange";
        error = "MSFullscreenError";
    }
    else if (e["mozCancelFullScreen"])
    {
        change = "mozfullscreenchange";
        error = "mozfullscreenerror";
    }
    else 
    {
        change = "fullscreenchange";
        error = "fullscreenerror";
    }

    // Install the event handlers
    jQuery(document).bind(change, fullScreenChangeHandler);
    jQuery(document).bind(error, fullScreenErrorHandler);
}

jQuery.fn["fullScreen"] = fullScreen;
jQuery.fn["toggleFullScreen"] = toggleFullScreen;
installFullScreenHandlers();

})(jQuery);
;

var designer_tables = [{name: "pdf_pages", key: "pg_nr", auto_inc: true},
                       {name: "table_coords", key: "id", auto_inc: true}];

var DesignerOfflineDB = (function () {
    var designerDB = {};
    var datastore = null;

    designerDB.open = function (callback) {
        var version = 1;
        var request = window.indexedDB.open("pma_designer", version);

        request.onupgradeneeded = function (e) {
            var db = e.target.result;
            e.target.transaction.onerror = designerDB.onerror;

            for (var t in designer_tables) {
                if (db.objectStoreNames.contains(designer_tables[t].name)) {
                    db.deleteObjectStore(designer_tables[t].name);
                }
            }

            for (var t in designer_tables) {
                db.createObjectStore(designer_tables[t].name, {
                    keyPath: designer_tables[t].key,
                    autoIncrement: designer_tables[t].auto_inc
                });
            }
        };

        request.onsuccess = function (e) {
            datastore = e.target.result;
            if (typeof callback !== 'undefined' && callback !== null) {
                callback(true);
            }
        };

        request.onerror = designerDB.onerror;
    };

    designerDB.loadObject = function (table, id, callback) {
        var db = datastore;
        var transaction = db.transaction([table], 'readwrite');
        var objStore = transaction.objectStore(table);
        var cursorRequest = objStore.get(parseInt(id));

        cursorRequest.onsuccess = function (e) {
            callback(e.target.result);
        };

        cursorRequest.onerror = designerDB.onerror;
    };

    designerDB.loadAllObjects = function (table, callback) {
        var db = datastore;
        var transaction = db.transaction([table], 'readwrite');
        var objStore = transaction.objectStore(table);
        var keyRange = IDBKeyRange.lowerBound(0);
        var cursorRequest = objStore.openCursor(keyRange);
        var results = [];

        transaction.oncomplete = function (e) {
            callback(results);
        };

        cursorRequest.onsuccess = function (e) {
            var result = e.target.result;
            if (Boolean(result) === false) {
                return;
            }
            results.push(result.value);
            result.continue();
        };

        cursorRequest.onerror = designerDB.onerror;
    };

    designerDB.loadFirstObject = function(table, callback) {
        var db = datastore;
        var transaction = db.transaction([table], 'readwrite');
        var objStore = transaction.objectStore(table);
        var keyRange = IDBKeyRange.lowerBound(0);
        var cursorRequest = objStore.openCursor(keyRange);
        var firstResult = null;

        transaction.oncomplete = function(e) {
            callback(firstResult);
        };

        cursorRequest.onsuccess = function(e) {
            var result = e.target.result;
            if (Boolean(result) === false) {
                return;
            }
            firstResult = result.value;
        };

        cursorRequest.onerror = designerDB.onerror;
    };

    designerDB.addObject = function(table, obj, callback) {
        var db = datastore;
        var transaction = db.transaction([table], 'readwrite');
        var objStore = transaction.objectStore(table);
        var request = objStore.put(obj);

        request.onsuccess = function(e) {
            if (typeof callback !== 'undefined' && callback !== null) {
                callback(e.currentTarget.result);
            }
        };

        request.onerror = designerDB.onerror;
    };

    designerDB.deleteObject = function(table, id, callback) {
        var db = datastore;
        var transaction = db.transaction([table], 'readwrite');
        var objStore = transaction.objectStore(table);
        var request = objStore.delete(parseInt(id));

        request.onsuccess = function(e) {
            if (typeof callback !== 'undefined' && callback !== null) {
                callback(true);
            }
        };

        request.onerror = designerDB.onerror;
    };

    designerDB.onerror = function(e) {
        console.log(e);
    };

    // Export the designerDB object.
    return designerDB;
}());
;

function PDFPage(db_name, page_descr, tbl_cords)
{
    this.pg_nr = null;
    this.db_name = db_name;
    this.page_descr = page_descr;
    this.tbl_cords = tbl_cords;
}

function TableCoordinate(db_name, table_name, pdf_pg_nr, x, y)
{
    this.id = null;
    this.db_name = db_name;
    this.table_name = table_name;
    this.pdf_pg_nr = pdf_pg_nr;
    this.x = x;
    this.y = y;
}
;

function Show_tables_in_landing_page(db)
{
    Load_first_page(db, function (page) {
        if (page) {
            Load_HTML_for_page(page.pg_nr);
            selected_page = page.pg_nr;
        } else {
            Show_new_page_tables(true);
        }
    });
}

function Save_to_new_page(db, page_name, table_positions, callback)
{
    Create_new_page(db, page_name, function (page) {
        if (page) {
            var tbl_cords = [];
            for (var pos = 0; pos < table_positions.length; pos++) {
                table_positions[pos].pdf_pg_nr = page.pg_nr;
                Save_table_positions(table_positions[pos], function (id) {
                    tbl_cords.push(id);
                    if (table_positions.length === tbl_cords.length) {
                        page.tbl_cords = tbl_cords;
                        DesignerOfflineDB.addObject('pdf_pages', page);
                    }
                });
            }
            if (typeof callback !== 'undefined') {
                callback(page);
            }
        }
    });
}

function Save_to_selected_page(db, page_id, page_name, table_positions, callback)
{
    Delete_page(page_id);
    Save_to_new_page(db, page_name, table_positions, function (page) {
        if (typeof callback !== 'undefined') {
            callback(page);
        }
        selected_page = page.pg_nr;
    });
}

function Create_new_page(db, page_name, callback)
{
    var newPage = new PDFPage(db, page_name);
    DesignerOfflineDB.addObject('pdf_pages', newPage, function (pg_nr) {
        newPage.pg_nr = pg_nr;
        if (typeof callback !== 'undefined') {
            callback(newPage);
        }
    });
}

function Save_table_positions(positions, callback)
{
    DesignerOfflineDB.addObject('table_coords', positions, callback);
}

function Create_page_list(db, callback)
{
    DesignerOfflineDB.loadAllObjects('pdf_pages', function (pages) {
        var html = "";
        for (var p = 0; p < pages.length; p++) {
            var page = pages[p];
            if (page.db_name == db) {
                html += '<option value="' + page.pg_nr + '">';
                html += escapeHtml(page.page_descr) + '</option>';
            }
        }
        if (typeof callback !== 'undefined') {
            callback(html);
        }
    });
}

function Delete_page(page_id, callback)
{
    DesignerOfflineDB.loadObject('pdf_pages', page_id, function (page) {
        if (page) {
            for (var i = 0; i < page.tbl_cords.length; i++) {
                DesignerOfflineDB.deleteObject('table_coords', page.tbl_cords[i]);
            }
            DesignerOfflineDB.deleteObject('pdf_pages', page_id, callback);
        }
    });
}

function Load_first_page(db, callback)
{
    DesignerOfflineDB.loadAllObjects('pdf_pages', function (pages) {
        var firstPage = null;
        for (var i = 0; i < pages.length; i++) {
            var page = pages[i];
            if (page.db_name == db) {
                // give preference to a page having same name as the db
                if (page.page_descr == db) {
                    callback(page);
                    return;
                }
                if (firstPage == null) {
                    firstPage = page;
                }
            }
        }
        callback(firstPage);
    });
}

function Show_new_page_tables(check)
{
    var all_tables = $("#id_scroll_tab").find("td input:checkbox");
    all_tables.prop('checked', check);
    for (var tab = 0; tab < all_tables.length; tab++) {
        var input = all_tables[tab];
        if (input.value) {
            var element = document.getElementById(input.value);
            element.style.top = Get_random(550, 20) + 'px';
            element.style.left = Get_random(700, 20) + 'px';
            VisibleTab(input, input.value);
        }
    }
    selected_page = -1;
    $("#page_name").text(PMA_messages.strUntitled);
    MarkUnsaved();
}

function Load_HTML_for_page(page_id)
{
    Show_new_page_tables(false);
    Load_page_objects(page_id, function (page, tbl_cords) {
        $("#name-panel").find("#page_name").text(page.page_descr);
        MarkSaved();
        for (var t = 0; t < tbl_cords.length; t++) {
            var tb_id = db + '.' + tbl_cords[t].table_name;
            var table = document.getElementById(tb_id);
            table.style.top = tbl_cords[t].y + 'px';
            table.style.left = tbl_cords[t].x + 'px';

            var checkbox = document.getElementById("check_vis_" + tb_id);
            checkbox.checked = true;
            VisibleTab(checkbox, checkbox.value);
        }
        selected_page = page.pg_nr;
    });
}

function Load_page_objects(page_id, callback)
{
    DesignerOfflineDB.loadObject('pdf_pages', page_id, function (page) {
        var tbl_cords = [];
        var count = page.tbl_cords.length;
        for (var i = 0; i < count; i++) {
            DesignerOfflineDB.loadObject('table_coords', page.tbl_cords[i], function (tbl_cord) {
                tbl_cords.push(tbl_cord);
                if (tbl_cords.length == count) {
                    if (typeof callback !== 'undefined') {
                        callback(page, tbl_cords);
                    }
                }
            });
        }
    });
}

function Get_random(max, min)
{
    var val = Math.random() * (max - min) + min;
    return Math.floor(val);
}
;

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * @fileoverview    function used in this file builds history tab and generates query.
  *
  * @requires    jQuery
  * @requires    moves.js
  * @version $Id$
  */

var history_array = []; // Global array to store history objects
var select_field = [];  // Global array to store informaation for columns which are used in select clause
var g_index;
var vqb_editor = null;

/**
 * To display details of objects(where,rename,Having,aggregate,groupby,orderby,having)
 *
 * @param index index of history_array where change is to be made
 *
**/

function detail(index)
{
    var type = history_array[index].get_type();
    var str;
    if (type == "Where") {
        str = 'Where ' + history_array[index].get_column_name() + history_array[index].get_obj().getrelation_operator() + history_array[index].get_obj().getquery();
    }
    if (type == "Rename") {
        str = 'Rename ' + history_array[index].get_column_name() + ' To ' + history_array[index].get_obj().getrename_to();
    }
    if (type == "Aggregate") {
        str = 'Select ' + history_array[index].get_obj().get_operator() + '( ' + history_array[index].get_column_name() + ' )';
    }
    if (type == "GroupBy") {
        str = 'GroupBy ' + history_array[index].get_column_name();
    }
    if (type == "OrderBy") {
        str = 'OrderBy ' + history_array[index].get_column_name() + ' ' + history_array[index].get_obj().get_order();
    }
    if (type == "Having") {
        str = 'Having ';
        if (history_array[index].get_obj().get_operator() != 'None') {
            str += history_array[index].get_obj().get_operator() + '( ' + history_array[index].get_column_name() + ' )';
            str += history_array[index].get_obj().getrelation_operator() + history_array[index].get_obj().getquery();
        } else {
            str = 'Having ' + history_array[index].get_column_name() + history_array[index].get_obj().getrelation_operator() + history_array[index].get_obj().getquery();
        }
    }
    return str;
}

/**
 * Sorts history_array[] first,using table name as the key and then generates the HTML code for history tab,
 * clubbing all objects of same tables together
 * This function is called whenever changes are made in history_array[]
 *
 *
 * @param {int}  init starting index of unsorted array
 * @param {int} finit   last index of unsorted array
 *
**/

function display(init, finit)
{
    var str, i, j, k, sto, temp;
    // this part sorts the history array based on table name,this is needed for clubbing all object of same name together.
    for (i = init; i < finit; i++) {
        sto = history_array[i];
        temp = history_array[i].get_tab();//+ '.' + history_array[i].get_obj_no(); for Self JOINS
        for (j = 0; j < i; j++) {
            if (temp > (history_array[j].get_tab())) {//+ '.' + history_array[j].get_obj_no())) { //for Self JOINS
                for (k = i; k > j; k--) {
                    history_array[k] = history_array[k - 1];
                }
                history_array[j] = sto;
                break;
            }
        }
    }
    // this part generates HTML code for history tab.adds delete,edit,and/or and detail features with objects.
    str = ''; // string to store Html code for history tab
    for (i = 0; i < history_array.length; i++) {
        temp = history_array[i].get_tab(); //+ '.' + history_array[i].get_obj_no(); for Self JOIN
        str += '<h3 class="tiger"><a href="#">' + temp + '</a></h3>';
        str += '<div class="toggle_container">\n';
        while ((history_array[i].get_tab()) == temp) { //+ '.' + history_array[i].get_obj_no()) == temp) {
            str += '<div class="block"> <table width ="250">';
            str += '<thead><tr><td>';
            if (history_array[i].get_and_or()) {
                str += '<img src="' + pmaThemeImage + 'pmd/or_icon.png" onclick="and_or(' + i + ')" title="OR"/></td>';
            } else {
                str += '<img src="' + pmaThemeImage + 'pmd/and_icon.png" onclick="and_or(' + i + ')" title="AND"/></td>';
            }
            str += '<td style="padding-left: 5px;" class="right">' + PMA_getImage('b_sbrowse.png', 'column name') + '</td>' +
                '<td width="175" style="padding-left: 5px">' + history_array[i].get_column_name() + '<td>';
            if (history_array[i].get_type() == "GroupBy" || history_array[i].get_type() == "OrderBy") {
                str += '<td class="center">' + PMA_getImage('s_info.png', detail(i)) + '</td>' +
                    '<td title="' + detail(i) + '">' + history_array[i].get_type() + '</td>' +
                    '<td onmouseover="this.className=\'history_table\';" onmouseout="this.className=\'history_table2\'" onclick=history_delete(' + i + ')>' + PMA_getImage('b_drop.png', PMA_messages.strDelete) + '</td>';
            } else {
                str += '<td class="center">' + PMA_getImage('s_info.png', detail(i)) + '</td>' +
                    '<td title="' + detail(i) + '">' + history_array[i].get_type() + '</td>' +
                    '<td onmouseover="this.className=\'history_table\';" onmouseout="this.className=\'history_table2\'" onclick=history_edit(' + i + ')>' + PMA_getImage('b_edit.png', PMA_messages.strEdit) + '</td>' +
                    '<td onmouseover="this.className=\'history_table\';" onmouseout="this.className=\'history_table2\'" onclick=history_delete(' + i + ')>' + PMA_getImage('b_drop.png', PMA_messages.strDelete) + '</td>';
            }
            str += '</tr></thead>';
            i++;
            if (i >= history_array.length) {
                break;
            }
            str += '</table></div>';
        }
        i--;
        str += '</div>';
    }
    return str;
}

/**
 * To change And/Or relation in history tab
 *
 *
 * @param {int} index of history_array where change is to be made
 *
**/

function and_or(index)
{
    if (history_array[index].get_and_or()) {
        history_array[index].set_and_or(0);
    } else {
        history_array[index].set_and_or(1);
    }
    var existingDiv = document.getElementById('ab');
    existingDiv.innerHTML = display(0, 0);
    $('#ab').accordion("refresh");
}

/**
 * Deletes entry in history_array
 *
 * @param index index of history_array[] which is to be deleted
 *
**/

function history_delete(index)
{
    for (var k = 0; k < from_array.length; k++) {
        if (from_array[k] == history_array[index].get_tab()) {
            from_array.splice(k, 1);
            break;
        }
    }
    history_array.splice(index, 1);
    var existingDiv = document.getElementById('ab');
    existingDiv.innerHTML = display(0, 0);
    $('#ab').accordion("refresh");
}

/**
 * To show where,rename,aggregate,having forms to edit a object
 *
 * @param{int} index index of history_array where change is to be made
 *
**/

function history_edit(index)
{
    g_index = index;
    var type = history_array[index].get_type();
    if (type == "Where") {
        document.getElementById('eQuery').value = history_array[index].get_obj().getquery();
        document.getElementById('erel_opt').value = history_array[index].get_obj().getrelation_operator();
        document.getElementById('query_where').style.left =  '530px';
        document.getElementById('query_where').style.top  = '130px';
        document.getElementById('query_where').style.position  = 'absolute';
        document.getElementById('query_where').style.zIndex = '103';
        document.getElementById('query_where').style.visibility = 'visible';
        document.getElementById('query_where').style.display = 'block';
    }
    if (type == "Having") {
        document.getElementById('hQuery').value = history_array[index].get_obj().getquery();
        document.getElementById('hrel_opt').value = history_array[index].get_obj().getrelation_operator();
        document.getElementById('hoperator').value = history_array[index].get_obj().get_operator();
        document.getElementById('query_having').style.left =  '530px';
        document.getElementById('query_having').style.top  = '130px';
        document.getElementById('query_having').style.position  = 'absolute';
        document.getElementById('query_having').style.zIndex = '103';
        document.getElementById('query_having').style.visibility = 'visible';
        document.getElementById('query_having').style.display = 'block';
    }
    if (type == "Rename") {
        document.getElementById('e_rename').value = history_array[index].get_obj().getrename_to();
        document.getElementById('query_rename_to').style.left =  '530px';
        document.getElementById('query_rename_to').style.top  = '130px';
        document.getElementById('query_rename_to').style.position  = 'absolute';
        document.getElementById('query_rename_to').style.zIndex = '103';
        document.getElementById('query_rename_to').style.visibility = 'visible';
        document.getElementById('query_rename_to').style.display = 'block';
    }
    if (type == "Aggregate") {
        document.getElementById('e_operator').value = history_array[index].get_obj().get_operator();
        document.getElementById('query_Aggregate').style.left = '530px';
        document.getElementById('query_Aggregate').style.top  = '130px';
        document.getElementById('query_Aggregate').style.position  = 'absolute';
        document.getElementById('query_Aggregate').style.zIndex = '103';
        document.getElementById('query_Aggregate').style.visibility = 'visible';
        document.getElementById('query_Aggregate').style.display = 'block';
    }
}

/**
 * Make changes in history_array when Edit button is clicked
 * checks for the type of object and then sets the new value
 *
 * @param index index of history_array where change is to be made
**/

function edit(type)
{
    if (type == "Rename") {
        if (document.getElementById('e_rename').value !== "") {
            history_array[g_index].get_obj().setrename_to(document.getElementById('e_rename').value);
            document.getElementById('e_rename').value = "";
        }
        document.getElementById('query_rename_to').style.visibility = 'hidden';
    }
    if (type == "Aggregate") {
        if (document.getElementById('e_operator').value != '---') {
            history_array[g_index].get_obj().set_operator(document.getElementById('e_operator').value);
            document.getElementById('e_operator').value = '---';
        }
        document.getElementById('query_Aggregate').style.visibility = 'hidden';
    }
    if (type == "Where") {
        if (document.getElementById('erel_opt').value != '--' && document.getElementById('eQuery').value !== "") {
            history_array[g_index].get_obj().setquery(document.getElementById('eQuery').value);
            history_array[g_index].get_obj().setrelation_operator(document.getElementById('erel_opt').value);
        }
        document.getElementById('query_where').style.visibility = 'hidden';
    }
    if (type == "Having") {
        if (document.getElementById('hrel_opt').value != '--' && document.getElementById('hQuery').value !== "") {
            history_array[g_index].get_obj().setquery(document.getElementById('hQuery').value);
            history_array[g_index].get_obj().setrelation_operator(document.getElementById('hrel_opt').value);
            history_array[g_index].get_obj().set_operator(document.getElementById('hoperator').value);
        }
        document.getElementById('query_having').style.visibility = 'hidden';
    }
    var existingDiv = document.getElementById('ab');
    existingDiv.innerHTML = display(0, 0);
    $('#ab').accordion("refresh");
}

/**
 * history object closure
 *
 * @param ncolumn_name  name of the column on which conditions are put
 * @param nobj          object details(where,rename,orderby,groupby,aggregate)
 * @param ntab          table name of the column on which conditions are applied
 * @param nobj_no       object no used for inner join
 * @param ntype         type of object
 *
**/

function history_obj(ncolumn_name, nobj, ntab, nobj_no, ntype)
{
    var and_or;
    var obj;
    var tab;
    var column_name;
    var obj_no;
    var type;
    this.set_column_name = function (ncolumn_name) {
        column_name = ncolumn_name;
    };
    this.get_column_name = function () {
        return column_name;
    };
    this.set_and_or = function (nand_or) {
        and_or = nand_or;
    };
    this.get_and_or = function () {
        return and_or;
    };
    this.get_relation = function () {
        return and_or;
    };
    this.set_obj = function (nobj) {
        obj = nobj;
    };
    this.get_obj = function () {
        return obj;
    };
    this.set_tab = function (ntab) {
        tab = ntab;
    };
    this.get_tab = function () {
        return tab;
    };
    this.set_obj_no = function (nobj_no) {
        obj_no = nobj_no;
    };
    this.get_obj_no = function () {
        return obj_no;
    };
    this.set_type = function (ntype) {
        type = ntype;
    };
    this.get_type = function () {
        return type;
    };
    this.set_obj_no(nobj_no);
    this.set_tab(ntab);
    this.set_and_or(0);
    this.set_obj(nobj);
    this.set_column_name(ncolumn_name);
    this.set_type(ntype);
}

/**
 * where object closure, makes an object with all information of where
 *
 * @param nrelation_operator type of relation operator to be applied
 * @param nquery             stores value of value/sub-query
 *
**/


var where = function (nrelation_operator, nquery) {
    var relation_operator;
    var query;
    this.setrelation_operator = function (nrelation_operator) {
        relation_operator = nrelation_operator;
    };
    this.setquery = function (nquery) {
        query = nquery;
    };
    this.getquery = function () {
        return query;
    };
    this.getrelation_operator = function () {
        return relation_operator;
    };
    this.setquery(nquery);
    this.setrelation_operator(nrelation_operator);
};

/**
 * Orderby object closure
 *
 * @param norder order, ASC or DESC
 */
var orderby = function(norder) {
    var order;
    this.set_order = function(norder) {
        order = norder;
    };
    this.get_order = function() {
        return order;
    };
    this.set_order(norder);
};

/**
 * Having object closure, makes an object with all information of where
 *
 * @param nrelation_operator type of relation operator to be applied
 * @param nquery             stores value of value/sub-query
 * @param noperator          operator
**/

var having = function (nrelation_operator, nquery, noperator) {
    var relation_operator;
    var query;
    var operator;
    this.set_operator = function (noperator) {
        operator = noperator;
    };
    this.setrelation_operator = function (nrelation_operator) {
        relation_operator = nrelation_operator;
    };
    this.setquery = function (nquery) {
        query = nquery;
    };
    this.getquery = function () {
        return query;
    };
    this.getrelation_operator = function () {
        return relation_operator;
    };
    this.get_operator = function () {
        return operator;
    };
    this.setquery(nquery);
    this.setrelation_operator(nrelation_operator);
    this.set_operator(noperator);
};

/**
 * rename object closure,makes an object with all information of rename
 *
 * @param nrename_to new name information
 *
**/

var rename = function (nrename_to) {
    var rename_to;
    this.setrename_to = function (nrename_to) {
        rename_to = nrename_to;
    };
    this.getrename_to = function () {
        return rename_to;
    };
    this.setrename_to(nrename_to);
};

/**
 * aggregate object closure
 *
 * @param noperator aggregte operator
 *
**/

var aggregate = function (noperator) {
    var operator;
    this.set_operator = function (noperator) {
        operator = noperator;
    };
    this.get_operator = function () {
        return operator;
    };
    this.set_operator(noperator);
};

/**
 * This function returns unique element from an array
 *
 * @param arrayName array from which duplicate elem are to be removed.
 * @return unique array
 */

function unique(arrayName)
{
    var newArray = [];
uniquetop:
    for (var i = 0; i < arrayName.length; i++) {
        for (var j = 0; j < newArray.length; j++) {
            if (newArray[j] == arrayName[i]) {
                continue uniquetop;
            }
        }
        newArray[newArray.length] = arrayName[i];
    }
    return newArray;
}

/**
 * This function takes in array and a value as input and returns 1 if values is present in array
 * else returns -1
 *
 * @param arrayName array
 * @param value  value which is to be searched in the array
 */

function found(arrayName, value)
{
    for (var i = 0; i < arrayName.length; i++) {
        if (arrayName[i] == value) {
            return 1;
        }
    }
    return -1;
}

/**
 * This function concatenates two array
 *
 * @params add array elements of which are pushed in
 * @params arr array in which elements are added
 */
function add_array(add, arr)
{
    for (var i = 0; i < add.length; i++) {
        arr.push(add[i]);
    }
    return arr;
}

/* This function removes all elements present in one array from the other.
 *
 * @params rem array from which each element is removed from other array.
 * @params arr array from which elements are removed.
 *
 */
function remove_array(rem, arr)
{
    for (var i = 0; i < rem.length; i++) {
        for (var j = 0; j < arr.length; j++) {
            if (rem[i] == arr[j]) {
                arr.splice(j, 1);
            }
        }
    }
    return arr;
}

/**
 * This function builds the groupby clause from history object
 *
 */

function query_groupby()
{
    var i;
    var str = "";
    for (i = 0; i < history_array.length;i++) {
        if (history_array[i].get_type() == "GroupBy") {
            str += '`' + history_array[i].get_column_name() + "`, ";
        }
    }
    str = str.substr(0, str.length - 2);
    return str;
}

/**
 * This function builds the Having clause from the history object.
 *
 */

function query_having()
{
    var i;
    var and = "(";
    for (i = 0; i < history_array.length;i++) {
        if (history_array[i].get_type() == "Having") {
            if (history_array[i].get_obj().get_operator() != 'None') {
                and += history_array[i].get_obj().get_operator() + "(`" + history_array[i].get_column_name() + "`) " + history_array[i].get_obj().getrelation_operator();
                and += " " + history_array[i].get_obj().getquery() + ", ";
            } else {
                and += "`" + history_array[i].get_column_name() + "` " + history_array[i].get_obj().getrelation_operator() + " " + history_array[i].get_obj().getquery() + ", ";
            }
        }
    }
    if (and == "(") {
        and = "";
    } else {
        and = and.substr(0, and.length - 2) + ")";
    }
    return and;
}


/**
 * This function builds the orderby clause from the history object.
 *
 */

function query_orderby()
{
    var i;
    var str = "";
    for (i = 0; i < history_array.length;i++) {
        if (history_array[i].get_type() == "OrderBy") {
            str += "`" + history_array[i].get_column_name() + "` " +
                history_array[i].get_obj().get_order() + ", ";
        }
    }
    str = str.substr(0, str.length - 2);
    return str;
}


/**
 * This function builds the Where clause from the history object.
 *
 */

function query_where()
{
    var i;
    var and = "(";
    var or = "(";
    for (i = 0; i < history_array.length;i++) {
        if (history_array[i].get_type() == "Where") {
            if (history_array[i].get_and_or() === 0) {
                and += "( `" + history_array[i].get_column_name() + "` " + history_array[i].get_obj().getrelation_operator() + " " + history_array[i].get_obj().getquery() + ")";
                and += " AND ";
            } else {
                or += "( `" + history_array[i].get_column_name() + "` " + history_array[i].get_obj().getrelation_operator() + " " + history_array[i].get_obj().getquery() + ")";
                or += " OR ";
            }
        }
    }
    if (or != "(") {
        or = or.substring(0, (or.length - 4)) + ")";
    } else {
        or = "";
    }
    if (and != "(") {
        and = and.substring(0, (and.length - 5)) + ")";
    } else {
        and = "";
    }
    if (or !== "") {
        and = and + " OR " + or + " )";
    }
    return and;
}

function check_aggregate(id_this)
{
    var i;
    for (i = 0; i < history_array.length; i++) {
        var temp = '`' + history_array[i].get_tab() + '`.`' + history_array[i].get_column_name() + '`';
        if (temp == id_this && history_array[i].get_type() == "Aggregate") {
            return history_array[i].get_obj().get_operator() + '(' + id_this + ')';
        }
    }
    return "";
}

function check_rename(id_this)
{
    var i;
    for (i = 0; i < history_array.length; i++) {
        var temp = '`' + history_array[i].get_tab() + '`.`' + history_array[i].get_column_name() + '`';
        if (temp == id_this && history_array[i].get_type() == "Rename") {
            return " AS `" + history_array[i].get_obj().getrename_to() + "`";
        }
    }
    return "";
}

 /**
  * This function builds from clause of query
  * makes automatic joins.
  *
  *
  */
function query_from()
{
    var i;
    var tab_left = [];
    var tab_used = [];
    var t_tab_used = [];
    var t_tab_left = [];
    var temp;
    var query = "";
    var quer = "";
    var parts = [];
    var t_array = [];
    t_array = from_array;
    var K = 0;
    var k;
    var key;
    var key2;
    var key3;
    var parts1;

    // the constraints that have been used in the LEFT JOIN
    var constraints_added = [];

    for (i = 0; i < history_array.length; i++) {
        from_array.push(history_array[i].get_tab());
    }
    from_array = unique(from_array);
    tab_left = from_array;
    temp = tab_left.shift();
    quer = '`' + temp + '`';
    tab_used.push(temp);

    // if master table (key2) matches with tab used get all keys and check if tab_left matches
    // after this check if master table (key2) matches with tab left then check if any foreign matches with master .
    for (i = 0; i < 2; i++) {
        for (K in contr) {
            for (key in contr[K]) {// contr name
                for (key2 in contr[K][key]) {// table name
                    parts = key2.split(".");
                    if (found(tab_used, parts[1]) > 0) {
                        for (key3 in contr[K][key][key2]) {
                            parts1 = contr[K][key][key2][key3][0].split(".");
                            if (found(tab_left, parts1[1]) > 0) {
                                if (found(constraints_added, key) > 0) {
                                    query += ' AND ' + '`' + parts[1] + '`.`' + key3 + '` = ';
                                    query += '`' + parts1[1] + '`.`' + contr[K][key][key2][key3][1] + '` ';
                                } else {
                                    query += "\n" + 'LEFT JOIN ';
                                    query += '`' + parts[1] + '` ON ';
                                    query += '`' + parts1[1] + '`.`' + contr[K][key][key2][key3][1] + '` = ';
                                    query += '`' + parts[1] + '`.`' + key3 + '` ';

                                    constraints_added.push(key);
                                }
                                t_tab_left.push(parts[1]);
                            }
                        }
                    }
                }
            }
        }
        K = 0;
        t_tab_left = unique(t_tab_left);
        tab_used = add_array(t_tab_left, tab_used);
        tab_left = remove_array(t_tab_left, tab_left);
        t_tab_left = [];
        for (K in contr) {
            for (key in contr[K]) {
                for (key2 in contr[K][key]) {// table name
                    parts = key2.split(".");
                    if (found(tab_left, parts[1]) > 0) {
                        for (key3 in contr[K][key][key2]) {
                            parts1 = contr[K][key][key2][key3][0].split(".");
                            if (found(tab_used, parts1[1]) > 0) {
                                if (found(constraints_added, key) > 0) {
                                    query += ' AND ' + '`' + parts[1] + '`.`' + key3 + '` = ';
                                    query += '`' + parts1[1] + '`.`' + contr[K][key][key2][key3][1] + '` ';
                                } else {
                                    query += "\n" + 'LEFT JOIN ';
                                    query += '`' + parts[1] + '` ON ';
                                    query += '`' + parts1[1] + '`.`' + contr[K][key][key2][key3][1] + '` = ';
                                    query += '`' + parts[1] + '`.`' + key3 + '` ';

                                    constraints_added.push(key);
                                }
                                t_tab_left.push(parts[1]);
                            }
                        }
                    }
                }
            }
        }
        t_tab_left = unique(t_tab_left);
        tab_used = add_array(t_tab_left, tab_used);
        tab_left = remove_array(t_tab_left, tab_left);
        t_tab_left = [];
    }
    for (k in tab_left) {
        quer += " , `" + tab_left[k] + "`";
    }
    query = quer + query;
    from_array = t_array;
    return query;
}

/**
 * This function is the main function for query building.
 * uses history object details for this.
 *
 * @ uses query_where()
 * @ uses query_groupby()
 * @ uses query_having()
 * @ uses query_orderby()
 *
 * @param formtitle title for the form
 * @param fadin
 */

function build_query(formtitle, fadin)
{
    var q_select = "SELECT ";
    var temp;
    if (select_field.length > 0) {
        for (var i = 0; i < select_field.length; i++) {
            temp = check_aggregate(select_field[i]);
            if (temp !== "") {
                q_select += temp;
                temp = check_rename(select_field[i]);
                q_select += temp + ", ";
            } else {
                temp = check_rename(select_field[i]);
                q_select += select_field[i] + temp + ", ";
            }
        }
        q_select = q_select.substring(0, q_select.length - 2);
    } else {
        q_select += "* ";
    }

    q_select += "\nFROM " + query_from();

    var q_where = query_where();
    if (q_where !== "") {
        q_select += "\nWHERE " + q_where;
    }

    var q_groupby = query_groupby();
    if (q_groupby !== "") {
        q_select += "\nGROUP BY " + q_groupby;
    }

    var q_having = query_having();
    if (q_having !== "") {
        q_select += "\nHAVING " + q_having;
    }

    var q_orderby = query_orderby();
    if (q_orderby !== "") {
        q_select += "\nORDER BY " + q_orderby;
    }

    /**
     * @var button_options Object containing options
     *                     for jQueryUI dialog buttons
     */
    var button_options = {};
    button_options[PMA_messages.strClose] = function () {
        $(this).dialog("close");
    };
    button_options[PMA_messages.strSubmit] = function () {
        if (vqb_editor) {
            var $elm = $ajaxDialog.find('textarea');
            vqb_editor.save();
            $elm.val(vqb_editor.getValue());
        }
        $('#vqb_form').submit();
    };

    var $ajaxDialog = $('#box').dialog({
        appendTo: '#page_content',
        width: 500,
        buttons: button_options,
        modal: true,
        title: 'SELECT'
    });
    // Attach syntax highlighted editor to query dialog
    /**
     * @var $elm jQuery object containing the reference
     *           to the query textarea.
     */
    var $elm = $ajaxDialog.find('textarea');
    if (! vqb_editor) {
        vqb_editor = PMA_getSQLEditor($elm);
    }
    if (vqb_editor) {
        vqb_editor.setValue(q_select);
        vqb_editor.focus();
    } else {
        $elm.val(q_select);
        $elm.focus();
    }
}

AJAX.registerTeardown('pmd/history.js', function () {
    vqb_editor = null;
    history_array = [];
    select_field = [];
    $("#ok_edit_rename").unbind('click');
    $("#ok_edit_having").unbind('click');
    $("#ok_edit_Aggr").unbind('click');
    $("#ok_edit_where").unbind('click');
});

AJAX.registerOnload('pmd/history.js', function () {
    $("#ok_edit_rename").click(function() {
        edit('Rename');
    });
    $("#ok_edit_having").click(function() {
        edit('Having');
    });
    $("#ok_edit_Aggr").click(function() {
        edit('Aggregate');
    });
    $("#ok_edit_where").click(function() {
        edit('Where');
    });
    $('#ab').accordion({collapsible : true, active : 'none'});
});
;

