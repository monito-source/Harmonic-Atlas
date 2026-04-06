import { InspectorControls, useBlockProps } from '@wordpress/block-editor'
import { PanelBody, Placeholder, RangeControl, TextControl, ToggleControl } from '@wordpress/components'
import { Fragment } from '@wordpress/element'
import { __ } from '@wordpress/i18n'

function normalizePositiveInteger(value, fallback = 1) {
  const parsed = Number.parseInt(value, 10)

  if (Number.isNaN(parsed) || parsed < 1) {
    return fallback
  }

  return parsed
}

export default function Edit({ attributes, setAttributes }) {
  const blockProps = useBlockProps()

  return (
    <Fragment>
      <InspectorControls>
        <PanelBody title={__('Project directory settings', 'wp-song-study-blocks')}>
          <TextControl
            label={__('Area slug', 'wp-song-study-blocks')}
            help={__(
              'Optional. Use a taxonomy slug like musica to show only projects from that area.',
              'wp-song-study-blocks'
            )}
            value={attributes.areaSlug || ''}
            onChange={(areaSlug) => setAttributes({ areaSlug })}
          />
          <RangeControl
            label={__('Projects per page', 'wp-song-study-blocks')}
            value={normalizePositiveInteger(attributes.postsPerPage, 9)}
            onChange={(postsPerPage) =>
              setAttributes({ postsPerPage: normalizePositiveInteger(postsPerPage, 9) })
            }
            min={1}
            max={24}
          />
          <ToggleControl
            label={__('Show featured image', 'wp-song-study-blocks')}
            checked={!!attributes.showImage}
            onChange={(showImage) => setAttributes({ showImage })}
          />
          <ToggleControl
            label={__('Show excerpt', 'wp-song-study-blocks')}
            checked={!!attributes.showExcerpt}
            onChange={(showExcerpt) => setAttributes({ showExcerpt })}
          />
          <ToggleControl
            label={__('Show area labels', 'wp-song-study-blocks')}
            checked={!!attributes.showArea}
            onChange={(showArea) => setAttributes({ showArea })}
          />
          <ToggleControl
            label={__('Show collaborators', 'wp-song-study-blocks')}
            checked={!!attributes.showCollaborators}
            onChange={(showCollaborators) => setAttributes({ showCollaborators })}
          />
          <ToggleControl
            label={__('Only current user projects', 'wp-song-study-blocks')}
            checked={!!attributes.onlyCurrentUser}
            onChange={(onlyCurrentUser) => setAttributes({ onlyCurrentUser })}
          />
          <TextControl
            label={__('Empty message', 'wp-song-study-blocks')}
            value={attributes.emptyMessage || ''}
            onChange={(emptyMessage) => setAttributes({ emptyMessage })}
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
          label={__('Project Directory', 'wp-song-study-blocks')}
          instructions={__(
            'Displays a reusable project listing with optional area filtering and current-user filtering.',
            'wp-song-study-blocks'
          )}
        />
      </div>
    </Fragment>
  )
}
