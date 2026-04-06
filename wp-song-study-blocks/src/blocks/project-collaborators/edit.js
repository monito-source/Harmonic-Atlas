import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Placeholder, ToggleControl } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps();

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody title={__('Collaborator block settings', 'wp-song-study-blocks')}>
          <ToggleControl
            label={__('Show avatar', 'wp-song-study-blocks')}
            checked={!!attributes.showAvatar}
            onChange={(showAvatar) => setAttributes({ showAvatar })}
          />
          <ToggleControl
            label={__('Show bio', 'wp-song-study-blocks')}
            checked={!!attributes.showBio}
            onChange={(showBio) => setAttributes({ showBio })}
          />
          <ToggleControl
            label={__('Show portfolio link', 'wp-song-study-blocks')}
            checked={!!attributes.showLink}
            onChange={(showLink) => setAttributes({ showLink })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <Placeholder
          label={__('Project Collaborators', 'wp-song-study-blocks')}
          instructions={__(
            'Shows the users related to the current project. Place it inside a proyecto template or single view.',
            'wp-song-study-blocks'
          )}
        />
      </div>
    </Fragment>
  );
}
