import { InspectorControls, useBlockProps } from '@wordpress/block-editor'
import { PanelBody, Placeholder, RangeControl, TextControl } from '@wordpress/components'
import { __ } from '@wordpress/i18n'

export function normalizePositiveInteger(value, fallback = 0) {
  const parsed = Number.parseInt(value, 10)

  if (Number.isNaN(parsed) || parsed < 0) {
    return fallback
  }

  return parsed
}

export function BlockPlaceholder({ label, instructions, children = null }) {
  const blockProps = useBlockProps()

  return (
    <div {...blockProps}>
      <Placeholder label={label} instructions={instructions}>
        {children}
      </Placeholder>
    </div>
  )
}

export function CollaboratorSourceControls({
  attributes,
  setAttributes,
  panelTitle,
  includePostsPerPage = false,
}) {
  return (
    <InspectorControls>
      <PanelBody title={panelTitle}>
        <TextControl
          label={__('Collaborator user ID', 'wp-song-study-blocks')}
          help={__(
            'Optional. Leave empty to use the author archive or the current post author automatically.',
            'wp-song-study-blocks'
          )}
          type="number"
          value={attributes.userId || ''}
          onChange={(userId) =>
            setAttributes({ userId: normalizePositiveInteger(userId, 0) })
          }
        />
        {includePostsPerPage ? (
          <RangeControl
            label={__('Number of projects', 'wp-song-study-blocks')}
            value={normalizePositiveInteger(attributes.postsPerPage, 6) || 6}
            onChange={(postsPerPage) =>
              setAttributes({ postsPerPage: normalizePositiveInteger(postsPerPage, 6) || 6 })
            }
            min={1}
            max={24}
          />
        ) : null}
      </PanelBody>
    </InspectorControls>
  )
}
