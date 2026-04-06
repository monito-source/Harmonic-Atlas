import { __ } from '@wordpress/i18n'
import { BlockPlaceholder } from '../shared'

export default function Edit() {
  return (
    <BlockPlaceholder
      label={__('Project Presskit', 'wp-song-study-blocks')}
      instructions={__(
        'Displays the tagline, summary and links of the current project.',
        'wp-song-study-blocks'
      )}
    />
  )
}
