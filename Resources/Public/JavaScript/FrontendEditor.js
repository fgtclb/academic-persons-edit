(function() {

  var editorConfig = {
    language: 'en',
    height: 350,
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

  var waitCKEDITOR = setInterval(function() {
    console.log('Wait for ckeditor');
    if (window.CKEDITOR) {
      clearInterval(waitCKEDITOR);
      CKEDITOR.replace('profile-teaching-area', editorConfig);
      CKEDITOR.replace('profile-core-competences', editorConfig);
      CKEDITOR.replace('profile-memberships', editorConfig);
      CKEDITOR.replace('profile-supervised-thesis', editorConfig);
      CKEDITOR.replace('profile-supervised-doctoral-thesis', editorConfig);
      CKEDITOR.replace('profile-vita', editorConfig);
      CKEDITOR.replace('profile-publications', editorConfig);
      CKEDITOR.replace('profile-miscellaneous', editorConfig);
    }
  }, 100);
})();
