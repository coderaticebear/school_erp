/*
 * Shared page behaviour, loaded on every page by the layout.
 *
 * The Content-Security-Policy (SEC-10) blocks inline onclick/onchange attributes, so pages mark
 * elements with these data attributes instead:
 *   data-print                          button: open the browser's print dialog
 *   data-submit-form="form-id"          link or button: submit that form (logout is POST only)
 *   data-auto-submit                    select or input: submit its form as soon as it changes
 *     data-clears="field-name"            ...after emptying that field of the same form
 *     data-disables-when-chosen="name"    ...leaving that field out while this one has a value
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var printButton = event.target.closest('[data-print]');

        if (printButton) {
            event.preventDefault();
            window.print();

            return;
        }

        var submitter = event.target.closest('[data-submit-form]');
        var form = submitter && document.getElementById(submitter.getAttribute('data-submit-form'));

        if (form) {
            event.preventDefault();
            form.submit();
        }
    });

    document.addEventListener('change', function (event) {
        var field = event.target.closest('[data-auto-submit]');

        if (! field || ! field.form) {
            return;
        }

        var cleared = field.getAttribute('data-clears');
        var disabled = field.getAttribute('data-disables-when-chosen');

        if (cleared && field.form.elements[cleared]) {
            field.form.elements[cleared].value = '';
        }

        if (disabled && field.form.elements[disabled]) {
            field.form.elements[disabled].disabled = field.value !== '';
        }

        field.form.submit();
    });
})();
