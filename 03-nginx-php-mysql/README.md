# 03-nginx-php-mysql

## 目的

Docker Composeを使って、Nginx / PHP-FPM / MySQL を別コンテナとして起動し、PHPからMySQLへPDO接続してブラウザにDBデータを表示する。

このステップでは、02で作った `Nginx + PHP-FPM` 構成に、MySQLコンテナを追加する。

```text
ブラウザ
↓
Nginxコンテナ
↓
PHPファイルならPHP-FPMコンテナへ渡す
↓
PHP-FPMがPHPを実行
↓
PHPがPDOでMySQLコンテナへ接続
↓
MySQLのデータを取得
↓
Nginx経由でブラウザへ返す
```

---

## 構成

```text
03-nginx-php-mysql/
├─ docker-compose.yml
├─ nginx/
│  └─ default.conf
├─ php/
│  └─ Dockerfile
├─ mysql/
│  └─ init/
│     └─ 01-create-table.sql
├─ src/
│  ├─ index.php
│  └─ assets/
│     ├─ css/
│     │  └─ style.css
│     └─ img/
│        └─ docker-nginx.svg
└─ README.md
```

---

## 起動方法

```bash
cd 03-nginx-php-mysql
docker compose up -d --build
```

ブラウザで確認。

```text
http://localhost:8082
```

---

## 停止方法

```bash
docker compose down
```

DBデータも含めて完全に削除する場合。

```bash
docker compose down -v
```

`-v` を付けると Docker volume も削除される。

今回の構成では、MySQLのデータは `mysql_data` volume に保存されるため、`docker compose down` だけではDBデータは残る。

---

## 状態確認

Compose管理下のコンテナを見る。

```bash
docker compose ps
```

Docker Engine全体で動いているコンテナを見る。

```bash
docker ps
```

今回の構成では、以下の3コンテナが起動する。

```text
nginx
php
mysql
```

---

## MySQL確認方法

MySQLコンテナ内でMySQLクライアントを実行する。

```bash
docker compose exec mysql mysql -u starter_user -p starter_db
```

パスワードは `docker-compose.yml` に設定したものを使う。

ログイン後、初期データを確認する。

```sql
SELECT * FROM messages;
```

---

## ホスト側からMySQLへ接続する

`docker-compose.yml` では、学習・確認用にMySQLポートを公開している。

```yaml
ports:
  - "3307:3306"
```

これは以下の意味。

```text
ホスト側の3307番ポート
↓
MySQLコンテナ内の3306番ポート
```

そのため、WSLやPC側のMySQLクライアントからも接続できる。

```bash
mysql -h 127.0.0.1 -P 3307 -u starter_user -p starter_db
```

ただし、PHPコンテナからMySQLへ接続するだけなら、このポート公開は不要。

Docker Compose内では、PHPからMySQLへはサービス名で接続できる。

```text
host = mysql
port = 3306
```

本番環境では、DBポートを外部公開しないのが基本。

---

## docker-compose.yml の本質

```yaml
services:
  nginx:
    image: nginx:stable-alpine
    ports:
      - "8082:80"
    volumes:
      - ./src:/var/www/html:ro
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - php

  php:
    build:
      context: ./php
    volumes:
      - ./src:/var/www/html:ro
    depends_on:
      - mysql

  mysql:
    image: mysql:8.0
    command:
      - --character-set-server=utf8mb4
      - --collation-server=utf8mb4_unicode_ci
    environment:
      MYSQL_ROOT_PASSWORD: root_pass
      MYSQL_DATABASE: starter_db
      MYSQL_USER: starter_user
      MYSQL_PASSWORD: starter_pass
    volumes:
      - mysql_data:/var/lib/mysql
      - ./mysql/init:/docker-entrypoint-initdb.d:ro
    ports:
      - "3307:3306"

volumes:
  mysql_data:
```

### `nginx`

ブラウザからのHTTPリクエストを受ける入口。

静的ファイルはNginxが返し、PHPファイルへのアクセスはPHP-FPMへ渡す。

### `php`

PHP-FPMでPHPを実行するコンテナ。

Nginxから渡された `.php` ファイルを処理し、PDOでMySQLへ接続する。

### `mysql`

データベース役。

`MYSQL_DATABASE` で初期DBを作成し、`MYSQL_USER` / `MYSQL_PASSWORD` でアプリ用ユーザーを作成する。

---

## php/Dockerfile の本質

```Dockerfile
FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
```

### `pdo_mysql`

PHPからMySQLへPDO接続するために必要。

これがない場合、PDOでMySQL接続しようとすると以下のようなエラーになる。

```text
could not find driver
```

PHP本体だけではMySQLに接続できない。

