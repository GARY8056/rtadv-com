<?php
/**
 * Plugin Name: RTADV SEO Noindex Thin Content (Run Once)
 * Description: Sets noindex on thin gallery/inspiration posts. SELF-DELETES after running.
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'init', 'rtadv_seo_noindex_run', 1 );

function rtadv_seo_noindex_run() {
    static $ran = false;
    if ( $ran ) return;
    $ran = true;

    // Post IDs to set noindex (thin gallery/inspiration posts, ~120-180 words)
    $noindex_ids = [
        26596, // 3種可食用且包裝精美的大麻包裝
        26584, // 4種明亮的雞蛋包裝設計
        26893, // 4種幽默可愛的霓虹包裝
        26080, // 讓你會心一笑的紙袋設計（下）
        25695, // 6種色彩大膽包裝有趣的茶葉包裝
        24977, // 精美的防曬包裝(下)
        25645, // 5種鮮明有趣的字體包裝設計
        25178, // 6種獨特的護膚品包裝
        24862, // 10個創意披薩包裝設計(下)
        26067, // 讓你會心一笑的紙袋設計（上）
        25134, // 15個互動包裝設計的巧妙例子（下）
        21757, // 在2021年設計產品包裝不可不知的3個最新潮流
        25863, // 回顧2019最受注目包裝
        25838, // 回顧2018最受注目包裝
        21018, // 簡單卻吸引人的名片設計
        21576, // 讓大家為之瘋狂的千禧粉紅色 運用實例與介紹
        20282, // 關於春季的調色板
        21732, // 那些讓開箱變得更加令人期待的精緻包裝
        24865, // 這些好看的零食包裝都看餓了!
        24986, // 精美的防曬包裝(上)
    ];

    $log = [];
    foreach ( $noindex_ids as $pid ) {
        if ( ! get_post( $pid ) ) {
            $log[] = "SKIP pid=$pid (not found)";
            continue;
        }
        // Rank Math robots: set noindex, keep follow
        update_post_meta( $pid, 'rank_math_robots', [ 'noindex' ] );
        // Also ensure advanced robots doesn't override
        $log[] = "NOINDEX pid=$pid";
    }

    $log_file = WP_CONTENT_DIR . '/rtadv-seo-noindex.log';
    file_put_contents( $log_file, implode("\n", $log) . "\n" );

    // Self-delete
    @unlink( __FILE__ );
}
