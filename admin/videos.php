<?php
declare(strict_types=1);

require __DIR__ . '/../backend/auth.php';
require __DIR__ . '/../backend/videos.php';
require __DIR__ . '/../backend/admin-layout.php';

$admin = require_admin();
$limit = video_upload_limit();

/**
 * Back to the list. The upload form posts in the background (to show a progress
 * bar) and then reloads this page itself, so it gets a bare "done" instead of a
 * redirect: following a redirect in the background would use up the flash message.
 */
function back_to_videos(string $hash = ''): void
{
    if (($_SERVER['HTTP_X_UPLOAD'] ?? '') === '1') {
        http_response_code(204);
        exit;
    }
    redirect('videos' . $hash);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // A video bigger than the server's post_max_size arrives with no form fields at all
    // (not even the security token), so say what happened instead of "session expired".
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('That video is larger than ' . format_mb($limit) . ', which is the most this server accepts. Please trim it or send a shorter clip.', 'error');
        back_to_videos();
    }
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    try {
        if ($action === 'upload') {
            $f = video_fields($_POST);
            $stored = store_video_upload($_FILES['video'] ?? [], $_FILES['poster'] ?? null);
            $duration = (int) ($_POST['duration'] ?? 0);
            $first = (int) db()->query('SELECT COALESCE(MIN(sort_order), 10) FROM video_testimonials')->fetchColumn();
            db()->prepare('INSERT INTO video_testimonials (client_name, service, caption, video_path, poster_path, mime, size_bytes, duration_seconds, consent_confirmed, sort_order)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$f['client_name'], $f['service'], $f['caption'], $stored['video'], $stored['poster'], $stored['mime'], $stored['size'],
                           $duration > 0 && $duration < 3600 ? $duration : null, $f['consent'], $first - 10]);
            flash("Video from {$f['client_name']} published. It now shows first in “Client Stories” on the Home page.");
        } elseif ($action === 'update') {
            $f = video_fields($_POST);
            db()->prepare('UPDATE video_testimonials SET client_name = ?, service = ?, caption = ?, consent_confirmed = ? WHERE id = ?')
                ->execute([$f['client_name'], $f['service'], $f['caption'], $f['consent'], $id]);
            flash("Video from {$f['client_name']} updated.");
        } elseif ($action === 'toggle') {
            db()->prepare('UPDATE video_testimonials SET is_visible = 1 - is_visible WHERE id = ?')->execute([$id]);
            flash('Visibility updated.');
        } elseif ($action === 'move') {
            $ids = array_map('intval', array_column(video_testimonials(), 'id'));
            $pos = array_search($id, $ids, true);
            $swap = ($_POST['dir'] ?? '') === 'up' ? $pos - 1 : $pos + 1;
            if ($pos !== false && isset($ids[$swap])) {
                [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
                $upd = db()->prepare('UPDATE video_testimonials SET sort_order = ? WHERE id = ?');
                foreach ($ids as $i => $vid) $upd->execute([($i + 1) * 10, $vid]);
            }
            redirect('videos#v-' . $id);
        } elseif ($action === 'delete') {
            $s = db()->prepare('SELECT video_path, poster_path, client_name FROM video_testimonials WHERE id = ?');
            $s->execute([$id]);
            if ($row = $s->fetch()) {
                db()->prepare('DELETE FROM video_testimonials WHERE id = ?')->execute([$id]);
                delete_video_file($row['video_path']);
                delete_video_file($row['poster_path']);
                flash("Video from {$row['client_name']} removed.");
            }
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
    }
    back_to_videos();
}

$items = video_testimonials();
$published = count(array_filter($items, fn($v) => $v['is_visible'] && $v['consent_confirmed']));

