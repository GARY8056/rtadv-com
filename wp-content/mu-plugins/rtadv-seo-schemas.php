<?php
/**
 * Plugin Name: RTADV SEO Schemas
 * Description: LocalBusiness + FAQPage + Article schemas
 */

// LocalBusiness schema on front page
add_action('wp_head', function() {
  if (!is_front_page()) return;
  $schema = [
    '@context' => 'https://schema.org',
    '@type' => 'PrintShop',
    'name' => '圓廣創意印刷',
    'alternateName' => 'RTADV Creative Printing',
    'url' => 'https://www.rtadv.com',
    'logo' => 'https://www.rtadv.com/wp-content/uploads/2024/01/rtadv-logo.png',
    'image' => 'https://www.rtadv.com/wp-content/uploads/2024/01/rtadv-og-image.jpg',
    'description' => '專為台北、新北品牌打造的客製化包裝設計與彩盒印刷服務。提供紙盒、紙袋、紙罐、貼紙等一站式包裝印刷解決方案。',
    'telephone' => '+886-2-2245-5586',
    'email' => 'service@rtadv.com',
    'address' => [
      '@type' => 'PostalAddress',
      'streetAddress' => '建康路276號5樓',
      'addressLocality' => '中和區',
      'addressRegion' => '新北市',
      'postalCode' => '235',
      'addressCountry' => 'TW',
    ],
    'geo' => [
      '@type' => 'GeoCoordinates',
      'latitude' => 24.9914,
      'longitude' => 121.4934,
    ],
    'openingHoursSpecification' => [
      '@type' => 'OpeningHoursSpecification',
      'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'],
      'opens' => '09:00',
      'closes' => '18:00',
    ],
    'priceRange' => '$$',
    'areaServed' => [
      ['@type' => 'City', 'name' => '台北市'],
      ['@type' => 'City', 'name' => '新北市'],
      ['@type' => 'AdministrativeArea', 'name' => '台灣'],
    ],
    'sameAs' => [
      'https://www.facebook.com/rtadv',
      'https://www.instagram.com/rtadv_printing/',
      'https://www.youtube.com/@rtadv',
      'https://www.linkedin.com/company/rtadv/',
      'https://line.me/R/ti/p/@rtadv',
    ],
    'hasOfferCatalog' => [
      '@type' => 'OfferCatalog',
      'name' => '包裝印刷服務',
      'itemListElement' => [
        ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => '客製化彩盒印刷']],
        ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => '包裝設計']],
        ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => '紙袋印刷']],
        ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => '紙管紙罐包裝']],
        ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => '貼紙標籤印刷']],
        ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => '精裝禮盒']],
      ],
    ],
  ];
  echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 5);

// FAQPage schema on FAQ pages (supports Divi Builder content)
add_action('wp_head', function() {
  if (!is_page([108, 53986, 'printing-faq', 'faq'])) return;
  global $post;
  if (!$post) return;

  $raw = $post->post_content;
  // Remove all shortcode tags [...] but keep their inner text content
  $text = preg_replace('/\[[^\]]*\]/', ' ', $raw);
  $text = strip_tags($text);
  $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
  // Collapse all whitespace to single spaces
  $text = preg_replace('/\s+/u', ' ', $text);

  // Extract Q&A pairs matching "Q1：question" pattern
  $faq_items = [];
  // Split by Q markers to get segments
  preg_match_all('/Q(\d+)[：:]\s*(.+?)(?=Q\d+[：:]|$)/su', $text, $matches, PREG_SET_ORDER);

  foreach ($matches as $m) {
    $full = trim($m[2]);
    if (mb_strlen($full) < 15) continue;
    // First sentence-ish chunk is the question (up to ？ or first 80 chars)
    $q_end = mb_strpos($full, '？');
    if ($q_end !== false && $q_end < 80) {
      $q = mb_substr($full, 0, $q_end + 1);
      $answer = trim(mb_substr($full, $q_end + 1));
    } else {
      // No ？ found, use a reasonable chunk
      $q = mb_substr($full, 0, 80);
      $answer = trim(mb_substr($full, 80));
    }
    $q = trim($q);
    $answer = trim(preg_replace('/\s+/u', ' ', $answer));
    if (mb_strlen($q) < 5 || mb_strlen($answer) < 10) continue;
    $answer = mb_substr($answer, 0, 500);
    $faq_items[] = [
      '@type' => 'Question',
      'name' => $q,
      'acceptedAnswer' => [
        '@type' => 'Answer',
        'text' => $answer,
      ],
    ];
  }
  if (empty($faq_items)) return;
  $schema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $faq_items,
  ];
  echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}, 5);

// Fix homepage Open Graph: use static image, proper description
add_filter('rank_math/opengraph/facebook/og_image', function($image) {
  if (is_front_page()) {
    return 'https://www.rtadv.com/wp-content/uploads/2024/01/rtadv-og-image.jpg';
  }
  return $image;
});
add_filter('rank_math/opengraph/facebook/og_image_secure_url', function($image) {
  if (is_front_page()) {
    return 'https://www.rtadv.com/wp-content/uploads/2024/01/rtadv-og-image.jpg';
  }
  return $image;
});
add_filter('rank_math/opengraph/facebook/og_image_alt', function($alt) {
  if (is_front_page()) {
    return '圓廣創意印刷 — 客製化包裝設計與彩盒印刷服務';
  }
  return $alt;
});
add_filter('rank_math/opengraph/facebook/og_description', function($desc) {
  if (is_front_page()) {
    return '專為台北、新北品牌打造的客製化包裝設計與彩盒印刷服務。提供紙盒、紙袋、紙罐、貼紙等一站式包裝印刷解決方案。';
  }
  return $desc;
});
add_filter('rank_math/opengraph/twitter/twitter_image', function($image) {
  if (is_front_page()) {
    return 'https://www.rtadv.com/wp-content/uploads/2024/01/rtadv-og-image.jpg';
  }
  return $image;
});
add_filter('rank_math/opengraph/twitter/twitter_description', function($desc) {
  if (is_front_page()) {
    return '專為台北、新北品牌打造的客製化包裝設計與彩盒印刷服務。提供紙盒、紙袋、紙罐、貼紙等一站式包裝印刷解決方案。';
  }
  return $desc;
});

// Ensure lazy loading on all images
add_filter('wp_get_attachment_image_attributes', function($attr) {
  if (!isset($attr['loading'])) {
    $attr['loading'] = 'lazy';
  }
  return $attr;
});

// Add lazy loading to content images
add_filter('the_content', function($content) {
  return preg_replace('/<img(?![^>]*loading=)([^>]*?)>/i', '<img loading="lazy"$1>', $content);
}, 99);
