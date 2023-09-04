# PukiWiki用プラグイン<br>ブログカード表示 blogcard.inc.php

ブログカードを表示する[PukiWiki](https://pukiwiki.osdn.jp/)用プラグイン。  

|対象PukiWikiバージョン|対象PHPバージョン|
|:---:|:---:|
|PukiWiki 1.5.3 ~ 1.5.4 (UTF-8)|PHP 7.4 ~ 8.2|

## インストール

下記GitHubページからダウンロードした blogcard.inc.php を PukiWiki の plugin ディレクトリに配置してください。

[https://github.com/ikamonster/pukiwiki-blogcard](https://github.com/ikamonster/pukiwiki-blogcard)

## 使い方

```
#blogcard(url)
```

url … リンク先ページのURL

## 使用例

```
#blogcard(https://example.com/)
```

## ご注意

- サムネイル画像を生成するため、PHPにGDモジュールが必要です。なくても動作しますが、重くなるため推奨しません。
- リンク先ページの情報はデフォルトで1週間キャッシュされます。キャッシュを消去するには次のコマンドを実行してください。  
```（ウィキのURL）?plugin=blogcard&query=clear```

## 設定

ソース内の下記の定数で動作を制御することができます。

|定数名|値|既定値|意味|
|:---|:---:|:---|:---|
|PLUGIN_BLOGCARD_NEWTAB|0 or 1|1|1ならURLを新規タブで開く|
|PLUGIN_BLOGCARD_THEME|0 ~ 2|0|カラーテーマ（0:ライト, 1:ダーク, 2:OS設定に自動適応）|
|PLUGIN_BLOGCARD_WIDTH|数値|50em|最大表示幅（単位付き）|
|PLUGIN_BLOGCARD_CACHE_AGE|数値|604800|情報キャッシュの有効期限（秒）|
|PLUGIN_BLOGCARD_USERAGENT|文字列|'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.0 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.0'|リンク先アクセス時の UserAgent|

