/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@hotwired/turbo/dist/turbo.es2017-esm.js":
/*!***************************************************************!*\
  !*** ./node_modules/@hotwired/turbo/dist/turbo.es2017-esm.js ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "PageRenderer": () => (/* binding */ PageRenderer),
/* harmony export */   "PageSnapshot": () => (/* binding */ PageSnapshot),
/* harmony export */   "clearCache": () => (/* binding */ clearCache),
/* harmony export */   "connectStreamSource": () => (/* binding */ connectStreamSource),
/* harmony export */   "disconnectStreamSource": () => (/* binding */ disconnectStreamSource),
/* harmony export */   "navigator": () => (/* binding */ navigator$1),
/* harmony export */   "registerAdapter": () => (/* binding */ registerAdapter),
/* harmony export */   "renderStreamMessage": () => (/* binding */ renderStreamMessage),
/* harmony export */   "session": () => (/* binding */ session),
/* harmony export */   "setConfirmMethod": () => (/* binding */ setConfirmMethod),
/* harmony export */   "setProgressBarDelay": () => (/* binding */ setProgressBarDelay),
/* harmony export */   "start": () => (/* binding */ start),
/* harmony export */   "visit": () => (/* binding */ visit)
/* harmony export */ });
/*
Turbo 7.1.0
Copyright © 2021 Basecamp, LLC
 */
(function () {
    if (window.Reflect === undefined || window.customElements === undefined ||
        window.customElements.polyfillWrapFlushCallback) {
        return;
    }
    const BuiltInHTMLElement = HTMLElement;
    const wrapperForTheName = {
        'HTMLElement': function HTMLElement() {
            return Reflect.construct(BuiltInHTMLElement, [], this.constructor);
        }
    };
    window.HTMLElement =
        wrapperForTheName['HTMLElement'];
    HTMLElement.prototype = BuiltInHTMLElement.prototype;
    HTMLElement.prototype.constructor = HTMLElement;
    Object.setPrototypeOf(HTMLElement, BuiltInHTMLElement);
})();

