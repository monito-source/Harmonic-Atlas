import metadata from '../../../build/interface/block.json';
import edit from './edit';
import save from './save';
import { registerBlockType } from '@wordpress/blocks';

registerBlockType(metadata.name, {
  edit,
  save,
});
