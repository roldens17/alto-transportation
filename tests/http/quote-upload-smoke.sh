#!/usr/bin/env bash
set -euo pipefail
COMPOSE=(docker compose --env-file .env -f docker-compose.klickspark.yml)
BASE_URL=http://127.0.0.1:8080
# Real HTTP request exercises PHP's is_uploaded_file() and move_uploaded_file().
NONCE="$("${COMPOSE[@]}" run --rm -T wpcli eval 'echo wp_create_nonce("wp_rest");' | tr -d '\r\n')"
test -n "$NONCE"
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
