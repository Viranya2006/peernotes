// PeerNotes - Main JavaScript Functions

// Theme Management
class ThemeManager {
    constructor() {
        this.theme = localStorage.getItem('theme') || 'light';
        this.init();
    }

    init() {
        this.createToggleButton();
        this.applyTheme();
        this.bindEvents();
    }

    createToggleButton() {
        const toggle = document.createElement('button');
        toggle.className = 'theme-toggle';
        toggle.innerHTML = this.theme === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon"></i>';
        toggle.setAttribute('aria-label', 'Toggle dark mode');
        toggle.setAttribute('title', 'Toggle dark mode');
        document.body.appendChild(toggle);
        this.toggleButton = toggle;
    }

    applyTheme() {
        document.documentElement.setAttribute('data-theme', this.theme);
        if (this.toggleButton) {
            this.toggleButton.innerHTML = this.theme === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon"></i>';
        }
    }

    bindEvents() {
        if (this.toggleButton) {
            this.toggleButton.addEventListener('click', () => this.toggleTheme());
        }
    }

    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        localStorage.setItem('theme', this.theme);
        this.applyTheme();
        
        // Add animation effect
        this.toggleButton.style.transform = 'scale(1.2) rotate(180deg)';
        setTimeout(() => {
            this.toggleButton.style.transform = 'scale(1) rotate(0deg)';
        }, 200);
    }
}

// Initialize theme manager
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});

// Utility Functions
class Utils {
    static showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="bi bi-${this.getToastIcon(type)} me-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        // Add styles
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-lg);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 400px;
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto remove
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, duration);
    }

    static getToastIcon(type) {
        const icons = {
            success: 'check-circle-fill',
            error: 'exclamation-triangle-fill',
            warning: 'exclamation-triangle-fill',
            info: 'info-circle-fill'
        };
        return icons[type] || 'info-circle-fill';
    }

    static formatFileSize(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' bytes';
        }
    }

    static timeAgo(date) {
        const now = new Date();
        const diff = now - new Date(date);
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        const months = Math.floor(days / 30);
        const years = Math.floor(days / 365);

        if (years > 0) return years + ' year' + (years > 1 ? 's' : '') + ' ago';
        if (months > 0) return months + ' month' + (months > 1 ? 's' : '') + ' ago';
        if (days > 0) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        if (hours > 0) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        if (minutes > 0) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
        return 'just now';
    }

    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    static throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
}

// Form Validation
class FormValidator {
    constructor(form) {
        this.form = form;
        this.rules = {};
        this.init();
    }

    init() {
        this.bindEvents();
    }

    addRule(fieldName, rule) {
        this.rules[fieldName] = rule;
    }

    bindEvents() {
        this.form.addEventListener('submit', (e) => {
            if (!this.validate()) {
                e.preventDefault();
            }
        });

        // Real-time validation
        Object.keys(this.rules).forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('blur', () => this.validateField(fieldName));
                field.addEventListener('input', Utils.debounce(() => this.validateField(fieldName), 300));
            }
        });
    }

    validate() {
        let isValid = true;
        Object.keys(this.rules).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
            }
        });
        return isValid;
    }

    validateField(fieldName) {
        const field = this.form.querySelector(`[name="${fieldName}"]`);
        const rule = this.rules[fieldName];
        
        if (!field || !rule) return true;

        const value = field.value.trim();
        const isValid = rule.validator(value);
        
        this.showFieldValidation(field, isValid, rule.message);
        return isValid;
    }

    showFieldValidation(field, isValid, message) {
        const feedback = field.parentNode.querySelector('.invalid-feedback') || 
                        field.parentNode.querySelector('.valid-feedback');
        
        if (feedback) {
            feedback.remove();
        }

        const div = document.createElement('div');
        div.className = isValid ? 'valid-feedback' : 'invalid-feedback';
        div.textContent = message;
        
        field.parentNode.appendChild(div);
        field.classList.toggle('is-valid', isValid);
        field.classList.toggle('is-invalid', !isValid);
    }
}

// File Upload Handler
class FileUploadHandler {
    constructor(input, options = {}) {
        this.input = input;
        this.options = {
            maxSize: 10 * 1024 * 1024, // 10MB
            allowedTypes: ['pdf', 'doc', 'docx', 'ppt', 'pptx'],
            onProgress: null,
            onSuccess: null,
            onError: null,
            ...options
        };
        this.init();
    }

    init() {
        this.createDropZone();
        this.bindEvents();
    }

    createDropZone() {
        const container = this.input.closest('.upload-container') || this.input.parentNode;
        const dropZone = document.createElement('div');
        dropZone.className = 'file-drop-zone';
        dropZone.innerHTML = `
            <div class="drop-zone-content">
                <i class="bi bi-cloud-upload"></i>
                <p>Drag & drop files here or click to browse</p>
                <small>Supports: PDF, DOC, DOCX, PPT, PPTX (Max 10MB)</small>
            </div>
        `;
        
        container.appendChild(dropZone);
        this.dropZone = dropZone;
    }

