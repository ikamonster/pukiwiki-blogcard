<?php
/*
PukiWiki - Yet another WikiWikiWeb clone.
blogcard.inc.php, v1.0.3 2020 M.Taniguchi
License: GPL v3 or (at your option) any later version

ブログカードを表示するプラグイン。

サムネイル画像を生成するため、PHPにGDモジュールが必要です。なくても一応動作しますが、重くなるため推奨しません。

【使い方】
#blogcard(url)

url … リンク先ページのURL

【使用例】
#blogcard(https://example.com/)

【キャッシュについて】
リンク先ページの情報はデフォルトで1週間キャッシュされます。
キャッシュを消去するには次のコマンドを実行してください。

（ウィキのURL）?plugin=blogcard&query=clear
*/

/////////////////////////////////////////////////
// ブログカードプラグイン（blogcard.inc.php）
if (!defined('PLUGIN_BLOGCARD_NEWTAB'))    define('PLUGIN_BLOGCARD_NEWTAB',     1);      // 1ならURLを新規タブで開く（0 or 1）
if (!defined('PLUGIN_BLOGCARD_THEME'))     define('PLUGIN_BLOGCARD_THEME',      0);      // カラーテーマ（0:ライト, 1:ダーク, 2:OS設定に自動適応）
if (!defined('PLUGIN_BLOGCARD_WIDTH'))     define('PLUGIN_BLOGCARD_WIDTH',      800);    // 最大表示幅（px）
if (!defined('PLUGIN_BLOGCARD_CACHE_AGE')) define('PLUGIN_BLOGCARD_CACHE_AGE',  604800); // 情報キャッシュの有効期限（秒）
if (!defined('PLUGIN_BLOGCARD_USERAGENT')) define('PLUGIN_BLOGCARD_USERAGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.0 (KHTML, like Gecko) Chrome/97.0.0.0 Safari/537.0'); // 対象サイトアクセス時のUserAgent


// ブロック呼び出し：ブログカード出力
// サイト情報取得処理は重いため、ここでは枠組み（iframe）だけ出力し、中身はコマンド呼び出しに委ねる
function plugin_blogcard_convert() {
	list($url) = func_get_args();

	if ($url) {
		$url = urlencode($url);
		$widgetTag = '<div class="_p_blogcard" ><iframe src="./?plugin=blogcard&refer=' . $url . '" width="' . PLUGIN_BLOGCARD_WIDTH . '" height="90px" loading="lazy"></iframe></div>';
	}

	static	$included = false;
	if (!$included) {
		$width = PLUGIN_BLOGCARD_WIDTH;
		$widgetTag .= <<<EOT
<style>
._p_blogcard,._p_blogcard>iframe{position:relative;max-width:{$width}px;max-height:90px;width:100%;height:100%;min-width:0;min-height:0;padding:0;overflow:hidden;box-sizing:border-box}
._p_blogcard>iframe{border:none}
</style>
EOT;
		$included = true;
	}

	return ($widgetTag)? $widgetTag : '#blogcard(url)';
}


