(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType;
  var InspectorControls = blockEditor.InspectorControls;
  var useBlockProps = blockEditor.useBlockProps;
  var PanelBody = components.PanelBody;
  var ToggleControl = components.ToggleControl;
  var Placeholder = components.Placeholder;
  var Fragment = element.Fragment;
  var createElement = element.createElement;
  var __ = i18n.__;

  registerBlockType('wp-song-study/interface', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps();

      return createElement(
        Fragment,
        null,
        createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            { title: __('Interface settings', 'wp-song-study-blocks') },
            createElement(ToggleControl, {
              label: __('Start in compact mode', 'wp-song-study-blocks'),
              checked: !!attributes.compactMode,
              onChange: function (compactMode) {
                setAttributes({ compactMode: compactMode });
              }
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(Placeholder, {
            label: __('Song Study Interface', 'wp-song-study-blocks'),
            instructions: __('Server-side rendered block. The interactive song browser/reader will appear on the frontend.', 'wp-song-study-blocks')
          })
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
