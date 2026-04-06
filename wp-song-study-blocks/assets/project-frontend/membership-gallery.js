jQuery(function ($) {
  const fields = $('.pd-membership-gallery-field')

  if (!fields.length || typeof wp === 'undefined' || !wp.media) {
    return
  }

  const parseIds = (raw) => {
    if (!raw) {
      return []
    }

    return String(raw)
      .split(',')
      .map((id) => parseInt(id, 10))
      .filter((id) => Number.isFinite(id) && id > 0)
  }

  fields.each(function () {
    const field = $(this)
    const input = field.find('input[name="pd_colaborador_galeria"]')
    const preview = field.find('.pd-membership-gallery-preview')
    const selectBtn = field.find('.pd-membership-gallery-select')
    const clearBtn = field.find('.pd-membership-gallery-clear')

    const renderPreview = (ids) => {
      preview.empty()

      if (!ids.length) {
        preview.append(
          $('<p />', {
            class: 'pd-membership-gallery-preview__empty',
            text: 'Todavía no hay imágenes seleccionadas.'
          })
        )
        return
      }

      ids.forEach((id) => {
        const attachment = wp.media.attachment(id)
        attachment.fetch().then(() => {
          const sizes = attachment.get('sizes') || {}
          const url = (sizes.thumbnail && sizes.thumbnail.url) || attachment.get('url')

          if (!url) {
            return
          }

          const figure = $('<figure />', { class: 'pd-membership-gallery-preview__item' })
          const image = $('<img />', {
            src: url,
            alt: ''
          })

          figure.append(image)
          preview.append(figure)
        })
      })
    }

    renderPreview(parseIds(input.val()))

    selectBtn.on('click', function (event) {
      event.preventDefault()

      const frame = wp.media({
        title: 'Selecciona imágenes para la galería',
        button: { text: 'Usar imágenes' },
        multiple: true
      })

      frame.on('select', function () {
        const selection = frame.state().get('selection')
        const ids = selection.map((attachment) => attachment.id)
        input.val(ids.join(','))
        renderPreview(ids)
      })

      frame.open()
    })

    clearBtn.on('click', function (event) {
      event.preventDefault()
      input.val('')
      renderPreview([])
    })
  })
})
