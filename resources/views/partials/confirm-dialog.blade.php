{{--
    One shared confirmation dialog. Any <form> with data-confirm-title asks before submitting:

    <form ... data-confirm-title="Delete Grade 5?" data-confirm-body="Its empty divisions go too."
          data-confirm-button="Delete Class" data-confirm-tone="danger">

    Text is read from attributes (never built into JavaScript), so names with quotes are safe.
    Focus starts on Cancel; tone "danger" makes the action red, anything else ink.
--}}
<div class="modal fade" id="confirm-dialog" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-body">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="confirm-dialog-title"></h2>
            </div>
            <div class="modal-body" id="confirm-dialog-body"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal" id="confirm-dialog-cancel">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-dialog-ok">Confirm</button>
            </div>
        </div>
    </div>
</div>

@once
    @push('js')
        <script>
            (function () {
                let pending = null;

                document.addEventListener('submit', function (event) {
                    const form = event.target;
                    if (! form.matches('form[data-confirm-title]') || form.dataset.confirmed === '1') {
                        return;
                    }
                    event.preventDefault();
                    pending = { form: form, submitter: event.submitter || null };

                    document.getElementById('confirm-dialog-title').textContent = form.dataset.confirmTitle;
                    const body = document.getElementById('confirm-dialog-body');
                    body.textContent = form.dataset.confirmBody || '';
                    body.hidden = ! form.dataset.confirmBody;

                    const ok = document.getElementById('confirm-dialog-ok');
                    ok.textContent = form.dataset.confirmButton || 'Confirm';
                    ok.className = 'btn ' + (form.dataset.confirmTone === 'danger' ? 'btn-danger' : 'btn-primary');

                    $('#confirm-dialog').modal('show');
                }, true);

                $('#confirm-dialog').on('shown.bs.modal', function () {
                    document.getElementById('confirm-dialog-cancel').focus();
                });

                document.getElementById('confirm-dialog-ok').addEventListener('click', function () {
                    if (! pending) { return; }
                    const { form, submitter } = pending;
                    pending = null;
                    form.dataset.confirmed = '1';
                    $('#confirm-dialog').modal('hide');
                    form.requestSubmit ? form.requestSubmit(submitter) : form.submit();
                });
            })();
        </script>
    @endpush
@endonce
