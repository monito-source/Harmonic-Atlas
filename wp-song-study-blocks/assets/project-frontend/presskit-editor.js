( function () {
  const config = window.wpssbFrontendPresskitEditor
  const container = document.getElementById('wpssb-frontend-presskit-editor')

  if (!container) {
    return
  }

  const missingPrerequisites = []

  if (!config) {
    missingPrerequisites.push('window.wpssbFrontendPresskitEditor')
  }

  if (typeof window.wp === 'undefined') {
    missingPrerequisites.push('window.wp')
  } else {
    if (!wp.element) {
      missingPrerequisites.push('wp.element')
    }

    if (!wp.blocks) {
      missingPrerequisites.push('wp.blocks')
    }

    if (!wp.blockEditor) {
      missingPrerequisites.push('wp.blockEditor')
    }
  }

  if (missingPrerequisites.length > 0) {
    container.innerHTML = `
      <div class="pd-membership-presskit-editor__fallback">
        <p>No se pudo iniciar el editor frontal del presskit.</p>
        <p>Faltan dependencias: ${missingPrerequisites.join(', ')}</p>
      </div>
    `
    return
  }

  const { Component, createElement: el, Fragment, useEffect, useMemo, useState } = wp.element
  const { __ } = wp.i18n
  const { cloneBlock, createBlock, getBlockType, parse, serialize } = wp.blocks
  const {
    BlockInspector,
    BlockCanvas,
    BlockEditorProvider,
    BlockTools,
    EditorStyles,
    Inserter,
    InspectorControls,
  } = wp.blockEditor
  const {
    Button,
    ColorPalette,
    DropZoneProvider,
    Notice,
    PanelBody,
    Popover,
    RangeControl,
    SlotFillProvider,
    ToggleControl,
  } = wp.components
  const { useDispatch, useSelect } = wp.data
  const apiFetch = wp.apiFetch
  const addFilter = wp.hooks && typeof wp.hooks.addFilter === 'function' ? wp.hooks.addFilter : null
  const createHigherOrderComponent =
    wp.compose && typeof wp.compose.createHigherOrderComponent === 'function'
      ? wp.compose.createHigherOrderComponent
      : null
  const themeColors = Array.isArray(config?.settings?.colors) ? config.settings.colors : []
  const themeGradients = Array.isArray(config?.settings?.gradients) ? config.settings.gradients : []
  const GradientPicker =
    wp.components && (
      wp.components.GradientPicker ||
      wp.components.__experimentalGradientPicker
    )
  const mediaUpload =
    (wp.blockEditor && typeof wp.blockEditor.mediaUpload === 'function' && wp.blockEditor.mediaUpload) ||
    (wp.editor && typeof wp.editor.mediaUpload === 'function' && wp.editor.mediaUpload) ||
    (wp.mediaUtils && typeof wp.mediaUtils.mediaUpload === 'function' && wp.mediaUtils.mediaUpload) ||
    undefined
  const mediaUploadSync =
    (wp.blockEditor && typeof wp.blockEditor.mediaUploadSync === 'function' && wp.blockEditor.mediaUploadSync) ||
    (wp.editor && typeof wp.editor.mediaUploadSync === 'function' && wp.editor.mediaUploadSync) ||
    (wp.mediaUtils && typeof wp.mediaUtils.mediaUploadSync === 'function' && wp.mediaUtils.mediaUploadSync) ||
    undefined

  const registerCustomGroupOverlayAttributes = () => {
    if (!addFilter) {
      return
    }

    addFilter(
      'blocks.registerBlockType',
      'wpssb/group-overlay-attributes',
      (settings, name) => {
        if (name !== 'core/group') {
          return settings
        }

        return {
          ...settings,
          attributes: {
            ...(settings.attributes || {}),
            pdOverlayEnabled: {
              type: 'boolean',
              default: false,
            },
            pdOverlayColor: {
              type: 'string',
              default: '',
            },
            pdOverlayGradient: {
              type: 'string',
              default: '',
            },
            pdOverlayOpacity: {
              type: 'number',
              default: 55,
            },
          },
        }
      }
    )
  }

  registerCustomGroupOverlayAttributes()

  if (
    !getBlockType('core/paragraph') &&
    window.wp.blockLibrary &&
    typeof window.wp.blockLibrary.registerCoreBlocks === 'function'
  ) {
    try {
      window.wp.blockLibrary.registerCoreBlocks()
    } catch (error) {
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('Could not register core blocks for frontend presskit editor', error)
      }
    }
  }

  const getGroupOverlaySettings = (attributes) => {
    const legacyOverlay = attributes?.style?.pdOverlay
    const enabled =
      typeof attributes?.pdOverlayEnabled === 'boolean'
        ? attributes.pdOverlayEnabled
        : !!legacyOverlay?.enabled

    if (!enabled) {
      return null
    }

    return {
      enabled,
      color:
        typeof attributes?.pdOverlayColor === 'string'
          ? attributes.pdOverlayColor
          : (typeof legacyOverlay?.color === 'string' ? legacyOverlay.color : ''),
      gradient:
        typeof attributes?.pdOverlayGradient === 'string'
          ? attributes.pdOverlayGradient
          : (typeof legacyOverlay?.gradient === 'string' ? legacyOverlay.gradient : ''),
      opacity:
        Number.isFinite(attributes?.pdOverlayOpacity)
          ? attributes.pdOverlayOpacity
          : (Number.isFinite(legacyOverlay?.opacity) ? legacyOverlay.opacity : 55),
    }
  }

  const hasGroupBackgroundImage = (attributes) => {
    const style = attributes?.style && typeof attributes.style === 'object' ? attributes.style : {}
    const background = style.background && typeof style.background === 'object' ? style.background : {}
    const backgroundImage = background.backgroundImage

    const hasResolvedBackgroundImage = Boolean(
      (typeof backgroundImage === 'string' && backgroundImage.trim()) ||
      (Array.isArray(backgroundImage) && backgroundImage.length > 0) ||
      (backgroundImage &&
        typeof backgroundImage === 'object' &&
        (
          (typeof backgroundImage.url === 'string' && backgroundImage.url.trim()) ||
          (typeof backgroundImage.src === 'string' && backgroundImage.src.trim()) ||
          typeof backgroundImage.id !== 'undefined'
        )) ||
      (typeof style.backgroundImage === 'string' && style.backgroundImage.trim())
    )

    return Boolean(
      hasResolvedBackgroundImage ||
      (attributes?.backgroundImage && typeof attributes.backgroundImage.url === 'string' && attributes.backgroundImage.url.trim()) ||
      (typeof attributes?.url === 'string' && attributes.url.trim())
    )
  }

  const registerCustomGroupOverlayControls = () => {
    if (!addFilter || !createHigherOrderComponent) {
      return
    }

    const withOverlayProps = (props, attributes) => {
      const overlay = getGroupOverlaySettings(attributes)

      if (!overlay) {
        return props
      }

      const nextProps = {
        ...props,
        className: [props.className, 'has-pd-custom-overlay'].filter(Boolean).join(' '),
        style: {
          ...(props.style || {}),
        },
      }

      if (overlay.color) {
        nextProps.style['--pd-group-overlay-color'] = overlay.color
      }

      if (overlay.gradient) {
        nextProps.style['--pd-group-overlay-gradient'] = overlay.gradient
      }

      if (Number.isFinite(overlay.opacity)) {
        nextProps.style['--pd-group-overlay-opacity'] = String(overlay.opacity / 100)
      }

      return nextProps
    }

    addFilter(
      'editor.BlockListBlock',
      'wpssb/group-overlay-editor-props',
      createHigherOrderComponent((BlockListBlock) => {
        return (props) => {
          if (props.name !== 'core/group') {
            return el(BlockListBlock, props)
          }

          return el(BlockListBlock, {
            ...props,
            wrapperProps: withOverlayProps(props.wrapperProps || {}, props.attributes || {}),
          })
        }
      }, 'withWpssbGroupOverlayEditorProps')
    )

    addFilter(
      'blocks.getSaveContent.extraProps',
      'wpssb/group-overlay-save-props',
      (extraProps, blockType, attributes) => {
        if (!blockType || blockType.name !== 'core/group') {
          return extraProps
        }

        return withOverlayProps(extraProps || {}, attributes || {})
      }
    )
  }

  registerCustomGroupOverlayControls()

  const renderOptional = (Component, props, children = null) => {
    if (typeof Component !== 'function') {
      return children
    }

    return el(Component, props || {}, children)
  }

  const renderPopoverSlot = () => {
    if (!Popover || typeof Popover.Slot !== 'function') {
      return null
    }

    return el(Popover.Slot, {})
  }

  const FRONTEND_EDITOR_EXTRA_STYLES = `
    :root {
      --pd-live-canvas-max-width: min(100%, 1100px);
    }

    .editor-styles-wrapper {
      color: var(--pd-membership-panel-text, inherit);
      background: transparent;
      padding: 1.25rem clamp(1rem, 2vw, 1.75rem) 3rem;
    }

    .editor-styles-wrapper .block-editor-block-list__layout.is-root-container {
      max-width: var(--pd-live-canvas-max-width);
      margin-inline: auto;
    }

    .editor-styles-wrapper .block-editor-block-list__layout.is-root-container > .wp-block {
      max-width: none !important;
    }

    .editor-styles-wrapper .is-selected > .block-editor-block-list__block-edit,
    .editor-styles-wrapper .is-multi-selected > .block-editor-block-list__block-edit {
      outline: 2px solid #2876fc;
      outline-offset: 3px;
      border-radius: 8px;
    }

    .editor-styles-wrapper .wp-block-media-text {
      align-items: start;
      gap: clamp(1rem, 2vw, 1.5rem);
      min-height: 0;
      width: 100%;
      max-width: 100%;
    }

    .editor-styles-wrapper .wp-block-media-text__media,
    .editor-styles-wrapper .wp-block-media-text__content {
      min-width: 0;
      max-width: 100%;
    }

    .editor-styles-wrapper .wp-block-media-text__media {
      align-self: start;
      overflow: hidden;
    }

    .editor-styles-wrapper .wp-block-media-text__media .components-resizable-box__container,
    .editor-styles-wrapper .wp-block-media-text__media .components-resizable-box__container img,
    .editor-styles-wrapper .wp-block-media-text__media .components-resizable-box__container video {
      max-width: 100%;
    }

    .editor-styles-wrapper .wp-block-media-text__media img,
    .editor-styles-wrapper .wp-block-media-text__media video {
      width: 100%;
      height: auto;
      max-height: none;
      object-fit: cover;
    }

    .editor-styles-wrapper .wp-block-media-text__content {
      padding: 0 !important;
    }
  `

  class PresskitEditorErrorBoundary extends Component {
    constructor(props) {
      super(props)
      this.state = { error: null }
    }

    static getDerivedStateFromError(error) {
      return { error }
    }

    componentDidCatch(error) {
      if (window.console && typeof window.console.error === 'function') {
        window.console.error('Frontend presskit editor render error', error)
      }
    }

    render() {
      if (this.state.error) {
        const message =
          (this.state.error && this.state.error.message) ||
          __('Error desconocido del editor frontal.', 'wp-song-study-blocks')

        return el(
          'div',
          { className: 'pd-membership-presskit-editor__fallback' },
          el('p', {}, __('No se pudo renderizar el editor frontal del presskit.', 'wp-song-study-blocks')),
          el('p', {}, message)
        )
      }

      return this.props.children
    }
  }

  if (apiFetch && config.restNonce && typeof apiFetch.use === 'function' && typeof apiFetch.createNonceMiddleware === 'function') {
    apiFetch.use(apiFetch.createNonceMiddleware(config.restNonce))
  }

  function PresskitEditorWorkspace(props) {
    const blockEditorDispatch = typeof useDispatch === 'function' ? useDispatch('core/block-editor') : null
    const editorState = useSelect((selectStore) => {
      const blockEditor = selectStore('core/block-editor')
      const selectedClientId = blockEditor.getSelectedBlockClientId()
      const selectedBlockClientIds = blockEditor.getSelectedBlockClientIds()
      const selectedBlockCount = blockEditor.getSelectedBlockCount()
      const selectedBlock =
        selectedClientId && typeof blockEditor.getBlock === 'function'
          ? blockEditor.getBlock(selectedClientId)
          : null
      const selectedBlocks = Array.isArray(selectedBlockClientIds) && typeof blockEditor.getBlocksByClientId === 'function'
        ? blockEditor.getBlocksByClientId(selectedBlockClientIds)
        : []
      const selectionRoots = Array.isArray(selectedBlockClientIds)
        ? selectedBlockClientIds.map((clientId) => blockEditor.getBlockRootClientId(clientId) || '')
        : []
      const uniqueSelectionRoots = Array.from(new Set(selectionRoots))
      let overlayTargetClientId = ''
      let overlayTargetBlock = null

      if (selectedBlock?.name === 'core/group') {
        overlayTargetClientId = selectedClientId || ''
        overlayTargetBlock = selectedBlock
      } else if (
        selectedClientId &&
        typeof blockEditor.getBlockParents === 'function' &&
        typeof blockEditor.getBlock === 'function'
      ) {
        const parentIds = blockEditor.getBlockParents(selectedClientId)

        if (Array.isArray(parentIds)) {
          const nearestGroupParentId = parentIds.find((parentId) => {
            const parentBlock = blockEditor.getBlock(parentId)
            return parentBlock?.name === 'core/group'
          })

          if (nearestGroupParentId) {
            overlayTargetClientId = nearestGroupParentId
            overlayTargetBlock = blockEditor.getBlock(nearestGroupParentId)
          }
        }
      }

      return {
        selectedClientId,
        selectedRootClientId: selectedClientId ? blockEditor.getBlockRootClientId(selectedClientId) : undefined,
        selectedBlockClientIds: Array.isArray(selectedBlockClientIds) ? selectedBlockClientIds : [],
        selectedBlocks: Array.isArray(selectedBlocks) ? selectedBlocks : [],
        selectedBlockCount: Number.isFinite(selectedBlockCount) ? selectedBlockCount : 0,
        canNativeGroupSelection: uniqueSelectionRoots.length <= 1,
        selectedBlock,
        overlayTargetClientId,
        overlayTargetBlock,
      }
    }, [])

    const handleGroupNativeSelection = () => {
      if (
        !getBlockType('core/group') ||
        editorState.selectedBlockCount < 1 ||
        !editorState.canNativeGroupSelection ||
        !blockEditorDispatch
      ) {
        return
      }

      const selectedBlocks = editorState.selectedBlocks.filter(Boolean).map((block) => cloneBlock(block))

      if (selectedBlocks.length === 0) {
        return
      }

      const groupBlock = createBlock('core/group', {}, selectedBlocks)

      blockEditorDispatch.replaceBlocks(
        editorState.selectedBlockClientIds,
        groupBlock
      )
      blockEditorDispatch.selectBlock(groupBlock.clientId)
    }

    const selectedIsGroup = editorState.overlayTargetBlock?.name === 'core/group'
    const selectedGroupHasBackgroundImage =
      selectedIsGroup && hasGroupBackgroundImage(editorState.overlayTargetBlock?.attributes || {})
    const selectedGroupOverlay = selectedIsGroup
      ? getGroupOverlaySettings(editorState.overlayTargetBlock?.attributes || {})
      : null
    const updateSelectedGroupOverlay = (patch) => {
      if (!selectedIsGroup || !editorState.overlayTargetClientId || !blockEditorDispatch) {
        return
      }

      const selectedAttributes = editorState.overlayTargetBlock?.attributes || {}
      const nextOverlay = {
        enabled: !!selectedGroupOverlay?.enabled,
        color: selectedGroupOverlay?.color || '',
        gradient: selectedGroupOverlay?.gradient || '',
        opacity: Number.isFinite(selectedGroupOverlay?.opacity) ? selectedGroupOverlay.opacity : 55,
        ...patch,
      }

      const currentStyle =
        selectedAttributes && typeof selectedAttributes.style === 'object'
          ? { ...selectedAttributes.style }
          : null

      if (currentStyle && typeof currentStyle.pdOverlay !== 'undefined') {
        delete currentStyle.pdOverlay
      }

      const nextAttributes = {
        pdOverlayEnabled: !!nextOverlay.enabled,
        pdOverlayColor: typeof nextOverlay.color === 'string' ? nextOverlay.color : '',
        pdOverlayGradient: typeof nextOverlay.gradient === 'string' ? nextOverlay.gradient : '',
        pdOverlayOpacity: Number.isFinite(nextOverlay.opacity) ? nextOverlay.opacity : 55,
      }

      if (currentStyle) {
        nextAttributes.style = Object.keys(currentStyle).length > 0 ? currentStyle : undefined
      }

      blockEditorDispatch.updateBlockAttributes(editorState.overlayTargetClientId, nextAttributes)
    }

    return el(
      Fragment,
      {},
      el(
        'div',
        { className: 'pd-membership-presskit-editor__toolbar' },
        el('div', { className: 'pd-membership-presskit-editor__tabs' },
          el(
            'p',
            { className: 'pd-membership-presskit-editor__live-label' },
            __('Edición con vista en vivo', 'wp-song-study-blocks')
          ),
          el(
            'p',
            { className: 'pd-membership-presskit-editor__toolbar-status' },
            editorState.selectedBlockCount > 0
              ? `${editorState.selectedBlockCount} ${__('bloques seleccionados', 'wp-song-study-blocks')}`
              : __('Selecciona un bloque en el lienzo para editarlo.', 'wp-song-study-blocks')
          )
        ),
        el(
          'div',
          { className: 'pd-membership-presskit-editor__actions' },
          el(
            'p',
            { className: 'pd-membership-presskit-editor__composition-hint' },
            __('Usa Insertar bloque para añadir Grupo, Columnas, Media y texto y otras composiciones nativas de WordPress.', 'wp-song-study-blocks')
          ),
          el(
            Button,
            {
              variant: 'secondary',
              disabled:
                !getBlockType('core/group') ||
                editorState.selectedBlockCount < 1 ||
                !editorState.canNativeGroupSelection ||
                !blockEditorDispatch,
              onClick: handleGroupNativeSelection
            },
            editorState.selectedBlockCount > 1
              ? __('Agrupar selección nativa', 'wp-song-study-blocks')
              : __('Agrupar bloque activo', 'wp-song-study-blocks')
          ),
          el(Inserter, {
            rootClientId: null,
            isAppender: false,
            renderToggle: ({ onToggle, disabled }) =>
              el(
                Button,
                {
                  variant: 'secondary',
                  disabled,
                  onClick: onToggle
                },
                config.inserterLabel || __('Insertar bloque', 'wp-song-study-blocks')
              )
          }),
          el(
            Button,
            {
              variant: 'primary',
              isBusy: props.isSaving,
              disabled: props.isSaving,
              onClick: props.onSave
            },
            config.saveLabel || __('Guardar presskit', 'wp-song-study-blocks')
          )
        )
      ),
      el(
        BlockTools,
        {},
        el(
          'div',
          { className: 'pd-membership-presskit-editor__workspace' },
          el(
            'div',
            { className: 'pd-membership-presskit-editor__canvas-shell' },
            !editorState.canNativeGroupSelection && editorState.selectedBlockCount > 1
              ? el(
                  'p',
                  { className: 'pd-membership-presskit-editor__selection-warning' },
                  __('La agrupación nativa requiere bloques hermanos dentro del mismo nivel.', 'wp-song-study-blocks')
                )
              : null,
            typeof BlockCanvas === 'function'
              ? el(BlockCanvas, {
                  height: '100%',
                  styles: Array.isArray(props.settings.styles) ? props.settings.styles : [],
                  className: 'pd-membership-presskit-editor__canvas'
                })
              : el(
                  'div',
                  { className: 'pd-membership-presskit-editor__fallback' },
                  el('p', {}, __('No se pudo cargar el canvas nativo del editor.', 'wp-song-study-blocks'))
            )
          ),
          el(
            'aside',
            { className: 'pd-membership-presskit-editor__sidebar' },
            selectedIsGroup
              ? el(
                  PanelBody,
                  {
                    title: __('Overlay del fondo', 'pertenencia-digital'),
                    initialOpen: true,
                  },
                  el(
                    Fragment,
                    {},
                    !selectedGroupHasBackgroundImage
                      ? el(
                          'p',
                          { className: 'pd-membership-presskit-editor__sidebar-empty' },
                          __('Este grupo aún no muestra una imagen de fondo detectable. Puedes configurar el overlay y se verá cuando la imagen esté presente.', 'pertenencia-digital')
                        )
                      : null,
                    el(ToggleControl, {
                      label: __('Activar overlay personalizado', 'pertenencia-digital'),
                      checked: !!selectedGroupOverlay?.enabled,
                      onChange: (value) =>
                        updateSelectedGroupOverlay({
                          enabled: !!value,
                        }),
                    }),
                    selectedGroupOverlay?.enabled
                      ? el(
                          Fragment,
                          {},
                          el(RangeControl, {
                            label: __('Opacidad del overlay', 'pertenencia-digital'),
                            value: Number.isFinite(selectedGroupOverlay?.opacity)
                              ? selectedGroupOverlay.opacity
                              : 55,
                            min: 0,
                            max: 100,
                            step: 1,
                            onChange: (value) =>
                              updateSelectedGroupOverlay({
                                opacity: Number.isFinite(value) ? value : 55,
                              }),
                          }),
                          el(
                            'div',
                            { className: 'pd-membership-presskit-editor__overlay-control' },
                            el(
                              'p',
                              { className: 'pd-membership-presskit-editor__overlay-label' },
                              __('Color base del overlay', 'pertenencia-digital')
                            ),
                            el(ColorPalette, {
                              colors: themeColors,
                              value: selectedGroupOverlay?.color || '',
                              clearable: true,
                              onChange: (value) =>
                                updateSelectedGroupOverlay({
                                  color: value || '',
                                }),
                            })
                          ),
                                typeof GradientPicker === 'function'
                                  ? el(
                                      'div',
                                      { className: 'pd-membership-presskit-editor__overlay-control' },
                                el(
                                  'p',
                                  { className: 'pd-membership-presskit-editor__overlay-label' },
                                  __('Degradado del overlay', 'pertenencia-digital')
                                      ),
                                      el(GradientPicker, {
                                        gradients: themeGradients,
                                        value: selectedGroupOverlay?.gradient || undefined,
                                        clearable: true,
                                        onChange: (value) =>
                                          updateSelectedGroupOverlay({
                                            gradient: value || '',
                                          }),
                                })
                              )
                            : null
                        )
                      : null
                  )
                )
              : null,
            editorState.selectedBlock
              ? el(
                  Fragment,
                  {},
                  el(
                    'p',
                    { className: 'pd-membership-presskit-editor__sidebar-label' },
                    __('Ajustes del bloque', 'wp-song-study-blocks')
                  ),
                  typeof BlockInspector === 'function'
                    ? el(
                        'div',
                        { className: 'pd-membership-presskit-editor__inspector-slot' },
                        el(BlockInspector, {})
                      )
                    : null,
                  typeof BlockInspector !== 'function' && InspectorControls && typeof InspectorControls.Slot === 'function'
                    ? el(
                        Fragment,
                        {},
                        el(
                          'p',
                          { className: 'pd-membership-presskit-editor__sidebar-label pd-membership-presskit-editor__sidebar-label--secondary' },
                          __('Estilo y colores', 'wp-song-study-blocks')
                        ),
                        el(
                          'div',
                          { className: 'pd-membership-presskit-editor__inspector-slot pd-membership-presskit-editor__inspector-slot--styles' },
                          el(InspectorControls.Slot, {}),
                          el(InspectorControls.Slot, { group: 'styles' }),
                          el(InspectorControls.Slot, { group: 'color' }),
                          el(InspectorControls.Slot, { group: 'typography' }),
                          el(InspectorControls.Slot, { group: 'dimensions' }),
                          el(InspectorControls.Slot, { group: 'border' })
                        )
                      )
                    : null
                )
              : el(
                  'p',
                  { className: 'pd-membership-presskit-editor__sidebar-empty' },
                  __('Selecciona un bloque para editar sus estilos y ajustes.', 'wp-song-study-blocks')
                )
          )
        ),
        renderPopoverSlot()
      )
    )
  }

  function PresskitWorkbench() {
    const [blocks, setBlocks] = useState(parse(config.content || ''))
    const [isSaving, setIsSaving] = useState(false)
    const [notice, setNotice] = useState(null)

    useEffect(() => {
      if (blocks.length > 0 || !getBlockType('core/paragraph')) {
        return
      }

      setBlocks([
        createBlock('core/heading', {
          level: 2,
          placeholder: __('Titulo del presskit', 'wp-song-study-blocks')
        }),
        createBlock('core/paragraph', {
          placeholder: __('Escribe aqui tu presentacion publica…', 'wp-song-study-blocks')
        })
      ])
    }, [blocks])

    const settings = useMemo(
      () =>
        Object.assign({}, config.settings || {}, {
          hasFixedToolbar: false,
          focusMode: false,
          templateLock: false,
          mediaUpload,
          mediaUploadSync,
          styles: [
            ...((config.settings && Array.isArray(config.settings.styles)) ? config.settings.styles : []),
            { css: FRONTEND_EDITOR_EXTRA_STYLES }
          ]
        }),
      []
    )

    const handleBlocksChange = (nextBlocks) => {
      setBlocks(nextBlocks)
    }

    const handleSave = async () => {
      if (!apiFetch || isSaving) {
        return
      }

      setIsSaving(true)
      setNotice(null)

      try {
        const serializedContent = serialize(blocks)
        await apiFetch({
          path: `${config.restPath}/${config.postId}`,
          method: 'POST',
          data: {
            content: serializedContent
          }
        })
        setNotice({
          status: 'success',
          message:
            (config.savedLabel || __('Presskit actualizado.', 'wp-song-study-blocks')) +
            ' ' +
            __('La vista de edición ya refleja el resultado en vivo.', 'wp-song-study-blocks')
        })
      } catch (error) {
        const message =
          (error && error.message) ||
          config.errorLabel ||
          __('No se pudo guardar el presskit.', 'wp-song-study-blocks')

        setNotice({
          status: 'error',
          message
        })
      } finally {
        setIsSaving(false)
      }
    }

    return el(
      'div',
      { className: 'pd-membership-presskit-editor' },
      notice
        ? el(Notice, {
            className: 'pd-membership-presskit-editor__notice',
            status: notice.status,
            isDismissible: true,
            onRemove: () => setNotice(null)
          }, notice.message)
        : null,
      renderOptional(
        DropZoneProvider,
        {},
        renderOptional(
          SlotFillProvider,
          {},
          el(
            BlockEditorProvider,
            {
              value: blocks,
              onInput: handleBlocksChange,
              onChange: handleBlocksChange,
              settings
            },
            typeof EditorStyles === 'function'
              ? el(EditorStyles, {
                  styles: Array.isArray(settings.styles) ? settings.styles : []
                })
              : null,
            el(PresskitEditorWorkspace, {
              settings,
              isSaving,
              onSave: handleSave,
            })
          )
        )
      )
    )
  }

  try {
    if (typeof wp.element.createRoot === 'function') {
      wp.element.createRoot(container).render(
        el(
          PresskitEditorErrorBoundary,
          {},
          el(PresskitWorkbench)
        )
      )
      return
    }

    wp.element.render(
      el(
        PresskitEditorErrorBoundary,
        {},
        el(PresskitWorkbench)
      ),
      container
    )
  } catch (error) {
    container.innerHTML =
      '<div class="pd-membership-presskit-editor__fallback"><p>No se pudo cargar el editor frontal del presskit.</p></div>'

    if (window.console && typeof window.console.error === 'function') {
      window.console.error('Frontend presskit editor failed to mount', error)
    }
  }
} )()
