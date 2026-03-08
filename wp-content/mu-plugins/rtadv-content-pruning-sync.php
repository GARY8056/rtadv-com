<?php
/**
 * Plugin Name: RTADV Content Pruning Sync
 * Description: Syncs GA4 and Search Console metrics for posts and generates pruning candidates.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('RTADV_Content_Pruning_Sync')) {
	final class RTADV_Content_Pruning_Sync {
		const PARENT_SLUG = 'rtadv-pruning-tool-direct';
		const PAGE_SLUG = 'rtadv-content-pruning-sync';
		const CAPABILITY = 'manage_options';
		const OPTION_KEY = 'rtadv_content_pruning_google_settings';
		const SAVE_SETTINGS_ACTION = 'rtadv_content_pruning_save_settings';
		const SYNC_GSC_ACTION = 'rtadv_content_pruning_sync_gsc';
		const SYNC_GA4_ACTION = 'rtadv_content_pruning_sync_ga4';
		const SYNC_BACKLINKS_ACTION = 'rtadv_content_pruning_sync_backlinks';
		const IMPORT_BACKLINKS_ACTION = 'rtadv_content_pruning_import_backlinks';
		const GENERATE_ACTION = 'rtadv_content_pruning_generate_candidates';
		const RUN_PIPELINE_ACTION = 'rtadv_content_pruning_run_pipeline';
		const NONCE_ACTION = 'rtadv_content_pruning_sync';
		const CANDIDATE_BATCH_SIZE = 10;

		public static function boot() {
			add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
			add_action('admin_post_' . self::SAVE_SETTINGS_ACTION, array(__CLASS__, 'handle_save_settings'));
			add_action('admin_post_' . self::SYNC_GSC_ACTION, array(__CLASS__, 'handle_sync_gsc'));
			add_action('admin_post_' . self::SYNC_GA4_ACTION, array(__CLASS__, 'handle_sync_ga4'));
			add_action('admin_post_' . self::SYNC_BACKLINKS_ACTION, array(__CLASS__, 'handle_sync_backlinks'));
			add_action('admin_post_' . self::IMPORT_BACKLINKS_ACTION, array(__CLASS__, 'handle_import_backlinks'));
			add_action('admin_post_' . self::GENERATE_ACTION, array(__CLASS__, 'handle_generate_candidates'));
			add_action('admin_post_' . self::RUN_PIPELINE_ACTION, array(__CLASS__, 'handle_run_pipeline'));
		}

		public static function register_admin_page() {
			add_submenu_page(
				self::PARENT_SLUG,
				'RTADV 刪文同步',
				'同步',
				self::CAPABILITY,
				self::PAGE_SLUG,
				array(__CLASS__, 'render_admin_page')
			);
		}

		public static function render_admin_page() {
			if (! current_user_can(self::CAPABILITY)) {
				wp_die(esc_html__('You do not have permission to access this page.', 'default'));
			}

			$settings = self::get_settings();
			$summary = self::get_summary();
			$candidates = self::get_candidates();
			?>
				<div class="wrap">
					<h1>RTADV 刪文同步</h1>
					<p>這頁現在走簡化流程：`Step 1 填設定` → `Step 2 一鍵同步` → `Step 3 到判斷台確認`。</p>
					<?php self::render_notice(); ?>

				<div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:16px 0;">
					<?php self::render_stat_card('Published posts', (string) $summary['posts']); ?>
					<?php self::render_stat_card('GSC synced', (string) $summary['gsc_posts']); ?>
					<?php self::render_stat_card('GA4 synced', (string) $summary['ga4_posts']); ?>
					<?php self::render_stat_card('Backlinks synced', (string) $summary['backlink_posts']); ?>
					<?php self::render_stat_card('Candidates', (string) $summary['candidates']); ?>
				</div>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:16px;margin-bottom:16px;">
					<input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_SETTINGS_ACTION); ?>" />
					<?php wp_nonce_field(self::NONCE_ACTION); ?>
					<h2 style="margin-top:0;">Step 1. 填連線設定</h2>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="rtadv-service-account-json">Service Account JSON</label></th>
								<td>
									<textarea id="rtadv-service-account-json" name="service_account_json" rows="12" class="large-text code"><?php echo esc_textarea($settings['service_account_json']); ?></textarea>
									<p class="description">Use a Google service account JSON key. The service account must have `Viewer` on GA4 and access to the Search Console property.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-gsc-site-url">Search Console Property</label></th>
								<td>
									<input id="rtadv-gsc-site-url" type="text" name="gsc_site_url" class="regular-text" value="<?php echo esc_attr($settings['gsc_site_url']); ?>" placeholder="https://www.rtadv.com/ or sc-domain:rtadv.com" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-ga4-property-id">GA4 Property ID</label></th>
								<td>
									<input id="rtadv-ga4-property-id" type="text" name="ga4_property_id" class="regular-text" value="<?php echo esc_attr($settings['ga4_property_id']); ?>" placeholder="123456789" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-backlinks-provider">Backlinks Provider</label></th>
								<td>
									<select id="rtadv-backlinks-provider" name="backlinks_provider">
										<option value="">None</option>
										<option value="dataforseo" <?php selected($settings['backlinks_provider'], 'dataforseo'); ?>>DataForSEO Backlinks API</option>
										<option value="semrush" <?php selected($settings['backlinks_provider'], 'semrush'); ?>>Semrush API</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-dataforseo-login">DataForSEO Login</label></th>
								<td>
									<input id="rtadv-dataforseo-login" type="text" name="dataforseo_login" class="regular-text code" value="<?php echo esc_attr($settings['dataforseo_login']); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-dataforseo-password">DataForSEO Password</label></th>
								<td>
									<input id="rtadv-dataforseo-password" type="password" name="dataforseo_password" class="regular-text code" value="<?php echo esc_attr($settings['dataforseo_password']); ?>" />
									<p class="description">Recommended provider for page-level bulk backlink sync. Uses DataForSEO `bulk_pages_summary/live`.</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-semrush-api-key">Semrush API Key</label></th>
								<td>
									<input id="rtadv-semrush-api-key" type="text" name="semrush_api_key" class="regular-text code" value="<?php echo esc_attr($settings['semrush_api_key']); ?>" />
									<p class="description">Optional fallback. Semrush Backlinks overview may not match exact page scope the same way the UI does.</p>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button('Save Step 1 Settings'); ?>
				</form>

				<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;margin-bottom:16px;">
					<h2 style="margin-top:0;">Step 2. 一鍵執行</h2>
					<p>建議先按一次完整流程。只有在某個資料源失敗時，才用下方單獨按鈕重跑。</p>
					<p style="margin-bottom:0;">
						<a class="button button-primary" href="<?php echo esc_url(self::action_url(self::RUN_PIPELINE_ACTION)); ?>">Run Full Sync + Generate</a>
						<a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rtadv-content-pruning')); ?>">Open Pruning Planner</a>
					</p>
					<details style="margin-top:12px;">
						<summary>Advanced actions</summary>
						<p style="margin:12px 0 0;">
							<a class="button button-secondary" href="<?php echo esc_url(self::action_url(self::SYNC_GSC_ACTION)); ?>">Sync GSC (12 months)</a>
							<a class="button button-secondary" href="<?php echo esc_url(self::action_url(self::SYNC_GA4_ACTION)); ?>">Sync GA4 (12 months)</a>
							<a class="button button-secondary" href="<?php echo esc_url(self::action_url(self::SYNC_BACKLINKS_ACTION)); ?>">Sync Backlinks</a>
							<a class="button button-primary" href="<?php echo esc_url(self::action_url(self::GENERATE_ACTION)); ?>">Generate Candidates</a>
						</p>
					</details>
				</div>

				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="background:#fff;border:1px solid #ccd0d4;padding:16px;margin-bottom:16px;">
					<input type="hidden" name="action" value="<?php echo esc_attr(self::IMPORT_BACKLINKS_ACTION); ?>" />
					<?php wp_nonce_field(self::NONCE_ACTION); ?>
					<h2 style="margin-top:0;">Optional. Backlinks CSV Import</h2>
					<p>Fallback for bulk import. Accepted headers: <code>url</code>, <code>backlinks</code>, optional <code>refdomains</code>. Semicolon and comma CSV are both accepted.</p>
					<input type="file" name="backlinks_csv" accept=".csv,text/csv" />
					<?php submit_button('Import Backlinks CSV', 'secondary', 'submit', false, array('style' => 'margin-left:8px;')); ?>
				</form>

					<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;">
						<h2 style="margin-top:0;">Candidate Preview</h2>
						<?php if (empty($candidates)) : ?>
							<p>目前還沒有候選刪文名單。先完成 GSC / GA4 同步，再執行 Generate Candidates。</p>
						<?php else : ?>
							<table class="widefat striped">
								<thead>
									<tr>
										<th>Post</th>
										<th>Suggested</th>
										<th>Suggested Batch</th>
										<th>GA4</th>
										<th>GSC Clicks</th>
										<th>GSC Impr.</th>
										<th>Backlinks</th>
										<th>Reason</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($candidates as $candidate) : ?>
										<tr>
											<td>
												<strong><a href="<?php echo esc_url(get_edit_post_link($candidate['post_id'], '')); ?>"><?php echo esc_html($candidate['title']); ?></a></strong><br />
												<small><?php echo esc_html($candidate['date']); ?></small>
											</td>
											<td><?php echo esc_html(self::action_label($candidate['suggested_action'])); ?></td>
											<td><?php echo esc_html($candidate['suggested_batch']); ?></td>
											<td><?php echo esc_html((string) $candidate['ga4']); ?></td>
											<td><?php echo esc_html((string) $candidate['gsc_clicks']); ?></td>
											<td><?php echo esc_html((string) $candidate['gsc_impressions']); ?></td>
											<td><?php echo esc_html((string) $candidate['backlinks']); ?></td>
											<td><?php echo esc_html($candidate['reason']); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
			</div>
			<?php
		}

		public static function handle_save_settings() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			$settings = array(
				'service_account_json' => isset($_POST['service_account_json']) ? trim((string) wp_unslash($_POST['service_account_json'])) : '',
				'gsc_site_url' => isset($_POST['gsc_site_url']) ? sanitize_text_field(wp_unslash($_POST['gsc_site_url'])) : '',
				'ga4_property_id' => isset($_POST['ga4_property_id']) ? preg_replace('/[^0-9]/', '', (string) wp_unslash($_POST['ga4_property_id'])) : '',
				'backlinks_provider' => isset($_POST['backlinks_provider']) ? sanitize_key(wp_unslash($_POST['backlinks_provider'])) : '',
				'dataforseo_login' => isset($_POST['dataforseo_login']) ? sanitize_text_field(wp_unslash($_POST['dataforseo_login'])) : '',
				'dataforseo_password' => isset($_POST['dataforseo_password']) ? sanitize_text_field(wp_unslash($_POST['dataforseo_password'])) : '',
				'semrush_api_key' => isset($_POST['semrush_api_key']) ? sanitize_text_field(wp_unslash($_POST['semrush_api_key'])) : '',
			);

			update_option(self::OPTION_KEY, $settings, false);
			self::redirect_with_notice('settings_saved', 'Metrics settings saved.');
		}

		public static function handle_sync_gsc() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			$settings = self::get_settings();
			if ('' === $settings['gsc_site_url']) {
				self::redirect_with_notice('error', 'Search Console property is required.');
			}

			$token = self::get_access_token($settings);
			if (is_wp_error($token)) {
				self::redirect_with_notice('error', $token->get_error_message());
			}

			$result = self::sync_gsc_metrics($settings, $token);
			if (is_wp_error($result)) {
				self::redirect_with_notice('error', $result->get_error_message());
			}

			self::redirect_with_notice('gsc_synced', sprintf('GSC synced for %d post(s).', $result['updated']));
		}

		public static function handle_sync_ga4() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			$settings = self::get_settings();
			if ('' === $settings['ga4_property_id']) {
				self::redirect_with_notice('error', 'GA4 property ID is required.');
			}

			$token = self::get_access_token($settings);
			if (is_wp_error($token)) {
				self::redirect_with_notice('error', $token->get_error_message());
			}

			$result = self::sync_ga4_metrics($settings, $token);
			if (is_wp_error($result)) {
				self::redirect_with_notice('error', $result->get_error_message());
			}

			self::redirect_with_notice('ga4_synced', sprintf('GA4 synced for %d post(s).', $result['updated']));
		}

		public static function handle_sync_backlinks() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			$settings = self::get_settings();
			if ('' === $settings['backlinks_provider']) {
				self::redirect_with_notice('error', 'Choose a backlinks provider before syncing.');
			}

			if ('dataforseo' === $settings['backlinks_provider']) {
				if ('' === $settings['dataforseo_login'] || '' === $settings['dataforseo_password']) {
					self::redirect_with_notice('error', 'DataForSEO login and password are required for backlinks sync.');
				}
			}

			if ('semrush' === $settings['backlinks_provider'] && '' === $settings['semrush_api_key']) {
				self::redirect_with_notice('error', 'Semrush API key is required for backlinks sync.');
			}

			$result = self::sync_backlinks_metrics($settings);
			if (is_wp_error($result)) {
				self::redirect_with_notice('error', $result->get_error_message());
			}

			self::redirect_with_notice('backlinks_synced', sprintf('Backlinks synced for %d post(s).', $result['updated']));
		}

		public static function handle_import_backlinks() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			if (empty($_FILES['backlinks_csv']['tmp_name'])) {
				self::redirect_with_notice('error', 'Choose a backlinks CSV file to import.');
			}

			$result = self::import_backlinks_csv($_FILES['backlinks_csv']['tmp_name']);
			if (is_wp_error($result)) {
				self::redirect_with_notice('error', $result->get_error_message());
			}

			self::redirect_with_notice('backlinks_imported', sprintf('Imported backlinks for %d post(s).', $result['updated']));
		}

		public static function handle_run_pipeline() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			$settings = self::get_settings();
			$messages = array();

			if ('' === $settings['gsc_site_url']) {
				self::redirect_with_notice('error', 'Search Console property is required before running the full sync.');
			}

			if ('' === $settings['ga4_property_id']) {
				self::redirect_with_notice('error', 'GA4 property ID is required before running the full sync.');
			}

			$token = self::get_access_token($settings);
			if (is_wp_error($token)) {
				self::redirect_with_notice('error', $token->get_error_message());
			}

			$gsc = self::sync_gsc_metrics($settings, $token);
			if (is_wp_error($gsc)) {
				self::redirect_with_notice('error', 'Full sync stopped at GSC: ' . $gsc->get_error_message());
			}
			$messages[] = sprintf('GSC %d', $gsc['updated']);

			$ga4 = self::sync_ga4_metrics($settings, $token);
			if (is_wp_error($ga4)) {
				self::redirect_with_notice('error', 'Full sync stopped at GA4: ' . $ga4->get_error_message());
			}
			$messages[] = sprintf('GA4 %d', $ga4['updated']);

			if ('' !== $settings['backlinks_provider']) {
				$backlinks = self::sync_backlinks_metrics($settings);
				if (is_wp_error($backlinks)) {
					self::redirect_with_notice('error', 'Full sync stopped at backlinks: ' . $backlinks->get_error_message());
				}
				$messages[] = sprintf('Backlinks %d', $backlinks['updated']);
			} else {
				$messages[] = 'Backlinks skipped';
			}

			$candidates = self::generate_candidates();
			if (is_wp_error($candidates)) {
				self::redirect_with_notice('error', 'Full sync stopped at candidate generation: ' . $candidates->get_error_message());
			}
			$messages[] = sprintf('Candidates %d', $candidates['candidates']);

			self::redirect_with_notice('pipeline_complete', 'Full sync complete: ' . implode(' | ', $messages));
		}

		public static function handle_generate_candidates() {
			self::assert_access();
			check_admin_referer(self::NONCE_ACTION);

			$result = self::generate_candidates();
			if (is_wp_error($result)) {
				self::redirect_with_notice('error', $result->get_error_message());
			}

			self::redirect_with_notice('candidates_generated', sprintf('Generated %d candidate(s) across %d batch(es).', $result['candidates'], $result['batches']));
		}

		private static function sync_gsc_metrics($settings, $token) {
			$post_map = self::get_post_path_map();
			if (empty($post_map)) {
				return array('updated' => 0);
			}

			$start_date = gmdate('Y-m-d', strtotime('-365 days'));
			$end_date   = gmdate('Y-m-d', strtotime('-1 day'));
			$start_row  = 0;
			$row_limit  = 25000;
			$updated    = 0;
			$seen_ids   = array();

			do {
				$response = self::google_post_json(
					'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($settings['gsc_site_url']) . '/searchAnalytics/query',
					$token,
					array(
						'startDate' => $start_date,
						'endDate'   => $end_date,
						'dimensions' => array('page'),
						'type'      => 'web',
						'rowLimit'  => $row_limit,
						'startRow'  => $start_row,
					)
				);

				if (is_wp_error($response)) {
					return $response;
				}

				$rows = isset($response['rows']) && is_array($response['rows']) ? $response['rows'] : array();
				foreach ($rows as $row) {
					if (empty($row['keys'][0])) {
						continue;
					}

					$key = self::normalize_path_key($row['keys'][0]);
					if (! isset($post_map[$key])) {
						continue;
					}

					$post_id = $post_map[$key];
					update_post_meta($post_id, self::meta_key('gsc_clicks'), (int) round((float) ($row['clicks'] ?? 0)));
					update_post_meta($post_id, self::meta_key('gsc_impressions'), (int) round((float) ($row['impressions'] ?? 0)));
					$seen_ids[$post_id] = true;
				}

				$start_row += $row_limit;
			} while (count($rows) === $row_limit);

			foreach (array_keys($post_map) as $path_key) {
				$post_id = $post_map[$path_key];
				if (isset($seen_ids[$post_id])) {
					$updated++;
					continue;
				}

				update_post_meta($post_id, self::meta_key('gsc_clicks'), 0);
				update_post_meta($post_id, self::meta_key('gsc_impressions'), 0);
			}

			return array('updated' => count($seen_ids));
		}

		private static function sync_ga4_metrics($settings, $token) {
			$post_map = self::get_post_path_map();
			if (empty($post_map)) {
				return array('updated' => 0);
			}

			$offset  = 0;
			$limit   = 10000;
			$updated = 0;
			$seen_ids = array();

			do {
				$response = self::google_post_json(
					'https://analyticsdata.googleapis.com/v1beta/properties/' . rawurlencode($settings['ga4_property_id']) . ':runReport',
					$token,
					array(
						'dateRanges' => array(
							array(
								'startDate' => '365daysAgo',
								'endDate'   => 'yesterday',
							),
						),
						'dimensions' => array(
							array('name' => 'unifiedPagePathScreen'),
						),
						'metrics' => array(
							array('name' => 'screenPageViews'),
						),
						'limit'  => (string) $limit,
						'offset' => (string) $offset,
					)
				);

				if (is_wp_error($response)) {
					return $response;
				}

				$rows = isset($response['rows']) && is_array($response['rows']) ? $response['rows'] : array();
				foreach ($rows as $row) {
					$path = $row['dimensionValues'][0]['value'] ?? '';
					if ('' === $path) {
						continue;
					}

					$key = self::normalize_path_key($path);
					if (! isset($post_map[$key])) {
						continue;
					}

					$post_id = $post_map[$key];
					$value = isset($row['metricValues'][0]['value']) ? (int) round((float) $row['metricValues'][0]['value']) : 0;
					update_post_meta($post_id, self::meta_key('ga4_pageviews'), $value);
					$seen_ids[$post_id] = true;
				}

				$offset += $limit;
			} while (count($rows) === $limit);

			foreach (array_keys($post_map) as $path_key) {
				$post_id = $post_map[$path_key];
				if (isset($seen_ids[$post_id])) {
					$updated++;
					continue;
				}

				update_post_meta($post_id, self::meta_key('ga4_pageviews'), 0);
			}

			return array('updated' => count($seen_ids));
		}

		private static function sync_backlinks_metrics($settings) {
			if ('dataforseo' === $settings['backlinks_provider']) {
				return self::sync_backlinks_via_dataforseo($settings);
			}

			$post_ids = self::get_eligible_post_ids();

			$updated = 0;
			foreach ($post_ids as $post_id) {
				$response = wp_remote_get(
					add_query_arg(
						array(
							'key' => $settings['semrush_api_key'],
							'type' => 'backlinks_overview',
							'target' => get_permalink($post_id),
							'target_type' => 'url',
							'export_columns' => 'total,domains_num',
						),
						'https://api.semrush.com/analytics/v1/'
					),
					array(
						'timeout' => 30,
					)
				);

				if (is_wp_error($response)) {
					return $response;
				}

				$code = (int) wp_remote_retrieve_response_code($response);
				$body = trim((string) wp_remote_retrieve_body($response));
				if (200 !== $code || '' === $body) {
					return new WP_Error('rtadv_backlinks_sync_failed', 'Semrush backlinks sync failed.');
				}

				$parsed = self::parse_semrush_overview_csv($body);
				if (is_wp_error($parsed)) {
					return $parsed;
				}

				update_post_meta($post_id, self::meta_key('backlinks'), $parsed['backlinks']);
				update_post_meta($post_id, self::meta_key('refdomains'), $parsed['refdomains']);
				$updated++;
			}

			return array('updated' => $updated);
		}

		private static function sync_backlinks_via_dataforseo($settings) {
			$post_ids = self::get_eligible_post_ids();

			if (empty($post_ids)) {
				return array('updated' => 0);
			}

			$updated = 0;
			foreach (array_chunk($post_ids, 200) as $chunk) {
				$targets = array_map('get_permalink', $chunk);
				$response = wp_remote_post(
					'https://api.dataforseo.com/v3/backlinks/bulk_pages_summary/live',
					array(
						'timeout' => 45,
						'headers' => array(
							'Authorization' => 'Basic ' . base64_encode($settings['dataforseo_login'] . ':' . $settings['dataforseo_password']),
							'Content-Type' => 'application/json',
						),
						'body' => wp_json_encode(
							array(
								array(
									'targets' => array_values($targets),
								),
							)
						),
					)
				);

				if (is_wp_error($response)) {
					return $response;
				}

				$code = (int) wp_remote_retrieve_response_code($response);
				$data = json_decode((string) wp_remote_retrieve_body($response), true);
				if (200 !== $code || ! is_array($data)) {
					return new WP_Error('rtadv_dataforseo_backlinks_failed', 'DataForSEO backlinks sync failed.');
				}

				$items = $data['tasks'][0]['result'][0]['items'] ?? array();
				if (! is_array($items)) {
					return new WP_Error('rtadv_dataforseo_backlinks_invalid', 'Unexpected DataForSEO backlinks response.');
				}

				$post_map = array();
				foreach ($chunk as $post_id) {
					$post_map[self::normalize_path_key(get_permalink($post_id))] = $post_id;
				}
				$seen_post_ids = array();

				foreach ($items as $item) {
					$url = isset($item['url']) ? (string) $item['url'] : '';
					$key = self::normalize_path_key($url);
					if (! isset($post_map[$key])) {
						continue;
					}

					$post_id = $post_map[$key];
					update_post_meta($post_id, self::meta_key('backlinks'), max(0, (int) ($item['backlinks'] ?? 0)));
					update_post_meta($post_id, self::meta_key('refdomains'), max(0, (int) ($item['referring_domains'] ?? 0)));
					$seen_post_ids[$post_id] = true;
					$updated++;
				}

				foreach ($chunk as $post_id) {
					if (isset($seen_post_ids[$post_id])) {
						continue;
					}

					update_post_meta($post_id, self::meta_key('backlinks'), 0);
					update_post_meta($post_id, self::meta_key('refdomains'), 0);
				}
			}

			return array('updated' => $updated);
		}

		private static function import_backlinks_csv($tmp_file) {
			$contents = file_get_contents($tmp_file);
			if (false === $contents || '' === trim($contents)) {
				return new WP_Error('rtadv_backlinks_csv_empty', 'Backlinks CSV is empty.');
			}

			$delimiter = substr_count($contents, ';') > substr_count($contents, ',') ? ';' : ',';
			$handle = fopen($tmp_file, 'r');
			if (! $handle) {
				return new WP_Error('rtadv_backlinks_csv_open_failed', 'Could not open backlinks CSV.');
			}

			$headers = fgetcsv($handle, 0, $delimiter);
			if (! is_array($headers)) {
				fclose($handle);
				return new WP_Error('rtadv_backlinks_csv_headers_missing', 'Backlinks CSV headers are missing.');
			}

			$headers = array_map(
				static function ($header) {
					return sanitize_key(str_replace(array(' ', '-'), '_', strtolower((string) $header)));
				},
				$headers
			);

			$url_index = array_search('url', $headers, true);
			$backlinks_index = array_search('backlinks', $headers, true);
			$refdomains_index = array_search('refdomains', $headers, true);
			if (false === $refdomains_index) {
				$refdomains_index = array_search('referring_domains', $headers, true);
			}
			if (false === $url_index || false === $backlinks_index) {
				fclose($handle);
				return new WP_Error('rtadv_backlinks_csv_columns_missing', 'CSV must include url and backlinks columns.');
			}

			$post_map = self::get_post_path_map();
			$updated = 0;

			while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
				$url = isset($row[$url_index]) ? trim((string) $row[$url_index]) : '';
				if ('' === $url) {
					continue;
				}

				$key = self::normalize_path_key($url);
				if (! isset($post_map[$key])) {
					continue;
				}

				$post_id = $post_map[$key];
				$backlinks = isset($row[$backlinks_index]) ? max(0, (int) preg_replace('/[^0-9]/', '', (string) $row[$backlinks_index])) : 0;
				$refdomains = ($refdomains_index !== false && isset($row[$refdomains_index]))
					? max(0, (int) preg_replace('/[^0-9]/', '', (string) $row[$refdomains_index]))
					: 0;

				update_post_meta($post_id, self::meta_key('backlinks'), $backlinks);
				update_post_meta($post_id, self::meta_key('refdomains'), $refdomains);
				$updated++;
			}

			fclose($handle);
			return array('updated' => $updated);
		}

		private static function generate_candidates() {
			$post_ids = self::get_eligible_post_ids();

			$candidates = 0;
			$batches = 0;
			$grouped_candidates = array(
				'delete_301' => array(),
				'merge_301' => array(),
			);
			foreach ($post_ids as $post_id) {
				$plan = self::get_plan($post_id);
				$recommendation = self::get_recommendation($post_id, $plan);
				$is_candidate = in_array($recommendation['key'], array('delete_301', 'merge_301'), true);

				if ($is_candidate) {
					update_post_meta($post_id, self::meta_key('candidate'), '1');
					update_post_meta($post_id, self::meta_key('suggested_action'), $recommendation['key']);
					update_post_meta($post_id, self::meta_key('candidate_reason'), $recommendation['reason']);
					$grouped_candidates[$recommendation['key']][] = $post_id;
					$candidates++;
					continue;
				}

				delete_post_meta($post_id, self::meta_key('candidate'));
				delete_post_meta($post_id, self::meta_key('suggested_action'));
				delete_post_meta($post_id, self::meta_key('suggested_batch'));
				delete_post_meta($post_id, self::meta_key('candidate_reason'));
			}

			foreach ($grouped_candidates as $action_key => $candidate_ids) {
				foreach (array_chunk($candidate_ids, self::CANDIDATE_BATCH_SIZE) as $index => $chunk) {
					$batches++;
						$batch_label = sprintf('%s-%02d', self::batch_prefix($action_key), $index + 1);
						foreach ($chunk as $post_id) {
							update_post_meta($post_id, self::meta_key('suggested_batch'), $batch_label);
						}
					}
			}

			return array(
				'candidates' => $candidates,
				'batches' => $batches,
			);
		}

		private static function get_candidates() {
			$eligible_post_ids = self::get_eligible_post_ids();
			$post_ids = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'post__in'            => empty($eligible_post_ids) ? array(0) : $eligible_post_ids,
					'fields'              => 'ids',
					'posts_per_page'      => 50,
					'orderby'             => 'date',
					'order'               => 'ASC',
					'ignore_sticky_posts' => true,
					'meta_key'            => self::meta_key('candidate'),
					'meta_value'          => '1',
				)
			);

			$items = array();
			foreach ($post_ids as $post_id) {
					$items[] = array(
						'post_id' => $post_id,
						'title' => get_the_title($post_id),
						'date' => get_the_date('Y-m-d', $post_id),
						'suggested_action' => (string) get_post_meta($post_id, self::meta_key('suggested_action'), true),
						'suggested_batch' => (string) get_post_meta($post_id, self::meta_key('suggested_batch'), true),
						'ga4' => (int) get_post_meta($post_id, self::meta_key('ga4_pageviews'), true),
						'gsc_clicks' => (int) get_post_meta($post_id, self::meta_key('gsc_clicks'), true),
						'gsc_impressions' => (int) get_post_meta($post_id, self::meta_key('gsc_impressions'), true),
						'backlinks' => (int) get_post_meta($post_id, self::meta_key('backlinks'), true),
						'reason' => (string) get_post_meta($post_id, self::meta_key('candidate_reason'), true),
					);
				}

				usort(
					$items,
					static function ($left, $right) {
						$left_key = $left['suggested_action'] . '|' . $left['suggested_batch'] . '|' . $left['date'];
						$right_key = $right['suggested_action'] . '|' . $right['suggested_batch'] . '|' . $right['date'];
						return strcmp($left_key, $right_key);
					}
				);

				return $items;
			}

		private static function get_summary() {
			$post_ids = self::get_eligible_post_ids();
			$gsc_posts = 0;
			$ga4_posts = 0;
			$backlink_posts = 0;
			$candidates = 0;

			foreach ($post_ids as $post_id) {
				if ('' !== (string) get_post_meta($post_id, self::meta_key('gsc_clicks'), true)) {
					$gsc_posts++;
				}
				if ('' !== (string) get_post_meta($post_id, self::meta_key('ga4_pageviews'), true)) {
					$ga4_posts++;
				}
				if ('' !== (string) get_post_meta($post_id, self::meta_key('backlinks'), true)) {
					$backlink_posts++;
				}
				if ('1' === (string) get_post_meta($post_id, self::meta_key('candidate'), true)) {
					$candidates++;
				}
			}

			return array(
				'posts' => count($post_ids),
				'gsc_posts' => $gsc_posts,
				'ga4_posts' => $ga4_posts,
				'backlink_posts' => $backlink_posts,
				'candidates' => $candidates,
			);
		}

		private static function get_settings() {
			$settings = get_option(self::OPTION_KEY, array());

			return array(
				'service_account_json' => isset($settings['service_account_json']) ? (string) $settings['service_account_json'] : '',
				'gsc_site_url' => isset($settings['gsc_site_url']) ? (string) $settings['gsc_site_url'] : '',
				'ga4_property_id' => isset($settings['ga4_property_id']) ? (string) $settings['ga4_property_id'] : '',
				'backlinks_provider' => isset($settings['backlinks_provider']) ? (string) $settings['backlinks_provider'] : '',
				'dataforseo_login' => isset($settings['dataforseo_login']) ? (string) $settings['dataforseo_login'] : '',
				'dataforseo_password' => isset($settings['dataforseo_password']) ? (string) $settings['dataforseo_password'] : '',
				'semrush_api_key' => isset($settings['semrush_api_key']) ? (string) $settings['semrush_api_key'] : '',
			);
		}

		private static function get_access_token($settings) {
			$cache_key = 'rtadv_content_pruning_google_token';
			$cached = get_transient($cache_key);
			if (is_string($cached) && '' !== $cached) {
				return $cached;
			}

			if ('' === $settings['service_account_json']) {
				return new WP_Error('rtadv_missing_google_json', 'Service account JSON is required.');
			}

			$credentials = json_decode($settings['service_account_json'], true);
			if (! is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
				return new WP_Error('rtadv_invalid_google_json', 'Service account JSON is invalid.');
			}

			$issued_at = time();
			$claims = array(
				'iss' => $credentials['client_email'],
				'scope' => 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/analytics.readonly',
				'aud' => 'https://oauth2.googleapis.com/token',
				'iat' => $issued_at,
				'exp' => $issued_at + 3600,
			);

			$jwt = self::build_jwt($credentials['private_key'], $claims);
			if (is_wp_error($jwt)) {
				return $jwt;
			}

			$response = wp_remote_post(
				'https://oauth2.googleapis.com/token',
				array(
					'timeout' => 30,
					'body' => array(
						'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
						'assertion' => $jwt,
					),
				)
			);

			if (is_wp_error($response)) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$body = json_decode((string) wp_remote_retrieve_body($response), true);
			if (200 !== $code || empty($body['access_token'])) {
				return new WP_Error('rtadv_google_auth_failed', 'Google token request failed.');
			}

			set_transient($cache_key, $body['access_token'], 55 * MINUTE_IN_SECONDS);
			return $body['access_token'];
		}

		private static function build_jwt($private_key, $claims) {
			$header = self::base64url_encode(wp_json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
			$payload = self::base64url_encode(wp_json_encode($claims));
			$signing_input = $header . '.' . $payload;
			$signature = '';
			$result = openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);

			if (! $result) {
				return new WP_Error('rtadv_google_sign_failed', 'Unable to sign Google service account JWT.');
			}

			return $signing_input . '.' . self::base64url_encode($signature);
		}

		private static function google_post_json($url, $token, $body) {
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 45,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type' => 'application/json',
					),
					'body' => wp_json_encode($body),
				)
			);

			if (is_wp_error($response)) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code($response);
			$data = json_decode((string) wp_remote_retrieve_body($response), true);
			if ($code < 200 || $code >= 300) {
				$message = isset($data['error']['message']) ? $data['error']['message'] : 'Google API request failed.';
				return new WP_Error('rtadv_google_api_failed', $message);
			}

			return is_array($data) ? $data : array();
		}

		private static function get_post_path_map() {
			$post_ids = self::get_eligible_post_ids();

			$map = array();
			foreach ($post_ids as $post_id) {
				$map[self::normalize_path_key(get_permalink($post_id))] = $post_id;
			}

			return $map;
		}

		private static function get_eligible_post_ids() {
			$post_ids = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'fields'              => 'ids',
					'posts_per_page'      => -1,
					'orderby'             => 'date',
					'order'               => 'ASC',
					'ignore_sticky_posts' => true,
				)
			);

			return array_values(
				array_filter(
					array_map('absint', $post_ids),
					array(__CLASS__, 'is_non_divi_post')
				)
			);
		}

		private static function is_non_divi_post($post_id) {
			return ! self::is_divi_built_post($post_id);
		}

		private static function is_divi_built_post($post_id) {
			$builder_flag = strtolower(trim((string) get_post_meta($post_id, '_et_pb_use_builder', true)));
			if (in_array($builder_flag, array('1', 'on', 'yes', 'true'), true)) {
				return true;
			}

			$content = (string) get_post_field('post_content', $post_id);
			return false !== strpos($content, '[et_pb_');
		}

		private static function normalize_path_key($url_or_path) {
			$value = trim((string) $url_or_path);
			if ('' === $value) {
				return '/';
			}

			$parts = wp_parse_url($value);
			$path = isset($parts['path']) ? $parts['path'] : $value;
			$path = '/' . ltrim($path, '/');

			if ('/' !== $path) {
				$path = trailingslashit($path);
			}

			return $path;
		}

		private static function get_plan($post_id) {
				return array(
					'ga4_pageviews' => self::get_number_meta($post_id, 'ga4_pageviews'),
					'gsc_clicks' => self::get_number_meta($post_id, 'gsc_clicks'),
					'gsc_impressions' => self::get_number_meta($post_id, 'gsc_impressions'),
					'backlinks' => self::get_number_meta($post_id, 'backlinks'),
					'refdomains' => self::get_number_meta($post_id, 'refdomains'),
					'commercial_value' => (string) get_post_meta($post_id, self::meta_key('commercial_value'), true),
				);
		}

		private static function get_recommendation($post_id, $plan) {
			$post_age_days = floor((time() - (int) get_post_time('U', true, $post_id)) / DAY_IN_SECONDS);
			if ($post_age_days < 365) {
				return array(
					'key' => 'keep_update',
					'label' => 'Keep / Update',
					'reason' => 'Post is newer than 12 months.',
				);
			}

			if (null === $plan['ga4_pageviews'] || null === $plan['gsc_clicks'] || null === $plan['gsc_impressions']) {
				return array(
					'key' => 'review',
					'label' => 'Review',
					'reason' => 'Missing GA4 or GSC data.',
				);
			}

			if (($plan['backlinks'] ?? 0) > 0 || ($plan['refdomains'] ?? 0) > 0 || 'high' === $plan['commercial_value'] || $plan['gsc_clicks'] > 5 || $plan['gsc_impressions'] > 100) {
				return array(
					'key' => 'keep_update',
					'label' => 'Keep / Update',
					'reason' => 'Still has search demand, links, or commercial value.',
				);
			}

			if ($plan['ga4_pageviews'] < 30 && $plan['gsc_clicks'] < 1 && $plan['gsc_impressions'] < 30 && ($plan['backlinks'] ?? 0) < 1 && 'none' === $plan['commercial_value']) {
				return array(
					'key' => 'delete_301',
					'label' => 'Delete + 301',
					'reason' => 'Old post with near-zero traffic, search demand, and link value.',
				);
			}

			if ($plan['ga4_pageviews'] < 80 && $plan['gsc_clicks'] < 3 && $plan['gsc_impressions'] < 80 && ($plan['backlinks'] ?? 0) < 1) {
				return array(
					'key' => 'merge_301',
					'label' => 'Merge / Review',
					'reason' => 'Weak signals suggest consolidation rather than keeping as-is.',
				);
			}

			return array(
				'key' => 'review',
				'label' => 'Review',
				'reason' => 'Needs manual judgment.',
			);
		}

		private static function get_number_meta($post_id, $suffix) {
			$value = get_post_meta($post_id, self::meta_key($suffix), true);
			if ('' === $value || null === $value) {
				return null;
			}

			return max(0, (int) $value);
		}

		private static function meta_key($suffix) {
			return '_rtadv_prune_' . $suffix;
		}

		private static function render_notice() {
				$type = isset($_GET['notice_type']) ? sanitize_key(wp_unslash($_GET['notice_type'])) : '';
				$message = isset($_GET['notice_message']) ? sanitize_text_field(urldecode((string) wp_unslash($_GET['notice_message']))) : '';
			if ('' === $type || '' === $message) {
				return;
			}

			$class = 'notice-success';
			if ('error' === $type) {
				$class = 'notice-error';
			}
			?>
			<div class="notice <?php echo esc_attr($class); ?> is-dismissible">
				<p><?php echo esc_html($message); ?></p>
			</div>
			<?php
		}

		private static function render_stat_card($label, $value) {
			echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:16px;">';
			echo '<div style="font-size:12px;color:#50575e;text-transform:uppercase;letter-spacing:.04em;">' . esc_html($label) . '</div>';
			echo '<div style="font-size:28px;font-weight:700;margin-top:6px;">' . esc_html($value) . '</div>';
			echo '</div>';
		}

		private static function action_label($key) {
			$labels = array(
				'delete_301' => 'Delete + 301',
				'merge_301' => 'Merge + 301',
				'keep_update' => 'Keep / Update',
				'review' => 'Review',
			);

			return isset($labels[$key]) ? $labels[$key] : $key;
		}

		private static function batch_prefix($action_key) {
			$prefixes = array(
				'delete_301' => 'DEL',
				'merge_301' => 'MRG',
			);

			return isset($prefixes[$action_key]) ? $prefixes[$action_key] : 'REV';
		}

		private static function parse_semrush_overview_csv($csv) {
			$lines = preg_split('/\r\n|\r|\n/', trim($csv));
			if (! is_array($lines) || count($lines) < 2) {
				return new WP_Error('rtadv_semrush_csv_invalid', 'Unexpected Semrush backlinks response.');
			}

			$headers = str_getcsv($lines[0], ';');
			$values = str_getcsv($lines[1], ';');
			$headers = array_map('trim', $headers);
			$values = array_map('trim', $values);
			$row = array();
			foreach ($headers as $index => $header) {
				$row[$header] = isset($values[$index]) ? $values[$index] : '';
			}

			return array(
				'backlinks' => isset($row['total']) ? max(0, (int) preg_replace('/[^0-9]/', '', (string) $row['total'])) : 0,
				'refdomains' => isset($row['domains_num']) ? max(0, (int) preg_replace('/[^0-9]/', '', (string) $row['domains_num'])) : 0,
			);
		}

		private static function action_url($action) {
			return wp_nonce_url(
				add_query_arg(
					array(
						'action' => $action,
					),
					admin_url('admin-post.php')
				),
				self::NONCE_ACTION
			);
		}

		private static function base64url_encode($value) {
			return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
		}

		private static function redirect_with_notice($type, $message) {
			$url = add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'notice_type' => $type,
					'notice_message' => $message,
				),
				admin_url('admin.php')
			);

			wp_safe_redirect($url);
			exit;
		}

		private static function assert_access() {
			if (! current_user_can(self::CAPABILITY)) {
				wp_die(esc_html__('You do not have permission to perform this action.', 'default'));
			}
		}
	}

	RTADV_Content_Pruning_Sync::boot();
}
