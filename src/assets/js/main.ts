// src/assets/js/main.ts - Entry point for Vite bundler
// This file imports all modules for bundling into a single main.js

// CSS
import '../css/main.css';
import '../css/theme-enhancements.css';

// Core
import './core/dashboard-core';

// Utils
import './utils/fetchWithTimeout';
import './utils/file-upload';
import './utils/ui-utils';
import './utils/uppercase';

// Shared maintenance utilities
import './shared/manutencao-utils';

// UI Utils
import './utils/ui/ErrorHandler';
import './utils/ui/LoadingManager';
import './utils/ui/theme-manager';
import './utils/showSuccessModal';

// Dashboard modules
import './modules/dashboard-charts';

// Log to confirm Vite is working
console.log('Vite build system initialized');
console.log('Build date:', new Date().toISOString());