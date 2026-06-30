<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nginx + PHP-FPM Starter</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body>
    <main class="wrapper">
        <section class="card">
            <img class="logo" src="/assets/img/docker-nginx.svg" alt="Nginx and PHP-FPM">

            <h1>Nginx + PHP-FPM Starter</h1>

            <p>
                Nginxコンテナが入口になり、PHP-FPMコンテナがPHPを実行しています。
            </p>

            <ul>
                <li>PHP Version: <span class="code"><?= PHP_VERSION ?></span></li>
                <li>Server Time: <span class="code"><?= date('Y-m-d H:i:s') ?></span></li>
            </ul>

            <p>
                CSSと画像はNginxが静的ファイルとして返し、PHPだけがPHP-FPMへ渡されます。
            </p>
        </section>
    </main>
</body>

</html>