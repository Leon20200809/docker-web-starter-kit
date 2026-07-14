# Reverse Proxy Gateway

複数のDocker Composeプロジェクトを、1つのNginxリバースプロキシからドメイン名で振り分ける学習環境です。

```text
http://wordpress.test → 04-wordpress
http://laravel.test   → 05-laravel
```

## 全体像

```text
Windowsブラウザ
        │
        ▼
127.0.0.1:80
        │
        ▼
06 Reverse Proxy
        │
        ├── wordpress.test → wordpress-web:80
        └── laravel.test   → laravel-web:80
```

ホスト側の80番を公開するのは06だけです。04と05のNginxは、各コンテナ内部の80番で待ち受けます。

## 構成

```text
06-reverse-proxy/
├── README.md
├── docker-compose.yml
└── nginx/
    └── default.conf
```

## 共有ネットワーク

別々のComposeプロジェクトを接続するため、外部ネットワークを作成します。

```bash
docker network create web-gateway
```

04・05・06のNginxを、この`web-gateway`へ参加させます。

```text
04・05の内部ネットワーク
→ PHP・MySQL・Nodeとの通信

web-gateway
→ 06から04・05のNginxへ転送する共通通路
```

## Compose設定

`docker-compose.yml`

```yaml
services:
  proxy:
    # 複数のWebサービスを振り分ける共通の入口
    image: nginx:stable-alpine
    container_name: 06-reverse-proxy

    # ホストの80番を使うのは門番だけ
    ports:
      - "80:80"

    # 振り分け設定を読み取り専用で渡す
    volumes:
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf:ro

    # 04・05と通信するための共有ネットワーク
    networks:
      - web-gateway

    restart: unless-stopped

networks:
  # 他のComposeプロジェクトとも共有する既存ネットワーク
  web-gateway:
    external: true
```

## Nginx振り分け設定

`nginx/default.conf`

```nginx
# wordpress.test 宛ての通信をWordPress側へ転送
server {
    listen 80;
    server_name wordpress.test;

    location / {
        proxy_pass http://wordpress-web:80;
        proxy_http_version 1.1;

        # 元のアクセス情報を転送先へ引き継ぐ
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}

# laravel.test 宛ての通信をLaravel側へ転送
server {
    listen 80;
    server_name laravel.test;

    location / {
        proxy_pass http://laravel-web:80;
        proxy_http_version 1.1;

        # 元のアクセス情報を転送先へ引き継ぐ
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

`server_name`でアクセス先を判定し、`proxy_pass`で転送先を決めます。

## Windows hosts設定

Windowsのhostsファイルへ追加します。

```text
127.0.0.1 wordpress.test
127.0.0.1 laravel.test
```

## 起動

04・05を起動した後、06を起動します。

```bash
cd ../04-wordpress
docker compose up -d

cd ../05-laravel
docker compose up -d

cd ../06-reverse-proxy
docker compose config
docker compose up -d
```

共有ネットワークの参加状況を確認します。

```bash
docker network inspect web-gateway   --format '{{range .Containers}}{{.Name}}{{"\n"}}{{end}}'
```

## 動作確認

ブラウザ:

```text
http://wordpress.test
http://laravel.test
```

hosts設定前でも、Hostヘッダーを指定して確認できます。

```bash
curl -I -H 'Host: wordpress.test' http://127.0.0.1
curl -I -H 'Host: laravel.test' http://127.0.0.1
```

`200`、`301`、`302`など、転送先からHTTP応答が返れば通信経路は成立しています。

## WordPressの301リダイレクト

初回インストールを`http://localhost:8082`で行った場合、WordPressはそのURLをDBの`home`と`siteurl`へ保存します。

そのため、門番経由でも8082番へ戻される場合があります。

```text
wordpress.test
↓
06 Reverse Proxy
↓
WordPress
↓
301 Moved Permanently
↓
localhost:8082
```

次のヘッダーがあれば、WordPress自身によるリダイレクトです。

```text
X-Redirect-By: WordPress
```

管理画面で次を変更します。

```text
設定
→ 一般
→ WordPress アドレス（URL）
→ サイトアドレス（URL）
```

設定値:

```text
http://wordpress.test
```

## 直通ポートを残す理由

学習中は直通ポートを残しています。

```text
http://localhost:8082 → WordPressへ直通
http://localhost:8083 → Laravelへ直通

http://wordpress.test → 06経由
http://laravel.test   → 06経由
```

これにより障害箇所を切り分けられます。

```text
直通は成功、.testは失敗
→ 06または共有ネットワークの問題

直通も.testも失敗
→ 04・05側の問題
```

完成後は04・05の`ports`を削除し、06だけを外部公開する構成にもできます。

## 06を停止するとどうなるか

```bash
docker compose stop
```

06だけを停止すると`.test`の入口は消えます。

04と05は動作を続けるため、直通ポートを残していれば次は利用できます。

```text
http://localhost:8082
http://localhost:8083
```

## この構成で学ぶこと

- リバースプロキシによるHost名振り分け
- コンテナ内部ポートとホスト公開ポートの違い
- 外部ネットワークによる複数Composeの接続
- Nginxの`server_name`と`proxy_pass`
- HTTPステータスコードを使った障害切り分け
- Laragonなどが裏側で行うVirtual Host相当の仕組み
