# PeerNotes - Academic Resource Sharing Platform

A full-stack web application for university students in Sri Lanka to share and discover academic resources like lecture notes, past papers, and presentations.

## Features

### Core Functionality
- **User Authentication**: Secure registration, login, and session management
- **File Upload**: Support for PDF, DOC, DOCX, PPT, PPTX files (max 10MB)
- **Resource Management**: Categorization by subject, course, and academic year
- **Search & Discovery**: Advanced search with filters and sorting options
- **Rating & Reviews**: 5-star rating system with user comments
- **Favorites**: Save and manage favorite resources
- **PDF Preview**: Built-in PDF viewer using PDF.js
- **Download Tracking**: Monitor download counts and popularity
- **Admin Panel**: Basic moderation tools for flagged content

### UI/UX Features
- **Responsive Design**: Mobile-first approach with Bootstrap 5
- **Dark Mode**: Toggle between light and dark themes
- **Futuristic Aesthetics**: Glassmorphism, gradients, and smooth animations
- **Accessibility**: WCAG AA compliant with keyboard navigation
- **Progressive Enhancement**: Works without JavaScript for core functionality

## Technology Stack

### Backend
- **PHP 7.4+**: Server-side logic and API endpoints
- **MySQL 8.0+**: Database with optimized schema and indexes
- **PDO**: Secure database interactions with prepared statements

### Frontend
- **HTML5**: Semantic markup with accessibility features
- **CSS3**: Custom properties, Grid, Flexbox, and animations
- **JavaScript (ES6+)**: Modern JavaScript with classes and modules
- **Bootstrap 5**: Responsive framework and components
- **PDF.js**: Client-side PDF rendering

### Security Features
- **Password Hashing**: PHP's `password_hash()` with bcrypt
- **SQL Injection Prevention**: PDO prepared statements
- **XSS Protection**: Input sanitization with `htmlspecialchars()`
- **File Upload Security**: Type validation and size limits
- **Session Management**: Secure session handling

## Installation & Setup

### Prerequisites
- **WAMP/XAMPP**: Local development environment
- **PHP 7.4+**: With PDO MySQL extension
- **MySQL 8.0+**: Database server
- **Web Server**: Apache/Nginx

### Installation Steps

1. **Clone/Download the Project**
   ```bash
   # Place files in your web server directory
   # For WAMP: C:\wamp64\www\peernotes
   # For XAMPP: C:\xampp\htdocs\peernotes
   ```

2. **Database Setup**
   ```sql
   -- Create database
   CREATE DATABASE peernotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   
   -- Import schema
   mysql -u root -p peernotes < database/schema.sql
   ```

3. **Configure Database Connection**
   ```php
   // Edit config/database.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'peernotes');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

4. **Set File Permissions**
   ```bash
   # Make uploads directory writable
   chmod 755 uploads/
   ```

5. **Start Web Server**
   - Start Apache and MySQL services
   - Navigate to `http://localhost/peernotes`

### Default Admin Account
- **Email**: admin@peernotes.lk
- **Password**: admin123

## Project Structure

```
peernotes/
├── index.php                 # Landing page
├── login.php                 # User login
├── register.php              # User registration
├── logout.php                # Session termination
├── upload.php                 # File upload form
├── search.php                 # Search interface
├── resource.php              # Resource detail page
├── profile.php               # User dashboard
├── admin.php                 # Admin panel
├── download.php              # File download handler
├── config/
│   └── database.php          # Database configuration
├── includes/
│   └── functions.php         # Helper functions
├── api/
│   ├── featured-resources.php
│   └── search-suggestions.php
├── assets/
│   ├── css/
│   │   └── style.css         # Main stylesheet
│   └── js/
│       ├── main.js           # Core JavaScript
│       └── home.js           # Home page scripts
├── database/
│   └── schema.sql            # Database schema
├── uploads/                   # File storage directory
└── README.md                 # This file
```

## Usage Guide

### For Students

1. **Registration**: Create an account with email and password
2. **Upload Resources**: Share your notes, papers, and presentations
3. **Search & Discover**: Find resources by subject, course, or keywords
4. **Rate & Review**: Help others by rating and reviewing resources
5. **Build Collection**: Save favorites for easy access

### For Administrators

1. **Access Admin Panel**: Login with admin credentials
2. **Review Flagged Content**: Check reported resources
3. **Moderate Content**: Approve or delete flagged resources
4. **Monitor Platform**: View statistics and recent activity

## API Endpoints

### Public Endpoints
- `GET /api/featured-resources.php` - Get featured resources
- `GET /api/search-suggestions.php?q=query` - Get search suggestions

### Protected Endpoints
- `POST /upload.php` - Upload new resource
- `POST /resource.php` - Rate/review resource
- `GET /download.php?id=123` - Download resource

## Security Considerations

### File Upload Security
- File type validation (whitelist approach)
- File size limits (10MB maximum)
- Secure file storage outside web root
- Virus scanning recommended for production

### Database Security
- Prepared statements prevent SQL injection
- Input sanitization prevents XSS attacks
- Password hashing with bcrypt
- Regular security updates recommended

### Session Security
- Secure session configuration
- Session regeneration on login
- Proper logout with session destruction
- CSRF protection recommended for production

## Performance Optimization

### Database Optimization
- Indexed columns for fast queries
- Optimized JOIN operations
- Pagination for large result sets
- Connection pooling recommended

### Frontend Optimization
- Minified CSS and JavaScript
- Image optimization
- CDN for static assets
- Progressive loading

## Browser Support

- **Chrome**: 90+
- **Firefox**: 88+
- **Safari**: 14+
- **Edge**: 90+
- **Mobile**: iOS 14+, Android 10+

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue on GitHub
- Contact: admin@peernotes.lk

## Roadmap

### Future Features
- Real-time notifications
- Advanced search with full-text indexing
- Mobile app development
- Integration with university systems
- Content recommendation engine
- Advanced analytics dashboard

---

**PeerNotes** - Empowering Sri Lankan students through shared knowledge.
