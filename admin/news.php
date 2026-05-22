<?php
// Your existing PHP session and authentication code here
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: login.php');
//     exit;
// }

// Your existing database connection and backend logic
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Blog Editor - AkkuApps Admin</title>
    
    <!-- Your existing CSS files -->
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    
    <style>
        /* Enhanced styles for CKEditor 5 */
        :root {
            --primary: #4f46e5;
            --secondary: #10b981;
            --purple: #8b5cf6;
            --info: #3b82f6;
            --border-color: #e5e7eb;
            --border-radius-lg: 12px;
            --transition-speed: 0.3s;
            --text-muted: #9ca3af;
            --text-secondary: #6b7280;
        }

        /* Animated gradient borders */
        .animated-border {
            position: relative;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
        }

        .animated-border::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--primary), var(--secondary), var(--purple), var(--info));
            background-size: 400% 400%;
            z-index: -1;
            animation: gradientShift 8s ease infinite;
            border-radius: inherit;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* CKEditor container styling */
        .editor-container {
            margin: 20px 0;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            transition: all var(--transition-speed);
            background: white;
        }

        .editor-container:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.1);
        }

        /* Enhanced form controls */
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            transition: all var(--transition-speed);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            transform: translateY(-1px);
        }

        .form-control:hover:not(:focus) {
            border-color: var(--text-muted);
        }

        /* Enhanced buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left var(--transition-speed);
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .btn:active {
            transform: scale(0.95);
        }

        /* Form layout */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        /* CKEditor 5 custom styling */
        .ck-editor__editable {
            min-height: 400px;
            max-height: 600px;
        }

        .ck.ck-editor {
            width: 100%;
        }

        /* Character counter styling */
        .character-counter {
            text-align: right;
            padding: 8px 12px;
            color: var(--text-muted);
            font-size: 14px;
            background: #f9fafb;
            border-top: 1px solid var(--border-color);
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-draft {
            background: #fef3c7;
            color: #92400e;
        }

        .status-published {
            background: #d1fae5;
            color: #065f46;
        }

        /* Preview section */
        .preview-section {
            margin-top: 32px;
            padding: 24px;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            background: #f9fafb;
        }

        .preview-section h3 {
            margin-bottom: 16px;
            color: #374151;
        }

        /* Loading overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>📰 News & Blog Editor</h1>
            <p>Create and manage articles with advanced rich text editing</p>
        </div>

        <div class="content-wrapper">
            <form id="articleForm" method="POST" action="save_article.php" enctype="multipart/form-data">
                
                <!-- Article Type -->
                <div class="form-group">
                    <label for="articleType">Article Type</label>
                    <select id="articleType" name="article_type" class="form-control" required>
                        <option value="news">📰 News</option>
                        <option value="blog">📝 Blog</option>
                    </select>
                </div>

                <!-- Title -->
                <div class="form-group">
                    <label for="articleTitle">Title *</label>
                    <input type="text" 
                           id="articleTitle" 
                           name="title" 
                           class="form-control" 
                           placeholder="Enter article title..." 
                           required 
                           maxlength="200">
                </div>

                <!-- Subtitle -->
                <div class="form-group">
                    <label for="articleSubtitle">Subtitle</label>
                    <input type="text" 
                           id="articleSubtitle" 
                           name="subtitle" 
                           class="form-control" 
                           placeholder="Enter subtitle (optional)" 
                           maxlength="300">
                </div>

                <!-- Featured Image -->
                <div class="form-group">
                    <label for="featuredImage">Featured Image</label>
                    <input type="file" 
                           id="featuredImage" 
                           name="featured_image" 
                           class="form-control" 
                           accept="image/*">
                    <small style="color: var(--text-muted);">Recommended size: 1200x630px</small>
                </div>

                <!-- Content Editor -->
                <div class="form-group">
                    <label for="editor">Content *</label>
                    <div class="editor-container animated-border">
                        <textarea id="editor" name="content"></textarea>
                    </div>
                    <div class="character-counter" id="charCounter">0 characters</div>
                </div>

                <!-- SEO Meta Description -->
                <div class="form-group">
                    <label for="metaDescription">SEO Meta Description</label>
                    <textarea id="metaDescription" 
                              name="meta_description" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Brief description for search engines (150-160 characters recommended)" 
                              maxlength="160"></textarea>
                </div>

                <!-- Tags -->
                <div class="form-group">
                    <label for="tags">Tags</label>
                    <input type="text" 
                           id="tags" 
                           name="tags" 
                           class="form-control" 
                           placeholder="Enter tags separated by commas (e.g., technology, gaming, PC)">
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label for="status">Publication Status</label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="draft">📝 Draft</option>
                        <option value="published">✅ Published</option>
                        <option value="scheduled">⏰ Scheduled</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        💾 Save Article
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="previewArticle()">
                        👁️ Preview
                    </button>
                    <button type="reset" class="btn" style="background: #6b7280; color: white;">
                        🔄 Reset
                    </button>
                </div>
            </form>

            <!-- Preview Section (Hidden by default) -->
            <div class="preview-section" id="previewSection" style="display: none;">
                <h3>📋 Preview</h3>
                <div id="previewContent"></div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- CKEditor 5 from CDN (Free Open Source Version) -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
    
    <script>
        let editor;
        
        // Initialize CKEditor 5 with comprehensive configuration
        ClassicEditor
            .create(document.querySelector('#editor'), {
                // Image upload configuration
                simpleUpload: {
                    uploadUrl: 'upload_image.php',
                    withCredentials: true,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                },
                
                // Toolbar configuration
                toolbar: {
                    items: [
                        'heading',
                        '|',
                        'bold',
                        'italic',
                        'underline',
                        'strikethrough',
                        '|',
                        'link',
                        'bulletedList',
                        'numberedList',
                        '|',
                        'outdent',
                        'indent',
                        '|',
                        'imageUpload',
                        'blockQuote',
                        'insertTable',
                        'mediaEmbed',
                        '|',
                        'undo',
                        'redo',
                        '|',
                        'fontSize',
                        'fontColor',
                        'fontBackgroundColor',
                        '|',
                        'alignment',
                        'horizontalLine',
                        'specialCharacters',
                        'code',
                        'codeBlock',
                        '|',
                        'removeFormat',
                        'sourceEditing'
                    ],
                    shouldNotGroupWhenFull: true
                },
                
                // Heading configuration
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                        { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                        { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
                    ]
                },
                
                // Font size options
                fontSize: {
                    options: [
                        'tiny',
                        'small',
                        'default',
                        'big',
                        'huge'
                    ]
                },
                
                // Image upload configuration
                // Note: You'll need to implement the upload handler on your server
                // See Step 3 below for the PHP upload handler
                
                // Link configuration
                link: {
                    decorators: {
                        openInNewTab: {
                            mode: 'manual',
                            label: 'Open in new tab',
                            attributes: {
                                target: '_blank',
                                rel: 'noopener noreferrer'
                            }
                        }
                    }
                },
                
                // Table configuration
                table: {
                    contentToolbar: [
                        'tableColumn',
                        'tableRow',
                        'mergeTableCells',
                        'tableProperties',
                        'tableCellProperties'
                    ]
                },
                
                // Code block languages
                codeBlock: {
                    languages: [
                        { language: 'plaintext', label: 'Plain text' },
                        { language: 'php', label: 'PHP' },
                        { language: 'javascript', label: 'JavaScript' },
                        { language: 'python', label: 'Python' },
                        { language: 'css', label: 'CSS' },
                        { language: 'html', label: 'HTML' },
                        { language: 'sql', label: 'SQL' }
                    ]
                },
                
                // Media embed configuration
                mediaEmbed: {
                    previewsInData: true
                }
            })
            .then(newEditor => {
                editor = newEditor;
                console.log('✅ CKEditor 5 initialized successfully');
                
                // Update character counter
                editor.model.document.on('change:data', () => {
                    updateCharacterCounter();
                });
                
                // Auto-save every 30 seconds (optional)
                setInterval(() => {
                    autoSave();
                }, 30000);
            })
            .catch(error => {
                console.error('❌ Error initializing CKEditor:', error);
                alert('Failed to initialize editor. Please refresh the page.');
            });

        // Update character counter
        function updateCharacterCounter() {
            if (editor) {
                const data = editor.getData();
                const plainText = data.replace(/<[^>]*>/g, '');
                const charCount = plainText.length;
                document.getElementById('charCounter').textContent = `${charCount} characters`;
            }
        }

        // Auto-save functionality
        function autoSave() {
            if (editor) {
                const data = editor.getData();
                localStorage.setItem('article_draft', data);
                console.log('📝 Auto-saved draft');
            }
        }

        // Load draft on page load
        window.addEventListener('DOMContentLoaded', () => {
            const draft = localStorage.getItem('article_draft');
            if (draft && confirm('Found a saved draft. Load it?')) {
                // Wait for editor to be ready
                const checkEditor = setInterval(() => {
                    if (editor) {
                        editor.setData(draft);
                        clearInterval(checkEditor);
                    }
                }, 100);
            }
        });

        // Preview article
        function previewArticle() {
            if (!editor) return;
            
            const title = document.getElementById('articleTitle').value;
            const subtitle = document.getElementById('articleSubtitle').value;
            const content = editor.getData();
            
            if (!title || !content) {
                alert('Please enter title and content');
                return;
            }
            
            const previewSection = document.getElementById('previewSection');
            const previewContent = document.getElementById('previewContent');
            
            previewContent.innerHTML = `
                <h1>${title}</h1>
                ${subtitle ? `<h2 style="color: #6b7280;">${subtitle}</h2>` : ''}
                <hr style="margin: 20px 0;">
                ${content}
            `;
            
            previewSection.style.display = 'block';
            previewSection.scrollIntoView({ behavior: 'smooth' });
        }

        // Form submission with validation
        document.getElementById('articleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!editor) {
                alert('Editor not initialized');
                return;
            }
            
            const content = editor.getData();
            if (!content.trim()) {
                alert('Please enter article content');
                return;
            }
            
            // Add content to form
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'content';
            hiddenInput.value = content;
            this.appendChild(hiddenInput);
            
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
            
            // Submit form
            this.submit();
            
            // Clear draft after successful submission
            localStorage.removeItem('article_draft');
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (editor && editor.getData().trim()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>