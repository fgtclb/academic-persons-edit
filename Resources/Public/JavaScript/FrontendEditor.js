(function() {

  var editorConfig = {
    language: 'en',
    height: 350,
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

  var editorConfigLinkOnly = {
    language: 'en',
    height: 350,
    format_tags: 'p',
    toolbarGroups: [
      { name: 'links' },
      { name: 'clipboard', groups: [ 'cleanup' ] }
    ],
    customConfig: '',
    removeButtons: [
      'Anchor',
    ]
  };

  var waitCKEDITOR = setInterval(function() {
    console.log('Wait for ckeditor');
    if (window.CKEDITOR) {
      clearInterval(waitCKEDITOR);
      CKEDITOR.replace('profile-teaching-area', editorConfig);
      CKEDITOR.replace('profile-core-competences', editorConfig);
      CKEDITOR.replace('profile-supervised-thesis', editorConfig);
      CKEDITOR.replace('profile-supervised-doctoral-thesis', editorConfig);
      CKEDITOR.replace('profile-miscellaneous', editorConfig);
      document.querySelectorAll('.publication-bodytext').forEach((textarea) => {
        CKEDITOR.replace(textarea, editorConfigLinkOnly);
      });
    }
  }, 100);
})();
