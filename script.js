/**
 * Add JavaScript confirmation to the User Delete button
 */
jQuery(function () {
    jQuery('.plugin-virtualgroup .act form').on('submit', function (e) {
        if (!confirm(LANG.del_confirm)) {
            e.preventDefault();
        }
    });
});
