(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType
  var InspectorControls = blockEditor.InspectorControls
  var useBlockProps = blockEditor.useBlockProps
  var useSettings = blockEditor.useSettings
  var BaseControl = components.BaseControl
  var ColorPalette = components.ColorPalette
  var PanelBody = components.PanelBody
  var Placeholder = components.Placeholder
  var RangeControl = components.RangeControl
  var SelectControl = components.SelectControl
  var TextControl = components.TextControl
  var ToggleControl = components.ToggleControl
  var Fragment = element.Fragment
  var createElement = element.createElement
  var __ = i18n.__

  registerBlockType('wp-song-study/current-membership', {
    edit: function (props) {
      var attributes = props.attributes
      var setAttributes = props.setAttributes
      var paletteSettings = typeof useSettings === 'function' ? useSettings('color.palette') : [[]]
      var themePalette = (paletteSettings && paletteSettings[0]) || []
      var blockProps = useBlockProps({
        className: 'is-layout-' + (attributes.layoutWidth || 'immersive'),
        style: {
          '--pd-membership-custom-shell-text': attributes.shellTextColor || undefined,
          '--pd-membership-custom-header-background': attributes.headerBackgroundColor || undefined,
          '--pd-membership-custom-header-text': attributes.headerTextColor || undefined,
          '--pd-membership-custom-panel-background': attributes.panelBackgroundColor || undefined,
          '--pd-membership-custom-panel-text': attributes.panelTextColor || undefined,
          '--pd-membership-custom-panel-border': attributes.panelBorderColor || undefined,
          '--pd-membership-custom-field-background': attributes.fieldBackgroundColor || undefined,
          '--pd-membership-custom-field-text': attributes.fieldTextColor || undefined,
          '--pd-membership-custom-link': attributes.linkColor || undefined,
          '--pd-membership-editor-min-height': attributes.editorMinHeight
            ? String(attributes.editorMinHeight) + 'px'
            : undefined,
        },
      })

      function renderColorControl(label, attributeName) {
        return createElement(
          BaseControl,
          { label: label },
          createElement(ColorPalette, {
            colors: themePalette,
            value: attributes[attributeName] || '',
            clearable: true,
            onChange: function (value) {
              var next = {}
              next[attributeName] = value || ''
              setAttributes(next)
            },
          })
        )
      }

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: __('Current membership settings', 'wp-song-study-blocks') },
            createElement(SelectControl, {
              label: __('Layout width', 'wp-song-study-blocks'),
              value: attributes.layoutWidth || 'immersive',
              options: [
                { label: __('Default', 'wp-song-study-blocks'), value: 'default' },
                { label: __('Wide', 'wp-song-study-blocks'), value: 'wide' },
                { label: __('Immersive', 'wp-song-study-blocks'), value: 'immersive' },
              ],
              onChange: function (layoutWidth) {
                setAttributes({ layoutWidth: layoutWidth })
              },
            }),
            createElement(RangeControl, {
              label: __('Editor minimum height', 'wp-song-study-blocks'),
              value: attributes.editorMinHeight || 720,
              min: 560,
              max: 1200,
              step: 20,
              onChange: function (editorMinHeight) {
                setAttributes({ editorMinHeight: Number(editorMinHeight) || 720 })
              },
            }),
            createElement(TextControl, {
              label: __('Default target user ID', 'wp-song-study-blocks'),
              help: __(
                'Optional. Administrators can still switch to another user from the frontend view.',
                'wp-song-study-blocks'
              ),
              type: 'number',
              value: attributes.targetUserId || '',
              onChange: function (targetUserId) {
                setAttributes({ targetUserId: parseInt(targetUserId || '0', 10) || 0 })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show projects', 'wp-song-study-blocks'),
              checked: !!attributes.showProjects,
              onChange: function (showProjects) {
                setAttributes({ showProjects: showProjects })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show public preview', 'wp-song-study-blocks'),
              checked: !!attributes.showPreview,
              onChange: function (showPreview) {
                setAttributes({ showPreview: showPreview })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show WordPress profile link', 'wp-song-study-blocks'),
              checked: !!attributes.showAdminLink,
              onChange: function (showAdminLink) {
                setAttributes({ showAdminLink: showAdminLink })
              },
            }),
            createElement(TextControl, {
              label: __('Login message', 'wp-song-study-blocks'),
              value: attributes.loginMessage || '',
              onChange: function (loginMessage) {
                setAttributes({ loginMessage: loginMessage })
              },
            })
          ),
          createElement(
            PanelBody,
            {
              title: __('Membership colors', 'wp-song-study-blocks'),
              initialOpen: false,
            },
            renderColorControl(__('Shell text', 'wp-song-study-blocks'), 'shellTextColor'),
            renderColorControl(
              __('Header background', 'wp-song-study-blocks'),
              'headerBackgroundColor'
            ),
            renderColorControl(__('Header text', 'wp-song-study-blocks'), 'headerTextColor'),
            renderColorControl(
              __('Panel background', 'wp-song-study-blocks'),
              'panelBackgroundColor'
            ),
            renderColorControl(__('Panel text', 'wp-song-study-blocks'), 'panelTextColor'),
            renderColorControl(__('Panel border', 'wp-song-study-blocks'), 'panelBorderColor'),
            renderColorControl(
              __('Field background', 'wp-song-study-blocks'),
              'fieldBackgroundColor'
            ),
            renderColorControl(__('Field text', 'wp-song-study-blocks'), 'fieldTextColor'),
            renderColorControl(__('Accent / links', 'wp-song-study-blocks'), 'linkColor')
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(
            Placeholder,
            {
              label: __('Current Membership', 'wp-song-study-blocks'),
              instructions: __(
                'Displays a frontend space where the logged-in user can edit their own presskit and review their projects.',
                'wp-song-study-blocks'
              ),
            },
            createElement(
              'p',
              null,
              __(
                'Use the inspector to widen the workbench and tune shell, panel, and field colors. If you clear those colors, the frontend view falls back to the active theme.',
                'wp-song-study-blocks'
              )
            )
          )
        )
      )
    },
    save: function () {
      return null
    },
  })
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n)
