/* Admin video testimonials: preview the chosen video, check its size, grab a
   cover image from it, and upload with a progress bar (videos are big on
   mobile data). If anything here fails, the form still submits normally. */
(() => {
  'use strict';
  const form = document.getElementById('video-upload');
  if (!form) return;

  const input = form.querySelector('input[name="video"]');
  const posterInput = form.querySelector('input[name="poster"]');
  const durationInput = form.querySelector('input[name="duration"]');
  const preview = form.querySelector('.video-preview');
  const player = form.querySelector('.video-preview__player');
  const info = form.querySelector('.video-preview__info');
  const progress = form.querySelector('.upload-progress');
  const bar = form.querySelector('.upload-progress__bar span');
  const progressText = form.querySelector('.upload-progress__text');
  const submit = form.querySelector('button[type="submit"]');
  const limit = Number(form.dataset.limit) || Infinity;
  const mb = (n) => (n / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB';
  let objectUrl = null;
  let ready = Promise.resolve();

  /** Draw one frame (about 1s in) to a JPEG cover image. */
  function grabCover() {
    return new Promise((resolve) => {
      const done = () => {
        try {
          const scale = Math.min(1, 900 / Math.max(player.videoWidth, player.videoHeight));
          const canvas = document.createElement('canvas');
          canvas.width = Math.round(player.videoWidth * scale);
          canvas.height = Math.round(player.videoHeight * scale);
          canvas.getContext('2d').drawImage(player, 0, 0, canvas.width, canvas.height);
          canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.82);
        } catch { resolve(null); }
      };
      player.addEventListener('seeked', done, { once: true });
      player.currentTime = Math.min(1, (player.duration || 2) / 2);
      setTimeout(() => resolve(null), 6000);       // some formats can't be drawn; the site then shows a plain cover
    });
  }

  input.addEventListener('change', () => {
    const file = input.files[0];
    posterInput.value = '';
    durationInput.value = '';
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    if (!file) { preview.hidden = true; return; }

    if (file.size > limit) {
      input.setCustomValidity(`This video is ${mb(file.size)}. The most this server accepts is ${form.dataset.limitLabel}. Please trim it or send a shorter clip.`);
      input.reportValidity();
      preview.hidden = true;
      return;
    }
    input.setCustomValidity('');

    objectUrl = URL.createObjectURL(file);
    player.src = objectUrl;
    preview.hidden = false;
    info.textContent = `${file.name} · ${mb(file.size)}`;
    submit.disabled = true;
    submit.textContent = 'Preparing video…';

    ready = new Promise((resolve) => {
      const finish = () => { submit.disabled = false; submit.textContent = 'Upload video'; resolve(); };
      player.addEventListener('error', () => {
        info.textContent = `${file.name} · ${mb(file.size)} · this browser can't preview it, but it will still upload.`;
        finish();
      }, { once: true });
      player.addEventListener('loadeddata', async () => {
        const secs = Math.round(player.duration || 0);
        if (secs) {
          durationInput.value = secs;
          info.textContent = `${file.name} · ${mb(file.size)} · ${Math.floor(secs / 60)}:${String(secs % 60).padStart(2, '0')}`;
        }
        const blob = await grabCover();
        if (blob && typeof DataTransfer === 'function') {
          const dt = new DataTransfer();
          dt.items.add(new File([blob], 'cover.jpg', { type: 'image/jpeg' }));
          posterInput.files = dt.files;
        }
        player.currentTime = 0;
        finish();
      }, { once: true });
    });
  });

  form.addEventListener('submit', async (e) => {
    if (form.dataset.sending) { e.preventDefault(); return; }
    if (typeof XMLHttpRequest !== 'function' || typeof FormData !== 'function') return;  // plain submit
    e.preventDefault();
    await ready;
    form.dataset.sending = '1';
    submit.disabled = true;
    submit.textContent = 'Uploading…';
    progress.hidden = false;

    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.getAttribute('action') || location.href);
    xhr.setRequestHeader('X-Upload', '1');      // the server then leaves its message for the page reload below
    xhr.upload.addEventListener('progress', (ev) => {
      if (!ev.lengthComputable) return;
      const pct = Math.round((ev.loaded / ev.total) * 100);
      bar.style.width = pct + '%';
      progressText.textContent = pct < 100 ? `Uploading… ${pct}% (${mb(ev.loaded)} of ${mb(ev.total)}). Keep this page open.` : 'Saving…';
    });
    // The server answers with the videos page (after a redirect), carrying the success or error message.
    xhr.addEventListener('load', () => {
      if (xhr.status === 413) {
        progressText.textContent = `The server refused this video because it is too large. The most it accepts is ${form.dataset.limitLabel}. Please trim it or send a shorter clip.`;
        submit.disabled = false;
        submit.textContent = 'Upload video';
        delete form.dataset.sending;
        return;
      }
      location.href = 'videos';
    });
    xhr.addEventListener('error', () => {
      progressText.textContent = 'The upload was interrupted. Check your connection and try again.';
      submit.disabled = false;
      submit.textContent = 'Upload video';
      delete form.dataset.sending;
    });
    xhr.send(new FormData(form));
  });
})();
