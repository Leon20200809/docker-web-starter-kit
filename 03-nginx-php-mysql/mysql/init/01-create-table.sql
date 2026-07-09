-- 初期化対象のDBを明示する
-- docker-compose.yml の MYSQL_DATABASE=starter_db と合わせる
USE starter_db;

-- SQLファイル内の文字列リテラルを utf8mb4 として扱う
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- PHPからMySQL接続を確認するためのサンプルテーブル
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 初期データ
-- idを固定しておくことで、手動で再実行しても重複しにくくする
INSERT IGNORE INTO messages (id, title, body) VALUES
(1, 'Docker MySQL Connected', 'PHPコンテナからMySQLコンテナへの接続に成功しました。'),
(2, 'Nginx + PHP-FPM + MySQL', 'Webサーバー、PHP実行環境、DBを別コンテナとして分離しています。'),
(3, 'Persistent Volume Ready', 'MySQLのデータはDocker volumeに保存されます。');