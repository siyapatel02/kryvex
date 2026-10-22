<div id="confirmModal" class="confirm-modal-overlay" aria-hidden="true">
    <div class="confirm-modal-box" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
        <div class="confirm-modal-icon" id="confirmModalIcon">!</div>
        <h3 id="confirmModalTitle">Are you sure?</h3>
        <p id="confirmModalMessage">Please confirm this action.</p>

        <div class="confirm-note-wrap" id="confirmNoteWrap">
            <label for="confirmNoteInput">Reason / Admin Note</label>
            <textarea id="confirmNoteInput" rows="3" placeholder="Enter reason..."></textarea>
            <small id="confirmNoteError"></small>
        </div>

        <div class="confirm-modal-actions">
            <button type="button" class="confirm-cancel-btn" id="confirmCancelBtn">Cancel</button>
            <button type="button" class="confirm-ok-btn" id="confirmOkBtn">Confirm</button>
        </div>
    </div>
</div>
