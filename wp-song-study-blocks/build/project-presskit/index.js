(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType
  var useBlockProps = blockEditor.useBlockProps
  var Placeholder = components.Placeholder
  var createElement = element.createElement
  var __ = i18n.__

  registerBlockType('wp-song-study/project-presskit', {
    edit: function () {
      var blockProps = useBlockProps()

      return createElement(
        'div',
        blockProps,
        createElement(Placeholder, {
          label: __('Project Presskit', 'wp-song-study-blocks'),
          instructions: __(
            'Displays the tagline, summary and links of the current project.',
            'wp-song-study-blocks'
          ),
        })
      )
    },
    save: function () {
      return null
    },
  })
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n)