/**
 * The MIT License (MIT)
 * 
 * Copyright (c) 2019 Javan Makhmali
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

(function(prototype) {
  if (typeof prototype.requestSubmit == "function") return

  prototype.requestSubmit = function(submitter) {
    if (submitter) {
      validateSubmitter(submitter, this);
      submitter.click();
    } else {
      submitter = document.createElement("input");
      submitter.type = "submit";
      submitter.hidden = true;
      this.appendChild(submitter);
      submitter.click();
      this.removeChild(submitter);
    }
  };

  function validateSubmitter(submitter, form) {
    submitter instanceof HTMLElement || raise(TypeError, "parameter 1 is not of type 'HTMLElement'");
    submitter.type == "submit" || raise(TypeError, "The specified element is not a submit button");
    submitter.form == form || raise(DOMException, "The specified element is not owned by this form element", "NotFoundError");
  }

  function raise(errorConstructor, message, name) {
    throw new errorConstructor("Failed to execute 'requestSubmit' on 'HTMLFormElement': " + message + ".", name)
  }
})(HTMLFormElement.prototype);

const submittersByForm = new WeakMap;
function findSubmitterFromClickTarget(target) {
    const element = target instanceof Element ? target : target instanceof Node ? target.parentElement : null;
    const candidate = element ? element.closest("input, button") : null;
    return (candidate === null || candidate === void 0 ? void 0 : candidate.type) == "submit" ? candidate : null;
}
function clickCaptured(event) {
    const submitter = findSubmitterFromClickTarget(event.target);
    if (submitter && submitter.form) {
        submittersByForm.set(submitter.form, submitter);
    }
}
(function () {
    if ("submitter" in Event.prototype)
        return;
    let prototype;
    if ("SubmitEvent" in window && /Apple Computer/.test(navigator.vendor)) {
        prototype = window.SubmitEvent.prototype;
    }
    else if ("SubmitEvent" in window) {
        return;
    }
    else {
        prototype = window.Event.prototype;
    }
    addEventListener("click", clickCaptured, true);
    Object.defineProperty(prototype, "submitter", {
        get() {
            if (this.type == "submit" && this.target instanceof HTMLFormElement) {
                return submittersByForm.get(this.target);
            }
        }
    });
})();

var FrameLoadingStyle;
(function (FrameLoadingStyle) {
    FrameLoadingStyle["eager"] = "eager";
    FrameLoadingStyle["lazy"] = "lazy";
})(FrameLoadingStyle || (FrameLoadingStyle = {}));
class FrameElement extends HTMLElement {
    constructor() {
        super();
        this.loaded = Promise.resolve();
        this.delegate = new FrameElement.delegateConstructor(this);
    }
    static get observedAttributes() {
        return ["disabled", "loading", "src"];
    }
    connectedCallback() {
        this.delegate.connect();
    }
    disconnectedCallback() {
        this.delegate.disconnect();
    }
    reload() {
        const { src } = this;
        this.src = null;
        this.src = src;
    }
    attributeChangedCallback(name) {
        if (name == "loading") {
            this.delegate.loadingStyleChanged();
        }
        else if (name == "src") {
            this.delegate.sourceURLChanged();
        }
        else {
            this.delegate.disabledChanged();
        }
    }
    get src() {
        return this.getAttribute("src");
    }
    set src(value) {
        if (value) {
            this.setAttribute("src", value);
        }
        else {
            this.removeAttribute("src");
        }
    }
    get loading() {
        return frameLoadingStyleFromString(this.getAttribute("loading") || "");
    }
    set loading(value) {
        if (value) {
            this.setAttribute("loading", value);
        }
        else {
            this.removeAttribute("loading");
        }
    }
    get disabled() {
        return this.hasAttribute("disabled");
    }
    set disabled(value) {
        if (value) {
            this.setAttribute("disabled", "");
        }
        else {
            this.removeAttribute("disabled");
        }
    }
    get autoscroll() {
        return this.hasAttribute("autoscroll");
    }
    set autoscroll(value) {
        if (value) {
            this.setAttribute("autoscroll", "");
        }
        else {
            this.removeAttribute("autoscroll");
        }
    }
    get complete() {
        return !this.delegate.isLoading;
    }
    get isActive() {
        return this.ownerDocument === document && !this.isPreview;
    }
    get isPreview() {
        var _a, _b;
        return (_b = (_a = this.ownerDocument) === null || _a === void 0 ? void 0 : _a.documentElement) === null || _b === void 0 ? void 0 : _b.hasAttribute("data-turbo-preview");
    }
}
function frameLoadingStyleFromString(style) {
    switch (style.toLowerCase()) {
        case "lazy": return FrameLoadingStyle.lazy;
        default: return FrameLoadingStyle.eager;
    }
}

function expandURL(locatable) {
    return new URL(locatable.toString(), document.baseURI);
}
function getAnchor(url) {
    let anchorMatch;
    if (url.hash) {
        return url.hash.slice(1);
    }
    else if (anchorMatch = url.href.match(/#(.*)$/)) {
        return anchorMatch[1];
    }
}
function getAction(form, submitter) {
    const action = (submitter === null || submitter === void 0 ? void 0 : submitter.getAttribute("formaction")) || form.getAttribute("action") || form.action;
    return expandURL(action);
}
function getExtension(url) {
    return (getLastPathComponent(url).match(/\.[^.]*$/) || [])[0] || "";
}
function isHTML(url) {
    return !!getExtension(url).match(/^(?:|\.(?:htm|html|xhtml))$/);
}
function isPrefixedBy(baseURL, url) {
    const prefix = getPrefix(url);
    return baseURL.href === expandURL(prefix).href || baseURL.href.startsWith(prefix);
}
function locationIsVisitable(location, rootLocation) {
    return isPrefixedBy(location, rootLocation) && isHTML(location);
}
function getRequestURL(url) {
    const anchor = getAnchor(url);
    return anchor != null
        ? url.href.slice(0, -(anchor.length + 1))
        : url.href;
}
function toCacheKey(url) {
    return getRequestURL(url);
}
function urlsAreEqual(left, right) {
    return expandURL(left).href == expandURL(right).href;
}
function getPathComponents(url) {
    return url.pathname.split("/").slice(1);
}
function getLastPathComponent(url) {
    return getPathComponents(url).slice(-1)[0];
}
function getPrefix(url) {
    return addTrailingSlash(url.origin + url.pathname);
}
function addTrailingSlash(value) {
    return value.endsWith("/") ? value : value + "/";
}

class FetchResponse {
    constructor(response) {
        this.response = response;
    }
    get succeeded() {
        return this.response.ok;
    }
    get failed() {
        return !this.succeeded;
    }
    get clientError() {
        return this.statusCode >= 400 && this.statusCode <= 499;
    }
    get serverError() {
        return this.statusCode >= 500 && this.statusCode <= 599;
    }
    get redirected() {
        return this.response.redirected;
    }
    get location() {
        return expandURL(this.response.url);
    }
    get isHTML() {
        return this.contentType && this.contentType.match(/^(?:text\/([^\s;,]+\b)?html|application\/xhtml\+xml)\b/);
    }
    get statusCode() {
        return this.response.status;
    }
    get contentType() {
        return this.header("Content-Type");
    }
    get responseText() {
        return this.response.clone().text();
    }
    get responseHTML() {
        if (this.isHTML) {
            return this.response.clone().text();
        }
        else {
            return Promise.resolve(undefined);
        }
    }
    header(name) {
        return this.response.headers.get(name);
    }
}

function dispatch(eventName, { target, cancelable, detail } = {}) {
    const event = new CustomEvent(eventName, { cancelable, bubbles: true, detail });
    if (target && target.isConnected) {
        target.dispatchEvent(event);
    }
    else {
        document.documentElement.dispatchEvent(event);
    }
    return event;
}
function nextAnimationFrame() {
    return new Promise(resolve => requestAnimationFrame(() => resolve()));
}
function nextEventLoopTick() {
    return new Promise(resolve => setTimeout(() => resolve(), 0));
}
function nextMicrotask() {
    return Promise.resolve();
}
function parseHTMLDocument(html = "") {
    return new DOMParser().parseFromString(html, "text/html");
}
function unindent(strings, ...values) {
    const lines = interpolate(strings, values).replace(/^\n/, "").split("\n");
    const match = lines[0].match(/^\s+/);
    const indent = match ? match[0].length : 0;
    return lines.map(line => line.slice(indent)).join("\n");
}
function interpolate(strings, values) {
    return strings.reduce((result, string, i) => {
        const value = values[i] == undefined ? "" : values[i];
        return result + string + value;
    }, "");
}
function uuid() {
    return Array.apply(null, { length: 36 }).map((_, i) => {
        if (i == 8 || i == 13 || i == 18 || i == 23) {
            return "-";
        }
        else if (i == 14) {
            return "4";
        }
        else if (i == 19) {
            return (Math.floor(Math.random() * 4) + 8).toString(16);
        }
        else {
            return Math.floor(Math.random() * 15).toString(16);
        }
    }).join("");
}
function getAttribute(attributeName, ...elements) {
    for (const value of elements.map(element => element === null || element === void 0 ? void 0 : element.getAttribute(attributeName))) {
        if (typeof value == "string")
            return value;
    }
    return null;
}
function markAsBusy(...elements) {
    for (const element of elements) {
        if (element.localName == "turbo-frame") {
            element.setAttribute("busy", "");
        }
        element.setAttribute("aria-busy", "true");
    }
}
function clearBusyState(...elements) {
    for (const element of elements) {
        if (element.localName == "turbo-frame") {
            element.removeAttribute("busy");
        }
        element.removeAttribute("aria-busy");
    }
}

var FetchMethod;
(function (FetchMethod) {
    FetchMethod[FetchMethod["get"] = 0] = "get";
    FetchMethod[FetchMethod["post"] = 1] = "post";
    FetchMethod[FetchMethod["put"] = 2] = "put";
    FetchMethod[FetchMethod["patch"] = 3] = "patch";
    FetchMethod[FetchMethod["delete"] = 4] = "delete";
})(FetchMethod || (FetchMethod = {}));
function fetchMethodFromString(method) {
    switch (method.toLowerCase()) {
        case "get": return FetchMethod.get;
        case "post": return FetchMethod.post;
        case "put": return FetchMethod.put;
        case "patch": return FetchMethod.patch;
        case "delete": return FetchMethod.delete;
    }
}
class FetchRequest {
    constructor(delegate, method, location, body = new URLSearchParams, target = null) {
        this.abortController = new AbortController;
        this.resolveRequestPromise = (value) => { };
        this.delegate = delegate;
        this.method = method;
        this.headers = this.defaultHeaders;
        this.body = body;
        this.url = location;
        this.target = target;
    }
    get location() {
        return this.url;
    }
    get params() {
        return this.url.searchParams;
    }
    get entries() {
        return this.body ? Array.from(this.body.entries()) : [];
    }
    cancel() {
        this.abortController.abort();
    }
    async perform() {
        var _a, _b;
        const { fetchOptions } = this;
        (_b = (_a = this.delegate).prepareHeadersForRequest) === null || _b === void 0 ? void 0 : _b.call(_a, this.headers, this);
        await this.allowRequestToBeIntercepted(fetchOptions);
        try {
            this.delegate.requestStarted(this);
            const response = await fetch(this.url.href, fetchOptions);
            return await this.receive(response);
        }
        catch (error) {
            if (error.name !== 'AbortError') {
                this.delegate.requestErrored(this, error);
                throw error;
            }
        }
        finally {
            this.delegate.requestFinished(this);
        }
    }
    async receive(response) {
        const fetchResponse = new FetchResponse(response);
        const event = dispatch("turbo:before-fetch-response", { cancelable: true, detail: { fetchResponse }, target: this.target });
        if (event.defaultPrevented) {
            this.delegate.requestPreventedHandlingResponse(this, fetchResponse);
        }
        else if (fetchResponse.succeeded) {
            this.delegate.requestSucceededWithResponse(this, fetchResponse);
        }
        else {
            this.delegate.requestFailedWithResponse(this, fetchResponse);
        }
        return fetchResponse;
    }
    get fetchOptions() {
        var _a;
        return {
            method: FetchMethod[this.method].toUpperCase(),
            credentials: "same-origin",
            headers: this.headers,
            redirect: "follow",
            body: this.isIdempotent ? null : this.body,
            signal: this.abortSignal,
            referrer: (_a = this.delegate.referrer) === null || _a === void 0 ? void 0 : _a.href
        };
    }
    get defaultHeaders() {
        return {
            "Accept": "text/html, application/xhtml+xml"
        };
    }
    get isIdempotent() {
        return this.method == FetchMethod.get;
    }
    get abortSignal() {
        return this.abortController.signal;
    }
    async allowRequestToBeIntercepted(fetchOptions) {
        const requestInterception = new Promise(resolve => this.resolveRequestPromise = resolve);
        const event = dispatch("turbo:before-fetch-request", {
            cancelable: true,
            detail: {
                fetchOptions,
                url: this.url,
                resume: this.resolveRequestPromise
            },
            target: this.target
        });
        if (event.defaultPrevented)
            await requestInterception;
    }
}

class AppearanceObserver {
    constructor(delegate, element) {
        this.started = false;
        this.intersect = entries => {
            const lastEntry = entries.slice(-1)[0];
            if (lastEntry === null || lastEntry === void 0 ? void 0 : lastEntry.isIntersecting) {
                this.delegate.elementAppearedInViewport(this.element);
            }
        };
        this.delegate = delegate;
        this.element = element;
        this.intersectionObserver = new IntersectionObserver(this.intersect);
    }
    start() {
        if (!this.started) {
            this.started = true;
            this.intersectionObserver.observe(this.element);
        }
    }
    stop() {
        if (this.started) {
            this.started = false;
            this.intersectionObserver.unobserve(this.element);
        }
    }
}

class StreamMessage {
    constructor(html) {
        this.templateElement = document.createElement("template");
        this.templateElement.innerHTML = html;
    }
    static wrap(message) {
        if (typeof message == "string") {
            return new this(message);
        }
        else {
            return message;
        }
    }
    get fragment() {
        const fragment = document.createDocumentFragment();
        for (const element of this.foreignElements) {
            fragment.appendChild(document.importNode(element, true));
        }
        return fragment;
    }
    get foreignElements() {
        return this.templateChildren.reduce((streamElements, child) => {
            if (child.tagName.toLowerCase() == "turbo-stream") {
                return [...streamElements, child];
            }
            else {
                return streamElements;
            }
        }, []);
    }
    get templateChildren() {
        return Array.from(this.templateElement.content.children);
    }
}
StreamMessage.contentType = "text/vnd.turbo-stream.html";

var FormSubmissionState;
(function (FormSubmissionState) {
    FormSubmissionState[FormSubmissionState["initialized"] = 0] = "initialized";
    FormSubmissionState[FormSubmissionState["requesting"] = 1] = "requesting";
    FormSubmissionState[FormSubmissionState["waiting"] = 2] = "waiting";
    FormSubmissionState[FormSubmissionState["receiving"] = 3] = "receiving";
    FormSubmissionState[FormSubmissionState["stopping"] = 4] = "stopping";
    FormSubmissionState[FormSubmissionState["stopped"] = 5] = "stopped";
})(FormSubmissionState || (FormSubmissionState = {}));
var FormEnctype;
(function (FormEnctype) {
    FormEnctype["urlEncoded"] = "application/x-www-form-urlencoded";
    FormEnctype["multipart"] = "multipart/form-data";
    FormEnctype["plain"] = "text/plain";
})(FormEnctype || (FormEnctype = {}));
function formEnctypeFromString(encoding) {
    switch (encoding.toLowerCase()) {
        case FormEnctype.multipart: return FormEnctype.multipart;
        case FormEnctype.plain: return FormEnctype.plain;
        default: return FormEnctype.urlEncoded;
    }
}
class FormSubmission {
    constructor(delegate, formElement, submitter, mustRedirect = false) {
        this.state = FormSubmissionState.initialized;
        this.delegate = delegate;
        this.formElement = formElement;
        this.submitter = submitter;
        this.formData = buildFormData(formElement, submitter);
        this.location = expandURL(this.action);
        if (this.method == FetchMethod.get) {
            mergeFormDataEntries(this.location, [...this.body.entries()]);
        }
        this.fetchRequest = new FetchRequest(this, this.method, this.location, this.body, this.formElement);
        this.mustRedirect = mustRedirect;
    }
    static confirmMethod(message, element) {
        return confirm(message);
    }
    get method() {
        var _a;
        const method = ((_a = this.submitter) === null || _a === void 0 ? void 0 : _a.getAttribute("formmethod")) || this.formElement.getAttribute("method") || "";
        return fetchMethodFromString(method.toLowerCase()) || FetchMethod.get;
    }
    get action() {
        var _a;
        const formElementAction = typeof this.formElement.action === 'string' ? this.formElement.action : null;
        return ((_a = this.submitter) === null || _a === void 0 ? void 0 : _a.getAttribute("formaction")) || this.formElement.getAttribute("action") || formElementAction || "";
    }
    get body() {
        if (this.enctype == FormEnctype.urlEncoded || this.method == FetchMethod.get) {
            return new URLSearchParams(this.stringFormData);
        }
        else {
            return this.formData;
        }
    }
    get enctype() {
        var _a;
        return formEnctypeFromString(((_a = this.submitter) === null || _a === void 0 ? void 0 : _a.getAttribute("formenctype")) || this.formElement.enctype);
    }
    get isIdempotent() {
        return this.fetchRequest.isIdempotent;
    }
    get stringFormData() {
        return [...this.formData].reduce((entries, [name, value]) => {
            return entries.concat(typeof value == "string" ? [[name, value]] : []);
        }, []);
    }
    get confirmationMessage() {
        return this.formElement.getAttribute("data-turbo-confirm");
    }
    get needsConfirmation() {
        return this.confirmationMessage !== null;
    }
    async start() {
        const { initialized, requesting } = FormSubmissionState;
        if (this.needsConfirmation) {
            const answer = FormSubmission.confirmMethod(this.confirmationMessage, this.formElement);
            if (!answer) {
                return;
            }
        }
        if (this.state == initialized) {
            this.state = requesting;
            return this.fetchRequest.perform();
        }
    }
    stop() {
        const { stopping, stopped } = FormSubmissionState;
        if (this.state != stopping && this.state != stopped) {
            this.state = stopping;
            this.fetchRequest.cancel();
            return true;
        }
    }
    prepareHeadersForRequest(headers, request) {
        if (!request.isIdempotent) {
            const token = getCookieValue(getMetaContent("csrf-param")) || getMetaContent("csrf-token");
            if (token) {
                headers["X-CSRF-Token"] = token;
            }
            headers["Accept"] = [StreamMessage.contentType, headers["Accept"]].join(", ");
        }
    }
    requestStarted(request) {
        var _a;
        this.state = FormSubmissionState.waiting;
        (_a = this.submitter) === null || _a === void 0 ? void 0 : _a.setAttribute("disabled", "");
        dispatch("turbo:submit-start", { target: this.formElement, detail: { formSubmission: this } });
        this.delegate.formSubmissionStarted(this);
    }
    requestPreventedHandlingResponse(request, response) {
        this.result = { success: response.succeeded, fetchResponse: response };
    }
    requestSucceededWithResponse(request, response) {
        if (response.clientError || response.serverError) {
            this.delegate.formSubmissionFailedWithResponse(this, response);
        }
        else if (this.requestMustRedirect(request) && responseSucceededWithoutRedirect(response)) {
            const error = new Error("Form responses must redirect to another location");
            this.delegate.formSubmissionErrored(this, error);
        }
        else {
            this.state = FormSubmissionState.receiving;
            this.result = { success: true, fetchResponse: response };
            this.delegate.formSubmissionSucceededWithResponse(this, response);
        }
    }
    requestFailedWithResponse(request, response) {
        this.result = { success: false, fetchResponse: response };
        this.delegate.formSubmissionFailedWithResponse(this, response);
    }
    requestErrored(request, error) {
        this.result = { success: false, error };
        this.delegate.formSubmissionErrored(this, error);
    }
    requestFinished(request) {
        var _a;
        this.state = FormSubmissionState.stopped;
        (_a = this.submitter) === null || _a === void 0 ? void 0 : _a.removeAttribute("disabled");
        dispatch("turbo:submit-end", { target: this.formElement, detail: Object.assign({ formSubmission: this }, this.result) });
        this.delegate.formSubmissionFinished(this);
    }
    requestMustRedirect(request) {
        return !request.isIdempotent && this.mustRedirect;
    }
}
function buildFormData(formElement, submitter) {
    const formData = new FormData(formElement);
    const name = submitter === null || submitter === void 0 ? void 0 : submitter.getAttribute("name");
    const value = submitter === null || submitter === void 0 ? void 0 : submitter.getAttribute("value");
    if (name && value != null && formData.get(name) != value) {
        formData.append(name, value);
    }
    return formData;
}
function getCookieValue(cookieName) {
    if (cookieName != null) {
        const cookies = document.cookie ? document.cookie.split("; ") : [];
        const cookie = cookies.find((cookie) => cookie.startsWith(cookieName));
        if (cookie) {
            const value = cookie.split("=").slice(1).join("=");
            return value ? decodeURIComponent(value) : undefined;
        }
    }
}
function getMetaContent(name) {
    const element = document.querySelector(`meta[name="${name}"]`);
    return element && element.content;
}
function responseSucceededWithoutRedirect(response) {
    return response.statusCode == 200 && !response.redirected;
}
function mergeFormDataEntries(url, entries) {
    const searchParams = new URLSearchParams;
    for (const [name, value] of entries) {
        if (value instanceof File)
            continue;
        searchParams.append(name, value);
    }
    url.search = searchParams.toString();
    return url;
}

class Snapshot {
    constructor(element) {
        this.element = element;
    }
    get children() {
        return [...this.element.children];
    }
    hasAnchor(anchor) {
        return this.getElementForAnchor(anchor) != null;
    }
    getElementForAnchor(anchor) {
        return anchor ? this.element.querySelector(`[id='${anchor}'], a[name='${anchor}']`) : null;
    }
    get isConnected() {
        return this.element.isConnected;
    }
    get firstAutofocusableElement() {
        return this.element.querySelector("[autofocus]");
    }
    get permanentElements() {
        return [...this.element.querySelectorAll("[id][data-turbo-permanent]")];
    }
    getPermanentElementById(id) {
        return this.element.querySelector(`#${id}[data-turbo-permanent]`);
    }
    getPermanentElementMapForSnapshot(snapshot) {
        const permanentElementMap = {};
        for (const currentPermanentElement of this.permanentElements) {
            const { id } = currentPermanentElement;
            const newPermanentElement = snapshot.getPermanentElementById(id);
            if (newPermanentElement) {
                permanentElementMap[id] = [currentPermanentElement, newPermanentElement];
            }
        }
        return permanentElementMap;
    }
}

class FormInterceptor {
    constructor(delegate, element) {
        this.submitBubbled = ((event) => {
            const form = event.target;
            if (!event.defaultPrevented && form instanceof HTMLFormElement && form.closest("turbo-frame, html") == this.element) {
                const submitter = event.submitter || undefined;
                const method = (submitter === null || submitter === void 0 ? void 0 : submitter.getAttribute("formmethod")) || form.method;
                if (method != "dialog" && this.delegate.shouldInterceptFormSubmission(form, submitter)) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    this.delegate.formSubmissionIntercepted(form, submitter);
                }
            }
        });
        this.delegate = delegate;
        this.element = element;
    }
    start() {
        this.element.addEventListener("submit", this.submitBubbled);
    }
    stop() {
        this.element.removeEventListener("submit", this.submitBubbled);
    }
}

class View {
    constructor(delegate, element) {
        this.resolveRenderPromise = (value) => { };
        this.resolveInterceptionPromise = (value) => { };
        this.delegate = delegate;
        this.element = element;
    }
    scrollToAnchor(anchor) {
        const element = this.snapshot.getElementForAnchor(anchor);
        if (element) {
            this.scrollToElement(element);
            this.focusElement(element);
        }
        else {
            this.scrollToPosition({ x: 0, y: 0 });
        }
    }
    scrollToAnchorFromLocation(location) {
        this.scrollToAnchor(getAnchor(location));
    }
    scrollToElement(element) {
        element.scrollIntoView();
    }
    focusElement(element) {
        if (element instanceof HTMLElement) {
            if (element.hasAttribute("tabindex")) {
                element.focus();
            }
            else {
                element.setAttribute("tabindex", "-1");
                element.focus();
                element.removeAttribute("tabindex");
            }
        }
    }
    scrollToPosition({ x, y }) {
        this.scrollRoot.scrollTo(x, y);
    }
    scrollToTop() {
        this.scrollToPosition({ x: 0, y: 0 });
    }
    get scrollRoot() {
        return window;
    }
    async render(renderer) {
        const { isPreview, shouldRender, newSnapshot: snapshot } = renderer;
        if (shouldRender) {
            try {
                this.renderPromise = new Promise(resolve => this.resolveRenderPromise = resolve);
                this.renderer = renderer;
                this.prepareToRenderSnapshot(renderer);
                const renderInterception = new Promise(resolve => this.resolveInterceptionPromise = resolve);
                const immediateRender = this.delegate.allowsImmediateRender(snapshot, this.resolveInterceptionPromise);
                if (!immediateRender)
                    await renderInterception;
                await this.renderSnapshot(renderer);
                this.delegate.viewRenderedSnapshot(snapshot, isPreview);
                this.finishRenderingSnapshot(renderer);
            }
            finally {
                delete this.renderer;
                this.resolveRenderPromise(undefined);
                delete this.renderPromise;
            }
        }
        else {
            this.invalidate();
        }
    }
    invalidate() {
        this.delegate.viewInvalidated();
    }
    prepareToRenderSnapshot(renderer) {
        this.markAsPreview(renderer.isPreview);
        renderer.prepareToRender();
    }
    markAsPreview(isPreview) {
        if (isPreview) {
            this.element.setAttribute("data-turbo-preview", "");
        }
        else {
            this.element.removeAttribute("data-turbo-preview");
        }
    }
    async renderSnapshot(renderer) {
        await renderer.render();
    }
    finishRenderingSnapshot(renderer) {
        renderer.finishRendering();
    }
}

class FrameView extends View {
    invalidate() {
        this.element.innerHTML = "";
    }
    get snapshot() {
        return new Snapshot(this.element);
    }
}

class LinkInterceptor {
    constructor(delegate, element) {
        this.clickBubbled = (event) => {
            if (this.respondsToEventTarget(event.target)) {
                this.clickEvent = event;
            }
            else {
                delete this.clickEvent;
            }
        };
        this.linkClicked = ((event) => {
            if (this.clickEvent && this.respondsToEventTarget(event.target) && event.target instanceof Element) {
                if (this.delegate.shouldInterceptLinkClick(event.target, event.detail.url)) {
                    this.clickEvent.preventDefault();
                    event.preventDefault();
                    this.delegate.linkClickIntercepted(event.target, event.detail.url);
                }
            }
            delete this.clickEvent;
        });
        this.willVisit = () => {
            delete this.clickEvent;
        };
        this.delegate = delegate;
        this.element = element;
    }
    start() {
        this.element.addEventListener("click", this.clickBubbled);
        document.addEventListener("turbo:click", this.linkClicked);
        document.addEventListener("turbo:before-visit", this.willVisit);
    }
    stop() {
        this.element.removeEventListener("click", this.clickBubbled);
        document.removeEventListener("turbo:click", this.linkClicked);
        document.removeEventListener("turbo:before-visit", this.willVisit);
    }
    respondsToEventTarget(target) {
        const element = target instanceof Element
            ? target
            : target instanceof Node
                ? target.parentElement
                : null;
        return element && element.closest("turbo-frame, html") == this.element;
    }
}

class Bardo {
    constructor(permanentElementMap) {
        this.permanentElementMap = permanentElementMap;
    }
    static preservingPermanentElements(permanentElementMap, callback) {
        const bardo = new this(permanentElementMap);
        bardo.enter();
        callback();
        bardo.leave();
    }
    enter() {
        for (const id in this.permanentElementMap) {
            const [, newPermanentElement] = this.permanentElementMap[id];
            this.replaceNewPermanentElementWithPlaceholder(newPermanentElement);
        }
    }
    leave() {
        for (const id in this.permanentElementMap) {
            const [currentPermanentElement] = this.permanentElementMap[id];
            this.replaceCurrentPermanentElementWithClone(currentPermanentElement);
            this.replacePlaceholderWithPermanentElement(currentPermanentElement);
        }
    }
    replaceNewPermanentElementWithPlaceholder(permanentElement) {
        const placeholder = createPlaceholderForPermanentElement(permanentElement);
        permanentElement.replaceWith(placeholder);
    }
    replaceCurrentPermanentElementWithClone(permanentElement) {
        const clone = permanentElement.cloneNode(true);
        permanentElement.replaceWith(clone);
    }
    replacePlaceholderWithPermanentElement(permanentElement) {
        const placeholder = this.getPlaceholderById(permanentElement.id);
        placeholder === null || placeholder === void 0 ? void 0 : placeholder.replaceWith(permanentElement);
    }
    getPlaceholderById(id) {
        return this.placeholders.find(element => element.content == id);
    }
    get placeholders() {
        return [...document.querySelectorAll("meta[name=turbo-permanent-placeholder][content]")];
    }
}
function createPlaceholderForPermanentElement(permanentElement) {
    const element = document.createElement("meta");
    element.setAttribute("name", "turbo-permanent-placeholder");
    element.setAttribute("content", permanentElement.id);
    return element;
}

class Renderer {
    constructor(currentSnapshot, newSnapshot, isPreview, willRender = true) {
        this.currentSnapshot = currentSnapshot;
        this.newSnapshot = newSnapshot;
        this.isPreview = isPreview;
        this.willRender = willRender;
        this.promise = new Promise((resolve, reject) => this.resolvingFunctions = { resolve, reject });
    }
    get shouldRender() {
        return true;
    }
    prepareToRender() {
        return;
    }
    finishRendering() {
        if (this.resolvingFunctions) {
            this.resolvingFunctions.resolve();
            delete this.resolvingFunctions;
        }
    }
    createScriptElement(element) {
        if (element.getAttribute("data-turbo-eval") == "false") {
            return element;
        }
        else {
            const createdScriptElement = document.createElement("script");
            if (this.cspNonce) {
                createdScriptElement.nonce = this.cspNonce;
            }
            createdScriptElement.textContent = element.textContent;
            createdScriptElement.async = false;
            copyElementAttributes(createdScriptElement, element);
            return createdScriptElement;
        }
    }
    preservingPermanentElements(callback) {
        Bardo.preservingPermanentElements(this.permanentElementMap, callback);
    }
    focusFirstAutofocusableElement() {
        const element = this.connectedSnapshot.firstAutofocusableElement;
        if (elementIsFocusable(element)) {
            element.focus();
        }
    }
    get connectedSnapshot() {
        return this.newSnapshot.isConnected ? this.newSnapshot : this.currentSnapshot;
    }
    get currentElement() {
        return this.currentSnapshot.element;
    }
    get newElement() {
        return this.newSnapshot.element;
    }
    get permanentElementMap() {
        return this.currentSnapshot.getPermanentElementMapForSnapshot(this.newSnapshot);
    }
    get cspNonce() {
        var _a;
        return (_a = document.head.querySelector('meta[name="csp-nonce"]')) === null || _a === void 0 ? void 0 : _a.getAttribute("content");
    }
}
function copyElementAttributes(destinationElement, sourceElement) {
    for (const { name, value } of [...sourceElement.attributes]) {
        destinationElement.setAttribute(name, value);
    }
}
function elementIsFocusable(element) {
    return element && typeof element.focus == "function";
}

class FrameRenderer extends Renderer {
    get shouldRender() {
        return true;
    }
    async render() {
        await nextAnimationFrame();
        this.preservingPermanentElements(() => {
            this.loadFrameElement();
        });
        this.scrollFrameIntoView();
        await nextAnimationFrame();
        this.focusFirstAutofocusableElement();
        await nextAnimationFrame();
        this.activateScriptElements();
    }
    loadFrameElement() {
        var _a;
        const destinationRange = document.createRange();
        destinationRange.selectNodeContents(this.currentElement);
        destinationRange.deleteContents();
        const frameElement = this.newElement;
        const sourceRange = (_a = frameElement.ownerDocument) === null || _a === void 0 ? void 0 : _a.createRange();
        if (sourceRange) {
            sourceRange.selectNodeContents(frameElement);
            this.currentElement.appendChild(sourceRange.extractContents());
        }
    }
    scrollFrameIntoView() {
        if (this.currentElement.autoscroll || this.newElement.autoscroll) {
            const element = this.currentElement.firstElementChild;
            const block = readScrollLogicalPosition(this.currentElement.getAttribute("data-autoscroll-block"), "end");
            if (element) {
                element.scrollIntoView({ block });
                return true;
            }
        }
        return false;
    }
    activateScriptElements() {
        for (const inertScriptElement of this.newScriptElements) {
            const activatedScriptElement = this.createScriptElement(inertScriptElement);
            inertScriptElement.replaceWith(activatedScriptElement);
        }
    }
    get newScriptElements() {
        return this.currentElement.querySelectorAll("script");
    }
}
function readScrollLogicalPosition(value, defaultValue) {
    if (value == "end" || value == "start" || value == "center" || value == "nearest") {
        return value;
    }
    else {
        return defaultValue;
    }
}

class ProgressBar {
    constructor() {
        this.hiding = false;
        this.value = 0;
        this.visible = false;
        this.trickle = () => {
            this.setValue(this.value + Math.random() / 100);
        };
        this.stylesheetElement = this.createStylesheetElement();
        this.progressElement = this.createProgressElement();
        this.installStylesheetElement();
        this.setValue(0);
    }
    static get defaultCSS() {
        return unindent `
      .turbo-progress-bar {
        position: fixed;
        display: block;
        top: 0;
        left: 0;
        height: 3px;
        background: #0076ff;
        z-index: 9999;
        transition:
          width ${ProgressBar.animationDuration}ms ease-out,
          opacity ${ProgressBar.animationDuration / 2}ms ${ProgressBar.animationDuration / 2}ms ease-in;
        transform: translate3d(0, 0, 0);
      }
    `;
    }
    show() {
        if (!this.visible) {
            this.visible = true;
            this.installProgressElement();
            this.startTrickling();
        }
    }
    hide() {
        if (this.visible && !this.hiding) {
            this.hiding = true;
            this.fadeProgressElement(() => {
                this.uninstallProgressElement();
                this.stopTrickling();
                this.visible = false;
                this.hiding = false;
            });
        }
    }
    setValue(value) {
        this.value = value;
        this.refresh();
    }
    installStylesheetElement() {
        document.head.insertBefore(this.stylesheetElement, document.head.firstChild);
    }
    installProgressElement() {
        this.progressElement.style.width = "0";
        this.progressElement.style.opacity = "1";
        document.documentElement.insertBefore(this.progressElement, document.body);
        this.refresh();
    }
    fadeProgressElement(callback) {
        this.progressElement.style.opacity = "0";
        setTimeout(callback, ProgressBar.animationDuration * 1.5);
    }
    uninstallProgressElement() {
        if (this.progressElement.parentNode) {
            document.documentElement.removeChild(this.progressElement);
        }
    }
    startTrickling() {
        if (!this.trickleInterval) {
            this.trickleInterval = window.setInterval(this.trickle, ProgressBar.animationDuration);
        }
    }
    stopTrickling() {
        window.clearInterval(this.trickleInterval);
        delete this.trickleInterval;
    }
    refresh() {
        requestAnimationFrame(() => {
            this.progressElement.style.width = `${10 + (this.value * 90)}%`;
        });
    }
    createStylesheetElement() {
        const element = document.createElement("style");
        element.type = "text/css";
        element.textContent = ProgressBar.defaultCSS;
        return element;
    }
    createProgressElement() {
        const element = document.createElement("div");
        element.className = "turbo-progress-bar";
        return element;
    }
}
ProgressBar.animationDuration = 300;

class HeadSnapshot extends Snapshot {
    constructor() {
        super(...arguments);
        this.detailsByOuterHTML = this.children
            .filter((element) => !elementIsNoscript(element))
            .map((element) => elementWithoutNonce(element))
            .reduce((result, element) => {
            const { outerHTML } = element;
            const details = outerHTML in result
                ? result[outerHTML]
                : {
                    type: elementType(element),
                    tracked: elementIsTracked(element),
                    elements: []
                };
            return Object.assign(Object.assign({}, result), { [outerHTML]: Object.assign(Object.assign({}, details), { elements: [...details.elements, element] }) });
        }, {});
    }
    get trackedElementSignature() {
        return Object.keys(this.detailsByOuterHTML)
            .filter(outerHTML => this.detailsByOuterHTML[outerHTML].tracked)
            .join("");
    }
    getScriptElementsNotInSnapshot(snapshot) {
        return this.getElementsMatchingTypeNotInSnapshot("script", snapshot);
    }
    getStylesheetElementsNotInSnapshot(snapshot) {
        return this.getElementsMatchingTypeNotInSnapshot("stylesheet", snapshot);
    }
    getElementsMatchingTypeNotInSnapshot(matchedType, snapshot) {
        return Object.keys(this.detailsByOuterHTML)
            .filter(outerHTML => !(outerHTML in snapshot.detailsByOuterHTML))
            .map(outerHTML => this.detailsByOuterHTML[outerHTML])
            .filter(({ type }) => type == matchedType)
            .map(({ elements: [element] }) => element);
    }
    get provisionalElements() {
        return Object.keys(this.detailsByOuterHTML).reduce((result, outerHTML) => {
            const { type, tracked, elements } = this.detailsByOuterHTML[outerHTML];
            if (type == null && !tracked) {
                return [...result, ...elements];
            }
            else if (elements.length > 1) {
                return [...result, ...elements.slice(1)];
            }
            else {
                return result;
            }
        }, []);
    }
    getMetaValue(name) {
        const element = this.findMetaElementByName(name);
        return element
            ? element.getAttribute("content")
            : null;
    }
    findMetaElementByName(name) {
        return Object.keys(this.detailsByOuterHTML).reduce((result, outerHTML) => {
            const { elements: [element] } = this.detailsByOuterHTML[outerHTML];
            return elementIsMetaElementWithName(element, name) ? element : result;
        }, undefined);
    }
}
function elementType(element) {
    if (elementIsScript(element)) {
        return "script";
    }
    else if (elementIsStylesheet(element)) {
        return "stylesheet";
    }
}
function elementIsTracked(element) {
    return element.getAttribute("data-turbo-track") == "reload";
}
function elementIsScript(element) {
    const tagName = element.tagName.toLowerCase();
    return tagName == "script";
}
function elementIsNoscript(element) {
    const tagName = element.tagName.toLowerCase();
    return tagName == "noscript";
}
function elementIsStylesheet(element) {
    const tagName = element.tagName.toLowerCase();
    return tagName == "style" || (tagName == "link" && element.getAttribute("rel") == "stylesheet");
}
function elementIsMetaElementWithName(element, name) {
    const tagName = element.tagName.toLowerCase();
    return tagName == "meta" && element.getAttribute("name") == name;
}
function elementWithoutNonce(element) {
    if (element.hasAttribute("nonce")) {
        element.setAttribute("nonce", "");
    }
    return element;
}

class PageSnapshot extends Snapshot {
    constructor(element, headSnapshot) {
        super(element);
        this.headSnapshot = headSnapshot;
    }
    static fromHTMLString(html = "") {
        return this.fromDocument(parseHTMLDocument(html));
    }
    static fromElement(element) {
        return this.fromDocument(element.ownerDocument);
    }
    static fromDocument({ head, body }) {
        return new this(body, new HeadSnapshot(head));
    }
    clone() {
        return new PageSnapshot(this.element.cloneNode(true), this.headSnapshot);
    }
    get headElement() {
        return this.headSnapshot.element;
    }
    get rootLocation() {
        var _a;
        const root = (_a = this.getSetting("root")) !== null && _a !== void 0 ? _a : "/";
        return expandURL(root);
    }
    get cacheControlValue() {
        return this.getSetting("cache-control");
    }
    get isPreviewable() {
        return this.cacheControlValue != "no-preview";
    }
    get isCacheable() {
        return this.cacheControlValue != "no-cache";
    }
    get isVisitable() {
        return this.getSetting("visit-control") != "reload";
    }
    getSetting(name) {
        return this.headSnapshot.getMetaValue(`turbo-${name}`);
    }
}

var TimingMetric;
(function (TimingMetric) {
    TimingMetric["visitStart"] = "visitStart";
    TimingMetric["requestStart"] = "requestStart";
    TimingMetric["requestEnd"] = "requestEnd";
    TimingMetric["visitEnd"] = "visitEnd";
})(TimingMetric || (TimingMetric = {}));
var VisitState;
(function (VisitState) {
    VisitState["initialized"] = "initialized";
    VisitState["started"] = "started";
    VisitState["canceled"] = "canceled";
    VisitState["failed"] = "failed";
    VisitState["completed"] = "completed";
})(VisitState || (VisitState = {}));
const defaultOptions = {
    action: "advance",
    historyChanged: false,
    visitCachedSnapshot: () => { },
    willRender: true,
};
var SystemStatusCode;
(function (SystemStatusCode) {
    SystemStatusCode[SystemStatusCode["networkFailure"] = 0] = "networkFailure";
    SystemStatusCode[SystemStatusCode["timeoutFailure"] = -1] = "timeoutFailure";
    SystemStatusCode[SystemStatusCode["contentTypeMismatch"] = -2] = "contentTypeMismatch";
})(SystemStatusCode || (SystemStatusCode = {}));
class Visit {
    constructor(delegate, location, restorationIdentifier, options = {}) {
        this.identifier = uuid();
        this.timingMetrics = {};
        this.followedRedirect = false;
        this.historyChanged = false;
        this.scrolled = false;
        this.snapshotCached = false;
        this.state = VisitState.initialized;
        this.delegate = delegate;
        this.location = location;
        this.restorationIdentifier = restorationIdentifier || uuid();
        const { action, historyChanged, referrer, snapshotHTML, response, visitCachedSnapshot, willRender } = Object.assign(Object.assign({}, defaultOptions), options);
        this.action = action;
        this.historyChanged = historyChanged;
        this.referrer = referrer;
        this.snapshotHTML = snapshotHTML;
        this.response = response;
        this.isSamePage = this.delegate.locationWithActionIsSamePage(this.location, this.action);
        this.visitCachedSnapshot = visitCachedSnapshot;
        this.willRender = willRender;
        this.scrolled = !willRender;
    }
    get adapter() {
        return this.delegate.adapter;
    }
    get view() {
        return this.delegate.view;
    }
    get history() {
        return this.delegate.history;
    }
    get restorationData() {
        return this.history.getRestorationDataForIdentifier(this.restorationIdentifier);
    }
    get silent() {
        return this.isSamePage;
    }
    start() {
        if (this.state == VisitState.initialized) {
            this.recordTimingMetric(TimingMetric.visitStart);
            this.state = VisitState.started;
            this.adapter.visitStarted(this);
            this.delegate.visitStarted(this);
        }
    }
    cancel() {
        if (this.state == VisitState.started) {
            if (this.request) {
                this.request.cancel();
            }
            this.cancelRender();
            this.state = VisitState.canceled;
        }
    }
    complete() {
        if (this.state == VisitState.started) {
            this.recordTimingMetric(TimingMetric.visitEnd);
            this.state = VisitState.completed;
            this.adapter.visitCompleted(this);
            this.delegate.visitCompleted(this);
            this.followRedirect();
        }
    }
    fail() {
        if (this.state == VisitState.started) {
            this.state = VisitState.failed;
            this.adapter.visitFailed(this);
        }
    }
    changeHistory() {
        var _a;
        if (!this.historyChanged) {
            const actionForHistory = this.location.href === ((_a = this.referrer) === null || _a === void 0 ? void 0 : _a.href) ? "replace" : this.action;
            const method = this.getHistoryMethodForAction(actionForHistory);
            this.history.update(method, this.location, this.restorationIdentifier);
            this.historyChanged = true;
        }
    }
    issueRequest() {
        if (this.hasPreloadedResponse()) {
            this.simulateRequest();
        }
        else if (this.shouldIssueRequest() && !this.request) {
            this.request = new FetchRequest(this, FetchMethod.get, this.location);
            this.request.perform();
        }
    }
    simulateRequest() {
        if (this.response) {
            this.startRequest();
            this.recordResponse();
            this.finishRequest();
        }
    }
    startRequest() {
        this.recordTimingMetric(TimingMetric.requestStart);
        this.adapter.visitRequestStarted(this);
    }
    recordResponse(response = this.response) {
        this.response = response;
        if (response) {
            const { statusCode } = response;
            if (isSuccessful(statusCode)) {
                this.adapter.visitRequestCompleted(this);
            }
            else {
                this.adapter.visitRequestFailedWithStatusCode(this, statusCode);
            }
        }
    }
    finishRequest() {
        this.recordTimingMetric(TimingMetric.requestEnd);
        this.adapter.visitRequestFinished(this);
    }
    loadResponse() {
        if (this.response) {
            const { statusCode, responseHTML } = this.response;
            this.render(async () => {
                this.cacheSnapshot();
                if (this.view.renderPromise)
                    await this.view.renderPromise;
                if (isSuccessful(statusCode) && responseHTML != null) {
                    await this.view.renderPage(PageSnapshot.fromHTMLString(responseHTML), false, this.willRender);
                    this.adapter.visitRendered(this);
                    this.complete();
                }
                else {
                    await this.view.renderError(PageSnapshot.fromHTMLString(responseHTML));
                    this.adapter.visitRendered(this);
                    this.fail();
                }
            });
        }
    }
    getCachedSnapshot() {
        const snapshot = this.view.getCachedSnapshotForLocation(this.location) || this.getPreloadedSnapshot();
        if (snapshot && (!getAnchor(this.location) || snapshot.hasAnchor(getAnchor(this.location)))) {
            if (this.action == "restore" || snapshot.isPreviewable) {
                return snapshot;
            }
        }
    }
    getPreloadedSnapshot() {
        if (this.snapshotHTML) {
            return PageSnapshot.fromHTMLString(this.snapshotHTML);
        }
    }
    hasCachedSnapshot() {
        return this.getCachedSnapshot() != null;
    }
    loadCachedSnapshot() {
        const snapshot = this.getCachedSnapshot();
        if (snapshot) {
            const isPreview = this.shouldIssueRequest();
            this.render(async () => {
                this.cacheSnapshot();
                if (this.isSamePage) {
                    this.adapter.visitRendered(this);
                }
                else {
                    if (this.view.renderPromise)
                        await this.view.renderPromise;
                    await this.view.renderPage(snapshot, isPreview, this.willRender);
                    this.adapter.visitRendered(this);
                    if (!isPreview) {
                        this.complete();
                    }
                }
            });
        }
    }
    followRedirect() {
        var _a;
        if (this.redirectedToLocation && !this.followedRedirect && ((_a = this.response) === null || _a === void 0 ? void 0 : _a.redirected)) {
            this.adapter.visitProposedToLocation(this.redirectedToLocation, {
                action: 'replace',
                response: this.response
            });
            this.followedRedirect = true;
        }
    }
    goToSamePageAnchor() {
        if (this.isSamePage) {
            this.render(async () => {
                this.cacheSnapshot();
                this.adapter.visitRendered(this);
            });
        }
    }
    requestStarted() {
        this.startRequest();
    }
    requestPreventedHandlingResponse(request, response) {
    }
    async requestSucceededWithResponse(request, response) {
        const responseHTML = await response.responseHTML;
        const { redirected, statusCode } = response;
        if (responseHTML == undefined) {
            this.recordResponse({ statusCode: SystemStatusCode.contentTypeMismatch, redirected });
        }
        else {
            this.redirectedToLocation = response.redirected ? response.location : undefined;
            this.recordResponse({ statusCode: statusCode, responseHTML, redirected });
        }
    }
    async requestFailedWithResponse(request, response) {
        const responseHTML = await response.responseHTML;
        const { redirected, statusCode } = response;
        if (responseHTML == undefined) {
            this.recordResponse({ statusCode: SystemStatusCode.contentTypeMismatch, redirected });
        }
        else {
            this.recordResponse({ statusCode: statusCode, responseHTML, redirected });
        }
    }
    requestErrored(request, error) {
        this.recordResponse({ statusCode: SystemStatusCode.networkFailure, redirected: false });
    }
    requestFinished() {
        this.finishRequest();
    }
    performScroll() {
        if (!this.scrolled) {
            if (this.action == "restore") {
                this.scrollToRestoredPosition() || this.scrollToAnchor() || this.view.scrollToTop();
            }
            else {
                this.scrollToAnchor() || this.view.scrollToTop();
            }
            if (this.isSamePage) {
                this.delegate.visitScrolledToSamePageLocation(this.view.lastRenderedLocation, this.location);
            }
            this.scrolled = true;
        }
    }
    scrollToRestoredPosition() {
        const { scrollPosition } = this.restorationData;
        if (scrollPosition) {
            this.view.scrollToPosition(scrollPosition);
            return true;
        }
    }
    scrollToAnchor() {
        const anchor = getAnchor(this.location);
        if (anchor != null) {
            this.view.scrollToAnchor(anchor);
            return true;
        }
    }
    recordTimingMetric(metric) {
        this.timingMetrics[metric] = new Date().getTime();
    }
    getTimingMetrics() {
        return Object.assign({}, this.timingMetrics);
    }
    getHistoryMethodForAction(action) {
        switch (action) {
            case "replace": return history.replaceState;
            case "advance":
            case "restore": return history.pushState;
        }
    }
    hasPreloadedResponse() {
        return typeof this.response == "object";
    }
    shouldIssueRequest() {
        if (this.isSamePage) {
            return false;
        }
        else if (this.action == "restore") {
            return !this.hasCachedSnapshot();
        }
        else {
            return this.willRender;
        }
    }
    cacheSnapshot() {
        if (!this.snapshotCached) {
            this.view.cacheSnapshot().then(snapshot => snapshot && this.visitCachedSnapshot(snapshot));
            this.snapshotCached = true;
        }
    }
    async render(callback) {
        this.cancelRender();
        await new Promise(resolve => {
            this.frame = requestAnimationFrame(() => resolve());
        });
        await callback();
        delete this.frame;
        this.performScroll();
    }
    cancelRender() {
        if (this.frame) {
            cancelAnimationFrame(this.frame);
            delete this.frame;
        }
    }
}
function isSuccessful(statusCode) {
    return statusCode >= 200 && statusCode < 300;
}

class BrowserAdapter {
    constructor(session) {
        this.progressBar = new ProgressBar;
        this.showProgressBar = () => {
            this.progressBar.show();
        };
        this.session = session;
    }
    visitProposedToLocation(location, options) {
        this.navigator.startVisit(location, uuid(), options);
    }
    visitStarted(visit) {
        visit.loadCachedSnapshot();
        visit.issueRequest();
        visit.changeHistory();
        visit.goToSamePageAnchor();
    }
    visitRequestStarted(visit) {
        this.progressBar.setValue(0);
        if (visit.hasCachedSnapshot() || visit.action != "restore") {
            this.showVisitProgressBarAfterDelay();
        }
        else {
            this.showProgressBar();
        }
    }
    visitRequestCompleted(visit) {
        visit.loadResponse();
    }
    visitRequestFailedWithStatusCode(visit, statusCode) {
        switch (statusCode) {
            case SystemStatusCode.networkFailure:
            case SystemStatusCode.timeoutFailure:
            case SystemStatusCode.contentTypeMismatch:
                return this.reload();
            default:
                return visit.loadResponse();
        }
    }
    visitRequestFinished(visit) {
        this.progressBar.setValue(1);
        this.hideVisitProgressBar();
    }
    visitCompleted(visit) {
    }
    pageInvalidated() {
        this.reload();
    }
    visitFailed(visit) {
    }
    visitRendered(visit) {
    }
    formSubmissionStarted(formSubmission) {
        this.progressBar.setValue(0);
        this.showFormProgressBarAfterDelay();
    }
    formSubmissionFinished(formSubmission) {
        this.progressBar.setValue(1);
        this.hideFormProgressBar();
    }
    showVisitProgressBarAfterDelay() {
        this.visitProgressBarTimeout = window.setTimeout(this.showProgressBar, this.session.progressBarDelay);
    }
    hideVisitProgressBar() {
        this.progressBar.hide();
        if (this.visitProgressBarTimeout != null) {
            window.clearTimeout(this.visitProgressBarTimeout);
            delete this.visitProgressBarTimeout;
        }
    }
    showFormProgressBarAfterDelay() {
        if (this.formProgressBarTimeout == null) {
            this.formProgressBarTimeout = window.setTimeout(this.showProgressBar, this.session.progressBarDelay);
        }
    }
    hideFormProgressBar() {
        this.progressBar.hide();
        if (this.formProgressBarTimeout != null) {
            window.clearTimeout(this.formProgressBarTimeout);
            delete this.formProgressBarTimeout;
        }
    }
    reload() {
        window.location.reload();
    }
    get navigator() {
        return this.session.navigator;
    }
}

class CacheObserver {
    constructor() {
        this.started = false;
    }
    start() {
        if (!this.started) {
            this.started = true;
            addEventListener("turbo:before-cache", this.removeStaleElements, false);
        }
    }
    stop() {
        if (this.started) {
            this.started = false;
            removeEventListener("turbo:before-cache", this.removeStaleElements, false);
        }
    }
    removeStaleElements() {
        const staleElements = [...document.querySelectorAll('[data-turbo-cache="false"]')];
        for (const element of staleElements) {
            element.remove();
        }
    }
}

class FormSubmitObserver {
    constructor(delegate) {
        this.started = false;
        this.submitCaptured = () => {
            removeEventListener("submit", this.submitBubbled, false);
            addEventListener("submit", this.submitBubbled, false);
        };
        this.submitBubbled = ((event) => {
            if (!event.defaultPrevented) {
                const form = event.target instanceof HTMLFormElement ? event.target : undefined;
                const submitter = event.submitter || undefined;
                if (form) {
                    const method = (submitter === null || submitter === void 0 ? void 0 : submitter.getAttribute("formmethod")) || form.getAttribute("method");
                    if (method != "dialog" && this.delegate.willSubmitForm(form, submitter)) {
                        event.preventDefault();
                        this.delegate.formSubmitted(form, submitter);
                    }
                }
            }
        });
        this.delegate = delegate;
    }
    start() {
        if (!this.started) {
            addEventListener("submit", this.submitCaptured, true);
            this.started = true;
        }
    }
    stop() {
        if (this.started) {
            removeEventListener("submit", this.submitCaptured, true);
            this.started = false;
        }
    }
}

class FrameRedirector {
    constructor(element) {
        this.element = element;
        this.linkInterceptor = new LinkInterceptor(this, element);
        this.formInterceptor = new FormInterceptor(this, element);
    }
    start() {
        this.linkInterceptor.start();
        this.formInterceptor.start();
    }
    stop() {
        this.linkInterceptor.stop();
        this.formInterceptor.stop();
    }
    shouldInterceptLinkClick(element, url) {
        return this.shouldRedirect(element);
    }
    linkClickIntercepted(element, url) {
        const frame = this.findFrameElement(element);
        if (frame) {
            frame.delegate.linkClickIntercepted(element, url);
        }
    }
    shouldInterceptFormSubmission(element, submitter) {
        return this.shouldSubmit(element, submitter);
    }
    formSubmissionIntercepted(element, submitter) {
        const frame = this.findFrameElement(element, submitter);
        if (frame) {
            frame.removeAttribute("reloadable");
            frame.delegate.formSubmissionIntercepted(element, submitter);
        }
    }
    shouldSubmit(form, submitter) {
        var _a;
        const action = getAction(form, submitter);
        const meta = this.element.ownerDocument.querySelector(`meta[name="turbo-root"]`);
        const rootLocation = expandURL((_a = meta === null || meta === void 0 ? void 0 : meta.content) !== null && _a !== void 0 ? _a : "/");
        return this.shouldRedirect(form, submitter) && locationIsVisitable(action, rootLocation);
    }
    shouldRedirect(element, submitter) {
        const frame = this.findFrameElement(element, submitter);
        return frame ? frame != element.closest("turbo-frame") : false;
    }
    findFrameElement(element, submitter) {
        const id = (submitter === null || submitter === void 0 ? void 0 : submitter.getAttribute("data-turbo-frame")) || element.getAttribute("data-turbo-frame");
        if (id && id != "_top") {
            const frame = this.element.querySelector(`#${id}:not([disabled])`);
            if (frame instanceof FrameElement) {
                return frame;
            }
        }
    }
}

class History {
    constructor(delegate) {
        this.restorationIdentifier = uuid();
        this.restorationData = {};
        this.started = false;
        this.pageLoaded = false;
        this.onPopState = (event) => {
            if (this.shouldHandlePopState()) {
                const { turbo } = event.state || {};
                if (turbo) {
                    this.location = new URL(window.location.href);
                    const { restorationIdentifier } = turbo;
                    this.restorationIdentifier = restorationIdentifier;
                    this.delegate.historyPoppedToLocationWithRestorationIdentifier(this.location, restorationIdentifier);
                }
            }
        };
        this.onPageLoad = async (event) => {
            await nextMicrotask();
            this.pageLoaded = true;
        };
        this.delegate = delegate;
    }
    start() {
        if (!this.started) {
            addEventListener("popstate", this.onPopState, false);
            addEventListener("load", this.onPageLoad, false);
            this.started = true;
            this.replace(new URL(window.location.href));
        }
    }
    stop() {
        if (this.started) {
            removeEventListener("popstate", this.onPopState, false);
            removeEventListener("load", this.onPageLoad, false);
            this.started = false;
        }
    }
    push(location, restorationIdentifier) {
        this.update(history.pushState, location, restorationIdentifier);
    }
    replace(location, restorationIdentifier) {
        this.update(history.replaceState, location, restorationIdentifier);
    }
    update(method, location, restorationIdentifier = uuid()) {
        const state = { turbo: { restorationIdentifier } };
        method.call(history, state, "", location.href);
        this.location = location;
        this.restorationIdentifier = restorationIdentifier;
    }
    getRestorationDataForIdentifier(restorationIdentifier) {
        return this.restorationData[restorationIdentifier] || {};
    }
    updateRestorationData(additionalData) {
        const { restorationIdentifier } = this;
        const restorationData = this.restorationData[restorationIdentifier];
        this.restorationData[restorationIdentifier] = Object.assign(Object.assign({}, restorationData), additionalData);
    }
    assumeControlOfScrollRestoration() {
        var _a;
        if (!this.previousScrollRestoration) {
            this.previousScrollRestoration = (_a = history.scrollRestoration) !== null && _a !== void 0 ? _a : "auto";
            history.scrollRestoration = "manual";
        }
    }
    relinquishControlOfScrollRestoration() {
        if (this.previousScrollRestoration) {
            history.scrollRestoration = this.previousScrollRestoration;
            delete this.previousScrollRestoration;
        }
    }
    shouldHandlePopState() {
        return this.pageIsLoaded();
    }
    pageIsLoaded() {
        return this.pageLoaded || document.readyState == "complete";
    }
}

class LinkClickObserver {
    constructor(delegate) {
        this.started = false;
        this.clickCaptured = () => {
            removeEventListener("click", this.clickBubbled, false);
            addEventListener("click", this.clickBubbled, false);
        };
        this.clickBubbled = (event) => {
            if (this.clickEventIsSignificant(event)) {
                const target = (event.composedPath && event.composedPath()[0]) || event.target;
                const link = this.findLinkFromClickTarget(target);
                if (link) {
                    const location = this.getLocationForLink(link);
                    if (this.delegate.willFollowLinkToLocation(link, location)) {
                        event.preventDefault();
                        this.delegate.followedLinkToLocation(link, location);
                    }
                }
            }
        };
        this.delegate = delegate;
    }
    start() {
        if (!this.started) {
            addEventListener("click", this.clickCaptured, true);
            this.started = true;
        }
    }
    stop() {
        if (this.started) {
            removeEventListener("click", this.clickCaptured, true);
            this.started = false;
        }
    }
    clickEventIsSignificant(event) {
        return !((event.target && event.target.isContentEditable)
            || event.defaultPrevented
            || event.which > 1
            || event.altKey
            || event.ctrlKey
            || event.metaKey
            || event.shiftKey);
    }
    findLinkFromClickTarget(target) {
        if (target instanceof Element) {
            return target.closest("a[href]:not([target^=_]):not([download])");
        }
    }
    getLocationForLink(link) {
        return expandURL(link.getAttribute("href") || "");
    }
}

function isAction(action) {
    return action == "advance" || action == "replace" || action == "restore";
}

class Navigator {
    constructor(delegate) {
        this.delegate = delegate;
    }
    proposeVisit(location, options = {}) {
        if (this.delegate.allowsVisitingLocationWithAction(location, options.action)) {
            if (locationIsVisitable(location, this.view.snapshot.rootLocation)) {
                this.delegate.visitProposedToLocation(location, options);
            }
            else {
                window.location.href = location.toString();
            }
        }
    }
    startVisit(locatable, restorationIdentifier, options = {}) {
        this.stop();
        this.currentVisit = new Visit(this, expandURL(locatable), restorationIdentifier, Object.assign({ referrer: this.location }, options));
        this.currentVisit.start();
    }
    submitForm(form, submitter) {
        this.stop();
        this.formSubmission = new FormSubmission(this, form, submitter, true);
        this.formSubmission.start();
    }
    stop() {
        if (this.formSubmission) {
            this.formSubmission.stop();
            delete this.formSubmission;
        }
        if (this.currentVisit) {
            this.currentVisit.cancel();
            delete this.currentVisit;
        }
    }
    get adapter() {
        return this.delegate.adapter;
    }
    get view() {
        return this.delegate.view;
    }
    get history() {
        return this.delegate.history;
    }
    formSubmissionStarted(formSubmission) {
        if (typeof this.adapter.formSubmissionStarted === 'function') {
            this.adapter.formSubmissionStarted(formSubmission);
        }
    }
    async formSubmissionSucceededWithResponse(formSubmission, fetchResponse) {
        if (formSubmission == this.formSubmission) {
            const responseHTML = await fetchResponse.responseHTML;
            if (responseHTML) {
                if (formSubmission.method != FetchMethod.get) {
                    this.view.clearSnapshotCache();
                }
                const { statusCode, redirected } = fetchResponse;
                const action = this.getActionForFormSubmission(formSubmission);
                const visitOptions = { action, response: { statusCode, responseHTML, redirected } };
                this.proposeVisit(fetchResponse.location, visitOptions);
            }
        }
    }
    async formSubmissionFailedWithResponse(formSubmission, fetchResponse) {
        const responseHTML = await fetchResponse.responseHTML;
        if (responseHTML) {
            const snapshot = PageSnapshot.fromHTMLString(responseHTML);
            if (fetchResponse.serverError) {
                await this.view.renderError(snapshot);
            }
            else {
                await this.view.renderPage(snapshot);
            }
            this.view.scrollToTop();
            this.view.clearSnapshotCache();
        }
    }
    formSubmissionErrored(formSubmission, error) {
        console.error(error);
    }
    formSubmissionFinished(formSubmission) {
        if (typeof this.adapter.formSubmissionFinished === 'function') {
            this.adapter.formSubmissionFinished(formSubmission);
        }
    }
    visitStarted(visit) {
        this.delegate.visitStarted(visit);
    }
    visitCompleted(visit) {
        this.delegate.visitCompleted(visit);
    }
    locationWithActionIsSamePage(location, action) {
        const anchor = getAnchor(location);
        const currentAnchor = getAnchor(this.view.lastRenderedLocation);
        const isRestorationToTop = action === 'restore' && typeof anchor === 'undefined';
        return action !== "replace" &&
            getRequestURL(location) === getRequestURL(this.view.lastRenderedLocation) &&
            (isRestorationToTop || (anchor != null && anchor !== currentAnchor));
    }
    visitScrolledToSamePageLocation(oldURL, newURL) {
        this.delegate.visitScrolledToSamePageLocation(oldURL, newURL);
    }
    get location() {
        return this.history.location;
    }
    get restorationIdentifier() {
        return this.history.restorationIdentifier;
    }
    getActionForFormSubmission(formSubmission) {
        const { formElement, submitter } = formSubmission;
        const action = getAttribute("data-turbo-action", submitter, formElement);
        return isAction(action) ? action : "advance";
    }
}

var PageStage;
(function (PageStage) {
    PageStage[PageStage["initial"] = 0] = "initial";
    PageStage[PageStage["loading"] = 1] = "loading";
    PageStage[PageStage["interactive"] = 2] = "interactive";
    PageStage[PageStage["complete"] = 3] = "complete";
})(PageStage || (PageStage = {}));
class PageObserver {
    constructor(delegate) {
        this.stage = PageStage.initial;
        this.started = false;
        this.interpretReadyState = () => {
            const { readyState } = this;
            if (readyState == "interactive") {
                this.pageIsInteractive();
            }
            else if (readyState == "complete") {
                this.pageIsComplete();
            }
        };
        this.pageWillUnload = () => {
            this.delegate.pageWillUnload();
        };
        this.delegate = delegate;
    }
    start() {
        if (!this.started) {
            if (this.stage == PageStage.initial) {
                this.stage = PageStage.loading;
            }
            document.addEventListener("readystatechange", this.interpretReadyState, false);
            addEventListener("pagehide", this.pageWillUnload, false);
            this.started = true;
        }
    }
    stop() {
        if (this.started) {
            document.removeEventListener("readystatechange", this.interpretReadyState, false);
            removeEventListener("pagehide", this.pageWillUnload, false);
            this.started = false;
        }
    }
    pageIsInteractive() {
        if (this.stage == PageStage.loading) {
            this.stage = PageStage.interactive;
            this.delegate.pageBecameInteractive();
        }
    }
    pageIsComplete() {
        this.pageIsInteractive();
        if (this.stage == PageStage.interactive) {
            this.stage = PageStage.complete;
            this.delegate.pageLoaded();
        }
    }
    get readyState() {
        return document.readyState;
    }
}

class ScrollObserver {
    constructor(delegate) {
        this.started = false;
        this.onScroll = () => {
            this.updatePosition({ x: window.pageXOffset, y: window.pageYOffset });
        };
        this.delegate = delegate;
    }
    start() {
        if (!this.started) {
            addEventListener("scroll", this.onScroll, false);
            this.onScroll();
            this.started = true;
        }
    }
    stop() {
        if (this.started) {
            removeEventListener("scroll", this.onScroll, false);
            this.started = false;
        }
    }
    updatePosition(position) {
        this.delegate.scrollPositionChanged(position);
    }
}

class StreamObserver {
    constructor(delegate) {
        this.sources = new Set;
        this.started = false;
        this.inspectFetchResponse = ((event) => {
            const response = fetchResponseFromEvent(event);
            if (response && fetchResponseIsStream(response)) {
                event.preventDefault();
                this.receiveMessageResponse(response);
            }
        });
        this.receiveMessageEvent = (event) => {
            if (this.started && typeof event.data == "string") {
                this.receiveMessageHTML(event.data);
            }
        };
        this.delegate = delegate;
    }
    start() {
        if (!this.started) {
            this.started = true;
            addEventListener("turbo:before-fetch-response", this.inspectFetchResponse, false);
        }
    }
    stop() {
        if (this.started) {
            this.started = false;
            removeEventListener("turbo:before-fetch-response", this.inspectFetchResponse, false);
        }
    }
    connectStreamSource(source) {
        if (!this.streamSourceIsConnected(source)) {
            this.sources.add(source);
            source.addEventListener("message", this.receiveMessageEvent, false);
        }
    }
    disconnectStreamSource(source) {
        if (this.streamSourceIsConnected(source)) {
            this.sources.delete(source);
            source.removeEventListener("message", this.receiveMessageEvent, false);
        }
    }
    streamSourceIsConnected(source) {
        return this.sources.has(source);
    }
    async receiveMessageResponse(response) {
        const html = await response.responseHTML;
        if (html) {
            this.receiveMessageHTML(html);
        }
    }
    receiveMessageHTML(html) {
        this.delegate.receivedMessageFromStream(new StreamMessage(html));
    }
}
function fetchResponseFromEvent(event) {
    var _a;
    const fetchResponse = (_a = event.detail) === null || _a === void 0 ? void 0 : _a.fetchResponse;
    if (fetchResponse instanceof FetchResponse) {
        return fetchResponse;
    }
}
function fetchResponseIsStream(response) {
    var _a;
    const contentType = (_a = response.contentType) !== null && _a !== void 0 ? _a : "";
    return contentType.startsWith(StreamMessage.contentType);
}

class ErrorRenderer extends Renderer {
    async render() {
        this.replaceHeadAndBody();
        this.activateScriptElements();
    }
    replaceHeadAndBody() {
        const { documentElement, head, body } = document;
        documentElement.replaceChild(this.newHead, head);
        documentElement.replaceChild(this.newElement, body);
    }
    activateScriptElements() {
        for (const replaceableElement of this.scriptElements) {
            const parentNode = replaceableElement.parentNode;
            if (parentNode) {
                const element = this.createScriptElement(replaceableElement);
                parentNode.replaceChild(element, replaceableElement);
            }
        }
    }
    get newHead() {
        return this.newSnapshot.headSnapshot.element;
    }
    get scriptElements() {
        return [...document.documentElement.querySelectorAll("script")];
    }
}

class PageRenderer extends Renderer {
    get shouldRender() {
        return this.newSnapshot.isVisitable && this.trackedElementsAreIdentical;
    }
    prepareToRender() {
        this.mergeHead();
    }
    async render() {
        if (this.willRender) {
            this.replaceBody();
        }
    }
    finishRendering() {
        super.finishRendering();
        if (!this.isPreview) {
            this.focusFirstAutofocusableElement();
        }
    }
    get currentHeadSnapshot() {
        return this.currentSnapshot.headSnapshot;
    }
    get newHeadSnapshot() {
        return this.newSnapshot.headSnapshot;
    }
    get newElement() {
        return this.newSnapshot.element;
    }
    mergeHead() {
        this.copyNewHeadStylesheetElements();
        this.copyNewHeadScriptElements();
        this.removeCurrentHeadProvisionalElements();
        this.copyNewHeadProvisionalElements();
    }
    replaceBody() {
        this.preservingPermanentElements(() => {
            this.activateNewBody();
            this.assignNewBody();
        });
    }
    get trackedElementsAreIdentical() {
        return this.currentHeadSnapshot.trackedElementSignature == this.newHeadSnapshot.trackedElementSignature;
    }
    copyNewHeadStylesheetElements() {
        for (const element of this.newHeadStylesheetElements) {
            document.head.appendChild(element);
        }
    }
    copyNewHeadScriptElements() {
        for (const element of this.newHeadScriptElements) {
            document.head.appendChild(this.createScriptElement(element));
        }
    }
    removeCurrentHeadProvisionalElements() {
        for (const element of this.currentHeadProvisionalElements) {
            document.head.removeChild(element);
        }
    }
    copyNewHeadProvisionalElements() {
        for (const element of this.newHeadProvisionalElements) {
            document.head.appendChild(element);
        }
    }
    activateNewBody() {
        document.adoptNode(this.newElement);
        this.activateNewBodyScriptElements();
    }
    activateNewBodyScriptElements() {
        for (const inertScriptElement of this.newBodyScriptElements) {
            const activatedScriptElement = this.createScriptElement(inertScriptElement);
            inertScriptElement.replaceWith(activatedScriptElement);
        }
    }
    assignNewBody() {
        if (document.body && this.newElement instanceof HTMLBodyElement) {
            document.body.replaceWith(this.newElement);
        }
        else {
            document.documentElement.appendChild(this.newElement);
        }
    }
    get newHeadStylesheetElements() {
        return this.newHeadSnapshot.getStylesheetElementsNotInSnapshot(this.currentHeadSnapshot);
    }
    get newHeadScriptElements() {
        return this.newHeadSnapshot.getScriptElementsNotInSnapshot(this.currentHeadSnapshot);
    }
    get currentHeadProvisionalElements() {
        return this.currentHeadSnapshot.provisionalElements;
    }
    get newHeadProvisionalElements() {
        return this.newHeadSnapshot.provisionalElements;
    }
    get newBodyScriptElements() {
        return this.newElement.querySelectorAll("script");
    }
}

class SnapshotCache {
    constructor(size) {
        this.keys = [];
        this.snapshots = {};
        this.size = size;
    }
    has(location) {
        return toCacheKey(location) in this.snapshots;
    }
    get(location) {
        if (this.has(location)) {
            const snapshot = this.read(location);
            this.touch(location);
            return snapshot;
        }
    }
    put(location, snapshot) {
        this.write(location, snapshot);
        this.touch(location);
        return snapshot;
    }
    clear() {
        this.snapshots = {};
    }
    read(location) {
        return this.snapshots[toCacheKey(location)];
    }
    write(location, snapshot) {
        this.snapshots[toCacheKey(location)] = snapshot;
    }
    touch(location) {
        const key = toCacheKey(location);
        const index = this.keys.indexOf(key);
        if (index > -1)
            this.keys.splice(index, 1);
        this.keys.unshift(key);
        this.trim();
    }
    trim() {
        for (const key of this.keys.splice(this.size)) {
            delete this.snapshots[key];
        }
    }
}

class PageView extends View {
    constructor() {
        super(...arguments);
        this.snapshotCache = new SnapshotCache(10);
        this.lastRenderedLocation = new URL(location.href);
    }
    renderPage(snapshot, isPreview = false, willRender = true) {
        const renderer = new PageRenderer(this.snapshot, snapshot, isPreview, willRender);
        return this.render(renderer);
    }
    renderError(snapshot) {
        const renderer = new ErrorRenderer(this.snapshot, snapshot, false);
        return this.render(renderer);
    }
    clearSnapshotCache() {
        this.snapshotCache.clear();
    }
    async cacheSnapshot() {
        if (this.shouldCacheSnapshot) {
            this.delegate.viewWillCacheSnapshot();
            const { snapshot, lastRenderedLocation: location } = this;
            await nextEventLoopTick();
            const cachedSnapshot = snapshot.clone();
            this.snapshotCache.put(location, cachedSnapshot);
            return cachedSnapshot;
        }
    }
    getCachedSnapshotForLocation(location) {
        return this.snapshotCache.get(location);
    }
    get snapshot() {
        return PageSnapshot.fromElement(this.element);
    }
    get shouldCacheSnapshot() {
        return this.snapshot.isCacheable;
    }
}

class Session {
    constructor() {
        this.navigator = new Navigator(this);
        this.history = new History(this);
        this.view = new PageView(this, document.documentElement);
        this.adapter = new BrowserAdapter(this);
        this.pageObserver = new PageObserver(this);
        this.cacheObserver = new CacheObserver();
        this.linkClickObserver = new LinkClickObserver(this);
        this.formSubmitObserver = new FormSubmitObserver(this);
        this.scrollObserver = new ScrollObserver(this);
        this.streamObserver = new StreamObserver(this);
        this.frameRedirector = new FrameRedirector(document.documentElement);
        this.drive = true;
        this.enabled = true;
        this.progressBarDelay = 500;
        this.started = false;
    }
    start() {
        if (!this.started) {
            this.pageObserver.start();
            this.cacheObserver.start();
            this.linkClickObserver.start();
            this.formSubmitObserver.start();
            this.scrollObserver.start();
            this.streamObserver.start();
            this.frameRedirector.start();
            this.history.start();
            this.started = true;
            this.enabled = true;
        }
    }
    disable() {
        this.enabled = false;
    }
    stop() {
        if (this.started) {
            this.pageObserver.stop();
            this.cacheObserver.stop();
            this.linkClickObserver.stop();
            this.formSubmitObserver.stop();
            this.scrollObserver.stop();
            this.streamObserver.stop();
            this.frameRedirector.stop();
            this.history.stop();
            this.started = false;
        }
    }
    registerAdapter(adapter) {
        this.adapter = adapter;
    }
    visit(location, options = {}) {
        this.navigator.proposeVisit(expandURL(location), options);
    }
    connectStreamSource(source) {
        this.streamObserver.connectStreamSource(source);
    }
    disconnectStreamSource(source) {
        this.streamObserver.disconnectStreamSource(source);
    }
    renderStreamMessage(message) {
        document.documentElement.appendChild(StreamMessage.wrap(message).fragment);
    }
    clearCache() {
        this.view.clearSnapshotCache();
    }
    setProgressBarDelay(delay) {
        this.progressBarDelay = delay;
    }
    get location() {
        return this.history.location;
    }
    get restorationIdentifier() {
        return this.history.restorationIdentifier;
    }
    historyPoppedToLocationWithRestorationIdentifier(location, restorationIdentifier) {
        if (this.enabled) {
            this.navigator.startVisit(location, restorationIdentifier, { action: "restore", historyChanged: true });
        }
        else {
            this.adapter.pageInvalidated();
        }
    }
    scrollPositionChanged(position) {
        this.history.updateRestorationData({ scrollPosition: position });
    }
    willFollowLinkToLocation(link, location) {
        return this.elementDriveEnabled(link)
            && locationIsVisitable(location, this.snapshot.rootLocation)
            && this.applicationAllowsFollowingLinkToLocation(link, location);
    }
    followedLinkToLocation(link, location) {
        const action = this.getActionForLink(link);
        this.convertLinkWithMethodClickToFormSubmission(link) || this.visit(location.href, { action });
    }
    convertLinkWithMethodClickToFormSubmission(link) {
        const linkMethod = link.getAttribute("data-turbo-method");
        if (linkMethod) {
            const form = document.createElement("form");
            form.method = linkMethod;
            form.action = link.getAttribute("href") || "undefined";
            form.hidden = true;
            if (link.hasAttribute("data-turbo-confirm")) {
                form.setAttribute("data-turbo-confirm", link.getAttribute("data-turbo-confirm"));
            }
            const frame = this.getTargetFrameForLink(link);
            if (frame) {
                form.setAttribute("data-turbo-frame", frame);
                form.addEventListener("turbo:submit-start", () => form.remove());
            }
            else {
                form.addEventListener("submit", () => form.remove());
            }
            document.body.appendChild(form);
            return dispatch("submit", { cancelable: true, target: form });
        }
        else {
            return false;
        }
    }
    allowsVisitingLocationWithAction(location, action) {
        return this.locationWithActionIsSamePage(location, action) || this.applicationAllowsVisitingLocation(location);
    }
    visitProposedToLocation(location, options) {
        extendURLWithDeprecatedProperties(location);
        this.adapter.visitProposedToLocation(location, options);
    }
    visitStarted(visit) {
        extendURLWithDeprecatedProperties(visit.location);
        if (!visit.silent) {
            this.notifyApplicationAfterVisitingLocation(visit.location, visit.action);
        }
    }
    visitCompleted(visit) {
        this.notifyApplicationAfterPageLoad(visit.getTimingMetrics());
    }
    locationWithActionIsSamePage(location, action) {
        return this.navigator.locationWithActionIsSamePage(location, action);
    }
    visitScrolledToSamePageLocation(oldURL, newURL) {
        this.notifyApplicationAfterVisitingSamePageLocation(oldURL, newURL);
    }
    willSubmitForm(form, submitter) {
        const action = getAction(form, submitter);
        return this.elementDriveEnabled(form)
            && (!submitter || this.elementDriveEnabled(submitter))
            && locationIsVisitable(expandURL(action), this.snapshot.rootLocation);
    }
    formSubmitted(form, submitter) {
        this.navigator.submitForm(form, submitter);
    }
    pageBecameInteractive() {
        this.view.lastRenderedLocation = this.location;
        this.notifyApplicationAfterPageLoad();
    }
    pageLoaded() {
        this.history.assumeControlOfScrollRestoration();
    }
    pageWillUnload() {
        this.history.relinquishControlOfScrollRestoration();
    }
    receivedMessageFromStream(message) {
        this.renderStreamMessage(message);
    }
    viewWillCacheSnapshot() {
        var _a;
        if (!((_a = this.navigator.currentVisit) === null || _a === void 0 ? void 0 : _a.silent)) {
            this.notifyApplicationBeforeCachingSnapshot();
        }
    }
    allowsImmediateRender({ element }, resume) {
        const event = this.notifyApplicationBeforeRender(element, resume);
        return !event.defaultPrevented;
    }
    viewRenderedSnapshot(snapshot, isPreview) {
        this.view.lastRenderedLocation = this.history.location;
        this.notifyApplicationAfterRender();
    }
    viewInvalidated() {
        this.adapter.pageInvalidated();
    }
    frameLoaded(frame) {
        this.notifyApplicationAfterFrameLoad(frame);
    }
    frameRendered(fetchResponse, frame) {
        this.notifyApplicationAfterFrameRender(fetchResponse, frame);
    }
    applicationAllowsFollowingLinkToLocation(link, location) {
        const event = this.notifyApplicationAfterClickingLinkToLocation(link, location);
        return !event.defaultPrevented;
    }
    applicationAllowsVisitingLocation(location) {
        const event = this.notifyApplicationBeforeVisitingLocation(location);
        return !event.defaultPrevented;
    }
    notifyApplicationAfterClickingLinkToLocation(link, location) {
        return dispatch("turbo:click", { target: link, detail: { url: location.href }, cancelable: true });
    }
    notifyApplicationBeforeVisitingLocation(location) {
        return dispatch("turbo:before-visit", { detail: { url: location.href }, cancelable: true });
    }
    notifyApplicationAfterVisitingLocation(location, action) {
        markAsBusy(document.documentElement);
        return dispatch("turbo:visit", { detail: { url: location.href, action } });
    }
    notifyApplicationBeforeCachingSnapshot() {
        return dispatch("turbo:before-cache");
    }
    notifyApplicationBeforeRender(newBody, resume) {
        return dispatch("turbo:before-render", { detail: { newBody, resume }, cancelable: true });
    }
    notifyApplicationAfterRender() {
        return dispatch("turbo:render");
    }
    notifyApplicationAfterPageLoad(timing = {}) {
        clearBusyState(document.documentElement);
        return dispatch("turbo:load", { detail: { url: this.location.href, timing } });
    }
    notifyApplicationAfterVisitingSamePageLocation(oldURL, newURL) {
        dispatchEvent(new HashChangeEvent("hashchange", { oldURL: oldURL.toString(), newURL: newURL.toString() }));
    }
    notifyApplicationAfterFrameLoad(frame) {
        return dispatch("turbo:frame-load", { target: frame });
    }
    notifyApplicationAfterFrameRender(fetchResponse, frame) {
        return dispatch("turbo:frame-render", { detail: { fetchResponse }, target: frame, cancelable: true });
    }
    elementDriveEnabled(element) {
        const container = element === null || element === void 0 ? void 0 : element.closest("[data-turbo]");
        if (this.drive) {
            if (container) {
                return container.getAttribute("data-turbo") != "false";
            }
            else {
                return true;
            }
        }
        else {
            if (container) {
                return container.getAttribute("data-turbo") == "true";
            }
            else {
                return false;
            }
        }
    }
    getActionForLink(link) {
        const action = link.getAttribute("data-turbo-action");
        return isAction(action) ? action : "advance";
    }
    getTargetFrameForLink(link) {
        const frame = link.getAttribute("data-turbo-frame");
        if (frame) {
            return frame;
        }
        else {
            const container = link.closest("turbo-frame");
            if (container) {
                return container.id;
            }
        }
    }
    get snapshot() {
        return this.view.snapshot;
    }
}
function extendURLWithDeprecatedProperties(url) {
    Object.defineProperties(url, deprecatedLocationPropertyDescriptors);
}
const deprecatedLocationPropertyDescriptors = {
    absoluteURL: {
        get() {
            return this.toString();
        }
    }
};

const session = new Session;
const { navigator: navigator$1 } = session;
function start() {
    session.start();
}
function registerAdapter(adapter) {
    session.registerAdapter(adapter);
}
function visit(location, options) {
    session.visit(location, options);
}
function connectStreamSource(source) {
    session.connectStreamSource(source);
}
function disconnectStreamSource(source) {
    session.disconnectStreamSource(source);
}
function renderStreamMessage(message) {
    session.renderStreamMessage(message);
}
function clearCache() {
    session.clearCache();
}
function setProgressBarDelay(delay) {
    session.setProgressBarDelay(delay);
}
function setConfirmMethod(confirmMethod) {
    FormSubmission.confirmMethod = confirmMethod;
}

var Turbo = /*#__PURE__*/Object.freeze({
    __proto__: null,
    navigator: navigator$1,
    session: session,
    PageRenderer: PageRenderer,
    PageSnapshot: PageSnapshot,
    start: start,
    registerAdapter: registerAdapter,
    visit: visit,
    connectStreamSource: connectStreamSource,
    disconnectStreamSource: disconnectStreamSource,
    renderStreamMessage: renderStreamMessage,
    clearCache: clearCache,
    setProgressBarDelay: setProgressBarDelay,
    setConfirmMethod: setConfirmMethod
});

