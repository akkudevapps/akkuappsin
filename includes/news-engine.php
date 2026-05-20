<?php

if (!defined('AKKUAPPS_LOADED')) {
    exit('Direct access not allowed');
}

if (!function_exists('akkuTableColumns')) {
    function akkuTableColumns(PDO $pdo, string $table): array
    {
        static $cache = [];

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $cache[$table] = array_map(static function ($row) {
                return $row['Field'];
            }, $stmt->fetchAll());
        } catch (Exception $e) {
            $cache[$table] = [];
        }

        return $cache[$table];
    }

    function akkuHasColumn(array $columns, string $name): bool
    {
        return in_array($name, $columns, true);
    }

    function akkuFirstColumn(array $columns, array $candidates, string $default = null): ?string
    {
        foreach ($candidates as $candidate) {
            if (akkuHasColumn($columns, $candidate)) {
                return $candidate;
            }
        }

        return $default;
    }

    function akkuNewsColumns(PDO $pdo): array
    {
        return akkuTableColumns($pdo, 'news_blogs');
    }

    function akkuUsersIdColumn(PDO $pdo): string
    {
        $columns = akkuTableColumns($pdo, 'users');
        return akkuFirstColumn($columns, ['user_id', 'id'], 'user_id');
    }

    function akkuNewsAuthorJoin(PDO $pdo, string $newsAlias = 'b', string $userAlias = 'u'): string
    {
        $userIdColumn = akkuUsersIdColumn($pdo);
        return "BINARY {$newsAlias}.author_id = BINARY {$userAlias}.{$userIdColumn}";
    }

    function akkuNewsIdColumn(PDO $pdo): string
    {
        return akkuFirstColumn(akkuNewsColumns($pdo), ['blog_id', 'id', 'news_id', 'article_id'], null);
    }

    function akkuNewsLookupColumn(PDO $pdo): ?string
    {
        $columns = akkuNewsColumns($pdo);
        return akkuFirstColumn($columns, ['blog_id', 'id', 'news_id', 'article_id', 'slug', 'title'], null);
    }

    function akkuNewsDateColumn(PDO $pdo): ?string
    {
        return akkuFirstColumn(akkuNewsColumns($pdo), ['published_at', 'created_at', 'updated_at', 'date']);
    }

    function akkuNewsTypeColumn(PDO $pdo): ?string
    {
        return akkuFirstColumn(akkuNewsColumns($pdo), ['article_type', 'content_type', 'post_type', 'type']);
    }

    function akkuNewsViewCountColumn(PDO $pdo): ?string
    {
        return akkuFirstColumn(akkuNewsColumns($pdo), ['view_count', 'views_count', 'views']);
    }

    function akkuNewsFolderColumn(PDO $pdo): ?string
    {
        return akkuFirstColumn(akkuNewsColumns($pdo), ['upload_folder', 'article_folder', 'asset_folder']);
    }

    function akkuNewsOrderBy(PDO $pdo, string $alias = 'b'): string
    {
        $columns = akkuNewsColumns($pdo);
        $parts = [];

        if (akkuHasColumn($columns, 'is_featured')) {
            $parts[] = "{$alias}.is_featured DESC";
        }

        $dateColumn = akkuNewsDateColumn($pdo);
        if ($dateColumn) {
            $parts[] = "{$alias}.{$dateColumn} DESC";
        }

        $idColumn = akkuNewsIdColumn($pdo);
        if ($idColumn) {
            $parts[] = "{$alias}.{$idColumn} DESC";
        } elseif (akkuHasColumn($columns, 'slug')) {
            $parts[] = "{$alias}.slug DESC";
        } elseif (akkuHasColumn($columns, 'title')) {
            $parts[] = "{$alias}.title ASC";
        }

        return implode(', ', $parts);
    }

    function akkuNewsDateSelect(PDO $pdo, string $alias = 'b'): string
    {
        $dateColumn = akkuNewsDateColumn($pdo);
        return $dateColumn ? "{$alias}.{$dateColumn} AS article_date" : "NULL AS article_date";
    }

    function akkuNewsSlugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-');
    }

    function akkuNewsStorageBasePath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'newsroom';
    }

    function akkuNewsEnsureStoragePath(string $slug, string $existingFolder = ''): string
    {
        $base = akkuNewsStorageBasePath();
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }

        $slug = akkuNewsSlugify($slug);
        if ($slug === '') {
            $slug = 'article-' . date('Ymd-His');
        }

        if ($existingFolder !== '') {
            $path = $base . DIRECTORY_SEPARATOR . $existingFolder;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            return $existingFolder;
        }

        $folder = $slug;
        $counter = 1;
        while (is_dir($base . DIRECTORY_SEPARATOR . $folder)) {
            $folder = $slug . '-' . $counter;
            $counter++;
        }

        @mkdir($base . DIRECTORY_SEPARATOR . $folder, 0755, true);
        return $folder;
    }

    function akkuNewsMetaFilePath(string $folder): string
    {
        return akkuNewsStorageBasePath() . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'article.json';
    }

    function akkuNewsPersistMeta(string $folder, array $payload): void
    {
        $path = akkuNewsMetaFilePath($folder);
        @file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    function akkuNewsArticleDate(array $article): ?string
    {
        foreach (['article_date', 'published_at', 'created_at', 'updated_at', 'date'] as $field) {
            if (!empty($article[$field])) {
                return $article[$field];
            }
        }

        return null;
    }

    function akkuNewsArticleType(array $article): string
    {
        foreach (['article_type', 'content_type', 'post_type', 'type'] as $field) {
            if (!empty($article[$field])) {
                return strtolower((string) $article[$field]);
            }
        }

        $category = strtolower((string) ($article['category'] ?? ''));
        if (in_array($category, ['guide', 'guides', 'opinion', 'opinions', 'how-to', 'review'], true)) {
            return 'blog';
        }

        return 'news';
    }

    function akkuNewsViewCount(array $article): int
    {
        foreach (['view_count', 'views_count', 'views'] as $field) {
            if (isset($article[$field])) {
                return (int) $article[$field];
            }
        }

        return 0;
    }

    function akkuNewsReadingMinutes(?string $content): int
    {
        $words = str_word_count(trim(strip_tags((string) $content)));
        return max(1, (int) ceil($words / 220));
    }

    function akkuNewsExcerpt(array $article, int $length = 140): string
    {
        $source = trim((string) ($article['excerpt'] ?? ''));
        if ($source === '') {
            $source = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($article['content'] ?? ''))));
        }

        if (function_exists('mb_strlen') && mb_strlen($source) > $length) {
            return mb_substr($source, 0, $length - 1) . '...';
        }

        if (strlen($source) > $length) {
            return substr($source, 0, $length - 1) . '...';
        }

        return $source;
    }

    function akkuNewsIncrementViewCount(PDO $pdo, array $article): void
    {
        $viewColumn = akkuNewsViewCountColumn($pdo);
        if (!$viewColumn) {
            return;
        }

        $lookupColumn = akkuNewsLookupColumn($pdo);
        if (!$lookupColumn || !isset($article[$lookupColumn])) {
            return;
        }

        try {
            $stmt = $pdo->prepare("UPDATE news_blogs SET {$viewColumn} = COALESCE({$viewColumn}, 0) + 1 WHERE {$lookupColumn} = ?");
            $stmt->execute([(string) $article[$lookupColumn]]);
        } catch (Exception $e) {
            error_log('News view count update failed: ' . $e->getMessage());
        }
    }

    function akkuNewsPromoBlocks(): array
    {
        return [
            [
                'eyebrow' => 'Sponsored Slot',
                'title' => 'Promote your service or product on AkkuApps',
                'copy' => 'Use this space for partner promos, featured tools, computer deals, or sponsored community updates.',
                'cta_label' => 'Advertise With Us',
                'cta_href' => '/auth/register.php',
                'tone' => 'sponsor',
            ],
            [
                'eyebrow' => 'Community',
                'title' => 'Join AkkuApps and publish your own content',
                'copy' => 'Follow creators, earn coins, and stay close to every guide, deal alert, and tech update.',
                'cta_label' => 'Join Now',
                'cta_href' => '/auth/register.php',
                'tone' => 'community',
            ],
            [
                'eyebrow' => 'Coming Soon',
                'title' => 'LEO Infotech marketplace and service booking',
                'copy' => 'Used desktops, laptops, spare parts, repairs, upgrades, and booking support are being prepared now.',
                'cta_label' => 'Explore News',
                'cta_href' => '/news/',
                'tone' => 'market',
            ],
        ];
    }

    function akkuNewsPublicUrl(array $article): string
    {
        if (!empty($article['slug'])) {
            return '/news/article.php?slug=' . urlencode($article['slug']);
        }

        $id = $article['blog_id'] ?? $article['id'] ?? $article['news_id'] ?? $article['article_id'] ?? '';
        return '/news/article.php?id=' . urlencode((string) $id);
    }

    function akkuNewsGetFileSubfolder(string $extension): string
    {
        $mapping = [
            'jpg' => 'images', 'jpeg' => 'images', 'png' => 'images',
            'gif' => 'images', 'webp' => 'images', 'svg' => 'images', 'avif' => 'images',
            'pdf' => 'documents', 'doc' => 'documents', 'docx' => 'documents',
            'txt' => 'documents', 'xls' => 'documents', 'xlsx' => 'documents',
            'ppt' => 'documents', 'pptx' => 'documents', 'odt' => 'documents',
            'mp3' => 'audio', 'wav' => 'audio', 'ogg' => 'audio', 'm4a' => 'audio', 'flac' => 'audio',
            'mp4' => 'videos', 'webm' => 'videos', 'avi' => 'videos', 'mov' => 'videos', 'mkv' => 'videos',
            'zip' => 'files', 'rar' => 'files', '7z' => 'files', 'tar' => 'files', 'gz' => 'files',
        ];
        return $mapping[strtolower($extension)] ?? 'files';
    }

    function akkuNewsUploadFile(array $file, string $articleFolder, string $baseDir): array
    {
        $maxSize = 10 * 1024 * 1024;
        $allowedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif',
            'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'odt',
            'mp3', 'wav', 'ogg', 'm4a', 'flac',
            'mp4', 'webm', 'avi', 'mov', 'mkv',
            'zip', 'rar', '7z', 'tar', 'gz',
        ];

        if ($file['size'] > $maxSize) {
            return ['error' => 'File size exceeds 10MB limit.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            return ['error' => 'File type not allowed.'];
        }

        $subfolder = akkuNewsGetFileSubfolder($ext);
        $uploadDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $articleFolder . DIRECTORY_SEPARATOR . $subfolder;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }

        $filename = uniqid('file_', true) . '.' . $ext;
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['error' => 'Failed to upload file.'];
        }

        $publicUrl = '/uploads/newsroom/' . $articleFolder . '/' . $subfolder . '/' . $filename;

        return [
            'url' => $publicUrl,
            'filename' => $file['name'],
            'type' => $subfolder,
            'size' => $file['size'],
            'extension' => $ext,
        ];
    }

    function akkuNewsSanitizeHTML(string $html): string
    {
        $allowed = '<p><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><br><hr><ul><ol><li><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><div><span><details><summary><abbr><sub><sup><ins><del><mark>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/', '', $html);
        $html = preg_replace('/\s+on\w+\s*=\s*\S+/', '', $html);
        $html = preg_replace('/href\s*=\s*["\']\s*(javascript|data)\s*:[^"\']*["\']/i', 'href="#"', $html);
        $html = preg_replace('/src\s*=\s*["\']\s*(javascript|data)\s*:[^"\']*["\']/i', 'src=""', $html);
        return $html;
    }

    function akkuNewsHighlightCode(string $code, string $lang): string
    {
        $lang = strtolower(trim($lang));
        $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        $tokens = [];
        $idx = 0;
        $wrap = function (string $type, string $match) use (&$tokens, &$idx): string {
            $key = "\x00T$idx\x00";
            $tokens[$key] = '<span class="token-' . $type . '">' . $match . '</span>';
            $idx++;
            return $key;
        };

        $keywords = [
            'python' => '\b(def|class|if|elif|else|for|while|return|import|from|as|try|except|finally|with|yield|lambda|True|False|None|self|raise|pass|break|continue|and|or|not|in|is|global|nonlocal|assert|del)\b',
            'csharp' => '\b(using|namespace|class|public|private|protected|internal|static|void|int|string|bool|var|if|else|for|foreach|while|do|switch|case|break|continue|return|new|this|base|override|virtual|abstract|sealed|async|await|try|catch|finally|throw|true|false|null|const|readonly|enum|struct|interface|partial|where|select|from|in|let|join|on|equals|into|group|by|orderby|descending|ascending|yield|operator|implicit|explicit|params|ref|out|is|as|typeof|nameof|sizeof)\b',
            'css' => '\b(@media|@keyframes|@import|@font-face|@charset|@supports|@page|!important|inherit|initial|unset|none|auto|block|inline|flex|grid|absolute|relative|fixed|sticky|solid|dashed|dotted|hidden|visible|scroll|center|left|right|top|bottom|both|ease|linear|ease-in|ease-out|ease-in-out|forwards|backwards|alternate|running|paused|normal|reverse|italic|bold|bolder|lighter|small-caps|capitalize|uppercase|lowercase|nowrap|pre|pre-wrap|pre-line|break-word|border-box|content-box|cover|contain|repeat|no-repeat|space|round)\b',
            'php' => '\b(function|class|public|private|protected|static|abstract|final|interface|trait|namespace|use|if|elseif|else|for|foreach|while|do|switch|case|break|continue|return|echo|print|require|require_once|include|include_once|new|this|self|parent|extends|implements|instanceof|try|catch|finally|throw|true|false|null|var|const|global|isset|unset|empty|die|exit|yield|from|as|and|or|xor|clone|declare|fn|match|readonly|enum)\b',
            'xaml' => '\b(x:Type|x:Static|x:Null|x:True|x:False|Binding|StaticResource|DynamicResource|TemplateBinding|RelativeSource|MultiBinding|PriorityBinding|DataTrigger|MultiDataTrigger|EventTrigger|StyleSetter|ControlTemplate|DataTemplate|HierarchicalDataTemplate|ItemsPanelTemplate)\b',
            'cshtml' => '\b(model|if|else|foreach|for|while|switch|case|break|continue|return|try|catch|finally|throw|using|var|new|true|false|null|this|base|lock|typeof|nameof|async|await|yield|dynamic|is|as|in|from|where|select|orderby|group|join|let|into|on|equals|by|ascending|descending)\b',
            'js' => '\b(const|let|var|function|class|if|else|for|while|do|switch|case|break|continue|return|new|this|super|extends|implements|interface|abstract|enum|typeof|instanceof|in|of|import|export|from|default|async|await|yield|try|catch|finally|throw|true|false|null|undefined|NaN|Infinity|void|delete|static|get|set|constructor|extends|super|symbol)\b',
            'c' => '\b(int|void|char|float|double|long|short|unsigned|signed|const|volatile|static|extern|auto|register|struct|union|enum|typedef|sizeof|if|else|for|while|do|switch|case|break|continue|return|goto|default|NULL|true|false|define|include|ifdef|ifndef|endif|elif|undef|pragma|error|line)\b',
        ];

        if (isset($keywords[$lang])) {
            $code = preg_replace_callback('/(' . $keywords[$lang] . ')/', function ($m) use ($wrap) {
                return $wrap('keyword', $m[0]);
            }, $code);
        }

        $stringPatterns = [
            'python' => ['/(f?"[^"]*")/', "/(f?'[^']*')/", '/("""[\s\S]*?""")/', "/('''[\s\S]*?''')/"],
            'csharp' => ['/(@"[^"]*")/', '/("[^"]*")/', "/('[^']*')/"],
            'css' => ['/(#[0-9a-fA-F]{3,8})\b/', '/("[^"]*")/', "/('[^']*')/"],
            'php' => ['/(b?"[^"]*")/', "/(b?'[^']*')/"],
            'xaml' => ['/(#[0-9a-fA-F]{3,8})\b/', '/("[^"]*")/'],
            'cshtml' => ['/(#[0-9a-fA-F]{3,8})\b/', '/("[^"]*")/', "/('[^']*')/"],
            'js' => ['/(`[\s\S]*?`)/', '/("[^"]*")/', "/('[^']*')/"],
            'c' => ['/(#[^\n]*)/', '/("[^"]*")/', "/('[^']*')/"],
        ];

        if (isset($stringPatterns[$lang])) {
            foreach ($stringPatterns[$lang] as $p) {
                $code = preg_replace_callback($p, function ($m) use ($wrap) {
                    return $wrap('string', $m[0]);
                }, $code);
            }
        }

        $numberPatterns = [
            'python' => '/\b(\d+\.?\d*(?:e[+-]?\d+)?)\b/i',
            'csharp' => '/\b(\d+\.?\d*(?:f|d|m|l|ul)?)\b/i',
            'css' => '/\b(\d+\.?\d*)(px|em|rem|%|vh|vw|deg|rad|turn|s|ms|fr)?\b/i',
            'php' => '/\b(\d+\.?\d*(?:e[+-]?\d+)?)\b/i',
            'xaml' => '/\b(\d+\.?\d*)\b/',
            'cshtml' => '/\b(\d+\.?\d*(?:e[+-]?\d+)?)\b/i',
            'js' => '/\b(\d+\.?\d*(?:e[+-]?\d+)?)\b/i',
            'c' => '/\b(\d+\.?\d*(?:f|l|ul|ll)?)\b/i',
        ];

        if (isset($numberPatterns[$lang])) {
            $code = preg_replace_callback($numberPatterns[$lang], function ($m) use ($wrap) {
                return $wrap('number', $m[0]);
            }, $code);
        }

        $code = preg_replace_callback('/\b([a-zA-Z_]\w*)\s*(?=\()/i', function ($m) use ($wrap) {
            return $wrap('function', $m[0]);
        }, $code);

        if (in_array($lang, ['xaml', 'cshtml'], true)) {
            $code = preg_replace_callback('/(<\/?)([\w:.]+)/', function ($m) use ($wrap) {
                return $m[1] . $wrap('tag', $m[2]);
            }, $code);
            $code = preg_replace_callback('/\s([\w:.]+)(?==)/', function ($m) use ($wrap) {
                return ' ' . $wrap('attribute', $m[1]);
            }, $code);
        }

        if ($lang === 'php') {
            $code = preg_replace_callback('/(\$[\w]+)/', function ($m) use ($wrap) {
                return $wrap('variable', $m[0]);
            }, $code);
        }

        $code = preg_replace_callback('/(=>|===|!==|==|!=|<=|>=|&&|\|\||\?\?|\?\.\.\.|\.\.\.|\+\+|--|[+\-*/%=<>!&|^~])/', function ($m) use ($wrap) {
            return $wrap('operator', $m[0]);
        }, $code);

        $commentPatterns = [
            'python' => '/(#.*$)/m',
            'csharp' => '/(\/\/.*$)/m',
            'css' => '/(\/\*[\s\S]*?\*\/)/',
            'php' => '/(\/\/.*$|\/\*[\s\S]*?\*\/|#.*$)/m',
            'xaml' => '/(<!--[\s\S]*?-->)/',
            'cshtml' => '/(\/\/.*$|\/\*[\s\S]*?\*\/|<!--[\s\S]*?-->)/m',
            'js' => '/(\/\/.*$|\/\*[\s\S]*?\*\/)/m',
            'c' => '/(\/\/.*$|\/\*[\s\S]*?\*\/)/m',
        ];

        if (isset($commentPatterns[$lang])) {
            $code = preg_replace_callback($commentPatterns[$lang], function ($m) use ($wrap) {
                return $wrap('comment', $m[0]);
            }, $code);
        }

        foreach ($tokens as $k => $v) {
            $code = str_replace($k, $v, $code);
        }

        return $code;
    }
}
