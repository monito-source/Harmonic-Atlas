import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, Placeholder } from '@wordpress/components';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Interface settings', 'wp-song-study-blocks')}>
          <ToggleControl
            label={__('Start in compact mode', 'wp-song-study-blocks')}
            checked={!!attributes.compactMode}
            onChange={(compactMode) => setAttributes({ compactMode })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <Placeholder
          label={__('Song Study Interface', 'wp-song-study-blocks')}
          instructions={__('Server-side rendered block. The interactive song browser/reader will appear on the frontend.', 'wp-song-study-blocks')}
        />
      </div>
    </>
  );
}
