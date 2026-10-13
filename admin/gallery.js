/* Admin gallery: preview the chosen photo and shrink it in the browser before
   upload (max 1600px, WebP). Faster uploads on mobile data, and works even if
   the server has no image library. If anything fails, the original is sent. */
(() => {
  'use strict';
  const MAX_EDGE = 1600;

  document.querySelectorAll('form[data-resize]').forEach((form) => {
    const input = form.querySelector('input[type="file"]');
    const preview = form.querySelector('.upload__preview');
    const submit = form.querySelector('button[type="submit"]');
    let ready = Promise.resolve();

    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file || !file.type.startsWith('image/')) return;
      submit.disabled = true;
      submit.textContent = 'Preparing photo…';

      ready = (async () => {
        try {
          const bitmap = await createImageBitmap(file);
          const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
          const canvas = document.createElement('canvas');
          canvas.width = Math.round(bitmap.width * scale);
          canvas.height = Math.round(bitmap.height * scale);
          canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);

          const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', 0.85));
          if (blob && blob.type === 'image/webp' && typeof DataTransfer === 'function') {
            const dt = new DataTransfer();
            dt.items.add(new File([blob], file.name.replace(/\.\w+$/, '') + '.webp', { type: 'image/webp' }));
            input.files = dt.files;
          }
          // Small preview (data: URL is allowed by the admin's security policy)
          const thumb = document.createElement('canvas');
          const t = Math.min(1, 240 / Math.max(canvas.width, canvas.height));
          thumb.width = Math.round(canvas.width * t);
          thumb.height = Math.round(canvas.height * t);
          thumb.getContext('2d').drawImage(canvas, 0, 0, thumb.width, thumb.height);
          preview.src = thumb.toDataURL('image/jpeg', 0.8);
          preview.hidden = false;
        } catch (err) {
          console.info('In-browser resize unavailable; uploading original.', err);
        } finally {
          submit.disabled = false;
          submit.textContent = 'Upload design';
        }
      })();
    });

    form.addEventListener('submit', async (e) => {
      if (form.dataset.sending) return;
      e.preventDefault();
      await ready;
      form.dataset.sending = '1';
      submit.disabled = true;
      submit.textContent = 'Uploading…';
      form.submit();
    });
  });
})();
