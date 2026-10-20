import { render } from '@wordpress/element';
import App from './components/App';

const radice = document.getElementById( 'push-contempt-admin-root' );
if ( radice ) {
	render( <App />, radice );
}
