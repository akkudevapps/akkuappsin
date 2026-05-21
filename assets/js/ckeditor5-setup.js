/**
 * CKEditor 5 Setup Helper
 * Initializes and manages CKEditor 5 instances
 */

const CKEditor5Setup = {
    // Configuration for CKEditor 5
    editorConfig: {
        toolbar: {
            items: [
                'heading',
                '|',
                'bold',
                'italic',
                'underline',
                'strikethrough',
                'code',
                '|',
                'link',
                'imageUpload',
                'mediaEmbed',
                'insertTable',
                'blockQuote',
                'codeBlock',
                '|',
                'bulletedList',
                'numberedList',
                'outdent',
                'indent',
                '|',
                'undo',
                'redo',
                'findAndReplace'
            ],
            shouldNotGroupWhenFull: true
        },
        heading: {
            options: [
                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
            ]
        },
        image: {
            resizeUnit: '%',
            resizeOptions: [
                {
                    name: 'resizeImage:original',
                    label: 'Original',
                    value: null
                },
                {
                    name: 'resizeImage:50',
                    label: '50%',
                    value: '50'
                },
                {
                    name: 'resizeImage:75',
                    label: '75%',
                    value: '75'
                }
            ],
            toolbar: [
                'imageTextAlternative',
                'imageStyle:inline',
                'imageStyle:block',
                'imageStyle:side',
                'resizeImage'
            ]
        },
        table: {
            contentToolbar: [
                'tableColumn',
                'tableRow',
                'mergeTableCells'
            ]
        },
        language: 'en',
        licenseKey: '' // Leave empty for open-source
    },

    // Initialize editor in a specific element
    initEditor: function(elementId, uploadUrl = '/admin/news.php?action=upload_file&folder=articles') {
        return new Promise((resolve, reject) => {
            const element = document.getElementById(elementId);
            if (!element) {
                reject('Element not found: ' + elementId);
                return;
            }

            // Add image upload adapter
            this.editorConfig.simpleUpload = {
                uploadUrl: uploadUrl,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            // Initialize CKEditor 5
            ClassicEditor
                .create(element, this.editorConfig)
                .then(editor => {
                    console.log('CKEditor 5 initialized successfully');
                    resolve(editor);
                })
                .catch(error => {
                    console.error('CKEditor 5 initialization error:', error);
                    reject(error);
                });
        });
    },

    // Get editor data
    getEditorData: function(editor) {
        return editor.getData();
    },

    // Set editor data
    setEditorData: function(editor, data) {
        editor.setData(data);
    },

    // Destroy editor
    destroyEditor: function(editor) {
        if (editor) {
            editor.destroy().catch(error => console.log('Error destroying editor:', error));
        }
    },

    // Enable/disable editor
    setEditorReadOnly: function(editor, isReadOnly) {
        editor.isReadOnly = isReadOnly;
    }
};

// Expose globally
window.CKEditor5Setup = CKEditor5Setup;