```text
PHP
↓
PDO
↓
pdo_mysql
↓
MySQL
```

`pdo_mysql` は、PDOからMySQLへ接続するためのドライバ。

---

## mysql/init/01-create-table.sql の本質

MySQL公式イメージでは、初回起動時に以下のフォルダ内のSQLが自動実行される。

```text
/docker-entrypoint-initdb.d
```

このリポジトリでは、ローカルの `mysql/init` フォルダをそこにマウントしている。

```yaml
volumes:
  - ./mysql/init:/docker-entrypoint-initdb.d:ro
```

そのため、以下のSQLが初回起動時に実行される。

```text
mysql/init/01-create-table.sql
```

SQLファイルはファイル名順に実行される。

```text
01-create-table.sql
02-insert-seed-data.sql
03-create-index.sql
```

のように番号を付けることで、実行順を制御できる。

---

## 初期SQL

```sql
USE starter_db;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO messages (id, title, body) VALUES
(1, 'Docker MySQL Connected', 'PHPコンテナからMySQLコンテナへの接続に成功しました。'),
(2, 'Nginx + PHP-FPM + MySQL', 'Webサーバー、PHP実行環境、DBを別コンテナとして分離しています。'),
(3, 'Persistent Volume Ready', 'MySQLのデータはDocker volumeに保存されます。');
```

### `SET NAMES utf8mb4`

MySQL初期化SQLで日本語を扱うために重要。

SQLファイル自体がUTF-8で保存されていても、MySQLへ流し込む接続時の文字コードがずれると、日本語が壊れた状態でDBに保存されることがある。

そのため、初期SQL内で文字コードを明示する。

```sql
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

# 文字化けトラブル記録

## 発生した症状

ブラウザ上で、DBから取得した日本語だけが文字化けした。

```text
PHPã‚³ãƒ³ãƒ†ãƒŠã‹ã‚‰MySQL...
```

一方で、PHPファイルに直接書いた日本語は正常に表示された。

```text
Nginxコンテナが入口になり、PHP-FPMコンテナがPHPを実行し...
```

この時点で、HTML全体の文字コード問題ではなく、DBから取得した文字列だけが怪しいと判断した。

---

## 最初に疑った箇所

### 1. HTMLの文字コード

`index.php` には以下が入っていた。

```html
<meta charset="UTF-8" />
```

PHP直書きの日本語は正常に表示されていたため、HTML側は主犯ではないと判断。

### 2. htmlspecialchars

出力時に以下の関数を使っていた。

```php
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
```

`htmlspecialchars()` は、HTMLとして危険な文字をエスケープする関数。

```text
<  → &lt;
>  → &gt;
"  → &quot;
'  → &#039;
&  → &amp;
```

日本語を別の文字コードへ変換する関数ではないため、主犯ではないと判断。

### 3. fetchAll

DB取得部分は以下。

```php
$messages = $stmt->fetchAll();
```

`fetchAll()` は、取得した結果を配列として受け取るだけ。

文字コードを変換する処理ではないため、主犯ではないと判断。

### 4. PDO接続文字コード

PDOのDSNに `charset=utf8mb4` を付けた。

```php
$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
```

さらに、PDO生成時のdriver optionsで初期コマンドも指定した。

```php
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
]);
```

`PDO::MYSQL_ATTR_INIT_COMMAND` は、`new PDO()` の第4引数に指定する必要がある。

後から `setAttribute()` で指定するものではない。

---

## PDO接続状態の確認

PDO接続後に、MySQLの文字コード関連変数を確認した。

```php
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
```

結果はすべて `utf8mb4` だった。

```text
character_set_client      utf8mb4
character_set_connection  utf8mb4
character_set_results     utf8mb4
character_set_database    utf8mb4
character_set_server      utf8mb4
```

この時点で、PHPからMySQLへ接続する通信路は正常と判断。

---

## DB保存データの確認

MySQL内で、実際に保存されている文字列のバイト列を確認した。

```sql
SELECT id, body, HEX(body) FROM messages;
```

最初は、`HEX(body)` に以下のような値が出ていた。

```text
C3A3...
```

これは、すでに文字化けした文字列がUTF-8として保存されている状態。

つまり、PDOやHTML出力の問題ではなく、DBに入った時点でデータが壊れていた。

---

## SQLファイルの確認

WSL側でSQLファイルを確認した。

```bash
sed -n '1,120p' mysql/init/01-create-table.sql
```

文字化け文字が混入していないか確認した。

```bash
grep -n "ã" mysql/init/01-create-table.sql
```

結果、SQLファイル自体は正常だった。

VS Code上でも `UTF-8` で保存されていた。

---

## 真犯人

SQLファイル自体は正常。

PDO接続も正常。

MySQLの文字コード設定も正常。

それでもDBに壊れた文字が保存されていた。

原因は、MySQL初期化時に `01-create-table.sql` を流し込む瞬間の文字コード指定不足。

つまり、以下の流れで壊れていた。

```text
SQLファイル自体はUTF-8
↓
MySQL初期化時にSQLを流し込む
↓
文字列リテラルの解釈がずれる
↓
壊れた文字列がDBに保存される
↓
PHPは壊れた文字列を正しく取得する
↓
ブラウザに文字化けとして表示される
```

---

## 解決策

`mysql/init/01-create-table.sql` に以下を追加した。

```sql
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
```

配置場所は `USE starter_db;` の直後。

```sql
USE starter_db;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
```

その後、MySQL volumeを削除して初期化し直した。

```bash
docker compose down -v
docker compose up -d --build
```

---

## 修正後の確認

再度、MySQL内で確認した。

```sql
SELECT id, body, HEX(body) FROM messages;
```

修正後は、`HEX(body)` に以下のような `E3` 系のUTF-8バイト列が出た。

```text
E382B3E383B3E38386...
```

これは日本語が正しいUTF-8として保存されている証拠。

ブラウザ上でも、DBから取得した日本語が正常に表示された。

---

## 文字化け調査の結論

今回の文字化けは、HTMLでもPHPでもPDOでもなく、初期SQL投入時の文字コード指定不足が原因だった。

```text
HTMLのmeta charset
→ 正常

