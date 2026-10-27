#!/usr/bin/env bash
set -euo pipefail
COMPOSE=(docker compose --env-file .env -f docker-compose.klickspark.yml)
BASE_URL=http://127.0.0.1:8080
# Real HTTP request exercises PHP's is_uploaded_file() and move_uploaded_file().
NONCE="$("${COMPOSE[@]}" run --rm -T wpcli eval 'echo wp_create_nonce("wp_rest");' | tr -d '\r\n')"
test -n "$NONCE"
# Verify the client-specific shortcode renders escaped service choices, while the shared
# shortcode remains a reusable free-text form for other client industries.
"${COMPOSE[@]}" run --rm -T wpcli eval '
$selected = do_shortcode("[klickspark_quote_form services=\"Standard Cleaning|Deep Cleaning|Move-In / Move-Out|Other Cleaning\"]");
if (strpos($selected, "<select name=\"service\" required>") === false ||
    strpos($selected, "<option value=\"Deep Cleaning\">Deep Cleaning</option>") === false ||
    strpos($selected, "<option value=\"Move-In / Move-Out\">Move-In / Move-Out</option>") === false) {
    fwrite(STDERR, "Client-specific quote service dropdown missing\\n"); exit(1);
}
$generic = do_shortcode("[klickspark_quote_form]");
if (strpos($generic, "<input name=\"service\" required") === false) {
    fwrite(STDERR, "Reusable quote service input missing\\n"); exit(1);
}
$escaped = do_shortcode("[klickspark_quote_form services='Safe Cleaning|<script>alert(1)</script>']");
if (strpos($escaped, "<script>alert(1)</script>") !== false ||
    strpos($escaped, "&lt;script&gt;alert(1)&lt;/script&gt;") === false) {
    fwrite(STDERR, "Quote service option HTML escaping failed\\n"); exit(1);
}
echo "Quote service choices, generic fallback and HTML escaping rendered\\n";
'

# A valid tiny PNG. The CI host creates the multipart body; Apache/PHP processes it.
printf '%s' 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lS8AAAAASUVORK5CYII=' | base64 -d > /tmp/ks-ci-photo.png
STATUS="$(curl -sS -o /tmp/ks-ci-response.json -w '%{http_code}' \
  -H "X-WP-Nonce: $NONCE" \
  -F 'name=HTTP Upload Test' -F 'email=http-upload@example.test' \
  -F 'service=Cleaning' -F 'description=HTTP multipart upload regression' \
  -F 'consent=1' -F 'photos[]=@/tmp/ks-ci-photo.png;type=image/png' \
  "$BASE_URL/?rest_route=/ks/v1/quote-request")"
if [[ "$STATUS" != 201 ]]; then
  echo "HTTP photo upload failed: $STATUS"
  cat /tmp/ks-ci-response.json
  exit 1
fi
# The private admin-post download route must never serve a photo anonymously.
QUOTE_ID="$("${COMPOSE[@]}" run --rm -T wpcli eval '
$posts = get_posts(["post_type"=>"ks_quote", "post_status"=>"private", "posts_per_page"=>-1]);
foreach ($posts as $post) {
  if ($post->post_title === "HTTP Upload Test — Cleaning") { echo $post->ID; exit; }
}
exit(1);
' | tr -d '\r\n')"
test -n "$QUOTE_ID"
DOWNLOAD_STATUS="$(curl -sS -o /tmp/ks-ci-anon-download-response -w '%{http_code}' \
  "$BASE_URL/wp-admin/admin-post.php?action=ks_quote_photo&quote_id=$QUOTE_ID&index=0")"
if [[ "$DOWNLOAD_STATUS" == 200 ]]; then
  echo "Anonymous private photo download unexpectedly succeeded"
  exit 1
fi
echo "Anonymous HTTP private photo download denied: $DOWNLOAD_STATUS"

# Log in through WordPress over HTTP and use its actual session cookie.
# Read the photo nonce from the quote edit screen in the same browser session.
source .env
# Keep WordPress canonical URL on the same host used by curl in disposable CI.
"${COMPOSE[@]}" run --rm -T wpcli option update home "$BASE_URL" >/dev/null
"${COMPOSE[@]}" run --rm -T wpcli option update siteurl "$BASE_URL" >/dev/null
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT
curl -sS -c "$COOKIE_JAR" "$BASE_URL/wp-login.php" -o /dev/null
LOGIN_STATUS="$(curl -sS -L -b "$COOKIE_JAR" -c "$COOKIE_JAR" -o /tmp/ks-ci-admin-dashboard.html -w '%{http_code}' \
  --data-urlencode "log=$WP_ADMIN_USER" \
  --data-urlencode "pwd=$WP_ADMIN_PASSWORD" \
  --data-urlencode 'wp-submit=Log In' \
  --data-urlencode "redirect_to=$BASE_URL/wp-admin/" \
  --data-urlencode 'testcookie=1' \
  "$BASE_URL/wp-login.php")"
