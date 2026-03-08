<?php
/**
 * Plugin Name: RTADV Content Pruning Planner
 * Description: Adds an admin workflow to review legacy blog posts, record pruning decisions, and export a pruning plan.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('RTADV_Content_Pruning_Planner')) {
	final class RTADV_Content_Pruning_Planner {
		const PAGE_SLUG = 'rtadv-content-pruning';
		const PARENT_SLUG = 'rtadv-pruning-tool-direct';
		const CAPABILITY = 'manage_options';
		const SAVE_ACTION = 'rtadv_save_content_pruning';
		const EXPORT_ACTION = 'rtadv_export_content_pruning';
		const NONCE_ACTION = 'rtadv_content_pruning';
		const PER_PAGE = 25;

		private static $action_options = array(
			''           => 'Unassigned',
			'review'     => 'Review',
			'keep_update' => 'Keep and Update',
			'noindex'    => 'Keep and Noindex',
			'merge_301'  => 'Merge and 301',
			'delete_301' => 'Delete and 301',
		);

		private static $commercial_value_options = array(
			''     => 'Unknown',
			'none' => 'None',
			'low'  => 'Low',
			'high' => 'High',
		);

		public static function boot() {
			add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
			add_action('admin_post_' . self::SAVE_ACTION, array(__CLASS__, 'handle_save'));
			add_action('admin_post_' . self::EXPORT_ACTION, array(__CLASS__, 'handle_export'));
		}

		public static function register_admin_page() {
			add_submenu_page(
				self::PARENT_SLUG,
				'RTADV 刪文判斷台',
				'判斷台',
				self::CAPABILITY,
				self::PAGE_SLUG,
				array(__CLASS__, 'render_admin_page')
			);
		}

		public static function render_admin_page() {
			if (! current_user_can(self::CAPABILITY)) {
				wp_die(esc_html__('You do not have permission to access this page.', 'default'));
			}

			$filters = self::get_filters();
			$query   = self::get_posts_query($filters);
			$has_posts = $query->have_posts();
			$export_url = wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::EXPORT_ACTION,
					),
					admin_url('admin-post.php')
				),
				self::NONCE_ACTION
			);
			?>
				<div class="wrap">
					<h1>RTADV 刪文判斷台</h1>
					<p>Step 3. 檢查候選結果，只處理一般文章 <code>post</code>。如果還沒同步資料，先到 <a href="<?php echo esc_url(admin_url('admin.php?page=rtadv-content-pruning-sync')); ?>">RTADV 刪文同步</a>。</p>
				<?php self::render_notice(); ?>
				<?php self::render_filter_form($filters); ?>

					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>" />
						<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr(self::current_page_url()); ?>" />
						<?php wp_nonce_field(self::NONCE_ACTION); ?>
						<p style="margin:12px 0;">
							<button type="button" class="button" id="rtadv-apply-suggestions">Apply suggested action/batch to empty fields</button>
						</p>

						<table class="widefat striped" style="margin-top:16px;">
						<thead>
							<tr>
								<th style="width:20%;">文章</th>
								<th style="width:10%;">三穩</th>
								<th style="width:10%;">建議</th>
								<th style="width:7%;">GA4 12m</th>
								<th style="width:7%;">GSC Clicks</th>
								<th style="width:7%;">GSC Impr.</th>
								<th style="width:7%;">Backlinks</th>
								<th style="width:8%;">商業價值</th>
								<th style="width:10%;">處置</th>
								<th style="width:14%;">301 / 備註</th>
							</tr>
						</thead>
						<tbody>
							<?php if (! $has_posts) : ?>
								<tr>
									<td colspan="10">目前沒有符合條件的文章。</td>
								</tr>
							<?php else : ?>
								<?php while ($query->have_posts()) : $query->the_post(); ?>
									<?php
										$post_id = get_the_ID();
										$plan    = self::get_plan($post_id);
										$checks  = self::get_three_stability($post_id, $plan);
										$recommendation = self::get_recommendation($post_id, $plan);
										$suggested_action = (string) get_post_meta($post_id, self::meta_key('suggested_action'), true);
										?>
										<tr>
										<td>
											<input type="hidden" name="post_ids[]" value="<?php echo esc_attr((string) $post_id); ?>" />
											<strong><a href="<?php echo esc_url(get_edit_post_link($post_id, '')); ?>"><?php echo esc_html(get_the_title()); ?></a></strong><br />
											<small>
												<?php echo esc_html(get_the_date('Y-m-d', $post_id)); ?>
												<?php
												$categories = get_the_category($post_id);
												if (! empty($categories)) {
													echo ' | ' . esc_html(implode(', ', wp_list_pluck($categories, 'name')));
												}
												?>
											</small><br />
											<small><a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank" rel="noopener noreferrer">View</a></small>
										</td>
										<td><?php self::render_stability_badges($checks); ?></td>
											<td><?php self::render_recommendation_badge($recommendation); ?></td>
										<td><input type="number" min="0" name="ga4_pageviews[<?php echo esc_attr((string) $post_id); ?>]" value="<?php echo esc_attr(self::stringify_number($plan['ga4_pageviews'])); ?>" style="width:100%;" /></td>
										<td><input type="number" min="0" name="gsc_clicks[<?php echo esc_attr((string) $post_id); ?>]" value="<?php echo esc_attr(self::stringify_number($plan['gsc_clicks'])); ?>" style="width:100%;" /></td>
										<td><input type="number" min="0" name="gsc_impressions[<?php echo esc_attr((string) $post_id); ?>]" value="<?php echo esc_attr(self::stringify_number($plan['gsc_impressions'])); ?>" style="width:100%;" /></td>
										<td><input type="number" min="0" name="backlinks[<?php echo esc_attr((string) $post_id); ?>]" value="<?php echo esc_attr(self::stringify_number($plan['backlinks'])); ?>" style="width:100%;" /></td>
										<td>
											<select name="commercial_value[<?php echo esc_attr((string) $post_id); ?>]" style="width:100%;">
												<?php foreach (self::$commercial_value_options as $value => $label) : ?>
													<option value="<?php echo esc_attr($value); ?>" <?php selected($plan['commercial_value'], $value); ?>><?php echo esc_html($label); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
											<td>
												<select name="prune_action[<?php echo esc_attr((string) $post_id); ?>]" data-suggested-action="<?php echo esc_attr($suggested_action); ?>" style="width:100%;margin-bottom:8px;">
													<?php foreach (self::$action_options as $value => $label) : ?>
														<option value="<?php echo esc_attr($value); ?>" <?php selected($plan['prune_action'], $value); ?>><?php echo esc_html($label); ?></option>
													<?php endforeach; ?>
												</select>
												<input type="text" name="batch_label[<?php echo esc_attr((string) $post_id); ?>]" data-suggested-batch="<?php echo esc_attr($plan['suggested_batch']); ?>" value="<?php echo esc_attr($plan['batch_label']); ?>" placeholder="Batch" style="width:100%;" />
												<?php if ('' !== $plan['suggested_batch']) : ?>
													<small style="display:block;margin-top:6px;color:#50575e;">Suggested: <?php echo esc_html(self::action_label($suggested_action)); ?> / <?php echo esc_html($plan['suggested_batch']); ?></small>
												<?php endif; ?>
											</td>
										<td>
											<input type="text" name="redirect_target[<?php echo esc_attr((string) $post_id); ?>]" value="<?php echo esc_attr($plan['redirect_target']); ?>" placeholder="/target-url/" style="width:100%;margin-bottom:8px;" />
											<textarea name="review_notes[<?php echo esc_attr((string) $post_id); ?>]" rows="3" style="width:100%;" placeholder="Notes"><?php echo esc_textarea($plan['review_notes']); ?></textarea>
										</td>
									</tr>
								<?php endwhile; ?>
							<?php endif; ?>
						</tbody>
					</table>

						<?php if ($has_posts) : ?>
						<p style="margin-top:16px;">
							<button type="submit" class="button button-primary">Save visible rows</button>
							<a class="button" href="<?php echo esc_url($export_url); ?>">Export CSV</a>
						</p>
					<?php endif; ?>
					</form>
					<script>
						(function() {
							const button = document.getElementById('rtadv-apply-suggestions');
							if (!button) return;

							button.addEventListener('click', function() {
								document.querySelectorAll('select[data-suggested-action]').forEach(function(select) {
									if (!select.value && select.dataset.suggestedAction) {
										select.value = select.dataset.suggestedAction;
									}
								});

								document.querySelectorAll('input[data-suggested-batch]').forEach(function(input) {
									if (!input.value && input.dataset.suggestedBatch) {
										input.value = input.dataset.suggestedBatch;
									}
								});
							});
						})();
					</script>

					<?php self::render_pagination($query, $filters); ?>

					<div style="margin-top:24px;padding:12px 16px;background:#fff;border:1px solid #ccd0d4;">
						<strong>Quick use</strong>
						<p style="margin:8px 0 0;">
							1. 按 `Apply suggested action/batch to empty fields`。<br />
							2. 補上 `redirect_target` 和備註。<br />
							3. 只先處理第一批 `DEL-*` 或 `MRG-*`。
						</p>
					</div>
			</div>
			<?php
			wp_reset_postdata();
		}

		public static function handle_save() {
			if (! current_user_can(self::CAPABILITY)) {
				wp_die(esc_html__('You do not have permission to perform this action.', 'default'));
			}

			check_admin_referer(self::NONCE_ACTION);

			$post_ids = isset($_POST['post_ids']) ? array_unique(array_map('absint', (array) wp_unslash($_POST['post_ids']))) : array();
			$updated  = 0;

			foreach ($post_ids as $post_id) {
				if ('post' !== get_post_type($post_id) || self::is_divi_built_post($post_id)) {
					continue;
				}

				self::save_field($post_id, 'ga4_pageviews', self::request_number('ga4_pageviews', $post_id));
				self::save_field($post_id, 'gsc_clicks', self::request_number('gsc_clicks', $post_id));
				self::save_field($post_id, 'gsc_impressions', self::request_number('gsc_impressions', $post_id));
				self::save_field($post_id, 'backlinks', self::request_number('backlinks', $post_id));
				self::save_field($post_id, 'commercial_value', self::request_choice('commercial_value', $post_id, array_keys(self::$commercial_value_options)));
				self::save_field($post_id, 'prune_action', self::request_choice('prune_action', $post_id, array_keys(self::$action_options)));
				self::save_field($post_id, 'batch_label', self::request_text('batch_label', $post_id));
				self::save_field($post_id, 'redirect_target', self::request_text('redirect_target', $post_id));
				self::save_field($post_id, 'review_notes', self::request_textarea('review_notes', $post_id));
				self::save_field($post_id, 'reviewed_at', current_time('mysql'));
				$updated++;
			}

			$redirect_url = wp_get_referer();
			if (! $redirect_url) {
				$redirect_url = add_query_arg(
					array(
						'page' => self::PAGE_SLUG,
					),
					admin_url('admin.php')
				);
			}
			$redirect_url = add_query_arg(
				array(
					'updated' => $updated,
				),
				$redirect_url
			);

			wp_safe_redirect($redirect_url);
			exit;
		}

		public static function handle_export() {
			if (! current_user_can(self::CAPABILITY)) {
				wp_die(esc_html__('You do not have permission to perform this action.', 'default'));
			}

			check_admin_referer(self::NONCE_ACTION);

			$posts = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'post__in'            => self::get_eligible_post_ids(),
					'posts_per_page'      => -1,
					'orderby'             => 'date',
					'order'               => 'ASC',
					'ignore_sticky_posts' => true,
				)
			);

			nocache_headers();
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename=rtadv-content-pruning-plan.csv');

			$output = fopen('php://output', 'w');
			fputcsv(
				$output,
				array(
					'post_id',
					'title',
					'url',
					'publish_date',
					'ga4_pageviews_12m',
					'gsc_clicks_12m',
					'gsc_impressions_12m',
					'backlinks',
					'commercial_value',
					'three_stability',
					'recommendation',
					'prune_action',
					'redirect_target',
						'batch_label',
						'suggested_batch',
						'review_notes',
					'reviewed_at',
				)
			);

			foreach ($posts as $post) {
				$plan = self::get_plan($post->ID);
				$checks = self::get_three_stability($post->ID, $plan);
				$recommendation = self::get_recommendation($post->ID, $plan);

				fputcsv(
					$output,
					array(
						$post->ID,
						$post->post_title,
						get_permalink($post),
						get_the_date('Y-m-d', $post),
						self::stringify_number($plan['ga4_pageviews']),
						self::stringify_number($plan['gsc_clicks']),
						self::stringify_number($plan['gsc_impressions']),
						self::stringify_number($plan['backlinks']),
						$plan['commercial_value'],
						implode(' | ', array_keys(array_filter($checks))),
						$recommendation['label'],
						$plan['prune_action'],
							$plan['redirect_target'],
							$plan['batch_label'],
							$plan['suggested_batch'],
							$plan['review_notes'],
						$plan['reviewed_at'],
					)
				);
			}

			fclose($output);
			exit;
		}

		private static function get_filters() {
			return array(
				'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
				'cat'    => isset($_GET['cat']) ? absint(wp_unslash($_GET['cat'])) : 0,
				'action' => isset($_GET['prune_action']) ? sanitize_key(wp_unslash($_GET['prune_action'])) : '',
				'paged'  => max(1, isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1),
			);
		}

		private static function get_posts_query($filters) {
			$eligible_post_ids = self::get_eligible_post_ids();
			$args = array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'post__in'            => empty($eligible_post_ids) ? array(0) : $eligible_post_ids,
				'posts_per_page'      => self::PER_PAGE,
				'paged'               => $filters['paged'],
				'orderby'             => 'date',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			);

			if ('' !== $filters['search']) {
				$args['s'] = $filters['search'];
			}

			if ($filters['cat'] > 0) {
				$args['cat'] = $filters['cat'];
			}

			if ('' !== $filters['action']) {
				$args['meta_query'] = array(
					array(
						'key'   => self::meta_key('prune_action'),
						'value' => $filters['action'],
					),
				);
			}

			return new WP_Query($args);
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

		private static function render_filter_form($filters) {
			$categories = get_categories(array('hide_empty' => false));
			?>
			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>" />
				<input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Search title" />
				<select name="cat">
					<option value="0">All categories</option>
					<?php foreach ($categories as $category) : ?>
						<option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($filters['cat'], $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="prune_action">
					<?php foreach (self::$action_options as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected($filters['action'], $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button">Filter</button>
			</form>
			<?php
		}

		private static function render_notice() {
			$updated = isset($_GET['updated']) ? absint(wp_unslash($_GET['updated'])) : 0;
			if ($updated < 1) {
				return;
			}
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html(sprintf('Saved %d row(s).', $updated)); ?></p>
			</div>
			<?php
		}

		private static function render_pagination($query, $filters) {
			if ($query->max_num_pages < 2) {
				return;
			}

			$base_args = array(
				'page'         => self::PAGE_SLUG,
				's'            => $filters['search'],
				'cat'          => $filters['cat'],
				'prune_action' => $filters['action'],
				'paged'        => '%#%',
			);

			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg($base_args, admin_url('admin.php')),
						'format'    => '',
						'current'   => $filters['paged'],
						'total'     => $query->max_num_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			echo '</div></div>';
		}

		private static function get_plan($post_id) {
			return array(
				'ga4_pageviews'   => self::get_number_meta($post_id, 'ga4_pageviews'),
				'gsc_clicks'      => self::get_number_meta($post_id, 'gsc_clicks'),
				'gsc_impressions' => self::get_number_meta($post_id, 'gsc_impressions'),
				'backlinks'       => self::get_number_meta($post_id, 'backlinks'),
				'commercial_value' => (string) get_post_meta($post_id, self::meta_key('commercial_value'), true),
				'prune_action'    => (string) get_post_meta($post_id, self::meta_key('prune_action'), true),
					'redirect_target' => (string) get_post_meta($post_id, self::meta_key('redirect_target'), true),
					'batch_label'     => (string) get_post_meta($post_id, self::meta_key('batch_label'), true),
					'suggested_batch' => (string) get_post_meta($post_id, self::meta_key('suggested_batch'), true),
					'review_notes'    => (string) get_post_meta($post_id, self::meta_key('review_notes'), true),
				'reviewed_at'     => (string) get_post_meta($post_id, self::meta_key('reviewed_at'), true),
			);
		}

		private static function get_three_stability($post_id, $plan) {
			$post_age_days = floor((time() - (int) get_post_time('U', true, $post_id)) / DAY_IN_SECONDS);
			$metrics_ready = null !== $plan['ga4_pageviews'] && null !== $plan['gsc_clicks'] && null !== $plan['gsc_impressions'] && null !== $plan['backlinks'];
			$data_stable   = $metrics_ready && $post_age_days >= 365;
			$value_stable  = $data_stable && $plan['backlinks'] < 1 && 'none' === $plan['commercial_value'] && $plan['gsc_clicks'] < 1 && $plan['gsc_impressions'] < 30;
			$rollout_stable = '' !== $plan['prune_action'] && (
				in_array($plan['prune_action'], array('keep_update', 'review', 'noindex'), true) ||
				('' !== trim($plan['redirect_target']) && in_array($plan['prune_action'], array('merge_301', 'delete_301'), true))
			);

			return array(
				'data_stable'    => $data_stable,
				'value_stable'   => $value_stable,
				'rollout_stable' => $rollout_stable,
			);
		}

		private static function get_recommendation($post_id, $plan) {
			$post_age_days = floor((time() - (int) get_post_time('U', true, $post_id)) / DAY_IN_SECONDS);
			if ($post_age_days < 365) {
				return array(
					'label' => 'Too new',
					'tone'  => '#2271b1',
				);
			}

			if (null === $plan['ga4_pageviews'] || null === $plan['gsc_clicks'] || null === $plan['gsc_impressions'] || null === $plan['backlinks']) {
				return array(
					'label' => 'Need data',
					'tone'  => '#dba617',
				);
			}

			if ($plan['backlinks'] > 0 || 'high' === $plan['commercial_value'] || $plan['gsc_clicks'] > 5 || $plan['gsc_impressions'] > 100) {
				return array(
					'label' => 'Keep / Update',
					'tone'  => '#2e7d32',
				);
			}

			if ($plan['ga4_pageviews'] < 30 && $plan['gsc_clicks'] < 1 && $plan['gsc_impressions'] < 30 && $plan['backlinks'] < 1 && 'none' === $plan['commercial_value']) {
				return array(
					'label' => 'Delete + 301',
					'tone'  => '#b32d2e',
				);
			}

			if ($plan['ga4_pageviews'] < 80 && $plan['gsc_clicks'] < 3 && $plan['gsc_impressions'] < 80 && $plan['backlinks'] < 1) {
				return array(
					'label' => 'Merge / Review',
					'tone'  => '#996800',
				);
			}

			return array(
				'label' => 'Manual review',
				'tone'  => '#646970',
			);
		}

		private static function render_stability_badges($checks) {
			$labels = array(
				'data_stable'    => 'Data',
				'value_stable'   => 'Value',
				'rollout_stable' => 'Rollout',
			);

			foreach ($labels as $key => $label) {
				$color = $checks[$key] ? '#2e7d32' : '#b32d2e';
				$text  = $checks[$key] ? 'Stable' : 'Open';
				echo '<span style="display:inline-block;margin:0 6px 6px 0;padding:3px 8px;border-radius:999px;background:' . esc_attr($color) . ';color:#fff;font-size:12px;">' . esc_html($label . ': ' . $text) . '</span>';
			}
		}

		private static function render_recommendation_badge($recommendation) {
			echo '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:' . esc_attr($recommendation['tone']) . ';color:#fff;font-weight:600;">' . esc_html($recommendation['label']) . '</span>';
		}

		private static function action_label($key) {
			$labels = array(
				'review' => 'Review',
				'keep_update' => 'Keep and Update',
				'noindex' => 'Keep and Noindex',
				'merge_301' => 'Merge and 301',
				'delete_301' => 'Delete and 301',
			);

			return isset($labels[$key]) ? $labels[$key] : $key;
		}

		private static function meta_key($suffix) {
			return '_rtadv_prune_' . $suffix;
		}

		private static function get_number_meta($post_id, $suffix) {
			$value = get_post_meta($post_id, self::meta_key($suffix), true);
			if ('' === $value || null === $value) {
				return null;
			}

			return max(0, (int) $value);
		}

		private static function request_number($field, $post_id) {
			if (! isset($_POST[$field][$post_id])) {
				return '';
			}

			$value = trim((string) wp_unslash($_POST[$field][$post_id]));
			if ('' === $value) {
				return '';
			}

			return max(0, (int) $value);
		}

		private static function request_choice($field, $post_id, $allowed) {
			if (! isset($_POST[$field][$post_id])) {
				return '';
			}

			$value = sanitize_key(wp_unslash($_POST[$field][$post_id]));
			return in_array($value, $allowed, true) ? $value : '';
		}

		private static function request_text($field, $post_id) {
			if (! isset($_POST[$field][$post_id])) {
				return '';
			}

			return sanitize_text_field(wp_unslash($_POST[$field][$post_id]));
		}

		private static function request_textarea($field, $post_id) {
			if (! isset($_POST[$field][$post_id])) {
				return '';
			}

			return sanitize_textarea_field(wp_unslash($_POST[$field][$post_id]));
		}

		private static function save_field($post_id, $suffix, $value) {
			$key = self::meta_key($suffix);
			if ('' === $value) {
				delete_post_meta($post_id, $key);
				return;
			}

			update_post_meta($post_id, $key, $value);
		}

		private static function current_page_url() {
			$filters = self::get_filters();
			return add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					's'            => $filters['search'],
					'cat'          => $filters['cat'],
					'prune_action' => $filters['action'],
					'paged'        => $filters['paged'],
				),
				admin_url('admin.php')
			);
		}

		private static function stringify_number($value) {
			return null === $value ? '' : (string) $value;
		}
	}

	RTADV_Content_Pruning_Planner::boot();
}
