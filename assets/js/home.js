// PeerNotes - Home Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Load featured resources
    loadFeaturedResources();
    
    // Initialize search functionality
    initializeSearch();
    
    // Add particle effects
    createParticleEffect();
});

// Load featured resources from API
async function loadFeaturedResources() {
    try {
        const response = await fetch('api/featured-resources.php');
        const data = await response.json();
        
        if (data.success) {
            renderFeaturedResources(data.resources);
        } else {
            showErrorMessage('Failed to load featured resources');
        }
    } catch (error) {
        console.error('Error loading featured resources:', error);
        showErrorMessage('Network error while loading resources');
    }
}

// Render featured resources in the carousel
function renderFeaturedResources(resources) {
    const container = document.getElementById('featured-resources');
    
    if (!resources || resources.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center">
                <div class="empty-state">
                    <i class="bi bi-book display-1 text-muted mb-3"></i>
                    <h4>No featured resources yet</h4>
                    <p class="text-muted">Be the first to upload academic resources!</p>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                </div>
            </div>
        `;
        return;
    }
    
    const html = resources.map((resource, index) => `
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="resource-card h-100 animate-slideInUp" style="animation-delay: ${index * 0.1}s">
                <div class="card-icon">
                    <i class="${getFileIcon(resource.file_type)}"></i>
                </div>
                <h5 class="card-title">${escapeHtml(resource.title)}</h5>
                <p class="card-subtitle">${escapeHtml(resource.subject)} • ${escapeHtml(resource.course)}</p>
                <p class="card-description">${escapeHtml(resource.description.substring(0, 100))}${resource.description.length > 100 ? '...' : ''}</p>
                
                <div class="card-meta">
                    <div class="rating-stars">
                        ${generateStars(resource.average_rating)}
                        <span class="ms-1 text-muted">(${resource.review_count})</span>
                    </div>
                    <small class="text-muted">${Utils.timeAgo(resource.upload_date)}</small>
                </div>
                
                <div class="card-actions mt-3">
                    <a href="resource.php?id=${resource.id}" class="btn btn-primary btn-sm">
                        <i class="bi bi-eye me-1"></i>View
                    </a>
                    <button class="btn btn-outline-secondary btn-sm ms-2" onclick="previewResource(${resource.id})">
                        <i class="bi bi-search me-1"></i>Preview
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = html;
}

// Initialize search functionality
function initializeSearch() {
    const searchForm = document.querySelector('.search-form');
    const searchInput = document.querySelector('.search-input');
    
    if (searchForm && searchInput) {
        // Add search suggestions
        const autocomplete = new SearchAutocomplete(searchInput, {
            minLength: 2,
            delay: 300
        });
        
        // Handle form submission
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `search.php?query=${encodeURIComponent(query)}`;
            }
        });
        
        // Add search animation
        searchInput.addEventListener('focus', function() {
            this.parentNode.classList.add('focused');
        });
        
        searchInput.addEventListener('blur', function() {
            this.parentNode.classList.remove('focused');
        });
    }
}

// Create particle effect for hero section
function createParticleEffect() {
    const heroSection = document.querySelector('.hero-section');
    if (!heroSection) return;
    
    const canvas = document.createElement('canvas');
    canvas.style.position = 'absolute';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.pointerEvents = 'none';
    canvas.style.zIndex = '1';
    
    heroSection.appendChild(canvas);
    
    const ctx = canvas.getContext('2d');
    let particles = [];
    
    function resizeCanvas() {
        canvas.width = heroSection.offsetWidth;
        canvas.height = heroSection.offsetHeight;
    }
    
    function createParticle() {
        return {
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height,
            size: Math.random() * 3 + 1,
            speedX: (Math.random() - 0.5) * 0.5,
            speedY: (Math.random() - 0.5) * 0.5,
            opacity: Math.random() * 0.5 + 0.2
        };
    }
    
    function initParticles() {
        particles = [];
        for (let i = 0; i < 50; i++) {
            particles.push(createParticle());
        }
    }
    
    function updateParticles() {
        particles.forEach(particle => {
            particle.x += particle.speedX;
            particle.y += particle.speedY;
            
            if (particle.x < 0 || particle.x > canvas.width) {
                particle.speedX *= -1;
            }
            if (particle.y < 0 || particle.y > canvas.height) {
                particle.speedY *= -1;
            }
        });
    }
    
    function drawParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        particles.forEach(particle => {
            ctx.beginPath();
            ctx.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(79, 70, 229, ${particle.opacity})`;
            ctx.fill();
        });
    }
    
    function animate() {
        updateParticles();
        drawParticles();
        requestAnimationFrame(animate);
    }
    
    // Initialize
    resizeCanvas();
    initParticles();
    animate();
    
    // Handle resize
    window.addEventListener('resize', () => {
        resizeCanvas();
        initParticles();
    });
}

// Utility functions
function getFileIcon(fileType) {
    const icons = {
        'pdf': 'bi-file-earmark-pdf text-danger',
        'doc': 'bi-file-earmark-word text-primary',
        'docx': 'bi-file-earmark-word text-primary',
        'ppt': 'bi-file-earmark-ppt text-warning',
        'pptx': 'bi-file-earmark-ppt text-warning'
    };
    return icons[fileType.toLowerCase()] || 'bi-file-earmark text-secondary';
}

function generateStars(rating) {
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 !== 0;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);
    
    let stars = '';
    
    // Full stars
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="bi bi-star-fill text-warning"></i>';
    }
    
    // Half star
    if (hasHalfStar) {
        stars += '<i class="bi bi-star-half text-warning"></i>';
    }
    
    // Empty stars
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="bi bi-star text-warning"></i>';
    }
    
    return stars;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showErrorMessage(message) {
    const container = document.getElementById('featured-resources');
    container.innerHTML = `
        <div class="col-12 text-center">
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${message}
            </div>
        </div>
    `;
}

// Preview resource function
function previewResource(resourceId) {
    // This would open a modal with resource preview
    // For now, redirect to resource detail page
    window.location.href = `resource.php?id=${resourceId}`;
}

// Add scroll animations
function addScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slideInUp');
            }
        });
    }, observerOptions);
    
    // Observe all resource cards
    document.querySelectorAll('.resource-card').forEach(card => {
        observer.observe(card);
    });
}

// Initialize scroll animations when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(addScrollAnimations, 1000);
});

// Add keyboard navigation support
document.addEventListener('keydown', function(e) {
    // Focus search input with Ctrl+K or Cmd+K
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.focus();
        }
    }
});

// Add loading state management
function showLoadingState() {
    const container = document.getElementById('featured-resources');
    container.innerHTML = `
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading featured resources...</p>
        </div>
    `;
}

// Add error state management
function showErrorState(message) {
    const container = document.getElementById('featured-resources');
    container.innerHTML = `
        <div class="col-12 text-center">
            <div class="error-state">
                <i class="bi bi-exclamation-triangle display-1 text-warning mb-3"></i>
                <h4>Oops! Something went wrong</h4>
                <p class="text-muted">${message}</p>
                <button class="btn btn-primary" onclick="loadFeaturedResources()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                </button>
            </div>
        </div>
    `;
}
