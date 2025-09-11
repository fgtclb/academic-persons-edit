(function() {
  const editorConfig = {
    language: 'en',
    height: 200,
    versionCheck: false,
    format_tags: 'p',
    toolbarGroups: [
      { name: 'basicstyles', groups: [ 'basicstyles' ] },
      { name: 'paragraph', groups: [ 'list' ] },
      { name: 'clipboard', groups: [ 'cleanup' ] }
    ],
    customConfig: '',
    removeButtons: [
        'Strike',
        'Subscript',
        'Superscript'
    ]
  };

  const waitCKEDITOR = setInterval(function() {
    if (window.CKEDITOR) {
      clearInterval(waitCKEDITOR);

      document.querySelectorAll('.rich-text').forEach((textarea) => {
        CKEDITOR.replace(textarea.getAttribute('id'), editorConfig);
      });
    }
  }, 100);
})();
