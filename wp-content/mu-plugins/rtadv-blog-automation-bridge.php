<?php
/**
 * Plugin Name: RTADV Blog Automation Bridge
 * Description: Draft-first bridge that imports generated RTADV article drafts and article images without touching Divi settings.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('RTADV_Blog_Automation_Bridge')) {
	final class RTADV_Blog_Automation_Bridge {
		const REST_NAMESPACE = 'rtadv/v1';
		const REST_ROUTE = '/blog-automation/draft';

		public static function boot() {
			$instance = new self();
			add_action('rest_api_init', array($instance, 'register_routes'));
			add_action('admin_menu', array($instance, 'register_admin_page'));
		}

		public function register_routes() {
			register_rest_route(
				self::REST_NAMESPACE,
				self::REST_ROUTE,
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array($this, 'handle_generate_request'),
					'permission_callback' => array($this, 'can_generate_drafts'),
				)
			);
		}

		public function register_admin_page() {
			add_management_page(
				'RTADV 自動草稿',
				'RTADV 自動草稿',
				'edit_posts',
				'rtadv-blog-automation',
				array($this, 'render_admin_page')
			);
		}

		public function can_generate_drafts() {
			return current_user_can('edit_posts');
		}

		public function render_admin_page() {
			if (! current_user_can('edit_posts')) {
				wp_die(esc_html__('You do not have permission to access this page.', 'default'));
			}

			$settings = $this->get_bridge_settings();
			$defaults = $this->get_default_payload();
			$endpoint = esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_ROUTE));
			$nonce    = wp_create_nonce('wp_rest');
			?>
			<div class="wrap">
				<h1>RTADV 自動產文與圖片草稿</h1>
				<p>這個工具只會建立 WordPress 草稿，並沿用共用 RTADV 產文流程。它不會改動 Divi Builder 或 Theme Builder 設定。</p>
				<?php if (! empty($settings['settingsSource'])) : ?>
					<p><em>目前預設值來源：<?php echo esc_html((string) $settings['settingsSource']); ?></em></p>
				<?php endif; ?>
				<form id="rtadv-blog-automation-form">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="rtadv-keyword">關鍵字</label></th>
								<td><input class="regular-text" id="rtadv-keyword" name="keyword" required type="text" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-title-hint">標題提示</label></th>
								<td><input class="regular-text" id="rtadv-title-hint" name="titleHint" type="text" value="<?php echo isset($defaults['titleHint']) ? esc_attr((string) $defaults['titleHint']) : ''; ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-audience">受眾</label></th>
								<td><input class="regular-text" id="rtadv-audience" name="audience" type="text" placeholder="台灣品牌主、採購、設計窗口" value="<?php echo isset($defaults['audience']) ? esc_attr((string) $defaults['audience']) : ''; ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-template">文章模板</label></th>
								<td>
									<select id="rtadv-template" name="templateVariant">
										<option value="">自動輪替</option>
										<option value="how-to" <?php selected(isset($defaults['templateVariant']) ? $defaults['templateVariant'] : '', 'how-to'); ?>>教學型</option>
										<option value="comparison" <?php selected(isset($defaults['templateVariant']) ? $defaults['templateVariant'] : '', 'comparison'); ?>>比較型</option>
										<option value="decision" <?php selected(isset($defaults['templateVariant']) ? $defaults['templateVariant'] : '', 'decision'); ?>>決策型</option>
										<option value="troubleshooting" <?php selected(isset($defaults['templateVariant']) ? $defaults['templateVariant'] : '', 'troubleshooting'); ?>>排查型</option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="rtadv-render-images">同步產圖</label></th>
								<td>
									<label>
										<input id="rtadv-render-images" name="includeRenderedImages" type="checkbox" value="1" <?php checked(! empty($defaults['includeRenderedImages'])); ?> />
										如果上游有啟用圖片模型，就把 `hero / detail / lifestyle` 圖片一起匯入媒體庫
									</label>
								</td>
							</tr>
						</tbody>
					</table>
					<?php submit_button('建立草稿'); ?>
				</form>
				<div id="rtadv-blog-automation-result" style="margin-top:16px;"></div>
			</div>
			<script>
				(function() {
					const form = document.getElementById('rtadv-blog-automation-form');
					const result = document.getElementById('rtadv-blog-automation-result');
					const endpoint = <?php echo wp_json_encode($endpoint); ?>;
					const nonce = <?php echo wp_json_encode($nonce); ?>;
					const defaults = <?php echo wp_json_encode($defaults); ?>;

					function escapeHtml(value) {
						return String(value)
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;')
							.replace(/"/g, '&quot;')
							.replace(/'/g, '&#039;');
					}

					function safeUrl(value) {
						try {
							const url = new URL(String(value), window.location.origin);
							if (url.protocol === 'http:' || url.protocol === 'https:') {
								return url.toString();
							}
						} catch (error) {
							return '';
						}

						return '';
					}

					if (!form || !result) {
						return;
					}

					form.addEventListener('submit', async function(event) {
						event.preventDefault();
						result.innerHTML = '<p>正在建立草稿...</p>';

						const data = new FormData(form);
						const payload = Object.assign({}, defaults, {
							keyword: String(data.get('keyword') || '').trim(),
							titleHint: String(data.get('titleHint') || '').trim(),
							audience: String(data.get('audience') || '').trim(),
							templateVariant: String(data.get('templateVariant') || '').trim(),
							includeRenderedImages: data.get('includeRenderedImages') === '1'
						});

						Object.keys(payload).forEach(function(key) {
							if (payload[key] === '') {
								delete payload[key];
							}
						});

						try {
							const response = await fetch(endpoint, {
								method: 'POST',
								headers: {
									'Content-Type': 'application/json',
									'X-WP-Nonce': nonce
								},
								body: JSON.stringify(payload)
							});

							const body = await response.json();

							if (!response.ok || !body.ok) {
								const error = escapeHtml(body.error || '建立草稿失敗');
								const detail = Array.isArray(body.issues)
									? '<pre>' + escapeHtml(JSON.stringify(body.issues, null, 2)) + '</pre>'
									: '';
								result.innerHTML = '<div class="notice notice-error inline"><p>' + error + '</p>' + detail + '</div>';
								return;
							}

							const links = [];
							if (body.editPostUrl) {
								const editUrl = safeUrl(body.editPostUrl);
								if (editUrl) {
									links.push('<a href="' + editUrl + '">編輯草稿</a>');
								}
							}
							if (body.previewUrl) {
								const previewUrl = safeUrl(body.previewUrl);
								if (previewUrl) {
									links.push('<a href="' + previewUrl + '" target="_blank" rel="noopener noreferrer">預覽頁</a>');
								}
							}

							result.innerHTML =
								'<div class="notice notice-success inline">' +
								'<p>已建立草稿：<strong>' + escapeHtml(body.postTitle || '未命名') + '</strong></p>' +
								'<p>文章 ID：' + escapeHtml(body.postId || '') + '</p>' +
								'<p>匯入圖片：' + escapeHtml(body.importedImages || 0) + '</p>' +
								(links.length ? '<p>' + links.join(' | ') + '</p>' : '') +
								'</div>';
						} catch (error) {
							result.innerHTML = '<div class="notice notice-error inline"><p>' + escapeHtml(String(error)) + '</p></div>';
						}
					});
				})();
			</script>
			<?php
		}

		public function handle_generate_request(WP_REST_Request $request) {
			$params = $request->get_json_params();
			if (! is_array($params)) {
				$params = array();
			}

			$keyword = isset($params['keyword']) ? sanitize_text_field(wp_unslash($params['keyword'])) : '';
			if ('' === $keyword) {
				return new WP_REST_Response(
					array(
						'ok'    => false,
						'error' => 'keyword is required',
					),
					400
				);
			}

			$payload = $this->build_upstream_payload($params);
			$upstream = $this->request_upstream_draft($payload);

			if (is_wp_error($upstream)) {
				return new WP_REST_Response(
					array(
						'ok'    => false,
						'error' => $upstream->get_error_message(),
					),
					500
				);
			}

			$post_result = $this->create_draft_post($upstream);
			if (is_wp_error($post_result)) {
				return new WP_REST_Response(
					array(
						'ok'    => false,
						'error' => $post_result->get_error_message(),
					),
					500
				);
			}

			return new WP_REST_Response(
				array(
					'ok'             => true,
					'postId'         => $post_result['post_id'],
					'postTitle'      => get_the_title($post_result['post_id']),
					'editPostUrl'    => get_edit_post_link($post_result['post_id'], 'raw'),
					'previewUrl'     => get_preview_post_link($post_result['post_id']),
					'importedImages' => count($post_result['imported_images']),
					'strategy'       => isset($upstream['strategy']) ? $upstream['strategy'] : array(),
					'workflow'       => isset($upstream['workflow']) ? $upstream['workflow'] : array(),
					'upstreamDraft'  => isset($upstream['draft']) ? $upstream['draft'] : null,
				),
				200
			);
		}

		private function build_upstream_payload(array $params) {
			$params = array_replace_recursive($this->get_default_payload(), $params);
			$allowed_scalar_fields = array(
				'keyword',
				'primaryKeyword',
				'category',
				'titleHint',
				'audience',
				'searchIntent',
				'templateVariant',
			);
			$allowed_list_fields = array(
				'secondaryKeywords',
				'queryVariants',
				'paaQuestions',
				'internalLinks',
			);
			$payload = array();

			foreach ($allowed_scalar_fields as $field) {
				if (! isset($params[ $field ])) {
					continue;
				}

				$value = sanitize_text_field(wp_unslash($params[ $field ]));
				if ('' !== $value) {
					$payload[ $field ] = $value;
				}
			}

			foreach ($allowed_list_fields as $field) {
				if (! isset($params[ $field ]) || ! is_array($params[ $field ])) {
					continue;
				}

				$values = array();
				foreach ($params[ $field ] as $item) {
					if ('internalLinks' === $field && is_array($item)) {
						$label = isset($item['label']) ? sanitize_text_field(wp_unslash($item['label'])) : '';
						$href  = isset($item['href']) ? esc_url_raw(wp_unslash($item['href'])) : '';
						if ('' !== $label && '' !== $href) {
							$values[] = array(
								'label' => $label,
								'href'  => $href,
							);
						}
						continue;
					}

					if (! is_scalar($item)) {
						continue;
					}

					$sanitized = sanitize_text_field(wp_unslash((string) $item));
					if ('' !== $sanitized) {
						$values[] = $sanitized;
					}
				}

				if (! empty($values)) {
					$payload[ $field ] = $values;
				}
			}

			$payload['includeRenderedImages'] = ! empty($params['includeRenderedImages']);

			return $payload;
		}

		private function request_upstream_draft(array $payload) {
			$settings = $this->get_bridge_settings();
			$base_url = apply_filters(
				'rtadv_blog_automation_bridge_base_url',
				isset($settings['upstreamBaseUrl']) ? (string) $settings['upstreamBaseUrl'] : 'https://www.rtadv.net'
			);
			$timeout  = (int) apply_filters(
				'rtadv_blog_automation_bridge_timeout',
				isset($settings['requestTimeout']) ? (int) $settings['requestTimeout'] : 90
			);
			$path     = isset($settings['upstreamEndpointPath']) ? (string) $settings['upstreamEndpointPath'] : '/api/blog-automation/draft';
			$url      = trailingslashit(untrailingslashit($base_url)) . ltrim($path, '/');
			$args     = array(
				'timeout' => max(15, $timeout),
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode($payload),
			);

			$args = apply_filters('rtadv_blog_automation_bridge_request_args', $args, $payload);
			$response = wp_remote_post($url, $args);

			if (is_wp_error($response)) {
				return new WP_Error(
					'rtadv_blog_automation_upstream_unreachable',
					sprintf('Failed to reach upstream draft service: %s', $response->get_error_message())
				);
			}

			$status = (int) wp_remote_retrieve_response_code($response);
			$body   = json_decode(wp_remote_retrieve_body($response), true);

			if ($status < 200 || $status >= 300 || ! is_array($body)) {
				return new WP_Error(
					'rtadv_blog_automation_upstream_invalid',
					sprintf('Upstream draft service returned HTTP %d.', $status)
				);
			}

			if (empty($body['ok']) || empty($body['draft']) || ! is_array($body['draft'])) {
				$message = isset($body['error']) ? (string) $body['error'] : 'Upstream draft service returned an invalid payload.';
				return new WP_Error('rtadv_blog_automation_upstream_failed', $message);
			}

			return $body;
		}

		private function create_draft_post(array $upstream) {
			$draft = $upstream['draft'];
			$title = ! empty($draft['title']) ? wp_strip_all_tags((string) $draft['title']) : 'RTADV 自動草稿';
			$slug  = ! empty($draft['slug']) ? sanitize_title((string) $draft['slug']) : sanitize_title($title);

			$post_data = array(
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_excerpt' => ! empty($draft['excerpt']) ? sanitize_text_field((string) $draft['excerpt']) : '',
				'post_content' => $this->build_post_content($draft),
			);

			$post_id = wp_insert_post(wp_slash($post_data), true);
			if (is_wp_error($post_id)) {
				return $post_id;
			}

			update_post_meta($post_id, '_rtadv_blog_automation_source_keyword', isset($draft['sourceKeyword']) ? sanitize_text_field((string) $draft['sourceKeyword']) : '');
			update_post_meta($post_id, '_rtadv_blog_automation_focus_keyword', isset($draft['focusKeyword']) ? sanitize_text_field((string) $draft['focusKeyword']) : '');
			update_post_meta($post_id, '_rtadv_blog_automation_template_variant', isset($draft['templateVariant']) ? sanitize_text_field((string) $draft['templateVariant']) : '');
			update_post_meta($post_id, '_rtadv_blog_automation_search_intent', isset($draft['searchIntent']) ? sanitize_text_field((string) $draft['searchIntent']) : '');
			update_post_meta($post_id, '_rtadv_blog_automation_seo_title', isset($draft['seoTitle']) ? sanitize_text_field((string) $draft['seoTitle']) : '');
			update_post_meta($post_id, '_rtadv_blog_automation_seo_description', isset($draft['seoDescription']) ? sanitize_textarea_field((string) $draft['seoDescription']) : '');
			update_post_meta($post_id, '_rtadv_blog_automation_raw_markdown', isset($draft['articleMarkdown']) ? (string) $draft['articleMarkdown'] : '');
			update_post_meta($post_id, '_rtadv_blog_automation_proofread', wp_json_encode(isset($draft['proofread']) ? $draft['proofread'] : array()));
			update_post_meta($post_id, '_rtadv_blog_automation_sources', wp_json_encode(isset($draft['sources']) ? $draft['sources'] : array()));
			update_post_meta($post_id, '_rtadv_blog_automation_image_plans', wp_json_encode(isset($draft['imagePlans']) ? $draft['imagePlans'] : array()));
			update_post_meta($post_id, '_rtadv_blog_automation_term_visuals', wp_json_encode(isset($draft['termVisuals']) ? $draft['termVisuals'] : array()));
			update_post_meta($post_id, '_rtadv_blog_automation_settings_source', $this->get_bridge_settings_value('settingsSource', ''));
			update_post_meta($post_id, '_rtadv_blog_automation_seo_defaults', wp_json_encode($this->get_bridge_settings_value('seoAutoDefaults', array())));

			$rank_math_focus_keyword = isset($draft['focusKeyword']) ? sanitize_text_field((string) $draft['focusKeyword']) : '';
			$rank_math_title         = isset($draft['seoTitle']) ? sanitize_text_field((string) $draft['seoTitle']) : '';
			$rank_math_description   = isset($draft['seoDescription']) ? sanitize_textarea_field((string) $draft['seoDescription']) : '';

			if ('' !== $rank_math_focus_keyword) {
				update_post_meta($post_id, 'rank_math_focus_keyword', $rank_math_focus_keyword);
			} else {
				delete_post_meta($post_id, 'rank_math_focus_keyword');
			}

			if ('' !== $rank_math_title) {
				update_post_meta($post_id, 'rank_math_title', $rank_math_title);
			} else {
				delete_post_meta($post_id, 'rank_math_title');
			}

			if ('' !== $rank_math_description) {
				update_post_meta($post_id, 'rank_math_description', $rank_math_description);
			} else {
				delete_post_meta($post_id, 'rank_math_description');
			}

			$imported_images = array();
			if (! empty($draft['imagePlans']) && is_array($draft['imagePlans'])) {
				foreach ($draft['imagePlans'] as $image_plan) {
					if (! is_array($image_plan)) {
						continue;
					}

					$attachment_id = $this->maybe_import_image_attachment($post_id, $slug, $image_plan);
					if (is_wp_error($attachment_id) || 0 === $attachment_id) {
						continue;
					}

					$imported_images[] = array(
						'variant'       => isset($image_plan['variant']) ? sanitize_text_field((string) $image_plan['variant']) : '',
						'attachment_id' => $attachment_id,
					);

					if (isset($image_plan['variant']) && 'hero' === $image_plan['variant']) {
						set_post_thumbnail($post_id, $attachment_id);
					}
				}
			}

			update_post_meta($post_id, '_rtadv_blog_automation_imported_images', $imported_images);

			return array(
				'post_id'         => $post_id,
				'imported_images' => $imported_images,
			);
		}

		private function build_post_content(array $draft) {
			$parts = array();

			if (! empty($draft['heroSummary'])) {
				$parts[] = wpautop(wp_kses_post((string) $draft['heroSummary']));
			}

			if (! empty($draft['sections']) && is_array($draft['sections'])) {
				foreach ($draft['sections'] as $section) {
					if (! is_array($section)) {
						continue;
					}

					$title = isset($section['title']) ? wp_strip_all_tags((string) $section['title']) : '';
					if ('' !== $title) {
						$parts[] = sprintf('<h2>%s</h2>', esc_html($title));
					}

					if (! empty($section['body']) && is_array($section['body'])) {
						foreach ($section['body'] as $paragraph) {
							$parts[] = wpautop(wp_kses_post((string) $paragraph));
						}
					}

					if (! empty($section['bullets']) && is_array($section['bullets'])) {
						$list_items = array();
						foreach ($section['bullets'] as $bullet) {
							$list_items[] = sprintf('<li>%s</li>', esc_html((string) $bullet));
						}
						if (! empty($list_items)) {
							$parts[] = '<ul>' . implode('', $list_items) . '</ul>';
						}
					}

					if (! empty($section['table']) && is_array($section['table'])) {
						$parts[] = $this->render_table($section['table']);
					}
				}
			}

			if (! empty($draft['geoSummary'])) {
				$parts[] = '<h2>SEO / GEO 摘要</h2>';
				$parts[] = wpautop(wp_kses_post((string) $draft['geoSummary']));
			}

			if (! empty($draft['faq']) && is_array($draft['faq'])) {
				$parts[] = '<h2>常見問題</h2>';
				foreach ($draft['faq'] as $item) {
					if (! is_array($item)) {
						continue;
					}
					if (! empty($item['question'])) {
						$parts[] = sprintf('<h3>%s</h3>', esc_html((string) $item['question']));
					}
					if (! empty($item['answer'])) {
						$parts[] = wpautop(wp_kses_post((string) $item['answer']));
					}
				}
			}

			if (! empty($draft['imagePlans']) && is_array($draft['imagePlans'])) {
				$image_notes = array();
				foreach ($draft['imagePlans'] as $image_plan) {
					if (! is_array($image_plan) || empty($image_plan['imagePrompt'])) {
						continue;
					}
					$label = ! empty($image_plan['label']) ? (string) $image_plan['label'] : '圖片規劃';
					$image_notes[] = sprintf(
						'<li><strong>%s</strong><br>%s</li>',
						esc_html($label),
						esc_html((string) $image_plan['imagePrompt'])
					);
				}

				if (! empty($image_notes)) {
					$parts[] = '<h2>圖片規劃</h2><ul>' . implode('', $image_notes) . '</ul>';
				}
			}

			return implode("\n\n", array_filter($parts));
		}

		private function render_table(array $table) {
			$headers = ! empty($table['headers']) && is_array($table['headers']) ? $table['headers'] : array();
			$rows    = ! empty($table['rows']) && is_array($table['rows']) ? $table['rows'] : array();

			if (empty($headers) || empty($rows)) {
				return '';
			}

			$header_html = '';
			foreach ($headers as $header) {
				$header_html .= sprintf('<th>%s</th>', esc_html((string) $header));
			}

			$row_html = '';
			foreach ($rows as $row) {
				if (! is_array($row)) {
					continue;
				}

				$cells = '';
				foreach ($row as $cell) {
					$cells .= sprintf('<td>%s</td>', esc_html((string) $cell));
				}

				if ('' !== $cells) {
					$row_html .= '<tr>' . $cells . '</tr>';
				}
			}

			if ('' === $row_html) {
				return '';
			}

			return '<table class="widefat striped"><thead><tr>' . $header_html . '</tr></thead><tbody>' . $row_html . '</tbody></table>';
		}

		private function maybe_import_image_attachment($post_id, $slug, array $image_plan) {
			if (empty($image_plan['imageBase64']) || empty($image_plan['imageMimeType'])) {
				return 0;
			}

			$binary = base64_decode((string) $image_plan['imageBase64'], true);
			if (false === $binary || '' === $binary) {
				return new WP_Error('rtadv_blog_automation_invalid_image', 'Failed to decode upstream image payload.');
			}

			$mime_to_extension = array(
				'image/webp' => 'webp',
				'image/png'  => 'png',
				'image/jpeg' => 'jpg',
			);
			$mime         = sanitize_text_field((string) $image_plan['imageMimeType']);
			$extension    = isset($mime_to_extension[ $mime ]) ? $mime_to_extension[ $mime ] : 'bin';
			$variant      = isset($image_plan['variant']) ? sanitize_key((string) $image_plan['variant']) : 'image';
			$file_stem    = sanitize_file_name($slug . '-' . $variant);
			$file_name    = sanitize_file_name($file_stem . '.' . $extension);
			$content_hash = sha1($binary);

			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_title = ! empty($image_plan['title'])
				? sanitize_text_field((string) $image_plan['title'])
				: (! empty($image_plan['label']) ? sanitize_text_field((string) $image_plan['label']) : $file_name);
			$attachment_caption = ! empty($image_plan['caption']) ? sanitize_textarea_field((string) $image_plan['caption']) : '';
			$attachment_description = ! empty($image_plan['description']) ? wp_kses_post((string) $image_plan['description']) : '';
			$attachment_alt = ! empty($image_plan['altText'])
				? sanitize_text_field((string) $image_plan['altText'])
				: (! empty($image_plan['label']) ? sanitize_text_field((string) $image_plan['label']) : '');
			$existing_attachment_id = $this->find_existing_image_attachment($post_id, $slug, $variant, $content_hash);

			$attachment = array(
				'post_mime_type' => $mime,
				'post_parent'    => $post_id,
				'post_status'    => 'inherit',
				'post_title'     => $attachment_title,
				'post_excerpt'   => $attachment_caption,
				'post_content'   => $attachment_description,
			);

			if ($existing_attachment_id > 0) {
				$attachment['ID'] = $existing_attachment_id;
				$updated_id = wp_update_post($attachment, true);
				if (is_wp_error($updated_id)) {
					return $updated_id;
				}
				if ('' !== $attachment_alt) {
					update_post_meta($existing_attachment_id, '_wp_attachment_image_alt', $attachment_alt);
				}
				$this->store_imported_image_attachment_meta($existing_attachment_id, $slug, $variant, $content_hash, $file_name);
				return (int) $existing_attachment_id;
			}

			$upload = wp_upload_bits($file_name, null, $binary);
			if (! empty($upload['error'])) {
				return new WP_Error('rtadv_blog_automation_upload_failed', (string) $upload['error']);
			}

			$attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id, true);
			if (is_wp_error($attachment_id)) {
				return $attachment_id;
			}
			$metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
			if (! is_wp_error($metadata) && ! empty($metadata)) {
				wp_update_attachment_metadata($attachment_id, $metadata);
			}

			if ('' !== $attachment_alt) {
				update_post_meta($attachment_id, '_wp_attachment_image_alt', $attachment_alt);
			}
			$this->store_imported_image_attachment_meta($attachment_id, $slug, $variant, $content_hash, $file_name);

			return (int) $attachment_id;
		}

		private function find_existing_image_attachment($post_id, $slug, $variant, $content_hash) {
			$normalized_slug = sanitize_title($slug);
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_parent'    => $post_id,
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'   => '_rtadv_blog_automation_image_hash',
							'value' => $content_hash,
						),
						array(
							'relation' => 'AND',
							array(
								'key'   => '_rtadv_blog_automation_image_slug',
								'value' => $normalized_slug,
							),
							array(
								'key'   => '_rtadv_blog_automation_image_variant',
								'value' => $variant,
							),
						),
					),
				)
			);

			if (! empty($attachments[0])) {
				return (int) $attachments[0];
			}

			return 0;
		}

		private function store_imported_image_attachment_meta($attachment_id, $slug, $variant, $content_hash, $file_name) {
			update_post_meta($attachment_id, '_rtadv_blog_automation_image_slug', sanitize_title($slug));
			update_post_meta($attachment_id, '_rtadv_blog_automation_image_variant', sanitize_key($variant));
			update_post_meta($attachment_id, '_rtadv_blog_automation_image_hash', sanitize_text_field($content_hash));
			update_post_meta($attachment_id, '_rtadv_blog_automation_image_file_name', sanitize_file_name($file_name));
		}

		private function get_bridge_settings() {
			static $settings = null;

			if (null !== $settings) {
				return $settings;
			}

			$settings = array(
				'settingsSource'      => 'Built-in defaults',
				'upstreamBaseUrl'     => 'https://www.rtadv.net',
				'upstreamEndpointPath'=> '/api/blog-automation/draft',
				'requestTimeout'      => 90,
				'defaultPayload'      => array(
					'audience'             => '台灣品牌主、行銷人員、採購與設計窗口',
					'searchIntent'         => 'informational',
					'includeRenderedImages'=> true,
					'internalLinks'        => array(
						array(
							'label' => '盒型總覽',
							'href'  => '/structural-design/box-styles',
						),
						array(
							'label' => '報價與門檻',
							'href'  => '/pricing-thresholds',
						),
						array(
							'label' => '聯絡詢價',
							'href'  => '/contact',
						),
					),
				),
				'seoAutoDefaults'     => array(
					'dailyArticleCount' => 20,
					'maxDailyPublish'   => 20,
					'headQuota'         => 4,
					'bodyQuota'         => 8,
					'longtailQuota'     => 8,
					'selectionMode'     => '穩健成長',
					'notebookLmEnabled' => true,
					'refreshEnabled'    => true,
					'refreshFrequency'  => 'weekly',
				),
			);

			$config_file = trailingslashit(__DIR__) . 'rtadv-blog-automation/bridge-config.php';
			if (file_exists($config_file)) {
				$loaded = include $config_file;
				if (is_array($loaded)) {
					$settings = array_replace_recursive($settings, $loaded);
				}
			}

			$settings = apply_filters('rtadv_blog_automation_bridge_settings', $settings);

			return $settings;
		}

		private function get_default_payload() {
			$settings = $this->get_bridge_settings();
			return isset($settings['defaultPayload']) && is_array($settings['defaultPayload'])
				? $settings['defaultPayload']
				: array();
		}

		private function get_bridge_settings_value($key, $fallback) {
			$settings = $this->get_bridge_settings();
			return isset($settings[ $key ]) ? $settings[ $key ] : $fallback;
		}
	}

	RTADV_Blog_Automation_Bridge::boot();
}
