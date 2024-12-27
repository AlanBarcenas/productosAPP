import { startStimulusApp } from '@symfony/stimulus-bridge';
import { startStimulusApp } from '@symfony/stimulus-bundle';

// assets/bootstrap.js
import 'bootstrap'

const app = startStimulusApp();
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
// assets/bootstrap.js
import './styles/app.css';