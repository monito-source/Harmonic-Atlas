import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl, ToggleControl, TextControl, Placeholder } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Song list settings', 'wp-song-study-blocks')}>
          <RangeControl
            label={__('Songs to show', 'wp-song-study-blocks')}
            value={attributes.postsToShow}
            onChange={(postsToShow) => setAttributes({ postsToShow })}
            min={1}
            max={100}
          />
          <SelectControl
            label={__('Order by', 'wp-song-study-blocks')}
            value={attributes.orderBy}
            options={[
              { label: __('Title', 'wp-song-study-blocks'), value: 'title' },
              { label: __('Date', 'wp-song-study-blocks'), value: 'date' },
              { label: __('Menu order', 'wp-song-study-blocks'), value: 'menu_order' },
            ]}
            onChange={(orderBy) => setAttributes({ orderBy })}
          />
          <SelectControl
            label={__('Order', 'wp-song-study-blocks')}
            value={attributes.order}
            options={[
              { label: __('Ascending', 'wp-song-study-blocks'), value: 'ASC' },
              { label: __('Descending', 'wp-song-study-blocks'), value: 'DESC' },
            ]}
            onChange={(order) => setAttributes({ order })}
          />
          <TextControl
            label={__('Filter by tonalidad slug', 'wp-song-study-blocks')}
            value={attributes.tonalidad}
            onChange={(tonalidad) => setAttributes({ tonalidad })}
          />
          <TextControl
            label={__('Filter by colección slug', 'wp-song-study-blocks')}
            value={attributes.coleccion}
            onChange={(coleccion) => setAttributes({ coleccion })}
          />
          <ToggleControl
            label={__('Show tonalidad', 'wp-song-study-blocks')}
            checked={!!attributes.showKey}
            onChange={(showKey) => setAttributes({ showKey })}
          />
          <ToggleControl
            label={__('Show colección', 'wp-song-study-blocks')}
            checked={!!attributes.showCollection}
            onChange={(showCollection) => setAttributes({ showCollection })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <Placeholder
          label={__('Song List', 'wp-song-study-blocks')}
          instructions={__('Server-side rendered list of songs. Filters are configured in the block inspector.', 'wp-song-study-blocks')}
        />
      </div>
    </>
  );
}
