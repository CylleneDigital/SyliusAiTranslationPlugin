import { Application } from '@hotwired/stimulus';
import ConfirmController from './js/confirm-controller.js';
import EditModalController from './js/edit-modal-controller.js';
import GenerationController from './js/generation-controller.js';
import HeaderHelpController from './js/header-help-controller.js';
import ReviewModalController from './js/review-modal-controller.js';
import ScopeFilterController from './js/scope-filter-controller.js';
import './css/admin.css';

const app = Application.start();
app.register('ait-admin-confirm', ConfirmController);
app.register('ait-admin-edit-modal', EditModalController);
app.register('ait-admin-generation', GenerationController);
app.register('ait-admin-header-help', HeaderHelpController);
app.register('ait-admin-review-modal', ReviewModalController);
app.register('ait-admin-scope-filter', ScopeFilterController);
