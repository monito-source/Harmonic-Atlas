(function (blocks, blockEditor, components, element, i18n) {
  function normalizePositiveInteger(value, fallback) {
    var parsed = parseInt(value, 10)
    return Number.isNaN(parsed) || parsed < 1 ? fallback : parsed
  }

  var registerBlockType = blocks.registerBlockType
  var InspectorControls = blockEditor.InspectorControls
  var useBlockProps = blockEditor.useBlockProps
  var PanelBody = components.PanelBody
  var Placeholder = components.Placeholder
  var RangeControl = components.RangeControl
  var TextControl = components.TextControl
  var ToggleControl = components.ToggleControl
  var Fragment = element.Fragment
  var createElement = element.createElement
  var __ = i18n.__

  registerBlockType('wp-song-study/project-directory', {
    edit: function (props) {
      var attributes = props.attributes
      var setAttributes = props.setAttributes
      var blockProps = useBlockProps()

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: __('Project directory settings', 'wp-song-study-blocks') },
            createElement(TextControl, {
              label: __('Area slug', 'wp-song-study-blocks'),
              help: __(
                'Optional. Use a taxonomy slug like musica to show only projects from that area.',
                'wp-song-study-blocks'
              ),
              value: attributes.areaSlug || '',
              onChange: function (areaSlug) {
                setAttributes({ areaSlug: areaSlug })
              },
            }),
            createElement(RangeControl, {
              label: __('Projects per page', 'wp-song-study-blocks'),
              value: normalizePositiveInteger(attributes.postsPerPage, 9),
              onChange: function (postsPerPage) {
                setAttributes({ postsPerPage: normalizePositiveInteger(postsPerPage, 9) })
              },
              min: 1,
              max: 24,
            }),
            createElement(ToggleControl, {
              label: __('Show featured image', 'wp-song-study-blocks'),
              checked: !!attributes.showImage,
              onChange: function (showImage) {
                setAttributes({ showImage: showImage })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show excerpt', 'wp-song-study-blocks'),
              checked: !!attributes.showExcerpt,
              onChange: function (showExcerpt) {
                setAttributes({ showExcerpt: showExcerpt })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show area labels', 'wp-song-study-blocks'),
              checked: !!attributes.showArea,
              onChange: function (showArea) {
                setAttributes({ showArea: showArea })
              },
            }),
            createElement(ToggleControl, {
              label: __('Show collaborators', 'wp-song-study-blocks'),
              checked: !!attributes.showCollaborators,
              onChange: function (showCollaborators) {
                setAttributes({ showCollaborators: showCollaborators })
              },
            }),
            createElement(ToggleControl, {
              label: __('Only current user projects', 'wp-song-study-blocks'),
              checked: !!attributes.onlyCurrentUser,
              onChange: function (onlyCurrentUser) {
                setAttributes({ onlyCurrentUser: onlyCurrentUser })
              },
            }),
            createElement(TextControl, {
              label: __('Empty message', 'wp-song-study-blocks'),
              value: attributes.emptyMessage || '',
              onChange: function (emptyMessage) {
                setAttributes({ emptyMessage: emptyMessage })
              },
            }),
            createElement(TextControl, {
              label: __('Login message', 'wp-song-study-blocks'),
              value: attributes.loginMessage || '',
              onChange: function (loginMessage) {
                setAttributes({ loginMessage: loginMessage })
              },
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(Placeholder, {
            label: __('Project Directory', 'wp-song-study-blocks'),
            instructions: __(
              'Displays a reusable project listing with optional area filtering and current-user filtering.',
              'wp-song-study-blocks'
            ),
          })
        )
      )
    },
    save: function () {
      return null
    },
  })
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n)