PHPファイルの文字コード
→ 正常

htmlspecialchars
→ 正常

PDO接続charset
→ 正常

MySQLサーバー文字コード
→ 正常

init SQL投入時の文字コード
→ 原因
```

学び。

```text
SQLファイルがUTF-8でも、
DB接続がutf8mb4でも、
初期SQL投入時の文字コードがずれると、
壊れた文字列がDBに保存されることがある。
```

---

# Connection refused について

`docker compose down -v` 後の初回起動時に、ブラウザで以下のエラーが出ることがある。

```text
SQLSTATE[HY000] [2002] Connection refused
```

これは、MySQLコンテナは起動していても、MySQLサーバー本体がまだ接続受付可能になっていない場合に起きる。

`depends_on` は、起動順をある程度制御するだけ。

```text
mysqlコンテナを先に起動開始する
↓
phpコンテナを起動する
```

しかし、以下までは保証しない。

```text
MySQLが完全に初期化され、ログイン受付可能になるまで待つ
```

そのため、初回起動直後は少し待ってからブラウザを更新する。

MySQLの準備完了はログで確認できる。

```bash
docker compose logs mysql
```

以下のような表示があれば接続可能。

```text
ready for connections
```

---

# Docker volume と初期SQLの注意点

`mysql/init/*.sql` は、MySQLのデータディレクトリが空の初回起動時だけ実行される。

そのため、SQLファイルを修正しても、既存の `mysql_data` volume が残っている場合は再実行されない。

初期SQLを再実行したい場合は、volumeを削除する。

```bash
docker compose down -v
docker compose up -d --build
```

ただし、`-v` はDBデータを削除する。

本番環境では軽く使ってはいけない。

---

# このステップの本質

02では、NginxとPHP-FPMを分離した。

```text
Nginx
↓
PHP-FPM
```

03では、そこにMySQLを追加した。

```text
Nginx
↓
PHP-FPM
↓
MySQL
```

これにより、WordPressやLaravelのDocker環境に近い構成になる。

```text
Webサーバー
PHP実行環境
データベース
```

この3つを別コンテナとして分離し、Docker Composeでまとめて起動する。

---

# 学んだこと

```text
Nginx
  = HTTPリクエストの入口

PHP-FPM
  = PHP実行専用コンテナ

MySQL
  = データベース専用コンテナ

Docker volume
  = DBデータの保存場所

init SQL
  = 初回起動時にDB初期化を行う仕組み

pdo_mysql
  = PHPからMySQLへ接続するためのドライバ
```

第三形態の核心。

```text
アプリ本体とDBを別コンテナに分け、
サービス名で通信する。
```

PHPからMySQLへ接続するときは、`localhost` ではなくComposeのサービス名を使う。

```text
host = mysql
```

`localhost` と書くと、PHPコンテナ自身を指してしまう。

---

# 次のステップ

次は、WordPress用のDocker環境へ進む。

```text
04-wordpress
```

今回の構成で学んだ以下の要素が、そのままWordPressに繋がる。

```text
Nginx / Apache
PHP
MySQL
volume
環境変数
初期化
文字コード
```
