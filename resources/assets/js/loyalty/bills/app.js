import { loyBillsLoad } from './list';
import { loyUploadInit } from './upload';

window.LOY_LOADER = loyBillsLoad;

function initAll() {
    loyUploadInit();

    if (document.getElementById('loy-list-body')) {
        loyBillsLoad(1);
    }
}

document.addEventListener('DOMContentLoaded', initAll);
document.addEventListener('turbo:load', initAll);
document.addEventListener('turbolinks:load', initAll);