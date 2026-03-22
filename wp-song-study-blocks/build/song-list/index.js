(function (blocks, blockEditor, components, element, i18n) {
  var registerBlockType = blocks.registerBlockType;
  var InspectorControls = blockEditor.InspectorControls;
  var useBlockProps = blockEditor.useBlockProps;
  var PanelBody = components.PanelBody;
  var RangeControl = components.RangeControl;
  var SelectControl = components.SelectControl;
  var ToggleControl = components.ToggleControl;
  var TextControl = components.TextControl;
  var Placeholder = components.Placeholder;
  var Fragment = element.Fragment;
  var createElement = element.createElement;
  var __ = i18n.__;

  registerBlockType('wp-song-study/song-list', {
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
            { title: __('Song list settings', 'wp-song-study-blocks') },
            createElement(RangeControl, {
              label: __('Songs to show', 'wp-song-study-blocks'),
              value: attributes.postsToShow,
              onChange: function (postsToShow) { setAttributes({ postsToShow: postsToShow }); },
              min: 1,
              max: 100
            }),
            createElement(SelectControl, {
              label: __('Order by', 'wp-song-study-blocks'),
              value: attributes.orderBy,
              options: [
                { label: __('Title', 'wp-song-study-blocks'), value: 'title' },
                { label: __('Date', 'wp-song-study-blocks'), value: 'date' },
                { label: __('Menu order', 'wp-song-study-blocks'), value: 'menu_order' }
              ],
              onChange: function (orderBy) { setAttributes({ orderBy: orderBy }); }
            }),
            createElement(SelectControl, {
              label: __('Order', 'wp-song-study-blocks'),
              value: attributes.order,
              options: [
                { label: __('Ascending', 'wp-song-study-blocks'), value: 'ASC' },
                { label: __('Descending', 'wp-song-study-blocks'), value: 'DESC' }
              ],
              onChange: function (order) { setAttributes({ order: order }); }
            }),
            createElement(TextControl, {
              label: __('Filter by tonalidad slug', 'wp-song-study-blocks'),
              value: attributes.tonalidad || '',
              onChange: function (tonalidad) { setAttributes({ tonalidad: tonalidad }); }
            }),
            createElement(TextControl, {
              label: __('Filter by colección slug', 'wp-song-study-blocks'),
              value: attributes.coleccion || '',
              onChange: function (coleccion) { setAttributes({ coleccion: coleccion }); }
            }),
            createElement(ToggleControl, {
              label: __('Show tonalidad', 'wp-song-study-blocks'),
              checked: !!attributes.showKey,
              onChange: function (showKey) { setAttributes({ showKey: showKey }); }
            }),
            createElement(ToggleControl, {
              label: __('Show colección', 'wp-song-study-blocks'),
              checked: !!attributes.showCollection,
              onChange: function (showCollection) { setAttributes({ showCollection: showCollection }); }
            })
          )
        ),
        createElement(
          'div',
          blockProps,
          createElement(Placeholder, {
            label: __('Song List', 'wp-song-study-blocks'),
            instructions: __('Server-side rendered list of songs. Filters are configured in the block inspector.', 'wp-song-study-blocks')
          })
        )
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n);
