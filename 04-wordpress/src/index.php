<?php
$dbHost = 'mysql';
$dbName = 'starter_db';
$dbUser = 'starter_user';
$dbPass = 'starter_pass';

$messages = [];
$errorMessage = null;

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

    // PDO接続時の挙動設定
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ];

    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);

    $charsetRows = $pdo->query("
        SHOW VARIABLES
        WHERE Variable_name IN (
            'character_set_client',
            'character_set_connection',
            'character_set_results',
            'character_set_database',
            'character_set_server'
        )
    ")->fetchAll();

    echo '<pre>';
    var_dump($charsetRows);
    echo '</pre>';

    $stmt = $pdo->query('SELECT id, title, body, created_at FROM messages ORDER BY id ASC');
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nginx + PHP-FPM + MySQL Starter</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <main class="wrapper">
        <section class="card">
            <img class="logo" src="/assets/img/docker-nginx.svg" alt="Nginx and PHP-FPM">

            <h1>Nginx + PHP-FPM + MySQL Starter</h1>

            <p>
                Nginxコンテナが入口になり、PHP-FPMコンテナがPHPを実行し、PDOでMySQLコンテナへ接続しています。
            </p>

            <ul>
                <li>PHP Version: <span class="code"><?= h(PHP_VERSION) ?></span></li>
                <li>Server Time: <span class="code"><?= h(date('Y-m-d H:i:s')) ?></span></li>
                <li>DB Host: <span class="code"><?= h($dbHost) ?></span></li>
                <li>DB Name: <span class="code"><?= h($dbName) ?></span></li>
            </ul>

            <?php if ($errorMessage !== null): ?>
                <section>
                    <h2>Database Connection Error</h2>
                    <p class="code"><?= h($errorMessage) ?></p>
                </section>
            <?php else: ?>
                <section>
                    <h2>Messages from MySQL</h2>

                    <?php if (count($messages) === 0): ?>
                        <p>messages テーブルにデータがありません。</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($messages as $message): ?>
                                <li>
                                    <strong><?= h($message['title']) ?></strong><br>
                                    <?= h($message['body']) ?><br>
                                    <span class="code"><?= h($message['created_at']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <p>
                このページが表示され、MySQLのデータが出ていれば第三形態は成功です。
            </p>
        </section>
    </main>
</body>

</html>