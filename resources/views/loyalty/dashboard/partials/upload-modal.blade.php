<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Upload Bill</h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body pt-3">

                <div class="loy-upload-drop"
                     id="loy-drop-zone"
                     style="position:relative;">

                    <input type="file"
                           id="loy-file-input"
                           accept=".jpg,.jpeg,.png,.pdf"
                           style="position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:10;">

                    <i class="fa-solid fa-file-arrow-up"
                       style="pointer-events:none;">
                    </i>

                    <div class="fw-bold mb-1" style="pointer-events:none;">
                        Drop your bill here or
                        <span style="color:var(--lp);text-decoration:underline;">
                            click to browse
                        </span>
                    </div>

                    <div class="text-muted fs-7" style="pointer-events:none;">
                        JPG, PNG or PDF · Max 5 MB
                    </div>
                </div>

                <div id="loy-file-name"
                     class="text-muted fs-7 mt-2 text-center"
                     style="min-height:18px;">
                </div>

                <div class="loy-progress" id="loy-progress">
                    <div class="loy-progress-bar"
                         id="loy-progress-bar"
                         style="width:0%">
                    </div>
                </div>

                <div id="loy-upload-msg"
                     class="mt-2 text-center fs-7">
                </div>

            </div>

            <div class="modal-footer border-0 pt-0">

                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="button"
                        class="btn btn-primary fw-bold"
                        id="loy-upload-btn"
                        disabled>

                    <i class="fa-solid fa-upload me-1"></i>
                    Upload
                </button>

            </div>

        </div>

    </div>
</div>