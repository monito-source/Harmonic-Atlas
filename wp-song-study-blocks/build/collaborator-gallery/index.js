(function (blocks, blockEditor, components, element, i18n) {
  function normalizePositiveInteger(value, fallback) {
    var parsed = parseInt(value, 10)
    return Number.isNaN(parsed) || parsed < 0 ? fallback : parsed
  }

  var registerBlockType = blocks.registerBlockType
  var InspectorControls = blockEditor.InspectorControls
  var useBlockProps = blockEditor.useBlockProps
  var PanelBody = components.PanelBody
  var Placeholder = components.Placeholder
  var TextControl = components.TextControl
  var Fragment = element.Fragment
  var createElement = element.createElement
  var __ = i18n.__

  registerBlockType('wp-song-study/collaborator-gallery', {
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
            { title: __('Collaborator source', 'wp-song-study-blocks') },
            createElement(TextControl, {
              label: __('Collaborator user ID', 'wp-song-study-blocks'),
              help: __(
                'Optional. Leave empty to use the author archive or the current post author automatically.',
                'wp-song-study-blocks'
              ),
              type: 'number',
              value: attributes.userId || '',
              onChange: function (userId) {
                setAttributes({ userId: normalizePositiveInteger(userId, 0) })
              },
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(Placeholder, {
            label: __('Collaborator Gallery', 'wp-song-study-blocks'),
            instructions: __('Displays the press photo gallery of a collaborator.', 'wp-song-study-blocks'),
          })
        )
      )
    },
    save: function () {
      return null
    },
  })
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n)
