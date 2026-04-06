import { __ } from '@wordpress/i18n'
import { BlockPlaceholder } from '../shared'

export default function Edit() {
  return (
    <BlockPlaceholder
      label={__('Project Contact', 'wp-song-study-blocks')}
      instructions={__(
        'Displays the contact details configured for the current project.',
        'wp-song-study-blocks'
      )}
    />
  )
}