if [[ "$LOGIN_STATUS" != 200 ]] || ! grep -Eq 'wp-admin-bar|wpadminbar|id="wpbody"' /tmp/ks-ci-admin-dashboard.html; then
  echo "Administrator HTTP login failed: $LOGIN_STATUS"
  exit 1
fi
curl -fsS -b "$COOKIE_JAR" "$BASE_URL/wp-admin/post.php?post=$QUOTE_ID&action=edit" -o /tmp/ks-ci-quote-admin.html
PHOTO_NONCE="$(grep -oE 'action=ks_quote_photo[^"]+' /tmp/ks-ci-quote-admin.html | head -n 1 | grep -oE '_wpnonce=[a-zA-Z0-9]+' | cut -d= -f2 || true)"
if [[ -z "$PHOTO_NONCE" ]]; then
  echo "Quote edit screen did not expose photo download nonce"
  exit 1
fi
ADMIN_DOWNLOAD_STATUS="$(curl -sS -b "$COOKIE_JAR" -o /tmp/ks-ci-admin-downloaded.png -w '%{http_code}' \
  "$BASE_URL/wp-admin/admin-post.php?action=ks_quote_photo&quote_id=$QUOTE_ID&index=0&_wpnonce=$PHOTO_NONCE")"
if [[ "$ADMIN_DOWNLOAD_STATUS" != 200 ]] || ! cmp -s /tmp/ks-ci-photo.png /tmp/ks-ci-admin-downloaded.png; then
  echo "Administrator photo download failed: HTTP $ADMIN_DOWNLOAD_STATUS or byte mismatch"
  exit 1
fi
echo "Authorized administrator downloaded exact private PNG bytes"

"${COMPOSE[@]}" run --rm -T wpcli eval '
$posts = get_posts(["post_type"=>"ks_quote", "post_status"=>"private", "posts_per_page"=>-1]);
$found = false;
foreach ($posts as $post) {
  if ($post->post_title !== "HTTP Upload Test — Cleaning") { continue; }
  $photos = get_post_meta($post->ID, "_ks_quote_photos", true);
  if (!is_array($photos) || count($photos) !== 1 || ($photos[0]["mime"] ?? "") !== "image/png" || !is_file($photos[0]["path"] ?? "")) {
    fwrite(STDERR, "HTTP upload missing private photo\n"); exit(1);
  }
  $found = true;
  wp_delete_post($post->ID, true);
  if (file_exists($photos[0]["path"])) { fwrite(STDERR, "HTTP upload cleanup failed\n"); exit(1); }
}
if (!$found) { fwrite(STDERR, "HTTP upload quote not found\n"); exit(1); }
echo "Real HTTP multipart upload and cleanup passed\n";
'


# Reject a disguised non-image over real HTTP before a quote is created.
printf 'not an image' > /tmp/ks-ci-invalid.png
BAD_STATUS="$(curl -sS -o /tmp/ks-ci-invalid-response.json -w '%{http_code}' \
  -H "X-WP-Nonce: $NONCE" \
  -F 'name=HTTP Invalid Image' -F 'email=invalid-image@example.test' \
  -F 'service=Cleaning' -F 'description=Invalid image rejection regression' \
  -F 'consent=1' -F 'photos[]=@/tmp/ks-ci-invalid.png;type=image/png' \
  "$BASE_URL/?rest_route=/ks/v1/quote-request")"
if [[ "$BAD_STATUS" != 400 ]]; then
  echo "Invalid HTTP image expected 400, got: $BAD_STATUS"
  cat /tmp/ks-ci-invalid-response.json
  exit 1
fi
"${COMPOSE[@]}" run --rm -T wpcli eval '
$invalid = get_posts(["post_type"=>"ks_quote", "post_status"=>"private", "posts_per_page"=>-1, "s"=>"HTTP Invalid Image"]);
if ($invalid) { fwrite(STDERR, "Invalid image created a quote record\n"); exit(1); }
echo "Invalid HTTP image rejected without creating a quote\n";
'


