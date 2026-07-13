# PHP-FPM公式イメージ
# Alpine Linuxベースの軽量版
FROM php:8.3-fpm-alpine

# PDO MySQL拡張をインストール
# PHPからMySQLへ接続するために必要
RUN docker-php-ext-install pdo_mysql

# composer実行ファイルだけコピー
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# コンテナ内の作業ディレクトリ
# docker-compose.yml で ./src をここに見せる
WORKDIR /var/www/html