function video_service_options(string $current): string
{
    $html = '<option value="">Choose…</option>';
    foreach (TESTIMONIAL_SERVICES as $k => $label) {
        $html .= '<option value="' . e($k) . '"' . ($k === $current ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

admin_header('Video Testimonials', $admin, 'videos');
?>
    <div class="page__head">
      <div>
        <h1 class="page__title">Video testimonials</h1>
        <p class="page__sub"><?= $published
            ? $published . ' published in “Client Stories” on the Home page. The order here is the order on the website.'
            : 'None published yet, so the “Client Stories” section is hidden on the website. It appears as soon as you publish one.' ?></p>
      </div>
      <a class="btn" href="../#client-stories" target="_blank" rel="noopener">View on site ↗</a>
    </div>

    <details class="card upload" <?= $items ? '' : 'open' ?>>
      <summary class="upload__summary">+ Add a video</summary>
      <form method="post" enctype="multipart/form-data" class="form form--grid upload__form" id="video-upload" data-limit="<?= $limit ?>" data-limit-label="<?= e(format_mb($limit)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="duration" value="">
        <div class="field field--full">
          <label for="v-video">Video</label>
          <input id="v-video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.m4v,.webm" required aria-describedby="v-video-h">
          <small id="v-video-h" class="field__help">MP4, MOV or WebM up to <?= e(format_mb($limit)) ?>. A video saved from WhatsApp or a phone works. Short clips (under 2 minutes) load fastest for visitors.</small>
          <input type="file" name="poster" accept="image/jpeg" hidden tabindex="-1" aria-hidden="true">
          <div class="video-preview" hidden>
            <video class="video-preview__player" controls playsinline muted preload="metadata"></video>
            <p class="hint video-preview__info"></p>
          </div>
        </div>
        <div class="field"><label for="v-name">Client name, as they'd like it shown</label>
          <input id="v-name" name="client_name" maxlength="80" required placeholder="e.g. Adaeze O."></div>
        <div class="field"><label for="v-service">Service</label>
          <select id="v-service" name="service" required><?= video_service_options('') ?></select></div>
        <div class="field field--full"><label for="v-caption">One-line quote or caption <small>(optional)</small></label>
          <input id="v-caption" name="caption" maxlength="<?= VIDEO_MAX_CAPTION ?>" placeholder="e.g. “My wedding gown was everything I dreamed of.”"></div>
        <label class="check field--full">
          <input type="checkbox" name="consent" value="1" required>
          <span>The client agreed to have this video, their name and their words shown on the website.</span>
        </label>
        <div class="field--full upload-progress" hidden>
          <div class="upload-progress__bar"><span></span></div>
          <p class="hint upload-progress__text" aria-live="polite"></p>
        </div>
        <div class="field--full"><button type="submit" class="btn btn--primary">Upload video</button></div>
      </form>
    </details>

<?php if (!$items): ?>
    <div class="empty"><p class="empty__title">No videos yet.</p>
      <p>Ask happy clients to record a short video about their outfit, fitting or training, and their permission to share it.</p></div>
<?php else: ?>
    <ol class="glist glist--videos">
<?php foreach ($items as $n => $v): $vid = (int) $v['id']; ?>
      <li class="gcard<?= $v['is_visible'] ? '' : ' is-hidden' ?>" id="v-<?= $vid ?>">
        <div class="gcard__thumb gcard__thumb--video">
          <video src="../<?= e($v['video_path']) ?>"<?= $v['poster_path'] ? ' poster="../' . e($v['poster_path']) . '"' : '' ?> controls playsinline preload="none"></video>
          <span class="gcard__pos"><?= $n + 1 ?></span>
        </div>
        <div class="gcard__body">
          <p class="gcard__title"><?= e($v['client_name']) ?></p>
          <p class="gcard__meta">
            <?= e(TESTIMONIAL_SERVICES[$v['service']] ?? $v['service']) ?>
            <?php if ($v['duration_seconds']): ?> · <?= e(format_duration((int) $v['duration_seconds'])) ?><?php endif; ?>
            · <?= e(format_mb((int) $v['size_bytes'])) ?>
            <?php if (!$v['is_visible']): ?><span class="badge badge--closed">Hidden</span><?php endif; ?>
          </p>
<?php if ($v['caption'] !== ''): ?>
          <p class="gcard__caption">“<?= e($v['caption']) ?>”</p>
<?php endif; ?>
          <div class="gcard__actions">
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="id" value="<?= $vid ?>">
              <button class="btn btn--icon" name="dir" value="up" <?= $n === 0 ? 'disabled' : '' ?> aria-label="Move video from <?= e($v['client_name']) ?> earlier">↑</button>
              <button class="btn btn--icon" name="dir" value="down" <?= $n === count($items) - 1 ? 'disabled' : '' ?> aria-label="Move video from <?= e($v['client_name']) ?> later">↓</button>
            </form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $vid ?>">
              <button class="btn btn--sm"><?= $v['is_visible'] ? 'Hide' : 'Show' ?></button>
            </form>
          </div>
          <details class="gcard__edit">
            <summary>Edit</summary>
            <form method="post" class="form">
              <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= $vid ?>">
              <label class="field"><span>Client name</span><input name="client_name" value="<?= e($v['client_name']) ?>" maxlength="80" required></label>
              <label class="field"><span>Service</span><select name="service" required><?= video_service_options($v['service']) ?></select></label>
              <label class="field"><span>Quote or caption</span><input name="caption" value="<?= e($v['caption']) ?>" maxlength="<?= VIDEO_MAX_CAPTION ?>"></label>
              <label class="check"><input type="checkbox" name="consent" value="1" required <?= $v['consent_confirmed'] ? 'checked' : '' ?>> <span>Client agreed to be shown</span></label>
              <button class="btn btn--primary btn--block">Save changes</button>
            </form>
            <form method="post" class="confirm-delete">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $vid ?>">
              <label class="check"><input type="checkbox" required> <span>Yes, remove this video</span></label>
              <button class="btn btn--danger btn--block">Remove video</button>
            </form>
          </details>
        </div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
    <script src="videos.js" defer></script>
<?php
admin_footer();
