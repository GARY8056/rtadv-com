<?php

return array(
	'settingsSource' => '/Users/gary/Documents/GitHub/seo/src/lib/seo-auto/config.ts + article/image workflow defaults',
	'upstreamBaseUrl' => 'https://www.rtadv.net',
	'upstreamEndpointPath' => '/api/blog-automation/draft',
	'requestTimeout' => 90,
	'defaultPayload' => array(
		'audience' => '台灣品牌主、行銷人員、採購與設計窗口',
		'searchIntent' => 'informational',
		'includeRenderedImages' => true,
		'internalLinks' => array(
			array(
				'label' => '盒型總覽',
				'href' => '/structural-design/box-styles',
			),
			array(
				'label' => '報價與門檻',
				'href' => '/pricing-thresholds',
			),
			array(
				'label' => '聯絡詢價',
				'href' => '/contact',
			),
		),
	),
	'seoAutoDefaults' => array(
		'dailyArticleCount' => 20,
		'maxDailyPublish' => 20,
		'headQuota' => 4,
		'bodyQuota' => 8,
		'longtailQuota' => 8,
		'selectionMode' => '穩健成長',
		'notebookLmEnabled' => true,
		'refreshEnabled' => true,
		'refreshFrequency' => 'weekly',
		'refreshBatchSize' => 5,
		'refreshMinAgeDays' => 60,
		'refreshCooldownDays' => 30,
	),
);