class FrameController {
    constructor(element) {
        this.fetchResponseLoaded = (fetchResponse) => { };
        this.currentFetchRequest = null;
        this.resolveVisitPromise = () => { };
        this.connected = false;
        this.hasBeenLoaded = false;
        this.settingSourceURL = false;
        this.element = element;
        this.view = new FrameView(this, this.element);
        this.appearanceObserver = new AppearanceObserver(this, this.element);
        this.linkInterceptor = new LinkInterceptor(this, this.element);
        this.formInterceptor = new FormInterceptor(this, this.element);
    }
    connect() {
        if (!this.connected) {
            this.connected = true;
            this.reloadable = false;
            if (this.loadingStyle == FrameLoadingStyle.lazy) {
                this.appearanceObserver.start();
            }
            this.linkInterceptor.start();
            this.formInterceptor.start();
            this.sourceURLChanged();
        }
    }
    disconnect() {
        if (this.connected) {
            this.connected = false;
            this.appearanceObserver.stop();
            this.linkInterceptor.stop();
            this.formInterceptor.stop();
        }
    }
    disabledChanged() {
        if (this.loadingStyle == FrameLoadingStyle.eager) {
            this.loadSourceURL();
        }
    }
    sourceURLChanged() {
        if (this.loadingStyle == FrameLoadingStyle.eager || this.hasBeenLoaded) {
            this.loadSourceURL();
        }
    }
    loadingStyleChanged() {
        if (this.loadingStyle == FrameLoadingStyle.lazy) {
            this.appearanceObserver.start();
        }
        else {
            this.appearanceObserver.stop();
            this.loadSourceURL();
        }
    }
    async loadSourceURL() {
        if (!this.settingSourceURL && this.enabled && this.isActive && (this.reloadable || this.sourceURL != this.currentURL)) {
            const previousURL = this.currentURL;
            this.currentURL = this.sourceURL;
            if (this.sourceURL) {
                try {
                    this.element.loaded = this.visit(expandURL(this.sourceURL));
                    this.appearanceObserver.stop();
                    await this.element.loaded;
                    this.hasBeenLoaded = true;
                }
                catch (error) {
                    this.currentURL = previousURL;
                    throw error;
                }
            }
        }
    }
    async loadResponse(fetchResponse) {
        if (fetchResponse.redirected || (fetchResponse.succeeded && fetchResponse.isHTML)) {
            this.sourceURL = fetchResponse.response.url;
        }
        try {
            const html = await fetchResponse.responseHTML;
            if (html) {
                const { body } = parseHTMLDocument(html);
                const snapshot = new Snapshot(await this.extractForeignFrameElement(body));
                const renderer = new FrameRenderer(this.view.snapshot, snapshot, false, false);
                if (this.view.renderPromise)
                    await this.view.renderPromise;
                await this.view.render(renderer);
                session.frameRendered(fetchResponse, this.element);
                session.frameLoaded(this.element);
                this.fetchResponseLoaded(fetchResponse);
            }
        }
        catch (error) {
            console.error(error);
            this.view.invalidate();
        }
        finally {
            this.fetchResponseLoaded = () => { };
        }
    }
    elementAppearedInViewport(element) {
        this.loadSourceURL();
    }
    shouldInterceptLinkClick(element, url) {
        if (element.hasAttribute("data-turbo-method")) {
            return false;
        }
        else {
            return this.shouldInterceptNavigation(element);
        }
    }
    linkClickIntercepted(element, url) {
        this.reloadable = true;
        this.navigateFrame(element, url);
    }
    shouldInterceptFormSubmission(element, submitter) {
        return this.shouldInterceptNavigation(element, submitter);
    }
    formSubmissionIntercepted(element, submitter) {
        if (this.formSubmission) {
            this.formSubmission.stop();
        }
        this.reloadable = false;
        this.formSubmission = new FormSubmission(this, element, submitter);
        const { fetchRequest } = this.formSubmission;
        this.prepareHeadersForRequest(fetchRequest.headers, fetchRequest);
        this.formSubmission.start();
    }
    prepareHeadersForRequest(headers, request) {
        headers["Turbo-Frame"] = this.id;
    }
    requestStarted(request) {
        markAsBusy(this.element);
    }
    requestPreventedHandlingResponse(request, response) {
        this.resolveVisitPromise();
    }
    async requestSucceededWithResponse(request, response) {
        await this.loadResponse(response);
        this.resolveVisitPromise();
    }
    requestFailedWithResponse(request, response) {
        console.error(response);
        this.resolveVisitPromise();
    }
    requestErrored(request, error) {
        console.error(error);
        this.resolveVisitPromise();
    }
    requestFinished(request) {
        clearBusyState(this.element);
    }
    formSubmissionStarted({ formElement }) {
        markAsBusy(formElement, this.findFrameElement(formElement));
    }
    formSubmissionSucceededWithResponse(formSubmission, response) {
        const frame = this.findFrameElement(formSubmission.formElement, formSubmission.submitter);
        this.proposeVisitIfNavigatedWithAction(frame, formSubmission.formElement, formSubmission.submitter);
        frame.delegate.loadResponse(response);
    }
    formSubmissionFailedWithResponse(formSubmission, fetchResponse) {
        this.element.delegate.loadResponse(fetchResponse);
    }
    formSubmissionErrored(formSubmission, error) {
        console.error(error);
    }
    formSubmissionFinished({ formElement }) {
        clearBusyState(formElement, this.findFrameElement(formElement));
    }
    allowsImmediateRender(snapshot, resume) {
        return true;
    }
    viewRenderedSnapshot(snapshot, isPreview) {
    }
    viewInvalidated() {
    }
    async visit(url) {
        var _a;
        const request = new FetchRequest(this, FetchMethod.get, url, new URLSearchParams, this.element);
        (_a = this.currentFetchRequest) === null || _a === void 0 ? void 0 : _a.cancel();
        this.currentFetchRequest = request;
        return new Promise(resolve => {
            this.resolveVisitPromise = () => {
                this.resolveVisitPromise = () => { };
                this.currentFetchRequest = null;
                resolve();
            };
            request.perform();
        });
    }
    navigateFrame(element, url, submitter) {
        const frame = this.findFrameElement(element, submitter);
        this.proposeVisitIfNavigatedWithAction(frame, element, submitter);
        frame.setAttribute("reloadable", "");
        frame.src = url;
    }
    proposeVisitIfNavigatedWithAction(frame, element, submitter) {
        const action = getAttribute("data-turbo-action", submitter, element, frame);
        if (isAction(action)) {
            const { visitCachedSnapshot } = new SnapshotSubstitution(frame);
            frame.delegate.fetchResponseLoaded = (fetchResponse) => {
                if (frame.src) {
                    const { statusCode, redirected } = fetchResponse;
                    const responseHTML = frame.ownerDocument.documentElement.outerHTML;
                    const response = { statusCode, redirected, responseHTML };
                    session.visit(frame.src, { action, response, visitCachedSnapshot, willRender: false });
                }
            };
        }
    }
    findFrameElement(element, submitter) {
        var _a;
        const id = getAttribute("data-turbo-frame", submitter, element) || this.element.getAttribute("target");
        return (_a = getFrameElementById(id)) !== null && _a !== void 0 ? _a : this.element;
    }
    async extractForeignFrameElement(container) {
        let element;
        const id = CSS.escape(this.id);
        try {
            if (element = activateElement(container.querySelector(`turbo-frame#${id}`), this.currentURL)) {
                return element;
            }
            if (element = activateElement(container.querySelector(`turbo-frame[src][recurse~=${id}]`), this.currentURL)) {
                await element.loaded;
                return await this.extractForeignFrameElement(element);
            }
            console.error(`Response has no matching <turbo-frame id="${id}"> element`);
        }
        catch (error) {
            console.error(error);
        }
        return new FrameElement();
    }
    formActionIsVisitable(form, submitter) {
        const action = getAction(form, submitter);
        return locationIsVisitable(expandURL(action), this.rootLocation);
    }
    shouldInterceptNavigation(element, submitter) {
        const id = getAttribute("data-turbo-frame", submitter, element) || this.element.getAttribute("target");
        if (element instanceof HTMLFormElement && !this.formActionIsVisitable(element, submitter)) {
            return false;
        }
        if (!this.enabled || id == "_top") {
            return false;
        }
        if (id) {
            const frameElement = getFrameElementById(id);
            if (frameElement) {
                return !frameElement.disabled;
            }
        }
        if (!session.elementDriveEnabled(element)) {
            return false;
        }
        if (submitter && !session.elementDriveEnabled(submitter)) {
            return false;
        }
        return true;
    }
    get id() {
        return this.element.id;
    }
    get enabled() {
        return !this.element.disabled;
    }
    get sourceURL() {
        if (this.element.src) {
            return this.element.src;
        }
    }
    get reloadable() {
        const frame = this.findFrameElement(this.element);
        return frame.hasAttribute("reloadable");
    }
    set reloadable(value) {
        const frame = this.findFrameElement(this.element);
        if (value) {
            frame.setAttribute("reloadable", "");
        }
        else {
            frame.removeAttribute("reloadable");
        }
    }
    set sourceURL(sourceURL) {
        this.settingSourceURL = true;
        this.element.src = sourceURL !== null && sourceURL !== void 0 ? sourceURL : null;
        this.currentURL = this.element.src;
        this.settingSourceURL = false;
    }
    get loadingStyle() {
        return this.element.loading;
    }
    get isLoading() {
        return this.formSubmission !== undefined || this.resolveVisitPromise() !== undefined;
    }
    get isActive() {
        return this.element.isActive && this.connected;
    }
    get rootLocation() {
        var _a;
        const meta = this.element.ownerDocument.querySelector(`meta[name="turbo-root"]`);
        const root = (_a = meta === null || meta === void 0 ? void 0 : meta.content) !== null && _a !== void 0 ? _a : "/";
        return expandURL(root);
    }
}
class SnapshotSubstitution {
    constructor(element) {
        this.visitCachedSnapshot = ({ element }) => {
            var _a;
            const { id, clone } = this;
            (_a = element.querySelector("#" + id)) === null || _a === void 0 ? void 0 : _a.replaceWith(clone);
        };
        this.clone = element.cloneNode(true);
        this.id = element.id;
    }
}
function getFrameElementById(id) {
    if (id != null) {
        const element = document.getElementById(id);
        if (element instanceof FrameElement) {
            return element;
        }
    }
}
function activateElement(element, currentURL) {
    if (element) {
        const src = element.getAttribute("src");
        if (src != null && currentURL != null && urlsAreEqual(src, currentURL)) {
            throw new Error(`Matching <turbo-frame id="${element.id}"> element has a source URL which references itself`);
        }
        if (element.ownerDocument !== document) {
            element = document.importNode(element, true);
        }
        if (element instanceof FrameElement) {
            element.connectedCallback();
            element.disconnectedCallback();
            return element;
        }
    }
}

