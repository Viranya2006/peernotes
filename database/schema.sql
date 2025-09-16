-- PeerNotes Database Schema
-- Create database and tables for the academic resource sharing platform

CREATE DATABASE IF NOT EXISTS peernotes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE peernotes;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(191) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_admin TINYINT(1) DEFAULT 0,
    favorites JSON DEFAULT NULL,
    profile_picture VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    university VARCHAR(255) DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
);

-- Resources table
CREATE TABLE resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(10) NOT NULL,
    file_size INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    course VARCHAR(191) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_flagged TINYINT(1) DEFAULT 0,
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    ratings JSON DEFAULT NULL,
    reviews JSON DEFAULT NULL,
    favorites_count INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_subject (subject),
    INDEX idx_course (course),
    INDEX idx_academic_year (academic_year),
    INDEX idx_upload_date (upload_date),
    INDEX idx_is_flagged (is_flagged),
    FULLTEXT idx_search (title, description, subject, course)
);

-- Reviews table (separate from JSON in resources for better querying)
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_resource (user_id, resource_id),
    INDEX idx_resource_id (resource_id),
    INDEX idx_user_id (user_id),
    INDEX idx_rating (rating),
    INDEX idx_created_at (created_at)
);

-- Reports table for flagged content
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    user_id INT NOT NULL,
    reason ENUM('inappropriate', 'spam', 'copyright', 'offensive', 'other') NOT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    admin_notes TEXT DEFAULT NULL,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_resource_id (resource_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Categories table for better organization
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-book',
    color VARCHAR(7) DEFAULT '#6c757d',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default categories
INSERT INTO categories (name, description, icon, color) VALUES
('Computer Science', 'Programming, algorithms, software engineering', 'bi-laptop', '#007bff'),
('Mathematics', 'Calculus, algebra, statistics, discrete math', 'bi-calculator', '#28a745'),
('Physics', 'Mechanics, thermodynamics, quantum physics', 'bi-atom', '#dc3545'),
('Chemistry', 'Organic, inorganic, physical chemistry', 'bi-flask', '#ffc107'),
('Biology', 'Cell biology, genetics, ecology', 'bi-heart-pulse', '#17a2b8'),
('Engineering', 'Civil, mechanical, electrical engineering', 'bi-gear', '#6f42c1'),
('Business', 'Management, marketing, finance', 'bi-briefcase', '#fd7e14'),
('Medicine', 'Anatomy, physiology, pathology', 'bi-hospital', '#e83e8c'),
('Law', 'Constitutional law, criminal law, civil law', 'bi-scale', '#20c997'),
('Arts', 'Visual arts, music, literature', 'bi-palette', '#6c757d'),
('Literature', 'English literature, world literature', 'bi-book', '#17a2b8'),
('History', 'World history, Sri Lankan history', 'bi-clock-history', '#6f42c1'),
('Geography', 'Physical geography, human geography', 'bi-globe', '#28a745'),
('Economics', 'Microeconomics, macroeconomics', 'bi-graph-up', '#007bff'),
('Psychology', 'Cognitive psychology, social psychology', 'bi-person-heart', '#e83e8c'),
('Sociology', 'Social theory, research methods', 'bi-people', '#fd7e14'),
('Political Science', 'Government, international relations', 'bi-building', '#dc3545'),
('Education', 'Teaching methods, educational psychology', 'bi-mortarboard', '#ffc107'),
('Architecture', 'Design, construction, urban planning', 'bi-building', '#6c757d'),
('Agriculture', 'Crop science, animal husbandry', 'bi-tree', '#28a745');

-- Sessions table for better session management
CREATE TABLE user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
);

