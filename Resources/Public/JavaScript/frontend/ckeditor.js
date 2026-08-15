/* Generated from Resources/Private/TypeScript — do not edit. */
"use strict";
(() => {
  // packages/fgtclb/academic-persons-edit/Resources/Private/TypeScript/frontend/ckeditor.ts
  var editorConfig = {
    language: "en",
    height: 200,
    versionCheck: false,
    format_tags: "p",
    toolbarGroups: [
      { name: "basicstyles", groups: ["basicstyles"] },
      { name: "paragraph", groups: ["list"] },
      { name: "clipboard", groups: ["cleanup"] }
    ],
    customConfig: "",
    removeButtons: [
      "Strike",
      "Subscript",
      "Superscript"
    ]
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