const StreamActions = {
    after() {
        this.targetElements.forEach(e => { var _a; return (_a = e.parentElement) === null || _a === void 0 ? void 0 : _a.insertBefore(this.templateContent, e.nextSibling); });
    },
    append() {
        this.removeDuplicateTargetChildren();
        this.targetElements.forEach(e => e.append(this.templateContent));
    },
    before() {
        this.targetElements.forEach(e => { var _a; return (_a = e.parentElement) === null || _a === void 0 ? void 0 : _a.insertBefore(this.templateContent, e); });
    },
    prepend() {
        this.removeDuplicateTargetChildren();
        this.targetElements.forEach(e => e.prepend(this.templateContent));
    },
    remove() {
        this.targetElements.forEach(e => e.remove());
    },
    replace() {
        this.targetElements.forEach(e => e.replaceWith(this.templateContent));
    },
    update() {
        this.targetElements.forEach(e => {
            e.innerHTML = "";
            e.append(this.templateContent);
        });
    }
};

class StreamElement extends HTMLElement {
    async connectedCallback() {
        try {
            await this.render();
        }
        catch (error) {
            console.error(error);
        }
        finally {
            this.disconnect();
        }
    }
    async render() {
        var _a;
        return (_a = this.renderPromise) !== null && _a !== void 0 ? _a : (this.renderPromise = (async () => {
            if (this.dispatchEvent(this.beforeRenderEvent)) {
                await nextAnimationFrame();
                this.performAction();
            }
        })());
    }
    disconnect() {
        try {
            this.remove();
        }
        catch (_a) { }
    }
    removeDuplicateTargetChildren() {
        this.duplicateChildren.forEach(c => c.remove());
    }
    get duplicateChildren() {
        var _a;
        const existingChildren = this.targetElements.flatMap(e => [...e.children]).filter(c => !!c.id);
        const newChildrenIds = [...(_a = this.templateContent) === null || _a === void 0 ? void 0 : _a.children].filter(c => !!c.id).map(c => c.id);
        return existingChildren.filter(c => newChildrenIds.includes(c.id));
    }
    get performAction() {
        if (this.action) {
            const actionFunction = StreamActions[this.action];
            if (actionFunction) {
                return actionFunction;
            }
            this.raise("unknown action");
        }
        this.raise("action attribute is missing");
    }
    get targetElements() {
        if (this.target) {
            return this.targetElementsById;
        }
        else if (this.targets) {
            return this.targetElementsByQuery;
        }
        else {
            this.raise("target or targets attribute is missing");
        }
    }
    get templateContent() {
        return this.templateElement.content.cloneNode(true);
    }
    get templateElement() {
        if (this.firstElementChild instanceof HTMLTemplateElement) {
            return this.firstElementChild;
        }
        this.raise("first child element must be a <template> element");
    }
    get action() {
        return this.getAttribute("action");
    }
    get target() {
        return this.getAttribute("target");
    }
    get targets() {
        return this.getAttribute("targets");
    }
    raise(message) {
        throw new Error(`${this.description}: ${message}`);
    }
    get description() {
        var _a, _b;
        return (_b = ((_a = this.outerHTML.match(/<[^>]+>/)) !== null && _a !== void 0 ? _a : [])[0]) !== null && _b !== void 0 ? _b : "<turbo-stream>";
    }
    get beforeRenderEvent() {
        return new CustomEvent("turbo:before-stream-render", { bubbles: true, cancelable: true });
    }
    get targetElementsById() {
        var _a;
        const element = (_a = this.ownerDocument) === null || _a === void 0 ? void 0 : _a.getElementById(this.target);
        if (element !== null) {
            return [element];
        }
        else {
            return [];
        }
    }
    get targetElementsByQuery() {
        var _a;
        const elements = (_a = this.ownerDocument) === null || _a === void 0 ? void 0 : _a.querySelectorAll(this.targets);
        if (elements.length !== 0) {
            return Array.prototype.slice.call(elements);
        }
        else {
            return [];
        }
    }
}

FrameElement.delegateConstructor = FrameController;
customElements.define("turbo-frame", FrameElement);
customElements.define("turbo-stream", StreamElement);

(() => {
    let element = document.currentScript;
    if (!element)
        return;
    if (element.hasAttribute("data-turbo-suppress-warning"))
        return;
    while (element = element.parentElement) {
        if (element == document.body) {
            return console.warn(unindent `
        You are loading Turbo from a <script> element inside the <body> element. This is probably not what you meant to do!

        Load your application’s JavaScript bundle inside the <head> element instead. <script> elements in <body> are evaluated with each page change.

        For more information, see: https://turbo.hotwired.dev/handbook/building#working-with-script-elements

        ——
        Suppress this warning by adding a "data-turbo-suppress-warning" attribute to: %s
      `, element.outerHTML);
        }
    }
})();

window.Turbo = Turbo;
start();




/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
(() => {
"use strict";
var __webpack_exports__ = {};
/*!**************************************!*\
  !*** ./resources/assets/js/turbo.js ***!
  \**************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _hotwired_turbo__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @hotwired/turbo */ "./node_modules/@hotwired/turbo/dist/turbo.es2017-esm.js");

window.Turbo = _hotwired_turbo__WEBPACK_IMPORTED_MODULE_0__;
_hotwired_turbo__WEBPACK_IMPORTED_MODULE_0__.start();
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (_hotwired_turbo__WEBPACK_IMPORTED_MODULE_0__);
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************!*\
  !*** ./resources/assets/js/custom/helper.js ***!
  \**********************************************/
window.listen = function (event, selector, callback) {
  $(document).on(event, selector, callback);
};

window.listenClick = function (selector, callback) {
  $(document).on('click', selector, callback);
};

window.listenSubmit = function (selector, callback) {
  $(document).on('submit', selector, callback);
};

window.listenChange = function (selector, callback) {
  $(document).on('change', selector, callback);
};

window.listenKeyup = function (selector, callback) {
  $(document).on('keyup', selector, callback);
};

window.listenHiddenBsModal = function (selector, callback) {
  $(document).on('hidden.bs.modal', selector, callback);
};
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************!*\
  !*** ./resources/assets/js/custom/custom.js ***!
  \**********************************************/
document.addEventListener('turbo:load', loadCustomData);
var source = null;
document.addEventListener('turbo:load', initAllComponents);

function initAllComponents() {
  refreshCsrfToken();
  alertInitialize();
  modalInputFocus();
  inputFocus();
  tooltip();
}

function alertInitialize() {
  $('.alert').delay(5000).slideUp(300);
}

function refreshCsrfToken() {
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
}

function tooltip() {
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
}

var inputFocus = function inputFocus() {
  $('input:text:not([readonly="readonly"]):not([name="search"])').first().focus();
};

var modalInputFocus = function modalInputFocus() {
  $(function () {
    $('.modal').on('shown.bs.modal', function () {
      if ($(this).find('input:text')[0]) {
        $(this).find('input:text')[0].focus();
      }
    });
  });
};

window.hideDropdownManually = function (dropdownBtnEle) {
  dropdownBtnEle.removeClass('show');
};

function loadCustomData() {
  // script to active parent menu if sub menu has currently active
  var hasActiveMenu = $(document).find('.nav-item.dropdown ul li').hasClass('active');

  if (hasActiveMenu) {
    $(document).find('.nav-item.dropdown ul li.active').parent('ul').css('display', 'block');
    $(document).find('.nav-item.dropdown ul li.active').parent('ul').parent('li').addClass('active');
  }

  var timezone_offset_minutes = new Date().getTimezoneOffset();
  timezone_offset_minutes = timezone_offset_minutes === 0 ? 0 : -timezone_offset_minutes;
  document.cookie = 'timezone_offset_minutes=' + timezone_offset_minutes;
}

listen('select2:open', function () {
  var allFound = document.querySelectorAll('.select2-container--open .select2-search__field');
  allFound[allFound.length - 1].focus();
});
listen('focus', '.select2.select2-container', function (e) {
  var isOriginalEvent = e.originalEvent; // don't re-open on closing focus event

  var isSingleSelect = $(this).find('.select2-selection--single').length > 0; // multi-select will pass focus to input

  if (isOriginalEvent && isSingleSelect) {
    $(this).siblings('select:enabled').select2('open');
  }
});
toastr.options = {
  "closeButton": true,
  "debug": false,
  "newestOnTop": false,
  "progressBar": true,
  "positionClass": "toast-top-right",
  "preventDuplicates": false,
  "onclick": null,
  "showDuration": "300",
  "hideDuration": "1000",
  "timeOut": "5000",
  "extendedTimeOut": "1000",
  "showEasing": "swing",
  "hideEasing": "linear",
  "showMethod": "fadeIn",
  "hideMethod": "fadeOut"
};

window.resetModalForm = function (formId, validationBox) {
  $(formId)[0].reset();
  $('select.select2Selector').each(function (index, element) {
    var drpSelector = '#' + $(this).attr('id');
    $(drpSelector).val('');
    $(drpSelector).trigger('change');
  });
  $(validationBox).hide();
};

window.printErrorMessage = function (selector, errorResult) {
  $(selector).show().html('');
  $(selector).text(errorResult.responseJSON.message);
};

window.manageAjaxErrors = function (data) {
  var errorDivId = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'editValidationErrorsBox';

  if (data.status == 404) {
    toastr.error(data.responseJSON.message);
  } else {
    printErrorMessage('#' + errorDivId, data);
  }
};

window.displaySuccessMessage = function (message) {
  toastr.success(message, Lang.get('js.successful'));
};

window.displayErrorMessage = function (message) {
  toastr.error(message, Lang.get('js.something_went_wrong'));
};

window.deleteItem = function (url, header) {
  var callFunction = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : null;
  swal({
    title: Lang.get('js.delete'),
    text: Lang.get('js.sure_delete') + ' "' + header + '"  ?',
    buttons: {
      confirm: Lang.get('js.yes'),
      cancel: Lang.get('js.no')
    },
    icon: sweetAlertIcon,
    reverseButtons: true
  }).then(function (willDelete) {
    if (willDelete) {
      deleteItemAjax(url, header, callFunction);
    }
  });
};

function deleteItemAjax(url, header) {
  var callFunction = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
  $.ajax({
    url: url,
    type: 'DELETE',
    dataType: 'json',
    success: function success(obj) {
      if (obj.success) {
        window.livewire.emit('refresh');
        window.livewire.emit('resetPageTable');
      }

      swal({
        icon: 'success',
        confirmButtonColor: '#ADB5BD',
        title: deleteMsg + ' !',
        text: header + ' ' + hasBeenDeleted,
        buttons: {
          confirm: Lang.get("js.ok")
        },
        timer: 2000
      });

      if (callFunction) {
        eval(callFunction);
      }
    },
    error: function error(data) {
      swal({
        title: 'Error',
        icon: 'error',
        text: data.responseJSON.message,
        type: 'error',
        timer: 4000
      });
    }
  });
}

window.format = function (dateTime) {
  var format = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'DD-MMM-YYYY';
  return moment(dateTime).format(format);
};

window.processingBtn = function (selecter, btnId) {
  var state = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : null;
  var loadingButton = $(selecter).find(btnId);

  if (state === 'loading') {
    loadingButton.button('loading');
  } else {
    loadingButton.button('reset');
  }
};

window.setBtnLoader = function (btnLoader) {
  if (btnLoader.attr('data-old-text')) {
    btnLoader.html(btnLoader.attr('data-old-text')).prop('disabled', false);
    btnLoader.removeAttr('data-old-text');
    return;
  }

  btnLoader.attr('data-old-text', btnLoader.text());
  btnLoader.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').prop('disabled', true);
};

window.prepareTemplateRender = function (templateSelector, data) {
  var template = jsrender.templates(templateSelector);
  return template.render(data);
};

window.isValidFile = function (inputSelector, validationMessageSelector) {
  var ext = $(inputSelector).val().split('.').pop().toLowerCase();

  if ($.inArray(ext, ['gif', 'png', 'jpg', 'jpeg']) == -1) {
    $(inputSelector).val('');
    $(validationMessageSelector).removeClass('d-none');
    $(validationMessageSelector).html('The image must be a file of type: jpeg, jpg, png.').show();
    $(validationMessageSelector).delay(5000).slideUp(300);
    return false;
  }

  $(validationMessageSelector).hide();
  return true;
};

window.displayPhoto = function (input, selector) {
  var displayPreview = true;

  if (input.files && input.files[0]) {
    var reader = new FileReader();

    reader.onload = function (e) {
      var image = new Image();
      image.src = e.target.result;

      image.onload = function () {
        $(selector).attr('src', e.target.result);
        displayPreview = true;
      };
    };

    if (displayPreview) {
      reader.readAsDataURL(input.files[0]);
      $(selector).show();
    }
  }
};

window.removeCommas = function (str) {
  return str.replace(/,/g, '');
};

window.DatetimepickerDefaults = function (opts) {
  return $.extend({}, {
    sideBySide: true,
    ignoreReadonly: true,
    icons: {
      close: 'fa fa-times',
      time: 'fa fa-clock-o',
      date: 'fa fa-calendar',
      up: 'fa fa-arrow-up',
      down: 'fa fa-arrow-down',
      previous: 'fa fa-chevron-left',
      next: 'fa fa-chevron-right',
      today: 'fa fa-clock-o',
      clear: 'fa fa-trash-o'
    }
  }, opts);
};

window.isEmpty = function (value) {
  return value === undefined || value === null || value === '';
};

window.urlValidation = function (value, regex) {
  var urlCheck = value == '' ? true : value.match(regex) ? true : false;

  if (!urlCheck) {
    return false;
  }

  return true;
};

if ($(window).width() > 992) {
  $('.no-hover').on('click', function () {
    $(this).toggleClass('open');
  });
}

window.preparedTemplate = function () {
  source = $('#actionTemplate').html();
  window.preparedTemplate = Handlebars.compile(source);
};

window.ajaxCallInProgress = function () {
  ajaxCallIsRunning = true;
};

window.ajaxCallCompleted = function () {
  ajaxCallIsRunning = false;
};

window.avoidSpace = function (event) {
  var k = event ? event.which : window.event.keyCode;

  if (k == 32) {
    return false;
  }
};

$('input[type=radio][name=gender]').on('change', function () {
  var file = $('#profilePicture').val();

  if (isEmpty(file)) {
    if (this.value == 1) {
      $('.image-input-wrapper').attr('style', 'background-image:url(' + manAvatar + ')');
    } else if (this.value == 2) {
      $('.image-input-wrapper').attr('style', 'background-image:url(' + womanAvatar + ')');
    }
  }
});

window.setBtnLoader = function (btnLoader) {
  if (btnLoader.attr('data-old-text')) {
    btnLoader.html(btnLoader.attr('data-old-text')).prop('disabled', false);
    btnLoader.removeAttr('data-old-text');
    return;
  }

  btnLoader.attr('data-old-text', btnLoader.text());
  btnLoader.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>').prop('disabled', true);
};

window.addCommas = function (nStr) {
  nStr += '';
  var x = nStr.split('.');
  var x1 = x[0];
  var x2 = x.length > 1 ? '.' + x[1] : '';
  var rgx = /(\d+)(\d{3})/;

  while (rgx.test(x1)) {
    x1 = x1.replace(rgx, '$1' + ',' + '$2');
  }

  return x1 + x2;
};

window.getFormattedPrice = function (price) {
  if (price != '' || price > 0) {
    if (typeof price !== 'number') {
      price = price.replace(/,/g, '');
    }

    return addCommas(price);
  }
};

listenClick('.change-type', function (e) {
  var inputField = $(this).siblings();
  var oldType = inputField.attr('type');
  var type = !isEmpty(oldType) ? oldType : 'password';

  if (type == 'password') {
    $(this).children().addClass('fa-eye');
    $(this).children().removeClass('fa-eye-slash');
    inputField.attr('type', 'text');
  } else {
    $(this).children().removeClass('fa-eye');
    $(this).children().addClass('fa-eye-slash');
    inputField.attr('type', 'password');
  }
});
$('.dropdown-menu a').on('click', function () {
  $(this).closest('.dropdown-menu').prev().dropdown('toggle');
}); // cancel schedule event modal code

listenClick('.cancel-scheduled-event', function () {
  var scheduledEventId = $(this).attr('data-id');
  $('#scheduleEventId').val(scheduledEventId);
  $('#cancelScheduleEventModal').modal('show').appendTo('body');
}); // cancel schedule event code

listenSubmit('#cancelScheduleEventForm', function (e) {
  e.preventDefault();

  if (isEmpty($('#cancelReason').val())) {
    displayErrorMessage('Cancel reason field is required.');
    return false;
  }

  var scheduledEventId = $('#scheduleEventId').val();
  $.ajax({
    url: route('cancel.scheduled.event', scheduledEventId),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        window.livewire.emit('refresh');
        $('#cancelScheduleEventModal').modal('hide');
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    }
  });
}); // cancel modal reset data code

