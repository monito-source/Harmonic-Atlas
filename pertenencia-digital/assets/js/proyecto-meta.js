jQuery(function ($) {
  const metaBox = $('.pd-proyecto-galeria-meta');
  if (!metaBox.length || typeof wp === 'undefined' || !wp.media) {
    return;
  }

  const input = metaBox.find('input[name="pd_proyecto_galeria"]');
  const preview = metaBox.find('.pd-proyecto-galeria-preview');
  const selectBtn = metaBox.find('.pd-proyecto-galeria-select');
  const clearBtn = metaBox.find('.pd-proyecto-galeria-clear');

  const parseIds = (raw) => {
    if (!raw) {
      return [];
    }
    return raw
      .split(',')
      .map((id) => parseInt(id, 10))
      .filter((id) => Number.isFinite(id) && id > 0);
  };

  const renderPreview = (ids) => {
    preview.empty();
    if (!ids.length) {
      return;
    }

    ids.forEach((id) => {
      const attachment = wp.media.attachment(id);
      attachment.fetch().then(() => {
        const sizes = attachment.get('sizes') || {};
        const url = (sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url');
        if (!url) {
          return;
        }
        const img = $('<img />', {
          src: url,
          alt: '',
          css: {
            width: '90px',
            height: '90px',
            objectFit: 'cover',
            borderRadius: '8px',
            boxShadow: '0 6px 18px rgba(15, 23, 42, 0.12)'
          }
        });
        preview.append(img);
      });
    });
  };

  renderPreview(parseIds(input.val()));

  selectBtn.on('click', function (event) {
    event.preventDefault();

    const frame = wp.media({
      title: 'Selecciona imágenes para la galería',
      button: { text: 'Usar imágenes' },
      multiple: true
    });

    frame.on('select', function () {
      const selection = frame.state().get('selection');
      const ids = selection.map((attachment) => attachment.id);
      input.val(ids.join(','));
      renderPreview(ids);
    });

    frame.open();
  });

  clearBtn.on('click', function (event) {
    event.preventDefault();
    input.val('');
    preview.empty();
  });
});