    bindEvents() {
        // Click to browse
        this.dropZone.addEventListener('click', () => this.input.click());
        
        // Drag and drop
        this.dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.dropZone.classList.add('dragover');
        });
        
        this.dropZone.addEventListener('dragleave', () => {
            this.dropZone.classList.remove('dragover');
        });
        
        this.dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            this.dropZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                this.handleFiles(files);
            }
        });
        
        // File input change
        this.input.addEventListener('change', (e) => {
            this.handleFiles(e.target.files);
        });
    }

    handleFiles(files) {
        const file = files[0];
        if (!file) return;

        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            Utils.showToast(validation.message, 'error');
            return;
        }

        // Show file info
        this.showFileInfo(file);
        
        // Upload file
        this.uploadFile(file);
    }

    validateFile(file) {
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!this.options.allowedTypes.includes(extension)) {
            return {
                valid: false,
                message: 'Invalid file type. Only PDF, DOC, DOCX, PPT, PPTX files are allowed.'
            };
        }
        
        if (file.size > this.options.maxSize) {
            return {
                valid: false,
                message: 'File size too large. Maximum size is 10MB.'
            };
        }
        
        return { valid: true };
    }

    showFileInfo(file) {
        this.dropZone.innerHTML = `
            <div class="file-info">
                <i class="bi bi-file-earmark"></i>
                <div class="file-details">
                    <strong>${file.name}</strong>
                    <small>${Utils.formatFileSize(file.size)}</small>
                </div>
                <div class="upload-progress">
                    <div class="progress-bar"></div>
                </div>
            </div>
        `;
    }

    uploadFile(file) {
        const formData = new FormData();
        formData.append('file', file);
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                const progressBar = this.dropZone.querySelector('.progress-bar');
                if (progressBar) {
                    progressBar.style.width = percentComplete + '%';
                }
                if (this.options.onProgress) {
                    this.options.onProgress(percentComplete);
                }
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    Utils.showToast('File uploaded successfully!', 'success');
                    if (this.options.onSuccess) {
                        this.options.onSuccess(response);
                    }
                } else {
                    Utils.showToast(response.message || 'Upload failed', 'error');
                    if (this.options.onError) {
                        this.options.onError(response);
                    }
                }
            } else {
                Utils.showToast('Upload failed', 'error');
                if (this.options.onError) {
                    this.options.onError({ message: 'Upload failed' });
                }
            }
        });
        
        xhr.addEventListener('error', () => {
            Utils.showToast('Network error', 'error');
            if (this.options.onError) {
                this.options.onError({ message: 'Network error' });
            }
        });
        
        xhr.open('POST', 'api/upload.php');
        xhr.send(formData);
    }
}

// Search Autocomplete
class SearchAutocomplete {
    constructor(input, options = {}) {
        this.input = input;
        this.options = {
            minLength: 2,
            delay: 300,
            maxResults: 10,
            ...options
        };
        this.suggestions = [];
        this.init();
    }

    init() {
        this.createSuggestionsContainer();
        this.bindEvents();
    }

    createSuggestionsContainer() {
        const container = document.createElement('div');
        container.className = 'suggestions-container';
        this.input.parentNode.appendChild(container);
        this.suggestionsContainer = container;
    }

    bindEvents() {
        this.input.addEventListener('input', Utils.debounce(() => {
            this.handleInput();
        }, this.options.delay));
        
        this.input.addEventListener('blur', () => {
            setTimeout(() => this.hideSuggestions(), 200);
        });
        
        this.input.addEventListener('focus', () => {
            if (this.suggestions.length > 0) {
                this.showSuggestions();
            }
        });
    }

    handleInput() {
        const query = this.input.value.trim();
        
        if (query.length < this.options.minLength) {
            this.hideSuggestions();
            return;
        }
        
        this.fetchSuggestions(query);
    }

    fetchSuggestions(query) {
        fetch(`api/search-suggestions.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                this.suggestions = data.suggestions || [];
                this.showSuggestions();
            })
            .catch(error => {
                console.error('Error fetching suggestions:', error);
            });
    }

    showSuggestions() {
        if (this.suggestions.length === 0) {
            this.hideSuggestions();
            return;
        }
        
        const html = this.suggestions.map(suggestion => `
            <div class="suggestion-item" data-value="${suggestion.value}">
                <i class="bi bi-${suggestion.icon} me-2"></i>
                <span>${suggestion.text}</span>
            </div>
        `).join('');
        
        this.suggestionsContainer.innerHTML = html;
        this.suggestionsContainer.style.display = 'block';
        
        // Bind click events
        this.suggestionsContainer.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                this.input.value = item.dataset.value;
                this.hideSuggestions();
                this.input.focus();
            });
        });
    }

    hideSuggestions() {
        this.suggestionsContainer.style.display = 'none';
    }
}

// Initialize common functionality
document.addEventListener('DOMContentLoaded', () => {
    // Initialize file upload handlers
    document.querySelectorAll('input[type="file"]').forEach(input => {
        new FileUploadHandler(input);
    });
    
    // Initialize search autocomplete
    document.querySelectorAll('.search-input').forEach(input => {
        new SearchAutocomplete(input);
    });
    
    // Add loading states to forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            }
        });
    });
    
    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add intersection observer for animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fadeIn');
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.resource-card, .featured-section').forEach(el => {
        observer.observe(el);
    });
});

// Export classes for use in other files
window.Utils = Utils;
window.FormValidator = FormValidator;
window.FileUploadHandler = FileUploadHandler;
window.SearchAutocomplete = SearchAutocomplete;