-- Activity log table for tracking user actions
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    resource_id INT DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resource_id) REFERENCES resources(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Create views for easier querying
CREATE VIEW resource_stats AS
SELECT 
    r.id,
    r.title,
    r.subject,
    r.course,
    r.upload_date,
    r.download_count,
    r.view_count,
    r.favorites_count,
    u.name as uploader_name,
    u.university as uploader_university,
    COALESCE(AVG(reviews.rating), 0) as average_rating,
    COUNT(reviews.id) as review_count,
    COUNT(CASE WHEN reviews.rating >= 4 THEN 1 END) as positive_reviews
FROM resources r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN reviews ON r.id = reviews.resource_id
WHERE r.is_flagged = 0
GROUP BY r.id;

-- Create view for featured resources
CREATE VIEW featured_resources AS
SELECT 
    r.*,
    u.name as uploader_name,
    u.university as uploader_university,
    COALESCE(AVG(reviews.rating), 0) as average_rating,
    COUNT(reviews.id) as review_count
FROM resources r
LEFT JOIN users u ON r.user_id = u.id
LEFT JOIN reviews ON r.id = reviews.resource_id
WHERE r.is_flagged = 0
GROUP BY r.id
HAVING average_rating >= 4.0 AND review_count >= 3
ORDER BY average_rating DESC, review_count DESC, r.upload_date DESC;

-- Create indexes for better performance
CREATE INDEX idx_resources_subject ON resources(subject);
CREATE INDEX idx_resources_course ON resources(course);
CREATE INDEX idx_resources_year ON resources(academic_year);
CREATE INDEX idx_resources_popularity ON resources(download_count, view_count);
CREATE INDEX idx_resources_recent ON resources(upload_date);

-- Create stored procedures for common operations
DELIMITER //

-- Procedure to update resource ratings
CREATE PROCEDURE UpdateResourceRatings(IN resource_id INT)
BEGIN
    DECLARE avg_rating DECIMAL(3,2);
    DECLARE rating_count INT;
    
    SELECT AVG(rating), COUNT(*) INTO avg_rating, rating_count
    FROM reviews 
    WHERE resource_id = resource_id;
    
    UPDATE resources 
    SET ratings = JSON_OBJECT('average', avg_rating, 'count', rating_count)
    WHERE id = resource_id;
END //

-- Procedure to update user favorites
CREATE PROCEDURE UpdateUserFavorites(IN user_id INT, IN resource_id INT, IN action ENUM('add', 'remove'))
BEGIN
    DECLARE current_favorites JSON;
    
    SELECT favorites INTO current_favorites FROM users WHERE id = user_id;
    
    IF action = 'add' THEN
        IF current_favorites IS NULL THEN
            SET current_favorites = JSON_ARRAY(resource_id);
        ELSE
            SET current_favorites = JSON_ARRAY_APPEND(current_favorites, '$', resource_id);
        END IF;
        
        UPDATE resources SET favorites_count = favorites_count + 1 WHERE id = resource_id;
    ELSE
        SET current_favorites = JSON_REMOVE(current_favorites, JSON_UNQUOTE(JSON_SEARCH(current_favorites, 'one', resource_id)));
        UPDATE resources SET favorites_count = favorites_count - 1 WHERE id = resource_id;
    END IF;
    
    UPDATE users SET favorites = current_favorites WHERE id = user_id;
END //

DELIMITER ;

-- Create triggers for automatic updates
DELIMITER //

-- Trigger to update resource ratings when review is added/updated
CREATE TRIGGER update_resource_ratings_after_review
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    CALL UpdateResourceRatings(NEW.resource_id);
END //

CREATE TRIGGER update_resource_ratings_after_review_update
AFTER UPDATE ON reviews
FOR EACH ROW
BEGIN
    CALL UpdateResourceRatings(NEW.resource_id);
END //

-- Trigger to update resource ratings when review is deleted
CREATE TRIGGER update_resource_ratings_after_review_delete
AFTER DELETE ON reviews
FOR EACH ROW
BEGIN
    CALL UpdateResourceRatings(OLD.resource_id);
END //

DELIMITER ;

-- Insert a default admin user (password: admin123)
INSERT INTO users (email, password, name, is_admin, university) VALUES
('admin@peernotes.lk', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 1, 'University of Colombo');

-- Create sample data for testing (optional)
-- INSERT INTO resources (user_id, title, description, file_path, file_name, file_type, file_size, subject, course, academic_year) VALUES
-- (1, 'Introduction to Computer Science', 'Basic concepts of computer science', 'uploads/sample.pdf', 'sample.pdf', 'pdf', 1024000, 'Computer Science', 'CS101', '2024/2025');

COMMIT;