# Reject too many photo attachments over HTTP.
COUNT_STATUS="$(curl -sS -o /tmp/ks-ci-count-response.json -w '%{http_code}' \
  -H "X-WP-Nonce: $NONCE" \
  -F 'name=HTTP Excess Images' -F 'email=excess@example.test' \
  -F 'service=Cleaning' -F 'description=Excess photo count regression' \
  -F 'consent=1' \
  -F 'photos[]=@/tmp/ks-ci-photo.png;type=image/png' \
  -F 'photos[]=@/tmp/ks-ci-photo.png;type=image/png' \
  -F 'photos[]=@/tmp/ks-ci-photo.png;type=image/png' \
  -F 'photos[]=@/tmp/ks-ci-photo.png;type=image/png' \
  "$BASE_URL/?rest_route=/ks/v1/quote-request")"
if [[ "$COUNT_STATUS" != 400 ]]; then
  echo "Excess image count expected 400, got: $COUNT_STATUS"
  cat /tmp/ks-ci-count-response.json
  exit 1
fi
"${COMPOSE[@]}" run --rm -T wpcli eval '
$posts = get_posts(["post_type"=>"ks_quote", "post_status"=>"private", "posts_per_page"=>-1, "s"=>"HTTP Excess Images"]);
if ($posts) { fwrite(STDERR, "Excess photo count created a quote record\n"); exit(1); }
echo "Excess photo count rejected without creating a quote\n";
'


# Verify a real HTTP oversized image is rejected before record creation.
# The size check must run before decoding the image, so a 5 MiB+ file is sufficient.
truncate -s 5242881 /tmp/ks-ci-oversized.png
SIZE_STATUS="$(curl -sS -o /tmp/ks-ci-size-response.json -w '%{http_code}' \
  -H "X-WP-Nonce: $NONCE" \
  -F 'name=HTTP Oversized Image' -F 'email=oversized@example.test' \
  -F 'service=Cleaning' -F 'description=Oversized image rejection regression' \
  -F 'consent=1' -F 'photos[]=@/tmp/ks-ci-oversized.png;type=image/png' \
  "$BASE_URL/?rest_route=/ks/v1/quote-request")"
if [[ "$SIZE_STATUS" != 400 ]]; then
  echo "Oversized image expected 400, got: $SIZE_STATUS"
  cat /tmp/ks-ci-size-response.json
  exit 1
fi
"${COMPOSE[@]}" run --rm -T wpcli eval '
$posts = get_posts(["post_type"=>"ks_quote", "post_status"=>"private", "posts_per_page"=>-1, "s"=>"HTTP Oversized Image"]);
if ($posts) { fwrite(STDERR, "Oversized image created a quote record\n"); exit(1); }
echo "Oversized image rejected without creating a quote\n";
'


# Force the photo metadata database write to fail after a real multipart upload.
# The quote and its newly created private photo directory must both be removed.
BEFORE_DIRS="$("${COMPOSE[@]}" run --rm -T wpcli eval '
$dirs = glob("/var/ks-private/quote-*", GLOB_ONLYDIR);
echo count($dirs ?: []);
' | tr -d '\r\n')"
FAIL_STATUS="$(curl -sS -o /tmp/ks-ci-meta-failure-response.json -w '%{http_code}' \
  -H "X-WP-Nonce: $NONCE" \
  -F 'name=HTTP Metadata Failure' -F 'email=metadata-failure@example.test' \
  -F 'service=Cleaning' -F 'description=Metadata write failure regression' \
  -F 'consent=1' -F 'photos[]=@/tmp/ks-ci-photo.png;type=image/png' \
  "$BASE_URL/?rest_route=/ks/v1/quote-request")"
if [[ "$FAIL_STATUS" != 503 ]]; then
  echo "Metadata failure expected 503, got: $FAIL_STATUS"
  cat /tmp/ks-ci-meta-failure-response.json
  exit 1
fi
"${COMPOSE[@]}" run --rm -T wpcli eval '
$posts = get_posts(["post_type"=>"ks_quote", "post_status"=>"private", "posts_per_page"=>-1, "s"=>"HTTP Metadata Failure"]);
if ($posts) { fwrite(STDERR, "Metadata failure left a quote record\n"); exit(1); }
'
AFTER_DIRS="$("${COMPOSE[@]}" run --rm -T wpcli eval '
$dirs = glob("/var/ks-private/quote-*", GLOB_ONLYDIR);
echo count($dirs ?: []);
' | tr -d '\r\n')"
if [[ "$BEFORE_DIRS" != "$AFTER_DIRS" ]]; then
  echo "Metadata failure left an orphaned private photo directory: before=$BEFORE_DIRS after=$AFTER_DIRS"
  exit 1
fi
echo "Metadata write failure cleaned up quote and private photos"