// コマンド呼び出し：サイト情報取得＆ブログカード描画、またはキャッシュファイル消去要求受付
function plugin_blogcard_action() {
	ini_set('user_agent', PLUGIN_BLOGCARD_USERAGENT);
	global	$vars;
	$cacheDir = CACHE_DIR . 'blogcard';
	$target = (PLUGIN_BLOGCARD_NEWTAB != 0)? '_blank' : '_top';

	if (isset($vars['query'])) {
		// 要求受付
		$query = strtolower($vars['query']);
		$body = 'Invalid query';

		// 全キャッシュファイル消去
		switch ($query) {
		case 'clear':
			if (file_exists($cacheDir) && $dir = glob($cacheDir . '/*.dat')) {
				foreach ($dir as $file) unlink($file);
			}
			$body = 'Caches deleted';
			break;
		}

		return array('msg' => 'blogcard plugin', 'body' => 'blogcard plugin: ' . $body);
	} else
	if (isset($vars['refer'])) {
		// ブログカード表示
		$url = $vars['refer'];

		if (!file_exists($cacheDir)) mkdir($cacheDir);

		// キャッシュファイルの有無を確認
		$cacheFile = $cacheDir . '/'. encode($url) . '.dat';
		if (file_exists($cacheFile) && ((PLUGIN_BLOGCARD_CACHE_AGE <= 0) || (filemtime($cacheFile) + PLUGIN_BLOGCARD_CACHE_AGE) >= time())) {
			// キャッシュファイル有効ならサイト情報を取り出す
			$fp = fopen($cacheFile, 'r');
			$data = fread($fp, 128 * 1024);
			fclose($fp);
		} else {
			// サイト情報取得
			$data = plugin_blogcard_getProperties($url);

			// 画像あり？
			if ($data['image']) {
				if (function_exists('imagecreatetruecolor')) {
					// GDモジュールがあれば画像をロードし、小さくリサイズしてキャッシュファイルに埋め込む

					// 拡張子から種別を判断して画像ロード
					$v = preg_replace('/\?.*/i', '', $data['image']);
					$ext = substr($v, strrpos($v, '.') + 1);
					switch ($ext) {
					case 'jpg':
					case 'jpeg':
						$src = imagecreatefromjpeg($data['image']);
						$type = 'image/jpeg';
						break;
					case 'png':
						$src = imagecreatefrompng($data['image']);
						$type = 'image/png';
						break;
					case 'gif':
						$src = imagecreatefromgif($data['image']);
						$type = 'image/gif';
						break;
					case 'webp':
						$src = imagecreatefromwebp($data['image']);
						$type = 'image/webp';
						break;
					case 'bmp':
						$src = imagecreatefrombmp($data['image']);
						$type = 'image/bmp';
						break;
					default:
						$src = null;
						break;
					}

					if ($src) {
						// リサイズ
						$w = (int)imagesx($src);
						$h = (int)imagesy($src);
						if ($w >= $h) {
							$x = (int)round(($w - $h) / 2);
							$y = 0;
							$w = $h;
						} else {
							$y = (int)round(($h - $w) / 2);
							$x = 0;
							$h = $w;
						}
						$dst = imagecreatetruecolor(180, 180);
						imagealphablending($dst, false);
						imagecopyresampled($dst, $src, 0, 0, $x, $y, 180, 180, $w, $h);

						// バイナリ出力
						ob_start();
						switch ($type) {
						case 'image/jpeg':
							imagejpeg($dst, null, 80);
							break;
						case 'image/png':
							imagesavealpha($dst, true);
							imagepng($dst);
							break;
						case 'image/gif':
							imagegif($dst);
							break;
						case 'image/webp':
							imagesavealpha($dst, true);
							imagewebp($dst);
							break;
						case 'image/bmp':
							imagebmp($dst);
							break;
						}
						$raw = ob_get_contents();
						ob_end_clean();

						// Base64エンコードしてサイト情報に追加
						$data['_p_blogcard_image'] = 'data:' . $type . ';base64,' . base64_encode($raw);
					}
				} else {
					// GDモジュールがなければ何もせず、画像は外部URLのままとする。
					// ファイルアップロードと同等のリスクがあるため、オリジナル画像を直接キャッシュすることはしない。
					// PukiWiki設定でファイル添付が許可されていたら、ファイルサイズがアップロード可能サイズに収まる限りでキャッシュしても良いかもしれない
				}
			}

			// サイト情報をJSON形式でキャッシュファイルに保存
			$data = json_encode($data);
			$fp = fopen($cacheFile, 'w');
			flock($fp, LOCK_EX);
			rewind($fp);
			fwrite($fp, $data);
			flock($fp, LOCK_UN);
			fclose($fp);
		}

		// 以下、サイト情報からブログカードHTMLを生成して出力
		$data = json_decode($data);

		$url = filter_var($data->url, FILTER_SANITIZE_URL);
		$siteName = plugin_blogcard_sanitize($data->site_name);
		//$siteName .= ($siteName ? ' - ' : '') . ((preg_match('/^https?:\/\/((www\.)?[0-9a-z\-\.]+:?[0-9]{0,5})/', $url, $matches))? $matches[1] : $url);

		$charset = ($data->charset)? $data->charset : 'UTF-8';

		$title = plugin_blogcard_sanitize($data->title);
		if (isset($data->description)) {
			$desc = '<div class="desc wb">' . plugin_blogcard_sanitize($data->description) . '</div>';
			$titleClass = 'wb';
		} else {
			$desc = '';
			$titleClass = 'titleTwo';
		}

		if (isset($data->_p_blogcard_image)) {
			$image = $data->_p_blogcard_image;
			$imageTag = '<div class="image"></div>';
			$imageSize = '90px';
		} else
		if (isset($data->image)) {
			$image = filter_var($data->image, FILTER_SANITIZE_URL);
			$imageTag = '<div class="image"></div>';
			$imageSize = '90px';
		} else {
			$image = $imageTag = '';
			$imageSize = 0;
		}

		switch (PLUGIN_BLOGCARD_THEME) {
		case 1:
			$themeCss = 'a{color:#ddd;color:var(--dcolor,#ddd)';
			break;
		case 2:
			$themeCss = '@media screen and (prefers-color-scheme: dark){a{color:#ddd;color:var(--dcolor,#ddd);.image{filter: brightness(90%)}}';
			break;
		default:
			$themeCss = '';
			break;
		}

		header('Content-Type: text/html; charset=' . $charset);

		$html = <<< EOT
<!DOCTYPE html>
<html>
<head>
<meta charset="{$charset}"/>
<meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1,viewport-fit=cover"/>
<meta name="format-detection" content="telephone=no"/>
<meta name="robots" content="noindex,nofollow,noarchive"/>
<style>
html,body{width:100%;height:100%;margin:0;padding:0;border:none;overflow:hidden;box-sizing:border-box;-webkit-touch-callout:none;-webkit-text-size-adjust:100%}

a {
	display: block;
	width: 100%;
	height: 100%;
	padding: 0 0 0 8px;
	margin: 0;
	box-sizing: border-box;
	border: 1px solid rgba(128,128,128,.25);
	border-radius: 6px;
	overflow: hidden;
	background: transparent;
	font-family: -apple-system,BlinkMacSystemFont,Helvetica Neue,Segoe UI,Hiragino Kaku Gothic ProN,Hiragino Sans,Meiryo,sans-serif;
	font-size: 12px;
	line-height: 1em;
	vertical-align: middle;
	color: #222;
	color: var(--color, #222);
	cursor: pointer;
	-webkit-font-feature-settings: 'kern';
	font-feature-settings: 'kern';
	-webkit-font-kerning: normal;
	font-kerning: normal;
	text-decoration: none;
}

a > div {
	display: inline-block;
	float: left;

	min-width: 90px;
	min-height: 90px;
	width: 90px;
	height: 90px;
	max-width: 90px;
	max-height: 90px;
}

.wb {
	overflow-wrap: normal;
	word-break: keep-all;
	white-space: nowrap;
	text-overflow: ellipsis;
	hyphens: none;
}

.image {
	background-image: url({$image});
	background-size: cover;
}

.text {
	min-width: 0;
	min-height: 90px;
	width: 100%;
	height: 75px;
	max-width: 530px;
	max-width: calc(100% - {$imageSize});
	max-height: 75px;
	padding-top: 7.5px
}

.text > div {
	min-width: 0;
	width: 100%;
	overflow: hidden;
	padding: 0 4px 0 0;
	margin: 0;
	box-sizing: border-box;
	min-height: 0;
	height: 100%;
	max-height: 25px;
	line-height: 25px;
}

.title {
	font-size: 16px;
	font-weight: bold;
	vertical-align: bottom;
}
.text > div.titleTwo { max-height: 50px; }
.desc {
	vertical-align: middle;
	opacity: .666;
}
.url {
	vertical-align: top;
}

{$themeCss}
</style>
</head>
<body>
<a href="{$url}" rel="noopener external" target="{$target}">
<div class="text">
<div class="title {$titleClass}">{$title}</div>
{$desc}
<div class="url wb">{$siteName}</div>
</div>
{$imageTag}
</a>
</body>
</head>
EOT;
	}

	echo $html;
	exit;
}


// サイト情報取得
// @param	$url	URL文字列
// @return			サイト情報連想配列。取得できなければNULL
function plugin_blogcard_getProperties($url) {
	$result = array();
	$result['url'] = $url;			// 戻り値 url を設定
	$result['site_name'] = (preg_match('/^https?:\/\/((www\.)?[0-9a-z\-\.]+:?[0-9]{0,5})/', $url, $matches))? $matches[1] : $url;	// 戻り値 site_name にもラベルとして url を仮設定しておく
	$result['title'] = $url;		// 戻り値 title にもラベルとして url を仮設定しておく

	do {
		// 対象ページのHTMLを取得
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/html', 'Accept-Language: ' . LANG, 'User-Agent: ' . PLUGIN_BLOGCARD_USERAGENT)); // 言語やUAで応答が異なるサイトも多いため設定
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		$html = curl_exec($ch);
		$type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		curl_close($ch);

		if (!$html || stripos($type, 'text/html') === false) break;
		if (preg_match('/charset=(.+)/i', $type, $matches)) $result['charset'] = $matches[1]; // 戻り値 charset を設定

		// OGP名前空間「http://ogp.me/ns#」のプリフィクス宣言を探す。なければ「og」とする。なお、OGPを表すogプリフィクス宣言は『RDFa Initial Context』仕様で省略が認められている。
		// DOM構造をきちんとたどるのが本来だが、どうせ定型の書き方しかされないうえ元々の仕様が緩いため、単なる文字列検索で代替する。宣言など無視してog決め打ちのブログカード実装もよくあるくらいなので、まず問題ないであろう
		$prefix = (preg_match('/prefix=[\"\']?\s*([a-zA-Z0-9_\-\.]+)\:\s*http\:\/\/ogp\.me\/ns\#/i', $html2, $matches))? $matches[1] : 'og';

		// 以下、DOMからタグを探す処理
		$doc = new DomDocument();
		if (!$doc->loadHTML($html)) break;
		$xpath = new DOMXPath($doc);

		// head > titleタグ
		$elements = $xpath->query('//head/title');
		if (isset($elements[0])) $result['title'] = $elements[0]->nodeValue; // 戻り値 title を設定

		// head > linkタグ
		$elements = $xpath->query('//head/link');
		$sizes = 0;
		foreach ($elements as $element) {
			if ($rel = $element->getAttribute('rel')) {
				// sizes指定があった場合、より低いものは無視する
				if ($v = (int)$element->getAttribute('sizes')) {
					if ($sizes < $v) $sizes = $v;
					else continue;
				}

				$rel = strtolower($rel);
				if (!$result['image'] && ($rel == 'icon' || $rel == 'shortcut icon')) { // ファビコン
					$result['image'] = $element->getAttribute('href'); // 戻り値 image を設定
				} else
				if (strpos($rel, 'apple-touch-icon') !== false) { // AppleTouchIcon
					$result['image'] = $element->getAttribute('href'); // 戻り値 image を設定
				}
			}
		}

		// head > metaタグ
		$elements = $xpath->query('//head/meta');
		foreach ($elements as $element) {
			$name = $element->getAttribute('name');
			if ($name && $name == 'description') {
				$result['description'] = $element->getAttribute('content'); // 戻り値 description を設定
			} else
			if ($v = $element->getAttribute('charset')) $result['charset'] = $v; // 戻り値 charset を設定
			else if ($v = $element->getAttribute('http-equiv') && strtolower($v) == 'content-type') $result['charset'] = $element->getAttribute('content'); // 戻り値 charset を設定
		}

		// head > metaタグ OGP情報
		if ($prefix) {
			//$elements = $xpath->query('//head/meta');
			foreach ($elements as $element) {
				$property = $element->getAttribute('property');
				if ($property && strpos($property, $prefix . ':') === 0) {
					// 戻り値 url, site_name, title, image, description 等を設定（すでにあるものは上書き）
					$property = strtolower(str_replace($prefix . ':', '', $property));
					$result[$property] = trim($element->getAttribute('content'));
				}
			}
		}

		// image:url, image:secure_url 対応
		if (isset($result['image:secure_url'])) $result['image'] = $result['image:secure_url'];
		else if (isset($result['image:url'])) $result['image'] = $result['image:url'];

		// url, image がもし相対パスなら是正
		if (strpos($result['url'], 'http://') !== 0 && strpos($result['url'], '//')) $result['url'] = $url;
		if ($result['image']) {
			$img = $result['image'];
			if (strpos($img, 'http') !== 0) {
				if (strpos($img, '/') === 0 && preg_match('/^(https?:\/\/(www\.)?[0-9a-z\-\.]+:?[0-9]{0,5})/', $url, $matches)) {
					$result['image'] = $img = $matches[1] . $img;
				} else {
					if (strpos($img, './') === 0) $img = substr($img, 2);
					$result['image'] = $img = (preg_match('/^(https?:\/\/(www\.)?[0-9a-z\-\.]+:?[0-9]{0,5}\/.*\/)/', $url, $matches) ? $matches[1] : $url) . $img;
				}
			}
		}
	} while (false);

	return $result;
}


// 文字列サニタイズ＆化け防止
function plugin_blogcard_sanitize($str) {
	return htmlsc((mb_detect_encoding($v = utf8_decode($str)) == 'UTF-8')? $v : $str);
}
