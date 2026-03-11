#!/bin/zsh
# Audit repeated "Video Thumbnail" media attachments from the public WP REST API.
# Usage:
#   ./scripts/wp-video-thumbnail-audit.sh
#   ./scripts/wp-video-thumbnail-audit.sh https://www.rtadv.com 2026-03-01T00:00:00 200

set -euo pipefail

SITE_URL="${1:-https://www.rtadv.com}"
AFTER_DATE="${2:-2026-03-01T00:00:00}"
PER_PAGE="${3:-100}"
PAGE=1
TMP_FILE="$(mktemp)"

cleanup() {
	rm -f "$TMP_FILE"
}
trap cleanup EXIT

while true; do
	RESPONSE="$(curl -fsSL "${SITE_URL}/wp-json/wp/v2/media?per_page=${PER_PAGE}&page=${PAGE}&after=${AFTER_DATE}")"
	if [ "$(printf '%s' "$RESPONSE" | jq 'length')" -eq 0 ]; then
		break
	fi

	printf '%s\n' "$RESPONSE" >> "$TMP_FILE"
	PAGE=$((PAGE + 1))
done

jq -s '
	map(.[])
	| map(
		select(.title.rendered | startswith("Video Thumbnail:"))
		| {
			id,
			date,
			post,
			slug,
			title: .title.rendered,
			source_url
		}
	)
	| {
		total_video_thumbnails: length,
		unique_posts: (map(.post) | unique | length),
		duplicate_groups: (
			group_by(.post, .title)
			| map(select(length > 1))
			| sort_by(length)
			| reverse
			| map({
				post: .[0].post,
				title: .[0].title,
				count: length,
				first_created: (map(.date) | min),
				last_created: (map(.date) | max),
				latest_source_url: .[-1].source_url,
				attachment_ids: map(.id)
			})
		)
	}
' "$TMP_FILE"
