# Next.js Docker Starter

Docker Compose上のNode.jsコンテナで、Next.js開発環境を構築する学習用ディレクトリです。

## 全体像

```text
http://localhost:3000
        │
        ▼
Node.jsコンテナ
        │
        ▼
Next.js開発サーバー :3000
```

最終的には共有ネットワーク`web-gateway`を通して、06のリバースプロキシからも接続します。

```text
http://next.test
        │
        ▼
06 Reverse Proxy
        │
        ▼
next-web:3000
        │
        ▼
07 Next.js
```

## 構成

```text
07-nextjs/
├── README.md
├── docker-compose.yml
└── src/
    └── Next.js本体
```

## Compose設定

`docker-compose.yml`

```yaml
services:
  next:
    # Next.jsを動かすNode.js公式イメージ
    image: node:24-alpine
    container_name: 07-nextjs-app

    # package.jsonがあるNext.jsプロジェクトのルート
    working_dir: /app

    # ホスト側のespoとUID・GIDを合わせる
    user: "1000:1000"

    # ホスト3000番 → Next.js開発サーバー3000番
    ports:
      - "3000:3000"

    # Next.js本体をホスト側から編集する
    volumes:
      - ./src:/app

    # コンテナ外から接続可能な状態で開発サーバーを起動
    command: npm run dev -- --hostname 0.0.0.0

    networks:
      default:

      # 06の共通門番から接続する
      web-gateway:
        aliases:
          - next-web

networks:
  # 04・05・06と共有する既存ネットワーク
  web-gateway:
    external: true
```

設定確認:

```bash
docker compose config
```

`config`はYAMLとCompose設定を検査します。実際のイメージ、ポート、ネットワーク、アプリ起動までは`up`で確認します。

## Next.js初回生成

`src/`が空の状態では`package.json`が存在しないため、常駐コンテナの`npm run dev`は失敗します。

```text
コンテナ起動
↓
package.jsonがない
↓
npm run devが失敗
↓
主プロセス終了
↓
コンテナ停止
```

また、通常のbind mount先である`/app`へ直接`create-next-app`を実行すると、書き込み権限の検査で失敗しました。

```text
作成先：/app
親：    /
```

コンテナは次の設定により、rootではなくUID・GIDが1000の一般ユーザーで動いています。

```yaml
user: "1000:1000"
```

`/app`はホスト側の`src/`とbind mountされているため、UID 1000で書き込めます。

一方、コンテナのルートディレクトリ`/`はroot所有であり、一般ユーザーからは書き込めません。

```text
/app
→ bind mount先
→ UID 1000で書き込み可能

/
→ コンテナのルートディレクトリ
→ root所有
→ UID 1000では書き込み不可
```

`create-next-app`は作成先だけでなく、親ディレクトリの書き込み権限も確認します。

そのため、`/app`自体にはファイルを作成できても、親である`/`へ書き込めないと判定され、Next.jsの生成が中止されました。

そこで、書き込み可能なホスト側の`07-nextjs/`全体を、一時コンテナの`/workspace`へbind mountします。

```text
ホスト側
07-nextjs/
├── docker-compose.yml
├── README.md
└── src/

        ↕ bind mount

一時コンテナ
/workspace/
├── docker-compose.yml
├── README.md
└── src/
```

この構成では、作成先と親ディレクトリが次のようになります。

```text
作成先：/workspace/src
親：    /workspace
```

`/workspace`はホスト側の`07-nextjs/`と接続され、UID 1000で書き込めるため、Next.jsを正常に生成できます。

初回生成には、常駐用の`next`サービスを土台にした使い捨てコンテナを使用します。

```bash
docker compose run --rm \
  --workdir /workspace \
  --volume "$PWD:/workspace" \
  next \
  npx --yes create-next-app@latest src \
  --ts \
  --tailwind \
  --eslint \
  --app \
  --use-npm \
  --disable-git \
  --yes
```

この一時コンテナは、Next.js本体の生成だけを行い、処理終了後に削除されます。

```text
常駐コンテナ
→ npm run devでNext.jsを動かし続ける

使い捨てコンテナ
→ create-next-appなどの単発処理を実行して消える
```

今回の本質は、一般ユーザーで安全にコンテナを動かす場合、書き込み先だけでなく、そのツールが親ディレクトリまで検査する可能性も考えることです。

### このコマンドの役割

```text
docker compose run
→ nextサービスの設定を利用して一時コンテナを作る

--rm
→ コマンド終了後に一時コンテナを削除する

--workdir /workspace
→ 一時コンテナ内の作業場所を指定する

--volume "$PWD:/workspace"
→ 07-nextjs全体を一時コンテナへ渡す

next
→ Composeのnextサービスを土台として使う

npx create-next-app
→ src/へNext.js本体を生成する
```

この一時コンテナは、初期生成だけを行う**突撃兵**です。

```text
常駐コンテナ
→ Next.js開発サーバーを動かし続ける

使い捨てコンテナ
→ create-next-appや単発npm処理だけ実行して消える
```

### `/workspace/src`へ生成した理由

通常のサービスでは、ホストの`src/`をコンテナの`/app`へbind mountしています。

しかし`create-next-app`は作成先だけでなく、親ディレクトリの書き込み権限も確認します。

```text
/appへ直接生成
→ 親ディレクトリが/
→ 一般ユーザーは/へ書き込めない
→ 書き込み不可と判定
```

そこで、書き込み可能な`07-nextjs/`全体を`/workspace`へ渡しました。

```text
ホスト
07-nextjs/
└── src/

        ↕ bind mount

一時コンテナ
/workspace/
└── src/
```

これにより、作成先`/workspace/src`と親`/workspace`の両方へUID 1000で書き込めます。

## 生成結果の確認

構成確認:

```bash
tree -L 2 -a
```

主な生成物:

```text
src/
├── app/
├── public/
├── package.json
├── package-lock.json
├── node_modules/
├── next.config.ts
├── tsconfig.json
└── .gitignore
```

所有者確認:

```bash
ls -ld src
find src ! -user espo -ls | head
```

`find`で何も表示されなければ、root所有のファイルは混ざっていません。

Git除外確認:

```bash
grep -E 'node_modules|\.next' src/.gitignore
```

通常、次はGit管理しません。

```text
node_modules/
.next/
```

## 起動

```bash
docker compose up -d
```

状態確認:

```bash
docker compose ps
docker compose logs -f next
```

理想状態:

```text
07-nextjs-app
Up
0.0.0.0:3000->3000/tcp
```

ブラウザ:

```text
http://localhost:3000
```

HTTP確認:

```bash
curl -I http://localhost:3000
```

## 停止

```bash
docker compose stop
```

コンテナを削除する場合:

```bash
docker compose down
```

Next.js本体はホスト側の`src/`に保存されているため、コンテナを削除しても残ります。

## よく使う単発npmコマンド

使い捨てコンテナで実行します。

```bash
docker compose run --rm next npm install
docker compose run --rm next npm run build
docker compose run --rm next npm run lint
```

開発サーバーは常駐コンテナへ任せます。

```bash
docker compose up -d next
```

## この構成で学ぶこと

- Node.js公式イメージをNext.js実行環境として使う
- 常駐コンテナと使い捨てコンテナの役割分離
- bind mountとUID・GIDによる所有権管理
- `package.json`とコンテナの主プロセスの関係
- Next.jsがNode.js自身でHTTPサーバーを動かす仕組み
- 06のリバースプロキシへ接続する共有ネットワーク
