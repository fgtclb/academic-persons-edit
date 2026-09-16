/* Generated from Resources/Private/TypeScript — do not edit. */
"use strict";
(() => {
  // packages/fgtclb/academic-persons-edit/Resources/Private/TypeScript/frontend/ckeditor.ts
  var blockElements = ["p", "ul", "ol", "li", "h2", "h3", "h4", "h5", "h6"];
  var editorLanguage = () => {
    const declared = document.documentElement.lang.trim().toLowerCase();
    const primarySubtag = declared.split(/[-_]/)[0] ?? "";
    return primarySubtag === "" ? "en" : primarySubtag;
  };
  var applyWriterRules = (instance) => {
    var _a;
    const writer = (_a = instance.dataProcessor) == null ? void 0 : _a.writer;
    if (writer === void 0) {
      return;
    }
    blockElements.forEach((tagName) => {
      writer.setRules(tagName, {
        breakAfterOpen: false,
        breakBeforeClose: false,
        breakAfterClose: true,
        indent: false
      });
    });
  };
  var editorConfig = {
    language: editorLanguage(),
    height: 200,
    versionCheck: false,
    format_tags: "p",
    toolbarGroups: [
      { name: "basicstyles", groups: ["basicstyles"] },
      { name: "paragraph", groups: ["list"] },
      // The "standard" build shipped by the CDN carries the link plugin
      // already, so the button is a matter of the toolbar group alone. The
      // group is the same one the backend preset for profile information uses
      // (`EXT:academic_persons/Configuration/CKEditor/LinkOnly.yaml`).
      { name: "links", groups: ["links"] },
      { name: "clipboard", groups: ["cleanup"] }
    ],
    // An anchor is a link into a page this editor does not control, and the
    // backend preset removes the button for the same reason.
    customConfig: "",
    removeButtons: [
      "Anchor",
      "Strike",
      "Subscript",
      "Superscript"
    ],
    // The advanced tab sets ids, styles and classes on the link. None of them
    // survive the rendering of the public views, so offering them would only
    // produce values that disappear.
    linkShowAdvancedTab: false,
    on: {
      instanceReady(event) {
        applyWriterRules(event.editor);
      }
    }
  };
  var editor = () => window.CKEDITOR;
  var waitForEditor = window.setInterval(() => {
    const ckeditor = editor();
    if (ckeditor === void 0) {
      return;
    }
    window.clearInterval(waitForEditor);
    document.querySelectorAll(".rich-text").forEach((textarea) => {
      const identifier = textarea.getAttribute("id");
      if (identifier !== null) {
        ckeditor.replace(identifier, editorConfig);
      }
    });
  }, 100);
})();
