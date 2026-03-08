<?php
/**
 * Plugin Name: RTADV Pruning Tool Direct
 * Description: Provides a direct, front-end pruning tool for reviewing posts and moving selected posts to trash.
 * Author: Codex
 */

if (! defined('ABSPATH')) {
	exit;
}

if (! class_exists('RTADV_Pruning_Tool_Direct')) {
	final class RTADV_Pruning_Tool_Direct {
		const TOOL_PATH = '/rtadv-pruning-tool/';
		const PAGE_SLUG = 'rtadv-pruning-tool-direct';
		const CAPABILITY = 'manage_options';
		const AJAX_ACTION = 'rtadv_pruning_trash_posts';
		const NONCE_ACTION = 'rtadv_pruning_tool_direct';

		public static function boot() {
			add_action('admin_menu', array(__CLASS__, 'register_admin_page'));
			add_action('template_redirect', array(__CLASS__, 'maybe_render_tool'));
			add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'handle_trash_posts'));
		}

		public static function register_admin_page() {
			add_menu_page(
				'RTADV 直達刪文工具',
				'RTADV 刪文',
				self::CAPABILITY,
				self::PAGE_SLUG,
				array(__CLASS__, 'render_admin_shell'),
				'dashicons-trash',
				57
			);

			add_submenu_page(
				self::PAGE_SLUG,
				'RTADV 直達刪文工具',
				'直達工具',
				self::CAPABILITY,
				self::PAGE_SLUG,
				array(__CLASS__, 'render_admin_shell')
			);
		}

		public static function render_admin_shell() {
			if (! self::current_user_can_use_tool()) {
				wp_die(esc_html__('You do not have permission to access this page.', 'default'));
			}

			echo '<div class="wrap" style="padding:0;margin:0;">';
			self::render_tool();
			echo '</div>';
		}

		public static function maybe_render_tool() {
			if (! self::is_tool_request() && ! self::is_query_request()) {
				return;
			}

			status_header(200);
			nocache_headers();
			self::render_tool();
			exit;
		}

		public static function handle_trash_posts() {
			if (! self::current_user_can_use_tool()) {
				wp_send_json_error(array('message' => '目前登入身分不能使用這個刪文工具。'), 403);
			}

			check_ajax_referer(self::NONCE_ACTION, 'nonce');

			$post_ids = isset($_POST['post_ids']) ? array_map('absint', (array) wp_unslash($_POST['post_ids'])) : array();
			$post_ids = array_values(array_filter(array_unique($post_ids)));

			if (empty($post_ids)) {
				wp_send_json_error(array('message' => '沒有收到要處理的文章。'), 400);
			}

			$trashed = array();
			$failed  = array();

			foreach ($post_ids as $post_id) {
				if ('post' !== get_post_type($post_id) || self::is_divi_built_post($post_id)) {
					$failed[] = array('id' => $post_id, 'reason' => 'not_post');
					continue;
				}

				if (! current_user_can('delete_post', $post_id)) {
					$failed[] = array('id' => $post_id, 'reason' => 'forbidden');
					continue;
				}

				$result = wp_trash_post($post_id);
				if ($result instanceof WP_Post) {
					$trashed[] = $post_id;
					continue;
				}

				$failed[] = array('id' => $post_id, 'reason' => 'trash_failed');
			}

			wp_send_json_success(
				array(
					'trashed' => $trashed,
					'failed'  => $failed,
					'message' => sprintf('已移到垃圾桶 %d 篇文章。', count($trashed)),
				)
			);
		}

		private static function is_tool_request() {
			$request_path = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
			$request_path = (string) wp_parse_url($request_path, PHP_URL_PATH);
			$target_path  = (string) wp_parse_url(home_url(self::TOOL_PATH), PHP_URL_PATH);

			return untrailingslashit($request_path) === untrailingslashit($target_path);
		}

		private static function is_query_request() {
			return isset($_GET['rtadv_pruning_tool']) && '1' === (string) wp_unslash($_GET['rtadv_pruning_tool']);
		}

		private static function current_user_can_use_tool() {
			if (! is_user_logged_in()) {
				return false;
			}

			$user = wp_get_current_user();
			if (! $user instanceof WP_User) {
				return false;
			}

			return current_user_can(self::CAPABILITY);
		}

		private static function get_posts_payload() {
			$post_ids = self::get_eligible_post_ids();
			if (empty($post_ids)) {
				return array();
			}

			$posts = get_posts(
				array(
					'post_type'           => 'post',
					'post_status'         => 'publish',
					'post__in'            => $post_ids,
					'posts_per_page'      => -1,
					'orderby'             => 'date',
					'order'               => 'ASC',
					'ignore_sticky_posts' => true,
				)
			);

			$payload = array();
			foreach ($posts as $post) {
				$payload[] = array(
					'id'    => (int) $post->ID,
					'title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
					'date'  => get_the_date('Y-m-d', $post),
					'url'   => get_permalink($post),
				);
			}

			return $payload;
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

		private static function render_tool() {
			$is_allowed = self::current_user_can_use_tool();
			$tool_url   = home_url(self::TOOL_PATH);
			$query_url  = home_url('/?rtadv_pruning_tool=1');
			$admin_url  = admin_url('admin.php?page=' . self::PAGE_SLUG);
			$login_url  = wp_login_url($tool_url);
			$ajax_url   = admin_url('admin-ajax.php');
			$posts_json = wp_json_encode(self::get_posts_payload());
			$nonce      = wp_create_nonce(self::NONCE_ACTION);
			?>
<!doctype html>
<html lang="zh-Hant">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>RTADV 直達刪文工具</title>
	<style>
		:root {
			color-scheme: light;
			--bg: #f4efe6;
			--panel: #fffdf8;
			--line: #d9d1c3;
			--text: #1e2a2a;
			--muted: #607070;
			--accent: #225b52;
			--warn: #a4551d;
			--danger: #a12929;
			--ok: #2f6b2f;
		}

		* { box-sizing: border-box; }
		body {
			margin: 0;
			font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			color: var(--text);
			background:
				radial-gradient(circle at top left, rgba(217, 160, 102, 0.18), transparent 32%),
				linear-gradient(180deg, #efe7d9 0%, var(--bg) 100%);
		}

		.wrap {
			max-width: 1320px;
			margin: 28px auto;
			padding: 0 18px 40px;
		}

		.hero, .panel {
			background: rgba(255, 253, 248, 0.96);
			border: 1px solid var(--line);
			border-radius: 18px;
			box-shadow: 0 14px 40px rgba(30, 42, 42, 0.08);
		}

		.hero {
			padding: 24px;
			margin-bottom: 16px;
		}

		h1 {
			margin: 0 0 8px;
			font-size: 32px;
			line-height: 1.15;
		}

		p {
			margin: 0;
			color: var(--muted);
		}

		.toolbar {
			display: grid;
			grid-template-columns: 1.4fr repeat(5, minmax(120px, 1fr));
			gap: 10px;
			margin: 16px 0 18px;
		}

		input[type="search"],
		input[type="number"],
		select {
			width: 100%;
			padding: 10px 12px;
			border: 1px solid var(--line);
			border-radius: 10px;
			background: #fff;
			font: inherit;
		}

		button, .button-link {
			appearance: none;
			border: 0;
			border-radius: 12px;
			padding: 12px 14px;
			font: inherit;
			font-weight: 700;
			cursor: pointer;
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
		}

		.button-primary { background: var(--accent); color: #fff; }
		.button-secondary { background: #fff; color: var(--text); border: 1px solid var(--line); }
		.button-danger { background: var(--danger); color: #fff; }

		.panel {
			padding: 18px;
		}

		.summary {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
			margin-bottom: 14px;
		}

		.pill {
			padding: 8px 12px;
			border-radius: 999px;
			border: 1px solid var(--line);
			background: #fff;
			color: var(--muted);
			font-size: 13px;
			font-weight: 700;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		th, td {
			padding: 10px 8px;
			border-top: 1px solid #ece4d6;
			vertical-align: top;
			text-align: left;
		}

		th {
			font-size: 12px;
			letter-spacing: 0.02em;
			text-transform: uppercase;
			color: var(--muted);
			border-top: 0;
		}

		tr.hidden {
			display: none;
		}

		.title a {
			color: var(--text);
			text-decoration: none;
			font-weight: 700;
		}

		.meta {
			color: var(--muted);
			font-size: 13px;
			margin-top: 4px;
		}

		.badge {
			display: inline-block;
			padding: 6px 10px;
			border-radius: 999px;
			font-size: 12px;
			font-weight: 800;
			letter-spacing: 0.02em;
			background: #f6f1e7;
			color: #564a34;
		}

		.badge.delete { background: #fbeaea; color: #8b2626; }
		.badge.keep { background: #ebf6ec; color: #2a6630; }
		.badge.review { background: #f8f0e5; color: var(--warn); }

		.notice {
			margin-top: 14px;
			padding: 12px 14px;
			border-radius: 12px;
			font-weight: 600;
			display: none;
		}

		.notice.show { display: block; }
		.notice.ok { background: #ebf6ec; color: var(--ok); }
		.notice.error { background: #fbeaea; color: var(--danger); }

		.empty {
			padding: 18px;
			text-align: center;
			color: var(--muted);
		}

		.login-box {
			padding: 32px;
			text-align: center;
		}

		@media (max-width: 1100px) {
			.toolbar {
				grid-template-columns: 1fr 1fr;
			}
			.table-scroll {
				overflow-x: auto;
			}
			table {
				min-width: 1100px;
			}
		}
	</style>
</head>
<body>
	<div class="wrap">
		<section class="hero">
			<h1>RTADV 直達刪文工具</h1>
			<p>直接填數字、直接判斷、直接刪。刪除動作會把文章移到垃圾桶。</p>
			<p style="margin-top:10px;">入口：
				<a href="<?php echo esc_url($tool_url); ?>"><?php echo esc_html($tool_url); ?></a> |
				<a href="<?php echo esc_url($query_url); ?>"><?php echo esc_html($query_url); ?></a> |
				<a href="<?php echo esc_url($admin_url); ?>"><?php echo esc_html($admin_url); ?></a>
			</p>
		</section>

		<?php if (! $is_allowed) : ?>
			<section class="panel login-box">
				<h2>需要先登入可用帳號</h2>
				<p style="margin:8px 0 18px;">先登入 `rtadv.com`，再回到這個固定路徑。</p>
				<a class="button-link button-primary" href="<?php echo esc_url($login_url); ?>">先登入</a>
			</section>
		<?php else : ?>
			<section class="panel">
				<div class="toolbar">
					<input id="search" type="search" placeholder="搜尋標題或 URL">
					<button class="button-secondary" id="recommend">自動判斷</button>
					<button class="button-secondary" id="select-delete">勾選建議刪除</button>
					<button class="button-secondary" id="clear-select">清空勾選</button>
					<button class="button-secondary" id="save-local">暫存到本機</button>
					<button class="button-danger" id="trash-selected">刪除勾選（移到垃圾桶）</button>
				</div>

				<div class="summary">
					<div class="pill" id="count-all">總文章 0</div>
					<div class="pill" id="count-visible">目前顯示 0</div>
					<div class="pill" id="count-delete">建議刪除 0</div>
					<div class="pill" id="count-selected">已勾選 0</div>
				</div>

				<div class="table-scroll">
					<table>
						<thead>
							<tr>
								<th>選取</th>
								<th>文章</th>
								<th>GA4</th>
								<th>GSC Clicks</th>
								<th>GSC Impr.</th>
								<th>Backlinks</th>
								<th>商業價值</th>
								<th>判斷</th>
							</tr>
						</thead>
						<tbody id="rows"></tbody>
					</table>
				</div>
				<div class="empty" id="empty" style="display:none;">目前沒有符合條件的文章。</div>
				<div class="notice" id="notice"></div>
			</section>
		<?php endif; ?>
	</div>

	<?php if ($is_allowed) : ?>
	<script>
	(() => {
		const posts = <?php echo $posts_json ? $posts_json : '[]'; ?>;
		const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
		const nonce = <?php echo wp_json_encode($nonce); ?>;
		const storageKey = 'rtadv-pruning-tool-state-v1';

		const state = JSON.parse(localStorage.getItem(storageKey) || '{}');
		const rowsEl = document.getElementById('rows');
		const emptyEl = document.getElementById('empty');
		const noticeEl = document.getElementById('notice');
		const searchEl = document.getElementById('search');
		const countAllEl = document.getElementById('count-all');
		const countVisibleEl = document.getElementById('count-visible');
		const countDeleteEl = document.getElementById('count-delete');
		const countSelectedEl = document.getElementById('count-selected');

		function getRowState(id) {
			if (!state[id]) {
				state[id] = {
					ga4: '',
					gscClicks: '',
					gscImpressions: '',
					backlinks: '',
					commercial: '',
					recommendation: 'review'
				};
			}
			return state[id];
		}

		function computeRecommendation(rowState) {
			const ga4 = Number(rowState.ga4 || 0);
			const clicks = Number(rowState.gscClicks || 0);
			const impressions = Number(rowState.gscImpressions || 0);
			const backlinks = Number(rowState.backlinks || 0);
			const commercial = rowState.commercial || '';

			if (commercial === 'high') return ['keep', '保留'];
			if (backlinks > 0) return ['keep', '保留'];
			if (clicks > 10 || impressions > 120) return ['keep', '保留'];
			if (commercial === 'low' && (ga4 > 10 || clicks > 0 || impressions > 30)) return ['keep', '保留'];
			if (ga4 <= 10 && clicks <= 3 && impressions <= 30 && backlinks === 0 && (commercial === '' || commercial === 'none' || commercial === 'low')) {
				return ['delete', '建議刪除'];
			}
			return ['review', '再看一下'];
		}

		function saveState() {
			localStorage.setItem(storageKey, JSON.stringify(state));
		}

		function setNotice(message, type) {
			noticeEl.textContent = message;
			noticeEl.className = 'notice show ' + type;
		}

		function clearNotice() {
			noticeEl.className = 'notice';
			noticeEl.textContent = '';
		}

		function render() {
			rowsEl.innerHTML = '';
			const keyword = (searchEl.value || '').trim().toLowerCase();
			let visible = 0;
			let deleteCount = 0;
			let selectedCount = 0;

			posts.forEach((post) => {
				const rowState = getRowState(post.id);
				const recommendation = computeRecommendation(rowState);
				rowState.recommendation = recommendation[0];
				const matches = !keyword || post.title.toLowerCase().includes(keyword) || post.url.toLowerCase().includes(keyword);
				if (!matches) return;
				visible += 1;
				if (recommendation[0] === 'delete') deleteCount += 1;

				const tr = document.createElement('tr');
				tr.dataset.postId = String(post.id);

				tr.innerHTML = `
					<td><input type="checkbox" class="select-row"></td>
					<td class="title">
						<a href="${post.url}" target="_blank" rel="noopener noreferrer">${escapeHtml(post.title || '(無標題)')}</a>
						<div class="meta">ID ${post.id} · ${post.date}</div>
					</td>
					<td><input type="number" min="0" data-key="ga4" value="${escapeAttr(rowState.ga4)}"></td>
					<td><input type="number" min="0" data-key="gscClicks" value="${escapeAttr(rowState.gscClicks)}"></td>
					<td><input type="number" min="0" data-key="gscImpressions" value="${escapeAttr(rowState.gscImpressions)}"></td>
					<td><input type="number" min="0" data-key="backlinks" value="${escapeAttr(rowState.backlinks)}"></td>
					<td>
						<select data-key="commercial">
							<option value="" ${rowState.commercial === '' ? 'selected' : ''}>Unknown</option>
							<option value="none" ${rowState.commercial === 'none' ? 'selected' : ''}>None</option>
							<option value="low" ${rowState.commercial === 'low' ? 'selected' : ''}>Low</option>
							<option value="high" ${rowState.commercial === 'high' ? 'selected' : ''}>High</option>
						</select>
					</td>
					<td><span class="badge ${recommendation[0]}">${recommendation[1]}</span></td>
				`;

				tr.querySelectorAll('input[data-key], select[data-key]').forEach((input) => {
					input.addEventListener('input', () => {
						rowState[input.dataset.key] = input.value;
						render();
					});
					input.addEventListener('change', () => {
						rowState[input.dataset.key] = input.value;
						render();
					});
				});

				const checkbox = tr.querySelector('.select-row');
				checkbox.checked = Boolean(rowState.selected);
				if (checkbox.checked) selectedCount += 1;
				checkbox.addEventListener('change', () => {
					rowState.selected = checkbox.checked;
					updateSummary();
				});

				rowsEl.appendChild(tr);
			});

			countAllEl.textContent = `總文章 ${posts.length}`;
			countVisibleEl.textContent = `目前顯示 ${visible}`;
			countDeleteEl.textContent = `建議刪除 ${deleteCount}`;
			countSelectedEl.textContent = `已勾選 ${selectedCount}`;
			emptyEl.style.display = visible ? 'none' : 'block';
			saveState();
		}

		function updateSummary() {
			let selectedCount = 0;
			let deleteCount = 0;
			let visible = 0;
			const keyword = (searchEl.value || '').trim().toLowerCase();

			posts.forEach((post) => {
				const rowState = getRowState(post.id);
				const recommendation = computeRecommendation(rowState);
				rowState.recommendation = recommendation[0];
				const matches = !keyword || post.title.toLowerCase().includes(keyword) || post.url.toLowerCase().includes(keyword);
				if (matches) visible += 1;
				if (matches && recommendation[0] === 'delete') deleteCount += 1;
				if (rowState.selected) selectedCount += 1;
			});

			countAllEl.textContent = `總文章 ${posts.length}`;
			countVisibleEl.textContent = `目前顯示 ${visible}`;
			countDeleteEl.textContent = `建議刪除 ${deleteCount}`;
			countSelectedEl.textContent = `已勾選 ${selectedCount}`;
			saveState();
		}

		function escapeHtml(value) {
			return String(value)
				.replaceAll('&', '&amp;')
				.replaceAll('<', '&lt;')
				.replaceAll('>', '&gt;')
				.replaceAll('"', '&quot;')
				.replaceAll("'", '&#39;');
		}

		function escapeAttr(value) {
			return escapeHtml(value == null ? '' : String(value));
		}

		async function trashSelected() {
			const selectedIds = posts
				.filter((post) => getRowState(post.id).selected)
				.map((post) => post.id);

			if (!selectedIds.length) {
				setNotice('請先勾選要刪除的文章。', 'error');
				return;
			}

			const confirmed = window.confirm(`確定要把 ${selectedIds.length} 篇文章移到垃圾桶嗎？`);
			if (!confirmed) return;

			clearNotice();

			const formData = new FormData();
			formData.append('action', <?php echo wp_json_encode(self::AJAX_ACTION); ?>);
			formData.append('nonce', nonce);
			selectedIds.forEach((id) => formData.append('post_ids[]', String(id)));

			try {
				const response = await fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData
				});
				const payload = await response.json();
				if (!response.ok || !payload.success) {
					throw new Error(payload && payload.data && payload.data.message ? payload.data.message : '刪除失敗');
				}

				const trashedIds = new Set(payload.data.trashed || []);
				for (const id of trashedIds) {
					delete state[id];
				}
				for (let i = posts.length - 1; i >= 0; i -= 1) {
					if (trashedIds.has(posts[i].id)) {
						posts.splice(i, 1);
					}
				}
				saveState();
				render();
				setNotice(payload.data.message || '已完成刪除。', 'ok');
			} catch (error) {
				setNotice(error.message || '刪除失敗。', 'error');
			}
		}

		document.getElementById('recommend').addEventListener('click', () => {
			render();
			setNotice('已重新完成自動判斷。', 'ok');
		});

		document.getElementById('select-delete').addEventListener('click', () => {
			posts.forEach((post) => {
				const rowState = getRowState(post.id);
				rowState.selected = computeRecommendation(rowState)[0] === 'delete';
			});
			render();
			setNotice('已勾選建議刪除的文章。', 'ok');
		});

		document.getElementById('clear-select').addEventListener('click', () => {
			posts.forEach((post) => {
				getRowState(post.id).selected = false;
			});
			render();
			clearNotice();
		});

		document.getElementById('save-local').addEventListener('click', () => {
			saveState();
			setNotice('目前填寫內容已暫存在這台電腦。', 'ok');
		});

		document.getElementById('trash-selected').addEventListener('click', trashSelected);
		searchEl.addEventListener('input', render);

		render();
	})();
	</script>
	<?php endif; ?>
</body>
</html>
			<?php
		}
	}

	RTADV_Pruning_Tool_Direct::boot();
}