listenHiddenBsModal('#cancelScheduleEventModal', function () {
  resetModalForm('#cancelScheduleEventForm', '#cancelValidationErrorsBox');
});
listenClick('.copy-google-meet-link', function () {
  var $temp = $('<input>');
  $('body').append($temp);
  $temp.val($(this).attr('data-link')).select();
  document.execCommand('copy');
  $temp.remove();
  $(this).children().css('color', '#8BC34A');
  $(this).children().removeClass('fa-copy');
  $(this).children().addClass('fa-check');
  displaySuccessMessage(Lang.get('js.linked_copy_successfully'));
  setTimeout(function () {
    $('.copy-google-meet-link').children().removeClass('fa-check');
    $('.copy-google-meet-link').children().addClass('fa-copy');
    $('.copy-google-meet-link').children().css('color', '#009ef7');
  }, 2000);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*******************************************!*\
  !*** ./resources/assets/js/users/user.js ***!
  \*******************************************/
document.addEventListener('turbo:load', loadUserData);

function loadUserData() {
  if (!$('#personalExperiences').length) {
    return;
  }

  $('#personalExperiences').select2({
    width: '100%',
    placeholder: Lang.get('js.select_personal_experience')
  });
} // reset filter modal code


listenClick('#resetFilter', function () {
  window.livewire.emit('refresh');
  $('#personalExperiences').val('').trigger('change');
  $('#personalExpFilterBtn').dropdown('toggle');
}); // user record delete code

listenClick('.user-delete-btn', function () {
  var deleteUserId = $(this).attr('data-id');
  deleteItem(route('users.destroy', deleteUserId), Lang.get('js.user_details'));
}); // admin record delete code

listenClick('.admin-delete-btn', function () {
  var deleteAdminId = $(this).attr('data-id');
  deleteItem(route('admins.destroy', deleteAdminId), Lang.get('js.admin'));
}); //  call to filter data code

listenChange('#personalExperiences', function () {
  window.livewire.emit('changeFilter', $(this).val());
  hideDropdownManually($('#userFilterBtn'));
});
listen('contextmenu', '.user-impersonate', function (e) {
  e.preventDefault(); // Stop right click on link

  return false;
});
var control = false;
listen('keyup keydown', function (e) {
  control = e.ctrlKey;
});
listenClick('.user-impersonate', function () {
  if (control) {
    return false; // Stop ctrl + click on link
  }

  var id = $(this).data('id');
  var element = document.createElement('a');
  element.setAttribute('href', route('impersonate', id));
  element.setAttribute('data-turbo', false);
  document.body.appendChild(element);
  element.click();
  document.body.removeChild(element);
  $('.user-impersonate').prop('disabled', true);
}); // Call Email verify JS code for user

listenClick('.user-email-verify', function () {
  var userId = $(this).attr('data-id');
  window.livewire.emit('userEmailVerify', userId);
}); // Call Email verify JS code for admin

listenClick('.admin-email-verify', function () {
  var adminId = $(this).attr('data-id');
  window.livewire.emit('adminEmailVerify', adminId);
});
document.addEventListener('email-verify-success', function () {
  displaySuccessMessage(Lang.get('js.email_verified_successfully'));
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*******************************************************!*\
  !*** ./resources/assets/js/users/user-create-edit.js ***!
  \*******************************************************/
document.addEventListener('turbo:load', loadUserCreateEditData);

function loadUserCreateEditData() {
  if (!$('#personalExperienceId').length) {
    return;
  }

  $('#personalExperienceId').select2({
    width: '100%',
    placeholder: Lang.get('js.select_personal_experience')
  });
}

listenSubmit('#createUserForm', function () {
  if ($('#error-msg').text() !== '') {
    $('#phoneNumber').focus();
    displayErrorMessage(Lang.get('js.contact_number_is_invalid_number'));
    return false;
  }

  $('#createUserSaveBtn').attr('disabled', true);
});
listenSubmit('#editUserForm', function () {
  if ($('#error-msg').text() !== '') {
    $('#phoneNumber').focus();
    displayErrorMessage("Contact number is " + $('#error-msg').text());
    return false;
  }

  $('#createUserSaveBtn').attr('disabled', true);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***************************************************!*\
  !*** ./resources/assets/js/users/user-profile.js ***!
  \***************************************************/
document.addEventListener('turbo:load', loadUserProfileData);

function loadUserProfileData() {
  if (!$('.select-language').length) {
    return;
  }

  $('.select-language').select2({
    dropdownParent: $('#changeLanguageModal')
  });

  if (!$('#userTimeZoneId').length) {
    return;
  }

  $('#userTimeZoneId').select2();
}

listenClick('#changePassword', function () {
  $('#changePasswordModal').modal('show').appendTo('body');
});
listenClick('#changeLanguage', function () {
  $('#changeLanguageModal').modal('show').appendTo('body');
});
listenSubmit('#changeLanguageForm', function (event) {
  event.preventDefault();
  var loadingButton = jQuery(this).find('#languageChangeBtn');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  $.ajax({
    url: route('update-language'),
    type: 'PUT',
    data: $('#changeLanguageForm').serialize(),
    success: function success(result) {
      $('#changeLanguageModal').modal('hide');
      $(loadingButton).attr('disabled', false);
      displaySuccessMessage(result.message);
      setTimeout(function () {
        location.reload();
      }, 1000);
      $('#selectLanguage').trigger('change');
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $(loadingButton).attr('disabled', false);
    }
  });
});
listenClick('#passwordChangeBtn', function () {
  var loadingButton = jQuery(this).find('#passwordChangeBtn');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  $.ajax({
    url: route('user.changePassword'),
    type: 'PUT',
    data: $('#changePasswordForm').serialize(),
    success: function success(result) {
      $('#changePasswordModal').modal('hide');
      $(loadingButton).attr('disabled', false);
      displaySuccessMessage(result.message);
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $(loadingButton).attr('disabled', false);
    }
  });
});

window.printErrorMessage = function (selector, errorResult) {
  $(selector).show().html('');
  $(selector).text(errorResult.responseJSON.message);
};

listenClick('.changeLanguage', function () {
  var languageName = $(this).data('prefix-value');
  $.ajax({
    type: 'POST',
    url: updateLanguageURL,
    data: {
      languageName: languageName
    },
    success: function success(result) {
      displaySuccessMessage(result.message);
      setTimeout(function () {
        location.reload();
      }, 1000);
    }
  });
});
listenSubmit('#profileId', function () {
  if ($('#error-msg').text() !== '') {
    $('#phoneNumber').focus();
    displayErrorMessage("Contact number is " + $('#error-msg').text());
    return false;
  }

  $('#profileSaveBtn').attr('disabled', true);
}); // change password modal reset code

listenHiddenBsModal('#changePasswordModal', function () {
  resetModalForm('#changePasswordForm', '#editPasswordValidationErrorsBox');
});
listenClick('#emailNotification', function () {
  $('#emailNotificationModal').modal('show').appendTo('body');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*************************************************************************!*\
  !*** ./resources/assets/js/personal_experiences/personal-experience.js ***!
  \*************************************************************************/
// open personal experience modal code
listenClick('.add-personal-experience', function () {
  $('#createPersonalExperienceModal').modal('show').appendTo('body');
}); // edit personal experience modal code

listenClick('.personal-exp-edit-btn', function () {
  var editPersonalExpId = $(this).attr('data-id');
  renderData(editPersonalExpId);
}); // render personal experience  code

function renderData(id) {
  $.ajax({
    url: route('personal-experiences.edit', id),
    type: 'GET',
    success: function success(result) {
      $('#personalExperienceID').val(result.data.id);
      $('#editName').val(result.data.name);
      $('#editPersonalExperienceModal').modal('show');
    }
  });
} // add personal experience modal code


listenSubmit('#createPersonalExperienceForm', function (e) {
  e.preventDefault();
  var loadingButton = $(this).find('#btnSave');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  $.ajax({
    url: route('personal-experiences.store'),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        $('#createPersonalExperienceModal').modal('hide');
        livewire.emit('refresh');
        $(loadingButton).attr('disabled', false);
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $(loadingButton).attr('disabled', false);
    }
  });
}); // update personal experience modal code

listenSubmit('#editPersonalExperienceForm', function (e) {
  e.preventDefault();
  var loadingButton = $(this).find('#editBtnSave');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  var id = $('#personalExperienceID').val();
  $.ajax({
    url: route('personal-experiences.update', id),
    type: 'PUT',
    data: $(this).serialize(),
    success: function success(result) {
      $('#editPersonalExperienceModal').modal('hide');
      $(loadingButton).attr('disabled', false);
      displaySuccessMessage(result.message);
      livewire.emit('refresh');
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $(loadingButton).attr('disabled', false);
    }
  });
}); // delete personal experience record code

listenClick('.personal-exp-delete-btn', function () {
  var personalExperienceId = $(this).attr('data-id');
  deleteItem(route('personal-experiences.destroy', personalExperienceId), Lang.get('js.personal_experience'));
}); // reset personal experience modal code

listenHiddenBsModal('#createPersonalExperienceModal', function () {
  resetModalForm('#createPersonalExperienceForm', '#createPersonalExperienceValidationErrorsBox');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*****************************************************************!*\
  !*** ./resources/assets/js/custom/phone-number-country-code.js ***!
  \*****************************************************************/
document.addEventListener('turbo:load', loadPhoneNumberCountryCodeData);

function loadPhoneNumberCountryCodeData() {
  loadPhoneNumberCountryCode();
}

function loadPhoneNumberCountryCode() {
  if (!$('#phoneNumber').length) {
    return false;
  }

  var input = document.querySelector('#phoneNumber'),
      errorMsg = document.querySelector('#error-msg'),
      validMsg = document.querySelector('#valid-msg');

  if ($('#valid-msg').length > 0) {
    setTimeout(function () {
      $('#valid-msg').addClass('d-none');
    }, 10);
  }

  var errorMap = [Lang.get('js.invalid_number'), Lang.get('js.invalid_country_code'), Lang.get('js.too_short'), Lang.get('js.too_long'), Lang.get('js.invalid_number')]; // initialise plugin

  var intl = window.intlTelInput(input, {
    initialCountry: defaultCountryCodeValue,
    separateDialCode: true,
    preferredCountries: false,
    geoIpLookup: function geoIpLookup(success, failure) {
      $.get('https://ipinfo.io', function () {}, 'jsonp').always(function (resp) {
        var countryCode = resp && resp.country ? resp.country : '';
        success(countryCode);
      });
    },
    utilsScript: '../../public/assets/js/inttel/js/utils.min.js'
  });

  var reset = function reset() {
    input.classList.remove('error');
    errorMsg.innerHTML = '';
    errorMsg.classList.add('d-none');
    validMsg.classList.add('d-none');
  };

  input.addEventListener('blur', function () {
    reset();

    if (input.value.trim()) {
      if (intl.isValidNumber()) {
        validMsg.classList.remove('d-none');
      } else {
        input.classList.add('error');
        var errorCode = intl.getValidationError();
        errorMsg.innerHTML = errorMap[errorCode];
        errorMsg.classList.remove('d-none');
      }
    }
  }); // on keyup / change flag: reset

  input.addEventListener('change', reset);
  input.addEventListener('keyup', reset);

  if (typeof phoneNo != 'undefined' && phoneNo !== '') {
    setTimeout(function () {
      $('#phoneNumber').trigger('change');
    }, 500);
  }

  $('#phoneNumber').on('blur keyup change countrychange', function () {
    if (typeof phoneNo != 'undefined' && phoneNo !== '') {
      intl.setNumber('+' + phoneNo);
      phoneNo = '';
    }

    var getCode = intl.selectedCountryData['dialCode'];
    $('#prefix_code').val(getCode);
  });
  var getCode = intl.selectedCountryData['dialCode'];
  $('#prefix_code').val(getCode);
  var getPhoneNumber = $('#phoneNumber').val();
  var removeSpacePhoneNumber = getPhoneNumber.replace(/\s/g, '');
  $('#phoneNumber').val(removeSpacePhoneNumber);
  $('#phoneNumber').focus();
  $('#phoneNumber').trigger('blur');
}

listenClick('.iti__country', function () {
  var flagClass = $('.iti__selected-flag>.iti__flag').attr('class');
  flagClass = flagClass.split(/\s+/)[1];
  var dialCodeVal = $('.iti__selected-dial-code').text();
  window.localStorage.setItem('flagClassLocal', flagClass);
  window.localStorage.setItem('dialCodeValLocal', dialCodeVal);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************!*\
  !*** ./resources/assets/js/events/events.js ***!
  \**********************************************/
// copy slot time code
listenClick(".copy-link", function () {
  var $temp = $("<input>");
  $("body").append($temp);
  $temp.val($(this).attr("data-link")).select();
  document.execCommand("copy");
  $temp.remove();
  $(this).text(Lang.get("js.copied"));
  $(this).prev().css("color", "#8BC34A");
  $(this).prev().removeClass("fa-copy");
  $(this).prev().addClass("fa-check");
  displaySuccessMessage(Lang.get("js.linked_copy_successfully"));
  setTimeout(function () {
    $(".copy-link").text(Lang.get("js.copy_link"));
    $(".copy-link").prev().removeClass("fa-check");
    $(".copy-link").prev().addClass("fa-copy");
    $(".fa-copy").css("color", "#009ef7");
  }, 2000);
}); // event delete record code

listenClick(".event-delete-btn", function () {
  var deleteEventId = $(this).attr("data-id");
  deleteItemLivewire("delete", deleteEventId, Lang.get("js.event"));
});

window.deleteItemLivewire = function (model, id, header) {
  swal({
    title: Lang.get("js.delete") + " !",
    text: Lang.get("js.sure_delete") + ' "' + header + '"  ?',
    buttons: {
      confirm: Lang.get("js.yes"),
      cancel: Lang.get("js.no")
    },
    icon: sweetAlertIcon,
    reverseButtons: true
  }).then(function (willDelete) {
    if (willDelete) {
      window.livewire.emit(model, id);
    }
  });
};

window.addEventListener("event-error", function (event) {
  swal({
    title: "Error!",
    text: Lang.get("js.this_event_can_not_be_deleted"),
    type: "error",
    confirmButtonColor: "#ADB5BD",
    timer: 2000
  });
});
window.addEventListener("deleted", function (data) {
  livewireDeleteEventListener(data, "Event");
});

window.livewireDeleteEventListener = function () {
  swal({
    icon: "success",
    confirmButtonColor: "#ADB5BD",
    title: deleteMsg + " !",
    text: Lang.get("js.event") + " " + hasBeenDeleted,
    buttons: {
      confirm: Lang.get("js.ok")
    },
    timer: 2000
  });
};

window.livewireDeleteErrorEventListener = function (data) {
  swal({
    title: "Error!",
    text: data,
    type: "error",
    confirmButtonColor: "#ADB5BD",
    timer: 2000
  });
}; // activation deactivation change event


listenChange(".event-status", function () {
  var eventId = $(this).attr("data-id");
  activeDeActiveStatus(eventId);
}); // activate de-activate Event status

window.activeDeActiveStatus = function (id) {
  $.ajax({
    url: route("change.event.status", id),
    type: "POST",
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        window.livewire.emit("refresh");
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      setTimeout(location.reload(true), 700);
    }
  });
};
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***************************************************!*\
  !*** ./resources/assets/js/events/create-edit.js ***!
  \***************************************************/
document.addEventListener("turbo:load", loadEventData);
var dateRange;
var eventType;
var afterGap;
var eventColor;

function loadEventData() {
  dateRange = $("#dateRangeEdit").val();
  eventType = $("#eventTypeEdit").val();
  afterGap = $("#afterGapEdit").val();
  eventColor = $("#colorEdit").val();

  if (currentRouteName == "events.edit") {
    $('select[name^="from_time"]').each(function () {
      var selectedIndex = $(this)[0].selectedIndex;
      var endSelectedIndex = $(this).closest(".add-slot").find('select[name^="to_time"] option:selected')[0].index;
      var endTimeOptions = $(this).closest(".add-slot").find('select[name^="to_time"] option');

      if (selectedIndex >= endSelectedIndex) {
        endTimeOptions.eq(selectedIndex + 1).prop("selected", true).trigger("change");
      }

      endTimeOptions.each(function (index) {
        if (index <= selectedIndex) {
          $(this).attr("disabled", true);
        } else {
          $(this).attr("disabled", false);
        }
      });
    });
    $('select[name^="to_time"]').each(function () {
      var selectedIndex = $(this)[0].selectedIndex;
      var startTimeOptions = $(this).closest(".timeSlot").next().find('select[name^="from_time"] option');
      startTimeOptions.each(function (index) {
        if (index <= selectedIndex) {
          $(this).attr("disabled", true);
        } else {
          $(this).attr("disabled", false);
        }
      });
    });

    if (!$("#slotTimeId").length || !$(".startTimeSlot").length || !$(".endTimeSlot").length || !$("#withinDateRangeId").length || !$("#afterEventTimeId").length || !$("#eventTimeZoneId").length || !$("#eventScheduleId").length) {
      return;
    }

    $("#slotTimeId").select2({
      width: "100%"
    });
    $("#eventTimeZoneId").select2({
      width: "100%"
    });
    $("#eventScheduleId").select2({
      width: "100%"
    });
    $(".startTimeSlot").select2({
      width: "100%"
    });
    $(".endTimeSlot").select2({
      width: "100%"
    });
    $("#afterEventTimeId").select2();
    $("#withinDateRangeId").daterangepicker({
      minDate: new Date(),
      locale: {
        applyLabel: Lang.get("js.apply"),
        cancelLabel: Lang.get("js.cancel"),
        fromLabel: Lang.get("js.from"),
        toLabel: Lang.get("js.to"),
        monthNames: [Lang.get("js.jan"), Lang.get("js.feb"), Lang.get("js.mar"), Lang.get("js.apr"), Lang.get("js.may"), Lang.get("js.jun"), Lang.get("js.jul"), Lang.get("js.aug"), Lang.get("js.sep"), Lang.get("js.oct"), Lang.get("js.nov"), Lang.get("js.dec")],
        daysOfWeek: [Lang.get("js.sun"), Lang.get("js.mon"), Lang.get("js.tue"), Lang.get("js.wed"), Lang.get("js.thu"), Lang.get("js.fri"), Lang.get("js.sat")]
      }
    });

    if (dateRange == 0) {
      $("#withinDateRangeId").removeClass("d-none");
      $("#withinDateRangeId").prop("disabled", false);
      $("#scheduleDayId").prop("disabled", true);
    } else {
      $("#withinDateRangeId").addClass("d-none");
      $("#scheduleDayId").prop("disabled", false);
    }

    if (afterGap != "") {
      $(".after-time").prop("checked", true);
      $("#afterEventTimeId").prop("disabled", false);
    } else {
      $(".after-time").prop("checked", false);
      $("#afterEventTimeId").prop("disabled", true);
    }

    if (eventType == 2) {
      $("#payableAmount").removeClass("d-none");
      $("#payableAmountId").prop("disabled", false);
    }
  }

  if (!$(".event-location").length || !$("#paymentTypeId").length || !$(".add-location").length) {
    return;
  }

  $(".event-location").select2({
    placeholder: Lang.get("js.add_location")
  });
  $("#paymentTypeId").select2({
    placeholder: Lang.get("js.select_event_type")
  });
  $(".add-location").select2();

  if (currentRouteName == "events.create") {
    var pickr = Pickr.create({
      el: ".color-wrapper",
      theme: "nano",
      // or 'monolith', or 'nano'
      closeWithKey: "Enter",
      autoReposition: true,
      defaultRepresentation: "HEX",
      position: "bottom-end",
      swatches: ["rgba(244, 67, 54, 1)", "rgba(233, 30, 99, 1)", "rgba(156, 39, 176, 1)", "rgba(103, 58, 183, 1)", "rgba(63, 81, 181, 1)", "rgba(33, 150, 243, 1)", "rgba(3, 169, 244, 1)", "rgba(0, 188, 212, 1)", "rgba(0, 150, 136, 1)", "rgba(76, 175, 80, 1)", "rgba(139, 195, 74, 1)", "rgba(205, 220, 57, 1)", "rgba(255, 235, 59, 1)", "rgba(255, 193, 7, 1)"],
      components: {
        // Main components
        preview: true,
        hue: true,
        // Input / output Options
        interaction: {
          input: true,
          clear: false,
          save: false
        }
      }
    });
    pickr.on("change", function () {
      var color = pickr.getColor().toHEXA().toString();

      if (wc_hex_is_light(color)) {
        $("#validationErrorsBoxForColor").removeClass("d-none").text("Pick a different color");
        $(':input[id="btnSave"]').prop("disabled", true);
        return;
      }

      $("#validationErrorsBoxForColor").addClass("d-none");
      $(':input[id="btnSave"]').prop("disabled", false);
      pickr.setColor(color);
      $("#color").val(color);
    });
    $(document).ready(function () {
      pickr.setColor("#d6b71b");
      $("#color").val("#d6b71b");
    });
  }

  if (currentRouteName == "events.edit") {
    var editPickr = Pickr.create({
      el: ".color-wrapper-edit",
      theme: "nano",
      // or 'monolith', or 'nano'
      closeWithKey: "Enter",
      autoReposition: true,
      defaultRepresentation: "HEX",
      position: "bottom-end",
      swatches: ["rgba(244, 67, 54, 1)", "rgba(233, 30, 99, 1)", "rgba(156, 39, 176, 1)", "rgba(103, 58, 183, 1)", "rgba(63, 81, 181, 1)", "rgba(33, 150, 243, 1)", "rgba(3, 169, 244, 1)", "rgba(0, 188, 212, 1)", "rgba(0, 150, 136, 1)", "rgba(76, 175, 80, 1)", "rgba(139, 195, 74, 1)", "rgba(205, 220, 57, 1)", "rgba(255, 235, 59, 1)", "rgba(255, 193, 7, 1)"],
      components: {
        // Main components
        preview: true,
        hue: true,
        // Input / output Options
        interaction: {
          input: true,
          clear: false,
          save: false
        }
      }
    });
    setTimeout(function () {
      editPickr.setColor(eventColor);
    }, 10);
    $("#editEventColor").val(eventColor);
    editPickr.on("change", function () {
      var editColor = editPickr.getColor().toHEXA().toString();

      if (wc_hex_is_light(editColor)) {
        $("#editValidationErrorsBoxForColor").addClass("d-block").text("Pick a different color");
        $(':input[id="btnEditSave"]').prop("disabled", true);
        return;
      }

      $("#editValidationErrorsBoxForColor").removeClass("d-block");
      $(':input[id="btnEditSave"]').prop("disabled", false);
      editPickr.setColor(editColor);
      $("#editEventColor").val(editColor);
    });
  }

  function wc_hex_is_light(color) {
    var hex = color.replace("#", "");
    var c_r = parseInt(hex.substr(0, 2), 16);
    var c_g = parseInt(hex.substr(2, 2), 16);
    var c_b = parseInt(hex.substr(4, 2), 16);
    var brightness = (c_r * 299 + c_g * 587 + c_b * 114) / 1000;
    return brightness > 240;
  }

  if (currentRouteName == "events.edit") {
    var id = $(".add-location").val();
    var prepareLocationData = [];

    if (locationMeta[0] == 1) {
      $("#shortDescLoc").val(locationMeta[1] ? locationMeta[1] : "");
      $("#longDescLoc").val(locationMeta[2] ? locationMeta[2] : "");
      console.log('[CallaLink][UI] restoring location_type:', eventLocationType, 'liveSharingActive:', eventIsLiveSharingActive);

      if (eventLocationType == 2) {
        $("input[name='location_type'][value='live']").prop("checked", true);
        $("#liveSharingToggleWrap").removeClass("d-none");
        $("#liveSharingToggle").prop("checked", eventIsLiveSharingActive == 1);
      } else {
        $("input[name='location_type'][value='fixed']").prop("checked", true);
        $("#liveSharingToggleWrap").addClass("d-none");
        $("#liveSharingToggle").prop("checked", false);
      }

      var shortName = $("#shortDescLoc").val();
      var longDesLoc = $("#longDescLoc").val();
      prepareLocationData.push(id);
      prepareLocationData.push(shortName);

      if (longDesLoc != "") {
        prepareLocationData.push(longDesLoc);
      }
    } else if (locationMeta[0] == 2) {
      if (locationMeta[1] == 2) {
        $("#phoneCallOption2").prop("checked", true);
        $("#longDescCall").val(locationMeta[3] ? locationMeta[3] : "");
        var phoneCallOption2 = $("#phoneCallOption2").val();
        var longDescCall = $("#longDescCall").val();
        prepareLocationData.push(id);
        prepareLocationData.push(phoneCallOption2);
        prepareLocationData.push("+" + $("#prefix_code").val() + $("#phoneNumber").val());

        if (longDescCall != "") {
          prepareLocationData.push(longDescCall);
        }
      } else {
        $("#phoneCallOption").prop("checked", true);
        var phoneCallOption = $("#phoneCallOption").val();
        prepareLocationData.push(id);
        prepareLocationData.push(phoneCallOption);
      }
    } else {
      $(".add-location-modal").addClass("d-none");
    }

    if (locationMeta[0] == "3") {
      $("#locationAddData").val(JSON.stringify(["3"]));
    } else {
      $("#locationAddData").val(JSON.stringify(prepareLocationData));
    }
  }

  $(".event-location").on("select2:select", function (e) {
    var id = e.params.data.element.value;
    $(".add-location").val(id).trigger("change");

    if (id != "") {
      if (currentRouteName == "events.edit") {
        if (locationMeta[0] == 1) {
          $(".add-location-modal").removeClass("d-none");
          $("#shortDescLoc").val(locationMeta[1] ? locationMeta[1] : "");
          $("#longDescLoc").val(locationMeta[2] ? locationMeta[2] : "");
          $(".long-desc-loc").addClass("d-none");
          $(".add-information-loc").removeClass("d-none");

          if (locationMeta[2] != undefined) {
            $(".long-desc-loc").removeClass("d-none");
            $(".add-information-loc").addClass("d-none");
          }
        } else if (locationMeta[0] == 2 && locationMeta[1] == 2) {
          $(".add-location-modal").removeClass("d-none");
          $("#phoneCallOption2").prop("checked", true);
          $("#callNumber").removeClass("d-none");
          $("#longDescCall").val(locationMeta[3] ? locationMeta[3] : "");
          $(".long-desc-call").addClass("d-none");
          $(".add-information-call").removeClass("d-none");

          if (locationMeta[3] != undefined) {
            $(".long-desc-call").removeClass("d-none");
            $(".add-information-call").addClass("d-none");
          }
        } else {
          $(".add-location-modal").addClass("d-none");
        }
      }

      if (id == 1) {
        $(".add-location-modal").removeClass("d-none");
        $("#locationData").removeClass("d-none");
        $("#phoneCallData").addClass("d-none");
      } else if (id == 2) {
        $(".add-location-modal").removeClass("d-none");
        $("#phoneCallData").removeClass("d-none");
        $("#locationData").addClass("d-none");
      } else {
        $(".add-location-modal").addClass("d-none");
      }

      listenClick(".phone-call-option", function () {
        if ($("#phoneCallOption2").is(":checked")) {
          $("#callNumber").removeClass("d-none");
        } else {
          $("#callNumber").addClass("d-none");
        }
      });

      if (currentRouteName == "events.create") {
        $(".long-desc-loc").addClass("d-none");
        $(".add-information-loc").removeClass("d-none");
      }

      listenClick(".add-information-loc", function () {
        $(".long-desc-loc").removeClass("d-none");
        $(".add-information-loc").addClass("d-none");
      });

      if (currentRouteName == "events.create") {
        $(".long-desc-call").addClass("d-none");
        $(".add-information-call").removeClass("d-none");
      }

      listenClick(".add-information-call", function () {
        $(".long-desc-call").removeClass("d-none");
        $(".add-information-call").addClass("d-none");
      });

      if (id == 1) {
        $("#updateLocation").modal("show").appendTo("body");
      } else {
        $("#locationAddData").val('["' + id + '"]');
      }
    }
  });
  $(".add-location").on("select2:select", function (e) {
    var id = e.params.data.element.value;

    if (id == 1) {
      $("#locationData").removeClass("d-none");
      $("#phoneCallData").addClass("d-none");
      setTimeout(function () {
        $("#shortDescLoc").focus();
      }, 500);
    } else if (id == 2) {
      $("#phoneCallData").removeClass("d-none");
      $("#locationData").addClass("d-none");
    }

    if (id != "") {
      if (currentRouteName == "events.edit") {
        if (locationMeta[0] == 1) {
          $("#shortDescLoc").val(locationMeta[1] ? locationMeta[1] : "");
          $("#longDescLoc").val(locationMeta[2] ? locationMeta[2] : "");
          $(".long-desc-loc").addClass("d-none");
          $(".add-information-loc").removeClass("d-none");

          if (locationMeta[2] != undefined) {
            $(".long-desc-loc").removeClass("d-none");
            $(".add-information-loc").addClass("d-none");
          }
        } else if (locationMeta[0] == 2 && locationMeta[1] == 2) {
          $("#phoneCallOption2").prop("checked", true);
          $("#callNumber").removeClass("d-none");
          $("#longDescCall").val(locationMeta[3] ? locationMeta[3] : "");
          $(".long-desc-call").addClass("d-none");
          $(".add-information-call").removeClass("d-none");

          if (locationMeta[3] != undefined) {
            $(".long-desc-call").removeClass("d-none");
            $(".add-information-call").addClass("d-none");
          }
        }
      }

      if (id == 1) {
        $("#locationData").removeClass("d-none");
        $("#phoneCallData").addClass("d-none");
        setTimeout(function () {
          $("#shortDescLoc").focus();
        }, 500);
      } else if (id == 2) {
        $("#phoneCallData").removeClass("d-none");
        $("#locationData").addClass("d-none");
      }

      listenClick(".phone-call-option", function () {
        if ($("#phoneCallOption2").is(":checked")) {
          $("#callNumber").removeClass("d-none");
        } else {
          $("#callNumber").addClass("d-none");
        }
      });
    }

    listenClick(".phone-call-option", function () {
      if ($("#phoneCallOption2").is(":checked")) {
        $("#callNumber").removeClass("d-none");
      } else {
        $("#callNumber").addClass("d-none");
      }
    });

    if (currentRouteName == "events.create") {
      $(".long-desc-loc").addClass("d-none");
      $(".add-information-loc").removeClass("d-none");
    }

    listenClick(".add-information-loc", function () {
      $(".long-desc-loc").removeClass("d-none");
      $(".add-information-loc").addClass("d-none");
    });

    if (currentRouteName == "events.create") {
      $(".long-desc-call").addClass("d-none");
      $(".add-information-call").removeClass("d-none");
    }

    listenClick(".add-information-call", function () {
      $(".long-desc-call").removeClass("d-none");
      $(".add-information-call").addClass("d-none");
    });

    if (id == 1) {
      $("#updateLocation").modal("show").appendTo("body");
    }
  });
  $(".payment-type").on("select2:select", function (e) {
    var id = e.params.data.element.value;

    if (id == 2) {
      $("#payableAmount").removeClass("d-none");
      $("#payableAmountId").prop("disabled", false);
    } else {
      $("#payableAmount").addClass("d-none");
      $("#payableAmountId").prop("disabled", true);
    }
  });
}

listenClick(".add-location-modal", function () {
  var id = $(".event-location").val();

  if (id == "" || id == 1) {
    $("#locationData").removeClass("d-none");
    $("#phoneCallData").addClass("d-none");
  } else if (id == 2) {
    $("#locationData").addClass("d-none");
    $("#phoneCallData").removeClass("d-none");
  }

  $("#updateLocation").modal("show").appendTo("body");

  if (id != "") {
    if (currentRouteName == "events.edit") {
      if (locationMeta[0] == 1) {
        $(".add-location").val(id).trigger("change");
        $("#shortDescLoc").val(locationMeta[1] ? locationMeta[1] : "");
        $("#longDescLoc").val(locationMeta[2] ? locationMeta[2] : "");
        $(".long-desc-loc").addClass("d-none");
        $(".add-information-loc").removeClass("d-none");

        if (locationMeta[2] != undefined) {
          $(".long-desc-loc").removeClass("d-none");
          $(".add-information-loc").addClass("d-none");
        }
      } else if (locationMeta[0] == 2 && locationMeta[1] == 2) {
        $(".add-location").val(id).trigger("change");
        $("#phoneCallOption2").prop("checked", true);
        $("#callNumber").removeClass("d-none");
        $("#longDescCall").val(locationMeta[3] ? locationMeta[3] : "");
        $(".long-desc-call").addClass("d-none");
        $(".add-information-call").removeClass("d-none");

        if (locationMeta[3] != undefined) {
          $(".long-desc-call").removeClass("d-none");
          $(".add-information-call").addClass("d-none");
        }
      }
    }

    if (id == 1) {
      $("#locationData").removeClass("d-none");
      $("#phoneCallData").addClass("d-none");
    } else if (id == 2) {
      $("#phoneCallData").removeClass("d-none");
      $("#locationData").addClass("d-none");
    }

    listenClick(".phone-call-option", function () {
      if ($("#phoneCallOption2").is(":checked")) {
        $("#callNumber").removeClass("d-none");
      } else {
        $("#callNumber").addClass("d-none");
      }
    });

    if (currentRouteName == "events.create") {
      $(".long-desc-loc").addClass("d-none");
      $(".add-information-loc").removeClass("d-none");
    }

    $(document).on("click", ".add-information-loc", function () {
      $(".long-desc-loc").removeClass("d-none");
      $(".add-information-loc").addClass("d-none");
    });

    if (currentRouteName == "events.create") {
      $(".long-desc-call").addClass("d-none");
      $(".add-information-call").removeClass("d-none");
    }

    listenClick(".add-information-call", function () {
      $(".long-desc-call").removeClass("d-none");
      $(".add-information-call").addClass("d-none");
    });

    if (id == 1) {
      $("#updateLocation").modal("show").appendTo("body");
    } else {
      $("#locationAddData").val('["' + id + '"]');
    }
  }
});
listenSubmit("#addLocationInfo", function (e) {
  e.preventDefault();
  var id = $(".add-location").val();
  console.log('[CallaLink][UI] addLocationInfo submit, add-location id =', id);
  var prepareLocationData = [];
  var radio = $("#phoneCallOption").val();

  if ($("#phoneCallOption2").prop("checked") == true) {
    radio = $("#phoneCallOption2").val();
  }

  if (id == 1) {
    var radioIsLive = $("input[name='location_type']:checked").val() === "live";
    var liveChecked = $("#liveSharingToggle").is(":checked");
    var addressRequired = !radioIsLive || radioIsLive && liveChecked;
    var shortName = $("#shortDescLoc").val();
    var empty = shortName.trim().replace(/ \r\n\t/g, "") === "";

    if (addressRequired) {
      if (shortName == "") {
        $("#shortDescLoc").focus();
        displayErrorMessage(Lang.get("js.location"));
        return false;
      }

      if (empty) {
        displayErrorMessage(Lang.get("js.location_white"));
        return false;
      }
    }

    $("#newLocationType").val($("input[name='location_type']:checked").val() === "live" ? 2 : 1);
    console.log('[CallaLink][UI] at submit — radioIsLive:', radioIsLive, 'liveChecked:', liveChecked);
    $("#newLocationIsLiveSharingActive").val($("input[name='location_type']:checked").val() === "live" && $("#liveSharingToggle").is(":checked") ? 1 : 0);
    console.log('[CallaLink][UI] newLocationIsLiveSharingActive set to:', $("#newLocationIsLiveSharingActive").val());
    $("#newLocationAddress").val($("#shortDescLoc").val());
    prepareLocationData.push(id);
    prepareLocationData.push($("#shortDescLoc").val());

    if ($("#longDescLoc").val() != "") {
      prepareLocationData.push($("#longDescLoc").val());
    }
  }

  if (id == 2) {
    if ($("#phoneCallOption").prop("checked") == true) {
      prepareLocationData.push(id);
      prepareLocationData.push(radio);
      $("#updateLocation").modal("hide");
    } else {
      if ($("#phoneNumber").val() == "") {
        $("#phoneNumber").focus();
        displayErrorMessage(Lang.get("js.phone"));
        return false;
      }

      prepareLocationData.push(id);
      prepareLocationData.push(radio);
      prepareLocationData.push("+" + $("#prefix_code").val() + $("#phoneNumber").val());

      if ($("#longDescCall").val() != "") {
        prepareLocationData.push($("#longDescCall").val());
      }
    }
  }

  $("#locationAddData").val(JSON.stringify(prepareLocationData));
  $("#updateLocation").modal("hide");
  $(".event-location").val($(".add-location").val()).trigger("change");
});

function fetchAndFillLocation() {
  var statusEl = $("#locationFetchStatus");
  statusEl.removeClass("d-none text-danger").addClass("text-muted").text("Fetching your location...");

  if (!navigator.geolocation) {
    statusEl.removeClass("text-muted").addClass("text-danger").text("Geolocation isn't supported by this browser.");
    return;
  }

  navigator.geolocation.getCurrentPosition(function (position) {
    var lat = position.coords.latitude;
    var lng = position.coords.longitude;
    var accuracy = position.coords.accuracy;
    $("#newLocationLatitude").val(lat);
    $("#newLocationLongitude").val(lng);
    $("#newLocationAccuracy").val(accuracy);
    fetch("https://nominatim.openstreetmap.org/reverse?format=json&lat=".concat(lat, "&lon=").concat(lng)).then(function (res) {
      return res.json();
    }).then(function (data) {
      if (data && data.display_name) {
        $("#shortDescLoc").val(data.display_name).trigger("change");
        $("#newLocationAddress").val(data.display_name);
        statusEl.addClass("d-none");
      } else {
        statusEl.removeClass("text-muted").addClass("text-danger").text("Couldn't resolve an address for that location.");
      }
    })["catch"](function () {
      statusEl.removeClass("text-muted").addClass("text-danger").text("Couldn't resolve an address for that location.");
    });
  }, function (error) {
    var msg = "Couldn't get your location.";

    if (error.code === error.PERMISSION_DENIED) {
      msg = "Location permission denied. You can still type the address manually.";
    }

    statusEl.removeClass("text-muted").addClass("text-danger").text(msg);
  }, {
    enableHighAccuracy: true,
    timeout: 10000
  });
}

$(document).on("click", "#useMyLocationBtn", function () {
  fetchAndFillLocation();
});
$(document).on('mouseenter', '.live-location-icon', function () {
  var $icon = $(this);
  if ($icon.data('resolved')) return;
  var locationType = parseInt($icon.data('location-type'), 10);

  if (locationType === 1) {
    var fixedAddress = $icon.data('address');
    $icon.attr('title', fixedAddress || 'Address unavailable').data('resolved', true);
    return;
  }

  var eventId = $icon.data('event-id');
  if (!eventId) return;
  $.get("/events/".concat(eventId, "/location/address")).done(function (res) {
    var payload = res.data || res;
    var title;

    switch (payload.status) {
      case 'not_started':
        title = "Location sharing hasn't started yet";
        break;

      case 'stopped':
        title = payload.address || 'Location sharing has stopped';
        break;

      default:
        title = payload.address || 'Address unavailable';
    }

    $icon.attr('title', title).data('resolved', true);
  }).fail(function (xhr) {
    console.error('[CallaLink] address resolve failed:', xhr.status, xhr.responseText);
  });
});
listenClick("input[name='location_type']", function () {
  console.log('[CallaLink][UI] location_type radio clicked:', $(this).val());

  if ($(this).val() === "live") {
    $("#liveSharingToggleWrap").removeClass("d-none");
    console.log('[CallaLink][UI] liveSharingToggleWrap shown');
  } else {
    $("#liveSharingToggleWrap").addClass("d-none");
    $("#liveSharingToggle").prop("checked", false);
    console.log('[CallaLink][UI] liveSharingToggleWrap hidden, toggle force-unchecked');
  }
});
listenClick("#liveSharingToggle", function () {
  var isChecked = $(this).is(':checked');
  console.log('[CallaLink][UI] liveSharingToggle changed, checked =', $(this).is(':checked'));

  if (isChecked && conflictingLiveEventName) {
    $(this).prop('checked', false); // revert immediately

    displayErrorMessage("Live location is already running for \"".concat(conflictingLiveEventName, "\"."));
    return;
  }

  if (isChecked) {
    fetchAndFillLocation();
  } else {
    $("#shortDescLoc").val("");
    $("#newLocationLatitude").val("");
    $("#newLocationLongitude").val("");
    $("#newLocationAccuracy").val("");
    $("#newLocationAddress").val("");
    $("#locationFetchStatus").addClass("d-none");
    console.log('[CallaLink][UI] liveSharingToggle off — cleared location fields');
  }
});
var picked = false;
listenClick("#color", function () {
  picked = true;
});
listen("keypress", "#eventLinkId", function (e) {
  if (e.keyCode === 32 || e.keyCode === 95) {
    return false;
  }

  var keyCode = e.keyCode || e.which;
  var regex = /^[A-Za-z0-9\-]+$/;
  var isValid = regex.test(String.fromCharCode(keyCode));

  if (!isValid) {
    return false;
  }
}); // Click on within data range checkbox js code

listenClick("input[name=date_range]", function () {
  if ($(this).hasClass("within-date-range")) {
    $("#withinDateRangeId").removeClass("d-none");
    $("#withinDateRangeId").prop("disabled", false);
    $("#scheduleDayId").prop("disabled", true);
  } else {
    $("#withinDateRangeId").addClass("d-none");
    $("#withinDateRangeId").prop("disabled", true);
    $("#scheduleDayId").prop("disabled", false);
  }
}); // Click on additional info link time slide up and down js code

listenClick(".add-rules-info", function () {
  if ($(".additional-event-rules").hasClass("d-none")) {
    $(this).next().removeClass("fa-chevron-right");
    $(this).next().addClass("fa-chevron-down");
    $(".additional-event-rules").removeClass("d-none");
  } else {
    $(this).next().removeClass("fa-chevron-down");
    $(this).next().addClass("fa-chevron-right");
    $(".additional-event-rules").addClass("d-none");
  }
}); // Click on before event checkbox then after select is enabled otherwise disabled js code

listenClick(".before-time", function () {
  if ($(this).prop("checked") == true) {
    $("#beforeEventTimeId").prop("disabled", false);
  } else {
    $("#beforeEventTimeId").prop("disabled", true);
  }
}); // Click on after event checkbox then after select is enabled otherwise disabled js code

listenClick(".after-time", function () {
  if ($(this).prop("checked") == true) {
    $("#afterEventTimeId").prop("disabled", false);
  } else {
    $("#afterEventTimeId").prop("disabled", true);
  }
});
listenKeyup("#scheduleDayId", function () {
  var scheduleDayId = $(this).val();
  scheduleDayId = parseInt(removeCommas(scheduleDayId));
  $(this).val(scheduleDayId);
});
listenKeyup("#maxEventPerDay", function () {
  var maxEventPerDay = $(this).val();
  maxEventPerDay = parseInt(removeCommas(maxEventPerDay));
  $(this).val(maxEventPerDay);
});
listenSubmit("#eventStoreForm", function () {
  if ($('[name="event_location"]').val() == 1 && $("#locationAddData").val() == "") {
    displayErrorMessage(Lang.get("js.location"));
    return false;
  } else if ($('[name="event_location"]').val() == 2 && $("#locationAddData").val() == "") {
    displayErrorMessage(Lang.get("js.phone"));
    return false;
  }

  $("#btnSave").attr("disabled", true);
});
listenSubmit("#eventEditForm", function () {
  console.log('[CallaLink][UI] eventEditForm submitting with newLocationType=%s, newLocationIsLiveSharingActive=%s', $("#newLocationType").val(), $("#newLocationIsLiveSharingActive").val());

  if ($('[name="event_location"]').val() == 1 && $("#locationAddData").val() == "") {
    displayErrorMessage(Lang.get("js.location"));
    return false;
  } else if ($('[name="event_location"]').val() == 2 && $("#locationAddData").val() == "") {
    displayErrorMessage(Lang.get("js.phone"));
    return false;
  }

  $("#btnSave").attr("disabled", true);
});
listenHiddenBsModal("#addScheduleNameModal", function () {
  resetModalForm("#scheduleNameForm", "#scheduleValidationErrorsBox");
});

if (currentRouteName == "events.create") {
  listenHiddenBsModal("#updateLocation", function () {
    console.log('[CallaLink][UI] updateLocation modal hidden — resetting toggle & radio state');
    $("#phoneNumber").val("");
    $("#valid-msg").addClass("hide");
    $("#error-msg").addClass("hide");
    $("#liveSharingToggle").prop("checked", false);
    $("#liveSharingToggleWrap").addClass("d-none");
    resetModalForm("#addLocationInfo", "#updateLocationValidationErrorsBox");
  });
}
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!******************************************************!*\
  !*** ./resources/assets/js/events/event-schedule.js ***!
  \******************************************************/
document.addEventListener('turbo:load', loadEventScheduleData);
var isEdit;
var eventId;

function loadEventScheduleData() {
  isEdit = $('#eventIsEdit').val();
  eventId = $('#eventIdEdit').val();
  var defaultUserSchedule = $('#defaultScheduleId').val();

  if (isEdit) {
    $('.change-schedule').val(defaultUserSchedule).trigger('change');
  }

  if (!$('#scheduleNameId').length || !$('#scheduleId').length || !$('#timeZoneId').length) {
    return;
  }

  $('#scheduleNameId').select2();
  $('#scheduleId').select2();
  $('#timeZoneId').select2();
}

listenClick('.add-session-time', function () {
  var selectedIndex = 0;

  if ($(this).parent().prev().children('.session-times').find('.timeSlot:last-child').length > 0) {
    selectedIndex = $(this).parent().prev().children('.session-times').find('.timeSlot:last-child').children('.add-slot').find('select[name^="to_time"] option:selected')[0].index;
  }

  var day = $(this).closest('.weekly-content').attr('data-day');
  var $ele = $(this);
  var weeklyEle = $(this).closest('.weekly-content');
  $.ajax({
    url: route('get.slot.by.gap'),
    data: {
      day: day
    },
    success: function success(data) {
      weeklyEle.find('.unavailable-time').html('');
      weeklyEle.find('input[name="checked_week_days[]"').prop('checked', true).prop('disabled', false);
      $ele.closest('.weekly-content').find('.session-times').append(data.data);
      weeklyEle.find('.time').select2();
      var startTimeOptions = $('.add-session-time').parent().prev().children('.session-times').find('.timeSlot:last-child').children('.add-slot').find('select[name^="from_time"] option');
      startTimeOptions.each(function (index) {
        if (index <= selectedIndex) {
          $(this).attr('disabled', true);
        } else {
          $(this).attr('disabled', false);
        }
      });
    }
  });
}); // copy slot day time

listenClick('.copy-btn', function () {
  $(this).closest('.copy-card').removeClass('show');
  var selectEle = $(this).closest('.weekly-content').find('.session-times').find('select'); // check for slot is empty

  if (selectEle.length == 0) {
    $(this).closest('.menu-content').find('.copy-label .form-check-input:checked').each(function () {
      var weekEle = $(".weekly-content[data-day=\"".concat($(this).val(), "\"]"));
      $(weekEle).find('.session-times').html('');
      weekEle.find('.weekly-row').find('.unavailable-time').remove();
      weekEle.find('.weekly-row').append('<div class="unavailable-time">Unavailable</div>');
      var dayChk = $(weekEle).find('.weekly-row').find('input[name="checked_week_days[]"');
      dayChk.prop('checked', false).prop('disabled', true);
    });
  } else {
    selectEle.each(function () {
      $(this).select2('destroy');
    });
    var selects = $(this).closest('.weekly-content').find('.session-times').find('select');
    var $cloneEle = $(this).closest('.weekly-content').find('.session-times').clone();
    $(this).closest('.menu-content').find('.copy-label .form-check-input:checked').each(function () {
      var $cloneEle2 = $cloneEle;
      var currentDay = $(this).val();
      var weekEle = ".weekly-content[data-day=\"".concat(currentDay, "\"]");
      $cloneEle2.find('select[name^="from_time"]').attr('name', "from_time[".concat(currentDay, "][]"));
      $cloneEle2.find('select[name^="to_time"]').attr('name', "to_time[".concat(currentDay, "][]"));
      $(weekEle).find('.unavailable-time').html('');
      $cloneEle2.find('.error-msg').html('');
      $(weekEle).find('.session-times').html($cloneEle2.html());
      $(weekEle).find('.session-times select').select2();
      $(weekEle).find('input[name="checked_week_days[]"').prop('disabled', false).prop('checked', true);
      $(selects).each(function (i) {
        var select = this;
        $(weekEle).find('.session-times').find('select').eq(i).val($(select).val()).trigger('change');
      });
    });
    $(this).closest('.weekly-content').find('.session-times').find('select').each(function () {
      $(this).select2();
    });
    $('.copy-check-input').prop('checked', false);
  }

  $('.copy-menu, .copy-days-btn').removeClass('show');
});
listenClick('.deleteBtn', function () {
  var selectedIndex = 0;

  if ($(this).closest('.timeSlot').prev().length > 0) {
    selectedIndex = $(this).closest('.timeSlot').prev().children('.add-slot').find('select[name^="to_time"] option:selected')[0].index;
  }

  if ($(this).closest('.weekly-row').find('.session-times').find('select').length == 2) {
    var dayChk = $(this).closest('.weekly-row').find('input[name="checked_week_days[]"');
    dayChk.prop('checked', false).prop('disabled', true);
    $(this).closest('.weekly-row').append('<div class="unavailable-time">Unavailable</div>');
  }

  var startTimeOptions = $(this).closest('.timeSlot').next().children('.add-slot').find('select[name^="from_time"] option');
  startTimeOptions.each(function (index) {
    if (index <= selectedIndex) {
      $(this).attr('disabled', true);
    } else {
      $(this).attr('disabled', false);
    }
  });
  $(this).parent().siblings('.error-msg').remove();
  $(this).parent().closest('.timeSlot').remove();
  $(this).parent().remove();
});
listenChange('select[name^="from_time"]', function () {
  var selectedIndex = $(this)[0].selectedIndex;
  var endTimeOptions = $(this).closest('.add-slot').find('select[name^="to_time"] option');
  var endSelectedIndex = $(this).closest('.add-slot').find('select[name^="to_time"] option:selected')[0].index;

  if (selectedIndex >= endSelectedIndex) {
    endTimeOptions.eq(selectedIndex + 1).prop('selected', true).trigger('change');
  }

  endTimeOptions.each(function (index) {
    if (index <= selectedIndex) {
      $(this).attr('disabled', true);
    } else {
      $(this).attr('disabled', false);
    }
  });
});
listenChange('select[name^="to_time"]', function () {
  var selectedIndex = $(this)[0].selectedIndex;
  var startTimeOptions = $(this).closest('.timeSlot').next().find('select[name^="from_time"] option');
  startTimeOptions.each(function (index) {
    if (index <= selectedIndex) {
      $(this).attr('disabled', true);
    } else {
      $(this).attr('disabled', false);
    }
  });
});
listenClick('.add-schedule-name', function () {
  $('#addScheduleNameModal').modal('show').append('body');
});
listenSubmit('#scheduleNameForm', function (e) {
  e.preventDefault();

  if ($('#scheduleName').val() == '') {
    displayErrorMessage(Lang.get('js.schedule_name'));
    return false;
  }

  var checkTab = $('.tab-pane').find('.active').attr('data-id');
  $('#checkTabId').val(checkTab);
  var formData = new FormData();
  formData.append('form1', $('#scheduleNameForm').serialize());
  formData.append('form2', $('#addEventScheduleForm').serialize());
  var loadingButton = jQuery(this).find('#scheduleNameBtn');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  $.ajax({
    url: route('schedules.store'),
    type: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    success: function success(result) {
      var eventId = result.data.event_id;
      var schedule = result.data.schedule;

      if (result.success) {
        displaySuccessMessage(result.message);
        $('#addScheduleNameModal').modal('hide');
        $(loadingButton).attr('disabled', false);

        if (result.data.scheduleWithTime === false) {
          var data = {
            id: schedule.id,
            name: schedule.schedule_name
          };
          var newOption = new Option(data.name, data.id, false, true);
          $('#scheduleNameId').append(newOption).trigger('change');
          $('#scheduleNameForm')[0].reset();
          $('#pills-existing-tab').click();
        } else {
          window.location.href = route('events.edit', eventId);
        }
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $(loadingButton).attr('disabled', false);
    }
  });
});
listenClick('#eventScheduleBtnSave', function (e) {
  e.preventDefault();
  var checkTab = $('.tab-pane').find('.active').attr('data-id');
  $('#checkTabId').val(checkTab);

  if ($('#slotTimeId').val() == '') {
    displayErrorMessage(Lang.get('js.slot_time'));
    return false;
  }

  $('#eventScheduleBtnSave').attr('disabled', true);
  $.ajax({
    url: route('add.event.schedule'),
    type: 'POST',
    data: $('#addEventScheduleForm').serialize(),
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        window.location.href = route('events.index');
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $('#eventScheduleBtnSave').attr('disabled', false);
    }
  });
});
listenChange('.change-schedule', function () {
  var scheduleId = $(this).val();
  $.ajax({
    url: route('get.time.by.schedule'),
    data: {
      schedule_id: scheduleId,
      event_id: eventId
    },
    success: function success(data) {
      $('.existing-schedule').children('.maincard-section').empty();
      $('.existing-schedule').append(data.data);
    }
  });
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***************************************************!*\
  !*** ./resources/assets/js/schedules/schedule.js ***!
  \***************************************************/
document.addEventListener('turbo:load', loadScheduleData);

function loadScheduleData() {
  if (!$('#scheduleNameId').length || !$('.startTimeSlot').length || !$('.endTimeSlot').length) {
    return;
  }

  $('#scheduleNameId').select2({
    width: '250px'
  });
  $('.startTimeSlot').select2({
    width: '100%'
  });
  $('.endTimeSlot').select2({
    width: '100%'
  });
}

document.addEventListener('livewire:load', function () {
  window.livewire.hook('message.processed', function () {
    if (!$('.startTimeSlot').length || !$('.endTimeSlot').length) {
      return;
    }

    $('.startTimeSlot').each(function () {
      $(this).select2();
    });
    $('.endTimeSlot').each(function () {
      $(this).select2();
    });
  });
}); // Store Schedule

listenSubmit('#addScheduleTimeForm', function (e) {
  e.preventDefault();
  $('#scheduleSaveButton').attr('disabled', true);
  var scheduleId = $('#scheduleNameId').val();
  var formData = new FormData($(this)[0]);
  formData.append('schedule_id', scheduleId);
  $.ajax({
    url: route('add.schedule.time.slot'),
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function success(result) {
      displaySuccessMessage(result.message);
      $('#scheduleSaveButton').attr('disabled', false);
      window.location.href = route('schedules.index');
    },
    error: function error(result) {
      displayErrorMessage(result.message);
      $('#scheduleSaveButton').attr('disabled', false);
    }
  });
});
listenClick('.edit-schedule', function () {
  var id = $(this).attr('data-id');
  renderData(id);
});

function renderData(id) {
  $.ajax({
    url: route('schedules.edit', id),
    type: 'GET',
    success: function success(result) {
      $('#editScheduleId').val(result.data.id);
      $('#isDefaultId').val(result.data.is_default);
      $('#editScheduleNameId').val(result.data.schedule_name);

      if (result.data.status == 1) {
        $('#editStatusId').prop('checked', true);
      } else {
        $('#editStatusId').prop('checked', false);
      }

      $('#editScheduleNameModal').modal('show').appendTo('body');
    }
  });
} // Edit Schedule


listenSubmit('#editScheduleNameForm', function (e) {
  e.preventDefault();
  var loadingButton = jQuery(this).find('#editScheduleNameBtn');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  var formData = $(this).serialize();
  var id = $('#editScheduleId').val();
  var isDefault = $('#isDefaultId').val();

  if (isDefault == 1) {
    displayErrorMessage(Lang.get('js.default_schedule'));
    return false;
  }

  $.ajax({
    url: route('schedules.update', id),
    type: 'PUT',
    data: formData,
    success: function success(result) {
      $('#scheduleNameId').empty();
      $.each(result.data, function (el, val) {
        $('#scheduleNameId').append("<option value=\"".concat(el, "\">").concat(val, "</option>"));
      });
      $('#editScheduleNameModal').modal('hide');
      $(loadingButton).attr('disabled', false);
      displaySuccessMessage(result.message);
      $('#scheduleNameId').val(id).trigger('change');
      window.livewire.emit('filterUserSchedule', id);
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
      $(loadingButton).attr('disabled', false);
    }
  });
});
listenChange('#scheduleNameId', function () {
  setTimeout(function () {
    $('select[name^="from_time"]').trigger('change');
    $('select[name^="to_time"]').trigger('change');
  }, 1000);
  window.livewire.emit('filterUserSchedule', $(this).val());
}); // delete schedule code

listenClick('.delete-schedule', function () {
  var scheduleId = $(this).attr('data-id');
  var callFunction = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : null;
  swal({
    title: 'Delete !',
    text: Lang.get('js.sure_delete') + ' "' + Lang.get('js.schedule') + '"  ?',
    buttons: {
      confirm: Lang.get('js.yes'),
      cancel: Lang.get('js.no')
    },
    icon: sweetAlertIcon,
    reverseButtons: true
  }).then(function (willDelete) {
    if (willDelete) {
      $.ajax({
        url: route('schedules.destroy', scheduleId),
        type: 'DELETE',
        dataType: 'json',
        success: function success(obj) {
          if (obj.success) {
            window.location.reload();
          }

          swal({
            icon: 'success',
            confirmButtonColor: '#ADB5BD',
            title: deleteMsg + ' !',
            text: Lang.get('js.schedule') + ' ' + hasBeenDeleted,
            timer: 2000
          });

          if (callFunction) {
            eval(callFunction);
          }
        },
        error: function error(data) {
          swal({
            title: 'Error',
            icon: 'error',
            text: data.responseJSON.message,
            type: 'error',
            timer: 4000
          });
        }
      });
    }
  });
});
listenHiddenBsModal('#addScheduleNameModal', function () {
  resetModalForm('#scheduleNameForm', '#scheduleValidationErrorsBox');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***************************************************************!*\
  !*** ./resources/assets/js/schedule_events/schedule-event.js ***!
  \***************************************************************/
document.addEventListener('turbo:load', loadScheduleEventData);

function loadScheduleEventData() {
  var uri = window.location.toString();

  if (uri.indexOf('?') > 0) {
    var clean_uri = uri.substring(0, uri.indexOf('?'));
    window.history.replaceState({}, document.title, clean_uri);
  }
} // delete schedule event code


listenClick('.scheduled-event-delete-btn', function () {
  var scheduledEventId = $(this).attr('data-id');
  deleteItem(route('scheduled-events.destroy', scheduledEventId), 'Scheduled Event');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!****************************************************************!*\
  !*** ./resources/assets/js/events/event-schedule-datatable.js ***!
  \****************************************************************/

})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!****************************************************************************!*\
  !*** ./resources/assets/js/front/front_testimonials/front_testimonials.js ***!
  \****************************************************************************/
// delete front testimonial record code
listenClick('.front-testimonial-delete-btn', function () {
  var deleteFrontTestimonialId = $(this).attr('data-id');
  deleteItem(route('front-testimonials.destroy', deleteFrontTestimonialId), Lang.get('js.front_testimonial'));
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*********************************************************************!*\
  !*** ./resources/assets/js/front/front_testimonials/create-edit.js ***!
  \*********************************************************************/
var imageSize = '';
listenChange('#profileImage', function () {
  return imageSize = this.files[0].size;
});
listenSubmit('#editFrontTestimonialForm, #createFrontTestimonialForm', function () {
  if (imageSize > 2000000) {
    displayErrorMessage(Lang.get('js.profile_size'));
    return false;
  }

  if ($('#name').val().trim() == '') {
    displayErrorMessage(Lang.get('js.name_required'));
    return false;
  }

  if ($('#shortDescription').val().trim() == '') {
    displayErrorMessage(Lang.get('js.short_description'));
    return false;
  }

  if ($('#designation').val().trim() == '') {
    displayErrorMessage(Lang.get('js.desig_required'));
    return false;
  }

  $('#frontTestimonialSaveBtn').attr('disabled', true);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*************************************************!*\
  !*** ./resources/assets/js/front/cms/create.js ***!
  \*************************************************/
document.addEventListener('turbo:load', loadCMSData);
var quill1;
var quill2;

function loadCMSData() {
  if (!$('#termConditionId').length || !$('#privacyPolicyId').length) {
    return;
  }

  quill1 = new Quill('#termConditionId', {
    modules: {
      toolbar: [[{
        header: [1, 2, false]
      }], ['bold', 'italic', 'underline'], ['image', 'code-block']]
    },
    placeholder: Lang.get('js.terms_cond'),
    theme: 'snow' // or 'bubble'

  });
  quill1.on('text-change', function (delta, oldDelta, source) {
    if (quill1.getText().trim().length === 0) {
      quill1.setContents([{
        insert: ''
      }]);
    }
  });
  quill2 = new Quill('#privacyPolicyId', {
    modules: {
      toolbar: [[{
        header: [1, 2, false]
      }], ['bold', 'italic', 'underline'], ['image', 'code-block']]
    },
    placeholder: Lang.get('js.privacy_policy'),
    theme: 'snow' // or 'bubble'

  });
  quill2.on('text-change', function (delta, oldDelta, source) {
    if (quill2.getText().trim().length === 0) {
      quill2.setContents([{
        insert: ''
      }]);
    }
  });
  var element = document.createElement('textarea');
  element.innerHTML = $('#termConditionData').val();
  quill1.root.innerHTML = element.value;
  element.innerHTML = $('#privacyPolicyData').val();
  quill2.root.innerHTML = element.value;
  var imageSize = '';
  listenChange('#frontImage', function () {
    return imageSize = this.files[0].size;
  });
  listenSubmit('#addCMSForm', function (e) {
    e.stopImmediatePropagation();

    if (imageSize > 2000000) {
      displayErrorMessage('Image size should be less than 2 MB');
      return false;
    }

    var emptyTitleField = $('#titleId').val().trim();

    if ($('#titleId').val() == '') {
      displayErrorMessage('Title field is required.');
      return false;
    } else if (isEmpty(emptyTitleField)) {
      displayErrorMessage('The title field is required.');
      return false;
    }

    var emptyEmailField = $('#email').val().trim();

    if ($('#email').val() == '') {
      displayErrorMessage('The email field is required.');
      return false;
    } else if (isEmpty(emptyEmailField)) {
      displayErrorMessage('The email field is required.');
      return false;
    }

    if ($('#phoneNumber').val() == '') {
      displayErrorMessage('The contact field is required.');
      return false;
    }

    var emptyAddressField = $('#address').val().trim();

    if ($('#address').val() == '') {
      displayErrorMessage('The address field is required.');
      return false;
    } else if (isEmpty(emptyAddressField)) {
      displayErrorMessage('The address field is required.');
      return false;
    }

    var facebookUrl = $('#facebookUrl').val();
    var twitterUrl = $('#twitterUrl').val();
    var instagramUrl = $('#instagramUrl').val();
    var facebookExp = new RegExp(/^(https?:\/\/)?((m{1}\.)?)?((w{2,3}\.)?)facebook.[a-z]{2,3}\/?.*/i);
    var twitterExp = new RegExp(/^(https?:\/\/)?((w{2,3}\.)?)twitter\.[a-z]{2,3}\/?.*/i);
    var instagramExp = new RegExp(/^(https?:\/\/)?((m{1}\.)?)?((w{2,3}\.)?)instagram.[a-z]{2,3}\/?.*/i);
    var facebookCheck = facebookUrl == '' ? true : facebookUrl.match(facebookExp) ? true : false;

    if (!facebookCheck) {
      displayErrorMessage(Lang.get('js.enter_valid_facebook_url'));
      return false;
    }

    var twitterCheck = twitterUrl == '' ? true : twitterUrl.match(twitterExp) ? true : false;

    if (!twitterCheck) {
      displayErrorMessage(Lang.get('js.enter_valid_twitter_url'));
      return false;
    }

    var instagramCheck = instagramUrl == '' ? true : instagramUrl.match(instagramExp) ? true : false;

    if (!instagramCheck) {
      displayErrorMessage(Lang.get('js.enter_valid_instagram_url'));
      return false;
    }

    if ($('#error-msg').text() !== '') {
      $('#phoneNumber').focus();
      return false;
    }

    var element = document.createElement('textarea');
    var editor_content_1 = quill1.root.innerHTML;
    element.innerHTML = editor_content_1;
    var editor_content_2 = quill2.root.innerHTML;

    if (quill1.getText().trim().length === 0) {
      displayErrorMessage(Lang.get('js.terms_condition'));
      return false;
    }

    if (quill2.getText().trim().length === 0) {
      displayErrorMessage(Lang.get('js.privacy_policy'));
      return false;
    }

    $('#termData').val(JSON.stringify(editor_content_1));
    $('#privacyData').val(JSON.stringify(editor_content_2));
    $('#cmsSaveButton').attr('disabled', true);
  });
}

listenKeyup('#facebookUrl', function () {
  this.value = this.value.toLowerCase();
});
listenKeyup('#twitterUrl', function () {
  this.value = this.value.toLowerCase();
});
listenKeyup('#instagramUrl', function () {
  this.value = this.value.toLowerCase();
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!********************************************************!*\
  !*** ./resources/assets/js/front/services/services.js ***!
  \********************************************************/
var imageSize = '';
listenChange('.service_image', function () {
  return imageSize = this.files[0].size;
});
listenSubmit('#addServiceForm', function () {
  if (imageSize > 2000000) {
    displayErrorMessage('Image size should be less than 2 MB');
    return false;
  }

  if ($('.main-title').val().trim() == '') {
    displayErrorMessage('The main title field is required.');
    return false;
  }

  if ($('.service-title-1').val().trim() == '') {
    displayErrorMessage('The service title 1 field is required.');
    return false;
  }

  if ($('.service-description-1').val().trim() == '') {
    displayErrorMessage('The service description 1 field is required.');
    return false;
  } else if ($('.service-description-1').val().length >= 90) {
    displayErrorMessage('The description 1 must not be greater than 90 characters.');
    return false;
  }

  if ($('.service-title-2').val().trim() == '') {
    displayErrorMessage('The service title 2 field is required.');
    return false;
  }

  if ($('.service-description-2').val().trim() == '') {
    displayErrorMessage('The service description 2 field is required.');
    return false;
  } else if ($('.service-description-2').val().length >= 90) {
    displayErrorMessage('The service description 2 must not be greater than 122 characters.');
    return false;
  }

  if ($('.service-title-3').val().trim() == '') {
    displayErrorMessage('The service title 3 field is required.');
    return false;
  }

  if ($('.service-description-3').val().trim() == '') {
    displayErrorMessage('The service description 3 field is required.');
    return false;
  } else if ($('.service-description-3').val().length >= 90) {
    displayErrorMessage('The service description 3 must not be greater than 90 characters.');
    return false;
  }

  $('#servicesSaveBtn').attr('disabled', true);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!************************************************!*\
  !*** ./resources/assets/js/front/faqs/faqs.js ***!
  \************************************************/
// delete faq record code
listenClick('.faq-delete-btn', function () {
  var deleteFaqId = $(this).attr('data-id');
  deleteItem(route('faqs.destroy', deleteFaqId), Lang.get('js.faq'));
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*******************************************************!*\
  !*** ./resources/assets/js/front/faqs/create-edit.js ***!
  \*******************************************************/
// create faq code
listenSubmit('#editFaqForm, #createFaqForm', function () {
  $('#faqSaveBtn').attr('disabled', true);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**************************************************************!*\
  !*** ./resources/assets/js/front/main_reason/main_reason.js ***!
  \**************************************************************/
var imageSize = '';
listenChange('#imageMainReason', function () {
  return imageSize = this.files[0].size;
});
listenSubmit('#addMainReasonForm', function () {
  if (imageSize > 2000000) {
    displayErrorMessage('Image size should be less than 2 MB');
    return false;
  }

  if ($('#main_title').val().trim() == '') {
    displayErrorMessage('The main title field is required.');
    return false;
  }

  if ($('#title_1').val().trim() == '') {
    displayErrorMessage('The title 1 field is required.');
    return false;
  }

  if ($('#description_1').val().trim() == '') {
    displayErrorMessage('The description 1 field is required.');
    return false;
  } else if ($('#description_1').val().length >= 122) {
    displayErrorMessage('The description 1 must not be greater than 122 characters.');
    return false;
  }

  if ($('#title_2').val().trim() == '') {
    displayErrorMessage('The title 2 field is required.');
    return false;
  }

  if ($('#description_2').val().trim() == '') {
    displayErrorMessage('The description 2 field is required.');
    return false;
  } else if ($('#description_2').val().length >= 122) {
    displayErrorMessage('The description 2 must not be greater than 122 characters.');
    return false;
  }

  if ($('#title_3').val().trim() == '') {
    displayErrorMessage('The title 3 field is required.');
    return false;
  }

  if ($('#description_3').val().trim() == '') {
    displayErrorMessage('The description 3 field is required.');
    return false;
  } else if ($('#description_3').val().length >= 122) {
    displayErrorMessage('The description 3 must not be greater than 122 characters.');
    return false;
  }

  $('#mainReasonSaveBtn').attr('disabled', true);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*********************************************************************!*\
  !*** ./resources/assets/js/subscription_plans/subscription_plan.js ***!
  \*********************************************************************/
document.addEventListener('turbo:load', loadSubscriptionPlanData);

function loadSubscriptionPlanData() {
  if (!$('#planTypeFilter').length) {
    return;
  }

  $('#planTypeFilter').select2({
    placeholder: 'Select Status'
  });
} // delete subscription record code


listenClick('.subscription-plan-delete-btn', function () {
  var deleteSubscriptionId = $(this).attr('data-id');
  var deleteSubscriptionUrl = route('subscription-plans.index') + '/' + deleteSubscriptionId;
  deleteItem(deleteSubscriptionUrl, Lang.get('js.subscription_plan'));
});
listenChange('.is_default', function (event) {
  var subscriptionPlanId = $(event.currentTarget).data('id');
  updateStatusToDefault(subscriptionPlanId);
});

window.updateStatusToDefault = function (id) {
  $.ajax({
    url: route('subscription-plans.index') + '/' + id + '/make-plan-as-default',
    method: 'post',
    cache: false,
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        livewire.emit('refresh');
      }
    }
  });
}; // reset filter modal code


listenClick('#resetFilter', function () {
  $('#planTypeFilter').val(0).trigger('change');
  $('#subscriptionPlanFilterBtn').dropdown('toggle');
}); // call filter data code

listenChange('#planTypeFilter', function () {
  window.livewire.emit('changeFilter', $(this).val());
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***************************************************************!*\
  !*** ./resources/assets/js/subscription_plans/create-edit.js ***!
  \***************************************************************/
document.addEventListener('turbo:load', loadSubscriptionPlanCreateEditData);

function loadSubscriptionPlanCreateEditData() {
  $('.price-input').trigger('input');
  $(window).on('beforeunload', function () {
    $('input[type=submit]').prop('disabled', 'disabled');
  });
  $('#createSubscriptionPlanForm, #editSubscriptionPlanForm').find('input:text:visible:first').focus();

  if (!$('#planType').length || !$('#currency').length) {
    return;
  }

  $('#planType').select2();
  $('#currency').select2();
}

listenSubmit('#createSubscriptionPlanForm, #editSubscriptionPlanForm', function () {
  $('#btnSave').attr('disabled', true);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*****************************************************************!*\
  !*** ./resources/assets/js/subscription_plans/plan_features.js ***!
  \*****************************************************************/
document.addEventListener('turbo:load', loadPlanFeatureData);

function loadPlanFeatureData() {
  // features selection script - starts
  var featureLength = $('.feature:checkbox:checked').length;
  featureChecked(featureLength);
}

window.featureChecked = function (featureLength) {
  var totalFeature = $('.feature:checkbox').length;

  if (featureLength === totalFeature) {
    $('#selectAll').prop('checked', true);
  } else {
    $('#selectAll').prop('checked', false);
  }
}; // script for selecting all features


listenClick('#selectAll', function () {
  if ($('#selectAll').is(':checked')) {
    $('.feature').each(function () {
      $(this).prop('checked', true);
    });
  } else {
    $('.feature').each(function () {
      $(this).prop('checked', false);
    });
  }
}); // script for selecting single feature

listenClick('.feature', function () {
  var featureLength = $('.feature:checkbox:checked').length;
  featureChecked(featureLength);
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**********************************************!*\
  !*** ./resources/assets/js/brands/brands.js ***!
  \**********************************************/
// open brand modal code
listenClick('.add-brand', function () {
  $('#createBrandModal').modal('show').appendTo('body');
}); // edit brand modal code

listenClick('.brand-edit-btn', function () {
  var id = $(this).attr('data-id');
  renderData(id);
});

function renderData(id) {
  $.ajax({
    url: route('brands.edit', id),
    type: 'GET',
    success: function success(result) {
      if (result.success) {
        $('#brandID').val(result.data.id);
        var brandLogo = result.data.brand_logo;
        $('#editBrandLogo').css('background-image', 'url("' + brandLogo + '")');
        $('#editBrandModal').modal('show').appendTo('body');
      }
    }
  });
} // add brand modal code


listenSubmit('#createBrandForm', function (e) {
  e.preventDefault();
  var loadingButton = jQuery(this).find('#addBrandBtn');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  var formData = new FormData(this);
  $.ajax({
    url: route('brands.store'),
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        $('#createBrandModal').modal('hide');
        $(loadingButton).attr('disabled', false);
        window.livewire.emit('refresh');
      }
    },
    error: function error(result) {
      $(loadingButton).attr('disabled', false);
      displayErrorMessage(result.responseJSON.message);
    }
  });
}); // update brand modal code

listenSubmit('#editBrandForm', function (e) {
  e.preventDefault();
  var loadingButton = jQuery(this).find('#editBrandBtn');
  loadingButton.button('loading');
  $(loadingButton).attr('disabled', true);
  var formData = new FormData(this);
  var id = $('#brandID').val();
  $.ajax({
    url: 'brands/' + id + '/update',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function success(result) {
      $('#editBrandModal').modal('hide');
      $(loadingButton).attr('disabled', false);
      displaySuccessMessage(result.message);
      livewire.emit('refresh');
    },
    error: function error(result) {
      $(loadingButton).attr('disabled', false);
      displayErrorMessage(result.responseJSON.message);
    }
  });
}); // delete brand record code

listenClick('.brand-delete-btn', function () {
  var recordId = $(this).attr('data-id');
  deleteItem(route('brands.destroy', recordId), Lang.get('js.front_band'));
}); // reset brand modal code

listenHiddenBsModal('#createBrandModal', function () {
  $('#bgImage').css('background-image', 'url(' + defaultImage + ')');
  resetModalForm('#createBrandForm', '#createBrandValidationErrorsBox');
});
listenHiddenBsModal('#editBrandModal', function () {
  resetModalForm('#editBrandForm', '#editBrandValidationErrorsBox');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**************************************************!*\
  !*** ./resources/assets/js/enquiries/enquiry.js ***!
  \**************************************************/
document.addEventListener('turbo:load', loadEnquiryData);

function loadEnquiryData() {
  if (!$('#enquiryStatusFilter').length) {
    return false;
  }

  $('#enquiryStatusFilter').select2({
    width: '100%',
    placeholder: Lang.get('js.select_status')
  });
} // status filter code


listenChange('.enquiry-status-filter', function () {
  window.livewire.emit('changeFilter', $(this).val());
}); // delete enquiry record code

listenClick('.enquiry-delete-btn', function () {
  var deleteEnquiryId = $(this).attr('data-id');
  deleteItem(route('enquiries.destroy', deleteEnquiryId), Lang.get('js.enquiry'));
}); // reset filter modal code

listenClick('#resetEnquiryStatusFilter', function () {
  window.livewire.emit('refresh');
  $('#enquiryStatusFilter').val(2).trigger('change');
  $('#enquiryStatusFilterBtn').dropdown('toggle');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*******************************************************!*\
  !*** ./resources/assets/js/subscribers/subscriber.js ***!
  \*******************************************************/
// subscriber record delete code
listenClick('.subscribe-delete-btn', function () {
  var deleteSubscriberId = $(this).attr('data-id');
  deleteItem(route('subscribers.destroy', deleteSubscriberId), Lang.get('js.subscriber'));
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***********************************************************!*\
  !*** ./resources/assets/js/front/about_us/create-edit.js ***!
  \***********************************************************/
document.addEventListener('turbo:load', loadAboutUsData);
var quill;

function loadAboutUsData() {
  if (!$('#aboutUsDescriptionId').length) {
    return;
  }

  quill = new Quill('#aboutUsDescriptionId', {
    modules: {
      toolbar: [[{
        header: [1, 2, false]
      }], ['bold', 'italic', 'underline'], ['image', 'code-block']]
    },
    placeholder: Lang.get('js.description'),
    theme: 'snow' // or 'bubble'

  });
  quill.on('text-change', function (delta, oldDelta, source) {
    if (quill.getText().trim().length === 0) {
      quill.setContents([{
        insert: ''
      }]);
    }
  });
  var element = document.createElement('textarea');
  element.innerHTML = $('#aboutUsData').val();
  quill.root.innerHTML = element.value;
}

listenSubmit('#aboutUsForm', function () {
  var element = document.createElement('textarea');
  var editor_content = quill.root.innerHTML;
  element.innerHTML = editor_content;

  if (quill.getText().trim().length === 0) {
    displayErrorMessage(Lang.get('js.description_required'));
    return false;
  }

  $('#aboutUsSaveBtn').attr('disabled', true);
  $('#aboutUsDescription').val(JSON.stringify(editor_content));
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*************************************************!*\
  !*** ./resources/assets/js/front/contact-us.js ***!
  \*************************************************/
// add enquiry code
listenSubmit('#contactForm', function (e) {
  e.preventDefault();
  $('#contactSubmitBtn').prop('disabled', true);
  $.ajax({
    url: route('enquiries.store'),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        $('#contactForm')[0].reset();
        $('#contactSubmitBtn').prop('disabled', false);
      }
    },
    error: function error(_error) {
      displayErrorMessage(_error.responseJSON.message);
      $('#contactForm')[0].reset();
      $('#contactSubmitBtn').prop('disabled', false);
    }
  });
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!***********************************************************!*\
  !*** ./resources/assets/js/subscriptions/subscription.js ***!
  \***********************************************************/
document.addEventListener('turbo:load', loadSubscriptionsData);

function loadSubscriptionsData() {
  loadSelect2();

  if (!$('#paymentType').length) {
    return false;
  }

  $('#paymentType').trigger('change');
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
} // cash payment JS code


listenClick('.payment-by-cash', function () {
  var formData = new FormData();
  var planID = $(this).data('id');
  var paymentAttachment = $('input[type="file"]')[0].files[0];
  var note = $('.payment-note').val();
  formData.append('plan_id', planID);

  if (typeof paymentAttachment !== 'undefined') {
    formData.append('payment_attachment', paymentAttachment);
  }

  formData.append('note', note);
  $(this).attr('disabled', true);
  $.ajax({
    url: route('cash.pay'),
    type: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    success: function success(result) {
      if (result.toastType == 'success') {
        window.location.href = result.url;
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    },
    complete: function complete() {
      $(this).attr('disabled', false);
    }
  });
}); // check attachment file validation JS code

listenChange('#paymentAttachment', function () {
  var ext = $(this).val().split('.').pop().toLowerCase();

  if ($.inArray(ext, ['png', 'jpg', 'jpeg', 'pdf']) == -1) {
    displayErrorMessage('The attachment must be a file of type: jpg, png, jpeg, pdf');
    $(this).val('');
    return false;
  }

  if (this.files[0].size >= 10485760) {
    displayErrorMessage('The maximum attachment size 10 mb allowed.');
    $(this).val('');
    return false;
  }
}); // stripe payment JS code

listenClick('.makePayment', function () {
  var _this = this;

  if (typeof getLoggedInUserdata != 'undefined' && getLoggedInUserdata == '') {
    window.location.href = logInUrl;
    return true;
  }

  var payloadData = {
    plan_id: $(this).data('id'),
    from_pricing: typeof fromPricing != 'undefined' ? fromPricing : null,
    price: $(this).data('plan-price'),
    payment_type: $('#paymentType option:selected').val()
  };
  $(this).addClass('disabled');
  $.post(route('purchase-subscription'), payloadData).done(function (result) {
    if (typeof result.data == 'undefined') {
      var toastMessageData = {
        'toastType': 'success',
        'toastMessage': result.message
      };
      paymentMessage(toastMessageData);
      setTimeout(function () {
        window.location.href = subscriptionPlans;
      }, 5000);
      return true;
    }

    var sessionId = result.data.sessionId;
    stripe.redirectToCheckout({
      sessionId: sessionId
    }).then(function (result) {
      $(this).html(subscribeText).removeClass('disabled');
      $('.makePayment').attr('disabled', false);
      var toastMessageData = {
        'toastType': 'error',
        'toastMessage': result.responseJSON.message
      };
      paymentMessage(toastMessageData);
    });
  })["catch"](function (error) {
    $(_this).html(subscribeText).removeClass('disabled');
    $('.makePayment').attr('disabled', false);
    var toastMessageData = {
      'toastType': 'error',
      'toastMessage': error.responseJSON.message
    };
    paymentMessage(toastMessageData);
  });
});
listenChange('#paymentType', function () {
  var paymentType = $(this).val();

  if (paymentType == 1) {
    $('.proceed-to-payment').addClass('d-none');
    $('.cash-payment').addClass('d-none');
    $('.stripePayment').removeClass('d-none');
    $('.cash-payment-note').addClass('d-none');
  }

  if (paymentType == 2) {
    $('.proceed-to-payment').addClass('d-none');
    $('.cash-payment').addClass('d-none');
    $('.paypalPayment').removeClass('d-none');
    $('.cash-payment-note').addClass('d-none');
  }

  if (paymentType == 3) {
    $('.stripePayment').addClass('d-none');
    $('.paypalPayment').addClass('d-none');
    $('.cash-payment').removeClass('d-none');
    $('.cash-payment-note').removeClass('d-none');
  }
}); // paypal payment JS code

listenClick('.paymentByPaypal', function () {
  var pricing = typeof fromPricing != 'undefined' ? fromPricing : null;
  $(this).addClass('disabled');
  $.ajax({
    type: 'GET',
    url: route('user.paypal.init'),
    data: {
      'planId': $(this).data('id'),
      'from_pricing': pricing,
      'payment_type': $('#paymentType option:selected').val()
    },
    success: function success(result) {
      if (result.status == 'CREATED') {
        var redirectTo = '';
        $.each(result.links, function (key, val) {
          if (val.rel == 'approve') {
            redirectTo = val.href;
          }
        });
        location.href = redirectTo;
      } else {
        location.href = result.url;
      }
    },
    error: function error(result) {},
    complete: function complete() {}
  });
});

function loadSelect2() {
  if (!$('.subscription-status').length) {
    return false;
  }

  $('.subscription-status').select2();
}

listenChange('.change-subscription-status', function () {
  var id = $(this).data('id');
  var status = $(this).val();
  window.livewire.emit('changeStatus', id, status);
});
window.addEventListener('changeStatusEvent', function (event) {
  displaySuccessMessage('Status updated successfully.');
});
listenClick('.get-cash-payment-note', function () {
  var userTransactionId = $(this).data('id');
  window.livewire.emit('getNoteData', userTransactionId);
});
window.addEventListener('retrieveNoteData', function (event) {
  $('.cash-payment-note').text(event.detail);
  $('#cashPaymentNoteModal').modal('show');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!****************************************************************!*\
  !*** ./resources/assets/js/subscriptions/free-subscription.js ***!
  \****************************************************************/
document.addEventListener('turbo:load', loadFreeSubscriptionData);

function loadFreeSubscriptionData() {
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
} // listenClick('.freePayment', function () {
//     if (typeof getLoggedInUserdata != 'undefined' && getLoggedInUserdata ==
//         '') {
//         window.location.href = logInUrl
//         return true
//     }
//     if ($(this).data('plan-price') === 0) {
//         $(this).addClass('disabled')
//         let data = {
//             plan_id: $(this).data('id'),
//             price: $(this).data('plan-price'),
//         }
//         $.post(route('purchase-subscription'), data).done((result) => {
//             let toastMessageData = {
//                 'toastType': 'success',
//                 'toastMessage': result.message,
//             }
//             paymentMessage(toastMessageData)
//             setTimeout(function () {
//                 location.reload()
//             }, 5000)
//         }).catch(error => {
//             $(this).html(subscribeText).removeClass('disabled')
//             $('.freePayment').attr('disabled', false)
//             let toastMessageData = {
//                 'toastType': 'error',
//                 'toastMessage': error.responseJSON.message,
//             }
//             paymentMessage(toastMessageData)
//         })
//         return true
//     }
// })
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*********************************************************************!*\
  !*** ./resources/assets/js/subscriptions/user-free-subscription.js ***!
  \*********************************************************************/
document.addEventListener('turbo:load', loadUserFreeSubscriptionData);

function loadUserFreeSubscriptionData() {
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
  });
}

listenClick('.freePayment', function () {
  var _this = this;

  if (typeof getLoggedInUserdata != 'undefined' && getLoggedInUserdata == '') {
    window.location.href = logInUrl;
    return true;
  }

  if ($(this).data('plan-price') === 0) {
    $(this).addClass('disabled');
    var data = {
      plan_id: $(this).data('id'),
      price: $(this).data('plan-price')
    };
    $.post(route('purchase-subscription'), data).done(function (result) {
      displaySuccessMessage(result.message);
      setTimeout(function () {
        location.reload();
      }, 5000);
    })["catch"](function (error) {
      $(_this).html(Lang.get('js.choose_plan')).removeClass('disabled');
      $('.freePayment').attr('disabled', false);
      displayErrorMessage(error.responseJSON.message);
    });
    return true;
  }
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**************************************************************!*\
  !*** ./resources/assets/js/subscriptions/payment-message.js ***!
  \**************************************************************/
document.addEventListener('turbo:load', loadSubscriptionData);

function loadSubscriptionData() {
  if (!$('#paymentType').length) {
    return;
  }

  $('#paymentType').select2();
}

window.paymentMessage = function () {
  var data = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
  var toastData = $('#toastDataId').val();
  toastData = data != null ? data : toastData;

  if (!isEmpty(toastData)) {
    setTimeout(function () {
      swal({
        title: toastData.toastType,
        icon: toastData.toastType,
        text: toastData.toastMessage,
        type: 'success',
        timer: 4000
      });
    }, 1000);
  }
};

paymentMessage();
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!**************************************************!*\
  !*** ./resources/assets/js/settings/settings.js ***!
  \**************************************************/
document.addEventListener('turbo:load', loadSettingData);

function loadSettingData() {
  var stripeCheckbox = $('#stripeCheckboxBtn').is(':checked');

  if (stripeCheckbox) {
    $('.stripe_div').removeClass('d-none');
  } else {
    $('.stripe_div').addClass('d-none');
  }

  var paypalCheckbox = $('#paypalCheckboxBtn').is(':checked');

  if (paypalCheckbox) {
    $('.paypal_div').removeClass('d-none');
  } else {
    $('.paypal_div').addClass('d-none');
  }

  if (!$('#settingCurrencyId').length) {
    return;
  }

  $('#settingCurrencyId').select2();
  var input = document.querySelector('#defaultCountryData');
  var intl = window.intlTelInput(input, {
    initialCountry: defaultCountryCodeValue,
    separateDialCode: true,
    preferredCountries: false,
    geoIpLookup: function geoIpLookup(success, failure) {
      $.get('https://ipinfo.io', function () {}, 'jsonp').always(function (resp) {
        var countryCode = resp && resp.country ? resp.country : '';
        success(countryCode);
      });
    },
    utilsScript: '../../public/assets/js/inttel/js/utils.min.js'
  });
  var getCode = intl.selectedCountryData['name'] + '+' + intl.selectedCountryData['dialCode'];
  $('#defaultCountryData').val(getCode);
}

listenChange('#autoDetectLocation', function () {
  var isChecked = $(this).is(':checked');

  if (isChecked) {
    $('.place-api-div').removeClass('d-none');
  } else {
    $('.place-api-div').addClass('d-none');
  }
});
listenSubmit('#generalSettingForm', function (e) {
  e.preventDefault();
  var checked = $('#autoDetectLocation').is(':checked');

  if (checked && $('#googlePlaceApiKey').val() == '') {
    displayErrorMessage(Lang.get('js.enter_google_api'));
    return false;
  }

  $('#generalSettingForm')[0].submit();
  $('#settingSaveBtn').attr('disabled', true);
});
listenClick('.iti__standard', function () {
  $('#defaultCountryData').val($(this).text());
  $(this).attr('data-country-code');
  $('#defaultCountryCode').val($(this).attr('data-country-code'));
});
listenChange('#stripeCheckboxBtn', function () {
  var stripeCheckboxIsChecked = $(this).is(':checked');

  if (stripeCheckboxIsChecked) {
    $('.stripe_div').removeClass('d-none');
  } else {
    $('.stripe_div').addClass('d-none');
  }
});
listenChange('#paypalCheckboxBtn', function () {
  var paypalCheckboxIsChecked = $(this).is(':checked');

  if (paypalCheckboxIsChecked) {
    $('.paypal_div').removeClass('d-none');
  } else {
    $('.paypal_div').addClass('d-none');
  }
});
listenSubmit('#credentialsSettings', function (e) {
  e.preventDefault();
  var stripeCheckbox = $('#stripeCheckboxBtn').is(':checked');
  var paypalCheckbox = $('#paypalCheckboxBtn').is(':checked');
  var emptyStripeKey = $('#stripeKey').val().trim();
  var emptyStripeSecret = $('#stripeSecret').val().trim();

  if (stripeCheckbox) {
    if (isEmpty(emptyStripeKey)) {
      displayErrorMessage(Lang.get('js.stripe_key'));
      return false;
    } else if (isEmpty(emptyStripeSecret)) {
      displayErrorMessage(Lang.get('js.stripe_secret'));
      return false;
    }
  }

  var emptyPaypalId = $('#paypalClientId').val().trim();
  var emptyPaypalSecret = $('#paypalSecret').val().trim();
  var emptyPaypalMode = $('#paypalMode').val().trim();

  if (paypalCheckbox) {
    if (isEmpty(emptyPaypalId)) {
      displayErrorMessage(Lang.get('js.paypal_client'));
      return false;
    } else if (isEmpty(emptyPaypalSecret)) {
      displayErrorMessage(Lang.get('js.paypal_secret'));
      return false;
    } else if (isEmpty(emptyPaypalMode)) {
      displayErrorMessage(Lang.get('js.paypal_mode'));
      return false;
    }
  }

  $('#credentialSettingBtn').attr('disabled', true);
  $('#credentialsSettings')[0].submit();
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*******************************************************!*\
  !*** ./resources/assets/js/settings/user_settings.js ***!
  \*******************************************************/
document.addEventListener('turbo:load', loadUserSettingData);

function loadUserSettingData() {
  var userStripeCheckbox = $('#userStripeCheckboxBtn').is(':checked');

  if (userStripeCheckbox) {
    $('.user_stripe_div').removeClass('d-none');
  } else {
    $('.user_stripe_div').addClass('d-none');
  }

  var userPaypalCheckbox = $('#userPaypalCheckboxBtn').is(':checked');

  if (userPaypalCheckbox) {
    $('.user_paypal_div').removeClass('d-none');
  } else {
    $('.user_paypal_div').addClass('d-none');
  }
}

listenChange('#userStripeCheckboxBtn', function () {
  var userStripeCheckbox = $('#userStripeCheckboxBtn').is(':checked');

  if (userStripeCheckbox) {
    $('.user_stripe_div').removeClass('d-none');
  } else {
    $('.user_stripe_div').addClass('d-none');
  }
});
listenChange('#userPaypalCheckboxBtn', function () {
  var userPaypalCheckbox = $('#userPaypalCheckboxBtn').is(':checked');

  if (userPaypalCheckbox) {
    $('.user_paypal_div').removeClass('d-none');
  } else {
    $('.user_paypal_div').addClass('d-none');
  }
}); // update user credentials setting code

listenSubmit('#UserCredentialsSettings', function (e) {
  e.preventDefault();
  var userStripeCheckbox = $('#userStripeCheckboxBtn').is(':checked');
  var userPaypalCheckbox = $('#userPaypalCheckboxBtn').is(':checked');

  if (userStripeCheckbox && $('#UserStripeKey').val() == '') {
    displayErrorMessage(Lang.get('js.stripe_key'));
    return false;
  }

  if (userStripeCheckbox && $('#UserStripeSecret').val() == '') {
    displayErrorMessage(Lang.get('js.stripe_secret'));
    return false;
  }

  if (userPaypalCheckbox && $('#UserPaypalClientId').val() == '') {
    displayErrorMessage(Lang.get('js.paypal_client'));
    return false;
  }

  if (userPaypalCheckbox && $('#userPaypalSecret').val() == '') {
    displayErrorMessage(Lang.get('js.paypal_secret'));
    return false;
  }

  if (userPaypalCheckbox && $('#UserPaypalMode').val() == '') {
    displayErrorMessage(Lang.get('js.paypal_mode'));
    return false;
  }

  $('#credentialSaveBtn').attr('disabled', true);
  $('#UserCredentialsSettings')[0].submit();
});
listenSubmit('#generalUserSettingForm', function () {
  $('#generalSettingSaveBtn').attr('disabled', true);
  var calendarView = $('.img-border').attr('data-calendar-val');
  $('#calendarView').val(calendarView);
});
listenClick('.img-radio', function () {
  $('.img-radio').removeClass('img-border');
  $(this).addClass('img-border');
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!****************************************************************!*\
  !*** ./resources/assets/js/google_calendar/google_calendar.js ***!
  \****************************************************************/
// Sync google calendar code
listenClick('#syncGoogleCalendar', function () {
  var btnSubmitEle = $(this);
  setBtnLoader(btnSubmitEle);
  $.ajax({
    url: route('syncGoogleCalendarList'),
    type: 'GET',
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        setTimeout(function () {
          location.reload();
        }, 1200);
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    },
    complete: function complete() {
      setBtnLoader(btnSubmitEle);
    }
  });
}); // Store google calendar code

listenSubmit('#googleCalendarForm', function (e) {
  e.preventDefault();

  if (!$('.google-calendar').is(':checked')) {
    displayErrorMessage(Lang.get('js.select_calender'));
    return;
  }

  var btnSubmitEle = $('#googleCalendarSubmitBtn');
  setBtnLoader(btnSubmitEle);
  $.ajax({
    url: route('event.google.calendar.store'),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        setTimeout(function () {
          location.reload();
        }, 1200);
      }
    },
    error: function error(_error) {
      displayErrorMessage(_error.responseJSON.message);
    },
    complete: function complete() {
      setBtnLoader(btnSubmitEle);
    }
  });
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!************************************************************************!*\
  !*** ./resources/assets/js/sidebar-menu-search/sidebar-menu-search.js ***!
  \************************************************************************/
document.addEventListener('turbo:load', loadSidebarMenuData);

function loadSidebarMenuData() {
  // image load component
  IOInitImageComponent(); // SideBar asideMenu initialize

  IOInitSidebar();
  var activeMenu = $(document).find('.sidebar-menu li.active');
  var activeDropdown = $(document).find('.sidebar-menu li.active');
  var activeDropdownMenu = $(activeDropdown).parents('.nav-item ul');
  var $block = $('.no-results');
  listenKeyup('#searchText', function () {
    var searchText = $(this).val();
    var isMatch = false;
    var value = this.value.toLowerCase().trim();
    listenClick('.close-sign', function () {
      $('#searchText').val('');
      $('.nav-item').show().filter(function () {
        if (searchText != '') {
          $(this).removeClass('active');
        }
      });
      $('.close-sign').hide();
      $('.search-sign').show();
      $('.no-results').addClass('d-none');
      toggleSubMenu();
    });
    $('.nav-item').show().filter(function () {
      $(this).addClass('active');

      if (searchText == '') {
        $(this).removeClass('active');
        $('.close-sign').hide();
        $('.search-sign').show();
        toggleSubMenu();
      }

      if ($(this).text().toLowerCase().trim().indexOf(value) == -1) {
        $(this).hide();
        $('.close-sign').show();
        $('.search-sign').hide();
      } else {
        isMatch = true;
        $(this).show();
      }
    });
    $('.no-results').removeClass('d-none');
    $('.no-results').toggle(!isMatch);
  });

  window.toggleSubMenu = function () {
    var hasClassNames = $(document).find('.nav-item');
    if (hasClassNames.hasClass('dropdown-menu')) $('.dropdown-menu').css('display', 'none');
    $(activeMenu).addClass('active');
    $(activeDropdown).parents(activeDropdown).addClass('active');
    $(activeDropdownMenu).css({
      'display': 'block'
    });
  };
}
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!******************************************************!*\
  !*** ./resources/assets/js/currencies/currencies.js ***!
  \******************************************************/
listenClick('#createCurrency', function () {
  $('#createCurrencyModal').modal('show').appendTo('body');
});
listen('hidden.bs.modal', '#createCurrencyModal', function () {
  resetModalForm('#createCurrencyForm', '#createCurrencyValidationErrorsBox');
});
listen('hidden.bs.modal', '#editCurrencyModal', function () {
  resetModalForm('#editCurrencyForm', '#editCurrencyValidationErrorsBox');
});
listenClick('.currency-edit-btn', function () {
  var editCurrencyId = $(this).attr('data-id');
  renderData(editCurrencyId);
});

function renderData(id) {
  $.ajax({
    url: route('currencies.edit', id),
    type: 'GET',
    success: function success(result) {
      $('#currencyID').val(result.data.id);
      $('#editCurrency_Name').val(result.data.currency_name);
      $('#editCurrency_Icon').val(result.data.currency_icon);
      $('#editCurrency_Code').val(result.data.currency_code);
      $('#editCurrencyModal').modal('show');
    }
  });
}

listenSubmit('#createCurrencyForm', function (e) {
  e.preventDefault();
  $.ajax({
    url: route('currencies.store'),
    type: 'POST',
    data: $(this).serialize(),
    success: function success(result) {
      if (result.success) {
        displaySuccessMessage(result.message);
        $('#createCurrencyModal').modal('hide');
        window.livewire.emit('refresh');
      }
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    }
  });
});
listenSubmit('#editCurrencyForm', function (e) {
  e.preventDefault();
  var updateCurrencyId = $('#currencyID').val();
  $.ajax({
    url: route('currencies.update', updateCurrencyId),
    type: 'PUT',
    data: $(this).serialize(),
    success: function success(result) {
      $('#editCurrencyModal').modal('hide');
      displaySuccessMessage(result.message);
      livewire.emit('refresh');
    },
    error: function error(result) {
      displayErrorMessage(result.responseJSON.message);
    },
    complete: function complete() {}
  });
});
listenClick('.currency-delete-btn', function () {
  var currencyRecordId = $(this).attr('data-id');
  deleteItem(route('currencies.destroy', currencyRecordId), Lang.get('js.currency'));
});
})();

// This entry need to be wrapped in an IIFE because it need to be isolated against other entry modules.
(() => {
/*!*******************************************************!*\
  !*** ./resources/assets/js/location/live-location.js ***!
  \*******************************************************/
(function (window, $) {
  var START_URL_TEMPLATE = '/events/__EVENT_ID__/location/live/start';
  var STOP_URL_TEMPLATE = '/events/__EVENT_ID__/location/live/stop';
  var UPDATE_URL_TEMPLATE = '/events/__EVENT_ID__/location/live/update';
  var SESSION_CHECK_URL = '/location/live-session';
  var DISTANCE_THRESHOLD_METERS = 150;
  var HEARTBEAT_INTERVAL_MS = 1 * 60 * 1000;
  var STORAGE_ACTIVE_KEY = 'callalink_live_location_active';
  var STORAGE_EVENT_KEY = 'callalink_live_location_event_id';
  var watchId = null;
  var updateTimer = null;
  var latestPosition = null;
  var activeEventId = null;
  var lastSentPosition = null;

  function buildUrl(template, eventId) {
    return template.replace('__EVENT_ID__', eventId);
  }

  function csrfToken() {
    return $('meta[name="csrf-token"]').attr('content');
  }

  function postJson(url, data) {
    return $.ajax({
      url: url,
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken()
      },
      data: data
    });
  }

  function sendUpdate() {
    if (!latestPosition || !activeEventId) {
      // console.log('[CallaLink] sendUpdate skipped — no position or no active event'); 
      return;
    }

    var coords = latestPosition.coords; // console.log('[CallaLink] sending update:', coords.latitude, coords.longitude, coords.accuracy);

    postJson(buildUrl(UPDATE_URL_TEMPLATE, activeEventId), {
      latitude: coords.latitude,
      longitude: coords.longitude,
      accuracy: coords.accuracy
    }).done(function (res) {// console.log('[CallaLink] update ACK:', res);
    }).fail(function (xhr) {
      // console.error('[CallaLink] update FAILED:', xhr.status, xhr.responseText);
      if (xhr.status === 409 || xhr.status === 404) {
        deactivateTracking();
      }
    });
  }

  function fetchAndSendImmediate() {
    if (!navigator.geolocation) {
      return;
    }

    navigator.geolocation.getCurrentPosition(function (position) {
      latestPosition = position;
      sendUpdate();
    }, function () {}, {
      enableHighAccuracy: true,
      maximumAge: 0
    });
  }

  function beginWatching() {
    if (!navigator.geolocation || watchId !== null) {
      // console.log('[CallaLink] beginWatching skipped — already watching or no geolocation'); 
      return;
    } // console.log('[CallaLink] beginWatching: starting watch + timer');


    watchId = navigator.geolocation.watchPosition(function (position) {
      maybeSend(position); // console.log('[CallaLink] position updated:', position.coords.latitude, position.coords.longitude, position.coords.accuracy); 
    }, function (err) {// console.error('[CallaLink] watchPosition ERROR:', err.code, err.message); 
    }, {
      enableHighAccuracy: true,
      maximumAge: 0
    });
    updateTimer = setInterval(function () {
      // console.log('[CallaLink] heartbeat interval fired');
      sendUpdate();
    }, HEARTBEAT_INTERVAL_MS);
  }

  function stopWatching() {
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }

    if (updateTimer !== null) {
      clearInterval(updateTimer);
      updateTimer = null;
    }

    latestPosition = null;
    lastSentPosition = null;
  }

  function deactivateTracking() {
    stopWatching();
    activeEventId = null;
    localStorage.removeItem(STORAGE_ACTIVE_KEY);
    localStorage.removeItem(STORAGE_EVENT_KEY);
  }

  function startSharing(eventId) {
    return postJson(buildUrl(START_URL_TEMPLATE, eventId), {}).done(function () {
      activeEventId = eventId;
      localStorage.setItem(STORAGE_ACTIVE_KEY, '1');
      localStorage.setItem(STORAGE_EVENT_KEY, String(eventId));
      fetchAndSendImmediate();
      beginWatching();
    });
  }

  function stopSharing(eventId) {
    return postJson(buildUrl(STOP_URL_TEMPLATE, eventId), {}).done(function () {
      deactivateTracking();
    });
  }

  function restoreSessionIfActive() {
    $.get(SESSION_CHECK_URL).done(function (response) {
      var data = response.data || response; // console.log('[CallaLink] session check:', data);

      if (data.active) {
        var previousEventId = localStorage.getItem(STORAGE_EVENT_KEY);
        var isNewActivation = String(previousEventId) !== String(data.event_id); // console.log('[CallaLink] session active, isNewActivation:', isNewActivation); 

        activeEventId = data.event_id;
        localStorage.setItem(STORAGE_ACTIVE_KEY, '1');
        localStorage.setItem(STORAGE_EVENT_KEY, String(data.event_id));

        if (isNewActivation) {
          fetchAndSendImmediate();
        }

        beginWatching();
      } else {
        // console.log('[CallaLink] session inactive, deactivating');
        deactivateTracking();
      }
    }).fail(function (xhr) {// console.error('[CallaLink] session check FAILED:', xhr.status, xhr.responseText); 
    });
  }

  function distanceInMeters(lat1, lon1, lat2, lon2) {
    var R = 6371000;
    var dLat = (lat2 - lat1) * Math.PI / 180;
    var dLon = (lon2 - lon1) * Math.PI / 180;
    var a = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.pow(Math.sin(dLon / 2), 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  }

  function maybeSend(position) {
    latestPosition = position;

    if (!lastSentPosition) {
      sendUpdate();
      lastSentPosition = position;
      return;
    }

    var moved = distanceInMeters(lastSentPosition.coords.latitude, lastSentPosition.coords.longitude, position.coords.latitude, position.coords.longitude);

    if (moved >= DISTANCE_THRESHOLD_METERS) {
      sendUpdate();
      lastSentPosition = position;
    }
  }

  document.addEventListener('turbo:load', restoreSessionIfActive);
  window.CallaLinkLiveLocation = {
    startSharing: startSharing,
    stopSharing: stopSharing
  };
})(window, jQuery);
})();

/******/ })()
;