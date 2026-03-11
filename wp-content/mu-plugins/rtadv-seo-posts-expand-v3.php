<?php
/**
 * Plugin Name: RTADV SEO Posts Expand v3
 * Description: Expand four thin auto-generated posts (55107, 55106, 55131, 55129).
 *              Runs once on admin init, writes log, then self-deletes.
 * Author: rtadv.com
 * Version: 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'rtadv_posts_expand_v3_run', 1 );

function rtadv_posts_expand_v3_run() {
	if ( ! ( defined( 'WP_CLI' ) || is_admin() ) ) {
		return;
	}
	$lock_key = 'rtadv_posts_expand_v3_done';
	if ( get_option( $lock_key ) ) {
		return;
	}
	update_option( $lock_key, time(), false );

	$log     = [];
	$updates = rtadv_posts_expand_v3_data();

	foreach ( $updates as $item ) {
		$pid = $item['id'];
		if ( ! get_post( $pid ) ) {
			$log[] = "SKIP pid=$pid (not found)";
			continue;
		}
		$result = wp_update_post( [
			'ID'           => $pid,
			'post_content' => $item['content'],
		], true );
		if ( is_wp_error( $result ) ) {
			$log[] = "ERROR pid=$pid: " . $result->get_error_message();
		} else {
			$log[] = "OK pid=$pid slug={$item['slug']}";
		}
		if ( ! empty( $item['focus_kw'] ) ) {
			update_post_meta( $pid, 'rank_math_focus_keyword', $item['focus_kw'] );
		}
		if ( ! empty( $item['rm_title'] ) ) {
			update_post_meta( $pid, 'rank_math_title', $item['rm_title'] );
		}
		if ( ! empty( $item['rm_desc'] ) ) {
			update_post_meta( $pid, 'rank_math_description', $item['rm_desc'] );
		}
	}

	$log_file = WP_CONTENT_DIR . '/rtadv-posts-expand-v3.log';
	file_put_contents( $log_file, implode( "\n", $log ) . "\n" );
	@unlink( __FILE__ );
}

function rtadv_posts_expand_v3_data() {
	return [

		// ── 1. 第一次詢價就到位 (ID=55107) ──────────────────────────────────
		[
			'id'       => 55107,
			'slug'     => 'packaging-quote-readiness-checklist-first-time',
			'focus_kw' => '包裝詢價,包裝詢價清單',
			'rm_title' => '第一次詢價就到位：6 大必備欄位與可複製模板｜圓廣印刷',
			'rm_desc'  => '包裝詢價前備齊用途、尺寸、數量、交期、設計稿與預算，廠商才能給出可執行報價。本文提供逐欄說明、常見補件情境解法與可直接複製的詢價模板。',
			'content'  => '<!-- wp:paragraph -->
<p>第一次詢價能不能拿到有效報價，取決於一件事：你帶著多少資訊去問。資訊不足，廠商只能給粗估，來回補件三到五次後才進入正式評估，整體時程可能多花兩到三週。這份清單把六個必要欄位說清楚，讓你一次把資料備齊，詢價流程走得比平均快一倍。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>六個必備欄位，缺一個就會補件</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>1. 產品用途</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>說清楚包材要裝什麼、給誰用。「零售門市手提袋」和「食品外帶包裝袋」雖然外形相似，但材質要求、合規需求、印刷工藝完全不同。填這個欄位時，具體說出：零售 / 活動派發 / 食品包裝 / 禮贈 / 電商出貨，比「一般包裝」有用十倍。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>2. 尺寸規格</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>提供長 × 寬 × 高，附上單位（公分或毫米）。若是袋類，說明是否有側褶、底部結構（平底 / 站立袋 / 梯形底）。沒有精確尺寸沒關係，但要給出「大約 A4 大小、高度 30 公分左右」這類範圍，讓廠商知道落在哪個規格區間，才能給出可參考的報價。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>3. 數量區間</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>數量影響的不只是單價，還有材質選項、印刷工藝和生產排程。500 件和 5000 件的工藝路線可能完全不同。若尚未確定，請給出「最低需求量」和「理想採購量」兩個數字，廠商可以同時提供兩個方案讓你比較。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>4. 交期需求</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>寫出「希望到貨日」或「最晚到貨日」，不要只說「越快越好」。客製包材從確認稿件到出貨，標準前置時間是三到六週，特殊材質或複雜加工需要更長。若交期緊，提前說清楚，廠商才能評估是否需要急件排程（通常有附加費用）。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>5. 設計稿或參考資料</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>有品牌 logo、設計稿或競品參考圖，請一起附上。即使設計稿尚未完成，提供「色彩方向」「主視覺元素」「品牌參考」也有幫助。設計稿越完整，廠商越能準確評估印刷版費、色數和特殊加工費用。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>6. 預算範圍（選填但有幫助）</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>很多人不想先透露預算，但適度提供範圍，能讓廠商優先推薦符合成本的方案，而不是從最高規格往下談。填法：「單件成本希望控制在 XX 元以下」或「總預算約 XX 萬」，不需要給出精確數字。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>為什麼這六個欄位缺一不可</h2>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>缺少的欄位</th><th>實際影響</th></tr></thead><tbody><tr><td>用途不明</td><td>廠商無法判斷合規需求，報價包含不必要的安全加成</td></tr><tr><td>尺寸不清</td><td>只能給「區間報價」，差距可能達 30–50%</td></tr><tr><td>數量不明</td><td>無法確認 MOQ，可能推薦錯誤工藝路線</td></tr><tr><td>交期模糊</td><td>廠商無法排程，急件需求最後才揭露反而更貴</td></tr><tr><td>無設計稿</td><td>版費和印刷費無法估算，最終報價可能多出 20–40%</td></tr><tr><td>無預算方向</td><td>廠商只能從高規格報起，來回調整增加時程</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading -->
<h2>詢價模板：可直接複製使用</h2>
<!-- /wp:heading -->

<!-- wp:code -->
<pre class="wp-block-code"><code>包材詢價資訊

用途：[零售 / 活動派發 / 食品 / 禮贈 / 其他]
材質偏好：[紙袋 / 帆布袋 / 不織布袋 / 鋁箔袋 / 未定]
尺寸規格：長＿＿ × 寬＿＿ × 高＿＿（公分）
數量區間：[最低需求量] ～ [理想採購量]
印刷加工：[單色 LOGO / 全彩印刷 / 特殊加工]
到貨日需求：[希望到貨日 / 最晚到貨日]
設計稿狀態：[已完成 / 設計中 / 僅有 LOGO / 尚未開始]
預算範圍：[單件約＿元 / 總預算約＿萬]（選填）
參考連結：[設計稿連結 / 競品圖片連結]（有則附上）</code></pre>
<!-- /wp:code -->

<!-- wp:heading -->
<h2>常見資料不足的情況與解法</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>尺寸還沒確定</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>先量一下你要裝入的主要商品，給出「長寬高的大概範圍」即可。例如：「商品最大約 20×15×5 公分，袋子需要能裝入且不太寬鬆」——這樣的描述已經能讓廠商推薦 2–3 個標準規格供選擇。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>數量還沒敲定</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>給出「計畫數量」和「最低願意採購量」兩個數字。例如：「活動預計派發 2000 個，但如果 1000 個起訂報價合理也可以接受」。廠商可以同時提供兩個 MOQ 的報價讓你評估。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>設計稿尚未完成</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>提供：品牌主色（Pantone 色號或 RGB）、主要 LOGO 檔案（AI/PDF/PNG）、大概的設計風格說明（簡約 / 繁複 / 全版印刷 / 主色塊）。這些資訊夠讓廠商評估版費基準，等設計稿完成再微調。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>常見問題</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>樣品費要付嗎？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>大多數客製包材廠商會收樣品費，費用通常在幾百到幾千元之間，視材質複雜度而定。正式下單後，部分廠商會折抵樣品費。詢價時可直接問「打樣費是多少，量產後是否折抵」。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>報價有效期多久？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>通常 30 天，但若遇到材料漲價或匯率波動，廠商可能提前通知調整。若你需要比較多家報價，建議在同一週內完成詢價，避免因時間差影響比較基準。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>一次詢問多款是否可以？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>可以，但要為每款分別填寫完整欄位，不要混在一起描述。廠商處理多款詢價時，資料越清楚，回覆速度越快、報價越準確。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>材質還沒決定，可以詢價嗎？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>可以，但效率較低。建議先確認「用途、承重需求、阻隔需求、預算敏感度」四個條件，快速收斂材質路線後再詢價，能省去 2–3 次來回確認。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>如果真的什麼資料都沒有，怎麼辦？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>至少確認「用途」和「大概數量」這兩個欄位，其他可以標「待確認」。廠商會在回覆中告訴你還需要補充哪些資訊。但要有心理準備：資料越少，第一次回覆通常是補件清單，而不是報價單。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>把這份清單帶著走，第一次詢價就能拿到可執行的回覆。</p>
<!-- /wp:paragraph -->',
		],

		// ── 2. 袋罐盒怎麼選 (ID=55106) ──────────────────────────────────────
		[
			'id'       => 55106,
			'slug'     => 'how-to-choose-bag-tube-box-before-quote',
			'focus_kw' => '包裝選擇,紙袋紙罐紙盒比較',
			'rm_title' => '袋、罐、盒怎麼選？包裝需求前先做這 5 個判斷｜圓廣印刷',
			'rm_desc'  => '紙袋、紙罐（紙管）、紙盒三種包材怎麼挑？從用途、展示需求、承重、阻隔、預算 5 個維度快速判斷，避免詢價後反覆改路線浪費時間。',
			'content'  => '<!-- wp:paragraph -->
<p>台灣品牌在選包材時，最常卡在同一個問題：紙袋、紙罐（紙管）、紙盒，到底哪個才對？三者都能承裝商品、都可客製印刷，但設計邏輯、功能定位和成本結構完全不同。選錯路線，打樣完才發現不可行，整個流程重來，時程至少延後四到八週。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>這篇文章用五個判斷維度，幫你在進入詢價前就收斂路線，減少後續補件和反覆確認的時間。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>三種包材的基本定位</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li><strong>手提紙袋</strong>：門市提袋、禮品外袋、活動派發。功能是「承載與攜帶」，品牌曝光面積大，成本相對低，適合大量採購。</li><li><strong>紙罐 / 紙管</strong>：圓形容器，密封性好。適合散裝食品（茶葉、堅果、蜂蜜）、保健品、香氛蠟燭等需要「保存」的品類。開蓋設計提升使用儀式感。</li><li><strong>紙盒（彩盒 / 精裝盒）</strong>：最廣泛的承裝形式。從折疊彩盒到硬殼精裝盒，適合大多數商品需要「展示保護」的情境。</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>五個判斷維度</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>維度 1：商品是放入還是裝入？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>「放入」代表商品本身有包裝（如已裝盒的產品），只需外袋攜帶 → 選<strong>手提紙袋</strong>。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>「裝入」代表包材本身就是主要容器，商品直接放進去 → 選<strong>紙罐</strong>（散裝品）或<strong>紙盒</strong>（固體成型商品）。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>維度 2：需要密封或阻隔嗎？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>如果商品有保存期限、需要防潮或阻氧（食品、保健品、香氛品）→ 優先考慮<strong>紙罐</strong>（含鋁箔內膜可達中等阻隔）或<strong>鋁箔袋</strong>（高阻隔需求）。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>紙袋和一般彩盒沒有阻隔功能，若商品對濕氣敏感，這兩種包材需要搭配額外的內包裝。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>維度 3：展示面積多重要？</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>品牌故事、視覺設計是核心訴求 → 選<strong>紙盒</strong>（六面可印）或<strong>手提紙袋</strong>（兩側大面積展示）。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>圓形包材（紙罐）只能做圍繞式標籤，無法呈現大面積平面設計。若你的品牌設計以方形版面為主，紙罐需要額外確認視覺適配性。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>維度 4：預計採購數量是多少？</h3>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>包材</th><th>一般 MOQ</th><th>最划算數量區間</th></tr></thead><tbody><tr><td>手提紙袋</td><td>500–1000 件</td><td>3,000 件以上單價最穩</td></tr><tr><td>紙罐 / 紙管</td><td>1,000–3,000 件</td><td>5,000 件以上進入量產甜蜜點</td></tr><tr><td>彩盒</td><td>500–1,000 件</td><td>3,000 件以上有明顯優勢</td></tr><tr><td>精裝盒</td><td>200–500 件</td><td>適合中低量精品定位</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>若採購量低於 500 件，紙罐的模具費和小量溢價會讓單價偏高；彩盒和手提紙袋在這個數量區間相對更具彈性。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>維度 5：交期壓力多大？</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li><strong>手提紙袋</strong>：標規款 2–3 週；客製款 4–6 週</li><li><strong>彩盒</strong>：折疊彩盒 3–5 週；精裝盒 5–8 週</li><li><strong>紙罐</strong>：標規尺寸 3–4 週；特殊直徑需要 6–10 週</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>交期在一個月以內，優先選擇有標規款的路線，或提前確認廠商是否有備料庫存。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>快速對照表</h2>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>需求情境</th><th>優先路線</th><th>備選路線</th></tr></thead><tbody><tr><td>門市零售提袋</td><td>手提紙袋</td><td>不織布袋（大量派發）</td></tr><tr><td>散裝食品（茶葉、堅果）</td><td>紙罐</td><td>鋁箔袋（高阻隔需求）</td></tr><tr><td>保健食品膠囊 / 粉末</td><td>紙罐 + 鋁箔內膜</td><td>鋁箔袋</td></tr><tr><td>禮品外包裝</td><td>精裝盒</td><td>彩盒（預算敏感）</td></tr><tr><td>電商出貨盒</td><td>瓦楞彩盒</td><td>折疊彩盒（輕量商品）</td></tr><tr><td>展覽活動派發</td><td>不織布袋</td><td>手提紙袋（品牌感優先）</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading -->
<h2>常見選錯情況</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>食品選了一般紙盒</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>一般彩盒沒有阻隔功能。若內容物對濕度敏感，單用彩盒會縮短保存期限，還可能違反食品包裝法規。正確做法是在彩盒內加獨立包裝袋，或改用有內膜的紙罐。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>活動派發選了精裝盒</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>精裝盒成本是彩盒的 3–5 倍。活動派發量通常在千件以上，這個規模選精裝盒，成本會是手提紙袋或不織布袋的 5–10 倍，很難說服採購端。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>小量採購選了紙罐</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>紙罐在 1000 件以下，模具費和小量溢價會讓單件成本高出預期。採購量在 500 件以下，先評估是否有標規尺寸可以套用，或考慮改用鋁箔站立袋替代，單價通常低 30–50%。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>確認路線後，下一步</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>確定包材類型後，帶著以下資訊進入詢價：</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li>確認後的包材類型（紙袋 / 紙罐 / 彩盒 / 精裝盒）</li><li>商品尺寸或希望的包材尺寸（長 × 寬 × 高）</li><li>採購數量區間</li><li>是否有特殊材質或阻隔需求</li><li>目標到貨日</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>資料備齊後再送出需求，廠商通常能在 1–2 個工作天內回覆可行的報價方向，而不是補件清單。</p>
<!-- /wp:paragraph -->',
		],

		// ── 3. 紙袋印刷價格怎麼抓 (ID=55131) ────────────────────────────────
		[
			'id'       => 55131,
			'slug'     => 'paper-bag-printing-cost-material-quantity-guide',
			'focus_kw' => '紙袋印刷價格,紙袋報價',
			'rm_title' => '紙袋印刷價格怎麼抓？材質、數量、加工與交期的估算方法｜圓廣印刷',
			'rm_desc'  => '紙袋印刷報價落差大，通常是前提條件不同所致。本文逐一拆解材質、數量、印刷色數、加工與交期對單價的影響，並提供可直接參考的報價區間。',
			'content'  => '<!-- wp:paragraph -->
<p>紙袋印刷的報價為什麼每家差這麼多？原因幾乎都在「條件不同」，而不是廠商亂報。同樣是手提紙袋，用 150g 白牛皮紙的成本，和用 250g 特種銅版紙的成本，可以差到三倍以上。這篇文章把影響紙袋印刷價格的四個主要變數一一拆解，讓你在比較報價前，先讓條件一致。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>影響紙袋印刷價格的四個主要變數</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>變數 1：紙材</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>紙袋成本結構中，紙材通常佔 40–60%。常見紙材選項與相對成本：</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>紙材</th><th>特性</th><th>相對成本</th></tr></thead><tbody><tr><td>白牛皮紙（120–150g）</td><td>輕量環保感，適合食品外帶</td><td>低</td></tr><tr><td>本色牛皮紙（120–150g）</td><td>原色質感，環保訴求強</td><td>低</td></tr><tr><td>銅版紙（200–250g）</td><td>色彩還原度高，適合精品</td><td>中</td></tr><tr><td>特種紙（書寫紙、雪銅）</td><td>觸感特殊，品牌差異化</td><td>中高</td></tr><tr><td>進口藝術紙</td><td>紋路獨特，高端禮品袋</td><td>高</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>同一款設計，從白牛皮換成進口藝術紙，單件成本增加 50–120%，視克重和採購量而定。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>變數 2：印刷色數與工藝</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>紙袋印刷以平版印刷（Offset）為主，色數多寡直接影響版費與印刷成本：</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li><strong>單色印刷</strong>：版費最低，適合 LOGO 單色、簡約風格</li><li><strong>雙色印刷</strong>：比單色版費約增加 60–80%</li><li><strong>四色全彩（CMYK）</strong>：適合全版設計、照片、漸層色</li><li><strong>特別色（Pantone）</strong>：精確色彩還原，但每個 Pantone 色單獨計費</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>表面加工會額外增加費用：霧膜 / 亮膜（+8–15%）、局部 UV（+15–25%）、燙金銀箔（+20–40%）、壓凸 / 壓凹（+10–20%）。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>變數 3：數量</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>版費是固定成本，數量越多，版費分攤越低，單件成本下降越明顯。台灣市場常見紙袋 MOQ 與單價關係：</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>採購數量</th><th>單件成本（四色全彩，一般銅版紙）</th></tr></thead><tbody><tr><td>500 件</td><td>約 45–70 元</td></tr><tr><td>1,000 件</td><td>約 28–45 元</td></tr><tr><td>3,000 件</td><td>約 18–30 元</td></tr><tr><td>5,000 件</td><td>約 14–22 元</td></tr><tr><td>10,000 件以上</td><td>約 10–16 元</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>以上為概估區間，實際報價依紙材、尺寸、加工選項和廠商報價策略而異。500 件和 3000 件的單價差，通常在 40–60% 之間。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>變數 4：提把與結構</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>提把類型對成本影響不小：</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li><strong>扭繩提把</strong>：紙繩，成本最低，適合一般零售袋</li><li><strong>棉繩提把</strong>：質感較好，成本約增加 5–12 元/件</li><li><strong>緞帶提把</strong>：精品感強，成本約增加 8–20 元/件</li><li><strong>PP 打孔提把</strong>：強度高，適合重物，成本低但質感一般</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>另外，紙袋尺寸越大，用紙量越多，成本也越高。大型手提袋（如 40×30×15 公分）的用紙量是小袋（20×15×8 公分）的三倍以上。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>讓報價可以比較的關鍵：統一條件</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>同時向多家廠商詢價時，若沒有統一條件，收到的報價根本無法比較。建議詢價時明確指定：</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li>紙材種類與克重（例如：250g 白銅版紙）</li><li>尺寸（長 × 寬 × 高，含或不含提把的高度）</li><li>印刷方式（單色 / 四色 / 特別色）</li><li>表面加工（霧膜 / 亮膜 / 無）</li><li>提把類型（扭繩 / 棉繩 / 緞帶）</li><li>採購數量</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>條件統一後，不同廠商的報價差異通常縮小到 15–25%，這時才有意義去比較服務品質和交期。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>常見報價誤區</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>只看單件價，忽略版費</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>500 件的單件價看起來不高，但加上版費（通常 3,000–8,000 元，依色數而定），總費用可能比 1000 件還高。每次詢價都要確認「版費是否含在報價內，還是另計」。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>把「材質升級」當加工費</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>從一般銅版紙換成特種紙，不只是加工費增加，連印刷難度也可能提升，廠商報價時會有複合加成。若預算有限，先確認特種紙的必要性。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>急件溢價沒算進去</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>交期壓縮到標準時間的 50%，通常會有 15–30% 的急件加價。若你的活動日期是固定的，提早詢價才能避免額外費用。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>如何讓報價回覆更快更準</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>詢價時附上以下資訊，廠商通常能在 1–2 個工作天內給出可執行的報價：</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li>用途（門市零售 / 禮品 / 活動派發）</li><li>紙材偏好（若有）</li><li>尺寸規格</li><li>印刷設計稿或參考圖</li><li>數量區間</li><li>希望到貨日</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>資料越完整，廠商越能在第一次回覆就給出精確報價，而不是先請你補件再報。</p>
<!-- /wp:paragraph -->',
		],

		// ── 4. 紙袋包裝設計完整指南 (ID=55129) ──────────────────────────────
		[
			'id'       => 55129,
			'slug'     => 'paper-bag-design-complete-guide',
			'focus_kw' => '紙袋包裝設計,手提紙袋設計',
			'rm_title' => '紙袋包裝設計怎麼做？材質、提把、印刷與成本決策順序｜圓廣印刷',
			'rm_desc'  => '紙袋包裝設計的核心不是視覺，而是決策順序。本文從使用情境出發，依序說明材質、提把、印刷加工與成本控制，幫你少走回頭路。',
			'content'  => '<!-- wp:paragraph -->
<p>做紙袋包裝設計時，最容易出錯的不是視覺美感，而是決策順序。設計師把版面做得很漂亮，結果廠商說「這個材質印不了漸層」或「這個提把撐不住這個尺寸的重量」——這類問題在確認訂單前才發現，整個設計方向就要重來。</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>建議的決策順序：先確認使用情境與承重，再決定材質與提把，最後才進入印刷與加工設計。照這個順序做，通常能少走很多回頭路。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>步驟一：先定使用情境</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>紙袋設計的出發點是「這個袋子給誰用、在哪裡用、裝什麼」，不是「我想要什麼風格」。不同情境對紙袋的要求差異很大：</p>
<!-- /wp:paragraph -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>使用情境</th><th>設計重點</th><th>材質方向</th></tr></thead><tbody><tr><td>門市外帶 / 零售提袋</td><td>品牌辨識、手提舒適</td><td>白牛皮 / 銅版紙，耐重</td></tr><tr><td>精品禮品袋</td><td>質感觸感、開箱體驗</td><td>特種紙 / 藝術紙，加工</td></tr><tr><td>展會活動派發</td><td>成本控制、交期彈性</td><td>白牛皮紙，簡單加工</td></tr><tr><td>食品外帶袋</td><td>防油防水、合規</td><td>牛皮紙（含 PE 淋膜）</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:heading -->
<h2>步驟二：決定紙材與克重</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>紙材決定了袋子的觸感、視覺質感和印刷上限，也是成本最主要的變數：</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>白牛皮紙（120–150g）</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>最常見的手提袋用紙。顏色偏白、印刷效果穩定，適合品牌色彩鮮明的設計。成本低，耐重性中等。若要承裝超過 2kg 的商品，建議使用 150g 以上。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>本色牛皮紙（100–150g）</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>原木色，環保訴求強。印刷在本色紙上顏色會偏暖，設計稿需要先做色彩模擬，避免與屏幕呈現差異太大。適合茶葉、有機食品、手工品牌。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>銅版紙（200–300g）</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>色彩還原度高，印刷精緻度最好。適合精品零售、化妝品、高端禮品。成本比牛皮紙高 30–60%，但視覺效果明顯提升。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>特種紙</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>包含書寫紙、雪銅、仿布紋等各類紙材，每種觸感與視覺效果不同。需要提前與廠商確認可印刷性，部分特種紙表面對油墨吸收不佳，需要特殊印刷處理。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>步驟三：選提把類型</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>提把是影響承重、使用體驗和整體質感的關鍵零件：</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul><li><strong>扭繩提把</strong>：紙質，成本最低，最大承重約 3–5kg，適合一般零售袋</li><li><strong>棉繩提把</strong>：手感柔軟舒適，承重 5–8kg，適合中高端品牌</li><li><strong>緞帶提把</strong>：精品感最強，承重約 3–5kg，適合禮品袋</li><li><strong>打孔 PP 提把</strong>：強度最高（可承重 10kg 以上），適合量販、食品外帶</li><li><strong>無提把（提繩穿孔）</strong>：成本最低，適合文件袋、輕量活動袋</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>選提把時，除了質感還要考慮實際承重。若袋內商品超過 2kg，建議避免扭繩提把（容易在使用中斷裂）。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>步驟四：印刷設計原則</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>主視覺放哪一面</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>手提袋的兩個大面（正面、背面）是視覺設計的主要位置。底部和側面通常印刷資訊性內容（品牌名、網址、材質說明）。設計時先確認「主視覺面」，其他面的設計可以簡化。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>印刷工藝的常見限制</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li><strong>漸層色</strong>：平版印刷可做，但需要確認紙材對 CMYK 漸層的適配性</li><li><strong>細線條</strong>：低於 0.1mm 的線條可能印刷失真，建議在設計稿上標注</li><li><strong>特別色（Pantone）</strong>：若品牌色是標準 Pantone 色，需要指定色號，不能只用截圖</li><li><strong>白色印刷</strong>：在本色牛皮紙上印白色，需要使用白色油墨單獨印刷，費用額外計</li></ul>
<!-- /wp:list -->

<!-- wp:heading {"level":3} -->
<h3>表面加工的選擇</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li><strong>霧膜</strong>：啞光質感，高端感強，指紋不明顯</li><li><strong>亮膜</strong>：光滑反光，色彩更飽和，適合色彩鮮豔設計</li><li><strong>局部 UV</strong>：在特定區域（如 LOGO）做亮面強調，與霧底形成對比</li><li><strong>燙金 / 燙銀</strong>：精品感最高，但需要額外製版，費用以燙金面積計算</li></ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2>成本控制的三個有效方法</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3>1. 在標規尺寸內設計</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>標規尺寸指廠商現有刀模的尺寸。使用標規尺寸可省去開新刀模的費用（通常 3,000–8,000 元），且交期較短。若你的商品尺寸允許，先問廠商有哪些現成刀模，再在這些尺寸內設計。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>2. 減少加工工序</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>每增加一道加工（上膜、燙金、局部 UV），就增加一道費用。若預算有限，選一種加工做到位，比三種都做但都做得普通更有效果。</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3>3. 提高採購量</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>版費和刀模費是固定成本，數量越高分攤越少。若你有長期需求，一次採購 3,000–5,000 件，比每次 500 件來三次要便宜 30–50%。</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>設計稿交付前的確認清單</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul><li>尺寸是否含展開圖（長 × 寬 × 高 + 折疊邊）？</li><li>出血是否設定為 3mm？</li><li>色彩模式是否為 CMYK（不是 RGB）？</li><li>特別色是否標注 Pantone 色號？</li><li>字體是否已轉曲線（避免廠商字型不一致）？</li><li>提把孔位是否已在設計稿上標注？</li></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>設計稿符合以上條件，廠商通常可以直接進入打樣，不需要來回確認技術細節，整個流程能省下三到七天。</p>
<!-- /wp:paragraph -->',
		],

	];
}
