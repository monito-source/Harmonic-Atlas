import { InspectorControls, useBlockProps } from '@wordpress/block-editor'
import { PanelBody, Placeholder, TextControl, ToggleControl } from '@wordpress/components'
import { Fragment } from '@wordpress/element'
import { __ } from '@wordpress/i18n'

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps()

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody title={__('Current membership settings', 'wp-song-study-blocks')}>
          <TextControl
            label={__('Default target user ID', 'wp-song-study-blocks')}
            help={__(
              'Optional. Administrators can still switch to another user from the frontend view.',
              'wp-song-study-blocks'
            )}
            type="number"
            value={attributes.targetUserId || ''}
            onChange={(targetUserId) =>
              setAttributes({ targetUserId: Number.parseInt(targetUserId || '0', 10) || 0 })
            }
          />
          <ToggleControl
            label={__('Show projects', 'wp-song-study-blocks')}
            checked={!!attributes.showProjects}
            onChange={(showProjects) => setAttributes({ showProjects })}
          />
          <ToggleControl
            label={__('Show public preview', 'wp-song-study-blocks')}
            checked={!!attributes.showPreview}
            onChange={(showPreview) => setAttributes({ showPreview })}
          />
          <ToggleControl
            label={__('Show WordPress profile link', 'wp-song-study-blocks')}
            checked={!!attributes.showAdminLink}
            onChange={(showAdminLink) => setAttributes({ showAdminLink })}
          />
          <TextControl
            label={__('Login message', 'wp-song-study-blocks')}
            value={attributes.loginMessage || ''}
            onChange={(loginMessage) => setAttributes({ loginMessage })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <Placeholder
          label={__('Current Membership', 'wp-song-study-blocks')}
          instructions={__(
            'Displays a frontend space where the logged-in user can edit their own presskit and review their projects.',
            'wp-song-study-blocks'
          )}
        />
      </div>
    </Fragment>
  )
}
