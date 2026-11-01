<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <div class="modal-header">
                <h2 class="modal-title fs-6 fw-bold" id="confirmTitle">Please confirm</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-confirm-message>Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-main">Confirm</button>
            </div>
        </form>
    </div>
</div>
