<?php
/**
 * Plugin Name: RTADV Article – 紙盒印刷
 * Description: One-time: creates 1 article about 紙盒印刷 via wp_insert_post().
 *              Sets Rank Math SEO meta. Self-deletes after execution.
 * Version: 1.0.0
 * Author: rtadv.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'rtadv_article_paper_box_printing_publish', 20 );

function rtadv_article_paper_box_printing_publish() {
	if ( get_option( 'rtadv_article_paper_box_printing_done_v1' ) ) {
		return;
	}

	$slug = 'paper-box-printing-guide';

	$existing = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $existing ) {
		update_option( 'rtadv_article_paper_box_printing_done_v1', true );
		@unlink( __FILE__ );
		return;
	}

	$title   = '紙盒印刷怎麼選？材質、工藝、費用與選廠 5 大重點';
	$content = rtadv_get_paper_box_printing_content();

	$post_id = wp_insert_post( [
		'post_title'    => $title,
		'post_name'     => $slug,
		'post_content'  => $content,
		'post_status'   => 'publish',
		'post_type'     => 'post',
		'post_category' => [ 95 ],
	], true );

	if ( is_wp_error( $post_id ) ) {
		$log = '[rtadv-article-paper-box-printing] ERROR: ' . $post_id->get_error_message();
		file_put_contents( WP_CONTENT_DIR . '/rtadv-article-paper-box-printing.log', $log . "\n" );
		return;
	}

	update_post_meta( $post_id, 'rank_math_focus_keyword', '紙盒印刷,紙盒印刷費用,紙盒包裝印刷' );
	update_post_meta( $post_id, 'rank_math_title', '紙盒印刷怎麼選？材質、工藝、費用與選廠重點｜圓廣印刷' );
	update_post_meta( $post_id, 'rank_math_description', '紙盒印刷從材質選擇、印刷方式到表面加工，每個環節都影響成品品質與費用。本文整理台灣紙盒印刷的主流材質、常見工藝、費用區間與選廠注意事項，幫助品牌主與採購做出正確決策。' );

	update_option( 'rtadv_article_paper_box_printing_done_v1', true );

	$log = sprintf(
		"[rtadv-article-paper-box-printing] OK at %s — post_id=%d url=%s\n",
		date( 'Y-m-d H:i:s' ),
		$post_id,
		get_permalink( $post_id )
	);
	file_put_contents( WP_CONTENT_DIR . '/rtadv-article-paper-box-printing.log', $log );

	@unlink( __FILE__ );
}

function rtadv_get_paper_box_printing_content() {
	return <<<'ENDHTML'
<p>紙盒印刷是台灣包裝產業中需求量最大的項目之一，從食品禮盒、美妝外盒到 3C 產品包裝，幾乎所有零售品牌都需要用到紙盒。然而，紙盒印刷涉及材質選擇、印刷方式、表面加工、結構設計等多個環節，每一項選擇都直接影響成本與成品質感。身為<a href="https://www.rtadv.com/color-box-printing/">台灣彩盒印刷整合專家</a>，圓廣印刷將在本文完整說明紙盒印刷的核心知識，協助台灣品牌主、採購與設計窗口做出最佳決策。</p>

<h2>紙盒印刷的主流材質</h2>

<p>紙盒印刷的第一步是選擇適合的紙材。不同材質在強度、印刷適性、質感和費用上差異明顯，以下為台灣市場最常見的四種選擇：</p>

<h3>1. 銅版紙（Coated Paper / C1S / C2S）</h3>
<p>銅版紙是紙盒印刷中使用率最高的紙種。表面經過塗佈處理，印刷色彩飽和度高、細節表現佳。常見克重為 300g–400g，單面塗佈（C1S）用於摺疊紙盒，雙面塗佈（C2S）則適合需要內外都呈現印刷效果的產品。</p>
<ul>
  <li><strong>適用場景</strong>：食品外盒、美妝彩盒、藥品包裝、日用品</li>
  <li><strong>優點</strong>：色彩還原度高、價格合理、供貨穩定</li>
  <li><strong>缺點</strong>：質感偏商業化，不適合強調手感的精品定位</li>
</ul>

<h3>2. 白卡紙（SBS / Solid Bleached Sulfate）</h3>
<p>白卡紙與銅版紙外觀相近，但紙芯為純木漿結構，挺度與白度更高。常見克重 250g–400g，適合需要較高結構強度的小型紙盒，例如化妝品、保健食品外盒。</p>
<ul>
  <li><strong>適用場景</strong>：化妝品盒、醫藥外盒、高單價食品</li>
  <li><strong>優點</strong>：挺度佳、印刷效果好、耐折度高</li>
  <li><strong>缺點</strong>：價格較銅版紙高約 15–25%</li>
</ul>

<h3>3. 灰底白板紙（GD / Grey Back Duplex Board）</h3>
<p>正面白色可印刷，背面為灰色回收紙漿。成本為三種紙材中最低，適合大量出貨且對包裝質感要求不高的品類。</p>
<ul>
  <li><strong>適用場景</strong>：日用品、五金零件、經濟型食品包裝</li>
  <li><strong>優點</strong>：價格最低、適合大量印製</li>
  <li><strong>缺點</strong>：灰底外露、挺度略差、不適合高端定位</li>
</ul>

<h3>4. 特種紙 / 美術紙</h3>
<p>包含各種進口紋路紙、珠光紙、觸感紙等，用於強調品牌質感的精品包裝。通常不直接印刷在特種紙上，而是以特種紙裱糊在灰板上，形成精裝盒結構。</p>
<ul>
  <li><strong>適用場景</strong>：精品禮盒、珠寶、高端保健品</li>
  <li><strong>優點</strong>：獨特質感、品牌辨識度高</li>
  <li><strong>缺點</strong>：單價高、庫存不穩定、需確認印刷相容性</li>
</ul>

<h2>紙盒印刷材質比較表</h2>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">
  <thead>
    <tr style="background-color:#f5f5f5;">
      <th>材質</th>
      <th>常見克重</th>
      <th>印刷適性</th>
      <th>挺度</th>
      <th>費用等級</th>
      <th>適合定位</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>銅版紙</td>
      <td>300g–400g</td>
      <td>★★★★★</td>
      <td>★★★☆☆</td>
      <td>低–中</td>
      <td>大眾消費品</td>
    </tr>
    <tr>
      <td>白卡紙</td>
      <td>250g–400g</td>
      <td>★★★★☆</td>
      <td>★★★★☆</td>
      <td>中</td>
      <td>中高端品牌</td>
    </tr>
    <tr>
      <td>灰底白板紙</td>
      <td>300g–450g</td>
      <td>★★★☆☆</td>
      <td>★★★☆☆</td>
      <td>低</td>
      <td>經濟型包裝</td>
    </tr>
    <tr>
      <td>特種紙</td>
      <td>依品種</td>
      <td>★★★☆☆</td>
      <td>依結構</td>
      <td>高</td>
      <td>精品 / 禮盒</td>
    </tr>
  </tbody>
</table>

<h2>紙盒印刷方式</h2>

<p>印刷方式直接決定色彩品質、生產速度與單價。以下為台灣紙盒印刷最常用的三種方式：</p>

<h3>平版印刷（Offset Lithography）</h3>
<p>平版印刷是台灣紙盒印刷的絕對主流，佔整體產量超過 80%。採用 CMYK 四色印刷，色彩穩定度高，適合中大量生產。常見機型為海德堡（Heidelberg）或小森（Komori）四色 / 五色印刷機。</p>
<ul>
  <li><strong>適合數量</strong>：1,000 個以上</li>
  <li><strong>優點</strong>：色彩精準、單價隨量遞減、品質穩定</li>
  <li><strong>缺點</strong>：需製版費（CTP），少量印製不划算</li>
</ul>

<h3>數位印刷（Digital Printing）</h3>
<p>適合小量、多款或客製化需求。無需製版，檔案直出，適合打樣或限量版包裝。近年 HP Indigo 等設備在紙盒印刷領域的品質已大幅提升。</p>
<ul>
  <li><strong>適合數量</strong>：50–500 個</li>
  <li><strong>優點</strong>：無製版費、可變資料印刷、交期快</li>
  <li><strong>缺點</strong>：大量印製單價偏高、色域略窄於平版</li>
</ul>

<h3>網版印刷（Screen Printing）</h3>
<p>通常不作為紙盒主體印刷方式，而是用於局部特殊效果，例如厚層 UV、螢光墨、夜光墨等。常搭配平版印刷使用。</p>

<h2>紙盒表面加工工藝</h2>

<p>表面加工是紙盒印刷中影響質感最大的環節。以下為常見選項：</p>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">
  <thead>
    <tr style="background-color:#f5f5f5;">
      <th>加工名稱</th>
      <th>效果說明</th>
      <th>費用影響</th>
      <th>注意事項</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>亮膜（Glossy Lamination）</td>
      <td>高光澤、色彩鮮豔</td>
      <td>低</td>
      <td>易反光、指紋明顯</td>
    </tr>
    <tr>
      <td>霧膜（Matte Lamination）</td>
      <td>柔和觸感、低調質感</td>
      <td>低</td>
      <td>深色易刮花，可加防刮處理</td>
    </tr>
    <tr>
      <td>局部上光（Spot UV）</td>
      <td>特定區域亮面凸顯</td>
      <td>中</td>
      <td>需另製版、對位精度要求高</td>
    </tr>
    <tr>
      <td>燙金 / 燙銀（Hot Foil Stamping）</td>
      <td>金屬光澤、高端感</td>
      <td>中–高</td>
      <td>需製銅版、面積影響費用</td>
    </tr>
    <tr>
      <td>壓凸 / 壓凹（Emboss / Deboss）</td>
      <td>立體觸感</td>
      <td>中</td>
      <td>需製鋅版或銅版</td>
    </tr>
    <tr>
      <td>打凸（Raised UV / Textured UV）</td>
      <td>厚層 UV 形成立體圖案</td>
      <td>中–高</td>
      <td>適合 logo 或重點圖案</td>
    </tr>
  </tbody>
</table>

<p>圓廣印刷建議：多數品牌最常採用「霧膜 + 局部 UV」或「霧膜 + 燙金」的組合，既控制成本又能有效提升質感。詳情可參考<a href="https://www.rtadv.com/contact/">聯絡詢價</a>頁面。</p>

<h2>紙盒印刷費用怎麼算？</h2>

<p>紙盒印刷費用由多個因素組成，以下為主要成本結構：</p>

<h3>費用組成</h3>
<ul>
  <li><strong>刀模費</strong>：一次性費用，NT$1,500–5,000 不等，依盒型複雜度而定</li>
  <li><strong>製版費（CTP）</strong>：平版印刷需要，四色約 NT$2,000–4,000</li>
  <li><strong>紙材費</strong>：依材質與克重計算，佔總成本 30–40%</li>
  <li><strong>印刷費</strong>：依色數、印量、機台計算</li>
  <li><strong>加工費</strong>：上膜、燙金、UV 等各項後加工</li>
  <li><strong>軋盒 / 糊盒費</strong>：裁切與黏合組裝</li>
</ul>

<h3>常見規格費用參考</h3>

<table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse;">
  <thead>
    <tr style="background-color:#f5f5f5;">
      <th>規格描述</th>
      <th>數量</th>
      <th>單價參考（NT$）</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>300g 銅版紙 / 四色 / 霧膜 / 小型盒</td>
      <td>1,000 個</td>
      <td>$12–20/個</td>
    </tr>
    <tr>
      <td>350g 白卡紙 / 四色 / 霧膜 + 局部 UV</td>
      <td>2,000 個</td>
      <td>$15–28/個</td>
    </tr>
    <tr>
      <td>350g 銅版紙 / 四色 / 霧膜 + 燙金</td>
      <td>3,000 個</td>
      <td>$18–35/個</td>
    </tr>
    <tr>
      <td>灰板裱糊精裝盒 / 特種紙 / 燙金</td>
      <td>500 個</td>
      <td>$80–200/個</td>
    </tr>
  </tbody>
</table>

<p><em>以上為概估費用，實際報價依尺寸、加工項目與數量而定。歡迎透過<a href="https://www.rtadv.com/contact/">圓廣印刷報價頁面</a>取得精確報價。</em></p>

<h2>紙盒印刷流程</h2>

<p>一個標準的紙盒印刷專案通常包含以下步驟：</p>

<ol>
  <li><strong>需求確認</strong>：確定盒型、尺寸、材質、數量與加工需求</li>
  <li><strong>結構設計</strong>：印刷廠提供刀模圖（Dieline），設計師在刀模上完成視覺稿</li>
  <li><strong>打樣確認</strong>：數位打樣或實體打樣，確認色彩與結構</li>
  <li><strong>製版印刷</strong>：CTP 出版、上機印刷</li>
  <li><strong>表面加工</strong>：上膜、燙金、UV 等後加工</li>
  <li><strong>軋盒糊盒</strong>：刀模裁切、摺疊黏合成型</li>
  <li><strong>品檢出貨</strong>：抽檢色差、結構、加工品質後出貨</li>
</ol>

<p>圓廣印刷標準工期約 7–12 個工作日（視加工項目與數量），急件可配合加速排程。</p>

<h2>選擇紙盒印刷廠的五個重點</h2>

<p>台灣紙盒印刷廠數量眾多，品質與服務差異大。以下為選廠時最關鍵的五個評估面向：</p>

<ol>
  <li><strong>自有印刷設備</strong>：自有機台的廠商在色彩管控與交期上較穩定，避免外發轉包的品質風險</li>
  <li><strong>後加工能力</strong>：確認燙金、UV、壓凸等加工是否廠內完成，還是需要外包</li>
  <li><strong>打樣服務</strong>：是否提供實體打樣？打樣與量產的色差控制能力如何？</li>
  <li><strong>最小起訂量</strong>：依品牌階段選擇合適的廠商，新創品牌需找能接小量的廠商</li>
  <li><strong>溝通效率</strong>：是否有專人對接？能否快速回覆報價與技術問題？</li>
</ol>

<h2>常見問題</h2>

<h3>紙盒印刷最少可以做幾個？</h3>
<p>平版印刷通常 1,000 個起較划算。若需求在 500 個以下，建議考慮數位印刷，雖然單價略高，但省去製版費，總費用可能更低。</p>

<h3>紙盒印刷的交期一般是多久？</h3>
<p>標準彩盒（銅版紙 / 白卡紙 + 基本加工）約 7–12 個工作日。精裝盒或含多項加工的訂單約 15–20 個工作日。急件需另行確認。</p>

<h3>印刷檔案需要提供什麼格式？</h3>
<p>建議提供 AI（Adobe Illustrator）或 PDF 格式，文字需轉外框，圖片解析度 300dpi 以上，色彩模式為 CMYK。出血區域需預留 3mm。</p>

<h3>紙盒印刷可以做食品級嗎？</h3>
<p>可以。食品級紙盒需使用符合 FDA 或台灣食品容器標準的紙材與油墨。圓廣印刷可提供食品級材質選項，詳情請<a href="https://www.rtadv.com/contact/">聯絡詢價</a>。</p>

<h3>燙金和局部 UV 可以同時做嗎？</h3>
<p>可以，但需注意加工順序。通常先上霧膜，再做燙金，最後施作局部 UV。加工項目越多，費用與工期都會增加，建議在打樣階段確認最終效果。</p>
ENDHTML;
}
