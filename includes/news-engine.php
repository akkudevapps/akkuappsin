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
}
